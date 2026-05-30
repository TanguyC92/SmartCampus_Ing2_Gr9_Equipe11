<?php
// login.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// On importe la variable $conn depuis config.php
require_once 'config.php';

$data = json_decode(file_get_contents("php://input"));

if (isset($data->email) && isset($data->password)) {
    // Si $conn n'existe pas, ça plantera ici. Mais on vient de le réparer !
    $email = mysqli_real_escape_string($conn, $data->email);
    $password = $data->password;

    $sql = "SELECT id_user, nom, prenom, role, password, statut_compte FROM `user` WHERE email = '$email'";
    $result = mysqli_query($conn, $sql);

    if (!$result) {
        echo json_encode(["success" => false, "message" => "CRASH SQL : " . mysqli_error($conn)]);
        exit;
    }

    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        
        if ($user['statut_compte'] !== 'actif') {
            echo json_encode(["success" => false, "message" => "Compte inactif."]);
            exit;
        }

        if (password_verify($password, $user['password'])) {
            unset($user['password']); 
            echo json_encode(["success" => true, "user" => $user]);
        } else {
            echo json_encode(["success" => false, "message" => "Mot de passe incorrect."]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Aucun compte trouvé avec cet email."]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Données incomplètes."]);
}
?>
