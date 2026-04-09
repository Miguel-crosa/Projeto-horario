<?php if (!isset($_GET['ajax_render'])): ?>
<link rel="stylesheet" href="../../css/agenda_professores.css">
<style>
    .slot-disabled {
        pointer-events: none !important;
        cursor: default !important;
    }
</style>
<?php endif; ?>

<?php
$selected_prof_id = isset($_GET['docente_id']) ? $_GET['docente_id'] : '';
$selected_prof_nome = '';

/* Fallback: If no docente_id is provided but we only have 1 professor (e.g. from a name search)
if (empty($selected_prof_id) && !empty($professores) && count($professores) === 1) {
    $selected_prof_id = $professores[0]['id'];
    $selected_prof_nome = $professores[0]['nome'];
} else */ if (empty($selected_prof_id) && !empty($_GET['search'])) {
    // Second fallback: Try to match by name if search is present
    foreach ($docentes as $d) {
        if (trim(strtolower($d['nome'])) === trim(strtolower(trim($_GET['search'])))) {
            $selected_prof_id = $d['id'];
            $selected_prof_nome = $d['nome'];
            break;
        }
    }
} else if ($selected_prof_id) {
    // Standard lookup
    foreach ($docentes as $d) {
        if ($d['id'] == $selected_prof_id) {
            $selected_prof_nome = $d['nome'];
            break;
        }
    }
}
?>

<?php
$docente_param = $selected_prof_id ? '&docente_id=' . $selected_prof_id : '';
if (empty($selected_prof_id) && !empty($_GET['search'])) {
    $docente_param .= '&search=' . urlencode($_GET['search']);
}
?>

<?php if (!isset($_GET['ajax_render'])): ?>
<div class="page-header agenda-header-container">
    <div>
        <h2><i class="fas fa-calendar-check"></i> Agenda de Professores</h2>
        <div class="view-selector-wrapper">
            <div class="view-selector">
                <a href="?view_mode=timeline&month=<?php echo $current_month; ?><?php echo $docente_param; ?>" class="view-btn <?php echo $view_mode == 'timeline' ? 'active' : ''; ?>"><i class="fas fa-grip-lines"></i> Timeline</a>
                <a href="?view_mode=blocks&month=<?php echo $current_month; ?><?php echo $docente_param; ?>" class="view-btn <?php echo $view_mode == 'blocks' ? 'active' : ''; ?>"><i class="fas fa-layer-group"></i> Blocos</a>
                <a href="?view_mode=calendar&month=<?php echo $current_month; ?><?php echo $docente_param; ?>" class="view-btn <?php echo $view_mode == 'calendar' ? 'active' : ''; ?>"><i class="fas fa-th-large"></i> Calendário</a>
                <a href="?view_mode=semestral&month=<?php echo $current_month; ?><?php echo $docente_param; ?>" class="view-btn <?php echo $view_mode == 'semestral' ? 'active' : ''; ?>"><i class="fas fa-calendar-week"></i> Semestral</a>
            </div>
        </div>
    </div>
</div>

