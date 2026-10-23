<?php
defined( 'ABSPATH' ) || exit;

function co_sales_document_statuses() {
	return array( 'publish', 'trash', 'draft', 'pending', 'private', 'future' );
}

/** Follow explicit IDs only: QO -> Invoices -> Receipts, never company names. */
function co_sales_quote_group( $id ) {
	if ( 'sales_document' !== get_post_type( $id ) || 'quotation' !== co_sales_meta( $id, 'document_type' ) ) throw new RuntimeException( 'ไม่พบ Quotation' );
	$documents = array( $id => get_post_status( $id ) ); $queue = array( $id );
	while ( $queue ) {
		$parent = array_shift( $queue );
		foreach ( get_posts( array( 'post_type' => 'sales_document', 'post_status' => co_sales_document_statuses(), 'numberposts' => -1, 'meta_key' => '_sales_source_document_id', 'meta_value' => $parent ) ) as $child ) {
			if ( isset( $documents[$child->ID] ) ) continue;
			$documents[$child->ID] = $child->post_status; $queue[] = $child->ID;
		}
	}
	$payments = array(); $candidates = array();
	foreach ( $documents as $doc => $status ) {
		$candidates[] = absint( co_sales_meta( $doc, 'payment_id' ) );
		foreach ( array( '_crm_invoice_id', '_crm_receipt_id' ) as $key ) foreach ( get_posts( array( 'post_type' => 'project_payment', 'post_status' => co_sales_document_statuses(), 'numberposts' => -1, 'meta_key' => $key, 'meta_value' => $doc ) ) as $payment ) $candidates[] = $payment->ID;
	}
	foreach ( array_unique( array_filter( $candidates ) ) as $payment ) {
		if ( 'project_payment' !== get_post_type( $payment ) ) continue;
		$invoice = absint( get_post_meta( $payment, '_crm_invoice_id', true ) ); $receipt = absint( get_post_meta( $payment, '_crm_receipt_id', true ) );
		if ( ( $invoice && ! isset( $documents[$invoice] ) ) || ( $receipt && ! isset( $documents[$receipt] ) ) ) continue;
		// A malformed/shared old link must not remove another QO's money.
		$shared = false;
		foreach ( get_posts( array( 'post_type' => 'sales_document', 'post_status' => co_sales_document_statuses(), 'numberposts' => -1, 'meta_key' => '_sales_payment_id', 'meta_value' => $payment ) ) as $reference ) if ( ! isset( $documents[$reference->ID] ) ) $shared = true;
		if ( ! $shared ) $payments[$payment] = get_post_status( $payment );
	}
	ksort( $documents ); ksort( $payments );
	return array( 'documents' => $documents, 'payments' => $payments );
}

function co_sales_quote_delete_reason( $id ) {
	try {
		$group = co_sales_quote_group( $id );
		foreach ( array_keys( $group['documents'] + $group['payments'] ) as $member ) if ( ! current_user_can( 'delete_post', $member ) ) return 'ไม่มีสิทธิ์ลบเอกสารหรือรายการรับเงินบางรายการในชุดนี้';
		foreach ( get_posts( array( 'post_type' => 'client_project', 'post_status' => co_sales_document_statuses(), 'numberposts' => -1, 'meta_key' => '_crm_quotation_id', 'meta_value' => $id ) ) as $project ) if ( ! current_user_can( 'edit_post', $project->ID ) ) return 'ไม่มีสิทธิ์แก้ข้อตกลงของ Project นี้';
	} catch ( RuntimeException $error ) { return $error->getMessage(); }
	return '';
}

function co_sales_lifecycle_locks( $keys, $callback ) {
	if ( ! $keys ) return $callback();
	$key = array_shift( $keys );
	return co_project_locked( $key, fn() => co_sales_lifecycle_locks( $keys, $callback ) );
}

/** Keep a compensating journal so a failed child write never leaves half a set. */
function co_sales_lifecycle_set_meta( $id, $key, $value, &$journal, $delete = false ) {
	if ( ! isset( $journal['meta'][$id][$key] ) ) $journal['meta'][$id][$key] = array( 'exists' => metadata_exists( 'post', $id, $key ), 'value' => get_post_meta( $id, $key, true ) );
	if ( $delete ) delete_post_meta( $id, $key ); else update_post_meta( $id, $key, $value );
	if ( $delete ? metadata_exists( 'post', $id, $key ) : ( ! metadata_exists( 'post', $id, $key ) || get_post_meta( $id, $key, true ) != $value ) ) throw new RuntimeException( 'บันทึกข้อมูลชุดเอกสารไม่สำเร็จ กรุณาลองใหม่' );
}

