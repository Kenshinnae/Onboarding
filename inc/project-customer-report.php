<?php
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/report-design.php';
require_once __DIR__ . '/project-customer-view.php';

add_filter( 'query_vars', function ( $vars ) { $vars[] = 'co_project_report'; return $vars; } );
add_action( 'template_redirect', function () {
	$token = get_query_var( 'co_project_report' );
	if ( ! $token ) return;
	co_crm_render_customer_project_report( $token, absint( $_GET['document'] ?? 0 ) ); exit;
} );

// Existing report bookmarks now open the customer page before wp-admin emits HTML.
add_action( 'admin_init', function () {
	if ( 'co-project-overview' !== ( $_GET['page'] ?? '' ) ) return;
	try { $token = co_crm_project_share_token( absint( $_GET['project'] ?? 0 ) ); } catch ( RuntimeException $error ) { wp_die( esc_html( $error->getMessage() ) ); }
	wp_safe_redirect( co_crm_customer_report_url( $token ) ); exit;
} );

function co_crm_require_report_admin( $project ) {
	if ( ! current_user_can( 'edit_posts' ) || ! current_user_can( 'edit_post', $project ) || 'client_project' !== get_post_type( $project ) || 'publish' !== get_post_status( $project ) ) throw new RuntimeException( 'Permission denied.' );
}

function co_crm_project_share_token( $project, $rotate = false ) {
	co_crm_require_report_admin( $project );
	return co_project_locked( 'project_report_token_' . $project, function () use ( $project, $rotate ) {
		$token = (string) co_crm_meta( $project, 'customer_report_token' );
		if ( $rotate || ! preg_match( '/^[a-f0-9]{64}$/D', $token ) ) {
			$token = bin2hex( random_bytes( 32 ) );
			update_post_meta( $project, '_crm_customer_report_token', $token );
			if ( co_crm_meta( $project, 'customer_report_token' ) !== $token ) throw new RuntimeException( 'Unable to save report link.' );
		}
		return $token;
	} );
}

function co_crm_customer_report_url( $token, $document = 0 ) {
	$url = home_url( '/?co_project_report=' . rawurlencode( $token ) );
	return $document ? add_query_arg( 'document', absint( $document ), $url ) : $url;
}

function co_crm_customer_report_project( $token ) {
	if ( ! is_string( $token ) || ! preg_match( '/^[a-f0-9]{64}$/D', $token ) ) return 0;
	$projects = get_posts( array( 'post_type' => 'client_project', 'post_status' => 'publish', 'numberposts' => 1, 'meta_key' => '_crm_customer_report_token', 'meta_value' => $token ) );
	if ( ! $projects || ! hash_equals( (string) co_crm_meta( $projects[0]->ID, 'customer_report_token' ), $token ) ) return 0;
	return $projects[0]->ID;
}

add_action( 'admin_post_co_project_customer_report', function () {
	$project = absint( $_GET['project'] ?? 0 ); check_admin_referer( 'co_project_customer_report_' . $project );
	try { $token = co_crm_project_share_token( $project ); } catch ( RuntimeException $error ) { wp_die( esc_html( $error->getMessage() ) ); }
	wp_safe_redirect( co_crm_customer_report_url( $token ) ); exit;
} );

add_action( 'wp_ajax_co_project_customer_report_link', function () {
	$project = absint( $_POST['project'] ?? 0 );
	if ( ! check_ajax_referer( 'co_project_customer_report_' . $project, 'nonce', false ) ) wp_send_json_error( array( 'message' => 'Invalid nonce.' ), 403 );
	try { $token = co_crm_project_share_token( $project ); } catch ( RuntimeException $error ) { wp_send_json_error( array( 'message' => $error->getMessage() ), 403 ); }
	wp_send_json_success( array( 'url' => co_crm_customer_report_url( $token ) ) );
} );

function co_crm_customer_assets( $project ) {
	$assets = co_crm_project_assets( $project );
	if ( '1' === (string) co_crm_meta( $project, 'customer_assets_configured' ) ) return array_intersect_key( $assets, array_flip( array_map( 'absint', (array) co_crm_meta( $project, 'customer_asset_ids', array() ) ) ) );
	return array_filter( $assets, fn( $source ) => 0 === strpos( $source, 'Onboarding' ) );
}

