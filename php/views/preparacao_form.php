<?php
require_once __DIR__ . '/../configs/db.php';
include __DIR__ . '/../components/header.php';

if (!isAdmin() && !isGestor()) {
    header("Location: ../../index.php");
    exit;
}

$id = $_GET['id'] ?? null;
$prep = ['docente_id' => '', 'tipo' => 'preparação', 'data_inicio' => '', 'data_fim' => '', 'horario_inicio' => '', 'horario_fim' => '', 'dias_semana' => ''];

if ($id) {
    $res = mysqli_query($conn, "SELECT * FROM preparacao_atestados WHERE id = " . (int) $id);
    if ($row = mysqli_fetch_assoc($res)) {
        $prep = $row;
    }
}

$professores = mysqli_fetch_all(mysqli_query($conn, "SELECT id, nome, area_conhecimento FROM docente ORDER BY nome ASC"), MYSQLI_ASSOC);
?>

<div class="page-header">
    <h2>
        <?= $id ? 'Editar' : 'Novo' ?> Registro de Preparação / Ausências
    </h2>
</div>

<div class="card" style="max-width: 600px; margin: 0 auto; padding: 30px; border-radius: 12px;">
    <form action="../controllers/preparacao_process.php" method="POST">
        <input type="hidden" name="id" value="<?= $id ?>">

        <div class="form-group" style="margin-bottom: 25px;">
            <label class="form-label"
                style="font-weight: 700; color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px; display: block;">Professor</label>
            <div style="display: flex; gap: 15px; align-items: flex-end;">
                <button type="button" class="btn" id="btn-selecionar-professor"
                    style="background: <?= !empty($prep['docente_id']) ? '#2e7d32' : 'var(--primary-red)' ?>; 
                           color: #fff;
                           border: 2px solid <?= !empty($prep['docente_id']) ? '#1b5e20' : 'var(--primary-red)' ?>; 
                           padding: 10px 24px; font-weight: 700; border-radius: 8px; display: flex; align-items: center; gap: 10px; height: 45px; min-width: 250px; justify-content: flex-start; transition: all 0.2s;">
                    <i class="fas fa-user-plus"></i>
                    <span id="btn-prof-label">
                        <?php
                        $nome_exibicao = 'Selecionar Professor';
                        if (!empty($prep['docente_id'])) {
                            foreach ($professores as $p) {
                                if ($p['id'] == $prep['docente_id']) {
                                    $nome_exibicao = htmlspecialchars($p['nome']);
                                    break;
                                }
                            }
                        }
                        echo $nome_exibicao;
                        ?>
                    </span>
                </button>
                <input type="hidden" name="docente_id" id="form-docente-id" value="<?= $prep['docente_id'] ?>" required>
            </div>
        </div>

        <div class="form-group" style="margin-bottom: 20px;">
            <label class="form-label" style="font-weight: 700;">Tipo</label>
            <select name="tipo" class="form-input" required onchange="toggleTimeFields(this.value)"
                style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color);">
                <option value="preparação" <?= $prep['tipo'] === 'preparação' ? 'selected' : '' ?>>Preparação de Aula
                </option>
                <option value="atestado" <?= $prep['tipo'] === 'atestado' ? 'selected' : '' ?>>Atestado Médico</option>
                <option value="ausência" <?= $prep['tipo'] === 'ausência' ? 'selected' : '' ?>>Ausência Particular</option>
            </select>
        </div>

        <div style="display: flex; gap: 20px; margin-bottom: 20px;">
            <div class="form-group" style="flex: 1;">
                <label class="form-label" style="font-weight: 700;">Data Início</label>
                <input type="date" name="data_inicio" class="form-input" value="<?= $prep['data_inicio'] ?>" required
                    style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color);">
            </div>
            <div class="form-group" style="flex: 1;">
                <label class="form-label" style="font-weight: 700;">Data Fim</label>
                <input type="date" name="data_fim" class="form-input" value="<?= $prep['data_fim'] ?>" required
                    style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color);">
            </div>
        </div>

        <!-- Seletor de Dias da Semana (Premium) -->
        <div id="weekday-selector"
            style="display: <?= $prep['tipo'] === 'preparação' ? 'block' : 'none' ?>; margin-bottom: 25px;">
            <label class="form-label"
                style="font-weight: 700; color: #9ba1b0; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px; display: block;">Dias
                da Semana</label>
            <div class="days-container" style="display: flex; gap: 12px; align-items: center;">
                <?php
                $dias_arr = $prep['dias_semana'] ? explode(',', $prep['dias_semana']) : [];
                $week_days = [
                    1 => 'S',
                    2 => 'T',
                    3 => 'Q',
                    4 => 'Q',
                    5 => 'S',
                    6 => 'S'
                ];
                foreach ($week_days as $num => $label):
                    $checked = in_array($num, $dias_arr) ? 'checked' : '';
                    ?>
                    <label class="day-circle" title="<?= $label ?>">
                        <input type="checkbox" name="dias_semana[]" value="<?= $num ?>" <?= $checked ?>
                            style="display: none;">
                        <span class="circle-initial" style="font-size: 0.75rem;"><?= $label ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div id="time-fields"
            style="display: flex; gap: 20px; margin-bottom: 20px;">
            <div class="form-group" style="flex: 1;">
                <label class="form-label" style="font-weight: 700;">Hora Início (Opcional)</label>
                <input type="time" name="horario_inicio" class="form-input"
                    value="<?= $prep['horario_inicio'] ? substr($prep['horario_inicio'], 0, 5) : '' ?>"
                    style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color);">
            </div>
            <div class="form-group" style="flex: 1;">
                <label class="form-label" style="font-weight: 700;">Hora Fim (Opcional)</label>
                <input type="time" name="horario_fim" class="form-input"
                    value="<?= $prep['horario_fim'] ? substr($prep['horario_fim'], 0, 5) : '' ?>"
                    style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border-color);">
            </div>
        </div>

        <div style="display: flex; gap: 15px; justify-content: flex-end; margin-top: 30px;">
            <a href="preparacao.php" class="btn"
                style="background: #ccc; color: #333; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 700;">Cancelar</a>
            <button type="submit" class="btn btn-primary"
                style="padding: 10px 30px; border-radius: 8px; font-weight: 700;">Salvar Alterações</button>
        </div>
    </form>
