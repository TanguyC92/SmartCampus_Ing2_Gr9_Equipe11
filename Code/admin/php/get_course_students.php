<?php
// get_course_students.php
require_once '../../Connexion/config.php';

$id_cours = isset($_GET['id_cours']) ? (int)$_GET['id_cours'] : 0;

$sql = "SELECT I.id_inscription, U.nom, U.prenom, Et.matricule 
        FROM `inscription` I
        JOIN `etudiant` Et ON I.id_etudiant = Et.id_etudiant
        JOIN `user` U ON Et.id_user = U.id_user
        WHERE I.id_cours = $id_cours
        ORDER BY U.nom ASC";

$result = mysqli_query($conn, $sql);
$students = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $students[] = $row;
    }
    echo json_encode(["success" => true, "data" => $students]);
} else {
    echo json_encode(["success" => false, "message" => mysqli_error($conn)]);
}
?>