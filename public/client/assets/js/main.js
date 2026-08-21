
import { initMenu } from "./menu.js";
import { initOrder } from "./order.js";
import { initOrderStatus } from "./order-status.js";
import { initUI } from "./ui.js";
import { initLanguageGate } from "./language.js";

function init() {
  initLanguageGate();
  initOrder();
  initOrderStatus();
  initMenu();
  initUI();
}

document.addEventListener("DOMContentLoaded", init);