add_action( 'admin_post_co_project_customer_report_settings', function () {
	$project = absint( $_POST['project'] ?? $_GET['project'] ?? 0 ); check_admin_referer( 'co_project_customer_report_' . $project );
	try {
		co_crm_require_report_admin( $project );
		if ( ! empty( $_POST['rotate_link'] ) ) co_crm_project_share_token( $project, true );
		else {
			$selected = array_map( 'absint', (array) ( $_POST['customer_asset_ids'] ?? array() ) );
			$selected = array_values( array_intersect( $selected, array_keys( co_crm_project_assets( $project ) ) ) );
			update_post_meta( $project, '_crm_customer_asset_ids', $selected ); update_post_meta( $project, '_crm_customer_assets_configured', '1' );
		}
	} catch ( RuntimeException $error ) { wp_die( esc_html( $error->getMessage() ) ); }
	wp_safe_redirect( admin_url( 'admin.php?page=co-projects&open_project=' . $project . '&report_settings_saved=1' ) ); exit;
} );

function co_crm_render_customer_report_controls( $project ) {
	$nonce = 'co_project_customer_report_' . $project; $assets = co_crm_project_assets( $project ); $shared = co_crm_customer_assets( $project ); ?>
	<section class="crm-customer-report-controls"><div class="crm-customer-report-actions"><a class="crm-btn crm-project-report-link" target="_blank" rel="noopener" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=co_project_customer_report&project=' . $project ), $nonce ) ); ?>">เปิดรายงานลูกค้า ↗</a><button class="crm-btn ghost-dark" type="button" data-copy-project-report="<?php echo $project; ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( $nonce ) ); ?>" data-endpoint="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">คัดลอกลิงก์รายงาน</button><span data-report-copy-status role="status"></span></div><p>ลูกค้าเปิดได้โดยไม่ต้องล็อกอิน ผู้ที่มีลิงก์จะเห็นข้อมูลและเอกสารของ Project นี้ ไม่แสดง Internal notes</p>
	<?php if ( ! empty( $_GET['report_settings_saved'] ) && absint( $_GET['open_project'] ?? 0 ) === $project ) : ?><p role="status">บันทึกการแชร์รายงานแล้ว</p><?php endif; ?>
	<details><summary>ไฟล์ที่ให้ลูกค้าดู / เปลี่ยนลิงก์</summary><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( $nonce ); ?><input type="hidden" name="action" value="co_project_customer_report_settings"><input type="hidden" name="project" value="<?php echo $project; ?>"><p>ไฟล์ Onboarding แสดงเริ่มต้น ส่วนไฟล์ที่เพิ่มในหลังบ้านต้องเลือกก่อนแชร์</p><?php foreach ( $assets as $asset => $source ) : ?><label class="crm-report-asset-choice"><input type="checkbox" name="customer_asset_ids[]" value="<?php echo $asset; ?>" <?php echo isset( $shared[$asset] ) ? 'checked' : ''; ?>><span><?php echo esc_html( get_the_title( $asset ) . ' · ' . $source ); ?></span></label><?php endforeach; ?><?php if ( ! $assets ) : ?><p>ยังไม่มีไฟล์ใน Project นี้</p><?php endif; ?><button class="crm-btn" type="submit">บันทึกไฟล์ที่แชร์</button></form><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( $nonce ); ?><input type="hidden" name="action" value="co_project_customer_report_settings"><input type="hidden" name="project" value="<?php echo $project; ?>"><button class="crm-btn ghost-dark" name="rotate_link" value="1" type="submit" onclick="return confirm('เปลี่ยนลิงก์รายงาน? ลิงก์เดิมและลิงก์เอกสารเดิมจะเปิดไม่ได้ ต้องส่งลิงก์ใหม่ให้ลูกค้า')">เปลี่ยนลิงก์ — ยกเลิกลิงก์เดิม</button></form></details></section>
<?php }

function co_crm_customer_document_url( $token, $document, $documents ) {
	return in_array( absint( $document ), array_column( $documents, 'ID' ), true ) ? co_crm_customer_report_url( $token, $document ) : '';
}

function co_crm_customer_doc_link( $token, $id, $documents ) {
	$url = co_crm_customer_document_url( $token, $id, $documents );
	return $url ? '<a target="_blank" rel="noopener" href="' . esc_url( $url ) . '">' . esc_html( co_sales_meta( $id, 'number' ) ) . ' ↗</a>' : '';
}

