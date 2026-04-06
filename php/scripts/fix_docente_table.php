<?php
require_once __DIR__ . '/../configs/db.php';

echo "Iniciando migração do banco de dados...\n";

// 1. Adicionar coluna profissao se não existir
$res = mysqli_query($conn, "SHOW COLUMNS FROM docente LIKE 'profissao'");
if (mysqli_num_rows($res) == 0) {
    echo "Adicionando coluna 'profissao' na tabela docente...\n";
    if (mysqli_query($conn, "ALTER TABLE docente ADD COLUMN profissao VARCHAR(255) AFTER nome")) {
        echo "Coluna 'profissao' adicionada com sucesso!\n";
    } else {
        echo "Erro ao adicionar coluna 'profissao': " . mysqli_error($conn) . "\n";
    }
} else {
    echo "Coluna 'profissao' já existe.\n";
}

// 2. Garantir que as colunas de limites existam
$cols = [
    'weekly_hours_limit' => "ALTER TABLE docente ADD COLUMN weekly_hours_limit INT NOT NULL DEFAULT 0 AFTER carga_horaria_contratual",
    'monthly_hours_limit' => "ALTER TABLE docente ADD COLUMN monthly_hours_limit INT NOT NULL DEFAULT 0 AFTER weekly_hours_limit"
];

foreach ($cols as $col => $sql) {
    $res = mysqli_query($conn, "SHOW COLUMNS FROM docente LIKE '$col'");
    if (mysqli_num_rows($res) == 0) {
        echo "Adicionando coluna '$col'...\n";
        if (mysqli_query($conn, $sql)) {
            echo "Coluna '$col' adicionada com sucesso!\n";
        } else {
            echo "Erro ao adicionar coluna '$col': " . mysqli_error($conn) . "\n";
        }
    } else {
        echo "Coluna '$col' já existe.\n";
    }
}

echo "Migração concluída.\n";
?>
