<?php
require_once 'configs/db.php';

$sql1 = "ALTER TABLE Usuario MODIFY COLUMN role ENUM('admin', 'gestor', 'professor', 'cri') NOT NULL DEFAULT 'professor'";
if ($mysqli->query($sql1)) {
    echo "Role 'cri' adicionada com sucesso.\n";
} else {
    echo "Erro ao adicionar role: " . $mysqli->error . "\n";
}

$sql2 = "CREATE TABLE IF NOT EXISTS notificacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    mensagem TEXT NOT NULL,
    link VARCHAR(255) DEFAULT NULL,
    lida TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES Usuario(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($mysqli->query($sql2)) {
    echo "Tabela 'notificacoes' criada com sucesso.\n";
} else {
    echo "Erro ao criar tabela notificacoes: " . $mysqli->error . "\n";
}
?>