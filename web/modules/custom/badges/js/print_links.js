(function ($, Drupal) {
    Drupal.behaviors.AchievementPrintLinks = {
        attach: function (context, settings) {
            $(".print-url", context)
                .once("setUp")
                .each(function () {
                    $(this).click(function (e) {
                        e.preventDefault();
                        $("body").after(Drupal.theme.ajaxProgressIndicatorFullscreen());
                        $(window)
                            .once("printListener")
                            .on("achievementprint", function () {
                                $(".ajax-progress-fullscreen").remove();
                            });

                        $("<iframe>")
                            .css("visibility", "hidden")
                            .attr("src", e.currentTarget.href)
                            .appendTo("body");
                    });
                });

            var url = window.document.location.href;
            if (url.indexOf("print=true") > 0) {
                window.print();
                var printEvent = new CustomEvent("achievementprint");
                window.parent.dispatchEvent(printEvent);
            }
        }
    };

})(jQuery, Drupal);
