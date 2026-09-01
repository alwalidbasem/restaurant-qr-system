/* global Chart */

(function () {
  var AdminCharts = window.AdminCharts || {};

  var colors = ['#b8541b', '#1c7ed6', '#2f9e44', '#7048e8', '#f08c00', '#0ca678'];
  var gridColor = 'rgba(122, 130, 143, .16)';
  var labelColor = '#68707d';

  function chartCanvas(id) {
    var canvas = document.getElementById(id);
    if (canvas && canvas.chart) canvas.chart.destroy();
    return canvas;
  }

  function compactTop(map, limit, emptyLabel) {
    var rows = Object.keys(map).map(function (key) {
      return { label: key, value: map[key] };
    }).sort(function (a, b) {
      return b.value - a.value;
    }).slice(0, limit);

    return rows.length ? rows : [{ label: emptyLabel, value: 0 }];
  }

  function commonOptions(overrides) {
    return Object.assign({
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#171a21',
          padding: 10,
          titleFont: { weight: '700' },
          bodyFont: { weight: '600' }
        }
      }
    }, overrides || {});
  }

  function axisOptions(options) {
    var settings = options || {};

    return {
      x: {
        beginAtZero: !!settings.xBeginAtZero,
        grid: { display: settings.xGrid === true, color: gridColor },
        ticks: Object.assign({ color: labelColor }, settings.xTicks || {})
      },
      y: {
        beginAtZero: settings.yBeginAtZero !== false,
        grid: { display: settings.yGrid === false ? false : true, color: gridColor },
        ticks: Object.assign({ color: labelColor }, settings.yTicks || {})
      }
    };
  }

  function render(id, config) {
    if (typeof Chart === 'undefined') return null;

    var canvas = chartCanvas(id);
    if (!canvas) return null;

    canvas.chart = new Chart(canvas, config);
    return canvas.chart;
  }

  AdminCharts.colors = colors;
  AdminCharts.gridColor = gridColor;
  AdminCharts.labelColor = labelColor;
  AdminCharts.chartCanvas = chartCanvas;
  AdminCharts.compactTop = compactTop;
  AdminCharts.commonOptions = commonOptions;
  AdminCharts.axisOptions = axisOptions;
  AdminCharts.render = render;

  window.AdminCharts = AdminCharts;
  window.chartCanvas = chartCanvas;
  window.compactTop = compactTop;
})();
