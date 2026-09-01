<?php
/** @var array $admin_context Injected by public/admin/view.php before include. */
$canCreateTables = admin_can($admin_context, 'tables.create');
$canUpdateTables = admin_can($admin_context, 'tables.update');
$canDeleteTables = admin_can($admin_context, 'tables.delete');
$restaurantId = (int) ($admin_context['active_restaurant_id'] ?? 0);
?>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
  window.TABLE_ICON_URL = <?= json_encode(app_base_url() . '/storage/icons/restaurant-table-icon.svg', JSON_UNESCAPED_SLASHES); ?>;
  window.TABLES_PAGE = <?= json_encode([
      'restaurant_id' => $restaurantId,
      'can_create' => $canCreateTables,
      'can_update' => $canUpdateTables,
      'can_delete' => $canDeleteTables,
  ], JSON_UNESCAPED_SLASHES); ?>;
</script>

<div class="card mb-3">
  <div class="card-body">
    <div class="tables-toolbar">
      <div>
        <div class="section-title mb-0">Tables</div>
        <small class="text-secondary">Select a floor, arrange tables, then save and print QR codes.</small>
      </div>
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <select class="form-select form-select-sm floor-select" id="floorSelect" aria-label="Floor"></select>
        <?php if ($canCreateTables): ?>
          <button class="btn btn-outline-secondary btn-sm" id="addFloorBtn" type="button">
            <i class="bi bi-layers"></i> Floor
          </button>
          <button class="btn btn-outline-primary btn-sm" id="newTableBtn" type="button">
            <i class="bi bi-plus-lg"></i> Table
          </button>
        <?php endif; ?>
        <?php if ($canUpdateTables || $canCreateTables): ?>
          <button class="btn btn-primary btn-sm" id="savePlanBtn" type="button">
            <i class="bi bi-check-lg"></i> Save
          </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body p-2 px-3">
    <div class="floor-plan wall" id="floorPlan">
      <div class="fp-hint"><i class="bi bi-arrows-move text-primary-custom"></i> Drag tables on the selected floor</div>
      <div class="fp-empty" id="floorEmpty">No tables on this floor.</div>
      <div class="fp-context" id="fpContext" role="menu"></div>
    </div>
    <div class="fp-toast" id="fpToast"><i class="bi bi-check-circle-fill"></i><span id="fpToastText">Saved</span></div>
  </div>
</div>

