<?php
defined( 'ABSPATH' ) || exit;

function co_ma_register_post_types() {
	$types = array(
		'ma_client'  => array( 'MA Clients', 'MA Client', 'dashicons-building' ),
		'ma_project' => array( 'MA Projects', 'MA Project', 'dashicons-portfolio' ),
		'ma_worklog' => array( 'MA Work Logs', 'MA Work Log', 'dashicons-clock' ),
	);
	foreach ( $types as $type => $labels ) {
		register_post_type( $type, array(
			'labels' => array( 'name' => $labels[0], 'singular_name' => $labels[1] ),
			'public' => false, 'show_ui' => true, 'show_in_menu' => false, 'show_in_rest' => false,
			'supports' => array( 'title' ), 'menu_icon' => $labels[2], 'capability_type' => 'post', 'map_meta_cap' => true,
		) );
	}
}
add_action( 'init', 'co_ma_register_post_types' );

add_action( 'init', function () {
	add_rewrite_rule( '^ma-tracker/?$', 'index.php?co_ma_dashboard=1', 'top' );
	add_rewrite_rule( '^ma-report/([^/]+)/?$', 'index.php?co_ma_report=$matches[1]', 'top' );
} );
add_filter( 'query_vars', function ( $vars ) { $vars[] = 'co_ma_dashboard'; $vars[] = 'co_ma_report'; $vars[] = 'co_ma_calendar'; return $vars; } );

add_action( 'admin_menu', function () {
	add_menu_page( 'MA Hour Tracker', 'MA Tracker', 'edit_posts', 'ma-tracker', 'co_ma_dashboard_page', 'dashicons-chart-area', 25 );
	add_submenu_page( 'ma-tracker', 'Dashboard', 'Dashboard', 'edit_posts', 'ma-tracker', 'co_ma_dashboard_page' );
	add_submenu_page( 'ma-tracker', 'Clients & Packages', 'Clients', 'edit_posts', 'edit.php?post_type=ma_client' );
	add_submenu_page( 'ma-tracker', 'Projects', 'Projects', 'edit_posts', 'edit.php?post_type=ma_project' );
	add_submenu_page( 'ma-tracker', 'Work Logs', 'Work Logs', 'edit_posts', 'edit.php?post_type=ma_worklog' );
	add_submenu_page( 'ma-tracker', 'Notifications & Calendar', 'Notifications', 'manage_options', 'ma-email-settings', 'co_ma_email_settings_page' );
	add_submenu_page( null, 'MA Client Detail', 'MA Client Detail', 'edit_posts', 'ma-client-detail', 'co_ma_client_detail_page' );
} );

add_action( 'admin_init', function () {
	register_setting( 'co_ma_settings_group', 'co_ma_settings', array( 'sanitize_callback' => function ( $value ) {
		$emails = preg_split( '/[\s,;]+/', (string) ( $value['notification_emails'] ?? '' ) );
		$emails = array_values( array_unique( array_filter( array_map( 'sanitize_email', $emails ), 'is_email' ) ) );
		return array(
			'notification_emails' => implode( "\n", $emails ),
			'notify_package'      => ! empty( $value['notify_package'] ) ? '1' : '0',
			'notify_worklog'      => ! empty( $value['notify_worklog'] ) ? '1' : '0',
			'notify_expiry'       => ! empty( $value['notify_expiry'] ) ? '1' : '0',
			'calendar_enabled'    => ! empty( $value['calendar_enabled'] ) ? '1' : '0',
			'calendar_token'      => sanitize_text_field( $value['calendar_token'] ?? wp_generate_password( 32, false, false ) ),
		);
	} ) );
} );
function co_ma_setting( $key, $default = '' ) { $settings = get_option( 'co_ma_settings', array() ); return $settings[ $key ] ?? $default; }
function co_ma_calendar_token() { $token = co_ma_setting( 'calendar_token' ); if ( ! $token ) { $settings = get_option( 'co_ma_settings', array() ); $token = wp_generate_password( 32, false, false ); $settings['calendar_token'] = $token; update_option( 'co_ma_settings', $settings ); } return $token; }
function co_ma_notification_emails() { $stored = co_ma_setting( 'notification_emails', co_ma_setting( 'notification_email', get_option( 'admin_email' ) ) ); return array_values( array_filter( preg_split( '/[\s,;]+/', $stored ), 'is_email' ) ); }
function co_ma_email_settings_page() { $emails = implode( "\n", co_ma_notification_emails() ); $calendar_url = add_query_arg( 'co_ma_calendar', co_ma_calendar_token(), home_url( '/' ) ); ?>
	<div class="wrap"><h1>MA Notifications &amp; Calendar</h1><p>These notifications are for your internal admin team only. Add one admin email per line.</p><form method="post" action="options.php"><?php settings_fields( 'co_ma_settings_group' ); ?><input type="hidden" name="co_ma_settings[calendar_token]" value="<?php echo esc_attr( co_ma_calendar_token() ); ?>"><table class="form-table"><tr><th><label for="ma-emails">Admin emails</label></th><td><textarea class="large-text" rows="5" id="ma-emails" name="co_ma_settings[notification_emails]" placeholder="admin@example.com&#10;manager@example.com"><?php echo esc_textarea( $emails ); ?></textarea><p class="description">One email per line. Every enabled notification is sent to all addresses.</p></td></tr><tr><th>Email notifications</th><td><label><input type="checkbox" name="co_ma_settings[notify_package]" value="1" <?php checked( co_ma_setting( 'notify_package', '1' ), '1' ); ?>> New MA client/package created</label><br><label><input type="checkbox" name="co_ma_settings[notify_expiry]" value="1" <?php checked( co_ma_setting( 'notify_expiry', '1' ), '1' ); ?>> Contract expires in 7 days</label><br><label><input type="checkbox" name="co_ma_settings[notify_worklog]" value="1" <?php checked( co_ma_setting( 'notify_worklog', '1' ), '1' ); ?>> Work is logged</label></td></tr><tr><th>Google Calendar</th><td><label><input type="checkbox" name="co_ma_settings[calendar_enabled]" value="1" <?php checked( co_ma_setting( 'calendar_enabled', '1' ), '1' ); ?>> Publish MA expiry dates to the private calendar feed</label><p><input class="large-text code" readonly value="<?php echo esc_attr( $calendar_url ); ?>"></p><p class="description">In Google Calendar choose Other calendars → From URL, then paste this URL. Each admin subscribes once; new client expiry dates are added automatically. Keep this private URL secret.</p></td></tr></table><?php submit_button( 'Save notification settings' ); ?></form></div>
<?php }

function co_ma_meta( $post_id, $key, $default = '' ) {
	$value = get_post_meta( $post_id, '_ma_' . $key, true );
	return '' === $value ? $default : $value;
}

