<?php
// admin/api/certificates.php
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
    case 'send_certificate':
        sendCertificateByEmail();
        break;
    case 'verify':
        verifyCertificateByCode();
        break;
    case 'stats':
        getCertificateStats();
        break;
    case 'bulk_generate':
        bulkGenerateCertificates();
        break;
    case 'check_new':
        checkNewCertificates();
        break;
    case 'validate_all':
        validateAllCertificates();
        break;
    case 'check_duplicates':
        checkDuplicateCertificates();
        break;
    case 'export':
        exportCertificates();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action non valide']);
        break;
}

function sendCertificateByEmail() {
    global $conn;
    
    $certificate_id = intval($_POST['certificate_id'] ?? 0);
    
    if ($certificate_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID certificat invalide']);
        return;
    }
    
    // Récupérer les informations du certificat
    $query = "SELECT c.*, u.firstname, u.lastname, u.email, f.title as formation_title
              FROM certificates c
              JOIN users u ON c.user_id = u.id
              JOIN formations f ON c.formation_id = f.id
              WHERE c.id = ? AND c.verified = 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $certificate_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Certificat non trouvé ou non vérifié']);
        return;
    }
    
    $certificate = $result->fetch_assoc();
    
    if (empty($certificate['email'])) {
        echo json_encode(['success' => false, 'message' => 'Aucune adresse email pour cet utilisateur']);
        return;
    }
    
    // Envoyer l'email
    $to = $certificate['email'];
    $subject = "Votre certificat de formation - " . $certificate['formation_title'];
    
    $message = "Bonjour " . $certificate['firstname'] . " " . $certificate['lastname'] . ",\n\n";
    $message .= "Félicitations ! Vous avez terminé avec succès la formation : " . $certificate['formation_title'] . "\n\n";
    $message .= "Votre certificat est maintenant disponible :\n";
    $message .= "Numéro de certificat : " . $certificate['certificate_number'] . "\n";
    $message .= "Code de vérification : " . $certificate['verification_code'] . "\n\n";
    $message .= "Vous pouvez télécharger votre certificat à l'adresse suivante :\n";
    $message .= "https://" . $_SERVER['HTTP_HOST'] . "/certificates/download.php?code=" . $certificate['verification_code'] . "\n\n";
    $message .= "Vous pouvez également vérifier l'authenticité de votre certificat à tout moment avec le code de vérification.\n\n";
    $message .= "Cordialement,\nL'équipe Netcrafter Formation";
    
    $headers = [
        'From: noreply@netcrafter.com',
        'Reply-To: support@netcrafterniger.com',
        'X-Mailer: PHP/' . phpversion(),
        'Content-Type: text/plain; charset=UTF-8'
    ];
    
    if (mail($to, $subject, $message, implode("\r\n", $headers))) {
        // Log de l'activité
        $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'send_certificate', ?)";
        $log_stmt = $conn->prepare($log_query);
        $description = "Envoi du certificat " . $certificate['certificate_number'] . " par email";
        $log_stmt->bind_param("is", $_SESSION['admin_id'], $description);
        $log_stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Certificat envoyé par email avec succès']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'envoi de l\'email']);
    }
}

function verifyCertificateByCode() {
    global $conn;
    
    $verification_code = $_GET['code'] ?? '';
    
    if (empty($verification_code)) {
        echo json_encode(['success' => false, 'message' => 'Code de vérification manquant']);
        return;
    }
    
    $query = "SELECT c.*, u.firstname, u.lastname, f.title as formation_title
              FROM certificates c
              JOIN users u ON c.user_id = u.id
              JOIN formations f ON c.formation_id = f.id
              WHERE c.verification_code = ? AND c.verified = 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $verification_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Certificat non valide']);
        return;
    }
    
    $certificate = $result->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'certificate' => [
            'user_name' => $certificate['firstname'] . ' ' . $certificate['lastname'],
            'formation_title' => $certificate['formation_title'],
            'issue_date' => date('d/m/Y', strtotime($certificate['issue_date'])),
            'certificate_number' => $certificate['certificate_number']
        ]
    ]);
}

