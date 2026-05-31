<?php
require_once '../../Connexion/config.php';

// Gérer les requêtes OPTIONS (preflight CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$rawInput = file_get_contents('php://input');
if (empty($rawInput)) {
    echo json_encode(["success" => false, "message" => "Corps de requête vide"]);
    exit;
}

$data = json_decode($rawInput, true);
if ($data === null) {
    echo json_encode(["success" => false, "message" => "JSON invalide : " . json_last_error_msg()]);
    exit;
}

$id_cours = isset($data['id_cours']) ? (int)$data['id_cours'] : 0;
$categories = isset($data['categories']) ? $data['categories'] : [];

if ($id_cours === 0) {
    echo json_encode(["success" => false, "message" => "ID cours manquant ou invalide"]);
    exit;
}
if (empty($categories)) {
    echo json_encode(["success" => false, "message" => "Aucune catégorie fournie"]);
    exit;
}

// Vérifier que les catégories sont uniques
$catKeys = array_column($categories, 'categorie');
if (count($catKeys) !== count(array_unique($catKeys))) {
    echo json_encode(["success" => false, "message" => "Catégories en double détectées"]);
    exit;
}

// Valider les catégories autorisées
$allowed = ['controle_continu', 'examen', 'projet'];
foreach ($categories as $cat) {
    if (!in_array($cat['categorie'], $allowed)) {
        echo json_encode(["success" => false, "message" => "Catégorie invalide : " . $cat['categorie']]);
        exit;
    }
}

// Supprimer l'ancienne config pour ce cours
$deleteResult = mysqli_query($conn, "DELETE FROM evaluation_config WHERE id_cours = $id_cours");
if ($deleteResult === false) {
    echo json_encode(["success" => false, "message" => "Erreur suppression : " . mysqli_error($conn)]);
    exit;
}

// Insérer la nouvelle config
foreach ($categories as $cat) {
    $categorie = mysqli_real_escape_string($conn, $cat['categorie']);
    $coeff = (float)($cat['coefficient_categorie'] ?? 1);
    $nb = (int)($cat['nombre_notes'] ?? 1);
    $pcts = isset($cat['pourcentages']) ? $cat['pourcentages'] : [];
    $pcts_json = mysqli_real_escape_string($conn, json_encode($pcts));

    $sql = "INSERT INTO evaluation_config (id_cours, categorie, coefficient_categorie, nombre_notes, pourcentages)
            VALUES ($id_cours, '$categorie', $coeff, $nb, '$pcts_json')";

    if (!mysqli_query($conn, $sql)) {
        echo json_encode(["success" => false, "message" => "Erreur insertion : " . mysqli_error($conn)]);
        exit;
    }
}

echo json_encode(["success" => true, "message" => "Configuration enregistrée avec succès"]);
?>