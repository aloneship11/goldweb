define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'order/index' + location.search,
                    add_url: 'order/add',
                    edit_url: 'order/edit',
                    del_url: 'order/del',
                    multi_url: 'order/multi',
                    import_url: 'order/import',
                    table: 'order',
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
                        {field: 'order_no', title: '订单号', operate: 'LIKE'},
                        {field: 'user_id', title: '用户'},
                        {field: 'plan_id', title: '套餐'},
                        {field: 'amount', title: '金额', operate:'BETWEEN'},
                        {field: 'pay_amount', title: '实付金额', operate:'BETWEEN'},
                        {field: 'pay_type', title: '支付方式', searchList: {"manual":"手动","wechat":"微信","alipay":"支付宝"}, operate: 'LIKE', formatter: Table.api.formatter.normal},
                        {field: 'status', title: '状态', searchList: {"pending":"待支付","paid":"已支付","cancel":"已取消","refund":"已退款"}, formatter: Table.api.formatter.label},
                        {field: 'expiretime', title: '到期时间', operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'paytime', title: '支付时间', operate:'RANGE', addclass:'datetimerange', autocomplete:false, formatter: Table.api.formatter.datetime},
                        {field: 'remark', title: '备注', operate: 'LIKE', table: table, class: 'autocontent', formatter: Table.api.formatter.content},
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
