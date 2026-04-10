<?php
require_once __DIR__ . '/../configs/db.php';
include __DIR__ . '/../components/header.php';

$salas = mysqli_fetch_all(mysqli_query($conn, "SELECT * FROM ambiente ORDER BY nome ASC"), MYSQLI_ASSOC);
?>

<div class="page-header">
    <h2>Gestão de Ambientes</h2>
    <div class="header-actions" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <div class="search-box">
            <input type="text" id="tableSearch" placeholder="Buscar ambiente..." class="form-input"
                style="width: 300px;" onkeyup="currentPage=1; updatePagination()">
        </div>
        <?php if (can_edit()): ?>
            <a href="salas_form.php" class="btn btn-primary"><i class="fas fa-plus"></i> Novo Ambiente</a>
        <?php endif; ?>
    </div>
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

    window.addEventListener('load', updatePagination);
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>