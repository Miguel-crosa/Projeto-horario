<?php
require_once __DIR__ . '/../configs/db.php';

echo "<h2>Diagnóstico e Ajuste de Banco de Dados</h2>";

$tables_to_check = ['turma'];
$columns_needed = [
    'turma' => [
        'horario_inicio' => "TIME DEFAULT '07:30' AFTER local",
        'horario_fim' => "TIME DEFAULT '11:30' AFTER horario_inicio",
        'tipo_custeio' => "ENUM('Gratuidade', 'Ressarcido') DEFAULT 'Gratuidade'",
        'previsao_despesa' => "DECIMAL(10,2) DEFAULT 0.00",
        'valor_turma' => "DECIMAL(10,2) DEFAULT 0.00",
        'numero_proposta' => "VARCHAR(100) DEFAULT ''",
        'tipo_atendimento' => "ENUM('Empresa','Entidade','Balcão') DEFAULT 'Balcão'",
        'parceiro' => "VARCHAR(255) DEFAULT ''",
        'contato_parceiro' => "VARCHAR(255) DEFAULT ''",
        'ativo' => "TINYINT(1) DEFAULT 1"
    ],
    'reservas' => [
        'periodo' => "VARCHAR(20) DEFAULT 'Manhã' AFTER hora_fim",
        'sigla' => "VARCHAR(100) DEFAULT '' AFTER periodo ",
        'tipo_custeio' => "ENUM('Gratuidade', 'Ressarcido') DEFAULT 'Gratuidade'",
        'previsao_despesa' => "DECIMAL(10,2) DEFAULT 0.00",
        'valor_turma' => "DECIMAL(10,2) DEFAULT 0.00",
        'numero_proposta' => "VARCHAR(100) DEFAULT ''",
        'tipo_atendimento' => "ENUM('Empresa','Entidade','Balcão') DEFAULT 'Balcão'",
        'parceiro' => "VARCHAR(255) DEFAULT ''",
        'contato_parceiro' => "VARCHAR(255) DEFAULT ''"
    ]
];

foreach ($tables_to_check as $table) {
    echo "Verificando tabela: <strong>$table</strong>...<br>";

    // Check existing columns
    $res = mysqli_query($conn, "SHOW COLUMNS FROM `$table` ");

    $existing_columns = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $existing_columns[] = $row['Field'];
    }

    foreach ($columns_needed[$table] as $col => $definition) {
        if (!in_array($col, $existing_columns)) {
            echo "A coluna <strong>$col</strong> está FALTANDO. Tentando criar... ";
            $sql = "ALTER TABLE `$table` ADD `$col` $definition";
            if (mysqli_query($conn, $sql)) {
                echo "<span style='color:green'>Sucesso!</span><br>";
            } else {
                echo "<span style='color:red'>Erro: " . mysqli_error($conn) . "</span><br>";
            }
        } else {
            echo "A coluna <strong>$col</strong> já existe. ✓<br>";
        }
    }
    echo "<br>";
}

echo "<strong>Concluído.</strong><br>";
echo "<a href='../views/import_excel.php'>Voltar para Importação</a>";
?>