/**
 * @file
 * Expands the behaviour of the Perls search page.
 */

(function($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.PerlsSearchPage = {
    attach: function(context, settings) {
      function useOldValueorNull(searchBoxInput) {
        var searchBoxInputTrimmed = searchBoxInput.trim();
        var userInput = $('#ui-id-1 .search-api-autocomplete-suggestion .autocomplete-suggestion-user-input');
        var suggestedSuffix = $('#ui-id-1 .search-api-autocomplete-suggestion .autocomplete-suggestion-suggestion-suffix');
        if (userInput.length > 0 && suggestedSuffix.length > 0 && searchBoxInput.length > 0) {
          var suggestedText = userInput[0].innerText + suggestedSuffix[0].innerText;
          if (suggestedText.indexOf(searchBoxInputTrimmed) == 0) {
            //match suggestion to user input
            var updatedSuggestion =   searchBoxInput + suggestedText.substring(searchBoxInputTrimmed.length);
            $("[id^='search_field_autocomplete']").val(updatedSuggestion);
          } else {
            $("[id^='search_field_autocomplete']").val('');
          }
        } else {
          $("[id^='search_field_autocomplete']").val('');
        }
      }
      // Clear all search information
      $('#clear_all_button').once('perls_clear_all').each(function() {
        $(this).on('click', function(e) {
          window.location = '/';
        });
      });
      // This function transfers Type selection from type select to exposed form.
      var type_select = $("#perls-search-page-type-dropdown", context);
      type_select.once('listener-added').each(function() {
        type_select.change(function(e) {
          var dropdown = $("#views-exposed-form-search-search-page .form-item-type select")[0];
          dropdown.value = e.currentTarget.value;
          $(".c-search-block .views-auto-submit-click").click();
        })
      });
      // This function transfers Category selection from category select to exposed form.
      var category_select = $("#perls-search-page-field_category-dropdown", context);
      category_select.once('listener-added').each(function() {
        category_select.change(function(e) {
          var dropdown = $("#views-exposed-form-search-search-page .form-item-field-category select")[0];
          dropdown.value = e.currentTarget.value;
          $(".c-search-block .views-auto-submit-click").click();
        })
      });
      // This Listener redirects all tag links on search page back to search page.
      $(".view-id-search .field--name-field-tags a", context).once('tag_redirect')
        .each(function() {
          this.pathname = "/search";
          this.search = "?search_api_fulltext=%23" + this.innerText;
        });

      // Transfer autocomplete results to autocomplete input behind search box.
      $('input[id^="edit-search-api-fulltext"]', context).once('perls_auto_listener_added').each(function() {
        $(this).on("autocompleteresponse", function(event, ui) {
          if (ui.content.length > 0 && event.currentTarget.value.length > 0 ) {
            useOldValueorNull(event.currentTarget.value);
          }
        });
      });

      // Prevent form submit by IE11 and use click instead.
      $('input[id^="edit-search-api-fulltext"]', context).once('perls_return_key_fix').each(function() {
        $(this).on("keypress", function(event, ui) {
          var keycode = (event.keyCode ? event.keyCode : event.which);
          if (keycode == '13') {
              event.preventDefault();
              //check for an autocomplete definition to add to search
              if ($("[id^='search_field_autocomplete']").val()){
                $('input[id^="edit-search-api-fulltext"]')[0].value = $("[id^='search_field_autocomplete']").val();
              }
              $(".c-search-block .views-auto-submit-click").click();
          }
        });
      });



      // The listener above can be slow to update so we need to clear suggestion on keyup if
      // suggestion no longer matches user input. If we don't we get two different words on top of each other.
      // This function also presists the last suggestion if it still matches user input.
      $('input[id^="edit-search-api-fulltext"]', context).once('perls_listener_added').each(function() {
        $(this).on('keyup keydown focus', function(e) {
          useOldValueorNull(e.currentTarget.value);
        });
      });

      $.fn.refocus_search = function() {
        // get stored data
        var savedValue = $('body').data('last_known_search_value');
        if (savedValue) {
          $('input[id^="edit-search-api-fulltext"]')[0].value = savedValue;
        }
        //we resubmit form on load to update promoted content
        // views does not render it correctly when loading via ajax.
        $(".c-search-block .views-auto-submit-click").click();
        // Move Cursor to end of Search Box
        SetCaretAtEnd($('input[id^="edit-search-api-fulltext"]')[0]);
      };

      $.fn.after_dashboard_search = function() {
        let searchPath = drupalSettings.perls_search.search_path;
        $('body').removeClass('path-frontpage').addClass('path-search');
        document.title = Drupal.t('Search');

        // In few theme the page title is part of the main content block,
        // we need to put back after we updated the content of the main block.
        if ($('.o-page-title').length === 0) {
          $("<div class='o-page-title'></div>").insertBefore('.l-content');
        }
        $('.o-page-title').text(Drupal.t('Search results'));

        let searchFieldValue = $('input[id^="edit-search-api-fulltext"]').val();
        let searchUrl = '/' + searchPath + "?search_api_fulltext=" + searchFieldValue;
        history.pushState({query: searchFieldValue}, "search", searchUrl);
        // We modify the destination on action urls.
        SetProperDestinationOnActions(searchUrl);
      };

      $.fn.before_replace = function() {
        //Get latest value from search box and save it.
        $('body').data('last_known_search_value', $('input[id^="edit-search-api-fulltext"]')[0].value);
      };

      history.replaceState({currentPath: window.location.href}, window.title);

      window.onpopstate = function (event) {
        if (event.state && event.state.currentPath) {
          window.location = event.state.currentPath;
        }
      };

      /* Set the proper destination for view's action links that user will be
      redirected to search result page after finished the content editing.*/
      function SetProperDestinationOnActions(destination) {
        $('td.views-field-search-api-operations .dropbutton a').each(function () {
          let parser = document.createElement('a');
          parser.href = decodeURIComponent($(this).attr('href'));
          parser.search = "?destination=" + destination;
          $(this).attr("href", parser.toString());

        }, destination);
      }

      function SetCaretAtEnd(elem) {
        var elemLen = elem.value.length;
        // For IE Only
        if (document.selection) {
          // Set focus
          elem.focus();
          // Use IE Ranges
          var oSel = document.selection.createRange();
          // Reset position to 0 & then set at end
          oSel.moveStart('character', -elemLen);
          oSel.moveStart('character', elemLen);
          oSel.moveEnd('character', 0);
          oSel.select();
        } else if (elem.selectionStart || elem.selectionStart == '0') {
          // Firefox/Chrome
          elem.selectionStart = elemLen;
          elem.selectionEnd = elemLen;
          setTimeout(function() { elem.focus()},1);
        } // if
      } // SetCaretAtEnd()

    }

  };


})(jQuery, Drupal, drupalSettings);
