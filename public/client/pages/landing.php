<script>
  window.CURRENT_LANGUAGE_CODE = <?= json_encode(current_language_code(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  window.MENU_FOODS_API_URL = window.MENU_FOODS_API_URL || <?= json_encode(app_url('api/menu-foods'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>

<div class="site-loader" id="siteLoader" role="status" aria-live="polite" aria-label="<?= t('loader_aria'); ?>">
  <div class="site-loader__logo" aria-hidden="true">
    <span class="site-loader__mark"><?= app_logo_img('site-loader__logo-img'); ?></span>
    <span class="site-loader__text"><?= app_brand_html(); ?></span>
  </div>
  <span class="site-loader__ring" aria-hidden="true"></span>
</div>

<div class="language-gate" id="languageGate" role="dialog" aria-modal="true" aria-labelledby="languageGateTitle" hidden>
  <div class="language-gate__panel" tabindex="-1">
    <p class="language-gate__eyebrow"><?= t('gate_eyebrow'); ?></p>
    <h2 class="language-gate__title" id="languageGateTitle"><?= t('gate_title'); ?></h2>
    <div class="language-gate__options" role="group" aria-label="<?= t('gate_options_aria'); ?>">
      <button type="button" class="language-card" data-language-choice="en">
        <span class="language-card__name"><?= t('gate_en'); ?></span>
      </button>
      <button type="button" class="language-card" data-language-choice="ar" dir="rtl">
        <span class="language-card__name"><?= t('gate_ar'); ?></span>
      </button>
    </div>
  </div>
</div>

<?php include(__DIR__ . "/../components/navbar.php"); ?>

<section class="hero" id="top">
  <div class="hero__media" id="heroMedia">
    <img
      src="https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=1920&q=80"
      alt="<?= t('hero_img_alt'); ?>"
      class="hero__img"
      id="heroImg">
    <div class="hero__overlay" aria-hidden="true"></div>
  </div>

  <div class="hero__content">
    <p class="hero__eyebrow"><i class="fa-solid fa-circle open-dot" aria-hidden="true"></i> <?= t('hero_eyebrow'); ?></p>
    <h1 class="hero__title"><?= t('hero_title_1'); ?><br><span class="hero__title-accent"><?= t('hero_title_2'); ?></span></h1>
    <p class="hero__desc"><?= t('hero_desc'); ?></p>
    <div class="hero__actions">
      <a href="#menu" class="btn btn--primary" id="exploreMenuBtn">
        <?= t('hero_explore'); ?> <i class="fa-solid fa-arrow-down-long" aria-hidden="true"></i>
      </a>
    </div>
  </div>

  <a href="#menu" class="hero__scroll-cue" aria-label="<?= t('hero_scroll_aria'); ?>">
    <span></span>
  </a>
</section>

<section class="menu-section" id="menu">
  <div class="menu-section__inner">

    <div class="menu-header">
      <p class="section-eyebrow"><?= t('menu_eyebrow'); ?></p>
      <h2 class="section-title"><?= t('menu_title'); ?></h2>
      <p class="section-subtitle"><?= t('menu_subtitle'); ?></p>
    </div>

    <div class="menu-controls">
      <label class="menu-select" for="menuCategory">
        <span class="sr-only"><?= t('menu_filter_sr'); ?></span>
        <select id="menuCategory" aria-label="<?= t('menu_filter_aria'); ?>">
          <option value="all"><?= t('menu_all'); ?></option>
        </select>
        <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
      </label>
    </div>

    <div class="food-grid" id="foodGrid" aria-live="polite"></div>

    <div class="empty-state" id="emptyState" hidden>
      <i class="fa-solid fa-fire-flame-simple" aria-hidden="true"></i>
      <h3><?= t('empty_title'); ?></h3>
      <p><?= t('empty_desc'); ?></p>
    </div>
  </div>
</section>


<div class="modal-backdrop" id="modalBackdrop" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle" id="foodModal">
    <button class="modal__close" id="modalCloseBtn" aria-label="<?= t('modal_close_aria'); ?>">
      <i class="fa-solid fa-xmark" aria-hidden="true"></i>
    </button>

    <div class="modal__media">
      <img id="modalImg" src="" alt="">
      <span class="modal__badge" id="modalBadge" hidden></span>
    </div>

    <div class="modal__body">
      <p class="modal__category" id="modalCategory"></p>
      <h3 class="modal__title" id="modalTitle"></h3>
      <p class="modal__desc" id="modalDesc"></p>
      <p class="modal__step" id="modalStepText"></p>

      <div class="modal__addons" id="modalAddons" hidden>
        <h4 id="modalAddonsTitle"><?= t('modal_addons_title'); ?></h4>
        <div class="modal__addons-list" id="modalAddonsList"></div>
      </div>
      <div class="modal__footer">
        <div class="modal-bill" aria-label="<?= t('modal_bill_aria'); ?>">
          <div class="modal-bill__row">
            <span><?= t('bill_extras'); ?></span>
            <strong id="modalBillExtras">0.00 JOD</strong>
          </div>
          <div class="modal-bill__row">
            <span><?= t('bill_qty'); ?></span>
            <strong id="modalBillQty">1</strong>
          </div>
          <div class="modal-bill__row modal-bill__row--total">
            <span><?= t('bill_total'); ?></span>
            <strong class="modal__price" id="modalPrice">0.00 JOD</strong>
          </div>
        </div>
        <p class="modal__qty-label"><?= t('qty_label'); ?></p>
        <div class="qty-selector" role="group" aria-label="<?= t('qty_group_aria'); ?>">
          <button type="button" class="qty-btn" id="modalQtyMinus" aria-label="<?= t('qty_decrease_aria'); ?>">−</button>
          <input class="qty-value" id="modalQtyValue" aria-label="<?= t('qty_aria'); ?>">
          <button type="button" class="qty-btn" id="modalQtyPlus" aria-label="<?= t('qty_increase_aria'); ?>">+</button>
        </div>
        <button type="button" class="btn btn--ghost modal__back-btn" id="modalBackBtn"><?= t('modal_back'); ?></button>
        <button type="button" class="btn btn--primary" id="modalNextBtn"><?= t('modal_next'); ?></button>
        <button type="button" class="btn btn--primary" id="modalAddBtn"><?= t('modal_add'); ?></button>
      </div>
    </div>
  </div>
</div>

<div class="drawer-backdrop" id="drawerBackdrop" hidden></div>
<aside class="order-drawer" id="orderDrawer" role="dialog" aria-modal="true" aria-labelledby="drawerTitle" hidden>
  <div class="order-drawer__header">
    <h3 id="drawerTitle"><i class="fa-solid fa-bag-shopping" aria-hidden="true"></i> <?= t('drawer_title'); ?></h3>
    <button class="drawer-close" id="drawerCloseBtn" aria-label="<?= t('drawer_close_aria'); ?>">
      <i class="fa-solid fa-xmark" aria-hidden="true"></i>
    </button>
  </div>

  <div class="order-drawer__body" id="orderItems"></div>

  <div class="order-drawer__empty" id="orderEmpty">
    <i class="fa-solid fa-utensils" aria-hidden="true"></i>
    <p><?= t('drawer_empty_title'); ?></p>
    <span><?= t('drawer_empty_desc'); ?></span>
  </div>

  <div class="order-drawer__footer" id="orderFooter" hidden>
    <div class="order-total">
      <span><?= t('order_total'); ?></span>
      <strong id="orderTotal">0.00 JOD</strong>
    </div>
    <button class="btn btn--primary btn--full" id="placeOrderBtn"><?= t('place_order'); ?></button>
  </div>
</aside>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
