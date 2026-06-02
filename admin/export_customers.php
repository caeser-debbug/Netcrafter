<?php
require_once 'includes/config.php';
require_once 'includes/auth_check.php';

// Vérifier que c'est une requête d'export
if (!isset($_GET['fields']) || !is_array($_GET['fields'])) {
    header('Location: customers.php');
    exit;
}

$fields = $_GET['fields'];
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Construction de la requête
$sql = "SELECT u.*, 
               COUNT(DISTINCT o.id) as total_orders,
               COALESCE(SUM(o.total_amount), 0) as total_spent,
               MAX(o.created_at) as last_order_date
        FROM users u 
        LEFT JOIN orders o ON u.id = o.user_id 
        WHERE u.is_admin = 0";

$params = [];
$types = "";

if (!empty($search_term)) {
    $sql .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.full_name LIKE ? OR u.phone LIKE ?)";
    $search_param = "%" . $search_term . "%";
    $params = [$search_param, $search_param, $search_param, $search_param];
    $types = "ssss";
}

$sql .= " GROUP BY u.id ORDER BY u.id DESC";

$stmt = $conn->prepare($sql);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Préparer le fichier CSV
$filename = 'clients_export_' . date('Y-m-d_H-i-s') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// BOM pour UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// En-têtes CSV
$headers = [];
if (in_array('basic_info', $fields)) {
    $headers = array_merge($headers, ['ID', 'Nom d\'utilisateur', 'Nom complet', 'Email', 'Téléphone']);
}
if (in_array('dates', $fields)) {
    $headers = array_merge($headers, ['Date d\'inscription', 'Dernière connexion']);
}
if (in_array('orders', $fields)) {
    $headers = array_merge($headers, ['Nombre de commandes', 'Total dépensé (FCFA)', 'Dernière commande']);
}

fputcsv($output, $headers, ';');

// Données
while ($row = $result->fetch_assoc()) {
    $data = [];
    
    if (in_array('basic_info', $fields)) {
        $data = array_merge($data, [
            $row['id'],
            $row['username'],
            $row['full_name'] ?? '',
            $row['email'],
            $row['phone'] ?? ''
        ]);
    }
    
    if (in_array('dates', $fields)) {
        $data = array_merge($data, [
            date('d/m/Y H:i', strtotime($row['created_at'])),
            $row['last_login'] ? date('d/m/Y H:i', strtotime($row['last_login'])) : 'Jamais'
        ]);
    }
    
    if (in_array('orders', $fields)) {
        $data = array_merge($data, [
            $row['total_orders'],
            number_format($row['total_spent'], 2, ',', ' '),
            $row['last_order_date'] ? date('d/m/Y', strtotime($row['last_order_date'])) : 'Aucune'
        ]);
    }
    
    fputcsv($output, $data, ';');
}

fclose($output);
exit;
?>