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

// FIX: Se o período for Noite e o horário de fim for maior que 22:00, força para 22:00
// Isso garante o cumprimento da regra de que aulas noturnas encerram obrigatoriamente às 22:00.
$h_fim_check = !empty($turma['horario_fim']) ? substr($turma['horario_fim'], 0, 5) : '';
if ($turma['periodo'] === 'Noite' && $h_fim_check > '22:00') {
    $turma['horario_fim'] = '22:00:00';
    mysqli_query($conn, "UPDATE turma SET horario_fim = '22:00:00' WHERE id = '$id'");
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
    $horas_por_dia = ($t2 - $t1) / 3600;

    // Se for Integral, subtrai 1h de almoço se a duração for superior a 4h
    if ($turma['periodo'] === 'Integral' && $horas_por_dia > 4) {
        $horas_por_dia -= 1;
    }
}

// Fallback por período se o horário estiver zerado
if ($horas_por_dia <= 0) {
    $periodo = $turma['periodo'];
    if ($periodo === 'Integral') {
        $horas_por_dia = 8;
    } else {
        $horas_por_dia = 4;
    }
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
    'message' => "Turma corrigida com sucesso.",
    'sigla' => $turma['sigla'],
    'data_fim_antiga' => $turma['data_fim'],
    'data_fim_nova' => $nova_data_fim,
    'horas_por_dia' => round($horas_por_dia, 2)
]);
exit;
