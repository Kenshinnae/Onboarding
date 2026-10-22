<?php
defined( 'ABSPATH' ) || exit;

function co_sales_items_value( $items ) {
	$cents = 0;
	foreach ( $items as $item ) $cents += (int) round( max( 0, (float) ( $item['amount'] ?? 0 ) ) * 100 );
	return $cents / 100;
}

function co_sales_installment_value( $base, $percent ) {
	return round( round( max( 0, (float) $base ), 2 ) * max( 0, min( 100, (float) $percent ) ) / 100, 2 );
}

/** Discounts belong to the whole job; invoices receive their installment's share. */
function co_sales_discounted_base( $base, $discount ) {
	return round( max( 0, round( (float) $base, 2 ) - max( 0, round( (float) $discount, 2 ) ) ), 2 );
}

/** The final installment absorbs cent rounding so every invoice sums to its QO. */
function co_sales_installment_amounts( $quote, $number ) {
	$number = absint( $number );
	$schedule = co_sales_schedule( $quote ); $used = $used_wht = $used_price = 0;
	$base = co_sales_document_total( $quote ); $price_base = (float) co_sales_meta( $quote, 'base_total', co_sales_items_value( co_sales_items( $quote ) ) );
	foreach ( $schedule as $index => $row ) {
		$last = $index === count( $schedule ) - 1;
		$subtotal = max( 0, round( $last ? $base - $used : min( $base - $used, co_sales_installment_value( $base, $row['percent'] ) ), 2 ) );
		$wht = max( 0, round( min( $subtotal, co_sales_wht( $quote ) - $used_wht, $last ? co_sales_wht( $quote ) - $used_wht : round( $subtotal * (float) co_sales_meta( $quote, 'wht_rate', 3 ) / 100, 2 ) ), 2 ) );
		$price = max( $subtotal, round( $last ? $price_base - $used_price : min( $price_base - $used_price, co_sales_installment_value( $price_base, $row['percent'] ) ), 2 ) );
		if ( $index + 1 === $number ) return array( 'subtotal' => $subtotal, 'wht' => $wht, 'net' => round( $subtotal - $wht, 2 ), 'price' => $price, 'adjustment' => round( $subtotal - co_sales_installment_value( $base, $row['percent'] ), 2 ) );
		$used += $subtotal; $used_wht += $wht; $used_price += $price;
	}
	return array( 'subtotal' => 0, 'wht' => 0, 'net' => 0, 'price' => 0, 'adjustment' => 0 );
}

function co_sales_document_breakdown( $id ) {
	$subtotal = co_sales_document_total( $id );
	$discount = max( 0, (float) co_sales_meta( $id, 'discount', 0 ) );
	if ( ! $discount ) return array( 'price' => $subtotal, 'discount' => 0 );
	$base = (float) co_sales_meta( $id, 'base_total', co_sales_items_value( co_sales_items( $id ) ) );
	$percent = 'quotation' === co_sales_meta( $id, 'document_type' ) ? 100 : co_sales_billing_schedule( $id )[0]['percent'];
	$price = (float) co_sales_meta( $id, 'price_total', co_sales_installment_value( $base, $percent ) );
	// Reconcile rounded installment amounts so Price - Discount always equals Subtotal.
	return array( 'price' => $price, 'discount' => max( 0, round( $price - $subtotal, 2 ) ) );
}

function co_sales_billing_schedule( $id ) {
	$schedule = co_sales_schedule( $id );
	if ( 'quotation' === co_sales_meta( $id, 'document_type' ) ) return $schedule;
	$index = max( 0, absint( co_sales_meta( $id, 'installment_no', 1 ) ) - 1 );
	$row = count( $schedule ) > 1 ? ( $schedule[$index] ?? $schedule[0] ) : ( $schedule[0] ?? array( 'percent' => 100, 'description' => '' ) );
	$stored_percent = co_sales_meta( $id, 'installment_percent' );
	if ( '' !== $stored_percent ) $row['percent'] = (float) $stored_percent;
	return array( $row );
}

function co_sales_child_documents( $id, $type ) {
	return get_posts( array(
		'post_type' => 'sales_document', 'post_status' => 'publish', 'numberposts' => -1,
		'orderby' => 'ID', 'order' => 'ASC',
		'meta_query' => array(
			array( 'key' => '_sales_source_document_id', 'value' => absint( $id ) ),
			array( 'key' => '_sales_document_type', 'value' => $type ),
		),
	) );
}

function co_sales_invoice_receipt( $id ) {
	$receipts = co_sales_child_documents( $id, 'receipt' );
	return $receipts ? $receipts[0]->ID : 0;
}

