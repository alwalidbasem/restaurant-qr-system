/* global Chart */

( function () {
  function initRestaurantSettings() {
    var form = document.getElementById('restaurantSettingsForm');
    var restaurantId = Number(window.RESTAURANT_SETTINGS_ID || activeRestaurantId || 0);
    if (!form || !restaurantId) return;

    var alertBox = document.getElementById('restaurantSettingsAlert');
    var current = null;
    var websiteHeroImageFile = document.getElementById('websiteHeroImageFile');
    var websiteHeroImagePreview = document.getElementById('websiteHeroImagePreview');
    var websiteLogoImageFile = document.getElementById('websiteLogoImageFile');
    var websiteLogoImagePreview = document.getElementById('websiteLogoImagePreview');
    var websitePreviewFrame = document.getElementById('websitePreviewFrame');
    var websitePreviewRefresh = document.getElementById('websitePreviewRefresh');
    var websiteRootCss = document.getElementById('websiteRootCss');
    var websiteColorSettings = [
      { group: 'Base', label: 'Background', id: 'websiteBackgroundColor', setting: 'background_color', variable: '--color-bg', defaultValue: '#1b140f', type: 'color' },
      { group: 'Base', label: 'Background alt', id: 'websiteBackgroundAltColor', setting: 'background_alt_color', variable: '--color-bg-alt', defaultValue: '#221a14', type: 'color' },
      { group: 'Base', label: 'Surface', id: 'websiteSurfaceColor', setting: 'surface_color', variable: '--color-surface', defaultValue: '#2a2019', type: 'color' },
      { group: 'Base', label: 'Surface raised', id: 'websiteSurfaceRaisedColor', setting: 'surface_raised_color', variable: '--color-surface-raised', defaultValue: '#322620', type: 'color' },
      { group: 'Base', label: 'Border', id: 'websiteBorderColor', setting: 'border_color', variable: '--color-border', defaultValue: '#3d2f26', type: 'color' },
      { group: 'Base', label: 'Text', id: 'websiteTextColor', setting: 'text_color', variable: '--color-text', defaultValue: '#f4ece0', type: 'color' },
      { group: 'Base', label: 'Text muted', id: 'websiteTextMutedColor', setting: 'text_muted_color', variable: '--color-text-muted', defaultValue: '#b9a696', type: 'color' },
      { group: 'Base', label: 'Text faint', id: 'websiteTextFaintColor', setting: 'text_faint_color', variable: '--color-text-faint', defaultValue: '#8a7768', type: 'color' },
      { group: 'Base', label: 'Primary', id: 'websitePrimaryColor', setting: 'primary_color', variable: '--color-accent', defaultValue: '#e0872f', type: 'color' },
      { group: 'Base', label: 'Accent dark', id: 'websiteAccentDarkColor', setting: 'accent_dark_color', variable: '--color-accent-dark', defaultValue: '#b85f1e', type: 'color' },
      { group: 'Base', label: 'Accent soft', id: 'websiteAccentSoftColor', setting: 'accent_soft_color', variable: '--color-accent-soft', defaultValue: 'rgba(224, 135, 47, 0.14)', type: 'text' },
      { group: 'Base', label: 'Ember', id: 'websiteEmberColor', setting: 'ember_color', variable: '--color-ember', defaultValue: '#c1441e', type: 'color' },
      { group: 'Base', label: 'Accent', id: 'websiteAccentColor', setting: 'accent_color', variable: '--color-gold', defaultValue: '#cba15c', type: 'color' },
      { group: 'Base', label: 'Success', id: 'websiteSuccessColor', setting: 'success_color', variable: '--color-success', defaultValue: '#6f9c6a', type: 'color' },
      { group: 'Base', label: 'Danger', id: 'websiteDangerColor', setting: 'danger_color', variable: '--color-danger', defaultValue: '#c1441e', type: 'color' },
      { group: 'Base', label: 'Danger strong', id: 'websiteDangerStrongColor', setting: 'danger_strong_color', variable: '--color-danger-strong', defaultValue: '#c95045', type: 'color' },
      { group: 'Base', label: 'Scrollbar accent', id: 'websiteScrollbarAccentColor', setting: 'scrollbar_accent_color', variable: '--color-scrollbar-accent', defaultValue: '#eda255', type: 'color' },
      { group: 'Base', label: 'Text on accent', id: 'websiteOnAccentColor', setting: 'on_accent_color', variable: '--color-on-accent', defaultValue: '#1b140f', type: 'color' },
      { group: 'Base', label: 'Text on success', id: 'websiteOnSuccessColor', setting: 'on_success_color', variable: '--color-on-success', defaultValue: '#10190f', type: 'color' },
      { group: 'Base', label: 'Transparent token', id: 'websiteTransparentColor', setting: 'transparent_color', variable: '--color-transparent', defaultValue: 'transparent', type: 'text' },
      { group: 'Overlays', label: 'Hero vertical', id: 'websiteOverlayHeroVertical', setting: 'overlay_hero_vertical', variable: '--overlay-hero-vertical', defaultValue: 'linear-gradient(180deg, rgba(15, 10, 7, 0.55) 0%, rgba(15, 10, 7, 0.65) 45%, rgba(15, 10, 7, 0.95) 100%)', type: 'text' },
      { group: 'Overlays', label: 'Hero horizontal', id: 'websiteOverlayHeroHorizontal', setting: 'overlay_hero_horizontal', variable: '--overlay-hero-horizontal', defaultValue: 'linear-gradient(90deg, rgba(15, 10, 7, 0.75) 0%, rgba(15, 10, 7, 0.25) 55%)', type: 'text' },
      { group: 'Overlays', label: 'Status cover', id: 'websiteOverlayStatusCover', setting: 'overlay_status_cover', variable: '--overlay-status-cover', defaultValue: 'linear-gradient(rgba(20, 15, 12, 0.7), rgba(20, 15, 12, 0.92))', type: 'text' },
      { group: 'Overlays', label: 'Modal backdrop', id: 'websiteOverlayModalBackdrop', setting: 'overlay_modal_backdrop', variable: '--overlay-modal-backdrop', defaultValue: 'rgba(10, 7, 5, 0.72)', type: 'text' },
      { group: 'Overlays', label: 'Drawer backdrop', id: 'websiteOverlayDrawerBackdrop', setting: 'overlay_drawer_backdrop', variable: '--overlay-drawer-backdrop', defaultValue: 'rgba(10, 7, 5, 0.6)', type: 'text' },
      { group: 'Overlays', label: 'Language backdrop', id: 'websiteOverlayLanguageBackdrop', setting: 'overlay_language_backdrop', variable: '--overlay-language-backdrop', defaultValue: 'rgba(10, 7, 5, 0.78)', type: 'text' },
      { group: 'Overlays', label: 'Navbar', id: 'websiteOverlayNavbarBg', setting: 'overlay_navbar_bg', variable: '--overlay-navbar-bg', defaultValue: 'rgba(27, 20, 15, 0.86)', type: 'text' },
      { group: 'Overlays', label: 'Navbar mobile', id: 'websiteOverlayNavbarMobileBg', setting: 'overlay_navbar_mobile_bg', variable: '--overlay-navbar-mobile-bg', defaultValue: 'rgba(27, 20, 15, 0.98)', type: 'text' },
      { group: 'Overlays', label: 'Food badge', id: 'websiteOverlayFoodBadgeBg', setting: 'overlay_food_badge_bg', variable: '--overlay-food-badge-bg', defaultValue: 'rgba(27, 20, 15, 0.85)', type: 'text' },
      { group: 'Overlays', label: 'Panel', id: 'websiteOverlayPanelBg', setting: 'overlay_panel_bg', variable: '--overlay-panel-bg', defaultValue: 'rgba(42, 32, 25, 0.84)', type: 'text' },
      { group: 'Overlays', label: 'Panel strong', id: 'websiteOverlayPanelStrongBg', setting: 'overlay_panel_strong_bg', variable: '--overlay-panel-strong-bg', defaultValue: 'rgba(42, 32, 25, 0.9)', type: 'text' },
      { group: 'Overlays', label: 'Footer', id: 'websiteOverlayFooterBg', setting: 'overlay_footer_bg', variable: '--overlay-footer-bg', defaultValue: 'rgba(34, 26, 20, 0.78)', type: 'text' },
      { group: 'Overlays', label: 'Footer strong', id: 'websiteOverlayFooterStrongBg', setting: 'overlay_footer_strong_bg', variable: '--overlay-footer-strong-bg', defaultValue: 'rgba(34, 26, 20, 0.82)', type: 'text' },
      { group: 'Overlays', label: 'Footer mobile', id: 'websiteOverlayFooterMobileBg', setting: 'overlay_footer_mobile_bg', variable: '--overlay-footer-mobile-bg', defaultValue: 'rgba(34, 26, 20, 0.88)', type: 'text' },
      { group: 'Overlays', label: 'Control', id: 'websiteOverlayControlBg', setting: 'overlay_control_bg', variable: '--overlay-control-bg', defaultValue: 'rgba(15, 10, 7, 0.38)', type: 'text' },
      { group: 'Overlays', label: 'Control mid', id: 'websiteOverlayControlBgMid', setting: 'overlay_control_bg_mid', variable: '--overlay-control-bg-mid', defaultValue: 'rgba(15, 10, 7, 0.5)', type: 'text' },
      { group: 'Overlays', label: 'Control focus', id: 'websiteOverlayControlBgFocus', setting: 'overlay_control_bg_focus', variable: '--overlay-control-bg-focus', defaultValue: 'rgba(15, 10, 7, 0.64)', type: 'text' },
      { group: 'Overlays', label: 'Control dark', id: 'websiteOverlayControlBgDark', setting: 'overlay_control_bg_dark', variable: '--overlay-control-bg-dark', defaultValue: 'rgba(15, 10, 7, 0.72)', type: 'text' },
      { group: 'Overlays', label: 'Control darker', id: 'websiteOverlayControlBgDarker', setting: 'overlay_control_bg_darker', variable: '--overlay-control-bg-darker', defaultValue: 'rgba(15, 10, 7, 0.94)', type: 'text' },
      { group: 'Overlays', label: 'Media', id: 'websiteOverlayMediaBg', setting: 'overlay_media_bg', variable: '--overlay-media-bg', defaultValue: 'rgba(15, 10, 7, 0.58)', type: 'text' },
      { group: 'Overlays', label: 'Quantity', id: 'websiteOverlayQtyBg', setting: 'overlay_qty_bg', variable: '--overlay-qty-bg', defaultValue: 'rgba(15, 10, 7, 0.22)', type: 'text' },
      { group: 'Overlays', label: 'Check inset', id: 'websiteOverlayCheckInset', setting: 'overlay_check_inset', variable: '--overlay-check-inset', defaultValue: 'rgba(27, 20, 15, 0.18)', type: 'text' },
      { group: 'Overlays', label: 'Bill', id: 'websiteOverlayBillBg', setting: 'overlay_bill_bg', variable: '--overlay-bill-bg', defaultValue: 'rgba(15, 10, 7, 0.14)', type: 'text' },
      { group: 'Overlays', label: 'Meal group', id: 'websiteOverlayMealGroupBg', setting: 'overlay_meal_group_bg', variable: '--overlay-meal-group-bg', defaultValue: 'rgba(15, 10, 7, 0.08)', type: 'text' },
      { group: 'Text Tints', label: 'Text 4.5%', id: 'websiteTintText045', setting: 'tint_text_045', variable: '--tint-text-045', defaultValue: 'rgba(244, 236, 224, 0.045)', type: 'text' },
      { group: 'Text Tints', label: 'Text 5%', id: 'websiteTintText05', setting: 'tint_text_05', variable: '--tint-text-05', defaultValue: 'rgba(244, 236, 224, 0.05)', type: 'text' },
      { group: 'Text Tints', label: 'Text 5.5%', id: 'websiteTintText055', setting: 'tint_text_055', variable: '--tint-text-055', defaultValue: 'rgba(244, 236, 224, 0.055)', type: 'text' },
      { group: 'Text Tints', label: 'Text 6%', id: 'websiteTintText06', setting: 'tint_text_06', variable: '--tint-text-06', defaultValue: 'rgba(244, 236, 224, 0.06)', type: 'text' },
      { group: 'Text Tints', label: 'Text 7%', id: 'websiteTintText07', setting: 'tint_text_07', variable: '--tint-text-07', defaultValue: 'rgba(244, 236, 224, 0.07)', type: 'text' },
      { group: 'Text Tints', label: 'Text 8%', id: 'websiteTintText08', setting: 'tint_text_08', variable: '--tint-text-08', defaultValue: 'rgba(244, 236, 224, 0.08)', type: 'text' },
      { group: 'Text Tints', label: 'Text 9%', id: 'websiteTintText09', setting: 'tint_text_09', variable: '--tint-text-09', defaultValue: 'rgba(244, 236, 224, 0.09)', type: 'text' },
      { group: 'Text Tints', label: 'Text 12%', id: 'websiteTintText12', setting: 'tint_text_12', variable: '--tint-text-12', defaultValue: 'rgba(244, 236, 224, 0.12)', type: 'text' },
      { group: 'Text Tints', label: 'Text 13%', id: 'websiteTintText13', setting: 'tint_text_13', variable: '--tint-text-13', defaultValue: 'rgba(244, 236, 224, 0.13)', type: 'text' },
      { group: 'Text Tints', label: 'Text 14%', id: 'websiteTintText14', setting: 'tint_text_14', variable: '--tint-text-14', defaultValue: 'rgba(244, 236, 224, 0.14)', type: 'text' },
      { group: 'Text Tints', label: 'Text 16%', id: 'websiteTintText16', setting: 'tint_text_16', variable: '--tint-text-16', defaultValue: 'rgba(244, 236, 224, 0.16)', type: 'text' },
      { group: 'Text Tints', label: 'Text 18%', id: 'websiteTintText18', setting: 'tint_text_18', variable: '--tint-text-18', defaultValue: 'rgba(244, 236, 224, 0.18)', type: 'text' },
      { group: 'Text Tints', label: 'Text 20%', id: 'websiteTintText20', setting: 'tint_text_20', variable: '--tint-text-20', defaultValue: 'rgba(244, 236, 224, 0.2)', type: 'text' },
      { group: 'Text Tints', label: 'Text 22%', id: 'websiteTintText22', setting: 'tint_text_22', variable: '--tint-text-22', defaultValue: 'rgba(244, 236, 224, 0.22)', type: 'text' },
      { group: 'Text Tints', label: 'Text 24%', id: 'websiteTintText24', setting: 'tint_text_24', variable: '--tint-text-24', defaultValue: 'rgba(244, 236, 224, 0.24)', type: 'text' },
      { group: 'Text Tints', label: 'Text 28%', id: 'websiteTintText28', setting: 'tint_text_28', variable: '--tint-text-28', defaultValue: 'rgba(244, 236, 224, 0.28)', type: 'text' },
      { group: 'Text Tints', label: 'Text 34%', id: 'websiteTintText34', setting: 'tint_text_34', variable: '--tint-text-34', defaultValue: 'rgba(244, 236, 224, 0.34)', type: 'text' },
      { group: 'Text Tints', label: 'Text 40%', id: 'websiteTintText40', setting: 'tint_text_40', variable: '--tint-text-40', defaultValue: 'rgba(244, 236, 224, 0.4)', type: 'text' },
      { group: 'Text Tints', label: 'Text 50%', id: 'websiteTintText50', setting: 'tint_text_50', variable: '--tint-text-50', defaultValue: 'rgba(244, 236, 224, 0.5)', type: 'text' },
      { group: 'Text Tints', label: 'Text 64%', id: 'websiteTintText64', setting: 'tint_text_64', variable: '--tint-text-64', defaultValue: 'rgba(244, 236, 224, 0.64)', type: 'text' },
      { group: 'Accent Tints', label: 'Accent 10%', id: 'websiteTintAccent10', setting: 'tint_accent_10', variable: '--tint-accent-10', defaultValue: 'rgba(224, 135, 47, 0.1)', type: 'text' },
      { group: 'Accent Tints', label: 'Accent 12%', id: 'websiteTintAccent12', setting: 'tint_accent_12', variable: '--tint-accent-12', defaultValue: 'rgba(224, 135, 47, 0.12)', type: 'text' },
      { group: 'Accent Tints', label: 'Accent 13%', id: 'websiteTintAccent13', setting: 'tint_accent_13', variable: '--tint-accent-13', defaultValue: 'rgba(224, 135, 47, 0.13)', type: 'text' },
      { group: 'Accent Tints', label: 'Accent 18%', id: 'websiteTintAccent18', setting: 'tint_accent_18', variable: '--tint-accent-18', defaultValue: 'rgba(224, 135, 47, 0.18)', type: 'text' },
      { group: 'Accent Tints', label: 'Accent 28%', id: 'websiteTintAccent28', setting: 'tint_accent_28', variable: '--tint-accent-28', defaultValue: 'rgba(224, 135, 47, 0.28)', type: 'text' },
      { group: 'Accent Tints', label: 'Accent 36%', id: 'websiteTintAccent36', setting: 'tint_accent_36', variable: '--tint-accent-36', defaultValue: 'rgba(224, 135, 47, 0.36)', type: 'text' },
      { group: 'Accent Tints', label: 'Accent 45%', id: 'websiteTintAccent45', setting: 'tint_accent_45', variable: '--tint-accent-45', defaultValue: 'rgba(224, 135, 47, 0.45)', type: 'text' },
      { group: 'Accent Tints', label: 'Accent 50%', id: 'websiteTintAccent50', setting: 'tint_accent_50', variable: '--tint-accent-50', defaultValue: 'rgba(224, 135, 47, 0.5)', type: 'text' },
      { group: 'Accent Tints', label: 'Accent 55%', id: 'websiteTintAccent55', setting: 'tint_accent_55', variable: '--tint-accent-55', defaultValue: 'rgba(224, 135, 47, 0.55)', type: 'text' },
      { group: 'Accent Tints', label: 'Accent 60%', id: 'websiteTintAccent60', setting: 'tint_accent_60', variable: '--tint-accent-60', defaultValue: 'rgba(224, 135, 47, 0.6)', type: 'text' },
      { group: 'Accent Tints', label: 'Accent 65%', id: 'websiteTintAccent65', setting: 'tint_accent_65', variable: '--tint-accent-65', defaultValue: 'rgba(224, 135, 47, 0.65)', type: 'text' },
      { group: 'Accent Tints', label: 'Accent 70%', id: 'websiteTintAccent70', setting: 'tint_accent_70', variable: '--tint-accent-70', defaultValue: 'rgba(224, 135, 47, 0.7)', type: 'text' },
      { group: 'Accent Tints', label: 'Gold 12%', id: 'websiteTintGold12', setting: 'tint_gold_12', variable: '--tint-gold-12', defaultValue: 'rgba(230, 184, 105, 0.12)', type: 'text' },
      { group: 'Accent Tints', label: 'Gold 28%', id: 'websiteTintGold28', setting: 'tint_gold_28', variable: '--tint-gold-28', defaultValue: 'rgba(230, 184, 105, 0.28)', type: 'text' },
      { group: 'Accent Tints', label: 'Danger 20%', id: 'websiteTintDanger20', setting: 'tint_danger_20', variable: '--tint-danger-20', defaultValue: 'rgba(184, 54, 44, 0.2)', type: 'text' },
      { group: 'Accent Tints', label: 'Success 20%', id: 'websiteTintSuccess20', setting: 'tint_success_20', variable: '--tint-success-20', defaultValue: 'rgba(111, 156, 106, 0.2)', type: 'text' },
      { group: 'Accent Tints', label: 'Success 48%', id: 'websiteTintSuccess48', setting: 'tint_success_48', variable: '--tint-success-48', defaultValue: 'rgba(111, 156, 106, 0.48)', type: 'text' },
      { group: 'Accent Tints', label: 'Success glow', id: 'websiteGlowSuccess', setting: 'glow_success', variable: '--glow-success', defaultValue: 'rgba(111, 156, 106, 0.9)', type: 'text' },
      { group: 'Shadows', label: 'Card hover', id: 'websiteShadowCardHover', setting: 'shadow_card_hover', variable: '--shadow-card-hover', defaultValue: 'rgba(0, 0, 0, 0.6)', type: 'text' },
      { group: 'Shadows', label: 'Panel', id: 'websiteShadowPanel', setting: 'shadow_panel', variable: '--shadow-panel', defaultValue: 'rgba(0, 0, 0, 0.82)', type: 'text' },
      { group: 'Shadows', label: 'Panel soft', id: 'websiteShadowPanelSoft', setting: 'shadow_panel_soft', variable: '--shadow-panel-soft', defaultValue: 'rgba(0, 0, 0, 0.25)', type: 'text' },
      { group: 'Shadows', label: 'Panel strong', id: 'websiteShadowPanelStrong', setting: 'shadow_panel_strong', variable: '--shadow-panel-strong', defaultValue: 'rgba(0, 0, 0, 0.38)', type: 'text' },
      { group: 'Shadows', label: 'Control', id: 'websiteShadowControl', setting: 'shadow_control', variable: '--shadow-control', defaultValue: 'rgba(0, 0, 0, 0.22)', type: 'text' },
      { group: 'Shadows', label: 'Toast', id: 'websiteShadowToast', setting: 'shadow_toast', variable: '--shadow-toast', defaultValue: 'rgba(0, 0, 0, 0.7)', type: 'text' }
    ];

    var websiteColorDefaults = websiteColorSettings.reduce(function (colors, setting) {
      colors[setting.variable] = setting.defaultValue;
      return colors;
    }, {});

    function setValue(id, value) {
      var el = document.getElementById(id);
      if (!el) return;
      if (el.dataset && el.dataset.htmlEditor === 'true') {
        el.innerHTML = text(value);
        return;
      }
      el.value = text(value);
    }

    function getValue(id) {
      var el = document.getElementById(id);
      if (!el) return '';
      if (el.dataset && el.dataset.htmlEditor === 'true') {
        return el.innerHTML.trim();
      }
      return el.value.trim();
    }

    function websiteColorMap(row) {
      var raw = row && row.website_colors;
      if (!raw) return {};
      if (typeof raw === 'object') return raw;

      try {
        var parsed = JSON.parse(raw);
        return parsed && typeof parsed === 'object' ? parsed : {};
      } catch (error) {
        return {};
      }
    }

    function parseRootCss(textValue) {
      var body = text(textValue)
        .replace(/\/\*[\s\S]*?\*\//g, '')
        .replace(/^\s*:root\s*\{/i, '')
        .replace(/\}\s*$/i, '');
      var colors = {};
      var match;
      var pattern = /(--[a-zA-Z0-9_-]+)\s*:\s*([^;]+);?/g;

      while ((match = pattern.exec(body)) !== null) {
        colors[match[1].trim()] = match[2].trim();
      }

      return colors;
    }

    function mergedWebsiteColors(row) {
      var stored = websiteColorMap(row || {});
      var colors = Object.assign({}, websiteColorDefaults);

      websiteColorSettings.forEach(function (setting) {
        colors[setting.variable] = stored[setting.variable] || row?.[setting.setting] || colors[setting.variable] || setting.defaultValue;
      });

      return colors;
    }

    function formatRootCss(colors) {
      var lines = [':root {'];
      Object.keys(colors).forEach(function (variable) {
        lines.push('  ' + variable + ': ' + colors[variable] + ';');
      });
      lines.push('}');

      return lines.join('\n');
    }

    function setWebsiteColors(row) {
      if (!websiteRootCss) return;
      websiteRootCss.value = formatRootCss(mergedWebsiteColors(row || {}));
    }

    function websiteColorsPayload() {
      var colors = Object.assign({}, websiteColorDefaults, parseRootCss(websiteRootCss ? websiteRootCss.value : ''));

      return colors;
    }

    function initHtmlEditors() {
      form.querySelectorAll('[data-html-editor="true"]').forEach(function (editor) {
        if (editor.dataset.editorReady === 'true') return;
        editor.dataset.editorReady = 'true';
        editor.dataset.placeholder = 'Write content...';
        editor.setAttribute('role', 'textbox');
        editor.setAttribute('aria-multiline', 'true');

        var toolbar = document.createElement('div');
        toolbar.className = 'settings-html-toolbar';
        toolbar.innerHTML =
          '<button class="btn btn-light btn-sm" type="button" data-command="bold" title="Bold"><i class="bi bi-type-bold"></i></button>' +
          '<button class="btn btn-light btn-sm" type="button" data-command="italic" title="Italic"><i class="bi bi-type-italic"></i></button>' +
          '<button class="btn btn-light btn-sm" type="button" data-command="underline" title="Underline"><i class="bi bi-type-underline"></i></button>' +
          '<button class="btn btn-light btn-sm" type="button" data-command="insertUnorderedList" title="List"><i class="bi bi-list-ul"></i></button>' +
          '<button class="btn btn-light btn-sm" type="button" data-command="createLink" title="Link"><i class="bi bi-link-45deg"></i></button>' +
          '<button class="btn btn-light btn-sm" type="button" data-command="removeFormat" title="Clear format"><i class="bi bi-eraser"></i></button>';

        editor.parentNode.insertBefore(toolbar, editor);

        toolbar.addEventListener('click', function (event) {
          var button = event.target.closest('[data-command]');
          if (!button) return;
          editor.focus();
          var command = button.dataset.command;
          if (command === 'createLink') {
            var url = window.prompt('Link URL');
            if (!url) return;
            if (!/^https?:\/\//i.test(url) && url.charAt(0) !== '/' && url.charAt(0) !== '#') {
              url = 'https://' + url;
            }
            document.execCommand(command, false, url);
            return;
          }

          document.execCommand(command, false, null);
        });

        editor.addEventListener('paste', function (event) {
          event.preventDefault();
          var plain = (event.clipboardData || window.clipboardData).getData('text/plain');
          document.execCommand('insertText', false, plain);
        });
      });
    }

    function applyPreviewColors() {
      if (!websitePreviewFrame || !websitePreviewFrame.contentDocument) return;

      var root = websitePreviewFrame.contentDocument.documentElement;
      var colors = websiteColorsPayload();
      Object.keys(colors).forEach(function (variable) {
        root.style.setProperty(variable, colors[variable]);
      });
    }

    function previewUrl() {
      var code = document.getElementById('settingsRestaurantCode').value.trim();
      var base = appBase || '';
      return base + '/?restaurant_code=' + encodeURIComponent(code) + '&preview=1';
    }

    function takeawayUrl() {
      var code = document.getElementById('settingsRestaurantCode').value.trim();
      var base = appBase || '';
      return base + '/?restaurant_code=' + encodeURIComponent(code) + '&takeaway=true';
    }

    function loadWebsitePreview() {
      if (!websitePreviewFrame) return;
      var code = document.getElementById('settingsRestaurantCode').value.trim();
      if (!code) return;
      websitePreviewFrame.src = previewUrl();
    }

    function initWebsitePreview() {
      if (websiteRootCss) {
        websiteRootCss.addEventListener('input', applyPreviewColors);
        websiteRootCss.addEventListener('change', applyPreviewColors);
      }

      if (websitePreviewFrame) {
        websitePreviewFrame.addEventListener('load', applyPreviewColors);
      }

      if (websitePreviewRefresh) {
        websitePreviewRefresh.addEventListener('click', loadWebsitePreview);
      }
    }

    function showSettingsError(message) {
      AdminUI.showAlert(alertBox, message, false);
    }

    function setSettings(row) {
      current = row;
      document.getElementById('settingsRestaurantId').value = row.id || '';
      document.getElementById('settingsRestaurantName').value = row.name || '';
      document.getElementById('settingsRestaurantCode').value = row.main_code || '';
      document.getElementById('settingsRestaurantLocation').value = row.location || '';
      document.getElementById('settingsRestaurantManager').value = row.manager_number || '';
      document.getElementById('settingsRestaurantActiveUntil').value = text(row.active_until || row.active_unitl).slice(0, 10);
      document.getElementById('settingsRestaurantDetails').value = row.txt_details || '';
      setValue('websiteBrandNameEn', row.brand_name_en);
      setValue('websiteBrandNameAr', row.brand_name_ar);
      setValue('websiteHeroTitleEn', row.hero_title_en);
      setValue('websiteHeroTitleAr', row.hero_title_ar);
      setValue('websiteHeroAccentEn', row.hero_accent_en);
      setValue('websiteHeroAccentAr', row.hero_accent_ar);
      setValue('websiteHeroEyebrowEn', row.hero_eyebrow_en);
      setValue('websiteHeroEyebrowAr', row.hero_eyebrow_ar);
      setValue('websiteHeroDescriptionEn', row.hero_description_en);
      setValue('websiteHeroDescriptionAr', row.hero_description_ar);
      setValue('websiteMenuTitleEn', row.menu_title_en);
      setValue('websiteMenuTitleAr', row.menu_title_ar);
      setValue('websiteMenuSubtitleEn', row.menu_subtitle_en);
      setValue('websiteMenuSubtitleAr', row.menu_subtitle_ar);
      setValue('websiteLogoImageUrl', row.logo_image_url);
      setValue('websiteHeroImageUrl', row.hero_image_url);
      var takeawayEnabled = document.getElementById('takeawayEnabled');
      if (takeawayEnabled) takeawayEnabled.checked = Number(row.takeaway_enabled || 0) === 1;
      var takeawayOrderLink = document.getElementById('takeawayOrderLink');
      if (takeawayOrderLink) takeawayOrderLink.href = takeawayUrl();
      setWebsiteColors(row);
      applyPreviewColors();
      if (websiteLogoImageFile) websiteLogoImageFile.value = '';
      if (websiteHeroImageFile) websiteHeroImageFile.value = '';
      setImagePreview(websiteLogoImagePreview, row.logo_image_url || '', 'bi bi-shop');
      setImagePreview(websiteHeroImagePreview, row.hero_image_url || '', 'bi bi-image');
      loadWebsitePreview();
    }

    function settingsPayload() {
      var websiteColors = websiteColorsPayload();

      return {
        name: document.getElementById('settingsRestaurantName').value.trim(),
        location: document.getElementById('settingsRestaurantLocation').value.trim(),
        active_until: document.getElementById('settingsRestaurantActiveUntil').value,
        manager_number: document.getElementById('settingsRestaurantManager').value.trim(),
        txt_details: document.getElementById('settingsRestaurantDetails').value.trim(),
        main_code: current ? current.main_code : document.getElementById('settingsRestaurantCode').value.trim(),
        brand_name_en: getValue('websiteBrandNameEn'),
        brand_name_ar: getValue('websiteBrandNameAr'),
        hero_title_en: getValue('websiteHeroTitleEn'),
        hero_title_ar: getValue('websiteHeroTitleAr'),
        hero_accent_en: getValue('websiteHeroAccentEn'),
        hero_accent_ar: getValue('websiteHeroAccentAr'),
        hero_eyebrow_en: getValue('websiteHeroEyebrowEn'),
        hero_eyebrow_ar: getValue('websiteHeroEyebrowAr'),
        hero_description_en: getValue('websiteHeroDescriptionEn'),
        hero_description_ar: getValue('websiteHeroDescriptionAr'),
        menu_title_en: getValue('websiteMenuTitleEn'),
        menu_title_ar: getValue('websiteMenuTitleAr'),
        menu_subtitle_en: getValue('websiteMenuSubtitleEn'),
        menu_subtitle_ar: getValue('websiteMenuSubtitleAr'),
        logo_image_url: getValue('websiteLogoImageUrl'),
        hero_image_url: getValue('websiteHeroImageUrl'),
        takeaway_enabled: document.getElementById('takeawayEnabled')?.checked ? 1 : 0,
        primary_color: websiteColors['--color-accent'] || '',
        accent_color: websiteColors['--color-gold'] || '',
        background_color: websiteColors['--color-bg'] || '',
        background_alt_color: websiteColors['--color-bg-alt'] || '',
        surface_color: websiteColors['--color-surface'] || '',
        surface_raised_color: websiteColors['--color-surface-raised'] || '',
        border_color: websiteColors['--color-border'] || '',
        text_color: websiteColors['--color-text'] || '',
        text_muted_color: websiteColors['--color-text-muted'] || '',
        text_faint_color: websiteColors['--color-text-faint'] || '',
        accent_dark_color: websiteColors['--color-accent-dark'] || '',
        accent_soft_color: websiteColors['--color-accent-soft'] || '',
        ember_color: websiteColors['--color-ember'] || '',
        success_color: websiteColors['--color-success'] || '',
        danger_color: websiteColors['--color-danger'] || '',
        website_colors: websiteColors
      };
    }

    function setTaxSettings(row) {
      var status = document.getElementById('taxConfigurationStatus');
      document.getElementById('taxpayerType').value = row.taxpayer_type || 'income_tax_only';
      document.getElementById('legalSellerName').value = row.legal_seller_name || row.restaurant_name || current?.name || '';
      document.getElementById('tradeName').value = row.trade_name || '';
      document.getElementById('sellerTaxNumber').value = row.seller_tax_number || '';
      document.getElementById('sellerNationalNumber').value = row.seller_national_number || '';
      document.getElementById('sellerAddress').value = row.seller_address || row.location || '';
      document.getElementById('sellerCity').value = row.seller_city || '';
      document.getElementById('sellerPhone').value = row.seller_phone || row.manager_number || '';
      document.getElementById('einvoicingEnabled').checked = Number(row.einvoicing_enabled || 0) === 1;
      document.getElementById('jofotaraClientId').value = row.jofotara_client_id || '';
      document.getElementById('jofotaraSecretKey').value = '';
      document.getElementById('jofotaraSecretKey').placeholder = row.has_secret_key ? '************' : 'Secret Key';
      document.getElementById('incomeSourceSequence').value = row.income_source_sequence || '';
      document.getElementById('defaultTaxRate').value = Number(row.default_tax_rate || 0);
      document.getElementById('pricesIncludeTax').checked = Number(row.prices_include_tax || 0) === 1;
      document.getElementById('invoicePrefix').value = row.invoice_prefix || 'INV';
      document.getElementById('automaticSubmission').checked = Number(row.automatic_submission ?? 1) === 1;
      document.getElementById('printAfterAccepted').checked = Number(row.print_after_accepted || 0) === 1;
      document.getElementById('invoicePrintFullPage').checked = Number(row.invoice_print_full_page || 0) === 1;
      document.getElementById('invoicePrintWidth').value = Number(row.invoice_print_width_mm || 80);
      document.getElementById('invoicePrintHeight').value = Number(row.invoice_print_height_mm || 297);
      document.getElementById('invoicePrintSizeFields').classList.toggle('d-none', Number(row.invoice_print_full_page || 0) === 1);

      if (status) {
        var label = (row.configuration_status || 'not_configured').replace(/_/g, ' ');
        status.textContent = label.charAt(0).toUpperCase() + label.slice(1);
        status.className = 'badge border ' + ({
          active: 'bg-success-subtle text-success border-success-subtle',
          configured: 'bg-primary-subtle text-primary border-primary-subtle',
          configuration_error: 'bg-danger-subtle text-danger border-danger-subtle'
        }[row.configuration_status] || 'bg-secondary-subtle text-secondary border-secondary-subtle');
      }
    }

    function taxSettingsPayload() {
      return {
        taxpayer_type: document.getElementById('taxpayerType').value,
        legal_seller_name: document.getElementById('legalSellerName').value.trim(),
        trade_name: document.getElementById('tradeName').value.trim(),
        seller_tax_number: document.getElementById('sellerTaxNumber').value.trim(),
        seller_national_number: document.getElementById('sellerNationalNumber').value.trim(),
        seller_address: document.getElementById('sellerAddress').value.trim(),
        seller_city: document.getElementById('sellerCity').value.trim(),
        seller_phone: document.getElementById('sellerPhone').value.trim(),
        einvoicing_enabled: document.getElementById('einvoicingEnabled').checked ? 1 : 0,
        jofotara_client_id: document.getElementById('jofotaraClientId').value.trim(),
        jofotara_secret_key: document.getElementById('jofotaraSecretKey').value.trim(),
        income_source_sequence: document.getElementById('incomeSourceSequence').value.trim(),
        default_tax_rate: Number(document.getElementById('defaultTaxRate').value || 0),
        prices_include_tax: document.getElementById('pricesIncludeTax').checked ? 1 : 0,
        invoice_prefix: document.getElementById('invoicePrefix').value.trim() || 'INV',
        automatic_submission: document.getElementById('automaticSubmission').checked ? 1 : 0,
        print_after_accepted: document.getElementById('printAfterAccepted').checked ? 1 : 0,
        invoice_print_full_page: document.getElementById('invoicePrintFullPage').checked ? 1 : 0,
        invoice_print_width_mm: Number(document.getElementById('invoicePrintWidth').value || 80),
        invoice_print_height_mm: Number(document.getElementById('invoicePrintHeight').value || 297)
      };
    }

    function showTaxError(error) {
      AdminUI.showError('taxSettingsAlert', error, 'Unable to save tax settings.', false);
    }

    request('/restaurants/' + restaurantId).then(function (payload) {
      setSettings(payload.data || {});
    }).catch(function (error) {
      showSettingsError(error.message || 'Unable to load restaurant settings.');
    });

    initHtmlEditors();
    initWebsitePreview();

    if (window.RESTAURANT_TAX_SETTINGS_ENABLED && document.getElementById('taxSettingsForm')) {
      request('/restaurants/' + restaurantId + '/tax-settings').then(function (payload) {
        setTaxSettings(payload.data || {});
      }).catch(showTaxError);

      document.getElementById('taxSettingsForm').addEventListener('submit', function (event) {
        event.preventDefault();
        document.getElementById('taxSettingsAlert').classList.add('d-none');
        request('/restaurants/' + restaurantId + '/tax-settings', {
          method: 'PUT',
          body: JSON.stringify(taxSettingsPayload())
        }).then(function (payload) {
          setTaxSettings(payload.data || {});
          swalToast('Tax settings saved');
        }).catch(showTaxError);
      });

      document.getElementById('testTaxConnectionBtn').addEventListener('click', function () {
        request('/restaurants/' + restaurantId + '/tax-settings-test', { method: 'POST' }).then(function (payload) {
          swalToast(payload.message || 'Configuration test passed');
        }).catch(showTaxError);
      });

      document.getElementById('invoicePrintFullPage').addEventListener('change', function (event) {
        document.getElementById('invoicePrintSizeFields').classList.toggle('d-none', event.target.checked);
      });
    }

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      alertBox.classList.add('d-none');
      var file = websiteHeroImageFile && websiteHeroImageFile.files ? websiteHeroImageFile.files[0] : null;
      var logoFile = websiteLogoImageFile && websiteLogoImageFile.files ? websiteLogoImageFile.files[0] : null;

      Promise.all([
        uploadImage(logoFile, 'website-logo'),
        uploadImage(file, 'website')
      ]).then(function (paths) {
        if (paths[0]) setValue('websiteLogoImageUrl', paths[0]);
        if (paths[1]) setValue('websiteHeroImageUrl', paths[1]);

        return request('/restaurants/' + restaurantId, {
          method: 'PUT',
          body: JSON.stringify(settingsPayload())
        });
      }).then(function (payload) {
        setSettings(payload.data || current || {});
        swalToast('Restaurant settings saved');
      }).catch(function (error) {
        var message = error.message || 'Unable to save restaurant settings.';
        if (error.errors) message = Object.values(error.errors).join(' ');
        showSettingsError(message);
      });
    });

    if (websiteHeroImageFile) {
      websiteHeroImageFile.addEventListener('change', function () {
        var file = websiteHeroImageFile.files && websiteHeroImageFile.files[0];
        setImagePreview(websiteHeroImagePreview, file ? URL.createObjectURL(file) : getValue('websiteHeroImageUrl'), 'bi bi-image');
      });
    }

    if (websiteLogoImageFile) {
      websiteLogoImageFile.addEventListener('change', function () {
        var file = websiteLogoImageFile.files && websiteLogoImageFile.files[0];
        setImagePreview(websiteLogoImagePreview, file ? URL.createObjectURL(file) : getValue('websiteLogoImageUrl'), 'bi bi-shop');
      });
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      initRestaurantSettings();
    });
  } else {
    initRestaurantSettings();
  }
})();

