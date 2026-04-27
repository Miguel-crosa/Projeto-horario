<?php
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/auth.php';
require_once __DIR__ . '/../configs/utils.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'Requisição inválida.']);
    exit;
}

$id = mysqli_real_escape_string($conn, $_POST['id']);

// 1. Busca os detalhes da turma e do curso vinculado
$query = "SELECT t.*, c.carga_horaria_total 
          FROM turma t 
          JOIN curso c ON t.curso_id = c.id 
          WHERE t.id = '$id'";
$res = mysqli_query($conn, $query);
$turma = mysqli_fetch_assoc($res);

if (!$turma) {
    echo json_encode(['success' => false, 'message' => "Turma #$id não encontrada."]);
    exit;
}

$ajuste_inteligente = false;
$msg_ajuste = "";

// --- LÓGICA INTELIGENTE: Detectar data_inicio incorreta (Bug de 1 dia na importação) ---
$dias_semana_arr = !empty($turma['dias_semana']) ? explode(',', $turma['dias_semana']) : [];
if (!empty($dias_semana_arr)) {
    $daysMap = [0 => 'Domingo', 1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado'];
    
    $di_atual = $turma['data_inicio'];
    $w_atual = (int)date('w', strtotime($di_atual));
    $nome_dia_atual = $daysMap[$w_atual];
    
    $is_holiday_atual = isHoliday($conn, $di_atual);
    $is_class_day_atual = in_array($nome_dia_atual, $dias_semana_arr);

    // Se NÃO é dia de aula OU é feriado
    if (!$is_class_day_atual || $is_holiday_atual) {
        $di_seguinte = date('Y-m-d', strtotime($di_atual . ' +1 day'));
        $w_seguinte = (int)date('w', strtotime($di_seguinte));
        $nome_dia_seguinte = $daysMap[$w_seguinte];
        
        $is_holiday_seguinte = isHoliday($conn, $di_seguinte);
        $is_class_day_seguinte = in_array($nome_dia_seguinte, $dias_semana_arr);

        // Se o dia seguinte É dia de aula E NÃO é feriado
        if ($is_class_day_seguinte && !$is_holiday_seguinte) {
            $turma['data_inicio'] = $di_seguinte;
            mysqli_query($conn, "UPDATE turma SET data_inicio = '$di_seguinte' WHERE id = '$id'");
            $ajuste_inteligente = true;
            $msg_ajuste = " (Início ajustado de " . date('d/m', strtotime($di_atual)) . " para " . date('d/m', strtotime($di_seguinte)) . ")";
        }
    }
}
// --------------------------------------------------------------------------------------

// FIX: Se o período for Noite e o horário de fim for maior que 23:00, força para 23:00
// Isso garante o cumprimento da regra de que aulas noturnas encerram obrigatoriamente às 23:00.
$h_fim_check = !empty($turma['horario_fim']) ? substr($turma['horario_fim'], 0, 5) : '';
if ($turma['periodo'] === 'Noite' && $h_fim_check > '23:00') {
    $turma['horario_fim'] = '23:00:00';
    mysqli_query($conn, "UPDATE turma SET horario_fim = '23:00:00' WHERE id = '$id'");
}

// FIX: Se o período for Integral e o horário de fim for maior que 17:30, força para 17:30
if ($turma['periodo'] === 'Integral' && $h_fim_check > '17:30') {
    $turma['horario_fim'] = '17:30:00';
    mysqli_query($conn, "UPDATE turma SET horario_fim = '17:30:00' WHERE id = '$id'");
}

// 2. Calcula horas por dia
$h_ini = $turma['horario_inicio'];
$h_fim = $turma['horario_fim'];
$horas_por_dia = 0;

if ($h_ini && $h_fim) {
    $t1 = strtotime($h_ini);
    $t2 = strtotime($h_fim);
    $raw_h = ($t2 - $t1) / 3600;

    if ($turma['periodo'] === 'Integral') {
        if ($raw_h > 4) $raw_h -= 2; // Almoço (11:30 - 13:30)
        $horas_por_dia = min(8, $raw_h);
    } else {
        $horas_por_dia = min(4, $raw_h);
    }
}

// Fallback por período se o horário estiver zerado
if ($horas_por_dia <= 0) {
    $periodo = $turma['periodo'];
    $horas_por_dia = ($periodo === 'Integral' ? 8 : 4);
}

// 3. Prepara dados para o cálculo
$dias_semana_arr = !empty($turma['dias_semana']) ? explode(',', $turma['dias_semana']) : [];
$docentes_ids = array_filter([
    $turma['docente_id1'],
    $turma['docente_id2'],
    $turma['docente_id3'],
    $turma['docente_id4']
]);

// 4. Calcula a nova Data Fim (usando a função robusta do utils.php)
// A função calculateEndDate já pula feriados e férias (se todos os docentes estiverem de férias)
$nova_data_fim = calculateEndDate(
    $conn, 
    $turma['data_inicio'], 
    $turma['carga_horaria_total'], 
    $horas_por_dia, 
    $dias_semana_arr, 
    $docentes_ids
);

// 5. Atualiza a turma com o novo cálculo
$update_query = "UPDATE turma SET data_fim = '$nova_data_fim' WHERE id = '$id'";
mysqli_query($conn, $update_query);

// 6. Regenera a Agenda
// Remove registros antigos da agenda desta turma
mysqli_query($conn, "DELETE FROM agenda WHERE turma_id = '$id'");

// Cria novos registros (generateAgendaRecords também pula feriados/férias)
generateAgendaRecords(
    $conn,
    $id,
    $dias_semana_arr,
    $turma['periodo'],
    $turma['horario_inicio'],
    $turma['horario_fim'],
    $turma['data_inicio'],
    $nova_data_fim,
    $turma['ambiente_id'],
    $docentes_ids
);

echo json_encode([
    'success' => true,
    'message' => "Turma corrigida com sucesso." . $msg_ajuste,
    'sigla' => $turma['sigla'],
    'data_fim_antiga' => $turma['data_fim'],
    'data_fim_nova' => $nova_data_fim,
    'horas_por_dia' => round($horas_por_dia, 2)
]);
exit;
