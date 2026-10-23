<?php
// In-memory fixtures only: no real WordPress data is deleted.
if ( PHP_SAPI !== 'cli' ) exit;
ob_start(); require __DIR__ . '/project-workflow.php'; ob_end_clean();
class QuotationDeleteTestDatabase {
	public $posts = 'posts', $postmeta = 'postmeta', $options = 'options', $comments = 'comments', $commentmeta = 'commentmeta', $term_relationships = 'term_relationships', $term_taxonomy = 'term_taxonomy';
	private $snapshot; private $active = false;
	function prepare( $sql, ...$args ) { return $sql; }
	function get_var( $sql ) { return str_contains( $sql, 'in_transaction' ) ? (int) $this->active : 'InnoDB'; }
	function query( $sql ) {
		if ( $sql === 'START TRANSACTION' ) { $this->snapshot = serialize( array( $GLOBALS['posts'], $GLOBALS['meta'], $GLOBALS['options'] ) ); $this->active = true; }
		elseif ( $sql === 'ROLLBACK' ) { list( $GLOBALS['posts'], $GLOBALS['meta'], $GLOBALS['options'] ) = unserialize( $this->snapshot ); $this->active = false; }
		elseif ( $sql === 'COMMIT' ) $this->active = false;
		return 1;
	}
}
$wpdb = new QuotationDeleteTestDatabase(); $_SERVER['REQUEST_METHOD'] = 'POST';
expect_workflow( co_sales_reference_document( $invoice ) === $quote && co_sales_reference_document( $receipt ) === $invoice && co_sales_reference_document( $quote ) === 0, 'INV references QO; Receipt references INV; QO has no Ref' );
$print_css = file_get_contents( CO_THEME_DIR . '/assets/sales-print.css' );
expect_workflow( str_contains( $print_css, '.sales-doc-totals b{padding:1mm 2mm;text-align:right;' ) && str_contains( $print_css, '.sales-doc-totals>div.sales-doc-baht{padding:1mm 2mm;text-align:right}' ), 'summary amounts and Thai total right aligned for all document types' );
foreach ( array( $invoice => $quote, $receipt => $invoice ) as $doc => $ref ) {
	ob_start(); co_sales_print_header( $doc, co_sales_meta( $doc, 'document_type' ), array(), array() ); $header = ob_get_clean();
	expect_workflow( str_contains( $header, 'เลขที่อ้างอิง / Ref</b><span>' . co_sales_meta( $ref, 'number' ) ), 'correct direct-source Ref row under No.' );
}
$_GET = array( 'type' => 'quotation' ); ob_start(); co_sales_documents_page(); $listing = ob_get_clean();
expect_workflow( ! str_contains( $listing, 'view=trash' ) && ! str_contains( $listing, 'กู้คืนทั้งชุด' ) && str_contains( $listing, 'value="delete"' ) && str_contains( $listing, 'กู้คืนไม่ได้' ), 'permanent delete UI, no Trash/Restore' );
$group = co_sales_quote_group( $quote ); $before = co_project_financials( $project ); $outside = $meta[$other_quote]; $ma_before = $meta[$ma_stream];
$denied_posts = array( $receipt ); rejected_workflow( fn() => co_sales_quote_lifecycle( $quote ), 'child permission required' ); $denied_posts = array();
$fail_delete_id = $receipt; rejected_workflow( fn() => co_sales_quote_lifecycle( $quote ), 'failed delete rolls back' );
foreach ( $group['documents'] + $group['payments'] as $member => $status ) expect_workflow( get_post_status( $member ) === $status, 'rollback restores member ' . $member );
expect_workflow( co_project_financials( $project ) === $before, 'rollback restores financials' );
$nonce_valid = false; rejected_workflow( fn() => action_workflow( 'admin_post_co_sales_quote_lifecycle', array( 'document_id' => $quote, 'operation' => 'delete' ) ), 'CSRF rejected' ); $nonce_valid = true;
$_SERVER['REQUEST_METHOD'] = 'GET'; rejected_workflow( fn() => action_workflow( 'admin_post_co_sales_quote_lifecycle', array( 'document_id' => $quote, 'operation' => 'delete' ) ), 'GET rejected' ); $_SERVER['REQUEST_METHOD'] = 'POST';
action_workflow( 'admin_post_co_sales_quote_lifecycle', array( 'document_id' => $quote, 'operation' => 'delete' ) );
foreach ( $group['documents'] + $group['payments'] as $member => $status ) expect_workflow( ! get_post( $member ) && ! isset( $meta[$member] ), 'member and metadata physically deleted ' . $member );
expect_workflow( co_project_financials( $project ) === array( 'quote' => 0, 'value' => 0.0, 'net' => 0.0, 'received' => 0.0, 'outstanding' => 0.0 ), 'deleted cash and sale absent from reports' );
expect_workflow( 'publish' === get_post_status( $project ) && $meta[$other_quote] === $outside && $meta[$ma_stream] === $ma_before, 'Project, other QOs and MA preserved' );
rejected_workflow( fn() => co_sales_quote_lifecycle( $quote ), 'repeat delete cannot affect other documents' );
rejected_workflow( fn() => co_sales_quote_lifecycle( $quote, true ), 'restore not supported' );
rejected_workflow( fn() => co_project_record_payment( $invoice, '2026-10-23' ), 'deleted invoice cannot receive payment' );
rejected_workflow( fn() => co_project_derive_document( $invoice ), 'deleted invoice cannot generate receipt' );
rejected_workflow( fn() => co_sales_wht_require_invoice( $invoice ), 'deleted tax files inaccessible' );
echo "PASS: permanent QO/Invoice/Receipt/payment deletion, rollback, financial cleanup, permissions, no recovery UI and correct document references.\n";
