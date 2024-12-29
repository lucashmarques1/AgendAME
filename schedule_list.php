<?php
// Inclui arquivos de conexão e configurações
require_once("./connection/connection.php");
require_once("./setting/links.php");
require_once("./setting/user.php");
require_once("./src/php/header.php");


// Verifica se o usuário está logado e pode ter acesso à página
if (!in_array($_SESSION["user_type"], [$user_administrator, $user_callcenter, $user_supervisor])) {
    header("Location: {$screen_login}");
    exit();
}


// Função que busca pacientes pendentes e em revisão. 
// Se houver uma busca especificada, a função realiza o SELECT juntamente com a busca.
function fetchPatients($connection, $searchTerm = null)
{
    // Inicializa a consulta SQL
    $sql = "SELECT p.id, ms.specialty_name AS medical_specialty, rp.name AS professional_name, p.exam_date, p.situation, p.comment FROM patient p JOIN medical_specialty ms ON p.medical_specialty = ms.id LEFT JOIN professional rp ON p.professional_id = rp.id WHERE p.situation IN ('pendente', 'revisao')";

    // Se houver um termo de busca, adiciona a condição ao SQL
    if ($searchTerm) {
        $searchTerm = "%{$searchTerm}%";
        $sql .= " AND ms.specialty_name LIKE ?";
    }

    $sql .= " ORDER BY p.exam_date ASC";

    $stmt = $connection->prepare($sql);

    if ($searchTerm) {
        $stmt->bind_param("s", $searchTerm);
    }

    $stmt->execute();
    return $stmt->get_result();
}


// Função para atualizar o status do paciente que vai ser cancelado.
function updatePatientStatus($connection, $id, $situation, $currentDateTime, $comment)
{
    $updateSql = "UPDATE patient SET situation = ?, cancellation_datetime = ?, comment = ? WHERE id = ?";
    $stmt = $connection->prepare($updateSql);
    $stmt->bind_param("sssi", $situation, $currentDateTime, $comment, $id);
    return $stmt->execute();
}


// Verifica se o paciente será cancelado.
if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET["id"], $_GET["situation"])) {
    $id = intval($_GET["id"]);
    $situation = $_GET["situation"];
    $comment = $_POST['COMMENT'] ?? null;

    if ($situation === 'cancelado') {
        updatePatientStatus($connection, $id, $situation, $currentDateTime, $comment);
        header("Location: {$screen_schedule_list}");
        exit();
    }
}


// Cadastra um novo paciente no horário disponível que foi selecionado.
function handleNewPatient($connection, $old_patient_id, $name, $registration_number, $currentDateTime, $comment)
{
    // Atualiza situação do paciente antigo para "cancelamento em andamento"
    $updateSql = "UPDATE patient SET situation = 'cancelamento em andamento' WHERE id = ?";
    $stmtUpdate = $connection->prepare($updateSql);
    $stmtUpdate->bind_param("i", $old_patient_id);
    $stmtUpdate->execute();

    // Recupera ID do responsável, ID da especialidade (agenda) e data do exame do paciente antigo para usar no novo paciente
    $selectSql = "SELECT ms.id AS medical_specialty, p.professional_id, p.exam_date FROM patient p JOIN medical_specialty ms ON p.medical_specialty = ms.id WHERE p.id = ?";
    $stmtSelect = $connection->prepare($selectSql);
    $stmtSelect->bind_param("i", $old_patient_id);
    $stmtSelect->execute();
    $result = $stmtSelect->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Verifica se a vaga já está ocupada por um paciente agendado (Se já não foi preenchida)
        $checkSql = "SELECT * FROM patient WHERE medical_specialty = ? AND professional_id = ? AND exam_date = ? AND situation IN ('agendado', 'agendamento em andamento')";
        $stmtCheck = $connection->prepare($checkSql);
        $stmtCheck->bind_param("iis", $row['medical_specialty'], $row['professional_id'], $row['exam_date']);
        $stmtCheck->execute();
        $checkResult = $stmtCheck->get_result();

        // Se a vaga estiver ocupada
        if ($checkResult->num_rows > 0) {
            return "A vaga já foi preenchida por outro paciente.";
        } else {
            // Insere os dados do novo paciente e coloca ele na situação "agendamento em andamento"
            $insertSql = "INSERT INTO patient (name, registration_number, medical_specialty, professional_id, exam_date, contact_datetime, registering_user_id, situation, comment) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $situation = "agendamento em andamento";
            $stmtInsert = $connection->prepare($insertSql);
            $stmtInsert->bind_param("ssiississ", $name, $registration_number, $row['medical_specialty'], $row['professional_id'], $row['exam_date'], $currentDateTime, $_SESSION["id_user"], $situation, $comment);
            $stmtInsert->execute();
            return "Paciente encaminhado para agendar sua consulta/exame.";
        }
    }
    return "Nenhum paciente pediu cancelamento!";
}