function co_ma_clients() {
	return get_posts( array( 'post_type' => 'ma_client', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
}

function co_ma_projects( $client_id = 0 ) {
	$args = array( 'post_type' => 'ma_project', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' );
	if ( $client_id ) $args['meta_query'] = array( array( 'key' => '_ma_client_id', 'value' => $client_id, 'compare' => '=' ) );
	return get_posts( $args );
}

function co_ma_available_pipeline_projects() {
	if ( ! post_type_exists( 'client_project' ) ) return array();
	$projects = get_posts( array( 'post_type' => 'client_project', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
	return array_values( array_filter( $projects, function ( $project ) {
		$stage = function_exists( 'co_crm_project_stage' ) ? co_crm_project_stage( $project->ID ) : get_post_meta( $project->ID, '_crm_stage', true );
		$linked = absint( get_post_meta( $project->ID, '_crm_ma_project_id', true ) );
		return 'ma' === $stage && ( ! $linked || 'ma_project' !== get_post_type( $linked ) );
	} ) );
}

function co_ma_logs( $client_id = 0, $project_id = 0 ) {
	$args = array( 'post_type' => 'ma_worklog', 'post_status' => 'publish', 'numberposts' => -1, 'meta_key' => '_ma_work_date', 'orderby' => 'meta_value', 'order' => 'DESC' );
	$meta = array();
	if ( $client_id ) $meta[] = array( 'key' => '_ma_client_id', 'value' => $client_id, 'compare' => '=' );
	if ( $project_id ) $meta[] = array( 'key' => '_ma_project_id', 'value' => $project_id, 'compare' => '=' );
	if ( $meta ) $args['meta_query'] = $meta;
	return get_posts( $args );
}

function co_ma_used_hours( $client_id, $project_id = 0, $month = '' ) {
	$total = 0.0;
	foreach ( co_ma_logs( $client_id, $project_id ) as $log ) {
		if ( $month && 0 !== strpos( (string) co_ma_meta( $log->ID, 'work_date' ), $month ) ) continue;
		if ( '0' !== (string) co_ma_meta( $log->ID, 'billable', '1' ) ) $total += (float) co_ma_meta( $log->ID, 'hours', 0 );
	}
	return round( $total, 2 );
}

function co_ma_package_type( $client_id ) { return co_ma_meta( $client_id, 'package_type', 'hour_bank' ); }
function co_ma_allowance( $client_id ) { return (float) co_ma_meta( $client_id, 'purchased_hours', 0 ); }
function co_ma_current_used( $client_id ) { return 'monthly_retainer' === co_ma_package_type( $client_id ) ? co_ma_used_hours( $client_id, 0, wp_date( 'Y-m' ) ) : co_ma_used_hours( $client_id ); }
function co_ma_monthly_history( $client_id ) {
	$months = array();
	foreach ( co_ma_logs( $client_id ) as $log ) { $month = substr( (string) co_ma_meta( $log->ID, 'work_date' ), 0, 7 ); if ( $month ) $months[ $month ] = co_ma_used_hours( $client_id, 0, $month ); }
	krsort( $months ); return $months;
}

function co_ma_hours( $value ) {
	return rtrim( rtrim( number_format( (float) $value, 2, '.', ',' ), '0' ), '.' );
}
function co_ma_date( $value, $default = '—' ) {
	if ( ! $value || ! strtotime( $value ) ) return $default;
	return wp_date( 'd/m/Y', strtotime( $value ) );
}

function co_ma_share_token( $client_id ) {
	$token = co_ma_meta( $client_id, 'share_token' );
	if ( ! $token ) { $token = wp_generate_password( 24, false, false ); update_post_meta( $client_id, '_ma_share_token', $token ); }
	return $token;
}
function co_ma_report_url( $client_id ) { return home_url( '/ma-report/' . co_ma_share_token( $client_id ) . '/' ); }
function co_ma_notify( $subject, $message ) { foreach ( co_ma_notification_emails() as $email ) wp_mail( $email, $subject, $message ); }

add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'co_ma_daily_expiry_check' ) ) wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'co_ma_daily_expiry_check' );
} );
add_action( 'co_ma_daily_expiry_check', 'co_ma_send_expiry_reminders' );
function co_ma_send_expiry_reminders() {
	if ( '1' !== co_ma_setting( 'notify_expiry', '1' ) ) return;
	$target = wp_date( 'Y-m-d', current_time( 'timestamp' ) + ( 7 * DAY_IN_SECONDS ) );
	$clients = get_posts( array( 'post_type' => 'ma_client', 'post_status' => 'publish', 'numberposts' => -1, 'meta_key' => '_ma_expiry_date', 'meta_value' => $target ) );
	foreach ( $clients as $client ) {
		if ( $target === co_ma_meta( $client->ID, 'expiry_notice_sent_for' ) ) continue;
		co_ma_notify( 'MA contract expires in 7 days — ' . $client->post_title, "An MA contract will expire in 7 days.\n\nCompany: {$client->post_title}\nExpiry: " . co_ma_date( $target ) . "\nPackage: " . ( 'monthly_retainer' === co_ma_package_type( $client->ID ) ? 'Monthly retainer' : 'Hour bank' ) . "\n\nClient details: " . admin_url( 'admin.php?page=ma-client-detail&client=' . $client->ID ) );
		update_post_meta( $client->ID, '_ma_expiry_notice_sent_for', $target );
	}
}

function co_ma_ics_escape( $value ) { return str_replace( array( "\\", ";", ",", "\r\n", "\n", "\r" ), array( "\\\\", '\\;', '\\,', '\\n', '\\n', '\\n' ), (string) $value ); }
function co_ma_render_calendar_feed() {
	if ( '1' !== co_ma_setting( 'calendar_enabled', '1' ) || ! hash_equals( co_ma_calendar_token(), sanitize_text_field( get_query_var( 'co_ma_calendar' ) ) ) ) { status_header( 404 ); exit; }
	header( 'Content-Type: text/calendar; charset=utf-8' );
	header( 'Content-Disposition: inline; filename="ma-contract-expiry.ics"' );
	echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Client Onboarding//MA Expiry//EN\r\nCALSCALE:GREGORIAN\r\nMETHOD:PUBLISH\r\nX-WR-CALNAME:MA Contract Expiry\r\n";
	foreach ( co_ma_clients() as $client ) {
		$expiry = preg_replace( '/[^0-9]/', '', co_ma_meta( $client->ID, 'expiry_date' ) ); if ( 8 !== strlen( $expiry ) ) continue;
		$next = gmdate( 'Ymd', strtotime( co_ma_meta( $client->ID, 'expiry_date' ) . ' +1 day' ) );
		echo "BEGIN:VEVENT\r\nUID:ma-client-{$client->ID}@" . sanitize_title( wp_parse_url( home_url(), PHP_URL_HOST ) ) . "\r\nDTSTAMP:" . gmdate( 'Ymd\\THis\\Z' ) . "\r\nDTSTART;VALUE=DATE:$expiry\r\nDTEND;VALUE=DATE:$next\r\nSUMMARY:" . co_ma_ics_escape( 'MA contract ends — ' . $client->post_title ) . "\r\nDESCRIPTION:" . co_ma_ics_escape( 'Review or extend this MA contract. ' . admin_url( 'admin.php?page=ma-client-detail&client=' . $client->ID ) ) . "\r\nEND:VEVENT\r\n";
	}
	echo "END:VCALENDAR\r\n"; exit;
}

