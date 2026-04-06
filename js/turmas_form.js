document.addEventListener('DOMContentLoaded', () => {
    const startInput = document.querySelector('input[name="data_inicio"]');
    const endInput = document.querySelector('input[name="data_fim"]');
    const periodoSelect = document.querySelector('select[name="periodo"]');

    function calcularDataFim() {
        const periodo = document.getElementById('periodo-select').value;
        const totalHoras = parseFloat(document.getElementById('carga_horaria_total').value) || 0;
        const dataInicio = document.getElementById('data_inicio').value;
        const checkboxes = document.querySelectorAll('input[name="dias_semana[]"]:checked');
        const saturdayCheckbox = document.querySelector('.saturday-checkbox input');

        if (periodo === 'Noite' && saturdayCheckbox) {
            if (saturdayCheckbox.checked) {
                alert('Sábado não é permitido para o período Noite.');
                saturdayCheckbox.checked = false;
            }
        }
    }
});
