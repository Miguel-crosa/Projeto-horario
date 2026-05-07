<?php
/**
 * Authentication Middleware
 * Include this file at the top of every protected page (or via header.php).
 * Handles session validation, role checks, and forced password change redirects.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Checks if the user is authenticated.
 * Redirects to login if not.
 */
function requireAuth()
{
    if (empty($_SESSION['user_id'])) {
        // Determine the correct path to login.php
        $path_parts = explode('/', trim($_SERVER['PHP_SELF'], '/'));
        $is_in_subdir = !empty(array_intersect(['views', 'controllers', 'components', 'configs'], $path_parts));
        $prefix = $is_in_subdir ? '' : 'php/views/';
        header('Location: ' . $prefix . 'login.php');
        exit;
    }
}

/**
 * Checks if the user must change their password.
 * Redirects/flags if obrigar_troca_senha is true.
 */
function checkForcePasswordChange()
{
    if (!empty($_SESSION['obrigar_troca_senha'])) {
        $current_page = basename($_SERVER['PHP_SELF']);
        // Allow the change password action and the login page itself
        $allowed_pages = ['login.php', 'login_process.php', 'usuarios_process.php', 'logout.php'];
        if (!in_array($current_page, $allowed_pages)) {
            // Set a flag that the frontend will use to show the modal
            $_SESSION['show_change_password_modal'] = true;
        }
    }
}

/**
 * Requires a specific role. Returns 403 if the user doesn't have it.
 * @param string|array $roles Allowed role(s)
 */
function requireRole($roles)
{
    if (!is_array($roles))
        $roles = [$roles];
    if (empty($_SESSION['user_role']) || !in_array($_SESSION['user_role'], $roles)) {
        http_response_code(403);
        echo json_encode(['error' => 'Acesso negado. Permissão insuficiente.']);
        exit;
    }
}

/**
 * Check if the current user is an admin.
 * @return bool
 */
function isAdmin()
{
    return ($_SESSION['user_role'] ?? '') === 'admin';
}

/**
 * Check if the current user is a gestor.
 * @return bool
 */
function isGestor()
{
    return ($_SESSION['user_role'] ?? '') === 'gestor';
}

/**
 * Check if the current user is a professor.
 * @return bool
 */
function isProfessor()
{
    return ($_SESSION['user_role'] ?? '') === 'professor';
}

/**
 * Check if the current user is a CRI.
 * @return bool
 */
function isCRI()
{
    return ($_SESSION['user_role'] ?? '') === 'cri';
}

/**
 * Check if the current user is a secretaria.
 * @return bool
 */
function isSecretaria()
{
    return ($_SESSION['user_role'] ?? '') === 'secretaria';
}

/**
 * Get the current user's name.
 * @return string
 */
function getUserName()
{
    return $_SESSION['user_nome'] ?? 'Usuário';
}

/**
 * Get the current user's role.
 * @return string
 */
function getUserRole()
{
    return $_SESSION['user_role'] ?? 'professor';
}

/**
 * Get the current user's linked docente_id.
 * @return int|null
 */
function getUserDocenteId()
{
    return $_SESSION['user_docente_id'] ?? null;
}

/**
 * Check if the current user can edit (admin or gestor).
 * Compatibility with Parafal code.
 * @return bool
 */
function can_edit()
{
    // CRI and Secretaria cannot edit classes or approve, only reserve (CRI) or read-only (Secretaria)
    return isAdmin() || isGestor();
}

/**
 * Check if the current user can reserve.
 * @return bool
 */
function can_reserve()
{
    // Secretaria cannot reserve, only admin, gestor and CRI
    return isAdmin() || isGestor() || isCRI();
}

$auth_user_id = $_SESSION['user_id'] ?? 0;
$auth_user_nome = $_SESSION['user_nome'] ?? 'Usuário';
$auth_user_role = $_SESSION['user_role'] ?? '';

/**
 * Inserts a notification into the DB for all users of specific roles (broadcasting).
 */
