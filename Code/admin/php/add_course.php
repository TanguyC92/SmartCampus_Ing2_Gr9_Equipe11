<?php
// add_course.php
require_once '../../Connexion/config.php';

$data = json_decode(file_get_contents("php://input"));

// On vérifie que le coefficient est bien transmis avec les autres données
if (isset($data->code_cours) && isset($data->titre) && isset($data->credits) && isset($data->capacite_max) && isset($data->coefficient)) {
    $code = mysqli_real_escape_string($conn, $data->code_cours);
    $titre = mysqli_real_escape_string($conn, $data->titre);
    $credits = (int)$data->credits;
    $capacite = (int)$data->capacite_max;
    $coefficient = (int)$data->coefficient; // Conversion en entier pour la sécurité
    $semestre = mysqli_real_escape_string($conn, $data->semestre ?? 'S1');
    $niveau = mysqli_real_escape_string($conn, $data->niveau ?? 'ING2');

    // Insertion avec la nouvelle colonne coefficient
    $sql = "INSERT INTO COURS (code_cours, titre, credits, coefficient, capacite_max, semestre, niveau) 
            VALUES ('$code', '$titre', $credits, $coefficient, $capacite, '$semestre', '$niveau')";

    if (mysqli_query($conn, $sql)) {
        echo json_encode(["success" => true, "message" => "Cours créé avec succès !"]);
    } else {
        echo json_encode(["success" => false, "message" => "Erreur SQL : " . mysqli_error($conn)]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Données incomplètes."]);
}
?>
