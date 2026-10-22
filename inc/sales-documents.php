<?php
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/sales-template.php';
require_once __DIR__ . '/sales-pages.php';
require_once __DIR__ . '/sales-billing.php';
require_once __DIR__ . '/project-billing.php';
require_once __DIR__ . '/project-maintenance.php';

function co_sales_register_types() {
	register_post_type( 'sales_client', array( 'labels' => array( 'name' => 'Sales Clients', 'singular_name' => 'Sales Client' ), 'public' => false, 'show_ui' => false, 'supports' => array( 'title' ) ) );
	register_post_type( 'sales_document', array( 'labels' => array( 'name' => 'Sales Documents', 'singular_name' => 'Sales Document' ), 'public' => false, 'show_ui' => false, 'supports' => array( 'title' ) ) );
}
add_action( 'init', 'co_sales_register_types' );

add_action( 'admin_menu', function () {
	add_menu_page( 'Sales Documents', 'Sales Documents', 'edit_posts', 'co-sales-documents', 'co_sales_documents_page', 'dashicons-media-spreadsheet', 23 );
	add_submenu_page( 'co-sales-documents', 'Documents', 'Documents', 'edit_posts', 'co-sales-documents', 'co_sales_documents_page' );
	add_submenu_page( 'co-sales-documents', 'Clients', 'Clients', 'edit_posts', 'co-sales-clients', 'co_sales_clients_page' );
	add_submenu_page( 'co-sales-documents', 'Issuer Settings', 'Issuer Settings', 'manage_options', 'co-sales-settings', 'co_sales_settings_page' );
	add_submenu_page( null, 'Edit Sales Document', 'Edit Sales Document', 'edit_posts', 'co-sales-edit', 'co_sales_edit_page' );
	add_submenu_page( null, 'Preview Sales Document', 'Preview Sales Document', 'edit_posts', 'co-sales-preview', 'co_sales_preview_page' );
} );

add_action( 'admin_enqueue_scripts', function () {
	if ( 'co-projects' !== sanitize_key( $_GET['page'] ?? '' ) && 'co-project-detail' !== sanitize_key( $_GET['page'] ?? '' ) ) return;
	wp_enqueue_style( 'co-sales-documents', get_template_directory_uri() . '/assets/sales-documents.css', array(), filemtime( CO_THEME_DIR . '/assets/sales-documents.css' ) );
} );

