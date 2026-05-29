<?php
// register.php
require_once 'config.php';

// Récupérer les données envoyées en JSON par le fetch Javascript
$data = json_decode(file_get_contents("php://input"));

// Vérifier que tous les champs sont bien reçus
if (isset($data->nom) && isset($data->prenom) && isset($data->email) && isset($data->password) && isset($data->role)) {

    // Sécurisation basique des chaînes de caractères
    $nom = mysqli_real_escape_string($conn, $data->nom);
    $prenom = mysqli_real_escape_string($conn, $data->prenom);
    $email = mysqli_real_escape_string($conn, $data->email);
    $role = mysqli_real_escape_string($conn, $data->role);

    // Hachage du mot de passe (règle de sécurité incontournable !)
    $hashed_password = password_hash($data->password, PASSWORD_DEFAULT);

    // 1. Vérifier si l'email existe déjà dans la base
    $check_email = "SELECT id_user FROM USER WHERE email = '$email'";
    $result = mysqli_query($conn, $check_email);

    if (mysqli_num_rows($result) > 0) {
        echo json_encode(["success" => false, "message" => "Cet email est déjà utilisé."]);
    } else {
        // 2. Insérer le nouvel utilisateur dans la table USER
        $sql = "INSERT INTO USER (nom, prenom, email, password, role) 
                VALUES ('$nom', '$prenom', '$email', '$hashed_password', '$role')";

        if (mysqli_query($conn, $sql)) {
            // Récupérer l'ID généré pour cet utilisateur
            $new_user_id = mysqli_insert_id($conn); 

            // 3. Logique métier : Insérer aussi dans la table spécifique selon le rôle
            if ($role === 'etudiant') {
                // Génération d'un faux matricule pour l'exemple (ex: E202615)
                $matricule = "E" . date("Y") . $new_user_id;
                mysqli_query($conn, "INSERT INTO ETUDIANT (matricule, id_user) VALUES ('$matricule', $new_user_id)");
            } elseif ($role === 'enseignant') {
                mysqli_query($conn, "INSERT INTO ENSEIGNANT (id_user) VALUES ($new_user_id)");
            }

            echo json_encode(["success" => true, "message" => "Compte créé avec succès !"]);
        } else {
            echo json_encode(["success" => false, "message" => "Erreur lors de la création : " . mysqli_error($conn)]);
        }
    }
} else {
    echo json_encode(["success" => false, "message" => "Veuillez remplir tous les champs."]);
}
?>
