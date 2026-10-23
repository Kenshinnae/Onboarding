<?php
defined( 'ABSPATH' ) || exit;

function co_project_start_ma( $project, $input ) {
	if ( 'client_project' !== get_post_type( $project ) ) throw new RuntimeException( 'Project ไม่ถูกต้อง' );
	$start_ma = function () use ( $project, $input ) {
		$linked = absint( co_crm_meta( $project, 'ma_project_id' ) );
		if ( $linked && 'ma_project' === get_post_type( $linked ) && 'publish' === get_post_status( $linked ) ) return absint( co_ma_meta( $linked, 'client_id' ) );
		$client = absint( $input['ma_client_id'] ?? 0 ); $workstream = absint( $input['ma_project_id'] ?? 0 );
		if ( $client && ( 'ma_client' !== get_post_type( $client ) || 'publish' !== get_post_status( $client ) ) ) throw new RuntimeException( 'แพ็กเกจ MA ไม่ถูกต้อง' );
		if ( $workstream ) {
			if ( ! $client || 'ma_project' !== get_post_type( $workstream ) || 'publish' !== get_post_status( $workstream ) || $client !== absint( co_ma_meta( $workstream, 'client_id' ) ) ) throw new RuntimeException( 'โปรเจกต์ MA นี้ไม่ได้อยู่ในแพ็กเกจที่เลือก' );
			$other = absint( co_ma_meta( $workstream, 'crm_project_id' ) );
			if ( $other && $other !== $project ) throw new RuntimeException( 'โปรเจกต์ MA นี้เชื่อมกับ Project อื่นอยู่แล้ว' );
		}
		if ( ! $client ) {
			$start = sanitize_text_field( $input['start_date'] ?? '' ); $duration = sanitize_key( $input['duration'] ?? '3m' );
			$months = array( '3m' => 3, '6m' => 6, '1y' => 12 ); $hours = (float) ( $input['hours'] ?? 0 );
			if ( ! co_sales_valid_date( $start ) || ! isset( $months[$duration] ) || $hours <= 0 ) throw new RuntimeException( 'ระบุวันที่เริ่ม ระยะเวลา และชั่วโมงของ MA ใหม่ให้ครบ' );
			$name = sanitize_text_field( wp_unslash( $input['ma_company_name'] ?? '' ) ) ?: co_crm_meta( $project, 'company', get_the_title( $project ) );
			$client = wp_insert_post( array( 'post_type' => 'ma_client', 'post_status' => 'publish', 'post_title' => $name ), true );
			if ( is_wp_error( $client ) || ! $client ) throw new RuntimeException( 'สร้าง MA ไม่สำเร็จ' );
			$values = array( 'purchased_hours' => $hours, 'package_type' => 'monthly_retainer' === ( $input['package_type'] ?? '' ) ? 'monthly_retainer' : 'hour_bank', 'contact_name' => co_crm_meta( $project, 'contact_name' ), 'contact_email' => co_crm_meta( $project, 'contact_email' ), 'start_date' => $start, 'duration' => $duration, 'expiry_date' => gmdate( 'Y-m-d', strtotime( '+' . $months[$duration] . ' months', strtotime( $start ) ) ), 'status' => 'active' );
			foreach ( $values as $key => $value ) update_post_meta( $client, '_ma_' . $key, $value );
			co_ma_share_token( $client );
			if ( '1' === co_ma_setting( 'notify_package', '1' ) ) co_ma_notify( 'New MA package — ' . $name, 'Project: ' . get_the_title( $project ) . "\nCompany: " . $name . "\nExpiry: " . co_ma_date( $values['expiry_date'] ) . "\n" . admin_url( 'admin.php?page=ma-client-detail&client=' . $client ) );
		}
		if ( ! $workstream ) {
			$workstream = wp_insert_post( array( 'post_type' => 'ma_project', 'post_status' => 'publish', 'post_title' => get_the_title( $project ) ), true );
			if ( is_wp_error( $workstream ) || ! $workstream ) throw new RuntimeException( 'สร้างโปรเจกต์ MA ไม่สำเร็จ' );
			update_post_meta( $workstream, '_ma_client_id', $client ); update_post_meta( $workstream, '_ma_status', 'active' ); update_post_meta( $workstream, '_ma_description', co_crm_project_brief( $project ) );
		}
		update_post_meta( $workstream, '_ma_crm_project_id', $project ); update_post_meta( $project, '_crm_ma_project_id', $workstream ); update_post_meta( $project, '_crm_ma_client_id', $client ); update_post_meta( $project, '_crm_stage', 'launch_ma' );
		return $client;
	};
	$stream = absint( $input['ma_project_id'] ?? 0 );
	return co_project_locked( 'project_ma_' . $project, fn() => $stream ? co_project_locked( 'ma_stream_' . $stream, $start_ma ) : $start_ma() );
}