function getCertificateStats() {
    global $conn;
    
    $stats_query = "SELECT 
        COUNT(*) as total_certificates,
        SUM(CASE WHEN verified = 1 THEN 1 ELSE 0 END) as verified_certificates,
        SUM(CASE WHEN verified = 0 THEN 1 ELSE 0 END) as revoked_certificates,
        COUNT(DISTINCT user_id) as unique_users,
        COUNT(DISTINCT formation_id) as formations_with_certificates,
        COUNT(CASE WHEN DATE(issue_date) = CURDATE() THEN 1 END) as today_certificates,
        COUNT(CASE WHEN DATE(issue_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as week_certificates,
        COUNT(CASE WHEN DATE(issue_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as month_certificates
        FROM certificates";
    
    $result = $conn->query($stats_query);
    $stats = $result->fetch_assoc();
    
    // Statistiques par formation
    $formation_stats_query = "SELECT f.title, COUNT(c.id) as certificate_count
                             FROM certificates c
                             JOIN formations f ON c.formation_id = f.id
                             WHERE c.verified = 1
                             GROUP BY f.id, f.title
                             ORDER BY certificate_count DESC
                             LIMIT 10";
    
    $formation_result = $conn->query($formation_stats_query);
    $formation_stats = [];
    while ($row = $formation_result->fetch_assoc()) {
        $formation_stats[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'formation_stats' => $formation_stats
    ]);
}

function bulkGenerateCertificates() {
    global $conn;
    
    // Récupérer tous les utilisateurs éligibles
    $eligible_query = "SELECT DISTINCT u.id as user_id, fs.formation_id
                      FROM users u
                      JOIN formation_subscriptions fs ON u.id = fs.user_id
                      LEFT JOIN certificates c ON u.id = c.user_id AND fs.formation_id = c.formation_id AND c.verified = 1
                      WHERE fs.status = 'active' 
                      AND fs.end_date >= CURDATE()
                      AND c.id IS NULL";
    
    $eligible_result = $conn->query($eligible_query);
    $generated = 0;
    
    $conn->begin_transaction();
    
    try {
        while ($eligible = $eligible_result->fetch_assoc()) {
            // Générer le certificat
            $certificate_number = 'NC-' . date('Y') . '-' . str_pad($eligible['formation_id'], 3, '0', STR_PAD_LEFT) . '-' . str_pad($eligible['user_id'], 5, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(md5(time() . $eligible['user_id'] . $eligible['formation_id']), 0, 4));
            $verification_code = strtoupper(substr(md5($certificate_number . time()), 0, 8));
            $certificate_url = "certificates/generate.php?code=" . $verification_code;
            
            $insert_query = "INSERT INTO certificates (user_id, formation_id, certificate_number, certificate_url, verification_code, verified) VALUES (?, ?, ?, ?, ?, 1)";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("iisss", $eligible['user_id'], $eligible['formation_id'], $certificate_number, $certificate_url, $verification_code);
            
            if ($stmt->execute()) {
                $generated++;
            }
        }
        
        $conn->commit();
        
        // Log de l'activité
        $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'bulk_generate_certificates', ?)";
        $log_stmt = $conn->prepare($log_query);
        $description = "Génération en masse de $generated certificats";
        $log_stmt->bind_param("is", $_SESSION['admin_id'], $description);
        $log_stmt->execute();
        
        echo json_encode(['success' => true, 'generated' => $generated]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la génération en masse: ' . $e->getMessage()]);
    }
}

function checkNewCertificates() {
    global $conn;
    
    // Vérifier les nouveaux certificats des 5 dernières minutes
    $query = "SELECT COUNT(*) as new_certificates 
              FROM certificates 
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
    
    $result = $conn->query($query);
    $data = $result->fetch_assoc();
    
    echo json_encode(['success' => true, 'new_certificates' => $data['new_certificates']]);
}

function validateAllCertificates() {
    global $conn;
    
    // Vérifier les certificats qui pourraient être invalides
    $query = "SELECT COUNT(*) as invalid_certificates
              FROM certificates c
              LEFT JOIN users u ON c.user_id = u.id
              LEFT JOIN formations f ON c.formation_id = f.id
              WHERE u.id IS NULL OR f.id IS NULL OR c.verified = 0";
    
    $result = $conn->query($query);
    $data = $result->fetch_assoc();
    
    echo json_encode(['success' => true, 'invalid_certificates' => $data['invalid_certificates']]);
}

function checkDuplicateCertificates() {
    global $conn;
    
    $query = "SELECT u.firstname, u.lastname, f.title as formation_title, COUNT(*) as duplicate_count
              FROM certificates c
              JOIN users u ON c.user_id = u.id
              JOIN formations f ON c.formation_id = f.id
              WHERE c.verified = 1
              GROUP BY c.user_id, c.formation_id
              HAVING COUNT(*) > 1";
    
    $result = $conn->query($query);
    $duplicates = [];
    
    while ($row = $result->fetch_assoc()) {
        $duplicates[] = [
            'user_name' => $row['firstname'] . ' ' . $row['lastname'],
            'formation_title' => $row['formation_title'],
            'count' => $row['duplicate_count']
        ];
    }
    
    echo json_encode(['success' => true, 'duplicates' => $duplicates]);
}

function exportCertificates() {
    global $conn;
    
    // Paramètres de filtrage (comme dans certificates.php)
    $search = $_GET['search'] ?? '';
    $formation_filter = $_GET['formation'] ?? 0;
    $status_filter = $_GET['status'] ?? 'all';
    
    $where_conditions = [];
    $params = [];
    $types = "";
    
    if (!empty($search)) {
        $where_conditions[] = "(u.firstname LIKE CONCAT('%', ?, '%') OR u.lastname LIKE CONCAT('%', ?, '%') OR c.certificate_number LIKE CONCAT('%', ?, '%') OR f.title LIKE CONCAT('%', ?, '%'))";
        $params = array_merge($params, [$search, $search, $search, $search]);
        $types .= "ssss";
    }
    
    if ($formation_filter > 0) {
        $where_conditions[] = "c.formation_id = ?";
        $params[] = $formation_filter;
        $types .= "i";
    }
    
    if ($status_filter !== 'all') {
        if ($status_filter === 'verified') {
            $where_conditions[] = "c.verified = 1";
        } else {
            $where_conditions[] = "c.verified = 0";
        }
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    $query = "SELECT c.certificate_number, c.verification_code, c.issue_date, c.verified,
              u.firstname, u.lastname, u.phone, u.email,
              f.title as formation_title, fc.name as category_name
              FROM certificates c
              JOIN users u ON c.user_id = u.id
              JOIN formations f ON c.formation_id = f.id
              LEFT JOIN formation_categories fc ON f.category_id = fc.id
              $where_clause
              ORDER BY c.issue_date DESC";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Générer le CSV
    $filename = 'certificates_export_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    
    // En-têtes CSV
    fputcsv($output, [
        'Numéro de certificat',
        'Code de vérification',
        'Prénom',
        'Nom',
        'Téléphone',
        'Email',
        'Formation',
        'Catégorie',
        'Date d\'émission',
        'Statut'
    ]);
    
    // Données
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['certificate_number'],
            $row['verification_code'],
            $row['firstname'],
            $row['lastname'],
            $row['phone'],
            $row['email'] ?? '',
            $row['formation_title'],
            $row['category_name'] ?? '',
            $row['issue_date'],
            $row['verified'] ? 'Vérifié' : 'Révoqué'
        ]);
    }
    
    fclose($output);
    
    // Log de l'activité
    $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'export_certificates', 'Export des certificats')";
    $log_stmt = $conn->prepare($log_query);
    $log_stmt->bind_param("i", $_SESSION['admin_id']);
    $log_stmt->execute();
    
    exit;
}

$conn->close();
?>