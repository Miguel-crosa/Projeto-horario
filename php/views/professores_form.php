<?php
require_once __DIR__ . '/../configs/db.php';
include __DIR__ . '/../components/header.php';

if (!can_edit()) {
    header("Location: professores.php");
    exit;
}

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

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <form action="../controllers/professores_process.php" method="POST">
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-group">
            <label class="form-label">Nome Completo</label>
            <input type="text" name="nome" class="form-input" value="<?= htmlspecialchars($prof['nome'] ?? '') ?>"
                required>
        </div>
        <div class="form-group">
            <label class="form-label">Area de Conhecimento</label>
            <select name="area_conhecimento" class="form-input" required>
                <option value="">Selecione a area...</option>
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
        
        <!-- Sistema de Abas Contextuais -->
        <div class="tabs-v5" style="margin-top: 30px;">
            <button type="button" class="tab-btn active" onclick="switchTab('hoje')">
                <i class="fas fa-clock"></i> HOJE
            </button>
            <button type="button" class="tab-btn" onclick="switchTab('planejamento')">
                <i class="fas fa-calendar-plus"></i> PLANEJAMENTO
            </button>
            <button type="button" class="tab-btn" onclick="switchTab('historico')">
                <i class="fas fa-history"></i> HISTÓRICO
            </button>
        </div>

        <!-- UI de Horários: Grandes Blocos Sazonais -->
        <div class="form-group" style="margin-top: 20px; margin-bottom: 25px;" id="section-blocos-horario">
            <div class="availability-header" style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 20px; margin-bottom: 20px;">
                <div>
                    <label class="form-label" style="font-size: 1.1rem; font-weight: 700; color: var(--text-color);">Definição de Horários de Trabalho</label>
                    <p style="color: var(--text-muted); font-size:0.85rem; margin-top: -3px;">Crie blocos de vigência (ex: um bloco para 2026 e outro para 2027).</p>
                </div>
                <div class="search-date-container" style="background: var(--bg-hover); padding: 8px 15px; border-radius: 10px; border: 1px solid var(--border-color); display: flex; align-items: center; gap: 10px;">
                    <label style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">Buscar por Data:</label>
                    <input type="date" id="search-date-horario" class="input-v4" style="width: 140px; border: none; background: transparent; padding: 0;" onkeydown="if(event.key === 'Enter') { event.preventDefault(); findBlockByDate(this.value); }">
                    <i class="fas fa-search" style="color: var(--text-muted); cursor: pointer;" onclick="findBlockByDate(document.getElementById('search-date-horario').value)"></i>
                </div>
            </div>

            <div id="container-grandes-blocos">
                <?php
                $blocos_map = [];
                if ($id) {
                    $res_b = mysqli_query($conn, "SELECT * FROM horario_trabalho WHERE docente_id = '$id' ORDER BY data_inicio ASC, id ASC");
                    while ($row_b = mysqli_fetch_assoc($res_b)) {
                        $di = $row_b['data_inicio'] ?? '';
                        $df = $row_b['data_fim']    ?? '';
                        $key = $di . '|' . $df;
                        $blocos_map[$key]['data_inicio'] = $di;
                        $blocos_map[$key]['data_fim']    = $df;
                        $blocos_map[$key]['slots'][]     = $row_b;
                    }
                }

                if (empty($blocos_map)) {
                    $ano_default = date('Y');
                    $key_default = "{$ano_default}-01-01|{$ano_default}-12-31";
                    $blocos_map[$key_default] = [
                        'data_inicio' => "{$ano_default}-01-01",
                        'data_fim'    => "{$ano_default}-12-31",
                        'slots'       => []
                    ];
                }

                $hoje_dt = date('Y-m-d');
                $buffer_hoje = '';
                $buffer_planejamento = '';
                $buffer_historico = '';
                $bloco_idx = 0;
                $global_slot_idx = 0;

                foreach ($blocos_map as $bkey => $bloco):
                    $b_ini = $bloco['data_inicio'];
                    $b_fim = $bloco['data_fim'];
                    $b_desc = $bloco['slots'][0]['descricao'] ?? ''; 
                    $b_ano = !empty($b_ini) ? date('Y', strtotime($b_ini)) : date('Y');

                    $status_class = 'bloco-futuro';
                    $status_label = 'PLANEJADO';
                    $status_icon = 'fa-calendar-alt';
                    $tab_context = 'planejamento';
                    
                    if ($b_ini <= $hoje_dt && $b_fim >= $hoje_dt) {
                        $status_class = 'bloco-vigente';
                        $status_label = 'VIGENTE';
                        $status_icon = 'fa-check-circle';
                        $tab_context = 'hoje';
                    } elseif ($b_fim < $hoje_dt) {
                        $status_class = 'bloco-historico';
                        $status_label = 'HISTÓRICO';
                        $status_icon = 'fa-history';
                        $tab_context = 'historico';
                    }

                    $is_collapsed = ($status_class !== 'bloco-vigente');

                    $grouped_slots = ['Manhã' => [], 'Tarde' => [], 'Noite' => []];
                    foreach ($bloco['slots'] as $s_data) {
                        $p_raw = mb_strtolower($s_data['periodo'] ?? '', 'UTF-8');
                        $p = (strpos($p_raw, 'man') !== false) ? 'Manhã' :
                            ((strpos($p_raw, 'tar') !== false) ? 'Tarde' :
                                ((strpos($p_raw, 'noi') !== false) ? 'Noite' : $s_data['periodo']));
                        if (isset($grouped_slots[$p])) $grouped_slots[$p][] = $s_data;
                    }

                    ob_start();
                ?>
                    <div class="grande-bloco <?= $status_class ?> <?= $is_collapsed ? 'collapsed' : '' ?>" data-bloco-idx="<?= $bloco_idx ?>" data-tab-context="<?= $tab_context ?>">
                        <div class="grande-bloco-header" onclick="toggleBloco(this)">
                            <div class="grande-bloco-info">
                                <div class="grande-bloco-ano" onclick="event.stopPropagation(); openAnoModal(this)" style="cursor: pointer;" title="Trocar Ano">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span class="grande-bloco-ano-label"><?= $b_ano ?></span>
                                </div>
                                <div class="status-badge-v4">
                                    <i class="fas <?= $status_icon ?>"></i> <?= $status_label ?>
                                </div>
                            </div>
                            
                            <div class="grande-bloco-datas" onclick="event.stopPropagation()">
                                <div class="bloco-date-field">
                                    <label>Início da Vigência</label>
                                    <input type="date" name="bloco_data_inicio[<?= $bloco_idx ?>]" class="input-v4 bloco-ini-input" value="<?= xe($b_ini) ?>">
                                </div>
                                <span class="bloco-sep">até</span>
                                <div class="bloco-date-field">
                                    <label>Fim da Vigência</label>
                                    <input type="date" name="bloco_data_fim[<?= $bloco_idx ?>]" class="input-v4 bloco-fim-input" value="<?= xe($b_fim) ?>">
                                </div>
                            </div>

                            <div class="grande-bloco-actions" onclick="event.stopPropagation()">
                                <button type="button" class="btn-action-v4 btn-duplicate-bloco" onclick="duplicarBloco(this)" title="Duplicar Vigência">
                                    <i class="fas fa-copy"></i>
                                </button>
                                <button type="button" class="btn-action-v4 btn-del-bloco" onclick="removerBloco(this)" title="Remover Bloco">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                                <button type="button" class="btn-toggle-bloco" onclick="toggleBloco(this.closest('.grande-bloco-header'))" title="Expandir/Recolher">
                                    <i class="fas <?= $is_collapsed ? 'fa-chevron-down' : 'fa-chevron-up' ?>" style="pointer-events: none;"></i>
                                </button>
                            </div>
                        </div>

                        <div class="grande-bloco-body">
                            <?php foreach (['Manhã', 'Tarde', 'Noite'] as $p_name):
                                $icon  = ($p_name == 'Manhã') ? 'fa-cog' : (($p_name == 'Tarde') ? 'fa-cloud-sun' : 'fa-moon');
                                $slots = $grouped_slots[$p_name];
                            ?>
                                <div class="card-v4">
                                    <div class="card-header-v4">
                                        <i class="fas <?= $icon ?>"></i> <?= mb_strtoupper($p_name) ?>
                                    </div>
                                    <div class="card-body-v4" id="container-v4-<?= $bloco_idx ?>-<?= $p_name ?>">
                                        <?php 
                                        $slot_cnt = 0;
                                        foreach ($slots as $s_data):
                                            $dias_pre = array_map('trim', explode(',', $s_data['dias'] ?? ''));
                                        ?>
                                            <div class="slot-row-v4" data-periodo="<?= $p_name ?>">
                                                <input type="hidden" name="periodo[]" value="<?= $p_name ?>">
                                                <input type="hidden" name="bloco_idx[]" value="<?= $bloco_idx ?>">

                                                <div class="col-time-v4">
                                                    <label>Horário</label>
                                                    <select name="horario[]" class="input-v4">
                                                        <option value="">-- : --</option>
                                                        <?php
                                                        $options = [
                                                            'Manhã' => ['07:30 as 11:30', '07:30 as 12:00', '08:00 as 12:00', '09:10 as 12:00'],
                                                            'Tarde' => ['13:00 as 16:30', '13:00 as 17:30', '13:30 as 17:30', '13:00 as 17:00', '13:00 as 18:10'],
                                                            'Noite' => ['18:00 as 22:00', '18:30 as 22:30', '19:00 as 22:00', '19:00 as 23:00']
                                                        ];
                                                        foreach ($options[$p_name] as $opt): ?>
                                                            <option value="<?= $opt ?>" <?= $s_data['horario'] == $opt ? 'selected' : '' ?>><?= $opt ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>

                                                <div class="col-days-v4">
                                                    <label>Dias da Semana</label>
                                                    <div class="circle-group-v4">
                                                        <?php
                                                        $days = [
                                                            ['full' => 'Segunda-feira', 'init' => 'S'],
                                                            ['full' => 'Terça-feira',   'init' => 'T'],
                                                            ['full' => 'Quarta-feira',  'init' => 'Q'],
                                                            ['full' => 'Quinta-feira',  'init' => 'Q'],
                                                            ['full' => 'Sexta-feira',   'init' => 'S'],
                                                            ['full' => 'Sábado',        'init' => 'S'],
                                                        ];
                                                        foreach ($days as $d):
                                                            if ($p_name === 'Noite' && $d['full'] === 'Sábado') continue;
                                                        ?>
                                                            <label class="circle-day-v4" title="<?= $d['full'] ?>">
                                                                <input type="checkbox" name="dias_horario[<?= $global_slot_idx ?>][]"
                                                                    value="<?= $d['full'] ?>"
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
                                        <?php 
                                        $global_slot_idx++;
                                        endforeach; ?>
                                    </div>
                                    <button type="button" class="btn-add-v4" onclick="addSlotV4('<?= $p_name ?>', <?= $bloco_idx ?>)">
                                        + NOVO HORÁRIO PARA <?= mb_strtoupper($p_name) ?>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php
                    $html = ob_get_clean();
                    if ($tab_context === 'hoje') $buffer_hoje .= $html;
                    elseif ($tab_context === 'planejamento') $buffer_planejamento .= $html;
                    else $buffer_historico .= $html;

                    $bloco_idx++;
                endforeach;

                // Renderiza os containers por contexto
                echo '<div id="tab-content-hoje" class="tab-pane active">' . ($buffer_hoje ?: '<p style="color:var(--text-muted); text-align:center; padding: 40px;">Nenhuma vigência ativa para hoje.</p>') . '</div>';
                echo '<div id="tab-content-planejamento" class="tab-pane" style="display:none;">' . ($buffer_planejamento ?: '<p style="color:var(--text-muted); text-align:center; padding: 40px;">Nenhuma vigência planejada para o futuro.</p>') . '</div>';
                echo '<div id="tab-content-historico" class="tab-pane" style="display:none;">' . ($buffer_historico ?: '<p style="color:var(--text-muted); text-align:center; padding: 40px;">Nenhum histórico de vigência arquivado.</p>') . '</div>';
                ?>
            </div>

            <button type="button" class="btn-add-bloco" onclick="adicionarBloco()">
                <i class="fas fa-plus-circle"></i> + ADICIONAR NOVO BLOCO DE TEMPORADA
            </button>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Salvar Docente</button>
        </div>
    </form>
