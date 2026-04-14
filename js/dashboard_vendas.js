/* js/dashboard_vendas.js */

document.addEventListener('DOMContentLoaded', function () {
    renderGanttVendas();
    initGanttDragScroll();
    window.addEventListener('resize', () => {
        const data = window.__ganttData;
        if (data) {
            applyDynamicDayWidth(data.daysInMonth);
            document.querySelectorAll('.gantt-track .gantt-bar').forEach(b => b.remove());
            drawGanttBarsCompact();
        }
    });
});

function applyDynamicDayWidth(daysInMonth) {
    const wrapper = document.querySelector('.gantt-vendas-wrapper');
    if (!wrapper) return;

    // Pega a largura da coluna de nome diretamente do CSS computado
    const nameCol = document.querySelector('.gantt-name-column');
    const nameWidth = nameCol ? nameCol.offsetWidth : 145;

    const wrapperWidth = wrapper.clientWidth;
    const availableWidthForDays = wrapperWidth - nameWidth - 10;

    // Na largura total, cada dia teria:
    let dayWidth = (availableWidthForDays / daysInMonth);

    // No celular, não deixamos o dia ficar menor que 35px para manter legibilidade
    const isMobile = window.innerWidth <= 768;
    const minDayWidth = isMobile ? 44 : 28;

    if (dayWidth < minDayWidth) {
        dayWidth = minDayWidth;
    }

    // Aplica em todos os th e td de dia (não na coluna de nome)
    document.querySelectorAll('.gantt-day-header:not(.gantt-name-column), .gantt-day-cell')
        .forEach(el => {
            el.style.width = dayWidth + 'px';
            el.style.minWidth = dayWidth + 'px';
        });

    // Guarda para uso no drawGanttBarsCompact
    window.__dayWidth = dayWidth;
}

function renderGanttVendas(ganttData = null) {
    const chart = document.getElementById('gantt-vendas-chart');
    if (!chart) return;

    if (ganttData) {
        window.__ganttData = ganttData;
        const label = document.getElementById('vendas-month-label');
        if (label && ganttData.mes_label) label.innerText = ganttData.mes_label;
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

    // Calcula largura ANTES de gerar o HTML
    const wrapper = document.querySelector('.gantt-vendas-wrapper');
    const isMobile = window.innerWidth <= 768;

    // Valor inicial aproximado para o primeiro render
    let nameWidth = isMobile ? 100 : 145;
    let dayWidth = 35;

    // Se o wrapper já existir (re-render), tentamos medir
    if (wrapper) {
        const nameCol = document.querySelector('.gantt-name-column');
        if (nameCol) nameWidth = nameCol.offsetWidth;

        const availableWidth = wrapper.clientWidth - nameWidth - 10;
        dayWidth = (availableWidth / daysInMonth);
        const minDayWidth = isMobile ? 38 : 28;
        if (dayWidth < minDayWidth) dayWidth = minDayWidth;
    }

    window.__dayWidth = dayWidth;

    const dayStyle = `style="width:${dayWidth}px;min-width:${dayWidth}px;"`;

    let html = `
        <table class="gantt-table">
            <thead>
                <tr>
                    <th class="gantt-name-column gantt-day-header">Docente</th>
    `;

    const dayNamesShort = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sab'];
    for (let d = 1; d <= daysInMonth; d++) {
        const date = new Date(year, month - 1, d);
        const isWeekend = date.getDay() === 0 || date.getDay() === 6;
        const dayLabel = dayNamesShort[date.getDay()];
        html += `<th class="gantt-day-header ${isWeekend ? 'weekend' : ''}" ${dayStyle}><small class="gantt-day-name">${dayLabel}</small><br><span class="gantt-day-num">${d}</span></th>`;
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
            html += `
                <td class="gantt-day-cell ${isWeekend ? 'weekend' : ''}" ${dayStyle}>
                    <div class="gantt-track-container" id="track-${doc.id}-${d}">
                        <div class="gantt-track"></div>
                    </div>
                </td>`;
        }
        html += `</tr>`;
    });

    html += `</tbody></table>`;
    chart.innerHTML = html;

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

    const isSmallMobile = window.innerWidth <= 480;
    const arrowOffset = isSmallMobile ? 5 : 8;

    if (!isSmallMobile) {
        // Restaurar lógica original para Desktop (evita quebra visual que o usuário relatou)
        let totalWidthDesktop = 0;
        for (let i = span.start; i <= span.end; i++) {
            const targetCell = document.querySelector(`#track-${docId}-${i}`);
            if (targetCell) {
                const cellElement = targetCell.closest('.gantt-day-cell');
                totalWidthDesktop += cellElement ? cellElement.getBoundingClientRect().width : (window.__dayWidth || 30);
            }
        }
        bar.style.width = `${totalWidthDesktop}px`;
        bar.style.left = '-12px';
    } else {
        // Lógica otimizada para Mobile (encaixe de setas e alinhamento preciso)
        const numDays = (span.end - span.start) + 1;
        const dayW = window.__dayWidth || 44;
        const totalW = numDays * dayW;
        bar.style.width = `${totalW + (2 * arrowOffset)}px`;
        bar.style.left = `-${arrowOffset}px`;
    }

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
        if (span.type === 'available') {
            const monthStr = `${window.__ganttData.year}-${String(window.__ganttData.month).padStart(2, '0')}`;
            window.location.href = `agenda_professores.php?docente_id=${docId}&month=${monthStr}`;
        }
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

    // Mouse Events
    slider.addEventListener('mousedown', (e) => {
        isDown = true;
        slider.style.cursor = 'grabbing';
        startX = e.pageX - slider.offsetLeft;
        startY = e.pageY - slider.offsetTop;
        scrollLeft = slider.scrollLeft;
        scrollTop = slider.scrollTop;
    });

    ['mouseleave', 'mouseup'].forEach(evt => {
        window.addEventListener(evt, () => {
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

    // Touch Events
    slider.addEventListener('touchstart', (e) => {
        isDown = true;
        startX = e.touches[0].pageX - slider.offsetLeft;
        startY = e.touches[0].pageY - slider.offsetTop;
        scrollLeft = slider.scrollLeft;
        scrollTop = slider.scrollTop;
    }, { passive: false });

    slider.addEventListener('touchend', () => {
        isDown = false;
    });

    slider.addEventListener('touchmove', (e) => {
        if (!isDown) return;
        const x = e.touches[0].pageX - slider.offsetLeft;
        const y = e.touches[0].pageY - slider.offsetTop;
        
        const walkX = (x - startX) * 1.5;
        const walkY = (y - startY) * 1.5;

        // Se o movimento for predominantemente horizontal, impedimos o scroll nativo da página
        if (Math.abs(walkX) > Math.abs(walkY)) {
            e.preventDefault();
        }

        slider.scrollLeft = scrollLeft - walkX;
        slider.scrollTop = scrollTop - walkY;
    }, { passive: false });
}

function filterGanttDocentes() {
    const term = document.getElementById('gantt-search-docente').value.toLowerCase();
    const rows = document.querySelectorAll('.gantt-row');
    rows.forEach(row => {
        const text = row.querySelector('.gantt-name-column').innerText.toLowerCase();
        row.style.display = text.includes(term) ? '' : 'none';
    });
}
