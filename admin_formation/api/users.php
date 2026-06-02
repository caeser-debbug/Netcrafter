<?php
// admin/api/users.php
session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

require_once __DIR__ . '/../db.php';

// Connexion à la base de données
$conn = new mysqli($servername, $username, $password, $dbname);

// Vérification de la connexion
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données']);
    exit;
}
$conn->set_charset("utf8");

// Headers pour JSON
header('Content-Type: application/json');

// Récupérer l'action
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get':
        getUserDetails();
        break;
    case 'export':
        exportUsers();
        break;
    case 'import':
        importUsers();
        break;
    case 'update':
        updateUser();
        break;
    case 'ban':
        banUser();
        break;
    case 'unban':
        unbanUser();
        break;
    case 'delete':
        deleteUser();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action non valide']);
        break;
}

function getUserDetails() {
    global $conn;
    
    $user_id = intval($_GET['id'] ?? 0);
    
    if ($user_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID utilisateur invalide']);
        return;
    }
    
    $query = "SELECT u.*, 
              COUNT(DISTINCT fs.id) as total_subscriptions,
              COUNT(DISTINCT c.id) as total_certificates,
              SUM(CASE WHEN fs.status = 'active' AND fs.end_date >= CURDATE() THEN 1 ELSE 0 END) as active_subscriptions
              FROM users u
              LEFT JOIN formation_subscriptions fs ON u.id = fs.user_id
              LEFT JOIN certificates c ON u.id = c.user_id
              WHERE u.id = ?
              GROUP BY u.id";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Utilisateur non trouvé']);
        return;
    }
    
    $user = $result->fetch_assoc();
    
    // Récupérer les formations de l'utilisateur
    $formations_query = "SELECT f.title, fs.status, fs.start_date, fs.end_date, fs.amount_paid
                        FROM formation_subscriptions fs
                        JOIN formations f ON fs.formation_id = f.id
                        WHERE fs.user_id = ?
                        ORDER BY fs.created_at DESC";
    
    $formations_stmt = $conn->prepare($formations_query);
    $formations_stmt->bind_param("i", $user_id);
    $formations_stmt->execute();
    $formations_result = $formations_stmt->get_result();
    
    $formations = [];
    while ($row = $formations_result->fetch_assoc()) {
        $formations[] = $row;
    }
    
    $user['formations'] = $formations;
    
    echo json_encode(['success' => true, 'user' => $user]);
}

function exportUsers() {
    global $conn;
    
    // Paramètres de filtrage (comme dans users.php)
    $search = $_GET['search'] ?? '';
    $status_filter = $_GET['status'] ?? 'all';
    
    $where_conditions = [];
    $params = [];
    $types = "";
    
    if (!empty($search)) {
        $where_conditions[] = "(firstname LIKE CONCAT('%', ?, '%') OR lastname LIKE CONCAT('%', ?, '%') OR phone LIKE CONCAT('%', ?, '%') OR email LIKE CONCAT('%', ?, '%'))";
        $params = array_merge($params, [$search, $search, $search, $search]);
        $types .= "ssss";
    }
    
    if ($status_filter !== 'all') {
        $where_conditions[] = "status = ?";
        $params[] = $status_filter;
        $types .= "s";
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    $query = "SELECT id, firstname, lastname, phone, email, status, created_at, last_login 
              FROM users $where_clause 
              ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Générer le CSV
    $filename = 'users_export_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // En-têtes CSV
    fputcsv($output, [
        'ID',
        'Prénom',
        'Nom',
        'Téléphone',
        'Email',
        'Statut',
        'Date inscription',
        'Dernière connexion'
    ]);
    
    // Données
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['firstname'],
            $row['lastname'],
            $row['phone'],
            $row['email'] ?? '',
            $row['status'],
            $row['created_at'],
            $row['last_login'] ?? ''
        ]);
    }
    
    fclose($output);
    
    // Log de l'activité
    $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'export_users', 'Export des utilisateurs')";
    $log_stmt = $conn->prepare($log_query);
    $log_stmt->bind_param("i", $_SESSION['admin_id']);
    $log_stmt->execute();
    
    exit;
}

