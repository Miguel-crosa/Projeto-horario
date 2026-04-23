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
        AND YEAR(t.data_fim) = YEAR(CURDATE())
        GROUP BY d.id, t.id
    ";
    
    $res = mysqli_query($conn, $query);
    $ranking = [];
    
    while ($row = mysqli_fetch_assoc($res)) {
        $did = $row['docente_id'];
        if (!isset($ranking[$did])) {
            $ranking[$did] = [
                'id' => $did,
                'nome' => $row['docente_nome'],
                'producao_total' => 0,
                'turmas' => []
            ];
        }
        
        $producao = (int)$row['alunos'] * (int)$row['ch_total'];
        $ranking[$did]['producao_total'] += $producao;
        $ranking[$did]['turmas'][] = [
            'id' => $row['turma_id'],
            'sigla' => $row['turma_sigla'] ?: ('Turma ' . $row['turma_id']),
            'curso' => $row['curso_nome'],
            'alunos' => (int)$row['alunos'],
            'ch' => (int)$row['ch_total'],
            'producao' => $producao
        ];
    }

    // Calcula o TOTAL UNIDADE (idêntico ao metas_controller.php)
    $q_total = "
        SELECT SUM(t.vagas * c.carga_horaria_total) as total
        FROM turma t
        JOIN curso c ON t.curso_id = c.id
        WHERE t.ativo = 1 
        AND t.tipo_custeio = 'Gratuidade'
        AND EXISTS (SELECT 1 FROM agenda a WHERE a.turma_id = t.id)
        AND YEAR(t.data_fim) = YEAR(CURDATE())
    ";
    $res_total = mysqli_query($conn, $q_total);
    $total_unidade = (float)(mysqli_fetch_assoc($res_total)['total'] ?? 0);
    
    echo json_encode([
        'ranking' => array_values($ranking),
        'total_unidade' => $total_unidade
    ]);
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
    // 1. Dados de Ressarcimento por Curso (Gráfico)
    // Combina turmas ativas e reservas CONCLUIDAS
    $q_ress = "
        SELECT curso, SUM(valor) as total
        FROM (
            SELECT c.nome as curso, t.valor_turma as valor 
            FROM turma t JOIN curso c ON t.curso_id = c.id 
            WHERE t.tipo_custeio = 'Ressarcido' AND t.valor_turma > 0
            
            UNION ALL
            
            SELECT COALESCE(c.nome, 'Sem Curso') as curso, r.valor_turma as valor 
            FROM reservas r LEFT JOIN curso c ON r.curso_id = c.id 
            WHERE r.tipo_custeio = 'Ressarcido' AND r.status = 'CONCLUIDA' AND r.valor_turma > 0
        ) as combinados
        GROUP BY curso
        ORDER BY total DESC
    ";
    $res_ress = mysqli_query($conn, $q_ress);
    $ressarcido = mysqli_fetch_all($res_ress, MYSQLI_ASSOC);

    $total_arrecadacao_real = 0;
    foreach ($ressarcido as $r) {
        $total_arrecadacao_real += (float)$r['total'];
    }

    // 1.1 Detalhamento de Ressarcimento (Lista)
    $q_ress_det = "
        SELECT curso, sigla, valor
        FROM (
            SELECT c.nome as curso, t.sigla, t.valor_turma as valor 
            FROM turma t JOIN curso c ON t.curso_id = c.id 
            WHERE t.tipo_custeio = 'Ressarcido' AND t.valor_turma > 0
            
            UNION ALL
            
            SELECT COALESCE(c.nome, 'Sem Curso') as curso, COALESCE(r.sigla, CONCAT('RES-', r.id)) as sigla, r.valor_turma as valor 
            FROM reservas r LEFT JOIN curso c ON r.curso_id = c.id 
            WHERE r.tipo_custeio = 'Ressarcido' AND r.status = 'CONCLUIDA' AND r.valor_turma > 0
            AND (r.sigla IS NULL OR r.sigla NOT IN (SELECT sigla FROM turma WHERE sigla IS NOT NULL))
        ) as detalhes
        ORDER BY valor DESC
    ";
    $res_ress_det = mysqli_query($conn, $q_ress_det);
    $ressarcido_detalhe = mysqli_fetch_all($res_ress_det, MYSQLI_ASSOC);

    // 2. Previsão de Despesas por Turma (Gráfico e Lista)
    $q_desp = "
        SELECT curso, sigla, valor
        FROM (
            SELECT c.nome as curso, t.sigla, t.previsao_despesa as valor 
            FROM turma t JOIN curso c ON t.curso_id = c.id 
            WHERE t.previsao_despesa > 0
            
            UNION ALL
            
            SELECT COALESCE(c.nome, 'Sem Curso') as curso, COALESCE(r.sigla, CONCAT('RES-', r.id)) as sigla, r.previsao_despesa as valor 
            FROM reservas r LEFT JOIN curso c ON r.curso_id = c.id 
            WHERE r.status = 'CONCLUIDA' AND r.previsao_despesa > 0
            AND (r.sigla IS NULL OR r.sigla NOT IN (SELECT sigla FROM turma WHERE sigla IS NOT NULL))
        ) as desp_combinadas
        ORDER BY valor DESC
    ";
    $res_desp = mysqli_query($conn, $q_desp);
    $despesas = mysqli_fetch_all($res_desp, MYSQLI_ASSOC);
    
    $total_geral_despesas = 0;
    foreach($despesas as $d) {
        $total_geral_despesas += (float)$d['valor'];
    }

    // 3. Pipeline (SOMENTE Reservas PENDENTE + APROVADA)
    $q_ress_pipe = "SELECT SUM(valor_turma) as total 
                    FROM reservas 
                    WHERE tipo_custeio = 'Ressarcido' AND status IN ('PENDENTE', 'APROVADA')";
    $res_ress_pipe = mysqli_query($conn, $q_ress_pipe);
    $total_ressarcido_pipeline = (float)(mysqli_fetch_assoc($res_ress_pipe)['total'] ?? 0);

    $q_desp_pipe = "SELECT SUM(previsao_despesa) as total 
                    FROM reservas 
                    WHERE status IN ('PENDENTE', 'APROVADA')";
    $res_desp_pipe = mysqli_query($conn, $q_desp_pipe);
    $total_despesas_pipeline = (float)(mysqli_fetch_assoc($res_desp_pipe)['total'] ?? 0);

    echo json_encode([
        'ressarcido' => $ressarcido,
        'ressarcido_detalhe' => $ressarcido_detalhe,
        'total_ressarcido_real' => $total_arrecadacao_real,
        'total_ressarcido_pipeline' => $total_ressarcido_pipeline,
        'despesas' => $despesas,
        'total_despesas' => $total_geral_despesas,
        'total_despesas_pipeline' => $total_despesas_pipeline
    ]);
    exit;
}
?>
