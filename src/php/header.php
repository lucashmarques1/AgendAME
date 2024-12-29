<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- FavIcon -->
    <link rel="shortcut icon" href="./img/favicon.png" type="image/x-icon">

    <!-- Bootstrap 5 (CSS) -->
    <link rel="stylesheet" href="src/bootstrap/css/bootstrap.min.css">

    <!-- CSS Manual -->
    <link rel="stylesheet" href="src/css/style.css">
</head>

<body>
    <nav id="header" class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container-fluid">
            <!-- Se estive logado vai aparecer o nome da pessoa, senão vai aparecer "Ame Botucatu" -->
            <a class="navbar-brand" href="<?php echo ($tela_index) ?>"><?php echo isset($_SESSION['username']) ? "<i class='bi bi-person-circle'></i> " . $_SESSION['name'] : "Ame Botucatu" ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav" style="flex-grow: 0;">
                <ul class="navbar-nav">
                    <?php
                    if (isset($_SESSION["id_user"])) {
                        echo "<li class='nav-item'><span id='currentDateTime' class='nav-link'>" . date('d/m/Y H:i:s') . "</span></li>";

                        $userType = $_SESSION["user_type"];

                        echo "<li class='nav-item'><a class='nav-link' href='$screen_profile'>Meu Perfil</a></li>";

                        if ($userType == $user_administrator) {
                            echo "<li class='nav-item'><a class='nav-link' href='$screen_administrator'>Cadastros</a></li>";
                        }

                        if ($userType == $user_administrator || $userType == $user_callcenter || $userType == $user_supervisor) {
                            echo "<li class='nav-item'><a class='nav-link' href='$screen_cancellation_form'>Ficha de Cancelamento</a></li>";
                            echo "<li class='nav-item'><a class='nav-link' href='$screen_schedule_list'>Agendas Disponíveis</a></li>";
                        }

                        if ($userType == $user_administrator || $userType == $user_scheduling || $userType == $user_supervisor) {
                            echo "<li class='nav-item'><a class='nav-link' href='$screen_scheduling'>Agendamento</a></li>";
                        }

                        echo "<li class='nav-item'><a class='nav-link' href='$screen_historic'>Histórico</a></li>";
                        
                        echo "<li class='nav-item'><a class='nav-link' href='$screen_login?logout=true' style='color: red !important;'>Sair <i class='bi bi-box-arrow-left'></i></a></li>";
                    } else {
                        echo "<li class='nav-item'><i class='bi bi-hospital fs-2'></i></li>";
                    }
                    ?>

                </ul>
            </div>
        </div>
    </nav>


    <!-- Bootstrap 5 (JS) -->
    <script src="src/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Bootstrap 5 (Icons) -->
    <link rel="stylesheet" href="src/bootstrap/icons/font/bootstrap-icons.min.css">

    <!-- Bootstrap 5 (jQuery) -->
    <script src="src/bootstrap/jquery/jquery-3.6.0.min.js"></script>

    <!-- JS Manual -->
    <script src="./src/js/script.js"></script>
</body>

</html>