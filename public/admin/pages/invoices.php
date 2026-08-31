<div class="row g-3">
  <div class="col-xl-5">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between">
        <span>Invoices</span>
        <small class="text-secondary" id="invoicesShowing">Loading...</small>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>No.</th>
              <th>Order</th>
              <th>Status</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody id="invoicesTableBody">
            <tr><td colspan="4" class="text-center text-secondary py-4">Loading invoices...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-xl-7">
    <div class="card h-100 invoice-detail-card">
      <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
          <span id="invoiceDetailTitle">Invoice Detail</span>
          <small class="d-block text-secondary" id="invoiceDetailMeta">Select an invoice</small>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-secondary btn-sm d-none" id="invoicePrintBtn" type="button"><i class="bi bi-printer"></i> Print</button>
          <button class="btn btn-outline-primary btn-sm d-none" id="invoiceRetryBtn" type="button"><i class="bi bi-arrow-repeat"></i> Retry</button>
        </div>
      </div>
      <div class="card-body" id="invoiceDetailBody">
        <div class="text-center text-secondary py-5">Select an invoice to view details.</div>
      </div>
    </div>
  </div>
</div>
