<?php

\defined('ABSPATH') || die();

/**
 * Edit a Ledyer order.
 *
 * @param int $order_id Order ID.
 * @param bool $action If this was triggered by an action.
 * @param $api The lom api instance
 * @param string $syncType order or customer
 */
function lom_edit_ledyer_order($order_id, $action = false, $api, $syncType) {
    $options = get_option('lom_settings');
    if ('no' === $options['lom_auto_update']) {
        return;
    }

    $order = wc_get_order($order_id);

    if (!lom_allow_editing($order)) {
        return;
    }

    $ledyer_order_id = $order->get_meta( '_wc_ledyer_order_id', true);

    if (!$ledyer_order_id && !$order->get_meta('_transaction_id', true)) {
        $order->add_order_note('Ledyer order ID is missing, Ledyer order could not be updated at this time.');
        $order->save();
        return;
    }

    $ledyer_order = $api->get_order($ledyer_order_id);

    if (is_wp_error($ledyer_order)) {
        $errmsg = 'Ledyer order could not be updated due to an error: ' . $ledyer_order->get_error_message();
        $order->add_order_note($errmsg);
        $order->save();
        return;
    }

    if (!lom_is_order_editable($ledyer_order)) {
        $order->add_order_note('Ledyer order has been captured or cancelled, Ledyer order could not be updated at this time.');
        $order->save();
        return;
    }

    if ("order" === $syncType) {
        lom_process_order_sync($order, $api, $ledyer_order_id);
    } else if ("customer" === $syncType) {
        lom_process_customer_sync($order, $api, $ledyer_order_id);
    }
}

function lom_allow_editing($order) {
    $is_ledyer_order = lom_order_placed_with_ledyer($order->get_payment_method());
    if (!$is_ledyer_order) {
        return false;
    }

    if ($order->has_status(array('completed', 'refunded', 'cancelled'))) {
        return false;
    }

    return true;
}

function lom_is_order_editable($ledyer_order) {
    return count($ledyer_order['status']) == 1 && in_array(LedyerOmOrderStatus::uncaptured, $ledyer_order['status']);
}

function lom_process_order_sync($order, $api, $ledyer_order_id) {
    $orderMapper = new \LedyerOm\OrderMapper($order);
    $data = $orderMapper->woo_to_ledyer_edit_order_lines();
    $response = $api->edit_order($ledyer_order_id, $data);
    lom_handle_sync_response($order, $response);
}

function lom_process_customer_sync($order, $api, $ledyer_order_id) {
    $customerMapper = new \LedyerOm\CustomerMapper($order);
    $data = $customerMapper->woo_to_ledyer_customer();
    $response = $api->edit_customer($ledyer_order_id, $data);
    lom_handle_sync_response($order, $response);
}

function lom_handle_sync_response($order, $response) {
    if (is_wp_error($response)) {
        $errmsg = 'Sync could not be completed due to an error: ' . $response->get_error_message();
        $order->add_order_note($errmsg);
        $order->save();
    }
}
