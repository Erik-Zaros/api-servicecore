<?php

function getDBConnection() {
    $host = "localhost";
    $db   = "service_core";
    $user = "erik";
    $pass = "pass";

    try {
        $pdo = new PDO("pgsql:host=$host;dbname=$db", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        return $pdo;
    } catch (PDOException $e) {
        die("Erro ao conectar ao banco: " . $e->getMessage());
    }
}
