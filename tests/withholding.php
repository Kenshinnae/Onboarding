<?php
// Isolated posts + temporary private files; never connects to the WordPress database.
if ( PHP_SAPI !== 'cli' ) exit;
$wht_test_mode = $argv[1] ?? 'test'; $argv[1] = 'test';
$test_dir = sys_get_temp_dir() . '/co-wht-test-' . bin2hex( random_bytes( 8 ) );
if ( ! mkdir( $test_dir, 0700 ) ) throw new RuntimeException( 'Cannot create test directory' );
$test_dir = realpath( $test_dir );
define( 'CO_WHT_STORAGE_DIR', $test_dir . '/private' );
ob_start(); require __DIR__ . '/project-workflow.php'; ob_end_clean();
$created_paths = array();
try {
	update_option( 'co_sales_issuers', array( array( 'id' => 'issuer_a', 'name' => 'Issuer A renamed in Settings' ), array( 'id' => 'issuer_b', 'name' => 'Issuer B' ) ) );
	foreach ( array( $quote, $invoice, $receipt ) as $doc ) { update_post_meta( $doc, '_sales_issuer_id', 'issuer_a' ); update_post_meta( $doc, '_sales_issuer_snapshot', array( 'id' => 'issuer_a', 'name' => 'Issuer A at issue time' ) ); }
	update_post_meta( $second, '_sales_issuer_id', 'issuer_b' ); update_post_meta( $second, '_sales_issuer_snapshot', array( 'id' => 'issuer_b', 'name' => 'Issuer B' ) );
	expect_workflow( co_sales_document_issuer( $quote )['name'] === 'Issuer A at issue time', 'issuer snapshot survives settings rename' );
	$ids = fn( $rows ) => array_map( fn( $doc ) => $doc->ID, $rows );
	expect_workflow( $ids( co_sales_wht_invoices( 'issuer_a', $quote ) ) === array( $invoice ), 'issuer A and QO filters intersect' );
	expect_workflow( $ids( co_sales_wht_invoices( 'issuer_b', $quote ) ) === array( $second ), 'existing mismatched invoice remains with actual issuer B' );
	expect_workflow( count( co_sales_wht_invoices( '', $quote, 'missing' ) ) === 2, 'two missing certificates before attachment' );
	$root = co_sales_wht_storage( true );
	expect_workflow( ! co_sales_wht_inside( $root, realpath( ABSPATH ) ), 'private storage is outside web root' );
	expect_workflow( co_sales_wht_inside( '/site/public/file', '/site/public' ) && ! co_sales_wht_inside( '/site/public-private/file', '/site/public' ), 'directory boundary check is not a string-prefix check' );
	foreach ( array( $invoice => 'a', $second => 'b' ) as $doc => $character ) {
		$file = array( 'id' => str_repeat( $character, 32 ), 'stored' => str_repeat( $character, 48 ) . '.png', 'name' => 'หนังสือรับรอง-' . $character . '.png', 'mime' => 'image/png', 'uploaded_at' => '2026-10-23 12:00:00', 'uploaded_by' => 1 );
		$path = $root . '/' . $file['stored'];
		file_put_contents( $path, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6eT0AAAAASUVORK5CYII=' ) ); $created_paths[] = $path;
		$file['size'] = filesize( $path ); update_post_meta( $doc, '_sales_wht_files', array( $file ) );
		expect_workflow( co_sales_wht_file_path( $file ) === $path, 'certificate resolves inside private storage' );
	}
	$before = co_project_financials( $project );
	$entries = co_sales_wht_archive_entries( co_sales_wht_invoices( 'issuer_a', $quote ) );
	expect_workflow( count( $entries ) === 1 && $entries[0]['invoice'] === $invoice && $entries[0]['issuer']['id'] === 'issuer_a', 'ZIP uses same issuer and QO scope as screen' );
	$archive = tempnam( $test_dir, 'archive-' ); $created_paths[] = $archive;
	co_sales_wht_build_archive( $archive, $entries ); $zip = new ZipArchive(); $zip->open( $archive );
	expect_workflow( $zip->numFiles === 2 && str_starts_with( $zip->getNameIndex( 0 ), 'issuer_a/' ), 'archive contains one file plus manifest in issuer folder' );
	$csv = $zip->getFromName( 'index.csv' ); $zip->close();
	expect_workflow( str_contains( $csv, 'Issuer A at issue time' ) && str_contains( $csv, '2025-12-31' ) && ! str_contains( $csv, 'Issuer B' ), 'manifest contains correct issuer and actual receipt date, not other issuer files' );
	$selected = co_sales_wht_archive_invoices( array( 'collection_mode' => 'selected', 'document_type' => 'quotation', 'document_ids' => array( $quote, $quote ), 'issuer' => 'issuer_a' ) );
	expect_workflow( count( $selected ) === 2 && count( co_sales_wht_archive_entries( $selected ) ) === 2, 'selecting QO includes both Invoice certificates exactly once' );
	$filtered = co_sales_wht_archive_invoices( array( 'collection_mode' => 'filtered', 'document_type' => 'quotation', 'issuer' => 'issuer_a' ) );
	expect_workflow( $ids( $filtered ) === $ids( $selected ), 'download all uses same QO issuer filter as listing' );
	$one_invoice = co_sales_wht_archive_invoices( array( 'collection_mode' => 'selected', 'document_type' => 'invoice', 'document_ids' => array( $invoice ), 'issuer' => 'issuer_a' ) );
	expect_workflow( $ids( $one_invoice ) === array( $invoice ), 'selected Invoice never includes an unselected sibling' );
	rejected_workflow( fn() => co_sales_wht_archive_invoices( array( 'collection_mode' => 'selected', 'document_type' => 'quotation' ) ), 'empty selection never exports everything' );
	rejected_workflow( fn() => co_sales_wht_archive_invoices( array( 'collection_mode' => 'selected', 'document_type' => 'invoice', 'document_ids' => array( $second ), 'issuer' => 'issuer_a' ) ), 'reject selection outside current issuer filter' );
	rejected_workflow( fn() => co_sales_wht_selected_invoices( array( $quote ), 'invoice' ), 'reject wrong document type in selection' );
	rejected_workflow( fn() => co_sales_wht_selected_invoices( array( 9999999 ), 'quotation' ), 'reject missing documents in selection' );
	$bulk_quote = wp_insert_post( array( 'post_type' => 'sales_document', 'post_status' => 'publish', 'post_title' => 'QO_BULK_TEST' ) );
	foreach ( array( 'document_type' => 'quotation', 'issuer_id' => 'issuer_a', 'issuer_snapshot' => array( 'name' => 'Issuer A at issue time' ), 'number' => 'QO_BULK_TEST' ) as $key => $value ) update_post_meta( $bulk_quote, '_sales_' . $key, $value );
	$bulk_invoice = wp_insert_post( array( 'post_type' => 'sales_document', 'post_status' => 'publish', 'post_title' => 'IV_BULK_TEST' ) );
	foreach ( array( 'document_type' => 'invoice', 'issuer_id' => 'issuer_a', 'source_document_id' => $bulk_quote, 'number' => 'IV_BULK_TEST', 'wht_files' => co_sales_wht_files( $invoice ) ) as $key => $value ) update_post_meta( $bulk_invoice, '_sales_' . $key, $value );
	$bulk = co_sales_wht_selected_invoices( array( $quote, $bulk_quote ), 'quotation', 'issuer_a' );
	expect_workflow( count( $bulk ) === 3 && count( co_sales_wht_archive_entries( $bulk ) ) === 3, 'multiple QOs can be collected in one download' );
	co_sales_wht_build_archive( $archive, co_sales_wht_archive_entries( $bulk ) ); $zip = new ZipArchive(); $zip->open( $archive );
	expect_workflow( $zip->numFiles === 4 && str_contains( $zip->getFromName( 'index.csv' ), 'QO_BULK_TEST' ), 'multi-QO ZIP contains three certificates and one complete index' ); $zip->close();
	$bulk_fixture_posts = array( $bulk_quote => $posts[$bulk_quote], $bulk_invoice => $posts[$bulk_invoice] );
	$bulk_fixture_meta = array( $bulk_quote => $meta[$bulk_quote], $bulk_invoice => $meta[$bulk_invoice] );
	unset( $posts[$bulk_quote], $posts[$bulk_invoice], $meta[$bulk_quote], $meta[$bulk_invoice] );
	expect_workflow( ! co_sales_wht_invoices( '', $quote, 'missing' ), 'no missing files after attachment' );
	co_sales_wht_set_removed( $invoice, str_repeat( 'a', 32 ), true );
	expect_workflow( ! co_sales_wht_files( $invoice ) && is_file( $created_paths[0] ) && count( co_sales_wht_files( $invoice, true ) ) === 1, 'remove is recoverable and retains private file' );
	expect_workflow( $ids( co_sales_wht_invoices( '', $quote, 'missing' ) ) === array( $invoice ), 'removed file no longer counts as attached' );
	ob_start(); co_sales_wht_listing_badge( co_sales_wht_document_invoices( $quote ) ); $missing_badge = ob_get_clean();
	expect_workflow( str_contains( $missing_badge, 'ขาด 1 ใบ' ) && ! str_contains( $missing_badge, '<a' ), 'main table only marks missing certificates with a short badge' );
	rejected_workflow( fn() => co_sales_wht_set_removed( $second, str_repeat( 'a', 32 ), true ), 'cannot remove another Invoice certificate' );
	co_sales_wht_set_removed( $invoice, str_repeat( 'a', 32 ), false );
	expect_workflow( count( co_sales_wht_files( $invoice ) ) === 1 && co_project_financials( $project ) === $before, 'restore works and never changes money or payments' );
	rejected_workflow( fn() => co_sales_wht_file_path( array( 'stored' => '../../wp-config.php' ) ), 'reject path traversal' );
	rejected_workflow( fn() => co_sales_wht_upload( $invoice, array() ), 'reject empty upload' );
	rejected_workflow( fn() => co_sales_wht_check_upload( array( 'name' => 'fake.pdf', 'tmp_name' => $created_paths[0], 'error' => 0 ) ), 'reject local path pretending to be HTTP upload' );
	$nonce_valid = false; rejected_workflow( fn() => action_workflow( 'admin_post_co_sales_wht_save', array( 'invoice_id' => $invoice, 'file_id' => str_repeat( 'a', 32 ) ) ), 'file changes require nonce' ); $nonce_valid = true;
	$allowed = false;
	rejected_workflow( fn() => co_sales_wht_set_removed( $invoice, str_repeat( 'a', 32 ), true ), 'certificate edit requires permission' );
	rejected_workflow( fn() => action_workflow( 'admin_post_co_sales_wht_download', array( 'invoice' => $invoice, 'file' => str_repeat( 'a', 32 ) ) ), 'certificate download requires permission' );
	rejected_workflow( fn() => action_workflow( 'admin_post_co_sales_wht_archive', array() ), 'bulk download requires permission' ); $allowed = true;
	$unchanged = serialize( array( $posts, $meta ) );
	rejected_workflow( fn() => document_workflow( $quote, $project, array( 'issuer_id' => 'issuer_b' ) ), 'cannot change agreed QO issuer after Invoice exists' );
	expect_workflow( $unchanged === serialize( array( $posts, $meta ) ), 'rejected issuer edit makes no changes' );
	document_workflow( $quote, $project, array( 'issuer_id' => 'issuer_a' ) );
	expect_workflow( co_sales_document_issuer( $quote )['name'] === 'Issuer A at issue time', 'normal save preserves historical issuer' );
	$unchanged = serialize( array( $posts, $meta ) );
	rejected_workflow( fn() => document_workflow( 0, 0, array( 'issuer_id' => 'does_not_exist' ) ), 'invalid issuer cannot silently fall back to someone else' );
	expect_workflow( $unchanged === serialize( array( $posts, $meta ) ), 'invalid issuer creates no document' );
	update_option( 'co_sales_issuers', array( array( 'id' => 'issuer_b', 'name' => 'Issuer B' ) ) );
	expect_workflow( isset( co_sales_issuer_filters()['issuer_a'] ), 'removed issuer still filterable from history' );
	$_GET = array( 'type' => 'quotation', 'issuer' => 'issuer_a' ); ob_start(); co_sales_documents_page(); $list_html = ob_get_clean();
	expect_workflow( str_contains( $list_html, '<div class="sales-list-heading"><h2>' ) && ! str_contains( $list_html, '<section class="sales-panel"><form class="sales-form sales-wht-filters"' ), 'issuer filter belongs to the table header, not a mostly empty separate panel' );
	expect_workflow( str_contains( $list_html, 'data-wht-bulk-form' ) && str_contains( $list_html, 'name="document_ids[]"' ) && str_contains( $list_html, 'sales-wht-list-badge complete' ) && str_contains( $list_html, 'ครบแล้ว' ), 'QO listing retains bulk selection and a compact certificate status' );
	foreach ( array( 'หนังสือรับรอง-a.png', 'หนังสือรับรอง-b.png', 'class="sales-wht-file-download"', 'class="sales-wht-owner-warning"', 'class="sales-btn light sales-wht-row-zip"', 'class="sales-wht-table-files"' ) as $removed_detail ) expect_workflow( ! str_contains( $list_html, $removed_detail ), 'compact main listing omits ' . $removed_detail );
	expect_workflow( str_contains( $list_html, 'ดาวน์โหลดทั้งหมด (ZIP)' ) && ! str_contains( $list_html, 'เลือกหลายใบเพื่อรวมส่งครั้งเดียว' ), 'bulk toolbar stays usable without long explanatory text' );
	$_GET = array( 'issuer' => 'issuer_a' ); ob_start(); co_sales_wht_page(); $wht_html = ob_get_clean();
	expect_workflow( str_contains( $wht_html, 'sales-wht-file-download' ) && str_contains( $wht_html, 'data-wht-select-all' ) && ! str_contains( $wht_html, 'หนังสือรับรอง-b.png' ), 'dedicated WHT list also downloads files inline and respects actual invoice issuer' );
	$_GET = array( 'document' => $quote ); ob_start(); co_sales_edit_page(); $html = ob_get_clean();
	expect_workflow( str_contains( $html, 'name="invoice_id" value="' . $invoice . '"' ) && str_contains( $html, 'name="invoice_id" value="' . $second . '"' ), 'QO attaches files separately for every Invoice' );
	expect_workflow( str_contains( $html, 'multipart/form-data' ) && str_contains( $html, 'ผู้ออก Invoice นี้ต่างจาก QO' ) && str_contains( $html, '(ข้อมูลเดิม)' ), 'upload forms, historical issuer and mismatch warning visible' );
	ob_start(); co_crm_render_customer_project_report( $customer_token ); $public = ob_get_clean();
	expect_workflow( ! str_contains( $public, 'หนังสือรับรอง-' ) && ! str_contains( $public, 'co_sales_wht_' ) && ! str_contains( $public, $root ), 'customer report never exposes tax files or storage paths' );
	if ( 'test' === $wht_test_mode ) echo "PASS: WHT ownership, historical issuers, filters, private storage, ZIP contents and manifest, recoverable removal, authorization, locks, customer privacy and unchanged finances\n";
	else {
		echo '<!doctype html><html><head><meta charset="utf-8"></head><body>';
		if ( 'quote' === $wht_test_mode ) { $_GET = array( 'document' => $quote ); co_sales_edit_page(); }
		elseif ( 'invoice' === $wht_test_mode ) { $_GET = array( 'document' => $invoice ); co_sales_edit_page(); }
		elseif ( 'documents' === $wht_test_mode ) { $_GET = array( 'issuer' => 'issuer_a' ); co_sales_documents_page(); }
		elseif ( 'documents-bulk' === $wht_test_mode ) { $posts += $bulk_fixture_posts; $meta += $bulk_fixture_meta; $_GET = array(); co_sales_documents_page(); }
		else { $_GET = 'filtered' === $wht_test_mode ? array( 'issuer' => 'issuer_a' ) : array(); co_sales_wht_page(); }
		echo '</body></html>';
	}
} finally {
	// Only files and directories created by this isolated test; no recursive cleanup.
	foreach ( $created_paths as $path ) if ( co_sales_wht_inside( $path, $test_dir ) && is_file( $path ) && ! is_link( $path ) ) unlink( $path );
	if ( is_dir( CO_WHT_STORAGE_DIR ) && ! is_link( CO_WHT_STORAGE_DIR ) ) rmdir( CO_WHT_STORAGE_DIR );
	if ( is_dir( $test_dir ) && ! is_link( $test_dir ) ) rmdir( $test_dir );
}
