<?php
// Tables management page (frontend only).
// Interactive floor plan: drag & drop tables, add new tables, manage status & price.
// Layout is persisted to localStorage since there is no backend yet.
?>
<script>
  window.TABLE_ICON_URL = <?= json_encode(app_base_url() . '/storage/icons/restaurant-table-icon.svg', JSON_UNESCAPED_SLASHES); ?>;
</script>

<div class="card mb-3">
  <div class="card-body">
    <div class="tables-toolbar">
      <div>
        <div class="section-title mb-0">Tables</div>
        <small class="text-secondary">Drag to move &middot; right-click for status / remove &middot; then save.</small>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-outline-primary" id="newTableBtn" type="button">
          <i class="bi bi-plus-lg"></i> New Table
        </button>
        <button class="btn btn-primary" id="savePlanBtn" type="button">
          <i class="bi bi-check-lg"></i> Save
        </button>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body p-2 px-3">
    <div class="floor-plan wall" id="floorPlan">
      <div class="fp-hint"><i class="bi bi-arrows-move text-primary-custom"></i> Drag a table to reposition it</div>
      <div class="fp-context" id="fpContext" role="menu"></div>
    </div>
    <div class="fp-toast" id="fpToast"><i class="bi bi-check-circle-fill"></i><span id="fpToastText">Layout saved</span></div>
  </div>
</div>

