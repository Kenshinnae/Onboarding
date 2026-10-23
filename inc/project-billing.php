<?php
defined( 'ABSPATH' ) || exit;

function co_project_quote( $project_id ) {
	if ( 'client_project' !== get_post_type( $project_id ) || 'publish' !== get_post_status( $project_id ) ) return 0;
	$id = absint( get_post_meta( $project_id, '_crm_quotation_id', true ) );
	return $id && 'sales_document' === get_post_type( $id ) && 'publish' === get_post_status( $id ) && 'quotation' === co_sales_meta( $id, 'document_type' ) && $project_id === absint( co_sales_meta( $id, 'project_id' ) ) ? $id : 0;
}

function co_project_financials( $project_id ) {
	$quote = co_project_quote( $project_id );
	$value = $quote ? co_sales_document_total( $quote ) : (float) get_post_meta( $project_id, '_crm_net_total', true );
	$net = $quote ? co_sales_grand_total( $quote ) : co_crm_net_after_3_percent( $value );
	$received = round( co_crm_received( $project_id ), 2 );
	return array( 'quote' => $quote, 'value' => $value, 'net' => $net, 'received' => $received, 'outstanding' => round( max( 0, $net - $received ), 2 ) );
}

function co_project_sync_quote( $quote ) {
	$project = absint( co_sales_meta( $quote, 'project_id' ) );
	if ( ! $project || co_project_quote( $project ) !== $quote ) return;
	foreach ( array( 'net_total' => co_sales_document_total( $quote ), 'cost' => co_sales_grand_total( $quote ), 'quotation_no' => co_sales_meta( $quote, 'number' ), 'sales_client_id' => co_sales_meta( $quote, 'client_id' ) ) as $key => $value ) update_post_meta( $project, '_crm_' . $key, $value );
}

/** Serialize writes, including repeated clicks and two admin windows. */
function co_project_locked( $key, $callback ) {
	$key = 'co_billing_lock_' . sanitize_key( $key );
	$old = get_option( $key );
	$started = is_array( $old ) ? ( $old['started'] ?? 0 ) : (int) $old;
	if ( $old && $started < time() - 120 ) delete_option( $key );
	$lease = array( 'started' => time(), 'owner' => uniqid( 'co_', true ) );
	if ( ! add_option( $key, $lease, '', false ) ) throw new RuntimeException( 'รายการนี้กำลังดำเนินการ กรุณารอสักครู่แล้วเปิดใหม่' );
	try { return $callback(); } finally { if ( get_option( $key ) === $lease ) delete_option( $key ); }
}

function co_sales_invoice_payment( $invoice ) {
	if ( 'invoice' !== co_sales_meta( $invoice, 'document_type' ) ) return 0;
	$receipt = co_sales_invoice_receipt( $invoice );
	$candidates = array( absint( co_sales_meta( $invoice, 'payment_id' ) ), $receipt ? absint( co_sales_meta( $receipt, 'payment_id' ) ) : 0 );
	foreach ( get_posts( array( 'post_type' => 'project_payment', 'post_status' => 'publish', 'numberposts' => -1, 'meta_key' => '_crm_invoice_id', 'meta_value' => $invoice ) ) as $payment ) $candidates[] = $payment->ID;
	foreach ( array_unique( $candidates ) as $id ) {
		if ( ! $id || 'project_payment' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) continue;
		if ( absint( get_post_meta( $id, '_crm_project_id', true ) ) !== absint( co_sales_meta( $invoice, 'project_id' ) ) ) continue;
		$linked = absint( get_post_meta( $id, '_crm_invoice_id', true ) );
		if ( ! $linked || $linked === $invoice ) return $id;
	}
	return 0;
}

function co_sales_create_numbered_document( $type, $client, $date, $installment, $manual_number = '' ) {
	return co_project_locked( 'sales_document_numbers', fn() => co_project_locked( 'number_' . $type . '_' . $client . '_' . $date . '_' . $installment, function () use ( $type, $client, $date, $installment, $manual_number ) {
		if ( '' !== $manual_number && 'quotation' !== $type ) throw new RuntimeException( 'กำหนดเลขเองได้เฉพาะ Quotation' );
		$number = '' !== $manual_number ? co_sales_validate_manual_number( $manual_number ) : co_sales_next_number( $type, $client, $date, $installment );
		if ( co_sales_number_in_use( $number ) ) throw new RuntimeException( 'เลข QO นี้มีในระบบแล้ว กรุณาใช้เลขอื่น' );
		$id = wp_insert_post( array( 'post_type' => 'sales_document', 'post_status' => 'publish', 'post_title' => $number ), true );
		if ( is_wp_error( $id ) || ! $id ) throw new RuntimeException( 'สร้างเอกสารไม่สำเร็จ' );
		update_post_meta( $id, '_sales_number', $number );
		return array( $id, $number );
	} ) );
}

