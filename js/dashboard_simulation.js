document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('dashboard-simulation-modal');
    const form = document.getElementById('simulation-form');
    const resultsDiv = document.getElementById('simulation-results-dashboard');
    const loading = document.getElementById('simulation-loading');

    if (!form) return;

    let currentSimPeriod = { inicio: '07:30', fim: '11:30', label: 'Manhã' };
    let simData = { docentes: [], ambientes: [] };
    let lastSimParams = { start: '', end: '', dias: [] };
    let selectedDocente = null;
    let selectedAmbiente = null;

    window.openSimulationModal = () => {
        modal.classList.add('active');
        resultsDiv.style.display = 'none';
        if (!document.querySelector('.sim-period.active')) {
            const first = document.querySelector('.sim-period');
            if (first) first.click();
        }
    };

    window.closeSimulationModal = () => {
        modal.classList.remove('active');
    };

    // Period buttons logic
    document.querySelectorAll('.sim-period').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.sim-period').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentSimPeriod.inicio = this.dataset.inicio;
            currentSimPeriod.fim = this.dataset.fim;
            currentSimPeriod.label = this.textContent.trim();
        });
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        loading.style.display = 'block';
        resultsDiv.style.display = 'none';

        const formData = new FormData(form);
        const area = formData.get('sim_area');
        const cursoId = formData.get('sim_curso_id');
        const dias = formData.getAll('sim_dias[]');
        const start = formData.get('sim_data_inicio');
        const end = formData.get('sim_data_fim');

        lastSimParams = { start, end, dias };

        let url = `php/controllers/simulation_api.php?action=simulate_resources&sim_area=${encodeURIComponent(area)}&curso_id=${cursoId}&data_inicio=${start}&data_fim=${end}&h_start=${currentSimPeriod.inicio}&h_end=${currentSimPeriod.fim}`;
        dias.forEach(d => url += `&dias_semana[]=${encodeURIComponent(d)}`);

        try {
            const resp = await fetch(url);
            simData = await resp.json();

            if (simData.error) {
                alert(simData.error);
                return;
            }

            // Pick the best (most available = lowest occupancy, which is index 0 due to ASC sort)
            selectedDocente = simData.docentes && simData.docentes.length > 0 ? simData.docentes[0] : null;
            selectedAmbiente = simData.ambientes && simData.ambientes.length > 0 ? simData.ambientes[0] : null;

            renderSimulationResults();
            resultsDiv.style.display = 'block';
        } catch (err) {
            console.error(err);
            alert('Erro ao processar simulação estratégica.');
        } finally {
            loading.style.display = 'none';
        }
    });

    function renderSimulationResults() {
        const docList = document.getElementById('sim-list-docentes');
        const ambList = document.getElementById('sim-list-ambientes');

        // Render selected Docente
        if (selectedDocente) {
            renderSuggestion(docList, selectedDocente, 'docente');
        } else {
            docList.innerHTML = '<div style="padding: 30px; text-align:center; color: var(--text-muted);"><i class="fas fa-user-slash" style="font-size:2rem; opacity:0.3; display:block; margin-bottom:10px;"></i>Nenhum professor livre na área para este período.</div>';
        }

        // Render selected Ambiente
        if (selectedAmbiente) {
            renderSuggestion(ambList, selectedAmbiente, 'ambiente');
        } else {
            ambList.innerHTML = '<div style="padding: 30px; text-align:center; color: var(--text-muted);"><i class="fas fa-door-closed" style="font-size:2rem; opacity:0.3; display:block; margin-bottom:10px;"></i>Nenhum ambiente livre encontrado.</div>';
        }
    }

    function renderSuggestion(container, primary, type) {
        const allItems = type === 'docente' ? simData.docentes : simData.ambientes;
        const totalOthers = allItems.length - 1;

        container.innerHTML = `
            <div class="sim-resource-item item-suggestion-main" style="${type === 'ambiente' ? 'border-left-color: #2196f3 !important;' : ''}">
                ${getResourceItemHtml(primary, type, true)}
            </div>
            ${totalOthers > 0 ? `
                <div style="padding:10px; text-align:center;">
                    <button type="button" class="sim-btn-swap" onclick="openOutrosModal('${type}')">
                        <i class="fas fa-exchange-alt"></i> Outros (${totalOthers})
                    </button>
                </div>
            ` : ''}
        `;
    }

    function getResourceItemHtml(item, type, isPrimary = false) {
        const params = new URLSearchParams();
        const cursoId = document.getElementsByName('sim_curso_id')[0]?.value;
        if (cursoId) params.set('curso_id', cursoId);
        params.set(type === 'docente' ? 'docente_id' : 'ambiente_id', item.id);
        params.set('data_inicio', lastSimParams.start);
        params.set('data_fim', lastSimParams.end);
        params.set('periodo', currentSimPeriod.label);
        params.set('h_start', currentSimPeriod.inicio);
        params.set('h_end', currentSimPeriod.fim);
        lastSimParams.dias.forEach(d => params.append('dias_semana[]', d));

        const occ = item.occupancy_count || 0;
        const badgeClass = occ < 5 ? 'sim-badge-high' : (occ < 15 ? 'sim-badge-mid' : 'sim-badge-low');
        const badgeLabel = occ === 0 ? 'Livre Total' : `${occ} dias ocup.`;

        return `
            <div style="flex:1;">
                <div style="display:flex; align-items:center; gap:8px;">
                    <strong style="font-size: 0.95rem; color: var(--text-color);">${item.nome}</strong>
                    ${type === 'docente' ? `<span class="sim-badge-avail ${badgeClass}">${badgeLabel}</span>` : ''}
                </div>
                <small style="color: var(--text-muted);">${item.area_conhecimento || item.tipo || 'Recurso'}</small>
            </div>
            <div style="display:flex; gap:6px;">
                ${!isPrimary ? `
                    <button type="button" class="btn btn-primary" style="padding: 5px 10px; font-size: 0.75rem; background: #1565c0; border-radius: 6px;"
                        onclick="selectResource('${type}', '${item.id}')">
                        <i class="fas fa-check"></i> Selecionar
                    </button>
                ` : ''}
                <a href="php/views/planejamento.php?${params.toString()}" class="btn btn-primary" style="padding: 5px 10px; font-size: 0.75rem; background: #2e7d32; border-radius: 6px;">
                    <i class="fas fa-bolt"></i> Agendar
                </a>
            </div>
        `;
    }

    // ========== Select Resource (swap the primary) ==========
    window.selectResource = function (type, itemId) {
        const allItems = type === 'docente' ? simData.docentes : simData.ambientes;
        const found = allItems.find(i => String(i.id) === String(itemId));
        if (!found) return;

        if (type === 'docente') {
            selectedDocente = found;
        } else {
            selectedAmbiente = found;
        }

        // Close the Outros modal and re-render
        closeOutrosModal();
        renderSimulationResults();
    };

    // ========== "Outros" Modal Logic ==========
    const outrosModal = document.getElementById('modal-selecionar-outros');
    let outrosType = 'docente';

    window.openOutrosModal = function (type) {
        outrosType = type;
        const titleEl = document.getElementById('outros-modal-title');
        const areaSelect = document.getElementById('outros-filtro-area');
        const ordemSelect = document.getElementById('outros-filtro-ordem');

        if (type === 'docente') {
            titleEl.textContent = 'Selecionar Outro Professor';
            areaSelect.style.display = '';
            ordemSelect.style.display = '';
            ordemSelect.innerHTML = `
                <option value="mais_livre">Maior Disponibilidade</option>
                <option value="menos_livre">Menor Disponibilidade</option>
            `;
        } else {
            titleEl.textContent = 'Selecionar Outro Ambiente';
            areaSelect.style.display = 'none'; // Hide area for environments
            ordemSelect.innerHTML = '<option value="nome">Ordenar por Nome</option>';
        }

        document.getElementById('outros-filtro-nome').value = '';
        if (areaSelect) areaSelect.value = '';

        renderOutrosList();
        outrosModal.classList.add('active');
    };

    window.closeOutrosModal = function () {
        outrosModal.classList.remove('active');
    };

    if (outrosModal) {
        let outrosModalClickStart = null;
        outrosModal.addEventListener('mousedown', e => outrosModalClickStart = e.target);
        outrosModal.addEventListener('click', e => {
            if (e.target === outrosModal && e.target === outrosModalClickStart) closeOutrosModal();
        });
    }

    // Filter listeners
    ['outros-filtro-nome', 'outros-filtro-area', 'outros-filtro-ordem'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', renderOutrosList);
        if (el) el.addEventListener('change', renderOutrosList);
    });

    function renderOutrosList() {
        const container = document.getElementById('outros-lista');
        const filterName = (document.getElementById('outros-filtro-nome')?.value || '').toLowerCase();
        const filterArea = document.getElementById('outros-filtro-area')?.value || '';
        const filterOrder = document.getElementById('outros-filtro-ordem')?.value || 'mais_livre';

        // Show ALL items except the currently selected one
        const currentSelectedId = outrosType === 'docente'
            ? (selectedDocente?.id || null)
            : (selectedAmbiente?.id || null);

        let items = (outrosType === 'docente'
            ? [...(simData.docentes || [])]
            : [...(simData.ambientes || [])]
        ).filter(i => String(i.id) !== String(currentSelectedId));

        // Apply name filter
        if (filterName) {
            items = items.filter(i => (i.nome || '').toLowerCase().includes(filterName));
        }

        // Apply area filter (for docentes)
        if (filterArea && outrosType === 'docente') {
            items = items.filter(i => {
                const area = i.area_conhecimento || '';
                return area === filterArea;
            });
        }

        // Apply area/tipo filter (for ambientes — use the name field for filtering)
        if (filterArea && outrosType === 'ambiente') {
            items = items.filter(i => {
                const tipo = i.tipo || '';
                return tipo.toLowerCase().includes(filterArea.toLowerCase());
            });
        }

        // Apply sort
        if (outrosType === 'docente') {
            if (filterOrder === 'menos_livre') {
                items.sort((a, b) => (b.occupancy_count || 0) - (a.occupancy_count || 0));
            } else {
                items.sort((a, b) => (a.occupancy_count || 0) - (b.occupancy_count || 0));
            }
        }

        if (items.length === 0) {
            container.innerHTML = '<div style="padding:30px; text-align:center; color: var(--text-muted);"><i class="fas fa-inbox" style="font-size:2rem; opacity:0.3; display:block; margin-bottom:10px;"></i>Nenhum resultado encontrado.</div>';
            return;
        }

        container.innerHTML = items.map(item => `
            <div class="sim-resource-item" style="margin-bottom: 8px; animation: fadeIn 0.2s;">
                ${getResourceItemHtml(item, outrosType, false)}
            </div>
        `).join('');
    }
});
