/**
 * Script de Substituição Temporária - Padrão SENAI (Sleek)
 * Fluxo: Turma -> Titular -> Config -> Substituto
 */

let selectedSubstitutoId = null;
let turmasCache = [];

function openSubstituicaoModal() {
    const modal = document.getElementById('modal-substituicao-gera');
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    // Reset estado
    selectedSubstitutoId = null;
    document.getElementById('subst-turma-id').value = '';
    document.getElementById('subst-turma-display').value = '';
    document.getElementById('subst-titular-select').innerHTML = '<option value="">-- Selecione a Turma Primeiro --</option>';
    document.getElementById('subst-titular-select').disabled = true;
    document.getElementById('subst-step-2').style.opacity = '0.5';
    document.getElementById('subst-step-2').style.pointerEvents = 'none';
    document.getElementById('subst-results-wrapper').style.display = 'none';
    document.getElementById('subst-confirmation').style.display = 'none';

    carregarTurmasParaSubst();
}

function closeSubstituicaoModal() {
    const modal = document.getElementById('modal-substituicao-gera');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
}

// LÓGICA DE PESQUISA DE TURMAS
function abrirPesquisaTurma() {
    const modal = document.getElementById('modal-pesquisa-turma');
    modal.style.display = 'block';
    const input = document.getElementById('input-busca-turma');
    input.value = '';
    input.focus();
    renderizarListaTurmas(turmasCache);
}

function fecharPesquisaTurma() {
    document.getElementById('modal-pesquisa-turma').style.display = 'none';
}

async function carregarTurmasParaSubst() {
    try {
        const response = await fetch('php/controllers/substituicao_api.php?action=get_turmas_ativas');
        const data = await response.json();
        if (data.turmas) {
            turmasCache = data.turmas;
        }
    } catch (e) { console.error(e); }
}

function filtrarListaTurmas(query) {
    const q = query.toLowerCase();
    const filtradas = turmasCache.filter(t => 
        t.sigla.toLowerCase().includes(q) || 
        t.curso_nome.toLowerCase().includes(q)
    );
    renderizarListaTurmas(filtradas);
}

function renderizarListaTurmas(lista) {
    const container = document.getElementById('lista-turmas-pesquisa');
    if (lista.length === 0) {
        container.innerHTML = '<div style="padding: 20px; text-align: center; color: #888;">Nenhuma turma encontrada.</div>';
        return;
    }

    container.innerHTML = lista.map(t => `
        <div class="subst-item-lista-turma" onclick="selecionarTurmaNaPesquisa(${t.id}, '${t.sigla}')">
            <strong>${t.sigla}</strong>
            <span>${t.curso_nome} (${t.periodo})</span>
            <small style="display: block; opacity: 0.6; font-size: 0.7rem;">Início: ${t.data_inicio} | Fim: ${t.data_fim || 'N/A'}</small>
        </div>
    `).join('');
}

function selecionarTurmaNaPesquisa(id, sigla) {
    document.getElementById('subst-turma-id').value = id;
    document.getElementById('subst-turma-display').value = sigla;
    fecharPesquisaTurma();
    onTurmaChange(id);
}

async function onTurmaChange(turmaId) {
    const titularSelect = document.getElementById('subst-titular-select');
    const step2 = document.getElementById('subst-step-2');
    
    if (!turmaId) {
        titularSelect.innerHTML = '<option value="">-- Selecione a Turma Primeiro --</option>';
        titularSelect.disabled = true;
        step2.style.opacity = '0.5';
        step2.style.pointerEvents = 'none';
        return;
    }

    titularSelect.disabled = false;
    titularSelect.innerHTML = '<option value="">Carregando professores...</option>';

    try {
        // Busca docentes
        const response = await fetch(`php/controllers/substituicao_api.php?action=get_docentes_por_turma&turma_id=${turmaId}`);
        const data = await response.json();
        
        // Busca data mais recente
        const respData = await fetch(`php/controllers/substituicao_api.php?action=get_datas_relevantes&turma_id=${turmaId}`);
        const infoData = await respData.json();

        if (infoData.data_mais_recente) {
            document.getElementById('subst-data-inicio').value = infoData.data_mais_recente;
            document.getElementById('subst-data-fim').value = infoData.data_mais_recente;
        }

        titularSelect.innerHTML = '<option value="">-- Selecionar Docente Titular --</option>';
        if (data.docentes && data.docentes.length > 0) {
            data.docentes.forEach(d => {
                const opt = document.createElement('option');
                opt.value = d.id;
                opt.dataset.area = d.area || '';
                opt.textContent = d.nome;
                titularSelect.appendChild(opt);
            });
        } else {
            titularSelect.innerHTML = '<option value="">Nenhum professor fixo encontrado</option>';
        }
    } catch (e) { console.error(e); }
}

