<?php
require_once __DIR__ . '/../configs/db.php';
include __DIR__ . '/../components/header.php';

$show_inactive = isset($_GET['show_inactive']) && $_GET['show_inactive'] == '1';
$status_filter = $show_inactive ? 0 : 1;

$stmt = $conn->prepare("SELECT * FROM docente WHERE ativo = ? ORDER BY nome ASC");
$stmt->bind_param('i', $status_filter);
$stmt->execute();
$professores = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<div class="page-header">
    <h2>Gestão de Professores</h2>
</div>

<div class="filter-bar" style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center; justify-content: flex-end;">
    <div class="search-box" style="flex: 1; max-width: 400px;">
        <input type="text" id="filter-nome" placeholder="Buscar professor ou área..." class="form-input"
            style="width: 100%;" onkeyup="filterProfessores()" onkeydown="if(event.key==='Enter') event.preventDefault();">
    </div>
    <div class="header-actions" style="display: flex; gap: 8px;">
        <?php if (can_edit()): ?>
            <a href="professores_form.php" class="btn btn-primary" style="font-weight: 700;"><i class="fas fa-plus"></i> NOVO PROFESSOR</a>
        <?php endif; ?>
        <?php if ($show_inactive): ?>
            <a href="professores.php" class="btn btn-secondary" style="font-weight: 700;"><i class="fas fa-check-circle"></i> VER ATIVOS</a>
        <?php else: ?>
            <a href="professores.php?show_inactive=1" class="btn btn-secondary" style="font-weight: 700;"><i class="fas fa-archive"></i> VER INATIVOS</a>
        <?php endif; ?>
    </div>
</div>

<div class="filter-chips-container dashboard-container" id="filter-chips-container" style="margin-bottom: 20px;">
    <!-- Chips serão inseridos aqui via JS -->
</div>

<div class="table-container">
    <table id="professores-table">
        <thead>
            <tr>
                <th style="width: 50px;">#</th>
                <th style="cursor: pointer;"
                    onclick="currentSort.column=1; currentSort.direction=(currentSort.direction==='asc'?'desc':'asc'); applySortAndPaginate();">
                    Nome <i class="fas fa-sort"></i></th>
                <th style="cursor: pointer;"
                    onclick="currentSort.column=2; currentSort.direction=(currentSort.direction==='asc'?'desc':'asc'); applySortAndPaginate();">
                    Área de Conhecimento <i class="fas fa-sort"></i></th>
                <th>Limites (S/M)</th>
                <th>Tipo Contrato</th>
                <?php if (can_edit()): ?>
                    <th style="text-align: center;">Ações</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($professores)): ?>
                <tr>
                    <td colspan="4" class="text-center">Nenhum docente cadastrado.</td>
                </tr>
            <?php else: ?>
                <?php $idx = 1;
                foreach ($professores as $p): ?>
                    <tr class="table-row matches-filter" data-id="<?= $p['id'] ?>"
                        data-area="<?= htmlspecialchars($p['area_conhecimento']) ?>">
                        <td style="color: var(--text-muted); font-size: 0.8rem; font-weight: 600;"><?= $idx++ ?></td>
                        <td class="prof-name-cell" style="font-weight: 700; color: var(--text-color);">
                            <?= htmlspecialchars($p['nome']) ?>
                            <?php if ($p['ativo'] == 0): ?>
                                <span class="badge"
                                    style="background: rgba(239, 68, 68, 0.1); color: #f87171; border-color: rgba(239, 68, 68, 0.2); margin-left: 10px; font-size: 0.65rem;">Inativo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="area-badge"
                                style="background: rgba(0,0,0,0.05); padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; border: 1px solid rgba(0,0,0,0.08);">
                                <?= htmlspecialchars($p['area_conhecimento']) ?>
                            </span>
                        </td>
                        <td style="font-weight: 600; color: var(--primary-color);">
                            <i class="fas fa-hourglass-half" style="font-size: 0.7rem; opacity: 0.5; margin-right: 4px;"></i>
                            <?= $p['weekly_hours_limit'] ?>h / <?= $p['monthly_hours_limit'] ?>h
                        </td>
                        <td><span
                                style="font-size: 0.85rem; color: var(--text-muted);"><?= htmlspecialchars($p['tipo_contrato'] ?? 'N/A') ?></span>
                        </td>
                        <?php if (can_edit()): ?>
                            <td>
                                <div style="display: flex; gap: 5px; justify-content: center; align-items: center;">
                                    <a href="professores_form.php?id=<?= $p['id'] ?>" class="btn btn-edit" title="Editar"><i
                                            class="fas fa-edit"></i></a>
                                    <?php if ($p['ativo'] == 1): ?>
                                        <a href="../controllers/professores_process.php?action=delete&id=<?= $p['id'] ?>"
                                            class="btn btn-delete"
                                            onclick="return confirm('Desativar este professor? Ele deixará de aparecer nas listas, mas seus dados serão mantidos.')"
                                            title="Desativar"><i class="fas fa-user-slash"></i></a>
                                    <?php else: ?>
                                        <a href="../controllers/professores_process.php?action=activate&id=<?= $p['id'] ?>"
                                            class="btn btn-edit"
                                            style="background-color: var(--primary-green); border-color: var(--primary-green);"
                                            onclick="return confirm('Reativar este professor?')" title="Reativar"><i
                                                class="fas fa-user-check"></i></a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="pagination-container">
    <div class="pagination-info" id="page-info">
        Exibindo página 1 de 1
    </div>
    <ul class="pagination-pages" id="pagination-list">
        <!-- Gerado via JS -->
    </ul>
