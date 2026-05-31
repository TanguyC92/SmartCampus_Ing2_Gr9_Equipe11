<?php
require_once '../../Connexion/config.php';

$id_cours = isset($_GET['id_cours']) ? (int)$_GET['id_cours'] : 0;
$type_evaluation = isset($_GET['type_evaluation']) ? mysqli_real_escape_string($conn, $_GET['type_evaluation']) : '';

if ($id_cours === 0 || $type_evaluation === '') {
    echo json_encode(["success" => false, "message" => "Paramètres manquants"]);
    exit;
}

$sql = "SELECT I.id_inscription, U.nom, U.prenom, ET.matricule,
               N.note, N.nom_evaluation
        FROM `inscription` I
        JOIN `etudiant` ET ON I.id_etudiant = ET.id_etudiant
        JOIN `user` U ON ET.id_user = U.id_user
        LEFT JOIN `note` N ON N.id_inscription = I.id_inscription
                        AND N.type_evaluation = '$type_evaluation'
        WHERE I.id_cours = $id_cours
        ORDER BY U.nom ASC, U.prenom ASC";

$result = mysqli_query($conn, $sql);
if (!$result) {
    echo json_encode(["success" => false, "message" => mysqli_error($conn)]);
    exit;
}

$notes = [];
while ($row = mysqli_fetch_assoc($result)) {
    $notes[] = $row;
}
echo json_encode(["success" => true, "notes" => $notes]);
?>