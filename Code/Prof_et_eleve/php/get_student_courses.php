<?php
// get_student_courses.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../Connexion/config.php';

// On récupère l'ID de l'utilisateur connecté passé dans l'URL
$id_user = isset($_GET['id_user']) ? (int)$_GET['id_user'] : 0;

if ($id_user === 0) {
    echo json_encode(["success" => false, "message" => "ID Utilisateur manquant."]);
    exit;
}

// Requête SQL avec des jointures pour lier l'Utilisateur -> Étudiant -> Inscription -> Cours -> Prof
$sql = "SELECT C.*, U_prof.nom AS prof_nom, U_prof.prenom AS prof_prenom 
        FROM `cours` C 
        JOIN `inscription` I ON C.id_cours = I.id_cours 
        JOIN `etudiant` Et ON I.id_etudiant = Et.id_etudiant 
        LEFT JOIN ENSEIGNANT E ON C.id_enseignant = E.id_enseignant 
        LEFT JOIN `user` U_prof ON E.id_user = U_prof.id_user 
        WHERE Et.id_user = $id_user
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
