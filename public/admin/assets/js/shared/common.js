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

( function () {
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
    if (isSuperAdmin) {
      if (['restaurants.create', 'restaurants.get', 'restaurants.update', 'restaurants.delete'].indexOf(permission) !== -1) {

        return permissionMap[permission] === true;
      }
      return true;
    }

    if (Number((adminContext.employee || {}).is_owner || 0) === 1) {
      return ['restaurants.create', 'restaurants.get', 'restaurants.update', 'restaurants.delete'].indexOf(permission) === -1;
    }

    if (Number((adminContext.employee || {}).is_manager || 0) === 1) {
      // Manager capabilities come from his permission string: his branch-management
      // keys (branches.*) plus his in-branch keys (staff, inventory, orders, etc.).
      return permissionMap[permission] === true;
    }

    return permissionMap[permission] === true;
  }

  function scopedPath(path) {
    if (!activeRestaurantId || path.indexOf('restaurant_id=') !== -1) return path;
    if (!/^\/(orders|tables|staff|menu-foods|menu-categories|food-addons|discounts|inventory|invoices|uploads|logs)(\?|$)/.test(path)) return path;
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

  function isManagerScopedStaff(person) {
    person = person || {};
    return Number(person.is_manager || 0) === 1
      || ['all', 'some', 'none'].indexOf(String(person.manager_scope || '')) !== -1
      || (Object.prototype.hasOwnProperty.call(person, 'allowed_branches') && person.allowed_branches !== null)
      || (Object.prototype.hasOwnProperty.call(person, 'managed_branches') && person.managed_branches !== null);
  }

  function isOwnerStaff(person, restaurantId) {
    person = person || {};
    var branchId = Number(person.branch_id || 0);
    return branchId <= 0
      && Number(person.is_owner || 0) === 1
      && String(person.restaurant_id || '') === String(restaurantId || '');
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
      staff: 'staff employee staff permission user manager',
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

window.appBase = appBase;
window.apiBase = apiBase;
window.currencyCode = currencyCode;
window.currencySymbol = currencySymbol;
window.permissions = permissions;
window.visibleStaffPermissions = visibleStaffPermissions;
window.adminContext = adminContext;
window.permissionMap = permissionMap;
window.activeRestaurantId = activeRestaurantId;
window.isSuperAdmin = isSuperAdmin;
window.isBranchBrandContext = isBranchBrandContext;
window.currentEmployeeId = currentEmployeeId;
window.can = can;
window.scopedPath = scopedPath;
window.request = request;
window.uploadImage = uploadImage;
window.setImagePreview = setImagePreview;
window.text = text;
window.money = money;
window.escapeHtml = escapeHtml;
window.isManagerScopedStaff = isManagerScopedStaff;
window.isOwnerStaff = isOwnerStaff;
window.statusBadge = statusBadge;
window.groupOrders = groupOrders;
window.initHeaderActions = initHeaderActions;

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', function () {
    initHeaderActions();
  });
} else {
  initHeaderActions();
}
})();

