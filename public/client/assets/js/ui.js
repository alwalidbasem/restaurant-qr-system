export const ESCAPE_EVENT = "app:escape";
import { i18n } from "./i18n.js";

let toastEl = null;
let toastTimeout = null;

export function escapeHtml(str) {
  const div = document.createElement("div");
  div.textContent = str;
  return div.innerHTML;
}

export function showToast(message) {
  if (!toastEl) return;
  toastEl.textContent = message;
  toastEl.classList.add("is-visible");
  clearTimeout(toastTimeout);
  toastTimeout = setTimeout(() => {
    toastEl.classList.remove("is-visible");
  }, 3000);
}

export function initUI() {
  const siteLoader = document.getElementById("siteLoader");
  const navbar = document.getElementById("navbar");
  const navLinks = document.getElementById("navLinks");
  const hamburgerBtn = document.getElementById("hamburgerBtn");
  const heroImg = document.getElementById("heroImg");

  toastEl = document.getElementById("toast");

  function handleScroll() {
    if (!navbar) return;
    navbar.classList.toggle("is-scrolled", window.scrollY > 40);
  }
  window.addEventListener("scroll", handleScroll, { passive: true });

  function handleHeroParallax() {
    if (!heroImg) return;
    const scrollY = window.scrollY;
    const heroHeight = window.innerHeight;
    if (scrollY <= heroHeight) {
      const progress = scrollY / heroHeight;
      heroImg.style.transform = `scale(${1.08 + progress * 0.06}) translateY(${progress * 40}px)`;
    }
  }
  window.addEventListener("scroll", handleHeroParallax, { passive: true });

  if (hamburgerBtn && navLinks) {
    hamburgerBtn.addEventListener("click", () => {
      const isOpen = navLinks.classList.toggle("is-open");
      hamburgerBtn.setAttribute("aria-expanded", String(isOpen));
      hamburgerBtn.setAttribute("aria-label", isOpen ? i18n('nav_close_menu') : i18n('nav_open_menu'));
    });

    navLinks.querySelectorAll(".navbar__link").forEach((link) => {
      link.addEventListener("click", () => {
        navLinks.classList.remove("is-open");
        hamburgerBtn.setAttribute("aria-expanded", "false");
        hamburgerBtn.setAttribute("aria-label", i18n('nav_open_menu'));
      });
    });
  }

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      document.dispatchEvent(new CustomEvent(ESCAPE_EVENT));
    }
  });

  handleScroll();
  handleHeroParallax();

  if (siteLoader) {
    const hideLoader = () => siteLoader.classList.add("is-hidden");
    if (document.readyState === "complete") {
      setTimeout(hideLoader, 450);
    } else {
      window.addEventListener("load", () => setTimeout(hideLoader, 450), { once: true });
      setTimeout(hideLoader, 2200);
    }
  }
}
