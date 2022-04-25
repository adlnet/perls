/**
 * @file
 * Attaches behaviors to Add to Calendar formatter to Date recur.
 */

(function (Drupal, $) {
  Drupal.behaviors.dateRecurIcal = {
    attach: function (context, settings) {
      var event = drupalSettings.date_recur_ical;

      $( ".daterecurical-btn" ).each(function( index ) {
        var cal = ics();
        let btnid = $(this).data('daterecurical');
        var recurrance = '';
        if ( event[btnid].rrule.freq !== '' ) {
          var recurrance = {
            freq: event[btnid].rrule.freq,
            interval: event[btnid].rrule.interval,
            count: event[btnid].rrule.count,
            until: event[btnid].rrule.until,
            byday: event[btnid].byday
          };
        }
        cal.addEvent(event[btnid].title, event[btnid].description, event[btnid].location, event[btnid].start, event[btnid].end, recurrance);
        var filename = 'event-' + btnid;
        this.onclick = function() {
          cal.download(filename, '.ics');
        }
      });
    }
  };
})(Drupal, jQuery);
