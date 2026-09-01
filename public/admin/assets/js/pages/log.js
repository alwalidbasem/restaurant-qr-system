/* global Chart */

( function () {
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

    function buildLogPath(extra) {
      var params = new URLSearchParams();
      params.set('limit', '25');
      params.set('range', rangeFilter ? rangeFilter.value : '24h');
      if (page.restaurant_id) params.set('restaurant_id', page.restaurant_id);

      var permissions = AdminUI.selectedValues(permissionFilter, '.log-permission-check');
      if (!permissions.length && Array.isArray(page.default_permissions) && page.default_permissions.length) {
        permissions = page.default_permissions;
      }
      var staffIds = AdminUI.selectedValues(staffFilter, '.log-staff-check');
      if (permissions.length) params.set('permissions', permissions.join(','));
      if (staffIds.length) params.set('employee_ids', staffIds.join(','));
      Object.keys(extra || {}).forEach(function (key) {
        if (extra[key]) params.set(key, extra[key]);
      });

      return '/logs?' + params.toString();
    }

    function logClass(log) {
      if (log.permission_key === 'restaurant.update' || log.permission_key.indexOf('branches.') === 0 || log.permission_key.indexOf('staff.') === 0) return 'log-message-critical';
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
        branches_dashboard: 'Branches Dashboard',
        dashboard: 'Dashboard',
        staff: 'Staff',
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
        pfp: 'Profile image',
        details: 'Details',
        hidden_details: 'Hidden details',
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
        employee: '/staff/',
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
      AdminUI.updateDropdownLabel(permissionFilter, permissionDropdown, '.log-permission-check', 'All permissions', '%d permissions selected');
      AdminUI.updateDropdownLabel(staffFilter, staffDropdown, '.log-staff-check', 'All staff', '%d staff selected');
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

    request('/staff').then(function (payload) {
      var logStaffRows = (payload.data || []).filter(function (person) {
        return !page.brand_mode || isManagerScopedStaff(person);
      });
      staffFilter.innerHTML = logStaffRows.map(function (person) {
        var label = person.name || person.username || ('Staff #' + person.id);
        return '<label class="dropdown-item log-filter-option">' +
          '<input class="form-check-input me-2 log-staff-check" type="checkbox" value="' + escapeHtml(person.id) + '" data-label="' + escapeHtml(label) + '">' +
          '<span>' + escapeHtml(label) + '</span>' +
        '</label>';
      }).join('');
      AdminUI.updateDropdownLabel(staffFilter, staffDropdown, '.log-staff-check', 'All staff', '%d staff selected');
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

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      initActivityLog();
    });
  } else {
    initActivityLog();
  }
})();

