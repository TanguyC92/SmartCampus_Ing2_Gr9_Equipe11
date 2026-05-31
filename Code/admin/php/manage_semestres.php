<?php
require_once '../../Connexion/config.php';

$method = $_SERVER['REQUEST_METHOD'];

// GET : liste des semestres + semestre actif
if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';

    if ($action === 'list') {
        $res = mysqli_query($conn, "SELECT * FROM semestre ORDER BY date_debut DESC");
        $semestres = [];
        while ($row = mysqli_fetch_assoc($res)) $semestres[] = $row;
        echo json_encode(["success" => true, "semestres" => $semestres]);

    } elseif ($action === 'actif') {
        $res = mysqli_query($conn, "SELECT * FROM semestre WHERE statut='actif' ORDER BY date_debut DESC LIMIT 1");
        $sem = $res ? mysqli_fetch_assoc($res) : null;
        echo json_encode(["success" => true, "semestre" => $sem]);
    }
    exit;
}

// POST
if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"));
    $action = $data->action ?? '';

    // --- Créer un nouveau semestre ---
    if ($action === 'create_semestre') {
        $nom = mysqli_real_escape_string($conn, $data->nom ?? '');
        $annee = mysqli_real_escape_string($conn, $data->annee_academique ?? '');
        $numero = mysqli_real_escape_string($conn, $data->numero ?? 'S1');
        $debut = mysqli_real_escape_string($conn, $data->date_debut ?? '');
        $fin = mysqli_real_escape_string($conn, $data->date_fin ?? '');

        if (!$nom || !$annee || !$debut || !$fin) {
            echo json_encode(["success" => false, "message" => "Données incomplètes"]);
            exit;
        }

        $sql = "INSERT INTO semestre (nom, annee_academique, numero, date_debut, date_fin, statut)
                VALUES ('$nom', '$annee', '$numero', '$debut', '$fin', 'actif')";
        if (mysqli_query($conn, $sql)) {
            $newId = mysqli_insert_id($conn);
            echo json_encode(["success" => true, "message" => "Semestre créé !", "id_semestre" => $newId]);
        } else {
            echo json_encode(["success" => false, "message" => mysqli_error($conn)]);
        }
        exit;
    }

    // --- Clôturer un semestre (archive les notes) ---
    if ($action === 'cloturer') {
        $id_semestre = (int)($data->id_semestre ?? 0);
        $id_user_admin = (int)($data->id_user ?? 0);

        if (!$id_semestre) {
            echo json_encode(["success" => false, "message" => "ID semestre manquant"]);
            exit;
        }

        // Vérifier que le semestre est bien actif
        $chk = mysqli_query($conn, "SELECT * FROM semestre WHERE id_semestre=$id_semestre AND statut='actif'");
        if (!$chk || mysqli_num_rows($chk) === 0) {
            echo json_encode(["success" => false, "message" => "Semestre introuvable ou déjà clôturé"]);
            exit;
        }

        mysqli_begin_transaction($conn);
        try {
            // Récupérer tous les cours du semestre
            $sqlCours = "SELECT * FROM cours WHERE id_semestre=$id_semestre";
            $resCours = mysqli_query($conn, $sqlCours);
            $archived = 0;

            while ($cours = mysqli_fetch_assoc($resCours)) {
                $idCours = (int)$cours['id_cours'];
                $codeCours = mysqli_real_escape_string($conn, $cours['code_cours']);
                $titreCours = mysqli_real_escape_string($conn, $cours['titre']);
                $coeffCours = (float)$cours['coefficient'];

                // Récupérer les inscriptions pour ce cours
                $sqlInscriptions = "SELECT I.id_inscription, I.id_etudiant FROM inscription I WHERE I.id_cours=$idCours";
                $resIns = mysqli_query($conn, $sqlInscriptions);

                while ($ins = mysqli_fetch_assoc($resIns)) {
                    $idInscription = (int)$ins['id_inscription'];
                    $idEtudiant = (int)$ins['id_etudiant'];

                    // Vérifier si déjà archivé
                    $chkArch = mysqli_query($conn, "SELECT id_archive FROM note_archive WHERE id_semestre=$id_semestre AND id_etudiant=$idEtudiant AND id_cours=$idCours LIMIT 1");
                    if ($chkArch && mysqli_num_rows($chkArch) > 0) continue;

                    // Récupérer les notes
                    $sqlNotes = "SELECT type_evaluation, note FROM note WHERE id_inscription=$idInscription";
                    $resNotes = mysqli_query($conn, $sqlNotes);
                    $notes = [];
                    while ($n = mysqli_fetch_assoc($resNotes)) $notes[] = $n;

                    if (empty($notes)) continue; // Pas de notes, pas d'archive

                    // Calculer moyenne du cours pour cet étudiant
                    $sqlConfig = "SELECT * FROM evaluation_config WHERE id_cours=$idCours";
                    $resConfig = mysqli_query($conn, $sqlConfig);
                    $configs = [];
                    while ($cfg = mysqli_fetch_assoc($resConfig)) {
                        $cfg['pourcentages'] = json_decode($cfg['pourcentages'] ?? '[]', true);
                        $configs[] = $cfg;
                    }

                    $moyenneCours = null;
                    $totalCoeff = 0;
                    $sommePond = 0;
                    foreach ($configs as $cfg) {
                        $catKey = $cfg['categorie'];
                        $coeffCat = (float)$cfg['coefficient_categorie'];
                        $nbNotes = (int)$cfg['nombre_notes'];
                        $pcts = $cfg['pourcentages'];
                        $sommeCat = 0; $totalPct = 0;
                        for ($i = 1; $i <= $nbNotes; $i++) {
                            $typeEval = $catKey . '_' . $i;
                            $pct = isset($pcts[$i-1]) ? (float)$pcts[$i-1] : 0;
                            foreach ($notes as $n) {
                                if ($n['type_evaluation'] === $typeEval) {
                                    $sommeCat += (float)$n['note'] * ($pct / 100);
                                    $totalPct += $pct;
                                    break;
                                }
                            }
                        }
                        if ($totalPct > 0) {
                            $moyCat = ($sommeCat / $totalPct) * 100;
                            $sommePond += $moyCat * $coeffCat;
                            $totalCoeff += $coeffCat;
                        }
                    }
                    if ($totalCoeff > 0) $moyenneCours = round($sommePond / $totalCoeff, 2);

                    // Archiver chaque note
                    foreach ($notes as $n) {
                        $typeEval = mysqli_real_escape_string($conn, $n['type_evaluation']);
                        $note = (float)$n['note'];
                        $moyC = $moyenneCours !== null ? $moyenneCours : 'NULL';
                        $sqlInsert = "INSERT INTO note_archive (id_semestre, id_etudiant, id_cours, code_cours, titre_cours, coefficient_cours, type_evaluation, note, moyenne_cours)
                                      VALUES ($id_semestre, $idEtudiant, $idCours, '$codeCours', '$titreCours', $coeffCours, '$typeEval', $note, $moyC)";
                        mysqli_query($conn, $sqlInsert);
                        $archived++;
                    }
                }
            }

            // Calculer et stocker la moyenne générale de chaque étudiant pour ce semestre
            $sqlEtudiants = "SELECT DISTINCT id_etudiant FROM note_archive WHERE id_semestre=$id_semestre";
            $resEtu = mysqli_query($conn, $sqlEtudiants);
            while ($etu = mysqli_fetch_assoc($resEtu)) {
                $idEtu = (int)$etu['id_etudiant'];
                // Calculer moyenne pondérée générale
                $sqlMoyGen = "SELECT DISTINCT na.id_cours, na.moyenne_cours, c.coefficient
                              FROM note_archive na
                              JOIN cours c ON c.id_cours = na.id_cours
                              WHERE na.id_semestre=$id_semestre AND na.id_etudiant=$idEtu AND na.moyenne_cours IS NOT NULL";
                $resMG = mysqli_query($conn, $sqlMoyGen);
                $sommeGen = 0; $totalCoeffGen = 0;
                while ($mg = mysqli_fetch_assoc($resMG)) {
                    $sommeGen += (float)$mg['moyenne_cours'] * (float)$mg['coefficient'];
                    $totalCoeffGen += (float)$mg['coefficient'];
                }
                if ($totalCoeffGen > 0) {
                    $moyGen = round($sommeGen / $totalCoeffGen, 2);
                    mysqli_query($conn, "UPDATE note_archive SET moyenne_generale=$moyGen WHERE id_semestre=$id_semestre AND id_etudiant=$idEtu");
                }
            }

            // Marquer le semestre comme clôturé
            $now = date('Y-m-d H:i:s');
            $adminId = $id_user_admin > 0 ? $id_user_admin : 'NULL';
            mysqli_query($conn, "UPDATE semestre SET statut='cloture', date_cloture='$now', cloture_par=$adminId WHERE id_semestre=$id_semestre");

            mysqli_commit($conn);
            echo json_encode(["success" => true, "message" => "Semestre clôturé ! $archived notes archivées.", "archived" => $archived]);

        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo json_encode(["success" => false, "message" => "Erreur : " . $e->getMessage()]);
        }
        exit;
    }

    // --- Mettre à jour le semestre d'un cours ---
    if ($action === 'assign_cours_semestre') {
        $id_cours = (int)($data->id_cours ?? 0);
        $id_semestre = (int)($data->id_semestre ?? 0);
        if (!$id_cours || !$id_semestre) {
            echo json_encode(["success" => false, "message" => "Données manquantes"]);
            exit;
        }
        mysqli_query($conn, "UPDATE cours SET id_semestre=$id_semestre WHERE id_cours=$id_cours");
        echo json_encode(["success" => true, "message" => "Cours assigné au semestre"]);
        exit;
    }

    echo json_encode(["success" => false, "message" => "Action inconnue"]);
}
?>
