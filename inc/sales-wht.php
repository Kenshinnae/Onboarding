<?php
defined( 'ABSPATH' ) || exit;

/** Use the issued snapshot, never the WordPress user or a name-based company match. */
function co_sales_document_issuer( $id ) {
	$snapshot = (array) co_sales_meta( $id, 'issuer_snapshot', array() );
	$key = (string) co_sales_meta( $id, 'issuer_id' );
	$name = $snapshot['name'] ?? '';
	if ( ! $name ) foreach ( co_sales_issuers() as $issuer ) if ( (string) $issuer['id'] === $key ) $name = $issuer['name'];
	return array( 'id' => $key ?: 'unassigned', 'name' => $name ?: 'ยังไม่ระบุผู้ออกเอกสาร' );
}

function co_sales_issuer_filters() {
	$choices = array();
	foreach ( co_sales_issuers() as $issuer ) $choices[$issuer['id']] = $issuer['name'] ?: 'ยังไม่ตั้งชื่อผู้ออกเอกสาร';
	// Historical issuers remain filterable after removal from Settings.
	foreach ( co_sales_documents() as $doc ) { $issuer = co_sales_document_issuer( $doc->ID ); if ( ! isset( $choices[$issuer['id']] ) ) $choices[$issuer['id']] = $issuer['name']; }
	return $choices;
}

function co_sales_wht_files( $invoice, $include_removed = false ) {
	return array_values( array_filter( (array) co_sales_meta( $invoice, 'wht_files', array() ), fn( $file ) => is_array( $file ) && isset( $file['id'], $file['stored'], $file['name'] ) && ( $include_removed || empty( $file['removed_at'] ) ) ) );
}

function co_sales_wht_invoices( $issuer = '', $quote = 0, $status = '' ) {
	return array_values( array_filter( co_sales_documents(), function ( $doc ) use ( $issuer, $quote, $status ) {
		if ( 'invoice' !== co_sales_meta( $doc->ID, 'document_type' ) ) return false;
		if ( $quote && absint( co_sales_meta( $doc->ID, 'source_document_id' ) ) !== absint( $quote ) ) return false;
		if ( $issuer && co_sales_document_issuer( $doc->ID )['id'] !== $issuer ) return false;
		$has_files = (bool) co_sales_wht_files( $doc->ID );
		$required = co_sales_wht( $doc->ID ) > 0 && 'cancelled' !== co_sales_meta( $doc->ID, 'status' );
		return 'attached' === $status ? $has_files : ( 'missing' === $status ? $required && ! $has_files : true );
	} ) );
}

function co_sales_filtered_documents( $type, $issuer = '' ) {
	return array_values( array_filter( co_sales_documents(), fn( $doc ) => $type === co_sales_meta( $doc->ID, 'document_type' ) && ( ! $issuer || co_sales_document_issuer( $doc->ID )['id'] === $issuer ) ) );
}

function co_sales_wht_document_invoices( $id ) {
	$type = co_sales_meta( $id, 'document_type' );
	return 'quotation' === $type ? co_sales_wht_invoices( '', $id ) : ( 'invoice' === $type ? array( get_post( $id ) ) : array() );
}

/** Resolve explicit selections without ever falling back to all documents. */
function co_sales_wht_selected_invoices( $ids, $type, $issuer = '' ) {
	if ( ! current_user_can( 'edit_posts' ) ) throw new RuntimeException( 'ไม่มีสิทธิ์ดาวน์โหลดเอกสารภาษี' );
	if ( ! is_array( $ids ) || ! $ids || count( $ids ) > 200 || ! in_array( $type, array( 'quotation', 'invoice' ), true ) ) throw new RuntimeException( 'กรุณาเลือกเอกสาร 1–200 ใบที่ต้องการดาวน์โหลด' );
	$invoices = array();
	foreach ( array_unique( array_map( 'absint', $ids ) ) as $id ) {
		if ( ! $id || 'sales_document' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) || $type !== co_sales_meta( $id, 'document_type' ) || ! current_user_can( 'edit_post', $id ) || ( $issuer && $issuer !== co_sales_document_issuer( $id )['id'] ) ) throw new RuntimeException( 'เอกสารที่เลือกไม่ตรงกับประเภทหรือผู้ออกที่กรองไว้ กรุณาเลือกใหม่' );
		foreach ( co_sales_wht_document_invoices( $id ) as $invoice ) { co_sales_wht_require_invoice( $invoice->ID ); $invoices[$invoice->ID] = $invoice; }
	}
	return array_values( $invoices );
}

function co_sales_wht_document_archive_url( $type, $issuer = '', $ids = null ) {
	$args = array( 'action' => 'co_sales_wht_archive', 'collection_mode' => null === $ids ? 'filtered' : 'selected', 'document_type' => $type, 'issuer' => $issuer );
	if ( null !== $ids ) $args['document_ids'] = array_values( $ids );
	return wp_nonce_url( admin_url( 'admin-post.php?' . http_build_query( $args ) ), 'co_sales_wht_archive' );
}

