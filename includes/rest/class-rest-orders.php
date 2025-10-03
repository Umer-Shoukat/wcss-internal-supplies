<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCSS_REST_Orders {
    const NS = 'wcss/v1';
    public function __construct() { add_action( 'rest_api_init', [ $this, 'routes' ] ); }
    public function can_manage( $request = null ): bool {
        return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
    }

    public function routes() {
        register_rest_route( self::NS, '/orders', [
            'methods' => 'GET', 'permission_callback' => [ $this, 'can_manage' ],
            'callback' => [ $this, 'orders_list' ],
            'args' => [
                'status'    => ['sanitize_callback'=>'sanitize_text_field'],
                'date_from' => ['sanitize_callback'=>'sanitize_text_field'],
                'date_to'   => ['sanitize_callback'=>'sanitize_text_field'],
                'page'      => ['validate_callback'=>'is_numeric'],
                'per_page'  => ['validate_callback'=>'is_numeric'],
            ],
        ]);

        register_rest_route( self::NS, '/orders/(?P<id>\d+)', [
            'methods'=>'GET', 'permission_callback'=>[ $this,'can_manage' ],
            'callback'=>[ $this,'orders_read' ],
        ]);

        register_rest_route( self::NS, '/orders/(?P<id>\d+)/status', [
            'methods'=>'POST', 'permission_callback'=>[ $this,'can_manage' ],
            'callback'=>[ $this,'orders_update_status' ],
            'args'=>['status'=>['required'=>true,'sanitize_callback'=>'sanitize_text_field'],
                     'note'=>['required'=>false,'sanitize_callback'=>'sanitize_textarea_field']],
        ]);

        register_rest_route( self::NS, '/orders/(?P<id>\d+)/note', [
            'methods'=>'POST', 'permission_callback'=>[ $this,'can_manage' ],
            'callback'=>[ $this,'orders_add_note' ],
            'args'=>['note'=>['required'=>true,'sanitize_callback'=>'sanitize_textarea_field']],
        ]);

        // Safe edit: limited fields only (no line items here)
        register_rest_route( self::NS, '/orders/(?P<id>\d+)', [
            'methods'=>'PATCH', 'permission_callback'=>[ $this,'can_manage' ],
            'callback'=>[ $this,'orders_patch' ],
        ]);

        // registring route for debugging... 
        
        register_rest_route( self::NS, '/whoami', [
            'methods'  => 'GET',
            'permission_callback' => '__return_true', // public for debugging only
            'callback' => function () {
                $u = wp_get_current_user();
                return [
                    'logged_in' => is_user_logged_in(),
                    'user_id'   => $u ? $u->ID : 0,
                    'caps'      => array_keys( $u ? (array) $u->allcaps : [] ),
                    'has_wcss'  => current_user_can( 'wcss_manage_portal' ),
                ];
            },
          ]);


    }

    /* ------------ Handlers ------------ */
/*
    public function orders_list( WP_REST_Request $req ) {
        $page = max(1, (int) $req->get_param('page') ?: 1);
        $per  = min(100, max(1, (int) $req->get_param('per_page') ?: 25));
        $args = [
            'type'    => 'shop_order',
            'limit'   => $per,
            'page'    => $page,
            'return'  => 'objects',
            'orderby' => 'date',
            'order'   => 'DESC',
        ];


        if ( $s = $req->get_param('status') ) {
            $args['status'] = array_map( fn($x)=> ltrim($x,'wc-'), array_map('trim', explode(',', $s)) );
        }
        $from = $req->get_param('date_from'); $to = $req->get_param('date_to');
        if ( $from || $to ) {
            $from = $from ? $from.' 00:00:00' : '1970-01-01 00:00:00';
            $to   = $to   ? $to  .' 23:59:59' : current_time('mysql');
            $args['date_created'] = $from . '...' . $to;
        }
        $orders = wc_get_orders( $args );
        return rest_ensure_response([
            'items' => array_map( [ $this, 'dto_order' ], $orders ),
        ]);
    }
*/

    public function orders_list( WP_REST_Request $req ) {
        $page = max(1, (int) $req->get_param('page') ?: 1);
        $per  = min(100, max(1, (int) $req->get_param('per_page') ?: 25));
    
        $args = [
            'type'    => 'shop_order',
            'limit'   => $per,
            'page'    => $page,
            'return'  => 'objects',
            'orderby' => 'date',
            'order'   => 'DESC',
        ];
    
        // Validate requested statuses against registered ones
        $requested = [];
        if ( $s = $req->get_param('status') ) {
            $requested = array_filter( array_map( 'trim', explode( ',', (string) $s ) ) );
        }
        if ( $requested ) {
            // Registered statuses like "wc-pending" → we convert to "pending"
            $valid_map = array_keys( wc_get_order_statuses() );            // ['wc-pending','wc-processing',...]
            $valid     = array_map( fn($k) => ltrim( $k, 'wc-' ), $valid_map ); // ['pending','processing',...]
    
            $filtered = array_values( array_intersect( $requested, $valid ) );
            if ( empty( $filtered ) ) {
                // If nothing matches, avoid fatal — default to 'pending' (or remove to show all)
                $filtered = [ 'pending' ];
            }
            $args['status'] = $filtered;
        }

    
        // (date filter unchanged)
        $from = $req->get_param('date_from'); $to = $req->get_param('date_to');
        if ( $from || $to ) {
            $from = $from ? $from.' 00:00:00' : '1970-01-01 00:00:00';
            $to   = $to   ? $to  .' 23:59:59' : current_time('mysql');
            $args['date_created'] = $from . '...' . $to;
        }
    
        $log  = wc_get_logger();

        // try {
        //     $orders = wc_get_orders( $args );
        // } catch ( Throwable $e ) {
        //     // ✅ Return a readable REST error instead of a 500 white-screen
        //     return new WP_Error( 'wcss_orders_query_failed', $e->getMessage(), [ 'status' => 500 ] );
        // }
    
        try {
            $orders = wc_get_orders( $args );
        } catch ( Throwable $e ) {
            $log->critical( 'WCSS orders_list query failed', [
                'source' => 'wcss',
                'error'  => $e->getMessage(),
                'args'   => $args,
                'trace'  => $e->getTraceAsString(),
            ]);
            return new WP_Error( 'wcss_orders_query_failed', 'Orders query failed.', [ 'status' => 500 ] );
        }
    
        try {
            $items = array_map( [ $this, 'dto_order' ], $orders );
            return rest_ensure_response( [ 'items' => $items ] );
        } catch ( Throwable $e ) {
            $log->critical( 'WCSS orders_list map failed', [
                'source' => 'wcss',
                'error'  => $e->getMessage(),
                'trace'  => $e->getTraceAsString(),
            ]);
            return new WP_Error( 'wcss_orders_map_failed', 'Orders mapping failed.', [ 'status' => 500 ] );
        }

        // return rest_ensure_response([
        //     'items' => array_map( [ $this, 'dto_order' ], $orders ),
        // ]);
    }


    public function orders_read( WP_REST_Request $req ) {
        $o = wc_get_order( (int) $req['id'] );
        if ( ! $o ) return new WP_Error('not_found','Order not found',[ 'status'=>404 ]);
        $dto = $this->dto_order( $o );
        $dto['billing']  = $this->dto_addr( $o->get_address('billing') );
        $dto['shipping'] = $this->dto_addr( $o->get_address('shipping') );
        $dto['items']    = [];
        foreach ( $o->get_items('line_item') as $it ) {
            $dto['items'][] = [
                'name' => $it->get_name(),
                'qty'  => $it->get_quantity(),
                'total'=> wc_price( $it->get_total() ),
            ];
        }
        $dto['timeline'] = WCSS_Order_Events::for_order( $o->get_id(), 50 );
        return rest_ensure_response( $dto );
    }

    public function orders_update_status( WP_REST_Request $req ) {
        $o = wc_get_order( (int) $req['id'] );
        if ( ! $o ) return new WP_Error('not_found','Order not found',[ 'status'=>404 ]);
        $status = sanitize_text_field( $req['status'] );
        $note   = sanitize_textarea_field( (string) $req->get_param('note') );
        $allowed = [ 'pending','awaiting-approval','approved','rejected','processing','completed','cancelled','on-hold' ];
        if ( ! in_array( $status, $allowed, true ) ) {
            return new WP_Error('bad_status','Status not allowed',[ 'status'=>400 ]);
        }
        $o->update_status( $status, $note ? $note : __( 'Status updated (Manager)', 'wcss' ), true );
        WCSS_Order_Events::log( $o->get_id(), 'status_change', [ 'new'=>$status, 'note'=>$note ] );
        return rest_ensure_response([ 'ok'=>true ]);
    }

    public function orders_add_note( WP_REST_Request $req ) {
        $o = wc_get_order( (int) $req['id'] );
        if ( ! $o ) return new WP_Error('not_found','Order not found',[ 'status'=>404 ]);
        $note = sanitize_textarea_field( $req['note'] );
        $o->add_order_note( $note, false, true );
        WCSS_Order_Events::log( $o->get_id(), 'note', [ 'note'=>$note ] );
        return rest_ensure_response([ 'ok'=>true ]);
    }

    // limited PATCH; allow only safe fields (addresses + meta we define)
    public function orders_patch( WP_REST_Request $req ) {
        $o = wc_get_order( (int) $req['id'] );
        if ( ! $o ) return new WP_Error('not_found','Order not found',[ 'status'=>404 ]);

        $data = $req->get_json_params() ?: [];
        $changed = false;

        if ( ! empty( $data['billing'] ) && is_array( $data['billing'] ) ) {
            $addr = $this->sanitize_addr( $data['billing'] );
            $o->set_address( $addr, 'billing' );
            $changed = true;
        }
        if ( ! empty( $data['shipping'] ) && is_array( $data['shipping'] ) ) {
            $addr = $this->sanitize_addr( $data['shipping'] );
            $o->set_address( $addr, 'shipping' );
            $changed = true;
        }
        if ( isset( $data['expected_delivery_date'] ) ) {
            $edd = sanitize_text_field( $data['expected_delivery_date'] );
            $o->update_meta_data( '_wcss_expected_delivery_date', $edd );
            $changed = true;
        }
        if ( isset( $data['internal_ref'] ) ) {
            $ref = sanitize_text_field( $data['internal_ref'] );
            $o->update_meta_data( '_wcss_internal_ref', $ref );
            $changed = true;
        }

        if ( $changed ) {
            $o->save();
            WCSS_Order_Events::log( $o->get_id(), 'edit', [ 'fields'=> array_keys( $data ) ] );
        }
        return rest_ensure_response([ 'ok'=>true ]);
    }

    /* ------------ DTO & helpers ------------ */

    private function dto_order( WC_Order $o ): array {
        return [
            'id'       => $o->get_id(),
            'number'   => $o->get_order_number(),
            'date'     => $o->get_date_created() ? $o->get_date_created()->date_i18n('Y-m-d H:i') : '',
            'status'   => wc_get_order_status_name( $o->get_status() ),
            'status_slug' => $o->get_status(),
            'total'    => $o->get_formatted_order_total(),
            'customer' => $o->get_billing_company() ?: $o->get_billing_email(),
            'edd'      => $o->get_meta('_wcss_expected_delivery_date'),
            'ref'      => $o->get_meta('_wcss_internal_ref'),
        ];
    }
    private function dto_addr( array $a ): array {
        return [
            'first_name'=>$a['first_name'] ?? '', 'last_name'=>$a['last_name'] ?? '',
            'company'=>$a['company'] ?? '', 'address_1'=>$a['address_1'] ?? '',
            'address_2'=>$a['address_2'] ?? '', 'city'=>$a['city'] ?? '',
            'state'=>$a['state'] ?? '', 'postcode'=>$a['postcode'] ?? '',
            'country'=>$a['country'] ?? '', 'email'=>$a['email'] ?? '', 'phone'=>$a['phone'] ?? '',
        ];
    }
    private function sanitize_addr( array $a ): array {
        $f = [];
        foreach ( ['first_name','last_name','company','address_1','address_2','city','state','postcode','country','email','phone'] as $k ) {
            if ( isset( $a[$k] ) ) { $f[$k] = is_email( $a[$k] ) && $k==='email' ? sanitize_email($a[$k]) : sanitize_text_field($a[$k]); }
        }
        return $f;
    }
}