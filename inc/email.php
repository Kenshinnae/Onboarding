<?php
defined( 'ABSPATH' ) || exit;
function co_send_notification( $post_id, $data ) {
	$recipient = co_get_setting( 'notification_email', get_option( 'admin_email' ) );
	$types = implode( ', ', $data['website_type'] ?? array() );
	$languages = implode( ', ', $data['languages'] ?? array() );
	$message = "New onboarding submission received.\n\nCompany: {$data['company_name']}\nContact: {$data['contact_name']}\nEmail: {$data['email']}\nWebsite type: {$types}\nLanguages: {$languages}\nTarget launch: {$data['target_launch']}\n\nView submission:\n" . get_edit_post_link( $post_id, 'raw' );
	wp_mail( $recipient, 'New Website Onboarding Submission — ' . $data['company_name'], $message );
}