function co_sales_payment_matches_invoice( $invoice ) {
	$payment = co_sales_invoice_payment( $invoice );
	return $payment && abs( (float) get_post_meta( $payment, '_crm_amount', true ) - co_sales_grand_total( $invoice ) ) < .005;
}

function co_sales_payment_invoice( $payment ) {
	$id = absint( get_post_meta( $payment, '_crm_invoice_id', true ) );
	if ( $id && 'sales_document' === get_post_type( $id ) && 'invoice' === co_sales_meta( $id, 'document_type' ) && 'publish' === get_post_status( $id ) ) return $id;
	foreach ( get_posts( array( 'post_type' => 'sales_document', 'post_status' => 'publish', 'numberposts' => -1, 'meta_key' => '_sales_payment_id', 'meta_value' => $payment ) ) as $doc ) {
		$type = co_sales_meta( $doc->ID, 'document_type' );
		if ( 'invoice' === $type ) return $doc->ID;
		$invoice = absint( co_sales_meta( $doc->ID, 'source_document_id' ) );
		if ( 'receipt' === $type && 'invoice' === co_sales_meta( $invoice, 'document_type' ) ) return $invoice;
	}
	return 0;
}

function co_project_unallocated_payments( $project ) {
	return array_values( array_filter( co_crm_payments( $project ), fn( $payment ) => ! co_sales_payment_invoice( $payment->ID ) ) );
}

function co_sales_valid_date( $date ) {
	$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
	return $parsed && $parsed->format( 'Y-m-d' ) === $date;
}

function co_project_record_payment( $invoice, $date, $existing = 0, $note = '', $confirm_new = false ) {
	if ( 'sales_document' !== get_post_type( $invoice ) || 'invoice' !== co_sales_meta( $invoice, 'document_type' ) || 'publish' !== get_post_status( $invoice ) ) throw new RuntimeException( 'Invoice ไม่ถูกต้อง' );
	$project = absint( co_sales_meta( $invoice, 'project_id' ) );
	if ( $project && ( 'client_project' !== get_post_type( $project ) || 'publish' !== get_post_status( $project ) ) ) throw new RuntimeException( 'Project ของ Invoice ไม่ถูกต้อง' );
	$write = function () use ( $invoice, $date, $existing, $note, $project, $confirm_new ) {
		return co_project_locked( 'invoice_payment_' . $invoice, function () use ( $invoice, $date, $existing, $note, $project, $confirm_new ) {
			if ( 'publish' !== get_post_status( $invoice ) ) throw new RuntimeException( 'Invoice ถูกลบแล้ว กรุณาโหลดหน้าใหม่' );
			$payment = co_sales_invoice_payment( $invoice );
			if ( $payment ) return $payment;
			$amount = co_sales_grand_total( $invoice );
			if ( $existing ) {
				if ( 'project_payment' !== get_post_type( $existing ) || 'publish' !== get_post_status( $existing ) || $project !== absint( get_post_meta( $existing, '_crm_project_id', true ) ) || co_sales_payment_invoice( $existing ) ) throw new RuntimeException( 'รายการรับเงินเดิมนี้ใช้จับคู่ไม่ได้' );
				if ( abs( (float) get_post_meta( $existing, '_crm_amount', true ) - $amount ) > .004 ) throw new RuntimeException( 'ยอดรับเงินเดิมไม่ตรงกับยอดสุทธิของงวดนี้ กรุณาตรวจสอบก่อนจับคู่' );
				$payment = $existing; // Preserve the actual received date and original note.
			} else {
				if ( $project && co_project_unallocated_payments( $project ) && ! $confirm_new ) throw new RuntimeException( 'มีรายการรับเงินเดิมที่ยังไม่จับคู่ กรุณาเลือกจับคู่หรือยืนยันว่าเป็นเงินคนละรายการก่อน' );
				if ( ! co_sales_valid_date( $date ) ) throw new RuntimeException( 'วันที่รับเงินจริงไม่ถูกต้อง' );
				$payment = wp_insert_post( array( 'post_type' => 'project_payment', 'post_status' => 'publish', 'post_title' => 'รับเงิน ' . co_sales_meta( $invoice, 'number' ) ), true );
				if ( is_wp_error( $payment ) || ! $payment ) throw new RuntimeException( 'บันทึกรับเงินไม่สำเร็จ' );
				update_post_meta( $payment, '_crm_project_id', $project );
				update_post_meta( $payment, '_crm_amount', $amount );
				update_post_meta( $payment, '_crm_paid_date', $date );
				update_post_meta( $payment, '_crm_note', $note );
			}
			update_post_meta( $payment, '_crm_invoice_id', $invoice );
			update_post_meta( $invoice, '_sales_payment_id', $payment );
			$receipt = co_sales_invoice_receipt( $invoice );
			if ( $receipt ) { update_post_meta( $receipt, '_sales_payment_id', $payment ); update_post_meta( $payment, '_crm_receipt_id', $receipt ); }
			return $payment;
		} );
	};
	return co_project_locked( 'document_' . $invoice, fn() => $existing ? co_project_locked( 'payment_' . $existing, $write ) : $write() );
}

