<?php
/**
 * Plugin Name: WooCommerce Orders Status
 * Description: Create a new Woocommerce order status and prevent stock reduction.
 * Version: 2.0
 * Author: Mohamed A. Bahnsawy
 * Author URI: https://brmja.tech
 */

 if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

add_filter( 'woocommerce_register_shop_order_post_statuses', function( $order_statuses ) {
    $order_statuses['wc-pending-approval'] = array(
        'label'                     => _x( 'Pending Approval', 'Order status', 'woocommerce' ),
        'public'                    => false,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop( 'Pending approval (%s)', 'Pending approvals (%s)', 'woocommerce' )
    );
    return $order_statuses;
});

add_filter( 'wc_order_statuses', function( $order_statuses ) {
    $order_statuses['wc-pending-approval'] = _x( 'Pending Approval', 'Order status', 'woocommerce' );
    return $order_statuses;
});


add_filter( 'woocommerce_order_status_completed', function( $reduce_stock, $order ) {
    if ( $order->get_status() == 'pending-approval' ) {
        return false;
    }
    return $reduce_stock;
}, 10, 2 );



add_action( 'woocommerce_order_status_changed', 'order_stock_reduction_based_on_status', 20, 4 );
function order_stock_reduction_based_on_status( $order_id, $old_status, $new_status, $order ){
    // Only for 'processing' and 'completed' order statuses change
    if ( $new_status == 'processing' || $new_status == 'completed' ){
    $stock_reduced = get_post_meta( $order_id, '_order_stock_reduced', true );
        if( empty($stock_reduced) ){
            wc_reduce_stock_levels($order_id);
        }
    }
}
// Finish

// Set 'pending-approval' status as default after order is placed
add_action( 'woocommerce_thankyou', function( $order_id ) {
    if ( ! $order_id ) {
        return;
    }
    $order = wc_get_order( $order_id );
    $order->update_status( 'pending-approval' );
});

add_action('woocommerce_payment_complete', function( $order_id ) {

    $order = wc_get_order( $order_id );
    $order->update_status( 'pending-approval' );

});


// Prevent order reduce 
function custom_prevent_stock_reduction( $reduce_stock, $order_id ) {
    $order = wc_get_order( $order_id );
    if ( $order->get_status() === 'pending-approval' ) {
        return false; // Do not reduce stock if status is 'Pending Approval'
    }
    return $reduce_stock; // Continue with default functionality otherwise
}
add_filter( 'woocommerce_can_reduce_order_stock', 'custom_prevent_stock_reduction', 10, 2 );



function custom_prevent_stock_reduction2( $reduce_stock, $order ) {
    if ( $order->get_status() === 'pending-approval' ) {
        return false; // Do not reduce stock if status is 'Pending Approval'
    }
    return $reduce_stock; // Continue with default functionality otherwise
}
add_filter( 'woocommerce_order_item_needs_processing', 'custom_prevent_stock_reduction2', 10, 2 );



// Disable stock reduction for the WooCommerce Stock Manager plugin
add_action('wc_stock_manager_before_process_stock_reduction', function() {
    global $woocommerce;
    $order_id = absint( $_POST['order_id'] );
    $order = wc_get_order( $order_id );
    if ( $order && $order->get_status() === 'pending-approval' ) {
        $woocommerce->add_error( __( 'Stock reduction is disabled for Pending Approval orders.', 'woocommerce' ) );
        exit;
    }
});

