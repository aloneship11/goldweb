define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/userproduct/index' + location.search,
                    add_url: 'user/userproduct/add',
                    edit_url: 'user/userproduct/edit',
                    del_url: 'user/userproduct/del',
                    multi_url: 'user/userproduct/multi',
                    table: 'user_product',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'id',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: 'ID'},
                        {field: 'user.nickname', title: '用户', operate: 'LIKE'},
                        {field: 'product.product_name', title: '产品', operate: 'LIKE'},
                        {field: 'status', title: '状态', searchList: {"0":"停用","1":"启用"}, formatter: Table.api.formatter.toggle},
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
