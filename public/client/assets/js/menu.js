import { state, FOOD_ITEMS, findItem, formatPrice } from "./data.js";
import { addToOrder, ORDER_EVENT } from "./order.js";
import { showToast, escapeHtml, ESCAPE_EVENT } from "./ui.js";
import { i18n } from "./i18n.js";

let menuCategorySelect, foodGrid, emptyState;
let modalBackdrop, modalCloseBtn, modalImg, modalBadge, modalCategory,
    modalTitle, modalDesc, modalPrice, modalQtyValue, modalQtyMinus,
    modalQtyPlus, modalAddBtn, modalAddons, modalAddonsList, modalAddonsTitle,
    modalBillExtras, modalBillQty, modalNextBtn, modalBackBtn, foodModal, orderDrawer;

const STEP_ONE = "modal--step-one";
const STEP_TWO = "modal--step-two";
const TWO_STEP = "modal--two-step";
const NO_ADDONS = "modal--no-addons";

function isTwoStep() {
  return true;
}

export function initMenu() {
  menuCategorySelect = document.getElementById("menuCategory");
  foodGrid = document.getElementById("foodGrid");
  emptyState = document.getElementById("emptyState");

  modalBackdrop = document.getElementById("modalBackdrop");
  foodModal = document.getElementById("foodModal");
  modalCloseBtn = document.getElementById("modalCloseBtn");
  modalImg = document.getElementById("modalImg");
  modalBadge = document.getElementById("modalBadge");
  modalCategory = document.getElementById("modalCategory");
  modalTitle = document.getElementById("modalTitle");
  modalDesc = document.getElementById("modalDesc");
  modalPrice = document.getElementById("modalPrice");
  modalAddons = document.getElementById("modalAddons");
  modalAddonsList = document.getElementById("modalAddonsList");
  modalAddonsTitle = document.getElementById("modalAddonsTitle");
  modalBillExtras = document.getElementById("modalBillExtras");
  modalBillQty = document.getElementById("modalBillQty");
  modalQtyValue = document.getElementById("modalQtyValue");
  modalQtyMinus = document.getElementById("modalQtyMinus");
  modalQtyPlus = document.getElementById("modalQtyPlus");
  modalNextBtn = document.getElementById("modalNextBtn");
  modalBackBtn = document.getElementById("modalBackBtn");
  modalAddBtn = document.getElementById("modalAddBtn");
  orderDrawer = document.getElementById("orderDrawer");

  if (!menuCategorySelect || !foodGrid || !emptyState) return;

  renderFoodGrid();

  menuCategorySelect.addEventListener("change", (e) => {
    state.category = e.target.value;
    renderFoodGrid();
  });

  foodGrid.addEventListener("click", (e) => {
    const openTrigger = e.target.closest("[data-open]");
    const cardTrigger = e.target.closest(".food-card");

    if (openTrigger) {
      openModal(openTrigger.dataset.open);
    } else if (cardTrigger) {
      openModal(cardTrigger.dataset.id);
    }
  });

  if (modalAddonsList) {
    modalAddonsList.addEventListener("change", syncModalAddons);
    modalAddonsList.addEventListener("input", syncModalAddons);
  }

  modalCloseBtn.addEventListener("click", closeModal);
  modalBackdrop.addEventListener("click", (e) => {
    if (e.target === modalBackdrop) closeModal();
  });

  modalQtyMinus.addEventListener("click", () => {
    state.modalQty = Math.max(1, state.modalQty - 1);
    setModalQtyValue(state.modalQty);
    updateActiveModalBill();
  });

  modalQtyPlus.addEventListener("click", () => {
    state.modalQty = Math.min(20, state.modalQty + 1);
    setModalQtyValue(state.modalQty);
    updateActiveModalBill();
  });

  modalQtyValue.addEventListener("input", () => {
    const nextQty = parseInt(modalQtyValue.value, 10);
    if (Number.isNaN(nextQty)) return;
    state.modalQty = Math.min(20, Math.max(1, nextQty));
    updateActiveModalBill();
  });

  modalQtyValue.addEventListener("change", () => {
    setModalQtyValue(state.modalQty);
  });

  if (modalNextBtn) {
    modalNextBtn.addEventListener("click", handleNextStep);
  }

  if (modalBackBtn) {
    modalBackBtn.addEventListener("click", handlePrevStep);
  }

  modalAddBtn.addEventListener("click", () => {
    addActiveItemToOrder({ includeAddons: true, button: modalAddBtn });
  });

  document.addEventListener(ESCAPE_EVENT, () => {
    if (!modalBackdrop.hidden) closeModal();
  });

  document.addEventListener(ORDER_EVENT, renderFoodGrid);
}
function renderFoodGrid() {
  const filtered = getFilteredItems();

  foodGrid.innerHTML = "";

  if (filtered.length === 0) {
    emptyState.hidden = false;
    return;
  }
  emptyState.hidden = true;

  const cardsHtml = filtered.map(buildFoodCardHtml).join("");
  foodGrid.insertAdjacentHTML("beforeend", cardsHtml);
}

