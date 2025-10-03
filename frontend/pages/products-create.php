<?php if ( ! defined('ABSPATH') ) exit; ?>

<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <?php wp_head(); ?>   <!-- REQUIRED for wp_localize_script and enqueued assets -->
</head>

<div class="wcssm-wrap">
  <?php include WCSS_DIR . 'frontend/pages/partials/manager-nav.php'; ?>
  <h2>Create Product</h2>

  <?php include WCSS_DIR . 'frontend/pages/partials/product-create-form.php'; ?>
</div>
<script>
jQuery(function($){
  if (window.WCSSM && WCSSM.view==='products' && WCSSM.action==='create') {
    if (typeof window.initProductCreate === 'function') window.initProductCreate();
  }
});
</script>