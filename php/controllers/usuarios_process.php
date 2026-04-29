<?php
/**
 * User Management Controller (Admin only, Gestor can create)
 * Handles CRUD for users with role-based permissions.
 */
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Must be authenticated
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Não autenticado.']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_role = $_SESSION['user_role'] ?? '';

switch ($action) {
    case 'create':
        // Both admin and gestor can create users
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'cri';
        $docente_id = !empty($_POST['docente_id']) ? (int) $_POST['docente_id'] : null;

        // Validate inputs
        if (empty($nome) || empty($email)) {
            $_SESSION['usuarios_error'] = 'Nome e e-mail são obrigatórios.';
            header('Location: ../views/usuarios.php');
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['usuarios_error'] = 'E-mail inválido.';
            header('Location: ../views/usuarios.php');
            exit;
        }

        // Gestor can ONLY create CRI or Secretaria
        if ($user_role === 'gestor') {
            if ($role !== 'cri' && $role !== 'secretaria') {
                $role = 'cri';
            }
        }

        // Sanitize role
        if (!in_array($role, ['admin', 'gestor', 'professor', 'cri', 'secretaria'])) {
            $role = 'professor';
        }

        // Gestores can only create CRI or Secretaria
        if ($user_role === 'gestor' && $role !== 'cri' && $role !== 'secretaria') {
            $role = 'cri';
        }

        // SECURITY FIX (IDOR): Only admin can create admin/gestor users
        // Gestor can ONLY create CRI or Professor (forced below)
        if ($user_role !== 'admin') {
            if ($role === 'admin' || $role === 'gestor') {
                $role = 'cri'; // Downgrade preventivo
            }
        }

        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM usuario WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $_SESSION['usuarios_error'] = 'Este e-mail já está cadastrado.';
            $stmt->close();
            header('Location: ../views/usuarios.php');
            exit;
        }
        $stmt->close();

        // Hash default password
        $hash = password_hash('senaisp', PASSWORD_BCRYPT);

        // Check if docente is already linked
        if ($docente_id) {
            $stmt = $conn->prepare("SELECT id FROM usuario WHERE docente_id = ?");
            $stmt->bind_param('i', $docente_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $_SESSION['usuarios_error'] = 'Este docente já está vinculado a outro usuário.';
                $stmt->close();
                header('Location: ../views/usuarios.php');
                exit;
            }
            $stmt->close();
        }

        $stmt = $conn->prepare("INSERT INTO usuario (nome, email, senha, role, docente_id, obrigar_troca_senha) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->bind_param('ssssi', $nome, $email, $hash, $role, $docente_id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['usuarios_success'] = 'Usuário criado com sucesso! Senha padrão: senaisp';
        header('Location: ../views/usuarios.php');
        exit;

    case 'edit':
        // Only admin can edit users
        if ($user_role !== 'admin') {
            http_response_code(403);
            $_SESSION['usuarios_error'] = 'Permissão insuficiente.';
            header('Location: ../views/usuarios.php');
            exit;
        }

        $id = (int) ($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'cri';
        $docente_id = !empty($_POST['docente_id']) ? (int) $_POST['docente_id'] : null;

        if (empty($nome) || empty($email) || !$id) {
            $_SESSION['usuarios_error'] = 'Dados inválidos.';
            header('Location: ../views/usuarios.php');
            exit;
        }

        // Sanitize role
        if (!in_array($role, ['admin', 'gestor', 'professor', 'cri', 'secretaria'])) {
            $role = 'professor';
        }

        // Check uniqueness of email (exclude current user)
        $stmt = $conn->prepare("SELECT id FROM usuario WHERE email = ? AND id != ?");
        $stmt->bind_param('si', $email, $id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $_SESSION['usuarios_error'] = 'Este e-mail já está em uso por outro usuário.';
            $stmt->close();
            header('Location: ../views/usuarios.php');
            exit;
        }
        $stmt->close();

        // Check if docente is already linked (exclude current user)
        if ($docente_id) {
            $stmt = $conn->prepare("SELECT id FROM usuario WHERE docente_id = ? AND id != ?");
            $stmt->bind_param('ii', $docente_id, $id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $_SESSION['usuarios_error'] = 'Este docente já está vinculado a outro usuário.';
                $stmt->close();
                header('Location: ../views/usuarios.php');
                exit;
            }
            $stmt->close();
        }

        $stmt = $conn->prepare("UPDATE usuario SET nome = ?, email = ?, role = ?, docente_id = ? WHERE id = ?");
        $stmt->bind_param('sssii', $nome, $email, $role, $docente_id, $id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['usuarios_success'] = 'Usuário atualizado com sucesso!';
        header('Location: ../views/usuarios.php');
        exit;

    case 'delete':
        // Only admin can delete users
        if ($user_role !== 'admin') {
            http_response_code(403);
            $_SESSION['usuarios_error'] = 'Permissão insuficiente.';
            header('Location: ../views/usuarios.php');
            exit;
        }

        $id = (int) ($_GET['id'] ?? 0);

        // Prevent self-deletion
        if ($id === (int) $_SESSION['user_id']) {
            $_SESSION['usuarios_error'] = 'Você não pode excluir seu próprio usuário.';
            header('Location: ../views/usuarios.php');
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM reservas WHERE usuario_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        // Delete related notifications
        $stmt = $conn->prepare("DELETE FROM notificacoes WHERE usuario_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        // Now delete the user
        $stmt = $conn->prepare("DELETE FROM usuario WHERE id = ?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $_SESSION['usuarios_success'] = 'Usuário removido com sucesso!';
        } else {
            $_SESSION['usuarios_error'] = 'Erro ao excluir usuário: ' . $stmt->error;
        }
        $stmt->close();

        header('Location: ../views/usuarios.php');
        exit;

    case 'reset_password':
        // Admin and Gestor can reset passwords
        if ($user_role !== 'admin' && $user_role !== 'gestor') {
            http_response_code(403);
            $_SESSION['usuarios_error'] = 'Permissão insuficiente.';
            header('Location: ../views/usuarios.php');
            exit;
        }

        $id = (int) ($_GET['id'] ?? 0);
        $hash = password_hash('senaisp', PASSWORD_BCRYPT);

        $stmt = $conn->prepare("UPDATE usuario SET senha = ?, obrigar_troca_senha = 1 WHERE id = ?");
        $stmt->bind_param('si', $hash, $id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['usuarios_success'] = 'Senha redefinida para o padrão (senaisp).';
        header('Location: ../views/usuarios.php');
        exit;

    case 'toggle_status':
        // Admin and Gestor can toggle status
        if ($user_role !== 'admin' && $user_role !== 'gestor') {
            http_response_code(403);
            $_SESSION['usuarios_error'] = 'Permissão insuficiente.';
            header('Location: ../views/usuarios.php');
            exit;
        }

        $id = (int) ($_GET['id'] ?? 0);
        $status = (int) ($_GET['status'] ?? 1);

        // Prevent self-deactivation
        if ($id === (int) $_SESSION['user_id']) {
            $_SESSION['usuarios_error'] = 'Você não pode desativar seu próprio usuário.';
            header('Location: ../views/usuarios.php');
            exit;
        }

        $stmt = $conn->prepare("UPDATE usuario SET ativo = ? WHERE id = ?");
        $stmt->bind_param('ii', $status, $id);
        
        if ($stmt->execute()) {
            $_SESSION['usuarios_success'] = 'Status do usuário atualizado com sucesso!';
        } else {
            $_SESSION['usuarios_error'] = 'Erro ao atualizar status: ' . $stmt->error;
        }
        $stmt->close();

        header('Location: ../views/usuarios.php');
        exit;

    default:
        header('Location: ../views/usuarios.php');
        exit;
}
