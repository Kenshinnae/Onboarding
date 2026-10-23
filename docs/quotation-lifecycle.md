# Quotation deletion

The Quotation list and editor have a “ลบ QO” action with confirmation. It removes
the entire QO set: the Quotation, all descendant Invoices and Receipts, and their
linked cash entries. It works for main agreements and invoiced/paid QOs too.
Only users allowed to delete every member and edit the affected Project can
perform the nonce-protected POST action.

Relationships follow document and payment IDs, never company names. Other QOs,
unrelated payments, Project work/status/assets/onboarding and MA remain intact.
If the QO was the main agreement, its link, synced sale value, net value and
quotation number are cleared. Linked cash entries leave active ledgers, so
Pipeline and annual reports no longer count the deleted sale or cash.
Certificates remain privately stored for recovery but cannot be downloaded
against deleted Invoices and disappear from the active certificate lists.

The entire set is recoverable from Quotation Trash. A batch manifest records
original post statuses and main-agreement fields; restoring returns the same
IDs, numbers, cash amounts and actual receipt-of-money dates, without creating
new payments. Previously trashed children are not resurrected. An old agreement
is restored only if the Project has not acquired a replacement agreement or
manually entered financial values. Duplicate clicks are safe. Stale edit,
receive-payment, preview and derive actions cannot revive deleted documents.
Project, document and payment locks serialize billing writes; a compensating
journal reverses changes if a child status or metadata write fails.

The `pre_delete_post` guard prevents WordPress's scheduled Trash cleanup from
purging any member of a deleted set. There is no permanent-delete or empty
Trash action. Explicit soft deletion also works with `EMPTY_TRASH_DAYS=0`.

Run `php tests/quotation-lifecycle.php` alongside the workflow and withholding
test suites. Tests use in-memory fixtures, never real customer documents.
