/**
 * Set the proper configuration for Xapi statement endpoint and provide
 * a default funcion for create a default statement object.
 */

(function ($, Drupal) {

  /**
   * Convert milliseconds to ISO 8601 format.
   *
   * @param date
   *   The milliseconds what the function will convert.
   * @returns {string}
   *   The milliseconds in ISO format.
   */
  Date.prototype.getISODurationSince = function (date) {
    const duration = Math.abs(this - date);
    let seconds = duration / 1000;
    if (seconds > 60) {
      if (seconds > 3600) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        seconds = (seconds % 3600) % 60;
        return 'PT' + hours + 'H' + minutes + 'M' + seconds + 'S';
      } else {
        const minutes = Math.floor(seconds / 60);
        seconds %= 60;
        return 'PT' + minutes + 'M' + seconds + 'S';
      }
    } else {
      return 'PT' + seconds + 'S';
    }
  };

  window.StatementBuilder = function (originalStatement) {
    let templateStatement = typeof drupalSettings.Xapi === 'undefined' ? {} : drupalSettings.Xapi.templateStatement;
    // Remove null values from the template statement so we can give them defaults below.
    if (templateStatement.verb === null) {
      delete templateStatement.verb;
    }
    if (templateStatement.object === null) {
      delete templateStatement.object;
    }
    let statementObj = jQuery.extend(true, {verb: ADL.verbs.experienced, object: {}}, originalStatement, templateStatement);
    let statement = new ADL.XAPIStatement(statementObj);

    return {
      setActor(actor) {
        statement.actor = actor;
        return this;
      },
      setVerb(verb, description) {
        if (verb instanceof ADL.XAPIStatement.Verb) {
          statement.verb = verb;
        } else {
          statement.verb = new ADL.XAPIStatement.Verb(verb, description);
        }

        if (!statement.verb.isValid) {
          throw new Error('Verb is invalid: ' + statement.verb.toString());
        }

        return this;
      },
      setCompletion: function (completion) {
        const result = Object.assign({}, statement.result, { completion: completion });
        return this.setResult(result);
      },
      setDurationSince: function (startDate) {
        return this.setDuration((new Date()).getISODurationSince(startDate));
      },
      setDuration: function (duration) {
        const result = Object.assign({}, statement.result, { duration: duration });
        return this.setResult(result);
      },
      setResult: function (result) {
        statement.result = result;
        return this;
      },
      setTimeStamp: function (date) {
        statement.timestamp = date.toISOString(date);
        return this;
      },
      setObjectProperties: function (activityID, activityName, language) {
        let activityNameMapping = {};
        if (typeof language == 'undefined') {
          activityNameMapping[document.documentElement.lang] = activityName;
        }
        else {
          activityNameMapping[language] = activityName;
        }
        return this.setObject(new ADL.XAPIStatement.Activity(activityID, activityNameMapping));
      },
      setObject: function (Activity) {
        statement.object = Activity;
        return this;
      },
      isValid: function () {
        return statement.actor && statement.actor.isValid()
          && statement.verb && statement.verb.isValid()
          && statement.object && statement.object.isValid()
          && Object.prototype.toString.call(statement.object.id) === "[object String]";
      },
      getStatement: function () {
        statement.timestamp = (new Date()).toISOString();
        return statement;
      },
      setGroupActivity: function (activity) {
        statement.addGroupingActivity(activity);
        return this;
      },
      setParentActivity: function (activity) {
        statement.addParentActivity(activity);
        return this;
      },
      setStatement: function (statement) {
        statement = statement;
      },
      sendStatement: function (headers) {
        if (this.isValid()) {
          return ADL.XAPIWrapper.sendStatement(this.getStatement());
        }
        else {
          console.log("xAPI statement not sent because it failed validation.");
          return false;
        }

      }
    }
  };

  let Xapi = {
    STATEMENT_QUEUE: 'Xapi.statementQueue',
  };

  /**
   * Add new statements to queue.
   *
   * @param statements
   *   An array which contains statement objects.
   */
  Xapi.queueStatements = function (statements) {
    let existingElements = JSON.parse(window.localStorage.getItem(this.STATEMENT_QUEUE));
    if (existingElements != null && existingElements.length > 0) {
      existingElements = existingElements.concat(statements);
      window.localStorage.setItem(this.STATEMENT_QUEUE, JSON.stringify(existingElements));
    }
    else {
      window.localStorage.setItem(this.STATEMENT_QUEUE, JSON.stringify(statements));
    }
  };

  Xapi.flushStatementQueue = function () {
    let abandonedStatements = JSON.parse(window.localStorage.getItem(this.STATEMENT_QUEUE));
    if (abandonedStatements != null && abandonedStatements.length > 0) {
      ADL.XAPIWrapper.sendStatements(abandonedStatements);
    }
    window.localStorage.removeItem(this.STATEMENT_QUEUE);
  };

  Xapi.getPageBaseUrl = function () {
    return window.location.protocol +
      '//' + window.location.hostname +
      (window.location.port ? ':' +
        window.location.port : '');
  }

  // Configure the XAPI wrapper library with default settings.
  function setDefaultConfig() {
    let url = Xapi.getPageBaseUrl();
    let conf = {
      endpoint: url + '/lrs/',
      auth: 'null',
    };

    if (drupalSettings.Xapi !== undefined) {
      conf.actor = drupalSettings.Xapi.templateStatement.actor;
    }

    ADL.XAPIWrapper.changeConfig(conf);
  }

  window.Xapi = Xapi;
  setDefaultConfig();
})(jQuery, Drupal);