add_action( 'admin_post_co_project_receive', function () {
	if ( ! current_user_can( 'edit_posts' ) ) wp_die( 'Permission denied.' );
	$invoice = absint( $_POST['invoice_id'] ?? 0 ); check_admin_referer( 'co_project_receive_' . $invoice );
	try { co_project_record_payment( $invoice, sanitize_text_field( $_POST['paid_date'] ?? '' ), absint( $_POST['existing_payment'] ?? 0 ), sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) ), ! empty( $_POST['confirm_new_payment'] ) ); }
	catch ( RuntimeException $error ) { wp_die( esc_html( $error->getMessage() ) ); }
	$project = absint( $_POST['return_project'] ?? 0 );
	$target = $project && $project === absint( co_sales_meta( $invoice, 'project_id' ) ) ? 'admin.php?page=co-projects&open_project=' . $project . '&received=1' : 'admin.php?page=co-sales-edit&document=' . $invoice . '&received=1#invoice-payment';
	wp_safe_redirect( admin_url( $target ) ); exit;
} );

add_action( 'admin_post_co_project_accept_quote', function () {
	if ( ! current_user_can( 'edit_posts' ) ) wp_die( 'Permission denied.' );
	$quote = absint( $_POST['quotation_id'] ?? 0 ); $project = absint( $_POST['project_id'] ?? 0 ); check_admin_referer( 'co_project_accept_' . $project );
	try {
		co_project_locked( 'project_quote_' . $project, function () use ( $quote, $project ) {
			if ( 'client_project' !== get_post_type( $project ) || 'publish' !== get_post_status( $project ) || 'sales_document' !== get_post_type( $quote ) || 'publish' !== get_post_status( $quote ) || 'quotation' !== co_sales_meta( $quote, 'document_type' ) || $project !== absint( co_sales_meta( $quote, 'project_id' ) ) ) throw new RuntimeException( 'เลือก QO ที่ผูกกับ Project นี้ก่อน' );
			$old = co_project_quote( $project );
			if ( $old && $old !== $quote && co_sales_child_documents( $old, 'invoice' ) ) throw new RuntimeException( 'QO หลักเดิมมี Invoice แล้ว ไม่สามารถเปลี่ยนข้อตกลงย้อนหลังได้' );
			$schedule = co_sales_schedule( $quote );
			if ( ! $schedule || abs( array_sum( array_column( $schedule, 'percent' ) ) - 100 ) > .001 ) throw new RuntimeException( 'แผนงวด QO ต้องครบ 100%' );
			$seen = array();
			foreach ( co_sales_child_documents( $quote, 'invoice' ) as $invoice ) {
				$number = max( 1, absint( co_sales_meta( $invoice->ID, 'installment_no', 1 ) ) );
				if ( isset( $seen[$number] ) || $project !== absint( co_sales_meta( $invoice->ID, 'project_id' ) ) || absint( co_sales_meta( $quote, 'client_id' ) ) !== absint( co_sales_meta( $invoice->ID, 'client_id' ) ) ) throw new RuntimeException( 'มี Invoice ซ้ำงวด หรือข้อมูล Project/ลูกค้าไม่ตรงกัน กรุณาตรวจสอบเอกสารเดิมก่อน' );
				$seen[$number] = true;
				$row = $schedule[max( 0, absint( co_sales_meta( $invoice->ID, 'installment_no', 1 ) ) - 1 )] ?? null;
				$allocation = co_sales_installment_amounts( $quote, max( 1, absint( co_sales_meta( $invoice->ID, 'installment_no', 1 ) ) ) );
				if ( ! $row || abs( co_sales_document_total( $invoice->ID ) - $allocation['subtotal'] ) > .004 || abs( co_sales_grand_total( $invoice->ID ) - $allocation['net'] ) > .004 || (float) co_sales_meta( $invoice->ID, 'wht_rate', 3 ) !== (float) co_sales_meta( $quote, 'wht_rate', 3 ) ) throw new RuntimeException( 'Invoice เดิมไม่ตรงกับราคา/งวดใน QO กรุณาตรวจสอบก่อนใช้เป็นข้อตกลง' );
			}
			update_post_meta( $project, '_crm_quotation_id', $quote ); co_project_sync_quote( $quote );
		} );
	} catch ( RuntimeException $error ) { wp_die( esc_html( $error->getMessage() ) ); }
	wp_safe_redirect( admin_url( 'admin.php?page=co-projects&open_project=' . $project ) ); exit;
} );

