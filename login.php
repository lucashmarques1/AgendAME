<?php

require_once("./connection/connection.php");
require_once("./setting/links.php");
require_once("./setting/user.php");
require_once("./src/php/header.php");


if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET["logout"]) && $_GET["logout"] === "true") {
    session_destroy();
    header("Location: {$screen_login}");
    exit();
}


// Verificar se o usuário está logado e redirecionar com base no tipo de usuário
if (isset($_SESSION["id_user"]) && ($_SESSION["user_type"] == $user_administrator || $_SESSION["user_type"] == $user_supervisor)) {
    header("Location: {$screen_historic}");
} elseif (isset($_SESSION["id_user"]) && $_SESSION["user_type"] == $user_callcenter) {
    header("Location: {$screen_cancellation_form}");
} elseif (isset($_SESSION["id_user"]) && $_SESSION["user_type"] == $user_scheduling) {
    header("Location: {$screen_scheduling}");
}


// Logar usuario
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["LOGIN"])) {
    $username = $_POST["USERNAME"];
    $password = md5($_POST["PASSWORD"]);

    // Consultar o banco de dados para verificar se o usuário existe
    $query = "SELECT id, name, username, user_type, password FROM user WHERE username = ? AND password = ? AND active = 1";
    $stmt = $connection->prepare($query);
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $_SESSION["id_user"] = $row["id"];
        $_SESSION["name"] = $row["name"];
        $_SESSION["username"] = $row["username"];
        $_SESSION["user_type"] = $row["user_type"];

        // Redirecionar com base no tipo de usuário
        if ($_SESSION["user_type"] == $user_administrator) {
            header("Location: {$screen_login}");
        } else {
            header("Location: {$screen_cancellation_form}");
        }
        exit();
    } else {
        // Usuário não encontrado, exibe um alerta e redireciona de volta para o formulário de login
        echo "<script>alert('Usuário ou senha inválidos!');</script>";
        echo "<script>window.location.href = '{$screen_login}';</script>";
        exit();
    }
}

?>


<!DOCTYPE html>
<html lang="pt-br">

<body>
    <section style="background-color: #156183; height: 92vh !important;">
        <div class="container h-100">
            <div class="row d-flex justify-content-center align-items-center h-100">
                <div class="col col-xl-10">
                    <div class="card" style="border-radius: 1rem;">
                        <div class="row g-0">

                            <div class="col-md-6 col-lg-5 d-none d-md-block">
                                <img src="img/ame.png" alt="login form" class="img-fluid" style="border-radius: 1rem 0 0 1rem;" />
                            </div>

                            <div class="col-md-6 col-lg-7 d-flex align-items-center">
                                <div class="card-body p-4 p-lg-4 text-black">

                                    <form method="post" action="<?php echo $screen_login; ?>">
                                        <div class="d-flex align-items-center justify-content-center">
                                            <img src="./img/logo.png" alt="Logotipo do AME" class="img-fluid mb-4" width="50%">
                                        </div>

                                        <div class="form-floating mb-4">
                                            <input type="text" id="username" class="form-control form-control-lg" name="USERNAME" placeholder="Digite seu usuário...">
                                            <label for="username">Digite seu usuário<label>
                                        </div>

                                        <div class="form-floating mb-4">
                                            <input type="password" id="password" class="form-control form-control-lg" name="PASSWORD" placeholder="Digite sua password...">
                                            <label for="password">Digite sua senha</label>
                                        </div>

                                        <div class="pt-1 mb-4">
                                            <button class="btn btn-lg btn-block col-12 text-white" name="LOGIN" style="background-color: #156183;" type="submit">Login</button>
                                        </div>

                                        <div class="text-center">
                                            <p>Última atualização: 30/12/2024</p>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</body>

</html>