function co_crm_customer_installments( $quote, $docs, $token ) {
	if ( ! $quote ) { echo '<p class="customer-empty">Payment milestones will appear once the main quotation is confirmed.</p>'; return; }
	$progress = co_sales_quote_progress( $quote ); $allowed = array_column( $docs, 'ID' );
	foreach ( $progress['schedule'] as $index => $row ) {
		$amount = co_sales_installment_amounts( $quote, $index + 1 );
		echo '<article class="customer-installment"><header><div><small>INSTALLMENT ' . ( $index + 1 ) . ' · ' . esc_html( $row['percent'] ) . '%</small><h3>' . esc_html( $row['description'] ) . '</h3></div><strong>฿' . esc_html( co_sales_money( $amount['net'] ) ) . '</strong></header><p class="customer-muted">Before withholding ฿' . esc_html( co_sales_money( $amount['subtotal'] ) ) . ' · Withholding ฿' . esc_html( co_sales_money( $amount['wht'] ) ) . '</p>';
		$invoices = array_filter( $progress['invoices'][$index + 1] ?? array(), fn( $invoice ) => in_array( $invoice['id'], $allowed, true ) );
		if ( ! $invoices ) echo '<span class="customer-badge">Invoice not issued yet</span>';
		foreach ( $invoices as $invoice ) {
			echo '<div class="customer-doc-chain">' . co_crm_customer_doc_link( $token, $invoice['id'], $docs );
			$matched = $invoice['payment'] && co_sales_payment_matches_invoice( $invoice['id'] );
			echo '<span class="customer-badge ' . ( $matched ? 'complete' : '' ) . '">' . ( $matched ? 'Payment received' : 'Awaiting payment / verification' ) . '</span>';
			if ( $matched ) echo '<small>' . esc_html( co_sales_date( co_crm_meta( $invoice['payment'], 'paid_date' ) ) ) . '</small>';
			if ( $invoice['receipt'] ) echo co_crm_customer_doc_link( $token, $invoice['receipt'], $docs );
			elseif ( $matched ) echo '<small>Receipt not issued yet</small>';
			echo '</div>';
		}
		echo '</article>';
	}
}

function co_crm_customer_ma( $data ) {
	$client = $data['ma_client']; $stream = $data['ma_stream'];
	if ( ! $client || ! $stream ) { echo '<p class="customer-empty">Maintenance has not started or has not been linked to this project yet.</p>'; return; }
	$monthly = 'monthly_retainer' === co_ma_package_type( $client ); $allowance = co_ma_allowance( $client ); $used = co_ma_current_used( $client ); $own = co_ma_used_hours( $client, $stream, $monthly ? wp_date( 'Y-m' ) : '' );
	echo '<div class="customer-ma-summary"><div><small>Package</small><strong>' . esc_html( co_ma_hours( $allowance ) ) . ( $monthly ? 'h / month' : 'h bank' ) . '</strong></div><div><small>' . ( $monthly ? 'This project · this month' : 'This project · total used' ) . '</small><strong>' . esc_html( co_ma_hours( $own ) ) . 'h</strong></div><div><small>' . ( $monthly ? 'Package left · this month' : 'Package remaining' ) . '</small><strong>' . esc_html( co_ma_hours( $allowance - $used ) ) . 'h</strong></div><div><small>Service period</small><strong class="customer-period">' . esc_html( co_ma_date( co_ma_meta( $client, 'start_date' ) ) ) . '<br>' . esc_html( co_ma_date( co_ma_meta( $client, 'expiry_date' ) ) ) . '</strong></div></div><p class="customer-muted">' . ( $monthly ? 'Allowance resets each month; unused hours do not roll over.' : 'Hour bank usage is deducted from the total allowance.' ) . ' The remaining balance is shared across the package. Only billable entries use the allowance.</p>';
	$months = array(); foreach ( $data['ma_logs'] as $log ) { $date = co_ma_meta( $log->ID, 'work_date' ); if ( co_sales_valid_date( $date ) ) $months[substr( $date, 0, 7 )] = true; }
	$start = co_ma_meta( $client, 'start_date' ); $expiry = co_ma_meta( $client, 'expiry_date' );
	if ( $monthly && co_sales_valid_date( $start ) ) {
		$cursor = new DateTimeImmutable( substr( $start, 0, 7 ) . '-01' ); $end = min( wp_date( 'Y-m' ), co_sales_valid_date( $expiry ) ? substr( $expiry, 0, 7 ) : wp_date( 'Y-m' ) );
		for ( $count = 0; $cursor->format( 'Y-m' ) <= $end && $count < 1200; $count++, $cursor = $cursor->modify( '+1 month' ) ) $months[$cursor->format( 'Y-m' )] = true;
	}
	krsort( $months );
	if ( $months ) { echo '<h3>Monthly usage <span class="customer-muted">· This project only</span></h3><div class="co-ma-months">'; foreach ( array_keys( $months ) as $month ) echo '<article><small>' . esc_html( wp_date( 'F Y', strtotime( $month . '-01' ) ) ) . '</small><strong>' . esc_html( co_ma_hours( co_ma_used_hours( $client, $stream, $month ) ) ) . 'h</strong></article>'; echo '</div>'; }
	echo '<h3>Maintenance activity</h3><div class="customer-worklogs">';
	foreach ( $data['ma_logs'] as $log ) echo '<article><header><div><small>' . esc_html( co_ma_date( co_ma_meta( $log->ID, 'work_date' ) ) ) . '</small><h4>' . esc_html( $log->post_title ) . '</h4></div><strong>' . esc_html( co_ma_hours( co_ma_meta( $log->ID, 'hours', 0 ) ) ) . 'h</strong></header><p class="customer-copy">' . esc_html( co_ma_meta( $log->ID, 'description' ) ) . '</p>' . ( '0' === (string) co_ma_meta( $log->ID, 'billable', '1' ) ? '<span class="customer-badge complete">No package hours deducted</span>' : '' ) . '</article>';
	if ( ! $data['ma_logs'] ) echo '<p class="customer-empty">No maintenance activity has been recorded yet.</p>';
	echo '</div>';
}

