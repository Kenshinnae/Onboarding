<?php
defined( 'ABSPATH' ) || exit;

function co_sales_document_statuses() {
	return array( 'publish', 'trash', 'draft', 'pending', 'private', 'future' );
}

/** Never cascade-delete accounting history, including documents already in Trash. */
function co_sales_quote_delete_reason( $id ) {
	if ( 'sales_document' !== get_post_type( $id ) || 'quotation' !== co_sales_meta( $id, 'document_type' ) ) return 'ไม่พบ Quotation';
	if ( ! current_user_can( 'delete_post', $id ) ) return 'ไม่มีสิทธิ์ลบ Quotation นี้';
	if ( get_posts( array( 'post_type' => 'sales_document', 'post_status' => co_sales_document_statuses(), 'numberposts' => 1, 'meta_key' => '_sales_source_document_id', 'meta_value' => $id ) ) ) return 'มี Invoice หรือเอกสารอ้างอิงแล้ว ไม่สามารถลบ QO ต้นทางได้';
	if ( get_posts( array( 'post_type' => 'client_project', 'post_status' => co_sales_document_statuses(), 'numberposts' => 1, 'meta_key' => '_crm_quotation_id', 'meta_value' => $id ) ) ) return 'เป็น QO หลักของ Project กรุณาเลือก QO ใบอื่นเป็นข้อตกลงก่อนลบ';
	if ( absint( co_sales_meta( $id, 'payment_id' ) ) || co_sales_wht_files( $id, true ) ) return 'มีรายการรับเงินหรือใบหักภาษีอ้างอิงอยู่ ไม่สามารถลบได้';
	return '';
}

function co_sales_quote_lifecycle( $id, $restore = false ) {
	if ( ! current_user_can( 'edit_posts' ) || ! current_user_can( 'delete_post', $id ) || ( $restore && ! current_user_can( 'edit_post', $id ) ) ) throw new RuntimeException( 'Permission denied.' );
	$change = fn() => co_project_locked( 'document_' . $id, function () use ( $id, $restore ) {
		if ( 'sales_document' !== get_post_type( $id ) || 'quotation' !== co_sales_meta( $id, 'document_type' ) ) throw new RuntimeException( 'ไม่พบ Quotation' );
		if ( $restore ) {
			if ( 'publish' === get_post_status( $id ) ) return; // Safe retry.
			if ( 'trash' !== get_post_status( $id ) ) throw new RuntimeException( 'เอกสารนี้ไม่ได้อยู่ในถังขยะ' );
			$duplicates = get_posts( array( 'post_type' => 'sales_document', 'post_status' => 'publish', 'numberposts' => 1, 'meta_key' => '_sales_number', 'meta_value' => co_sales_meta( $id, 'number' ) ) );
			if ( $duplicates ) throw new RuntimeException( 'เลขที่เอกสารซ้ำกับใบที่ใช้งานอยู่ กรุณาตรวจสอบก่อนกู้คืน' );
			$result = wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ), true );
			if ( is_wp_error( $result ) || ! $result || 'publish' !== get_post_status( $id ) ) throw new RuntimeException( 'กู้คืน Quotation ไม่สำเร็จ' );
			delete_post_meta( $id, '_wp_trash_meta_status' ); delete_post_meta( $id, '_wp_trash_meta_time' );
			update_post_meta( $id, '_sales_restored_by', get_current_user_id() );
			update_post_meta( $id, '_sales_restored_at', current_time( 'mysql' ) );
		} else {
			if ( 'trash' === get_post_status( $id ) ) return; // Safe retry.
			if ( 'publish' !== get_post_status( $id ) ) throw new RuntimeException( 'สถานะ Quotation ไม่ถูกต้อง' );
			$reason = co_sales_quote_delete_reason( $id );
			if ( $reason ) throw new RuntimeException( $reason );
			if ( ! wp_trash_post( $id ) || 'trash' !== get_post_status( $id ) ) throw new RuntimeException( 'ย้าย Quotation ลงถังขยะไม่สำเร็จ' );
			update_post_meta( $id, '_sales_trashed_by', get_current_user_id() );
			update_post_meta( $id, '_sales_trashed_at', current_time( 'mysql' ) );
		}
	} );
	$project = absint( co_sales_meta( $id, 'project_id' ) );
	return $project ? co_project_locked( 'project_quote_' . $project, $change ) : $change();
}

