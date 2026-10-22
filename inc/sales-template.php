<?php
defined( 'ABSPATH' ) || exit;

function co_sales_sync_payment( $id ) {
	if ( 'receipt' !== co_sales_meta( $id, 'document_type' ) ) return;
	$project_id = absint( co_sales_meta( $id, 'project_id' ) );
	$payment_id = absint( co_sales_meta( $id, 'payment_id' ) );
	if ( 'client_project' !== get_post_type( $project_id ) ) return;
	$number = co_sales_meta( $id, 'number' );
	$data = array( 'post_type' => 'project_payment', 'post_status' => 'publish', 'post_title' => 'Receipt ' . $number );
	if ( $payment_id && 'project_payment' === get_post_type( $payment_id ) ) $data['ID'] = $payment_id;
	$payment_id = wp_insert_post( $data, true );
	if ( is_wp_error( $payment_id ) ) return;
	update_post_meta( $payment_id, '_crm_project_id', $project_id );
	update_post_meta( $payment_id, '_crm_amount', co_sales_grand_total( $id ) );
	update_post_meta( $payment_id, '_crm_paid_date', co_sales_meta( $id, 'issue_date' ) );
	update_post_meta( $payment_id, '_crm_note', 'Receipt ' . $number );
	update_post_meta( $id, '_sales_payment_id', $payment_id );
}

function co_sales_default_items() {
	return array(
		array( 'text' => "Project Concept :\nWeb strategic Planning :\n- Creative Strategic Direction - วางแผนกำหนดแนวคิดในการสื่อสารที่เหมาะสมกับแบรนด์\n- Communication Strategy - ออกแบบการสื่อสารของแบรนด์\n- User Behavior Flow (UX Strategic) - ออกแบบการใช้งานที่เหมาะสมของเว็บไซต์\n- Customer Experience - วางแผนสร้างประสบการณ์ที่ดีสำหรับผู้ใช้งาน\n- Brand Perception - สร้างการจดจำของแบรนด์\n- Engagement & Conversion - เพิ่มการมีส่วนร่วมกับแบรนด์ในมิติต่างๆ\n- Measurement & Analysis - วางแผนการวัดผลในมิติต่างๆ ให้รอบด้าน\n- Optimization - วางกรอบแนวทางในการปรับปรุงหลังการใช้งานแล้ว\n- Reach Goal - ผลักดันให้งานบรรลุเป้าหมาย\n- Compettitor Analysis - วิเคราะห์คู่แข่งเทียบเคียง\n- Website Benchmark - หาตัวอย่างมาตรฐานที่เหมาะสมที่จะนำมาใช้งาน\n- Scope of Works - กำหนดกรอบของความรับผิดชอบทั้งหมดตลอดอายุสัญญา\n- Wireframe - ออกแบบร่างหน้าจอต่างๆ อุปกรณ์มือถือ\n\nProject Execution :\nWebsite Branding - ออกแบบและพัฒนาเว็บไซต์นำเสนอภาพลักษณ์แบรนด์\n- Home Page\n- Our Curriculums (Certified ISO 9001 / British Curriulum Pearson Edexcel)\n- Preschool English Course (Playground / Early Years)\n- Primary (Skill Improvement / Conversation)\n- iLower Secondary\n- Pre University (IELTS / TOEIC / TOEFL Preparation)\n- Academic / Research Skills (Academic Writing / English For Presentation)\n- English For Adults (Business English / Born to be)\n- Certified Tutor\n- English Test (CEFR Placement Test / Mock IELTS)\n- Exam Centre\n- About EFL\n- Organization\n- Awards of Excellence\n- Testimonials\n- Gallery\n- News / Detail page\n- Become Our Partner School\n- Blog / Detail Blog", 'amount' => '' ),
		array( 'text' => "- Contact us (Location 1 / Location 2)\n- Thank you page", 'amount' => '' ),
		array( 'text' => "Customize Function\n- ปรับแต่งฟังก์ชันการอัปโหลดเนื้อหาข้อมูลบนเว็บไซต์", 'amount' => '' ),
		array( 'text' => "การดูแลครอบคลุมถึง\n- Mobile & Tablet Responsive Design\n- PDPA - พรบคุ้มครองข้อมูลส่วนบุคคล\n- User Manual Website\n- Free Server 1 Year ( Value 1,500 baht)", 'amount' => '' ),
		array( 'text' => "Multiple Languages - เว็บไซต์ 3 ภาษา EN-TH-CN\n(ภาษาที่2 คิดภาษาละ 20% ของราคางานสุทธิ)", 'amount' => '' ),
		array( 'text' => "Retaining & Maintenance [Free]\nWebsite Warranted      6 months  [ประกันผลงานการทำงานของเว็บไซต์]\nWebsite Maintenance   6 months  [ดูแลแนะนำให้คำปรึกษาแนะนำและปรับแต่งเว็บไซต์]\nในการดูแลรักษาทีมเราจะช่วยดูแลรักษาเว็บไซต์หลังขึ้นเว็บไซต์ออนไลน์ตามระยะสัญญา\nโดยการทำงานจะคิดเป็น เดือนละ 20 ชั่วโมง เราจะดูแลให้ 4 หัวข้อใหญ่ดังนี้\n- Bug fixed (แก้ไขบั๊คที่เจอ)\n- Technical Support (อัพเดท Theme, Plugins, wordpress, Backup site)\n- Content Update (ช่วยลงข้อมูล แก้ไขรูปภาพ แก้ไขข้อความ)\n- Compatibility and Performance Monitoring across browsers and devices\n(มอร์นิเตอร์เว็บไซต์ ความเร็วในการโหลด, ภาพรวมเว็บไซต์)\n\nสิ่งที่ลูกค้าจะได้รับ :\nสิทธิในการเป็นเจ้าของงานออกแบบทั้งหมด\nสิทธิในการเป็นเจ้าของและเข้าถึงข้อมูลเว็บไซต์ทั้งหมด\n\nระยะเวลาในการทำงาน : 65 วัน\n(ขึ้นอยู่กับการเตรียมพร้อมข้อมูลจริงที่จะใช้บนระบบและเว็บไซต์)", 'amount' => '' ),
	);
}

