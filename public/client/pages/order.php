<main class="order-status-page" id="orderStatusPage">
  <section class="order-status-hero">
    <div class="order-status-hero__inner">

      <div class="order-status-summary">
        <div class="order-status-summary__main">
          <p class="section-eyebrow"><?= t('status_eyebrow'); ?></p>
          <h1><?= t('status_title_prefix'); ?> <span id="orderStatusValue"><?= t('status_loading'); ?></span></h1>
          <p class="order-status-summary__copy"><?= t('status_copy'); ?></p>
        </div>

        <div class="order-status-total" aria-label="<?= t('status_total_aria'); ?>">
          <span><?= t('status_total_label'); ?></span>
          <strong id="orderStatusTotal">0.00 JOD</strong>
        </div>
      </div>

      <div class="order-status-meta" aria-label="<?= t('status_info_aria'); ?>">
        <div>
          <span><?= t('status_order_number'); ?></span>
          <strong id="orderStatusId">--</strong>
        </div>
        <div>
          <span><?= t('status_placed_at'); ?></span>
          <strong id="orderStatusTime">--</strong>
        </div>
      </div>
    </div>
  </section>

  <section class="order-status-list-section">
    <div class="order-status-list-section__inner">
      <div class="order-status-list-head">
        <h2><?= t('status_items_title'); ?></h2>
        <span><?= t('status_live_summary'); ?></span>
      </div>

      <div class="order-status-list" id="orderStatusItems"></div>

      <div class="order-status-empty" id="orderStatusEmpty" hidden>
        <i class="fa-solid fa-receipt" aria-hidden="true"></i>
        <h2><?= t('status_empty_title'); ?></h2>
        <p><?= t('status_empty_desc'); ?></p>
        <a href="<?= htmlspecialchars(localized_app_url(), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn--primary"><?= t('status_open_menu'); ?></a>
      </div>
    </div>
  </section>
</main>
