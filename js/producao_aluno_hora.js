/* js/producao_aluno_hora.js */

let producaoData = [];
let producaoChart = null;

async function openProducaoModal() {
    const modal = document.getElementById('modal-producao-geral');
    if (modal) {
        modal.classList.add('active');
        document.body.classList.add('modal-open');
        await fetchProducaoData();
        renderProducaoChart();
        updateTotalProducao();
    }
}

function closeProducaoModal(id) {
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

async function fetchProducaoData() {
    try {
        const response = await fetch('php/controllers/producao_controller.php?action=get_data');
        const data = await response.json();
        producaoData = data.ranking;
        window.totalUnidadeProducao = data.total_unidade;
    } catch (error) {
        console.error('Erro ao buscar dados de produção:', error);
    }
}

function updateTotalProducao() {
    const totalEl = document.getElementById('total-producao-geral');
    if (totalEl) {
        totalEl.innerText = (window.totalUnidadeProducao || 0).toLocaleString('pt-BR');
    }
}

function renderProducaoChart(filter = '') {
    const ctx = document.getElementById('chartProducaoDocentes').getContext('2d');

    // Filtra e ordena (Ranking)
    let filtered = producaoData
        .filter(d => d.nome.toLowerCase().includes(filter.toLowerCase()))
        .sort((a, b) => b.producao_total - a.producao_total);

    const labels = filtered.map(d => d.nome);
    const values = filtered.map(d => d.producao_total);

    if (producaoChart) {
        producaoChart.destroy();
    }

    producaoChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Produção Aluno/Hora',
                data: values,
                backgroundColor: 'rgba(25, 118, 210, 0.7)',
                borderColor: '#1976d2',
                borderWidth: 1,
                borderRadius: 5,
                hoverBackgroundColor: '#1565c0',
                barPercentage: 0.6
            }]
        },
        options: {
            indexAxis: 'x', // Barra vertical agora
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            return 'Produção: ' + context.raw.toLocaleString('pt-BR') + ' A/H';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    title: { display: true, text: 'PROD. ALUNO/HORA' }
                },
                x: {
                    grid: { display: false },
                    ticks: {
                        autoSkip: false,
                        maxRotation: 45,
                        minRotation: 0,
                        font: { size: 9 }
                    }
                }
            },
            onClick: (e, elements) => {
                if (elements.length > 0) {
                    const index = elements[0].index;
                    const docente = filtered[index];
                    openTeacherDetail(docente.id);
                }
            }
        }
    });
}

function filterProducaoChart() {
    const searchInput = document.getElementById('search-producao-prof');
    const query = searchInput ? searchInput.value : '';
    renderProducaoChart(query);
}

function openTeacherDetail(docenteId) {
    const docente = producaoData.find(d => d.id == docenteId);
    if (!docente) return;

    document.getElementById('detalhe-prof-nome').innerText = docente.nome;
    const listContainer = document.getElementById('lista-turmas-producao');
    listContainer.innerHTML = '';

    docente.turmas.forEach(t => {
        const item = document.createElement('div');
        item.className = 'turma-producao-item';
        item.innerHTML = `
            <div class="turma-info">
                <span class="turma-nome">${t.sigla} - ${t.curso}</span>
                <span class="turma-calc">
                    <span class="alunos-count">${t.alunos}</span> alunos x ${t.ch} horas = <strong>${t.producao} A/H</strong>
                </span>
            </div>
            <div style="display: flex; align-items: center; gap: 12px;">
                <div class="producao-value-label">${t.producao.toLocaleString('pt-BR')} A/H</div>
                <div class="evasao-action" style="display: flex; gap: 8px;">
                    <button class="btn-adicionar" onclick="adicionarAluno(${t.id}, ${docenteId})" title="Adicionar Aluno(s)">
                        <i class="fas fa-user-plus"></i>
                    </button>
                    <button class="btn-evasao" onclick="registrarEvasao(${t.id}, ${docenteId})" title="Registrar Evasão(ões)">
                        <i class="fas fa-user-minus"></i>
                    </button>
                </div>
            </div>
        `;
        listContainer.appendChild(item);
    });

    closeProducaoModal('modal-producao-geral');
    document.getElementById('modal-producao-detalhe').classList.add('active');
}

function backToProducaoGeral() {
    closeProducaoModal('modal-producao-detalhe');
    document.getElementById('modal-producao-geral').classList.add('active');
}

function registrarEvasao(turmaId, docenteId) {
    document.getElementById('hidden-evasao-turma-id').value = turmaId;
    document.getElementById('hidden-evasao-docente-id').value = docenteId;
    document.getElementById('input-qtd-evasao').value = 1; // Reset para 1
    const modal = document.getElementById('modal-producao-evasao');
    modal.classList.add('active');
    document.body.classList.add('modal-open');
}

