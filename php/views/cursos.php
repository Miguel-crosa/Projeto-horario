<?php
require_once __DIR__ . '/../configs/db.php';
include __DIR__ . '/../components/header.php';

$cursos = mysqli_fetch_all(mysqli_query($conn, "SELECT * FROM curso ORDER BY nome ASC"), MYSQLI_ASSOC);
?>

<div class="filter-bar" style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center; justify-content: flex-end;">
    <div class="search-box" style="flex: 1; max-width: 400px;">
        <input type="text" id="filter-nome" placeholder="Filtrar por nome do curso..." class="form-input"
            style="width: 100%;" onkeyup="filterCursos()">
    </div>
    <div class="header-actions">
        <?php if (can_edit()): ?>
            <a href="cursos_form.php" class="btn btn-primary" style="font-weight: 700;"><i class="fas fa-plus"></i> NOVO CURSO</a>
        <?php endif; ?>
    </div>
</div>

<div class="filter-chips-container dashboard-container" id="filter-chips-container" style="margin-bottom: 20px;">
    <!-- Chips via JS -->
</div>

<script>
    let currentPage = 1;
    const itemsPerPage = 20;
    let currentSort = { column: null, direction: 'asc' };

    function filterCursos() {
        const queryInput = document.getElementById('filter-nome');
        const query = queryInput ? queryInput.value.toLowerCase().trim() : '';
        const rows = Array.from(document.querySelectorAll('#cursos-table tbody tr:not(.empty-row)'));
        
        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            if (text.includes(query)) {
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
            chip.innerHTML = `<i class="fas fa-search"></i> <span>Busca: ${el.value}</span> <i class="fas fa-times remove-chip" onclick="document.getElementById('filter-nome').value=''; filterCursos();"></i>`;
            container.appendChild(chip);
        }
    }

    function sortTable(columnIndex) {
        const table = document.querySelector('#cursos-table table');
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
                    th.classList.add('active-sort');
                } else {
                    icon.innerHTML = ' <i class="fas fa-sort" style="opacity: 0.3;"></i>';
                    th.classList.remove('active-sort');
                }
            }
        });

        applySortAndPaginate();
    }

    function applySortAndPaginate() {
        const tbody = document.querySelector('#cursos-table tbody');
        const rows = Array.from(tbody.querySelectorAll('tr.matches-filter'));
        
        if (currentSort.column !== null) {
            rows.sort((a, b) => {
                let valA = a.cells[currentSort.column].innerText.trim();
                let valB = b.cells[currentSort.column].innerText.trim();

                if (currentSort.column === 4) { // Carga Horária
                    valA = parseInt(valA) || 0;
                    valB = parseInt(valB) || 0;
                } else {
                    valA = valA.toLowerCase();
                    valB = valB.toLowerCase();
                }

                if (valA < valB) return currentSort.direction === 'asc' ? -1 : 1;
                if (valA > valB) return currentSort.direction === 'asc' ? 1 : -1;
                return 0;
            });

            rows.forEach(row => tbody.appendChild(row));
        }

        updatePagination();
    }

    function updatePagination() {
        const rows = Array.from(document.querySelectorAll('#cursos-table tbody tr.matches-filter'));
        const allRows = Array.from(document.querySelectorAll('#cursos-table tbody tr:not(.empty-row)'));
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

        const infoEl = document.getElementById('page-info');
        if (infoEl) {
            const currentTotal = rows.length;
            const shownStart = currentTotal > 0 ? start + 1 : 0;
            const shownEnd = Math.min(end, currentTotal);
            infoEl.innerHTML = `Exibindo <strong>${shownStart}-${shownEnd}</strong> de <strong>${currentTotal}</strong> cursos`;
        }
        
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
        filterCursos();
    });
</script>

<div class="table-container" id="cursos-table">
    <table>
        <thead>
            <tr>
                <th style="width: 50px;">#</th>
                <th onclick="sortTable(1)" style="cursor:pointer;">Nome <span class="sort-icon"><i class="fas fa-sort" style="opacity: 0.3;"></i></span></th>
                <th onclick="sortTable(2)" style="cursor:pointer;">Tipo <span class="sort-icon"><i class="fas fa-sort" style="opacity: 0.3;"></i></span></th>
                <th onclick="sortTable(3)" style="cursor:pointer;">Área <span class="sort-icon"><i class="fas fa-sort" style="opacity: 0.3;"></i></span></th>
                <th onclick="sortTable(4)" style="cursor:pointer;">Carga Horária <span class="sort-icon"><i class="fas fa-sort" style="opacity: 0.3;"></i></span></th>
                <th onclick="sortTable(5)" style="cursor:pointer;">Semestral <span class="sort-icon"><i class="fas fa-sort" style="opacity: 0.3;"></i></span></th>
                <?php if (can_edit()): ?>
                    <th>Ações</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($cursos)): ?>
                <tr class="empty-row">
                    <td colspan="7" class="text-center">Nenhum curso cadastrado.</td>
                </tr>
            <?php else: ?>
                <?php $idx = 1;
                foreach ($cursos as $c): ?>
                    <tr class="matches-filter">
                        <td style="color: var(--text-muted); font-size: 0.8rem;"><?= $idx++ ?></td>
                        <td style="font-weight: 700;"><?= htmlspecialchars($c['nome']) ?></td>
                        <td><?= htmlspecialchars($c['tipo']) ?></td>
                        <td><?= htmlspecialchars($c['area']) ?></td>
                        <td><?= $c['carga_horaria_total'] ?>h</td>
                        <td><?= $c['semestral'] ? 'Sim' : 'Não' ?></td>
                        <?php if (can_edit()): ?>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    <a href="cursos_form.php?id=<?= $c['id'] ?>" class="btn btn-edit" title="Editar"><i class="fas fa-edit"></i></a>
                                    <a href="../controllers/cursos_process.php?action=delete&id=<?= $c['id'] ?>"
                                        class="btn btn-delete" title="Excluir"
                                        onclick="return confirm('Tem certeza que deseja excluir este curso?')"><i
                                            class="fas fa-trash"></i></a>
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

<?php include __DIR__ . '/../components/footer.php'; ?>