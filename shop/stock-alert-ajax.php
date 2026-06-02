<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset('utf8');

// Create table if needed
$conn->query("CREATE TABLE IF NOT EXISTS stock_alerts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  email VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_alert (product_id, email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8");

$product_id = intval($_POST['product_id'] ?? 0);
$email      = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);

if (!$product_id || !$email) {
    echo json_encode(['ok'=>false,'msg'=>'Données invalides']);
    exit;
}

$stmt = $conn->prepare("INSERT IGNORE INTO stock_alerts (product_id, email) VALUES (?,?)");
$stmt->bind_param('is', $product_id, $email);
$stmt->execute();

// Notify admin of new alert subscription
$prod_row     = $conn->query("SELECT name FROM products WHERE id=$product_id")->fetch_assoc();
$product_name = $prod_row['name'] ?? "Produit #$product_id";
require_once __DIR__ . '/../includes/mail.php';
nc_mail_stock_alert(NC_MAIL_ADMIN, $product_name, $email);

$conn->close();

echo json_encode(['ok'=>true]);
