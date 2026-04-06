<?php
require_once __DIR__ . '/../configs/db.php';
include __DIR__ . '/../components/header.php';

$id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : null;
$prof = ['nome' => '', 'profissao' => '', 'area_conhecimento' => '', 'cidade' => '', 'weekly_hours_limit' => '0', 'monthly_hours_limit' => '0'];

if ($id) {
    $prof = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM docente WHERE id = '$id'"));
    if (!$prof) {
        header("Location: professores.php");
        exit;
    }
    // Buscar disponibilidade para a grade
    $availability = [];
    $res_h = mysqli_query($conn, "SELECT * FROM horario_trabalho WHERE docente_id = '$id'");
    while ($row_h = mysqli_fetch_assoc($res_h)) {
        $p = $row_h['periodo'];
        $ds = explode(',', $row_h['dias']);
        foreach ($ds as $d) {
            $availability[$p][trim($d)] = true;
        }
    }
} else {
    $availability = [];
}
?>

<div class="page-header">
    <h2><?= $id ? 'Editar Professor' : 'Novo Professor' ?></h2>
    <a href="professores.php" class="btn btn-back"><i class="fas fa-arrow-left"></i> Voltar</a>
</div>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <form action="../controllers/professores_process.php" method="POST">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-group">
            <label class="form-label">Nome Completo</label>
            <input type="text" name="nome" class="form-input" value="<?= htmlspecialchars($prof['nome'] ?? '') ?>"
                required>
        </div>
        <div class="form-group">
            <label class="form-label">Área de Conhecimento</label>
            <select name="area_conhecimento" class="form-input" required>
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
                    'Construção Civil'
                ];
                $area_atual = $prof['area_conhecimento'] ?? '';
                $area_encontrada = false;
                foreach ($areas_padronizadas as $ap):
                    $selected = (strcasecmp(trim($area_atual), trim($ap)) == 0);
                    if ($selected)
                        $area_encontrada = true;
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
            <label class="form-label">Cidade / Unidade</label>
            <input type="text" name="cidade" class="form-input"
                value="<?= (($prof['cidade'] ?? '') === '0') ? '' : htmlspecialchars($prof['cidade'] ?? '') ?>"
                placeholder="Ex: São José dos Campos">
        </div>
        <div class="form-group" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
            <div>
                <label class="form-label">Limite de Horas (Semanal)</label>
                <input type="number" name="weekly_hours_limit" class="form-input"
                    value="<?= htmlspecialchars($prof['weekly_hours_limit'] ?? '0') ?>" min="0">
                <small style="color:var(--text-muted); font-size:0.75rem;">20-40h Sugerido. 0 = Sem limite.</small>
            </div>
            <div>
                <label class="form-label">Limite de Horas (Mensal)</label>
                <input type="number" name="monthly_hours_limit" class="form-input"
                    value="<?= htmlspecialchars($prof['monthly_hours_limit'] ?? '0') ?>" min="0">
                <small style="color:var(--text-muted); font-size:0.75rem;">100-180h Sugerido. 0 = Sem limite.</small>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Tipo Contrato</label>
            <input type="text" name="tipo_contrato" class="form-input"
                value="<?= htmlspecialchars($prof['tipo_contrato'] ?? '') ?>" placeholder="Ex: Horista">
        </div>
        <!-- UI de Horários V4: Ultra Simples e Robusta -->
        <div class="form-group" style="margin-top: 30px;">
            <div class="availability-header">
                <label class="form-label"
                    style="font-size: 1.1rem; font-weight: 700; color: var(--text-color);">Definição de Horários de
                    Trabalho</label>
                <p style="color: var(--text-muted); font-size:0.85rem; margin-top: -3px;">Escolha os períodos e os dias
                    autorizados para este docente.</p>
            </div>

            <?php
            $grouped_slots = ['Manhã' => [], 'Tarde' => [], 'Noite' => []];
            if ($id) {
                $res_s = mysqli_query($conn, "SELECT * FROM horario_trabalho WHERE docente_id = '$id' ORDER BY id ASC");
                while ($row_s = mysqli_fetch_assoc($res_s)) {
                    $p_raw = mb_strtolower($row_s['periodo'] ?? '', 'UTF-8');
                    $p = (strpos($p_raw, 'man') !== false) ? 'Manhã' :
                        ((strpos($p_raw, 'tar') !== false) ? 'Tarde' :
                            ((strpos($p_raw, 'noi') !== false) ? 'Noite' : $row_s['periodo']));

                    if (isset($grouped_slots[$p]))
                        $grouped_slots[$p][] = $row_s;
                }
            }

            foreach (['Manhã', 'Tarde', 'Noite'] as $p_name):
                $icon = ($p_name == 'Manhã' ? 'fa-cog' : ($p_name == 'Tarde' ? 'fa-cloud-sun' : 'fa-moon'));
                $slots = $grouped_slots[$p_name];
                ?>
                <div class="card-v4">
                    <div class="card-header-v4">
                        <i class="fas <?= $icon ?>"></i> <?= mb_strtoupper($p_name) ?>
                    </div>

                    <div class="card-body-v4" id="container-v4-<?= $p_name ?>">
                        <?php foreach ($slots as $s_idx => $s_data):
                            $dias_pre = array_map('trim', explode(',', $s_data['dias'] ?? ''));
                            ?>
                            <div class="slot-row-v4" data-periodo="<?= $p_name ?>">
                                <input type="hidden" name="periodo[]" value="<?= $p_name ?>">

                                <div class="col-time-v4">
                                    <label>Horário</label>
                                    <select name="horario[]" class="input-v4" required>
                                        <option value="">-- : --</option>
                                        <?php
                                        $options = [
                                            'Manhã' => ['07:30 as 11:30', '08:00 as 12:00', '09:10 as 12:00'],
                                            'Tarde' => ['13:30 as 17:30', '13:00 as 17:00', '13:00 as 18:10'],
                                            'Noite' => ['18:00 as 22:00', '18:30 as 22:00', '19:00 as 22:00']
                                        ];
                                        foreach ($options[$p_name] as $opt): ?>
                                            <option value="<?= $opt ?>" <?= $s_data['horario'] == $opt ? 'selected' : '' ?>><?= $opt ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-days-v4">
                                    <label>Dias da Semana</label>
                                    <div class="circle-group-v4">
                                        <?php
                                        $days = [
                                            ['full' => 'Segunda-feira', 'init' => 'S'],
                                            ['full' => 'Terça-feira', 'init' => 'T'],
                                            ['full' => 'Quarta-feira', 'init' => 'Q'],
                                            ['full' => 'Quinta-feira', 'init' => 'Q'],
                                            ['full' => 'Sexta-feira', 'init' => 'S'],
                                            ['full' => 'Sábado', 'init' => 'S']
                                        ];
                                        foreach ($days as $d):
                                            if ($p_name === 'Noite' && $d['full'] === 'Sábado')
                                                continue;
                                            ?>
                                            <label class="circle-day-v4" title="<?= $d['full'] ?>">
                                                <input type="checkbox" name="dias_raw[]" value="<?= $d['full'] ?>"
                                                    <?= in_array($d['full'], $dias_pre) ? 'checked' : '' ?>>
                                                <span><?= $d['init'] ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div class="col-actions-v4">
                                    <button type="button" class="btn-del-v4" onclick="removeSlotV4(this)" title="Remover">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <button type="button" class="btn-add-v4" onclick="addSlotV4('<?= $p_name ?>')">
                        + NOVO HORÁRIO PARA <?= mb_strtoupper($p_name) ?>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>

        <script>
            function prepareV4Data() {
                document.querySelectorAll('.slot-row-v4').forEach((row, idx) => {
                    row.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                        cb.name = `dias_horario[${idx}][]`;
                    });
                });
            }
            document.querySelector('form').addEventListener('submit', prepareV4Data);



            // Auto-calculate monthly hours from weekly hours (Multiplier x3 as per example: 40 -> 120)
            const weeklyInput = document.querySelector('input[name="weekly_hours_limit"]');
            const monthlyInput = document.querySelector('input[name="monthly_hours_limit"]');

            if (weeklyInput && monthlyInput) {
                const updateBlockedStatus = () => {
                    const weeklyValue = parseFloat(weeklyInput.value) || 0;
                    const monthlyValue = parseFloat(monthlyInput.value) || 0;
                    const isZero = (weeklyValue === 0 && monthlyValue === 0);

                    document.querySelectorAll('.card-v4').forEach(card => {
                        if (isZero) {
                            card.classList.add('is-blocked');
                            card.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.disabled = true);
                        } else {
                            card.classList.remove('is-blocked');
                            card.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.disabled = false);
                        }
                    });
                };

                weeklyInput.addEventListener('input', () => {
                    const weeklyValue = parseFloat(weeklyInput.value) || 0;
                    if (weeklyValue > 0) {
                        monthlyInput.value = Math.round(weeklyValue * 3);
                    }
                    updateBlockedStatus();
                });
                monthlyInput.addEventListener('input', updateBlockedStatus);
                window.addEventListener('load', updateBlockedStatus);
            }


            function addSlotV4(p) {
                const area = document.getElementById(`container-v4-${p}`);
                if (area.querySelectorAll('.slot-row-v4').length >= 3) return alert('Máximo de 3 por período.');

                // If container is empty, we need a template row to clone.
                // We'll use a hidden template or just generate the HTML.
                // Let's use a template approach.
                const template = `
                    <div class="slot-row-v4" data-periodo="${p}">
                        <input type="hidden" name="periodo[]" value="${p}">
                        <div class="col-time-v4">
                            <label>Horário</label>
                            <select name="horario[]" class="input-v4" required>
                                <option value="">-- : --</option>
                                ${p === 'Manhã' ? '<option value="07:30 as 11:30">07:30 as 11:30</option><option value="08:00 as 12:00">08:00 as 12:00</option><option value="09:10 as 12:00">09:10 as 12:00</option>' :
                        p === 'Tarde' ? '<option value="13:30 as 17:30">13:30 as 17:30</option><option value="13:00 as 17:00">13:00 as 17:00</option><option value="13:00 as 18:10">13:00 as 18:10</option>' :
                            '<option value="18:00 as 22:00">18:00 as 22:00</option><option value="18:30 as 22:00">18:30 as 22:00</option><option value="19:00 as 22:00">19:00 as 22:00</option>'}
                            </select>
                        </div>
                        <div class="col-days-v4">
                            <label>Dias da Semana</label>
                            <div class="circle-group-v4">
                                ${['Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'].filter(d => !(p === 'Noite' && d === 'Sábado')).map(d => `
                                    <label class="circle-day-v4" title="${d}">
                                        <input type="checkbox" name="dias_raw[]" value="${d}">
                                        <span>${d[0]}</span>
                                    </label>
                                `).join('')}
                            </div>
                        </div>
                        <div class="col-actions-v4">
                            <button type="button" class="btn-del-v4" onclick="removeSlotV4(this)" title="Remover">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </div>
                `;
                area.insertAdjacentHTML('beforeend', template);
            }

            function removeSlotV4(btn) {
                const row = btn.closest('.slot-row-v4');
                row.remove();
            }
        </script>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Salvar Docente</button>
        </div>
    </form>
