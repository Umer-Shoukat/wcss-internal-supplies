<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCSS_REST_Products {
    const NS = 'wcss/v1';
    public function __construct(){ add_action( 'rest_api_init', [ $this, 'routes' ] ); }
    public function can_manage( $req = null ): bool {
        return current_user_can( 'wcss_manage_portal' ) || current_user_can( 'manage_options' );
    }

    // public function routes() {
    //     register_rest_route( self::NS, '/products', [
    //         'methods'  => 'GET',
    //         'permission_callback' => [ $this, 'can_manage' ],
    //         'callback' => [ $this, 'list' ],
    //     ]);
    //     register_rest_route( self::NS, '/products/(?P<id>\d+)', [
    //         'methods'  => 'GET',
    //         'permission_callback' => [ $this, 'can_manage' ],
    //         'callback' => [ $this, 'read' ],
    //     ]);

    //     // Create
    //     register_rest_route( self::NS, '/products', [
    //         'methods'=>'POST','permission_callback'=>[ $this,'can_manage' ],
    //         'callback'=>[ $this,'create' ],
    //         'args'=>[
    //             'name'=>['required'=>true,'sanitize_callback'=>'sanitize_text_field'],
    //             'sku'=>['required'=>false,'sanitize_callback'=>'sanitize_text_field'],
    //             'price'=>['required'=>true,'sanitize_callback'=>'wc_format_decimal'],
    //             'short_description'=>['required'=>false,'sanitize_callback'=>'wp_kses_post'],
    //             'category_ids'=>['required'=>false],           // array
    //             'brand'=>['required'=>false,'sanitize_callback'=>'sanitize_text_field'],
    //             'vendor'=>['required'=>false,'sanitize_callback'=>'sanitize_text_field'],
    //             'images'=>['required'=>false],                 // array of attachment IDs
    //             'manage_stock'=>['required'=>false],
    //             'stock'=>['required'=>false],
    //             'stock_status'=>['required'=>false,'sanitize_callback'=>'sanitize_text_field'],
    //             'status'=>['required'=>false,'sanitize_callback'=>'sanitize_text_field'],
    //         ],
    //     ]);

    //     // Update
    //     register_rest_route( self::NS, '/products/(?P<id>\d+)', [
    //         'methods'=>'PUT','permission_callback'=>[ $this,'can_manage' ],
    //         'callback'=>[ $this,'update' ],
    //     ]);

    //     // Delete
    //     register_rest_route( self::NS, '/products/(?P<id>\d+)', [
    //         'methods'  => 'DELETE',
    //         'permission_callback' => [ $this, 'can_manage' ],
    //         'callback' => [ $this, 'delete' ],
    //     ]);

    //     // Meta for form: categories/brands/vendors
    //     register_rest_route( self::NS, '/products/meta', [
    //         'methods'=>'GET','permission_callback'=>[ $this,'can_manage' ],
    //         'callback'=>[ $this,'meta' ],
    //     ]);


    // }


    public function routes() {
        
        register_rest_route( self::NS, '/products', [
            'methods'  => 'GET',
            'permission_callback' => [ $this, 'can_manage' ],
            'callback' => [ $this, 'list_products' ],
            'args'     => [
                'page' => [
                    'validate_callback' => function( $value ) {
                        return $value === null || $value === '' || is_numeric( $value );
                    },
                ],
                'per_page' => [
                    'validate_callback' => function( $value ) {
                        return $value === null || $value === '' || is_numeric( $value );
                    },
                ],
                'search' => [
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    
    
        register_rest_route( self::NS, '/products/(?P<id>\d+)', [
            'methods'  => 'GET',
            'permission_callback' => [ $this, 'can_manage' ],
            'callback' => [ $this, 'read' ],
        ]);
    
        register_rest_route( self::NS, '/products', [
            'methods'  => 'POST',
            'permission_callback' => [ $this, 'can_manage' ],
            'callback' => [ $this, 'create' ],
            'args'     => [
                'name'=>['required'=>true,'sanitize_callback'=>'sanitize_text_field'],
                'sku'=>['required'=>false,'sanitize_callback'=>'sanitize_text_field'],
                'price'=>['required'=>true,'sanitize_callback'=>'wc_format_decimal'],
                'short_description'=>['required'=>false,'sanitize_callback'=>'wp_kses_post'],
                'category_ids'=>['required'=>false],      // array
                'brand'=>['required'=>false,'sanitize_callback'=>'sanitize_text_field'],
                'vendor'=>['required'=>false,'sanitize_callback'=>'sanitize_text_field'],
                'images'=>['required'=>false],            // array of attachment IDs
                'manage_stock'=>['required'=>false],
                'stock'=>['required'=>false],
                'stock_status'=>['required'=>false,'sanitize_callback'=>'sanitize_text_field'],
                'status'=>['required'=>false,'sanitize_callback'=>'sanitize_text_field'],
            ],
        ]);
    
        register_rest_route( self::NS, '/products/(?P<id>\d+)', [
            'methods'=>'PUT',
            'permission_callback'=>[ $this,'can_manage' ],
            'callback'=>[ $this,'update' ],
        ]);
    
        register_rest_route( self::NS, '/products/(?P<id>\d+)', [
            'methods'  => 'DELETE',
            'permission_callback' => [ $this, 'can_manage' ],
            'callback' => [ $this, 'delete' ],
        ]);
    
        register_rest_route( self::NS, '/products/meta', [
            'methods'=>'GET',
            'permission_callback'=>[ $this,'can_manage' ],
            'callback'=>[ $this,'meta' ],
        ]);

        // Create a product category
        register_rest_route( self::NS, '/categories', [
            'methods'  => 'POST',
            'permission_callback' => [ $this, 'can_manage' ],
            'callback' => [ $this, 'create_category' ],
            'args'     => [
                'name' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
                'parent' => [ 'required' => false, 'validate_callback' => 'is_numeric' ],
            ],
        ]);

        // Create a vendor term
        register_rest_route( self::NS, '/vendors', [
            'methods'  => 'POST',
            'permission_callback' => [ $this, 'can_manage' ],
            'callback' => [ $this, 'create_vendor' ],
            'args'     => [
                'name' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
                'vendor_ids' => ['required'=>false], // array<int>
            ],
        ]);


    }

    /* ---------- list/read (already present) ---------- */

    // adding pagination to list product s
    public function list_products( WP_REST_Request $req ) {
        $page   = max( 1, (int) ($req->get_param('page') ?: 1) );
        $per    = min( 20, max( 100, (int) ($req->get_param('per_page') ?: 20) ) );
        $search = sanitize_text_field( (string) $req->get_param('search') );
    
        // Build a safe, boring WP_Query for products
        $q_args = [
            'post_type'      => 'product',
            'post_status'    => [ 'publish', 'draft', 'pending' ],
            'orderby'        => 'date',
            'order'          => 'DESC',
            'posts_per_page' => $per,
            'paged'          => $page,
            's'              => $search,
            'no_found_rows'  => false,            // we want totals for pagination
            'fields'         => 'ids',            // fetch only IDs; faster
        ];
        // return 'testing the fucntion';
        try {
            $q = new WP_Query( $q_args );
    
            if ( is_wp_error( $q ) ) {
                wc_get_logger()->error( 'WCSS products WP_Query failed: ' . $q->get_error_message(), [ 'source' => 'wcss' ] );
                return new WP_Error( 'wcss_products_query_failed', 'Products query failed.', [ 'status' => 500 ] );
            }
    
            $ids   = (array) $q->posts;
            $items = [];
    
            foreach ( $ids as $pid ) {
                $p = wc_get_product( $pid );
                if ( ! $p instanceof WC_Product ) { continue; }
                $items[] = $this->dto( $p );
            }
    
            return rest_ensure_response([
                'items'       => $items,
                'total'       => (int) $q->found_posts,
                'total_pages' => (int) $q->max_num_pages,
                'page'        => $page,
                'per_page'    => $per,
            ]);
    
        } catch ( Throwable $e ) {
            wc_get_logger()->critical(
                'WCSS products list exception: ' . $e->getMessage(),
                [ 'source' => 'wcss', 'trace' => $e->getTraceAsString(), 'args' => $q_args ]
            );
            return new WP_Error( 'wcss_products_exception', 'Products query crashed.', [ 'status' => 500 ] );
        }
    }

    public function read( WP_REST_Request $req ) {
        $p = wc_get_product( (int) $req['id'] );
        if ( ! $p ) return new WP_Error( 'not_found', 'Product not found', [ 'status' => 404 ] );
        return rest_ensure_response( $this->dto( $p ) );
    }

    /* ---------- create/update/delete ---------- */

    public function delete( WP_REST_Request $req ) {
        $p = wc_get_product( (int) $req['id'] );
        if ( ! $p ) return new WP_Error( 'not_found', 'Product not found', [ 'status' => 404 ] );
        try {
            $p->delete( true );
            return rest_ensure_response([ 'ok' => true ]);
        } catch ( Throwable $e ) {
            return new WP_Error( 'wcss_product_delete_failed', $e->getMessage(), [ 'status' => 500 ] );
        }
    }

    /* ---------- DTO ---------- */
    // private function dto( WC_Product $p ): array {
    //     $brand_terms  = function_exists('wp_get_post_terms') ? wp_get_post_terms( $p->get_id(), 'wcss_brand',  ['fields'=>'names'] ) : [];
    //     $vendor_terms = function_exists('wp_get_post_terms') ? wp_get_post_terms( $p->get_id(), 'wcss_vendor', ['fields'=>'names'] ) : [];
    //     $cat_ids      = function_exists('wp_get_post_terms') ? wp_get_post_terms( $p->get_id(), 'product_cat', ['fields'=>'ids'] ) : [];
    
    //     $featured     = (int) $p->get_image_id();
    //     $gallery      = array_map('intval', (array) $p->get_gallery_image_ids());
    //     $images       = array_values( array_filter( array_unique( array_merge( [$featured], $gallery ) ) ) );
    
    //     return [
    //         'id'                 => $p->get_id(),
    //         'name'               => $p->get_name(),
    //         'sku'                => $p->get_sku(),
    //         'price'              => (float) $p->get_price(),
    //         'price_html'         => wc_price( (float) $p->get_price() ),
    //         'short_description'  => $p->get_short_description(),
    //         'manage_stock'       => (bool) $p->get_manage_stock(),
    //         'stock'              => $p->get_stock_quantity(),
    //         'stock_status'       => $p->get_stock_status(),
    //         'status'             => $p->get_status(),
    //         'category_ids'       => $cat_ids,
    //         'brand'              => $brand_terms ? $brand_terms[0] : '',
    //         'vendor'             => $vendor_terms ? $vendor_terms[0] : '',
    //         'images'             => $images,
    //     ];
    // }

    private function dto( WC_Product $p ): array {
        $brand_terms   = wp_get_post_terms( $p->get_id(), 'wcss_brand',  [ 'fields' => 'names' ] );
        if ( is_wp_error( $brand_terms ) )   $brand_terms = [];
    
        $vendor_terms  = wp_get_post_terms( $p->get_id(), 'wcss_vendor', [ 'fields' => 'names' ] );
        if ( is_wp_error( $vendor_terms ) )  $vendor_terms = [];
    
        $vendor_ids    = wp_get_post_terms( $p->get_id(), 'wcss_vendor', [ 'fields' => 'ids' ] );
        if ( is_wp_error( $vendor_ids ) )    $vendor_ids = [];
    
        $cat_ids       = wp_get_post_terms( $p->get_id(), 'product_cat', [ 'fields' => 'ids' ] );
        if ( is_wp_error( $cat_ids ) )       $cat_ids = [];
    
        $featured = (int) $p->get_image_id();
        $gallery  = array_map( 'intval', (array) $p->get_gallery_image_ids() );
        $images   = array_values( array_filter( array_unique( array_merge( $featured ? [ $featured ] : [], $gallery ) ) ) );
    
        return [
            'id'                => $p->get_id(),
            'name'              => $p->get_name(),
            'sku'               => $p->get_sku(),
            'price'             => (float) $p->get_price(),
            'price_html'        => wc_price( (float) $p->get_price() ),
            'short_description' => $p->get_short_description(),
            'manage_stock'      => (bool) $p->get_manage_stock(),
            'stock'             => $p->get_stock_quantity(),
            'stock_status'      => $p->get_stock_status(),
            'status'            => $p->get_status(),
            'category_ids'      => $cat_ids,
            'brand'             => $brand_terms  ? $brand_terms[0]  : '',
            'vendor'            => $vendor_terms ? $vendor_terms[0] : '',
            'vendor_ids'        => array_map('intval',$vendor_ids),
            'images'            => $images,
        ];
    }




    private function set_terms_by_name( int $post_id, string $taxonomy, ?string $name ): void {
        if ( $name === null ) return;
        $name = trim( (string) $name );
        if ( $name === '' ) {
            wp_set_object_terms( $post_id, [], $taxonomy, false );
        } else {
            wp_set_object_terms( $post_id, [ $name ], $taxonomy, false ); // auto creates if missing
        }
    }
    
    private function set_product_images( WC_Product $p, array $image_ids ): void {
        $image_ids = array_values( array_filter( array_map( 'intval', $image_ids ) ) );
        if ( empty( $image_ids ) ) {
            $p->set_image_id( 0 );
            $p->set_gallery_image_ids( [] );
            return;
        }
        $p->set_image_id( array_shift( $image_ids ) );     // featured
        $p->set_gallery_image_ids( $image_ids );           // gallery
    }

/*
    public function create( WP_REST_Request $req ) {
        try {
            $p = new WC_Product_Simple();


            // adding capability to add vendor and Categories


                $cat_ids = $data['category_ids'] ?? [];
                if ( is_array($cat_ids) ) {
                    wp_set_object_terms( 0, [], 'product_cat' ); // noop for new; WC sets later on save
                    $product->set_category_ids( array_map('intval', $cat_ids) );
                }

                // Vendors (taxonomy)
                if ( taxonomy_exists('wcss_vendor') ) {
                    if ( ! empty( $data['vendor_ids'] ) && is_array( $data['vendor_ids'] ) ) {
                        wp_set_object_terms( 0, [], 'wcss_vendor' ); // noop for new
                        // Save after product gets an ID
                        $product->_wcss_vendor_ids = array_map('intval', $data['vendor_ids']);
                    } elseif ( ! empty( $data['vendor'] ) ) {
                        $t = term_exists( $data['vendor'], 'wcss_vendor' );
                        if ( ! $t || is_wp_error($t) ) {
                            $t = wp_insert_term( $data['vendor'], 'wcss_vendor' );
                        }
                        if ( ! is_wp_error($t) ) {
                            $product->_wcss_vendor_ids = [ (int) $t['term_id'] ];
                        }
                    }
                }


    
            $p->set_name( sanitize_text_field( $req['name'] ) );
            if ( $req['sku'] ) $p->set_sku( sanitize_text_field( $req['sku'] ) );
            $p->set_regular_price( wc_format_decimal( $req['price'] ) );
    
            if ( isset($req['short_description']) ) {
                $p->set_short_description( wp_kses_post( $req['short_description'] ) );
            }
    
            // Inventory
            $manage = isset($req['manage_stock']) ? (bool)$req['manage_stock'] : false;
            $p->set_manage_stock( $manage );
            if ( $manage && isset($req['stock']) ) {
                $p->set_stock_quantity( (int)$req['stock'] );
            }
            if ( isset($req['stock_status']) ) {
                $ss = sanitize_text_field( $req['stock_status'] );
                if ( in_array($ss, ['instock','outofstock','onbackorder'], true) ) $p->set_stock_status($ss);
            }
    
            // Status
            $status = $req['status'] ? sanitize_text_field($req['status']) : 'publish';
            $p->set_status( in_array($status, ['publish','draft','pending'], true) ? $status : 'publish' );
    
            // Save first to get ID (needed for terms/images)
            $p->save();
    
            // Categories (IDs)
            if ( ! empty($req['category_ids']) && is_array($req['category_ids']) ) {
                $cat_ids = array_map('intval', $req['category_ids']);
                wp_set_object_terms( $p->get_id(), $cat_ids, 'product_cat', false );
            } else {
                wp_set_object_terms( $p->get_id(), [], 'product_cat', false );
            }
    
            // Brand / Vendor (by name)
            $this->set_terms_by_name( $p->get_id(), 'wcss_brand',  $req['brand']  ?? null );
            $this->set_terms_by_name( $p->get_id(), 'wcss_vendor', $req['vendor'] ?? null );
    
            // Images (attachment IDs)
            if ( isset($req['images']) && is_array($req['images']) ) {
                $this->set_product_images( $p, $req['images'] );
                $p->save();
            }
    
            return rest_ensure_response( $this->dto( $p ) );
        } catch ( Throwable $e ) {
            return new WP_Error( 'wcss_product_create_failed', $e->getMessage(), [ 'status' => 500 ] );
        }
    }
*/

public function create( WP_REST_Request $req ) {
    try {
        $p = new WC_Product_Simple();

        // Core fields
        $p->set_name( sanitize_text_field( $req['name'] ) );
        if ( $req['sku'] ) {
            $p->set_sku( sanitize_text_field( $req['sku'] ) );
        }
        $p->set_regular_price( wc_format_decimal( $req['price'] ) );

        if ( isset($req['short_description']) ) {
            $p->set_short_description( wp_kses_post( $req['short_description'] ) );
        }

        // Inventory
        $manage = isset($req['manage_stock']) ? (bool)$req['manage_stock'] : false;
        $p->set_manage_stock( $manage );
        if ( $manage && isset($req['stock']) ) {
            $p->set_stock_quantity( (int) $req['stock'] );
        }
        if ( isset($req['stock_status']) ) {
            $ss = sanitize_text_field( $req['stock_status'] );
            if ( in_array($ss, ['instock','outofstock','onbackorder'], true) ) {
                $p->set_stock_status($ss);
            }
        }

        // Status
        $status = $req['status'] ? sanitize_text_field($req['status']) : 'publish';
        $p->set_status( in_array($status, ['publish','draft','pending'], true) ? $status : 'publish' );

        // Save first to get ID
        $p->save();

        // Categories (IDs)
        if ( ! empty($req['category_ids']) && is_array($req['category_ids']) ) {
            $cat_ids = array_map('intval', $req['category_ids']);
            wp_set_object_terms( $p->get_id(), $cat_ids, 'product_cat', false );
        } else {
            wp_set_object_terms( $p->get_id(), [], 'product_cat', false );
        }

        // Vendors (IDs preferred; fallback to legacy 'vendor' name)
        if ( taxonomy_exists('wcss_vendor') ) {
            if ( ! empty( $req['vendor_ids'] ) && is_array( $req['vendor_ids'] ) ) {
                $vids = array_map('intval', (array) $req['vendor_ids']);
                wp_set_object_terms( $p->get_id(), $vids, 'wcss_vendor', false );
            } elseif ( ! empty( $req['vendor'] ) ) {
                $vn = sanitize_text_field( $req['vendor'] );
                $t  = term_exists( $vn, 'wcss_vendor' );
                if ( ! $t || is_wp_error($t) ) $t = wp_insert_term( $vn, 'wcss_vendor' );
                if ( ! is_wp_error($t) ) wp_set_object_terms( $p->get_id(), [ (int) $t['term_id'] ], 'wcss_vendor', false );
            } else {
                wp_set_object_terms( $p->get_id(), [], 'wcss_vendor', false );
            }
        }

        // Images (attachment IDs)
        if ( isset($req['images']) && is_array($req['images']) ) {
            $this->set_product_images( $p, $req['images'] );
            $p->save();
        }

        return rest_ensure_response( $this->dto( $p ) );
    } catch ( Throwable $e ) {
        return new WP_Error( 'wcss_product_create_failed', $e->getMessage(), [ 'status' => 500 ] );
    }
}

/*
    public function update( WP_REST_Request $req ) {
        $p = wc_get_product( (int) $req['id'] );
        if ( ! $p ) return new WP_Error( 'not_found', 'Product not found', [ 'status' => 404 ] );
    
        $body = $req->get_json_params() ?: [];
        try {
            if ( array_key_exists('name', $body) )  $p->set_name( sanitize_text_field($body['name']) );
            if ( array_key_exists('sku', $body) )   $p->set_sku( sanitize_text_field($body['sku']) );
            if ( array_key_exists('price', $body) ) $p->set_regular_price( wc_format_decimal($body['price']) );
            if ( array_key_exists('short_description', $body) ) $p->set_short_description( wp_kses_post($body['short_description']) );
    
            if ( array_key_exists('manage_stock', $body) ) $p->set_manage_stock( (bool)$body['manage_stock'] );
            if ( array_key_exists('stock', $body) )        $p->set_stock_quantity( (int)$body['stock'] );
            if ( array_key_exists('stock_status', $body) ) {
                $ss = sanitize_text_field( $body['stock_status'] );
                if ( in_array($ss, ['instock','outofstock','onbackorder'], true) ) $p->set_stock_status($ss);
            }
    
            if ( array_key_exists('status', $body) ) {
                $st = sanitize_text_field($body['status']);
                if ( in_array($st, ['publish','draft','pending'], true) ) $p->set_status($st);
            }
    
            // Save before terms/images if needed
            $p->save();
    
            if ( array_key_exists('category_ids', $body) && is_array($body['category_ids']) ) {
                $cat_ids = array_map('intval', $body['category_ids']);
                wp_set_object_terms( $p->get_id(), $cat_ids, 'product_cat', false );
            }
    
            if ( array_key_exists('brand', $body) )  $this->set_terms_by_name( $p->get_id(), 'wcss_brand',  $body['brand'] );
            if ( array_key_exists('vendor', $body) ) $this->set_terms_by_name( $p->get_id(), 'wcss_vendor', $body['vendor'] );
    
            if ( array_key_exists('images', $body) && is_array($body['images']) ) {
                $this->set_product_images( $p, $body['images'] );
                $p->save();
            }
    
            return rest_ensure_response( $this->dto( $p ) );
        } catch ( Throwable $e ) {
            return new WP_Error( 'wcss_product_update_failed', $e->getMessage(), [ 'status' => 500 ] );
        }
    }
*/

    public function update( WP_REST_Request $req ) {
        
        $p = wc_get_product( (int) $req['id'] );
        if ( ! $p ) return new WP_Error( 'not_found', 'Product not found', [ 'status' => 404 ] );

        $body = $req->get_json_params() ?: [];
        try {
            if ( array_key_exists('name', $body) )  $p->set_name( sanitize_text_field($body['name']) );
            if ( array_key_exists('sku', $body) )   $p->set_sku( sanitize_text_field($body['sku']) );
            if ( array_key_exists('price', $body) ) $p->set_regular_price( wc_format_decimal($body['price']) );
            if ( array_key_exists('short_description', $body) ) $p->set_short_description( wp_kses_post($body['short_description']) );

            if ( array_key_exists('manage_stock', $body) ) $p->set_manage_stock( (bool)$body['manage_stock'] );
            if ( array_key_exists('stock', $body) )        $p->set_stock_quantity( (int)$body['stock'] );
            if ( array_key_exists('stock_status', $body) ) {
                $ss = sanitize_text_field( $body['stock_status'] );
                if ( in_array($ss, ['instock','outofstock','onbackorder'], true) ) $p->set_stock_status($ss);
            }

            if ( array_key_exists('status', $body) ) {
                $st = sanitize_text_field($body['status']);
                if ( in_array($st, ['publish','draft','pending'], true) ) $p->set_status($st);
            }

            $p->save();

            if ( array_key_exists('category_ids', $body) && is_array($body['category_ids']) ) {
                $cat_ids = array_map('intval', $body['category_ids']);
                wp_set_object_terms( $p->get_id(), $cat_ids, 'product_cat', false );
            }

            // Vendors
            if ( taxonomy_exists('wcss_vendor') ) {
                if ( array_key_exists('vendor_ids', $body) && is_array($body['vendor_ids']) ) {
                    wp_set_object_terms( $p->get_id(), array_map('intval',$body['vendor_ids']), 'wcss_vendor', false );
                } elseif ( array_key_exists('vendor', $body) ) {
                    $name = trim((string)$body['vendor']);
                    if ( $name === '' ) {
                        wp_set_object_terms( $p->get_id(), [], 'wcss_vendor', false );
                    } else {
                        $t = term_exists( $name, 'wcss_vendor' );
                        if ( ! $t || is_wp_error($t) ) $t = wp_insert_term( $name, 'wcss_vendor' );
                        if ( ! is_wp_error($t) ) {
                            wp_set_object_terms( $p->get_id(), [ (int) $t['term_id'] ], 'wcss_vendor', false );
                        }
                    }
                }
            }

            if ( array_key_exists('images', $body) && is_array($body['images']) ) {
                $this->set_product_images( $p, $body['images'] );
                $p->save();
            }

            return rest_ensure_response( $this->dto( $p ) );
        } catch ( Throwable $e ) {
            return new WP_Error( 'wcss_product_update_failed', $e->getMessage(), [ 'status' => 500 ] );
        }
    }

    public function meta( WP_REST_Request $req ) {
        $out = [ 'categories' => [], 'brands' => [], 'vendors' => [] ];
    
        // Categories
        $cats = get_terms( [
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        ] );
        if ( ! is_wp_error( $cats ) ) {
            foreach ( $cats as $t ) {
                $out['categories'][] = [ 'id' => (int) $t->term_id, 'name' => $t->name, 'parent' => (int) $t->parent ];
            }
        }
    
        // Brand
        $brands = taxonomy_exists('wcss_brand') ? get_terms( [ 'taxonomy' => 'wcss_brand', 'hide_empty' => false ] ) : [];
        if ( ! is_wp_error( $brands ) ) {
            foreach ( $brands as $t ) {
                $out['brands'][] = [ 'id' => (int) $t->term_id, 'name' => $t->name ];
            }
        }
    
        // Vendor
        $vendors = taxonomy_exists('wcss_vendor') ? get_terms( [ 'taxonomy' => 'wcss_vendor', 'hide_empty' => false ] ) : [];
        if ( ! is_wp_error( $vendors ) ) {
            foreach ( $vendors as $t ) {
                $out['vendors'][] = [ 'id' => (int) $t->term_id, 'name' => $t->name ];
            }
        }
    
        return rest_ensure_response( $out );
    }

    public function enqueue_assets() {
        // existing css/js enqueues…
        wp_enqueue_media(); // <-- needed for wp.media image picker
    
        wp_localize_script( 'wcss-manager', 'WCSSM', [
            'rest'  => esc_url_raw( rest_url( 'wcss/v1/' ) ),
            'nonce' => wp_create_nonce( 'wp_rest' ),
        ]);
    }


    public function create_category( WP_REST_Request $req ) {
        $name   = trim( (string) $req->get_param('name') );
        $parent = (int) $req->get_param('parent');
    
        if ( $name === '' ) return new WP_Error('wcss_bad_name', 'Category name required', [ 'status'=>400 ]);
    
        // If exists, return it
        $existing = term_exists( $name, 'product_cat', $parent ?: 0 );
        if ( $existing && ! is_wp_error($existing) ) {
            $t = get_term( (int) $existing['term_id'] );
            return rest_ensure_response([ 'id'=>(int)$t->term_id, 'name'=>$t->name, 'parent'=>(int)$t->parent ]);
        }
    
        $res = wp_insert_term( $name, 'product_cat', [ 'parent' => $parent ?: 0 ] );
        if ( is_wp_error( $res ) ) return $res;
    
        $t = get_term( (int) $res['term_id'] );
        return rest_ensure_response([ 'id'=>(int)$t->term_id, 'name'=>$t->name, 'parent'=>(int)$t->parent ]);
    }
    
    public function create_vendor( WP_REST_Request $req ) {
        $name = trim( (string) $req->get_param('name') );
        if ( $name === '' ) return new WP_Error('wcss_bad_name', 'Vendor name required', [ 'status'=>400 ]);
    
        if ( ! taxonomy_exists('wcss_vendor') ) {
            return new WP_Error('wcss_vendor_missing', 'Vendor taxonomy not registered', [ 'status'=>500 ]);
        }
    
        // If exists, return it
        $existing = term_exists( $name, 'wcss_vendor' );
        if ( $existing && ! is_wp_error($existing) ) {
            $t = get_term( (int) $existing['term_id'] );
            return rest_ensure_response([ 'id'=>(int)$t->term_id, 'name'=>$t->name ]);
        }
    
        $res = wp_insert_term( $name, 'wcss_vendor' );
        if ( is_wp_error( $res ) ) return $res;
    
        $t = get_term( (int) $res['term_id'] );
        return rest_ensure_response([ 'id'=>(int)$t->term_id, 'name'=>$t->name ]);
    }


}