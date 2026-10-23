# Quotation numbers

New Quotations use automatic numbers by default. In document information, choose
“กรอกเลขเอง — เอกสารที่ออกไว้แล้ว” to import an existing external QO number.
The supplied number is saved exactly after trimming surrounding whitespace.
Allowed: English letters, digits, `.`, `_`, `/`, `-`, up to 80 characters.
Active document numbers cannot be duplicated, including case-only differences;
legacy Trash does not reserve numbers. Auto and manual allocation share a lock.

Existing uninvoiced QOs may be corrected manually. Normal saves preserve their
current number; choosing the default on an existing QO does not renumber it.
Once an Invoice references a QO, the QO number is locked to protect the reference.
Invoice and Receipt numbering and the Task/financial locks are unchanged.

Tests: `php tests/manual-quotation-number.php` and `php tests/document-numbering.php`.
