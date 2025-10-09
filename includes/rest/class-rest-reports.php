<?php
if ( ! defined('ABSPATH') ) exit;

class WCSS_REST_Reports {
    const NS = 'wcss/v1';

    public function __construct() { add_action('rest_api_init', [ $this, 'routes' ] ); }
    public function can_manage( $r = null ): bool {
        return current_user_can('wcss_manage_portal') || current_user_can('manage_woocommerce') || current_user_can('manage_options');
    }

    public function routes() {
        register_rest_route( self::NS, '/reports/overview', [
            'methods'  => 'GET',
            'permission_callback' => [ $this, 'can_manage' ],
            'callback' => [ $this, 'dashboard' ],
            'args'     => [
                'month' => [ 'sanitize_callback' => 'sanitize_text_field' ], // YYYY-MM
                'range' => [ 'sanitize_callback' => 'sanitize_text_field' ], // default 6m
            ],
        ]);
    }
/*
    public function dashboard( WP_REST_Request $req ) {
        // ---- helpers (scoped here so no new class methods are required) ----
        $status_slugs = [ 'pending','awaiting-approval','approved','rejected','processing','completed','cancelled','on-hold' ];
        $paid_like    = [ 'processing','completed','approved' ]; // adjust if needed

        $tz = wp_timezone();
        $ym_param = sanitize_text_field( (string) $req->get_param('month') );
        if ( $ym_param && preg_match('/^\d{4}-\d{2}$/', $ym_param) ) {
            $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $ym_param . '-01 00:00:00', $tz);
        } else {
            $start = (new DateTimeImmutable('first day of this month', $tz))->setTime(0,0,0);
        }
        $end   = $start->modify('first day of next month');

        $from = $start->format('Y-m-d H:i:s');
        $to   = $end->format('Y-m-d H:i:s');
        $ym   = $start->format('Y-m');

        // tiny helper to get only totals for a status
        $count_status = function( string $st ) use ( $from, $to ) : int {
            $q = wc_get_orders([
                'type'         => 'shop_order',
                'status'       => [ $st ],
                'date_created' => $from.'...'.$to,
                'paginate'     => true,
                'limit'        => 1,
                'return'       => 'ids',
            ]);
            return (int) ( is_object($q) ? $q->total : 0 );
        };

        // ---- counts by status (current month) ----
        $counts = [];
        foreach ( $status_slugs as $st ) { $counts[$st] = $count_status($st); }

        // ---- sales (orders + revenue) this month ----
        $orders_total = 0;
        $revenue = 0.0;
        $page = 1;
        do {
            $res = wc_get_orders([
                'type'         => 'shop_order',
                'status'       => $paid_like,
                'date_created' => $from.'...'.$to,
                'paginate'     => true,
                'limit'        => 200,
                'page'         => $page,
                'return'       => 'objects',
            ]);
            $orders_total = (int) ( is_object($res) ? $res->total : $orders_total );
            $orders_page  = is_object($res) ? $res->orders : [];
            foreach ( $orders_page as $o ) { $revenue += (float) $o->get_total(); }
            $page++;
        } while ( is_object($res) && $page <= max(1,(int)$res->max_num_pages) );

        // ---- stores: total + active (had orders this month) ----
        $stores_total = (int) ( new WP_Query([
            'post_type'      => 'store_location',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => false,
        ]) )->found_posts;

        $active_store_ids = [];
        $page = 1;
        do {
            $scan = wc_get_orders([
                'type'         => 'shop_order',
                'date_created' => $from.'...'.$to,
                'status'       => array_keys( wc_get_order_statuses() ),
                'paginate'     => true,
                'limit'        => 200,
                'page'         => $page,
                'return'       => 'objects',
            ]);
            $orders_page = is_object($scan) ? $scan->orders : [];
            foreach ( $orders_page as $o ) {
                $sid = (int) $o->get_meta('_wcss_store_id');
                if ( $sid ) { $active_store_ids[$sid] = true; }
            }
            $page++;
        } while ( is_object($scan) && $page <= max(1,(int)$scan->max_num_pages) );
        $stores_active = count($active_store_ids);

        // ---- trend: last 6 months count ----
        $trend = [];
        $first = (new DateTimeImmutable('first day of this month', $tz))->modify('-5 months');
        for ( $i=0; $i<6; $i++ ) {
            $mStart = $first->modify("+{$i} months")->setTime(0,0,0);
            $mEnd   = $mStart->modify('first day of next month');
            $q = wc_get_orders([
                'type'         => 'shop_order',
                'date_created' => $mStart->format('Y-m-d H:i:s').'...'.$mEnd->format('Y-m-d H:i:s'),
                'paginate'     => true,
                'limit'        => 1,
                'return'       => 'ids',
            ]);
            $trend[] = [
                'ym'     => $mStart->format('Y-m'),
                'orders' => (int) ( is_object($q) ? $q->total : 0 ),
            ];
        }

        // ---- top vendors (this month) ----
        $vendorAgg = [];  // name => [orders, revenue]
        $vendorId  = [];  // name => term_id
        $page = 1;
        do {
            $scan = wc_get_orders([
                'type'         => 'shop_order',
                'date_created' => $from.'...'.$to,
                'status'       => array_keys( wc_get_order_statuses() ),
                'paginate'     => true,
                'limit'        => 100,
                'page'         => $page,
                'return'       => 'objects',
            ]);
            $orders_page = is_object($scan) ? $scan->orders : [];
            foreach ( $orders_page as $o ) {
                $seen = [];
                foreach ( $o->get_items('line_item') as $it ) {
                    $pid = $it->get_product_id() ?: $it->get_variation_id();
                    if ( ! $pid ) continue;
                    $terms = wp_get_post_terms( $pid, 'wcss_vendor' );
                    if ( is_wp_error($terms) || empty($terms) ) continue;
                    $t = $terms[0]; $name = $t->name; $vendorId[$name] = (int)$t->term_id;
                    if ( empty($vendorAgg[$name]) ) $vendorAgg[$name] = ['orders'=>0,'revenue'=>0.0];
                    if ( empty($seen[$name]) ) { $vendorAgg[$name]['orders']++; $seen[$name]=true; }
                    $vendorAgg[$name]['revenue'] += (float) $it->get_total();
                }
            }
            $page++;
        } while ( is_object($scan) && $page <= max(1,(int)$scan->max_num_pages) );

        $vendors = [];
        foreach ( $vendorAgg as $name => $agg ) {
            $vendors[] = [
                'id'      => $vendorId[$name] ?? 0,
                'name'    => $name,
                'orders'  => (int) $agg['orders'],
                'revenue' => (float) $agg['revenue'],
            ];
        }
        usort($vendors, fn($a,$b) => $b['revenue'] <=> $a['revenue']);
        $vendors = array_slice($vendors, 0, 10);

        // ---- simple per-store ledger for month ----
        $ledger_map = []; // store_id => [store_id, store, orders, spend]
        $page = 1;
        do {
            $scan2 = wc_get_orders([
                'type'         => 'shop_order',
                'date_created' => $from.'...'.$to,
                'status'       => array_keys( wc_get_order_statuses() ),
                'paginate'     => true,
                'limit'        => 200,
                'page'         => $page,
                'return'       => 'objects',
            ]);
            $orders_page = is_object($scan2) ? $scan2->orders : [];
            foreach ( $orders_page as $o ) {
                $sid = (int) $o->get_meta('_wcss_store_id');
                if ( ! $sid ) continue;
                if ( empty($ledger_map[$sid]) ) {
                    $ledger_map[$sid] = [
                        'store_id' => $sid,
                        'store'    => get_the_title($sid) ?: ('#'.$sid),
                        'orders'   => 0,
                        'spend'    => 0.0,
                    ];
                }
                $ledger_map[$sid]['orders'] += 1;
                $ledger_map[$sid]['spend']  += (float) $o->get_total();
            }
            $page++;
        } while ( is_object($scan2) && $page <= max(1,(int)$scan2->max_num_pages) );

        $ledger = array_values($ledger_map);

        return rest_ensure_response([
            'month'  => $ym,
            'counts' => $counts,
            'stores' => [ 'total' => (int)$stores_total, 'active' => (int)$stores_active ],
            'sales'  => [ 'orders' => (int)$orders_total, 'revenue' => (float)$revenue, 'currency' => get_woocommerce_currency() ],
            'trend'  => $trend,
            'vendors'=> $vendors,
            'ledger' => $ledger,
        ]);
    }
*/

public function dashboard( WP_REST_Request $req ) {
    // ---- helpers ----
    $status_slugs = [ 'pending','awaiting-approval','approved','rejected','processing','completed','cancelled','on-hold' ];
    $paid_like    = [ 'processing','completed','approved' ];

    $tz = wp_timezone();
    $ym_param = sanitize_text_field( (string) $req->get_param('month') );
    if ( $ym_param && preg_match('/^\d{4}-\d{2}$/', $ym_param) ) {
        $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $ym_param . '-01 00:00:00', $tz);
    } else {
        $start = (new DateTimeImmutable('first day of this month', $tz))->setTime(0,0,0);
    }
    $end   = $start->modify('first day of next month');

