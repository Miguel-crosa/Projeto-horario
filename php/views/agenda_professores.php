<?php
/**
 * Agenda de Professores — Timeline, Blocos, Calendário, Semestral
 * Tabelas: docente, turma(sigla), ambiente, agenda(docente_id, horario_inicio/fim), reservas(docente_id), Usuario
 */
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/auth.php';
require_once __DIR__ . '/../models/AgendaModel.php';

$agendaModel = new AgendaModel($conn);

// --- AJAX: VERIFICAÇÃO DE BLOQUEIOS POR DIA DA SEMANA ---
if (isset($_GET['ajax_weekday_check'])) {
    $prof_id = (int) $_GET['prof_id'];
    $date_start = $_GET['date_start'];
    $date_end = $_GET['date_end'];
    $hora_inicio = $_GET['hora_inicio'];
    $hora_fim = $_GET['hora_fim'];

    $st = $mysqli->prepare("\n        SELECT DAYOFWEEK(a.data) as dow, a.data, a.horario_inicio, a.horario_fim\n        FROM agenda a\n        WHERE a.docente_id = ?\n        AND a.data BETWEEN ? AND ?\n    ");
    $st->bind_param('iss', $prof_id, $date_start, $date_end);
    $st->execute();
    $results = $st->get_result()->fetch_all(MYSQLI_ASSOC);

    $date_turnos = [];
    $blocked = [];
    foreach ($results as $row) {
        $mysql_dow = (int) $row['dow'];
        if ($mysql_dow < 2)
            continue;
        $our_dow = $mysql_dow - 1;
        $hi = $row['horario_inicio'];
        $hf = $row['horario_fim'];
        $dt = $row['data'];
        if (!isset($date_turnos[$our_dow][$dt]))
            $date_turnos[$our_dow][$dt] = ['M' => false, 'T' => false, 'N' => false];
        if ($hi < '12:00:00')
            $date_turnos[$our_dow][$dt]['M'] = true;
        if ($hi < '18:00:00' && $hf > '12:00:00')
            $date_turnos[$our_dow][$dt]['T'] = true;
        if ($hf > '18:00:00' || $hi >= '18:00:00')
            $date_turnos[$our_dow][$dt]['N'] = true;
        if ($hi < $hora_fim && $hf > $hora_inicio) {
            if (!isset($blocked[$our_dow]))
                $blocked[$our_dow] = 0;
            $blocked[$our_dow]++;
        }
    }
    $turnos = [];
    foreach ($date_turnos as $dow => $dates) {
        $turnos[$dow] = ['M' => 0, 'T' => 0, 'N' => 0, 'total' => count($dates)];
        foreach ($dates as $dt => $t) {
            if ($t['M'])
                $turnos[$dow]['M']++;
            if ($t['T'])
                $turnos[$dow]['T']++;
            if ($t['N'])
                $turnos[$dow]['N']++;
        }
    }
    $total_datas_por_dow = [];
    $cur = new DateTime($date_start);
    $end = new DateTime($date_end);
    $end->modify('+1 day');
    while ($cur < $end) {
        $mysql_dow = (int) $cur->format('w') + 1;
        if ($mysql_dow >= 2) {
            $our_dow = $mysql_dow - 1;
            if (!isset($total_datas_por_dow[$our_dow]))
                $total_datas_por_dow[$our_dow] = 0;
            $total_datas_por_dow[$our_dow]++;
        }
        $cur->modify('+1 day');
    }
    header('Content-Type: application/json');
    echo json_encode(['blocked' => $blocked, 'turnos' => $turnos, 'total_datas_por_dow' => $total_datas_por_dow]);
    exit;
}

// --- AJAX: LISTA DE PROFESSORES POR ÁREA DE CONHECIMENTO ---
if (isset($_GET['ajax_profs_by_specialty'])) {
    $especialidade = $_GET['especialidade'];
    $st = $mysqli->prepare("SELECT id, nome FROM docente WHERE area_conhecimento = ? ORDER BY nome ASC");
    $st->bind_param('s', $especialidade);
    $st->execute();
    header('Content-Type: application/json');
    echo json_encode($st->get_result()->fetch_all(MYSQLI_ASSOC));
    exit;
}

