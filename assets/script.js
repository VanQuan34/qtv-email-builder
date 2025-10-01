(function() {
    const data = window.QTV_TEMPLATE_VIEWS || {};
    const labels = Object.keys(data);
    const counts = Object.values(data);

    const ctx = document.getElementById('templateUsageChart').getContext('2d');
    const chart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: window.QTV_VIEWS_LABEL || 'Views',
                data: counts,
                backgroundColor: '#226FF5',
                borderColor: '#226FF5',
                borderWidth: 1,
                borderRadius: 4,
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    stepSize: 10
                }
            },
            plugins: {
                legend: {
                    display: true,
                    labels: {
                        color: '#2a2a72',
                        font: {
                            weight: 'bold',
                            size: 14
                        }
                    }
                },
                tooltip: {
                    enabled: true,
                }
            }
        }
    });
})();
