<?php
require_once __DIR__ . '/../configs/db.php';
include __DIR__ . '/../components/header.php';

if (!isAdmin() && !isGestor()) {
    header("Location: dashboard_vendas.php");
    exit;
}

// Fetch vacations for the list
$ferias = mysqli_fetch_all(mysqli_query($conn, "SELECT v.*, d.nome AS professor_nome, d.tipo_contrato FROM vacations v LEFT JOIN docente d ON v.teacher_id = d.id ORDER BY v.start_date ASC"), MYSQLI_ASSOC);

// Fetch all teachers for the status helper table
$current_year = date('Y');
$prof_status_query = "
    SELECT d.id, d.nome, d.tipo_contrato,
           (SELECT COUNT(*) FROM vacations v 
            WHERE (v.teacher_id = d.id OR (v.type = 'collective' AND v.teacher_id IS NULL))
            AND (YEAR(v.start_date) = $current_year OR YEAR(v.end_date) = $current_year)
           ) as has_vacation
FROM docente d 
    ORDER BY d.nome ASC";
$professores_status = mysqli_fetch_all(mysqli_query($conn, $prof_status_query), MYSQLI_ASSOC);
?>

<style>
    .vacation-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.85);
        display: none;
        z-index: 9999;
        backdrop-filter: blur(12px);
        justify-content: center;
        align-items: flex-start;
        padding: 5vh 20px;
        overflow-y: auto;
        animation: fadeIn 0.3s ease-out;
    }

    .vacation-modal-content {
        background: #1e293b;
        width: 95%;
        max-width: 1300px;
        border-radius: 20px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        max-height: 90vh;
        border: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: 0 25px 60px rgba(0, 0, 0, 0.8);
        animation: slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    @keyframes slideUp {
        from {
            transform: translateY(30px);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .v-modal-header {
        padding: 25px 30px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: rgba(255, 255, 255, 0.02);
    }

    .v-modal-body {
        padding: 0;
        display: flex;
        overflow: hidden;
        min-height: 550px;
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .v-modal-detail-side {
        width: 0;
        opacity: 0;
        padding: 0;
        overflow: hidden;
        border-right: 1px solid rgba(255, 255, 255, 0.05);
        background: rgba(0, 0, 0, 0.2);
        transition: all 0.4s ease;
    }

    .v-modal-body.show-details .v-modal-detail-side {
        width: 320px;
        opacity: 1;
        padding: 30px;
    }

    .detail-v-card {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.05);
        border-radius: 12px;
        padding: 12px 15px;
        margin-bottom: 10px;
        display: flex;
        flex-direction: column;
        gap: 4px;
        transition: transform 0.2s;
    }

    .detail-v-card:hover {
        transform: translateX(5px);
        background: rgba(255, 255, 255, 0.05);
    }

    .detail-v-card.collective {
        border-left: 3px solid #0288d1;
    }

    .detail-v-card.individual {
        border-left: 3px solid #00796b;
    }

    .v-modal-form-side {
        width: 400px;
        flex-shrink: 0;
        padding: 30px;
        border-right: 1px solid rgba(255, 255, 255, 0.05);
        overflow-y: auto;
        background: rgba(0, 0, 0, 0.1);
    }

    .v-modal-helper-side {
        flex: 1;
        padding: 30px;
        background: transparent;
        overflow-y: auto;
        position: relative;
    }

    .status-tab-btn {
        padding: 10px 20px;
        border-radius: 30px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        background: rgba(255, 255, 255, 0.05);
        color: #94a3b8;
        cursor: pointer;
        font-size: 0.85rem;
        font-weight: 600;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .status-tab-btn:hover {
        background: rgba(255, 255, 255, 0.1);
        color: #fff;
        transform: translateY(-2px);
    }

    .status-tab-btn.active {
        background: var(--primary-red);
        color: white;
        border-color: var(--primary-red);
        box-shadow: 0 4px 15px rgba(237, 28, 36, 0.4);
    }

    .v-modal-form-side .form-label {
        color: #f1f5f9;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
        opacity: 0.9;
    }

    .v-modal-form-side .form-input {
        background: rgba(15, 23, 42, 0.6);
        border: 1px solid rgba(255, 255, 255, 0.08);
        color: #fff;
        padding: 14px;
        border-radius: 12px;
        font-weight: 500;
    }

    .v-modal-form-side .form-input:focus {
        border-color: var(--primary-red);
        background: rgba(15, 23, 42, 0.85);
        box-shadow: 0 0 0 4px rgba(237, 28, 36, 0.15);
    }

    .status-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.9rem;
    }

    .status-table th {
        text-align: left;
        padding: 12px 15px;
        border-bottom: 2px solid rgba(255, 255, 255, 0.05);
        color: #64748b;
        font-weight: 800;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 1px;
    }

    .status-table td {
        padding: 15px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.03);
        vertical-align: middle;
    }

    .status-table tr:hover td {
        background: rgba(255, 255, 255, 0.02);
    }

    .status-table tr {
        cursor: pointer;
        transition: all 0.2s;
    }

    .status-table tr:hover td {
        background: rgba(255, 255, 255, 0.03);
    }

    .status-table tr.selected-row td {
        background: rgba(237, 28, 36, 0.08) !important;
        color: #fff;
        border-left: 2px solid var(--primary-red);
    }

    .status-badge {
        padding: 5px 12px;
        border-radius: 30px;
        font-size: 0.75rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .status-yes {
        background: rgba(34, 197, 94, 0.15);
        color: #4ade80;
        border: 1px solid rgba(34, 197, 94, 0.2);
    }

    .status-no {
        background: rgba(239, 68, 68, 0.1);
        color: #f87171;
        border: 1px solid rgba(239, 68, 68, 0.2);
    }

    /* Fix background table badges */
    .badge {
        padding: 6px 14px;
        border-radius: 10px;
        font-size: 0.78rem;
        font-weight: 700;
        color: #fff !important;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
        border: none !important;
    }

    .badge-collective {
        background: linear-gradient(135deg, #0288d1, #01579b) !important;
        box-shadow: 0 4px 10px rgba(2, 136, 209, 0.3);
    }

    .badge-individual {
        background: linear-gradient(135deg, #00796b, #004d40) !important;
        box-shadow: 0 4px 10px rgba(0, 121, 107, 0.3);
    }

    /* Estilo para múltiplas seleções em férias coletivas */
    .status-table tr.multi-selected td {
        background: rgba(34, 197, 94, 0.08) !important;
        border-left: 2px solid #22c55e !important;
    }

    .status-table tr.multi-selected .status-badge {
        background: rgba(34, 197, 94, 0.2);
        color: #fff;
    }
</style>

<div class="page-header">
    <h2>Gestão de Férias e Fechamentos</h2>
    <div class="header-actions" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <div class="search-box">
            <input type="text" id="tableSearch" placeholder="Buscar professor..." class="form-input"
                style="width: 300px;" onkeyup="currentPage=1; updatePagination()">
        </div>
        <button onclick="openVacationModal()" class="btn btn-primary"><i class="fas fa-plus"></i> Registrar
            Férias</button>
        <button type="button" id="btn-delete-bulk" class="btn" style="background: #fb8c00; color: white; display: none;"
            onclick="deleteSelectedVacations()">
            <i class="fas fa-trash-alt"></i> Excluir Selecionados
        </button>
        <?php if (isAdmin()): ?>
            <button type="button" class="btn" style="background: #e53935; color: white;" onclick="deleteAllVacations()">
                <i class="fas fa-dumpster-fire"></i> Excluir Tudo
            </button>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div style="padding: 15px; margin-bottom: 20px; border-radius: 8px;">
        <?php if ($_GET['msg'] === 'deleted_all'): ?>
            <div class="alert alert-danger"
                style="background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px;">Todos os registros de
                férias foram removidos.</div>
        <?php elseif ($_GET['msg'] === 'deleted_bulk'): ?>
            <div class="alert alert-warning"
                style="background: #fff3cd; color: #856404; border: 1px solid #ffeeba; padding: 10px;">
                <?= (int) $_GET['count'] ?> registros removidos com sucesso.</div>
        <?php elseif ($_GET['msg'] === 'deleted'): ?>
            <div class="alert alert-warning"
                style="background: #fff3cd; color: #856404; border: 1px solid #ffeeba; padding: 10px;">Registro removido com
                sucesso.</div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th style="width: 40px; text-align: center;">
                    <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)">
                </th>
                <th style="width: 50px;">#</th>
                <th onclick="sortTable(2)" style="cursor:pointer;">Tipo <span class="sort-icon"><i class="fas fa-sort"
                            style="opacity: 0.3;"></i></span></th>
                <th onclick="sortTable(3)" style="cursor:pointer;">Professor <span class="sort-icon"><i
                            class="fas fa-sort" style="opacity: 0.3;"></i></span></th>
                <th>Tipo Contrato</th>
                <th onclick="sortTable(5)" style="cursor:pointer;">Início <span class="sort-icon"><i class="fas fa-sort"
                            style="opacity: 0.3;"></i></span></th>
                <th onclick="sortTable(6)" style="cursor:pointer;">Fim <span class="sort-icon"><i class="fas fa-sort"
                            style="opacity: 0.3;"></i></span></th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($ferias)): ?>
                <tr>
                    <td colspan="5" class="text-center">Nenhum registro de férias cadastrado.</td>
                </tr>
            <?php else: ?>
                <?php $idx = 1;
                foreach ($ferias as $f): ?>
                    <tr class="table-row">
                        <td style="text-align: center;">
                            <input type="checkbox" class="row-checkbox" value="<?= $f['id'] ?>" onclick="updateBulkButton()">
                        </td>
                        <td style="color: var(--text-muted); font-size: 0.8rem;"><?= $idx++ ?></td>
                        <td>
                            <?php if ($f['type'] === 'collective'): ?>
                                <span class="badge badge-collective">Coletivas</span>
                            <?php else: ?>
                                <span class="badge badge-individual">Individuais</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= ($f['professor_nome']) ? htmlspecialchars($f['professor_nome']) : 'Todos os Professores' ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($f['tipo_contrato'] ?? 'N/A') ?>
                        </td>
                        <td>
                            <?= date('d/m/Y', strtotime($f['start_date'])) ?>
                        </td>
                        <td>
                            <?= date('d/m/Y', strtotime($f['end_date'])) ?>
                        </td>
                        <td>
                            <a href="javascript:void(0)" onclick="editVacation(<?= $f['id'] ?>)" class="btn btn-edit"><i
                                    class="fas fa-edit"></i></a>
                            <a href="../controllers/ferias_process.php?action=delete&id=<?= $f['id'] ?>" class="btn btn-delete"
                                onclick="return confirm('Tem certeza que deseja remover?')"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="pagination-controls"
    style="display: flex; justify-content: center; align-items: center; gap: 20px; margin-top: 20px;">
    <button id="prev-page" class="btn" onclick="changePage(-1)"><i class="fas fa-chevron-left"></i> Anterior</button>
    <span id="page-info">Página 1 de 1</span>
    <button id="next-page" class="btn" onclick="changePage(1)">Próxima <i class="fas fa-chevron-right"></i></button>
</div>

<!-- Modal de Registro de Férias -->
<div class="vacation-modal-overlay" id="vacation-modal">
    <div class="vacation-modal-content">
        <div class="v-modal-header" style="background: rgba(255,255,255,0.03); border-bottom: 1px solid rgba(255,255,255,0.1); padding: 20px 30px;">
            <div style="display: flex; align-items: center; gap: 20px;">
                <h3 style="margin: 0; font-size: 1.1rem; letter-spacing: 0.5px;" id="modal-title">
                    <i class="fas fa-calendar-plus" style="color: var(--primary-red); margin-right: 8px;"></i> Novo Registro de Férias
                </h3>
                <button type="button" id="btn-toggle-history" class="btn" 
                        style="padding: 6px 14px; font-size: 0.78rem; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.15); color: #94a3b8; border-radius: 30px; display: flex; align-items: center; gap: 8px;"
                        onclick="toggleHistory()">
                    <i class="fas fa-history"></i> VER HISTÓRICO
                </button>
            </div>
            <button class="btn c" onclick="closeVacationModal()" style="padding: 5px 10px; background: rgba(255,255,255,0.05); color: #94a3b8;"><i class="fas fa-times"></i></button>
        </div>
        <div class="v-modal-body" id="modal-body-container">
            <!-- LADO EXTREMO ESQUERDO: Detalhes do Professor -->
            <div class="v-modal-detail-side" id="v-modal-detail-side">
                <h4 style="margin: 0 0 20px 0; font-size: 0.95rem; color: #fff;"><i class="fas fa-history"
                        style="color: var(--primary-red);"></i> Férias Marcadas (<?= $current_year ?>)</h4>
                <div id="teacher-vacations-list">
                    <div
                        style="color: #64748b; font-size: 0.85rem; padding: 20px; text-align: center; border: 1px dashed rgba(255,255,255,0.1); border-radius: 12px;">
                        Selecione um docente para ver seu histórico de férias.</div>
                </div>
            </div>

            <!-- LADO ESQUERDO: Formulário -->
            <div class="v-modal-form-side">
                <form id="form-ferias" action="../controllers/ferias_process.php" method="POST">
                    <input type="hidden" name="id" value="">
                    <input type="hidden" name="ajax" value="1">

                    <div class="form-group">
                        <label class="form-label">Tipo de Férias</label>
                        <select name="type" id="type_select_modal" class="form-input" required
                            onchange="toggleModalFields()">
                            <option value="individual">Individuais (1 Professor)</option>
                            <option value="collective">Coletivas / Fechamento (Todos)</option>
                        </select>
                    </div>

                    <div class="form-group" id="modal_teacher_group">
                        <label class="form-label">Professor</label>
                        <select name="teacher_id" id="modal_teacher_id" class="form-input" required>
                            <option value="">Selecione o professor...</option>
                            <?php foreach ($professores_status as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Container para IDs selecionados em modo Coletivo -->
                    <div id="collective_ids_container"></div>

                    <div class="form-group" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <label class="form-label">Data de Início</label>
                            <input type="date" name="start_date" class="form-input" value="<?= date('Y-m-d') ?>"
                                required>
                        </div>
                        <div>
                            <label class="form-label">Data de Fim</label>
                            <input type="date" name="end_date" class="form-input"
                                value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                        </div>
                    </div>

                    <div class="form-actions" style="margin-top: 30px;">
                        <button type="submit" id="btn-save-modal" class="btn btn-primary"
                            style="width: 100%; padding: 15px; font-weight: 800;">
                            <i class="fas fa-check-circle"></i> SALVAR FÉRIAS
                        </button>
                    </div>
                </form>
            </div>

            <!-- LADO DIREITO: Tabela de Apoio -->
            <div class="v-modal-helper-side">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h4 style="margin: 0; font-size: 0.9rem; color: var(--text-color);">Status dos Docentes (ANO
                        <?= $current_year ?>)</h4>
                </div>

                <div
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 10px; flex-wrap: wrap;">
                    <div style="display: flex; gap: 8px;">
                        <button class="status-tab-btn active" onclick="filterStatusTable('all', this)">Todos</button>
                        <button class="status-tab-btn" onclick="filterStatusTable('yes', this)">Com Férias</button>
                        <button class="status-tab-btn" onclick="filterStatusTable('no', this)">Sem Férias</button>
                    </div>
                    <button type="button" id="btn_select_all" class="btn"
                        style="padding: 8px 15px; font-size: 0.75rem; background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.25); color: #fff; display: none; align-items: center; gap: 8px;"
                        onclick="selectAllDocentes()">
                        <i class="fas fa-check-double"></i> Selecionar Todos
                    </button>
                </div>

                <div class="search-box" style="margin-bottom: 15px;">
                    <input type="text" id="statusTableSearch" placeholder="Pesquisar docente..." class="form-input"
                        style="padding: 8px 12px; font-size: 0.8rem;" onkeyup="searchStatusTable()">
                </div>

                <div
                    style="max-height: 400px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 10px;">
                    <table class="status-table" id="prof-status-table">
                        <thead>
                            <tr>
                                <th>Docente</th>
                                <th style="text-align: center;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($professores_status as $ps): ?>
                                <tr data-prof-id="<?= $ps['id'] ?>"
                                    data-status="<?= $ps['has_vacation'] > 0 ? 'yes' : 'no' ?>"
                                    onclick="selectDocente(<?= $ps['id'] ?>, this)">
                                    <td>
                                        <div style="font-weight: 600;"><?= htmlspecialchars($ps['nome']) ?></div>
                                        <div style="font-size: 0.7rem; color: var(--text-muted);">
                                            <?= htmlspecialchars($ps['tipo_contrato']) ?></div>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($ps['has_vacation'] > 0): ?>
                                            <span class="status-badge status-yes"><i class="fas fa-check"></i> Agendada</span>
                                        <?php else: ?>
                                            <span class="status-badge status-no"><i class="fas fa-times"></i> Nenhuma</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let currentPage = 1;
    const itemsPerPage = 20;
    let currentSort = { column: null, direction: 'asc' };
    const allVacations = <?= json_encode($ferias) ?>;

    function sortTable(columnIndex) {
        const table = document.querySelector('table');
        const headers = table.querySelectorAll('th');

        if (currentSort.column === columnIndex) {
            currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
        } else {
            currentSort.column = columnIndex;
            currentSort.direction = 'asc';
        }

        headers.forEach((th, idx) => {
            const icon = th.querySelector('.sort-icon');
            if (icon) {
                if (idx === columnIndex) {
                    icon.innerHTML = currentSort.direction === 'asc' ? ' <i class="fas fa-sort-up"></i>' : ' <i class="fas fa-sort-down"></i>';
                    th.style.color = 'var(--primary-red)';
                } else {
                    icon.innerHTML = ' <i class="fas fa-sort" style="opacity: 0.3;"></i>';
                    th.style.color = '';
                }
            }
        });

        applySortAndPaginate();
    }

    function applySortAndPaginate() {
        const tbody = document.querySelector('tbody');
        const searchTerm = document.getElementById('tableSearch').value.toLowerCase();
        const rows = Array.from(tbody.querySelectorAll('tr:not(.empty-row)'));

        let filteredRows = rows.filter(row => row.innerText.toLowerCase().includes(searchTerm));

        if (currentSort.column !== null) {
            filteredRows.sort((a, b) => {
                let valA = a.cells[currentSort.column].innerText.trim();
                let valB = b.cells[currentSort.column].innerText.trim();

                if ([5, 6].includes(currentSort.column)) { // Datas
                    const parseD = (s) => {
                        const p = s.split('/');
                        return new Date(p[2], p[1] - 1, p[0]);
                    };
                    valA = parseD(valA);
                    valB = parseD(valB);
                } else {
                    valA = valA.toLowerCase();
                    valB = valB.toLowerCase();
                }

                if (valA < valB) return currentSort.direction === 'asc' ? -1 : 1;
                if (valA > valB) return currentSort.direction === 'asc' ? 1 : -1;
                return 0;
            });
            filteredRows.forEach(row => tbody.appendChild(row));
        }

        updatePagination();
    }

    function updatePagination() {
        const selectAll = document.getElementById('selectAll');
        if (selectAll) selectAll.checked = false;

        const searchTerm = document.getElementById('tableSearch').value.toLowerCase();
        const tbody = document.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr:not(.empty-row)'));

        const filteredRows = rows.filter(row => {
            return row.innerText.toLowerCase().includes(searchTerm);
        });

        rows.forEach(row => row.style.display = 'none');
        const totalPages = Math.ceil(filteredRows.length / itemsPerPage);

        if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const start = (currentPage - 1) * itemsPerPage;
        const end = start + itemsPerPage;

        filteredRows.forEach((row, idx) => {
            if (idx >= start && idx < end) {
                row.style.display = '';
            }
        });

        document.getElementById('page-info').innerText = `Página ${currentPage} de ${totalPages || 1}`;
        document.getElementById('prev-page').disabled = currentPage === 1;
        document.getElementById('next-page').disabled = currentPage === totalPages || totalPages === 0;
    }

    function changePage(delta) {
        currentPage += delta;
        updatePagination();
    }

    // Modal Control
    function openVacationModal() {
        document.getElementById('vacation-modal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeVacationModal() {
        if (window.feriasAdded) {
            location.reload();
            return;
        }
        document.getElementById('vacation-modal').style.display = 'none';
        document.getElementById('modal-body-container').classList.remove('show-details');
        
        // Reset botão de histórico
        const btnHistory = document.getElementById('btn-toggle-history');
        btnHistory.classList.remove('active');
        btnHistory.innerHTML = '<i class="fas fa-history"></i> VER HISTÓRICO';
        btnHistory.style.background = 'rgba(255,255,255,0.1)';
        btnHistory.style.color = '#94a3b8';
        
        document.body.style.overflow = 'auto';

        // Reset Modal to Default
        const form = document.getElementById('form-ferias');
        form.reset();
        form.querySelector('input[name="id"]').value = '';
        form.querySelector('input[name="start_date"]').value = '<?= date('Y-m-d') ?>';
        form.querySelector('input[name="end_date"]').value = '<?= date('Y-m-d', strtotime('+30 days')) ?>';
        document.getElementById('modal-title').innerHTML = '<i class="fas fa-calendar-plus" style="color: var(--primary-red);"></i> Novo Registro de Férias';
        document.getElementById('btn-save-modal').innerHTML = '<i class="fas fa-check-circle"></i> SALVAR FÉRIAS';
        document.querySelectorAll('#prof-status-table tr').forEach(r => r.classList.remove('selected-row', 'multi-selected'));
        document.getElementById('collective_ids_container').innerHTML = '';
        document.getElementById('modal_teacher_group').style.display = 'block';
        document.getElementById('modal_teacher_id').setAttribute('required', 'required');
    }

    function editVacation(id) {
        // Encontrar os dados (allVacations contém objetos com teacher_id, type, dates)
        const vac = allVacations.find(v => v.id == id);
        if (!vac) return;

        openVacationModal();

        const form = document.getElementById('form-ferias');
        form.querySelector('input[name="id"]').value = vac.id;
        document.getElementById('modal-title').innerHTML = '<i class="fas fa-edit" style="color: var(--primary-red);"></i> Editar Registro de Férias';
        document.getElementById('btn-save-modal').innerHTML = '<i class="fas fa-save"></i> ATUALIZAR FÉRIAS';

        const typeSelect = document.getElementById('type_select_modal');
        typeSelect.value = vac.type;
        toggleModalFields(); // Ajusta campos necessários

        form.querySelector('input[name="start_date"]').value = vac.start_date;
        form.querySelector('input[name="end_date"]').value = vac.end_date;

        if (vac.type === 'individual' && vac.teacher_id) {
            const row = document.querySelector(`#prof-status-table tr[data-prof-id="${vac.teacher_id}"]`);
            if (row) selectDocente(vac.teacher_id, row);
        } else if (vac.type === 'collective' && vac.teacher_id) {
            // Se for coletivo mas para um professor específico
            const row = document.querySelector(`#prof-status-table tr[data-prof-id="${vac.teacher_id}"]`);
            if (row) selectDocente(vac.teacher_id, row);
        }
    }

    function toggleModalFields() {
        const type = document.getElementById('type_select_modal').value;
        const teacherGroup = document.getElementById('modal_teacher_group');
        const teacherSelect = document.getElementById('modal_teacher_id');
        const container = document.getElementById('collective_ids_container');

        // Resetar seleções ao trocar de tipo
        document.querySelectorAll('#prof-status-table tr').forEach(r => r.classList.remove('selected-row', 'multi-selected'));
        container.innerHTML = '';
        teacherSelect.value = '';

        if (type === 'collective') {
            teacherGroup.style.display = 'none';
            teacherSelect.removeAttribute('required');
            document.getElementById('btn_select_all').style.display = 'inline-flex';
            document.getElementById('modal-body-container').classList.remove('show-details');
        } else {
            teacherGroup.style.display = 'block';
            teacherSelect.setAttribute('required', 'required');
            document.getElementById('btn_select_all').style.display = 'none';
        }
    }

    // Status Table Control
    function filterStatusTable(status, btn) {
        document.querySelectorAll('.status-tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        const rows = document.querySelectorAll('#prof-status-table tbody tr');
        rows.forEach(row => {
            if (status === 'all' || row.dataset.status === status) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
        searchStatusTable(); // Re-apply search
    }

    function searchStatusTable() {
        const query = document.getElementById('statusTableSearch').value.toLowerCase();
        const activeBtn = document.querySelector('.status-tab-btn.active');
        const statusLimit = activeBtn ? activeBtn.innerText.toLowerCase() : 'todos';

        const rows = document.querySelectorAll('#prof-status-table tbody tr');

        rows.forEach(row => {
            const name = row.querySelector('div').innerText.toLowerCase();
            const matchesQuery = name.includes(query);

            let matchesTab = true;
            if (statusLimit === 'com férias') matchesTab = row.dataset.status === 'yes';
            else if (statusLimit === 'sem férias') matchesTab = row.dataset.status === 'no';

            row.style.display = (matchesQuery && matchesTab) ? '' : 'none';
        });
    }

    // Teacher Selection from Helper Table
    function selectDocente(id, row) {
        const type = document.getElementById('type_select_modal').value;
        const teacherSelect = document.getElementById('modal_teacher_id');
        const container = document.getElementById('collective_ids_container');
        const detailPane = document.getElementById('modal-body-container');
        const listDiv = document.getElementById('teacher-vacations-list');

        if (type === 'individual') {
            // Unico docente
            teacherSelect.value = id;
            document.querySelectorAll('#prof-status-table tr').forEach(r => r.classList.remove('selected-row', 'multi-selected'));
            row.classList.add('selected-row');

            // 1. Filtrar histórico para o painel lateral
            const profVacations = allVacations.filter(v => v.teacher_id == id);
            
            // 2. Preencher datas automaticamente (Novidade)
            const startDateInput = document.querySelector('input[name="start_date"]');
            const endDateInput = document.querySelector('input[name="end_date"]');
            
            if (profVacations.length > 0) {
                // Pegar o último registro de férias marcado
                const lastVac = profVacations[profVacations.length - 1];
                startDateInput.value = lastVac.start_date;
                endDateInput.value = lastVac.end_date;
            } else {
                // Se não houver nenhum, limpar (equivalente a 00/00/0000 no picker do browser)
                startDateInput.value = '';
                endDateInput.value = '';
            }

            // Atualizar conteúdo do histórico (não abrir automaticamente)
            if (profVacations.length === 0) {
                listDiv.innerHTML = '<div style="color: #64748b; font-size: 0.85rem; padding: 20px; text-align: center; border: 1px dashed rgba(255,255,255,0.1); border-radius: 12px; margin-top: 10px;">Nenhuma férias agendada para este docente.</div>';
            } else {
                listDiv.innerHTML = profVacations.map(v => {
                    const badge = v.type === 'collective' ? 'collective' : 'individual';
                    const lbl = v.type === 'collective' ? 'Coletivas' : 'Individuais';
                    return `
                        <div class="detail-v-card ${badge}">
                            <div style="font-weight: 700; color: #fff; font-size: 0.82rem;">${lbl}</div>
                            <div style="color: #94a3b8; font-size: 0.75rem;">
                                <i class="far fa-calendar-alt"></i> ${v.start_date.split('-').reverse().join('/')} até ${v.end_date.split('-').reverse().join('/')}
                            </div>
                        </div>
                    `;
                }).join('');
            }

            // Visual feedback
            teacherSelect.style.borderColor = 'var(--primary-red)';
            setTimeout(() => teacherSelect.style.borderColor = '', 1000);
        } else {
            // Múltiplos docentes para modo Coletivo
            row.classList.toggle('multi-selected');
            detailPane.classList.remove('show-details');

            // Atualizar inputs ocultos
            updateCollectiveInputs();
        }
    }

    function toggleHistory() {
        const detailPane = document.getElementById('modal-body-container');
        const btn = document.getElementById('btn-toggle-history');
        const isShowing = detailPane.classList.toggle('show-details');
        
        if (isShowing) {
            btn.classList.add('active');
            btn.innerHTML = '<i class="fas fa-eye-slash"></i> OCULTAR HISTÓRICO';
            btn.style.background = 'var(--primary-red)';
            btn.style.color = '#fff';
        } else {
            btn.classList.remove('active');
            btn.innerHTML = '<i class="fas fa-history"></i> VER HISTÓRICO';
            btn.style.background = 'rgba(255,255,255,0.1)';
            btn.style.color = '#94a3b8';
        }
    }

    function updateCollectiveInputs() {
        const container = document.getElementById('collective_ids_container');
        container.innerHTML = '';
        const selectedRows = document.querySelectorAll('#prof-status-table tr.multi-selected');

        selectedRows.forEach(row => {
            const id = row.dataset.profId;
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'collective_teacher_ids[]';
            input.value = id;
            container.appendChild(input);
        });

        // Atualizar texto do botão Select All
        const btnAll = document.getElementById('btn_select_all');
        if (selectedRows.length > 0) {
            btnAll.innerHTML = '<i class="fas fa-times-circle"></i> Desmarcar Todos';
        } else {
            btnAll.innerHTML = '<i class="fas fa-check-double"></i> Selecionar Todos';
        }
    }

    function selectAllDocentes() {
        const rows = document.querySelectorAll('#prof-status-table tbody tr');
        const visibleRows = Array.from(rows).filter(r => r.style.display !== 'none');
        const selectedCount = visibleRows.filter(r => r.classList.contains('multi-selected')).length;

        if (selectedCount === visibleRows.length && selectedCount > 0) {
            // Desmarcar todos visíveis
            visibleRows.forEach(r => r.classList.remove('multi-selected'));
        } else {
            // Marcar todos visíveis
            visibleRows.forEach(r => r.classList.add('multi-selected'));
        }
        updateCollectiveInputs();
    }

    window.feriasAdded = false;
    document.getElementById('form-ferias').addEventListener('submit', function (e) {
        e.preventDefault();
        const form = this;
        const btn = form.querySelector('button[type="submit"]');
        const oldHtml = btn.innerHTML;
        const oldBg = btn.style.background;

        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> SALVANDO...';
        btn.style.background = '#475569';
        btn.disabled = true;

        fetch(form.action, {
            method: 'POST',
            body: new FormData(form)
        }).then(res => res.json()).then(data => {
            if (data.status === 'success') {
                window.feriasAdded = true;
                btn.innerHTML = '<i class="fas fa-check"></i> SALVO!';
                btn.style.background = '#059669';

                // Update table row visually
                const type = document.getElementById('type_select_modal').value;
                const selectedIds = Array.from(document.querySelectorAll('input[name="collective_teacher_ids[]"]')).map(i => i.value);
                const individualId = document.getElementById('modal_teacher_id').value;

                if (type === 'individual') {
                    const tr = document.querySelector('#prof-status-table tr[data-prof-id="' + individualId + '"]');
                    updateRowStatus(tr);
                } else if (selectedIds.length > 0) {
                    // Update only selected teachers in collective mode
                    selectedIds.forEach(id => {
                        const tr = document.querySelector('#prof-status-table tr[data-prof-id="' + id + '"]');
                        updateRowStatus(tr);
                    });
                } else {
                    // Update all (Collective for everyone)
                    document.querySelectorAll('#prof-status-table tr[data-prof-id]').forEach(tr => {
                        updateRowStatus(tr);
                    });
                }

                // Cleanup Modal for next
                document.getElementById('modal_teacher_id').value = '';
                document.getElementById('collective_ids_container').innerHTML = '';
                document.querySelectorAll('#prof-status-table tr.selected-row, #prof-status-table tr.multi-selected').forEach(r => r.classList.remove('selected-row', 'multi-selected'));
                document.getElementById('btn_select_all').innerHTML = '<i class="fas fa-check-double"></i> Selecionar Todos';

                // Restore Button after 2 sec
                setTimeout(() => {
                    btn.innerHTML = oldHtml;
                    btn.style.background = oldBg;
                    btn.disabled = false;
                }, 1500);
            }
        }).catch(err => {
            console.error(err);
            btn.innerHTML = oldHtml;
            btn.style.background = oldBg;
            btn.disabled = false;
            alert('Erro ao processar as férias.');
        });
    });

    function updateRowStatus(tr) {
        if (!tr) return;
        tr.dataset.status = 'yes';
        const td = tr.querySelector('td:last-child');
        if (td) {
            td.innerHTML = '<span class="status-badge status-yes"><i class="fas fa-check"></i> Agendada</span>';
        }
    }

    function toggleSelectAll(master) {
        const checkboxes = document.querySelectorAll('.row-checkbox');
        checkboxes.forEach(cb => {
            if (cb.closest('.table-row').style.display !== 'none') {
                cb.checked = master.checked;
            }
        });
        updateBulkButton();
    }

    function updateBulkButton() {
        const selected = document.querySelectorAll('.row-checkbox:checked').length;
        const btn = document.getElementById('btn-delete-bulk');
        if (btn) {
            btn.style.display = selected > 0 ? 'inline-flex' : 'none';
            btn.innerHTML = `<i class="fas fa-trash-alt"></i> Excluir ${selected}`;
        }
    }

    function deleteSelectedVacations() {
        const selected = Array.from(document.querySelectorAll('.row-checkbox:checked')).map(cb => cb.value);
        if (selected.length === 0) return;

        if (confirm(`Tem certeza que deseja excluir os ${selected.length} registros selecionados?`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '../controllers/ferias_process.php?action=delete_bulk';

            selected.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = id;
                form.appendChild(input);
            });

            document.body.appendChild(form);
            form.submit();
        }
    }

    function deleteAllVacations() {
        Swal.fire({
            title: 'Tem certeza?',
            text: "Esta ação removerá TODAS as férias e afastamentos permanentemente!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#2e7d32',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sim, excluir tudo!',
            cancelButtonText: 'Não, cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '../controllers/ferias_process.php?action=delete_all';
            }
        });
    }

    window.addEventListener('load', updatePagination);
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>