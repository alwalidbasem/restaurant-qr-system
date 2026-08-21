export function i18n(key, vars = {}) {
  const dict = (typeof window !== "undefined" && window.I18N) || {};
  let text = dict[key] ?? key;

  if (vars && typeof vars === "object") {
    for (const [name, value] of Object.entries(vars)) {
      text = String(text).replaceAll(`{${name}}`, String(value));
    }
  }

  return text;
}