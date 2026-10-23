<?php
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/project-customer-report.php';

function co_crm_project_report_url( $project ) {
	return admin_url( 'admin.php?page=co-project-overview&project=' . absint( $project ) );
}

/** Read the existing ledgers and explicit links. Rendering never creates or syncs records. */
function co_crm_project_report_data( $id ) {
	$finance = co_project_financials( $id );
	$docs = co_sales_documents( $id ); $counts = array_fill_keys( array_keys( co_sales_types() ), 0 );
	$waiting = $missing_receipt = $mismatched = 0;
	foreach ( $docs as $doc ) {
		$type = co_sales_meta( $doc->ID, 'document_type' );
		if ( isset( $counts[$type] ) ) $counts[$type]++;
		if ( 'invoice' !== $type ) continue;
		$paid = co_sales_invoice_payment( $doc->ID );
		if ( ! $paid ) $waiting++;
		elseif ( ! co_sales_payment_matches_invoice( $doc->ID ) ) $mismatched++;
		elseif ( ! co_sales_invoice_receipt( $doc->ID ) ) $missing_receipt++;
	}
	$stream = absint( co_crm_meta( $id, 'ma_project_id' ) );
	if ( 'ma_project' !== get_post_type( $stream ) || 'publish' !== get_post_status( $stream ) || absint( co_ma_meta( $stream, 'crm_project_id' ) ) !== $id ) $stream = 0;
	$ma_client = $stream ? absint( co_ma_meta( $stream, 'client_id' ) ) : 0;
	if ( 'ma_client' !== get_post_type( $ma_client ) || 'publish' !== get_post_status( $ma_client ) ) { $stream = 0; $ma_client = 0; }
	return array( 'finance' => $finance, 'documents' => $docs, 'counts' => $counts, 'waiting' => $waiting, 'missing_receipt' => $missing_receipt, 'mismatched' => $mismatched, 'payments' => co_crm_payments( $id ), 'unallocated' => co_project_unallocated_payments( $id ), 'ma_stream' => $stream, 'ma_client' => $ma_client, 'ma_logs' => $stream ? co_ma_logs( $ma_client, $stream ) : array() );
}

function co_crm_report_field( $label, $value ) {
	echo '<div><dt>' . esc_html( $label ) . '</dt><dd>' . nl2br( esc_html( '' === (string) $value ? '—' : $value ) ) . '</dd></div>';
}

function co_crm_report_document_status( $id, $quote = 0 ) {
	$type = co_sales_meta( $id, 'document_type' );
	if ( 'quotation' === $type ) return $id === $quote ? 'QO หลักที่ลูกค้าตกลง' : 'ใบเสนอราคา — ไม่ใช่ข้อตกลงหลัก';
	if ( 'receipt' === $type ) return co_sales_meta( $id, 'payment_id' ) ? 'Receipt อ้างอิงรับเงินเดิม' : 'Receipt เดิม — ยังไม่จับคู่รับเงิน';
	if ( ! co_sales_invoice_payment( $id ) ) return co_sales_invoice_receipt( $id ) ? 'มี Receipt เดิม — ยังไม่จับคู่รับเงิน' : 'รอรับเงิน';
	if ( ! co_sales_payment_matches_invoice( $id ) ) return 'ยอดรับเงินเดิมไม่ตรง Invoice';
	return co_sales_invoice_receipt( $id ) ? 'ครบแล้ว' : 'รับแล้ว—ยังไม่มี Receipt';
}

