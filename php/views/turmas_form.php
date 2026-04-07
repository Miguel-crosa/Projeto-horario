<?php
require_once __DIR__ . '/../configs/db.php';
include __DIR__ . '/../components/header.php';

$id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : null;
$turma = [
    'curso_id' => '', 'ambiente_id' => '', 'periodo' => '', 'data_inicio' => '', 'data_fim' => '',
    'tipo' => 'Presencial', 'sigla' => '', 'vagas' => '', 'docente_id1' => '', 'docente_id2' => '',
    'docente_id3' => '', 'docente_id4' => '', 'local' => '', 'dias_semana' => '',
    'horario_inicio' => '07:30', 'horario_fim' => '11:30',
    'tipo_custeio' => 'Gratuidade', 'previsao_despesa' => 0, 'valor_turma' => 0,
    'numero_proposta' => '', 'tipo_atendimento' => 'Balcão', 'parceiro' => '', 'contato_parceiro' => ''
];

if ($id) {
    $turma = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM turma WHERE id = '$id'"));
    if (!$turma) {
        header("Location: turmas.php");
        exit;
    }
}

$cursos_ag = mysqli_fetch_all(mysqli_query($conn, "SELECT id, nome, carga_horaria_total, area, tipo FROM curso ORDER BY area ASC, nome ASC"), MYSQLI_ASSOC);
$grouped_cursos_ag = [];
foreach ($cursos_ag as $c_ag) {
    $area_ag = $c_ag['area'] ?: 'Outros';
    $grouped_cursos_ag[$area_ag][] = $c_ag;
}
$ambientes = mysqli_fetch_all(mysqli_query($conn, "SELECT id, nome FROM ambiente ORDER BY nome ASC"), MYSQLI_ASSOC);
$docentes = mysqli_fetch_all(mysqli_query($conn, "SELECT id, nome, area_conhecimento FROM docente ORDER BY nome ASC"), MYSQLI_ASSOC);

$dias_selecionados = !empty($turma['dias_semana']) ? explode(',', $turma['dias_semana']) : [];