</div>

<style>
    /* Estilos V4 - Compatíveis com Modo Escuro */
    .card-v4 {
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        margin-bottom: 20px;
        overflow: hidden;
    }

    .card-header-v4 {
        background: var(--bg-hover);
        padding: 10px 15px;
        border-bottom: 1px solid var(--border-color);
        font-weight: 700;
        font-size: 0.85rem;
        color: #ed1c24;
        /* SENAI Red sempre legível */
        text-transform: uppercase;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .slot-row-v4 {
        padding: 15px 20px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: flex-end;
        gap: 30px;
    }

    .slot-row-v4:last-child {
        border-bottom: none;
    }

    .col-time-v4 {
        width: 180px;
    }

    .col-days-v4 {
        flex: 1;
    }

    .col-actions-v4 {
        width: 40px;
    }

    .slot-row-v4 label {
        display: block;
        font-size: 0.7rem;
        font-weight: 700;
        color: var(--text-muted);
        margin-bottom: 8px;
        text-transform: uppercase;
    }

    .input-v4 {
        width: 100%;
        border: 1px solid var(--border-color);
        border-radius: 4px;
        padding: 8px;
        font-size: 0.9rem;
        background: var(--bg-color);
        color: var(--text-color);
    }

    .circle-group-v4 {
        display: flex;
        gap: 8px;
    }

    .circle-day-v4 {
        cursor: pointer;
        user-select: none;
    }

    .circle-day-v4 input {
        display: none;
    }

    .circle-day-v4 span {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        border: 2px solid var(--border-color);
        border-radius: 50%;
        font-size: 0.8rem;
        font-weight: 800;
        color: var(--text-muted);
        transition: all 0.2s;
        background: var(--card-bg);
    }

    .circle-day-v4:hover span {
        border-color: #ed1c24;
        color: #ed1c24;
    }

    .circle-day-v4 input:checked+span {
        background: #2e7d32;
        /* Verde SENAI / Disponível */
        border-color: #2e7d32;
        color: #fff;
    }

    .circle-day-v4 input:disabled+span {
        background: var(--bg-hover);
        opacity: 0.5;
        cursor: not-allowed;
    }

    .is-blocked {
        opacity: 0.15;
        cursor: not-allowed;
        pointer-events: none;
    }

    .is-blocked span {
        background: var(--bg-hover);
        border-style: dotted;
    }

    .btn-del-v4 {
        background: none;
        border: none;
        color: var(--text-muted);
        cursor: pointer;
        padding-bottom: 8px;
        font-size: 1.1rem;
        transition: color 0.2s;
    }

    .btn-del-v4:hover {
        color: #ed1c24;
    }

    .btn-add-v4 {
        width: 100%;
        padding: 12px;
        background: var(--bg-hover);
        border: none;
        border-top: 1px solid var(--border-color);
        color: var(--text-muted);
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        cursor: pointer;
        transition: background 0.2s;
    }

    .btn-add-v4:hover {
        background: var(--border-color);
        color: #ed1c24;
    }

    @media (max-width: 768px) {
        .slot-row-v4 {
            flex-direction: column;
            align-items: stretch;
            gap: 15px;
        }

        .col-time-v4,
        .col-actions-v4 {
            width: 100%;
        }

        .btn-del-v4 {
            padding-bottom: 0;
            text-align: right;
        }
    }
</style>

<?php include __DIR__ . '/../components/footer.php'; ?>