// Processa o novo paciente para assumir a vaga disponível.
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["NOVO_PACIENTE"])) {
    $name = trim($_POST['NAME']);
    $registration_number = trim($_POST['REGISTRATION_NUMBER']);
    $old_patient_id = trim($_POST['OLD_PATIENT_ID']);
    $comment = $_POST['COMMENT'] ?? null;
    $message = handleNewPatient($connection, $old_patient_id, $name, $registration_number, $currentDateTime, $comment);
    if ($message) {
        echo "<script>alert('$message');</script>";
    }
}


// Se a opção "sem demanda" for clicada, o sistema irá enviar para o agendamento sem um paciente para substituição da vaga disponível.
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["SEM_DEMANDA"])) {
    $old_patient_id = trim($_POST['OLD_PATIENT_ID']);

    $updateSql = "UPDATE patient SET situation = 'cancelamento em andamento', comment = 'Solicitado apenas o cancelamento dessa agenda, pois não há paciente disponível para preencher essa vaga.' WHERE id = ?";
    $stmtUpdate = $connection->prepare($updateSql);
    $stmtUpdate->bind_param("i", $old_patient_id);
    $stmtUpdate->execute();

    header("Location: {$screen_schedule_list}");
    exit();
}

// Executa a busca de pacientes (caso tenha ou não uma pesquisa)
$result = fetchPatients($connection, $_POST['search'] ?? null);
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <title>Lista de Agendas Disponíveis</title>
</head>

<body id="screen-schedule-list">
    <div class="container">
        <h2>Lista de Agendas Disponíveis</h2>
        <div class="row justify-content-center">
            <div class="col-md-6">
                <form method="post" action="<?php echo $screen_schedule_list; ?>">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" placeholder="Digite a especialidade (agenda)..." name="search">
                        <button class="btn btn-primary" type="submit">Buscar</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-striped text-center">
                <thead>
                    <tr>
                        <th>Agenda</th>
                        <th>Responsável</th>
                        <th>Data da Agenda</th>
                        <th>Comentário</th>
                        <th>Situação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result->num_rows > 0) {
                        // Exibir dados de cada paciente
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($row["medical_specialty"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["professional_name"]) . "</td>";
                            echo "<td>" . date('d/m/Y H:i:s', strtotime($row['exam_date'])) . "</td>";
                            echo "<td>" . nl2br(htmlspecialchars($row["comment"])) . "</td>";
                            if ($row["situation"] == "pendente") { /* Pendente é o paciente que será cancelado  */
                                echo "<td><button type='button' class='btn btn-primary substituir-paciente' data-bs-toggle='modal' data-bs-target='#schedule_new_patient' data-paciente-id='" . htmlspecialchars($row['id']) . "'>Preencher Vaga</button></td>";
                            } elseif ($row["situation"] == "revisao") { /* Revisao é o paciente que será cancelado e após receberá uma ligação do CallCenter avisando sobre  */
                                echo '<td><button onclick="if(confirm(\'Você já entrou em contato com o paciente para confirmar o cancelamento da consulta/exame?\')) { window.location.href=\'' . htmlspecialchars($screen_schedule_list) . '?id=' . htmlspecialchars($row['id']) . '&situation=cancelado\'; }" class="btn btn-danger">Notificar paciente</button></td>';
                            } else {
                                echo "<td>erro</td>";
                            }
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5'>Nenhum paciente foi encontrado.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="schedule_new_patient" tabindex="-1" aria-labelledby="schedule_patient" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="schedule_patient">Agendar Novo Paciente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="<?php echo $screen_schedule_list; ?>">
                        <input type="hidden" id="old_patient_id" name="OLD_PATIENT_ID"> <!-- ID Paciente que será cancelado -->
                        <div class="mb-3">
                            <label for="name" class="form-label">Nome do Paciente:</label>
                            <input type="text" id="name" name="NAME" class="form-control" maxlength="100" autocomplete="off" required>
                        </div>
                        <div class="mb-3">
                            <label for="registration_number" class="form-label">Código de Identificação (CROSS):</label>
                            <input type="number" id="registration_number" name="REGISTRATION_NUMBER" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label for="comment" class="form-label">Comentário:</label>
                            <textarea name="COMMENT" id="comment" class="form-control" rows="3" maxlength="500"></textarea>
                            <small class="form-text text-muted">Máximo de 500 caracteres.</small>
                        </div>
                        <div class="modal-footer d-flex justify-content-between">
                            <button type="submit" class="btn btn-danger" onclick="setDefaultValues()" name="SEM_DEMANDA">Sem demanda</button>
                            <button type="submit" class="btn btn-primary" name="NOVO_PACIENTE">Confirmar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function setDefaultValues() {
            // Adiciona um ponto final aos campos NAME e REGISTRATION_NUMBER quando é clicado no botão "SEM DEMANDA", esses valores não serão armazenados, isso é utilizado apenas por ser campos obrigatorios do formulário.
            document.getElementById('name').value = "N/A";
            document.getElementById('registration_number').value = 0;
        }
    </script>

</body>

</html>