function co_sales_wht_listing_summary( $invoices ) {
	$files = $attached = $missing = 0;
	foreach ( $invoices as $invoice ) {
		$count = count( co_sales_wht_files( $invoice->ID ) ); $files += $count;
		if ( $count ) $attached++;
		elseif ( co_sales_wht( $invoice->ID ) > 0 && 'cancelled' !== co_sales_meta( $invoice->ID, 'status' ) ) $missing++;
	}
	return array( 'files' => $files, 'attached' => $attached, 'missing' => $missing );
}

/** Main document tables stay compact; file details belong inside the document. */
function co_sales_wht_listing_badge( $invoices ) {
	$summary = co_sales_wht_listing_summary( $invoices );
	$required = array_filter( $invoices, fn( $invoice ) => co_sales_wht( $invoice->ID ) > 0 && 'cancelled' !== co_sales_meta( $invoice->ID, 'status' ) );
	$state = $summary['missing'] ? 'pending' : ( $required || $summary['files'] ? 'complete' : 'neutral' );
	$label = $summary['missing'] ? 'ขาด ' . $summary['missing'] . ' ใบ' : ( $required || $summary['files'] ? 'ครบแล้ว' : ( $invoices ? 'ไม่มีใบค้าง' : 'ยังไม่มี Invoice' ) );
	echo '<span class="sales-billing-badge sales-wht-list-badge ' . esc_attr( $state ) . '" title="นับเฉพาะ Invoice ที่ออกแล้วและมียอดหัก ณ ที่จ่าย">' . esc_html( $label ) . '</span>';
}

/** Downloads are available in the list itself; opening a document is only for uploading. */
function co_sales_wht_listing_cell( $id, $invoices = null ) {
	$invoices = null === $invoices ? co_sales_wht_document_invoices( $id ) : $invoices;
	$summary = co_sales_wht_listing_summary( $invoices ); $type = co_sales_meta( $id, 'document_type' );
	echo '<div class="sales-wht-list-cell"><div class="sales-wht-list-count"><strong>' . $summary['files'] . ' ไฟล์ · ' . $summary['attached'] . '/' . count( $invoices ) . ' Invoice</strong>';
	if ( $summary['missing'] ) echo '<span class="sales-billing-badge pending">ยังขาด ' . $summary['missing'] . ' Invoice</span>';
	if ( ! $invoices ) echo '<small>ยังไม่ออก Invoice</small>';
	echo '</div><ul class="sales-wht-table-files">';
	$mismatch = false;
	foreach ( $invoices as $invoice ) {
		$files = co_sales_wht_files( $invoice->ID ); $owner = co_sales_document_issuer( $invoice->ID );
		if ( 'quotation' === $type && $owner['id'] !== co_sales_document_issuer( $id )['id'] ) $mismatch = true;
		echo '<li><small>' . esc_html( co_sales_meta( $invoice->ID, 'number' ) . ' · งวด ' . co_sales_meta( $invoice->ID, 'installment_no', 1 ) . ' · ' . $owner['name'] ) . '</small>';
		foreach ( $files as $file ) echo '<a class="sales-wht-file-download" href="' . esc_url( co_sales_wht_download_url( $invoice->ID, $file['id'] ) ) . '"><span>' . esc_html( $file['name'] ) . '</span><span aria-hidden="true">↓</span></a>';
		if ( ! $files ) echo '<span class="sales-wht-file-missing">' . ( co_sales_wht( $invoice->ID ) > 0 && 'cancelled' !== co_sales_meta( $invoice->ID, 'status' ) ? 'ยังไม่มีใบหัก ณ ที่จ่าย' : 'ไม่มีภาษีหัก / เอกสารยกเลิก' ) . '</span>';
		echo '</li>';
	}
	echo '</ul>';
	if ( $mismatch ) echo '<small class="sales-wht-owner-warning">ผู้ออก Invoice บางใบต่างจาก QO — ZIP แยกโฟลเดอร์ตามผู้ออก Invoice จริง</small>';
	if ( $summary['files'] ) echo '<a class="sales-btn light sales-wht-row-zip" href="' . esc_url( co_sales_wht_document_archive_url( $type, '', array( $id ) ) ) . '">ดาวน์โหลด ZIP ' . ( 'quotation' === $type ? 'QO' : 'Invoice' ) . ' นี้</a>';
	echo '<a class="sales-wht-manage-link" href="' . esc_url( admin_url( 'admin.php?page=co-sales-edit&document=' . $id . '#sales-wht' ) ) . '">แนบ / จัดการไฟล์ →</a></div>';
}

