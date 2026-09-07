<?php
defined( 'ABSPATH' ) || exit;

function co_client_ip() {
	return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );
}

function co_check_rate_limit() {
	$key = 'co_rate_' . md5( co_client_ip() );
	$hits = (int) get_transient( $key );
	if ( $hits >= 5 ) {
		return new WP_Error( 'rate_limited', __( 'Too many submissions. Please try again later.', 'client-onboarding' ), array( 'status' => 429 ) );
	}
	set_transient( $key, $hits + 1, 15 * MINUTE_IN_SECONDS );
	return true;
}

function co_allowed_mimes() {
	return array( 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'csv' => 'text/csv', 'zip' => 'application/zip', 'svg' => 'image/svg+xml' );
}

function co_sanitize_list( $value ) {
	return array_values( array_filter( array_map( 'sanitize_text_field', is_array( $value ) ? $value : array() ) ) );
}