</div>

<!-- Modal Genérico para Alertas e Confirmações -->
<div class="modal-overlay" id="modal-custom-alert" style="z-index: 10001;">
    <div class="modal-content" style="max-width: 400px; text-align: center; border-top: 4px solid #ed1c24;">
        <div class="modal-body" style="padding: 30px;">
            <div id="custom-alert-icon" style="font-size: 3rem; color: #ed1c24; margin-bottom: 20px;">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <h3 id="custom-alert-title" style="margin-bottom: 10px; color: var(--text-color);">Atenção</h3>
            <p id="custom-alert-message" style="color: var(--text-muted); line-height: 1.5; margin-bottom: 25px;"></p>
            <div id="custom-alert-actions" style="display: flex; gap: 10px; justify-content: center;">
                <button type="button" class="btn btn-secondary" id="btn-alert-cancel" style="display: none;">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-alert-ok" style="background: #ed1c24; border-color: #ed1c24;">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Seleção de Ano -->
<div class="modal-overlay" id="modal-select-ano" style="z-index: 10002;">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header" style="justify-content: space-between; display: flex; align-items: center;">
            <h3><i class="fas fa-calendar-check" style="color: #ed1c24;"></i> Selecionar Ano</h3>
            <div style="display: flex; gap: 10px; align-items: center;">
                <button type="button" class="btn-nav-ano" onclick="changeAnoRange(-9)" title="Anterior"><i class="fas fa-chevron-left"></i></button>
                <button type="button" class="btn-nav-ano" onclick="changeAnoRange(9)" title="Próximo"><i class="fas fa-chevron-right"></i></button>
                <button class="modal-close" onclick="closeAnoModal()" style="margin-left: 10px;"><i class="fas fa-times"></i></button>
            </div>
        </div>
        <div class="modal-body" style="padding: 20px;">
            <p style="margin-bottom: 20px; color: var(--text-muted); font-size: 0.85rem;">Escolha o ano para este bloco (Disponível de 1926 até 2126).</p>
            <div id="ano-grid-container" class="ano-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;">
                <!-- Anos gerados via JS -->
            </div>
        </div>
    </div>