<!-- Seção Unificada de Seleção e Navegação Mensal -->
<div class="card prof-selection-card">
    <div class="prof-selection-flex">
        <div class="prof-selector-group">
            <label class="period-label-text">Professor Selecionado</label>
            <?php if (!false): ?>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <button type="button" class="btn btn-primary" id="btn-selecionar-professor"
                        style="background: <?= $selected_prof_id ? '#2e7d32' : '#ed1c16' ?>; border-color: <?= $selected_prof_id ? '#1b5e20' : '#ed1c16' ?>; padding: 10px 24px; font-weight: 700; border-radius: 8px; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-user-plus"></i>
                        <span id="btn-prof-label"><?= $selected_prof_id ? htmlspecialchars($selected_prof_nome) : 'Selecionar Professor' ?></span>
                    </button>
                </div>
            <?php else: ?>
                <div style="font-weight: 800; font-size: 1.1rem; color: var(--text-color);">
                    <?= htmlspecialchars(getUserName()) ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="month-nav-group" <?php if ($view_mode == 'calendar'): ?>style="display:none;"<?php endif; ?>>
            <label class="period-label-text">Período de Exibição</label>
            <div class="month-nav-controls" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <!-- Seletor de Mês -->
                <div class="sub-nav-group" style="display: flex; align-items: center; gap: 8px;">
                    <button onclick="navigateMonth(-1)" class="month-btn-nav" title="Mês Anterior" style="width:32px;height:32px;text-decoration:none;color:var(--corTxt3); border:none; display: flex; align-items: center; justify-content: center; background: var(--card-bg); border-radius: 50%; border: 1px solid var(--border-color); cursor:pointer; transition: all 0.2s;"><i class="fas fa-chevron-left" style="font-size:0.75rem;"></i></button>
                    <span id="global-month-label" style="font-weight: 800; font-size: 0.95rem; min-width: 110px; text-align: center; text-transform: capitalize; color: var(--text-color);"><?php echo $months_pt[$m_num]; ?></span>
                    <button onclick="navigateMonth(1)" class="month-btn-nav" title="Próximo Mês" style="width:32px;height:32px;text-decoration:none;color:var(--corTxt3); border:none; display: flex; align-items: center; justify-content: center; background: var(--card-bg); border-radius: 50%; border: 1px solid var(--border-color); cursor:pointer; transition: all 0.2s;"><i class="fas fa-chevron-right" style="font-size:0.75rem;"></i></button>
                </div>

                <div style="width: 1px; height: 24px; background: var(--border-color);"></div>

                <!-- NOVO: Seletor de Ano -->
                <div class="sub-nav-group" style="display: flex; align-items: center; gap: 8px;">
                    <button onclick="navigateYear(-1)" class="month-btn-nav" title="Ano Anterior" style="width:32px;height:32px;text-decoration:none;color:var(--corTxt3); border:none; display: flex; align-items: center; justify-content: center; background: var(--card-bg); border-radius: 50%; border: 1px solid var(--border-color); cursor:pointer; transition: all 0.2s;"><i class="fas fa-angle-double-left" style="font-size:0.75rem;"></i></button>
                    <span id="global-year-label" style="font-weight: 800; font-size: 0.95rem; min-width: 50px; text-align: center; color: var(--text-color); cursor:pointer;" onclick="openGlobalAnoModal()"><?php echo $m_year; ?></span>
                    <button onclick="navigateYear(1)" class="month-btn-nav" title="Próximo Ano" style="width:32px;height:32px;text-decoration:none;color:var(--corTxt3); border:none; display: flex; align-items: center; justify-content: center; background: var(--card-bg); border-radius: 50%; border: 1px solid var(--border-color); cursor:pointer; transition: all 0.2s;"><i class="fas fa-angle-double-right" style="font-size:0.75rem;"></i></button>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="availability-section availability-container" id="availability-section">
    <div class="period-selector-container period-selector-wrapper">
        <div style="display: flex; align-items: center; gap: 15px;">
            <span class="period-label-text">Pré-definir Período:</span>
            <div class="period-btns period-btns-group">
                <button type="button" class="period-btn period-btn-all period-btn-style" data-periodo="" id="btn-todos">
                    <i class="fas fa-th" style="font-size: 0.9rem;"></i> Todos
                </button>
                <button type="button" class="period-btn period-btn-style" data-periodo="Manhã" data-inicio="07:30" data-fim="11:30" id="btn-manha">
                    <i class="fas fa-sun" style="font-size: 0.9rem;"></i> Manhã
                </button>
                <button type="button" class="period-btn period-btn-style" data-periodo="Tarde" data-inicio="13:30" data-fim="17:30" id="btn-tarde">
                    <i class="fas fa-cloud-sun" style="font-size: 0.9rem;"></i> Tarde
                </button>
                <button type="button" class="period-btn period-btn-style" data-periodo="Noite" data-inicio="19:30" data-fim="23:30" id="btn-noite">
                    <i class="fas fa-moon" style="font-size: 0.9rem;"></i> Noite
                </button>
                <button type="button" class="period-btn period-btn-style" data-periodo="Integral" data-inicio="07:30" data-fim="17:30" id="btn-integral">
                    <i class="fas fa-circle" style="font-size: 0.9rem;"></i> Integral
                </button>
            </div>
        </div>
        <div class="avail-legend period-legend-group" style="flex-wrap: wrap; justify-content: flex-start;">
            <span style="display: flex; align-items: center; gap: 5px; font-size: 0.75rem;"><span class="avail-dot" style="width: 8px; height: 8px; border-radius: 50%; background: #4caf50;"></span> Disp.</span>
            <span style="display: flex; align-items: center; gap: 5px; font-size: 0.75rem;"><span class="avail-dot" style="width: 8px; height: 8px; border-radius: 50%; background: #f44336;"></span> Ocup.</span>
            <span style="display: flex; align-items: center; gap: 5px; font-size: 0.75rem;"><span class="avail-dot" style="width: 8px; height: 8px; border-radius: 50%; background: #ffb300;"></span> Res.</span>
            <span style="display: flex; align-items: center; gap: 5px; font-size: 0.75rem;"><span class="avail-dot" style="width: 8px; height: 8px; border-radius: 50%; background: #1565c0;"></span> Feriado</span>
        </div>
    </div>

    <div class="action-buttons-group">
        <button type="button" class="btn btn-primary" id="btn-modo-reserva-unificado" style="background: #ff8f00; border-color: #e65100; font-size: 0.8rem; padding: 10px 20px; border-radius: 8px; font-weight: 700;" onclick="handleModoReservaClick()">
            <i class="fas fa-bookmark" style="margin-right: 8px;"></i> Modo Reserva
        </button>
        <button type="button" class="btn btn-primary" id="btn-confirmar-reserva" style="display: none; background: #2e7d32; border-color: #1b5e20; font-size: 0.8rem; padding: 10px 20px; border-radius: 8px; font-weight: 700;" onclick="confirmReservations()">
            <i class="fas fa-check" style="margin-right: 8px;"></i> Confirmar Reserva
        </button>
        <?php if (isAdmin()): ?>
            <button type="button" class="btn btn-secondary" id="btn-remover-selecionados" style="display: none; background: #d32f2f; border-color: #c62828; font-size: 0.8rem; padding: 10px 20px; border-radius: 8px; font-weight: 700;" onclick="batchRemoveReservations()">
                <i class="fas fa-trash-alt" style="margin-right: 8px;"></i> Remover Selecionados
            </button>
        <?php endif; ?>
        <button type="button" class="btn btn-back" id="btn-cancelar-reserva" style="display: none; font-size: 0.8rem; padding: 10px 20px; border-radius: 8px; font-weight: 700;" onclick="handleCancelarReservaClick()">
            <i class="fas fa-times" style="margin-right: 8px;"></i> Cancelar
        </button>
    </div>

    <div id="availability-bar" class="avail-bar-container avail-bar-outer">
        <div class="avail-bar-track" style="height: 100%; display: flex;">
            <div class="avail-bar-free" style="width: 100%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 800; color: var(--text-color);">Selecione um professor</div>
        </div>
    </div>

    <div class="avail-footer avail-status-footer">
        <div id="avail-status-text" style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted);"></div>
        <?php if (isGestor() || isAdmin()): ?>
        <button class="btn btn-agendar-bar" id="btn-agendar-bar" onclick="openCalendarScheduleModal()" style="display: none; background: #2196f3; border-color: #1976d2; color: #fff; padding: 8px 20px; border-radius: 8px; font-size: 0.8rem; font-weight: 700; align-items: center; gap: 8px;">
            <i class="fas fa-plus-circle"></i> Cadastrar Horário
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="filter-header" style="justify-content: flex-end;">
    <div style="display: flex; align-items: center; gap: 14px;">
        <div style="font-size: 0.85rem; font-weight: 600; opacity: 0.7;">Exibindo <?php echo count($professores); ?> de <?php echo $total_count; ?> professores</div>
    </div>
</div>