function co_sales_lifecycle_set_status( $id, $status, &$journal ) {
	$journal['statuses'][$id] = get_post_status( $id );
	foreach ( array( '_wp_trash_meta_status', '_wp_trash_meta_time' ) as $key ) if ( ! isset( $journal['meta'][$id][$key] ) ) $journal['meta'][$id][$key] = array( 'exists' => metadata_exists( 'post', $id, $key ), 'value' => get_post_meta( $id, $key, true ) );
	// Explicit soft-delete also works when WordPress disables its default Trash.
	$result = wp_update_post( array( 'ID' => $id, 'post_status' => $status ), true );
	if ( is_wp_error( $result ) || ! $result || $status !== get_post_status( $id ) ) throw new RuntimeException( 'เปลี่ยนสถานะเอกสารไม่สำเร็จ กรุณาลองใหม่' );
}

function co_sales_quote_lifecycle( $id, $restore = false ) {
	if ( ! current_user_can( 'edit_posts' ) || ! current_user_can( 'delete_post', $id ) ) throw new RuntimeException( 'Permission denied.' );
	$group = co_sales_quote_group( $id ); $batch = co_sales_meta( $id, 'trash_batch', array() );
	$members = $restore && $batch ? (array) $batch['members'] : $group['documents'] + $group['payments'];
	$projects = get_posts( array( 'post_type' => 'client_project', 'post_status' => co_sales_document_statuses(), 'numberposts' => -1, 'meta_key' => '_crm_quotation_id', 'meta_value' => $id ) );
	$project_ids = array_column( $projects, 'ID' );
	if ( $restore && $batch ) $project_ids = array_merge( $project_ids, array_keys( $batch['projects'] ) );
	foreach ( array_keys( $group['documents'] ) as $doc ) $project_ids[] = absint( co_sales_meta( $doc, 'project_id' ) );
	$project_ids = array_values( array_unique( array_filter( $project_ids ) ) ); sort( $project_ids );
	$keys = array_map( fn( $project ) => 'project_quote_' . $project, $project_ids );
	$keys[] = 'document_' . $id;
	foreach ( array_keys( $group['documents'] + $members ) as $doc ) if ( $doc !== $id && 'sales_document' === get_post_type( $doc ) ) $keys[] = 'document_' . $doc;
	foreach ( array_keys( $group['payments'] + $members ) as $payment ) if ( 'project_payment' === get_post_type( $payment ) ) $keys[] = 'payment_' . $payment;
	return co_sales_lifecycle_locks( array_values( array_unique( $keys ) ), function () use ( $id, $restore, $group, $members, $project_ids, $batch ) {
		if ( ( $restore && 'publish' === get_post_status( $id ) ) || ( ! $restore && 'trash' === get_post_status( $id ) ) ) return;
		if ( ( $restore ? 'trash' : 'publish' ) !== get_post_status( $id ) ) throw new RuntimeException( 'สถานะ Quotation ไม่ถูกต้อง' );
		if ( $group !== co_sales_quote_group( $id ) ) throw new RuntimeException( 'เอกสารเพิ่งเปลี่ยน กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง' );
		if ( $restore && $batch !== co_sales_meta( $id, 'trash_batch', array() ) ) throw new RuntimeException( 'ชุดเอกสารเปลี่ยนแล้ว กรุณาโหลดหน้าใหม่' );
		$projects = get_posts( array( 'post_type' => 'client_project', 'post_status' => co_sales_document_statuses(), 'numberposts' => -1, 'meta_key' => '_crm_quotation_id', 'meta_value' => $id ) );
		foreach ( $projects as $project ) if ( ! in_array( $project->ID, $project_ids, true ) ) throw new RuntimeException( 'ข้อตกลง Project เพิ่งเปลี่ยน กรุณาโหลดหน้าใหม่' );
		foreach ( array_keys( $group['documents'] ) as $doc ) { $project = absint( co_sales_meta( $doc, 'project_id' ) ); if ( $project && ! in_array( $project, $project_ids, true ) ) throw new RuntimeException( 'Project ของเอกสารเพิ่งเปลี่ยน กรุณาโหลดหน้าใหม่' ); }
		foreach ( $members as $member => $status ) {
			if ( ! current_user_can( 'delete_post', $member ) || ( $restore && ! current_user_can( 'edit_post', $member ) ) ) throw new RuntimeException( 'ไม่มีสิทธิ์จัดการเอกสารบางรายการในชุดนี้' );
			if ( $restore && ( 'trash' !== get_post_status( $member ) || ( $member !== $id && absint( co_sales_meta( $member, 'trash_root' ) ) !== $id ) ) ) throw new RuntimeException( 'เอกสารในถังขยะเปลี่ยนไป กรุณาตรวจสอบก่อนกู้คืนทั้งชุด' );
			if ( $restore && 'sales_document' === get_post_type( $member ) && get_posts( array( 'post_type' => 'sales_document', 'post_status' => 'publish', 'numberposts' => 1, 'meta_key' => '_sales_number', 'meta_value' => co_sales_meta( $member, 'number' ) ) ) ) throw new RuntimeException( 'เลขที่เอกสารซ้ำกับใบที่ใช้งานอยู่ กรุณาตรวจสอบก่อนกู้คืน' );
		}
		foreach ( $projects as $project ) if ( ! current_user_can( 'edit_post', $project->ID ) ) throw new RuntimeException( 'ไม่มีสิทธิ์แก้ข้อตกลง Project' );
		$journal = array( 'meta' => array(), 'statuses' => array() );
		try {
			if ( ! $restore ) {
				$batch = array( 'members' => array_filter( $members, fn( $status ) => 'trash' !== $status ), 'projects' => array() );
				foreach ( $projects as $project ) foreach ( array( '_crm_quotation_id', '_crm_net_total', '_crm_cost', '_crm_quotation_no' ) as $key ) $batch['projects'][$project->ID][$key] = array( 'exists' => metadata_exists( 'post', $project->ID, $key ), 'value' => get_post_meta( $project->ID, $key, true ) );
				co_sales_lifecycle_set_meta( $id, '_sales_trash_batch', $batch, $journal );
				// Children and money leave active ledgers first; the QO is removed last.
				$ordered = $batch['members']; unset( $ordered[$id] ); $ordered[$id] = $members[$id];
				foreach ( $ordered as $member => $status ) {
					co_sales_lifecycle_set_meta( $member, '_sales_trash_root', $id, $journal );
					co_sales_lifecycle_set_meta( $member, '_wp_trash_meta_status', $status, $journal );
					co_sales_lifecycle_set_meta( $member, '_wp_trash_meta_time', time(), $journal );
					co_sales_lifecycle_set_status( $member, 'trash', $journal );
				}
				foreach ( $projects as $project ) {
					co_sales_lifecycle_set_meta( $project->ID, '_crm_quotation_id', '', $journal, true );
					co_sales_lifecycle_set_meta( $project->ID, '_crm_net_total', 0, $journal );
					co_sales_lifecycle_set_meta( $project->ID, '_crm_cost', 0, $journal );
					co_sales_lifecycle_set_meta( $project->ID, '_crm_quotation_no', '', $journal, true );
				}
			} else {
				foreach ( $members as $member => $status ) {
					co_sales_lifecycle_set_status( $member, $batch ? $status : 'publish', $journal );
					foreach ( array( '_wp_trash_meta_status', '_wp_trash_meta_time', '_sales_trash_root' ) as $key ) co_sales_lifecycle_set_meta( $member, $key, '', $journal, true );
				}
				foreach ( $batch['projects'] ?? array() as $project => $fields ) {
					if ( 'client_project' !== get_post_type( $project ) || 'publish' !== get_post_status( $project ) ) continue;
					if ( get_post_meta( $project, '_crm_quotation_id', true ) || (float) get_post_meta( $project, '_crm_net_total', true ) || (float) get_post_meta( $project, '_crm_cost', true ) || get_post_meta( $project, '_crm_quotation_no', true ) ) continue;
					if ( ! current_user_can( 'edit_post', $project ) ) throw new RuntimeException( 'ไม่มีสิทธิ์กู้คืนข้อตกลง Project' );
					foreach ( $fields as $key => $field ) co_sales_lifecycle_set_meta( $project, $key, $field['value'], $journal, ! $field['exists'] );
				}
				co_sales_lifecycle_set_meta( $id, '_sales_trash_batch', '', $journal, true );
			}
			co_sales_lifecycle_set_meta( $id, $restore ? '_sales_restored_by' : '_sales_trashed_by', get_current_user_id(), $journal );
			co_sales_lifecycle_set_meta( $id, $restore ? '_sales_restored_at' : '_sales_trashed_at', current_time( 'mysql' ), $journal );
		} catch ( Throwable $error ) {
			foreach ( array_reverse( $journal['statuses'], true ) as $member => $status ) wp_update_post( array( 'ID' => $member, 'post_status' => $status ) );
			foreach ( $journal['meta'] as $member => $fields ) foreach ( $fields as $key => $field ) { if ( $field['exists'] ) update_post_meta( $member, $key, $field['value'] ); else delete_post_meta( $member, $key ); }
			throw new RuntimeException( $error->getMessage() );
		}
	} );
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

// Retain the entire deleted set, including cash entries and private-file metadata.
add_filter( 'pre_delete_post', function ( $delete, $post ) {
	return ( 'sales_document' === $post->post_type && 'quotation' === co_sales_meta( $post->ID, 'document_type' ) ) || co_sales_meta( $post->ID, 'trash_root' ) ? false : $delete;
}, 10, 2 );

function co_sales_quote_action_button( $id, $restore = false ) {
	$reason = $restore ? '' : co_sales_quote_delete_reason( $id );
	if ( ! current_user_can( 'delete_post', $id ) ) return;
	?><button type="submit" form="sales-quote-action-<?php echo absint( $id ); ?>" class="sales-link <?php echo $restore ? '' : 'danger'; ?>" <?php disabled( (bool) $reason ); ?> title="<?php echo esc_attr( $reason ?: 'ลบ QO พร้อม Invoice, Receipt และรายการรับเงินที่เกี่ยวข้องทั้งหมด' ); ?>" data-sales-confirm="<?php echo esc_attr( $restore ? 'กู้คืนทั้งชุด QO, Invoice, Receipt และรายการรับเงิน? ยอดรับเงินจะกลับเข้ารายงาน และจะคืนข้อตกลงเดิมหาก Project ยังไม่มีข้อตกลงใหม่' : 'ลบ ' . co_sales_meta( $id, 'number' ) . ' พร้อม Invoice, Receipt และรายการรับเงินที่เกี่ยวข้องทั้งหมด? จะถอดข้อตกลงหลักและตัดยอดชุดนี้ออกจากรายงาน ไม่ลบ Project หรือ MA สามารถกู้คืนทั้งชุดจากถังขยะได้' ); ?>"><?php echo $restore ? 'กู้คืนทั้งชุด' : 'ลบ QO'; ?></button><?php
}

/** Separate forms keep delete POSTs out of the ZIP export and document-save forms. */
function co_sales_quote_action_form( $id, $restore = false ) {
	if ( ! current_user_can( 'delete_post', $id ) ) return;
	?><form hidden id="sales-quote-action-<?php echo absint( $id ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'co_sales_quote_lifecycle_' . $id ); ?><input type="hidden" name="action" value="co_sales_quote_lifecycle"><input type="hidden" name="document_id" value="<?php echo absint( $id ); ?>"><input type="hidden" name="operation" value="<?php echo $restore ? 'restore' : 'trash'; ?>"></form><?php
}

function co_sales_quote_trash_page() {
	$documents = get_posts( array( 'post_type' => 'sales_document', 'post_status' => 'trash', 'numberposts' => -1, 'meta_key' => '_sales_document_type', 'meta_value' => 'quotation' ) );
	co_sales_assets(); ?>
	<div class="sales-wrap"><header class="sales-edit-head"><div><a href="<?php echo esc_url( admin_url( 'admin.php?page=co-sales-documents&type=quotation' ) ); ?>">← Quotation</a><h1>ถังขยะ Quotation (<?php echo count( $documents ); ?>)</h1></div></header><section class="sales-panel"><p>กู้คืนทั้งชุด QO, Invoice, Receipt และรายการรับเงินเดิม ยอดจะกลับเข้ารายงานโดยไม่สร้างรายการซ้ำ</p><div class="sales-table-wrap"><table><thead><tr><th>Quotation</th><th>ลูกค้า</th><th>วันที่</th><th></th></tr></thead><tbody>
	<?php foreach ( $documents as $document ) : $id = $document->ID; $client = co_sales_meta( $id, 'client_snapshot', array() ); ?><tr><td><strong><?php echo esc_html( co_sales_meta( $id, 'number' ) ); ?></strong></td><td><?php echo esc_html( $client['name'] ?? '' ); ?></td><td><?php echo esc_html( co_sales_date( co_sales_meta( $id, 'issue_date' ) ) ); ?></td><td><?php co_sales_quote_action_button( $id, true ); ?></td></tr><?php endforeach; ?>
	<?php if ( ! $documents ) : ?><tr><td colspan="4">ไม่มี Quotation ในถังขยะ</td></tr><?php endif; ?></tbody></table></div></section><?php foreach ( $documents as $document ) co_sales_quote_action_form( $document->ID, true ); ?></div>
	<?php
}