function co_sales_meta( $id, $key, $default = '' ) { $value = get_post_meta( $id, '_sales_' . $key, true ); return '' === $value ? $default : $value; }
function co_sales_clients() { return get_posts( array( 'post_type' => 'sales_client', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ); }
function co_sales_documents( $project_id = 0 ) { $args = array( 'post_type' => 'sales_document', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'date', 'order' => 'DESC' ); if ( $project_id ) $args['meta_query'] = array( array( 'key' => '_sales_project_id', 'value' => $project_id ) ); return get_posts( $args ); }
function co_sales_types() { return array( 'quotation' => 'Quotation', 'invoice' => 'Invoice', 'receipt' => 'Receipt' ); }
function co_sales_statuses() { return array( 'draft' => 'Draft', 'issued' => 'Issued', 'accepted' => 'Accepted', 'paid' => 'Paid', 'cancelled' => 'Cancelled' ); }
function co_sales_money( $value ) { return number_format( (float) $value, 2 ); }
function co_sales_date( $date ) { return $date && strtotime( $date ) ? wp_date( 'd/m/Y', strtotime( $date ) ) : '—'; }
function co_sales_document_total( $id ) { return (float) co_sales_meta( $id, 'subtotal', 0 ); }
function co_sales_wht( $id ) { $stored = co_sales_meta( $id, 'wht_amount' ); return 'quotation' !== co_sales_meta( $id, 'document_type' ) && '' !== $stored ? (float) $stored : round( co_sales_document_total( $id ) * (float) co_sales_meta( $id, 'wht_rate', 3 ) / 100, 2 ); }
function co_sales_grand_total( $id ) { return max( 0, co_sales_document_total( $id ) - co_sales_wht( $id ) ); }
function co_sales_client_short( $client_id ) {
	$short = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) co_sales_meta( $client_id, 'short_name' ) ) );
	return $short ?: 'CL' . absint( $client_id );
}
function co_sales_next_number( $type, $client_id, $date, $installment = 0 ) {
	$prefix = 'quotation' === $type ? 'QO' : ( 'invoice' === $type ? 'IV' . max( 1, absint( $installment ) ) : 'RC' . max( 1, absint( $installment ) ) );
	$base = $prefix . '_' . co_sales_client_short( $client_id ) . wp_date( 'Ymd', strtotime( $date ?: 'now' ) ); $number = $base; $index = 2;
	while ( get_posts( array( 'post_type' => 'sales_document', 'post_status' => 'publish', 'numberposts' => 1, 'meta_key' => '_sales_number', 'meta_value' => $number ) ) ) $number = $base . '-' . str_pad( $index++, 2, '0', STR_PAD_LEFT );
	return $number;
}
function co_sales_default_concept() {
	return "Project Concept :\nWeb Strategic Planning :\n- Creative Strategic Direction\n- Communication Strategy\n- User Behavior Flow (UX Strategy)\n- Customer Experience\n- Brand Perception\n- Engagement & Conversion\n- Measurement & Analysis\n- Optimization\n- Competitor Analysis\n- Website Benchmark\n- Scope of Works\n- Wireframe\n\nProject Execution :\nWebsite Branding - ออกแบบและพัฒนาเว็บไซต์นำเสนอภาพลักษณ์แบรนด์\n- Responsive Design\n- PDPA\n- User Manual Website\n\nRetaining & Maintenance :\n- Website Warranty\n- Technical Support\n- Content Update\n- Compatibility and Performance Monitoring";
}
function co_sales_parse_schedule( $text ) {
	$rows = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
		// Accept the original internal format and ordinary text entered in older forms.
		if ( ! preg_match( '/^\s*(\d+(?:\.\d+)?)\s*(?:\||%\s*|\s+)(.*)$/u', $line, $match ) ) {
			if ( ! preg_match( '/^\s*(\d+(?:\.\d+)?)\s*$/', $line, $match ) ) continue;
		}
		$percent = (float) $match[1];
		if ( $percent > 0 && $percent <= 100 ) $rows[] = array( 'percent' => $percent, 'description' => sanitize_text_field( trim( $match[2] ?? '' ) ) );
	}
	return $rows;
}

function co_sales_schedule( $id ) {
	$text = co_sales_meta( $id, 'payment_schedule_text' );
	$parsed = co_sales_parse_schedule( $text );
	return $parsed ?: (array) co_sales_meta( $id, 'payment_schedule', array() );
}
function co_sales_issuers() {
	$issuers = get_option( 'co_sales_issuers', array() );
	if ( ! $issuers ) $issuers = array( array( 'id' => 'default', 'name' => '', 'address' => '', 'tax_id' => '', 'phone' => '', 'email' => '', 'bank' => '', 'signature_id' => 0 ) );
	return $issuers;
}
function co_sales_issuer( $id ) { foreach ( co_sales_issuers() as $issuer ) if ( (string) $issuer['id'] === (string) $id ) return $issuer; return co_sales_issuers()[0]; }
function co_sales_find_derived_document( $source_id, $type, $installment ) {
	$documents = get_posts( array(
		'post_type'      => 'sales_document',
		'post_status'    => 'publish',
		'numberposts'    => 1,
		'fields'         => 'ids',
		'meta_query'     => array(
			'relation' => 'AND',
			array( 'key' => '_sales_source_document_id', 'value' => absint( $source_id ) ),
			array( 'key' => '_sales_document_type', 'value' => sanitize_key( $type ) ),
			array( 'key' => '_sales_installment_no', 'value' => absint( $installment ) ),
		),
	) );
	return $documents ? absint( $documents[0] ) : 0;
}

