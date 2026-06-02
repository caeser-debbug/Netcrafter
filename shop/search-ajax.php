<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) { echo json_encode([]); exit; }

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset('utf8');

$safe = '%' . $conn->real_escape_string($q) . '%';
$sql = "SELECT p.id, p.name, p.price,
               (SELECT image_url FROM product_images WHERE product_id=p.id ORDER BY is_primary DESC LIMIT 1) as image
        FROM products p
        WHERE p.name LIKE ? OR p.description LIKE ?
        LIMIT 8";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ss', $safe, $safe);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$conn->close();

echo json_encode($rows);
