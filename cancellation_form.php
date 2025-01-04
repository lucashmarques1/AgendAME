<?php
require_once("./connection/connection.php");
require_once("./setting/links.php");
require_once("./setting/user.php");
require_once("./src/php/header.php");


// Verificar se o usuário está logado e se o usuário tem permissão para acessar a página
if (!in_array($_SESSION["user_type"], [$user_administrator, $user_callcenter, $user_supervisor])) {
    header("Location: {$screen_login}");
    exit();
}


// Função para inserir paciente no banco de dados
function insertPatient($connection, $name, $registration_number, $medical_specialty_id, $exam_date, $cancel_reason, $user_id, $professional_id, $situation)
{
    $sql = "INSERT INTO patients (name, registration_number, medical_specialty_id, exam_date, contact_datetime, cancel_reason, registering_user_id, professional_id, situation)
            VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?)";
    $stmt = $connection->prepare($sql);
    if ($stmt === false) {
        return $connection->error;
    }
    $stmt->bind_param("ssisssis", $name, $registration_number, $medical_specialty_id, $exam_date, $cancel_reason, $user_id, $professional_id, $situation);
    if ($stmt->execute()) {
        return true;
    }
    return $stmt->error;
}


// Função para verificar se já existe um paciente com a mesma especialidade médica, profissional e data de exame
function checkPatientExistence($connection, $medical_specialty_id, $professional_id, $exam_date)
{
    $sql = "SELECT id FROM patients 
            WHERE medical_specialty_id = ? 
            AND professional_id = ? 
            AND exam_date = ? 
            AND situation != 'cancelado'"; // A situação 'cancelado' pode ser ignorada para essa verificação

    $stmt = $connection->prepare($sql);
    $stmt->bind_param("iis", $medical_specialty_id, $professional_id, $exam_date);
    $stmt->execute();
    $stmt->store_result();

    // Retorna true se encontrar algum paciente, ou false se não encontrar
    return $stmt->num_rows > 0;
}


// Consulta especialidades médicas
function getMedicalSpecialties($connection)
{
    $sql = "SELECT id, specialty_name FROM medical_specialties WHERE active = 1 ORDER BY specialty_name ASC";
    return $connection->query($sql);
}


// Consulta profissionais baseados na especialidade que cada um atua
function getProfessionalsBySpecialty($connection, $specialty_id)
{
    $sql = "SELECT p.id, p.name FROM professionals p
            JOIN professional_specialties ps ON p.id = ps.professional_id
            WHERE ps.specialty_id = ? AND p.active = 1 AND ps.active = 1"; // Profissional e relação do profissional com a especialidade PRECISA estar ativa.

    $stmt = $connection->prepare($sql);
    $stmt->bind_param("i", $specialty_id);
    $stmt->execute();
    return $stmt->get_result();
}

// Variáveis de controle que irão ser usadas para buscar os resultados pelas funções OU parâmetros
$specialtiesResult = getMedicalSpecialties($connection);
$professionalsResult = null;
$selectedSpecialtyId = null;
$selectedProfessionalId = null;


