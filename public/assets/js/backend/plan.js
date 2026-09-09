define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'plan/index' + location.search,
                    add_url: 'plan/add',
                    edit_url: 'plan/edit',
                    del_url: 'plan/del',
                    multi_url: 'plan/multi',
                    import_url: 'plan/import',
                    table: 'plan',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'weigh',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: 'ID'},
                        {field: 'name', title: '名称', operate: 'LIKE'},
                        {field: 'price', title: '价格', operate:'BETWEEN'},
                        {field: 'duration', title: '天数'},
                        {field: 'description', title: '描述', operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
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
