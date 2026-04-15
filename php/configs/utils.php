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
          AND ( (type = 'collective' AND teacher_id IS NULL) OR teacher_id = $did )
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
    $stmt = $conn->prepare("SELECT a.horario_inicio, a.horario_fim, a.data, a.dia_semana, a.periodo, t.data_inicio, t.data_fim 
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
        if (($row['periodo'] ?? '') === 'Integral' && $h > 4) $h -= 1;

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
    $stmt = $conn->prepare("SELECT data_inicio, data_fim, hora_inicio, hora_fim, dias_semana, periodo 
                            FROM reservas WHERE docente_id = ? AND status IN ('PENDENTE', 'APROVADA', 'ativo', 'CONCLUIDA')
                            AND data_inicio <= ? AND data_fim >= ?");
    $stmt->bind_param("iss", $did, $end_date, $start_date);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $h = (strtotime($row['hora_fim']) - strtotime($row['hora_inicio'])) / 3600;
        if (($row['periodo'] ?? '') === 'Integral' && $h > 4) $h -= 1;
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
    $date_esc = mysqli_real_escape_string($conn, $date);

    foreach ($check_periods as $p) {
        $p_esc   = mysqli_real_escape_string($conn, $p);
        $dia_esc = mysqli_real_escape_string($conn, $nome_dia);

        // Verifica se existe um bloco ativo para a data que autoriza esse dia/período
        $q = "SELECT id FROM horario_trabalho
              WHERE docente_id = $did
              AND periodo = '$p_esc'
              AND (dias = '$dia_esc' OR FIND_IN_SET('$dia_esc', dias) > 0 OR dias LIKE '%$dia_esc%')
              AND (
                  -- Bloco legado (sem datas) = sempre válido
                  (data_inicio IS NULL AND data_fim IS NULL)
                  OR
                  -- Bloco sazonal: a data deve estar dentro do intervalo
                  ('$date_esc' BETWEEN data_inicio AND data_fim)
              )";
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

            // Precisamos verificar para CADA DATA dentro do intervalo da turma que cai nesse dia.
            // Para performance, verificamos apenas as datas extremas do intervalo.
            // Se a turma cruza múltiplos blocos, cada data deve estar coberta.
            $dates_to_check = [$data_start, $data_end];
            // Adiciona o 1º dia do bloco intermediário para turmas longas (simples)
            $mid_ts = (strtotime($data_start) + strtotime($data_end)) / 2;
            $dates_to_check[] = date('Y-m-d', (int)$mid_ts);

            foreach ($dates_to_check as $check_date) {
                $dow = (int) date('w', strtotime($check_date));
                $check_day_name = [0=>'Domingo',1=>'Segunda-feira',2=>'Terça-feira',3=>'Quarta-feira',4=>'Quinta-feira',5=>'Sexta-feira',6=>'Sábado'][$dow];
                if (mb_strtolower($check_day_name, 'UTF-8') !== mb_strtolower($dia_nome, 'UTF-8'))
                    continue;

                $check_esc = mysqli_real_escape_string($conn, $check_date);
                $q = "SELECT id FROM horario_trabalho
                      WHERE docente_id = $did
                      AND periodo = '$pts_esc'
                      AND (dias = '$dia_esc' OR FIND_IN_SET('$dia_esc', dias) > 0 OR dias LIKE '%$dia_esc%')
                      AND (
                          (data_inicio IS NULL AND data_fim IS NULL)
                          OR ('$check_esc' BETWEEN data_inicio AND data_fim)
                      )";
                $res = mysqli_query($conn, $q);
                if (!$res || mysqli_num_rows($res) == 0) {
                    return "Bloqueio: O docente $doc_name não possui autorização de trabalho (bloco de horário) para o período $pts na $dia_nome em " . date('d/m/Y', strtotime($check_date)) . ".";
                }
                break; // basta verificar a primeira data válida do dia
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

/**
 * Validates teacher limits against proposed turma schedule.
 *
 * FIX: Todas as comparações de DateTime agora usam format('Y-m-d') para evitar
 * que o horário 00:00:00 faça o último dia ser cortado nas interseções de
 * semana/mês.
 */
function checkDocenteLimits($conn, $docente_id, $turma_id_to_ignore, $data_start, $data_end, $days_arr, $h_start, $h_end, $periodo = '')
{
    if (!$docente_id || $docente_id <= 0)
        return true;

    // 1. Get Teacher Info and Limits
    $res = mysqli_query($conn, "SELECT nome, weekly_hours_limit, monthly_hours_limit FROM docente WHERE id = $docente_id");
    $doc = mysqli_fetch_assoc($res);
    if (!$doc)
        return true;

    $limit_w = (float) $doc['weekly_hours_limit'];
    $limit_m = (float) $doc['monthly_hours_limit'];
    $name = $doc['nome'];

    if ($limit_w <= 0 && $limit_m <= 0) {
        return "O docente $name possui carga horária zerada e não pode ser vinculado a turmas.";
    }

    // 2. Calculate New Class Duration (in hours)
    $t1 = strtotime($h_start);
    $t2 = strtotime($h_end);
    $hours_per_class = ($t2 - $t1) / 3600;
    
    // Regra Integral: Subtrai 1h de almoço se for Integral e duração > 4h
    if ($periodo === 'Integral' && $hours_per_class > 4) {
        $hours_per_class -= 1;
    }

    if ($hours_per_class <= 0)
        return true;

    $it = new DateTime($data_start);
    $end = new DateTime($data_end);

    // FIX: normaliza para meia-noite
    $it->setTime(0, 0, 0);
    $end->setTime(0, 0, 0);

    // 3. Check Weekly Limit
    if ($limit_w > 0) {
        $weeks_checked = [];
        $temp_it = clone $it;

        while ($temp_it->format('Y-m-d') <= $end->format('Y-m-d')) {
            $yw = $temp_it->format('oW');

            if (!in_array($yw, $weeks_checked)) {
                $classes_this_week = 0;

                $week_start = clone $temp_it;
                $week_start->modify('Monday this week');
                $week_start->setTime(0, 0, 0);

                $week_end = clone $week_start;
                $week_end->modify('+6 days');
                $week_end->setTime(0, 0, 0);

                // FIX: comparação por string de data, sem interferência de horário
                $r_start = ($week_start->format('Y-m-d') > $it->format('Y-m-d')) ? $week_start : $it;
                $r_end = ($week_end->format('Y-m-d') < $end->format('Y-m-d')) ? $week_end : $end;

                $check_day = clone $r_start;
                while ($check_day->format('Y-m-d') <= $r_end->format('Y-m-d')) {
                    $curr_v = $check_day->format('Y-m-d');
                    if (in_array(getDayNameString($check_day), $days_arr)) {
                        if (!isHoliday($conn, $curr_v) && !isVacation($conn, $docente_id, $curr_v)) {
                            $classes_this_week++;
                        }
                    }
                    $check_day->modify('+1 day');
                }

                $new_hours_w = $classes_this_week * $hours_per_class;

                $q = "SELECT SUM(
                        (TIMESTAMPDIFF(SECOND, horario_inicio, horario_fim)/3600) 
                        - IF(periodo = 'Integral' AND TIMESTAMPDIFF(SECOND, horario_inicio, horario_fim) > 14400, 1, 0)
                      ) as total 
                      FROM agenda 
                      WHERE docente_id = $docente_id 
                      AND YEARWEEK(data, 1) = $yw";
                if ($turma_id_to_ignore)
                    $q .= " AND turma_id != $turma_id_to_ignore";

                $row = mysqli_fetch_assoc(mysqli_query($conn, $q));
                $current_w = (float) ($row['total'] ?? 0);

                if (($current_w + $new_hours_w) > ($limit_w + 0.01)) {
                    return "O docente $name excedeu o limite semanal ($limit_w h). Total planejado: " . round($current_w + $new_hours_w, 1) . "h na semana de " . $r_start->format('d/m') . ".";
                }
                $weeks_checked[] = $yw;
            }
            $temp_it->modify('+1 day');
        }
    }

    // 4. Check Monthly Limit
    if ($limit_m > 0) {
        $months_checked = [];
        $temp_it = clone $it;

        while ($temp_it->format('Y-m-d') <= $end->format('Y-m-d')) {
            $month = $temp_it->format('Y-m');

            if (!in_array($month, $months_checked)) {
                $month_val = $temp_it->format('Ym');

                $m_start = new DateTime($temp_it->format('Y-m-01'));
                $m_start->setTime(0, 0, 0);

                $m_end = new DateTime($temp_it->format('Y-m-t'));
                $m_end->setTime(0, 0, 0);

                // FIX: comparação por string de data
                $r_start = ($m_start->format('Y-m-d') > $it->format('Y-m-d')) ? $m_start : $it;
                $r_end = ($m_end->format('Y-m-d') < $end->format('Y-m-d')) ? $m_end : $end;

                $classes_this_month = 0;
                $check_day = clone $r_start;

                while ($check_day->format('Y-m-d') <= $r_end->format('Y-m-d')) {
                    $curr_v = $check_day->format('Y-m-d');
                    if (in_array(getDayNameString($check_day), $days_arr)) {
                        if (!isHoliday($conn, $curr_v) && !isVacation($conn, $docente_id, $curr_v)) {
                            $classes_this_month++;
                        }
                    }
                    $check_day->modify('+1 day');
                }

                $new_hours_m = $classes_this_month * $hours_per_class;

                $q = "SELECT SUM(
                        (TIMESTAMPDIFF(SECOND, horario_inicio, horario_fim)/3600)
                        - IF(periodo = 'Integral' AND TIMESTAMPDIFF(SECOND, horario_inicio, horario_fim) > 14400, 1, 0)
                      ) as total 
                      FROM agenda 
                      WHERE docente_id = $docente_id 
                      AND DATE_FORMAT(data, '%Y%m') = '$month_val'";
                if ($turma_id_to_ignore)
                    $q .= " AND turma_id != $turma_id_to_ignore";

                $row = mysqli_fetch_assoc(mysqli_query($conn, $q));
                $current_m = (float) ($row['total'] ?? 0);

                if (($current_m + $new_hours_m) > ($limit_m + 0.01)) {
                    return "O docente $name excedeu o limite mensal ($limit_m h). Total planejado: " . round($current_m + $new_hours_m, 1) . "h em " . $temp_it->format('m/Y') . ".";
                }
                $months_checked[] = $month;
            }
            $temp_it->modify('+1 day');
        }
    }

    return true;
}

function getDayNameString($dateTime)
{
    $map = [0 => 'Domingo', 1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado'];
    return $map[(int) $dateTime->format('w')];
}

/**
 * Checks for schedule overlaps in agenda and reserves.
 * (Sem alterações de lógica — o BETWEEN do SQL já é inclusivo nos dois extremos.)
 */
function checkDocenteConflicts($conn, $docente_id, $turma_id_to_ignore, $data_start, $data_end, $days_arr, $h_start, $h_end)
{
    if (!$docente_id || $docente_id <= 0)
        return true;

    $res_doc = mysqli_query($conn, "SELECT nome FROM docente WHERE id = $docente_id");
    $doc_name = ($row_doc = mysqli_fetch_assoc($res_doc)) ? $row_doc['nome'] : "Docente #$docente_id";

    // 1. Check existing agenda items (Aulas)
    $q_agenda = "SELECT a.data, a.horario_inicio, a.horario_fim, t.sigla 
                 FROM agenda a 
                 JOIN turma t ON a.turma_id = t.id
                 WHERE a.docente_id = $docente_id 
                 AND a.data BETWEEN '$data_start' AND '$data_end'";
    if ($turma_id_to_ignore)
        $q_agenda .= " AND a.turma_id != $turma_id_to_ignore";

    $res_agenda = mysqli_query($conn, $q_agenda);
    while ($row = mysqli_fetch_assoc($res_agenda)) {
        $day_name = getDayNameString(new DateTime($row['data']));
        if (in_array($day_name, $days_arr)) {
            if ($h_start < $row['horario_fim'] && $h_end > $row['horario_inicio']) {
                $data_f = date('d/m/Y', strtotime($row['data']));
                return "O docente $doc_name não estará disponível em $data_f ($day_name) pois já possui aula na turma {$row['sigla']} das {$row['horario_inicio']} às {$row['horario_fim']}.";
            }
        }
    }

    // 2. Check reserves
    $q_reserva = "SELECT r.data_inicio, r.data_fim, r.hora_inicio, r.hora_fim, r.dias_semana, r.sigla
                  FROM reservas r
                  WHERE r.docente_id = $docente_id
                  AND r.status IN ('PENDENTE', 'APROVADA')
                  AND r.data_inicio <= '$data_end'
                  AND r.data_fim >= '$data_start'";

    $res_reserva = mysqli_query($conn, $q_reserva);
    while ($row = mysqli_fetch_assoc($res_reserva)) {
        $res_days = explode(',', $row['dias_semana']);
        foreach ($days_arr as $d) {
            if (in_array($d, $res_days)) {
                if ($h_start < $row['hora_fim'] && $h_end > $row['hora_inicio']) {
                    return "O docente $doc_name não estará disponível pois possui uma reserva ({$row['sigla']}) que sobrepõe este horário nos dias de $d.";
                }
            }
        }
    }

    return true;
}

/**
 * Calcula a carga horária de um docente em um determinado intervalo de datas,
 * baseando-se nos registros de horario_trabalho.
 * Útil para calcular o total anual e o saldo remanescente.
 */
function calculateTeacherYearlyWorkload($conn, $docente_id, $data_inicio, $data_fim) {
    if (!$docente_id) return 0;

    // 0. AJUSTE DE ADMISSÃO PROPORCIONAL: 
    // Se o docente começou no meio do ano, o potencial deve contar a partir do primeiro bloco de horário.
    $q_first_block = mysqli_query($conn, "SELECT MIN(data_inicio) as first_d FROM horario_trabalho WHERE docente_id = $docente_id");
    $first_d = mysqli_fetch_assoc($q_first_block)['first_d'] ?? $data_inicio;
    if ($first_d > $data_inicio) {
        $data_inicio = $first_d;
    }

    // Cache de Feriados para o intervalo
    static $feriados_cache = null;
    if ($feriados_cache === null) {
        $feriados_cache = [];
        $res_f = mysqli_query($conn, "SELECT date as data FROM holidays WHERE (end_date IS NULL AND date BETWEEN '$data_inicio' AND '$data_fim') OR (end_date IS NOT NULL AND date <= '$data_fim' AND end_date >= '$data_inicio')");
        if ($res_f) {
            while ($f = mysqli_fetch_assoc($res_f)) {
                $feriados_cache[$f['data']] = true;
                // Se for intervalo, preenche os dias intermediários
                if (isset($f['end_date']) && $f['end_date'] > $f['data']) {
                    $curr_it = new DateTime($f['data']);
                    $end_it = new DateTime($f['end_date']);
                    while ($curr_it <= $end_it) {
                        $feriados_cache[$curr_it->format('Y-m-d')] = true;
                        $curr_it->modify('+1 day');
                    }
                }
            }
        }
    }

    // Cache de Férias do Docente para o intervalo
    $ferias_intervalos = [];
    $res_v = mysqli_query($conn, "
        SELECT start_date, end_date 
        FROM vacations 
        WHERE (teacher_id = $docente_id OR teacher_id IS NULL) 
          AND (start_date <= '$data_fim' AND end_date >= '$data_inicio')
    ");
    if ($res_v) {
        while ($v = mysqli_fetch_assoc($res_v)) {
            $ferias_intervalos[] = ['ini' => $v['start_date'], 'fim' => $v['end_date']];
        }
    }

    // Cache de Preparação/Atestados (Dedução de Regência vs Atividades)
    $atividades_ocupadas = [];
    $res_p = mysqli_query($conn, "
        SELECT tipo, data_inicio, data_fim, dias_semana, horario_inicio, horario_fim 
        FROM preparacao_atestados 
        WHERE docente_id = $docente_id AND status = 'ativo'
          AND data_inicio <= '$data_fim' AND data_fim >= '$data_inicio'
    ");
    if ($res_p) {
        while ($p = mysqli_fetch_assoc($res_p)) {
            $h_ini_p = strtotime($p['horario_inicio']);
            $h_fim_p = strtotime($p['horario_fim']);
            $horas_p = ($h_ini_p && $h_fim_p) ? ($h_fim_p - $h_ini_p) / 3600 : 0;
            
            $dias_p = !empty($p['dias_semana']) ? explode(',', $p['dias_semana']) : [];
            
            $atividades_ocupadas[] = [
                'ini' => $p['data_inicio'],
                'fim' => $p['data_fim'],
                'dias' => $dias_p,
                'horas' => $horas_p
            ];
        }
    }

    $daysMap = [0 => 'Domingo', 1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado'];
    $daysDOWMap = ['Segunda-feira' => 1, 'Terça-feira' => 2, 'Quarta-feira' => 3, 'Quinta-feira' => 4, 'Sexta-feira' => 5, 'Sábado' => 6, 'Domingo' => 0];

    $query = "SELECT dias, horario, data_inicio, data_fim 
              FROM horario_trabalho 
              WHERE docente_id = $docente_id";
    $res = mysqli_query($conn, $query);
    
    $total_horas = 0;
    while ($row = mysqli_fetch_assoc($res)) {
        $partes = explode(' as ', mb_strtolower($row['horario']));
        if (count($partes) !== 2) continue;
        $h_ini = strtotime(trim($partes[0]));
        $h_fim = strtotime(trim($partes[1]));
        if (!$h_ini || !$h_fim || $h_fim <= $h_ini) continue;
        $horas_por_dia = ($h_fim - $h_ini) / 3600;

        $dias_autorizados = array_map(function($d) { return mb_strtolower(trim($d), 'UTF-8'); }, explode(',', $row['dias']));

        $bloco_ini = !empty($row['data_inicio']) ? max($data_inicio, $row['data_inicio']) : $data_inicio;
        $bloco_fim = !empty($row['data_fim']) ? min($data_fim, $row['data_fim']) : $data_fim;
        if ($bloco_ini > $bloco_fim) continue;

        $it = new DateTime($bloco_ini);
        $itFim = new DateTime($bloco_fim);
        $it->setTime(0,0,0);
        $itFim->setTime(0,0,0);

        while ($it <= $itFim) {
            $curr_date = $it->format('Y-m-d');
            $w = (int)$it->format('w');
            $nome_dia = mb_strtolower($daysMap[$w], 'UTF-8');

            if (in_array($nome_dia, $dias_autorizados)) {
                // 1. Check Feriado (Cache)
                if (!isset($feriados_cache[$curr_date])) {
                    // 2. Check Férias (Local Intervals)
                    $esta_de_ferias = false;
                    foreach ($ferias_intervalos as $periodo) {
                        if ($curr_date >= $periodo['ini'] && $curr_date <= $periodo['fim']) {
                            $esta_de_ferias = true;
                            break;
                        }
                    }
                    
                    if (!$esta_de_ferias) {
                        // 3. DEDUÇÃO DE PREPARAÇÃO/HORA-ATIVIDADE
                        $horas_disponiveis_no_dia = $horas_por_dia;
                        foreach ($atividades_ocupadas as $atv) {
                            if ($curr_date >= $atv['ini'] && $curr_date <= $atv['fim']) {
                                // Se for o mesmo dia da semana ou se não especificado dias (bloqueia o dia todo se horas baterem)
                                if (empty($atv['dias']) || in_array($w, $atv['dias'])) {
                                    $horas_disponiveis_no_dia = max(0, $horas_disponiveis_no_dia - $atv['horas']);
                                }
                            }
                        }
                        $total_horas += $horas_disponiveis_no_dia;
                    }
                }
            }
            $it->modify('+1 day');
        }
    }
    return $total_horas;
}

