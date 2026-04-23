/* js/metas_ah.js */

let metasData = null;
let realProductionData = null;
let metasChart = null;
let simulacaoChart = null;
let currentVision = 'geral'; // 'geral' ou 'detalhada'
let selectedYear = new Date().getFullYear();

async function initMetasAH() {
    selectedYear = new Date().getFullYear();
    await fetchMetas();
    if (metasData && metasData.despesa_anual > 0) {
        openMetasDashboard();
    } else {
        openMetasModal('modal-metas-ensino');
    }
}

async function fetchMetas() {
    try {
        const response = await fetch(`php/controllers/metas_controller.php?action=get_metas&ano=${selectedYear}`);
        metasData = await response.json();
        
        if (metasData && metasData.auth_error) {
            window.location.reload(); // Força o redirecionamento via PHP no reload
            return;
        }
    } catch (error) {
        console.error('Erro ao buscar metas:', error);
    }
}

async function fetchRealProduction() {
    try {
        const response = await fetch(`php/controllers/metas_controller.php?action=get_real_production&ano=${selectedYear}`);
        realProductionData = await response.json();
    } catch (error) {
        console.error('Erro ao buscar produção real:', error);
    }
}

function openMetasModal(id) {
    // Fecha todos os modais de produção/metas para garantir que apenas um fique visível
    document.querySelectorAll('.modal-producao').forEach(m => m.classList.remove('active'));
    
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.add('active');
        document.body.classList.add('modal-open');
    }
}

function closeMetasModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove('active');
        document.body.classList.remove('modal-open');
    }
}

function closeMetasSimulation() {
    closeMetasModal('modal-metas-simulacao');
    // Restaura o estado original do dashboard
    updateMetasCards();
    renderMetasChart();
}

async function nextToDespesas() {
    // Captura dados da Modal 1
    const cai_h = parseInt(document.getElementById('meta-cai-horas').value) || 0;
    const ct_h = parseInt(document.getElementById('meta-ct-horas').value) || 0;
    const fic_h = parseInt(document.getElementById('meta-fic-horas').value) || 0;

    // Lógica simplificada: Soma direta das produções totais
    const totalAH = cai_h + ct_h + fic_h;

    if (totalAH <= 0) {
        Swal.fire('Aviso', 'Por favor, insira valores válidos para as metas de ensino.', 'warning');
        return;
    }

    document.getElementById('display-total-ah-meta').innerText = totalAH.toLocaleString('pt-BR');
    
    // Salva temporariamente no objeto global
    metasData = {
        ano: selectedYear,
        cai_horas: cai_h,
        cai_alunos: 1,
        ct_horas: ct_h,
        ct_alunos: 1,
        fic_horas: fic_h,
        fic_alunos: 1
    };

    closeMetasModal('modal-metas-ensino');
    openMetasModal('modal-metas-despesas');
}

async function saveAllMetas() {
    const despesa = parseFloat(document.getElementById('meta-despesa-anual').value) || 0;
    if (despesa <= 0) {
        Swal.fire('Aviso', 'Por favor, insira um valor de despesa válido.', 'warning');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'save_metas');
    formData.append('ano', selectedYear);
    formData.append('cai_horas', metasData.cai_horas);
    formData.append('cai_alunos', metasData.cai_alunos);
    formData.append('ct_horas', metasData.ct_horas);
    formData.append('ct_alunos', metasData.ct_alunos);
    formData.append('fic_horas', metasData.fic_horas);
    formData.append('fic_alunos', metasData.fic_alunos);
    formData.append('despesa_anual', despesa);

    try {
        const response = await fetch('php/controllers/metas_controller.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        if (result.success) {
            closeMetasModal('modal-metas-despesas');
            await fetchMetas();
            openMetasDashboard();
        } else {
            Swal.fire('Erro', 'Erro ao salvar metas: ' + result.error, 'error');
        }
    } catch (error) {
        console.error('Erro ao salvar metas:', error);
    }
}

async function openMetasDashboard() {
    await fetchRealProduction();
    const modal = document.getElementById('modal-metas-dashboard');
    if (modal) {
        modal.classList.add('active');
        document.body.classList.add('modal-open');
        updateMetasCards();
        renderMetasChart();
    }
}

