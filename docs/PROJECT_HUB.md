# WCSS Internal Supplies — Project Hub

Single source of truth for architecture, routes, roles/caps, UI flows, and active TODOs.

_Last updated: {{update this date when you change}}_

---

## 0) Quick Start (Dev)

- **Requires:** WP + WooCommerce on Local; PHP 8.x recommended.
- **Install plugin:** place folder at `wp-content/plugins/wcss-internal-supplies`.
- **Activate:** WP Admin → Plugins → _WCSS Internal Supplies_.
- **Flush permalinks:** Settings → Permalinks → _Save_ (important for `/manager` routes).
- **Capabilities (one-time):**
  ```php
  add_action('init', function(){
    if ($r = get_role('shop_manager')) { $r->add_cap('wcss_manage_portal'); }
  }, 20);
  ```
