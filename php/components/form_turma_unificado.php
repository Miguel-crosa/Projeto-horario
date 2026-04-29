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
    <input type="hidden" name="return_url" value="<?= htmlspecialchars($return_url ?? '') ?>">
    <input type="hidden" name="send_email" id="send_email_unified" value="0">

    <div class="form-grid">
        <div class="form-group" id="grp-unified-curso">
            <label class="form-label">CURSO / MODALIDADE</label>
            <div style="position: relative;">
                <button type="button" class="form-input" id="btn-abrir-modal-cursos-unified" 
                    style="text-align: left; display: flex; justify-content: space-between; align-items: center; background: var(--bg-card); cursor: pointer; height: auto; min-height: 45px; padding: 10px 15px; border: 1px solid var(--border-color); border-radius: 8px; width: 100%;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-graduation-cap" style="color: var(--primary-red); opacity: 0.7;"></i>
                        <span id="curso-nome-display-unified" style="font-weight: 600;">
                            <?php 
                                $turma_ch_inicial = 0;
                                if (!empty($turma['curso_id'])) {
                                    // Busca o nome e CH do curso para exibição inicial se estiver editando
                                    foreach ($grouped_cursos_ag as $area_l => $lista_l) {
                                        foreach ($lista_l as $c_l) {
                                            if ($c_l['id'] == $turma['curso_id']) {
                                                $turma_ch_inicial = $c_l['carga_horaria_total'];
                                                echo htmlspecialchars($c_l['nome']) . " (" . ($c_l['tipo'] ?: 'FIC') . ") - " . $c_l['carga_horaria_total'] . "h";
                                                break 2;
                                            }
                                        }
                                    }
                                } else {
                                    echo "Clique para selecionar o curso...";
                                }
                            ?>
                        </span>
                    </div>
                    <i class="fas fa-search" style="font-size: 0.9rem; opacity: 0.5;"></i>
                </button>
                <input type="hidden" name="curso_id" id="curso-id-unified" value="<?= $turma['curso_id'] ?>" data-ch="<?= $turma_ch_inicial ?>" required>
            </div>
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
            <option value="Noite" <?= $turma['periodo'] == 'Noite' ? 'selected' : '' ?>>Noite (18:00 - 23:00)</option>
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
        <div class="agenda-header-v4" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span class="form-label" style="margin: 0;">Configuração da Agenda</span>
                <div id="flex-count-badge" style="font-size: 0.65rem; font-weight: 900; color: white; background: var(--primary-red); padding: 3px 10px; border-radius: 20px; display: none; box-shadow: 0 2px 5px rgba(237,28,36,0.2); letter-spacing: 0.5px; text-transform: uppercase;">
                    <span id="flex-date-count">0</span> dias
                </div>
            </div>
            <div class="agenda-type-toggle-v4">
                <label class="toggle-option">
                    <input type="radio" name="tipo_agenda" value="recorrente" <?= ($turma['tipo_agenda'] ?? 'recorrente') == 'recorrente' ? 'checked' : '' ?> onchange="toggleAgendaTypeUnified()">
                    <div class="option-content">
                        <i class="fas fa-redo-alt"></i>
                        <span>Semanal</span>
                    </div>
                </label>
                <label class="toggle-option">
                    <input type="radio" name="tipo_agenda" value="flexivel" <?= ($turma['tipo_agenda'] ?? 'recorrente') == 'flexivel' ? 'checked' : '' ?> onchange="toggleAgendaTypeUnified()">
                    <div class="option-content">
                        <i class="fas fa-calendar-day"></i>
                        <span>Manual</span>
                    </div>
                </label>
            </div>
        </div>

        <!-- Container Dias da Semana (Recorrente) -->
        <div id="container-agenda-recorrente" style="display: <?= ($turma['tipo_agenda'] ?? 'recorrente') == 'recorrente' ? 'block' : 'none' ?>;">
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

        <!-- Container Datas Específicas (Flexível) -->
        <div id="container-agenda-flexivel" style="display: <?= ($turma['tipo_agenda'] ?? 'recorrente') == 'flexivel' ? 'block' : 'none' ?>; margin-top: 5px;">
            <div class="flex-setup-card" style="background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 16px; padding: 15px; border-left: 4px solid var(--primary-red);">
                <button type="button" class="btn-action-v4" style="width: 100%; height: 48px; gap: 10px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--primary-red); font-weight: 800; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: all 0.2s;" onclick="openFlexibleCalendar()" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 8px rgba(0,0,0,0.05)';" onmouseout="this.style.transform='none'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.02)';">
                    <i class="fas fa-calendar-plus" style="font-size: 1.1rem;"></i> CONFIGURAR CALENDÁRIO
                </button>
                <div id="selected-dates-preview" style="margin-top: 15px; max-height: 250px; overflow-y: auto; padding-right: 5px; scrollbar-width: thin; scrollbar-color: var(--text-muted) transparent;">
                    <!-- Preenchido via JS -->
                </div>
            </div>
            <input type="hidden" name="agenda_flexivel" id="agenda-flexivel-input" value="<?= htmlspecialchars($turma['agenda_flexivel'] ?? '') ?>">
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
                <label id="calc-auto-container-unified" style="display: <?= ($turma['tipo_agenda'] ?? 'recorrente') == 'recorrente' ? 'flex' : 'none' ?>; align-items: center; gap: 5px; font-size: 0.8rem; white-space: nowrap; cursor: pointer;">
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

