<?php
/**
 * Script de Migração Automática - Projeto Horário
 * Este script corrige as datas de feriados importadas com erro (-1 dia) 
 * e limpa duplicatas automaticamente.
 */

// Se estiver sendo chamado via direct access, carregar o DB
if (!isset($conn) && !isset($mysqli)) {
    require_once __DIR__ . '/../configs/db.php';
}
global $conn;

function run_migrations($conn)
{
    // 1. Criar tabela de controle de migrações se não existir
    $sql_table = "CREATE TABLE IF NOT EXISTS sys_migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration_name VARCHAR(255) UNIQUE NOT NULL,
        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    mysqli_query($conn, $sql_table);

    // 2. Migração: Correção Retroativa de Datas de Feriados (+1 dia)
    $migration_name = 'fix_holiday_dates_offset_2026_04_02';
    $check = mysqli_query($conn, "SELECT id FROM sys_migrations WHERE migration_name = '$migration_name'");

    if (mysqli_num_rows($check) == 0) {
        // Log para debug (opcional)
        // echo "Executando migração: $migration_name...\n";

        // Adiciona 1 dia a todos os feriados para corrigir o erro de importação anterior
        // Usamos DATE_ADD para garantir que a manipulação de data seja correta no MySQL
        $sql_fix = "UPDATE holidays SET 
            date = DATE_ADD(date, INTERVAL 1 DAY),
            end_date = CASE 
                WHEN end_date IS NOT NULL THEN DATE_ADD(end_date, INTERVAL 1 DAY) 
                ELSE DATE_ADD(date, INTERVAL 1 DAY) 
            END";

        if (mysqli_query($conn, $sql_fix)) {
            // Após corrigir as datas, limpamos as duplicatas
            $sql_cleanup = "
                DELETE h1 FROM holidays h1
                INNER JOIN holidays h2 
                WHERE h1.id > h2.id 
                  AND h1.name = h2.name 
                  AND h1.date = h2.date
            ";
            mysqli_query($conn, $sql_cleanup);

            // Registrar sucesso da migração
            $stmt = mysqli_prepare($conn, "INSERT INTO sys_migrations (migration_name) VALUES (?)");
            mysqli_stmt_bind_param($stmt, "s", $migration_name);
            mysqli_stmt_execute($stmt);
        }
    }

    // 3. Migração: Adicionar coluna 'ativo' na tabela docente
    $migration_ativo = 'add_column_ativo_to_docente_2026_04_02';
    $check_ativo = mysqli_query($conn, "SELECT id FROM sys_migrations WHERE migration_name = '$migration_ativo'");
    if (mysqli_num_rows($check_ativo) == 0) {
        $res = mysqli_query($conn, "SHOW COLUMNS FROM docente LIKE 'ativo'");
        if (mysqli_num_rows($res) == 0) {
            mysqli_query($conn, "ALTER TABLE docente ADD COLUMN ativo TINYINT(1) DEFAULT 1");
        }
        mysqli_query($conn, "INSERT INTO sys_migrations (migration_name) VALUES ('$migration_ativo')");
    }

    // 4. Migração: Adicionar novos campos na tabela turma
    $migration_turma_novos_campos = 'add_novos_campos_turma_2026_04_02';
    $check_turma = mysqli_query($conn, "SELECT id FROM sys_migrations WHERE migration_name = '$migration_turma_novos_campos'");
    if (mysqli_num_rows($check_turma) == 0) {
        $res = mysqli_query($conn, "SHOW COLUMNS FROM turma LIKE 'numero_proposta'");
        if (mysqli_num_rows($res) == 0) {
            mysqli_query($conn, "ALTER TABLE turma 
                ADD COLUMN numero_proposta VARCHAR(100) DEFAULT NULL AFTER valor_turma,
                ADD COLUMN tipo_atendimento ENUM('Empresa', 'Entidade', 'Balcão') DEFAULT 'Balcão' AFTER numero_proposta,
                ADD COLUMN parceiro VARCHAR(255) DEFAULT NULL AFTER tipo_atendimento,
                ADD COLUMN contato_parceiro VARCHAR(255) DEFAULT NULL AFTER parceiro");
        }
        mysqli_query($conn, "INSERT INTO sys_migrations (migration_name) VALUES ('$migration_turma_novos_campos')");
    }

    // 5. Migração: Adicionar tipo 'ausência' na tabela preparacao_atestados
    $migration_prep_ausencia = 'add_tipo_ausencia_to_preparacao_2026_04_27';
    $check_prep = mysqli_query($conn, "SELECT id FROM sys_migrations WHERE migration_name = '$migration_prep_ausencia'");
    if (mysqli_num_rows($check_prep) == 0) {
        mysqli_query($conn, "ALTER TABLE preparacao_atestados MODIFY COLUMN tipo ENUM('preparação', 'atestado', 'ausência') NOT NULL");
        mysqli_query($conn, "INSERT INTO sys_migrations (migration_name) VALUES ('$migration_prep_ausencia')");
    }

    // 6. Migração: Criar tabela de Áreas e popular dados iniciais
    $migration_areas = 'create_table_areas_2026_05_05';
    $check_areas = mysqli_query($conn, "SELECT id FROM sys_migrations WHERE migration_name = '$migration_areas'");
    if (mysqli_num_rows($check_areas) == 0) {
        // Criar tabela
        mysqli_query($conn, "CREATE TABLE IF NOT EXISTS area (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(255) UNIQUE NOT NULL
        )");

        // Áreas padronizadas coletadas dos formulários
        $initial_areas = [
            'TECNOLOGIA DA INFORMAÇÃO',
            'Mecatrônica / Automação',
            'Metalmecânica',
            'Logística',
            'Eletroeletrônica',
            'Gestão / Qualidade',
            'Alimentos',
            'Vestuário',
            'Soldagem',
            'Manutenção Industrial',
            'Automotiva',
            'Construção Civil',
            'Madeira e Mobiliário',
            'Administração e Gestão',
            'TI / Software',
            'Geral'
        ];

        foreach ($initial_areas as $area_name) {
            $area_esc = mysqli_real_escape_string($conn, $area_name);
            mysqli_query($conn, "INSERT IGNORE INTO area (nome) VALUES ('$area_esc')");
        }

        // Tentar capturar áreas customizadas que já existam nas tabelas
        $sources = [
            ['table' => 'curso', 'column' => 'area'],
            ['table' => 'docente', 'column' => 'area_conhecimento'],
            ['table' => 'ambiente', 'column' => 'area_vinculada']
        ];
        foreach ($sources as $src) {
            $res = mysqli_query($conn, "SELECT DISTINCT {$src['column']} as nome FROM {$src['table']} WHERE {$src['column']} IS NOT NULL AND {$src['column']} != ''");
            if ($res) {
                while ($row = mysqli_fetch_assoc($res)) {
                    $an = mysqli_real_escape_string($conn, $row['nome']);
                    mysqli_query($conn, "INSERT IGNORE INTO area (nome) VALUES ('$an')");
                }
            }
        }

        mysqli_query($conn, "INSERT INTO sys_migrations (migration_name) VALUES ('$migration_areas')");
    }

    return false;
}

// Executar se for incluído ou chamado
run_migrations($conn);
