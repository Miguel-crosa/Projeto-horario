<?php
/**
 * CONSOLIDATED MODALS - Projeto Horário Miguel
 * All modals used in the system are defined here.
 */
?>

<!-- 1. Simulation Modal (Dashboard) -->
<div class="modal-overlay" id="dashboard-simulation-modal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h3><i class="fas fa-vial" style="color: #2e7d32;"></i> Modo de Simulação (Seguro)</h3>
            <button class="modal-close" onclick="closeSimulationModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <p style="margin-bottom: 20px; font-size: 0.9rem; color: var(--text-muted);">Teste a viabilidade de uma nova
                turma sem gravar no banco de dados. Encontre recursos 100% disponíveis.</p>

            <form id="simulation-form" class="form-grid-simulation">
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                    <div class="form-group">
                        <label class="form-label">Selecionar Curso</label>
                        <select name="sim_curso_id" class="form-input" required>
                            <option value="">Escolha um curso registrado...</option>
                            <?php
                            $all_cursos_sim = mysqli_fetch_all(mysqli_query($conn, "SELECT id, nome, area FROM curso ORDER BY nome ASC"), MYSQLI_ASSOC);
                            foreach ($all_cursos_sim as $c): ?>
                                <option value="<?= $c['id'] ?>" data-area="<?= htmlspecialchars($c['area']) ?>">
                                    <?= htmlspecialchars($c['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Área de Conhecimento</label>
                        <select name="sim_area" class="form-input" required>
                            <option value="">Selecione a área...</option>
                            <?php
                            $areas_sim = mysqli_fetch_all(mysqli_query($conn, "SELECT DISTINCT area_conhecimento FROM docente WHERE ativo = 1 AND area_conhecimento IS NOT NULL AND area_conhecimento != '' ORDER BY area_conhecimento ASC"), MYSQLI_ASSOC);
                            foreach ($areas_sim as $al):
                                $ap = $al['area_conhecimento']; ?>
                                <option value="<?= htmlspecialchars($ap) ?>">
                                    <?= htmlspecialchars($ap) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                    <div class="form-group">
                        <label class="form-label">Data Início</label>
                        <input type="date" name="sim_data_inicio" class="form-input" required
                            value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Data Fim</label>
                        <input type="date" name="sim_data_fim" class="form-input" required
                            value="<?= date('Y-m-d', strtotime('+3 months')) ?>">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:15px;">
                    <label class="form-label">Dias da Semana</label>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <?php foreach (['Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'] as $d_sim): ?>
                            <label class="sim-checkbox-label">
                                <input type="checkbox" name="sim_dias[]" value="<?= $d_sim ?>">
                                <span class="sim-checkbox-text">
                                    <?= substr($d_sim, 0, 3) ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:20px;">
                    <label class="form-label">Período</label>
                    <div class="sim-period-selector" style="display:flex; gap:10px;">
                        <button type="button" class="sim-period" data-inicio="07:30" data-fim="11:30">Manhã</button>
                        <button type="button" class="sim-period" data-inicio="19:30" data-fim="23:30">Noite</button>
                        <button type="button" class="sim-period" data-inicio="07:30" data-fim="17:30">Integral</button>
                    </div>
                </div>
                <div class="form-actions" style="grid-column: span 2; display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary"
                        style="background: #2e7d32; border-color: #1b5e20; width: 100%; justify-content: center; padding: 12px;">
                        <i class="fas fa-search"></i> Analisar Disponibilidade Estratégica
                    </button>
                </div>
            </form>

            <div id="simulation-loading" style="display: none; text-align: center; padding: 20px;">
                <div class="spinner-border text-success" role="status"></div>
                <p style="margin-top: 10px;">Cruzando dados de agenda e ocupação...</p>
            </div>

            <div id="simulation-results-dashboard" style="margin-top: 25px; display: none;">
                <div class="sim-strategic-header"
                    style="margin-bottom: 20px; padding: 15px; background: rgba(76, 175, 80, 0.1); border-radius: 10px; border: 1px solid rgba(76, 175, 80, 0.2);">
                    <h4 style="color: #81c784; margin-bottom: 5px;"><i class="fas fa-chart-line"></i> Resultado da
                        Análise</h4>
                    <p style="font-size: 0.85rem; margin: 0;">Exibindo os recursos com maior tempo livre na área
                        selecionada.</p>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <h4 style="margin-bottom: 12px; color: #81c784; font-size: 0.9rem;"><i
                                class="fas fa-chalkboard-teacher"></i> Docente Sugerido (Maior Tempo Livre)</h4>
                        <div id="sim-list-docentes" class="sim-resource-list"></div>
                    </div>
                    <div>
                        <h4 style="margin-bottom: 12px; color: #64b5f6; font-size: 0.9rem;"><i
                                class="fas fa-door-open"></i> Ambiente Sugerido</h4>
                        <div id="sim-list-ambientes" class="sim-resource-list"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 2. Select Others Modal (Docente/Ambiente) -->
<div class="modal-overlay" id="modal-selecionar-outros">
    <div class="modal-content" style="max-width: 650px;">
        <div class="modal-header">
            <h3><i class="fas fa-exchange-alt" style="color: #2e7d32; margin-right: 12px;"></i> <span
                    id="outros-modal-title">Selecionar Outro</span></h3>
            <button class="modal-close" onclick="closeOutrosModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div style="display:flex; gap:10px; margin-bottom:15px; flex-wrap:wrap; align-items:center;">
                <input type="text" id="outros-filtro-nome" class="form-input" placeholder="Buscar por nome..."
                    style="flex:1; min-width:180px;">
                <select id="outros-filtro-area" class="form-input" style="max-width:200px;">
                    <option value="">Todas as Áreas</option>
                    <?php
                    $areas_outros = mysqli_fetch_all(mysqli_query($conn, "SELECT DISTINCT area_conhecimento FROM docente WHERE ativo = 1 AND area_conhecimento IS NOT NULL AND area_conhecimento != '' ORDER BY area_conhecimento ASC"), MYSQLI_ASSOC);
                    foreach ($areas_outros as $al_outros):
                        $ap_outros = $al_outros['area_conhecimento']; ?>
                        <option value="<?= htmlspecialchars($ap_outros) ?>">
                            <?= htmlspecialchars($ap_outros) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select id="outros-filtro-ordem" class="form-input" style="max-width:200px;">
                    <option value="mais_livre">Maior Disponibilidade</option>
                    <option value="menos_livre">Menor Disponibilidade</option>
                </select>
            </div>
            <div id="outros-lista" style="max-height: 400px; overflow-y: auto;"></div>
        </div>
    </div>
</div>

<!-- 3. Select Professor Modal (Planejamento) -->
<div class="modal-overlay" id="modal-selecionar-professor">
    <div class="modal-content" style="max-width: 550px;">
        <div class="modal-header">
            <h3><i class="fas fa-user-check" style="color: var(--primary-red); margin-right: 12px;"></i> Selecionar
                Professor</h3>
            <button class="modal-close" id="modal-prof-close" onclick="closeModal('modal-selecionar-professor')"><i
                    class="fas fa-times"></i></button>
        </div>
        <div style="padding: 0 25px 10px;">
            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label">Buscar por nome</label>
                <input type="text" id="prof-search-input" class="form-input" placeholder="Digite o nome do professor..."
                    autocomplete="off">
            </div>
            <div class="form-group" style="margin-bottom: 12px;">
                <label class="form-label">Filtrar por área</label>
                <select id="prof-area-filter" class="form-input">
                    <option value="">Todas as áreas</option>
                    <?php
                    $all_docentes_modal = mysqli_fetch_all(mysqli_query($conn, "SELECT id, nome, area_conhecimento FROM docente WHERE ativo = 1 ORDER BY area_conhecimento ASC, nome ASC"), MYSQLI_ASSOC);
                    $areas_unique = array_unique(array_map(fn($d) => $d['area_conhecimento'] ?: 'Outros', $all_docentes_modal));
                    sort($areas_unique);
                    foreach ($areas_unique as $area_u): ?>
                        <option value="<?= htmlspecialchars($area_u) ?>">
                            <?= htmlspecialchars($area_u) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div id="prof-search-results" style="max-height: 350px; overflow-y: auto; padding: 0 25px 20px;">
            <!-- Dynamically populated -->
        </div>
    </div>
</div>

<!-- 4. Schedule/Turma Modal (Planejamento) -->
<div class="modal-overlay" id="modal-agendar-calendar">
    <div class="modal-content" style="max-width: 750px; padding: 0; border: none; overflow: hidden;">
        <div class="modal-header" style="padding: 20px 25px; background: var(--bg-hover); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; display: flex; align-items: center; gap: 12px; color: var(--text-color);">
                <i id="modal-cal-icon" class="fas fa-calendar-plus" style="color: var(--primary-red);"></i> 
                <span id="modal-cal-title">Salvar Turma</span>
            </h3>
            <button class="modal-close" id="modal-cal-close" onclick="closeModal('modal-agendar-calendar')" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; color: var(--text-muted);"><i class="fas fa-times"></i></button>
        </div>

        <div id="modal-cal-reservation-warning" style="display: none; background: rgba(255,179,0,0.1); color: #e65100; padding: 12px 25px; border-bottom: 1px solid rgba(255,179,0,0.2); font-size: 0.85rem; font-weight: 600;">
            <i class="fas fa-info-circle"></i> Você está criando uma <strong>Reserva</strong> (Agendamento Provisório).
        </div>

        <div class="modal-body-scroll" style="max-height: calc(100vh - 150px); overflow-y: auto; padding: 25px;">
            <div class="modal-info" style="margin-bottom: 20px; padding: 12px 15px; background: var(--bg-color); border-radius: 8px; border-left: 4px solid var(--primary-red);">
                <span style="font-size: 0.9rem; color: var(--text-color);">Professor Principal: <strong id="modal-cal-docente-nome" style="color: var(--primary-red);"></strong></span>
            </div>

            <form id="form-agendar-calendar">
                <input type="hidden" name="docente_id" id="modal-cal-docente-id">

                <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label class="form-label" style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px;">CURSO</label>
                        <select name="curso_id" id="modal-cal-curso-id" class="form-input">
                            <option value="">Selecione o curso...</option>
                            <?php
                            $cursos_ag = mysqli_fetch_all(mysqli_query($conn, "SELECT id, nome, carga_horaria_total, area, tipo FROM curso ORDER BY area ASC, nome ASC"), MYSQLI_ASSOC);
                            $grouped_cursos_ag = [];
                            foreach ($cursos_ag as $c_ag) {
                                $area_ag = $c_ag['area'] ?: 'Outros';
                                $grouped_cursos_ag[$area_ag][] = $c_ag;
                            }
                            foreach ($grouped_cursos_ag as $area_label => $lista_ag): ?>
                                <optgroup label="<?= htmlspecialchars(mb_strtoupper($area_label, 'UTF-8')) ?>">
                                    <?php foreach ($lista_ag as $c_ag): ?>
                                        <option value="<?= $c_ag['id'] ?>" data-area="<?= htmlspecialchars($c_ag['area'] ?? '') ?>"
                                            data-ch="<?= $c_ag['carga_horaria_total'] ?>">
                                            <?= htmlspecialchars($c_ag['nome']) ?> (<?= htmlspecialchars($c_ag['tipo'] ?? '') ?>) -
                                            <?= $c_ag['carga_horaria_total'] ?>h
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px;">SIGLA DA TURMA <span style="font-weight: normal; opacity: 0.7;">(Opcional)</span></label>
                        <input type="text" name="sigla" class="form-input" placeholder="Ex: TI-2026-123">
                    </div>
                </div>

                <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label class="form-label" style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px;">VAGAS</label>
                        <input type="number" name="vagas" class="form-input" value="32" min="1" max="100">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px;">CARGA HORÁRIA</label>
                        <input type="number" name="carga_horaria" id="modal-cal-ch" class="form-input" placeholder="0" min="1">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px;">LOCAL</label>
                        <input type="text" name="local" class="form-input" placeholder="Ex: Unidade SJC" value="Sede">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px;">AMBIENTE</label>
                    <select name="ambiente_id" id="modal-cal-ambiente-id" class="form-input" required>
                        <option value="">Selecione o ambiente...</option>
                        <?php
                        $ambientes_ag = mysqli_fetch_all(mysqli_query($conn, "SELECT id, nome, tipo FROM ambiente ORDER BY tipo ASC, nome ASC"), MYSQLI_ASSOC);
                        $grouped_amb_ag = [];
                        foreach ($ambientes_ag as $a_ag) {
                            $tipo_ag = $a_ag['tipo'] ?: 'Outros';
                            $grouped_amb_ag[$tipo_ag][] = $a_ag;
                        }
                        foreach ($grouped_amb_ag as $tipo_label => $lista_amb): ?>
                            <optgroup label="<?= htmlspecialchars($tipo_label) ?>">
                                <?php foreach ($lista_amb as $a_ag): ?>
                                    <option value="<?= $a_ag['id'] ?>" data-tipo="<?= htmlspecialchars($a_ag['tipo'] ?? '') ?>">
                                        <?= htmlspecialchars($a_ag['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="form-label" style="display: block; font-size: 0.9rem; color: var(--primary-red); font-weight: 800; border-bottom: 2px solid rgba(237,28,36,0.1); padding-bottom: 8px; margin-bottom: 15px; text-transform: uppercase;">
                        <i class="fas fa-chalkboard-teacher"></i> Corpo Docente (Até 4)
                    </label>
                    
                    <div id="modal-cal-selected-docentes-container" class="docentes-list-v4">
                        <!-- Preenchido via JavaScript -->
                    </div>

                    <button type="button" class="add-docente-btn-v4" id="btn-modal-cal-abrir-docentes" style="display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; padding: 12px; background: var(--bg-hover); border: 1px dashed var(--border-color); border-radius: 8px; color: var(--text-color); font-weight: 700; cursor: pointer; transition: all 0.2s;">
                        <i class="fas fa-plus-circle"></i> ADICIONAR DOCENTE
                    </button>
                    
                    <!-- Campos Ocultos para o Formulário -->
                    <input type="hidden" name="docente_id1" id="modal-cal-hidden-docente-1" value="">
                    <input type="hidden" name="docente_id2" id="modal-cal-hidden-docente-2" value="">
                    <input type="hidden" name="docente_id3" id="modal-cal-hidden-docente-3" value="">
                    <input type="hidden" name="docente_id4" id="modal-cal-hidden-docente-4" value="">
                </div>

                <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label class="form-label" style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px;">PERÍODO</label>
                        <select name="periodo" id="modal-cal-periodo" class="form-input" required>
                            <option value="">Selecione o período...</option>
                            <option value="Manhã">Manhã</option>
                            <option value="Tarde">Tarde</option>
                            <option value="Noite">Noite</option>
                            <option value="Integral">Integral</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px;">TIPO</label>
                        <select name="tipo" class="form-input">
                            <option value="Presencial">Presencial</option>
                            <option value="EAD">EAD</option>
                            <option value="Híbrido">Híbrido</option>
                        </select>
                    </div>
                </div>

                <div class="form-group-last" style="margin-bottom: 20px;">
                    <label class="form-label" style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px;">DIAS DA SEMANA</label>
                    <div class="dias-checkboxes" style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <?php
                        $dias_labels_ag = ['Seg' => 'Segunda-feira', 'Ter' => 'Terça-feira', 'Qua' => 'Quarta-feira', 'Qui' => 'Quinta-feira', 'Sex' => 'Sexta-feira', 'Sáb' => 'Sábado'];
                        foreach ($dias_labels_ag as $short_ag => $full_ag): ?>
                            <label class="dia-checkbox-label" style="flex: 1; min-width: 60px; height: 45px; border: 1px solid var(--border-color); border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; position: relative; background: var(--bg-color);">
                                <input type="checkbox" name="dias_semana[]" value="<?= $full_ag ?>" style="position: absolute; opacity: 0; cursor: pointer;">
                                <span class="dia-checkbox-text" style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted);"><?= $short_ag ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <label class="form-label" style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px;">DATA INÍCIO</label>
                        <input type="date" id="modal-cal-data-inicio" name="data_inicio" class="form-input" required>
                    </div>
                    <div>
                        <label class="form-label" style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px;">DATA FIM</label>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <input type="date" id="modal-cal-data-fim" name="data_fim" class="form-input" required
                                style="background: var(--bg-hover); cursor: not-allowed; flex: 1;" readonly>
                            <label style="display: flex; align-items: center; gap: 5px; font-size: 0.8rem; white-space: nowrap; cursor: pointer; color: var(--text-color);">
                                <input type="checkbox" id="modal-cal-calc-auto" checked style="width: 16px; height: 16px;">
                                Auto
                            </label>
                        </div>
                    </div>
                </div>
                <div id="modal-cal-data-fim-info" style="font-size: 0.82rem; color: var(--text-muted); margin-top: -15px; margin-bottom: 20px; font-weight: 500;"></div>

                <div class="form-grid" id="horario-fields" style="display: none; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <label class="form-label" style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px;">HORÁRIO INÍCIO</label>
                        <input type="time" name="horario_inicio" class="form-input" value="07:30" min="07:30" id="res_horario_inicio">
                    </div>
                    <div>
                        <label class="form-label" style="display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; letter-spacing: 0.5px;">HORÁRIO FIM</label>
                        <input type="time" name="horario_fim" class="form-input" value="11:30" id="res_horario_fim">
                    </div>
                </div>

                <div class="sim-toggle-container" style="margin-bottom: 20px; padding: 12px 15px; background: rgba(46, 125, 50, 0.05); border-radius: 10px; border: 1px solid rgba(46, 125, 50, 0.1);">
                    <label class="sim-toggle-label" style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" name="simulacao" id="simulacao-toggle" value="1" style="width: 18px; height: 18px;">
                        <span class="sim-toggle-text" style="font-size: 0.9rem; font-weight: 600; color: #2e7d32;">
                            <i class="fas fa-vial"></i> Modo Simulação (Verificar Conflitos)
                        </span>
                    </label>
                </div>

                <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                    <div id="admin-reservation-actions" style="display: none; margin-top: 15px; border-top: 1px dashed var(--border-color); padding-top: 20px; text-align: center;">
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 12px; font-weight: 600;">Ações de Administrador:</p>
                        <button type="button" id="btn-remover-reserva" class="btn"
                            style="background: #ef5350; color: white; width: 100%; border: none; padding: 12px; border-radius: 8px; font-weight: 700; cursor: pointer; transition: all 0.2s;">
                            <i class="fas fa-trash-alt" style="margin-right: 8px;"></i> Remover Reserva deste Dia
                        </button>
                    </div>
                <?php endif; ?>

                <div id="simulation-results" style="margin-top: 15px; display: none;"></div>

                <div class="form-actions" style="margin-top: 30px;">
                    <input type="hidden" name="is_reserva" id="modal-cal-is-reserva" value="0">
                    <button type="submit" id="btn-modal-cal-submit" class="btn btn-primary"
                        style="width: 100%; justify-content: center; padding: 15px; font-size: 1.05rem; font-weight: 800; background: var(--primary-red); border: none; border-radius: 10px; color: white; cursor: pointer; box-shadow: 0 4px 15px rgba(237,28,36,0.2); display: flex; align-items: center; gap: 10px; transition: all 0.3s;">
                        <i class="fas fa-calendar-check" style="font-size: 1.2rem;"></i> 
                        <span id="btn-modal-cal-text">Salvar Turma</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
    </div>
</div>

<!-- 5. Timeline Modal (Agenda Professores) -->
<div id="timelineModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 95%; width: 1200px;">
        <span class="close-modal" onclick="closeModal('timelineModal')">&times;</span>
        <div class="month-nav">
            <button class="month-btn" id="prev_month_btn"><i class="fas fa-chevron-left"></i></button>
            <h2 id="timeline_prof_name" style="margin: 0; min-width: 280px;">Timeline</h2>
            <button class="month-btn" id="next_month_btn"><i class="fas fa-chevron-right"></i></button>
        </div>
        <div id="calendar_render_area"></div>
    </div>
</div>

<!-- 6. Quick Schedule Modal (Agenda Professores) -->
<div id="scheduleModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-calendar-plus"></i> Agendar Período</h3>
            <button class="modal-close" onclick="closeModal('scheduleModal')"><i class="fas fa-times"></i></button>
        </div>

        <div class="modal-info">
            <p id="schedule_info" style="font-weight: 700; color: #2e7d32; margin: 0;"></p>
        </div>

        <form action="../controllers/planejamento_process.php" method="POST">
            <input type="hidden" name="is_quick" value="1">

            <div class="form-group">
                <label class="form-label"><i class="fas fa-chalkboard-teacher"></i> Professor</label>
                <select name="docente_id" id="form_prof_id" required class="modal-prof-select form-input">
                    <option value="">Selecione...</option>
                    <?php
                    $all_profs_modal = mysqli_fetch_all(mysqli_query($conn, "SELECT id, nome, area_conhecimento FROM docente WHERE ativo = 1 ORDER BY nome ASC"), MYSQLI_ASSOC);
                    foreach ($all_profs_modal as $ap_m): ?>
                        <option value="<?php echo $ap_m['id']; ?>"
                            data-especialidade="<?php echo htmlspecialchars($ap_m['area_conhecimento']); ?>">
                            <?php echo htmlspecialchars($ap_m['nome']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Data Inicial</label>
                    <input type="date" name="data_inicio" id="form_date_start" required class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Data Final</label>
                    <input type="date" name="data_fim" id="form_date_end" required class="form-input">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label"><i class="fas fa-calendar-week"></i> Dias da Semana</label>
                <div id="weekday_checkboxes" class="dias-checkboxes"
                    style="background: var(--bg-color); padding: 12px; border-radius: 10px;">
                    <?php
                    $wk_days_m = [1 => ['name' => 'Seg', 'checked' => true], 2 => ['name' => 'Ter', 'checked' => true], 3 => ['name' => 'Qua', 'checked' => true], 4 => ['name' => 'Qui', 'checked' => true], 5 => ['name' => 'Sex', 'checked' => true], 6 => ['name' => 'Sáb', 'checked' => false]];
                    foreach ($wk_days_m as $wd_num_m => $wd_info_m): ?>
                        <div id="weekday_card_<?php echo $wd_num_m; ?>" class="weekday-card"
                            onclick="toggleWeekdayCard(<?php echo $wd_num_m; ?>)">
                            <i class="fas fa-lock wc-lock-icon"></i>
                            <input type="checkbox" name="dias_semana[]" id="weekday_<?php echo $wd_num_m; ?>"
                                value="<?php echo $wd_num_m; ?>" <?php echo $wd_info_m['checked'] ? 'checked' : ''; ?>
                                style="margin: 0; cursor: pointer;" onclick="event.stopPropagation();">
                            <div class="wc-day-name">
                                <?php echo $wd_info_m['name']; ?>
                            </div>
                            <div id="weekday_turno_<?php echo $wd_num_m; ?>" class="wc-turno-row"></div>
                            <div id="weekday_count_<?php echo $wd_num_m; ?>" class="wc-count"></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div id="weekday_blocking_info"
                    style="display:none; margin-top: 10px; padding: 12px 16px; background: rgba(255, 152, 0, 0.06); border-radius: 10px; border-left: 4px solid #f9a825; font-size: 0.82rem; color: #e65100;">
                    <i class="fas fa-info-circle"></i> <span id="weekday_blocking_text"></span>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Turma / Curso</label>
                    <select name="turma_id" required class="form-input">
                        <option value="">Selecione...</option>
                        <?php
                        $turmas_m = mysqli_fetch_all(mysqli_query($conn, "SELECT t.id, t.sigla as nome FROM turma t ORDER BY t.sigla ASC"), MYSQLI_ASSOC);
                        foreach ($turmas_m as $t_m): ?>
                            <option value="<?php echo $t_m['id']; ?>">
                                <?php echo htmlspecialchars($t_m['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Ambiente</label>
                    <select name="ambiente_id" required class="form-input">
                        <option value="">Selecione...</option>
                        <?php
                        $salas_m = mysqli_fetch_all(mysqli_query($conn, "SELECT id, nome FROM ambiente ORDER BY nome ASC"), MYSQLI_ASSOC);
                        foreach ($salas_m as $s_m): ?>
                            <option value="<?php echo $s_m['id']; ?>">
                                <?php echo htmlspecialchars($s_m['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Horário Início</label>
                    <input type="time" name="horario_inicio" id="form_hora_inicio" value="08:00" required
                        class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Horário Fim</label>
                    <input type="time" name="horario_fim" id="form_hora_fim" value="12:00" required class="form-input">
                </div>
            </div>

            <div class="form-actions" style="margin-top: 30px; text-align: center;">
                <button type="submit" class="btn btn-primary"
                    style="width: 100%; justify-content: center; padding: 15px; font-size: 1rem;">
                    <i class="fas fa-check-double"></i> Confirmar
                </button>
            </div>
        </form>
    </div>
</div>

<?php // 7. Reservation Modal - Obsolete - Removed in refactoring ?>

<!-- 8. Teacher Monthly Summary Modal (Dashboard/Agenda) -->
<div id="teacherMonthlySummaryModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 95%; width: 1000px;">
        <div class="modal-header">
            <h3><i class="fas fa-calendar-alt" style="color: var(--primary-red);"></i> Resumo Mensal: <span
                    id="summary-prof-name"></span></h3>
            <button class="modal-close" onclick="closeModal('teacherMonthlySummaryModal')"><i
                    class="fas fa-times"></i></button>
        </div>
        <div class="month-nav-summary"
            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding: 10px; background: rgba(0,0,0,0.05); border-radius: 8px;">
            <button class="btn btn-secondary" id="btn-prev-month-summary" style="padding: 8px 15px;"><i
                    class="fas fa-chevron-left"></i> Mês Anterior</button>
            <h4 id="summary-current-month" style="margin: 0; font-weight: 700; text-transform: uppercase;"></h4>
            <button class="btn btn-secondary" id="btn-next-month-summary" style="padding: 8px 15px;">Próximo Mês <i
                    class="fas fa-chevron-right"></i></button>
        </div>
        <div id="summary-calendar-area" style="min-height: 400px; position: relative;">
            <!-- Renderizado via JS -->
        </div>
    </div>
</div>