// --- AJAX: VERIFICAÇÃO DE RESERVAS ---
if (isset($_GET['ajax_reservas_check'])) {
    $prof_id = (int) $_GET['prof_id'];
    $month = $_GET['month'];
    $f_day = $month . '-01';
    $l_day = date('Y-m-t', strtotime($f_day));
    $st = $mysqli->prepare("\n        SELECT r.*, u.nome as gestor_nome\n        FROM reservas r JOIN usuario u ON r.usuario_id = u.id\n        WHERE r.docente_id = ? AND r.status = 'ativo'\n        AND r.data_inicio <= ? AND r.data_fim >= ?\n    ");
    $st->bind_param('iss', $prof_id, $l_day, $f_day);
    $st->execute();
    $reservas = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $reserved_dates = [];
    foreach ($reservas as $r) {
        $dias_arr = explode(',', $r['dias_semana']);
        $cur = new DateTime(max($r['data_inicio'], $f_day));
        $end = new DateTime(min($r['data_fim'], $l_day));
        $end->modify('+1 day');
        while ($cur < $end) {
            $dow = $cur->format('N');
            if (in_array($dow, $dias_arr)) {
                $d = $cur->format('Y-m-d');
                $reserved_dates[$d] = [
                    'reserva_id' => $r['id'],
                    'gestor' => $r['gestor_nome'],
                    'hora_inicio' => $r['hora_inicio'],
                    'hora_fim' => $r['hora_fim'],
                    'own' => ($r['usuario_id'] == $auth_user_id),
                    'notas' => $r['notas']
                ];
            }
            $cur->modify('+1 day');
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'reserved' => $reserved_dates]);
    exit;
}