function co_sales_assets() { ?>
	<style><?php include CO_THEME_DIR . '/assets/sales-documents.css'; include CO_THEME_DIR . '/assets/sales-print.css'; include CO_THEME_DIR . '/assets/project-billing.css'; ?></style>
<script src="<?php echo esc_url( get_template_directory_uri() . '/assets/sales-documents.js?v=' . filemtime( CO_THEME_DIR . '/assets/sales-documents.js' ) ); ?>" defer></script>
<script src="<?php echo esc_url( get_template_directory_uri() . '/assets/project-billing.js?v=' . filemtime( CO_THEME_DIR . '/assets/project-billing.js' ) ); ?>" defer></script>
	<script>document.addEventListener('DOMContentLoaded',()=>{const q=(s,p=document)=>p.querySelector(s);document.querySelectorAll('[data-sales-confirm]').forEach(a=>a.addEventListener('click',e=>{if(!confirm(a.dataset.salesConfirm))e.preventDefault()}));const client=q('#sales-client'),project=q('#sales-project');project?.addEventListener('change',()=>{const o=project.selectedOptions[0];if(o?.dataset.client&&client&&!client.value)client.value=o.dataset.client});q('[data-add-issuer]')?.addEventListener('click',()=>{const list=q('.sales-issuer-list'),row=list.querySelector('.sales-issuer-row').cloneNode(true);row.querySelectorAll('input,textarea').forEach(i=>{i.value='';if(i.name==='issuer_id[]')i.value='issuer_'+Date.now()});row.querySelector('[data-signature-preview]')?.replaceChildren();list.appendChild(row)});document.addEventListener('click',e=>{const remove=e.target.closest('[data-remove-issuer]');if(remove&&document.querySelectorAll('.sales-issuer-row').length>1)remove.closest('.sales-issuer-row').remove();const pick=e.target.closest('[data-pick-signature]');if(pick&&window.wp?.media){e.preventDefault();const row=pick.closest('.sales-issuer-row'),frame=wp.media({title:'Choose signature',button:{text:'Use signature'},multiple:false});frame.on('select',()=>{const item=frame.state().get('selection').first().toJSON();row.querySelector('[name="issuer_signature_id[]"]').value=item.id;row.querySelector('[data-signature-preview]').innerHTML='<img src="'+(item.sizes?.medium?.url||item.url)+'" alt="Signature">'});frame.open()}})});</script>
<?php }

add_action( 'admin_post_co_sales_save_client', function () {
	if ( ! current_user_can( 'edit_posts' ) ) wp_die( 'Permission denied.' ); check_admin_referer( 'co_sales_client' ); $id = absint( $_POST['client_id'] ?? 0 ); $name = sanitize_text_field( wp_unslash( $_POST['company_name'] ?? '' ) ); if ( ! $name ) wp_die( 'Company name is required.' );
	if ( $id && 'sales_client' === get_post_type( $id ) ) wp_update_post( array( 'ID' => $id, 'post_title' => $name ) ); else $id = wp_insert_post( array( 'post_type' => 'sales_client', 'post_status' => 'publish', 'post_title' => $name ) );
	foreach ( array( 'short_name','company_name_en','address','tax_id','contact_name','phone','email' ) as $field ) update_post_meta( $id, '_sales_' . $field, 'email' === $field ? sanitize_email( wp_unslash( $_POST[$field] ?? '' ) ) : sanitize_textarea_field( wp_unslash( $_POST[$field] ?? '' ) ) );
	wp_safe_redirect( admin_url( 'admin.php?page=co-sales-clients&saved=1' ) ); exit;
} );