function dispararNotificacaoGlobal($mysqli, $tipo, $titulo, $mensagem, $link = null, $roles = ['admin', 'gestor'])
{
    global $auth_user_id;

    if (empty($roles))
        return;

    // Get all users with the specified roles
    $in = str_repeat('?,', count($roles) - 1) . '?';
    $types = str_repeat('s', count($roles));

    $stmt = $mysqli->prepare("SELECT id FROM usuario WHERE role IN ($in)");
    $stmt->bind_param($types, ...$roles);
    $stmt->execute();
    $res = $stmt->get_result();

    $insert = $mysqli->prepare("INSERT INTO notificacoes (usuario_id, tipo, titulo, mensagem, link) VALUES (?, ?, ?, ?, ?)");
    while ($u = $res->fetch_assoc()) {
        $uid = $u['id'];

        // Dont necessarily notify the exact same user doing the action
        // UNLESS we want to, but standard behavior is to notify others.
        if ($uid == $auth_user_id && $tipo !== 'reserva_realizada' && $tipo !== 'registro_horario')
            continue;

        $insert->bind_param('issss', $uid, $tipo, $titulo, $mensagem, $link);
        $insert->execute();
    }
}

/**
 * Notificação direcionada para reservas: envia mensagem pessoal ao criador/docente
 * e mensagem genérica aos demais admins/gestores.
 * 
 * @param int|null $usuario_id_criador  ID do usuário que criou a reserva
 * @param int|null $docente_id          ID do docente vinculado à reserva
 * @param string   $titulo_pessoal     Título para o criador/docente (ex: "Sua reserva foi Aprovada")
 * @param string   $titulo_generico    Título para os demais (ex: "Reserva Aprovada")
 * @param string   $mensagem_pessoal   Mensagem para o criador/docente
 * @param string   $mensagem_generica  Mensagem genérica para admins/gestores
 */
function dispararNotificacaoReserva($mysqli, $tipo, $titulo_pessoal, $titulo_generico, $mensagem_pessoal, $mensagem_generica, $link = null, $usuario_id_criador = null, $docente_id = null)
{
    global $auth_user_id;

    $insert = $mysqli->prepare("INSERT INTO notificacoes (usuario_id, tipo, titulo, mensagem, link) VALUES (?, ?, ?, ?, ?)");
    $notificados = []; // IDs já notificados para evitar duplicata

    // 1. Notificar o criador da reserva (mensagem pessoal)
    if ($usuario_id_criador && $usuario_id_criador > 0) {
        $insert->bind_param('issss', $usuario_id_criador, $tipo, $titulo_pessoal, $mensagem_pessoal, $link);
        $insert->execute();
        $notificados[] = (int)$usuario_id_criador;
    }

    // 2. Notificar o docente vinculado (mensagem pessoal)
    if ($docente_id && $docente_id > 0) {
        $user_res = $mysqli->query("SELECT id FROM usuario WHERE docente_id = " . (int)$docente_id . " LIMIT 1");
        if ($user_row = $user_res->fetch_assoc()) {
            $docente_user_id = (int)$user_row['id'];
            if (!in_array($docente_user_id, $notificados)) {
                $insert->bind_param('issss', $docente_user_id, $tipo, $titulo_pessoal, $mensagem_pessoal, $link);
                $insert->execute();
                $notificados[] = $docente_user_id;
            }
        }
    }

    // 3. Notificar admins e gestores com mensagem genérica (excluindo quem já foi notificado e quem executou a ação)
    $admin_res = $mysqli->query("SELECT id FROM usuario WHERE role IN ('admin', 'gestor')");
    while ($u = $admin_res->fetch_assoc()) {
        $uid = (int)$u['id'];
        if (in_array($uid, $notificados)) continue; // Já recebeu a versão pessoal
        if ($uid == $auth_user_id) continue;          // Quem executou a ação não precisa receber

        $insert->bind_param('issss', $uid, $tipo, $titulo_generico, $mensagem_generica, $link);
        $insert->execute();
    }
}
