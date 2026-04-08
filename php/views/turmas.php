<?php
require_once __DIR__ . '/../configs/db.php';
include __DIR__ . '/../components/header.php';

$view = isset($_GET['view']) ? $_GET['view'] : 'active';
$is_archived_view = ($view === 'archived');

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
          WHERE t.ativo = " . ($is_archived_view ? '0' : '1') . "
          ORDER BY t." . ($is_archived_view ? 'id' : 'data_inicio') . " DESC";
$turmas = mysqli_fetch_all(mysqli_query($conn, $query), MYSQLI_ASSOC);
?>

<div class="page-header"
    style="flex-direction: column; align-items: flex-start; gap: 15px; padding: 25px; background: var(--card-bg); border-radius: 12px; border: 1px solid var(--border-color); margin-bottom: 25px;">
    <div style="display: flex; justify-content: space-between; width: 100%; align-items: center;">
        <h2 style="margin: 0; font-size: 1.8rem; font-weight: 800; color: var(--text-color);">Gestão de Turmas</h2>
        <div style="display: flex; gap: 10px;">
            <a href="fix_turmas_loading.php" class="btn btn-warning"
                style="background: rgba(255,179,0,0.1); border-color: rgba(255,179,0,0.2); color: #ffb300; font-size: 0.8rem; height: 36px; display: flex; align-items: center; text-decoration: none; padding: 0 12px; border-radius: 6px;">
                <i class="fas fa-magic" style="margin-right: 6px;"></i> Ajustar horários
            </a>
            <?php if ($is_archived_view): ?>
                <a href="turmas.php" class="btn btn-secondary" style="background: var(--primary-green); color: white;"><i
                        class="fas fa-check-circle"></i> Ver Ativas</a>
            <?php else: ?>
                <a href="turmas.php?view=archived" class="btn btn-secondary"><i class="fas fa-archive"></i> Ver
                    Arquivadas</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="header-actions"
        style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap; width: 100%; padding-top: 15px; border-top: 1px solid var(--border-color);">
        <div class="search-box" style="flex: 1; min-width: 200px;">
            <i class="fas fa-search"
                style="position: absolute; margin-left: 12px; color: var(--text-muted); pointer-events: none;"></i>
            <input type="text" id="filter-sigla" placeholder="Filtrar por Sigla ou Curso..." class="form-input"
                style="padding-left: 35px; width: 100%;" onkeyup="filterTurmas()">
        </div>
        <div class="search-box" style="flex: 1; min-width: 180px;">
            <i class="fas fa-user-tie"
                style="position: absolute; margin-left: 12px; color: var(--text-muted); pointer-events: none;"></i>
            <input type="text" id="filter-docente" placeholder="Filtrar por Docente..." class="form-input"
                style="padding-left: 35px; width: 100%;" onkeyup="filterTurmas()">
        </div>
        <select id="filter-periodo" class="form-input" style="width: 160px;" onchange="filterTurmas()">
            <option value="">Todos Períodos</option>
            <option value="Manhã">Manhã</option>
            <option value="Tarde">Tarde</option>
            <option value="Noite">Noite</option>
            <option value="Integral">Integral</option>
        </select>
        <div style="display: flex; gap: 8px;">
            <button type="button" onclick="openGlobalReserva()" class="btn btn-warning"
                style="background: #ffb300; border-color: #ffa000; color: #5d4037; font-weight: 700;">
                <i class="fas fa-bookmark"></i> RESERVA
            </button>
            <a href="turmas_form.php" class="btn btn-primary"
                style="font-weight: 700; background: var(--primary-red); border-color: var(--primary-red);">
                <i class="fas fa-plus"></i> NOVA TURMA
            </a>
        </div>
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
                <th onclick="sortTable(5)" style="cursor:pointer;">Docente(s) <span class="sort-icon"><i
                            class="fas fa-sort" style="opacity: 0.3;"></i></span></th>
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
                        <td>
                            <strong><?= htmlspecialchars($t['sigla']) ?></strong>
                            <?php if ($is_archived_view): ?>
                                <span
                                    style="display: block; font-size: 0.65rem; color: #d32f2f; font-weight: 700; text-transform: uppercase; margin-top: 4px;">
                                    <i class="fas fa-archive"></i> Arquivada
                                </span>
                            <?php endif; ?>
                        </td>
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
                                    <span
                                        style="display: inline-block; background: rgba(229,57,53,0.08); color: var(--text-color); padding: 2px 8px; border-radius: 6px; font-size: 0.78rem; font-weight: 600; margin: 1px 2px; border: 1px solid rgba(229,57,53,0.15);">
                                        <i class="fas fa-user"
                                            style="font-size: 0.65rem; opacity: 0.6; margin-right: 3px;"></i><?= htmlspecialchars($dn) ?>
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
                            <?php if ($is_archived_view): ?>
                                <a href="../controllers/turmas_process.php?action=activate&id=<?= $t['id'] ?>" class="btn btn-edit"
                                    title="Reativar Turma"
                                    style="background: var(--primary-green); border-color: var(--primary-green);"
                                    onclick="return confirm('Deseja restaurar esta turma para o status ativo?')">
                                    <i class="fas fa-undo"></i>
                                </a>
                                <button type="button" class="btn btn-delete" title="Excluir Permanentemente"
                                    data-id="<?= $t['id'] ?>" data-sigla="<?= htmlspecialchars($t['sigla']) ?>"
                                    data-fim="<?= $t['data_fim'] ?>" onclick="handleDeleteTurma(this)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            <?php else: ?>
                                <a href="turmas_form.php?id=<?= $t['id'] ?>" class="btn btn-edit" title="Editar"><i
                                        class="fas fa-edit"></i></a>
                                <button type="button" class="btn btn-delete" title="Excluir" data-id="<?= $t['id'] ?>"
                                    data-sigla="<?= htmlspecialchars($t['sigla']) ?>" data-fim="<?= $t['data_fim'] ?>"
                                    onclick="handleDeleteTurma(this)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            <?php endif; ?>
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
        gap: 8px;
        justify-content: center;
        align-items: center;
        height: 100%;
        min-height: 45px;
    }

    #turmas-table table {
        border-collapse: separate;
        border-spacing: 0 4px;
    }

    #turmas-table tbody tr {
        background: var(--card-bg);
        transition: transform 0.2s;
    }

    #turmas-table tbody tr:hover {
        background: var(--bg-hover) !important;
        transform: scale(1.002);
    }

    #turmas-table td,
    #turmas-table th {
        border: none;
        border-bottom: 1px solid var(--border-color);
        padding: 12px 10px;
    }
