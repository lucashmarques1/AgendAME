<?php
require_once("./connection/connection.php");
require_once("./setting/links.php");
require_once("./setting/user.php");
require_once("./src/php/header.php");

// Verifica se o usuário está logado e pode ter acesso à página
if (!in_array($_SESSION["user_type"], [$user_administrator])) {
    header("Location: {$screen_login}");
    exit();
}

// Funções para especialidades
function getSpecialtyById($connection, $id)
{
    $stmt = $connection->prepare("SELECT id, specialty_name, active FROM medical_specialties WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function saveSpecialty($connection, $id, $specialty_name, $active)
{
    if ($id) {
        $stmt = $connection->prepare("UPDATE medical_specialties SET specialty_name = ?, active = ? WHERE id = ?");
        $stmt->bind_param("sii", $specialty_name, $active, $id);
    } else {
        $stmt = $connection->prepare("INSERT INTO medical_specialties (specialty_name, active) VALUES (?, ?)");
        $stmt->bind_param("si", $specialty_name, $active);
    }
    return $stmt->execute();
}

// Funções para profissionais
function getProfessionalById($connection, $id)
{
    $stmt = $connection->prepare("SELECT id, name, license_number, license_type, active FROM professionals WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function saveProfessional($connection, $id, $name, $license_number, $license_type, $active)
{
    if ($id) {
        $stmt = $connection->prepare("UPDATE professionals SET name = ?, license_number = ?, license_type = ?, active = ? WHERE id = ?");
        $stmt->bind_param("sssii", $name, $license_number, $license_type, $active, $id);
    } else {
        $stmt = $connection->prepare("INSERT INTO professionals (name, license_number, license_type, active) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $name, $license_number, $license_type, $active);
    }
    return $stmt->execute();
}

// Funções para carregar dados Profissionais/Especialidades
function getProfessionals($connection)
{
    $stmt = $connection->prepare("SELECT id, name, active FROM professionals ORDER BY name ASC");
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getSpecialties($connection)
{
    $stmt = $connection->prepare("SELECT id, specialty_name, active FROM medical_specialties ORDER BY specialty_name ASC");
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getProfessionalSpecialties($connection)
{
    $stmt = $connection->prepare(
        "SELECT ps.id, ps.professional_id, ps.specialty_id, p.name AS professional_name, s.specialty_name, ps.active
        FROM professional_specialties ps
        JOIN professionals p ON ps.professional_id = p.id
        JOIN medical_specialties s ON ps.specialty_id = s.id
        ORDER BY name ASC"
    );
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Processamento do formulário de especialidade
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['specialty_name'])) {
    $id = $_POST['specialty_id'] ?? null;
    $specialty_name = $_POST['specialty_name'] ?? '';
    $active = $_POST['active'] ?? 1;

    if (saveSpecialty($connection, $id, $specialty_name, $active)) {
        echo "<script>alert('Especialidade salva com sucesso!'); window.location.href = 'administrator.php';</script>";
    } else {
        echo "<script>alert('Erro ao salvar especialidade.');</script>";
    }
}

// Processamento do formulário de profissional
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['professional_name'])) {
    $id = $_POST['professional_id'] ?? null;
    $name = $_POST['professional_name'] ?? '';
    $license_number = $_POST['license_number'] ?? '';
    $license_type = $_POST['license_type'] ?? '';
    $active = $_POST['active'] ?? 1;

    if (saveProfessional($connection, $id, $name, $license_number, $license_type, $active)) {
        echo "<script>alert('Profissional salvo com sucesso!'); window.location.href = 'administrator.php';</script>";
    } else {
        echo "<script>alert('Erro ao salvar profissional.');</script>";
    }
}

// Processamento do formulário de vínculo entre profissional e especialidade
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['professional_specialty_id'])) {
    $id = $_POST['professional_specialty_id'];
    $professional_id = isset($_POST['professional_id']) ? intval($_POST['professional_id']) : null;
    $specialty_id = isset($_POST['specialty_id']) ? intval($_POST['specialty_id']) : null;
    $active = isset($_POST['active']) ? intval($_POST['active']) : 1;

    if ($professional_id === 0 || $specialty_id === 0) {
        print("<script>alert('Profissional e especialidade são obrigatórios!');</script>");
    } else {
        if ($id === 'novo') {
            // Verifica se o vínculo já existe
            $checkStmt = $connection->prepare(
                "SELECT COUNT(*) FROM professional_specialties WHERE professional_id = ? AND specialty_id = ?"
            );
            $checkStmt->bind_param("ii", $professional_id, $specialty_id);
            $checkStmt->execute();
            $checkStmt->bind_result($count);
            $checkStmt->fetch();
            $checkStmt->close();

            if ($count > 0) {
                print("<script>alert('Este vínculo já existe!');</script>");
            } else {
                $stmt = $connection->prepare("INSERT INTO professional_specialties (professional_id, specialty_id, active) VALUES (?, ?, ?)");
                $stmt->bind_param("iii", $professional_id, $specialty_id, $active);
                $stmt->execute();
                print("<script>alert('Vínculo criado com sucesso!');</script>");
            }
        } else {
            // Atualiza diretamente o status ativo/inativo
            $stmt = $connection->prepare("UPDATE professional_specialties SET active = ? WHERE id = ?");
            $stmt->bind_param("ii", $active, $id);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                $statusMessage = $active ? "Ativo" : "Inativo";
                print("<script>alert('Vínculo atualizado para $statusMessage!');</script>");
            } else {
                print("<script>alert('Nenhuma alteração realizada.');</script>");
            }
        }
    }
    // Atualiza a página para refletir mudanças no select
    print("<script>window.location.href = window.location.href;</script>");
    exit();
}

// Carrega os dados para edição
$specialty = ["id" => "", "specialty_name" => "", "active" => 1];
if (isset($_GET['specialty_id']) && !empty($_GET['specialty_id'])) {
    $specialty = getSpecialtyById($connection, intval($_GET['specialty_id'])) ?? $specialty;
}

$professional = ["id" => "", "name" => "", "license_number" => "", "license_type" => "CRM", "active" => 1];
if (isset($_GET['professional_id']) && !empty($_GET['professional_id'])) {
    $professional = getProfessionalById($connection, intval($_GET['professional_id'])) ?? $professional;
}

// Busca listas para dropdown
$existingSpecialties = $connection->query("SELECT id, specialty_name FROM medical_specialties ORDER BY specialty_name ASC");
$existingProfessionals = $connection->query("SELECT id, name FROM professionals ORDER BY name ASC");

// Buscar Profissionais, Especialidades para usar nos vinculos
$professionals = getProfessionals($connection);
$specialties = getSpecialties($connection);
$professionalSpecialties = getProfessionalSpecialties($connection);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro</title>

    <script>
        const professionalSpecialties = <?= json_encode($professionalSpecialties) ?>;
        const professionals = <?= json_encode($professionals) ?>;
        const specialties = <?= json_encode($specialties) ?>;

        function handleSelectChange() {
            const selectedId = document.getElementById('professional_specialty_id').value;
            const professionalSelect = document.getElementById('professional_id');
            const specialtySelect = document.getElementById('specialty_id');
            const activeSelect = document.getElementById('active');

            if (selectedId === 'novo') {
                loadOptions(professionalSelect, professionals, true);
                loadOptions(specialtySelect, specialties, true);
                activeSelect.value = "1";
                professionalSelect.disabled = false;
                specialtySelect.disabled = false;
            } else {
                const selected = professionalSpecialties.find(link => link.id == selectedId);
                if (selected) {
                    professionalSelect.value = selected.professional_id;
                    specialtySelect.value = selected.specialty_id;
                    activeSelect.value = selected.active;
                    professionalSelect.disabled = true;
                    specialtySelect.disabled = true;
                }
            }
        }

        function loadOptions(select, data, onlyActive) {
            select.innerHTML = '<option value="0">Selecione</option>';
            data.forEach(item => {
                if (!onlyActive || item.active) {
                    select.innerHTML += `<option value="${item.id}">${item.name || item.specialty_name}</option>`;
                }
            });
        }

        window.onload = function() {
            loadOptions(document.getElementById('professional_id'), professionals, true);
            loadOptions(document.getElementById('specialty_id'), specialties, true);
            handleSelectChange();
        }
    </script>
</head>

<body class="text-white" style="background-color: #156183;">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <!-- Especialidade -->
            <div class="col-md-3 p-3 bg-white rounded shadow mb-4 mx-1">
                <h2 class="text-center mb-4">Especialidade Médica</h2>
                <form method="POST">
                    <select name="specialty_id" class="form-select mb-3" onchange="window.location.href='?specialty_id=' + this.value">
                        <option value="">Nova Especialidade</option>
                        <?php while ($row = $existingSpecialties->fetch_assoc()): ?>
                            <option value="<?= $row['id'] ?>" <?= ($row['id'] == $specialty['id']) ? 'selected' : '' ?>><?= $row['specialty_name'] ?></option>
                        <?php endwhile; ?>
                    </select>
                    <input type="text" name="specialty_name" class="form-control mb-3" placeholder="Nome da Especialidade" maxlength="100" autocomplete="off" value="<?= htmlspecialchars($specialty['specialty_name']) ?>" required>
                    <select name="active" class="form-select mb-3">
                        <option value="1" <?= ($specialty['active'] == 1) ? 'selected' : '' ?>>Ativo</option>
                        <option value="0" <?= ($specialty['active'] == 0) ? 'selected' : '' ?>>Inativo</option>
                    </select>
                    <button type="submit" class="btn btn-primary w-100">Salvar</button>
                </form>
            </div>

            <!-- Profissional -->
            <div class="col-md-3 p-3 bg-white rounded shadow mb-4 mx-1">
                <h2 class="text-center mb-4">Profissional</h2>
                <form method="POST">
                    <select name="professional_id" class="form-select mb-3" onchange="window.location.href='?professional_id=' + this.value">
                        <option value="">Novo Profissional</option>
                        <?php while ($row = $existingProfessionals->fetch_assoc()): ?>
                            <option value="<?= $row['id'] ?>" <?= ($row['id'] == $professional['id']) ? 'selected' : '' ?>><?= $row['name'] ?></option>
                        <?php endwhile; ?>
                    </select>
                    <input type="text" name="professional_name" class="form-control mb-3" placeholder="Nome"  maxlength="150" autocomplete="off" value="<?= htmlspecialchars($professional['name']) ?>" required>
                    <input type="number" name="license_number" class="form-control mb-3" placeholder="Nº Licença" value="<?= htmlspecialchars($professional['license_number']) ?>" required>
                    <select name="license_type" class="form-select mb-3">
                        <option value="CRM" <?= ($professional['license_type'] == 'CRM') ? 'selected' : '' ?>>CRM</option>
                        <option value="COREN" <?= ($professional['license_type'] == 'COREN') ? 'selected' : '' ?>>COREN</option>
                        <option value="CRFA" <?= ($professional['license_type'] == 'CRFA') ? 'selected' : '' ?>>CRFA</option>
                        <option value="CRP" <?= ($professional['license_type'] == 'CRP') ? 'selected' : '' ?>>CRP</option>
                        <option value="CRN" <?= ($professional['license_type'] == 'CRN') ? 'selected' : '' ?>>CRN</option>
                    </select>
                    <select name="active" class="form-select mb-3">
                        <option value="1" <?= ($professional['active'] == 1) ? 'selected' : '' ?>>Ativo</option>
                        <option value="0" <?= ($professional['active'] == 0) ? 'selected' : '' ?>>Inativo</option>
                    </select>
                    <button type="submit" class="btn btn-primary w-100">Salvar</button>
                </form>
            </div>
        </div>

        <!-- Formulário de Vínculo Profissional/Especialidade -->
        <div class="row text-center justify-content-center">
            <div class="col-md-8 p-3 bg-white rounded shadow mb-4 mx-1 text-center">
                <h2 class="text-center mb-4">Gerenciar Profissional/Especialidade</h2>
                <form method="POST">
                    <select id="professional_specialty_id" name="professional_specialty_id" class="form-select mb-3" required onchange="handleSelectChange()">
                        <option value="novo">Novo Vínculo</option>
                        <?php foreach ($professionalSpecialties as $link): ?>
                            <option value="<?= $link['id'] ?>">
                                <?= $link['professional_name'] ?> - <?= $link['specialty_name'] ?> - <?= $link['active'] ? 'Ativo' : 'Inativo' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select id="professional_id" name="professional_id" class="form-select mb-3" required></select>
                    <select id="specialty_id" name="specialty_id" class="form-select mb-3" required></select>
                    <select id="active" name="active" class="form-select mb-3" required>
                        <option value="1">Ativo</option>
                        <option value="0">Inativo</option>
                    </select>
                    <button type="submit" class="btn btn-primary w-100">Salvar</button>
                </form>
            </div>
        </div>

    </div>
</body>

</html>