# 🚀 Sistema de Gestão Escolar e Agendamento de Horários

Um sistema web robusto e intuitivo desenvolvido para gerenciar o agendamento de turmas, disponibilidade de docentes e ocupação de ambientes. Focado na automação de processos, integridade de dados e saúde financeira escolar.


## ✨ Funcionalidades Principais

*   **Dashboard Inteligente**: Visualização em tempo real da ocupação de professores e salas, com filtros avançados e indicadores de desempenho.
*   **Monitoramento de Carga Horária (Workload)**: 
    *   **Barra de Saldo Individual**: Acompanhamento do saldo de horas de cada professor até o final do ano.
    *   **Admissão Proporcional**: Cálculo automático do potencial anual baseado na data de ingresso.
*   **Produção Aluno/Hora**: Sistema de métricas acadêmicas com acompanhamento de evasão e adição de alunos (**Métrica SENAI**).
*   **Gestão Financeira & Vendas**: Dashboard dedicado para acompanhamento de ressarcimento, previsão de despesas e lucratividade por turma.
*   **Integração com Excel**: Módulos robustos para importação e exportação de dados em massa (Docentes, Cursos, Turmas e Agenda).
*   **Estratégia de Substituição**: Módulo dedicado para encontrar substitutos temporários com base na área de conhecimento e janelas de disponibilidade.
*   **Gestão de Calendário**: Controle de feriados globais e férias docentes com suporte a recessos acadêmicos.
*   **Sistema de Notificações**: Alertas em tempo real sobre conflitos de horários e lembretes administrativos.
*   **Auditória e Reparo**: Ferramentas para regeneração global de agendas e limpeza de dados duplicados.


## 🛠️ Tecnologias Utilizadas

*   **Backend**: PHP 8.1+
*   **Banco de Dados**: MySQL / MariaDB (InnoDB)
*   **Frontend**: HTML5, JavaScript (ES6+), Vanilla CSS
*   **Gráficos**: Chart.js para visualização analítica profunda
*   **Design**: Premium UI com foco em UX, Glassmorphism e micro-animações
*   **Ícones**: Font Awesome 6
*   **Tipografia**: Google Fonts (Inter / Roboto / Outfit)

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
├───assets/          # Recursos visuais (Imagens, Mockups, Ícones)
├───css/             # Estilização CSS personalizada (Glassmorphism UI)
├───js/              # Scripts JavaScript (Dashboard, APIs, Charts)
├───php/             
│   ├───components/  # Componentes reutilizáveis (Header, Sidebar)
│   ├───configs/     # Conexões DB e helpers (utils.php - motor de cálculos)
│   ├───controllers/ # Lógica de negócio e endpoints AJAX
│   ├───models/      # Modelagem de dados
│   ├───scripts/     # Scripts de manutenção, migração e auditoria
│   └───views/       # Telas do sistema (Docentes, Turmas, Financeiro)
├───Sql/             # Scripts de criação e evolução do banco de dados
└───index.php        # Interface principal / Dashboard Unificado
```

## 🔒 Segurança e Regras de Negócio

O sistema implementa validações críticas automatizadas:

*   **Detecção de Conflitos**: Impede sobreposição de horários para professores e ambientes.
*   **Regência vs. Atividade**: Cálculo de disponibilidade deduz automaticamente as horas de planejamento (Hora-Atividade).
*   **Interatividade Premium**: Fechamento inteligente de modais ao clicar no overlay (Click-to-Close).
*   **Controle de Acesso**: Sistema de permissões baseado em papéis (RBAC).

## 📝 Autor
Desenvolvido para otimização de fluxos acadêmicos e gestão estratégica de recursos educacionais.

---
**Última atualização:** 15 de Abril de 2026.
