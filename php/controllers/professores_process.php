<?php
require_once __DIR__ . '/../configs/db.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'save';

if ($action == 'delete') {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    // Soft Delete: Apenas desativa o professor
    mysqli_query($conn, "UPDATE docente SET ativo = 0 WHERE id = '$id'");
    header("Location: ../views/professores.php?msg=deactivated");
    exit;
}

if ($action == 'activate') {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    mysqli_query($conn, "UPDATE docente SET ativo = 1 WHERE id = '$id'");
    header("Location: ../views/professores.php?msg=activated");
    exit;
}

if ($action == 'list_active') {
    header('Content-Type: application/json');
    $res = mysqli_query($conn, "SELECT id, nome, area_conhecimento FROM docente WHERE ativo = 1 ORDER BY nome ASC");
    $list = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $list[] = $row;
    }
    echo json_encode($list);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $nome = mysqli_real_escape_string($conn, $_POST['nome']);
    $area_conhecimento = mysqli_real_escape_string($conn, $_POST['area_conhecimento']);
    $cidade = mysqli_real_escape_string($conn, $_POST['cidade'] ?? '');
    if (trim($cidade) === '0' || trim($cidade) === '')
        $cidade = '';
    $weekly_hours_limit = (int) ($_POST['weekly_hours_limit'] ?? 0);
    $monthly_hours_limit = (int) ($_POST['monthly_hours_limit'] ?? 0);
    $tipo_contrato = mysqli_real_escape_string($conn, $_POST['tipo_contrato'] ?? '');

    if ($id) {
        $query = "UPDATE docente SET 
                  nome = '$nome', 
                  area_conhecimento = '$area_conhecimento', 
                  cidade = '$cidade',
                  weekly_hours_limit = '$weekly_hours_limit',
                  monthly_hours_limit = '$monthly_hours_limit',
                  tipo_contrato = '$tipo_contrato'
                  WHERE id = '$id'";
        mysqli_query($conn, $query);
    } else {
        $query = "INSERT INTO docente (nome, area_conhecimento, cidade, weekly_hours_limit, monthly_hours_limit, tipo_contrato) 
                  VALUES ('$nome', '$area_conhecimento', '$cidade', '$weekly_hours_limit', '$monthly_hours_limit', '$tipo_contrato')";
        mysqli_query($conn, $query);
        $id = mysqli_insert_id($conn);
    }

    // Process Work Schedules — Blocos Sazonais (V5)
    // Limpa todos os horários antigos do docente
    mysqli_query($conn, "DELETE FROM horario_trabalho WHERE docente_id = '$id'");

    // Os dados chegam agrupados por "bloco" de período.
    // Estrutura POST esperada:
    //   bloco_data_inicio[]  => "2026-01-01"
    //   bloco_data_fim[]     => "2026-04-30"
    //   periodo[]            => "Manhã"
    //   horario[]            => "07:30 as 11:30"
    //   dias_horario[N][]    => ["Segunda-feira", "Quarta-feira"]
    //   bloco_idx[]          => índice do bloco ao qual cada regra pertence

    $periodos_arr    = $_POST['periodo']    ?? [];
    $horarios_arr    = $_POST['horario']    ?? [];
    $dias_horario    = $_POST['dias_horario'] ?? [];
    $bloco_inicio    = $_POST['bloco_data_inicio'] ?? [];
    $bloco_fim       = $_POST['bloco_data_fim']    ?? [];
    $bloco_idx_arr   = $_POST['bloco_idx']         ?? [];

    foreach ($periodos_arr as $idx => $p) {
        $h      = $horarios_arr[$idx] ?? '';
        $d_list = $dias_horario[$idx] ?? [];
        $b_idx  = $bloco_idx_arr[$idx] ?? 0;

        $dt_ini = $bloco_inicio[$b_idx] ?? null;
        $dt_fim = $bloco_fim[$b_idx]    ?? null;

        if (empty($d_list) || empty($h) || empty($dt_ini) || empty($dt_fim))
            continue;

        // Validação: Sábado não permitido no período Noite
        if ($p === 'Noite') {
            $d_list = array_filter($d_list, fn($d) => $d !== 'Sábado');
        }
        if (empty($d_list)) continue;

        // Deriva o ano a partir da data de início do bloco
        $ano_bloco = (int) date('Y', strtotime($dt_ini));

        $p_esc   = mysqli_real_escape_string($conn, $p);
        $h_esc   = mysqli_real_escape_string($conn, $h);
        $d_str   = mysqli_real_escape_string($conn, implode(',', $d_list));
        $dt_ini_esc = mysqli_real_escape_string($conn, $dt_ini);
        $dt_fim_esc = mysqli_real_escape_string($conn, $dt_fim);

        mysqli_query($conn, "INSERT INTO horario_trabalho
                                (docente_id, dias, periodo, horario, data_inicio, data_fim, ano)
                             VALUES
                                ('$id', '$d_str', '$p_esc', '$h_esc', '$dt_ini_esc', '$dt_fim_esc', '$ano_bloco')");
    }

    header("Location: ../views/professores.php?msg=" . ($id ? 'updated' : 'created'));
    exit;
}

header("Location: ../views/professores.php");
?>