function co_crm_report_onboarding( $submission, $customer = false ) {
	if ( 'onboarding_submit' !== get_post_type( $submission ) || 'trash' === get_post_status( $submission ) ) { echo '<p class="project-report-empty">Project นี้ไม่ได้เชื่อม Onboarding หรือข้อมูลเดิมไม่พร้อมใช้งาน</p>'; return; }
	$labels = array( 'company_name' => 'บริษัท', 'contact_name' => 'ผู้ติดต่อ', 'email' => 'อีเมล', 'phone' => 'โทรศัพท์', 'business_description' => 'รายละเอียดธุรกิจ', 'services' => 'สินค้า / บริการ', 'existing_website' => 'มีเว็บไซต์เดิม', 'website_url' => 'เว็บไซต์เดิม', 'website_type' => 'ประเภทเว็บไซต์', 'website_type_other' => 'ประเภทเว็บไซต์อื่น ๆ', 'primary_action' => 'เป้าหมายเว็บไซต์', 'primary_action_other' => 'เป้าหมายเพิ่มเติม', 'language_count' => 'จำนวนภาษา', 'languages' => 'ภาษา', 'pages' => 'หน้าที่ต้องการ', 'custom_pages' => 'หน้าเพิ่มเติม', 'content_status' => 'สถานะเนื้อหา', 'assets' => 'สื่อที่มีแล้ว', 'assets_other' => 'สื่ออื่น ๆ', 'features' => 'ฟังก์ชัน', 'features_other' => 'ฟังก์ชันเพิ่มเติม', 'domain_status' => 'สถานะโดเมน', 'domain' => 'โดเมน', 'hosting_status' => 'สถานะ Hosting', 'hosting_choice' => 'Hosting ที่เลือก', 'target_launch' => 'วันที่ต้องการเปิดเว็บไซต์', 'no_date' => 'ยังไม่กำหนดวันเปิด', 'notes' => 'หมายเหตุจากลูกค้า' );
	echo '<dl class="project-report-fields">';
	foreach ( $labels as $key => $label ) {
		$value = get_post_meta( $submission, '_onboarding_' . $key, true );
		if ( '' === $value || array() === $value ) continue;
		if ( is_array( $value ) ) $value = implode( ', ', array_filter( $value, 'is_scalar' ) );
		if ( 'no_date' === $key ) $value = $value ? 'ยังไม่กำหนด' : 'กำหนดวันที่แล้ว';
		if ( 'target_launch' === $key ) $value = co_sales_date( $value );
		co_crm_report_field( $label, $value );
	}
	echo '</dl>';
	$references = (array) get_post_meta( $submission, '_onboarding_references', true );
	foreach ( $references as $reference ) if ( is_array( $reference ) && ! empty( $reference['url'] ) ) echo '<p class="project-report-reference"><a target="_blank" rel="noopener" href="' . esc_url( $reference['url'] ) . '">' . esc_html( $reference['url'] ) . ' ↗</a><br>' . nl2br( esc_html( $reference['notes'] ?? '' ) ) . '</p>';
	if ( ! $customer ) echo '<p><a target="_blank" rel="noopener" href="' . esc_url( get_permalink( $submission ) ) . '">เปิด Onboarding Summary ต้นฉบับ ↗</a></p>';
}

function co_crm_report_installments( $quote, $docs ) {
	if ( ! $quote ) { echo '<p class="project-report-empty">ยังไม่ได้เลือก QO หลัก จึงยังไม่มีแผนงวดที่ตกลง เอกสารทั้งหมดแสดงอยู่ด้านล่าง</p>'; return; }
	$progress = co_sales_quote_progress( $quote ); $allowed = array_column( $docs, 'ID' );
	echo '<p>อ้างอิง ' . co_sales_document_link( $quote ) . ' · ราคาหลังส่วนลดและแผนงวดจาก QO หลัก</p><div class="project-report-installments">';
	$rows = $progress['schedule'];
	foreach ( array_keys( $progress['invoices'] ) as $number ) if ( ! isset( $rows[$number - 1] ) ) $rows[$number - 1] = array( 'percent' => 0, 'description' => 'Invoice เดิมนอกแผนงวดปัจจุบัน' );
	ksort( $rows );
	foreach ( $rows as $index => $row ) {
		$amounts = co_sales_installment_amounts( $quote, $index + 1 );
		echo '<article class="project-report-installment"><header><div><small>งวดที่ ' . ( $index + 1 ) . ' · ' . esc_html( $row['percent'] ) . '%</small><h3>' . esc_html( $row['description'] ) . '</h3></div><strong>฿' . esc_html( co_sales_money( $amounts['net'] ) ) . '</strong></header><p class="project-report-muted">ก่อนหัก ฿' . esc_html( co_sales_money( $amounts['subtotal'] ) ) . ' · หัก ณ ที่จ่าย ฿' . esc_html( co_sales_money( $amounts['wht'] ) ) . '</p>';
		$invoices = array_filter( $progress['invoices'][$index + 1] ?? array(), fn( $doc ) => in_array( $doc['id'], $allowed, true ) );
		if ( ! $invoices ) echo '<span class="project-report-badge warning">ยังไม่ออก Invoice</span>';
		foreach ( $invoices as $invoice ) {
			echo '<div class="project-report-payment-chain">' . co_sales_document_link( $invoice['id'] ) . '<span class="project-report-badge">' . esc_html( co_crm_report_document_status( $invoice['id'] ) ) . '</span>';
			if ( $invoice['payment'] ) echo '<span>รับเงินจริง ฿' . esc_html( co_sales_money( co_crm_meta( $invoice['payment'], 'amount' ) ) ) . ' · ' . esc_html( co_sales_date( co_crm_meta( $invoice['payment'], 'paid_date' ) ) ) . '</span>';
			if ( $invoice['receipt'] && in_array( $invoice['receipt'], $allowed, true ) ) echo co_sales_document_link( $invoice['receipt'] );
			echo '</div>';
		}
		echo '</article>';
	}
	echo '</div>';
}

