<?php
require_once __DIR__ . '/../configs/db.php';
include __DIR__ . '/../components/header.php';

$salas = mysqli_fetch_all(mysqli_query($conn, "SELECT * FROM ambiente ORDER BY nome ASC"), MYSQLI_ASSOC);
?>

<div class="filter-bar" style="margin-bottom: 20px; display: flex; gap: 10px; align-items: center; justify-content: flex-end;">
    <div class="search-box" style="flex: 1; max-width: 400px;">
        <input type="text" id="tableSearch" placeholder="Buscar ambiente..." class="form-input"
            style="width: 100%;" onkeyup="currentPage=1; updateFilterChips(); updatePagination()">
    </div>
    <div class="header-actions">
        <?php if (can_edit()): ?>
            <a href="salas_form.php" class="btn btn-primary" style="font-weight: 700;"><i class="fas fa-plus"></i> NOVO AMBIENTE</a>
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
                <th style="width: 50px;">#</th>
                <th>Nome do Ambiente</th>
                <th>Capacidade</th>
                <th>Tipo</th>
                <th>Cidade</th>
                <th>Área</th>
                <?php if (can_edit()): ?>
                    <th>Ações</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($salas)): ?>
                <tr>
                    <td colspan="6" class="text-center">Nenhum ambiente cadastrado.</td>
                </tr>
            <?php else: ?>
                <?php $idx = 1;
                foreach ($salas as $s): ?>
                    <tr class="table-row">
                        <td style="color: var(--text-muted); font-size: 0.8rem;"><?= $idx++ ?></td>
                        <td><?= htmlspecialchars($s['nome']) ?></td>
                        <td><?= $s['capacidade'] ?> pessoas</td>
                        <td><?= htmlspecialchars($s['tipo']) ?></td>
                        <td><?= htmlspecialchars($s['cidade']) ?></td>
                        <td><?= htmlspecialchars($s['area_vinculada']) ?></td>
                        <?php if (can_edit()): ?>
                            <td>
                                <a href="salas_form.php?id=<?= $s['id'] ?>" class="btn btn-edit"><i class="fas fa-edit"></i></a>
                                <a href="../controllers/salas_process.php?action=delete&id=<?= $s['id'] ?>" class="btn btn-delete"
                                    onclick="return confirm('Tem certeza?')"><i class="fas fa-trash"></i></a>
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

<script>
    let currentPage = 1;
    const itemsPerPage = 20;

    function updateFilterChips() {
        const container = document.getElementById('filter-chips-container');
        if (!container) return;
        container.innerHTML = '';

        const el = document.getElementById('tableSearch');
        if (el && el.value) {
            const chip = document.createElement('div');
            chip.className = 'filter-chip animate-fade-in';
            chip.innerHTML = `<i class="fas fa-search"></i> <span>Busca: ${el.value}</span> <i class="fas fa-times remove-chip" onclick="document.getElementById('tableSearch').value=''; updateFilterChips(); updatePagination();"></i>`;
            container.appendChild(chip);
        }
    }

    function updatePagination() {
        const searchTerm = document.getElementById('tableSearch').value.toLowerCase().trim();
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

        const infoEl = document.getElementById('page-info');
        if (infoEl) {
            const currentTotal = filteredRows.length;
            const shownStart = currentTotal > 0 ? start + 1 : 0;
            const shownEnd = Math.min(end, currentTotal);
            infoEl.innerHTML = `Exibindo <strong>${shownStart}-${shownEnd}</strong> de <strong>${currentTotal}</strong> ambientes`;
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
        updateFilterChips();
        updatePagination();
    });
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>