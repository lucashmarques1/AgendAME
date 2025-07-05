<?php
$server       = "localhost:3306";
$user_bd      = "root";
$password_bd  = "root";
$bd           = "patient_schedule";

$connection  = mysqli_connect($server, $user_bd, $password_bd, $bd);

if (mysqli_connect_errno()) {
    die("Falha na conexão: Erro Nº " . mysqli_connect_errno());
}


session_start();


// Definir o fuso horário para São Paulo (Brasil)
date_default_timezone_set('America/Sao_Paulo');
$currentDateTime = date("Y-m-d H:i:s");