function buildFoodCardHtml(item) {
  return `
    <article class="food-card" data-id="${item.id}">
      <div class="food-card__media">
        <button class="food-card__open" data-open="${item.id}" aria-label="View details for ${escapeHtml(item.name)}"></button>
        <img src="${item.image}" alt="${escapeHtml(item.name)}, a ${escapeHtml(item.category.toLowerCase())} dish" loading="lazy">
        ${item.badge ? `<span class="food-card__badge">${escapeHtml(item.badge)}</span>` : ""}
      </div>
      <div class="food-card__body">
        <p class="food-card__category">${escapeHtml(item.category)}</p>
        <h3 class="food-card__name">${escapeHtml(item.name)}</h3>
        <p class="food-card__desc">${escapeHtml(item.description)}</p>
        <div class="food-card__footer">
          <span class="food-card__price">${formatPrice(item.price)}</span>
        </div>
      </div>
    </article>
  `;
}

function getFilteredItems() {
  return FOOD_ITEMS.filter((item) => {
    return state.category === "all" || item.category === state.category;
  });
}

function openModal(id) {
  const item = findItem(id);
  if (!item) return;

  state.activeFoodId = id;
  state.modalQty = 1;
  state.modalAddons = [];

  modalImg.src = item.image;
  modalImg.alt = `${item.name}, a ${item.category.toLowerCase()} dish`;
  modalCategory.textContent = item.category;
  modalTitle.textContent = item.name;
  modalDesc.textContent = item.description || "";
  modalDesc.hidden = !item.description;
  modalPrice.textContent = formatPrice(item.price);
  setModalQtyValue(1);
  renderModalAddons(item);
  updateModalPrice(item);

  if (item.badge) {
    modalBadge.textContent = item.badge;
    modalBadge.hidden = false;
  } else {
    modalBadge.hidden = true;
  }

  modalBackdrop.hidden = false;
  applyModalStep();
  document.body.style.overflow = "hidden";
  if (isTwoStep()) {
    modalNextBtn?.focus();
  } else {
    modalCloseBtn.focus();
  }
}

function closeModal() {
  modalBackdrop.hidden = true;
  foodModal?.classList.remove(TWO_STEP, STEP_ONE, STEP_TWO, NO_ADDONS);
  state.activeFoodId = null;
  state.modalAddons = [];
  if (!orderDrawer || !orderDrawer.classList.contains("is-open")) {
    document.body.style.overflow = "";
  }
}

function applyModalStep() {
  if (!foodModal) return;
  foodModal.classList.remove(STEP_ONE, STEP_TWO, NO_ADDONS);
  if (isTwoStep()) {
    foodModal.classList.add(TWO_STEP, STEP_ONE);
  } else {
    foodModal.classList.remove(TWO_STEP);
  }
}

