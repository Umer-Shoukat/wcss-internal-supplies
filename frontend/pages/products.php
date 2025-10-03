<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="wcssm-wrap">
  <?php include WCSS_DIR . 'frontend/pages/partials/manager-nav.php'; ?>
  <div class="flex-between">
    <h2>Products</h2>
    <a href="<?php echo esc_url( home_url('/manager/products/create') ); ?>" class="btn">+ Create Product</a>
  </div>

  <div id="wcssm-products-grid" class="tablelike" style="margin-top:12px"></div>
</div>


<script>
jQuery(function($){
  // Page initializer is in manager.js (initProductsList)
  if (window.WCSSM && WCSSM.view==='products' && !WCSSM.action) {
    if (typeof window.initProductsList === 'function') window.initProductsList();
  }
});

</script>