import {
  STORAGE_KEY,
  LAST_ORDER_STORAGE_KEY,
  SESSION_ORDER_KEY_STORAGE_KEY,
  findItem,
  formatPrice,
  loadMenuItems
} from "./data.js";
import { escapeHtml } from "./ui.js";
import { i18n } from "./i18n.js";

export async function initOrderStatus() {
  const page = document.getElementById("orderStatusPage");
  if (!page) return;

  await loadMenuItems();
  const order = await getVisibleOrder();
  renderOrderStatus(order);
  startTableStatusPolling();
}

async function getVisibleOrder() {
  const apiOrder = await getOrderFromApi();
  if (apiOrder) return apiOrder;

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

async function getOrderFromApi() {
  const params = new URLSearchParams(window.location.search);
  const orderNumber = (params.get("order_number") || params.get("session_order_key") || "").trim();
  const savedOrder = readJson(LAST_ORDER_STORAGE_KEY);
  const orderId = Number(params.get("order_id") || savedOrder?.orderId || 0);

  if (!orderNumber || !orderId || !window.ORDERS_API_URL) return null;

  try {
    const sessionOrderKey = getSessionOrderKey();
    if (!sessionOrderKey || sessionOrderKey !== orderNumber) return null;

    const url = new URL(window.ORDERS_API_URL, window.location.origin);
    url.searchParams.set("session_order_key", orderNumber);
    url.searchParams.set("order_id", String(orderId));
    if (window.RESTAURANT_CODE) {
      url.searchParams.set("restaurant_code", window.RESTAURANT_CODE);
      url.searchParams.delete("restaurant_id");
    }

    const response = await fetch(url.toString(), {
      credentials: "omit",
      headers: {
        Accept: "application/json",
        "SESSION-ORDER-KEY": sessionOrderKey
      }
    });

    if (!response.ok) {
      throw new Error(`Orders API returned ${response.status}`);
    }

    const payload = await response.json();
    const rows = Array.isArray(payload.data) ? payload.data : [];
    const selectedRows = rows;

    if (!selectedRows.length) return null;

    return normalizeApiOrder(selectedRows, orderNumber);
  } catch (err) {
    console.warn("Could not load submitted order from API:", err);
    return null;
  }
}

function getSessionOrderKey() {
  try {
    return localStorage.getItem(SESSION_ORDER_KEY_STORAGE_KEY) || "";
  } catch (err) {
    return "";
  }
}

function normalizeApiOrder(rows, orderNumber) {
  const grouped = new Map();

  rows.forEach((row) => {
    const addons = normalizeApiAddons(row);
    const key = [
      row.food_id,
      addons.map((addon) => addon.id).sort().join(","),
      row.details || ""
    ].join("::");
    const qty = Number(row.qty || 1);

    if (!grouped.has(key)) {
      grouped.set(key, {
        id: String(row.food_id),
        name: getLocalizedFoodName(row),
        image: row.image_url || "",
        price: Number(row.food_price || row.price || 0),
        qty,
        addons,
        note: row.details || ""
      });
      return;
    }

    const item = grouped.get(key);
    item.qty += qty;
  });

  const items = Array.from(grouped.values());

  return {
    id: orderNumber,
    status: rows[0]?.status || "",
    placedAt: rows[0]?.created_at || null,
    items,
    total: rows.reduce((sum, row) => sum + Number(row.price || 0), 0)
  };
}

function normalizeApiAddons(row) {
  if (Array.isArray(row.addons)) {
    return row.addons.map((addon) => ({
      id: String(addon.id),
      name: getLocalizedAddonName(addon),
      type: addon.type || "checkbox",
      value: addon.value ?? null,
      price: Number(addon.extra_price || addon.price || 0),
      profit: Number(addon.extra_profit || addon.profit || 0)
    }));
  }

  const addonIds = normalizeApiAddonIds(row.addon_id);
  if (addonIds.length === 0) return [];

  return addonIds.map((addonId, index) => ({
    id: String(addonId),
    name: index === 0 ? getLocalizedAddonName(row) : `#${addonId}`,
    type: "checkbox",
    value: null,
    price: index === 0 ? Number(row.addon_extra_price || 0) : 0,
    profit: index === 0 ? Number(row.addon_extra_profit || 0) : 0
  }));
}

function normalizeApiAddonIds(value) {
  if (!value) return [];

  const values = Array.isArray(value) ? value : [value];

  return values
    .map((addonId) => Number(addonId))
    .filter((addonId) => Number.isInteger(addonId) && addonId > 0);
}

function getLocalizedFoodName(row) {
  const lang = document.documentElement.lang === "ar" ? "ar" : "en";
  return row[`food_name_${lang}`] || row[`food_name_${lang === "ar" ? "en" : "ar"}`] || "";
}

function getLocalizedAddonName(row) {
  const lang = document.documentElement.lang === "ar" ? "ar" : "en";
  return row[`name_${lang}`]
    || row[`addon_name_${lang}`]
    || row[`name_${lang === "ar" ? "en" : "ar"}`]
    || row[`addon_name_${lang === "ar" ? "en" : "ar"}`]
    || "";
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
  const titleEl = document.getElementById("orderStatusTitle");
  const statusEl = document.getElementById("orderStatusValue");
  const totalEl = document.getElementById("orderStatusTotal");
  const idEl = document.getElementById("orderStatusId");
  const timeEl = document.getElementById("orderStatusTime");
  const listEl = document.getElementById("orderStatusItems");
  const emptyEl = document.getElementById("orderStatusEmpty");

  if (titleEl) {
    titleEl.innerHTML = `${escapeHtml(i18n("status_title_prefix"))} <span id="orderStatusValue">${escapeHtml(getOrderStatusText(order))}</span>`;
  }
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
  if (!item && !line.name) return "";

  const qty = Number(line.qty || 1);
  const addons = line.addons || [];
  const unitPrice = Number(item?.price || line.price || 0);
  const name = line.name || item.name;
  const image = item?.image || line.image || "";
  const lineTotal = (unitPrice + getLineAddonTotal(line)) * qty;
  const addonsHtml = addons.length
    ? `<div class="order-status-item__addons">${addons.map(buildAddonHtml).join("")}</div>`
    : "";
  const noteHtml = line.note
    ? `<div class="order-status-item__addons"><span>Note: ${escapeHtml(line.note)}</span></div>`
    : "";
  const itemMeta = i18n("js_status_item_meta", {
    qty,
    price: formatPrice(unitPrice),
    each: i18n("js_each")
  });

  return `
    <article class="order-status-item">
      ${image ? `<img src="${escapeHtml(image)}" alt="${escapeHtml(name)}">` : ""}
      <div class="order-status-item__body">
        <div>
          <p class="order-status-item__name">${escapeHtml(name)}</p>
          <p class="order-status-item__meta">${escapeHtml(itemMeta)}</p>
        </div>
        ${addonsHtml}
        ${noteHtml}
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

function startTableStatusPolling() {
  if (!window.TABLES_API_URL) return;

  let stopped = false;
  const check = async () => {
    if (stopped) return;

    try {
      const table = await fetchCurrentTable();
      if (!table) return;

      const status = normalizeTableStatus(table.table_status);
      if (status === "free" || hasMissingWaitingOrder(status, table)) {
        stopped = true;
        window.location.replace(getLandingPageUrl(table));
        return;
      }

      if (status === "order_done") {
        renderOrderDoneTitle();
      }
    } catch (err) {
      console.warn("Could not check table status:", err);
    }
  };

  check();
  const intervalId = window.setInterval(check, 5000);
  window.addEventListener("pagehide", () => {
    stopped = true;
    window.clearInterval(intervalId);
  }, { once: true });
}

async function fetchCurrentTable() {
  const response = await fetch(window.TABLES_API_URL, {
    credentials: "omit",
    headers: { Accept: "application/json" }
  });

  if (!response.ok) {
    throw new Error(`Tables API returned ${response.status}`);
  }

  const payload = await response.json();
  return payload?.success ? payload.data : null;
}

function normalizeTableStatus(status) {
  return String(status || "").trim().toLowerCase().replace(/[\s-]+/g, "_");
}

function hasMissingWaitingOrder(status, table) {
  return status === "waiting_order" && Number(table?.order_id || 0) <= 0;
}

function renderOrderDoneTitle() {
  const titleEl = document.getElementById("orderStatusTitle");
  const statusEl = document.getElementById("orderStatusValue");
  const doneTitle = i18n("status_done_title");

  if (titleEl) titleEl.textContent = doneTitle;
  if (statusEl) statusEl.textContent = doneTitle;
}

function getLandingPageUrl(table = null) {
  const url = new URL(window.location.href);
  const restaurantCode = String(window.RESTAURANT_CODE || "").trim();
  const tableNumber = Number(table?.table_number || window.TABLE_NUMBER || 0);
  const path = url.pathname.replace(/\/+$/, "");
  const publicClientIndex = path.indexOf("/public/client");

  if (publicClientIndex !== -1) {
    url.pathname = path.slice(0, publicClientIndex) || "/";
  } else if (path.endsWith("/order")) {
    url.pathname = path.slice(0, -6) || "/";
  } else {
    url.pathname = path || "/";
  }

  url.search = "";
  if (restaurantCode) url.searchParams.set("restaurant_code", restaurantCode);
  if (tableNumber > 0) url.searchParams.set("t_n", String(tableNumber));
  if (document.documentElement.lang) url.searchParams.set("language", document.documentElement.lang);

  return url.toString();
}

function getItemsTotal(items) {
  return items.reduce((sum, line) => {
    const item = findItem(line.id);
    if (!item) return sum;
    return sum + (Number(item.price || 0) + getLineAddonTotal(line)) * Number(line.qty || 1);
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