</div>

<script>
    // --- Sistema de Modais Customizados ---
    function toggleBloco(header) {
        const bloco = header.closest('.grande-bloco');
        const body = bloco.querySelector('.grande-bloco-body');
        const icon = header.querySelector('.btn-toggle-bloco i');
        
        const isCollapsed = bloco.classList.toggle('collapsed');
        
        if (icon) {
            icon.classList.toggle('fa-chevron-up', !isCollapsed);
            icon.classList.toggle('fa-chevron-down', isCollapsed);
        }
    }

    function findBlockByDate(dateStr) {
        if (!dateStr) return;
        
        // Validação de Ano: Evita disparar alerta enquanto o usuário ainda está digitando o ano (ex: 0002)
        const parts = dateStr.split('-');
        const year = parseInt(parts[0]);
        if (isNaN(year) || year < 1900) {
            // Se o ano for menor que 1900, provavelmente ainda está sendo preenchido.
            // Não mostramos alerta, apenas ignoramos.
            return;
        }
        
        // Ajuste para evitar problemas de timezone na conversão da string de data
        const searchDate = new Date(dateStr + 'T00:00:00');
        const blocks = document.querySelectorAll('.grande-bloco');
        let found = false;

        blocks.forEach(bloco => {
            const iniStr = bloco.querySelector('.bloco-ini-input').value;
            const fimStr = bloco.querySelector('.bloco-fim-input').value;
            
            if (!iniStr || !fimStr) return;
            
            const ini = new Date(iniStr + 'T00:00:00');
            const fim = new Date(fimStr + 'T00:00:00');

            if (searchDate >= ini && searchDate <= fim) {
                // 1. Troca para a aba correta (Hoje/Planejamento/Histórico)
                const tabContext = bloco.getAttribute('data-tab-context');
                if (tabContext) switchTab(tabContext);

                // 2. Garante que o bloco está expandido
                if (bloco.classList.contains('collapsed')) {
                    toggleBloco(bloco.querySelector('.grande-bloco-header'));
                }

                // 3. Scroll e Highlight
                setTimeout(() => {
                    bloco.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    bloco.style.transition = 'outline 0.3s';
                    bloco.style.outline = '3px solid #ed1c24';
                    bloco.style.outlineOffset = '4px';
                    
                    setTimeout(() => {
                        bloco.style.outline = 'none';
                    }, 3000);
                }, 300);
                
                found = true;
            }
        });

        if (!found) {
            showCustomAlert('Nenhum bloco de vigência encontrado para esta data específica.', 'Data não localizada');
        }
    }

    function toggleArchiveHistory() {
        // Redireciona para a aba de histórico agora que usamos sistema de abas
        switchTab('historico');
    }

    function switchTab(tabId) {
        // Atualiza botões
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        const activeBtn = document.querySelector(`.tab-btn[onclick="switchTab('${tabId}')"]`);
        if (activeBtn) activeBtn.classList.add('active');

        // Atualiza conteúdos
        document.querySelectorAll('.tab-pane').forEach(pane => pane.style.display = 'none');
        const activePane = document.getElementById(`tab-content-${tabId}`);
        if (activePane) {
            activePane.style.display = 'flex';
            activePane.style.flexDirection = 'column';
            activePane.style.gap = '20px';
        }
    }

    function duplicarBloco(btn) {
        const original = btn.closest('.grande-bloco');
        const clone = original.cloneNode(true);
        blocoCounter++;

        // Reset visual do clone
        clone.classList.remove('bloco-vigente', 'bloco-historico', 'collapsed');
        clone.classList.add('bloco-futuro');
        clone.setAttribute('data-bloco-idx', blocoCounter);
        clone.setAttribute('data-tab-context', 'planejamento'); // Novas clonagens vão para planejamento por padrão
        
        const badge = clone.querySelector('.status-badge-v4');
        badge.innerHTML = '<i class="fas fa-calendar-alt"></i> PLANEJADO';
        
        // Atualiza Inputs de Data com Lógica Inteligente
        const oldIniStr = original.querySelector('.bloco-ini-input').value;
        const oldFimStr = original.querySelector('.bloco-fim-input').value;
        
        const iniInput = clone.querySelector('.bloco-ini-input');
        const fimInput = clone.querySelector('.bloco-fim-input');
        iniInput.name = `bloco_data_inicio[${blocoCounter}]`;
        fimInput.name = `bloco_data_fim[${blocoCounter}]`;

        if (oldIniStr && oldFimStr) {
            const oldIni = new Date(oldIniStr + 'T00:00:00');
            const oldFim = new Date(oldFimStr + 'T00:00:00');
            
            // Nova Data Início = Fim Original + 1 dia
            const newIni = new Date(oldFim);
            newIni.setDate(newIni.getDate() + 1);
            
            // Calcula duração original para manter a mesma proporção
            const diffTime = Math.abs(oldFim - oldIni);
            const newFim = new Date(newIni.getTime() + diffTime);
            
            iniInput.value = newIni.toISOString().split('T')[0];
            fimInput.value = newFim.toISOString().split('T')[0];
            
            // Atualiza o label do ano no cabeçalho do clone
            const anoLabel = clone.querySelector('.grande-bloco-ano-label');
            if (anoLabel) anoLabel.textContent = newIni.getFullYear();
        }

        // Atualiza Slots
        const slots = clone.querySelectorAll('.slot-row-v4');
        slots.forEach((slot) => {
            slot.querySelector('input[name="bloco_idx[]"]').value = blocoCounter;
            
            const checkboxes = slot.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(cb => {
                cb.name = `dias_horario[${globalSlotCounter}][]`;
            });
            globalSlotCounter++;
        });

        // Atualiza botões de add slot
        const addBtns = clone.querySelectorAll('.btn-add-v4');
        addBtns.forEach(b => {
            const onClickStr = b.getAttribute('onclick');
            const newOnClick = onClickStr.replace(/,\s*\d+\)$/, `, ${blocoCounter})`);
            b.setAttribute('onclick', newOnClick);
        });

        // Inserir na aba de Planejamento e trocar para ela
        document.getElementById('tab-content-planejamento').appendChild(clone);
        switchTab('planejamento');
        
        setTimeout(() => {
            clone.scrollIntoView({ behavior: 'smooth', block: 'center' });
            clone.style.outline = '2px dashed #ed1c24';
            setTimeout(() => clone.style.outline = 'none', 2000);
        }, 300);
    }

    // Validação Manual para evitar erro de "An invalid form control is not focusable"
    document.querySelector('form').addEventListener('submit', function(e) {
        const blocos = document.querySelectorAll('.grande-bloco');
        let hasError = false;

        blocos.forEach(bloco => {
            const ini = bloco.querySelector('.bloco-ini-input');
            const fim = bloco.querySelector('.bloco-fim-input');
            const slots = bloco.querySelectorAll('.slot-row-v4');

            // Se o bloco tem slots, as datas de vigência tornam-se obrigatórias
            if (slots.length > 0) {
                if (!ini.value || !fim.value) {
                    hasError = true;
                    // Expande se estiver escondido
                    expandToField(ini);
                    ini.style.borderColor = '#ed1c24';
                }

                slots.forEach(slot => {
                    const horario = slot.querySelector('select[name="horario[]"]');
                    const checkboxes = slot.querySelectorAll('input[type="checkbox"]:checked');
                    
                    if (!horario.value || checkboxes.length === 0) {
                        hasError = true;
                        expandToField(horario);
                        horario.style.borderColor = '#ed1c24';
                    }
                });
            }
        });

        if (hasError) {
            e.preventDefault();
            showCustomAlert('Existem campos obrigatórios não preenchidos nos horários. Os blocos foram expandidos para correção.', 'Atenção');
        }
    });

    function expandToField(field) {
        const bloco = field.closest('.grande-bloco');
        const tabContext = bloco.getAttribute('data-tab-context');
        
        if (tabContext) switchTab(tabContext);

        if (bloco && bloco.classList.contains('collapsed')) {
            toggleBloco(bloco.querySelector('.grande-bloco-header'));
        }
        field.focus();
    }

    function showCustomAlert(msg, title = 'Atenção', isConfirm = false, onConfirm = null) {
        const modal = document.getElementById('modal-custom-alert');
        document.getElementById('custom-alert-message').textContent = msg;
        document.getElementById('custom-alert-title').textContent = title;
        
        const btnCancel = document.getElementById('btn-alert-cancel');
        const btnOk = document.getElementById('btn-alert-ok');
        
        btnCancel.style.display = isConfirm ? 'block' : 'none';
        
        const newBtnOk = btnOk.cloneNode(true);
        btnOk.parentNode.replaceChild(newBtnOk, btnOk);
        
        newBtnOk.onclick = () => {
            modal.classList.remove('active');
            if (onConfirm) onConfirm();
        };
        
        btnCancel.onclick = () => modal.classList.remove('active');
        modal.classList.add('active');
    }

    let _currentAnoBlocoIdx = null;
    let _modalBaseAno = new Date().getFullYear();

    function openAnoModal(btn) {
        const bloco = btn.closest('.grande-bloco');
        _currentAnoBlocoIdx = bloco.getAttribute('data-bloco-idx');
        
        // Pega o ano atual do label para começar por ele
        const labelAno = parseInt(bloco.querySelector('.grande-bloco-ano-label').textContent);
        
        if (labelAno >= 2022 && labelAno <= 2030) {
            _modalBaseAno = 2022;
        } else {
            const diff = labelAno - 2022;
            const blockNum = Math.floor(diff / 9);
            _modalBaseAno = 2022 + (blockNum * 9);
        }
        
        renderAnoGrid();

        // Posicionamento dinâmico baseado no elemento clicado
        const modal = document.getElementById('modal-select-ano');
        const modalContent = modal.querySelector('.modal-content');
        const rect = btn.getBoundingClientRect();
        
        // Ajusta a posição do conteúdo do modal para abrir sobre o botão
        modalContent.style.position = 'absolute';
        modalContent.style.left = (rect.left + window.scrollX) + 'px';
        modalContent.style.top = (rect.top + window.scrollY) + 'px';
        modalContent.style.transform = 'translate(-20%, -50%)'; // Centraliza um pouco melhor em relação ao clique
        
        modal.classList.add('active');
    }

    function renderAnoGrid() {
        const container = document.getElementById('ano-grid-container');
        container.innerHTML = '';
        
        for (let i = 0; i < 9; i++) {
            const ano = _modalBaseAno + i;
            if (ano > 2126) break;
            if (ano < 1926) continue;

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn-ano-option';
            btn.textContent = ano;
            btn.onclick = () => setAnoBloco(ano);
            btn.style = `padding: 15px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-hover); color: var(--text-color); font-weight: 700; cursor: pointer; transition: all 0.2s;`;
            container.appendChild(btn);
        }
    }

    function changeAnoRange(delta) {
        const nextBase = _modalBaseAno + delta;
        if (nextBase > 2126) return;
        if (nextBase < 1926 - 8) return;
        _modalBaseAno = nextBase;
        renderAnoGrid();
    }

    function closeAnoModal() {
        document.getElementById('modal-select-ano').classList.remove('active');
    }

    function setAnoBloco(ano) {
        const bloco = document.querySelector(`[data-bloco-idx="${_currentAnoBlocoIdx}"]`);
        if (bloco) {
            bloco.querySelector('.bloco-ini-input').value = `${ano}-01-01`;
            bloco.querySelector('.bloco-fim-input').value = `${ano}-12-31`;
            bloco.querySelector('.grande-bloco-ano-label').textContent = ano;
        }
        closeAnoModal();
    }

    let blocoCounter = <?= $bloco_idx ?>;
    let globalSlotCounter = <?= $global_slot_idx ?>;
    
    function prepareV4Data() {
        let slotGlobalIdx = 0;
        document.querySelectorAll('.slot-row-v4').forEach((row) => {
            row.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                cb.name = `dias_horario[${slotGlobalIdx}][]`;
            });
            const bIdx = row.querySelector('input[name="bloco_idx[]"]');
            if (bIdx) bIdx.name = `bloco_idx[${slotGlobalIdx}]`;
            slotGlobalIdx++;
        });
    }
    document.querySelector('form').addEventListener('submit', prepareV4Data);

    const weeklyInput  = document.querySelector('input[name="weekly_hours_limit"]');
    const monthlyInput = document.querySelector('input[name="monthly_hours_limit"]');
    if (weeklyInput && monthlyInput) {
        const updateBlockedStatus = () => {
            const w = parseFloat(weeklyInput.value) || 0;
            const m = parseFloat(monthlyInput.value) || 0;
            const isZero = (w === 0 && m === 0);
            document.querySelectorAll('.card-v4').forEach(card => {
                card.classList.toggle('is-blocked', isZero);
                card.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.disabled = isZero);
            });
        };
        weeklyInput.addEventListener('input', () => {
            const w = parseFloat(weeklyInput.value) || 0;
            if (w > 0) monthlyInput.value = Math.round(w * 3);
            updateBlockedStatus();
        });
        monthlyInput.addEventListener('input', updateBlockedStatus);
        window.addEventListener('load', updateBlockedStatus);
    }

    function getTimeOptions(p) {
        const map = {
            'Manhã':  '<option value="07:30 as 11:30">07:30 as 11:30</option><option value="07:30 as 12:00">07:30 as 12:00</option><option value="08:00 as 12:00">08:00 as 12:00</option><option value="09:10 as 12:00">09:10 as 12:00</option>',
            'Tarde':  '<option value="13:00 as 16:30">13:00 as 16:30</option><option value="13:00 as 17:30">13:00 as 17:30</option><option value="13:30 as 17:30">13:30 as 17:30</option><option value="13:00 as 17:00">13:00 as 17:00</option><option value="13:00 as 18:10">13:00 as 18:10</option>',
            'Noite':  '<option value="18:00 as 22:00">18:00 as 22:00</option><option value="18:30 as 22:30">18:30 as 22:30</option><option value="19:00 as 22:00">19:00 as 22:00</option><option value="19:00 as 23:00">19:00 as 23:00</option>'
        };
        return map[p] || '';
    }

    function getDaysHtml(p, bIdx, slotKey) {
        const days = ['Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'];
        return days.filter(d => !(p === 'Noite' && d === 'Sábado')).map(d => `
            <label class="circle-day-v4" title="${d}">
                <input type="checkbox" name="dias_horario[${slotKey}][]" value="${d}">
                <span>${d[0]}</span>
            </label>`).join('');
    }

    function addSlotV4(p, bIdx) {
        const area = document.getElementById(`container-v4-${bIdx}-${p}`);
        if (!area) return;
        if (area.querySelectorAll('.slot-row-v4').length >= 3) {
            return showCustomAlert('Máximo de 3 horários por período neste bloco.');
        }
        
        const html = `
            <div class="slot-row-v4" data-periodo="${p}">
                <input type="hidden" name="periodo[]" value="${p}">
                <input type="hidden" name="bloco_idx[]" value="${bIdx}">
                <div class="col-time-v4">
                    <label>Horário</label>
                    <select name="horario[]" class="input-v4">
                        <option value="">-- : --</option>
                        ${getTimeOptions(p)}
                    </select>
                </div>
                <div class="col-days-v4">
                    <label>Dias da Semana</label>
                    <div class="circle-group-v4">
                        ${['Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'].filter(d => !(p === 'Noite' && d === 'Sábado')).map(d => `
                            <label class="circle-day-v4" title="${d}">
                                <input type="checkbox" name="dias_horario[${globalSlotCounter}][]" value="${d}">
                                <span>${d[0]}</span>
                            </label>`).join('')}
                    </div>
                </div>
                <div class="col-actions-v4">
                    <button type="button" class="btn-del-v4" onclick="removeSlotV4(this)" title="Remover">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </div>`;
        area.insertAdjacentHTML('beforeend', html);
        globalSlotCounter++;
    }

    function removeSlotV4(btn) {
        btn.closest('.slot-row-v4').remove();
    }
    
    function removerBloco(btn) {
        if (document.querySelectorAll('.grande-bloco').length <= 1) {
            return showCustomAlert('É necessário manter pelo menos um bloco de horário.');
        }
        showCustomAlert('Remover este bloco de horário e todos os períodos dentro dele?', 'Confirmar Remoção', true, () => {
            btn.closest('.grande-bloco').remove();
        });
    }

    function adicionarBloco() {
        const bIdx = blocoCounter++;
        const anoAtual = new Date().getFullYear();
        const html = `
            <div class="grande-bloco" data-bloco-idx="${bIdx}">
                <div class="grande-bloco-header">
                    <div class="grande-bloco-ano" onclick="openAnoModal(this)" style="cursor: pointer;" title="Trocar Ano">
                        <i class="fas fa-calendar-alt"></i>
                        <span class="grande-bloco-ano-label">${anoAtual}</span>
                    </div>
                    <div class="grande-bloco-datas">
                        <div class="bloco-date-field">
                            <label>Início da Vigência</label>
                            <input type="date" name="bloco_data_inicio[${bIdx}]" class="input-v4 bloco-ini-input" value="${anoAtual}-01-01">
                        </div>
                        <span class="bloco-sep">até</span>
                        <div class="bloco-date-field">
                            <label>Fim da Vigência</label>
                            <input type="date" name="bloco_data_fim[${bIdx}]" class="input-v4 bloco-fim-input" value="${anoAtual}-12-31">
                        </div>
                    </div>
                    <div class="grande-bloco-actions">
                        <button type="button" class="btn-action-v4 btn-duplicate-bloco" onclick="duplicarBloco(this)" title="Duplicar Vigência">
                            <i class="fas fa-copy"></i>
                        </button>
                        <button type="button" class="btn-action-v4 btn-del-bloco" onclick="removerBloco(this)" title="Remover Bloco">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                        <button type="button" class="btn-toggle-bloco" onclick="toggleBloco(this.closest('.grande-bloco-header'))" title="Expandir/Recolher">
                            <i class="fas fa-chevron-up"></i>
                        </button>
                    </div>
                </div>
                <div class="grande-bloco-body">
                    ${['Manhã','Tarde','Noite'].map(p => {
                        const icon = p === 'Manhã' ? 'fa-cog' : (p === 'Tarde' ? 'fa-cloud-sun' : 'fa-moon');
                        return `
                        <div class="card-v4">
                            <div class="card-header-v4">
                                <i class="fas ${icon}"></i> ${p.toUpperCase()}
                            </div>
                            <div class="card-body-v4" id="container-v4-${bIdx}-${p}"></div>
                            <button type="button" class="btn-add-v4" onclick="addSlotV4('${p}', ${bIdx})">
                                + NOVO HORÁRIO PARA ${p.toUpperCase()}
                            </button>
                        </div>`;
                    }).join('')}
                </div>
            </div>`;
        document.getElementById('container-grandes-blocos').insertAdjacentHTML('beforeend', html);

        const novoBloco = document.querySelector(`[data-bloco-idx="${bIdx}"]`);
        
        // Listener para mudar o label do ano ao mudar a data de início
        novoBloco.querySelector('.bloco-ini-input').addEventListener('change', function() {
            const y = this.value ? new Date(this.value + 'T00:00:00').getFullYear() : anoAtual;
            novoBloco.querySelector('.grande-bloco-ano-label').textContent = y;
        });
    }

    // Listener para blocos carregados inicialmente
    document.querySelectorAll('.bloco-ini-input').forEach(inp => {
        inp.addEventListener('change', function() {
            const bloco = this.closest('.grande-bloco');
            if (bloco) {
                const y = this.value ? new Date(this.value + 'T00:00:00').getFullYear() : new Date().getFullYear();
                bloco.querySelector('.grande-bloco-ano-label').textContent = y;
            }
        });
    });
