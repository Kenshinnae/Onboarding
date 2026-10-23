# Quotation deletion

The Quotation list and editor have a “ลบ QO” action with confirmation. It moves
unused QOs into a dedicated recoverable Trash view; it never deletes a Project,
Invoice, payment, Receipt, or certificate. Only users allowed to edit documents
and delete that specific post can perform the nonce-protected POST action.

QOs selected as a Project's main agreement cannot be trashed: select another
agreement first, before any Invoice has been issued. A QO with any derived
document (including a child in Trash), payment reference, or certificate history
also cannot be deleted. The editor explains the reason; the list disables the
delete action for protected QOs.

Trash retains document metadata and its reserved document number. Restoring
returns the same document ID and number without selecting a main agreement,
changing Project totals, creating payments, or sending email. Duplicate clicks
are safe. Stale save/preview/derive requests cannot revive trashed documents.
Project-agreement and document write locks serialize changes with billing.

The `pre_delete_post` guard prevents WordPress's scheduled Trash cleanup from
permanently deleting QOs. There is intentionally no permanent-delete or empty
Trash action. With `EMPTY_TRASH_DAYS=0`, WordPress cannot safely trash a QO and
the action fails explicitly instead of deleting it permanently.

Run `php tests/quotation-lifecycle.php` alongside the workflow and withholding
test suites. Tests use in-memory fixtures, never real customer documents.
