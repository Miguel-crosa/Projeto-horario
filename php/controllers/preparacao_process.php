<?php
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/auth.php';

if (!isAdmin() && !isGestor()) {
    header("Location: ../../index.php");
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'delete') {
    $id = (int) $_GET['id'];
    mysqli_query($conn, "DELETE FROM preparacao_atestados WHERE id = $id");
    header("Location: ../views/preparacao.php?msg=deleted");
    exit;
}

if ($action === 'delete_multiple' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = $_POST['ids'] ?? [];
    if (!empty($ids)) {
        $ids_clean = array_map('intval', $ids);
        $ids_str = implode(',', $ids_clean);
        mysqli_query($conn, "DELETE FROM preparacao_atestados WHERE id IN ($ids_str)");
        header("Location: ../views/preparacao.php?msg=deleted_multiple");
    } else {
        header("Location: ../views/preparacao.php");
    }
    exit;
}

if ($action === 'delete_all') {
    mysqli_query($conn, "DELETE FROM preparacao_atestados");
    header("Location: ../views/preparacao.php?msg=deleted_all");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ? (int) $_POST['id'] : null;
    $docente_id = (int) $_POST['docente_id'];
    $tipo = $_POST['tipo'];
    $data_inicio = $_POST['data_inicio'];
    $data_fim = $_POST['data_fim'];
    $horario_inicio = $_POST['horario_inicio'] ?: null;
    $horario_fim = $_POST['horario_fim'] ?: null;
    $dias_semana = isset($_POST['dias_semana']) ? implode(',', $_POST['dias_semana']) : null;

    if ($id) {
        $stmt = $mysqli->prepare("UPDATE preparacao_atestados SET docente_id = ?, tipo = ?, data_inicio = ?, data_fim = ?, horario_inicio = ?, horario_fim = ?, dias_semana = ? WHERE id = ?");
        $stmt->bind_param('issssssi', $docente_id, $tipo, $data_inicio, $data_fim, $horario_inicio, $horario_fim, $dias_semana, $id);
    } else {
        $stmt = $mysqli->prepare("INSERT INTO preparacao_atestados (docente_id, tipo, data_inicio, data_fim, horario_inicio, horario_fim, dias_semana) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('issssss', $docente_id, $tipo, $data_inicio, $data_fim, $horario_inicio, $horario_fim, $dias_semana);
    }

    if ($stmt->execute()) {
        header("Location: ../views/preparacao.php?msg=success");
    } else {
        echo "Erro: " . $stmt->error;
    }
}
