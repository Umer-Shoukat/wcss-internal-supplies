<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// class WCSS_Notifications {

//     public function __construct() {
//         // When a new order is created (our requisition submit)
//         add_action( 'woocommerce_new_order', [ $this, 'notify_admin_on_submit' ], 20, 2 );

//         // When status changes to approved/rejected
//         add_action( 'woocommerce_order_status_changed', [ $this, 'notify_user_on_decision' ], 10, 4 );
//     }

//     /**
//      * Admin notification when a requisition is submitted.
//      * We piggyback Woo's built-in "New order" email so recipients come from Woo settings.
//      */
//     public function notify_admin_on_submit( $order_id, $order ) {
//         if ( ! $order instanceof WC_Order ) {
//             $order = wc_get_order( $order_id );
//         }
//         if ( ! $order ) return;

//         // Only for our gateway (avoid emailing for other gateways)
//         $pm = $order->get_payment_method();
//         if ( $pm !== 'wcss_internal_requisition' ) {
//             return;
//         }

//         // Trigger Woo's native New Order email
//         $mailer = WC()->mailer();
//         $emails = $mailer->get_emails();
//         if ( isset( $emails['WC_Email_New_Order'] ) ) {
//             $emails['WC_Email_New_Order']->trigger( $order_id );
//         }
//     }

//     /**
//      * Customer notification when an order is approved or rejected.
//      */
//     public function notify_user_on_decision( $order_id, $old_status, $new_status, $order ) {
//         if ( ! $order instanceof WC_Order ) {
//             $order = wc_get_order( $order_id );
//         }
//         if ( ! $order ) return;

//         // Only act on our custom statuses
//         if ( $new_status !== 'approved' && $new_status !== 'rejected' ) {
//             return;
//         }

//         $to   = $order->get_billing_email();
//         if ( ! $to ) return;

//         $store_name = $order->get_meta( '_wcss_store_name' );
//         $subject = ( $new_status === 'approved' )
//             ? sprintf( __( 'Your requisition #%s has been APPROVED', 'wcss' ), $order->get_order_number() )
//             : sprintf( __( 'Your requisition #%s has been REJECTED', 'wcss' ), $order->get_order_number() );

//         $status_label = ( $new_status === 'approved' ) ? __( 'Approved', 'wcss' ) : __( 'Rejected', 'wcss' );
//         $view_link    = $order->get_view_order_url();

//         $lines = [];
//         $lines[] = sprintf( __( 'Store: %s', 'wcss' ), $store_name ? $store_name : '-' );
//         $lines[] = sprintf( __( 'Order: #%s', 'wcss' ), $order->get_order_number() );
//         $lines[] = sprintf( __( 'Status: %s', 'wcss' ), $status_label );
//         $lines[] = __( 'You can review this order here:', 'wcss' ) . ' ' . $view_link;

//         $body = implode( "\n", $lines );

//         // Use Woo's mailer so branding/templates apply
//         wc_mail( $to, $subject, $body );
//     }
// }

class WCSS_Notifications {

    public function __construct() {
        // New order placed
        add_action( 'woocommerce_new_order', [ $this, 'handle_new_order' ], 10, 2 );

        // Order status changed (pending → approved / rejected / etc)
        add_action(
            'woocommerce_order_status_changed',
            [ $this, 'handle_status_change' ],
            10,
            4
        );
        // add_action( 'woocommerce_order_status_processing', [ $this, 'notify_vendors_on_status_change' ], 10, 2 );
        add_action( 'woocommerce_order_status_completed',  [ $this, 'notify_vendors_on_status_change' ], 10, 2 );
        // If you also want it on your custom "approved" status:
        add_action( 'woocommerce_order_status_approved',   [ $this, 'notify_vendors_on_status_change' ], 10, 2 );

    }

    /**
     * Handle new order: notify store employee, supply manager, and vendors.
     */
    public function handle_new_order( $order_id, $order = null ) {
        $order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
        if ( ! $order ) return;

        $recipients = [];

        // 1) Store employee (based on _wcss_store_id meta)
        $store_employee_email = $this->get_store_employee_email_for_order( $order );
        if ( $store_employee_email ) {
            $recipients[] = $store_employee_email;
        }

        // 2) Supply manager (global email(s))
        $recipients = array_merge(
            $recipients,
            $this->get_supply_manager_emails()
        );

        // 3) Vendors on this order
        $recipients = array_merge(
            $recipients,
            $this->get_vendor_emails_for_order( $order )
        );

        $recipients = array_unique( array_filter( $recipients ) );
        if ( empty( $recipients ) ) {
            return;
        }

        $subject = sprintf(
            '[Internal Supply] New order #%s (%s)',
            $order->get_order_number(),
            wc_get_order_status_name( $order->get_status() )
        );

        $message = $this->build_new_order_email_body( $order );
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];

