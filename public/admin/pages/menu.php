<?php
$restaurantId = (int) ($admin_context['active_restaurant_id'] ?? 0);
$canCreateCategories = admin_can($admin_context, 'categories.create');
$canUpdateCategories = admin_can($admin_context, 'categories.update');
$canDeleteCategories = admin_can($admin_context, 'categories.delete');
$canCreateFoods = admin_can($admin_context, 'foods.create');
$canUpdateFoods = admin_can($admin_context, 'foods.update');
$canDeleteFoods = admin_can($admin_context, 'foods.delete');
$menuSection = strtolower((string) ($_GET['menu_section'] ?? 'foods'));
if (!in_array($menuSection, ['foods', 'addons', 'categories'], true)) {
    $menuSection = 'foods';
}
?>
<script>
  window.MENU_PAGE = <?= json_encode([
      'restaurant_id' => $restaurantId,
      'section' => $menuSection,
      'can_create_categories' => $canCreateCategories,
      'can_update_categories' => $canUpdateCategories,
      'can_delete_categories' => $canDeleteCategories,
      'can_create_foods' => $canCreateFoods,
      'can_update_foods' => $canUpdateFoods,
      'can_delete_foods' => $canDeleteFoods,
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>

<div class="row g-3">
  <div class="col-12<?= $menuSection === 'categories' ? '' : ' d-none'; ?>" data-menu-section="categories">
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <span>Categories</span>
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-outline-secondary btn-sm" type="button" id="categoryTableBtn" data-bs-toggle="modal" data-bs-target="#categoryTableModal">
            <i class="bi bi-table"></i> Table
          </button>
          <?php if ($canCreateCategories): ?>
            <button class="btn btn-primary btn-sm" type="button" id="categoryAddBtn" data-bs-toggle="modal" data-bs-target="#categoryModal">
              <i class="bi bi-plus-lg"></i> Add Category
            </button>
          <?php endif; ?>
        </div>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Name</th>
                <th>Description</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody id="menuCategoriesBody">
              <tr><td colspan="3" class="text-center text-secondary py-4">Loading categories...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12<?= $menuSection === 'foods' ? '' : ' d-none'; ?>" data-menu-section="foods">
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <span>Foods</span>
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-outline-secondary btn-sm" type="button" id="foodTableBtn" data-bs-toggle="modal" data-bs-target="#foodTableModal">
            <i class="bi bi-table"></i> Table
          </button>
          <?php if ($canCreateFoods): ?>
            <button class="btn btn-primary btn-sm" type="button" id="foodAddBtn" data-bs-toggle="modal" data-bs-target="#foodModal">
              <i class="bi bi-plus-lg"></i> Add Food
            </button>
          <?php endif; ?>
        </div>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Food</th>
                <th>Category</th>
                <th>Price</th>
                <th>Tax</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody id="menuFoodsBody">
              <tr><td colspan="5" class="text-center text-secondary py-4">Loading foods...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12<?= $menuSection === 'addons' ? '' : ' d-none'; ?>" data-menu-section="addons">
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
          <span>Food Addons</span>
          <div class="modal-subtitle">Addons can be assigned to one food or used as category defaults.</div>
        </div>
        <?php if ($canCreateFoods): ?>
          <button class="btn btn-primary btn-sm" type="button" id="addonAddBtn" data-bs-toggle="modal" data-bs-target="#addonModal">
            <i class="bi bi-plus-lg"></i> Add Addon
          </button>
        <?php endif; ?>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Addon</th>
                <th>Scope</th>
                <th>Extra Price</th>
                <th>Profit</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody id="menuAddonsBody">
              <tr><td colspan="5" class="text-center text-secondary py-4">Loading addons...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="categoryTableModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content admin-form-modal">
      <div class="modal-header">
        <div>
          <h1 class="modal-title fs-6">Categories Table</h1>
          <div class="modal-subtitle">Full category list with pagination.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <div class="menu-table-tools">
          <input class="form-control" id="categoryTableSearch" placeholder="Search categories...">
          <small class="text-secondary" id="categoryTableShowing"></small>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Name</th>
                <th>Description</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody id="menuCategoriesFullBody">
              <tr><td colspan="3" class="text-center text-secondary py-4">Loading categories...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <button class="btn btn-outline-secondary btn-sm" type="button" id="categoryTablePrev"><i class="bi bi-chevron-left"></i> Prev</button>
        <span class="small text-secondary" id="categoryTablePage"></span>
        <button class="btn btn-outline-secondary btn-sm" type="button" id="categoryTableNext">Next <i class="bi bi-chevron-right"></i></button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="foodTableModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content admin-form-modal">
      <div class="modal-header">
        <div>
          <h1 class="modal-title fs-6">Foods Table</h1>
          <div class="modal-subtitle">Full food list with pagination.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <div class="menu-table-tools">
          <input class="form-control" id="foodTableSearch" placeholder="Search foods...">
          <small class="text-secondary" id="foodTableShowing"></small>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Food</th>
                <th>Category</th>
                <th>Price</th>
                <th>Tax</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody id="menuFoodsFullBody">
              <tr><td colspan="5" class="text-center text-secondary py-4">Loading foods...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer justify-content-between">
        <button class="btn btn-outline-secondary btn-sm" type="button" id="foodTablePrev"><i class="bi bi-chevron-left"></i> Prev</button>
        <span class="small text-secondary" id="foodTablePage"></span>
        <button class="btn btn-outline-secondary btn-sm" type="button" id="foodTableNext">Next <i class="bi bi-chevron-right"></i></button>
      </div>
    </div>
  </div>
</div>

<?php if ($canCreateCategories || $canUpdateCategories): ?>
<div class="modal fade" id="categoryModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form class="modal-content admin-form-modal" id="categoryForm" autocomplete="off">
      <div class="modal-header">
        <div>
          <h1 class="modal-title fs-6" id="categoryModalTitle">Add Category</h1>
          <div class="modal-subtitle">Menu grouping shown to staff and guests.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger d-none" id="categoryFormAlert"></div>
        <input type="hidden" id="categoryId">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="categoryNameEn">Name EN</label>
            <input class="form-control" id="categoryNameEn" required>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="categoryNameAr">Name AR</label>
            <input class="form-control" id="categoryNameAr" required>
          </div>
          <div class="col-12">
            <label class="form-label" for="categoryDescriptionEn">Description EN</label>
            <textarea class="form-control" id="categoryDescriptionEn" rows="3"></textarea>
          </div>
          <div class="col-12">
            <label class="form-label" for="categoryDescriptionAr">Description AR</label>
            <textarea class="form-control" id="categoryDescriptionAr" rows="3"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit">
          <i class="bi bi-save"></i> Save Category
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if ($canCreateFoods || $canUpdateFoods): ?>
<div class="modal fade" id="addonModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form class="modal-content admin-form-modal" id="addonForm" autocomplete="off">
      <div class="modal-header">
        <div>
          <h1 class="modal-title fs-6" id="addonModalTitle">Add Addon</h1>
          <div class="modal-subtitle">Assign this addon to a single food or to all foods in a category.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger d-none" id="addonFormAlert"></div>
        <input type="hidden" id="addonId">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="addonNameEn">Name EN</label>
            <input class="form-control" id="addonNameEn" required>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="addonNameAr">Name AR</label>
            <input class="form-control" id="addonNameAr" required>
          </div>
          <div class="col-12">
            <label class="form-label" for="addonScope">Addon For</label>
            <select class="form-select" id="addonScope">
              <option value="food">Single food</option>
              <option value="category">Category default</option>
            </select>
          </div>
          <div class="col-12" id="addonFoodGroup">
            <label class="form-label" for="addonFoodId">Food</label>
            <select class="form-select" id="addonFoodId"></select>
          </div>
          <div class="col-12 d-none" id="addonCategoryGroup">
            <label class="form-label" for="addonCategoryId">Category</label>
            <select class="form-select" id="addonCategoryId"></select>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="addonExtraPrice">Extra Price</label>
            <input class="form-control" id="addonExtraPrice" type="number" min="0" step="0.001" value="0">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="addonExtraProfit">Extra Profit</label>
            <input class="form-control" id="addonExtraProfit" type="number" min="0" step="0.001" value="0">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit">
          <i class="bi bi-save"></i> Save Addon
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if ($canCreateFoods || $canUpdateFoods): ?>
<div class="modal fade" id="foodModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <form class="modal-content admin-form-modal" id="foodForm" autocomplete="off">
      <div class="modal-header">
        <div>
          <h1 class="modal-title fs-6" id="foodModalTitle">Add Food</h1>
          <div class="modal-subtitle">Food details, image, pricing, and tax setup.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger d-none" id="foodFormAlert"></div>
        <input type="hidden" id="foodId">
        <input type="hidden" id="foodImageUrl">
        <div class="modal-form-grid">
          <div class="modal-media-panel">
            <div class="image-upload-preview image-upload-preview-food" id="foodImagePreview">
              <i class="bi bi-image"></i>
            </div>
            <label class="form-label" for="foodImageFile">Food Image</label>
            <input class="form-control" id="foodImageFile" type="file" accept="image/*">
            <div class="form-text">Images are scanned, compressed, and saved as WEBP. Max 5MB.</div>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="foodNameEn">Name EN</label>
              <input class="form-control" id="foodNameEn" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="foodNameAr">Name AR</label>
              <input class="form-control" id="foodNameAr" required>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="foodCategoryId">Category</label>
              <select class="form-select" id="foodCategoryId" required>
                <option value="">Loading categories...</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label" for="foodPrice">Price</label>
              <input class="form-control" id="foodPrice" type="number" min="0" step="0.001" required>
            </div>
            <div class="col-md-3">
              <label class="form-label" for="foodProfit">Profit</label>
              <input class="form-control" id="foodProfit" type="number" min="0" step="0.001" value="0">
            </div>
            <div class="col-12">
              <label class="form-label" for="foodDescriptionEn">Description EN</label>
              <textarea class="form-control" id="foodDescriptionEn" rows="3"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label" for="foodDescriptionAr">Description AR</label>
              <textarea class="form-control" id="foodDescriptionAr" rows="3"></textarea>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="foodTaxCategory">Tax</label>
              <select class="form-select" id="foodTaxCategory">
                <option value="default">Use restaurant default</option>
                <option value="S">Standard taxable</option>
                <option value="Z">Zero rate</option>
                <option value="O">Outside scope</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="foodTaxRate">Tax rate override</label>
              <input class="form-control" id="foodTaxRate" type="number" min="0" max="100" step="0.001" placeholder="Default">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="foodSpecialTax">Special tax amount</label>
              <input class="form-control" id="foodSpecialTax" type="number" min="0" step="0.001" value="0">
            </div>
            <div class="col-12">
              <div class="modal-switch-row">
                <label class="form-check-label" for="foodTaxExempt">Tax exempt</label>
                <input class="form-check-input modal-switch-control" type="checkbox" role="switch" id="foodTaxExempt">
              </div>
            </div>
            <div class="col-12">
              <div class="modal-switch-row">
                <label class="form-check-label" for="foodNoteEnabled">Enable chef note input</label>
                <input class="form-check-input modal-switch-control" type="checkbox" role="switch" id="foodNoteEnabled">
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" type="submit">
          <i class="bi bi-save"></i> Save Food
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
