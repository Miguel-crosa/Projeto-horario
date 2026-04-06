<?php
/**
 * Script de Limpeza de Duplicatas (Versão Aprimorada)
 */
require_once __DIR__ . '/../configs/db.php';

if (php_sapi_name() !== 'cli') {
    echo "<pre>";
}

echo "--- Iniciando limpeza de duplicatas ---\n";

// 1. Limpar Feriados (Holidays)
$sql_holidays = "
    DELETE h1 FROM holidays h1
    INNER JOIN holidays h2 
    WHERE h1.id > h2.id 
      AND h1.name = h2.name 
      AND h1.date = h2.date 
      AND (h1.end_date = h2.end_date OR (h1.end_date IS NULL AND h2.end_date IS NULL))
";

if (mysqli_query($conn, $sql_holidays)) {
    $affected = mysqli_affected_rows($conn);
    echo "[Feriados] Removidos: $affected\n";
} else {
    echo "[Feriados] Erro: " . mysqli_error($conn) . "\n";
}

// 2. Limpar Férias (Vacations)
$sql_vacations = "
    DELETE v1 FROM vacations v1
    INNER JOIN vacations v2 
    WHERE v1.id > v2.id 
      AND (v1.teacher_id = v2.teacher_id OR (v1.teacher_id IS NULL AND v2.teacher_id IS NULL))
      AND v1.start_date = v2.start_date 
      AND v1.end_date = v2.end_date
";

if (mysqli_query($conn, $sql_vacations)) {
    $affected = mysqli_affected_rows($conn);
    echo "[Férias] Removidas: $affected\n";
} else {
    echo "[Férias] Erro: " . mysqli_error($conn) . "\n";
}

echo "--- Limpeza concluída em " . date('d/m/Y H:i:s') . " ---\n";

if (php_sapi_name() !== 'cli') {
    echo "</pre>";
}
?>



echo "Iniciando limpeza de duplicatas...\n";

// 1. Limpar Feriados (Holidays)
// Definimos duplicata como: mesmo nome, data_inicio e data_fim (se houver)
$sql_holidays = "
    DELETE h1 FROM holidays h1
    INNER JOIN holidays h2 
    WHERE h1.id > h2.id 
      AND h1.name = h2.name 
      AND h1.date = h2.date 
      AND (h1.end_date = h2.end_date OR (h1.end_date IS NULL AND h2.end_date IS NULL))
";
if (mysqli_query($conn, $sql_holidays)) {
    echo "Feriados duplicados removidos com sucesso: " . mysqli_affected_rows($conn) . "\n";
} else {
    echo "Erro ao remover feriados: " . mysqli_error($conn) . "\n";
}

// 2. Limpar Férias (Vacations)
// Definimos duplicata como: mesmo docente_id (ou NULL para coletiva), data_inicio e data_fim
$sql_vacations = "
    DELETE v1 FROM vacations v1
    INNER JOIN vacations v2 
    WHERE v1.id > v2.id 
      AND (v1.teacher_id = v2.teacher_id OR (v1.teacher_id IS NULL AND v2.teacher_id IS NULL))
      AND v1.start_date = v2.start_date 
      AND v1.end_date = v2.end_date
";
if (mysqli_query($conn, $sql_vacations)) {
    echo "Férias duplicadas removidas com sucesso: " . mysqli_affected_rows($conn) . "\n";
} else {
    echo "Erro ao remover férias: " . mysqli_error($conn) . "\n";
}

echo "Limpeza concluída.\n";
?>
