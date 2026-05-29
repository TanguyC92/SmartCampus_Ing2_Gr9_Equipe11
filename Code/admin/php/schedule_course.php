<?php
// schedule_course.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../../Connexion/config.php';

$data = json_decode(file_get_contents("php://input"));

if (isset($data->id_cours, $data->date_cours, $data->heure_debut, $data->heure_fin, $data->salle)) {
    
    $id_cours = (int)$data->id_cours;
    $date_cours = mysqli_real_escape_string($conn, $data->date_cours);
    $h_debut = mysqli_real_escape_string($conn, $data->heure_debut) . ":00";
    $h_fin = mysqli_real_escape_string($conn, $data->heure_fin) . ":00";
    $salle = mysqli_real_escape_string($conn, $data->salle);

    if ($h_debut >= $h_fin) {
        echo json_encode(["success" => false, "message" => "L'heure de début doit être avant l'heure de fin."]);
        exit;
    }

    $date_obj = new DateTime($date_cours);
    if ($date_obj->format('w') == 0) {
        echo json_encode(["success" => false, "message" => "Impossible de programmer un cours le dimanche !"]);
        exit;
    }

    // MOTEUR DE COLLISION AMÉLIORÉ (Salles + Personnes)
    $collision_query = "
        SELECT C.titre, EDT.heure_debut, EDT.heure_fin, EDT.salle,
               CASE 
                   WHEN EDT.salle = '$salle' THEN 'salle'
                   ELSE 'personne'
               END as type_conflit
        FROM EMPLOI_DU_TEMPS EDT
        JOIN COURS C ON EDT.id_cours = C.id_cours
        WHERE EDT.date_cours = '$date_cours'
          AND (EDT.heure_debut < '$h_fin' AND EDT.heure_fin > '$h_debut')
          AND (
               EDT.salle = '$salle'  /* <-- LA SALLE EST-ELLE DÉJÀ PRISE ? */
               OR (C.id_enseignant IS NOT NULL AND C.id_enseignant = (SELECT id_enseignant FROM COURS WHERE id_cours = $id_cours))
               OR C.id_cours IN (
                   SELECT I2.id_cours FROM INSCRIPTION I1
                   JOIN INSCRIPTION I2 ON I1.id_etudiant = I2.id_etudiant
                   WHERE I1.id_cours = $id_cours
               )
          ) LIMIT 1";

    $result_collision = mysqli_query($conn, $collision_query);

    if (mysqli_num_rows($result_collision) > 0) {
        $conflit = mysqli_fetch_assoc($result_collision);
        if ($conflit['type_conflit'] == 'salle') {
            $msg = "Conflit de salle ! Le cours '" . $conflit['titre'] . "' occupe déjà la salle " . $conflit['salle'] . " de " . substr($conflit['heure_debut'],0,5) . " à " . substr($conflit['heure_fin'],0,5) . ".";
        } else {
            $msg = "Conflit ! Un prof ou élève de ce cours est déjà pris par le cours '" . $conflit['titre'] . "' sur ce créneau.";
        }
        echo json_encode(["success" => false, "message" => $msg]);
    } else {
        $sql_insert = "INSERT INTO EMPLOI_DU_TEMPS (date_cours, heure_debut, heure_fin, salle, id_cours) 
                       VALUES ('$date_cours', '$h_debut', '$h_fin', '$salle', $id_cours)";
        if (mysqli_query($conn, $sql_insert)) {
            echo json_encode(["success" => true, "message" => "Séance programmée avec succès le $date_cours !"]);
        } else {
            echo json_encode(["success" => false, "message" => "Erreur BDD : " . mysqli_error($conn)]);
        }
    }
} else {
    echo json_encode(["success" => false, "message" => "Données manquantes."]);
}
?>