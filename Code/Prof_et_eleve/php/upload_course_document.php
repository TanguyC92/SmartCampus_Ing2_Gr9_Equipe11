<?php

header('Content-Type: application/json');

require_once '../../Connexion/config.php';

$id_cours = $_POST['id_cours'];
$id_professeur = $_POST['id_professeur'];

$titre = $_POST['titre'];
$description = $_POST['description'];
$note_document = $_POST['note_document'];

$dossier = trim($_POST['dossier']);
$ordre_affichage = intval($_POST['ordre_affichage']);

if(!isset($_FILES['document'])){

    echo json_encode([
        'success' => false,
        'message' => 'Aucun fichier reçu'
    ]);

    exit;
}

$uploadDir = '../../uploads/cours/';

if(!file_exists($uploadDir)){
    mkdir($uploadDir, 0777, true);
}

$fileName =
    time() . '_' .
    basename($_FILES['document']['name']);

$targetFile =
    $uploadDir . $fileName;

if(move_uploaded_file(
    $_FILES['document']['tmp_name'],
    $targetFile
)){

    $chemin_fichier =
        'uploads/cours/' . $fileName;

    $stmt = $conn->prepare(

        "INSERT INTO documents_cours
        (
            id_cours,
            id_professeur,
            titre,
            description,
            note_document,
            nom_fichier,
            chemin_fichier,
            dossier,
            ordre_affichage
        )

        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"

    );

    $stmt->bind_param(

        'iissssssi',

        $id_cours,
        $id_professeur,
        $titre,
        $description,
        $note_document,
        $fileName,
        $chemin_fichier,
        $dossier,
        $ordre_affichage

    );

    if($stmt->execute()){

        echo json_encode([
            'success' => true,
            'message' => 'Document ajouté'
        ]);

    } else {

        echo json_encode([
            'success' => false,
            'message' => 'Erreur SQL'
        ]);

    }

} else {

    echo json_encode([
        'success' => false,
        'message' => 'Erreur upload fichier'
    ]);

}

?>