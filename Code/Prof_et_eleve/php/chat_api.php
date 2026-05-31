<?php
// chat_api.php
ini_set('display_errors', 0);
require_once '../../Connexion/config.php';

$method = $_SERVER['REQUEST_METHOD'];

// ─── GET CONTACTS ──────────────────────────────────────────────
if ($method === 'GET' && ($_GET['action'] ?? '') === 'get_contacts') {
    $id_user = (int)$_GET['id_user'];
    $role    = strtolower(trim($_GET['role'] ?? ''));
    $contacts = [];

    // Non-lus = messages reçus dont date_lecture IS NULL
    $extra_fields = "
        (SELECT COUNT(*) FROM MESSAGERIE M
         WHERE M.id_expediteur = U.id_user
           AND M.id_destinataire = $id_user
           AND M.date_lecture IS NULL) AS unread_count,
        (SELECT MAX(date_envoi) FROM MESSAGERIE M
         WHERE (M.id_expediteur = U.id_user AND M.id_destinataire = $id_user)
            OR (M.id_expediteur = $id_user AND M.id_destinataire = U.id_user)) AS last_activity,
        (SELECT contenu FROM MESSAGERIE M
         WHERE (M.id_expediteur = U.id_user AND M.id_destinataire = $id_user)
            OR (M.id_expediteur = $id_user AND M.id_destinataire = U.id_user)
         ORDER BY date_envoi DESC LIMIT 1) AS last_message
    ";

    if ($role === 'etudiant' || $role === 'étudiant') {
        $sql = "SELECT DISTINCT U.id_user, U.nom, U.prenom, $extra_fields
                FROM USER U
                JOIN ENSEIGNANT E  ON U.id_user      = E.id_user
                JOIN COURS C       ON E.id_enseignant = C.id_enseignant
                JOIN INSCRIPTION I ON C.id_cours      = I.id_cours
                JOIN ETUDIANT Et   ON I.id_etudiant   = Et.id_etudiant
                WHERE Et.id_user = $id_user
                ORDER BY last_activity DESC, U.nom ASC";
    } elseif ($role === 'enseignant') {
        $sql = "SELECT DISTINCT U.id_user, U.nom, U.prenom, $extra_fields
                FROM USER U
                JOIN ETUDIANT Et   ON U.id_user       = Et.id_user
                JOIN INSCRIPTION I ON Et.id_etudiant  = I.id_etudiant
                JOIN COURS C       ON I.id_cours       = C.id_cours
                JOIN ENSEIGNANT E  ON C.id_enseignant  = E.id_enseignant
                WHERE E.id_user = $id_user
                ORDER BY last_activity DESC, U.nom ASC";
    } else {
        echo json_encode(["success" => false, "message" => "Rôle non autorisé"]); exit;
    }

    $res = mysqli_query($conn, $sql);
    while ($r = mysqli_fetch_assoc($res)) { $contacts[] = $r; }
    echo json_encode(["success" => true, "data" => $contacts]);
}

// ─── GET MESSAGES ──────────────────────────────────────────────
elseif ($method === 'GET' && ($_GET['action'] ?? '') === 'get_messages') {
    $me    = (int)$_GET['me'];
    $other = (int)$_GET['other'];

    // Marquer comme lus (date_lecture = maintenant)
    mysqli_query($conn, "UPDATE MESSAGERIE
                         SET date_lecture = CURRENT_TIMESTAMP
                         WHERE id_destinataire = $me
                           AND id_expediteur   = $other
                           AND date_lecture IS NULL");

    $sql = "SELECT * FROM MESSAGERIE
            WHERE (id_expediteur = $me AND id_destinataire = $other)
               OR (id_expediteur = $other AND id_destinataire = $me)
            ORDER BY date_envoi ASC";

    $res = mysqli_query($conn, $sql);
    $messages = [];
    while ($r = mysqli_fetch_assoc($res)) { $messages[] = $r; }
    echo json_encode(["success" => true, "data" => $messages]);
}

// ─── GET UNREAD COUNT (pour les notifs) ────────────────────────
elseif ($method === 'GET' && ($_GET['action'] ?? '') === 'get_unread') {
    $id_user = (int)$_GET['id_user'];

    $sql = "SELECT M.id_expediteur AS id_user,
                   U.nom, U.prenom,
                   COUNT(*) AS unread_count,
                   MAX(M.contenu) AS last_message,
                   MAX(M.date_envoi) AS last_date
            FROM MESSAGERIE M
            JOIN USER U ON M.id_expediteur = U.id_user
            WHERE M.id_destinataire = $id_user
              AND M.date_lecture IS NULL
            GROUP BY M.id_expediteur, U.nom, U.prenom
            ORDER BY last_date DESC";

    $res = mysqli_query($conn, $sql);
    $convs = [];
    while ($r = mysqli_fetch_assoc($res)) { $convs[] = $r; }
    echo json_encode(["success" => true, "data" => $convs]);
}

// ─── SEND MESSAGE ──────────────────────────────────────────────
elseif ($method === 'POST') {
    $data    = json_decode(file_get_contents("php://input"));
    $me      = (int)$data->me;
    $other   = (int)$data->other;
    $contenu = mysqli_real_escape_string($conn, $data->contenu);

    if (!empty($contenu)) {
        $sql = "INSERT INTO MESSAGERIE (id_expediteur, id_destinataire, contenu)
                VALUES ($me, $other, '$contenu')";
        if (mysqli_query($conn, $sql)) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "message" => mysqli_error($conn)]);
        }
    }
}
?>
