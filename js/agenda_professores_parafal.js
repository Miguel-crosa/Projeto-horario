/**
 * agenda_professores_parafal.js
 * JS para Agenda de Professores — portado do Parafal, adaptado para Miguel
 */

const currentMonthStart = document.querySelector('[name="month"]')?.value || new Date().toISOString().slice(0, 7);
let currentProfId = null;
let currentProfNome = "";
let currentViewMonth = currentMonthStart;

// Reservation Mode
let reservaModeActive = false;
let reservaSelectedDates = [];
let reservaCurrentProfId = null;
let reservaCurrentProfNome = "";

window.toggleReservaMode = function () {
    reservaModeActive = !reservaModeActive;
    const btn = document.getElementById('btn-modo-reserva-unificado');
    const label = document.getElementById('reservaModeLabel');
    if (reservaModeActive) {
        if (btn) btn.classList.add('active');
        if (label) label.textContent = 'Sair do Modo Reserva';
    } else {
        if (btn) btn.classList.remove('active');
        if (label) label.textContent = 'Modo Reserva';
        document.querySelectorAll('.bar-seg-selected').forEach(el => el.classList.remove('bar-seg-selected'));
    }
    reservaSelectedDates = [];
    reservaCurrentProfId = null;
    reservaCurrentProfNome = "";
    updateFloatingBar();
}

function handleReservaClick(profId, profNome, dateStr, element) {
    if (!reservaModeActive) return false;
    if (reservaCurrentProfId && reservaCurrentProfId !== profId) {
        reservaSelectedDates = [];
        document.querySelectorAll('.bar-seg-selected').forEach(el => el.classList.remove('bar-seg-selected'));
    }
    reservaCurrentProfId = profId;
    reservaCurrentProfNome = profNome;
    const idx = reservaSelectedDates.findIndex(d => d.date === dateStr);
    if (idx >= 0) {
        reservaSelectedDates.splice(idx, 1);
        if (element) element.classList.remove('bar-seg-selected');
    } else {
        reservaSelectedDates.push({ date: dateStr, profId, profNome });
        if (element) element.classList.add('bar-seg-selected');
    }
    updateFloatingBar();
    return true;
}

function updateFloatingBar() {
    const bar = document.getElementById('reservaFloatingBar');
    if (!bar) return;
    if (reservaModeActive && reservaSelectedDates.length > 0) {
        bar.classList.add('visible');
        document.getElementById('rfSelectedCount').textContent = reservaSelectedDates.length;
        document.getElementById('rfProfName').textContent = reservaCurrentProfNome;
    } else {
        bar.classList.remove('visible');
    }
}

window.cancelReservaSelection = function () {
    reservaSelectedDates = [];
    document.querySelectorAll('.bar-seg-selected').forEach(el => el.classList.remove('bar-seg-selected'));
    updateFloatingBar();
}

function openReservaConfirmModal() {
    if (reservaSelectedDates.length === 0) return;
    const sorted = [...reservaSelectedDates].sort((a, b) => a.date.localeCompare(b.date));
    const firstDate = sorted[0].date;
    const lastDate = sorted[sorted.length - 1].date;
    const dowSet = new Set();
    const dowNames = { 1: 'Seg', 2: 'Ter', 3: 'Qua', 4: 'Qui', 5: 'Sex', 6: 'Sáb' };
    sorted.forEach(d => {
        const dt = new Date(d.date + 'T00:00:00');
        let dow = dt.getDay(); if (dow === 0) dow = 7;
        if (dow <= 6) dowSet.add(dow);
    });
    const dowList = [...dowSet].sort();
    const dowLabels = dowList.map(d => dowNames[d] || d);
    document.getElementById('reserva_summary').innerHTML =
        `<i class="fas fa-user"></i> Professor: <strong>${reservaCurrentProfNome}</strong><br>` +
        `<i class="fas fa-calendar-alt"></i> Período: ${formatDateBR(firstDate)} a ${formatDateBR(lastDate)}<br>` +
        `<i class="fas fa-calendar-week"></i> Dias: ${dowLabels.join(', ')} (${sorted.length} dia(s))`;
    const modal = document.getElementById('reservaModal');
    modal.dataset.profId = reservaCurrentProfId;
    modal.dataset.dateStart = firstDate;
    modal.dataset.dateEnd = lastDate;
    modal.dataset.diasSemana = dowList.join(',');
    document.getElementById('reserva_error').style.display = 'none';
    modal.classList.add('active');
}

function formatDateBR(dateStr) {
    const [y, m, d] = dateStr.split('-');
    return `${d}/${m}/${y}`;
}

