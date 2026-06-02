<?php
mysqli_report(MYSQLI_REPORT_OFF); // Prevent PHP 8.1+ from throwing exceptions on DB errors
// Initialisation de la session si elle n'est pas déjà démarrée
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Vérification de l'authentification de l'administrateur
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Enregistrement de l'URL actuelle pour la redirection après connexion
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    
    // Redirection vers la page de connexion
    header("Location: login.php");
    exit;
}

// Configuration de la base de données
require_once dirname(__DIR__, 2) . '/shop/db.php';

// Connexion à la base de données
$conn = new mysqli($servername, $username, $password, $dbname);

// Vérification de la connexion
if ($conn->connect_error) {
    die("Échec de la connexion: " . $conn->connect_error);
}
$conn->set_charset("utf8");

// Fonction pour échapper les chaînes de caractères
function escape($conn, $string) {
    return $conn->real_escape_string($string);
}

// Fonction pour générer une URL propre (slug)
function generateSlug($string) {
    // Remplacer les caractères non alphanumériques par des tirets
    $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower(trim($string)));
    // Supprimer les tirets en début et fin de chaîne
    $slug = trim($slug, '-');
    return $slug;
}

// Fonction pour formater les dates
function formatDate($date, $format = 'd/m/Y H:i') {
    return date($format, strtotime($date));
}

// Fonction pour obtenir le statut d'un produit sous forme d'un badge HTML
function getStatusBadge($status) {
    switch ($status) {
        case 'active':
            return '<span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Actif</span>';
        case 'inactive':
            return '<span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">Inactif</span>';
        case 'out_of_stock':
            return '<span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Rupture</span>';
        default:
            return '<span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Inconnu</span>';
    }
}

// Fonction pour obtenir le statut d'une commande sous forme d'un badge HTML
function getOrderStatusBadge($status) {
    switch ($status) {
        case 'pending':
            return '<span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">En attente</span>';
        case 'processing':
            return '<span class="px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">En traitement</span>';
        case 'shipped':
            return '<span class="px-2 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">Expédiée</span>';
        case 'delivered':
            return '<span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Livrée</span>';
        case 'cancelled':
            return '<span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Annulée</span>';
        case 'returned':
            return '<span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Retournée</span>';
        default:
            return '<span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Inconnu</span>';
    }
}

// Fonction pour obtenir le statut de paiement sous forme d'un badge HTML
function getPaymentStatusBadge($status) {
    switch ($status) {
        case 'pending':
            return '<span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">En attente</span>';
        case 'paid':
            return '<span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Payé</span>';
        case 'failed':
            return '<span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Échoué</span>';
        case 'refunded':
            return '<span class="px-2 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">Remboursé</span>';
        default:
            return '<span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Inconnu</span>';
    }
}

// Fonction pour tronquer un texte avec une longueur maximum
function truncateText($text, $maxLength = 100) {
    if (strlen($text) > $maxLength) {
        $text = substr($text, 0, $maxLength) . '...';
    }
    return $text;
}

// Fonction pour télécharger et sauvegarder une image
function uploadImage($file, $directory = '../uploads/products/') {
    // Vérifier si le répertoire existe, le créer si nécessaire
    if (!file_exists($directory)) {
        mkdir($directory, 0777, true);
    }
    
    // Générer un nom de fichier unique
    $filename = uniqid() . '_' . basename($file['name']);
    $target_file = $directory . $filename;
    
    // Extensions autorisées
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Vérification de l'extension
    if (!in_array($file_extension, $allowed_extensions)) {
        return [
            'success' => false,
            'message' => 'Extension de fichier non autorisée. Veuillez utiliser JPG, JPEG, PNG, GIF ou WEBP.'
        ];
    }
    
    // Vérification de la taille (maximum 2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        return [
            'success' => false,
            'message' => 'La taille du fichier ne doit pas dépasser 2MB.'
        ];
    }
    
    // Déplacer le fichier téléchargé vers le répertoire cible
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return [
            'success' => true,
            'file_path' => substr($target_file, 3), // Enlever '../' du chemin
            'message' => 'Image téléchargée avec succès.'
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Une erreur est survenue lors du téléchargement de l\'image.'
        ];
    }
}

// Récupérer le nombre de commandes en attente
function getPendingOrdersCount($conn) {
    $sql = "SELECT COUNT(*) as count FROM orders WHERE order_status = 'pending'";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['count'];
    }
    return 0;
}

// Récupérer le nombre de produits en rupture de stock
function getOutOfStockProductsCount($conn) {
    $sql = "SELECT COUNT(*) as count FROM products WHERE stock = 0 OR status = 'out_of_stock'";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['count'];
    }
    return 0;
}

// Récupérer le nombre total de clients
function getTotalCustomersCount($conn) {
    $sql = "SELECT COUNT(*) as count FROM users WHERE is_admin = 0";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['count'];
    }
    return 0;
}

// Récupérer le chiffre d'affaires total
function getTotalRevenue($conn) {
    $sql = "SELECT SUM(total_amount) as total FROM orders WHERE payment_status = 'paid'";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['total'] ? $row['total'] : 0;
    }
    return 0;
}

// Variables communes pour la navigation active
$current_page = basename($_SERVER['PHP_SELF']);
$pending_orders_count = getPendingOrdersCount($conn);
$out_of_stock_products_count = getOutOfStockProductsCount($conn);
?>