<?php
require_once __DIR__ . '/../configs/db.php';

$action = $_GET['action'] ?? '';

if ($action === 'simulate_resources') {
    $data_inicio = $_GET['data_inicio'] ?? '';
    $data_fim = $_GET['data_fim'] ?? '';
    $dias_semana = $_GET['dias_semana'] ?? []; // Array
    $h_start = $_GET['h_start'] ?? '';
    $h_end = $_GET['h_end'] ?? '';
    $area = $_GET['sim_area'] ?? '';
    $curso_id = (int) ($_GET['curso_id'] ?? 0);

    if (!$data_inicio || !$data_fim || empty($dias_semana) || !$h_start || !$h_end) {
        echo json_encode(['error' => 'Parâmetros incompletos para simulação.']);
        exit;
    }

    // 1. Get all Agendas that overlap with the date range
    // Now correctly checks ALL docentes via Agenda table (since salvar_horario creates entries for all)
    $overlap_q = "
        SELECT a.docente_id, a.ambiente_id, a.dia_semana, a.horario_inicio, a.horario_fim
        FROM agenda a
        JOIN turma t ON a.turma_id = t.id
        WHERE t.data_inicio <= '$data_fim'
          AND t.data_fim >= '$data_inicio'
    ";
    $res = mysqli_query($conn, $overlap_q);
    $busy_docentes = [];
    $busy_ambientes = [];

    while ($row = mysqli_fetch_assoc($res)) {
        // Check if day matches
        if (!in_array($row['dia_semana'], $dias_semana))
            continue;

        // Check if time overlaps: (start1 < end2) AND (end1 > start2)
        // If times are missing (reservation), assume it covers the whole day
        if ((!$row['horario_inicio'] || !$row['horario_fim']) || ($row['horario_inicio'] < $h_end && $row['horario_fim'] > $h_start)) {
            $busy_docentes[$row['docente_id']] = true;
            $busy_ambientes[$row['ambiente_id']] = true;
        }
    }

    // Also check Turma table for docente_id2, docente_id3, docente_id4 (for legacy data)
    $turma_overlap = mysqli_query($conn, "
        SELECT t.docente_id1, t.docente_id2, t.docente_id3, t.docente_id4, t.dias_semana AS t_dias, t.periodo AS t_periodo
        FROM turma t
        WHERE t.data_inicio <= '$data_fim' AND t.data_fim >= '$data_inicio'
    ");
    while ($trow = mysqli_fetch_assoc($turma_overlap)) {
        $t_dias = explode(',', $trow['t_dias']);
        $has_overlap_day = false;
        foreach ($dias_semana as $d) {
            if (in_array(trim($d), array_map('trim', $t_dias))) {
                $has_overlap_day = true;
                break;
            }
        }
        if (!$has_overlap_day)
            continue;

        // Mark all docentes as busy
        for ($i = 1; $i <= 4; $i++) {
            $did = $trow["docente_id$i"];
            if ($did)
                $busy_docentes[$did] = true;
        }
    }

    // 2. Get Docentes filtered by area and calculate their occupancy %
    require_once __DIR__ . '/../configs/utils.php';
    $where_area = $area ? " WHERE area_conhecimento = '" . mysqli_real_escape_string($conn, $area) . "' " : "";
    $all_docentes = mysqli_fetch_all(mysqli_query($conn, "SELECT id, nome, area_conhecimento FROM docente $where_area"), MYSQLI_ASSOC);

    $available_docentes = [];
    foreach ($all_docentes as $d) {
        if (!isset($busy_docentes[$d['id']])) {
            // Calculate total occupancy in the month for sorting (freest first)
            $mes_ref = date('Y-m', strtotime($data_inicio));
            $occ = calcularDiasOcupadosNoMes($conn, $d['id'], date('Y-m-01', strtotime($mes_ref)), date('Y-m-t', strtotime($mes_ref)));
            $d['occupancy_count'] = $occ;
            $available_docentes[] = $d;
        }
    }

    // Sort available docentes by occupancy_count ASC (freest first = most available)
    usort($available_docentes, function ($a, $b) {
        return $a['occupancy_count'] - $b['occupancy_count'];
    });

    // 3. Get all Ambientes NOT in busy_ambientes
    $ambiente_where = "";
    if (stripos($area, 'informática') !== false || stripos($area, 'ti') !== false) {
        $ambiente_where = " ORDER BY CASE WHEN tipo LIKE '%Laboratório%' THEN 0 ELSE 1 END, nome ASC ";
    } else {
        $ambiente_where = " ORDER BY CASE WHEN tipo LIKE '%Sala de Aula%' OR tipo LIKE '%Teórica%' THEN 0 ELSE 1 END, nome ASC ";
    }

    $all_ambientes = mysqli_fetch_all(mysqli_query($conn, "SELECT id, nome, tipo, cidade FROM ambiente $ambiente_where"), MYSQLI_ASSOC);
    $available_ambientes = [];
    foreach ($all_ambientes as $a) {
        if (!isset($busy_ambientes[$a['id']])) {
            $available_ambientes[] = $a;
        }
    }

    echo json_encode([
        'docentes' => $available_docentes,
        'ambientes' => $available_ambientes
    ]);
    exit;
}
