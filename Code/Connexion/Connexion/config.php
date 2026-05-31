<?php
// config.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

$servername = "localhost";
$username = "root";
$password = "root"; 
$dbname = "SmartCampusDB";
$port = 8889; 

// On crée la fameuse variable $conn ici !
$conn = mysqli_connect($servername, $username, $password, $dbname, $port);

if (!$conn) {
    die(json_encode(["success" => false, "message" => "Échec BDD : " . mysqli_connect_error()]));
}
?>