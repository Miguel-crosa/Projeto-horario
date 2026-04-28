<?php
require_once __DIR__ . '/../configs/db.php';
include __DIR__ . '/../components/header.php';

$view = isset($_GET['view']) ? $_GET['view'] : 'active';
$is_archived_view = ($view === 'archived');

// Migrated to lowercase table names for Linux compatibility
$query = "SELECT t.*, c.nome as curso_nome, c.carga_horaria_total,
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

<div class="page-header">
    <h2>Gestão de Turmas</h2>
</div>

<div class="filter-bar" style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center; justify-content: flex-end;">
    <div class="search-box" style="flex: 1; max-width: 300px;">
        <input type="text" id="filter-sigla" placeholder="Filtrar por Sigla ou Curso..." class="form-input"
            style="width: 100%;" onkeyup="filterTurmas()" onkeydown="if(event.key==='Enter') event.preventDefault();">
    </div>
    <input type="text" id="filter-docente" placeholder="Filtrar por Docente..." class="form-input" style="width: 180px;"
        onkeyup="filterTurmas()" onkeydown="if(event.key==='Enter') event.preventDefault();">
    <select id="filter-periodo" class="form-input" style="width: 140px;" onchange="filterTurmas()">
        <option value="">Todos Períodos</option>
        <option value="Manhã">Manhã</option>
        <option value="Tarde">Tarde</option>
        <option value="Noite">Noite</option>
        <option value="Integral">Integral</option>
    </select>
    <select id="filter-dia" class="form-input" style="width: 130px;" onchange="filterTurmas()">
        <option value="">Todos Dias</option>
        <option value="Segunda-feira">Segunda</option>
        <option value="Terça-feira">Terça</option>
        <option value="Quarta-feira">Quarta</option>
        <option value="Quinta-feira">Quinta</option>
        <option value="Sexta-feira">Sexta</option>
        <option value="Sábado">Sábado</option>
    </select>
    <select id="filter-sort" class="form-input" style="width: 160px;" onchange="applyQuickSort()">
        <option value="">Ordenar por...</option>
        <option value="ch_desc">Maior Carga Horária</option>
        <option value="ch_asc">Menor Carga Horária</option>
        <option value="data_desc">Data Início (Novas)</option>
        <option value="data_asc">Data Início (Antigas)</option>
    </select>
    <div class="header-actions" style="display: flex; gap: 8px;">
        <?php if (can_reserve()): ?>
            <button type="button" onclick="openGlobalReserva()" class="btn btn-warning"
                style="background: #ffb300; border: none; color: #5d4037; font-weight: 700; height: 38px;">
                <i class="fas fa-bookmark"></i> RESERVA
            </button>
        <?php endif; ?>
        <?php if (isAdmin()): ?>
            <a href="fix_turmas_loading.php" class="btn" style="font-weight: 700; background: #00796b; color: #ffffff; border: none; height: 38px; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.2); transition: all 0.3s ease;">
                <i class="fas fa-magic"></i> AJUSTAR HORÁRIOS
            </a>
        <?php endif; ?>
        <?php if (can_edit()): ?>
            <a href="javascript:void(0)" onclick="goToNewTurma()" class="btn btn-primary" style="font-weight: 700;"><i class="fas fa-plus"></i> NOVA TURMA</a>
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

    function filterTurmas() {
        const siglaInput = document.getElementById('filter-sigla');
        const sigla = siglaInput ? siglaInput.value.toLowerCase() : '';
        const periodoInput = document.getElementById('filter-periodo');
        const periodo = periodoInput ? periodoInput.value : '';
        const docenteInput = document.getElementById('filter-docente');
        const docenteFilter = docenteInput ? docenteInput.value.toLowerCase().trim() : '';
        const diaInput = document.getElementById('filter-dia');
        const dia = diaInput ? diaInput.value : '';
        const rows = Array.from(document.querySelectorAll('#turmas-table tbody tr:not(.empty-row)'));

        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            const pCell = row.cells[6].innerText.trim();
            // Coluna oculta com nomes dos docentes (última coluna de dados, antes de ações)
            const docentesCell = row.dataset.docentes || '';
            const diasTurma = row.dataset.dias || '';

            const matchesSigla = !sigla || text.includes(sigla);
            const matchesPeriodo = !periodo || pCell === periodo;
            const matchesDocente = !docenteFilter || docentesCell.toLowerCase().includes(docenteFilter);
            const matchesDia = !dia || diasTurma.includes(dia);

            if (matchesSigla && matchesPeriodo && matchesDocente && matchesDia) {
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

        const filters = [
            { id: 'filter-sigla', label: 'Busca', icon: 'fa-search' },
            { id: 'filter-docente', label: 'Professor', icon: 'fa-user-tie' },
            { id: 'filter-periodo', label: 'Período', icon: 'fa-clock' },
            { id: 'filter-dia', label: 'Dia', icon: 'fa-calendar-day' }
        ];

        filters.forEach(f => {
            const el = document.getElementById(f.id);
            if (el && el.value) {
                let valText = el.value;
                if (el.tagName === 'SELECT') {
                    valText = el.options[el.selectedIndex].text;
                }
                
                const chip = document.createElement('div');
                chip.className = 'filter-chip animate-fade-in';
                chip.innerHTML = `
                    <i class="fas ${f.icon}"></i>
                    <span><strong>${f.label}:</strong> ${valText}</span>
                    <i class="fas fa-times-circle remove-chip" onclick="clearSpecificFilter('${f.id}')"></i>
                `;
                container.appendChild(chip);
            }
        });

        if (container.children.length > 0) {
            const clearAll = document.createElement('div');
            clearAll.className = 'filter-chip';
            clearAll.style.cursor = 'pointer';
            clearAll.style.background = 'rgba(237, 28, 36, 0.1)';
            clearAll.style.borderColor = 'var(--primary-red)';
            clearAll.style.color = 'var(--primary-red)';
            clearAll.innerHTML = `<span>Limpar Tudo</span>`;
            clearAll.onclick = () => {
                filters.forEach(f => document.getElementById(f.id).value = '');
                filterTurmas();
            };
            container.appendChild(clearAll);
        }
    }

    function clearSpecificFilter(id) {
        const el = document.getElementById(id);
        if (el) {
            el.value = '';
            filterTurmas();
        }
    }

    function applyQuickSort() {
        const sortVal = document.getElementById('filter-sort').value;
        if (!sortVal) return;

        if (sortVal === 'ch_desc') {
            currentSort = { column: 3, direction: 'desc' };
        } else if (sortVal === 'ch_asc') {
            currentSort = { column: 3, direction: 'asc' };
        } else if (sortVal === 'data_desc') {
            currentSort = { column: 8, direction: 'desc' };
        } else if (sortVal === 'data_asc') {
            currentSort = { column: 8, direction: 'asc' };
        }
        
        updateSortUI();
        applySortAndPaginate();
    }

    function updateSortUI() {
        const headers = document.querySelectorAll('#turmas-table th');
        headers.forEach((th, idx) => {
            const icon = th.querySelector('.sort-icon');
            if (icon) {
                if (idx === currentSort.column) {
                    icon.innerHTML = currentSort.direction === 'asc' ? ' <i class="fas fa-sort-up"></i>' : ' <i class="fas fa-sort-down"></i>';
                    th.classList.add('active-sort');
                } else {
                    icon.innerHTML = ' <i class="fas fa-sort" style="opacity: 0.3;"></i>';
                    th.classList.remove('active-sort');
                }
            }
        });
    }

    function sortTable(columnIndex) {
        const table = document.querySelector('#turmas-table table');

        // Update direction
        if (currentSort.column === columnIndex) {
            currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
        } else {
            currentSort.column = columnIndex;
            currentSort.direction = 'asc';
        }

        // Update UI Indicators
        updateSortUI();

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
                if ([10, 11].includes(currentSort.column)) { // Início (10), Fim (11)
                    const parseDate = (d) => {
                        const parts = d.split('/');
                        return new Date(parts[2], parts[1] - 1, parts[0]);
                    };
                    valA = parseDate(valA);
                    valB = parseDate(valB);
                } else if (currentSort.column === 5 || currentSort.column === 12) { // C/H (5) ou Vagas (12)
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

        // Atualiza Texto de Info
        const infoEl = document.getElementById('page-info');
        if (infoEl) {
            const currentTotal = rows.length;
            const shownStart = currentTotal > 0 ? start + 1 : 0;
            const shownEnd = Math.min(end, currentTotal);
            infoEl.innerHTML = `Exibindo <strong>${shownStart}-${shownEnd}</strong> de <strong>${currentTotal}</strong> turmas`;
        }

        // Renderiza Lista de Páginas Premium
        const listEl = document.getElementById('pagination-list');
        if (listEl) {
            listEl.innerHTML = '';
            
            // Botão Anterior
            const prevLi = document.createElement('li');
            prevLi.className = `page-item nav-btn ${currentPage === 1 ? 'disabled' : ''}`;
            prevLi.innerHTML = `<i class="fas fa-chevron-left"></i> Anterior`;
            if (currentPage > 1) prevLi.onclick = () => changePage(-1);
            listEl.appendChild(prevLi);

            // Números de Página
            const maxVisible = 5;
            let startPage = Math.max(1, currentPage - 2);
            let endPage = Math.min(totalPages, startPage + maxVisible - 1);
            
            if (endPage - startPage < maxVisible - 1) {
                startPage = Math.max(1, endPage - maxVisible + 1);
            }

            for (let i = startPage; i <= endPage; i++) {
                const li = document.createElement('li');
                li.className = `page-item ${i === currentPage ? 'active' : ''}`;
                li.innerText = i;
                li.onclick = () => {
                    currentPage = i;
                    updatePagination();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                };
                listEl.appendChild(li);
            }

            // Botão Próximo
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

    // Gestão de Seleção e Barra Flutuante
    function handleRowSelect(checkbox) {
        const row = checkbox.closest('tr');
        if (checkbox.checked) {
            row.classList.add('row-selected');
        } else {
            row.classList.remove('row-selected');
        }
        updateBulkButton();
    }

    function updateBulkButton() {
        const selected = document.querySelectorAll('.turma-checkbox:checked');
        const count = selected.length;
        
        // Botão clássico (topo)
        const btnBulk = document.getElementById('btn-bulk-edit');
        if (btnBulk) {
            btnBulk.style.display = count > 0 ? 'inline-flex' : 'none';
            const countSpan = document.getElementById('bulk-count');
            if (countSpan) countSpan.innerText = count;
        }

        // Barra Flutuante
        const floatingBar = document.getElementById('floating-bar');
        const floatingCount = document.getElementById('floating-count');
        if (floatingBar && floatingCount) {
            if (count > 0) {
                floatingBar.classList.add('active');
                floatingCount.innerText = count;
            } else {
                floatingBar.classList.remove('active');
            }
        }
    }

    function toggleSelectAll(master) {
        const checkboxes = document.querySelectorAll('.turma-checkbox');
        checkboxes.forEach(cb => {
            const row = cb.closest('tr');
            if (row.style.display !== 'none') {
                cb.checked = master.checked;
                handleRowSelect(cb);
            }
        });
    }

    function clearSelection() {
        document.querySelectorAll('.turma-checkbox').forEach(cb => {
            cb.checked = false;
            handleRowSelect(cb);
        });
        const selectAll = document.getElementById('selectAll');
        if (selectAll) selectAll.checked = false;
    }

    function deleteSelectedTurmas() {
        const selected = Array.from(document.querySelectorAll('.turma-checkbox:checked')).map(cb => cb.value);
        if (selected.length === 0) return;

        Swal.fire({
            title: 'Excluir Turmas em Lote?',
            html: `Você selecionou <strong>${selected.length} turmas</strong> para exclusão definitiva.<br><br>Para confirmar, digite <strong>EXCLUIR</strong> abaixo:`,
            icon: 'warning',
            input: 'text',
            inputAttributes: {
                autocapitalize: 'off',
                placeholder: 'EXCLUIR'
            },
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sim, excluir tudo',
            cancelButtonText: 'Cancelar',
            preConfirm: (value) => {
                if (value !== 'EXCLUIR') {
                    Swal.showValidationMessage('Você deve digitar EXCLUIR para confirmar');
                }
                return value === 'EXCLUIR';
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '../controllers/turmas_process.php';
                
                const actInput = document.createElement('input');
                actInput.type = 'hidden';
                actInput.name = 'action';
                actInput.value = 'delete_bulk';
                form.appendChild(actInput);

                const urlInput = document.createElement('input');
                urlInput.type = 'hidden';
                urlInput.name = 'return_url';
                urlInput.value = 'turmas.php?' + getCurrentFilterParams();
                form.appendChild(urlInput);

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
        });
    }

    // Atalhos de Teclado
    document.addEventListener('keydown', (e) => {
        // Ctrl + K (Busca)
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            const searchInput = document.getElementById('filter-sigla');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }

        // Esc (Limpar Filtros e Seleção)
        if (e.key === 'Escape') {
            const searchInput = document.getElementById('filter-sigla');
            const profInput = document.getElementById('filter-docente');
            if (searchInput) searchInput.value = '';
            if (profInput) profInput.value = '';
            document.getElementById('filter-periodo').value = '';
            document.getElementById('filter-dia').value = '';
            filterTurmas();
            clearSelection();
        }
    });


    window.addEventListener('load', () => {
        // Inicializa filtros da URL
        const urlParams = new URLSearchParams(window.location.search);
        
        const siglaParam = urlParams.get('sigla');
        if (siglaParam) document.getElementById('filter-sigla').value = siglaParam;

        const docenteParam = urlParams.get('docente');
        if (docenteParam) document.getElementById('filter-docente').value = docenteParam;

        const periodoParam = urlParams.get('periodo');
        if (periodoParam) document.getElementById('filter-periodo').value = periodoParam;

        const diaParam = urlParams.get('dia');
        if (diaParam) document.getElementById('filter-dia').value = diaParam;

        const sortParam = urlParams.get('sort');
        if (sortParam) {
            document.getElementById('filter-sort').value = sortParam;
            applyQuickSort();
        }

        // Recupera ordenação por coluna clicada
        const sortColParam = urlParams.get('sort_col');
        const sortDirParam = urlParams.get('sort_dir');
        if (sortColParam !== null) {
            currentSort.column = parseInt(sortColParam);
            currentSort.direction = sortDirParam || 'asc';
            updateSortUI();
            applySortAndPaginate();
        }

        const rows = document.querySelectorAll('#turmas-table tbody tr:not(.empty-row)');
        rows.forEach(r => r.classList.add('matches-filter'));
        filterTurmas(); // Aplica filtros iniciais
        updateBulkButton(); // Garante estado inicial da barra flutuante

        // Mover barra flutuante para o final do body (Portal pattern)
        // Isso resolve problemas de position: fixed dentro de containers com transform/animation
        const floatingBar = document.getElementById('floating-bar');
        if (floatingBar) {
            document.body.appendChild(floatingBar);
        }

        const targetId = urlParams.get('id');
        if (targetId) {
            const targetRow = document.querySelector(`tr[data-id="${targetId}"]`);
            if (targetRow) {
                // Se a turma estiver em outra página, precisamos ir para ela
                const allVisibleRows = Array.from(document.querySelectorAll('#turmas-table tbody tr.matches-filter'));
                const rowIndex = allVisibleRows.indexOf(targetRow);
                if (rowIndex !== -1) {
                    currentPage = Math.ceil((rowIndex + 1) / itemsPerPage);
                    updatePagination();
                }

                // Destaque visual
                targetRow.style.backgroundColor = 'rgba(237, 28, 36, 0.1)';
                targetRow.style.transition = 'background-color 2s';
                targetRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                setTimeout(() => {
                    targetRow.style.backgroundColor = '';
                }, 3000);
            }
        }

        // --- NOVO: Exibição de mensagens (ex: Edição em Lote) ---
        const msg = urlParams.get('msg');
        const msgText = urlParams.get('msg_text');
        if (msg && msgText) {
            const isError = msg === 'error';
            const isBulk = msg === 'bulk_success';
            
            Swal.fire({
                title: isError ? 'Erro na Operação' : (isBulk ? 'Edição Concluída' : 'Sucesso!'),
                html: decodeURIComponent(msgText),
                icon: isError ? 'error' : 'success',
                confirmButtonColor: isError ? '#d32f2f' : '#2e7d32',
                confirmButtonText: 'Entendido'
            });

            // Limpa os parâmetros da URL para evitar repetir o alerta no refresh
            const cleanUrl = window.location.href.split('?')[0];
            const newSearch = new URLSearchParams(window.location.search);
            newSearch.delete('msg');
            newSearch.delete('msg_text');
            const finalUrl = cleanUrl + (newSearch.toString() ? '?' + newSearch.toString() : '');
            window.history.replaceState({}, document.title, finalUrl);
        }

    });


    function getCurrentFilterParams() {
        const params = new URLSearchParams();
        const sigla = document.getElementById('filter-sigla').value;
        const docente = document.getElementById('filter-docente').value;
        const periodo = document.getElementById('filter-periodo').value;
        const dia = document.getElementById('filter-dia').value;
        const sort = document.getElementById('filter-sort').value;

        if (sigla) params.set('sigla', sigla);
        if (docente) params.set('docente', docente);
        if (periodo) params.set('periodo', periodo);
        if (dia) params.set('dia', dia);
        if (sort) params.set('sort', sort);
        
        // Persiste a ordenação atual da tabela (clique na coluna)
        if (currentSort.column !== null) {
            params.set('sort_col', currentSort.column);
            params.set('sort_dir', currentSort.direction);
        }
        
        return params.toString();
    }

    function goToNewTurma() {
        const filters = getCurrentFilterParams();
        window.location.href = `turmas_form.php?return_url=${encodeURIComponent('../views/turmas.php?' + filters)}`;
    }

    function goToEditTurma(id) {
        const filters = getCurrentFilterParams();
        window.location.href = `turmas_form.php?id=${id}&return_url=${encodeURIComponent('../views/turmas.php?' + filters)}`;
    }

    function openBulkEditModal() {
        const checked = document.querySelectorAll('.turma-checkbox:checked');
        const ids = Array.from(checked).map(cb => cb.value);
        document.getElementById('bulk-ids').value = ids.join(',');
        openModal('modal-bulk-edit');
    }
</script>

<div class="table-container" id="turmas-table">
    <table>
        <thead>
            <tr>
                <th style="width: 40px; text-align: center;"><input type="checkbox" id="selectAll" onclick="toggleSelectAll(this)"></th>
                <th style="width: 40px;">#</th>
                <th onclick="sortTable(2)" style="cursor:pointer;">SIGLA <span class="sort-icon"><i class="fas fa-sort"
                            style="opacity: 0.3;"></i></span></th>
                <th>STATUS</th>
                <th onclick="sortTable(4)" style="cursor:pointer;">CURSO <span class="sort-icon"><i class="fas fa-sort"
                            style="opacity: 0.3;"></i></span></th>
                <th onclick="sortTable(5)" style="cursor:pointer;">C/H <span class="sort-icon"><i class="fas fa-sort"
                            style="opacity: 0.3;"></i></span></th>
                <th onclick="sortTable(6)" style="cursor:pointer;">PERÍODO <span class="sort-icon"><i
                            class="fas fa-sort" style="opacity: 0.3;"></i></span></th>
                <th>HORÁRIO</th>
                <th>DIAS</th>
                <th onclick="sortTable(9)" style="cursor:pointer;">DOCENTE(S) <span class="sort-icon"><i
                            class="fas fa-sort" style="opacity: 0.3;"></i></span></th>
                <th onclick="sortTable(10)" style="cursor:pointer;">INÍCIO <span class="sort-icon"><i class="fas fa-sort"
                            style="opacity: 0.3;"></i></span></th>
                <th onclick="sortTable(11)" style="cursor:pointer;">FIM <span class="sort-icon"><i class="fas fa-sort"
                            style="opacity: 0.3;"></i></span></th>
                <th onclick="sortTable(12)" style="cursor:pointer;">VAGAS <span class="sort-icon"><i class="fas fa-sort"
                            style="opacity: 0.3;"></i></span></th>
                <?php if (can_edit()): ?>
                    <th style="text-align: center;">AÇÕES</th>
                <?php endif; ?>
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
                    $dias_semana_raw = $t['dias_semana'] ?? '';
                    ?>
                    <tr class="matches-filter" data-id="<?= $t['id'] ?>" data-docentes="<?= xe($docentes_search) ?>" data-dias="<?= xe($dias_semana_raw) ?>">
                        <td style="text-align: center;"><input type="checkbox" class="row-checkbox turma-checkbox" value="<?= $t['id'] ?>" onchange="handleRowSelect(this)"></td>
                        <td style="color: var(--text-muted); font-size: 0.8rem;"><?= $idx++ ?></td>
                        <td>
                            <strong><?= xe($t['sigla']) ?></strong>
                        </td>
                        <td>
                            <?php
                            $hoje = date('Y-m-d');
                            $status_class = '';
                            $status_label = '';
                            $status_icon = '';

                            if ($t['data_fim'] < $hoje) {
                                $status_class = 'status-encerrada';
                                $status_label = 'Encerrada';
                                $status_icon = 'fa-check-circle';
                            } elseif ($t['data_inicio'] > $hoje) {
                                $status_class = 'status-futura';
                                $status_label = 'Futura';
                                $status_icon = 'fa-clock';
                            } else {
                                $status_class = 'status-andamento';
                                $status_label = 'Em Curso';
                                $status_icon = 'fa-play-circle';
                            }
                            ?>
                            <span class="status-badge <?= $status_class ?>" title="De <?= date('d/m/Y', strtotime($t['data_inicio'])) ?> até <?= date('d/m/Y', strtotime($t['data_fim'])) ?>">
                                <i class="fas <?= $status_icon ?>"></i> <?= $status_label ?>
                            </span>
                        </td>
                        <td><?= xe($t['curso_nome']) ?> <span style="font-size: 0.8rem; color: var(--text-muted); opacity: 0.8;">(<?= $t['carga_horaria_total'] ?>h)</span></td>
                        <td style="font-weight: 700; color: var(--primary-color);"><?= $t['carga_horaria_total'] ?>h</td>
                        <td><?= xe($t['periodo']) ?></td>
                        <td>
                            <?php
                            $h_ini = !empty($t['horario_inicio']) ? substr($t['horario_inicio'], 0, 5) : '--:--';
                            $h_fim = !empty($t['horario_fim']) ? substr($t['horario_fim'], 0, 5) : '--:--';
                            echo "$h_ini - $h_fim";
                            ?>
                        </td>
                        <td style="min-width: 100px;">
                            <?php
                            $dias_semana = !empty($t['dias_semana']) ? explode(',', $t['dias_semana']) : [];
                            $map_dias = [
                                'Segunda-feira' => 'SEG',
                                'Terça-feira' => 'TER',
                                'Quarta-feira' => 'QUA',
                                'Quinta-feira' => 'QUI',
                                'Sexta-feira' => 'SEX',
                                'Sábado' => 'SÁB',
                                'Domingo' => 'DOM'
                            ];
                            foreach ($dias_semana as $ds):
                                $ds_trim = trim($ds);
                                $label = $map_dias[$ds_trim] ?? $ds_trim;
                                echo "<span style='display: inline-block; background: rgba(0,0,0,0.05); color: var(--text-color); padding: 2px 5px; border-radius: 4px; font-size: 0.7rem; font-weight: 700; margin: 1px;'>$label</span>";
                            endforeach;
                            ?>
                        </td>
                        <td style="max-width: 250px;">
                            <div class="docente-list" style="display: flex; flex-wrap: wrap; gap: 4px;">
                                <?php if (!empty($docentes_list)): ?>
                                    <?php foreach ($docentes_list as $dn): ?>
                                        <span class="docente-badge docente-cell" 
                                              style="background: rgba(229,57,53,0.08); padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; border: 1px solid rgba(229,57,53,0.15); cursor: pointer; transition: all 0.2s;"
                                              title="Filtrar por: <?= xe($dn) ?>"
                                              onclick="document.getElementById('filter-docente').value='<?= xe($dn) ?>'; filterTurmas();">
                                            <i class="fas fa-user-tie" style="font-size: 0.7rem; opacity: 0.6; margin-right: 4px;"></i><?= xe($dn) ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 0.8rem;">—</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?= !empty($t['data_inicio']) ? date('d/m/Y', strtotime($t['data_inicio'])) : '-' ?></td>
                        <td><?= !empty($t['data_fim']) ? date('d/m/Y', strtotime($t['data_fim'])) : '-' ?></td>
                        <td style="text-align: center; font-weight: 700;"><?= $t['vagas'] ?></td>
                        <?php if (can_edit()): ?>
                            <td>
                                <div style="display: flex; gap: 5px; justify-content: center; align-items: center;">
                                    <?php if ($is_archived_view): ?>
                                        <a href="../controllers/turmas_process.php?action=activate&id=<?= $t['id'] ?>"
                                            class="btn btn-edit" title="Reativar Turma"
                                            style="background: var(--primary-green); border-color: var(--primary-green);"
                                            onclick="return confirm('Deseja restaurar esta turma para o status ativo?')">
                                            <i class="fas fa-undo"></i>
                                        </a>
                                        <button type="button" class="btn btn-delete" title="Excluir Permanentemente"
                                            data-id="<?= $t['id'] ?>" data-sigla="<?= xe($t['sigla']) ?>"
                                            data-fim="<?= $t['data_fim'] ?>" onclick="handleDeleteTurma(this)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php else: ?>
                                        <a href="javascript:void(0)" onclick="goToEditTurma(<?= $t['id'] ?>)" class="btn btn-edit" title="Editar"><i
                                                class="fas fa-edit"></i></a>
                                        <button type="button" class="btn btn-delete" title="Excluir" data-id="<?= $t['id'] ?>"
                                            data-sigla="<?= xe($t['sigla']) ?>" data-fim="<?= $t['data_fim'] ?>"
                                            onclick="handleDeleteTurma(this)">
                                            <i class="fas fa-trash"></i>
                                        </button>
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



<style>
    #turmas-table td {
        vertical-align: middle;
        padding: 12px 15px;
        transition: background-color 0.2s;
    }

    /* Badges de Status */
    .status-badge {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 800;
        text-transform: uppercase;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        letter-spacing: 0.5px;
        white-space: nowrap;
    }
    .status-andamento { background: rgba(76, 175, 80, 0.15); color: #4caf50; border: 1px solid rgba(76, 175, 80, 0.3); }
    .status-futura { background: rgba(33, 150, 243, 0.15); color: #2196f3; border: 1px solid rgba(33, 150, 243, 0.3); }
    .status-encerrada { background: rgba(158, 158, 158, 0.15); color: #9e9e9e; border: 1px solid rgba(158, 158, 158, 0.3); }

    /* Seleção de Linhas */
    .row-selected { background-color: rgba(106, 27, 154, 0.1) !important; }

    /* Barra Flutuante de Seleção */
    .floating-selection-bar {
        position: fixed;
        bottom: -100px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(30, 41, 59, 0.95);
        backdrop-filter: blur(15px);
        padding: 12px 30px;
        border-radius: 50px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.5);
        border: 1px solid rgba(255,255,255,0.1);
        display: flex;
        align-items: center;
        gap: 20px;
        z-index: 10002;
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .floating-selection-bar.active { 
        bottom: 30px;
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
    }
    .selection-count {
        color: #fff;
        font-weight: 800;
        padding-right: 20px;
        border-right: 1px solid rgba(255,255,255,0.1);
        font-size: 0.9rem;
    }
    .bar-actions { display: flex; gap: 10px; }
    .bar-btn {
        background: transparent;
        border: none;
        color: #fff;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        padding: 8px 15px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s;
    }
    .bar-btn:hover { background: rgba(255,255,255,0.1); transform: translateY(-2px); }
    .bar-btn.btn-delete:hover { color: #ff5252; background: rgba(255, 82, 82, 0.1); }

    /* Estilo do Modal de Edição em Lote */
    #modal-bulk-edit .modal-content {
        background: #1e293b;
        color: #fff;
        border: 1px solid rgba(255,255,255,0.1);
    }
    #modal-bulk-edit .modal-header {
        border-bottom: 1px solid rgba(255,255,255,0.1);
        padding: 20px 25px;
    }
    #modal-bulk-edit .close-modal {
        background: transparent !important;
        border: none !important;
        color: #fff !important;
        box-shadow: none !important;
        font-size: 1.5rem !important;
        width: auto !important;
        height: auto !important;
        line-height: 1;
        opacity: 0.7;
    }
    #modal-bulk-edit .close-modal:hover { opacity: 1; }
    #modal-bulk-edit .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        padding: 20px 25px;
        background: rgba(0,0,0,0.2);
        border-top: 1px solid rgba(255,255,255,0.1);
    }
    #modal-bulk-edit .btn-primary {
        background: #ed1c16;
        border: none;
        color: #fff;
        font-weight: 800;
        padding: 10px 25px;
        border-radius: 8px;
    }
    #modal-bulk-edit .btn-secondary {
        background: rgba(255,255,255,0.1);
        color: #fff;
        border: 1px solid rgba(255,255,255,0.2);
    }

    /* Hover Inteligente Docente */
    .docente-cell:hover {
        background: rgba(229,57,53,0.15) !important;
        border-color: rgba(229,57,53,0.3) !important;
        transform: translateY(-2px);
    }
</style>

<!-- Barra Flutuante de Seleção Única -->
<div id="floating-bar" class="floating-selection-bar">
    <div class="selection-count"><span id="floating-count">0</span> selecionadas</div>
    <div class="bar-actions">
        <button class="bar-btn" style="background: #6a1b9a;" onclick="openBulkEditModal()">
            <i class="fas fa-edit"></i> Editar Horários
        </button>
        <?php if (isAdmin()): ?>
            <button class="bar-btn btn-delete" onclick="deleteSelectedTurmas()">
                <i class="fas fa-trash"></i> Excluir
            </button>
        <?php endif; ?>
        <button class="bar-btn" onclick="clearSelection()" style="opacity: 0.8;">
            <i class="fas fa-times"></i> Cancelar
        </button>
    </div>
</div>

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

<!-- Modal: Edição em Lote -->
<div id="modal-bulk-edit" class="modal-overlay">
    <div class="modal-content animate-pop-in" style="max-width: 500px;">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edição em Lote</h3>
            <button type="button" class="close-modal" onclick="closeModal('modal-bulk-edit')">&times;</button>
        </div>
        <form action="../controllers/turmas_process.php?action=bulk_update" method="POST">
            <input type="hidden" name="ids" id="bulk-ids">
            <input type="hidden" name="return_url" value="turmas.php">
            
            <div class="modal-body">
                <p style="margin-bottom: 20px; color: var(--text-muted); font-size: 0.9rem;">
                    Os campos preenchidos abaixo serão aplicados a todas as turmas selecionadas. Campos em branco não serão alterados.
                </p>
                
                <div class="form-group">
                    <label class="form-label">Período</label>
                    <select name="periodo" id="bulk-periodo" class="form-input">
                        <option value="">Manter atual...</option>
                        <option value="Manhã">Manhã (07:30 - 11:30)</option>
                        <option value="Tarde">Tarde (13:30 - 17:30)</option>
                        <option value="Noite">Noite (18:00 - 23:00)</option>
                        <option value="Integral">Integral (07:30 - 17:30)</option>
                    </select>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Horário Início</label>
                        <input type="time" name="horario_inicio" id="bulk-horario-inicio" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Horário Até</label>
                        <input type="time" name="horario_fim" id="bulk-horario-fim" class="form-input">
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-bulk-edit')">Cancelar</button>
                <button type="submit" class="btn btn-primary" style="font-weight: 700;">SALVAR ALTERAÇÕES</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Atualiza o return_url do modal de bulk edit com os filtros atuais
    document.querySelector('#modal-bulk-edit form').addEventListener('submit', function() {
        const filters = getCurrentFilterParams();
        this.querySelector('input[name="return_url"]').value = '../views/turmas.php?' + filters;
    });

    // Preenchimento automático de horários baseado no período (Bulk Edit)
    document.getElementById('bulk-periodo').addEventListener('change', function() {
        const val = this.value;
        const hi = document.getElementById('bulk-horario-inicio');
        const hf = document.getElementById('bulk-horario-fim');
        
        if (val === 'Manhã') {
            hi.value = '07:30';
            hf.value = '11:30';
        } else if (val === 'Tarde') {
            hi.value = '13:30';
            hf.value = '17:30';
        } else if (val === 'Noite') {
            hi.value = '18:00';
            hf.value = '23:00';
        } else if (val === 'Integral') {
            hi.value = '07:30';
            hf.value = '17:30';
        }
    });
</script>