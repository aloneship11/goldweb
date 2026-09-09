define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'alert/log/index' + location.search,
                    add_url: 'alert/log/add',
                    edit_url: 'alert/log/edit',
                    del_url: 'alert/log/del',
                    multi_url: 'alert/log/multi',
                    import_url: 'alert/log/import',
                    table: 'alert_log',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                fixedColumns: true,
                fixedRightNumber: 1,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: 'ID'},
                        {field: 'user_id', title: '用户'},
                        {field: 'product_id', title: '产品'},
                        {field: 'type', title: '提醒类型', searchList: {"base":"基准价","newhigh":"新高","newlow":"新低"}, operate: 'LIKE'},
                        {field: 'price', title: '价格', operate:'BETWEEN'},
                        {field: 'title', title: '标题', operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
                        {field: 'channel', title: '渠道', operate: 'LIKE'},
                        {field: 'status', title: '状态', searchList: {"success":"成功","fail":"失败"}, formatter: Table.api.formatter.label},
                        {field: 'createtime', title: '创建时间', operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
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
