<?php
require_once __DIR__ . '/../configs/db.php';
include __DIR__ . '/../components/header.php';

if (!isAdmin() && !isGestor()) {
    header("Location: ../../index.php");
    exit;
}

$prep = mysqli_fetch_all(mysqli_query($conn, "SELECT p.*, d.nome AS professor_nome FROM preparacao_atestados p LEFT JOIN docente d ON p.docente_id = d.id ORDER BY p.data_inicio DESC"), MYSQLI_ASSOC);
?>

<div class="page-header">
    <h2>Preparação / Ausências</h2>
</div>

<div class="filter-bar" style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center; justify-content: flex-end;">
    <div class="search-box" style="flex: 1; max-width: 350px;">
        <input type="text" id="tableSearch" placeholder="Buscar por professor ou tipo..." class="form-input"
            style="width: 100%;" onkeyup="currentPage=1; updatePagination(); updateFilterChips();">
    </div>
    <div class="header-actions" style="display: flex; gap: 8px;">
        <button type="button" id="btnDeleteSelected" class="btn" style="background:#dc3545; color:#fff; display:none;" onclick="deleteSelected()">
            <i class="fas fa-trash"></i> EXCLUIR SELECIONADOS
        </button>
        <button type="button" class="btn" style="background:#6c757d; color:#fff;" onclick="deleteAll()">
            <i class="fas fa-dumpster"></i> EXCLUIR TUDO
        </button>
        <a href="preparacao_form.php" class="btn btn-primary" style="font-weight: 700;"><i class="fas fa-plus"></i> NOVO REGISTRO</a>
    </div>
</div>

<form id="bulkDeleteForm" action="../controllers/preparacao_process.php?action=delete_multiple" method="POST" style="display:none;"></form>

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
                <th>Tipo</th>
                <th>Professor</th>
                <th>Início</th>
                <th>Fim</th>
                <th>Horário</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($prep)): ?>
                <tr class="table-row">
                    <td colspan="6" class="text-center">Nenhum registro encontrado.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($prep as $p): ?>
                    <tr class="table-row">
                        <td style="text-align: center;">
                            <input type="checkbox" class="row-checkbox" value="<?= $p['id'] ?>" onclick="updateDeleteButton()">
                        </td>
                        <td>
                            <?php if ($p['tipo'] === 'preparação'): ?>
                                <span class="badge" style="background:#673ab7; color: #fff !important; font-weight: 700;">Preparação</span>
                            <?php elseif ($p['tipo'] === 'atestado'): ?>
                                <span class="badge" style="background:#e91e63; color: #fff !important; font-weight: 700;">Atestado</span>
                            <?php else: ?>
                                <span class="badge" style="background:#ff9800; color: #fff !important; font-weight: 700;">Ausência</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($p['professor_nome']) ?>
                        </td>
                        <td>
                            <?= date('d/m/Y', strtotime($p['data_inicio'])) ?>
                        </td>
                        <td>
                            <?= date('d/m/Y', strtotime($p['data_fim'])) ?>
                        </td>
                        <td>
                            <?php 
                            if ($p['horario_inicio']) {
                                echo substr($p['horario_inicio'], 0, 5) . ' - ' . substr($p['horario_fim'], 0, 5);
                            } else {
                                echo 'Dia Integral';
                            }
                            
                            if ($p['tipo'] === 'preparação' && !empty($p['dias_semana'])) {
                                $dias_map = [1 => '2ª', 2 => '3ª', 3 => '4ª', 4 => '5ª', 5 => '6ª', 6 => 'sab'];
                                $parts = explode(',', $p['dias_semana']);
                                $labels = [];
                                foreach ($parts as $pt) if (isset($dias_map[$pt])) $labels[] = $dias_map[$pt];
                                echo '<br><small style="color:var(--text-muted); font-weight:600;">(' . implode(', ', $labels) . ')</small>';
                            }
                            ?>
                        </td>
                        <td>
                            <a href="preparacao_form.php?id=<?= $p['id'] ?>" class="btn btn-edit"><i
                                    class="fas fa-edit"></i></a>
                            <a href="../controllers/preparacao_process.php?action=delete&id=<?= $p['id'] ?>"
                                class="btn btn-delete" onclick="return confirm('Tem certeza que deseja remover?')"><i
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

    function updateDeleteButton() {
        const checkboxes = document.querySelectorAll('.row-checkbox:checked');
        const btn = document.getElementById('btnDeleteSelected');
        btn.style.display = checkboxes.length > 0 ? 'inline-block' : 'none';
        
        // Update selectAll state
        const allCheckboxes = document.querySelectorAll('.row-checkbox');
        document.getElementById('selectAll').checked = allCheckboxes.length > 0 && checkboxes.length === allCheckboxes.length;
    }

    function toggleSelectAll(source) {
        const checkboxes = document.querySelectorAll('.row-checkbox');
        checkboxes.forEach(cb => {
            if (cb.closest('tr').style.display !== 'none') {
                cb.checked = source.checked;
            }
        });
        updateDeleteButton();
    }

    function deleteSelected() {
        const checkboxes = document.querySelectorAll('.row-checkbox:checked');
        if (checkboxes.length === 0) return;

        if (confirm(`Tem certeza que deseja excluir os ${checkboxes.length} registros selecionados?`)) {
            const form = document.getElementById('bulkDeleteForm');
            form.innerHTML = '';
            checkboxes.forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = cb.value;
                form.appendChild(input);
            });
            form.submit();
        }
    }

    function deleteAll() {
        if (confirm('ATENÇÃO: Isso apagará TODOS os registros de preparação e ausências. Esta ação não pode ser desfeita. Deseja continuar?')) {
            window.location.href = '../controllers/preparacao_process.php?action=delete_all';
        }
    }

    window.addEventListener('load', () => {
        updatePagination();
        updateFilterChips();

        // Alertas de feedback
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('msg')) {
            const msg = urlParams.get('msg');
            if (msg === 'deleted') {
                Swal.fire({ icon: 'success', title: 'Excluído!', text: 'Registro removido com sucesso.', timer: 2000, showConfirmButton: false });
            } else if (msg === 'deleted_multiple') {
                Swal.fire({ icon: 'success', title: 'Excluídos!', text: 'Registros selecionados foram removidos.', timer: 2000, showConfirmButton: false });
            } else if (msg === 'deleted_all') {
                Swal.fire({ icon: 'success', title: 'Limpeza Total!', text: 'Todos os registros foram removidos.', timer: 2000, showConfirmButton: false });
            } else if (msg === 'success') {
                Swal.fire({ icon: 'success', title: 'Sucesso!', text: 'Operação realizada com êxito.', timer: 2000, showConfirmButton: false });
            }
        }
    });
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>