function co_sales_quote_progress( $id ) {
	$schedule = co_sales_schedule( $id );
	$invoices = co_sales_child_documents( $id, 'invoice' );
	$by_installment = array(); $pending = 0;
	foreach ( $invoices as $invoice ) {
		$number = max( 1, absint( co_sales_meta( $invoice->ID, 'installment_no', 1 ) ) );
		$receipt = co_sales_invoice_receipt( $invoice->ID );
		$payment = co_sales_invoice_payment( $invoice->ID );
		$by_installment[$number][] = array( 'id' => $invoice->ID, 'receipt' => $receipt, 'payment' => $payment );
		if ( $payment && ! $receipt ) $pending++;
	}
	$issued = 0;
	foreach ( $schedule as $index => $row ) if ( ! empty( $by_installment[$index + 1] ) ) $issued++;
	return array( 'schedule' => $schedule, 'invoices' => $by_installment, 'issued' => $issued, 'remaining' => max( 0, count( $schedule ) - $issued ), 'pending' => $pending );
}

function co_sales_document_link( $id, $label = '' ) {
	return '<a href="' . esc_url( admin_url( 'admin.php?page=co-sales-edit&document=' . absint( $id ) ) ) . '">' . esc_html( $label ?: co_sales_meta( $id, 'number' ) ) . '</a>';
}

function co_sales_derive_url( $id, $installment = 0 ) {
	$url = admin_url( 'admin-post.php?action=co_sales_derive_document&source=' . absint( $id ) );
	if ( $installment ) $url = add_query_arg( 'installment', absint( $installment ), $url );
	return wp_nonce_url( $url, 'co_sales_derive_' . $id );
}

function co_sales_render_quote_progress( $id, $inline = false ) {
	$progress = co_sales_quote_progress( $id );
	$project = absint( co_sales_meta( $id, 'project_id' ) );
	$can_invoice = ! $project || co_project_quote( $project ) === $id;
	$total = co_sales_document_total( $id ); ?>
	<section class="sales-panel sales-billing-progress"><div class="sales-panel-head"><div><h2>ติดตามงวดชำระเงิน</h2><p>ข้อมูลตามข้อตกลงที่บันทึกไว้ · วันรับเงินจริงแยกจากวันออก Receipt</p></div></div>
	<div class="sales-progress-summary"><div><small>งวดทั้งหมด</small><strong><?php echo count( $progress['schedule'] ); ?></strong></div><div><small>ออก Invoice แล้ว</small><strong><?php echo $progress['issued']; ?></strong></div><div><small>ยังไม่ออก Invoice</small><strong><?php echo $progress['remaining']; ?></strong></div><div class="<?php echo $progress['pending'] ? 'attention' : ''; ?>"><small>Invoice ที่ยังไม่มี Receipt</small><strong><?php echo $progress['pending']; ?></strong></div></div>
	<div class="sales-table-wrap"><table><thead><tr><th>งวด</th><th>เงื่อนไข</th><th>ยอดสุทธิรับเงิน</th><th>Invoice</th><th>รับเงิน / Receipt</th></tr></thead><tbody>
	<?php
	$rows = $progress['schedule'];
	foreach ( array_keys( $progress['invoices'] ) as $number ) if ( ! isset( $rows[$number - 1] ) ) $rows[$number - 1] = array( 'percent' => 0, 'description' => 'Invoice เดิมนอกแผนงวดปัจจุบัน' );
	ksort( $rows );
	foreach ( $rows as $index => $row ) : $documents = $progress['invoices'][$index + 1] ?? array(); ?>
	<?php $allocation = co_sales_installment_amounts( $id, $index + 1 ); $gross = $allocation['subtotal']; $net = $allocation['net']; ?>
	<tr><td><strong>งวด <?php echo $index + 1; ?></strong><br><?php echo esc_html( $row['percent'] ); ?>%</td><td><?php echo esc_html( $row['description'] ); ?></td><td>฿<?php echo esc_html( co_sales_money( $net ) ); ?><small class="sales-amount-before">ก่อนหัก ฿<?php echo esc_html( co_sales_money( $gross ) ); ?></small></td><td>
	<?php if ( $documents ) : foreach ( $documents as $document ) : ?><div class="sales-progress-document"><?php echo co_sales_document_link( $document['id'] ); ?><small>฿<?php echo esc_html( co_sales_money( co_sales_document_total( $document['id'] ) ) ); ?></small></div><?php endforeach; elseif ( $can_invoice ) : ?><a class="sales-btn light" href="<?php echo esc_url( co_sales_derive_url( $id, $index + 1 ) ); ?>">ออก Invoice งวดนี้</a><?php else : ?><small>เลือก QO เป็นข้อตกลงก่อน</small><?php endif; ?></td><td>
	<?php if ( $documents ) : foreach ( $documents as $document ) : ?><div class="sales-progress-document"><?php if ( $document['payment'] ) : ?><small>รับ ฿<?php echo esc_html( co_sales_money( get_post_meta( $document['payment'], '_crm_amount', true ) ) ); ?> · <?php echo esc_html( co_sales_date( get_post_meta( $document['payment'], '_crm_paid_date', true ) ) ); ?></small><?php endif; ?><?php if ( $document['payment'] && ! co_sales_payment_matches_invoice( $document['id'] ) ) : ?><span class="sales-billing-badge pending">ยอดรับเงินเดิมไม่ตรง Invoice</span><?php echo co_sales_document_link( $document['id'], 'ตรวจสอบยอดเดิม →' ); ?><?php elseif ( $document['receipt'] && $document['payment'] ) : ?><span class="sales-billing-badge complete">ครบแล้ว</span><?php echo co_sales_document_link( $document['receipt'] ); ?><?php elseif ( $document['receipt'] ) : ?><span class="sales-billing-badge pending">Receipt เดิม — ยังไม่จับคู่รับเงิน</span><?php echo co_sales_document_link( $document['id'], 'จับคู่รับเงิน →' ); ?><?php elseif ( $document['payment'] ) : ?><span class="sales-billing-badge pending">รับแล้ว—ยังไม่มี Receipt</span><a href="<?php echo esc_url( co_sales_derive_url( $document['id'] ) ); ?>" data-sales-confirm="ออก Receipt โดยไม่เพิ่มยอดรับเงินซ้ำ?">ออก Receipt →</a><?php else : ?><span class="sales-billing-badge pending">รอรับเงิน</span><?php if ( $inline ) : ?><details class="sales-inline-receive"><summary>บันทึกรับเงินงวดนี้</summary><?php co_project_render_payment( $document['id'], true ); ?></details><?php else : echo co_sales_document_link( $document['id'], 'บันทึกรับเงินงวดนี้ →' ); endif; ?><?php endif; ?></div><?php endforeach; else : ?><span class="sales-billing-badge pending">ยังไม่ออก Invoice</span><?php endif; ?></td></tr>
	<?php endforeach; ?>
	</tbody></table></div></section>
<?php }

