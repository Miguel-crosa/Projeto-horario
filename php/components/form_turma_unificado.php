<?php
/**
 * FORMULÁRIO UNIFICADO DE TURMAS E RESERVAS
 * Este componente contém o HTML e o JavaScript consolidado para cadastro e edição.
 */
// Impede o carregamento duplicado que causa conflitos de ID e trava o JavaScript
if (defined('UNIFIED_FORM_LOADED')) return;
define('UNIFIED_FORM_LOADED', true);

// Se IDs ou outros parâmetros não estiverem definidos, define fallbacks sensatos
$id = $id ?? null;
$turma = $turma ?? [
    'curso_id' => '', 'ambiente_id' => '', 'periodo' => '', 'data_inicio' => '', 'data_fim' => '',
    'tipo' => 'Presencial', 'sigla' => '', 'vagas' => '32', 'docente_id1' => '', 'docente_id2' => '',
    'docente_id3' => '', 'docente_id4' => '', 'local' => 'Sede', 'dias_semana' => '',
    'horario_inicio' => '07:30', 'horario_fim' => '11:30',
    'tipo_custeio' => 'Gratuidade', 'previsao_despesa' => 0, 'valor_turma' => 0,
    'numero_proposta' => '', 'tipo_atendimento' => 'Balcão', 'parceiro' => '', 'contato_parceiro' => ''
];
$dias_selecionados = !empty($turma['dias_semana']) ? explode(',', $turma['dias_semana']) : [];

