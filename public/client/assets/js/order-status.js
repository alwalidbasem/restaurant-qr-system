import { STORAGE_KEY, LAST_ORDER_STORAGE_KEY, findItem, formatPrice } from "./data.js";
import { escapeHtml } from "./ui.js";
import { i18n } from "./i18n.js";

export function initOrderStatus() {
  const page = document.getElementById("orderStatusPage");
  if (!page) return;

  const order = getVisibleOrder();
  renderOrderStatus(order);
}

function getVisibleOrder() {
  const submitted = readJson(LAST_ORDER_STORAGE_KEY);
  if (submitted && Array.isArray(submitted.items)) {
    return submitted;
  }

  const cartItems = readJson(STORAGE_KEY) || [];
  return {
    id: i18n("js_status_draft"),
    statusKey: cartItems.length ? "js_status_not_placed" : "js_status_no_order",
    placedAt: null,
    items: Array.isArray(cartItems) ? cartItems : [],
    total: getItemsTotal(Array.isArray(cartItems) ? cartItems : [])
  };
}

function readJson(key) {
  try {
    const raw = localStorage.getItem(key);
    return raw ? JSON.parse(raw) : null;
  } catch (err) {
    console.warn(`Could not read ${key}:`, err);
    return null;
  }
}

function renderOrderStatus(order) {
  const statusEl = document.getElementById("orderStatusValue");
  const totalEl = document.getElementById("orderStatusTotal");
  const idEl = document.getElementById("orderStatusId");
  const timeEl = document.getElementById("orderStatusTime");
  const listEl = document.getElementById("orderStatusItems");
  const emptyEl = document.getElementById("orderStatusEmpty");

  if (statusEl) statusEl.textContent = getOrderStatusText(order);
  if (totalEl) totalEl.textContent = formatPrice(order.total || getItemsTotal(order.items));
  if (idEl) idEl.textContent = order.id || i18n("js_status_draft");
  if (timeEl) timeEl.textContent = order.placedAt ? formatPlacedAt(order.placedAt) : i18n("js_status_empty_value");

  if (!listEl || !emptyEl) return;

  if (!order.items.length) {
    listEl.innerHTML = "";
    emptyEl.hidden = false;
    return;
  }

  emptyEl.hidden = true;
  listEl.innerHTML = order.items.map(buildItemHtml).join("");
}

function buildItemHtml(line) {
  const item = findItem(line.id);
  if (!item) return "";

  const qty = Number(line.qty || 1);
  const addons = line.addons || [];
  const lineTotal = (item.price + getLineAddonTotal(line)) * qty;
  const addonsHtml = addons.length
    ? `<div class="order-status-item__addons">${addons.map(buildAddonHtml).join("")}</div>`
    : "";
  const itemMeta = i18n("js_status_item_meta", {
    qty,
    price: formatPrice(item.price),
    each: i18n("js_each")
  });

  return `
    <article class="order-status-item">
      <img src="${item.image}" alt="${escapeHtml(item.name)}">
      <div class="order-status-item__body">
        <div>
          <p class="order-status-item__name">${escapeHtml(item.name)}</p>
          <p class="order-status-item__meta">${escapeHtml(itemMeta)}</p>
        </div>
        ${addonsHtml}
      </div>
      <strong class="order-status-item__price">${formatPrice(lineTotal)}</strong>
    </article>
  `;
}

function buildAddonHtml(addon) {
  const value = addon.type === "input"
    ? i18n("js_status_addon_value", { value: escapeHtml(addon.value) })
    : "";
  const price = addon.price
    ? i18n("js_status_addon_price", { price: formatPrice(addon.price) })
    : "";
  return `<span>${escapeHtml(addon.name)}${value}${price}</span>`;
}

function getOrderStatusText(order) {
  if (order.statusKey) return i18n(order.statusKey);
  return order.status || i18n("js_status_no_order");
}

function getItemsTotal(items) {
  return items.reduce((sum, line) => {
    const item = findItem(line.id);
    if (!item) return sum;
    return sum + (item.price + getLineAddonTotal(line)) * Number(line.qty || 1);
  }, 0);
}

function getLineAddonTotal(line) {
  return (line.addons || []).reduce((sum, addon) => sum + Number(addon.price || 0), 0);
}

function formatPlacedAt(value) {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return i18n("js_status_empty_value");
  return date.toLocaleString(document.documentElement.lang || undefined, {
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit"
  });
}
