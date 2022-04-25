/**
 * @file
 * More ADL Verbs.
 */

(function (Xapi) {
  Xapi.verbs = {
    annotated: new ADL.XAPIStatement.Verb("http://risc-inc.com/annotator/verbs/annotated", "annotated"),
    enabled: new ADL.XAPIStatement.Verb("http://id.tincanapi.com/verb/enabled", "enabled"),
    disabled: new ADL.XAPIStatement.Verb("http://id.tincanapi.com/verb/disabled", "disabled"),
  }
}(Xapi));
