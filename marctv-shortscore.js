jQuery(function ($) {

    $('body').addClass('js');

    $('.' + marctvmoderatejs.pluginprefix + '-report').click(function () {

        if (confirm(marctvmoderatejs.confirm_report)) {
            var element = $(this);
            var cid = $(this).data('cid');
            var nonce = $(this).data('nonce');

            $(element).addClass('marctv-moderate-loading');
            $(element).text(marctvmoderatejs.reporting_string + '…');

            $.ajax({
                type: 'POST',
                url: marctvmoderatejs.adminurl,
                data: {
                    action: marctvmoderatejs.pluginprefix + '_flag',
                    id: cid,
                    _ajax_nonce: nonce
                },
                success: function (response_data) {
                    $(element).removeClass('marctv-moderate-loading');

                    var msg = $(document.createElement('span'))
                        .addClass(marctvmoderatejs.pluginprefix + '-report ' + marctvmoderatejs.pluginprefix + '-success')
                        .text(response_data);
                    $(element).replaceWith(msg);
                },
                dataType: 'html'
            });

        }

        return false;

    });

});