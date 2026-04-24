<?php
require_once __DIR__ . '/../configs/db.php';
include __DIR__ . '/../components/header.php';

// Apenas admin/gestor
if (!isAdmin() && !isGestor()) {
    header("Location: ../../index.php");
    exit;
}

$feriados = mysqli_fetch_all(mysqli_query($conn, "SELECT * FROM holidays ORDER BY date ASC"), MYSQLI_ASSOC);
?>

<?php if (isset($_GET['msg'])): ?>
    <?php if ($_GET['msg'] === 'created'): ?>
        <div class="alert alert-success"
            style="padding: 15px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 8px; margin-bottom: 20px;">
            Feriado criado com sucesso!</div>
    <?php elseif ($_GET['msg'] === 'updated'): ?>
        <div class="alert alert-success"
            style="padding: 15px; background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; border-radius: 8px; margin-bottom: 20px;">
            Feriado atualizado com sucesso!</div>
    <?php elseif ($_GET['msg'] === 'deleted'): ?>
        <div class="alert alert-warning"
            style="padding: 15px; background: #fff3cd; color: #856404; border: 1px solid #ffeeba; border-radius: 8px; margin-bottom: 20px;">
            Feriado removido com sucesso.</div>
    <?php elseif ($_GET['msg'] === 'duplicate'): ?>
        <div class="alert alert-danger"
            style="padding: 15px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 8px; margin-bottom: 20px;">
            Erro: Já existe um feriado com este nome e data!</div>
    <?php elseif ($_GET['msg'] === 'deleted_all'): ?>
        <div class="alert alert-danger"
            style="padding: 15px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 8px; margin-bottom: 20px;">
            Todos os feriados foram removidos com sucesso.</div>
    <?php elseif ($_GET['msg'] === 'deleted_bulk'): ?>
        <div class="alert alert-warning"
            style="padding: 15px; background: #fff3cd; color: #856404; border: 1px solid #ffeeba; border-radius: 8px; margin-bottom: 20px;">
            <?= (int) $_GET['count'] ?> feriados removidos com sucesso.</div>
    <?php elseif ($_GET['msg'] === 'unauthorized'): ?>
        <div class="alert alert-danger"
            style="padding: 15px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 8px; margin-bottom: 20px;">
            Permissão negada para esta ação.</div>
    <?php endif; ?>
<?php endif; ?>

<div class="page-header">
    <h2>Gestão de Feriados</h2>
</div>

<div class="filter-bar" style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center; justify-content: flex-end;">
    <div class="search-box" style="flex: 1; max-width: 350px;">
        <input type="text" id="tableSearch" placeholder="Buscar feriado por nome ou data..." class="form-input" style="width: 100%;"
            onkeyup="currentPage=1; updatePagination(); updateFilterChips();">
    </div>
    <div class="header-actions" style="display: flex; gap: 8px;">
        <a href="feriados_form.php" class="btn btn-primary" style="font-weight: 700;"><i class="fas fa-plus"></i> NOVO FERIADO</a>
        <button type="button" id="btn-delete-bulk" class="btn" style="background: #fb8c00; color: white; display: none; font-weight: 700;"
            onclick="deleteSelectedHolidays()">
            <i class="fas fa-trash-alt"></i> Excluir Selecionados
        </button>
        <?php if (isAdmin()): ?>
            <button type="button" class="btn" style="background: #e53935; color: white; font-weight: 700;" onclick="deleteAllHolidays()">
                <i class="fas fa-dumpster-fire"></i> Excluir Tudo
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="filter-chips-container dashboard-container" id="filter-chips-container" style="margin-bottom: 20px;">
    <!-- Chips via JS -->
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th style="width: 40px; text-align: center;">
                    <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)">
                </th>
                <th style="width: 50px;">#</th>
                <th>Início</th>
                <th>Fim</th>
                <th>Nome / Descrição</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($feriados)): ?>
                <tr>
                    <td colspan="3" class="text-center">Nenhum feriado cadastrado.</td>
                </tr>
            <?php else: ?>
                <?php $idx = 1;
                foreach ($feriados as $f): ?>
                    <tr class="table-row">
                        <td style="text-align: center;">
                            <input type="checkbox" class="row-checkbox" value="<?= $f['id'] ?>" onclick="updateBulkButton()">
                        </td>
                        <td style="color: var(--text-muted); font-size: 0.8rem;"><?= $idx++ ?></td>
                        <td>
                            <?= date('d/m/Y', strtotime($f['date'])) ?>
                        </td>
                        <td>
                            <?= $f['end_date'] ? date('d/m/Y', strtotime($f['end_date'])) : date('d/m/Y', strtotime($f['date'])) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($f['name']) ?>
                        </td>
                        <td>
                            <a href="feriados_form.php?id=<?= $f['id'] ?>" class="btn btn-edit"><i class="fas fa-edit"></i></a>
                            <a href="../controllers/feriados_process.php?action=delete&id=<?= $f['id'] ?>"
                                class="btn btn-delete"
                                onclick="return confirm('Tem certeza que deseja remover este feriado?')"><i
                                    class="fas fa-trash"></i></a>
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

<script>
    let currentPage = 1;
    const itemsPerPage = 20;

    function updatePagination() {
        // Reset select all when searching or paginating
        const selectAll = document.getElementById('selectAll');
        if (selectAll) selectAll.checked = false;

        const searchTerm = document.getElementById('tableSearch').value.toLowerCase();
        const rows = Array.from(document.querySelectorAll('.table-row'));

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

    function deleteSelectedHolidays() {
        const selected = Array.from(document.querySelectorAll('.row-checkbox:checked')).map(cb => cb.value);
        if (selected.length === 0) return;

        if (confirm(`Tem certeza que deseja excluir os ${selected.length} feriados selecionados?`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '../controllers/feriados_process.php?action=delete_bulk';

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

    function deleteAllHolidays() {
        Swal.fire({
            title: 'Tem certeza?',
            text: "Esta ação removerá TODOS os feriados permanentemente!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#2e7d32',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sim, excluir tudo!',
            cancelButtonText: 'Não, cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '../controllers/feriados_process.php?action=delete_all';
            }
        });
    }

    function updateFilterChips() {
        const container = document.getElementById('filter-chips-container');
        if (!container) return;
        container.innerHTML = '';

        const el = document.getElementById('tableSearch');
        if (el && el.value) {
            const chip = document.createElement('div');
            chip.className = 'filter-chip animate-fade-in';
            chip.innerHTML = `<i class="fas fa-search"></i> <span>Busca: ${el.value}</span> <i class="fas fa-times remove-chip" onclick="document.getElementById('tableSearch').value=''; updatePagination(); updateFilterChips();"></i>`;
            container.appendChild(chip);
        }
    }

    window.addEventListener('load', () => {
        updatePagination();
        updateFilterChips();
    });
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>