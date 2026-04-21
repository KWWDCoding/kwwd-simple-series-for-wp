/**
 * KWWD Article Series - Admin JavaScript
 */
(function($) {
    'use strict';

    var SeriesAdmin = {
        init: function() {
            this.initSortable();
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