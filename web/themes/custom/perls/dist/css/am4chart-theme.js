/**
 * @file
 * Provides a custom theme for amCharts.
 *
 * Used by the Veracity VQL renderer.
 *
 * I know this isn't a CSS file. But, by pretending like it is one,
 * it makes it much easier for the Color module to replace the color
 * values below with the custom theme colors.
 */

let colors = {
  primary: '#006686',
  secondary: '#26a1c6',
  tertiary: '#d6e8ee',
  tip: '#dd031d',
  quiz: '#63378f',
  flash: '#fc990b',
  course: '#2588d0',
  text: '#232323',
  base: '#ffffff'
};

function am4themes_perls(target) {
  // It's feasible that the tertiary color could be white (or some very light color)
  // which would be unreadable in the chart. We do a rudimentary check for that
  // here and change it to light gray if it appears it may be too light.
  if (am4core.colors.rgbToHsl(am4core.color(colors.tertiary).rgb).l >= 0.9) {
    colors.tertiary = '#dddddd';
  }

  if (target instanceof am4core.InterfaceColorSet) {
    target.setFor("text", am4core.color("#000000"));
  }

  if (target instanceof am4charts.ColumnSeries) {
    target.events.on('datavalidated', function(e) {
      if (e.target.heatRules.length) {
        return;
      }

      e.target.heatRules.push({
        max: am4core.color(colors.secondary),
        min: am4core.color("white"),
        property: 'fill',
        target: e.target.columns.template,
      });
    });
  }

  if (target instanceof am4core.ColorSet) {
    target.list = [
      am4core.color(colors.secondary),
      am4core.color(colors.secondary).lighten(0.35),
      am4core.color(colors.tip),
      am4core.color(colors.quiz),
      am4core.color(colors.flash),
      am4core.color(colors.course),
      am4core.color(colors.primary),
      am4core.color(colors.tertiary),
      am4core.color(colors.secondary).lighten(-0.2),
    ];
  }
}

let customStyling = document.createElement('style');
document.head.appendChild(customStyling);
customStyling.sheet.insertRule(`.itemicon, .progressChange .iconarea .fa, .textarea { color: ${colors.secondary} !important; }`);
customStyling.sheet.insertRule(`.itemmain { color: ${colors.primary}; }`);
customStyling.sheet.insertRule(`.progressChange .mainarea { color: #000; }`);
customStyling.sheet.insertRule(`.progressChange .flexroot { height: 100%; }`);