# Document signatures

Document information includes “ลายเซ็นบนเอกสาร” for Quotation, Invoice and
Receipt. Choose the configured signature image or leave the issuer's signing
space blank for hand signing a physical original. Manual mode omits the image
and its URL from the document HTML, including the shared customer document view;
it does not merely hide the image using print CSS.

The preference is stored independently on each document and can be changed on
existing invoiced/paid documents without changing financial fields or cash dates.
New Invoices inherit their QO's preference; new Receipts inherit their Invoice's
preference. Later changes do not rewrite previously created child documents.
Existing documents without a preference continue including the configured image.
Issuer signature settings and the uploaded signature image are never removed.

The admin Preview / PDF toolbar also offers “แนบลายเซ็น / เว้นไว้เซ็นมือ”.
Changing this selector refreshes the preview using a read-only GET parameter;
printing uses that selected version. It affects only this preview/PDF, not the
saved document, its children, finances, issuer settings or customer-shared view.
The initial preview uses the document's saved preference. Customer links do not
accept this admin preview override.

Test: `php tests/document-signatures.php` (isolated fixtures, no real data).
