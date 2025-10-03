<!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php wp_head(); ?>
</head>
<body class="wcss-manager-body">
  <div class="wcssm-wrap">

    <div class="wcssm-header-cards">
      <div class="controls">
        <div class="row">
          <label for="wcssm-store-picker">Store</label>
          <select id="wcssm-store-picker" class="input"></select>
        </div>

        <div class="row">
          <label for="wcssm-month">Month</label>
          <div class="month-nav">
            <button class="btn btn-icon" id="wcssm-month-prev" aria-label="Previous month">‹</button>
            <input id="wcssm-month" class="input" type="month" value="<?php echo esc_attr( gmdate('Y-m') ); ?>">
            <button class="btn btn-icon" id="wcssm-month-next" aria-label="Next month">›</button>
          </div>
        </div>

        <button class="btn" id="wcssm-refresh-usage">Refresh</button>
      </div>

      <div class="cards">
        <div class="kcard" id="k-orders-card">
          <div class="klabel">Orders used</div>
          <div class="kvalue"><span id="k-orders">—</span></div>
          <div class="progress"><div class="bar" id="k-orders-bar" style="width:0%"></div></div>
          <div class="ksub" id="k-orders-sub">—</div>
        </div>

        <div class="kcard" id="k-budget-card">
          <div class="klabel">Budget used</div>
          <div class="kvalue"><span id="k-budget">—</span></div>
          <div class="progress"><div class="bar" id="k-budget-bar" style="width:0%"></div></div>
          <div class="ksub" id="k-budget-sub">—</div>
        </div>
      </div>

      <div class="alerts" id="wcssm-usage-alert" style="display:none;"></div>
    </div>

    <header class="wcssm-header">
      <h1>Store Manager</h1>
      <nav class="wcssm-tabs">
        <a href="#approvals" class="active">Approvals</a>
        <a href="#orders">Orders</a>
        <a href="#products">Products</a>
        <a href="#stores">Stores</a>
      </nav>
    </header>



    <main class="wcssm-main">
      <section id="approvals" class="tab active">
        <h2>Pending Approvals</h2>
        <div id="wcssm-approvals-list" class="card-list" data-endpoint="orders" data-filter="status=awaiting-approval"></div>
        <!-- <div id="wcssm-approvals-list" class="card-list" data-endpoint="orders" data-filter="status=pending"></div> -->
      </section>

      <section id="orders" class="tab">
        <h2>Orders</h2>
        <div class="toolbar">
          <select id="orders-status-filter" class="input">
            <option value="">All</option>
            <option value="pending">Pending</option>
            <option value="awaiting-approval">Awaiting approval</option>
            <option value="approved">Approved</option>
            <option value="processing">Processing</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
            <option value="rejected">Rejected</option>
          </select>
          <button class="btn" id="orders-refresh">Refresh</button>
        </div>
        <div id="wcssm-orders-list" class="card-list" data-endpoint="orders"></div>

        <!-- Simple modal -->
        <div id="order-modal" class="modal card" style="display:none">
          <div><strong id="order-modal-title"></strong></div>
          <div class="grid">
            <input class="input" id="om-edd" placeholder="Expected delivery (YYYY-MM-DD)">
            <input class="input" id="om-ref" placeholder="Internal reference">
            <input class="input" id="om-note" placeholder="Add note (optional)">
          </div>
          <div>
            <button class="btn btn-primary" id="om-save">Save</button>
            <button class="btn" id="om-close">Close</button>
          </div>
        </div>
      </section>

      <section id="products" class="tab">
        <h2>Products</h2>
        <form id="form-product" class="card form">
          <div><strong id="prod-form-title">Create product</strong></div>
          <input type="hidden" name="id" />
          <input class="input" name="name" placeholder="Name" required>
          <input class="input" name="sku" placeholder="SKU">
          <input class="input" name="price" placeholder="Price" type="number" step="0.01" required>
          <input class="input" name="stock_qty" placeholder="Stock qty" type="number" step="1">
          <select class="input" name="status">
            <option value="publish">Publish</option>
            <option value="draft">Draft</option>
          </select>
          <div><button class="btn btn-primary" type="submit">Save</button> <button class="btn" type="button" id="prod-cancel">Cancel</button></div>
        </form>
        <div id="wcssm-products-list" class="card-list" data-endpoint="products"></div>
      </section>

      <section id="stores" class="tab">
        <h2>Stores</h2>
        <form id="form-store" class="card form">
          <div><strong id="store-form-title">Create store</strong></div>
          <input type="hidden" name="id" />
          <input class="input" name="name" placeholder="Store name" required>
          <input class="input" name="code" placeholder="Code">
          <input class="input" name="address" placeholder="Address">
          <div class="grid">
            <input class="input" name="city" placeholder="City">
            <input class="input" name="state" placeholder="State">
            <input class="input" name="postcode" placeholder="Postcode">
            <input class="input" name="country" placeholder="Country">
          </div>
          <div class="grid">
            <input class="input" name="quota" placeholder="Monthly quota" type="number" step="1">
            <input class="input" name="budget" placeholder="Monthly budget" type="number" step="0.01">
          </div>
          <div><button class="btn btn-primary" type="submit">Save</button> <button class="btn" type="button" id="store-cancel">Cancel</button></div>
        </form>
        <div id="wcssm-stores-list" class="card-list" data-endpoint="stores"></div>
      </section>
    </main>
  </div>
  <?php wp_footer(); ?>
</body>
</html>