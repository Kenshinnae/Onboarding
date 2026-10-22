<?php
// Run with PHP CLI. All posts, options and notifications are isolated in memory.
if ( PHP_SAPI !== 'cli' ) exit;
define( 'ABSPATH', '/' ); define( 'CO_THEME_DIR', dirname( __DIR__ ) );
$posts = $meta = $options = $actions = array(); $allowed = $nonce_valid = true; $notices = 0;
function add_action( $name, $fn, ...$args ) { $GLOBALS['actions'][$name][] = $fn; }
function add_filter( ...$args ) {}
function absint( $n ) { return abs( (int) $n ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $s ) ); }
function sanitize_text_field( $s ) { return trim( preg_replace( '/[\r\n\t]+/', ' ', strip_tags( $s ) ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( $s ) ); }
function sanitize_email( $s ) { return trim( $s ); }
function wp_unslash( $s ) { return is_array( $s ) ? array_map( 'wp_unslash', $s ) : stripslashes( (string) $s ); }
function wp_slash( $s ) { return is_array( $s ) ? array_map( 'wp_slash', $s ) : addslashes( (string) $s ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return esc_html( $s ); }
function esc_textarea( $s ) { return esc_html( $s ); }
function esc_url( $s ) { return esc_attr( $s ); }
function esc_url_raw( $s ) { return $s; }
function wp_date( $f, $t = null ) { return date( $f, $t ?? strtotime( '2026-10-22 12:00:00' ) ); }
function current_time( $f ) { return 'timestamp' === $f ? strtotime( '2026-10-22 12:00:00' ) : wp_date( $f ); }
function current_user_can( ...$a ) { return $GLOBALS['allowed']; }
function check_admin_referer( ...$a ) { if ( ! $GLOBALS['nonce_valid'] ) throw new RuntimeException( 'Invalid nonce' ); }
function is_wp_error( $v ) { return false; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][$key] ?? $default; }
function add_option( $key, $value, ...$args ) { if ( array_key_exists( $key, $GLOBALS['options'] ) ) return false; $GLOBALS['options'][$key] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['options'][$key] ); }
function update_option( $key, $value ) { $GLOBALS['options'][$key] = $value; }
function get_post( $id ) { return $GLOBALS['posts'][$id] ?? null; }
function get_post_type( $id ) { return get_post( $id )->post_type ?? false; }
function get_post_status( $id ) { return get_post( $id )->post_status ?? false; }
function get_the_title( $id ) { return get_post( $id )->post_title ?? ''; }
function get_post_meta( $id, $key, $single = true ) { if ( ! $id ) return false; return $GLOBALS['meta'][$id][$key] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['meta'][$id][$key] = $value; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['meta'][$id][$key] ); }
function wp_insert_post( $data, ...$args ) { $id = $data['ID'] ?? count( $GLOBALS['posts'] ) + 1; $GLOBALS['posts'][$id] = (object) array_merge( array( 'post_date' => '2026-10-01', 'post_title' => '' ), (array) get_post( $id ), $data, array( 'ID' => $id ) ); return $id; }
function wp_update_post( $data ) { return wp_insert_post( $data ); }
function wp_trash_post( $id ) { $GLOBALS['posts'][$id]->post_status = 'trash'; }
function get_posts( $args ) {
	$result = array_values( array_filter( $GLOBALS['posts'], function ( $p ) use ( $args ) {
		if ( ( $args['post_type'] ?? '' ) !== $p->post_type || ( 'any' !== ( $args['post_status'] ?? 'publish' ) && ( $args['post_status'] ?? 'publish' ) !== $p->post_status ) ) return false;
		if ( isset( $args['meta_key'], $args['meta_value'] ) && (string) get_post_meta( $p->ID, $args['meta_key'] ) !== (string) $args['meta_value'] ) return false;
		foreach ( $args['meta_query'] ?? array() as $q ) if ( is_array( $q ) && (string) get_post_meta( $p->ID, $q['key'] ) !== (string) $q['value'] ) return false;
		return true;
	} ) );
	if ( ( $args['numberposts'] ?? -1 ) > 0 ) $result = array_slice( $result, 0, $args['numberposts'] );
	return 'ids' === ( $args['fields'] ?? '' ) ? array_column( $result, 'ID' ) : $result;
}
class WorkflowRedirect extends Exception {}
function wp_safe_redirect( $url ) { throw new WorkflowRedirect( $url ); }
function wp_die( $s ) { throw new RuntimeException( $s ); }
function admin_url( $s ) { return 'https://test.invalid/wp-admin/' . $s; }
function get_template_directory_uri() { return 'https://test.invalid/theme'; }
function wp_nonce_url( $s, ...$a ) { return $s; }
function wp_nonce_field( ...$a ) {}
function add_query_arg( $key, $value, $url ) { return $url . '&' . $key . '=' . $value; }
function selected( $a, $b ) { if ( (string) $a === (string) $b ) echo 'selected'; }
function disabled( $a, $b = true ) { if ( (bool) $a === (bool) $b ) echo 'disabled'; }
function wp_get_attachment_image_url( ...$a ) { return ''; }
function wp_create_nonce( ...$a ) { return 'nonce'; }
function get_the_date( $f, $post ) { return date( $f, strtotime( $post->post_date ) ); }
function get_permalink( $id ) { return 'https://test.invalid/brief/' . $id; }
function wp_generate_password( ...$a ) { return 'test-private-token'; }
function wp_mail( ...$a ) { $GLOBALS['notices']++; }
function is_email( $s ) { return (bool) filter_var( $s, FILTER_VALIDATE_EMAIL ); }
function home_url( $s = '' ) { return 'https://test.invalid' . $s; }
require CO_THEME_DIR . '/inc/ma-tracker.php';
require CO_THEME_DIR . '/inc/project-crm.php';
require CO_THEME_DIR . '/inc/sales-documents.php';
function expect_workflow( $ok, $label ) { if ( ! $ok ) throw new RuntimeException( 'FAIL: ' . $label ); }
function action_workflow( $name, $data ) {
	$_POST = $data; $_GET = $data;
	try { foreach ( $GLOBALS['actions'][$name] ?? array() as $fn ) $fn(); } catch ( WorkflowRedirect $e ) { parse_str( parse_url( $e->getMessage(), PHP_URL_QUERY ), $q ); return (int) ( $q['document'] ?? $q['open_project'] ?? 0 ); }
	throw new RuntimeException( 'No redirect for ' . $name );
}
function rejected_workflow( $fn, $label ) { $caught = false; try { $fn(); } catch ( RuntimeException $error ) { $caught = true; } expect_workflow( $caught, $label ); }
function document_workflow( $id, $project, $extra = array() ) {
	$quote = $id ? co_sales_meta( $id, 'document_type' ) : 'quotation';
	$data = array( 'document_id' => $id, 'document_type' => $quote, 'project_id' => $project, 'client_id' => 1, 'issue_date' => '2026-10-22', 'installment_no' => $id ? co_sales_meta( $id, 'installment_no', 0 ) : 0, 'source_document_id' => $id ? co_sales_meta( $id, 'source_document_id', 0 ) : 0, 'issuer_id' => 'default', 'document_title' => 'Project task', 'item_text' => array( "Project task :\n- พัฒนาเว็บไซต์" ), 'item_amount' => array( 20000 ), 'discount' => 2000, 'wht_rate' => 3, 'schedule_percent' => array( 50, 50 ), 'schedule_description' => array( 'เริ่มงาน', 'ส่งมอบงาน' ) );
	if ( $id && 'quotation' !== $quote ) { $row = co_sales_billing_schedule( $id )[0]; $data['schedule_percent'] = array( $row['percent'] ); $data['schedule_description'] = array( $row['description'] ); }
	return action_workflow( 'admin_post_co_sales_save_document', array_merge( $data, $extra ) );
}
$client = wp_insert_post( array( 'post_type' => 'sales_client', 'post_status' => 'publish', 'post_title' => 'บริษัทผู้ชำระเงิน' ) );
update_post_meta( $client, '_sales_short_name', 'TEST' );
$project = wp_insert_post( array( 'post_type' => 'client_project', 'post_status' => 'publish', 'post_title' => 'Website project' ) );
update_post_meta( $project, '_crm_company', 'เจ้าของงานคนละบริษัท' ); update_post_meta( $project, '_crm_stage', 'doing' ); update_post_meta( $project, '_crm_start_date', '2026-10-01' );
$quote = document_workflow( 0, $project, array( 'installment_no' => 99 ) );
expect_workflow( co_sales_meta( $quote, 'installment_no' ) === 0, 'Quotation is not an individual installment' );
expect_workflow( co_sales_meta( 0, 'issue_date', wp_date( 'Y-m-d' ) ) === '2026-10-22', 'new document metadata uses defaults for invalid ID zero' );
expect_workflow( co_project_quote( $project ) === 0, 'draft quote never becomes agreement automatically' );
rejected_workflow( fn() => co_project_derive_document( $quote, 1 ), 'select agreement before invoice' );
action_workflow( 'admin_post_co_project_accept_quote', array( 'project_id' => $project, 'quotation_id' => $quote ) );
expect_workflow( co_project_quote( $project ) === $quote && (float) co_crm_meta( $project, 'net_total' ) === 18000.0, 'QO controls discounted sale value' );
expect_workflow( co_project_financials( $project )['net'] === 17460.0, 'QO controls net value' );
expect_workflow( co_crm_meta( $project, 'stage' ) === 'doing', 'agreement does not move stage' );
document_workflow( $quote, $project, array( 'discount' => 3000 ) );
expect_workflow( (float) co_crm_meta( $project, 'net_total' ) === 17000.0, 'QO changes sync before first invoice' );
document_workflow( $quote, $project );
$invoice = co_project_derive_document( $quote, 1 );
expect_workflow( co_project_derive_document( $quote, 1 ) === $invoice, 'duplicate invoice opens same doc' );
expect_workflow( co_sales_grand_total( $invoice ) === 8730.0, 'invoice uses discounted installment' );
$count = count( $posts ); expect_workflow( co_project_derive_document( $invoice ) === 0 && count( $posts ) === $count, 'receipt requires payment before creating anything' );
rejected_workflow( fn() => document_workflow( $quote, $project, array( 'discount' => 3000 ) ), 'locked QO rejects financial edits' );
rejected_workflow( fn() => document_workflow( $invoice, $project, array( 'schedule_percent' => array( 40 ) ) ), 'invoice respects agreed installment' );
document_workflow( $quote, $project, array( 'item_text' => array( 'ปรับรายละเอียดโดยไม่แก้ราคา' ) ) );
$payment = co_project_record_payment( $invoice, '2025-12-31' );
expect_workflow( co_project_record_payment( $invoice, '2026-01-01' ) === $payment, 'duplicate receive does not create payment' );
expect_workflow( co_project_financials( $project )['received'] === 8730.0 && co_project_financials( $project )['outstanding'] === 8730.0, 'cash counted before receipt' );
$receipt = co_project_derive_document( $invoice );
expect_workflow( co_sales_meta( $receipt, 'payment_id' ) === $payment && count( co_crm_payments( $project ) ) === 1, 'receipt reuses payment' );
expect_workflow( co_project_derive_document( $invoice ) === $receipt, 'duplicate receipt prevented' );
document_workflow( $receipt, $project, array( 'issue_date' => '2026-10-23' ) );
expect_workflow( get_post_meta( $payment, '_crm_paid_date', true ) === '2025-12-31' && get_post_meta( $payment, '_crm_amount', true ) === 8730.0, 'receipt issue-date edits never move cash across years' );
$second = co_project_derive_document( $quote, 2 );
update_post_meta( $receipt, '_sales_source_document_id', $second ); co_sales_sync_payment( $receipt );
expect_workflow( (int) get_post_meta( $payment, '_crm_invoice_id', true ) === $invoice, 'legacy receipt cannot steal another invoice payment' );
update_post_meta( $receipt, '_sales_source_document_id', $invoice );
$legacy = wp_insert_post( array( 'post_type' => 'project_payment', 'post_status' => 'publish', 'post_title' => 'งวดเดิมที่เคยกรอก' ) );
update_post_meta( $legacy, '_crm_project_id', $project ); update_post_meta( $legacy, '_crm_amount', 8730 ); update_post_meta( $legacy, '_crm_paid_date', '2026-09-01' );
rejected_workflow( fn() => co_project_record_payment( $second, '2026-10-22' ), 'unmatched legacy payments require review' );
expect_workflow( co_project_record_payment( $second, '2026-10-22', $legacy ) === $legacy, 'explicit match uses existing payment' );
expect_workflow( get_post_meta( $legacy, '_crm_paid_date', true ) === '2026-09-01' && count( co_crm_payments( $project ) ) === 2, 'matching keeps date and does not add cash' );
expect_workflow( co_project_financials( $project )['outstanding'] == 0, 'contract fully paid with second agreed installment' );
$other_quote = document_workflow( 0, $project );
rejected_workflow( fn() => action_workflow( 'admin_post_co_project_accept_quote', array( 'project_id' => $project, 'quotation_id' => $other_quote ) ), 'cannot replace agreement after invoices' );
rejected_workflow( fn() => action_workflow( 'admin_post_co_crm_delete_project', array( 'project_id' => $project ) ), 'cannot trash project financial history' );
$zero_project = wp_insert_post( array( 'post_type' => 'client_project', 'post_status' => 'publish', 'post_title' => 'No withholding project' ) ); update_post_meta( $zero_project, '_crm_stage', 'doing' );
$zero_quote = document_workflow( 0, $zero_project, array( 'wht_rate' => 0 ) ); action_workflow( 'admin_post_co_project_accept_quote', array( 'project_id' => $zero_project, 'quotation_id' => $zero_quote ) );
expect_workflow( co_project_financials( $zero_project )['net'] === 18000.0, 'project uses actual QO withholding, not hardcoded 3%' );
$ma_client = wp_insert_post( array( 'post_type' => 'ma_client', 'post_status' => 'publish', 'post_title' => 'บริษัท MA คนละชื่อ' ) );
update_post_meta( $ma_client, '_ma_purchased_hours', 20 ); update_post_meta( $ma_client, '_ma_start_date', '2026-01-01' ); update_post_meta( $ma_client, '_ma_expiry_date', '2026-10-25' );
$ma_stream = wp_insert_post( array( 'post_type' => 'ma_project', 'post_status' => 'publish', 'post_title' => 'โปรเจกต์เดิม' ) ); update_post_meta( $ma_stream, '_ma_client_id', $ma_client );
$count = count( $posts ); co_project_start_ma( $project, array( 'ma_client_id' => $ma_client, 'ma_project_id' => $ma_stream ) );
expect_workflow( count( $posts ) === $count && co_crm_meta( $project, 'ma_project_id' ) === $ma_stream && co_crm_meta( $project, 'stage' ) === 'ma', 'explicit MA link reuses package and workstream' );
expect_workflow( co_ma_meta( $ma_client, 'purchased_hours' ) === 20 && co_ma_meta( $ma_client, 'start_date' ) === '2026-01-01', 'MA balances and dates preserved' );
expect_workflow( co_project_start_ma( $project, array() ) === $ma_client, 'repeated MA start is idempotent' );
rejected_workflow( fn() => co_project_start_ma( $zero_project, array( 'ma_client_id' => $ma_client, 'ma_project_id' => $ma_stream ) ), 'cannot steal another project MA link' );
$allowed = false; rejected_workflow( fn() => action_workflow( 'admin_post_co_project_receive', array( 'invoice_id' => $second ) ), 'receive endpoint requires permission' ); $allowed = true;
$nonce_valid = false; rejected_workflow( fn() => action_workflow( 'admin_post_co_project_receive', array( 'invoice_id' => $second ) ), 'receive endpoint requires nonce' ); $nonce_valid = true;
expect_workflow( count( co_crm_stages() ) === 7, 'seven existing work stages retained' );
update_option( 'admin_email', 'admin@example.com' );
$before_ma = co_project_financials( $zero_project );
$new_ma = co_project_start_ma( $zero_project, array( 'start_date' => '2026-10-22', 'hours' => 20, 'duration' => '1y', 'package_type' => 'monthly_retainer', 'ma_company_name' => 'New MA company' ) );
expect_workflow( co_ma_meta( $new_ma, 'expiry_date' ) === '2027-10-22' && co_ma_package_type( $new_ma ) === 'monthly_retainer' && $notices === 1, 'new MA dates, package, calendar source and admin notification' );
expect_workflow( co_project_financials( $zero_project ) === $before_ma, 'included MA does not add sale or cash' );
$pending_project = wp_insert_post( array( 'post_type' => 'client_project', 'post_status' => 'publish', 'post_title' => 'Pending legacy reconciliation' ) ); update_post_meta( $pending_project, '_crm_stage', 'doing' );
action_workflow( 'admin_post_co_project_client', array( 'project_id' => $pending_project, 'sales_client_id' => 0 ) );
$created_client = absint( co_crm_meta( $pending_project, 'sales_client_id' ) ); $client_count = count( co_sales_clients() );
action_workflow( 'admin_post_co_project_client', array( 'project_id' => $pending_project, 'sales_client_id' => 0 ) );
expect_workflow( count( co_sales_clients() ) === $client_count, 'repeated create client from project does not duplicate' );
$pending_quote = document_workflow( 0, $pending_project, array( 'client_id' => $created_client ) ); action_workflow( 'admin_post_co_project_accept_quote', array( 'project_id' => $pending_project, 'quotation_id' => $pending_quote ) );
$pending_invoice = co_project_derive_document( $pending_quote );
$pending_legacy = wp_insert_post( array( 'post_type' => 'project_payment', 'post_status' => 'publish', 'post_title' => 'Legacy pending match' ) ); update_post_meta( $pending_legacy, '_crm_project_id', $pending_project ); update_post_meta( $pending_legacy, '_crm_amount', 8730 ); update_post_meta( $pending_legacy, '_crm_paid_date', '2025-11-01' );
add_option( 'co_billing_lock_invoice_payment_' . $pending_invoice, time() );
rejected_workflow( fn() => co_project_record_payment( $pending_invoice, '2026-10-22', $pending_legacy ), 'concurrent receive request blocked' ); delete_option( 'co_billing_lock_invoice_payment_' . $pending_invoice );
rejected_workflow( fn() => co_project_record_payment( $pending_invoice, '2026-02-30', 0, '', true ), 'invalid received date rejected' );
rejected_workflow( fn() => co_project_record_payment( $pending_invoice, '2026-10-22', $payment ), 'cannot attach payment from another project' );
foreach ( array( 18000.01, .02 ) as $decimal ) {
	$money_quote = document_workflow( 0, 0, array( 'item_amount' => array( $decimal ), 'discount' => 0, 'schedule_percent' => array( 25, 25, 25, 25 ), 'schedule_description' => array( 'a', 'b', 'c', 'd' ) ) );
	$sum = $tax = $net = 0;
	for ( $n = 1; $n <= 4; $n++ ) { $part = co_project_derive_document( $money_quote, $n ); $sum += co_sales_document_total( $part ); $tax += co_sales_wht( $part ); $net += co_sales_grand_total( $part ); expect_workflow( co_sales_document_total( $part ) >= 0 && co_sales_grand_total( $part ) >= 0, 'nonnegative rounded installment' ); }
	expect_workflow( round( $sum, 2 ) === $decimal && round( $tax, 2 ) === co_sales_wht( $money_quote ) && round( $net, 2 ) == co_sales_grand_total( $money_quote ), 'installment cents and withholding sum exactly to QO' );
}
$empty_project = wp_insert_post( array( 'post_type' => 'client_project', 'post_status' => 'publish', 'post_title' => 'Project without documents' ) );
action_workflow( 'admin_post_co_project_client', array( 'project_id' => $empty_project, 'sales_client_id' => $client ) );
expect_workflow( (int) co_crm_meta( $empty_project, 'sales_client_id' ) === $client, 'billing client button links selected client' );
$_GET = array( 'client_linked' => $empty_project ); ob_start(); co_project_render_billing( $empty_project ); $empty_hub = ob_get_clean();
expect_workflow( str_contains( $empty_hub, 'role="status"' ) && str_contains( $empty_hub, 'เชื่อมลูกค้าสำหรับออกบิลแล้ว' ), 'client link displays scoped success notice' );
expect_workflow( str_contains( $empty_hub, 'ยังไม่มีเอกสารที่เชื่อมกับ Project นี้' ) && str_contains( $empty_hub, 'co-sales-edit&amp;project=' . $empty_project ), 'empty documents show explanation and create action' );
$_GET = array( 'project' => $empty_project ); ob_start(); co_sales_edit_page(); $new_quote_form = ob_get_clean();
expect_workflow( str_contains( $new_quote_form, 'name="issue_date" value="2026-10-22"' ), 'new Quotation date preselected today' );
expect_workflow( str_contains( $new_quote_form, 'data-document-installment hidden' ) && str_contains( $new_quote_form, 'type="hidden" name="installment_no" value="0"' ), 'no individual installment input on Quotation' );
expect_workflow( str_contains( $new_quote_form, 'data-sales-schedule' ), 'existing Terms of Payment remains' );
$_GET = array( 'document' => $quote ); update_post_meta( $quote, '_sales_issue_date', '2026-10-14' ); ob_start(); co_sales_edit_page(); $saved_quote_form = ob_get_clean();
expect_workflow( str_contains( $saved_quote_form, 'name="issue_date" value="2026-10-14"' ), 'saved document keeps historical issue date' );
update_post_meta( $quote, '_sales_issue_date', '2026-10-22' );
$mode = $argv[1] ?? 'test';
if ( $mode !== 'test' ) {
	$_GET = array( 'project' => $project, 'document' => $invoice );
	echo '<!doctype html><html><head><meta charset="utf-8"><style>body{margin:0;background:#f3f6fb;font:14px Tahoma,sans-serif}</style></head><body>';
	if ( $mode === 'pipeline' ) co_crm_dashboard();
	elseif ( $mode === 'detail' ) co_crm_detail();
	elseif ( $mode === 'quote' ) { $_GET['document'] = $quote; co_sales_edit_page(); }
	elseif ( $mode === 'new-quote' || $mode === 'new-invoice' ) { $_GET = array( 'type' => $mode === 'new-quote' ? 'quotation' : 'invoice' ); co_sales_edit_page(); }
	elseif ( $mode === 'empty-project' ) { $_GET = array( 'client_linked' => $empty_project ); co_crm_assets(); echo $empty_hub; }
	elseif ( $mode === 'pending-invoice' ) { $_GET['document'] = $second; co_sales_edit_page(); }
	elseif ( $mode === 'unpaid-invoice' ) { $_GET['document'] = $pending_invoice; co_sales_edit_page(); }
	elseif ( $mode === 'report' ) { $_GET['report_year'] = 2026; co_crm_report(); }
	elseif ( str_starts_with( $mode, 'preview' ) ) { $preview = str_contains( $mode, 'quotation' ) ? $quote : ( str_contains( $mode, 'invoice' ) ? $invoice : $receipt ); $_GET['document'] = $preview; $items = co_sales_default_items(); $items[0]['amount'] = 20000; update_post_meta( $preview, '_sales_items', $items ); co_sales_preview_page(); }
	else co_sales_edit_page();
	echo '</body></html>';
} else echo "PASS: agreed QO, discounts, installments, financial locks, actual cash before receipt, duplicate protection, legacy matching, cash dates, MA reuse, permissions and 7 work stages\n";
