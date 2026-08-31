const STORAGE_PREFIX = getRestaurantStoragePrefix();

export const STORAGE_KEY = `${STORAGE_PREFIX}:VineOrder`;
export const LAST_ORDER_STORAGE_KEY = `${STORAGE_PREFIX}:VineLastOrder`;
export const SESSION_ORDER_KEY_STORAGE_KEY = `${STORAGE_PREFIX}:VineSessionOrderKey`;

export let FOOD_ITEMS = [];
export let MENU_ITEMS_LOADED_FROM_API = false;

export const state = {
  category: "all",
  order: [],
  activeFoodId: null,
  modalQty: 1,
  modalAddons: {},
  modalNotes: []
};

export async function loadMenuItems() {
  const apiUrl = typeof window !== "undefined" ? window.MENU_FOODS_API_URL : "";
  if (!apiUrl) {
    FOOD_ITEMS = [];
    MENU_ITEMS_LOADED_FROM_API = false;
    return FOOD_ITEMS;
  }

  try {
    const response = await fetch(apiUrl, {
      credentials: "omit",
      headers: {
        Accept: "application/json"
      }
    });

    if (!response.ok) {
      throw new Error(`Menu API returned ${response.status}`);
    }

    const payload = await response.json();
    const rows = Array.isArray(payload.data) ? payload.data : [];
    FOOD_ITEMS = rows.map(normalizeFoodFromApi);
    MENU_ITEMS_LOADED_FROM_API = true;
  } catch (err) {
    console.warn("Could not load menu foods from API:", err);
    FOOD_ITEMS = [];
    MENU_ITEMS_LOADED_FROM_API = false;
  }

  return FOOD_ITEMS;
}

export function findItem(id) {
  return FOOD_ITEMS.find((item) => String(item.id) === String(id));
}

export function formatPrice(amount) {
  return `${Number(amount || 0).toFixed(2)} JOD`;
}

function normalizeFoodFromApi(row) {
  const lang = getCurrentLanguage();
  const localizedName = pickLocalized(row, "name", lang);
  const localizedDescription = pickLocalized(row, "description", lang);
  const localizedCategory = pickLocalized(
    {
      name_ar: row.category_name_ar,
      name_en: row.category_name_en
    },
    "name",
    lang
  );

  return {
    id: String(row.id),
    name: localizedName,
    name_ar: row.name_ar || "",
    name_en: row.name_en || "",
    categoryId: String(row.category_id),
    category: localizedCategory,
    category_ar: row.category_name_ar || "",
    category_en: row.category_name_en || "",
    description: localizedDescription,
    description_ar: row.description_ar || "",
    description_en: row.description_en || "",
    price: Number(row.discounted_price ?? row.price ?? 0),
    originalPrice: Number(row.original_price ?? row.price ?? 0),
    discountAmount: Number(row.discount_amount || 0),
    hasDiscount: Boolean(row.has_discount) && Number(row.discount_amount || 0) > 0,
    profit: Number(row.profit || 0),
    image: row.image_url || "",
    badge: row.badge || "",
    noteEnabled: Number(row.note_enabled || 0) === 1,
    addons: normalizeAddons(row.addons || [], lang)
  };
}

function normalizeAddons(addons, lang) {
  if (!Array.isArray(addons)) return [];

  return addons.map((addon) => ({
    id: String(addon.id),
    name: pickLocalized(addon, "name", lang),
    name_ar: addon.name_ar || "",
    name_en: addon.name_en || "",
    type: addon.type || "checkbox",
    price: Number(addon.discounted_extra_price ?? addon.extra_price ?? addon.price ?? 0),
    originalPrice: Number(addon.original_extra_price ?? addon.extra_price ?? addon.price ?? 0),
    discountAmount: Number(addon.discount_amount || 0),
    hasDiscount: Boolean(addon.has_discount) && Number(addon.discount_amount || 0) > 0,
    profit: Number(addon.extra_profit || addon.profit || 0)
  }));
}

function pickLocalized(row, field, lang) {
  const primary = row[`${field}_${lang}`];
  const fallback = row[`${field}_${lang === "ar" ? "en" : "ar"}`];

  return primary || fallback || "";
}

function getCurrentLanguage() {
  const lang = typeof window !== "undefined" ? window.CURRENT_LANGUAGE_CODE : "en";

  return lang === "ar" ? "ar" : "en";
}

function getRestaurantStoragePrefix() {
  if (typeof window === "undefined") return "restaurant";

  const params = new URLSearchParams(window.location.search);
  const code = String(params.get("r_code") || params.get("restaurant_code") || "").trim();
  if (code) return code;

  const id = String(window.RESTAURANT_ID || "").trim();
  return id ? `restaurant-${id}` : "restaurant";
}
