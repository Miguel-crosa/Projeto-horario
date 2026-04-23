document.addEventListener('DOMContentLoaded', () => {
    const calendarEl = document.getElementById('professor-calendar');

    const apiBase = '../controllers/agenda_api.php';
    let currentDocente = null;
    let docenteAgendas = [];
    let mesesOcupados = [];
    window.calendarCurrentDate = new Date();
    // Para compatibilidade com a navegação do template
    Object.defineProperty(window, 'currentDate', {
        get: () => window.calendarCurrentDate,
        set: (v) => { window.calendarCurrentDate = v; }
    });
    let showingMonthPicker = false;

    // Estado do período vindo da URL ou global
    const urlParams = new URLSearchParams(window.location.search);
    window.calendarCurrentPeriod = urlParams.get('periodo') || null;
    const periodConfig = {
        'Manhã': { inicio: '07:30', fim: '11:30', min: '07:30', max: '11:30' },
        'Tarde': { inicio: '13:30', fim: '17:30', min: '13:30', max: '17:30' },
        'Noite': { inicio: '18:00', fim: '23:00', min: '18:00', max: '23:00' },
        'Integral': { inicio: '07:30', fim: '17:30', min: '07:30', max: '17:30' }
    };

    // Estado do modo de reserva
    let reservationMode = false;
    let reservedSlots = []; // array de strings dateISO
    let reservationsConfirmed = false; // após clicar em confirmar, as datas ficam amarelas

    // Exibição de estatísticas mensais
    let monthlyStatsEl = document.getElementById('calendar-monthly-stats');
    if (!monthlyStatsEl) {
        monthlyStatsEl = document.createElement('div');
        monthlyStatsEl.id = 'calendar-monthly-stats';
        monthlyStatsEl.style.cssText = 'margin: 15px 0; font-size: 0.85rem; color: var(--text-muted); text-align: right; background: var(--bg-color); padding: 12px; border-radius: 10px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm);';
        if (calendarEl) {
            calendarEl.parentNode.insertBefore(monthlyStatsEl, calendarEl.nextSibling);
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.toString().replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }
    function escapeAttr(s) { return s.replace(/"/g, '&quot;').replace(/'/g, '&#39;'); }

    const mesesNomes = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    const diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    const diasSemanaFull = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];

    const docenteSelect = document.getElementById('calendar-docente-select');

    // ============================================================
    // PROFESSOR SELECTION MODAL
    // ============================================================
    const profModal = document.getElementById('modal-selecionar-professor');
    const profSearchInput = document.getElementById('prof-search-input');
    const profAreaFilter = document.getElementById('prof-area-filter');
    const profSearchResults = document.getElementById('prof-search-results');
    const btnSelecionarProf = document.getElementById('btn-selecionar-professor');
    const btnProfLabel = document.getElementById('btn-prof-label');
    const docentes = window.__docentesData || [];

    if (btnSelecionarProf) {
        btnSelecionarProf.addEventListener('click', () => {
            profModal.classList.add('active');
            profSearchInput.value = '';
            profAreaFilter.value = '';
            renderProfessorResults();
            setTimeout(() => profSearchInput.focus(), 100);
        });
    }

    document.getElementById('modal-prof-close')?.addEventListener('click', () => profModal.classList.remove('active'));
    let profModalClickStart = null;
    profModal?.addEventListener('mousedown', e => profModalClickStart = e.target);
    profModal?.addEventListener('click', e => { if (e.target === profModal && e.target === profModalClickStart) profModal.classList.remove('active'); });

    profSearchInput?.addEventListener('input', renderProfessorResults);
    profAreaFilter?.addEventListener('change', renderProfessorResults);

    function renderProfessorResults() {
        const query = (profSearchInput?.value || '').toLowerCase().trim();
        const areaFilter = profAreaFilter?.value || '';

        let filtered = docentes.filter(d => {
            const nameMatch = !query || d.nome.toLowerCase().includes(query);
            const areaMatch = !areaFilter || (d.area_conhecimento || 'Outros') === areaFilter;
            return nameMatch && areaMatch;
        });

        if (filtered.length === 0) {
            profSearchResults.innerHTML = '<div style="text-align: center; padding: 30px; color: var(--text-muted); font-size: 0.9rem;"><i class="fas fa-search" style="font-size: 2rem; margin-bottom: 10px; display: block; opacity: 0.4;"></i>Nenhum professor encontrado.</div>';
            return;
        }

        let html = '';
        filtered.forEach(d => {
            const area = d.area_conhecimento || 'Outros';
            html += `<div class="prof-result-item" data-id="${d.id}" style="display: flex; align-items: center; justify-content: space-between; padding: 12px 15px; margin-bottom: 6px; border-radius: 10px; border: 1px solid var(--border-color); background: var(--bg-color); cursor: pointer; transition: all 0.2s;">
                <div>
                    <strong style="font-size: 0.95rem;">${escapeHtml(d.nome)}</strong>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 3px;">
                        <i class="fas fa-tag" style="margin-right: 5px;"></i>${escapeHtml(area)}
                    </div>
                </div>
                <button type="button" class="btn btn-primary" style="padding: 6px 14px; font-size: 0.8rem; white-space: nowrap;">
                    <i class="fas fa-check" style="margin-right: 5px;"></i>Selecionar
                </button>
            </div>`;
        });
        profSearchResults.innerHTML = html;

        // Adiciona listeners de clique
        profSearchResults.querySelectorAll('.prof-result-item').forEach(item => {
            item.addEventListener('click', function () {
                const id = this.dataset.id;
                const prof = docentes.find(d => String(d.id) === String(id));
                if (!prof) return;

                // Atualiza o select oculto
                if (docenteSelect) docenteSelect.value = id;
                if (btnProfLabel) btnProfLabel.textContent = prof.nome;
                if (btnSelecionarProf) {
                    btnSelecionarProf.style.background = '#2e7d32';
                    btnSelecionarProf.style.borderColor = '#1b5e20';
                }

                // Se não estivermos na visualização principal/SPA do calendário, devemos recarregar com o filtro de pesquisa
                const calendarElement = document.getElementById('professor-calendar');
                if (!calendarElement) {
                    const currentUrl = new URL(window.location.href);
                    currentUrl.searchParams.set('search', prof.nome);
                    currentUrl.searchParams.set('docente_id', prof.id);
                    currentUrl.searchParams.delete('page'); // Volta para a primeira página
                    window.location.href = currentUrl.toString();
                    return;
                }

                // Fecha o modal e carrega a agenda (modo SPA)
                profModal.classList.remove('active');
                if (typeof loadDocenteAgenda === 'function') loadDocenteAgenda(id);
            });
        });

        // Estilos ao passar o mouse
        profSearchResults.querySelectorAll('.prof-result-item').forEach(item => {
            item.addEventListener('mouseenter', () => {
                item.style.borderColor = 'var(--primary-red)';
                item.style.background = 'rgba(229, 57, 53, 0.05)';
            });
            item.addEventListener('mouseleave', () => {
                item.style.borderColor = 'var(--border-color)';
                item.style.background = 'var(--bg-color)';
            });
        });
    }



    // ============================================================
    // LOAD DOCENTE AGENDA
    // ============================================================
    if (docenteSelect) {
        docenteSelect.addEventListener('change', function () {
            const id = this.value;
            if (id) loadDocenteAgenda(id);
            else {
                currentDocente = null;
                docenteAgendas = [];
                mesesOcupados = [];
                renderCalendar();
            }
        });
    }

    window.loadDocenteAgenda = function (docenteId, focusDate = null) {
        let fetchMonth = '';
        if (focusDate) {
            const parts = focusDate.split('-');
            if (parts.length >= 2) fetchMonth = `${parts[0]}-${parts[1]}`;
        } else if (window.__currentMonth) {
            fetchMonth = window.__currentMonth;
        } else {
            const now = new Date();
            fetchMonth = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
        }

        const url = `${apiBase}?action=get_docente_agenda&docente_id=${docenteId}&month=${fetchMonth}`;
        return fetch(url)
            .then(r => r.json())
            .then(data => {
                currentDocente = data.docente;
                docenteAgendas = data.agendas || [];
                mesesOcupados = data.meses_ocupados || [];

                if (focusDate) {
                    const parts = focusDate.split('-');
                    if (parts.length >= 2) {
                        window.calendarCurrentDate = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, 1);
                    }
                } else if (window.__currentMonth) {
                    const parts = window.__currentMonth.split('-');
                    if (parts.length >= 2) {
                        window.calendarCurrentDate = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, 1);
                    }
                } else {
                    const now = new Date();
                    window.calendarCurrentDate = new Date(now.getFullYear(), now.getMonth(), 1);
                }
                showingMonthPicker = false;
                renderCalendar();
                updateAvailabilityBar();

                // Exibe a seção de disponibilidade
                const availSec = document.getElementById('availability-section');
                if (availSec) availSec.style.display = '';
                const btnAgendar = document.getElementById('btn-agendar-bar');
                if (btnAgendar) btnAgendar.style.display = '';

                // Preenche previamente o docente_id1 no modal de agendamento
                const d1 = document.querySelector('#form-agendar-calendar select[name="docente_id1"]');
                if (d1) d1.value = docenteId;
            })
            .catch(err => console.error(err));
    }

    // ============================================================
    // CALENDAR RENDERING
    // ============================================================
    window.renderCalendar = function () {
        const year = currentDate.getFullYear();
        const month = currentDate.getMonth();
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const startDayOfWeek = firstDay.getDay();
        const totalDays = lastDay.getDate();

        // Detecta regras de sobreposição de bloqueio
        const blockingOverlaps = {
            'Integral': ['Manhã', 'Tarde'],
            'Manhã': ['Integral'],
            'Tarde': ['Integral']
        };

        // Texto informativo - movido para um local sutil
        let infoHtml = '';
        // (We can remove the global month-wide otherPeriods check as we'll do it per-day if needed, 
        // or just keep the period badge in the header)


        // Badge do cabeçalho de período — garante alto contraste
        const periodBadge = window.calendarCurrentPeriod
            ? `<span style="font-size: 0.8rem; background: rgba(0,0,0,0.25); border: 1px solid rgba(255,255,255,0.4); color: #fff; padding: 4px 12px; border-radius: 20px; font-weight: 700; margin-left: 10px;">${window.calendarCurrentPeriod}</span>`
            : '';

        let html = `<div class="cal-header" style="position: relative;">
            <button class="cal-nav-btn" id="cal-prev" style="display:none;"><i class="fas fa-chevron-left"></i></button>
            <div style="text-align: center; width: 100%;">
                <h3 class="cal-month-title" id="cal-month-title" style="margin-bottom: 0;">${mesesNomes[month]} ${year}${periodBadge}</h3>
                ${infoHtml}
            </div>
            <button class="cal-nav-btn" id="cal-next" style="display:none;"><i class="fas fa-chevron-right"></i></button>
        </div>`;

        if (showingMonthPicker) html += renderMonthPicker(year);
        else {
            html += `<div class="cal-grid-wrapper" style="position: relative;">
                <div class="cal-grid">`;
            diasSemana.forEach(d => html += `<div class="cal-day-header">${d}</div>`);
            for (let i = 0; i < startDayOfWeek; i++) html += `<div class="cal-day empty"></div>`;
            for (let day = 1; day <= totalDays; day++) {
                const dateObj = new Date(year, month, day);
                const dayOfWeek = dateObj.getDay();
                const dayName = diasSemanaFull[dayOfWeek];
                const isToday = isSameDay(dateObj, new Date());
                const dateISO = formatISO(dateObj);
                const aulasNoDia = getAulasNoDia(dateObj, dayName);
                // Filtra WORK_SCHEDULE para detecção de conteúdo real
                const realEntries = aulasNoDia.filter(a => a.type !== 'WORK_SCHEDULE');
                const hasAula = realEntries.length > 0;
                const isSunday = dayOfWeek === 0;
                const isReservedDate = reservedSlots.includes(dateISO);

                const hasWorkSchedule = aulasNoDia.some(a => a.type === 'WORK_SCHEDULE');
                const hasRealAula = realEntries.some(a => a.type === 'AULA' || a.type === 'RESERVA' || a.type === 'RESERVA_LEGADO');
                const hasFeriado = realEntries.some(a => a.type === 'FERIADO' || a.type === 'FERIAS');
                let statusClass = '';
                if (hasFeriado) {
                    statusClass = ' feriado';
                } else if (hasRealAula) {
                    const hasReserved = realEntries.some(a => a.status === 'RESERVADO' || a.type === 'RESERVA');
                    const hasConfirmed = realEntries.some(a => a.type === 'AULA');
                    if (hasReserved && !hasConfirmed) statusClass = ' reservado';
                    else statusClass = ' has-aula';
                } else if (!hasWorkSchedule && !isSunday && currentDocente) {
                    // Se não tem horário de trabalho registrado para este período/dia
                    statusClass = ' off-schedule';
                }

                if (isReservedDate && !hasRealAula && !hasFeriado) statusClass = ' reservado';

                let classes = 'cal-day' + (isToday ? ' today' : '') + statusClass + (isSunday ? ' domingo' : '');

                // Tooltip: usar apenas entradas reais (exclui WORK_SCHEDULE)
                let tooltipContent = '';
                if (hasAula && currentDocente) {
                    // Deduplicar entradas do tooltip por chave única
                    const tooltipSeen = new Set();
                    const tooltipEntries = realEntries.filter(a => {
                        const key = `${a.type}-${(a.curso_nome || a.turma_nome || '').trim()}-${(a.horario_inicio || '').substring(0, 5)}-${(a.horario_fim || '').substring(0, 5)}`;
                        if (tooltipSeen.has(key)) return false;
                        tooltipSeen.add(key);
                        return true;
                    });

                    tooltipContent = `<strong>${escapeHtml(currentDocente.nome)}</strong><br><i class="far fa-calendar-alt"></i> ${fmtDate(dateObj)}<br>` + tooltipEntries.map(a => {
                        const isFeriado = a.type === 'FERIADO' || a.type === 'FERIAS';
                        const isReserva = a.status === 'RESERVADO' || a.type === 'RESERVA';
                        const statusLabel = isReserva ? ' [RESERVADO]' : '';
                        // Fallback para nome do curso/turma
                        const displayName = a.curso_nome || a.turma_nome || a.sigla || (isFeriado ? 'Feriado/Férias' : (isReserva ? 'Reserva' : 'Aula'));
                        let timeStr;
                        if (isFeriado) {
                            timeStr = 'Dia Inteiro';
                        } else if (a.horario_inicio && a.horario_fim) {
                            timeStr = `${a.horario_inicio.substring(0, 5)} - ${a.horario_fim.substring(0, 5)}`;
                        } else if (isReserva) {
                            timeStr = 'Reservado';
                        } else {
                            timeStr = a.periodo || 'Horário não definido';
                        }
                        const icon = isFeriado ? 'fas fa-umbrella-beach' : 'far fa-clock';
                        return `<i class="${icon}"></i> ${escapeHtml(displayName)} | ${timeStr}${statusLabel}`;
                    }).join('<br>');
                }

                let classTimesHtml = '';
                if (hasAula && currentDocente) {
                    // Se for feriado, mostramos apenas o feriado
                    const entriesToDisplay = hasFeriado
                        ? realEntries.filter(a => a.type === 'FERIADO' || a.type === 'FERIAS')
                        : realEntries;

                    // Deduplicar entradas de exibição na célula
                    const cellSeen = new Set();
                    const uniqueEntries = entriesToDisplay.filter(a => {
                        const name = a.curso_nome || a.turma_nome || a.sigla || '';
                        const key = `${a.type}-${name.trim()}-${(a.horario_inicio || '').substring(0, 5)}`;
                        if (cellSeen.has(key)) return false;
                        cellSeen.add(key);
                        return true;
                    });

                    classTimesHtml = uniqueEntries.map(a => {
                        const isRes = a.status === 'RESERVADO' || a.type === 'RESERVA';
                        const isFeriado = a.type === 'FERIADO' || a.type === 'FERIAS';
                        const displayName = a.curso_nome || a.turma_nome || a.sigla || (isFeriado ? 'Férias' : 'Reserva');
                        if (isFeriado) return `<span class="cal-class-time" style="color:#42a5f5; font-weight:800; font-size: 0.7rem;">${escapeHtml(displayName)}</span>`;

                        const color = isRes ? '#f57f17' : 'var(--primary-red)';
                        let timeRange = 'Reservado';
                        if (a.horario_inicio && a.horario_fim) {
                            timeRange = `${a.horario_inicio.substring(0, 5)}-${a.horario_fim.substring(0, 5)}`;
                        } else if (a.periodo) {
                            timeRange = `${a.periodo}`;
                        }
                        return `<span class="cal-class-time" style="color:${color};">${timeRange}</span>`;
                    }).join('');
                }

                // Verifica aulas em OUTROS períodos (para indicador visual)
                let otherPeriodDot = '';
                let conflictLabel = '';
                if (window.calendarCurrentPeriod && currentDocente && !isSunday) {
                    const allAulasDay = getAllAulasNoDia(dateObj, dayName);

                    // Identifica conflito específico para ESTE dia
                    const dayOtherPeriods = new Set();
                    allAulasDay.forEach(a => { if (a.periodo !== window.calendarCurrentPeriod) dayOtherPeriods.add(a.periodo); });

                    const dayConflicts = blockingOverlaps[window.calendarCurrentPeriod]
                        ? Array.from(dayOtherPeriods).filter(p => blockingOverlaps[window.calendarCurrentPeriod].includes(p))
                        : [];

                    if (dayConflicts.length > 0) {
                        conflictLabel = `<div style="background: rgba(229,57,53,0.15); color: #e53935; padding: 2px 4px; border-radius: 4px; font-size: 0.65rem; text-transform: lowercase; border: 1px solid rgba(229,57,53,0.2); backdrop-filter: blur(2px); margin-top: 2px; line-height: 1.1;">
                            período inválido
                        </div>`;
                    } else if (dayOtherPeriods.size > 0 && !hasAula) {
                        otherPeriodDot = `<span style="display:block; width:6px; height:6px; border-radius:50%; background:#ffb300; margin: 2px auto 0; opacity:0.8;" title="Aula em outro período"></span>`;
                        classes += ' has-other-period';
                    }
                }

                // Tenta encontrar o ID da turma ou reserva principal para este dia no período selecionado
                let entryId = null;
                let entryType = null;
                if (hasAula) {
                    const primary = realEntries.find(a => a.periodo === window.calendarCurrentPeriod) || realEntries[0];
                    if (primary) {
                        entryId = primary.id;
                        // Usa o tipo retornado pelo AgendaModel para decidir qual API chamar
                        if (primary.type === 'RESERVA' || primary.status === 'RESERVADO') {
                            entryType = 'reserva';
                        } else {
                            entryType = 'turma';
                        }
                    }
                }

                html += `<div class="${classes}" ${hasAula ? `data-tooltip="${escapeAttr(tooltipContent)}"` : ''} data-date="${dateISO}" ${entryId ? `data-entry-id="${entryId}" data-entry-type="${entryType}"` : ''}>
                    <span class="cal-day-num">${day}</span>
                    ${hasAula && currentDocente ? `<span class="cal-prof-name">${escapeHtml(currentDocente.nome.split(' ')[0])}</span>` : ''}
                    ${classTimesHtml}
                    ${otherPeriodDot}
                    ${conflictLabel}
                    ${isSunday ? '<span class="cal-sunday-label">Indisponível</span>' : ''}
                    ${statusClass.includes('off-schedule') ? '<span class="cal-sunday-label" style="background:none; color:var(--text-muted); opacity:0.7;">Indisponível</span>' : ''}
                    ${(!hasRealAula && !hasFeriado && !isSunday && !statusClass.includes('off-schedule') && currentDocente) ? '<span class="cal-available-label">Disponível</span>' : ''}
                </div>`;
            }
            html += `</div></div>`;
        }
        if (calendarEl) { calendarEl.innerHTML = html; }

        // Navegação
        document.getElementById('cal-prev')?.addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() - 1); showingMonthPicker = false; renderCalendar(); updateAvailabilityBar(); });
        document.getElementById('cal-next')?.addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() + 1); showingMonthPicker = false; renderCalendar(); updateAvailabilityBar(); });
        document.getElementById('cal-month-title')?.addEventListener('click', () => { showingMonthPicker = !showingMonthPicker; renderCalendar(); });
        document.querySelectorAll('.cal-month-item').forEach(item => item.addEventListener('click', function () { currentDate.setMonth(parseInt(this.dataset.month)); showingMonthPicker = false; renderCalendar(); updateAvailabilityBar(); }));

        // Manipuladores de clique nos dias
        document.querySelectorAll('.cal-day:not(.empty):not(.domingo)').forEach(day => {
            if (window.userIsProfessor) {
                day.style.cursor = 'default';
                return;
            }
            day.addEventListener('click', function (e) {
                // Bug fix: Se o horário/dia estiver indisponível (cinza), ignore o clique.
                if (this.classList.contains('off-schedule') || this.classList.contains('domingo')) {
                    console.log("Clique bloqueado: dia indisponível ou domingo.");
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }

                const dateISO = this.dataset.date;
                if (!dateISO) return;

                // 1. Se estiver no Modo Reserva (selecionando múltiplos dias)
                if (reservationMode && currentDocente) {
                    const isAlreadyReserved = docenteAgendas.some(a => a.status === 'RESERVADO' && a.data_inicio === dateISO);
                    const idx = reservedSlots.indexOf(dateISO);
                    if (idx >= 0) {
                        reservedSlots.splice(idx, 1);
                        this.classList.remove('reservado', 'reservado-remove');
                        if (isAlreadyReserved) this.classList.add('reservado');
                    } else {
                        reservedSlots.push(dateISO);
                        if (isAlreadyReserved) this.classList.add('reservado-remove');
                        else this.classList.add('reservado');
                    }
                    updateReservationCount();
                    return;
                }

                // 2. Identifica se é um dia "Livre" (verde) ou ocupado
                const entryId = this.dataset.entryId;
                const entryType = this.dataset.entryType;
                const isFree = !this.classList.contains('has-aula') && !this.classList.contains('reservado');

                if (isFree) {
                    // Lógica requerida: CRI apenas reserva. Admin escolhe.
                    if (window.userIsCRI && !window.userIsAdmin) {
                        // Força modo/modal de reserva para CRI
                        window.openCalendarScheduleModal(dateISO, dateISO, true);
                    } else {
                        // Menu de escolha do Admin/Gestor
                        window.showSchedulingChoiceMenu(dateISO, e);
                    }
                } else {
                    // Clicar em um slot ocupado ou reserva confirmada abre o modal padrão
                    window.openCalendarScheduleModal(dateISO, dateISO, entryType === 'reserva', entryId);
                }
            });
        });

        window.showSchedulingChoiceMenu = function (dateISO, event, profId = null) {
            // Se currentDocente não existir ou for diferente do profId, tenta sincronizar
            if (profId && (!currentDocente || String(currentDocente.id) !== String(profId))) {
                const found = (window.__docentesData || []).find(d => String(d.id) === String(profId));
                if (found) currentDocente = found;
                else currentDocente = { id: profId };
            }

            if (!currentDocente) return; // Guard: Professor deve estar selecionado ou resolvido

            // Remove qualquer menu existente
            const existing = document.getElementById('scheduling-choice-menu');
            if (existing) existing.remove();

            const menu = document.createElement('div');
            menu.id = 'scheduling-choice-menu';

            // Garante que o evento tenha coordenadas (fallback para redimensionamento/fake events)
            const x = event && event.clientX ? event.clientX : window.innerWidth / 2;
            const y = event && event.clientY ? event.clientY : window.innerHeight / 2;

            menu.style.cssText = `
            position: fixed;
            top: ${y}px;
            left: ${x}px;
            background: var(--card-bg);
            animation: fadeInScale 0.2s ease-out;
            z-index: 99999;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            border: 1px solid var(--border-color);
            padding: 5px;
            border-radius: 12px;
        `;

            // Animação CSS para o menu
            if (!document.getElementById('scheduling-menu-style')) {
                const style = document.createElement('style');
                style.id = 'scheduling-menu-style';
                style.innerHTML = `
                @keyframes fadeInScale {
                    from { opacity: 0; transform: scale(0.95); }
                    to { opacity: 1; transform: scale(1); }
                }
                .choice-btn {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    width: 100%;
                    padding: 10px 15px;
                    border: none;
                    background: transparent;
                    color: var(--text-color);
                    font-size: 0.85rem;
                    font-weight: 600;
                    border-radius: 8px;
                    cursor: pointer;
                    transition: all 0.2s;
                    text-align: left;
                }
                .choice-btn:hover {
                    background: var(--bg-hover);
                    color: var(--primary-red);
                }
                .choice-btn i {
                    font-size: 1rem;
                    width: 20px;
                    text-align: center;
                }
                .cal-day.off-schedule, .cal-day.domingo {
                    background: var(--bg-hover);
                    opacity: 0.6;
                    cursor: not-allowed !important;
                    pointer-events: none !important;
                }
                .cal-day.off-schedule .cal-day-num, .cal-day.domingo .cal-day-num {
                    color: var(--text-muted);
                }
            `;
                document.head.appendChild(style);
            }

            menu.innerHTML = `
            <button class="choice-btn" id="choice-reserva">
                <i class="fas fa-bookmark" style="color: #ffb300;"></i> Solicitar Reserva
            </button>
            <button class="choice-btn" id="choice-aula">
                <i class="fas fa-calendar-plus" style="color: var(--primary-red);"></i> Cadastrar Aula
            </button>
        `;

            document.body.appendChild(menu);

            // Ajusta a posição caso saia da tela
            const rect = menu.getBoundingClientRect();
            if (rect.right > window.innerWidth) menu.style.left = (window.innerWidth - rect.width - 20) + 'px';
            if (rect.bottom > window.innerHeight) menu.style.top = (window.innerHeight - rect.height - 20) + 'px';

            // Listeners
            document.getElementById('choice-reserva').onclick = () => {
                menu.remove();
                window.openCalendarScheduleModal(dateISO, dateISO, true);
            };
            document.getElementById('choice-aula').onclick = () => {
                menu.remove();
                window.openCalendarScheduleModal(dateISO, dateISO, false);
            };

            // Fecha ao clicar fora
            const closeMenu = (e) => {
                if (!menu.contains(e.target)) {
                    menu.remove();
                    document.removeEventListener('mousedown', closeMenu);
                }
            };
            document.addEventListener('mousedown', closeMenu);
        }

        if (typeof setupTooltips === 'function') setupTooltips();
        updateMonthlyStats();
    }

    // ============================================================
    // MODO RESERVA
    // ============================================================
    // Modo reserva antigo (desativado/simplificado)
    window.toggleReservationMode = function () {
        if (!currentDocente) {
            showNotification('Selecione um professor primeiro.', 'error');
            return;
        }
        if (typeof handleModoReservaClick === 'function') handleModoReservaClick();
    };

    window.cancelReservationMode = function () {
        if (typeof handleCancelarReservaClick === 'function') handleCancelarReservaClick();
    };

    window.confirmReservations = function () {
        // Detecta se estamos no "Modo Agenda" (Timeline/Blocos do agenda_professores.js)
        let isAgendaMode = typeof window.reservaModeActive !== 'undefined' && window.reservaModeActive;
        let selectedDates = [];
        let firstProfId = null;

        if (isAgendaMode && window.reservaSelectedDates && window.reservaSelectedDates.length > 0) {
            selectedDates = window.reservaSelectedDates.map(d => d.date);
            firstProfId = window.reservaSelectedDates[0].profId;
        } else {
            selectedDates = reservedSlots;
            firstProfId = currentDocente ? currentDocente.id : null;
        }

        if (!selectedDates || selectedDates.length === 0) {
            if (typeof showNotification === 'function') showNotification('Nenhum dia selecionado para reservar.', 'error');
            else alert('Nenhum dia selecionado para reservar.');
            return;
        }

        // Encontra datas mínima e máxima
        const sorted = [...selectedDates].sort();
        const startISO = sorted[0];
        const endISO = sorted[sorted.length - 1];

        // Garante que currentDocente esteja definido na lógica do calendar.js se tivermos um profId mas não estiver definido
        if (firstProfId && (!currentDocente || String(currentDocente.id) !== String(firstProfId))) {
            if (typeof window.loadDocenteAgenda === 'function') {
                // Se estivermos no modo Timeline, talvez não queiramos recarregar todo o calendário, mas PRECISAMOS do currentDocente para o modal funcionar.
                // Tentamos defini-lo a partir de window.__docentesData se possível para evitar um fetch.
                const prof = (window.__docentesData || []).find(d => String(d.id) === String(firstProfId));
                if (prof) {
                    currentDocente = prof;
                    // Também atualiza o select oculto para manter a consistência
                    if (docenteSelect) docenteSelect.value = firstProfId;
                } else {
                    // Alternativa usando fetch
                    window.loadDocenteAgenda(firstProfId).then(() => {
                        window.openCalendarScheduleModal(startISO, endISO, true);
                    });
                    return; // Espera pelo fetch
                }
            }
        }

        if (typeof window.openCalendarScheduleModal === 'function') {
            window.openCalendarScheduleModal(startISO, endISO, true);
        } else {
            alert("Erro: Modal de agendamento não encontrado.");
        }
    };

    window.batchRemoveReservations = function () {
        let isAgendaMode = typeof window.reservaModeActive !== 'undefined' && window.reservaModeActive;
        let selected = isAgendaMode ? window.reservaSelectedDates.map(d => d.date) : reservedSlots;
        let pId = isAgendaMode && window.reservaCurrentProfId ? window.reservaCurrentProfId : docenteSelect.value;

        if (!selected || selected.length === 0) {
            showNotification('Selecione os dias reservados que deseja remover.', 'error');
            return;
        }

        if (!confirm(`Deseja realmente remover ${selected.length} reserva(s) selecionada(s)?`)) return;

        const fd = new FormData();
        fd.append('action', 'remove_reservations_batch');
        fd.append('docente_id', pId);
        if (window.calendarCurrentPeriod) fd.append('periodo', window.calendarCurrentPeriod);
        selected.forEach(d => fd.append('dates[]', d));

        fetch(apiBase, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (isAgendaMode && typeof cancelReservaSelection === 'function') {
                        cancelReservaSelection();
                        location.reload();
                    } else {
                        reservationMode = false;
                        const btnModo = document.getElementById('btn-modo-reserva-unificado') || document.getElementById('btn-modo-reserva');
                        if (btnModo) btnModo.style.display = 'inline-flex';
                        document.getElementById('btn-confirmar-reserva').style.display = 'none';
                        const btnRemoverBatch = document.getElementById('btn-remover-selecionados');
                        if (btnRemoverBatch) btnRemoverBatch.style.display = 'none';
                        document.getElementById('btn-cancelar-reserva').style.display = 'none';

                        showNotification(data.message, 'success');
                        loadDocenteAgenda(pId);
                    }
                } else {
                    showNotification(data.message || 'Erro ao remover reservas.', 'error');
                }
            })
            .catch(() => showNotification('Erro de conexão ao remover reservas.', 'error'));
    };

    function updateReservationCount() {
        const btn = document.getElementById('btn-confirmar-reserva');
        if (btn) {
            btn.innerHTML = `<i class="fas fa-check"></i> Confirmar Reserva (${reservedSlots.length})`;
        }
        const btnRem = document.getElementById('btn-remover-selecionados');
        if (btnRem) {
            btnRem.innerHTML = `<i class="fas fa-trash-alt"></i> Remover Selecionados (${reservedSlots.length})`;
        }
    }

    function updateTeacherDropdowns() {
        const selects = document.querySelectorAll('.docente-turma-select');
        const selectedValues = Array.from(selects)
            .map(s => s.value)
            .filter(v => v !== "");

        selects.forEach(select => {
            const currentValue = select.value;
            Array.from(select.options).forEach(option => {
                if (option.value === "") return;
                // Se estiver selecionado em OUTRO dropdown, oculte-o
                const isSelectedElsewhere = selectedValues.includes(option.value) && option.value !== currentValue;
                option.hidden = isSelectedElsewhere;
                option.style.display = isSelectedElsewhere ? 'none' : '';
            });
        });
    }

    document.querySelectorAll('.docente-turma-select').forEach(select => {
        select.addEventListener('change', updateTeacherDropdowns);
    });

    // ============================================================
    // FUNÇÕES AUXILIARES
    // ============================================================
    function updateMonthlyStats() {
        if (!docenteAgendas || !currentDate) return;
        const viewMonth = currentDate.getMonth();
        const viewYear = currentDate.getFullYear();
        const lastDay = new Date(viewYear, viewMonth + 1, 0).getDate();

        let occupiedDaysCount = 0;
        for (let day = 1; day <= lastDay; day++) {
            const dateObj = new Date(viewYear, viewMonth, day);
            const dayOfWeek = dateObj.getDay();
            if (dayOfWeek === 0) continue; // Pular Domingos

            const dayName = diasSemanaFull[dayOfWeek];
            const aulas = getAulasNoDia(dateObj, dayName);
            const hasOccupancy = aulas.some(a => a.type !== 'WORK_SCHEDULE');
            if (hasOccupancy) {
                occupiedDaysCount++;
            }
        }

        const mStats = document.getElementById('calendar-monthly-stats');
        if (mStats) {
            const periodText = window.calendarCurrentPeriod ? ` (Período: ${window.calendarCurrentPeriod})` : '';
            mStats.innerHTML = `<i class="fas fa-info-circle" style="color:var(--primary-color);"></i> <strong>${mesesNomes[viewMonth]} ${viewYear}${periodText}</strong>: ${occupiedDaysCount} dia(s) ocupado(s).`;
        }
    }

    function formatISO(date) {
        return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
    }

    function renderMonthPicker(year) {
        let html = `<div class="cal-month-picker">`;
        mesesNomes.forEach((m, i) => {
            const isActive = i === currentDate.getMonth();
            const mesChave = year + '-' + String(i + 1).padStart(2, '0');
            const temAula = mesesOcupados.includes(mesChave);

            // Verifica aulas em períodos específicos neste mês
            let periodInfo = '';
            if (currentDocente && window.calendarCurrentPeriod) {
                const monthStart = new Date(year, i, 1);
                const monthEnd = new Date(year, i + 1, 0);
                let hasCurrentPeriod = false;
                let monthOtherPeriods = new Set();

                for (let d = 1; d <= monthEnd.getDate(); d++) {
                    const dd = new Date(year, i, d);
                    if (dd.getDay() === 0) continue;
                    const dn = diasSemanaFull[dd.getDay()];
                    const allAulas = getAllAulasNoDia(dd, dn);
                    allAulas.forEach(a => {
                        if (a.periodo === window.calendarCurrentPeriod) hasCurrentPeriod = true;
                        else monthOtherPeriods.add(a.periodo);
                    });
                }

                if (hasCurrentPeriod) {
                    periodInfo = `<span style="display:block; font-size:0.6rem; color:#4caf50; margin-top:2px;"><i class="fas fa-check-circle"></i> ${window.calendarCurrentPeriod}</span>`;
                }
                if (monthOtherPeriods.size > 0) {
                    const otherText = Array.from(monthOtherPeriods).join(', ');
                    periodInfo += `<span style="display:block; font-size:0.55rem; color:#ffb300; margin-top:1px;" title="Possui aulas em: ${otherText}"><i class="fas fa-exclamation-circle"></i> Outros períodos</span>`;
                }
            }

            const dotClass = temAula ? 'month-dot-busy' : 'month-dot-free';
            html += `<div class="cal-month-item ${isActive ? 'active' : ''}" data-month="${i}">${m}${currentDocente ? `<span class="month-dot ${dotClass}"></span>` : ''}${periodInfo}</div>`;
        });
        return html + `</div>`;
    }

    function getAulasNoDia(dateObj, dayName) {
        if (!docenteAgendas.length) return [];
        const periodOrder = { 'Manhã': 1, 'Tarde': 2, 'Noite': 3, 'Integral': 4 };

        const filtered = docenteAgendas.filter(a => {
            // Filtragem por Professor: Garante que só mostre dados do professor atual
            if (a.docente_id && currentDocente && String(a.docente_id) !== String(currentDocente.id)) {
                return false;
            }

            // Se já tiver uma data fixa (como feriado ou reserva individual)
            if (a.agenda_data) {
                const sameDate = a.agenda_data === formatISO(dateObj);
                if (!sameDate) return false;
            } else {
                // Se for recorrente (turma)
                const agDateStart = new Date(a.data_inicio + 'T00:00:00');
                const agDateEnd = new Date(a.data_fim + 'T00:00:00');
                const dateMatches = (dateObj >= agDateStart && dateObj <= agDateEnd && a.dia_semana === dayName);
                if (!dateMatches) return false;
            }

            // Se o filtro de período estiver ativo, mostre apenas as aulas correspondentes
            // Feriados e Férias são mostrados em qualquer período (bloqueio total)
            if (a.type === 'FERIADO' || a.type === 'FERIAS') return true;

            if (window.calendarCurrentPeriod) {
                if (window.calendarCurrentPeriod === 'Integral') {
                    return a.periodo === 'Manhã' || a.periodo === 'Tarde' || a.periodo === 'Integral';
                }
                return a.periodo === window.calendarCurrentPeriod;
            }
            return true;
        });

        // Deduplicação visual: Evitar o mesmo curso/turma no mesmo horário/período
        const seen = new Set();
        return filtered.filter(a => {
            const hIni = (a.horario_inicio || '').substring(0, 5);
            const hFim = (a.horario_fim || '').substring(0, 5);
            const period = (a.periodo || '').trim().toUpperCase();
            const name = (a.turma_nome || a.curso_nome || '').trim().toUpperCase();
            const date = formatISO(dateObj); // Consistent date for the specific day being rendered

            const key = `${date}-${hIni}-${hFim}-${period}-${name}`;
            if (seen.has(key)) return false;
            seen.add(key);
            return true;
        }).sort((a, b) => (periodOrder[a.periodo] || 99) - (periodOrder[b.periodo] || 99));
    }

    // Versão não filtrada — retorna TODAS as aulas independentemente do período
    function getAllAulasNoDia(dateObj, dayName) {
        if (!docenteAgendas.length) return [];
        const periodOrder = { 'Manhã': 1, 'Tarde': 2, 'Noite': 3, 'Integral': 4 };

        const filtered = docenteAgendas.filter(a => {
            // Filtragem por Professor: Garante que só mostre dados do professor atual
            if (a.docente_id && currentDocente && String(a.docente_id) !== String(currentDocente.id)) {
                return false;
            }

            if (a.agenda_data) {
                return a.agenda_data === formatISO(dateObj);
            }
            const agDateStart = new Date(a.data_inicio + 'T00:00:00');
            const agDateEnd = new Date(a.data_fim + 'T00:00:00');
            return dateObj >= agDateStart && dateObj <= agDateEnd && a.dia_semana === dayName;
        });

        // Deduplicação visual
        const seen = new Set();
        return filtered.filter(a => {
            const hIni = (a.horario_inicio || '').substring(0, 5);
            const hFim = (a.horario_fim || '').substring(0, 5);
            const period = (a.periodo || '').trim().toUpperCase();
            const name = (a.turma_nome || a.curso_nome || '').trim().toUpperCase();
            const date = formatISO(dateObj); // Use consistent date

            const key = `${date}-${hIni}-${hFim}-${period}-${name}`;
            if (seen.has(key)) return false;
            seen.add(key);
            return true;
        }).sort((a, b) => (periodOrder[a.periodo] || 99) - (periodOrder[b.periodo] || 99));
    }

    function setupTooltips() {
        const t = document.getElementById('cal-tooltip') || createTooltip();
        document.querySelectorAll('.cal-day[data-tooltip]').forEach(el => {
            el.addEventListener('mouseenter', e => { t.innerHTML = el.dataset.tooltip; t.style.display = 'block'; posTooltip(t, e); });
            el.addEventListener('mousemove', e => posTooltip(t, e));
            el.addEventListener('mouseleave', () => t.style.display = 'none');
        });
    }

    function createTooltip() { const t = document.createElement('div'); t.id = 'cal-tooltip'; t.className = 'cal-tooltip'; document.body.appendChild(t); return t; }
    function posTooltip(t, e) { t.style.left = (e.clientX + 12) + 'px'; t.style.top = (e.clientY - 10) + 'px'; }

    function updateAvailabilityBar() {
        const barEl = document.getElementById('availability-bar');
        if (!barEl) return;
        const year = currentDate.getFullYear(), month = currentDate.getMonth();
        const lastDay = new Date(year, month + 1, 0).getDate();
        let totalUteis = 0, diasOcup = 0;
        for (let day = 1; day <= lastDay; day++) {
            const d = new Date(year, month, day);
            if (d.getDay() === 0) continue;
            totalUteis++;
            const aulas = getAulasNoDia(d, diasSemanaFull[d.getDay()]);
            const isOcupado = aulas.some(a => a.type !== 'WORK_SCHEDULE');
            if (isOcupado) diasOcup++;
        }
        const libres = totalUteis - diasOcup, pctOcup = totalUteis > 0 ? Math.round((diasOcup / totalUteis) * 100) : 0, pctLivre = 100 - pctOcup;
        barEl.innerHTML = `<div class="avail-bar-track">
            <div class="avail-bar-free" style="width: ${pctLivre}%">${pctLivre > 15 ? `${libres} livres (${pctLivre}%)` : ''}</div>
            <div class="avail-bar-busy" style="width: ${pctOcup}%">${pctOcup > 15 ? `${diasOcup} ocupados (${pctOcup}%)` : ''}</div>
        </div>`;
    }

    function fmtDate(d) { return String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0') + '/' + d.getFullYear(); }
    function isSameDay(a, b) { return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate(); }

    // Sincroniza window.currentDate com uso local
    const syncCurrentDate = () => {
        // Usamos um getter/setter acima, então variáveis locais dentro de funções podem precisar de cuidado
        // mas como costumamos usar 'currentDate' dentro de renderCalendar etc., 
        // substituir o 'let' local por 'window.currentDate' em todo o arquivo é o mais seguro.
    };

    // ============================================================
    // ABRIR MODAL DE AGENDAMENTO
    // ============================================================
    window.openCalendarScheduleModal = function (startISO = null, endISO = null, forceReserva = false) {
        const m = document.getElementById('modal-agendar-calendar');
        if (!m || !currentDocente) return;

        // Limpa alertas anteriores
        const alertBox = document.getElementById('form-alert-container');
        if (alertBox) alertBox.style.display = 'none';

        const isReserva = forceReserva || reservationMode || window.userIsCRI;

        // Prepara os dados para o formulário unificado
        const formData = {
            id: null,
            is_reserva: isReserva,
            docentes: [currentDocente],
            data_inicio: startISO,
            data_fim: endISO,
            periodo: window.calendarCurrentPeriod || 'Manhã',
            dias_semana: []
        };

        // Lógica de sugestão de datas caso não venha startISO
        if (!startISO) {
            const today = new Date();
            let suggestedDate = new Date(today);
            let firstWorkingDay = null;
            let found = false;

            const periodToMatch = window.calendarCurrentPeriod || 'Manhã';

            for (let i = 0; i < 60; i++) {
                const checkDate = new Date(today);
                checkDate.setDate(today.getDate() + i);
                const isoStr = checkDate.toISOString().slice(0, 10);
                const dow = checkDate.getDay();
                if (dow === 0) continue; // Pula domingo

                const dayName = diasSemanaFull[dow];

                // 1. Verifica se o professor trabalha neste dia/período
                const hasWorkSchedule = (window.docenteAgendas || []).some(a =>
                    a.type === 'WORK_SCHEDULE' &&
                    a.dia_semana === dayName &&
                    (a.periodo === periodToMatch || a.periodo === 'Integral')
                );

                if (hasWorkSchedule) {
                    // 2. Verifica se NÃO é feriado ou férias
                    const isBlocked = (window.docenteAgendas || []).some(a => {
                        const isTypeMatch = (a.type === 'HOLIDAY' || a.type === 'VACATION' || a.type === 'BLOCK');
                        const isDateMatch = a.agenda_data === isoStr || (isoStr >= a.data_inicio && isoStr <= a.data_fim);
                        return isTypeMatch && isDateMatch;
                    });

                    if (!isBlocked) {
                        // 3. Verifica se já não tem aula
                        const hasClass = (window.docenteAgendas || []).some(a =>
                            a.status === 'CONFIRMADO' &&
                            a.data === isoStr &&
                            (a.periodo === periodToMatch || a.periodo === 'Integral')
                        );

                        if (!hasClass) {
                            suggestedDate = checkDate;
                            firstWorkingDay = dayName;
                            found = true;
                            break;
                        }
                    }
                }
            }

            if (found) {
                formData.data_inicio = suggestedDate.toISOString().slice(0, 10);
                formData.dias_semana = [firstWorkingDay];
            } else {
                formData.data_inicio = today.toISOString().slice(0, 10);
                formData.dias_semana = ['Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira'];
            }
        } else {
            // Se tem data de início, descobre o dia da semana
            const dateObj = new Date(startISO + 'T00:00:00');
            const dayName = diasSemanaFull[dateObj.getDay()];
            formData.dias_semana = [dayName];
        }

        // Preenche o formulário via função exportada pelo componente
        if (window.fillUnifiedForm) {
            window.fillUnifiedForm(formData);
        }

        // Ações do Admin (Remover Reserva)
        const adminSection = document.getElementById('admin-reservation-actions');
        if (adminSection) {
            const isReserved = docenteAgendas.some(a => a.status === 'RESERVADO' && a.data === startISO && (window.calendarCurrentPeriod ? a.periodo === window.calendarCurrentPeriod : true));
            adminSection.style.display = (window.__isAdmin && isReserved) ? 'block' : 'none';

            const btnRemover = document.getElementById('btn-remover-reserva');
            if (btnRemover) {
                btnRemover.onclick = () => {
                    if (confirm('Deseja realmente remover a reserva deste dia?')) {
                        const data = new FormData();
                        data.append('action', 'remove_reservation');
                        data.append('docente_id', currentDocente.id);
                        data.append('data', startISO);
                        data.append('periodo', window.calendarCurrentPeriod || '');

                        fetch('api_agenda.php', { method: 'POST', body: data })
                            .then(r => r.json())
                            .then(res => {
                                if (res.success) {
                                    m.classList.remove('active');
                                    loadDocenteAgenda(currentDocente.id);
                                } else {
                                    alert('Erro ao remover reserva: ' + (res.message || 'Erro desconhecido'));
                                }
                            })
                            .catch(err => console.error(err));
                    }
                };
            }
        }

        m.classList.add('active');
    };


    function enforceSaturdayNightConstraint() {
        const periodSelect = document.getElementById('periodo-select-unified');
        const checkboxes = document.querySelectorAll('#dias-semana-container-unified input[name="dias_semana[]"]');
        const sabCheckbox = Array.from(checkboxes).find(cb => cb.value === 'Sábado');
        if (!periodSelect || !sabCheckbox) return;

        const isNoite = periodSelect.value === 'Noite';

        if (isNoite) {
            sabCheckbox.disabled = true;
            if (sabCheckbox.checked) {
                sabCheckbox.checked = false;
                if (typeof calcularDataFimUnified === 'function') calcularDataFimUnified();
            }
            const label = sabCheckbox.closest('label') || sabCheckbox.parentElement;
            if (label) {
                label.style.opacity = '0.4';
                label.title = 'Não é permitido aulas no período Noite aos Sábados.';
            }
        } else {
            if (!sabCheckbox.dataset.occupied) {
                sabCheckbox.disabled = false;
                const label = sabCheckbox.closest('label') || sabCheckbox.parentElement;
                if (label) {
                    label.style.opacity = '1';
                    label.title = '';
                }
            }
        }

        const noiteOption = Array.from(periodSelect.options).find(opt => opt.value === 'Noite');
        if (noiteOption) {
            if (sabCheckbox.checked) {
                noiteOption.disabled = true;
                if (periodSelect.value === 'Noite') {
                    periodSelect.value = '';
                    showNotification('Aviso: Período "Noite" não é permitido aos Sábados.', 'warning');
                }
            } else {
                noiteOption.disabled = false;
            }
        }
    }

    function disableOccupiedDays(startISO, endISO) {
        if (!docenteAgendas.length || !startISO) return;
        const startDate = new Date(startISO + 'T00:00:00');
        const endDate = endISO ? new Date(endISO + 'T00:00:00') : startDate;
        const occupiedDays = new Set();

        docenteAgendas.forEach(a => {
            if (a.status === 'RESERVADO') return; // Reservas não bloqueiam o dia aqui
            const agStart = new Date(a.data_inicio + 'T00:00:00');
            const agEnd = new Date(a.data_fim + 'T00:00:00');
            if (agStart <= endDate && agEnd >= startDate) {
                occupiedDays.add(a.dia_semana);
            }
        });

        document.querySelectorAll('#dias-semana-container-unified input[name="dias_semana[]"]').forEach(cb => {
            const label = cb.closest('label') || cb.parentElement;
            if (occupiedDays.has(cb.value)) {
                cb.disabled = true;
                cb.checked = false;
                cb.dataset.occupied = 'true';
                if (label) { label.style.opacity = '0.4'; label.title = `${cb.value}: Professor já tem aula neste dia`; }
            } else {
                cb.disabled = false;
                cb.dataset.occupied = '';
                if (label) { label.style.opacity = '1'; label.title = ''; }
            }
        });
    }

    // ============================================================
    // RESTRIÇÕES DE PERÍODO -> TEMPO NO MODAL
    // ============================================================
    function applyPeriodToForm(periodo) {
        const config = periodConfig[periodo];
        if (!config) return;

        const hInicio = document.getElementById('horario_inicio_unified');
        const hFim = document.getElementById('horario_fim_unified');

        if (hInicio) { hInicio.value = config.inicio; hInicio.min = config.min; hInicio.max = config.max; }
        if (hFim) { hFim.value = config.fim; hFim.min = config.min; hFim.max = config.max; }

        // Sincroniza restrição de Sábado Noite com o novo form
        enforceSaturdayNightConstraint();
    }

    // Mudança na seleção de período no modal
    const periodSelect = document.getElementById('modal-cal-periodo');
    if (periodSelect) {
        periodSelect.addEventListener('change', function () {
            const p = this.value;
            if (p && periodConfig[p]) {
                applyPeriodToForm(p);
            }
            enforceSaturdayNightConstraint();
        });
    }

    // Adiciona listener aos checkboxes para restrição de Sábado à Noite
    document.querySelectorAll('#form-agendar-calendar input[name="dias_semana[]"]').forEach(cb => {
        cb.addEventListener('change', enforceSaturdayNightConstraint);
    });

    // ============================================================
    // RESTRIÇÃO DE SALA: Informática apenas para TI
    // ============================================================
    const formModal = document.getElementById('form-agendar-calendar');

    function filterRoomsByCourse() {
        if (!formModal) return;
        const cursoSelect = formModal.querySelector('select[name="curso_id"]');
        const selectedCourse = cursoSelect?.options[cursoSelect.selectedIndex];
        const area = (selectedCourse?.dataset?.area || '').toLowerCase();
        const isTI = area.includes('ti') || area.includes('software') || area.includes('hardware') || area.includes('computação');

        const ambienteSelect = formModal.querySelector('select[name="ambiente_id"]');
        if (!ambienteSelect) return;
        const options = ambienteSelect.querySelectorAll('option[data-tipo]');
        const groups = ambienteSelect.querySelectorAll('optgroup');
        let currentRoomStillValid = true;
        const currentRoomId = ambienteSelect.value;

        options.forEach(opt => {
            const isInf = opt.dataset.tipo === 'Informática';
            if (isInf && !isTI) {
                opt.disabled = true; opt.style.display = 'none';
                if (opt.value === currentRoomId) currentRoomStillValid = false;
            } else {
                opt.disabled = false; opt.style.display = '';
            }
        });

        groups.forEach(group => {
            const groupOptions = Array.from(group.querySelectorAll('option'));
            const hasVisible = groupOptions.some(opt => opt.style.display !== 'none');
            group.style.display = hasVisible ? '' : 'none';
        });

        if (!currentRoomStillValid) ambienteSelect.value = '';
    }

    if (formModal) {
        formModal.querySelector('select[name="curso_id"]')?.addEventListener('change', filterRoomsByCourse);
    }

    // ============================================================
    // FECHAR MODAL
    // ============================================================
    const modalCal = document.getElementById('modal-agendar-calendar');
    if (modalCal) {
        document.getElementById('modal-cal-close')?.addEventListener('click', () => modalCal.classList.remove('active'));
        let modalCalClickStart = null;
        modalCal.addEventListener('mousedown', e => modalCalClickStart = e.target);
        modalCal.addEventListener('click', e => { if (e.target === modalCal && e.target === modalCalClickStart) modalCal.classList.remove('active'); });
    }

    // ============================================================
    // AUTO CALCULATION DE DATA FIM (MODAL 4)
    // ============================================================
    const autoCalcCheckbox = document.getElementById('modal-cal-calc-auto');
    const dfimModal = document.getElementById('modal-cal-data-fim');
    if (autoCalcCheckbox && dfimModal) {
        autoCalcCheckbox.addEventListener('change', function () {
            dfimModal.readOnly = this.checked;
            dfimModal.style.background = this.checked ? 'var(--bg-hover)' : 'var(--bg-card)';
            dfimModal.style.cursor = this.checked ? 'not-allowed' : 'text';
            if (!this.checked) dfimModal.required = true;
        });
    }

    function calcularDataFimModal() {
        const autoCheck = document.getElementById('modal-cal-calc-auto');
        if (!autoCheck || !autoCheck.checked) return;

        const cursoSelectModal = document.getElementById('modal-cal-curso-id');
        const periodSelectModal = document.getElementById('modal-cal-periodo');
        const dataIniciModal = document.getElementById('modal-cal-data-inicio');
        const dataFimModal = document.getElementById('modal-cal-data-fim');
        const infoElModal = document.getElementById('modal-cal-data-fim-info');

        if (!cursoSelectModal || !periodSelectModal || !dataIniciModal || !dataFimModal) return;

        const opt = cursoSelectModal.options[cursoSelectModal.selectedIndex];
        const ch = parseInt(opt?.dataset?.ch) || 0;
        const periodo = periodSelectModal.value;
        const inicio = dataIniciModal.value;
        const diasChecked = Array.from(document.querySelectorAll('#form-agendar-calendar input[name="dias_semana[]"]:checked')).map(cb => cb.value);

        if (!ch || !periodo || !inicio || diasChecked.length === 0) {
            dataFimModal.value = '';
            if (infoElModal) infoElModal.textContent = '';
            return;
        }

        let horasPorDia = 0;
        switch (periodo) {
            case 'Manhã': case 'Tarde': case 'Noite': horasPorDia = 4; break;
            case 'Integral': horasPorDia = 8; break;
        }

        if (horasPorDia === 0) return;

        const totalDias = Math.ceil(ch / horasPorDia);
        const mapDias = { 'Segunda-feira': 1, 'Terça-feira': 2, 'Quarta-feira': 3, 'Quinta-feira': 4, 'Sexta-feira': 5, 'Sábado': 6 };
        const diasIndices = diasChecked.map(d => mapDias[d] ?? -1).filter(i => i >= 0);

        let date = new Date(inicio + 'T12:00:00');
        let count = 0;

        // Coleta IDs dos docentes selecionados para checar feriados individuais/férias
        const currentDocIds = selectedModalDocentes.map(d => String(d.id));

        for (let safety = 0; safety < 1000 && count < totalDias; safety++) {
            const dow = date.getDay();
            const dateISO = date.toISOString().slice(0, 10);

            // Verifica se o dia é um dia da semana selecionado
            if (diasIndices.includes(dow)) {
                // VERIFICAÇÃO DE FERIADO / FÉRIAS / BLOQUEIO (Pular automaticamente)
                const isBlocked = (window.docenteAgendas || []).some(a => {
                    const isTypeMatch = (a.type === 'HOLIDAY' || a.type === 'VACATION' || a.type === 'BLOCK');
                    const isDateMatch = a.agenda_data === dateISO || (dateISO >= a.data_inicio && dateISO <= a.data_fim);
                    const isDocMatch = !a.docente_id || currentDocIds.includes(String(a.docente_id));
                    return isTypeMatch && isDateMatch && isDocMatch;
                });

                if (!isBlocked) {
                    count++;
                }
            }

            if (count >= totalDias) break;
            date.setDate(date.getDate() + 1);
        }

        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        dataFimModal.value = `${y}-${m}-${d}`;
        if (infoElModal) {
            infoElModal.innerHTML = `<i class="fas fa-info-circle"></i> ${ch}h ÷ ${horasPorDia}h/dia = <strong>${totalDias} dias de aula</strong> (Pula feriados/férias).`;
        }

        disableOccupiedDays(inicio, dataFimModal.value);
    }

    if (formModal) {
        document.getElementById('curso-select-unified')?.addEventListener('change', () => {
            if (typeof calcularDataFimUnified === 'function') calcularDataFimUnified();
        });
        document.getElementById('data-inicio-unified')?.addEventListener('change', () => {
            if (typeof calcularDataFimUnified === 'function') calcularDataFimUnified();
            disableOccupiedDays(document.getElementById('data-inicio-unified').value, document.getElementById('data-fim-unified')?.value);
        });
        document.getElementById('data-fim-unified')?.addEventListener('change', () => {
            disableOccupiedDays(document.getElementById('data-inicio-unified')?.value, document.getElementById('data-fim-unified').value);
        });
        document.getElementById('periodo-select-unified')?.addEventListener('change', function () {
            const p = this.value;
            if (p && periodConfig[p]) applyPeriodToForm(p);
            if (typeof calcularDataFimUnified === 'function') calcularDataFimUnified();
        });
        document.querySelectorAll('#dias-semana-container-unified input[name="dias_semana[]"]').forEach(cb => {
            cb.addEventListener('change', () => {
                enforceSaturdayNightConstraint();
                if (typeof calcularDataFimUnified === 'function') calcularDataFimUnified();
            });
        });
    }

    // BLOCO DE SUBMISSÃO REMOVIDO: O formulário unificado (turma-form-unified) 
    // agora gerencia sua própria submissão com feedback visual integrado.

    // ============================================================
    // NOTIFICAÇÕES
    // ============================================================

    // ============================================================
    // ALTERNAR VISUALIZAÇÃO (Calendário / Gantt)
    // ============================================================
    document.querySelectorAll('.view-toggle-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.view-toggle-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const view = this.dataset.view;
            document.getElementById('calendar-view').style.display = view === 'calendar' ? '' : 'none';
            document.getElementById('gantt-view').style.display = view === 'gantt' ? '' : 'none';
        });
    });

    // Carrega automaticamente se o valor já estiver definido (ex: para Professores ou via GET)
    if (docenteSelect && docenteSelect.value) {
        loadDocenteAgenda(docenteSelect.value);
    }

    // ============================================================
    // MANIPULADOR GLOBAL PARA CLIQUE NO NOME DO PROFESSOR
    // ============================================================
    window.openTimelineModal = function (profId, profNome) {
        if (typeof window.syncCurrentProfessorUI === 'function') {
            window.syncCurrentProfessorUI(profId, profNome);
        }
        // Muda a visualização para o calendário automaticamente para mostrar o contexto do modal
        // Ou apenas deixa a UI atualizar (estilo SPA). Vamos deixar na visualização atual.
    };

    renderCalendar();

    // ============================================================
    // MANIPULADORES GLOBAIS PARA BARRAS HTML RENDERIZADAS (MODELOS TIMELINE)
    // ============================================================
    // --- Sincronização de Carga Horária (Modal de Agendamento) ---
    const modalCursoSelect = document.getElementById('modal-cal-curso-id');
    const modalCHInput = document.getElementById('modal-cal-ch');
    if (modalCursoSelect && modalCHInput) {
        modalCursoSelect.addEventListener('change', function () {
            const selectedOption = this.options[this.selectedIndex];
            const ch = selectedOption ? selectedOption.dataset.ch : '';
            modalCHInput.value = ch || '';
        });
    }

    window.syncCurrentProfessorUI = function (profId, profNome) {
        const docenteSelect = document.getElementById('calendar-docente-select');
        const btnProfLabel = document.getElementById('btn-prof-label');
        const btnSelecionarProf = document.getElementById('btn-selecionar-professor');

        if (docenteSelect) docenteSelect.value = profId;
        if (btnProfLabel) btnProfLabel.textContent = profNome;
        if (btnSelecionarProf) {
            btnSelecionarProf.style.background = '#2e7d32';
            btnSelecionarProf.style.borderColor = '#1b5e20';
        }

        let fullProf = null;
        if (window.__docentesData) {
            fullProf = window.__docentesData.find(d => String(d.id) === String(profId));
        }
        currentDocente = fullProf || { id: profId, nome: profNome };

        if (typeof loadDocenteAgenda === 'function') {
            loadDocenteAgenda(profId).catch(() => { });
        }

        const currentUrl = new URL(window.location.href);
        if (String(currentUrl.searchParams.get('docente_id')) !== String(profId)) {
            currentUrl.searchParams.set('docente_id', profId);
            window.history.pushState({}, '', currentUrl);
        }

        // Oculta os outros professores para aplicar o filtro visualmente sem recarregar a página
        document.querySelectorAll('.prof-row').forEach(row => {
            if (row.getAttribute('data-prof-id') !== String(profId)) {
                row.style.display = 'none';
            } else {
                row.style.display = '';
            }
        });
    };

    // Lógica antiga de multi-docente removida: o formulário unificado gerencia isso.
    window.openCalendarScheduleModal = async function (start, end, isReserva = false, id = null) {
        // Se estamos em modo reserva ou criação global, permitimos abrir sem professor (usuário seleciona no form)
        if (!currentDocente && !isReserva) {
            showNotification('Selecione um professor primeiro.', 'error');
            return;
        }

        const m = document.getElementById('modal-agendar-calendar');
        if (!m) return;

        let formData = {
            id: id,
            is_reserva: isReserva,
            docentes: currentDocente ? [currentDocente] : [],
            data_inicio: start || new Date().toISOString().slice(0, 10),
            data_fim: end || start || new Date().toISOString().slice(0, 10),
            periodo: window.calendarCurrentPeriod || 'Manhã',
            dias_semana: []
        };

        // Se tiver ID, busca dados atualizados do servidor antes de preencher
        if (id) {
            try {
                const action = isReserva ? 'get_reserva' : 'get_turma';
                const response = await fetch(`${apiBase}?action=${action}&id=${id}`);
                const result = await response.json();
                if (result.success) {
                    const d = result.data;
                    formData = {
                        id: d.id,
                        is_reserva: isReserva,
                        docentes: [], // Será preenchido abaixo
                        data_inicio: d.data_inicio,
                        data_fim: d.data_fim,
                        periodo: d.periodo,
                        dias_semana: d.dias_semana ? d.dias_semana.split(',').map(s => s.trim()) : [],
                        sigla: d.sigla,
                        vagas: d.vagas,
                        curso_id: d.curso_id,
                        ambiente_id: d.ambiente_id,
                        horario_inicio: d.horario_inicio ? d.horario_inicio.substring(0, 5) : null,
                        horario_fim: d.horario_fim ? d.horario_fim.substring(0, 5) : null,
                        tipo_custeio: d.tipo_custeio,
                        previsao_despesa: d.previsao_despesa,
                        valor_turma: d.valor_turma,
                        numero_proposta: d.numero_proposta,
                        tipo_atendimento: d.tipo_atendimento,
                        parceiro: d.parceiro,
                        contato_parceiro: d.contato_parceiro
                    };

                    // Adiciona docentes (para reservas é um, para turmas podem ser vários)
                    if (isReserva) {
                        // Reserva usa docente_id
                        const dId = d.docente_id || d.docente_id1;
                        const dNome = d.docente_nome || d.docente1_nome;
                        if (dId) formData.docentes = [{ id: dId, nome: dNome || "Professor Desconhecido" }];
                    } else {
                        // Turma usa docente_id1, id2, etc.
                        if (d.docente_id1) formData.docentes.push({ id: d.docente_id1, nome: d.docente1_nome || d.docente_nome || "Professor 1" });
                        if (d.docente_id2) formData.docentes.push({ id: d.docente_id2, nome: d.docente2_nome || "Professor 2" });
                        if (d.docente_id3) formData.docentes.push({ id: d.docente_id3, nome: d.docente3_nome || "Professor 3" });
                        if (d.docente_id4) formData.docentes.push({ id: d.docente_id4, nome: d.docente4_nome || "Professor 4" });
                        
                        // Caso a turma tenha apenas docente_id (em migrações), tenta capturar tbm
                        if (formData.docentes.length === 0 && d.docente_id) {
                            formData.docentes.push({ id: d.docente_id, nome: d.docente_nome || "Professor" });
                        }
                    }
                }
            } catch (err) {
                console.error("Erro ao buscar detalhes:", err);
            }
        } else if (start) {
            const dateObj = new Date(start + 'T00:00:00');
            formData.dias_semana = [diasSemanaFull[dateObj.getDay()]];
        } else {
            formData.dias_semana = ['Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira'];
        }

        if (window.fillUnifiedForm) {
            window.fillUnifiedForm(formData);
        }

        m.classList.add('active');

        setTimeout(() => {
            disableOccupiedDays(formData.data_inicio, formData.data_fim);
            enforceSaturdayNightConstraint();
        }, 100);
    };

    window.handleBarClick = function (profId, profNome, dateStr, element, event) {
        // Bug fix: Se o elemento clicado for cinza (indisponível), ignore totalmente.
        if (element && (element.classList.contains('busy') || element.classList.contains('reserved') || element.classList.contains('timeline-day-sunday') || element.classList.contains('off-schedule'))) {
            console.log("handleBarClick bloqueado: elemento indisponível.");
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            return false;
        }

        // Seleciona automaticamente o professor se ainda não estiver selecionado
        if (!currentDocente || String(currentDocente.id) !== String(profId)) {
            if (typeof window.syncCurrentProfessorUI === 'function') {
                window.syncCurrentProfessorUI(profId, profNome);
            }
        }
        let isFree = false;
        if (element && (
            element.classList.contains('bar-seg-free') ||
            element.classList.contains('block-seg-free') ||
            element.classList.contains('sem-day-free') ||
            element.classList.contains('timeline-day-free') ||
            element.classList.contains('calendar-day-free') ||
            element.classList.contains('timeline-day-busy') ||
            element.classList.contains('calendar-day-busy') ||
            element.classList.contains('calendar-day-partial') ||
            element.classList.contains('block-seg-busy') ||
            element.classList.contains('block-seg-partial')
        )) {
            isFree = true;
        }

        if (isFree) {
            if (window.userIsCRI && !window.userIsAdmin) {
                if (typeof window.openCalendarScheduleModal === 'function') {
                    window.openCalendarScheduleModal(dateStr, dateStr, true);
                }
            } else {
                const rect = element ? element.getBoundingClientRect() : { left: window.innerWidth / 2, top: window.innerHeight / 2, width: 0, height: 0 };
                const e = event || window.event;

                // Fallback de coordenadas caso o evento seja por teclado ou mal formado
                const fakeEvent = {
                    clientX: rect.left + (rect.width / 2) - 90,
                    clientY: rect.top + (rect.height / 2) + 10
                };

                const finalEvent = (e && e.clientX) ? e : fakeEvent;

                if (typeof window.showSchedulingChoiceMenu === 'function') {
                    window.showSchedulingChoiceMenu(dateStr, finalEvent, profId);
                } else if (typeof window.openCalendarScheduleModal === 'function') {
                    window.openCalendarScheduleModal(dateStr, dateStr);
                }
            }
        } else {
            // slots "própria reserva" ou outros previamente reservados simplesmente abrem o modal diretamente
            if (typeof window.openCalendarScheduleModal === 'function') {
                window.openCalendarScheduleModal(dateStr, dateStr);
            }
        }
    };
});