<!-- Payment modal -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title fs-6" id="paymentModalTitle">Collect Payment</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="paymentModalBody">
        <!-- JS-rendered payment stages -->
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';

  var STORAGE_KEY = 'savora_admin_tables_plan';
  var canvas = document.getElementById('floorPlan');
  var toast = document.getElementById('fpToast');
  var toastText = document.getElementById('fpToastText');
  var newTableBtn = document.getElementById('newTableBtn');
  var savePlanBtn = document.getElementById('savePlanBtn');
  var context = document.getElementById('fpContext');

  var TABLE_W = 92;   // table element width (px)
  var TABLE_H = 116;   // approx full height (number + icon + badges)
  var STATUSES = ['Free', 'Waiting order', 'Order done', 'Canceled'];
  var STATUS_META = {
    'Free':          { cls: 'free',     icon: 'bi-check2-circle' },
    'Waiting order': { cls: 'waiting',  icon: 'bi-clipboard' },
    'Order done':    { cls: 'done',     icon: 'bi-check2-square' },
    'Canceled':      { cls: 'canceled', icon: 'bi-x-circle' }
  };
  var tables = [];
  var nextId = 1;

  function clamp(v, min, max) { return Math.max(min, Math.min(max, v)); }
  function idLabel(t) { return 'T-' + String(t.id).padStart(2, '0'); }
  function moneyStr(v) { v = (typeof v === 'number' && v > 0) ? v : 0; return '$' + v.toFixed(2); }
  function normalStatus(s) { return STATUSES.indexOf(s) !== -1 ? s : 'Free'; }
  function hasBill(s) { return s === 'Waiting order' || s === 'Order done'; }

  function showToast(msg) {
    toastText.textContent = msg;
    toast.classList.add('show');
    clearTimeout(showToast._t);
    showToast._t = setTimeout(function () { toast.classList.remove('show'); }, 2200);
  }

  /* ===== Persistence ===== */
  function loadTables() {
    try {
      var raw = localStorage.getItem(STORAGE_KEY);
      if (raw) {
        var parsed = JSON.parse(raw);
        if (Array.isArray(parsed) && parsed.length) {
          parsed.forEach(function (t) {
            if (t.id >= nextId) nextId = t.id + 1;
            // normalize old/legacy status values
            if (t.status === 'Occupied' || t.status === 'Reserved') t.status = 'Waiting order';
            t.status = normalStatus(t.status);
          });
          return parsed;
        }
      }
    } catch (e) { /* fall through to seed */ }

    return [
      { id: 1, x: 70,  y: 90,  seats: 4, status: 'Free',          total: 0    },
      { id: 2, x: 200, y: 180, seats: 6, status: 'Waiting order', total: 32.50 },
      { id: 3, x: 340, y: 70,  seats: 2, status: 'Order done',    total: 42.50 },
      { id: 4, x: 320, y: 300, seats: 8, status: 'Canceled',      total: 28.00 },
      { id: 5, x: 540, y: 170, seats: 4, status: 'Order done',    total: 88.90 },
      { id: 6, x: 40,  y: 330, seats: 2, status: 'Free',          total: 0    }
    ];
  }

  function saveTables() {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(tables));
      showToast('Layout saved (' + tables.length + ' tables)');
    } catch (e) {
      showToast('Could not save layout');
    }
  }

  /* ===== Rendering ===== */
  function render() {
    canvas.querySelectorAll('.table-object').forEach(function (n) { n.remove(); });
    tables.forEach(function (t) { canvas.appendChild(buildNode(t)); });
  }

  function buildNode(t) {
    var el = document.createElement('div');
    el.className = 'table-object';
    el.style.left = t.x + 'px';
    el.style.top = t.y + 'px';

    var status = normalStatus(t.status);
    var meta = STATUS_META[status] || STATUS_META['Free'];
    var statusClass = meta.cls;

    // Show the total price badge only when the table has an active bill.
    var priceBadge = '';
    if (hasBill(status)) {
      var total = (typeof t.total === 'number' && t.total > 0) ? t.total : 0;
      priceBadge = '<span class="table-badge table-badge-price" title="Total price">' +
        '<i class="bi bi-cash-coin"></i> ' + moneyStr(total) + '</span>';
    }

    el.innerHTML =
      '<div class="table-num">' + idLabel(t) + '</div>' +
      '<div class="table-icon-wrapper">' +
        '<img class="table-icon" src="' + window.TABLE_ICON_URL + '" alt="' + idLabel(t) + '" draggable="false">' +
      '</div>' +
      '<div class="table-badges">' +
        priceBadge +
        '<span class="table-badge table-status-tag ' + statusClass + '">' + status + '</span>' +
      '</div>';

    bind(el, t);
    return el;
  }

  /* ===== Drag & drop ===== */
  // Pointer-event based drag & drop. Clicking a table does nothing —
  // status is changed only via the right-click context menu.
  function bind(el, t) {
    var active = false;
    var moved = false;
    var sx = 0, sy = 0, sl = 0, st = 0;

    el.addEventListener('pointerdown', function (e) {
      active = true;
      moved = false;
      sx = e.clientX;
      sy = e.clientY;
      sl = t.x;
      st = t.y;
      el.classList.add('dragging');
      try { el.setPointerCapture(e.pointerId); } catch (err) {}
    });

    el.addEventListener('pointermove', function (e) {
      if (!active) return;
      if (Math.abs(e.clientX - sx) + Math.abs(e.clientY - sy) > 3) moved = true;
      var nx = clamp(sl + (e.clientX - sx), 0, canvas.clientWidth - TABLE_W);
      var ny = clamp(st + (e.clientY - sy), 0, canvas.clientHeight - TABLE_H);
      t.x = Math.round(nx);
      t.y = Math.round(ny);
      el.style.left = t.x + 'px';
      el.style.top = t.y + 'px';
    });

    var stop = function () {
      if (!active) return;
      active = false;
      el.classList.remove('dragging');
      // A plain click (no drag) on an "Order done" table opens the payment popup.
      if (!moved && t.status === 'Order done') openPayment(t);
    };

    el.addEventListener('pointerup', stop);
    el.addEventListener('pointercancel', stop);

    el.addEventListener('contextmenu', function (e) {
      e.preventDefault();
      e.stopPropagation();
      openContext(e, t, el);
    });
  }

  /* ===== Context menu ===== */
  var _ctxTable = null;    // table the menu belongs to
  var _ctxEl = null;       // the menu's table element

  function openContext(e, t, el) {
    _ctxTable = t;
    _ctxEl = el;
    el.classList.add('dragging');

    var options = statusOptions(t);
    var html = '<div class="ctx-title">' + idLabel(t) + ' — Status</div>';
    options.forEach(function (o) {
      html += '<button class="ctx-item" data-action="' + o.action + '">' +
        '<i class="bi ' + o.icon + '"></i> ' + o.label + '</button>';
    });
    html += '<div class="ctx-sep"></div>' +
      '<button class="ctx-item" data-action="duplicate"><i class="bi bi-copy"></i> Duplicate</button>' +
      '<button class="ctx-item danger" data-action="remove"><i class="bi bi-trash"></i> Remove table</button>';

    context.innerHTML = html;

    // Position the menu near the cursor, keeping it inside the canvas.
    var rect = canvas.getBoundingClientRect();
    var menuW = context.offsetWidth || 190;
    var menuH = context.offsetHeight || 150;
    var x = e.clientX - rect.left;
    var y = e.clientY - rect.top;
    if (x + menuW > rect.width) x = rect.width - menuW - 8;
    if (y + menuH > rect.height) y = rect.height - menuH - 8;
    x = Math.max(6, x);
    y = Math.max(6, y);
    context.style.left = x + 'px';
    context.style.top = y + 'px';
    context.classList.add('show');
  }

  // Possible status transitions based on the current status:
  //  Free -> Waiting order
  //  Waiting order -> Order done / Canceled
  //  Order done -> Free (collect payment)
  //  Canceled -> Free / Waiting order
  function statusOptions(t) {
    var icon = function (s) { return (STATUS_META[s] || {}).icon || 'bi-arrow-right-circle'; };
    switch (t.status) {
      case 'Waiting order':
        return [
          { label: 'Order done', action: 'set:Order done', icon: icon('Order done') },
          { label: 'Canceled',   action: 'set:Canceled',   icon: icon('Canceled') }
        ];
      case 'Order done':
        return [
          { label: 'Free (collect payment)', action: 'payment', icon: 'bi-cash-coin' }
        ];
      case 'Canceled':
        return [
          { label: 'Free again', action: 'set:Free', icon: 'bi-arrow-counterclockwise' }
        ];
      case 'Free':
      default:
        return [
          { label: 'Waiting order', action: 'set:Waiting order', icon: icon('Waiting order') }
        ];
    }
  }

  function closeContext() {
    context.classList.remove('show');
    if (_ctxEl) _ctxEl.classList.remove('dragging');
    _ctxTable = null;
    _ctxEl = null;
  }

  function onContextAction(e) {
    var btn = e.target.closest('[data-action]');
    if (!btn || !_ctxTable) return;
    var action = btn.getAttribute('data-action');
    var t = _ctxTable;
    var el = _ctxEl;
    closeContext();

    if (action === 'remove') {
      var i = tables.indexOf(t);
      if (i !== -1) {
        tables.splice(i, 1);
        render();
        showToast('Removed ' + idLabel(t));
      }
    } else if (action === 'duplicate') {
      tables.push({
        id: nextId++,
        x: Math.min(t.x + 28, canvas.clientWidth - TABLE_W),
        y: Math.min(t.y + 28, canvas.clientHeight - TABLE_H),
        seats: t.seats,
        status: t.status,
        total: t.total
      });
      render();
      showToast('Duplicated ' + idLabel(t));
    } else if (action === 'payment') {
      openPayment(t);
    } else if (action.indexOf('set:') === 0) {
      var target = action.slice(4);
      if (STATUSES.indexOf(target) !== -1) {
        setStatus(t, target);
      }
    }
  }

  function setStatus(t, s) {
    t.status = s;
    render();
    saveTables();
  }

  context.addEventListener('click', onContextAction);

  // Close menu when clicking anywhere else, scrolling, or pressing Esc.
  document.addEventListener('click', function (e) {
    if (context.classList.contains('show') && !context.contains(e.target)) {
      closeContext();
    }
  });
  document.addEventListener('contextmenu', function (e) {
    if (context.classList.contains('show') && !context.contains(e.target)) {
      closeContext();
    }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeContext();
  });
  window.addEventListener('resize', closeContext);

  /* ===== Payment modal ===== */
  var _payTable = null;             // table being paid / freed
  var paymentBody = document.getElementById('paymentModalBody');
  var paymentTitle = document.getElementById('paymentModalTitle');
  var paymentModal = null;

  function getModal() {
    if (!paymentModal) paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
    return paymentModal;
  }

  function openPayment(t) {
    _payTable = t;
    paymentTitle.textContent = 'Collect Payment — ' + idLabel(t) + ' (' + moneyStr(t.total) + ')';
    showPayTypes();
    getModal().show();
  }

  // Stage 1: choose payment type.
  function showPayTypes() {
    paymentBody.innerHTML =
      '<p class="text-muted small mb-3 text-center">Select payment type</p>' +
      '<div class="d-grid gap-2">' +
        '<button class="btn btn-outline-primary pay-type" data-type="cash">' +
          '<i class="bi bi-cash-coin me-2"></i>Cash</button>' +
        '<button class="btn btn-outline-primary pay-type" data-type="card">' +
          '<i class="bi bi-credit-card me-2"></i>Credit card</button>' +
        '<button class="btn btn-primary pay-type" data-type="mixed">' +
          '<i class="bi bi-cash-stack me-2"></i>Cash &amp; Credit card</button>' +
      '</div>';

    paymentBody.querySelectorAll('.pay-type').forEach(function (b) {
      b.addEventListener('click', function () {
        choosePayType(b.getAttribute('data-type'));
      });
    });
  }

  // Stage 2: single-method success, or split (cash & card) form.
  function choosePayType(type) {
    if (type === 'mixed') {
      showSplitPayment();
    } else {
      var label = (type === 'cash') ? 'Cash' : 'Credit card';
      paymentBody.innerHTML =
        '<div class="text-center py-4">' +
          '<i class="bi bi-check-circle-fill text-success" style="font-size:4rem;"></i>' +
          '<h5 class="mt-3 mb-1">' + label + ' received</h5>' +
          '<p class="text-muted small mb-0">' + idLabel(_payTable) + ' is now free</p>' +
        '</div>';
      freeTableAfter(1300);
    }
  }

  function freeTableAfter(ms) {
    setTimeout(function () {
      if (_payTable) {
        _payTable.status = 'Free';
      }
      saveTables();
      render();
      getModal().hide();
      if (_payTable) showToast(idLabel(_payTable) + ' is now Free');
      _payTable = null;
    }, ms);
  }

  // Stage 3: split (Cash & Credit card) — back + two amount inputs.
  function showSplitPayment() {
    paymentBody.innerHTML =
      '<button type="button" class="btn btn-sm btn-link mb-2 px-0" id="mixedBackBtn">' +
        '<i class="bi bi-arrow-left me-1"></i>Back</button>' +

      '<div class="row g-2 mb-2">' +
        '<div class="col-6">' +
          '<label class="form-label small fw-semibold" for="cashAmount">Cash paid</label>' +
          '<div class="input-group fp-amt">' +
            '<span class="input-group-text"><i class="bi bi-cash-coin"></i></span>' +
            '<input type="number" class="form-control" id="cashAmount" min="0" step="0.01" placeholder="0.00">' +
          '</div>' +
        '</div>' +
        '<div class="col-6">' +
          '<label class="form-label small fw-semibold" for="cardAmount">Card paid</label>' +
          '<div class="input-group fp-amt">' +
            '<span class="input-group-text"><i class="bi bi-credit-card"></i></span>' +
            '<input type="number" class="form-control" id="cardAmount" min="0" step="0.01" placeholder="0.00">' +
          '</div>' +
        '</div>' +
      '</div>' +

      '<div id="mixedError" class="text-danger small d-none mb-2">' +
        '<i class="bi bi-exclamation-triangle me-1"></i>Two amounts must be provided</div>' +

      '<div id="mixedSuccess" class="text-center d-none py-2">' +
        '<i class="bi bi-check-circle-fill text-success" style="font-size:3.2rem;"></i>' +
        '<div class="text-muted small mt-1">Payment complete</div>' +
      '</div>' +

      '<button type="button" class="btn btn-primary w-100" id="updatePayBtn">Update status</button>';

    var backBtn = document.getElementById('mixedBackBtn');
    var cashEl = document.getElementById('cashAmount');
    var cardEl = document.getElementById('cardAmount');
    var updateBtn = document.getElementById('updatePayBtn');
    var errorEl = document.getElementById('mixedError');
    var successEl = document.getElementById('mixedSuccess');

    backBtn.addEventListener('click', showPayTypes);

    function bothFilled() {
      return cashEl.value.trim() !== '' && cardEl.value.trim() !== '';
    }

    function clearInvalid() {
      [cashEl, cardEl].forEach(function (inp) {
        inp.classList.remove('is-invalid');
        var w = inp.closest('.fp-amt');
        if (w) w.classList.remove('fp-shake');
      });
      errorEl.classList.add('d-none');
    }

    function validateMix() {
      clearInvalid();
      if (bothFilled()) {
        successEl.classList.remove('d-none');
      } else {
        successEl.classList.add('d-none');
      }
    }

    cashEl.addEventListener('input', validateMix);
    cardEl.addEventListener('input', validateMix);

    updateBtn.addEventListener('click', function () {
      if (bothFilled()) {
        freeTableAfter(200);
        return;
      }
      // One (or both) is empty -> shake + danger border + message.
      errorEl.classList.remove('d-none');
      successEl.classList.add('d-none');
      if (cashEl.value.trim() === '') flagInvalid(cashEl);
      if (cardEl.value.trim() === '') flagInvalid(cardEl);
    });

    function flagInvalid(inp) {
      inp.classList.add('is-invalid');
      var w = inp.closest('.fp-amt');
      if (w) {
        w.classList.remove('fp-shake');
        void w.offsetWidth; // restart the shake animation
        w.classList.add('fp-shake');
      }
    }
  }

  /* ===== Buttons ===== */
  function addTable() {
    var x = 24 + Math.floor(Math.random() * Math.max(1, canvas.clientWidth - TABLE_W - 24));
    var y = 60 + Math.floor(Math.random() * Math.max(1, 240));
    tables.push({ id: nextId++, x: x, y: y, seats: 4, status: 'Free', total: 0 });
    render();
    showToast('Added ' + idLabel({ id: nextId - 1 }));
  }

  newTableBtn.addEventListener('click', addTable);
  savePlanBtn.addEventListener('click', saveTables);

  /* ===== Init ===== */
  tables = loadTables();
  render();
})();
</script>
