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
            <label class="form-label" style="display: flex; justify-content: space-between; align-items: center;">
                Área 
                <button type="button" onclick="openQuickAreaModal()" class="btn btn-sm" style="padding: 0 8px; font-size: 0.75rem; background: var(--bg-hover); color: var(--primary-red); border: 1px solid var(--border-color);">
                    <i class="fas fa-plus"></i> NOVA ÁREA
                </button>
            </label>
            <select name="area" id="area_select" class="form-input" required>
                <option value="">Selecione a área...</option>
                <?php
                $res_areas = mysqli_query($conn, "SELECT nome FROM area ORDER BY nome ASC");
                $area_atual = $curso['area'] ?? '';
                $area_encontrada = false;
                while($a = mysqli_fetch_assoc($res_areas)): 
                    $ap = $a['nome'];
                    $selected = (strcasecmp(trim($area_atual), trim($ap)) == 0);
                    if ($selected) $area_encontrada = true;
                ?>
                    <option value="<?= htmlspecialchars($ap) ?>" <?= $selected ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ap) ?>
                    </option>
                <?php endwhile; ?>
                <?php if ($area_atual && !$area_encontrada): ?>
                    <option value="<?= htmlspecialchars($area_atual) ?>" selected>
                        <?= htmlspecialchars($area_atual) ?> (Personalizado)
                    </option>
                <?php endif; ?>
            </select>
        </div>

        <!-- Modal Rápido de Área -->
        <div class="modal-overlay" id="quick-area-modal" style="display: none; z-index: 10000;">
            <div class="modal-content" style="max-width: 400px;">
                <div class="modal-header">
                    <h3>Nova Área</h3>
                    <button type="button" class="modal-close" onclick="closeQuickAreaModal()"><i class="fas fa-times"></i></button>
                </div>
                <div class="modal-body">
                    <input type="text" id="quick-area-nome" class="form-input" placeholder="Nome da nova área">
                    <button type="button" onclick="saveQuickArea()" class="btn btn-primary" style="width: 100%; margin-top: 15px;">Salvar e Selecionar</button>
                </div>
            </div>
        </div>

        <script>
        function openQuickAreaModal() {
            document.getElementById('quick-area-modal').style.display = 'flex';
            document.getElementById('quick-area-nome').focus();
        }
        function closeQuickAreaModal() {
            document.getElementById('quick-area-modal').style.display = 'none';
        }
        async function saveQuickArea() {
            const nome = document.getElementById('quick-area-nome').value.trim();
            if (!nome) return;
            
            const fd = new FormData();
            fd.append('action', 'save');
            fd.append('nome', nome);
            
            const res = await fetch('../controllers/area_api.php', { method: 'POST', body: fd });
            const data = await res.json();
            
            if (data.success) {
                const sel = document.getElementById('area_select');
                const opt = new Option(data.nome, data.nome, true, true);
                sel.add(opt);
                closeQuickAreaModal();
            } else {
                Swal.fire('Erro!', data.message, 'error');
            }
        }
        </script>

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