<!-- Modal Agenda Flexível -->
<div id="modal-agenda-flexivel" class="modal-overlay">
    <div class="modal-content" style="max-width: 1000px; width: 95%;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-calendar-check"></i> Selecionar Datas Manuais</h3>
            <button type="button" class="modal-close" onclick="closeFlexibleCalendar()">&times;</button>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <div class="cal-nav-v4" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; background: rgba(0,0,0,0.03); padding: 10px; border-radius: 12px;">
                <button type="button" class="btn-nav-cal" onclick="changeCalMonths(-3)">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <div id="cal-current-range" style="font-weight: 800; font-size: 1.2rem; color: var(--text-color); letter-spacing: -0.5px;"></div>
                <button type="button" class="btn-nav-cal" onclick="changeCalMonths(3)">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            
            <div id="cal-flex-container" class="cal-flex-grid">
                <!-- 3 Meses serão renderizados aqui -->
            </div>

            <div class="modal-footer-flex" style="margin-top: 30px; padding: 25px; background: #f8fafc; border-radius: 20px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #edf2f7; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);">
                <div style="display: flex; align-items: center; gap: 20px;">
                    <div style="display: flex; flex-direction: column;">
                        <span style="font-size: 0.65rem; text-transform: uppercase; font-weight: 800; color: #a0aec0; letter-spacing: 1px; margin-bottom: 4px;">Seleção Atual</span>
                        <div style="display: flex; align-items: baseline; gap: 6px;">
                            <span id="cal-flex-count" style="font-size: 2.2rem; font-weight: 900; color: var(--primary-red); line-height: 1;">0</span>
                            <span style="font-weight: 700; color: #4a5568; font-size: 0.95rem;">dias</span>
                        </div>
                    </div>
                </div>
                <div style="display: flex; gap: 15px;">
                    <button type="button" class="btn-secondary-v4" onclick="clearFlexDates()" style="padding: 14px 24px; border-radius: 14px; border: 1px solid #e2e8f0; background: white; font-weight: 700; color: #718096; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-eraser"></i> Limpar
                    </button>
                    <button type="button" class="btn btn-primary" onclick="confirmFlexDates()" style="padding: 14px 35px; border-radius: 14px; font-weight: 800; box-shadow: 0 10px 20px rgba(237,28,36,0.25); display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle"></i> Confirmar Seleção
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Segmented Control Moderno */
.agenda-type-toggle-v4 { display: flex; background: var(--bg-color); padding: 4px; border-radius: 14px; border: 1px solid var(--border-color); position: relative; width: fit-content; box-shadow: inset 0 2px 4px rgba(0,0,0,0.03); }
.toggle-option { position: relative; cursor: pointer; flex: 1; min-width: 130px; }
.toggle-option input { position: absolute; opacity: 0; width: 0; height: 0; }
.toggle-option .option-content { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 10px 18px; border-radius: 12px; font-size: 0.85rem; font-weight: 700; color: var(--text-muted); transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1); }
.toggle-option input:checked + .option-content { background: var(--card-bg); color: var(--primary-red); box-shadow: 0 4px 12px rgba(0,0,0,0.08); transform: translateY(-1px); }
[data-tema="escuro"] .toggle-option input:checked + .option-content { box-shadow: 0 4px 12px rgba(0,0,0,0.4); }
.toggle-option input:checked + .option-content i { color: var(--primary-red); }
.toggle-option:hover:not(:checked) .option-content { color: var(--primary-red); background: rgba(237,28,36,0.02); }