        // Use WooCommerce mail helper so it respects WC email settings
        if ( function_exists( 'wc_mail' ) ) {
            wc_mail( $recipients, $subject, $message, $headers );
        } else {
            wp_mail( $recipients, $subject, $message, $headers );
        }
    }

    /**
     * Handle order status change: notify about approval / rejection etc.
     */
    public function handle_status_change( $order_id, $old_status, $new_status, $order ) {
        $order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
        if ( ! $order ) return;

        // Only notify for certain transitions if you want (e.g. approved / rejected)
        $interesting = [ 'approved', 'rejected', 'completed', 'cancelled' ];
        if ( ! in_array( $new_status, $interesting, true ) ) {
            return;
        }

        $recipients = [];

        $store_employee_email = $this->get_store_employee_email_for_order( $order );
        if ( $store_employee_email ) {
            $recipients[] = $store_employee_email;
        }

        $recipients = array_merge(
            $recipients,
            $this->get_supply_manager_emails()
        );

        $recipients = array_merge(
            $recipients,
            $this->get_vendor_emails_for_order( $order )
        );

        $recipients = array_unique( array_filter( $recipients ) );
        if ( empty( $recipients ) ) {
            return;
        }

        $subject = sprintf(
            '[Internal Supply] Order #%s status changed: %s',
            $order->get_order_number(),
            wc_get_order_status_name( $new_status )
        );

        $message = $this->build_status_change_email_body(
            $order,
            $old_status,
            $new_status
        );
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];

        if ( function_exists( 'wc_mail' ) ) {
            wc_mail( $recipients, $subject, $message, $headers );
        } else {
            wp_mail( $recipients, $subject, $message, $headers );
        }
    }

    /* ===================== Helpers ===================== */

    /**
     * Find the store employee email for this order.
     * Uses _wcss_store_id on the order + user meta _wcss_store_id.
     */
    private function get_store_employee_email_for_order( WC_Order $order ): string {
        $store_id = (int) $order->get_meta( '_wcss_store_id' );
        if ( ! $store_id ) return '';

        $users = get_users( [
            'meta_key'   => '_wcss_store_id',
            'meta_value' => $store_id,
            'number'     => 1,
            'fields'     => [ 'user_email' ],
        ] );

        if ( empty( $users ) || empty( $users[0]->user_email ) ) {
            return '';
        }
        return $users[0]->user_email;
    }

    /**
     * Supply manager emails.
     * You can later wire this to plugin settings; for now:
     * - try option 'wcss_supply_manager_emails'
     * - fallback to site admin_email
     */
    private function get_supply_manager_emails(): array {
        $emails = [];

        $opt = get_option( 'wcss_supply_manager_emails', '' );
        if ( is_string( $opt ) && $opt !== '' ) {
            $parts = preg_split( '/[,\s]+/', $opt );
            foreach ( $parts as $e ) {
                $e = trim( $e );
                if ( is_email( $e ) ) $emails[] = $e;
            }
        }

        if ( empty( $emails ) ) {
            $admin = get_option( 'admin_email' );
            if ( is_email( $admin ) ) {
                $emails[] = $admin;
            }
        }

        return array_unique( array_filter( $emails ) );
    }

    /**
     * Collect vendor emails for all vendors who appear in line items.
     * Assumes:
     *  - taxonomy: wcss_vendor
     *  - term meta: _wcss_vendor_email
     */
    private function get_vendor_emails_for_order( WC_Order $order ): array {
        $emails = [];
        foreach ( $order->get_items( 'line_item' ) as $item ) {
            $pid = $item->get_product_id() ?: $item->get_variation_id();
            if ( ! $pid ) continue;

            $terms = wp_get_post_terms( $pid, 'wcss_vendor' );
            if ( is_wp_error( $terms ) || empty( $terms ) ) continue;

            foreach ( $terms as $term ) {
                $email = get_term_meta( $term->term_id, '_wcss_vendor_email', true );
                if ( is_email( $email ) ) {
                    $emails[] = $email;
                }
            }
        }
        return array_unique( array_filter( $emails ) );
    }

    /**
     * Simple HTML email for NEW order.
     */
    private function build_new_order_email_body( WC_Order $order ): string {
        $store_id   = (int) $order->get_meta( '_wcss_store_id' );
        $store_name = $store_id ? get_the_title( $store_id ) : '';
        $store_code = $store_id ? get_post_meta( $store_id, WCSS_Store_CPT::META_CODE, true ) : '';
        $store_city = $store_id ? get_post_meta( $store_id, WCSS_Store_CPT::META_CITY, true ) : '';

        ob_start();
        ?>
        <h2>New order received: #<?php echo esc_html( $order->get_order_number() ); ?></h2>
        <p>
            <strong>Date:</strong> <?php echo esc_html( $order->get_date_created() ? $order->get_date_created()->date_i18n( 'Y-m-d H:i' ) : '' ); ?><br>
            <strong>Status:</strong> <?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?><br>
            <strong>Total:</strong> <?php echo wp_kses_post( $order->get_formatted_order_total() ); ?><br>
            <strong>Customer:</strong> <?php echo esc_html( $order->get_billing_email() ); ?>
        </p>

        <?php if ( $store_id ) : ?>
            <h3>Store</h3>
            <p>
                <strong>Name:</strong> <?php echo esc_html( $store_name ?: '#' . $store_id ); ?><br>
                <strong>Code:</strong> <?php echo esc_html( $store_code ); ?><br>
                <strong>City:</strong> <?php echo esc_html( $store_city ); ?>
            </p>
        <?php endif; ?>

        <h3>Items</h3>
        <table cellspacing="0" cellpadding="6" border="1" style="border-collapse:collapse;">
            <thead>
                <tr>
                    <th align="left">Product</th>
                    <th align="left">Qty</th>
                    <th align="left">Total</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $order->get_items() as $item ) : ?>
                <tr>
                    <td><?php echo esc_html( $item->get_name() ); ?></td>
                    <td><?php echo esc_html( $item->get_quantity() ); ?></td>
                    <td><?php echo wp_kses_post( $order->get_formatted_line_subtotal( $item ) ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }

    /**
     * Simple HTML email for STATUS CHANGE.
     */
    private function build_status_change_email_body( WC_Order $order, string $old_status, string $new_status ): string {
        $old_label = wc_get_order_status_name( $old_status );
        $new_label = wc_get_order_status_name( $new_status );

        ob_start();
        ?>
        <h2>Order #<?php echo esc_html( $order->get_order_number() ); ?> status updated</h2>
        <p>
            <strong>From:</strong> <?php echo esc_html( $old_label ); ?><br>
            <strong>To:</strong> <?php echo esc_html( $new_label ); ?><br>
            <strong>Total:</strong> <?php echo wp_kses_post( $order->get_formatted_order_total() ); ?><br>
            <strong>Customer:</strong> <?php echo esc_html( $order->get_billing_email() ); ?>
        </p>
        <?php
        return ob_get_clean();
    }


    /**
     * Notify each vendor with only their own items from the order.
     *
     * @param int       $order_id
     * @param WC_Order|null $order
     */

    public function notify_vendors_on_status_change( $order_id, $order = null ) {
            if ( ! $order instanceof WC_Order ) {
                $order = wc_get_order( $order_id );
            }
            if ( ! $order ) {
                return;
            }

            // Build vendor → items map
            $vendors = []; // vendor_id => [ 'term' => WP_Term, 'email' => string, 'name' => string, 'items' => [] ]

            foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
                $product_id = $item->get_product_id() ?: $item->get_variation_id();
                if ( ! $product_id ) {
                    continue;
                }

                // Get vendor term from product
                $terms = wp_get_post_terms( $product_id, 'wcss_vendor' );
                if ( is_wp_error( $terms ) || empty( $terms ) ) {
                    continue;
                }

                $term = $terms[0];
                $vid  = (int) $term->term_id;

                if ( empty( $vendors[ $vid ] ) ) {
                    // 🔁 Adjust meta key to whatever you actually use for vendor email
                    $email = get_term_meta( $vid, '_wcss_vendor_email', true );
                    $name  = $term->name;

                    // If you store vendor email elsewhere, change it here.
                    if ( ! $email ) {
                        // No email → skip this vendor entirely
                        // or fall back to a default notification address if you want.
                        continue;
                    }

                    $vendors[ $vid ] = [
                        'term'  => $term,
                        'email' => $email,
                        'name'  => $name,
                        'items' => [],
                    ];
                }

                $vendors[ $vid ]['items'][ $item_id ] = $item;
            }

            if ( empty( $vendors ) ) {
                return;
            }

            // Shared info
            $subject_base = sprintf(
                'New order #%s from Internal Supply',
                $order->get_order_number()
            );

            foreach ( $vendors as $vendor_id => $data ) {
                $to      = $data['email'];
                $subject = $subject_base . ' – ' . $data['name'];
                $message = $this->build_vendor_email_html( $order, $data );
                $headers = [
                    'Content-Type: text/html; charset=UTF-8',
                    // Adjust "from" as needed:
                    'From: Internal Supply <no-reply@' . wp_parse_url( home_url(), PHP_URL_HOST ) . '>',
                ];

                wp_mail( $to, $subject, $message, $headers );
            }
    }

}