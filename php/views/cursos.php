<?php
require_once __DIR__ . '/../configs/db.php';
include __DIR__ . '/../components/header.php';

$cursos = mysqli_fetch_all(mysqli_query($conn, "SELECT * FROM curso ORDER BY nome ASC"), MYSQLI_ASSOC);
?>

<div class="page-header">
    <h2>Gestão de Cursos</h2>
    <div class="header-actions" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <div class="search-box">
            <input type="text" id="filter-nome" placeholder="Filtrar por nome do curso..." class="form-input"
                style="width: 300px;" onkeyup="filterCursos()">
        </div>
        <a href="cursos_form.php" class="btn btn-primary"><i class="fas fa-plus"></i> Novo Curso</a>
    </div>
</div>

<script>
    let currentPage = 1;
    const itemsPerPage = 20;
    let currentSort = { column: null, direction: 'asc' };

    function filterCursos() {
        const queryInput = document.getElementById('filter-nome');
        const query = queryInput ? queryInput.value.toLowerCase() : '';
        const rows = Array.from(document.querySelectorAll('#cursos-table tbody tr:not(.empty-row)'));
        
        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            if (text.includes(query)) {
                row.classList.add('matches-filter');
            } else {
                row.classList.remove('matches-filter');
            }
        });

        applySortAndPaginate();
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
        if (infoEl) infoEl.innerText = `Página ${currentPage} de ${totalPages || 1}`;
        
        const prevBtn = document.getElementById('prev-page');
        const nextBtn = document.getElementById('next-page');
        if (prevBtn) prevBtn.disabled = currentPage === 1;
        if (nextBtn) nextBtn.disabled = currentPage === totalPages || totalPages === 0;
    }

    function changePage(delta) {
        currentPage += delta;
        updatePagination();
    }

    window.addEventListener('load', () => {
        const rows = document.querySelectorAll('#cursos-table tbody tr:not(.empty-row)');
        rows.forEach(r => r.classList.add('matches-filter'));
        updatePagination();
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
                <th>Ações</th>
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
                        <td>
                            <div style="display: flex; gap: 5px;">
                                <a href="cursos_form.php?id=<?= $c['id'] ?>" class="btn btn-edit" title="Editar"><i class="fas fa-edit"></i></a>
                                <a href="../controllers/cursos_process.php?action=delete&id=<?= $c['id'] ?>"
                                    class="btn btn-delete" title="Excluir"
                                    onclick="return confirm('Tem certeza que deseja excluir este curso?')"><i
                                        class="fas fa-trash"></i></a>
                            </div>
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

<?php include __DIR__ . '/../components/footer.php'; ?>