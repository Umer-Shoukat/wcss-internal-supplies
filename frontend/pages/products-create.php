<?php if ( ! defined('ABSPATH') ) exit; ?>
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