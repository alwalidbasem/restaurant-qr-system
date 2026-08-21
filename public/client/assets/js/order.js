import { state, STORAGE_KEY, LAST_ORDER_STORAGE_KEY, findItem, formatPrice } from "./data.js";
import { showToast, escapeHtml, ESCAPE_EVENT } from "./ui.js";
import { i18n } from "./i18n.js";

export const ORDER_EVENT = "order:changed";

let orderBadge, orderDrawer, drawerBackdrop, drawerCloseBtn, orderItemsEl,
    orderEmptyEl, orderFooterEl, orderTotalEl, placeOrderBtn;

export function initOrder() {
  orderBadge = document.getElementById("orderBadge");
  orderDrawer = document.getElementById("orderDrawer");
  drawerBackdrop = document.getElementById("drawerBackdrop");
  drawerCloseBtn = document.getElementById("drawerCloseBtn");
  orderItemsEl = document.getElementById("orderItems");
  orderEmptyEl = document.getElementById("orderEmpty");
  orderFooterEl = document.getElementById("orderFooter");
  orderTotalEl = document.getElementById("orderTotal");
  placeOrderBtn = document.getElementById("placeOrderBtn");
  const viewOrderBtn = document.getElementById("viewOrderBtn");

  state.order = loadOrderFromStorage();
  renderOrderBadge();
  renderDrawer();

  if (orderItemsEl) {
    orderItemsEl.addEventListener("click", (e) => {
      const minusBtn = e.target.closest("[data-qty-minus]");
      const plusBtn = e.target.closest("[data-qty-plus]");
      const removeBtn = e.target.closest("[data-remove]");

      if (minusBtn) updateOrderQty(minusBtn.dataset.qtyMinus, -1);
      if (plusBtn) updateOrderQty(plusBtn.dataset.qtyPlus, 1);
      if (removeBtn) removeOrderLine(removeBtn.dataset.remove);
    });
  }

  if (viewOrderBtn) viewOrderBtn.addEventListener("click", openDrawer);
  if (drawerCloseBtn) drawerCloseBtn.addEventListener("click", closeDrawer);
  if (drawerBackdrop) drawerBackdrop.addEventListener("click", closeDrawer);

  if (placeOrderBtn) {
    placeOrderBtn.addEventListener("click", () => {
      if (state.order.length === 0) return;
      showToast(i18n('js_order_placed'));
      saveSubmittedOrder();
      state.order = [];
      saveOrderToStorage();
      renderOrderBadge();
      renderDrawer();
      notifyOrderChanged();
      setTimeout(() => {
        window.location.href = getOrderPageUrl();
      }, 500);
    });
  }

  document.addEventListener(ESCAPE_EVENT, () => {
    if (orderDrawer && orderDrawer.classList.contains("is-open")) closeDrawer();
  });
}

function loadOrderFromStorage() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    const order = raw ? JSON.parse(raw) : [];
    return order.map(normalizeOrderLine);
  } catch (err) {
    console.warn("Could not read saved order:", err);
    return [];
  }
}

function saveOrderToStorage() {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(state.order));
  } catch (err) {
    console.warn("Could not save order:", err);
  }
}

function saveSubmittedOrder() {
  const order = state.order.map((line) => ({ ...line, addons: [...(line.addons || [])] }));
  const summary = {
    id: `EV-${Date.now().toString().slice(-6)}`,
    statusKey: "js_status_preparing",
    placedAt: new Date().toISOString(),
    items: order,
    total: getOrderTotal()
  };

  try {
    localStorage.setItem(LAST_ORDER_STORAGE_KEY, JSON.stringify(summary));
  } catch (err) {
    console.warn("Could not save submitted order:", err);
  }
}

function getOrderPageUrl() {
  const path = window.location.pathname.replace(/\/+$/, "");
  const publicClientIndex = path.indexOf("/public/client");
  let basePath = path || "";

  if (publicClientIndex !== -1) {
    basePath = path.slice(0, publicClientIndex);
  } else if (basePath.endsWith("/order")) {
    basePath = basePath.slice(0, -6);
  }

  const url = new URL(window.location.href);
  url.pathname = `${basePath}/order`.replace(/\/{2,}/g, "/");
  return url.toString();
}

export function addToOrder(id, qty, addons = []) {
  const lineKey = buildLineKey(id, addons);
  const existing = state.order.find((line) => line.key === lineKey);
  if (existing) {
    existing.qty += qty;
  } else {
    state.order.push({ key: lineKey, id, qty, addons });
  }
  saveOrderToStorage();
  renderOrderBadge();
  renderDrawer();
  notifyOrderChanged();
  bumpBadge();
}

