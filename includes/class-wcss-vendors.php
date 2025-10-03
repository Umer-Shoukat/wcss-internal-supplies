<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCSS_Vendors {
    public function __construct() {
        add_action( 'init', [ $this, 'register_taxonomy' ] );
    }
    public function register_taxonomy() {
        $labels = [
            'name'          => _x('Vendors', 'taxonomy general name', 'wcss'),
            'singular_name' => _x('Vendor', 'taxonomy singular name', 'wcss'),
            'search_items'  => __('Search Vendors','wcss'),
            'all_items'     => __('All Vendors','wcss'),
            'edit_item'     => __('Edit Vendor','wcss'),
            'update_item'   => __('Update Vendor','wcss'),
            'add_new_item'  => __('Add New Vendor','wcss'),
            'menu_name'     => __('Vendors','wcss'),
        ];
        register_taxonomy( 'wcss_vendor', [ 'product' ], [
            'hierarchical'      => false,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => [ 'slug' => 'vendor' ],
        ] );
    }
}