    $from = $start->format('Y-m-d H:i:s');
    $to   = $end->format('Y-m-d H:i:s');
    $ym   = $start->format('Y-m');

    // count helper
    $count_status = function( string $st ) use ( $from, $to ) : int {
        $q = wc_get_orders([
            'type'         => 'shop_order',
            'status'       => [ $st ],
            'date_created' => $from.'...'.$to,
            'paginate'     => true,
            'limit'        => 1,
            'return'       => 'ids',
        ]);
        return (int) ( is_object($q) ? $q->total : 0 );
    };

    // ---- counts by status ----
    $counts = [];
    foreach ( $status_slugs as $st ) {
        $counts[$st] = $count_status($st);
    }

    // ---- sales (orders + revenue) this month ----
    $orders_total = 0;
    $revenue = 0.0;
    $page = 1;
    do {
        $res = wc_get_orders([
            'type'         => 'shop_order',
            'status'       => $paid_like,
            'date_created' => $from.'...'.$to,
            'paginate'     => true,
            'limit'        => 200,
            'page'         => $page,
            'return'       => 'objects',
        ]);
        $orders_total = (int) ( is_object($res) ? $res->total : $orders_total );
        $orders_page  = is_object($res) ? $res->orders : [];
        foreach ( $orders_page as $o ) {
            $revenue += (float) $o->get_total();
        }
        $page++;
    } while ( is_object($res) && $page <= max(1,(int)$res->max_num_pages) );

