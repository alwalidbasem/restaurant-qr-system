<header class="navbar" id="navbar">
  <div class="navbar__inner">
    <a href="#top" class="navbar__logo" aria-label="<?= t('nav_logo_aria'); ?>">
      <span class="navbar__logo-mark"><?= app_logo_img('navbar__logo-img'); ?></span>
      <span class="navbar__logo-text"><?= app_brand_html(); ?></span>
    </a>

    <nav class="navbar__links" id="navLinks" aria-label="Primary">
      <a href="#top" class="navbar__link"><?= t('nav_home'); ?></a>
      <a href="#menu" class="navbar__link"><?= t('nav_menu'); ?></a>
      <a href="#location" class="navbar__link"><?= t('nav_location'); ?></a>
    </nav>

    <div class="navbar__actions">
      <button class="order-btn" id="viewOrderBtn" aria-haspopup="dialog" aria-controls="orderDrawer" aria-label="<?= t('nav_order_aria'); ?>">
        <i class="fa-solid fa-bag-shopping" aria-hidden="true"></i>
        <span><?= t('nav_view_order'); ?></span>
        <span class="order-btn__badge" id="orderBadge" aria-hidden="true">0</span>
      </button>


      <button class="hamburger" id="hamburgerBtn" aria-label="<?= t('nav_open_menu'); ?>" aria-expanded="false" aria-controls="navLinks">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
</header>
