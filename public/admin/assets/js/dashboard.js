/* global Chart */
(function () {
  var sidebar = document.getElementById('sidebar');
  var overlay = document.getElementById('overlay');
  var toggleBtn = document.getElementById('toggleSidebar');

  function closeSidebar() {
    if (sidebar) sidebar.classList.remove('show');
    if (overlay) overlay.classList.remove('show');
  }

  if (toggleBtn) {
    toggleBtn.addEventListener('click', function () {
      if (sidebar) sidebar.classList.toggle('show');
      if (overlay) overlay.classList.toggle('show');
    });
  }

  if (overlay) overlay.addEventListener('click', closeSidebar);

  if (sidebar) {
    sidebar.querySelectorAll('.sidebar .nav-link').forEach(function (link) {
      link.addEventListener('click', function () {
        if (link.dataset && link.dataset.bsToggle === 'collapse') return;
        closeSidebar();
      });
    });
  }
})();

(function () {
  var appBase = window.location.pathname.split('/admin')[0] || '';
  var apiBase = appBase + '/api';
  var adminCurrency = window.ADMIN_CURRENCY || {};
  var currencyCode = adminCurrency.code || 'JOD';
  var currencySymbol = adminCurrency.symbol || currencyCode;
  var permissions = Array.isArray(window.STAFF_PERMISSION_KEYS) ? window.STAFF_PERMISSION_KEYS : [];
  var visibleStaffPermissions = Array.isArray(window.STAFF_VISIBLE_PERMISSION_KEYS) ? window.STAFF_VISIBLE_PERMISSION_KEYS : permissions;
  var adminContext = window.ADMIN_CONTEXT || {};
  var permissionMap = adminContext.permissions || {};
  var activeRestaurantId = Number(adminContext.active_restaurant_id || 0);
  var isSuperAdmin = !!adminContext.is_super_admin;
  var isBranchBrandContext = !!adminContext.is_branch_brand_context;
  var currentEmployeeId = Number((adminContext.employee || {}).id || 0);

  function can(permission) {
    return isSuperAdmin || permissionMap[permission] === true;
  }

  function swalConfirm(message, title) {
    if (typeof Swal === 'undefined') {
      return Promise.resolve(window.confirm(message));
    }

    return Swal.fire({
      title: title || 'Are you sure?',
      text: message,
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes',
      cancelButtonText: 'Cancel',
      confirmButtonColor: '#b8541b'
    }).then(function (result) {
      return result.isConfirmed;
    });
  }

  function swalToast(message, icon) {
    if (typeof Swal === 'undefined') return;

    Swal.fire({
      toast: true,
      position: 'top-end',
      timer: 1800,
      showConfirmButton: false,
      icon: icon || 'success',
      title: message
    });
  }

  function scopedPath(path) {
    if (!activeRestaurantId || path.indexOf('restaurant_id=') !== -1) return path;
    if (!/^\/(orders|tables|employees|menu-foods|menu-categories|food-addons|discounts|inventory|invoices|uploads|logs)(\?|$)/.test(path)) return path;
    return path + (path.indexOf('?') === -1 ? '?' : '&') + 'restaurant_id=' + encodeURIComponent(activeRestaurantId);
  }

  function request(path, options) {
    return fetch(apiBase + scopedPath(path), Object.assign({
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' }
    }, options || {})).then(function (response) {
      return response.json().catch(function () {
        return { success: false, message: 'Invalid JSON response.' };
      }).then(function (payload) {
        if (!response.ok || payload.success === false) throw payload;
        return payload;
      });
    });
  }

  function uploadImage(file, type) {
    if (!file) return Promise.resolve('');

    var form = new FormData();
    form.append('image', file);
    form.append('type', type || 'general');
    if (activeRestaurantId) form.append('restaurant_id', activeRestaurantId);

    return fetch(apiBase + scopedPath('/uploads'), {
      method: 'POST',
      credentials: 'same-origin',
      body: form
    }).then(function (response) {
      return response.json().catch(function () {
        return { success: false, message: 'Invalid JSON response.' };
      }).then(function (payload) {
        if (!response.ok || payload.success === false) throw payload;
        return payload.data && payload.data.path ? payload.data.path : '';
      });
    });
  }

  function setImagePreview(preview, path, iconClass) {
    if (!preview) return;

    if (path) {
      preview.innerHTML = '<img src="' + escapeHtml(path) + '" alt="">';
      return;
    }

    preview.innerHTML = '<i class="' + escapeHtml(iconClass || 'bi bi-image') + '"></i>';
  }

  function text(value, fallback) {
    if (value === null || value === undefined || value === '') return fallback || '';
    return String(value);
  }

  function money(value) {
    return Number(value || 0).toFixed(2) + ' ' + currencySymbol;
  }

  function escapeHtml(value) {
    return text(value).replace(/[&<>"']/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
    });
  }

  function initHeaderActions() {
    var input = document.getElementById('headerSearchInput');
    var icon = document.getElementById('headerSearchIcon');
    var results = document.getElementById('headerSearchResults');
    if (!input || !results) return;

    var aliases = {
      dashboard: 'home overview stats charts live',
      restaurants: 'restaurant restaurants branch owner super admin',
      orders: 'order orders receipt kitchen food status table waiting finished canceled',
      cashier: 'cashier tables takeaway payment paid unpaid pickup',
      takeaway: 'takeaway pickup order orders paid unpaid finished waiting',
      menu: 'menu food foods category categories items dish price addon',
      tables: 'table tables floor qr status seats payment',
      inventory: 'inventory stock waste pcs kgs liters movement',
      discounts: 'discount discounts offer food category addon menu percent fixed',
      invoices: 'invoice invoices tax jofotara receipt payment print qr',
      staff: 'staff employee employees permission role user',
      settings: 'settings restaurant configuration tax invoice size',
      log: 'log logs activity audit edits messages staff actions'
    };
    var nav = (Array.isArray(window.ADMIN_NAV) ? window.ADMIN_NAV : []).slice();
    var settingsLink = document.querySelector('.sidebar .nav-link[href*="page=settings"]');
    if (settingsLink && !nav.some(function (item) { return item.key === 'settings'; })) {
      nav.push({
        key: 'settings',
        label: 'Settings',
        icon: 'bi-gear-fill',
        url: settingsLink.getAttribute('href') || '?page=settings'
      });
    }

    function enriched(item) {
      return [
        item.key,
        item.label,
        aliases[item.key] || ''
      ].join(' ').toLowerCase();
    }

    function renderHeaderResults() {
      var query = input.value.trim().toLowerCase();
      if (!query) {
        results.classList.add('d-none');
        results.innerHTML = '';
        return;
      }

      var matches = nav.filter(function (item) {
        return enriched(item).indexOf(query) !== -1;
      }).slice(0, 7);

      results.innerHTML = matches.map(function (item, index) {
        return '<a class="header-search-item' + (index === 0 ? ' active' : '') + '" href="' + escapeHtml(item.url || ('?page=' + item.key)) + '">' +
          '<i class="bi ' + escapeHtml(item.icon || 'bi-grid') + '"></i>' +
          '<span><span class="fw-semibold d-block">' + escapeHtml(item.label) + '</span>' +
          '<small class="text-secondary">' + escapeHtml((aliases[item.key] || 'Open section').split(' ').slice(0, 5).join(' ')) + '</small></span>' +
        '</a>';
      }).join('') || '<div class="header-search-empty">No matching section found.</div>';

      results.classList.remove('d-none');
    }

    if (icon) {
      icon.addEventListener('click', function () {
        input.focus();
        renderHeaderResults();
      });
    }

    input.addEventListener('input', renderHeaderResults);
    input.addEventListener('focus', renderHeaderResults);
    input.addEventListener('keydown', function (event) {
      var first = results.querySelector('.header-search-item');
      if (event.key === 'Enter' && first) {
        event.preventDefault();
        window.location.href = first.getAttribute('href');
      }
      if (event.key === 'Escape') {
        results.classList.add('d-none');
        input.blur();
      }
    });

    document.addEventListener('click', function (event) {
      if (!event.target.closest('#headerSearchWrap')) {
        results.classList.add('d-none');
      }
    });
  }

  function statusBadge(status) {
    var normalized = text(status, 'waiting');
    var color = { waiting: 'warning', finished: 'success', canceled: 'danger' }[normalized] || 'secondary';
    var label = { waiting: 'Waiting', finished: 'Finished / Completed', canceled: 'Canceled' }[normalized] || normalized;
    return '<span class="badge badge-status bg-' + color + '-subtle text-' + color + ' border border-' + color + '-subtle">' +
      escapeHtml(label) +
      '</span>';
  }

  function groupOrders(rows) {
    var grouped = {};
    rows.forEach(function (row) {
      var id = row.order_id;
      if (!grouped[id]) {
        grouped[id] = {
          order_id: id,
          session_order_key: row.session_order_key,
          restaurant_id: row.restaurant_id,
          restaurant_name: row.restaurant_name,
          table_number: row.table_number,
          order_type: row.order_type || 'table',
          status: row.status,
          created_at: row.created_at,
          order_price: Number(row.order_price || row.price || 0),
          order_profit: Number(row.order_profit || row.profit || 0),
          items: 0,
          rows: []
        };
      }
      grouped[id].items += Number(row.qty || 1);
      grouped[id].rows.push(row);
    });

    return Object.keys(grouped).map(function (key) { return grouped[key]; });
  }

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

    var categoryTop = compactTop(categoryCounts, 5, 'No category data');
    var dishTop = compactTop(dishCounts, 6, 'No dish data');
    var chartColors = ['#b8541b', '#1c7ed6', '#2f9e44', '#7048e8', '#f08c00', '#0ca678'];
    var dailySalaryCost = (staff || []).reduce(function (sum, person) {
      return sum + Number(person.salary || 0);
    }, 0) / 30;
    var profitAfterSalaryByDay = profitByDay.map(function (value) {
      return value - dailySalaryCost;
    });
    var gridColor = 'rgba(122, 130, 143, .16)';
    var labelColor = '#68707d';
    var commonOptions = {
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
    };

    var revenueCanvas = chartCanvas('revenueChart');
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

    var categoryCanvas = chartCanvas('categoryChart');
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

    var profitOnlyCanvas = chartCanvas('profitOnlyChart');
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

    var profitSalaryCanvas = chartCanvas('profitSalaryChart');
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

    var hourCanvas = chartCanvas('ordersHourChart');
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

    var dishesCanvas = chartCanvas('topDishesChart');
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

    var statusCanvas = chartCanvas('statusChart');
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
        request('/employees'),
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

  function initOrdersPage() {
    var body = document.getElementById('ordersPageTableBody');
    var form = document.getElementById('ordersFilterForm');
    if (!body || !form) return;

    var showing = document.getElementById('ordersPageShowing');
    var categoryFilter = document.getElementById('ordersCategoryFilter');
    var categoryDropdown = document.getElementById('ordersCategoryDropdown');
    var statusFilter = document.getElementById('ordersStatusFilter');
    var sortFilter = document.getElementById('ordersSortFilter');
    var detailTitle = document.getElementById('orderDetailTitle');
    var detailMeta = document.getElementById('orderDetailMeta');
    var detailBody = document.getElementById('orderDetailBody');
    var showAllFoodsBtn = document.getElementById('ordersShowAllFoodsBtn');
    var ordersPageRows = [];
    var selectedOrderId = null;
    var showAllFoods = false;

    function buildOrdersQuery() {
      var params = new URLSearchParams();
      var restaurantId = document.getElementById('ordersRestaurantId').value.trim();
      var orderId = document.getElementById('ordersOrderId').value.trim();
      var sessionKey = document.getElementById('ordersSessionKey').value.trim();

      if (restaurantId) params.set('restaurant_id', restaurantId);
      if (orderId) params.set('order_id', orderId);
      if (sessionKey) params.set('session_order_key', sessionKey);

      var query = params.toString();
      return '/orders' + (query ? '?' + query : '');
    }

    function selectedCategoryIds() {
      if (!categoryFilter) return [];

      return Array.from(categoryFilter.querySelectorAll('.orders-category-check:checked')).map(function (input) {
        return Number(input.value || 0);
      }).filter(function (id) {
        return id > 0;
      });
    }

    function updateCategoryDropdownLabel() {
      if (!categoryDropdown || !categoryFilter) return;

      var selected = Array.from(categoryFilter.querySelectorAll('.orders-category-check:checked'));
      if (selected.length === 0) {
        categoryDropdown.textContent = 'All categories';
        return;
      }

      if (selected.length === 1) {
        categoryDropdown.textContent = selected[0].dataset.label || '1 category';
        return;
      }

      categoryDropdown.textContent = selected.length + ' categories selected';
    }

    function selectedStatus() {
      return statusFilter ? statusFilter.value : '';
    }

    function selectedSort() {
      return sortFilter ? sortFilter.value : 'newest';
    }

    function sortOrders(orders) {
      var dir = selectedSort() === 'oldest' ? 1 : -1;

      return orders.sort(function (a, b) {
        var at = Date.parse(String(a.created_at || '').replace(' ', 'T')) || 0;
        var bt = Date.parse(String(b.created_at || '').replace(' ', 'T')) || 0;
        if (at !== bt) return (at - bt) * dir;
        return (Number(a.order_id || 0) - Number(b.order_id || 0)) * dir;
      });
    }

    function filterRows(rows) {
      var categoryIds = selectedCategoryIds();
      var status = selectedStatus();

      return rows.filter(function (row) {
        var categoryMatch = categoryIds.length === 0 || categoryIds.indexOf(Number(row.category_id || 0)) !== -1;
        var statusMatch = !status || row.status === status;

        return categoryMatch && statusMatch;
      });
    }

    function foodStatusBadge(status) {
      var normalized = text(status, 'waiting');
      var color = { waiting: 'warning', finished: 'success', canceled: 'danger' }[normalized] || 'secondary';
      var label = { waiting: 'Waiting', finished: 'Finished', canceled: 'Canceled' }[normalized] || normalized;

      return '<span class="badge badge-status bg-' + color + '-subtle text-' + color + ' border border-' + color + '-subtle">' +
        escapeHtml(label) +
      '</span>';
    }

    function addonSignature(addons) {
      if (!Array.isArray(addons) || addons.length === 0) return 'none';

      return addons.map(function (addon) {
        return Number(addon.id || 0);
      }).sort(function (a, b) {
        return a - b;
      }).join(',');
    }

    function groupOrderFoods(rows) {
      var grouped = {};

      rows.forEach(function (row) {
        var status = text(row.food_status, 'waiting');
        var key = [
          row.food_id,
          addonSignature(row.addons),
          text(row.details),
          status,
          row.price,
          row.extra_price
        ].join('|');

        if (!grouped[key]) {
          grouped[key] = Object.assign({}, row, {
            food_status: status,
            qty: 0,
            group_row_ids: [],
            group_price: 0
          });
        }

        grouped[key].qty += Number(row.qty || 1);
        grouped[key].group_price += Number(row.price || row.food_price || 0);
        grouped[key].group_row_ids.push(row.order_food_row_id || row.order_food_id);
      });

      return Object.keys(grouped).map(function (key) { return grouped[key]; });
    }

    function foodStatusActions(food) {
      if (!can('orders.update')) return '';

      var rowIds = Array.isArray(food.group_row_ids) && food.group_row_ids.length
        ? food.group_row_ids
        : [food.order_food_row_id || food.order_food_id];
      var ids = rowIds.join(',');
      var qty = Number(food.qty || rowIds.length || 1);

      return '<div class="food-status-actions">' +
        '<button class="btn btn-sm btn-outline-warning order-food-status-set" data-id="' + escapeHtml(rowIds[0]) + '" data-ids="' + escapeHtml(ids) + '" data-status="waiting">Waiting</button>' +
        '<button class="btn btn-sm btn-outline-success order-food-status-set" data-id="' + escapeHtml(rowIds[0]) + '" data-ids="' + escapeHtml(ids) + '" data-status="finished">Finished all</button>' +
        '<button class="btn btn-sm btn-outline-danger order-food-cancel-qty" data-id="' + escapeHtml(rowIds[0]) + '" data-ids="' + escapeHtml(ids) + '" data-max="' + escapeHtml(qty) + '">Cancel qty</button>' +
      '</div>';
    }

    function renderAddons(addons) {
      if (!Array.isArray(addons) || addons.length === 0) {
        return '<div class="order-food-meta mt-2">No addons</div>';
      }

      return '<div class="addon-list">' + addons.map(function (addon) {
        return '<span class="addon-pill">' +
          escapeHtml(addon.name_en || addon.name_ar || 'Addon') +
          ' / ' + escapeHtml(addon.name_ar || addon.name_en || 'Addon') +
          ' +' + money(addon.extra_price) +
        '</span>';
      }).join('') + '</div>';
    }

    function renderOrderDetails(order) {
      if (!detailBody) return;

      if (!order) {
        detailTitle.textContent = 'Order details';
        detailMeta.textContent = 'Select an order';
        detailBody.innerHTML = '<div class="text-center text-secondary py-5">Select an order to show its foods.</div>';
        if (showAllFoodsBtn) showAllFoodsBtn.classList.add('d-none');
        return;
      }

      var categoryIds = selectedCategoryIds();
      var foodRows = showAllFoods || categoryIds.length === 0
        ? order.rows
        : order.rows.filter(function (row) { return categoryIds.indexOf(Number(row.category_id || 0)) !== -1; });
      var foods = groupOrderFoods(foodRows);

      detailTitle.textContent = 'Order #' + order.order_id;
      detailMeta.textContent = (order.order_type === 'takeaway' ? 'Takeaway' : 'Table T-' + (order.table_number || '-')) + ' / ' + text(order.status, 'waiting');
      if (showAllFoodsBtn) showAllFoodsBtn.classList.toggle('d-none', categoryIds.length === 0 || showAllFoods);

      detailBody.innerHTML =
        '<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">' +
          '<div>' + statusBadge(order.status) + '<span class="ms-2 fw-bold">' + money(order.order_price) + '</span></div>' +
        '</div>' +
        foods.map(function (food) {
          var foodStatus = text(food.food_status, 'waiting');

          return '<div class="order-food-card food-status-' + escapeHtml(foodStatus) + '">' +
            '<div class="order-food-title">' +
              '<span>' + escapeHtml(food.food_name_en || '-') + ' / ' + escapeHtml(food.food_name_ar || '-') + '</span>' +
              '<span>' + money(food.group_price || food.price || food.food_price) + '</span>' +
            '</div>' +
            '<div class="mt-2">' + foodStatusBadge(foodStatus) + '</div>' +
            '<div class="order-food-meta">' +
              escapeHtml(food.category_name_en || '-') + ' / ' + escapeHtml(food.category_name_ar || '-') +
              ' - Qty ' + escapeHtml(food.qty || 1) +
            '</div>' +
            '<p class="order-food-meta mb-0 mt-2">' +
              escapeHtml(food.food_description_en || '-') +
              '<br>' +
              escapeHtml(food.food_description_ar || '-') +
            '</p>' +
            renderAddons(food.addons) +
            (food.details ? '<div class="order-food-meta mt-2"><strong>Chef note:</strong> ' + escapeHtml(food.details) + '</div>' : '') +
            foodStatusActions(food) +
          '</div>';
        }).join('') || '<div class="text-center text-secondary py-4">No foods match this category.</div>';
    }

    function renderOrdersPage(rows) {
      ordersPageRows = rows;
      var filteredRows = filterRows(rows);
      var orders = sortOrders(groupOrders(filteredRows));
      var allOrders = groupOrders(rows);

      if (selectedOrderId && !orders.some(function (order) { return Number(order.order_id) === Number(selectedOrderId); })) {
        selectedOrderId = null;
        showAllFoods = false;
      }

      body.innerHTML = orders.map(function (order) {
        var activeClass = Number(order.order_id) === Number(selectedOrderId) ? ' class="order-row-active"' : '';

        return '<tr data-order-id="' + escapeHtml(order.order_id) + '"' + activeClass + '>' +
          '<td class="fw-semibold">#' + escapeHtml(order.order_id) + '</td>' +
          '<td>' + escapeHtml(order.order_type === 'takeaway' ? 'Takeaway' : 'T-' + (order.table_number || '-')) + '</td>' +
          '<td>' + escapeHtml(order.items) + '</td>' +
          '<td>' + money(order.order_price) + '</td>' +
          '<td>' + statusBadge(order.status) + '</td>' +
          '<td class="text-end">' + orderActions(order) + '</td>' +
        '</tr>';
      }).join('') || '<tr><td colspan="6" class="text-center text-secondary py-4">No orders found.</td></tr>';

      if (showing) showing.textContent = 'Showing ' + orders.length + ' orders from ' + filteredRows.length + ' food rows';
      renderOrderDetails(allOrders.find(function (order) { return Number(order.order_id) === Number(selectedOrderId); }) || null);
    }

    var ordersLiveTimer = null;
    var ordersLoading = false;

    function loadOrdersPage(silent) {
      if (ordersLoading) return;
      ordersLoading = true;

      if (!silent) {
        body.innerHTML = '<tr><td colspan="6" class="text-center text-secondary py-4">Loading orders...</td></tr>';
        if (showing) showing.textContent = 'Loading orders...';
      }

      request(buildOrdersQuery()).then(function (payload) {
        renderOrdersPage(payload.data || []);
      }).catch(function (error) {
        if (!silent) {
          body.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-4">' +
            escapeHtml(error.message || 'Unable to load orders.') +
            '</td></tr>';
          if (showing) showing.textContent = 'Unable to load orders';
        }
      }).finally(function () {
        ordersLoading = false;
      });
    }

    function parseRowIds(value) {
      return text(value).split(',').map(function (id) {
        return Number(id || 0);
      }).filter(function (id) {
        return id > 0;
      });
    }

    function updateFoodRowsStatus(button, status, cancelQty) {
      var rowIds = parseRowIds(button.dataset.ids || button.dataset.id);
      var firstId = Number(button.dataset.id || rowIds[0] || 0);
      if (!firstId || rowIds.length === 0) return;

      request('/order-foods/' + firstId + '/status', {
        method: 'PATCH',
        body: JSON.stringify({
          status: status,
          row_ids: rowIds,
          cancel_qty: cancelQty || 0
        })
      }).then(function () {
        swalToast('Food status updated');
        loadOrdersPage(true);
      }).catch(function (error) {
        window.alert(error.message || 'Unable to update food.');
      });
    }

    function cancelFoodQuantity(button) {
      var maxQty = Number(button.dataset.max || 1);

      if (typeof Swal === 'undefined') {
        var qty = Number(window.prompt('Cancel quantity', '1') || 0);
        if (qty > 0 && qty <= maxQty) updateFoodRowsStatus(button, 'canceled', qty);
        return;
      }

      Swal.fire({
        title: 'Cancel food quantity',
        input: 'number',
        inputValue: 1,
        inputAttributes: {
          min: 1,
          max: maxQty,
          step: 1
        },
        text: 'Choose how many items to mark as canceled.',
        showCancelButton: true,
        confirmButtonText: 'Cancel quantity',
        confirmButtonColor: '#b8541b',
        inputValidator: function (value) {
          var qty = Number(value || 0);
          if (!Number.isInteger(qty) || qty < 1 || qty > maxQty) {
            return 'Enter a quantity from 1 to ' + maxQty + '.';
          }

          return null;
        }
      }).then(function (result) {
        if (!result.isConfirmed) return;
        updateFoodRowsStatus(button, 'canceled', Number(result.value));
      });
    }

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      showAllFoods = false;
      loadOrdersPage();
    });

    if (categoryFilter) {
      categoryFilter.addEventListener('change', function (event) {
        if (!event.target.classList.contains('orders-category-check')) return;
        showAllFoods = false;
        updateCategoryDropdownLabel();
        renderOrdersPage(ordersPageRows);
      });
    }

    if (statusFilter) {
      statusFilter.addEventListener('change', function () {
        renderOrdersPage(ordersPageRows);
      });
    }

    if (sortFilter) {
      sortFilter.addEventListener('change', function () {
        renderOrdersPage(ordersPageRows);
      });
    }

    if (activeRestaurantId && !isSuperAdmin) {
      document.getElementById('ordersRestaurantId').value = activeRestaurantId;
      document.getElementById('ordersRestaurantId').readOnly = true;
    }

    body.addEventListener('click', function (event) {
      var del = event.target.closest('.order-delete');
      var update = event.target.closest('.order-update');
      var fullStatus = event.target.closest('.order-full-status');
      var statusButton = event.target.closest('.order-food-status-set');
      var cancelQtyButton = event.target.closest('.order-food-cancel-qty');
      var row = event.target.closest('tr[data-order-id]');

      if (statusButton) {
        event.stopPropagation();
        updateFoodRowsStatus(statusButton, statusButton.dataset.status);
        return;
      }

      if (cancelQtyButton) {
        event.stopPropagation();
        cancelFoodQuantity(cancelQtyButton);
        return;
      }

      if (fullStatus) {
        event.stopPropagation();
        updateFullOrderStatus(fullStatus.dataset.id, fullStatus.dataset.status || 'waiting');
        return;
      }

      if (del) {
        event.stopPropagation();
        swalConfirm('Delete this order?', 'Delete order').then(function (confirmed) {
          if (!confirmed) return;
          request('/orders/' + del.dataset.id, { method: 'DELETE' }).then(loadOrdersPage).catch(function (error) {
            window.alert(error.message || 'Unable to delete order.');
          });
        });
        return;
      }

      if (update) {
        event.stopPropagation();
        selectedOrderId = Number(update.dataset.id || 0);
        renderOrdersPage(ordersPageRows);
        return;
      }

      if (row) {
        selectedOrderId = Number(row.dataset.orderId || 0);
        showAllFoods = false;
        renderOrdersPage(ordersPageRows);
      }
    });

    function updateFullOrderStatus(orderId, currentStatus) {
      var statuses = {
        waiting: 'Waiting',
        finished: 'Finished / Completed',
        canceled: 'Canceled'
      };

      if (typeof Swal === 'undefined') {
        var promptStatus = window.prompt('Full order status: waiting, finished, canceled', currentStatus || 'waiting');
        if (!promptStatus) return;
        saveFullOrderStatus(orderId, promptStatus);
        return;
      }

      Swal.fire({
        title: 'Update full order status',
        input: 'select',
        inputOptions: statuses,
        inputValue: currentStatus || 'waiting',
        showCancelButton: true,
        confirmButtonText: 'Update',
        confirmButtonColor: '#b8541b',
        inputValidator: function (value) {
          return statuses[value] ? null : 'Select a valid status.';
        }
      }).then(function (result) {
        if (!result.isConfirmed) return;
        saveFullOrderStatus(orderId, result.value);
      });
    }

    function saveFullOrderStatus(orderId, status) {
      request('/orders/' + orderId, {
        method: 'PUT',
        body: JSON.stringify({ status: status })
      }).then(function () {
        swalToast('Order status updated');
        loadOrdersPage(true);
      }).catch(function (error) {
        window.alert(error.message || 'Unable to update order.');
      });
    }

    if (detailBody) {
      detailBody.addEventListener('click', function (event) {
        var statusButton = event.target.closest('.order-food-status-set');
        var cancelQtyButton = event.target.closest('.order-food-cancel-qty');

        if (statusButton) {
          updateFoodRowsStatus(statusButton, statusButton.dataset.status);
          return;
        }

        if (cancelQtyButton) {
          cancelFoodQuantity(cancelQtyButton);
        }
      });
    }

    if (showAllFoodsBtn) {
      showAllFoodsBtn.addEventListener('click', function () {
        showAllFoods = true;
        renderOrdersPage(ordersPageRows);
      });
    }

    if (categoryFilter) {
      request('/menu-categories').then(function (payload) {
        var categories = payload.data || [];
        categoryFilter.innerHTML = categories.map(function (category) {
          var label = category.name_en || category.name_ar || ('Category #' + category.id);

          return '<label class="dropdown-item orders-category-option">' +
            '<input class="form-check-input me-2 orders-category-check" type="checkbox" value="' + escapeHtml(category.id) + '" data-label="' + escapeHtml(label) + '">' +
            '<span>' + escapeHtml(label) + '</span>' +
          '</label>';
        }).join('') || '<div class="text-secondary small px-2 py-1">No categories found.</div>';
        updateCategoryDropdownLabel();
      }).catch(function () {});
    }

    loadOrdersPage();
    ordersLiveTimer = window.setInterval(function () {
      if (document.hidden) return;
      loadOrdersPage(true);
    }, 1000);

    window.addEventListener('beforeunload', function () {
      if (ordersLiveTimer) window.clearInterval(ordersLiveTimer);
    });
  }

  function permissionsToString() {
    var checked = {};
    document.querySelectorAll('.staff-permission').forEach(function (input) {
      checked[input.value] = input.checked ? '1' : '0';
    });
    return permissions.map(function (key) { return checked[key] || '0'; }).join(',');
  }

  function applyPermissionString(value) {
    var values = text(value).split(',');
    document.querySelectorAll('.staff-permission').forEach(function (input) {
      var index = permissions.indexOf(input.value);
      input.checked = values[index] === '1';
    });
    lockPermissionChildren();
    syncPermissionGroupState();
  }

  function lockPermissionChildren() {
    ['restaurants', 'restaurant', 'employees', 'inventory', 'orders', 'foods', 'categories', 'discounts', 'tables'].forEach(function (group) {
      var read = document.querySelector('.staff-permission[value="' + group + '.get"]');
      if (!read) return;

      ['create', 'update', 'delete'].forEach(function (action) {
        var child = document.querySelector('.staff-permission[value="' + group + '.' + action + '"]');
        if (!child) return;
        child.disabled = !read.checked;
        if (!read.checked) child.checked = false;
      });
    });
  }

  function syncPermissionGroupState() {
    document.querySelectorAll('.permission-group').forEach(function (group) {
      var read = group.querySelector('.permission-read');
      var button = group.querySelector('.permission-group-toggle');
      if (!read || !button) return;

      button.classList.toggle('permission-enabled', read.checked);
    });
  }

  function permissionCollapse(group) {
    var collapse = group ? group.querySelector('.accordion-collapse') : null;
    if (!collapse || typeof bootstrap === 'undefined') return null;

    return bootstrap.Collapse.getOrCreateInstance(collapse, { toggle: false });
  }

  function initStaff() {
    var body = document.getElementById('staffTableBody');
    var form = document.getElementById('staffForm');
    if (!body) return;

    var currentStaff = [];
    var alertBox = document.getElementById('staffFormAlert');
    var modalEl = document.getElementById('staffModal');
    var staffModal = modalEl ? new bootstrap.Modal(modalEl) : null;
    var staffPfpFile = document.getElementById('staffPfpFile');
    var staffPfpPreview = document.getElementById('staffPfpPreview');
    var branchRows = [];
    var currentRestaurant = null;
    var staffPage = window.STAFF_PAGE || { mode: 'staff', title: 'Staff', singular: 'Staff Member' };
    var managersOnly = staffPage.mode === 'managers';

    function showError(message) {
      if (!alertBox) {
        window.alert(message);
        return;
      }
      alertBox.textContent = message;
      alertBox.classList.remove('d-none');
    }

    function clearForm() {
      if (!form) return;
      form.reset();
      document.getElementById('staffId').value = '';
      document.getElementById('staffPassword').required = true;
      document.getElementById('staffFormTitle').textContent = 'Add ' + staffPage.singular;
      document.getElementById('staffPfp').value = '';
      document.getElementById('staffSalary').value = '0';
      document.getElementById('staffBranchId').value = '';
      document.getElementById('staffRole').value = managersOnly ? 'manager' : 'delivery_manager';
      document.getElementById('staffRole').disabled = managersOnly;
      setImagePreview(staffPfpPreview, '', 'bi bi-person');
      applyPermissionString('');
      if (managersOnly) applyManagerPermissionDefaults();
      setPermissionInputsLocked(false);
      if (alertBox) alertBox.classList.add('d-none');
      if (activeRestaurantId) document.getElementById('staffRestaurantId').value = activeRestaurantId;
    }

    function renderStaff(rows) {
      body.innerHTML = rows.map(function (person) {
        var enabled = text(person.permissions).split(',').filter(function (value) { return value.trim() === '1'; }).length;
        var actions = '';
        if (can('employees.update')) {
          actions += '<button class="btn btn-sm btn-outline-primary staff-edit" type="button" data-id="' + person.id + '"><i class="bi bi-pencil"></i></button> ';
        }
        if (can('employees.delete')) {
          actions += '<button class="btn btn-sm btn-outline-danger staff-delete" type="button" data-id="' + person.id + '"><i class="bi bi-trash"></i></button>';
        }

        return '<tr><td class="fw-semibold">' + escapeHtml(person.name) + '</td><td>' +
          escapeHtml(person.username) + '</td><td>' + escapeHtml(person.role) + '</td><td>' +
          money(person.salary || 0) + '</td><td>' + escapeHtml(person.branch_name || '-') + '</td><td>' +
          enabled + ' enabled</td><td class="text-end">' + (actions || '<span class="text-secondary">-</span>') + '</td></tr>';
      }).join('') || '<tr><td colspan="7" class="text-center text-secondary py-4">No ' + escapeHtml(staffPage.title.toLowerCase()) + ' found.</td></tr>';
    }

    function renderBranchSelect() {
      var wrap = document.getElementById('staffBranchWrap');
      var select = document.getElementById('staffBranchId');
      if (!wrap || !select) return;

      var enabled = currentRestaurant && Number(currentRestaurant.branch_management_enabled || 0) === 1;
      wrap.classList.toggle('d-none', !enabled);
      select.required = !!enabled && !managersOnly;
      select.innerHTML = '<option value="">Select branch</option>' + branchRows.map(function (branch) {
        return '<option value="' + escapeHtml(branch.id) + '">' + escapeHtml(branch.name) + '</option>';
      }).join('');
    }

    function loadStaff() {
      request('/employees').then(function (payload) {
        currentStaff = (payload.data || []).filter(function (person) {
          return !managersOnly || person.role === 'manager' || person.role === 'owner';
        });
        renderStaff(currentStaff);
      }).catch(function (error) {
        body.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">' + escapeHtml(error.message || ('Unable to load ' + staffPage.title.toLowerCase() + '.')) + '</td></tr>';
      });
    }

    function loadBranches() {
      request('/restaurants').then(function (payload) {
        var restaurants = payload.data || [];
        currentRestaurant = restaurants.find(function (row) {
          return String(row.id) === String(activeRestaurantId);
        }) || null;
        branchRows = restaurants.filter(function (row) {
          return String(row.parent_restaurant_id || '') === String(activeRestaurantId);
        });
        renderBranchSelect();
      }).catch(function () {
        currentRestaurant = null;
        branchRows = [];
        renderBranchSelect();
      });
    }

    if (form) {
      var addBtn = document.getElementById('staffAddBtn');
      if (addBtn) {
        addBtn.addEventListener('click', function () {
          clearForm();
        });
      }

      document.querySelectorAll('.staff-permission').forEach(function (input) {
        input.addEventListener('change', function () {
          var group = input.closest('.permission-group');
          lockPermissionChildren();
          syncPermissionGroupState();

          if (input.classList.contains('permission-read')) {
            var collapse = permissionCollapse(group);
            if (collapse) {
              if (input.checked) {
                collapse.show();
              } else {
                collapse.hide();
              }
            }
          }
        });
      });

      document.getElementById('staffSelectAllPermissions').addEventListener('click', function () {
        var inputs = Array.from(document.querySelectorAll('.staff-permission')).filter(function (input) {
          return visibleStaffPermissions.indexOf(input.value) !== -1;
        });
        var next = inputs.some(function (input) { return !input.checked; });
        inputs.forEach(function (input) { input.checked = next; });
        lockPermissionChildren();
        syncPermissionGroupState();
      });

      document.querySelectorAll('.permission-group-toggle').forEach(function (button) {
        button.addEventListener('click', function (event) {
          var group = button.closest('.permission-group');
          var read = group ? group.querySelector('.permission-read') : null;
          if (read && !read.checked) {
            read.checked = true;
            lockPermissionChildren();
            syncPermissionGroupState();
          }
        });
      });
    }

    function setPermissionInputsLocked(locked) {
      document.querySelectorAll('.staff-permission').forEach(function (input) {
        input.disabled = !!locked;
      });
      var toggle = document.getElementById('staffSelectAllPermissions');
      if (toggle) toggle.disabled = !!locked;
      if (!locked) lockPermissionChildren();
    }

    function applyManagerPermissionDefaults() {
      var defaults = {
        'branches.get': true,
        'branches.create': true,
        'branches.update': true,
        'branches.delete': true,
        'employees.get': true,
        'branches_logs.get': true
      };
      document.querySelectorAll('.staff-permission').forEach(function (input) {
        if (defaults[input.value]) input.checked = true;
      });
      lockPermissionChildren();
      syncPermissionGroupState();
    }

    body.addEventListener('click', function (event) {
      var edit = event.target.closest('.staff-edit');
      var del = event.target.closest('.staff-delete');

      if (edit) {
        var person = currentStaff.find(function (row) { return String(row.id) === String(edit.dataset.id); });
        if (!person) return;
        if (!form) return;
        document.getElementById('staffId').value = person.id;
        document.getElementById('staffName').value = person.name || '';
        document.getElementById('staffUsername').value = person.username || '';
        document.getElementById('staffPassword').value = '';
        document.getElementById('staffPassword').required = false;
        document.getElementById('staffRole').value = person.role || 'delivery_manager';
        document.getElementById('staffRole').disabled = managersOnly;
        document.getElementById('staffSalary').value = Number(person.salary || 0).toFixed(2);
        document.getElementById('staffRestaurantId').value = person.restaurant_id || '';
        renderBranchSelect();
        document.getElementById('staffBranchId').value = person.branch_id || '';
        document.getElementById('staffPfp').value = person.pfp || '';
        if (staffPfpFile) staffPfpFile.value = '';
        setImagePreview(staffPfpPreview, person.pfp || '', 'bi bi-person');
        document.getElementById('staffDescription').value = person.description || '';
        applyPermissionString(person.permissions);
        setPermissionInputsLocked(Number(person.id || 0) === currentEmployeeId);
        document.getElementById('staffFormTitle').textContent = 'Edit ' + staffPage.singular;
        if (staffModal) staffModal.show();
      }

      if (del) {
        swalConfirm('Delete this staff member?', 'Delete staff member').then(function (confirmed) {
          if (!confirmed) return;
          request('/employees/' + del.dataset.id, { method: 'DELETE' }).then(function () {
            loadStaff();
            swalToast('Staff member deleted');
          }).catch(function (error) {
            showError(error.message || 'Unable to delete staff member.');
          });
        });
      }
    });

    if (form) {
      if (staffPfpFile) {
        staffPfpFile.addEventListener('change', function () {
          var file = staffPfpFile.files && staffPfpFile.files[0];
          setImagePreview(staffPfpPreview, file ? URL.createObjectURL(file) : document.getElementById('staffPfp').value, 'bi bi-person');
        });
      }

      form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (alertBox) alertBox.classList.add('d-none');

        var id = document.getElementById('staffId').value;
        var password = document.getElementById('staffPassword').value;
        var payload = {
          name: document.getElementById('staffName').value.trim(),
          username: document.getElementById('staffUsername').value.trim(),
          role: managersOnly ? 'manager' : document.getElementById('staffRole').value,
          restaurant_id: Number(document.getElementById('staffRestaurantId').value || activeRestaurantId),
          salary: Number(document.getElementById('staffSalary').value || 0),
          branch_id: document.getElementById('staffBranchId').value ? Number(document.getElementById('staffBranchId').value) : null,
          pfp: document.getElementById('staffPfp').value.trim(),
          description: document.getElementById('staffDescription').value.trim(),
          permissions: permissionsToString()
        };

        if (!id || password !== '') payload.password = password;

        var file = staffPfpFile && staffPfpFile.files ? staffPfpFile.files[0] : null;
        uploadImage(file, 'staff').then(function (path) {
          if (path) payload.pfp = path;

          return request('/employees' + (id ? '/' + id : ''), {
            method: id ? 'PUT' : 'POST',
            body: JSON.stringify(payload)
          });
        }).then(function () {
          clearForm();
          if (staffModal) staffModal.hide();
          loadStaff();
          swalToast(id ? 'Staff member updated' : 'Staff member created');
        }).catch(function (error) {
          var message = error.message || 'Unable to save staff member.';
          if (error.errors) message = Object.values(error.errors).join(' ');
          showError(message);
        });
      });
    }

    document.getElementById('staffSearch').addEventListener('input', function (event) {
      var term = event.target.value.toLowerCase();
      renderStaff(currentStaff.filter(function (person) {
        return [person.name, person.username, person.role].join(' ').toLowerCase().includes(term);
      }));
    });

    clearForm();
    loadBranches();
    if (form && activeRestaurantId) document.getElementById('staffRestaurantId').value = activeRestaurantId;
    loadStaff();
  }

  function orderActions(order) {
    var html = '';
    if (can('orders.update')) {
      html += '<button class="btn btn-sm btn-outline-primary order-update" data-id="' + escapeHtml(order.order_id) + '"><i class="bi bi-eye"></i></button> ';
      html += '<button class="btn btn-sm btn-outline-secondary order-full-status" data-id="' + escapeHtml(order.order_id) + '" data-status="' + escapeHtml(order.status || 'waiting') + '"><i class="bi bi-arrow-repeat"></i> Status</button> ';
    }
    if (can('orders.delete')) {
      html += '<button class="btn btn-sm btn-outline-danger order-delete" data-id="' + escapeHtml(order.order_id) + '"><i class="bi bi-trash"></i></button>';
    }
    return html || '<span class="text-secondary">-</span>';
  }

  function initInventory() {
    var body = document.getElementById('inventoryTableBody');
    if (!body) return;

    var page = window.INVENTORY_PAGE || {};
    var canCreate = !!page.can_create;
    var canUpdate = !!page.can_update;
    var canDelete = !!page.can_delete;
    var rows = [];
    var foods = [];
    var addons = [];
    var movements = [];
    var modalEl = document.getElementById('inventoryModal');
    var modal = modalEl ? new bootstrap.Modal(modalEl) : null;
    var movementModalEl = document.getElementById('inventoryMovementModal');
    var movementModal = movementModalEl ? new bootstrap.Modal(movementModalEl) : null;

    function unitLabel(unit) {
      return { pcs: 'pcs', kgs: 'kgs', liters: 'liters' }[unit] || unit || 'pcs';
    }

    function qty(value, unit) {
      return Number(value || 0).toFixed(unit === 'pcs' ? 0 : 3) + ' ' + unitLabel(unit);
    }

    function foodOptions(selected) {
      return '<option value="">Select food</option>' + foods.map(function (food) {
        return '<option value="' + escapeHtml(food.id) + '"' + (Number(selected) === Number(food.id) ? ' selected' : '') + '>' +
          escapeHtml(food.name_en || food.name_ar || ('Food #' + food.id)) +
        '</option>';
      }).join('');
    }

    function selectedAddonLabel(count) {
      if (count === 0) return 'Select addons';
      if (count === 1) return '1 addon selected';
      return count + ' addons selected';
    }

    function addonDropdown(foodId, selectedLinks) {
      var selectedByAddon = (selectedLinks || []).reduce(function (map, link) {
        if (link.addon_id) map[Number(link.addon_id)] = link;
        return map;
      }, {});
      var foodAddons = addons.filter(function (addon) { return Number(addon.food_id || 0) === Number(foodId || 0); });
      var selectedCount = Object.keys(selectedByAddon).length;

      if (!foodId) {
        return '<button class="form-select text-start inventory-addon-menu-toggle" type="button" disabled>Select food first</button>';
      }

      if (foodAddons.length === 0) {
        return '<button class="form-select text-start inventory-addon-menu-toggle" type="button" disabled>No addons</button>';
      }

      return '<div class="dropdown inventory-addon-dropdown">' +
        '<button class="form-select text-start inventory-addon-menu-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">' +
          escapeHtml(selectedAddonLabel(selectedCount)) +
        '</button>' +
        '<div class="dropdown-menu inventory-addon-menu">' +
          foodAddons.map(function (addon) {
            var selected = selectedByAddon[Number(addon.id)];
            var checked = selected ? ' checked' : '';
            return '<label class="dropdown-item inventory-addon-option">' +
            '<input class="form-check-input inventory-addon-check" type="checkbox" value="' + escapeHtml(addon.id) + '"' + checked + '>' +
              '<span>' + escapeHtml(addon.name_en || addon.name_ar || ('Addon #' + addon.id)) + '</span>' +
            '</label>';
          }).join('') +
        '</div>' +
      '</div>';
    }

    function addonEffectInputs(foodId, selectedLinks) {
      var selectedByAddon = (selectedLinks || []).reduce(function (map, link) {
        if (link.addon_id) map[Number(link.addon_id)] = link;
        return map;
      }, {});
      var foodAddons = addons.filter(function (addon) { return Number(addon.food_id || 0) === Number(foodId || 0); });

      return foodAddons.filter(function (addon) {
        return !!selectedByAddon[Number(addon.id)];
      }).map(function (addon) {
        var selected = selectedByAddon[Number(addon.id)];
        var name = addon.name_en || addon.name_ar || ('Addon #' + addon.id);
        return '<div class="inventory-addon-effect-input" data-addon-id="' + escapeHtml(addon.id) + '">' +
          '<label class="form-label small text-secondary mb-1">Effect of ' + escapeHtml(name) + '</label>' +
          '<div class="input-group">' +
            '<span class="input-group-text">-</span>' +
            '<input class="form-control inventory-addon-qty" type="number" min="0.001" step="0.001" value="' + escapeHtml(selected ? selected.quantity_per_item : '') + '">' +
          '</div>' +
        '</div>';
      }).join('');
    }

    function groupInventoryLinks(links) {
      return (links || []).reduce(function (groups, link) {
        var foodId = Number(link.food_id || 0);
        if (!foodId) return groups;
        if (!groups[foodId]) groups[foodId] = [];
        groups[foodId].push(link);
        return groups;
      }, {});
    }

    function linkRow(foodId, selectedLinks) {
      var links = selectedLinks || [];
      var baseLink = links.find(function (link) { return !link.addon_id; }) || {};
      var div = document.createElement('div');
      div.className = 'inventory-link-row';
      div.innerHTML =
        '<div class="inventory-link-main">' +
          '<div>' +
            '<label class="form-label small text-secondary mb-1">Food</label>' +
            '<select class="form-select inventory-link-food">' + foodOptions(foodId) + '</select>' +
          '</div>' +
          '<div>' +
            '<label class="form-label small text-secondary mb-1">Addons</label>' +
            '<div class="inventory-addon-select">' + addonDropdown(foodId, links) + '</div>' +
          '</div>' +
          '<div class="inventory-stock-effects">' +
            '<label class="form-label small text-secondary mb-1">Normal effect</label>' +
            '<div class="input-group">' +
              '<span class="input-group-text">-</span>' +
              '<input class="form-control inventory-link-qty" type="number" min="0" step="0.001" value="' + escapeHtml(baseLink.quantity_per_item || '') + '">' +
            '</div>' +
            '<div class="inventory-addon-effects">' + addonEffectInputs(foodId, links) + '</div>' +
          '</div>' +
          '<button class="btn btn-outline-danger inventory-link-remove" type="button"><i class="bi bi-x-lg"></i></button>' +
        '</div>';
      var food = div.querySelector('.inventory-link-food');
      var addonSelect = div.querySelector('.inventory-addon-select');
      var effects = div.querySelector('.inventory-addon-effects');

      function selectedAddonLinks() {
        return Array.from(div.querySelectorAll('.inventory-addon-check:checked')).map(function (check) {
          var existingInput = div.querySelector('.inventory-addon-effect-input[data-addon-id="' + check.value + '"] .inventory-addon-qty');
          return {
            food_id: Number(food.value || 0),
            addon_id: Number(check.value),
            quantity_per_item: Number(existingInput ? existingInput.value || 0 : 0)
          };
        });
      }

      food.addEventListener('change', function () {
        addonSelect.innerHTML = addonDropdown(food.value, []);
        effects.innerHTML = '';
      });
      addonSelect.addEventListener('change', function (event) {
        var check = event.target.closest('.inventory-addon-check');
        if (!check) return;
        var selectedLinksNow = selectedAddonLinks();
        addonSelect.innerHTML = addonDropdown(food.value, selectedLinksNow);
        effects.innerHTML = addonEffectInputs(food.value, selectedLinksNow);
      });
      div.querySelector('.inventory-link-remove').addEventListener('click', function () {
        div.remove();
      });
      return div;
    }

    function collectLinks() {
      return Array.from(document.querySelectorAll('.inventory-link-row')).reduce(function (links, row) {
        var foodId = Number(row.querySelector('.inventory-link-food').value || 0);
        var normalQty = Number(row.querySelector('.inventory-link-qty').value || 0);
        if (foodId > 0 && normalQty > 0) {
          links.push({ food_id: foodId, addon_id: null, quantity_per_item: normalQty });
        }

        row.querySelectorAll('.inventory-addon-effect-input').forEach(function (effect) {
          var addonId = Number(effect.dataset.addonId || 0);
          var addonQty = Number(effect.querySelector('.inventory-addon-qty').value || 0);
          if (foodId > 0 && addonId > 0 && addonQty > 0) {
            links.push({ food_id: foodId, addon_id: addonId, quantity_per_item: addonQty });
          }
        });

        return links;
      }, []);
    }

    function fillInventory(item) {
      document.getElementById('inventoryId').value = item ? item.id : '';
      document.getElementById('inventoryFormTitle').textContent = item ? 'Edit Inventory Item' : 'Add Inventory Item';
      document.getElementById('inventoryName').value = item ? item.name : '';
      document.getElementById('inventoryUnit').value = item ? (item.unit || 'pcs') : 'pcs';
      document.getElementById('inventoryQuantity').value = item ? Number(item.quantity || 0) : '';
      document.getElementById('inventoryRestaurantId').value = item ? item.restaurant_id : activeRestaurantId || '';
      document.getElementById('inventoryFormAlert').classList.add('d-none');
      var linksBox = document.getElementById('inventoryLinks');
      linksBox.innerHTML = '';
      var groupedLinks = groupInventoryLinks(item && Array.isArray(item.links) ? item.links : []);
      var foodIds = Object.keys(groupedLinks);

      if (foodIds.length) {
        foodIds.forEach(function (foodId) {
          linksBox.appendChild(linkRow(foodId, groupedLinks[foodId]));
        });
      } else {
        linksBox.appendChild(linkRow('', []));
      }
    }

    function inventoryPayload() {
      return {
        name: document.getElementById('inventoryName').value.trim(),
        unit: document.getElementById('inventoryUnit').value,
        quantity: Number(document.getElementById('inventoryQuantity').value || 0),
        restaurant_id: Number(document.getElementById('inventoryRestaurantId').value || activeRestaurantId || 0),
        links: collectLinks()
      };
    }

    function stockChartColor(value) {
      var amount = Number(value || 0);
      if (amount >= 0 && amount <= 25) return '#2f9e44';
      if (amount > 150) return '#2f9e44';
      return '#f59f00';
    }

    function movementChartColor(type) {
      return {
        purchase: '#2f9e44',
        waste: '#e03131',
        adjustment: '#868e96',
        consume: '#1c7ed6',
        return: '#51cf66'
      }[type] || '#adb5bd';
    }

    function movementChartLabel(type) {
      return {
        purchase: 'Add stock',
        waste: 'Waste',
        adjustment: 'Decrease stock',
        consume: 'Consumed by orders',
        return: 'Returned from canceled foods'
      }[type] || type;
    }

    function movementTooltipLines(type) {
      return movements.filter(function (movement) {
        return movement.movement_type === type;
      }).slice(0, 8).map(function (movement) {
        var amount = Math.abs(Number(movement.quantity_change || 0));
        return qty(amount, movement.unit) + ' ' + (movement.inventory_name || 'Stock item');
      });
    }

    function renderChartsLocal() {
      if (typeof Chart === 'undefined') return;

      var stockCanvas = document.getElementById('inventoryStockChart');
      if (stockCanvas) {
        var stockItems = rows.slice(0, 10);
        if (stockCanvas.chart) stockCanvas.chart.destroy();
        stockCanvas.chart = new Chart(stockCanvas, {
          type: 'bar',
          data: {
            labels: stockItems.map(function (item) { return item.name; }),
            datasets: [{
              data: stockItems.map(function (item) { return Number(item.quantity || 0); }),
              backgroundColor: stockItems.map(function (item) { return stockChartColor(item.quantity); })
            }]
          },
          options: { plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } } } }
        });
      }

      var movementCanvas = document.getElementById('inventoryMovementChart');
      if (movementCanvas) {
        var counts = movements.reduce(function (map, movement) {
          map[movement.movement_type] = (map[movement.movement_type] || 0) + 1;
          return map;
        }, {});
        if (movementCanvas.chart) movementCanvas.chart.destroy();
        movementCanvas.chart = new Chart(movementCanvas, {
          type: 'doughnut',
          data: {
            labels: Object.keys(counts).map(movementChartLabel),
            datasets: [{
              data: Object.keys(counts).map(function (key) { return counts[key]; }),
              backgroundColor: Object.keys(counts).map(movementChartColor)
            }]
          },
          options: {
            plugins: {
              legend: { position: 'bottom' },
              tooltip: {
                callbacks: {
                  label: function (context) {
                    return context.label + ': ' + context.parsed;
                  },
                  afterLabel: function (context) {
                    var type = Object.keys(counts)[context.dataIndex];
                    return movementTooltipLines(type);
                  }
                }
              }
            }
          }
        });
      }
    }

    function renderInventory() {
      document.getElementById('inventoryStatItems').textContent = rows.length;
      document.getElementById('inventoryStatLow').textContent = rows.filter(function (item) { return Number(item.quantity || 0) <= 5; }).length;
      document.getElementById('inventoryStatWaste').textContent = movements.filter(function (item) { return item.movement_type === 'waste'; }).length;
      document.getElementById('inventoryShowing').textContent = 'Showing ' + rows.length + ' stock items';

      body.innerHTML = rows.map(function (item) {
        var links = Array.isArray(item.links) ? item.links : [];
        var linkedText = links.map(function (link) {
          return escapeHtml(link.food_name_en || link.food_name_ar || '-') +
            (link.addon_id ? ' / ' + escapeHtml(link.addon_name_en || link.addon_name_ar || 'Addon') : '') +
            ' = -' + qty(link.quantity_per_item, item.unit);
        }).join('<br>');
        var actions = '';
        if (canUpdate) actions += '<button class="btn btn-sm btn-outline-secondary inventory-movement" data-id="' + escapeHtml(item.id) + '"><i class="bi bi-plus-slash-minus"></i></button> ';
        if (canUpdate) actions += '<button class="btn btn-sm btn-outline-primary inventory-edit" data-id="' + escapeHtml(item.id) + '"><i class="bi bi-pencil"></i></button> ';
        if (canDelete) actions += '<button class="btn btn-sm btn-outline-danger inventory-delete" data-id="' + escapeHtml(item.id) + '"><i class="bi bi-trash"></i></button>';

        return '<tr>' +
          '<td><div class="fw-bold">' + escapeHtml(item.name) + '</div><small class="text-secondary">' + escapeHtml(unitLabel(item.unit)) + '</small></td>' +
          '<td class="fw-bold">' + qty(item.quantity, item.unit) + '</td>' +
          '<td class="small">' + (linkedText || '<span class="text-secondary">No food links</span>') + '</td>' +
          '<td class="text-end">' + (actions || '-') + '</td>' +
        '</tr>';
      }).join('') || '<tr><td colspan="4" class="text-center text-secondary py-4">No inventory items.</td></tr>';

      var list = document.getElementById('inventoryMovementsList');
      list.innerHTML = movements.slice(0, 12).map(function (movement) {
        var negative = Number(movement.quantity_change || 0) < 0;
        return '<li class="list-group-item d-flex justify-content-between gap-2">' +
          '<div><div class="fw-semibold">' + escapeHtml(movement.inventory_name || '-') + '</div><small class="text-secondary">' + escapeHtml(movement.reason || movement.movement_type) + '</small></div>' +
          '<span class="' + (negative ? 'text-danger' : 'text-success') + '">' + qty(movement.quantity_change, movement.unit) + '</span>' +
        '</li>';
      }).join('') || '<li class="list-group-item text-secondary">No movements yet.</li>';

      renderChartsLocal();
    }

    function loadInventory() {
      return Promise.all([
        request('/inventory'),
        request('/inventory/movements'),
        request('/menu-foods'),
        request('/food-addons')
      ]).then(function (results) {
        rows = results[0].data || [];
        movements = results[1].data || [];
        foods = results[2].data || [];
        addons = results[3].data || [];
        renderInventory();
      }).catch(function (error) {
        body.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-4">' + escapeHtml(error.message || 'Unable to load inventory.') + '</td></tr>';
      });
    }

    var addBtn = document.getElementById('inventoryAddBtn');
    if (addBtn) addBtn.addEventListener('click', function () {
      fillInventory(null);
      modal.show();
    });

    document.getElementById('inventoryAddLinkBtn').addEventListener('click', function () {
      document.getElementById('inventoryLinks').appendChild(linkRow('', []));
    });

    body.addEventListener('click', function (event) {
      var edit = event.target.closest('.inventory-edit');
      var del = event.target.closest('.inventory-delete');
      var move = event.target.closest('.inventory-movement');

      if (edit) {
        var item = rows.find(function (row) { return String(row.id) === String(edit.dataset.id); });
        if (!item) return;
        fillInventory(item);
        modal.show();
      }

      if (move) {
        var movingItem = rows.find(function (row) { return String(row.id) === String(move.dataset.id); });
        document.getElementById('movementInventoryId').value = move.dataset.id;
        document.getElementById('inventoryMovementTitle').textContent = movingItem ? 'Movement - ' + movingItem.name : 'Inventory Movement';
        document.getElementById('inventoryMovementForm').reset();
        document.getElementById('inventoryMovementAlert').classList.add('d-none');
        movementModal.show();
      }

      if (del) {
        swalConfirm('Delete this inventory item?', 'Delete inventory').then(function (confirmed) {
          if (!confirmed) return;
          request('/inventory/' + del.dataset.id, { method: 'DELETE' }).then(loadInventory).catch(function (error) {
            window.alert(error.message || 'Unable to delete inventory.');
          });
        });
      }
    });

    document.getElementById('inventoryForm').addEventListener('submit', function (event) {
      event.preventDefault();
      var id = document.getElementById('inventoryId').value;
      request('/inventory' + (id ? '/' + id : ''), {
        method: id ? 'PUT' : 'POST',
        body: JSON.stringify(inventoryPayload())
      }).then(function () {
        modal.hide();
        swalToast('Inventory saved');
        loadInventory();
      }).catch(function (error) {
        var box = document.getElementById('inventoryFormAlert');
        box.textContent = error.errors ? Object.values(error.errors).join(' ') : (error.message || 'Unable to save inventory.');
        box.classList.remove('d-none');
      });
    });

    document.getElementById('inventoryMovementForm').addEventListener('submit', function (event) {
      event.preventDefault();
      var id = document.getElementById('movementInventoryId').value;
      request('/inventory/' + id + '/movement', {
        method: 'POST',
        body: JSON.stringify({
          movement_type: document.getElementById('movementType').value,
          quantity: Number(document.getElementById('movementQuantity').value || 0),
          reason: document.getElementById('movementReason').value.trim()
        })
      }).then(function () {
        movementModal.hide();
        swalToast('Movement saved');
        loadInventory();
      }).catch(function (error) {
        var box = document.getElementById('inventoryMovementAlert');
        box.textContent = error.message || 'Unable to save movement.';
        box.classList.remove('d-none');
      });
    });

    loadInventory();
  }

  function initDiscounts() {
    var body = document.getElementById('discountTableBody');
    if (!body) return;

    var page = window.DISCOUNTS_PAGE || {};
    var restaurantId = Number(page.restaurant_id || activeRestaurantId || 0);
    var canCreateDiscounts = !!page.can_create;
    var canUpdateDiscounts = !!page.can_update;
    var canDeleteDiscounts = !!page.can_delete;
    var discounts = [];
    var foods = [];
    var categories = [];
    var addons = [];
    var form = document.getElementById('discountForm');
    var modalEl = document.getElementById('discountModal');
    var discountModal = modalEl ? new bootstrap.Modal(modalEl) : null;
    var search = document.getElementById('discountSearch');

    function targetTypeLabel(type) {
      return {
        food: 'Food',
        category: 'Category',
        addon: 'Food Addon',
        full_menu_with_addons: 'Full menu (Addons included)',
        full_menu_without_addons: 'Full menu (Without Addons)'
      }[type] || type;
    }

    function targetList(type) {
      if (type === 'food') return foods;
      if (type === 'category') return categories;
      if (type === 'addon') return addons;
      return [];
    }

    function targetName(item, type) {
      if (!item) return '';
      if (type === 'category') return item.name_en || item.name_ar || ('Category #' + item.id);
      if (type === 'addon') return item.name_en || item.name_ar || ('Addon #' + item.id);
      return item.name_en || item.name_ar || ('Food #' + item.id);
    }

    function renderTargetOptions(selectedId) {
      var type = document.getElementById('discountTargetType').value;
      var group = document.getElementById('discountTargetGroup');
      var select = document.getElementById('discountTargetId');
      var rows = targetList(type);
      var needsTarget = rows.length || ['food', 'category', 'addon'].indexOf(type) !== -1;

      if (group) group.classList.toggle('d-none', !needsTarget);
      if (!select) return;

      select.required = needsTarget;
      select.innerHTML = '<option value="">Select target</option>' + rows.map(function (item) {
        return '<option value="' + escapeHtml(item.id) + '"' + (String(selectedId || '') === String(item.id) ? ' selected' : '') + '>' +
          escapeHtml(targetName(item, type)) +
        '</option>';
      }).join('');
    }

    function valueLabel(discount) {
      if (discount.discount_type === 'percentage') {
        return Number(discount.discount_value || 0).toFixed(3).replace(/\.?0+$/, '') + '%';
      }

      return money(discount.discount_value);
    }

    function rowText(discount) {
      return [
        discount.name,
        discount.discount_type,
        discount.discount_value,
        targetTypeLabel(discount.target_type),
        discount.target_label
      ].join(' ').toLowerCase();
    }

    function renderDiscounts(rows) {
      body.innerHTML = rows.map(function (discount) {
        var actions = '';
        if (canUpdateDiscounts) {
          actions += '<button class="btn btn-sm btn-outline-primary discount-edit" type="button" data-id="' + escapeHtml(discount.id) + '"><i class="bi bi-pencil"></i></button> ';
        }
        if (canDeleteDiscounts) {
          actions += '<button class="btn btn-sm btn-outline-danger discount-delete" type="button" data-id="' + escapeHtml(discount.id) + '"><i class="bi bi-trash"></i></button>';
        }

        return '<tr>' +
          '<td><div class="fw-semibold">' + escapeHtml(discount.name || '-') + '</div><small class="text-secondary">' + escapeHtml(discount.discount_type === 'fixed' ? 'Fixed amount' : 'Percentage') + '</small></td>' +
          '<td>' + escapeHtml(valueLabel(discount)) + '</td>' +
          '<td><div>' + escapeHtml(targetTypeLabel(discount.target_type)) + '</div><small class="text-secondary">' + escapeHtml(discount.target_label || '-') + '</small></td>' +
          '<td><span class="badge text-bg-' + (Number(discount.is_active || 0) === 1 ? 'success' : 'secondary') + '">' + (Number(discount.is_active || 0) === 1 ? 'Active' : 'Inactive') + '</span></td>' +
          '<td class="text-end">' + (actions || '<span class="text-secondary">-</span>') + '</td>' +
        '</tr>';
      }).join('') || '<tr><td colspan="5" class="text-center text-secondary py-4">No discounts found.</td></tr>';
    }

    function filterAndRender() {
      var query = text(search ? search.value : '').trim().toLowerCase();
      renderDiscounts(query ? discounts.filter(function (discount) {
        return rowText(discount).indexOf(query) !== -1;
      }) : discounts);
    }

    function discountPayload() {
      var targetType = document.getElementById('discountTargetType').value;
      return {
        name: document.getElementById('discountName').value.trim(),
        discount_type: document.getElementById('discountType').value,
        discount_value: Number(document.getElementById('discountValue').value || 0),
        target_type: targetType,
        target_id: ['food', 'category', 'addon'].indexOf(targetType) !== -1 ? Number(document.getElementById('discountTargetId').value || 0) : null,
        is_active: document.getElementById('discountActive').checked ? 1 : 0,
        restaurant_id: restaurantId
      };
    }

    function showDiscountError(error) {
      var box = document.getElementById('discountFormAlert');
      if (!box) return;
      box.textContent = error.errors ? Object.values(error.errors).join(' ') : (error.message || 'Unable to save discount.');
      box.classList.remove('d-none');
    }

    function fillDiscount(discount) {
      if (!form) return;
      form.reset();
      document.getElementById('discountId').value = discount ? discount.id : '';
      document.getElementById('discountName').value = discount ? text(discount.name) : '';
      document.getElementById('discountType').value = discount ? text(discount.discount_type, 'percentage') : 'percentage';
      document.getElementById('discountValue').value = discount ? text(discount.discount_value) : '';
      document.getElementById('discountTargetType').value = discount ? text(discount.target_type, 'food') : 'food';
      document.getElementById('discountActive').checked = discount ? Number(discount.is_active || 0) === 1 : true;
      document.getElementById('discountModalTitle').textContent = discount ? 'Edit Discount' : 'Add Discount';
      document.getElementById('discountFormAlert').classList.add('d-none');
      renderTargetOptions(discount ? discount.target_id : '');
    }

    function loadDiscounts() {
      Promise.all([
        request('/discounts'),
        request('/menu-foods'),
        request('/menu-categories'),
        request('/food-addons')
      ]).then(function (results) {
        discounts = results[0].data || [];
        foods = results[1].data || [];
        categories = results[2].data || [];
        addons = results[3].data || [];
        filterAndRender();
        if (form) renderTargetOptions('');
      }).catch(function (error) {
        body.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">' + escapeHtml(error.message || 'Unable to load discounts.') + '</td></tr>';
      });
    }

    if (search) {
      search.addEventListener('input', filterAndRender);
    }

    var addBtn = document.getElementById('discountAddBtn');
    if (addBtn) {
      addBtn.addEventListener('click', function () {
        fillDiscount(null);
      });
    }

    if (form) {
      document.getElementById('discountTargetType').addEventListener('change', function () {
        renderTargetOptions('');
      });

      form.addEventListener('submit', function (event) {
        event.preventDefault();
        document.getElementById('discountFormAlert').classList.add('d-none');

        var id = document.getElementById('discountId').value;
        request('/discounts' + (id ? '/' + id : ''), {
          method: id ? 'PUT' : 'POST',
          body: JSON.stringify(discountPayload())
        }).then(function () {
          fillDiscount(null);
          if (discountModal) discountModal.hide();
          loadDiscounts();
          swalToast(id ? 'Discount updated' : 'Discount created');
        }).catch(showDiscountError);
      });
    }

    body.addEventListener('click', function (event) {
      var edit = event.target.closest('.discount-edit');
      var del = event.target.closest('.discount-delete');

      if (edit && canUpdateDiscounts) {
        var discount = discounts.find(function (row) { return String(row.id) === String(edit.dataset.id); });
        if (!discount) return;
        fillDiscount(discount);
        if (discountModal) discountModal.show();
      }

      if (del && canDeleteDiscounts) {
        swalConfirm('Delete this discount?', 'Delete discount').then(function (confirmed) {
          if (!confirmed) return;
          request('/discounts/' + del.dataset.id, { method: 'DELETE' }).then(function () {
            swalToast('Discount deleted');
            loadDiscounts();
          }).catch(function (error) {
            swalToast(error.message || 'Unable to delete discount', 'error');
          });
        });
      }
    });

    if (canCreateDiscounts || canUpdateDiscounts) {
      fillDiscount(null);
    }
    loadDiscounts();
  }

  function initMenuPage() {
    var categoriesBody = document.getElementById('menuCategoriesBody');
    var foodsBody = document.getElementById('menuFoodsBody');
    var addonsBody = document.getElementById('menuAddonsBody');
    if (!categoriesBody || !foodsBody) return;

    var page = window.MENU_PAGE || {};
    var restaurantId = Number(page.restaurant_id || activeRestaurantId || 0);
    var canCreateCategories = !!page.can_create_categories;
    var canUpdateCategories = !!page.can_update_categories;
    var canDeleteCategories = !!page.can_delete_categories;
    var canCreateFoods = !!page.can_create_foods;
    var canUpdateFoods = !!page.can_update_foods;
    var canDeleteFoods = !!page.can_delete_foods;
    var categories = [];
    var foods = [];
    var addons = [];
    var categoryForm = document.getElementById('categoryForm');
    var foodForm = document.getElementById('foodForm');
    var addonForm = document.getElementById('addonForm');
    var categoryModalEl = document.getElementById('categoryModal');
    var foodModalEl = document.getElementById('foodModal');
    var categoryTableModalEl = document.getElementById('categoryTableModal');
    var foodTableModalEl = document.getElementById('foodTableModal');
    var categoryModal = categoryModalEl ? new bootstrap.Modal(categoryModalEl) : null;
    var foodModal = foodModalEl ? new bootstrap.Modal(foodModalEl) : null;
    var addonModalEl = document.getElementById('addonModal');
    var addonModal = addonModalEl ? new bootstrap.Modal(addonModalEl) : null;
    var foodImageFile = document.getElementById('foodImageFile');
    var foodImagePreview = document.getElementById('foodImagePreview');
    var categoriesFullBody = document.getElementById('menuCategoriesFullBody');
    var foodsFullBody = document.getElementById('menuFoodsFullBody');
    var categoryTableSearch = document.getElementById('categoryTableSearch');
    var foodTableSearch = document.getElementById('foodTableSearch');
    var categoryTableShowing = document.getElementById('categoryTableShowing');
    var foodTableShowing = document.getElementById('foodTableShowing');
    var categoryTablePage = document.getElementById('categoryTablePage');
    var foodTablePage = document.getElementById('foodTablePage');
    var categoryPage = 1;
    var foodPage = 1;
    var tablePageSize = 8;

    function showFormError(id, error, fallback) {
      var box = document.getElementById(id);
      if (!box) return;
      box.textContent = error.errors ? Object.values(error.errors).join(' ') : (error.message || fallback);
      box.classList.remove('d-none');
    }

    function hideFormError(id) {
      var box = document.getElementById(id);
      if (box) box.classList.add('d-none');
    }

    function hideOpenModal(modalEl) {
      if (!modalEl || !modalEl.classList.contains('show')) return;
      var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
      modal.hide();
    }

    function categoryPayload() {
      return {
        name_en: document.getElementById('categoryNameEn').value.trim(),
        name_ar: document.getElementById('categoryNameAr').value.trim(),
        description_en: document.getElementById('categoryDescriptionEn').value.trim(),
        description_ar: document.getElementById('categoryDescriptionAr').value.trim(),
        restaurant_id: restaurantId
      };
    }

    function foodPayload() {
      return {
        name_en: document.getElementById('foodNameEn').value.trim(),
        name_ar: document.getElementById('foodNameAr').value.trim(),
        description_en: document.getElementById('foodDescriptionEn').value.trim(),
        description_ar: document.getElementById('foodDescriptionAr').value.trim(),
        image_url: document.getElementById('foodImageUrl').value.trim(),
        price: Number(document.getElementById('foodPrice').value || 0),
        profit: Number(document.getElementById('foodProfit').value || 0),
        category_id: Number(document.getElementById('foodCategoryId').value || 0),
        restaurant_id: restaurantId,
        tax_category: document.getElementById('foodTaxCategory').value,
        tax_rate: document.getElementById('foodTaxRate').value.trim(),
        special_tax_amount: Number(document.getElementById('foodSpecialTax').value || 0),
        tax_exempt: document.getElementById('foodTaxExempt').checked ? 1 : 0,
        note_enabled: document.getElementById('foodNoteEnabled').checked ? 1 : 0
      };
    }

    function addonPayload() {
      var scope = document.getElementById('addonScope').value;
      return {
        name_en: document.getElementById('addonNameEn').value.trim(),
        name_ar: document.getElementById('addonNameAr').value.trim(),
        food_id: scope === 'food' ? Number(document.getElementById('addonFoodId').value || 0) : null,
        category_id: scope === 'category' ? Number(document.getElementById('addonCategoryId').value || 0) : null,
        extra_price: Number(document.getElementById('addonExtraPrice').value || 0),
        extra_profit: Number(document.getElementById('addonExtraProfit').value || 0),
        restaurant_id: restaurantId
      };
    }

    function fillCategory(category) {
      if (!categoryForm) return;
      categoryForm.reset();
      document.getElementById('categoryId').value = category ? category.id : '';
      document.getElementById('categoryNameEn').value = category ? text(category.name_en) : '';
      document.getElementById('categoryNameAr').value = category ? text(category.name_ar) : '';
      document.getElementById('categoryDescriptionEn').value = category ? text(category.description_en) : '';
      document.getElementById('categoryDescriptionAr').value = category ? text(category.description_ar) : '';
      document.getElementById('categoryModalTitle').textContent = category ? 'Edit Category' : 'Add Category';
      hideFormError('categoryFormAlert');
    }

    function fillFood(food) {
      if (!foodForm) return;
      foodForm.reset();
      document.getElementById('foodId').value = food ? food.id : '';
      document.getElementById('foodNameEn').value = food ? text(food.name_en) : '';
      document.getElementById('foodNameAr').value = food ? text(food.name_ar) : '';
      document.getElementById('foodDescriptionEn').value = food ? text(food.description_en) : '';
      document.getElementById('foodDescriptionAr').value = food ? text(food.description_ar) : '';
      document.getElementById('foodImageUrl').value = food ? text(food.image_url) : '';
      if (foodImageFile) foodImageFile.value = '';
      if (foodImageFile) foodImageFile.required = !food;
      setImagePreview(foodImagePreview, food ? text(food.image_url) : '', 'bi bi-image');
      document.getElementById('foodPrice').value = food ? text(food.original_price || food.price) : '';
      document.getElementById('foodProfit').value = food ? text(food.profit, '0') : '0';
      document.getElementById('foodCategoryId').value = food ? text(food.category_id) : '';
      document.getElementById('foodTaxCategory').value = food ? text(food.tax_category, 'default') : 'default';
      document.getElementById('foodTaxRate').value = food && food.tax_rate !== null && food.tax_rate !== undefined ? text(food.tax_rate) : '';
      document.getElementById('foodSpecialTax').value = food ? text(food.special_tax_amount, '0') : '0';
      document.getElementById('foodTaxExempt').checked = Number(food ? food.tax_exempt : 0) === 1;
      document.getElementById('foodNoteEnabled').checked = Number(food ? food.note_enabled : 0) === 1;
      document.getElementById('foodModalTitle').textContent = food ? 'Edit Food' : 'Add Food';
      hideFormError('foodFormAlert');
    }

    function setAddonScope(scope) {
      var isCategory = scope === 'category';
      var foodGroup = document.getElementById('addonFoodGroup');
      var categoryGroup = document.getElementById('addonCategoryGroup');
      var foodSelect = document.getElementById('addonFoodId');
      var categorySelect = document.getElementById('addonCategoryId');
      document.getElementById('addonScope').value = isCategory ? 'category' : 'food';
      if (foodGroup) foodGroup.classList.toggle('d-none', isCategory);
      if (categoryGroup) categoryGroup.classList.toggle('d-none', !isCategory);
      if (foodSelect) foodSelect.required = !isCategory;
      if (categorySelect) categorySelect.required = isCategory;
    }

    function fillAddon(addon) {
      if (!addonForm) return;
      addonForm.reset();
      renderAddonTargetOptions();
      document.getElementById('addonId').value = addon ? addon.id : '';
      document.getElementById('addonNameEn').value = addon ? text(addon.name_en) : '';
      document.getElementById('addonNameAr').value = addon ? text(addon.name_ar) : '';
      document.getElementById('addonExtraPrice').value = addon ? text(addon.original_extra_price || addon.extra_price, '0') : '0';
      document.getElementById('addonExtraProfit').value = addon ? text(addon.extra_profit, '0') : '0';
      setAddonScope(addon && addon.category_id ? 'category' : 'food');
      document.getElementById('addonFoodId').value = addon && addon.food_id ? text(addon.food_id) : '';
      document.getElementById('addonCategoryId').value = addon && addon.category_id ? text(addon.category_id) : '';
      document.getElementById('addonModalTitle').textContent = addon ? 'Edit Addon' : 'Add Addon';
      hideFormError('addonFormAlert');
    }

    function renderCategoryOptions() {
      var select = document.getElementById('foodCategoryId');
      if (!select) return;

      var selected = select.value;
      select.innerHTML = '<option value="">Select category</option>' + categories.map(function (category) {
        return '<option value="' + escapeHtml(category.id) + '"' + (String(selected) === String(category.id) ? ' selected' : '') + '>' +
          escapeHtml(category.name_en || category.name_ar || ('Category #' + category.id)) +
        '</option>';
      }).join('');
    }

    function renderAddonTargetOptions() {
      var foodSelect = document.getElementById('addonFoodId');
      var categorySelect = document.getElementById('addonCategoryId');

      if (foodSelect) {
        foodSelect.innerHTML = '<option value="">Select food</option>' + foods.map(function (food) {
          return '<option value="' + escapeHtml(food.id) + '">' +
            escapeHtml(food.name_en || food.name_ar || ('Food #' + food.id)) +
          '</option>';
        }).join('');
      }

      if (categorySelect) {
        categorySelect.innerHTML = '<option value="">Select category</option>' + categories.map(function (category) {
          return '<option value="' + escapeHtml(category.id) + '">' +
            escapeHtml(category.name_en || category.name_ar || ('Category #' + category.id)) +
          '</option>';
        }).join('');
      }
    }

    function categoryActions(category) {
        var actions = '';
        if (canUpdateCategories) {
          actions += '<button class="btn btn-sm btn-outline-primary menu-category-edit" type="button" data-id="' + escapeHtml(category.id) + '"><i class="bi bi-pencil"></i></button> ';
        }
        if (canDeleteCategories) {
          actions += '<button class="btn btn-sm btn-outline-danger menu-category-delete" type="button" data-id="' + escapeHtml(category.id) + '"><i class="bi bi-trash"></i></button>';
        }

        return actions || '<span class="text-secondary">-</span>';
    }

    function categoryRow(category) {
        return '<tr>' +
          '<td><div class="fw-semibold">' + escapeHtml(category.name_en || '-') + '</div><small class="text-secondary">' + escapeHtml(category.name_ar || '-') + '</small></td>' +
          '<td class="small text-secondary">' + escapeHtml(category.description_en || category.description_ar || '-') + '</td>' +
          '<td class="text-end">' + categoryActions(category) + '</td>' +
        '</tr>';
    }

    function foodActions(food) {
      var actions = '';
      if (canUpdateFoods) {
        actions += '<button class="btn btn-sm btn-outline-primary menu-food-edit" type="button" data-id="' + escapeHtml(food.id) + '"><i class="bi bi-pencil"></i></button> ';
      }
      if (canDeleteFoods) {
        actions += '<button class="btn btn-sm btn-outline-danger menu-food-delete" type="button" data-id="' + escapeHtml(food.id) + '"><i class="bi bi-trash"></i></button>';
      }

      return actions || '<span class="text-secondary">-</span>';
    }

    function foodRow(food) {
        var tax = food.tax_exempt ? 'Exempt' : (food.tax_category === 'default' ? 'Default' : food.tax_category);
        if (food.tax_rate !== null && food.tax_rate !== undefined && food.tax_rate !== '') {
          tax += ' / ' + Number(food.tax_rate).toFixed(3) + '%';
        }
        var hasDiscount = Number(food.discount_amount || 0) > 0 && Number(food.discounted_price || food.price) < Number(food.original_price || food.price);
        var priceHtml = hasDiscount
          ? '<div class="fw-semibold text-success">' + money(food.discounted_price) + '</div><small class="text-secondary text-decoration-line-through">' + money(food.original_price || food.price) + '</small>'
          : money(food.price);

        return '<tr>' +
          '<td><div class="d-flex align-items-center gap-2"><span class="menu-food-thumb">' + (food.image_url ? '<img src="' + escapeHtml(food.image_url) + '" alt="">' : '<i class="bi bi-image"></i>') + '</span><span><div class="fw-semibold">' + escapeHtml(food.name_en || '-') + '</div><small class="text-secondary">' + escapeHtml(food.name_ar || '-') + '</small></span></div></td>' +
          '<td>' + escapeHtml(food.category_name_en || food.category_name_ar || '-') + '</td>' +
          '<td>' + priceHtml + '</td>' +
          '<td>' + escapeHtml(tax) + '</td>' +
          '<td class="text-end">' + foodActions(food) + '</td>' +
        '</tr>';
    }

    function addonActions(addon) {
      var actions = '';
      if (canUpdateFoods) {
        actions += '<button class="btn btn-sm btn-outline-primary menu-addon-edit" type="button" data-id="' + escapeHtml(addon.id) + '"><i class="bi bi-pencil"></i></button> ';
      }
      if (canDeleteFoods) {
        actions += '<button class="btn btn-sm btn-outline-danger menu-addon-delete" type="button" data-id="' + escapeHtml(addon.id) + '"><i class="bi bi-trash"></i></button>';
      }

      return actions || '<span class="text-secondary">-</span>';
    }

    function addonScopeLabel(addon) {
      if (addon.category_id) {
        return '<span class="badge text-bg-info">Category default</span> ' +
          escapeHtml(addon.category_name_en || addon.category_name_ar || ('Category #' + addon.category_id));
      }

      return '<span class="badge text-bg-secondary">Single food</span> ' +
        escapeHtml(addon.food_name_en || addon.food_name_ar || ('Food #' + addon.food_id));
    }

    function addonRow(addon) {
      var hasDiscount = Number(addon.discount_amount || 0) > 0 && Number(addon.discounted_extra_price || addon.extra_price) < Number(addon.original_extra_price || addon.extra_price);
      var priceHtml = hasDiscount
        ? '<div class="fw-semibold text-success">' + money(addon.discounted_extra_price) + '</div><small class="text-secondary text-decoration-line-through">' + money(addon.original_extra_price || addon.extra_price) + '</small>'
        : money(addon.extra_price);

      return '<tr>' +
        '<td><div class="fw-semibold">' + escapeHtml(addon.name_en || '-') + '</div><small class="text-secondary">' + escapeHtml(addon.name_ar || '-') + '</small></td>' +
        '<td>' + addonScopeLabel(addon) + '</td>' +
        '<td>' + priceHtml + '</td>' +
        '<td>' + money(addon.extra_profit) + '</td>' +
        '<td class="text-end">' + addonActions(addon) + '</td>' +
      '</tr>';
    }

    function filteredList(rows, searchInput, fields) {
      var query = text(searchInput ? searchInput.value : '').trim().toLowerCase();
      if (!query) return rows.slice();

      return rows.filter(function (row) {
        return fields.some(function (field) {
          return text(row[field]).toLowerCase().indexOf(query) !== -1;
        });
      });
    }

    function renderPaginatedTable(rows, page, body, showing, pageLabel, prevBtn, nextBtn, rowRenderer, emptyColspan, emptyText) {
      var totalPages = Math.max(1, Math.ceil(rows.length / tablePageSize));
      page = Math.min(Math.max(1, page), totalPages);
      var start = (page - 1) * tablePageSize;
      var visible = rows.slice(start, start + tablePageSize);

      body.innerHTML = visible.map(rowRenderer).join('') || '<tr><td colspan="' + emptyColspan + '" class="text-center text-secondary py-4">' + emptyText + '</td></tr>';
      if (showing) showing.textContent = rows.length ? 'Showing ' + (start + 1) + '-' + Math.min(start + tablePageSize, rows.length) + ' of ' + rows.length : 'No records';
      if (pageLabel) pageLabel.textContent = 'Page ' + page + ' of ' + totalPages;
      if (prevBtn) prevBtn.disabled = page <= 1;
      if (nextBtn) nextBtn.disabled = page >= totalPages;

      return page;
    }

    function renderCategoryTable() {
      if (!categoriesFullBody) return;
      categoryPage = renderPaginatedTable(
        filteredList(categories, categoryTableSearch, ['name_en', 'name_ar', 'description_en', 'description_ar']),
        categoryPage,
        categoriesFullBody,
        categoryTableShowing,
        categoryTablePage,
        document.getElementById('categoryTablePrev'),
        document.getElementById('categoryTableNext'),
        categoryRow,
        3,
        'No categories found.'
      );
    }

    function renderFoodTable() {
      if (!foodsFullBody) return;
      foodPage = renderPaginatedTable(
        filteredList(foods, foodTableSearch, ['name_en', 'name_ar', 'description_en', 'description_ar', 'category_name_en', 'category_name_ar']),
        foodPage,
        foodsFullBody,
        foodTableShowing,
        foodTablePage,
        document.getElementById('foodTablePrev'),
        document.getElementById('foodTableNext'),
        foodRow,
        5,
        'No foods found.'
      );
    }

    function renderMenu() {
      categoriesBody.innerHTML = categories.slice(0, 5).map(categoryRow).join('') || '<tr><td colspan="3" class="text-center text-secondary py-4">No categories yet.</td></tr>';
      foodsBody.innerHTML = foods.slice(0, 5).map(foodRow).join('') || '<tr><td colspan="5" class="text-center text-secondary py-4">No foods yet.</td></tr>';
      if (addonsBody) {
        addonsBody.innerHTML = addons.slice(0, 5).map(addonRow).join('') || '<tr><td colspan="5" class="text-center text-secondary py-4">No addons yet.</td></tr>';
      }

      renderCategoryOptions();
      renderAddonTargetOptions();
      renderCategoryTable();
      renderFoodTable();
    }

    function loadMenu() {
      Promise.all([
        request('/menu-categories'),
        request('/menu-foods'),
        request('/food-addons')
      ]).then(function (results) {
        categories = results[0].data || [];
        foods = results[1].data || [];
        addons = results[2].data || [];
        categoryPage = 1;
        foodPage = 1;
        renderMenu();
      }).catch(function (error) {
        categoriesBody.innerHTML = '<tr><td colspan="3" class="text-center text-danger py-4">' + escapeHtml(error.message || 'Unable to load categories.') + '</td></tr>';
        foodsBody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">' + escapeHtml(error.message || 'Unable to load foods.') + '</td></tr>';
        if (addonsBody) {
          addonsBody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">' + escapeHtml(error.message || 'Unable to load addons.') + '</td></tr>';
        }
      });
    }

    var categoryAddBtn = document.getElementById('categoryAddBtn');
    if (categoryAddBtn) {
      categoryAddBtn.addEventListener('click', function () {
        fillCategory(null);
      });
    }

    function handleCategoryTableClick(event) {
      var edit = event.target.closest('.menu-category-edit');
      var del = event.target.closest('.menu-category-delete');

      if (edit) {
        var category = categories.find(function (row) { return String(row.id) === String(edit.dataset.id); });
        if (!category || !categoryForm) return;
        fillCategory(category);
        hideOpenModal(categoryTableModalEl);
        if (categoryModal) categoryModal.show();
      }

      if (del) {
        swalConfirm('Delete this category?', 'Delete category').then(function (confirmed) {
          if (!confirmed) return;
          request('/menu-categories/' + del.dataset.id, { method: 'DELETE' }).then(function () {
            swalToast('Category deleted');
            loadMenu();
          }).catch(function (error) {
            swalToast(error.message || 'Unable to delete category', 'error');
          });
        });
      }
    }

    categoriesBody.addEventListener('click', handleCategoryTableClick);
    if (categoriesFullBody) categoriesFullBody.addEventListener('click', handleCategoryTableClick);

    if (categoryForm) {
      categoryForm.addEventListener('submit', function (event) {
        event.preventDefault();
        hideFormError('categoryFormAlert');
        var id = document.getElementById('categoryId').value;
        request('/menu-categories' + (id ? '/' + id : ''), {
          method: id ? 'PUT' : 'POST',
          body: JSON.stringify(categoryPayload())
        }).then(function () {
          fillCategory(null);
          if (categoryModal) categoryModal.hide();
          swalToast(id ? 'Category updated' : 'Category added');
          loadMenu();
        }).catch(function (error) {
          showFormError('categoryFormAlert', error, 'Unable to save category.');
        });
      });
    }

    var foodAddBtn = document.getElementById('foodAddBtn');
    if (foodAddBtn) {
      foodAddBtn.addEventListener('click', function () {
        renderCategoryOptions();
        fillFood(null);
      });
    }

    function handleFoodTableClick(event) {
      var edit = event.target.closest('.menu-food-edit');
      var del = event.target.closest('.menu-food-delete');

      if (edit) {
        var food = foods.find(function (row) { return String(row.id) === String(edit.dataset.id); });
        if (!food || !foodForm) return;
        renderCategoryOptions();
        fillFood(food);
        hideOpenModal(foodTableModalEl);
        if (foodModal) foodModal.show();
      }

      if (del) {
        swalConfirm('Delete this food?', 'Delete food').then(function (confirmed) {
          if (!confirmed) return;
          request('/menu-foods/' + del.dataset.id, { method: 'DELETE' }).then(function () {
            swalToast('Food deleted');
            loadMenu();
          }).catch(function (error) {
            swalToast(error.message || 'Unable to delete food', 'error');
          });
        });
      }
    }

    foodsBody.addEventListener('click', handleFoodTableClick);
    if (foodsFullBody) foodsFullBody.addEventListener('click', handleFoodTableClick);

    var addonAddBtn = document.getElementById('addonAddBtn');
    if (addonAddBtn) {
      addonAddBtn.addEventListener('click', function () {
        fillAddon(null);
      });
    }

    var addonScope = document.getElementById('addonScope');
    if (addonScope) {
      addonScope.addEventListener('change', function () {
        setAddonScope(addonScope.value);
      });
    }

    function handleAddonTableClick(event) {
      var edit = event.target.closest('.menu-addon-edit');
      var del = event.target.closest('.menu-addon-delete');

      if (edit) {
        var addon = addons.find(function (row) { return String(row.id) === String(edit.dataset.id); });
        if (!addon || !addonForm) return;
        fillAddon(addon);
        if (addonModal) addonModal.show();
      }

      if (del) {
        swalConfirm('Delete this addon?', 'Delete addon').then(function (confirmed) {
          if (!confirmed) return;
          request('/food-addons/' + del.dataset.id, { method: 'DELETE' }).then(function () {
            swalToast('Addon deleted');
            loadMenu();
          }).catch(function (error) {
            swalToast(error.message || 'Unable to delete addon', 'error');
          });
        });
      }
    }

    if (addonsBody) addonsBody.addEventListener('click', handleAddonTableClick);

    if (addonForm) {
      addonForm.addEventListener('submit', function (event) {
        event.preventDefault();
        hideFormError('addonFormAlert');
        var id = document.getElementById('addonId').value;
        request('/food-addons' + (id ? '/' + id : ''), {
          method: id ? 'PUT' : 'POST',
          body: JSON.stringify(addonPayload())
        }).then(function () {
          fillAddon(null);
          if (addonModal) addonModal.hide();
          swalToast(id ? 'Addon updated' : 'Addon added');
          loadMenu();
        }).catch(function (error) {
          showFormError('addonFormAlert', error, 'Unable to save addon.');
        });
      });
    }

    if (categoryTableSearch) {
      categoryTableSearch.addEventListener('input', function () {
        categoryPage = 1;
        renderCategoryTable();
      });
    }

    if (foodTableSearch) {
      foodTableSearch.addEventListener('input', function () {
        foodPage = 1;
        renderFoodTable();
      });
    }

    var categoryTablePrev = document.getElementById('categoryTablePrev');
    var categoryTableNext = document.getElementById('categoryTableNext');
    var foodTablePrev = document.getElementById('foodTablePrev');
    var foodTableNext = document.getElementById('foodTableNext');

    if (categoryTablePrev) categoryTablePrev.addEventListener('click', function () { categoryPage -= 1; renderCategoryTable(); });
    if (categoryTableNext) categoryTableNext.addEventListener('click', function () { categoryPage += 1; renderCategoryTable(); });
    if (foodTablePrev) foodTablePrev.addEventListener('click', function () { foodPage -= 1; renderFoodTable(); });
    if (foodTableNext) foodTableNext.addEventListener('click', function () { foodPage += 1; renderFoodTable(); });

    if (foodForm) {
      if (foodImageFile) {
        foodImageFile.addEventListener('change', function () {
          var file = foodImageFile.files && foodImageFile.files[0];
          setImagePreview(foodImagePreview, file ? URL.createObjectURL(file) : document.getElementById('foodImageUrl').value, 'bi bi-image');
        });
      }

      foodForm.addEventListener('submit', function (event) {
        event.preventDefault();
        hideFormError('foodFormAlert');
        var id = document.getElementById('foodId').value;
        var payload = foodPayload();
        var file = foodImageFile && foodImageFile.files ? foodImageFile.files[0] : null;

        uploadImage(file, 'foods').then(function (path) {
          if (path) payload.image_url = path;

          return request('/menu-foods' + (id ? '/' + id : ''), {
            method: id ? 'PUT' : 'POST',
            body: JSON.stringify(payload)
          });
        }).then(function () {
          fillFood(null);
          if (foodModal) foodModal.hide();
          swalToast(id ? 'Food updated' : 'Food added');
          loadMenu();
        }).catch(function (error) {
          showFormError('foodFormAlert', error, 'Unable to save food.');
        });
      });
    }

    loadMenu();
  }

  function initActivityLog() {
    var stream = document.getElementById('logStream');
    var messages = document.getElementById('logMessages');
    if (!stream || !messages) return;

    var page = window.LOG_PAGE || {};
    var permissionFilter = document.getElementById('logPermissionFilter');
    var permissionDropdown = document.getElementById('logPermissionDropdown');
    var staffFilter = document.getElementById('logStaffFilter');
    var staffDropdown = document.getElementById('logStaffDropdown');
    var rangeFilter = document.getElementById('logRangeFilter');
    var loadMore = document.getElementById('logLoadMore');
    var modalEl = document.getElementById('logInfoModal');
    var modal = modalEl && typeof bootstrap !== 'undefined' ? new bootstrap.Modal(modalEl) : null;
    var modalTitle = document.getElementById('logInfoTitle');
    var modalSubtitle = document.getElementById('logInfoSubtitle');
    var modalBody = document.getElementById('logInfoBody');
    var logsById = {};
    var newestId = 0;
    var oldestId = 0;
    var loading = false;

    function selectedValues(container, selector) {
      return Array.from(container ? container.querySelectorAll(selector + ':checked') : []).map(function (input) {
        return input.value;
      }).filter(Boolean);
    }

    function updateDropdownLabel(container, button, selector, emptyLabel, selectedLabel) {
      if (!container || !button) return;
      var selected = Array.from(container.querySelectorAll(selector + ':checked'));
      if (!selected.length) {
        button.textContent = emptyLabel;
      } else if (selected.length === 1) {
        button.textContent = selected[0].dataset.label || selectedLabel.replace('%d', '1');
      } else {
        button.textContent = selectedLabel.replace('%d', selected.length);
      }
    }

    function buildLogPath(extra) {
      var params = new URLSearchParams();
      params.set('limit', '25');
      params.set('range', rangeFilter ? rangeFilter.value : '24h');
      if (page.restaurant_id) params.set('restaurant_id', page.restaurant_id);

      var permissions = selectedValues(permissionFilter, '.log-permission-check');
      if (!permissions.length && Array.isArray(page.default_permissions) && page.default_permissions.length) {
        permissions = page.default_permissions;
      }
      var staffIds = selectedValues(staffFilter, '.log-staff-check');
      if (permissions.length) params.set('permissions', permissions.join(','));
      if (staffIds.length) params.set('employee_ids', staffIds.join(','));
      Object.keys(extra || {}).forEach(function (key) {
        if (extra[key]) params.set(key, extra[key]);
      });

      return '/logs?' + params.toString();
    }

    function logClass(log) {
      if (log.permission_key === 'restaurant.update' || log.permission_key.indexOf('branches.') === 0 || log.permission_key.indexOf('employees.') === 0) return 'log-message-critical';
      if (log.permission_key.indexOf('.delete') !== -1) return 'log-message-delete';
      if (log.permission_key.indexOf('.create') !== -1) return 'log-message-create';
      if (log.permission_key === 'orders.update') return 'log-message-normal';
      return '';
    }

    function actionVerb(permission) {
      var action = text(permission).split('.').pop();
      if (action === 'create') return 'ADD';
      if (action === 'delete') return 'DELETE';
      if (action === 'update') return 'UPDATE';
      if (action === 'get') return 'VIEW';
      return action.toUpperCase();
    }

    function actionLabel(permission) {
      var action = actionVerb(permission);
      if (action === 'ADD') return 'Added';
      if (action === 'DELETE') return 'Deleted';
      if (action === 'UPDATE') return 'Updated';
      if (action === 'VIEW') return 'Viewed';
      return action.charAt(0) + action.slice(1).toLowerCase();
    }

    function permissionTitle(permission) {
      var group = text(permission).split('.')[0] || 'system';
      var labels = {
        auth: 'Login Records',
        restaurants: 'Restaurant Management',
        restaurant: 'Restaurant Settings',
        branches: 'Branches',
        branches_logs: 'Branch Manager Logs',
        employees: 'Staff',
        inventory: 'Inventory',
        orders: 'Orders',
        foods: 'Food Menu',
        categories: 'Categories',
        tables: 'Tables',
        logs: 'Activity Logs'
      };
      if (labels[group]) return labels[group];

      return group.replace(/_/g, ' ').replace(/s$/, '').replace(/\b\w/g, function (letter) {
        return letter.toUpperCase();
      });
    }

    function entityTitle(type) {
      return ({
        food: 'Food',
        category: 'Category',
        food_addon: 'Food Addon',
        employee: 'Staff Member',
        table: 'Table',
        order: 'Order',
        order_food: 'Order Food',
        inventory: 'Inventory Item',
        restaurant: 'Restaurant'
      })[type] || text(type, 'Record').replace(/_/g, ' ');
    }

    function fieldLabel(key) {
      var labels = {
        id: 'ID',
        order_id: 'Order ID',
        food_id: 'Food ID',
        addon_id: 'Addon ID',
        table_id: 'Table ID',
        restaurant_id: 'Restaurant ID',
        branch_id: 'Branch ID',
        name: 'Name',
        name_en: 'English name',
        name_ar: 'Arabic name',
        username: 'Username',
        role: 'Role',
        pfp: 'Profile image',
        description: 'Description',
        description_en: 'English description',
        description_ar: 'Arabic description',
        image_url: 'Image',
        price: 'Price',
        profit: 'Profit',
        extra_price: 'Extra price',
        extra_profit: 'Extra profit',
        category_id: 'Category',
        category_name_en: 'Category',
        category_name_ar: 'Arabic category',
        tax_category: 'Tax category',
        tax_rate: 'Tax rate',
        special_tax_amount: 'Special tax amount',
        tax_exempt: 'Tax exempt',
        table_number: 'Table number',
        table_status: 'Table status',
        table_floor: 'Floor',
        position: 'Position',
        status: 'Status',
        payment_status: 'Payment status',
        payment_method: 'Payment method',
        total_paid_cash: 'Paid cash',
        total_paid_credit: 'Paid credit',
        session_order_key: 'Order session key',
        quantity: 'Quantity',
        unit: 'Unit',
        quantity_per_item: 'Stock effect per item',
        quantity_change: 'Stock change',
        movement_type: 'Movement type',
        reason: 'Reason',
        main_code: 'Restaurant code',
        manager_number: 'Manager phone',
        location: 'Location',
        txt_details: 'Details',
        active_until: 'Active until',
        brand_name_en: 'English brand name',
        brand_name_ar: 'Arabic brand name',
        hero_title_en: 'English hero title',
        hero_title_ar: 'Arabic hero title',
        hero_accent_en: 'English hero accent',
        hero_accent_ar: 'Arabic hero accent',
        hero_description_en: 'English hero description',
        hero_description_ar: 'Arabic hero description',
        hero_eyebrow_en: 'English hero eyebrow',
        hero_eyebrow_ar: 'Arabic hero eyebrow',
        menu_title_en: 'English menu title',
        menu_title_ar: 'Arabic menu title',
        menu_subtitle_en: 'English menu subtitle',
        menu_subtitle_ar: 'Arabic menu subtitle',
        logo_image_url: 'Logo image',
        primary_color: 'Primary color',
        accent_color: 'Accent color',
        background_color: 'Background color',
        text_color: 'Text color',
        permissions: 'Permissions'
      };

      return labels[key] || text(key).replace(/_/g, ' ').replace(/\b\w/g, function (letter) {
        return letter.toUpperCase();
      });
    }

    function readableValue(key, value) {
      if (value === null || value === undefined || value === '') return 'Not set';
      var normalized = String(value);
      var booleanFields = {
        tax_exempt: true,
        einvoicing_enabled: true,
        prices_include_tax: true,
        automatic_submission: true,
        print_after_accepted: true,
        invoice_print_full_page: true
      };
      var map = {
        free: 'Free',
        waiting_order: 'Waiting order',
        order_done: 'Order done',
        waiting: 'Waiting',
        finished: 'Finished',
        canceled: 'Canceled',
        unpaid: 'Unpaid',
        paid: 'Paid',
        cash: 'Cash',
        credit: 'Credit card',
        cash_credit: 'Cash and credit card',
        pcs: 'Pieces',
        kgs: 'Kilograms',
        liters: 'Liters',
        purchase: 'Added stock',
        consume: 'Used by order',
        return: 'Returned to stock',
        waste: 'Waste',
        adjustment: 'Manual adjustment'
      };

      if (normalized === 'Enabled' || normalized === 'Disabled') {
        return normalized;
      }

      if ((key.indexOf('.') !== -1 || booleanFields[key]) && (normalized === '0' || normalized === '1')) {
        return normalized === '1' ? 'Enabled' : 'Disabled';
      }

      return map[normalized] || normalized;
    }

    function changeText(key, value, prefix) {
      return prefix + ' ' + fieldLabel(key) + ' = ' + readableValue(key, value);
    }

    function metadata(log) {
      if (log.metadata && typeof log.metadata === 'object') return log.metadata;
      try {
        var parsed = JSON.parse(log.metadata || '{}');
        return parsed && typeof parsed === 'object' ? parsed : {};
      } catch (error) {
        return {};
      }
    }

    function entityLabel(log) {
      var meta = metadata(log);
      var type = entityTitle(log.entity_type);
      var name = meta.entity_name ? ' - ' + meta.entity_name : '';
      if (!log.entity_id) return name || '';
      return '(<button class="log-entity-link" type="button" data-log-id="' + escapeHtml(log.id) + '">' +
        escapeHtml(type) + ' ' + escapeHtml(log.entity_id) +
      '</button>)' + escapeHtml(name);
    }

    function renderLog(log) {
      var own = adminContext.employee && Number(adminContext.employee.id) === Number(log.employee_id);
      var headline = '<span class="log-actor">' + escapeHtml(log.employee_name || 'System') + '</span>' +
        '<span class="log-action">' + escapeHtml(actionVerb(log.permission_key)) + '</span>' +
        '<span class="log-area">' + escapeHtml(permissionTitle(log.permission_key)) + '</span>' +
        '<span class="log-description">' + escapeHtml(log.action_label || actionLabel(log.permission_key)) + ' ' + entityLabel(log) + '</span>';
      return '<div class="log-message ' + logClass(log) + (own ? ' log-message-own' : '') + '" data-id="' + escapeHtml(log.id) + '">' +
        '<div class="log-open" role="button" tabindex="0" data-log-id="' + escapeHtml(log.id) + '">' + headline + '</div>' +
        '<div class="log-message-meta"><span>' + escapeHtml(log.permission_key) + '</span><span>' + escapeHtml(log.created_at) + '</span></div>' +
      '</div>';
    }

    function valueHtml(value) {
      if (value === null || value === undefined || value === '') return '<span class="text-secondary">empty</span>';
      if (typeof value === 'object') return '<code>' + escapeHtml(JSON.stringify(value)) + '</code>';
      return escapeHtml(value);
    }

    function changesHtml(changes) {
      var keys = Object.keys(changes || {});
      if (!keys.length) return '<div class="log-empty">No changed fields were captured for this action.</div>';

      return '<div class="log-detail-list">' + keys.map(function (key) {
        var change = changes[key] || {};
        var isPermission = key.indexOf('.') !== -1 && (change.old === 'Enabled' || change.old === 'Disabled' || change.new === 'Enabled' || change.new === 'Disabled');
        return '<div class="log-detail-row">' +
          '<div class="log-detail-key">' + escapeHtml(isPermission ? key : fieldLabel(key)) + '</div>' +
          '<div><span>' + escapeHtml(changeText(key, change.old, 'Old value')) + '</span><strong>' + valueHtml(readableValue(key, change.old)) + '</strong></div>' +
          '<div><span>' + escapeHtml(changeText(key, change.new, 'New value')) + '</span><strong>' + valueHtml(readableValue(key, change.new)) + '</strong></div>' +
        '</div>';
      }).join('') + '</div>';
    }

    function snapshotHtml(snapshot) {
      var keys = Object.keys(snapshot || {}).filter(function (key) {
        return snapshot[key] !== null && snapshot[key] !== '' && typeof snapshot[key] !== 'object';
      }).slice(0, 20);

      if (!keys.length) return '<div class="log-empty">No extra information found.</div>';

      return '<div class="log-detail-list">' + keys.map(function (key) {
        return '<div class="log-detail-row compact"><div class="log-detail-key">' + escapeHtml(fieldLabel(key)) + '</div><strong>' + valueHtml(readableValue(key, snapshot[key])) + '</strong></div>';
      }).join('') + '</div>';
    }

    function entityPath(log) {
      var id = log.entity_id;
      if (!id) return '';
      var map = {
        food: '/menu-foods/',
        category: '/menu-categories/',
        food_addon: '/food-addons/',
        employee: '/employees/',
        table: '/tables/',
        order: '/orders/',
        inventory: '/inventory/',
        restaurant: '/restaurants/'
      };
      return map[log.entity_type] ? map[log.entity_type] + encodeURIComponent(id) : '';
    }

    function showLogModal(log, fetchEntity) {
      if (!modal || !modalBody) return;
      var meta = metadata(log);
      modalTitle.textContent = actionLabel(log.permission_key) + ' ' + permissionTitle(log.permission_key);
      modalSubtitle.textContent = (log.employee_name || 'System') + ' / ' + text(log.created_at) + ' / ' + text(log.permission_key);
      modalBody.innerHTML =
        '<div class="log-detail-event">' + escapeHtml(log.action_label || log.message || 'No action summary available.') + '</div>' +
        '<div class="log-detail-summary">' +
          '<div><span>Staff member</span><strong>' + escapeHtml(log.employee_name || 'System') + '</strong></div>' +
          '<div><span>Action</span><strong>' + escapeHtml(actionLabel(log.permission_key)) + ' ' + escapeHtml(permissionTitle(log.permission_key)) + '</strong></div>' +
          '<div><span>Affected record</span><strong>' + escapeHtml(entityTitle(log.entity_type)) + ' #' + escapeHtml(log.entity_id || '-') + '</strong></div>' +
          '<div><span>Branch ID</span><strong>' + escapeHtml(Number(log.branch_id || 0) || 'Main restaurant') + '</strong></div>' +
          '<div><span>Record name</span><strong>' + escapeHtml(meta.entity_name || '-') + '</strong></div>' +
          '<div><span>Permission used</span><strong>' + escapeHtml(log.permission_key || '-') + '</strong></div>' +
          '<div><span>Time</span><strong>' + escapeHtml(log.created_at || '-') + '</strong></div>' +
        '</div>' +
        '<h6 class="log-detail-heading">What Changed</h6>' +
        changesHtml(meta.changes || {}) +
        '<h6 class="log-detail-heading">Saved Information</h6>' +
        snapshotHtml(meta.snapshot || {});
      modal.show();

      var path = fetchEntity ? entityPath(log) : '';
      if (!path) return;

      modalBody.insertAdjacentHTML('beforeend', '<div class="log-loading">Loading current record...</div>');
      request(path).then(function (payload) {
        var loadingNode = modalBody.querySelector('.log-loading');
        if (loadingNode) loadingNode.remove();
        modalBody.insertAdjacentHTML('beforeend', '<h6 class="log-detail-heading">Current Record</h6>' + snapshotHtml(payload.data || {}));
      }).catch(function () {
        var loadingNode = modalBody.querySelector('.log-loading');
        if (loadingNode) loadingNode.textContent = 'Current record could not be loaded.';
      });
    }

    function appendLogs(rows, prepend) {
      if (!rows.length && !prepend && !messages.children.length) {
        messages.innerHTML = '<div class="text-center text-secondary py-4">No logs found.</div>';
        return;
      }

      if (messages.children.length === 1 && messages.textContent.indexOf('No logs found.') !== -1) {
        messages.innerHTML = '';
      }

      rows.forEach(function (row) {
        logsById[Number(row.id || 0)] = row;
        newestId = Math.max(newestId, Number(row.id || 0));
        oldestId = oldestId === 0 ? Number(row.id || 0) : Math.min(oldestId, Number(row.id || 0));
      });

      var html = rows.map(renderLog).join('');
      if (prepend) {
        messages.insertAdjacentHTML('afterbegin', html);
      } else {
        messages.insertAdjacentHTML('beforeend', html);
        stream.scrollTop = stream.scrollHeight;
      }
    }

    function loadLogs(mode) {
      if (loading) return Promise.resolve();
      loading = true;
      var extra = {};
      if (mode === 'newer' && newestId) extra.after_id = newestId;
      if (mode === 'older' && oldestId) extra.before_id = oldestId;

      return request(buildLogPath(extra)).then(function (payload) {
        appendLogs(payload.data || [], mode === 'older');
      }).finally(function () {
        loading = false;
      });
    }

    function resetLogs() {
      newestId = 0;
      oldestId = 0;
      logsById = {};
      messages.innerHTML = '';
      updateDropdownLabel(permissionFilter, permissionDropdown, '.log-permission-check', 'All permissions', '%d permissions selected');
      updateDropdownLabel(staffFilter, staffDropdown, '.log-staff-check', 'All staff', '%d staff selected');
      loadLogs();
    }

    var visibleLogPermissions = Object.keys(page.permissions || {}).filter(function (key) {
      return !Array.isArray(page.default_permissions) || !page.default_permissions.length || page.default_permissions.indexOf(key) !== -1;
    });
    permissionFilter.innerHTML = visibleLogPermissions.map(function (key) {
      return '<label class="dropdown-item log-filter-option">' +
        '<input class="form-check-input me-2 log-permission-check" type="checkbox" value="' + escapeHtml(key) + '" data-label="' + escapeHtml(key) + '">' +
        '<span>' + escapeHtml(key) + '</span>' +
      '</label>';
    }).join('');

    request('/employees').then(function (payload) {
      var logStaffRows = (payload.data || []).filter(function (person) {
        return !page.brand_mode || person.role === 'manager' || person.role === 'owner';
      });
      staffFilter.innerHTML = logStaffRows.map(function (person) {
        var label = person.name || person.username || ('Staff #' + person.id);
        return '<label class="dropdown-item log-filter-option">' +
          '<input class="form-check-input me-2 log-staff-check" type="checkbox" value="' + escapeHtml(person.id) + '" data-label="' + escapeHtml(label) + '">' +
          '<span>' + escapeHtml(label) + '</span>' +
        '</label>';
      }).join('');
      updateDropdownLabel(staffFilter, staffDropdown, '.log-staff-check', 'All staff', '%d staff selected');
    }).catch(function () {});

    document.getElementById('logApplyFilters').addEventListener('click', resetLogs);
    [rangeFilter].forEach(function (filter) {
      if (filter) filter.addEventListener('change', resetLogs);
    });
    [permissionFilter, staffFilter].forEach(function (filter) {
      if (!filter) return;
      filter.addEventListener('change', function (event) {
        if (!event.target.matches('.log-permission-check, .log-staff-check')) return;
        resetLogs();
      });
    });
    if (loadMore) loadMore.addEventListener('click', function () { loadLogs('older'); });

    messages.addEventListener('click', function (event) {
      var entityButton = event.target.closest('.log-entity-link');
      var openButton = event.target.closest('.log-open');
      var button = entityButton || openButton;
      if (!button) return;
      var log = logsById[Number(button.getAttribute('data-log-id') || 0)];
      if (log) showLogModal(log, !!entityButton);
    });

    stream.addEventListener('scroll', function () {
      if (stream.scrollTop <= 8) loadLogs('older');
    });

    resetLogs();
    window.setInterval(function () {
      if (!document.hidden) loadLogs('newer');
    }, 2000);
  }

  function initInvoices() {
    var body = document.getElementById('invoicesTableBody');
    if (!body) return;

    var rows = [];
    var selectedInvoice = null;
    var printSettings = { invoice_print_full_page: 0, invoice_print_width_mm: 80, invoice_print_height_mm: 297 };
    var detailTitle = document.getElementById('invoiceDetailTitle');
    var detailMeta = document.getElementById('invoiceDetailMeta');
    var detailBody = document.getElementById('invoiceDetailBody');
    var printBtn = document.getElementById('invoicePrintBtn');
    var retryBtn = document.getElementById('invoiceRetryBtn');

    function invoiceStatusBadge(status) {
      var normalized = text(status, 'draft');
      var color = {
        accepted: 'success',
        rejected: 'danger',
        retry_pending: 'warning',
        ready: 'primary',
        submitting: 'info',
        disabled: 'secondary',
        draft: 'secondary'
      }[normalized] || 'secondary';

      return '<span class="badge bg-' + color + '-subtle text-' + color + ' border border-' + color + '-subtle">' +
        escapeHtml(normalized.replace(/_/g, ' ')) +
      '</span>';
    }

    function renderInvoices() {
      document.getElementById('invoicesShowing').textContent = rows.length + ' invoices';
      body.innerHTML = rows.map(function (invoice) {
        return '<tr class="invoice-row" data-id="' + escapeHtml(invoice.id) + '">' +
          '<td class="fw-semibold">' + escapeHtml(invoice.local_invoice_number || ('#' + invoice.id)) + '</td>' +
          '<td>#' + escapeHtml(invoice.order_id) + '</td>' +
          '<td>' + invoiceStatusBadge(invoice.jofotara_submission_status) + '</td>' +
          '<td>' + money(invoice.grand_total) + '</td>' +
        '</tr>';
      }).join('') || '<tr><td colspan="4" class="text-center text-secondary py-4">No invoices yet.</td></tr>';
    }

    function invoiceItemsTable(items) {
      return '<div class="table-responsive mt-3"><table class="table table-sm align-middle">' +
        '<thead><tr><th>Description</th><th>Qty</th><th>Unit</th><th>Tax %</th><th>Tax</th><th>Total</th></tr></thead>' +
        '<tbody>' + (items || []).map(function (item) {
          return '<tr>' +
            '<td>' + escapeHtml(item.description) + '</td>' +
            '<td>' + escapeHtml(item.quantity) + '</td>' +
            '<td>' + money(item.unit_price) + '</td>' +
            '<td>' + escapeHtml(item.tax_rate) + '</td>' +
            '<td>' + money(item.tax_amount) + '</td>' +
            '<td>' + money(item.line_total) + '</td>' +
          '</tr>';
        }).join('') + '</tbody></table></div>';
    }

    function renderInvoiceDetail(invoice) {
      selectedInvoice = invoice;
      detailTitle.textContent = 'Invoice ' + (invoice.local_invoice_number || ('#' + invoice.id));
      detailMeta.textContent = 'Order #' + invoice.order_id + ' / ' + text(invoice.issued_at);
      if (printBtn) printBtn.classList.remove('d-none');
      if (retryBtn) retryBtn.classList.toggle('d-none', invoice.jofotara_submission_status === 'accepted' || invoice.jofotara_submission_status === 'disabled');

      detailBody.innerHTML =
        '<div class="invoice-print-area">' +
          '<div class="thermal-invoice-header">' +
            '<div class="fw-bold">' + escapeHtml(invoice.seller_name || '-') + '</div>' +
            '<div>' + escapeHtml(invoice.seller_address || '-') + '</div>' +
            '<div>' + escapeHtml(invoice.seller_phone || '-') + '</div>' +
            '<div>' + escapeHtml(invoice.seller_tax_number ? ('TIN: ' + invoice.seller_tax_number) : ('National No: ' + (invoice.seller_national_number || '-'))) + '</div>' +
          '</div>' +
          '<hr>' +
          '<div class="d-flex justify-content-between gap-2 flex-wrap">' +
            '<div><div class="text-secondary small">Invoice</div><div class="fw-semibold">' + escapeHtml(invoice.local_invoice_number) + '</div></div>' +
            '<div><div class="text-secondary small">Status</div>' + invoiceStatusBadge(invoice.jofotara_submission_status) + '</div>' +
            '<div><div class="text-secondary small">EIN</div><div>' + escapeHtml(invoice.electronic_invoice_number || '-') + '</div></div>' +
          '</div>' +
          invoiceItemsTable(invoice.items || []) +
          '<div class="invoice-totals mt-3">' +
            '<div><span>Subtotal</span><strong>' + money(invoice.subtotal) + '</strong></div>' +
            '<div><span>Discount</span><strong>' + money(invoice.discount_total) + '</strong></div>' +
            '<div><span>Tax</span><strong>' + money(invoice.tax_total) + '</strong></div>' +
            '<div><span>Grand Total</span><strong>' + money(invoice.grand_total) + '</strong></div>' +
          '</div>' +
          (invoice.jofotara_qr_value ? '<div class="mt-3"><div class="text-secondary small">JoFotara QR Value</div><code class="small d-block text-break">' + escapeHtml(invoice.jofotara_qr_value) + '</code></div>' : '') +
          (invoice.error_message ? '<div class="alert alert-warning mt-3 mb-0">' + escapeHtml(invoice.error_message) + '</div>' : '') +
        '</div>';
    }

    function applyInvoicePrintSettings() {
      var existing = document.getElementById('invoicePrintSettingsStyle');
      if (existing) existing.remove();

      var fullPage = Number(printSettings.invoice_print_full_page || 0) === 1;
      var width = Math.max(40, Math.min(300, Number(printSettings.invoice_print_width_mm || 80)));
      var height = Math.max(80, Math.min(500, Number(printSettings.invoice_print_height_mm || 297)));
      var size = fullPage ? 'auto' : width + 'mm ' + height + 'mm';
      var areaWidth = fullPage ? '100%' : width + 'mm';
      var style = document.createElement('style');
      style.id = 'invoicePrintSettingsStyle';
      style.textContent = '@page{size:' + size + ';margin:' + (fullPage ? '10mm' : '0') + ';}' +
        '@media print{html,body{width:' + areaWidth + ';min-width:' + areaWidth + ';}' +
        '.invoice-print-area{width:' + areaWidth + ';}}';
      document.head.appendChild(style);
      document.body.classList.toggle('invoice-print-full-page', fullPage);
    }

    function loadInvoices() {
      request('/invoices').then(function (payload) {
        rows = payload.data || [];
        renderInvoices();
        if (selectedInvoice) {
          var refreshed = rows.find(function (row) { return String(row.id) === String(selectedInvoice.id); });
          if (!refreshed && detailBody) {
            detailBody.innerHTML = '<div class="text-center text-secondary py-5">Select an invoice to view details.</div>';
          }
        }
      }).catch(function (error) {
        body.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-4">' + escapeHtml(error.message || 'Unable to load invoices.') + '</td></tr>';
      });
    }

    if (activeRestaurantId) {
      request('/restaurants/' + activeRestaurantId + '/tax-settings').then(function (payload) {
        printSettings = Object.assign(printSettings, payload.data || {});
      }).catch(function () {});
    }

    body.addEventListener('click', function (event) {
      var row = event.target.closest('.invoice-row');
      if (!row) return;
      request('/invoices/' + row.dataset.id).then(function (payload) {
        renderInvoiceDetail(payload.data || {});
      }).catch(function (error) {
        detailBody.innerHTML = '<div class="alert alert-danger">' + escapeHtml(error.message || 'Unable to load invoice.') + '</div>';
      });
    });

    if (retryBtn) retryBtn.addEventListener('click', function () {
      if (!selectedInvoice) return;
      request('/invoices/' + selectedInvoice.id + '/retry', { method: 'POST' }).then(function (payload) {
        renderInvoiceDetail(payload.data || selectedInvoice);
        swalToast(payload.message || 'Invoice retry finished');
        loadInvoices();
      }).catch(function (error) {
        detailBody.insertAdjacentHTML('afterbegin', '<div class="alert alert-warning">' + escapeHtml(error.message || 'Retry failed.') + '</div>');
      });
    });

    if (printBtn) printBtn.addEventListener('click', function () {
      applyInvoicePrintSettings();
      window.print();
    });

    loadInvoices();
  }

  function initRestaurants() {
    var body = document.getElementById('restaurantsTableBody');
    var form = document.getElementById('restaurantForm');
    if (!body || !form) return;

    var rows = [];
    var modalEl = document.getElementById('restaurantModal');
    var modal = modalEl ? new bootstrap.Modal(modalEl) : null;
    var alertBox = document.getElementById('restaurantFormAlert');

    function showRestaurantError(message) {
      alertBox.textContent = message;
      alertBox.classList.remove('d-none');
    }

    function restaurantPayload() {
      var parentId = document.getElementById('restaurantParentId').value;
      var settings = document.getElementById('restaurantBranchSettings').value.trim();
      var isBranch = !!parentId;
      var isBrand = !isBranch && document.getElementById('restaurantBranchEnabled').checked;
      var name = document.getElementById('restaurantName').value.trim();
      var code = document.getElementById('restaurantCode').value.trim();

      return {
        name: name,
        location: isBrand ? (document.getElementById('restaurantLocation').value.trim() || 'Brand office') : document.getElementById('restaurantLocation').value.trim(),
        active_until: isBrand ? (document.getElementById('restaurantActiveUntil').value || '2030-12-31') : document.getElementById('restaurantActiveUntil').value,
        manager_number: document.getElementById('restaurantManager').value.trim(),
        txt_details: isBrand ? (document.getElementById('restaurantDetails').value.trim() || 'Brand account') : document.getElementById('restaurantDetails').value.trim(),
        main_code: isBrand && !code ? brandCode(name) : code,
        parent_restaurant_id: parentId ? Number(parentId) : null,
        branch_management_enabled: !isBranch && document.getElementById('restaurantBranchEnabled').checked ? 1 : 0,
        branch_limit: !isBranch && document.getElementById('restaurantBranchEnabled').checked ? Number(document.getElementById('restaurantBranchLimit').value || 0) : 0,
        branch_settings: settings
      };
    }

    function brandCode(name) {
      var base = text(name, 'BRAND').toUpperCase().replace(/[^A-Z0-9]+/g, '').slice(0, 12) || 'BRAND';
      return base + String(Date.now()).slice(-4);
    }

    function setRestaurantMode(row, parentId) {
      var isBranch = !!parentId || !!(row && row.parent_restaurant_id);
      var branchEnabled = document.getElementById('restaurantBranchEnabled');
      var isBrand = !isBranch && branchEnabled.checked;
      var branchLimitWrap = document.getElementById('restaurantBranchLimitWrap');
      var branchEnabledWrap = document.getElementById('restaurantBranchEnabledWrap');
      var codeWrap = document.getElementById('restaurantCodeWrap');
      var locationWrap = document.getElementById('restaurantLocationWrap');
      var detailsWrap = document.getElementById('restaurantDetailsWrap');
      var activeUntilWrap = document.getElementById('restaurantActiveUntilWrap');
      var settingsWrap = document.getElementById('restaurantBranchSettings').closest('.col-12');

      document.getElementById('restaurantNameLabel').textContent = isBrand ? 'Brand Name' : 'Restaurant Name';
      document.getElementById('restaurantManagerLabel').textContent = isBrand ? 'Owner Contact Info' : 'Manager Phone';
      codeWrap.classList.toggle('d-none', isBrand);
      locationWrap.classList.toggle('d-none', isBrand);
      detailsWrap.classList.toggle('d-none', isBrand);
      activeUntilWrap.classList.toggle('d-none', isBrand);
      branchEnabledWrap.classList.toggle('d-none', isBranch);
      branchLimitWrap.classList.toggle('d-none', isBranch || !branchEnabled.checked);
      if (settingsWrap) settingsWrap.classList.toggle('d-none', isBrand);

      document.getElementById('restaurantCode').required = !isBrand;
      document.getElementById('restaurantLocation').required = !isBrand;
      document.getElementById('restaurantDetails').required = !isBrand;
      document.getElementById('restaurantActiveUntil').required = !isBrand;
      document.getElementById('restaurantBranchLimit').required = !isBranch && branchEnabled.checked;
      branchEnabled.disabled = !isSuperAdmin || isBranch;
      document.getElementById('restaurantBranchLimit').readOnly = !isSuperAdmin;
    }

    function fillRestaurant(row, parentId) {
      document.getElementById('restaurantId').value = row ? row.id : '';
      document.getElementById('restaurantName').value = row ? text(row.name) : '';
      document.getElementById('restaurantCode').value = row ? text(row.main_code) : '';
      document.getElementById('restaurantLocation').value = row ? text(row.location) : '';
      document.getElementById('restaurantParentId').value = row ? text(row.parent_restaurant_id) : (parentId || (isBranchBrandContext && activeRestaurantId ? activeRestaurantId : '') || (!isSuperAdmin && activeRestaurantId ? activeRestaurantId : ''));
      document.getElementById('restaurantBranchEnabled').checked = row ? Number(row.branch_management_enabled || 0) === 1 : false;
      document.getElementById('restaurantBranchLimit').value = row ? Number(row.branch_limit || 0) : 0;
      document.getElementById('restaurantBranchSettings').value = row ? text(row.branch_settings) : '';
      document.getElementById('restaurantManager').value = row ? text(row.manager_number) : '';
      document.getElementById('restaurantActiveUntil').value = row ? text(row.active_until || row.active_unitl).slice(0, 10) : '';
      document.getElementById('restaurantDetails').value = row ? text(row.txt_details) : '';
      setRestaurantMode(row, document.getElementById('restaurantParentId').value);
      document.getElementById('restaurantModalTitle').textContent = row ? (row.parent_restaurant_id ? 'Edit Branch' : 'Edit Brand') : (document.getElementById('restaurantParentId').value ? 'Add Branch' : 'Add Restaurant');
      alertBox.classList.add('d-none');
    }

    function renderRestaurants() {
      var baseRestaurantId = Number((adminContext.employee || {}).restaurant_id || activeRestaurantId || 0);
      var visibleRows = isBranchBrandContext && activeRestaurantId
        ? rows.filter(function (row) {
          return String(row.id) === String(activeRestaurantId) || String(row.parent_restaurant_id || '') === String(activeRestaurantId);
        })
        : (isSuperAdmin ? rows : rows.filter(function (row) {
        return String(row.id) === String(baseRestaurantId) || String(row.parent_restaurant_id || '') === String(baseRestaurantId);
      })).slice();
      var branchesByParent = {};
      visibleRows.forEach(function (row) {
        var parentId = row.parent_restaurant_id || '';
        if (!parentId) return;
        if (!branchesByParent[parentId]) branchesByParent[parentId] = [];
        branchesByParent[parentId].push(row);
      });
      var roots = visibleRows.filter(function (row) {
        return !row.parent_restaurant_id;
      });

      if (isBranchBrandContext && activeRestaurantId) {
        var brand = rows.find(function (row) {
          return String(row.id) === String(activeRestaurantId);
        }) || null;
        var branches = rows.filter(function (row) {
          return String(row.parent_restaurant_id || '') === String(activeRestaurantId);
        });

        body.innerHTML = branches.map(function (branch) {
          var branchActions = '<button class="btn btn-sm btn-outline-success restaurant-enter" data-id="' + branch.id + '"><i class="bi bi-box-arrow-in-right"></i></button> ';
          if (can('restaurants.update') || can('branches.update')) {
            branchActions += '<button class="btn btn-sm btn-outline-primary restaurant-edit" data-id="' + branch.id + '"><i class="bi bi-pencil"></i></button> ';
          }
          if (can('restaurants.delete') || can('branches.delete')) {
            branchActions += '<button class="btn btn-sm btn-outline-danger restaurant-delete" data-id="' + branch.id + '"><i class="bi bi-trash"></i></button>';
          }

          return '<tr class="restaurant-row branch-row" data-id="' + branch.id + '">' +
            '<td><span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle me-2">Branch</span>' + escapeHtml(branch.name) + '<div class="small text-secondary">' + escapeHtml(branch.main_code || '') + '</div></td>' +
            '<td>' + escapeHtml(branch.manager_number || '-') + '</td>' +
            '<td>' + escapeHtml(branch.location || '-') + '</td>' +
            '<td><span class="text-secondary">' + escapeHtml(brand ? ('Under ' + brand.name) : 'Branch') + '</span></td>' +
            '<td class="text-end">' + branchActions + '</td>' +
          '</tr>';
        }).join('') || '<tr><td colspan="5" class="text-center text-secondary py-4">No branches found.</td></tr>';

        return;
      }

      body.innerHTML = roots.map(function (row) {
        var branches = branchesByParent[row.id] || [];
        var hasBranchManagement = Number(row.branch_management_enabled || 0) === 1;
        var status = hasBranchManagement
          ? '<span class="badge bg-primary-subtle text-primary border border-primary-subtle">Branch management</span>'
          : '<span class="badge bg-success-subtle text-success border border-success-subtle">Only one branch</span>';
        var expand = hasBranchManagement
          ? '<button class="btn btn-sm btn-outline-secondary branch-toggle" type="button" data-id="' + row.id + '"><i class="bi bi-chevron-down"></i></button> '
          : '';
        var actions = '<button class="btn btn-sm btn-outline-success restaurant-enter" data-id="' + row.id + '"><i class="bi bi-box-arrow-in-right"></i></button> ';
        if (can('restaurants.update')) {
          actions += '<button class="btn btn-sm btn-outline-primary restaurant-edit" data-id="' + row.id + '"><i class="bi bi-pencil"></i></button> ';
        }
        if (hasBranchManagement && (can('restaurants.create') || can('branches.create'))) {
          actions += '<button class="btn btn-sm btn-outline-primary restaurant-add-branch" data-id="' + row.id + '"><i class="bi bi-plus-lg"></i> Branch</button> ';
        }
        if (can('restaurants.delete')) {
          actions += '<button class="btn btn-sm btn-outline-danger restaurant-delete" data-id="' + row.id + '"><i class="bi bi-trash"></i></button>';
        }

        return '<tr class="restaurant-row" data-id="' + row.id + '">' +
          '<td class="fw-semibold">' + expand + escapeHtml(row.name) + '</td>' +
          '<td>' + escapeHtml(row.manager_number || '-') + '</td>' +
          '<td>' + status + '</td>' +
          '<td>' + (hasBranchManagement ? escapeHtml(branches.length + ' / ' + Number(row.branch_limit || 0)) : '<span class="text-secondary">1 / 1</span>') + '</td>' +
          '<td class="text-end">' + actions + '</td>' +
        '</tr>' +
        (hasBranchManagement ? branches.map(function (branch) {
          var branchActions = '<button class="btn btn-sm btn-outline-success restaurant-enter" data-id="' + branch.id + '"><i class="bi bi-box-arrow-in-right"></i></button> ';
          if (can('restaurants.update') || can('branches.update')) {
            branchActions += '<button class="btn btn-sm btn-outline-primary restaurant-edit" data-id="' + branch.id + '"><i class="bi bi-pencil"></i></button> ';
          }
          if (can('restaurants.delete') || can('branches.delete')) {
            branchActions += '<button class="btn btn-sm btn-outline-danger restaurant-delete" data-id="' + branch.id + '"><i class="bi bi-trash"></i></button>';
          }

          return '<tr class="restaurant-row branch-row branch-of-' + row.id + ' d-none" data-id="' + branch.id + '">' +
            '<td><span class="branch-indent"></span><span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle me-2">Branch</span>' + escapeHtml(branch.name) + '<div class="small text-secondary">' + escapeHtml(branch.main_code || '') + '</div></td>' +
            '<td>' + escapeHtml(branch.manager_number || '-') + '</td>' +
            '<td>' + escapeHtml(branch.location || '-') + '</td>' +
            '<td><span class="text-secondary">Under ' + escapeHtml(row.name) + '</span></td>' +
            '<td class="text-end">' + branchActions + '</td>' +
          '</tr>';
        }).join('') : '');
      }).join('') || '<tr><td colspan="5" class="text-center text-secondary py-4">No restaurants found.</td></tr>';
    }

    function loadRestaurants() {
      request('/restaurants').then(function (payload) {
        rows = payload.data || [];
        renderRestaurants();
      }).catch(function (error) {
        body.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">' + escapeHtml(error.message || 'Unable to load restaurants.') + '</td></tr>';
      });
    }

    var addBtn = document.getElementById('restaurantAddBtn');
    if (addBtn) addBtn.addEventListener('click', function () { fillRestaurant(null, isBranchBrandContext ? activeRestaurantId : null); });
    document.getElementById('restaurantBranchEnabled').addEventListener('change', function () {
      setRestaurantMode(null, document.getElementById('restaurantParentId').value);
    });

    body.addEventListener('click', function (event) {
      var toggle = event.target.closest('.branch-toggle');
      var addBranch = event.target.closest('.restaurant-add-branch');
      var enter = event.target.closest('.restaurant-enter');
      var edit = event.target.closest('.restaurant-edit');
      var del = event.target.closest('.restaurant-delete');
      var rowEl = event.target.closest('.restaurant-row');
      var id = (toggle || addBranch || enter || edit || del || rowEl || {}).dataset ? (toggle || addBranch || enter || edit || del || rowEl).dataset.id : null;
      var row = rows.find(function (item) { return String(item.id) === String(id); });

      if (toggle) {
        document.querySelectorAll('.branch-of-' + toggle.dataset.id).forEach(function (branchRow) {
          branchRow.classList.toggle('d-none');
        });
        return;
      }

      if (addBranch && row) {
        fillRestaurant(null, row.id);
        if (modal) modal.show();
        return;
      }

      if (enter && row && confirm('Log in to the control panel as the restaurant owner?')) {
        window.location.href = '?page=dashboard&restaurant_id=' + encodeURIComponent(row.id);
      }

      if (edit && row) {
        fillRestaurant(row);
        modal.show();
      }

      if (del && row && confirm('Delete this restaurant?')) {
        request('/restaurants/' + row.id, { method: 'DELETE' }).then(loadRestaurants).catch(function (error) {
          showRestaurantError(error.message || 'Unable to delete restaurant.');
          if (modal) modal.show();
        });
      }
    });

    body.addEventListener('dblclick', function (event) {
      var rowEl = event.target.closest('.restaurant-row');
      if (!rowEl) return;
      if (confirm('Log in to the control panel as the restaurant owner?')) {
        window.location.href = '?page=dashboard&restaurant_id=' + encodeURIComponent(rowEl.dataset.id);
      }
    });

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      var id = document.getElementById('restaurantId').value;
      request('/restaurants' + (id ? '/' + id : ''), {
        method: id ? 'PUT' : 'POST',
        body: JSON.stringify(restaurantPayload())
      }).then(function () {
        if (modal) modal.hide();
        loadRestaurants();
      }).catch(function (error) {
        var message = error.message || 'Unable to save restaurant.';
        if (error.errors) message = Object.values(error.errors).join(' ');
        showRestaurantError(message);
      });
    });

    loadRestaurants();
  }

  function initRestaurantSettings() {
    var form = document.getElementById('restaurantSettingsForm');
    var restaurantId = Number(window.RESTAURANT_SETTINGS_ID || activeRestaurantId || 0);
    if (!form || !restaurantId) return;

    var alertBox = document.getElementById('restaurantSettingsAlert');
    var current = null;
    var websiteHeroImageFile = document.getElementById('websiteHeroImageFile');
    var websiteHeroImagePreview = document.getElementById('websiteHeroImagePreview');
    var websiteLogoImageFile = document.getElementById('websiteLogoImageFile');
    var websiteLogoImagePreview = document.getElementById('websiteLogoImagePreview');
    var websitePreviewFrame = document.getElementById('websitePreviewFrame');
    var websitePreviewRefresh = document.getElementById('websitePreviewRefresh');
    var websiteRootCss = document.getElementById('websiteRootCss');
    var websiteColorSettings = [
      { group: 'Base', label: 'Background', id: 'websiteBackgroundColor', setting: 'background_color', variable: '--color-bg', defaultValue: '#1b140f', type: 'color' },
      { group: 'Base', label: 'Background alt', id: 'websiteBackgroundAltColor', setting: 'background_alt_color', variable: '--color-bg-alt', defaultValue: '#221a14', type: 'color' },
      { group: 'Base', label: 'Surface', id: 'websiteSurfaceColor', setting: 'surface_color', variable: '--color-surface', defaultValue: '#2a2019', type: 'color' },
      { group: 'Base', label: 'Surface raised', id: 'websiteSurfaceRaisedColor', setting: 'surface_raised_color', variable: '--color-surface-raised', defaultValue: '#322620', type: 'color' },
      { group: 'Base', label: 'Border', id: 'websiteBorderColor', setting: 'border_color', variable: '--color-border', defaultValue: '#3d2f26', type: 'color' },
      { group: 'Base', label: 'Text', id: 'websiteTextColor', setting: 'text_color', variable: '--color-text', defaultValue: '#f4ece0', type: 'color' },
      { group: 'Base', label: 'Text muted', id: 'websiteTextMutedColor', setting: 'text_muted_color', variable: '--color-text-muted', defaultValue: '#b9a696', type: 'color' },
      { group: 'Base', label: 'Text faint', id: 'websiteTextFaintColor', setting: 'text_faint_color', variable: '--color-text-faint', defaultValue: '#8a7768', type: 'color' },
      { group: 'Base', label: 'Primary', id: 'websitePrimaryColor', setting: 'primary_color', variable: '--color-accent', defaultValue: '#e0872f', type: 'color' },
      { group: 'Base', label: 'Accent dark', id: 'websiteAccentDarkColor', setting: 'accent_dark_color', variable: '--color-accent-dark', defaultValue: '#b85f1e', type: 'color' },
      { group: 'Base', label: 'Accent soft', id: 'websiteAccentSoftColor', setting: 'accent_soft_color', variable: '--color-accent-soft', defaultValue: 'rgba(224, 135, 47, 0.14)', type: 'text' },
      { group: 'Base', label: 'Ember', id: 'websiteEmberColor', setting: 'ember_color', variable: '--color-ember', defaultValue: '#c1441e', type: 'color' },
      { group: 'Base', label: 'Accent', id: 'websiteAccentColor', setting: 'accent_color', variable: '--color-gold', defaultValue: '#cba15c', type: 'color' },
      { group: 'Base', label: 'Success', id: 'websiteSuccessColor', setting: 'success_color', variable: '--color-success', defaultValue: '#6f9c6a', type: 'color' },
      { group: 'Base', label: 'Danger', id: 'websiteDangerColor', setting: 'danger_color', variable: '--color-danger', defaultValue: '#c1441e', type: 'color' },
      { group: 'Base', label: 'Danger strong', id: 'websiteDangerStrongColor', setting: 'danger_strong_color', variable: '--color-danger-strong', defaultValue: '#c95045', type: 'color' },
      { group: 'Base', label: 'Scrollbar accent', id: 'websiteScrollbarAccentColor', setting: 'scrollbar_accent_color', variable: '--color-scrollbar-accent', defaultValue: '#eda255', type: 'color' },
      { group: 'Base', label: 'Text on accent', id: 'websiteOnAccentColor', setting: 'on_accent_color', variable: '--color-on-accent', defaultValue: '#1b140f', type: 'color' },
      { group: 'Base', label: 'Text on success', id: 'websiteOnSuccessColor', setting: 'on_success_color', variable: '--color-on-success', defaultValue: '#10190f', type: 'color' },
      { group: 'Base', label: 'Transparent token', id: 'websiteTransparentColor', setting: 'transparent_color', variable: '--color-transparent', defaultValue: 'transparent', type: 'text' },
      { group: 'Overlays', label: 'Hero vertical', id: 'websiteOverlayHeroVertical', setting: 'overlay_hero_vertical', variable: '--overlay-hero-vertical', defaultValue: 'linear-gradient(180deg, rgba(15, 10, 7, 0.55) 0%, rgba(15, 10, 7, 0.65) 45%, rgba(15, 10, 7, 0.95) 100%)', type: 'text' },
      { group: 'Overlays', label: 'Hero horizontal', id: 'websiteOverlayHeroHorizontal', setting: 'overlay_hero_horizontal', variable: '--overlay-hero-horizontal', defaultValue: 'linear-gradient(90deg, rgba(15, 10, 7, 0.75) 0%, rgba(15, 10, 7, 0.25) 55%)', type: 'text' },
      { group: 'Overlays', label: 'Status cover', id: 'websiteOverlayStatusCover', setting: 'overlay_status_cover', variable: '--overlay-status-cover', defaultValue: 'linear-gradient(rgba(20, 15, 12, 0.7), rgba(20, 15, 12, 0.92))', type: 'text' },
      { group: 'Overlays', label: 'Modal backdrop', id: 'websiteOverlayModalBackdrop', setting: 'overlay_modal_backdrop', variable: '--overlay-modal-backdrop', defaultValue: 'rgba(10, 7, 5, 0.72)', type: 'text' },
      { group: 'Overlays', label: 'Drawer backdrop', id: 'websiteOverlayDrawerBackdrop', setting: 'overlay_drawer_backdrop', variable: '--overlay-drawer-backdrop', defaultValue: 'rgba(10, 7, 5, 0.6)', type: 'text' },
      { group: 'Overlays', label: 'Language backdrop', id: 'websiteOverlayLanguageBackdrop', setting: 'overlay_language_backdrop', variable: '--overlay-language-backdrop', defaultValue: 'rgba(10, 7, 5, 0.78)', type: 'text' },
      { group: 'Overlays', label: 'Navbar', id: 'websiteOverlayNavbarBg', setting: 'overlay_navbar_bg', variable: '--overlay-navbar-bg', defaultValue: 'rgba(27, 20, 15, 0.86)', type: 'text' },
      { group: 'Overlays', label: 'Navbar mobile', id: 'websiteOverlayNavbarMobileBg', setting: 'overlay_navbar_mobile_bg', variable: '--overlay-navbar-mobile-bg', defaultValue: 'rgba(27, 20, 15, 0.98)', type: 'text' },
      { group: 'Overlays', label: 'Food badge', id: 'websiteOverlayFoodBadgeBg', setting: 'overlay_food_badge_bg', variable: '--overlay-food-badge-bg', defaultValue: 'rgba(27, 20, 15, 0.85)', type: 'text' },
      { group: 'Overlays', label: 'Panel', id: 'websiteOverlayPanelBg', setting: 'overlay_panel_bg', variable: '--overlay-panel-bg', defaultValue: 'rgba(42, 32, 25, 0.84)', type: 'text' },
      { group: 'Overlays', label: 'Panel strong', id: 'websiteOverlayPanelStrongBg', setting: 'overlay_panel_strong_bg', variable: '--overlay-panel-strong-bg', defaultValue: 'rgba(42, 32, 25, 0.9)', type: 'text' },
      { group: 'Overlays', label: 'Footer', id: 'websiteOverlayFooterBg', setting: 'overlay_footer_bg', variable: '--overlay-footer-bg', defaultValue: 'rgba(34, 26, 20, 0.78)', type: 'text' },
      { group: 'Overlays', label: 'Footer strong', id: 'websiteOverlayFooterStrongBg', setting: 'overlay_footer_strong_bg', variable: '--overlay-footer-strong-bg', defaultValue: 'rgba(34, 26, 20, 0.82)', type: 'text' },
      { group: 'Overlays', label: 'Footer mobile', id: 'websiteOverlayFooterMobileBg', setting: 'overlay_footer_mobile_bg', variable: '--overlay-footer-mobile-bg', defaultValue: 'rgba(34, 26, 20, 0.88)', type: 'text' },
      { group: 'Overlays', label: 'Control', id: 'websiteOverlayControlBg', setting: 'overlay_control_bg', variable: '--overlay-control-bg', defaultValue: 'rgba(15, 10, 7, 0.38)', type: 'text' },
      { group: 'Overlays', label: 'Control mid', id: 'websiteOverlayControlBgMid', setting: 'overlay_control_bg_mid', variable: '--overlay-control-bg-mid', defaultValue: 'rgba(15, 10, 7, 0.5)', type: 'text' },
      { group: 'Overlays', label: 'Control focus', id: 'websiteOverlayControlBgFocus', setting: 'overlay_control_bg_focus', variable: '--overlay-control-bg-focus', defaultValue: 'rgba(15, 10, 7, 0.64)', type: 'text' },
      { group: 'Overlays', label: 'Control dark', id: 'websiteOverlayControlBgDark', setting: 'overlay_control_bg_dark', variable: '--overlay-control-bg-dark', defaultValue: 'rgba(15, 10, 7, 0.72)', type: 'text' },
      { group: 'Overlays', label: 'Control darker', id: 'websiteOverlayControlBgDarker', setting: 'overlay_control_bg_darker', variable: '--overlay-control-bg-darker', defaultValue: 'rgba(15, 10, 7, 0.94)', type: 'text' },
      { group: 'Overlays', label: 'Media', id: 'websiteOverlayMediaBg', setting: 'overlay_media_bg', variable: '--overlay-media-bg', defaultValue: 'rgba(15, 10, 7, 0.58)', type: 'text' },
      { group: 'Overlays', label: 'Quantity', id: 'websiteOverlayQtyBg', setting: 'overlay_qty_bg', variable: '--overlay-qty-bg', defaultValue: 'rgba(15, 10, 7, 0.22)', type: 'text' },
      { group: 'Overlays', label: 'Check inset', id: 'websiteOverlayCheckInset', setting: 'overlay_check_inset', variable: '--overlay-check-inset', defaultValue: 'rgba(27, 20, 15, 0.18)', type: 'text' },
      { group: 'Overlays', label: 'Bill', id: 'websiteOverlayBillBg', setting: 'overlay_bill_bg', variable: '--overlay-bill-bg', defaultValue: 'rgba(15, 10, 7, 0.14)', type: 'text' },
      { group: 'Overlays', label: 'Meal group', id: 'websiteOverlayMealGroupBg', setting: 'overlay_meal_group_bg', variable: '--overlay-meal-group-bg', defaultValue: 'rgba(15, 10, 7, 0.08)', type: 'text' },
      { group: 'Text Tints', label: 'Text 4.5%', id: 'websiteTintText045', setting: 'tint_text_045', variable: '--tint-text-045', defaultValue: 'rgba(244, 236, 224, 0.045)', type: 'text' },
      { group: 'Text Tints', label: 'Text 5%', id: 'websiteTintText05', setting: 'tint_text_05', variable: '--tint-text-05', defaultValue: 'rgba(244, 236, 224, 0.05)', type: 'text' },
      { group: 'Text Tints', label: 'Text 5.5%', id: 'websiteTintText055', setting: 'tint_text_055', variable: '--tint-text-055', defaultValue: 'rgba(244, 236, 224, 0.055)', type: 'text' },
      { group: 'Text Tints', label: 'Text 6%', id: 'websiteTintText06', setting: 'tint_text_06', variable: '--tint-text-06', defaultValue: 'rgba(244, 236, 224, 0.06)', type: 'text' },
      { group: 'Text Tints', label: 'Text 7%', id: 'websiteTintText07', setting: 'tint_text_07', variable: '--tint-text-07', defaultValue: 'rgba(244, 236, 224, 0.07)', type: 'text' },
      { group: 'Text Tints', label: 'Text 8%', id: 'websiteTintText08', setting: 'tint_text_08', variable: '--tint-text-08', defaultValue: 'rgba(244, 236, 224, 0.08)', type: 'text' },
      { group: 'Text Tints', label: 'Text 9%', id: 'websiteTintText09', setting: 'tint_text_09', variable: '--tint-text-09', defaultValue: 'rgba(244, 236, 224, 0.09)', type: 'text' },
      { group: 'Text Tints', label: 'Text 12%', id: 'websiteTintText12', setting: 'tint_text_12', variable: '--tint-text-12', defaultValue: 'rgba(244, 236, 224, 0.12)', type: 'text' },
      { group: 'Text Tints', label: 'Text 13%', id: 'websiteTintText13', setting: 'tint_text_13', variable: '--tint-text-13', defaultValue: 'rgba(244, 236, 224, 0.13)', type: 'text' },
      { group: 'Text Tints', label: 'Text 14%', id: 'websiteTintText14', setting: 'tint_text_14', variable: '--tint-text-14', defaultValue: 'rgba(244, 236, 224, 0.14)', type: 'text' },
      { group: 'Text Tints', label: 'Text 16%', id: 'websiteTintText16', setting: 'tint_text_16', variable: '--tint-text-16', defaultValue: 'rgba(244, 236, 224, 0.16)', type: 'text' },
      { group: 'Text Tints', label: 'Text 18%', id: 'websiteTintText18', setting: 'tint_text_18', variable: '--tint-text-18', defaultValue: 'rgba(244, 236, 224, 0.18)', type: 'text' },
      { group: 'Text Tints', label: 'Text 20%', id: 'websiteTintText20', setting: 'tint_text_20', variable: '--tint-text-20', defaultValue: 'rgba(244, 236, 224, 0.2)', type: 'text' },
      { group: 'Text Tints', label: 'Text 22%', id: 'websiteTintText22', setting: 'tint_text_22', variable: '--tint-text-22', defaultValue: 'rgba(244, 236, 224, 0.22)', type: 'text' },
      { group: 'Text Tints', label: 'Text 24%', id: 'websiteTintText24', setting: 'tint_text_24', variable: '--tint-text-24', defaultValue: 'rgba(244, 236, 224, 0.24)', type: 'text' },
      { group: 'Text Tints', label: 'Text 28%', id: 'websiteTintText28', setting: 'tint_text_28', variable: '--tint-text-28', defaultValue: 'rgba(244, 236, 224, 0.28)', type: 'text' },
      { group: 'Text Tints', label: 'Text 34%', id: 'websiteTintText34', setting: 'tint_text_34', variable: '--tint-text-34', defaultValue: 'rgba(244, 236, 224, 0.34)', type: 'text' },
      { group: 'Text Tints', label: 'Text 40%', id: 'websiteTintText40', setting: 'tint_text_40', variable: '--tint-text-40', defaultValue: 'rgba(244, 236, 224, 0.4)', type: 'text' },
      { group: 'Text Tints', label: 'Text 50%', id: 'websiteTintText50', setting: 'tint_text_50', variable: '--tint-text-50', defaultValue: 'rgba(244, 236, 224, 0.5)', type: 'text' },
      { group: 'Text Tints', label: 'Text 64%', id: 'websiteTintText64', setting: 'tint_text_64', variable: '--tint-text-64', defaultValue: 'rgba(244, 236, 224, 0.64)', type: 'text' },
      { group: 'Accent Tints', label: 'Accent 10%', id: 'websiteTintAccent10', setting: 'tint_accent_10', variable: '--tint-accent-10', defaultValue: 'rgba(224, 135, 47, 0.1)', type: 'text' },
      { group: 'Accent Tints', label: 'Accent 12%', id: 'websiteTintAccent12', setting: 'tint_accent_12', variable: '--tint-accent-12', defaultValue: 'rgba(224, 135, 47, 0.12)', type: 'text' },
      { group: 'Accent Tints', label: 'Accent 13%', id: 'websiteTintAccent13', setting: 'tint_accent_13', variable: '--tint-accent-13', defaultValue: 'rgba(224, 135, 47, 0.13)', type: 'text' },
      { group: 'Accent Tints', label: 'Accent 18%', id: 'websiteTintAccent18', setting: 'tint_accent_18', variable: '--tint-accent-18', defaultValue: 'rgba(224, 135, 47, 0.18)', type: 'text' },
      { group: 'Accent Tints', label: 'Accent 28%', id: 'websiteTintAccent28', setting: 'tint_accent_28', variable: '--tint-accent-28', defaultValue: 'rgba(224, 135, 47, 0.28)', type: 'text' },
      { group: 'Accent Tints', label: 'Accent 36%', id: 'websiteTintAccent36', setting: 'tint_accent_36', variable: '--tint-accent-36', defaultValue: 'rgba(224, 135, 47, 0.36)', type: 'text' },
      { group: 'Accent Tints', label: 'Accent 45%', id: 'websiteTintAccent45', setting: 'tint_accent_45', variable: '--tint-accent-45', defaultValue: 'rgba(224, 135, 47, 0.45)', type: 'text' },
      { group: 'Accent Tints', label: 'Accent 50%', id: 'websiteTintAccent50', setting: 'tint_accent_50', variable: '--tint-accent-50', defaultValue: 'rgba(224, 135, 47, 0.5)', type: 'text' },
      { group: 'Accent Tints', label: 'Accent 55%', id: 'websiteTintAccent55', setting: 'tint_accent_55', variable: '--tint-accent-55', defaultValue: 'rgba(224, 135, 47, 0.55)', type: 'text' },
      { group: 'Accent Tints', label: 'Accent 60%', id: 'websiteTintAccent60', setting: 'tint_accent_60', variable: '--tint-accent-60', defaultValue: 'rgba(224, 135, 47, 0.6)', type: 'text' },
      { group: 'Accent Tints', label: 'Accent 65%', id: 'websiteTintAccent65', setting: 'tint_accent_65', variable: '--tint-accent-65', defaultValue: 'rgba(224, 135, 47, 0.65)', type: 'text' },
      { group: 'Accent Tints', label: 'Accent 70%', id: 'websiteTintAccent70', setting: 'tint_accent_70', variable: '--tint-accent-70', defaultValue: 'rgba(224, 135, 47, 0.7)', type: 'text' },
      { group: 'Accent Tints', label: 'Gold 12%', id: 'websiteTintGold12', setting: 'tint_gold_12', variable: '--tint-gold-12', defaultValue: 'rgba(230, 184, 105, 0.12)', type: 'text' },
      { group: 'Accent Tints', label: 'Gold 28%', id: 'websiteTintGold28', setting: 'tint_gold_28', variable: '--tint-gold-28', defaultValue: 'rgba(230, 184, 105, 0.28)', type: 'text' },
      { group: 'Accent Tints', label: 'Danger 20%', id: 'websiteTintDanger20', setting: 'tint_danger_20', variable: '--tint-danger-20', defaultValue: 'rgba(184, 54, 44, 0.2)', type: 'text' },
      { group: 'Accent Tints', label: 'Success 20%', id: 'websiteTintSuccess20', setting: 'tint_success_20', variable: '--tint-success-20', defaultValue: 'rgba(111, 156, 106, 0.2)', type: 'text' },
      { group: 'Accent Tints', label: 'Success 48%', id: 'websiteTintSuccess48', setting: 'tint_success_48', variable: '--tint-success-48', defaultValue: 'rgba(111, 156, 106, 0.48)', type: 'text' },
      { group: 'Accent Tints', label: 'Success glow', id: 'websiteGlowSuccess', setting: 'glow_success', variable: '--glow-success', defaultValue: 'rgba(111, 156, 106, 0.9)', type: 'text' },
      { group: 'Shadows', label: 'Card hover', id: 'websiteShadowCardHover', setting: 'shadow_card_hover', variable: '--shadow-card-hover', defaultValue: 'rgba(0, 0, 0, 0.6)', type: 'text' },
      { group: 'Shadows', label: 'Panel', id: 'websiteShadowPanel', setting: 'shadow_panel', variable: '--shadow-panel', defaultValue: 'rgba(0, 0, 0, 0.82)', type: 'text' },
      { group: 'Shadows', label: 'Panel soft', id: 'websiteShadowPanelSoft', setting: 'shadow_panel_soft', variable: '--shadow-panel-soft', defaultValue: 'rgba(0, 0, 0, 0.25)', type: 'text' },
      { group: 'Shadows', label: 'Panel strong', id: 'websiteShadowPanelStrong', setting: 'shadow_panel_strong', variable: '--shadow-panel-strong', defaultValue: 'rgba(0, 0, 0, 0.38)', type: 'text' },
      { group: 'Shadows', label: 'Control', id: 'websiteShadowControl', setting: 'shadow_control', variable: '--shadow-control', defaultValue: 'rgba(0, 0, 0, 0.22)', type: 'text' },
      { group: 'Shadows', label: 'Toast', id: 'websiteShadowToast', setting: 'shadow_toast', variable: '--shadow-toast', defaultValue: 'rgba(0, 0, 0, 0.7)', type: 'text' }
    ];

    var websiteColorDefaults = websiteColorSettings.reduce(function (colors, setting) {
      colors[setting.variable] = setting.defaultValue;
      return colors;
    }, {});

    function setValue(id, value) {
      var el = document.getElementById(id);
      if (!el) return;
      if (el.dataset && el.dataset.htmlEditor === 'true') {
        el.innerHTML = text(value);
        return;
      }
      el.value = text(value);
    }

    function getValue(id) {
      var el = document.getElementById(id);
      if (!el) return '';
      if (el.dataset && el.dataset.htmlEditor === 'true') {
        return el.innerHTML.trim();
      }
      return el.value.trim();
    }

    function websiteColorMap(row) {
      var raw = row && row.website_colors;
      if (!raw) return {};
      if (typeof raw === 'object') return raw;

      try {
        var parsed = JSON.parse(raw);
        return parsed && typeof parsed === 'object' ? parsed : {};
      } catch (error) {
        return {};
      }
    }

    function parseRootCss(textValue) {
      var body = text(textValue)
        .replace(/\/\*[\s\S]*?\*\//g, '')
        .replace(/^\s*:root\s*\{/i, '')
        .replace(/\}\s*$/i, '');
      var colors = {};
      var match;
      var pattern = /(--[a-zA-Z0-9_-]+)\s*:\s*([^;]+);?/g;

      while ((match = pattern.exec(body)) !== null) {
        colors[match[1].trim()] = match[2].trim();
      }

      return colors;
    }

    function mergedWebsiteColors(row) {
      var stored = websiteColorMap(row || {});
      var colors = Object.assign({}, websiteColorDefaults);

      websiteColorSettings.forEach(function (setting) {
        colors[setting.variable] = stored[setting.variable] || row?.[setting.setting] || colors[setting.variable] || setting.defaultValue;
      });

      return colors;
    }

    function formatRootCss(colors) {
      var lines = [':root {'];
      Object.keys(colors).forEach(function (variable) {
        lines.push('  ' + variable + ': ' + colors[variable] + ';');
      });
      lines.push('}');

      return lines.join('\n');
    }

    function setWebsiteColors(row) {
      if (!websiteRootCss) return;
      websiteRootCss.value = formatRootCss(mergedWebsiteColors(row || {}));
    }

    function websiteColorsPayload() {
      var colors = Object.assign({}, websiteColorDefaults, parseRootCss(websiteRootCss ? websiteRootCss.value : ''));

      return colors;
    }

    function initHtmlEditors() {
      form.querySelectorAll('[data-html-editor="true"]').forEach(function (editor) {
        if (editor.dataset.editorReady === 'true') return;
        editor.dataset.editorReady = 'true';
        editor.dataset.placeholder = 'Write content...';
        editor.setAttribute('role', 'textbox');
        editor.setAttribute('aria-multiline', 'true');

        var toolbar = document.createElement('div');
        toolbar.className = 'settings-html-toolbar';
        toolbar.innerHTML =
          '<button class="btn btn-light btn-sm" type="button" data-command="bold" title="Bold"><i class="bi bi-type-bold"></i></button>' +
          '<button class="btn btn-light btn-sm" type="button" data-command="italic" title="Italic"><i class="bi bi-type-italic"></i></button>' +
          '<button class="btn btn-light btn-sm" type="button" data-command="underline" title="Underline"><i class="bi bi-type-underline"></i></button>' +
          '<button class="btn btn-light btn-sm" type="button" data-command="insertUnorderedList" title="List"><i class="bi bi-list-ul"></i></button>' +
          '<button class="btn btn-light btn-sm" type="button" data-command="createLink" title="Link"><i class="bi bi-link-45deg"></i></button>' +
          '<button class="btn btn-light btn-sm" type="button" data-command="removeFormat" title="Clear format"><i class="bi bi-eraser"></i></button>';

        editor.parentNode.insertBefore(toolbar, editor);

        toolbar.addEventListener('click', function (event) {
          var button = event.target.closest('[data-command]');
          if (!button) return;
          editor.focus();
          var command = button.dataset.command;
          if (command === 'createLink') {
            var url = window.prompt('Link URL');
            if (!url) return;
            if (!/^https?:\/\//i.test(url) && url.charAt(0) !== '/' && url.charAt(0) !== '#') {
              url = 'https://' + url;
            }
            document.execCommand(command, false, url);
            return;
          }

          document.execCommand(command, false, null);
        });

        editor.addEventListener('paste', function (event) {
          event.preventDefault();
          var plain = (event.clipboardData || window.clipboardData).getData('text/plain');
          document.execCommand('insertText', false, plain);
        });
      });
    }

    function applyPreviewColors() {
      if (!websitePreviewFrame || !websitePreviewFrame.contentDocument) return;

      var root = websitePreviewFrame.contentDocument.documentElement;
      var colors = websiteColorsPayload();
      Object.keys(colors).forEach(function (variable) {
        root.style.setProperty(variable, colors[variable]);
      });
    }

    function previewUrl() {
      var code = document.getElementById('settingsRestaurantCode').value.trim();
      var base = appBase || '';
      return base + '/?restaurant_code=' + encodeURIComponent(code) + '&preview=1';
    }

    function takeawayUrl() {
      var code = document.getElementById('settingsRestaurantCode').value.trim();
      var base = appBase || '';
      return base + '/?restaurant_code=' + encodeURIComponent(code) + '&takeaway=true';
    }

    function loadWebsitePreview() {
      if (!websitePreviewFrame) return;
      var code = document.getElementById('settingsRestaurantCode').value.trim();
      if (!code) return;
      websitePreviewFrame.src = previewUrl();
    }

    function initWebsitePreview() {
      if (websiteRootCss) {
        websiteRootCss.addEventListener('input', applyPreviewColors);
        websiteRootCss.addEventListener('change', applyPreviewColors);
      }

      if (websitePreviewFrame) {
        websitePreviewFrame.addEventListener('load', applyPreviewColors);
      }

      if (websitePreviewRefresh) {
        websitePreviewRefresh.addEventListener('click', loadWebsitePreview);
      }
    }

    function showSettingsError(message) {
      alertBox.textContent = message;
      alertBox.classList.remove('d-none');
    }

    function setSettings(row) {
      current = row;
      document.getElementById('settingsRestaurantId').value = row.id || '';
      document.getElementById('settingsRestaurantName').value = row.name || '';
      document.getElementById('settingsRestaurantCode').value = row.main_code || '';
      document.getElementById('settingsRestaurantLocation').value = row.location || '';
      document.getElementById('settingsRestaurantManager').value = row.manager_number || '';
      document.getElementById('settingsRestaurantActiveUntil').value = text(row.active_until || row.active_unitl).slice(0, 10);
      document.getElementById('settingsRestaurantDetails').value = row.txt_details || '';
      setValue('websiteBrandNameEn', row.brand_name_en);
      setValue('websiteBrandNameAr', row.brand_name_ar);
      setValue('websiteHeroTitleEn', row.hero_title_en);
      setValue('websiteHeroTitleAr', row.hero_title_ar);
      setValue('websiteHeroAccentEn', row.hero_accent_en);
      setValue('websiteHeroAccentAr', row.hero_accent_ar);
      setValue('websiteHeroEyebrowEn', row.hero_eyebrow_en);
      setValue('websiteHeroEyebrowAr', row.hero_eyebrow_ar);
      setValue('websiteHeroDescriptionEn', row.hero_description_en);
      setValue('websiteHeroDescriptionAr', row.hero_description_ar);
      setValue('websiteMenuTitleEn', row.menu_title_en);
      setValue('websiteMenuTitleAr', row.menu_title_ar);
      setValue('websiteMenuSubtitleEn', row.menu_subtitle_en);
      setValue('websiteMenuSubtitleAr', row.menu_subtitle_ar);
      setValue('websiteLogoImageUrl', row.logo_image_url);
      setValue('websiteHeroImageUrl', row.hero_image_url);
      var takeawayEnabled = document.getElementById('takeawayEnabled');
      if (takeawayEnabled) takeawayEnabled.checked = Number(row.takeaway_enabled || 0) === 1;
      var takeawayOrderLink = document.getElementById('takeawayOrderLink');
      if (takeawayOrderLink) takeawayOrderLink.href = takeawayUrl();
      setWebsiteColors(row);
      applyPreviewColors();
      if (websiteLogoImageFile) websiteLogoImageFile.value = '';
      if (websiteHeroImageFile) websiteHeroImageFile.value = '';
      setImagePreview(websiteLogoImagePreview, row.logo_image_url || '', 'bi bi-shop');
      setImagePreview(websiteHeroImagePreview, row.hero_image_url || '', 'bi bi-image');
      loadWebsitePreview();
    }

    function settingsPayload() {
      var websiteColors = websiteColorsPayload();

      return {
        name: document.getElementById('settingsRestaurantName').value.trim(),
        location: document.getElementById('settingsRestaurantLocation').value.trim(),
        active_until: document.getElementById('settingsRestaurantActiveUntil').value,
        manager_number: document.getElementById('settingsRestaurantManager').value.trim(),
        txt_details: document.getElementById('settingsRestaurantDetails').value.trim(),
        main_code: current ? current.main_code : document.getElementById('settingsRestaurantCode').value.trim(),
        brand_name_en: getValue('websiteBrandNameEn'),
        brand_name_ar: getValue('websiteBrandNameAr'),
        hero_title_en: getValue('websiteHeroTitleEn'),
        hero_title_ar: getValue('websiteHeroTitleAr'),
        hero_accent_en: getValue('websiteHeroAccentEn'),
        hero_accent_ar: getValue('websiteHeroAccentAr'),
        hero_eyebrow_en: getValue('websiteHeroEyebrowEn'),
        hero_eyebrow_ar: getValue('websiteHeroEyebrowAr'),
        hero_description_en: getValue('websiteHeroDescriptionEn'),
        hero_description_ar: getValue('websiteHeroDescriptionAr'),
        menu_title_en: getValue('websiteMenuTitleEn'),
        menu_title_ar: getValue('websiteMenuTitleAr'),
        menu_subtitle_en: getValue('websiteMenuSubtitleEn'),
        menu_subtitle_ar: getValue('websiteMenuSubtitleAr'),
        logo_image_url: getValue('websiteLogoImageUrl'),
        hero_image_url: getValue('websiteHeroImageUrl'),
        takeaway_enabled: document.getElementById('takeawayEnabled')?.checked ? 1 : 0,
        primary_color: websiteColors['--color-accent'] || '',
        accent_color: websiteColors['--color-gold'] || '',
        background_color: websiteColors['--color-bg'] || '',
        background_alt_color: websiteColors['--color-bg-alt'] || '',
        surface_color: websiteColors['--color-surface'] || '',
        surface_raised_color: websiteColors['--color-surface-raised'] || '',
        border_color: websiteColors['--color-border'] || '',
        text_color: websiteColors['--color-text'] || '',
        text_muted_color: websiteColors['--color-text-muted'] || '',
        text_faint_color: websiteColors['--color-text-faint'] || '',
        accent_dark_color: websiteColors['--color-accent-dark'] || '',
        accent_soft_color: websiteColors['--color-accent-soft'] || '',
        ember_color: websiteColors['--color-ember'] || '',
        success_color: websiteColors['--color-success'] || '',
        danger_color: websiteColors['--color-danger'] || '',
        website_colors: websiteColors
      };
    }

    function setTaxSettings(row) {
      var status = document.getElementById('taxConfigurationStatus');
      document.getElementById('taxpayerType').value = row.taxpayer_type || 'income_tax_only';
      document.getElementById('legalSellerName').value = row.legal_seller_name || row.restaurant_name || current?.name || '';
      document.getElementById('tradeName').value = row.trade_name || '';
      document.getElementById('sellerTaxNumber').value = row.seller_tax_number || '';
      document.getElementById('sellerNationalNumber').value = row.seller_national_number || '';
      document.getElementById('sellerAddress').value = row.seller_address || row.location || '';
      document.getElementById('sellerCity').value = row.seller_city || '';
      document.getElementById('sellerPhone').value = row.seller_phone || row.manager_number || '';
      document.getElementById('einvoicingEnabled').checked = Number(row.einvoicing_enabled || 0) === 1;
      document.getElementById('jofotaraClientId').value = row.jofotara_client_id || '';
      document.getElementById('jofotaraSecretKey').value = '';
      document.getElementById('jofotaraSecretKey').placeholder = row.has_secret_key ? '************' : 'Secret Key';
      document.getElementById('incomeSourceSequence').value = row.income_source_sequence || '';
      document.getElementById('defaultTaxRate').value = Number(row.default_tax_rate || 0);
      document.getElementById('pricesIncludeTax').checked = Number(row.prices_include_tax || 0) === 1;
      document.getElementById('invoicePrefix').value = row.invoice_prefix || 'INV';
      document.getElementById('automaticSubmission').checked = Number(row.automatic_submission ?? 1) === 1;
      document.getElementById('printAfterAccepted').checked = Number(row.print_after_accepted || 0) === 1;
      document.getElementById('invoicePrintFullPage').checked = Number(row.invoice_print_full_page || 0) === 1;
      document.getElementById('invoicePrintWidth').value = Number(row.invoice_print_width_mm || 80);
      document.getElementById('invoicePrintHeight').value = Number(row.invoice_print_height_mm || 297);
      document.getElementById('invoicePrintSizeFields').classList.toggle('d-none', Number(row.invoice_print_full_page || 0) === 1);

      if (status) {
        var label = (row.configuration_status || 'not_configured').replace(/_/g, ' ');
        status.textContent = label.charAt(0).toUpperCase() + label.slice(1);
        status.className = 'badge border ' + ({
          active: 'bg-success-subtle text-success border-success-subtle',
          configured: 'bg-primary-subtle text-primary border-primary-subtle',
          configuration_error: 'bg-danger-subtle text-danger border-danger-subtle'
        }[row.configuration_status] || 'bg-secondary-subtle text-secondary border-secondary-subtle');
      }
    }

    function taxSettingsPayload() {
      return {
        taxpayer_type: document.getElementById('taxpayerType').value,
        legal_seller_name: document.getElementById('legalSellerName').value.trim(),
        trade_name: document.getElementById('tradeName').value.trim(),
        seller_tax_number: document.getElementById('sellerTaxNumber').value.trim(),
        seller_national_number: document.getElementById('sellerNationalNumber').value.trim(),
        seller_address: document.getElementById('sellerAddress').value.trim(),
        seller_city: document.getElementById('sellerCity').value.trim(),
        seller_phone: document.getElementById('sellerPhone').value.trim(),
        einvoicing_enabled: document.getElementById('einvoicingEnabled').checked ? 1 : 0,
        jofotara_client_id: document.getElementById('jofotaraClientId').value.trim(),
        jofotara_secret_key: document.getElementById('jofotaraSecretKey').value.trim(),
        income_source_sequence: document.getElementById('incomeSourceSequence').value.trim(),
        default_tax_rate: Number(document.getElementById('defaultTaxRate').value || 0),
        prices_include_tax: document.getElementById('pricesIncludeTax').checked ? 1 : 0,
        invoice_prefix: document.getElementById('invoicePrefix').value.trim() || 'INV',
        automatic_submission: document.getElementById('automaticSubmission').checked ? 1 : 0,
        print_after_accepted: document.getElementById('printAfterAccepted').checked ? 1 : 0,
        invoice_print_full_page: document.getElementById('invoicePrintFullPage').checked ? 1 : 0,
        invoice_print_width_mm: Number(document.getElementById('invoicePrintWidth').value || 80),
        invoice_print_height_mm: Number(document.getElementById('invoicePrintHeight').value || 297)
      };
    }

    function showTaxError(error) {
      var box = document.getElementById('taxSettingsAlert');
      if (!box) return;
      box.textContent = error.errors ? Object.values(error.errors).join(' ') : (error.message || 'Unable to save tax settings.');
      box.classList.remove('d-none');
    }

    request('/restaurants/' + restaurantId).then(function (payload) {
      setSettings(payload.data || {});
    }).catch(function (error) {
      showSettingsError(error.message || 'Unable to load restaurant settings.');
    });

    initHtmlEditors();
    initWebsitePreview();

    if (window.RESTAURANT_TAX_SETTINGS_ENABLED && document.getElementById('taxSettingsForm')) {
      request('/restaurants/' + restaurantId + '/tax-settings').then(function (payload) {
        setTaxSettings(payload.data || {});
      }).catch(showTaxError);

      document.getElementById('taxSettingsForm').addEventListener('submit', function (event) {
        event.preventDefault();
        document.getElementById('taxSettingsAlert').classList.add('d-none');
        request('/restaurants/' + restaurantId + '/tax-settings', {
          method: 'PUT',
          body: JSON.stringify(taxSettingsPayload())
        }).then(function (payload) {
          setTaxSettings(payload.data || {});
          swalToast('Tax settings saved');
        }).catch(showTaxError);
      });

      document.getElementById('testTaxConnectionBtn').addEventListener('click', function () {
        request('/restaurants/' + restaurantId + '/tax-settings-test', { method: 'POST' }).then(function (payload) {
          swalToast(payload.message || 'Configuration test passed');
        }).catch(showTaxError);
      });

      document.getElementById('invoicePrintFullPage').addEventListener('change', function (event) {
        document.getElementById('invoicePrintSizeFields').classList.toggle('d-none', event.target.checked);
      });
    }

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      alertBox.classList.add('d-none');
      var file = websiteHeroImageFile && websiteHeroImageFile.files ? websiteHeroImageFile.files[0] : null;
      var logoFile = websiteLogoImageFile && websiteLogoImageFile.files ? websiteLogoImageFile.files[0] : null;

      Promise.all([
        uploadImage(logoFile, 'website-logo'),
        uploadImage(file, 'website')
      ]).then(function (paths) {
        if (paths[0]) setValue('websiteLogoImageUrl', paths[0]);
        if (paths[1]) setValue('websiteHeroImageUrl', paths[1]);

        return request('/restaurants/' + restaurantId, {
          method: 'PUT',
          body: JSON.stringify(settingsPayload())
        });
      }).then(function (payload) {
        setSettings(payload.data || current || {});
        swalToast('Restaurant settings saved');
      }).catch(function (error) {
        var message = error.message || 'Unable to save restaurant settings.';
        if (error.errors) message = Object.values(error.errors).join(' ');
        showSettingsError(message);
      });
    });

    if (websiteHeroImageFile) {
      websiteHeroImageFile.addEventListener('change', function () {
        var file = websiteHeroImageFile.files && websiteHeroImageFile.files[0];
        setImagePreview(websiteHeroImagePreview, file ? URL.createObjectURL(file) : getValue('websiteHeroImageUrl'), 'bi bi-image');
      });
    }

    if (websiteLogoImageFile) {
      websiteLogoImageFile.addEventListener('change', function () {
        var file = websiteLogoImageFile.files && websiteLogoImageFile.files[0];
        setImagePreview(websiteLogoImagePreview, file ? URL.createObjectURL(file) : getValue('websiteLogoImageUrl'), 'bi bi-shop');
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initHeaderActions();
      initDashboard();
      initRestaurants();
      initRestaurantSettings();
      initStaff();
      initOrdersPage();
      initInventory();
      initDiscounts();
      initMenuPage();
      initActivityLog();
      initInvoices();
    });
  } else {
    initHeaderActions();
    initDashboard();
    initRestaurants();
    initRestaurantSettings();
    initStaff();
    initOrdersPage();
    initInventory();
    initDiscounts();
    initMenuPage();
    initActivityLog();
    initInvoices();
  }
})();
