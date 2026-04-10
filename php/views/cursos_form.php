<?php
require_once __DIR__ . '/../configs/db.php';
include __DIR__ . '/../components/header.php';

if (!can_edit()) {
    header("Location: cursos.php");
    exit;
}

$id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : null;
$curso = ['tipo' => '', 'nome' => '', 'area' => '', 'carga_horaria_total' => '', 'semestral' => 0];

if ($id) {
    $curso = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM curso WHERE id = '$id'"));
    if (!$curso) {
        header("Location: cursos.php");
        exit;
    }
}
?>

<div class="page-header">
    <h2><?= $id ? 'Editar Curso' : 'Novo Curso' ?></h2>
    <a href="cursos.php" class="btn btn-back"><i class="fas fa-arrow-left"></i> Voltar</a>
</div>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <form action="../controllers/cursos_process.php" method="POST">
        <input type="hidden" name="id" value="<?= $id ?>">

        <div class="form-group">
            <label class="form-label">Tipo de Curso</label>
            <select name="tipo" class="form-input" required>
                <option value="FIC" <?= $curso['tipo'] == 'FIC' ? 'selected' : '' ?>>FIC (Formação Inicial e Continuada)
                </option>
                <option value="Técnico" <?= $curso['tipo'] == 'Técnico' ? 'selected' : '' ?>>Técnico</option>
                <option value="CAI" <?= $curso['tipo'] == 'CAI' ? 'selected' : '' ?>>CAI (Curso de Aprendizagem Industrial)
                </option>
                <option value="Superior" <?= $curso['tipo'] == 'Superior' ? 'selected' : '' ?>>Superior</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Nome do Curso</label>
            <input type="text" name="nome" class="form-input" value="<?= htmlspecialchars($curso['nome']) ?>" required>
        </div>

        <div class="form-group">
            <label class="form-label">Área</label>
            <select name="area" class="form-input" required>
                <option value="">Selecione a área...</option>
                <?php
                $areas_padronizadas = [
                    'TECNOLOGIA DA INFORMAÇÃO',
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
                    'Construção Civil',
                    'Madeira e Mobiliário',
                    'Administração e Gestão'
                ];
                $area_atual = $curso['area'] ?? '';
                $area_encontrada = false;
                foreach ($areas_padronizadas as $ap): 
                    $selected = (strcasecmp(trim($area_atual), trim($ap)) == 0);
                    if ($selected) $area_encontrada = true;
                ?>
                    <option value="<?= htmlspecialchars($ap) ?>" <?= $selected ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ap) ?>
                    </option>
                <?php endforeach; ?>
                <?php if ($area_atual && !$area_encontrada): ?>
                    <option value="<?= htmlspecialchars($area_atual) ?>" selected>
                        <?= htmlspecialchars($area_atual) ?> (Personalizado)
                    </option>
                <?php endif; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">Carga Horária Total (horas)</label>
            <input type="number" name="carga_horaria_total" class="form-input"
                value="<?= $curso['carga_horaria_total'] ?>" required>
        </div>

        <div class="form-group-last">
            <label class="form-label">
                <input type="checkbox" name="semestral" value="1" <?= $curso['semestral'] ? 'checked' : '' ?>>
                Curso Semestral
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Salvar Curso</button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>