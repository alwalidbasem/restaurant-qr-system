( function () {
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
      AdminUI.showError('discountFormAlert', error, 'Unable to save discount.');
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

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      initDiscounts();
    });
  } else {
    initDiscounts();
  }
})();