add_action( 'admin_post_co_sales_save_settings', function () {
	if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Permission denied.' ); check_admin_referer( 'co_sales_settings' ); $issuers = array(); $names = (array) ( $_POST['issuer_name'] ?? array() );
	foreach ( $names as $i => $name ) { $name = sanitize_text_field( wp_unslash( $name ) ); if ( ! $name ) continue; $issuers[] = array( 'id' => sanitize_key( $_POST['issuer_id'][$i] ?? wp_generate_uuid4() ), 'name' => $name, 'address' => sanitize_textarea_field( wp_unslash( $_POST['issuer_address'][$i] ?? '' ) ), 'tax_id' => sanitize_text_field( wp_unslash( $_POST['issuer_tax_id'][$i] ?? '' ) ), 'phone' => sanitize_text_field( wp_unslash( $_POST['issuer_phone'][$i] ?? '' ) ), 'email' => sanitize_email( wp_unslash( $_POST['issuer_email'][$i] ?? '' ) ), 'bank' => sanitize_textarea_field( wp_unslash( $_POST['issuer_bank'][$i] ?? '' ) ), 'signature_id' => absint( $_POST['issuer_signature_id'][$i] ?? 0 ) ); }
	update_option( 'co_sales_issuers', $issuers ); wp_safe_redirect( admin_url( 'admin.php?page=co-sales-settings&saved=1' ) ); exit;
} );