// Variáveis globais de dados (Fallbacks para quando incluído no modal)
if (!isset($grouped_cursos_ag) && isset($conn)) {
    $cursos_ag_fetch = mysqli_fetch_all(mysqli_query($conn, "SELECT id, nome, carga_horaria_total, area, tipo FROM curso ORDER BY area ASC, nome ASC"), MYSQLI_ASSOC);
    $grouped_cursos_ag = [];
    foreach ($cursos_ag_fetch as $c_ag) {
        $area_ag = $c_ag['area'] ?: 'Outros';
        $grouped_cursos_ag[$area_ag][] = $c_ag;
    }
}
if (!isset($ambientes) && isset($conn)) {
    $ambientes = mysqli_fetch_all(mysqli_query($conn, "SELECT id, nome FROM ambiente ORDER BY nome ASC"), MYSQLI_ASSOC);
}
if (!isset($docentes) && isset($conn)) {
    $res_doc = mysqli_query($conn, "SELECT id, nome, area_conhecimento FROM docente ORDER BY nome ASC");
    $docentes = $res_doc ? mysqli_fetch_all($res_doc, MYSQLI_ASSOC) : [];
}
if (!isset($feriados_data) && isset($conn)) {
    $feriados_res = mysqli_query($conn, "
        SELECT date AS data_inicio, COALESCE(end_date, date) AS data_fim, NULL AS docente_id, 'HOLIDAY' AS tipo FROM holidays
        UNION ALL
        SELECT start_date AS data_inicio, end_date AS data_fim, teacher_id AS docente_id, 'VACATION' AS tipo FROM vacations
        UNION ALL
        SELECT data AS data_inicio, data AS data_fim, docente_id, 'BLOCK' AS tipo FROM agenda WHERE status = 'RESERVADO' AND turma_id IS NULL
    ");
    $feriados_data = $feriados_res ? mysqli_fetch_all($feriados_res, MYSQLI_ASSOC) : [];
}
?>

<div id="form-alert-container" class="alert alert-danger" style="display: none; margin-bottom: 20px; border-left: 5px solid #dc3545; background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px;">
    <i class="fas fa-exclamation-triangle"></i> <strong>Alerta:</strong> 
    <span id="form-alert-msg-unified"></span>
</div>

<form action="../controllers/turmas_process.php" method="POST" id="turma-form-unified">
    <input type="hidden" name="id" id="unified-id" value="<?= $id ?>">
    <input type="hidden" name="ajax" value="1">
    <input type="hidden" name="is_reserva" id="unified-is-reserva" value="<?= isCRI() ? '1' : '0' ?>">

    <div class="form-grid">
        <div class="form-group" id="grp-unified-curso">
            <label class="form-label">CURSO</label>
            <select name="curso_id" class="form-input" required id="curso-select-unified">
                <option value="" data-ch="0">Selecione o curso...</option>
                <?php foreach ($grouped_cursos_ag as $area_label => $lista_ag): ?>
                    <optgroup label="<?= htmlspecialchars(mb_strtoupper($area_label, 'UTF-8')) ?>" data-area="<?= htmlspecialchars($area_label) ?>">
                        <?php foreach ($lista_ag as $c): ?>
                            <?php $tipo_label = !empty($c['tipo']) ? " ( {$c['tipo']} )" : ""; ?>
                            <option value="<?= $c['id'] ?>" data-ch="<?= $c['carga_horaria_total'] ?>" <?= $turma['curso_id'] == $c['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nome']) ?><?= $tipo_label ?> - <?= $c['carga_horaria_total'] ?>h
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">SIGLA DA TURMA <span style="font-weight: normal; opacity: 0.7;">(Opcional)</span></label>
            <input type="text" name="sigla" id="unified-sigla" class="form-input" value="<?= htmlspecialchars($turma['sigla']) ?>"
                placeholder="Ex: TI-2026-123">
        </div>
    </div>
    
    <div class="form-grid">
        <div class="form-group">
            <label class="form-label">Vagas</label>
            <input type="number" name="vagas" id="unified-vagas" class="form-input" value="<?= $turma['vagas'] ?>"
                placeholder="Ex: 32">
        </div>
        <div class="form-group">
            <label class="form-label">Local</label>
            <input type="text" name="local" id="unified-local" class="form-input" value="<?= htmlspecialchars($turma['local']) ?>"
                placeholder="Ex: Unidade SJC">
        </div>
    </div>

    <!-- Custeio e Proposta -->
    <div id="unified-financial-section">
        <div class="form-grid" style="margin-top: 15px; border-top: 1px solid var(--border-color); padding-top: 15px;">
            <div class="form-group">
                <label class="form-label"><i class="fas fa-hand-holding-usd"></i> Tipo de Custeio</label>
                <select name="tipo_custeio" class="form-input" id="tipo-custeio-unified" onchange="toggleCusteioFieldsUnified()">
                    <option value="Gratuidade" <?= $turma['tipo_custeio'] == 'Gratuidade' ? 'selected' : '' ?>>Gratuidade</option>
                    <option value="Ressarcido" <?= $turma['tipo_custeio'] == 'Ressarcido' ? 'selected' : '' ?>>Ressarcido</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><i class="fas fa-money-bill-wave"></i> Previsão de Despesas</label>
                <input type="number" step="0.01" name="previsao_despesa" id="unified-previsao-despesa" class="form-input" value="<?= $turma['previsao_despesa'] ?>" placeholder="Ex: 500.00">
            </div>
        </div>

        <div class="form-group" id="group-valor-turma-unified" style="margin-top: 15px; <?= $turma['tipo_custeio'] == 'Ressarcido' ? '' : 'display: none;' ?>">
            <label class="form-label" style="color: var(--primary-red); font-weight: 700;">VALOR DA TURMA (ARRECADADO)</label>
            <div style="position: relative;">
                <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-weight: 700; color: var(--text-muted);">R$</span>
                <input type="number" step="0.01" name="valor_turma" id="unified-valor-turma" class="form-input" value="<?= $turma['valor_turma'] ?>" placeholder="0.00" style="padding-left: 40px;">
            </div>
        </div>

        <div class="form-grid" style="margin-top: 15px; border-top: 1px solid var(--border-color); padding-top: 15px;">
            <div class="form-group" id="group-numero-proposta-unified">
                <label class="form-label"><i class="fas fa-file-contract"></i> Nº da Proposta</label>
                <input type="text" name="numero_proposta" id="unified-numero-proposta" class="form-input" value="<?= htmlspecialchars($turma['numero_proposta'] ?? '') ?>" placeholder="Ex: 123/2026">
            </div>
            <div class="form-group">
                <label class="form-label"><i class="fas fa-users-cog"></i> Tipo de Atendimento</label>
                <div style="display: flex; gap: 15px; align-items: center; margin-top: 8px;">
                    <label style="display: flex; align-items: center; gap: 5px; cursor: pointer; font-size: 0.9rem;">
                        <input type="radio" name="tipo_atendimento" value="Empresa" <?= ($turma['tipo_atendimento'] ?? '') == 'Empresa' ? 'checked' : '' ?> onchange="toggleAtendimentoFieldsUnified()"> Empresa
                    </label>
                    <label style="display: flex; align-items: center; gap: 5px; cursor: pointer; font-size: 0.9rem;">
                        <input type="radio" name="tipo_atendimento" value="Entidade" <?= ($turma['tipo_atendimento'] ?? '') == 'Entidade' ? 'checked' : '' ?> onchange="toggleAtendimentoFieldsUnified()"> Entidade
                    </label>
                    <label style="display: flex; align-items: center; gap: 5px; cursor: pointer; font-size: 0.9rem;">
                        <input type="radio" name="tipo_atendimento" value="Balcão" <?= ($turma['tipo_atendimento'] ?? 'Balcão') == 'Balcão' ? 'checked' : '' ?> onchange="toggleAtendimentoFieldsUnified()"> Balcão
                    </label>
                </div>
            </div>
        </div>

        <div class="form-grid" id="group-parceria-detalhes-unified" style="margin-top: 15px; display: none;">
            <div class="form-group">
                <label class="form-label"><i class="fas fa-handshake"></i> Parceiro</label>
                <input type="text" name="parceiro" id="unified-parceiro" class="form-input" value="<?= htmlspecialchars($turma['parceiro'] ?? '') ?>" placeholder="Ex: Empresa Exemplo">
            </div>
            <div class="form-group">
                <label class="form-label"><i class="fas fa-address-book"></i> Contato do Parceiro</label>
                <input type="text" name="contato_parceiro" id="unified-contato-parceiro" class="form-input" value="<?= htmlspecialchars($turma['contato_parceiro'] ?? '') ?>" placeholder="Ex: João - (11) 9999-9999">
            </div>
        </div>
    </div>
    
    <div class="form-group" style="margin-top: 15px;">
        <label class="form-label">Ambiente</label>
        <select name="ambiente_id" id="ambiente-select-unified" class="form-input" required onchange="toggleAmbienteOutroUnified()">
            <option value="">Selecione o ambiente...</option>
            <?php foreach ($ambientes as $a): 
                if (trim(strtolower($a['nome'])) === 'outros' || trim(strtolower($a['nome'])) === 'outro') continue;
            ?>
                <option value="<?= $a['id'] ?>" <?= $turma['ambiente_id'] == $a['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($a['nome']) ?>
                </option>
            <?php endforeach; ?>
            <option value="outro" <?= ($turma['ambiente_id'] === null && !empty($turma['local']) && $turma['local'] !== 'Sede') ? 'selected' : '' ?>>Outros (Especificar)</option>
        </select>
    </div>

    <div class="form-group" id="ambiente-outro-container-unified" style="margin-top: 10px; display: <?= ($turma['ambiente_id'] === null && !empty($turma['local']) && $turma['local'] !== 'Sede') ? 'block' : 'none' ?>;">
        <label class="form-label"><i class="fas fa-map-marker-alt"></i> Nome do Ambiente / Local</label>
        <input type="text" name="local" id="local-manual-unified" class="form-input" value="<?= htmlspecialchars($turma['local'] ?? '') ?>" placeholder="Ex: Auditório Externo, Sala de Reuniões, etc.">
    </div>

    <div class="form-group" style="margin-top: 20px;">
        <label class="form-label" style="font-size: 1.1rem; color: var(--primary-red); font-weight: 800; border-bottom: 2px solid rgba(237,28,36,0.1); padding-bottom: 8px; margin-bottom: 15px;">
            <i class="fas fa-chalkboard-teacher"></i> Corpo Docente (Até 4)
        </label>
        
        <div id="selected-docentes-container-unified" class="docentes-list-v4">
            <!-- Preenchido via JavaScript -->
        </div>

        <button type="button" class="add-docente-btn-v4" id="btn-abrir-modal-docentes-unified">
            <i class="fas fa-plus-circle"></i> SELECIONAR DOCENTE
        </button>
        <div id="docente-error-unified" style="color: var(--primary-red); font-size: 0.85rem; margin-top: 8px; display: none; font-weight: 600;">
            <i class="fas fa-exclamation-circle"></i> Pelo menos um docente deve ser vinculado.
        </div>
        
        <!-- Campos Ocultos para o Formulário -->
        <input type="hidden" name="docente_id1" id="hidden-docente-1-unified" value="<?= $turma['docente_id1'] ?>">
        <input type="hidden" name="docente_id2" id="hidden-docente-2-unified" value="<?= $turma['docente_id2'] ?>">
        <input type="hidden" name="docente_id3" id="hidden-docente-3-unified" value="<?= $turma['docente_id3'] ?>">
        <input type="hidden" name="docente_id4" id="hidden-docente-4-unified" value="<?= $turma['docente_id4'] ?>">
    </div>

    <div class="form-group">
        <label class="form-label">Período</label>
        <select name="periodo" class="form-input" required id="periodo-select-unified">
            <option value="">Selecione o período...</option>
            <option value="Manhã" <?= $turma['periodo'] == 'Manhã' ? 'selected' : '' ?>>Manhã (07:30 - 11:30)</option>
            <option value="Tarde" <?= $turma['periodo'] == 'Tarde' ? 'selected' : '' ?>>Tarde (13:30 - 17:30)</option>
            <option value="Noite" <?= $turma['periodo'] == 'Noite' ? 'selected' : '' ?>>Noite (19:00 - 23:00)</option>
            <option value="Integral" <?= $turma['periodo'] == 'Integral' ? 'selected' : '' ?>>Integral (07:30 - 17:30)</option>
        </select>
    </div>

    <div class="form-grid">
        <div class="form-group">
            <label class="form-label">Horário Início</label>
            <input type="time" name="horario_inicio" id="horario_inicio_unified" class="form-input"
                value="<?= substr($turma['horario_inicio'] ?? '07:30', 0, 5) ?>" required>
        </div>
        <div class="form-group">
            <label class="form-label">Horário Até</label>
            <input type="time" name="horario_fim" id="horario_fim_unified" class="form-input"
                value="<?= substr($turma['horario_fim'] ?? '11:30', 0, 5) ?>" required>
        </div>
    </div>

    <input type="hidden" name="tipo" value="Presencial">

    <div class="form-group" style="margin-top: 15px;">
        <label class="form-label">Dias da Semana</label>
        <div class="dias-checkboxes" id="dias-semana-container-unified">
            <?php
            $dias = ['Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
            foreach ($dias as $dia): ?>
                <label class="dia-checkbox-label" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 10px 5px; min-width: 45px; height: 45px;">
                    <input type="checkbox" name="dias_semana[]" value="<?= $dia ?>" 
                        <?= in_array($dia, $dias_selecionados) ? 'checked' : '' ?>
                        onchange="calcularDataFimUnified()" style="margin-bottom: 3px;">
                    <span class="dia-checkbox-text" style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; width: 33px; overflow: hidden; white-space: nowrap; text-align: center; display: block;"><?= $dia ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="form-grid" style="margin-top: 15px;">
        <div>
            <label class="form-label">Data Início</label>
            <input type="date" name="data_inicio" class="form-input" id="data-inicio-unified"
                value="<?= $turma['data_inicio'] ?>" required>
        </div>
        <div>
            <label class="form-label">Data Fim</label>
            <div style="display: flex; align-items: center; gap: 10px;">
                <input type="date" name="data_fim" class="form-input" id="data-fim-unified"
                    value="<?= $turma['data_fim'] ?>" readonly
                    style="background: var(--bg-hover); cursor: not-allowed; flex: 1;">
                <label style="display: flex; align-items: center; gap: 5px; font-size: 0.8rem; white-space: nowrap; cursor: pointer;">
                    <input type="checkbox" id="calc-auto-unified" checked style="width: 16px; height: 16px;">
                    Auto
                </label>
            </div>
        </div>
    </div>
    <div id="data-fim-info-unified" style="font-size: 0.8rem; color: var(--text-muted); margin-top: 5px;"></div>

    <div class="sim-toggle-container-unified" id="sim-toggle-container-unified" style="margin-top: 20px; padding: 12px 15px; background: rgba(46, 125, 50, 0.05); border-radius: 10px; border: 1px solid rgba(46, 125, 50, 0.1);">
        <label class="sim-toggle-label" style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
            <input type="checkbox" name="validate_only" id="simulacao-toggle-unified" value="1" style="width: 18px; height: 18px;">
            <span class="sim-toggle-text" style="font-size: 0.9rem; font-weight: 600; color: #2e7d32;">
                <i class="fas fa-vial"></i> Modo Simulação (Verificar Conflitos e Disponibilidade)
            </span>
        </label>
        <div id="simulacao-feedback-unified" style="margin-top: 12px; display: none; padding: 12px; border-radius: 8px; font-size: 0.9rem; font-weight: 600; text-align: center; border: 1px solid transparent;"></div>
    </div>

    <div class="form-actions" style="margin-top: 30px;">
        <button type="submit" id="btn-salvar-unified" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 15px; font-size: 1.05rem;">
            <span id="btn-text-unified"><?= isCRI() ? 'Solicitar Reserva' : 'Salvar Registro' ?></span>
        </button>
    </div>
</form>

<script>
// --- Script Unificado Adaptado ---
(function() {
    // Garante que as variáveis sejam arrays mesmo que o PHP falhe
    const docentesData = <?= json_encode($docentes ?: [], JSON_UNESCAPED_UNICODE) ?> || [];
    const feriadosData = <?= json_encode($feriados_data ?: [], JSON_UNESCAPED_UNICODE) ?> || [];
    let selectedDocentes = [];

    let modalDoc, searchInput, resultsContainer, areaFilter;

    function normalizeString(str) {
        if (!str) return '';
        return str.toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
    }

    function initSelectedDocentes() {
        selectedDocentes = [];
        for (let i = 1; i <= 4; i++) {
            const el = document.getElementById(`hidden-docente-${i}-unified`);
            if (el && el.value && el.value !== "" && el.value !== "NULL" && el.value !== "0") {
                const docId = String(el.value);
                const doc = Array.isArray(docentesData) ? docentesData.find(d => String(d.id) === docId) : null;
                if (doc) {
                    // Evitar duplicatas na reconstrução inicial
                    if (!selectedDocentes.find(sd => String(sd.id) === docId)) {
                        selectedDocentes.push(doc);
                    }
                }
            }
        }
        const selectCurso = document.getElementById('curso-select-unified');
        renderSelectedDocentes(selectCurso ? selectCurso.value : null);
    }

    window.renderSelectedDocentes = function(forcedCursoId = null) {
        const container = document.getElementById('selected-docentes-container-unified');
        if (!container) return;
        container.innerHTML = '';

        selectedDocentes.forEach((doc, index) => {
            const card = document.createElement('div');
            card.className = 'docente-card-v4';
            card.innerHTML = `
                <div class="docente-card-info">
                    <div class="docente-card-avatar"><i class="fas fa-user"></i></div>
                    <div>
                        <span class="docente-card-name">${doc.nome}</span>
                        <span class="docente-card-area">${doc.area_conhecimento || 'Área não definida'}</span>
                    </div>
                </div>
                <button type="button" class="docente-card-remove" onclick="removeDocenteUnified(${index})" title="Remover Docente">
                    <i class="fas fa-trash-alt"></i>
                </button>
            `;
            container.appendChild(card);
        });

        for (let i = 1; i <= 4; i++) {
            const el = document.getElementById(`hidden-docente-${i}-unified`);
            if (el) el.value = selectedDocentes[i - 1] ? selectedDocentes[i - 1].id : '';
        }

        const btnAdd = document.getElementById('btn-abrir-modal-docentes-unified');
        if (btnAdd) {
            if (selectedDocentes.length >= 4) {
                btnAdd.style.display = 'none';
            } else {
                btnAdd.style.display = 'flex';
                btnAdd.innerHTML = `<i class="fas fa-plus-circle"></i> ADICIONAR DOCENTE (${selectedDocentes.length}/4)`;
            }
        }
    }

    window.removeDocenteUnified = function(index) {
        selectedDocentes.splice(index, 1);
        const selectCurso = document.getElementById('curso-select-unified');
        renderSelectedDocentes(selectCurso ? selectCurso.value : null);
        calcularDataFimUnified();
    }

    function renderModalResultsUnified() {
        if (!resultsContainer || !searchInput) return;
        const query = searchInput.value.toLowerCase().trim();
        const area = areaFilter ? areaFilter.value : '';

        let filtered = docentesData.filter(d => {
            const matchesName = !query || normalizeString(d.nome).includes(normalizeString(query));
            const matchesArea = !area || normalizeString(d.area_conhecimento) === normalizeString(area);
            const alreadySelected = selectedDocentes.some(sd => String(sd.id) === String(d.id));
            return matchesName && matchesArea && !alreadySelected;
        });

        if (filtered.length === 0) {
            resultsContainer.innerHTML = '<div style="text-align:center; padding:20px; color:#888;">Nenhum professor encontrado.</div>';
            return;
        }

        resultsContainer.innerHTML = filtered.map(d => `
            <div class="prof-result-item" data-id="${d.id}" 
                style="padding: 12px 15px; border-bottom: 1px solid var(--border-color); cursor: pointer; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <strong>${d.nome}</strong><br>
                    <small style="color: #888; font-size: 0.75rem;">${d.area_conhecimento || 'Outros'}</small>
                </div>
                <i class="fas fa-plus-circle" style="color: var(--primary-red); font-size: 1.1rem;"></i>
            </div>
        `).join('');

        resultsContainer.querySelectorAll('.prof-result-item').forEach(item => {
            item.onclick = function() {
                const id = this.dataset.id;
                const doc = docentesData.find(d => String(d.id) === String(id));
                if (doc && selectedDocentes.length < 4) {
                    selectedDocentes.push(doc);
                    const optSelect = document.getElementById('curso-select-unified');
                    renderSelectedDocentes(optSelect ? optSelect.value : null);
                    if (modalDoc) modalDoc.classList.remove('active');
                    calcularDataFimUnified();
                }
            };
        });
    }

    window.toggleCusteioFieldsUnified = function() {
        const typeEl = document.getElementById('tipo-custeio-unified');
        const group = document.getElementById('group-valor-turma-unified');
        if (typeEl && group) {
            group.style.display = (typeEl.value === 'Ressarcido') ? 'block' : 'none';
        }
    }

    window.toggleAtendimentoFieldsUnified = function() {
        const selected = document.querySelector('input[name="tipo_atendimento"]:checked')?.value;
        const groupProposta = document.getElementById('group-numero-proposta-unified');
        const groupParceria = document.getElementById('group-parceria-detalhes-unified');
        
        if (selected === 'Balcão') {
            if (groupProposta) groupProposta.style.display = 'none';
            if (groupParceria) groupParceria.style.display = 'none';
        } else {
            if (groupProposta) groupProposta.style.display = 'block';
            if (groupParceria) groupParceria.style.display = 'grid';
        }
    }

    window.calcularDataFimUnified = function() {
        const cursoSelect = document.getElementById('curso-select-unified');
        const periodoSelect = document.getElementById('periodo-select-unified');
        const dataInicio = document.getElementById('data-inicio-unified');
        const dataFim = document.getElementById('data-fim-unified');
        const infoEl = document.getElementById('data-fim-info-unified');
        const btnSalvar = document.getElementById('btn-salvar-unified');

        if (!cursoSelect || !periodoSelect || !dataInicio) return;

        const opt = cursoSelect.options[cursoSelect.selectedIndex];
        const ch = parseInt(opt?.dataset.ch) || 0;
        const periodo = periodoSelect.value;
        const inicio = dataInicio.value;
        const diasChecked = Array.from(document.querySelectorAll('#dias-semana-container-unified input[name="dias_semana[]"]:checked')).map(cb => cb.value);

        const h_ini = document.getElementById('horario_inicio_unified');
        const h_fim = document.getElementById('horario_fim_unified');
        if (periodoSelect.dataset.lastPeriod !== periodo) {
            const periodDefaults = {
                'Manhã': ['07:30', '11:30'],
                'Tarde': ['13:30', '17:30'],
                'Noite': ['19:00', '23:00'],
                'Integral': ['07:30', '17:30']
            };
            if (periodDefaults[periodo]) {
                if (h_ini) h_ini.value = periodDefaults[periodo][0];
                if (h_fim) h_fim.value = periodDefaults[periodo][1];
            }
            periodoSelect.dataset.lastPeriod = periodo;
        }

        // TRAVA RIGOROSA: Noite não pode passar das 23h
        if (periodo === 'Noite' && h_fim && h_fim.value > '23:00') {
            h_fim.value = '23:00';
        }

        // TRAVA RIGOROSA: Integral não pode passar das 17:30
        if (periodo === 'Integral' && h_fim && h_fim.value > '17:30') {
            h_fim.value = '17:30';
        }

        if (!ch || !periodo || !inicio || diasChecked.length === 0) {
            if (dataFim) dataFim.value = '';
            if (infoEl) infoEl.textContent = '';
            return;
        }

        let horasPorDia = 0;
        if (h_ini && h_fim && h_ini.value && h_fim.value) {
            const [h1, m1] = h_ini.value.split(':').map(Number);
            const [h2, m2] = h_fim.value.split(':').map(Number);
            horasPorDia = ((h2 * 60 + m2) - (h1 * 60 + m1)) / 60;
            
            // Se for Integral, subtrai 1h de almoço se a duração for superior a 4h
            if (periodo === 'Integral' && horasPorDia > 4) {
                horasPorDia -= 1;
            }
        }
        if (horasPorDia <= 0) horasPorDia = (periodo === 'Integral' ? 8 : 4);

        const totalDias = Math.ceil(ch / horasPorDia);
        const mapIndices = {'Domingo':0,'Segunda-feira':1,'Terça-feira':2,'Quarta-feira':3,'Quinta-feira':4,'Sexta-feira':5,'Sábado':6};
        const diasIndices = diasChecked.map(d => mapIndices[d]).filter(i => i !== undefined);

        let date = new Date(inicio + 'T12:00:00');
        let count = 0;
        const currentDocIds = selectedDocentes.map(d => String(d.id));

        for (let safety = 0; safety < 2000 && count < totalDias; safety++) {
            const dow = date.getDay();
            const dateISO = date.toISOString().slice(0, 10);
            if (diasIndices.includes(dow)) {
                const isBlocked = feriadosData.some(f => {
                    const isDateMatch = (dateISO === f.data_inicio) || (dateISO >= f.data_inicio && dateISO <= f.data_fim);
                    const isDocMatch = !f.docente_id || currentDocIds.includes(String(f.docente_id));
                    return isDateMatch && isDocMatch;
                });
                if (!isBlocked) count++;
            }
            if (count >= totalDias) break;
            date.setDate(date.getDate() + 1);
        }

        if (dataFim) {
            dataFim.value = date.toISOString().slice(0, 10);
        }
        if (infoEl) {
            infoEl.innerHTML = `<i class="fas fa-info-circle"></i> ${ch}h ÷ ${horasPorDia.toFixed(2)}h/dia = <strong>${totalDias} dias de aula</strong>.`;
        }

        checkAvailabilityUnified();
    }

    async function checkAvailabilityUnified() {
        const form = document.getElementById('turma-form-unified');
        const alertBox = document.getElementById('form-alert-container');
        const msgEl = document.getElementById('form-alert-msg-unified');
        const btnSalvar = document.getElementById('btn-salvar-unified');

        const inicio = document.getElementById('data-inicio-unified').value;
        const periodo = document.getElementById('periodo-select-unified').value;
        const dias = document.querySelectorAll('#dias-semana-container-unified input[name="dias_semana[]"]:checked');

        if (!inicio || !periodo || dias.length === 0 || selectedDocentes.length === 0) {
            if(btnSalvar) btnSalvar.disabled = false;
            return;
        }

        try {
            const formData = new FormData(form);
            formData.append('validate_only', '1');
            const response = await fetch(form.action, { method: 'POST', body: formData });
            
            if (!response.ok) return;
            const text = await response.text();
            let result;
            try { result = JSON.parse(text); } catch (e) { return; }

            if (!result.success) {
                if(btnSalvar) btnSalvar.disabled = true;
                if(alertBox && msgEl) {
                    msgEl.innerText = result.message;
                    alertBox.style.display = 'block';
                }
            } else {
                if(btnSalvar) btnSalvar.disabled = false;
                if(alertBox) alertBox.style.display = 'none';
            }
        } catch (err) {
            console.error(err);
        }
    }

    function initUnifiedComponent() {
        modalDoc = document.getElementById('modal-selecionar-professor-unified');
        searchInput = document.getElementById('prof-search-input-unified');
        resultsContainer = document.getElementById('prof-search-results-unified');
        areaFilter = document.getElementById('prof-area-filter-unified');

        const btnAbrir = document.getElementById('btn-abrir-modal-docentes-unified');
        if (btnAbrir) {
            btnAbrir.onclick = () => {
                if (modalDoc) {
                    modalDoc.classList.add('active');
                    if (searchInput) {
                        searchInput.value = '';
                        renderModalResultsUnified();
                        setTimeout(() => searchInput.focus(), 100);
                    }
                }
            };
        }

        if (searchInput) {
            searchInput.oninput = renderModalResultsUnified;
        }
        if (areaFilter) {
            areaFilter.onchange = renderModalResultsUnified;
        }

        const fields = ['curso-select-unified', 'periodo-select-unified', 'data-inicio-unified', 'horario_inicio_unified', 'horario_fim_unified'];
        fields.forEach(id => {
            const el = document.getElementById(id);
            if(el) el.addEventListener('change', calcularDataFimUnified);
        });
        document.querySelectorAll('#dias-semana-container-unified input[name="dias_semana[]"]').forEach(cb => {
            cb.addEventListener('change', calcularDataFimUnified);
        });

        document.getElementById('calc-auto-unified')?.addEventListener('change', function() {
            const df = document.getElementById('data-fim-unified');
            df.readOnly = this.checked;
            df.style.background = this.checked ? 'var(--bg-hover)' : 'var(--bg-card)';
            df.style.cursor = this.checked ? 'not-allowed' : 'text';
            if(!this.checked) df.required = true;
        });

        // Lógica de Ambiente "Outros"
        window.toggleAmbienteOutroUnified = function() {
            const select = document.getElementById('ambiente-select-unified');
            const container = document.getElementById('ambiente-outro-container-unified');
            const input = document.getElementById('local-manual-unified');
            if (select && container) {
                if (select.value === 'outro') {
                    container.style.display = 'block';
                    if (input) input.focus();
                } else {
                    container.style.display = 'none';
                }
            }
        };

        const ambienteSelect = document.getElementById('ambiente-select-unified');
        if (ambienteSelect) {
            ambienteSelect.addEventListener('change', window.toggleAmbienteOutroUnified);
            // Trigger inicial
            window.toggleAmbienteOutroUnified();
        }

        const form = document.getElementById('turma-form-unified');
        if (form) {
            form.onsubmit = async function(e) {
                e.preventDefault();
                const isSimulation = document.getElementById('simulacao-toggle-unified')?.checked;
                const btn = document.getElementById('btn-salvar-unified');
                const alertBox = document.getElementById('form-alert-container');
                const msgEl = document.getElementById('form-alert-msg-unified');
                const feedbackEl = document.getElementById('simulacao-feedback-unified');

                // Limpar estados anteriores
                if(alertBox) alertBox.style.display = 'none';
                if(feedbackEl) {
                    feedbackEl.style.display = 'none';
                    feedbackEl.style.background = '';
                    feedbackEl.style.color = '';
                    feedbackEl.style.borderColor = '';
                }

                if (selectedDocentes.length === 0) {
                    const docErr = document.getElementById('docente-error-unified');
                    if(docErr) docErr.style.display = 'block';
                    
                    if (isSimulation && feedbackEl) {
                        feedbackEl.innerHTML = '<i class="fas fa-user-times"></i> Erro: Selecione pelo menos um docente.';
                        feedbackEl.style.display = 'block';
                        feedbackEl.style.background = 'rgba(211, 47, 47, 0.1)';
                        feedbackEl.style.color = '#d32f2f';
                        feedbackEl.style.borderColor = 'rgba(211, 47, 47, 0.2)';
                    } else if(alertBox && msgEl) {
                        msgEl.innerText = 'Selecione pelo menos um docente.';
                        alertBox.style.display = 'block';
                    }
                    return;
                }

                try {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
                    
                    const fd = new FormData(form);
                    const response = await fetch(form.action, { method: 'POST', body: fd });
                    const responseText = await response.text();
                    
                    let result;
                    try {
                        result = JSON.parse(responseText);
                    } catch (parseError) {
                        console.error('Resposta não-JSON:', responseText);
                        btn.disabled = false;
                        btn.innerHTML = isSimulation ? '<i class="fas fa-check-double"></i> Validar Disponibilidade' : '<i class="fas fa-save"></i> SALVAR';
                        if (msgEl && alertBox) {
                            msgEl.innerHTML = 'Erro técnico (não-JSON):<br><small style="font-family:monospace; font-size:10px; display:block; max-height:100px; overflow:auto;">' + 
                                responseText.replace(/</g, "&lt;").substring(0, 500) + '...</small>';
                            alertBox.style.display = 'block';
                        }
                        return;
                    }

                    if (result.success) {
                        if (isSimulation) {
                            btn.disabled = false;
                            btn.innerHTML = 'Validar Novamente';
                            
                            if (feedbackEl) {
                                feedbackEl.innerHTML = '<i class="fas fa-check-circle"></i> Disponibilidade confirmada! Nenhum conflito encontrado.';
                                feedbackEl.style.display = 'block';
                                feedbackEl.style.background = 'rgba(46, 125, 50, 0.1)';
                                feedbackEl.style.color = '#2e7d32';
                                feedbackEl.style.borderColor = 'rgba(46, 125, 50, 0.2)';
                            }
                        } else {
                            if (window.showNotification) {
                                window.showNotification(result.message || 'Operação realizada com sucesso!', 'success');
                            }
                            
                            setTimeout(() => {
                                const modalParente = form.closest('.modal-overlay');
                                if (modalParente) {
                                    modalParente.classList.remove('active');
                                    location.reload();
                                } else if (result.redirect) {
                                    window.location.href = result.redirect;
                                } else {
                                    location.reload();
                                }
                            }, 1500);
                        }
                    } else {
                        btn.disabled = false;
                        btn.innerHTML = isSimulation ? 'Tentar Novamente' : 'Salvar Registro';
                        
                        if (isSimulation && feedbackEl) {
                            feedbackEl.innerHTML = `<i class="fas fa-exclamation-circle"></i> CONFLITO: ${result.message}`;
                            feedbackEl.style.display = 'block';
                            feedbackEl.style.background = 'rgba(211, 47, 47, 0.1)';
                            feedbackEl.style.color = '#d32f2f';
                            feedbackEl.style.borderColor = 'rgba(211, 47, 47, 0.2)';
                        } else if(alertBox && msgEl) {
                            msgEl.innerText = result.message;
                            alertBox.style.display = 'block';
                            alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    }
                } catch (err) {
                    console.error(err);
                    btn.disabled = false;
                    btn.innerHTML = 'Salvar Registro';
                    
                    if (alertBox && msgEl) {
                        msgEl.innerText = 'Erro de comunicação com o servidor.';
                        alertBox.style.display = 'block';
                    }
                }
            };
        }

        const periodoSelectInit = document.getElementById('periodo-select-unified');
        if (periodoSelectInit) {
            periodoSelectInit.dataset.lastPeriod = periodoSelectInit.value;
        }

        initSelectedDocentes();
        if (window.toggleAtendimentoFieldsUnified) window.toggleAtendimentoFieldsUnified();
        if (window.calcularDataFimUnified) window.calcularDataFimUnified();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initUnifiedComponent);
    } else {
        initUnifiedComponent();
    }

    window.fillUnifiedForm = function(data) {
        if (data.id) {
            const idEl = document.getElementById('unified-id');
            if(idEl) idEl.value = data.id;
        }
        if (data.is_reserva !== undefined) {
            const resEl = document.getElementById('unified-is-reserva');
            if(resEl) resEl.value = data.is_reserva ? '1' : '0';
            const finSec = document.getElementById('unified-financial-section');
            // Agora sempre mostramos a seção financeira, tanto para turmas quanto para reservas
            if(finSec) finSec.style.display = 'block';
            
            const cursoSel = document.getElementById('curso-select-unified');
            if(cursoSel) {
                cursoSel.required = !data.is_reserva;
                // Para reservas, permitimos que o curso seja opcional se o usuário desejar
            }

            const btnText = document.getElementById('btn-text-unified');
            if(btnText) btnText.innerText = data.is_reserva ? 'Confirmar Reserva' : 'Salvar Turma';

            const modalTitle = document.getElementById('modal-cal-title');
            const modalIcon = document.getElementById('modal-cal-icon');
            if (modalTitle) modalTitle.innerText = data.is_reserva ? 'Salvar Reserva' : 'Salvar Turma';
            if (modalIcon) {
                modalIcon.className = data.is_reserva ? 'fas fa-bookmark' : 'fas fa-calendar-plus';
                modalIcon.style.color = data.is_reserva ? '#ffb300' : 'var(--primary-red)';
            }
        }
        
        if (data.docentes && Array.isArray(data.docentes)) {
            selectedDocentes = [...data.docentes];
            renderSelectedDocentes();
        }

        if (data.data_inicio) {
            const di = document.getElementById('data-inicio-unified');
            if(di) di.value = data.data_inicio;
        }
        if (data.data_fim) {
            const df = document.getElementById('data-fim-unified');
            if(df) df.value = data.data_fim;
        }
        if (data.periodo) {
            const pSel = document.getElementById('periodo-select-unified');
            if(pSel) {
                pSel.value = data.periodo;
                pSel.dispatchEvent(new Event('change'));
            }
        }
        if (data.dias_semana && Array.isArray(data.dias_semana)) {
            document.querySelectorAll('#dias-semana-container-unified input[name="dias_semana[]"]').forEach(cb => {
                cb.checked = data.dias_semana.includes(cb.value);
            });
        }
        
        if (data.tipo_custeio) {
            const tc = document.getElementById('tipo-custeio-unified');
            if(tc) {
                tc.value = data.tipo_custeio;
                toggleCusteioFieldsUnified();
            }
        }
        if (data.previsao_despesa !== undefined) {
            const pd = document.getElementById('unified-previsao-despesa');
            if(pd) pd.value = data.previsao_despesa;
        }
        if (data.valor_turma !== undefined) {
            const vt = document.getElementById('unified-valor-turma');
            if(vt) vt.value = data.valor_turma;
        }
        if (data.numero_proposta !== undefined) {
            const np = document.getElementById('unified-numero-proposta');
            if(np) np.value = data.numero_proposta;
        }
        if (data.tipo_atendimento) {
            const radios = document.querySelectorAll('input[name="tipo_atendimento"]');
            radios.forEach(r => {
                if(r.value === data.tipo_atendimento) r.checked = true;
            });
            toggleAtendimentoFieldsUnified();
        }
        if (data.parceiro) {
            const p = document.getElementById('unified-parceiro');
            if(p) p.value = data.parceiro;
        }
        if (data.contato_parceiro) {
            const cp = document.getElementById('unified-contato-parceiro');
            if(cp) cp.value = data.contato_parceiro;
        }
        
        calcularDataFimUnified();
    }
})();
</script>
