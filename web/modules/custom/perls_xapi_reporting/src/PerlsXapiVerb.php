<?php

namespace Drupal\perls_xapi_reporting;

use Drupal\xapi\XapiVerb;

/**
 * This class has a list of xapi verbs.
 */
class PerlsXapiVerb extends XapiVerb {

  /**
   * XapiVerb follow.
   *
   * @returns static instance of follow verb.
   */
  public static function follow() {
    return new self("http://activitystrea.ms/schema/1.0/follow", "followed");
  }

  /**
   * XapiVerb stop following.
   *
   * @returns static instance of stop following verb.
   */
  public static function stopFollowing() {
    return new self("http://activitystrea.ms/schema/1.0/stop-following", "stopped following");
  }

  /**
   * XapiVerb voted-down.
   *
   * @returns static instance of voted-down verb.
   */
  public static function votedDown() {
    return new self("http://id.tincanapi.com/verb/voted-down", "downvoted");
  }

  /**
   * XapiVerb voted-up.
   *
   * @returns static instance of voted-up verb.
   */
  public static function votedUp() {
    return new self("http://id.tincanapi.com/verb/voted-up", "upvoted");
  }

  /**
   * XapiVerb skipped.
   *
   * @returns static instance of skipped verb.
   */
  public static function skipped() {
    return new self("http://id.tincanapi.com/verb/skipped", "skipped");
  }

  /**
   * XapiVerb selected.
   *
   * @returns static instance of selected verb.
   */
  public static function selected() {
    return new self("http://id.tincanapi.com/verb/selected", "selected");
  }

  /**
   * XapiVerb defined.
   *
   * @returns static instance of defined verb.
   */
  public static function defined() {
    return new self("http://id.tincanapi.com/verb/defined", "defined");
  }

  /**
   * XapiVerb loggedin.
   *
   * @returns static instance of loggedin verb.
   */
  public static function loggedIn() {
    return new self("https://w3id.org/xapi/adl/verbs/logged-in", "logged in");
  }

  /**
   * XapiVerb loggedout.
   *
   * @returns static instance of loggedout verb.
   */
  public static function loggedOut() {
    return new self("https://w3id.org/xapi/adl/verbs/logged-out", "logged out");
  }

  /**
   * XapiVerb viewed.
   *
   * @returns static instance of viewed verb.
   */
  public static function viewed() {
    return new self("http://id.tincanapi.com/verb/viewed", "viewed");
  }

  /**
   * XapiVerb searched.
   *
   * @returns static instance of searched verb.
   */
  public static function searched() {
    return new self("http://activitystrea.ms/schema/1.0/search", "searched");
  }

  /**
   * XapiVerb added.
   *
   * @returns static instance of added verb.
   */
  public static function added() {
    return new self("http://activitystrea.ms/schema/1.0/add", "added");
  }

  /**
   * XapiVerb added.
   *
   * @returns static instance of removed verb.
   */
  public static function removed() {
    return new self("http://activitystrea.ms/schema/1.0/remove", "removed");
  }

  /**
   * XapiVerb earned.
   *
   * @returns static instance of earned verb.
   */
  public static function earned() {
    return new self("http://id.tincanapi.com/verb/earned", "earned");
  }

  /**
   * XapiVerb saved.
   *
   * @returns static instance of saved verb.
   */
  public static function saved() {
    return new self("http://activitystrea.ms/schema/1.0/save", "saved");
  }

  /**
   * XapiVerb unsaved.
   *
   * @returns static instance of unsaved verb.
   */
  public static function unsaved() {
    return new self("http://activitystrea.ms/schema/1.0/unsave", "unsaved");
  }

  /**
   * XapiVerb author.
   *
   * @returns static instance of author verb.
   */
  public static function author() {
    return new self("http://activitystrea.ms/schema/1.0/author", "authored");
  }

  /**
   * XapiVerb create.
   *
   * @returns static instance of create verb.
   */
  public static function create() {
    return new self("http://activitystrea.ms/schema/1.0/create", "created");
  }

  /**
   * XapiVerb update.
   *
   * @returns static instance of update verb.
   */
  public static function update() {
    return new self("http://activitystrea.ms/schema/1.0/update", "updated");
  }

  /**
   * XapiVerb delete.
   *
   * @returns static instance of delete verb.
   */
  public static function delete() {
    return new self("http://activitystrea.ms/schema/1.0/delete", "deleted");
  }

  /**
   * XapiVerb submit.
   *
   * @returns static instance of submit verb.
   */
  public static function submit() {
    return new self("http://activitystrea.ms/schema/1.0/submit", "submitted");
  }

  /**
   * XapiVerb approve.
   *
   * @returns static instance of approve verb.
   */
  public static function approve() {
    return new self("http://activitystrea.ms/schema/1.0/approve", "approved");
  }

  /**
   * XapiVerb deny.
   *
   * @returns static instance of deny verb.
   */
  public static function deny() {
    return new self("http://activitystrea.ms/schema/1.0/deny", "denied");
  }

  /**
   * XapiVerb archive.
   *
   * @returns static instance of archive verb.
   */
  public static function archive() {
    return new self("http://activitystrea.ms/schema/1.0/archive", "archived");
  }

  /**
   * XapiVerb give.
   *
   * @returns static instance of give verb.
   */
  public static function give() {
    return new self("http://activitystrea.ms/schema/1.0/give", "gave");
  }

  /**
   * XapiVerb receive.
   *
   * @returns static instance of receive verb.
   */
  public static function received() {
    return new self("http://activitystrea.ms/schema/1.0/receive", "received");
  }

  /**
   * XapiVerb join.
   *
   * @returns static instance of join verb.
   */
  public static function join() {
    return new self('http://activitystrea.ms/schema/1.0/join', 'joined');
  }

  /**
   * XapiVerb leave.
   *
   * @returns static instance of leave verb.
   */
  public static function leave() {
    return new self('http://activitystrea.ms/schema/1.0/leave', 'left');
  }

  /**
   * XapiVerb recommended.
   *
   * @returns static instance of recommended verb.
   */
  public static function recommended() {
    return new self('https://w3id.org/xapi/dod-isd/verbs/recommended', 'recommended');
  }

  /**
   * XapiVerb send.
   *
   * @returns static instance of send verb.
   */
  public static function send() {
    return new self('http://activitystrea.ms/schema/1.0/send', 'send');
  }

}
