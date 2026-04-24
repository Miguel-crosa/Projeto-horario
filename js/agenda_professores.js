/**
 * agenda_professores.js
 */

const currentMonthStart = document.querySelector('[name="month"]')?.value || new Date().toISOString().slice(0, 7);
let currentProfId = null;
let currentProfNome = "";
let currentViewMonth = currentMonthStart;

// Modo Reserva
// Variáveis/funcionalidades do Modo Reserva Antigo (Agora gerenciado diretamente pelo modal)
window.reservaModeActive = false;
window.reservaSelectedDates = [];
window.reservaCurrentProfId = null;
window.reservaCurrentProfNome = "";

window.toggleReservaMode = function () {
    if (typeof handleModoReservaClick === 'function') handleModoReservaClick();
};

window.cancelReservaSelection = function () {
    if (typeof handleCancelarReservaClick === 'function') handleCancelarReservaClick();
};

window.confirmReservations = function () {
    if (typeof handleModoReservaClick === 'function') handleModoReservaClick();
};

function formatDateBR(dateStr) {
    const [y, m, d] = dateStr.split('-');
    return `${d}/${m}/${y}`;
}

function handleBarClick(profId, profNome, dateStr, element) {
    // Bug fix: Se o elemento clicado tiver classe de indisponibilidade ou domingo, ignora.
    if (window.userIsProfessor) return;
    if (element && (element.classList.contains('timeline-day-sunday') || 
                    element.classList.contains('sem-day-sunday') || 
                    element.classList.contains('block-seg-sunday'))) {
        return;
    }

    // Bug fix: Se for ocupado e não for reserva própria, ignora (exceto para Admin ver detalhes)
    if (element && (element.classList.contains('timeline-day-busy') || 
                    element.classList.contains('sem-day-busy') || 
                    element.classList.contains('block-seg-busy') ||
                    element.classList.contains('timeline-day-reserved') ||
                    element.classList.contains('sem-day-reserved') ||
                    element.classList.contains('block-seg-reserved'))) {
        
        // Se for admin, pode querer ver o que tem, mas para o fluxo de reserva/agendamento normal de clique verde/cinza:
        if (!window.userIsAdmin) return;
    }

    // Lógica requerida: Clique deve acionar a abertura da modal de 'registrar reserva' ou 'registrar horário'
    // Se o usuário for CRI (não Admin), abre como reserva.
    const forceReserva = window.userIsCRI && !window.userIsAdmin;
    
    openScheduleModal(profId, profNome, dateStr, forceReserva);
}

function openScheduleModal(profId, profNome, date, isReserva = false) {
    // 1. Atualiza visualmente o professor ativo e a URL com a linha clicada
    const docenteSelect = document.getElementById('calendar-docente-select');
    if (docenteSelect && docenteSelect.value != profId) {
        docenteSelect.value = profId;
        docenteSelect.dispatchEvent(new Event('change'));

        // Atualiza a URL para manter a seleção ao recarregar a página
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('docente_id', profId);
        currentUrl.searchParams.set('search', profNome);
        currentUrl.searchParams.delete('page');
        window.history.pushState({}, '', currentUrl);

        // Oculta os outros professores para aplicar o filtro visualmente sem recarregar a página
        document.querySelectorAll('.prof-row').forEach(row => {
            if (row.getAttribute('data-prof-id') !== String(profId)) {
                row.style.display = 'none';
            } else {
                row.style.display = '';
            }
        });

        // Atualiza o botão visual
        const btnProfLabel = document.getElementById('btn-prof-label');
        if (btnProfLabel) btnProfLabel.textContent = profNome;

        const btnSelecionarProf = document.getElementById('btn-selecionar-professor');
        if (btnSelecionarProf) {
            btnSelecionarProf.style.background = '#2e7d32';
            btnSelecionarProf.style.borderColor = '#1b5e20';
        }

        // Atualiza os links de navegação dinamicamente
        document.querySelectorAll('.view-btn, .month-btn, .pagination a').forEach(link => {
            if (link.tagName.toLowerCase() === 'a' && link.href) {
                try {
                    const url = new URL(link.href);
                    url.searchParams.set('docente_id', profId);
                    url.searchParams.set('search', profNome);
                    link.href = url.toString();
                } catch (e) { }
            }
        });
    }

    const clickedDate = new Date(date + 'T00:00:00');
    let dow = clickedDate.getDay(); if (dow === 0) dow = 7;
    const formatDate = (d) => d.toISOString().split('T')[0];

    // Passamos a data exata ao invés da semana inteira para que o modal selecione aquele dia direto
    const exactDateStr = formatDate(clickedDate);

    // Chama o modal global para criar um agendamento
    if (typeof window.openCalendarScheduleModal === 'function') {
        window.openCalendarScheduleModal(exactDateStr, exactDateStr, isReserva);
    } else {
        alert("Erro no carregamento do calendário. Recarregue a página e tente novamente.");
    }
}