<script>
    window.__currentMonth = '<?php echo $current_month; ?>';
    window.__viewMode = '<?php echo $view_mode; ?>';

    function navigateMonth(offset) {
        const [year, month] = window.__currentMonth.split('-').map(Number);
        const actualOffset = window.__viewMode === 'semestral' ? offset * 6 : offset;
        const date = new Date(year, month - 1 + actualOffset, 1);
        updateDateAndReload(date);
    }

    function navigateYear(offset) {
        const [year, month] = window.__currentMonth.split('-').map(Number);
        const date = new Date(year + offset, month - 1, 1);
        updateDateAndReload(date);
    }

    function updateDateAndReload(date) {
        const nextMonth = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0');
        const monthsPt = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        const monthsPtFull = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
        
        const labelEl = document.getElementById('global-month-label');
        const yearEl = document.getElementById('global-year-label');
        
        const prevYear = window.__currentMonth.split('-')[0];
        const newYear = nextMonth.split('-')[0];

        if (window.__viewMode === 'timeline') {
            if (prevYear != newYear) {
                location.href = updateUrlParam(window.location.href, 'month', nextMonth);
                return;
            }
            const mNum = date.getMonth() + 1;
            const targetMonth = document.querySelector('.month-group[data-month="' + mNum + '"]');
            if (targetMonth) {
                document.querySelectorAll('.timeline-grid').forEach(grid => {
                    grid.scrollTo({ left: targetMonth.offsetLeft, behavior: 'smooth' });
                });
                if (labelEl) labelEl.textContent = monthsPtFull[date.getMonth()];
                if (yearEl) yearEl.textContent = date.getFullYear();
                window.__currentMonth = nextMonth;
                updateUrlMonth(nextMonth);
            } else {
                location.href = updateUrlParam(window.location.href, 'month', nextMonth);
            }
        } else if (window.__viewMode === 'calendar') {
             if (window.currentDate && typeof window.renderCalendar === 'function') {
                 window.currentDate = date;
                 window.__currentMonth = nextMonth;
                 
                 if (prevYear != newYear && typeof window.loadDocenteAgenda === 'function' && window.currentDocenteId) {
                     window.loadDocenteAgenda(window.currentDocenteId, nextMonth).then(() => {
                         let l = monthsPtFull[date.getMonth()];
                         if (labelEl) labelEl.textContent = l;
                         if (yearEl) yearEl.textContent = date.getFullYear();
                         window.renderCalendar();
                     });
                 } else {
                     let l = monthsPtFull[date.getMonth()];
                     if (labelEl) labelEl.textContent = l;
                     if (yearEl) yearEl.textContent = date.getFullYear();
                     window.renderCalendar();
                 }
                 updateUrlMonth(nextMonth);
             } else {
                 location.href = updateUrlParam(window.location.href, 'month', nextMonth);
             }
        } else {
            const url = new URL(window.location.href);
            url.searchParams.set('month', nextMonth);
            url.searchParams.set('ajax_render', '1');
            
            fetch(url.toString())
                .then(r => r.text())
                .then(html => {
                    const temp = document.createElement('div');
                    temp.innerHTML = html;
                    const newTable = temp.querySelector('.table-container');
                    if (newTable) {
                        document.querySelector('.table-container').replaceWith(newTable);
                        window.__currentMonth = nextMonth;
                        if (labelEl) {
                           if (window.__viewMode === 'semestral') {
                               const mNum = date.getMonth() + 1;
                               const semNum = (mNum <= 6) ? 1 : 2;
                               labelEl.textContent = semNum + 'º Semestre';
                           } else {
                               labelEl.textContent = monthsPtFull[date.getMonth()];
                           }
                        }
                        if (yearEl) yearEl.textContent = date.getFullYear();
                        updateUrlMonth(nextMonth);
                    }
                });
        }
    }
    });
        }
    }

    function updateUrlMonth(m) {
        const url = new URL(window.location.href);
        url.searchParams.set('month', m);
        window.history.pushState({}, '', url.toString());
    }

    function updateUrlParam(url, param, value) {
        const u = new URL(url);
        u.searchParams.set(param, value);
        return u.toString();
    }
</script>

<!-- Scripts e modais globais para seleção -->
<script>
    window.__docentesData = <?= $docentes_json ?>;
    window.__isAdmin = <?= json_encode(isAdmin()) ?>;

    function handleModoReservaClick() {
        if (typeof window.openCalendarScheduleModal === 'function') {
            // Abrir para o modo RESERVA diretamente (hoje até +30 dias)
            const today = new Date().toISOString().slice(0, 10);
            const nextMonth = new Date();
            nextMonth.setDate(nextMonth.getDate() + 30);
            const end = nextMonth.toISOString().slice(0, 10);
            window.openCalendarScheduleModal(today, end, true);
        } else {
            alert('Erro: Modal de agendamento não carregado.');
        }
    }

    function handleCancelarReservaClick() {
        // Apenas oculta os botões se eles estiverem aparecendo
        const btns = ['btn-confirmar-reserva', 'btn-cancelar-reserva', 'btn-remover-selecionados'];
        btns.forEach(id => {
            const el = document.getElementById(id);
            if (el) el.style.display = 'none';
        });
        const btnModo = document.getElementById('btn-modo-reserva-unificado');
        if (btnModo) btnModo.style.display = 'inline-flex';
    }

    // Modal de Ano Global para a Agenda
    function openGlobalAnoModal() {
        const [year, month] = window.__currentMonth.split('-').map(Number);
        
        // Modal genérico de seleção de ano (Reaproveitado do professores_form ou customizado aqui)
        // Por simplificação UX, vamos usar um prompt ou se você quiser a mesma estética, criamos o modal:
        if (typeof showCustomYearPicker === 'function') {
            showCustomYearPicker(year, (newYear) => {
                navigateYear(newYear - year);
            });
        } else {
            const y = prompt("Digite o ano (1926-2126):", year);
            if (y && y >= 1926 && y <= 2126) {
                navigateYear(parseInt(y) - year);
            }
        }
    }
</script>