// --- AJAX: CRIAR NOVA RESERVA ---
if (isset($_POST['ajax_create_reserva'])) {
    header('Content-Type: application/json');
    if (!can_reserve()) {
        echo json_encode(['ok' => false, 'error' => 'Sem permissão.']);
        exit;
    }
    $docente_id_r = (int) $_POST['professor_id'];
    $data_inicio = $_POST['data_inicio'];
    $data_fim = $_POST['data_fim'];
    $dias_semana = $_POST['dias_semana'];
    $hora_inicio = $_POST['hora_inicio'];
    $hora_fim = $_POST['hora_fim'];
    $notas = $_POST['notas'] ?? '';
    // Novos campos
    $numero_proposta = $_POST['numero_proposta'] ?? '';
    $tipo_atendimento = $_POST['tipo_atendimento'] ?? 'Balcão';
    $parceiro = $_POST['parceiro'] ?? '';
    $contato_parceiro = $_POST['contato_parceiro'] ?? '';
    $tipo_custeio = $_POST['tipo_custeio'] ?? 'Gratuidade';
    $previsao_despesa = (float)($_POST['previsao_despesa'] ?? 0);
    $valor_turma = (float)($_POST['valor_turma'] ?? 0);

    $usuario_id = $auth_user_id;
    if (!$docente_id_r || !$data_inicio || !$data_fim || !$dias_semana || !$hora_inicio || !$hora_fim) {
        echo json_encode(['ok' => false, 'error' => 'Dados incompletos.']);
        exit;
    }
    $dias_arr = explode(',', $dias_semana);
    $st = $mysqli->prepare("\n        SELECT r.*, u.nome as gestor_nome FROM reservas r\n        JOIN usuario u ON r.usuario_id = u.id\n        WHERE r.docente_id = ? AND r.status = 'ativo' AND r.usuario_id != ?\n        AND r.data_inicio <= ? AND r.data_fim >= ?\n        AND (r.hora_inicio < ? AND r.hora_fim > ?)\n    ");
    $st->bind_param('iissss', $docente_id_r, $usuario_id, $data_fim, $data_inicio, $hora_fim, $hora_inicio);
    $st->execute();
    $conflicts = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($conflicts as $c) {
        $c_dias = explode(',', $c['dias_semana']);
        $overlap = array_intersect($dias_arr, $c_dias);
        if (!empty($overlap)) {
            $dow_names = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb'];
            $names = array_map(function ($d) use ($dow_names) {
                return $dow_names[$d] ?? $d;
            }, $overlap);
            echo json_encode(['ok' => false, 'error' => "Conflito: \"{$c['gestor_nome']}\" já reservou em " . implode(', ', $names)]);
            exit;
        }
    }
    $st2 = $mysqli->prepare("INSERT INTO reservas (docente_id, usuario_id, data_inicio, data_fim, dias_semana, hora_inicio, hora_fim, notas, numero_proposta, tipo_atendimento, parceiro, contato_parceiro, tipo_custeio, previsao_despesa, valor_turma) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $st2->bind_param('iisssssssssssdd', $docente_id_r, $usuario_id, $data_inicio, $data_fim, $dias_semana, $hora_inicio, $hora_fim, $notas, $numero_proposta, $tipo_atendimento, $parceiro, $contato_parceiro, $tipo_custeio, $previsao_despesa, $valor_turma);
    $st2->execute();
    echo json_encode(['ok' => true, 'id' => $mysqli->insert_id, 'msg' => 'Professor reservado com sucesso!']);
    exit;
}

// --- AJAX: DADOS PARA TIMELINE/CALENDÁRIO ---
if (isset($_GET['ajax_availability'])) {
    $prof_id = (int) $_GET['prof_id'];
    $month = $_GET['month'];
    $f_day = $month . '-01';
    $l_day = date('Y-m-t', strtotime($f_day));
    $st = $mysqli->prepare("\n        SELECT a.data, t.sigla as turma_nome, a.horario_inicio, a.horario_fim\n        FROM agenda a JOIN turma t ON a.turma_id = t.id\n        WHERE a.docente_id = ? AND a.data BETWEEN ? AND ?\n    ");
    $st->bind_param('iss', $prof_id, $f_day, $l_day);
    $st->execute();
    $results = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $busy = [];
    $turnos = [];
    foreach ($results as $row) {
        $busy[$row['data']] = $row['turma_nome'];
        if (!isset($turnos[$row['data']]))
            $turnos[$row['data']] = ['M' => false, 'T' => false, 'N' => false];
        $hi = $row['horario_inicio'];
        $hf = $row['horario_fim'];
        if ($hi < '12:00:00')
            $turnos[$row['data']]['M'] = true;
        if ($hi < '18:00:00' && $hf > '12:00:00')
            $turnos[$row['data']]['T'] = true;
        if ($hf > '18:00:00' || $hi >= '18:00:00')
            $turnos[$row['data']]['N'] = true;
    }
    $st_res = $mysqli->prepare("\n        SELECT r.*, u.nome as gestor_nome FROM reservas r\n        JOIN usuario u ON r.usuario_id = u.id\n        WHERE r.docente_id = ? AND r.status = 'ativo'\n        AND r.data_inicio <= ? AND r.data_fim >= ?\n    ");
    $st_res->bind_param('iss', $prof_id, $l_day, $f_day);
    $st_res->execute();
    $res_rows = $st_res->get_result()->fetch_all(MYSQLI_ASSOC);
    $reserved = [];
    foreach ($res_rows as $rr) {
        $dias_arr = explode(',', $rr['dias_semana']);
        $cur = new DateTime(max($rr['data_inicio'], $f_day));
        $end = new DateTime(min($rr['data_fim'], $l_day));
        $end->modify('+1 day');
        while ($cur < $end) {
            $dow = $cur->format('N');
            if (in_array($dow, $dias_arr)) {
                $d = $cur->format('Y-m-d');
                $reserved[$d] = ['gestor' => $rr['gestor_nome'], 'own' => ($rr['usuario_id'] == $auth_user_id), 'hora' => $rr['hora_inicio'] . '-' . $rr['hora_fim']];
                
                if (!isset($turnos[$d])) $turnos[$d] = ['M' => false, 'T' => false, 'N' => false];
                $hi = $rr['hora_inicio'];
                $hf = $rr['hora_fim'];
                if ($hi < '12:00:00') $turnos[$d]['M'] = true;
                if ($hi < '18:00:00' && $hf > '12:00:00') $turnos[$d]['T'] = true;
                if ($hf > '18:00:00' || $hi >= '18:00:00') $turnos[$d]['N'] = true;
            }
            $cur->modify('+1 day');
        }
    }
    // Adiciona Pré-para-ção / Atestados
    $st_prep = $mysqli->prepare("SELECT * FROM preparacao_atestados WHERE docente_id = ? AND data_inicio <= ? AND data_fim >= ?");
    $st_prep->bind_param('iss', $prof_id, $l_day, $f_day);
    $st_prep->execute();
    $prep_rows = $st_prep->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($prep_rows as $pr) {
        $cur = new DateTime(max($pr['data_inicio'], $f_day));
        $end = new DateTime(min($pr['data_fim'], $l_day));
        $end->modify('+1 day');
        while ($cur < $end) {
            $d = $cur->format('Y-m-d');
            $dow = $cur->format('N'); // 1 (Seg) a 7 (Dom)
            $dias_permitidos = !empty($pr['dias_semana']) ? explode(',', $pr['dias_semana']) : [];

            // Se o tipo for preparação e houver dias selecionados, verifica se o dia atual está na lista
            if ($pr['tipo'] === 'preparação' && !empty($dias_permitidos)) {
                if (!in_array($dow, $dias_permitidos)) {
                    $cur->modify('+1 day');
                    continue;
                }
            }

            $label = ($pr['tipo'] === 'atestado' ? 'Atestado' : 'Preparação');
            $busy[$d] = $label;
            if (!isset($turnos[$d]))
                $turnos[$d] = ['M' => false, 'T' => false, 'N' => false];
            $hi = $pr['horario_inicio'];
            $hf = $pr['horario_fim'];
            if (!$hi || !$hf || ($hi == '00:00:00' && $hf == '23:59:59')) {
                $turnos[$d]['M'] = $turnos[$d]['T'] = $turnos[$d]['N'] = true;
            } else {
                if ($hi < '12:00:00')
                    $turnos[$d]['M'] = true;
                if ($hi < '18:00:00' && $hf > '12:00:00')
                    $turnos[$d]['T'] = true;
                if ($hf > '18:00:00' || $hi >= '18:00:00')
                    $turnos[$d]['N'] = true;
            }
            $cur->modify('+1 day');
        }
    }

    header('Content-Type: application/json');
    echo json_encode(['busy' => $busy, 'turnos' => $turnos, 'reserved' => $reserved]);
    exit;
}

include __DIR__ . '/../components/header.php';

// Filtros
$search_name = isset($_GET['search']) ? $_GET['search'] : '';
$filter_especialidade = isset($_GET['especialidade']) ? $_GET['especialidade'] : '';
$ordem_disp = isset($_GET['ordem_disp']) ? $_GET['ordem_disp'] : 'mais';
$view_mode = isset($_GET['view_mode']) ? $_GET['view_mode'] : 'timeline';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = in_array($view_mode, ['calendar', 'grafico_agenda']) ? 1 : 10;
$offset = ($page - 1) * $limit;

$current_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$view_mode = isset($_GET['view_mode']) ? $_GET['view_mode'] : 'timeline';

if ($view_mode === 'timeline') {
    // Intervalo anual para o modo timeline
    $current_year = date('Y', strtotime($current_month . '-01'));
    $first_day = "$current_year-01-01";
    $last_day = "$current_year-12-31";
} else {
    // Intervalo do mês atual para os outros modos
    $first_day = date('Y-m-01', strtotime($current_month));
    $last_day = date('Y-m-t', strtotime($current_month));
}
$days_in_month = date('t', strtotime($current_month));

$especialidades_lista = ['TI / Software', 'Mecatrônica / Automação', 'Metalmecânica', 'Logística', 'Eletroeletrônica', 'Gestão / Qualidade', 'Alimentos'];
$especialidades = array_map(function ($e) {
    return ['area_conhecimento' => $e];
}, $especialidades_lista);

$where_search = $search_name ? "AND p.nome LIKE ?" : "";
$where_especialidade = $filter_especialidade ? "AND p.area_conhecimento = ?" : "";
$params_search = $search_name ? ["%$search_name%"] : [];

$filter_docente = isset($_GET['docente_id']) && (int) $_GET['docente_id'] > 0 ? (int) $_GET['docente_id'] : 0;
$where_docente = $filter_docente ? "AND p.id = ?" : "";

$filter_periodo = isset($_GET['periodo']) ? $_GET['periodo'] : '';
$where_periodo = $filter_periodo ? "AND a.periodo = ?" : "";

$query_base = "
    FROM docente p
    LEFT JOIN (
        SELECT COUNT(*) as total_aulas, docente_id as pid 
        FROM agenda 
        WHERE data BETWEEN '$first_day' AND '$last_day' 
        " . ($filter_periodo ? "AND periodo = '$filter_periodo'" : "") . "
        GROUP BY docente_id
    ) a ON p.id = a.pid
    WHERE 1=1 $where_search $where_especialidade $where_docente
";

$bind_types_count = '';
$bind_vals_count = [];
if ($search_name) {
    $bind_types_count .= 's';
    $bind_vals_count[] = $params_search[0];
}
if ($filter_especialidade) {
    $bind_types_count .= 's';
    $bind_vals_count[] = $filter_especialidade;
}
if ($filter_docente) {
    $bind_types_count .= 'i';
    $bind_vals_count[] = $filter_docente;
}

$total_profs = $mysqli->prepare("SELECT COUNT(*) $query_base");
if (!empty($bind_vals_count))
    $total_profs->bind_param($bind_types_count, ...$bind_vals_count);
$total_profs->execute();
$total_count = $total_profs->get_result()->fetch_row()[0];

$sort_sql = $ordem_disp == 'mais' ? "ORDER BY COALESCE(a.total_aulas, 0) ASC" : "ORDER BY COALESCE(a.total_aulas, 0) DESC";

$stmt_profs = $mysqli->prepare("SELECT p.*, COALESCE(a.total_aulas, 0) as total_aulas $query_base $sort_sql LIMIT $limit OFFSET $offset");
if (!empty($bind_vals_count))
    $stmt_profs->bind_param($bind_types_count, ...$bind_vals_count);
$stmt_profs->execute();
$professores = $stmt_profs->get_result()->fetch_all(MYSQLI_ASSOC);

$prof_ids = array_column($professores, 'id');
$agenda_data = [];
$turno_detail = [];
$turno_summary = [];
$reserva_data = [];

if (!empty($prof_ids)) {
    $filters = [];
    if (!empty($filter_periodo) && $filter_periodo !== 'Todos') {
        $filters['periodo'] = $filter_periodo;
    }

    $unified_expanded = $agendaModel->getExpandedAgenda($prof_ids, $first_day, $last_day, $filters);

    $work_schedules = []; // track registered work hours per pid and data

    foreach ($unified_expanded as $row) {
        $pid = $row['docente_id'];
        $dt = $row['agenda_data'];

        if ($row['type'] === 'WORK_SCHEDULE') {
            $work_schedules[$pid][$dt][$row['periodo']] = true;
            continue;
        }

        $hi = $row['horario_inicio'];
        $hf = $row['horario_fim'];

        if ($row['type'] === 'AULA' || $row['type'] === 'PREPARACAO') {
            $label = ($row['type'] === 'AULA') ? (!empty($row['turma_nome']) ? $row['turma_nome'] : ($row['curso_nome'] ?: 'Aula')) : ($row['tipo'] === 'atestado' ? 'Atestado' : 'Preparação');
            $agenda_data[$pid][$dt] = $label;

            if (!isset($turno_detail[$pid][$dt])) {
                $turno_detail[$pid][$dt] = ['M' => false, 'T' => false, 'N' => false, 'I' => false];
            }
            if (!isset($turno_summary[$pid])) {
                $turno_summary[$pid] = ['M' => 0, 'T' => 0, 'N' => 0];
            }

            if ($hi < '12:00:00') {
                if (!$turno_detail[$pid][$dt]['M'])
                    $turno_summary[$pid]['M']++;
                $turno_detail[$pid][$dt]['M'] = $label;
            }
            if ($hi < '18:00:00' && $hf > '12:00:00') {
                if (!$turno_detail[$pid][$dt]['T'])
                    $turno_summary[$pid]['T']++;
                $turno_detail[$pid][$dt]['T'] = $label;
            }
            if ($hf > '18:00:00' || $hi >= '18:00:00') {
                if (!$turno_detail[$pid][$dt]['N'])
                    $turno_summary[$pid]['N']++;
                $turno_detail[$pid][$dt]['N'] = $label;
            }
            // Mapeamento explícito para o período 'Integral'
            if (isset($row['periodo']) && $row['periodo'] === 'Integral') {
                if (!$turno_detail[$pid][$dt]['M']) {
                    $turno_summary[$pid]['M']++;
                    $turno_detail[$pid][$dt]['M'] = $label;
                }
                if (!$turno_detail[$pid][$dt]['T']) {
                    $turno_summary[$pid]['T']++;
                    $turno_detail[$pid][$dt]['T'] = $label;
                }
            }
        } else {
            $lbl_gestor = $row['gestor_nome'] ?? 'Bloqueio Automático';
            if ($row['type'] === 'FERIADO')
                $lbl_gestor = 'Feriado: ' . ($row['name'] ?? '');
            if ($row['type'] === 'FERIAS')
                $lbl_gestor = 'Férias / Fechamento';

            $reserva_data[$pid][$dt] = [
                'gestor' => $lbl_gestor,
                'own' => (isset($row['usuario_id']) && $row['usuario_id'] == $auth_user_id),
                'hora' => ($row['horario_inicio'] ?? '00:00:00') . '-' . ($row['horario_fim'] ?? '23:59:59'),
                'notas' => $row['notas'] ?? 'Bloqueio Automático',
                'tipo_bloqueio' => ($row['type'] === 'FERIADO') ? 'FERIADO' : (($row['type'] === 'FERIAS') ? 'FERIAS' : null)
            ];

            // NOVO: Adiciona ao detalhamento de turnos para renderização precisa
            if (!isset($turno_detail[$pid][$dt])) {
                $turno_detail[$pid][$dt] = ['M' => false, 'T' => false, 'N' => false, 'I' => false];
            }
            if ($hi < '12:00:00') {
                $turno_detail[$pid][$dt]['M'] = 'RESERVADO';
            }
            if ($hi < '18:00:00' && $hf > '12:00:00') {
                $turno_detail[$pid][$dt]['T'] = 'RESERVADO';
            }
            if ($hf > '18:00:00' || $hi >= '18:00:00') {
                $turno_detail[$pid][$dt]['N'] = 'RESERVADO';
            }
        }
    }

    // Now, for each day and each professor, if they have NO work schedules registered for a period (M, T, N), 
    // mark it as OFF_SCHEDULE so the frontend can grey it out.
    foreach ($professores as $p) {
        $pid = $p['id'];

        $start_ptr = new DateTime($first_day);
        $end_ptr = new DateTime($last_day);
        while ($start_ptr <= $end_ptr) {
            $dt = $start_ptr->format('Y-m-d');
            $dow = $start_ptr->format('N');

            // If it's Sunday, it's always blocked (existing logic)
            if ($dow == 7) {
                $start_ptr->modify('+1 day');
                continue;
            }

            foreach (['Manhã' => 'M', 'Tarde' => 'T', 'Noite' => 'N'] as $p_full => $p_key) {
                $is_authorized = isset($work_schedules[$pid][$dt][$p_full]) || isset($work_schedules[$pid][$dt]['Integral']);

                if (!$is_authorized) {
                    if (!isset($turno_detail[$pid][$dt])) {
                        $turno_detail[$pid][$dt] = ['M' => false, 'T' => false, 'N' => false, 'I' => false];
                    }
                    // Only mark as OFF_SCHEDULE if it's not already occupied (AULA/PREPARACAO)
                    if ($turno_detail[$pid][$dt][$p_key] === false) {
                        $turno_detail[$pid][$dt][$p_key] = 'OFF_SCHEDULE';
                    }
                }
            }
            $start_ptr->modify('+1 day');
        }
    }

}

$turmas_select = $mysqli->query("SELECT t.id, t.sigla as nome, c.nome as curso_nome FROM turma t JOIN curso c ON t.curso_id = c.id ORDER BY t.sigla ASC")->fetch_all(MYSQLI_ASSOC);
$salas_select = $mysqli->query("SELECT id, nome FROM ambiente ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);
$all_profs = $mysqli->query("SELECT id, nome, area_conhecimento as especialidade FROM docente ORDER BY nome ASC")->fetch_all(MYSQLI_ASSOC);
$all_especialidades_modal = $mysqli->query("SELECT DISTINCT area_conhecimento as especialidade FROM docente WHERE area_conhecimento IS NOT NULL AND area_conhecimento != '' ORDER BY area_conhecimento ASC")->fetch_all(MYSQLI_ASSOC);

$can_reserve = can_reserve();

// Verifica o nível de permissão; se for gestor/admin, exibe a lista completa de profissionais.
$is_prof = false;
$logged_docente_id = getUserDocenteId();
if ($is_prof && $logged_docente_id) {
    $docentes = mysqli_fetch_all(mysqli_query($conn, "SELECT id, nome, area_conhecimento FROM docente WHERE id = $logged_docente_id"), MYSQLI_ASSOC);
} else {
    $docentes = mysqli_fetch_all(mysqli_query($conn, "SELECT id, nome, area_conhecimento FROM docente ORDER BY area_conhecimento ASC, nome ASC"), MYSQLI_ASSOC);
}
$cursos = mysqli_fetch_all(mysqli_query($conn, "SELECT id, nome, area, tipo FROM curso ORDER BY area ASC, nome ASC"), MYSQLI_ASSOC);
$ambientes = mysqli_fetch_all(mysqli_query($conn, "SELECT id, nome, tipo FROM ambiente ORDER BY tipo ASC, nome ASC"), MYSQLI_ASSOC);
$grouped = [];
foreach ($docentes as $d) {
    $area = $d['area_conhecimento'] ?: 'Outros';
    $grouped[$area][] = $d;
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
    $gantt_docentes[] = ['id' => $d['id'], 'nome' => $d['nome'], 'alocacoes' => $alocacoes];
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
$docentes_json = json_encode($docentes, JSON_UNESCAPED_UNICODE);

$prev_month = date('Y-m', strtotime($current_month . '-01 -1 month'));
$next_month = date('Y-m', strtotime($current_month . '-01 +1 month'));
$months_pt = [1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'];
$m_num = (int) date('m', strtotime($current_month . '-01'));
$m_year = date('Y', strtotime($current_month . '-01'));
$month_label = $months_pt[$m_num] . ' ' . $m_year;
if ($view_mode == 'semestral') {
    $prev_month = date('Y-m', strtotime($current_month . '-01 -6 months'));
    $next_month = date('Y-m', strtotime($current_month . '-01 +6 months'));
    $sem_num = ($m_num <= 6) ? 1 : 2;
    $month_label = $sem_num . 'º Semestre ' . $m_year . ($sem_num == 1 ? ' (Jan–Jun)' : ' (Jul–Dez)');
}

include __DIR__ . '/agenda_professores_template.php';
?>