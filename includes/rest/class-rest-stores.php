<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCSS_REST_Stores {
    const NS = 'wcss/v1';
    public function __construct(){ add_action( 'rest_api_init', [ $this, 'routes' ] ); }
    public function can_manage( $r=null ): bool {
        return current_user_can( 'wcss_manage_portal' ) || current_user_can( 'manage_options' );
    }

    public function routes() {
        register_rest_route( self::NS, '/stores', [
            'methods'=>'GET','permission_callback'=>[ $this,'can_manage' ],'callback'=>[ $this,'list' ],
        ]);
        register_rest_route( self::NS, '/stores/(?P<id>\d+)', [
            'methods'=>'GET','permission_callback'=>[ $this,'can_manage' ],'callback'=>[ $this,'read' ],
        ]);

        // ✅ Create
        register_rest_route( self::NS, '/stores', [
            'methods'=>'POST','permission_callback'=>[ $this,'can_manage' ],'callback'=>[ $this,'create' ],
            'args'=>[
                'name'=>['required'=>true,'sanitize_callback'=>'sanitize_text_field'],
                'code'=>['required'=>false,'sanitize_callback'=>'sanitize_text_field'],
                'city'=>['required'=>false,'sanitize_callback'=>'sanitize_text_field'],
                'state'=>['required'=>false,'sanitize_callback'=>'sanitize_text_field'],
                'quota'=>['required'=>false,'validate_callback'=>'is_numeric'],
                'budget'=>['required'=>false,'sanitize_callback'=>'wc_format_decimal'],
            ],
        ]);

        // ✅ Update
        register_rest_route( self::NS, '/stores/(?P<id>\d+)', [
            'methods'=>'PUT','permission_callback'=>[ $this,'can_manage' ],'callback'=>[ $this,'update' ],
        ]);

        // ✅ Delete
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
        $q = new WP_Query([
            'post_type'=>'store_location',
            'post_status'=>'publish',
            'posts_per_page'=>50,
        ]);
        $items = array_map( [ $this, 'dto' ], $q->posts );
        return rest_ensure_response([ 'items'=>$items ]);
    }

    public function read( WP_REST_Request $req ) {
        $p = get_post( (int) $req['id'] );
        if ( ! $p || $p->post_type !== 'store_location' ) return new WP_Error('not_found','Store not found',[ 'status'=>404 ]);
        return rest_ensure_response( $this->dto( $p ) );
    }

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

        return rest_ensure_response( $this->dto( get_post($id) ) );
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
        return [
            'id'     => $p->ID,
            'name'   => $p->post_title,
            'code'   => $code,
            'city'   => $city,
            'state'  => $state,
            'quota'  => (int) $quota,
            'budget' => (float) $budget,
        ];
    }
}