<!-- Select oculto para compatibilidade do JS -->
<select id="calendar-docente-select" style="display:none;">
    <option value="">Escolha um professor...</option>
    <?php foreach ($docentes as $p): ?>
        <option value="<?= $p['id'] ?>" <?= (isset($_GET['docente_id']) && $_GET['docente_id'] == $p['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($p['nome']) ?>
        </option>
    <?php endforeach; ?>
</select>

<?php if ($view_mode == 'calendar'): ?>
    <div id="professor-calendar"></div>

    <!-- Navegação por Mês abaixo do calendário -->
    <div class="card prof-selection-card" style="margin-top: 15px;">
        <div style="display: flex; justify-content: center; align-items: center; gap: 8px;">
            <label class="period-label-text" style="margin: 0;">Navegação por Mês</label>
            <div class="month-nav-controls">
                <button onclick="navigateMonth(-1)" class="month-btn-nav" style="width:32px;height:32px;text-decoration:none;color:var(--corTxt3); border:none; display: flex; align-items: center; justify-content: center; background: var(--card-bg); border-radius: 50%; border: 1px solid var(--border-color); cursor:pointer; transition: all 0.2s;"><i class="fas fa-chevron-left" style="font-size:0.75rem;"></i></button>
                <span id="global-month-label-bottom" style="font-weight: 800; font-size: 0.95rem; min-width: 140px; text-align: center; text-transform: capitalize; color: var(--text-color);"><?php echo $month_label; ?></span>
                <button onclick="navigateMonth(1)" class="month-btn-nav" style="width:32px;height:32px;text-decoration:none;color:var(--corTxt3); border:none; display: flex; align-items: center; justify-content: center; background: var(--card-bg); border-radius: 50%; border: 1px solid var(--border-color); cursor:pointer; transition: all 0.2s;"><i class="fas fa-chevron-right" style="font-size:0.75rem;"></i></button>
            </div>
        </div>
    </div>
<?php else: ?>
<div class="table-container">
    <?php if (empty($professores)): ?>
        <p style="text-align:center; padding: 50px; opacity:0.5;">Nenhum professor encontrado.</p>
    <?php else: ?>
        <?php foreach ($professores as $p): ?>
            <?php
            // Dados comuns para todas as views
            $p_esp = $p['area_conhecimento'] ?? '';
            $livres = 0;
            for ($d = 1; $d <= $days_in_month; $d++) {
                $dt = sprintf("%s-%02d", $current_month, $d);
                $dow = date('N', strtotime($dt));
                $is_free_day = false;
                if ($dow < 7 && !isset($agenda_data[$p['id']][$dt])) {
                    if (!isset($turno_detail[$p['id']][$dt])) {
                        // Se não tem detalhe de turno e não tem aula, assumimos livre 
                        // (Isso ocorre se o professor não tem nenhum horário de trabalho cadastrado E a lógica de OFF_SCHEDULE foi pulada, 
                        // ou se o dia é realmente livre). Com a mudança no backend, o normal é vir OFF_SCHEDULE se não tiver horário.
                        $is_free_day = true; 
                    } else {
                        $td = $turno_detail[$p['id']][$dt];
                        if (($td['M'] === false) || ($td['T'] === false) || ($td['N'] === false)) {
                            $is_free_day = true;
                        }
                    }
                }
                if ($is_free_day) $livres++;
            }
            ?>

            <?php if ($view_mode == 'semestral'): ?>
                <?php
                $cur_m = (int)date('m', strtotime($current_month . '-01'));
                $cur_y = (int)date('Y', strtotime($current_month . '-01'));
                $semester_start = ($cur_m <= 6) ? 1 : 7;
                $semester_end = $semester_start + 5;
                $months_pt_sem = [1=>'Jan',2=>'Fev',3=>'Mar',4=>'Abr',5=>'Mai',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Set',10=>'Out',11=>'Nov',12=>'Dez'];
                $sem_first = sprintf("%04d-%02d-01", $cur_y, $semester_start);
                $sem_last = date('Y-m-t', strtotime(sprintf("%04d-%02d-01", $cur_y, $semester_end)));

                // Utiliza AgendaModel para buscar corretamente agendamentos recorrentes (com filtro de período)
                $sem_filters = [];
                if (!empty($filter_periodo) && $filter_periodo !== 'Todos') {
                    $sem_filters['periodo'] = $filter_periodo;
                }
                $sem_expanded = $agendaModel->getExpandedAgenda([$p['id']], $sem_first, $sem_last, $sem_filters);
                $sem_busy = []; $sem_turno = [];
                $sem_work_schedules = [];
                
                foreach ($sem_expanded as $row) {
                    $dt_sem = $row['agenda_data'];
                    if ($row['type'] === 'WORK_SCHEDULE') {
                        $sem_work_schedules[$dt_sem][$row['periodo']] = true;
                        continue;
                    }
                    if ($row['type'] === 'AULA') {
                        $sem_busy[$dt_sem] = $row['turma_nome'];
                        if (!isset($sem_turno[$dt_sem])) $sem_turno[$dt_sem] = ['M'=>false,'T'=>false,'N'=>false,'I'=>false];
                        $hi=$row['horario_inicio']; $hf=$row['horario_fim'];
                        if ($hi < '12:00:00') $sem_turno[$dt_sem]['M'] = true;
                        if ($hi < '18:00:00' && $hf > '12:00:00') $sem_turno[$dt_sem]['T'] = true;
                        if ($hf > '18:00:00' || $hi >= '18:00:00') $sem_turno[$dt_sem]['N'] = true;
                        
                        if (isset($row['periodo']) && $row['periodo'] === 'Integral') {
                            $sem_turno[$dt_sem]['I'] = true;
                            $sem_turno[$dt_sem]['M'] = true; $sem_turno[$dt_sem]['T'] = true;
                        }
                        if ($sem_turno[$dt_sem]['M'] && $sem_turno[$dt_sem]['T']) $sem_turno[$dt_sem]['I'] = true;
                    }
                }

                // Check for OFF_SCHEDULE in semestral view
                if (true) {
                    $sem_ptr = new DateTime($sem_first);
                    $sem_end_ptr = new DateTime($sem_last);
                    while ($sem_ptr <= $sem_end_ptr) {
                        $d_sem = $sem_ptr->format('Y-m-d');
                        if ($sem_ptr->format('N') < 7) {
                            foreach (['Manhã' => 'M', 'Tarde' => 'T', 'Noite' => 'N'] as $p_full => $p_key) {
                                if (!isset($sem_work_schedules[$d_sem][$p_full]) && !isset($sem_work_schedules[$d_sem]['Integral'])) {
                                    if (!isset($sem_turno[$d_sem])) $sem_turno[$d_sem] = ['M'=>false,'T'=>false,'N'=>false,'I'=>false];
                                    if ($sem_turno[$d_sem][$p_key] === false) {
                                        $sem_turno[$d_sem][$p_key] = 'OFF_SCHEDULE';
                                    }
                                }
                            }
                        }
                        $sem_ptr->modify('+1 day');
                    }
                }
                $total_sem_free = 0; $total_sem_busy = 0;
                for ($m = $semester_start; $m <= $semester_end; $m++) {
                    $ms = sprintf("%04d-%02d", $cur_y, $m);
                    $dc = (int)date('t', strtotime($ms . '-01'));
                    for ($dd = 1; $dd <= $dc; $dd++) {
                        $ddt = sprintf("%s-%02d", $ms, $dd);
                        $ddow = date('N', strtotime($ddt));
                        $is_sem_free = false;
                        if ($ddow < 7 && !isset($sem_busy[$ddt])) {
                            if (!isset($sem_turno[$ddt])) {
                                $is_sem_free = true;
                            } else {
                                $st = $sem_turno[$ddt];
                                if ($st['M'] === false || $st['T'] === false || $st['N'] === false) {
                                    $is_sem_free = true;
                                }
                            }
                        }
                        if ($is_sem_free) $total_sem_free++;
                    }
                }
                $perc_free = ($total_sem_free + $total_sem_busy > 0) ? round(($total_sem_free / ($total_sem_free + $total_sem_busy)) * 100) : 0;
                ?>
                <div class="prof-row" data-prof-id="<?php echo $p['id']; ?>">
                    <div class="prof-info-row">
                        <div onclick="openTimelineModal(<?php echo $p['id']; ?>, '<?php echo addslashes($p['nome']); ?>')" style="cursor:pointer; display:flex; align-items:center; gap:10px; flex: 1;">
                            <div style="width:32px; height:32px; background: linear-gradient(135deg, #e53935, #c62828); color:#fff; border-radius:8px; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:0.8rem;"><?php echo mb_substr($p['nome'], 0, 1); ?></div>
                            <div>
                                <div style="font-weight:800; font-size:0.95rem;"><?php echo htmlspecialchars($p['nome']); ?></div>
                                <div style="font-size:0.75rem; color: #9ba1b0;"><?php echo htmlspecialchars($p_esp); ?></div>
                            </div>
                        </div>
                        <div class="semestral-stats-group">
                            <span style="color:#2e7d32;"><i class="fas fa-check-circle"></i> <?php echo $total_sem_free; ?></span>
                            <span style="color:#d32f2f;"><i class="fas fa-times-circle"></i> <?php echo $total_sem_busy; ?></span>
                            <span style="color:var(--text-muted);"><?php echo $perc_free; ?>%</span>
                        </div>
                    </div>
                    <div class="semestral-grid">
                        <?php for ($m = $semester_start; $m <= $semester_end; $m++):
                            $month_str = sprintf("%04d-%02d", $cur_y, $m);
                            $days_count = (int)date('t', strtotime($month_str . '-01'));
                            $first_dow = (int)date('w', strtotime($month_str . '-01'));
                            $m_free = 0; $m_busy = 0;
                            for ($dd = 1; $dd <= $days_count; $dd++) {
                                $ddt = sprintf("%s-%02d", $month_str, $dd);
                                $ddow = (int)date('N', strtotime($ddt));
                                if ($ddow < 7 && !isset($sem_busy[$ddt])) $m_free++;
                                if (isset($sem_busy[$ddt])) $m_busy++;
                            }
                        ?>
                        <div class="sem-month-box">
                            <div class="sem-month-header"><span><?php echo $months_pt_sem[$m]; ?></span><span class="sem-month-stats"><span style="color:#2e7d32;"><?php echo $m_free; ?></span>/<span style="color:#d32f2f;"><?php echo $m_busy; ?></span></span></div>
                            <div class="sem-day-headers"><span>D</span><span>S</span><span>T</span><span>Q</span><span>Q</span><span>S</span><span>S</span></div>
                            <div class="sem-calendar-grid">
                                <?php for ($e = 0; $e < $first_dow; $e++): ?><div class="sem-day sem-day-empty"></div><?php endfor; ?>
                                <?php for ($dd = 1; $dd <= $days_count; $dd++):
                                    $ddt = sprintf("%s-%02d", $month_str, $dd);
                                    $ddow = (int)date('N', strtotime($ddt));
                                    $is_sunday = ($ddow == 7); $is_saturday = ($ddow == 6);
                                    $is_busy = isset($sem_busy[$ddt]);
                                    $is_reserved_s = isset($reserva_data[$p['id']][$ddt]) ? $reserva_data[$p['id']][$ddt] : false;
                                    
                                    $is_feriado_s = ($is_reserved_s && isset($is_reserved_s['tipo_bloqueio']) && in_array($is_reserved_s['tipo_bloqueio'], ['FERIADO', 'FERIAS']));
                                    
                                    $is_all_off_sem = (isset($sem_turno[$ddt]) && $sem_turno[$ddt]['M'] === 'OFF_SCHEDULE' && $sem_turno[$ddt]['T'] === 'OFF_SCHEDULE' && $sem_turno[$ddt]['N'] === 'OFF_SCHEDULE');

                                    $cell_class = 'sem-day-free';
                                    if ($is_sunday) $cell_class = 'sem-day-sunday';
                                    elseif ($is_feriado_s) $cell_class = 'sem-day-feriado'; 
                                    elseif ($is_busy) $cell_class = 'sem-day-busy'; 
                                    elseif ($is_reserved_s && !$is_reserved_s['own']) $cell_class = 'sem-day-reserved';
                                    elseif ($is_reserved_s && $is_reserved_s['own']) $cell_class = 'sem-day-reserved-own';
                                    elseif ($is_all_off_sem) $cell_class = 'sem-day-sunday slot-disabled';
                                    elseif ($is_saturday) $cell_class = 'sem-day-weekend';
                                    $clickable = (!$is_sunday && !$is_busy && !$is_all_off_sem && !($is_reserved_s && !$is_reserved_s['own']));
                                    
                                    $base_c = null;
                                    if ($cell_class === 'sem-day-busy' || $cell_class === 'sem-day-reserved' || $cell_class === 'sem-day-reserved-own' || $cell_class === 'sem-day-feriado') {
                                        $base_c = 'rgba(255,255,255,0.4)';
                                    }
                                    $p_m_c = $p_t_c = $p_n_c = $p_i_c = $base_c;

                                    $is_feriado_spec = ($cell_class === 'sem-day-feriado');

                                    if ($is_reserved_s || $is_feriado_spec) {
                                         $p_m_c = $p_t_c = $p_n_c = $p_i_c = '#fff';
                                    }
                                    if ($is_busy) {
                                         if ($sem_turno[$ddt]['M'] === 'OFF_SCHEDULE') $p_m_c = '#555';
                                         elseif (!empty($sem_turno[$ddt]['M'])) $p_m_c = '#fff';

                                         if ($sem_turno[$ddt]['T'] === 'OFF_SCHEDULE') $p_t_c = '#555';
                                         elseif (!empty($sem_turno[$ddt]['T'])) $p_t_c = '#fff';

                                         if ($sem_turno[$ddt]['N'] === 'OFF_SCHEDULE') $p_n_c = '#555';
                                         elseif (!empty($sem_turno[$ddt]['N'])) $p_n_c = '#fff';
                                    } else {
                                         if (isset($sem_turno[$ddt])) {
                                             if ($sem_turno[$ddt]['M'] === 'OFF_SCHEDULE') $p_m_c = '#555';
                                             if ($sem_turno[$ddt]['T'] === 'OFF_SCHEDULE') $p_t_c = '#555';
                                             if ($sem_turno[$ddt]['N'] === 'OFF_SCHEDULE') $p_n_c = '#555';
                                         }
                                    }
                                    if ($is_saturday) {
                                        $p_n_c = 'rgba(0,0,0,0.2)';
                                        if ($cell_class === 'sem-day-busy' || $cell_class === 'sem-day-reserved') $p_n_c = 'rgba(255,255,255,0.2)';
                                    }
                                    $title_extra = '';
                                    if ($is_busy) $title_extra = ' — ' . $sem_busy[$ddt];
                                    elseif ($is_reserved_s && isset($is_reserved_s['gestor'])) $title_extra = ' — ' . $is_reserved_s['gestor'];
                                ?>
                                    <div class="sem-day <?php echo $cell_class; ?>" title="<?php echo $dd . ' ' . $months_pt_sem[$m]; ?><?= $title_extra ?>"
                                         <?php if ($clickable): ?>onclick="handleBarClick(<?php echo $p['id']; ?>, '<?php echo addslashes($p['nome']); ?>', '<?php echo $ddt; ?>', this, event)"<?php endif; ?>>
                                        <div class="sem-day-num-label"><?php echo $dd; ?></div>
                                        <div class="sem-day-bars-container">
                                            <div style="height:2px; background:<?=$p_m_c?>; border-radius:1px;"></div>
                                            <div style="height:2px; background:<?=$p_t_c?>; border-radius:1px;"></div>
                                            <div style="height:2px; background:<?=$p_n_c?>; border-radius:1px;"></div>
                                        </div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>

            <?php else: ?>
                <div class="prof-row" data-prof-id="<?php echo $p['id']; ?>">
                    <div class="prof-info-header">
                        <div class="prof-name">
                            <?php echo htmlspecialchars($p['nome']); ?>
                            <span class="prof-spec"> &middot; <?php echo htmlspecialchars($p_esp); ?></span>
                        </div>
                        <div style="font-size: 0.85rem; font-weight: 700;">
                            <span style="color: #2e7d32;"><?php echo $livres; ?> dias livres</span>
                        </div>
                    </div>

                    <?php if ($view_mode == 'blocks'): ?>
                        <?php
                        $blocks = []; $cur_block = null;
                        for ($i = 1; $i <= $days_in_month; $i++) {
                            $dt = sprintf("%s-%02d", $current_month, $i);
                            $dow = date('N', strtotime($dt));
                            $is_busy = isset($agenda_data[$p['id']][$dt]) ? $agenda_data[$p['id']][$dt] : false;
                            $is_reserved_b = isset($reserva_data[$p['id']][$dt]) ? $reserva_data[$p['id']][$dt] : false;
                            
                            $is_feriado_b = ($is_reserved_b && isset($is_reserved_b['tipo_bloqueio']) && in_array($is_reserved_b['tipo_bloqueio'], ['FERIADO', 'FERIAS']));

                            if ($dow == 7) { $status = 'sunday'; $label = 'Bloqueado'; }
                            elseif ($is_feriado_b) { $status = 'feriado'; $label = ($is_reserved_b['tipo_bloqueio'] === 'FERIADO' ? 'Feriado' : 'Férias'); }
                            elseif ($is_busy) { $status = 'busy:' . $is_busy; $label = $is_busy; }
                            elseif ($is_reserved_b && !$is_reserved_b['own']) { $status = 'reserved'; $label = 'Reservado'; }
                            elseif ($is_reserved_b && $is_reserved_b['own']) { $status = 'reserved_own'; $label = 'Minha Reserva'; }
                            else { 
                                $is_off_b = (isset($turno_detail[$p['id']][$dt]) && $turno_detail[$p['id']][$dt]['M'] === 'OFF_SCHEDULE' && $turno_detail[$p['id']][$dt]['T'] === 'OFF_SCHEDULE' && $turno_detail[$p['id']][$dt]['N'] === 'OFF_SCHEDULE');
                                if ($is_off_b) { $status = 'sunday'; $label = 'Indisponível'; }
                                else { $status = 'free'; $label = 'Livre'; }
                            }
                            if ($cur_block && $cur_block['status'] === $status) { $cur_block['end'] = $i; $cur_block['count']++; }
                            else { if ($cur_block) $blocks[] = $cur_block; $cur_block = ['start' => $i, 'end' => $i, 'status' => $status, 'label' => $label, 'count' => 1]; }
                        }
                        if ($cur_block) $blocks[] = $cur_block;
                        ?>
                        <div class="blocks-bar-wrapper" style="overflow-x: auto; display: flex; -webkit-overflow-scrolling: touch; padding-bottom: 5px;">
                            <?php foreach ($blocks as $block):
                                $range_text = ($block['start'] == $block['end']) ? 'Dia ' . str_pad($block['start'], 2, '0', STR_PAD_LEFT) : 'Dia ' . str_pad($block['start'], 2, '0', STR_PAD_LEFT) . ' - ' . str_pad($block['end'], 2, '0', STR_PAD_LEFT);
                                if (strpos($block['status'], 'busy:') === 0) $bclass = 'block-seg-busy';
                                elseif ($block['status'] === 'feriado') $bclass = 'block-seg-feriado';
                                elseif ($block['status'] === 'reserved') $bclass = 'block-seg-reserved';
                                elseif ($block['status'] === 'reserved_own') $bclass = 'block-seg-reserved-own';
                                elseif ($block['status'] === 'sunday') $bclass = 'block-seg-sunday';
                                else $bclass = 'block-seg-free';
                                $first_dt = sprintf("%s-%02d", $current_month, $block['start']);
                                $is_clickable = ($block['status'] === 'free' || $block['status'] === 'reserved_own');
                            ?>
                                <div class="block-seg <?php echo $bclass; ?> <?php echo !$is_clickable ? 'slot-disabled' : ''; ?>" style="flex: 0 0 auto; min-width: 120px; margin-right: 5px; border-radius: 8px;" title="<?php echo $range_text; ?>: <?php echo htmlspecialchars($block['label']); ?>"
                                     <?php if ($is_clickable): ?>onclick="handleBarClick(<?php echo $p['id']; ?>, '<?php echo addslashes($p['nome']); ?>', '<?php echo $first_dt; ?>', this, event)"<?php endif; ?>>
                                    <span class="block-range" style="font-size: 0.7rem;"><?php echo $range_text; ?></span>
                                    <span class="block-label" style="font-size: 0.8rem; font-weight: 800;"><?php echo htmlspecialchars($block['label']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <?php
                        $meses_nomes_completos = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
                        $dias_nomes_curtos = [1=>'Seg',2=>'Ter',3=>'Qua',4=>'Qui',5=>'Sex',6=>'Sáb',7=>'Dom'];
                        $day_colors = [1=>'#2e7d32',2=>'#e53935',3=>'#1565c0',4=>'#6a1b9a',5=>'#ef6c00',6=>'#546e7a',7=>'#a84a4a'];
                        $current_year_tl = date('Y', strtotime($current_month . '-01'));
                        ?>
                        <div class="timeline-grid">
                            <?php for ($m = 1; $m <= 12; $m++): 
                                $ms = sprintf("%04d-%02d", $current_year_tl, $m);
                                $days_in_m = (int)date('t', strtotime($ms . '-01'));
                                
                                $m_busy = 0; $m_free = 0;
                                for ($d = 1; $d <= $days_in_m; $d++) {
                                    $dt = sprintf("%s-%02d", $ms, $d);
                                    $dow = (int)date('N', strtotime($dt));
                                    if ($dow == 7) continue;
                                    
                                    $is_busy = isset($agenda_data[$p['id']][$dt]) ? $agenda_data[$p['id']][$dt] : false;
                                    $is_res = isset($reserva_data[$p['id']][$dt]) ? $reserva_data[$p['id']][$dt] : false;
                                    $is_feriado = ($is_res && isset($is_res['tipo_bloqueio']) && in_array($is_res['tipo_bloqueio'], ['FERIADO', 'FERIAS']));
                                    
                                    $is_off_m = (isset($turno_detail[$p['id']][$dt]) && $turno_detail[$p['id']][$dt]['M'] === 'OFF_SCHEDULE' && $turno_detail[$p['id']][$dt]['T'] === 'OFF_SCHEDULE' && $turno_detail[$p['id']][$dt]['N'] === 'OFF_SCHEDULE');
                                    
                                    if ($is_feriado || $is_off_m) continue;
                                    if ($is_busy || ($is_res && !$is_feriado)) $m_busy++;
                                    else $m_free++;
                                }
                            ?>
                                <div class="month-group" data-month="<?= $m ?>">
                                    <div class="month-header">
                                        <div class="month-name"><?= $meses_nomes_completos[$m-1] ?></div>
                                        <div class="month-stats">
                                            <span style="color:#4caf50;"><i class="fas fa-check-circle"></i> <?= $m_free ?> livre</span>
                                            <span style="color:#e53935;"><i class="fas fa-times-circle"></i> <?= $m_busy ?> ocupado</span>
                                        </div>
                                    </div>
                                    
                                    <div class="timeline-days-container">
                                        <?php for ($i = 1; $i <= $days_in_m; $i++):
                                            $dt = sprintf("%s-%02d", $ms, $i);
                                            $dow = (int)date('N', strtotime($dt));
                                            $is_busy_tl = isset($agenda_data[$p['id']][$dt]) ? $agenda_data[$p['id']][$dt] : false;
                                            $is_reserved_tl = isset($reserva_data[$p['id']][$dt]) ? $reserva_data[$p['id']][$dt] : false;
                                            $t_detail = isset($turno_detail[$p['id']][$dt]) ? $turno_detail[$p['id']][$dt] : ['M'=>false,'T'=>false,'N'=>false,'I'=>false];
                                            $p_name_js = addslashes($p['nome']);
                        
                                            $is_all_off_tl = ($t_detail['M'] === 'OFF_SCHEDULE' && $t_detail['T'] === 'OFF_SCHEDULE' && $t_detail['N'] === 'OFF_SCHEDULE');

                                            $is_feriado_tl = ($is_reserved_tl && isset($is_reserved_tl['tipo_bloqueio']) && in_array($is_reserved_tl['tipo_bloqueio'], ['FERIADO', 'FERIAS']));
                        
                                            $cell_status_class = '';
                                            if ($dow == 7) $cell_status_class = 'timeline-day-sunday';
                                            elseif ($is_feriado_tl) $cell_status_class = 'timeline-day-feriado';
                                            elseif ($is_all_off_tl) $cell_status_class = 'timeline-day-off';
                                            elseif ($is_busy_tl) $cell_status_class = 'timeline-day-busy';
                                            elseif ($is_reserved_tl) $cell_status_class = ($is_reserved_tl['own'] ? 'timeline-day-reserved-own' : 'timeline-day-reserved');
                                            else $cell_status_class = 'timeline-day-free';
 
                                            $has_free_shift_tl = false;
                                            foreach (['M', 'T', 'N'] as $pk) {
                                                if (isset($t_detail[$pk]) && $t_detail[$pk] !== 'OFF_SCHEDULE' && empty($t_detail[$pk])) {
                                                    if ($dow == 6 && $pk === 'N') continue;
                                                    $has_free_shift_tl = true;
                                                    break;
                                                }
                                            }
                                            
                                            $is_own_res_tl = ($is_reserved_tl && isset($is_reserved_tl['own']) && $is_reserved_tl['own']);
                                            $is_clickable_tl = ($dow != 7 && !$is_feriado_tl && !($is_reserved_tl && !$is_own_res_tl) && ($has_free_shift_tl || $is_own_res_tl));
                                            
                                            $cursor_tl = $is_clickable_tl ? 'pointer' : 'default';
                                            $onclick_tl = ($cursor_tl === 'pointer') ? "onclick=\"handleBarClick({$p['id']}, '{$p_name_js}', '{$dt}', this, event)\"" : "";
                                            $disabled_class_tl = ($cursor_tl === 'default') ? 'slot-disabled' : '';
                                            
                                            $bg_day_tl = ($dow == 7) ? '#a84a4a' : ($is_feriado_tl ? 'linear-gradient(135deg, #1565c0, #1976d2)' : ($is_busy_tl ? 'linear-gradient(135deg, #e53935, #c62828)' : ($is_reserved_tl ? 'linear-gradient(135deg, #f9a825, #ff8f00)' : ($is_all_off_tl ? '#555' : 'linear-gradient(135deg, #2e7d32, #1b5e20)'))));
                                        ?>
                                            <div class="timeline-day-cell <?= $cell_status_class ?> <?= $disabled_class_tl ?>" title="Dia <?= $i ?> (<?= $dias_nomes_curtos[$dow] ?>)<?= $is_busy_tl ? ' — OCUPADO' : '' ?>" <?= $onclick_tl ?>
                                                 style="flex: 0 0 42px; display: flex; flex-direction: column; gap: 3px; cursor: <?= $cursor_tl ?>;">
                                                <div class="timeline-day-num-box" style="background: <?= $bg_day_tl ?>;">
                                                    <div><?= str_pad($i, 2, '0', STR_PAD_LEFT) ?></div>
                                                    <div class="timeline-dow-label"><?= $dias_nomes_curtos[$dow] ?></div>
                                                </div>
                                                <?php foreach (['M', 'T', 'N'] as $pk):
                                                    if ($dow == 7) $bar_color = '#555';
                                                    elseif ($is_feriado_tl) $bar_color = '#1565c0';
                                                    elseif ($is_reserved_tl && !$is_reserved_tl['own']) $bar_color = '#f9a825';
                                                    elseif ($dow == 6 && $pk === 'N') $bar_color = '#ccc';
                                                    elseif ($t_detail[$pk] === 'OFF_SCHEDULE') $bar_color = '#555';
                                                    elseif ($t_detail[$pk]) $bar_color = '#e53935';
                                                    else $bar_color = '#4caf50';
                                                ?>
                                                    <div style="height: 6px; width: 100%; background: <?= $bar_color ?>; border-radius: 1px;"></div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px; padding: 0 10px;">
                            <div style="display: flex; gap: 12px; font-size: 0.65rem; color: #888; font-weight: 700; flex-wrap: wrap;">
                                <span style="display: flex; align-items: center; gap: 4px;"><span style="width: 12px; height: 4px; background: #4caf50; border-radius: 1px; display: inline-block;"></span> Manhã</span>
                                <span style="display: flex; align-items: center; gap: 4px;"><span style="width: 12px; height: 4px; background: #4caf50; border-radius: 1px; display: inline-block;"></span> Tarde</span>
                                <span style="display: flex; align-items: center; gap: 4px;"><span style="width: 12px; height: 4px; background: #4caf50; border-radius: 1px; display: inline-block;"></span> Noite</span>
                                <span style="margin-left: 4px; opacity: 0.6;">(de cima p/ baixo)</span>
                            </div>
                            <!-- (internal navigation removed) -->
                        </div>

                        <script>
                        (function() {
                            const currentMonth = <?= (int)date('m', strtotime($current_month . '-01')) ?>;
                            document.querySelectorAll('.timeline-grid').forEach(function(grid) {
                                const targetMonth = grid.querySelector('.month-group[data-month="' + currentMonth + '"]');
                                if (targetMonth) {
                                    setTimeout(() => {
                                        grid.scrollLeft = targetMonth.offsetLeft;
                                    }, 100);
                                }
                            });
                        })();
                        </script>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Paginação -->
<?php $total_pages = ceil($total_count / $limit); if ($total_pages > 1): ?>
<div class="pagination">
    <a href="?page=<?php echo max(1, $page - 1); ?>&search=<?php echo urlencode($search_name); ?>&especialidade=<?php echo urlencode($filter_especialidade); ?>&ordem_disp=<?php echo $ordem_disp; ?>&month=<?php echo $current_month; ?>&view_mode=<?php echo $view_mode; ?><?php echo $docente_param; ?>" class="btn-nav <?php echo $page <= 1 ? 'disabled' : ''; ?>"><i class="fas fa-chevron-left"></i></a>
    <span style="font-weight: 700;">Página <?php echo $page; ?> de <?php echo $total_pages; ?></span>
    <a href="?page=<?php echo min($total_pages, $page + 1); ?>&search=<?php echo urlencode($search_name); ?>&especialidade=<?php echo urlencode($filter_especialidade); ?>&ordem_disp=<?php echo $ordem_disp; ?>&month=<?php echo $current_month; ?>&view_mode=<?php echo $view_mode; ?><?php echo $docente_param; ?>" class="btn-nav <?php echo $page >= $total_pages ? 'disabled' : ''; ?>"><i class="fas fa-chevron-right"></i></a>
</div>
<?php endif; ?>

<?php endif; ?>

<!-- Barra Flutuante + MODAL 3: Reserva -->
<script>
    window.userIsCRI = <?php echo isCRI() ? 'true' : 'false'; ?>;
    window.userIsAdmin = <?php echo (isAdmin() || isGestor()) ? 'true' : 'false'; ?>;
</script>
<script>
// Inicializa o calendário SPA caso esteja na visualização correspondente
(function() {
    const initCalendar = () => {
        const viewMode = "<?php echo $view_mode; ?>";
        if (viewMode === 'calendar') {
            const profId = "<?php echo $selected_prof_id; ?>";
            const month = "<?php echo $current_month; ?>";
            if (profId && typeof loadDocenteAgenda === 'function') {
                loadDocenteAgenda(profId, month);
            } else if (profId) {
                // Tenta de novo se ainda não carregou
                setTimeout(initCalendar, 100);
            }
        }
    };
    if (document.readyState === 'complete') initCalendar();
    else window.addEventListener('load', initCalendar);
})();
</script>

<?php if (!isset($_GET['ajax_render'])): ?>
<?php include __DIR__ . '/../components/footer.php'; ?>
<?php endif; ?>
