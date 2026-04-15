/* js/workload_dashboard.js */

let workloadData = [];
let workloadChart = null;

async function openWorkloadModal() {
    const modal = document.getElementById('modal-workload-global');
    if (modal) {
        modal.classList.add('active');
        document.body.classList.add('modal-open');
        await fetchWorkloadData();
        renderWorkloadChart();
    }
}

function closeWorkloadModal() {
    const modal = document.getElementById('modal-workload-global');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => {
            if (document.querySelectorAll('.modal-producao.active').length === 0) {
                document.body.classList.remove('modal-open');
            }
        }, 50);
    }
}

async function fetchWorkloadData() {
    try {
        const response = await fetch('php/controllers/workload_controller.php?action=get_global');
        workloadData = await response.json();
    } catch (error) {
        console.error('Erro ao buscar dados de carga horária:', error);
    }
}

function renderWorkloadChart() {
    const ctx = document.getElementById('chartWorkloadDocentes').getContext('2d');

    // Mapeamento para o gráfico (já vem ordenado do controller, mas garantimos aqui)
    const labels = workloadData.map(d => d.nome);
    const values = workloadData.map(d => d.saldo);

    if (workloadChart) {
        workloadChart.destroy();
    }

    workloadChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Horas Disponíveis (Saldo)',
                data: values,
                backgroundColor: 'rgba(106, 27, 154, 0.7)',
                borderColor: '#4a148c',
                borderWidth: 1,
                borderRadius: 5,
                hoverBackgroundColor: '#4a148c',
                barPercentage: 0.6
            }]
        },
        options: {
            indexAxis: 'x',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            return 'Saldo: ' + context.raw.toLocaleString('pt-BR') + ' horas';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    title: { display: true, text: 'HORAS DISPONÍVEIS' }
                },
                x: {
                    grid: { display: false },
                    ticks: {
                        autoSkip: false,
                        maxRotation: 45,
                        minRotation: 45,
                        font: { size: 9, weight: 'bold' }
                    }
                }
            }
        }
    });
}
