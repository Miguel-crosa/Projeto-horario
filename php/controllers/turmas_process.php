<?php
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/auth.php';
require_once __DIR__ . '/../configs/utils.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'save';

if ($action == 'delete') {
    $id = mysqli_real_escape_string($conn, $_GET['id']);

    // Get sigla for notification before deleting
    $res = mysqli_query($conn, "SELECT sigla FROM turma WHERE id = '$id'");
    $sigla_deleted = ($row = mysqli_fetch_assoc($res)) ? $row['sigla'] : 'Desconhecida';

    // Delete associated agenda records first
    mysqli_query($conn, "DELETE FROM agenda WHERE turma_id = '$id'");
    mysqli_query($conn, "DELETE FROM turma WHERE id = '$id'");

    dispararNotificacaoGlobal($conn, 'exclusao_turma', 'Turma Excluída', "A turma $sigla_deleted foi removida do sistema.", BASE_URL . "/php/views/turmas.php", ['admin', 'gestor', 'professor', 'cri']);

    header("Location: ../views/turmas.php?msg=deleted");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $is_ajax = isset($_POST['ajax']) && $_POST['ajax'] == '1';

    function handle_response($conn, $success, $message, $redirect_url, $is_ajax)
    {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => $success, 'message' => $message, 'redirect' => $redirect_url]);
            exit;
        } else {
            if ($success) {
                header("Location: $redirect_url");
            } else {
                $msg = urlencode($message);
                header("Location: ../views/turmas_form.php?msg=error&error_text=$msg");
            }
            exit;
        }
    }

    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $curso_id = mysqli_real_escape_string($conn, $_POST['curso_id']);
    $tipo = mysqli_real_escape_string($conn, $_POST['tipo']);
    $periodo = mysqli_real_escape_string($conn, $_POST['periodo']);
    $data_inicio = mysqli_real_escape_string($conn, $_POST['data_inicio']);
    $data_fim = mysqli_real_escape_string($conn, $_POST['data_fim']);
    $ambiente_id = mysqli_real_escape_string($conn, $_POST['ambiente_id']);
    $sigla = mysqli_real_escape_string($conn, $_POST['sigla']);
    $vagas = (int) $_POST['vagas'];
    $local = mysqli_real_escape_string($conn, $_POST['local']);
    $docente_id1 = !empty($_POST['docente_id1']) ? (int) $_POST['docente_id1'] : "NULL";
    $docente_id2 = !empty($_POST['docente_id2']) ? (int) $_POST['docente_id2'] : "NULL";
    $docente_id3 = !empty($_POST['docente_id3']) ? (int) $_POST['docente_id3'] : "NULL";
    $docente_id4 = !empty($_POST['docente_id4']) ? (int) $_POST['docente_id4'] : "NULL";
    $dias_semana_arr = $_POST['dias_semana'] ?? [];
    $dias_semana_str = mysqli_real_escape_string($conn, implode(',', $dias_semana_arr));
    $horario_inicio = mysqli_real_escape_string($conn, $_POST['horario_inicio'] ?? '07:30');
    $horario_fim = mysqli_real_escape_string($conn, $_POST['horario_fim'] ?? '11:30');
    $tipo_custeio = mysqli_real_escape_string($conn, $_POST['tipo_custeio'] ?? 'Gratuidade');
    $previsao_despesa = (float) ($_POST['previsao_despesa'] ?? 0);
    $valor_turma = $tipo_custeio === 'Ressarcido' ? (float) ($_POST['valor_turma'] ?? 0) : 0;
    $numero_proposta = mysqli_real_escape_string($conn, $_POST['numero_proposta'] ?? '');
    $tipo_atendimento = mysqli_real_escape_string($conn, $_POST['tipo_atendimento'] ?? 'Balcão');
    $parceiro = mysqli_real_escape_string($conn, $_POST['parceiro'] ?? '');
    $contato_parceiro = mysqli_real_escape_string($conn, $_POST['contato_parceiro'] ?? '');

    // Auto-derive if needed (fallback, though POST should have it now)
    if (empty($horario_inicio) || empty($horario_fim)) {
        $period_times = [
            'Manhã' => ['07:30', '11:30'],
            'Tarde' => ['13:30', '17:30'],
            'Noite' => ['18:00', '22:00'],
            'Integral' => ['07:30', '17:30'],
        ];
        $horario_inicio = $horario_inicio ?: ($period_times[$periodo][0] ?? '07:30');
        $horario_fim = $horario_fim ?: ($period_times[$periodo][1] ?? '11:30');
    }

    // For notification naming
    $display_nome = !empty($sigla) ? $sigla : "Turma (Sem Sigla)";

    // --- VALIDATION: Professor Hour Limits & Conflicts ---
    $docentes_to_check = array_filter([$docente_id1, $docente_id2, $docente_id3, $docente_id4], function ($val) {
        return $val !== "NULL" && $val > 0;
    });

    foreach ($docentes_to_check as $did) {
        $val_res = checkDocenteLimits($conn, $did, $id ?: null, $data_inicio, $data_fim, $dias_semana_arr, $horario_inicio, $horario_fim);
        if ($val_res !== true) {
            handle_response($conn, false, $val_res, "", $is_ajax);
        }

        $conf_res = checkDocenteConflicts($conn, $did, $id ?: null, $data_inicio, $data_fim, $dias_semana_arr, $horario_inicio, $horario_fim);
        if ($conf_res !== true) {
            handle_response($conn, false, $conf_res, "", $is_ajax);
        }

        $work_res = checkDocenteWorkSchedule($conn, $did, $data_inicio, $data_fim, $dias_semana_arr, $periodo, $horario_inicio, $horario_fim);
        if ($work_res !== true) {
            handle_response($conn, false, $work_res, "", $is_ajax);
        }
    }

    // --- VALIDATION: Environment (Ambiente) Conflict ---
    $amb_res = checkAmbienteConflict($conn, $ambiente_id, $id ?: null, $data_inicio, $data_fim, $dias_semana_arr, $horario_inicio, $horario_fim);
    if ($amb_res !== true) {
        handle_response($conn, false, $amb_res, "", $is_ajax);
    }

    if ($id) {
        // UPDATE existing turma
        $query = "UPDATE turma SET 
                  curso_id = '$curso_id', 
                  tipo = '$tipo', 
                  periodo = '$periodo', 
                  data_inicio = '$data_inicio', 
                  data_fim = '$data_fim', 
                  ambiente_id = '$ambiente_id', 
                  sigla = '$sigla',
                  vagas = $vagas,
                  local = '$local',
                  dias_semana = '$dias_semana_str',
                  horario_inicio = '$horario_inicio',
                  horario_fim = '$horario_fim',
                  docente_id1 = $docente_id1,
                  docente_id2 = $docente_id2,
                  docente_id3 = $docente_id3,
                  docente_id4 = $docente_id4,
                  tipo_custeio = '$tipo_custeio',
                  previsao_despesa = $previsao_despesa,
                  valor_turma = $valor_turma,
                  numero_proposta = '$numero_proposta',
                  tipo_atendimento = '$tipo_atendimento',
                  parceiro = '$parceiro',
                  contato_parceiro = '$contato_parceiro'
                  WHERE id = '$id'";
        mysqli_query($conn, $query);

        dispararNotificacaoGlobal($conn, 'edicao_turma', 'Turma Atualizada', "A turma $display_nome ($periodo) teve seus dados atualizados.", BASE_URL . "/php/views/turmas.php", ['admin', 'gestor', 'professor', 'cri']);

        // Regenerate agenda: delete old records and create new ones
        mysqli_query($conn, "DELETE FROM agenda WHERE turma_id = '$id'");
        generateAgendaRecords($conn, $id, $dias_semana_arr, $periodo, $horario_inicio, $horario_fim, $data_inicio, $data_fim, $ambiente_id, $docentes_to_check);

        $next_url = "../views/agenda_professores.php?docente_id=" . (!empty($docentes_to_check) ? $docentes_to_check[0] : '') . "&msg=updated";

        if (isset($_POST['validate_only']) && $_POST['validate_only'] == '1') {
            handle_response($conn, true, "Horário disponível para atualização", "", $is_ajax);
        }

        handle_response($conn, true, "Turma atualizada com sucesso", $next_url, $is_ajax);
    } else {
        // INSERT new turma
        $query = "INSERT INTO turma (curso_id, tipo, periodo, data_inicio, data_fim, ambiente_id, sigla, vagas, local, dias_semana, horario_inicio, horario_fim, docente_id1, docente_id2, docente_id3, docente_id4, tipo_custeio, previsao_despesa, valor_turma, numero_proposta, tipo_atendimento, parceiro, contato_parceiro) 
                  VALUES ('$curso_id', '$tipo', '$periodo', '$data_inicio', '$data_fim', '$ambiente_id', '$sigla', $vagas, '$local', '$dias_semana_str', '$horario_inicio', '$horario_fim', $docente_id1, $docente_id2, $docente_id3, $docente_id4, '$tipo_custeio', $previsao_despesa, $valor_turma, '$numero_proposta', '$tipo_atendimento', '$parceiro', '$contato_parceiro')";

        if (isset($_POST['validate_only']) && $_POST['validate_only'] == '1') {
            handle_response($conn, true, "Horário disponível para cadastro", "", $is_ajax);
        }

        mysqli_query($conn, $query);
        $turma_id = mysqli_insert_id($conn);

        dispararNotificacaoGlobal($conn, 'registro_turma', 'Nova Turma Registrada', "A turma $display_nome ($periodo) foi cadastrada no sistema.", BASE_URL . "/php/views/turmas.php", ['admin', 'gestor', 'professor', 'cri']);

        // Auto-generate agenda records
        generateAgendaRecords($conn, $turma_id, $dias_semana_arr, $periodo, $horario_inicio, $horario_fim, $data_inicio, $data_fim, $ambiente_id, $docentes_to_check);

        // Redirect to calendar instead of turmas list
        $next_url = "../views/agenda_professores.php?docente_id=" . (!empty($docentes_to_check) ? $docentes_to_check[0] : '') . "&msg=created";
        handle_response($conn, true, "Turma criada com sucesso", $next_url, $is_ajax);
    }
    exit;
}