function co_sales_save_document_data() {
	$id = absint( $_POST['document_id'] ?? 0 ); $type = sanitize_key( $_POST['document_type'] ?? 'quotation' ); if ( ! isset( co_sales_types()[$type] ) ) $type = 'quotation'; $client_id = absint( $_POST['client_id'] ?? 0 ); if ( 'sales_client' !== get_post_type( $client_id ) ) throw new RuntimeException( 'Select a valid client.' ); $date = sanitize_text_field( $_POST['issue_date'] ?? wp_date( 'Y-m-d' ) ); $installment = absint( $_POST['installment_no'] ?? 0 );
	if ( $id && 'sales_document' !== get_post_type( $id ) ) throw new RuntimeException( 'Invalid document.' );
	if ( $id ) $type = co_sales_meta( $id, 'document_type' );
	$project_id = absint( $_POST['project_id'] ?? 0 );
	if ( $project_id && 'client_project' !== get_post_type( $project_id ) ) throw new RuntimeException( 'Project ไม่ถูกต้อง' );
	$old_project = $id ? absint( co_sales_meta( $id, 'project_id' ) ) : 0;
	if ( $old_project && co_project_quote( $old_project ) === $id && $project_id !== $old_project ) throw new RuntimeException( 'ไม่สามารถย้าย QO หลักออกจาก Project ที่ตกลงไว้' );
	if ( ! $id && $project_id && 'invoice' === $type ) throw new RuntimeException( 'กรุณาออก Invoice จากตารางงวดของ QO หลักใน Project' );
	if ( 'quotation' !== $type ) $installment = max( 1, $installment );
	$parsed_date = DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
	if ( ! $parsed_date || $parsed_date->format( 'Y-m-d' ) !== $date ) throw new RuntimeException( 'วันที่เอกสารไม่ถูกต้อง' );
	$schedule = array();
	if ( isset( $_POST['schedule_percent'] ) ) {
		foreach ( (array) $_POST['schedule_percent'] as $i => $percent ) {
			$description = sanitize_text_field( wp_unslash( $_POST['schedule_description'][$i] ?? '' ) );
			if ( '' === trim( (string) $percent ) && '' === $description ) continue;
			if ( ! is_numeric( $percent ) || (float) $percent <= 0 || (float) $percent > 100 ) throw new RuntimeException( 'เปอร์เซ็นต์แต่ละงวดต้องมากกว่า 0 และไม่เกิน 100' );
			if ( '' === $description ) throw new RuntimeException( 'กรุณาระบุเงื่อนไขการชำระของแต่ละงวด' );
			$schedule[] = array( 'percent' => (float) $percent, 'description' => $description );
		}
		$_POST['payment_schedule'] = wp_slash( implode( "\n", array_map( fn( $row ) => $row['percent'] . '|' . $row['description'], $schedule ) ) );
	} else {
		$schedule = co_sales_parse_schedule( wp_unslash( $_POST['payment_schedule'] ?? '' ) );
	}
	if ( 'quotation' === $type && $schedule && abs( array_sum( array_column( $schedule, 'percent' ) ) - 100 ) > 0.001 ) throw new RuntimeException( 'เปอร์เซ็นต์งวดชำระต้องรวมเป็น 100%' );
	if ( 'quotation' !== $type && count( $schedule ) > 1 ) $schedule = array( $schedule[max( 0, $installment - 1 )] ?? $schedule[0] );
	if ( 'quotation' !== $type && ! $schedule ) throw new RuntimeException( 'กรุณาระบุเปอร์เซ็นต์และเงื่อนไขของงวดนี้' );
	$_POST['payment_schedule'] = wp_slash( implode( "\n", array_map( fn( $row ) => $row['percent'] . '|' . $row['description'], $schedule ) ) );
	$items = array();
	foreach ( (array) ( $_POST['item_text'] ?? array() ) as $i => $text ) {
		$rich = 'rich' === ( $_POST['item_format'][$i] ?? '' );
		$clean = $rich ? implode( '<br>', array_column( co_sales_rich_lines( wp_unslash( $text ) ), 'html' ) ) : sanitize_textarea_field( wp_unslash( $text ) );
		$items[] = array( 'text' => $clean, 'format' => $rich ? 'rich' : 'plain', 'amount' => '' === ( $_POST['item_amount'][$i] ?? '' ) ? '' : max( 0, round( (float) $_POST['item_amount'][$i], 2 ) ) );
	}
	$base_total = co_sales_items_value( $items );
	$discount = $_POST['discount'] ?? co_sales_meta( $id, 'discount', 0 );
	if ( '' === $discount ) $discount = 0;
	if ( ! is_numeric( $discount ) || ! is_finite( (float) $discount ) || (float) $discount < 0 || round( (float) $discount, 2 ) > $base_total ) throw new RuntimeException( 'ส่วนลดต้องเป็นจำนวนเงินตั้งแต่ 0 และไม่เกินมูลค่ารวมรายการ' );
	$discount = round( (float) $discount, 2 );
	co_sales_assert_financials( $id, $items, $discount, $schedule );
	if ( ! $id && 'receipt' === $type ) throw new RuntimeException( 'กรุณาออก Receipt จาก Invoice ที่บันทึกรับเงินแล้ว' );
	if ( $id && 'sales_document' === get_post_type( $id ) ) { $number = co_sales_meta( $id, 'number' ); wp_update_post( array( 'ID' => $id, 'post_title' => $number ) ); } else { list( $id, $number ) = co_sales_create_numbered_document( $type, $client_id, $date, $installment ); }
	if ( is_wp_error( $id ) || ! $id ) throw new RuntimeException( 'ไม่สามารถบันทึกเอกสารได้' );
	$issuer = co_sales_issuer( sanitize_key( $_POST['issuer_id'] ?? '' ) ); $client = get_post( $client_id );
	if ( $items ) update_post_meta( $id, '_sales_items', $items );
	update_post_meta( $id, '_sales_body', implode( "\n\n", array_map( fn( $item ) => implode( "\n", array_column( co_sales_description_lines( $item ), 'text' ) ), $items ) ) );
	$percent = 'quotation' === $type ? 100 : (float) $schedule[0]['percent'];
	$_POST['subtotal'] = round( co_sales_installment_value( co_sales_discounted_base( $base_total, $discount ), $percent ) + (float) co_sales_meta( $id, 'rounding_adjustment', 0 ), 2 );
	update_post_meta( $id, '_sales_base_total', $base_total );
	update_post_meta( $id, '_sales_discount', $discount );
	update_post_meta( $id, '_sales_installment_percent', $percent );
	$title = sanitize_text_field( wp_unslash( $_POST['document_title'] ?? '' ) );
	if ( 'quotation' !== $type && ( '' === $title || preg_match( '/^งวดที่ \d+\s*:/u', $title ) ) ) $_POST['document_title'] = wp_slash( 'งวดที่ ' . $installment . ' : ' . $percent . '% ' . $schedule[0]['description'] );
	$old_snapshot = co_sales_meta( $id, 'client_snapshot' );
	if ( $old_snapshot && (int) co_sales_meta( $id, 'client_id' ) === $client_id ) $preserve_client = $old_snapshot;
	$old_issuer = co_sales_meta( $id, 'issuer_snapshot' );
	if ( $old_issuer && co_sales_meta( $id, 'issuer_id' ) === $issuer['id'] ) $issuer = $old_issuer;
	$snapshot = array( 'name' => $client->post_title, 'name_en' => co_sales_meta( $client_id, 'company_name_en' ), 'address' => co_sales_meta( $client_id, 'address' ), 'tax_id' => co_sales_meta( $client_id, 'tax_id' ), 'contact_name' => co_sales_meta( $client_id, 'contact_name' ), 'phone' => co_sales_meta( $client_id, 'phone' ), 'email' => co_sales_meta( $client_id, 'email' ) );
	update_post_meta( $id, '_sales_number', $number ); update_post_meta( $id, '_sales_document_type', $type ); update_post_meta( $id, '_sales_client_id', $client_id ); update_post_meta( $id, '_sales_project_id', absint( $_POST['project_id'] ?? 0 ) ); update_post_meta( $id, '_sales_source_document_id', absint( $_POST['source_document_id'] ?? 0 ) ); update_post_meta( $id, '_sales_installment_no', $installment ); update_post_meta( $id, '_sales_issue_date', $date ); update_post_meta( $id, '_sales_status', isset( co_sales_statuses()[sanitize_key( $_POST['document_status'] ?? '' )] ) ? sanitize_key( $_POST['document_status'] ) : 'draft' ); update_post_meta( $id, '_sales_client_snapshot', $preserve_client ?? $snapshot ); update_post_meta( $id, '_sales_issuer_id', sanitize_key( $_POST['issuer_id'] ?? '' ) ); update_post_meta( $id, '_sales_issuer_snapshot', $issuer ); update_post_meta( $id, '_sales_title', sanitize_text_field( wp_unslash( $_POST['document_title'] ?? '' ) ) );  update_post_meta( $id, '_sales_subtotal', max( 0, (float) ( $_POST['subtotal'] ?? 0 ) ) ); update_post_meta( $id, '_sales_wht_rate', min( 100, max( 0, (float) ( $_POST['wht_rate'] ?? 3 ) ) ) ); update_post_meta( $id, '_sales_payment_schedule', co_sales_parse_schedule( wp_unslash( $_POST['payment_schedule'] ?? '' ) ) ); update_post_meta( $id, '_sales_payment_schedule_text', sanitize_textarea_field( wp_unslash( $_POST['payment_schedule'] ?? '' ) ) ); update_post_meta( $id, '_sales_notes', sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ) );
	co_sales_sync_payment( $id );
	if ( 'quotation' === $type ) co_project_sync_quote( $id );
	return $id;
}

