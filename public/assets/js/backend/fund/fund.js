define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'fund/fund/index' + location.search,
                    add_url: 'fund/fund/add',
                    edit_url: 'fund/fund/edit',
                    del_url: 'fund/fund/del',
                    multi_url: 'fund/fund/multi',
                    import_url: 'fund/fund/import',
                    table: 'fund',
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
                        {field: 'fund_name', title: '基金名称', operate: 'LIKE'},
                        {field: 'fund_code', title: '基金代码', operate: 'LIKE'},
                        {field: 'fund_type', title: '基金类型', operate: 'LIKE'},
                        {field: 'api_url', title: '采集地址', operate: 'LIKE', formatter: Table.api.formatter.url},
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
