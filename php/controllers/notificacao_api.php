<?php
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/auth.php';
requireAuth();

header('Content-Type: application/json');

function returnError($msg, $code = 400)
{
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

$action = $_REQUEST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'listar') {
        $status = $_GET['status'] ?? 'todas'; // 'lido', 'nao_lido', 'todas'
        $tipo = $_GET['tipo'] ?? 'todos';     // 'reserva_realizada', 'registro_horario', etc

        $where = "usuario_id = ?";
        $params = [$auth_user_id];
        $types = "i";

        if ($status === 'lido') {
            $where .= " AND lida = 1";
        } elseif ($status === 'nao_lido') {
            $where .= " AND lida = 0";
        }

        if ($tipo !== 'todos' && !empty($tipo)) {
            $where .= " AND tipo = ?";
            $params[] = $tipo;
            $types .= "s";
        }

        $stmt = $mysqli->prepare("SELECT * FROM notificacoes WHERE $where ORDER BY created_at DESC LIMIT 50");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $notificacoes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        // Also fetch count of unread
        $st_count = $mysqli->prepare("SELECT COUNT(*) FROM notificacoes WHERE usuario_id = ? AND lida = 0");
        $st_count->bind_param('i', $auth_user_id);
        $st_count->execute();
        $nao_lidas_count = $st_count->get_result()->fetch_row()[0];

        echo json_encode(['success' => true, 'notificacoes' => $notificacoes, 'unread' => $nao_lidas_count]);
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'marcar_lida') {
        $notificacao_id = (int) ($_POST['notificacao_id'] ?? 0);
        if ($notificacao_id) {
            $stmt = $mysqli->prepare("UPDATE notificacoes SET lida = 1 WHERE id = ? AND usuario_id = ?");
            $stmt->bind_param('ii', $notificacao_id, $auth_user_id);
            $stmt->execute();
            echo json_encode(['success' => true]);
        } else {
            returnError('ID inválido');
        }
        exit;
    }

    if ($action === 'marcar_todas_lidas') {
        $stmt = $mysqli->prepare("UPDATE notificacoes SET lida = 1 WHERE usuario_id = ? AND lida = 0");
        $stmt->bind_param('i', $auth_user_id);
        $stmt->execute();
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'limpar_lidas') {
        $stmt = $mysqli->prepare("DELETE FROM notificacoes WHERE usuario_id = ? AND lida = 1");
        $stmt->bind_param('i', $auth_user_id);
        $stmt->execute();
        echo json_encode(['success' => true]);
        exit;
    }
}

returnError('Ação inválida');
?>