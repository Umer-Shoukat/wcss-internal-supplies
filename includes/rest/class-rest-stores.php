<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCSS_REST_Stores {
    const NS = 'wcss/v1';
    public function __construct(){ add_action( 'rest_api_init', [ $this, 'routes' ] ); }
    public function can_manage( $r=null ): bool {
        return current_user_can( 'wcss_manage_portal' ) || current_user_can( 'manage_options' );
    }

    public function routes() {

        register_rest_route( self::NS, '/users', [
            'methods'  => 'GET',
            'permission_callback' => [ $this, 'can_manage' ],
            'callback' => [ $this, 'list_users' ],
        ]);


        register_rest_route( self::NS, '/stores', [
            'methods'  => 'GET',
            'permission_callback' => [ $this, 'can_manage' ],
            'callback' => [ $this, 'list' ],
            'args'     => [
                'page' => [
                    'required' => false,
                    'validate_callback' => function( $value ) { return is_numeric( $value ) && (int)$value >= 1; },
                ],
                'per_page' => [
                    'required' => false,
                    'validate_callback' => function( $value ) { return is_numeric( $value ) && (int)$value >= 1 && (int)$value <= 100; },
                ],
                'search' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route( self::NS, '/stores/(?P<id>\d+)', [
            'methods'=>'GET','permission_callback'=>[ $this,'can_manage' ],'callback'=>[ $this,'read' ],
        ]);

        //  Create
        register_rest_route( self::NS, '/stores', [
            'methods'=>'POST','permission_callback'=>[ $this,'can_manage' ],'callback'=>[ $this,'create' ],
            'args'=>[
                'name'  => ['required'=>true,  'sanitize_callback'=>'sanitize_text_field'],
                'code'  => ['required'=>true, 'sanitize_callback'=>'sanitize_text_field'],
                'city'  => ['required'=>false, 'sanitize_callback'=>'sanitize_text_field'],
                'state' => ['required'=>false, 'sanitize_callback'=>'sanitize_text_field'],
                'quota' => [
                    'required'=>true,
                    'validate_callback'=> function( $value ) { return $value === '' || is_numeric( $value ); },
                ],
                'budget'=> ['required'=>true, 'sanitize_callback'=>'wc_format_decimal'],
                'user_id' => [
                    'required' => true,
                    'validate_callback' => [ $this, 'validate_user_id_required' ],
                ],
            
            ],

        ]);

        // Update
        register_rest_route( self::NS, '/stores/(?P<id>\d+)', [
            'methods'=>'PUT','permission_callback'=>[ $this,'can_manage' ],'callback'=>[ $this,'update' ],
        ]);

        //  Delete
        register_rest_route( self::NS, '/stores/(?P<id>\d+)', [
            'methods'=>'DELETE','permission_callback'=>[ $this,'can_manage' ],'callback'=>[ $this,'delete' ],
        ]);

        // (Ledger route you added can stay here OR be in class-rest-ledger.php)
        register_rest_route( self::NS, '/stores/(?P<id>\d+)/ledger', [
            'methods'=>'GET','permission_callback'=>[ $this,'can_manage' ],'callback'=>[ $this,'store_ledger' ],
            'args'=>['month'=>['sanitize_callback'=>'sanitize_text_field']],
        ]);
    }

    public function list( WP_REST_Request $req ) {
        $page   = max( 1, (int) ( $req->get_param('page') ?: 1 ) );
        $per    = min( 100, max( 1, (int) ( $req->get_param('per_page') ?: 20 ) ) );
        $search = sanitize_text_field( (string) $req->get_param('search') );
    
        $args = [
            'post_type'      => 'store_location',
            'post_status'    => 'publish',
            'orderby'        => 'date',
            'order'          => 'DESC',
            'posts_per_page' => $per,
            'paged'          => $page,
            's'              => $search,
            'no_found_rows'  => false, // we want totals
            'fields'         => 'all',
        ];
    
        $q = new WP_Query( $args );
    
        if ( is_wp_error( $q ) ) {
            return new WP_Error( 'wcss_stores_query_failed', $q->get_error_message(), [ 'status' => 500 ] );
        }
    
        $items = array_map( [ $this, 'dto' ], (array) $q->posts );
    
        return rest_ensure_response([
            'items'       => $items,
            'total'       => (int) $q->found_posts,
            'total_pages' => (int) $q->max_num_pages,
            'page'        => $page,
            'per_page'    => $per,
        ]);
    }

    public function read( WP_REST_Request $req ) {
        $p = get_post( (int) $req['id'] );
        if ( ! $p || $p->post_type !== 'store_location' ) return new WP_Error('not_found','Store not found',[ 'status'=>404 ]);
        return rest_ensure_response( $this->dto( $p ) );
    }
/*
    public function create( WP_REST_Request $req ) {


        $post_id = wp_insert_post([
            'post_type'  => 'store_location',
            'post_title' => sanitize_text_field( $req['name'] ),
            'post_status'=> 'publish',
        ], true );
        if ( is_wp_error($post_id) ) return $post_id;

        update_post_meta( $post_id, '_store_code',   sanitize_text_field( (string)$req['code'] ) );
        update_post_meta( $post_id, '_store_city',   sanitize_text_field( (string)$req['city'] ) );
        update_post_meta( $post_id, '_store_state',  sanitize_text_field( (string)$req['state'] ) );
        if ( isset($req['quota']) )  update_post_meta( $post_id, '_store_quota',  (int)$req['quota'] );
        if ( isset($req['budget']) ) update_post_meta( $post_id, '_store_budget', wc_format_decimal($req['budget']) );

        return rest_ensure_response( $this->dto( get_post($post_id) ) );
    }
*/

    public function create( WP_REST_Request $req ) {
        // ——— Sanitize + trim
        $name  = trim( (string) $req->get_param('name') );
        $code  = trim( (string) $req->get_param('code') );
        $city  = trim( (string) $req->get_param('city') );
        $state = trim( (string) $req->get_param('state') );

        // ——— Required fields
        if ($name === '' || $code === '') {
            return new WP_Error(
                'wcss_store_bad_input',
                'Name and Code are required.',
                [ 'status' => 400 ]
            );
        }

        // ——— Optional: also require City/State (uncomment if you want them mandatory)
        // if ($city === '' || $state === '') {
        //     return new WP_Error(
        //         'wcss_store_bad_input',
        //         'City and State/Province are required.',
        //         [ 'status' => 400 ]
        //     );
        // }

        // ——— Validate numeric inputs when provided
        $quota  = $req->get_param('quota');
        $budget = $req->get_param('budget');

        if ($quota !== null && $quota !== '' && (!is_numeric($quota) || (int) $quota < 0)) {
            return new WP_Error('wcss_store_bad_quota', 'Quota must be a non-negative integer.', [ 'status' => 400 ]);
        }
        if ($budget !== null && $budget !== '' && (!is_numeric($budget) || (float) $budget < 0)) {
            return new WP_Error('wcss_store_bad_budget', 'Budget must be a non-negative number.', [ 'status' => 400 ]);
        }

        // ——— Ensure store code is unique
        $exists = get_posts([
            'post_type'      => 'store_location',
            'post_status'    => 'any',
            'meta_key'       => '_store_code',
            'meta_value'     => $code,
            'fields'         => 'ids',
            'posts_per_page' => 1,
            'no_found_rows'  => true,
            'suppress_filters' => true,
        ]);
        if ( ! empty($exists) ) {
            return new WP_Error(
                'wcss_store_code_exists',
                'A store with this code already exists.',
                [ 'status' => 409 ]
            );
        }

        // ——— Create
        $post_id = wp_insert_post([
            'post_type'   => 'store_location',
            'post_title'  => sanitize_text_field( $name ),
            'post_status' => 'publish',
        ], true );
        if ( is_wp_error($post_id) ) return $post_id;

        update_post_meta( $post_id, '_store_code',  sanitize_text_field( $code ) );
        update_post_meta( $post_id, '_store_city',  sanitize_text_field( $city ) );
        update_post_meta( $post_id, '_store_state', sanitize_text_field( $state ) );

        if ($quota !== null && $quota !== '') {
            update_post_meta( $post_id, '_store_quota', (int) $quota );
        }
        if ($budget !== null && $budget !== '') {
            update_post_meta( $post_id, '_store_budget', wc_format_decimal( $budget ) );
        }

        // added required user allocation to create store.. 

            $uid = (int) $req->get_param('user_id');
            if ( $uid <= 0 ) {
                return new WP_Error(
                    'wcss_store_user_required',
                    'A Store Employee must be assigned.',
                    [ 'status' => 400 ]
                );
            }
        
            // 👇 NEW: Check if this user already belongs to another store
            $existing_store = (int) get_user_meta( $uid, '_wcss_store_id', true );
            if ( $existing_store && get_post_status( $existing_store ) ) {
                $store_title = get_the_title( $existing_store ) ?: "#{$existing_store}";
                return new WP_Error(
                    'wcss_user_already_assigned',
                    "This user is already assigned to store {$store_title}. Each user can belong to only one store.",
                    [ 'status' => 400 ]
                );
            }
        
            // If valid, assign the user to this store
            update_user_meta( $uid, '_wcss_store_id', $post_id );




        return rest_ensure_response( $this->dto( get_post($post_id) ) );
    }

/*
    public function update( WP_REST_Request $req ) {
        $id = (int) $req['id'];
        $p = get_post( $id );
        if ( ! $p || $p->post_type !== 'store_location' ) return new WP_Error('not_found','Store not found',[ 'status'=>404 ]);

        $body = $req->get_json_params() ?: [];
        if ( isset($body['name']) ) {
            wp_update_post([ 'ID'=>$id, 'post_title'=>sanitize_text_field($body['name']) ]);
        }
        if ( array_key_exists('code', $body) )   update_post_meta( $id, '_store_code',  sanitize_text_field((string)$body['code']) );
        if ( array_key_exists('city', $body) )   update_post_meta( $id, '_store_city',  sanitize_text_field((string)$body['city']) );
        if ( array_key_exists('state', $body) )  update_post_meta( $id, '_store_state', sanitize_text_field((string)$body['state']) );
        if ( array_key_exists('quota', $body) )  update_post_meta( $id, '_store_quota', (int)$body['quota'] );
        if ( array_key_exists('budget', $body) ) update_post_meta( $id, '_store_budget', wc_format_decimal($body['budget']) );


        // added required user allocation to create store.. 

        $uid = (int) $req->get_param('user_id');
        if ( $uid <= 0 ) {
            return new WP_Error(
                'wcss_store_user_required',
                'A Store Employee must be assigned.',
                [ 'status' => 400 ]
            );
        }
    
        // 👇 NEW: Check if this user already belongs to another store
        $existing_store = (int) get_user_meta( $uid, '_wcss_store_id', true );
        if ( $existing_store && get_post_status( $existing_store ) ) {
            $store_title = get_the_title( $existing_store ) ?: "#{$existing_store}";
            return new WP_Error(
                'wcss_user_already_assigned',
                "This user is already assigned to store {$store_title}. Each user can belong to only one store.",
                [ 'status' => 400 ]
            );
        }
    
        // If valid, assign the user to this store
        update_user_meta( $uid, '_wcss_store_id', $post_id );




        return rest_ensure_response( $this->dto( get_post($id) ) );
    }
*/

    public function update( WP_REST_Request $req ) {
        $id = (int) $req['id'];
        $p  = get_post( $id );
        if ( ! $p || $p->post_type !== 'store_location' ) {
            return new WP_Error( 'not_found', 'Store not found', [ 'status' => 404 ] );
        }

        $body = $req->get_json_params() ?: [];

        // ---------- Required fields (same spirit as create) ----------
        $name   = isset($body['name'])    ? sanitize_text_field($body['name'])   : '';
        $user_id = isset($body['user_id']) ? (int) $body['user_id'] : 0;

        if ( $name === '' ) {
            return new WP_Error( 'wcss_store_name_required', 'Store name is required.', [ 'status' => 400 ] );
        }
        if ( $user_id <= 0 ) {
            return new WP_Error( 'wcss_store_user_required', 'A Store Employee must be assigned.', [ 'status' => 400 ] );
        }

        // ---------- Update core fields ----------
        wp_update_post([ 'ID' => $id, 'post_title' => $name ]);

        if ( array_key_exists('code', $body) )   update_post_meta( $id, '_store_code',   sanitize_text_field((string) $body['code']) );
        if ( array_key_exists('city', $body) )   update_post_meta( $id, '_store_city',   sanitize_text_field((string) $body['city']) );
        if ( array_key_exists('state', $body) )  update_post_meta( $id, '_store_state',  sanitize_text_field((string) $body['state']) );
        if ( array_key_exists('quota', $body) )  update_post_meta( $id, '_store_quota',  (int) $body['quota'] );
        if ( array_key_exists('budget', $body) ) update_post_meta( $id, '_store_budget', wc_format_decimal( $body['budget'] ) );

        // ---------- Enforce one-store-per-user ----------
        // If this user already has a store (different from current), block.
        $existing_store = (int) get_user_meta( $user_id, '_wcss_store_id', true );
        if ( $existing_store && $existing_store !== $id && get_post_status( $existing_store ) ) {
            $store_title = get_the_title( $existing_store ) ?: "#{$existing_store}";
            return new WP_Error(
                'wcss_user_already_assigned',
                "This user is already assigned to store {$store_title}. Each user can belong to only one store.",
                [ 'status' => 400 ]
            );
        }

        // If some *other* user is currently assigned to *this* store, unassign them.
        $prev_users = get_users([
            'meta_key'   => '_wcss_store_id',
            'meta_value' => $id,
            'fields'     => [ 'ID' ],
            'number'     => 2, // defensive: we only expect 0 or 1
        ]);
        foreach ( $prev_users as $u ) {
            if ( (int) $u->ID !== $user_id ) {
                delete_user_meta( $u->ID, '_wcss_store_id' );
            }
        }

        // Assign the selected user to this store
        update_user_meta( $user_id, '_wcss_store_id', $id );

        return rest_ensure_response( $this->dto( get_post( $id ) ) );
    }

    public function delete( WP_REST_Request $req ) {
        $id = (int) $req['id'];
        $p  = get_post( $id );
        if ( ! $p || $p->post_type !== 'store_location' ) return new WP_Error('not_found','Store not found',[ 'status'=>404 ]);
        wp_delete_post( $id, true );
        return rest_ensure_response([ 'ok'=>true ]);
    }

    /* Ledger passthrough (uses WCSS_Ledger) */
    public function store_ledger( WP_REST_Request $req ) {
        $store_id = (int) $req['id'];
        $ym = $req->get_param('month') ?: gmdate('Y-m');

        $quota  = (int) get_post_meta( $store_id, '_store_quota',  true );
        $budget = (float) get_post_meta( $store_id, '_store_budget', true );

        $row = class_exists('WCSS_Ledger') ? WCSS_Ledger::get( $store_id, $ym ) : ['orders_count'=>0,'spend_total'=>0];
        $used_count = (int) ($row['orders_count'] ?? 0);
        $used_amt   = (float) ($row['spend_total'] ?? 0);

        return rest_ensure_response([
            'store_id' => $store_id,
            'month'    => $ym,
            'quota'    => $quota,
            'used_orders' => $used_count,
            'remaining_orders' => max(0, $quota - $used_count),
            'budget'   => $budget,
            'used_amount' => $used_amt,
            'remaining_amount' => max(0.0, $budget - $used_amt),
            'currency' => get_woocommerce_currency(),
        ]);
    }

    private function dto( WP_Post $p ): array {
        $code   = get_post_meta( $p->ID, '_store_code',   true );
        $city   = get_post_meta( $p->ID, '_store_city',   true );
        $state  = get_post_meta( $p->ID, '_store_state',  true );
        $quota  = get_post_meta( $p->ID, '_store_quota',  true );
        $budget = get_post_meta( $p->ID, '_store_budget', true );

        $assigned = get_users([
            'meta_key'   => '_wcss_store_id',
            'meta_value' => $p->ID,
            'fields'     => 'ID',
            'number'     => 1,
        ]);

        $user_id = $assigned ? (int) $assigned[0] : 0;
        // $user_id = (int) get_post_meta( $p->ID, '_wcss_user_id', true );
        $user    = $user_id ? get_userdata( $user_id ) : null;


        return [
            'id'     => $p->ID,
            'name'   => $p->post_title,
            'code'   => $code,
            'city'   => $city,
            'state'  => $state,
            'quota'  => (int) $quota,
            'budget' => (float) $budget,
            'user_id' => $user ? ($user->display_name ?: $user->user_login) : '',
            'user'      => $user ? ($user->display_name ?: $user->user_login) : '',
            'user_email' => $user ? $user->user_email : '',
        ];
    }


    // public function validate_user_ids_required( $value, $request, $param ): bool {
    //     if ( ! is_array( $value ) || empty( $value ) ) return false;
    //     foreach ( $value as $v ) {
    //         if ( ! is_numeric( $v ) ) return false;
    //     }
    //     return true;
    // }
    
    public function validate_user_id_required( $value ): bool {
        if ( ! is_numeric( $value ) ) return false;
        $uid = (int) $value;
        if ( $uid <= 0 ) return false;
    
        $u = get_user_by( 'id', $uid );
        if ( ! $u ) return false;
    
        // must be store_employee
        return in_array( 'store_employee', (array) $u->roles, true );
    }
    
    // public function list_users( WP_REST_Request $req ) {
    //     $users = get_users([
    //         'role__in' => [ 'store_employee' ],   // 👈 only this role
    //         'number'   => 200,
    //         'fields'   => [ 'ID', 'user_email', 'display_name' ],
    //     ]);
    
    //     $out = [];
    //     foreach ( $users as $u ) {
    //         $out[] = [
    //             'id'    => (int) $u->ID,
    //             'name'  => $u->display_name,
    //             'email' => $u->user_email,
    //         ];
    //     }
    //     return rest_ensure_response( $out );
    // }

    public function list_users( WP_REST_Request $req ) {
        $users = get_users([
            'role__in' => [ 'store_employee' ],
            'number'   => 500,
            'fields'   => [ 'ID', 'user_email', 'display_name' ],
        ]);
    
        $out = [];
        foreach ( $users as $u ) {
            $store_id = (int) get_user_meta( $u->ID, '_wcss_store_id', true );
            $store_name = $store_id ? get_the_title( $store_id ) : '';
    
            // 👇 Skip users already assigned to another store
            if ( $store_id && get_post_status( $store_id ) ) {
                continue;
            }
    
            $out[] = [
                'id'    => (int) $u->ID,
                'name'  => $u->display_name,
                'email' => $u->user_email,
            ];
        }
        return rest_ensure_response( $out );
    }
    
    
}