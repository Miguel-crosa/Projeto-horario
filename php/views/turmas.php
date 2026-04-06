<?php
require_once __DIR__ . '/../configs/db.php';
include __DIR__ . '/../components/header.php';

// Migrated to lowercase table names for Linux compatibility
$query = "SELECT t.*, c.nome as curso_nome,
          d1.nome as docente1_nome, d2.nome as docente2_nome, 
          d3.nome as docente3_nome, d4.nome as docente4_nome
          FROM turma t 
          JOIN curso c ON t.curso_id = c.id 
          LEFT JOIN docente d1 ON t.docente_id1 = d1.id
          LEFT JOIN docente d2 ON t.docente_id2 = d2.id
          LEFT JOIN docente d3 ON t.docente_id3 = d3.id
          LEFT JOIN docente d4 ON t.docente_id4 = d4.id
          ORDER BY t.data_inicio DESC";
$turmas = mysqli_fetch_all(mysqli_query($conn, $query), MYSQLI_ASSOC);
?>

<div class="page-header">
    <h2>Gestão de Turmas</h2>
    <div class="header-actions" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <div class="search-box">
            <input type="text" id="filter-sigla" placeholder="Filtrar por Sigla/Curso..." class="form-input"
                style="width: 250px;" onkeyup="filterTurmas()">
        </div>
        <div class="search-box">
            <input type="text" id="filter-docente" placeholder="Buscar por Docente..." class="form-input"
                style="width: 220px;" onkeyup="filterTurmas()">
        </div>
        <select id="filter-periodo" class="form-input" style="width: 150px;" onchange="filterTurmas()">
            <option value="">Todos os Períodos</option>
            <option value="Manhã">Manhã</option>
            <option value="Tarde">Tarde</option>
            <option value="Noite">Noite</option>
            <option value="Integral">Integral</option>
        </select>
        <a href="turmas_form.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nova Turma</a>
        <a href="fix_turmas_loading.php" class="btn btn-warning"><i class="fas fa-magic"></i> Ajustar horários incorretos</a>
    </div>