add_action( 'admin_post_co_ma_save', function () {
	if ( ! current_user_can( 'edit_posts' ) ) wp_die( 'Permission denied.' );
	check_admin_referer( 'co_ma_save' );
	$entity = sanitize_key( $_POST['entity'] ?? '' );
	$return_url = esc_url_raw( wp_unslash( $_POST['return_url'] ?? '' ) );
	$redirect = $return_url ? add_query_arg( 'ma_saved', '1', $return_url ) : admin_url( 'admin.php?page=ma-tracker&ma_saved=1' );

	if ( 'client' === $entity ) {
		$name = sanitize_text_field( wp_unslash( $_POST['company_name'] ?? '' ) );
		$hours = max( 0, (float) ( $_POST['purchased_hours'] ?? 0 ) );
		if ( ! $name || ! $hours ) wp_die( 'Company name and purchased hours are required.' );
		$id = wp_insert_post( array( 'post_type' => 'ma_client', 'post_status' => 'publish', 'post_title' => $name ) );
		update_post_meta( $id, '_ma_purchased_hours', $hours );
		update_post_meta( $id, '_ma_package_type', in_array( sanitize_key( $_POST['package_type'] ?? '' ), array( 'hour_bank', 'monthly_retainer' ), true ) ? sanitize_key( $_POST['package_type'] ) : 'hour_bank' );
		update_post_meta( $id, '_ma_contact_name', sanitize_text_field( wp_unslash( $_POST['contact_name'] ?? '' ) ) );
		update_post_meta( $id, '_ma_contact_email', sanitize_email( wp_unslash( $_POST['contact_email'] ?? '' ) ) );
		$start = sanitize_text_field( $_POST['start_date'] ?? wp_date( 'Y-m-d' ) ); $duration = sanitize_key( $_POST['duration'] ?? '3m' );
		$months = array( '3m' => 3, '6m' => 6, '1y' => 12 ); $duration = isset( $months[ $duration ] ) ? $duration : '3m';
		$expiry = gmdate( 'Y-m-d', strtotime( '+' . $months[ $duration ] . ' months', strtotime( $start ) ) );
		update_post_meta( $id, '_ma_start_date', $start ); update_post_meta( $id, '_ma_duration', $duration ); update_post_meta( $id, '_ma_expiry_date', $expiry ); co_ma_share_token( $id );
		update_post_meta( $id, '_ma_status', 'active' );
		if ( '1' === co_ma_setting( 'notify_package', '1' ) ) co_ma_notify( 'New MA package — ' . $name, "A new MA package was created.\n\nCompany: $name\nHours: $hours\nDuration: $duration\nStart: " . co_ma_date( $start ) . "\nExpiry: " . co_ma_date( $expiry ) . "\n\nDashboard: " . home_url( '/ma-tracker/' ) );
		$redirect = add_query_arg( 'client', $id, $redirect );
		} elseif ( 'project' === $entity ) {
			$client_id = absint( $_POST['client_id'] ?? 0 ); $pipeline_id = absint( $_POST['pipeline_project_id'] ?? 0 ); $target = sanitize_text_field( wp_unslash( $_POST['ma_project_target'] ?? 'new' ) ); $name = sanitize_text_field( wp_unslash( $_POST['project_name'] ?? '' ) );
			if ( 'ma_client' !== get_post_type( $client_id ) ) wp_die( 'Invalid MA client.' );
			if ( $pipeline_id ) {
				if ( 'client_project' !== get_post_type( $pipeline_id ) || 'ma' !== co_crm_project_stage( $pipeline_id ) ) wp_die( 'The selected Pipeline project is not ready for MA.' );
				$existing_link = absint( get_post_meta( $pipeline_id, '_crm_ma_project_id', true ) );
				if ( $existing_link && 'ma_project' === get_post_type( $existing_link ) ) wp_die( 'This Pipeline project is already connected to MA.' );
				if ( 'new' === $target ) $name = get_the_title( $pipeline_id );
			}
			$description = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
			if ( $pipeline_id && ! $description ) $description = co_crm_project_brief( $pipeline_id ) ?: co_crm_meta( $pipeline_id, 'current_task' );
			if ( $pipeline_id && 'new' !== $target ) {
				$id = absint( $target );
				if ( 'ma_project' !== get_post_type( $id ) || $client_id !== absint( co_ma_meta( $id, 'client_id' ) ) ) wp_die( 'The selected MA workstream does not belong to this client.' );
				$current_pipeline = absint( co_ma_meta( $id, 'crm_project_id' ) );
				if ( $current_pipeline && $current_pipeline !== $pipeline_id ) wp_die( 'This MA workstream is already connected to another Pipeline project.' );
				if ( ! co_ma_meta( $id, 'description' ) && $description ) update_post_meta( $id, '_ma_description', $description );
			} else {
				if ( ! $name ) wp_die( 'Project name is required.' );
				$id = wp_insert_post( array( 'post_type' => 'ma_project', 'post_status' => 'publish', 'post_title' => $name ) );
				update_post_meta( $id, '_ma_client_id', $client_id ); update_post_meta( $id, '_ma_status', 'active' );
				update_post_meta( $id, '_ma_description', $description );
			}
			if ( $pipeline_id ) {
				update_post_meta( $id, '_ma_crm_project_id', $pipeline_id );
				update_post_meta( $pipeline_id, '_crm_ma_project_id', $id );
				update_post_meta( $pipeline_id, '_crm_ma_client_id', $client_id );
			}
			$redirect = add_query_arg( 'client', $client_id, $redirect );
	} elseif ( 'client_update' === $entity ) {
		$id = absint( $_POST['client_id'] ?? 0 ); if ( 'ma_client' !== get_post_type( $id ) ) wp_die( 'Invalid client.' );
		$name = sanitize_text_field( wp_unslash( $_POST['company_name'] ?? '' ) ); $hours = max( 0, (float) ( $_POST['purchased_hours'] ?? 0 ) );
		wp_update_post( array( 'ID' => $id, 'post_title' => $name ) ); update_post_meta( $id, '_ma_purchased_hours', $hours ); update_post_meta( $id, '_ma_package_type', in_array( sanitize_key( $_POST['package_type'] ?? '' ), array( 'hour_bank', 'monthly_retainer' ), true ) ? sanitize_key( $_POST['package_type'] ) : co_ma_package_type( $id ) );
		update_post_meta( $id, '_ma_contact_name', sanitize_text_field( wp_unslash( $_POST['contact_name'] ?? '' ) ) ); update_post_meta( $id, '_ma_contact_email', sanitize_email( wp_unslash( $_POST['contact_email'] ?? '' ) ) );
		$start = sanitize_text_field( $_POST['start_date'] ?? wp_date( 'Y-m-d' ) ); $duration = sanitize_key( $_POST['duration'] ?? '3m' ); $months = array( '3m'=>3, '6m'=>6, '1y'=>12 ); $duration = isset( $months[$duration] ) ? $duration : '3m';
		update_post_meta( $id, '_ma_start_date', $start ); update_post_meta( $id, '_ma_duration', $duration ); update_post_meta( $id, '_ma_expiry_date', gmdate( 'Y-m-d', strtotime( '+' . $months[$duration] . ' months', strtotime( $start ) ) ) );
		$redirect = admin_url( 'admin.php?page=ma-client-detail&client=' . $id . '&ma_saved=1' );
	} elseif ( 'client_model' === $entity ) {
		$id = absint( $_POST['client_id'] ?? 0 ); if ( 'ma_client' !== get_post_type( $id ) ) wp_die( 'Invalid client.' );
		$type = sanitize_key( $_POST['package_type'] ?? 'hour_bank' ); if ( ! in_array( $type, array( 'hour_bank', 'monthly_retainer' ), true ) ) $type = 'hour_bank';
		update_post_meta( $id, '_ma_package_type', $type ); update_post_meta( $id, '_ma_purchased_hours', max( .25, (float) ( $_POST['allowance_hours'] ?? 0 ) ) );
		$redirect = admin_url( 'admin.php?page=ma-client-detail&client=' . $id . '&ma_saved=1' );
	} elseif ( 'client_extend' === $entity ) {
		$id = absint( $_POST['client_id'] ?? 0 ); if ( 'ma_client' !== get_post_type( $id ) ) wp_die( 'Invalid client.' );
		$duration = sanitize_key( $_POST['extension'] ?? '3m' ); $months = array( '3m'=>3, '6m'=>6, '1y'=>12 ); $duration = isset( $months[$duration] ) ? $duration : '3m';
		$current_expiry = co_ma_meta( $id, 'expiry_date', wp_date( 'Y-m-d' ) ); $base = max( strtotime( $current_expiry ), current_time( 'timestamp' ) );
		update_post_meta( $id, '_ma_expiry_date', gmdate( 'Y-m-d', strtotime( '+' . $months[$duration] . ' months', $base ) ) );
		$redirect = admin_url( 'admin.php?page=ma-client-detail&client=' . $id . '&ma_saved=1' );
	} elseif ( 'worklog' === $entity ) {
		$client_id = absint( $_POST['client_id'] ?? 0 ); $project_id = absint( $_POST['project_id'] ?? 0 );
		$title = sanitize_text_field( wp_unslash( $_POST['task_title'] ?? '' ) ); $hours = max( 0, (float) ( $_POST['hours'] ?? 0 ) );
		if ( ! $client_id || ! $project_id || ! $title || ! $hours ) wp_die( 'Client, project, task and hours are required.' );
		$id = wp_insert_post( array( 'post_type' => 'ma_worklog', 'post_status' => 'publish', 'post_title' => $title ) );
		update_post_meta( $id, '_ma_client_id', $client_id ); update_post_meta( $id, '_ma_project_id', $project_id );
		update_post_meta( $id, '_ma_hours', $hours ); update_post_meta( $id, '_ma_work_date', sanitize_text_field( $_POST['work_date'] ?? wp_date( 'Y-m-d' ) ) );
		update_post_meta( $id, '_ma_assignee', sanitize_text_field( wp_unslash( $_POST['assignee'] ?? '' ) ) );
		update_post_meta( $id, '_ma_description', sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ) );
		update_post_meta( $id, '_ma_billable', isset( $_POST['billable'] ) ? '1' : '0' );
		if ( '1' === co_ma_setting( 'notify_worklog', '1' ) ) co_ma_notify( 'MA work logged — ' . $title, "A work log was added.\n\nTask: $title\nHours: $hours\nDate: " . co_ma_date( sanitize_text_field( $_POST['work_date'] ?? '' ) ) . "\n\nDashboard: " . home_url( '/ma-tracker/' ) );
		$redirect = add_query_arg( 'client', $client_id, $redirect );
	}
	wp_safe_redirect( $redirect ); exit;
} );