function updateMetasCards() {
    const despesa = parseFloat(metasData.despesa_anual);
    
    // Variável X: Soma das produções totais de cada modalidade
    const totalAH_Meta = parseInt(metasData.cai_horas) + 
                         parseInt(metasData.ct_horas) + 
                         parseInt(metasData.fic_horas);
    
    // Variável Z: Y / X
    const custoMeta = totalAH_Meta > 0 ? (despesa / totalAH_Meta) : 0;

    // Realizado Global: Y / R
    const totalAH_Real = realProductionData.Total;
    const custoReal = totalAH_Real > 0 ? (despesa / totalAH_Real) : 0;

    document.getElementById('card-custo-meta').innerText = 'R$ ' + custoMeta.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('card-volume-meta').innerText = totalAH_Meta.toLocaleString('pt-BR') + ' A/H Esperados';
    
    document.getElementById('card-custo-real').innerText = 'R$ ' + custoReal.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('card-volume-real').innerText = totalAH_Real.toLocaleString('pt-BR') + ' A/H Realizados';
}

function renderMetasChart() {
    const ctx = document.getElementById('chartMetasComparison').getContext('2d');
    
    let labels = [];
    let metaValues = [];
    let realValues = [];

    const despesa = parseFloat(metasData.despesa_anual);

    if (currentVision === 'geral') {
        labels = ['Geral (Unidade)'];
        
        const totalAH_Meta = parseInt(metasData.cai_horas) + 
                             parseInt(metasData.ct_horas) + 
                             parseInt(metasData.fic_horas);

        const custoMeta = totalAH_Meta > 0 ? (despesa / totalAH_Meta) : 0;
        const custoReal = realProductionData.Total > 0 ? (despesa / realProductionData.Total) : 0;

        // Voltando para comparação financeira (Custo R$) conforme pedido original
        metaValues = [custoMeta];
        realValues = [custoReal];
    } else {
        // Visão Detalhada: Comparando volumes (A/H) por modalidade
        labels = ['CAI', 'CT', 'FIC'];
        
        const volMetaCAI = metasData.cai_horas;
        const volMetaCT = metasData.ct_horas;
        const volMetaFIC = metasData.fic_horas;

        metaValues = [volMetaCAI, volMetaCT, volMetaFIC];
        realValues = [
            realProductionData.CAI || 0,
            realProductionData.CT || 0,
            realProductionData.FIC || 0
        ];
    }

    if (metasChart) metasChart.destroy();

    metasChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: currentVision === 'geral' ? 'Custo A/H (Meta)' : 'Produção A/H (Meta)',
                data: metaValues,
                backgroundColor: 'rgba(0, 137, 123, 0.7)',
                borderColor: 'rgba(0, 137, 123, 1)',
                borderWidth: 1,
                borderRadius: 8
            }, {
                label: currentVision === 'geral' ? 'Custo A/H (Real)' : 'Produção A/H (Real)',
                data: realValues,
                backgroundColor: 'rgba(25, 118, 210, 0.7)',
                borderColor: 'rgba(25, 118, 210, 1)',
                borderWidth: 1,
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            const value = context.raw;
                            if (currentVision === 'geral') {
                                return context.dataset.label + ': R$ ' + value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            }
                            return context.dataset.label + ': ' + value.toLocaleString('pt-BR') + ' A/H';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: document.body.classList.contains('dark-mode') || document.documentElement.getAttribute('data-tema') === 'escuro' ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.05)' },
                    title: {
                        display: true,
                        text: currentVision === 'geral' ? 'CUSTO EM REAIS (R$)' : 'VOLUME DE PRODUÇÃO (A/H)',
                        font: { weight: 'bold' }
                    },
                    ticks: {
                        callback: function(value) {
                            if (currentVision === 'geral') return 'R$ ' + value.toLocaleString('pt-BR');
                            return value.toLocaleString('pt-BR');
                        }
                    }
                }
            }
        }
    });
}

function changeMetasVision() {
    currentVision = currentVision === 'geral' ? 'detalhada' : 'geral';
    document.getElementById('btn-change-vision').innerHTML = currentVision === 'geral' ? '<i class="fas fa-search-plus"></i> Detalhar por Curso' : '<i class="fas fa-compress-alt"></i> Visão Geral';
    renderMetasChart();
}

