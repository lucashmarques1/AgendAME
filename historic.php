<?php
require_once("./connection/connection.php");
require_once("./setting/links.php");
require_once("./setting/user.php");
require_once("./src/php/header.php");


// Verifica se o usuário está logado
if (empty($_SESSION["user_type"])) {
    header("Location: {$screen_login}");
    exit();
}


// Consulta SQL para busca de pacientes individuais por nome ou número de registro
$patientInfo = null;
if (isset($_POST['search_individual']) && !empty(trim($_POST['search_individual']))) {
    $searchIndividual = "%" . trim($_POST['search_individual']) . "%";
    $sqlIndividual = "SELECT p.name AS name, p.registration_number, p.situation, u.username AS registered_user, ms.specialty_name, rp.name AS professional_name, p.exam_date, p.contact_datetime, p.cancel_reason, p.comment,
    CASE 
        WHEN p.situation = 'cancelado' THEN '#d12a3b' 
        WHEN p.situation = 'agendado' THEN '#198754' 
        WHEN p.situation IN ('pendente', 'revisão') THEN '#b3b3b3' 
        WHEN p.situation = 'agendamento em andamento' THEN '#69a156' 
        WHEN p.situation = 'cancelamento em andamento' THEN '#a15454' 
        ELSE 'gray' 
    END AS situation_color 
FROM patients p JOIN medical_specialties ms ON p.medical_specialty_id = ms.id JOIN users u ON p.registering_user_id = u.id
LEFT JOIN professionals rp ON p.professional_id = rp.id WHERE p.name LIKE ? OR p.registration_number LIKE ? ORDER BY p.contact_datetime ASC LIMIT 10;
"; // Limitar a busca a até 10 resultados
    $stmtInd = $connection->prepare($sqlIndividual);
    $stmtInd->bind_param("ss", $searchIndividual, $searchIndividual);

    if ($stmtInd->execute()) {
        $patientInfo = $stmtInd->get_result();
    }
    $stmtInd->close();
}


// SQL base para a tabela de pacientes agendados e cancelados com mesmas especialidades e datas de exame
$sql = "SELECT p1.name AS canceled_patient_name, p1.registration_number AS canceled_patient_cross, p1.situation AS canceled_patient_status, u1.username AS canceled_patient_user, p2.name AS scheduled_patient_name, p2.registration_number AS scheduled_patient_cross, p2.situation AS scheduled_patient_status, u2.username AS scheduled_patient_user, ms.specialty_name AS medical_specialty_id, rs.name AS professional_name, p1.exam_date AS exam_date, 
-- Cores (Situação Paciente 1)
CASE 
    WHEN p1.situation = 'cancelado' THEN '#d12a3b'
    WHEN p1.situation = 'agendado' THEN '#198754'
    WHEN p1.situation IN ('pendente', 'revisão') THEN '#b3b3b3'
    WHEN p1.situation = 'agendamento em andamento' THEN '#69a156'
    WHEN p1.situation = 'cancelamento em andamento' THEN '#a15454'
    ELSE 'gray' 
END AS patient_status_color,
-- Cores (Situação Paciente 2)
CASE 
    WHEN p2.situation = 'cancelado' THEN '#d12a3b'
    WHEN p2.situation = 'agendado' THEN '#198754'
    WHEN p2.situation IN ('pendente', 'revisão') THEN '#b3b3b3'
    WHEN p2.situation = 'agendamento em andamento' THEN '#69a156'
    WHEN p2.situation = 'cancelamento em andamento' THEN '#a15454'
    ELSE 'gray' 
END AS scheduled_patient_status_color

FROM patients p1 LEFT JOIN patients p2 ON p1.exam_date = p2.exam_date AND p1.medical_specialty_id = p2.medical_specialty_id AND p1.professional_id = p2.professional_id AND p2.situation IN ('agendado', 'agendamento em andamento') JOIN medical_specialties ms ON p1.medical_specialty_id = ms.id JOIN professionals rs ON p1.professional_id = rs.id JOIN users u1 ON p1.registering_user_id = u1.id LEFT JOIN users u2 ON p2.registering_user_id = u2.id WHERE p1.exam_date >= CURRENT_DATE() - INTERVAL 30 DAY AND p1.situation IN ('cancelado', 'cancelamento em andamento', 'pendente')";

// Filtro de busca da tabela principal
if (isset($_POST['search_main']) && !empty(trim($_POST['search_main']))) {
    $searchTerm = "%" . trim($_POST['search_main']) . "%";
    $sql .= " AND (p1.name LIKE ? OR p1.registration_number LIKE ? OR p2.name LIKE ? OR p2.registration_number LIKE ?)";
}

$sql .= " ORDER BY p1.contact_datetime DESC;";
$stmt = $connection->prepare($sql);

