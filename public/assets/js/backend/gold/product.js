define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'gold/product/index' + location.search,
                    add_url: 'gold/product/add',
                    edit_url: 'gold/product/edit',
                    del_url: 'gold/product/del',
                    multi_url: 'gold/product/multi',
                    import_url: 'gold/product/import',
                    table: 'gold_product',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                fixedColumns: true,
                fixedRightNumber: 1,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: 'ID'},
                        {field: 'bank_name', title: '银行', operate: 'LIKE'},
                        {field: 'product_name', title: '产品名称', operate: 'LIKE'},
                        {field: 'product_sku', title: 'SKU代码', operate: 'LIKE'},
                        {field: 'api_url', title: '采集地址', operate: 'LIKE', formatter: Table.api.formatter.url},
                        {field: 'record_threshold', title: '记录阈值', operate:'BETWEEN'},
                        {field: 'status', title: '状态', searchList: {"0":"停用","1":"启用"}, formatter: Table.api.formatter.toggle},
                        {field: 'weigh', title: '权重', operate: false},
                        {field: 'createtime', title: '创建时间', operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'updatetime', title: '更新时间', operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'operate', title: '操作', table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
