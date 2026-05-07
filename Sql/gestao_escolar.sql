-- ============================================================
-- GESTÃO ESCOLAR — Script Completo de Criação do Banco
-- Copie e cole este script inteiro no phpMyAdmin ou MySQL CLI.
-- Ele cria o banco, todas as tabelas, índices e o usuário admin.
-- ============================================================

    CREATE DATABASE IF NOT EXISTS gestao_escolar DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
    USE gestao_escolar;

    -- ============================================================
    -- 1. AMBIENTE
    -- ============================================================
    CREATE TABLE IF NOT EXISTS ambiente (
        id INT(11) NOT NULL AUTO_INCREMENT,
        nome VARCHAR(255) DEFAULT NULL,
        tipo VARCHAR(100) DEFAULT NULL,
        area_vinculada VARCHAR(255) DEFAULT NULL,
        cidade VARCHAR(100) DEFAULT NULL,
        capacidade INT(11) DEFAULT NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- ============================================================
    -- 2. CURSO
    -- ============================================================
    CREATE TABLE IF NOT EXISTS curso (
        id INT(11) NOT NULL AUTO_INCREMENT,
        tipo VARCHAR(50) DEFAULT NULL,
        nome VARCHAR(255) DEFAULT NULL,
        area VARCHAR(255) DEFAULT NULL,
        carga_horaria_total INT(11) DEFAULT NULL,
        semestral TINYINT(1) DEFAULT NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- ============================================================
    -- 3. DOCENTE
    -- ============================================================
    CREATE TABLE IF NOT EXISTS docente (
        id INT(11) NOT NULL AUTO_INCREMENT,
        nome VARCHAR(255) DEFAULT NULL,
        area_conhecimento VARCHAR(255) DEFAULT NULL,
        cidade VARCHAR(100) DEFAULT NULL,
        carga_horaria_contratual INT(11) DEFAULT NULL,
        weekly_hours_limit INT NOT NULL DEFAULT 0,
        monthly_hours_limit INT NOT NULL DEFAULT 0,
        disponibilidade_semanal VARCHAR(255) DEFAULT NULL,
        areas_atuacao TEXT DEFAULT NULL,
        cor_agenda VARCHAR(7) DEFAULT '#ed1c24',
        dias_semana VARCHAR(255) DEFAULT NULL,
        dias_trabalho VARCHAR(255) DEFAULT NULL,
        tipo_contrato VARCHAR(100) DEFAULT NULL,
        turno VARCHAR(15) DEFAULT NULL,
        ativo TINYINT(1) DEFAULT 1,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- ============================================================
    -- 3.1 HORÁRIO DE TRABALHO (Blocos Sazonais)
    -- ============================================================
    CREATE TABLE IF NOT EXISTS horario_trabalho (
        id INT(11) NOT NULL AUTO_INCREMENT,
        docente_id INT(11) NOT NULL,
        dias VARCHAR(255) DEFAULT NULL,
        periodo VARCHAR(50) DEFAULT NULL,
        horario VARCHAR(50) DEFAULT NULL,
        data_inicio DATE DEFAULT NULL,
        data_fim DATE DEFAULT NULL,
        ano YEAR DEFAULT NULL,
        PRIMARY KEY (id),
        INDEX (docente_id),
        INDEX idx_ht_bloco (docente_id, data_inicio, data_fim),
        FOREIGN KEY (docente_id) REFERENCES docente(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- ============================================================
    -- 4. USUARIO
    -- ============================================================
    CREATE TABLE IF NOT EXISTS usuario (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        senha VARCHAR(255) NOT NULL,
        role ENUM('admin', 'gestor', 'professor', 'cri', 'secretaria') NOT NULL DEFAULT 'professor',
        docente_id INT DEFAULT NULL,
        ativo TINYINT(1) NOT NULL DEFAULT 1,
        obrigar_troca_senha TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (docente_id) REFERENCES docente(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- ============================================================
    -- 5. NOTIFICAÇÕES
    -- ============================================================
    CREATE TABLE IF NOT EXISTS notificacoes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        tipo VARCHAR(50) NOT NULL,
        titulo VARCHAR(255) NOT NULL,
        mensagem TEXT NOT NULL,
        link VARCHAR(255) DEFAULT NULL,
        lida TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuario(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- ============================================================
    -- 6. TURMA
    -- ============================================================
    CREATE TABLE IF NOT EXISTS turma (
        id INT(11) NOT NULL AUTO_INCREMENT,
        curso_id INT(11) DEFAULT NULL,
        tipo VARCHAR(50) DEFAULT NULL,
        sigla VARCHAR(50) DEFAULT NULL,
        vagas INT(11) DEFAULT NULL,
        periodo VARCHAR(50) DEFAULT NULL,
        data_inicio DATE DEFAULT NULL,
        data_fim DATE DEFAULT NULL,
        dias_semana VARCHAR(100) DEFAULT NULL,
        ambiente_id INT(11) DEFAULT NULL,
        componentes TEXT DEFAULT NULL,
        docente_id1 INT(11) DEFAULT NULL,
        docente_id2 INT(11) DEFAULT NULL,
        docente_id3 INT(11) DEFAULT NULL,
        docente_id4 INT(11) DEFAULT NULL,
        local VARCHAR(255) DEFAULT NULL,
        tipo_custeio ENUM('Gratuidade', 'Ressarcido') DEFAULT 'Gratuidade',
        previsao_despesa DECIMAL(10,2) DEFAULT 0.00,
        valor_turma DECIMAL(10,2) DEFAULT 0.00,
        numero_proposta VARCHAR(100) DEFAULT NULL,
        tipo_atendimento ENUM('Empresa', 'Entidade', 'Balcão') DEFAULT 'Balcão',
        parceiro VARCHAR(255) DEFAULT NULL,
        contato_parceiro VARCHAR(255) DEFAULT NULL,
        horario_inicio TIME DEFAULT '07:30',
        horario_fim TIME DEFAULT '11:30',
        horario_almoco TIME DEFAULT '02:00',
        tipo_agenda ENUM('recorrente', 'flexivel') DEFAULT 'recorrente',
        agenda_flexivel TEXT DEFAULT NULL,
        ativo TINYINT(1) DEFAULT 1,
        PRIMARY KEY (id),
        FOREIGN KEY (curso_id) REFERENCES curso(id),
        FOREIGN KEY (ambiente_id) REFERENCES ambiente(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- ============================================================
    -- 7. AGENDA
    -- ============================================================
    CREATE TABLE IF NOT EXISTS agenda (
        id INT(11) NOT NULL AUTO_INCREMENT,
        turma_id INT(11) DEFAULT NULL,
        docente_id INT(11) DEFAULT NULL,
        ambiente_id INT(11) DEFAULT NULL,
        dia_semana VARCHAR(50) DEFAULT NULL,
        periodo VARCHAR(20) DEFAULT 'Manhã',
        horario_inicio TIME DEFAULT NULL,
        horario_fim TIME DEFAULT NULL,
        data DATE DEFAULT NULL,
        status ENUM('CONFIRMADO','RESERVADO') DEFAULT 'CONFIRMADO',
        PRIMARY KEY (id),
        FOREIGN KEY (turma_id) REFERENCES turma(id),
        FOREIGN KEY (docente_id) REFERENCES docente(id),
        FOREIGN KEY (ambiente_id) REFERENCES ambiente(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- ============================================================
    -- 8. RESERVAS
    -- ============================================================
    CREATE TABLE IF NOT EXISTS reservas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        docente_id INT NOT NULL,
        usuario_id INT NOT NULL,
        curso_id INT DEFAULT NULL,
        ambiente_id INT DEFAULT NULL,
        turma_id INT DEFAULT NULL,
        data_inicio DATE NOT NULL,
        data_fim DATE NOT NULL,
        dias_semana VARCHAR(255) NOT NULL,
        hora_inicio TIME NOT NULL,
        hora_fim TIME NOT NULL,
        horario_almoco TIME DEFAULT '02:00',
        periodo VARCHAR(50) DEFAULT NULL,
        sigla VARCHAR(50) DEFAULT NULL,
        vagas INT DEFAULT NULL,
        local VARCHAR(255) DEFAULT NULL,
        tipo VARCHAR(50) DEFAULT NULL,
        tipo_custeio ENUM('Gratuidade', 'Ressarcido') DEFAULT 'Gratuidade',
        previsao_despesa DECIMAL(10,2) DEFAULT 0.00,
        valor_turma DECIMAL(10,2) DEFAULT 0.00,
        numero_proposta VARCHAR(100) DEFAULT NULL,
        tipo_atendimento ENUM('Empresa', 'Entidade', 'Balcão') DEFAULT 'Balcão',
        parceiro VARCHAR(255) DEFAULT NULL,
        contato_parceiro VARCHAR(255) DEFAULT NULL,
        status ENUM('ativo', 'concluido', 'PENDENTE', 'APROVADA', 'RECUSADA', 'CONCLUIDA') NOT NULL DEFAULT 'PENDENTE',
        tipo_agenda ENUM('recorrente', 'flexivel') DEFAULT 'recorrente',
        agenda_flexivel TEXT DEFAULT NULL,
        notas TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (docente_id) REFERENCES docente(id),
        FOREIGN KEY (usuario_id) REFERENCES usuario(id),
        FOREIGN KEY (curso_id) REFERENCES curso(id),
        FOREIGN KEY (ambiente_id) REFERENCES ambiente(id),
        FOREIGN KEY (turma_id) REFERENCES turma(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- ============================================================
    -- 9. FERIADOS (HOLIDAYS)
    -- ============================================================
    CREATE TABLE IF NOT EXISTS holidays (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        date DATE NOT NULL,
        end_date DATE DEFAULT NULL,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES usuario(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- ============================================================
    -- 10. FÉRIAS (VACATIONS)
    -- ============================================================
    CREATE TABLE IF NOT EXISTS vacations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type ENUM('collective', 'individual') NOT NULL,
        teacher_id INT DEFAULT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (teacher_id) REFERENCES docente(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- ============================================================
    -- 11. PREPARAÇÃO E ATESTADOS
    -- ============================================================
    CREATE TABLE IF NOT EXISTS preparacao_atestados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        docente_id INT NOT NULL,
        tipo ENUM('preparação', 'atestado', 'ausência') NOT NULL,
        data_inicio DATE NOT NULL,
        data_fim DATE NOT NULL,
        dias_semana VARCHAR(255) DEFAULT NULL,
        horario_inicio TIME DEFAULT NULL,
        horario_fim TIME DEFAULT NULL,
        status VARCHAR(50) DEFAULT 'ativo',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (docente_id) REFERENCES docente(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- ============================================================
    -- 12. METAS E CUSTOS A/H (Gestão Financeira)
    -- ============================================================
    CREATE TABLE IF NOT EXISTS metas_ah (
        ano YEAR NOT NULL PRIMARY KEY,
        cai_horas INT DEFAULT 0,
        cai_alunos INT DEFAULT 0,
        ct_horas INT DEFAULT 0,
        ct_alunos INT DEFAULT 0,
        fic_horas INT DEFAULT 0,
        fic_alunos INT DEFAULT 0,
        despesa_anual DECIMAL(15,2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- ============================================================
    -- 13. ÁREAS DE CONHECIMENTO
    -- ============================================================
    CREATE TABLE IF NOT EXISTS area (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(255) UNIQUE NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    INSERT IGNORE INTO area (nome) VALUES 
    ('TECNOLOGIA DA INFORMAÇÃO'), ('Mecatrônica / Automação'), ('Metalmecânica'), 
    ('Logística'), ('Eletroeletrônica'), ('Gestão / Qualidade'), ('Alimentos'), 
    ('Vestuário'), ('Soldagem'), ('Manutenção Industrial'), ('Automotiva'), 
    ('Construção Civil'), ('Madeira e Mobiliário'), ('Administração e Gestão'), ('TI / Software');

    -- ============================================================
    -- ÍNDICES DE PERFORMANCE
    -- ============================================================
    CREATE INDEX idx_reservas_docente ON reservas(docente_id, status);
    CREATE INDEX idx_reservas_datas ON reservas(data_inicio, data_fim, status);
    CREATE INDEX idx_reservas_usuario ON reservas(usuario_id, status);
    CREATE INDEX idx_agenda_docente ON agenda(docente_id, data);
    CREATE INDEX idx_agenda_turma ON agenda(turma_id);
    CREATE INDEX idx_notificacoes_usuario ON notificacoes(usuario_id, lida);

    -- ============================================================
    -- USUÁRIO ADMINISTRADOR PADRÃO
    -- Senha: admin123 (hash bcrypt)
    -- ============================================================
    INSERT INTO usuario (nome, email, senha, role, obrigar_troca_senha) 
    VALUES ('Administrador', 'admin@senai.br', '$2y$10$d8zHMItalmR8WxmucXWdquWSHknxyWy.imiT3sNO6H3L36DUcLVly', 'admin', 1);

    -- ============================================================
    -- COMANDOS DE ATUALIZAÇÃO (PARA QUEM JÁ POSSUI O BANCO)
    -- Execute apenas estes comandos se você não deseja refazer o banco.
    -- ============================================================

    /*
    -- 1. Criar tabela de metas
    CREATE TABLE IF NOT EXISTS metas_ah (
        ano YEAR NOT NULL PRIMARY KEY,
        cai_horas INT DEFAULT 0,
        cai_alunos INT DEFAULT 0,
        ct_horas INT DEFAULT 0,
        ct_alunos INT DEFAULT 0,
        fic_horas INT DEFAULT 0,
        fic_alunos INT DEFAULT 0,
        despesa_anual DECIMAL(15,2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    -- 2. Adicionar colunas financeiras e de status se não existirem
    ALTER TABLE turma ADD COLUMN IF NOT EXISTS tipo_custeio ENUM('Gratuidade', 'Ressarcido') DEFAULT 'Gratuidade';
    ALTER TABLE turma ADD COLUMN IF NOT EXISTS previsao_despesa DECIMAL(10,2) DEFAULT 0.00;
    ALTER TABLE turma ADD COLUMN IF NOT EXISTS valor_turma DECIMAL(10,2) DEFAULT 0.00;
    ALTER TABLE turma ADD COLUMN IF NOT EXISTS numero_proposta VARCHAR(100) DEFAULT NULL;
    ALTER TABLE turma ADD COLUMN IF NOT EXISTS tipo_atendimento ENUM('Empresa', 'Entidade', 'Balcão') DEFAULT 'Balcão';
    ALTER TABLE turma ADD COLUMN IF NOT EXISTS parceiro VARCHAR(255) DEFAULT NULL;
    ALTER TABLE turma ADD COLUMN IF NOT EXISTS contato_parceiro VARCHAR(255) DEFAULT NULL;
    ALTER TABLE turma ADD COLUMN IF NOT EXISTS ativo TINYINT(1) DEFAULT 1;

    ALTER TABLE docente ADD COLUMN IF NOT EXISTS ativo TINYINT(1) DEFAULT 1;
    ALTER TABLE usuario ADD COLUMN IF NOT EXISTS ativo TINYINT(1) DEFAULT 1;

    ALTER TABLE reservas ADD COLUMN IF NOT EXISTS tipo_custeio ENUM('Gratuidade', 'Ressarcido') DEFAULT 'Gratuidade';
    ALTER TABLE reservas ADD COLUMN IF NOT EXISTS previsao_despesa DECIMAL(10,2) DEFAULT 0.00;
    ALTER TABLE reservas ADD COLUMN IF NOT EXISTS valor_turma DECIMAL(10,2) DEFAULT 0.00;
    ALTER TABLE reservas ADD COLUMN IF NOT EXISTS numero_proposta VARCHAR(100) DEFAULT NULL;
    ALTER TABLE reservas ADD COLUMN IF NOT EXISTS tipo_atendimento ENUM('Empresa', 'Entidade', 'Balcão') DEFAULT 'Balcão';
    ALTER TABLE reservas ADD COLUMN IF NOT EXISTS parceiro VARCHAR(255) DEFAULT NULL;
    ALTER TABLE reservas ADD COLUMN IF NOT EXISTS contato_parceiro VARCHAR(255) DEFAULT NULL;

    -- 3. Suporte a Agenda Flexível (Datas Manuais)
    ALTER TABLE turma ADD COLUMN IF NOT EXISTS tipo_agenda ENUM('recorrente', 'flexivel') DEFAULT 'recorrente';
    ALTER TABLE turma ADD COLUMN IF NOT EXISTS agenda_flexivel TEXT DEFAULT NULL;
    ALTER TABLE reservas ADD COLUMN IF NOT EXISTS tipo_agenda ENUM('recorrente', 'flexivel') DEFAULT 'recorrente';
    ALTER TABLE reservas ADD COLUMN IF NOT EXISTS agenda_flexivel TEXT DEFAULT NULL;

    -- 4. Suporte ao novo cargo Secretaria
    ALTER TABLE usuario MODIFY COLUMN role ENUM('admin', 'gestor', 'professor', 'cri', 'secretaria') NOT NULL DEFAULT 'professor';

    -- 5. Suporte ao Horário de Almoço Configurável
    ALTER TABLE turma ADD COLUMN IF NOT EXISTS horario_almoco TIME DEFAULT '02:00' AFTER horario_fim;
    ALTER TABLE reservas ADD COLUMN IF NOT EXISTS horario_almoco TIME DEFAULT '02:00' AFTER hora_fim;

    -- 6. Suporte a Áreas Dinâmicas e Ausências
    CREATE TABLE IF NOT EXISTS area (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(255) UNIQUE NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

    ALTER TABLE preparacao_atestados MODIFY COLUMN tipo ENUM('preparação', 'atestado', 'ausência') NOT NULL;
    */

-- ============================================================
-- SCRIPT DE ATUALIZAÇÃO (COLAR NO SERVIDOR SEM DELETAR O BANCO)
-- Pode ser executado múltiplas vezes sem risco (idempotente)
-- ============================================================

ALTER TABLE reservas ADD COLUMN IF NOT EXISTS turma_id INT DEFAULT NULL AFTER ambiente_id;

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservas' AND COLUMN_NAME = 'turma_id' AND REFERENCED_TABLE_NAME = 'turma');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE reservas ADD FOREIGN KEY (turma_id) REFERENCES turma(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE reservas MODIFY COLUMN status ENUM('ativo', 'concluido', 'PENDENTE', 'APROVADA', 'REJEITADA', 'RECUSADA', 'CONCLUIDA') NOT NULL DEFAULT 'PENDENTE';

UPDATE reservas SET status = 'RECUSADA' WHERE status = 'REJEITADA';

-- 5. Vincular retroativamente reservas concluídas às turmas já existentes
UPDATE reservas r
    JOIN turma t ON t.sigla = r.sigla AND t.docente_id1 = r.docente_id
SET r.turma_id = t.id
WHERE r.status = 'CONCLUIDA' AND r.turma_id IS NULL AND r.sigla IS NOT NULL AND r.sigla != '';
