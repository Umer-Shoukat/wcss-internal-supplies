<?php
if ( ! defined('ABSPATH') ) exit;

class WCSS_Access_Guard {
    public function __construct() {
        add_action( 'template_redirect', [ $this, 'restrict_frontend_for_portal_users' ], 1 );
    }

    private function is_portal_user(): bool {
        return current_user_can('wcss_manage_portal') || current_user_can('store_employee');
    }

    private function is_allowed_request(): bool {
        // Allow REST, AJAX, login/logout, admin, and the manager portal itself
        if ( is_admin() ) return true;
        if ( defined('DOING_AJAX') && DOING_AJAX ) return true;
        if ( defined('REST_REQUEST') && REST_REQUEST ) return true;

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $uri = strtolower( (string) $uri );

        // Allow wp-login / logout
        if ( strpos($uri, 'wp-login.php') !== false ) return true;
        if ( strpos($uri, 'action=logout') !== false ) return true;

        // Allow manager portal
        if ( preg_match('#/manager(?:/|$)#', $uri) ) return true;

        return false;
    }

    public function restrict_frontend_for_portal_users() {
        if ( ! is_user_logged_in() ) return;
        if ( ! $this->is_portal_user() ) return;
        if ( $this->is_allowed_request() ) return;

        // Hard redirect portal users to the dashboard if they try to hit public pages
        wp_safe_redirect( home_url('/manager') );
        exit;
    }
}