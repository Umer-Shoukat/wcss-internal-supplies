<?php if ( ! defined('ABSPATH') ) exit; ?>

<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <?php wp_head(); ?>   <!-- REQUIRED for wp_localize_script and enqueued assets -->
</head>


<div class="wcssm-wrap">
  <?php include WCSS_DIR . 'frontend/pages/partials/manager-nav.php'; ?>
  <h2>Dashboard</h2>
</div>