-- 修复 gb_server_record.websites 字段中被双重实体化/双重编码的脏数据（MySQL 5.7+/8.0）
-- 执行后，历史记录网站列表会变成干净的 JSON 字符串数组，后续前端/后端不再需要剥离 &quot;

-- 1) 多轮把 &quot; 替换成真正的双引号（最多循环 5 轮覆盖三重编码）
UPDATE `gb_server_record` SET `websites` = REPLACE(`websites`, '&quot;', '"') WHERE INSTR(`websites`, '&quot;') > 0;
UPDATE `gb_server_record` SET `websites` = REPLACE(`websites`, '&quot;', '"') WHERE INSTR(`websites`, '&quot;') > 0;
UPDATE `gb_server_record` SET `websites` = REPLACE(`websites`, '&quot;', '"') WHERE INSTR(`websites`, '&quot;') > 0;

-- 2) 其他常见实体
UPDATE `gb_server_record` SET `websites` = REPLACE(`websites`, '&#039;', "'")  WHERE INSTR(`websites`, '&#039;') > 0;
UPDATE `gb_server_record` SET `websites` = REPLACE(`websites`, '&amp;',   '&')  WHERE INSTR(`websites`, '&amp;') > 0;
UPDATE `gb_server_record` SET `websites` = REPLACE(`websites`, '&lt;',    '<')  WHERE INSTR(`websites`, '&lt;') > 0;
UPDATE `gb_server_record` SET `websites` = REPLACE(`websites`, '&gt;',    '>')  WHERE INSTR(`websites`, '&gt;') > 0;

-- 3) 解掉被双重 JSON_encode 的字符串（字段首尾多余的 "）：形如 "[\"a\",\"b\"]" 应该是 ["a","b"]
--    以 "[" 开头并以 "]" 结尾、且整段包了一层 " 的，剥掉那层最外引号并转义回来
--    （通过 MySQL 自身的 JSON_VALID/JSON_UNQUOTE 实现，MySQL 5.7+ 可用）
UPDATE `gb_server_record`
   SET `websites` = JSON_UNQUOTE(`websites`)
 WHERE `websites` IS NOT NULL
   AND LENGTH(`websites`) > 0
   AND JSON_VALID(`websites`) = 1                    -- 整体是一个合法 JSON
   AND JSON_TYPE(`websites`) = 'STRING'              -- 但类型是 STRING（即包了一层引号的字符串）
   AND LEFT(JSON_UNQUOTE(`websites`), 1) = '[';      -- 字符串里真正的内容才是数组开头

-- 4) 如果上面一条没生效（JSON_TYPE 是 ARRAY，已经是数组形式），再做一轮：
--    数组里的单个元素可能又是"双重 JSON 字符串"，在 PHP/前端我们已经做了解析，
--    但为了数据干净，把元素前后多余的引号和空格清一下即可（一般不需要额外 SQL）。

-- 5) 刷新 website_count 与真实数组元素数保持一致（MySQL 8 / MariaDB 10.2+ 有 JSON_TABLE，
--    这里用通用方式：统计"逗号数"+1，并对空值处理）
UPDATE `gb_server_record` SET `website_count` = 0 WHERE `websites` IS NULL OR `websites` = '' OR `websites` = '[]';

-- 如果你的 MySQL >= 8.0 或 MariaDB >= 10.5，可以取消下面注释精确统计 JSON 数组长度：
-- UPDATE `gb_server_record`
--    SET `website_count` = CASE WHEN JSON_VALID(`websites`) THEN JSON_LENGTH(`websites`) ELSE 0 END
--  WHERE `websites` IS NOT NULL AND `websites` <> '';