function co_sales_wht_bulk_toolbar( $type, $issuer, $has_files, $filtered_url = '' ) {
	?><div class="sales-wht-bulk-toolbar"><div><label class="sales-wht-select-all-label"><input type="checkbox" data-wht-select-all aria-label="เลือกทุกใบที่มีไฟล์ภาษีในตารางนี้"> เลือกทั้งหมด</label><span data-wht-selected-count aria-live="polite">เลือกแล้ว 0 ใบ</span></div><div><button class="sales-btn" type="submit" data-wht-export-selected disabled>ดาวน์โหลดที่เลือก (ZIP)</button><?php if ( $has_files ) : ?><a class="sales-btn light" title="ดาวน์โหลดทุกใบตามตัวกรองที่เลือก" href="<?php echo esc_url( $filtered_url ?: co_sales_wht_document_archive_url( $type, $issuer ) ); ?>">ดาวน์โหลดทั้งหมด (ZIP)</a><?php endif; ?></div></div><?php
}

function co_sales_wht_inside( $path, $root ) { return $path === $root || 0 === strpos( $path, rtrim( $root, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR ); }

/** Store certificates outside the web root: no Media Library or public attachment URL. */
function co_sales_wht_storage( $create = false ) {
	$wp_root = realpath( ABSPATH );
	$web_root = ! empty( $_SERVER['DOCUMENT_ROOT'] ) ? realpath( $_SERVER['DOCUMENT_ROOT'] ) : $wp_root;
	if ( ! $wp_root || ! $web_root ) throw new RuntimeException( 'ไม่สามารถตรวจสอบพื้นที่เก็บไฟล์ภาษีแบบส่วนตัวได้' );
	$target = defined( 'CO_WHT_STORAGE_DIR' ) ? CO_WHT_STORAGE_DIR : dirname( $web_root ) . '/co-private-wht-' . substr( hash( 'sha256', ABSPATH ), 0, 12 );
	$parent = realpath( dirname( $target ) );
	if ( ! $parent || is_link( $target ) || co_sales_wht_inside( $parent . '/' . basename( $target ), $wp_root ) || co_sales_wht_inside( $parent . '/' . basename( $target ), $web_root ) ) throw new RuntimeException( 'พื้นที่ไฟล์ภาษีต้องอยู่นอก web root กรุณาตั้งค่า CO_WHT_STORAGE_DIR' );
	if ( ! is_dir( $target ) && $create && ! @mkdir( $target, 0700 ) ) throw new RuntimeException( 'สร้างพื้นที่ไฟล์ส่วนตัวไม่ได้ กรุณาตั้งค่า CO_WHT_STORAGE_DIR ให้อยู่นอก web root และเขียนได้' );
	$resolved = realpath( $target );
	if ( ! $resolved || co_sales_wht_inside( $resolved, $wp_root ) || co_sales_wht_inside( $resolved, $web_root ) ) throw new RuntimeException( 'ไม่พบพื้นที่เก็บไฟล์ภาษีส่วนตัว' );
	return $resolved;
}

function co_sales_wht_file_path( $file ) {
	if ( ! preg_match( '/^[a-f0-9]{48}\.(pdf|png|jpg|webp)$/D', $file['stored'] ?? '' ) ) throw new RuntimeException( 'ไฟล์ภาษีไม่ถูกต้อง' );
	$root = co_sales_wht_storage(); $path = $root . '/' . $file['stored'];
	if ( is_link( $path ) || ! is_file( $path ) || ! is_readable( $path ) || dirname( realpath( $path ) ?: '' ) !== $root ) throw new RuntimeException( 'ไม่พบไฟล์ภาษีในพื้นที่ส่วนตัว' );
	return $path;
}

function co_sales_wht_check_upload( $upload ) {
	if ( UPLOAD_ERR_OK !== (int) ( $upload['error'] ?? UPLOAD_ERR_NO_FILE ) || empty( $upload['tmp_name'] ) || ! is_uploaded_file( $upload['tmp_name'] ) ) throw new RuntimeException( 'อัปโหลดไม่สำเร็จ กรุณาเลือกไฟล์อีกครั้ง' );
	$size = filesize( $upload['tmp_name'] );
	if ( ! $size || $size > 20 * 1024 * 1024 ) throw new RuntimeException( 'ไฟล์ภาษีต้องไม่เกิน 20 MB ต่อไฟล์' );
	$name = sanitize_file_name( $upload['name'] ); $ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
	$allowed = array( 'pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp' );
	if ( ! class_exists( 'finfo' ) ) throw new RuntimeException( 'กรุณาเปิด PHP fileinfo บนโฮสต์ก่อนอัปโหลดไฟล์ภาษี' );
	$mime = ( new finfo( FILEINFO_MIME_TYPE ) )->file( $upload['tmp_name'] );
	if ( ! isset( $allowed[$ext] ) || $allowed[$ext] !== $mime ) throw new RuntimeException( 'รองรับ PDF, JPG, PNG และ WebP ที่ชนิดไฟล์ตรงกับนามสกุลเท่านั้น' );
	return array( 'id' => bin2hex( random_bytes( 16 ) ), 'stored' => bin2hex( random_bytes( 24 ) ) . '.' . ( 'jpeg' === $ext ? 'jpg' : $ext ), 'name' => $name, 'mime' => $mime, 'size' => $size, 'uploaded_at' => wp_date( 'Y-m-d H:i:s' ), 'uploaded_by' => get_current_user_id() );
}

function co_sales_wht_require_invoice( $id ) {
	if ( ! current_user_can( 'edit_posts' ) || ! current_user_can( 'edit_post', $id ) ) throw new RuntimeException( 'ไม่มีสิทธิ์จัดการเอกสารภาษี' );
	if ( 'sales_document' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) || 'invoice' !== co_sales_meta( $id, 'document_type' ) ) throw new RuntimeException( 'กรุณาเลือก Invoice ที่มีอยู่ในระบบ' );
}

