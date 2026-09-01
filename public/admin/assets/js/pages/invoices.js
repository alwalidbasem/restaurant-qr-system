( function () {
  function initInvoices() {
    var body = document.getElementById('invoicesTableBody');
    if (!body) return;

    var rows = [];
    var selectedInvoice = null;
    var printSettings = { invoice_print_full_page: 0, invoice_print_width_mm: 80, invoice_print_height_mm: 297 };
    var detailTitle = document.getElementById('invoiceDetailTitle');
    var detailMeta = document.getElementById('invoiceDetailMeta');
    var detailBody = document.getElementById('invoiceDetailBody');
    var printBtn = document.getElementById('invoicePrintBtn');
    var retryBtn = document.getElementById('invoiceRetryBtn');

    function invoiceStatusBadge(status) {
      var normalized = text(status, 'draft');
      var color = {
        accepted: 'success',
        rejected: 'danger',
        retry_pending: 'warning',
        ready: 'primary',
        submitting: 'info',
        disabled: 'secondary',
        draft: 'secondary'
      }[normalized] || 'secondary';

      return '<span class="badge bg-' + color + '-subtle text-' + color + ' border border-' + color + '-subtle">' +
        escapeHtml(normalized.replace(/_/g, ' ')) +
      '</span>';
    }

    function renderInvoices() {
      document.getElementById('invoicesShowing').textContent = rows.length + ' invoices';
      body.innerHTML = rows.map(function (invoice) {
        return '<tr class="invoice-row" data-id="' + escapeHtml(invoice.id) + '">' +
          '<td class="fw-semibold">' + escapeHtml(invoice.local_invoice_number || ('#' + invoice.id)) + '</td>' +
          '<td>#' + escapeHtml(invoice.order_id) + '</td>' +
          '<td>' + invoiceStatusBadge(invoice.jofotara_submission_status) + '</td>' +
          '<td>' + money(invoice.grand_total) + '</td>' +
        '</tr>';
      }).join('') || '<tr><td colspan="4" class="text-center text-secondary py-4">No invoices yet.</td></tr>';
    }

    function invoiceItemsTable(items) {
      return '<div class="table-responsive mt-3"><table class="table table-sm align-middle">' +
        '<thead><tr><th>Description</th><th>Qty</th><th>Unit</th><th>Tax %</th><th>Tax</th><th>Total</th></tr></thead>' +
        '<tbody>' + (items || []).map(function (item) {
          return '<tr>' +
            '<td>' + escapeHtml(item.description) + '</td>' +
            '<td>' + escapeHtml(item.quantity) + '</td>' +
            '<td>' + money(item.unit_price) + '</td>' +
            '<td>' + escapeHtml(item.tax_rate) + '</td>' +
            '<td>' + money(item.tax_amount) + '</td>' +
            '<td>' + money(item.line_total) + '</td>' +
          '</tr>';
        }).join('') + '</tbody></table></div>';
    }

    function renderInvoiceDetail(invoice) {
      selectedInvoice = invoice;
      detailTitle.textContent = 'Invoice ' + (invoice.local_invoice_number || ('#' + invoice.id));
      detailMeta.textContent = 'Order #' + invoice.order_id + ' / ' + text(invoice.issued_at);
      if (printBtn) printBtn.classList.remove('d-none');
      if (retryBtn) retryBtn.classList.toggle('d-none', invoice.jofotara_submission_status === 'accepted' || invoice.jofotara_submission_status === 'disabled');

      detailBody.innerHTML =
        '<div class="invoice-print-area">' +
          '<div class="thermal-invoice-header">' +
            '<div class="fw-bold">' + escapeHtml(invoice.seller_name || '-') + '</div>' +
            '<div>' + escapeHtml(invoice.seller_address || '-') + '</div>' +
            '<div>' + escapeHtml(invoice.seller_phone || '-') + '</div>' +
            '<div>' + escapeHtml(invoice.seller_tax_number ? ('TIN: ' + invoice.seller_tax_number) : ('National No: ' + (invoice.seller_national_number || '-'))) + '</div>' +
          '</div>' +
          '<hr>' +
          '<div class="d-flex justify-content-between gap-2 flex-wrap">' +
            '<div><div class="text-secondary small">Invoice</div><div class="fw-semibold">' + escapeHtml(invoice.local_invoice_number) + '</div></div>' +
            '<div><div class="text-secondary small">Status</div>' + invoiceStatusBadge(invoice.jofotara_submission_status) + '</div>' +
            '<div><div class="text-secondary small">EIN</div><div>' + escapeHtml(invoice.electronic_invoice_number || '-') + '</div></div>' +
          '</div>' +
          invoiceItemsTable(invoice.items || []) +
          '<div class="invoice-totals mt-3">' +
            '<div><span>Subtotal</span><strong>' + money(invoice.subtotal) + '</strong></div>' +
            '<div><span>Discount</span><strong>' + money(invoice.discount_total) + '</strong></div>' +
            '<div><span>Tax</span><strong>' + money(invoice.tax_total) + '</strong></div>' +
            '<div><span>Grand Total</span><strong>' + money(invoice.grand_total) + '</strong></div>' +
          '</div>' +
          (invoice.jofotara_qr_value ? '<div class="mt-3"><div class="text-secondary small">JoFotara QR Value</div><code class="small d-block text-break">' + escapeHtml(invoice.jofotara_qr_value) + '</code></div>' : '') +
          (invoice.error_message ? '<div class="alert alert-warning mt-3 mb-0">' + escapeHtml(invoice.error_message) + '</div>' : '') +
        '</div>';
    }

    function applyInvoicePrintSettings() {
      var existing = document.getElementById('invoicePrintSettingsStyle');
      if (existing) existing.remove();

      var fullPage = Number(printSettings.invoice_print_full_page || 0) === 1;
      var width = Math.max(40, Math.min(300, Number(printSettings.invoice_print_width_mm || 80)));
      var height = Math.max(80, Math.min(500, Number(printSettings.invoice_print_height_mm || 297)));
      var size = fullPage ? 'auto' : width + 'mm ' + height + 'mm';
      var areaWidth = fullPage ? '100%' : width + 'mm';
      var style = document.createElement('style');
      style.id = 'invoicePrintSettingsStyle';
      style.textContent = '@page{size:' + size + ';margin:' + (fullPage ? '10mm' : '0') + ';}' +
        '@media print{html,body{width:' + areaWidth + ';min-width:' + areaWidth + ';}' +
        '.invoice-print-area{width:' + areaWidth + ';}}';
      document.head.appendChild(style);
      document.body.classList.toggle('invoice-print-full-page', fullPage);
    }

    function loadInvoices() {
      request('/invoices').then(function (payload) {
        rows = payload.data || [];
        renderInvoices();
        if (selectedInvoice) {
          var refreshed = rows.find(function (row) { return String(row.id) === String(selectedInvoice.id); });
          if (!refreshed && detailBody) {
            detailBody.innerHTML = '<div class="text-center text-secondary py-5">Select an invoice to view details.</div>';
          }
        }
      }).catch(function (error) {
        body.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-4">' + escapeHtml(error.message || 'Unable to load invoices.') + '</td></tr>';
      });
    }

    if (activeRestaurantId) {
      request('/restaurants/' + activeRestaurantId + '/tax-settings').then(function (payload) {
        printSettings = Object.assign(printSettings, payload.data || {});
      }).catch(function () {});
    }

    body.addEventListener('click', function (event) {
      var row = event.target.closest('.invoice-row');
      if (!row) return;
      request('/invoices/' + row.dataset.id).then(function (payload) {
        renderInvoiceDetail(payload.data || {});
      }).catch(function (error) {
        detailBody.innerHTML = '<div class="alert alert-danger">' + escapeHtml(error.message || 'Unable to load invoice.') + '</div>';
      });
    });

    if (retryBtn) retryBtn.addEventListener('click', function () {
      if (!selectedInvoice) return;
      request('/invoices/' + selectedInvoice.id + '/retry', { method: 'POST' }).then(function (payload) {
        renderInvoiceDetail(payload.data || selectedInvoice);
        swalToast(payload.message || 'Invoice retry finished');
        loadInvoices();
      }).catch(function (error) {
        detailBody.insertAdjacentHTML('afterbegin', '<div class="alert alert-warning">' + escapeHtml(error.message || 'Retry failed.') + '</div>');
      });
    });

    if (printBtn) printBtn.addEventListener('click', function () {
      applyInvoicePrintSettings();
      window.print();
    });

    loadInvoices();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      initInvoices();
    });
  } else {
    initInvoices();
  }
})();

