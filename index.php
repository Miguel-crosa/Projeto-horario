<?php
require_once __DIR__ . '/php/configs/db.php';
require_once __DIR__ . '/php/configs/utils.php';
require_once __DIR__ . '/php/configs/auth.php';

if (isCRI()) {
    header("Location: php/views/dashboard_vendas.php");
    exit;
}

if (isProfessor()) {
    header("Location: php/views/dashboard_vendas.php");
    exit;
}

if (!isset($_GET['ajax_render'])) {
    include __DIR__ . '/php/components/header.php';
    echo '<link rel="stylesheet" href="css/dashboard.css">';
    echo '<link rel="stylesheet" href="css/substituicao.css">';
    echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';
}

$count_prof = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM docente WHERE ativo = 1"))[0];
$count_salas = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM ambiente"))[0];
$count_turmas = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM turma"))[0];
$count_cursos = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM curso"))[0];

$filtro_prof = $_GET['filtro_prof'] ?? 'mais_livre';
$filtro_area = $_GET['filtro_area'] ?? '';
$filtro_nome = $_GET['filtro_nome'] ?? '';
$filtro_docente_id = $_GET['docente_id'] ?? '';
$mes_sel = $_GET['mes_sel'] ?? date('Y-m');
$primeiro_dia_mes = date('Y-m-01', strtotime($mes_sel . '-01'));
$ultimo_dia_mes = date('Y-m-t', strtotime($mes_sel . '-01'));

// Conta apenas as turmas que terão aula no mês selecionado
$sql_count = "SELECT COUNT(DISTINCT a.id) FROM agenda a JOIN turma t ON a.turma_id = t.id WHERE t.data_inicio <= '$ultimo_dia_mes' AND t.data_fim>= '$primeiro_dia_mes'";
if (!empty($filtro_docente_id)) {
    $sql_count .= " AND a.docente_id = " . (int) $filtro_docente_id;
}
$count_aulas = mysqli_fetch_row(mysqli_query($conn, $sql_count))[0];
$total_dias_uteis = contarDiasUteisNoMes($primeiro_dia_mes, $ultimo_dia_mes);

$where_clauses = [];
if (!empty($filtro_docente_id)) {
    $safe_did = (int) $filtro_docente_id;
    $where_clauses[] = "id = $safe_did";
}
if (!empty($filtro_area)) {
    $area_esc = mysqli_real_escape_string($conn, $filtro_area);
    $where_clauses[] = "(area_conhecimento LIKE '%$area_esc%')";
}

// Lógica: Monta a consulta de professores. Se houver filtros, adiciona as cláusulas WHERE correspondentes.
$prof_page = (int) ($_GET['prof_page'] ?? 1);
$prof_limit = 5;
$offset = ($prof_page - 1) * $prof_limit;

$is_filtered = !empty($where_clauses);
$where_sql = $is_filtered ? ' WHERE ativo = 1 AND ' . implode(' AND ', $where_clauses) : ' WHERE ativo = 1 ';
$profs_query = mysqli_query($conn, "SELECT id, nome, area_conhecimento FROM docente $where_sql");

$prof_resumo_temp = [];
if ($profs_query) {
    while ($d = mysqli_fetch_assoc($profs_query)) {
        $did_tmp = (int) $d['id'];
        $dias_ocup_mes_selecionado = calcularDiasOcupadosNoMes($conn, $did_tmp, $primeiro_dia_mes, $ultimo_dia_mes);
        $prof_resumo_temp[] = [
            'id' => $did_tmp,
            'nome' => $d['nome'],
            'area' => $d['area_conhecimento'],
            'dias_ocupados_mes' => $dias_ocup_mes_selecionado
        ];
    }
}

// Ordenação padrão
usort($prof_resumo_temp, function ($a, $b) use ($filtro_prof) {
    if ($filtro_prof === 'mais_ocupado') {
        if ($b['dias_ocupados_mes'] !== $a['dias_ocupados_mes']) {
            return $b['dias_ocupados_mes'] - $a['dias_ocupados_mes'];
        }
        return strcmp($a['nome'], $b['nome']);
    }
    if ($a['dias_ocupados_mes'] !== $b['dias_ocupados_mes']) {
        return $a['dias_ocupados_mes'] - $b['dias_ocupados_mes'];
    }
    return strcmp($a['nome'], $b['nome']);
});

$total_professores_filtered = count($prof_resumo_temp);
$total_pages_prof = ceil($total_professores_filtered / $prof_limit);
$prof_resumo_final = array_slice($prof_resumo_temp, $offset, $prof_limit);

$prof_resumo = [];
$ano_atual = date('Y', strtotime($mes_sel . '-01'));
$meses_nomes_curtos = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
$meses_nomes_completos = [
    'Janeiro',
    'Fevereiro',
    'Março',
    'Abril',
    'Maio',
    'Junho',
    'Julho',
    'Agosto',
    'Setembro',
    'Outubro',
    'Novembro',
    'Dezembro'
];

foreach ($prof_resumo_final as $pr) {
    $did_tmp = $pr['id'];
    $ano_resumo = getDailyStatusForYear($conn, $did_tmp, $ano_atual);
    $dias_livres = countFreeDays($ano_resumo);

    // Calcula se os períodos (Manhã, Tarde, Noite) estão integralmente livres no ano para exibição de tags rápidas
    $is_manha_free = true;
    $is_tarde_free = true;
    $is_noite_free = true;
    $has_manha_work = false;
    $has_tarde_work = false;
    $has_noite_work = false;
    foreach ($ano_resumo as $day => $d_data) {
        if ($d_data['Manhã'] === 'Ocupado' || $d_data['Manhã'] === 'Reservado')
            $is_manha_free = false;
        if ($d_data['Tarde'] === 'Ocupado' || $d_data['Tarde'] === 'Reservado')
            $is_tarde_free = false;
        if ($d_data['Noite'] === 'Ocupado' || $d_data['Noite'] === 'Reservado')
            $is_noite_free = false;

        if ($d_data['Manhã'] === 'Livre')
            $has_manha_work = true;
        if ($d_data['Tarde'] === 'Livre')
            $has_tarde_work = true;
        if ($d_data['Noite'] === 'Livre')
            $has_noite_work = true;
    }
    $is_manha_free = $is_manha_free && $has_manha_work;
    $is_tarde_free = $is_tarde_free && $has_tarde_work;
    $is_noite_free = $is_noite_free && $has_noite_work;

    $prof_resumo[] = [
        'id' => $did_tmp,
        'nome' => $pr['nome'],
        'area' => $pr['area'],
        'annual_status' => $ano_resumo,
        'dias_livres' => $dias_livres,
        'free_periods' => [
            'Manhã' => $is_manha_free,
            'Tarde' => $is_tarde_free,
            'Noite' => $is_noite_free
        ]
    ];
}

$selected_prof_nome = '';
if (!empty($filtro_docente_id)) {
    foreach ($prof_resumo as $p) {
        if ($p['id'] == $filtro_docente_id) {
            $selected_prof_nome = $p['nome'];
            break;
        }
    }
    if (empty($selected_prof_nome)) {
        $p_query = mysqli_query($conn, "SELECT nome FROM docente WHERE id = " . (int) $filtro_docente_id);
        if ($p_data = mysqli_fetch_assoc($p_query)) {
            $selected_prof_nome = $p_data['nome'];
        }
    }
}