async function submitReserva() {
    const modal = document.getElementById('reservaModal');
    const btn = document.getElementById('reservaConfirmBtn');
    const errDiv = document.getElementById('reserva_error');
    errDiv.style.display = 'none';

    // Saturday Night Constraint check
    const hInicio = document.getElementById('reserva_hora_inicio').value;
    const hFim = document.getElementById('reserva_hora_fim').value;
    const turno = getSelectedTurno(hInicio, hFim);

    if (turno.N) {
        const hasSab = reservaSelectedDates.some(dtObj => {
            const d = new Date(dtObj.date + 'T00:00:00').getUTCDay();
            return d === 6; // Sat
        });
        if (hasSab) {
            errDiv.textContent = "Aviso: Reservas no período 'Noite' não são permitidas aos Sábados. Por favor, ajuste o horário.";
            errDiv.style.display = 'block';
            return;
        }
    }

    // Basic validation for reservaModal
    if (!hInicio || !hFim) {
        errDiv.textContent = "Por favor, preencha o horário de início e fim.";
        errDiv.style.display = 'block';
        return;
    }
    if (hInicio >= hFim) {
        errDiv.textContent = "A hora de início deve ser anterior à hora de fim.";
        errDiv.style.display = 'block';
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Reservando...';
    const formData = new FormData();
    formData.append('ajax_create_reserva', '1');
    formData.append('professor_id', modal.dataset.profId);
    formData.append('data_inicio', modal.dataset.dateStart);
    formData.append('data_fim', modal.dataset.dateEnd);
    formData.append('dias_semana', modal.dataset.diasSemana);
    formData.append('hora_inicio', hInicio);
    formData.append('hora_fim', hFim);
    formData.append('notas', document.getElementById('reserva_notas').value);
    try {
        const resp = await fetch('agenda_professores.php', { method: 'POST', body: formData });
        const data = await resp.json();
        if (data.ok) {
            closeModal('reservaModal');
            reservaSelectedDates = [];
            reservaModeActive = false;
            const toggleBtn = document.getElementById('btn-modo-reserva-unificado');
            if (toggleBtn) toggleBtn.classList.remove('active');
            const label = document.getElementById('reservaModeLabel');
            if (label) label.textContent = 'Modo Reserva';
            updateFloatingBar();
            alert(data.msg || 'Reserva criada com sucesso!');
            location.reload();
        } else {
            errDiv.textContent = data.error || 'Erro ao criar reserva.';
            errDiv.style.display = 'block';
        }
    } catch (e) {
        errDiv.textContent = 'Erro de rede ao criar reserva.';
        errDiv.style.display = 'block';
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-bookmark"></i> Reservar';
    }
}

function handleBarClick(profId, profNome, dateStr, element) {
    if (reservaModeActive) {
        handleReservaClick(profId, profNome, dateStr, element);
    } else {
        openScheduleModal(profId, profNome, dateStr);
    }
}

function openScheduleModal(profId, profNome, date) {
    // Sync with calendar.js expectations
    const docenteSelect = document.getElementById('calendar-docente-select');
    if (docenteSelect) {
        docenteSelect.value = profId;
        docenteSelect.dispatchEvent(new Event('change'));
    }

    const clickedDate = new Date(date + 'T00:00:00');
    let dow = clickedDate.getDay(); if (dow === 0) dow = 7;
    const monday = new Date(clickedDate); monday.setDate(clickedDate.getDate() - (dow - 1));
    const saturday = new Date(monday); saturday.setDate(monday.getDate() + 5);
    const formatDate = (d) => d.toISOString().split('T')[0];

    const start = formatDate(monday);
    const end = formatDate(saturday);

    if (window.openCalendarScheduleModal) {
        window.openCalendarScheduleModal(start, end);
    } else {
        // Fallback to simpler modal if calendar.js is not loaded
        document.getElementById('form_prof_id').value = profId;
        document.getElementById('form_date_start').value = start;
        document.getElementById('form_date_end').value = end;
        document.getElementById('schedule_info').innerHTML = `<i class="fas fa-user"></i> Professor: ${profNome} <br> <i class="fas fa-calendar-alt"></i> Semana: ${monday.toLocaleDateString('pt-BR')} – ${saturday.toLocaleDateString('pt-BR')}`;
        document.getElementById('scheduleModal').classList.add('active');
        if (typeof resetWeekdayCheckboxes === 'function') resetWeekdayCheckboxes();
        if (typeof checkWeekdayBlocking === 'function') checkWeekdayBlocking();
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
        let clickHandler = clickable ? `onclick="handleBarClick(${profId}, '${profNome}', '${dStr}', this)"` : '';
        html += `<div class="calendar-day ${statusClass} ${weekendClass}" ${clickHandler} data-date="${dStr}"><div class="day-number">${i}</div><div class="day-status-label">${statusLabel}</div>${extraHtml}</div>`;
    }
    html += `</div></div>`;
    container.innerHTML = html;
}

// Weekday blocking
const dayNamesGlobal = { 1: 'Segunda', 2: 'Terça', 3: 'Quarta', 4: 'Quinta', 5: 'Sexta', 6: 'Sábado' };
const turnoLabelsGlobal = { M: '☀ M', T: '☁ T', N: '☽ N' };

function toggleWeekdayCard(dayNum) {
    const cb = document.getElementById('weekday_' + dayNum);
    if (cb && !cb.disabled) {
        cb.checked = !cb.checked;

        // Auto-sync Date End if selecting a day further in the week
        if (cb.checked) {
            const dateStartStr = document.getElementById('form_date_start')?.value;
            const dateEndInput = document.getElementById('form_date_end');
            if (dateStartStr && dateEndInput) {
                const start = new Date(dateStartStr + 'T00:00:00');
                const dowStart = start.getDay(); // 0(Sun) to 6(Sat)
                const startDowAdjusted = dowStart === 0 ? 7 : dowStart; // Map to 1-7

                // Calculate the date for the clicked dayNum in the same week
                const diff = dayNum - startDowAdjusted;
                const targetDate = new Date(start);
                targetDate.setDate(start.getDate() + diff);

                const currentEnd = new Date(dateEndInput.value + 'T00:00:00');
                if (targetDate > currentEnd) {
                    dateEndInput.value = targetDate.toISOString().split('T')[0];
                }
            }
        }

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
});