function co_sales_wht_upload( $invoice, $uploads ) {
	co_sales_wht_require_invoice( $invoice );
	if ( ! current_user_can( 'upload_files' ) ) throw new RuntimeException( 'ไม่มีสิทธิ์อัปโหลดไฟล์' );
	foreach ( array( 'name', 'tmp_name', 'error' ) as $key ) if ( ! isset( $uploads[$key] ) || ! is_array( $uploads[$key] ) ) throw new RuntimeException( 'กรุณาเลือกไฟล์ภาษีที่ต้องการแนบ' );
	$count = count( $uploads['name'] );
	if ( ! $count || $count > 10 || count( co_sales_wht_files( $invoice ) ) + $count > 20 ) throw new RuntimeException( 'เลือกไม่เกิน 10 ไฟล์ต่อครั้ง และไม่เกิน 20 ไฟล์ต่อ Invoice' );
	$pending = array();
	for ( $i = 0; $i < $count; $i++ ) {
		$upload = array(); foreach ( array( 'name', 'tmp_name', 'error' ) as $key ) $upload[$key] = $uploads[$key][$i] ?? null;
		$pending[] = array( 'file' => co_sales_wht_check_upload( $upload ), 'tmp' => $upload['tmp_name'] );
	}
	$root = co_sales_wht_storage( true );
	foreach ( $pending as $upload ) {
		$path = $root . '/' . $upload['file']['stored'];
		if ( file_exists( $path ) || ! move_uploaded_file( $upload['tmp'], $path ) ) throw new RuntimeException( 'บันทึกไฟล์ไม่ได้ กรุณาตรวจไฟล์ที่แนบแล้วก่อนลองใหม่' );
		chmod( $path, 0600 );
		$files = co_sales_wht_files( $invoice, true ); $files[] = $upload['file'];
		update_post_meta( $invoice, '_sales_wht_files', $files );
	}
}

function co_sales_wht_set_removed( $invoice, $file_id, $removed ) {
	co_sales_wht_require_invoice( $invoice ); $files = co_sales_wht_files( $invoice, true ); $found = false;
	foreach ( $files as &$file ) if ( hash_equals( $file['id'], (string) $file_id ) ) { $file['removed_at'] = $removed ? wp_date( 'Y-m-d H:i:s' ) : ''; $file['changed_by'] = get_current_user_id(); $found = true; }
	unset( $file );
	if ( ! $found ) throw new RuntimeException( 'ไม่พบไฟล์ใน Invoice นี้' );
	update_post_meta( $invoice, '_sales_wht_files', $files );
}

add_action( 'admin_post_co_sales_wht_save', function () {
	$invoice = absint( $_POST['invoice_id'] ?? 0 );
	try {
		co_sales_wht_require_invoice( $invoice ); check_admin_referer( 'co_sales_wht_' . $invoice );
		co_project_locked( 'wht_invoice_' . $invoice, function () use ( $invoice ) {
			if ( ! empty( $_POST['file_id'] ) ) co_sales_wht_set_removed( $invoice, sanitize_key( $_POST['file_id'] ), empty( $_POST['restore'] ) );
			else co_sales_wht_upload( $invoice, $_FILES['wht_files'] ?? array() );
		} );
	} catch ( RuntimeException $error ) { wp_die( esc_html( $error->getMessage() ) ); }
	$quote = absint( $_POST['quotation_id'] ?? 0 );
	$target = $quote && 'quotation' === co_sales_meta( $quote, 'document_type' ) && $quote === absint( co_sales_meta( $invoice, 'source_document_id' ) ) ? $quote : $invoice;
	wp_safe_redirect( admin_url( 'admin.php?page=co-sales-edit&document=' . $target . '&wht_saved=1#sales-wht' ) ); exit;
} );

function co_sales_wht_download_url( $invoice, $file_id ) {
	return wp_nonce_url( admin_url( 'admin-post.php?action=co_sales_wht_download&invoice=' . absint( $invoice ) . '&file=' . rawurlencode( $file_id ) ), 'co_sales_wht_download_' . $invoice );
}

