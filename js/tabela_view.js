document.addEventListener('DOMContentLoaded', () => {
    const dataEl = document.getElementById('tabela-prof-data');
    if (!dataEl) return;

    const data = JSON.parse(dataEl.textContent);
    let docentes = data.docentes;
    let filteredDocentes = [...docentes];
    let currentPage = 1;
    const perPage = data.per_page;
    const totalSlots = data.total_dias_uteis || 24;

    const tbody = document.getElementById('tabela-prof-body');
    const searchInput = document.getElementById('search-professor');
    const filterArea = document.getElementById('filter-area');
    const filterSelect = document.getElementById('filter-disponibilidade');
    const prevBtn = document.getElementById('page-prev');
    const nextBtn = document.getElementById('page-next');
    const pageInfo = document.getElementById('page-info');

    function renderTable() {
        const totalPages = Math.max(1, Math.ceil(filteredDocentes.length / perPage));
        if (currentPage > totalPages) currentPage = totalPages;
        const start = (currentPage - 1) * perPage;
        const pageDocentes = filteredDocentes.slice(start, start + perPage);

        if (pageDocentes.length === 0) {
            tbody.innerHTML = `<tr><td colspan="4" class="td-empty"><i class="fas fa-search"></i> Nenhum professor encontrado.</td></tr>`;
        } else {
            tbody.innerHTML = pageDocentes.map(doc => {
                const pctOcupado = (doc.dias_ocupados / totalSlots) * 100;
                const isHigh = pctOcupado > 50;
                return `<tr>
                    <td>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-user-circle" style="color: var(--primary-red); font-size: 1.3rem;"></i>
                            <div>
                                <strong style="display: block;">${escapeHtml(doc.nome)}</strong>
                                <small style="color: var(--text-muted); font-size: 0.75rem;">${escapeHtml(doc.area)}</small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="mini-avail-bar" style="height: 20px; border-radius: 6px; margin-top: 5px;">
                            <div class="mini-avail-free" style="width: ${100 - pctOcupado}%; cursor: pointer;" title="Próximo dia livre: ${doc.proximo_dia_livre === 'N/A' ? 'Indisponível' : doc.proximo_dia_livre.split('-').reverse().join('/')} (Clique para agendar)" onclick="location.href='agenda_professores.php?docente_id=${doc.id}&data_inicio=${doc.proximo_dia_livre}'"></div>
                            <div class="mini-avail-busy" style="width: ${pctOcupado}%;" title="Ocupado"></div>
                        </div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px; text-align: right;">
                            ${Math.round(pctOcupado)}% ocupado (${doc.dias_ocupados}/${totalSlots} dias úteis do mês)
                        </div>
                    </td>
                    <td style="text-align:center;">${doc.total_aulas}</td>
                    <td style="text-align:center;">${doc.dias_ocupados}</td>
                </tr>`;
            }).join('');
        }
        pageInfo.textContent = `Página ${currentPage} de ${totalPages}`;
        prevBtn.disabled = currentPage <= 1;
        nextBtn.disabled = currentPage >= totalPages;
    }

    function applyFilters() {
        const search = searchInput.value.toLowerCase().trim();
        const area = filterArea.value;
        const order = filterSelect ? filterSelect.value : 'mais_livre';

        filteredDocentes = docentes.filter(d => {
            const matchesSearch = d.nome.toLowerCase().includes(search);
            const matchesArea = area === '' || d.area === area;
            return matchesSearch && matchesArea;
        });

        filteredDocentes.sort((a, b) => {
            if (order === 'mais_livre') return a.dias_ocupados - b.dias_ocupados;
            return b.dias_ocupados - a.dias_ocupados;
        });
        currentPage = 1;
        renderTable();
    }

    searchInput.addEventListener('input', applyFilters);
    filterSelect.addEventListener('change', applyFilters);
    filterArea.addEventListener('change', applyFilters);
    prevBtn.addEventListener('click', () => { currentPage--; renderTable(); });
    nextBtn.addEventListener('click', () => { currentPage++; renderTable(); });

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }
    applyFilters();
});