function openEditMetas() {
    // Preenche os campos da modal 1 com os dados atuais
    document.getElementById('meta-cai-horas').value = metasData.cai_horas;
    document.getElementById('meta-ct-horas').value = metasData.ct_horas;
    document.getElementById('meta-fic-horas').value = metasData.fic_horas;
    document.getElementById('meta-despesa-anual').value = metasData.despesa_anual;
    
    closeMetasModal('modal-metas-dashboard');
    openMetasModal('modal-metas-ensino');
}

function openMetasSimulationModal() {
    document.getElementById('sim-despesa').value = metasData.despesa_anual;
    document.getElementById('sim-cai').value = realProductionData.CAI || 0;
    document.getElementById('sim-ct').value = realProductionData.CT || 0;
    document.getElementById('sim-fic').value = realProductionData.FIC || 0;
    updateMetasSimulation();
    openMetasModal('modal-metas-simulacao');
}

function updateMetasSimulation() {
    // Valores Simulados
    const simDespesa = parseFloat(document.getElementById('sim-despesa').value) || metasData.despesa_anual;
    const simCAI = parseFloat(document.getElementById('sim-cai').value) || 0;
    const simCT = parseFloat(document.getElementById('sim-ct').value) || 0;
    const simFIC = parseFloat(document.getElementById('sim-fic').value) || 0;
    
    const simProducaoTotal = simCAI + simCT + simFIC;

    // Meta Original baseada na soma direta
    const totalAH_Meta = parseInt(metasData.cai_horas) + 
                         parseInt(metasData.ct_horas) + 
                         parseInt(metasData.fic_horas);
    
    // RECALCULA AMBOS OS CUSTOS COM A DESPESA SIMULADA
    const custoMetaSim = totalAH_Meta > 0 ? (simDespesa / totalAH_Meta) : 0;
    const custoRealSim = simProducaoTotal > 0 ? (simDespesa / simProducaoTotal) : 0;
    const atingimento = totalAH_Meta > 0 ? (simProducaoTotal / totalAH_Meta) * 100 : 0;

    // SUBSTITUIÇÃO TEMPORÁRIA NO DASHBOARD PRINCIPAL (ALTERA OS DOIS)
    document.getElementById('card-custo-meta').innerText = 'R$ ' + custoMetaSim.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('card-custo-real').innerText = 'R$ ' + custoRealSim.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('card-volume-real').innerText = simProducaoTotal.toLocaleString('pt-BR') + ' A/H (Simulado)';
    
    // Resultados internos do modal de simulação
    document.getElementById('sim-resultado-producao').innerText = simProducaoTotal.toLocaleString('pt-BR') + ' A/H';
    document.getElementById('sim-resultado-custo').innerText = 'R$ ' + custoRealSim.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('sim-resultado-atingimento').innerText = atingimento.toFixed(1) + '%';
    
    // Atualiza o gráfico principal (se estiver na visão geral de custo)
    if (metasChart && currentVision === 'geral') {
        metasChart.data.datasets[0].data = [custoMetaSim];
        metasChart.data.datasets[1].data = [custoRealSim];
        metasChart.update();
    }

    // Atualiza o gráfico interno da modal de simulação
    updateSimulacaoChart(custoMetaSim, custoRealSim);
}

function updateSimulacaoChart(metaVal, realVal) {
    const ctx = document.getElementById('chartSimulacao').getContext('2d');
    
    if (simulacaoChart) {
        simulacaoChart.data.datasets[0].data = [metaVal];
        simulacaoChart.data.datasets[1].data = [realVal];
        simulacaoChart.update();
    } else {
        simulacaoChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Comparativo Simulado (R$)'],
                datasets: [{
                    label: 'Custo A/H Meta',
                    data: [metaVal],
                    backgroundColor: 'rgba(0, 137, 123, 0.5)',
                    borderColor: 'rgba(0, 137, 123, 1)',
                    borderWidth: 1,
                    borderRadius: 8
                }, {
                    label: 'Custo A/H Simulado',
                    data: [realVal],
                    backgroundColor: 'rgba(106, 27, 154, 0.7)',
                    borderColor: 'rgba(106, 27, 154, 1)',
                    borderWidth: 1,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': R$ ' + context.raw.toLocaleString('pt-BR', { minimumFractionDigits: 2 });
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'CUSTO A/H (R$)' }
                    }
                }
            }
        });
    }
}