if (isset($searchTerm)) {
    $stmt->bind_param("ssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm);
}

// Executa a consulta e verifica erros
if ($stmt->execute()) {
    $result = $stmt->get_result();
} else {
    echo "<div class='alert alert-danger'>Erro na execução da consulta: " . $stmt->error . "</div>";
    $result = null;
}
$stmt->close();

?>



<!DOCTYPE html>
<html lang="pt-br">

<head>
    <title>Histórico</title>
</head>

<body id="screen-historic">
    <div class="container">
        <!-- Formulário de busca para informações individuais do paciente -->
        <h2>Buscar Paciente Individual</h2>
        <div class="row justify-content-center mb-3">
            <div class="col-md-6">
                <form method="post">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Buscar paciente individual por nome ou cross..." name="search_individual">
                        <button class="btn btn-primary" type="submit">Buscar</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabela de informações individuais -->
        <?php if ($patientInfo && $patientInfo->num_rows > 0): ?>
            <div class="table-responsive mt-3">
                <table class="table table-striped"> <!-- Removida a classe table-bordered -->
                    <thead class="table-dark">
                        <tr>
                            <th>Nome</th>
                            <th>Cross</th>
                            <th>Usuário</th>
                            <th>Agenda</th>
                            <th>Responsável</th>
                            <th>Data de Exame/Consulta</th>
                            <th>Data de Contato</th>
                            <th>Motivo do Cancelamento e Comentário</th>
                            <th>Situação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $patientInfo->fetch_assoc()): ?>
                            <tr class="text-center">
                                <td><?= htmlspecialchars($row["name"]) ?></td>
                                <td><?= htmlspecialchars($row["registration_number"]) ?></td>
                                <td><?= htmlspecialchars($row["registered_user"]) ?></td>
                                <td><?= htmlspecialchars($row["specialty_name"]) ?></td>
                                <td><?= htmlspecialchars($row["professional_name"]) ?></td>
                                <td><?= date("d/m/Y H:i:s", strtotime($row["exam_date"])) ?></td>
                                <td><?= date("d/m/Y H:i:s", strtotime($row["contact_datetime"])) ?></td>
                                <td>
                                    <?php
                                    if (!empty($row["cancel_reason"]) && !empty($row["comment"])) {
                                        echo "<strong>Motivo:</strong> " . htmlspecialchars($row["cancel_reason"]) . "<br><strong>Comentário:</strong> " . htmlspecialchars($row["comment"]);
                                    } elseif (!empty($row["cancel_reason"])) {
                                        echo "<strong>Motivo:</strong> " . htmlspecialchars($row["cancel_reason"]);
                                    } elseif (!empty($row["comment"])) {
                                        echo "<strong>Comentário:</strong> " . htmlspecialchars($row["comment"]);
                                    }
                                    ?>
                                </td>
                                <!-- Célula de situação com cor de fundo e texto em branco -->
                                <td style="background-color: <?= htmlspecialchars($row['situation_color']); ?>; color: white;">
                                    <?= htmlspecialchars($row["situation"]) ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>

                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <hr>

        <h2>Histórico</h2>

        <!-- Formulário de busca para tabela principal -->
        <div class="row justify-content-center mb-3">
            <div class="col-md-6">
                <form method="post">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Buscar por nome ou cross do paciente..." name="search_main">
                        <button class="btn btn-primary" type="submit">Buscar</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabela principal -->
        <div class="table-responsive mt-3">
            <table class="table table-striped"> <!-- Removida a classe table-bordered -->
                <thead class="table-dark">
                    <tr>
                        <th>Nome do Paciente Cancelado</th>
                        <th>Cross do Paciente Cancelado</th>
                        <th>Situação do Paciente Cancelado</th>
                        <th>Usuário do Paciente Cancelado</th>
                        <th>Nome do Paciente Agendado</th>
                        <th>Cross do Paciente Agendado</th>
                        <th>Situação do Paciente Agendado</th>
                        <th>Usuário do Paciente Agendado</th>
                        <th>Especialidade Médica</th>
                        <th>Responsável</th>
                        <th>Data de Exame/Consulta</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result && $result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            echo "<tr class='text-center'>";
                            echo "<td>" . htmlspecialchars($row["canceled_patient_name"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["canceled_patient_cross"]) . "</td>";

                            // Célula de status do paciente cancelado com texto em branco
                            echo "<td style='background-color: " . htmlspecialchars($row["patient_status_color"]) . "; color: white;'>" . htmlspecialchars($row["canceled_patient_status"]) . "</td>";

                            echo "<td>" . htmlspecialchars($row["canceled_patient_user"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["scheduled_patient_name"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["scheduled_patient_cross"]) . "</td>";

                            // Verificando se p2 está vazio
                            if (empty($row["scheduled_patient_name"])) {
                                // Célula vazia com fundo branco e texto em branco
                                echo "<td style='background-color: white; color: white;'>" . htmlspecialchars($row["scheduled_patient_status"]) . "</td>";
                            } else {
                                // Célula de status do paciente agendado com cor correspondente e texto em branco
                                echo "<td style='background-color: " . htmlspecialchars($row["scheduled_patient_status_color"]) . "; color: white;'>" . htmlspecialchars($row["scheduled_patient_status"]) . "</td>";
                            }

                            echo "<td>" . htmlspecialchars($row["scheduled_patient_user"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["medical_specialty_id"]) . "</td>";
                            echo "<td>" . htmlspecialchars($row["professional_name"]) . "</td>";
                            echo "<td>" . date("d/m/Y H:i:s", strtotime($row["exam_date"])) . "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='11' class='text-center'>Nenhum paciente encontrado com as mesmas especialidade e data de exame.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>


    </div>
</body>

</html>