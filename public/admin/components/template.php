<?php
/**
 * Admin layout template.
 * Mirrors public/client/components/template.php.
 *
 * Expected variables:
 *   $template['title']  - '<title>' text
 *   $template['body']   - page body markup
 *   $template['footer'] - (optional) extra <script> tags before the closing body
 *   $auth_layout        - (bool) true renders only $template['body']
 *                         without the sidebar/topbar (used by auth pages)
 */
$template      = $template ?? [];
$auth_layout   = !empty($auth_layout);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars((string) ($template['title'] ?? 'Restaurant Management Panel')); ?></title>

  <!-- Bootstrap CSS -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

  <?php if (isset($template['header']['body'])) echo $template['header']['body']; ?>
</head>
<body class="<?= $auth_layout ? 'auth-body' : ''; ?>">
  <?php if ($auth_layout): ?>
    <?= $template['body'] ?? ''; ?>
  <?php else: ?>
    <?php include __DIR__ . '/sidebar.php'; ?>

    <div class="main-content">
      <?php include __DIR__ . '/navbar.php'; ?>
      <?= $template['body'] ?? ''; ?>
    </div>
  <?php endif; ?>

  <?php if (isset($template['footer'])) echo $template['footer']; ?>

  <!-- Bootstrap JS -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
</body>
</html>
