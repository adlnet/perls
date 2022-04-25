/**
 * @file
 * Custom JS for PERLS.
 */

(function ($, Drupal, drupalSettings, Sortable) {
  'use strict';

  Drupal.behaviors.PerlsTheme = {
    attach: function (context, settings) {
      if (settings.appearance) {
        $('body.perls-content-manager, body.perls-learner .c-header, body.l-page--path--user.l-page--user-login, body.l-page--path--user.l-page--user-password, body.l-page--path--user.l-page--user-register').css({
          backgroundImage: 'url(' + settings.appearance.custom_background + ')',
          backgroundRepeat: settings.appearance.custom_background_repeat,
          backgroundPosition: settings.appearance.custom_background_anchor,
          backgroundSize: settings.appearance.custom_background_size,
          backgroundBlendMode: 'normal',
          backgroundAttachment: 'fixed',
        });
      }

      // Content Manager menu hide/show controls.
      $('body.perls-content-manager .c-nav__toggle').once().on('click', function() {
        $('body').toggleClass('cm-menu-open');
      });

      $('.js-slick-slider > ul', context).once().on('init', function (event, slick) {
        $(this).attr('slick_id', slick.instanceUid);
      });

      /**
       * Slick sliders
       */
      $('.js-slick-slider > ul', context).slick({
        dots: false,
        autoplay: false,
        arrows: true,
        infinite: false,
        speed: 250,
        cssEase: 'linear',
        variableWidth: true,
        draggable: true,
        swipeToSlide: true,
      });

      // When viewing a course, automatically slide to the first
      // incomplete lesson so the learner can resume where they left off.
      $('.c-node--full--course .js-slick-slider > ul', context).each(function () {
        var $slider = $(this);
        var slides = $('li', $slider);

        // Checking whether "description card" exists.
        // * Helps to determine the "resume" position in the slider. *
        var x = $(slides[0]).hasClass('course-description') ? 1 : 0;

        for (var i = x; i < slides.length; ++i) {
          if ($('.flag.completed', slides[i]).length === 0) {
            if (i > x) {
              $slider.slick('slickGoTo', i);
            }
            return;
          }
        }
      });

      // Focus on the input when a select2 element is opened.
      $(document).on('select2:open', function (e) {
        var container = document.getElementById('select2-' + e.target.id + '-results').closest('.select2-container--open');
        var input = container && container.querySelector('.select2-search__field');
        if (input) {
          input.focus();
        }
      })
      // Redundant remove element tracking since draggable from SortableJS
      // causes unexpected behavior.
      document.addEventListener('mouseup', function(e) {
        if (!e.target.closest('.select2-selection__choice__remove')) return;
        // Trigger removal via click so select2 handles value update.
        e.target.click();
      }, false);

      $('.select2-widget', context).on('select2-init', function (e) {
        var config = $(e.target).data('select2-config');
        config.language = {
          inputTooShort: function () {
            return ('minInputText' in e.currentTarget.dataset) ? e.currentTarget.dataset.minInputText : "Search for selection...";
          },
          noResults: function () {
            return ('noResultsText' in e.currentTarget.dataset) ? e.currentTarget.dataset.noResultsText : "No results found";
          }
        }
        $(e.target).data('select2-config', config);
      });

      /**
       * For each card that is over window height, adds toggle.
       */
      $('.c-node--card--flash-card', context).each(function (index, item) {
        var toggleButton = '<button class="o-button--more js-toggle" aria-label="Read More" data-toggled="this"></button>';
        if ($(item).height() > $(window).height()) {
          $(item).addClass('js-this has-toggle');
          $(item).find('.c-card__content').append(toggleButton);
        }
      });

      // Ensures the quiz scrolls back to the top so the feedback is fully in view.
      // This is needed in the context of a test where the card height is constant
      // and not determined by the intrinsic height.
      $('.c-quiz__option a', context).click(function () {
        var $container = $(this).parents('.c-card__content').first();
        setTimeout(function () {
          $container.scrollTop(0);
        }, 600);
      });

      /**
       * Contrib back button block.
       */
      if ($('.c-block-go-back-history') && window.history.length > 1) {
        // Button is hidden by default unless there is history. This prevents a
        // "FOUC" that would be seen if the back button was simply removed.
        $('.c-block-go-back-history').removeClass('go-back--hidden');
      }

      /**
       * History back button.
       */
      $('.js-button-back').click(function (e) {
        if ($('#edit-back-button').length) {
          e.preventDefault();
          $('#edit-back-button').click();
        } else {
          e.preventDefault();
          window.location.href = '/user/login' + location.search;
          history.back();
        }
      });

      /**
       *  Clicking on learning content title on course edit page opens edit
       *  modal for that content item.
       */
      var courseTitleClick = function (courseItem) {
        $(courseItem).find('.c-card__title-link, .c-card .c-card__link')
        .each(function (index, item) {
          $(item).on('click', function(event) {
            event.preventDefault();
            event.stopPropagation();
            var target = $(event.currentTarget);
            if (!target.is('[disabled]')) {
              // Prevents multiple synchronous events from being fired.
              target.attr('disabled', 'disabled');
              // Trigger event on edit button for content item.
              $(courseItem).find('.edit-button').mousedown();
              target.removeAttr('disabled');
            }
          })
        });
      };

      // Initialize the title click edit modal behavior on course edit pages.
      if ($('.l-page--node-type--course .c-form--node-course-edit-form').length != 0) {
        $('.field--name-field-learning-content .item-container', context).each(function (index, item) {
          courseTitleClick(item);
        });
      }

      /**
       * General helper function to support toggle functions.
       */
      var toggleClasses = function (element) {
        var $this = element,
          $togglePrefix = $this.data('prefix') || 'this';

        // If the element you need toggled is relative to the toggle, add the
        // .js-this class to the parent element and "this" to the data-toggled attr.
        if ($this.data('toggled') == "this") {
          var $toggled = $this.closest('.js-this');
          var $toggledParent = $this.closest('.js-this').parent().closest('.js-this');
        }
        else {
          var $toggled = $('.' + $this.data('toggled'));
        }
        if ($this.attr('aria-expanded', 'true')) {
          $this.attr('aria-expanded', 'true')
        }
        else {
          $this.attr('aria-expanded', 'false')
        }

        $toggled.toggleClass($togglePrefix + '-is-active');

        if ($toggledParent) {
          $toggledParent.toggleClass($togglePrefix + '-is-active');
        }

        // Remove a class on another element, if needed.
        if ($this.data('remove')) {
          $('.' + $this.data('remove')).removeClass($this.data('remove'));
        }
      };

      /*
       * Toggle Active Classes
       *
       * @description:
       *  toggle specific classes based on data-attr of clicked element
       *
       * @requires:
       *  'js-toggle' class and a data-attr with the element to be
       *  toggled's class name both applied to the clicked element
       *
       * @example usage:
       *  <span class="js-toggle" data-toggled="toggled-class">Toggler</span>
       *  <div class="toggled-class">This element's class will be toggled</div>
       *
       */

      $('.js-toggle', context).on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        toggleClasses($(this));
      });

      // Toggle parent class
      $('.js-toggle-parent', context).on('click', function (e) {
        e.preventDefault();
        var $this = $(this);
        $this.toggleClass('this-is-active');
        $this.parent().toggleClass('this-is-active');
      });

      // Close 'user-menu' dropdown on click event outside (".l-wrap" == entire page) of it.
      // This Behaviour only applies to this element.
      $('body', context).on('click', function (e) {
        if ($(e.target).closest(".c-user-menu").length === 0) {
          $(".c-user-menu__toggle, .c-user-menu").toggleClass("this-is-active", false);
        }
      });

      // Remove class from search form block if body is clicked
      $('.l-main', context).on('click', function () {
        $('.c-search-block').removeClass('this-is-active');
        $('.c-search-block__toggle').removeClass('this-is-active');
      });

      // On click, add focus to input field
      $('.c-search-block__toggle', context).on('click', function () {
        setTimeout(function () {
          $('.c-search-block__form input').focus();
        }, 100);
      });

      // Set Sortable pointer event false for safari browser
      // to prevent loses drag event in safari
      if ( /^((?!chrome|android).)*safari/i.test(navigator.userAgent)) {
        var el = document.getElementsByClassName('sortable');
        var i;
        for (i = 0; i < el.length; i++) {
          if (el[i]) {
            var sortable = new Sortable(el[i], {
              supportPointer: false,
            });
          }
        }
      }
    }
  };

})(jQuery, Drupal, drupalSettings, Sortable);
