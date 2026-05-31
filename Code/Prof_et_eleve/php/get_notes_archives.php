<?php
require_once '../../Connexion/config.php';

$id_user = isset($_GET['id_user']) ? (int)$_GET['id_user'] : 0;
if ($id_user === 0) {
    echo json_encode(["success" => false, "message" => "ID manquant"]);
    exit;
}

// Récupérer l'id_etudiant depuis id_user
$sqlEtu = "SELECT id_etudiant FROM etudiant WHERE id_user = $id_user LIMIT 1";
$resEtu = mysqli_query($conn, $sqlEtu);
if (!$resEtu || mysqli_num_rows($resEtu) === 0) {
    echo json_encode(["success" => false, "message" => "Étudiant introuvable"]);
    exit;
}
$etudiant = mysqli_fetch_assoc($resEtu);
$id_etudiant = (int)$etudiant['id_etudiant'];

// Récupérer tous les semestres clôturés pour cet étudiant
$sqlSemestres = "SELECT DISTINCT S.id_semestre, S.nom, S.annee_academique, S.numero,
                        S.date_debut, S.date_fin, S.statut, S.date_cloture
                 FROM semestre S
                 JOIN note_archive NA ON NA.id_semestre = S.id_semestre
                 WHERE NA.id_etudiant = $id_etudiant
                 ORDER BY S.date_debut DESC";
$resSem = mysqli_query($conn, $sqlSemestres);
$semestres = [];

if ($resSem) {
    while ($sem = mysqli_fetch_assoc($resSem)) {
        $idSem = (int)$sem['id_semestre'];

        // Récupérer les cours archivés de ce semestre pour cet étudiant
        $sqlCours = "SELECT DISTINCT id_cours, code_cours, titre_cours, coefficient_cours,
                            moyenne_cours, moyenne_generale
                     FROM note_archive
                     WHERE id_semestre = $idSem AND id_etudiant = $id_etudiant
                     ORDER BY titre_cours ASC";
        $resCours = mysqli_query($conn, $sqlCours);
        $cours = [];

        if ($resCours) {
            while ($c = mysqli_fetch_assoc($resCours)) {
                $idCours = (int)$c['id_cours'];
                // Récupérer les notes de ce cours
                $sqlNotes = "SELECT type_evaluation, note FROM note_archive
                             WHERE id_semestre = $idSem AND id_etudiant = $id_etudiant AND id_cours = $idCours
                             ORDER BY type_evaluation ASC";
                $resNotes = mysqli_query($conn, $sqlNotes);
                $notes = [];
                if ($resNotes) {
                    while ($n = mysqli_fetch_assoc($resNotes)) $notes[] = $n;
                }
                $cours[] = [
                    'id_cours' => $idCours,
                    'code_cours' => $c['code_cours'],
                    'titre_cours' => $c['titre_cours'],
                    'coefficient_cours' => $c['coefficient_cours'],
                    'moyenne_cours' => $c['moyenne_cours'],
                    'notes' => $notes
                ];
            }
        }

        // Moyenne générale du semestre
        $moyGen = null;
        if (!empty($cours)) {
            $r = mysqli_query($conn, "SELECT DISTINCT moyenne_generale FROM note_archive
                                      WHERE id_semestre=$idSem AND id_etudiant=$id_etudiant AND moyenne_generale IS NOT NULL LIMIT 1");
            if ($r) {
                $row = mysqli_fetch_assoc($r);
                $moyGen = $row ? $row['moyenne_generale'] : null;
            }
        }

        $semestres[] = [
            'id_semestre' => $idSem,
            'nom' => $sem['nom'],
            'annee_academique' => $sem['annee_academique'],
            'numero' => $sem['numero'],
            'date_debut' => $sem['date_debut'],
            'date_fin' => $sem['date_fin'],
            'statut' => $sem['statut'],
            'date_cloture' => $sem['date_cloture'],
            'cours' => $cours,
            'moyenne_generale' => $moyGen
        ];
    }
}

echo json_encode(["success" => true, "semestres" => $semestres, "id_etudiant" => $id_etudiant]);
?>
