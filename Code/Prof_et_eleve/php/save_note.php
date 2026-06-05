<?php
// Gérer le CORS et les requêtes OPTIONS (Preflight)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ÉTAPE 1 : On force l'affichage des erreurs...
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

ob_start(); // Capture les outputs parasites

require_once '../../Connexion/config.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!isset($conn) || !$conn) {
    ob_end_clean();
    echo json_encode(["success" => false, "message" => "Connexion DB indisponible"]);
    exit;
}

$notes = $data['notes'] ?? [];

if (empty($notes)) {
    ob_end_clean();
    echo json_encode(["success" => false, "message" => "Aucune note à enregistrer"]);
    exit;
}

$errors = [];
foreach ($notes as $n) {
    $id_inscription = (int)$n['id_inscription'];
    $note = isset($n['note']) ? (float)$n['note'] : 0.0; 
    $type_eval = mysqli_real_escape_string($conn, $n['type_evaluation']);
    $nom_eval = mysqli_real_escape_string($conn, $n['nom_evaluation'] ?? '');
    $date = date('Y-m-d');

    try {
        $check = mysqli_query($conn, "SELECT id_note FROM note WHERE id_inscription = $id_inscription AND type_evaluation = '$type_eval'");

        if ($check === false) {
            $errors[] = "Erreur SELECT: " . mysqli_error($conn);
            continue;
        }

        if (mysqli_num_rows($check) > 0) {
            $row = mysqli_fetch_assoc($check);
            $id_note = (int)$row['id_note'];
            $sql = "UPDATE note SET note = $note, date_evaluation = '$date', nom_evaluation = '$nom_eval' WHERE id_note = $id_note";
        } else {
            $sql = "INSERT INTO note (type_evaluation, note, coefficient, date_evaluation, statut_validation, id_inscription, nom_evaluation)
                    VALUES ('$type_eval', $note, 1, '$date', 'valide', $id_inscription, '$nom_eval')";
        }

        if (!mysqli_query($conn, $sql)) {
            $errors[] = "Erreur SQL (ID $id_inscription): " . mysqli_error($conn);
        }
    } catch (mysqli_sql_exception $e) {
        // On capture le crash SQL et on le stocke proprement !
        $errors[] = "Contrainte SQL (ID $id_inscription) : " . $e->getMessage();
    }
}

$parasiteOutput = ob_get_clean();

// On s'assure d'envoyer le bon header
header('Content-Type: application/json');

if (empty($errors)) {
    echo json_encode([
        "success" => true,
        "message" => "Notes enregistrées",
        "debug_parasite" => $parasiteOutput ?: null
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => implode(' | ', $errors),
        "debug_parasite" => $parasiteOutput ?: null
    ]);
}
?>