(function ($, Drupal) {
  Drupal.behaviors.pushNotificationTimeField = {
    attach: function (context, settings) {
      const timefield = $('.timepicker');
      timefield.timepicker({
        'step': parseInt(drupalSettings.pushNotificationTimeField.step),
        'timeFormat': 'g:i a',
        'disableTextInput': 'true',
        'scrollDefault': 46800,
        'noneOption': ['None']
      });

      timefield.change(function () {
        $(this).attr('value', timefield.val());
      });
    }
  };
})(jQuery, Drupal);