    // ---- stores: total + active (had orders this month) ----
    $stores_total = (int) ( new WP_Query([
        'post_type'      => 'store_location',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'no_found_rows'  => false,
    ]) )->found_posts;

    $active_store_ids = [];
    $page = 1;
    do {
        $scan = wc_get_orders([
            'type'         => 'shop_order',
            'date_created' => $from.'...'.$to,
            'status'       => array_keys( wc_get_order_statuses() ),
            'paginate'     => true,
            'limit'        => 200,
            'page'         => $page,
            'return'       => 'objects',
        ]);
        $orders_page = is_object($scan) ? $scan->orders : [];
        foreach ( $orders_page as $o ) {
            $sid = (int) $o->get_meta('_wcss_store_id');
            if ( $sid ) { $active_store_ids[$sid] = true; }
        }
        $page++;
    } while ( is_object($scan) && $page <= max(1,(int)$scan->max_num_pages) );
    $stores_active = count($active_store_ids);

    // ---- trend: last 6 months count ----
    $trend = [];
    $first = (new DateTimeImmutable('first day of this month', $tz))->modify('-5 months');
    for ( $i=0; $i<6; $i++ ) {
        $mStart = $first->modify("+{$i} months")->setTime(0,0,0);
        $mEnd   = $mStart->modify('first day of next month');
        $q = wc_get_orders([
            'type'         => 'shop_order',
            'date_created' => $mStart->format('Y-m-d H:i:s').'...'.$mEnd->format('Y-m-d H:i:s'),
            'paginate'     => true,
            'limit'        => 1,
            'return'       => 'ids',
        ]);
        $trend[] = [
            'ym'     => $mStart->format('Y-m'),
            'orders' => (int) ( is_object($q) ? $q->total : 0 ),
        ];
    }

