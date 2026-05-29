<?php
// get_teacher_courses.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../Connexion/config.php';

$id_user = isset($_GET['id_user']) ? (int)$_GET['id_user'] : 0;

if ($id_user === 0) {
    echo json_encode(["success" => false, "message" => "ID Utilisateur manquant."]);
    exit;
}

// On fait la jointure pour trouver les cours liés à cet enseignant précis
$sql = "SELECT C.* FROM COURS C 
        JOIN ENSEIGNANT E ON C.id_enseignant = E.id_enseignant 
        WHERE E.id_user = $id_user
        ORDER BY C.semestre ASC, C.titre ASC";

$result = mysqli_query($conn, $sql);
$courses = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $courses[] = $row;
    }
    echo json_encode(["success" => true, "courses" => $courses]);
} else {
    echo json_encode(["success" => false, "message" => "Erreur SQL : " . mysqli_error($conn)]);
}
?>