# coding=utf-8
"""
gold_monitor 库 → goldweb 库 数据迁移脚本
==========================================
把旧项目 gold_monitor 库的 gold_product / gold_price_log / gold_base_price
迁移到 goldweb 库的 gb_gold_product / gb_gold_price / gb_gold_base。

关键处理：
  1. 时间字段 DATETIME → Unix 时间戳 (INT)
  2. 产品表按 product_sku 匹配，建立 旧ID→新ID 映射，避免重复插入
  3. 价格记录按 (新product_id, createtime) 去重，避免重复导入
  4. 基准价按 product_id upsert

用法：
  python migrate_gold_monitor.py              # 迁移全部
  python migrate_gold_monitor.py --dry-run    # 只打印不写入
  python migrate_gold_monitor.py --products   # 只迁产品
  python migrate_gold_monitor.py --prices     # 只迁价格
  python migrate_gold_monitor.py --base        # 只迁基准价

依赖：pip install pymysql
"""
import argparse
import sys
import time
from datetime import datetime

import pymysql

# ================== 数据库配置 ==================

# 源库：gold_monitor（旧项目库，默认指向线上）
SRC_DB = {
    'host': '8.217.120.52',
    'port': 3306,
    'user': 'aloneship',
    'password': 'Ff2022888',
    'database': 'gold_monitor',
    'charset': 'utf8mb4',
}

# 目标库：goldweb（新项目库，默认指向本地；线上运行时改成线上配置）
DST_DB = {
    'host': '8.217.120.52',
    'port': 3306,
    'user': 'aloneship',
    'password': 'Ff2022888',
    'database': 'goldweb',
    'charset': 'utf8mb4',
}

# 单次批量插入大小
BATCH_SIZE = 500


# ================== 工具函数 ==================

def dt_to_ts(dt_str):
    """DATETIME 字符串 → Unix 时间戳"""
    if not dt_str:
        return None
    if isinstance(dt_str, datetime):
        return int(dt_str.timestamp())
    # 兼容 "2026-07-07 13:30:34" / "2026-07-07"
    for fmt in ('%Y-%m-%d %H:%M:%S', '%Y-%m-%d', '%Y-%m-%d %H:%M'):
        try:
            return int(datetime.strptime(str(dt_str).strip(), fmt).timestamp())
        except ValueError:
            continue
    return None


def log(msg):
    print(f"[{datetime.now().strftime('%H:%M:%S')}] {msg}")


# ================== 迁移逻辑 ==================

def migrate_products(src_cur, dst_cur, dry_run=False):
    """迁移产品表：按 product_sku 匹配，建立 旧ID→新ID 映射"""
    log("==== 迁移产品表 ====")
    src_cur.execute(
        "SELECT id, bank_name, product_name, product_sku, api_url, api_headers, status, created_at "
        "FROM gold_product ORDER BY id"
    )
    src_rows = src_cur.fetchall()
    log(f"源库产品数: {len(src_rows)}")

    id_map = {}  # 旧 product_id → 新 product_id
    inserted = 0
    updated = 0

    for r in src_rows:
        sku = r['product_sku'] or ''
        # 先查目标库是否已存在该 sku
        dst_cur.execute(
            "SELECT id FROM gb_gold_product WHERE product_sku=%s", (sku,)
        )
        existing = dst_cur.fetchone()

        ts = dt_to_ts(r['created_at'])
        status_val = '1' if int(r['status']) == 1 else '0'

        if existing:
            new_id = existing['id']
            if not dry_run:
                dst_cur.execute(
                    "UPDATE gb_gold_product SET bank_name=%s, product_name=%s, api_url=%s, "
                    "api_headers=%s, status=%s, updatetime=%s WHERE id=%s",
                    (r['bank_name'], r['product_name'], r['api_url'],
                     r['api_headers'], status_val, ts, new_id)
                )
            updated += 1
        else:
            if not dry_run:
                dst_cur.execute(
                    "INSERT INTO gb_gold_product (bank_name, product_name, product_sku, api_url, "
                    "api_headers, status, weigh, createtime, updatetime) "
                    "VALUES (%s, %s, %s, %s, %s, %s, 0, %s, %s)",
                    (r['bank_name'], r['product_name'], sku, r['api_url'],
                     r['api_headers'], status_val, ts, ts)
                )
                new_id = dst_cur.lastrowid
            else:
                new_id = f"<new for sku={sku}>"
            inserted += 1

        id_map[r['id']] = new_id

    log(f"产品迁移完成: 新增 {inserted}，更新 {updated}")
    log(f"ID 映射: {id_map}")
    return id_map


