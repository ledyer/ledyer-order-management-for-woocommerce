<?php

\defined('ABSPATH') || die();

/**
 * Refunds a Ledyer order. Only supports fullRefund and refunds on an order that has been fullCaptured.
 *
 * @param int $order_id Order ID.
 * @param float $amount Refund amount.
 * @param object $api Lom API instance.
 */
function lom_refund_ledyer_order($order_id, $amount, $api) {
    $order = wc_get_order($order_id);

    if (!lom_order_placed_with_ledyer($order->get_payment_method())) {
        return false; // Not a Ledyer order
    }

    if (!_lom_can_order_be_refunded($order, $api)) {
        return false; // Order cannot be refunded
    }

    $response = _lom_attempt_refund($order, $amount, $api);
    return is_wp_error($response) ? _lom_handle_wp_error($order, $response, 'refunded') : _lom_process_successful_refund($order, $amount);
}

function _lom_can_order_be_refunded($order, $api) {
    if (!_lom_is_order_captured($order)) {
        _lom_add_order_note_and_save($order, 'Ledyer order not captured/captured outside WooCommerce, cannot be refunded.');
        return false;
    }

    $ledyer_payment_status_response = $api->get_payment_status($order->get_meta('_wc_ledyer_order_id', true));
    if (is_wp_error($ledyer_payment_status_response) || $ledyer_payment_status_response['status'] !== LedyerOmPaymentStatus::orderCaptured) {
        _lom_add_order_note_and_save($order, 'Ledyer order not fully captured, cannot be refunded.');
        return false;
    }

    return true;
}

function _lom_attempt_refund($order, $amount, $api) {
    $ledyer_order_id = $order->get_meta('_wc_ledyer_order_id', true);
    $ledyer_order = $api->get_order($ledyer_order_id);

    if (is_wp_error($ledyer_order) || count($ledyer_order['captured']) > 1) {
        return new WP_Error('refund_error', 'Error fetching Ledyer order or order captured multiple times.');
    }

    $captured_ledger_id = $ledyer_order['captured'][0]['ledgerId'];
    $refund_order = $order->get_refunds()[0];
    $orderMapper = new \LedyerOm\OrderMapper($refund_order);
    $data = $orderMapper->woo_to_ledyer_refund_order_lines();

    return $api->partial_refund_order($ledyer_order_id, $captured_ledger_id, $data);
}

function _lom_is_order_captured($order): bool {
    return $order->get_meta( '_wc_ledyer_capture_id', true) ? true : false;
}

function _lom_add_order_note_and_save($order, $note) {
    $order->add_order_note($note);
    $order->save();
}

function _lom_handle_wp_error($order, $wp_error, $action) {
    $errmsg = 'Ledyer order could not be ' . $action . ' due to an error: ' . $wp_error->get_error_message();
    _lom_add_order_note_and_save($order, $errmsg);
    return false;
}

function _lom_process_successful_refund($order, $amount) {
    $formatted_amount = wc_price($amount, array('currency' => $order->get_meta('_order_currency', true)));
    _lom_add_order_note_and_save($order, $formatted_amount . ' refunded via Ledyer.');
    $order->update_meta_data('_wc_ledyer_capture_id', false);
    $order->save();
}