add_action( 'admin_post_co_sales_wht_download', function () {
	$invoice = absint( $_GET['invoice'] ?? 0 );
	try {
		co_sales_wht_require_invoice( $invoice ); check_admin_referer( 'co_sales_wht_download_' . $invoice );
		$file = null; foreach ( co_sales_wht_files( $invoice ) as $entry ) if ( hash_equals( $entry['id'], (string) ( $_GET['file'] ?? '' ) ) ) $file = $entry;
		if ( ! $file ) throw new RuntimeException( 'ไม่พบไฟล์ใน Invoice นี้' );
		$path = co_sales_wht_file_path( $file );
	} catch ( RuntimeException $error ) { wp_die( esc_html( $error->getMessage() ) ); }
	nocache_headers(); header( 'X-Content-Type-Options: nosniff' ); header( 'Content-Type: application/octet-stream' );
	header( 'Content-Disposition: attachment; filename="certificate.' . pathinfo( $file['stored'], PATHINFO_EXTENSION ) . '"; filename*=UTF-8\'\'' . rawurlencode( $file['name'] ) );
	header( 'Content-Length: ' . filesize( $path ) ); readfile( $path ); exit;
} );

function co_sales_wht_archive_url( $issuer = '', $quote = 0, $status = '' ) {
	return wp_nonce_url( admin_url( 'admin-post.php?action=co_sales_wht_archive&issuer=' . rawurlencode( $issuer ) . '&quotation=' . absint( $quote ) . '&wht_status=' . rawurlencode( $status ) ), 'co_sales_wht_archive' );
}

function co_sales_wht_archive_invoices( $request ) {
	$mode = sanitize_key( $request['collection_mode'] ?? '' ); $issuer = sanitize_key( $request['issuer'] ?? '' );
	if ( in_array( $mode, array( 'selected', 'filtered' ), true ) ) {
		$type = sanitize_key( $request['document_type'] ?? '' );
		$ids = 'selected' === $mode ? ( $request['document_ids'] ?? array() ) : array_map( fn( $doc ) => $doc->ID, co_sales_filtered_documents( $type, $issuer ) );
		return co_sales_wht_selected_invoices( $ids, $type, $issuer );
	}
	if ( $mode ) throw new RuntimeException( 'รูปแบบชุดดาวน์โหลดไม่ถูกต้อง กรุณาเลือกใหม่' );
	return co_sales_wht_invoices( $issuer, absint( $request['quotation'] ?? 0 ), sanitize_key( $request['wht_status'] ?? '' ) );
}

/** Build the same filtered collection used by the screen, or fail instead of silently skipping files. */
function co_sales_wht_archive_entries( $invoices ) {
	$entries = array(); $bytes = 0;
	foreach ( $invoices as $invoice ) foreach ( co_sales_wht_files( $invoice->ID ) as $file ) {
		$path = co_sales_wht_file_path( $file ); $bytes += filesize( $path );
		if ( count( $entries ) >= 200 || $bytes > 200 * 1024 * 1024 ) throw new RuntimeException( 'ไฟล์รวมเกิน 200 ไฟล์หรือ 200 MB กรุณาดาวน์โหลดแยกตาม QO หรือผู้ออกเอกสาร' );
		$quote = absint( co_sales_meta( $invoice->ID, 'source_document_id' ) );
		$entries[] = array( 'path' => $path, 'file' => $file, 'invoice' => $invoice->ID, 'quote' => 'quotation' === co_sales_meta( $quote, 'document_type' ) ? $quote : 0, 'issuer' => co_sales_document_issuer( $invoice->ID ) );
	}
	if ( ! $entries ) throw new RuntimeException( 'ยังไม่มีใบหัก ณ ที่จ่ายในชุดที่เลือก' );
	return $entries;
}

