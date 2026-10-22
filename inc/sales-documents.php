<?php
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/sales-template.php';
require_once __DIR__ . '/sales-pages.php';

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
function co_sales_wht( $id ) { return round( co_sales_document_total( $id ) * (float) co_sales_meta( $id, 'wht_rate', 3 ) / 100, 2 ); }
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
	<style><?php include CO_THEME_DIR . '/assets/sales-documents.css'; include CO_THEME_DIR . '/assets/sales-print.css'; ?></style>
<script src="<?php echo esc_url( get_template_directory_uri() . '/assets/sales-documents.js?v=' . filemtime( CO_THEME_DIR . '/assets/sales-documents.js' ) ); ?>" defer></script>
	<script>document.addEventListener('DOMContentLoaded',()=>{const q=(s,p=document)=>p.querySelector(s);document.querySelectorAll('[data-sales-confirm]').forEach(a=>a.addEventListener('click',e=>{if(!confirm(a.dataset.salesConfirm))e.preventDefault()}));const client=q('#sales-client'),project=q('#sales-project');project?.addEventListener('change',()=>{const o=project.selectedOptions[0];if(o?.dataset.client&&client&&!client.value)client.value=o.dataset.client});const subtotal=q('[name="subtotal"]'),rate=q('[name="wht_rate"]'),wht=q('[data-sales-wht]'),total=q('[data-sales-total]');const calc=()=>{const s=Math.max(0,Number(subtotal?.value||0)),r=Math.max(0,Number(rate?.value||0));if(wht)wht.textContent=(s*r/100).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});if(total)total.textContent=(s-s*r/100).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})};subtotal?.addEventListener('input',calc);rate?.addEventListener('input',calc);calc();q('[data-add-issuer]')?.addEventListener('click',()=>{const list=q('.sales-issuer-list'),row=list.querySelector('.sales-issuer-row').cloneNode(true);row.querySelectorAll('input,textarea').forEach(i=>{i.value='';if(i.name==='issuer_id[]')i.value='issuer_'+Date.now()});row.querySelector('[data-signature-preview]')?.replaceChildren();list.appendChild(row)});document.addEventListener('click',e=>{const remove=e.target.closest('[data-remove-issuer]');if(remove&&document.querySelectorAll('.sales-issuer-row').length>1)remove.closest('.sales-issuer-row').remove();const pick=e.target.closest('[data-pick-signature]');if(pick&&window.wp?.media){e.preventDefault();const row=pick.closest('.sales-issuer-row'),frame=wp.media({title:'Choose signature',button:{text:'Use signature'},multiple:false});frame.on('select',()=>{const item=frame.state().get('selection').first().toJSON();row.querySelector('[name="issuer_signature_id[]"]').value=item.id;row.querySelector('[data-signature-preview]').innerHTML='<img src="'+(item.sizes?.medium?.url||item.url)+'" alt="Signature">'});frame.open()}})});</script>
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