function importUsers() {
    global $conn;
    
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Aucun fichier uploadé ou erreur d\'upload']);
        return;
    }
    
    $send_welcome_email = isset($_POST['send_welcome_email']);
    
    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, 'r');
    
    if (!$handle) {
        echo json_encode(['success' => false, 'message' => 'Impossible de lire le fichier']);
        return;
    }
    
    // Lire les en-têtes
    $headers = fgetcsv($handle);
    
    if (!$headers || !in_array('firstname', $headers) || !in_array('lastname', $headers) || !in_array('phone', $headers)) {
        echo json_encode(['success' => false, 'message' => 'Le fichier CSV doit contenir au minimum les colonnes: firstname, lastname, phone']);
        fclose($handle);
        return;
    }
    
    $imported = 0;
    $errors = [];
    
    $conn->begin_transaction();
    
    try {
        while (($data = fgetcsv($handle)) !== FALSE) {
            if (count($data) < count($headers)) {
                continue; // Ligne incomplète
            }
            
            $row = array_combine($headers, $data);
            
            // Valider les données
            if (empty($row['firstname']) || empty($row['lastname']) || empty($row['phone'])) {
                $errors[] = "Ligne ignorée: données manquantes";
                continue;
            }
            
            // Vérifier si le téléphone existe déjà
            $check_query = "SELECT id FROM users WHERE phone = ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("s", $row['phone']);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows > 0) {
                $errors[] = "Téléphone {$row['phone']} déjà existant";
                continue;
            }
            
            // Générer un mot de passe temporaire
            $temp_password = bin2hex(random_bytes(4)); // 8 caractères
            $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);
            
            // Insérer l'utilisateur
            $insert_query = "INSERT INTO users (firstname, lastname, phone, email, password, status) VALUES (?, ?, ?, ?, ?, 'active')";
            $insert_stmt = $conn->prepare($insert_query);
            $email = !empty($row['email']) ? $row['email'] : null;
            $insert_stmt->bind_param("sssss", $row['firstname'], $row['lastname'], $row['phone'], $email, $hashed_password);
            
            if ($insert_stmt->execute()) {
                $imported++;
                
                // Envoyer l'email de bienvenue si demandé et si email fourni
                if ($send_welcome_email && !empty($email)) {
                    sendWelcomeEmail($email, $row['firstname'], $row['lastname'], $temp_password);
                }
            } else {
                $errors[] = "Erreur lors de l'insertion pour {$row['firstname']} {$row['lastname']}";
            }
        }
        
        $conn->commit();
        
        // Log de l'activité
        $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'import_users', ?)";
        $log_stmt = $conn->prepare($log_query);
        $description = "Import de $imported utilisateurs";
        $log_stmt->bind_param("is", $_SESSION['admin_id'], $description);
        $log_stmt->execute();
        
        echo json_encode([
            'success' => true, 
            'imported' => $imported,
            'errors' => $errors
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'importation: ' . $e->getMessage()]);
    }
    
    fclose($handle);
}

function sendWelcomeEmail($email, $firstname, $lastname, $password) {
    // Récupérer le template d'email
    global $conn;
    
    $template_query = "SELECT subject, body FROM email_templates WHERE name = 'welcome' AND is_active = 1";
    $template_result = $conn->query($template_query);
    
    if ($template_result->num_rows === 0) {
        return false;
    }
    
    $template = $template_result->fetch_assoc();
    
    // Remplacer les variables
    $subject = str_replace(['{firstname}', '{lastname}'], [$firstname, $lastname], $template['subject']);
    $body = str_replace([
        '{firstname}', 
        '{lastname}', 
        '{email}', 
        '{password}'
    ], [
        $firstname, 
        $lastname, 
        $email, 
        $password
    ], $template['body']);
    
    $body .= "\n\nVotre mot de passe temporaire: $password\nVeuillez le changer lors de votre première connexion.";
    
    // Headers email
    $headers = [
        'From: noreply@netcrafter.com',
        'Reply-To: support@netcrafterniger.com',
        'X-Mailer: PHP/' . phpversion(),
        'Content-Type: text/plain; charset=UTF-8'
    ];
    
    return mail($email, $subject, $body, implode("\r\n", $headers));
}

