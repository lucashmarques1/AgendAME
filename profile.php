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


// Função para verificar a existência de um usuário
function userExists($connection, $username)
{
    $stmt = $connection->prepare("SELECT * FROM user WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// Função para cadastrar usuário
function registerUser($connection, $username, $password, $user_type)
{
    $hashed_password = md5($password);
    $stmt = $connection->prepare("INSERT INTO user (username, password, user_type) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $hashed_password, $user_type);
    return $stmt->execute();
}

// Função para alterar a senha
function changePassword($connection, $username, $new_password)
{
    $hashed_password = md5($new_password);
    $stmt = $connection->prepare("UPDATE user SET password = ? WHERE username = ?");
    $stmt->bind_param("ss", $hashed_password, $username);
    return $stmt->execute();
}

// Função para redefinir a senha de um usuário
function resetPassword($connection, $user_id, $new_password = 'ame@123')
{
    $hashed_password = md5($new_password);
    $stmt = $connection->prepare("UPDATE user SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashed_password, $user_id);
    return $stmt->execute();
}

// Processamento do formulário de usuário
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['username'], $_POST['password'], $_POST['confirm_password'], $_POST['user_type'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $user_type = $_POST['user_type'];

    if ($password !== $confirm_password) {
        print("<script>alert('As senhas não coincidem.');</script>");
    } elseif (userExists($connection, $username)) {
        print("<script>alert('O nome de usuário já existe.');</script>");
    } elseif (registerUser($connection, $username, $password, $user_type)) {
        print("<script>alert('Usuário cadastrado com sucesso.');</script>");
    } else {
        print("<script>alert('Erro ao cadastrar o usuário.');</script>");
    }
}

// Processamento do formulário de alteração de senha
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['new_password'], $_POST['confirm_new_password'])) {
    $new_password = $_POST['new_password'];
    $confirm_new_password = $_POST['confirm_new_password'];

    if ($new_password !== $confirm_new_password) {
        print("<script>alert('As senhas não coincidem.');</script>");
    } elseif (changePassword($connection, $_SESSION['username'], $new_password)) {
        print("<script>alert('Senha alterada com sucesso.');</script>");
    } else {
        print("<script>alert('Erro ao alterar a senha.');</script>");
    }
}

// Processamento do formulário de redefinição de senha (nova funcionalidade)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];

    if (resetPassword($connection, $user_id)) {
        print("<script>alert('Senha redefinida para o usuário com sucesso!');</script>");
    } else {
        print("<script>alert('Erro ao redefinir a senha.');</script>");
    }
}

// Busca todos os usuários para o formulário de redefinição de senha
$user = $connection->query("SELECT id, username FROM user ORDER BY username ASC");

?>



<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu perfil</title>
</head>

<body class="text-white " style="background-color: #156183;">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <?php if (in_array($_SESSION["user_type"], [$user_administrator])) { // Se for Administrador, vai ter acesso 
            ?>
                <!-- Formulário de Cadastro de Usuário -->
                <div class="col-md-4 p-4 bg-white rounded shadow mb-4 mx-1">
                    <h2 class="text-center mb-4 text-dark">Cadastro de Usuário</h2>
                    <form method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label text-dark">Nome de Usuário</label>
                            <input type="text" name="username" class="form-control" autocomplete="off" required>
                        </div>
                        <div class="mb-3 position-relative">
                            <label for="password" class="form-label text-dark">Senha</label>
                            <div class="input-group">
                                <input type="password" name="password" id="password" class="form-control" required>
                                <span class="input-group-text bg-white border-0">
                                    <i id="togglePassword" class="bi bi-eye-fill text-dark" style="cursor: pointer;"></i>
                                </span>
                            </div>
                        </div>
                        <div class="mb-3 position-relative">
                            <label for="confirm_password" class="form-label text-dark">Confirme a Senha</label>
                            <div class="input-group">
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                                <span class="input-group-text bg-white border-0">
                                    <i id="toggleConfirmPassword" class="bi bi-eye-fill text-dark" style="cursor: pointer;"></i>
                                </span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="user_type" class="form-label text-dark">Tipo de Usuário</label>
                            <select name="user_type" class="form-select" required>
                                <option value="agendamento">Agendamento</option>
                                <option value="telefonista">Telefonista</option>
                                <option value="supervisor">Supervisor</option>
                                <option value="administrador">Administrador</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Cadastrar Usuário</button>
                    </form>
                </div>

                <!-- Formulário de Redefinição de Senha -->
                <div class="col-md-4 p-4 bg-white rounded shadow mb-4 mx-1">
                    <h2 class="text-center mb-4 text-dark">Redefinir Senha</h2>
                    <form method="POST" onsubmit="return confirm('Tem certeza que deseja redefinir a senha?');">
                        <!-- Seleção do Usuário -->
                        <div class="mb-3">
                            <label for="user_select" class="form-label text-dark">Selecione o Usuário</label>
                            <select id="user_select" name="user_id" class="form-select" required>
                                <?php
                                // Consulta para listar os usuários
                                $query = "SELECT id, username FROM user"; // Inclui id e username
                                $result = $connection->query($query); // Reutiliza a conexão aberta

                                if ($result->num_rows > 0) {
                                    // Iterando sobre os resultados
                                    while ($row = $result->fetch_assoc()) {
                                        // Usando o id como value e username como texto do option
                                        echo "<option value='{$row['id']}'>{$row['username']}</option>";
                                    }
                                } else {
                                    echo "<option disabled>Nenhum usuário encontrado</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <!-- Botão para redefinir senha -->
                        <button type="submit" class="btn btn-primary w-100">Redefinir Senha</button>
                    </form>
                </div>

            <?php } ?>

            <!-- Formulário de Alteração de Senha (Somente para usuários específicos) -->
            <div class="col-md-4 p-4 bg-white rounded shadow mb-4 mx-1">
                <h2 class="text-center mb-4 text-dark">Alterar Sua Senha</h2>
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
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Função genérica para mostrar/ocultar senha
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