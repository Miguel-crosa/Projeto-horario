<?php
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/utils.php';

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: ../views/planejamento.php");
    exit;
}

// Dados do formulário
$turma_id = (int)($_POST['turma_id'] ?? 0);
$docente_id = (int)($_POST['docente_id'] ?? 0);
$ambiente_id = (int)($_POST['ambiente_id'] ?? 0);
$dias_semana = $_POST['dias_semana'] ?? [];
$h_inicio = $_POST['horario_inicio'] ?? '';
$h_fim = $_POST['horario_fim'] ?? '';

if (empty($dias_semana) || !$docente_id || !$turma_id || !$h_inicio || !$h_fim) {
    die("Erro: Dados incompletos para o planejamento.");
}

/**
 * Função para calcular se a nova alocação ultrapassa os limites semanais/mensais
 */
function verificarLimiteCargaHoraria($conn, $docente_id, $data_inicio, $data_fim, $h_inicio, $h_fim, $dias_semana) {
    // 1. Obter limites cadastrados do docente
    $stmt = $conn->prepare("SELECT nome, weekly_hours_limit, monthly_hours_limit FROM docente WHERE id = ?");
    $stmt->bind_param("i", $docente_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $doc = $res->fetch_assoc();
    $stmt->close();

    if (!$doc) return true;

    $limit_w = (float)$doc['weekly_hours_limit'];
    $limit_m = (float)$doc['monthly_hours_limit'];
    $nome_doc = $doc['nome'];

    if ($limit_w <= 0 && $limit_m <= 0) {
        return "O docente $nome_doc possui carga horária zerada e não pode ser vinculado a turmas.";
    }

    // 2. Calcular duração decimal da NOVA aula
    $t1 = strtotime($h_inicio);
    $t2 = strtotime($h_fim);
    $hours_per_class = ($t2 - $t1) / 3600;
    if ($hours_per_class <= 0) return true;

    // 3. Mapeamento de dias e cálculo da carga da NOVA turma
    $it = new DateTime($data_inicio);
    $end = new DateTime($data_fim);
    $semanas_alocacao = []; // [ 'YYYY-WW' => horas ]
    $meses_alocacao = [];   // [ 'YYYY-MM' => horas ]

    $daysMap = [0 => 'Domingo', 1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado'];

    $it_temp = clone $it;
    while ($it_temp <= $end) {
        $currDate = $it_temp->format('Y-m-d');
        if (in_array($daysMap[(int)$it_temp->format('w')], $dias_semana)) {
            if (!isHoliday($conn, $currDate) && !isVacation($conn, $docente_id, $currDate)) {
                $yw = $it_temp->format('o-W'); 
                $ym = $it_temp->format('Y-m');
                $semanas_alocacao[$yw] = ($semanas_alocacao[$yw] ?? 0) + $hours_per_class;
                $meses_alocacao[$ym] = ($meses_alocacao[$ym] ?? 0) + $hours_per_class;
            }
        }
        $it_temp->modify('+1 day');
    }

    // 4. Validar limites Semanais
    foreach ($semanas_alocacao as $yw => $new_h) {
        $yw_parts = explode('-', $yw);
        $dt_sem = new DateTime();
        $dt_sem->setISODate((int)$yw_parts[0], (int)$yw_parts[1]);
        $ws = $dt_sem->format('Y-m-d');
        $dt_sem->modify('+6 days');
        $we = $dt_sem->format('Y-m-d');

        // Busca carga JÁ existente (Centralizado no utils.php)
        $curr_h = calculateConsumedHours($conn, $docente_id, $ws, $we);

        $total_week = $curr_h + $new_h;
        if ($total_week > ($limit_w + 0.001)) {
            return "O docente $nome_doc excederia o limite SEMANAL ($limit_w h). Total previsto: " . round($total_week, 1) . "h na semana de $ws. (Excesso: " . round($total_week - $limit_w, 1) . "h).";
        }
    }

    // 5. Validar limites Mensais
    foreach ($meses_alocacao as $ym => $new_h) {
        $ms = "$ym-01";
        $me = date('Y-m-t', strtotime($ms));

        // Busca carga JÁ existente (Centralizado no utils.php)
        $curr_h = calculateConsumedHours($conn, $docente_id, $ms, $me);

        $total_month = $curr_h + $new_h;
        if ($total_month > ($limit_m + 0.001)) {
            return "O docente $nome_doc excederia o limite MENSAL ($limit_m h). Total previsto para " . date('m/Y', strtotime($ms)) . ": " . round($total_month, 1) . "h. (Excesso: " . round($total_month - $limit_m, 1) . "h).";
        }
    }

    return true;
}


// Início do processamento
$conn->begin_transaction();
$conflitos = [];

try {
    // 1. Buscar datas da turma
    $stmt = $conn->prepare("SELECT data_inicio, data_fim FROM turma WHERE id = ?");
    $stmt->bind_param("i", $turma_id);
    $stmt->execute();
    $t_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$t_data) throw new Exception("Turma não encontrada.");
    $data_inicio = $t_data['data_inicio'];
    $data_fim = $t_data['data_fim'];

    // 2. Validar Limite de Carga Horária
    $val_carga = verificarLimiteCargaHoraria($conn, $docente_id, $data_inicio, $data_fim, $h_inicio, $h_fim, $dias_semana);
    if ($val_carga !== true) {
        $conflitos[] = $val_carga;
    }

    // 3. Verificar Conflitos de Docente e Ambiente
    $daysMap = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
    
    foreach ($dias_semana as $dia) {
        // Conflitos de Agenda (Aulas)
        $q_conf = "SELECT a.dia_semana, t.data_inicio AS t_start, t.data_fim AS t_end, c.nome AS curso, a.docente_id, a.ambiente_id
                   FROM agenda a 
                   JOIN turma t ON a.turma_id = t.id 
                   JOIN curso c ON t.curso_id = c.id
                   WHERE a.dia_semana = ? 
                     AND a.horario_inicio < ? AND a.horario_fim > ?
                     AND t.data_inicio <= ? AND t.data_fim >= ?";
        
        $stmt_c = $conn->prepare($q_conf);
        $stmt_c->bind_param("sssss", $dia, $h_fim, $h_inicio, $data_fim, $data_inicio);
        $stmt_c->execute();
        $res_c = $stmt_c->get_result();

        while ($r = $res_c->fetch_assoc()) {
            $overlap_start = max($data_inicio, $r['t_start']);
            $overlap_end = min($data_fim, $r['t_end']);
            $it = new DateTime($overlap_start);
            $itEnd = new DateTime($overlap_end);
            
            while ($it <= $itEnd) {
                if ($daysMap[(int)$it->format('w')] === $dia) {
                    if ($r['docente_id'] == $docente_id) {
                        $conflitos[] = "Docente já ocupado no curso '{$r['curso']}' em " . $it->format('d/m/Y') . ".";
                        break 2;
                    }
                    if ($ambiente_id != ID_AMBIENTE_OUTROS && $r['ambiente_id'] == $ambiente_id) {
                        $conflitos[] = "Ambiente já ocupado por '{$r['curso']}' em " . $it->format('d/m/Y') . ".";
                        break 2;
                    }
                }
                $it->modify('+1 day');
            }
        }
        $stmt_c->close();
    }

    // Se houver conflitos, Rollback e exibir erro
    if (!empty($conflitos)) {
        $conn->rollback();
        include __DIR__ . '/../components/header.php';
        ?>
        <div class="page-header">
            <h2><i class="fas fa-exclamation-triangle"></i> Falha no Planejamento</h2>
        </div>
        <div class="card conflict-card">
            <h3 style="color: var(--primary-red); margin-bottom: 15px;">Conflitos/Erros Encontrados</h3>
            <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px;">
                <?php foreach ($conflitos as $c): ?>
                    <div class="conflict-item" style="background: rgba(237,28,36,0.05); padding: 12px; border-left: 4px solid var(--primary-red); border-radius: 4px; font-weight: 500;">
                        <?= htmlspecialchars($c) ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="text-center">
                <a href="../views/planejamento.php" class="btn btn-primary">Voltar</a>
            </div>
        </div>
        <?php
        include __DIR__ . '/../components/footer.php';
        exit;
    }

    // 4. Inserir na agenda se tudo ok
    $stmt_ins = $conn->prepare("INSERT INTO agenda (docente_id, ambiente_id, turma_id, dia_semana, horario_inicio, horario_fim) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($dias_semana as $dia) {
        $stmt_ins->bind_param("iiisss", $docente_id, $ambiente_id, $turma_id, $dia, $h_inicio, $h_fim);
        $stmt_ins->execute();
    }
    $stmt_ins->close();

    $conn->commit();
    header("Location: ../../index.php?msg=agenda_updated");
    exit;

} catch (Exception $e) {
    $conn->rollback();
    die("Erro crítico ao salvar o planejamento: " . $e->getMessage());
}
?>