/**
 * Generate Agenda records for each valid day between data_inicio and data_fim.
 * Automatically SKIPS holidays and vacation days for each docente.
 *
 * FIX: Todas as comparações de DateTime agora usam format('Y-m-d') para evitar
 * que o horário 00:00:00 faça o último dia ser ignorado no loop.
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

    // Handle NULL ambiente_id gracefully for FK constraint
    $amb_sql = (!empty($ambiente_id) && intval($ambiente_id) > 0) ? intval($ambiente_id) : 'NULL';

    $it = new DateTime($data_inicio);
    $end = new DateTime($data_fim);

    // FIX: normaliza ambas as datas para meia-noite para garantir comparação
    // puramente por data, sem interferência de horário residual.
    $it->setTime(0, 0, 0);
    $end->setTime(0, 0, 0);

    while ($it->format('Y-m-d') <= $end->format('Y-m-d')) {
        $w = (int) $it->format('w');
        $dayName = $daysMap[$w] ?? '';

        if (in_array($dayName, $dias_arr)) {
            $dateStr = $it->format('Y-m-d');

            // Skip holidays (national/institutional)
            if (isHoliday($conn, $dateStr)) {
                $it->modify('+1 day');
                continue;
            }

            $dia_esc = mysqli_real_escape_string($conn, $dayName);
            $periodo_esc = mysqli_real_escape_string($conn, $periodo);

            if (!empty($docentes_ids)) {
                foreach ($docentes_ids as $doc_id) {
                    $doc_val = (int) $doc_id;
                    // Skip if this specific docente is on vacation
                    if (isVacation($conn, $doc_val, $dateStr)) {
                        continue;
                    }
                    mysqli_query($conn, "INSERT INTO agenda (turma_id, docente_id, ambiente_id, dia_semana, periodo, horario_inicio, horario_fim, data, status)
                                         VALUES ('$turma_id', $doc_val, $amb_sql, '$dia_esc', '$periodo_esc', '$h_inicio', '$h_fim', '$dateStr', 'CONFIRMADO')");
                }
            } else {
                mysqli_query($conn, "INSERT INTO agenda (turma_id, docente_id, ambiente_id, dia_semana, periodo, horario_inicio, horario_fim, data, status)
                                     VALUES ('$turma_id', NULL, $amb_sql, '$dia_esc', '$periodo_esc', '$h_inicio', '$h_fim', '$dateStr', 'CONFIRMADO')");
            }
        }
        $it->modify('+1 day');
    }
}

header("Location: ../views/turmas.php");

/**
 * Validates teacher limits against proposed turma schedule.
 *
 * FIX: Todas as comparações de DateTime agora usam format('Y-m-d') para evitar
 * que o horário 00:00:00 faça o último dia ser cortado nas interseções de
 * semana/mês.
 */
function checkDocenteLimits($conn, $docente_id, $turma_id_to_ignore, $data_start, $data_end, $days_arr, $h_start, $h_end)
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

                $q = "SELECT SUM(TIMESTAMPDIFF(SECOND, horario_inicio, horario_fim))/3600 as total 
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

                $q = "SELECT SUM(TIMESTAMPDIFF(SECOND, horario_inicio, horario_fim))/3600 as total 
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