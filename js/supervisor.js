async function loadChart() {
    try {
        const response = await fetch('config/chart_data.php'); 
        const rawText = await response.text();

        if (rawText.trim().startsWith('<')) {
            console.error("May PHP Error sa backend. Ito ang lumabas:", rawText);
            return;
        }

        const chartData = JSON.parse(rawText);
        console.log("Nakuha ang Data:", chartData);

        const ctx = document.getElementById('myChart').getContext('2d');
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
                datasets: [{
                    label: 'Product Done Quantity',
                    data: chartData,
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
                animations: {
                    tension: {
                        duration: 1000,
                        easing: 'linear',
                        from: 0.8,
                        to: 0.4,
                        loop: true
                    },
                    y: {
                        duration: 2000,
                        easing: 'easeInOutQuad'
                    }
                },
                scales: {
                    x: {
                        title: { display: true, text: 'Months (January - December)' }
                    },
                    y: {
                        beginAtZero: true,
                        min: 0,
                        max: 100000,
                        ticks: {
                            stepSize: 20000
                        },
                        title: { display: true, text: 'Total Completed Quantity' }
                    }
                }
            }
        });

    } catch (error) {
        console.error("Error loading chart:", error);
    }
}   

document.addEventListener("DOMContentLoaded", loadChart);