</div>

</div>

<script>
    let currentPage = 1;
    const itemsPerPage = 20;
    let currentSort = { column: null, direction: 'asc' };

    function filterProfessores() {
        const input = document.getElementById('filter-nome');
        const term = input ? input.value.toLowerCase().trim() : '';
        const rows = Array.from(document.querySelectorAll('#professores-table tbody tr:not(.empty-row)'));

        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            if (text.includes(term)) {
                row.classList.add('matches-filter');
            } else {
                row.classList.remove('matches-filter');
            }
        });

        updateFilterChips();
        applySortAndPaginate();
    }


    function updateFilterChips() {
        const container = document.getElementById('filter-chips-container');
        if (!container) return;
        container.innerHTML = '';

        const el = document.getElementById('filter-nome');
        if (el && el.value) {
            const chip = document.createElement('div');
            chip.className = 'filter-chip animate-fade-in';
            chip.innerHTML = `<i class="fas fa-search"></i> <span>Busca: ${el.value}</span> <i class="fas fa-times remove-chip" onclick="document.getElementById('filter-nome').value=''; filterProfessores();"></i>`;
            container.appendChild(chip);
        }
    }

    function applySortAndPaginate() {
        const tbody = document.querySelector('#professores-table tbody');
        const rows = Array.from(document.querySelectorAll('#professores-table tbody tr.table-row'));

        if (currentSort.column !== null) {
            rows.sort((a, b) => {
                let valA = a.cells[currentSort.column].innerText.trim();
                let valB = b.cells[currentSort.column].innerText.trim();

                valA = valA.toLowerCase();
                valB = valB.toLowerCase();

                if (valA < valB) return currentSort.direction === 'asc' ? -1 : 1;
                if (valA > valB) return currentSort.direction === 'asc' ? 1 : -1;
                return 0;
            });
            rows.forEach(row => tbody.appendChild(row));
        }

        updatePagination();
    }

    function updatePagination() {
        const rows = Array.from(document.querySelectorAll('#professores-table tbody tr.matches-filter'));
        const allRows = Array.from(document.querySelectorAll('#professores-table tbody tr.table-row'));
        const totalPages = Math.ceil(rows.length / itemsPerPage);

        if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        allRows.forEach(row => row.style.display = 'none');

        const start = (currentPage - 1) * itemsPerPage;
        const end = start + itemsPerPage;

        rows.forEach((row, idx) => {
            if (idx >= start && idx < end) {
                row.style.display = '';
            }
        });

        // Atualiza Texto de Info
        const infoEl = document.getElementById('page-info');
        if (infoEl) {
            const currentTotal = rows.length;
            const shownStart = currentTotal > 0 ? start + 1 : 0;
            const shownEnd = Math.min(end, currentTotal);
            infoEl.innerHTML = `Exibindo <strong>${shownStart}-${shownEnd}</strong> de <strong>${currentTotal}</strong> docentes`;
        }

        // Renderiza Lista de Páginas Premium
        const listEl = document.getElementById('pagination-list');
        if (listEl) {
            listEl.innerHTML = '';
            const prevLi = document.createElement('li');
            prevLi.className = `page-item nav-btn ${currentPage === 1 ? 'disabled' : ''}`;
            prevLi.innerHTML = `<i class="fas fa-chevron-left"></i> Anterior`;
            if (currentPage > 1) prevLi.onclick = () => changePage(-1);
            listEl.appendChild(prevLi);

            const maxVisible = 5;
            let startPage = Math.max(1, currentPage - 2);
            let endPage = Math.min(totalPages, startPage + maxVisible - 1);
            if (endPage - startPage < maxVisible - 1) startPage = Math.max(1, endPage - maxVisible + 1);

            for (let i = startPage; i <= endPage; i++) {
                const li = document.createElement('li');
                li.className = `page-item ${i === currentPage ? 'active' : ''}`;
                li.innerText = i;
                li.onclick = () => { currentPage = i; updatePagination(); window.scrollTo({ top: 0, behavior: 'smooth' }); };
                listEl.appendChild(li);
            }

            const nextLi = document.createElement('li');
            nextLi.className = `page-item nav-btn ${currentPage === totalPages || totalPages === 0 ? 'disabled' : ''}`;
            nextLi.innerHTML = `Próximo <i class="fas fa-chevron-right"></i>`;
            if (currentPage < totalPages && totalPages > 0) nextLi.onclick = () => changePage(1);
            listEl.appendChild(nextLi);
        }
    }

    function changePage(delta) {
        currentPage += delta;
        updatePagination();
    }

    window.addEventListener('load', () => {
        filterProfessores();
    });
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>