/* global Chart */

( function () {
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
    ['restaurants', 'restaurant', 'staff', 'managers', 'inventory', 'orders', 'foods', 'categories', 'discounts', 'tables'].forEach(function (group) {
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
      AdminUI.showAlert(alertBox, message);
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
      document.getElementById('staffDetails').value = '';
      if (document.getElementById('staffHiddenDetails')) document.getElementById('staffHiddenDetails').value = '';
      setImagePreview(staffPfpPreview, '', 'bi bi-person');
      applyPermissionString('');
      if (managersOnly) {
        applyManagerPermissionDefaults();
        applyManagerScope('all');
      }
      setPermissionInputsLocked(false);
      if (alertBox) alertBox.classList.add('d-none');
      if (activeRestaurantId) document.getElementById('staffRestaurantId').value = activeRestaurantId;
    }

    function isManagerStaff(person) {
      return Number((person || {}).is_owner || 0) !== 1 && isManagerScopedStaff(person);
    }

    function isVisibleOnManagersPage(person) {
      return isManagerStaff(person)
        || (Number((adminContext.employee || {}).is_owner || 0) === 1 && Number((person || {}).is_owner || 0) === 1);
    }

    function canModifyStaffRow(person) {
      if (isSuperAdmin) return true;
      if (Number((person || {}).is_owner || 0) === 1) return false;
      if (Number((adminContext.employee || {}).is_manager || 0) === 1 && Number((person || {}).is_manager || 0) === 1) return false;
      return true;
    }

    function renderStaff(rows) {
      body.innerHTML = rows.map(function (person) {
        var enabled = text(person.permissions).split(',').filter(function (value) { return value.trim() === '1'; }).length;
        var actions = '';
        if (canModifyStaffRow(person) && can('staff.update')) {
          actions += '<button class="btn btn-sm btn-outline-primary staff-edit" type="button" data-id="' + person.id + '"><i class="bi bi-pencil"></i></button> ';
        }
        if (canModifyStaffRow(person) && can('staff.delete')) {
          actions += '<button class="btn btn-sm btn-outline-danger staff-delete" type="button" data-id="' + person.id + '"><i class="bi bi-trash"></i></button>';
        }
        var hiddenCell = isSuperAdmin ? '<td class="small text-secondary">' + escapeHtml(person.hidden_details || '-') + '</td>' : '';

        return '<tr><td class="fw-semibold">' + escapeHtml(person.name) + '</td><td>' +
          escapeHtml(person.username) + '</td><td class="small text-secondary">' + escapeHtml(person.details || person.description || '-') + '</td>' +
          hiddenCell + '<td>' +
          money(person.salary || 0) + '</td><td>' + escapeHtml(person.branch_name || '-') + '</td><td>' +
          enabled + ' enabled</td><td class="text-end">' + (actions || '<span class="text-secondary">-</span>') + '</td></tr>';
      }).join('') || '<tr><td colspan="' + (isSuperAdmin ? '8' : '7') + '" class="text-center text-secondary py-4">No ' + escapeHtml(staffPage.title.toLowerCase()) + ' found.</td></tr>';
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
      request('/staff').then(function (payload) {
        currentStaff = (payload.data || []).filter(function (person) {
          return !managersOnly || isVisibleOnManagersPage(person);
        });
        renderStaff(currentStaff);
      }).catch(function (error) {
        body.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">' + escapeHtml(error.message || ('Unable to load ' + staffPage.title.toLowerCase() + '.')) + '</td></tr>';
      });
    }

    function renderManagerBranches() {
      var list = document.getElementById('managerBranchList');
      if (!list) return;
      list.innerHTML = branchRows.map(function (branch) {
        return '<label class="form-check manager-branch-item">' +
          '<input class="form-check-input manager-branch-check" type="checkbox" value="' + escapeHtml(branch.id) + '">' +
          '<span class="form-check-label">' + escapeHtml(branch.name) + '</span>' +
        '</label>';
      }).join('') || '<div class="text-secondary small">No branches available.</div>';
    }

    function updateManagerSubPermissions() {
      var sub = document.getElementById('managerSubPermissions');
      if (!sub) return;
      var scopeEl = document.querySelector('input[name="managerScope"]:checked');
      var count = document.querySelectorAll('.manager-branch-check:checked').length;
      sub.classList.toggle('d-none', !(scopeEl && scopeEl.value === 'some' && count > 0));
    }

    function applyManagerScope(scope) {
      var warning = document.getElementById('managerScopeWarning');
      var wrap = document.getElementById('managerBranchWrap');
      if (!warning && !wrap) return;
      var isAll = scope === 'all';
      var isSome = scope === 'some';
      if (warning) warning.classList.toggle('d-none', !isAll);
      if (wrap) wrap.classList.toggle('d-none', !isSome);
      updateManagerSubPermissions();
    }

    function setManagerScope(person) {
      if (!managersOnly) return;
      var scope = (person && (person.manager_scope === 'some' || person.manager_scope === 'none')) ? person.manager_scope : 'all';
      var radio = document.querySelector('input[name="managerScope"][value="' + scope + '"]');
      if (radio) radio.checked = true;
      var managed = String((person && (person.allowed_branches || person.managed_branches)) || '').split(',').map(Number).filter(Boolean);
      document.querySelectorAll('.manager-branch-check').forEach(function (c) {
        c.checked = managed.indexOf(Number(c.value)) !== -1;
      });
      applyManagerScope(scope);
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
        renderManagerBranches();
      }).catch(function () {
        currentRestaurant = null;
        branchRows = [];
        renderBranchSelect();
        renderManagerBranches();
      });
    }

    if (form) {
      var addBtn = document.getElementById('staffAddBtn');
      if (addBtn) {
        addBtn.addEventListener('click', function () {
          clearForm();
        });
      }

      if (managersOnly) {
        var managerBranchListEl = document.getElementById('managerBranchList');
        if (managerBranchListEl) {
          managerBranchListEl.addEventListener('change', function (event) {
            if (event.target && event.target.classList.contains('manager-branch-check')) {
              updateManagerSubPermissions();
            }
          });
        }
        document.querySelectorAll('input[name="managerScope"]').forEach(function (radio) {
          radio.addEventListener('change', function () {
            applyManagerScope(this.value);
          });
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
        'dashboard.get': true,
        'branches_dashboard.get': true,
        'branches_logs.get': true,
        'branches.get': true,
        'branches.create': true,
        'branches.update': true,
        'branches.delete': true
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
        document.getElementById('staffSalary').value = Number(person.salary || 0).toFixed(2);
        document.getElementById('staffRestaurantId').value = person.restaurant_id || '';
        renderBranchSelect();
        document.getElementById('staffBranchId').value = person.branch_id || '';
        document.getElementById('staffPfp').value = person.pfp || '';
        if (staffPfpFile) staffPfpFile.value = '';
        setImagePreview(staffPfpPreview, person.pfp || '', 'bi bi-person');
        document.getElementById('staffDetails').value = person.details || person.description || '';
        if (document.getElementById('staffHiddenDetails')) document.getElementById('staffHiddenDetails').value = person.hidden_details || '';
        applyPermissionString(person.permissions);
        setManagerScope(person);
        setPermissionInputsLocked(Number(person.id || 0) === currentEmployeeId);
        document.getElementById('staffFormTitle').textContent = 'Edit ' + staffPage.singular;
        if (staffModal) staffModal.show();
      }

      if (del) {
        swalConfirm('Delete this staff member?', 'Delete staff member').then(function (confirmed) {
          if (!confirmed) return;
          request('/staff/' + del.dataset.id, { method: 'DELETE' }).then(function () {
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
          restaurant_id: Number(document.getElementById('staffRestaurantId').value || activeRestaurantId),
          salary: Number(document.getElementById('staffSalary').value || 0),
          branch_id: document.getElementById('staffBranchId').value ? Number(document.getElementById('staffBranchId').value) : null,
          pfp: document.getElementById('staffPfp').value.trim(),
          details: document.getElementById('staffDetails').value.trim(),
          permissions: permissionsToString(),
          is_superadmin: 0,
          is_owner: 0,
          is_manager: managersOnly ? 1 : 0,
          is_employee: managersOnly ? 0 : 1
        };
        if (document.getElementById('staffHiddenDetails')) {
          payload.hidden_details = document.getElementById('staffHiddenDetails').value.trim();
        }

        if (managersOnly) {
          var managerScopeEl = document.querySelector('input[name="managerScope"]:checked');
          payload.manager_scope = managerScopeEl ? managerScopeEl.value : 'all';
          payload.allowed_branches = payload.manager_scope === 'all' ? 'all' : [];
          payload.managed_branches = payload.allowed_branches;
          if (payload.manager_scope === 'some') {
            Array.prototype.forEach.call(document.querySelectorAll('.manager-branch-check:checked'), function (c) {
              payload.allowed_branches.push(Number(c.value));
            });
            payload.managed_branches = payload.allowed_branches;
          }
          if (payload.manager_scope === 'none') {
            payload.allowed_branches = '';
            payload.managed_branches = '';
          }
        }

        if (!id || password !== '') payload.password = password;

        var file = staffPfpFile && staffPfpFile.files ? staffPfpFile.files[0] : null;
        uploadImage(file, 'staff').then(function (path) {
          if (path) payload.pfp = path;

          return request('/staff' + (id ? '/' + id : ''), {
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
        return [person.name, person.username, person.details, person.hidden_details].join(' ').toLowerCase().includes(term);
      }));
    });

    clearForm();
    loadBranches();
    if (form && activeRestaurantId) document.getElementById('staffRestaurantId').value = activeRestaurantId;
    loadStaff();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      initStaff();
    });
  } else {
    initStaff();
  }
})();

