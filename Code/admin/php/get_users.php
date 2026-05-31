<?php
// get_users.php — retourne les utilisateurs selon rôle, ou TOUS pour l'admin
require_once '../../Connexion/config.php';

$role = $_GET['role'] ?? '';

if ($role === 'enseignant') {
    $sql = "SELECT E.id_enseignant AS id, U.id_user, U.nom, U.prenom, 'enseignant' AS role
            FROM ENSEIGNANT E
            JOIN `user` U ON E.id_user = U.id_user ORDER BY U.nom ASC";
} elseif ($role === 'etudiant') {
    $sql = "SELECT Et.id_etudiant AS id, U.id_user, U.nom, U.prenom, Et.matricule, 'etudiant' AS role
            FROM `etudiant` Et
            JOIN `user` U ON Et.id_user = U.id_user ORDER BY U.nom ASC";
} else {
    // Aucun rôle précisé → tous les utilisateurs (pour l'admin : contacts messagerie + stats)
    $sql = "SELECT U.id_user, U.nom, U.prenom, U.role,
                   CASE WHEN U.role='etudiant' THEN Et.matricule ELSE NULL END AS matricule
            FROM `user` U
            LEFT JOIN `etudiant` Et ON Et.id_user = U.id_user
            ORDER BY U.nom ASC";

    $result = mysqli_query($conn, $sql);
    if (!$result) {
        echo json_encode(["success" => false, "message" => mysqli_error($conn)]);
        exit;
    }

    $users = [];
    $counts = ['etudiant' => 0, 'enseignant' => 0, 'admin' => 0];
    while ($row = mysqli_fetch_assoc($result)) {
        $users[] = $row;
        $r = strtolower($row['role'] ?? '');
        if (isset($counts[$r])) $counts[$r]++;
    }

    echo json_encode([
        "success"     => true,
        "users"       => $users,
        "counts"      => $counts,
        "total"       => count($users)
    ]);
    exit;
}

// Pour rôle spécifique
$result = mysqli_query($conn, $sql);
$list = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $list[] = $row;
    }
    echo json_encode(["success" => true, "data" => $list, "count" => count($list)]);
} else {
    echo json_encode(["success" => false, "message" => mysqli_error($conn)]);
}
?>
