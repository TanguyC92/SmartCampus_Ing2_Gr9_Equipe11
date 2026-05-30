<?php
// get_timetable.php
// On cache les erreurs PHP pures pour ne pas casser le format JSON du retour
ini_set('display_errors', 0);
require_once '../../Connexion/config.php';

$id_user = (int)($_GET['id_user'] ?? 0);
$role = strtolower(trim($_GET['role'] ?? 'etudiant'));
$week_start = $_GET['week_start'] ?? ''; 
$week_end = $_GET['week_end'] ?? '';

$schedule = [];
$debug = "Analyse en cours...";

// NOUVEAUTÉ : La sous-requête SQL pour calculer le rang de la séance (le "X" de "X/10")
$rank_query = "(SELECT COUNT(*) FROM EMPLOI_DU_TEMPS EDT2 WHERE EDT2.id_cours = EDT.id_cours AND (EDT2.date_cours < EDT.date_cours OR (EDT2.date_cours = EDT.date_cours AND EDT2.heure_debut <= EDT.heure_debut))) AS seance_rank";

if ($role === 'etudiant' || $role === 'étudiant') {
    // 1. Cherche si l'user est bien dans la table ETUDIANT
    $q1 = mysqli_query($conn, "SELECT id_etudiant FROM ETUDIANT WHERE id_user = $id_user");
    if (mysqli_num_rows($q1) == 0) {
        $debug = "Erreur : Ce compte n'a pas de profil dans la table ETUDIANT.";
    } else {
        $etu = mysqli_fetch_assoc($q1);
        $id_etu = $etu['id_etudiant'];
        
        // 2. Cherche si l'étudiant est inscrit à des cours
        $q2 = mysqli_query($conn, "SELECT id_cours FROM INSCRIPTION WHERE id_etudiant = $id_etu");
        if (mysqli_num_rows($q2) == 0) {
            $debug = "L'élève (ID: $id_etu) n'est inscrit à AUCUN cours. Va dans l'admin et inscris-le !";
        } else {
            $cours_ids = [];
            while($c = mysqli_fetch_assoc($q2)) { $cours_ids[] = $c['id_cours']; }
            $in_clause = implode(',', $cours_ids);
            
            // 3. Cherche si ces cours existent dans EMPLOI_DU_TEMPS
            $q3 = mysqli_query($conn, "SELECT * FROM EMPLOI_DU_TEMPS WHERE id_cours IN ($in_clause)");
            if (mysqli_num_rows($q3) == 0) {
                $debug = "L'élève est inscrit aux cours ($in_clause), mais ils n'ont AUCUNE séance programmée dans la BDD.";
            } else {
                // 4. On cherche pour la bonne semaine EN INCLUANT nb_seances_total et seance_rank !
                $sql_final = "SELECT EDT.*, C.titre, U.nom AS prof_nom, C.nb_seances_total, $rank_query 
                              FROM EMPLOI_DU_TEMPS EDT
                              JOIN COURS C ON EDT.id_cours = C.id_cours
                              LEFT JOIN ENSEIGNANT E ON C.id_enseignant = E.id_enseignant
                              LEFT JOIN USER U ON E.id_user = U.id_user
                              WHERE EDT.id_cours IN ($in_clause)
                              AND EDT.date_cours >= '$week_start' AND EDT.date_cours <= '$week_end'";
                $q_final = mysqli_query($conn, $sql_final);
                if (mysqli_num_rows($q_final) == 0) {
                    $debug = "Des séances existent, mais AUCUNE entre le $week_start et le $week_end. Navigue vers la bonne semaine !";
                } else {
                    while($r = mysqli_fetch_assoc($q_final)) { $schedule[] = $r; }
                    $debug = "Tout fonctionne parfaitement !";
                }
            }
        }
    }
} else {
    // Si c'est un enseignant (Avec le rang de séance aussi)
    $sql_prof = "SELECT EDT.*, C.titre, U.nom AS prof_nom, C.nb_seances_total, $rank_query 
                  FROM EMPLOI_DU_TEMPS EDT
                  JOIN COURS C ON EDT.id_cours = C.id_cours
                  JOIN ENSEIGNANT E ON C.id_enseignant = E.id_enseignant
                  JOIN USER U ON E.id_user = U.id_user
                  WHERE E.id_user = $id_user
                  AND EDT.date_cours >= '$week_start' AND EDT.date_cours <= '$week_end'";
    $res = mysqli_query($conn, $sql_prof);
    while($r = mysqli_fetch_assoc($res)) { $schedule[] = $r; }
    $debug = count($schedule) > 0 ? "Cours trouvés." : "Aucun cours pour ce prof cette semaine.";
}

// On renvoie les données ET le diagnostic
echo json_encode(["success" => true, "data" => $schedule, "debug" => $debug]);
?>
