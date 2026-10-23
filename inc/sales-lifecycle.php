<?php
defined( 'ABSPATH' ) || exit;

function co_sales_document_statuses() { return array( 'publish', 'trash', 'draft', 'pending', 'private', 'future' ); }

/** Explicit ID relationships only; never infer ownership from company names. */
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

/** Validate the exact private-file targets before deleting any database records. */
function co_sales_quote_delete_files( $documents ) {
	$owned = array(); $shared = array();
	foreach ( array_keys( $documents ) as $doc ) foreach ( co_sales_wht_files( $doc, true ) as $file ) $owned[$file['stored']] = $file;
	if ( ! $owned ) return array();
	foreach ( get_posts( array( 'post_type' => 'sales_document', 'post_status' => co_sales_document_statuses(), 'numberposts' => -1 ) ) as $other ) {
		if ( isset( $documents[$other->ID] ) ) continue;
		foreach ( co_sales_wht_files( $other->ID, true ) as $file ) $shared[$file['stored']] = true;
	}
	$root = co_sales_wht_storage(); $paths = array();
	foreach ( $owned as $stored => $file ) {
		if ( isset( $shared[$stored] ) ) continue;
		if ( ! preg_match( '/^[a-f0-9]{48}\.(pdf|png|jpg|webp)$/D', $stored ) ) throw new RuntimeException( 'ชื่อไฟล์ภาษีไม่ถูกต้อง ยังไม่ได้ลบเอกสาร' );
		$path = $root . '/' . $stored;
		if ( is_link( $path ) ) throw new RuntimeException( 'ไม่ลบไฟล์ภาษีที่เป็น symbolic link' );
		if ( ! file_exists( $path ) ) continue;
		if ( ! is_file( $path ) || dirname( realpath( $path ) ?: '' ) !== $root || ! is_writable( $root ) ) throw new RuntimeException( 'ไม่สามารถลบไฟล์ภาษีในพื้นที่ส่วนตัว ยังไม่ได้ลบเอกสาร' );
		$paths[] = $path;
	}
	return $paths;
}

/** Permanent record deletion is atomic, including when called in a test transaction. */
function co_sales_delete_transaction( $callback, $ids ) {
	global $wpdb;
	foreach ( array( $wpdb->posts, $wpdb->postmeta, $wpdb->options, $wpdb->comments, $wpdb->commentmeta, $wpdb->term_relationships, $wpdb->term_taxonomy ) as $table ) {
		if ( 'innodb' !== strtolower( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) ) ) ) throw new RuntimeException( 'ตารางฐานข้อมูลต้องรองรับ transaction ก่อนลบถาวร' );
	}
	$nested = (bool) $wpdb->get_var( 'SELECT @@session.in_transaction' );
	$savepoint = 'co_delete_' . bin2hex( random_bytes( 6 ) );
	if ( false === $wpdb->query( $nested ? 'SAVEPOINT ' . $savepoint : 'START TRANSACTION' ) ) throw new RuntimeException( 'เริ่มลบเอกสารไม่สำเร็จ' );
	try {
		$result = $callback();
		if ( false === $wpdb->query( $nested ? 'RELEASE SAVEPOINT ' . $savepoint : 'COMMIT' ) ) throw new RuntimeException( 'บันทึกการลบเอกสารไม่สำเร็จ' );
		return $result;
	} catch ( Throwable $error ) {
		$wpdb->query( $nested ? 'ROLLBACK TO SAVEPOINT ' . $savepoint : 'ROLLBACK' );
		foreach ( $ids as $id ) clean_post_cache( $id );
		throw new RuntimeException( $error->getMessage() );
	}
}