add_action( 'admin_post_co_sales_save_document', function () {
	if ( ! current_user_can( 'edit_posts' ) ) wp_die( 'Permission denied.' ); check_admin_referer( 'co_sales_document' );
	$id = absint( $_POST['document_id'] ?? 0 );
	$project = $id ? absint( co_sales_meta( $id, 'project_id' ) ) : absint( $_POST['project_id'] ?? 0 );
	$type = $id ? co_sales_meta( $id, 'document_type' ) : sanitize_key( $_POST['document_type'] ?? 'quotation' );
	if ( ! isset( co_sales_types()[$type] ) ) $type = 'quotation';
	$save = fn() => $id ? co_project_locked( 'document_' . $id, 'co_sales_save_document_data' ) : co_sales_save_document_data();
	try { $id = $project && 'quotation' === $type ? co_project_locked( 'project_quote_' . $project, $save ) : $save(); }
	catch ( RuntimeException $error ) { wp_die( esc_html( $error->getMessage() ) ); }
	$page = ! empty( $_POST['preview_after_save'] ) ? 'co-sales-preview' : 'co-sales-edit';
	wp_safe_redirect( admin_url( 'admin.php?page=' . $page . '&document=' . $id . '&saved=1' ) ); exit;
} );

add_action( 'admin_post_co_sales_derive_document', function () {
	if ( ! current_user_can( 'edit_posts' ) ) wp_die( 'Permission denied.' );
	$source = absint( $_GET['source'] ?? 0 ); check_admin_referer( 'co_sales_derive_' . $source );
	try { $id = co_project_derive_document( $source, absint( $_GET['installment'] ?? co_sales_meta( $source, 'installment_no', 1 ) ) ); }
	catch ( RuntimeException $error ) { wp_die( esc_html( $error->getMessage() ) ); }
	wp_safe_redirect( admin_url( 'admin.php?page=co-sales-edit&document=' . ( $id ?: $source ) . ( $id ? '' : '&needs_payment=1#invoice-payment' ) ) ); exit;
} );