function handleNextStep() {
  if (!isTwoStep()) {
    addActiveItemToOrder({ includeAddons: false, button: modalNextBtn });
    return;
  }
  const item = findItem(state.activeFoodId);
  if (!item) return;
  foodModal.classList.toggle(NO_ADDONS, !item.addons?.length);
  renderMealGroups(item);
  foodModal.classList.remove(STEP_ONE);
  foodModal.classList.add(STEP_TWO);
  modalCloseBtn?.focus({ preventScroll: true });
  resetModalScroll();
}

function handlePrevStep() {
  if (!isTwoStep() || !foodModal) return;
  foodModal.classList.remove(STEP_TWO);
  foodModal.classList.add(STEP_ONE);
  modalNextBtn?.focus();
}

function setModalQtyValue(value) {
  if (modalQtyValue) modalQtyValue.value = value;
}

function resetModalScroll() {
  const scrollToTop = () => {
    [
      modalBackdrop,
      foodModal,
      foodModal?.querySelector(".modal__body"),
      modalAddonsList
    ].forEach((element) => {
      if (!element) return;
      element.scrollTop = 0;
      element.scrollLeft = 0;
    });
  };

  scrollToTop();
  requestAnimationFrame(scrollToTop);
  setTimeout(scrollToTop, 50);
}

function addActiveItemToOrder({ includeAddons, button }) {
  if (!state.activeFoodId) return;

  const item = findItem(state.activeFoodId);
  if (!item) return;

  if (includeAddons) syncModalAddons();
  const selectedAddons = includeAddons ? getSelectedAddons(item) : [];

  addToOrder(state.activeFoodId, state.modalQty, selectedAddons);
  showToast(`${item.name} ${i18n('js_added_toast')}`);

  const originalText = button.textContent;
  button.textContent = i18n('js_added_btn');
  button.disabled = true;
  setTimeout(() => {
    button.textContent = originalText;
    button.disabled = false;
    closeModal();
  }, 550);
}

function renderModalAddons(item) {
  if (!modalAddons || !modalAddonsList) return;
  const addons = item.addons || [];

  if (modalAddonsTitle) {
    modalAddonsTitle.textContent = i18n('js_addons_title');
  }

  if (addons.length === 0) {
    modalAddons.hidden = true;
    modalAddonsList.innerHTML = "";
    return;
  }

  modalAddons.hidden = false;
  modalAddonsList.innerHTML = addons.map((addon) => buildAddonControlHtml(addon, 0)).join("");
}

