/**
 * @file
 * Attaches show/hide functionality to checkboxes in the "Recommendation
 *   plugin" Settings page.
 */

(function ($, Drupal) {
  "use strict";

  Drupal.behaviors.PromptsXapiReport = {
    attach: function (context, settings) {
      // Report prompt view.
      $('.prompt-wrapper[submission-uuid]', context).each(function (index, element) {
        sendAskedStatement(element);
      });

      // Override the submit behaviour.
      $('.prompt-wrapper[submission-uuid] .form-radio', context).once().on('click', function (event) {
        let form = $(this).closest('.prompt-wrapper');
        event.preventDefault();
        sendRespondedStatement(form);
      });

    }
  }

  function sendAskedStatement(element) {
    var systemActor = new ADL.XAPIStatement.Agent({
      homePage: window.location.protocol + '//' + window.location.host,
      name: drupalSettings.Xapi.systemName,
    }, drupalSettings.Xapi.systemName);

    (new StatementBuilder())
      .setActor(systemActor)
      .setVerb(ADL.verbs.asked)
      .setObject(new ADL.XAPIStatement.Activity(prepareActivity(element)))
      .sendStatement();
  }

  function sendRespondedStatement(element) {
    (new StatementBuilder())
      .setVerb(ADL.verbs.responded)
      .setObject(new ADL.XAPIStatement.Activity(prepareActivity(element)))
      .setResult({'response' : $('.form-radio:checked', element).val()})
      .sendStatement();
  }

  function prepareActivity(element) {
    let title = $(".form-item__label", element).text();
    let activity = getPromptActivity(element);
    activity['definition']['name'][document.documentElement.lang] = title;
    return activity;
  }

  function getPromptActivity(element) {
    return {
      'id': getObjectUrl(element),
      'definition': {
        'type': ADL.activityTypes.question,
        'name': {}
      }
    }
  }

  function getObjectUrl(form_element) {
    let uuid = $(form_element).attr('submission-uuid');
    return Xapi.getPageBaseUrl() + '/submission/' + uuid;
  }

})(jQuery, Drupal);