function openTimelineModal(profId, profNome) {
    currentProfId = profId;
    currentProfNome = profNome;
    currentViewMonth = currentMonthStart;
    updateModalTitle();
    fetchNewAvailability();
    document.getElementById('timelineModal').classList.add('active');
}

function updateModalTitle() {
    const dateObj = new Date(currentViewMonth + "-01T00:00:00");
    const monthName = dateObj.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
    const titleEl = document.getElementById('timeline_prof_name');
    if (titleEl) {
        titleEl.innerHTML = `<div style="font-size:0.9rem; opacity:0.7;">${currentProfNome}</div><div style="text-transform:capitalize;">${monthName}</div>`;
    }
}

const prevBtn = document.getElementById('prev_month_btn');
if (prevBtn) prevBtn.onclick = () => changeMonth(-1);
const nextBtn = document.getElementById('next_month_btn');
if (nextBtn) nextBtn.onclick = () => changeMonth(1);

function changeMonth(delta) {
    let [year, month] = currentViewMonth.split('-').map(Number);
    month += delta;
    if (month > 12) { month = 1; year++; }
    if (month < 1) { month = 12; year--; }
    currentViewMonth = `${year}-${String(month).padStart(2, '0')}`;
    updateModalTitle();
    fetchNewAvailability();
}

async function fetchNewAvailability() {
    const container = document.getElementById('calendar_render_area');
    if (!container) return;
    container.style.opacity = '0.5';
    try {
        const response = await fetch(`?ajax_availability=1&prof_id=${currentProfId}&month=${currentViewMonth}`);
        const data = await response.json();
        renderCalendarView(currentProfId, currentProfNome, currentViewMonth, data.busy, 'calendar_render_area', data.turnos || {}, data.reserved || {});
    } catch (e) {
        console.error(e);
    } finally {
        container.style.opacity = '1';
    }
}

function renderCalendarView(profId, profNome, monthStr, busyDays, targetContainerId, turnoData, reservedData) {
    const container = document.getElementById(targetContainerId);
    if (!container) return;
    const date = new Date(monthStr + "-01T00:00:00");
    const firstDayOfWeek = date.getDay();
    const daysInMonth = new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate();
    const dayNamesShort = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    let html = `<div class="calendar-container"><div class="calendar-header-grid">${dayNamesShort.map(d => `<div>${d}</div>`).join('')}</div><div class="calendar-grid">`;
    for (let i = 0; i < firstDayOfWeek; i++) html += `<div class="calendar-day calendar-day-empty"></div>`;
    for (let i = 1; i <= daysInMonth; i++) {
        const dStr = `${monthStr}-${String(i).padStart(2, '0')}`;
        const dObj = new Date(dStr + "T00:00:00");
        const dow = dObj.getDay();
        const isBusy = busyDays[dStr];
        const turno = turnoData ? turnoData[dStr] : null;
        const reserved = reservedData ? reservedData[dStr] : null;
        const isSunday = (dow === 0), isSaturday = (dow === 6);
        const hasM = turno && turno.M, hasT = turno && turno.T, hasN = turno && turno.N;
        const allFull = ((hasM ? 1 : 0) + (hasT ? 1 : 0) + (hasN ? 1 : 0)) >= 2;
        const isPartial = isBusy && !allFull;
        let statusClass, weekendClass = '', statusLabel = 'Livre', clickable = false, extraHtml = '';
        if (isSunday) { statusClass = 'calendar-day-busy'; weekendClass = 'calendar-day-weekendd'; statusLabel = 'Bloqueado'; }
        else if (reserved && !reserved.own) { statusClass = 'calendar-day-reserved'; statusLabel = 'Reservado'; extraHtml = `<div style="font-size:0.55rem;margin-top:2px;opacity:0.85;"><i class="fas fa-bookmark"></i> ${reserved.gestor}</div>`; }
        else if (reserved && reserved.own) { statusClass = 'calendar-day-reserved-own'; statusLabel = 'Reservado'; clickable = true; }
        else if (isBusy && isPartial) { statusClass = 'calendar-day-partial'; statusLabel = 'Parcial'; clickable = true; }
        else if (isBusy) { statusClass = 'calendar-day-busy'; weekendClass = isSaturday ? 'calendar-day-weekend' : ''; statusLabel = 'Ocupado'; }
        else { statusClass = 'calendar-day-free'; weekendClass = isSaturday ? 'calendar-day-weekend' : ''; clickable = true; }
        let clickHandler = clickable ? `onclick="handleBarClick(${profId}, '${profNome}', '${dStr}', this, event)"` : '';
        html += `<div class="calendar-day ${statusClass} ${weekendClass}" ${clickHandler} data-date="${dStr}"><div class="day-number">${i}</div><div class="day-status-label">${statusLabel}</div>${extraHtml}</div>`;
    }
    html += `</div></div>`;
    container.innerHTML = html;
}

