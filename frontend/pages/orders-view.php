<?php if ( ! defined('ABSPATH') ) exit; ?>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <?php wp_head(); ?>
</head>

<div class="wcssm-wrap">
  <?php include WCSS_DIR . 'frontend/pages/partials/manager-nav.php'; ?>

  <div class="wcssm-header">
    <h1>Order Details</h1>
    <a href="<?php echo esc_url( home_url('/manager/orders') ); ?>" class="btn btn-light">← Back to Orders</a>
  </div>

  <div id="order-detail">
    <p>Loading order…</p>
  </div>
</div>







<script>
jQuery(function($){
  if (window.WCSSM && WCSSM.view==='orders' && WCSSM.action==='view' && WCSSM.id) {
    if (typeof window.initOrderView === 'function') window.initOrderView(WCSSM.id);
  }
});
</script>


<style>

  /* status pill by slug */
.badge { display:inline-block; padding:.2rem .5rem; border-radius:999px; font-size:.85rem; font-weight:600; }
.badge[data-slug="awaiting-approval"] { background:#fff4e5; color:#8a5800; }
.badge[data-slug="approved"]          { background:#eaf7ef; color:#0f6d2a; }
.badge[data-slug="rejected"]          { background:#fde9ea; color:#8a1020; }

/* ledger box */
.ov-ledger { border:1px solid #e5e7eb; border-radius:12px; padding:16px; background:#fff; margin-top:16px; }
.ov-ledger .kv { display:flex; justify-content:space-between; margin:.25rem 0; }

.table .sku {
  font-size: 12px;
  color: #888;
}
</style>