(function ($, Drupal) {
  Drupal.behaviors.PackagedEntity = {
    attach: function (context, settings) {
      // Rewrite the template statement based on any provided query params.
      if (settings.Xapi && settings.Xapi.templateStatement) {
        settings.Xapi.templateStatement = rewriteTemplateStatement(settings.Xapi.templateStatement);
      }
    }
  }

  /**
   * Rewrite the template xAPI statement based on query parameters passed to the page.
   *
   * @param {object} statement
   *   The original template xAPI statement.
   *
   * @return {object}
   *   The updated template xAPI statement.
   */
  function rewriteTemplateStatement(statement) {
    var params = (new URL(window.location.href)).searchParams;

    if (params.has('activity_id')) {
      var activityId = params.get('activity_id');

      if (activityId !== statement.object.id) {
        statement = rewriteIRIs(statement, activityId);
      }
    }

    if (params.has('actor')) {
      try {
        statement.actor = JSON.parse(params.get('actor'));
      } catch (e) {}
    }

    if (params.has('definition')) {
      try {
        statement.object.definition = JSON.parse(params.get('definition'));
      } catch (e) {}
    }

    if (params.has('endpoint')) {
      ADL.XAPIWrapper.changeConfig({
        endpoint: params.get('endpoint')
      });
    }

    return statement;
  }

  /**
   * Rewrites IRIs in the provided statement based on the new object ID.
   *
   * This directly updates the object ID on the statement and attempts
   * to update all other IRIs based on the changes made to the object ID.
   *
   * When the new and old object ID have the exact same path, then the only
   * thing changing will be the host of the ID. In that case, all IRIs
   * referencing the old host should be updated to match the new host.
   *
   * @param {object} statement
   *   The template xAPI statement.
   * @param {string} newActivityId
   *   The new activity ID.
   */
  function rewriteIRIs(statement, newActivityId) {
    var oldActivityId = statement.object.id;

    // Directly update the activity ID.
    statement.object.id = newActivityId;

    // Attempt to update any other IRIs referencing the old host.
    try {
      var newActivityUri = new URL(newActivityId);
      var oldActivityUri = new URL(oldActivityId);

      if (newActivityUri.pathname === oldActivityUri.pathname) {
        var statementJson = JSON.stringify(statement);
        statementJson = statementJson.split(oldActivityUri.origin).join(newActivityUri.origin);
        statement = JSON.parse(statementJson);
      }
    } catch (e) {
    } finally {
      return statement;
    }
  }

}(jQuery, Drupal));
