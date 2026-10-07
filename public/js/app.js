document.querySelectorAll('[data-submit-form]').forEach((form) => {
    const button = form.querySelector('[data-submit-button]');
    const spinner = button.querySelector('.button-spinner');
    const status = form.querySelector('[data-submit-status]');
    const initiallyDisabled = button.disabled;
    const reset = () => {
        button.disabled = initiallyDisabled;
        form.removeAttribute('aria-busy');
        spinner.hidden = true;
        status.textContent = '';
    };
    form.addEventListener('submit', () => {
        button.disabled = true;
        spinner.hidden = false;
        form.setAttribute('aria-busy', 'true');
        status.textContent = 'Đang xử lý, vui lòng chờ…';
    });
    window.addEventListener('pageshow', reset);
});

document.querySelectorAll('[data-availability-url]').forEach((form) => {
    const button = form.querySelector('[data-check-availability]');
    const status = form.querySelector('[data-availability-status]');
    button.addEventListener('click', async () => {
        const input = new FormData(form);
        const query = new URLSearchParams();
        ['date', 'time', 'staff_id'].forEach((key) => { if (input.get(key)) query.set(key, input.get(key)); });
        input.getAll('service_ids[]').forEach((id) => query.append('service_ids[]', id));
        button.disabled = true;
        status.textContent = 'Đang kiểm tra…';
        try {
            const response = await fetch(form.dataset.availabilityUrl + '?' + query.toString(), {headers: {'Accept': 'application/json'}});
            const body = await response.json();
            status.textContent = response.ok ? body.message : (Object.values(body.errors || {}).flat().join(' ') || 'Không thể kiểm tra lúc này.');
        } catch {
            status.textContent = 'Không thể kết nối. Vui lòng thử lại.';
        } finally {
            button.disabled = false;
        }
    });
});
