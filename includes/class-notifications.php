<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WCSS_Notifications {

    public function __construct() {
        // When a new order is created (our requisition submit)
        add_action( 'woocommerce_new_order', [ $this, 'notify_admin_on_submit' ], 20, 2 );

        // When status changes to approved/rejected
        add_action( 'woocommerce_order_status_changed', [ $this, 'notify_user_on_decision' ], 10, 4 );
    }

    /**
     * Admin notification when a requisition is submitted.
     * We piggyback Woo's built-in "New order" email so recipients come from Woo settings.
     */
    public function notify_admin_on_submit( $order_id, $order ) {
        if ( ! $order instanceof WC_Order ) {
            $order = wc_get_order( $order_id );
        }
        if ( ! $order ) return;

        // Only for our gateway (avoid emailing for other gateways)
        $pm = $order->get_payment_method();
        if ( $pm !== 'wcss_internal_requisition' ) {
            return;
        }

        // Trigger Woo's native New Order email
        $mailer = WC()->mailer();
        $emails = $mailer->get_emails();
        if ( isset( $emails['WC_Email_New_Order'] ) ) {
            $emails['WC_Email_New_Order']->trigger( $order_id );
        }
    }

    /**
     * Customer notification when an order is approved or rejected.
     */
    public function notify_user_on_decision( $order_id, $old_status, $new_status, $order ) {
        if ( ! $order instanceof WC_Order ) {
            $order = wc_get_order( $order_id );
        }
        if ( ! $order ) return;

        // Only act on our custom statuses
        if ( $new_status !== 'approved' && $new_status !== 'rejected' ) {
            return;
        }

        $to   = $order->get_billing_email();
        if ( ! $to ) return;

        $store_name = $order->get_meta( '_wcss_store_name' );
        $subject = ( $new_status === 'approved' )
            ? sprintf( __( 'Your requisition #%s has been APPROVED', 'wcss' ), $order->get_order_number() )
            : sprintf( __( 'Your requisition #%s has been REJECTED', 'wcss' ), $order->get_order_number() );

        $status_label = ( $new_status === 'approved' ) ? __( 'Approved', 'wcss' ) : __( 'Rejected', 'wcss' );
        $view_link    = $order->get_view_order_url();

        $lines = [];
        $lines[] = sprintf( __( 'Store: %s', 'wcss' ), $store_name ? $store_name : '-' );
        $lines[] = sprintf( __( 'Order: #%s', 'wcss' ), $order->get_order_number() );
        $lines[] = sprintf( __( 'Status: %s', 'wcss' ), $status_label );
        $lines[] = __( 'You can review this order here:', 'wcss' ) . ' ' . $view_link;

        $body = implode( "\n", $lines );

        // Use Woo's mailer so branding/templates apply
        wc_mail( $to, $subject, $body );
    }
}