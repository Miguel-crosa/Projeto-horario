<?php
require_once __DIR__ . '/../configs/db.php';
include __DIR__ . '/../components/header.php';

if (!isAdmin() && !isGestor()) {
    header("Location: dashboard_vendas.php");
    exit;
}

$prep = mysqli_fetch_all(mysqli_query($conn, "SELECT p.*, d.nome AS professor_nome FROM preparacao_atestados p LEFT JOIN docente d ON p.docente_id = d.id ORDER BY p.data_inicio DESC"), MYSQLI_ASSOC);
?>

<div class="page-header">
    <h2>Preparação e Atestados</h2>
    <div class="header-actions" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <div class="search-box">
            <input type="text" id="tableSearch" placeholder="Buscar professor..." class="form-input"
                style="width: 300px;" onkeyup="currentPage=1; updatePagination()">
        </div>
        <a href="preparacao_form.php" class="btn btn-primary"><i class="fas fa-plus"></i> Novo Registro</a>
    </div>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
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
                        <td>
                            <?php if ($p['tipo'] === 'preparação'): ?>
                                <span class="badge" style="background:#673ab7;">Preparação</span>
                            <?php else: ?>
                                <span class="badge" style="background:#e91e63;">Atestado</span>
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

    window.addEventListener('load', updatePagination);
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>