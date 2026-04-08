/* js/dashboard_vendas.js */

document.addEventListener('DOMContentLoaded', function () {
    renderGanttVendas();
    initGanttDragScroll();
});

function renderGanttVendas(ganttData = null) {
    const chart = document.getElementById('gantt-vendas-chart');
    if (!chart) return;

    // Se novos dados foram passados via AJAX, atualizamos a variável global
    if (ganttData) {
        window.__ganttData = ganttData;

        // Atualiza o label do mês se disponível
        const label = document.getElementById('vendas-month-label');
        if (label && ganttData.mes_label) label.innerText = ganttData.mes_label;

        // Atualiza o campo oculto do mês
        const input = document.getElementById('vendas-mes-sel');
        if (input) {
            const m = String(ganttData.month).padStart(2, '0');
            input.value = `${ganttData.year}-${m}`;
        }
    }

    const data = window.__ganttData;
    const year = data.year;
    const month = data.month;
    const daysInMonth = data.daysInMonth;
    const docentes = data.docentes;

    let html = `
        <table class="gantt-table">
            <thead>
                <tr>
                    <th class="gantt-name-column gantt-day-header">Docente</th>
    `;

    for (let d = 1; d <= daysInMonth; d++) {
        const date = new Date(year, month - 1, d);
        const isWeekend = date.getDay() === 0 || date.getDay() === 6;
        html += `<th class="gantt-day-header ${isWeekend ? 'weekend' : ''}">${d}</th>`;
    }
    html += `</tr></thead><tbody>`;

    docentes.forEach(doc => {
        html += `
            <tr class="gantt-row" id="doc-row-${doc.id}">
                <td class="gantt-name-column">
                    <div class="gantt-name-inner">
                        <strong onclick="window.location.href='agenda_professores.php?docente_id=${doc.id}&month=${year}-${String(month).padStart(2, '0')}'" style="cursor:pointer;">${doc.nome}</strong>
                        <span>${doc.area || 'Docente'}</span>
                    </div>
                </td>
        `;

        for (let d = 1; d <= daysInMonth; d++) {
            const date = new Date(year, month - 1, d);
            const isWeekend = date.getDay() === 0 || date.getDay() === 6;
            const dayStr = `${year}-${String(month).padStart(2, '0')}-${String(d).padStart(2, '0')}`;

            html += `
                <td class="gantt-day-cell ${isWeekend ? 'weekend' : ''}" 
                    onclick="window.location.href='agenda_professores.php?docente_id=${doc.id}&month=${year}-${String(month).padStart(2, '0')}'">
                    <div class="gantt-track-container" id="track-${doc.id}-${d}">
                        <div class="gantt-track"></div>
                    </div>
                </td>`;
        }
        html += `</tr>`;
    });

    html += `</tbody></table>`;
    chart.innerHTML = html;

    // ← Aguarda o browser renderizar antes de desenhar as barras
    requestAnimationFrame(() => {
        drawGanttBarsCompact();
    });
}