function co_sales_wht_build_archive( $path, $entries ) {
	if ( ! class_exists( 'ZipArchive' ) ) throw new RuntimeException( 'โฮสต์ยังไม่เปิด PHP ZipArchive กรุณาเปิดส่วนขยาย zip หรือดาวน์โหลดทีละไฟล์' );
	$zip = new ZipArchive(); if ( true !== $zip->open( $path, ZipArchive::OVERWRITE ) ) throw new RuntimeException( 'สร้าง ZIP ไม่สำเร็จ' );
	$csv = fopen( 'php://temp', 'w+' ); fwrite( $csv, "\xEF\xBB\xBF" );
	fputcsv( $csv, array( 'Quotation', 'Quotation issuer', 'Quotation issuer ID', 'Invoice', 'Installment', 'Invoice issuer', 'Invoice issuer ID', 'Customer', 'Invoice date', 'Actual payment date', 'Invoice WHT amount', 'Certificate file' ) );
	try {
		foreach ( $entries as $index => $entry ) {
			$invoice = $entry['invoice']; $client = (array) co_sales_meta( $invoice, 'client_snapshot', array() ); $payment = co_sales_invoice_payment( $invoice );
			$name = sanitize_file_name( $entry['issuer']['id'] ) . '/' . sanitize_file_name( co_sales_meta( $invoice, 'number' ) ) . '/' . ( $index + 1 ) . '-' . sanitize_file_name( $entry['file']['name'] );
			if ( ! $zip->addFile( $entry['path'], $name ) ) throw new RuntimeException( 'เพิ่มไฟล์ใน ZIP ไม่สำเร็จ' );
			$quote_issuer = co_sales_document_issuer( $entry['quote'] );
			$row = array( co_sales_meta( $entry['quote'], 'number' ), $entry['quote'] ? $quote_issuer['name'] : '', $entry['quote'] ? $quote_issuer['id'] : '', co_sales_meta( $invoice, 'number' ), co_sales_meta( $invoice, 'installment_no' ), $entry['issuer']['name'], $entry['issuer']['id'], $client['name'] ?? '', co_sales_meta( $invoice, 'issue_date' ), $payment ? co_crm_meta( $payment, 'paid_date' ) : '', co_sales_wht( $invoice ), $name );
			// Avoid spreadsheet formula execution when the manifest is opened in Excel.
			fputcsv( $csv, array_map( fn( $cell ) => preg_match( '/^[\s]*[=+@-]/u', (string) $cell ) ? "'" . $cell : $cell, $row ) );
		}
		rewind( $csv ); if ( ! $zip->addFromString( 'index.csv', stream_get_contents( $csv ) ) ) throw new RuntimeException( 'สร้างสารบัญไม่สำเร็จ' );
	} finally { fclose( $csv ); $closed = $zip->close(); }
	if ( ! $closed ) throw new RuntimeException( 'บันทึก ZIP ไม่สำเร็จ' );
}

add_action( 'admin_post_co_sales_wht_archive', function () {
	try {
		if ( ! current_user_can( 'edit_posts' ) ) throw new RuntimeException( 'ไม่มีสิทธิ์ดาวน์โหลดเอกสารภาษี' );
		check_admin_referer( 'co_sales_wht_archive' );
		$request = 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ? $_POST : $_GET;
		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) && ! in_array( $request['collection_mode'] ?? '', array( 'selected', 'filtered' ), true ) ) throw new RuntimeException( 'กรุณาเลือกรายการที่ต้องการดาวน์โหลดจากตาราง' );
		$invoices = co_sales_wht_archive_invoices( $request );
		foreach ( $invoices as $invoice ) co_sales_wht_require_invoice( $invoice->ID );
		$entries = co_sales_wht_archive_entries( $invoices ); $root = co_sales_wht_storage(); $path = tempnam( $root, 'export-' );
		if ( ! $path || is_link( $path ) || ! is_file( $path ) || realpath( dirname( $path ) ) !== $root ) throw new RuntimeException( 'สร้างไฟล์ชั่วคราวส่วนตัวไม่สำเร็จ' );
		register_shutdown_function( static function () use ( $path ) { if ( is_file( $path ) && ! is_link( $path ) ) unlink( $path ); } );
		co_sales_wht_build_archive( $path, $entries );
	} catch ( RuntimeException $error ) { wp_die( esc_html( $error->getMessage() ) ); }
	nocache_headers(); header( 'X-Content-Type-Options: nosniff' ); header( 'Content-Type: application/zip' ); header( 'Content-Disposition: attachment; filename="withholding-certificates-' . wp_date( 'Ymd' ) . '.zip"' );
	header( 'Content-Length: ' . filesize( $path ) ); readfile( $path ); exit;
} );

