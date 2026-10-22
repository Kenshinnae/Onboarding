document.addEventListener('DOMContentLoaded', () => {
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
