/**
 * @file
 * Initializes Veracity charts on page load.
 */

(function (Drupal) {
  let rendered = [];
  Drupal.behaviors.veracityChart = {
    attach: function (context, settings) {
      Object.keys(settings.veracity_vql)
        .filter(function (chart_id) {
          return rendered.indexOf(chart_id) === -1;
        })
        .forEach(function (chart_id) {
          let chart = settings.veracity_vql[chart_id];
          vqlRender(chart_id, chart.vql, chart.theme.name, chart.theme.url, chart.theme.background);
          rendered.push(chart_id);
        });
    }
  };
})(Drupal);
