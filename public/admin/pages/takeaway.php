<?php
/** @var array $admin_context Injected by public/admin/view.php before include. */
$restaurantId = (int) ($admin_context['active_restaurant_id'] ?? 0);
$canUpdateOrders = admin_can($admin_context, 'orders.update');
?>
<script>
  window.TAKEAWAY_PAGE = <?= json_encode([
      'restaurant_id' => $restaurantId,
      'can_update' => $canUpdateOrders,
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>

<div class="card mb-3">
  <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
      <span>Takeaway Orders</span>
      <small class="d-block text-secondary" id="takeawayShowing">Live pickup orders</small>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <input class="form-control form-control-sm table-search" id="takeawaySearch" placeholder="Search order...">
      <select class="form-select form-select-sm w-auto" id="takeawayStatusFilter">
        <option value="">All status</option>
        <option value="waiting">Waiting</option>
        <option value="finished">Finished</option>
        <option value="canceled">Canceled</option>
      </select>
      <select class="form-select form-select-sm w-auto" id="takeawayPaymentFilter">
        <option value="unpaid">Unpaid only</option>
        <option value="">All payments</option>
        <option value="paid">Paid only</option>
      </select>
      <select class="form-select form-select-sm w-auto" id="takeawaySortFilter">
        <option value="newest">Newest</option>
        <option value="oldest">Oldest</option>
      </select>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>Order</th>
          <th>Items</th>
          <th>Status</th>
          <th>Payment</th>
          <th>Total</th>
          <th>Created</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody id="takeawayOrdersBody">
        <tr><td colspan="7" class="text-center text-secondary py-4">Loading takeaway orders...</td></tr>
      </tbody>
    </table>
  </div>
</div>

<div class="modal fade" id="takeawayStatusModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content admin-form-modal" id="takeawayStatusForm">
      <div class="modal-header">
        <div>
          <h1 class="modal-title fs-6" id="takeawayStatusTitle">Update Status</h1>
          <div class="modal-subtitle" id="takeawayStatusMeta">Takeaway order</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger d-none" id="takeawayStatusError"></div>
        <input type="hidden" id="takeawayStatusOrderId">
        <label class="form-label" for="takeawayOrderStatus">Status</label>
        <select class="form-select" id="takeawayOrderStatus" <?= $canUpdateOrders ? '' : 'disabled'; ?>>
          <option value="waiting">Waiting</option>
          <option value="finished">Finished</option>
          <option value="canceled">Canceled</option>
        </select>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
        <?php if ($canUpdateOrders): ?>
          <button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> Update Status</button>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="takeawayPaymentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content admin-form-modal" id="takeawayPaymentForm">
      <div class="modal-header">
        <div>
          <h1 class="modal-title fs-6" id="takeawayPaymentTitle">Payment</h1>
          <div class="modal-subtitle">Collect takeaway payment.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger d-none" id="takeawayPaymentError"></div>
        <div class="alert alert-info d-none" id="takeawayPaymentExtra"></div>
        <input type="hidden" id="takeawayPaymentOrderId">
        <div class="payment-total-box mb-3">
          <span>Total order price</span>
          <strong id="takeawayPaymentTotal">0.00</strong>
        </div>
        <label class="form-label" for="takeawayPaymentMethod">Payment method</label>
        <select class="form-select" id="takeawayPaymentMethod" required>
          <option value="cash">Cash</option>
          <option value="credit">Credit card</option>
          <option value="cash_credit">Cash &amp; credit card</option>
        </select>
        <div class="row g-2 mt-2 d-none" id="takeawayMixedPaymentFields">
          <div class="col-6">
            <label class="form-label" for="takeawayPaidCashAmount">Cash amount</label>
            <input class="form-control" id="takeawayPaidCashAmount" type="number" min="0" step="0.01">
          </div>
          <div class="col-6">
            <label class="form-label" for="takeawayPaidCreditAmount">Credit amount</label>
            <input class="form-control" id="takeawayPaidCreditAmount" type="number" min="0" step="0.01">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
        <?php if ($canUpdateOrders): ?>
          <button class="btn btn-primary" type="submit"><i class="bi bi-cash-coin"></i> Paid</button>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  'use strict';

  var page = window.TAKEAWAY_PAGE || {};
  var restaurantId = Number(page.restaurant_id || 0);
  var canUpdate = !!page.can_update;
  var appBase = window.location.pathname.split('/admin')[0] || '';
  var apiBase = appBase + '/api';
  var adminCurrency = window.ADMIN_CURRENCY || {};
  var currencySymbol = adminCurrency.symbol || adminCurrency.code || 'JOD';
  var rows = [];
  var orders = [];
  var loading = false;
  var liveTimer = null;
  var paymentOrder = null;

  var body = document.getElementById('takeawayOrdersBody');
  var showing = document.getElementById('takeawayShowing');
  var search = document.getElementById('takeawaySearch');
  var statusFilter = document.getElementById('takeawayStatusFilter');
  var paymentFilter = document.getElementById('takeawayPaymentFilter');
  var sortFilter = document.getElementById('takeawaySortFilter');
  var statusModalEl = document.getElementById('takeawayStatusModal');
  var statusForm = document.getElementById('takeawayStatusForm');
  var paymentModalEl = document.getElementById('takeawayPaymentModal');
  var paymentForm = document.getElementById('takeawayPaymentForm');
  var paymentMethod = document.getElementById('takeawayPaymentMethod');
  var mixedPaymentFields = document.getElementById('takeawayMixedPaymentFields');
  var paymentExtra = document.getElementById('takeawayPaymentExtra');
  var paymentError = document.getElementById('takeawayPaymentError');

  function request(path, options) {
    return fetch(apiBase + path, Object.assign({
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

  function escapeHtml(value) {
    return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
    });
  }

  function money(value) {
    return Number(value || 0).toFixed(2) + ' ' + currencySymbol;
  }

  function formatDate(value) {
    if (!value) return '-';
    var date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleString();
  }

  function toast(message, icon) {
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

  function showError(message) {
    if (typeof Swal !== 'undefined') {
      Swal.fire({ icon: 'error', title: 'Takeaway', text: message });
      return;
    }
    window.alert(message);
  }

  function statusBadge(status) {
    var normalized = String(status || 'waiting');
    var color = { waiting: 'warning', finished: 'success', canceled: 'danger' }[normalized] || 'secondary';
    var label = { waiting: 'Waiting', finished: 'Finished', canceled: 'Canceled' }[normalized] || normalized;
    return '<span class="badge bg-' + color + '-subtle text-' + color + ' border border-' + color + '-subtle">' + escapeHtml(label) + '</span>';
  }

  function paymentBadge(status) {
    var paid = status === 'paid';
    return '<span class="badge text-bg-' + (paid ? 'success' : 'secondary') + '">' + (paid ? 'Paid' : 'Unpaid') + '</span>';
  }

  function groupOrders(data) {
    var grouped = {};

    data.forEach(function (row) {
      if ((row.order_type || 'table') !== 'takeaway') return;
      var id = row.order_id;
      if (!grouped[id]) {
        grouped[id] = {
          order_id: id,
          status: row.status || 'waiting',
          payment_status: row.payment_status || 'unpaid',
          payment_method: row.payment_method || '',
          created_at: row.created_at,
          order_price: Number(row.order_price || row.price || 0),
          items: [],
          rows: []
        };
      }

      grouped[id].rows.push(row);
      if (row.food_id) {
        grouped[id].items.push({
          name: row.food_name_en || row.food_name_ar || ('Food #' + row.food_id),
          qty: Number(row.qty || 1),
          details: row.details || ''
        });
      }
    });

    return Object.keys(grouped).map(function (key) {
      return grouped[key];
    });
  }

  function orderText(order) {
    return [
      order.order_id,
      order.status,
      order.payment_status,
      order.payment_method,
      order.items.map(function (item) { return item.name; }).join(' ')
    ].join(' ').toLowerCase();
  }

  function filteredOrders() {
    var query = (search.value || '').trim().toLowerCase();
    var status = statusFilter.value;
    var payment = paymentFilter.value;
    var dir = sortFilter.value === 'oldest' ? 1 : -1;

    return orders.filter(function (order) {
      if (status && order.status !== status) return false;
      if (payment && order.payment_status !== payment) return false;
      if (query && orderText(order).indexOf(query) === -1) return false;
      return true;
    }).sort(function (a, b) {
      var at = Date.parse(String(a.created_at || '').replace(' ', 'T')) || 0;
      var bt = Date.parse(String(b.created_at || '').replace(' ', 'T')) || 0;
      if (at !== bt) return (at - bt) * dir;
      return (Number(a.order_id || 0) - Number(b.order_id || 0)) * dir;
    });
  }

  function render() {
    var visible = filteredOrders();
    if (showing) {
      showing.textContent = visible.length + ' visible / ' + orders.length + ' takeaway orders';
    }

    body.innerHTML = visible.map(function (order) {
      var items = order.items.slice(0, 3).map(function (item) {
        return escapeHtml(item.qty + 'x ' + item.name) + (item.details ? '<br><small class="text-secondary">Note: ' + escapeHtml(item.details) + '</small>' : '');
      }).join('<br>');
      if (order.items.length > 3) {
        items += '<br><small class="text-secondary">+' + (order.items.length - 3) + ' more</small>';
      }

      var actions = '';
      if (canUpdate) {
        actions += '<button class="btn btn-sm btn-outline-primary takeaway-status" type="button" data-id="' + escapeHtml(order.order_id) + '"><i class="bi bi-arrow-repeat"></i> Update status</button> ';
        if (order.status === 'finished' && order.payment_status !== 'paid') {
          actions += '<button class="btn btn-sm btn-outline-success takeaway-payment" type="button" data-id="' + escapeHtml(order.order_id) + '"><i class="bi bi-cash-coin"></i> Payment</button>';
        }
      }

      return '<tr data-order-id="' + escapeHtml(order.order_id) + '">' +
        '<td class="fw-semibold">#' + escapeHtml(order.order_id) + '</td>' +
        '<td class="small">' + (items || '-') + '</td>' +
        '<td>' + statusBadge(order.status) + '</td>' +
        '<td>' + paymentBadge(order.payment_status) + '</td>' +
        '<td>' + money(order.order_price) + '</td>' +
        '<td>' + escapeHtml(formatDate(order.created_at)) + '</td>' +
        '<td class="text-end">' + (actions || '<span class="text-secondary">-</span>') + '</td>' +
      '</tr>';
    }).join('') || '<tr><td colspan="7" class="text-center text-secondary py-4">No takeaway orders match the filters.</td></tr>';
  }

  function load(silent) {
    if (loading) return Promise.resolve();
    loading = true;
    if (!silent) {
      body.innerHTML = '<tr><td colspan="7" class="text-center text-secondary py-4">Loading takeaway orders...</td></tr>';
    }

    return request('/orders?restaurant_id=' + encodeURIComponent(restaurantId) + '&order_type=takeaway').then(function (payload) {
      rows = payload.data || [];
      orders = groupOrders(rows);
      render();
    }).catch(function (error) {
      if (!silent) {
        body.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">' + escapeHtml(error.message || 'Unable to load takeaway orders.') + '</td></tr>';
      }
    }).finally(function () {
      loading = false;
    });
  }

  function findOrder(id) {
    return orders.find(function (order) {
      return Number(order.order_id) === Number(id);
    });
  }

  function openStatus(order) {
    if (!order) return;
    document.getElementById('takeawayStatusOrderId').value = order.order_id;
    document.getElementById('takeawayStatusTitle').textContent = 'Update Status - Order #' + order.order_id;
    document.getElementById('takeawayStatusMeta').textContent = money(order.order_price) + ' / ' + order.items.length + ' item(s)';
    document.getElementById('takeawayOrderStatus').value = order.status || 'waiting';
    document.getElementById('takeawayStatusError').classList.add('d-none');
    bootstrap.Modal.getOrCreateInstance(statusModalEl).show();
  }

  function openPayment(order) {
    if (!order || order.status !== 'finished') return;
    paymentOrder = order;
    paymentForm.reset();
    mixedPaymentFields.classList.add('d-none');
    paymentExtra.classList.add('d-none');
    paymentError.classList.add('d-none');
    document.getElementById('takeawayPaymentOrderId').value = order.order_id;
    document.getElementById('takeawayPaymentTitle').textContent = 'Payment - Order #' + order.order_id;
    document.getElementById('takeawayPaymentTotal').textContent = money(order.order_price);
    bootstrap.Modal.getOrCreateInstance(paymentModalEl).show();
  }

  function paymentPayload() {
    var method = paymentMethod.value;
    var payload = { payment_method: method };
    if (method === 'cash_credit') {
      payload.total_paid_cash = Number(document.getElementById('takeawayPaidCashAmount').value || 0);
      payload.total_paid_credit = Number(document.getElementById('takeawayPaidCreditAmount').value || 0);
    }
    return payload;
  }

  function updatePaymentExtra() {
    if (!paymentOrder || paymentMethod.value !== 'cash_credit') {
      paymentExtra.classList.add('d-none');
      return;
    }

    var cash = Number(document.getElementById('takeawayPaidCashAmount').value || 0);
    var credit = Number(document.getElementById('takeawayPaidCreditAmount').value || 0);
    var extra = (cash + credit) - Number(paymentOrder.order_price || 0);
    if (extra > 0) {
      paymentExtra.textContent = 'An additional charge was applied to the customer: ' + money(extra);
      paymentExtra.classList.remove('d-none');
    } else {
      paymentExtra.classList.add('d-none');
    }
  }

  [search, statusFilter, paymentFilter, sortFilter].forEach(function (input) {
    if (!input) return;
    input.addEventListener('input', render);
    input.addEventListener('change', render);
  });

  body.addEventListener('click', function (event) {
    var statusButton = event.target.closest('.takeaway-status');
    var paymentButton = event.target.closest('.takeaway-payment');
    if (statusButton) openStatus(findOrder(statusButton.dataset.id));
    if (paymentButton) openPayment(findOrder(paymentButton.dataset.id));
  });

  if (statusForm) {
    statusForm.addEventListener('submit', function (event) {
      event.preventDefault();
      var orderId = Number(document.getElementById('takeawayStatusOrderId').value || 0);
      var status = document.getElementById('takeawayOrderStatus').value;
      var errorBox = document.getElementById('takeawayStatusError');
      errorBox.classList.add('d-none');

      request('/orders/' + orderId + '/takeaway-status', {
        method: 'PATCH',
        body: JSON.stringify({ status: status })
      }).then(function () {
        bootstrap.Modal.getOrCreateInstance(statusModalEl).hide();
        toast('Takeaway status updated');
        return load(true);
      }).catch(function (error) {
        errorBox.textContent = error.errors ? Object.values(error.errors).join(' ') : (error.message || 'Unable to update status.');
        errorBox.classList.remove('d-none');
      });
    });
  }

  if (paymentMethod) {
    paymentMethod.addEventListener('change', function () {
      mixedPaymentFields.classList.toggle('d-none', paymentMethod.value !== 'cash_credit');
      updatePaymentExtra();
    });
  }

  ['takeawayPaidCashAmount', 'takeawayPaidCreditAmount'].forEach(function (id) {
    var input = document.getElementById(id);
    if (input) input.addEventListener('input', updatePaymentExtra);
  });

  if (paymentForm) {
    paymentForm.addEventListener('submit', function (event) {
      event.preventDefault();
      if (!paymentOrder) return;

      var payload = paymentPayload();
      paymentError.classList.add('d-none');
      if (
        payload.payment_method === 'cash_credit'
        && (Number(payload.total_paid_cash || 0) + Number(payload.total_paid_credit || 0)) < Number(paymentOrder.order_price || 0)
      ) {
        paymentError.textContent = 'Paid total must be higher than or equal to the order total.';
        paymentError.classList.remove('d-none');
        return;
      }

      request('/orders/' + paymentOrder.order_id + '/takeaway-payment', {
        method: 'PATCH',
        body: JSON.stringify(payload)
      }).then(function (payload) {
        bootstrap.Modal.getOrCreateInstance(paymentModalEl).hide();
        if (payload.data && Number(payload.data.extra_paid || 0) > 0) {
          toast('Extra paid: ' + money(payload.data.extra_paid));
        } else {
          toast('Takeaway payment saved');
        }
        paymentOrder = null;
        return load(true);
      }).catch(function (error) {
        paymentError.textContent = error.errors ? Object.values(error.errors).join(' ') : (error.message || 'Unable to save payment.');
        paymentError.classList.remove('d-none');
      });
    });
  }

  if (!restaurantId) {
    showError('No active restaurant selected.');
    return;
  }

  load();
  liveTimer = window.setInterval(function () {
    if (document.hidden || statusModalEl.classList.contains('show') || paymentModalEl.classList.contains('show')) return;
    load(true);
  }, 1000);

  window.addEventListener('beforeunload', function () {
    if (liveTimer) window.clearInterval(liveTimer);
  });
})();
</script>
