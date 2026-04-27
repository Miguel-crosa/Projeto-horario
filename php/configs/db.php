<?php
date_default_timezone_set('America/Sao_Paulo');
// Caminho absoluto para evitar quebra de links nas notificações
// Detecção dinâmica da BASE_URL (Universal para Local e Deploy)
if (!isset($_SERVER['DOCUMENT_ROOT'])) {
     define('BASE_URL', '');
} else {
     $doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
     $current_dir = str_replace('\\', '/', __DIR__);
     $dir_path = str_ireplace($doc_root, '', $current_dir);
     $base_url = str_ireplace('/php/configs', '', $dir_path);
     $base_url = rtrim($base_url, '/');
     define('BASE_URL', $base_url);
}
$host = 'localhost'; // Geralmente 'localhost' na Hostinger
$user = 'root';
$pass = '';

$db = 'gestao_escolar';
$port = 3306;

// Ativar exibição de erros apenas para log, ocultar do usuário final
mysqli_report(MYSQLI_REPORT_OFF);

try {
     $conn = mysqli_connect($host, $user, $pass, $db, $port);
     if (!$conn) {
          throw new Exception("Falha na conexão.");
     }
} catch (Exception $e) {
     // Logar erro internamente se necessário
     die("Erro de conexão com o banco de dados. Por favor, tente novamente mais tarde.");
}

mysqli_set_charset($conn, "utf8mb4");
mysqli_query($conn, "SET time_zone = '-03:00'");

// Alias OOP para compatibilidade com código portado do Parafal
$mysqli = $conn;

/**
 * Garantir que o ambiente "Outros" exista para evitar conflitos
 */
function getOutrosAmbienteId($conn)
{
     static $id = null;
     if ($id !== null)
          return $id;

     $res = mysqli_query($conn, "SELECT id FROM ambiente WHERE nome = 'Outros' LIMIT 1");
     if ($row = mysqli_fetch_assoc($res)) {
          $id = $row['id'];
     } else {
          mysqli_query($conn, "INSERT INTO ambiente (nome, tipo, area_vinculada) VALUES ('Outros', 'Virtual/Flexível', 'Geral')");
          $id = mysqli_insert_id($conn);
     }
     return $id;
}
define('ID_AMBIENTE_OUTROS', getOutrosAmbienteId($conn));

// Helper de escape HTML
if (!function_exists('xe')) {
     function xe($v)
     {
          return htmlspecialchars((string) $v, ENT_QUOTES | ENT_XML1, 'UTF-8');
     }
}

// Executar migrações automáticas (Correção de feriados, duplicatas, etc.)
require_once __DIR__ . '/../scripts/run_migrations.php';
