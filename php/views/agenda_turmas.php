<?php
/**
 * Cronograma de Turmas — Modelo Timeline
 * Mostra o cronograma das turmas em formato de linha do tempo anual.
 */
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/auth.php';

// Filtros
$search_sigla = isset($_GET['search']) ? $_GET['search'] : '';
$search_prof = isset($_GET['search_prof']) ? $_GET['search_prof'] : '';
$current_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$current_year = (int)date('Y', strtotime($current_month . '-01'));

// Intervalo anual para o cronograma
$first_day = "$current_year-01-01";
$last_day = "$current_year-12-31";

// Buscar Turmas
$where_professor = "";
if (isProfessor()) {
    $logged_did = getUserDocenteId();
    if ($logged_did) {
        $where_professor = " AND (t.docente_id1 = $logged_did OR t.docente_id2 = $logged_did OR t.docente_id3 = $logged_did OR t.docente_id4 = $logged_did)";
    } else {
        $where_professor = " AND 1=0";
    }
}

$where_search = $search_sigla ? " AND (t.sigla LIKE ? OR c.nome LIKE ?)" : "";
$where_prof = $search_prof ? " AND (d1.nome LIKE ? OR d2.nome LIKE ? OR d3.nome LIKE ? OR d4.nome LIKE ?)" : "";

$query_turmas = "
    SELECT t.*, c.nome as curso_nome,
           d1.nome as docente1, d2.nome as docente2, d3.nome as docente3, d4.nome as docente4
    FROM turma t
    JOIN curso c ON t.curso_id = c.id
    LEFT JOIN docente d1 ON t.docente_id1 = d1.id
    LEFT JOIN docente d2 ON t.docente_id2 = d2.id
    LEFT JOIN docente d3 ON t.docente_id3 = d3.id
    LEFT JOIN docente d4 ON t.docente_id4 = d4.id
    WHERE t.ativo = 1 $where_professor $where_search $where_prof
    ORDER BY t.data_inicio ASC, t.sigla ASC
";

$stmt = $mysqli->prepare($query_turmas);
$params = [];
$types = "";

