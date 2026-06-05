<?php
require_once '../../Connexion/config.php';

$data = json_decode(file_get_contents("php://input"));

if (isset($data->code_cours) && isset($data->titre) && isset($data->credits) && isset($data->capacite_max) && isset($data->coefficient)) {
    
    $code = mysqli_real_escape_string($conn, $data->code_cours);
    $titre = mysqli_real_escape_string($conn, $data->titre);
    $credits = (int)$data->credits;
    $capacite = (int)$data->capacite_max;
    $coefficient = (int)$data->coefficient;
    $semestre = mysqli_real_escape_string($conn, $data->semestre ?? 'S1');
    $niveau = mysqli_real_escape_string($conn, $data->niveau ?? 'ING2');

    $sql = "INSERT INTO COURS (code_cours, titre, credits, coefficient, capacite_max, semestre, niveau, nb_seances_total) 
            VALUES ('$code', '$titre', $credits, $coefficient, $capacite, '$semestre', '$niveau', 10)";

    try {
        if (mysqli_query($conn, $sql)) {
            echo json_encode(["success" => true, "message" => "Cours créé avec succès !"]);
        } else {
            echo json_encode(["success" => false, "message" => "Erreur SQL : " . mysqli_error($conn)]);
        }
    } catch (Exception $e) {
        // Si MySQL crashe, on attrape l'erreur et on l'affiche proprement au Javascript !
        echo json_encode(["success" => false, "message" => "Erreur de Base de données : " . $e->getMessage()]);
    }

} else {
    echo json_encode(["success" => false, "message" => "Données incomplètes."]);
}
?>