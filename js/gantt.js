document.addEventListener('DOMContentLoaded', () => {
    const chart = document.getElementById('gantt-chart'), tooltip = document.getElementById('gantt-tooltip'), dataEl = document.getElementById('gantt-data');
    if (!chart || !dataEl) return;
    const ganttData = JSON.parse(dataEl.textContent), timelineStart = new Date(ganttData.timeline_start), timelineEnd = new Date(ganttData.timeline_end);
    const totalDays = Math.ceil((timelineEnd - timelineStart) / 864e5), docenteSelect = document.getElementById('calendar-docente-select');
    if (docenteSelect) docenteSelect.addEventListener('change', renderGantt);

    const ganttNav = document.createElement('div');
    ganttNav.className = 'gantt-nav-controls';
    ganttNav.style.cssText = 'display: flex; gap: 10px; margin-bottom: 15px; align-items: center;';
    ganttNav.innerHTML = `
        <button class="btn btn-sm" id="gantt-prev"><i class="fas fa-chevron-left"></i> Anterior</button>
        <span id="gantt-month-label" style="font-weight: 700; min-width: 120px; text-align: center;"></span>
        <button class="btn btn-sm" id="gantt-next">Próximo <i class="fas fa-chevron-right"></i></button>
    `;
    chart.parentNode.insertBefore(ganttNav, chart);

    let ganttViewDate = new Date(timelineStart.getFullYear(), timelineStart.getMonth(), 1);

    document.getElementById('gantt-prev').onclick = () => { ganttViewDate.setMonth(ganttViewDate.getMonth() - 1); renderGantt(); };
    document.getElementById('gantt-next').onclick = () => { ganttViewDate.setMonth(ganttViewDate.getMonth() + 1); renderGantt(); };

    const meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

    function renderGantt() {
        chart.innerHTML = '';
        const sid = docenteSelect?.value;
        const filtered = sid ? ganttData.docentes.filter(d => d.id == sid) : ganttData.docentes;

        const viewEnd = new Date(ganttViewDate.getFullYear(), ganttViewDate.getMonth() + 3, 0); // Show 3 months range
        document.getElementById('gantt-month-label').textContent = meses[ganttViewDate.getMonth()] + ' ' + ganttViewDate.getFullYear();

        if (!filtered.length) { chart.innerHTML = '<div class="td-empty">Selecione um professor.</div>'; return; }
        buildHeader(ganttViewDate, viewEnd);
        filtered.forEach(d => buildRow(d, ganttViewDate, viewEnd));
    }

    function buildHeader(vs, ve) {
        const h = document.createElement('div'); h.className = 'gantt-header';
        const l = document.createElement('div'); l.className = 'gantt-header-label'; l.textContent = 'Professor'; h.appendChild(l);
        const t = document.createElement('div'); t.className = 'gantt-header-timeline';
        let c = new Date(vs);
        const viewTotalDays = Math.ceil((ve - vs) / 864e5) + 1;
        while (c < ve) {
            const ms = new Date(c), me = new Date(c.getFullYear(), c.getMonth() + 1, 0), e = me > ve ? ve : me;
            const days = Math.ceil((e - ms) / 864e5) + 1, w = (days / viewTotalDays * 100);
            const m = document.createElement('div'); m.className = 'gantt-month'; m.style.flex = `0 0 ${w}%`; m.textContent = meses[c.getMonth()] + ' ' + c.getFullYear();
            t.appendChild(m); c = new Date(c.getFullYear(), c.getMonth() + 1, 1);
        }
        h.appendChild(t); chart.appendChild(h);
    }

    function buildRow(doc, vs, ve) {
        const r = document.createElement('div'); r.className = 'gantt-row';
        const l = document.createElement('div'); l.className = 'gantt-label'; l.innerHTML = `<i class="fas fa-user-circle"></i> ${esc(doc.nome)}`; r.appendChild(l);
        const b = document.createElement('div'); b.className = 'gantt-bars';
        const merged = merge(doc.alocacoes || []);
        const viewTotalDays = Math.ceil((ve - vs) / 864e5) + 1;
        let le = new Date(vs);
        merged.forEach(a => {
            const s = new Date(a.inicio + 'T00:00:00'), e = new Date(a.fim + 'T00:00:00');
            if (e < vs || s > ve) return;
            const rs = s < vs ? vs : s, re = e > ve ? ve : e;
            if (rs > le) { const gs = new Date(le), ge = new Date(rs); ge.setDate(ge.getDate() - 1); if (ge >= gs) bar(b, 'free', gs, ge, 'Livre', doc, vs, viewTotalDays); }
            bar(b, 'busy', rs, re, a.cursos.join(', '), doc, vs, viewTotalDays, a); le = new Date(re); le.setDate(le.getDate() + 1);
        });
        if (le <= ve) bar(b, 'free', le, ve, 'Livre', doc, vs, viewTotalDays);
        if (!merged.length) bar(b, 'free', vs, ve, 'Livre', doc, vs, viewTotalDays);
        r.appendChild(b); chart.appendChild(r);
    }

    function bar(cont, type, s, e, lbl, doc, vs, vtd, ctx = null) {
        const od = Math.max(0, Math.ceil((s - vs) / 864e5)), sd = Math.ceil((e - s) / 864e5) + 1, w = (sd / vtd * 100), l = (od / vtd * 100);
        const b = document.createElement('div'); b.className = `gantt-bar gantt-bar-${type}`; b.style.left = l + '%'; b.style.width = Math.max(w, 0.4) + '%';
        if (w > 8) b.textContent = lbl;
        const sISO = s.toISOString().split('T')[0], eISO = e.toISOString().split('T')[0];
        b.addEventListener('mouseenter', ev => {
            let timeInfo = (type === 'busy' && ctx) ? `<br><i class="far fa-clock"></i> ${ctx.inicio_hora.substring(0, 5)} - ${ctx.fim_hora.substring(0, 5)}` : '';
            tooltip.innerHTML = `<strong>${esc(doc.nome)}</strong><br><i class="far fa-calendar-alt"></i> ${fmt(sISO)} → ${fmt(eISO)}${timeInfo}<br><span class="badge-${type}">${type === 'busy' ? lbl : 'Livre'}</span>`;
            tooltip.style.display = 'block';
        });
        b.addEventListener('mousemove', ev => { tooltip.style.left = (ev.clientX + 12) + 'px'; tooltip.style.top = (ev.clientY - 10) + 'px'; });
        b.addEventListener('mouseleave', () => tooltip.style.display = 'none');
        if (type === 'free') b.addEventListener('click', () => window.openCalendarScheduleModal?.(sISO, eISO));
        cont.appendChild(b);
    }

    function merge(al) {
        if (!al.length) return [];
        const s = al.slice().sort((a, b) => a.inicio.localeCompare(b.inicio)), m = [{ inicio: s[0].inicio, fim: s[0].fim, cursos: [s[0].curso], inicio_hora: s[0].inicio_hora, fim_hora: s[0].fim_hora }];
        for (let i = 1; i < s.length; i++) {
            const l = m[m.length - 1];
            if (s[i].inicio <= l.fim) { if (s[i].fim > l.fim) l.fim = s[i].fim; if (!l.cursos.includes(s[i].curso)) l.cursos.push(s[i].curso); }
            else m.push({ inicio: s[i].inicio, fim: s[i].fim, cursos: [s[i].curso], inicio_hora: s[i].inicio_hora, fim_hora: s[i].fim_hora });
        }
        return m;
    }
    function fmt(d) { const p = d.split('-'); return `${p[2]}/${p[1]}/${p[0]}`; }
    function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    renderGantt();
});