add_action( 'admin_post_co_sales_quote_lifecycle', function () {
	$id = absint( $_POST['document_id'] ?? 0 );
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) wp_die( 'POST required.' );
	check_admin_referer( 'co_sales_quote_lifecycle_' . $id );
	$operation = sanitize_key( $_POST['operation'] ?? '' );
	if ( ! in_array( $operation, array( 'trash', 'restore' ), true ) ) wp_die( 'Invalid operation.' );
	try { co_sales_quote_lifecycle( $id, 'restore' === $operation ); }
	catch ( RuntimeException $error ) { wp_die( esc_html( $error->getMessage() ) ); }
	wp_safe_redirect( admin_url( 'admin.php?page=co-sales-documents&type=quotation&lifecycle=' . $operation . ( 'restore' === $operation ? '&view=trash' : '' ) ) ); exit;
} );

// WordPress normally empties Trash automatically. QOs remain recoverable instead.
add_filter( 'pre_delete_post', function ( $delete, $post ) {
	return 'sales_document' === $post->post_type && 'quotation' === co_sales_meta( $post->ID, 'document_type' ) ? false : $delete;
}, 10, 2 );

function co_sales_quote_action_button( $id, $restore = false ) {
	$reason = $restore ? '' : co_sales_quote_delete_reason( $id );
	if ( ! current_user_can( 'delete_post', $id ) ) return;
	?><button type="submit" form="sales-quote-action-<?php echo absint( $id ); ?>" class="sales-link <?php echo $restore ? '' : 'danger'; ?>" <?php disabled( (bool) $reason ); ?> title="<?php echo esc_attr( $reason ); ?>" data-sales-confirm="<?php echo esc_attr( $restore ? 'กู้คืน Quotation นี้? จะไม่เปลี่ยน QO หลักหรือยอดเงินของ Project' : 'ย้าย ' . co_sales_meta( $id, 'number' ) . ' ลงถังขยะ? สามารถกู้คืนได้ โดยไม่ลบ Project หรือเอกสารอื่น' ); ?>"><?php echo $restore ? 'กู้คืน' : 'ลบ QO'; ?></button><?php
}

/** Separate forms keep delete POSTs out of the ZIP export and document-save forms. */
function co_sales_quote_action_form( $id, $restore = false ) {
	if ( ! current_user_can( 'delete_post', $id ) ) return;
	?><form hidden id="sales-quote-action-<?php echo absint( $id ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'co_sales_quote_lifecycle_' . $id ); ?><input type="hidden" name="action" value="co_sales_quote_lifecycle"><input type="hidden" name="document_id" value="<?php echo absint( $id ); ?>"><input type="hidden" name="operation" value="<?php echo $restore ? 'restore' : 'trash'; ?>"></form><?php
}

function co_sales_quote_trash_page() {
	$documents = get_posts( array( 'post_type' => 'sales_document', 'post_status' => 'trash', 'numberposts' => -1, 'meta_key' => '_sales_document_type', 'meta_value' => 'quotation' ) );
	co_sales_assets(); ?>
	<div class="sales-wrap"><header class="sales-edit-head"><div><a href="<?php echo esc_url( admin_url( 'admin.php?page=co-sales-documents&type=quotation' ) ); ?>">← Quotation</a><h1>ถังขยะ Quotation (<?php echo count( $documents ); ?>)</h1></div></header><section class="sales-panel"><p>กู้คืนเอกสารได้โดยไม่เพิ่มยอดรับเงินหรือเปลี่ยนข้อตกลงหลักของ Project</p><div class="sales-table-wrap"><table><thead><tr><th>Quotation</th><th>ลูกค้า</th><th>วันที่</th><th></th></tr></thead><tbody>
	<?php foreach ( $documents as $document ) : $id = $document->ID; $client = co_sales_meta( $id, 'client_snapshot', array() ); ?><tr><td><strong><?php echo esc_html( co_sales_meta( $id, 'number' ) ); ?></strong></td><td><?php echo esc_html( $client['name'] ?? '' ); ?></td><td><?php echo esc_html( co_sales_date( co_sales_meta( $id, 'issue_date' ) ) ); ?></td><td><?php co_sales_quote_action_button( $id, true ); ?></td></tr><?php endforeach; ?>
	<?php if ( ! $documents ) : ?><tr><td colspan="4">ไม่มี Quotation ในถังขยะ</td></tr><?php endif; ?></tbody></table></div></section><?php foreach ( $documents as $document ) co_sales_quote_action_form( $document->ID, true ); ?></div>
	<?php
}
