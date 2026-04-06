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

    // Process Work Schedules (New Slot-Based Logic V4)
    // First, clear old ones
    mysqli_query($conn, "DELETE FROM horario_trabalho WHERE docente_id = '$id'");

    $periodos_arr = $_POST['periodo'] ?? [];
    $horarios_arr = $_POST['horario'] ?? [];
    $dias_horario_arr = $_POST['dias_horario'] ?? [];

    foreach ($periodos_arr as $idx => $p) {
        $h = $horarios_arr[$idx] ?? '';
        $d_list = $dias_horario_arr[$idx] ?? [];

        if (empty($d_list) || empty($h))
            continue;

        // Validação Backend: Sábado não é permitido no período Noite
        if ($p === 'Noite') {
            $d_list = array_filter($d_list, function ($d) {
                return $d !== 'Sábado';
            });
        }

        if (empty($d_list))
            continue;

        $p_esc = mysqli_real_escape_string($conn, $p);
        $h_esc = mysqli_real_escape_string($conn, $h);
        $d_str = mysqli_real_escape_string($conn, implode(',', $d_list));

        mysqli_query($conn, "INSERT INTO horario_trabalho (docente_id, dias, periodo, horario) 
                            VALUES ('$id', '$d_str', '$p_esc', '$h_esc')");
    }

    header("Location: ../views/professores.php?msg=" . ($id ? 'updated' : 'created'));
    exit;
}

header("Location: ../views/professores.php");
?>