// Fallback: Se ambiente_id estiver vazio, tenta encontrar pelo campo 'local'
if (empty($turma['ambiente_id']) && !empty($turma['local'])) {
    $local_esc = mysqli_real_escape_string($conn, trim($turma['local']));
    // Tenta busca exata primeiro
    $found_amb = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM ambiente WHERE TRIM(nome) = '$local_esc' LIMIT 1"));
    if ($found_amb) {
        $turma['ambiente_id'] = $found_amb['id'];
    } else {
        // Tenta busca parcial se for algo como "SALA 01 - UNIDADE X"
        $found_amb = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM ambiente WHERE '$local_esc' LIKE CONCAT('%', nome, '%') LIMIT 1"));
        if ($found_amb) {
            $turma['ambiente_id'] = $found_amb['id'];
        }
    }
}
// Busca feriados e bloqueios para o cálculo de data fim (Unificado: holidays, vacations e agenda blocks)
$feriados_res = mysqli_query($conn, "
    SELECT date AS data_inicio, COALESCE(end_date, date) AS data_fim, NULL AS docente_id, 'HOLIDAY' AS tipo FROM holidays
    UNION ALL
    SELECT start_date AS data_inicio, end_date AS data_fim, teacher_id AS docente_id, 'VACATION' AS tipo FROM vacations
    UNION ALL
    SELECT data AS data_inicio, data AS data_fim, docente_id, 'BLOCK' AS tipo FROM agenda WHERE status = 'RESERVADO' AND turma_id IS NULL
");
$feriados_data = mysqli_fetch_all($feriados_res, MYSQLI_ASSOC);
?>

<div class="page-header">
    <h2><?= $id ? 'Editar Turma' : 'Nova Turma' ?></h2>
    <a href="turmas.php" class="btn btn-back"><i class="fas fa-arrow-left"></i> Voltar</a>
</div>

<div class="card" style="max-width: 700px; margin: 0 auto;">
    <div id="form-alert" class="alert alert-danger" style="display: <?= (isset($_GET['msg']) && ($_GET['msg'] === 'limit_exceeded' || $_GET['msg'] === 'error')) ? 'block' : 'none' ?>; margin-bottom: 20px; border-left: 5px solid #dc3545; background: #f8d7da; color: #721c24; padding: 15px; border-radius: 4px;">
        <i class="fas fa-exclamation-triangle"></i> <strong>Alerta:</strong> 
        <span id="form-alert-msg"><?= htmlspecialchars($_GET['error_text'] ?? 'Ocorreu um erro na validação.') ?></span>
    </div>

    <form action="../controllers/turmas_process.php" method="POST" id="turma-form">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="ajax" value="1">
        <div class="form-grid">
            <div class="form-group">
                <label class="form-label">CURSO</label>
                <select name="curso_id" class="form-input" required id="curso-select">
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
                <input type="text" name="sigla" class="form-input" value="<?= htmlspecialchars($turma['sigla']) ?>"
                    placeholder="Ex: TI-2026-123">
            </div>
        </div>
        <div class="form-grid">
            <div class="form-group">
                <label class="form-label">Vagas</label>
                <input type="number" name="vagas" class="form-input" value="<?= $turma['vagas'] ?>"
                    placeholder="Ex: 32">
            </div>
            <div class="form-group">
                <label class="form-label">Local</label>
                <input type="text" name="local" class="form-input" value="<?= htmlspecialchars($turma['local']) ?>"
                    placeholder="Ex: Unidade SJC">
            </div>
        </div>
        <div class="form-grid" style="margin-top: 15px; border-top: 1px solid var(--border-color); padding-top: 15px;">
            <div class="form-group">
                <label class="form-label"><i class="fas fa-hand-holding-usd"></i> Tipo de Custeio</label>
                <select name="tipo_custeio" class="form-input" id="tipo-custeio" onchange="toggleCusteioFields()">
                    <option value="Gratuidade" <?= $turma['tipo_custeio'] == 'Gratuidade' ? 'selected' : '' ?>>Gratuidade</option>
                    <option value="Ressarcido" <?= $turma['tipo_custeio'] == 'Ressarcido' ? 'selected' : '' ?>>Ressarcido</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><i class="fas fa-money-bill-wave"></i> Previsão de Despesas</label>
                <input type="number" step="0.01" name="previsao_despesa" class="form-input" value="<?= $turma['previsao_despesa'] ?>" placeholder="Ex: 500.00">
            </div>
        </div>

        <div class="form-group" id="group-valor-turma" style="margin-top: 15px; <?= $turma['tipo_custeio'] == 'Ressarcido' ? '' : 'display: none;' ?>">
            <label class="form-label" style="color: var(--primary-red); font-weight: 700;">VALOR DA TURMA (ARRECADADO)</label>
            <div style="position: relative;">
                <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-weight: 700; color: var(--text-muted);">R$</span>
                <input type="number" step="0.01" name="valor_turma" class="form-input" value="<?= $turma['valor_turma'] ?>" placeholder="0.00" style="padding-left: 40px;">
            </div>
        </div>

        <div class="form-grid" style="margin-top: 15px; border-top: 1px solid var(--border-color); padding-top: 15px;">
            <div class="form-group" id="group-numero-proposta">
                <label class="form-label"><i class="fas fa-file-contract"></i> Nº da Proposta</label>
                <input type="text" name="numero_proposta" class="form-input" value="<?= htmlspecialchars($turma['numero_proposta'] ?? '') ?>" placeholder="Ex: 123/2026">
            </div>
            <div class="form-group">
                <label class="form-label"><i class="fas fa-users-cog"></i> Tipo de Atendimento</label>
                <div style="display: flex; gap: 15px; align-items: center; margin-top: 8px;">
                    <label style="display: flex; align-items: center; gap: 5px; cursor: pointer; font-size: 0.9rem;">
                        <input type="radio" name="tipo_atendimento" value="Empresa" <?= ($turma['tipo_atendimento'] ?? '') == 'Empresa' ? 'checked' : '' ?> onchange="toggleAtendimentoFields()"> Empresa
                    </label>
                    <label style="display: flex; align-items: center; gap: 5px; cursor: pointer; font-size: 0.9rem;">
                        <input type="radio" name="tipo_atendimento" value="Entidade" <?= ($turma['tipo_atendimento'] ?? '') == 'Entidade' ? 'checked' : '' ?> onchange="toggleAtendimentoFields()"> Entidade
                    </label>
                    <label style="display: flex; align-items: center; gap: 5px; cursor: pointer; font-size: 0.9rem;">
                        <input type="radio" name="tipo_atendimento" value="Balcão" <?= ($turma['tipo_atendimento'] ?? 'Balcão') == 'Balcão' ? 'checked' : '' ?> onchange="toggleAtendimentoFields()"> Balcão
                    </label>
                </div>
            </div>
        </div>

        <div class="form-grid" id="group-parceria-detalhes" style="margin-top: 15px;">
            <div class="form-group">
                <label class="form-label"><i class="fas fa-handshake"></i> Parceiro</label>
                <input type="text" name="parceiro" class="form-input" value="<?= htmlspecialchars($turma['parceiro'] ?? '') ?>" placeholder="Ex: Empresa Exemplo">
            </div>
            <div class="form-group">
                <label class="form-label"><i class="fas fa-address-book"></i> Contato do Parceiro</label>
                <input type="text" name="contato_parceiro" class="form-input" value="<?= htmlspecialchars($turma['contato_parceiro'] ?? '') ?>" placeholder="Ex: João - (11) 9999-9999">
            </div>
        </div>
        
        <div class="form-group" style="margin-top: 15px;">
            <label class="form-label">Ambiente</label>
            <select name="ambiente_id" class="form-input" required>
                <option value="">Selecione o ambiente...</option>
                <?php foreach ($ambientes as $a): ?>
                    <option value="<?= $a['id'] ?>" <?= $turma['ambiente_id'] == $a['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($a['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="margin-top: 20px;">
            <label class="form-label" style="font-size: 1.1rem; color: var(--primary-red); font-weight: 800; border-bottom: 2px solid rgba(237,28,36,0.1); padding-bottom: 8px; margin-bottom: 15px;">
                <i class="fas fa-chalkboard-teacher"></i> Corpo Docente (Até 4)
            </label>
            
            <div id="selected-docentes-container" class="docentes-list-v4">
                <!-- Preenchido via JavaScript -->
            </div>

            <button type="button" class="add-docente-btn-v4" id="btn-abrir-modal-docentes">
                <i class="fas fa-plus-circle"></i> SELECIONAR DOCENTE
            </button>
            <div id="docente-error" style="color: var(--primary-red); font-size: 0.85rem; margin-top: 8px; display: none; font-weight: 600;">
                <i class="fas fa-exclamation-circle"></i> Pelo menos um docente deve ser vinculado à turma.
            </div>
            
            <!-- Campos Ocultos para o Formulário -->
            <input type="hidden" name="docente_id1" id="hidden-docente-1" value="<?= $turma['docente_id1'] ?>">
            <input type="hidden" name="docente_id2" id="hidden-docente-2" value="<?= $turma['docente_id2'] ?>">
            <input type="hidden" name="docente_id3" id="hidden-docente-3" value="<?= $turma['docente_id3'] ?>">
            <input type="hidden" name="docente_id4" id="hidden-docente-4" value="<?= $turma['docente_id4'] ?>">
        </div>
            <div class="form-group">
                <label class="form-label">Período</label>
                <select name="periodo" class="form-input" required id="periodo-select">
                    <option value="">Selecione o período...</option>
                    <option value="Manhã" <?= $turma['periodo'] == 'Manhã' ? 'selected' : '' ?>>Manhã (07:30 - 11:30)</option>
                    <option value="Tarde" <?= $turma['periodo'] == 'Tarde' ? 'selected' : '' ?>>Tarde (13:30 - 17:30)</option>
                    <option value="Noite" <?= $turma['periodo'] == 'Noite' ? 'selected' : '' ?>>Noite (18:00 - 22:00)</option>
                    <option value="Integral" <?= $turma['periodo'] == 'Integral' ? 'selected' : '' ?>>Integral (07:30 - 17:30)</option>
                </select>
            </div>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Horário Início</label>
                    <input type="time" name="horario_inicio" id="horario_inicio" class="form-input"
                        value="<?= substr($turma['horario_inicio'], 0, 5) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Horário Até</label>
                    <input type="time" name="horario_fim" id="horario_fim" class="form-input"
                        value="<?= substr($turma['horario_fim'], 0, 5) ?>" required>
                </div>
            </div>
        <div class="form-group">
            <label class="form-label">Tipo</label>
            <select name="tipo" class="form-input">
                <option value="Presencial" selected>Presencial</option>
            </select>
        </div>

        <div class="form-group" style="margin-top: 15px;">
            <label class="form-label">Dias da Semana</label>
                    <div class="dias-checkboxes" id="dias-semana-container">
                        <?php
                        $dias = ['Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
                        foreach ($dias as $dia): ?>
                            <label class="dia-checkbox-label">
                                <input type="checkbox" name="dias_semana[]" value="<?= $dia ?>" 
                                    <?= in_array($dia, $dias_selecionados) ? 'checked' : '' ?>
                                    onchange="calcularDataFim()">
                                <span><?= substr($dia, 0, 3) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
        </div>

        <div class="form-grid" style="margin-top: 15px;">
            <div>
                <label class="form-label">Data Início</label>
                <input type="date" name="data_inicio" class="form-input" id="data-inicio"
                    value="<?= $turma['data_inicio'] ?>" required>
            </div>
            <div>
                <label class="form-label">Data Fim</label>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <input type="date" name="data_fim" class="form-input" id="data-fim"
                        value="<?= $turma['data_fim'] ?>" readonly
                        style="background: var(--bg-hover); cursor: not-allowed; flex: 1;">
                    <label style="display: flex; align-items: center; gap: 5px; font-size: 0.8rem; white-space: nowrap; cursor: pointer;">
                        <input type="checkbox" id="calc-auto" checked style="width: 16px; height: 16px;">
                        Auto
                    </label>
                </div>
            </div>
        </div>
        <script>
            document.getElementById('calc-auto').addEventListener('change', function() {
                const df = document.getElementById('data-fim');
                df.readOnly = this.checked;
                df.style.background = this.checked ? 'var(--bg-hover)' : 'var(--bg-card)';
                df.style.cursor = this.checked ? 'not-allowed' : 'text';
                if(!this.checked) df.required = true;
            });
        </script>
        <div id="data-fim-info" style="font-size: 0.8rem; color: var(--text-muted); margin-top: 5px;"></div>

        <div class="form-actions" style="margin-top: 20px;">
            <button type="submit" class="btn btn-primary">Salvar Turma</button>
        </div>
    </form>
</div>

<script>
// --- Novo Seletor de Docentes ---
const docentesData = <?= json_encode($docentes) ?>;
const feriadosData = <?= json_encode($feriados_data) ?>;
let selectedDocentes = [];

// Elementos que serão inicializados no DOMContentLoaded
let modalDoc, searchInput, resultsContainer, areaFilter;

function normalizeString(str) {
    if (!str) return '';
    return str.toString()
        .toLowerCase()
        .normalize('NFD') // Decompõe acentos
        .replace(/[\u0300-\u036f]/g, '') // Remove os acentos
        .trim();
}

function initSelectedDocentes() {
    for (let i = 1; i <= 4; i++) {
        const el = document.getElementById(`hidden-docente-${i}`);
        if (el && el.value) {
            const doc = docentesData.find(d => d.id == el.value);
            if (doc) selectedDocentes.push(doc);
        }
    }
    
    // Captura o curso atual ANTES de filtrar, para garantir que ele não se perca
    const selectCurso = document.getElementById('curso-select');
    const currentCursoId = selectCurso ? selectCurso.value : null;
    
    renderSelectedDocentes(currentCursoId);
}

function renderSelectedDocentes(forcedCursoId = null) {
    const container = document.getElementById('selected-docentes-container');
    if (!container) return;
    container.innerHTML = '';

    // Filtra cursos pela área do primeiro professor, mas mantendo o curso atual
    if (selectedDocentes.length > 0) {
        filterCursosByArea(selectedDocentes[0].area_conhecimento, forcedCursoId);
    } else {
        filterCursosByArea(null, forcedCursoId);
    }

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
            <button type="button" class="docente-card-remove" onclick="removeDocente(${index})" title="Remover Docente">
                <i class="fas fa-trash-alt"></i>
            </button>
        `;
        container.appendChild(card);
    });

    for (let i = 1; i <= 4; i++) {
        const el = document.getElementById(`hidden-docente-${i}`);
        if (el) el.value = selectedDocentes[i - 1] ? selectedDocentes[i - 1].id : '';
    }

    const btnAdd = document.getElementById('btn-abrir-modal-docentes');
    if (btnAdd) {
        if (selectedDocentes.length >= 4) {
            btnAdd.style.display = 'none';
        } else {
            btnAdd.style.display = 'flex';
            btnAdd.innerHTML = `<i class="fas fa-plus-circle"></i> ADICIONAR DOCENTE (${selectedDocentes.length}/4)`;
        }
    }
}

function removeDocente(index) {
    selectedDocentes.splice(index, 1);
    const selectCurso = document.getElementById('curso-select');
    renderSelectedDocentes(selectCurso ? selectCurso.value : null);
    if(typeof checkAvailability === 'function') checkAvailability();
}

function renderModalResults() {
    if (!resultsContainer || !searchInput) return;
    const query = searchInput.value.toLowerCase().trim();
    const area = areaFilter ? areaFilter.value : '';

    let filtered = docentesData.filter(d => {
        const matchesName = !query || normalizeString(d.nome).includes(normalizeString(query));
        const matchesArea = !area || normalizeString(d.area_conhecimento) === normalizeString(area);
        const alreadySelected = selectedDocentes.some(sd => sd.id == d.id);
        return matchesName && matchesArea && !alreadySelected;
    });

    if (filtered.length === 0) {
        resultsContainer.innerHTML = '<div style="text-align:center; padding:20px; color:#888;">Nenhum professor disponível encontrado.</div>';
        return;
    }

    resultsContainer.innerHTML = filtered.map(d => `
        <div class="prof-result-item" data-id="${d.id}" 
            style="padding: 12px 15px; border-bottom: 1px solid var(--border-color); cursor: pointer; display: flex; justify-content: space-between; align-items: center; transition: all 0.2s;">
            <div>
                <strong style="font-size: 0.95rem;">${d.nome}</strong><br>
                <small style="color: #888; font-size: 0.75rem;">${d.area_conhecimento || 'Outros'}</small>
            </div>
            <i class="fas fa-plus-circle" style="color: var(--primary-red); font-size: 1.1rem;"></i>
        </div>
    `).join('');

    resultsContainer.querySelectorAll('.prof-result-item').forEach(item => {
        item.onclick = function() {
            const id = this.dataset.id;
            const doc = docentesData.find(d => d.id == id);
            if (doc && selectedDocentes.length < 4) {
                selectedDocentes.push(doc);
                const selectCurso = document.getElementById('curso-select');
                renderSelectedDocentes(selectCurso ? selectCurso.value : null);
                if (modalDoc) modalDoc.classList.remove('active');
                if(typeof checkAvailability === 'function') checkAvailability();
            }
        };
    });
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('active');
}

function filterCursosByArea(area, forcedCursoId = null) {
    const select = document.getElementById('curso-select');
    if (!select) return;
    
    const groups = select.querySelectorAll('optgroup');
    groups.forEach(group => {
        // Remove a limitação: Sempre exibe todos os cursos
        group.style.display = '';
    });

    // Removido o reset do select caso o curso não batesse com a área
}

function toggleCusteioFields() {
    const type = document.getElementById('tipo-custeio').value;
    const group = document.getElementById('group-valor-turma');
    if (group) {
        group.style.display = (type === 'Ressarcido') ? 'block' : 'none';
    }
}

function toggleAtendimentoFields() {
    const selected = document.querySelector('input[name="tipo_atendimento"]:checked')?.value;
    const groupProposta = document.getElementById('group-numero-proposta');
    const groupParceria = document.getElementById('group-parceria-detalhes');
    
    if (selected === 'Balcão') {
        if (groupProposta) groupProposta.style.display = 'none';
        if (groupParceria) groupParceria.style.display = 'none';
    } else {
        if (groupProposta) groupProposta.style.display = 'block';
        if (groupParceria) groupParceria.style.display = 'grid'; // form-grid é grid
    }
}

// --- Lógica de Cálculo de Data Fim ---
const cursoSelect = document.getElementById('curso-select');
const periodoSelect = document.getElementById('periodo-select');
const dataInicio = document.getElementById('data-inicio');
const dataFim = document.getElementById('data-fim');
const infoEl = document.getElementById('data-fim-info');

function getHorasPorDia() {
    const periodo = document.getElementById('periodo-select').value;
    const h_ini = document.getElementById('horario_inicio');
    const h_fim = document.getElementById('horario_fim');

    if (h_ini && h_fim && h_ini.value && h_fim.value) {
        const [h1, m1] = h_ini.value.split(':').map(Number);
        const [h2, m2] = h_fim.value.split(':').map(Number);
        const diffMinutes = (h2 * 60 + m2) - (h1 * 60 + m1);
        if (diffMinutes > 0) return diffMinutes / 60;
    }

    // Fallback por período
    switch (periodo) {
        case 'Manhã': case 'Tarde': case 'Noite': return 4;
        case 'Integral': return 8;
        default: return 0;
    }
}

function getDiaSemanaIndex(nome) {
    const map = {'Domingo':0, 'Segunda-feira':1, 'Terça-feira':2, 'Quarta-feira':3, 'Quinta-feira':4, 'Sexta-feira':5, 'Sábado':6};
    return map[nome] ?? -1;
}

function calcularDataFim() {
    if (!cursoSelect || !periodoSelect || !dataInicio) return;
    const opt = cursoSelect.options[cursoSelect.selectedIndex];
    const ch = parseInt(opt?.dataset.ch) || 0;
    const periodo = periodoSelect.value;
    const inicio = dataInicio.value;
    const diasChecked = Array.from(document.querySelectorAll('input[name="dias_semana[]"]:checked')).map(cb => cb.value);

    const h_ini = document.getElementById('horario_inicio');
    const h_fim = document.getElementById('horario_fim');

    if (periodoSelect.dataset.lastPeriod !== periodo) {
        const periodDefaults = {
            'Manhã': ['07:30', '11:30'],
            'Tarde': ['13:30', '17:30'],
            'Noite': ['18:00', '22:00'],
            'Integral': ['07:30', '17:30']
        };
        if (periodDefaults[periodo]) {
            if (h_ini) h_ini.value = periodDefaults[periodo][0];
            if (h_fim) h_fim.value = periodDefaults[periodo][1];
        }
        periodoSelect.dataset.lastPeriod = periodo;
    }

    if (!ch || !periodo || !inicio || diasChecked.length === 0) {
        if (dataFim) dataFim.value = '';
        if (infoEl) infoEl.textContent = '';
        return;
    }

    const horasPorDia = getHorasPorDia();
    if (horasPorDia === 0) return;

    const totalDias = Math.ceil(ch / horasPorDia);
    const diasIndices = diasChecked.map(d => getDiaSemanaIndex(d)).filter(i => i >= 0);

    let date = new Date(inicio + 'T12:00:00');
    let count = 0;
    
    // Filtra IDs dos docentes selecionados
    const currentDocIds = selectedDocentes.map(d => String(d.id));

    for (let safety = 0; safety < 1000 && count < totalDias; safety++) {
        const dow = date.getDay();
        const dateISO = date.toISOString().slice(0, 10);

        if (diasIndices.includes(dow)) {
            // VERIFICAÇÃO DE FERIADO / FÉRIAS / BLOQUEIO (Pular automaticamente)
            const isBlocked = feriadosData.some(f => {
                const isDateMatch = (dateISO === f.data_inicio) || (dateISO >= f.data_inicio && dateISO <= f.data_fim);
                const isDocMatch = !f.docente_id || currentDocIds.includes(String(f.docente_id));
                return isDateMatch && isDocMatch;
            });

            if (!isBlocked) {
                count++;
            }
        }
        if (count >= totalDias) break;
        date.setDate(date.getDate() + 1);
    }

    if (dataFim) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        dataFim.value = `${y}-${m}-${d}`;
    }
    if (infoEl) {
        infoEl.innerHTML = `<i class="fas fa-info-circle"></i> ${ch}h ÷ ${horasPorDia.toFixed(1)}h/dia = <strong>${totalDias} dias de aula</strong> (Pula feriados/férias).`;
    }

    if(typeof checkAvailability === 'function') checkAvailability();
}

async function checkAvailability() {
    const form = document.getElementById('turma-form');
    if (!form) return;
    const btnSalvar = form.querySelector('button[type="submit"]');
    const globalAlert = document.getElementById('form-alert');
    const alertMsg = document.getElementById('form-alert-msg');
    
    // Validar apenas se temos o mínimo necessário
    const data_inicio = document.getElementById('data-inicio').value;
    const periodo = document.getElementById('periodo-select').value;
    const diasChecked = Array.from(document.querySelectorAll('input[name="dias_semana[]"]:checked'));
    
    if (!data_inicio || !periodo || diasChecked.length === 0 || selectedDocentes.length === 0) {
        if (btnSalvar) btnSalvar.disabled = false;
        return;
    }

    try {
        const formData = new FormData(form);
        formData.append('validate_only', '1');
        
        const response = await fetch(form.action, { method: 'POST', body: formData });
        const result = await response.json();

        if (!result.success) {
            if (btnSalvar) btnSalvar.disabled = true;
            if (globalAlert && alertMsg) {
                alertMsg.innerText = result.message;
                globalAlert.style.display = 'block';
                globalAlert.style.background = '#f8d7da';
                globalAlert.style.color = '#721c24';
                globalAlert.style.borderColor = '#f5c6cb';
            }
        } else {
            if (btnSalvar) btnSalvar.disabled = false;
            // Se for apenas uma mensagem informativa de sucesso do validador, podemos esconder o alerta
            if (globalAlert && (alertMsg.innerText.includes("disponível") || alertMsg.innerText === "")) {
                globalAlert.style.display = 'none';
            }
        }
    } catch (err) {
        console.error("Erro na validação de disponibilidade:", err);
    }
}

if (cursoSelect) cursoSelect.addEventListener('change', calcularDataFim);
if (periodoSelect) periodoSelect.addEventListener('change', calcularDataFim);
if (dataInicio) dataInicio.addEventListener('change', calcularDataFim);
document.querySelectorAll('input[name="dias_semana[]"]').forEach(cb => cb.addEventListener('change', calcularDataFim));
if (document.getElementById('horario_inicio')) document.getElementById('horario_inicio').addEventListener('change', calcularDataFim);
if (document.getElementById('horario_fim')) document.getElementById('horario_fim').addEventListener('change', calcularDataFim);

// Inicializar carregamento

document.addEventListener('DOMContentLoaded', () => {
    // Inicializar elementos do Modal (que vêm do footer.php)
    modalDoc = document.getElementById('modal-selecionar-professor');
    searchInput = document.getElementById('prof-search-input');
    resultsContainer = document.getElementById('prof-search-results');
    areaFilter = document.getElementById('prof-area-filter');

    const btnAbrirModal = document.getElementById('btn-abrir-modal-docentes');
    if (btnAbrirModal) {
        btnAbrirModal.onclick = () => {
            if (modalDoc) {
                modalDoc.classList.add('active');
                
                // Pré-filtra área se houver curso selecionado
                const selCurso = document.getElementById('curso-select');
                if (selCurso && selCurso.value && areaFilter) {
                    const opt = selCurso.options[selCurso.selectedIndex];
                    const group = opt.closest('optgroup');
                    if (group && group.dataset.area) {
                        // Tenta encontrar a opção correspondente no filtro de área do modal
                        const areaToSelect = group.dataset.area;
                        // Itera nas opções para achar a que normalizada bate com a do curso
                        Array.from(areaFilter.options).forEach(o => {
                            if (normalizeString(o.value) === normalizeString(areaToSelect)) {
                                areaFilter.value = o.value;
                            }
                        });
                    }
                }

                if (searchInput) {
                    searchInput.value = '';
                    renderModalResults();
                    setTimeout(() => searchInput.focus(), 100);
                }
            }
        };
    }

    if (searchInput) searchInput.oninput = renderModalResults;
    if (areaFilter) areaFilter.onchange = renderModalResults;

    initSelectedDocentes();
    calcularDataFim();
    toggleAtendimentoFields();

    // Validação e Submissão de formulário via AJAX
    const form = document.getElementById('turma-form');
    if (form) {
        form.onsubmit = async function(e) {
            e.preventDefault();
            const errorEl = document.getElementById('docente-error');
            const globalAlert = document.getElementById('form-alert');
            const alertMsg = document.getElementById('form-alert-msg');
            const btnSalvar = form.querySelector('button[type="submit"]');

            // 1. Validação de Docente Obrigatório
            if (selectedDocentes.length === 0) {
                if (errorEl) {
                    errorEl.style.display = 'block';
                    errorEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return false;
            } else {
                if (errorEl) errorEl.style.display = 'none';
            }

            // 2. Submissão AJAX
            try {
                btnSalvar.disabled = true;
                btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
                
                const formData = new FormData(form);
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    window.location.href = result.redirect;
                } else {
                    btnSalvar.disabled = false;
                    btnSalvar.innerHTML = 'Salvar Turma';
                    
                    if (globalAlert && alertMsg) {
                        alertMsg.innerText = result.message;
                        globalAlert.style.display = 'block';
                        globalAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            } catch (err) {
                console.error("Erro na submissão:", err);
                btnSalvar.disabled = false;
                btnSalvar.innerHTML = 'Salvar Turma';
                alert("Ocorreu um erro inesperado ao salvar a turma.");
            }

            return false;
        };
    }
});
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>