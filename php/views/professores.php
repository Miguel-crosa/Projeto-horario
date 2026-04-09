<?php
require_once __DIR__ . '/../configs/db.php';
include __DIR__ . '/../components/header.php';

$show_inactive = isset($_GET['show_inactive']) && $_GET['show_inactive'] == '1';
$where_status = $show_inactive ? " WHERE ativo = 0" : " WHERE ativo = 1";
$professores = mysqli_fetch_all(mysqli_query($conn, "SELECT * FROM docente $where_status ORDER BY nome ASC"), MYSQLI_ASSOC);
?>

<div class="page-header">
    <h2>Gestão de Professores</h2>
    <div class="header-actions" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <div class="search-box" style="flex: 1; min-width: 250px;">
            <input type="text" id="tableSearch" placeholder="Buscar professor..." class="form-input"
                style="width: 100%;" onkeyup="currentPage=1; updatePagination()">
        </div>
        <a href="professores_form.php" class="btn btn-primary"><i class="fas fa-plus"></i> Novo Professor</a>
        <?php if ($show_inactive): ?>
            <a href="professores.php" class="btn btn-secondary"><i class="fas fa-eye"></i> Ver Apenas Ativos</a>
        <?php else: ?>
            <a href="professores.php?show_inactive=1" class="btn btn-secondary"><i class="fas fa-eye-slash"></i> Ver Inativos</a>
        <?php endif; ?>
    </div>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th style="width: 50px;">#</th>
                <th>Nome</th>
                <th>Área de Conhecimento</th>
                <th>Limites (S/M)</th>
                <th>Tipo Contrato</th>
                <th>Ações</th>
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
                    <tr class="table-row">
                        <td style="color: var(--text-muted); font-size: 0.8rem;"><?= $idx++ ?></td>
                        <td>
                            <?= htmlspecialchars($p['nome']) ?>
                            <?php if($p['ativo'] == 0): ?>
                                <span class="badge" style="background: rgba(239, 68, 68, 0.1); color: #f87171; border-color: rgba(239, 68, 68, 0.2); margin-left: 10px;">Inativo</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($p['area_conhecimento']) ?></td>
                        <td><?= $p['weekly_hours_limit'] ?>h / <?= $p['monthly_hours_limit'] ?>h</td>
                        <td><?= htmlspecialchars($p['tipo_contrato'] ?? 'N/A') ?></td>
                        <td>
                            <a href="professores_form.php?id=<?= $p['id'] ?>" class="btn btn-edit"><i
                                    class="fas fa-edit"></i></a>
                            <?php if ($p['ativo'] == 1): ?>
                                <a href="../controllers/professores_process.php?action=delete&id=<?= $p['id'] ?>"
                                    class="btn btn-delete" onclick="return confirm('Desativar este professor? Ele deixará de aparecer nas listas, mas seus dados serão mantidos.')" title="Desativar"><i class="fas fa-user-slash"></i></a>
                            <?php else: ?>
                                <a href="../controllers/professores_process.php?action=activate&id=<?= $p['id'] ?>"
                                    class="btn btn-edit" style="background-color: #2e7d32;" onclick="return confirm('Reativar este professor?')" title="Reativar"><i class="fas fa-user-check"></i></a>
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