/**
 * Dashboard da Agenda do Professor - Projeto Horário
 * Gerencia o modal de resumo mensal para professores.
 */

document.addEventListener('DOMContentLoaded', () => {
    let summaryCurrentProfId = null;
    let summaryCurrentDate = new Date();
    const mesesNomes = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

    window.openTeacherAgenda = function (profId, profName, monthStr = null) {
        summaryCurrentProfId = profId;
        document.getElementById('summary-prof-name').textContent = profName;

        if (monthStr) {
            const parts = monthStr.split('-');
            summaryCurrentDate = new Date(parts[0], parts[1] - 1, 1);
        } else {
            const now = new Date();
            summaryCurrentDate = new Date(now.getFullYear(), now.getMonth(), 1);
        }

        const modal = document.getElementById('teacherMonthlySummaryModal');
        if (modal) {
            modal.classList.add('active');
            loadSummaryAgenda();
        }
    };

    function loadSummaryAgenda() {
        const area = document.getElementById('summary-calendar-area');
        area.innerHTML = '<div style="text-align: center; padding: 50px;"><div class="spinner-border text-danger" role="status"></div><p style="margin-top:15px;">Carregando agenda...</p></div>';

        const year = summaryCurrentDate.getFullYear();
        const month = String(summaryCurrentDate.getMonth() + 1).padStart(2, '0');
        const monthLabel = mesesNomes[summaryCurrentDate.getMonth()] + ' ' + year;
        document.getElementById('summary-current-month').textContent = monthLabel;

        const url = `php/controllers/agenda_api.php?action=get_docente_agenda&docente_id=${summaryCurrentProfId}&month=${year}-${month}`;

        fetch(url)
            .then(r => r.json())
            .then(data => {
                renderSummaryCalendar(data.agendas || []);
            })
            .catch(err => {
                console.error('Erro ao carregar agenda:', err);
                area.innerHTML = '<div class="alert alert-danger">Erro ao carregar os dados da agenda.</div>';
            });
    }

    function renderSummaryCalendar(agendas) {
        const area = document.getElementById('summary-calendar-area');
        const year = summaryCurrentDate.getFullYear();
        const month = summaryCurrentDate.getMonth();
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const startDayOfWeek = firstDay.getDay();
        const totalDays = lastDay.getDate();

        let html = `<div class="cal-grid" style="display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 5px; background: rgba(0,0,0,0.05); padding: 10px; border-radius: 12px;">`;

        const diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
        diasSemana.forEach(d => {
            html += `<div style="text-align: center; font-weight: 800; font-size: 0.75rem; color: var(--text-muted); padding: 10px 0;">${d}</div>`;
        });

        for (let i = 0; i < startDayOfWeek; i++) {
            html += `<div style="min-height: 120px; background: rgba(255,255,255,0.02); border-radius: 8px;"></div>`;
        }

        for (let day = 1; day <= totalDays; day++) {
            const dateObj = new Date(year, month, day);
            const dateISO = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
            const dayOfWeek = dateObj.getDay();
            const isToday = new Date().toISOString().split('T')[0] === dateISO;

            // Filtrar aulas do dia usando a data exata da API expandida
            const aulasNoDia = agendas.filter(a => a.agenda_data === dateISO && a.type !== 'WORK_SCHEDULE');
            const scheduleRecords = agendas.filter(a => a.agenda_data === dateISO && a.type === 'WORK_SCHEDULE');
            const hasAgendamento = scheduleRecords.length > 0;
            const hasGlobalSchedule = agendas.some(a => a.type === 'WORK_SCHEDULE');

            let contentHtml = '';
            let bg = 'var(--bg-card)';
            let borderColor = 'var(--border-color)';

            if (dayOfWeek === 0) {
                bg = 'rgba(0,0,0,0.05)';
            } else if (hasGlobalSchedule && !hasAgendamento) {
                // Se o professor tem disponibilidade cadastrada no mês, mas não nesse dia
                bg = 'rgba(0,0,0,0.2)';
            }

            if (aulasNoDia.length > 0) {
                aulasNoDia.forEach(a => {
                    const isRes = a.type === 'RESERVA' || a.type === 'RESERVA_LEGADO' || a.status === 'RESERVADO';
                    const isHoliday = a.type === 'FERIADO';
                    const isVacation = a.type === 'FERIAS';
                    const isPrep = a.type === 'PREPARACAO';

                    let color = 'var(--primary-red)';
                    if (isRes) color = '#ffb300'; // Laranja para reservas
                    if (isHoliday || isVacation) color = '#1565c0'; // Azul para Feriado/Férias
                    if (isPrep) color = '#e53935'; // Vermelho para Preparação/Atestado

                    const timeStr = (isHoliday || isVacation) ? '' : (a.horario_inicio ? a.horario_inicio.substring(0, 5) : (a.periodo || ''));
                    const label = (isRes && a.sigla) ? a.sigla : (a.curso_nome || a.turma_nome || 'Reservado');

                    contentHtml += `<div style="font-size: 0.75rem; background: ${color}1a; color: ${color}; padding: 4px 6px; border-radius: 4px; margin-top: 2px; border-left: 3px solid ${color}; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: default; font-weight: 600;" title="${label} ${timeStr ? '(' + timeStr + ')' : ''}">
                        ${timeStr ? '<strong>' + timeStr + '</strong>: ' : ''}${label}
                    </div>`;
                });
            }

            html += `<div style="min-height: 120px; min-width: 0; background: ${bg}; border: 1px solid ${isToday ? 'var(--primary-red)' : borderColor}; border-radius: 8px; padding: 5px; display: flex; flex-direction: column; position: relative; ${isToday ? 'box-shadow: 0 0 10px rgba(229,57,53,0.2);' : ''}">
                <span style="font-size: 0.8rem; font-weight: 800; opacity: 0.6; margin-bottom: 5px;">${day}</span>
                <div style="flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 2px;">
                    ${contentHtml}
                </div>
            </div>`;
        }

        html += `</div>`;
        area.innerHTML = html;
    }

    document.getElementById('btn-prev-month-summary')?.addEventListener('click', () => {
        const year = summaryCurrentDate.getFullYear();
        const month = summaryCurrentDate.getMonth();
        summaryCurrentDate = new Date(year, month - 1, 1);
        loadSummaryAgenda();
    });

    document.getElementById('btn-next-month-summary')?.addEventListener('click', () => {
        const year = summaryCurrentDate.getFullYear();
        const month = summaryCurrentDate.getMonth();
        summaryCurrentDate = new Date(year, month + 1, 1);
        loadSummaryAgenda();
    });
});
