<?php
// Isolated in-memory fixtures. No live documents are renumbered.
if ( PHP_SAPI !== 'cli' ) exit;
ob_start(); require __DIR__ . '/project-workflow.php'; ob_end_clean();
$manual = document_workflow( 0, 0, array( 'quotation_number_mode' => 'manual', 'quotation_number' => 'QO_EFL20260113' ) );
expect_workflow( co_sales_meta( $manual, 'number' ) === 'QO_EFL20260113' && get_the_title( $manual ) === 'QO_EFL20260113' && co_sales_meta( $manual, 'number_mode' ) === 'manual', 'create exact manual number, title and mode' );
$count = count( $posts );
rejected_workflow( fn() => document_workflow( 0, 0, array( 'quotation_number_mode' => 'manual', 'quotation_number' => 'qo_efl20260113' ) ), 'duplicate check is case insensitive' );
expect_workflow( count( $posts ) === $count, 'duplicate request creates no partial document' );
foreach ( array( '', '   ', '<script>', 'QO bad', str_repeat( 'X', 81 ) ) as $invalid ) rejected_workflow( fn() => document_workflow( 0, 0, array( 'quotation_number_mode' => 'manual', 'quotation_number' => $invalid ) ), 'invalid manual number rejected' );
document_workflow( $manual, 0 );
expect_workflow( co_sales_meta( $manual, 'number' ) === 'QO_EFL20260113', 'ordinary saves preserve manual number' );
document_workflow( $manual, 0, array( 'quotation_number_mode' => 'manual', 'quotation_number' => 'LEGACY/2026-001' ) );
expect_workflow( co_sales_meta( $manual, 'number' ) === 'LEGACY/2026-001', 'unlocked QO can adopt original legacy number' );
$auto_number = co_sales_next_number( 'quotation', 1, '2026-10-22' );
$auto = document_workflow( 0, 0 );
expect_workflow( co_sales_meta( $auto, 'number' ) === $auto_number && co_sales_meta( $auto, 'number_mode' ) === 'auto', 'default auto sequence unchanged' );
document_workflow( $auto, 0, array( 'quotation_number_mode' => 'manual', 'quotation_number' => 'QO-MANUAL-OLD' ) );
expect_workflow( co_sales_meta( $auto, 'number' ) === 'QO-MANUAL-OLD', 'existing uninvoiced auto QO can be corrected manually' );
rejected_workflow( fn() => document_workflow( $auto, 0, array( 'quotation_number_mode' => 'manual', 'quotation_number' => 'legacy/2026-001' ) ), 'renaming cannot duplicate another QO' );
rejected_workflow( fn() => document_workflow( $quote, $project, array( 'quotation_number_mode' => 'manual', 'quotation_number' => 'CHANGED-AFTER-INVOICE' ) ), 'existing invoice references protect QO identity' );
$old_invoice = co_sales_meta( $invoice, 'number' );
document_workflow( $invoice, $project, array( 'quotation_number_mode' => 'manual', 'quotation_number' => 'SHOULD-NOT-APPLY' ) );
expect_workflow( co_sales_meta( $invoice, 'number' ) === $old_invoice, 'manual number feature is QO only' );
add_option( 'co_billing_lock_sales_document_numbers', array( 'started' => time(), 'owner' => 'fixture' ) );
rejected_workflow( fn() => document_workflow( 0, 0, array( 'quotation_number_mode' => 'manual', 'quotation_number' => 'LOCKED-MANUAL' ) ), 'manual allocation uses shared number lock' );
rejected_workflow( fn() => document_workflow( 0, 0 ), 'auto allocation uses same number lock' );
delete_option( 'co_billing_lock_sales_document_numbers' );
$_GET = array( 'type' => 'quotation' ); ob_start(); co_sales_edit_page(); $new_html = ob_get_clean();
expect_workflow( str_contains( $new_html, 'data-quotation-number-mode' ) && str_contains( $new_html, 'รันเลขอัตโนมัติ (ปกติ)' ) && str_contains( $new_html, 'เลข QO ที่กำหนดเอง' ), 'new QO presents optional manual numbering, auto by default' );
ob_start(); co_sales_quotation_number_field( $quote, 'quotation' ); $locked_html = ob_get_clean();
expect_workflow( ! str_contains( $locked_html, 'data-quotation-number-mode' ) && str_contains( $locked_html, 'มี Invoice อ้างอิงแล้ว' ), 'locked QO shows original number without editable override' );
if ( '--fixture' === ( $argv[1] ?? '' ) ) { echo $new_html; exit; }
echo "PASS: optional manual QO numbers, preserved auto defaults, case-insensitive duplicate prevention, validation, shared locking, existing QO corrections and invoice-reference protection.\n";
