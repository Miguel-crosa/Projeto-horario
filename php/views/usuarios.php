<?php
require_once __DIR__ . '/../configs/db.php';
require_once __DIR__ . '/../configs/auth.php';
requireAuth();

// Roles summary:
// admin: full access
// gestor: can create professor only, cannot edit/delete
// CRI: no access to this page (only admin/gestor can manage users)
if (isCRI()) {
    header('Location: ../../index.php');
    exit;
}

$can_edit = isAdmin() || isGestor();
$can_delete = isAdmin();
$can_reset = isAdmin() || isGestor();
$can_create = isAdmin() || isGestor();
$can_toggle_status = isAdmin() || isGestor();

$show_inactive = isset($_GET['show_inactive']) && $_GET['show_inactive'] == '1';
$where_clause = $show_inactive ? "" : "WHERE u.ativo = 1";

$usuarios = mysqli_fetch_all(mysqli_query($conn, "
    SELECT u.id, u.nome, u.email, u.role, u.obrigar_troca_senha, u.ativo, u.created_at, u.docente_id, 
           d.nome as docente_nome, d.area_conhecimento as docente_area
    FROM usuario u 
    LEFT JOIN docente d ON u.docente_id = d.id 
    $where_clause
    ORDER BY u.created_at DESC
"), MYSQLI_ASSOC);

// Fetch docentes with their areas and check if they are already linked to a user
$docentes = mysqli_fetch_all(mysqli_query($conn, "
    SELECT d.id, d.nome, d.area_conhecimento, u.nome as vinculado_a, u.id as vinculado_id
    FROM docente d 
    LEFT JOIN usuario u ON d.id = u.docente_id 
    ORDER BY d.nome ASC
"), MYSQLI_ASSOC);

$error = $_SESSION['usuarios_error'] ?? '';
$success = $_SESSION['usuarios_success'] ?? '';
unset($_SESSION['usuarios_error'], $_SESSION['usuarios_success']);

include __DIR__ . '/../components/header.php';
?>

<div class="page-header">
    <h2><i class="fas fa-users-cog" style="color: var(--primary-red);"></i> Gerenciamento de Usuários</h2>
    <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
        <div class="search-container" style="position: relative; min-width: 250px;">
            <i class="fas fa-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.85rem;"></i>
            <input type="text" id="user-search-table" placeholder="Buscar por nome ou e-mail..." 
                   style="width: 100%; padding: 8px 12px 8px 35px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--card-bg); color: var(--text-color); outline: none; font-size: 0.9rem; transition: all 0.2s;">
        </div>
        <a href="?show_inactive=<?= $show_inactive ? '0' : '1' ?>" class="btn <?= $show_inactive ? 'btn-edit' : 'btn-outline' ?>" style="text-decoration: none; display: flex; align-items: center; gap: 8px; height: 38px;">
            <i class="fas <?= $show_inactive ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
            <?= $show_inactive ? 'Ocultar Inativos' : 'Ver Inativos' ?>
        </a>
        <?php if ($can_create): ?>
            <button class="btn btn-primary" style="height: 38px;" onclick="document.getElementById('modal-user-create').style.display='flex'">
                <i class="fas fa-plus"></i> Novo Usuário
            </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i>
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i>
        <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>E-mail</th>
                <th>Papel</th>
                <th>Vínculo Docente</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($usuarios)): ?>
                <tr>
                    <td colspan="7" class="text-center">Nenhum usuário cadastrado.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td><strong><?= htmlspecialchars($u['nome']) ?></strong></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <span class="role-badge role-<?= $u['role'] ?>">
                                <i
                                    class="fas <?= $u['role'] === 'admin' ? 'fa-shield-alt' : ($u['role'] === 'gestor' ? 'fa-user-tie' : ($u['role'] === 'professor' ? 'fa-chalkboard-teacher' : ($u['role'] === 'secretaria' ? 'fa-user-tag' : 'fa-user'))) ?>"></i>
                                <?= ucfirst($u['role']) ?>
                            </span>
                        </td>
                        <td><?= $u['docente_nome'] ? htmlspecialchars($u['docente_nome']) : '<span style="color:var(--text-muted); font-size:0.8rem;">Nenhum</span>' ?>
                        </td>
                        <td style="text-align:center;">
                            <?php if ($u['ativo']): ?>
                                <span class="badge badge-success" style="background: #2e7d32; color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 700;">
                                    <i class="fas fa-check-circle"></i> Ativo
                                </span>
                            <?php else: ?>
                                <span class="badge badge-danger" style="background: #c62828; color: #fff; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 700;">
                                    <i class="fas fa-times-circle"></i> Inativo
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($can_edit && (isAdmin() || (isGestor() && $u['role'] !== 'admin'))): ?>
                                <button class="btn btn-edit" onclick="openEditModal(<?= htmlspecialchars(json_encode($u)) ?>)" title="Editar dados">
                                     <i class="fas fa-edit"></i>
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($can_toggle_status && $u['id'] != $_SESSION['user_id'] && (isAdmin() || (isGestor() && $u['role'] !== 'admin'))): ?>
                                <a href="../controllers/usuarios_process.php?action=toggle_status&id=<?= $u['id'] ?>&status=<?= $u['ativo'] ? '0' : '1' ?>" 
                                    class="btn <?= $u['ativo'] ? 'btn-delete' : 'btn-primary' ?>" 
                                    style="background: <?= $u['ativo'] ? '#546e7a' : '#2e7d32' ?>;"
                                    title="<?= $u['ativo'] ? 'Desativar usuário' : 'Ativar usuário' ?>">
                                     <i class="fas <?= $u['ativo'] ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                                </a>
                            <?php endif; ?>

                            <?php if ($can_reset && $u['id'] != $_SESSION['user_id'] && (isAdmin() || (isGestor() && $u['role'] !== 'admin'))): ?>
                                <a href="../controllers/usuarios_process.php?action=reset_password&id=<?= $u['id'] ?>"
                                    class="btn btn-edit" title="Redefinir senha para padrão (senaisp)"
                                    onclick="return confirm('Redefinir a senha deste usuário para senaisp?')">
                                    <i class="fas fa-key"></i>
                                </a>
                            <?php endif; ?>

                            <?php if ($can_delete && $u['id'] != $_SESSION['user_id']): ?>
                                <a href="../controllers/usuarios_process.php?action=delete&id=<?= $u['id'] ?>"
                                    class="btn btn-delete" title="Excluir permanentemente"
                                    onclick="return confirm('Tem certeza que deseja remover este usuário permanentemente? Esta ação não pode ser desfeita.')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal Criar Usuário -->
<?php if ($can_create): ?>
    <div id="modal-user-create" class="cpw-overlay" style="display: none;">
        <div class="cpw-card" style="max-width: 500px;">
            <h3><i class="fas fa-user-plus"></i> Novo Usuário</h3>
            <p>A senha padrão será <strong>senaisp</strong>.</p>
            <form action="../controllers/usuarios_process.php" method="POST">
                <input type="hidden" name="action" value="create">
                <div class="login-field">
                    <label>Nome Completo</label>
                    <input type="text" name="nome" class="login-input" required>
                </div>
                <div class="login-field">
                    <label>E-mail</label>
                    <input type="email" name="email" class="login-input" required>
                </div>
                <div class="login-field">
                    <label>Papel</label>
                    <select name="role" class="login-input" id="create-role-select" onchange="toggleDocenteField('create')">
                        <option value="professor">Professor</option>
                        <option value="cri">CRI</option>
                        <option value="secretaria">Secretaria</option>
                        <option value="gestor">Gestor</option>
                        <?php if (isAdmin()): ?>
                            <option value="admin">Administrador</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="login-field" id="create-docente-field">
                    <label>Vínculo Docente</label>
                    <div id="create-docente-display" class="selection-display">
                        <span class="selection-text">Nenhum docente selecionado</span>
                        <input type="hidden" name="docente_id" id="create-user-docente">
                        <button type="button" class="btn-selection" onclick="openDocenteSelector('create')">
                            <i class="fas fa-search"></i> Selecionar
                        </button>
                    </div>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <button type="submit" class="login-btn" style="flex: 1;"><i class="fas fa-save"></i> Criar</button>
                    <button type="button" class="login-btn"
                        style="flex: 1; background: var(--bg-color, #334155); color: var(--text-color);"
                        onclick="document.getElementById('modal-user-create').style.display='none'">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Modal Editar Usuário (Admin only) -->
<?php if ($can_edit): ?>
    <div id="modal-user-edit" class="cpw-overlay" style="display: none;">
        <div class="cpw-card" style="max-width: 500px;">
            <h3><i class="fas fa-user-edit"></i> Editar Usuário</h3>
            <form action="../controllers/usuarios_process.php" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit-user-id">
                <div class="login-field">
                    <label>Nome Completo</label>
                    <input type="text" name="nome" id="edit-user-nome" class="login-input" required>
                </div>
                <div class="login-field">
                    <label>E-mail</label>
                    <input type="email" name="email" id="edit-user-email" class="login-input" required>
                </div>
                <div class="login-field">
                    <label>Papel</label>
                    <select name="role" id="edit-user-role" class="login-input" onchange="toggleDocenteField('edit')">
                        <option value="professor">Professor</option>
                        <option value="cri">CRI</option>
                        <option value="secretaria">Secretaria</option>
                        <option value="gestor">Gestor</option>
                        <?php if (isAdmin()): ?>
                            <option value="admin">Administrador</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="login-field" id="edit-docente-field">
                    <label>Vínculo Docente</label>
                    <div id="edit-docente-display" class="selection-display">
                        <span class="selection-text">Nenhum docente selecionado</span>
                        <input type="hidden" name="docente_id" id="edit-user-docente">
                        <button type="button" class="btn-selection" onclick="openDocenteSelector('edit')">
                            <i class="fas fa-sync"></i> Trocar
                        </button>
                    </div>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <button type="submit" class="login-btn" style="flex: 1;"><i class="fas fa-save"></i> Salvar</button>
                    <button type="button" class="login-btn"
                        style="flex: 1; background: var(--bg-color, #334155); color: var(--text-color);"
                        onclick="document.getElementById('modal-user-edit').style.display='none'">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
    <!-- Modal Seletor de Docente -->
    <div id="modal-docente-selector" class="cpw-overlay" style="display: none; z-index: 2000;">
        <div class="cpw-card" style="max-width: 600px; max-height: 80vh; display: flex; flex-direction: column;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3><i class="fas fa-chalkboard-teacher"></i> Selecionar Docente</h3>
                <button type="button" class="btn-close-minimal" onclick="closeDocenteSelector()">&times;</button>
            </div>
            
            <div class="login-field" style="margin-bottom: 10px;">
                <input type="text" id="docente-search" class="login-input" placeholder="🔍 Buscar por nome ou área..." onkeyup="filterDocenteCards()">
            </div>

            <div id="docente-cards-list" class="docente-cards-container">
                <div class="docente-card-item" onclick="confirmDocenteSelection('', 'Nenhum', '')" style="border-left: 4px solid #94a3b8;">
                    <div class="docente-card-info">
                        <strong>Nenhum</strong>
                        <small>Remover vínculo atual</small>
                    </div>
                    <i class="fas fa-times-circle" style="color: #94a3b8;"></i>
                </div>
                <?php foreach ($docentes as $d): ?>
                    <?php 
                        $js_nome = str_replace("'", "\\'", $d['nome']);
                        $js_area = str_replace("'", "\\'", $d['area_conhecimento'] ?: 'Geral');
                        $is_linked = !empty($d['vinculado_a']);
                    ?>
                    <div class="docente-card-item <?= $is_linked ? 'is-linked-card' : '' ?>" 
                         data-linked-id="<?= $d['vinculado_id'] ?: '' ?>"
                         onclick="confirmDocenteSelection('<?= $d['id'] ?>', '<?= $js_nome ?>', '<?= $js_area ?>', this)"
                         data-search="<?= mb_strtolower($d['nome'] . ' ' . $d['area_conhecimento']) ?>">
                        <div class="docente-card-info">
                            <strong><?= htmlspecialchars($d['nome']) ?></strong>
                            <small><?= htmlspecialchars($d['area_conhecimento'] ?: 'Geral') ?></small>
                            <?php if ($d['vinculado_a']): ?>
                                <span class="already-linked"><i class="fas fa-link"></i> Vinculado a <?= htmlspecialchars($d['vinculado_a']) ?></span>
                            <?php endif; ?>
                        </div>
                        <i class="fas <?= $is_linked ? 'fa-lock' : 'fa-chevron-right' ?>"></i>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        let docenteSelectionTarget = 'create'; // 'create' or 'edit'
        let currentUserIdBeingEdited = null;

        function toggleDocenteField(mode) {
            // Removida restrição por cargo: qualquer cargo pode ter vínculo docente
            const docenteField = document.getElementById(mode + '-docente-field');
            if (docenteField) {
                docenteField.style.display = 'block';
            }
        }

        function openDocenteSelector(mode) {
            docenteSelectionTarget = mode;
            document.getElementById('docente-search').value = '';
            filterDocenteCards();
            document.getElementById('modal-docente-selector').style.display = 'flex';
        }

        function closeDocenteSelector() {
            document.getElementById('modal-docente-selector').style.display = 'none';
        }

        function filterDocenteCards() {
            const query = document.getElementById('docente-search').value.toLowerCase();
            const cards = document.querySelectorAll('.docente-card-item');
            cards.forEach(card => {
                const searchData = card.getAttribute('data-search');
                if (!searchData || searchData.includes(query)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Esta função atualiza apenas a UI e os campos ocultos
        function updateDocenteUI(mode, id, nome, area) {
            const input = document.getElementById(mode + '-user-docente');
            const display = document.querySelector('#' + mode + '-docente-display .selection-text');
            
            if (input) input.value = id;
            if (display) {
                if (id === '' || id === null) {
                    display.innerHTML = 'Nenhum docente selecionado';
                    display.style.color = 'var(--text-muted)';
                } else {
                    display.innerHTML = `<strong>${nome}</strong> <br><small>${area}</small>`;
                    display.style.color = 'var(--text-color)';
                }
            }
        }

        // Esta função é chamada pelo clique no card do modal
        function confirmDocenteSelection(id, nome, area, cardElement) {
            // Se o card estiver vinculado a OUTRO usuário, bloqueia
            if (cardElement && cardElement.classList.contains('is-linked-card')) {
                const linkedToId = cardElement.getAttribute('data-linked-id');
                if (linkedToId != currentUserIdBeingEdited) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Docente já vinculado',
                        text: 'Este docente já está associado a outra conta de usuário.',
                        confirmButtonColor: '#ed1c24'
                    });
                    return;
                }
            }

            updateDocenteUI(docenteSelectionTarget, id, nome, area);
            closeDocenteSelector();
        }

        function openEditModal(user) {
            currentUserIdBeingEdited = user.id;
            document.getElementById('edit-user-id').value = user.id;
            document.getElementById('edit-user-nome').value = user.nome;
            document.getElementById('edit-user-email').value = user.email;
            document.getElementById('edit-user-role').value = user.role;
            
            // Forçamos o modo 'edit' antes de atualizar a UI
            updateDocenteUI('edit', user.docente_id || '', user.docente_nome || 'Nenhum', user.docente_area || '');

            toggleDocenteField('edit');
            document.getElementById('modal-user-edit').style.display = 'flex';
        }

        window.addEventListener('load', () => {
            toggleDocenteField('create');
            currentUserIdBeingEdited = null;

            // Lógica de busca na tabela
            const searchInput = document.getElementById('user-search-table');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const query = this.value.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
                    const rows = document.querySelectorAll('table tbody tr');
                    
                    rows.forEach(row => {
                        if (row.cells.length < 2) return; // Pula linha de "nenhum usuário"
                        
                        const nome = row.cells[1].textContent.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
                        const email = row.cells[2].textContent.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
                        
                        if (nome.includes(query) || email.includes(query)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            }
        });
    </script>

    <style>
        /* Garantir empilhamento correto de modais */
        #modal-user-create, #modal-user-edit { z-index: 1100; }
        #modal-docente-selector { z-index: 1200; background: rgba(0,0,0,0.85); }
        
        #user-search-table:focus {
            border-color: var(--primary-red) !important;
            box-shadow: 0 0 0 3px rgba(237, 28, 36, 0.1);
        }

        .is-linked-card {
            opacity: 0.6;
            background: rgba(255,255,255,0.02) !important;
            cursor: not-allowed !important;
        }
        .is-linked-card:hover {
            transform: none !important;
            border-color: rgba(255,255,255,0.1) !important;
        }

        .selection-display {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 15px;
            background: rgba(255,255,255,0.05);
            border: 2px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            margin-top: 8px;
            transition: border-color 0.2s;
        }
        .selection-display:hover {
            border-color: rgba(255,255,255,0.2);
        }
        .selection-text {
            font-size: 0.9rem;
            color: var(--text-color);
            line-height: 1.2;
        }
        .btn-selection {
            background: var(--primary-red);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.2s;
        }
        .btn-selection:hover { opacity: 0.9; transform: translateY(-1px); }

        .docente-cards-container {
            overflow-y: auto;
            flex: 1;
            padding-right: 5px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .docente-card-item {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 8px;
            padding: 12px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .docente-card-item:hover {
            background: rgba(255,255,255,0.08);
            border-color: var(--primary-red);
            transform: translateX(5px);
        }
        .docente-card-info { display: flex; flex-direction: column; gap: 2px; }
        .docente-card-info strong { font-size: 1rem; color: var(--text-color); }
        .docente-card-info small { font-size: 0.8rem; color: var(--text-muted); }
        .already-linked {
            font-size: 0.75rem;
            color: #fbbf24;
            display: flex;
            align-items: center;
            gap: 4px;
            margin-top: 4px;
        }
        .btn-close-minimal {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 1.5rem;
            cursor: pointer;
        }
    </style>
<?php endif; ?>

<?php include __DIR__ . '/../components/footer.php'; ?>