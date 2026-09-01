(function () {
  var AdminUI = window.AdminUI || {};

  function errorMessage(error, fallback) {
    if (error && error.errors) return Object.values(error.errors).join(' ');
    return (error && error.message) || fallback || 'Something went wrong.';
  }

  function swalConfirm(message, title) {
    if (typeof Swal === 'undefined') {
      return Promise.resolve(window.confirm(message));
    }

    return Swal.fire({
      title: title || 'Are you sure?',
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

  function swalToast(message, icon) {
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

  function showAlert(target, message, fallbackMode) {
    var box = typeof target === 'string' ? document.getElementById(target) : target;
    if (!box) {
      if (fallbackMode !== false) window.alert(message);
      return;
    }

    box.textContent = message;
    box.classList.remove('d-none');
    box.classList.add('show');
  }

  function hideAlert(target) {
    var box = typeof target === 'string' ? document.getElementById(target) : target;
    if (!box) return;

    box.classList.add('d-none');
    box.classList.remove('show');
  }

  function showError(target, error, fallback, fallbackMode) {
    showAlert(target, errorMessage(error, fallback), fallbackMode);
  }

  function hideOpenModal(modalEl) {
    if (!modalEl || !modalEl.classList.contains('show') || typeof bootstrap === 'undefined') return;

    var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    modal.hide();
  }

  function filteredList(rows, searchInput, fields) {
    var query = window.text(searchInput ? searchInput.value : '').trim().toLowerCase();
    if (!query) return rows.slice();

    return rows.filter(function (row) {
      return fields.some(function (field) {
        return window.text(row[field]).toLowerCase().indexOf(query) !== -1;
      });
    });
  }

  function renderPaginatedTable(config) {
    var rows = config.rows || [];
    var page = config.page || 1;
    var pageSize = config.pageSize || 10;
    var totalPages = Math.max(1, Math.ceil(rows.length / pageSize));
    var currentPage = Math.min(Math.max(1, page), totalPages);
    var start = (currentPage - 1) * pageSize;
    var visible = rows.slice(start, start + pageSize);

    config.body.innerHTML = visible.map(config.rowRenderer).join('') ||
      '<tr><td colspan="' + config.emptyColspan + '" class="text-center text-secondary py-4">' +
        window.escapeHtml(config.emptyText || 'No records found.') +
      '</td></tr>';

    if (config.showing) {
      config.showing.textContent = rows.length
        ? 'Showing ' + (start + 1) + '-' + Math.min(start + pageSize, rows.length) + ' of ' + rows.length
        : 'No records';
    }
    if (config.pageLabel) config.pageLabel.textContent = 'Page ' + currentPage + ' of ' + totalPages;
    if (config.prevBtn) config.prevBtn.disabled = currentPage <= 1;
    if (config.nextBtn) config.nextBtn.disabled = currentPage >= totalPages;

    return currentPage;
  }

  function selectedValues(container, selector) {
    return Array.from(container ? container.querySelectorAll(selector + ':checked') : []).map(function (input) {
      return input.value;
    }).filter(Boolean);
  }

  function updateDropdownLabel(container, button, selector, emptyLabel, selectedLabel) {
    if (!container || !button) return;

    var selected = Array.from(container.querySelectorAll(selector + ':checked'));
    if (!selected.length) {
      button.textContent = emptyLabel;
    } else if (selected.length === 1) {
      button.textContent = selected[0].dataset.label || selectedLabel.replace('%d', '1');
    } else {
      button.textContent = selectedLabel.replace('%d', selected.length);
    }
  }

  AdminUI.errorMessage = errorMessage;
  AdminUI.swalConfirm = swalConfirm;
  AdminUI.swalToast = swalToast;
  AdminUI.showAlert = showAlert;
  AdminUI.hideAlert = hideAlert;
  AdminUI.showError = showError;
  AdminUI.hideOpenModal = hideOpenModal;
  AdminUI.filteredList = filteredList;
  AdminUI.renderPaginatedTable = renderPaginatedTable;
  AdminUI.selectedValues = selectedValues;
  AdminUI.updateDropdownLabel = updateDropdownLabel;

  window.AdminUI = AdminUI;
  window.swalConfirm = swalConfirm;
  window.swalToast = swalToast;
  window.showAlert = showAlert;
  window.hideAlert = hideAlert;
  window.showError = showError;
  window.showFormError = showError;
  window.hideFormError = hideAlert;
  window.hideOpenModal = hideOpenModal;
  window.filteredList = filteredList;
  window.renderPaginatedTable = renderPaginatedTable;
  window.selectedValues = selectedValues;
  window.updateDropdownLabel = updateDropdownLabel;
})();
