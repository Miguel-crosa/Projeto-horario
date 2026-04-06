<?php
require_once __DIR__ . '/../configs/db.php';
include __DIR__ . '/../components/header.php';

// Apenas admin/gestor
if (!isAdmin() && !isGestor()) {
    header("Location: ../../index.php");
    exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$feriado = ['name' => '', 'date' => date('Y-m-d'), 'end_date' => date('Y-m-d')];

if ($id) {
    $feriado = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM holidays WHERE id = $id"));
    if (!$feriado) {
        header("Location: feriados.php");
        exit;
    }
}
?>

<div class="page-header">
    <h2>
        <?= $id ? 'Editar Feriado' : 'Novo Feriado' ?>
    </h2>
    <a href="feriados.php" class="btn btn-back"><i class="fas fa-arrow-left"></i> Voltar</a>
</div>

<div class="card" style="max-width: 500px; margin: 0 auto;">
    <form action="../controllers/feriados_process.php" method="POST">
        <input type="hidden" name="id" value="<?= $id ?>">

        <div class="form-group">
            <label class="form-label">Nome / Descrição do Feriado</label>
            <input type="text" name="name" class="form-input" value="<?= htmlspecialchars($feriado['name']) ?>" required
                placeholder="Ex: Dia do Trabalho">
        </div>

        <div class="row" style="display: flex; gap: 15px;">
            <div class="form-group" style="flex: 1;">
                <label class="form-label">Data Início</label>
                <input type="date" name="date" class="form-input" value="<?= htmlspecialchars($feriado['date']) ?>"
                    required>
            </div>

            <div class="form-group" style="flex: 1;">
                <label class="form-label">Data Fim</label>
                <input type="date" name="end_date" class="form-input"
                    value="<?= htmlspecialchars($feriado['end_date'] ?: $feriado['date']) ?>" required>
            </div>
        </div>

        <div class="form-actions" style="margin-top: 20px;">
            <button type="submit" class="btn btn-primary">Salvar Feriado</button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>