function co_sales_default_schedule_text() {
	return "30|หลังเซ็นต์เอกสารใบเสนอราคา ยืนยันการจัดจ้างทำ / ส่งมอบ Working Timeline,\n30|หลังวางโครงแบบ (Wireframe) และออกแบบเว็บไซต์แล้วเสร็จทั้งหมด\n30|หลังระบบเว็บไซต์ฟังก์ชั่นแล้วเสร็จทั้งหมด ทุกหน้าจออุปกรณ์ ส่งมอบงาน พร้อมคู่มือการใช้งานเว็บไซต์\n10|ส่งมอบงาน พร้อมคู่มือการใช้งานเว็บไซต์";
}

function co_sales_items( $id ) {
	$items = co_sales_meta( $id, 'items', array() );
	if ( $items ) return $items;
	$source_id = absint( co_sales_meta( $id, 'source_document_id' ) );
	$expected = array( 'invoice' => 'quotation', 'receipt' => 'invoice' )[co_sales_meta( $id, 'document_type' )] ?? '';
	if ( $source_id && $expected && $expected === co_sales_meta( $source_id, 'document_type' ) ) return co_sales_items( $source_id );
	// Existing documents retain their entered content until the user changes it.
	return array( array( 'text' => co_sales_meta( $id, 'title' ) . "\n" . co_sales_meta( $id, 'body' ), 'amount' => co_sales_document_total( $id ) ) );
}

function co_sales_baht_text( $amount ) {
	$digits = array( 'ศูนย์', 'หนึ่ง', 'สอง', 'สาม', 'สี่', 'ห้า', 'หก', 'เจ็ด', 'แปด', 'เก้า' );
	$read = function ( $number ) use ( &$read, $digits ) {
		$number = (int) $number;
		if ( $number >= 1000000 ) return $read( intdiv( $number, 1000000 ) ) . 'ล้าน' . ( $number % 1000000 ? $read( $number % 1000000 ) : '' );
		$text = ''; $str = (string) $number; $length = strlen( $str );
		$places = array( '', 'สิบ', 'ร้อย', 'พัน', 'หมื่น', 'แสน' );
		for ( $i = 0; $i < $length; $i++ ) {
			$n = (int) $str[$i]; $position = $length - $i - 1;
			if ( ! $n ) continue;
			$text .= ( 1 === $position && 1 === $n ) ? '' : ( ( 1 === $position && 2 === $n ) ? 'ยี่' : ( ( 0 === $position && 1 === $n && $length > 1 ) ? 'เอ็ด' : $digits[$n] ) );
			$text .= $places[$position];
		}
		return $text ?: 'ศูนย์';
	};
	$value = explode( '.', number_format( max( 0, (float) $amount ), 2, '.', '' ) );
	return $read( (int) $value[0] ) . 'บาท' . ( (int) $value[1] ? $read( (int) $value[1] ) . 'สตางค์' : 'ถ้วน' );
}

