<?php

header('Content-Type: application/json');

require_once '../../Connexion/config.php';

$id_cours = $_GET['id_cours'];

$stmt = $conn->prepare(

    "SELECT *
     FROM documents_cours
     WHERE id_cours = ?
     ORDER BY
     ordre_affichage ASC,
     dossier ASC,
     id_document DESC"

);

$stmt->bind_param('i', $id_cours);

$stmt->execute();

$result = $stmt->get_result();

$documents = [];

while($row = $result->fetch_assoc()){

    $documents[] = $row;
}

echo json_encode([
    'documents' => $documents
]);

?>