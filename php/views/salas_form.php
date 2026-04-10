<?php
require_once __DIR__ . '/../configs/db.php';
include __DIR__ . '/../components/header.php';

if (!can_edit()) {
    header("Location: salas.php");
    exit;
}

$id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : null;
$sala = ['nome' => '', 'capacidade' => '', 'tipo' => '', 'cidade' => '', 'area_vinculada' => ''];

if ($id) {
    $sala = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM ambiente WHERE id = '$id'"));
    if (!$sala) {
        header("Location: salas.php");
        exit;
    }
}
?>

<div class="page-header">
    <h2><?= $id ? 'Editar Ambiente' : 'Novo Ambiente' ?></h2>
    <a href="salas.php" class="btn btn-back"><i class="fas fa-arrow-left"></i> Voltar</a>
</div>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <form action="../controllers/salas_process.php" method="POST">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-group">
            <label class="form-label">Nome do Ambiente</label>
            <input type="text" name="nome" class="form-input" value="<?= htmlspecialchars($sala['nome']) ?>" required>
        </div>
        <div class="form-group">
            <label class="form-label">Capacidade</label>
            <input type="number" name="capacidade" class="form-input" value="<?= $sala['capacidade'] ?>" required>
        </div>
        <div class="form-group">
            <label class="form-label">Tipo</label>
            <select name="tipo" id="tipo_ambiente" class="form-input">
                <option value="Teórica" <?= $sala['tipo'] == 'Teórica' ? 'selected' : '' ?>>Teórica</option>
                <option value="Laboratório" <?= $sala['tipo'] == 'Laboratório' ? 'selected' : '' ?>>Laboratório</option>
                <option value="Oficina" <?= $sala['tipo'] == 'Oficina' ? 'selected' : '' ?>>Oficina</option>
                <option value="Outros" <?= $sala['tipo'] == 'Outros' ? 'selected' : '' ?>>Outros</option>
            </select>
        </div>
        <div class="form-group" id="container_area_select" <?= $sala['tipo'] == 'Outros' ? 'style="display:none;"' : '' ?>>
            <label class="form-label">Área Vinculada</label>
            <select name="area_vinculada" id="area_select" class="form-input" <?= $sala['tipo'] == 'Outros' ? 'disabled' : '' ?>>
                <option value="">Selecione a área...</option>
                <?php
                $areas_padronizadas = [
                    'TI / Software',
                    'Mecatrônica / Automação',
                    'Metalmecânica',
                    'Logística',
                    'Eletroeletrônica',
                    'Gestão / Qualidade',
                    'Alimentos',
                    'Vestuário',
                    'Soldagem',
                    'Manutenção Industrial',
                    'Automotiva',
                    'Construção Civil'
                ];
                foreach ($areas_padronizadas as $ap): ?>
                    <option value="<?= htmlspecialchars($ap) ?>" <?= trim($sala['area_vinculada']) == trim($ap) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ap) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" id="container_area_input" <?= $sala['tipo'] == 'Outros' ? '' : 'style="display:none;"' ?>>
            <label class="form-label">Área Vinculada (Manual)</label>
            <input type="text" name="area_vinculada" id="area_input" class="form-input"
                value="<?= htmlspecialchars($sala['area_vinculada']) ?>" <?= $sala['tipo'] == 'Outros' ? '' : 'disabled' ?>>
        </div>
        <div class="form-group-last">
            <label class="form-label">Cidade</label>
            <input type="text" name="cidade" class="form-input" value="<?= htmlspecialchars($sala['cidade']) ?>">
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Salvar Ambiente</button>
        </div>
    </form>
</div>

<script>
    document.getElementById('tipo_ambiente').addEventListener('change', function () {
        const isOutros = this.value === 'Outros';
        const containerSelect = document.getElementById('container_area_select');
        const containerInput = document.getElementById('container_area_input');
        const areaSelect = document.getElementById('area_select');
        const areaInput = document.getElementById('area_input');

        if (isOutros) {
            containerSelect.style.display = 'none';
            containerInput.style.display = 'block';
            areaSelect.disabled = true;
            areaInput.disabled = false;
            areaInput.required = true;
        } else {
            containerSelect.style.display = 'block';
            containerInput.style.display = 'none';
            areaSelect.disabled = false;
            areaInput.disabled = true;
            areaSelect.required = true;
        }
    });
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>