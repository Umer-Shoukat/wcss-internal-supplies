<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class WCSS_Route_Manager {

    public function __construct() {
        add_action( 'init',                [ $this, 'register_rewrite' ] );
        add_filter( 'query_vars',          [ $this, 'add_qv' ] );
        add_action( 'template_redirect',   [ $this, 'maybe_render' ] );
        add_action( 'wp_enqueue_scripts',  [ $this, 'enqueue_assets' ] );
    }

    /**
     * Pretty URLs:
     *  /manager                       -> wcssm=dashboard
     *  /manager/{view}                -> wcssm={view}
     *  /manager/{view}/{action}       -> wcssm={view}&wcssm_action={action}
     *  /manager/{view}/{action}/{id}  -> wcssm={view}&wcssm_action={action}&wcssm_id={id}
     */


    public function register_rewrite() {
        // Tell WP these query vars exist
        add_rewrite_tag( '%wcss%',        '([a-z0-9_-]+)' );
        add_rewrite_tag( '%wcss_action%', '([a-z0-9_-]+)' );
        add_rewrite_tag( '%wcss_id%',     '([0-9]+)' );

        // Allowed sections under /manager
        $sections = '(products|stores|orders|reports|dashboard)';

        // /manager  → dashboard
        add_rewrite_rule(
            '^manager/?$',
            'index.php?wcss=dashboard',
            'top'
        );

        // /manager/{section}
        add_rewrite_rule(
            '^manager/' . $sections . '/?$',
            'index.php?wcss=$matches[1]',
            'top'
        );

        // /manager/{section}/{action}   (e.g., create|edit|view|list)
        add_rewrite_rule(
            '^manager/' . $sections . '/([a-z0-9_-]+)/?$',
            'index.php?wcss=$matches[1]&wcss_action=$matches[2]',
            'top'
        );

        // /manager/{section}/{action}/{id}  (numeric id)
        add_rewrite_rule(
            '^manager/' . $sections . '/([a-z0-9_-]+)/([0-9]+)/?$',
            'index.php?wcss=$matches[1]&wcss_action=$matches[2]&wcss_id=$matches[3]',
            'top'
        );
    }

    public function add_qv( $vars ) {
        $vars[] = 'wcss'; $vars[] = 'wcss_action'; $vars[] = 'wcss_id'; return $vars;
    }

    public function enqueue_assets() {


        if ( ! get_query_var( 'wcss' ) ) {
            error_log('WCSS enqueue skipped (no query var)');
            return;
        }
        error_log('WCSS enqueue firing for view: ' . get_query_var('wcss'));
    
        wp_enqueue_style(
            'wcss-manager',
            plugins_url( 'frontend/assets/css/manager.css', WCSS_FILE ),
            [],
            '1.0.0'
        );
    
        wp_enqueue_script(
            'wcss-manager',
            plugins_url( 'frontend/assets/js/manager.js', WCSS_FILE ),
            [ 'jquery' ],   // <— important
            '1.0.0',
            true
        );
    
        if ( in_array( get_query_var('wcss'), ['products','products-create','products-edit'], true ) ) {
            wp_enqueue_media();
        }
    
        wp_localize_script( 'wcss-manager', 'WCSSM', [

            'rest'         => esc_url_raw( rest_url( 'wcss/v1/' ) ),
            'nonce'        => wp_create_nonce( 'wp_rest' ),
            'view'         => (string) get_query_var( 'wcss' ),
            'action'       => (string) get_query_var( 'wcss_action' ),
            'id'           => absint( get_query_var( 'wcss_id' ) ),
            // convenience for building links from JS
            'manager_base' => trailingslashit( home_url( '/manager' ) ),

        ]);
        

    }


    public function maybe_render() {
        $view   = get_query_var('wcss');
        $action = get_query_var('wcss_action');
        $id     = absint( get_query_var('wcss_id') );
    
        if ( ! $view ) return;
    
        // Auth
        if ( ! is_user_logged_in() || ( ! current_user_can('wcss_manage_portal') && ! current_user_can('manage_options') ) ) {
            auth_redirect(); exit;
        }
    
        status_header(200);
        nocache_headers();
        show_admin_bar(false);
        
        if ( $view === 'products' && in_array( $action, [ 'create', 'edit' ], true ) ) {
            wp_enqueue_media();
        }

        $base = WCSS_DIR . 'frontend/pages/';
        $file = $this->resolve_view( $base, $view, $action );

        $inline = 'window.WCSSM = Object.assign(window.WCSSM||{}, ' . wp_json_encode( [
            'view'   => $view,
            'action' => $action,
            'id'     => $id,
            'home'   => home_url( '/manager/' ),
        ] ) . ');';
        wp_add_inline_script( 'wcss-manager', $inline, 'after' );


    
        if ( $file && file_exists($file) ) {
    
            // Capture your page fragment
            ob_start();
            include $file;
            $content = ob_get_clean();
    
            // 🔑 Minimal shell so enqueued assets print
            ?>
            <!doctype html>
            <html <?php language_attributes(); ?>>
            <head>
                <meta charset="<?php bloginfo('charset'); ?>">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <?php wp_head(); // <-- this prints your enqueued CSS/JS ?>
            </head>
            <body <?php body_class('wcss-manager-body'); ?>>
                <?php
                    // Optional: hide theme header if needed
                    // echo '<style>#wpadminbar, header.site-header { display:none !important; }</style>';
                    echo $content;
                ?>
                <?php wp_footer(); // <-- prints footer scripts (jQuery, your manager.js if in footer) ?>
            </body>
            </html>
            <?php
            exit;
        }
    
        echo '<div class="wcssm-wrap"><h1>Manager</h1><p>Page not found.</p></div>';
        exit;
    }


    private function resolve_view( string $base, string $view, ?string $action ) {
        switch ( $view ) {
            case 'products':
                if ( $action === 'create' ) return $base . 'products-create.php';
                if ( $action === 'edit' )   return $base . 'products-edit.php';
                return $base . 'products.php';
    
            case 'stores':
                if ( $action === 'create' ) return $base . 'stores-create.php';
                if ( $action === 'edit' )   return $base . 'stores-edit.php';
                return $base . 'stores.php';

            case 'orders':
                if ( $action === 'create' ) return $base . 'order-create.php';
                if ( $action === 'view' )   return $base . 'orders-view.php';
                return $base . 'orders.php';

            default:
                return $base . 'dashboard.php';
        }
    }


}