add_action( 'admin_post_co_ma_delete_log', function () {
	if ( ! current_user_can( 'delete_posts' ) ) wp_die( 'Permission denied.' );
	$log_id = absint( $_GET['log_id'] ?? 0 ); check_admin_referer( 'co_ma_delete_' . $log_id );
	if ( 'ma_worklog' === get_post_type( $log_id ) ) wp_trash_post( $log_id );
	wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=ma-tracker' ) ); exit;
} );

function co_ma_client_detail_page() {
	$id = absint( $_GET['client'] ?? 0 ); $client = get_post( $id );
	if ( ! $client || 'ma_client' !== $client->post_type ) { echo '<div class="wrap"><h1>Client not found</h1></div>'; return; }
	$projects = co_ma_projects( $id ); $logs = co_ma_logs( $id ); $purchased = co_ma_allowance( $id ); $used = co_ma_current_used( $id ); $duration = co_ma_meta( $id, 'duration', '3m' ); $package_type = co_ma_package_type( $id ); $history = co_ma_monthly_history( $id );
	?>
	<div class="wrap co-ma-wrap co-ma-detail">
		<header class="co-ma-top"><div><span class="co-ma-kicker">Client MA workspace</span><h1><?php echo esc_html( $client->post_title ); ?></h1><p>Only projects and work logs belonging to this company are shown here.</p></div><div class="co-ma-actions"><a class="co-ma-btn co-ma-btn-light" href="<?php echo esc_url( admin_url( 'admin.php?page=ma-tracker' ) ); ?>">← All clients</a><a class="co-ma-btn co-ma-btn-light" target="_blank" rel="noopener" href="<?php echo esc_url( co_ma_report_url( $id ) ); ?>">Open client report ↗</a><button type="button" class="co-ma-btn co-ma-copy" data-copy="<?php echo esc_url( co_ma_report_url( $id ) ); ?>">Copy report link</button></div></header>
		<?php if ( isset( $_GET['ma_saved'] ) ) : ?><div class="co-ma-notice">Client details saved.</div><?php endif; ?>
		<section class="co-ma-overview"><div><small>Package type</small><strong class="co-ma-type-name"><?php echo 'monthly_retainer' === $package_type ? 'Monthly' : 'Hour bank'; ?></strong><span><?php echo 'monthly_retainer' === $package_type ? esc_html( co_ma_hours( $purchased ) ) . ' hours / month' : esc_html( co_ma_hours( $purchased ) ) . ' purchased hours'; ?></span></div><div><small><?php echo 'monthly_retainer' === $package_type ? 'Used this month' : 'Used'; ?></small><strong><?php echo esc_html( co_ma_hours( $used ) ); ?></strong><span>Billable work</span></div><div><small><?php echo 'monthly_retainer' === $package_type ? 'Remaining this month' : 'Remaining'; ?></small><strong><?php echo esc_html( co_ma_hours( $purchased - $used ) ); ?></strong><span>Available balance</span></div><div><small>Contract expiry</small><strong class="co-ma-date-value"><?php echo esc_html( co_ma_date( co_ma_meta( $id, 'expiry_date' ) ) ); ?></strong><span><?php echo count( $projects ); ?> client projects</span></div></section>
		<section class="co-ma-section co-ma-model-card"><header><div><h2>Package model &amp; contract</h2><p>Choose how hours are calculated for this company</p></div></header><div class="co-ma-model-layout"><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'co_ma_save' ); ?><input type="hidden" name="action" value="co_ma_save"><input type="hidden" name="entity" value="client_model"><input type="hidden" name="client_id" value="<?php echo $id; ?>"><fieldset class="co-ma-package-types"><label><input type="radio" name="package_type" value="hour_bank" <?php checked( $package_type, 'hour_bank' ); ?>><span><b>Hour bank</b><small>Hours decrease continuously until depleted</small></span></label><label><input type="radio" name="package_type" value="monthly_retainer" <?php checked( $package_type, 'monthly_retainer' ); ?>><span><b>Monthly retainer</b><small>Allowance resets at the start of each month</small></span></label></fieldset><label class="co-ma-allowance-label"><?php echo 'monthly_retainer' === $package_type ? 'Hours per month' : 'Purchased hours'; ?><input required type="number" min=".25" step=".25" name="allowance_hours" value="<?php echo esc_attr( $purchased ); ?>"></label><button class="co-ma-btn" type="submit">Save package model</button></form><form class="co-ma-extend" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'co_ma_save' ); ?><input type="hidden" name="action" value="co_ma_save"><input type="hidden" name="entity" value="client_extend"><input type="hidden" name="client_id" value="<?php echo $id; ?>"><strong>Extend contract</strong><p>Current expiry: <?php echo esc_html( co_ma_date( co_ma_meta( $id, 'expiry_date' ) ) ); ?></p><div><button name="extension" value="3m" class="co-ma-btn co-ma-btn-light">+ 3 months</button><button name="extension" value="6m" class="co-ma-btn co-ma-btn-light">+ 6 months</button><button name="extension" value="1y" class="co-ma-btn co-ma-btn-light">+ 1 year</button></div></form></div></section>
		<div class="co-ma-detail-grid"><section class="co-ma-section"><header><div><h2>Package details</h2><p>Edit only this company’s package information</p></div></header><form class="co-ma-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'co_ma_save' ); ?><input type="hidden" name="action" value="co_ma_save"><input type="hidden" name="entity" value="client_update"><input type="hidden" name="client_id" value="<?php echo $id; ?>"><div class="co-ma-form-grid"><label>Company name<input required name="company_name" value="<?php echo esc_attr( $client->post_title ); ?>"></label><label>Purchased hours<input required type="number" min=".25" step=".25" name="purchased_hours" value="<?php echo esc_attr( $purchased ); ?>"></label><label>Contact name<input name="contact_name" value="<?php echo esc_attr( co_ma_meta( $id, 'contact_name' ) ); ?>"></label><label>Contact email<input type="email" name="contact_email" value="<?php echo esc_attr( co_ma_meta( $id, 'contact_email' ) ); ?>"></label><label>Start date<input type="date" name="start_date" value="<?php echo esc_attr( co_ma_meta( $id, 'start_date' ) ); ?>"></label><fieldset class="co-ma-duration"><legend>Duration</legend><label><input type="radio" name="duration" value="3m" <?php checked( $duration, '3m' ); ?>><span>3 months</span></label><label><input type="radio" name="duration" value="6m" <?php checked( $duration, '6m' ); ?>><span>6 months</span></label><label><input type="radio" name="duration" value="1y" <?php checked( $duration, '1y' ); ?>><span>1 year</span></label></fieldset></div><button class="co-ma-btn" type="submit">Save package details</button></form></section>
		<section class="co-ma-section"><header><div><h2><?php echo esc_html( $client->post_title ); ?> projects</h2><p>No projects from other companies appear here</p></div><button class="co-ma-btn" data-ma-open="project">+ Add project</button></header><div class="co-ma-projects"><?php foreach ( $projects as $project ) : $crm_project_id = absint( co_ma_meta( $project->ID, 'crm_project_id' ) ); ?><article><span class="co-ma-project-icon">P</span><div><h3><?php echo esc_html( $project->post_title ); ?></h3><p><?php echo esc_html( co_ma_meta( $project->ID, 'description' ) ); ?></p><?php if ( $crm_project_id ) : ?><a class="co-ma-pipeline-link" href="<?php echo esc_url( admin_url( 'admin.php?page=co-projects&open_project=' . $crm_project_id ) ); ?>">Linked to Pipeline →</a><?php endif; ?></div><strong><?php echo esc_html( co_ma_hours( co_ma_used_hours( $id, $project->ID, 'monthly_retainer' === $package_type ? wp_date( 'Y-m' ) : '' ) ) ); ?><small>hours</small></strong></article><?php endforeach; ?></div></section></div>
		<?php if ( 'monthly_retainer' === $package_type ) : ?><section class="co-ma-section co-ma-month-history"><header><div><h2>Monthly usage history</h2><p>Each month receives a fresh <?php echo esc_html( co_ma_hours( $purchased ) ); ?>-hour allowance; unused hours do not roll over</p></div></header><div class="co-ma-months"><?php if ( $history ) : foreach ( $history as $month => $month_used ) : ?><article><small><?php echo esc_html( wp_date( 'F Y', strtotime( $month . '-01' ) ) ); ?></small><strong><?php echo esc_html( co_ma_hours( $month_used ) ); ?>h</strong><span><?php echo esc_html( co_ma_hours( max( 0, $purchased - $month_used ) ) ); ?>h remaining</span><div class="co-ma-bar"><i style="width:<?php echo esc_attr( min( 100, $purchased ? $month_used / $purchased * 100 : 0 ) ); ?>%"></i></div></article><?php endforeach; else : ?><p class="co-ma-empty">No monthly usage has been recorded yet.</p><?php endif; ?></div></section><?php endif; ?>
		<section class="co-ma-section co-ma-detail-logs"><header><div><h2><?php echo esc_html( $client->post_title ); ?> work logs</h2><p>Every entry below belongs to this client only</p></div><button class="co-ma-btn" data-ma-open="worklog">+ Log work</button></header><div class="co-ma-table-wrap"><table class="co-ma-table"><thead><tr><th>Date</th><th>Task</th><th>Project</th><th>Person</th><th>Hours</th></tr></thead><tbody><?php foreach ( $logs as $log ) : $project = get_post( absint( co_ma_meta( $log->ID, 'project_id' ) ) ); ?><tr><td><?php echo esc_html( co_ma_date( co_ma_meta( $log->ID, 'work_date' ) ) ); ?></td><td><strong><?php echo esc_html( $log->post_title ); ?></strong><small><?php echo esc_html( co_ma_meta( $log->ID, 'description' ) ); ?></small></td><td><span class="co-ma-tag"><?php echo esc_html( $project ? $project->post_title : '—' ); ?></span></td><td><?php echo esc_html( co_ma_meta( $log->ID, 'assignee' ) ); ?></td><td><b><?php echo esc_html( co_ma_hours( co_ma_meta( $log->ID, 'hours', 0 ) ) ); ?>h</b></td></tr><?php endforeach; ?></tbody></table></div></section>
	</div>
	<?php co_ma_modals( array( $client ), $projects, $id, false ); co_ma_assets();
}

