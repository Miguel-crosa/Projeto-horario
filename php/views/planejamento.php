<?php
require_once __DIR__ . '/../configs/db.php';
$embedded_mode = defined('PLANEJAMENTO_EMBED') && PLANEJAMENTO_EMBED;
if (!$embedded_mode) {
    include __DIR__ . '/../components/header.php';
}

$cursos = mysqli_fetch_all(mysqli_query($conn, "
    SELECT id, nome, area, tipo FROM curso ORDER BY area ASC, nome ASC
"), MYSQLI_ASSOC);
$ambientes = mysqli_fetch_all(mysqli_query($conn, "SELECT id, nome, tipo FROM ambiente ORDER BY tipo ASC, nome ASC"), MYSQLI_ASSOC);
$dias = ['Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];

// If Professor, restrict docente selection to their linked ID
$is_prof = isProfessor();
$logged_docente_id = getUserDocenteId();

if ($is_prof && $logged_docente_id) {
    $docentes = mysqli_fetch_all(mysqli_query($conn, "SELECT id, nome, area_conhecimento FROM docente WHERE id = $logged_docente_id"), MYSQLI_ASSOC);
} else {
    $docentes = mysqli_fetch_all(mysqli_query($conn, "SELECT id, nome, area_conhecimento FROM docente ORDER BY area_conhecimento ASC, nome ASC"), MYSQLI_ASSOC);
}

$gantt_docentes = [];
foreach ($docentes as $d) {
    $did = (int) $d['id'];
    $alocacoes = mysqli_fetch_all(mysqli_query($conn, "
        SELECT DISTINCT t.id AS turma_id, c.nome AS curso, t.data_inicio AS inicio, t.data_fim AS fim, a.horario_inicio AS inicio_hora, a.horario_fim AS fim_hora
        FROM agenda a
        JOIN turma t ON a.turma_id = t.id
        JOIN curso c ON t.curso_id = c.id
        WHERE a.docente_id = $did
        ORDER BY t.data_inicio ASC
    "), MYSQLI_ASSOC);
    $gantt_docentes[] = [
        'id' => $d['id'],
        'nome' => $d['nome'],
        'alocacoes' => $alocacoes
    ];
}

$timeline_start = date('Y-m-01');
$timeline_end = date('Y-m-t', strtotime('+6 months'));

$gantt_json = json_encode([
    'docentes' => $gantt_docentes,
    'cursos' => $cursos,
    'ambientes' => $ambientes,
    'timeline_start' => $timeline_start,
    'timeline_end' => $timeline_end
], JSON_UNESCAPED_UNICODE);

// Prepare docentes JSON for JS modal
$docentes_json = json_encode($docentes, JSON_UNESCAPED_UNICODE);
$initial_view = (isset($_GET['start_view']) && $_GET['start_view'] === 'gantt') ? 'gantt' : 'calendar';
?>

<?php if (!$embedded_mode): ?>
    <div class="page-header">
        <h2><i class="fas fa-calendar-alt" style="color: var(--primary-red); margin-right: 12px;"></i> Planejamento de
            Horários</h2>
    </div>
    <p style="margin-bottom: 20px;">Selecione um professor para visualizar sua agenda e cadastrar novos horários.</p>
<?php endif; ?>

<div class="agenda-professor-section"
    style="margin-top: 0; <?= $embedded_mode ? 'border:none; background:transparent; padding:0;' : '' ?>">
    <?php if (!$embedded_mode): ?>
        <h3><i class="fas fa-user-calendar" style="color: var(--primary-red); margin-right: 10px;"></i>
            <?= $is_prof ? 'Minha Agenda' : 'Planejamento — Selecionar Professor' ?>
        </h3>
    <?php endif; ?>

    <div class="agenda-professor-controls" style="<?= $embedded_mode ? 'justify-content: flex-end;' : '' ?>">
        <?php if (!$is_prof): ?>
            <div class="form-group"
                style="margin-bottom: 0; flex: 1; max-width: 400px; <?= $embedded_mode ? 'display:none;' : '' ?>">
                <label class="form-label">Professor Selecionado</label>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <select id="calendar-docente-select" class="form-input" style="display: none;">
                        <option value="">Escolha um professor...</option>
                        <?php
                        $grouped = [];
                        foreach ($docentes as $d) {
                            $area = $d['area_conhecimento'] ?: 'Outros';
                            $grouped[$area][] = $d;
                        }
                        foreach ($grouped as $area => $profs): ?>
                            <optgroup label="<?= htmlspecialchars($area) ?>">
                                <?php foreach ($profs as $p): ?>
                                    <option value="<?= $p['id'] ?>" <?= ($is_prof || (isset($_GET['docente_id']) && $_GET['docente_id'] == $p['id'])) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($p['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn btn-primary" id="btn-selecionar-professor"
                        style="white-space: nowrap; padding: 10px 20px; font-size: 0.9rem;">
                        <i class="fas fa-user-plus" style="margin-right: 8px;"></i>
                        <span id="btn-prof-label">Selecionar Professor</span>
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="form-group" style="margin-bottom: 0; flex: 1; max-width: 400px; display:none;">
                <select id="calendar-docente-select" class="form-input" disabled>
                    <?php foreach ($docentes as $p): ?>
                        <option value="<?= $p['id'] ?>" selected>

                            <?= htmlspecialchars($p['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="view-toggle" style="<?= $embedded_mode ? 'display:none;' : '' ?>">
            <button class="view-toggle-btn <?= $initial_view === 'calendar' ? 'active' : '' ?>" data-view="calendar"><i
                    class="fas fa-calendar-alt" style="margin-right: 8px;"></i> Calendário</button>
            <button class="view-toggle-btn <?= $initial_view === 'gantt' ? 'active' : '' ?>" data-view="gantt"><i
                    class="fas fa-chart-bar" style="margin-right: 8px;"></i> Gráfico</button>
        </div>
    </div>

    <div class="availability-section" id="availability-section" style="display: none;">
        <div class="period-selector-container">
            <span
                style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-right: 15px;">Pré-definir
                Período:</span>
            <div class="period-btns">
                <button type="button" class="period-btn period-btn-all" data-periodo="" id="btn-todos">
                    <i class="fas fa-th status-icon" style="margin-right: 6px;"></i> Todos</button>
                <button type="button" class="period-btn" data-periodo="Manhã" data-inicio="07:30" data-fim="11:30"
                    id="btn-manha"><i class="fas fa-sun status-icon" style="margin-right: 6px;"></i> Manhã</button>
                <button type="button" class="period-btn" data-periodo="Tarde" data-inicio="13:30" data-fim="17:30"
                    id="btn-tarde"><i class="fas fa-cloud-sun status-icon" style="margin-right: 6px;"></i>
                    Tarde</button>
                <button type="button" class="period-btn" data-periodo="Noite" data-inicio="19:30" data-fim="23:30"
                    id="btn-noite"><i class="fas fa-moon status-icon" style="margin-right: 6px;"></i> Noite</button>
                <button type="button" class="period-btn" data-periodo="Integral" data-inicio="07:30" data-fim="17:30"
                    id="btn-integral"><i class="fas fa-circle status-icon" style="margin-right: 6px;"></i>
                    Integral</button>
            </div>
        </div>
        <div class="avail-legend">
            <span><span class="avail-dot free"></span> Disponível</span>
            <span><span class="avail-dot busy"></span> Ocupado</span>
            <span><span class="avail-dot" style="background: #ffb300;"></span> Reservado</span>
        </div>
        <div style="margin-top: 10px;">
            <button type="button" class="btn btn-primary" id="btn-modo-reserva"
                style="background: #ff8f00; border-color: #e65100; font-size: 0.85rem; padding: 8px 16px;"
                onclick="toggleReservationMode()">
                <i class="fas fa-bookmark"></i> Modo Reserva
            </button>
            <button type="button" class="btn btn-primary" id="btn-confirmar-reserva"
                style="display: none; background: #2e7d32; border-color: #1b5e20; font-size: 0.85rem; padding: 8px 16px; margin-left: 8px;"
                onclick="confirmReservations()">
                <i class="fas fa-check"></i> Confirmar Reserva
            </button>
            <?php if (isAdmin()): ?>
                <button type="button" class="btn btn-secondary" id="btn-remover-selecionados"
                    style="display: none; background: #d32f2f; border-color: #c62828; font-size: 0.85rem; padding: 8px 16px; margin-left: 8px;"
                    onclick="batchRemoveReservations()">
                    <i class="fas fa-trash-alt"></i> Remover Selecionados
                </button>
            <?php endif; ?>
            <button type="button" class="btn btn-back" id="btn-cancelar-reserva"
                style="display: none; font-size: 0.85rem; padding: 8px 16px; margin-left: 8px;"
                onclick="cancelReservationMode()">
                <i class="fas fa-times"></i> Cancelar
            </button>
        </div>
        <div id="availability-bar" class="avail-bar-container">
            <div class="avail-bar-track">
                <div class="avail-bar-free" style="width: 100%;">Selecione um professor</div>
            </div>
        </div>
        <div class="avail-footer">
            <div id="avail-status-text" style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted);"></div>
            <button class="btn btn-agendar-bar" id="btn-agendar-bar" onclick="openCalendarScheduleModal()"
                style="display: none;">
                <i class="fas fa-plus-circle"></i> Cadastrar Horário
            </button>
        </div>
    </div>

    <div class="calendar-view" id="calendar-view" style="<?= $initial_view === 'calendar' ? '' : 'display: none;' ?>">
        <div id="professor-calendar" class="professor-calendar"></div>
    </div>

    <div class="gantt-view" id="gantt-view" style="<?= $initial_view === 'gantt' ? '' : 'display: none;' ?>">
        <div class="gantt-section" style="margin-top: 0;">
            <div class="gantt-container">
                <div class="gantt-legend">
                    <div class="gantt-legend-item">
                        <div class="gantt-legend-dot busy"></div> Ocupado
                    </div>
                    <div class="gantt-legend-item">
                        <div class="gantt-legend-dot free"></div> Disponível
                    </div>
                </div>
                <div class="gantt-chart" id="gantt-chart"></div>
            </div>
        </div>
    </div>
</div>

<div class="gantt-tooltip" id="gantt-tooltip"></div>


</div>

<script>
    // Pass data to JS
    window.__docentesData = <?= $docentes_json ?>;
    window.__isAdmin = <?= json_encode(isAdmin()) ?>;
    window.__isCRI = <?= json_encode(isCRI()) ?>;
    window.__isGestor = <?= json_encode(isGestor()) ?>;
</script>

<script type="application/json" id="gantt-data"><?= $gantt_json ?></script>

<?php if (!$embedded_mode)
    include __DIR__ . '/../components/footer.php'; ?>