function co_sales_description_is_bold( $text ) {
	return (bool) preg_match( '/^(Project |Web strategic|Website Branding|Customize|การดูแล|Multiple |\(ภาษาที่|Retaining|โดยการ|สิ่งที่|สิทธิ|ระยะเวลา|\(ขึ้นอยู่)/u', trim( $text ) );
}

/** Normalize editor HTML into independent lines containing only text and bold. */
function co_sales_rich_lines( $html ) {
	$document = new DOMDocument();
	$document->loadHTML( '<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
	$rows = array( array( 'text' => '', 'html' => '' ) );
	$new_line = function () use ( &$rows ) { $rows[] = array( 'text' => '', 'html' => '' ); };
	$walk = function ( $node, $bold = false ) use ( &$walk, &$rows, $new_line ) {
		if ( XML_TEXT_NODE === $node->nodeType ) {
			foreach ( preg_split( '/\r\n|\r|\n/', $node->nodeValue ) as $i => $text ) {
				if ( $i ) $new_line();
				$last = count( $rows ) - 1;
				$rows[$last]['text'] .= $text;
				$escaped = esc_html( $text );
				$rows[$last]['html'] .= $bold && '' !== $text ? '<strong>' . $escaped . '</strong>' : $escaped;
			}
			return;
		}
		if ( XML_ELEMENT_NODE !== $node->nodeType ) return;
		$tag = strtolower( $node->nodeName );
		if ( in_array( $tag, array( 'script', 'style', 'iframe', 'object', 'svg' ), true ) ) return;
		if ( 'br' === $tag ) { $new_line(); return; }
		$block = in_array( $tag, array( 'p', 'div', 'li', 'h1', 'h2', 'h3' ), true );
		if ( $block && '' !== $rows[count( $rows ) - 1]['text'] ) $new_line();
		foreach ( $node->childNodes as $child ) $walk( $child, $bold || in_array( $tag, array( 'b', 'strong' ), true ) );
		if ( $block && '' !== $rows[count( $rows ) - 1]['text'] ) $new_line();
	};
	$body = $document->getElementsByTagName( 'body' )->item( 0 );
	if ( $body ) foreach ( $body->childNodes as $node ) $walk( $node );
	if ( count( $rows ) > 1 && '' === $rows[count( $rows ) - 1]['text'] ) array_pop( $rows );
	return $rows;
}

function co_sales_description_lines( $item ) {
	if ( 'rich' === ( $item['format'] ?? '' ) ) return co_sales_rich_lines( $item['text'] );
	return array_map( fn( $line ) => array( 'text' => $line, 'html' => esc_html( $line ) ), explode( "\n", $item['text'] ) );
}

function co_sales_item_editor( $item, $index ) {
	$initial = array_map( function ( $row ) use ( $item ) {
		return 'rich' !== ( $item['format'] ?? '' ) && co_sales_description_is_bold( $row['text'] ) ? '<strong>' . $row['html'] . '</strong>' : $row['html'];
	}, co_sales_description_lines( $item ) ); ?>
	<article class="sales-item-editor" data-sales-item>
		<div class="sales-item-head"><strong>รายละเอียดงาน / รายการ</strong><button type="button" class="sales-link danger" data-remove-item>ลบรายการ</button></div>
		<label>คำอธิบาย / Description<textarea name="item_text[]" rows="8"><?php echo esc_textarea( $item['text'] ); ?></textarea></label>
		<input type="hidden" name="item_format[]" value="<?php echo 'rich' === ( $item['format'] ?? '' ) ? 'rich' : 'plain'; ?>">
		<template data-description-initial><?php echo implode( '<br>', $initial ); ?></template>
		<label>มูลค่าก่อนภาษี / Pre-Tax Amount<input type="number" min="0" step=".01" name="item_amount[]" value="<?php echo esc_attr( $item['amount'] ); ?>" placeholder="เว้นว่างสำหรับรายละเอียดที่ไม่มีราคา"></label>
	</article>
<?php }

function co_sales_print_header( $id, $type, $client, $issuer ) { ?>
	<header class="sales-doc-header">
		<h1><?php echo esc_html( array( 'quotation' => 'ใบเสนอราคา', 'invoice' => 'ใบแจ้งหนี้', 'receipt' => 'ใบเสร็จรับเงิน' )[$type] ); ?><br><span><?php echo esc_html( co_sales_types()[$type] ); ?></span></h1>
		<div class="sales-doc-party customer"><div><b>ลูกค้า / Customer</b><strong><?php echo esc_html( $client['name'] ?? '' ); ?></strong><b>เลขที่ / No.</b><span><?php echo esc_html( co_sales_meta( $id, 'number' ) ); ?></span></div><div class="address"><b>ที่อยู่ / Address</b><span><?php echo nl2br( esc_html( $client['address'] ?? '' ) ); ?></span><b>วันที่ / Issue</b><span><?php echo esc_html( co_sales_date( co_sales_meta( $id, 'issue_date' ) ) ); ?></span></div><div><b>เลขผู้เสียภาษี / Tax ID</b><span><?php echo esc_html( $client['tax_id'] ?? '' ); ?></span></div><div><b>ผู้ติดต่อ / Attention</b><span><?php echo esc_html( $client['contact_name'] ?? '' ); ?></span><b>T :</b><span><?php echo esc_html( $client['phone'] ?? '' ); ?></span></div></div>
		<div class="sales-doc-party issuer"><div><b>ผู้ออก / issuer</b><strong><?php echo esc_html( $issuer['name'] ?? '' ); ?></strong><b>ผู้ติดต่อ / Attention</b><span><?php echo esc_html( $issuer['name'] ?? '' ); ?></span></div><div><b>ที่อยู่ / Address</b><span><?php echo nl2br( esc_html( $issuer['address'] ?? '' ) ); ?></span><b>T :</b><span><?php echo esc_html( $issuer['phone'] ?? '' ); ?></span></div><div><b>เลขผู้เสียภาษี / Tax ID</b><span><?php echo esc_html( $issuer['tax_id'] ?? '' ); ?></span><b>Email :</b><span><?php echo esc_html( $issuer['email'] ?? '' ); ?></span></div></div>
		<div class="sales-doc-columns"><span>รหัส<br>ID no.</span><span>คำอธิบาย<br>Description</span><span>มูลค่าก่อนภาษี<br>Pre-Tax Amount</span></div>
	</header>
<?php }

function co_sales_render_paper( $id ) {
	$type = co_sales_meta( $id, 'document_type', 'quotation' );
	$client = (array) co_sales_meta( $id, 'client_snapshot', array() );
	$issuer = (array) co_sales_meta( $id, 'issuer_snapshot', array() );
	// Older snapshots may predate the payment settings. Use only the matching issuer.
	if ( empty( trim( (string) ( $issuer['bank'] ?? '' ) ) ) ) {
		$issuer_id = co_sales_meta( $id, 'issuer_id', $issuer['id'] ?? '' );
		foreach ( co_sales_issuers() as $profile ) {
			if ( $issuer_id && (string) $profile['id'] === (string) $issuer_id ) {
				$issuer['bank'] = $profile['bank'] ?? '';
				break;
			}
		}
	}
	$signature = ! empty( $issuer['signature_id'] ) ? wp_get_attachment_image_url( $issuer['signature_id'], 'medium' ) : '';
	$items = co_sales_items( $id );
	$schedule = co_sales_billing_schedule( $id );
	?>
	<div data-sales-print-source hidden>
		<div data-print-header><?php co_sales_print_header( $id, $type, $client, $issuer ); ?></div>
		<div data-print-rows>
		<?php if ( 'quotation' !== $type ) : ?><div class="sales-doc-line strong"><span></span><span><?php echo esc_html( co_sales_meta( $id, 'title' ) ); ?></span><strong><?php echo esc_html( co_sales_money( co_sales_document_total( $id ) ) ); ?></strong></div><?php endif; ?>
		<?php foreach ( $items as $item ) :
			$lines = co_sales_description_lines( $item );
			foreach ( $lines as $index => $description ) :
				$line = $description['text'];
				$strong = 'rich' !== ( $item['format'] ?? '' ) && co_sales_description_is_bold( $line );
				$classes = $strong ? ' strong' : '';
				if ( preg_match( '/^(Project Concept|Project Execution|Customize Function|การดูแลครอบคลุมถึง|Multiple Languages|Retaining & Maintenance|สิ่งที่ลูกค้า|ระยะเวลา)/u', trim( $line ) ) ) $classes .= ' is-section';
				if ( str_starts_with( trim( $line ), '-' ) ) $classes .= ' is-bullet';
				if ( '' === trim( $line ) ) $classes .= ' is-blank'; ?>
				<div class="sales-doc-line<?php echo esc_attr( $classes ); ?>"><span></span><span><?php echo $line === '' ? '&nbsp;' : $description['html']; ?></span><strong><?php if ( ( str_starts_with( $lines[0]['text'], 'Project Concept :' ) ? 1 : 0 ) === $index && 'quotation' === $type && '' !== $item['amount'] ) echo esc_html( co_sales_money( $item['amount'] ) ); ?></strong></div>
			<?php endforeach;
		endforeach; if ( $schedule ) co_sales_print_terms( $schedule, co_sales_meta( $id, 'wht_rate', 3 ) ); ?>
		<?php if ( co_sales_meta( $id, 'notes' ) ) : ?><div class="sales-doc-line"><span></span><span><?php echo nl2br( esc_html( co_sales_meta( $id, 'notes' ) ) ); ?></span><span></span></div><?php endif; ?>
		</div>
		<div data-print-footer><div class="sales-doc-footer"><div class="sales-doc-payment"><?php if ( in_array( $type, array( 'quotation', 'invoice' ), true ) ) : ?><b>การชำระเงิน / Payment</b><div><?php echo nl2br( esc_html( $issuer['bank'] ?? '' ) ); ?></div><?php endif; ?></div><div class="sales-doc-totals"><div><span>ราคาสุทธิสินค้า (บาท) / Pre-WHT Amount</span><b><?php echo esc_html( co_sales_money( co_sales_document_total( $id ) ) ); ?></b></div><div><span>จำนวนหัก ณ ที่จ่าย <?php echo esc_html( co_sales_meta( $id, 'wht_rate', 3 ) ); ?>% (บาท) / WHT</span><b><?php echo esc_html( co_sales_money( co_sales_wht( $id ) ) ); ?></b></div><div><span>จำนวนเงินรวมทั้งสิ้น (บาท) / Grand Total</span><b><?php echo esc_html( co_sales_money( co_sales_grand_total( $id ) ) ); ?></b></div><div class="sales-doc-baht"><?php echo esc_html( co_sales_baht_text( co_sales_grand_total( $id ) ) ); ?></div></div></div><div class="sales-doc-signatures"><div><p>อนุมัติโดย / Approved by</p><div class="signature-space"><?php if ( $signature ) : ?><img src="<?php echo esc_url( $signature ); ?>" alt="ลายเซ็นผู้ออกเอกสาร"><?php endif; ?></div><p>วันที่ / Date <?php echo esc_html( co_sales_date( co_sales_meta( $id, 'issue_date' ) ) ); ?></p></div><div><p>ยอมรับ<?php echo 'receipt' === $type ? 'ใบเสร็จรับเงิน' : ( 'invoice' === $type ? 'ใบแจ้งหนี้' : 'ใบเสนอราคา' ); ?> / Accepted by</p><div class="signature-space dotted"></div><p>วันที่ / Date..........................</p></div></div></div>
	</div>
	<div class="sales-print-pages" data-sales-pages></div>
	<?php
}

function co_sales_print_terms( $schedule, $rate = 3 ) { ?>
	<div class="sales-doc-line strong is-section"><span></span><span>Terms of Payment :</span><span></span></div>
	<?php foreach ( $schedule as $row ) : ?><div class="sales-doc-line"><span></span><span><b><?php echo esc_html( (float) $row['percent'] ); ?>%</b> <?php echo esc_html( $row['description'] ); ?></span><span></span></div><?php endforeach; ?>
	<div class="sales-doc-line"><span></span><span>จำนวนหัก ณ ที่จ่าย <?php echo esc_html( $rate ); ?>% (บาท) / WHT</span><span></span></div>
<?php }
