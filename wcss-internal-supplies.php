<?php
/**
 * Plugin Name: WCSS – Internal Supplies for WooCommerce
 * Description: Converts WooCommerce into an internal supplies portal with login-only ordering, monthly order quotas, budget enforcement, and approval workflow (scaffold).
 * Version: 0.1.1
 * Author:  adex360 LTD
 * Text Domain: wcss
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** ----------------------------------------------------------------
 * Constants
 * --------------------------------------------------------------- */
define( 'WCSS_VERSION', '0.1.1' );
define( 'WCSS_FILE', __FILE__ );
define( 'WCSS_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCSS_URL', plugin_dir_url( __FILE__ ) );

/** ----------------------------------------------------------------
 * Includes (minimal at this stage)
 * --------------------------------------------------------------- */

 require_once WCSS_DIR . 'includes/class-status-policy.php';
require_once WCSS_DIR . 'includes/class-activator.php';
require_once WCSS_DIR . 'includes/class-admin-settings.php';
require_once WCSS_DIR . 'includes/class-store-cpt.php';
require_once WCSS_DIR . 'includes/class-user-store-map.php';
require_once WCSS_DIR . 'includes/class-order-statuses.php';
require_once WCSS_DIR . 'includes/class-approval-workflow.php';
require_once WCSS_DIR . 'includes/class-private-portal.php';
require_once WCSS_DIR . 'includes/class-checkout-store-lock.php';
require_once WCSS_DIR . 'includes/class-myaccount-customize.php';
require_once WCSS_DIR . 'includes/class-order-quota.php';
require_once WCSS_DIR . 'includes/class-notifications.php';
require_once WCSS_DIR . 'includes/class-quota-usage-ui.php';
require_once WCSS_DIR . 'includes/class-store-admin-columns.php';
require_once WCSS_DIR . 'includes/class-store-admin-highlight.php';
require_once WCSS_DIR . 'includes/class-admin-menu-restrict.php';
require_once WCSS_DIR . 'includes/class-order-events.php';
// require_once WCSS_DIR . 'includes/class-manager-rest.php';
require_once WCSS_DIR . 'frontend/routes/class-routes-manager.php';

require_once WCSS_DIR . 'includes/rest/class-rest-orders.php';
require_once WCSS_DIR . 'includes/rest/class-rest-products.php';
require_once WCSS_DIR . 'includes/rest/class-rest-stores.php';
require_once WCSS_DIR . 'includes/class-wcss-ledger.php';
require_once WCSS_DIR . 'includes/rest/class-rest-ledger.php';

require_once WCSS_DIR . 'includes/class-wcss-vendors.php';



// On activation
// register_activation_hook( __FILE__, function () {
//     if ( class_exists( 'WCSS_Ledger' ) ) {
//         WCSS_Ledger::install();
//     } else {
//         // Fallback include if load order differs
//         require_once WCSS_DIR . 'includes/class-wcss-ledger.php';
//         WCSS_Ledger::install();
//     }
// });

// // Self-heal in case activation didn’t run (e.g., plugin already active)
// add_action( 'plugins_loaded', function () {
//     if ( class_exists( 'WCSS_Ledger' ) && ! WCSS_Ledger::exists() ) {
//         WCSS_Ledger::install();
//     }
// }, 20);




/** ----------------------------------------------------------------
 * Activation / Deactivation
 * --------------------------------------------------------------- */
register_activation_hook( __FILE__, ['WCSS_Activator', 'activate'] );
register_deactivation_hook( __FILE__, ['WCSS_Activator', 'deactivate'] );

register_activation_hook( __FILE__, function () {
    global $wpdb;
    $table = $wpdb->prefix . 'wcss_order_events';
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        order_id BIGINT UNSIGNED NOT NULL,
        type VARCHAR(64) NOT NULL,          -- status_change|note|shipment|receiving|incident|exception
        payload LONGTEXT NULL,               -- JSON
        created_at DATETIME NOT NULL,
        created_by BIGINT UNSIGNED NULL,
        PRIMARY KEY (id),
        KEY order_id (order_id),
        KEY type (type),
        KEY created_at (created_at)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
});

register_activation_hook( __FILE__, function () {
    global $wpdb;
    $table   = $wpdb->prefix . 'wcss_store_monthly_ledger';
    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        store_id BIGINT UNSIGNED NOT NULL,
        ym CHAR(7) NOT NULL,                 -- 'YYYY-MM'
        orders_count INT UNSIGNED NOT NULL DEFAULT 0,
        spend_total DECIMAL(16,2) NOT NULL DEFAULT 0.00,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_store_month (store_id, ym),
        KEY store_id (store_id),
        KEY ym (ym)
    ) $charset;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
});


