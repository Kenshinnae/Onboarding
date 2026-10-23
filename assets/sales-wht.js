document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-wht-bulk-form]').forEach(form => {
    const all = form.querySelector('[data-wht-select-all]');
    const rows = [...form.querySelectorAll('[data-wht-select]:not(:disabled)')];
    const count = form.querySelector('[data-wht-selected-count]');
    const submit = form.querySelector('[data-wht-export-selected]');
    const update = () => {
      const selected = rows.filter(row => row.checked).length;
      count.textContent = 'เลือกแล้ว ' + selected + ' ใบ';
      submit.disabled = selected === 0;
      all.checked = rows.length > 0 && selected === rows.length;
      all.indeterminate = selected > 0 && selected < rows.length;
      all.disabled = rows.length === 0;
    };
    all.addEventListener('change', () => { rows.forEach(row => row.checked = all.checked); update(); });
    rows.forEach(row => row.addEventListener('change', update));
    form.addEventListener('submit', event => { if (!rows.some(row => row.checked)) { event.preventDefault(); update(); } });
    window.addEventListener('pageshow', update);
    update();
  });
});