function co_sales_wht_invoice_card( $invoice, $quote = 0 ) {
	$issuer = co_sales_document_issuer( $invoice ); $files = co_sales_wht_files( $invoice ); $all = co_sales_wht_files( $invoice, true );
	$missing = ! $files && co_sales_wht( $invoice ) > 0 && 'cancelled' !== co_sales_meta( $invoice, 'status' ); ?>
	<article class="sales-wht-invoice"><header><div><h3><?php echo co_sales_document_link( $invoice ); ?> <small>งวดที่ <?php echo absint( co_sales_meta( $invoice, 'installment_no', 1 ) ); ?></small></h3><p>ผู้ออก / ผู้รวบรวมภาษี: <strong><?php echo esc_html( $issuer['name'] ); ?></strong> · ยอดหักตาม Invoice ฿<?php echo esc_html( co_sales_money( co_sales_wht( $invoice ) ) ); ?></p></div><span class="sales-billing-badge <?php echo $missing ? 'pending' : 'complete'; ?>"><?php echo $files ? 'แนบแล้ว ' . count( $files ) . ' ไฟล์' : ( $missing ? 'ยังไม่มีใบหัก ณ ที่จ่าย' : 'ไม่มีภาษีหัก / เอกสารยกเลิก' ); ?></span></header>
	<?php if ( $quote && co_sales_document_issuer( $quote )['id'] !== $issuer['id'] ) : ?><p class="sales-billing-warning">ผู้ออก Invoice นี้ต่างจาก QO (<?php echo esc_html( co_sales_document_issuer( $quote )['name'] ); ?>) กรุณาตรวจสอบก่อนส่งภาษี ระบบไม่ได้เปลี่ยนข้อมูลเดิม</p><?php endif; ?>
	<ul class="sales-wht-files"><?php foreach ( $all as $file ) : $removed = ! empty( $file['removed_at'] ); ?><li <?php echo $removed ? 'class="is-removed"' : ''; ?>><div><?php if ( ! $removed ) : ?><a href="<?php echo esc_url( co_sales_wht_download_url( $invoice, $file['id'] ) ); ?>"><?php echo esc_html( $file['name'] ); ?> ↓</a><?php else : ?><span><?php echo esc_html( $file['name'] ); ?> — นำออกแล้ว</span><?php endif; ?><small>แนบ <?php echo esc_html( co_sales_date( substr( $file['uploaded_at'] ?? '', 0, 10 ) ) ); ?></small></div><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'co_sales_wht_' . $invoice ); ?><input type="hidden" name="action" value="co_sales_wht_save"><input type="hidden" name="invoice_id" value="<?php echo $invoice; ?>"><input type="hidden" name="quotation_id" value="<?php echo $quote; ?>"><input type="hidden" name="file_id" value="<?php echo esc_attr( $file['id'] ); ?>"><button type="submit" name="restore" value="<?php echo $removed ? '1' : '0'; ?>" class="sales-link <?php echo $removed ? '' : 'danger'; ?>"><?php echo $removed ? 'คืนไฟล์' : 'นำออก'; ?></button></form></li><?php endforeach; ?></ul>
	<?php if ( current_user_can( 'upload_files' ) ) : ?><form class="sales-form sales-wht-upload" enctype="multipart/form-data" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'co_sales_wht_' . $invoice ); ?><input type="hidden" name="action" value="co_sales_wht_save"><input type="hidden" name="invoice_id" value="<?php echo $invoice; ?>"><input type="hidden" name="quotation_id" value="<?php echo $quote; ?>"><label>แนบหนังสือรับรองหัก ณ ที่จ่าย<input required type="file" multiple name="wht_files[]" accept="application/pdf,image/jpeg,image/png,image/webp"></label><button class="sales-btn light" type="submit">บันทึกไฟล์</button><small>PDF / JPG / PNG / WebP · ไม่เกิน 20 MB ต่อไฟล์ · เก็บส่วนตัวเฉพาะแอดมิน</small></form><?php endif; ?>
	</article>
<?php }

function co_sales_render_wht( $document ) {
	$type = co_sales_meta( $document, 'document_type' ); if ( ! in_array( $type, array( 'quotation', 'invoice' ), true ) ) return;
	$quote = 'quotation' === $type ? $document : 0; $invoices = $quote ? co_sales_wht_invoices( '', $quote ) : array( get_post( $document ) ); $issuer = co_sales_document_issuer( $document ); ?>
	<section class="sales-panel sales-wht-panel" id="sales-wht"><div class="sales-panel-head"><div><h2>หนังสือรับรองหัก ณ ที่จ่าย</h2><p><?php echo $quote ? 'ผู้ออก Quotation: ' : 'ผู้ออก Invoice: '; ?><strong><?php echo esc_html( $issuer['name'] ); ?></strong> · แนบแยกตาม Invoice และรวบรวมส่งตามผู้ออกเอกสาร</p></div><a class="sales-btn light" href="<?php echo esc_url( admin_url( 'admin.php?page=co-sales-wht&issuer=' . rawurlencode( $issuer['id'] ) ) ); ?>">ดูทั้งหมดของผู้ออกคนนี้ →</a></div>
	<?php if ( ! empty( $_GET['wht_saved'] ) ) : ?><p role="status" class="sales-billing-badge complete">บันทึกไฟล์ภาษีแล้ว</p><?php endif; ?>
	<?php if ( $quote && array_filter( $invoices, fn( $doc ) => co_sales_wht_files( $doc->ID ) ) ) : ?><p><a class="sales-btn" href="<?php echo esc_url( co_sales_wht_archive_url( '', $quote ) ); ?>">ดาวน์โหลดใบหักภาษีของ QO นี้ (ZIP)</a></p><?php endif; ?>
	<?php foreach ( $invoices as $invoice ) co_sales_wht_invoice_card( $invoice->ID, $quote ); ?>
	<?php if ( ! $invoices ) : ?><p class="sales-empty">ยังไม่มี Invoice — เมื่อออก Invoice ของแต่ละงวดแล้ว สามารถแนบใบหัก ณ ที่จ่ายที่นี่ได้</p><?php endif; ?>
	<p class="sales-field-help">การแนบไฟล์ไม่เปลี่ยนยอดรับเงินหรือ Receipt · ปุ่มนำออกเก็บไฟล์เดิมไว้และกดคืนไฟล์ได้ · ไฟล์ภาษีไม่แสดงในรายงานลูกค้า</p></section>
<?php }

