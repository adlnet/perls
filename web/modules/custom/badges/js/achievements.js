/**
 * @file
 * Attaches behaviors for the Achievements module.
 */

(function ($) {

    Drupal.behaviors.achievements = {
        attach: function (context, settings) {

            var height = 104;
            var margin = 10;
            var showBadgeDuration = 3000;
            var timeout;
            var notifications = $('.achievement-notification', context).once('processed').dialog({
                dialogClass: 'achievement-notification-dialog',
                autoOpen: false,
                show: 'fade',
                hide: 'fade',
                closeOnEscape: false,
                draggable: false,
                resizable: false,
                height: height,
                width: 500,
                position: {
                    my: "right bottom",
                    at: "right bottom",
                    of: window,
                    collision: "none"
                },
                closeText: '',
                close: onClose
            });

            notifications.on('dialogopen', function (event, ui) {
              // Each badge will be shown for a couple of seconds.
              timeout = setTimeout(closeDialog, showBadgeDuration);
            });

            if (notifications.length) {
                setTimeout(showNextAchievement, 500);
            }


            function showNextAchievement() {
                if (notifications.length) {
                    notifications.eq(0).dialog('open').hover(
                    function () {
                        // Pretty sure this doesn't work, though it does get called.
                        clearTimeout(timeout);
                    },
                    function () {
                        // the longer the list, longer the onscreen time.
                        timeout = setTimeout(closeDialog, showBadgeDuration);
                    }
                );

                }
            }

            function onClose() {
                var i, length, properties, widget;
                notifications = notifications.not(notifications[0]);
                length = notifications.length;

                function close() {
                    showNextAchievement();
                    timeout = setTimeout(closeDialog, showBadgeDuration);
                }

                if (length) {
                    widget = notifications.eq(0).dialog('widget');
                    widget.animate("", close);
                }
            }

            function closeDialog() {
                notifications.eq(0).dialog('close');
            }

        }
    };

})(jQuery);
