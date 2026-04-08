document.addEventListener('DOMContentLoaded', () => {
    const toggleBtns = document.querySelectorAll('.view-toggle-btn');
    const calView = document.getElementById('calendar-view');
    const ganttView = document.getElementById('gantt-view');
    const availSection = document.getElementById('availability-section');
    const docenteSelect = document.getElementById('calendar-docente-select');

    toggleBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            toggleBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const view = this.dataset.view;
            if (view === 'calendar') {
                calView.style.display = 'block';
                ganttView.style.display = 'none';
            } else {
                calView.style.display = 'none';
                ganttView.style.display = 'block';
            }
        });
    });

    const periodLabel = document.createElement('div');
    periodLabel.id = 'current-period-display';
    periodLabel.style.cssText = 'font-size: 0.85rem; font-weight: 700; color: var(--primary-red); margin-left: auto;';
    document.querySelector('.period-selector-container')?.appendChild(periodLabel);

    let currentPeriod = { inicio: '07:30', fim: '11:30', label: 'Manhã' };

    async function updatePeriodStatus(docenteId) {
        if (!docenteId) return;
        const startInput = document.querySelector('input[name="data_inicio"]');
        const endInput = document.querySelector('input[name="data_fim"]');
        const start = startInput ? startInput.value : '';
        const end = endInput ? endInput.value : '';

        const hStart = currentPeriod.inicio;
        const hEnd = currentPeriod.fim;

        try {
            const resp = await fetch(`../controllers/check_availability.php?docente_id=${docenteId}&data_inicio=${start}&data_fim=${end}&h_start=${hStart}&h_end=${hEnd}`);
            const data = await resp.json();

            // Update period icons
            const status = data.periods;
            document.querySelectorAll('.period-btn').forEach(btn => {
                const label = btn.textContent.trim();
                const icon = btn.querySelector('.status-icon');
                if (icon) {
                    if (btn.classList.contains('active')) {
                        icon.style.color = 'var(--primary-red)';
                    } else if (status[label]) {
                        icon.style.color = status[label] === 'free' ? '#66bb6a' : '#ef5350';
                    }
                }
            });

            // Disable busy days checkboxes
            const busyDays = data.busy_days || [];
            document.querySelectorAll('.dia-checkbox-label').forEach(label => {
                const checkbox = label.querySelector('input');
                const dayName = label.querySelector('.dia-checkbox-text').textContent.trim();

                if (busyDays.includes(dayName)) {
                    checkbox.disabled = true;
                    label.style.opacity = '0.5';
                    label.title = 'Professor ocupado neste período';
                    checkbox.checked = false;
                } else {
                    checkbox.disabled = false;
                    label.style.opacity = '1';
                    label.title = '';
                }
            });
        } catch (e) { console.error('Error fetching status:', e); }
    }

    const periodBtns = document.querySelectorAll('.period-btn');

    periodBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            periodBtns.forEach(b => {
                b.classList.remove('active');
                b.style.backgroundColor = '';
                b.style.color = '';
                // Reset internal icon if needed, but usually icons represent status
            });
            this.classList.add('active');
            this.style.backgroundColor = 'var(--primary-red)';
            this.style.color = '#fff';

            currentPeriod.inicio = this.dataset.inicio;
            currentPeriod.fim = this.dataset.fim;
            currentPeriod.label = this.textContent.trim();

            periodLabel.textContent = `Período Ativo: ${currentPeriod.label}`;

            const modalInicio = document.querySelector('input[name="horario_inicio"]');
            const modalFim = document.querySelector('input[name="horario_fim"]');
            if (modalInicio && modalFim) {
                modalInicio.value = currentPeriod.inicio;
                modalFim.value = currentPeriod.fim;
            }

            // Sync with global calendar period state
            window.calendarCurrentPeriod = this.dataset.periodo || null;

            if (docenteSelect && docenteSelect.value) updatePeriodStatus(docenteSelect.value);

            // Re-render calendar to show chosen period logic/highlight if needed
            if (window.renderCalendar) window.renderCalendar();

            // Reload if not in calendar/gantt mode to apply global filter
            const urlParams = new URLSearchParams(window.location.search);
            const viewMode = urlParams.get('view_mode') || 'timeline';
            if (viewMode !== 'calendar' && viewMode !== 'gantt' && window.location.pathname.includes('agenda_professores.php')) {
                const currentHrefPeriod = urlParams.get('periodo') || '';
                const newPeriod = this.dataset.periodo;
                if (currentHrefPeriod !== newPeriod) {
                    if (newPeriod) {
                        urlParams.set('periodo', newPeriod);
                    } else {
                        urlParams.delete('periodo');
                    }
                    urlParams.delete('page');
                    window.location.search = urlParams.toString();
                }
            }
        });
    });

    const currentUrlPeriod = (new URLSearchParams(window.location.search)).get('periodo');
    if (periodBtns.length > 0) {
        let matched = false;
        if (currentUrlPeriod) {
            const btn = Array.from(periodBtns).find(b => b.dataset.periodo === currentUrlPeriod);
            if (btn) {
                btn.click();
                matched = true;
            }
        }
        if (!matched) periodBtns[0].click();
    }

    if (docenteSelect) {
        docenteSelect.addEventListener('change', function () {
            if (this.value) {
                if (availSection) availSection.style.display = 'block';
                const btnAgendar = document.getElementById('btn-agendar-bar');
                if (btnAgendar) btnAgendar.style.display = 'inline-flex';
                updatePeriodStatus(this.value);
                if (typeof loadDocenteAgenda === 'function') loadDocenteAgenda(this.value);
            } else {
                if (availSection) availSection.style.display = 'none';
                const btnAgendar = document.getElementById('btn-agendar-bar');
                if (btnAgendar) btnAgendar.style.display = 'none';
            }
        });
    }

    // Refresh icons when dates change in modal
    document.addEventListener('change', (e) => {
        if (e.target.name === 'data_inicio' || e.target.name === 'data_fim') {
            if (docenteSelect.value) updatePeriodStatus(docenteSelect.value);
        }
    });

    // URL Parameter handling (from Simulation)
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('data_inicio')) {
        const start = urlParams.get('data_inicio');
        const end = urlParams.get('data_fim');
        const hStart = urlParams.get('h_start');
        const hEnd = urlParams.get('h_end');
        const periodoStr = urlParams.get('periodo');
        const diasSem = urlParams.getAll('dias_semana[]');
        const docenteId = urlParams.get('docente_id');
        const ambienteId = urlParams.get('ambiente_id');
        const cursoId = urlParams.get('curso_id');

        // Pre-fill selects if present
        let loadPromise = null;
        if (docenteId && window.loadDocenteAgenda) {
            if (docenteSelect) docenteSelect.value = docenteId;
            loadPromise = window.loadDocenteAgenda(docenteId, start);

            // Show the availability section and schedule button since a teacher is selected
            if (availSection) availSection.style.display = 'block';
            const btnAgendar = document.getElementById('btn-agendar-bar');
            if (btnAgendar) btnAgendar.style.display = 'inline-flex';
        }

        const modalIn = document.querySelector('#form-agendar-calendar input[name="data_inicio"]');
        const modalOut = document.querySelector('#form-agendar-calendar input[name="data_fim"]');
        const modalHIn = document.querySelector('#form-agendar-calendar input[name="horario_inicio"]');
        const modalHOut = document.querySelector('#form-agendar-calendar input[name="horario_fim"]');
        const modalPeriodo = document.getElementById('modal-cal-periodo');
        const modalAmbiente = document.querySelector('#form-agendar-calendar select[name="ambiente_id"]');

        if (modalIn) modalIn.value = start;
        if (modalOut) modalOut.value = end;
        if (modalHIn) modalHIn.value = hStart;
        if (modalHOut) modalHOut.value = hEnd;
        if (modalPeriodo) modalPeriodo.value = periodoStr || 'Manhã';
        if (modalAmbiente && ambienteId) modalAmbiente.value = ambienteId;

        const modalCursoId = document.getElementById('modal-cal-curso-id');
        if (modalCursoId && cursoId) {
            modalCursoId.value = cursoId;
            // If we have a course but no turma, we make turma_id not required
            const selectTurma = document.querySelector('select[name="turma_id"]');
            if (selectTurma) selectTurma.removeAttribute('required');
        }

        // Check checkboxes
        document.querySelectorAll('#form-agendar-calendar input[name="dias_semana[]"]').forEach(cb => {
            cb.checked = diasSem.includes(cb.value);
        });

        // Match period button visually
        if (periodBtns) {
            // Prioritize matching by period label if available, otherwise match by start and end times
            let matchingBtn = null;
            if (periodoStr) {
                matchingBtn = Array.from(periodBtns).find(b => b.textContent.trim() === periodoStr);
            }
            if (!matchingBtn && hStart) {
                matchingBtn = Array.from(periodBtns).find(b => b.dataset.inicio === hStart && (!hEnd || b.dataset.fim === hEnd));
            }
            if (!matchingBtn && hStart) {
                matchingBtn = Array.from(periodBtns).find(b => b.dataset.inicio === hStart);
            }
            if (matchingBtn) matchingBtn.click();
        }

        // Open modal only if auto_open is present
        if (urlParams.has('auto_open') && window.openCalendarScheduleModal) {
            if (loadPromise && typeof loadPromise.then === 'function') {
                loadPromise.then(() => window.openCalendarScheduleModal(start, end));
            } else {
                window.openCalendarScheduleModal(start, end);
            }
        }
    } else {
        // Auto-load if a professor is already selected on page load (but no data_inicio param)
        if (docenteSelect && docenteSelect.value) {
            if (availSection) availSection.style.display = 'block';
            const btnAgendar = document.getElementById('btn-agendar-bar');
            if (btnAgendar) btnAgendar.style.display = 'inline-flex';
            setTimeout(() => {
                if (typeof window.loadDocenteAgenda === 'function') {
                    // Start focusDate as null
                    let focusDate = null;
                    const urlMonth = (new URLSearchParams(window.location.search)).get('month');
                    if (urlMonth && urlMonth.length === 7) {
                        focusDate = urlMonth + '-01';
                    }
                    window.loadDocenteAgenda(docenteSelect.value, focusDate);
                    // updatePeriodStatus is usually called inside loadDocenteAgenda but we can ensure it
                    if (window.updatePeriodStatus) window.updatePeriodStatus(docenteSelect.value);
                }
            }, 100);
        }
    }
});
