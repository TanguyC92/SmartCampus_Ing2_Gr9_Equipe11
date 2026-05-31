<?php
require_once '../../Connexion/config.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
$notes = $data['notes'] ?? [];

if (empty($notes)) {
    echo json_encode(["success" => false, "message" => "Aucune note à enregistrer"]);
    exit;
}

$errors = [];
foreach ($notes as $n) {
    $id_inscription = (int)$n['id_inscription'];
    $note = (float)$n['note'];
    $type_eval = mysqli_real_escape_string($conn, $n['type_evaluation']);
    $nom_eval = mysqli_real_escape_string($conn, $n['nom_evaluation'] ?? '');
    $date = date('Y-m-d');

    $check = mysqli_query($conn, "SELECT id_note FROM `note` WHERE id_inscription = $id_inscription AND type_evaluation = '$type_eval'");
    if ($check && mysqli_num_rows($check) > 0) {
        $row = mysqli_fetch_assoc($check);
        $id_note = $row['id_note'];
        $sql = "UPDATE `note` SET note = $note, date_evaluation = '$date', nom_evaluation = '$nom_eval' WHERE id_note = $id_note";
    } else {
        $sql = "INSERT INTO `note` (type_evaluation, note, coefficient, date_evaluation, statut_validation, id_inscription, nom_evaluation)
                VALUES ('$type_eval', $note, 1, '$date', 'valide', $id_inscription, '$nom_eval')";
    }

    if (!mysqli_query($conn, $sql)) {
        $errors[] = mysqli_error($conn);
    }
}

if (empty($errors)) {
    echo json_encode(["success" => true, "message" => "Notes enregistrées"]);
} else {
    echo json_encode(["success" => false, "message" => implode(' | ', $errors)]);
}
?>