</style>

<script>
    // Dependências para o calendar.js / formulário unificado funcionar independentemente
    window.userIsAdmin = <?= (isAdmin() || isGestor()) ? 'true' : 'false' ?>;
    window.userIsCRI = <?= isCRI() ? 'true' : 'false' ?>;

    function openGlobalReserva() {
        const today = new Date().toISOString().split('T')[0];

        // Se window.openCalendarScheduleModal não estiver disponível, esperamos um pouco (carregamento async)
        if (typeof window.openCalendarScheduleModal === 'function') {
            window.openCalendarScheduleModal(today, today, true);
        } else {
            console.error('Script de agendamento ainda não carregado.');
            showNotification('O sistema de agendamento ainda está carregando. Tente novamente em 2 segundos.', 'error');
        }
    }

    // Lógica de exclusão condicional
    function handleDeleteTurma(btn) {
        const id = btn.dataset.id;
        const sigla = btn.dataset.sigla;
        const dataFim = btn.dataset.fim;

        if (!dataFim) {
            // Fallback se não tiver data (não deve acontecer)
            if (confirm(`Tem certeza que deseja excluir a turma ${sigla}?`)) {
                window.location.href = `../controllers/turmas_process.php?action=delete&id=${id}`;
            }
            return;
        }

        const hoje = new Date();
        hoje.setHours(0, 0, 0, 0);

        // Ajuste para considerar o timezone local ao criar objeto Date da string ISO
        const [ano, mes, dia] = dataFim.split('-').map(Number);
        const fimDate = new Date(ano, mes - 1, dia);

        if (fimDate < hoje) {
            // Soft Delete: Turma encerrada
            if (confirm(`Esta turma (${sigla}) já foi encerrada. Ela será DESATIVADA/ARQUIVADA, mantendo o histórico de agenda. Deseja continuar?`)) {
                window.location.href = `../controllers/turmas_process.php?action=delete&id=${id}&mode=soft`;
            }
        } else {
            // Hard Delete: Turma vigente ou futura (GitHub Style)
            currentDeleteId = id;
            expectedSigla = sigla;

            const expectedEl = document.getElementById('delete-expected-sigla');
            const inputEl = document.getElementById('delete-confirm-input');
            const confirmBtn = document.getElementById('btn-confirm-delete-permanent');

            if (expectedEl) expectedEl.innerText = sigla;
            if (inputEl) inputEl.value = '';
            if (confirmBtn) {
                confirmBtn.disabled = true;
                confirmBtn.style.opacity = "0.5";
            }

            openModal('modal-confirm-hard-delete');
        }
    }
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>