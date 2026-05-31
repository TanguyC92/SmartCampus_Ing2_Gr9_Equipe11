<?php
// get_timetable.php
ini_set('display_errors', 0);
require_once '../../Connexion/config.php';

$id_user   = (int)($_GET['id_user'] ?? 0);
$role      = strtolower(trim($_GET['role'] ?? 'etudiant'));
$week_start = $_GET['week_start'] ?? '';
$week_end   = $_GET['week_end']   ?? '';

$schedule = [];
$debug    = "Analyse en cours...";

// Sous-requête pour le rang de la séance
$rank_query = "(SELECT COUNT(*) FROM EMPLOI_DU_TEMPS EDT2
                WHERE EDT2.id_cours = EDT.id_cours
                AND (EDT2.date_cours < EDT.date_cours
                  OR (EDT2.date_cours = EDT.date_cours AND EDT2.heure_debut <= EDT.heure_debut))
               ) AS seance_rank";

if ($role === 'etudiant' || $role === 'étudiant') {

    $q1 = mysqli_query($conn, "SELECT id_etudiant FROM ETUDIANT WHERE id_user = $id_user");
    if (mysqli_num_rows($q1) == 0) {
        $debug = "Erreur : Ce compte n'a pas de profil dans la table ETUDIANT.";
    } else {
        $etu    = mysqli_fetch_assoc($q1);
        $id_etu = $etu['id_etudiant'];

        $q2 = mysqli_query($conn, "SELECT id_cours FROM INSCRIPTION WHERE id_etudiant = $id_etu");
        if (mysqli_num_rows($q2) == 0) {
            $debug = "L'élève (ID: $id_etu) n'est inscrit à AUCUN cours.";
        } else {
            $cours_ids = [];
            while ($c = mysqli_fetch_assoc($q2)) { $cours_ids[] = $c['id_cours']; }
            $in_clause = implode(',', $cours_ids);

            $q3 = mysqli_query($conn, "SELECT * FROM EMPLOI_DU_TEMPS WHERE id_cours IN ($in_clause)");
            if (mysqli_num_rows($q3) == 0) {
                $debug = "L'élève est inscrit aux cours ($in_clause), mais aucune séance n'est programmée.";
            } else {
                // C.niveau joue le rôle de « classe » dans cette BDD
                $sql_final = "SELECT EDT.*,
                                     C.titre,
                                     C.niveau,
                                     C.semestre,
                                     C.nb_seances_total,
                                     U.nom    AS prof_nom,
                                     U.prenom AS prof_prenom,
                                     $rank_query
                              FROM EMPLOI_DU_TEMPS EDT
                              JOIN COURS     C ON EDT.id_cours      = C.id_cours
                              LEFT JOIN ENSEIGNANT E ON C.id_enseignant = E.id_enseignant
                              LEFT JOIN USER  U ON E.id_user        = U.id_user
                              WHERE EDT.id_cours IN ($in_clause)
                                AND EDT.date_cours >= '$week_start'
                                AND EDT.date_cours <= '$week_end'";

                $q_final = mysqli_query($conn, $sql_final);
                if (mysqli_num_rows($q_final) == 0) {
                    $debug = "Séances existantes, mais aucune entre le $week_start et le $week_end.";
                } else {
                    while ($r = mysqli_fetch_assoc($q_final)) { $schedule[] = $r; }
                    $debug = "Tout fonctionne parfaitement !";
                }
            }
        }
    }

} else {
    // Enseignant — on expose niveau + semestre comme « classe »
    $sql_prof = "SELECT EDT.*,
                        C.titre,
                        C.niveau,
                        C.semestre,
                        C.nb_seances_total,
                        U.nom    AS prof_nom,
                        U.prenom AS prof_prenom,
                        $rank_query
                 FROM EMPLOI_DU_TEMPS EDT
                 JOIN COURS      C ON EDT.id_cours      = C.id_cours
                 JOIN ENSEIGNANT E ON C.id_enseignant   = E.id_enseignant
                 JOIN USER       U ON E.id_user         = U.id_user
                 WHERE E.id_user = $id_user
                   AND EDT.date_cours >= '$week_start'
                   AND EDT.date_cours <= '$week_end'";

    $res = mysqli_query($conn, $sql_prof);
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) { $schedule[] = $r; }
    }
    $debug = count($schedule) > 0 ? "Cours trouvés." : "Aucun cours pour ce prof cette semaine.";
}

echo json_encode(["success" => true, "data" => $schedule, "debug" => $debug]);
?>
