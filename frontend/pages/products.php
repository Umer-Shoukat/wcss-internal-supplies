<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="wcssm-wrap">
  <?php include WCSS_DIR . 'frontend/pages/partials/manager-nav.php'; ?>


  <div id="wcssm-flash" class="wcssm-flash" style="display:none;"></div>

  


  <div class="wcssm-header">
    <h2>Products</h2>

    <div>
    <input id="p-search" class="input" placeholder="Search products…">
    <button id="p-refresh" class="btn btn-light">Search</button>
    <a class="btn btn-light" target="_blank" href="<?php echo admin_url('edit.php?post_type=product&page=product_exporter'); ?>">Export All (Woo)</a>
  </div>

    <a href="<?php echo esc_url( home_url('/manager/products/create') ); ?>" class="btn btn-primary">+ Create Product</a>
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