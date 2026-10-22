document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('.sales-document-form');
  function initializeDescriptions(container = form) {
    container?.querySelectorAll('[data-sales-item]').forEach(item => {
      if (item.querySelector('[data-description-editor]')) return;
      const textarea = item.querySelector('[name="item_text[]"]');
      const initial = item.querySelector('[data-description-initial]');
      const wrapper = document.createElement('div'); wrapper.dataset.descriptionUi = '';
      wrapper.className = 'sales-description-ui';
      wrapper.innerHTML = '<div class="sales-description-toolbar" role="toolbar" aria-label="จัดรูปแบบคำอธิบาย"><button type="button" data-description-format="bold" aria-pressed="false"><b>B</b> ตัวหนา</button><button type="button" data-description-format="normal">ตัวปกติ</button></div><div class="sales-description-editor" data-description-editor contenteditable="true" role="textbox" aria-label="คำอธิบาย / Description" aria-multiline="true"></div>';
      const editor = wrapper.querySelector('[data-description-editor]');
      editor.innerHTML = initial.innerHTML;
      textarea.after(wrapper); textarea.hidden = true;
      item.querySelector('[name="item_format[]"]').value = 'rich';
      const sync = () => { textarea.value = editor.innerHTML; };
      const updateButton = () => wrapper.querySelector('[data-description-format="bold"]').setAttribute('aria-pressed', String(document.queryCommandState('bold')));
      editor.addEventListener('input', () => { sync(); updateButton(); });
      editor.addEventListener('keyup', updateButton);
      editor.addEventListener('mouseup', updateButton);
      editor.addEventListener('paste', event => {
        event.preventDefault();
        document.execCommand('insertText', false, event.clipboardData.getData('text/plain'));
        sync();
      });
      wrapper.querySelector('.sales-description-toolbar').addEventListener('mousedown', event => event.preventDefault());
      wrapper.addEventListener('click', event => {
        const button = event.target.closest('[data-description-format]');
        if (!button) return;
        const selection = window.getSelection();
        if (!selection.rangeCount || !editor.contains(selection.getRangeAt(0).commonAncestorContainer)) editor.focus();
        if (button.dataset.descriptionFormat === 'bold') document.execCommand('bold');
        else {
          document.execCommand('removeFormat');
          if (window.getSelection().isCollapsed && document.queryCommandState('bold')) document.execCommand('bold');
        }
        sync(); updateButton();
      });
      sync();
    });
  }
  initializeDescriptions();
  form?.addEventListener('submit', () => {
    form.querySelectorAll('[data-sales-items] [data-sales-item]').forEach(item => {
      const editor = item.querySelector('[data-description-editor]');
      if (editor) item.querySelector('[name="item_text[]"]').value = editor.innerHTML;
    });
  });
  const money = n => Number(n).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  const rounded = n => Math.round((n + Number.EPSILON) * 100) / 100;
  const schedule = form?.querySelector('[data-sales-schedule]');
  const financialLocked = form?.dataset.financialLocked === '1';
  if (financialLocked) {
    form.querySelectorAll('[name="item_amount[]"],[name="discount"],[name="wht_rate"],[name="installment_no"],[name="schedule_percent[]"],[name="schedule_description[]"]').forEach(input => input.readOnly = true);
    form.querySelectorAll('[name="project_id"],[name="client_id"]').forEach(select => {
      const hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.name = select.name; hidden.value = select.value; select.after(hidden); select.disabled = true;
    });
    form.querySelectorAll('[data-add-item],[data-remove-item],[data-use-original-template]').forEach(button => button.hidden = true);
  }
  function calculate() {
    if (!form) return;
    const sum = [...form.querySelectorAll('[name="item_amount[]"]')].reduce((n, el) => n + Math.round(Math.max(0, Number(el.value) || 0) * 100), 0) / 100;
    const discountInput = form.querySelector('[name="discount"]');
    const enteredDiscount = Number(discountInput.value) || 0;
    discountInput.max = sum.toFixed(2);
    discountInput.setCustomValidity(enteredDiscount < 0 || enteredDiscount > sum ? 'ส่วนลดต้องเป็นจำนวนเงินตั้งแต่ 0 และไม่เกินมูลค่ารวมรายการ' : '');
    const discount = rounded(Math.min(sum, Math.max(0, enteredDiscount)));
    const netBase = rounded(sum - discount);
    const quotation = form.dataset.documentType === 'quotation';
    const installment = form.querySelector('[name="installment_no"]');
    form.querySelector('[data-document-installment]').hidden = quotation;
    installment.type = quotation ? 'hidden' : 'number';
    installment.value = quotation ? '0' : String(Math.max(1, Number(installment.value) || 1));
    const percent = quotation ? 100 : Math.min(100, Math.max(0, Number(schedule?.querySelector('[name="schedule_percent[]"]').value) || 0));
    const subtotal = form.querySelector('[name="subtotal"]');
    const value = rounded(rounded(netBase * percent / 100) + (financialLocked ? Number(form.dataset.roundingAdjustment || 0) : 0));
    const priceBeforeDiscount = financialLocked && !quotation ? Number(form.dataset.priceTotal) : rounded(sum * percent / 100);
    subtotal.value = value.toFixed(2);
    form.querySelector('[data-sales-base-total]').value = money(sum);
    form.querySelector('[data-sales-net-base]').value = money(netBase);
    form.querySelector('[data-billing-base-panel]').hidden = quotation;
    form.querySelector('[data-billing-formula]').textContent = 'ยอดหลังส่วนลด ฿' + money(netBase) + ' × ' + percent + '% = ฿' + money(value) + ' (ก่อนหัก ณ ที่จ่าย)' + (discount ? ' · ส่วนลดเฉลี่ยงวดนี้ ฿' + money(Math.max(0, rounded(priceBeforeDiscount - value))) : '') + (financialLocked && Number(form.dataset.roundingAdjustment || 0) ? ' · ปรับเศษสตางค์งวดสุดท้ายให้รวมตรง QO' : '');
    form.querySelector('[data-subtotal-label]').textContent = quotation ? 'ราคาสุทธิหลังส่วนลด (คำนวณอัตโนมัติ)' : 'มูลค่างวดนี้หลังส่วนลด (คำนวณอัตโนมัติ)';
    const wht = financialLocked && !quotation ? Number(form.dataset.whtAmount) : rounded(Math.min(100, Math.max(0, Number(form.querySelector('[name="wht_rate"]').value) || 0)) * value / 100);
    form.querySelector('[data-sales-wht]').textContent = money(wht);
    form.querySelector('[data-sales-total]').textContent = money(rounded(value - wht));
    updateSchedule(netBase);
    // Keep an automatically generated installment title in sync, preserving custom titles.
    const title = form.querySelector('[name="document_title"]');
    if (!quotation && /^งวดที่ \d+\s*:/.test(title.value)) {
      title.value = 'งวดที่ ' + Math.max(1, Number(form.querySelector('[name="installment_no"]').value) || 1) + ' : ' + percent + '% ' + schedule.querySelector('textarea').value.trim();
    }
  }
  form?.addEventListener('input', calculate);
  form?.querySelector('[data-type-select]')?.addEventListener('change', event => {
    form.dataset.documentType = event.target.value;
    calculate();
  });
  calculate();

  function updateSchedule(base) {
    if (!schedule) return;
    const quotation = form.dataset.documentType === 'quotation';
    schedule.querySelector('[data-schedule-heading]').textContent = quotation ? 'แผนงวดชำระเงิน / Terms of Payment' : 'งวดที่เรียกเก็บในใบนี้';
    schedule.querySelector('[data-add-schedule]').hidden = !quotation || financialLocked;
    const preview = schedule.querySelector('[data-schedule-preview]');
    preview.replaceChildren();
    const heading = document.createElement('p'); heading.textContent = 'Terms of Payment :';
    preview.append(heading);
    let total = 0;
    const planned = [...schedule.querySelectorAll('[data-schedule-row]')].filter(row => Number(row.querySelector('[name="schedule_percent[]"]').value) > 0);
    const balanced = quotation && Math.abs(planned.reduce((sum, row) => sum + Number(row.querySelector('[name="schedule_percent[]"]').value), 0) - 100) < 0.001;
    let allocated = 0;
    schedule.querySelectorAll('[data-schedule-row]').forEach((row, index) => {
      const active = quotation || index === 0;
      row.hidden = !active;
      row.querySelectorAll('input,textarea').forEach(input => input.disabled = !active);
      row.querySelector('[data-remove-schedule]').hidden = !quotation || financialLocked;
      if (!active) return;
      row.querySelector('[data-schedule-label]').textContent = 'งวดที่ ' + (quotation ? index + 1 : Math.max(1, Number(form.querySelector('[name="installment_no"]').value) || 1));
      const input = row.querySelector('[name="schedule_percent[]"]');
      const percent = Number(input.value || 0);
      total += percent;
      let amount = rounded(base * Math.min(100, Math.max(0, percent)) / 100);
      if (balanced) amount = Math.max(0, rounded(row === planned.at(-1) ? base - allocated : Math.min(base - allocated, amount)));
      if (!quotation && financialLocked) amount = Number(form.querySelector('[name="subtotal"]').value);
      allocated = rounded(allocated + amount);
      row.querySelector('[data-schedule-amount]').textContent = '฿' + money(amount);
      const descriptionInput = row.querySelector('textarea');
      descriptionInput.required = percent > 0;
      const description = descriptionInput.value.trim();
      const line = document.createElement('p');
      const value = document.createElement('b'); value.textContent = percent + '% ';
      line.append(value, document.createTextNode(description || '— กรุณาระบุเงื่อนไขการชำระ'));
      preview.append(line);
    });
    const valid = quotation ? Math.abs(total - 100) < 0.001 : total > 0 && total <= 100;
    const totalLabel = schedule.querySelector('[data-schedule-total]');
    totalLabel.textContent = quotation
      ? 'รวม ' + Number(total.toFixed(2)) + '% ' + (valid ? '— ครบ 100%' : '— เปอร์เซ็นต์ทุกงวดควรรวมเป็น 100%')
      : 'เรียกเก็บงวดนี้ ' + Number(total.toFixed(2)) + '% ' + (valid ? 'ของยอดหลังส่วนลด' : '— ระบุเปอร์เซ็นต์มากกว่า 0 และไม่เกิน 100');
    totalLabel.classList.toggle('is-invalid', !valid);
  }
  schedule?.addEventListener('click', event => {
    const list = schedule.querySelector('[data-schedule-rows]');
    if (event.target.closest('[data-add-schedule]')) {
      const row = list.firstElementChild.cloneNode(true);
      row.querySelectorAll('input,textarea').forEach(el => el.value = '');
      list.append(row); calculate(); row.querySelector('input').focus();
    }
    const remove = event.target.closest('[data-remove-schedule]');
    if (remove) {
      if (list.children.length > 1) remove.closest('[data-schedule-row]').remove();
      else list.querySelectorAll('input,textarea').forEach(el => el.value = '');
      calculate();
    }
  });
  document.addEventListener('click', event => {
    if (event.target.closest('[data-use-original-template]') && confirm('แทนรายละเอียดงานปัจจุบันด้วยแม่แบบต้นฉบับครบทุกส่วน?')) {
      form.querySelector('[data-sales-items]').replaceChildren(form.querySelector('[data-sales-default-items]').content.cloneNode(true));
      initializeDescriptions();
      calculate();
    }
    const add = event.target.closest('[data-add-item]');
    if (add) {
      const list = form.querySelector('[data-sales-items]');
      const row = list.firstElementChild.cloneNode(true);
      row.querySelectorAll('[data-description-ui]').forEach(el => el.remove());
      row.querySelector('[data-description-initial]').innerHTML = '';
      row.querySelectorAll('input,textarea').forEach(el => el.value = '');
      list.append(row);
      initializeDescriptions();
      row.querySelector('[data-description-editor]').focus();
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
    let sheet, body;
    const nextPage = () => {
      sheet = document.createElement('section'); sheet.className = 'sales-document-sheet';
      sheet.append(source.querySelector('[data-print-header]').firstElementChild.cloneNode(true));
      body = document.createElement('div'); body.className = 'sales-doc-body'; sheet.append(body);
      output.append(sheet);
    };
    nextPage();
    for (const original of source.querySelector('[data-print-rows]').children) {
      const row = original.cloneNode(true); body.append(row);
      if ((sheet.scrollHeight > sheet.clientHeight + 1 || body.scrollHeight > body.clientHeight + 1) && body.children.length > 1) {
        row.remove();
        // Keep a section heading with its first detail line on continuation pages.
        const carry = [];
        while (body.lastElementChild && (body.lastElementChild.matches('.is-section,.is-blank') || (row.matches('.is-payment-terms') && body.lastElementChild.matches('.is-payment-terms')))) {
          const previous = body.lastElementChild;
          carry.unshift(previous); previous.remove();
        }
        nextPage(); body.append(...carry, row);
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
      while (body.children.length && (height < reserved || body.lastElementChild.matches('.is-section,.is-blank') || (moved[0]?.matches('.is-payment-terms') && body.lastElementChild.matches('.is-payment-terms')))) {
        const row = body.lastElementChild; height += row.getBoundingClientRect().height; moved.unshift(row); row.remove();
      }
      nextPage(); body.append(...moved); sheet.append(end);
    }
    document.documentElement.dataset.salesReady = 'true';
  };
  document.fonts.ready.then(build);
  window.addEventListener('beforeprint', build);
});
