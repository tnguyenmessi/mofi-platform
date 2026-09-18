const check = document.getElementById('check-health');
check?.addEventListener('click', async () => {
    const output = document.getElementById('health-result');
    check.disabled = true;
    output.textContent = 'Đang kiểm tra…';
    try {
        const response = await fetch('/admin/health', {headers: {Accept: 'application/json'}});
        const result = await response.json();
        if (!result.checks) throw new Error();
        const labels = {database: 'Database', cache: 'Cache', demo_portfolio: 'Danh mục', market_fixture: 'Giá mô phỏng'};
        output.replaceChildren(...Object.entries(result.checks).map(([key, value]) => {
            const item = document.createElement('p');
            item.textContent = `${labels[key] ?? key}: ${value ? 'Sẵn sàng' : 'Cần kiểm tra'}`;
            item.className = value ? 'positive' : 'negative';
            return item;
        }));
    } catch { output.textContent = 'Không kiểm tra được hệ thống. Vui lòng thử lại.'; }
    finally { check.disabled = false; }
});