$areas_query = mysqli_query($conn, "SELECT DISTINCT area_conhecimento FROM docente WHERE ativo = 1 AND area_conhecimento IS NOT
    NULL AND area_conhecimento != '' ORDER BY area_conhecimento ASC");
$areas_raw = mysqli_fetch_all($areas_query, MYSQLI_ASSOC);
// Extrai áreas únicas (suporta docentes com múltiplas áreas separadas por vírgula)
$unique_areas = [];
foreach ($areas_raw as $ar) {
    $parts = array_map('trim', explode(',', $ar['area_conhecimento']));
    foreach ($parts as $p) {
        if (!empty($p) && !in_array($p, $unique_areas)) {
            $unique_areas[] = $p;
        }
    }
}
sort($unique_areas);
$areas_list = array_map(function ($a) {
    return ['area_conhecimento' => $a];
}, $unique_areas);

$turmas_cidade = mysqli_fetch_all(mysqli_query($conn, "
    SELECT COALESCE(amb.cidade, 'Sede') AS cidade, COUNT(t.id) AS total
    FROM turma t LEFT JOIN ambiente amb ON t.ambiente_id = amb.id
    GROUP BY COALESCE(amb.cidade, 'Sede') ORDER BY total DESC
    "), MYSQLI_ASSOC);

$proximas_query = mysqli_query($conn, "
    SELECT t.id, amb.cidade, c.nome AS curso_nome, t.data_inicio, t.tipo
    FROM turma t JOIN curso c ON t.curso_id = c.id LEFT JOIN ambiente amb ON t.ambiente_id = amb.id
    WHERE t.data_inicio >= '" . date('Y-m-d') . "' ORDER BY t.data_inicio ASC LIMIT 15
");

$proximas = [];
if ($proximas_query) {
    while ($row = mysqli_fetch_assoc($proximas_query)) {
        $tid = (int) $row['id'];
        $agenda_res = mysqli_query($conn, "SELECT MIN(data) as fd FROM agenda WHERE turma_id = $tid AND data >= '" . date('Y-m-d') . "'");
        $agenda_row = mysqli_fetch_assoc($agenda_res);

        $data_final = (!empty($agenda_row['fd'])) ? $agenda_row['fd'] : $row['data_inicio'];

        // Se cair no domingo, pula para segunda
        if (date('w', strtotime($data_final)) == 0) {
            $data_final = date('Y-m-d', strtotime($data_final . ' +1 day'));
        }

        $row['data_inicio_real'] = $data_final;
        $proximas[] = $row;
    }
}
usort($proximas, function ($a, $b) {
    return strcmp($a['data_inicio_real'], $b['data_inicio_real']);
});
$proximas = array_slice($proximas, 0, 5);

// --- Turmas Encerrando (Próximos 7 dias) ---
$data_hoje = date('Y-m-d');
$data_7dias = date('Y-m-d', strtotime('+7 days'));
$encerrando_query = mysqli_query($conn, "
    SELECT t.id, t.sigla, c.nome AS curso_nome, t.data_fim
    FROM turma t 
    JOIN curso c ON t.curso_id = c.id
    WHERE t.data_fim BETWEEN '$data_hoje' AND '$data_7dias'
    ORDER BY t.data_fim ASC
");
$encerrando = mysqli_fetch_all($encerrando_query, MYSQLI_ASSOC);

$cores = ['#e53935', '#1976d2', '#388e3c', '#ff8f00', '#9c27b0', '#00838f', '#6d4c41'];
?>
<?php if (!isset($_GET['ajax_render'])): ?>
    <div class="dashboard-home dashboard-container" id="dashboard-main-wrapper" style="padding-top: 20px;">
    <?php endif; ?>

    <div class="dashboard-content-ajax" id="dashboard-ajax-content">
        <div class="welcome-banner animate-fade-in">
            <div class="welcome-date"><i class="bi bi-calendar3"></i> <?= date('d/m/Y') ?></div><br>
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h2><i class="bi bi-speedometer2"></i> Dashboard — Gestão Escolar SENAI</h2><br>
                    <p>Visão geral do sistema com turmas, professores, ambientes e agenda.</p>
                </div>
                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; justify-content: flex-end;">
                    <button class="btn btn-primary btn-producao-mobile" onclick="openProducaoModal()"
                        style="background: #1976d2; border-color: #1565c0; box-shadow: 0 4px 10px rgba(25, 118, 210, 0.2); font-weight: 700;">
                        <i class="fas fa-chart-line"></i> <span class="hide-mobile">Produção Aluno/Hora</span><span
                            class="show-mobile">Prod. A/H</span>
                    </button>
                    <button class="btn btn-primary btn-financeiro-mobile" onclick="openRessarcimentoModal()"
                        style="background: #388e3c; border-color: #2e7d32; box-shadow: 0 4px 10px rgba(56, 142, 60, 0.2); font-weight: 700;">
                        <i class="fas fa-hand-holding-usd"></i> <span class="hide-mobile">Métricas de
                            Ressarcimento</span><span class="show-mobile">Ressarc.</span>
                    </button>
                    <button class="btn btn-primary" onclick="openSubstituicaoModal()"
                        style="background: #1565c0; border-color: #0d47a1; box-shadow: 0 4px 10px rgba(21, 101, 192, 0.2); font-weight: 700;">
                        <i class="fas fa-user-friends"></i> <span class="hide-mobile">Professores
                            Disponíveis</span><span class="show-mobile">Subst.</span>
                    </button>
                    <button class="btn btn-primary btn-financeiro-mobile" onclick="openDespesasModal()"
                        style="background: #e65100; border-color: #ef6c00; box-shadow: 0 4px 10px rgba(230, 81, 0, 0.2); font-weight: 700;">
                        <i class="fas fa-money-bill-wave"></i> <span class="hide-mobile">Previsão de
                            Despesas</span><span class="show-mobile">Despesas</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="stats-grid">
            <?php
            $stats = [
                ['Professores', $count_prof, 'fa-chalkboard-teacher', 'var(--primary-red)', 'php/views/professores.php', 'Gerenciar'],
                ['Ambientes', $count_salas, 'fa-door-open', '#1976d2', 'php/views/salas.php', 'Gerenciar'],
                ['Turmas', $count_turmas, 'fa-users', '#388e3c', 'php/views/turmas.php', 'Gerenciar'],
                ['Cursos', $count_cursos, 'fa-graduation-cap', '#ff8f00', 'php/views/cursos.php', 'Gerenciar'],
                ['Aulas Agendadas', $count_aulas, 'fa-calendar-check', '#9c27b0', 'php/views/agenda_professores.php', 'Visualizar'],
            ];
            $i = 1;
            foreach ($stats as $s): ?>
                <div class="stat-card animate-fade-in delay-<?= $i++ ?> shimmer-hover"
                    onclick="location.href='<?= $prefix . $s[4] ?>'" style="cursor: pointer;">
                    <div class="stat-icon" style="background: <?= $s[3] ?>1a; color: <?= $s[3] ?>;">
                        <i class="fas <?= $s[2] ?>"></i>
                    </div>
                    <div class="stat-number"><?= $s[1] ?></div>
                    <div class="stat-label"><?= $s[0] ?></div>
                    <a href="<?= $prefix . $s[4] ?>" class="stat-link" style="color: <?= $s[3] ?>;"><?= $s[5] ?> <i
                            class="fas fa-arrow-right"></i></a>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card"
            style="margin-bottom: 20px; background: var(--card-bg); border: 1px solid var(--border-color); padding: 20px; border-radius: 12px;">
            <form method="GET" action="index.php" id="dashboard-filter-form"
                style="display: flex; justify-content: space-between; align-items: flex-end; gap: 20px; flex-wrap: wrap;">
                <div style="display: flex; gap: 15px; align-items: flex-end; flex: 1; flex-wrap: wrap;">


                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <label
                            style="font-weight: 700; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Professor
                            Selecionado</label>
                        <button type="button" class="btn btn-primary" id="btn-selecionar-professor"
                            style="background: <?= !empty($filtro_docente_id) ? '#2e7d32' : '#ed1c16' ?>; border-color: <?= !empty($filtro_docente_id) ? '#1b5e20' : '#ed1c16' ?>; padding: 10px 24px; font-weight: 700; border-radius: 8px; display: flex; align-items: center; gap: 10px; height: 42px;">
                            <i class="fas fa-user-plus"></i>
                            <span
                                id="btn-prof-label"><?= !empty($selected_prof_nome) ? htmlspecialchars($selected_prof_nome) : 'Selecionar Professor' ?></span>
                        </button>
                        <input type="hidden" name="docente_id" id="dashboard-docente-id"
                            value="<?= htmlspecialchars($filtro_docente_id) ?>">
                    </div>

                    <?php if (!empty($filtro_docente_id)): ?>
                        <button type="button" onclick="clearTeacherFilter()" class="btn btn-export"
                            style="height: 42px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; border-radius: 8px; background: var(--bg-color); border: 1px solid var(--border-color); color: var(--text-color); padding: 0 15px; font-weight: 600; font-size: 0.85rem; cursor: pointer;">
                            <i class="fas fa-times"></i> Limpar Seleção
                        </button>
                    <?php endif; ?>
                </div>

                <div style="display: flex; flex-direction: column; gap: 8px; align-items: flex-end;">
                    <label
                        style="font-weight: 700; font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Navegação
                        por Mês</label>
                    <div
                        style="display: flex; align-items: center; gap: 15px; background: var(--bg-color); padding: 5px 10px; border-radius: 50px; border: 1px solid var(--border-color); box-shadow: 0 2px 8px rgba(0,0,0,0.05); height: 42px;">
                        <button type="button" onclick="navigateDashboardMonth(-1)" class="month-btn-nav"
                            style="width:30px;height:30px; color:var(--text-color); border:none; display: flex; align-items: center; justify-content: center; background: var(--card-bg); border-radius: 50%; border: 1px solid var(--border-color); cursor:pointer; transition: all 0.2s;"><i
                                class="fas fa-chevron-left" style="font-size:0.75rem;"></i></button>
                        <span id="dashboard-month-label"
                            style="font-weight: 800; font-size: 0.9rem; min-width: 140px; text-align: center; text-transform: capitalize; color: var(--text-color);">
                            <?= $meses_nomes_completos[(int) date('m', strtotime($mes_sel . '-01')) - 1] . ' ' . date('Y', strtotime($mes_sel . '-01')) ?>
                        </span>
                        <button type="button" onclick="navigateDashboardMonth(1)" class="month-btn-nav"
                            style="width:30px;height:30px; color:var(--text-color); border:none; display: flex; align-items: center; justify-content: center; background: var(--card-bg); border-radius: 50%; border: 1px solid var(--border-color); cursor:pointer; transition: all 0.2s;"><i
                                class="fas fa-chevron-right" style="font-size:0.75rem;"></i></button>
                    </div>
                    <input type="hidden" name="mes_sel" id="mes_sel_hidden" value="<?= $mes_sel ?>">
                </div>
            </form>
        </div>

        <!-- Filtros por Área -->
        <?php if (!empty($areas_list)): ?>
            <div class="area-filter-bar"
                style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; padding: 16px 20px; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; align-items: center;">
                <span
                    style="font-weight: 800; font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-right: 8px; display: flex; align-items: center; gap: 6px;">
                    <i class="fas fa-filter"></i> Filtrar por Área:
                </span>
                <a href="?mes_sel=<?= urlencode($mes_sel) ?>&docente_id=<?= urlencode($filtro_docente_id) ?>&prof_page=1"
                    class="area-filter-btn <?= empty($filtro_area) ? 'active' : '' ?>"
                    style="padding: 7px 18px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; text-decoration: none; transition: all 0.25s ease; border: 1.5px solid <?= empty($filtro_area) ? 'var(--primary-red)' : 'var(--border-color)' ?>; background: <?= empty($filtro_area) ? 'var(--primary-red)' : 'var(--card-bg)' ?>; color: <?= empty($filtro_area) ? '#fff' : 'var(--text-color)' ?>; cursor: pointer;">
                    Todos
                </a>
                <?php foreach ($areas_list as $al):
                    $area_val = $al['area_conhecimento'];
                    $is_active = ($filtro_area === $area_val);
                    // Conta docentes nessa área
                    $area_count_esc = mysqli_real_escape_string($conn, $area_val);
                    $area_count_q = mysqli_query($conn, "SELECT COUNT(*) as c FROM docente WHERE ativo = 1 AND area_conhecimento LIKE '%$area_count_esc%'");
                    $area_count = mysqli_fetch_assoc($area_count_q)['c'];
                    ?>
                    <a href="?filtro_area=<?= urlencode($area_val) ?>&mes_sel=<?= urlencode($mes_sel) ?>&docente_id=<?= urlencode($filtro_docente_id) ?>&prof_page=1"
                        class="area-filter-btn <?= $is_active ? 'active' : '' ?>"
                        style="padding: 7px 18px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; text-decoration: none; transition: all 0.25s ease; border: 1.5px solid <?= $is_active ? 'var(--primary-red)' : 'var(--border-color)' ?>; background: <?= $is_active ? 'var(--primary-red)' : 'var(--card-bg)' ?>; color: <?= $is_active ? '#fff' : 'var(--text-color)' ?>; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                        <?= htmlspecialchars($area_val) ?>
                        <span
                            style="background: <?= $is_active ? 'rgba(255,255,255,0.3)' : 'rgba(0,0,0,0.08)' ?>; padding: 1px 7px; border-radius: 10px; font-size: 0.7rem;"><?= $area_count ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="dashboard-grid">
            <div class="dash-section allow-overflow">
                <div class="dash-section-header dash-section-header-sticky"
                    style="justify-content: space-between; display: flex; align-items: center; padding-bottom: 15px; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                    <h3
                        style="display: flex; align-items: center; gap: 10px; margin: 0; font-size: 1.1rem; flex-wrap: wrap;">
                        <i class="fas fa-users" style="color: var(--primary-red);"></i> Resumo Anual de Horários
                        <div
                            style="display: inline-flex; align-items: center; gap: 8px; margin-left: 15px; background: rgba(0,0,0,0.05); padding: 4px 8px; border-radius: 20px;">
                            <button type="button" onclick="navigateDashboardMonth(-12)" title="Ano Anterior"
                                style="border: none; background: transparent; cursor: pointer; color: var(--text-color);"><i
                                    class="fas fa-chevron-left" style="font-size: 0.8rem;"></i></button>
                            <span
                                style="font-weight: 800; font-size: 0.9rem; color: var(--primary-red);"><?= $ano_atual ?></span>
                            <button type="button" onclick="navigateDashboardMonth(12)" title="Próximo Ano"
                                style="border: none; background: transparent; cursor: pointer; color: var(--text-color);"><i
                                    class="fas fa-chevron-right" style="font-size: 0.8rem;"></i></button>
                        </div>

                        <?php if ($total_pages_prof > 1): ?>
                            <div class="prof-pagination"
                                style="display: inline-flex; align-items: center; gap: 10px; margin-left: 15px;">
                                <a href="?prof_page=<?= max(1, $prof_page - 1) ?>&filtro_area=<?= urlencode($filtro_area) ?>&mes_sel=<?= urlencode($mes_sel) ?>&docente_id=<?= urlencode($filtro_docente_id) ?>#resumo-anual"
                                    title="Anterior"
                                    style="width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 50%; color: var(--text-color); text-decoration: none; <?= $prof_page <= 1 ? 'opacity:0.3; pointer-events:none;' : '' ?>"><i
                                        class="fas fa-arrow-left" style="font-size: 0.7rem;"></i></a>

                                <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700;">
                                    <?= $prof_page ?> / <?= $total_pages_prof ?>
                                </span>

                                <a href="?prof_page=<?= min($total_pages_prof, $prof_page + 1) ?>&filtro_area=<?= urlencode($filtro_area) ?>&mes_sel=<?= urlencode($mes_sel) ?>&docente_id=<?= urlencode($filtro_docente_id) ?>#resumo-anual"
                                    title="Próximo"
                                    style="width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 50%; color: var(--text-color); text-decoration: none; <?= $prof_page >= $total_pages_prof ? 'opacity:0.3; pointer-events:none;' : '' ?>"><i
                                        class="fas fa-arrow-right" style="font-size: 0.7rem;"></i></a>
                            </div>
                        <?php endif; ?>
                    </h3>
                    <div style="display: flex; gap: 15px; font-size: 0.8rem; font-weight: 600;">
                        <span style="display: flex; align-items: center; gap: 5px; color: #4caf50;"><span
                                style="width: 10px; height: 10px; background: #4caf50; display: inline-block; border-radius: 2px;"></span>
                            Livre</span>
                        <span style="display: flex; align-items: center; gap: 5px; color: #e53935;"><span
                                style="width: 10px; height: 10px; background: #e53935; display: inline-block; border-radius: 2px;"></span>
                            Ocupado</span>
                        <span style="display: flex; align-items: center; gap: 5px; color: #ff8f00;"><span
                                style="width: 10px; height: 10px; background: #ff8f00; display: inline-block; border-radius: 2px;"></span>
                            Reservado</span>
                        <span style="display: flex; align-items: center; gap: 5px; color: #1565c0;"><span
                                style="width: 10px; height: 10px; background: #1565c0; display: inline-block; border-radius: 2px;"></span>
                            Feriado / Férias</span>
                        <span style="display: flex; align-items: center; gap: 5px; color: #555;"><span
                                style="width: 10px; height: 10px; background: #555; display: inline-block; border-radius: 2px;"></span>
                            Indisponível</span>
                    </div>
                </div>
                <div class="dash-section-body" style="padding: 0;">
                    <?php if (empty($prof_resumo)): ?>
                        <div class="td-empty" style="padding: 40px; text-align: center; color: var(--text-muted);">
                            <i class="fas fa-search"
                                style="font-size: 2rem; display: block; margin-bottom: 10px; opacity: .4;"></i>
                            <?php if (empty($where_clauses)): ?>
                                Use o filtro acima para pesquisar um professor e ver seu resumo anual.
                            <?php else: ?>
                                Nenhum professor encontrado com esses critérios.
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="prof-anual-list" id="resumo-anual"
                            style="display: flex; flex-direction: column; gap: 35px;">
                            <?php foreach ($prof_resumo as $pr): ?>
                                <div class="prof-anual-item"
                                    style="background: var(--card-bg); padding: 25px; border-radius: 16px; border: 1px solid var(--border-color); box-shadow: 0 4px 20px rgba(0,0,0,0.08); transition: transform 0.2s;">
                                    <div
                                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                        <div style="font-weight: 800; font-size: 1rem; text-transform: uppercase;">
                                            <a href="javascript:void(0)"
                                                onclick="openTeacherAgenda(<?= $pr['id'] ?>, '<?= addslashes($pr['nome']) ?>', '<?= $mes_sel ?>')"
                                                style="color: inherit; text-decoration: none;">
                                                <?= htmlspecialchars($pr['nome']) ?>
                                            </a>
                                            <span
                                                style="color: #888; font-weight: normal; font-size: 0.85rem; text-transform: none;">·
                                                <?= htmlspecialchars($pr['area']) ?></span>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 15px;">
                                            <span style="color: #4caf50; font-weight: 700; font-size: 0.9rem;">
                                                <?= $pr['dias_livres'] ?> dias livres
                                            </span>
                                            <i class="far fa-calendar-alt" style="color: #888;"></i>
                                        </div>
                                    </div>

                                    <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                                        <?php
                                        foreach ($pr['free_periods'] as $per => $is_free):
                                            if (!$is_free)
                                                continue;
                                            $icon = ($per == 'Manhã') ? 'fa-sun' : (($per == 'Tarde') ? 'fa-cloud-sun' : 'fa-moon');
                                            ?>
                                            <div
                                                style="background: rgba(46, 125, 50, 0.1); color: #4caf50; padding: 6px 14px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; display: flex; align-items: center; gap: 8px; border: 1px solid rgba(46, 125, 50, 0.2);">
                                                <i class="fas <?= $icon ?>"></i> <?= $per ?> Livre
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <div class="timeline-container" style="position: relative;">
                                        <!-- Botões Navegação Paginada -->
                                        <button
                                            onclick="const _gridPrev = this.parentElement.querySelector('.timeline-grid'); _gridPrev.scrollBy({left: -_gridPrev.clientWidth, behavior: 'smooth'})"
                                            class="timeline-nav-btn prev">
                                            <i class="fas fa-chevron-left"></i>
                                        </button>

                                        <div class="timeline-grid"
                                            style="display: flex; overflow-x: auto; background: var(--bg-color); border-radius: 12px; scroll-behavior: smooth; scroll-snap-type: x mandatory; border: 1px solid var(--border-color);">
                                            <?php
                                            // Agrupa o status anual do docente mês a mês
                                            $months_data = [];
                                            foreach ($pr['annual_status'] as $date => $data) {
                                                $m_idx = (int) date('m', strtotime($date));
                                                $months_data[$m_idx][$date] = $data;
                                            }
                                            ksort($months_data);

                                            foreach ($months_data as $m_idx => $days):
                                                $m_name = $meses_nomes_completos[$m_idx - 1];

                                                // Conta os dias ocupados vs livres neste mês
                                                $m_busy_count = 0;
                                                $m_free_count = 0;
                                                foreach ($days as $dk => $dd) {
                                                    if ($dd['is_sunday'])
                                                        continue;

                                                    $has_busy = ($dd['Manhã'] === 'Ocupado' || $dd['Manhã'] === 'Reservado' || $dd['Tarde'] === 'Ocupado' || $dd['Tarde'] === 'Reservado' || $dd['Noite'] === 'Ocupado' || $dd['Noite'] === 'Reservado' || $dd['Integral'] === 'Ocupado' || $dd['Integral'] === 'Reservado');
                                                    if ((isset($dd['holiday']) && $dd['holiday']) || (isset($dd['vacation']) && $dd['vacation']))
                                                        continue;

                                                    if ($has_busy)
                                                        $m_busy_count++;
                                                    else
                                                        $m_free_count++;
                                                }
                                                ?>
                                                <div class="month-group" data-month="<?= $m_idx ?>"
                                                    style="min-width: 100%; scroll-snap-align: start; display: flex; flex-direction: column; gap: 8px; padding: 15px; box-sizing: border-box;">
                                                    <div class="month-header"
                                                        style="font-size: 0.9rem; font-weight: 900; text-transform: uppercase; color: var(--corTxt3); border-bottom: 2px solid <?= $m_busy_count > 0 ? '#e53935' : '#4caf50' ?>; padding-bottom: 5px; margin-bottom: 8px; letter-spacing: 1.5px; display: flex; justify-content: space-between; align-items: center;">
                                                        <span><?= $m_name ?></span>
                                                        <span
                                                            style="font-size: 0.7rem; font-weight: 600; display: flex; gap: 10px; align-items: center;">
                                                            <?php if ($m_busy_count > 0): ?>
                                                                <span style="color: #e53935;"><i class="fas fa-times-circle"></i>
                                                                    <?= $m_busy_count ?>
                                                                    ocupado<?= $m_busy_count > 1 ? 's' : '' ?></span>
                                                            <?php endif; ?>
                                                            <span style="color: #4caf50;"><i class="fas fa-check-circle"></i>
                                                                <?= $m_free_count ?>
                                                                livre<?= $m_free_count > 1 ? 's' : '' ?></span>
                                                            <span style="color: #888;">Ano <?= $ano_atual ?></span>
                                                        </span>
                                                    </div>
                                                    <div class="timeline-days-container"
                                                        style="display: flex; gap: 2px; overflow-x: auto; padding-bottom: 5px; scrollbar-width: thin;">
                                                        <?php foreach ($days as $date => $data):
                                                            $d = date('d', strtotime($date));
                                                            $dow = date('N', strtotime($date));
                                                            $dias_nomes_curtos = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom'];
                                                            $nome_dia = $dias_nomes_curtos[$dow];

                                                            // Verifica se QUALQUER período no dia está ocupado
                                                            $any_busy = ($data['Manhã'] === 'Ocupado' || $data['Manhã'] === 'Reservado' || $data['Tarde'] === 'Ocupado' || $data['Tarde'] === 'Reservado' || $data['Noite'] === 'Ocupado' || $data['Noite'] === 'Reservado' || $data['Integral'] === 'Ocupado' || $data['Integral'] === 'Reservado');

                                                            // Define as cores do cabeçalho do dia (Visual semelhante ao da agenda completa)
                                                            $bg_day = '#2e7d32'; // Verde = Livre
                                                            $txt_day = '#ffffff';
                                                            $label_top = $d;

                                                            if (isset($data['holiday']) && $data['holiday']) {
                                                                $bg_day = '#1565c0'; // Azul para Feriado
                                                                $label_top = 'F';
                                                            } elseif (isset($data['vacation']) && $data['vacation']) {
                                                                $bg_day = '#1565c0'; // Azul para Férias
                                                                $label_top = 'F';
                                                            } elseif ($data['is_sunday']) {
                                                                $bg_day = '#a84a4a'; // Vermelho suave para Domingo
                                                            } elseif ($any_busy) {
                                                                $bg_day = '#e53935'; // Vermelho forte para Ocupado
                                                            } elseif ($data['is_weekend']) {
                                                                $bg_day = '#2e7d32';
                                                                $txt_day = '#ffffff';
                                                            }
                                                            ?>
                                                            <?php
                                                            $is_sat = (date('N', strtotime($date)) == 6);
                                                            ?>
                                                            <div class="timeline-day"
                                                                onclick="window.location.href='php/views/agenda_professores.php?docente_id=<?= $pr['id'] ?>&month=<?= date('Y-m', strtotime($date)) ?>&view_mode=timeline'"
                                                                style="flex: 1; min-width: 32px; max-width: 42px; display: flex; flex-direction: column; gap: 3px; cursor: pointer; transition: transform 0.2s;"
                                                                onmouseover="this.style.transform='translateY(-2px)'"
                                                                onmouseout="this.style.transform='none'"
                                                                title="<?= (isset($data['holiday']) && $data['holiday']) ? 'FERIADO: ' . $data['holiday'] : ((isset($data['vacation']) && $data['vacation']) ? 'BLOQUEIO: ' . $data['vacation'] : ($any_busy ? 'OCUPADO' : 'LIVRE')) ?>">

                                                                <?php
                                                                // Gradient for header like agenda_professores.php
                                                                $bg_gradient = 'linear-gradient(135deg, #2e7d32, #1b5e20)';
                                                                if (isset($data['holiday']) && $data['holiday'] || isset($data['vacation']) && $data['vacation']) {
                                                                    $bg_gradient = 'linear-gradient(135deg, #1565c0, #1976d2)';
                                                                } elseif ($data['is_sunday']) {
                                                                    $bg_gradient = '#a84a4a';
                                                                } elseif ($any_busy) {
                                                                    $bg_gradient = 'linear-gradient(135deg, #e53935, #c62828)';
                                                                } elseif ($data['is_weekend']) {
                                                                    $bg_gradient = 'linear-gradient(135deg, #2e7d32, #1b5e20)';
                                                                }
                                                                ?>

                                                                <div
                                                                    style="text-align: center; font-size: 0.8rem; font-weight: 800; padding: 7px 0; background: <?= $bg_gradient ?>; border-radius: 6px; color: <?= $txt_day ?>; line-height: 1.1; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                                                    <div><?= str_pad($label_top, 2, '0', STR_PAD_LEFT) ?></div>
                                                                    <div
                                                                        style="font-size: 0.6rem; opacity: 0.9; text-transform: uppercase;">
                                                                        <?= substr($nome_dia, 0, 3) ?>
                                                                    </div>
                                                                </div>
                                                                <?php
                                                                $periods = ['Manhã', 'Tarde', 'Noite'];
                                                                foreach ($periods as $p):
                                                                    // Define a cor de cada período na subbarra inferior
                                                                    if ((isset($data['holiday']) && $data['holiday']) || (isset($data['vacation']) && $data['vacation'])) {
                                                                        $p_color = '#1565c0'; // Azul = Férias/Feriado
                                                                    } elseif ($data['is_sunday']) {
                                                                        $p_color = '#a84a4a'; // Vermelho suave para Domingo
                                                                    } elseif ($data[$p] === 'Ocupado') {
                                                                        $p_color = '#e53935'; // Vermelho = Ocupado
                                                                    } elseif ($data[$p] === 'OFF_SCHEDULE') {
                                                                        $p_color = '#555'; // Cinza = Fora do Horário
                                                                    } elseif ($data[$p] === 'Reservado') {
                                                                        $p_color = '#ff8f00'; // Laranja = Reservado
                                                                    } elseif ($is_sat && $p === 'Noite') {
                                                                        $p_color = '#ccc'; // Cinza Claro = Sábado à Noite (Inativo - Fallback)
                                                                    } else {
                                                                        $p_color = '#4caf50'; // Verde = Livre
                                                                    }
                                                                    ?>
                                                                    <?php
                                                                    $status_label = ($data[$p] === 'OFF_SCHEDULE' || ($is_sat && $p === 'Noite')) ? 'Indisponível' : $data[$p];
                                                                    ?>
                                                                    <div style="height: 6px; width: 100%; background: <?= $p_color ?>; border-radius: 1px;"
                                                                        title="<?= $date ?> - <?= $p ?>: <?= $status_label ?>">
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <!-- Added wrapper for arrows to not overlay scrollbar -->
                                        <button
                                            onclick="const _gridNext = this.parentElement.querySelector('.timeline-grid'); _gridNext.scrollBy({left: _gridNext.clientWidth, behavior: 'smooth'})"
                                            class="timeline-nav-btn next">
                                            <i class="fas fa-chevron-right"></i>
                                        </button>
                                    </div>
                                    <div
                                        style="display: flex; gap: 15px; font-size: 0.75rem; color: var(--text-muted); margin-top: 15px; font-weight: 700; flex-wrap: wrap; align-items: center; background: var(--bg-color); padding: 10px 20px; border-radius: 10px;">
                                        <span style="color: var(--text-color); margin-right: 5px;"><i
                                                class="fas fa-info-circle"></i> Legenda de Períodos:</span>
                                        <span style="display: flex; align-items: center; gap: 6px;"><span
                                                style="width: 12px; height: 4px; background: #4caf50; border-radius: 1px; display: inline-block;"></span>
                                            Manhã</span>
                                        <span style="display: flex; align-items: center; gap: 6px;"><span
                                                style="width: 12px; height: 4px; background: #4caf50; border-radius: 1px; display: inline-block;"></span>
                                            Tarde</span>
                                        <span style="display: flex; align-items: center; gap: 6px;"><span
                                                style="width: 12px; height: 4px; background: #4caf50; border-radius: 1px; display: inline-block;"></span>
                                            Noite</span>
                                        <span style="display: flex; align-items: center; gap: 6px;"><span
                                                style="width: 12px; height: 4px; background: #555; border-radius: 1px; display: inline-block;"></span>
                                            Indisponível</span>
                                        <span style="margin-left: auto; font-size: 0.7rem; opacity: 0.8; font-style: italic;">
                                            * Períodos exibidos de cima para baixo
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="sidebar-column">
                <div class="dash-section">
                    <div class="dash-section-header">
                        <h3><i class="fas fa-map-marked-alt" style="color: #1976d2;"></i> Turmas por Cidade</h3>
                    </div>
                    <div class="dash-section-body">
                        <?php if (empty($turmas_cidade)): ?>
                            <p class="text-center" style="color: var(--text-muted);">Nenhuma turma cadastrada.</p>
                        <?php else: ?>
                            <?php foreach ($turmas_cidade as $i => $tc):
                                $cor = $cores[$i % count($cores)]; ?>
                                <div class="city-list-item">
                                    <div style="display: flex; align-items: center;">
                                        <span class="city-dot" style="background: <?= $cor ?>;"></span>
                                        <span style="font-weight: 600;"><?= htmlspecialchars($tc['cidade']) ?></span>
                                    </div>
                                    <span
                                        style="font-weight: 800; font-size: 1.1rem; color: <?= $cor ?>;"><?= $tc['total'] ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="dash-section"
                    style="border-left: 4px solid var(--primary-red); background: rgba(229, 57, 53, 0.03);">
                    <div class="dash-section-header">
                        <h3 style="color: var(--primary-red);"><i class="fas fa-exclamation-circle"></i> Turmas
                            Encerrando (7 dias)</h3>
                    </div>
                    <div class="dash-section-body">
                        <?php if (empty($encerrando)): ?>
                            <p class="text-center" style="color: var(--text-muted); font-size: 0.85rem;">Nenhuma turma
                                encerrando esta semana.</p>
                        <?php else: ?>
                            <?php foreach ($encerrando as $te): ?>
                                <div class="city-list-item"
                                    style="flex-direction: column; align-items: flex-start; gap: 4px; border-bottom: 1px dashed rgba(229, 57, 53, 0.2); padding-bottom: 10px; margin-bottom: 10px;">
                                    <div style="font-weight: 700; font-size: .9rem; color: var(--primary-red);">Turma
                                        <?= htmlspecialchars($te['sigla']) ?>
                                    </div>
                                    <div style="font-size: .8rem; color: var(--text-muted);">
                                        <?= htmlspecialchars($te['curso_nome']) ?>
                                    </div>
                                    <div style="font-size: .78rem; color: #555; font-weight: 700;">
                                        <i class="fas fa-calendar-times"></i> Encerramento:
                                        <?= date('d/m/Y', strtotime($te['data_fim'])) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="dash-section">
                    <div class="dash-section-header">
                        <h3><i class="fas fa-rocket" style="color: #ff8f00;"></i> Próximas Turmas</h3>
                    </div>
                    <div class="dash-section-body">
                        <?php if (empty($proximas)): ?>
                            <p class="text-center" style="color: var(--text-muted);">Nenhuma turma futura.</p>
                        <?php else: ?>
                            <?php foreach ($proximas as $pt): ?>
                                <div class="city-list-item" style="flex-direction: column; align-items: flex-start; gap: 4px;">
                                    <div style="font-weight: 700; font-size: .9rem;">Turma #<?= $pt['id'] ?></div>
                                    <div style="font-size: .8rem; color: var(--text-muted);">
                                        <?= htmlspecialchars($pt['curso_nome']) ?> · <?= $pt['tipo'] ?>
                                        <?php if (!empty($pt['cidade'])): ?> · <i class="fas fa-map-marker-alt"></i>
                                            <?= htmlspecialchars($pt['cidade']) ?>         <?php endif; ?>
                                    </div>
                                    <div style="font-size: .78rem; color: var(--primary-red); font-weight: 600;">
                                        <i class="fas fa-calendar"></i> Início:
                                        <?= date('d/m/Y', strtotime($pt['data_inicio_real'])) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modais de Produção Aluno/Hora -->
    <link rel="stylesheet" href="css/producao_dashboard.css">
    <script src="js/producao_aluno_hora.js"></script>

    <!-- Modal 1: Visão Geral e Gráfico -->
    <div id="modal-producao-geral" class="modal-producao">
        <div class="modal-producao-content animate-pop-in">
            <div class="modal-producao-header">
                <h3><i class="fas fa-chart-bar"></i> Produção Aluno/Hora por Docente</h3>
                <button class="modal-producao-close"
                    onclick="closeProducaoModal('modal-producao-geral')">&times;</button>
            </div>
            <div class="modal-producao-body">
                <p
                    style="text-align: center; color: var(--text-muted); font-size: 0.95rem; margin-top: -10px; margin-bottom: 25px; font-weight: 500;">
                    <i class="fas fa-info-circle"></i> Clique em uma barra para abrir o detalhamento por turma.
                </p>
                <div class="producao-kpi-container">
                    <div class="producao-kpi-card" style="margin-bottom: 20px;">
                        <span class="kpi-label">Produção Total Acumulada</span>
                        <span class="kpi-value" id="total-producao-geral">0 A/H</span>
                    </div>
                </div>
                <div class="producao-chart-section">
                    <div class="chart-container-wrapper" style="height: 400px;">
                        <canvas id="chartProducaoDocentes"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal 2: Detalhes do Professor -->
    <div id="modal-producao-detalhe" class="modal-producao">
        <div class="modal-producao-content animate-pop-in">
            <div class="modal-producao-header">
                <h3><i class="fas fa-user-tie"></i> Detalhes de Produção: <span id="detalhe-prof-nome">...</span></h3>
                <button class="modal-producao-close"
                    onclick="closeProducaoModal('modal-producao-detalhe')">&times;</button>
            </div>
            <div class="modal-producao-body">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding: 0 25px;">
                    <span
                        style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Turma
                        / Curso</span>
                    <span
                        style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Ação</span>
                </div>
                <div id="lista-turmas-producao" class="turmas-producao-list">
                    <!-- Preenchido via JS -->
                </div>
            </div>
            <div class="modal-producao-footer" style="justify-content: center;">
                <button class="btn btn-secondary" onclick="backToProducaoGeral()"><i class="fas fa-arrow-left"></i>
                    Voltar ao Ranking</button>
            </div>
        </div>
    </div>

    <!-- Modal 3: Pequeno Input de Evasão -->
    <div id="modal-producao-evasao" class="modal-producao">
        <div class="modal-producao-content animate-pop-in" style="max-width: 400px;">
            <div class="modal-producao-header">
                <h3><i class="fas fa-user-minus"></i> Registrar Evasão</h3>
                <button class="modal-producao-close"
                    onclick="closeProducaoModal('modal-producao-evasao')">&times;</button>
            </div>
            <div class="modal-producao-body" style="text-align: center;">
                <p style="font-size: 1rem; color: var(--text-color); margin-bottom: 25px; font-weight: 500;">
                    Informe a quantidade de alunos que saíram desta turma:
                </p>
                <div class="form-group">
                    <input type="number" id="input-qtd-evasao" class="form-input" value="1" min="1"
                        style="font-size: 1.2rem; text-align: center; font-weight: 700;">
                </div>
                <input type="hidden" id="hidden-evasao-turma-id">
                <input type="hidden" id="hidden-evasao-docente-id">
            </div>
            <div class="modal-producao-footer" style="justify-content: center; gap: 15px;">
                <button class="btn btn-secondary"
                    onclick="closeProducaoModal('modal-producao-evasao')">Cancelar</button>
                <button class="btn btn-primary" onclick="confirmarEvasao()" style="background: #d32f2f;">Confirmar
                    Evasão</button>
            </div>
        </div>
    </div>

    <!-- Modal 4: Pequeno Input de Adição de Alunos -->
    <div id="modal-producao-adicao" class="modal-producao">
        <div class="modal-producao-content animate-pop-in" style="max-width: 400px;">
            <div class="modal-producao-header">
                <h3><i class="fas fa-user-plus"></i> Adicionar Aluno(s)</h3>
                <button class="modal-producao-close"
                    onclick="closeProducaoModal('modal-producao-adicao')">&times;</button>
            </div>
            <div class="modal-producao-body" style="text-align: center;">
                <p style="font-size: 1rem; color: var(--text-color); margin-bottom: 25px; font-weight: 500;">
                    Informe a quantidade de alunos a adicionar nesta turma:
                </p>
                <div class="form-group">
                    <input type="number" id="input-qtd-adicao" class="form-input" value="1" min="1"
                        style="font-size: 1.2rem; text-align: center; font-weight: 700;">
                </div>
                <input type="hidden" id="hidden-adicao-turma-id">
                <input type="hidden" id="hidden-adicao-docente-id">
            </div>
            <div class="modal-producao-footer" style="justify-content: center; gap: 15px;">
                <button class="btn btn-secondary"
                    onclick="closeProducaoModal('modal-producao-adicao')">Cancelar</button>
                <button class="btn btn-primary" onclick="confirmarAdicao()" style="background: #2e7d32;">Confirmar
                    Adição</button>
            </div>
        </div>
    </div>

    <!-- Modal 5: Ressarcimento por Curso (Ranking) -->
    <div id="modal-ressarcimento-ranking" class="modal-producao">
        <div class="modal-producao-content animate-pop-in">
            <div class="modal-producao-header" style="background: #388e3c; color: white;">
                <h3><i class="fas fa-hand-holding-usd"></i> Ressarcimento por Curso</h3>
                <button class="modal-producao-close" onclick="closeFinanceiroModal('modal-ressarcimento-ranking')"
                    style="color: white;">&times;</button>
            </div>
            <div class="modal-producao-body">
                <div class="producao-kpi-container" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="producao-kpi-card" onclick="openRessarcimentoListaModal()"
                        style="cursor: pointer; border-left-color: #388e3c; background: linear-gradient(135deg, #388e3c, #2e7d32);">
                        <span class="kpi-label">Arrecadação Real (Confirmada)</span>
                        <span class="kpi-value" id="total-ressarcido-geral">R$ 0,00</span>
                        <span class="kpi-subtext"><i class="fas fa-search-plus"></i> Ver detalhamento real</span>
                    </div>
                    <div class="producao-kpi-card"
                        style="border-left-color: #fb8c00; background: linear-gradient(135deg, #fb8c00, #ef6c00);">
                        <span class="kpi-label">Pipeline (Reservas)</span>
                        <span class="kpi-value" id="total-ressarcido-pipeline">R$ 0,00</span>
                        <span class="kpi-subtext"><i class="fas fa-info-circle"></i> Turmas em negociação</span>
                    </div>
                </div>
                <div class="producao-chart-section">
                    <div class="chart-container-wrapper" style="height: 400px;">
                        <canvas id="chartRessarcimentoCursos"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal 5.1: Detalhamento de Ressarcimento (Lista) -->
    <div id="modal-ressarcimento-lista" class="modal-producao">
        <div class="modal-producao-content animate-pop-in">
            <div class="modal-producao-header" style="background: #2e7d32; color: white;">
                <h3><i class="fas fa-list-ul"></i> Detalhamento de Ressarcimento</h3>
                <button class="modal-producao-close" onclick="closeFinanceiroModal('modal-ressarcimento-lista')"
                    style="color: white;">&times;</button>
            </div>
            <div class="modal-producao-body">
                <div id="lista-ressarcimento-turmas" class="turmas-producao-list"
                    style="max-height: 500px; overflow-y: auto;">
                    <!-- Preenchido via JS -->
                </div>
            </div>
            <div class="modal-producao-footer" style="justify-content: center;">
                <button class="btn btn-secondary" onclick="backToRessarcimentoRanking()"><i
                        class="fas fa-arrow-left"></i> Voltar ao Gráfico</button>
            </div>
        </div>
    </div>

    <!-- Modal 6: Previsão de Despesas (Gráfico Geral) -->
    <div id="modal-despesas-geral" class="modal-producao">
        <div class="modal-producao-content animate-pop-in">
            <div class="modal-producao-header" style="background: #e65100; color: white;">
                <h3><i class="fas fa-money-bill-wave"></i> Previsão de Despesas por Turma</h3>
                <button class="modal-producao-close" onclick="closeFinanceiroModal('modal-despesas-geral')"
                    style="color: white;">&times;</button>
            </div>
            <div class="modal-producao-body">
                <div class="producao-kpi-container" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="producao-kpi-card" onclick="openDespesasListaModal()"
                        style="cursor: pointer; border-left-color: #e65100; background: linear-gradient(135deg, #e65100, #bf360c);">
                        <span class="kpi-label">Gasto Real (Confirmado)</span>
                        <span class="kpi-value" id="total-previsao-despesas">R$ 0,00</span>
                        <span class="kpi-subtext"><i class="fas fa-search-plus"></i> Ver detalhamento real</span>
                    </div>
                    <div class="producao-kpi-card"
                        style="border-left-color: #546e7a; background: linear-gradient(135deg, #78909c, #546e7a);">
                        <span class="kpi-label">Pipeline de Despesas (Reservas)</span>
                        <span class="kpi-value" id="total-previsao-despesas-pipeline">R$ 0,00</span>
                        <span class="kpi-subtext"><i class="fas fa-info-circle"></i> Previsão para reservas</span>
                    </div>
                </div>
                <div class="producao-chart-section">
                    <div class="chart-container-wrapper" style="height: 400px;">
                        <canvas id="chartDespesasTurmas"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal 7: Detalhamento de Despesas (Lista) -->
    <div id="modal-despesas-lista" class="modal-producao">
        <div class="modal-producao-content animate-pop-in">
            <div class="modal-producao-header" style="background: #bf360c; color: white;">
                <h3><i class="fas fa-list-ul"></i> Detalhamento de Despesas</h3>
                <button class="modal-producao-close" onclick="closeFinanceiroModal('modal-despesas-lista')"
                    style="color: white;">&times;</button>
            </div>
            <div class="modal-producao-body">
                <div id="lista-despesas-turmas" class="turmas-producao-list"
                    style="max-height: 500px; overflow-y: auto;">
                    <!-- Preenchido via JS -->
                </div>
            </div>
            <div class="modal-producao-footer" style="justify-content: center;">
                <button class="btn btn-secondary" onclick="backToDespesasGeral()"><i class="fas fa-arrow-left"></i>
                    Voltar ao Gráfico</button>
            </div>
        </div>
    </div>


    <script src="js/financeiro_dashboard.js"></script>
    <script src="js/dashboard_simulation.js"></script>
    <script>
        function navigateDashboardMonth(offset) {
            const input = document.getElementById('mes_sel_hidden');
            if (!input) return;

            const [year, month] = input.value.split('-').map(Number);
            const date = new Date(year, month - 1 + offset, 1);
            const newMonth = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0');

            input.value = newMonth;

            const form = document.getElementById('dashboard-filter-form');
            const formData = new FormData(form);
            const params = new URLSearchParams(formData);
            params.set('ajax_render', '1');

            const url = 'index.php?' + params.toString();
            const container = document.getElementById('dashboard-ajax-content');
            if (container) container.style.opacity = '0.5';

            fetch(url)
                .then(r => r.text())
                .then(html => {
                    const temp = document.createElement('div');
                    temp.innerHTML = html;
                    const newContent = temp.querySelector('#dashboard-ajax-content');
                    if (newContent && container) {
                        container.innerHTML = newContent.innerHTML;
                        container.style.opacity = '1';

                        const displayParams = new URLSearchParams(formData);
                        window.history.pushState({}, '', 'index.php?' + displayParams.toString());
                        initDashboardScripts();
                    }
                })
                .catch(err => {
                    console.error('Erro na navegação AJAX:', err);
                    form.submit();
                });
        }

        function initDashboardScripts() {
            const mesInput = document.getElementById('mes_sel_hidden');
            if (!mesInput) return;
            const currentMonthData = mesInput.value.split('-');
            const currentMonth = parseInt(currentMonthData[1]);

            document.querySelectorAll('.timeline-grid').forEach(function (grid) {
                const targetGroup = grid.querySelector('.month-group[data-month="' + currentMonth + '"]');
                if (targetGroup) {
                    grid.scrollLeft = targetGroup.offsetLeft;
                }
            });

            const form = document.getElementById('dashboard-filter-form');
            if (form) {
                form.onsubmit = function (e) {
                    e.preventDefault();
                    navigateDashboardMonth(0);
                };
            }

            // Inicializa a lógica do Modal de Seleção de Professor
            const btnSel = document.getElementById('btn-selecionar-professor');
            const profModal = document.getElementById('modal-selecionar-professor');
            const profSearchInput = document.getElementById('prof-search-input');
            const profSearchResults = document.getElementById('prof-search-results');
            const hiddenId = document.getElementById('dashboard-docente-id');
            const btnLabel = document.getElementById('btn-prof-label');
            const docentes = window.__docentesData || [];

            if (btnSel) {
                btnSel.onclick = function () {
                    if (profModal) {
                        profModal.classList.add('active');
                        if (profSearchInput) profSearchInput.value = '';
                        renderModalResults();
                        setTimeout(() => profSearchInput?.focus(), 100);
                    }
                };
            }

            // Modal close logic
            const closeBtn = document.getElementById('modal-prof-close');
            if (closeBtn) {
                closeBtn.onclick = () => {
                    if (profModal) profModal.classList.remove('active');
                };
            }

            // Close on click outside content
            if (profModal) {
                profModal.onclick = (e) => {
                    if (e.target === profModal) profModal.classList.remove('active');
                };
            }

            function renderModalResults() {
                if (!profSearchResults) return;
                const query = (profSearchInput?.value || '').toLowerCase().trim();
                let filtered = docentes.filter(d => !query || d.nome.toLowerCase().includes(query));

                if (filtered.length === 0) {
                    profSearchResults.innerHTML = '<div style="text-align:center; padding:20px; color:#888;">Nenhum professor encontrado.</div>';
                    return;
                }

                profSearchResults.innerHTML = filtered.map(d => `
                        <div class="prof-result-item" data-id="${d.id}" data-nome="${d.nome.replace(/"/g, '&quot;')}" 
                            style="padding: 10px; border-bottom: 1px solid var(--border-color); cursor: pointer; display: flex; justify-content: space-between; align-items: center; transition: background 0.2s;">
                            <div>
                                <strong style="font-size: 0.95rem;">${d.nome}</strong><br>
                                <small style="color: #888; font-size: 0.75rem;">${d.area_conhecimento || 'Outros'}</small>
                            </div>
                            <i class="fas fa-chevron-right" style="color: #ccc; font-size: 0.8rem;"></i>
                        </div>
                    `).join('');

                profSearchResults.querySelectorAll('.prof-result-item').forEach(item => {
                    item.onclick = function () {
                        const id = this.dataset.id;
                        const nome = this.dataset.nome;
                        const hId = document.getElementById('dashboard-docente-id');
                        const bLabel = document.getElementById('btn-prof-label');
                        const bSel = document.getElementById('btn-selecionar-professor');

                        if (hId) hId.value = id;
                        if (bLabel) bLabel.textContent = nome;
                        if (bSel) {
                            bSel.style.background = '#2e7d32';
                            bSel.style.borderColor = '#1b5e20';
                        }
                        if (profModal) profModal.classList.remove('active');
                        navigateDashboardMonth(0);
                    };
                });
            }

            if (profSearchInput) {
                profSearchInput.oninput = renderModalResults;
            }
        }

        function clearTeacherFilter() {
            const hId = document.getElementById('dashboard-docente-id');
            const bLabel = document.getElementById('btn-prof-label');
            const bSel = document.getElementById('btn-selecionar-professor');

            if (hId) hId.value = '';
            if (bLabel) bLabel.textContent = 'Selecionar Professor';
            if (bSel) {
                bSel.style.background = '#ed1c16';
                bSel.style.borderColor = '#ed1c16';
            }
            navigateDashboardMonth(0);
        }

        document.addEventListener('DOMContentLoaded', initDashboardScripts);
    </script>

    <?php if (!isset($_GET['ajax_render'])): ?>
    <script>
        window.__docentesData = <?= json_encode(mysqli_fetch_all(mysqli_query($conn, "SELECT id, nome, area_conhecimento FROM docente WHERE ativo = 1 ORDER BY nome ASC"), MYSQLI_ASSOC)) ?>;
    </script>
</div> <!-- End dashboard-main-wrapper -->

<!-- Funcionalidade: Substituição Temporária de Professores (Padrão SENAI) -->


<div id="modal-substituicao-gera" class="modal-subst">
    <div class="modal-subst-content">
        <div class="modal-subst-header">
            <h3><i class="fas fa-user-clock"></i> Substituição Temporária</h3>
            <button class="modal-subst-close" onclick="closeSubstituicaoModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-subst-body">
            <!-- PASSO 1: ONDE E QUEM -->
            <div class="subst-step-title">1. Identifique a Turma e o Docente Titular</div>
            <div class="subst-filter-grid"
                style="margin-bottom: 20px; background: rgba(0,90,165,0.05); border-color: var(--subst-primary);">
                <div class="subst-filter-item">
                    <label>Turma</label>
                    <div style="display: flex; gap: 8px;">
                        <input type="text" id="subst-turma-display" class="subst-input" readonly
                            placeholder="Selecione uma turma..." style="flex: 1; cursor: pointer;"
                            onclick="abrirPesquisaTurma()">
                        <input type="hidden" id="subst-turma-id" value="">
                        <button type="button" class="subst-btn-icon" onclick="abrirPesquisaTurma()"
                            title="Pesquisar Turma">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="subst-filter-item">
                    <label>Docente a ser Substituído</label>
                    <select id="subst-titular-select" class="subst-input" disabled onchange="onTitularChange(this)">
                        <option value="">-- Selecione a Turma Primeiro --</option>
                    </select>
                </div>
            </div>

            <!-- PASSO 2: CONFIGURAÇÕES -->
            <div id="subst-step-2" style="opacity: 0.5; pointer-events: none; transition: 0.3s;">
                <div class="subst-step-title">2. Configure o Período e a Área</div>
                <div class="subst-filter-grid">
                    <div class="subst-filter-item">
                        <label>Área do Docente</label>
                        <select id="subst-area" class="subst-input">
                            <option value="">Todas as Áreas</option>
                            <?php foreach ($areas_list as $area): ?>
                            <option value="<?= htmlspecialchars($area['area_conhecimento']) ?>">
                                <?= htmlspecialchars($area['area_conhecimento']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="subst-filter-item">
                        <label>Período</label>
                        <select id="subst-periodo" class="subst-input">
                            <option value="Manhã">Manhã</option>
                            <option value="Tarde">Tarde</option>
                            <option value="Noite">Noite</option>
                            <option value="Integral">Integral</option>
                        </select>
                    </div>
                    <div class="subst-filter-item">
                        <label>Data Inicial</label>
                        <input type="date" id="subst-data-inicio" class="subst-input" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="subst-filter-item">
                        <label>Data Final</label>
                        <input type="date" id="subst-data-fim" class="subst-input" value="<?= date('Y-m-d') ?>">
                    </div>
                    <button class="subst-btn-search" onclick="buscarProfessoresDisponiveis()">
                        <i class="fas fa-search"></i> Encontrar Substitutos Disponíveis
                    </button>
                </div>
            </div>

            <!-- PASSO 3: RESULTADOS -->
            <div id="subst-results-wrapper" style="display:none;">
                <div class="subst-step-title">3. Selecione o Professor Substituto</div>
                <div id="subst-results" class="subst-results-container">
                    <div style="padding: 20px; text-align: center; color: var(--subst-text-muted);">
                        Buscando professores disponíveis...
                    </div>
                </div>
            </div>

            <!-- PASSO 4: CONFIRMAÇÃO -->
            <div id="subst-confirmation" class="subst-confirmation-panel" style="display:none;">
                <div class="subst-confirmation-header">
                    <i class="fas fa-check-circle" style="color: var(--subst-success);"></i>
                    Confirmar Substituição Temporária
                </div>
                <p style="font-size: 0.85rem; color: #888; margin-bottom: 20px;">
                    Ao confirmar, o sistema alterará a agenda da turma no período selecionado.
                </p>
                <button class="subst-btn-confirm" onclick="confirmarSubstituicao()">
                    Confirmar Substituição
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Interna: Pesquisa de Turmas -->
<div id="modal-pesquisa-turma" class="modal-subst" style="z-index: 6000;">
    <div class="modal-subst-content" style="max-width: 600px; margin-top: 80px;">
        <div class="modal-subst-header" style="background: #333;">
            <h3><i class="fas fa-search"></i> Pesquisar Turma</h3>
            <button class="modal-subst-close" onclick="fecharPesquisaTurma()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-subst-body">
            <input type="text" id="input-busca-turma" class="subst-input" placeholder="Digite a sigla ou curso..."
                style="width: 100%; margin-bottom: 15px;" oninput="filtrarListaTurmas(this.value)">
            <div id="lista-turmas-pesquisa"
                style="max-height: 400px; overflow-y: auto; border: 1px solid var(--subst-border); border-radius: 8px;">
                <!-- Preenchido via JS -->
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<script src="js/substituicao.js"></script>
<?php include __DIR__ . '/php/components/footer.php'; ?>