def migrate_prices(src_cur, dst_cur, id_map, dry_run=False):
    """迁移价格记录：按映射后的 product_id + createtime 去重"""
    log("==== 迁移价格记录 ====")
    if not id_map:
        log("无产品ID映射，跳过价格迁移")
        return 0

    # 只迁移那些 product_id 在映射表里的记录
    valid_old_ids = list(id_map.keys())
    placeholders = ','.join(['%s'] * len(valid_old_ids))

    src_cur.execute(
        f"SELECT id, product_id, price, remark, created_at "
        f"FROM gold_price_log WHERE product_id IN ({placeholders}) ORDER BY id",
        valid_old_ids
    )
    src_rows = src_cur.fetchall()
    log(f"源库价格记录数: {len(src_rows)}")

    # 先查目标库已有的 (product_id, createtime) 组合，用于去重
    existing_keys = set()
    for old_id, new_id in id_map.items():
        dst_cur.execute(
            "SELECT createtime FROM gb_gold_price WHERE product_id=%s", (new_id,)
        )
        for row in dst_cur.fetchall():
            existing_keys.add((new_id, row['createtime']))

    log(f"目标库已存在价格记录去重键数: {len(existing_keys)}")

    inserted = 0
    skipped = 0
    batch = []

    for r in src_rows:
        new_pid = id_map.get(r['product_id'])
        if not new_pid:
            skipped += 1
            continue

        ts = dt_to_ts(r['created_at'])
        if not ts:
            skipped += 1
            continue

        key = (new_pid, ts)
        if key in existing_keys:
            skipped += 1
            continue

        batch.append((new_pid, r['price'], r['remark'] or '', ts))
        existing_keys.add(key)  # 防止源库内自己重复

        if len(batch) >= BATCH_SIZE:
            if not dry_run:
                dst_cur.executemany(
                    "INSERT INTO gb_gold_price (product_id, price, remark, createtime) "
                    "VALUES (%s, %s, %s, %s)",
                    batch
                )
            inserted += len(batch)
            batch = []

    if batch:
        if not dry_run:
            dst_cur.executemany(
                "INSERT INTO gb_gold_price (product_id, price, remark, createtime) "
                "VALUES (%s, %s, %s, %s)",
                batch
            )
        inserted += len(batch)

    log(f"价格迁移完成: 新增 {inserted}，跳过 {skipped}")
    return inserted


def migrate_base(src_cur, dst_cur, id_map, dry_run=False):
    """迁移基准价表：按映射后的 product_id upsert"""
    log("==== 迁移基准价 ====")
    if not id_map:
        log("无产品ID映射，跳过基准价迁移")
        return 0

    valid_old_ids = list(id_map.keys())
    placeholders = ','.join(['%s'] * len(valid_old_ids))

    src_cur.execute(
        f"SELECT product_id, base_price, updated_at "
        f"FROM gold_base_price WHERE product_id IN ({placeholders})",
        valid_old_ids
    )
    src_rows = src_cur.fetchall()
    log(f"源库基准价记录数: {len(src_rows)}")

    upserted = 0
    for r in src_rows:
        new_pid = id_map.get(r['product_id'])
        if not new_pid:
            continue
        ts = dt_to_ts(r['updated_at']) or int(time.time())
        if not dry_run:
            # 先查是否存在
            dst_cur.execute(
                "SELECT id FROM gb_gold_base WHERE product_id=%s", (new_pid,)
            )
            ex = dst_cur.fetchone()
            if ex:
                dst_cur.execute(
                    "UPDATE gb_gold_base SET base_price=%s, updatetime=%s WHERE product_id=%s",
                    (r['base_price'], ts, new_pid)
                )
            else:
                dst_cur.execute(
                    "INSERT INTO gb_gold_base (product_id, base_price, updatetime) VALUES (%s, %s, %s)",
                    (new_pid, r['base_price'], ts)
                )
        upserted += 1

    log(f"基准价迁移完成: upsert {upserted} 条")
    return upserted


