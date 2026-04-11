<?php
$host = 'mysql';
$db   = 'rodrigo';
$user = 'root';
$pass = 'root';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Erro: " . $conn->connect_error);
}

echo "Conectado com sucesso ao MySQL!";
?>