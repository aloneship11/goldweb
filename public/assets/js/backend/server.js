define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            Table.api.init({
                extend: {
                    index_url: 'server/index' + location.search,
                    del_url: 'server/del',
                    table: 'server_record',
                }
            });

            var table = $("#table");

            // HTML 实体解码（&quot; &amp; &#039; 等）
            function htmlDecode(str){
                if(typeof str !== 'string') return str;
                return $('<div/>').html(str).text();
            }

            // 解析 websites 字段：优先用后端已解析好的 websites_arr，否则自解
            function parseSites(row){
                if($.isArray(row.websites_arr) && row.websites_arr.length) {
                    return $.map(row.websites_arr, function(s){
                        s = htmlDecode(String(s||'')).trim().replace(/^["'\s]+|["'\s]+$/g,'');
                        return s;
                    }).filter(function(s){return s.length>0;});
                }
                var v = row.websites;
                if(!v) return [];
                if($.isArray(v)) return $.map(v, function(s){ return htmlDecode(String(s||'')).trim().replace(/^["'\s]+|["'\s]+$/g,''); }).filter(function(s){return s.length>0;});
                if(typeof v === 'string'){
                    var raw = htmlDecode(v);
                    try{
                        var a = JSON.parse(raw);
                        if($.isArray(a)) return $.map(a, function(s){
                            s = htmlDecode(String(s||'')).trim().replace(/^["'\s]+|["'\s]+$/g,'');
                            return s;
                        }).filter(function(s){return s.length>0;});
                        if(typeof a === 'string'){
                            try{
                                var b = JSON.parse(htmlDecode(a));
                                if($.isArray(b)) return $.map(b, function(s){
                                    s = htmlDecode(String(s||'')).trim().replace(/^["'\s]+|["'\s]+$/g,'');
                                    return s;
                                }).filter(function(s){return s.length>0;});
                            }catch(e){}
                        }
                    }catch(e){}
                    // 兜底：剥掉首尾 []、按常见分隔符切
                    raw = raw.replace(/^\s*\[|\]\s*$/g,'');
                    var parts = raw.split(/[,"\s]+/);
                    return $.grep($.map(parts, function(s){
                        return htmlDecode(s).trim().replace(/^["'\s]+|["'\s]+$/g,'');
                    }), function(s){return s.length>0;});
                }
                return [];
            }

            // 时间戳 -> YYYY-MM-DD HH:mm:ss
            function formatTs(ts){
                if(!ts) return '—';
                var t = new Date(parseInt(ts)*1000);
                if(isNaN(t.getTime())) return String(ts);
                function pad(n){return n<10?'0'+n:n;}
                return t.getFullYear()+'-'+pad(t.getMonth()+1)+'-'+pad(t.getDate())
                    +' '+pad(t.getHours())+':'+pad(t.getMinutes())+':'+pad(t.getSeconds());
            }

            function onlineState(row){
                var now = Math.floor(new Date().getTime()/1000);
                if(!row.last_ping_time) return {cls:'default', text:'未知'};
                var diff = now - row.last_ping_time;
                if(diff < 86400) return {cls:'success', text:'在线'};   // <1天
                if(diff < 3*86400) return {cls:'warning', text:'3天内'};
                return {cls:'danger', text: Math.floor(diff/86400)+'天未心跳'};
            }

            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                pk: 'id',
                sortName: 'last_ping_time',
                sortOrder: 'desc',
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: 'ID', width: 50, operate: false},
                        {field: 'server_name', title: '服务器名称', operate: 'LIKE', formatter: function(v,row){
                            var st = onlineState(row);
                            return '<span class="label label-'+st.cls+'" style="margin-right:6px;">'+st.text+'</span> <b>'+(v||'—')+'</b>';
                        }},
                        {field: 'server_ip', title: 'IP 地址', width: 135, operate: 'LIKE', formatter: function(v){
                            return '<code style="background:#f4f4f5;padding:2px 6px;border-radius:3px;color:#c7254e;">'+(v||'')+'</code>';
                        }},
                        {field: 'website_count', title: '网站数', width: 80, operate:'BETWEEN', formatter: function(v,row){
                            var n = (v && parseInt(v)>0) ? parseInt(v) : parseSites(row).length;
                            if(!n) return '<span class="text-muted">0</span>';
                            return '<span class="badge bg-light-blue">'+n+'</span>';
                        }},
                        {field: 'websites_arr', title: '运行的网站', operate: false, formatter: function(v,row){
                            var sites = parseSites(row);
                            if(!sites.length) return '<span class="text-muted">—</span>';
                            var html = '', show = Math.min(sites.length, 8);
                            for(var i=0;i<show;i++){
                                var s = sites[i] || '';
                                // 自动检测 http/https
                                var url = /^https?:/i.test(s) ? s : ('http://'+s);
                                html += '<a href="'+url+'" target="_blank" class="label label-info" '
                                    + 'style="display:inline-block;margin:2px 4px 2px 0;padding:3px 8px;font-weight:normal;text-decoration:none;">'
                                    + $('<div/>').text(s).html() + '</a>';
                            }
                            if(sites.length > show){
                                var extra = sites.slice(show).map(function(s){
                                    var url = /^https?:/i.test(s) ? s : ('http://'+s);
                                    return '<a href="'+url+'" target="_blank" class="label label-default" '
                                        + 'style="display:inline-block;margin:2px 4px 2px 0;padding:3px 8px;font-weight:normal;text-decoration:none;">'
                                        + $('<div/>').text(s).html() + '</a>';
                                }).join('');
                                html += ' <span class="sites-more-box" data-more="'+encodeURIComponent(extra)+'">'
                                    + '<a href="javascript:;" class="sites-more-btn label label-default" style="padding:3px 8px;">+'
                                    + (sites.length-show)+' <i class="fa fa-chevron-down"></i></a>'
                                    + '<span class="sites-more hidden" style="display:block;margin-top:4px;">'+extra+'</span></span>';
                            }
                            return html;
                        }, cellStyle: function(){
                            return {css:{'max-width':'520px','min-width':'320px','white-space':'normal','line-height':'1.8'}};
                        }},
                        {field: 'wipe_scheduled', title: '待清理', width: 90, operate:false, formatter: function(v){
                            return v ? '<span class="label label-danger"><i class="fa fa-exclamation-circle"></i> 待执行</span>'
                                    : '<span class="label label-success"><i class="fa fa-check"></i> 否</span>';
                        }},
                        {field: 'wipe_done', title: '清理状态', width: 160, operate:false, formatter: function(v,row){
                            if(row.wipe_scheduled) return '<span class="label label-warning"><i class="fa fa-clock-o"></i> 等待执行</span>';
                            if(v){
                                var t = row.wipe_time ? formatTs(row.wipe_time) : '';
                                return '<span class="label label-danger" title="'+t+'"><i class="fa fa-trash"></i> 已清理 '+(t||'')+'</span>';
                            }
                            return '<span class="label label-default"><i class="fa fa-ban"></i> 未清理</span>';
                        }},
                        {field: 'ping_count', title: '心跳次数', width: 90, operate:false},
                        {field: 'last_ping_time', title: '最后心跳', width: 165,
                            operate:'RANGE', addclass:'datetimerange', autocomplete:false,
                            formatter: function(v,row){
                                var st = onlineState(row);
                                return '<span class="text-'+st.cls+'"><i class="fa fa-circle" style="font-size:8px;"></i> '
                                    + (v ? formatTs(v) : '—') + '</span>';
                            }},
                    ]
                ],
                onPostBody: function(){
                    // 展开 / 收起更多站点
                    $('.sites-more-btn').off('click').on('click', function(){
                        var box = $(this).closest('.sites-more-box');
                        var more = box.find('.sites-more');
                        more.toggleClass('hidden');
                        if(more.hasClass('hidden')){
                            $(this).find('i').attr('class','fa fa-chevron-down');
                        }else{
                            $(this).find('i').attr('class','fa fa-chevron-up');
                        }
                    });
                }
            });

            Table.api.bindevent(table);

            // 标记清空
            $('#btn-mark-wipe').on('click', function(){
                var ids = Table.api.selectedids(table);
                if(!ids.length){ Layer.alert('请先勾选服务器'); return; }
                Layer.confirm(
                    '已勾选 <b>'+ids.length+'</b> 台服务器，确定将在下一次心跳时执行 <b style="color:#d9534f;">清空 /www/wwwroot</b>？<br><br>' +
                    '<span class="text-warning"><i class="fa fa-exclamation-triangle"></i> 此操作会删除该服务器所有网站文件，请谨慎使用！</span>',
                    {icon: 3, title:'危险操作确认', btn: ['确认标记', '取消'], area:['480px','280px']},
                    function(i){
                        $.post(Backend.api.fixurl('server/markWipe'), {ids: ids.join(',')}, function(ret){
                            if(ret.code==1){ Layer.msg(ret.msg,{icon:1,time:1500}); table.bootstrapTable('refresh'); }
                            else{ Layer.alert(ret.msg); }
                        },'json');
                        Layer.close(i);
                    }
                );
            });

            // 取消清理
            $('#btn-cancel-wipe').on('click', function(){
                var ids = Table.api.selectedids(table);
                if(!ids.length){ Layer.alert('请先勾选服务器'); return; }
                $.post(Backend.api.fixurl('server/cancelWipe'), {ids: ids.join(',')}, function(ret){
                    if(ret.code==1){ Layer.msg(ret.msg,{icon:1,time:1500}); table.bootstrapTable('refresh'); }
                    else{ Layer.alert(ret.msg); }
                },'json');
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
