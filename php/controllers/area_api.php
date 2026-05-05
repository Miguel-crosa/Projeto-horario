<?php
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/auth.php';

header('Content-Type: application/json');

if (!isAdmin() && !isGestor()) {
    echo json_encode(['success' => false, 'message' => 'Acesso negado.']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'list') {
    $res = mysqli_query($conn, "SELECT * FROM area ORDER BY nome ASC");
    $areas = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $areas[] = $row;
    }
    echo json_encode(['success' => true, 'areas' => $areas]);
    exit;
}

if ($action === 'save') {
    $id = mysqli_real_escape_string($conn, $_POST['id'] ?? '');
    $nome = mysqli_real_escape_string($conn, trim($_POST['nome'] ?? ''));

    if (empty($nome)) {
        echo json_encode(['success' => false, 'message' => 'O nome da área é obrigatório.']);
        exit;
    }

    if ($id) {
        $query = "UPDATE area SET nome = '$nome' WHERE id = '$id'";
    } else {
        $query = "INSERT INTO area (nome) VALUES ('$nome')";
    }

    if (mysqli_query($conn, $query)) {
        echo json_encode(['success' => true, 'message' => 'Área salva com sucesso!', 'id' => $id ?: mysqli_insert_id($conn), 'nome' => $nome]);
    } else {
        $error = mysqli_error($conn);
        if (strpos($error, 'Duplicate entry') !== false) {
            echo json_encode(['success' => false, 'message' => 'Esta área já está cadastrada.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao salvar área: ' . $error]);
        }
    }
    exit;
}

if ($action === 'delete') {
    $id = mysqli_real_escape_string($conn, $_POST['id'] ?? '');
    if (mysqli_query($conn, "DELETE FROM area WHERE id = '$id'")) {
        echo json_encode(['success' => true, 'message' => 'Área excluída com sucesso!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir área.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Ação inválida.']);
