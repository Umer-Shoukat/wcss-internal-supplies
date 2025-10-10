<?php if ( ! defined('ABSPATH') ) exit; ?>


<div class="wcssm-wrap">
<?php include WCSS_DIR . 'frontend/pages/partials/manager-nav.php'; ?>
  <div class="wcssm-header">
    <h1>Create Store</h1>
    <a class="btn btn-light" href="<?php echo esc_url( home_url('/manager/stores') ); ?>">← Back</a>
  </div>

  <form id="store-form" onsubmit="return false;">
    <div class="grid-2">
      <div><label>Name *</label><input id="s-name" class="input" required></div>
      <div><label>Code</label><input id="s-code" class="input"></div>
    </div>

    <div class="grid-2">
      <div><label>City</label><input id="s-city" class="input"></div>
      <div><label>State/Province</label><input id="s-state" class="input"></div>
    </div>

    <div class="grid-2">
      <div><label>Monthly Quota</label><input id="s-quota" class="input" type="number" min="0" step="1"></div>
      <div><label>Monthly Budget</label><input id="s-budget" class="input" type="number" min="0" step="0.01"></div>
    </div>

    <div class="grid-1">
    <label>Store Employee *</label>
    <select id="s-user" class="input" required></select>
  </div>


    <div class="actions">
      <button id="s-save" class="btn btn-primary" type="button">Create</button>
      <span id="s-msg" class="muted"></span>
    </div>
  </form>
</div>

<script>
jQuery(function($){
  if (window.WCSSM && WCSSM.view==='stores' && WCSSM.action==='create') {
    if (typeof window.initStoreCreate === 'function') window.initStoreCreate();
  }
});
</script>