function updateOrderQty(key, delta) {
  const line = state.order.find((l) => l.key === key);
  if (!line) return;
  line.qty += delta;
  if (line.qty <= 0) {
    state.order = state.order.filter((l) => l.key !== key);
  }
  saveOrderToStorage();
  renderOrderBadge();
  renderDrawer();
  notifyOrderChanged();
}

function removeOrderLine(key) {
  state.order = state.order.filter((l) => l.key !== key);
  saveOrderToStorage();
  renderOrderBadge();
  renderDrawer();
  notifyOrderChanged();
}

function getOrderTotal() {
  return state.order.reduce((sum, line) => {
    const item = findItem(line.id);
    return item ? sum + (item.price + getLineAddonTotal(line)) * line.qty : sum;
  }, 0);
}

function getOrderCount() {
  return state.order.reduce((sum, line) => sum + line.qty, 0);
}

function renderOrderBadge() {
  if (!orderBadge) return;
  orderBadge.textContent = getOrderCount();
}

function bumpBadge() {
  if (!orderBadge) return;
  orderBadge.classList.remove("is-bump");
  void orderBadge.offsetWidth;
  orderBadge.classList.add("is-bump");
}

function renderDrawer() {
  if (!orderItemsEl) return;
  if (state.order.length === 0) {
    orderItemsEl.innerHTML = "";
    if (orderEmptyEl) orderEmptyEl.hidden = false;
    if (orderFooterEl) orderFooterEl.hidden = true;
    return;
  }

  if (orderEmptyEl) orderEmptyEl.hidden = true;
  if (orderFooterEl) orderFooterEl.hidden = false;

  orderItemsEl.innerHTML = state.order
    .map((line) => {
      const item = findItem(line.id);
      if (!item) return "";
      const lineKey = escapeHtml(line.key);
      const lineTotal = item.price + getLineAddonTotal(line);
      const addonsHtml = buildOrderAddonsHtml(line.addons);
      return `
        <div class="order-item" data-id="${lineKey}">
          <img src="${item.image}" alt="${escapeHtml(item.name)}">
          <div>
            <p class="order-item__name">${escapeHtml(item.name)}</p>
            <p class="order-item__price">${formatPrice(lineTotal)} ${i18n('js_each')}</p>
            ${addonsHtml}
            <div class="order-item__controls">
              <button class="order-item__qty-btn" data-qty-minus="${lineKey}" aria-label="${i18n('js_qty_decrease')} ${escapeHtml(item.name)}">-</button>
              <span class="order-item__qty">${line.qty}</span>
              <button class="order-item__qty-btn" data-qty-plus="${lineKey}" aria-label="${i18n('js_qty_increase')} ${escapeHtml(item.name)}">+</button>
            </div>
          </div>
          <button class="order-item__remove" data-remove="${lineKey}" aria-label="${i18n('js_remove')}: ${escapeHtml(item.name)}">
            <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
          </button>
        </div>
      `;
    })
    .join("");

  if (orderTotalEl) orderTotalEl.textContent = formatPrice(getOrderTotal());
}

function openDrawer() {
  if (!orderDrawer) return;
  orderDrawer.hidden = false;
  if (drawerBackdrop) drawerBackdrop.hidden = false;
  document.body.style.overflow = "hidden";
  requestAnimationFrame(() => orderDrawer.classList.add("is-open"));
  if (drawerCloseBtn) drawerCloseBtn.focus();
}

function closeDrawer() {
  if (!orderDrawer) return;
  orderDrawer.classList.remove("is-open");
  document.body.style.overflow = "";
  setTimeout(() => {
    orderDrawer.hidden = true;
    if (drawerBackdrop) drawerBackdrop.hidden = true;
  }, 400);
}

function notifyOrderChanged() {
  document.dispatchEvent(new CustomEvent(ORDER_EVENT));
}

function normalizeOrderLine(line) {
  const addons = Array.isArray(line.addons) ? line.addons : [];
  return {
    key: line.key || buildLineKey(line.id, addons),
    id: line.id,
    qty: Number(line.qty || 1),
    addons
  };
}

function buildLineKey(id, addons = []) {
  const addonSignature = addons
    .map((addon) => `${addon.id}:${String(addon.value)}:${Number(addon.price || 0)}`)
    .sort()
    .join("|");
  return `${id}::${addonSignature}`;
}

function getLineAddonTotal(line) {
  return (line.addons || []).reduce((sum, addon) => sum + Number(addon.price || 0), 0);
}

function buildOrderAddonsHtml(addons = []) {
  if (addons.length === 0) return "";

  const addonLines = addons
    .map((addon) => {
      const value = addon.type === "input" ? `: ${escapeHtml(addon.value)}` : "";
      const price = addon.price ? ` (+${formatPrice(addon.price)})` : "";
      return `<span>${escapeHtml(addon.name)}${value}${price}</span>`;
    })
    .join("");

  return `<div class="order-item__addons">${addonLines}</div>`;
}
