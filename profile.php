<?php
require_once("./connection/connection.php");
require_once("./setting/links.php");
require_once("./setting/user.php");
require_once("./src/php/header.php");

// Verifica se o usuário está logado
if (!isset($_SESSION["id_user"])) {
    header("Location: {$screen_login}");
    exit();
}

// Função para buscar informações do usuário pelo ID
function getUserInfo($connection, $user_id)
{
    $stmt = $connection->prepare("SELECT id, name, username, user_type, active FROM user WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Função para verificar a existência de um usuário com exceção do próprio ID
function userExists($connection, $username, $id = null)
{
    if ($id) {
        $stmt = $connection->prepare("SELECT * FROM user WHERE username = ? AND id != ?");
        $stmt->bind_param("si", $username, $id);
    } else {
        $stmt = $connection->prepare("SELECT * FROM user WHERE username = ?");
        $stmt->bind_param("s", $username);
    }
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// Função para cadastrar ou atualizar usuário
function saveUser($connection, $id, $name, $username, $password, $user_type, $active)
{
    if ($id) {
        if (!empty($password)) {
            $hashed_password = md5($password);
            $stmt = $connection->prepare("UPDATE user SET name = ?, username = ?, password = ?, user_type = ?, active = ? WHERE id = ?");
            $stmt->bind_param("ssssii", $name, $username, $hashed_password, $user_type, $active, $id);
        } else {
            $stmt = $connection->prepare("UPDATE user SET name = ?, username = ?, user_type = ?, active = ? WHERE id = ?");
            $stmt->bind_param("sssii", $name, $username, $user_type, $active, $id);
        }
    } else {
        $hashed_password = md5($password);
        $stmt = $connection->prepare("INSERT INTO user (name, username, password, user_type, active) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $name, $username, $hashed_password, $user_type, $active);
    }
    return $stmt->execute();
}



// Função para alterar a senha
function changePassword($connection, $id, $new_password)
{
    $hashed_password = md5($new_password);
    $stmt = $connection->prepare("UPDATE user SET password = ? WHERE id = ?");
    $stmt->bind_param("ss", $hashed_password, $id);
    return $stmt->execute();
}

// Processamento do formulário de alteração de senha
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['new_password'], $_POST['confirm_new_password'])) {
    $new_password = $_POST['new_password'];
    $confirm_new_password = $_POST['confirm_new_password'];

    if ($new_password !== $confirm_new_password) {
        print("<script>alert('As senhas não coincidem.');</script>");
    } elseif (changePassword($connection, $_SESSION['id_user'], $new_password)) {
        print("<script>alert('Senha alterada com sucesso.');</script>");
    } else {
        print("<script>alert('Erro ao alterar a senha.');</script>");
    }
}

// Processamento para buscar informações do usuário
$user = [];
if (!empty($_POST['id'])) {
    $user = getUserInfo($connection, intval($_POST['id']));
}

// Processamento do formulário de cadastro/edição de usuário
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_user'])) {
    $id = $_POST['id'] ?? null;
    $name = $_POST['name'] ?? '';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $user_type = $_POST['user_type'] ?? '';
    $active = $_POST['active'] ?? 1;

    if ($password !== $confirm_password) {
        echo "<script>alert('As senhas não coincidem.');</script>";
    } elseif (userExists($connection, $username, $id)) {
        echo "<script>alert('O nome de usuário já existe.');</script>";
    } elseif (saveUser($connection, $id, $name, $username, $password, $user_type, $active)) {
        echo "<script>alert('Usuário salvo com sucesso.'); window.location.href = window.location.href;</script>";
    } else {
        echo "<script>alert('Erro ao salvar o usuário.');</script>";
    }
}

// Busca todos os usuários para o select
$users = $connection->query("SELECT id, username FROM user ORDER BY username ASC");
?>



<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Gerenciamento de Usuários</title>
</head>

<body class="text-white" style="background-color: #156183;">
    <div class="container mt-5">
        <?php if (in_array($_SESSION["user_type"], [$user_administrator])) { // Se for Administrador, vai ter acesso 
        ?>
            <div class="row justify-content-center">
                <div class="col-md-4 p-5 bg-white rounded shadow mb-4 mx-1">
                    <h2 class="text-center mb-4">Cadastro de Usuário</h2>
                    <form method="POST">
                        <div class="mb-3">
                            <label for="select_user" class="form-label text-dark">Selecionar Usuário</label>
                            <select id="select_user" name="id" class="form-select" onchange="this.form.submit()">
                                <option value="">Novo Usuário</option>
                                <?php while ($row = $users->fetch_assoc()) { ?>
                                    <option value="<?= $row['id'] ?>" <?php if (isset($user['id']) && $user['id'] == $row['id']) echo 'selected'; ?>><?= $row['username'] ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="name" class="form-label text-dark">Nome</label>
                            <input type="text" name="name" id="name" class="form-control" value="<?php echo $user['name'] ?? ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="username" class="form-label text-dark">Usuário</label>
                            <input type="text" name="username" id="username" class="form-control" value="<?php echo $user['username'] ?? ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label text-dark">Senha</label>
                            <input type="password" name="password" id="password" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label text-dark">Confirme a Senha</label>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label for="user_type" class="form-label text-dark">Tipo de Usuário</label>
                            <select name="user_type" id="user_type" class="form-select">
                                <option value="agendamento" <?php if (isset($user['user_type']) && $user['user_type'] == 'agendamento') echo 'selected'; ?>>Agendamento</option>
                                <option value="telefonista" <?php if (isset($user['user_type']) && $user['user_type'] == 'telefonista') echo 'selected'; ?>>Telefonista</option>
                                <option value="supervisor" <?php if (isset($user['user_type']) && $user['user_type'] == 'supervisor') echo 'selected'; ?>>Supervisor</option>
                                <option value="administrador" <?php if (isset($user['user_type']) && $user['user_type'] == 'administrador') echo 'selected'; ?>>Administrador</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="active" class="form-label text-dark">Ativo</label>
                            <select name="active" id="active" class="form-select">
                                <option value="1" <?php if (isset($user['active']) && $user['active'] == 1) echo 'selected'; ?>>Sim</option>
                                <option value="0" <?php if (isset($user['active']) && $user['active'] == 0) echo 'selected'; ?>>Não</option>
                            </select>
                        </div>
                        <button type="submit" name="save_user" class="btn btn-primary w-100">Salvar</button>
                    </form>
                </div>
            <?php } ?>


            <!-- Formulário de Alteração de Senha (Somente para usuários específicos) -->
            <div class="col-md-4 p-5 bg-white rounded shadow mb-4 mx-1">
                <h2 class="text-center mb-4">Alterar Sua Senha</h2>
                <form method="POST">
                    <div class="mb-3 position-relative">
                        <label for="new_password" class="form-label text-dark">Nova Senha</label>
                        <div class="input-group">
                            <input type="password" name="new_password" id="new_password" class="form-control" required>
                            <span class="input-group-text bg-white border-0">
                                <i id="toggleNewPassword" class="bi bi-eye-fill text-dark" style="cursor: pointer;"></i>
                            </span>
                        </div>
                    </div>
                    <div class="mb-3 position-relative">
                        <label for="confirm_new_password" class="form-label text-dark">Confirmar Nova Senha</label>
                        <div class="input-group">
                            <input type="password" name="confirm_new_password" id="confirm_new_password" class="form-control" required>
                            <span class="input-group-text bg-white border-0">
                                <i id="toggleConfirmNewPassword" class="bi bi-eye-fill text-dark" style="cursor: pointer;"></i>
                            </span>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Alterar Senha</button>
                </form>
            </div>
            </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Função para mostrar/ocultar senha
            function toggleVisibility(inputId, toggleId) {
                const inputField = document.getElementById(inputId);
                const toggleIcon = document.getElementById(toggleId);

                if (toggleIcon) {
                    toggleIcon.addEventListener('click', function() {
                        if (inputField.type === 'password') {
                            inputField.type = 'text';
                        } else {
                            inputField.type = 'password';
                        }
                    });
                }
            }

            // Chama a função para os campos de senha
            toggleVisibility('password', 'togglePassword');
            toggleVisibility('confirm_password', 'toggleConfirmPassword');
            toggleVisibility('new_password', 'toggleNewPassword');
            toggleVisibility('confirm_new_password', 'toggleConfirmNewPassword');
        });
    </script>
</body>

</html>