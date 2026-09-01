/* global Chart */

( function () {
  function renderCharts(orders, orderRows, staff) {
    if (typeof Chart === 'undefined' || !document.getElementById('revenueChart')) return;

    var days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    var revenueByDay = [0, 0, 0, 0, 0, 0, 0];
    var profitByDay = [0, 0, 0, 0, 0, 0, 0];
    var ordersByHour = Array.from({ length: 24 }, function () { return 0; });
    var statusCounts = { waiting: 0, finished: 0, canceled: 0 };
    var categoryCounts = {};
    var dishCounts = {};

    orders.forEach(function (order) {
      var date = order.created_at ? new Date(order.created_at.replace(' ', 'T')) : new Date();
      revenueByDay[date.getDay()] += Number(order.order_price || 0);
      profitByDay[date.getDay()] += Number(order.order_profit || 0);
      ordersByHour[date.getHours()] += 1;
      statusCounts[order.status] = (statusCounts[order.status] || 0) + 1;
    });

    (orderRows || []).forEach(function (row) {
      var qty = Number(row.qty || 1);
      var category = row.category_name_en || row.category_name_ar || 'Uncategorized';
      var dish = row.food_name_en || row.food_name_ar || ('Food #' + row.food_id);
      categoryCounts[category] = (categoryCounts[category] || 0) + qty;
      dishCounts[dish] = (dishCounts[dish] || 0) + qty;
    });

    var categoryTop = AdminCharts.compactTop(categoryCounts, 5, 'No category data');
    var dishTop = AdminCharts.compactTop(dishCounts, 6, 'No dish data');
    var chartColors = AdminCharts.colors;
    var dailySalaryCost = (staff || []).reduce(function (sum, person) {
      return sum + Number(person.salary || 0);
    }, 0) / 30;
    var profitAfterSalaryByDay = profitByDay.map(function (value) {
      return value - dailySalaryCost;
    });
    var gridColor = AdminCharts.gridColor;
    var labelColor = AdminCharts.labelColor;
    var commonOptions = AdminCharts.commonOptions();

    var revenueCanvas = AdminCharts.chartCanvas('revenueChart');
    if (revenueCanvas) {
      revenueCanvas.chart = new Chart(revenueCanvas, {
      type: 'line',
      data: {
        labels: days,
        datasets: [{
          label: 'Revenue',
          data: revenueByDay,
          borderColor: '#b8541b',
          backgroundColor: 'rgba(184,84,27,0.14)',
          pointBackgroundColor: '#fff',
          pointBorderColor: '#b8541b',
          pointBorderWidth: 2,
          pointRadius: 4,
          fill: true,
          tension: .38
        }]
      },
        options: Object.assign({}, commonOptions, {
          scales: {
            x: { grid: { display: false }, ticks: { color: labelColor } },
            y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: labelColor } }
          }
        })
      });
    }

    var categoryCanvas = AdminCharts.chartCanvas('categoryChart');
    if (categoryCanvas) {
      categoryCanvas.chart = new Chart(categoryCanvas, {
        type: 'doughnut',
        data: {
          labels: categoryTop.map(function (row) { return row.label; }),
          datasets: [{
            data: categoryTop.map(function (row) { return row.value; }),
            backgroundColor: chartColors,
            borderColor: '#fff',
            borderWidth: 4,
            hoverOffset: 8
          }]
        },
        options: Object.assign({}, commonOptions, {
          cutout: '64%',
          plugins: Object.assign({}, commonOptions.plugins, {
            legend: { display: true, position: 'bottom', labels: { boxWidth: 10, color: labelColor, usePointStyle: true } }
          })
        })
      });
    }

    var profitOnlyCanvas = AdminCharts.chartCanvas('profitOnlyChart');
    if (profitOnlyCanvas) {
      profitOnlyCanvas.chart = new Chart(profitOnlyCanvas, {
        type: 'bar',
        data: {
          labels: days,
          datasets: [{
            label: 'Profit',
            data: profitByDay,
            backgroundColor: 'rgba(47,158,68,.78)',
            borderRadius: 8,
            maxBarThickness: 22
          }]
        },
        options: Object.assign({}, commonOptions, {
          scales: {
            x: { grid: { display: false }, ticks: { color: labelColor } },
            y: { grid: { color: gridColor }, ticks: { color: labelColor } }
          }
        })
      });
    }

    var profitSalaryCanvas = AdminCharts.chartCanvas('profitSalaryChart');
    if (profitSalaryCanvas) {
      profitSalaryCanvas.chart = new Chart(profitSalaryCanvas, {
        type: 'bar',
        data: {
          labels: days,
          datasets: [{
            label: 'Profit after salary',
            data: profitAfterSalaryByDay,
            backgroundColor: 'rgba(112,72,232,.78)',
            borderRadius: 8,
            maxBarThickness: 22
          }]
        },
        options: Object.assign({}, commonOptions, {
          scales: {
            x: { grid: { display: false }, ticks: { color: labelColor } },
            y: { grid: { color: gridColor }, ticks: { color: labelColor } }
          }
        })
      });
    }

    var hourCanvas = AdminCharts.chartCanvas('ordersHourChart');
    if (hourCanvas) {
      hourCanvas.chart = new Chart(hourCanvas, {
        type: 'bar',
        data: {
          labels: ordersByHour.map(function (_, hour) { return hour + ':00'; }),
          datasets: [{
            label: 'Orders',
            data: ordersByHour,
            backgroundColor: 'rgba(28,126,214,.78)',
            borderRadius: 8,
            maxBarThickness: 16
          }]
        },
        options: Object.assign({}, commonOptions, {
          scales: {
            x: { grid: { display: false }, ticks: { color: labelColor, maxRotation: 0, autoSkip: true, maxTicksLimit: 6 } },
            y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: labelColor, precision: 0 } }
          }
        })
      });
    }

    var dishesCanvas = AdminCharts.chartCanvas('topDishesChart');
    if (dishesCanvas) {
      dishesCanvas.chart = new Chart(dishesCanvas, {
        type: 'bar',
        data: {
          labels: dishTop.map(function (row) { return row.label; }),
          datasets: [{
            label: 'Qty',
            data: dishTop.map(function (row) { return row.value; }),
            backgroundColor: '#2f9e44',
            borderRadius: 8,
            maxBarThickness: 18
          }]
        },
        options: Object.assign({}, commonOptions, {
          indexAxis: 'y',
          scales: {
            x: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: labelColor, precision: 0 } },
            y: { grid: { display: false }, ticks: { color: labelColor } }
          }
        })
      });
    }

    var statusCanvas = AdminCharts.chartCanvas('statusChart');
    if (statusCanvas) {
      statusCanvas.chart = new Chart(statusCanvas, {
        type: 'doughnut',
        data: {
          labels: ['Waiting', 'Finished', 'Canceled'],
          datasets: [{
            data: [statusCounts.waiting || 0, statusCounts.finished || 0, statusCounts.canceled || 0],
            backgroundColor: ['#f59f00', '#2f9e44', '#e03131'],
            borderColor: '#fff',
            borderWidth: 4,
            hoverOffset: 8
          }]
        },
        options: Object.assign({}, commonOptions, {
          cutout: '68%',
          plugins: Object.assign({}, commonOptions.plugins, {
            legend: { display: true, position: 'bottom', labels: { boxWidth: 10, color: labelColor, usePointStyle: true } }
          })
        })
      });
    }
  }

  function initDashboard() {
    var statRevenue = document.getElementById('statRevenue');
    if (!statRevenue) return;
    var dashboardLoading = false;
    var dashboardChartsLoaded = false;

    function loadDashboardData(silent) {
      if (dashboardLoading) return;
      dashboardLoading = true;

      Promise.allSettled([
        request('/orders'),
        request('/tables'),
        request('/staff'),
        request('/menu-foods')
      ]).then(function (results) {
      var orderRows = results[0].status === 'fulfilled' ? results[0].value.data : [];
      var tables = results[1].status === 'fulfilled' ? results[1].value.data : [];
      var staff = results[2].status === 'fulfilled' ? results[2].value.data : [];
      var orders = groupOrders(orderRows);
      var revenue = orders.reduce(function (sum, order) { return sum + Number(order.order_price || 0); }, 0);
      var occupied = tables.filter(function (table) { return table.table_status !== 'free'; }).length;
      var waiting = orders.filter(function (order) { return order.status === 'waiting'; }).length;

      statRevenue.textContent = money(revenue);
      document.getElementById('statOrders').textContent = orders.length;
      document.getElementById('statOrdersMeta').textContent = waiting + ' waiting now';
      document.getElementById('statTables').textContent = occupied + ' / ' + tables.length;
      document.getElementById('statTablesMeta').textContent = tables.length ? Math.round((occupied / tables.length) * 100) + '% occupancy' : 'No tables';
      document.getElementById('statStaff').textContent = staff.length;

      var tablesGrid = document.getElementById('tablesGrid');
      if (tablesGrid) {
        tablesGrid.innerHTML = tables.map(function (table) {
          var status = table.table_status || 'free';
          var color = { free: 'success', waiting_order: 'warning', order_done: 'primary' }[status] || 'secondary';
          return '<div class="col-6 col-sm-4 col-md-3 col-lg-2"><div class="table-status-tile text-' + color + '">' +
            '<div class="d-flex align-items-center justify-content-between"><span class="table-number">T-' + escapeHtml(table.table_number) + '</span><span class="table-status-dot"></span></div>' +
            '<small class="text-secondary">' + escapeHtml(status.replace(/_/g, ' ')) + '</small></div></div>';
        }).join('');
      }

      if (!dashboardChartsLoaded) {
        renderCharts(orders, orderRows, staff);
        dashboardChartsLoaded = true;
      }
      loadBranchesDashboard();
    }).catch(function () {
        if (!silent) {
          statRevenue.textContent = 'Error';
        }
      }).finally(function () {
        dashboardLoading = false;
      });
    }

    function branchHighlightCard(label, branch, icon, valueField) {
      if (!branch) return '';
      return '<div class="col-md-4"><div class="stat-card">' +
        '<div class="stat-card-top"><span class="stat-icon"><i class="bi ' + escapeHtml(icon) + '"></i></span></div>' +
        '<div><div class="stat-label">' + escapeHtml(label) + '</div>' +
        '<div class="stat-value stat-value-sm">' + escapeHtml(branch.name || '-') + '</div>' +
        '<small class="text-secondary">' + money(branch[valueField] || 0) + '</small></div>' +
      '</div></div>';
    }

    function loadBranchesDashboard() {
      var section = document.getElementById('branchesDashboardSection');
      var body = document.getElementById('branchesDashboardBody');
      var highlights = document.getElementById('branchHighlights');
      if (!section || !body || !activeRestaurantId) return;

      request('/restaurants/' + activeRestaurantId + '/branches-dashboard').then(function (payload) {
        var data = payload.data || {};
        var branches = data.branches || [];
        section.classList.toggle('d-none', branches.length === 0);
        highlights.innerHTML =
          branchHighlightCard('Highest Profitability', data.highest_profitability, 'bi-graph-up-arrow', 'profit_with_salary') +
          branchHighlightCard('Highest Inventory Loss', data.highest_inventory_losses, 'bi-exclamation-triangle', 'inventory_loss') +
          branchHighlightCard('Lowest Inventory Loss', data.lowest_inventory_losses, 'bi-shield-check', 'inventory_loss');
        body.innerHTML = branches.map(function (branch) {
          return '<tr>' +
            '<td class="fw-semibold">' + escapeHtml(branch.name) + '</td>' +
            '<td>' + escapeHtml(branch.location || '-') + '</td>' +
            '<td>' + money(branch.profit_without_salary || 0) + '</td>' +
            '<td>' + money(branch.salary_total || 0) + '</td>' +
            '<td>' + money(branch.profit_with_salary || 0) + '</td>' +
            '<td>' + escapeHtml(Number(branch.inventory_loss || 0).toFixed(3)) + '</td>' +
          '</tr>';
        }).join('') || '<tr><td colspan="6" class="text-center text-secondary py-4">No branch data found.</td></tr>';
      }).catch(function () {
        section.classList.add('d-none');
      });
    }

    loadDashboardData();
    window.setInterval(function () {
      if (document.hidden) return;
      loadDashboardData(true);
    }, 1000);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initDashboard();
    });
  } else {
    initDashboard();
  }
})();