/* Calendário Grid Premium */
.cal-flex-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px; padding: 10px; }
.cal-month-v4 { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 20px; padding: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.03); transition: transform 0.3s ease; }
[data-tema="escuro"] .cal-month-v4 { box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
.cal-month-v4:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0,0,0,0.05); }
.cal-month-title { text-align: center; font-weight: 800; margin-bottom: 24px; color: var(--text-color); text-transform: uppercase; font-size: 0.9rem; letter-spacing: 1.2px; border-bottom: 2px solid var(--bg-color); padding-bottom: 12px; }
.cal-days-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px; text-align: center; }
.cal-day-name { font-size: 0.75rem; font-weight: 800; color: var(--text-muted); padding-bottom: 12px; }
.cal-day-cell { aspect-ratio: 1; display: flex; align-items: center; justify-content: center; font-size: 0.95rem; font-weight: 700; border-radius: 12px; cursor: pointer; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); position: relative; color: var(--text-color); }
.cal-day-cell:hover:not(.empty) { background: rgba(237,28,36,0.05); color: var(--primary-red); transform: scale(1.15); z-index: 2; box-shadow: 0 4px 12px rgba(237,28,36,0.15); }
.cal-day-cell.selected { background: var(--primary-red); color: white !important; box-shadow: 0 8px 20px rgba(237,28,36,0.4); transform: scale(1.05); z-index: 1; }
.cal-day-cell.today::after { content: ''; position: absolute; bottom: 6px; width: 4px; height: 4px; background: var(--primary-red); border-radius: 50%; }
.cal-day-cell.selected.today::after { background: white !important; }
.cal-day-cell.weekend { color: var(--text-muted); background: var(--bg-color); opacity: 0.6; }
.cal-day-cell.holiday { color: #f56565; background: rgba(245, 101, 101, 0.05); font-weight: 800; }
.cal-day-cell.holiday::before { content: '•'; position: absolute; top: 2px; right: 2px; font-size: 10px; }
.cal-day-cell.blocked { cursor: not-allowed; opacity: 0.4; background: var(--bg-color); pointer-events: auto; }
.cal-day-cell.blocked:hover { transform: none !important; box-shadow: none !important; color: inherit !important; background: var(--bg-color) !important; }
.cal-day-cell.empty { cursor: default; }

/* Preview de Datas Moderno */
.selected-dates-group { margin-bottom: 15px; width: 100%; }
.group-title { font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; display: flex; align-items: center; gap: 8px; }
.group-title::after { content: ''; flex: 1; height: 1px; background: var(--border-color); }
.date-pill-v4 { background: var(--card-bg); border: 1px solid var(--border-color); padding: 6px 12px; border-radius: 10px; font-size: 0.8rem; font-weight: 700; color: var(--text-color); display: flex; align-items: center; gap: 8px; transition: all 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.02); animation: pillEntry 0.3s ease backwards; }
@keyframes pillEntry { from { opacity: 0; transform: scale(0.8); } to { opacity: 1; transform: scale(1); } }
.date-pill-v4:hover { border-color: var(--primary-red); color: var(--primary-red); transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.05); }
.date-pill-v4 i { color: var(--text-muted); cursor: pointer; font-size: 0.8rem; transition: color 0.2s; }
.date-pill-v4:hover i { color: var(--primary-red); }

.cal-nav-v4 { background: var(--card-bg) !important; border: 1px solid var(--border-color); box-shadow: 0 4px 12px rgba(0,0,0,0.03); color: var(--text-color); }
.btn-nav-cal { border: none !important; background: var(--bg-color) !important; color: var(--text-color) !important; }
.btn-nav-cal:hover { background: var(--primary-red) !important; color: white !important; transform: scale(1.05); }