</div>


<style>
    .day-circle {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 42px;
        height: 42px;
        border-radius: 50%;
        border: 2px solid rgba(155, 161, 176, 0.2);
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        user-select: none;
        background: transparent;
    }

    .day-circle .circle-initial {
        font-weight: 800;
        font-size: 0.9rem;
        color: #9ba1b0;
        transition: all 0.3s;
    }

    .day-circle:has(input:checked) {
        background: #1a1f2b;
        border-color: #ed1c24 !important;
        box-shadow: 0 0 15px rgba(237, 28, 36, 0.2);
        transform: translateY(-2px);
    }

    .day-circle:has(input:checked) .circle-initial {
        color: #fff;
    }

    .day-circle:hover:not(:has(input:checked)) {
        border-color: rgba(155, 161, 176, 0.5);
        background: rgba(155, 161, 176, 0.05);
    }
</style>

<script>
    function toggleTimeFields(val) {
        const weekdays = document.getElementById('weekday-selector');

        if (val === 'preparação') {
            weekdays.style.display = 'block';
        } else {
            weekdays.style.display = 'none';
        }
    }

    // Lógica do Modal de Seleção de Professor (Idêntico ao Dashboard)
    document.addEventListener('DOMContentLoaded', function () {
        const btnSel = document.getElementById('btn-selecionar-professor');
        const profModal = document.getElementById('modal-selecionar-professor');
        const profSearchInput = document.getElementById('prof-search-input');
        const profAreaFilter = document.getElementById('prof-area-filter');
        const profSearchResults = document.getElementById('prof-search-results');
        const hiddenId = document.getElementById('form-docente-id');
        const btnLabel = document.getElementById('btn-prof-label');
        const docentes = window.__docentesData || [];

        if (btnSel) {
            btnSel.onclick = function () {
                if (profModal) {
                    profModal.classList.add('active');
                    if (profSearchInput) profSearchInput.value = '';
                    if (profAreaFilter) profAreaFilter.value = '';
                    renderModalResults();
                    setTimeout(() => profSearchInput?.focus(), 100);
                }
            };
        }

        function renderModalResults() {
            if (!profSearchResults) return;
            const query = (profSearchInput?.value || '').toLowerCase().trim();
            const area = (profAreaFilter?.value || '');

            let filtered = docentes.filter(d => {
                const matchNome = !query || d.nome.toLowerCase().includes(query);
                const matchArea = !area || d.area_conhecimento === area;
                return matchNome && matchArea;
            });

            if (filtered.length === 0) {
                profSearchResults.innerHTML = '<div style="text-align:center; padding:20px; color:#888;">Nenhum professor encontrado.</div>';
                return;
            }

            profSearchResults.innerHTML = filtered.map(d => `
                <div class="prof-result-item" data-id="${d.id}" data-nome="${d.nome.replace(/"/g, '&quot;')}" 
                    style="padding: 12px; border-bottom: 1px solid var(--border-color); cursor: pointer; display: flex; justify-content: space-between; align-items: center; transition: background 0.2s;">
                    <div>
                        <strong style="font-size: 0.95rem; color: var(--text-color);">${d.nome}</strong><br>
                        <small style="color: #888; font-size: 0.75rem;">${d.area_conhecimento || 'Outros'}</small>
                    </div>
                    <i class="fas fa-chevron-right" style="color: #ccc; font-size: 0.8rem;"></i>
                </div>
            `).join('');

            profSearchResults.querySelectorAll('.prof-result-item').forEach(item => {
                item.onclick = function () {
                    const id = this.dataset.id;
                    const nome = this.dataset.nome;

                    if (hiddenId) hiddenId.value = id;
                    if (btnLabel) btnLabel.textContent = nome;
                    if (btnSel) {
                        btnSel.style.background = '#2e7d32';
                        btnSel.style.border = '2px solid #1b5e20';
                    }
                    if (profModal) profModal.classList.remove('active');
                };
            });
        }

        if (profSearchInput) profSearchInput.oninput = renderModalResults;
        if (profAreaFilter) profAreaFilter.onchange = renderModalResults;
    });

    window.__docentesData = <?= json_encode($professores) ?>;
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>