function updateUser() {
    global $conn;
    
    $user_id = intval($_POST['user_id'] ?? 0);
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    if ($user_id <= 0 || empty($firstname) || empty($lastname) || empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Données manquantes']);
        return;
    }
    
    // Vérifier si le téléphone n'est pas déjà utilisé par un autre utilisateur
    $check_query = "SELECT id FROM users WHERE phone = ? AND id != ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("si", $phone, $user_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Ce numéro de téléphone est déjà utilisé']);
        return;
    }
    
    // Mettre à jour l'utilisateur
    $update_query = "UPDATE users SET firstname = ?, lastname = ?, phone = ?, email = ?, status = ?, updated_at = NOW() WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $email_value = !empty($email) ? $email : null;
    $update_stmt->bind_param("sssssi", $firstname, $lastname, $phone, $email_value, $status, $user_id);
    
    if ($update_stmt->execute()) {
        // Log de l'activité
        $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'update_user', ?)";
        $log_stmt = $conn->prepare($log_query);
        $description = "Modification de l'utilisateur ID: $user_id";
        $log_stmt->bind_param("is", $_SESSION['admin_id'], $description);
        $log_stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Utilisateur mis à jour avec succès']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
    }
}

function banUser() {
    global $conn;
    
    $user_id = intval($_POST['user_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    
    if ($user_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID utilisateur invalide']);
        return;
    }
    
    $update_query = "UPDATE users SET status = 'banned', updated_at = NOW() WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("i", $user_id);
    
    if ($update_stmt->execute()) {
        // Log de l'activité
        $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'ban_user', ?)";
        $log_stmt = $conn->prepare($log_query);
        $description = "Bannissement de l'utilisateur ID: $user_id" . (!empty($reason) ? " - Raison: $reason" : "");
        $log_stmt->bind_param("is", $_SESSION['admin_id'], $description);
        $log_stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Utilisateur banni avec succès']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors du bannissement']);
    }
}

function unbanUser() {
    global $conn;
    
    $user_id = intval($_POST['user_id'] ?? 0);
    
    if ($user_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID utilisateur invalide']);
        return;
    }
    
    $update_query = "UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("i", $user_id);
    
    if ($update_stmt->execute()) {
        // Log de l'activité
        $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'unban_user', ?)";
        $log_stmt = $conn->prepare($log_query);
        $description = "Débannissement de l'utilisateur ID: $user_id";
        $log_stmt->bind_param("is", $_SESSION['admin_id'], $description);
        $log_stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Utilisateur débanni avec succès']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors du débannissement']);
    }
}

function deleteUser() {
    global $conn;
    
    $user_id = intval($_POST['user_id'] ?? 0);
    
    if ($user_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID utilisateur invalide']);
        return;
    }
    
    // Vérifier si l'utilisateur a des abonnements actifs
    $check_query = "SELECT COUNT(*) as active_subs FROM formation_subscriptions WHERE user_id = ? AND status = 'active' AND end_date >= CURDATE()";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $user_id);
    $check_stmt->execute();
    $active_subs = $check_stmt->get_result()->fetch_assoc()['active_subs'];
    
    if ($active_subs > 0) {
        echo json_encode(['success' => false, 'message' => 'Impossible de supprimer cet utilisateur car il a des abonnements actifs']);
        return;
    }
    
    $conn->begin_transaction();
    
    try {
        // Supprimer les données liées
        $tables_to_clean = [
            'formation_favorites',
            'video_progress',
            'quiz_attempts',
            'certificates',
            'formation_subscriptions',
            'forum_replies',
            'forum_topics',
            'user_settings'
        ];
        
        foreach ($tables_to_clean as $table) {
            $delete_query = "DELETE FROM $table WHERE user_id = ?";
            $delete_stmt = $conn->prepare($delete_query);
            $delete_stmt->bind_param("i", $user_id);
            $delete_stmt->execute();
        }
        
        // Supprimer l'utilisateur
        $delete_user_query = "DELETE FROM users WHERE id = ?";
        $delete_user_stmt = $conn->prepare($delete_user_query);
        $delete_user_stmt->bind_param("i", $user_id);
        $delete_user_stmt->execute();
        
        $conn->commit();
        
        // Log de l'activité
        $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'delete_user', ?)";
        $log_stmt = $conn->prepare($log_query);
        $description = "Suppression définitive de l'utilisateur ID: $user_id";
        $log_stmt->bind_param("is", $_SESSION['admin_id'], $description);
        $log_stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Utilisateur supprimé avec succès']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression: ' . $e->getMessage()]);
    }
}

$conn->close();
?>