[data-tema="escuro"] .cal-nav-v4 span { color: var(--text-color) !important; }
[data-tema="escuro"] .cal-month-v4 { border-color: #334155; }
[data-tema="escuro"] .date-pill-v4 { background: #1e293b; border-color: #334155; }
[data-tema="escuro"] .modal-footer-flex { background: #1a2233 !important; border-color: #334155 !important; }
</style>

<script>
// --- Script Unificado Adaptado ---
(function() {
    // Garante que as variáveis sejam arrays mesmo que o PHP falhe
    const docentesData = <?= json_encode($docentes ?: [], JSON_UNESCAPED_UNICODE) ?> || [];
    const feriadosData = <?= json_encode($feriados_data ?: [], JSON_UNESCAPED_UNICODE) ?> || [];
    
    // Lista plana de cursos para facilitar a busca
    const allCursos = [];
    <?php foreach ($grouped_cursos_ag as $area => $lista): ?>
        <?php foreach ($lista as $c): ?>
            allCursos.push({
                id: "<?= $c['id'] ?>",
                nome: "<?= addslashes($c['nome']) ?>",
                area: "<?= addslashes($c['area'] ?: 'Outros') ?>",
                tipo: "<?= addslashes($c['tipo'] ?: 'FIC') ?>",
                ch: <?= (int)$c['carga_horaria_total'] ?>
            });
        <?php endforeach; ?>
    <?php endforeach; ?>

    let selectedDocentes = [];
    let flexSelectedDates = [];
    let calStartMonth = new Date();
    calStartMonth.setDate(1);

    window.toggleAgendaTypeUnified = function() {
        const type = document.querySelector('input[name="tipo_agenda"]:checked').value;
        const rec = document.getElementById('container-agenda-recorrente');
        const flex = document.getElementById('container-agenda-flexivel');
        
        const di = document.getElementById('data-inicio-unified');
        const df = document.getElementById('data-fim-unified');

        if (type === 'recorrente') {
            rec.style.display = 'block';
            flex.style.display = 'none';
            document.getElementById('calc-auto-container-unified').style.display = 'flex';
            // Se mudou para recorrente, as datas serão recalculadas pela função calcularDataFimUnified()
        } else {
            rec.style.display = 'none';
            flex.style.display = 'block';
            document.getElementById('calc-auto-container-unified').style.display = 'none';
            // Se mudou para flexível, garante que as datas venham do calendário
            updateFlexPreview();
        }
        calcularDataFimUnified();
    }

    window.openFlexibleCalendar = function() {
        const modal = document.getElementById('modal-agenda-flexivel');
        const input = document.getElementById('agenda-flexivel-input');
        
        if (input.value) {
            flexSelectedDates = input.value.split(',').filter(d => d);
        } else {
            flexSelectedDates = [];
        }

        // Se tiver datas, tenta focar o calendário no mês da primeira data
        if (flexSelectedDates.length > 0) {
            calStartMonth = new Date(flexSelectedDates[0] + 'T12:00:00');
            calStartMonth.setDate(1);
        } else {
            calStartMonth = new Date();
            calStartMonth.setDate(1);
        }

        modal.classList.add('active');
        // Pequeno delay para garantir que o modal esteja visível antes de renderizar
        setTimeout(() => {
            renderFlexCalendarGrid();
        }, 50);
    }

    window.closeFlexibleCalendar = function() {
        document.getElementById('modal-agenda-flexivel').classList.remove('active');
    }

    window.changeCalMonths = function(offset) {
        calStartMonth.setMonth(calStartMonth.getMonth() + offset);
        renderFlexCalendarGrid();
    }

    window.renderFlexCalendarGrid = function() {
        const container = document.getElementById('cal-flex-container');
        const rangeEl = document.getElementById('cal-current-range');
        const countEl = document.getElementById('cal-flex-count');
        container.innerHTML = '';
        
        const months = [];
        let tempDate = new Date(calStartMonth);
        for(let i=0; i<3; i++) {
            months.push(new Date(tempDate));
            tempDate.setMonth(tempDate.getMonth() + 1);
        }

        const monthNames = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
        rangeEl.innerText = `${monthNames[months[0].getMonth()]} / ${months[0].getFullYear()} - ${monthNames[months[2].getMonth()]} / ${months[2].getFullYear()}`;
        countEl.innerText = flexSelectedDates.length;

        months.forEach(mDate => {
            const year = mDate.getFullYear();
            const month = mDate.getMonth();
            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            
            const monthDiv = document.createElement('div');
            monthDiv.className = 'cal-month-v4';
            
            let html = `<div class="cal-month-title">${monthNames[month]} ${year}</div>`;
            html += `<div class="cal-days-grid">`;
            ['D','S','T','Q','Q','S','S'].forEach(d => html += `<div class="cal-day-name">${d}</div>`);
            
            // Células vazias iniciais
            for(let i=0; i<firstDay; i++) html += `<div class="cal-day-cell empty"></div>`;
            
            for(let day=1; day<=daysInMonth; day++) {
                const dateISO = `${year}-${String(month+1).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
                const isSelected = flexSelectedDates.includes(dateISO);
                const isToday = new Date().toISOString().slice(0,10) === dateISO;
                const dow = new Date(year, month, day).getDay();
                const isWeekend = (dow === 0 || dow === 6);
                
                // Verifica feriado
                const isHoliday = feriadosData.some(f => f.tipo === 'HOLIDAY' && dateISO >= f.data_inicio && dateISO <= f.data_fim);
                const isBlocked = (dow === 0 || isHoliday); // Bloqueia Domingos e Feriados
                
                html += `<div class="cal-day-cell ${isSelected ? 'selected' : ''} ${isToday ? 'today' : ''} ${isWeekend ? 'weekend' : ''} ${isHoliday ? 'holiday' : ''} ${isBlocked ? 'blocked' : ''}" 
                            onclick="toggleFlexDate('${dateISO}', ${isBlocked})" title="${isHoliday ? 'Feriado' : (dow === 0 ? 'Domingo' : '')}">${day}</div>`;
            }
            
            html += `</div>`;
            monthDiv.innerHTML = html;
            container.appendChild(monthDiv);
        });
    }

    window.toggleFlexDate = function(dateISO, isBlocked) {
        if (isBlocked) {
            if (window.showNotification) {
                window.showNotification('Não é possível selecionar domingos ou feriados.', 'warning');
            } else {
                alert('Não é possível selecionar domingos ou feriados.');
            }
            return;
        }

        const idx = flexSelectedDates.indexOf(dateISO);
        if (idx > -1) {
            flexSelectedDates.splice(idx, 1);
        } else {
            flexSelectedDates.push(dateISO);
        }
        flexSelectedDates.sort();
        renderFlexCalendarGrid();
    }

    window.clearFlexDates = function() {
        if(confirm('Deseja limpar todas as datas selecionadas?')) {
            flexSelectedDates = [];
            renderFlexCalendarGrid();
        }
    }

    window.confirmFlexDates = function() {
        const input = document.getElementById('agenda-flexivel-input');
        
        // Filtro de segurança final: remove domingos e feriados
        const cleanDates = flexSelectedDates.filter(dateISO => {
            const dateObj = new Date(dateISO + 'T12:00:00');
            const dow = dateObj.getDay();
            const isHoliday = feriadosData.some(f => f.tipo === 'HOLIDAY' && dateISO >= f.data_inicio && dateISO <= f.data_fim);
            return dow !== 0 && !isHoliday;
        });

        input.value = cleanDates.join(',');
        flexSelectedDates = cleanDates;
        
        updateFlexPreview();
        closeFlexibleCalendar();
        calcularDataFimUnified();
    }

    function updateFlexPreview() {
        const preview = document.getElementById('selected-dates-preview');
        const input = document.getElementById('agenda-flexivel-input');
        if (!preview || !input) return;
        
        const countDisplay = document.getElementById('flex-date-count');
        const countBadge = document.getElementById('flex-count-badge');
        
        if (!input.value) {
            preview.innerHTML = `<div style="color: var(--text-muted); font-size: 0.9rem; padding: 20px; border: 2px dashed var(--border-color); border-radius: 16px; width: 100%; text-align: center; background: var(--bg-color);">Nenhuma data selecionada</div>`;
            if (countDisplay) countDisplay.innerText = '0';
            if (countBadge) countBadge.style.display = 'none';
            return;
        }

        const allDates = input.value.split(',').filter(d => d).sort();
        
        // Filtra datas bloqueadas no preview também
        const dates = allDates.filter(dateISO => {
            const dateObj = new Date(dateISO + 'T12:00:00');
            const dow = dateObj.getDay();
            const isHoliday = feriadosData.some(f => f.tipo === 'HOLIDAY' && dateISO >= f.data_inicio && dateISO <= f.data_fim);
            return dow !== 0 && !isHoliday;
        });

        if (dates.length !== allDates.length) {
            input.value = dates.join(',');
            flexSelectedDates = dates;
        }

        if (countDisplay) countDisplay.innerText = dates.length;
        if (countBadge) countBadge.style.display = 'inline-block';

        // Sincroniza datas de início e fim
        if (dates.length > 0) {
            const di = document.getElementById('data-inicio-unified');
            const df = document.getElementById('data-fim-unified');
            
            // Garante que o input escondido e o estado também estejam atualizados e ordenados
            flexSelectedDates = [...dates].sort();
            input.value = flexSelectedDates.join(',');

            if (di) di.value = flexSelectedDates[0];
            if (df) df.value = flexSelectedDates[flexSelectedDates.length - 1];
        }

        preview.innerHTML = '';
        
        // Agrupar por Mês
        const monthsMap = {};
        const monthNames = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
        const dayNames = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];

        dates.forEach(dateISO => {
            const dateObj = new Date(dateISO + 'T12:00:00');
            const mKey = `${monthNames[dateObj.getMonth()]} ${dateObj.getFullYear()}`;
            if (!monthsMap[mKey]) monthsMap[mKey] = [];
            monthsMap[mKey].push({
                iso: dateISO,
                label: `${dayNames[dateObj.getDay()]} ${String(dateObj.getDate()).padStart(2,'0')}/${String(dateObj.getMonth()+1).padStart(2,'0')}`
            });
        });

        for (const month in monthsMap) {
            const group = document.createElement('div');
            group.className = 'selected-dates-group';
            group.innerHTML = `<div class="group-title">${month}</div><div style="display: flex; flex-wrap: wrap; gap: 8px;"></div>`;
            const container = group.querySelector('div:last-child');
            
            monthsMap[month].forEach(d => {
                const pill = document.createElement('div');
                pill.className = 'date-pill-v4';
                pill.innerHTML = `<span>${d.label}</span> <i class="fas fa-times" onclick="removeSingleFlexDate('${d.iso}')"></i>`;
                container.appendChild(pill);
            });
            preview.appendChild(group);
        }

        if (dates.length > 2) {
            const clearBtn = document.createElement('button');
            clearBtn.type = 'button';
            clearBtn.className = 'btn-action-v4';
            clearBtn.style.cssText = 'background: rgba(237,28,36,0.08); color: var(--primary-red); margin-top: 15px; width: 100%; border: 1px solid rgba(237,28,36,0.2); font-size: 0.8rem; justify-content: center; font-weight: 800; height: 45px; border-radius: 12px; transition: all 0.2s;';
            clearBtn.innerHTML = '<i class="fas fa-trash-alt"></i> LIMPAR TODAS AS DATAS';
            clearBtn.onmouseover = () => {
                clearBtn.style.background = 'var(--primary-red)';
                clearBtn.style.color = 'white';
            };
            clearBtn.onmouseout = () => {
                clearBtn.style.background = 'rgba(237,28,36,0.08)';
                clearBtn.style.color = 'var(--primary-red)';
            };
            clearBtn.onclick = () => {
                if(confirm('Limpar todas as datas?')) {
                    input.value = '';
                    flexSelectedDates = [];
                    updateFlexPreview();
                    calcularDataFimUnified();
                }
            };
            preview.appendChild(clearBtn);
        }
    }

    window.removeSingleFlexDate = function(dateISO) {
        const input = document.getElementById('agenda-flexivel-input');
        let dates = input.value.split(',').filter(d => d !== dateISO);
        input.value = dates.join(',');
        flexSelectedDates = dates;
        updateFlexPreview();
        calcularDataFimUnified();
    }

    document.addEventListener('DOMContentLoaded', function() {
        updateFlexPreview();
        toggleAgendaTypeUnified();
    });

    let modalDoc, searchInput, resultsContainer, areaFilter;
    let modalCurso, cursoSearchInput, cursoResultsContainer, cursoAreaFilter, cursoTipoFilter;

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
        const cursoId = document.getElementById('curso-id-unified')?.value;
        renderSelectedDocentes(cursoId);
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
        const cursoId = document.getElementById('curso-id-unified')?.value;
        renderSelectedDocentes(cursoId);
        calcularDataFimUnified();
    }

    // --- LÓGICA DE CURSOS ---
    function renderModalCursosUnified() {
        if (!cursoResultsContainer || !cursoSearchInput) return;
        const query = normalizeString(cursoSearchInput.value);
        const area = cursoAreaFilter ? cursoAreaFilter.value : '';
        const tipo = cursoTipoFilter ? cursoTipoFilter.value : '';

        let filtered = allCursos.filter(c => {
            const matchesName = !query || normalizeString(c.nome).includes(query);
            const matchesArea = !area || c.area === area;
            const matchesTipo = !tipo || c.tipo === tipo;
            return matchesName && matchesArea && matchesTipo;
        });

        if (filtered.length === 0) {
            cursoResultsContainer.innerHTML = '<div style="text-align:center; padding:20px; color:#888;">Nenhum curso encontrado.</div>';
            return;
        }

        cursoResultsContainer.innerHTML = filtered.map(c => `
            <div class="curso-result-item" data-id="${c.id}" 
                style="padding: 15px; border-bottom: 1px solid var(--border-color); cursor: pointer; display: flex; justify-content: space-between; align-items: center; transition: all 0.2s;">
                <div style="flex: 1;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-weight: 700; font-size: 1rem; color: var(--text-color);">${c.nome}</span>
                        <span style="font-size: 0.7rem; background: rgba(237,28,36,0.1); color: var(--primary-red); padding: 2px 6px; border-radius: 4px; font-weight: 800;">${c.tipo}</span>
                    </div>
                    <div style="font-size: 0.8rem; color: #888; margin-top: 6px; display: flex; align-items: center; gap: 10px;">
                        <span><i class="fas fa-layer-group" style="opacity: 0.6;"></i> ${c.area}</span>
                        <span style="background: rgba(33, 150, 243, 0.1); color: #1976d2; padding: 2px 8px; border-radius: 4px; font-weight: 700; font-size: 0.75rem;">
                            <i class="fas fa-clock" style="font-size: 0.7rem;"></i> ${c.ch} HORAS
                        </span>
                    </div>
                </div>
                <i class="fas fa-chevron-right" style="color: #ccc; font-size: 0.9rem;"></i>
            </div>
        `).join('');

        cursoResultsContainer.querySelectorAll('.curso-result-item').forEach(item => {
            item.onmouseover = function() { this.style.background = 'var(--bg-hover)'; };
            item.onmouseout = function() { this.style.background = 'transparent'; };
            item.onclick = function() {
                const id = this.dataset.id;
                const curso = allCursos.find(c => String(c.id) === String(id));
                if (curso) {
                    selectCursoUnified(curso);
                }
            };
        });
    }

    function selectCursoUnified(curso) {
        const idInput = document.getElementById('curso-id-unified');
        const display = document.getElementById('curso-nome-display-unified');
        
        if (idInput) {
            idInput.value = curso.id;
            // Armazena CH no dataset para o cálculo de data fim
            idInput.dataset.ch = curso.ch;
        }
        if (display) {
            display.innerHTML = `<span style="color:var(--text-color)">${curso.nome} (${curso.tipo})</span> <span style="margin-left:8px; background:rgba(33,150,243,0.1); color:#1976d2; padding:3px 10px; border-radius:6px; font-weight:800; font-size:0.8rem;">${curso.ch} HORAS</span>`;
        }
        
        if (modalCurso) modalCurso.classList.remove('active');
        
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
        const cursoIdInput = document.getElementById('curso-id-unified');
        const periodoSelect = document.getElementById('periodo-select-unified');
        const dataInicio = document.getElementById('data-inicio-unified');
        const dataFim = document.getElementById('data-fim-unified');
        const infoEl = document.getElementById('data-fim-info-unified');
        const btnSalvar = document.getElementById('btn-salvar-unified');

        if (!cursoIdInput || !periodoSelect || !dataInicio) return;

        const ch = parseInt(cursoIdInput.dataset.ch) || 0;
        const periodo = periodoSelect.value;
        const inicio = dataInicio.value;
        const diasChecked = Array.from(document.querySelectorAll('#dias-semana-container-unified input[name="dias_semana[]"]:checked')).map(cb => cb.value);

        const h_ini = document.getElementById('horario_inicio_unified');
        const h_fim = document.getElementById('horario_fim_unified');
        if (periodoSelect.dataset.lastPeriod !== periodo) {
            const periodDefaults = {
                'Manhã': ['07:30', '11:30'],
                'Tarde': ['13:30', '17:30'],
                'Noite': ['18:00', '23:00'],
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

        const agendaType = document.querySelector('input[name="tipo_agenda"]:checked')?.value;

        // --- AJUSTE AGENDA FLEXÍVEL (Sincronização Manual) ---
        if (agendaType === 'flexivel') {
            const flexInput = document.getElementById('agenda-flexivel-input');
            const datesArr = flexInput.value.split(',').filter(d => d).sort();
            if (datesArr.length > 0) {
                if (dataInicio) dataInicio.value = datesArr[0];
                if (dataFim) dataFim.value = datesArr[datesArr.length - 1];
                if (infoEl) {
                    infoEl.innerHTML = `<i class="fas fa-calendar-check"></i> <strong>${datesArr.length} datas específicas</strong> selecionadas manualmente.`;
                }
            } else {
                if (dataFim) dataFim.value = '';
                if (infoEl) infoEl.innerHTML = '<span style="color:var(--primary-red)">Selecione as datas no calendário.</span>';
            }
            return; // No modo flexível, o cálculo automático por carga horária é ignorado
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
            let rawHours = ((h2 * 60 + m2) - (h1 * 60 + m1)) / 60;
            
            if (periodo === 'Integral') {
                if (rawHours > 4) rawHours -= 2; // Almoço (11:30 - 13:30)
                horasPorDia = Math.min(8, rawHours);
            } else {
                horasPorDia = Math.min(4, rawHours);
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

        // Inicialização de Cursos
        modalCurso = document.getElementById('modal-selecionar-curso-unified');
        cursoSearchInput = document.getElementById('curso-search-input-unified');
        cursoResultsContainer = document.getElementById('curso-search-results-unified');
        cursoAreaFilter = document.getElementById('curso-area-filter-unified');
        cursoTipoFilter = document.getElementById('curso-tipo-filter-unified');

        const btnAbrirCurso = document.getElementById('btn-abrir-modal-cursos-unified');
        if (btnAbrirCurso) {
            btnAbrirCurso.onclick = () => {
                if (modalCurso) {
                    modalCurso.classList.add('active');
                    if (cursoSearchInput) {
                        cursoSearchInput.value = '';
                        renderModalCursosUnified();
                        setTimeout(() => cursoSearchInput.focus(), 100);
                    }
                }
            };
        }

        if (cursoSearchInput) cursoSearchInput.oninput = renderModalCursosUnified;
        if (cursoAreaFilter) cursoAreaFilter.onchange = renderModalCursosUnified;
        if (cursoTipoFilter) cursoTipoFilter.onchange = renderModalCursosUnified;

        const fields = ['periodo-select-unified', 'data-inicio-unified', 'horario_inicio_unified', 'horario_fim_unified'];
        fields.forEach(id => {
            const el = document.getElementById(id);
            if(el) el.addEventListener('change', calcularDataFimUnified);
        });
        document.querySelectorAll('#dias-semana-container-unified input[name="dias_semana[]"]').forEach(cb => {
            cb.addEventListener('change', calcularDataFimUnified);
        });

        updateFlexPreview();
        toggleAgendaTypeUnified();

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
            // Adicionar campo hidden para send_email se não existir
            if (!document.getElementById('send_email_unified')) {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'send_email';
                hidden.id = 'send_email_unified';
                hidden.value = '0';
                form.appendChild(hidden);
            }

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

                // --- MODAL DE E-MAIL (Somente se NÃO for simulação e NÃO for edição) ---
                const isEdit = document.getElementById('unified-id')?.value !== '';
                if (!isSimulation && !isEdit) {
                    const swalResult = await Swal.fire({
                        title: 'Confirmar Cadastro',
                        text: "Deseja cadastrar e enviar uma notificação por e-mail para os docentes selecionados?",
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#2e7d32',
                        cancelButtonColor: '#d33',
                        confirmButtonText: '<i class="fas fa-envelope"></i> Cadastrar e Enviar E-mail',
                        denyButtonText: '<i class="fas fa-save"></i> Cadastrar sem E-mail',
                        showDenyButton: true,
                        cancelButtonText: 'Cancelar'
                    });

                    if (swalResult.isDismissed) return; // Cancelado
                    
                    document.getElementById('send_email_unified').value = swalResult.isConfirmed ? '1' : '0';
                } else {
                    const emailInput = document.getElementById('send_email_unified');
                    if (emailInput) emailInput.value = '0';
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
                                } else {
                                    // Se não estiver em modal, redireciona para a URL de retorno ou turmas.php
                                    const returnUrl = document.getElementsByName('return_url')[0]?.value;
                                    window.location.href = result.redirect || returnUrl || 'turmas.php';
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
            
            const cursoIdInput = document.getElementById('curso-id-unified');
            if(cursoIdInput) {
                cursoIdInput.required = !data.is_reserva;
            }

            if (data.curso_id) {
                const curso = allCursos.find(c => String(c.id) === String(data.curso_id));
                if (curso) {
                    if (cursoIdInput) {
                        cursoIdInput.value = curso.id;
                        cursoIdInput.dataset.ch = curso.ch;
                    }
                    const display = document.getElementById('curso-nome-display-unified');
                    if (display) display.innerText = `${curso.nome} (${curso.tipo})`;
                }
            } else {
                // Reset curso if none
                const display = document.getElementById('curso-nome-display-unified');
                if (display) display.innerText = "Clique para selecionar o curso...";
                if (cursoIdInput) {
                    cursoIdInput.value = "";
                    cursoIdInput.dataset.ch = 0;
                }
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
        
        if (data.tipo_agenda) {
            const radios = document.querySelectorAll('input[name="tipo_agenda"]');
            radios.forEach(r => {
                if(r.value === data.tipo_agenda) r.checked = true;
            });
            toggleAgendaTypeUnified();
        }
        if (data.agenda_flexivel) {
            const input = document.getElementById('agenda-flexivel-input');
            if(input) input.value = data.agenda_flexivel;
            updateFlexPreview();
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