function co_sales_financial_locked( $id ) {
	$type = co_sales_meta( $id, 'document_type' );
	if ( 'quotation' === $type ) return (bool) co_sales_child_documents( $id, 'invoice' );
	if ( 'receipt' === $type ) return (bool) absint( co_sales_meta( $id, 'payment_id' ) ) || (bool) co_sales_invoice_payment( absint( co_sales_meta( $id, 'source_document_id' ) ) );
	$quote = absint( co_sales_meta( $id, 'source_document_id' ) ); $project = absint( co_sales_meta( $id, 'project_id' ) );
	return (bool) co_sales_invoice_payment( $id ) || ( $quote && 'quotation' === co_sales_meta( $quote, 'document_type' ) );
}

function co_project_derive_document( $source, $installment = 1 ) {
	if ( 'sales_document' !== get_post_type( $source ) || 'publish' !== get_post_status( $source ) || ! in_array( co_sales_meta( $source, 'document_type' ), array( 'quotation', 'invoice' ), true ) ) throw new RuntimeException( 'เอกสารต้นทางไม่ถูกต้อง' );
	$type = 'quotation' === co_sales_meta( $source, 'document_type' ) ? 'invoice' : 'receipt';
	if ( 'receipt' === $type ) $installment = absint( co_sales_meta( $source, 'installment_no', 1 ) );
	$installment = max( 1, absint( $installment ) );
	$derive = fn() => co_project_locked( 'document_' . $source, fn() => co_project_locked( 'derive_' . $source . '_' . $type . '_' . ( 'receipt' === $type ? 0 : $installment ), function () use ( $source, $type, $installment ) {
		if ( 'publish' !== get_post_status( $source ) ) throw new RuntimeException( 'เอกสารต้นทางถูกลบแล้ว กรุณาโหลดหน้าใหม่' );
		$existing = 'receipt' === $type ? co_sales_invoice_receipt( $source ) : co_sales_find_derived_document( $source, $type, $installment );
		if ( $existing ) return $existing;
		$subtotal = co_sales_document_total( $source ); $payment = 0;
		if ( 'invoice' === $type ) {
			$project = absint( co_sales_meta( $source, 'project_id' ) );
			if ( $project && co_project_quote( $project ) !== $source ) throw new RuntimeException( 'กรุณาเลือก QO ใบนี้เป็นข้อตกลงของ Project ก่อนออก Invoice' );
			$row = co_sales_schedule( $source )[$installment - 1] ?? null;
			if ( ! $row ) throw new RuntimeException( 'ไม่พบงวดชำระนี้ใน Quotation' );
			$allocation = co_sales_installment_amounts( $source, $installment );
			$subtotal = $allocation['subtotal'];
		} else {
			$payment = co_sales_invoice_payment( $source );
			if ( ! $payment ) return 0;
			if ( abs( (float) get_post_meta( $payment, '_crm_amount', true ) - co_sales_grand_total( $source ) ) > .004 ) throw new RuntimeException( 'ยอดรับเงินเดิมไม่ตรง Invoice กรุณาตรวจสอบก่อนออก Receipt' );
			$row = co_sales_billing_schedule( $source )[0];
		}
		$client = absint( co_sales_meta( $source, 'client_id' ) ); $date = wp_date( 'Y-m-d' );
		list( $id, $number ) = co_sales_create_numbered_document( $type, $client, $date, $installment );
		foreach ( array( 'project_id', 'client_id', 'client_snapshot', 'issuer_id', 'issuer_snapshot', 'body', 'wht_rate', 'notes', 'discount' ) as $key ) update_post_meta( $id, '_sales_' . $key, co_sales_meta( $source, $key ) );
		$values = array( 'items' => co_sales_items( $source ), 'base_total' => co_sales_meta( $source, 'base_total', co_sales_items_value( co_sales_items( $source ) ) ), 'number' => $number, 'document_type' => $type, 'source_document_id' => $source, 'installment_no' => $installment, 'installment_percent' => (float) $row['percent'], 'payment_schedule' => array( $row ), 'payment_schedule_text' => $row['percent'] . '|' . $row['description'], 'issue_date' => $date, 'status' => 'receipt' === $type ? 'paid' : 'draft', 'subtotal' => $subtotal, 'title' => 'invoice' === $type ? 'งวดที่ ' . $installment . ' : ' . $row['percent'] . '% ' . $row['description'] : co_sales_meta( $source, 'title' ) );
		foreach ( $values as $key => $value ) update_post_meta( $id, '_sales_' . $key, $value );
		if ( 'invoice' === $type ) {
			update_post_meta( $id, '_sales_wht_amount', $allocation['wht'] ); update_post_meta( $id, '_sales_price_total', $allocation['price'] ); update_post_meta( $id, '_sales_rounding_adjustment', $allocation['adjustment'] );
		} else {
			foreach ( array( 'wht_amount', 'price_total', 'rounding_adjustment' ) as $key ) if ( '' !== co_sales_meta( $source, $key ) ) update_post_meta( $id, '_sales_' . $key, co_sales_meta( $source, $key ) );
		}
		if ( $payment ) { update_post_meta( $id, '_sales_payment_id', $payment ); co_sales_sync_payment( $id ); }
		return $id;
	} ) );
	$project = absint( co_sales_meta( $source, 'project_id' ) );
	return 'invoice' === $type && $project ? co_project_locked( 'project_quote_' . $project, $derive ) : $derive();
}

