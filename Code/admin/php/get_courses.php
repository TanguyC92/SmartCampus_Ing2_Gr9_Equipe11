<?php
// get_courses.php
require_once 'config.php';

$sql = "SELECT C.*, U.nom AS prof_nom, U.prenom AS prof_prenom 
        FROM COURS C 
        LEFT JOIN ENSEIGNANT E ON C.id_enseignant = E.id_enseignant 
        LEFT JOIN USER U ON E.id_user = U.id_user 
        ORDER BY C.id_cours DESC";

$result = mysqli_query($conn, $sql);
$courses = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $courses[] = $row;
    }
    echo json_encode(["success" => true, "courses" => $courses]);
} else {
    echo json_encode(["success" => false, "message" => mysqli_error($conn)]);
}
?>