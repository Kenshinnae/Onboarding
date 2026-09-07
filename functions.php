<?php
defined( 'ABSPATH' ) || exit;

define( 'CO_THEME_VERSION', '1.0.0' );
define( 'CO_THEME_DIR', get_template_directory() );

foreach ( array( 'cpt', 'security', 'submissions', 'email', 'rest-api', 'admin', 'ma-tracker', 'project-crm' ) as $file ) {
	require_once CO_THEME_DIR . '/inc/' . $file . '.php';
}

add_action( 'after_setup_theme', function () {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
} );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_front_page() && ! is_page_template( 'templates/onboarding.php' ) ) {
		return;
	}
	$manifest_path = CO_THEME_DIR . '/assets/dist/.vite/manifest.json';
	if ( ! file_exists( $manifest_path ) ) {
		return;
	}
	$manifest = json_decode( file_get_contents( $manifest_path ), true );
	$entry = $manifest['assets/src/js/app.js'] ?? null;
	if ( ! $entry ) {
		return;
	}
	wp_enqueue_script( 'client-onboarding', get_template_directory_uri() . '/assets/dist/' . $entry['file'], array(), CO_THEME_VERSION, true );
	wp_script_add_data( 'client-onboarding', 'type', 'module' );
	foreach ( $entry['css'] ?? array() as $css ) {
		wp_enqueue_style( 'client-onboarding-' . md5( $css ), get_template_directory_uri() . '/assets/dist/' . $css, array(), CO_THEME_VERSION );
	}
	wp_localize_script( 'client-onboarding', 'clientOnboarding', array(
		'endpoint' => esc_url_raw( rest_url( 'client-onboarding/v1/submit' ) ),
		'nonce' => wp_create_nonce( 'wp_rest' ),
		'startedAt' => time(),
		'title' => co_get_setting( 'form_title', __( 'Website Project Onboarding', 'client-onboarding' ) ),
		'intro' => co_get_setting( 'intro_text', __( 'Tell us a little about your business and what you need from your new website.', 'client-onboarding' ) ),
		'logo' => co_get_logo_url(),
	) );
} );
