<?php
// manage_sessions.php
require_once '../../Connexion/config.php';
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $id_cours = (int)$_GET['id_cours'];
    
    // 1. Récupère le total prévu
    $q1 = mysqli_query($conn, "SELECT nb_seances_total FROM COURS WHERE id_cours = $id_cours");
    $total = mysqli_fetch_assoc($q1)['nb_seances_total'] ?? 10;
    
    // 2. Récupère les séances programmées
    $q2 = mysqli_query($conn, "SELECT * FROM EMPLOI_DU_TEMPS WHERE id_cours = $id_cours ORDER BY date_cours ASC, heure_debut ASC");
    $sessions = [];
    while($r = mysqli_fetch_assoc($q2)) { $sessions[] = $r; }
    
    echo json_encode(["success"=>true, "total"=>$total, "sessions"=>$sessions]);

} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    $action = $data->action ?? '';
    
    if ($action === 'update_total') {
        $id = (int)$data->id_cours;
        $tot = (int)$data->total;
        mysqli_query($conn, "UPDATE COURS SET nb_seances_total = $tot WHERE id_cours = $id");
        echo json_encode(["success"=>true, "message"=>"Total mis à jour !"]);
        
    } elseif ($action === 'delete') {
        $id = (int)$data->id_cours;
        $dc = mysqli_real_escape_string($conn, $data->date_cours);
        $hd = mysqli_real_escape_string($conn, $data->heure_debut);
        mysqli_query($conn, "DELETE FROM EMPLOI_DU_TEMPS WHERE id_cours=$id AND date_cours='$dc' AND heure_debut='$hd'");
        echo json_encode(["success"=>true, "message"=>"Séance supprimée !"]);
    }
}
?>
