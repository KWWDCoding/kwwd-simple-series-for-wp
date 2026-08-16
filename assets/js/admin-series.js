/**
 * KWWD Article Series - Admin JavaScript
 */
(function($) {
    'use strict';

    var SeriesAdmin = {
        init: function() {
            this.initSortable();
            this.initAddSeries();
        },

        initSortable: function() {
            $('.kwwd-series-posts-list').each(function() {
                var $list = $(this);
                var seriesId = $list.data('series-id');

                if (!seriesId) return;

                $list.sortable({
                    handle: '.dashicons-menu',
                    placeholder: 'kwwd-series-post-item ui-sortable-placeholder',
                    axis: 'y',
                    update: function(event, ui) {
                        SeriesAdmin.saveOrder(seriesId, $list);
                    }
                });
            });
        },

        initAddSeries: function() {
            var $btn = $('#kwwd-add-series');
            if (!$btn.length) return;

            var $input = $('#kwwd-new-series-title');
            var $msg = $('#kwwd-series-add-msg');

            $btn.on('click', function() {
                var title = $.trim($input.val());
                if (!title) {
                    SeriesAdmin.showAddMsg($msg, kwwdSeriesAdmin.strings.add_series_error, true);
                    return;
                }

                $btn.prop('disabled', true);
                $.ajax({
                    url: kwwdSeriesAdmin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'kwwd_series_create',
                        title: title,
                        nonce: kwwdSeriesAdmin.create_nonce
                    },
                    success: function(response) {
                        $btn.prop('disabled', false);
                        if (response.success) {
                            SeriesAdmin.addSeriesCheckbox(response.data.id, response.data.title);
                            $input.val('');
                            SeriesAdmin.showAddMsg($msg, kwwdSeriesAdmin.strings.add_series_success, false);
                        } else {
                            var data = response.data;
                            if (data && typeof data === 'object' && data.code === 'duplicate' && data.id) {
                                SeriesAdmin.showDuplicateMsg($msg, data.id, data.title);
                            } else {
                                SeriesAdmin.showAddMsg($msg, (data && data.message) ? data.message : (data || kwwdSeriesAdmin.strings.add_series_error), true);
                            }
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false);
                        SeriesAdmin.showAddMsg($msg, kwwdSeriesAdmin.strings.add_series_error, true);
                    }
                });
            });
        },

        addSeriesCheckbox: function(id, title) {
            var $existing = $('.kwwd-series-checkboxes').find('input[name="kwwd_post_series_ids[]"][value="' + id + '"]');
            if ($existing.length) {
                $existing.prop('checked', true);
                return;
            }
            var $list = $('.kwwd-series-checkboxes');
            $('#kwwd-series-empty').remove();
            if (!$list.length) {
                $list = $('<div class="kwwd-series-checkboxes" style="max-height:200px;overflow-y:auto;"></div>');
                $('#kwwd_post_series_nonce').closest('.inside').find('.kwwd-series-add-box').before($list);
            }
            var $row = $('<p><label></label></p>');
            $row.find('label').append(
                $('<input>').attr({ type: 'checkbox', name: 'kwwd_post_series_ids[]', value: id }).prop('checked', true)
            ).append(' ').append($('<span>').text(title));
            $list.append($row);
        },

        showDuplicateMsg: function($msg, id, title) {
            $msg.empty().show();
            $msg.css('color', '#d63638');
            $('<span>').text(kwwdSeriesAdmin.strings.duplicate_exists + ' ').appendTo($msg);
            var $link = $('<a href="#"></a>').text(kwwdSeriesAdmin.strings.duplicate_add_link);
            $link.on('click', function(e) {
                e.preventDefault();
                SeriesAdmin.addSeriesCheckbox(id, title);
                $('#kwwd-new-series-title').val('');
                SeriesAdmin.showAddMsg($msg, kwwdSeriesAdmin.strings.duplicate_added, false);
            });
            $msg.append($link);
        },

        showAddMsg: function($msg, message, isError) {
            $msg.text(message).show();
            $msg.css('color', isError ? '#d63638' : '#00a32a');
        },

        saveOrder: function(seriesId, $list) {
            var postOrders = [];
            $list.find('.kwwd-series-post-item').each(function(index) {
                postOrders.push($(this).data('id'));
            });

            $.ajax({
                url: kwwdSeriesAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'kwwd_series_update_post_order',
                    series_id: seriesId,
                    post_orders: postOrders,
                    nonce: kwwdSeriesAdmin.nonce
                },
                beforeSend: function() {
                    $list.css('opacity', '0.5');
                },
                success: function(response) {
                    $list.css('opacity', '1');
                    if (response.success) {
                        SeriesAdmin.showNotice(kwwdSeriesAdmin.strings.saved, 'success');
                    }
                },
                error: function() {
                    $list.css('opacity', '1');
                    SeriesAdmin.showNotice('Error saving order', 'error');
                }
            });
        },

        showNotice: function(message, type) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').after($notice);
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };

    $(document).ready(function() {
        SeriesAdmin.init();
    });

})(jQuery);