function co_sales_assert_financials( $id, $items, $discount, $schedule ) {
	if ( ! $id || ! co_sales_financial_locked( $id ) ) return;
	$amounts = fn( $rows ) => array_map( fn( $row ) => round( (float) ( $row['amount'] ?: 0 ), 2 ), $rows );
	$changed = $amounts( $items ) !== $amounts( co_sales_items( $id ) ) || $discount !== round( (float) co_sales_meta( $id, 'discount', 0 ), 2 ) || (float) ( $_POST['wht_rate'] ?? 3 ) !== (float) co_sales_meta( $id, 'wht_rate', 3 ) || $schedule !== co_sales_billing_schedule( $id );
	$percent = 'quotation' === co_sales_meta( $id, 'document_type' ) ? 100 : (float) ( $schedule[0]['percent'] ?? 0 );
	if ( abs( co_sales_installment_value( co_sales_discounted_base( co_sales_items_value( $items ), $discount ), $percent ) + (float) co_sales_meta( $id, 'rounding_adjustment', 0 ) - co_sales_document_total( $id ) ) > .004 ) $changed = true;
	$identity_keys = array( 'project_id', 'client_id', 'source_document_id' );
	if ( 'quotation' !== co_sales_meta( $id, 'document_type' ) ) $identity_keys[] = 'installment_no';
	foreach ( $identity_keys as $key ) if ( absint( $_POST[$key] ?? 0 ) !== absint( co_sales_meta( $id, $key ) ) ) $changed = true;
	if ( $changed ) throw new RuntimeException( 'ราคา ลูกค้า Project และแผนงวดถูกล็อกแล้ว เพื่อรักษาข้อตกลงและยอดรับเงินจริงเดิม' );
}

function co_project_render_accept( $quote, $project ) {
	if ( ! $project ) return;
	if ( co_project_quote( $project ) === $quote ) { echo '<p class="sales-billing-badge complete">QO หลักที่ตกลงของ Project</p>'; return; }
	if ( co_project_quote( $project ) && co_sales_child_documents( co_project_quote( $project ), 'invoice' ) ) return; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'co_project_accept_' . $project ); ?><input type="hidden" name="action" value="co_project_accept_quote"><input type="hidden" name="project_id" value="<?php echo $project; ?>"><input type="hidden" name="quotation_id" value="<?php echo $quote; ?>"><button class="sales-btn" onclick="return confirm('ใช้ราคา ส่วนลด และแผนงวดของ QO ใบนี้เป็นข้อตกลงของ Project?')">ใช้ใบนี้เป็นข้อตกลง</button></form>
<?php }

