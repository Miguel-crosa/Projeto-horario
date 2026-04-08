<?php
// Polyfill para mb_strtolower se a extensão mbstring não estiver ativa no Linux
if (!function_exists('mb_strtolower')) {
    function mb_strtolower($str, $encoding = 'UTF-8')
    {
        return strtolower($str);
    }
}

// Fallback para mysqli_fetch_all (requer mysqlnd, que pode faltar em alguns compartilhados)
if (!function_exists('mysqli_fetch_all')) {
    function mysqli_fetch_all($result, $mode = MYSQLI_NUM)
    {
        $all = [];
        while ($row = $result->fetch_array($mode)) {
            $all[] = $row;
        }
        return $all;
    }
}

function calcularDiasOcupadosNoMes($conn, $did, $primeiro, $ultimo)
{
    $res = mysqli_query($conn, "
        SELECT a.dia_semana, a.data AS individual_date, t.data_inicio, t.data_fim, a.status
        FROM agenda a
        LEFT JOIN turma t ON a.turma_id = t.id
        WHERE a.docente_id = $did
          AND (
              (t.id IS NOT NULL AND t.data_inicio <= '$ultimo' AND t.data_fim >= '$primeiro')
              OR 
              (a.data IS NOT NULL AND a.data BETWEEN '$primeiro' AND '$ultimo')
          )
    ");

    $dias_contados = [];
    $total_ocupados = 0;
    $daysMap = [0 => 'Domingo', 1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado'];

    while ($row = mysqli_fetch_assoc($res)) {
        if ($row['individual_date']) {
            $ds = $row['individual_date'];
            if (!isset($dias_contados[$ds])) {
                $dias_contados[$ds] = true;
                $total_ocupados++;
            }
            continue;
        }

        if ($row['data_inicio'] && $row['data_fim']) {
            $dia_db = mb_strtolower(trim($row['dia_semana']), 'UTF-8');

            $start_ts = strtotime($row['data_inicio']);
            $end_ts = strtotime($row['data_fim']);
            if (!$start_ts || !$end_ts || $start_ts > $end_ts)
                continue;

            $it = new DateTime(max($primeiro, $row['data_inicio']));
            $itFim = new DateTime(min($ultimo, $row['data_fim']));
            // FIX: normaliza para meia-noite para evitar corte do último dia
            $it->setTime(0, 0, 0);
            $itFim->setTime(0, 0, 0);

            while ($it->format('Y-m-d') <= $itFim->format('Y-m-d')) {
                $w = (int) $it->format('w');
                $dia_nome = mb_strtolower($daysMap[$w], 'UTF-8');
                if ($dia_nome === $dia_db || strpos($dia_db, $dia_nome) !== false) {
                    $ds = $it->format('Y-m-d');
                    if (!isset($dias_contados[$ds])) {
                        $dias_contados[$ds] = true;
                        $total_ocupados++;
                    }
                }
                $it->modify('+1 day');
            }
        }
    }

    // 3. Preparação / Atestados
    $res_p = mysqli_query($conn, "
        SELECT tipo, data_inicio, data_fim, dias_semana
        FROM preparacao_atestados
        WHERE docente_id = $did AND status = 'ativo'
          AND data_inicio <= '$ultimo' AND data_fim >= '$primeiro'
    ");
    while ($row = mysqli_fetch_assoc($res_p)) {
        $it = new DateTime(max($primeiro, $row['data_inicio']));
        $itFim = new DateTime(min($ultimo, $row['data_fim']));
        // FIX: normaliza para meia-noite
        $it->setTime(0, 0, 0);
        $itFim->setTime(0, 0, 0);

        $dias_permitidos = !empty($row['dias_semana']) ? explode(',', $row['dias_semana']) : [];

        while ($it->format('Y-m-d') <= $itFim->format('Y-m-d')) {
            $dow = $it->format('N');
            if ($row['tipo'] === 'preparação' && !empty($dias_permitidos)) {
                if (!in_array($dow, $dias_permitidos)) {
                    $it->modify('+1 day');
                    continue;
                }
            }
            $ds = $it->format('Y-m-d');
            if (!isset($dias_contados[$ds])) {
                $dias_contados[$ds] = true;
                $total_ocupados++;
            }
            $it->modify('+1 day');
        }
    }

    return $total_ocupados;
}

function contarDiasUteisNoMes($primeiro, $ultimo)
{
    $total = 0;
    $dt = new DateTime($primeiro);
    $dtFim = new DateTime($ultimo);
    // FIX: normaliza para meia-noite
    $dt->setTime(0, 0, 0);
    $dtFim->setTime(0, 0, 0);

    while ($dt->format('Y-m-d') <= $dtFim->format('Y-m-d')) {
        if ($dt->format('w') != 0)
            $total++;
        $dt->modify('+1 day');
    }
    return $total;
}

function getProximoDiaLivre($conn, $did, $start_date = null)
{
    if (!$start_date)
        $start_date = date('Y-m-d');

    $it = new DateTime($start_date);
    $limit = clone $it;
    $limit->modify('+90 days');
    // FIX: normaliza para meia-noite
    $it->setTime(0, 0, 0);
    $limit->setTime(0, 0, 0);

    $res = mysqli_query($conn, "
        SELECT a.dia_semana, a.data AS individual_date, t.data_inicio, t.data_fim
        FROM agenda a
        LEFT JOIN turma t ON a.turma_id = t.id
        WHERE a.docente_id = $did
    ");
    $agendas = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $agendas[] = $row;
    }

    $daysMap = [0 => 'Domingo', 1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado'];

    while ($it->format('Y-m-d') <= $limit->format('Y-m-d')) {
        $w = (int) $it->format('w');
        if ($w == 0) {
            $it->modify('+1 day');
            continue;
        }

        $dia_str = $daysMap[$w];
        $current_date = $it->format('Y-m-d');
        $ocupado = false;

        foreach ($agendas as $ag) {
            if ($ag['individual_date'] === $current_date) {
                $ocupado = true;
                break;
            }
            if ($ag['dia_semana'] === $dia_str && $ag['data_inicio'] && $ag['data_fim']) {
                if ($current_date >= $ag['data_inicio'] && $current_date <= $ag['data_fim']) {
                    $ocupado = true;
                    break;
                }
            }
        }

        if (!$ocupado) {
            return $it->format('Y-m-d');
        }
        $it->modify('+1 day');
    }
    return 'N/A';
}

function getDailyStatusForMonth($conn, $did, $month)
{
    require_once __DIR__ . '/../models/AgendaModel.php';
    $agendaModel = new AgendaModel($conn);

    $primeiro = "$month-01";
    $ultimo = date('Y-m-t', strtotime($primeiro));

    $res_count = mysqli_query($conn, "SELECT COUNT(*) as total FROM horario_trabalho WHERE docente_id = $did");
    $count_ht = mysqli_fetch_assoc($res_count)['total'] ?? 0;
    $has_no_work_schedule = ($count_ht == 0);

    $expanded = $agendaModel->getExpandedAgenda([$did], $primeiro, $ultimo);

    $daily_status = [];
    $num_days = date('t', strtotime($primeiro));

    for ($i = 1; $i <= $num_days; $i++) {
        $date_key = $month . '-' . str_pad($i, 2, '0', STR_PAD_LEFT);
        $w = (int) date('w', strtotime($date_key));
        $is_weekend = ($w == 0 || $w == 6);

        $default_st = $has_no_work_schedule ? 'OFF_SCHEDULE' : 'Livre';

        $daily_status[$date_key] = [
            'Manhã' => $default_st,
            'Tarde' => $default_st,
            'Noite' => $default_st,
            'Integral' => $default_st,
            'is_weekend' => $is_weekend,
            'is_sunday' => ($w == 0)
        ];
    }

    // FIX: coleta work_schedules para poder aplicar OFF_SCHEDULE no mensal
    // (esse pós-processamento existia no getDailyStatusForYear mas estava
    //  ausente aqui, fazendo dias fora do horário aparecerem como "Livre")
    $work_schedules_found = [];

    foreach ($expanded as $item) {
        $ds = $item['agenda_data'];
        if (!isset($daily_status[$ds]))
            continue;

        $type = $item['type'];

        if ($type === 'FERIADO' || $type === 'FERIAS')
            continue;

        // FIX: captura work_schedule igual ao getDailyStatusForYear
        if ($type === 'WORK_SCHEDULE') {
            $work_schedules_found[$ds][$item['periodo']] = true;
            continue;
        }

        $mapped_status = 'Ocupado';
        if ($type === 'RESERVA' || $type === 'RESERVA_LEGADO' || ($item['status'] ?? '') === 'RESERVADO') {
            $mapped_status = 'Reservado';
        }

        $periodo = $item['periodo'] ?? '';
        $hi = $item['horario_inicio'];
        $hf = $item['horario_fim'];

        $periodos_to_set = [];

        if ($periodo === 'Integral') {
            $periodos_to_set = ['Manhã', 'Tarde', 'Noite'];
        } elseif ($periodo === 'Manhã' || $periodo === 'Tarde' || $periodo === 'Noite') {
            $periodos_to_set = [$periodo];
        } else {
            if (!$hi || !$hf || ($hi == '00:00:00' && $hf == '23:59:59')) {
                $periodos_to_set = ['Manhã', 'Tarde', 'Noite'];
            } else {
                if ($hi < '12:00:00')
                    $periodos_to_set[] = 'Manhã';
                if ($hi < '18:00:00' && $hf > '12:00:00')
                    $periodos_to_set[] = 'Tarde';
                if ($hf > '18:00:00' || $hi >= '18:00:00')
                    $periodos_to_set[] = 'Noite';
            }
        }

        foreach ($periodos_to_set as $pts) {
            $daily_status[$ds][$pts] = $mapped_status;
        }
    }

    // FIX: aplica OFF_SCHEDULE no mensal (igual ao getDailyStatusForYear)
    if (!$has_no_work_schedule && !empty($work_schedules_found)) {
        foreach ($daily_status as $date_key => &$d_data) {
            if ($d_data['is_sunday'])
                continue;
            foreach (['Manhã', 'Tarde', 'Noite'] as $p) {
                if ($d_data[$p] === 'Livre') {
                    if (!isset($work_schedules_found[$date_key][$p]) && !isset($work_schedules_found[$date_key]['Integral'])) {
                        $d_data[$p] = 'OFF_SCHEDULE';
                    }
                }
            }
        }
        unset($d_data);
    }

    return $daily_status;
}

function countFreeDays($daily_status)
{
    $free_count = 0;
    foreach ($daily_status as $day => $data) {
        if ($data['is_sunday'])
            continue;
        $has_busy = (
            $data['Manhã'] === 'Ocupado' || $data['Manhã'] === 'Reservado' ||
            $data['Tarde'] === 'Ocupado' || $data['Tarde'] === 'Reservado' ||
            $data['Noite'] === 'Ocupado' || $data['Noite'] === 'Reservado' ||
            $data['Integral'] === 'Ocupado' || $data['Integral'] === 'Reservado'
        );
        if (!$has_busy) {
            $free_count++;
        }
    }
    return $free_count;
}

function getDailyStatusForYear($conn, $did, $year)
{
    require_once __DIR__ . '/../models/AgendaModel.php';
    $agendaModel = new AgendaModel($conn);

    $primeiro_ano = "$year-01-01";
    $ultimo_ano = "$year-12-31";

    $res_count = mysqli_query($conn, "SELECT COUNT(*) as total FROM horario_trabalho WHERE docente_id = $did");
    $count_ht = mysqli_fetch_assoc($res_count)['total'] ?? 0;
    $has_no_work_schedule = ($count_ht == 0);

    $expanded = $agendaModel->getExpandedAgenda([$did], $primeiro_ano, $ultimo_ano);
    $annual_status = [];
    $work_schedules_found = [];

    for ($m = 1; $m <= 12; $m++) {
        $mes_str = str_pad($m, 2, '0', STR_PAD_LEFT);
        $days_in_month = date('t', strtotime("$year-$mes_str-01"));
        for ($i = 1; $i <= $days_in_month; $i++) {
            $date_key = "$year-$mes_str-" . str_pad($i, 2, '0', STR_PAD_LEFT);
            $w = (int) date('w', strtotime($date_key));
            $default_st = $has_no_work_schedule ? 'OFF_SCHEDULE' : 'Livre';

            $annual_status[$date_key] = [
                'Manhã' => $default_st,
                'Tarde' => $default_st,
                'Noite' => $default_st,
                'Integral' => $default_st,
                'is_weekend' => ($w == 0 || $w == 6),
                'is_sunday' => ($w == 0),
                'holiday' => null,
                'vacation' => null
            ];
        }
    }

    foreach ($expanded as $item) {
        $ds = $item['agenda_data'];
        if (!isset($annual_status[$ds]))
            continue;

        $type = $item['type'];
        $mapped_status = 'Ocupado';

        if ($type === 'FERIADO') {
            $annual_status[$ds]['holiday'] = str_replace('FERIADO: ', '', $item['curso_nome']);
            continue;
        }
        if ($type === 'FERIAS') {
            $annual_status[$ds]['vacation'] = $item['curso_nome'];
            continue;
        }
        if ($type === 'WORK_SCHEDULE') {
            $work_schedules_found[$ds][$item['periodo']] = true;
            continue;
        }

        if ($type === 'RESERVA' || $type === 'RESERVA_LEGADO' || $item['status'] === 'RESERVADO') {
            $mapped_status = 'Reservado';
        }

        $periodo = $item['periodo'] ?? '';
        $hi = $item['horario_inicio'];
        $hf = $item['horario_fim'];

        $periodos_to_set = [];

        if ($periodo === 'Integral') {
            $periodos_to_set = ['Manhã', 'Tarde', 'Noite'];
        } elseif ($periodo === 'Manhã' || $periodo === 'Tarde' || $periodo === 'Noite') {
            $periodos_to_set = [$periodo];
        } else {
            if (!$hi || !$hf || ($hi == '00:00:00' && $hf == '23:59:59')) {
                $periodos_to_set = ['Manhã', 'Tarde', 'Noite'];
            } else {
                if ($hi < '12:00:00')
                    $periodos_to_set[] = 'Manhã';
                if ($hi < '18:00:00' && $hf > '12:00:00')
                    $periodos_to_set[] = 'Tarde';
                if ($hf > '18:00:00' || $hi >= '18:00:00')
                    $periodos_to_set[] = 'Noite';
            }
        }

        foreach ($periodos_to_set as $pts) {
            $annual_status[$ds][$pts] = $mapped_status;
        }
    }

    if (!empty($work_schedules_found)) {
        foreach ($annual_status as $date_key => &$d_data) {
            if ($d_data['is_sunday'])
                continue;
            foreach (['Manhã', 'Tarde', 'Noite'] as $p) {
                if ($d_data[$p] === 'Livre') {
                    if (!isset($work_schedules_found[$date_key][$p]) && !isset($work_schedules_found[$date_key]['Integral'])) {
                        $d_data[$p] = 'OFF_SCHEDULE';
                    }
                }
            }
        }
        unset($d_data);
    }

    return $annual_status;
}

// ============================================================
// FERIADOS, FÉRIAS E LIMITES
// ============================================================

function isHoliday($conn, $date)
{
    $date_esc = mysqli_real_escape_string($conn, $date);
    $res = mysqli_query($conn, "SELECT id, name FROM holidays WHERE '$date_esc' BETWEEN date AND end_date LIMIT 1");
    return mysqli_fetch_assoc($res);
}

function isVacation($conn, $did, $date)
{
    if (!$did)
        $did = 0;
    $date_esc = mysqli_real_escape_string($conn, $date);
    $res = mysqli_query($conn, "
        SELECT id, type, teacher_id 
        FROM vacations 
        WHERE '$date_esc' BETWEEN start_date AND end_date
          AND (type = 'collective' OR (type = 'individual' AND teacher_id = $did))
        LIMIT 1
    ");
    return mysqli_fetch_assoc($res);
}

function calculateConsumedHours($conn, $did, $start_date, $end_date)
{
    if (!$did)
        return 0;

    $total_hours = 0;
    $daysMap = [0 => 'Domingo', 1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado'];

    // 1. Aulas Confirmadas
    $stmt = $conn->prepare("SELECT a.horario_inicio, a.horario_fim, a.data, a.dia_semana, t.data_inicio, t.data_fim 
                            FROM agenda a 
                            LEFT JOIN turma t ON a.turma_id = t.id
                            WHERE a.docente_id = ? 
                            AND (
                                (a.data IS NOT NULL AND a.data BETWEEN ? AND ?)
                                OR (a.data IS NULL AND t.data_inicio <= ? AND t.data_fim >= ?)
                            )");
    $stmt->bind_param("issss", $did, $start_date, $end_date, $end_date, $start_date);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $h = (strtotime($row['horario_fim']) - strtotime($row['horario_inicio'])) / 3600;

        if ($row['data']) {
            if (!isHoliday($conn, $row['data']) && !isVacation($conn, $did, $row['data'])) {
                $total_hours += $h;
            }
        } else {
            $it = new DateTime(max($start_date, $row['data_inicio']));
            $itFim = new DateTime(min($end_date, $row['data_fim']));
            // FIX: normaliza para meia-noite
            $it->setTime(0, 0, 0);
            $itFim->setTime(0, 0, 0);

            while ($it->format('Y-m-d') <= $itFim->format('Y-m-d')) {
                if ($daysMap[(int) $it->format('w')] === $row['dia_semana']) {
                    if (!isHoliday($conn, $it->format('Y-m-d')) && !isVacation($conn, $did, $it->format('Y-m-d'))) {
                        $total_hours += $h;
                    }
                }
                $it->modify('+1 day');
            }
        }
    }
    $stmt->close();

    // 2. Reservas
    $stmt = $conn->prepare("SELECT data_inicio, data_fim, hora_inicio, hora_fim, dias_semana 
                            FROM reservas WHERE docente_id = ? AND status IN ('PENDENTE', 'APROVADA', 'ativo', 'CONCLUIDA')
                            AND data_inicio <= ? AND data_fim >= ?");
    $stmt->bind_param("iss", $did, $end_date, $start_date);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $h = (strtotime($row['hora_fim']) - strtotime($row['hora_inicio'])) / 3600;
        $d_list = array_map('trim', explode(',', $row['dias_semana']));

        $it = new DateTime(max($start_date, $row['data_inicio']));
        $itFim = new DateTime(min($end_date, $row['data_fim']));
        // FIX: normaliza para meia-noite
        $it->setTime(0, 0, 0);
        $itFim->setTime(0, 0, 0);

        while ($it->format('Y-m-d') <= $itFim->format('Y-m-d')) {
            if (in_array($daysMap[(int) $it->format('w')], $d_list)) {
                if (!isHoliday($conn, $it->format('Y-m-d')) && !isVacation($conn, $did, $it->format('Y-m-d'))) {
                    $total_hours += $h;
                }
            }
            $it->modify('+1 day');
        }
    }
    $stmt->close();

    // 3. Preparação / Atestados
    $stmt = $conn->prepare("SELECT horario_inicio, horario_fim, data_inicio, data_fim, dias_semana, tipo 
                            FROM preparacao_atestados WHERE docente_id = ? AND status = 'ativo'
                            AND data_inicio <= ? AND data_fim >= ?");
    $stmt->bind_param("iss", $did, $end_date, $start_date);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        if ($row['horario_inicio'] && $row['horario_fim']) {
            $h = (strtotime($row['horario_fim']) - strtotime($row['horario_inicio'])) / 3600;
            $d_perm = !empty($row['dias_semana']) ? explode(',', $row['dias_semana']) : [];

            $it = new DateTime(max($start_date, $row['data_inicio']));
            $itFim = new DateTime(min($end_date, $row['data_fim']));
            // FIX: normaliza para meia-noite
            $it->setTime(0, 0, 0);
            $itFim->setTime(0, 0, 0);

            while ($it->format('Y-m-d') <= $itFim->format('Y-m-d')) {
                $dow = (int) $it->format('N');
                if ($row['tipo'] !== 'preparação' || empty($d_perm) || in_array($dow, $d_perm)) {
                    if (!isHoliday($conn, $it->format('Y-m-d')) && !isVacation($conn, $did, $it->format('Y-m-d'))) {
                        $total_hours += $h;
                    }
                }
                $it->modify('+1 day');
            }
        }
    }
    $stmt->close();

    return (float) $total_hours;
}

function isWithinWorkSchedule($conn, $did, $date, $periodo)
{
    if (!$did)
        return true;

    $check_periods = [$periodo];
    if ($periodo === 'Integral') {
        $check_periods = ['Manhã', 'Tarde'];
    }

    $res_count = mysqli_query($conn, "SELECT COUNT(*) as total FROM horario_trabalho WHERE docente_id = $did");
    $count = mysqli_fetch_assoc($res_count)['total'];

    if ($count == 0)
        return true;

    $daysMap = [0 => 'Domingo', 1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado'];
    $w = (int) date('w', strtotime($date));
    $nome_dia = $daysMap[$w];

    foreach ($check_periods as $p) {
        $p_esc = mysqli_real_escape_string($conn, $p);
        $dia_esc = mysqli_real_escape_string($conn, $nome_dia);

        $q = "SELECT id FROM horario_trabalho 
              WHERE docente_id = $did 
              AND periodo = '$p_esc' 
              AND (dias = '$dia_esc' OR FIND_IN_SET('$dia_esc', dias) > 0 OR dias LIKE '%$dia_esc%')";
        $res = mysqli_query($conn, $q);
        if (!$res || mysqli_num_rows($res) == 0) {
            return false;
        }
    }

    return true;
}

function checkDocenteWorkSchedule($conn, $did, $data_start, $data_end, $days_arr, $periodo, $h_start, $h_end)
{
    if (!$did || $did <= 0)
        return true;

    $res_n = mysqli_query($conn, "SELECT nome FROM docente WHERE id = $did");
    $doc_name = ($row = mysqli_fetch_assoc($res_n)) ? $row['nome'] : "Docente #$did";

    foreach ($days_arr as $dia_nome) {
        $periods_to_check = [$periodo];
        if ($periodo === 'Integral')
            $periods_to_check = ['Manhã', 'Tarde'];

        foreach ($periods_to_check as $pts) {
            $pts_esc = mysqli_real_escape_string($conn, $pts);
            $dia_esc = mysqli_real_escape_string($conn, $dia_nome);

            $q = "SELECT id FROM horario_trabalho 
                  WHERE docente_id = $did 
                  AND periodo = '$pts_esc' 
                  AND (dias = '$dia_esc' OR FIND_IN_SET('$dia_esc', dias) > 0 OR dias LIKE '%$dia_esc%')";
            $res = mysqli_query($conn, $q);
            if (!$res || mysqli_num_rows($res) == 0) {
                return "Bloqueio: O docente $doc_name não possui autorização de trabalho (horario_trabalho) cadastrada para o período $pts na $dia_nome.";
            }
        }
    }

    return true;
}

function checkAmbienteConflict($conn, $ambiente_id, $turma_id_to_ignore, $data_start, $data_end, $days_arr, $h_start, $h_end)
{
    if (!$ambiente_id)
        return true;

    $amb_esc = mysqli_real_escape_string($conn, $ambiente_id);

    $res_n = mysqli_query($conn, "SELECT nome FROM ambiente WHERE id = '$amb_esc' LIMIT 1");
    if ($row_n = mysqli_fetch_assoc($res_n)) {
        $nome_amb = mb_strtolower(trim($row_n['nome']), 'UTF-8');
        if ($nome_amb === 'a definir') {
            return true;
        }
    }

    $ignore_stmt = $turma_id_to_ignore ? "AND a.turma_id != $turma_id_to_ignore" : "";

    $q = "SELECT a.data, a.horario_inicio, a.horario_fim, t.sigla as turma_nome, c.nome as curso_nome
          FROM agenda a
          JOIN turma t ON a.turma_id = t.id
          JOIN curso c ON t.curso_id = c.id
          WHERE a.ambiente_id = '$amb_esc'
          AND a.data BETWEEN '$data_start' AND '$data_end'
          $ignore_stmt
          AND (a.horario_inicio < '$h_end' AND a.horario_fim > '$h_start')";

    $res = mysqli_query($conn, $q);
    $daysMap = [0 => 'Domingo', 1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado'];

    while ($row = mysqli_fetch_assoc($res)) {
        $dt = $row['data'];
        $dow = (int) date('w', strtotime($dt));
        $dia_nome = $daysMap[$dow];

        if (in_array($dia_nome, $days_arr)) {
            $data_f = date('d/m/Y', strtotime($dt));
            return "Ambiente Indisponível: A sala selecionada já está ocupada pela turma {$row['turma_nome']} ({$row['curso_nome']}) em $data_f ($dia_nome) das {$row['horario_inicio']} às {$row['horario_fim']}.";
        }
    }

    return true;
}
/**
 * Calcula a data de fim de uma turma pulando feriados e férias,
 * baseando-se na carga horária total e horas por dia.
 */
function calculateEndDate($conn, $data_inicio, $ch_total, $horas_por_dia, $dias_semana_arr, $docentes_ids = [])
{
    if (!$ch_total || !$horas_por_dia || empty($dias_semana_arr))
        return $data_inicio;

    $total_days_needed = ceil($ch_total / $horas_por_dia);
    $it = new DateTime($data_inicio);
    $it->setTime(0, 0, 0);

    $count = 0;
    $safety = 0;

    $daysMap = [0 => 'Domingo', 1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado'];

    while ($count < $total_days_needed && $safety < 2000) {
        $safety++;
        $dow = (int) $it->format('w');
        $dayName = $daysMap[$dow] ?? '';

        if (in_array($dayName, $dias_semana_arr)) {
            // Verifica feriado global
            if (!isHoliday($conn, $it->format('Y-m-d'))) {
                // Verifica se TODOS os docentes estão de férias simultaneamente
                $isBlocked = false;
                if (!empty($docentes_ids)) {
                    $vacation_count = 0;
                    foreach ($docentes_ids as $did) {
                        if (isVacation($conn, $did, $it->format('Y-m-d'))) {
                            $vacation_count++;
                        }
                    }
                    if ($vacation_count > 0 && $vacation_count === count($docentes_ids)) {
                        $isBlocked = true;
                    }
                }

                if (!$isBlocked) {
                    $count++;
                }
            }
        }

        if ($count >= $total_days_needed)
            break;
        $it->modify('+1 day');
    }

    return $it->format('Y-m-d');
}

/**
 * Gera registros na tabela agenda para uma turma entre data_inicio e data_fim.
 * Pula feriados e férias de docentes.
 */
function generateAgendaRecords($conn, $turma_id, $dias_arr, $periodo, $h_inicio, $h_fim, $data_inicio, $data_fim, $ambiente_id, $docentes_ids)
{
    $daysMap = [
        0 => 'Domingo',
        1 => 'Segunda-feira',
        2 => 'Terça-feira',
        3 => 'Quarta-feira',
        4 => 'Quinta-feira',
        5 => 'Sexta-feira',
        6 => 'Sábado'
    ];

    $amb_sql = (!empty($ambiente_id) && intval($ambiente_id) > 0) ? intval($ambiente_id) : 'NULL';

    $it = new DateTime($data_inicio);
    $end = new DateTime($data_fim);
    $it->setTime(0, 0, 0);
    $end->setTime(0, 0, 0);

    while ($it->format('Y-m-d') <= $end->format('Y-m-d')) {
        $w = (int) $it->format('w');
        $dayName = $daysMap[$dow = $w] ?? '';

        if (in_array($dayName, $dias_arr)) {
            $dateStr = $it->format('Y-m-d');

            if (isHoliday($conn, $dateStr)) {
                $it->modify('+1 day');
                continue;
            }

            $dia_esc = mysqli_real_escape_string($conn, $dayName);
            $periodo_esc = mysqli_real_escape_string($conn, $periodo);

            if (!empty($docentes_ids)) {
                foreach ($docentes_ids as $doc_id) {
                    $doc_val = (int) $doc_id;
                    if (isVacation($conn, $doc_val, $dateStr)) {
                        continue;
                    }
                    mysqli_query($conn, "INSERT IGNORE INTO agenda (turma_id, docente_id, ambiente_id, dia_semana, periodo, horario_inicio, horario_fim, data, status)
                                         VALUES ('$turma_id', $doc_val, $amb_sql, '$dia_esc', '$periodo_esc', '$h_inicio', '$h_fim', '$dateStr', 'CONFIRMADO')");
                }
            } else {
                mysqli_query($conn, "INSERT IGNORE INTO agenda (turma_id, docente_id, ambiente_id, dia_semana, periodo, horario_inicio, horario_fim, data, status)
                                     VALUES ('$turma_id', NULL, $amb_sql, '$dia_esc', '$periodo_esc', '$h_inicio', '$h_fim', '$dateStr', 'CONFIRMADO')");
            }
        }
        $it->modify('+1 day');
    }
}