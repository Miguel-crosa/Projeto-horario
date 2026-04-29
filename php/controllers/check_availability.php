<?php
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/utils.php';

header('Content-Type: application/json');

$docente_id = isset($_GET['docente_id']) ? (int) $_GET['docente_id'] : 0;
$data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-d');
$data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d');

if (!$docente_id) {
    echo json_encode(['error' => 'Missing docente_id']);
    exit;
}

// 1. Obter limites e informações do docente
$res_doc = mysqli_query($conn, "SELECT nome, weekly_hours_limit, monthly_hours_limit FROM docente WHERE id = $docente_id");
$doc = mysqli_fetch_assoc($res_doc);

$daysMap = [
    0 => 'Domingo',
    1 => 'Segunda-feira',
    2 => 'Terça-feira',
    3 => 'Quarta-feira',
    4 => 'Quinta-feira',
    5 => 'Sexta-feira',
    6 => 'Sábado'
];

// Fetch all agendas for this teacher in the given range
$res = mysqli_query($conn, "
    SELECT a.horario_inicio, a.horario_fim, a.dia_semana, t.data_inicio, t.data_fim
    FROM agenda a
    JOIN turma t ON a.turma_id = t.id
    WHERE a.docente_id = $docente_id
      AND t.data_inicio <= '$data_fim'
      AND t.data_fim >= '$data_inicio'
");

$agendas = mysqli_fetch_all($res, MYSQLI_ASSOC);

function isBusy($agendas, $target_start, $target_end, $target_day = null)
{
    foreach ($agendas as $ag) {
        if ($target_day && $ag['dia_semana'] !== $target_day)
            continue;

        $s = $ag['horario_inicio'];
        $e = $ag['horario_fim'];
        // Check for time overlap
        if (!($target_end <= $s || $target_start >= $e)) {
            return true;
        }
    }
    return false;
}

$periods = [
    'Manhã' => ['start' => '07:30', 'end' => '11:30'],
    'Tarde' => ['start' => '13:30', 'end' => '17:30'],
    'Noite' => ['start' => '19:30', 'end' => '23:30']
];

$results = [];

// Adicionar limites ao retorno
$results['weekly_limit'] = (float)($doc['weekly_hours_limit'] ?? 0);
$results['monthly_limit'] = (float)($doc['monthly_hours_limit'] ?? 0);

// Calcular carga semanal atual (ISO-8601 Week)
$dt = new DateTime($data_inicio);
$week_start = clone $dt;
$week_start->modify('Monday this week');
$week_end = clone $week_start;
$week_end->modify('+6 days');

$results['current_weekly_hours'] = calculateConsumedHours($conn, $docente_id, $week_start->format('Y-m-d'), $week_end->format('Y-m-d'));

// Calcular carga mensal atual
$month_start = $dt->format('Y-m-01');
$month_end = $dt->format('Y-m-t');
$results['current_monthly_hours'] = calculateConsumedHours($conn, $docente_id, $month_start, $month_end);

foreach ($periods as $name => $times) {
    $is_busy_any_day = false;
    $itTemp = new DateTime($data_inicio);
    $itTemp->setTime(0, 0, 0); // FIX
    $itEndTemp = new DateTime($data_fim);
    $itEndTemp->setTime(0, 0, 0); // FIX
    while ($itTemp->format('Y-m-d') <= $itEndTemp->format('Y-m-d')) { // FIX: comparação por string
        if (isBusy($agendas, $times['start'], $times['end'], $daysMap[(int) $itTemp->format('w')])) {
            $is_busy_any_day = true;
            break;
        }
        $itTemp->modify('+1 day');
    }
    $results['periods'][$name] = $is_busy_any_day ? 'busy' : 'free';
}

$h_start = isset($_GET['h_start']) ? $_GET['h_start'] : $periods['Manhã']['start'];
$h_end = isset($_GET['h_end']) ? $_GET['h_end'] : $periods['Manhã']['end'];

$busy_days = [];
$it = new DateTime($data_inicio);
$it->setTime(0, 0, 0); // FIX
$itEnd = new DateTime($data_fim);
$itEnd->setTime(0, 0, 0); // FIX
while ($it->format('Y-m-d') <= $itEnd->format('Y-m-d')) { // FIX: comparação por string
    $w = (int) $it->format('w');
    if (isBusy($agendas, $h_start, $h_end, $daysMap[$w])) {
        $busy_days[$daysMap[$w]] = true;
    }
    $it->modify('+1 day');
}

$results['busy_days'] = array_keys($busy_days);

echo json_encode($results);
?>