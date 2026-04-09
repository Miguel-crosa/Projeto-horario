<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/auth.php';
require_once __DIR__ . '/../configs/utils.php';
require_once __DIR__ . '/../models/AgendaModel.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$agendaModel = new AgendaModel($conn);

switch ($action) {
    case 'get_docente_agenda':
        $docente_id = (int) ($_GET['docente_id'] ?? 0);
        if (!$docente_id) {
            echo json_encode(['error' => 'ID inválido']);
            exit;
        }

        // Use unified model logic
        // For the calendar, we usually want a wide range or at least the year of context
        $focus_month = $_GET['month'] ?? null; // format Y-m
        $default_start = date('Y-01-01');
        $default_end = date('Y-12-31');

        if ($focus_month && preg_match('/^\d{4}-\d{2}$/', $focus_month)) {
            $year = substr($focus_month, 0, 4);
            $default_start = "$year-01-01";
            $default_end = "$year-12-31";
        }

        $start_date = $_GET['start'] ?? $default_start;
        $end_date = $_GET['end'] ?? $default_end;

        $agendas = $agendaModel->getExpandedAgenda([$docente_id], $start_date, $end_date);

        $doc_res = mysqli_query($conn, "SELECT id, nome, area_conhecimento FROM docente WHERE id = $docente_id");
        $doc = $doc_res ? mysqli_fetch_assoc($doc_res) : null;

        $meses_ocupados = [];
        $ultima_aula = null;
        $is_cri = isCRI() && !isAdmin() && !isGestor();

        foreach ($agendas as $key => &$ag) {
            if (($ag['type'] ?? '') === 'WORK_SCHEDULE') {
                continue;
            }
            // CRI Restriction: Can only see details of their own reservations
            $is_owner = (isset($ag['usuario_id']) && $ag['usuario_id'] == $auth_user_id);

            if ($is_cri && !$is_owner) {
                $ag['curso_nome'] = 'Indisponível (Reservado)';
                $ag['ambiente_nome'] = '---';
                if (isset($ag['sigla']))
                    $ag['sigla'] = '---';
            }

            // Calculate metadata for the calendar UI
            $d_start = $ag['data_inicio'] ?? ($ag['agenda_data'] ?? 'now');
            $d_end = $ag['data_fim'] ?? ($ag['agenda_data'] ?? 'now');

            $iter = new DateTime($d_start);
            $fim = new DateTime($d_end);
            while ($iter <= $fim) {
                $meses_ocupados[$iter->format('Y-m')] = true;
                $iter->modify('first day of next month');
            }
            if ($d_end && (!$ultima_aula || $d_end > $ultima_aula))
                $ultima_aula = $d_end;
        }

        echo json_encode(['docente' => $doc, 'agendas' => $agendas, 'meses_ocupados' => array_keys($meses_ocupados), 'ultima_aula' => $ultima_aula], JSON_UNESCAPED_UNICODE);
        break;

    case 'remove_reservation':
        if (!isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Acesso negado. Apenas administradores podem remover reservas.']);
            exit;
        }

        $docente_id = (int) ($_POST['docente_id'] ?? 0);
        $data = $_POST['data'] ?? '';
        $periodo = $_POST['periodo'] ?? '';

        if (!$docente_id || !$data) {
            echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos para remoção de reserva.']);
            exit;
        }

        $query = "DELETE FROM agenda 
                  WHERE docente_id = ? 
                    AND data = ? 
                    AND status = 'RESERVADO' 
                    AND turma_id IS NULL";
        
        if ($periodo) {
            $query .= " AND periodo = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('iss', $docente_id, $data, $periodo);
        } else {
            $stmt = $conn->prepare($query);
            $stmt->bind_param('is', $docente_id, $data);
        }

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Reserva removida com sucesso.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao processar solicitação.']);
        }
        $stmt->close();
        exit;

    case 'remove_reservations_batch':
        if (!isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
            exit;
        }
        $docente_id = (int) ($_POST['docente_id'] ?? 0);
        $dates = $_POST['dates'] ?? [];
        $periodo = $_POST['periodo'] ?? '';

        if (!$docente_id || empty($dates)) {
            echo json_encode(['success' => false, 'message' => 'Docente e datas são obrigatórios.']);
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($dates), '?'));
        $query = "DELETE FROM agenda WHERE docente_id = ? AND data IN ($placeholders) AND status = 'RESERVADO' AND turma_id IS NULL";
        
        if ($periodo) {
            $query .= " AND periodo = ?";
        }

        $stmt = $conn->prepare($query);
        $types = 'i' . str_repeat('s', count($dates));
        $params = array_merge([$docente_id], $dates);
        if ($periodo) {
            $types .= 's';
            $params[] = $periodo;
        }
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            $count = $stmt->affected_rows;
            echo json_encode(['success' => true, 'message' => "$count reserva(s) removida(s)."]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao processar remoção em lote.']);
        }
        $stmt->close();
        exit;

    case 'save_reservations':
        $docente_id = (int) ($_POST['docente_id'] ?? 0);
        $dates = $_POST['dates'] ?? [];
        $periodo = mysqli_real_escape_string($conn, $_POST['periodo'] ?? 'Manhã');

        if (!$docente_id || empty($dates)) {
            echo json_encode(['success' => false, 'message' => 'Docente e datas são obrigatórios.']);
            exit;
        }

        $saved = 0;
        $daysMap = [0 => 'Domingo', 1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado'];

        $stmt_check = $conn->prepare("SELECT id FROM agenda WHERE docente_id = ? AND data = ? AND status = 'RESERVADO' AND turma_id IS NULL AND periodo = ?");
        $stmt_ins = $conn->prepare("INSERT INTO agenda (docente_id, dia_semana, data, status, turma_id, periodo) VALUES (?, ?, ?, 'RESERVADO', NULL, ?)");

        foreach ($dates as $date) {
            if ($h = isHoliday($conn, $date)) {
                echo json_encode(['success' => false, 'message' => "Não é possível reservar: $date é feriado ({$h['name']})."]);
                exit;
            }
            if ($v = isVacation($conn, $docente_id, $date)) {
                $v_msg = ($v['type'] === 'collective') ? "férias coletivas" : "férias do professor";
                echo json_encode(['success' => false, 'message' => "Não é possível reservar: $date está dentro do período de $v_msg."]);
                exit;
            }

            $w = (int) date('w', strtotime($date));
            $dia_semana = $daysMap[$w];

            if (!isWithinWorkSchedule($conn, $docente_id, $date, $periodo)) {
                echo json_encode(['success' => false, 'message' => "O docente não possui disponibilidade cadastrada para $dia_semana no período $periodo."]);
                exit;
            }

            $stmt_check->bind_param('iss', $docente_id, $date, $periodo);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows == 0) {
                $stmt_ins->bind_param('isss', $docente_id, $dia_semana, $date, $periodo);
                $stmt_ins->execute();
                $saved++;
            }
        }
        $stmt_check->close();
        $stmt_ins->close();

        echo json_encode(['success' => true, 'message' => "$saved dia(s) reservado(s) com sucesso."], JSON_UNESCAPED_UNICODE);
        break;

    case 'salvar_horario':
        // --- DEDUPLICAÇÃO E VALIDAÇÃO DE DOCENTES ---
        // Coleta todos os IDs enviados e remove duplicatas ou zeros
        $raw_ids = [
            (int) ($_POST['docente_id1'] ?? 0),
            (int) ($_POST['docente_id2'] ?? 0),
            (int) ($_POST['docente_id3'] ?? 0),
            (int) ($_POST['docente_id4'] ?? 0),
            (int) ($_POST['docente_id'] ?? 0) // ID oculto do calendário
        ];

        $all_docente_ids = array_unique(array_filter($raw_ids));
        $ids_unique = array_values($all_docente_ids);

        // Define os IDs individuais para a tabela 'turma' (ID1 é o principal)
        $docente_id = $ids_unique[0] ?? 0;
        $docente_id1 = $docente_id;
        $docente_id2 = $ids_unique[1] ?? 0;
        $docente_id3 = $ids_unique[2] ?? 0;
        $docente_id4 = $ids_unique[3] ?? 0;

        $curso_id = (int) ($_POST['curso_id'] ?? 0);
        $ambiente_id = (int) ($_POST['ambiente_id'] ?? 0);
        $dias_semana = $_POST['dias_semana'] ?? [];
        $periodo = mysqli_real_escape_string($conn, $_POST['periodo'] ?? '');
        $h_inicio = mysqli_real_escape_string($conn, $_POST['horario_inicio'] ?? '');
        $h_fim = mysqli_real_escape_string($conn, $_POST['horario_fim'] ?? '');
        $data_inicio = mysqli_real_escape_string($conn, $_POST['data_inicio'] ?? '');
        $data_fim = mysqli_real_escape_string($conn, $_POST['data_fim'] ?? '');

        $is_simulation = ($_POST['is_simulation'] ?? '0') == '1';
        $is_reserva_flag = ($_POST['is_reserva'] ?? '0') == '1' || isCRI();
        $edit_reserva_id = (int) ($_POST['edit_reserva_id'] ?? 0); // ID to ignore in conflicts

        // New Turma metadata fields
        $sigla = mysqli_real_escape_string($conn, $_POST['sigla'] ?? '');
        $vagas = (int) ($_POST['vagas'] ?? 32);
        $local = mysqli_real_escape_string($conn, $_POST['local'] ?? 'Sede');
        $tipo = mysqli_real_escape_string($conn, $_POST['tipo'] ?? 'Presencial');

        // dias_semana can be array or CSV string
        if (is_string($dias_semana)) {
            $dias_semana = array_filter(explode(',', $dias_semana));
        }

        // Build list of ALL docentes involved (Moved up for validations)
        // $all_docente_ids já foi construído acima de forma única e limpa

        $missing = [];
        if (empty($all_docente_ids))
            $missing[] = 'Docente';
        if (empty($dias_semana))
            $missing[] = 'Dias da Semana';
        if (!$periodo)
            $missing[] = 'Período';
        if (!$data_inicio)
            $missing[] = 'Data Início';
        if (!$data_fim)
            $missing[] = 'Data Fim';

        // Course and Environment are required for real Turmas, but optional for Simulations and Reservations
        if (!$is_simulation && !$is_reserva_flag) {
            if (!$curso_id)
                $missing[] = 'Curso';
            if (!$ambiente_id)
                $missing[] = 'Ambiente';
        }

        if (!empty($missing)) {
            echo json_encode(['success' => false, 'message' => 'Faltam campos obrigatórios: ' . implode(', ', $missing)]);
            exit;
        }

        // --- VALIDATIONS (UPDATE.MD) ---
        // 1. Check Holidays and Vacations
        $it_v = new DateTime($data_inicio);
        $it_v_fim = new DateTime($data_fim);
        $daysMap = [0 => 'Domingo', 1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado'];

        while ($it_v <= $it_v_fim) {
            $curr_v = $it_v->format('Y-m-d');
            $w = (int) $it_v->format('w');
            if (in_array($daysMap[$w], $dias_semana)) {
                /* BLOQUEIOS SILENCIADOS - O SISTEMA PULA ESSES DIAS NO SALVAMENTO REAL */
                /*
                if ($h = isHoliday($conn, $curr_v)) {
                    echo json_encode(['success' => false, 'message' => "O período selecionado inclui o feriado '{$h['name']}' em " . $it_v->format('d/m/Y') . ". Operação bloqueada."]);
                    exit;
                }
                */
                foreach ($all_docente_ids as $did) {
                    // --- WORK SCHEDULE ENFORCEMENT ---
                    if (!isWithinWorkSchedule($conn, $did, $curr_v, $periodo)) {
                        $doc_name = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nome FROM docente WHERE id = $did"))['nome'] ?? "Docente #$did";
                        echo json_encode(['success' => false, 'message' => "O docente $doc_name não possui disponibilidade cadastrada em " . $it_v->format('d/m/Y') . " ($periodo)."]);
                        exit;
                    }

                    /*
                    if ($v = isVacation($conn, $did, $curr_v)) {
                        $v_msg = ($v['type'] ?? 'professor') === 'collective' ? "férias coletivas" : "férias do professor";
                        $doc_name = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nome FROM docente WHERE id = $did"))['nome'] ?? "Docente #$did";
                        echo json_encode(['success' => false, 'message' => "O período selecionado conflita com $v_msg ($doc_name) em " . $it_v->format('d/m/Y') . "."]);
                        exit;
                    }
                    */
                }
            }
            $it_v->modify('+1 day');
        }

        // 2. Check Exclusivity and Hour Limits
        $hoursMap = ['Manhã' => 4, 'Tarde' => 4, 'Noite' => 4, 'Integral' => 12];
        $target_h_per_day = $hoursMap[$periodo] ?? 0;

        foreach ($all_docente_ids as $did) {
            $lim_res = mysqli_query($conn, "SELECT nome, weekly_hours_limit, monthly_hours_limit FROM docente WHERE id = $did");
            $doc_lim = $lim_res ? mysqli_fetch_assoc($lim_res) : null;

            if ($doc_lim) {
                $it_start = new DateTime($data_inicio);
                $it_end = new DateTime($data_fim);
                
                // --- MONTHLY LIMIT VALIDATION (Iterate through all affected months) ---
                if ($doc_lim['monthly_hours_limit'] > 0) {
                    $months_to_check = [];
                    $temp_it = clone $it_start;
                    while ($temp_it <= $it_end) {
                        $m_key = $temp_it->format('Y-m');
                        if (!isset($months_to_check[$m_key])) {
                            $months_to_check[$m_key] = [
                                'start' => $temp_it->format('Y-m-01'),
                                'end'   => $temp_it->format('Y-m-t'),
                                'new_hours' => 0
                            ];
                        }
                        
                        // Check if this specific day is a class day for this docente
                        $curr_d = $temp_it->format('Y-m-d');
                        $w_idx = (int)$temp_it->format('w');
                        if (in_array($daysMap[$w_idx], $dias_semana)) {
                            if (!isHoliday($conn, $curr_d) && !isVacation($conn, $did, $curr_d)) {
                                $months_to_check[$m_key]['new_hours'] += $target_h_per_day;
                            }
                        }
                        $temp_it->modify('+1 day');
                    }

                    foreach ($months_to_check as $m_key => $m_data) {
                        $consumed = calculateConsumedHours($conn, $did, $m_data['start'], $m_data['end']);
                        if (($consumed + $m_data['new_hours']) > ($doc_lim['monthly_hours_limit'] + 0.01)) {
                            $m_label = date('m/Y', strtotime($m_data['start']));
                            echo json_encode([
                                'success' => false, 
                                'message' => "Limite MENSAL excedido para {$doc_lim['nome']} em $m_label ($consumed h atuais + {$m_data['new_hours']} h previstas > {$doc_lim['monthly_hours_limit']} h limite)."
                            ]);
                            exit;
                        }
                    }
                }

                // --- WEEKLY LIMIT VALIDATION (Iterate through all affected weeks) ---
                if ($doc_lim['weekly_hours_limit'] > 0) {
                    $weeks_to_check = [];
                    $temp_it = clone $it_start;
                    while ($temp_it <= $it_end) {
                        $w_key = $temp_it->format('o-W'); // Year-WeekNumber
                        if (!isset($weeks_to_check[$w_key])) {
                            $w_start = clone $temp_it;
                            $w_start->modify('Monday this week');
                            $w_end = clone $w_start;
                            $w_end->modify('+6 days');
                            
                            $weeks_to_check[$w_key] = [
                                'start' => $w_start->format('Y-m-d'),
                                'end'   => $w_end->format('Y-m-d'),
                                'new_hours' => 0
                            ];
                        }

                        $curr_d = $temp_it->format('Y-m-d');
                        $w_idx = (int)$temp_it->format('w');
                        if (in_array($daysMap[$w_idx], $dias_semana)) {
                            if (!isHoliday($conn, $curr_d) && !isVacation($conn, $did, $curr_d)) {
                                $weeks_to_check[$w_key]['new_hours'] += $target_h_per_day;
                            }
                        }
                        $temp_it->modify('+1 day');
                    }

                    foreach ($weeks_to_check as $w_key => $w_data) {
                        $consumed = calculateConsumedHours($conn, $did, $w_data['start'], $w_data['end']);
                        if (($consumed + $w_data['new_hours']) > ($doc_lim['weekly_hours_limit'] + 0.01)) {
                            $w_label = date('d/m', strtotime($w_data['start']));
                            echo json_encode([
                                'success' => false,
                                'message' => "Limite SEMANAL excedido para {$doc_lim['nome']} na semana de $w_label ($consumed h atuais + {$w_data['new_hours']} h previstas > {$doc_lim['weekly_hours_limit']} h limite)."
                            ]);
                            exit;
                        }
                    }
                }

                // 5. If period is Manhã or Noite, check daily exclusivity
                if ($periodo === 'Manhã' || $periodo === 'Noite' || $periodo === 'Integral') {
                    $it_e = new DateTime($data_inicio);
                    $it_e_fim = new DateTime($data_fim);
                    while ($it_e <= $it_e_fim) {
                        $curr_e = $it_e->format('Y-m-d');
                        $w = (int) $it_e->format('w');
                        if (in_array($daysMap[$w], $dias_semana)) {
                            $opposing = ($periodo === 'Manhã') ? 'Noite' : (($periodo === 'Noite') ? 'Manhã' : 'Integral');
                            $q_opp = "SELECT id FROM agenda WHERE docente_id = $did AND data = '$curr_e' AND (periodo = '$opposing' OR periodo = 'Integral')";
                            $res_opp = mysqli_query($conn, $q_opp);
                            if (mysqli_num_rows($res_opp) > 0) {
                                echo json_encode(['success' => false, 'message' => "O docente {$doc_lim['nome']} já possui horário em $opposing na data " . $it_e->format('d/m/Y') . ". Exclusividade Manhã/Noite violada."]);
                                exit;
                            }
                        }
                        $it_e->modify('+1 day');
                    }
                }
            }
        }

        // Auto-set horarios from period if not provided
        $periodTimes = [
            'Manhã' => ['07:30', '11:30'],
            'Tarde' => ['13:30', '17:30'],
            'Noite' => ['19:30', '23:30'],
            'Integral' => ['07:30', '17:30']
        ];

        if ($periodo && $periodo !== 'Todos') {
            $times = $periodTimes[$periodo] ?? ['07:30', '11:30'];
            $h_inicio = $h_inicio ?: $times[0];
            $h_fim = $h_fim ?: $times[1];
        }

        // Build list of ALL docentes involved (Actually done above)
        // $all_docente_ids = array_unique(array_filter([$docente_id, $docente_id1, $docente_id2, $docente_id3, $docente_id4]));

        // Conflict checking — check ALL docentes
        $conflitos = [];
        foreach ($dias_semana as $dia) {
            $dia_esc = mysqli_real_escape_string($conn, $dia);

            $base_q = "SELECT a.id, c.nome AS curso, t.data_inicio AS t_start, t.data_fim AS t_end, d.nome AS docente_nome
                  FROM agenda a 
                  JOIN turma t ON a.turma_id = t.id 
                  JOIN curso c ON t.curso_id = c.id 
                  JOIN docente d ON a.docente_id = d.id
                  WHERE a.dia_semana = '$dia_esc' 
                  AND a.horario_inicio < '$h_fim' AND a.horario_fim > '$h_inicio'
                  AND t.data_inicio <= '$data_fim' AND t.data_fim >= '$data_inicio' ";

            $getFirstConflictDate = function ($conn, $query, $target_start, $target_end, $dia_nome) {
                $res = mysqli_query($conn, $query);
                while ($r = mysqli_fetch_assoc($res)) {
                    // Normalize dates from different table structures if needed
                    $r_start = $r['t_start'] ?? $r['data_inicio'];
                    $r_end = $r['t_end'] ?? $r['data_fim'];

                    $start = max($target_start, $r_start);
                    $end = min($target_end, $r_end);
                    $it = new DateTime($start);
                    $itEnd = new DateTime($end);
                    $daysMap = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];

                    // The dia_nome is the day we are checking (e.g. 'Segunda-feira')
                    // The conflict record might have a list of days (reservas) or a single day (agenda)
                    $is_in_days = false;
                    $r_dias = isset($r['dias_semana']) ? explode(',', $r['dias_semana']) : [$r['dia_semana']];
                    if (in_array($dia_nome, $r_dias)) {
                        $is_in_days = true;
                    }

                    if ($is_in_days) {
                        while ($it <= $itEnd) {
                            if ($daysMap[(int) $it->format('w')] === $dia_nome) {
                                return ['curso' => $r['curso'], 'date' => $it->format('d/m/Y'), 'docente' => $r['docente_nome'] ?? '', 'tipo' => $r['conflict_type'] ?? 'AULA'];
                            }
                            $it->modify('+1 day');
                        }
                    }
                }
                return null;
            };

            // 1. Check conflicts in Agenda table (Already confirmed classes)
            $agenda_q = "SELECT a.id, c.nome AS curso, t.data_inicio AS t_start, t.data_fim AS t_end, d.nome AS docente_nome, a.dia_semana, 'AULA' as conflict_type
                  FROM agenda a 
                  JOIN turma t ON a.turma_id = t.id 
                  JOIN curso c ON t.curso_id = c.id 
                  JOIN docente d ON a.docente_id = d.id
                  WHERE a.dia_semana = '$dia_esc' 
                  AND a.horario_inicio < '$h_fim' AND a.horario_fim > '$h_inicio'
                  AND t.data_inicio <= '$data_fim' AND t.data_fim >= '$data_inicio' ";

            // 2. Check conflicts in reservas table (Pending or Approved requests)
            $reserva_q = "SELECT r.id, c.nome AS curso, r.data_inicio, r.data_fim, d.nome AS docente_nome, r.dias_semana, 'RESERVA' as conflict_type
                  FROM reservas r
                  JOIN curso c ON r.curso_id = c.id
                  JOIN docente d ON r.docente_id = d.id
                  WHERE (r.status = 'PENDENTE' OR r.status = 'APROVADA')
                  AND r.id != $edit_reserva_id
                  AND r.hora_inicio < '$h_fim' AND r.hora_fim > '$h_inicio'
                  AND r.data_inicio <= '$data_fim' AND r.data_fim >= '$data_inicio' ";

            // Check conflicts for EACH docente involved in this new scheduling
            foreach ($all_docente_ids as $did) {
                // Check against classes
                $conf_d_aula = $getFirstConflictDate($conn, $agenda_q . " AND a.docente_id = '$did'", $data_inicio, $data_fim, $dia);
                if ($conf_d_aula) {
                    $docNome = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nome FROM docente WHERE id = $did"))['nome'] ?? "Docente #$did";
                    $conflitos[] = "Conflito de Docente ($docNome) em {$conf_d_aula['date']}: {$conf_d_aula['tipo']} '{$conf_d_aula['curso']}'.";
                }
                // Check against other reservations
                $conf_d_res = $getFirstConflictDate($conn, $reserva_q . " AND r.docente_id = '$did'", $data_inicio, $data_fim, $dia);
                if ($conf_d_res) {
                    $docNome = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nome FROM docente WHERE id = $did"))['nome'] ?? "Docente #$did";
                    $conflitos[] = "Conflito de Docente ($docNome) em {$conf_d_res['date']}: {$conf_d_res['tipo']} '{$conf_d_res['curso']}'.";
                }
            }

            // Check ambiente conflict
            $is_placeholder = false;
            if ($ambiente_id) {
                $res_n = mysqli_query($conn, "SELECT nome FROM ambiente WHERE id = '$ambiente_id' LIMIT 1");
                if ($row_n = mysqli_fetch_assoc($res_n)) {
                    $nome_amb = mb_strtolower(trim($row_n['nome']), 'UTF-8');
                    if ($nome_amb === 'a definir') $is_placeholder = true;
                }
            }

            if (!$is_placeholder) {
                $conf_a_aula = $getFirstConflictDate($conn, $agenda_q . " AND a.ambiente_id = '$ambiente_id'", $data_inicio, $data_fim, $dia);
                if ($conf_a_aula)
                    $conflitos[] = "Conflito de Ambiente em {$conf_a_aula['date']}: sala ocupada por '{$conf_a_aula['curso']}'.";

                $conf_a_res = $getFirstConflictDate($conn, $reserva_q . " AND r.ambiente_id = '$ambiente_id'", $data_inicio, $data_fim, $dia);
                if ($conf_a_res)
                    $conflitos[] = "Conflito de Ambiente em {$conf_a_res['date']}: sala reservada para '{$conf_a_res['curso']}'.";
            }
        }

        if (!empty($conflitos)) {
            echo json_encode(['success' => false, 'message' => implode(' | ', $conflitos)], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (isset($_POST['is_simulation']) && $_POST['is_simulation'] == '1') {
            echo json_encode(['success' => true, 'message' => 'Simulação concluída com sucesso. Nenhum conflito de horário encontrado.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // --- NEW: Save as RESERVA PENDENTE if requested OR if user is CRI ---
        $is_reserva = ($_POST['is_reserva'] ?? '0') == '1';
        if (isCRI())
            $is_reserva = true; // CRI users can ONLY create reservations

        if ($is_reserva) {
            $dias_str = mysqli_real_escape_string($conn, implode(',', $dias_semana));
            $sql_res = "INSERT INTO reservas (docente_id, curso_id, ambiente_id, usuario_id, data_inicio, data_fim, dias_semana, hora_inicio, hora_fim, periodo, sigla, vagas, local, tipo, status)
                        VALUES ($docente_id, $curso_id, $ambiente_id, $auth_user_id, '$data_inicio', '$data_fim', '$dias_str', '$h_inicio', '$h_fim', '$periodo', '$sigla', $vagas, '$local', '$tipo', 'PENDENTE')";

            if (mysqli_query($conn, $sql_res)) {
                $reserva_id = mysqli_insert_id($conn);
                $executor = $_SESSION['user_nome'] ?? 'Usuário';
                dispararNotificacaoGlobal($conn, 'reserva_realizada', 'Nova Reserva Solicitada', "A reserva ($sigla) foi solicitada por $executor para o período de " . date('d/m/Y', strtotime($data_inicio)) . " a " . date('d/m/Y', strtotime($data_fim)) . ".", BASE_URL . "/php/views/gerenciar_reservas.php?status=PENDENTE&reserva_id=$reserva_id", ['admin', 'gestor']);

                echo json_encode(['success' => true, 'message' => 'Reserva solicitada com sucesso! Aguarde aprovação.'], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erro ao solicitar reserva: ' . mysqli_error($conn)]);
            }
            exit;
        }

        // Build Turma with all metadata
        $dias_str = mysqli_real_escape_string($conn, implode(',', $dias_semana));
        if (!$sigla) {
            $curso_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nome FROM curso WHERE id = $curso_id"));
            $sigla = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $curso_row['nome'] ?? 'CRS'), 0, 4)) . '-' . date('His');
        }

        $amb_id_val = $ambiente_id ? $ambiente_id : 'NULL';
        $d2_val = $docente_id2 ? $docente_id2 : 'NULL';
        $d3_val = $docente_id3 ? $docente_id3 : 'NULL';
        $d4_val = $docente_id4 ? $docente_id4 : 'NULL';

        $insert_turma = "INSERT INTO turma (curso_id, tipo, sigla, vagas, periodo, data_inicio, data_fim, dias_semana, ambiente_id, docente_id1, docente_id2, docente_id3, docente_id4, local) 
                         VALUES ($curso_id, '$tipo', '$sigla', $vagas, '$periodo', '$data_inicio', '$data_fim', '$dias_str', $amb_id_val, $docente_id, $d2_val, $d3_val, $d4_val, '$local')";
        if (!mysqli_query($conn, $insert_turma)) {
            echo json_encode(['success' => false, 'message' => 'Erro ao criar turma: ' . mysqli_error($conn)]);
            exit;
        }
        $turma_id = mysqli_insert_id($conn);

        // Create explicit detailed Agenda Entries per day (SKIPPING HOLIDAYS/VACATIONS)
        $it = new DateTime($data_inicio);
        $end = new DateTime($data_fim);
        $amb_val = $ambiente_id ?: 'NULL';

        while ($it <= $end) {
            $currStr = $it->format('Y-m-d');
            $w = (int) $it->format('w');
            $dayName = $daysMap[$w] ?? '';

            if (in_array($dayName, $dias_semana)) {
                $dia_esc = mysqli_real_escape_string($conn, $dayName);
                foreach ($all_docente_ids as $did) {
                    // Skip holiday/vacation
                    if (!isHoliday($conn, $currStr) && !isVacation($conn, $did, $currStr)) {
                        mysqli_query($conn, "INSERT INTO agenda (docente_id, ambiente_id, turma_id, dia_semana, periodo, horario_inicio, horario_fim, data, status)
                                             VALUES ($did, $amb_val, $turma_id, '$dia_esc', '$periodo', '$h_inicio', '$h_fim', '$currStr', 'CONFIRMADO')");
                    }
                }
            }
            $it->modify('+1 day');
        }

        // Clean up any RESERVADO entries for the involved docentes in the date range that overlap with the new period
        $p_list = "('$periodo')";
        if ($periodo === 'Manhã' || $periodo === 'Integral') {
            $p_list = "('Manhã', 'Integral')";
        }
        if ($periodo === 'Tarde') {
            $p_list = "('Tarde', 'Integral')";
        }
        if ($periodo === 'Integral') {
            $p_list = "('Manhã', 'Tarde', 'Integral')";
        }

        foreach ($all_docente_ids as $did) {
            mysqli_query($conn, "
                DELETE FROM agenda 
                WHERE docente_id = $did 
                  AND turma_id IS NULL 
                  AND status = 'RESERVADO' 
                  AND periodo IN $p_list
                  AND data >= '$data_inicio' 
                  AND data <= '$data_fim'
            ");
        }

        $executor = $_SESSION['user_nome'] ?? 'Gestor';
        dispararNotificacaoGlobal($conn, 'registro_horario', 'Novo Horário Registrado', "A turma $sigla foi agendada por $executor para " . date('d/m/Y', strtotime($data_inicio)) . " a " . date('d/m/Y', strtotime($data_fim)) . ".", BASE_URL . "/php/views/turmas.php", ['admin', 'gestor', 'professor', 'cri']);

        echo json_encode(['success' => true, 'message' => 'Turma criada e horário agendado com sucesso!'], JSON_UNESCAPED_UNICODE);
        break;

    case 'aprovar_reserva':
        if (!isAdmin() && !isGestor()) {
            echo json_encode(['success' => false, 'message' => 'Sem permissão para aprovar.']);
            exit;
        }
        $reserva_id = (int) ($_POST['reserva_id'] ?? 0);
        if (!$reserva_id) {
            echo json_encode(['success' => false, 'message' => 'ID da reserva inválido.']);
            exit;
        }

        // Start transaction
        mysqli_begin_transaction($conn);
        try {
            // Get reservation data
            $res = mysqli_query($conn, "SELECT * FROM reservas WHERE id = $reserva_id");
            $r = mysqli_fetch_assoc($res);
            if (!$r || $r['status'] !== 'PENDENTE') {
                throw new Exception("Reserva não encontrada ou já processada.");
            }

            // 1. Create Turma
            $dias_arr = explode(',', $r['dias_semana']);
            $env_id = !empty($r['ambiente_id']) ? $r['ambiente_id'] : "NULL";
            $cur_id = !empty($r['curso_id']) ? $r['curso_id'] : "NULL";
            $props = !empty($r['numero_proposta']) ? "'".$r['numero_proposta']."'" : "NULL";
            $parc = !empty($r['parceiro']) ? "'".$r['parceiro']."'" : "NULL";
            $cont = !empty($r['contato_parceiro']) ? "'".$r['contato_parceiro']."'" : "NULL";

            $sql_turma = "INSERT INTO turma (curso_id, tipo, sigla, vagas, periodo, data_inicio, data_fim, dias_semana, ambiente_id, docente_id1, local, tipo_custeio, previsao_despesa, valor_turma, numero_proposta, tipo_atendimento, parceiro, contato_parceiro) 
                          VALUES ($cur_id, '{$r['tipo']}', '{$r['sigla']}', {$r['vagas']}, '{$r['periodo']}', '{$r['data_inicio']}', '{$r['data_fim']}', '{$r['dias_semana']}', $env_id, {$r['docente_id']}, '{$r['local']}', '{$r['tipo_custeio']}', {$r['previsao_despesa']}, {$r['valor_turma']}, $props, '{$r['tipo_atendimento']}', $parc, $cont)";
            if (!mysqli_query($conn, $sql_turma))
                throw new Exception("Erro ao criar turma: " . mysqli_error($conn) . " SQL: " . $sql_turma);
            $turma_id = mysqli_insert_id($conn);

            // 2. Clear old legacy RESERVADO rows from the agenda table in this range to avoid conflicts
            $del_p = $r['periodo'];
            $p_list = "('$del_p')";
            if ($del_p === 'Manhã' || $del_p === 'Integral')
                $p_list = "('Manhã', 'Integral')";
            if ($del_p === 'Tarde')
                $p_list = "('Tarde', 'Integral')";
            if ($del_p === 'Integral')
                $p_list = "('Manhã', 'Tarde', 'Integral')";

            mysqli_query($conn, "
                DELETE FROM agenda 
                WHERE docente_id = {$r['docente_id']} 
                  AND turma_id IS NULL AND status = 'RESERVADO' 
                  AND periodo IN $p_list
                  AND data >= '{$r['data_inicio']}' AND data <= '{$r['data_fim']}'
            ");

            // 3. Create explicit detailed Agenda Entries per day
            $daysMap = [0 => 'Domingo', 1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado'];
            $it = new DateTime($r['data_inicio']);
            $end = new DateTime($r['data_fim']);
            $dias_arr = array_map('trim', explode(',', $r['dias_semana']));
            $amb_val = $r['ambiente_id'] ?: 'NULL';

            while ($it <= $end) {
                $w = (int) $it->format('w');
                $dayName = $daysMap[$w] ?? '';
                if (in_array($dayName, $dias_arr)) {
                    $dateStr = $it->format('Y-m-d');
                    $dia_esc = mysqli_real_escape_string($conn, $dayName);

                    $sql_insert = "INSERT INTO agenda (docente_id, ambiente_id, turma_id, dia_semana, periodo, horario_inicio, horario_fim, data, status)
                                   VALUES ({$r['docente_id']}, $amb_val, $turma_id, '$dia_esc', '{$r['periodo']}', '{$r['hora_inicio']}', '{$r['hora_fim']}', '$dateStr', 'CONFIRMADO')";
                    if (!mysqli_query($conn, $sql_insert)) {
                        throw new Exception("Erro ao agendar dia $dateStr: " . mysqli_error($conn));
                    }
                }
                $it->modify('+1 day');
            }

            // 3. Update Reservation Status
            mysqli_query($conn, "UPDATE reservas SET status = 'CONCLUIDA' WHERE id = $reserva_id");

            $executor = $_SESSION['user_nome'] ?? 'Gestor';
            dispararNotificacaoGlobal($conn, 'reserva_realizada', 'Sua reserva foi Aprovada', "A reserva da turma {$r['sigla']} foi aprovada por $executor e confirmada na agenda.", "../views/gerenciar_reservas.php?status=CONCLUIDA&reserva_id=$reserva_id", ['admin', 'gestor', 'professor', 'cri']); // Goes everywhere but ignores selves mostly, users will see if it's theirs

            mysqli_commit($conn);
            echo json_encode(['success' => true, 'message' => 'Reserva aprovada e aula cadastrada!']);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;

    case 'get_reserva':
        $reserva_id = (int) ($_GET['id'] ?? 0);
        if (!$reserva_id) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            exit;
        }
        $res = mysqli_query($conn, "SELECT r.*, d.nome as docente_nome FROM reservas r LEFT JOIN docente d ON r.docente_id = d.id WHERE r.id = $reserva_id");
        $data = mysqli_fetch_assoc($res);
        if ($data) {
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['success' => false, 'message' => 'Reserva não encontrada']);
        }
        exit;

    case 'get_turma':
        $turma_id = (int) ($_GET['id'] ?? 0);
        if (!$turma_id) {
            echo json_encode(['success' => false, 'message' => 'ID inválido']);
            exit;
        }
        $res = mysqli_query($conn, "SELECT t.*, 
            d1.nome as docente1_nome, d2.nome as docente2_nome, 
            d3.nome as docente3_nome, d4.nome as docente4_nome,
            d1.nome as docente_nome 
            FROM turma t 
            LEFT JOIN docente d1 ON t.docente_id1 = d1.id
            LEFT JOIN docente d2 ON t.docente_id2 = d2.id
            LEFT JOIN docente d3 ON t.docente_id3 = d3.id
            LEFT JOIN docente d4 ON t.docente_id4 = d4.id
            WHERE t.id = $turma_id");
        $data = mysqli_fetch_assoc($res);
        if ($data) {
            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['success' => false, 'message' => 'Turma não encontrada']);
        }
        exit;

    case 'recusar_reserva':
        if (!isAdmin() && !isGestor()) {
            echo json_encode(['success' => false, 'message' => 'Sem permissão para recusar.']);
            exit;
        }
        $reserva_id = (int) ($_POST['reserva_id'] ?? 0);
        if (mysqli_query($conn, "UPDATE reservas SET status = 'RECUSADA' WHERE id = $reserva_id")) {
            $executor = $_SESSION['user_nome'] ?? 'Gestor';
            dispararNotificacaoGlobal($conn, 'reserva_realizada', 'Sua reserva foi Recusada', "A solicitação de reserva #$reserva_id foi recusada por $executor.", "../views/gerenciar_reservas.php?status=RECUSADA&reserva_id=$reserva_id", ['admin', 'gestor', 'professor', 'cri']);

            echo json_encode(['success' => true, 'message' => 'Reserva recusada.']);
        } else {
            echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
        }
        exit;

    case 'get_recent_reservations':
        $res = mysqli_query($conn, "
            SELECT r.*, d.nome as docente_nome, c.nome as curso_nome, u.nome as solicitante_nome 
            FROM reservas r
            JOIN docente d ON r.docente_id = d.id
            JOIN curso c ON r.curso_id = c.id
            JOIN usuario u ON r.usuario_id = u.id
            ORDER BY r.created_at DESC LIMIT 10
        ");
        $list = mysqli_fetch_all($res, MYSQLI_ASSOC);
        echo json_encode($list, JSON_UNESCAPED_UNICODE);
        exit;

    default:
        echo json_encode(['error' => 'Ação inválida']);
        break;
}
?>