<?php
// admin/export_data.php
session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/db.php';

// Connexion à la base de données
$conn = new mysqli($servername, $username, $password, $dbname);

// Vérification de la connexion
if ($conn->connect_error) {
    die("Échec de la connexion: " . $conn->connect_error);
}
$conn->set_charset("utf8");

// Récupérer les paramètres
$export_type = $_POST['export_type'] ?? '';
$start_date = $_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_POST['end_date'] ?? date('Y-m-d');

// Fonction pour nettoyer les données CSV
function cleanForCSV($value) {
    $value = str_replace('"', '""', $value);
    return '"' . $value . '"';
}

// Fonction pour générer le CSV
function generateCSV($data, $headers, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    
    $output = fopen('php://output', 'w');
    
    // BOM pour UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // En-têtes
    fputcsv($output, $headers, ';');
    
    // Données
    foreach ($data as $row) {
        fputcsv($output, $row, ';');
    }
    
    fclose($output);
}

try {
    switch ($export_type) {
        case 'users':
            // Export des utilisateurs
            $query = "
                SELECT 
                    u.id,
                    u.firstname,
                    u.lastname,
                    u.phone,
                    u.email,
                    u.date_of_birth,
                    u.place_of_birth,
                    u.address,
                    u.status,
                    u.created_at,
                    u.last_login,
                    COUNT(DISTINCT fs.id) as total_subscriptions,
                    SUM(fs.amount_paid) as total_spent,
                    COUNT(DISTINCT c.id) as certificates_earned
                FROM users u
                LEFT JOIN formation_subscriptions fs ON u.id = fs.user_id AND fs.status = 'active'
                LEFT JOIN certificates c ON u.id = c.user_id
                WHERE u.created_at BETWEEN ? AND ?
                GROUP BY u.id
                ORDER BY u.created_at DESC
            ";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $start_date, $end_date);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $headers = [
                'ID',
                'Prénom',
                'Nom',
                'Téléphone',
                'Email',
                'Date de naissance',
                'Lieu de naissance',
                'Adresse',
                'Statut',
                'Date d\'inscription',
                'Dernière connexion',
                'Nombre d\'abonnements',
                'Total dépensé (FCFA)',
                'Certificats obtenus'
            ];
            
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = [
                    $row['id'],
                    $row['firstname'],
                    $row['lastname'],
                    $row['phone'],
                    $row['email'] ?? '',
                    $row['date_of_birth'] ?? '',
                    $row['place_of_birth'] ?? '',
                    $row['address'] ?? '',
                    $row['status'],
                    date('d/m/Y H:i', strtotime($row['created_at'])),
                    $row['last_login'] ? date('d/m/Y H:i', strtotime($row['last_login'])) : 'Jamais',
                    $row['total_subscriptions'],
                    number_format($row['total_spent'] ?? 0, 0, ',', ' '),
                    $row['certificates_earned']
                ];
            }
            
            $filename = 'utilisateurs_' . date('Y-m-d_H-i-s') . '.csv';
            generateCSV($data, $headers, $filename);
            break;
            
        case 'subscriptions':
            // Export des abonnements
            $query = "
                SELECT 
                    fs.id,
                    u.firstname,
                    u.lastname,
                    u.phone,
                    f.title as formation_title,
                    fs.payment_method,
                    fs.subscription_months,
                    fs.amount_paid,
                    fs.status,
                    fs.start_date,
                    fs.end_date,
                    fs.created_at
                FROM formation_subscriptions fs
                JOIN users u ON fs.user_id = u.id
                JOIN formations f ON fs.formation_id = f.id
                WHERE fs.created_at BETWEEN ? AND ?
                ORDER BY fs.created_at DESC
            ";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $start_date, $end_date);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $headers = [
                'ID Abonnement',
                'Prénom',
                'Nom',
                'Téléphone',
                'Formation',
                'Méthode de paiement',
                'Durée (mois)',
                'Montant payé (FCFA)',
                'Statut',
                'Date de début',
                'Date de fin',
                'Date d\'abonnement'
            ];
            
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = [
                    $row['id'],
                    $row['firstname'],
                    $row['lastname'],
                    $row['phone'],
                    $row['formation_title'],
                    strtoupper($row['payment_method']),
                    $row['subscription_months'],
                    number_format($row['amount_paid'], 0, ',', ' '),
                    $row['status'],
                    $row['start_date'] ? date('d/m/Y', strtotime($row['start_date'])) : '',
                    $row['end_date'] ? date('d/m/Y', strtotime($row['end_date'])) : '',
                    date('d/m/Y H:i', strtotime($row['created_at']))
                ];
            }
            
            $filename = 'abonnements_' . date('Y-m-d_H-i-s') . '.csv';
            generateCSV($data, $headers, $filename);
            break;
            
        case 'revenue':
            // Export des revenus par formation
            $query = "
                SELECT 
                    f.title as formation_title,
                    f.price_per_month,
                    COUNT(fs.id) as total_subscriptions,
                    SUM(fs.amount_paid) as total_revenue,
                    AVG(fs.amount_paid) as avg_revenue,
                    MIN(fs.created_at) as first_subscription,
                    MAX(fs.created_at) as last_subscription,
                    COUNT(CASE WHEN fs.status = 'active' THEN 1 END) as active_subscriptions,
                    COUNT(CASE WHEN fs.status = 'expired' THEN 1 END) as expired_subscriptions
                FROM formations f
                LEFT JOIN formation_subscriptions fs ON f.id = fs.formation_id
                WHERE fs.created_at BETWEEN ? AND ?
                GROUP BY f.id, f.title, f.price_per_month
                HAVING total_subscriptions > 0
                ORDER BY total_revenue DESC
            ";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $start_date, $end_date);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $headers = [
                'Formation',
                'Prix mensuel (FCFA)',
                'Nombre d\'abonnements',
                'Revenus totaux (FCFA)',
                'Revenus moyens (FCFA)',
                'Premier abonnement',
                'Dernier abonnement',
                'Abonnements actifs',
                'Abonnements expirés'
            ];
            
            $data = [];
            $total_revenue = 0;
            $total_subscriptions = 0;
            
            while ($row = $result->fetch_assoc()) {
                $total_revenue += $row['total_revenue'];
                $total_subscriptions += $row['total_subscriptions'];
                
                $data[] = [
                    $row['formation_title'],
                    number_format($row['price_per_month'], 0, ',', ' '),
                    $row['total_subscriptions'],
                    number_format($row['total_revenue'], 0, ',', ' '),
                    number_format($row['avg_revenue'], 0, ',', ' '),
                    $row['first_subscription'] ? date('d/m/Y', strtotime($row['first_subscription'])) : '',
                    $row['last_subscription'] ? date('d/m/Y', strtotime($row['last_subscription'])) : '',
                    $row['active_subscriptions'],
                    $row['expired_subscriptions']
                ];
            }
            
            // Ajouter une ligne de total
            $data[] = [
                'TOTAL',
                '',
                $total_subscriptions,
                number_format($total_revenue, 0, ',', ' '),
                $total_subscriptions > 0 ? number_format($total_revenue / $total_subscriptions, 0, ',', ' ') : '0',
                '',
                '',
                '',
                ''
            ];
            
            $filename = 'revenus_' . date('Y-m-d_H-i-s') . '.csv';
            generateCSV($data, $headers, $filename);
            break;
            
        case 'certificates':
            // Export des certificats
            $query = "
                SELECT 
                    c.id,
                    c.certificate_number,
                    u.firstname,
                    u.lastname,
                    u.phone,
                    f.title as formation_title,
                    c.issue_date,
                    c.verified,
                    c.verification_code,
                    qa.score,
                    qa.completed_at as quiz_completed_at
                FROM certificates c
                JOIN users u ON c.user_id = u.id
                JOIN formations f ON c.formation_id = f.id
                LEFT JOIN quiz_attempts qa ON c.quiz_attempt_id = qa.id
                WHERE c.created_at BETWEEN ? AND ?
                ORDER BY c.created_at DESC
            ";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $start_date, $end_date);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $headers = [
                'ID Certificat',
                'Numéro de certificat',
                'Prénom',
                'Nom',
                'Téléphone',
                'Formation',
                'Date d\'émission',
                'Vérifié',
                'Code de vérification',
                'Score du quiz (%)',
                'Date de completion du quiz'
            ];
            
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = [
                    $row['id'],
                    $row['certificate_number'],
                    $row['firstname'],
                    $row['lastname'],
                    $row['phone'],
                    $row['formation_title'],
                    date('d/m/Y H:i', strtotime($row['issue_date'])),
                    $row['verified'] ? 'Oui' : 'Non',
                    $row['verification_code'],
                    $row['score'] ?? 'N/A',
                    $row['quiz_completed_at'] ? date('d/m/Y H:i', strtotime($row['quiz_completed_at'])) : 'N/A'
                ];
            }
            
            $filename = 'certificats_' . date('Y-m-d_H-i-s') . '.csv';
            generateCSV($data, $headers, $filename);
            break;
            
        default:
            throw new Exception('Type d\'export non valide');
    }
    
} catch (Exception $e) {
    // En cas d'erreur, rediriger vers la page des rapports avec un message d'erreur
    header("Location: reports.php?error=" . urlencode($e->getMessage()));
    exit;
} finally {
    $conn->close();
}
?>