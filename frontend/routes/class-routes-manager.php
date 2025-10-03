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
        // Manager dashboard home → /manager
        add_rewrite_rule(
            '^manager/?$',
            'index.php?wcss=dashboard',
            'top'
        );
    
        // Manager section pages → /manager/products , /manager/stores etc.
        add_rewrite_rule(
            '^manager/([^/]+)/?$',
            'index.php?wcss=$matches[1]',
            'top'
        );
    
        // Manager action pages → /manager/products/edit , /manager/stores/create
        add_rewrite_rule(
            '^manager/([^/]+)/([^/]+)/?$',
            'index.php?wcss=$matches[1]&wcss_action=$matches[2]',
            'top'
        );
    
        // Manager item pages → /manager/products/edit/123
        add_rewrite_rule(
            '^manager/([^/]+)/([^/]+)/([0-9]+)/?$',
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
            'rest'  => esc_url_raw( rest_url( 'wcss/v1/' ) ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
            'home'  => home_url('/manager/'),
            'view'  => get_query_var('wcss'),
            'action'=> get_query_var('wcss_action'),
            'id'    => absint( get_query_var('wcss_id') ),
        ]);

    }

    // public function maybe_render() {
    //     $view   = get_query_var( 'wcssm' );
    //     $action = get_query_var( 'wcssm_action' );
    //     $id     = absint( get_query_var( 'wcssm_id' ) );

    //     if ( ! $view ) return;

    //     // Only Shop Managers (custom cap) or Admins
    //     if ( ! is_user_logged_in() || ( ! current_user_can( 'wcss_manage_portal' ) && ! current_user_can( 'manage_options' ) ) ) {
    //         auth_redirect();
    //         exit;
    //     }

    //     status_header( 200 );
    //     nocache_headers();
    //     show_admin_bar( false );

    //     $base = WCSS_DIR . 'frontend/pages/';
    //     $file = $this->resolve_view( $base, $view, $action );

    //     if ( $file && file_exists( $file ) ) {
    //         include $file;
    //         exit;
    //     }

    //     // Fallback to legacy shell if present
    //     $legacy = WCSS_DIR . 'frontend/views/manager-shell.php';
    //     if ( file_exists( $legacy ) ) {
    //         include $legacy;
    //         exit;
    //     }

    //     echo '<h1>Manager</h1><p>Page not found.</p>';
    //     exit;
    // }

    // public function maybe_render() {
    //     $view   = get_query_var('wcss');
    //     $action = get_query_var('wcss_action');
    //     $id     = absint( get_query_var('wcss_id') );
    //     if ( ! $view ) return;
    
    //     // auth
    //     if ( ! is_user_logged_in() || ( ! current_user_can('wcss_manage_portal') && ! current_user_can('manage_options') ) ) {
    //         auth_redirect(); exit;
    //     }
    
    //     status_header(200); nocache_headers(); show_admin_bar(false);
    
    //     $base = WCSS_DIR . 'frontend/pages/';
    //     $file = $this->resolve_view( $base, $view, $action );
    //     if ( $file && file_exists($file) ) { include $file; exit; }
    
    //     echo '<div class="wcssm-wrap"><h1>Manager</h1><p>Page not found.</p></div>'; exit;
    // }

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
    
        $base = WCSS_DIR . 'frontend/pages/';
        $file = $this->resolve_view( $base, $view, $action );
    
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

    
    // private function resolve_view( string $base, string $view, string $action = '' ) : ?string {
    //     // Specific action file: e.g., products-create.php
    //     if ( $action ) {
    //         $candidate = $base . sanitize_file_name( $view . '-' . $action ) . '.php';
    //         if ( file_exists( $candidate ) ) return $candidate;
    //     }
    //     // Generic view: e.g., products.php, orders.php, stores.php
    //     $candidate = $base . sanitize_file_name( $view ) . '.php';
    //     if ( file_exists( $candidate ) ) return $candidate;

    //     // Dashboard default
    //     if ( $view === 'dashboard' ) {
    //         $candidate = $base . 'dashboard.php';
    //         if ( file_exists( $candidate ) ) return $candidate;
    //     }
    //     return null;
    // }


    private function resolve_view( $base, $view, $action='' ) {
        if ( $action ) {
            $f = $base . sanitize_file_name($view.'-'.$action).'.php';
            if ( file_exists($f) ) return $f;
        }
        $f = $base . sanitize_file_name($view).'.php';
        if ( file_exists($f) ) return $f;
        if ( $view === 'dashboard' && file_exists($base.'dashboard.php') ) return $base.'dashboard.php';
        return null;
    }


}