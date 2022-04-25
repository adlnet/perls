(function($, Drupal) {
  Drupal.behaviors.perlsXapiReportingVidyo = {
    attach: function (context, settings) {
      let joinTime = 0;
      let inCall = false;
      let videoCallUrl = $('.vidyo-client').attr('call-url');
      setObjectData();
      $(document).once('#vidyoConnector').each(function () {
        $(this).on('connected', function () {
          joinTime = new Date();
          inCall = true;
          let newStatement = (new StatementBuilder());
          removeParentContext(newStatement);
          newStatement
            .setVerb(ADL.verbs.launched)
            .sendStatement();

          newStatement = (new StatementBuilder());
          // Remove the parent contextActivities.
          removeParentContext(newStatement);
          newStatement
            .setVerb(ADL.verbs.initialized)
            .sendStatement();
        });

        $(this).on('disconnected', function () {
          // User spent time in call in sec.
          let now = new Date();
          let diff = Math.floor((now.getTime() - joinTime.getTime()) / 1000);
          // We send statement if the user spent 5 mins in call.
          let newStatement = (new StatementBuilder());
          // Remove the parent contextActivities.
          removeParentContext(newStatement);
          if (diff >= 300) {
            newStatement
              .setVerb(ADL.verbs.completed)
              .setCompletion()
              .setDurationSince(joinTime.getTime())
              .sendStatement();

            newStatement = (new StatementBuilder());
            // Remove the parent contextActivities.
            removeParentContext(newStatement);
            newStatement
              .setVerb(ADL.verbs.attended)
              .sendStatement();
          }
          else {
            sendTerminatedStatement();
            inCall = false;
          }
        });

        $(this).on('cameraOn cameraOff', function (event) {
          let verb = {};
          if (event.type === 'cameraOn') {
            verb = new ADL.XAPIStatement.Verb(Xapi.verbs.enabled);
          }
          else {
            verb = new ADL.XAPIStatement.Verb(Xapi.verbs.disabled);
          }
          let activity = new ADL.XAPIStatement.Activity(videoCallUrl + '#camera', Drupal.t('camera'));
          activity.definition.type = 'http://activitystrea.ms/schema/1.0/device';
          let statement = new StatementBuilder();

          statement.setVerb(verb)
            .setObject(activity)
            .sendStatement();
        });

        $(this).on('startSharing stopSharing', function (event) {
          let verb = {};
          if (event.type === 'startSharing') {
            verb = new ADL.XAPIStatement.Verb(Xapi.verbs.enabled);
          }
          else {
            verb = new ADL.XAPIStatement.Verb(Xapi.verbs.disabled);
          }
          let activity = new ADL.XAPIStatement.Activity(videoCallUrl + '#screen', Drupal.t('screen sharing'));
          let statement = new StatementBuilder();
          activity.definition.type = 'http://activitystrea.ms/schema/1.0/device';
          statement
            .setVerb(verb)
            .setObject(activity)
            .sendStatement();
        });

        $(this).on('microphoneOn microphoneOff', function (event) {
          let verb = {};
          if (event.type === 'microphoneOn') {
            verb = new ADL.XAPIStatement.Verb(Xapi.verbs.enabled);
          }
          else {
            verb = new ADL.XAPIStatement.Verb(Xapi.verbs.disabled);
          }
          let activity = new ADL.XAPIStatement.Activity(videoCallUrl + '#microphone', Drupal.t('microphone'));
          activity.definition.type = 'http://activitystrea.ms/schema/1.0/device';
          let statement = new StatementBuilder();

          statement.setVerb(verb)
            .setObject(activity)
            .sendStatement();
        });

      });

      /**
       * Send a terminated statement when user leave the call.
       */
      function sendTerminatedStatement() {
        newStatement = (new StatementBuilder());
        // Remove the parent contextActivities.
        removeParentContext(newStatement);
        newStatement
          .setVerb(ADL.verbs.terminated)
          .setDurationSince(joinTime.getTime())
          .sendStatement();
      }


      /**
       * Remove the prepopulated parent context.
       *
       * @param statement
       * @returns {*}
       */
      function removeParentContext(statementObject) {
        statement = statementObject.getStatement();
        if (statement.hasOwnProperty('context') &&
          statement.context.hasOwnProperty('contextActivities') &&
          statement.context.contextActivities.hasOwnProperty('parent') &&
          statement.context.contextActivities.parent != null) {
          delete statement.context.contextActivities.parent;
        }
        return statementObject.setStatement(statement);
      }

      function setObjectData() {
        let statement = settings.Xapi.templateStatement;
        // The video call doesn't belong to an event.
        if (statement.object == null) {
          let activityNameMapping = {};
          activityNameMapping[document.documentElement.lang] = Drupal.t('Adhoc meeting');
          statement.object = new ADL.XAPIStatement.Activity(videoCallUrl, activityNameMapping);
          statement.object.definition.type = 'http://adlnet.gov/expapi/activities/meeting';
        }
        settings.Xapi.templateStatement = statement;
      }

    }
  };
}(jQuery, Drupal));
