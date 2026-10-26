<?php
// All fixtures are isolated in memory. No customer document is modified.
if ( PHP_SAPI !== 'cli' ) exit;
ob_start(); require __DIR__ . '/project-workflow.php'; ob_end_clean();
$fixture_signature_urls = array( 432 => 'https://test.invalid/signature-fixture.png' );
$finance = co_project_financials( $project ); $cash_meta = $meta[$payment]; $issuer_profiles = co_sales_issuers(); $mail_count = $notices;
foreach ( array( $quote, $invoice, $receipt ) as $doc ) {
	$issuer = co_sales_meta( $doc, 'issuer_snapshot', array() ); $issuer['signature_id'] = 432;
	update_post_meta( $doc, '_sales_issuer_snapshot', $issuer );
	expect_workflow( co_sales_signature_mode( $doc ) === 'image', 'legacy document keeps existing signature behavior' );
	ob_start(); co_sales_render_paper( $doc ); $signed = ob_get_clean();
	expect_workflow( str_contains( $signed, 'signature-fixture.png' ) && str_contains( $signed, 'alt="ลายเซ็นผู้ออกเอกสาร"' ), 'image signature included for each document type' );
	document_workflow( $doc, $project, array( 'signature_mode' => 'manual' ) );
	ob_start(); co_sales_render_paper( $doc ); $manual = ob_get_clean();
	expect_workflow( ! str_contains( $manual, 'signature-fixture.png' ) && ! str_contains( $manual, 'alt="ลายเซ็นผู้ออกเอกสาร"' ) && substr_count( $manual, 'class="signature-space dotted"' ) === 2, 'hand-sign document omits image and retains both signing spaces' );
	ob_start(); co_crm_render_customer_project_report( $customer_token, $doc ); $customer_copy = ob_get_clean();
	expect_workflow( $http_status === 200 && str_contains( $customer_copy, 'data-sales-print-source' ) && ! str_contains( $customer_copy, 'signature-fixture.png' ), 'customer document view also omits signature image' );
	expect_workflow( co_sales_meta( $doc, 'issuer_snapshot' )['signature_id'] === 432, 'stored issuer signature is not deleted' );
	document_workflow( $doc, $project );
	expect_workflow( co_sales_signature_mode( $doc ) === 'manual', 'ordinary saves preserve selected hand-sign mode' );
	document_workflow( $doc, $project, array( 'signature_mode' => 'image' ) );
	expect_workflow( co_sales_signature_mode( $doc ) === 'image', 'mode can be switched back even on financially locked documents' );
}
$new = document_workflow( 0, 0, array( 'signature_mode' => 'manual' ) );
$new_iv = co_project_derive_document( $new, 1 );
expect_workflow( co_sales_signature_mode( $new_iv ) === 'manual', 'new Invoice inherits QO signing preference' );
co_project_record_payment( $new_iv, '2026-10-23' ); $new_rc = co_project_derive_document( $new_iv );
expect_workflow( co_sales_signature_mode( $new_rc ) === 'manual', 'new Receipt inherits Invoice signing preference' );
document_workflow( $new_iv, 0, array( 'signature_mode' => 'image' ) );
expect_workflow( co_sales_signature_mode( $new ) === 'manual' && co_sales_signature_mode( $new_iv ) === 'image' && co_sales_signature_mode( $new_rc ) === 'manual', 'each document retains its own independently editable preference' );
$count = count( $posts );
rejected_workflow( fn() => document_workflow( 0, 0, array( 'signature_mode' => 'invalid' ) ), 'invalid signature mode rejected' );
expect_workflow( count( $posts ) === $count, 'invalid mode creates no partial document' );
expect_workflow( co_project_financials( $project ) === $finance && $meta[$payment] === $cash_meta && co_sales_issuers() === $issuer_profiles && $notices === $mail_count, 'signature changes preserve finances, actual cash dates, issuer settings and notifications' );
$_GET = array( 'document' => $receipt ); ob_start(); co_sales_edit_page(); $editor = ob_get_clean();
expect_workflow( str_contains( $editor, 'name="signature_mode"' ) && str_contains( $editor, 'เว้นไว้เซ็นมือ' ), 'selector available on existing paid Receipt' );
$preview_fixture = array();
foreach ( array( $quote, $invoice, $receipt ) as $doc ) {
	$saved_meta = $meta; $saved_options = $options;
	$_GET = array( 'document' => $doc, 'preview_signature' => 'manual' );
	ob_start(); co_sales_preview_page(); $preview = ob_get_clean();
	expect_workflow( str_contains( $preview, 'name="preview_signature"' ) && str_contains( $preview, 'value="manual" selected' ) && ! str_contains( $preview, 'alt="ลายเซ็นผู้ออกเอกสาร"' ), 'preview can suppress image without changing saved image preference' );
	expect_workflow( $meta === $saved_meta && $options === $saved_options && co_sales_signature_mode( $doc ) === 'image', 'manual preview performs no writes' );
	$preview_fixture['manual'] = $preview;
	update_post_meta( $doc, '_sales_signature_mode', 'manual' ); $saved_meta = $meta;
	$_GET = array( 'document' => $doc, 'preview_signature' => 'image' );
	ob_start(); co_sales_preview_page(); $preview = ob_get_clean();
	expect_workflow( str_contains( $preview, 'alt="ลายเซ็นผู้ออกเอกสาร"' ) && str_contains( $preview, 'value="image" selected' ), 'preview can include image without changing saved manual preference' );
	expect_workflow( $meta === $saved_meta && co_sales_signature_mode( $doc ) === 'manual', 'signed preview leaves saved preference untouched' );
	$preview_fixture['image'] = $preview;
	// The shared customer view ignores an admin-only query-string override.
	ob_start(); co_crm_render_customer_project_report( $customer_token, $doc ); $customer_copy = ob_get_clean();
	expect_workflow( ! str_contains( $customer_copy, 'signature-fixture.png' ), 'customer link cannot override saved manual signature preference' );
	unset( $_GET['preview_signature'] ); ob_start(); co_sales_preview_page(); $default_preview = ob_get_clean();
	expect_workflow( ! str_contains( $default_preview, 'alt="ลายเซ็นผู้ออกเอกสาร"' ), 'initial preview follows saved manual preference' );
	update_post_meta( $doc, '_sales_signature_mode', 'image' );
}
$_GET = array( 'document' => $quote, 'preview_signature' => 'invalid' );
rejected_workflow( fn() => co_sales_preview_page(), 'invalid preview signature mode rejected' );
if ( '--preview-fixture' === ( $argv[1] ?? '' ) ) { echo json_encode( $preview_fixture, JSON_UNESCAPED_UNICODE ); exit; }
echo "PASS: saved and preview-only image/manual signing for QO/Invoice/Receipt, read-only preview overrides, customer-view privacy, validation and unchanged finances.\n";
