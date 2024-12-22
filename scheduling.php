<?php
// Inclui arquivos de conexão e configurações
require_once("./connection/connection.php");
require_once("./setting/links.php");
require_once("./setting/user.php");
require_once("./src/php/header.php");


// Verifica se o usuário está logado e tem acesso à página
if (!in_array($_SESSION["user_type"], [$user_administrator, $user_scheduling, $user_supervisor])) {
    header("Location: {$screen_login}");
    exit();
}


// Pega as informações do paciente para a função updatePatientStatus
function getPatientDetails($connection, $id)
{
    $sql = "SELECT name, registration_number FROM patient WHERE id = ?";
    if ($stmt = $connection->prepare($sql)) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            return $result->fetch_assoc();
        }
    }
    return null;
}


// Função principal para atualizar a situação do paciente
function updatePatientStatus($connection, $id, $situation, $currentDateTime, $comment = null)
{
    $dateField = ($situation === "cancelado") ? "cancellation_datetime" : "exchange_date";

    // Obter detalhes do paciente se estiver em revisão
    if ($situation == "revisao") {
        $patientDetails = getPatientDetails($connection, $id);
        if ($patientDetails) {
            $comment .= "\rNome paciente: " . $patientDetails['name'] . "\rCross: " . $patientDetails['registration_number'];
        } else {
            echo "<script>alert('Erro ao buscar dados do paciente para revisão.');</script>";
            return false;
        }
    }

    // Prepara a query de atualização
    $sql = "UPDATE patient SET situation = ?, $dateField = ?, comment = ? WHERE id = ?";
    if ($stmt = $connection->prepare($sql)) {
        $stmt->bind_param("sssi", $situation, $currentDateTime, $comment, $id);
        if ($stmt->execute()) {
            return true;
        } else {
            echo "<script>alert('Erro ao atualizar o paciente: " . addslashes($stmt->error) . "');</script>";
        }
    } else {
        echo "<script>alert('Erro na preparação da consulta: " . addslashes($connection->error) . "');</script>";
    }
    return false;
}


// Processa a atualização do status via GET
if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET["id"], $_GET["situation"])) {
    $id = intval($_GET["id"]);
    $situation = $_GET["situation"];

    if ($situation === 'cancelado') {
        if (updatePatientStatus($connection, $id, $situation, $currentDateTime)) {
            header("Location: {$screen_scheduling}");
            exit();
        }
    }
}


// Processa o formulário via POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $patient_id = $_POST['OLD_PATIENT_ID'] ?? null;
    $situation = $_POST['situation'] ?? null;
    $comment = $_POST['COMMENT'] ?? null;

    // Verifica se o paciente existe e se a situação está definida
    if ($patient_id && in_array($situation, ['revisao', 'agendado'])) {
        // Busca a situação atual do paciente
        $sql = "SELECT situation FROM patient WHERE id = ?";
        if ($stmt = $connection->prepare($sql)) {
            $stmt->bind_param("i", $patient_id);
            $stmt->execute();
            $stmt->bind_result($currentSituation);
            $stmt->fetch();
            $stmt->close();

            // Verifica se a situação do paciente está em andamento
            if (in_array($currentSituation, ['agendamento em andamento', 'cancelamento em andamento'])) {
                // Se a situação for válida, atualiza o status do paciente
                if (updatePatientStatus($connection, $patient_id, $situation, $currentDateTime, $comment)) {
                    header("Location: {$screen_scheduling}");
                    exit();
                }
            } else {
                // Se a situação não for válida, exibe um alerta e recarrega a página
                echo "<script>alert('Este paciente já foi agendado ou cancelado.'); window.location.href = '{$screen_scheduling}';</script>";
            }
        }
    } else {
        echo "<script>alert('Situação não reconhecida ou paciente não encontrado.');</script>";
    }
}


// Seleciona pacientes que precisam ser agendados ou cancelados
$sql = "SELECT p.id, p.name, p.registration_number, ms.specialty_name AS medical_specialty, rs.name AS professional_name, p.exam_date, p.cancel_reason, p.situation, p.comment FROM patient p JOIN medical_specialty ms ON p.medical_specialty = ms.id LEFT JOIN professional rs ON p.professional_id = rs.id WHERE p.situation IN ('agendamento em andamento', 'cancelamento em andamento')";