    // ---- top vendors (this month) ----
    $vendorAgg = [];  // name => [orders, revenue]
    $vendorId  = [];  // name => term_id
    $page = 1;
    do {
        $scan = wc_get_orders([
            'type'         => 'shop_order',
            'date_created' => $from.'...'.$to,
            'status'       => array_keys( wc_get_order_statuses() ),
            'paginate'     => true,
            'limit'        => 100,
            'page'         => $page,
            'return'       => 'objects',
        ]);
        $orders_page = is_object($scan) ? $scan->orders : [];
        foreach ( $orders_page as $o ) {
            $seen = [];
            foreach ( $o->get_items('line_item') as $it ) {
                $pid = $it->get_product_id() ?: $it->get_variation_id();
                if ( ! $pid ) continue;
                $terms = wp_get_post_terms( $pid, 'wcss_vendor' );
                if ( is_wp_error($terms) || empty($terms) ) continue;
                $t = $terms[0];
                $name = $t->name;
                $vendorId[$name] = (int) $t->term_id;
                if ( empty($vendorAgg[$name]) ) $vendorAgg[$name] = ['orders'=>0,'revenue'=>0.0];
                if ( empty($seen[$name]) ) { $vendorAgg[$name]['orders']++; $seen[$name]=true; }
                $vendorAgg[$name]['revenue'] += (float) $it->get_total();
            }
        }
        $page++;
    } while ( is_object($scan) && $page <= max(1,(int)$scan->max_num_pages) );

    $vendors = [];
    foreach ( $vendorAgg as $name => $agg ) {
        $vendors[] = [
            'id'      => $vendorId[$name] ?? 0,
            'name'    => $name,
            'orders'  => (int) $agg['orders'],
            'revenue' => (float) $agg['revenue'],
        ];
    }
    usort($vendors, fn($a,$b) => $b['revenue'] <=> $a['revenue']);
    $vendors = array_slice($vendors, 0, 10);

    // ---- per-store ledger for month (WITH store name, quota, budget) ----
    $ledger_map = []; // store_id => row
    $page = 1;
    do {
        $scan2 = wc_get_orders([
            'type'         => 'shop_order',
            'date_created' => $from.'...'.$to,
            'status'       => array_keys( wc_get_order_statuses() ),
            'paginate'     => true,
            'limit'        => 200,
            'page'         => $page,
            'return'       => 'objects',
        ]);
        $orders_page = is_object($scan2) ? $scan2->orders : [];
        foreach ( $orders_page as $o ) {

            // $sid = (int) $o->get_meta('_wcss_store_id');

            // $sid = (int) $o->get_meta('_wcss_store_id');


            $store_name = get_the_title( $sid );
            if ( ! $sid ) continue;

            if ( empty($ledger_map[$sid]) ) {
                $quota  = (int) get_post_meta( $sid, '_store_quota',  true );
                $budget = (float) get_post_meta( $sid, '_store_budget', true );

                $ledger_map[$sid] = [
                    'store_id' => $sid,
                    'store'    => get_the_title($sid) ?: ('#'.$sid), // <-- store name here
                    'orders'   => 0,
                    'spend'    => 0.0,
                    'quota'    => $quota,
                    'budget'   => $budget,
                    'used_orders'  => 0,     // for compatibility with older UI if needed
                    'used_amount'  => 0.0,   // "
                ];
            }
            $ledger_map[$sid]['orders']      += 1;
            $ledger_map[$sid]['spend']       += (float) $o->get_total();
            $ledger_map[$sid]['used_orders'] += 1;                 // compat
            $ledger_map[$sid]['used_amount'] += (float) $o->get_total(); // compat
        }
        $page++;
    } while ( is_object($scan2) && $page <= max(1,(int)$scan2->max_num_pages) );

    $ledger = array_values($ledger_map);

    return rest_ensure_response([
        'month'  => $ym,
        'counts' => $counts,
        'stores' => [ 'total' => (int)$stores_total, 'active' => (int)$stores_active ],
        'sales'  => [ 'orders' => (int)$orders_total, 'revenue' => (float)$revenue, 'currency' => get_woocommerce_currency() ],
        'trend'  => $trend,
        'vendors'=> $vendors,
        'ledger' => $ledger,
    ]);
}

} 
