
/* Atualizar Horário na Tela de patient.PHP */
$(document).ready(function () {
    // Função para atualizar a data e hora a cada segundo
    function updateDateTime() {
        // Obter a data e hora atual do servidor
        var currentDateTime = new Date().toLocaleString('pt-BR');

        // Atualizar o texto do h2 com a data e hora atual
        $('#currentDateTime').text(currentDateTime);
    }

    // Chamar a função para atualizar a data e hora a cada segundo
    setInterval(updateDateTime, 1000);
});



/* Colocar id no input hidden que existe no modal - patient.php*/
document.addEventListener('DOMContentLoaded', function () {
    var scheduleModal = document.getElementById('schedule_new_patient');
    scheduleModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        var pacienteId = button.getAttribute('data-paciente-id');
        var pacienteIdInput = scheduleModal.querySelector('#old_patient_id');
        pacienteIdInput.value = pacienteId;
    });
});



/* Não deixa colocar a data manualmente na DATA DO EXAME CANCELADO - callcenter.php */
document.addEventListener('DOMContentLoaded', function () {
    // Obtém o elemento do campo de entrada de data e hora
    const dateTimeInput = document.getElementById('date_time_cancellation');

    // Adiciona um listener de evento para o evento "keydown" no campo
    dateTimeInput.addEventListener('keydown', function (event) {
        // Cancela o evento para impedir que a entrada manual seja inserida
        event.preventDefault();
    });
});