// Se o formulário foi enviado (Foi cadastrado um novo cancelamento)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["REGISTRATION_CANCELLATION"])) {
    // Pega os valores do formulário
    $name = trim($_POST['NAME']);
    $registration_number = trim($_POST['REGISTRATION_NUMBER']);
    $medical_specialty_id = $_POST['MEDICAL_SPECIALTY'];
    $cancel_reason = trim($_POST['CANCEL_REASON']);
    $exam_date = $_POST['EXAM_DATE']; // datetime-local já retorna no formato adequado
    $user_id = $_SESSION['id_user']; // recebe usuário logado do momento
    $professional_id = $_POST['PROFESSIONAL']; // Recebe o ID do profissional selecionado
    $situation = "pendente";

    // Verifica se já existe um paciente com o mesmo profissional, especialidade e data de exame
    $exam_date = date('Y-m-d H:i:s', strtotime($exam_date)); // Garantir o formato de data correto

    if (checkPatientExistence($connection, $medical_specialty_id, $professional_id, $exam_date)) {
        echo "<script>alert('Já há um paciente com consulta ou exame marcado para o mesmo horário com este profissional.');</script>";
    } else {
        // Cadastra o paciente e verifica se houve erro
        $insertResult = insertPatient($connection, $name, $registration_number, $medical_specialty_id, $exam_date, $cancel_reason, $user_id, $professional_id, $situation);

        if ($insertResult === true) {
            echo "<script>alert('Paciente cadastrado com sucesso.')</script>";
            echo "<script>window.location = '{$screen_cancellation_form}';</script>";
        } else {
            echo "<script>alert('Erro ao cadastrar o paciente: {$insertResult}')</script>";
        }
    }
} elseif (isset($_POST['MEDICAL_SPECIALTY']) && !empty($_POST['MEDICAL_SPECIALTY'])) {
    // Atualiza o select do PROFISSIONAL, quando é selecionado alguma especialidade
    $selectedSpecialtyId = $_POST['MEDICAL_SPECIALTY'];
    $professionalsResult = getProfessionalsBySpecialty($connection, $selectedSpecialtyId);
    $selectedProfessionalId = isset($_POST['PROFESSIONAL']) ? $_POST['PROFESSIONAL'] : null;
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <title>Ficha de Cancelamento</title>
</head>

<body id="screen-cancellation-form">
    <div class="container">
        <h2>Ficha de Cancelamento</h2>

        <form method="post" action="<?php echo $screen_cancellation_form; ?>">
            <div>
                <label for="name">Nome do Paciente:</label>
                <input type="text" id="name" name="NAME" maxlength="100" autocomplete="off" value="<?php echo isset($_POST['NAME']) ? htmlspecialchars($_POST['NAME']) : ''; ?>" required>
            </div>
            <div>
                <label for="registration_number">Código de Identificação (CROSS):</label>
                <input type="number" id="registration_number" name="REGISTRATION_NUMBER" value="<?php echo isset($_POST['REGISTRATION_NUMBER']) ? htmlspecialchars($_POST['REGISTRATION_NUMBER']) : ''; ?>" required>
            </div>
            <div>
                <label for="medical_specialty">Especialidade Médica:</label>
                <select id="medical_specialty" name="MEDICAL_SPECIALTY" onchange="this.form.submit()" required>
                    <option value="" disabled <?php echo !$selectedSpecialtyId ? 'selected' : ''; ?>>Selecione uma especialidade médica</option>
                    <?php
                    if ($specialtiesResult->num_rows > 0) {
                        while ($row = $specialtiesResult->fetch_assoc()) {
                            $selected = ($selectedSpecialtyId == $row["id"]) ? 'selected' : '';
                            echo '<option value="' . $row["id"] . '" ' . $selected . '>' . htmlspecialchars($row["specialty_name"]) . '</option>';
                        }
                    } else {
                        echo '<option value="" disabled>Nenhuma especialidade disponível</option>';
                    }
                    ?>
                </select>
            </div>
            <div>
                <label for="professional">Profissional Responsável:</label>
                <select id="professional" name="PROFESSIONAL" required>
                    <option value="" disabled selected>Selecione um profissional</option>
                    <?php
                    if ($professionalsResult && $professionalsResult->num_rows > 0) {
                        while ($row = $professionalsResult->fetch_assoc()) {
                            $selected = ($selectedProfessionalId == $row["id"]) ? 'selected' : '';
                            echo '<option value="' . $row["id"] . '" ' . $selected . '>' . htmlspecialchars($row["name"]) . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>
            <div>
                <label for="cancel_reason">Motivo do Cancelamento:</label>
                <textarea id="cancel_reason" name="CANCEL_REASON" maxlength="500" required><?php echo isset($_POST['CANCEL_REASON']) ? htmlspecialchars($_POST['CANCEL_REASON']) : ''; ?></textarea>
            </div>
            <div class="mb-4">
                <label for="exam_date">Data da Consulta / Exame:</label>
                <input type="datetime-local" id="exam_date" name="EXAM_DATE" value="<?php echo isset($_POST['EXAM_DATE']) ? $_POST['EXAM_DATE'] : ''; ?>" required>
            </div>
            <div>
                <button type="submit" name="REGISTRATION_CANCELLATION">Cadastrar</button>
            </div>
        </form>
    </div>
</body>

</html>