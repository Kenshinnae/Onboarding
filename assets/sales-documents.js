document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('.sales-document-form');
  const money = n => Number(n).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  function calculate() {
    if (!form) return;
    const sum = [...form.querySelectorAll('[name="item_amount[]"]')].reduce((n, el) => n + Math.max(0, Number(el.value)), 0);
    const subtotal = form.querySelector('[name="subtotal"]');
    if (form.dataset.documentType === 'quotation') subtotal.value = sum.toFixed(2);
    const value = Math.max(0, Number(subtotal.value));
    const wht = Math.min(100, Math.max(0, Number(form.querySelector('[name="wht_rate"]').value))) * value / 100;
    form.querySelector('[data-sales-wht]').textContent = money(wht);
    form.querySelector('[data-sales-total]').textContent = money(value - wht);
  }
  form?.addEventListener('input', calculate);
  form?.querySelector('[data-type-select]')?.addEventListener('change', event => {
    form.dataset.documentType = event.target.value;
    form.querySelector('[name="subtotal"]').readOnly = event.target.value === 'quotation';
    calculate();
  });
  calculate();

  const schedule = form?.querySelector('[data-sales-schedule]');
  function updateSchedule() {
    if (!schedule) return;
    const preview = schedule.querySelector('[data-schedule-preview]');
    preview.replaceChildren();
    const heading = document.createElement('p'); heading.textContent = 'Terms of Payment :';
    preview.append(heading);
    let total = 0;
    schedule.querySelectorAll('[data-schedule-row]').forEach((row, index) => {
      row.querySelector('[data-schedule-label]').textContent = 'งวดที่ ' + (index + 1);
      const input = row.querySelector('[name="schedule_percent[]"]');
      const percent = Number(input.value || 0);
      total += percent;
      const descriptionInput = row.querySelector('textarea');
      descriptionInput.required = percent > 0;
      const description = descriptionInput.value.trim();
      const line = document.createElement('p');
      const value = document.createElement('b'); value.textContent = percent + '% ';
      line.append(value, document.createTextNode(description || '— กรุณาระบุเงื่อนไขการชำระ'));
      preview.append(line);
    });
    const valid = Math.abs(total - 100) < 0.001;
    const totalLabel = schedule.querySelector('[data-schedule-total]');
    totalLabel.textContent = 'รวม ' + Number(total.toFixed(2)) + '% ' + (valid ? '— ครบ 100%' : '— เปอร์เซ็นต์ทุกงวดควรรวมเป็น 100%');
    totalLabel.classList.toggle('is-invalid', !valid);
  }
  schedule?.addEventListener('input', updateSchedule);
  schedule?.addEventListener('click', event => {
    const list = schedule.querySelector('[data-schedule-rows]');
    if (event.target.closest('[data-add-schedule]')) {
      const row = list.firstElementChild.cloneNode(true);
      row.querySelectorAll('input,textarea').forEach(el => el.value = '');
      list.append(row); updateSchedule(); row.querySelector('input').focus();
    }
    const remove = event.target.closest('[data-remove-schedule]');
    if (remove) {
      if (list.children.length > 1) remove.closest('[data-schedule-row]').remove();
      else list.querySelectorAll('input,textarea').forEach(el => el.value = '');
      updateSchedule();
    }
  });
  updateSchedule();
  document.addEventListener('click', event => {
    if (event.target.closest('[data-use-original-template]') && confirm('แทนรายละเอียดงานปัจจุบันด้วยแม่แบบต้นฉบับครบทุกส่วน?')) {
      form.querySelector('[data-sales-items]').replaceChildren(form.querySelector('[data-sales-default-items]').content.cloneNode(true));
      calculate();
    }
    const add = event.target.closest('[data-add-item]');
    if (add) {
      const list = form.querySelector('[data-sales-items]');
      const row = list.firstElementChild.cloneNode(true);
      row.querySelectorAll('input,textarea').forEach(el => el.value = el.name === 'item_page[]' ? '1' : '');
      list.append(row);
      row.querySelector('textarea').focus();
    }
    const remove = event.target.closest('[data-remove-item]');
    if (remove && form.querySelectorAll('[data-sales-item]').length > 1) { remove.closest('[data-sales-item]').remove(); calculate(); }
  });

  // Build actual A4 sheets, repeating the document header on every page.
  const source = document.querySelector('[data-sales-print-source]');
  const output = document.querySelector('[data-sales-pages]');
  if (!source || !output) return;
  const build = () => {
    output.replaceChildren();
    let sheet, body, requested = 1;
    const nextPage = () => {
      sheet = document.createElement('section'); sheet.className = 'sales-document-sheet';
      sheet.append(source.querySelector('[data-print-header]').firstElementChild.cloneNode(true));
      body = document.createElement('div'); body.className = 'sales-doc-body'; sheet.append(body);
      output.append(sheet);
    };
    nextPage();
    for (const original of source.querySelector('[data-print-rows]').children) {
      const page = Number(original.dataset.page || 1);
      if (page > requested) { if (body.children.length && output.children.length < page) nextPage(); requested = page; }
      const row = original.cloneNode(true); body.append(row);
      if ((sheet.scrollHeight > sheet.clientHeight + 1 || body.scrollHeight > body.clientHeight + 1) && body.children.length > 1) {
        row.remove(); nextPage(); body.append(row);
      }
    }
    const footer = source.querySelector('[data-print-footer]').firstElementChild.cloneNode(true);
    const signatures = source.querySelector('.sales-doc-signatures').cloneNode(true);
    const end = document.createElement('div'); end.className = 'sales-doc-ending'; end.append(footer, signatures); sheet.append(end);
    // Move final body lines to a continuation sheet when the signature area needs room.
    if (sheet.scrollHeight > sheet.clientHeight + 1 || body.scrollHeight > body.clientHeight + 1) {
      end.remove(); const moved = [];
      const reserved = 190;
      let height = 0;
      while (body.children.length && height < reserved) {
        const row = body.lastElementChild; height += row.getBoundingClientRect().height; moved.unshift(row); row.remove();
      }
      nextPage(); body.append(...moved); sheet.append(end);
    }
    document.documentElement.dataset.salesReady = 'true';
  };
  document.fonts.ready.then(build);
  window.addEventListener('beforeprint', build);
});