if ($search_sigla) {
    $search_param = "%$search_sigla%";
    $params[] = $search_param; $params[] = $search_param;
    $types .= "ss";
}
if ($search_prof) {
    $prof_param = "%$search_prof%";
    $params[] = $prof_param; $params[] = $prof_param; $params[] = $prof_param; $params[] = $prof_param;
    $types .= "ssss";
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$turmas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Buscar Agenda (Aulas) para estas turmas no intervalo do ano
$agenda_data = [];
$turno_detail = [];

if (!empty($turmas)) {
    $turma_ids = array_column($turmas, 'id');
    $placeholders = implode(',', array_fill(0, count($turma_ids), '?'));
    
    $query_agenda = "
        SELECT a.turma_id, a.data, a.periodo, a.horario_inicio, a.horario_fim
        FROM agenda a
        WHERE a.turma_id IN ($placeholders)
          AND a.data BETWEEN ? AND ?
    ";
    
    $stmt_agenda = $mysqli->prepare($query_agenda);
    $params = array_merge($turma_ids, [$first_day, $last_day]);
    $types = str_repeat('i', count($turma_ids)) . 'ss';
    $stmt_agenda->bind_param($types, ...$params);
    $stmt_agenda->execute();
    $res_agenda = $stmt_agenda->get_result();
    
    while ($row = $res_agenda->fetch_assoc()) {
        $tid = $row['turma_id'];
        $dt = $row['data'];
        $per = $row['periodo'];
        
        $agenda_data[$tid][$dt] = true;
        
        if (!isset($turno_detail[$tid][$dt])) {
            $turno_detail[$tid][$dt] = ['M' => false, 'T' => false, 'N' => false];
        }
        
        if ($per === 'Integral') {
            $turno_detail[$tid][$dt]['M'] = true;
            $turno_detail[$tid][$dt]['T'] = true;
        } else {
            $hi = $row['horario_inicio'];
            $hf = $row['horario_fim'];
            if ($hi < '12:00:00') $turno_detail[$tid][$dt]['M'] = true;
            if ($hi < '18:00:00' && $hf > '12:00:00') $turno_detail[$tid][$dt]['T'] = true;
            if ($hf > '18:00:00' || $hi >= '18:00:00') $turno_detail[$tid][$dt]['N'] = true;
        }
    }
}

// Buscar Feriados
$holidays = [];
$res_h = mysqli_query($conn, "SELECT date FROM holidays WHERE date BETWEEN '$first_day' AND '$last_day'");
while($h = mysqli_fetch_assoc($res_h)) $holidays[$h['date']] = true;

// Labels de meses em PT-BR
$months_pt = [1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'];
$days_nomes_curtos = [1=>'Seg',2=>'Ter',3=>'Qua',4=>'Qui',5=>'Sex',6=>'Sáb',7=>'Dom'];

include __DIR__ . '/../components/header.php';
?>

<link rel="stylesheet" href="../../css/agenda_professores.css">
<style>
    .timeline-day-cell { cursor: default !important; }
    .turma-badge {
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        background: var(--primary-red);
        color: #fff;
    }
    .timeline-grid {
        gap: 15px;
        padding: 15px 5px;
    }
    .month-group {
        border: 1px solid var(--border-color);
        background: var(--card-bg);
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    }
    .month-header {
        border-bottom: 2px solid var(--primary-red);
        padding-bottom: 8px;
        margin-bottom: 12px;
    }
    .month-name {
        font-size: 0.95rem;
        color: var(--text-color);
    }
    .timeline-day-num-box {
        height: 38px;
        width: 38px;
        border-radius: 8px;
        margin-bottom: 4px;
    }
    .timeline-day-cell {
        flex: 0 0 38px;
        gap: 2px;
    }
    .timeline-legend {
        margin-top: 10px;
        padding: 15px;
        background: var(--card-bg);
        border-radius: 12px;
        border: 1px solid var(--border-color);
        display: flex;
        gap: 25px;
        justify-content: center;
        flex-wrap: wrap;
    }
    .legend-item { display: flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 600; }
    .legend-box { width: 14px; height: 14px; border-radius: 3px; }
    
    [data-tema="escuro"] .month-group { background: #1a1a1a; }
    [data-tema="escuro"] .timeline-day-free .timeline-day-num-box { background: rgba(255,255,255,0.05); color: #888; }
</style>

<div class="agenda-header-container">
    <div>
        <h2 style="margin: 0;"><i class="fas fa-stream" style="color: var(--primary-red);"></i> Cronograma de Turmas</h2>
        <p style="margin: 5px 0 0; opacity: 0.7; font-size: 0.85rem; font-weight: 600;">Visualização anual das aulas por turma</p>
    </div>
    
    <div class="month-nav-controls">
        <button onclick="navigateYear(-1)" class="month-btn-nav" title="Ano Anterior"><i class="fas fa-chevron-left"></i></button>
        <span style="font-weight: 900; font-size: 1.2rem; min-width: 80px; text-align: center; color: var(--primary-red);"><?= $current_year ?></span>
        <button onclick="navigateYear(1)" class="month-btn-nav" title="Próximo Ano"><i class="fas fa-chevron-right"></i></button>
    </div>
</div>

<div class="prof-selection-card" style="padding: 15px 20px;">
    <form action="" method="GET" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
        <div style="position: relative; flex: 1; min-width: 250px;">
            <i class="fas fa-search" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted); opacity: 0.5;"></i>
            <input type="text" name="search" value="<?= htmlspecialchars($search_sigla) ?>" 
                   placeholder="Turma ou Curso..." 
                   class="form-input" style="width: 100%; padding-left: 45px;">
        </div>

        <div style="position: relative; flex: 1; min-width: 250px;">
            <i class="fas fa-user-tie" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted); opacity: 0.5;"></i>
            <input type="text" name="search_prof" value="<?= htmlspecialchars($search_prof) ?>" 
                   placeholder="Nome do Professor..." 
                   class="form-input" style="width: 100%; padding-left: 45px;">
        </div>

        <input type="hidden" name="month" value="<?= $current_month ?>">
        
        <button type="submit" class="btn btn-primary" style="height: 42px; padding: 0 25px;">
            <i class="fas fa-filter"></i> Filtrar
        </button>

        <?php if($search_sigla || $search_prof): ?>
            <a href="?month=<?= $current_month ?>" class="btn" style="background: rgba(0,0,0,0.05); color: var(--text-muted); border: 1px solid var(--border-color); height: 42px; display: flex; align-items: center;">Limpar</a>
        <?php endif; ?>
    </form>
</div>

<div class="timeline-legend">
    <div class="legend-item"><div class="legend-box" style="background: linear-gradient(135deg, #e53935, #c62828);"></div> Aula Agendada</div>
    <div class="legend-item"><div class="legend-box" style="background: #a84a4a;"></div> Domingo</div>
    <div class="legend-item"><div class="legend-box" style="background: #1565c0;"></div> Feriado</div>
    <div class="legend-item"><div class="legend-box" style="background: #2e7d32;"></div> Disponível</div>
</div>

<div class="table-container" style="background: transparent; padding: 0; border: none; margin-top: 25px;">
    <?php if (empty($turmas)): ?>
        <div class="card" style="text-align: center; padding: 80px 20px;">
            <i class="fas fa-layer-group" style="font-size: 4rem; opacity: 0.1; margin-bottom: 20px; display: block;"></i>
            <h3 style="color: var(--text-muted);">Nenhuma turma ativa encontrada</h3>
            <p style="color: var(--text-muted); opacity: 0.7;">Tente ajustar os filtros ou busque por outro termo.</p>
        </div>
    <?php else: ?>
        <?php foreach ($turmas as $t): ?>
            <div class="prof-row" style="background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 20px; margin-bottom: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.02);">
                <div class="prof-info-header" style="margin-bottom: 15px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span class="turma-badge"><?= htmlspecialchars($t['tipo']) ?></span>
                        <div>
                            <strong style="font-size: 1.15rem; color: var(--text-color);"><?= htmlspecialchars($t['sigla']) ?></strong>
                            <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">
                                <?= htmlspecialchars($t['curso_nome']) ?>
                                <span style="margin-left: 10px; color: var(--primary-red); opacity: 0.8;">
                                    <?php 
                                    $profs = array_filter([$t['docente1'], $t['docente2'], $t['docente3'], $t['docente4']]);
                                    echo !empty($profs) ? "• " . implode(' / ', $profs) : "";
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted);">
                            <i class="far fa-calendar-alt" style="color: var(--primary-red);"></i> 
                            <?= date('d/m/y', strtotime($t['data_inicio'])) ?> — <?= date('d/m/y', strtotime($t['data_fim'])) ?>
                        </div>
                    </div>
                </div>

                <div class="timeline-grid">
                    <?php for ($m = 1; $m <= 12; $m++): 
                        $ms = sprintf("%04d-%02d", $current_year, $m);
                        $days_in_m = (int)date('t', strtotime($ms . '-01'));
                        
                        $m_busy = 0;
                        for ($d = 1; $d <= $days_in_m; $d++) {
                            $dt = sprintf("%s-%02d", $ms, $d);
                            if (isset($agenda_data[$t['id']][$dt])) $m_busy++;
                        }
                    ?>
                        <div class="month-group" data-month="<?= $m ?>" style="min-width: fit-content; flex-shrink: 0;">
                            <div class="month-header">
                                <div class="month-name"><?= $months_pt[$m] ?></div>
                                <div class="month-stats">
                                    <span style="color: var(--primary-red);"><?= $m_busy ?> dias</span>
                                </div>
                            </div>
                            
                            <div class="timeline-days-container">
                                <?php for ($i = 1; $i <= $days_in_m; $i++):
                                    $dt = sprintf("%s-%02d", $ms, $i);
                                    $dow = (int)date('N', strtotime($dt));
                                    $t_detail = isset($turno_detail[$t['id']][$dt]) ? $turno_detail[$t['id']][$dt] : ['M'=>false,'T'=>false,'N'=>false];
                                    
                                    $is_aula = isset($agenda_data[$t['id']][$dt]);
                                    $is_sunday = ($dow == 7);
                                    $is_feriado = isset($holidays[$dt]);
                                    $is_within_range = ($dt >= $t['data_inicio'] && $dt <= $t['data_fim']);
                                    
                                    $cell_class = 'timeline-day-free';
                                    if ($is_sunday) $cell_class = 'timeline-day-sunday';
                                    elseif ($is_feriado) $cell_class = 'timeline-day-feriado';
                                    elseif ($is_aula) $cell_class = 'timeline-day-busy';
                                    
                                    $bg_day = 'rgba(0,0,0,0.05)';
                                    $color_num = 'var(--text-muted)';
                                    
                                    if ($is_sunday) { $bg_day = '#a84a4a'; $color_num = '#fff'; }
                                    elseif ($is_feriado) { $bg_day = '#1565c0'; $color_num = '#fff'; }
                                    elseif ($is_aula) { $bg_day = 'linear-gradient(135deg, #e53935, #c62828)'; $color_num = '#fff'; }
                                    elseif ($is_within_range) { $bg_day = 'rgba(46, 125, 50, 0.1)'; }
                                ?>
                                    <div class="timeline-day-cell <?= $cell_class ?>" 
                                         title="Dia <?= $i ?> (<?= $days_nomes_curtos[$dow] ?>)<?= $is_aula ? ' — AULA' : ($is_feriado ? ' — FERIADO' : '') ?>">
                                        <div class="timeline-day-num-box" style="background: <?= $bg_day ?>; color: <?= $color_num ?>;">
                                            <div style="font-size: 0.85rem; font-weight: 800;"><?= $i ?></div>
                                            <div class="timeline-dow-label"><?= $days_nomes_curtos[$dow] ?></div>
                                        </div>
                                        <?php foreach (['M', 'T', 'N'] as $pk):
                                            $bar_color = 'rgba(0,0,0,0.03)';
                                            if ($is_aula && $t_detail[$pk]) $bar_color = '#e53935';
                                            elseif ($is_sunday || $is_feriado) $bar_color = 'rgba(0,0,0,0.1)';
                                            elseif ($is_within_range) $bar_color = '#2e7d32';
                                        ?>
                                            <div style="height: 4px; width: 100%; background: <?= $bar_color ?>; border-radius: 1px; margin-top: 2px;"></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
    function navigateYear(offset) {
        const url = new URL(window.location.href);
        const currentMonth = url.searchParams.get('month') || '<?= date('Y-m') ?>';
        const [year, month] = currentMonth.split('-').map(Number);
        const nextMonth = (year + offset) + '-' + String(month).padStart(2, '0');
        url.searchParams.set('month', nextMonth);
        window.location.href = url.toString();
    }

    document.addEventListener('DOMContentLoaded', function() {
        const currentMonthNum = <?= (int)date('m', strtotime($current_month . '-01')) ?>;
        // Scroll horizontal apenas se estivermos vendo o ano atual
        const now = new Date();
        const viewingYear = <?= $current_year ?>;
        
        if (viewingYear === now.getFullYear()) {
            document.querySelectorAll('.timeline-grid').forEach(grid => {
                const targetMonth = grid.querySelector('.month-group[data-month="' + (now.getMonth() + 1) + '"]');
                if (targetMonth) {
                    // Centralizar um pouco o mês atual
                    grid.scrollLeft = targetMonth.offsetLeft - (grid.offsetWidth / 2) + (targetMonth.offsetWidth / 2);
                }
            });
        }
    });
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>
