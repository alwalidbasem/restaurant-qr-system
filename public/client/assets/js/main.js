
import { initMenu } from "./menu.js";
import { initOrder } from "./order.js";
import { initOrderStatus } from "./order-status.js";
import { initUI } from "./ui.js";
import { initLanguageGate } from "./language.js";

async function init() {
  initLanguageGate();
  initUI();
  await initMenu();
  initOrder();
  await initOrderStatus();
}

document.addEventListener("DOMContentLoaded", init);
