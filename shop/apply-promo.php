<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/db.php';

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset('utf8');

// Create promo_codes table if needed
$conn->query("CREATE TABLE IF NOT EXISTS promo_codes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  discount_percent DECIMAL(5,2) DEFAULT 0,
  discount_fixed   DECIMAL(10,2) DEFAULT 0,
  min_order        DECIMAL(10,2) DEFAULT 0,
  max_uses         INT DEFAULT 0,
  uses_count       INT DEFAULT 0,
  expires_at       DATETIME DEFAULT NULL,
  active           TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8");

$code = strtoupper(trim($_POST['code'] ?? ''));
if (!$code) { echo json_encode(['ok'=>false,'msg'=>'Code vide']); exit; }

$stmt = $conn->prepare("SELECT * FROM promo_codes WHERE code=? AND active=1
  AND (expires_at IS NULL OR expires_at > NOW())
  AND (max_uses=0 OR uses_count < max_uses)");
$stmt->bind_param('s', $code);
$stmt->execute();
$promo = $stmt->get_result()->fetch_assoc();
$conn->close();

if (!$promo) {
    echo json_encode(['ok'=>false,'msg'=>'Code invalide ou expiré']);
    exit;
}

// Compute cart total to check min_order
$cart_total = array_sum(array_map(function($qty) use ($promo) {
    return $qty; // simplified — caller sends total
}, $_SESSION['cart'] ?? []));

$_SESSION['promo'] = [
    'code'    => $promo['code'],
    'percent' => (float)$promo['discount_percent'],
    'fixed'   => (float)$promo['discount_fixed'],
    'id'      => $promo['id'],
];

echo json_encode(['ok'=>true,'code'=>$promo['code'],'percent'=>$promo['discount_percent'],'fixed'=>$promo['discount_fixed']]);
