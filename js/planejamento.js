/**
 * planejamento.js
 * Lógica da página de planejamento: seleção de turma e vigência
 */
document.addEventListener('DOMContentLoaded', () => {
    const turmaSelect = document.getElementById('turma_select');
    if (!turmaSelect) return;

    turmaSelect.addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        const inicio = document.getElementById('view_inicio');
        const fim = document.getElementById('view_fim');

        if (opt.value) {
            inicio.value = opt.dataset.inicio.split('-').reverse().join('/');
            fim.value = opt.dataset.fim.split('-').reverse().join('/');
        } else {
            inicio.value = '';
            fim.value = '';
        }
    });
});