function drawGanttBarsCompact() {
    const data = window.__ganttData;
    const docentes = data.docentes;
    const daysInMonth = data.daysInMonth;

    docentes.forEach(doc => {
        let currentSpan = null;

        for (let d = 1; d <= daysInMonth; d++) {
            const dateStr = `${data.year}-${String(data.month).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
            const dayStatus = getCompactDayStatus(doc, dateStr);

            const isSameAsPrevious = currentSpan &&
                currentSpan.type === dayStatus.type &&
                currentSpan.mainLabel === dayStatus.mainLabel &&
                currentSpan.count === dayStatus.count; // Não agrupa se a altura for diferente

            if (isSameAsPrevious) {
                currentSpan.end = d;
            } else {
                if (currentSpan) renderCompactBar(doc.id, currentSpan);
                currentSpan = {
                    start: d,
                    end: d,
                    ...dayStatus
                };
            }
        }
        if (currentSpan) renderCompactBar(doc.id, currentSpan);
    });
}

function getCompactDayStatus(doc, dateStr) {
    const events = (doc.events && doc.events[dateStr]) ? doc.events[dateStr] : [];
    const statusObj = doc.daily_status ? doc.daily_status[dateStr] : null;

    const date = new Date(dateStr + 'T00:00:00');
    if (date.getDay() === 0) {
        return { type: 'unavailable', mainLabel: 'Domingo', curso: 'Domingo', count: 3 };
    }

    if (!statusObj) {
        return { type: 'unavailable', mainLabel: 'Fora do Contrato', curso: 'Fora do Contrato', count: 3 };
    }

    // Prioridade 1: Aulas Confirmadas (Ocupado)
    // Se qualquer período for 'Ocupado', o dia todo fica vermelho
    const periods = ['Manhã', 'Tarde', 'Noite'];
    const busyPeriods = periods.filter(p => statusObj[p] === 'Ocupado');

    if (busyPeriods.length > 0) {
        const aulas = events.filter(e => e.type === 'AULA' || e.type === 'RESERVA_LEGADO');
        const label = aulas.length > 0 ? [...new Set(aulas.map(a => a.turma_nome || a.curso_nome))].join(' + ') : 'Ocupado';
        return {
            type: 'occupied',
            mainLabel: label,
            curso: label,
            count: busyPeriods.length
        };
    }

    // Prioridade 2: Reservas
    const reservedPeriods = periods.filter(p => statusObj[p] === 'Reservado');
    if (reservedPeriods.length > 0) {
        const resList = events.filter(e => e.type === 'RESERVA');
        const label = resList.length > 0 ? [...new Set(resList.map(r => r.sigla || r.curso_nome))].join(' + ') : 'Reservado';
        return {
            type: 'reserved',
            mainLabel: label,
            curso: label,
            count: reservedPeriods.length
        };
    }

    // Prioridade 3: Indisponibilidade Especial (Férias / Feriado)
    // Como a função getDailyStatusForMonth do PHP pula esses tipos no mapeamento de períodos,
    // buscamos diretamente nos eventos brutos.
    const critical = events.find(e => e.type === 'FERIAS' || e.type === 'FERIADO');
    if (critical) {
        const label = critical.type === 'FERIADO' ? (critical.name || 'FERIADO') : (critical.vacation_type === 'collective' ? 'FECHAMENTO' : 'FÉRIAS');
        return { type: 'holiday', mainLabel: label, curso: label, count: 3 };
    }

    // Prioridade 4: Preparação / Atestado
    const preps = events.filter(e => e.type === 'PREPARACAO');
    if (preps.length > 0) {
        const label = preps[0].tipo === 'atestado' ? 'ATESTADO' : 'PREPARAÇÃO';
        return { type: 'indisponivel', mainLabel: label, curso: label, count: 3 };
    }

    // Prioridade 5: Disponibilidade (Verde)
    const freePeriods = periods.filter(p => statusObj[p] === 'Livre');
    if (freePeriods.length > 0) {
        return { type: 'available', mainLabel: 'Disponível', curso: 'Disponível', count: freePeriods.length };
    }

    // Fallback: Fora do Contrato (Cinza)
    return { type: 'unavailable', mainLabel: 'Fora do Contrato', curso: 'Fora do Contrato', count: 3 };
}

function renderCompactBar(docId, span) {
    const startTrack = document.querySelector(`#track-${docId}-${span.start} .gantt-track`);
    if (!startTrack) return;

    const bar = document.createElement('div');
    bar.className = `gantt-bar ${span.type} height-p${span.count}`;

    const numDays = (span.end - span.start) + 1;

    const cellEl = document.querySelector('.gantt-day-cell');
    // ← Subtrai 1px de borda por célula para alinhamento perfeito
    const cellWidth = cellEl ? cellEl.offsetWidth - 1 : 79;

    bar.style.width = `${numDays * cellWidth}px`;
    bar.style.left = '-10px';
    bar.style.position = 'absolute';

    let labelHTML = `<div class="gantt-label-rich"><strong>${span.curso}</strong>`;
    if (span.local || span.ini) {
        let sub = span.local || "";
        if (span.ini) sub += ` (${formatDateGantt(span.ini)} a ${formatDateGantt(span.fim)})`;
        labelHTML += `<span>${sub}</span>`;
    }
    labelHTML += `</div>`;
    bar.innerHTML = labelHTML;

    bar.onclick = (e) => {
        e.stopPropagation();
        const monthStr = `${window.__ganttData.year}-${String(window.__ganttData.month).padStart(2, '0')}`;
        window.location.href = `agenda_professores.php?docente_id=${docId}&month=${monthStr}`;
    };

    startTrack.appendChild(bar);
}

function formatDateGantt(d) {
    if (!d) return "";
    const parts = d.split('-');
    return `${parts[2]}/${parts[1]}`;
}

function navigateVendasMonth(dir) {
    const current = document.getElementById('vendas-mes-sel').value;
    const parts = current.split('-');
    let year = parseInt(parts[0]);
    let month = parseInt(parts[1]) - 1;

    let date = new Date(year, month);
    date.setMonth(date.getMonth() + dir);

    const nextYear = date.getFullYear();
    const nextMonth = String(date.getMonth() + 1).padStart(2, '0');
    const newMesSel = `${nextYear}-${nextMonth}`;

    // Mostra loading
    const chart = document.getElementById('gantt-vendas-chart');
    if (chart) {
        chart.innerHTML = `<div class="gantt-loading"><i class="fas fa-spinner fa-spin"></i> Atualizando Calendário...</div>`;
    }

    // Busca via AJAX sem recarregar a página
    fetch(`dashboard_vendas.php?mes_sel=${newMesSel}&ajax=1`)
        .then(response => response.json())
        .then(data => {
            renderGanttVendas(data);
            // Reaplica o filtro de busca existente para o professor não sumir ao mudar o mês
            filterGanttDocentes();
        })
        .catch(err => {
            console.error("Erro na navegação AJAX:", err);
            // Fallback para reload se falhar
            window.location.href = 'dashboard_vendas.php?mes_sel=' + newMesSel;
        });
}

function initGanttDragScroll() {
    const slider = document.querySelector('.gantt-vendas-wrapper');
    if (!slider) return;

    let isDown = false;
    let startX, startY;
    let scrollLeft, scrollTop;

    slider.addEventListener('mousedown', (e) => {
        isDown = true;
        slider.style.cursor = 'grabbing';
        startX = e.pageX - slider.offsetLeft;
        startY = e.pageY - slider.offsetTop;
        scrollLeft = slider.scrollLeft;
        scrollTop = slider.scrollTop;
    });

    ['mouseleave', 'mouseup'].forEach(evt => {
        slider.addEventListener(evt, () => {
            isDown = false;
            slider.style.cursor = 'grab';
        });
    });

    slider.addEventListener('mousemove', (e) => {
        if (!isDown) return;
        e.preventDefault();
        const x = e.pageX - slider.offsetLeft;
        const y = e.pageY - slider.offsetTop;
        const walkX = (x - startX) * 1.5;
        const walkY = (y - startY) * 1.5;
        slider.scrollLeft = scrollLeft - walkX;
        slider.scrollTop = scrollTop - walkY;
    });
}

function filterGanttDocentes() {
    const term = document.getElementById('gantt-search-docente').value.toLowerCase();
    const rows = document.querySelectorAll('.gantt-row');
    rows.forEach(row => {
        const text = row.querySelector('.gantt-name-column').innerText.toLowerCase();
        row.style.display = text.includes(term) ? '' : 'none';
    });
}
