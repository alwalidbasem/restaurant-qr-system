/* global Chart */

( function () {
  function initRestaurants() {
    var body = document.getElementById('restaurantsTableBody');
    var form = document.getElementById('restaurantForm');
    if (!body || !form) return;

    var rows = [];
    var owners = [];
    var modalEl = document.getElementById('restaurantModal');
    var modal = modalEl ? new bootstrap.Modal(modalEl) : null;
    var alertBox = document.getElementById('restaurantFormAlert');
    var ownerModalEl = document.getElementById('ownerModal');
    var ownerModal = ownerModalEl ? new bootstrap.Modal(ownerModalEl) : null;
    var ownerForm = document.getElementById('ownerForm');
    var ownerAlertBox = document.getElementById('ownerFormAlert');

    function openOwnerModal(restaurantId) {
      document.getElementById('ownerRestaurantId').value = restaurantId || '';
      document.getElementById('ownerName').value = '';
      document.getElementById('ownerUsername').value = '';
      document.getElementById('ownerPhone').value = '';
      document.getElementById('ownerEmail').value = '';
      document.getElementById('ownerPassword').value = '';
      var file = document.getElementById('ownerPfpFile');
      if (file) file.value = '';
      setImagePreview(document.getElementById('ownerPfpPreview'), '', 'bi bi-person');
      if (ownerAlertBox) ownerAlertBox.classList.add('d-none');
      if (ownerModal) ownerModal.show();
    }

    if (ownerForm) {
      var ownerPfpFile = document.getElementById('ownerPfpFile');
      if (ownerPfpFile) {
        ownerPfpFile.addEventListener('change', function () {
          var file = ownerPfpFile.files && ownerPfpFile.files[0];
          setImagePreview(document.getElementById('ownerPfpPreview'), file ? URL.createObjectURL(file) : '', 'bi bi-person');
        });
      }

      var ownerPasswordInput = document.getElementById('ownerPassword');
      var ownerPasswordToggle = document.getElementById('ownerPasswordToggle');
      if (ownerPasswordToggle && ownerPasswordInput) {
        ownerPasswordToggle.addEventListener('click', function () {
          var show = ownerPasswordInput.type === 'password';
          ownerPasswordInput.type = show ? 'text' : 'password';
          ownerPasswordToggle.innerHTML = show ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
        });
      }

      ownerForm.addEventListener('submit', function (event) {
        event.preventDefault();
        if (ownerAlertBox) ownerAlertBox.classList.add('d-none');
        var name = document.getElementById('ownerName').value.trim();
        var username = document.getElementById('ownerUsername').value.trim();
        var phone = document.getElementById('ownerPhone').value.trim();
        var email = document.getElementById('ownerEmail').value.trim();
        var password = document.getElementById('ownerPassword').value;
        var restaurantId = Number(document.getElementById('ownerRestaurantId').value || 0);
        if (!restaurantId) {
          if (ownerAlertBox) ownerAlertBox.textContent = 'Brand is required.';
          if (ownerAlertBox) ownerAlertBox.classList.remove('d-none');
          return;
        }
        if (!username) {
          if (ownerAlertBox) ownerAlertBox.textContent = 'Owner username is required.';
          if (ownerAlertBox) ownerAlertBox.classList.remove('d-none');
          return;
        }
        var file = ownerPfpFile && ownerPfpFile.files ? ownerPfpFile.files[0] : null;

        uploadImage(file, 'staff').then(function (path) {
          return request('/staff', {
            method: 'POST',
            body: JSON.stringify({
              name: name,
              username: username,
              password: password,
              phone: phone,
              email: email,
              pfp: path || '',
              restaurant_id: restaurantId,
              branch_id: null,
              is_superadmin: 0,
              is_owner: 1,
              is_manager: 0,
              is_employee: 0,
              manager_scope: 'all',
              allowed_branches: null,
              managed_branches: null,
              salary: 0,
              permissions: null
            })
          });
        }).then(function () {
          if (ownerModal) ownerModal.hide();
          loadRestaurants();
          swalToast('Owner created');
        }).catch(function (error) {
          var message = error.message || 'Unable to create owner.';
          if (error.errors) message = Object.values(error.errors).join(' ');
          if (ownerAlertBox) ownerAlertBox.textContent = message;
          if (ownerAlertBox) ownerAlertBox.classList.remove('d-none');
        });
      });
    }

    function showRestaurantError(message) {
      AdminUI.showAlert(alertBox, message, false);
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
function managerBranchIds() {
      var emp = adminContext.employee || {};
      if (Number(emp.is_manager || 0) !== 1) return null;
      var raw = String(emp.allowed_branches || emp.managed_branches || '').trim();
      if (raw === '' || raw.toLowerCase() === 'all') return null;
      return raw.split(',').map(Number).filter(Boolean);
    }

    function renderRestaurants() {
      var baseRestaurantId = Number((adminContext.employee || {}).restaurant_id || activeRestaurantId || 0);
      var managedBranches = managerBranchIds();
      var visibleRows = isBranchBrandContext && activeRestaurantId
        ? rows.filter(function (row) {
          return String(row.id) === String(activeRestaurantId) || String(row.parent_restaurant_id || '') === String(activeRestaurantId);
        })
        : (isSuperAdmin ? rows : rows.filter(function (row) {
        return String(row.id) === String(baseRestaurantId) || String(row.parent_restaurant_id || '') === String(baseRestaurantId);
      })).slice();
      if (managedBranches) {
        visibleRows = visibleRows.filter(function (row) {
          return (String(row.id) === String(baseRestaurantId) && !row.parent_restaurant_id) || managedBranches.indexOf(Number(row.id)) !== -1;
        });
      }
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
        var brandOwners = brand ? restaurantOwners(brand.id) : [];
        var brandRow = brand
          ? '<tr class="restaurant-parent-row restaurant-row-open" data-id="' + brand.id + '" data-accordion-id="' + brand.id + '" tabindex="0">' +
            '<td class="fw-semibold"><div class="restaurant-title-cell"><span class="restaurant-avatar"><i class="bi bi-building"></i></span><span><span class="restaurant-name">' + escapeHtml(brand.name) + '</span><div class="small text-secondary">' + escapeHtml(brand.main_code || '') + '</div></span></div></td>' +
            '<td>' + renderOwnerManagerSummary(brand, brandOwners) + '</td>' +
            '<td><span class="badge bg-primary-subtle text-primary border border-primary-subtle">Branch management</span></td>' +
            '<td>' + escapeHtml(branches.length + ' / ' + Number(brand.branch_limit || 0)) + '</td>' +
            '<td class="text-end">' + (can('restaurants.create') ? '<button class="btn btn-sm btn-outline-primary restaurant-add-owner" data-id="' + brand.id + '"><i class="bi bi-person-plus"></i> Add owner</button>' : '') + '</td>' +
          '</tr>'
          : '';

        body.innerHTML = brandRow + (branches.map(function (branch) {
          var branchActions = '<button class="btn btn-sm btn-outline-success restaurant-enter" data-id="' + branch.id + '"><i class="bi bi-box-arrow-in-right"></i></button> ';
          if (can('restaurants.update') || can('branches.update')) {
            branchActions += '<button class="btn btn-sm btn-outline-primary restaurant-edit" data-id="' + branch.id + '"><i class="bi bi-pencil"></i></button> ';
          }
          if (can('restaurants.delete') || can('branches.delete')) {
            branchActions += '<button class="btn btn-sm btn-outline-danger restaurant-delete" data-id="' + branch.id + '"><i class="bi bi-trash"></i></button>';
          }

          return '<tr class="restaurant-row branch-row" data-id="' + branch.id + '">' +
            '<td><div class="restaurant-title-cell"><span class="restaurant-avatar restaurant-avatar-branch"><i class="bi bi-shop"></i></span><span><span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle me-2">Branch</span><span class="restaurant-name">' + escapeHtml(branch.name) + '</span><div class="small text-secondary">' + escapeHtml(branch.main_code || '') + '</div></span></div></td>' +
            '<td>' + escapeHtml(branch.manager_number || '-') + '</td>' +
            '<td>' + escapeHtml(branch.location || '-') + '</td>' +
            '<td><span class="text-secondary">' + escapeHtml(brand ? ('Under ' + brand.name) : 'Branch') + '</span></td>' +
            '<td class="text-end">' + branchActions + '</td>' +
          '</tr>';
        }).join('') || '<tr><td colspan="5" class="text-center text-secondary py-4">No branches found.</td></tr>');

        return;
      }

      body.innerHTML = roots.map(function (row) {
        var branches = branchesByParent[row.id] || [];
        var brandOwners = restaurantOwners(row.id);
        var hasBranchManagement = Number(row.branch_management_enabled || 0) === 1;
        var status = hasBranchManagement
          ? '<span class="badge bg-primary-subtle text-primary border border-primary-subtle">Branch management</span>'
          : '<span class="badge bg-success-subtle text-success border border-success-subtle">Only one branch</span>';
        var expand = '<button class="btn btn-sm btn-outline-secondary branch-toggle" type="button" data-id="' + row.id + '" aria-expanded="false" title="Show details"><i class="bi bi-chevron-down"></i></button> ';
        var enterLabel = hasBranchManagement ? 'Join brand' : 'Join to restaurant';
        var actions = '';
        if (isSuperAdmin && can('restaurants.create')) {
          actions += '<button class="btn btn-sm btn-outline-primary restaurant-add-owner" data-id="' + row.id + '"><i class="bi bi-person-plus"></i> Add owner</button> ';
        }
        if (hasBranchManagement && (can('restaurants.create') || can('branches.create'))) {
          actions += '<button class="btn btn-sm btn-outline-primary restaurant-add-branch" data-id="' + row.id + '"><i class="bi bi-plus-lg"></i> Add branch</button> ';
        }
        actions += '<button class="btn btn-sm btn-outline-success restaurant-enter" data-id="' + row.id + '"><i class="bi bi-box-arrow-in-right"></i> ' + enterLabel + '</button> ';
        if (can('restaurants.update')) {
          actions += '<button class="btn btn-sm btn-outline-primary restaurant-edit" data-id="' + row.id + '"><i class="bi bi-pencil"></i></button> ';
        }
        if (can('restaurants.delete')) {
          actions += '<button class="btn btn-sm btn-outline-danger restaurant-delete" data-id="' + row.id + '"><i class="bi bi-trash"></i></button>';
        }

        return '<tr class="restaurant-parent-row" data-id="' + row.id + '" data-accordion-id="' + row.id + '" tabindex="0">' +
          '<td class="fw-semibold"><div class="restaurant-title-cell">' + expand + '<span class="restaurant-avatar"><i class="bi bi-building"></i></span><span><span class="restaurant-name">' + escapeHtml(row.name) + '</span><div class="small text-secondary">' + escapeHtml(row.main_code || '') + '</div></span></div></td>' +
          '<td>' + renderOwnerManagerSummary(row, brandOwners) + '</td>' +
          '<td>' + status + '</td>' +
          '<td>' + (hasBranchManagement ? escapeHtml(branches.length + ' / ' + Number(row.branch_limit || 0)) : '<span class="text-secondary">1 / 1</span>') + '</td>' +
          '<td class="text-end">' + actions + '</td>' +
        '</tr>' +
        renderRestaurantDropdown(row, branches, hasBranchManagement);
      }).join('') || '<tr><td colspan="5" class="text-center text-secondary py-4">No restaurants found.</td></tr>';
    }

    function renderRestaurantDropdown(row, branches, hasBranchManagement) {
      var html = '';
      var groupClass = 'd-none branch-of-' + row.id;

      var branchRows = branches.map(function (branch) {
        var branchActions = '<button class="btn btn-sm btn-outline-success restaurant-enter" data-id="' + branch.id + '"><i class="bi bi-box-arrow-in-right"></i></button> ';
        if (can('restaurants.update') || can('branches.update')) {
          branchActions += '<button class="btn btn-sm btn-outline-primary restaurant-edit" data-id="' + branch.id + '"><i class="bi bi-pencil"></i></button> ';
        }
        if (can('restaurants.delete') || can('branches.delete')) {
          branchActions += '<button class="btn btn-sm btn-outline-danger restaurant-delete" data-id="' + branch.id + '"><i class="bi bi-trash"></i></button>';
        }
        return '<tr class="restaurant-row branch-row ' + groupClass + '" data-id="' + branch.id + '">' +
          '<td><div class="restaurant-title-cell"><span class="branch-indent"></span><span class="restaurant-avatar restaurant-avatar-branch"><i class="bi bi-shop"></i></span><span><span class="restaurant-name">' + escapeHtml(branch.name) + '</span><div class="small text-secondary">' + escapeHtml(branch.main_code || '') + '</div></span></div></td>' +
          '<td>' + escapeHtml(branch.manager_number || '-') + '</td>' +
          '<td>' + escapeHtml(branch.location || '-') + '</td>' +
          '<td><span class="text-secondary">Under ' + escapeHtml(row.name) + '</span></td>' +
          '<td class="text-end">' + branchActions + '</td>' +
        '</tr>';
      }).join('');

      var brandOwners = restaurantOwners(row.id);

      var ownerRows = brandOwners.map(function (owner) {
        var ownerActions = '';
        if (isSuperAdmin && can('restaurants.update')) {
          ownerActions += '<button class="btn btn-sm btn-outline-primary restaurant-owner-edit" data-id="' + owner.id + '"><i class="bi bi-pencil"></i></button> ';
          if (isSuperAdmin && can('restaurants.delete')) ownerActions += '<button class="btn btn-sm btn-outline-danger restaurant-owner-delete" data-id="' + owner.id + '"><i class="bi bi-trash"></i></button>';
        }
        var pfp = owner.pfp
          ? '<img src="' + escapeHtml(owner.pfp) + '" alt="" class="owner-thumb">'
          : '<span class="owner-thumb owner-thumb-placeholder"><i class="bi bi-person"></i></span>';
        return '<tr class="restaurant-row owner-row ' + groupClass + '" data-owner-id="' + owner.id + '">' +
          '<td><div class="restaurant-title-cell"><span class="branch-indent"></span>' + pfp + '<span><span class="fw-semibold">' + escapeHtml(owner.name) + '</span>' +
            '<div class="small text-secondary">' + escapeHtml(owner.email || '') + '</div></span></div></td>' +
          '<td>' + escapeHtml(owner.phone || '-') + '</td>' +
          '<td><span class="badge bg-dark-subtle text-dark border border-secondary-subtle">Owner access</span></td>' +
          '<td><span class="text-secondary">' + escapeHtml(row.name) + '</span></td>' +
          '<td class="text-end">' + ownerActions + '</td>' +
        '</tr>';
      }).join('');

      if (hasBranchManagement) {
        html += '<tr class="restaurant-row group-header ' + groupClass + '"><td colspan="5"><i class="bi bi-collection me-1"></i> Branches</td></tr>';
        html += branchRows || '<tr class="restaurant-row dropdown-empty-row ' + groupClass + '"><td colspan="5" class="text-secondary">No branches.</td></tr>';
      }
      html += '<tr class="restaurant-row group-header ' + groupClass + '"><td colspan="5"><i class="bi bi-people me-1"></i> Owners</td></tr>';
      html += ownerRows || '<tr class="restaurant-row dropdown-empty-row ' + groupClass + '"><td colspan="5" class="text-secondary">No owners.</td></tr>';

      return html;
    }

    function restaurantOwners(restaurantId) {
      return owners.filter(function (person) {
        return isOwnerStaff(person, restaurantId);
      });
    }

    function renderOwnerManagerSummary(row, brandOwners) {
      if (brandOwners.length > 0) {
        var visibleOwners = brandOwners.slice(0, 2).map(function (owner) {
          return escapeHtml(owner.name || owner.username || ('Owner #' + owner.id));
        }).join(', ');
        var extraCount = brandOwners.length - 2;
        var extraLabel = extraCount > 0 ? ' +' + extraCount : '';
        var contact = brandOwners[0].phone || brandOwners[0].email || row.manager_number || '';

        return '<div class="restaurant-contact fw-semibold">' + visibleOwners + escapeHtml(extraLabel) + '</div>' +
          '<div class="small text-secondary">' + escapeHtml(contact || '-') + '</div>';
      }

      return '<span class="restaurant-contact">' + escapeHtml(row.manager_number || '-') + '</span>';
    }

    function ownerStaffRequestPath() {
      return '/staff?restaurant_id=';
    }

    function loadOwners() {
      if (!isSuperAdmin) {
        owners = [];
        return Promise.resolve();
      }

      return request(ownerStaffRequestPath()).then(function (payload) {
        var staff = payload.data || [];
        owners = staff.filter(function (person) {
          return Number((person || {}).branch_id || 0) <= 0
            && (
              Number((person || {}).is_owner || 0) === 1
              || String((person || {}).role || '').toLowerCase() === 'owner'
            );
        });
      }).catch(function () {
        owners = [];
      });
    }

    function toggleRestaurantAccordion(restaurantId) {
      document.querySelectorAll('.branch-of-' + restaurantId).forEach(function (branchRow) {
        branchRow.classList.toggle('d-none');
      });
      var button = body.querySelector('.branch-toggle[data-id="' + restaurantId + '"]');
      if (button) {
        var expanded = button.getAttribute('aria-expanded') === 'true';
        button.setAttribute('aria-expanded', expanded ? 'false' : 'true');
      }
      var parent = body.querySelector('.restaurant-parent-row[data-id="' + restaurantId + '"]');
      if (parent) parent.classList.toggle('restaurant-row-open');
    }

    function isRestaurantRowAction(target) {
      return !!target.closest('button, a, input, select, textarea, label, .dropdown-menu');
    }

    function loadRestaurants() {
      if (isBranchBrandContext) {
        Promise.all([
          request('/restaurants'),
          loadOwners()
        ]).then(function (responses) {
          var payload = responses[0];
          rows = payload.data || [];
          renderRestaurants();
        }).catch(function (error) {
          body.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">' + escapeHtml(error.message || 'Unable to load branches.') + '</td></tr>';
        });
        return;
      }

      Promise.all([
        request('/restaurants'),
        loadOwners()
      ]).then(function (responses) {
        rows = responses[0].data || [];
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
      var addOwner = event.target.closest('.restaurant-add-owner');
      var ownerEdit = event.target.closest('.restaurant-owner-edit');
      var ownerDelete = event.target.closest('.restaurant-owner-delete');
      var enter = event.target.closest('.restaurant-enter');
      var edit = event.target.closest('.restaurant-edit');
      var del = event.target.closest('.restaurant-delete');
      var rowEl = event.target.closest('.restaurant-row');
      var id = (toggle || addBranch || addOwner || ownerEdit || ownerDelete || enter || edit || del || rowEl || {}).dataset ? (toggle || addBranch || addOwner || ownerEdit || ownerDelete || enter || edit || del || rowEl).dataset.id : null;
      var row = rows.find(function (item) { return String(item.id) === String(id); });
      var owner = ownerEdit || ownerDelete
        ? owners.find(function (item) { return String(item.id) === String(id); })
        : null;

      if (toggle) {
        toggleRestaurantAccordion(toggle.dataset.id);
        return;
      }

      if (rowEl && rowEl.classList.contains('restaurant-parent-row') && !isRestaurantRowAction(event.target)) {
        toggleRestaurantAccordion(rowEl.dataset.accordionId || rowEl.dataset.id);
        return;
      }

      if (addBranch && row) {
        fillRestaurant(null, row.id);
        if (modal) modal.show();
        return;
      }

      if (addOwner && row) {
        openOwnerModal(row.id);
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

      if (ownerDelete && owner && confirm('Delete this owner? This cannot be undone.')) {
        request('/staff/' + owner.id, { method: 'DELETE' }).then(loadRestaurants).catch(function (error) {
          showRestaurantError(error.message || 'Unable to delete owner.');
        });
      }

      if (ownerEdit && owner) {
        window.location.href = '?page=staff&restaurant_id=' + encodeURIComponent(owner.restaurant_id || '') + '&focus_employee=' + encodeURIComponent(owner.id);
      }
    });

    body.addEventListener('keydown', function (event) {
      if (event.key !== 'Enter' && event.key !== ' ') return;
      var rowEl = event.target.closest('.restaurant-parent-row');
      if (!rowEl || isRestaurantRowAction(event.target)) return;
      event.preventDefault();
      toggleRestaurantAccordion(rowEl.dataset.accordionId || rowEl.dataset.id);
    });

    body.addEventListener('dblclick', function (event) {
      var rowEl = event.target.closest('.restaurant-row');
      if (!rowEl) return;
      if (isRestaurantRowAction(event.target) || !rowEl.classList.contains('branch-row')) return;
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

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      initRestaurants();
    });
  } else {
    initRestaurants();
  }
})();

