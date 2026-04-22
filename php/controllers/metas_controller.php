<?php
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/auth.php';
header('Content-Type: application/json');
error_reporting(E_ALL); 
ini_set('display_errors', 0);

// Captura qualquer saída inesperada (como avisos)
ob_start();

try {
    if (empty($_SESSION['user_id'])) {
        ob_clean();
        echo json_encode(['error' => 'Sessão expirada', 'auth_error' => true]);
        exit;
    }


$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'get_metas') {
    $ano = (int)($_GET['ano'] ?? date('Y'));
    $stmt = $conn->prepare("SELECT * FROM metas_ah WHERE ano = ?");
    $stmt->bind_param("i", $ano);
    $stmt->execute();
    $result = $stmt->get_result();
    $meta = $result->fetch_assoc();
    
    echo json_encode($meta);
    exit;
}

if ($action === 'save_metas') {
    $ano = (int)($_POST['ano'] ?? date('Y'));
    $cai_h = (int)($_POST['cai_horas'] ?? 0);
    $cai_a = (int)($_POST['cai_alunos'] ?? 0);
    $ct_h = (int)($_POST['ct_horas'] ?? 0);
    $ct_a = (int)($_POST['ct_alunos'] ?? 0);
    $fic_h = (int)($_POST['fic_horas'] ?? 0);
    $fic_a = (int)($_POST['fic_alunos'] ?? 0);
    $despesa = (float)($_POST['despesa_anual'] ?? 0);

    $stmt = $conn->prepare("INSERT INTO metas_ah (ano, cai_horas, cai_alunos, ct_horas, ct_alunos, fic_horas, fic_alunos, despesa_anual) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
        cai_horas = VALUES(cai_horas), 
        cai_alunos = VALUES(cai_alunos), 
        ct_horas = VALUES(ct_horas), 
        ct_alunos = VALUES(ct_alunos), 
        fic_horas = VALUES(fic_horas), 
        fic_alunos = VALUES(fic_alunos), 
        despesa_anual = VALUES(despesa_anual)");
    
    $stmt->bind_param("iiiiiiid", $ano, $cai_h, $cai_a, $ct_h, $ct_a, $fic_h, $fic_a, $despesa);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }
    exit;
}

if ($action === 'get_real_production') {
    $ano = (int)($_GET['ano'] ?? date('Y'));
    
    // Produção Real por Tipo de Curso
    $query = "
        SELECT 
            t.tipo,
            SUM(t.vagas * c.carga_horaria_total) as producao
        FROM turma t
        JOIN curso c ON t.curso_id = c.id
        WHERE t.ativo = 1 
        AND t.tipo_custeio = 'Gratuidade'
        AND EXISTS (SELECT 1 FROM agenda a WHERE a.turma_id = t.id)
        AND YEAR(t.data_fim) = ?
        GROUP BY t.tipo
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $ano);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $production = [
        'CAI' => 0,
        'CT' => 0,
        'FIC' => 0,
        'Total' => 0
    ];
    
    while ($row = $result->fetch_assoc()) {
        $tipo = strtoupper($row['tipo'] ?? 'FIC');
        $prod = (float)$row['producao'];
        
        if (strpos($tipo, 'CAI') !== false) {
            $production['CAI'] += $prod;
        } elseif (strpos($tipo, 'CT') !== false || strpos($tipo, 'TECNICO') !== false || strpos($tipo, 'TÉCNICO') !== false) {
            $production['CT'] += $prod;
        } else {
            $production['FIC'] += $prod;
        }
    }
    
    // Força o total a ser a soma exata das partes para evitar divergências no dashboard
    $production['Total'] = $production['CAI'] + $production['CT'] + $production['FIC'];
    
    echo json_encode($production);
    exit;
}
} catch (Throwable $e) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