// Parâmetros de busca, se houver um termo de pesquisa
$searchTerm = null;
if (!empty($_POST['search'])) {
    $searchTerm = "%" . $_POST['search'] . "%";
    $sql .= " AND (p.name LIKE ? OR p.registration_number LIKE ?)";
}

// Adiciona ordenação
$sql .= " ORDER BY p.exam_date ASC";

// Prepara e executa a consulta
$stmt = $connection->prepare($sql);
if ($searchTerm) {
    $stmt->bind_param("ss", $searchTerm, $searchTerm);
}
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>



<!DOCTYPE html>
<html lang="pt-br">

<head>
    <title>Agendamento</title>
</head>

<body id="screen-scheduling">
    <div class="container">
        <h2>Agendamento</h2>
        <div class="row justify-content-center">
            <div class="col-md-6">
                <form method="post" action="<?php echo $screen_scheduling; ?>">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" autocomplete="off" placeholder="Digite o Nome ou Código de Identificação (CROSS) do paciente..." name="search">
                        <button class="btn btn-primary" type="submit">Buscar</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-responsive mt-3">
            <table class="table table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>Nome</th>
                        <th>Código de Identificação (CROSS)</th>
                        <th>Agenda</th>
                        <th>Responsável</th>
                        <th>Data da Agenda</th>
                        <th>Motivo do Cancelamento / Comentário </th>
                        <th>Situação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= $row["name"] ?></td>
                                <td><?= $row["registration_number"] ?></td>
                                <td><?= $row["medical_specialty"] ?></td>
                                <td><?= $row["professional_name"] ?></td>
                                <td><?= date("d/m/Y H:i:s", strtotime($row["exam_date"])) ?></td>
                                <td>
                                    <?php
                                    if (!empty($row["cancel_reason"]) && !empty($row["comment"])) {
                                        // Se ambos motivo e comentário existirem
                                        echo "<strong>Motivo:</strong> " . $row["cancel_reason"] . "<br><strong>Comentário:</strong> " . $row["comment"];
                                    } elseif (!empty($row["cancel_reason"])) {
                                        // Se apenas o motivo existir
                                        echo "<strong>Motivo:</strong> " . $row["cancel_reason"];
                                    } elseif (!empty($row["comment"])) {
                                        // Se apenas o comentário existir
                                        echo "<strong>Comentário:</strong> " . $row["comment"];
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($row["situation"] == 'cancelamento em andamento'): ?>
                                        <button onclick="if(confirm('Você tem certeza que deseja cancelar este paciente?')) { window.location.href='<?= $screen_scheduling ?>?id=<?= $row['id'] ?>&situation=cancelado'; }" class="btn btn-danger">Cancelar Horário</button>
                                    <?php elseif ($row["situation"] == 'agendamento em andamento'): ?>
                                        <button type="button" class="btn btn-primary substituir-paciente" data-bs-toggle="modal" data-bs-target="#schedule_new_patient" data-paciente-id="<?= $row['id'] ?>">Agendar paciente</button>
                                    <?php else: ?>
                                        erro
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">Nenhum paciente foi encontrado!</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="schedule_new_patient" tabindex="-1" aria-labelledby="schedule_patient" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="schedule_patient">Confirmação de Agendamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="<?php echo $screen_scheduling; ?>">
                        <input type="hidden" id="old_patient_id" name="OLD_PATIENT_ID">
                        <div class="mb-3">
                            <label for="comment" class="form-label">Comentário:</label>
                            <textarea name="COMMENT" id="comment" class="form-control" rows="3" maxlength="500"></textarea>
                            <small class="form-text text-muted">Máximo de 500 caracteres.</small>
                        </div>
                        <div class="modal-footer d-flex justify-content-between">
                            <button type="submit" name="situation" value="revisao" class="btn btn-secondary">Retornar CallCenter</button>
                            <button type="submit" name="situation" value="agendado" class="btn btn-primary">Confirmar Agendamento</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

</body>

</html>