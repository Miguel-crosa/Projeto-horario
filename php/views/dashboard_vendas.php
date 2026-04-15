<?php
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/utils.php';
require_once __DIR__ . '/../configs/auth.php';

// Proteção de Rota: Admin, Gestor e CRI
// Se for uma requisição AJAX, retornamos apenas o JSON de dados
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    // A lógica de busca continua abaixo e damos echo no final
}

if (!isAdmin() && !isGestor() && !isCRI() && !isProfessor()) {
    header("Location: ../../index.php");
    exit;
}

if (!isset($_GET['ajax'])) {
    include __DIR__ . '/../components/header.php';
}

$mes_sel = $_GET['mes_sel'] ?? date('Y-m');
$primeiro_dia = date('Y-m-01', strtotime($mes_sel . '-01'));
$ultimo_dia = date('Y-m-t', strtotime($mes_sel . '-01'));
$dias_no_mes = date('t', strtotime($mes_sel . '-01'));

require_once __DIR__ . '/../models/AgendaModel.php';

$agendaModel = new AgendaModel($conn);

// Busca Docentes
$where_vendas = "WHERE ativo = 1";
if (isProfessor()) {
    $logged_did = getUserDocenteId();
    if ($logged_did) {
        $where_vendas = "WHERE id = " . (int)$logged_did;
    } else {
        // Se for professor sem vínculo, não mostra nada ou mostra erro? por enquanto, mostra vazio.
        $where_vendas = "WHERE 1=0";
    }
}
$docentes = mysqli_fetch_all(mysqli_query($conn, "SELECT id, nome, area_conhecimento FROM docente $where_vendas ORDER BY nome ASC"), MYSQLI_ASSOC);
$docente_ids = array_column($docentes, 'id');

// Busca Feriados do mês (como feito na agenda para expansão)
$holidays = mysqli_fetch_all(mysqli_query($conn, "SELECT name, date, end_date FROM holidays WHERE (date <= '$ultimo_dia' AND end_date >= '$primeiro_dia')"), MYSQLI_ASSOC);

// Usa AgendaModel para buscar TODOS os eventos expandidos por dia
$expanded_events = $agendaModel->getExpandedAgenda($docente_ids, $primeiro_dia, $ultimo_dia);

// Organiza os eventos por docente e data para facilitar o acesso no loop
$events_by_docente = [];
foreach ($expanded_events as $event) {
    $pid = $event['docente_id'];
    $dt = $event['agenda_data'];
    $events_by_docente[$pid][$dt][] = $event;
}

// Preparar dados para o Gantt
$gantt_docentes = [];

foreach ($docentes as $d) {
    $did = (int) $d['id'];

    // Filtramos os eventos específicos deste docente para o Frontend
    $docente_events = $events_by_docente[$did] ?? [];

    // Geramos o status diário consolidado (unificando com a lógica do Dashboard de Gestão)
    $daily_status = getDailyStatusForMonth($conn, $did, $mes_sel);

    $gantt_docentes[] = [
        'id' => $d['id'],
        'nome' => $d['nome'],
        'area' => $d['area_conhecimento'],
        'events' => $docente_events,
        'daily_status' => $daily_status // Novo campo consolidado
    ];
}


$meses_nomes = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
$mes_label = $meses_nomes[(int) date('m', strtotime($mes_sel)) - 1] . ' ' . date('Y', strtotime($mes_sel));

$gantt_data = [
    'year' => (int) date('Y', strtotime($mes_sel)),
    'month' => (int) date('m', strtotime($mes_sel)),
    'daysInMonth' => (int) $dias_no_mes,
    'docentes' => $gantt_docentes,
    'holidays' => $holidays,
    'mes_label' => $mes_label
];

// Se for AJAX, enviamos o JSON e encerramos
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    echo json_encode($gantt_data);
    exit;
}

?>

<link rel="stylesheet" href="../../css/dashboard_vendas.css">

<div class="dashboard-vendas-hero">
    <h1 class="vendas-title">Alocação de Docentes</h1>

    <div class="vendas-legend-container">
        <div class="legend-item"><span class="dot orange"></span> Aula</div>
        <div class="legend-item"><span class="dot yellow"></span> Reservado</div>
        <div class="legend-item"><span class="dot blue"></span> Feriado</div>
        <div class="legend-item"><span class="dot gray"></span> Folga</div>
        <div class="legend-item"><span class="dot purple"></span> Indisp.</div>
        <div class="legend-item"><span class="dot green-light"></span> Livre</div>
    </div>

    <div class="vendas-search-wrapper">
        <div class="vendas-search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="gantt-search-docente" placeholder="Pesquisar..."
                oninput="filterGanttDocentes()">
        </div>
    </div>
</div>

<div class="gantt-controls-container">
    <div class="month-selector-vendas">
        <button type="button" onclick="navigateVendasMonth(-1)" class="btn-nav"><i
                class="fas fa-chevron-left"></i></button>
        <span id="vendas-month-label"><?= $mes_label ?></span>
        <button type="button" onclick="navigateVendasMonth(1)" class="btn-nav"><i
                class="fas fa-chevron-right"></i></button>
        <input type="hidden" id="vendas-mes-sel" value="<?= $mes_sel ?>">
    </div>

    <div class="view-mode-badge">Visualização Mensal</div>
</div>

<div class="gantt-vendas-container card">
    <div class="gantt-vendas-wrapper">
        <div id="gantt-vendas-chart">
            <!-- Renderizado via JS -->
            <div class="gantt-loading">
                <i class="fas fa-spinner fa-spin"></i> Preparando Linha do Tempo...
            </div>
        </div>
    </div>
</div>

<div class="gantt-tooltip" id="gantt-tooltip-vendas"></div>

<script>
    window.__ganttData = <?= json_encode($gantt_data) ?>;
    window.__holidays = <?= json_encode($holidays) ?>;
    window.__mesSel = "<?= $mes_sel ?>";
</script>
<script src="../../js/dashboard_vendas.js"></script>

<?php
if (!isset($_GET['ajax'])) {
    include __DIR__ . '/../components/footer.php';
}
?>