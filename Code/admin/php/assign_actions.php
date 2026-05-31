<?php
// assign_actions.php
require_once '../../Connexion/config.php';

$data = json_decode(file_get_contents("php://input"));
$action = $data->action ?? '';

if ($action === 'assign_teacher') {
    $id_cours = (int)$data->id_cours;
    $id_prof = (int)$data->id_enseignant;

    // VÉRIFICATION DES COLLISIONS POUR LE PROFESSEUR
    // Si le cours qu'on lui donne a des séances qui chevauchent SES autres cours
    $check_collision = "
        SELECT C2.titre, EDT1.date_cours, EDT1.heure_debut, EDT1.heure_fin
        FROM EMPLOI_DU_TEMPS EDT1
        JOIN EMPLOI_DU_TEMPS EDT2 ON EDT1.date_cours = EDT2.date_cours 
                                 AND EDT1.heure_debut < EDT2.heure_fin 
                                 AND EDT1.heure_fin > EDT2.heure_debut
        JOIN `cours` C2 ON EDT2.id_cours = C2.id_cours
        WHERE EDT1.id_cours = $id_cours
          AND C2.id_enseignant = $id_prof
          AND EDT1.id_cours != EDT2.id_cours
        LIMIT 1
    ";
    $res_col = mysqli_query($conn, $check_collision);
    
    if (mysqli_num_rows($res_col) > 0) {
        $conflit = mysqli_fetch_assoc($res_col);
        echo json_encode(["success" => false, "message" => "Impossible : Ce professeur a déjà le cours '" . $conflit['titre'] . "' le " . $conflit['date_cours'] . " qui chevauche une séance de ce nouveau cours."]);
        exit;
    }

    $sql = "UPDATE COURS SET id_enseignant = $id_prof WHERE id_cours = $id_cours";
    if (mysqli_query($conn, $sql)) {
        echo json_encode(["success" => true, "message" => "Enseignant assigné !"]);
    } else {
        echo json_encode(["success" => false, "message" => mysqli_error($conn)]);
    }

} elseif ($action === 'assign_student') {
    $id_cours = (int)$data->id_cours;
    $id_eleve = (int)$data->id_etudiant;

    // 1. Est-il déjà inscrit ?
    $check = mysqli_query($conn, "SELECT * FROM `inscription` WHERE id_etudiant = $id_eleve AND id_cours = $id_cours");
    if (mysqli_num_rows($check) > 0) {
        echo json_encode(["success" => false, "message" => "Cet élève est déjà inscrit à ce cours."]);
        exit;
    } 

    // 2. VÉRIFICATION DES COLLISIONS POUR L'ÉLÈVE
    $check_collision = "
        SELECT C2.titre, EDT1.date_cours, EDT1.heure_debut, EDT1.heure_fin
        FROM EMPLOI_DU_TEMPS EDT1
        JOIN EMPLOI_DU_TEMPS EDT2 ON EDT1.date_cours = EDT2.date_cours 
                                 AND EDT1.heure_debut < EDT2.heure_fin 
                                 AND EDT1.heure_fin > EDT2.heure_debut
        JOIN `cours` C2 ON EDT2.id_cours = C2.id_cours
        JOIN `inscription` I ON I.id_cours = C2.id_cours
        WHERE EDT1.id_cours = $id_cours
          AND I.id_etudiant = $id_eleve
          AND EDT1.id_cours != EDT2.id_cours
        LIMIT 1
    ";
    $res_col = mysqli_query($conn, $check_collision);
    
    if (mysqli_num_rows($res_col) > 0) {
        $conflit = mysqli_fetch_assoc($res_col);
        echo json_encode(["success" => false, "message" => "Impossible : Cet élève a déjà le cours '" . $conflit['titre'] . "' le " . $conflit['date_cours'] . " en même temps."]);
        exit;
    }

    // 3. Si tout est bon, on l'inscrit
    $sql = "INSERT INTO INSCRIPTION (id_etudiant, id_cours) VALUES ($id_eleve, $id_cours)";
    if (mysqli_query($conn, $sql)) {
        echo json_encode(["success" => true, "message" => "Élève inscrit !"]);
    } else {
        echo json_encode(["success" => false, "message" => mysqli_error($conn)]);
    }

} elseif ($action === 'unassign_student') {
    $id_cours = (int)$data->id_cours;
    $id_eleve = (int)$data->id_etudiant;

    $sql = "DELETE FROM `inscription` WHERE id_etudiant = $id_eleve AND id_cours = $id_cours";
    if (mysqli_query($conn, $sql)) {
        echo json_encode(["success" => true, "message" => "Élève retiré du cours !"]);
    } else {
        echo json_encode(["success" => false, "message" => "Erreur SQL : " . mysqli_error($conn)]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Action inconnue."]);
}
?>