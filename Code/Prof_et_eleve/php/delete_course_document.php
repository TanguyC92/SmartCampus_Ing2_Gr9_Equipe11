<?php

header('Content-Type: application/json');

require_once '../../Connexion/config.php';

$data = json_decode(file_get_contents("php://input"), true);

$id_document = $data['id_document'];

$stmt = $conn->prepare(
    "SELECT chemin_fichier
     FROM documents_cours
     WHERE id_document = ?"
);

$stmt->bind_param('i', $id_document);

$stmt->execute();

$result = $stmt->get_result();

if($result->num_rows === 0){

    echo json_encode([
        'success' => false,
        'message' => 'Document introuvable'
    ]);

    exit;
}

$document = $result->fetch_assoc();

$filePath = '../../' . $document['chemin_fichier'];

if(file_exists($filePath)){
    unlink($filePath);
}

$deleteStmt = $conn->prepare(
    "DELETE FROM documents_cours
     WHERE id_document = ?"
);

$deleteStmt->bind_param('i', $id_document);

if($deleteStmt->execute()){

    echo json_encode([
        'success' => true,
        'message' => 'Document supprimé'
    ]);

}else{

    echo json_encode([
        'success' => false,
        'message' => 'Erreur SQL'
    ]);
}

?>