<div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title fs-6">Table QR Codes</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="qr-print-grid" id="qrPrintGrid"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Close</button>
        <button class="btn btn-primary" type="button" id="printQrBtn"><i class="bi bi-printer"></i> Print</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="tablePaymentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" id="tablePaymentForm">
      <div class="modal-header">
        <h1 class="modal-title fs-6" id="tablePaymentTitle">Collect Payment</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="paymentTableId">
        <div class="payment-total-box mb-3">
          <span>Total order price</span>
          <strong id="paymentOrderTotal">$0.00</strong>
        </div>
        <label class="form-label" for="paymentMethod">Payment method</label>
        <select class="form-select" id="paymentMethod" required>
          <option value="cash">Cash</option>
          <option value="credit">Credit card</option>
          <option value="cash_credit">Cash &amp; credit card</option>
        </select>
        <div class="row g-2 mt-2 d-none" id="mixedPaymentFields">
          <div class="col-6">
            <label class="form-label" for="paidCashAmount">Cash amount</label>
            <input class="form-control" id="paidCashAmount" type="number" min="0" step="0.01">
          </div>
          <div class="col-6">
            <label class="form-label" for="paidCreditAmount">Credit amount</label>
            <input class="form-control" id="paidCreditAmount" type="number" min="0" step="0.01">
          </div>
        </div>
        <div class="alert alert-info d-none mt-3 mb-0" id="paymentExtraAlert"></div>
        <div class="alert alert-danger d-none mt-3 mb-0" id="paymentErrorAlert"></div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit"><i class="bi bi-cash-coin"></i> Paid</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  'use strict';

  var page = window.TABLES_PAGE || {};
  var restaurantId = Number(page.restaurant_id || 0);
  var canCreate = !!page.can_create;
  var canUpdate = !!page.can_update;
  var canDelete = !!page.can_delete;
  var appBase = window.location.pathname.split('/admin')[0] || '';
  var apiBase = appBase + '/api';
  var webBase = window.location.origin + appBase + '/';
  var adminCurrency = window.ADMIN_CURRENCY || {};
  var currencySymbol = adminCurrency.symbol || adminCurrency.code || 'JOD';

  var canvas = document.getElementById('floorPlan');
  var emptyState = document.getElementById('floorEmpty');
  var floorSelect = document.getElementById('floorSelect');
  var addFloorBtn = document.getElementById('addFloorBtn');
  var newTableBtn = document.getElementById('newTableBtn');
  var savePlanBtn = document.getElementById('savePlanBtn');
  var context = document.getElementById('fpContext');
  var toast = document.getElementById('fpToast');
  var toastText = document.getElementById('fpToastText');
  var qrModalEl = document.getElementById('qrModal');
  var qrGrid = document.getElementById('qrPrintGrid');
  var printQrBtn = document.getElementById('printQrBtn');
  var paymentModalEl = document.getElementById('tablePaymentModal');
  var paymentForm = document.getElementById('tablePaymentForm');
  var paymentMethod = document.getElementById('paymentMethod');
  var mixedPaymentFields = document.getElementById('mixedPaymentFields');
  var paymentExtraAlert = document.getElementById('paymentExtraAlert');
  var paymentErrorAlert = document.getElementById('paymentErrorAlert');
  var paymentTable = null;
  var paymentOrderTotal = 0;

  var GRID_SIZE = 26;
  var TABLE_W = GRID_SIZE * 4;
  var TABLE_H = GRID_SIZE * 5;
  var tables = [];
  var restaurant = null;
  var selectedFloor = 1;
  var tempId = -1;
  var tableDragging = false;
  var tablesLiveLoading = false;
  var tablesLiveTimer = null;
  var statusMeta = {
    free: { label: 'Free', cls: 'free', icon: 'bi-check2-circle' },
    waiting_order: { label: 'Waiting order', cls: 'waiting', icon: 'bi-clipboard' },
    order_done: { label: 'Order done', cls: 'done', icon: 'bi-check2-square' }
  };

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

  function showToast(message) {
    toastText.textContent = message;
    toast.classList.add('show');
    clearTimeout(showToast.timer);
    showToast.timer = setTimeout(function () { toast.classList.remove('show'); }, 2200);
  }

  function showError(message) {
    if (typeof Swal !== 'undefined') {
      Swal.fire({ icon: 'error', title: 'Tables', text: message });
      return;
    }

    window.alert(message);
  }

  function confirmAction(message) {
    if (typeof Swal === 'undefined') {
      return Promise.resolve(window.confirm(message));
    }

    return Swal.fire({
      title: 'Are you sure?',
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

  function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
  }

  function snapToGrid(value) {
    return Math.round(value / GRID_SIZE) * GRID_SIZE;
  }

  function parsePosition(table) {
    if (table.position && typeof table.position === 'string') {
      try {
        table.position = JSON.parse(table.position);
      } catch (error) {
        table.position = null;
      }
    }

    if (!table.position || typeof table.position !== 'object') {
      table.position = { x: 24, y: 64 };
    }

    table.position.x = snapToGrid(Number(table.position.x || 24));
    table.position.y = snapToGrid(Number(table.position.y || 64));

    return table;
  }

  function currentTables() {
    return tables.filter(function (table) {
      return Number(table.table_floor || 1) === selectedFloor;
    });
  }

  function tableRect(table, x, y) {
    return {
      left: x,
      top: y,
      right: x + TABLE_W,
      bottom: y + TABLE_H
    };
  }

  function rectsOverlap(a, b) {
    return a.left < b.right
      && a.right > b.left
      && a.top < b.bottom
      && a.bottom > b.top;
  }

  function positionIsFree(table, x, y) {
    var rect = tableRect(table, x, y);

    return currentTables().every(function (other) {
      if (other === table || Number(other.id) === Number(table.id)) return true;

      return !rectsOverlap(rect, tableRect(other, other.position.x, other.position.y));
    });
  }

  function firstFreePosition(preferredX, preferredY, table) {
    var maxX = Math.max(0, canvas.clientWidth - TABLE_W);
    var maxY = Math.max(0, canvas.clientHeight - TABLE_H);
    var startX = clamp(snapToGrid(preferredX), 0, maxX);
    var startY = clamp(snapToGrid(preferredY), 0, maxY);

    if (positionIsFree(table, startX, startY)) {
      return { x: startX, y: startY };
    }

    for (var y = 0; y <= maxY; y += GRID_SIZE) {
      for (var x = 0; x <= maxX; x += GRID_SIZE) {
        if (positionIsFree(table, x, y)) {
          return { x: x, y: y };
        }
      }
    }

    return null;
  }

  function arrangeCurrentFloorTables() {
    var placed = [];
    var maxX = Math.max(0, canvas.clientWidth - TABLE_W);
    var maxY = Math.max(0, canvas.clientHeight - TABLE_H);

    currentTables().forEach(function (table) {
      var startX = clamp(snapToGrid(table.position.x), 0, maxX);
      var startY = clamp(snapToGrid(table.position.y), 0, maxY);
      var currentRect = tableRect(table, startX, startY);
      var overlapsPlaced = placed.some(function (other) {
        return rectsOverlap(currentRect, tableRect(other, other.position.x, other.position.y));
      });

      if (!overlapsPlaced) {
        table.position = { x: startX, y: startY };
        placed.push(table);
        return;
      }

      for (var y = 0; y <= maxY; y += GRID_SIZE) {
        for (var x = 0; x <= maxX; x += GRID_SIZE) {
          var testRect = tableRect(table, x, y);
          var blocked = placed.some(function (other) {
            return rectsOverlap(testRect, tableRect(other, other.position.x, other.position.y));
          });

          if (!blocked) {
            table.position = { x: x, y: y };
            placed.push(table);
            return;
          }
        }
      }

      table.position = { x: startX, y: startY };
      placed.push(table);
    });
  }

  function tableLabel(table) {
    return 'T-' + String(table.table_number).padStart(2, '0');
  }

  function tableUrl(table) {
    return webBase + '?r_code=' + encodeURIComponent(restaurant.main_code || '') + '&t_n=' + encodeURIComponent(table.table_number);
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

  function syncFloors() {
    var floors = Array.from(new Set(tables.map(function (table) {
      return Number(table.table_floor || 1);
    }).concat([selectedFloor, 1]))).filter(function (floor) {
      return floor > 0;
    }).sort(function (a, b) {
      return a - b;
    });

    floorSelect.innerHTML = floors.map(function (floor) {
      return '<option value="' + floor + '">Floor ' + floor + '</option>';
    }).join('');
    floorSelect.value = String(selectedFloor);
  }

  function render() {
    arrangeCurrentFloorTables();

    canvas.querySelectorAll('.table-object').forEach(function (node) {
      node.remove();
    });

    var rows = currentTables();
    emptyState.classList.toggle('show', rows.length === 0);

    rows.forEach(function (table) {
      canvas.appendChild(buildTableNode(table));
    });
  }

  function buildTableNode(table) {
    var position = table.position || { x: 24, y: 64 };
    var status = statusMeta[table.table_status] || statusMeta.free;
    var node = document.createElement('div');
    node.className = 'table-object';
    node.style.left = position.x + 'px';
    node.style.top = position.y + 'px';
    node.innerHTML =
      '<div class="table-num">' + escapeHtml(tableLabel(table)) + '</div>' +
      '<div class="table-icon-wrapper">' +
        '<img class="table-icon" src="' + window.TABLE_ICON_URL + '" alt="' + escapeHtml(tableLabel(table)) + '" draggable="false">' +
      '</div>' +
      '<div class="table-badges">' +
        '<span class="table-badge table-status-tag ' + status.cls + '">' + escapeHtml(status.label) + '</span>' +
      '</div>';

    bindTableNode(node, table);

    return node;
  }

  function bindTableNode(node, table) {
    var active = false;
    var startX = 0;
    var startY = 0;
    var startLeft = 0;
    var startTop = 0;
    var moved = false;

    node.addEventListener('pointerdown', function (event) {
      if (event.button && event.button !== 0) return;

      active = true;
      tableDragging = true;
      moved = false;
      startX = event.clientX;
      startY = event.clientY;
      startLeft = table.position.x;
      startTop = table.position.y;
      if (canUpdate || table.id < 0) node.classList.add('dragging');
      try { node.setPointerCapture(event.pointerId); } catch (error) {}
    });

    node.addEventListener('pointermove', function (event) {
      if (!active) return;
      if (!canUpdate && table.id > 0) return;

      moved = moved || Math.abs(event.clientX - startX) > 4 || Math.abs(event.clientY - startY) > 4;
      var nextX = clamp(snapToGrid(startLeft + (event.clientX - startX)), 0, canvas.clientWidth - TABLE_W);
      var nextY = clamp(snapToGrid(startTop + (event.clientY - startY)), 0, canvas.clientHeight - TABLE_H);
      if (!positionIsFree(table, nextX, nextY)) return;

      table.position.x = nextX;
      table.position.y = nextY;
      node.style.left = table.position.x + 'px';
      node.style.top = table.position.y + 'px';
    });

    function stop() {
      if (!active) return;
      active = false;
      tableDragging = false;
      node.classList.remove('dragging');
    }

    node.addEventListener('pointerup', function (event) {
      var wasMoved = moved;
      stop();
      if (!wasMoved) {
        ignoreNextDocumentClick = true;
        openContext(event, table, node);
      }
    });
    node.addEventListener('pointercancel', stop);
    node.addEventListener('contextmenu', function (event) {
      event.preventDefault();
      openContext(event, table, node);
    });
  }

  var contextTable = null;
  var contextNode = null;
  var ignoreNextDocumentClick = false;

  function openContext(event, table, node) {
    closeContext();
    contextTable = table;
    contextNode = node;
    node.classList.add('selected');

    var html = '<div class="ctx-title">' + escapeHtml(tableLabel(table)) + '</div>';
    if (canUpdate || table.id < 0) {
      statusActions(table).forEach(function (item) {
        html += '<button class="ctx-item" type="button" data-action="' + item.action + '">' +
          '<i class="bi ' + item.icon + '"></i> ' + item.label + '</button>';
      });
      html += '<div class="ctx-sep"></div>';
    }
    html += '<button class="ctx-item" type="button" data-action="qr"><i class="bi bi-qr-code"></i> QR code</button>';
    if (canDelete || table.id < 0) {
      html += '<button class="ctx-item danger" type="button" data-action="remove"><i class="bi bi-trash"></i> Remove table</button>';
    }

    context.innerHTML = html;

    var rect = canvas.getBoundingClientRect();
    context.style.left = clamp(event.clientX - rect.left, 6, rect.width - 190) + 'px';
    context.style.top = clamp(event.clientY - rect.top, 6, rect.height - 160) + 'px';
    context.classList.add('show');
  }

  function closeContext() {
    context.classList.remove('show');
    if (contextNode) contextNode.classList.remove('selected');
    contextTable = null;
    contextNode = null;
  }

  function nextTableNumber() {
    return tables.reduce(function (max, table) {
      return Math.max(max, Number(table.table_number || 0));
    }, 0) + 1;
  }

  function addTable() {
    if (!canCreate) return;

    var count = currentTables().length;
    var x = 24 + ((count * 34) % Math.max(120, canvas.clientWidth - TABLE_W - 24));
    var y = 64 + (Math.floor(count / 5) * 130);
    var table = parsePosition({
      id: tempId--,
      table_number: nextTableNumber(),
      table_status: 'free',
      table_floor: selectedFloor,
      position: { x: snapToGrid(x), y: snapToGrid(y) },
      order_id: null,
      restaurant_id: restaurantId
    });
    var position = firstFreePosition(x, y, table);

    if (!position) {
      showError('No free grid space on this floor.');
      return;
    }

    table.position = position;
    tables.push(table);

    syncFloors();
    render();
    showToast('Table added to floor ' + selectedFloor);
  }

  function statusActions(table) {
    if (table.id < 0) {
      return [];
    }

    if (table.table_status === 'waiting_order') {
      return [
        { action: 'status:order_done', label: 'Order done', icon: statusMeta.order_done.icon },
        { action: 'status-cancel', label: 'Cancel order', icon: 'bi-x-circle' }
      ];
    }

    if (table.table_status === 'order_done') {
      return [
        { action: 'payment', label: 'Free table', icon: 'bi-cash-coin' }
      ];
    }

    return [];
  }

  function addFloor() {
    if (!canCreate) return;

    var nextFloor = Math.max.apply(null, Array.from(new Set(tables.map(function (table) {
      return Number(table.table_floor || 1);
    }).concat([1])))) + 1;

    selectedFloor = nextFloor;
    syncFloors();
    render();
    showToast('Floor ' + nextFloor + ' selected');
  }

  function tablePayload(table) {
    return {
      table_number: Number(table.table_number),
      table_status: table.table_status || 'free',
      table_floor: Number(table.table_floor || selectedFloor),
      position: {
        x: Number(table.position.x || 0),
        y: Number(table.position.y || 0)
      },
      order_id: table.order_id || null,
      restaurant_id: restaurantId
    };
  }

  function updateTableStatus(table, payload) {
    return request('/tables/' + table.id + '/status', {
      method: 'PATCH',
      body: JSON.stringify(payload)
    }).then(function (response) {
      if (response.data && Number(response.data.extra_paid || 0) > 0) {
        showToast('Extra paid: ' + money(response.data.extra_paid));
      } else {
        showToast(response.message || 'Table updated');
      }

      return loadTables();
    });
  }

  function fetchOrderTotal(table) {
    if (!table.order_id) {
      return Promise.resolve(0);
    }

    return request('/orders/' + encodeURIComponent(table.order_id)).then(function (payload) {
      var rows = payload.data || [];
      var first = rows[0] || {};
      return Number(first.order_price || first.price || 0);
    });
  }

  function openPayment(table) {
    paymentTable = table;
    paymentOrderTotal = 0;
    paymentForm.reset();
    paymentErrorAlert.classList.add('d-none');
    paymentExtraAlert.classList.add('d-none');
    mixedPaymentFields.classList.add('d-none');
    document.getElementById('paymentTableId').value = table.id;
    document.getElementById('tablePaymentTitle').textContent = 'Collect Payment - ' + tableLabel(table);
    document.getElementById('paymentOrderTotal').textContent = 'Loading...';

    bootstrap.Modal.getOrCreateInstance(paymentModalEl).show();

    fetchOrderTotal(table).then(function (total) {
      paymentOrderTotal = total;
      document.getElementById('paymentOrderTotal').textContent = money(total);
      updatePaymentExtra();
    }).catch(function (error) {
      paymentErrorAlert.textContent = error.message || 'Unable to load order total.';
      paymentErrorAlert.classList.remove('d-none');
    });
  }

  function updatePaymentExtra() {
    if (paymentMethod.value !== 'cash_credit') {
      paymentExtraAlert.classList.add('d-none');
      return;
    }

    var cash = Number(document.getElementById('paidCashAmount').value || 0);
    var credit = Number(document.getElementById('paidCreditAmount').value || 0);
    var extra = (cash + credit) - paymentOrderTotal;

    if (extra > 0) {
      paymentExtraAlert.textContent = 'An additional charge was applied to the customer: ' + money(extra);
      paymentExtraAlert.classList.remove('d-none');
    } else {
      paymentExtraAlert.classList.add('d-none');
    }
  }

  function paymentPayload() {
    var method = paymentMethod.value;
    var payload = {
      table_status: 'free',
      payment_method: method
    };

    if (method === 'cash_credit') {
      payload.total_paid_cash = Number(document.getElementById('paidCashAmount').value || 0);
      payload.total_paid_credit = Number(document.getElementById('paidCreditAmount').value || 0);
    }

    return payload;
  }

  function saveTables() {
    if (!restaurantId) {
      showError('Restaurant is required before saving tables.');
      return;
    }

    var rows = currentTables();
    var hasOverlap = rows.some(function (table, index) {
      return rows.slice(index + 1).some(function (other) {
        return rectsOverlap(
          tableRect(table, table.position.x, table.position.y),
          tableRect(other, other.position.x, other.position.y)
        );
      });
    });

    if (hasOverlap) {
      showError('Two tables cannot be in the same grid space.');
      return;
    }

    var writes = rows.map(function (table) {
      if (table.id < 0) {
        if (!canCreate) return null;

        return request('/tables', {
          method: 'POST',
          body: JSON.stringify(tablePayload(table))
        });
      }

      if (!canUpdate) return null;

      return request('/tables/' + table.id, {
        method: 'PUT',
        body: JSON.stringify(tablePayload(table))
      });
    }).filter(Boolean);

    Promise.all(writes).then(function () {
      showToast('Floor ' + selectedFloor + ' saved');
      return loadTables();
    }).catch(function (error) {
      var message = error.message || 'Unable to save tables.';
      if (error.errors) message = Object.values(error.errors).join(' ');
      showError(message);
    });
  }

  function removeTable(table) {
    confirmAction('Delete ' + tableLabel(table) + '?').then(function (confirmed) {
      if (!confirmed) return;

      if (table.id < 0) {
        tables = tables.filter(function (row) { return row !== table; });
        render();
        return;
      }

      request('/tables/' + table.id, { method: 'DELETE' }).then(function () {
        tables = tables.filter(function (row) { return row.id !== table.id; });
        syncFloors();
        render();
        showToast('Table deleted');
      }).catch(function (error) {
        showError(error.message || 'Unable to delete table.');
      });
    });
  }

  function showQrModal(rows) {
    if (!restaurant || !restaurant.main_code) {
      showError('Restaurant code is required to generate QR codes.');
      return;
    }

    qrGrid.innerHTML = rows.map(function (table) {
      var url = tableUrl(table);
      return '<div class="qr-card">' +
        '<div class="qr-box" data-url="' + escapeHtml(url) + '"></div>' +
        '<div class="qr-title">' + escapeHtml(tableLabel(table)) + '</div>' +
        '<div class="qr-url">' + escapeHtml(url) + '</div>' +
      '</div>';
    }).join('');

    qrGrid.querySelectorAll('.qr-box').forEach(function (box) {
      if (typeof QRCode !== 'undefined') {
        new QRCode(box, {
          text: box.getAttribute('data-url'),
          width: 132,
          height: 132,
          correctLevel: QRCode.CorrectLevel.M
        });
      } else {
        box.textContent = box.getAttribute('data-url');
      }
    });

    bootstrap.Modal.getOrCreateInstance(qrModalEl).show();
  }

  function loadTables() {
    return request('/tables?restaurant_id=' + encodeURIComponent(restaurantId)).then(function (payload) {
      tables = (payload.data || []).map(parsePosition);
      syncFloors();
      render();
    });
  }

  function refreshTableStatuses() {
    if (tablesLiveLoading || tableDragging || currentTables().some(function (table) { return table.id < 0; })) return;
    tablesLiveLoading = true;

    request('/tables?restaurant_id=' + encodeURIComponent(restaurantId)).then(function (payload) {
      var liveRows = (payload.data || []).map(parsePosition);
      var currentById = {};

      tables.forEach(function (table) {
        if (table.id > 0) currentById[Number(table.id)] = table;
      });

      liveRows.forEach(function (live) {
        var existing = currentById[Number(live.id)];

        if (existing) {
          existing.table_status = live.table_status;
          existing.order_id = live.order_id;
          existing.table_number = live.table_number;
          existing.table_floor = live.table_floor;
          existing.restaurant_id = live.restaurant_id;
          return;
        }

        tables.push(live);
      });

      tables = tables.filter(function (table) {
        return table.id < 0 || liveRows.some(function (live) {
          return Number(live.id) === Number(table.id);
        });
      });

      syncFloors();
      render();
    }).catch(function () {
      // Keep the current floor plan if a background refresh fails.
    }).finally(function () {
      tablesLiveLoading = false;
    });
  }

  function loadRestaurant() {
    return request('/restaurants/' + encodeURIComponent(restaurantId)).then(function (payload) {
      restaurant = payload.data || {};
    });
  }

  context.addEventListener('click', function (event) {
    var button = event.target.closest('[data-action]');
    if (!button || !contextTable) return;

    var action = button.getAttribute('data-action');
    var table = contextTable;
    closeContext();

    if (action === 'status:order_done') {
      updateTableStatus(table, { table_status: 'order_done' }).catch(function (error) {
        showError(error.message || 'Unable to update table.');
      });
      return;
    }

    if (action === 'status-cancel') {
      confirmAction('Cancel this order and free the table?').then(function (confirmed) {
        if (!confirmed) return;
        updateTableStatus(table, { table_status: 'free' }).catch(function (error) {
          showError(error.message || 'Unable to cancel order.');
        });
      });
      return;
    }

    if (action === 'payment') {
      openPayment(table);
      return;
    }

    if (action === 'qr') {
      showQrModal([table]);
      return;
    }

    if (action === 'remove') {
      removeTable(table);
    }
  });

  document.addEventListener('click', function (event) {
    if (ignoreNextDocumentClick) {
      ignoreNextDocumentClick = false;
      return;
    }

    if (context.classList.contains('show') && !context.contains(event.target)) {
      closeContext();
    }
  });

  floorSelect.addEventListener('change', function () {
    selectedFloor = Number(floorSelect.value || 1);
    render();
  });

  if (newTableBtn) newTableBtn.addEventListener('click', addTable);
  if (addFloorBtn) addFloorBtn.addEventListener('click', addFloor);
  if (savePlanBtn) savePlanBtn.addEventListener('click', saveTables);
  if (printQrBtn) printQrBtn.addEventListener('click', function () { window.print(); });
  if (paymentMethod) {
    paymentMethod.addEventListener('change', function () {
      mixedPaymentFields.classList.toggle('d-none', paymentMethod.value !== 'cash_credit');
      updatePaymentExtra();
    });
  }
  ['paidCashAmount', 'paidCreditAmount'].forEach(function (id) {
    var input = document.getElementById(id);
    if (input) input.addEventListener('input', updatePaymentExtra);
  });
  if (paymentForm) {
    paymentForm.addEventListener('submit', function (event) {
      event.preventDefault();
      paymentErrorAlert.classList.add('d-none');

      if (!paymentTable) return;

      var payload = paymentPayload();
      if (
        payload.payment_method === 'cash_credit'
        && (Number(payload.total_paid_cash || 0) + Number(payload.total_paid_credit || 0)) < paymentOrderTotal
      ) {
        paymentErrorAlert.textContent = 'Paid total must be higher than or equal to the order total.';
        paymentErrorAlert.classList.remove('d-none');
        return;
      }

      updateTableStatus(paymentTable, payload).then(function () {
        bootstrap.Modal.getOrCreateInstance(paymentModalEl).hide();
        paymentTable = null;
      }).catch(function (error) {
        var message = error.message || 'Unable to save payment.';
        if (error.errors) message = Object.values(error.errors).join(' ');
        paymentErrorAlert.textContent = message;
        paymentErrorAlert.classList.remove('d-none');
      });
    });
  }

  if (!restaurantId) {
    showError('No active restaurant selected.');
    return;
  }

  Promise.all([loadRestaurant(), loadTables()]).catch(function (error) {
    showError(error.message || 'Unable to load tables.');
  });

  tablesLiveTimer = window.setInterval(function () {
    if (document.hidden || context.classList.contains('show') || paymentModalEl.classList.contains('show')) return;
    refreshTableStatuses();
  }, 1000);

  window.addEventListener('beforeunload', function () {
    if (tablesLiveTimer) window.clearInterval(tablesLiveTimer);
  });
})();
</script>
