<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="wcssm-wrap">
  <?php include WCSS_DIR . 'frontend/partials/manager-nav.php'; ?>
  <h2>Edit Product</h2>

  <?php include WCSS_DIR . 'frontend/partials/product-create-form.php'; ?>
  <!-- We reuse the same form markup; JS will prefill + save via PUT -->
  <div style="margin-top:8px">
    <button id="p-update" class="btn">Save Changes</button>
    <a href="<?php echo esc_url( home_url('/manager/products') ); ?>" class="btn btn-light" style="margin-left:8px">Back</a>
    <div id="p-msg" class="ksub" style="margin-top:8px"></div>
  </div>
</div>
<script>
jQuery(function($){
  if (window.WCSSM && WCSSM.view==='products' && WCSSM.action==='edit') {
    if (typeof window.initProductEdit === 'function') window.initProductEdit(WCSSM.id);
  }
});
</script>