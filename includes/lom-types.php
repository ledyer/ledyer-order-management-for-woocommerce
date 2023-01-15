<?php

\defined( 'ABSPATH' ) || die();

abstract class LedyerOrderStatus {
	const cancelled = 'cancelled';
  const fullyCaptured = 'fullyCaptured';
  const fullyRefunded = 'fullyRefunded';
  const partiallyCaptured = 'partiallyCaptured';
  const partiallyRefunded = 'partiallyRefunded';
  const unacknowledged = 'unacknowledged';
  const uncaptured = 'uncaptured';
}

