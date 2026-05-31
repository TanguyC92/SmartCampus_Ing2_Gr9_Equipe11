<?php
// notifications_api.php
ini_set('display_errors', 0);
require_once '../../Connexion/config.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ─── GET : récupérer les notifications d'un user ──────────────
if ($method === 'GET' && $action === 'get') {
    $id_user = (int)($_GET['id_user'] ?? 0);
    if (!$id_user) { echo json_encode(['success' => false, 'message' => 'ID manquant']); exit; }

    $sql = "SELECT id_notification, type, titre, corps, dedup_id, lue, date_creation
            FROM notification
            WHERE id_user = $id_user
            ORDER BY date_creation DESC
            LIMIT 60";

    $res = mysqli_query($conn, $sql);
    $notifs = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $r['lue'] = (bool)$r['lue'];
        $notifs[] = $r;
    }
    echo json_encode(['success' => true, 'data' => $notifs]);

// ─── GET : récupérer tous les dedup_id définitivement vus ─────
// Combine notification active + notification_vue (historique)
} elseif ($method === 'GET' && $action === 'get_seen') {
    $id_user = (int)($_GET['id_user'] ?? 0);
    if (!$id_user) { echo json_encode(['success' => true, 'seen' => []]); exit; }

    // Les notifs encore présentes
    $res1 = mysqli_query($conn,
        "SELECT dedup_id FROM notification WHERE id_user = $id_user AND dedup_id IS NOT NULL");
    // Les notifs supprimées mais mémorisées
    $res2 = mysqli_query($conn,
        "SELECT dedup_id FROM notification_vue WHERE id_user = $id_user");

    $seen = [];
    while ($r = mysqli_fetch_assoc($res1)) { $seen[] = $r['dedup_id']; }
    while ($r = mysqli_fetch_assoc($res2)) { $seen[] = $r['dedup_id']; }
    echo json_encode(['success' => true, 'seen' => array_values(array_unique($seen))]);

// ─── POST : ajouter une notification ─────────────────────────
} elseif ($method === 'POST' && $action === 'add') {
    $data    = json_decode(file_get_contents('php://input'), true);
    $id_user = (int)($data['id_user'] ?? 0);
    $type    = mysqli_real_escape_string($conn, $data['type'] ?? '');
    $titre   = mysqli_real_escape_string($conn, $data['titre'] ?? '');
    $corps   = mysqli_real_escape_string($conn, $data['corps'] ?? '');
    $dedup   = isset($data['dedup_id']) ? mysqli_real_escape_string($conn, $data['dedup_id']) : null;

    if (!$id_user || !$type || !$titre) {
        echo json_encode(['success' => false, 'message' => 'Paramètres manquants']); exit;
    }

    $dedup_sql = $dedup ? "'$dedup'" : 'NULL';
    // INSERT IGNORE grâce à l'index UNIQUE (id_user, dedup_id)
    $sql = "INSERT IGNORE INTO notification (id_user, type, titre, corps, dedup_id)
            VALUES ($id_user, '$type', '$titre', '$corps', $dedup_sql)";
    $ok       = mysqli_query($conn, $sql);
    $inserted = mysqli_affected_rows($conn);
    echo json_encode(['success' => (bool)$ok, 'inserted' => (int)$inserted]);

// ─── POST : supprimer une notification (et mémoriser le dedup) ─
} elseif ($method === 'POST' && $action === 'delete') {
    $data            = json_decode(file_get_contents('php://input'), true);
    $id_user         = (int)($data['id_user'] ?? 0);
    $id_notification = (int)($data['id_notification'] ?? 0);
    if (!$id_user || !$id_notification) {
        echo json_encode(['success' => false, 'message' => 'Paramètres manquants']); exit;
    }

    // Récupérer le dedup_id avant suppression
    $res = mysqli_query($conn,
        "SELECT dedup_id FROM notification WHERE id_notification = $id_notification AND id_user = $id_user");
    $row = mysqli_fetch_assoc($res);

    // Mémoriser dans notification_vue
    if ($row && $row['dedup_id']) {
        $d = mysqli_real_escape_string($conn, $row['dedup_id']);
        mysqli_query($conn,
            "INSERT IGNORE INTO notification_vue (id_user, dedup_id) VALUES ($id_user, '$d')");
    }

    mysqli_query($conn, "DELETE FROM notification WHERE id_notification = $id_notification AND id_user = $id_user");
    echo json_encode(['success' => true]);

// ─── POST : tout effacer pour un user (et mémoriser) ──────────
} elseif ($method === 'POST' && $action === 'clear_all') {
    $data    = json_decode(file_get_contents('php://input'), true);
    $id_user = (int)($data['id_user'] ?? 0);
    if (!$id_user) { echo json_encode(['success' => false, 'message' => 'ID manquant']); exit; }

    // Mémoriser tous les dedup_id avant suppression
    $res = mysqli_query($conn,
        "SELECT dedup_id FROM notification WHERE id_user = $id_user AND dedup_id IS NOT NULL");
    while ($r = mysqli_fetch_assoc($res)) {
        $d = mysqli_real_escape_string($conn, $r['dedup_id']);
        mysqli_query($conn,
            "INSERT IGNORE INTO notification_vue (id_user, dedup_id) VALUES ($id_user, '$d')");
    }

    mysqli_query($conn, "DELETE FROM notification WHERE id_user = $id_user");
    echo json_encode(['success' => true]);

// ─── POST : effacer par types (et mémoriser) ──────────────────
} elseif ($method === 'POST' && $action === 'clear_types') {
    $data    = json_decode(file_get_contents('php://input'), true);
    $id_user = (int)($data['id_user'] ?? 0);
    $types   = $data['types'] ?? [];
    if (!$id_user || !is_array($types) || count($types) === 0) {
        echo json_encode(['success' => false, 'message' => 'Paramètres manquants']); exit;
    }
    $escaped = array_map(fn($t) => "'" . mysqli_real_escape_string($conn, $t) . "'", $types);
    $in      = implode(',', $escaped);

    // Mémoriser les dedup_id avant suppression
    $res = mysqli_query($conn,
        "SELECT dedup_id FROM notification WHERE id_user = $id_user AND type IN ($in) AND dedup_id IS NOT NULL");
    while ($r = mysqli_fetch_assoc($res)) {
        $d = mysqli_real_escape_string($conn, $r['dedup_id']);
        mysqli_query($conn,
            "INSERT IGNORE INTO notification_vue (id_user, dedup_id) VALUES ($id_user, '$d')");
    }

    mysqli_query($conn, "DELETE FROM notification WHERE id_user = $id_user AND type IN ($in)");
    echo json_encode(['success' => true]);

} else {
    echo json_encode(['success' => false, 'message' => 'Action inconnue']);
}
?>
