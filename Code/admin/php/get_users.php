<?php
// get_users.php
require_once '../../Connexion/config.php';

$role = $_GET['role'] ?? '';

if ($role === 'enseignant') {
    $sql = "SELECT E.id_enseignant AS id, U.nom, U.prenom 
            FROM ENSEIGNANT E 
            JOIN `user` U ON E.id_user = U.id_user ORDER BY U.nom ASC";
} elseif ($role === 'etudiant') {
    $sql = "SELECT Et.id_etudiant AS id, U.nom, U.prenom, Et.matricule 
            FROM `etudiant` Et 
            JOIN `user` U ON Et.id_user = U.id_user ORDER BY U.nom ASC";
} else {
    echo json_encode(["success" => false, "message" => "Rôle invalide."]);
    exit;
}

$result = mysqli_query($conn, $sql);
$list = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $list[] = $row;
    }
    echo json_encode(["success" => true, "data" => $list]);
} else {
    echo json_encode(["success" => false, "message" => mysqli_error($conn)]);
}
?>