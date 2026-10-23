<?php
// In-memory fixtures only; no real customer documents are changed or renumbered.
if ( PHP_SAPI !== 'cli' ) exit;
ob_start(); require __DIR__ . '/project-workflow.php'; ob_end_clean();
$number_client = wp_insert_post( array( 'post_type' => 'sales_client', 'post_status' => 'publish', 'post_title' => 'Numbering fixture' ) );
update_post_meta( $number_client, '_sales_short_name', 'NUMTEST' );
$cases = array(); $date = '2027-01-02';
foreach ( array( 'quotation' => 'QO', 'invoice' => 'IV1', 'receipt' => 'RC1' ) as $type => $prefix ) {
	$base = $prefix . '_NUMTEST20270102';
	foreach ( array( $base => 'trash', $base . '-02' => 'trash', $base . '-03' => 'publish' ) as $number => $status ) {
		$doc = wp_insert_post( array( 'post_type' => 'sales_document', 'post_status' => $status, 'post_title' => $number ) );
		update_post_meta( $doc, '_sales_number', $number ); update_post_meta( $doc, '_sales_document_type', $type );
	}
	expect_workflow( co_sales_next_number( $type, $number_client, $date, 1 ) === $base, $type . ' ignores old deleted base and -02 despite active -03' );
	list( $first, $number ) = co_sales_create_numbered_document( $type, $number_client, $date, 1 );
	expect_workflow( $number === $base, $type . ' starts without suffix when base is free' );
	list( $next, $number ) = co_sales_create_numbered_document( $type, $number_client, $date, 1 );
	expect_workflow( $number === $base . '-02', $type . ' reuses lowest available suffix, ignoring Trash' );
	expect_workflow( co_sales_next_number( $type, $number_client, $date, 1 ) === $base . '-04', $type . ' never collides with live base/-02/-03' );
	$cases[] = array( 'id' => $first, 'type' => $type, 'base' => $base );
}
foreach ( $cases as $case ) {
	wp_delete_post( $case['id'], true );
	expect_workflow( co_sales_next_number( $case['type'], $number_client, $date, 1 ) === $case['base'], $case['type'] . ' permanent deletion frees its number' );
}
echo "PASS: QO/INV/Receipt numbering ignores legacy Trash, reuses available numbers after deletion, and prevents live-document collisions.\n";