/** ----------------------------------------------------------------
 * Helpers (keep these lightweight & pure)
 * --------------------------------------------------------------- */
/**
 * Get a plugin option with defaults merged.
 *
 * @param string $key
 * @param mixed  $default
 * @return mixed
 */
function wcss_get_option( $key, $default = null ) {
    $defaults = [
        'visibility_mode'        => 'public_catalog', // public_catalog | fully_private
        'default_monthly_quota'  => 1,
        'budget_enforcement'     => 1,
        'exempt_product_ids'     => '',
        'bypass_method'          => 'internal_requisition', // internal_requisition | cod | coupon
        'count_statuses'         => ['wc-awaiting-approval','wc-approved'],
    ];
    $opts = wp_parse_args( get_option( 'wcss_settings', [] ), $defaults );
    return array_key_exists( $key, $opts ) ? $opts[ $key ] : $default;
}

/**
 * Convenience: is site fully private (login required for all pages)?
 *
 * @return bool
 */
function wcss_is_fully_private() {
    return wcss_get_option( 'visibility_mode' ) === 'fully_private';
}

/** ----------------------------------------------------------------
 * Bootstrap
 * --------------------------------------------------------------- */
add_action( 'plugins_loaded', function() {

    // Load translations early
    load_plugin_textdomain( 'wcss', false, dirname( plugin_basename( WCSS_FILE ) ) . '/languages' );

    // Require WooCommerce
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'WCSS requires WooCommerce to be active.', 'wcss' ) . '</p></div>';
        } );
        return;
    }

    // Admin settings UI
    if ( is_admin() ) {
        new WCSS_Admin_Settings();
    }
    do_action( 'wcss_bootstrap' );


    
} );

add_action( 'wcss_bootstrap', function () {
    new WCSS_Store_CPT();
    new WCSS_User_Store_Map();
    new WCSS_Order_Statuses();
    new WCSS_Approval_Workflow();
    if ( class_exists( 'WCSS_Status_Policy' ) ) {
        new WCSS_Status_Policy();
    }
    new WCSS_Private_Portal();
    require_once WCSS_DIR . 'includes/class-gateway-internal-requisition.php';
    require_once WCSS_DIR . 'includes/class-gateway-internal-requisition-bootstrap.php';
    new WCSS_Gateway_Internal_Requisition_Bootstrap();
    new WCSS_Checkout_Store_Lock();
    new WCSS_MyAccount_Customize();
    new WCSS_Order_Quota();
    new WCSS_Notifications();
    new WCSS_Quota_Usage_UI();
    new WCSS_Store_Admin_Columns();
    new WCSS_Store_Admin_Highlight(); 
    new WCSS_Admin_Menu_Restrict();
    // new WCSS_Manager_REST();
    new WCSS_Route_Manager();

    new WCSS_REST_Orders();
    new WCSS_REST_Products();
    new WCSS_REST_Stores();
    new WCSS_REST_Ledger();
    new WCSS_Vendors();

} );



// adding logs for orders status changes to make order delivery reporting

add_action( 'woocommerce_order_status_changed', function( $order_id, $old, $new, $order ) {
    WCSS_Order_Events::log( (int) $order_id, 'status_change', [
        'old' => $old, 'new' => $new, 'note' => 'Status changed'
    ] );
}, 10, 4 );

add_action( 'woocommerce_new_order_note', function( $note_id, $args ) {
    // capture public order notes created via UI or API
    if ( ! empty( $args['order_id'] ) ) {
        WCSS_Order_Events::log( (int) $args['order_id'], 'note', [
            'note_id' => (int) $note_id, 'is_customer_note' => (bool) ($args['customer_note'] ?? false)
        ] );
    }
}, 10, 2 );



add_action( 'init', function () {
    foreach ( [ 'administrator', 'shop_manager' /*, 'supply_manager' */ ] as $role_name ) {
        if ( $role = get_role( $role_name ) ) {
            if ( ! $role->has_cap( 'wcss_manage_portal' ) ) {
                $role->add_cap( 'wcss_manage_portal' );
            }
        }
    }
}, 20);


//Update ledger on status transitions


