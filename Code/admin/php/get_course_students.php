<?php
// get_course_students.php
ini_set('display_errors', 0);
require_once '../../Connexion/config.php';

$id_cours = (int)($_GET['id_cours'] ?? 0);

// Ajout de I.id_inscription pour que la sauvegarde des notes fonctionne !
$sql = "SELECT I.id_inscription, Et.id_etudiant, U.nom, U.prenom, Et.matricule 
        FROM INSCRIPTION I
        JOIN ETUDIANT Et ON I.id_etudiant = Et.id_etudiant
        JOIN USER U ON Et.id_user = U.id_user
        WHERE I.id_cours = $id_cours
        ORDER BY U.nom ASC";

$res = mysqli_query($conn, $sql);
$data = [];

if ($res) {
    while($row = mysqli_fetch_assoc($res)) { 
        $data[] = $row; 
    }
    echo json_encode(["success" => true, "data" => $data]);
} else {
    echo json_encode(["success" => false, "message" => mysqli_error($conn)]);
}
?>