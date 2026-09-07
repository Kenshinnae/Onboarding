<?php
defined( 'ABSPATH' ) || exit;

function co_register_submission_post_type() {
	register_post_type( 'onboarding_submit', array(
		'labels' => array( 'name' => __( 'Onboarding Submissions', 'client-onboarding' ), 'singular_name' => __( 'Submission', 'client-onboarding' ), 'menu_name' => __( 'Onboarding', 'client-onboarding' ) ),
		'public' => false, 'publicly_queryable' => true, 'exclude_from_search' => true, 'has_archive' => false,
		'rewrite' => array( 'slug' => 'onboarding-brief', 'with_front' => false ), 'query_var' => true,
		'show_ui' => true, 'show_in_menu' => true, 'show_in_rest' => false,
		'supports' => array( 'title' ), 'menu_icon' => 'dashicons-clipboard',
		'capability_type' => 'post', 'map_meta_cap' => true,
	) );
}

// WordPress normally loads themes before `init`. The fallback also supports
// bootstrap contexts where this theme is included after that hook has fired.
if ( did_action( 'init' ) ) {
	co_register_submission_post_type();
} else {
	add_action( 'init', 'co_register_submission_post_type' );
}
