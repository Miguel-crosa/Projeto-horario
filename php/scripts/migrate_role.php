<?php
require_once __DIR__ . '/../configs/db.php';

$query = "ALTER TABLE usuario MODIFY COLUMN role ENUM('admin', 'gestor', 'professor', 'cri', 'secretaria') NOT NULL DEFAULT 'professor'";

if (mysqli_query($conn, $query)) {
    echo "Sucesso: Coluna 'role' atualizada para incluir 'secretaria'.\n";
} else {
    echo "Erro ao atualizar coluna 'role': " . mysqli_error($conn) . "\n";
}
