<?php
/** @var array $admin_context Injected by public/admin/view.php before include. */
$restaurantId = (int) ($admin_context['active_restaurant_id'] ?? 0);
$canUpdateRestaurant = admin_can($admin_context, 'restaurant.update');
?>
<script>
  window.RESTAURANT_SETTINGS_ID = <?= json_encode($restaurantId); ?>;
  window.RESTAURANT_TAX_SETTINGS_ENABLED = <?= json_encode($canUpdateRestaurant); ?>;
</script>

<div class="card">
  <div class="card-header d-flex align-items-center justify-content-between">
    <span>Restaurant Settings</span>
    <small class="text-secondary">ID and code are locked for restaurant staff</small>
  </div>
  <div class="card-body">
    <div class="alert alert-danger d-none" id="restaurantSettingsAlert"></div>
    <form id="restaurantSettingsForm" class="row g-3">
      <div class="col-md-2">
        <label class="form-label" for="settingsRestaurantId">ID</label>
        <input class="form-control" id="settingsRestaurantId" readonly>
      </div>
      <div class="col-md-5">
        <label class="form-label" for="settingsRestaurantName">Name</label>
        <input class="form-control" id="settingsRestaurantName" required>
      </div>
      <div class="col-md-5">
        <label class="form-label" for="settingsRestaurantCode">Code</label>
        <input class="form-control" id="settingsRestaurantCode" readonly>
      </div>
      <div class="col-12">
        <label class="form-label" for="settingsRestaurantLocation">Location</label>
        <input class="form-control" id="settingsRestaurantLocation" required>
      </div>
      <div class="col-md-6">
        <label class="form-label" for="settingsRestaurantManager">Phone Number</label>
        <input class="form-control" id="settingsRestaurantManager" required>
      </div>
      <div class="col-md-6">
        <label class="form-label" for="settingsRestaurantActiveUntil">Active Until</label>
        <input class="form-control" id="settingsRestaurantActiveUntil" type="date" required>
      </div>
      <div class="col-12">
        <label class="form-label" for="settingsRestaurantDetails">Details</label>
        <textarea class="form-control" id="settingsRestaurantDetails" rows="4" required></textarea>
      </div>
      <div class="col-12">
        <div class="settings-panel">
          <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <div>
              <h2 class="h6 mb-1">Customer Website</h2>
              <small class="text-secondary">Hero content, menu section text, image, and colors.</small>
            </div>
          </div>
          <input type="hidden" id="websiteHeroImageUrl">
          <input type="hidden" id="websiteLogoImageUrl">
          <div class="settings-website-grid">
            <div class="settings-media-stack">
              <div class="modal-media-panel">
                <div class="image-upload-preview image-upload-preview-logo" id="websiteLogoImagePreview">
                  <i class="bi bi-shop"></i>
                </div>
                <label class="form-label" for="websiteLogoImageFile">Logo Image</label>
                <input class="form-control" id="websiteLogoImageFile" type="file" accept="image/*">
                <div class="form-text">PNG, SVG, JPG, WEBP, or GIF. Keep it square for the cleanest result.</div>
              </div>
              <div class="modal-media-panel">
                <div class="image-upload-preview image-upload-preview-food" id="websiteHeroImagePreview">
                  <i class="bi bi-image"></i>
                </div>
                <label class="form-label" for="websiteHeroImageFile">Hero Image</label>
                <input class="form-control" id="websiteHeroImageFile" type="file" accept="image/*">
                <div class="form-text">Shown at the top of the customer ordering page. Saved as compressed WEBP.</div>
              </div>
            </div>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label" for="websiteBrandNameEn">Brand Name EN</label>
                <div class="form-control settings-html-editor" id="websiteBrandNameEn" contenteditable="true" data-html-editor="true"></div>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="websiteBrandNameAr">Brand Name AR</label>
                <div class="form-control settings-html-editor" id="websiteBrandNameAr" contenteditable="true" data-html-editor="true" dir="rtl"></div>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="websiteHeroTitleEn">Hero Title EN</label>
                <div class="form-control settings-html-editor" id="websiteHeroTitleEn" contenteditable="true" data-html-editor="true"></div>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="websiteHeroTitleAr">Hero Title AR</label>
                <div class="form-control settings-html-editor" id="websiteHeroTitleAr" contenteditable="true" data-html-editor="true" dir="rtl"></div>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="websiteHeroAccentEn">Hero Accent EN</label>
                <div class="form-control settings-html-editor" id="websiteHeroAccentEn" contenteditable="true" data-html-editor="true"></div>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="websiteHeroAccentAr">Hero Accent AR</label>
                <div class="form-control settings-html-editor" id="websiteHeroAccentAr" contenteditable="true" data-html-editor="true" dir="rtl"></div>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="websiteHeroEyebrowEn">Hero Eyebrow EN</label>
                <div class="form-control settings-html-editor" id="websiteHeroEyebrowEn" contenteditable="true" data-html-editor="true"></div>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="websiteHeroEyebrowAr">Hero Eyebrow AR</label>
                <div class="form-control settings-html-editor" id="websiteHeroEyebrowAr" contenteditable="true" data-html-editor="true" dir="rtl"></div>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="websiteHeroDescriptionEn">Hero Description EN</label>
                <div class="form-control settings-html-editor settings-html-editor-tall" id="websiteHeroDescriptionEn" contenteditable="true" data-html-editor="true"></div>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="websiteHeroDescriptionAr">Hero Description AR</label>
                <div class="form-control settings-html-editor settings-html-editor-tall" id="websiteHeroDescriptionAr" contenteditable="true" data-html-editor="true" dir="rtl"></div>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="websiteMenuTitleEn">Menu Title EN</label>
                <div class="form-control settings-html-editor" id="websiteMenuTitleEn" contenteditable="true" data-html-editor="true"></div>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="websiteMenuTitleAr">Menu Title AR</label>
                <div class="form-control settings-html-editor" id="websiteMenuTitleAr" contenteditable="true" data-html-editor="true" dir="rtl"></div>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="websiteMenuSubtitleEn">Menu Subtitle EN</label>
                <div class="form-control settings-html-editor settings-html-editor-tall" id="websiteMenuSubtitleEn" contenteditable="true" data-html-editor="true"></div>
              </div>
              <div class="col-md-6">
                <label class="form-label" for="websiteMenuSubtitleAr">Menu Subtitle AR</label>
                <div class="form-control settings-html-editor settings-html-editor-tall" id="websiteMenuSubtitleAr" contenteditable="true" data-html-editor="true" dir="rtl"></div>
              </div>
              <div class="col-12">
                <div class="settings-feature-toggle">
                  <div>
                    <strong>Order takeaway food</strong>
                    <small>Allow customers to order from a takeaway link without choosing a table.</small>
                  </div>
                  <div class="settings-feature-actions">
                    <a class="btn btn-outline-secondary btn-sm" id="takeawayOrderLink" href="#" target="_blank" rel="noopener">
                      <i class="bi bi-box-arrow-up-right"></i> Link
                    </a>
                    <div class="form-check form-switch mb-0">
                      <input class="form-check-input" type="checkbox" role="switch" id="takeawayEnabled">
                      <label class="form-check-label" for="takeawayEnabled">Enabled</label>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-12">
                <div class="settings-color-panel">
                  <div class="settings-color-panel-head">
                    <div>
                      <h3 class="h6 mb-1">Website Colors</h3>
                      <small class="text-secondary">Paste or edit the customer website <code>:root</code> color variables.</small>
                    </div>
                  </div>
                  <div class="settings-root-editor-wrap">
                    <textarea class="form-control settings-root-editor" id="websiteRootCss" spellcheck="false"></textarea>
                  </div>
                </div>
              </div>
              <div class="col-12">
                <div class="settings-preview-panel">
                  <div class="settings-preview-header">
                    <div>
                      <h3 class="h6 mb-1">Live Website Preview</h3>
                      <small class="text-secondary">Color changes update here before saving.</small>
                    </div>
                    <button class="btn btn-outline-secondary btn-sm" type="button" id="websitePreviewRefresh">
                      <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                  </div>
                  <div class="settings-preview-frame-wrap">
                    <iframe id="websitePreviewFrame" title="Customer website preview"></iframe>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-12">
        <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Save Changes</button>
      </div>
    </form>
  </div>
