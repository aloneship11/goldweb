define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'user/user/index',
                    add_url: 'user/user/add',
                    edit_url: 'user/user/edit',
                    del_url: 'user/user/del',
                    multi_url: 'user/user/multi',
                    table: 'user',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'user.id',
                fixedColumns: true,
                fixedRightNumber: 1,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id'), sortable: true},
                        {field: 'username', title: __('Username'), operate: 'LIKE'},
                        {field: 'nickname', title: __('Nickname'), operate: 'LIKE'},
                        {field: 'mobile', title: __('Mobile'), operate: 'LIKE'},
                        {field: 'email', title: __('Email'), operate: 'LIKE'},
                        {field: 'expiretime', title: '到期时间', formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange', sortable: true},
                        {field: 'alert_email', title: '提醒邮箱', operate: 'LIKE'},
                        {field: 'base_alert', title: '基准价提醒', formatter: Table.api.formatter.toggle, searchList: {1: '开', 0: '关'}},
                        {field: 'highlow_alert', title: '新高低提醒', formatter: Table.api.formatter.toggle, searchList: {1: '开', 0: '关'}},
                        {field: 'alert_threshold', title: '波动阈值', operate: 'BETWEEN'},
                        {field: 'status', title: __('Status'), formatter: Table.api.formatter.status, searchList: {normal: __('Normal'), hidden: __('Hidden')}},
                        {field: 'operate', title: __('Operate'), table: table, buttons: [
                            {name: 'subscribe', text: '订阅', icon: 'fa fa-bell', classname: 'btn btn-xs btn-info btn-dialog', url: 'user/user/subscribe', extend: 'data-area=\'["640px","520px"]\''},
                            {name: 'recharge', text: '充值', icon: 'fa fa-rmb', classname: 'btn btn-xs btn-success btn-dialog', url: 'user/user/recharge', extend: 'data-area=\'["450px","440px"]\''}
                        ], events: Table.api.events.operate, formatter: Table.api.formatter.operate}
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
        recharge: function () {
            Controller.api.bindevent();
        },
        subscribe: function () {
            // 添加订阅表单：成功后刷新当前弹窗（保留在订阅页）
            Form.api.bindevent($("#subscribe-form"), function () {
                location.reload();
                return false;
            });
            // 取消订阅
            $(document).on('click', '.btn-unsubscribe', function () {
                var id = $(this).data('id');
                Fast.api.ajax('user/user/unsubscribe/ids/' + id, {ids: id}, function () {
                    location.reload();
                    return false;
                });
            });
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});
