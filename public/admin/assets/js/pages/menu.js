/* global Chart */

( function () {
  function initMenuPage() {
    var categoriesBody = document.getElementById('menuCategoriesBody');
    var foodsBody = document.getElementById('menuFoodsBody');
    var addonsBody = document.getElementById('menuAddonsBody');
    if (!categoriesBody || !foodsBody) return;

    var page = window.MENU_PAGE || {};
    var restaurantId = Number(page.restaurant_id || activeRestaurantId || 0);
    var canCreateCategories = !!page.can_create_categories;
    var canUpdateCategories = !!page.can_update_categories;
    var canDeleteCategories = !!page.can_delete_categories;
    var canCreateFoods = !!page.can_create_foods;
    var canUpdateFoods = !!page.can_update_foods;
    var canDeleteFoods = !!page.can_delete_foods;
    var categories = [];
    var foods = [];
    var addons = [];
    var categoryForm = document.getElementById('categoryForm');
    var foodForm = document.getElementById('foodForm');
    var addonForm = document.getElementById('addonForm');
    var categoryModalEl = document.getElementById('categoryModal');
    var foodModalEl = document.getElementById('foodModal');
    var categoryTableModalEl = document.getElementById('categoryTableModal');
    var foodTableModalEl = document.getElementById('foodTableModal');
    var categoryModal = categoryModalEl ? new bootstrap.Modal(categoryModalEl) : null;
    var foodModal = foodModalEl ? new bootstrap.Modal(foodModalEl) : null;
    var addonModalEl = document.getElementById('addonModal');
    var addonModal = addonModalEl ? new bootstrap.Modal(addonModalEl) : null;
    var foodImageFile = document.getElementById('foodImageFile');
    var foodImagePreview = document.getElementById('foodImagePreview');
    var categoriesFullBody = document.getElementById('menuCategoriesFullBody');
    var foodsFullBody = document.getElementById('menuFoodsFullBody');
    var categoryTableSearch = document.getElementById('categoryTableSearch');
    var foodTableSearch = document.getElementById('foodTableSearch');
    var categoryTableShowing = document.getElementById('categoryTableShowing');
    var foodTableShowing = document.getElementById('foodTableShowing');
    var categoryTablePage = document.getElementById('categoryTablePage');
    var foodTablePage = document.getElementById('foodTablePage');
    var categoryPage = 1;
    var foodPage = 1;
    var tablePageSize = 8;

    function categoryPayload() {
      return {
        name_en: document.getElementById('categoryNameEn').value.trim(),
        name_ar: document.getElementById('categoryNameAr').value.trim(),
        description_en: document.getElementById('categoryDescriptionEn').value.trim(),
        description_ar: document.getElementById('categoryDescriptionAr').value.trim(),
        restaurant_id: restaurantId
      };
    }

    function foodPayload() {
      return {
        name_en: document.getElementById('foodNameEn').value.trim(),
        name_ar: document.getElementById('foodNameAr').value.trim(),
        description_en: document.getElementById('foodDescriptionEn').value.trim(),
        description_ar: document.getElementById('foodDescriptionAr').value.trim(),
        image_url: document.getElementById('foodImageUrl').value.trim(),
        price: Number(document.getElementById('foodPrice').value || 0),
        profit: Number(document.getElementById('foodProfit').value || 0),
        category_id: Number(document.getElementById('foodCategoryId').value || 0),
        restaurant_id: restaurantId,
        tax_category: document.getElementById('foodTaxCategory').value,
        tax_rate: document.getElementById('foodTaxRate').value.trim(),
        special_tax_amount: Number(document.getElementById('foodSpecialTax').value || 0),
        tax_exempt: document.getElementById('foodTaxExempt').checked ? 1 : 0,
        note_enabled: document.getElementById('foodNoteEnabled').checked ? 1 : 0
      };
    }

    function addonPayload() {
      var scope = document.getElementById('addonScope').value;
      return {
        name_en: document.getElementById('addonNameEn').value.trim(),
        name_ar: document.getElementById('addonNameAr').value.trim(),
        food_id: scope === 'food' ? Number(document.getElementById('addonFoodId').value || 0) : null,
        category_id: scope === 'category' ? Number(document.getElementById('addonCategoryId').value || 0) : null,
        extra_price: Number(document.getElementById('addonExtraPrice').value || 0),
        extra_profit: Number(document.getElementById('addonExtraProfit').value || 0),
        restaurant_id: restaurantId
      };
    }

    function fillCategory(category) {
      if (!categoryForm) return;
      categoryForm.reset();
      document.getElementById('categoryId').value = category ? category.id : '';
      document.getElementById('categoryNameEn').value = category ? text(category.name_en) : '';
      document.getElementById('categoryNameAr').value = category ? text(category.name_ar) : '';
      document.getElementById('categoryDescriptionEn').value = category ? text(category.description_en) : '';
      document.getElementById('categoryDescriptionAr').value = category ? text(category.description_ar) : '';
      document.getElementById('categoryModalTitle').textContent = category ? 'Edit Category' : 'Add Category';
      hideFormError('categoryFormAlert');
    }

    function fillFood(food) {
      if (!foodForm) return;
      foodForm.reset();
      document.getElementById('foodId').value = food ? food.id : '';
      document.getElementById('foodNameEn').value = food ? text(food.name_en) : '';
      document.getElementById('foodNameAr').value = food ? text(food.name_ar) : '';
      document.getElementById('foodDescriptionEn').value = food ? text(food.description_en) : '';
      document.getElementById('foodDescriptionAr').value = food ? text(food.description_ar) : '';
      document.getElementById('foodImageUrl').value = food ? text(food.image_url) : '';
      if (foodImageFile) foodImageFile.value = '';
      if (foodImageFile) foodImageFile.required = !food;
      setImagePreview(foodImagePreview, food ? text(food.image_url) : '', 'bi bi-image');
      document.getElementById('foodPrice').value = food ? text(food.original_price || food.price) : '';
      document.getElementById('foodProfit').value = food ? text(food.profit, '0') : '0';
      document.getElementById('foodCategoryId').value = food ? text(food.category_id) : '';
      document.getElementById('foodTaxCategory').value = food ? text(food.tax_category, 'default') : 'default';
      document.getElementById('foodTaxRate').value = food && food.tax_rate !== null && food.tax_rate !== undefined ? text(food.tax_rate) : '';
      document.getElementById('foodSpecialTax').value = food ? text(food.special_tax_amount, '0') : '0';
      document.getElementById('foodTaxExempt').checked = Number(food ? food.tax_exempt : 0) === 1;
      document.getElementById('foodNoteEnabled').checked = Number(food ? food.note_enabled : 0) === 1;
      document.getElementById('foodModalTitle').textContent = food ? 'Edit Food' : 'Add Food';
      hideFormError('foodFormAlert');
    }

    function setAddonScope(scope) {
      var isCategory = scope === 'category';
      var foodGroup = document.getElementById('addonFoodGroup');
      var categoryGroup = document.getElementById('addonCategoryGroup');
      var foodSelect = document.getElementById('addonFoodId');
      var categorySelect = document.getElementById('addonCategoryId');
      document.getElementById('addonScope').value = isCategory ? 'category' : 'food';
      if (foodGroup) foodGroup.classList.toggle('d-none', isCategory);
      if (categoryGroup) categoryGroup.classList.toggle('d-none', !isCategory);
      if (foodSelect) foodSelect.required = !isCategory;
      if (categorySelect) categorySelect.required = isCategory;
    }

    function fillAddon(addon) {
      if (!addonForm) return;
      addonForm.reset();
      renderAddonTargetOptions();
      document.getElementById('addonId').value = addon ? addon.id : '';
      document.getElementById('addonNameEn').value = addon ? text(addon.name_en) : '';
      document.getElementById('addonNameAr').value = addon ? text(addon.name_ar) : '';
      document.getElementById('addonExtraPrice').value = addon ? text(addon.original_extra_price || addon.extra_price, '0') : '0';
      document.getElementById('addonExtraProfit').value = addon ? text(addon.extra_profit, '0') : '0';
      setAddonScope(addon && addon.category_id ? 'category' : 'food');
      document.getElementById('addonFoodId').value = addon && addon.food_id ? text(addon.food_id) : '';
      document.getElementById('addonCategoryId').value = addon && addon.category_id ? text(addon.category_id) : '';
      document.getElementById('addonModalTitle').textContent = addon ? 'Edit Addon' : 'Add Addon';
      hideFormError('addonFormAlert');
    }

    function renderCategoryOptions() {
      var select = document.getElementById('foodCategoryId');
      if (!select) return;

      var selected = select.value;
      select.innerHTML = '<option value="">Select category</option>' + categories.map(function (category) {
        return '<option value="' + escapeHtml(category.id) + '"' + (String(selected) === String(category.id) ? ' selected' : '') + '>' +
          escapeHtml(category.name_en || category.name_ar || ('Category #' + category.id)) +
        '</option>';
      }).join('');
    }

    function renderAddonTargetOptions() {
      var foodSelect = document.getElementById('addonFoodId');
      var categorySelect = document.getElementById('addonCategoryId');

      if (foodSelect) {
        foodSelect.innerHTML = '<option value="">Select food</option>' + foods.map(function (food) {
          return '<option value="' + escapeHtml(food.id) + '">' +
            escapeHtml(food.name_en || food.name_ar || ('Food #' + food.id)) +
          '</option>';
        }).join('');
      }

      if (categorySelect) {
        categorySelect.innerHTML = '<option value="">Select category</option>' + categories.map(function (category) {
          return '<option value="' + escapeHtml(category.id) + '">' +
            escapeHtml(category.name_en || category.name_ar || ('Category #' + category.id)) +
          '</option>';
        }).join('');
      }
    }

    function categoryActions(category) {
        var actions = '';
        if (canUpdateCategories) {
          actions += '<button class="btn btn-sm btn-outline-primary menu-category-edit" type="button" data-id="' + escapeHtml(category.id) + '"><i class="bi bi-pencil"></i></button> ';
        }
        if (canDeleteCategories) {
          actions += '<button class="btn btn-sm btn-outline-danger menu-category-delete" type="button" data-id="' + escapeHtml(category.id) + '"><i class="bi bi-trash"></i></button>';
        }

        return actions || '<span class="text-secondary">-</span>';
    }

    function categoryRow(category) {
        return '<tr>' +
          '<td><div class="fw-semibold">' + escapeHtml(category.name_en || '-') + '</div><small class="text-secondary">' + escapeHtml(category.name_ar || '-') + '</small></td>' +
          '<td class="small text-secondary">' + escapeHtml(category.description_en || category.description_ar || '-') + '</td>' +
          '<td class="text-end">' + categoryActions(category) + '</td>' +
        '</tr>';
    }

    function foodActions(food) {
      var actions = '';
      if (canUpdateFoods) {
        actions += '<button class="btn btn-sm btn-outline-primary menu-food-edit" type="button" data-id="' + escapeHtml(food.id) + '"><i class="bi bi-pencil"></i></button> ';
      }
      if (canDeleteFoods) {
        actions += '<button class="btn btn-sm btn-outline-danger menu-food-delete" type="button" data-id="' + escapeHtml(food.id) + '"><i class="bi bi-trash"></i></button>';
      }

      return actions || '<span class="text-secondary">-</span>';
    }

    function foodRow(food) {
        var tax = food.tax_exempt ? 'Exempt' : (food.tax_category === 'default' ? 'Default' : food.tax_category);
        if (food.tax_rate !== null && food.tax_rate !== undefined && food.tax_rate !== '') {
          tax += ' / ' + Number(food.tax_rate).toFixed(3) + '%';
        }
        var hasDiscount = Number(food.discount_amount || 0) > 0 && Number(food.discounted_price || food.price) < Number(food.original_price || food.price);
        var priceHtml = hasDiscount
          ? '<div class="fw-semibold text-success">' + money(food.discounted_price) + '</div><small class="text-secondary text-decoration-line-through">' + money(food.original_price || food.price) + '</small>'
          : money(food.price);

        return '<tr>' +
          '<td><div class="d-flex align-items-center gap-2"><span class="menu-food-thumb">' + (food.image_url ? '<img src="' + escapeHtml(food.image_url) + '" alt="">' : '<i class="bi bi-image"></i>') + '</span><span><div class="fw-semibold">' + escapeHtml(food.name_en || '-') + '</div><small class="text-secondary">' + escapeHtml(food.name_ar || '-') + '</small></span></div></td>' +
          '<td>' + escapeHtml(food.category_name_en || food.category_name_ar || '-') + '</td>' +
          '<td>' + priceHtml + '</td>' +
          '<td>' + escapeHtml(tax) + '</td>' +
          '<td class="text-end">' + foodActions(food) + '</td>' +
        '</tr>';
    }

    function addonActions(addon) {
      var actions = '';
      if (canUpdateFoods) {
        actions += '<button class="btn btn-sm btn-outline-primary menu-addon-edit" type="button" data-id="' + escapeHtml(addon.id) + '"><i class="bi bi-pencil"></i></button> ';
      }
      if (canDeleteFoods) {
        actions += '<button class="btn btn-sm btn-outline-danger menu-addon-delete" type="button" data-id="' + escapeHtml(addon.id) + '"><i class="bi bi-trash"></i></button>';
      }

      return actions || '<span class="text-secondary">-</span>';
    }

    function addonScopeLabel(addon) {
      if (addon.category_id) {
        return '<span class="badge text-bg-info">Category default</span> ' +
          escapeHtml(addon.category_name_en || addon.category_name_ar || ('Category #' + addon.category_id));
      }

      return '<span class="badge text-bg-secondary">Single food</span> ' +
        escapeHtml(addon.food_name_en || addon.food_name_ar || ('Food #' + addon.food_id));
    }

    function addonRow(addon) {
      var hasDiscount = Number(addon.discount_amount || 0) > 0 && Number(addon.discounted_extra_price || addon.extra_price) < Number(addon.original_extra_price || addon.extra_price);
      var priceHtml = hasDiscount
        ? '<div class="fw-semibold text-success">' + money(addon.discounted_extra_price) + '</div><small class="text-secondary text-decoration-line-through">' + money(addon.original_extra_price || addon.extra_price) + '</small>'
        : money(addon.extra_price);

      return '<tr>' +
        '<td><div class="fw-semibold">' + escapeHtml(addon.name_en || '-') + '</div><small class="text-secondary">' + escapeHtml(addon.name_ar || '-') + '</small></td>' +
        '<td>' + addonScopeLabel(addon) + '</td>' +
        '<td>' + priceHtml + '</td>' +
        '<td>' + money(addon.extra_profit) + '</td>' +
        '<td class="text-end">' + addonActions(addon) + '</td>' +
      '</tr>';
    }

    function renderCategoryTable() {
      if (!categoriesFullBody) return;
      categoryPage = AdminUI.renderPaginatedTable({
        rows: AdminUI.filteredList(categories, categoryTableSearch, ['name_en', 'name_ar', 'description_en', 'description_ar']),
        page: categoryPage,
        pageSize: tablePageSize,
        body: categoriesFullBody,
        showing: categoryTableShowing,
        pageLabel: categoryTablePage,
        prevBtn: document.getElementById('categoryTablePrev'),
        nextBtn: document.getElementById('categoryTableNext'),
        rowRenderer: categoryRow,
        emptyColspan: 3,
        emptyText: 'No categories found.'
      });
    }

    function renderFoodTable() {
      if (!foodsFullBody) return;
      foodPage = AdminUI.renderPaginatedTable({
        rows: AdminUI.filteredList(foods, foodTableSearch, ['name_en', 'name_ar', 'description_en', 'description_ar', 'category_name_en', 'category_name_ar']),
        page: foodPage,
        pageSize: tablePageSize,
        body: foodsFullBody,
        showing: foodTableShowing,
        pageLabel: foodTablePage,
        prevBtn: document.getElementById('foodTablePrev'),
        nextBtn: document.getElementById('foodTableNext'),
        rowRenderer: foodRow,
        emptyColspan: 5,
        emptyText: 'No foods found.'
      });
    }

    function renderMenu() {
      categoriesBody.innerHTML = categories.slice(0, 5).map(categoryRow).join('') || '<tr><td colspan="3" class="text-center text-secondary py-4">No categories yet.</td></tr>';
      foodsBody.innerHTML = foods.slice(0, 5).map(foodRow).join('') || '<tr><td colspan="5" class="text-center text-secondary py-4">No foods yet.</td></tr>';
      if (addonsBody) {
        addonsBody.innerHTML = addons.slice(0, 5).map(addonRow).join('') || '<tr><td colspan="5" class="text-center text-secondary py-4">No addons yet.</td></tr>';
      }

      renderCategoryOptions();
      renderAddonTargetOptions();
      renderCategoryTable();
      renderFoodTable();
    }

    function loadMenu() {
      Promise.all([
        request('/menu-categories'),
        request('/menu-foods'),
        request('/food-addons')
      ]).then(function (results) {
        categories = results[0].data || [];
        foods = results[1].data || [];
        addons = results[2].data || [];
        categoryPage = 1;
        foodPage = 1;
        renderMenu();
      }).catch(function (error) {
        categoriesBody.innerHTML = '<tr><td colspan="3" class="text-center text-danger py-4">' + escapeHtml(error.message || 'Unable to load categories.') + '</td></tr>';
        foodsBody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">' + escapeHtml(error.message || 'Unable to load foods.') + '</td></tr>';
        if (addonsBody) {
          addonsBody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-4">' + escapeHtml(error.message || 'Unable to load addons.') + '</td></tr>';
        }
      });
    }

    var categoryAddBtn = document.getElementById('categoryAddBtn');
    if (categoryAddBtn) {
      categoryAddBtn.addEventListener('click', function () {
        fillCategory(null);
      });
    }

    function handleCategoryTableClick(event) {
      var edit = event.target.closest('.menu-category-edit');
      var del = event.target.closest('.menu-category-delete');

      if (edit) {
        var category = categories.find(function (row) { return String(row.id) === String(edit.dataset.id); });
        if (!category || !categoryForm) return;
        fillCategory(category);
        hideOpenModal(categoryTableModalEl);
        if (categoryModal) categoryModal.show();
      }

      if (del) {
        swalConfirm('Delete this category?', 'Delete category').then(function (confirmed) {
          if (!confirmed) return;
          request('/menu-categories/' + del.dataset.id, { method: 'DELETE' }).then(function () {
            swalToast('Category deleted');
            loadMenu();
          }).catch(function (error) {
            swalToast(error.message || 'Unable to delete category', 'error');
          });
        });
      }
    }

    categoriesBody.addEventListener('click', handleCategoryTableClick);
    if (categoriesFullBody) categoriesFullBody.addEventListener('click', handleCategoryTableClick);

    if (categoryForm) {
      categoryForm.addEventListener('submit', function (event) {
        event.preventDefault();
        hideFormError('categoryFormAlert');
        var id = document.getElementById('categoryId').value;
        request('/menu-categories' + (id ? '/' + id : ''), {
          method: id ? 'PUT' : 'POST',
          body: JSON.stringify(categoryPayload())
        }).then(function () {
          fillCategory(null);
          if (categoryModal) categoryModal.hide();
          swalToast(id ? 'Category updated' : 'Category added');
          loadMenu();
        }).catch(function (error) {
          showFormError('categoryFormAlert', error, 'Unable to save category.');
        });
      });
    }

    var foodAddBtn = document.getElementById('foodAddBtn');
    if (foodAddBtn) {
      foodAddBtn.addEventListener('click', function () {
        renderCategoryOptions();
        fillFood(null);
      });
    }

    function handleFoodTableClick(event) {
      var edit = event.target.closest('.menu-food-edit');
      var del = event.target.closest('.menu-food-delete');

      if (edit) {
        var food = foods.find(function (row) { return String(row.id) === String(edit.dataset.id); });
        if (!food || !foodForm) return;
        renderCategoryOptions();
        fillFood(food);
        hideOpenModal(foodTableModalEl);
        if (foodModal) foodModal.show();
      }

      if (del) {
        swalConfirm('Delete this food?', 'Delete food').then(function (confirmed) {
          if (!confirmed) return;
          request('/menu-foods/' + del.dataset.id, { method: 'DELETE' }).then(function () {
            swalToast('Food deleted');
            loadMenu();
          }).catch(function (error) {
            swalToast(error.message || 'Unable to delete food', 'error');
          });
        });
      }
    }

    foodsBody.addEventListener('click', handleFoodTableClick);
    if (foodsFullBody) foodsFullBody.addEventListener('click', handleFoodTableClick);

    var addonAddBtn = document.getElementById('addonAddBtn');
    if (addonAddBtn) {
      addonAddBtn.addEventListener('click', function () {
        fillAddon(null);
      });
    }

    var addonScope = document.getElementById('addonScope');
    if (addonScope) {
      addonScope.addEventListener('change', function () {
        setAddonScope(addonScope.value);
      });
    }

    function handleAddonTableClick(event) {
      var edit = event.target.closest('.menu-addon-edit');
      var del = event.target.closest('.menu-addon-delete');

      if (edit) {
        var addon = addons.find(function (row) { return String(row.id) === String(edit.dataset.id); });
        if (!addon || !addonForm) return;
        fillAddon(addon);
        if (addonModal) addonModal.show();
      }

      if (del) {
        swalConfirm('Delete this addon?', 'Delete addon').then(function (confirmed) {
          if (!confirmed) return;
          request('/food-addons/' + del.dataset.id, { method: 'DELETE' }).then(function () {
            swalToast('Addon deleted');
            loadMenu();
          }).catch(function (error) {
            swalToast(error.message || 'Unable to delete addon', 'error');
          });
        });
      }
    }

    if (addonsBody) addonsBody.addEventListener('click', handleAddonTableClick);

    if (addonForm) {
      addonForm.addEventListener('submit', function (event) {
        event.preventDefault();
        hideFormError('addonFormAlert');
        var id = document.getElementById('addonId').value;
        request('/food-addons' + (id ? '/' + id : ''), {
          method: id ? 'PUT' : 'POST',
          body: JSON.stringify(addonPayload())
        }).then(function () {
          fillAddon(null);
          if (addonModal) addonModal.hide();
          swalToast(id ? 'Addon updated' : 'Addon added');
          loadMenu();
        }).catch(function (error) {
          showFormError('addonFormAlert', error, 'Unable to save addon.');
        });
      });
    }

    if (categoryTableSearch) {
      categoryTableSearch.addEventListener('input', function () {
        categoryPage = 1;
        renderCategoryTable();
      });
    }

    if (foodTableSearch) {
      foodTableSearch.addEventListener('input', function () {
        foodPage = 1;
        renderFoodTable();
      });
    }

    var categoryTablePrev = document.getElementById('categoryTablePrev');
    var categoryTableNext = document.getElementById('categoryTableNext');
    var foodTablePrev = document.getElementById('foodTablePrev');
    var foodTableNext = document.getElementById('foodTableNext');

    if (categoryTablePrev) categoryTablePrev.addEventListener('click', function () { categoryPage -= 1; renderCategoryTable(); });
    if (categoryTableNext) categoryTableNext.addEventListener('click', function () { categoryPage += 1; renderCategoryTable(); });
    if (foodTablePrev) foodTablePrev.addEventListener('click', function () { foodPage -= 1; renderFoodTable(); });
    if (foodTableNext) foodTableNext.addEventListener('click', function () { foodPage += 1; renderFoodTable(); });

    if (foodForm) {
      if (foodImageFile) {
        foodImageFile.addEventListener('change', function () {
          var file = foodImageFile.files && foodImageFile.files[0];
          setImagePreview(foodImagePreview, file ? URL.createObjectURL(file) : document.getElementById('foodImageUrl').value, 'bi bi-image');
        });
      }

      foodForm.addEventListener('submit', function (event) {
        event.preventDefault();
        hideFormError('foodFormAlert');
        var id = document.getElementById('foodId').value;
        var payload = foodPayload();
        var file = foodImageFile && foodImageFile.files ? foodImageFile.files[0] : null;

        uploadImage(file, 'foods').then(function (path) {
          if (path) payload.image_url = path;

          return request('/menu-foods' + (id ? '/' + id : ''), {
            method: id ? 'PUT' : 'POST',
            body: JSON.stringify(payload)
          });
        }).then(function () {
          fillFood(null);
          if (foodModal) foodModal.hide();
          swalToast(id ? 'Food updated' : 'Food added');
          loadMenu();
        }).catch(function (error) {
          showFormError('foodFormAlert', error, 'Unable to save food.');
        });
      });
    }

    loadMenu();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      initMenuPage();
    });
  } else {
    initMenuPage();
  }
})();