function renderMealGroups(item) {
  if (!modalAddons || !modalAddonsList) return;
  const addons = item.addons || [];

  if (addons.length === 0) {
    modalAddons.hidden = true;
    modalAddonsList.innerHTML = "";
    return;
  }

  modalAddons.hidden = false;
  state.modalAddons = [];
  const qty = state.modalQty;
  const groups = [];

  for (let i = 0; i < qty; i += 1) {
    state.modalAddons.push({});
    const head = qty > 1
      ? `<p class="addon-meal-group__head"><i class="fa-solid fa-utensils" aria-hidden="true"></i> ${i18n('js_meal')} ${i + 1}</p>`
      : "";
    groups.push(`
      <div class="addon-meal-group">
        ${head}
        <div class="addon-meal-group__rows">
          ${addons.map((addon) => buildAddonControlHtml(addon, i)).join("")}
        </div>
      </div>
    `);
  }

  modalAddonsList.innerHTML = groups.join("");
}
function buildAddonControlHtml(addon, mealIndex = 0) {
  const priceLabel = addon.price ? `+${formatPrice(addon.price)}` : "+0.00 JOD";
  const safeId = escapeHtml(addon.id);
  const safeName = escapeHtml(addon.name);
  const suffix = mealIndex > 0 ? `-${mealIndex}` : "";
  const mealAttr = `data-meal="${mealIndex}"`;

  let control = "";
  if (addon.type === "boolean") {
    control = `
      <div class="addon-switch" role="radiogroup" aria-label="${safeName}">
        <input class="addon-switch__input" type="radio" name="addon-${safeId}${suffix}" id="addon-${safeId}-no${suffix}" ${mealAttr} data-addon="${safeId}" data-addon-type="boolean" value="no" checked>
        <label class="addon-switch__option addon-switch__option--no" for="addon-${safeId}-no${suffix}">${i18n('js_no')}</label>
        <input class="addon-switch__input" type="radio" name="addon-${safeId}${suffix}" id="addon-${safeId}-yes${suffix}" ${mealAttr} data-addon="${safeId}" data-addon-type="boolean" value="yes">
        <label class="addon-switch__option addon-switch__option--yes" for="addon-${safeId}-yes${suffix}">${i18n('js_yes')}</label>
      </div>
    `;
  } else if (addon.type === "input") {
    control = `<input class="addon-row__input" type="text" ${mealAttr} data-addon="${safeId}" data-addon-type="input" placeholder="${i18n('js_note_placeholder')}">`;
  } else {
    control = `
      <label class="addon-checkbox">
        <input type="checkbox" ${mealAttr} data-addon="${safeId}" data-addon-type="checkbox" aria-label="${safeName}">
        <span class="addon-checkbox__box" aria-hidden="true"><i class="fa-solid fa-check"></i></span>
      </label>
    `;
  }

  return `
    <div class="addon-row">
      <div class="addon-row__info">
        <span class="addon-row__name">${safeName}</span>
        <span class="addon-row__price">${priceLabel}</span>
      </div>
      <div class="addon-row__control">${control}</div>
    </div>
  `;
}

function syncModalAddons() {
  const item = findItem(state.activeFoodId);
  if (!item || !modalAddonsList) return;

  const mealCount = isTwoStep() ? state.modalQty : 1;
  state.modalAddons = [];

  for (let i = 0; i < mealCount; i += 1) {
    const mealSelection = {};
    item.addons?.forEach((addon) => {
      const controls = Array.from(
        modalAddonsList.querySelectorAll(`[data-meal="${i}"][data-addon="${addon.id}"]`)
      );
      const first = controls[0];
      if (addon.type === "boolean") {
        const selected = controls.find((control) => control.checked);
        mealSelection[addon.id] = selected?.value === "yes";
      } else if (addon.type === "input") {
        mealSelection[addon.id] = first ? first.value.trim() : "";
      } else {
        mealSelection[addon.id] = Boolean(first?.checked);
      }
    });
    state.modalAddons.push(mealSelection);
  }

  updateModalPrice(item);
}
function getSelectedAddons(item) {
  if (!item?.addons) return [];

  const selected = [];
  (state.modalAddons || []).forEach((mealSelection) => {
    item.addons.forEach((addon) => {
      const value = mealSelection?.[addon.id];
      const isSelected = addon.type === "input" ? Boolean(value) : Boolean(value);
      if (!isSelected) return;
      selected.push({
        id: addon.id,
        name: addon.name,
        type: addon.type,
        value,
        price: addon.type === "input" ? 0 : Number(addon.price || 0)
      });
    });
  });
  return selected;
}

function getModalAddonTotal(item) {
  return getSelectedAddons(item).reduce((sum, addon) => sum + addon.price, 0);
}

function updateModalPrice(item) {
  const addonTotal = getModalAddonTotal(item);
  if (modalBillExtras) modalBillExtras.textContent = formatPrice(addonTotal);
  if (modalBillQty) modalBillQty.textContent = state.modalQty;

  if (isTwoStep()) {
    // Extras are grouped per meal, so extras already scale with quantity.
    modalPrice.textContent = formatPrice(item.price * state.modalQty + addonTotal);
  } else {
    modalPrice.textContent = formatPrice((item.price + addonTotal) * state.modalQty);
  }
}

function updateActiveModalBill() {
  const item = findItem(state.activeFoodId);
  if (item) updateModalPrice(item);
}