function co_crm_report_documents( $docs, $quote ) {
	if ( ! $docs ) { echo '<p class="project-report-empty">ยังไม่มีเอกสารที่เชื่อมกับ Project นี้</p>'; return; }
	foreach ( $docs as $doc ) {
		$id = $doc->ID; $type = co_sales_types()[co_sales_meta( $id, 'document_type' )] ?? 'เอกสาร'; $breakdown = co_sales_document_breakdown( $id );
		$client = (array) co_sales_meta( $id, 'client_snapshot', array() ); $issuer = (array) co_sales_meta( $id, 'issuer_snapshot', array() );
		if ( empty( $issuer['bank'] ) ) foreach ( co_sales_issuers() as $profile ) if ( (string) ( $profile['id'] ?? '' ) === (string) ( $issuer['id'] ?? co_sales_meta( $id, 'issuer_id' ) ) ) { $issuer['bank'] = $profile['bank'] ?? ''; break; }
		$source = absint( co_sales_meta( $id, 'source_document_id' ) );
		echo '<article class="project-report-document" data-report-document="' . $id . '"><header><div><small>' . esc_html( $type ) . ' · ' . esc_html( co_sales_date( co_sales_meta( $id, 'issue_date' ) ) ) . '</small><h3>' . co_sales_document_link( $id ) . '</h3><span class="project-report-badge">' . esc_html( co_crm_report_document_status( $id, $quote ) ) . '</span></div><div class="project-report-doc-total"><small>ยอดสุทธิในใบนี้</small><strong>฿' . esc_html( co_sales_money( co_sales_grand_total( $id ) ) ) . '</strong><a target="_blank" rel="noopener" href="' . esc_url( admin_url( 'admin.php?page=co-sales-preview&document=' . $id ) ) . '">Preview / PDF ↗</a></div></header>';
		if ( $source && in_array( $source, array_column( $docs, 'ID' ), true ) ) echo '<p>อ้างอิง ' . co_sales_document_link( $source ) . '</p>';
		echo '<details><summary>ดูรายละเอียดเอกสารและรายการงาน</summary><dl class="project-report-fields">';
		foreach ( array( 'ชื่องาน / หัวข้อเอกสาร' => co_sales_meta( $id, 'title' ), 'ผู้รับเอกสาร' => $client['name'] ?? get_the_title( co_sales_meta( $id, 'client_id' ) ), 'ที่อยู่ออกบิล' => $client['address'] ?? '', 'เลขผู้เสียภาษีลูกค้า' => $client['tax_id'] ?? '', 'ผู้ติดต่อออกบิล' => $client['contact_name'] ?? '', 'อีเมลลูกค้า' => $client['email'] ?? '', 'โทรศัพท์ลูกค้า' => $client['phone'] ?? '', 'ผู้ออกเอกสาร' => $issuer['name'] ?? '', 'ที่อยู่ผู้ออก' => $issuer['address'] ?? '', 'เลขผู้เสียภาษีผู้ออก' => $issuer['tax_id'] ?? '', 'ราคาในใบนี้ก่อนส่วนลด' => '฿' . co_sales_money( $breakdown['price'] ), 'ส่วนลดในใบนี้' => '฿' . co_sales_money( $breakdown['discount'] ), 'ยอดหลังส่วนลดก่อนหัก' => '฿' . co_sales_money( co_sales_document_total( $id ) ), 'หัก ณ ที่จ่าย ' . co_sales_meta( $id, 'wht_rate', 3 ) . '%' => '฿' . co_sales_money( co_sales_wht( $id ) ) ) as $label => $value ) co_crm_report_field( $label, $value );
		echo '</dl><div class="project-report-work-items">';
		foreach ( co_sales_items( $id ) as $index => $item ) {
			echo '<div><header><strong>รายการที่ ' . ( $index + 1 ) . '</strong><b>' . ( '' === (string) $item['amount'] ? '' : '฿' . esc_html( co_sales_money( $item['amount'] ) ) ) . '</b></header><div class="project-report-copy">';
			foreach ( co_sales_description_lines( $item ) as $line ) echo '<div>' . $line['html'] . '</div>';
			echo '</div></div>';
		}
		echo '</div><h4>เงื่อนไขชำระของเอกสาร</h4>';
		foreach ( co_sales_billing_schedule( $id ) as $row ) echo '<p>' . esc_html( $row['percent'] . '% ' . $row['description'] ) . '</p>';
		if ( 'receipt' !== co_sales_meta( $id, 'document_type' ) ) echo '<h4>Payment / Bank details</h4><p class="project-report-copy">' . nl2br( esc_html( $issuer['bank'] ?? '' ) ) . '</p>';
		if ( co_sales_meta( $id, 'notes' ) ) echo '<h4>หมายเหตุ</h4><p class="project-report-copy">' . nl2br( esc_html( co_sales_meta( $id, 'notes' ) ) ) . '</p>';
		echo '</details></article>';
	}
}

