<?php
// manage_rooms.php
require_once '../../Connexion/config.php';
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $res = mysqli_query($conn, "SELECT * FROM SALLE ORDER BY nom ASC");
    $salles = [];
    while($r = mysqli_fetch_assoc($res)) { $salles[] = $r; }
    echo json_encode(["success" => true, "data" => $salles]);

} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    $action = $data->action ?? '';
    
    if ($action === 'add') {
        $nom = mysqli_real_escape_string($conn, trim($data->nom));
        if (mysqli_query($conn, "INSERT INTO SALLE (nom) VALUES ('$nom')")) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "message" => "Cette salle existe déjà."]);
        }
    } elseif ($action === 'delete') {
        $id = (int)$data->id_salle;
        if(mysqli_query($conn, "DELETE FROM SALLE WHERE id_salle = $id")) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "message" => "Erreur lors de la suppression."]);
        }
    }
}
?>