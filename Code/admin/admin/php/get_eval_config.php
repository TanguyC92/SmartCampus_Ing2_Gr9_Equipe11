<?php
require_once '../../Connexion/config.php';

$id_cours = isset($_GET['id_cours']) ? (int)$_GET['id_cours'] : 0;
if ($id_cours === 0) {
    echo json_encode(["success" => false, "message" => "ID cours manquant"]);
    exit;
}

$sql = "SELECT * FROM evaluation_config WHERE id_cours = $id_cours ORDER BY id_config ASC";
$result = mysqli_query($conn, $sql);

if ($result === false) {
    echo json_encode(["success" => false, "message" => "Erreur SQL : " . mysqli_error($conn)]);
    exit;
}

$configs = [];
while ($row = mysqli_fetch_assoc($result)) {
    // S'assurer que pourcentages est bien un tableau JSON valide
    if (is_string($row['pourcentages'])) {
        $decoded = json_decode($row['pourcentages'], true);
        $row['pourcentages'] = is_array($decoded) ? $decoded : [];
    } else {
        $row['pourcentages'] = [];
    }
    $configs[] = $row;
}

echo json_encode(["success" => true, "configs" => $configs]);
?>