function co_project_render_payment( $invoice, $inline = false ) {
	$payment = co_sales_invoice_payment( $invoice ); $receipt = co_sales_invoice_receipt( $invoice ); $project = absint( co_sales_meta( $invoice, 'project_id' ) ); ?>
	<?php if ( $payment && ! co_sales_payment_matches_invoice( $invoice ) ) : ?><section class="sales-panel sales-billing-warning" id="invoice-payment<?php echo $inline ? '-' . $invoice : ''; ?>"><h2>ตรวจสอบยอดรับเงินเดิม</h2><p>ยอดรับเงินเดิม ฿<?php echo esc_html( co_sales_money( get_post_meta( $payment, '_crm_amount', true ) ) ); ?> ไม่ตรงกับ Invoice ฿<?php echo esc_html( co_sales_money( co_sales_grand_total( $invoice ) ) ); ?> ระบบจะไม่เพิ่มหรือแก้ยอดเดิม และยังไม่ออก Receipt ใหม่จนกว่าจะตรวจสอบ</p><?php if ( $receipt ) echo co_sales_document_link( $receipt, 'เปิด Receipt เดิม' ); ?></section><?php return; endif; ?>
	<section class="sales-panel sales-payment-panel" id="invoice-payment<?php echo $inline ? '-' . $invoice : ''; ?>"><h2><?php echo $payment ? ( $receipt ? 'ครบแล้ว — รับเงินและออก Receipt แล้ว' : 'รับแล้ว — ยังไม่มี Receipt' ) : 'รอรับเงินงวดนี้'; ?></h2>
	<?php if ( $payment ) : ?><p>รับเงินจริง ฿<?php echo esc_html( co_sales_money( get_post_meta( $payment, '_crm_amount', true ) ) ); ?> · วันที่ <?php echo esc_html( co_sales_date( get_post_meta( $payment, '_crm_paid_date', true ) ) ); ?></p><?php if ( $receipt ) : echo co_sales_document_link( $receipt ); else : ?><a class="sales-btn" href="<?php echo esc_url( co_sales_derive_url( $invoice ) ); ?>" data-sales-confirm="ออก Receipt โดยอ้างอิงรายการรับเงินเดิม? จะไม่เพิ่มยอดรับเงินซ้ำ">ออก Receipt</a><?php endif; ?>
	<?php else : $unallocated = $project ? co_project_unallocated_payments( $project ) : array(); ?>
	<form class="sales-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'co_project_receive_' . $invoice ); ?><input type="hidden" name="action" value="co_project_receive"><input type="hidden" name="invoice_id" value="<?php echo $invoice; ?>"><input type="hidden" name="return_project" value="<?php echo $inline ? $project : 0; ?>"><div class="sales-grid"><label>ยอดสุทธิตาม Invoice<input readonly value="฿<?php echo esc_attr( co_sales_money( co_sales_grand_total( $invoice ) ) ); ?>"></label><label>วันที่รับเงินจริง<input required type="date" name="paid_date" value="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>"></label></div>
	<?php if ( $unallocated ) : ?><p class="sales-billing-warning">มีรายการรับเงินเดิมที่ยังไม่จับคู่ เลือกรายการเดิมเพื่อไม่ให้นับยอดซ้ำ เมื่อจับคู่จะเก็บวันที่รับเงินจริงเดิมไว้</p><label>จับคู่รายการรับเงิน<select name="existing_payment"><option value="0">บันทึกเงินเข้าใหม่</option><?php foreach ( $unallocated as $old ) : ?><option data-paid-date="<?php echo esc_attr( get_post_meta( $old->ID, '_crm_paid_date', true ) ); ?>" value="<?php echo $old->ID; ?>"><?php echo esc_html( $old->post_title . ' · ฿' . co_sales_money( get_post_meta( $old->ID, '_crm_amount', true ) ) . ' · ' . co_sales_date( get_post_meta( $old->ID, '_crm_paid_date', true ) ) ); ?></option><?php endforeach; ?></select></label><label class="sales-inline-check"><input type="checkbox" name="confirm_new_payment" value="1"> ถ้าบันทึกใหม่ ยืนยันว่าไม่ใช่รายการรับเงินเดิมด้านบน</label><?php endif; ?>
	<label>หมายเหตุ<textarea name="note" rows="2"></textarea></label><button class="sales-btn">บันทึกรับเงินงวดนี้</button></form>
	<?php endif; ?></section>
<?php }