// Bloqueio por dia da semana
const dayNamesGlobal = { 1: 'Segunda', 2: 'Terça', 3: 'Quarta', 4: 'Quinta', 5: 'Sexta', 6: 'Sábado' };
const turnoLabelsGlobal = { M: '☀ M', T: '☁ T', N: '☽ N' };

function toggleWeekdayCard(dayNum) {
    const cb = document.getElementById('weekday_' + dayNum);
    if (cb && !cb.disabled) {
        cb.checked = !cb.checked;
        scheduleWeekdayCheck();
    }
}

function resetWeekdayCheckboxes() {
    for (let d = 1; d <= 6; d++) {
        const cb = document.getElementById('weekday_' + d);
        const card = document.getElementById('weekday_card_' + d);
        const turnoEl = document.getElementById('weekday_turno_' + d);
        const countEl = document.getElementById('weekday_count_' + d);
        if (cb) { cb.disabled = false; cb.checked = (d <= 5); }
        if (card) card.classList.remove('wc-blocked', 'wc-partial-block');
        if (turnoEl) turnoEl.innerHTML = '';
        if (countEl) countEl.textContent = '';
    }
    const infoDiv = document.getElementById('weekday_blocking_info');
    if (infoDiv) infoDiv.style.display = 'none';
}

function getSelectedTurno(horaInicio, horaFim) {
    const result = { M: false, T: false, N: false };
    if (horaInicio < '12:00') result.M = true;
    if (horaInicio < '18:00' && horaFim > '12:00') result.T = true;
    if (horaFim > '18:00' || horaInicio >= '18:00') result.N = true;
    return result;
}

let weekdayCheckTimeout = null;

async function checkWeekdayBlocking() {
    const profId = document.getElementById('form_prof_id')?.value;
    const dateStart = document.getElementById('form_date_start')?.value;
    const dateEnd = document.getElementById('form_date_end')?.value;
    const horaInicio = document.getElementById('form_hora_inicio')?.value;
    const horaFim = document.getElementById('form_hora_fim')?.value;
    if (!profId || !dateStart || !dateEnd || !horaInicio || !horaFim) { resetWeekdayCheckboxes(); return; }
    const selectedTurno = getSelectedTurno(horaInicio, horaFim);
    try {
        const url = `?ajax_weekday_check=1&prof_id=${profId}&date_start=${dateStart}&date_end=${dateEnd}&hora_inicio=${horaInicio}&hora_fim=${horaFim}`;
        const response = await fetch(url);
        const data = await response.json();
        const turnos = data.turnos || {};
        let blockedNames = [];
        for (let d = 1; d <= 6; d++) {
            const cb = document.getElementById('weekday_' + d);
            const card = document.getElementById('weekday_card_' + d);
            const turnoEl = document.getElementById('weekday_turno_' + d);
            const countEl = document.getElementById('weekday_count_' + d);
            const turnoData = turnos[d] || null;
            if (!cb || !card) continue;
            card.classList.remove('wc-blocked', 'wc-partial-block');
            let turnoConflict = false;
            let turnoHtml = '';
            if (turnoData) {
                ['M', 'T', 'N'].forEach(t => {
                    const count = turnoData[t] || 0;
                    if (count > 0) {
                        const isConflict = selectedTurno[t];
                        if (isConflict) turnoConflict = true;
                        turnoHtml += `<span class="wc-turno-badge ${isConflict ? 'wt-conflict' : 'wt-occupied'}">${turnoLabelsGlobal[t]} ${count}d</span>`;
                    } else {
                        turnoHtml += `<span class="wc-turno-badge wt-free">${turnoLabelsGlobal[t]}</span>`;
                    }
                });
                if (countEl) countEl.textContent = `${turnoData.total} dia(s)`;
            } else {
                turnoHtml = '<span class="wc-turno-badge wt-free">Livre</span>';
                if (countEl) countEl.textContent = '';
            }
            if (turnoEl) turnoEl.innerHTML = turnoHtml;

            const isSabNightConflict = (d === 6 && selectedTurno.N);

            if (turnoConflict || isSabNightConflict) {
                cb.disabled = true; cb.checked = false;
                card.classList.add('wc-blocked');
                if (isSabNightConflict) {
                    card.title = "O período 'Noite' não é permitido aos Sábados.";
                    blockedNames.push(dayNamesGlobal[d] + ' (Noite)');
                } else {
                    blockedNames.push(dayNamesGlobal[d]);
                }
            } else if (turnoData && turnoData.total > 0) {
                cb.disabled = false;
                card.classList.add('wc-partial-block');
                card.title = "";
            } else {
                cb.disabled = false;
                card.title = "";
            }
        }
        const infoDiv = document.getElementById('weekday_blocking_info');
        if (infoDiv) {
            if (blockedNames.length > 0) {
                const textEl = document.getElementById('weekday_blocking_text');
                if (textEl) textEl.innerHTML = `<strong>Bloqueados (${blockedNames.length}):</strong> ${blockedNames.join(', ')} — turno ${horaInicio}–${horaFim} ocupado.`;
                infoDiv.style.display = 'block';
            } else {
                infoDiv.style.display = 'none';
            }
        }
    } catch (e) { console.error('Erro ao verificar bloqueio:', e); }
}

