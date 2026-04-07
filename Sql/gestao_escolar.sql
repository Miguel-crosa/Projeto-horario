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
-- 3.1 HORÁRIO DE TRABALHO
-- ============================================================
CREATE TABLE IF NOT EXISTS horario_trabalho (
    id INT(11) NOT NULL AUTO_INCREMENT,
    docente_id INT(11) NOT NULL,
    dias VARCHAR(255) DEFAULT NULL,
    periodo VARCHAR(50) DEFAULT NULL,
    horario VARCHAR(50) DEFAULT NULL,
    PRIMARY KEY (id),
    INDEX (docente_id),
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
    role ENUM('admin', 'gestor', 'professor', 'cri') NOT NULL DEFAULT 'professor',
    docente_id INT DEFAULT NULL,
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
    horario_inicio TIME DEFAULT '07:30',
    horario_fim TIME DEFAULT '11:30',
    tipo_custeio ENUM('Gratuidade', 'Ressarcido') DEFAULT 'Gratuidade',
    previsao_despesa DECIMAL(10,2) DEFAULT 0.00,
    valor_turma DECIMAL(10,2) DEFAULT 0.00,
    numero_proposta VARCHAR(100) DEFAULT NULL,
    tipo_atendimento ENUM('Empresa', 'Entidade', 'Balcão') DEFAULT 'Balcão',
    parceiro VARCHAR(255) DEFAULT NULL,
    contato_parceiro VARCHAR(255) DEFAULT NULL,
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
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    dias_semana VARCHAR(255) NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fim TIME NOT NULL,
    periodo VARCHAR(50) DEFAULT NULL,
    sigla VARCHAR(50) DEFAULT NULL,
    vagas INT DEFAULT NULL,
    local VARCHAR(255) DEFAULT NULL,
    tipo VARCHAR(50) DEFAULT NULL,
    tipo_custeio ENUM('Gratuidade', 'Ressarcido') DEFAULT 'Gratuidade',
    previsao_despesa DECIMAL(10,2) DEFAULT 0.00,
    valor_turma DECIMAL(10,2) DEFAULT 0.00,
    numero_proposta VARCHAR(100) DEFAULT NULL,
    tipo_atendimento ENUM('Empresa','Entidade','Balcão') DEFAULT 'Balcão',
    parceiro VARCHAR(255) DEFAULT NULL,
    contato_parceiro VARCHAR(255) DEFAULT NULL,
    status ENUM('ativo', 'concluido', 'PENDENTE', 'APROVADA', 'REJEITADA', 'CONCLUIDA') NOT NULL DEFAULT 'PENDENTE',
    notas TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (docente_id) REFERENCES docente(id),
    FOREIGN KEY (usuario_id) REFERENCES usuario(id),
    FOREIGN KEY (curso_id) REFERENCES curso(id),
    FOREIGN KEY (ambiente_id) REFERENCES ambiente(id)
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
    tipo ENUM('preparação', 'atestado') NOT NULL,
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
-- ============================================================
INSERT INTO usuario (nome, email, senha, role, obrigar_troca_senha) 
VALUES ('Administrador', 'admin@senai.br', '$2y$10$d8zHMItalmR8WxmucXWdquWSHknxyWy.imiT3sNO6H3L36DUcLVly', 'admin', 1);

INSERT INTO usuario (nome, email, senha, role, obrigar_troca_senha) 
VALUES ('roberto', 'roberto@senai.br', '$2y$10$XFjAiGRelFifZrzlE.pWheJrBhacywoE.14JdrbgWM7JsNLrF4b7G', 'admin', 0);


-- ============================================================
-- PARA QUEM NÃO QUER APAGAR O BANCO (COPIE E COLE PARA ATUALIZAR)
-- ============================================================

/*
-- 1. ADICIONAR CAMPOS NA TABELA 'DOCENTE'
ALTER TABLE docente ADD COLUMN IF NOT EXISTS weekly_hours_limit INT NOT NULL DEFAULT 0 AFTER carga_horaria_contratual;
ALTER TABLE docente ADD COLUMN IF NOT EXISTS monthly_hours_limit INT NOT NULL DEFAULT 0 AFTER weekly_hours_limit;
ALTER TABLE docente ADD COLUMN IF NOT EXISTS ativo TINYINT(1) DEFAULT 1;

-- 2. ADICIONAR CAMPOS NA TABELA 'TURMA'
ALTER TABLE turma ADD COLUMN IF NOT EXISTS horario_inicio TIME DEFAULT '07:30' AFTER local;
ALTER TABLE turma ADD COLUMN IF NOT EXISTS horario_fim TIME DEFAULT '11:30' AFTER horario_inicio;
ALTER TABLE turma ADD COLUMN IF NOT EXISTS tipo_custeio ENUM('Gratuidade', 'Ressarcido') DEFAULT 'Gratuidade';
ALTER TABLE turma ADD COLUMN IF NOT EXISTS previsao_despesa DECIMAL(10,2) DEFAULT 0.00;
ALTER TABLE turma ADD COLUMN IF NOT EXISTS valor_turma DECIMAL(10,2) DEFAULT 0.00;
ALTER TABLE turma ADD COLUMN IF NOT EXISTS numero_proposta VARCHAR(100) DEFAULT NULL;
ALTER TABLE turma ADD COLUMN IF NOT EXISTS tipo_atendimento ENUM('Empresa', 'Entidade', 'Balcão') DEFAULT 'Balcão';
ALTER TABLE turma ADD COLUMN IF NOT EXISTS parceiro VARCHAR(255) DEFAULT NULL;
ALTER TABLE turma ADD COLUMN IF NOT EXISTS contato_parceiro VARCHAR(255) DEFAULT NULL;
ALTER TABLE turma ADD COLUMN IF NOT EXISTS ativo TINYINT(1) DEFAULT 1;

-- 3. ADICIONAR CAMPOS NA TABELA 'RESERVAS'
ALTER TABLE reservas ADD COLUMN IF NOT EXISTS tipo_custeio ENUM('Gratuidade', 'Ressarcido') DEFAULT 'Gratuidade' AFTER local;
ALTER TABLE reservas ADD COLUMN IF NOT EXISTS previsao_despesa DECIMAL(10,2) DEFAULT 0.00 AFTER tipo_custeio;
ALTER TABLE reservas ADD COLUMN IF NOT EXISTS valor_turma DECIMAL(10,2) DEFAULT 0.00 AFTER previsao_despesa;
ALTER TABLE reservas ADD COLUMN IF NOT EXISTS numero_proposta VARCHAR(100) DEFAULT NULL;
ALTER TABLE reservas ADD COLUMN IF NOT EXISTS tipo_atendimento ENUM('Empresa', 'Entidade', 'Balcão') DEFAULT 'Balcão';
ALTER TABLE reservas ADD COLUMN IF NOT EXISTS parceiro VARCHAR(255) DEFAULT NULL;
ALTER TABLE reservas ADD COLUMN IF NOT EXISTS contato_parceiro VARCHAR(255) DEFAULT NULL;
*/