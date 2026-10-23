# Shared screen UI

`assets/workspace-ui.css` is the common screen-only layer for Pipeline, Sales
Documents, MA, admin screens, customer reports, login and onboarding. Keep new
screens on these foundations instead of defining a new density per module.

- Spacing scale: 4 / 8 / 12 / 16 / 24 px. Standard admin panel padding: 16 px.
- Standard panel radius: 12 px; form controls and buttons: 8 px.
- Admin controls: 40 px desktop, 44 px on small screens. Keep input text at least
  15 px desktop / 16 px mobile; reduce whitespace, not reading size.
- Filter forms belong in the list header, aligned at the bottom of their inputs.
  Do not create a full-width standalone panel for a single dropdown.
- Follow-up queues show counts in compact native `details` summaries. Open the
  required category to inspect its existing links; no hidden data is deleted.
- Main document lists keep the short tax badge. File details remain inside the
  document; bulk selection is retained.
- Mobile document cards use a two-column field grid with a full-width title.
- Reuse the existing blue/navy palette, font family and emphasis hierarchy.

The shared layer is included after each module stylesheet and enqueued after the
admin skin / onboarding bundle. Customer Brief and Project reports share it too.
Do not apply these density rules to printable document canvases: Quotation,
Invoice and Receipt retain `sales-print.css`, Tahoma and their A4 geometry.