</script>

<style>
    /* Modais e Botões */
    .btn-ano-option:hover {
        border-color: #ed1c24 !important; color: #ed1c24 !important;
        background: rgba(237,28,36,0.05) !important; transform: translateY(-2px);
    }
    .btn-nav-ano {
        background: var(--bg-hover); border: 1px solid var(--border-color);
        color: var(--text-color); border-radius: 6px; width: 32px; height: 32px;
        cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center;
    }
    .btn-nav-ano:hover { color: #ed1c24; border-color: #ed1c24; }
    
    .modal-overlay {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.4); backdrop-filter: blur(2px);
        display: none; align-items: center; justify-content: center; z-index: 9999;
    }
    .modal-overlay.active { display: flex !important; opacity: 1; visibility: visible; }
    
    .modal-content {
        background: var(--card-bg); border-radius: 12px; border: 1px solid var(--border-color);
        box-shadow: 0 20px 40px rgba(0,0,0,0.4); width: 100%; max-width: 450px; position: relative;
    }

    #modal-custom-alert .modal-content, #modal-select-ano .modal-content {
        animation: modalScaleUp 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    @keyframes modalScaleUp { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }

    /* Layout de Blocos */
    #container-grandes-blocos { display: flex; flex-direction: column; gap: 24px; margin-bottom: 16px; }
    .grande-bloco { 
        border: 2px solid var(--border-color); border-radius: 12px; overflow: hidden;
        background: var(--card-bg); box-shadow: 0 4px 16px rgba(0,0,0,0.1); 
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    /* Estados de Status */
    .bloco-vigente { border-color: #2e7d32; box-shadow: 0 4px 20px rgba(46, 125, 50, 0.15); }
    .bloco-vigente .grande-bloco-header { background: rgba(46, 125, 50, 0.05); }
    .bloco-vigente .grande-bloco-ano { color: #2e7d32; }
    
    .bloco-futuro { border-color: #1565c0; }
    .bloco-futuro .grande-bloco-ano { color: #1565c0; }
    
    .bloco-historico { opacity: 0.8; border-color: var(--border-color); }
    .bloco-historico .grande-bloco-ano { color: var(--text-muted); }
    .bloco-historico.collapsed { opacity: 0.6; }
    .bloco-historico.collapsed:hover { opacity: 1; }

    .grande-bloco-header { 
        display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 12px 15px;
        background: var(--bg-hover); border-bottom: 2px solid var(--border-color);
        cursor: pointer; user-select: none;
    }
    .grande-bloco-header:hover { background: var(--border-color); }
    
    .grande-bloco-info { display: flex; align-items: center; gap: 15px; }
    
    .status-badge-v4 {
        font-size: 0.65rem; font-weight: 800; padding: 4px 10px; border-radius: 20px;
        letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px;
    }
    .bloco-vigente .status-badge-v4 { background: #2e7d32; color: #fff; }
    .bloco-futuro .status-badge-v4 { background: #1565c0; color: #fff; }
    .bloco-historico .status-badge-v4 { background: var(--text-muted); color: #fff; }

    .grande-bloco-ano { 
        display: flex; align-items: center; gap: 8px; font-weight: 900; 
        font-size: 1.4rem; color: #ed1c24; min-width: 80px; transition: 0.2s;
    }
    .grande-bloco-ano:hover { transform: scale(1.05); }
    
    .grande-bloco-datas { display: flex; align-items: flex-end; gap: 8px; }
    .bloco-date-field { display: flex; flex-direction: column; gap: 4px; }
    .bloco-date-field label { font-size: 0.65rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }
    .bloco-date-field .input-v4 { width: 150px; font-weight: 600; font-size: 0.85rem; padding: 6px 8px; }
    .bloco-sep { color: var(--text-muted); padding-bottom: 6px; font-size: 0.8rem; }
    
    .grande-bloco-actions { display: flex; align-items: center; gap: 10px; }
    .btn-toggle-bloco { 
        background: var(--bg-color); border: 1.5px solid var(--border-color); border-radius: 50%;
        width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
        color: var(--text-color); cursor: pointer; transition: 0.3s;
    }
    .grande-bloco:hover .btn-toggle-bloco { border-color: var(--text-muted); }
    
    .btn-del-bloco { background: none; border: 1.5px solid var(--border-color); border-radius: 8px; color: var(--text-muted); cursor: pointer; padding: 6px 10px; }
    .btn-del-bloco:hover { color: #ed1c16; border-color: #ed1c16; }
    
    .grande-bloco-body { 
        padding: 16px; display: flex; flex-direction: column; gap: 12px;
        transition: all 0.3s ease;
    }
    .grande-bloco.collapsed .grande-bloco-body { display: none; }
    .grande-bloco.collapsed { border-bottom-width: 2px; }

    .btn-add-bloco {
        width: 100%; padding: 14px; background: transparent; border: 2px dashed var(--border-color);
        border-radius: 12px; color: var(--text-muted); font-weight: 700; cursor: pointer;
    }
    .btn-add-bloco:hover { border-color: #ed1c24; color: #ed1c24; }

    /* Cards e Slots */
    .card-v4 { background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; }
    .card-header-v4 { background: var(--bg-hover); padding: 10px 15px; border-bottom: 1px solid var(--border-color); color: #ed1c24; font-weight: 700; display: flex; align-items: center; gap: 10px; }
    .slot-row-v4 { padding: 15px 20px; border-bottom: 1px solid var(--border-color); display: flex; align-items: flex-end; gap: 30px; }
    .slot-row-v4:last-child { border-bottom: none; }
    .col-time-v4 { width: 180px; }
    .col-days-v4 { flex: 1; }
    .input-v4 { width: 100%; border: 1px solid var(--border-color); border-radius: 4px; padding: 8px; background: var(--bg-color); color: var(--text-color); }
    .circle-group-v4 { display: flex; gap: 8px; }
    .circle-day-v4 input { display: none; }
    .circle-day-v4 span { 
        display: flex; align-items: center; justify-content: center; width: 34px; height: 34px;
        border: 2px solid var(--border-color); border-radius: 50%; font-weight: 800; color: var(--text-muted);
    }
    .circle-day-v4 input:checked + span { background: #2e7d32; border-color: #2e7d32; color: #fff; }
    .btn-del-v4 { background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.1rem; }
    .btn-del-v4:hover { color: #ed1c24; }
    .btn-add-v4 { width: 100%; padding: 12px; background: var(--bg-hover); border: none; cursor: pointer; font-weight: 700; color: var(--text-muted); }
    .btn-add-v4:hover { color: #ed1c24; }

    /* Sistema de Abas Contextuais */
    .tabs-v5 { display: flex; gap: 10px; border-bottom: 2px solid var(--border-color); padding-bottom: 0; margin-bottom: 20px; }
    .tab-btn {
        background: transparent; border: none; padding: 12px 25px; cursor: pointer;
        color: var(--text-muted); font-weight: 700; font-size: 0.85rem;
        border-bottom: 3px solid transparent; transition: all 0.2s;
        display: flex; align-items: center; gap: 8px;
    }
    .tab-btn:hover { color: var(--text-color); background: var(--bg-hover); }
    .tab-btn.active { color: #ed1c24; border-bottom-color: #ed1c24; }
    .tab-btn i { font-size: 1rem; }

    .btn-action-v4 {
        background: var(--bg-color); border: 1.5px solid var(--border-color); border-radius: 8px;
        width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
        color: var(--text-muted); cursor: pointer; transition: 0.3s;
    }
    .btn-action-v4:hover { border-color: #ed1c24; color: #ed1c24; background: var(--bg-hover); }
    .btn-duplicate-bloco i { font-size: 0.9rem; }

    /* Arquivo de Histórico */
    .btn-toggle-historico {
        width: 100%; padding: 16px 25px; background: var(--bg-hover);
        border: 2px solid var(--border-color); border-radius: 12px;
        color: var(--text-muted); font-weight: 700; cursor: pointer;
        display: flex; align-items: center; gap: 15px; transition: all 0.2s;
    }
    .btn-toggle-historico:hover { border-color: var(--text-muted); color: var(--text-color); }
    .btn-toggle-historico.active { background: var(--border-color); color: var(--text-color); border-bottom-left-radius: 0; border-bottom-right-radius: 0; }
    .btn-toggle-historico i.fa-archive { color: #ed1c24; font-size: 1.1rem; }
    
    #container-historico {
        border-top: none !important; border-top-left-radius: 0 !important; border-top-right-radius: 0 !important;
        animation: slideDown 0.3s ease-out;
    }
    @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

    /* Responsividade Professores Form */
    @media (max-width: 768px) {
        .card { padding: 15px !important; }
        .grande-bloco-header {
            flex-direction: column;
            align-items: flex-start !important;
            gap: 15px !important;
        }
        .grande-bloco-datas {
            flex-direction: column;
            align-items: stretch !important;
            width: 100%;
        }
        .bloco-date-field .input-v4 {
            width: 100% !important;
        }
        .bloco-sep {
            display: none;
        }
        .slot-row-v4 {
            flex-direction: column;
            align-items: stretch !important;
            gap: 15px !important;
            padding: 15px !important;
        }
        .col-time-v4 {
            width: 100% !important;
        }
        .circle-group-v4 {
            flex-wrap: wrap;
        }
        .modal-content {
            width: 95% !important;
            margin: 10px !important;
            max-height: 90vh;
            overflow-y: auto;
        }
    }
</style>

<?php include __DIR__ . '/../components/footer.php'; ?>
