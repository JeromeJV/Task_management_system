const productPieColors = [
    '#16A34A', '#52B3FB', '#F59E0B', '#EF4444', '#8B5CF6',
    '#14B8A6', '#F97316', '#EC4899', '#64748B', '#84CC16'
];
let lineChart;
let productPieChart;
let chartRequestId = 0;

function showPage(page) {
    const pages = ['dashboard', 'task', 'employee'];
    pages.forEach(pageName => {
        const pageElement = document.getElementById(`page-${pageName}`);
        if (pageElement) {
            pageElement.classList.toggle('active', pageName === page);
            pageElement.style.display = pageName === page ? 'block' : 'none';
        }

        const navButton = document.getElementById(`nav${pageName.charAt(0).toUpperCase()}${pageName.slice(1)}`);
        if (navButton) {
            navButton.classList.toggle('active', pageName === page);
        }
    });
}

function renderCharts(chartData) {
    const lineCanvas = document.getElementById('myChart');
    if (lineChart) {
        lineChart.destroy();
    }
    lineChart = new Chart(lineCanvas.getContext('2d'), {
        type: 'line',
        data: {
            labels: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
            datasets: [{
                label: 'Completed Product Quantity',
                data: chartData.monthly_quantity,
                borderColor: '#52b3fb',
                backgroundColor: 'rgba(82, 179, 251, 0.2)',
                borderWidth: 3,
                pointRadius: 5,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    title: { display: true, text: 'Due month' }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: value => Number(value).toLocaleString()
                    },
                    title: { display: true, text: 'Completed Quantity' }
                }
            }
        }
    });

    const canvas = document.getElementById('productPieChart');
    const emptyMessage = document.getElementById('pieChartEmpty');
    if (productPieChart) {
        productPieChart.destroy();
        productPieChart = undefined;
    }

    if (chartData.product_frequency.length === 0) {
        canvas.hidden = true;
        emptyMessage.textContent = 'No production records available for the selected year and month.';
        emptyMessage.hidden = false;
        return;
    }

    canvas.hidden = false;
    emptyMessage.hidden = true;
    productPieChart = new Chart(canvas.getContext('2d'), {
        type: 'pie',
        data: {
            labels: chartData.product_frequency.map(product => product.product_name),
            datasets: [{
                label: 'Production Records',
                data: chartData.product_frequency.map(product => product.record_count),
                backgroundColor: chartData.product_frequency.map((_, index) => productPieColors[index % productPieColors.length]),
                borderColor: '#FFFFFF',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: context => `${context.label}: ${context.parsed} production record(s)`
                    }
                }
            }
        }
    });
}

async function loadCharts() {
    const requestId = ++chartRequestId;
    const yearFilter = document.getElementById('chartYearFilter');
    const monthFilter = document.getElementById('pieMonthFilter');
    const year = yearFilter.value;
    const month = monthFilter.value;
    const emptyMessage = document.getElementById('pieChartEmpty');
    emptyMessage.textContent = 'Loading production data...';
    emptyMessage.hidden = false;

    try {
        const query = new URLSearchParams({ year, month });
        const response = await fetch(`config/chart_data.php?${query.toString()}`);
        const chartData = await response.json();
        if (!response.ok || chartData.error) {
            throw new Error(chartData.error || 'Unable to load supervisor chart data.');
        }
        if (requestId !== chartRequestId) {
            return;
        }

        const selectedYear = String(chartData.selected_year);
        yearFilter.replaceChildren();
        chartData.available_years.forEach(availableYear => {
            const option = document.createElement('option');
            option.value = String(availableYear);
            option.textContent = String(availableYear);
            yearFilter.append(option);
        });
        yearFilter.value = selectedYear;
        renderCharts(chartData);
    } catch (error) {
        if (requestId === chartRequestId) {
            emptyMessage.textContent = 'Unable to load production data.';
            emptyMessage.hidden = false;
        }
        console.error('Error loading supervisor charts:', error);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('chartYearFilter').addEventListener('change', loadCharts);
    document.getElementById('pieMonthFilter').addEventListener('change', loadCharts);
    loadCharts();
});
