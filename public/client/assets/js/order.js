import {
  state,
  STORAGE_KEY,
  LAST_ORDER_STORAGE_KEY,
  SESSION_ORDER_KEY_STORAGE_KEY,
  MENU_ITEMS_LOADED_FROM_API,
  findItem,
  formatPrice
} from "./data.js";
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
      const removeBtn = e.target.closest("[data-remove]");

      if (removeBtn) removeOrderLine(removeBtn.dataset.remove);
    });
  }

  if (viewOrderBtn) viewOrderBtn.addEventListener("click", openDrawer);
  if (drawerCloseBtn) drawerCloseBtn.addEventListener("click", closeDrawer);
  if (drawerBackdrop) drawerBackdrop.addEventListener("click", closeDrawer);

  if (placeOrderBtn) {
    placeOrderBtn.addEventListener("click", async () => {
      if (state.order.length === 0) return;
      placeOrderBtn.disabled = true;

      try {
        const submittedOrder = await submitOrder();
        showToast(i18n('js_order_placed'));
        saveSubmittedOrder(submittedOrder);
        state.order = [];
        saveOrderToStorage();
        renderOrderBadge();
        renderDrawer();
        notifyOrderChanged();
        setTimeout(() => {
          window.location.href = getOrderPageUrl(submittedOrder);
        }, 500);
      } catch (err) {
        console.warn("Could not place order:", err);
        showToast(i18n('js_order_failed'));
      } finally {
        placeOrderBtn.disabled = false;
      }
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

async function submitOrder() {
  const context = getOrderContext();
  if (!context) {
    throw new Error("Missing table or restaurant context.");
  }

  if (!MENU_ITEMS_LOADED_FROM_API) {
    throw new Error("Menu API did not load; refusing to submit order items.");
  }

  const placedAt = new Date().toISOString();
  const items = state.order.map((line) => ({ ...line, addons: [...(line.addons || [])] }));

  for (const line of items) {
    const item = findItem(line.id);
    if (!item || !Number.isInteger(Number(item.id)) || Number(item.id) <= 0) {
      throw new Error("Order contains an invalid food item.");
    }
  }

  if (items.length === 0) {
    throw new Error("No valid order lines to submit.");
  }

  const response = await fetch(context.ordersApiUrl, {
    method: "POST",
    credentials: "omit",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json"
    },
    body: JSON.stringify({
      restaurant_id: context.restaurantId,
      table_id: context.orderType === "takeaway" ? null : context.tableId,
      order_type: context.orderType,
      created_at: formatSqlDateTime(placedAt),
      items: items.map(buildOrderItemPayload)
    })
  });

  const result = await response.json().catch(() => null);
  if (!response.ok || !result?.success) {
    throw new Error(getApiErrorMessage(result, response.status));
  }

  const responses = Array.isArray(result.data?.orders) ? result.data.orders : [];
  if (result.data?.session_order_key) {
    saveSessionOrderKey(result.data.session_order_key);
  }
  const orderNumber = result.data?.session_order_key || "";
  const orderId = Number(result.data?.order_id || responses[0]?.order_id || 0);

  return {
    id: orderNumber,
    orderId,
    sessionOrderKey: orderNumber,
    statusKey: "js_status_preparing",
    placedAt,
    items,
    total: getOrderTotal(),
    apiOrders: responses
  };
}

function buildOrderItemPayload(line) {
  const item = findItem(line.id);
  const qty = normalizeQty(line.qty);
  const addons = getPayloadAddons(line);

  return {
    food_id: Number(item.id),
    qty,
    details: line.note || "",
    addons: addons.map((addon) => ({
      id: Number(addon.id),
      type: addon.type,
      value: addon.value ?? null
    }))
  };
}

function getOrderContext() {
  const restaurantId = Number(window.RESTAURANT_ID || 0);
  const tableId = Number(window.TABLE_ID || 0);
  const ordersApiUrl = window.ORDERS_API_URL || "";
  const orderType = tableId > 0 ? "table" : "takeaway";

  if (!restaurantId || !ordersApiUrl) return null;
  if (orderType === "table" && !tableId) return null;

  return {
    restaurantId,
    tableId,
    ordersApiUrl,
    orderType
  };
}

function saveSessionOrderKey(key) {
  try {
    localStorage.setItem(SESSION_ORDER_KEY_STORAGE_KEY, key);
  } catch (err) {
    console.warn("Could not save session order key:", err);
  }
}

function getApiErrorMessage(result, status) {
  if (result?.errors && typeof result.errors === "object") {
    return Object.entries(result.errors)
      .map(([field, message]) => `${field}: ${message}`)
      .join(", ");
  }

  return result?.error || result?.message || `Order API returned ${status}`;
}

function saveSubmittedOrder(submittedOrder) {
  const order = state.order.map((line) => ({ ...line, addons: [...(line.addons || [])] }));
  const summary = submittedOrder || {
    id: `ORDER-${Date.now().toString().slice(-6)}`,
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

function getOrderPageUrl(submittedOrder = null) {
  const orderNumber = submittedOrder?.sessionOrderKey || submittedOrder?.id || "";
  const orderId = Number(submittedOrder?.orderId || 0);

  if (window.ORDER_PAGE_URL) {
    const url = new URL(window.ORDER_PAGE_URL, window.location.origin);
    if (orderNumber) url.searchParams.set("order_number", orderNumber);
    if (orderId > 0) url.searchParams.set("order_id", String(orderId));
    return url.toString();
  }

  const path = window.location.pathname.replace(/\/+$/, "");
  const publicClientIndex = path.indexOf("/public/client");
  let basePath = path || "";

  if (publicClientIndex !== -1) {
    basePath = path.slice(0, publicClientIndex);
  } else if (basePath.endsWith("/landing")) {
    basePath = basePath.slice(0, -8);
  } else if (basePath.endsWith("/order")) {
    basePath = basePath.slice(0, -6);
  }

  const url = new URL(window.location.href);
  url.pathname = `${basePath}/order`.replace(/\/{2,}/g, "/");
  if (orderNumber) url.searchParams.set("order_number", orderNumber);
  if (orderId > 0) url.searchParams.set("order_id", String(orderId));
  return url.toString();
}

export function addToOrder(id, qty, addons = [], notes = []) {
  buildGroupedOrderLines(id, qty, addons, notes).forEach((line) => {
    const existing = state.order.find((orderLine) => buildLineKey(orderLine.id, orderLine.addons, orderLine.note) === buildLineKey(line.id, line.addons, line.note));

    if (existing && !line.note) {
      existing.qty = normalizeQty(existing.qty) + normalizeQty(line.qty);
      return;
    }

    state.order.push(line);
  });
  saveOrderToStorage();
  renderOrderBadge();
  renderDrawer();
  notifyOrderChanged();
  bumpBadge();
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
    return item ? sum + getLineTotal(item, line) : sum;
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
  const visibleLines = state.order.filter((line) => findItem(line.id));

  if (visibleLines.length === 0) {
    orderItemsEl.innerHTML = "";
    if (orderEmptyEl) orderEmptyEl.hidden = false;
    if (orderFooterEl) orderFooterEl.hidden = true;
    if (orderTotalEl) orderTotalEl.textContent = formatPrice(0);
    return;
  }

  if (orderEmptyEl) orderEmptyEl.hidden = true;
  if (orderFooterEl) orderFooterEl.hidden = false;

  orderItemsEl.innerHTML = visibleLines
    .map((line) => {
      const item = findItem(line.id);
      if (!item) return "";
      const lineKey = escapeHtml(line.key);
      const lineTotal = getLineTotal(item, line);
      const addonsHtml = buildOrderAddonsHtml(line.addons);
      const noteHtml = line.note ? `<div class="order-item__addons"><span>Note: ${escapeHtml(line.note)}</span></div>` : "";
      const qtyHtml = Number(line.qty) > 1
        ? `<div class="order-item__controls"><span class="order-item__qty">${i18n('js_status_qty')}: ${line.qty}</span></div>`
        : "";
      return `
        <div class="order-item" data-id="${lineKey}">
          <img src="${item.image}" alt="${escapeHtml(item.name)}">
          <div>
            <p class="order-item__name">${escapeHtml(item.name)}</p>
            <p class="order-item__price">${formatPrice(lineTotal)}</p>
            ${addonsHtml}
            ${noteHtml}
            ${qtyHtml}
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
    key: line.key || buildLineKey(line.id, addons, line.note),
    id: line.id,
    qty: normalizeQty(line.qty),
    addons,
    note: line.note || ""
  };
}

function buildGroupedOrderLines(id, qty, addons = [], notes = []) {
  const normalizedQty = normalizeQty(qty);
  const addonGroups = groupAddonsBySelection(addons, notes);
  const groupedQty = addonGroups.reduce((sum, group) => sum + group.qty, 0);

  if (groupedQty < normalizedQty) {
    const coveredMeals = new Set(addonGroups.flatMap((group) => group.mealIndexes || []));
    for (let i = 0; i < normalizedQty; i += 1) {
      const note = String(notes[i] || "").trim();
      if (note && !coveredMeals.has(i)) addonGroups.push({ qty: 1, addons: [], note, mealIndexes: [i] });
    }

    const nextGroupedQty = addonGroups.reduce((sum, group) => sum + group.qty, 0);
    if (nextGroupedQty < normalizedQty) {
      addonGroups.push({ qty: normalizedQty - nextGroupedQty, addons: [], note: "" });
    }
  }

  return addonGroups.map((group) => ({
    key: buildUniqueLineKey(id, group.addons, group.note),
    id,
    qty: group.qty,
    addons: group.addons,
    note: group.note || ""
  }));
}

function groupAddonsBySelection(addons = [], notes = []) {
  const mealGroups = new Map();
  const grouped = new Map();

  addons.forEach((addon) => {
    const visibleAddon = normalizeAddonForDisplay(addon);
    const mealIndex = Number.isInteger(Number(addon.mealIndex)) ? Number(addon.mealIndex) : 0;
    const mealAddons = mealGroups.get(mealIndex) || [];
    mealAddons.push(visibleAddon);
    mealGroups.set(mealIndex, mealAddons);
  });

  mealGroups.forEach((mealAddons) => {
    const mealIndex = Number(mealAddons[0]?.mealIndex || 0);
    const note = String(notes[mealIndex] || "").trim();
    const key = `${buildAddonSelectionKey(mealAddons)}::${note}`;

    if (!grouped.has(key)) {
      grouped.set(key, {
        qty: 0,
        addons: mealAddons,
        note,
        mealIndexes: []
      });
    }

    grouped.get(key).qty += 1;
    grouped.get(key).mealIndexes.push(mealIndex);
  });

  return Array.from(grouped.values());
}

function normalizeAddonForDisplay(addon) {
  return {
    id: addon.id,
    name: addon.name,
    type: addon.type,
    value: addon.value ?? null,
    mealIndex: Number.isInteger(Number(addon.mealIndex)) ? Number(addon.mealIndex) : 0,
    price: Number(addon.price || 0),
    profit: Number(addon.profit || 0)
  };
}

function getPayloadAddons(line) {
  const addons = Array.isArray(line.addons) ? line.addons : [];
  return addons;
}

function normalizeQty(qty) {
  const nextQty = Number(qty || 1);
  return Number.isFinite(nextQty) ? Math.max(1, nextQty) : 1;
}

function buildUniqueLineKey(id, addons = [], note = "") {
  const random = typeof crypto !== "undefined" && crypto.randomUUID
    ? crypto.randomUUID()
    : `${Date.now()}-${Math.random().toString(36).slice(2)}`;
  return `${buildLineKey(id, addons, note)}::${random}`;
}

function buildLineKey(id, addons = [], note = "") {
  return `${id}::${buildAddonSelectionKey(addons)}::${String(note || "")}`;
}

function buildAddonSelectionKey(addons = []) {
  return addons
    .map((addon) => `${addon.id}:${String(addon.value ?? "")}:${Number(addon.price || 0)}`)
    .sort()
    .join("|");
}

function getLineAddonTotal(line) {
  return (line.addons || []).reduce((sum, addon) => sum + Number(addon.price || 0), 0);
}

function getLineTotal(item, line) {
  const qty = normalizeQty(line.qty);
  return (Number(item.price || 0) + getLineAddonTotal(line)) * qty;
}

function getLineAddonProfit(line) {
  return (line.addons || []).reduce((sum, addon) => sum + Number(addon.profit || 0), 0);
}

function formatSqlDateTime(value) {
  const date = new Date(value);
  const pad = (part) => String(part).padStart(2, "0");

  return [
    date.getFullYear(),
    pad(date.getMonth() + 1),
    pad(date.getDate())
  ].join("-") + " " + [
    pad(date.getHours()),
    pad(date.getMinutes()),
    pad(date.getSeconds())
  ].join(":");
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
