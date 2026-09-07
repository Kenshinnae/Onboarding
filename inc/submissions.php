<?php
defined( 'ABSPATH' ) || exit;

function co_field_schema() {
	return array(
		'company_name'=>'text','contact_name'=>'text','email'=>'email','phone'=>'text','business_description'=>'textarea','services'=>'textarea','existing_website'=>'text','website_url'=>'url',
		'website_type'=>'list','website_type_other'=>'text','primary_action'=>'text','primary_action_other'=>'text','language_count'=>'number','languages'=>'list','pages'=>'list','custom_pages'=>'list',
		'content_status'=>'text','assets'=>'list','assets_other'=>'text','references'=>'references','features'=>'list','features_other'=>'text',
		'domain_status'=>'text','domain'=>'text','hosting_status'=>'text','hosting_choice'=>'text','target_launch'=>'text','no_date'=>'bool','notes'=>'textarea'
	);
}

function co_sanitize_submission( $raw ) {
	$out = array();
	foreach ( co_field_schema() as $key => $type ) {
		$value = $raw[ $key ] ?? '';
		switch ( $type ) {
			case 'email': $out[$key] = sanitize_email( $value ); break;
			case 'url': $out[$key] = esc_url_raw( $value ); break;
			case 'textarea': $out[$key] = sanitize_textarea_field( $value ); break;
			case 'list': $out[$key] = co_sanitize_list( $value ); break;
			case 'bool': $out[$key] = (bool) $value; break;
			case 'number': $out[$key] = min( 10, max( 1, absint( $value ) ) ); break;
			case 'references':
				$out[$key] = array_slice( array_values( array_filter( array_map( function ( $ref ) {
					$url = esc_url_raw( $ref['url'] ?? '' );
					return $url ? array( 'url' => $url, 'notes' => sanitize_textarea_field( $ref['notes'] ?? '' ) ) : null;
				}, is_array( $value ) ? $value : array() ) ) ), 0, 3 );
				break;
			default: $out[$key] = sanitize_text_field( $value );
		}
	}
	return $out;
}

function co_validate_submission( $data ) {
	$labels = array( 'company_name'=>'Company name','contact_name'=>'Contact name','email'=>'Email address','business_description'=>'Business description' );
	$errors = array();
	foreach ( $labels as $key => $label ) if ( empty( $data[$key] ) ) $errors[$key] = "$label is required.";
	if ( ! empty( $data['email'] ) && ! is_email( $data['email'] ) ) $errors['email'] = 'Enter a valid email address.';
	if ( 'Yes' === $data['existing_website'] && empty( $data['website_url'] ) ) $errors['website_url'] = 'Website URL is required.';
	if ( empty( $data['languages'] ) || count( $data['languages'] ) !== (int) $data['language_count'] ) $errors['languages'] = 'Please enter every website language.';
	return $errors;
}

function co_save_meta( $post_id, $data ) {
	foreach ( $data as $key => $value ) update_post_meta( $post_id, '_onboarding_' . $key, $value );
	update_post_meta( $post_id, '_onboarding_status', 'new' );
	if ( function_exists( 'co_crm_sync_onboarding_project' ) ) co_crm_sync_onboarding_project( $post_id, $data );
}

function co_handle_uploads( $post_id ) {
	if ( empty( $_FILES['assets']['name'] ) ) return array();
	require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/image.php';
	$files = $_FILES['assets']; $ids = array(); $count = is_array( $files['name'] ) ? count( $files['name'] ) : 0;
	if ( $count > 10 ) return new WP_Error( 'too_many_files', 'A maximum of 10 files is allowed.', array( 'status' => 400 ) );
	foreach ( range( 0, max( -1, $count - 1 ) ) as $i ) {
		if ( empty( $files['name'][$i] ) ) continue;
		if ( (int) $files['size'][$i] > 10 * MB_IN_BYTES ) return new WP_Error( 'file_too_large', 'Each file must be 10MB or smaller.', array( 'status' => 400 ) );
		$checked = wp_check_filetype_and_ext( $files['tmp_name'][$i], $files['name'][$i], co_allowed_mimes() );
		if ( empty( $checked['type'] ) || empty( $checked['ext'] ) ) return new WP_Error( 'invalid_file', 'One of the uploaded files is not allowed.', array( 'status' => 400 ) );
		$_FILES['co_asset'] = array( 'name'=>$files['name'][$i], 'type'=>$files['type'][$i], 'tmp_name'=>$files['tmp_name'][$i], 'error'=>$files['error'][$i], 'size'=>$files['size'][$i] );
		$id = media_handle_upload( 'co_asset', $post_id, array(), array( 'test_form'=>false, 'mimes'=>co_allowed_mimes() ) );
		if ( is_wp_error( $id ) ) return $id;
		$ids[] = $id;
	}
	unset( $_FILES['co_asset'] ); update_post_meta( $post_id, '_onboarding_uploaded_files', $ids ); return $ids;
}

function co_handle_sitemap_upload( $post_id ) {
	if ( empty( $_FILES['sitemap']['name'] ) ) return 0;
	$file = $_FILES['sitemap'];
	if ( (int) $file['size'] > 10 * MB_IN_BYTES ) return new WP_Error( 'sitemap_too_large', 'The sitemap file must be 10MB or smaller.', array( 'status' => 400 ) );
	$checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], co_allowed_mimes() );
	if ( empty( $checked['type'] ) || empty( $checked['ext'] ) ) return new WP_Error( 'invalid_sitemap', 'The sitemap file type is not allowed.', array( 'status' => 400 ) );
	require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/image.php';
	$attachment_id = media_handle_upload( 'sitemap', $post_id, array( 'post_title' => 'Sitemap — ' . get_the_title( $post_id ) ), array( 'test_form' => false, 'mimes' => co_allowed_mimes() ) );
	if ( is_wp_error( $attachment_id ) ) return $attachment_id;
	update_post_meta( $post_id, '_onboarding_sitemap_file', $attachment_id );
	return $attachment_id;
}