function co_sales_clients_page() { if ( ! current_user_can( 'edit_posts' ) ) return; $clients = co_sales_clients(); $edit_id = absint( $_GET['edit_client'] ?? 0 ); $editing = $edit_id ? get_post( $edit_id ) : null; co_sales_assets(); ?>
	<div class="sales-wrap"><header class="sales-hero compact"><div><small>CLIENT DIRECTORY</small><h1>Billing Clients</h1><p>Create a client once and reuse the details across every document.</p></div></header><div class="sales-two"><section class="sales-panel"><h2><?php echo $editing ? 'Edit client' : 'Add client'; ?></h2><form class="sales-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'co_sales_client' ); ?><input type="hidden" name="action" value="co_sales_save_client"><input type="hidden" name="client_id" value="<?php echo $editing ? $editing->ID : 0; ?>"><label>Company name *<input required name="company_name" value="<?php echo esc_attr( $editing ? $editing->post_title : '' ); ?>"></label><div class="sales-grid"><label>English name<input name="company_name_en" value="<?php echo esc_attr( $editing ? co_sales_meta( $editing->ID, 'company_name_en' ) : '' ); ?>"></label><label>Client abbreviation *<input required maxlength="12" name="short_name" placeholder="EFL" value="<?php echo esc_attr( $editing ? co_sales_meta( $editing->ID, 'short_name' ) : '' ); ?>"></label><label>Tax ID<input name="tax_id" value="<?php echo esc_attr( $editing ? co_sales_meta( $editing->ID, 'tax_id' ) : '' ); ?>"></label><label>Contact name<input name="contact_name" value="<?php echo esc_attr( $editing ? co_sales_meta( $editing->ID, 'contact_name' ) : '' ); ?>"></label><label>Phone<input name="phone" value="<?php echo esc_attr( $editing ? co_sales_meta( $editing->ID, 'phone' ) : '' ); ?>"></label><label>Email<input type="email" name="email" value="<?php echo esc_attr( $editing ? co_sales_meta( $editing->ID, 'email' ) : '' ); ?>"></label></div><label>Address<textarea name="address" rows="4"><?php echo esc_textarea( $editing ? co_sales_meta( $editing->ID, 'address' ) : '' ); ?></textarea></label><button class="sales-btn" type="submit">Save client</button></form></section><section class="sales-panel"><h2>Clients</h2><div class="sales-client-list"><?php foreach ( $clients as $client ) : ?><a href="<?php echo esc_url( admin_url( 'admin.php?page=co-sales-clients&edit_client=' . $client->ID ) ); ?>"><span><?php echo esc_html( strtoupper( co_sales_meta( $client->ID, 'short_name', 'CL' ) ) ); ?></span><div><strong><?php echo esc_html( $client->post_title ); ?></strong><small><?php echo esc_html( co_sales_meta( $client->ID, 'tax_id', 'No Tax ID' ) ); ?></small></div><i>→</i></a><?php endforeach; ?></div></section></div></div>
<?php }

