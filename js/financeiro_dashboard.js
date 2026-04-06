/* js/financeiro_dashboard.js */

let financeiroData = {
    ressarcido: [],
    ressarcido_detalhe: [],
    despesas: [],
    total_despesas: 0
};
let ressarcidoChart = null;
let despesasChart = null;

async function fetchFinanceiroData() {
    try {
        const response = await fetch('php/controllers/producao_controller.php?action=get_financeiro_data');
        financeiroData = await response.json();
    } catch (error) {
        console.error('Erro ao buscar dados financeiros:', error);
    }
}

async function openRessarcimentoModal() {
    const modal = document.getElementById('modal-ressarcimento-ranking');
    if (modal) {
        modal.classList.add('active');
        document.body.classList.add('modal-open');
        await fetchFinanceiroData();
        renderRessarcidoChart();
        updateTotalRessarcido();
    }
}

function openRessarcimentoListaModal() {
    closeFinanceiroModal('modal-ressarcimento-ranking');
    const modal = document.getElementById('modal-ressarcimento-lista');
    if (modal) {
        modal.classList.add('active');
        document.body.classList.add('modal-open');
        renderRessarcimentoTable();
    }
}

function backToRessarcimentoRanking() {
    closeFinanceiroModal('modal-ressarcimento-lista');
    const modal = document.getElementById('modal-ressarcimento-ranking');
    if (modal) {
        modal.classList.add('active');
        document.body.classList.add('modal-open');
    }
}

async function openDespesasModal() {
    const modal = document.getElementById('modal-despesas-geral');
    if (modal) {
        modal.classList.add('active');
        document.body.classList.add('modal-open');
        await fetchFinanceiroData();
        renderDespesasChart();
        updateTotalDespesas();
    }
}

function openDespesasListaModal() {
    closeFinanceiroModal('modal-despesas-geral');
    const modal = document.getElementById('modal-despesas-lista');
    if (modal) {
        modal.classList.add('active');
        document.body.classList.add('modal-open');
        renderDespesasTable();
    }
}

function backToDespesasGeral() {
    closeFinanceiroModal('modal-despesas-lista');
    const modal = document.getElementById('modal-despesas-geral');
    if (modal) {
        modal.classList.add('active');
        document.body.classList.add('modal-open');
    }
}

function closeFinanceiroModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove('active');
        // Remove a trava de scroll apenas se não houver mais nenhum modal de produção ativo
        setTimeout(() => {
            if (document.querySelectorAll('.modal-producao.active').length === 0) {
                document.body.classList.remove('modal-open');
            }
        }, 50);
    }
}

function updateTotalRessarcido() {
    const total = financeiroData.ressarcido.reduce((acc, curr) => acc + parseFloat(curr.total), 0);
    const totalEl = document.getElementById('total-ressarcido-geral');
    if (totalEl) {
        totalEl.innerText = 'R$ ' + total.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
    }
}

function updateTotalDespesas() {
    const totalEl = document.getElementById('total-previsao-despesas');
    if (totalEl) {
        totalEl.innerText = 'R$ ' + parseFloat(financeiroData.total_despesas).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
    }
}

function renderRessarcidoChart() {
    const ctx = document.getElementById('chartRessarcimentoCursos').getContext('2d');
    const labels = financeiroData.ressarcido.map(d => d.curso);
    const values = financeiroData.ressarcido.map(d => parseFloat(d.total));

    if (ressarcidoChart) {
        ressarcidoChart.destroy();
    }

    ressarcidoChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Arrecadação R$',
                data: values,
                backgroundColor: 'rgba(56, 142, 60, 0.7)',
                borderColor: '#2e7d32',
                borderWidth: 1,
                borderRadius: 5,
                barPercentage: 0.6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            return 'Total: R$ ' + context.raw.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'VALOR ARRECADADO (R$)' }
                },
                x: {
                    ticks: { 
                        autoSkip: false,
                        maxRotation: 45,
                        minRotation: 0,
                        font: { size: 9 } 
                    }
                }
            }
        }
    });
}

function renderDespesasChart() {
    const ctx = document.getElementById('chartDespesasTurmas').getContext('2d');
    
    // Voltar a usar Sigla como label primário conforme solicitado
    const labels = financeiroData.despesas.map(d => d.sigla);
    const values = financeiroData.despesas.map(d => parseFloat(d.valor));

    if (despesasChart) {
        despesasChart.destroy();
    }

    despesasChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Despesa Prevista R$',
                data: values,
                backgroundColor: 'rgba(230, 81, 0, 0.7)',
                borderColor: '#ef6c00',
                borderWidth: 1,
                borderRadius: 5,
                barPercentage: 0.6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        title: function(context) {
                            const index = context[0].dataIndex;
                            return financeiroData.despesas[index].sigla + ' - ' + financeiroData.despesas[index].curso;
                        },
                        label: function (context) {
                            return 'Despesa: R$ ' + context.raw.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: { display: true, text: 'PREVISÃO DE GASTOS (R$)' }
                },
                x: {
                    ticks: { 
                        autoSkip: false,
                        maxRotation: 45,
                        minRotation: 0,
                        font: { size: 9 } 
                    }
                }
            }
        }
    });
}

function renderRessarcimentoTable() {
    const listContainer = document.getElementById('lista-ressarcimento-turmas');
    
    if (listContainer) {
        listContainer.innerHTML = '';
        if (financeiroData.ressarcido_detalhe) {
            financeiroData.ressarcido_detalhe.forEach(d => {
                const item = document.createElement('div');
                item.className = 'turma-producao-item';
                item.style.padding = '15px';
                item.innerHTML = `
                    <div class="turma-info">
                        <span class="turma-nome" style="font-size: 1rem; font-weight: 700;">${d.sigla}</span>
                        <span class="turma-calc" style="font-size: 0.85rem; color: var(--text-muted);">${d.curso} · Valor da Turma</span>
                    </div>
                    <div class="producao-value-label" style="background: rgba(56, 142, 60, 0.1); color: #2e7d32; font-weight: 800; padding: 5px 12px; border-radius: 6px; white-space: nowrap;">
                        R$ ${parseFloat(d.valor).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                    </div>
                `;
                listContainer.appendChild(item);
            });
        }
    }
}

function renderDespesasTable() {
    const listContainer = document.getElementById('lista-despesas-turmas');
    
    if (listContainer) {
        listContainer.innerHTML = '';
        financeiroData.despesas.forEach(d => {
            const item = document.createElement('div');
            item.className = 'turma-producao-item';
            item.style.padding = '15px';
            item.innerHTML = `
                <div class="turma-info">
                    <span class="turma-nome" style="font-size: 1rem; font-weight: 700;">${d.sigla}</span>
                    <span class="turma-calc" style="font-size: 0.85rem; color: var(--text-muted);">${d.curso} · Previsão de Gastos Operacionais</span>
                </div>
                <div class="producao-value-label" style="background: rgba(230, 81, 0, 0.1); color: #e65100; font-weight: 800; padding: 5px 12px; border-radius: 6px; white-space: nowrap;">
                    R$ ${parseFloat(d.valor).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                </div>
            `;
            listContainer.appendChild(item);
        });
    }
}