function co_ma_dashboard_page( $frontend = false ) {
	$clients = co_ma_clients(); $selected_id = absint( $_GET['client'] ?? ( $clients[0]->ID ?? 0 ) );
	$selected = $selected_id ? get_post( $selected_id ) : null; $projects = co_ma_projects( $selected_id ); $logs = co_ma_logs( $selected_id );
	$purchased = $selected ? co_ma_allowance( $selected_id ) : 0; $used = $selected ? co_ma_current_used( $selected_id ) : 0; $remaining = $purchased - $used;
	$total_purchased = 0; $total_used = 0; foreach ( $clients as $client ) { $total_purchased += co_ma_allowance( $client->ID ); $total_used += co_ma_current_used( $client->ID ); }
	?>
	<div class="wrap co-ma-wrap">
		<header class="co-ma-top"><div><span class="co-ma-kicker">Maintenance service</span><h1>MA Hour Tracker</h1><p>Track packages, projects and every hour spent in one place.</p></div><div class="co-ma-actions"><?php if ( $frontend && current_user_can( 'manage_options' ) ) : ?><a class="co-ma-btn co-ma-btn-light" href="<?php echo esc_url( admin_url( 'admin.php?page=ma-email-settings' ) ); ?>">Email settings</a><?php endif; ?><button class="co-ma-btn co-ma-btn-light" data-ma-open="client">+ New package</button><button class="co-ma-btn" data-ma-open="worklog">+ Log work</button></div></header>
		<?php if ( isset( $_GET['ma_saved'] ) ) : ?><div class="co-ma-notice">Saved successfully.</div><?php endif; ?>
		<section class="co-ma-overview">
			<div><small>Active clients</small><strong><?php echo count( $clients ); ?></strong><span>MA packages</span></div>
			<div><small>Hours purchased</small><strong><?php echo esc_html( co_ma_hours( $total_purchased ) ); ?></strong><span>Across all clients</span></div>
			<div><small>Hours used</small><strong><?php echo esc_html( co_ma_hours( $total_used ) ); ?></strong><span><?php echo $total_purchased ? esc_html( round( $total_used / $total_purchased * 100 ) ) : 0; ?>% utilisation</span></div>
			<div><small>Hours remaining</small><strong><?php echo esc_html( co_ma_hours( $total_purchased - $total_used ) ); ?></strong><span>Available balance</span></div>
		</section>

		<div class="co-ma-layout">
			<aside class="co-ma-clients"><header><h2>Clients</h2><button data-ma-open="client">+</button></header><?php if ( $clients ) : foreach ( $clients as $client ) : $client_used = co_ma_current_used( $client->ID ); $client_hours = co_ma_allowance( $client->ID ); ?><a class="<?php echo $selected_id === $client->ID ? 'active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=ma-tracker&client=' . $client->ID ) ); ?>"><span class="co-ma-avatar"><?php echo esc_html( strtoupper( substr( $client->post_title, 0, 2 ) ) ); ?></span><span><b><?php echo esc_html( $client->post_title ); ?></b><small><?php echo esc_html( co_ma_hours( $client_hours - $client_used ) ); ?> of <?php echo esc_html( co_ma_hours( $client_hours ) ); ?> hrs left</small></span><i style="--usage:<?php echo $client_hours ? esc_attr( min( 100, $client_used / $client_hours * 100 ) ) : 0; ?>%"></i></a><?php endforeach; else : ?><p class="co-ma-empty">Add your first MA client to begin.</p><?php endif; ?></aside>

			<main class="co-ma-main"><?php if ( $selected ) : ?>
				<section class="co-ma-client-head"><div><span class="co-ma-avatar large"><?php echo esc_html( strtoupper( substr( $selected->post_title, 0, 2 ) ) ); ?></span><div><small>CLIENT PACKAGE</small><h2><?php echo esc_html( $selected->post_title ); ?></h2><p><?php echo esc_html( co_ma_meta( $selected_id, 'contact_name' ) ); ?><?php echo co_ma_meta( $selected_id, 'contact_email' ) ? ' · ' . esc_html( co_ma_meta( $selected_id, 'contact_email' ) ) : ''; ?></p></div></div><div class="co-ma-actions"><a class="co-ma-btn co-ma-btn-light" target="_blank" rel="noopener" href="<?php echo esc_url( co_ma_report_url( $selected_id ) ); ?>">Open report ↗</a><button class="co-ma-btn co-ma-btn-light co-ma-copy" type="button" data-copy="<?php echo esc_url( co_ma_report_url( $selected_id ) ); ?>">Copy report link</button><button class="co-ma-btn co-ma-btn-light" data-ma-open="project">+ Add project</button></div></section>
				<section class="co-ma-balance"><div class="co-ma-ring" style="--progress:<?php echo $purchased ? esc_attr( min( 100, $used / $purchased * 100 ) ) : 0; ?>%"><span><strong><?php echo esc_html( co_ma_hours( max( 0, $remaining ) ) ); ?></strong><small>hours left</small></span></div><div><small>PACKAGE USAGE</small><h3><?php echo esc_html( co_ma_hours( $used ) ); ?> of <?php echo esc_html( co_ma_hours( $purchased ) ); ?> hours used</h3><div class="co-ma-bar"><i style="width:<?php echo $purchased ? esc_attr( min( 100, $used / $purchased * 100 ) ) : 0; ?>%"></i></div><p><?php echo $remaining < 0 ? '<b class="over">Over package by ' . esc_html( co_ma_hours( abs( $remaining ) ) ) . ' hours</b>' : esc_html( round( $purchased ? $used / $purchased * 100 : 0 ) ) . '% of package consumed'; ?></p></div><dl><div><dt>Start date</dt><dd><?php echo esc_html( co_ma_date( co_ma_meta( $selected_id, 'start_date' ) ) ); ?></dd></div><div><dt>Expiry</dt><dd><?php echo esc_html( co_ma_date( co_ma_meta( $selected_id, 'expiry_date' ) ) ); ?></dd></div></dl></section>

				<section class="co-ma-section"><header><div><h2>Projects</h2><p><?php echo 'monthly_retainer' === co_ma_package_type( $selected_id ) ? 'Hours used this month by project' : 'Hours used by project'; ?></p></div><button class="co-ma-link" data-ma-open="project">+ Add project</button></header><div class="co-ma-projects"><?php if ( $projects ) : foreach ( $projects as $project ) : $project_hours = co_ma_used_hours( $selected_id, $project->ID, 'monthly_retainer' === co_ma_package_type( $selected_id ) ? wp_date( 'Y-m' ) : '' ); $crm_project_id = absint( co_ma_meta( $project->ID, 'crm_project_id' ) ); ?><article><span class="co-ma-project-icon">P</span><div><h3><?php echo esc_html( $project->post_title ); ?></h3><p><?php echo esc_html( co_ma_meta( $project->ID, 'description', 'No description' ) ); ?></p><?php if ( $crm_project_id ) : ?><a class="co-ma-pipeline-link" href="<?php echo esc_url( admin_url( 'admin.php?page=co-projects&open_project=' . $crm_project_id ) ); ?>">Linked to Pipeline →</a><?php endif; ?></div><strong><?php echo esc_html( co_ma_hours( $project_hours ) ); ?><small>hours</small></strong></article><?php endforeach; else : ?><p class="co-ma-empty">No projects yet. Add Project A, Project B or any client workstream.</p><?php endif; ?></div></section>

				<section class="co-ma-section"><header><div><h2>Work history</h2><p>Detailed tasks and time entries</p></div><button class="co-ma-btn" data-ma-open="worklog">+ Log work</button></header><div class="co-ma-table-wrap"><table class="co-ma-table"><thead><tr><th>Date</th><th>Task</th><th>Project</th><th>Person</th><th>Hours</th><th></th></tr></thead><tbody><?php if ( $logs ) : foreach ( $logs as $log ) : $project = get_post( absint( co_ma_meta( $log->ID, 'project_id' ) ) ); ?><tr><td><?php echo esc_html( co_ma_date( co_ma_meta( $log->ID, 'work_date' ) ) ); ?></td><td><strong><?php echo esc_html( $log->post_title ); ?></strong><small><?php echo esc_html( co_ma_meta( $log->ID, 'description' ) ); ?></small></td><td><span class="co-ma-tag"><?php echo esc_html( $project ? $project->post_title : '—' ); ?></span></td><td><?php echo esc_html( co_ma_meta( $log->ID, 'assignee', '—' ) ); ?></td><td><b><?php echo esc_html( co_ma_hours( co_ma_meta( $log->ID, 'hours', 0 ) ) ); ?>h</b></td><td><a class="co-ma-delete" onclick="return confirm('Move this work log to trash?')" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=co_ma_delete_log&log_id=' . $log->ID ), 'co_ma_delete_' . $log->ID ) ); ?>">×</a></td></tr><?php endforeach; else : ?><tr><td colspan="6" class="co-ma-empty">No work has been logged yet.</td></tr><?php endif; ?></tbody></table></div></section>
				<footer class="co-ma-dashboard-footer"><a class="co-ma-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=ma-client-detail&client=' . $selected_id ) ); ?>">View full client details →</a></footer>
			<?php else : ?><section class="co-ma-welcome"><span>◷</span><h2>Create your first MA package</h2><p>Add a client and purchased hours, then create projects and log work as it happens.</p><button class="co-ma-btn" data-ma-open="client">Create package</button></section><?php endif; ?></main>
		</div>
	</div>
	<?php co_ma_modals( $clients, $projects, $selected_id, $frontend ); co_ma_assets();
}