function co_sales_wht_page() {
	if ( ! current_user_can( 'edit_posts' ) ) return;
	$issuer = sanitize_key( $_GET['issuer'] ?? '' ); $status = sanitize_key( $_GET['wht_status'] ?? '' ); $invoices = co_sales_wht_invoices( $issuer, 0, $status );
	$files = array_sum( array_map( fn( $doc ) => count( co_sales_wht_files( $doc->ID ) ), $invoices ) ); co_sales_assets(); ?>
	<div class="sales-wrap"><header class="sales-hero compact"><div><small>WITHHOLDING CERTIFICATES</small><h1>รวมใบหัก ณ ที่จ่าย</h1><p>ดูไฟล์และดาวน์โหลดจากตารางนี้ได้เลย ไม่ต้องเปิดเอกสารทีละใบ</p></div></header>
	<section class="sales-panel"><form class="sales-form sales-wht-filters" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>"><input type="hidden" name="page" value="co-sales-wht"><label>ผู้ออก Invoice<select name="issuer"><option value="">ทุกคน</option><?php foreach ( co_sales_issuer_filters() as $key => $name ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $issuer, $key ); ?>><?php echo esc_html( $name ); ?></option><?php endforeach; ?></select></label><label>ใบหัก ณ ที่จ่าย<select name="wht_status"><option value="">ทั้งหมด</option><option value="missing" <?php selected( $status, 'missing' ); ?>>ยังขาดใบหัก ณ ที่จ่าย</option><option value="attached" <?php selected( $status, 'attached' ); ?>>แนบไฟล์แล้ว</option></select></label><button class="sales-btn" type="submit">กรอง</button><a href="<?php echo esc_url( admin_url( 'admin.php?page=co-sales-documents' ) ); ?>">← กลับ Quotation</a></form><p><?php echo count( $invoices ); ?> Invoice · <?php echo $files; ?> ไฟล์ · กดชื่อไฟล์เพื่อดาวน์โหลด หรือเลือกหลายใบเพื่อรวมส่งเป็น ZIP</p>
	<form data-wht-bulk-form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'co_sales_wht_archive' ); ?><input type="hidden" name="action" value="co_sales_wht_archive"><input type="hidden" name="collection_mode" value="selected"><input type="hidden" name="document_type" value="invoice"><input type="hidden" name="issuer" value="<?php echo esc_attr( $issuer ); ?>">
	<?php co_sales_wht_bulk_toolbar( 'invoice', $issuer, $files > 0, co_sales_wht_archive_url( $issuer, 0, $status ) ); ?>
	<div class="sales-table-wrap"><table class="sales-wht-index-table"><thead><tr><th class="sales-wht-select-cell">เลือก</th><th>Quotation / Invoice</th><th>ลูกค้า</th><th>ผู้ออก Invoice / ผู้รวบรวมภาษี</th><th>ยอดหักตาม Invoice</th><th>ใบหัก ณ ที่จ่าย / ดาวน์โหลด</th></tr></thead><tbody>
	<?php foreach ( $invoices as $invoice ) : $id = $invoice->ID; $quote = absint( co_sales_meta( $id, 'source_document_id' ) ); $owner = co_sales_document_issuer( $id ); $client = (array) co_sales_meta( $id, 'client_snapshot', array() ); $attached = co_sales_wht_files( $id ); ?>
	<tr><td class="sales-wht-select-cell"><input type="checkbox" name="document_ids[]" value="<?php echo $id; ?>" data-wht-select <?php disabled( ! $attached ); ?> aria-label="เลือกใบหักภาษีของ <?php echo esc_attr( co_sales_meta( $id, 'number' ) ); ?>"></td><td class="sales-document-title-cell"><?php if ( 'quotation' === co_sales_meta( $quote, 'document_type' ) ) echo co_sales_document_link( $quote ); ?><br><?php echo co_sales_document_link( $id ); ?><small class="sales-amount-before">งวด <?php echo absint( co_sales_meta( $id, 'installment_no', 1 ) ); ?> · <?php echo esc_html( co_sales_date( co_sales_meta( $id, 'issue_date' ) ) ); ?></small></td><td data-label="ลูกค้า"><?php echo esc_html( $client['name'] ?? '' ); ?></td><td data-label="ผู้ออก Invoice"><?php echo esc_html( $owner['name'] ); ?></td><td data-label="ยอดหักตาม Invoice">฿<?php echo esc_html( co_sales_money( co_sales_wht( $id ) ) ); ?></td><td data-label="ใบหัก ณ ที่จ่าย / ดาวน์โหลด"><?php co_sales_wht_listing_cell( $id, array( $invoice ) ); ?></td></tr>
	<?php endforeach; if ( ! $invoices ) : ?><tr><td colspan="6">ไม่พบ Invoice ตามตัวกรองที่เลือก</td></tr><?php endif; ?>
	</tbody></table></div></form></section></div>
<?php }