</div>

<?php if ($canUpdateRestaurant): ?>
<div class="card mt-3" id="taxSettingsCard">
  <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
      <span>Tax &amp; E-Invoicing</span>
      <small class="d-block text-secondary">الضريبة والفوترة الإلكترونية</small>
    </div>
    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle" id="taxConfigurationStatus">Not configured</span>
  </div>
  <div class="card-body">
    <div class="alert alert-info">
      JoFotara credentials are obtained from the Jordan Income and Sales Tax Department National Electronic Invoicing portal. The Secret Key is stored securely and will not be displayed again.
    </div>
    <div class="alert alert-danger d-none" id="taxSettingsAlert"></div>
    <form id="taxSettingsForm" class="row g-3">
      <div class="col-lg-6">
        <div class="settings-panel h-100">
          <h2 class="h6 mb-3">Tax Registration</h2>
          <label class="form-label" for="taxpayerType">Taxpayer Type</label>
          <select class="form-select mb-2" id="taxpayerType">
            <option value="income_tax_only">Income tax only</option>
            <option value="general_sales_tax">General sales tax</option>
            <option value="special_sales_tax">Special sales tax</option>
          </select>
          <small class="text-secondary d-block mb-3">Do not assume 16%. Select the restaurant's real tax registration.</small>
          <label class="form-label" for="legalSellerName">Legal seller name</label>
          <input class="form-control mb-2" id="legalSellerName">
          <label class="form-label" for="tradeName">Trade name</label>
          <input class="form-control mb-2" id="tradeName">
          <label class="form-label" for="sellerTaxNumber">Tax number / TIN</label>
          <input class="form-control mb-2" id="sellerTaxNumber">
          <label class="form-label" for="sellerNationalNumber">National number</label>
          <input class="form-control mb-2" id="sellerNationalNumber">
          <label class="form-label" for="sellerAddress">Address</label>
          <input class="form-control mb-2" id="sellerAddress">
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label" for="sellerCity">City / Governorate</label>
              <input class="form-control" id="sellerCity">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="sellerPhone">Phone</label>
              <input class="form-control" id="sellerPhone">
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="settings-panel h-100">
          <h2 class="h6 mb-3">JoFotara Integration</h2>
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" role="switch" id="einvoicingEnabled">
            <label class="form-check-label" for="einvoicingEnabled">Enable Jordan E-Invoicing</label>
          </div>
          <label class="form-label" for="jofotaraClientId">Client ID / User Number</label>
          <input class="form-control mb-2" id="jofotaraClientId" autocomplete="off">
          <label class="form-label" for="jofotaraSecretKey">Secret Key</label>
          <input class="form-control mb-2" id="jofotaraSecretKey" autocomplete="new-password" placeholder="Leave blank to keep current secret">
          <label class="form-label" for="incomeSourceSequence">Income Source Sequence / Activity Number</label>
          <input class="form-control mb-3" id="incomeSourceSequence">
          <button class="btn btn-outline-secondary btn-sm" id="testTaxConnectionBtn" type="button">
            <i class="bi bi-plug"></i> Test Connection
          </button>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="settings-panel h-100">
          <h2 class="h6 mb-3">Tax Calculation</h2>
          <label class="form-label" for="defaultTaxRate">Default tax rate</label>
          <input class="form-control mb-3" id="defaultTaxRate" type="number" min="0" max="100" step="0.001">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="pricesIncludeTax">
            <label class="form-check-label" for="pricesIncludeTax">Menu prices include tax</label>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="settings-panel h-100">
          <h2 class="h6 mb-3">Invoice Settings</h2>
          <label class="form-label" for="invoicePrefix">Invoice prefix</label>
          <input class="form-control mb-3" id="invoicePrefix">
          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" role="switch" id="automaticSubmission">
            <label class="form-check-label" for="automaticSubmission">Submit automatically after payment</label>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="printAfterAccepted">
            <label class="form-check-label" for="printAfterAccepted">Print invoice after accepted</label>
          </div>
          <hr>
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" role="switch" id="invoicePrintFullPage">
            <label class="form-check-label" for="invoicePrintFullPage">Print as full page</label>
          </div>
          <div class="row g-2" id="invoicePrintSizeFields">
            <div class="col-md-6">
              <label class="form-label" for="invoicePrintWidth">Width (mm)</label>
              <input class="form-control" id="invoicePrintWidth" type="number" min="40" max="300" step="1">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="invoicePrintHeight">Height (mm)</label>
              <input class="form-control" id="invoicePrintHeight" type="number" min="80" max="500" step="1">
            </div>
          </div>
        </div>
      </div>
      <div class="col-12">
        <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Save Tax Settings</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
