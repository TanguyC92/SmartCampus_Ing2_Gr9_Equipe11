<?php
// get_available_rooms.php
require_once '../../Connexion/config.php';

$date = mysqli_real_escape_string($conn, $_GET['date'] ?? '');
$h_debut = mysqli_real_escape_string($conn, $_GET['h_debut'] ?? '') . ':00';
$h_fin = mysqli_real_escape_string($conn, $_GET['h_fin'] ?? '') . ':00';

if(empty($date) || empty($_GET['h_debut']) || empty($_GET['h_fin'])) {
    echo json_encode(["success" => false, "message" => "Paramètres manquants"]); 
    exit;
}

// Magie SQL : On prend toutes les salles SAUF celles qui sont dans l'emploi du temps au même moment
$sql = "SELECT nom FROM SALLE WHERE nom NOT IN (
            SELECT salle FROM EMPLOI_DU_TEMPS 
            WHERE date_cours = '$date' 
            AND (heure_debut < '$h_fin' AND heure_fin > '$h_debut')
        ) ORDER BY nom ASC";

$res = mysqli_query($conn, $sql);
$salles = [];
while($r = mysqli_fetch_assoc($res)) { 
    $salles[] = $r['nom']; 
}

echo json_encode(["success" => true, "data" => $salles]);
?>