function co_crm_report_ma( $data ) {
	$client = $data['ma_client']; $stream = $data['ma_stream'];
	if ( ! $client || ! $stream ) { echo '<p class="project-report-empty">ยังไม่ได้เชื่อมโปรเจกต์ MA กับ Project นี้ เลือกเชื่อมหรือเริ่ม MA ได้จากแถบรายละเอียด Project ใน Pipeline</p>'; return; }
	$monthly = 'monthly_retainer' === co_ma_package_type( $client ); $allowance = co_ma_allowance( $client ); $package_used = co_ma_current_used( $client );
	$own_used = co_ma_used_hours( $client, $stream, $monthly ? wp_date( 'Y-m' ) : '' ); $expiry = co_ma_meta( $client, 'expiry_date' ); $start = co_ma_meta( $client, 'start_date' ); $today = wp_date( 'Y-m-d' );
	$status = co_ma_meta( $client, 'status', 'active' ); $status = array( 'active' => 'ใช้งาน', 'inactive' => 'ไม่ใช้งาน', 'expired' => 'หมดอายุ' )[$status] ?? $status;
	if ( co_sales_valid_date( $expiry ) && $expiry < $today ) $status = 'หมดอายุ';
	elseif ( co_sales_valid_date( $start ) && $start > $today ) $status = 'ยังไม่เริ่ม';
	elseif ( ! $monthly && $package_used >= $allowance ) $status = 'ชั่วโมงหมด';
	echo '<div class="project-report-section-head"><div><h3>' . esc_html( get_the_title( $stream ) ) . '</h3><p>แพ็กเกจ: ' . esc_html( get_the_title( $client ) ) . '</p></div><a class="crm-btn ghost-dark" href="' . esc_url( admin_url( 'admin.php?page=ma-client-detail&client=' . $client ) ) . '">จัดการ MA →</a></div><dl class="project-report-fields">';
	foreach ( array( 'บริษัท MA' => get_the_title( $client ), 'รูปแบบ' => $monthly ? 'Monthly retainer — โควตาใหม่ทุกเดือน ไม่ทบยอด' : 'Hour bank — หักชั่วโมงสะสม', 'สถานะปัจจุบัน' => $status, 'วันเริ่ม MA' => co_ma_date( $start ), 'วันหมดอายุ MA' => co_ma_date( $expiry ), 'ผู้ติดต่อ MA' => co_ma_meta( $client, 'contact_name' ), 'อีเมล MA' => co_ma_meta( $client, 'contact_email' ), 'ชั่วโมงแพ็กเกจ' => co_ma_hours( $allowance ) . ( $monthly ? ' ชั่วโมง / เดือน' : ' ชั่วโมง' ), $monthly ? 'Project นี้ใช้เดือนปัจจุบัน' : 'Project นี้ใช้สะสม' => co_ma_hours( $own_used ) . ' ชั่วโมง', $monthly ? 'ทุกงานในแพ็กเกจใช้เดือนปัจจุบัน' : 'ทุกงานในแพ็กเกจใช้สะสม' => co_ma_hours( $package_used ) . ' ชั่วโมง', $monthly ? 'แพ็กเกจคงเหลือเดือนปัจจุบัน' : 'แพ็กเกจคงเหลือ' => co_ma_hours( $allowance - $package_used ) . ' ชั่วโมง' ) as $label => $value ) co_crm_report_field( $label, $value );
	echo '</dl><p class="project-report-muted">ยอดคงเหลือเป็นยอดร่วมของทั้งแพ็กเกจ ไม่ใช่โควตาแยกของ Project นี้ · สรุปชั่วโมงเฉพาะรายการที่หักโควตา · MA ที่รวมมากับงานไม่เพิ่มยอดขาย</p>';
	if ( $monthly ) echo '<p class="project-report-muted">โควตาในสรุปรายเดือนอ้างอิงการตั้งค่าแพ็กเกจปัจจุบัน</p>';
	if ( co_ma_meta( $stream, 'description' ) ) echo '<p class="project-report-copy">' . nl2br( esc_html( co_ma_meta( $stream, 'description' ) ) ) . '</p>';
	$months = array(); foreach ( $data['ma_logs'] as $log ) { $date = co_ma_meta( $log->ID, 'work_date' ); if ( co_sales_valid_date( $date ) ) $months[substr( $date, 0, 7 )] = true; }
	if ( $monthly && co_sales_valid_date( $start ) ) {
		$cursor = new DateTimeImmutable( substr( $start, 0, 7 ) . '-01' ); $end = min( substr( $today, 0, 7 ), co_sales_valid_date( $expiry ) ? substr( $expiry, 0, 7 ) : substr( $today, 0, 7 ) );
		while ( $cursor->format( 'Y-m' ) <= $end ) { $months[$cursor->format( 'Y-m' )] = true; $cursor = $cursor->modify( '+1 month' ); }
	}
	krsort( $months );
	if ( $months ) {
		echo '<h3>สรุปชั่วโมงรายเดือน</h3><div class="project-report-months">';
		foreach ( array_keys( $months ) as $month ) {
			echo '<article><small>' . esc_html( wp_date( 'F Y', strtotime( $month . '-01' ) ) ) . '</small><strong>' . esc_html( co_ma_hours( co_ma_used_hours( $client, $stream, $month ) ) ) . ' ชั่วโมง</strong><span>เฉพาะ Project นี้</span>';
			if ( $monthly ) echo '<span>แพ็กเกจรวมใช้ ' . esc_html( co_ma_hours( co_ma_used_hours( $client, 0, $month ) ) ) . ' / ' . esc_html( co_ma_hours( $allowance ) ) . ' ชั่วโมง</span>';
			echo '</article>';
		}
		echo '</div>';
	}
	echo '<h3>ประวัติงาน MA ของ Project นี้ (' . count( $data['ma_logs'] ) . ')</h3><div class="project-report-log-list">';
	foreach ( $data['ma_logs'] as $log ) echo '<article><header><div><small>' . esc_html( co_ma_date( co_ma_meta( $log->ID, 'work_date' ) ) ) . '</small><h4>' . esc_html( $log->post_title ) . '</h4></div><strong>' . esc_html( co_ma_hours( co_ma_meta( $log->ID, 'hours', 0 ) ) ) . ' ชั่วโมง</strong></header><p class="project-report-copy">' . nl2br( esc_html( co_ma_meta( $log->ID, 'description' ) ) ) . '</p><small>ผู้ทำ: ' . esc_html( co_ma_meta( $log->ID, 'assignee', '—' ) ) . ' · ' . ( '0' === (string) co_ma_meta( $log->ID, 'billable', '1' ) ? 'ไม่หักโควตา' : 'หักโควตา' ) . '</small></article>';
	if ( ! $data['ma_logs'] ) echo '<p class="project-report-empty">ยังไม่มีการบันทึกงาน MA ของ Project นี้</p>';
	echo '</div>';
}