</div>
<script>
    let currentPage = 1;
    const itemsPerPage = 20;
    let currentSort = { column: null, direction: 'asc' };

    function filterTurmas() {
        const siglaInput = document.getElementById('filter-sigla');
        const sigla = siglaInput ? siglaInput.value.toLowerCase() : '';
        const periodoInput = document.getElementById('filter-periodo');
        const periodo = periodoInput ? periodoInput.value : '';
        const docenteInput = document.getElementById('filter-docente');
        const docenteFilter = docenteInput ? docenteInput.value.toLowerCase().trim() : '';
        const rows = Array.from(document.querySelectorAll('#turmas-table tbody tr:not(.empty-row)'));

        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            const pCell = row.cells[3].innerText.trim();
            // Coluna oculta com nomes dos docentes (última coluna de dados, antes de ações)
            const docentesCell = row.dataset.docentes || '';

            const matchesSigla = text.includes(sigla);
            const matchesPeriodo = !periodo || pCell === periodo;
            const matchesDocente = !docenteFilter || docentesCell.toLowerCase().includes(docenteFilter);

            if (matchesSigla && matchesPeriodo && matchesDocente) {
                row.classList.add('matches-filter');
            } else {
                row.classList.remove('matches-filter');
            }
        });

        applySortAndPaginate();
    }

    function sortTable(columnIndex) {
        const table = document.querySelector('#turmas-table table');
        const headers = table.querySelectorAll('th');

        // Update direction
        if (currentSort.column === columnIndex) {
            currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
        } else {
            currentSort.column = columnIndex;
            currentSort.direction = 'asc';
        }

        // Update UI Indicators
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
        const tbody = document.querySelector('#turmas-table tbody');
        const rows = Array.from(tbody.querySelectorAll('tr.matches-filter'));

        if (currentSort.column !== null) {
            rows.sort((a, b) => {
                let valA = a.cells[currentSort.column].innerText.trim();
                let valB = b.cells[currentSort.column].innerText.trim();

                const isEmptyA = !valA || valA === '-';
                const isEmptyB = !valB || valB === '-';

                if (isEmptyA && !isEmptyB) return 1;
                if (!isEmptyA && isEmptyB) return -1;
                if (isEmptyA && isEmptyB) return 0;

                // Sort logic based on column index
                if ([6, 7].includes(currentSort.column)) { // Início (6), Fim (7)
                    const parseDate = (d) => {
                        const parts = d.split('/');
                        return new Date(parts[2], parts[1] - 1, parts[0]);
                    };
                    valA = parseDate(valA);
                    valB = parseDate(valB);
                } else if (currentSort.column === 8) { // Vagas
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

            // Re-order in DOM
            rows.forEach(row => tbody.appendChild(row));
        }

        updatePagination();
    }

    function updatePagination() {
        const rows = Array.from(document.querySelectorAll('#turmas-table tbody tr.matches-filter'));
        const allRows = Array.from(document.querySelectorAll('#turmas-table tbody tr:not(.empty-row)'));
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
        const rows = document.querySelectorAll('#turmas-table tbody tr:not(.empty-row)');
        rows.forEach(r => r.classList.add('matches-filter'));
        updatePagination();
    });
</script>

<div class="table-container" id="turmas-table">
    <table>
        <thead>
            <tr>
                <th style="width: 50px;">#</th>
                <th onclick="sortTable(1)" style="cursor:pointer;">Sigla <span class="sort-icon"><i class="fas fa-sort"
                            style="opacity: 0.3;"></i></span></th>
                <th onclick="sortTable(2)" style="cursor:pointer;">Curso <span class="sort-icon"><i class="fas fa-sort"
                            style="opacity: 0.3;"></i></span></th>
                <th onclick="sortTable(3)" style="cursor:pointer;">Período <span class="sort-icon"><i
                            class="fas fa-sort" style="opacity: 0.3;"></i></span></th>
                <th>Horário</th>
                <th onclick="sortTable(5)" style="cursor:pointer;">Docente(s) <span class="sort-icon"><i class="fas fa-sort"
                            style="opacity: 0.3;"></i></span></th>
                <th onclick="sortTable(6)" style="cursor:pointer;">Início <span class="sort-icon"><i class="fas fa-sort"
                            style="opacity: 0.3;"></i></span></th>
                <th onclick="sortTable(7)" style="cursor:pointer;">Fim <span class="sort-icon"><i class="fas fa-sort"
                            style="opacity: 0.3;"></i></span></th>
                <th onclick="sortTable(8)" style="cursor:pointer;">Vagas <span class="sort-icon"><i class="fas fa-sort"
                            style="opacity: 0.3;"></i></span></th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($turmas)): ?>
                <tr class="empty-row">
                    <td colspan="10" class="text-center">Nenhuma turma cadastrada.</td>
                </tr>
            <?php else: ?>
                <?php $idx = 1;
                foreach ($turmas as $t):
                    // Monta lista de docentes para busca
                    $docentes_list = array_filter([
                        $t['docente1_nome'] ?? '',
                        $t['docente2_nome'] ?? '',
                        $t['docente3_nome'] ?? '',
                        $t['docente4_nome'] ?? ''
                    ]);
                    $docentes_str = implode(', ', $docentes_list);
                    $docentes_search = implode(' ', $docentes_list);
                ?>
                    <tr class="matches-filter" data-docentes="<?= htmlspecialchars($docentes_search) ?>">
                        <td style="color: var(--text-muted); font-size: 0.8rem;"><?= $idx++ ?></td>
                        <td><strong><?= htmlspecialchars($t['sigla']) ?></strong></td>
                        <td><?= htmlspecialchars($t['curso_nome']) ?></td>
                        <td><?= htmlspecialchars($t['periodo']) ?></td>
                        <td>
                            <?php
                            $h_ini = !empty($t['horario_inicio']) ? substr($t['horario_inicio'], 0, 5) : '--:--';
                            $h_fim = !empty($t['horario_fim']) ? substr($t['horario_fim'], 0, 5) : '--:--';
                            echo "$h_ini - $h_fim";
                            ?>
                        </td>
                        <td style="max-width: 200px;">
                            <?php if (!empty($docentes_list)): ?>
                                <?php foreach ($docentes_list as $dn): ?>
                                    <span style="display: inline-block; background: rgba(229,57,53,0.08); color: var(--text-color); padding: 2px 8px; border-radius: 6px; font-size: 0.78rem; font-weight: 600; margin: 1px 2px; border: 1px solid rgba(229,57,53,0.15);">
                                        <i class="fas fa-user" style="font-size: 0.65rem; opacity: 0.6; margin-right: 3px;"></i><?= htmlspecialchars($dn) ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span style="color: var(--text-muted); font-size: 0.8rem;">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?= !empty($t['data_inicio']) ? date('d/m/Y', strtotime($t['data_inicio'])) : '-' ?></td>
                        <td><?= !empty($t['data_fim']) ? date('d/m/Y', strtotime($t['data_fim'])) : '-' ?></td>
                        <td><?= $t['vagas'] ?></td>
                        <td class="actions-cell">
                            <a href="turmas_form.php?id=<?= $t['id'] ?>" class="btn btn-edit" title="Editar"><i
                                    class="fas fa-edit"></i></a>
                            <a href="../controllers/turmas_process.php?action=delete&id=<?= $t['id'] ?>" class="btn btn-delete"
                                title="Excluir"
                                onclick="return confirm('Tem certeza que deseja excluir esta turma e toda a agenda vinculada?')">
                                <i class="fas fa-trash"></i>
                            </a>
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

<style>
    .header-actions {
        display: flex;
        gap: 10px;
    }

    .actions-cell {
        display: flex;
        gap: 5px;
    }
</style>

<?php include __DIR__ . '/../components/footer.php'; ?>