add_action( 'admin_post_co_sales_save_document', function () {
	if ( ! current_user_can( 'edit_posts' ) ) wp_die( 'Permission denied.' ); check_admin_referer( 'co_sales_document' ); $id = absint( $_POST['document_id'] ?? 0 ); $type = sanitize_key( $_POST['document_type'] ?? 'quotation' ); if ( ! isset( co_sales_types()[$type] ) ) $type = 'quotation'; $client_id = absint( $_POST['client_id'] ?? 0 ); if ( 'sales_client' !== get_post_type( $client_id ) ) wp_die( 'Select a valid client.' ); $date = sanitize_text_field( $_POST['issue_date'] ?? wp_date( 'Y-m-d' ) ); $installment = absint( $_POST['installment_no'] ?? 0 );
	if ( $id && 'sales_document' !== get_post_type( $id ) ) wp_die( 'Invalid document.' );
	if ( $id ) $type = co_sales_meta( $id, 'document_type' );
	$parsed_date = DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
	if ( ! $parsed_date || $parsed_date->format( 'Y-m-d' ) !== $date ) wp_die( 'วันที่เอกสารไม่ถูกต้อง' );
	$schedule = array();
	if ( isset( $_POST['schedule_percent'] ) ) {
		foreach ( (array) $_POST['schedule_percent'] as $i => $percent ) {
			$description = sanitize_text_field( wp_unslash( $_POST['schedule_description'][$i] ?? '' ) );
			if ( '' === trim( (string) $percent ) && '' === $description ) continue;
			if ( ! is_numeric( $percent ) || (float) $percent <= 0 || (float) $percent > 100 ) wp_die( 'เปอร์เซ็นต์แต่ละงวดต้องมากกว่า 0 และไม่เกิน 100' );
			if ( '' === $description ) wp_die( 'กรุณาระบุเงื่อนไขการชำระของแต่ละงวด' );
			$schedule[] = array( 'percent' => (float) $percent, 'description' => $description );
		}
		$_POST['payment_schedule'] = wp_slash( implode( "\n", array_map( fn( $row ) => $row['percent'] . '|' . $row['description'], $schedule ) ) );
	} else {
		$schedule = co_sales_parse_schedule( wp_unslash( $_POST['payment_schedule'] ?? '' ) );
	}
	if ( 'quotation' === $type && $schedule && abs( array_sum( array_column( $schedule, 'percent' ) ) - 100 ) > 0.001 ) wp_die( 'เปอร์เซ็นต์งวดชำระต้องรวมเป็น 100%' );
	if ( $id && 'sales_document' === get_post_type( $id ) ) { $number = co_sales_meta( $id, 'number' ); wp_update_post( array( 'ID' => $id, 'post_title' => $number ) ); } else { $number = co_sales_next_number( $type, $client_id, $date, $installment ); $id = wp_insert_post( array( 'post_type' => 'sales_document', 'post_status' => 'publish', 'post_title' => $number ), true ); }
	if ( is_wp_error( $id ) || ! $id ) wp_die( 'ไม่สามารถบันทึกเอกสารได้' );
	$issuer = co_sales_issuer( sanitize_key( $_POST['issuer_id'] ?? '' ) ); $client = get_post( $client_id );
	$items = array();
	foreach ( (array) ( $_POST['item_text'] ?? array() ) as $i => $text ) {
		$items[] = array( 'text' => sanitize_textarea_field( wp_unslash( $text ) ), 'amount' => '' === ( $_POST['item_amount'][$i] ?? '' ) ? '' : max( 0, round( (float) $_POST['item_amount'][$i], 2 ) ), 'page' => max( 1, min( 99, absint( $_POST['item_page'][$i] ?? 1 ) ) ) );
	}
	if ( $items ) update_post_meta( $id, '_sales_items', $items );
	update_post_meta( $id, '_sales_body', implode( "\n\n", array_column( $items, 'text' ) ) );
	if ( 'quotation' === $type && $items ) $_POST['subtotal'] = array_sum( array_column( $items, 'amount' ) );
	$old_snapshot = co_sales_meta( $id, 'client_snapshot' );
	if ( $old_snapshot && (int) co_sales_meta( $id, 'client_id' ) === $client_id ) $preserve_client = $old_snapshot;
	$old_issuer = co_sales_meta( $id, 'issuer_snapshot' );
	if ( $old_issuer && co_sales_meta( $id, 'issuer_id' ) === $issuer['id'] ) $issuer = $old_issuer;
	$snapshot = array( 'name' => $client->post_title, 'name_en' => co_sales_meta( $client_id, 'company_name_en' ), 'address' => co_sales_meta( $client_id, 'address' ), 'tax_id' => co_sales_meta( $client_id, 'tax_id' ), 'contact_name' => co_sales_meta( $client_id, 'contact_name' ), 'phone' => co_sales_meta( $client_id, 'phone' ), 'email' => co_sales_meta( $client_id, 'email' ) );
	update_post_meta( $id, '_sales_number', $number ); update_post_meta( $id, '_sales_document_type', $type ); update_post_meta( $id, '_sales_client_id', $client_id ); update_post_meta( $id, '_sales_project_id', absint( $_POST['project_id'] ?? 0 ) ); update_post_meta( $id, '_sales_source_document_id', absint( $_POST['source_document_id'] ?? 0 ) ); update_post_meta( $id, '_sales_installment_no', $installment ); update_post_meta( $id, '_sales_issue_date', $date ); update_post_meta( $id, '_sales_status', isset( co_sales_statuses()[sanitize_key( $_POST['document_status'] ?? '' )] ) ? sanitize_key( $_POST['document_status'] ) : 'draft' ); update_post_meta( $id, '_sales_client_snapshot', $preserve_client ?? $snapshot ); update_post_meta( $id, '_sales_issuer_id', sanitize_key( $_POST['issuer_id'] ?? '' ) ); update_post_meta( $id, '_sales_issuer_snapshot', $issuer ); update_post_meta( $id, '_sales_title', sanitize_text_field( wp_unslash( $_POST['document_title'] ?? '' ) ) );  update_post_meta( $id, '_sales_subtotal', max( 0, (float) ( $_POST['subtotal'] ?? 0 ) ) ); update_post_meta( $id, '_sales_wht_rate', min( 100, max( 0, (float) ( $_POST['wht_rate'] ?? 3 ) ) ) ); update_post_meta( $id, '_sales_payment_schedule', co_sales_parse_schedule( wp_unslash( $_POST['payment_schedule'] ?? '' ) ) ); update_post_meta( $id, '_sales_payment_schedule_text', sanitize_textarea_field( wp_unslash( $_POST['payment_schedule'] ?? '' ) ) ); update_post_meta( $id, '_sales_notes', sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ) );
	co_sales_sync_payment( $id );
	$page = ! empty( $_POST['preview_after_save'] ) ? 'co-sales-preview' : 'co-sales-edit';
	wp_safe_redirect( admin_url( 'admin.php?page=' . $page . '&document=' . $id . '&saved=1' ) ); exit;
} );