function co_ma_modals( $clients, $projects, $selected_id, $frontend = false ) { $action = esc_url( admin_url( 'admin-post.php' ) ); $return_url = $frontend ? add_query_arg( 'client', $selected_id, home_url( '/ma-tracker/' ) ) : ( ( $_GET['page'] ?? '' ) === 'ma-client-detail' ? admin_url( 'admin.php?page=ma-client-detail&client=' . $selected_id ) : '' ); $pipeline_projects = co_ma_available_pipeline_projects(); $all_ma_projects = co_ma_projects(); ?>
	<div class="co-ma-modal" data-ma-modal="client"><div><button class="co-ma-close">×</button><span class="co-ma-kicker">New client package</span><h2>Add MA package</h2><form method="post" action="<?php echo $action; ?>"><?php wp_nonce_field( 'co_ma_save' ); ?><input type="hidden" name="action" value="co_ma_save"><input type="hidden" name="entity" value="client"><input type="hidden" name="return_url" value="<?php echo esc_url( $return_url ); ?>"><label>Company name *<input required name="company_name"></label><div class="co-ma-form-grid"><label>Purchased hours *<input required min="0.25" step="0.25" type="number" name="purchased_hours"></label><label>Contact name<input name="contact_name"></label><label>Email<input type="email" name="contact_email"></label><label>Start date<input type="date" name="start_date" value="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>"></label><fieldset class="co-ma-duration"><legend>Package duration *</legend><label><input type="radio" name="duration" value="3m" checked><span>3 months</span></label><label><input type="radio" name="duration" value="6m"><span>6 months</span></label><label><input type="radio" name="duration" value="1y"><span>1 year</span></label></fieldset></div><div class="co-ma-form-actions"><button class="co-ma-btn co-ma-btn-light co-ma-cancel" type="button">Cancel</button><button class="co-ma-btn" type="submit">Create package</button></div></form></div></div>
	<div class="co-ma-modal" data-ma-modal="project"><div><button class="co-ma-close">×</button><span class="co-ma-kicker">New workstream</span><h2>Add or connect project</h2><form method="post" action="<?php echo $action; ?>"><?php wp_nonce_field( 'co_ma_save' ); ?><input type="hidden" name="action" value="co_ma_save"><input type="hidden" name="entity" value="project"><input type="hidden" name="return_url" value="<?php echo esc_url( $return_url ); ?>"><label>Client *<select required name="client_id" id="co-ma-project-client"><?php foreach ( $clients as $client ) : ?><option value="<?php echo $client->ID; ?>" <?php selected( $selected_id, $client->ID ); ?>><?php echo esc_html( $client->post_title ); ?></option><?php endforeach; ?></select></label><label class="co-ma-pipeline-source">Project source<select name="pipeline_project_id" id="co-ma-pipeline-project"><option value="">Create a manual workstream</option><?php foreach ( $pipeline_projects as $pipeline_project ) : $company = co_crm_meta( $pipeline_project->ID, 'company' ); ?><option value="<?php echo $pipeline_project->ID; ?>" data-title="<?php echo esc_attr( $pipeline_project->post_title ); ?>" data-description="<?php echo esc_attr( co_crm_project_brief( $pipeline_project->ID ) ?: co_crm_meta( $pipeline_project->ID, 'current_task' ) ); ?>"><?php echo esc_html( ( $company ?: 'Client' ) . ' — ' . $pipeline_project->post_title ); ?></option><?php endforeach; ?></select><small>Shows every unlinked Pipeline project in MA status. The source company is included in each option.</small></label><label class="co-ma-link-target" hidden>Connect to<select name="ma_project_target" id="co-ma-project-target"><option value="">Choose how to connect</option><option value="new">Create a new MA workstream</option><?php foreach ( $all_ma_projects as $ma_project ) : if ( co_ma_meta( $ma_project->ID, 'crm_project_id' ) ) continue; ?><option value="<?php echo $ma_project->ID; ?>" data-client="<?php echo absint( co_ma_meta( $ma_project->ID, 'client_id' ) ); ?>">Use existing — <?php echo esc_html( $ma_project->post_title ); ?></option><?php endforeach; ?></select><small>Choose an existing workstream to keep its work logs and hours without creating a duplicate.</small></label><label class="co-ma-project-name">Project name *<input required name="project_name" placeholder="e.g. Website maintenance"></label><label class="co-ma-project-description">Description<textarea name="description" rows="4"></textarea></label><div class="co-ma-form-actions"><button class="co-ma-btn co-ma-btn-light co-ma-cancel" type="button">Cancel</button><button class="co-ma-btn" type="submit">Add project</button></div></form></div></div>
	<div class="co-ma-modal" data-ma-modal="worklog"><div><button class="co-ma-close">×</button><span class="co-ma-kicker">Time entry</span><h2>Log completed work</h2><form method="post" action="<?php echo $action; ?>"><?php wp_nonce_field( 'co_ma_save' ); ?><input type="hidden" name="action" value="co_ma_save"><input type="hidden" name="entity" value="worklog"><div class="co-ma-form-grid"><label>Client *<select required name="client_id" id="co-ma-client-select"><?php foreach ( $clients as $client ) : ?><option value="<?php echo $client->ID; ?>" <?php selected( $selected_id, $client->ID ); ?>><?php echo esc_html( $client->post_title ); ?></option><?php endforeach; ?></select></label><label>Project *<select required name="project_id" id="co-ma-project-select"><?php foreach ( co_ma_projects() as $project ) : ?><option data-client="<?php echo absint( co_ma_meta( $project->ID, 'client_id' ) ); ?>" value="<?php echo $project->ID; ?>"><?php echo esc_html( $project->post_title ); ?></option><?php endforeach; ?></select></label><label>Work date *<input required type="date" name="work_date" value="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>"></label><label>Hours used *<input required min="0.25" step="0.25" type="number" name="hours" placeholder="1.5"></label></div><label>Task title *<input required name="task_title" placeholder="e.g. Update homepage banner"></label><label>Work details<textarea name="description" rows="4" placeholder="What was completed?"></textarea></label><label>Team member<input name="assignee" value="<?php echo esc_attr( wp_get_current_user()->display_name ); ?>"></label><label class="co-ma-check"><input type="checkbox" name="billable" checked> Deduct this time from the MA package</label><button class="co-ma-btn" type="submit">Save work log</button></form></div></div>
	<?php }