add_action( 'woocommerce_order_status_changed', function( $order_id, $old, $new, $order ){
    // Which statuses count toward monthly usage
    $count_in   = apply_filters( 'wcss_ledger_count_in',   ['approved','processing','completed'] );
    $count_out  = apply_filters( 'wcss_ledger_count_out',  ['rejected','cancelled','refunded'] );

    // Determine delta to apply
    $delta = 0;
    if ( in_array( $old, $count_in, true ) && ! in_array( $new, $count_in, true ) ) { $delta = -1; }
    if ( ! in_array( $old, $count_in, true ) && in_array( $new, $count_in, true ) ) { $delta = +1; }

    // Amount delta: only when crossing in/out of count domain
    $amount = 0.0;
    if ( $delta !== 0 ) {
        $amount = (float) $order->get_total() * ( $delta > 0 ? 1 : -1 );
    }
    // Also treat explicit out statuses as removing if they weren’t already handled
    if ( $delta === 0 && in_array( $new, $count_out, true ) && in_array( $old, $count_in, true ) ) {
        $delta  = -1;
        $amount = - (float) $order->get_total();
    }

    if ( 0 === $delta && 0.0 === $amount ) return;

    // Get store id from order meta (set earlier when we map user->store at checkout)
    $store_id = (int) $order->get_meta( '_wcss_store_id' );
    if ( ! $store_id ) return;

    // Which month? Use order created month (so retro changes keep the original month)
    $created = $order->get_date_created();
    $ym = $created ? $created->date_i18n('Y-m') : WCSS_Ledger::ym_now();

    WCSS_Ledger::bump( $store_id, $ym, $delta, $amount );
}, 10, 4 );



/** ----------------------------------------------------------------
 * Optional: small guard if you later toggle "Fully Private"
 * (We will flesh this out in a later phase with redirects & exceptions.)
 * --------------------------------------------------------------- */
// add_action( 'template_redirect', function() {
//     if ( wcss_is_fully_private() && ! is_user_logged_in() ) {
//         // Allow wp-login, account, or specific endpoints as needed.
//         auth_redirect(); // sends to wp-login.php with redirect back
//     }
// } );


// Run once manually (then remove/comment)
// add_action( 'admin_init', function (){
//     if ( ! current_user_can('manage_options') ) return;
//     if ( ! isset($_GET['wcss_backfill_ledger']) ) return;

//     $orders = wc_get_orders([
//         'limit' => -1,
//         'status' => ['approved','processing','completed'],
//         'date_created' => gmdate('Y-m-01 00:00:00') . '...' . gmdate('Y-m-t 23:59:59'),
//         'return' => 'objects',
//     ]);
//     foreach ( $orders as $o ) {
//         $store_id = (int) $o->get_meta('_wcss_store_id');
//         if ( ! $store_id ) continue;
//         $ym = $o->get_date_created()->date_i18n('Y-m');
//         WCSS_Ledger::bump( $store_id, $ym, +1, (float) $o->get_total() );
//     }
//     wp_die('Backfilled.');
// });


// On activation: make sure rewrites are built
// register_activation_hook( __FILE__, function(){
//     flush_rewrite_rules();
// });

// // One-time manual flush helper (visit /?wcss_flush=1 while logged in as admin)
// add_action('init', function(){
//     if ( isset($_GET['wcss_flush']) && current_user_can('manage_options') ) {
//         flush_rewrite_rules();
//     }
// });



// add_action('init', function(){
//     if ( isset($_GET['wcss_flush']) && current_user_can('manage_options') ) {
//         flush_rewrite_rules(true);
//         wp_die('WCSS: rewrite rules flushed');
//     }
// });

// // 2) Dump rules to verify our /manager rules are present
// add_action('init', function(){
//     if ( isset($_GET['wcss_show_rules']) && current_user_can('manage_options') ) {
//         global $wp_rewrite;
//         header('Content-Type: text/plain; charset=utf-8');
//         print_r( $wp_rewrite->wp_rewrite_rules() );
//         exit;
//     }
// });




add_action('template_redirect', function(){
    // Log what WP parsed
    if ( isset($_GET['wcss_debug']) && current_user_can('manage_options') ) {
        error_log( 'WCSS DEBUG vars: ' . print_r([
            'wcss'        => get_query_var('wcss'),
            'wcss_action' => get_query_var('wcss_action'),
            'wcss_id'     => get_query_var('wcss_id'),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
        ], true) );
    }

    // Ultra simple test renderer (short-circuits the theme):
    if ( get_query_var('wcss') === 'dashboard' && isset($_GET['wcss_probe']) ) {
        status_header(200); nocache_headers(); show_admin_bar(false);
        echo '<div style="padding:20px;font:14px/1.4 system-ui">Router OK — dashboard</div>';
        exit;
    }
}, 0);

