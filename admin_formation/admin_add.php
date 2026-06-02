<?php
// Configuration de la base de données
require_once __DIR__ . '/db.php';
$host = $servername;
// $username, $password, $dbname set by db.php

try {
    // Connexion à la base de données
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Fonction pour ajouter un administrateur
    function addAdmin($pdo, $adminData) {
        // Validation des données
        $requiredFields = ['username', 'email', 'password', 'firstname', 'lastname', 'role'];
        foreach ($requiredFields as $field) {
            if (empty($adminData[$field])) {
                throw new Exception("Le champ $field est requis");
            }
        }
        
        // Validation de l'email
        if (!filter_var($adminData['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("L'adresse email n'est pas valide");
        }
        
        // Validation du rôle
        $validRoles = ['super_admin', 'admin', 'moderator'];
        if (!in_array($adminData['role'], $validRoles)) {
            throw new Exception("Le rôle doit être : " . implode(', ', $validRoles));
        }
        
        // Vérifier si l'username ou l'email existe déjà
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = ? OR email = ?");
        $checkStmt->execute([$adminData['username'], $adminData['email']]);
        
        if ($checkStmt->fetchColumn() > 0) {
            throw new Exception("Un administrateur avec ce nom d'utilisateur ou cette adresse email existe déjà");
        }
        
        // Hachage du mot de passe
        $hashedPassword = password_hash($adminData['password'], PASSWORD_DEFAULT);
        
        // Définition des permissions par défaut selon le rôle
        $permissions = getDefaultPermissions($adminData['role']);
        
        // Insertion dans la base de données
        $sql = "INSERT INTO admins (username, email, password, firstname, lastname, role, permissions, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $adminData['username'],
            $adminData['email'],
            $hashedPassword,
            $adminData['firstname'],
            $adminData['lastname'],
            $adminData['role'],
            json_encode($permissions)
        ]);
        
        if ($result) {
            return $pdo->lastInsertId();
        } else {
            throw new Exception("Erreur lors de l'insertion en base de données");
        }
    }
    
    // Fonction pour définir les permissions par défaut
    function getDefaultPermissions($role) {
        switch ($role) {
            case 'super_admin':
                return [
                    "users" => ["view" => true, "create" => true, "edit" => true, "delete" => true],
                    "formations" => ["view" => true, "create" => true, "edit" => true, "delete" => true],
                    "subscriptions" => ["view" => true, "approve" => true, "reject" => true],
                    "certificates" => ["view" => true, "create" => true, "revoke" => true],
                    "settings" => ["view" => true, "edit" => true],
                    "logs" => ["view" => true]
                ];
            
            case 'admin':
                return [
                    "users" => ["view" => true],
                    "formations" => ["view" => true, "create" => true, "edit" => true],
                    "subscriptions" => ["view" => true],
                    "certificates" => ["view" => true]
                ];
            
            case 'moderator':
                return [
                    "users" => ["view" => true, "edit" => true],
                    "formations" => ["view" => true, "edit" => true],
                    "subscriptions" => ["view" => true, "approve" => true],
                    "certificates" => ["view" => true]
                ];
            
            default:
                return [];
        }
    }
    
    // Fonction pour afficher le formulaire
    function displayForm() {
        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Ajouter un Administrateur</title>
            <style>
                body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
                .form-group { margin-bottom: 15px; }
                label { display: block; margin-bottom: 5px; font-weight: bold; }
                input, select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
                button { background-color: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
                button:hover { background-color: #0056b3; }
                .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
                .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
                .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
            </style>
        </head>
        <body>
            <h1>Ajouter un Administrateur</h1>
            <form method="POST">
                <div class="form-group">
                    <label for="username">Nom d'utilisateur *</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Adresse email *</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Mot de passe *</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="firstname">Prénom *</label>
                    <input type="text" id="firstname" name="firstname" required>
                </div>
                
                <div class="form-group">
                    <label for="lastname">Nom *</label>
                    <input type="text" id="lastname" name="lastname" required>
                </div>
                
                <div class="form-group">
                    <label for="role">Rôle *</label>
                    <select id="role" name="role" required>
                        <option value="">Sélectionnez un rôle</option>
                        <option value="super_admin">Super Administrateur</option>
                        <option value="admin">Administrateur</option>
                        <option value="moderator">Modérateur</option>
                    </select>
                </div>
                
                <button type="submit" name="add_admin">Ajouter l'Administrateur</button>
            </form>
        </body>
        </html>
        <?php
    }
    
    // Traitement du formulaire
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
        try {
            $adminData = [
                'username' => trim($_POST['username']),
                'email' => trim($_POST['email']),
                'password' => $_POST['password'],
                'firstname' => trim($_POST['firstname']),
                'lastname' => trim($_POST['lastname']),
                'role' => $_POST['role']
            ];
            
            $adminId = addAdmin($pdo, $adminData);
            
            echo '<div class="alert alert-success">Administrateur ajouté avec succès ! ID: ' . $adminId . '</div>';
            
        } catch (Exception $e) {
            echo '<div class="alert alert-error">Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
    
    // Affichage du formulaire
    displayForm();
    
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}
?>

<?php
// ========================================
// VERSION SIMPLE POUR UTILISATION EN CLI
// ========================================

/*
// Configuration
$host = '127.0.0.1:3306';
$dbname = 'netcrafter_formation';
$username = 'root';
$password = '';

// Données de l'administrateur à ajouter
$newAdmin = [
    'username' => 'nouvel_admin',
    'email' => 'admin@example.com',
    'password' => 'motdepasse123',
    'firstname' => 'Prénom',
    'lastname' => 'Nom',
    'role' => 'admin' // super_admin, admin, ou moderator
];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Vérification unicité
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = ? OR email = ?");
    $checkStmt->execute([$newAdmin['username'], $newAdmin['email']]);
    
    if ($checkStmt->fetchColumn() > 0) {
        die("Un administrateur avec ce nom d'utilisateur ou cette adresse email existe déjà\n");
    }
    
    // Hachage du mot de passe
    $hashedPassword = password_hash($newAdmin['password'], PASSWORD_DEFAULT);
    
    // Permissions par défaut
    $permissions = [
        "users" => ["view" => true],
        "formations" => ["view" => true, "create" => true, "edit" => true],
        "subscriptions" => ["view" => true],
        "certificates" => ["view" => true]
    ];
    
    // Insertion
    $sql = "INSERT INTO admins (username, email, password, firstname, lastname, role, permissions, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        $newAdmin['username'],
        $newAdmin['email'],
        $hashedPassword,
        $newAdmin['firstname'],
        $newAdmin['lastname'],
        $newAdmin['role'],
        json_encode($permissions)
    ]);
    
    if ($result) {
        echo "Administrateur ajouté avec succès ! ID: " . $pdo->lastInsertId() . "\n";
    }
    
} catch (PDOException $e) {
    die("Erreur : " . $e->getMessage() . "\n");
}
*/
?>