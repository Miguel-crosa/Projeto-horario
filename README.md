# 🚀 Sistema de Gestão Escolar e Agendamento de Horários

Um sistema web robusto e intuitivo desenvolvido para gerenciar o agendamento de turmas, disponibilidade de docentes e ocupação de ambientes. Focado na automação de processos e integridade de dados.

---

## ✨ Funcionalidades Principais

- **Dashboard Inteligente**: Visualização rápida da disponibilidade dos docentes com filtros avançados por área de conhecimento.
- **Gestão de Turmas**: Cadastro e edição de turmas com cálculo automático da data de término, respeitando feriados e férias.
- **Corpo Docente**: Gerenciamento de professores, áreas de atuação, carga horária contratual e limites de horas.
- **Controle de Ambientes**: Gestão de salas e laboratórios com detecção automática de conflitos de ocupação.
- **Gestão de Calendário**: Cadastro centralizado de feriados globais e férias (coletivas ou individuais) para docentes.
- **Auditória e Reparo**: Ferramenta exclusiva para ajuste e regeneração global de agendas, garantindo precisão total nos horários.
- **Financeiro & Custeio**: Registro de tipo de custeio (Gratuidade/Ressarcido), previsão de despesas e valores arrecadados.

---

## 🛠️ Tecnologias Utilizadas

- **Backend**: PHP 8+
- **Banco de Dados**: MySQL / MariaDB
- **Frontend**: HTML5, JavaScript (ES6+), Vanilla CSS
- **Design**: Premium UI com foco em UX, Glassmorphism e animações suaves
- **Ícones**: Font Awesome 6
- **Tipografia**: Google Fonts (Inter/Roboto)

---

## ⚙️ Instalação e Configuração

### 1. Pré-requisitos
- Servidor local (XAMPP, WAMP, Laragon) com PHP 8.0+.
- Servidor de Banco de Dados MySQL.

### 2. Configurar Banco de Dados
1. Crie um banco de dados chamado `gestao_escolar`.
2. Importe o script SQL localizado em:
   `Sql/gestao_escolar.sql`

### 3. Configurar Conexão
Edite o arquivo `php/configs/db.php` com suas credenciais locais:
```php
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'gestao_escolar';
```

### 4. Acesso Padrão
- **URL**: `http://localhost/aulaPHP/2026/projetos/Projeto-horario/`
- **Usuário**: `admin@senai.br`
- **Senha**: `senaisp`

---

## 📂 Estrutura do Projeto

```text
├───css/             # Estilização CSS (Dashboard, Formulários, Login)
├───js/              # Scripts JavaScript globais
├───php/             
│   ├───components/  # Componentes reutilizáveis (Header, Footer, Menu)
│   ├───configs/     # Conexões DB, Auth e Helpers (Utils)
│   ├───controllers/ # Lógica de processamento e APIs AJAX
│   ├───models/      # Modelagem de dados (Agenda, etc.)
│   └───views/       # Telas do sistema (Docentes, Turmas, Reservas)
├───Sql/             # Scripts de criação e migração do banco
└───index.php        # Interface principal / Dashboard
```

---

## 🔒 Segurança e Regras de Negócio

O sistema implementa validações críticas:
- **Conflito de Professor**: Um docente não pode estar em duas turmas no mesmo horário.
- **Conflito de Sala**: Um ambiente não pode ser alocado para turmas diferentes simultaneamente.
- **Cálculo de Fim de Turma**: Utiliza a carga horária total do curso dividida pelas horas/dia, pulando automaticamente qualquer feriado ou dia de férias cadastrado.
- **Controle de Acesso**: Sistema de login com níveis de permissão (Admin, Gestor, Professor).

---

## 📝 Autor

Desenvolvido para otimização de fluxos acadêmicos e gestão de recursos curriculares.

---
*Este projeto foi reconfigurado em Git em 06/04/2026.*
