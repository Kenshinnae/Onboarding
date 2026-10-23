document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-copy-project-report]').forEach(button => {
    button.addEventListener('click', async () => {
      const box = button.closest('.crm-customer-report-controls');
      const status = box.querySelector('[data-report-copy-status]');
      button.disabled = true; status.textContent = 'กำลังเตรียมลิงก์…';
      try {
        const response = await fetch(button.dataset.endpoint, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'co_project_customer_report_link', project:button.dataset.copyProjectReport, nonce:button.dataset.nonce})});
        const result = await response.json();
        if (!response.ok || !result.success || !result.data.url) throw new Error('Unable to load report link');
        let copied = false;
        try { if (navigator.clipboard && window.isSecureContext) {await navigator.clipboard.writeText(result.data.url); copied = true;} } catch (_) {}
        if (!copied) {
          const input = document.createElement('textarea'); input.value = result.data.url; input.readOnly = true;
          input.style.cssText = 'position:fixed;left:-9999px'; document.body.append(input); input.select();
          try {copied = document.execCommand('copy');} catch (_) {} input.remove();
        }
        let fallback = box.querySelector('[data-report-link-fallback]');
        if (!copied) {
          if (!fallback) {fallback = document.createElement('input'); fallback.readOnly = true; fallback.dataset.reportLinkFallback = ''; fallback.setAttribute('aria-label','ลิงก์รายงานลูกค้า — เลือกแล้วคัดลอก'); box.querySelector('.crm-customer-report-actions').after(fallback);}
          fallback.value = result.data.url; fallback.hidden = false; fallback.focus(); fallback.select();
        } else if (fallback) fallback.hidden = true;
        status.textContent = copied ? 'คัดลอกลิงก์แล้ว ✓' : 'เลือกคัดลอกลิงก์จากช่องด้านล่าง';
      } catch (_) {status.textContent = 'เตรียมลิงก์ไม่สำเร็จ กรุณาลองใหม่ หรือกดเปิดรายงานลูกค้า';}
      finally {button.disabled = false;}
    });
  });
  document.querySelectorAll('.sales-project-client').forEach(form => {
    form.addEventListener('submit', () => {
      const button = form.querySelector('button');
      if (button) { button.disabled = true; button.textContent = 'กำลังเชื่อมลูกค้า…'; }
    });
  });
  const clientNotice = document.querySelector('.sales-project-client-success');
  if (clientNotice) requestAnimationFrame(() => clientNotice.scrollIntoView({block: 'center'}));
  document.querySelectorAll('[name="existing_payment"]').forEach(select => {
    const date = select.closest('form').querySelector('[name="paid_date"]');
    const original = date.value;
    const update = () => { date.readOnly = select.value !== '0'; date.value = date.readOnly ? select.selectedOptions[0].dataset.paidDate : original; };
    select.addEventListener('change', update); update();
  });
  document.querySelectorAll('[data-project-ma-client]').forEach(select => {
    const form = select.closest('form');
    const update = () => {
      const existing = select.value !== '0';
      form.querySelector('[data-project-ma-new]').hidden = existing;
      form.querySelectorAll('[data-project-ma-new] input,[data-project-ma-new] select').forEach(input => input.disabled = existing);
      form.querySelectorAll('[data-project-ma-existing]').forEach(section => section.hidden = !existing);
      const stream = form.querySelector('[data-project-ma-stream]');
      stream.value = '0';
      stream.querySelectorAll('[data-client]').forEach(option => { option.hidden = option.dataset.client !== select.value; option.disabled = option.hidden; });
    };
    select.addEventListener('change', update); update();
  });
  document.querySelectorAll('.crm-drawer [data-sales-confirm],.crm-wrap [data-sales-confirm]').forEach(button => {
    button.addEventListener('click', event => { if (!window.confirm(button.dataset.salesConfirm)) event.preventDefault(); });
  });
});
