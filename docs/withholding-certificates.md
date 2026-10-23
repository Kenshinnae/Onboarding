# Withholding certificate attachments

Sales Documents → ใบหัก ณ ที่จ่าย collects certificates attached to individual
Invoices. A saved Quotation also shows all its invoices and their files. Filters
use the recorded document issuer ID, not the WordPress user or company-name matching.
Invoice issuer mismatches with the source Quotation are flagged without rewriting history.

The main Quotation/Invoice list shows only a short certificate badge: missing
count, complete, no pending certificates, or no Invoice yet. File names,
invoice-by-invoice details and individual download buttons remain inside the
document, keeping the main rows compact. Check several rows and use
“ดาวน์โหลดที่เลือก (ZIP)”, or export all rows matching the issuer filter in one
click. The dedicated WHT list retains detailed file downloads and multi-selection.
Empty selections never fall back to exporting every document.

The main list filters by the issuer of the displayed document type (QO or Invoice).
The dedicated certificate list filters by actual Invoice issuer. For historical
QO/Invoice issuer mismatches, QO exports include its invoices but show a warning
and separate files by actual Invoice issuer folder; the CSV records both issuers.

Files are private and are not WordPress Media Library attachments or public report assets.
The default storage directory is `co-private-wht-<site-hash>` next to (not inside)
the server's document root. PHP must have permission to create and write this directory.
Uploads fail with an explicit error if private storage is unavailable; there is no public fallback.

For production, set an explicit stable absolute directory outside the web root in
`wp-config.php`, for example:

```php
define( 'CO_WHT_STORAGE_DIR', '/absolute/private/site-certificates' );
```

The parent must already exist. Never point this setting inside `public_html`, the
WordPress installation, or at a symbolic link. Back up this folder together with
the WordPress database: the files are not included in a normal uploads-folder-only backup.
Changing servers requires copying this private folder and updating the setting.

PHP `fileinfo` is required for content-type checks and `zip` / `ZipArchive` for
bulk exports. Limits: PDF/JPG/PNG/WebP, 20 MB per file, 10 files per upload,
20 active files per invoice; ZIP exports up to 200 files / 200 MB. The ZIP includes
an Excel-compatible UTF-8 `index.csv` recording QO and Invoice issuer snapshots,
invoice numbers, installments, expected WHT and actual payment dates. It does not
parse or verify amounts printed inside the uploaded certificates.

“นำออก” is recoverable, not physical deletion. Removed files are excluded from
downloads and counts, and “คืนไฟล์” restores them. Attaching files never records a
payment, generates a receipt, changes tax amounts, or sends anything externally.
After invoices exist, the saved issuer is locked along with the financial agreement.

Tests (PHP 8.2 with ZipArchive):

```sh
php tests/project-workflow.php
php tests/withholding.php
```