function onTitularChange(select) {
    const step2 = document.getElementById('subst-step-2');
    const areaSelect = document.getElementById('subst-area');
    
    if (select.value) {
        step2.style.opacity = '1';
        step2.style.pointerEvents = 'all';
        
        // Lógica sugerida: Preencher área automaticamente
        const area = select.options[select.selectedIndex].dataset.area;
        if (area) {
            areaSelect.value = area;
        }
    } else {
        step2.style.opacity = '0.5';
        step2.style.pointerEvents = 'none';
    }
}

async function buscarProfessoresDisponiveis() {
    const periodo = document.getElementById('subst-periodo').value;
    const data_inicio = document.getElementById('subst-data-inicio').value;
    const data_fim = document.getElementById('subst-data-fim').value;
    const area = document.getElementById('subst-area').value;

    if (!data_inicio || !data_fim) {
        Swal.fire('Atenção', 'Selecione o intervalo de datas.', 'warning');
        return;
    }

    const wrapper = document.getElementById('subst-results-wrapper');
    const container = document.getElementById('subst-results');
    
    wrapper.style.display = 'block';
    container.innerHTML = '<div style="padding: 30px; text-align: center;"><i class="fas fa-spinner fa-spin fa-2x"></i><br><br>Buscando substitutos...</div>';
    document.getElementById('subst-confirmation').style.display = 'none';

    try {
        const turmaId = document.getElementById('subst-turma-id').value;
        const titularId = document.getElementById('subst-titular-select').value;
        const url = `php/controllers/substituicao_api.php?action=buscar_disponiveis&periodo=${periodo}&data_inicio=${data_inicio}&data_fim=${data_fim}&area=${area}&turma_id=${turmaId}&titular_id=${titularId}`;
        const response = await fetch(url);
        const data = await response.json();

        if (data.professores && data.professores.length > 0) {
            let html = '<div class="subst-grid-prof">';
            data.professores.forEach(p => {
                html += `
                    <div class="subst-card-prof" data-id="${p.id}" onclick="selecionarSubstituto(this, ${p.id})">
                        <h4>${p.nome}</h4>
                        <p>${p.area || 'Sem Área'}</p>
                    </div>
                `;
            });
            html += '</div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = '<div style="padding: 20px; text-align: center; color: #ff5252;">Nenhum professor substituto disponível para estes critérios.</div>';
        }
    } catch (e) {
        console.error(e);
        container.innerHTML = '<div style="padding: 20px; text-align: center; color: #ff5252;">Erro na busca.</div>';
    }
}

function selecionarSubstituto(element, id) {
    document.querySelectorAll('.subst-card-prof').forEach(el => el.classList.remove('selected'));
    element.classList.add('selected');
    selectedSubstitutoId = id;
    
    document.getElementById('subst-confirmation').style.display = 'block';
    document.getElementById('subst-confirmation').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

async function confirmarSubstituicao() {
    const turma_id = document.getElementById('subst-turma-id').value;
    const data_inicio = document.getElementById('subst-data-inicio').value;
    const data_fim = document.getElementById('subst-data-fim').value;
    const titular_id = document.getElementById('subst-titular-select').value;

    if (!selectedSubstitutoId || !turma_id || !titular_id) {
        Swal.fire('Atenção', 'Selecione o substituto, o titular e a turma.', 'warning');
        return;
    }

    const btn = document.querySelector('.subst-btn-confirm');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';

    const formData = new FormData();
    formData.append('action', 'executar_substituicao');
    formData.append('docente_id', selectedSubstitutoId);
    formData.append('turma_id', turma_id);
    formData.append('data_inicio', data_inicio);
    formData.append('data_fim', data_fim);
    formData.append('docente_titular_id', titular_id);

    try {
        const response = await fetch('php/controllers/substituicao_api.php', { method: 'POST', body: formData });
        const data = await response.json();

        if (data.success) {
            Swal.fire({ title: 'Sucesso!', text: data.message, icon: 'success' }).then(() => location.reload());
        } else {
            Swal.fire('Erro', data.message, 'error');
            btn.disabled = false;
            btn.innerHTML = 'Confirmar Substituição';
        }
    } catch (e) {
        console.error(e);
        Swal.fire('Erro', 'Erro crítico.', 'error');
        btn.disabled = false;
        btn.innerHTML = 'Confirmar Substituição';
    }
}
