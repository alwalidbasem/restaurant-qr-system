/* ==========================================================================
   Savora Admin - dashboard.js
   (Extracted from public/admin/test.html and split into assets.)
   ========================================================================== */

/* ===== Sidebar toggle (mobile) ===== */
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

  if (overlay) {
    overlay.addEventListener('click', closeSidebar);
  }

  // Close the mobile sidebar whenever a nav link is clicked.
  if (sidebar) {
    sidebar.querySelectorAll('.sidebar .nav-link').forEach(function (link) {
      link.addEventListener('click', closeSidebar);
    });
  }
})();

/* ===== Sample Data ===== */
(function () {
  /* ---- Orders ---- */
  var orders = [
    { id: '#ORD-2451', customer: 'Liam Carter', table: 'T-04', items: 3, total: '$42.50', status: 'Served', time: '12:04 PM' },
    { id: '#ORD-2452', customer: 'Sara Ahmed', table: 'T-11', items: 2, total: '$28.00', status: 'Preparing', time: '12:10 PM' },
    { id: '#ORD-2453', customer: 'James Lee', table: 'T-02', items: 5, total: '$76.25', status: 'Served', time: '12:14 PM' },
    { id: '#ORD-2454', customer: 'Nora Fields', table: 'T-09', items: 1, total: '$14.00', status: 'Pending', time: '12:20 PM' },
    { id: '#ORD-2455', customer: 'Omar Haddad', table: 'T-06', items: 4, total: '$58.75', status: 'Preparing', time: '12:22 PM' },
    { id: '#ORD-2456', customer: 'Ava Thompson', table: 'T-15', items: 2, total: '$31.00', status: 'Cancelled', time: '12:29 PM' },
    { id: '#ORD-2457', customer: 'Youssef Amin', table: 'T-08', items: 6, total: '$91.40', status: 'Served', time: '12:35 PM' },
    { id: '#ORD-2458', customer: 'Emily Zhang', table: 'T-03', items: 2, total: '$24.90', status: 'Pending', time: '12:41 PM' }
  ];

  var statusColor = {
    'Served': 'success',
    'Preparing': 'warning',
    'Pending': 'secondary',
    'Cancelled': 'danger'
  };

  var ordersBody = document.getElementById('ordersTableBody');
  if (ordersBody) {
    orders.forEach(function (o) {
      ordersBody.insertAdjacentHTML('beforeend',
        '<tr>' +
          '<td class="fw-semibold">' + o.id + '</td>' +
          '<td>' + o.customer + '</td>' +
          '<td>' + o.table + '</td>' +
          '<td>' + o.items + '</td>' +
          '<td>' + o.total + '</td>' +
          '<td><span class="badge badge-status bg-' + statusColor[o.status] + '-subtle text-' + statusColor[o.status] + ' border border-' + statusColor[o.status] + '-subtle">' + o.status + '</span></td>' +
          '<td class="text-secondary">' + o.time + '</td>' +
          '<td class="text-end">' +
            '<button class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></button> ' +
            '<button class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></button>' +
          '</td>' +
        '</tr>'
      );
    });
  }

  /* ---- Menu ---- */
  var menuItems = [
    { name: 'Grilled Salmon', cat: 'Main Course', price: '$18.50', stock: 24, status: 'Available' },
    { name: 'Truffle Pasta', cat: 'Main Course', price: '$16.00', stock: 12, status: 'Available' },
    { name: 'Caesar Salad', cat: 'Starters', price: '$9.50', stock: 0, status: 'Out of Stock' },
    { name: 'Margherita Pizza', cat: 'Main Course', price: '$13.00', stock: 30, status: 'Available' },
    { name: 'Chocolate Lava Cake', cat: 'Dessert', price: '$7.50', stock: 8, status: 'Low Stock' }
  ];
  var menuStatusColor = { 'Available': 'success', 'Out of Stock': 'danger', 'Low Stock': 'warning' };
  var menuBody = document.getElementById('menuTableBody');
  if (menuBody) {
    menuItems.forEach(function (m) {
      menuBody.insertAdjacentHTML('beforeend',
        '<tr>' +
          '<td class="fw-semibold">' + m.name + '</td>' +
          '<td>' + m.cat + '</td>' +
          '<td>' + m.price + '</td>' +
          '<td>' + m.stock + '</td>' +
          '<td><span class="badge badge-status bg-' + menuStatusColor[m.status] + '-subtle text-' + menuStatusColor[m.status] + ' border border-' + menuStatusColor[m.status] + '-subtle">' + m.status + '</span></td>' +
        '</tr>'
      );
    });
  }
})();

