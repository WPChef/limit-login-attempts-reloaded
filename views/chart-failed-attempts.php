<?php
/**
 * Chart failed attempts
 *
 * @var string $active_app
 * @var string $is_active_app_custom
 * @var bool|mixed $api_stats
 * @var bool $is_agency
 * @var array $requests
 * @var bool|string $is_exhausted
 */
use LLAR\Core\Config;
if ( Config::are_free_requests_exhausted() ) {
	include __DIR__ . '/chart-failed-attempts-cloud.php';
	include __DIR__ . '/chart-failed-attempts-local.php';
}
else {
if ( $is_active_app_custom ) {
	include __DIR__ . '/chart-failed-attempts-cloud.php';
} else {
	include __DIR__ . '/chart-failed-attempts-local.php';
}
}
