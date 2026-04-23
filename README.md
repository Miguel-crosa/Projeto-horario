# 🚀 Sistema de Gestão Escolar e Agendamento de Horários

Um sistema web robusto e intuitivo desenvolvido para gerenciar o agendamento de turmas, disponibilidade de docentes e ocupação de ambientes. Focado na automação de processos, integridade de dados e saúde financeira escolar.


## ✨ Funcionalidades Principais

*   **Dashboard de Alta Performance**: Visualização em tempo real da ocupação de professores e salas, com filtros avançados, indicadores de saúde financeira e **navegação paginada (periodo de 30 dias)** com carregamento AJAX.
*   **Motor de Importação Inteligente (Excel)**: 
    *   **Multi-abas**: Suporte a processamento em lote de Cursos, Docentes, Ambientes, Turmas e Agendas em um único arquivo.
    *   **Normalização Heurística**: Tratamento automático de datas (Serial Excel/ISO), nomes com acentos e mapeamento inteligente de colunas.
    *   **Auto-Agenda**: Geração automática de cronogramas completos baseados na carga horária e dias da semana caso a aba de agenda seja omitida.
*   **Gestão de Metas e Custos A/H**: Painel analítico para acompanhamento de Custo Meta vs. Custo Realizado, incluindo ferramentas de **Simulação de Cenários** (What-if analysis).
*   **Logística de Professores e Ambientes**:
    *   **Estratégia de Substituição**: Busca de substitutos por área de conhecimento e janelas de disponibilidade.
    *   **Gestão de Férias e Feriados**: Bloqueio automático de agendas e recalculo de disponibilidade baseado em calendários civis e férias docentes.
    *   **Workload (Carga Horária)**: Monitoramento de saldo de horas anual com cálculo proporcional para admissões recentes.
*   **Métrica SENAI (Produção Aluno/Hora)**: Acompanhamento detalhado de matrículas, evasão e aditamentos com impacto imediato nos indicadores de produtividade.
*   **Redirecionamento Inteligente**: Sistema de notificações globais com links de "deep-linking" que levam diretamente ao registro com destaque visual.

## 🛠️ Tecnologias Utilizadas

*   **Backend**: PHP 8.1+ (Arquitetura MVC simplificada)
*   **Banco de Dados**: MySQL / MariaDB (InnoDB) com transações ACID
*   **Frontend**: HTML5, JavaScript (ES6+), Vanilla CSS
*   **Gráficos**: Chart.js 4.x para visualização analítica profunda
*   **Design**: Premium UI (Dark/Light Mode), Glassmorphism e micro-animações
*   **Integração**: PHPSpreadsheet / Custom Excel Parser

## ⚙️ Instalação e Configuração

### 1. Pré-requisitos
*   Servidor local (XAMPP, WAMP, Laragon) com PHP 8.0+.
*   Servidor de Banco de Dados MySQL.

### 2. Configurar Banco de Dados
1. Crie um banco de dados chamado `gestao_escolar`.
2. Importe o script SQL localizado em: `Sql/gestao_escolar.sql`

### 3. Configurar Conexão
Edite o arquivo `php/configs/db.php` com suas credenciais locais:
```php
$host = 'localhost';
$user = 'root';
$pass = ''; // Sua senha do MySQL
$db   = 'gestao_escolar';
```

### 4. Acesso Padrão
*   **URL**: `http://localhost/nome-da-pasta-do-projeto/`
*   **Usuário**: `admin@senai.br`
*   **Senha**: `senaisp`

## 📂 Estrutura do Projeto

```text
├───assets/          # Recursos visuais e mockups
├───css/             # Design System (Variáveis HSL, Glassmorphism)
├───js/              # Lógica de negócio (Dashboards, Charts, Notificações)
├───php/             
│   ├───components/  # Componentes UI (Header, Sidebar, Forms Unificados)
│   ├───configs/     # Motor de cálculos (utils.php) e Conexão DB
│   ├───controllers/ # Endpoints AJAX e Processamento de dados
│   ├───models/      # Abstrações de dados
│   └───views/       # Telas de Gestão (CRI, Administrativo, Docente)
├───Sql/             # Evolução do Schema (DML/DDL)
└───index.php        # Dashboard Unificado e Roteamento Principal
```

## 🔒 Segurança e Regras de Negócio

*   **Validação de Conflitos**: Algoritmo robusto que impede a alocação dupla de docentes ou salas no mesmo horário/dia.
*   **Controle de Acesso (RBAC)**: Níveis de permissão distintos (Admin, Gestor, CRI, Professor) com restrições de visualização e edição.
*   **Cálculo de Hora-Atividade**: Dedução automática de horas de planejamento conforme regras contratuais.
*   **Integridade de Dados**: Uso de transações SQL durante importações massivas para garantir "rollback" em caso de inconsistência no Excel.

## 📝 Autor
Desenvolvido para otimização estratégica de recursos educacionais e automação de fluxos acadêmicos SENAI.

---
**Última atualização:** 23 de Abril de 2026.
