<?php
require_once '../../Connexion/config.php';

$id_user = isset($_GET['id_user']) ? (int)$_GET['id_user'] : 0;
if ($id_user === 0) {
    echo json_encode(["success" => false, "message" => "ID manquant"]);
    exit;
}

// Récupérer les cours de l'élève avec les notes et la config d'évaluation
$sql = "SELECT C.id_cours, C.titre, C.coefficient AS coeff_cours,
               I.id_inscription,
               N.id_note, N.type_evaluation, N.note,
               EC.coefficient_categorie, EC.nombre_notes, EC.pourcentages, EC.categorie
        FROM `inscription` I
        JOIN `etudiant` ET ON I.id_etudiant = ET.id_etudiant
        JOIN `cours` C ON I.id_cours = C.id_cours
        LEFT JOIN `note` N ON N.id_inscription = I.id_inscription
        LEFT JOIN evaluation_config EC ON EC.id_cours = C.id_cours
            AND EC.categorie = SUBSTRING_INDEX(N.type_evaluation, '_', -0)
        WHERE ET.id_user = $id_user
        ORDER BY C.titre ASC, N.type_evaluation ASC";

// Requête simplifiée : on récupère notes et config séparément
$sqlCours = "SELECT C.id_cours, C.titre, C.code_cours, C.coefficient AS coeff_cours, I.id_inscription
             FROM `inscription` I
             JOIN `etudiant` ET ON I.id_etudiant = ET.id_etudiant
             JOIN `cours` C ON I.id_cours = C.id_cours
             WHERE ET.id_user = $id_user
             ORDER BY C.titre ASC";

$resCours = mysqli_query($conn, $sqlCours);
$result = [];

if ($resCours) {
    while ($cours = mysqli_fetch_assoc($resCours)) {
        $idInscription = $cours['id_inscription'];
        $idCours = $cours['id_cours'];

        // Notes de l'élève pour ce cours
        $sqlNotes = "SELECT N.type_evaluation, N.note FROM `note` N WHERE N.id_inscription = $idInscription ORDER BY N.type_evaluation ASC";
        $resNotes = mysqli_query($conn, $sqlNotes);
        $notes = [];
        if ($resNotes) {
            while ($row = mysqli_fetch_assoc($resNotes)) $notes[] = $row;
        }

        // Config d'évaluation du cours
        $sqlConfig = "SELECT * FROM evaluation_config WHERE id_cours = $idCours";
        $resConfig = mysqli_query($conn, $sqlConfig);
        $configs = [];
        if ($resConfig) {
            while ($row = mysqli_fetch_assoc($resConfig)) {
                $row['pourcentages'] = json_decode($row['pourcentages'] ?? '[]', true);
                $configs[] = $row;
            }
        }

        // Stats globales pour chaque type_evaluation (toute la classe)
        $sqlStats = "SELECT N2.type_evaluation,
                        AVG(N2.note) AS moyenne_classe,
                        MIN(N2.note) AS note_min,
                        MAX(N2.note) AS note_max
                     FROM `note` N2
                     JOIN `inscription` I2 ON N2.id_inscription = I2.id_inscription
                     WHERE I2.id_cours = $idCours
                     GROUP BY N2.type_evaluation";
        $resStats = mysqli_query($conn, $sqlStats);
        $stats = [];
        if ($resStats) {
            while ($row = mysqli_fetch_assoc($resStats)) $stats[$row['type_evaluation']] = $row;
        }

        // Calcul de la moyenne de l'élève pour ce cours
        $moyenneCours = null;
        $totalCoeffCat = 0;
        $sommePonderee = 0;
        foreach ($configs as $config) {
            $catKey = $config['categorie'];
            $coeffCat = (float)$config['coefficient_categorie'];
            $nbNotes = (int)$config['nombre_notes'];
            $pcts = $config['pourcentages'];

            $moyenneCat = null;
            $sommeCat = 0;
            $totalPctCat = 0;
            for ($i = 1; $i <= $nbNotes; $i++) {
                $typeEval = $catKey . '_' . $i;
                $pct = isset($pcts[$i-1]) ? (float)$pcts[$i-1] : 0;
                $noteEtudiant = null;
                foreach ($notes as $n) {
                    if ($n['type_evaluation'] === $typeEval) { $noteEtudiant = (float)$n['note']; break; }
                }
                if ($noteEtudiant !== null) {
                    $sommeCat += $noteEtudiant * ($pct / 100);
                    $totalPctCat += $pct;
                }
            }
            if ($totalPctCat > 0) {
                $moyenneCat = ($sommeCat / $totalPctCat) * 100;
                $sommePonderee += $moyenneCat * $coeffCat;
                $totalCoeffCat += $coeffCat;
            }
        }
        if ($totalCoeffCat > 0) $moyenneCours = $sommePonderee / $totalCoeffCat;

        // Stats moyennes par matière (toute la classe)
        $sqlMoyClasse = "SELECT AVG(sub.moy) as moy_classe, MIN(sub.moy) as moy_min, MAX(sub.moy) as moy_max
            FROM (
                SELECT I3.id_inscription, AVG(N3.note) as moy
                FROM `note` N3
                JOIN `inscription` I3 ON N3.id_inscription = I3.id_inscription
                WHERE I3.id_cours = $idCours
                GROUP BY I3.id_inscription
            ) sub";
        $resMoyClasse = mysqli_query($conn, $sqlMoyClasse);
        $moyClasse = $resMoyClasse ? mysqli_fetch_assoc($resMoyClasse) : [];

        $result[] = [
            'id_cours' => $idCours,
            'titre' => $cours['titre'],
            'code_cours' => $cours['code_cours'],
            'coeff_cours' => $cours['coeff_cours'],
            'notes' => $notes,
            'configs' => $configs,
            'stats' => $stats,
            'moyenne_cours' => $moyenneCours !== null ? round($moyenneCours, 2) : null,
            'stats_moyennes_cours' => $moyClasse
        ];
    }
}

// Calcul moyenne générale
$moyenneGenerale = null;
$totalCoeffGen = 0;
$sommeGen = 0;
foreach ($result as $cours) {
    if ($cours['moyenne_cours'] !== null) {
        $sommeGen += $cours['moyenne_cours'] * (float)$cours['coeff_cours'];
        $totalCoeffGen += (float)$cours['coeff_cours'];
    }
}
if ($totalCoeffGen > 0) $moyenneGenerale = round($sommeGen / $totalCoeffGen, 2);

echo json_encode(["success" => true, "cours" => $result, "moyenne_generale" => $moyenneGenerale]);
?>