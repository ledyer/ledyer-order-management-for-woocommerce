<?php

\defined( 'ABSPATH' ) || die();

abstract class LedyerOmOrderStatus {
	const cancelled = 'cancelled';
  const fullyCaptured = 'fullyCaptured';
  const fullyRefunded = 'fullyRefunded';
  const partiallyCaptured = 'partiallyCaptured';
  const partiallyRefunded = 'partiallyRefunded';
  const unacknowledged = 'unacknowledged';
  const uncaptured = 'uncaptured';
}

abstract class LedyerOmPaymentStatus {
	const orderInitiated = "orderInitiated";
	const orderPending = "orderPending";
	const paymentPending = "paymentPending";
	const paymentConfirmed = "paymentConfirmed";
	const orderCaptured = "orderCaptured";
	const orderRefunded = "orderRefunded";
	const orderCancelled = "orderCancelled";
	const unknown = "unknown";
}