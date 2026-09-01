( function () {
  function initInventory() {
    var body = document.getElementById('inventoryTableBody');
    if (!body) return;

    var page = window.INVENTORY_PAGE || {};
    var canCreate = !!page.can_create;
    var canUpdate = !!page.can_update;
    var canDelete = !!page.can_delete;
    var rows = [];
    var foods = [];
    var addons = [];
    var movements = [];
    var modalEl = document.getElementById('inventoryModal');
    var modal = modalEl ? new bootstrap.Modal(modalEl) : null;
    var movementModalEl = document.getElementById('inventoryMovementModal');
    var movementModal = movementModalEl ? new bootstrap.Modal(movementModalEl) : null;

    function unitLabel(unit) {
      return { pcs: 'pcs', kgs: 'kgs', liters: 'liters' }[unit] || unit || 'pcs';
    }

    function qty(value, unit) {
      return Number(value || 0).toFixed(unit === 'pcs' ? 0 : 3) + ' ' + unitLabel(unit);
    }

    function foodOptions(selected) {
      return '<option value="">Select food</option>' + foods.map(function (food) {
        return '<option value="' + escapeHtml(food.id) + '"' + (Number(selected) === Number(food.id) ? ' selected' : '') + '>' +
          escapeHtml(food.name_en || food.name_ar || ('Food #' + food.id)) +
        '</option>';
      }).join('');
    }

    function selectedAddonLabel(count) {
      if (count === 0) return 'Select addons';
      if (count === 1) return '1 addon selected';
      return count + ' addons selected';
    }

    function addonDropdown(foodId, selectedLinks) {
      var selectedByAddon = (selectedLinks || []).reduce(function (map, link) {
        if (link.addon_id) map[Number(link.addon_id)] = link;
        return map;
      }, {});
      var foodAddons = addons.filter(function (addon) { return Number(addon.food_id || 0) === Number(foodId || 0); });
      var selectedCount = Object.keys(selectedByAddon).length;

      if (!foodId) {
        return '<button class="form-select text-start inventory-addon-menu-toggle" type="button" disabled>Select food first</button>';
      }

      if (foodAddons.length === 0) {
        return '<button class="form-select text-start inventory-addon-menu-toggle" type="button" disabled>No addons</button>';
      }

      return '<div class="dropdown inventory-addon-dropdown">' +
        '<button class="form-select text-start inventory-addon-menu-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">' +
          escapeHtml(selectedAddonLabel(selectedCount)) +
        '</button>' +
        '<div class="dropdown-menu inventory-addon-menu">' +
          foodAddons.map(function (addon) {
            var selected = selectedByAddon[Number(addon.id)];
            var checked = selected ? ' checked' : '';
            return '<label class="dropdown-item inventory-addon-option">' +
            '<input class="form-check-input inventory-addon-check" type="checkbox" value="' + escapeHtml(addon.id) + '"' + checked + '>' +
              '<span>' + escapeHtml(addon.name_en || addon.name_ar || ('Addon #' + addon.id)) + '</span>' +
            '</label>';
          }).join('') +
        '</div>' +
      '</div>';
    }

    function addonEffectInputs(foodId, selectedLinks) {
      var selectedByAddon = (selectedLinks || []).reduce(function (map, link) {
        if (link.addon_id) map[Number(link.addon_id)] = link;
        return map;
      }, {});
      var foodAddons = addons.filter(function (addon) { return Number(addon.food_id || 0) === Number(foodId || 0); });

      return foodAddons.filter(function (addon) {
        return !!selectedByAddon[Number(addon.id)];
      }).map(function (addon) {
        var selected = selectedByAddon[Number(addon.id)];
        var name = addon.name_en || addon.name_ar || ('Addon #' + addon.id);
        return '<div class="inventory-addon-effect-input" data-addon-id="' + escapeHtml(addon.id) + '">' +
          '<label class="form-label small text-secondary mb-1">Effect of ' + escapeHtml(name) + '</label>' +
          '<div class="input-group">' +
            '<span class="input-group-text">-</span>' +
            '<input class="form-control inventory-addon-qty" type="number" min="0.001" step="0.001" value="' + escapeHtml(selected ? selected.quantity_per_item : '') + '">' +
          '</div>' +
        '</div>';
      }).join('');
    }

    function groupInventoryLinks(links) {
      return (links || []).reduce(function (groups, link) {
        var foodId = Number(link.food_id || 0);
        if (!foodId) return groups;
        if (!groups[foodId]) groups[foodId] = [];
        groups[foodId].push(link);
        return groups;
      }, {});
    }

    function linkRow(foodId, selectedLinks) {
      var links = selectedLinks || [];
      var baseLink = links.find(function (link) { return !link.addon_id; }) || {};
      var div = document.createElement('div');
      div.className = 'inventory-link-row';
      div.innerHTML =
        '<div class="inventory-link-main">' +
          '<div>' +
            '<label class="form-label small text-secondary mb-1">Food</label>' +
            '<select class="form-select inventory-link-food">' + foodOptions(foodId) + '</select>' +
          '</div>' +
          '<div>' +
            '<label class="form-label small text-secondary mb-1">Addons</label>' +
            '<div class="inventory-addon-select">' + addonDropdown(foodId, links) + '</div>' +
          '</div>' +
          '<div class="inventory-stock-effects">' +
            '<label class="form-label small text-secondary mb-1">Normal effect</label>' +
            '<div class="input-group">' +
              '<span class="input-group-text">-</span>' +
              '<input class="form-control inventory-link-qty" type="number" min="0" step="0.001" value="' + escapeHtml(baseLink.quantity_per_item || '') + '">' +
            '</div>' +
            '<div class="inventory-addon-effects">' + addonEffectInputs(foodId, links) + '</div>' +
          '</div>' +
          '<button class="btn btn-outline-danger inventory-link-remove" type="button"><i class="bi bi-x-lg"></i></button>' +
        '</div>';
      var food = div.querySelector('.inventory-link-food');
      var addonSelect = div.querySelector('.inventory-addon-select');
      var effects = div.querySelector('.inventory-addon-effects');

      function selectedAddonLinks() {
        return Array.from(div.querySelectorAll('.inventory-addon-check:checked')).map(function (check) {
          var existingInput = div.querySelector('.inventory-addon-effect-input[data-addon-id="' + check.value + '"] .inventory-addon-qty');
          return {
            food_id: Number(food.value || 0),
            addon_id: Number(check.value),
            quantity_per_item: Number(existingInput ? existingInput.value || 0 : 0)
          };
        });
      }

      food.addEventListener('change', function () {
        addonSelect.innerHTML = addonDropdown(food.value, []);
        effects.innerHTML = '';
      });
      addonSelect.addEventListener('change', function (event) {
        var check = event.target.closest('.inventory-addon-check');
        if (!check) return;
        var selectedLinksNow = selectedAddonLinks();
        addonSelect.innerHTML = addonDropdown(food.value, selectedLinksNow);
        effects.innerHTML = addonEffectInputs(food.value, selectedLinksNow);
      });
      div.querySelector('.inventory-link-remove').addEventListener('click', function () {
        div.remove();
      });
      return div;
    }

    function collectLinks() {
      return Array.from(document.querySelectorAll('.inventory-link-row')).reduce(function (links, row) {
        var foodId = Number(row.querySelector('.inventory-link-food').value || 0);
        var normalQty = Number(row.querySelector('.inventory-link-qty').value || 0);
        if (foodId > 0 && normalQty > 0) {
          links.push({ food_id: foodId, addon_id: null, quantity_per_item: normalQty });
        }

        row.querySelectorAll('.inventory-addon-effect-input').forEach(function (effect) {
          var addonId = Number(effect.dataset.addonId || 0);
          var addonQty = Number(effect.querySelector('.inventory-addon-qty').value || 0);
          if (foodId > 0 && addonId > 0 && addonQty > 0) {
            links.push({ food_id: foodId, addon_id: addonId, quantity_per_item: addonQty });
          }
        });

        return links;
      }, []);
    }

    function fillInventory(item) {
      document.getElementById('inventoryId').value = item ? item.id : '';
      document.getElementById('inventoryFormTitle').textContent = item ? 'Edit Inventory Item' : 'Add Inventory Item';
      document.getElementById('inventoryName').value = item ? item.name : '';
      document.getElementById('inventoryUnit').value = item ? (item.unit || 'pcs') : 'pcs';
      document.getElementById('inventoryQuantity').value = item ? Number(item.quantity || 0) : '';
      document.getElementById('inventoryRestaurantId').value = item ? item.restaurant_id : activeRestaurantId || '';
      document.getElementById('inventoryFormAlert').classList.add('d-none');
      var linksBox = document.getElementById('inventoryLinks');
      linksBox.innerHTML = '';
      var groupedLinks = groupInventoryLinks(item && Array.isArray(item.links) ? item.links : []);
      var foodIds = Object.keys(groupedLinks);

      if (foodIds.length) {
        foodIds.forEach(function (foodId) {
          linksBox.appendChild(linkRow(foodId, groupedLinks[foodId]));
        });
      } else {
        linksBox.appendChild(linkRow('', []));
      }
    }

    function inventoryPayload() {
      return {
        name: document.getElementById('inventoryName').value.trim(),
        unit: document.getElementById('inventoryUnit').value,
        quantity: Number(document.getElementById('inventoryQuantity').value || 0),
        restaurant_id: Number(document.getElementById('inventoryRestaurantId').value || activeRestaurantId || 0),
        links: collectLinks()
      };
    }

    function stockChartColor(value) {
      var amount = Number(value || 0);
      if (amount >= 0 && amount <= 25) return '#2f9e44';
      if (amount > 150) return '#2f9e44';
      return '#f59f00';
    }

    function movementChartColor(type) {
      return {
        purchase: '#2f9e44',
        waste: '#e03131',
        adjustment: '#868e96',
        consume: '#1c7ed6',
        return: '#51cf66'
      }[type] || '#adb5bd';
    }

    function movementChartLabel(type) {
      return {
        purchase: 'Add stock',
        waste: 'Waste',
        adjustment: 'Decrease stock',
        consume: 'Consumed by orders',
        return: 'Returned from canceled foods'
      }[type] || type;
    }

    function movementTooltipLines(type) {
      return movements.filter(function (movement) {
        return movement.movement_type === type;
      }).slice(0, 8).map(function (movement) {
        var amount = Math.abs(Number(movement.quantity_change || 0));
        return qty(amount, movement.unit) + ' ' + (movement.inventory_name || 'Stock item');
      });
    }

    function renderChartsLocal() {
      if (typeof Chart === 'undefined') return;

      var stockCanvas = document.getElementById('inventoryStockChart');
      if (stockCanvas) {
        var stockItems = rows.slice(0, 10);
        AdminCharts.chartCanvas('inventoryStockChart');
        stockCanvas.chart = new Chart(stockCanvas, {
          type: 'bar',
          data: {
            labels: stockItems.map(function (item) { return item.name; }),
            datasets: [{
              data: stockItems.map(function (item) { return Number(item.quantity || 0); }),
              backgroundColor: stockItems.map(function (item) { return stockChartColor(item.quantity); })
            }]
          },
          options: { plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } } } }
        });
      }

      var movementCanvas = document.getElementById('inventoryMovementChart');
      if (movementCanvas) {
        var counts = movements.reduce(function (map, movement) {
          map[movement.movement_type] = (map[movement.movement_type] || 0) + 1;
          return map;
        }, {});
        AdminCharts.chartCanvas('inventoryMovementChart');
        movementCanvas.chart = new Chart(movementCanvas, {
          type: 'doughnut',
          data: {
            labels: Object.keys(counts).map(movementChartLabel),
            datasets: [{
              data: Object.keys(counts).map(function (key) { return counts[key]; }),
              backgroundColor: Object.keys(counts).map(movementChartColor)
            }]
          },
          options: {
            plugins: {
              legend: { position: 'bottom' },
              tooltip: {
                callbacks: {
                  label: function (context) {
                    return context.label + ': ' + context.parsed;
                  },
                  afterLabel: function (context) {
                    var type = Object.keys(counts)[context.dataIndex];
                    return movementTooltipLines(type);
                  }
                }
              }
            }
          }
        });
      }
    }

    function renderInventory() {
      document.getElementById('inventoryStatItems').textContent = rows.length;
      document.getElementById('inventoryStatLow').textContent = rows.filter(function (item) { return Number(item.quantity || 0) <= 5; }).length;
      document.getElementById('inventoryStatWaste').textContent = movements.filter(function (item) { return item.movement_type === 'waste'; }).length;
      document.getElementById('inventoryShowing').textContent = 'Showing ' + rows.length + ' stock items';

      body.innerHTML = rows.map(function (item) {
        var links = Array.isArray(item.links) ? item.links : [];
        var linkedText = links.map(function (link) {
          return escapeHtml(link.food_name_en || link.food_name_ar || '-') +
            (link.addon_id ? ' / ' + escapeHtml(link.addon_name_en || link.addon_name_ar || 'Addon') : '') +
            ' = -' + qty(link.quantity_per_item, item.unit);
        }).join('<br>');
        var actions = '';
        if (canUpdate) actions += '<button class="btn btn-sm btn-outline-secondary inventory-movement" data-id="' + escapeHtml(item.id) + '"><i class="bi bi-plus-slash-minus"></i></button> ';
        if (canUpdate) actions += '<button class="btn btn-sm btn-outline-primary inventory-edit" data-id="' + escapeHtml(item.id) + '"><i class="bi bi-pencil"></i></button> ';
        if (canDelete) actions += '<button class="btn btn-sm btn-outline-danger inventory-delete" data-id="' + escapeHtml(item.id) + '"><i class="bi bi-trash"></i></button>';

        return '<tr>' +
          '<td><div class="fw-bold">' + escapeHtml(item.name) + '</div><small class="text-secondary">' + escapeHtml(unitLabel(item.unit)) + '</small></td>' +
          '<td class="fw-bold">' + qty(item.quantity, item.unit) + '</td>' +
          '<td class="small">' + (linkedText || '<span class="text-secondary">No food links</span>') + '</td>' +
          '<td class="text-end">' + (actions || '-') + '</td>' +
        '</tr>';
      }).join('') || '<tr><td colspan="4" class="text-center text-secondary py-4">No inventory items.</td></tr>';

      var list = document.getElementById('inventoryMovementsList');
      list.innerHTML = movements.slice(0, 12).map(function (movement) {
        var negative = Number(movement.quantity_change || 0) < 0;
        return '<li class="list-group-item d-flex justify-content-between gap-2">' +
          '<div><div class="fw-semibold">' + escapeHtml(movement.inventory_name || '-') + '</div><small class="text-secondary">' + escapeHtml(movement.reason || movement.movement_type) + '</small></div>' +
          '<span class="' + (negative ? 'text-danger' : 'text-success') + '">' + qty(movement.quantity_change, movement.unit) + '</span>' +
        '</li>';
      }).join('') || '<li class="list-group-item text-secondary">No movements yet.</li>';

      renderChartsLocal();
    }

    function loadInventory() {
      return Promise.all([
        request('/inventory'),
        request('/inventory/movements'),
        request('/menu-foods'),
        request('/food-addons')
      ]).then(function (results) {
        rows = results[0].data || [];
        movements = results[1].data || [];
        foods = results[2].data || [];
        addons = results[3].data || [];
        renderInventory();
      }).catch(function (error) {
        body.innerHTML = '<tr><td colspan="4" class="text-center text-danger py-4">' + escapeHtml(error.message || 'Unable to load inventory.') + '</td></tr>';
      });
    }

    var addBtn = document.getElementById('inventoryAddBtn');
    if (addBtn) addBtn.addEventListener('click', function () {
      fillInventory(null);
      modal.show();
    });

    document.getElementById('inventoryAddLinkBtn').addEventListener('click', function () {
      document.getElementById('inventoryLinks').appendChild(linkRow('', []));
    });

    body.addEventListener('click', function (event) {
      var edit = event.target.closest('.inventory-edit');
      var del = event.target.closest('.inventory-delete');
      var move = event.target.closest('.inventory-movement');

      if (edit) {
        var item = rows.find(function (row) { return String(row.id) === String(edit.dataset.id); });
        if (!item) return;
        fillInventory(item);
        modal.show();
      }

      if (move) {
        var movingItem = rows.find(function (row) { return String(row.id) === String(move.dataset.id); });
        document.getElementById('movementInventoryId').value = move.dataset.id;
        document.getElementById('inventoryMovementTitle').textContent = movingItem ? 'Movement - ' + movingItem.name : 'Inventory Movement';
        document.getElementById('inventoryMovementForm').reset();
        document.getElementById('inventoryMovementAlert').classList.add('d-none');
        movementModal.show();
      }

      if (del) {
        swalConfirm('Delete this inventory item?', 'Delete inventory').then(function (confirmed) {
          if (!confirmed) return;
          request('/inventory/' + del.dataset.id, { method: 'DELETE' }).then(loadInventory).catch(function (error) {
            window.alert(error.message || 'Unable to delete inventory.');
          });
        });
      }
    });

    document.getElementById('inventoryForm').addEventListener('submit', function (event) {
      event.preventDefault();
      var id = document.getElementById('inventoryId').value;
      request('/inventory' + (id ? '/' + id : ''), {
        method: id ? 'PUT' : 'POST',
        body: JSON.stringify(inventoryPayload())
      }).then(function () {
        modal.hide();
        swalToast('Inventory saved');
        loadInventory();
      }).catch(function (error) {
        var box = document.getElementById('inventoryFormAlert');
        box.textContent = error.errors ? Object.values(error.errors).join(' ') : (error.message || 'Unable to save inventory.');
        box.classList.remove('d-none');
      });
    });

    document.getElementById('inventoryMovementForm').addEventListener('submit', function (event) {
      event.preventDefault();
      var id = document.getElementById('movementInventoryId').value;
      request('/inventory/' + id + '/movement', {
        method: 'POST',
        body: JSON.stringify({
          movement_type: document.getElementById('movementType').value,
          quantity: Number(document.getElementById('movementQuantity').value || 0),
          reason: document.getElementById('movementReason').value.trim()
        })
      }).then(function () {
        movementModal.hide();
        swalToast('Movement saved');
        loadInventory();
      }).catch(function (error) {
        var box = document.getElementById('inventoryMovementAlert');
        box.textContent = error.message || 'Unable to save movement.';
        box.classList.remove('d-none');
      });
    });

    loadInventory();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      initInventory();
    });
  } else {
    initInventory();
  }
})();