function scheduleWeekdayCheck() {
    clearTimeout(weekdayCheckTimeout);
    weekdayCheckTimeout = setTimeout(checkWeekdayBlocking, 300);
}

document.addEventListener('DOMContentLoaded', () => {
    ['form_prof_id', 'form_date_start', 'form_date_end', 'form_hora_inicio', 'form_hora_fim'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', scheduleWeekdayCheck);
            if (id.includes('hora')) el.addEventListener('input', scheduleWeekdayCheck);
        }
    });

    const schForm = document.querySelector('#scheduleModal form');
    if (schForm) {
        schForm.addEventListener('submit', (e) => {
            const hInicio = document.getElementById('form_hora_inicio').value;
            const hFim = document.getElementById('form_hora_fim').value;
            const turno = getSelectedTurno(hInicio, hFim);
            const sabCb = document.getElementById('weekday_6');

            if (turno.N && sabCb && sabCb.checked) {
                e.preventDefault();
                alert("Erro: O período 'Noite' não é permitido aos Sábados. Por favor, ajuste o horário ou desmarque o Sábado.");
                return false;
            }
        });
    }

    ['reserva_hora_inicio', 'reserva_hora_fim'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', () => {
                const errDiv = document.getElementById('reserva_error');
                if (errDiv) errDiv.style.display = 'none';
            });
        }
    });
    // Dispara a busca inicial se um professor já estiver selecionado (a partir da URL)
    const docenteSelect = document.getElementById('calendar-docente-select');
    if (docenteSelect && docenteSelect.value) {
        currentProfId = docenteSelect.value;
        const selectedOption = docenteSelect.options[docenteSelect.selectedIndex];
        currentProfNome = selectedOption ? selectedOption.text : "";
        fetchNewAvailability();

        // Exibe o botão de agendamento
        const btnAgendar = document.getElementById('btn-agendar-bar');
        if (btnAgendar) btnAgendar.style.display = 'flex';
    }
    // --- Suporte a Swipe (Touch) para Modal de Agenda ---
    let touchStartX = 0;
    let touchEndX = 0;
    const agendaModalContent = document.querySelector('#timelineModal .modal-content');

    if (agendaModalContent) {
        agendaModalContent.addEventListener('touchstart', e => {
            touchStartX = e.changedTouches[0].screenX;
        }, {passive: true});

        agendaModalContent.addEventListener('touchend', e => {
            touchEndX = e.changedTouches[0].screenX;
            handleAgendaSwipe();
        }, {passive: true});
    }

    function handleAgendaSwipe() {
        const threshold = 100;
        if (touchEndX < touchStartX - threshold) {
            // Swipe Left -> Próximo Mês
            changeMonth(1);
        }
        if (touchEndX > touchStartX + threshold) {
            // Swipe Right -> Mês Anterior
            changeMonth(-1);
        }
    }
});
