<?php
require_once __DIR__ . '/../configs/auth.php';
requireAuth();
checkForcePasswordChange();

$current_page = basename($_SERVER['PHP_SELF']);
$theme = $_COOKIE['tema'] ?? 'claro';

$path_parts = explode('/', trim($_SERVER['PHP_SELF'], '/'));
$is_in_subdir = !empty(array_intersect(['views', 'controllers', 'components', 'configs'], $path_parts));
$prefix = $is_in_subdir ? '../../' : '';

// Bloqueio de acesso direto via URL para perfis não autorizados
if (isCRI() || isProfessor() || isSecretaria()) {
    $restricted = [
        'professores.php', 'cursos.php', 'salas.php', 'usuarios.php',
        'professores_form.php', 'cursos_form.php', 'salas_form.php',
        'form_turma_unificado.php', 'preparacao.php', 'preparacao_form.php', 'feriados.php', 'ferias.php',
        'turmas_form.php'
    ];
    
    // CRI e Professores também não veem a lista de turmas
    if (isProfessor() || isCRI()) {
        $restricted[] = 'turmas.php';
    }

    if (in_array($current_page, $restricted)) {
        header('Location: ' . $prefix . 'index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br" data-tema="<?= htmlspecialchars($theme) ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão Escolar</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $prefix ?>css/nav.css">
    <link rel="stylesheet" href="<?= $prefix ?>css/style.css">
    <link rel="stylesheet" href="<?= $prefix ?>css/header.css">
    <link rel="stylesheet" href="<?= $prefix ?>css/login.css">
    <link rel="stylesheet" href="<?= $prefix ?>css/relatorio_mensal.css">
    <link rel="icon" type="image/x-icon" href="<?= $prefix ?>assets/icon/favicon.ico">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="<?= $prefix ?>js/dashboard_agenda.js" defer></script>
    <script src="<?= $prefix ?>js/relatorio_mensal.js" defer></script>

    <style>
        /* Skeleton Loaders */
        .skeleton {
            background: linear-gradient(90deg, rgba(0,0,0,0.06) 25%, rgba(0,0,0,0.03) 50%, rgba(0,0,0,0.06) 75%);
            background-size: 200% 100%;
            animation: skeleton-loading 1.5s infinite;
            border-radius: 4px;
            display: inline-block;
            height: 1em;
            width: 100%;
        }
        [data-tema="escuro"] .skeleton {
            background: linear-gradient(90deg, rgba(255,255,255,0.05) 25%, rgba(255,255,255,0.02) 50%, rgba(255,255,255,0.05) 75%);
            background-size: 200% 100%;
        }
        @keyframes skeleton-loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Command Palette Modal */
        #command-palette-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 10000;
        }
        #command-palette {
            display: none;
            position: fixed;
            top: 15%;
            left: 50%;
            transform: translateX(-50%);
            width: 600px;
            max-width: 90vw;
            background: rgba(30, 41, 59, 0.98);
            backdrop-filter: blur(20px);
            border-radius: 16px;
            box-shadow: 0 20px 70px rgba(0,0,0,0.6);
            border: 1px solid rgba(255,255,255,0.1);
            z-index: 10001;
            overflow: hidden;
            animation: cpSlideIn 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        @keyframes cpSlideIn {
            from { opacity: 0; transform: translate(-50%, -20px); }
            to { opacity: 1; transform: translate(-50%, 0); }
        }
        .cp-input-wrapper {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .cp-input {
            background: transparent;
            border: none;
            color: #fff;
            font-size: 1.1rem;
            width: 100%;
            outline: none;
            font-family: 'Inter', sans-serif;
        }
        .cp-results {
            max-height: 400px;
            overflow-y: auto;
            padding: 10px;
        }
        .cp-item {
            padding: 12px 15px;
            border-radius: 10px;
            color: #cbd5e1;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.2s;
            text-decoration: none;
        }
        .cp-item:hover, .cp-item.active {
            background: rgba(255,255,255,0.1);
            color: #fff;
        }
        .cp-shortcut {
            margin-left: auto;
            font-size: 0.7rem;
            background: rgba(255,255,255,0.1);
            padding: 2px 6px;
            border-radius: 4px;
            color: #94a3b8;
        }
        .cp-section-title {
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #64748b;
            padding: 10px 15px 5px;
            font-weight: 800;
        }
    </style>
</head>

<body class="<?= ($_COOKIE['sidebar'] ?? '') == 'closed' ? 'sidebar-closed' : '' ?>">

    <!-- Global Page Preloader -->
    <div id="page-preloader">
        <div class="preloader-container">
            <div class="preloader-logo">
                <img src="<?= $prefix ?>assets/images/senailogo.png" alt="SENAI">
            </div>
            <div class="preloader-spinner"></div>
            <div class="preloader-text">Carregando Sistema...</div>
        </div>
    </div>

    <script>
        (function () {
            const preloader = document.getElementById('page-preloader');
            const isImportPage = window.location.pathname.includes('import_excel.php');
            const forceLoader = new URLSearchParams(window.location.search).has('force_loader');

            if (isImportPage || forceLoader) {
                if (preloader) preloader.classList.add('active');
            }

            window.addEventListener('load', function () {
                if (preloader && preloader.classList.contains('active')) {
                    setTimeout(() => {
                        preloader.classList.add('fade-out');
                        setTimeout(() => {
                            preloader.classList.remove('active', 'fade-out');
                        }, 500);
                    }, 300);
                }
            });
        })();
    </script>

    <?php
    /* 
     * @ATENÇÃO @ Miguel: NÃO MOVA ESTE MODAL.
     * O modal de troca de senha DEVE ficar no topo do <body>.
     * Se colocado dentro de containers com 'transform' (como .main-content), 
     * o 'position: fixed' quebra e o modal aparece cortado ou some.
     */
    if (!empty($_SESSION['obrigar_troca_senha'])):
        // O prefixo garante que o caminho funcione em qualquer pasta (views ou raiz)
        $cpw_endpoint = $prefix . 'php/controllers/login_process.php';
        $logout_path = $prefix . 'php/controllers/logout.php';
        ?>
        <div class="cpw-overlay">
            <div class="cpw-card">
                <div class="cpw-title"><i class="fas fa-shield-alt"></i> Troca de Senha Obrigatória</div>
                <div class="cpw-subtitle">Para sua total segurança, é necessário alterar sua senha padrão no primeiro
                    acesso.</div>
                <form id="form-forced-cpw">
                    <input type="hidden" name="action" value="change_password">
                    <div class="cpw-field">
                        <label>Nova Senha</label>
                        <input type="password" name="nova_senha" class="cpw-input" required minlength="6"
                            placeholder="Mínimo 6 caracteres">
                    </div>
                    <div class="cpw-field">
                        <label>Confirmar Nova Senha</label>
                        <input type="password" name="confirmar_senha" class="cpw-input" required
                            placeholder="Repita a nova senha">
                    </div>
                    <button type="submit" class="cpw-btn"><i class="fas fa-key"></i> Atualizar Senha</button>
                    <div id="cpw-msg" style="margin-top: 20px; font-weight: 600;"></div>
                </form>
                <div class="cpw-logout">
                    <a href="<?= $logout_path ?>" class="cpw-logout-link">
                        <i class="fas fa-sign-out-alt"></i> Sair do Sistema
                    </a>
                </div>
            </div>
        </div>
        <script>
            document.getElementById('form-forced-cpw').addEventListener('submit', function (e) {
                e.preventDefault();
                const msg = document.getElementById('cpw-msg');
                const btn = this.querySelector('.cpw-btn');
                msg.style.color = 'var(--text-muted)';
                msg.innerText = 'Processando...';
                btn.disabled = true;

                fetch('<?= $cpw_endpoint ?>', {
                    method: 'POST',
                    body: new FormData(this)
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            msg.style.color = '#2e7d32';
                            msg.innerText = data.message + ' Recarregando...';
                            setTimeout(() => window.location.reload(), 1500);
                        } else {
                            msg.style.color = 'var(--primary-red)';
                            msg.innerText = data.error;
                            btn.disabled = false;
                        }
                    })
                    .catch(() => {
                        msg.style.color = 'var(--primary-red)';
                        msg.innerText = 'Erro na conexão com o servidor.';
                        btn.disabled = false;
                    });
            });
        </script>
    <?php endif; ?>

    <nav class="sidebar">
        <div class="div-img">
            <img src="<?= $prefix ?>assets/images/senailogo.png" id="senai-logo" alt="SENAI">
        </div>

        <div class="div-links">
             <?php
            $dashboard_pages = ['index.php', 'dashboard_vendas.php'];
            $is_dashboard_active = in_array($current_page, $dashboard_pages);

            $can_see_full_dashboard = isAdmin() || isGestor() || isCRI() || isSecretaria();
            if ($can_see_full_dashboard): 
                $dashboard_open_cookie = $_COOKIE['menu_open_dashboard'] ?? null;
                $is_dashboard_open = $dashboard_open_cookie === 'open' || ($dashboard_open_cookie === null && $is_dashboard_active);
            ?>
                <div class="menu-manutencao <?= $is_dashboard_open ? 'aberto' : '' ?>" data-menu-id="dashboard">
                    <a href="<?= $prefix ?>index.php"
                        class="links manutencao-btn <?= $is_dashboard_active ? 'ativo' : '' ?>">
                        <i class="bi bi-speedometer2" style="margin-right: 10px;"></i> Dashboard <i
                            class="bi bi-caret-down-fill seta"></i>
                    </a>
                    <div class="submenu <?= $is_dashboard_open ? 'aberto' : '' ?>">
                        <?php if (!isProfessor()): ?>
                            <a href="<?= $prefix ?>index.php"
                                class="links-sub <?= ($current_page == 'index.php' || $current_page == 'Projeto-horario') ? 'active-sub' : '' ?>">
                                <i class="bi bi-speedometer2" style="margin-right: 8px;"></i> Gestão
                            </a>
                        <?php endif; ?>
                        <?php if (!isSecretaria()): ?>
                            <a href="<?= $prefix ?>php/views/dashboard_vendas.php"
                                class="links-sub <?= $current_page == 'dashboard_vendas.php' ? 'active-sub' : '' ?>">
                                <i class="bi bi-bar-chart-line" style="margin-right: 8px;"></i> Vendas
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isCRI()): ?>
                <!-- CRI vê também Agenda Professores e Gerenciar Reservas como links diretos (facilitando o fluxo) -->
                <a href="<?= $prefix ?>php/views/agenda_professores.php"
                    class="links <?= $current_page == 'agenda_professores.php' ? 'ativo' : '' ?>">
                    <i class="bi bi-calendar-week" style="margin-right: 10px;"></i> Agenda Professores
                </a>
                <a href="<?= $prefix ?>php/views/gerenciar_reservas.php"
                    class="links <?= $current_page == 'gerenciar_reservas.php' ? 'ativo' : '' ?>">
                    <i class="bi bi-calendar2-heart" style="margin-right: 10px;"></i> Gerenciar Reservas
                </a>
            <?php endif; ?>

            <?php if (isAdmin() || isGestor() || isProfessor() || isSecretaria()): ?>
                <a href="<?= $prefix ?>php/views/turmas.php"
                    class="links <?= in_array($current_page, ['turmas.php', 'turmas_form.php']) ? 'ativo' : '' ?>">
                    <i class="bi bi-people-fill" style="margin-right: 10px;"></i> Turmas
                </a>
            <?php endif; ?>

            <?php if (isAdmin() || isGestor()): ?>

                <a href="<?= $prefix ?>php/views/professores.php"
                    class="links <?= $current_page == 'professores.php' ? 'ativo' : '' ?>">
                    <i class="bi bi-person-workspace" style="margin-right: 10px;"></i> Docentes
                </a>
                <a href="<?= $prefix ?>php/views/salas.php"
                    class="links <?= in_array($current_page, ['salas.php', 'salas_form.php']) ? 'ativo' : '' ?>">
                    <i class="bi bi-building" style="margin-right: 10px;"></i> Ambientes
                </a>
                <a href="<?= $prefix ?>php/views/cursos.php"
                    class="links <?= in_array($current_page, ['cursos.php', 'cursos_form.php']) ? 'ativo' : '' ?>">
                    <i class="bi bi-journal-bookmark-fill" style="margin-right: 10px;"></i> Cursos
                </a>
                <a href="<?= $prefix ?>php/views/feriados.php"
                    class="links <?= $current_page == 'feriados.php' ? 'ativo' : '' ?>">
                    <i class="bi bi-calendar-event" style="margin-right: 10px;"></i> Feriados
                </a>
                <a href="<?= $prefix ?>php/views/ferias.php"
                    class="links <?= $current_page == 'ferias.php' ? 'ativo' : '' ?>">
                    <i class="bi bi-sun" style="margin-right: 10px;"></i> Férias
                </a>
                <a href="<?= $prefix ?>php/views/preparacao.php"
                    class="links <?= in_array($current_page, ['preparacao.php', 'preparacao_form.php']) ? 'ativo' : '' ?>">
                    <i class="bi bi-briefcase-fill" style="margin-right: 10px;"></i> Preparação / Ausências
                </a>
            <?php endif; ?>

            <?php if (!isCRI()): ?>
                <?php if (isProfessor()): ?>
                    <a href="<?= $prefix ?>php/views/dashboard_vendas.php"
                        class="links <?= $current_page == 'dashboard_vendas.php' ? 'ativo' : '' ?>">
                        <i class="bi bi-bar-chart-line" style="margin-right: 10px;"></i> Dashboard Vendas
                    </a>
                <?php endif; ?>
                <?php if (isAdmin() || isGestor() || isProfessor()): ?>
                    <?php
                    $planejamento_pages = ['agenda_professores.php', 'agenda_salas.php', 'gerenciar_reservas.php'];
                    $is_planejamento_active = in_array($current_page, $planejamento_pages);
                    ?>
                    <?php
                    $planejamento_open_cookie = $_COOKIE['menu_open_planejamento'] ?? null;
                    $is_planejamento_open = $planejamento_open_cookie === 'open' || ($planejamento_open_cookie === null && $is_planejamento_active);
                    ?>
                    <div class="menu-manutencao <?= $is_planejamento_open ? 'aberto' : '' ?>" data-menu-id="planejamento">
                        <a href="<?= $prefix ?>php/views/agenda_professores.php"
                            class="links manutencao-btn <?= $is_planejamento_active ? 'ativo' : '' ?>">
                            <i class="bi bi-tools" style="margin-right: 10px;"></i> Planejamento <i
                                class="bi bi-caret-down-fill seta"></i>
                        </a>
                        <div class="submenu <?= $is_planejamento_open ? 'aberto' : '' ?>" id="submenu-manutencao">
                            <a href="<?= $prefix ?>php/views/agenda_professores.php"
                                class="links-sub <?= $current_page == 'agenda_professores.php' ? 'active-sub' : '' ?>">
                                <i class="bi bi-calendar-check" style="margin-right: 8px;"></i> Agenda Professores
                            </a>
                            <?php if (!isProfessor()): ?>
                                <a href="<?= $prefix ?>php/views/agenda_salas.php"
                                    class="links-sub <?= $current_page == 'agenda_salas.php' ? 'active-sub' : '' ?>">
                                    <i class="bi bi-building-check" style="margin-right: 8px;"></i> Agenda Salas
                                </a>
                                <a href="<?= $prefix ?>php/views/gerenciar_reservas.php"
                                    class="links-sub <?= $current_page == 'gerenciar_reservas.php' ? 'active-sub' : '' ?>">
                                    <i class="bi bi-calendar2-heart" style="margin-right: 8px;"></i> Gerenciar Reservas
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            <?php if (isAdmin() || isGestor()): ?>
                <a href="<?= $prefix ?>php/views/usuarios.php"
                    class="links <?= $current_page == 'usuarios.php' ? 'ativo' : '' ?>">
                    <i class="bi bi-shield-lock-fill" style="margin-right: 10px;"></i> Usuários
                </a>
                <?php
                $exportacao_pages = ['dados_exportacao.php', 'import_excel.php'];
                $is_exportacao_active = in_array($current_page, $exportacao_pages);
                ?>
                <?php
                $exportacao_open_cookie = $_COOKIE['menu_open_exportacao'] ?? null;
                $is_exportacao_open = $exportacao_open_cookie === 'open' || ($exportacao_open_cookie === null && $is_exportacao_active);
                ?>
                <div class="menu-manutencao <?= $is_exportacao_open ? 'aberto' : '' ?>" data-menu-id="exportacao">
                    <a href="<?= $prefix ?>php/views/dados_exportacao.php"
                        class="links manutencao-btn <?= $is_exportacao_active ? 'ativo' : '' ?>">
                        <i class="bi bi-cloud-download-fill" style="margin-right: 10px;"></i> Exportar/Importar <i
                            class="bi bi-caret-down-fill seta"></i>
                    </a>
                    <div class="submenu <?= $is_exportacao_open ? 'aberto' : '' ?>">
                        <a href="<?= $prefix ?>php/views/dados_exportacao.php"
                            class="links-sub <?= $current_page == 'dados_exportacao.php' ? 'active-sub' : '' ?>">
                            <i class="bi bi-cloud-download-fill" style="margin-right: 8px;"></i> Exportar
                        </a>
                        <a href="<?= $prefix ?>php/views/import_excel.php"
                            class="links-sub <?= $current_page == 'import_excel.php' ? 'active-sub' : '' ?>">
                            <i class="bi bi-file-earmark-spreadsheet" style="margin-right: 8px;"></i> Importar
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div class="div-configs">
            <button id="tema" onclick="changeTheme()">
                <?= $theme === 'escuro' ? '<i class="bi bi-moon-stars-fill"></i>' : '<i class="bi bi-brightness-high-fill"></i>' ?>
            </button>
            <a href="<?= $prefix ?>php/controllers/logout.php" class="sair" title="Sair do sistema">
                Sair <i class="bi bi-door-closed-fill"></i>
            </a>
        </div>
    </nav>
    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <div class="main-content">
        <header class="top-bar">
            <div class="top-bar-left">
                <button id="mobile-sidebar-toggle" class="sidebar-toggle-btn" title="Alternar Menu">
                    <div class="hamburger-menu">
                        <span class="line"></span>
                        <span class="line"></span>
                        <span class="line"></span>
                    </div>
                </button>
                <div class="avatar"><i class="fas fa-user-circle"></i></div>
                <div class="user-info-text">
                    <span class="user-greeting">Bem-vindo,</span>
                    <span class="user-name"><?= htmlspecialchars(getUserName()) ?></span>
                </div>
            </div>
            <div class="top-bar-right">
                <!-- Notification Bell -->
                <div class="notification-wrapper" style="position: relative; cursor: pointer;" id="notification-bell">
                    <div
                        style="background: var(--bg-color); border: 1px solid var(--border-color); width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: var(--text-color); position: relative; transition: all 0.2s;">
                        <i class="fas fa-bell"></i>
                        <span id="notification-count"
                            style="display: none; position: absolute; top: -5px; right: -5px; background: var(--primary-red); color: white; font-size: 0.65rem; padding: 2px 6px; border-radius: 50%; font-weight: 800; border: 2px solid var(--card-bg);">0</span>
                    </div>
                </div>
                <div class="status-box hide-mobile">
                    <span class="status-label">Status do Sistema</span>
                    <span class="status-online"><i class="fas fa-circle status-dot"></i> Online</span>
                </div>
                <div class="date-badge hide-mobile">
                    <i class="far fa-calendar-alt"></i> <?= date('d/m/Y') ?>
                </div>
            </div>
        </header>

        <!-- Notification Modal -->
        <div class="modal-overlay" id="notification-modal" style="display: none; z-index: 9999;">
            <div class="modal-content"
                style="max-width: 420px; position: absolute; top: 70px; right: 20px; margin: 0; padding: 0; box-shadow: 0 10px 30px rgba(0,0,0,0.15); border-radius: 12px; overflow: hidden; display: flex; flex-direction: column;">

                <div class="modal-header"
                    style="padding: 15px 20px; background: var(--bg-color); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                    <h4 style="margin: 0; font-size: 1.1rem; color: var(--text-color);"><i class="fas fa-bell"
                            style="color: var(--primary-red); margin-right: 8px;"></i> Notificações</h4>
                    <div style="display: flex; gap: 15px; align-items: center;">
                        <button onclick="NotifSystem.limparLidas()" title="Limpar Lidas"
                            style="background:none; border:none; color: var(--text-muted); cursor: pointer;"><i
                                class="fas fa-trash-alt"></i></button>
                        <button onclick="NotifSystem.marcarTodasLidas()" title="Marcar todas como Lidas"
                            style="background:none; border:none; color: var(--primary-red); cursor: pointer;"><i
                                class="fas fa-check-double"></i></button>
                        <button class="modal-close"
                            onclick="NotifSystem.toggle()"
                            style="background:none; border:none; color: var(--text-color); font-size: 1.2rem; cursor: pointer;"><i
                                class="fas fa-times"></i></button>
                    </div>
                </div>

                <div
                    style="background: rgba(0,0,0,0.02); padding: 12px 20px; border-bottom: 1px solid var(--border-color);">
                    <div style="display: flex; gap: 8px; margin-bottom: 12px;">
                        <button class="notif-tab active" data-status="todas" onclick="NotifSystem.setTab('todas')"
                            style="flex:1; padding: 6px; font-size: 0.8rem; border-radius: 20px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-muted); cursor: pointer; font-weight: 700;">Todas</button>
                        <button class="notif-tab" data-status="nao_lido" onclick="NotifSystem.setTab('nao_lido')"
                            style="flex:1; padding: 6px; font-size: 0.8rem; border-radius: 20px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-muted); cursor: pointer; font-weight: 700;">Não
                            Lido</button>
                        <button class="notif-tab" data-status="lido" onclick="NotifSystem.setTab('lido')"
                            style="flex:1; padding: 6px; font-size: 0.8rem; border-radius: 20px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-muted); cursor: pointer; font-weight: 700;">Lido</button>
                    </div>
                    <select id="notif-filter-tipo" onchange="NotifSystem.setFilter(this.value)"
                        style="width: 100%; padding: 8px 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-color); font-size: 0.85rem; outline: none;">
                        <option value="todos">Todos os Eventos</option>
                        <option value="reserva_realizada">Reserva de Horário</option>
                        <option value="registro_horario">Registro de Turma no Calendário</option>
                        <option value="registro_turma">Criação de Turma</option>
                        <option value="edicao_turma">Edição de Turma</option>
                        <option value="exclusao_turma">Exclusão de Turma</option>
                    </select>
                </div>

                <div id="notification-list" style="max-height: 380px; overflow-y: auto; padding: 0;">
                    <div style="text-align: center; padding: 30px; color: var(--text-muted); font-size: 0.9rem;">
                        Carregando...</div>
                </div>

            </div>
        </div>

        <script>
            const NotifSystem = {
                statusVal: 'todas',
                tipoVal: 'todos',
                urlBase: '<?= $prefix ?>php/controllers/notificacao_api.php',
                userRole: '<?= $_SESSION['user_role'] ?? 'guest' ?>',
                baseUrl: '<?= BASE_URL ?>',

                init: function () {
                    document.getElementById('notification-bell').addEventListener('click', (e) => {
                        e.stopPropagation();
                        this.toggle();
                    });

                    // Clique fora para fechar
                    window.addEventListener('click', (e) => {
                        const modal = document.getElementById('notification-modal');
                        const modalContent = modal.querySelector('.modal-content');
                        if (modal.style.display === 'flex' && !modalContent.contains(e.target)) {
                            this.toggle();
                        }
                    });
                    this.updateTabUI();
                    this.load();
                    setInterval(() => this.load(), 600000); // 10-minute auto refresh unread
                },

                toggle: function() {
                    const modal = document.getElementById('notification-modal');
                    modal.style.display = modal.style.display === 'none' ? 'flex' : 'none';
                    if (modal.style.display === 'flex') this.load();

                    // Create permission denied modal logic
                    if (!document.getElementById('permission-denied-modal')) {
                        const pm = document.createElement('div');
                        pm.id = 'permission-denied-modal';
                        pm.style.cssText = 'display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:100000; align-items:center; justify-content:center; backdrop-filter:blur(3px);';
                        pm.innerHTML = `
                            <div style="background:var(--card-bg); border-radius:12px; padding:25px; width:90%; max-width:400px; text-align:center; box-shadow:0 10px 30px rgba(0,0,0,0.2); animation: fadeInScale 0.2s ease-out;">
                                <i class="fas fa-exclamation-triangle" style="font-size:3rem; color:var(--primary-red); margin-bottom:15px;"></i>
                                <h3 style="color:var(--text-color); margin-bottom:10px;">Acesso Negado</h3>
                                <p style="color:var(--text-muted); font-size:0.95rem; margin-bottom:20px;">Você não possui permissão para acessar a página desta notificação.</p>
                                <button onclick="document.getElementById('permission-denied-modal').style.display='none'" class="btn btn-primary" style="padding:10px 25px; border-radius:8px;">Entendi</button>
                            </div>
                        `;
                        document.body.appendChild(pm);
                    }
                },

                setTab: function (status) {
                    this.statusVal = status;
                    this.updateTabUI();
                    this.load();
                },

                setFilter: function (tipo) {
                    this.tipoVal = tipo;
                    this.load();
                },

                updateTabUI: function () {
                    document.querySelectorAll('.notif-tab').forEach(btn => {
                        if (btn.dataset.status === this.statusVal) {
                            btn.style.background = 'var(--primary-red)';
                            btn.style.color = '#fff';
                            btn.style.borderColor = 'var(--primary-red)';
                        } else {
                            btn.style.background = 'var(--bg-color)';
                            btn.style.color = 'var(--text-color)';
                            btn.style.borderColor = 'var(--border-color)';
                        }
                    });
                },

                load: async function () {
                    try {
                        const response = await fetch(`${this.urlBase}?action=listar&status=${this.statusVal}&tipo=${this.tipoVal}`);
                        const data = await response.json();

                        const countEl = document.getElementById('notification-count');
                        if (data.unread > 0) {
                            countEl.textContent = data.unread > 99 ? '99+' : data.unread;
                            countEl.style.display = 'block';
                        } else {
                            countEl.style.display = 'none';
                        }

                        const list = document.getElementById('notification-list');
                        if (!data.notificacoes || data.notificacoes.length === 0) {
                            list.innerHTML = '<div style="text-align: center; padding: 40px 20px; color: var(--text-muted);"><i class="fas fa-bell-slash" style="font-size: 2rem; opacity: 0.3; margin-bottom: 10px; display: block;"></i>Nenhuma notificação encontrada.</div>';
                            return;
                        }

                        list.innerHTML = data.notificacoes.map(n => {
                            const date = new Date(n.created_at).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
                            const isUnread = n.lida == 0;
                            const bg = isUnread ? 'rgba(237, 28, 36, 0.04)' : 'transparent';
                            const dot = isUnread ? '<div style="width: 8px; height: 8px; background: var(--primary-red); border-radius: 50%; display: inline-block; margin-right: 6px; flex-shrink: 0; margin-top: 5px;"></div>' : '';

                            // Cursor fix
                            const linkAttr = n.link ? `onclick="NotifSystem.marcarLidaEIr(${n.id}, '${n.link}')" style="cursor: pointer;"` : 'style="cursor: default;"';

                            let icon = 'fa-info-circle';
                            let iconColor = 'var(--text-muted)';
                            if (n.tipo === 'reserva_realizada') { icon = 'fa-bookmark'; iconColor = '#ffb300'; }
                            if (n.tipo === 'registro_horario') { icon = 'fa-clock'; iconColor = '#2e7d32'; }
                            if (n.tipo === 'registro_turma') { icon = 'fa-users'; iconColor = '#1976d2'; }
                            if (n.tipo === 'exclusao_turma') { icon = 'fa-trash-alt'; iconColor = '#d32f2f'; }
                            if (n.tipo === 'edicao_turma') { icon = 'fa-edit'; iconColor = '#f57c00'; }

                            // Make sure pointer remains even if hover area changes slightly
                            return `
                            <div class="notif-item" style="padding: 15px 20px; border-bottom: 1px solid var(--border-color); background: ${bg}; transition: background 0.2s; ${n.link ? 'cursor: pointer;' : ''}" onmouseover="if(${n.link ? 'true' : 'false'}) this.style.background='rgba(0,0,0,0.03)'" onmouseout="this.style.background='${bg}'">
                                <div style="display: flex; gap: 12px; align-items: flex-start;" ${linkAttr}>
                                    <div style="font-size: 1.2rem; color: ${iconColor}; padding-top: 2px;"><i class="fas ${icon}"></i></div>
                                    <div style="flex: 1; overflow: hidden;">
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px;">
                                            <div style="display: flex; align-items: flex-start; max-width: 70%;">
                                                ${dot}
                                                <strong style="font-size: 0.9rem; color: var(--text-color); line-height: 1.2;">${n.titulo}</strong>
                                            </div>
                                            <span style="font-size: 0.7rem; color: var(--text-muted); white-space: nowrap; margin-left: 10px;">${date}</span>
                                        </div>
                                        <div style="font-size: 0.8rem; color: var(--text-muted); line-height: 1.4; word-wrap: break-word;">${n.mensagem}</div>
                                        ${isUnread ? `<div style="text-align: right; margin-top: 6px;"><button onclick="event.stopPropagation(); NotifSystem.marcarLida(${n.id});" style="background:none; border:none; color: var(--primary-red); font-size: 0.75rem; font-weight: 700; cursor: pointer;">Marcar como lida</button></div>` : ''}
                                    </div>
                                </div>
                            </div>
                            `;
                        }).join('');
                    } catch (e) {
                        document.getElementById('notification-list').innerHTML = '<div style="text-align: center; padding: 20px; color: var(--primary-red);">Erro de conexão.</div>';
                    }
                },

                postAction: async function (action, bodyData = {}) {
                    try {
                        const fd = new URLSearchParams();
                        fd.append('action', action);
                        for (const key in bodyData) fd.append(key, bodyData[key]);

                        await fetch(this.urlBase, {
                            method: 'POST',
                            body: fd,
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
                        });
                        this.load();
                    } catch (e) { console.error('Error posting ', action); }
                },

                marcarLida: function (id) { this.postAction('marcar_lida', { notificacao_id: id }); },
                marcarTodasLidas: function () { this.postAction('marcar_todas_lidas'); },
                limparLidas: function () { this.postAction('limpar_lidas'); },

                marcarLidaEIr: function (id, link) {
                    // Sanitiza links legados relativos (../views/...) convertendo para absoluto
                    if (link && !link.startsWith('/') && !link.startsWith('http')) {
                        // Remove o prefixo relativo (ex: "../views/" ou "./views/")
                        link = link.replace(/^\.\.\//, '').replace(/^\.\//, '');
                        link = this.baseUrl + '/php/' + link;
                    }

                    // Check permission FIRST
                    const r = this.userRole;
                    const l = link.toLowerCase();
                    let allowed = true;

                    // Typical restricted paths:
                    // /turmas, /professores, /cursos, /salas, /usuarios => Admin/Gestor only
                    const adminOnlyPaths = ['professores.php', 'cursos.php', 'salas.php', 'usuarios.php'];
                    const restrictedForProf = ['usuarios.php', 'professores.php'];
                    
                    if (r === 'cri') {
                        if (['turmas.php', ...adminOnlyPaths].some(p => l.includes(p))) {
                            allowed = false;
                        }
                    } else if (r === 'professor') {
                        if (restrictedForProf.some(p => l.includes(p))) {
                            allowed = false;
                        }
                    }

                    const fd = new URLSearchParams();
                    fd.append('action', 'marcar_lida');
                    fd.append('notificacao_id', id);

                    if (allowed) {
                        fetch(this.urlBase, {
                            method: 'POST',
                            body: fd,
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
                        }).finally(() => {
                            window.location.href = link;
                        });
                    } else {
                        // Just mark as read and show modal
                        fetch(this.urlBase, {
                            method: 'POST',
                            body: fd,
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
                        }).finally(() => {
                            this.load();
                            document.getElementById('permission-denied-modal').style.display = 'flex';
                            document.getElementById('notification-modal').style.display = 'none';
                        });
                    }
                }
            };

            document.addEventListener('DOMContentLoaded', () => NotifSystem.init());
        </script>
    <div id="command-palette-overlay" onclick="closeCommandPalette()"></div>
    <div id="command-palette">
        <div class="cp-input-wrapper">
            <i class="fas fa-search" style="color: #64748b;"></i>
            <input type="text" id="cp-input" class="cp-input" placeholder="O que você deseja fazer? (Ex: Turmas, Agenda...)" autocomplete="off">
            <span class="cp-shortcut">ESC</span>
        </div>
        <div class="cp-results" id="cp-results">
            <!-- Navegação -->
            <div class="cp-section-title">Navegação</div>
            <a href="<?= $prefix ?>index.php" class="cp-item">
                <i class="fas fa-chart-line"></i> Dashboard Principal <span class="cp-shortcut">D</span>
            </a>
            <?php if (!isCRI()): ?>
                <a href="<?= $prefix ?>php/views/turmas.php" class="cp-item">
                    <i class="fas fa-users"></i> Gestão de Turmas <span class="cp-shortcut">T</span>
                </a>
            <?php endif; ?>
            <a href="<?= $prefix ?>php/views/dashboard_vendas.php" class="cp-item">
                <i class="bi bi-bar-chart-line"></i> Dashboard Vendas <span class="cp-shortcut">V</span>
            </a>
            <a href="<?= $prefix ?>php/views/agenda_professores.php" class="cp-item">
                <i class="fas fa-calendar-alt"></i> Agenda de Professores <span class="cp-shortcut">A</span>
            </a>
            <?php if (can_edit()): ?>
                <a href="<?= $prefix ?>php/views/professores.php" class="cp-item">
                    <i class="fas fa-chalkboard-teacher"></i> Cadastro de Docentes <span class="cp-shortcut">P</span>
                </a>
            <?php endif; ?>
            <a href="<?= $prefix ?>php/views/gerenciar_reservas.php" class="cp-item">
                <i class="bi bi-calendar2-heart"></i> Gerenciar Reservas <span class="cp-shortcut">G</span>
            </a>
            
            <!-- Ações Rápidas -->
            <div class="cp-section-title">Ações Rápidas</div>
            <?php if (can_edit()): ?>
                <a href="<?= $prefix ?>php/views/turmas_form.php" class="cp-item">
                    <i class="fas fa-plus-circle"></i> Criar Nova Turma <span class="cp-shortcut">N</span>
                </a>
            <?php endif; ?>
            <a href="javascript:void(0)" onclick="openGlobalReserva()" class="cp-item">
                <i class="fas fa-bookmark"></i> Nova Reserva de Docente <span class="cp-shortcut">R</span>
            </a>
        </div>
    </div>

    <script>
        function openCommandPalette() {
            document.getElementById('command-palette-overlay').style.display = 'block';
            const cp = document.getElementById('command-palette');
            cp.style.display = 'block';
            const input = document.getElementById('cp-input');
            input.value = '';
            input.focus();
            filterCPResults('');
        }

        function closeCommandPalette() {
            document.getElementById('command-palette-overlay').style.display = 'none';
            document.getElementById('command-palette').style.display = 'none';
        }

        function filterCPResults(query) {
            const items = document.querySelectorAll('.cp-item');
            query = query.toLowerCase();
            items.forEach(item => {
                const text = item.innerText.toLowerCase();
                item.style.display = text.includes(query) ? 'flex' : 'none';
            });
        }

        document.getElementById('cp-input').addEventListener('input', (e) => filterCPResults(e.target.value));

        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                openCommandPalette();
            }
            if (e.key === 'Escape') closeCommandPalette();
        });

        // Clique fora para fechar (já tratado pelo overlay, mas por segurança)
        window.addEventListener('click', (e) => {
            if (e.target === document.getElementById('command-palette-overlay')) closeCommandPalette();
        });
    </script>

    <div class="content-wrapper">