# ================== 主入口 ==================

def main():
    parser = argparse.ArgumentParser(description='gold_monitor → goldweb 数据迁移')
    parser.add_argument('--dry-run', action='store_true', help='只打印不写入')
    parser.add_argument('--products', action='store_true', help='只迁产品')
    parser.add_argument('--prices', action='store_true', help='只迁价格')
    parser.add_argument('--base', action='store_true', help='只迁基准价')
    args = parser.parse_args()

    # 如果没有指定任何单项，则全部迁移
    all_mode = not (args.products or args.prices or args.base)

    log(f"源库: {SRC_DB['host']}/{SRC_DB['database']}")
    log(f"目标: {DST_DB['host']}/{DST_DB['database']}")
    if args.dry_run:
        log("⚠ DRY-RUN 模式：不实际写入")
    log("")

    src_conn = pymysql.connect(
        host=SRC_DB['host'], port=SRC_DB['port'],
        user=SRC_DB['user'], password=SRC_DB['password'],
        database=SRC_DB['database'], charset=SRC_DB['charset'],
        cursorclass=pymysql.cursors.DictCursor
    )
    dst_conn = pymysql.connect(
        host=DST_DB['host'], port=DST_DB['port'],
        user=DST_DB['user'], password=DST_DB['password'],
        database=DST_DB['database'], charset=DST_DB['charset'],
        cursorclass=pymysql.cursors.DictCursor
    )

    try:
        src_cur = src_conn.cursor()
        dst_cur = dst_conn.cursor()

        id_map = None

        # 1. 产品（价格和基准价都依赖产品 ID 映射，所以产品必须先迁）
        if all_mode or args.products:
            id_map = migrate_products(src_cur, dst_cur, args.dry_run)
            if not args.dry_run:
                dst_conn.commit()
                log("产品已提交\n")

        # 2. 价格
        if all_mode or args.prices:
            if id_map is None:
                # 单独迁价格时，从目标库重建 ID 映射（按 sku）
                src_cur.execute("SELECT id, product_sku FROM gold_product")
                id_map = {}
                for r in src_cur.fetchall():
                    sku = r['product_sku'] or ''
                    dst_cur.execute(
                        "SELECT id FROM gb_gold_product WHERE product_sku=%s", (sku,)
                    )
                    ex = dst_cur.fetchone()
                    if ex:
                        id_map[r['id']] = ex['id']
                    else:
                        log(f"⚠ 源产品 id={r['id']} sku={sku} 在目标库找不到，跳过其价格")
            migrate_prices(src_cur, dst_cur, id_map, args.dry_run)
            if not args.dry_run:
                dst_conn.commit()
                log("价格已提交\n")

        # 3. 基准价
        if all_mode or args.base:
            if id_map is None:
                src_cur.execute("SELECT id, product_sku FROM gold_product")
                id_map = {}
                for r in src_cur.fetchall():
                    sku = r['product_sku'] or ''
                    dst_cur.execute(
                        "SELECT id FROM gb_gold_product WHERE product_sku=%s", (sku,)
                    )
                    ex = dst_cur.fetchone()
                    if ex:
                        id_map[r['id']] = ex['id']
            migrate_base(src_cur, dst_cur, id_map, args.dry_run)
            if not args.dry_run:
                dst_conn.commit()
                log("基准价已提交\n")

        log("✅ 迁移完成")
        if not args.dry_run:
            log("提示：可执行以下 SQL 验证：")
            log("  SELECT COUNT(*) FROM gb_gold_product;")
            log("  SELECT COUNT(*) FROM gb_gold_price;")
            log("  SELECT COUNT(*) FROM gb_gold_base;")

    except Exception as e:
        log(f"❌ 迁移失败: {e}")
        import traceback
        traceback.print_exc()
        sys.exit(1)
    finally:
        src_conn.close()
        dst_conn.close()


if __name__ == '__main__':
    main()