function co_ma_assets() { ?>
	<style><?php include CO_THEME_DIR . '/assets/ma-tracker.css'; include CO_THEME_DIR . '/assets/ma-tracker-overrides.css'; ?></style>
	<script>document.addEventListener('DOMContentLoaded',()=>{const modals=document.querySelectorAll('[data-ma-modal]'),close=m=>m?.classList.remove('open');document.querySelectorAll('[data-ma-open]').forEach(b=>b.addEventListener('click',()=>document.querySelector('[data-ma-modal="'+b.dataset.maOpen+'"]').classList.add('open')));document.querySelectorAll('.co-ma-close,.co-ma-cancel').forEach(b=>b.addEventListener('click',()=>close(b.closest('.co-ma-modal'))));modals.forEach(m=>m.addEventListener('click',e=>{if(e.target===m)close(m)}));document.querySelectorAll('.co-ma-copy').forEach(b=>b.addEventListener('click',async()=>{const label=b.textContent;let copied=false;try{if(navigator.clipboard&&window.isSecureContext){await navigator.clipboard.writeText(b.dataset.copy);copied=true}}catch(e){}if(!copied){const t=document.createElement('textarea');t.value=b.dataset.copy;t.setAttribute('readonly','');t.style.cssText='position:fixed;left:-9999px';document.body.appendChild(t);t.select();try{copied=document.execCommand('copy')}catch(e){}t.remove()}b.textContent=copied?'Report link copied ✓':'Copy failed — open report';setTimeout(()=>b.textContent=label,2200)}));if(location.pathname.includes('/ma-tracker'))document.querySelectorAll('.co-ma-modal form').forEach(f=>{let i=f.querySelector('[name=return_url]');if(!i){i=document.createElement('input');i.type='hidden';i.name='return_url';f.appendChild(i)}i.value=location.href});const c=document.querySelector('#co-ma-client-select'),p=document.querySelector('#co-ma-project-select');function filter(){if(!c||!p)return;let first=null;[...p.options].forEach(o=>{o.hidden=o.dataset.client!==c.value;if(!o.hidden&&!first)first=o});if(p.selectedOptions[0]?.hidden&&first)first.selected=true}c?.addEventListener('change',filter);filter()});</script>
	<script>document.addEventListener('DOMContentLoaded',()=>{if(new URLSearchParams(location.search).get('page')==='ma-client-detail')document.querySelectorAll('.co-ma-modal form').forEach(f=>{let i=f.querySelector('[name=return_url]');if(!i){i=document.createElement('input');i.type='hidden';i.name='return_url';f.appendChild(i)}i.value=location.href})});</script>
	<script>document.addEventListener('DOMContentLoaded',()=>{const f=document.querySelector('[data-ma-modal="client"] form');if(!f||f.querySelector('[name=package_type]'))return;const first=f.querySelector('label'),hours=f.querySelector('[name=purchased_hours]')?.closest('label'),box=document.createElement('fieldset');box.className='co-ma-package-types';box.innerHTML='<label><input type="radio" name="package_type" value="hour_bank" checked><span><b>Hour bank</b><small>One balance used until depleted</small></span></label><label><input type="radio" name="package_type" value="monthly_retainer"><span><b>Monthly retainer</b><small>Fresh allowance every month</small></span></label>';first.before(box);box.addEventListener('change',e=>{if(e.target.name==='package_type'&&hours)hours.childNodes[0].textContent=e.target.value==='monthly_retainer'?'Hours per month *':'Purchased hours *'})});</script>
	<script>document.addEventListener('DOMContentLoaded',()=>{const modal=document.querySelector('[data-ma-modal="project"]'),source=modal?.querySelector('#co-ma-pipeline-project'),client=modal?.querySelector('#co-ma-project-client'),target=modal?.querySelector('#co-ma-project-target'),targetLabel=modal?.querySelector('.co-ma-link-target'),name=modal?.querySelector('[name="project_name"]'),description=modal?.querySelector('[name="description"]'),submit=modal?.querySelector('[type="submit"]');if(!source)return;const filterTargets=()=>{if(!target||!client)return;[...target.options].forEach(o=>{if(!o.dataset.client)return;o.hidden=o.dataset.client!==client.value});if(target.selectedOptions[0]?.hidden)target.value=''};const refresh=()=>{const option=source.selectedOptions[0],linked=!!option?.value;if(targetLabel)targetLabel.hidden=!linked;if(target)target.required=linked;if(linked){if(name)name.value=option.dataset.title||'';if(description)description.value=option.dataset.description||''}else if(target){target.value=''}filterTargets();const existing=linked&&target&&target.value&&target.value!=='new';if(name){name.required=!existing;name.closest('label').hidden=existing}if(description)description.closest('label').hidden=existing;if(submit)submit.textContent=existing?'Connect project':'Add project'};source.addEventListener('change',()=>{if(target)target.value='';refresh()});client?.addEventListener('change',()=>{if(target)target.value='';filterTargets();refresh()});target?.addEventListener('change',refresh);refresh()});</script>
	<script>document.addEventListener('DOMContentLoaded',()=>document.querySelectorAll('.co-ma-extend button[name="extension"]').forEach(button=>button.addEventListener('click',event=>{const duration=button.textContent.replace('+','').trim();if(!window.confirm('Extend this contract by '+duration+'?\n\nThe contract expiry date will be updated.'))event.preventDefault()})));</script>
	<?php }

