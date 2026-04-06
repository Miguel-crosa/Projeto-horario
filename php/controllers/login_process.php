<?php
/**
 * Login Process Controller
 * Handles authentication via email/password with bcrypt verification.
 * Also handles forced password change.
 */
require_once __DIR__ . '/../configs/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'login':
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';

        if (empty($email) || empty($senha)) {
            $_SESSION['login_error'] = 'Preencha todos os campos.';
            header('Location: ../views/login.php');
            exit;
        }

        // Use prepared statement to prevent SQL injection
        // Check if firstLogin column exists
        $login_cols = 'id, nome, email, senha, role, obrigar_troca_senha, docente_id';
        $fl_check = $conn->query("SHOW COLUMNS FROM usuario LIKE 'firstLogin'");
        if ($fl_check && $fl_check->num_rows > 0) {
            $login_cols = 'id, nome, email, senha, role, obrigar_troca_senha, firstLogin, docente_id';
        }
        $stmt = $conn->prepare("SELECT $login_cols FROM usuario WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user || !password_verify($senha, $user['senha'])) {
            $_SESSION['login_error'] = 'E-mail ou senha inválidos.';
            header('Location: ../views/login.php');
            exit;
        }

        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_nome'] = $user['nome'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_docente_id'] = $user['docente_id'];

        // Force password change if it's first login or flagged in DB
        $is_default_password = ($senha === 'senaisp');
        $_SESSION['obrigar_troca_senha'] = (bool) ($user['obrigar_troca_senha'] || $user['firstLogin'] || $is_default_password);

        // Redirect to main page (header.php will handle forced password change)
        header('Location: ../../index.php');
        exit;

    case 'change_password':
        header('Content-Type: application/json');

        if (empty($_SESSION['user_id'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Não autenticado.']);
            exit;
        }

        $nova_senha = $_POST['nova_senha'] ?? '';
        $confirmar_senha = $_POST['confirmar_senha'] ?? '';

        // Validate
        if (strlen($nova_senha) < 6) {
            echo json_encode(['success' => false, 'error' => 'A senha deve ter no mínimo 6 caracteres.']);
            exit;
        }
        if ($nova_senha !== $confirmar_senha) {
            echo json_encode(['success' => false, 'error' => 'As senhas não coincidem.']);
            exit;
        }
        // Prevent setting the default password again
        if ($nova_senha === 'senaisp') {
            echo json_encode(['success' => false, 'error' => 'A nova senha não pode ser igual à senha padrão.']);
            exit;
        }

        $hash = password_hash($nova_senha, PASSWORD_BCRYPT);
        $uid = (int) $_SESSION['user_id'];

        // Check if firstLogin column exists before using it
        $has_firstLogin = false;
        $cols_result = $conn->query("SHOW COLUMNS FROM usuario LIKE 'firstLogin'");
        if ($cols_result && $cols_result->num_rows > 0) {
            $has_firstLogin = true;
        }

        if ($has_firstLogin) {
            $stmt = $conn->prepare("UPDATE usuario SET senha = ?, obrigar_troca_senha = 0, firstLogin = 0 WHERE id = ?");
        } else {
            $stmt = $conn->prepare("UPDATE usuario SET senha = ?, obrigar_troca_senha = 0 WHERE id = ?");
        }
        $stmt->bind_param('si', $hash, $uid);

        if ($stmt->execute()) {
            $_SESSION['obrigar_troca_senha'] = false;
            $_SESSION['show_change_password_modal'] = false;
            echo json_encode(['success' => true, 'message' => 'Senha alterada com sucesso!']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Erro ao atualizar senha: ' . $stmt->error]);
        }
        $stmt->close();
        exit;

    default:
        header('Location: ../views/login.php');
        exit;
}
