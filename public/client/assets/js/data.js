export const STORAGE_KEY = "emberVineOrder";
export const LAST_ORDER_STORAGE_KEY = "emberVineLastOrder";
export const SESSION_ORDER_KEY_STORAGE_KEY = "emberVineSessionOrderKey";

const FALLBACK_FOOD_ITEMS = [
  {
    id: "burger-ember",
    name: "Ember Smashburger",
    category: "Burgers",
    description: "Two smashed beef patties, aged cheddar, charred onion and house sauce on a toasted brioche bun.",
    price: 12.5,
    image: "https://images.unsplash.com/photo-1568901346375-23c9450c58cd?auto=format&fit=crop&w=800&q=80",
    badge: "Popular",
    addons: [
      { id: "extra-cheese", name: "Extra cheddar", type: "checkbox", price: 0.75 },
      { id: "double-patty", name: "Double patty", type: "boolean", price: 2.0 },
      { id: "burger-note", name: "Special instructions", type: "input", price: 0 }
    ]
  },
  {
    id: "burger-smoke",
    name: "Smokehouse BBQ Burger",
    category: "Burgers",
    description: "Beef patty, smoked bacon, crispy onions and bourbon BBQ glaze on a pretzel bun.",
    price: 13.75,
    image: "https://images.unsplash.com/photo-1553979459-d2229ba7433b?auto=format&fit=crop&w=800&q=80",
    badge: "",
    addons: [
      { id: "extra-bacon", name: "Extra smoked bacon", type: "checkbox", price: 1.25 },
      { id: "bbq-on-side", name: "BBQ sauce on side", type: "boolean", price: 0 },
      { id: "burger-note", name: "Special instructions", type: "input", price: 0 }
    ]
  },
  {
    id: "burger-mushroom",
    name: "Wild Mushroom Swiss Burger",
    category: "Burgers",
    description: "Beef patty topped with sautéed wild mushrooms, melted Swiss and garlic aioli.",
    price: 13.25,
    image: "https://images.unsplash.com/photo-1571091718767-18b5b1457add?auto=format&fit=crop&w=800&q=80",
    badge: ""
  },
  {
    id: "pizza-margherita",
    name: "Margherita Pizza",
    category: "Pizza",
    description: "San Marzano tomato, fior di latte mozzarella, fresh basil and a drizzle of olive oil.",
    price: 14.0,
    image: "https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?auto=format&fit=crop&w=800&q=80",
    badge: "Chef's Choice",
    addons: [
      { id: "extra-mozzarella", name: "Extra mozzarella", type: "checkbox", price: 1.0 },
      { id: "gluten-free", name: "Gluten-free crust", type: "boolean", price: 1.5 },
      { id: "pizza-note", name: "Pizza note", type: "input", price: 0 }
    ]
  },
  {
    id: "pizza-diavola",
    name: "Pizza Diavola",
    category: "Pizza",
    description: "Spicy soppressata, tomato, mozzarella and a light chilli-honey finish, fired at 900°F.",
    price: 15.5,
    image: "https://images.unsplash.com/photo-1628840042765-356cda07504e?auto=format&fit=crop&w=800&q=80",
    badge: "Popular",
    addons: [
      { id: "extra-chilli", name: "Extra chilli honey", type: "checkbox", price: 0.5 },
      { id: "no-soppressata", name: "No soppressata", type: "boolean", price: 0 },
      { id: "pizza-note", name: "Pizza note", type: "input", price: 0 }
    ]
  },
  {
    id: "pizza-funghi",
    name: "Wild Mushroom Pizza",
    category: "Pizza",
    description: "Roasted mushroom medley, taleggio, thyme and a truffle-oil finish on a blistered crust.",
    price: 16.0,
    image: "https://images.unsplash.com/photo-1594007654729-407eedc4be65?auto=format&fit=crop&w=800&q=80",
    badge: ""
  },
  {
    id: "main-steak",
    name: "Charred Ribeye",
    category: "Main Dishes",
    description: "12oz ribeye finished over the open hearth, served with smoked butter and seasonal greens.",
    price: 28.0,
    image: "https://images.unsplash.com/photo-1546833999-b9f581a1996d?auto=format&fit=crop&w=800&q=80",
    badge: "Chef's Choice",
    addons: [
      { id: "smoked-butter", name: "Extra smoked butter", type: "checkbox", price: 0.75 },
      { id: "medium-rare", name: "Cook medium rare", type: "boolean", price: 0 },
      { id: "steak-note", name: "Steak note", type: "input", price: 0 }
    ]
  },
  {
    id: "main-salmon",
    name: "Cedar-Plank Salmon",
    category: "Main Dishes",
    description: "Slow-roasted salmon on cedar plank with lemon-dill butter and charred asparagus.",
    price: 22.5,
    image: "https://images.unsplash.com/photo-1519708227418-c8fd9a32b7a2?auto=format&fit=crop&w=800&q=80",
    badge: ""
  },
  {
    id: "main-pasta",
    name: "Slow-Braised Short Rib Pasta",
    category: "Main Dishes",
    description: "Hand-cut pappardelle tossed with red-wine braised short rib and shaved pecorino.",
    price: 19.75,
    image: "https://images.unsplash.com/photo-1551183053-bf91a1d81141?auto=format&fit=crop&w=800&q=80",
    badge: "Popular"
  },
  {
    id: "app-bruschetta",
    name: "Charred Tomato Bruschetta",
    category: "Appetizers",
    description: "Grilled sourdough topped with heirloom tomato, basil, garlic and aged balsamic.",
    price: 8.5,
    image: "https://images.unsplash.com/photo-1572695157366-5e585ab2b69f?auto=format&fit=crop&w=800&q=80",
    badge: ""
  },
  {
    id: "app-burrata",
    name: "Smoked Burrata",
    category: "Appetizers",
    description: "House-smoked burrata, charred grapes, pistachio and hearth-toasted bread.",
    price: 11.0,
    image: "https://images.unsplash.com/photo-1626200419199-391ae4be7a41?auto=format&fit=crop&w=800&q=80",
    badge: "Chef's Choice"
  },
  {
    id: "app-soup",
    name: "Roasted Squash Soup",
    category: "Appetizers",
    description: "Fire-roasted squash, brown butter, sage and a swirl of crème fraîche.",
    price: 7.75,
    image: "https://images.unsplash.com/photo-1547592166-23ac45744acd?auto=format&fit=crop&w=800&q=80",
    badge: ""
  },
  {
    id: "dessert-cake",
    name: "Dark Chocolate Ember Cake",
    category: "Desserts",
    description: "Molten dark chocolate cake with sea salt, served warm with vanilla bean ice cream.",
    price: 9.5,
    image: "https://images.unsplash.com/photo-1533134242443-d4fd215305ad?auto=format&fit=crop&w=800&q=80",
    badge: "Popular",
    addons: [
      { id: "extra-icecream", name: "Extra ice cream", type: "checkbox", price: 1.0 },
      { id: "birthday", name: "Birthday candle", type: "boolean", price: 0 },
      { id: "dessert-note", name: "Dessert note", type: "input", price: 0 }
    ]
  },
  {
    id: "dessert-icecream",
    name: "Charred Peach Sundae",
    category: "Desserts",
    description: "Hearth-charred peaches, brown-butter ice cream, honey and toasted almonds.",
    price: 8.0,
    image: "https://images.unsplash.com/photo-1497034825429-c343d7c6a68f?auto=format&fit=crop&w=800&q=80",
    badge: ""
  },
  {
    id: "drink-negroni",
    name: "Smoked Negroni",
    category: "Drinks",
    description: "Gin, sweet vermouth and Campari, finished with a hint of applewood smoke.",
    price: 13.0,
    image: "https://images.unsplash.com/photo-1551024506-0bccd828d307?auto=format&fit=crop&w=800&q=80",
    badge: "Chef's Choice"
  },
  {
    id: "drink-lemonade",
    name: "Rosemary Ember Lemonade",
    category: "Drinks",
    description: "House lemonade with charred rosemary syrup, served over hand-cut ice.",
    price: 6.5,
    image: "https://images.unsplash.com/photo-1621263764928-df1444c5e859?auto=format&fit=crop&w=800&q=80",
    badge: ""
  }
];

export let FOOD_ITEMS = FALLBACK_FOOD_ITEMS;
export let MENU_ITEMS_LOADED_FROM_API = false;

export const state = {
  category: "all",
  order: [],
  activeFoodId: null,
  modalQty: 1,
  modalAddons: {}
};

export async function loadMenuItems() {
  const apiUrl = typeof window !== "undefined" ? window.MENU_FOODS_API_URL : "";
  if (!apiUrl) return FOOD_ITEMS;

  try {
    const response = await fetch(apiUrl, {
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
    FOOD_ITEMS = FALLBACK_FOOD_ITEMS;
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
    price: Number(row.price || 0),
    profit: Number(row.profit || 0),
    image: row.image_url || "",
    badge: row.badge || "",
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
    price: Number(addon.extra_price || addon.price || 0),
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