function co_crm_project_report() {
	if ( ! current_user_can( 'edit_posts' ) ) wp_die( 'Permission denied.' );
	$id = absint( $_GET['project'] ?? 0 ); $project = get_post( $id );
	if ( ! $project || 'client_project' !== $project->post_type || 'publish' !== $project->post_status ) { echo '<div class="wrap"><h1>ไม่พบ Project</h1></div>'; return; }
	if ( ! current_user_can( 'edit_post', $id ) ) wp_die( 'Permission denied.' );
	$data = co_crm_project_report_data( $id ); $finance = $data['finance']; $quote = $finance['quote']; $stage = co_crm_project_stage( $id, false ); $onboarding = absint( co_crm_meta( $id, 'onboarding_id' ) ); $billing = $quote ? absint( co_sales_meta( $quote, 'client_id' ) ) : absint( co_crm_meta( $id, 'sales_client_id' ) );
	co_crm_assets(); ?><style><?php include CO_THEME_DIR . '/assets/project-report.css'; ?></style>
	<div class="crm-wrap project-report"><a class="crm-back" href="<?php echo esc_url( admin_url( 'admin.php?page=co-projects&open_project=' . $id ) ); ?>">← กลับ Project ใน Pipeline</a>
	<header class="crm-hero"><div><small>PROJECT REPORT · สำหรับแอดมิน</small><h1><?php echo esc_html( $project->post_title ); ?></h1><p><?php echo esc_html( co_crm_meta( $id, 'company' ) ); ?> · ข้อมูลเชื่อมจาก Project #<?php echo $id; ?></p><span class="project-report-hero-status"><?php echo esc_html( co_crm_stages()[$stage] ); ?></span></div><a class="crm-btn white" href="<?php echo esc_url( admin_url( 'admin.php?page=co-project-detail&project=' . $id ) ); ?>">แก้ไข Project →</a></header>
	<section class="crm-stats"><div><small>มูลค่างานหลังส่วนลด</small><strong>฿<?php echo esc_html( co_crm_money( $finance['value'] ) ); ?></strong></div><div><small>ยอดหลังหัก ณ ที่จ่าย</small><strong>฿<?php echo esc_html( co_crm_money( $finance['net'] ) ); ?></strong></div><div><small>รับเงินจริงแล้ว</small><strong>฿<?php echo esc_html( co_crm_money( $finance['received'] ) ); ?></strong></div><div><small>ยอดคงค้างสุทธิ</small><strong>฿<?php echo esc_html( co_crm_money( $finance['outstanding'] ) ); ?></strong></div></section>
	<nav class="project-report-nav" aria-label="ส่วนของรายงาน"><a href="#report-overview">สถานะและข้อมูลงาน</a><a href="#report-brief">บรีฟ / Onboarding / Assets</a><a href="#report-documents">เอกสารและงวดชำระ</a><a href="#report-cash">ประวัติรับเงิน</a><a href="#report-ma">MA และประวัติงาน</a></nav>
	<section class="project-report-alerts" aria-label="รายการที่ต้องติดตาม">
	<?php if ( ! in_array( $stage, co_crm_sold_stages(), true ) ) : ?><p>สถานะนี้ยังไม่นับเป็นยอดขายในรายงานปี — เริ่มนับตั้งแต่ Wireframe แต่ยอดรับเงินจริงยังแสดงตามรายการเดิม</p><?php endif; ?>
	<?php if ( ! $quote ) : ?><p>ยังไม่ได้เลือก QO หลัก ตัวเลขมูลค่างานอ้างอิงข้อมูล Project เดิม</p><?php endif; ?>
	<?php if ( $data['waiting'] ) : ?><p>Invoice รอรับเงิน <?php echo $data['waiting']; ?> ใบ</p><?php endif; ?>
	<?php if ( $data['missing_receipt'] ) : ?><p>รับเงินแล้วแต่ยังไม่มี Receipt <?php echo $data['missing_receipt']; ?> ใบ</p><?php endif; ?>
	<?php if ( $data['mismatched'] ) : ?><p>ยอดรับเงินเดิมไม่ตรง Invoice <?php echo $data['mismatched']; ?> ใบ — ต้องตรวจสอบ</p><?php endif; ?>
	<?php if ( $data['unallocated'] ) : ?><p>รับเงินเดิมยังไม่จับคู่กับ Invoice <?php echo count( $data['unallocated'] ); ?> รายการ — ยอดนี้รวมในเงินรับแล้ว ไม่ต้องสร้างซ้ำ</p><?php endif; ?>
	<?php if ( $finance['received'] > $finance['net'] ) : ?><p>รับเงินเกินยอดสุทธิ ฿<?php echo esc_html( co_sales_money( $finance['received'] - $finance['net'] ) ); ?> — กรุณาตรวจสอบรายการเดิม</p><?php endif; ?>
	<?php $ma_expiry = $data['ma_client'] ? co_ma_meta( $data['ma_client'], 'expiry_date' ) : ''; if ( co_sales_valid_date( $ma_expiry ) && $ma_expiry <= wp_date( 'Y-m-d', strtotime( '+7 days', strtotime( wp_date( 'Y-m-d' ) ) ) ) ) : ?><p>MA <?php echo $ma_expiry < wp_date( 'Y-m-d' ) ? 'หมดอายุแล้ว' : 'ใกล้หมดอายุภายใน 7 วัน'; ?> · <?php echo esc_html( co_ma_date( $ma_expiry ) ); ?></p><?php endif; ?>
	</section>
	<section class="project-report-section" id="report-overview"><h2>สถานะและข้อมูลงาน</h2><ol class="project-report-stages"><?php foreach ( co_crm_stages() as $key => $label ) : ?><li <?php echo $key === $stage ? 'aria-current="step" class="current"' : ''; ?>><?php echo esc_html( $label ); ?></li><?php endforeach; ?></ol><p class="project-report-muted">แสดงสถานะปัจจุบัน ไม่ใช่ประวัติการเปลี่ยนสถานะ · การออกเอกสารไม่เปลี่ยนสถานะงานอัตโนมัติ</p><div class="project-report-two"><div><h3>เจ้าของงาน</h3><dl class="project-report-fields"><?php foreach ( array( 'ชื่อ Project' => $project->post_title, 'บริษัทเจ้าของงาน' => co_crm_meta( $id, 'company' ), 'ผู้ติดต่อ' => co_crm_meta( $id, 'contact_name' ), 'อีเมล' => co_crm_meta( $id, 'contact_email' ), 'วันที่เริ่มงาน' => co_sales_date( co_crm_meta( $id, 'start_date' ) ), 'กำหนดส่ง' => co_sales_date( co_crm_meta( $id, 'deadline' ) ), 'งานที่กำลังทำ / รออยู่' => co_crm_meta( $id, 'current_task' ), 'เลขที่ใบเสนอราคาเดิม' => co_crm_meta( $id, 'quotation_no' ) ) as $label => $value ) co_crm_report_field( $label, $value ); ?></dl></div><div><h3>ลูกค้าสำหรับออกบิล</h3><?php if ( 'sales_client' === get_post_type( $billing ) ) : ?><dl class="project-report-fields"><?php foreach ( array( 'บริษัทออกบิล' => get_the_title( $billing ), 'ที่อยู่' => co_sales_meta( $billing, 'address' ), 'เลขผู้เสียภาษี' => co_sales_meta( $billing, 'tax_id' ), 'ชื่อย่อ' => co_sales_meta( $billing, 'short_name' ), 'ผู้ติดต่อ' => co_sales_meta( $billing, 'contact_name' ), 'อีเมล' => co_sales_meta( $billing, 'email' ), 'โทรศัพท์' => co_sales_meta( $billing, 'phone' ) ) as $label => $value ) co_crm_report_field( $label, $value ); ?></dl><?php else : ?><p class="project-report-empty">ยังไม่เชื่อมลูกค้าสำหรับออกบิล</p><?php endif; ?><h3>Timeline</h3><?php if ( co_crm_meta( $id, 'timeline_url' ) ) : ?><a class="crm-btn ghost-dark" target="_blank" rel="noopener" href="<?php echo esc_url( co_crm_meta( $id, 'timeline_url' ) ); ?>">เปิด Google Sheet Timeline ↗</a><p class="project-report-muted">ใช้ Timeline ใน Google Sheet เดิม ไม่ได้ซิงก์หรือคัดลอก Task ในชีต</p><?php else : ?><p class="project-report-empty">ยังไม่แนบ Google Sheet Timeline</p><?php endif; ?></div></div></section>
	<section class="project-report-section" id="report-brief"><h2>บรีฟ / Onboarding / Assets</h2><h3>บรีฟเพิ่มเติม / Internal notes</h3><div class="project-report-copy"><?php echo nl2br( esc_html( co_crm_project_brief( $id ) ?: 'ยังไม่มีบรีฟเพิ่มเติม' ) ); ?></div><h3>ข้อมูล Onboarding ทั้งหมด</h3><?php co_crm_report_onboarding( $onboarding ); ?><h3>ไฟล์ของ Project และ Onboarding (<?php echo count( co_crm_project_assets( $id ) ); ?>)</h3><?php if ( co_crm_project_assets( $id ) ) co_crm_render_assets( $id ); else echo '<p class="project-report-empty">ยังไม่มีไฟล์ที่เชื่อมกับ Project นี้</p>'; ?></section>
	<section class="project-report-section" id="report-documents"><h2>เอกสารและงวดชำระ</h2><div class="project-report-doc-counts"><?php foreach ( $data['counts'] as $type => $count ) : ?><div><small><?php echo esc_html( co_sales_types()[$type] ); ?></small><strong><?php echo $count; ?> ใบ</strong></div><?php endforeach; ?></div><p class="project-report-muted">แสดงเฉพาะเอกสารที่เชื่อมกับ Project นี้ ไม่รวมยอด QO + Invoice + Receipt ซ้ำเป็นยอดขาย</p><h3>งวดตามข้อตกลงหลัก</h3><?php co_crm_report_installments( $quote, $data['documents'] ); ?><h3>เอกสารทั้งหมดและรายละเอียด</h3><?php co_crm_report_documents( $data['documents'], $quote ); ?></section>
	<section class="project-report-section" id="report-cash"><h2>ประวัติรับเงินจริง (<?php echo count( $data['payments'] ); ?> รายการ)</h2><p class="project-report-muted">ยึดวันที่เงินเข้าจริง ไม่ใช่วันที่ออก Receipt · แสดงทั้งรายการตามงวดและรับเงินเดิมที่ยังไม่จับคู่</p><div class="project-report-log-list"><?php foreach ( $data['payments'] as $payment ) : $invoice = co_sales_payment_invoice( $payment->ID ); $receipt = $invoice ? co_sales_invoice_receipt( $invoice ) : 0; ?><article><header><div><small>รับเงินจริง <?php echo esc_html( co_sales_date( co_crm_meta( $payment->ID, 'paid_date' ) ) ); ?></small><h3><?php echo esc_html( $payment->post_title ); ?></h3></div><strong>฿<?php echo esc_html( co_sales_money( co_crm_meta( $payment->ID, 'amount' ) ) ); ?></strong></header><div class="project-report-payment-chain"><?php if ( $invoice && in_array( $invoice, array_column( $data['documents'], 'ID' ), true ) ) echo co_sales_document_link( $invoice ); else echo '<span class="project-report-badge warning">ยังไม่จับคู่ Invoice</span>'; if ( $receipt && in_array( $receipt, array_column( $data['documents'], 'ID' ), true ) ) echo co_sales_document_link( $receipt ); else echo '<span class="project-report-badge warning">ยังไม่มี Receipt ที่เชื่อม</span>'; ?></div><?php if ( co_crm_meta( $payment->ID, 'note' ) ) : ?><p class="project-report-copy"><?php echo nl2br( esc_html( co_crm_meta( $payment->ID, 'note' ) ) ); ?></p><?php endif; ?></article><?php endforeach; ?><?php if ( ! $data['payments'] ) echo '<p class="project-report-empty">ยังไม่มีการบันทึกรับเงินจริง</p>'; ?></div></section>
	<section class="project-report-section" id="report-ma"><h2>MA และประวัติงาน</h2><?php co_crm_report_ma( $data ); ?></section><footer class="project-report-footer">Project #<?php echo $id; ?> · รายงานสำหรับแอดมิน · อ่านข้อมูลปัจจุบัน ไม่สร้างหรือเปลี่ยนรายการใด ๆ</footer></div>
<?php }
