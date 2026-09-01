/* global Chart */

( function () {
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

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      initOrdersPage();
    });
  } else {
    initOrdersPage();
  }
})();

