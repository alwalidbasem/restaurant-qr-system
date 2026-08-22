<?php
$status_context = $status_context ?? [];
$status_type = (string) ($status_context['type'] ?? 'error');
$status_icon = (string) ($status_context['icon'] ?? 'fa-circle-exclamation');
$status_title = (string) ($status_context['title'] ?? t('table_status_error_title'));
$status_message = (string) ($status_context['message'] ?? t('table_status_error_copy'));
$status_action = (string) ($status_context['action_label'] ?? t('table_status_try_again'));
$table_number = $status_context['table_number'] ?? null;
$table_status = $status_context['table_status'] ?? null;
?>

<main class="table-status-page table-status-page--<?= htmlspecialchars($status_type, ENT_QUOTES, 'UTF-8'); ?>">
  <section class="table-status-panel" aria-labelledby="tableStatusTitle">
    <div class="table-status-panel__brand">
      <?= app_logo_img('table-status-panel__logo'); ?>
      <span><?= app_brand_html(); ?></span>
    </div>

    <div class="table-status-panel__icon" aria-hidden="true">
      <i class="fa-solid <?= htmlspecialchars($status_icon, ENT_QUOTES, 'UTF-8'); ?>"></i>
    </div>

    <p class="section-eyebrow"><?= t('table_status_eyebrow'); ?></p>
    <h1 id="tableStatusTitle"><?= htmlspecialchars($status_title, ENT_QUOTES, 'UTF-8'); ?></h1>
    <p class="table-status-panel__copy"><?= htmlspecialchars($status_message, ENT_QUOTES, 'UTF-8'); ?></p>

    <?php if ($table_number !== null || $table_status !== null): ?>
      <dl class="table-status-details">
        <?php if ($table_number !== null): ?>
          <div>
            <dt><?= t('table_status_table_number'); ?></dt>
            <dd><?= htmlspecialchars((string) $table_number, ENT_QUOTES, 'UTF-8'); ?></dd>
          </div>
        <?php endif; ?>

        <?php if ($table_status !== null): ?>
          <div>
            <dt><?= t('table_status_current_status'); ?></dt>
            <dd><?= htmlspecialchars((string) $table_status, ENT_QUOTES, 'UTF-8'); ?></dd>
          </div>
        <?php endif; ?>
      </dl>
    <?php endif; ?>

    <a class="btn btn--primary table-status-panel__action" href="javascript:window.close();">
      <?= htmlspecialchars($status_action, ENT_QUOTES, 'UTF-8'); ?>
    </a>
  </section>
</main>