(function () {
  /* ---- Staff ---- */
  var staff = [
    { name: 'Karim Nasser', role: 'Head Chef', status: 'On Duty' },
    { name: 'Lina Farouk', role: 'Waitress', status: 'On Duty' },
    { name: 'Tom Becker', role: 'Bartender', status: 'Break' },
    { name: 'Huda Saleh', role: 'Waitress', status: 'On Duty' },
    { name: 'Marco Rossi', role: 'Sous Chef', status: 'Off Duty' }
  ];
  var staffList = document.getElementById('staffList');
  if (staffList) {
    staff.forEach(function (s) {
      var dot = s.status === 'On Duty' ? 'success' : (s.status === 'Break' ? 'warning' : 'secondary');
      staffList.insertAdjacentHTML('beforeend',
        '<li class="list-group-item d-flex align-items-center justify-content-between">' +
          '<div class="d-flex align-items-center gap-2">' +
            '<span class="avatar-sm" style="background:#495057;">' + s.name.split(' ').map(function (n) { return n[0]; }).join('') + '</span>' +
            '<div>' +
              '<div class="fw-semibold">' + s.name + '</div>' +
              '<small class="text-secondary">' + s.role + '</small>' +
            '</div>' +
          '</div>' +
          '<span class="badge rounded-pill bg-' + dot + '-subtle text-' + dot + ' border border-' + dot + '-subtle">' + s.status + '</span>' +
        '</li>'
      );
    });
  }

  /* ---- Tables ---- */
  var tablesGrid = document.getElementById('tablesGrid');
  if (tablesGrid) {
    var tColor = { 'Occupied': 'danger', 'Free': 'success', 'Reserved': 'warning' };
    for (var i = 1; i <= 24; i++) {
      var r = Math.random();
      var status = r < 0.55 ? 'Occupied' : (r < 0.8 ? 'Free' : 'Reserved');
      tablesGrid.insertAdjacentHTML('beforeend',
        '<div class="col-6 col-sm-4 col-md-3 col-lg-2">' +
          '<div class="border rounded-3 p-2 text-center border-' + tColor[status] + ' bg-' + tColor[status] + '-subtle">' +
            '<div class="fw-bold">T-' + String(i).padStart(2, '0') + '</div>' +
            '<small class="text-' + tColor[status] + '">' + status + '</small>' +
          '</div>' +
        '</div>'
      );
    }
  }
})();

/* ===== Charts ===== */
function initCharts() {
  if (typeof Chart === 'undefined') {
    console.error('Chart.js failed to load from CDN. Check your internet connection or try a different browser.');
    return;
  }
  var orange = '#b8541b';

  new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: {
      labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
      datasets: [{
        label: 'Revenue ($)',
        data: [2100, 2450, 1980, 2600, 3400, 4800, 4286],
        borderColor: orange,
        backgroundColor: 'rgba(184,84,27,0.12)',
        fill: true,
        tension: .35,
        pointRadius: 4,
        pointBackgroundColor: orange
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { grid: { color: '#f0f0f0' } }, x: { grid: { display: false } } }
    }
  });

  new Chart(document.getElementById('categoryChart'), {
    type: 'doughnut',
    data: {
      labels: ['Main Course', 'Starters', 'Desserts', 'Beverages'],
      datasets: [{
        data: [45, 20, 15, 20],
        backgroundColor: ['#b8541b', '#e8a04e', '#2f9e44', '#1c7ed6'],
        borderWidth: 2,
        borderColor: '#fff'
      }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } } }
  });

  new Chart(document.getElementById('ordersHourChart'), {
    type: 'bar',
    data: {
      labels: ['9AM', '11AM', '1PM', '3PM', '5PM', '7PM', '9PM'],
      datasets: [{
        label: 'Orders',
        data: [8, 22, 35, 18, 25, 42, 30],
        backgroundColor: '#e8a04e',
        borderRadius: 6
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { grid: { color: '#f0f0f0' } }, x: { grid: { display: false } } }
    }
  });

  new Chart(document.getElementById('topDishesChart'), {
    type: 'bar',
    data: {
      labels: ['Salmon', 'Pasta', 'Pizza', 'Salad', 'Lava Cake'],
      datasets: [{
        label: 'Units Sold',
        data: [120, 98, 86, 54, 40],
        backgroundColor: '#1c7ed6',
        borderRadius: 6
      }]
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { x: { grid: { color: '#f0f0f0' } }, y: { grid: { display: false } } }
    }
  });

  new Chart(document.getElementById('statusChart'), {
    type: 'pie',
    data: {
      labels: ['Served', 'Preparing', 'Pending', 'Cancelled'],
      datasets: [{
        data: [62, 20, 13, 5],
        backgroundColor: ['#2f9e44', '#f5a623', '#868e96', '#e03131'],
        borderWidth: 2,
        borderColor: '#fff'
      }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } } }
  });
}

if (document.getElementById('revenueChart')) {
  if (document.readyState === 'complete') {
    initCharts();
  } else {
    window.addEventListener('load', initCharts);
  }
}