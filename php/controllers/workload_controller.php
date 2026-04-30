<?php
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/utils.php';
require_once __DIR__ . '/../configs/auth.php';
requireAuth();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'get_individual') {
    $docente_id = (int)($_GET['docente_id'] ?? 0);
    if ($docente_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID de docente inválido']);
        exit;
    }

    $hoje = date('Y-m-d');
    $fim_ano = date('Y-12-31');
    $inicio_ano = date('Y-01-01');

    $total_ano = calculateTeacherYearlyWorkload($conn, $docente_id, $inicio_ano, $fim_ano);
    $saldo_remanescente = calculateTeacherYearlyWorkload($conn, $docente_id, $hoje, $fim_ano);
    $consumido = calculateConsumedHours($conn, $docente_id, $hoje, $fim_ano, false);
    $saldo_final = max(0, $saldo_remanescente - $consumido);

    echo json_encode([
        'success' => true,
        'total_ano' => round($total_ano, 1),
        'saldo_remanescente' => round($saldo_final, 1),
        'progresso' => $total_ano > 0 ? round(($saldo_final / $total_ano) * 100, 1) : 0
    ]);
    exit;
}

if ($action === 'get_global') {
    $hoje = date('Y-m-d');
    $fim_ano = date('Y-12-31');
    
    $query = "SELECT id, nome FROM docente WHERE ativo = 1";
    $res = mysqli_query($conn, $query);
    $ranking = [];

    while ($row = mysqli_fetch_assoc($res)) {
        $did = (int)$row['id'];
        $saldo = calculateTeacherYearlyWorkload($conn, $did, $hoje, $fim_ano);
        $consumido = calculateConsumedHours($conn, $did, $hoje, $fim_ano, false);
        $saldo_real = max(0, $saldo - $consumido);
        
        if ($saldo_real > 0) {
            $ranking[] = [
                'id' => $did,
                'nome' => $row['nome'],
                'saldo' => round($saldo_real, 1)
            ];
        }
    }

    // Ordenação Decrescente por saldo
    usort($ranking, function($a, $b) {
        return $b['saldo'] <=> $a['saldo'];
    });

    echo json_encode($ranking);
    exit;
}
?>