async function confirmarEvasao() {
    const turmaId = document.getElementById('hidden-evasao-turma-id').value;
    const docenteId = document.getElementById('hidden-evasao-docente-id').value;
    const quantidade = parseInt(document.getElementById('input-qtd-evasao').value);

    if (isNaN(quantidade) || quantidade <= 0) {
        alert('Por favor, digite um número válido maior que zero.');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('action', 'registrar_evasao');
        formData.append('turma_id', turmaId);
        formData.append('quantidade', quantidade);

        const response = await fetch('php/controllers/producao_controller.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        if (result.success) {
            // Atualização reativa local
            const docente = producaoData.find(d => d.id == docenteId);
            const turma = docente.turmas.find(t => t.id == turmaId);

            // Recalcula localmente
            turma.alunos = Math.max(0, turma.alunos - quantidade);
            turma.producao = turma.alunos * turma.ch;

            // Atualiza produção total do docente
            docente.producao_total = docente.turmas.reduce((acc, t) => acc + t.producao, 0);

            // Fecha modal de evasão
            closeProducaoModal('modal-producao-evasao');

            // Atualiza UI em tempo real
            openTeacherDetail(docenteId); // Recarrega Modal 2
            updateTotalProducao(); // Recarrega Totalizador Geral
            const searchInput = document.getElementById('search-producao-prof');
            renderProducaoChart(searchInput ? searchInput.value : ''); // Recarrega Gráfico ranking
        } else {
            alert('Erro ao registrar evasão: ' + result.error);
        }
    } catch (error) {
        console.error('Erro na requisição de evasão:', error);
    }
}

function adicionarAluno(turmaId, docenteId) {
    document.getElementById('hidden-adicao-turma-id').value = turmaId;
    document.getElementById('hidden-adicao-docente-id').value = docenteId;
    document.getElementById('input-qtd-adicao').value = 1; // Reset para 1
    const modal = document.getElementById('modal-producao-adicao');
    modal.classList.add('active');
    document.body.classList.add('modal-open');
}

async function confirmarAdicao() {
    const turmaId = document.getElementById('hidden-adicao-turma-id').value;
    const docenteId = document.getElementById('hidden-adicao-docente-id').value;
    const quantidade = parseInt(document.getElementById('input-qtd-adicao').value);

    if (isNaN(quantidade) || quantidade <= 0) {
        alert('Por favor, digite um número válido maior que zero.');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('action', 'adicionar_aluno');
        formData.append('turma_id', turmaId);
        formData.append('quantidade', quantidade);

        const response = await fetch('php/controllers/producao_controller.php', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        if (result.success) {
            // Atualização reativa local
            const docente = producaoData.find(d => d.id == docenteId);
            const turma = docente.turmas.find(t => t.id == turmaId);
            
            // Recalcula localmente
            turma.alunos = (turma.alunos || 0) + quantidade;
            turma.producao = turma.alunos * turma.ch;
            
            // Atualiza produção total do docente
            docente.producao_total = docente.turmas.reduce((acc, t) => acc + t.producao, 0);

            // Fecha modal de adição
            closeProducaoModal('modal-producao-adicao');

            // Atualiza UI em tempo real
            openTeacherDetail(docenteId); // Recarrega Modal 2
            updateTotalProducao(); // Recarrega Totalizador Geral
            const searchInput = document.getElementById('search-producao-prof');
            renderProducaoChart(searchInput ? searchInput.value : ''); // Recarrega Gráfico ranking
        } else {
            alert('Erro ao adicionar aluno: ' + result.error);
        }
    } catch (error) {
        console.error('Erro na requisição de adição de aluno:', error);
    }
}
// Fechamento ao clicar fora da modal (no overlay)
let producaoModalClickStart = null;
window.addEventListener('mousedown', function(e) {
    producaoModalClickStart = e.target;
});

window.addEventListener('click', function (event) {
    if (event.target !== producaoModalClickStart) return;

    // Caso 1: Modais de Produção/Carga Horária (classe modal-producao)
    if (event.target.classList.contains('modal-producao')) {
        const modalId = event.target.id;
        if (modalId === 'modal-workload-global' && typeof closeWorkloadModal === 'function') {
            closeWorkloadModal();
        } else if (modalId === 'modal-metas-simulacao' && typeof closeMetasSimulation === 'function') {
            closeMetasSimulation();
        } else {
            closeProducaoModal(modalId);
        }
    }
    
    // Caso 2: Modais de Substituição/Disponibilidade (classe modal-subst)
    if (event.target.classList.contains('modal-subst')) {
        const modalId = event.target.id;
        if (modalId === 'modal-substituicao-gera' && typeof closeSubstituicaoModal === 'function') {
            closeSubstituicaoModal();
        } else if (modalId === 'modal-pesquisa-turma' && typeof fecharPesquisaTurma === 'function') {
            fecharPesquisaTurma();
        }
    }
});

// Listener global para garantir responsividade dos gráficos de produção ao redimensionar a janela
window.addEventListener('resize', () => {
    if (typeof producaoChart !== 'undefined' && producaoChart) {
        producaoChart.resize();
    }
});
