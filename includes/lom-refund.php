<?php

\defined('ABSPATH') || die();

/**
 * Refunds a Ledyer order. Only supports fullRefund and refunds on an order that has been fullCaptured.
 *
 * @param int  $order_id Order ID.
 * @param bool $action If this was triggered by an action.
 * @param $api The lom api instance
 */
function lom_refund_ledyer_order($result, $order_id, $amount, $reason, $api) {
    $order = wc_get_order($order_id);

    // Only support Ledyer orders
    if (!lom_order_placed_with_ledyer($order->get_payment_method())) {
        return false;
    }

    // Check if the order was captured
    if (!lom_is_order_captured($order_id)) {
        lom_add_order_note_and_save($order, 'Ledyer order has not been captured / not captured through Woocommerce and therefore cannot be refunded.');
        return false;
    }

    $ledyer_order_id = $order->get_meta('_wc_ledyer_order_id', true);
    $ledyer_order = $api->get_order($ledyer_order_id);

    if (is_wp_error($ledyer_order)) {
        return lom_handle_wp_error($order, $ledyer_order, 'refunded');
    }

    if (!lom_can_order_be_refunded($api, $ledyer_order_id, $order, $ledyer_order)) {
        return false;
    }

    $captured_ledger_id = $ledyer_order['captured'][0]['ledgerId'];
    $refund_order = $order->get_refunds()[0];
    $orderMapper = new \LedyerOm\OrderMapper($refund_order);
    $data = $orderMapper->woo_to_ledyer_refund_order_lines();
    $response = $api->partial_refund_order($ledyer_order_id, $captured_ledger_id, $data);

    if (!is_wp_error($response)) {
        lom_process_successful_refund($order, $amount);
        return true;
    }

    return lom_handle_wp_error($order, $response, 'refunded');
}

function lom_is_order_captured($order_id) {
    return $order->get_meta( '_wc_ledyer_capture_id', true) ? true : false;
}

function lom_add_order_note_and_save($order, $note) {
    $order->add_order_note($note);
    $order->save();
}

function lom_handle_wp_error($order, $wp_error, $action) {
    $errmsg = 'Ledyer order could not be ' . $action . ' due to an error: ' . $wp_error->get_error_message();
    lom_add_order_note_and_save($order, $errmsg);
    return false;
}

function lom_can_order_be_refunded($api, $ledyer_order_id, $order, $ledyer_order) {
    // Additional checks for refund eligibility
    // Add order notes and return false if not eligible
    // Return true if eligible
}

function lom_process_successful_refund($order, $amount) {
    $formatted_amount = wc_price($amount, array('currency' => $order->get_meta('_order_currency', true)));
    lom_add_order_note_and_save($order, $formatted_amount . ' refunded via Ledyer.');
    $order->update_meta_data('_wc_ledyer_capture_id', false);
    $order->save();
}

