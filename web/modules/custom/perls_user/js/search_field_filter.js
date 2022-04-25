/**
 * @file
 */

(function (Drupal, $) {
  Drupal.behaviors.perlsIsotope = {

    attach: function () {
      $('.iso-grid-item').each(function () {
        var that = this;
        $(this).on('click', function () {
          $(that).toggleClass("checked");
        })
      });

      $('.iso-grid').each(function () {
        var qsRegex;
        var $grid = $(this);

        // Use value of search field to filter.
        var $quicksearch = $('.iso-search-input').keyup(debounce(function () {
          qsRegex = new RegExp($quicksearch.val(), 'gi');
          $grid.isotope({
            itemSelector: '.iso-grid-item',
            layoutMode: 'fitRows',
            getSortData: {
              selectedCategory: function (itemElem) {
                return $(itemElem).hasClass('checked') ? 0 : 1;
              }
            },
            filter: function () {
              var $this = $(this);
              var isChecked = $this.hasClass('checked');
              var searchResult = qsRegex ? $this.text().match(qsRegex) : true;
              return searchResult || isChecked;
            }
          });
          $grid.isotope('updateSortData');
          $grid.isotope({ sortBy: 'selectedCategory' });
        }, 200));

        // Debounce so filtering doesn't happen every millisecond.
        function debounce(fn, threshold) {
          var timeout;
          threshold = threshold || 100;
          return function debounced() {
            clearTimeout(timeout);
            var args = arguments;
            var _this = this;
            function delayed() {
              fn.apply(_this, args);
            }
            timeout = setTimeout(delayed, threshold);
          };
        }
      });
    }

  };
}(Drupal, jQuery));
