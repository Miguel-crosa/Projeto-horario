/**
 * Relatório Mensal Detalhado de Professores
 * Projeto Horário - Miguel
 */

document.addEventListener('DOMContentLoaded', () => {
    let reportCurrentProfId = null;
    let reportCurrentProfName = "";
    let reportCurrentDate = new Date();
    const mesesNomes = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

    // 1. Abrir Modal de Seleção
    window.openReportSelectProfModal = function () {
        const modal = document.getElementById('modal-relatorio-select-prof');
        const modalReport = document.getElementById('modal-relatorio-mensal-detalhado');

        if (modal) {
            // Resetar estilos de animação que podem ter ficado travados
            modal.style.transform = 'translateX(0)';
            modal.style.opacity = '1';
            modal.classList.add('active');
            loadReportProfList();
        }

        if (modalReport) {
            modalReport.classList.remove('active');
            modalReport.style.transform = 'translateX(100%)';
            modalReport.style.opacity = '0';
        }
    };

    // 2. Carregar lista de professores para seleção
    function loadReportProfList(search = "") {
        const resultsArea = document.getElementById('report-prof-results');
        resultsArea.innerHTML = '<div style="text-align: center; padding: 20px;"><div class="spinner-border text-danger" role="status"></div></div>';

        // Busca básica - reutilizando API se houver, ou apenas filtrando via JS se for pequeno
        // Para garantir performance em bases grandes, faremos um fetch simples ou usaremos dados pré-carregados
        fetch('php/controllers/professores_process.php?action=list_active')
            .then(r => r.json())
            .then(data => {
                let filtered = data;
                if (search) {
                    const s = search.toLowerCase();
                    filtered = data.filter(p => p.nome.toLowerCase().includes(s) || (p.area_conhecimento && p.area_conhecimento.toLowerCase().includes(s)));
                }

                if (filtered.length === 0) {
                    resultsArea.innerHTML = '<p style="text-align:center; color: var(--text-muted); padding:20px;">Nenhum professor encontrado.</p>';
                    return;
                }

                let html = '<div style="display: flex; flex-direction: column; gap: 10px;">';
                filtered.forEach(p => {
                    html += `
                        <div class="prof-item-card" onclick="openDetailedReport(${p.id}, '${p.nome.replace(/'/g, "\\'")}')" 
                             style="padding: 12px 15px; background: rgba(0,0,0,0.05); border-radius: 10px; cursor: pointer; transition: all 0.2s; display: flex; justify-content: space-between; align-items: center; border: 1px solid transparent;">
                            <div>
                                <strong style="display: block; color: var(--text-color);">${p.nome}</strong>
                                <small style="color: var(--text-muted);">${p.area_conhecimento || 'Sem área'}</small>
                            </div>
                            <i class="fas fa-chevron-right" style="color: var(--primary-red); opacity: 0.5;"></i>
                        </div>`;
                });
                html += '</div>';
                resultsArea.innerHTML = html;

                // Add hover effects via JS or CSS
                const cards = resultsArea.querySelectorAll('.prof-item-card');
                cards.forEach(c => {
                    c.onmouseover = () => { c.style.background = 'rgba(0,0,0,0.1)'; c.style.borderColor = 'var(--primary-red)'; };
                    c.onmouseout = () => { c.style.background = 'rgba(0,0,0,0.05)'; c.style.borderColor = 'transparent'; };
                });
            });
    }

    // Listener para busca
    document.getElementById('report-prof-search')?.addEventListener('input', (e) => {
        loadReportProfList(e.target.value);
    });

    // 3. Abrir Relatório Detalhado (Modal 2)
    window.openDetailedReport = function (profId, profName) {
        reportCurrentProfId = profId;
        reportCurrentProfName = profName;
        
        // Fecha Modal 1 (Opcional, mas o usuário pediu sobreposta)
        // Se quiser sobreposta, mantenha. Se quiser substituir, remova o 'active' do Modal 1.
        
        const modalReport = document.getElementById('modal-relatorio-mensal-detalhado');
        document.getElementById('report-prof-name-label').textContent = profName;
        
        if (modalReport) {
            // Animação de Slide: O primeiro modal sai para a esquerda, o segundo entra pela direita
            const modalSelect = document.getElementById('modal-relatorio-select-prof');
            if (modalSelect) {
                modalSelect.style.transform = 'translateX(-100%)';
                modalSelect.style.opacity = '0';
                setTimeout(() => modalSelect.classList.remove('active'), 300);
            }
            
            modalReport.classList.add('active');
            modalReport.style.transform = 'translateX(0)';
            modalReport.style.opacity = '1';
            loadReportData();
        }
    };

    // Voltar para seleção
    window.backToReportSelect = function() {
        const modalReport = document.getElementById('modal-relatorio-mensal-detalhado');
        const modalSelect = document.getElementById('modal-relatorio-select-prof');
        
        if (modalReport) {
            modalReport.style.transform = 'translateX(100%)';
            modalReport.style.opacity = '0';
            setTimeout(() => modalReport.classList.remove('active'), 300);
        }
        
        if (modalSelect) {
            modalSelect.classList.add('active');
            setTimeout(() => {
                modalSelect.style.transform = 'translateX(0)';
                modalSelect.style.opacity = '1';
            }, 50);
        }
    };

    // 4. Carregar dados do relatório
    function loadReportData() {
        const tableBody = document.getElementById('report-table-body');
        tableBody.innerHTML = '<tr><td colspan="9" style="padding: 50px;"><div class="spinner-border text-danger" role="status"></div><p style="margin-top:10px;">Processando relatório...</p></td></tr>';

        const year = reportCurrentDate.getFullYear();
        const month = String(reportCurrentDate.getMonth() + 1).padStart(2, '0');
        const monthLabel = mesesNomes[reportCurrentDate.getMonth()] + ' ' + year;
        document.getElementById('report-month-label').textContent = monthLabel;

        const url = `php/controllers/agenda_api.php?action=get_docente_agenda&docente_id=${reportCurrentProfId}&month=${year}-${month}`;

        fetch(url)
            .then(r => r.json())
            .then(data => {
                renderReportTable(data.agendas || []);
            })
            .catch(err => {
                console.error('Erro ao carregar relatório:', err);
                tableBody.innerHTML = '<tr><td colspan="9" class="alert alert-danger">Erro ao carregar os dados.</td></tr>';
            });
    }

    // 5. Renderizar a Tabela
    function renderReportTable(agendas) {
        const tableBody = document.getElementById('report-table-body');
        const year = reportCurrentDate.getFullYear();
        const month = reportCurrentDate.getMonth();
        const lastDay = new Date(year, month + 1, 0).getDate();
        const diasSemanaNomes = ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'];

        let html = "";
        let totalMonthlySeconds = 0;

        for (let day = 1; day <= lastDay; day++) {
            const dateISO = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
            const dateObj = new Date(year, month, day);
            const dayOfWeekIdx = dateObj.getDay();
            const dayName = diasSemanaNomes[dayOfWeekIdx];

            // Buscar dados para este dia
            const dayEvents = agendas.filter(a => a.agenda_data === dateISO);
            const workSchedules = dayEvents.filter(a => a.type === 'WORK_SCHEDULE');
            const classes = dayEvents.filter(a => a.type !== 'WORK_SCHEDULE');
            const actualWork = classes.filter(a => a.type !== 'FERIADO' && a.type !== 'FERIAS');
            
            const isHoliday = classes.some(a => a.type === 'FERIADO');
            const isVacation = classes.some(a => a.type === 'FERIAS');
            const isSunday = dayOfWeekIdx === 0;


            // Deduplicação de classes para visualização nos turnos
            const uniqueClassesMap = new Map();
            actualWork.forEach(c => {
                const key = c.id ? `ID_${c.id}` : `SLOT_${c.turma_id}_${c.agenda_data}_${c.horario_inicio}`;
                if (!uniqueClassesMap.has(key)) {
                    uniqueClassesMap.set(key, c);
                }
            });
            const deduplicatedClasses = Array.from(uniqueClassesMap.values());

            // --- CÁLCULO DE HORAS ---
            // Conforme solicitado, o relatório deve considerar APENAS a jornada prevista (Bloco de Horários),
            // ignorando as horas de aulas reais para fins de somatório e exibição na coluna de horas.
            let dayScheduleSeconds = 0;
            if (!isHoliday && !isVacation) {
                // Deduplicação de períodos para evitar somas redundantes (ex: Manhã + Integral)
                const periods = workSchedules.map(s => s.periodo);
                const hasIntegral = periods.includes('Integral');
                const processedPeriods = new Set();

                workSchedules.forEach(s => {
                    if (!s.horario) return;

                    // Se tem Integral, ignora registros individuais de Manhã e Tarde
                    if (hasIntegral && (s.periodo === 'Manhã' || s.periodo === 'Tarde')) return;
                    
                    // Garante que processamos cada período apenas uma vez por dia
                    if (!processedPeriods.has(s.periodo)) {
                        const parts = s.horario.toLowerCase().split(/ as | até | - /);
                        if (parts.length >= 2) {
                            const start = parseTime(parts[0].trim());
                            const end = parseTime(parts[1].trim());
                            if (end > start) {
                                dayScheduleSeconds += (end - start);
                                processedPeriods.add(s.periodo);
                            }
                        }
                    }
                });
            }

            const dayEffectiveSeconds = dayScheduleSeconds;
            
            totalMonthlySeconds += dayEffectiveSeconds;
            const dayHoursDecimal = dayEffectiveSeconds / 3600;
            
            // Lógica de alerta (Ocupado vs Livre)
            // Se tem aula mas não tem jornada, continua mostrando as horas de aula no alerta, mas não soma no total?
            // Na verdade, o usuário quer ver o conflito.
            const hoursClass = ""; 
            const hoursClick = `onclick="window.location.href='php/views/professores_form.php?id=${reportCurrentProfId}'" title="Clique para editar jornada"`;

            // Renderizar Turnos
            const turnos = ['Manhã', 'Tarde', 'Noite'];
            let turnosHtml = "";

            turnos.forEach(t => {
                // Busca classe que REALMENTE pertence a este turno
                const classInTurno = deduplicatedClasses.find(c => {
                    // Se o período estiver explicitamente definido
                    if (c.periodo === t) return true;
                    
                    // Lógica baseada em horário real para evitar que Integral vaze para a Noite indevidamente
                    if (c.horario_inicio && c.horario_fim) {
                        const hIni = parseInt(c.horario_inicio.split(':')[0]);
                        const hFim = parseInt(c.horario_fim.split(':')[0]);
                        
                        if (t === 'Manhã' && hIni < 12) return true;
                        if (t === 'Tarde' && hIni >= 12 && hIni < 18) return true;
                        if (t === 'Noite' && (hIni >= 18 || hFim > 18)) return true;
                    }
                    
                    // Fallback para Integral (apenas Manhã e Tarde se não houver horário específico de noite)
                    if (c.periodo === 'Integral' && (t === 'Manhã' || t === 'Tarde')) return true;
                    
                    return false;
                });

                const scheduleInTurno = workSchedules.find(s => {
                    if (s.periodo === t) return true;
                    if (s.periodo === 'Integral' && (t === 'Manhã' || t === 'Tarde')) return true;
                    return false;
                });
                
                let cellClass = "";
                let entrada = "-";
                let saida = "-";

                if (isVacation) {
                    cellClass = "bg-vacation";
                } else if (isHoliday) {
                    cellClass = "bg-holiday";
                } else if (scheduleInTurno) {
                    // SÓ MOSTRA SE POSSUI JORNADA (BLOCO DE DISPONIBILIDADE)
                    // Definimos o horário base do bloco para exibição consistente
                    if (scheduleInTurno.horario) {
                        const parts = scheduleInTurno.horario.toLowerCase().split(/ as | até | - /);
                        if (parts.length >= 2) {
                            entrada = parts[0].trim().substring(0, 5);
                            saida = parts[1].trim().substring(0, 5);
                        } else {
                            entrada = scheduleInTurno.horario.substring(0, 5);
                            saida = "---";
                        }
                    }

                    if (classInTurno) {
                        // Ocupado dentro da jornada
                        cellClass = "bg-busy";
                    } else {
                        // Livre dentro da jornada
                        cellClass = "bg-free";
                    }
                } else {
                    // FORA DA JORNADA: Ignora aulas extras e mostra vazio
                    cellClass = "bg-unavailable";
                }

                turnosHtml += `
                    <td class="${cellClass}"><span class="time-box">${entrada}</span></td>
                    <td class="${cellClass}"><span class="time-box">${saida}</span></td>
                `;
            });

            // Lógica de cores da coluna DIA e HORAS (Simplificada: ou tem jornada ou não tem)
            let dayClass = "";
            let hoursExtraClass = "";
            if (isHoliday || isVacation) {
                dayClass = "bg-holiday";
            } else if (workSchedules.length > 0) {
                // Tem jornada: Verde se livre, Vermelho se ocupado
                const hasClassInAnyTurno = deduplicatedClasses.some(c => {
                    // Verifica se a aula cai em algum turno que tem jornada
                    return workSchedules.some(s => {
                        if (c.periodo === s.periodo) return true;
                        if (s.periodo === 'Integral' && (c.periodo === 'Manhã' || c.periodo === 'Tarde')) return true;
                        return false;
                    });
                });
                dayClass = hasClassInAnyTurno ? "bg-busy" : "bg-free";
            } else {
                dayClass = "bg-unavailable";
            }

            html += `
                <tr>
                    <td class="col-dia ${dayClass}">${day}</td>
                    <td class="${dayClass}">${dayName}</td>
                    <td class="${hoursClass} ${hoursExtraClass}" ${hoursClick} style="${hoursClick ? 'cursor:pointer;' : ''}">${dayHoursDecimal > 0 ? dayHoursDecimal.toFixed(1) + 'h' : '-'}</td>
                    ${turnosHtml}
                </tr>
            `;
        }

        tableBody.innerHTML = html;

        // Atualizar Resumo
        const totalHoursInt = Math.floor(totalMonthlySeconds / 3600);
        document.getElementById('summary-hours-int').textContent = totalHoursInt;
        document.getElementById('summary-obs').textContent = totalHoursInt > 0 ? "concluído" : "não concluído";
        document.getElementById('summary-obs').style.color = totalHoursInt > 0 ? "#4caf50" : "#f44336";
        document.getElementById('summary-total-time').textContent = formatSecondsToHHMMSS(totalMonthlySeconds);
    }

    // Helpers
    function parseTime(timeStr) {
        const parts = timeStr.split(':');
        return parseInt(parts[0]) * 3600 + parseInt(parts[1]) * 60;
    }

    function formatSecondsToHHMMSS(totalSeconds) {
        const h = Math.floor(totalSeconds / 3600);
        const m = Math.floor((totalSeconds % 3600) / 60);
        const s = totalSeconds % 60;
        return String(h).padStart(2, '0') + ":" + String(m).padStart(2, '0') + ":" + String(s).padStart(2, '0');
    }

    // Navegação
    document.getElementById('report-next-month')?.addEventListener('click', () => {
        reportCurrentDate.setMonth(reportCurrentDate.getMonth() + 1);
        loadReportData();
    });

    document.getElementById('report-prev-month')?.addEventListener('click', () => {
        reportCurrentDate.setMonth(reportCurrentDate.getMonth() - 1);
        loadReportData();
    });

    // --- Suporte a Swipe (Touch) ---
    let touchStartX = 0;
    let touchEndX = 0;
    const reportModalContent = document.querySelector('#modal-relatorio-mensal-detalhado .modal-content');

    if (reportModalContent) {
        reportModalContent.addEventListener('touchstart', e => {
            touchStartX = e.changedTouches[0].screenX;
        }, {passive: true});

        reportModalContent.addEventListener('touchend', e => {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        }, {passive: true});
    }

    function handleSwipe() {
        const threshold = 100;
        if (touchEndX < touchStartX - threshold) {
            // Swipe Left -> Próximo Mês
            reportCurrentDate.setMonth(reportCurrentDate.getMonth() + 1);
            loadReportData();
        }
        if (touchEndX > touchStartX + threshold) {
            // Swipe Right -> Mês Anterior
            reportCurrentDate.setMonth(reportCurrentDate.getMonth() - 1);
            loadReportData();
        }
    }
});
