<?php
defined( 'ABSPATH' ) || exit;
add_action( 'rest_api_init', function () {
	register_rest_route( 'client-onboarding/v1', '/submit', array( 'methods'=>'POST', 'callback'=>'co_rest_submit', 'permission_callback'=>'__return_true' ) );
} );
function co_rest_submit( WP_REST_Request $request ) {
	if ( ! wp_verify_nonce( $request->get_header( 'X-WP-Nonce' ), 'wp_rest' ) ) return new WP_Error( 'invalid_nonce', 'Your session expired. Refresh and try again.', array( 'status'=>403 ) );
	$payload = json_decode( $request->get_param( 'payload' ), true );
	if ( ! is_array( $payload ) ) return new WP_Error( 'invalid_payload', 'Invalid form data.', array( 'status'=>400 ) );
	if ( ! empty( $payload['website'] ) ) return new WP_Error( 'spam', 'Unable to submit.', array( 'status'=>400 ) );
	if ( time() - (int) ( $payload['started_at'] ?? time() ) < 3 ) return new WP_Error( 'too_fast', 'Please take a moment to review your details.', array( 'status'=>400 ) );
	$rate = co_check_rate_limit(); if ( is_wp_error( $rate ) ) return $rate;
	$data = co_sanitize_submission( $payload ); $errors = co_validate_submission( $data );
	if ( $errors ) return new WP_REST_Response( array( 'success'=>false, 'message'=>'Please correct the highlighted fields.', 'errors'=>$errors ), 422 );
	$title = sprintf( '%s — %s — %s', $data['company_name'], $data['contact_name'], wp_date( 'd M Y' ) );
	$post_id = wp_insert_post( array( 'post_type'=>'onboarding_submit', 'post_status'=>'publish', 'post_title'=>$title ), true );
	if ( is_wp_error( $post_id ) ) return $post_id;
	co_save_meta( $post_id, $data ); $uploads = co_handle_uploads( $post_id );
	if ( is_wp_error( $uploads ) ) { wp_delete_post( $post_id, true ); return $uploads; }
	$sitemap = co_handle_sitemap_upload( $post_id );
	if ( is_wp_error( $sitemap ) ) { wp_delete_post( $post_id, true ); return $sitemap; }
	co_send_notification( $post_id, $data );
	return new WP_REST_Response( array( 'success'=>true, 'submission_id'=>$post_id, 'message'=>"Thank you. Your project details have been submitted." ), 201 );
}