function co_project_render_billing( $project ) {
	$finance = co_project_financials( $project ); $quote = $finance['quote']; $docs = co_sales_documents( $project ); ?>
	<section class="crm-drawer-section sales-project-hub"><div class="crm-section-heading"><div><h3>ข้อตกลง เอกสาร และงวดชำระ</h3><p>ราคาและงวดอ้างอิง QO ที่ลูกค้าตกลง รับเงินตาม Invoice แล้วออก Receipt ภายหลังได้</p></div><a class="crm-btn" href="<?php echo esc_url( admin_url( $quote ? 'admin.php?page=co-sales-edit&document=' . $quote : 'admin.php?page=co-sales-edit&project=' . $project ) ); ?>"><?php echo $quote ? 'เปิด QO หลัก' : '+ Quotation'; ?></a></div>
	<?php co_project_render_client( $project ); ?>
	<?php if ( ! $quote ) : ?><p><?php echo $docs ? 'ยังไม่ได้เลือก QO หลัก — เลือกใบที่ลูกค้าตกลงด้านล่าง ข้อมูลราคาเดิมยังคงอยู่' : 'ยังไม่มี QO ที่เชื่อมกับ Project นี้ — สร้าง Quotation ใหม่ หรือเปิด QO เดิมแล้วเลือก Project นี้ก่อน ข้อมูลราคาเดิมยังคงอยู่'; ?></p><?php endif; ?>
	<div class="sales-project-docs"><?php foreach ( $docs as $doc ) : if ( 'quotation' !== co_sales_meta( $doc->ID, 'document_type' ) ) continue; ?><article><?php echo co_sales_document_link( $doc->ID ); co_project_render_accept( $doc->ID, $project ); ?></article><?php endforeach; ?></div>
	<?php if ( $quote ) co_sales_render_quote_progress( $quote, true ); ?>
	<details class="sales-project-archive"><summary>เอกสารทั้งหมดของ Project (<?php echo count( $docs ); ?>)</summary><div class="sales-project-docs"><?php foreach ( $docs as $doc ) : ?><article><?php echo co_sales_document_link( $doc->ID ); ?><small><?php echo esc_html( co_sales_types()[co_sales_meta( $doc->ID, 'document_type' )] ?? '' ); ?></small><strong>฿<?php echo esc_html( co_sales_money( co_sales_grand_total( $doc->ID ) ) ); ?></strong></article><?php endforeach; ?><?php if ( ! $docs ) : ?><article><p>ยังไม่มีเอกสารที่เชื่อมกับ Project นี้ ใบที่เลือก “ไม่เชื่อม Project” จะไม่แสดงในส่วนนี้</p><a href="<?php echo esc_url( admin_url( 'admin.php?page=co-sales-edit&project=' . $project ) ); ?>">+ สร้าง Quotation ของ Project นี้</a><a href="<?php echo esc_url( admin_url( 'admin.php?page=co-sales-documents&type=quotation' ) ); ?>">เปิดรายการ Quotation เดิมเพื่อเลือกเชื่อม Project →</a></article><?php endif; ?></div></details>
	<?php $old = co_project_unallocated_payments( $project ); if ( $old ) : ?><div class="sales-billing-warning"><strong>รับเงินเดิมที่ยังไม่จับคู่ (<?php echo count( $old ); ?>)</strong><p>ยอดเหล่านี้นับในเงินรับแล้ว ไม่ต้องกรอกเพิ่ม ให้เลือกจับคู่จาก Invoice ที่ตรงกัน</p><?php foreach ( $old as $payment ) : ?><p><?php echo esc_html( $payment->post_title . ' · ฿' . co_sales_money( get_post_meta( $payment->ID, '_crm_amount', true ) ) . ' · ' . co_sales_date( get_post_meta( $payment->ID, '_crm_paid_date', true ) ) ); ?></p><?php endforeach; ?></div><?php endif; ?>
	</section>
<?php }

function co_project_render_client( $project ) {
	$quote = co_project_quote( $project ); $client = absint( get_post_meta( $project, '_crm_sales_client_id', true ) ); ?>
	<?php if ( absint( $_GET['client_linked'] ?? 0 ) === $project && $client ) : ?><p class="sales-project-client-success" role="status">เชื่อมลูกค้าสำหรับออกบิลแล้ว: <?php echo esc_html( get_the_title( $client ) ); ?> — กด “เติมข้อมูลออกบิล” เพื่อแก้ที่อยู่ เลขผู้เสียภาษี และข้อมูลอื่น ๆ</p><?php endif; ?>
	<form class="sales-form sales-project-client" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'co_project_client_' . $project ); ?><input type="hidden" name="action" value="co_project_client"><input type="hidden" name="project_id" value="<?php echo $project; ?>"><label>ลูกค้าสำหรับออกบิล (เลือกต่างจากบริษัทเจ้าของงานหรือ MA ได้)<select name="sales_client_id" <?php disabled( (bool) $quote ); ?>><option value="0">สร้างลูกค้าจากข้อมูล Project</option><?php foreach ( co_sales_clients() as $item ) : ?><option value="<?php echo $item->ID; ?>" <?php selected( $client, $item->ID ); ?>><?php echo esc_html( $item->post_title ); ?></option><?php endforeach; ?></select></label><?php if ( ! $quote ) : ?><button class="sales-btn light">เชื่อมลูกค้า / สร้างจาก Project</button><?php endif; ?><?php if ( $client ) : ?><a href="<?php echo esc_url( admin_url( 'admin.php?page=co-sales-clients&edit_client=' . $client ) ); ?>">เติมข้อมูลออกบิล →</a><?php endif; ?></form>