add_action( 'admin_post_co_sales_derive_document', function () {
	if ( ! current_user_can( 'edit_posts' ) ) wp_die( 'Permission denied.' );
	$source_id = absint( $_GET['source'] ?? 0 );
	check_admin_referer( 'co_sales_derive_' . $source_id );
	if ( 'sales_document' !== get_post_type( $source_id ) ) wp_die( 'Invalid source document.' );
	$source_type = co_sales_meta( $source_id, 'document_type' );
	if ( ! in_array( $source_type, array( 'quotation', 'invoice' ), true ) ) wp_die( 'Invalid source document type.' );
	$type = 'quotation' === $source_type ? 'invoice' : 'receipt';
	$installment = absint( $_GET['installment'] ?? co_sales_meta( $source_id, 'installment_no', 1 ) );
	$existing_id = co_sales_find_derived_document( $source_id, $type, $installment );
	if ( $existing_id ) {
		wp_safe_redirect( admin_url( 'admin.php?page=co-sales-edit&document=' . $existing_id . '&existing=1' ) );
		exit;
	}
	$client_id = absint( co_sales_meta( $source_id, 'client_id' ) );
	$date = wp_date( 'Y-m-d' );
	$number = co_sales_next_number( $type, $client_id, $date, $installment );
	$id = wp_insert_post( array( 'post_type' => 'sales_document', 'post_status' => 'publish', 'post_title' => $number ) );
	foreach ( array( 'project_id','client_id','client_snapshot','issuer_id','issuer_snapshot','body','wht_rate','notes','items','payment_schedule','payment_schedule_text' ) as $key ) update_post_meta( $id, '_sales_' . $key, co_sales_meta( $source_id, $key ) );
	$subtotal = co_sales_document_total( $source_id );
	if ( 'invoice' === $type ) {
		$schedule = co_sales_schedule( $source_id );
		$row = $schedule[max( 0, $installment - 1 )] ?? array( 'percent' => 100, 'description' => '' );
		$subtotal = round( $subtotal * (float) $row['percent'] / 100, 2 );
		update_post_meta( $id, '_sales_title', 'งวดที่ ' . $installment . ' : ' . $row['percent'] . '% ' . $row['description'] );
	} else {
		update_post_meta( $id, '_sales_title', co_sales_meta( $source_id, 'title' ) );
		update_post_meta( $source_id, '_sales_status', 'paid' );
	}
	update_post_meta( $id, '_sales_number', $number ); update_post_meta( $id, '_sales_document_type', $type ); update_post_meta( $id, '_sales_source_document_id', $source_id ); update_post_meta( $id, '_sales_installment_no', $installment ); update_post_meta( $id, '_sales_issue_date', $date ); update_post_meta( $id, '_sales_status', 'receipt' === $type ? 'paid' : 'draft' ); update_post_meta( $id, '_sales_subtotal', $subtotal );
	co_sales_sync_payment( $id );
	wp_safe_redirect( admin_url( 'admin.php?page=co-sales-edit&document=' . $id ) ); exit;
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