add_action( 'template_redirect', function () {
	if ( get_query_var( 'co_ma_calendar' ) ) co_ma_render_calendar_feed();
	if ( get_query_var( 'co_ma_dashboard' ) ) {
		if ( ! is_user_logged_in() ) { auth_redirect(); exit; }
		wp_safe_redirect( admin_url( 'admin.php?page=ma-tracker' ) ); exit;
	}
	$token = sanitize_text_field( get_query_var( 'co_ma_report' ) );
	if ( $token ) { co_ma_render_customer_report( $token ); exit; }
} );

function co_ma_render_customer_report( $token ) {
	$clients = get_posts( array( 'post_type' => 'ma_client', 'post_status' => 'publish', 'numberposts' => 1, 'meta_key' => '_ma_share_token', 'meta_value' => $token ) );
	if ( ! $clients ) { status_header( 404 ); echo 'Report not found.'; return; }
	$client = $clients[0]; $id = $client->ID; $purchased = co_ma_allowance( $id ); $package_type = co_ma_package_type( $id ); $used = co_ma_current_used( $id ); $remaining = $purchased - $used; $projects = co_ma_projects( $id ); $logs = co_ma_logs( $id ); $history = co_ma_monthly_history( $id );
	status_header( 200 ); nocache_headers();
	echo '<!doctype html><html ' . get_language_attributes() . '><head><meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html( $client->post_title ) . ' — MA Report</title>'; wp_head(); echo '</head><body class="co-ma-report-page"><main class="co-ma-report">';
	echo '<header class="co-ma-report-hero"><span>MA SERVICE REPORT</span><h1>' . esc_html( $client->post_title ) . '</h1><p>Maintenance package usage and completed work</p><button onclick="window.print()">Print report</button></header>';
	echo '<section class="co-ma-report-summary"><div><small>Package</small><strong>' . esc_html( 'monthly_retainer' === $package_type ? co_ma_hours( $purchased ) . 'h / month' : co_ma_hours( $purchased ) . 'h bank' ) . '</strong></div><div><small>' . ( 'monthly_retainer' === $package_type ? 'Used this month' : 'Used' ) . '</small><strong>' . esc_html( co_ma_hours( $used ) ) . 'h</strong></div><div><small>' . ( 'monthly_retainer' === $package_type ? 'Remaining this month' : 'Remaining' ) . '</small><strong>' . esc_html( co_ma_hours( $remaining ) ) . 'h</strong></div><div><small>Contract period</small><strong>' . esc_html( co_ma_date( co_ma_meta( $id, 'start_date' ) ) ) . ' — ' . esc_html( co_ma_date( co_ma_meta( $id, 'expiry_date' ) ) ) . '</strong></div></section>';
	echo '<section class="co-ma-report-card"><header><div><h2>Package usage</h2><p>' . esc_html( round( $purchased ? $used / $purchased * 100 : 0 ) ) . '% consumed</p></div><b>' . esc_html( co_ma_hours( max( 0, $remaining ) ) ) . ' hours left</b></header><div class="co-ma-bar"><i style="width:' . esc_attr( min( 100, $purchased ? $used / $purchased * 100 : 0 ) ) . '%"></i></div></section>';
	echo '<section class="co-ma-report-card"><header><div><h2>Projects</h2><p>' . ( 'monthly_retainer' === $package_type ? 'Hours used this month by workstream' : 'Hours used by workstream' ) . '</p></div></header><div class="co-ma-projects">'; foreach ( $projects as $project ) echo '<article><span class="co-ma-project-icon">P</span><div><h3>' . esc_html( $project->post_title ) . '</h3><p>' . esc_html( co_ma_meta( $project->ID, 'description' ) ) . '</p></div><strong>' . esc_html( co_ma_hours( co_ma_used_hours( $id, $project->ID, 'monthly_retainer' === $package_type ? wp_date( 'Y-m' ) : '' ) ) ) . '<small>hours</small></strong></article>'; echo '</div></section>';
	if ( 'monthly_retainer' === $package_type ) { echo '<section class="co-ma-report-card"><header><div><h2>Monthly usage history</h2><p>Allowance resets monthly; unused hours do not roll over</p></div></header><div class="co-ma-months">'; foreach ( $history as $month => $month_used ) echo '<article><small>' . esc_html( wp_date( 'F Y', strtotime( $month . '-01' ) ) ) . '</small><strong>' . esc_html( co_ma_hours( $month_used ) ) . 'h</strong><span>' . esc_html( co_ma_hours( max( 0, $purchased - $month_used ) ) ) . 'h remaining</span><div class="co-ma-bar"><i style="width:' . esc_attr( min( 100, $purchased ? $month_used / $purchased * 100 : 0 ) ) . '%"></i></div></article>'; echo '</div></section>'; }
	echo '<section class="co-ma-report-card"><header><div><h2>Completed work</h2><p>Detailed maintenance history</p></div></header><div class="co-ma-table-wrap"><table class="co-ma-table"><thead><tr><th>Date</th><th>Task</th><th>Project</th><th>Hours</th></tr></thead><tbody>'; foreach ( $logs as $log ) { $project = get_post( absint( co_ma_meta( $log->ID, 'project_id' ) ) ); echo '<tr><td>' . esc_html( co_ma_date( co_ma_meta( $log->ID, 'work_date' ) ) ) . '</td><td><strong>' . esc_html( $log->post_title ) . '</strong><small>' . esc_html( co_ma_meta( $log->ID, 'description' ) ) . '</small></td><td><span class="co-ma-tag">' . esc_html( $project ? $project->post_title : '—' ) . '</span></td><td><b>' . esc_html( co_ma_hours( co_ma_meta( $log->ID, 'hours', 0 ) ) ) . 'h</b></td></tr>'; } echo '</tbody></table></div></section>';
	echo '<footer>Last updated ' . esc_html( wp_date( 'j F Y, H:i' ) ) . '</footer></main>'; co_ma_assets(); wp_footer(); echo '</body></html>';
}

add_filter( 'manage_ma_client_posts_columns', function () { return array( 'cb' => '<input type="checkbox">', 'title' => 'Company', 'ma_hours' => 'Package hours', 'ma_used' => 'Used', 'ma_left' => 'Remaining', 'date' => 'Created' ); } );
add_action( 'manage_ma_client_posts_custom_column', function ( $column, $id ) { $hours = co_ma_allowance( $id ); $used = co_ma_current_used( $id ); if ( 'ma_hours' === $column ) echo esc_html( co_ma_hours( $hours ) ) . ( 'monthly_retainer' === co_ma_package_type( $id ) ? ' / month' : '' ); if ( 'ma_used' === $column ) echo esc_html( co_ma_hours( $used ) ); if ( 'ma_left' === $column ) echo esc_html( co_ma_hours( $hours - $used ) ); }, 10, 2 );
