<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/db.php';

$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset('utf8');

$conn->query("CREATE TABLE IF NOT EXISTS product_reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  user_id INT DEFAULT NULL,
  name VARCHAR(100) NOT NULL,
  title VARCHAR(200) DEFAULT '',
  rating TINYINT NOT NULL DEFAULT 5,
  review TEXT NOT NULL,
  status ENUM('pending','approved') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_product (product_id),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = intval($_POST['product_id'] ?? 0);
    $name       = trim(htmlspecialchars($_POST['name']   ?? '', ENT_QUOTES));
    $title      = trim(htmlspecialchars($_POST['title']  ?? '', ENT_QUOTES));
    $rating     = max(1, min(5, intval($_POST['rating']  ?? 5)));
    $review_txt = trim(htmlspecialchars($_POST['review'] ?? '', ENT_QUOTES));

    if (!$product_id || !$name || !$review_txt) {
        echo json_encode(['ok' => false, 'msg' => 'Données manquantes']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO product_reviews (product_id, name, title, rating, review) VALUES (?,?,?,?,?)");
    $stmt->bind_param('issis', $product_id, $name, $title, $rating, $review_txt);
    echo $stmt->execute()
        ? json_encode(['ok' => true,  'msg' => 'Merci ! Votre avis sera visible après modération.'])
        : json_encode(['ok' => false, 'msg' => 'Erreur lors de l\'enregistrement.']);
} else {
    $product_id = intval($_GET['product_id'] ?? 0);
    if (!$product_id) { echo json_encode([]); exit; }

    $stmt = $conn->prepare(
        "SELECT id, name, title, rating, review, created_at
         FROM product_reviews
         WHERE product_id=? AND status='approved'
         ORDER BY created_at DESC LIMIT 20"
    );
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
}

$conn->close();
