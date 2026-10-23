# Permanent quotation deletion

“ลบ QO” permanently deletes the Quotation, its descendant Invoices/Receipts,
and linked payment records. There is no Trash, restore or recovery operation.
Confirmation explicitly identifies the whole set and warns it cannot be undone.

Relationships follow IDs, not names. Other QOs, unrelated payments, Project
work/status/assets/onboarding and MA are unchanged. Main-agreement links and
synced financial fields are cleared; deleted sales and cash leave reports.

All member permissions and private certificate paths are validated first.
Project/document/payment locks serialize concurrent billing. InnoDB transactions
roll back database changes if a record deletion fails. Private certificate files
unique to the deleted set are unlinked after the database succeeds; shared files
are preserved. File cleanup failures stop with an explicit administrator error.
Nothing automatically purges previously trashed records on deployment.

Run `php tests/quotation-lifecycle.php`, `php tests/project-workflow.php` and
`php tests/withholding.php`. Tests use isolated in-memory fixtures.