function co_sales_render_pending_receipts() {
	$pending = array_filter( co_sales_documents(), fn( $doc ) => 'invoice' === co_sales_meta( $doc->ID, 'document_type' ) && co_sales_invoice_payment( $doc->ID ) && ! co_sales_invoice_receipt( $doc->ID ) );
	if ( ! $pending ) return; ?>
	<section class="sales-panel sales-pending-receipts"><h2>Invoice ที่ยังไม่ได้ออก Receipt (<?php echo count( $pending ); ?>)</h2><div class="sales-pending-list"><?php foreach ( $pending as $doc ) : $client = co_sales_meta( $doc->ID, 'client_snapshot', array() ); ?><article><div><?php echo co_sales_document_link( $doc->ID ); ?><small><?php echo esc_html( $client['name'] ?? '' ); ?> · <?php echo esc_html( co_sales_date( co_sales_meta( $doc->ID, 'issue_date' ) ) ); ?></small></div><strong>฿<?php echo esc_html( co_sales_money( co_sales_grand_total( $doc->ID ) ) ); ?></strong><a class="sales-btn light" data-sales-confirm="สร้าง Receipt เมื่อได้รับชำระเงินแล้ว?" href="<?php echo esc_url( co_sales_derive_url( $doc->ID ) ); ?>">ออก Receipt</a></article><?php endforeach; ?></div></section>
<?php }

function co_sales_render_listing_tracking( $id, $type ) {
	if ( 'quotation' === $type ) {
		$p = co_sales_quote_progress( $id );
		echo '<span>Invoice ' . $p['issued'] . '/' . count( $p['schedule'] ) . ' งวด</span><small>ยังไม่ออก ' . $p['remaining'] . ' งวด</small>';
		if ( $p['pending'] ) echo '<span class="sales-billing-badge pending">รอ Receipt ' . $p['pending'] . ' ใบ</span>';
	} elseif ( 'invoice' === $type ) {
		$receipt = co_sales_invoice_receipt( $id );
		if ( $receipt ) echo '<span class="sales-billing-badge complete">มี Receipt แล้ว</span>' . co_sales_document_link( $receipt );
		else echo '<span class="sales-billing-badge pending">' . ( co_sales_invoice_payment( $id ) ? 'รับแล้ว—ยังไม่มี Receipt' : 'รอรับเงิน' ) . '</span>';
	} else {
		$invoice = absint( co_sales_meta( $id, 'source_document_id' ) );
		echo $invoice ? co_sales_document_link( $invoice, 'อ้างอิง ' . co_sales_meta( $invoice, 'number' ) ) : '—';
	}
}

function co_sales_render_invoice_progress( $id ) {
	$quote = absint( co_sales_meta( $id, 'source_document_id' ) ); ?>
	<?php if ( $quote && 'quotation' === co_sales_meta( $quote, 'document_type' ) ) : ?><p>อ้างอิง <?php echo co_sales_document_link( $quote ); ?></p><?php endif; ?><?php co_project_render_payment( $id ); ?>
<?php }