function co_sales_quote_lifecycle( $id, $restore = false ) {
	if ( $restore ) throw new RuntimeException( 'การลบเป็นแบบถาวร ไม่มีการกู้คืน' );
	if ( ! current_user_can( 'edit_posts' ) ) throw new RuntimeException( 'Permission denied.' );
	$group = co_sales_quote_group( $id ); $members = $group['documents'] + $group['payments'];
	$projects = get_posts( array( 'post_type' => 'client_project', 'post_status' => co_sales_document_statuses(), 'numberposts' => -1, 'meta_key' => '_crm_quotation_id', 'meta_value' => $id ) );
	$project_ids = array_column( $projects, 'ID' );
	foreach ( array_keys( $group['documents'] ) as $doc ) $project_ids[] = absint( co_sales_meta( $doc, 'project_id' ) );
	$project_ids = array_values( array_unique( array_filter( $project_ids ) ) ); sort( $project_ids );
	$keys = array_map( fn( $project ) => 'project_quote_' . $project, $project_ids ); $keys[] = 'document_' . $id;
	foreach ( array_keys( $group['documents'] ) as $doc ) if ( $doc !== $id ) $keys[] = 'document_' . $doc;
	foreach ( array_keys( $group['payments'] ) as $payment ) $keys[] = 'payment_' . $payment;
	return co_sales_lifecycle_locks( $keys, function () use ( $id, $group, $members, $project_ids ) {
		if ( $group !== co_sales_quote_group( $id ) ) throw new RuntimeException( 'เอกสารเพิ่งเปลี่ยน กรุณาโหลดหน้าใหม่' );
		$reason = co_sales_quote_delete_reason( $id ); if ( $reason ) throw new RuntimeException( $reason );
		$projects = get_posts( array( 'post_type' => 'client_project', 'post_status' => co_sales_document_statuses(), 'numberposts' => -1, 'meta_key' => '_crm_quotation_id', 'meta_value' => $id ) );
		foreach ( $projects as $project ) if ( ! in_array( $project->ID, $project_ids, true ) ) throw new RuntimeException( 'ข้อตกลง Project เพิ่งเปลี่ยน กรุณาโหลดหน้าใหม่' );
		foreach ( array_keys( $group['documents'] ) as $doc ) { $project = absint( co_sales_meta( $doc, 'project_id' ) ); if ( $project && ! in_array( $project, $project_ids, true ) ) throw new RuntimeException( 'Project ของเอกสารเพิ่งเปลี่ยน กรุณาโหลดหน้าใหม่' ); }
		$paths = co_sales_quote_delete_files( $group['documents'] );
		co_sales_delete_transaction( function () use ( $id, $members, $projects ) {
			foreach ( $projects as $project ) {
				delete_post_meta( $project->ID, '_crm_quotation_id' ); delete_post_meta( $project->ID, '_crm_quotation_no' );
				update_post_meta( $project->ID, '_crm_net_total', 0 ); update_post_meta( $project->ID, '_crm_cost', 0 );
				if ( get_post_meta( $project->ID, '_crm_quotation_id', true ) || get_post_meta( $project->ID, '_crm_quotation_no', true ) || (float) get_post_meta( $project->ID, '_crm_net_total', true ) || (float) get_post_meta( $project->ID, '_crm_cost', true ) ) throw new RuntimeException( 'ล้างยอด Project ไม่สำเร็จ' );
			}
			$ordered = array_keys( $members ); $ordered = array_values( array_diff( $ordered, array( $id ) ) ); $ordered[] = $id;
			foreach ( $ordered as $member ) {
				if ( ! wp_delete_post( $member, true ) || get_post( $member ) ) throw new RuntimeException( 'ลบเอกสารบางรายการไม่สำเร็จ ยกเลิกการลบทั้งชุดแล้ว' );
			}
		}, array_merge( array_keys( $members ), array_column( $projects, 'ID' ) ) );
		foreach ( $paths as $path ) if ( ! unlink( $path ) ) throw new RuntimeException( 'ลบเอกสารถาวรแล้ว แต่ลบไฟล์ภาษีไม่สำเร็จ กรุณาตรวจสิทธิ์โฟลเดอร์ส่วนตัวก่อนดำเนินการต่อ' );
	} );
}

add_action( 'admin_post_co_sales_quote_lifecycle', function () {
	$id = absint( $_POST['document_id'] ?? 0 );
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) wp_die( 'POST required.' );
	check_admin_referer( 'co_sales_quote_lifecycle_' . $id );
	if ( 'delete' !== sanitize_key( $_POST['operation'] ?? '' ) ) wp_die( 'กรุณาโหลดหน้าใหม่ การลบเป็นแบบถาวรแล้ว' );
	try { co_sales_quote_lifecycle( $id ); } catch ( RuntimeException $error ) { wp_die( esc_html( $error->getMessage() ) ); }
	wp_safe_redirect( admin_url( 'admin.php?page=co-sales-documents&type=quotation&lifecycle=deleted' ) ); exit;
} );

function co_sales_quote_action_button( $id ) {
	$reason = co_sales_quote_delete_reason( $id ); if ( ! current_user_can( 'delete_post', $id ) ) return;
	?><button type="submit" form="sales-quote-action-<?php echo absint( $id ); ?>" class="sales-link danger" <?php disabled( (bool) $reason ); ?> title="<?php echo esc_attr( $reason ?: 'ลบถาวรทั้งชุด' ); ?>" data-sales-confirm="<?php echo esc_attr( 'ลบถาวร ' . co_sales_meta( $id, 'number' ) . ' พร้อม Invoice, Receipt, รายการรับเงิน และไฟล์ภาษีที่เกี่ยวข้องทั้งหมด? กู้คืนไม่ได้ ยอดชุดนี้จะถูกตัดออกจากรายงาน ไม่ลบ Project หรือ MA' ); ?>">ลบ QO</button><?php
}

/** Delete POSTs stay outside ZIP-export and document-save forms. */
function co_sales_quote_action_form( $id ) {
	if ( ! current_user_can( 'delete_post', $id ) ) return;
	?><form hidden id="sales-quote-action-<?php echo absint( $id ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'co_sales_quote_lifecycle_' . $id ); ?><input type="hidden" name="action" value="co_sales_quote_lifecycle"><input type="hidden" name="document_id" value="<?php echo absint( $id ); ?>"><input type="hidden" name="operation" value="delete"></form><?php
}