function co_sales_settings_page() { if ( ! current_user_can( 'manage_options' ) ) return; $issuers = co_sales_issuers(); wp_enqueue_media(); co_sales_assets(); ?>
	<div class="sales-wrap"><header class="sales-hero compact"><div><small>DOCUMENT SETTINGS</small><h1>Issuers</h1><p>Manage the people or businesses that can issue sales documents.</p></div><button class="sales-btn white" type="button" data-add-issuer>+ Add issuer</button></header><form class="sales-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'co_sales_settings' ); ?><input type="hidden" name="action" value="co_sales_save_settings"><div class="sales-issuer-list"><?php foreach ( $issuers as $issuer ) : ?><section class="sales-panel sales-issuer-row"><input type="hidden" name="issuer_id[]" value="<?php echo esc_attr( $issuer['id'] ); ?>"><div class="sales-panel-head"><h2>Issuer profile</h2><button type="button" class="sales-link danger" data-remove-issuer>Remove</button></div><div class="sales-grid"><label>Name *<input required name="issuer_name[]" value="<?php echo esc_attr( $issuer['name'] ); ?>"></label><label>Tax ID<input name="issuer_tax_id[]" value="<?php echo esc_attr( $issuer['tax_id'] ); ?>"></label><label>Phone<input name="issuer_phone[]" value="<?php echo esc_attr( $issuer['phone'] ); ?>"></label><label>Email<input type="email" name="issuer_email[]" value="<?php echo esc_attr( $issuer['email'] ); ?>"></label><div class="sales-signature-field"><span>Signature</span><input type="hidden" name="issuer_signature_id[]" value="<?php echo absint( $issuer['signature_id'] ); ?>"><div data-signature-preview><?php if ( ! empty( $issuer['signature_id'] ) ) echo wp_get_attachment_image( $issuer['signature_id'], 'medium' ); ?></div><button class="sales-btn light" type="button" data-pick-signature>Choose image</button></div></div><label>Address<textarea name="issuer_address[]" rows="3"><?php echo esc_textarea( $issuer['address'] ); ?></textarea></label><label>Payment / bank details<textarea name="issuer_bank[]" rows="3"><?php echo esc_textarea( $issuer['bank'] ); ?></textarea></label></section><?php endforeach; ?></div><button class="sales-btn" type="submit">Save issuer settings</button></form></div>
<?php }

function co_sales_render_project_documents( $project_id ) {
	$documents = co_sales_documents( $project_id ); ?><section class="crm-drawer-section"><div class="crm-section-heading"><div><h3>Sales documents</h3><p>Quotations, invoices and receipts for this project.</p></div><a class="crm-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=co-sales-edit&project=' . $project_id ) ); ?>">+ Quotation</a></div><div class="sales-project-docs"><?php if ( $documents ) : foreach ( $documents as $document ) : ?><a href="<?php echo esc_url( admin_url( 'admin.php?page=co-sales-edit&document=' . $document->ID ) ); ?>"><span><?php echo esc_html( co_sales_meta( $document->ID, 'number' ) ); ?><small><?php echo esc_html( co_sales_types()[co_sales_meta( $document->ID, 'document_type' )] ?? 'Document' ); ?></small></span><strong>฿<?php echo esc_html( co_sales_money( co_sales_grand_total( $document->ID ) ) ); ?></strong></a><?php endforeach; else : ?><p>No sales documents yet.</p><?php endif; ?></div></section><?php
}