function co_crm_customer_report_headers( $status = 200 ) {
	status_header( $status ); nocache_headers();
	if ( ! headers_sent() ) { header( 'X-Robots-Tag: noindex, nofollow, noarchive' ); header( 'Referrer-Policy: no-referrer' ); header( "Content-Security-Policy: frame-ancestors 'self'" ); }
}

function co_crm_render_customer_project_report( $token, $document = 0 ) {
	$id = co_crm_customer_report_project( $token );
	if ( ! $id ) { co_crm_customer_report_headers( 404 ); echo '<!doctype html><html lang="th"><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><title>ไม่พบรายงาน</title><p>ไม่พบรายงาน หรือลิงก์นี้ถูกยกเลิกแล้ว กรุณาขอลิงก์ใหม่จากผู้ดูแล</p></html>'; return; }
	$data = co_crm_project_report_data( $id );
	if ( $document && ! co_crm_customer_document_url( $token, $document, $data['documents'] ) ) { co_crm_customer_report_headers( 404 ); echo '<!doctype html><html lang="th"><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><title>ไม่พบเอกสาร</title><p>ไม่พบเอกสารในรายงานนี้</p></html>'; return; }
	co_crm_customer_report_headers();
	$project = get_post( $id ); $finance = $data['finance']; $stage = co_crm_project_stage( $id, false );
	echo '<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><meta name="referrer" content="no-referrer"><title>' . esc_html( $document ? co_sales_meta( $document, 'number' ) : $project->post_title . ' — Project Report' ) . '</title><style>';
	if ( $document ) { include CO_THEME_DIR . '/assets/sales-documents.css'; include CO_THEME_DIR . '/assets/sales-print.css'; }
	else include CO_THEME_DIR . '/assets/brief-report.css';
	include CO_THEME_DIR . '/assets/project-customer-report.css';
	if ( ! $document ) include CO_THEME_DIR . '/assets/workspace-ui.css';
	echo '</style></head><body class="co-ma-report-page project-customer-page' . ( $document ? ' customer-document-page' : '' ) . '">';
	if ( $document ) {
		echo '<div class="customer-document-toolbar"><a href="' . esc_url( co_crm_customer_report_url( $token ) ) . '">← กลับรายงานโปรเจกต์</a><button type="button" onclick="window.print()">Print / Save PDF</button></div>';
		co_sales_render_paper( $document );
		echo '<script src="' . esc_url( get_template_directory_uri() . '/assets/sales-documents.js?v=' . filemtime( CO_THEME_DIR . '/assets/sales-documents.js' ) ) . '" defer></script></body></html>'; return;
	}
	co_crm_customer_report_view( $id, $data, $token );
	echo '</body></html>';
}
