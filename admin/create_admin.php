<?php
// Script à créer dans un fichier create_admin.php à placer dans votre dossier d'administration
// Exécutez ce script une fois pour créer un utilisateur administrateur
// Puis supprimez-le ou désactivez-le après usage pour des raisons de sécurité

// Configuration de la base de données
require_once dirname(__DIR__) . '/shop/db.php';

// Informations de l'administrateur
$admin_username = "admin";
$admin_email = "admin@netcrafter.com";
$admin_password = "Admin123!"; // Vous pouvez modifier ce mot de passe
$admin_full_name = "Administrateur Netcrafter";
$admin_phone = "+227 88371817";

// Connexion à la base de données
$conn = new mysqli($servername, $username, $password, $dbname);

// Vérification de la connexion
if ($conn->connect_error) {
    die("Échec de la connexion: " . $conn->connect_error);
}
$conn->set_charset("utf8");

// Vérifier si l'utilisateur/email existe déjà
$check_query = "SELECT id FROM users WHERE username = ? OR email = ?";
$stmt = $conn->prepare($check_query);
$stmt->bind_param("ss", $admin_username, $admin_email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo '<div style="background-color: #ffcccc; padding: 20px; border-radius: 5px; margin: 20px; text-align: center;">';
    echo '<h2>Erreur</h2>';
    echo '<p>Un utilisateur avec ce nom d\'utilisateur ou cette adresse e-mail existe déjà.</p>';
    echo '</div>';
} else {
    // Hasher le mot de passe
    $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
    
    // Insérer l'utilisateur administrateur
    $insert_query = "INSERT INTO users (username, email, password, full_name, phone, created_at, is_admin) 
                     VALUES (?, ?, ?, ?, ?, NOW(), 1)";
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("sssss", $admin_username, $admin_email, $hashed_password, $admin_full_name, $admin_phone);
    
    if ($stmt->execute()) {
        echo '<div style="background-color: #ccffcc; padding: 20px; border-radius: 5px; margin: 20px; text-align: center;">';
        echo '<h2>Succès!</h2>';
        echo '<p>L\'utilisateur administrateur a été créé avec succès.</p>';
        echo '<p><strong>Nom d\'utilisateur:</strong> ' . htmlspecialchars($admin_username) . '</p>';
        echo '<p><strong>Email:</strong> ' . htmlspecialchars($admin_email) . '</p>';
        echo '<p><strong>Mot de passe:</strong> ' . htmlspecialchars($admin_password) . '</p>';
        echo '<p style="margin-top: 20px; color: red;"><strong>IMPORTANT:</strong> Supprimez ou désactivez ce script immédiatement après usage pour des raisons de sécurité!</p>';
        echo '<p><a href="login.php" style="display: inline-block; margin-top: 10px; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px;">Aller à la page de connexion</a></p>';
        echo '</div>';
    } else {
        echo '<div style="background-color: #ffcccc; padding: 20px; border-radius: 5px; margin: 20px; text-align: center;">';
        echo '<h2>Erreur</h2>';
        echo '<p>Une erreur est survenue lors de la création de l\'utilisateur: ' . $stmt->error . '</p>';
        echo '</div>';
    }
}

// Fermeture de la connexion
$stmt->close();
$conn->close();
?>