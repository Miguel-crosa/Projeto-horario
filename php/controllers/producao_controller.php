<?php
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/auth.php';
requireAuth();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'get_data') {
    // Busca todos os professores e suas produções baseadas nas turmas vinculadas na agenda
    $query = "
        SELECT 
            d.id AS docente_id, 
            d.nome AS docente_nome,
            t.id AS turma_id,
            t.sigla AS turma_sigla,
            t.vagas AS alunos,
            c.nome AS curso_nome,
            c.carga_horaria_total AS ch_total
        FROM docente d
        JOIN agenda a ON d.id = a.docente_id
        JOIN turma t ON a.turma_id = t.id
        JOIN curso c ON t.curso_id = c.id
        WHERE t.tipo_custeio = 'Gratuidade'
        GROUP BY d.id, t.id
    ";
    
    $res = mysqli_query($conn, $query);
    $data = [];
    
    while ($row = mysqli_fetch_assoc($res)) {
        $did = $row['docente_id'];
        if (!isset($data[$did])) {
            $data[$did] = [
                'id' => $did,
                'nome' => $row['docente_nome'],
                'producao_total' => 0,
                'turmas' => []
            ];
        }
        
        $producao = (int)$row['alunos'] * (int)$row['ch_total'];
        $data[$did]['producao_total'] += $producao;
        $data[$did]['turmas'][] = [
            'id' => $row['turma_id'],
            'sigla' => $row['turma_sigla'] ?: ('Turma ' . $row['turma_id']),
            'curso' => $row['curso_nome'],
            'alunos' => (int)$row['alunos'],
            'ch' => (int)$row['ch_total'],
            'producao' => $producao
        ];
    }
    
    // Converte para array indexado para o Chart.js
    echo json_encode(array_values($data));
    exit;
}

if ($action === 'registrar_evasao') {
    $turma_id = (int)($_POST['turma_id'] ?? 0);
    $quantidade = (int)($_POST['quantidade'] ?? 1);
    
    if ($turma_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID da turma inválido.']);
        exit;
    }
    
    // Diminui a quantidade informada de vagas (alunos) na turma
    $query = "UPDATE turma SET vagas = GREATEST(0, vagas - $quantidade) WHERE id = $turma_id";
    if (mysqli_query($conn, $query)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    }
    exit;
}

if ($action === 'adicionar_aluno') {
    $turma_id = (int)($_POST['turma_id'] ?? 0);
    $quantidade = (int)($_POST['quantidade'] ?? 1);
    
    if ($turma_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'ID da turma inválido.']);
        exit;
    }
    
    // Aumenta a quantidade informada de vagas (alunos) na turma
    $query = "UPDATE turma SET vagas = vagas + $quantidade WHERE id = $turma_id";
    if (mysqli_query($conn, $query)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    }
    exit;
}

if ($action === 'get_financeiro_data') {
    // 1. Dados de Ressarcimento por Curso (Real)
    $q_ress = "SELECT c.nome as curso, SUM(t.valor_turma) as total 
               FROM turma t 
               JOIN curso c ON t.curso_id = c.id 
               WHERE t.tipo_custeio = 'Ressarcido' AND t.ativo = 1
               GROUP BY c.id 
               HAVING total > 0 
               ORDER BY total DESC";
    $res_ress = mysqli_query($conn, $q_ress);
    $ressarcido = mysqli_fetch_all($res_ress, MYSQLI_ASSOC);

    // 2. Previsão de Despesas por Turma (Real)
    $q_desp = "SELECT c.nome as curso, t.sigla, t.previsao_despesa as valor 
               FROM turma t 
               JOIN curso c ON t.curso_id = c.id 
               WHERE t.previsao_despesa > 0 AND t.ativo = 1
               ORDER BY t.previsao_despesa DESC";
    $res_desp = mysqli_query($conn, $q_desp);
    $despesas = mysqli_fetch_all($res_desp, MYSQLI_ASSOC);
    
    // 3. Pipeline (Reservas PENDENTES e APROVADAS)
    $q_pipe = "SELECT c.nome as curso, r.sigla, r.previsao_despesa as valor, r.status
               FROM reservas r
               JOIN curso c ON r.curso_id = c.id
               WHERE r.previsao_despesa > 0 AND r.status IN ('PENDENTE', 'APROVADA')
               ORDER BY r.previsao_despesa DESC";
    $res_pipe = mysqli_query($conn, $q_pipe);
    $pipeline_despesas = mysqli_fetch_all($res_pipe, MYSQLI_ASSOC);

    $q_pipe_ress = "SELECT c.nome as curso, r.sigla, r.valor_turma as valor, r.status
                    FROM reservas r
                    JOIN curso c ON r.curso_id = c.id
                    WHERE r.valor_turma > 0 AND r.status IN ('PENDENTE', 'APROVADA')";
    $res_pipe_ress = mysqli_query($conn, $q_pipe_ress);
    $pipeline_ressarcido = mysqli_fetch_all($res_pipe_ress, MYSQLI_ASSOC);

    $total_geral_despesas = 0;
    foreach($despesas as $d) $total_geral_despesas += (float)$d['valor'];

    $total_pipeline_despesas = 0;
    foreach($pipeline_despesas as $p) $total_pipeline_despesas += (float)$p['valor'];

    $total_pipeline_ress = 0;
    foreach($pipeline_ressarcido as $p) $total_pipeline_ress += (float)$p['valor'];

    echo json_encode([
        'ressarcido' => $ressarcido,
        'despesas' => $despesas,
        'pipeline_despesas' => $pipeline_despesas,
        'pipeline_ressarcido' => $pipeline_ressarcido,
        'total_despesas' => $total_geral_despesas,
        'total_pipeline_despesas' => $total_pipeline_despesas,
        'total_pipeline_ress' => $total_pipeline_ress
    ]);
    exit;
}
?>