function co_project_render_ma( $project, $return_url = '' ) {
	$linked = absint( co_crm_meta( $project, 'ma_project_id' ) ); $client = absint( co_crm_meta( $project, 'ma_client_id' ) ); ?>
	<section class="crm-drawer-section crm-drawer-ma crm-panel"><h3>Maintenance period</h3><p>MA ที่รวมมากับงานไม่สร้างยอดขายเพิ่ม บริษัท MA เลือกต่างจากผู้ออกบิลได้</p>
	<?php if ( $linked && 'publish' === get_post_status( $linked ) ) : ?><a class="crm-btn" href="<?php echo esc_url( admin_url( 'admin.php?page=ma-client-detail&client=' . $client ) ); ?>">เปิด MA ที่เชื่อมไว้ →</a>
	<?php else : ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'co_crm_save' ); ?><input type="hidden" name="action" value="co_crm_save"><input type="hidden" name="entity" value="start_ma"><input type="hidden" name="project_id" value="<?php echo $project; ?>"><input type="hidden" name="return_url" value="<?php echo esc_url( $return_url ); ?>">
	<label>แพ็กเกจ / บริษัท MA<select name="ma_client_id" data-project-ma-client><option value="0">สร้างแพ็กเกจ MA ใหม่</option><?php foreach ( co_ma_clients() as $item ) : ?><option <?php selected( $client, $item->ID ); ?> value="<?php echo $item->ID; ?>"><?php echo esc_html( $item->post_title . ' · ' . co_ma_date( co_ma_meta( $item->ID, 'expiry_date' ) ) ); ?></option><?php endforeach; ?></select></label>
	<label data-project-ma-existing hidden>โปรเจกต์ MA เดิม<select name="ma_project_id" data-project-ma-stream><option value="0">สร้างโปรเจกต์ใหม่ในแพ็กเกจที่เลือก</option><?php foreach ( co_ma_projects() as $item ) : if ( co_ma_meta( $item->ID, 'crm_project_id' ) ) continue; ?><option data-client="<?php echo absint( co_ma_meta( $item->ID, 'client_id' ) ); ?>" value="<?php echo $item->ID; ?>"><?php echo esc_html( $item->post_title ); ?></option><?php endforeach; ?></select><small>เลือกของเดิมเพื่อเชื่อมโดยไม่สร้างโปรเจกต์ซ้ำ ชั่วโมงและประวัติเดิมจะไม่เปลี่ยน</small></label>
	<div data-project-ma-new><label>ชื่อบริษัท MA<input name="ma_company_name" value="<?php echo esc_attr( co_crm_meta( $project, 'company' ) ); ?>"></label><div class="crm-grid"><label>รูปแบบ<select name="package_type"><option value="hour_bank">Hour bank</option><option value="monthly_retainer">Monthly retainer</option></select></label><label>ชั่วโมง<input type="number" min=".25" step=".25" name="hours" required></label><label>วันที่เริ่ม MA<input type="date" name="start_date" required value="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>"></label><label>ระยะเวลา<select name="duration"><option value="3m">3 เดือน</option><option value="6m">6 เดือน</option><option value="1y">1 ปี</option></select></label></div></div>
	<p data-project-ma-existing hidden>ใช้วันที่เริ่ม/หมดอายุและยอดชั่วโมงของแพ็กเกจเดิม ไม่รีเซ็ตข้อมูล</p><div class="crm-drawer-actions"><button class="crm-btn" onclick="return confirm('ยืนยันเริ่ม/เชื่อม MA ตามข้อมูลที่เลือก? สถานะ Project จะเปลี่ยนเป็น MA โดยไม่เพิ่มยอดขาย')">ยืนยันเริ่ม / เชื่อม MA</button></div></form>
	<?php endif; ?></section>
<?php }
