/**
 * @file: Formtips module file - altered
 */

(function ($) {

  Drupal.behaviors.perlsFormtipsAltered = {
    attach: function (context, settings) {

      function hideOnClickOutside(element, $description) {
        var outsideClickListener = function (event) {
          var $target = $(event.target);
          if (!$target.hasClass('formtip') && !$target.hasClass('formtips-processed')) {
            $description.toggleClass('formtips-show', false);
          }
        }

        $(document).on('click', outsideClickListener);
      }

      var formtip_settings = settings.formtips;
      var selectors = formtip_settings.selectors;
      if ($.isArray(selectors)) {
        selectors = selectors.join(', ');
      }

      var $descriptions = $('.form-item .description,.form-item .filter-guidelines, .field--widget-autocomplete-deluxe .form-item .description')
        .not(selectors)
        .not('.formtips-processed');

      // Filter out empty descriptions. This helps avoid the password strength
      // description getting caught in a help.
      $descriptions = $descriptions.filter(function () {
        return $.trim($(this).text()) !== '';
      });
      if (formtip_settings.max_width.length) {
        $descriptions.css('max-width', formtip_settings.max_width);
      }

      // Hide descriptions when escaped is hit.
      $(document).on('keyup', function (e) {
        if (e.which === 27) {
          $descriptions.removeClass('formtips-show');
        }
      });

      $descriptions.once('formtips').each(function () {
        var isParagraph = false;
        var $formtip = $('<a class="formtip"></a>');
        var $description = $(this);
        var $item = $description.closest('.form-item');

        // If there's a filter wrapper check that.
        var $label = $item.find('label:not(.visually-hidden)').first();

        // Look for a fieldset legend or draggable table label.
        if (!$label.length) {
          $label = $item.find('.fieldset-legend,.label').first();
        }

        // Use the fieldset if the item is a radio or checkbox.
        var $fieldset = $item.find('.fieldset-legend');
        if ($fieldset.length && $item.find('input[type="checkbox"], input[type="radio"]').length) {
          $label = $fieldset;
        }

        // We need to handle the paragraph differently because the label element
        // isn't proper to us.
        if ($description.closest('.field--widget-paragraphs, .field--widget-entity-reference-paragraphs').length) {
          $label = $item.find('table .field-label').first();
          isParagraph = true;
        }

        // Look for a widget label.
        var $widget = $item.closest('.autocomplete-deluxe-value-container').closest('.field--widget-autocomplete-deluxe');
        if ($widget.length) {
          $item = $widget;
          $label = $item.find('label:not(.visually-hidden)').first();
        }

        // If there is no label, skip.
        if (!$label.length) {
          return;
        }

        $description.addClass('formtips-processed');

        $item.addClass('formtips-item');
        $description.toggleClass('formtips-show', false);
        $label.append($formtip);

        // If the current tip text belongs to paragraph where the tip text
        // appears at the question mark not under the field.
        if (isParagraph) {
          $formtip.append($description);
        }


        if (formtip_settings.trigger_action === 'click') {
          $formtip.on('click', function () {
            $description.toggleClass('formtips-show');
            return false;
          });
          // Hide description when clicking elsewhere.
          hideOnClickOutside($item[0], $description);

        }
        else {
          $formtip.hoverIntent({
            sensitivity: formtip_settings.sensitivity,
            interval: formtip_settings.interval,
            over: function () {
              $description.toggleClass('formtips-show', true);
            },
            timeout: formtip_settings.timeout,
            out: function () {
              $description.toggleClass('formtips-show', false);
            }
          });
        }
      });
    }
  };

})(jQuery);
