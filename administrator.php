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


// Função para verificar a existência de especialidade
function specialtyExists($connection, $specialty_name)
{
    $stmt = $connection->prepare("SELECT * FROM medical_specialty WHERE specialty_name = ?");
    $stmt->bind_param("s", $specialty_name);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// Função para cadastrar especialidade
function registerSpecialty($connection, $specialty_name)
{
    $stmt = $connection->prepare("INSERT INTO medical_specialty (specialty_name) VALUES (?)");
    $stmt->bind_param("s", $specialty_name);
    return $stmt->execute();
}

// Funções para carregar dados
function getProfessionals($connection)
{
    $stmt = $connection->prepare("SELECT id, name, active FROM professional");
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getSpecialties($connection)
{
    $stmt = $connection->prepare("SELECT id, specialty_name, active FROM medical_specialty");
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getProfessionalSpecialties($connection)
{
    $stmt = $connection->prepare(
        "SELECT ps.id, ps.professional_id, ps.specialty_id, p.name AS professional_name, s.specialty_name, ps.active
        FROM professional_specialty ps
        JOIN professional p ON ps.professional_id = p.id
        JOIN medical_specialty s ON ps.specialty_id = s.id"
    );
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
                "SELECT COUNT(*) FROM professional_specialty WHERE professional_id = ? AND specialty_id = ?"
            );
            $checkStmt->bind_param("ii", $professional_id, $specialty_id);
            $checkStmt->execute();
            $checkStmt->bind_result($count);
            $checkStmt->fetch();
            $checkStmt->close();

            if ($count > 0) {
                print("<script>alert('Este vínculo já existe!');</script>");
            } else {
                $stmt = $connection->prepare("INSERT INTO professional_specialty (professional_id, specialty_id, active) VALUES (?, ?, ?)");
                $stmt->bind_param("iii", $professional_id, $specialty_id, $active);
                $stmt->execute();
                print("<script>alert('Vínculo criado com sucesso!');</script>");
            }
        } else {
            // Atualiza diretamente o status ativo/inativo
            $stmt = $connection->prepare("UPDATE professional_specialty SET active = ? WHERE id = ?");
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
    // Atualiza a página para refletir mudanças no frontend
    print("<script>window.location.href = window.location.href;</script>");
    exit();
}

// Processamento do formulário de especialidade
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['specialty_name'])) {
    $specialty_name = $_POST['specialty_name'];

    if (specialtyExists($connection, $specialty_name)) {
        print("<script>alert('A especialidade já existe.');</script>");
    } elseif (registerSpecialty($connection, $specialty_name)) {
        print("<script>alert('Especialidade cadastrada com sucesso.');</script>");
    } else {
        print("<script>alert('Erro ao cadastrar a especialidade.');</script>");
    }
}

// Busca todas as especialidades médicas existentes
$existingSpecialties = $connection->query("SELECT specialty_name FROM medical_specialty ORDER BY specialty_name ASC");


$professionals = getProfessionals($connection);
$specialties = getSpecialties($connection);
$professionalSpecialties = getProfessionalSpecialties($connection);
?>



<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu perfil</title>

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
            handleSelectChange(); // Garante carregamento inicial correto
        }
    </script>
</head>

<body class="text-white " style="background-color: #156183;">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <!-- Formulário de Cadastro de Especialidade Médica -->
            <div class="col-md-3 p-3 bg-white rounded shadow mb-4 mx-1">
                <h2 class="text-center mb-4 text-dark">Especialidade Médica</h2>
                <form method="POST">
                    <div class="mb-3">
                        <label for="specialty_name" class="form-label text-dark">Nome da Especialidade</label>
                        <input type="text" name="specialty_name" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mb-4">Cadastrar Especialidade</button>
                </form>

                <div class="mb-3">
                    <label for="existing_specialties" class="form-label text-dark">Especialidades Existentes</label>
                    <select id="existing_specialties" class="form-select">
                        <option value="" disabled selected>Selecione uma especialidade médica</option>
                        <?php while ($row = $existingSpecialties->fetch_assoc()): ?>
                            <option value="<?php echo $row['specialty_name']; ?>"><?php echo $row['specialty_name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <!-- Formulário de Vínculo -->
            <form method="POST">
                <h2>Gerenciar Vínculo</h2>
                <select id="professional_specialty_id" name="professional_specialty_id" required onchange="handleSelectChange()">
                    <option value="novo">Novo</option>
                    <?php foreach ($professionalSpecialties as $link): ?>
                        <option value="<?= $link['id'] ?>">
                            <?= $link['professional_name'] ?> - <?= $link['specialty_name'] ?> - <?= $link['active'] ? 'Ativo' : 'Inativo' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select id="professional_id" name="professional_id" required></select>
                <select id="specialty_id" name="specialty_id" required></select>
                <select id="active" name="active" required>
                    <option value="1">Ativo</option>
                    <option value="0">Inativo</option>
                </select>
                <button type="submit">Salvar</button>
            </form>
        </div>
    </div>
</body>

</html>