export function initLanguageGate() {
  const gate = document.getElementById("languageGate");
  if (!gate) return;

  // The language is now chosen server-side via ?language=.
  // Only show the first-visit gate when no explicit language is set.
  const hasLanguage = new URL(window.location.href).searchParams.has("language");
  if (hasLanguage) return;

  gate.hidden = false;
  document.body.style.overflow = "hidden";
  gate.querySelector(".language-gate__panel")?.focus({ preventScroll: true });

  gate.addEventListener("click", (event) => {
    const choice = event.target.closest("[data-language-choice]");
    if (!choice) return;

    const language = choice.dataset.languageChoice;
    applyLanguage(language);
  });
}

function applyLanguage(language) {
  const isArabic = language === "ar";
  document.documentElement.lang = isArabic ? "ar" : "en";
  document.documentElement.dir = isArabic ? "rtl" : "ltr";

  const url = new URL(window.location.href);
  url.searchParams.set("language", language);
  window.location.href = url.toString();
}
