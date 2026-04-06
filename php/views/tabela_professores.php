<?php
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/utils.php';
include __DIR__ . '/../components/header.php';

$mes_sel = $_GET['mes_sel'] ?? date('Y-m');
$primeiro_dia_mes = date('Y-m-01', strtotime($mes_sel . '-01'));
$ultimo_dia_mes = date('Y-m-t', strtotime($mes_sel . '-01'));
$total_dias_uteis = contarDiasUteisNoMes($primeiro_dia_mes, $ultimo_dia_mes);

$docentes_query = mysqli_query($conn, "SELECT id, nome, area_conhecimento FROM docente ORDER BY nome ASC");
$gantt_docentes = [];
while ($d = mysqli_fetch_assoc($docentes_query)) {
    $did = (int) $d['id'];
    $res_aulas = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT COUNT(*) as total FROM Agenda a
        JOIN turma t ON a.turma_id = t.id
        WHERE a.docente_id = $did
          AND t.data_inicio <= '$ultimo_dia_mes'
          AND t.data_fim >= '$primeiro_dia_mes'
    "));
    $total_aulas = $res_aulas['total'] ?? 0;
    $dias_reais_ocup = calcularDiasOcupadosNoMes($conn, $did, $primeiro_dia_mes, $ultimo_dia_mes);

    $gantt_docentes[] = [
        'id' => $did,
        'nome' => $d['nome'],
        'area' => $d['area_conhecimento'] ?: 'Outros',
        'total_aulas' => $total_aulas,
        'dias_ocupados' => $dias_reais_ocup,
        'proximo_dia_livre' => getProximoDiaLivre($conn, $did)
    ];
}

$areas_query = mysqli_query($conn, "SELECT DISTINCT area_conhecimento FROM docente WHERE area_conhecimento IS NOT NULL AND area_conhecimento != '' ORDER BY area_conhecimento ASC");
$areas_list = mysqli_fetch_all($areas_query, MYSQLI_ASSOC);

$per_page = 10;
$tabela_json = json_encode([
    'docentes' => $gantt_docentes,
    'per_page' => $per_page,
    'total_dias_uteis' => $total_dias_uteis
], JSON_UNESCAPED_UNICODE);
?>

<div class="page-header">
    <h2><i class="fas fa-table" class="tabela-prof-header-icon"></i> Tabela de Professores</h2>
</div>
<p class="tabela-prof-desc">Visão geral da disponibilidade de todos os professores.</p>

<form method="GET" action="tabela_professores.php" class="tabela-prof-controls">
    <div class="search-box">
        <i class="fas fa-search"></i>
        <input type="text" id="search-professor" class="form-input search-input" placeholder="Buscar professor...">
    </div>
    <div class="filter-select-group">
        <label><i class="bi bi-calendar-event"></i> Mês:</label>
        <input type="month" name="mes_sel" value="<?= $mes_sel ?>" class="form-input filter-month"
            onchange="this.form.submit()">

        <label class="filter-label"><i class="bi bi-funnel-fill"></i> Área:</label>
        <select id="filter-area" name="filter_area" class="form-input filter-select">
            <option value="">Todas as Áreas</option>
            <?php foreach ($areas_list as $ar): ?>
                <option value="<?= htmlspecialchars($ar['area_conhecimento']) ?>">
                    <?= htmlspecialchars($ar['area_conhecimento']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label class="filter-label"><i class="bi bi-sort-down"></i> Ordenar:</label>
        <select id="filter-disponibilidade" class="form-input filter-select-large">
            <option value="mais_livre">Mais disponíveis primeiro</option>
            <option value="mais_ocupado">Menos disponíveis primeiro</option>
        </select>
    </div>
</form>

<div class="card tabela-prof-card">
    <div class="tabela-prof-legend">
        <span><span class="avail-dot free"></span> Disponível</span>
        <span><span class="avail-dot busy"></span> Ocupado</span>
    </div>
    <div class="table-container">
        <table class="tabela-prof-table" id="tabela-prof">
            <thead>
                <tr>
                    <th class="col-prof">Professor</th>
                    <th>Disponibilidade</th>
                    <th class="col-center" style="width: 120px;">Aulas</th>
                    <th class="col-center" style="width: 130px;">Dias ocupados do mês</th>
                </tr>
            </thead>
            <tbody id="tabela-prof-body"></tbody>
        </table>
    </div>

    <div class="tabela-prof-pagination">
        <button class="pagination-btn" id="page-prev"><i class="fas fa-chevron-left"></i> Anterior</button>
        <span class="pagination-info" id="page-info"></span>
        <button class="pagination-btn" id="page-next">Próxima <i class="fas fa-chevron-right"></i></button>
    </div>
</div>

<script type="application/json" id="tabela-prof-data"><?= $tabela_json ?></script>
<script src="../../js/tabela_view.js"></script>

<?php include __DIR__ . '/../components/footer.php'; ?>