<?php }

add_action( 'admin_post_co_project_client', function () {
	if ( ! current_user_can( 'edit_posts' ) ) wp_die( 'Permission denied.' );
	$project = absint( $_POST['project_id'] ?? 0 ); check_admin_referer( 'co_project_client_' . $project );
	if ( 'client_project' !== get_post_type( $project ) || co_project_quote( $project ) ) wp_die( 'Project นี้มีลูกค้าจาก QO หลักแล้ว' );
	try { co_project_locked( 'project_quote_' . $project, function () use ( $project ) {
		if ( co_project_quote( $project ) ) throw new RuntimeException( 'Project นี้มีลูกค้าจาก QO หลักแล้ว' );
		$client = absint( $_POST['sales_client_id'] ?? 0 );
		if ( ! $client ) {
			$client = absint( get_post_meta( $project, '_crm_sales_client_id', true ) );
			if ( ! $client || 'sales_client' !== get_post_type( $client ) ) {
				$client = wp_insert_post( array( 'post_type' => 'sales_client', 'post_status' => 'publish', 'post_title' => co_crm_meta( $project, 'company', get_the_title( $project ) ) ), true );
				if ( is_wp_error( $client ) || ! $client ) throw new RuntimeException( 'สร้างลูกค้าไม่สำเร็จ' );
				$onboard = absint( co_crm_meta( $project, 'onboarding_id' ) );
				update_post_meta( $client, '_sales_contact_name', co_crm_meta( $project, 'contact_name' ) ); update_post_meta( $client, '_sales_email', co_crm_meta( $project, 'contact_email' ) ); update_post_meta( $client, '_sales_phone', $onboard ? get_post_meta( $onboard, '_onboarding_phone', true ) : '' );
			}
		}
		if ( 'sales_client' !== get_post_type( $client ) || 'publish' !== get_post_status( $client ) ) throw new RuntimeException( 'ลูกค้าไม่ถูกต้อง' );
		update_post_meta( $project, '_crm_sales_client_id', $client );
	} ); } catch ( RuntimeException $error ) { wp_die( esc_html( $error->getMessage() ) ); }
	wp_safe_redirect( admin_url( 'admin.php?page=co-projects&open_project=' . $project . '&client_linked=' . $project ) ); exit;
} );

function co_project_render_tasks() {
	$waiting = $receipts = array();
	foreach ( co_sales_documents() as $doc ) if ( 'invoice' === co_sales_meta( $doc->ID, 'document_type' ) ) {
		if ( ! co_sales_payment_matches_invoice( $doc->ID ) ) $waiting[] = $doc;
		elseif ( ! co_sales_invoice_receipt( $doc->ID ) ) $receipts[] = $doc;
	}
	$today = wp_date( 'Y-m-d' ); $until = wp_date( 'Y-m-d', strtotime( '+7 days', current_time( 'timestamp' ) ) );
	$expiring = array_filter( co_ma_clients(), fn( $client ) => co_ma_meta( $client->ID, 'expiry_date' ) >= $today && co_ma_meta( $client->ID, 'expiry_date' ) <= $until ); ?>
	<section class="sales-task-hub"><h2>งานที่ต้องติดตาม</h2><div class="sales-task-grid">
	<?php foreach ( array( 'Invoice รอรับเงิน' => $waiting, 'รับแล้ว ยังไม่มี Receipt' => $receipts, 'MA หมดภายใน 7 วัน' => $expiring ) as $label => $items ) : ?>
		<details class="sales-task-group<?php echo $items ? ' has-items' : ''; ?>"><summary><span><?php echo esc_html( $label ); ?></span><b><?php echo count( $items ); ?></b></summary><div>
		<?php foreach ( $items as $item ) : ?><p><?php if ( 'ma_client' === $item->post_type ) : ?><a href="<?php echo esc_url( admin_url( 'admin.php?page=ma-client-detail&client=' . $item->ID ) ); ?>"><?php echo esc_html( $item->post_title . ' · ' . co_sales_date( co_ma_meta( $item->ID, 'expiry_date' ) ) ); ?></a><?php else : ?><?php echo co_sales_document_link( $item->ID ); ?> <small>฿<?php echo esc_html( co_sales_money( co_sales_grand_total( $item->ID ) ) ); ?></small><?php endif; ?></p><?php endforeach; if ( ! $items ) : ?><p>ไม่มีรายการค้าง</p><?php endif; ?>
		</div></details>
	<?php endforeach; ?></div></section>
<?php }
