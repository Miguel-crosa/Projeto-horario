<?php
require_once __DIR__ . '/../configs/db.php';
include __DIR__ . '/../components/header.php';

if (!isAdmin() && !isGestor()) {
    header("Location: ../../index.php");
    exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$v = ['type' => 'individual', 'teacher_id' => '', 'start_date' => date('Y-m-d'), 'end_date' => date('Y-m-d', strtotime('+30 days'))];

if ($id) {
    $v = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM vacations WHERE id = $id"));
    if (!$v) {
        header("Location: ferias.php");
        exit;
    }
}
$professores = mysqli_fetch_all(mysqli_query($conn, "SELECT id, nome FROM docente ORDER BY nome ASC"), MYSQLI_ASSOC);
?>

<div class="page-header">
    <h2>
        <?= $id ? 'Editar Férias' : 'Registrar Férias' ?>
    </h2>
    <a href="ferias.php" class="btn btn-back"><i class="fas fa-arrow-left"></i> Voltar</a>
</div>

<div class="card" style="max-width: 500px; margin: 0 auto;">
    <form action="../controllers/ferias_process.php" method="POST">
        <input type="hidden" name="id" value="<?= $id ?>">

        <div class="form-group">
            <label class="form-label">Tipo de Férias</label>
            <select name="type" id="type_select" class="form-input" required onchange="toggleTeacherSelect()">
                <option value="individual" <?= $v['type'] == 'individual' ? 'selected' : '' ?>>Individuais (1 Professor)
                </option>
                <option value="collective" <?= $v['type'] == 'collective' ? 'selected' : '' ?>>Coletivas / Fechamento
                    (Todos)</option>
            </select>
        </div>

        <div class="form-group" id="teacher_group" style="<?= $v['type'] == 'collective' ? 'display:none;' : '' ?>">
            <label class="form-label">Professor (Férias Individuais)</label>
            <select name="teacher_id" id="teacher_select" class="form-input" <?= $v['type'] == 'individual' ? 'required' : '' ?>>
                <option value="">Selecione o professor...</option>
                <?php foreach ($professores as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $v['teacher_id'] == $p['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" id="collective_teachers_group"
            style="<?= $v['type'] == 'collective' ? 'display:block;' : 'display:none;' ?>">
            <label class="form-label">Professores (Férias Coletivas)</label>
            <p style="font-size:0.8rem; color:#666; margin-bottom:10px;">Selecione os professores que entrarão em Férias
                Coletivas. <b>Se não selecionar nenhum, as férias serão aplicadas a TODOS os professores.</b></p>

            <?php if ($id && $v['teacher_id']): ?>
                <!-- Editing a specific collective vacation for one professor -->
                <input type="hidden" name="collective_teacher_ids[]" value="<?= $v['teacher_id'] ?>">
                <div style="padding: 10px; background: #eee; border-radius: 5px;">
                    Aplicado para:
                    <b><?= htmlspecialchars(array_column(array_filter($professores, function ($pp) use ($v) {
                        return $pp['id'] == $v['teacher_id'];
                    }), 'nome')[0] ?? 'Desconhecido') ?></b>
                </div>
            <?php else: ?>
                <input type="text" id="prof_search" class="form-input" placeholder="Pesquisar professor por nome..."
                    style="margin-bottom: 10px;" onkeyup="filterProfessors()">

                <div id="prof_checkboxes"
                    style="max-height: 200px; overflow-y: auto; border: 1px solid #ccc; border-radius: 5px; padding: 10px;">
                    <?php foreach ($professores as $p): ?>
                        <div class="prof-item" style="margin-bottom: 5px;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.9rem;">
                                <input type="checkbox" name="collective_teacher_ids[]" value="<?= $p['id'] ?>"
                                    class="prof-checkbox" <?= ($id && $v['teacher_id'] == $p['id']) ? 'checked' : '' ?>>
                                <span class="prof-name"><?= htmlspecialchars($p['nome']) ?></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top: 10px; display: flex; gap: 10px;">
                    <button type="button" class="btn btn-secondary" style="padding: 4px 8px; font-size: 0.8rem;"
                        onclick="selectAllProfs(true)">Marcar Todos</button>
                    <button type="button" class="btn btn-secondary" style="padding: 4px 8px; font-size: 0.8rem;"
                        onclick="selectAllProfs(false)">Desmarcar Todos</button>
                </div>
            <?php endif; ?>
        </div>

        <div class="form-group" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <div>
                <label class="form-label">Data de Início</label>
                <input type="date" name="start_date" class="form-input"
                    value="<?= htmlspecialchars($v['start_date']) ?>" required>
            </div>
            <div>
                <label class="form-label">Data de Resplendor / Fim</label>
                <input type="date" name="end_date" class="form-input" value="<?= htmlspecialchars($v['end_date']) ?>"
                    required>
            </div>
        </div>

        <div class="form-actions" style="margin-top: 20px;">
            <button type="submit" class="btn btn-primary">Salvar Férias</button>
        </div>
    </form>
</div>

<script>
    function toggleTeacherSelect() {
        const type = document.getElementById('type_select').value;
        const teacherGroup = document.getElementById('teacher_group');
        const teacherSelect = document.getElementById('teacher_select');
        const collectiveGroup = document.getElementById('collective_teachers_group');

        if (type === 'collective') {
            teacherGroup.style.display = 'none';
            teacherSelect.removeAttribute('required');
            if (collectiveGroup) collectiveGroup.style.display = 'block';
        } else {
            teacherGroup.style.display = 'block';
            teacherSelect.setAttribute('required', 'required');
            if (collectiveGroup) collectiveGroup.style.display = 'none';
        }
    }

    function filterProfessors() {
        const input = document.getElementById('prof_search').value.toLowerCase();
        const items = document.querySelectorAll('.prof-item');

        items.forEach(item => {
            const name = item.querySelector('.prof-name').textContent.toLowerCase();
            if (name.includes(input)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    }

    function selectAllProfs(check) {
        document.querySelectorAll('.prof-checkbox').forEach(cb => {
            // only check visible ones (filtered)
            if (cb.closest('.prof-item').style.display !== 'none') {
                cb.checked = check;
            }
        });
    }
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>