<?php
// Initialisation de la session pour le panier et les favoris
session_start();

require_once __DIR__ . '/db.php';

// Connexion à la base de données avec gestion d'erreurs améliorée
try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    // Vérification de la connexion
    if ($conn->connect_error) {
        error_log("Échec de la connexion à la base de données: " . $conn->connect_error);
        die("Erreur de connexion à la base de données. Veuillez réessayer plus tard.");
    }
    
    $conn->set_charset("utf8");
    
} catch (Exception $e) {
    error_log("Erreur de connexion DB: " . $e->getMessage());
    die("Erreur de connexion à la base de données. Veuillez réessayer plus tard.");
}

// Fonction pour obtenir l'adresse MAC (simplifiée et sécurisée)
function getMacAddress() {
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

// Initialisation du panier et des favoris s'ils n'existent pas
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
if (!isset($_SESSION['favorites'])) {
    $_SESSION['favorites'] = [];
}

// Enregistrement de la session (version simplifiée pour éviter les erreurs)
if (!isset($_SESSION['mac_address'])) {
    $_SESSION['mac_address'] = getMacAddress();
    
    // Enregistrement optionnel en base (peut être désactivé si problématique)
    try {
        $session_id = session_id();
        $mac_address = $_SESSION['mac_address'];
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        // Vérifier si la table sessions existe
        $check_table = "SHOW TABLES LIKE 'sessions'";
        $table_result = $conn->query($check_table);
        
        if ($table_result && $table_result->num_rows > 0) {
            // Vérifier si cette session existe déjà
            $check_session = "SELECT id FROM sessions WHERE id = ?";
            $stmt = $conn->prepare($check_session);
            if ($stmt) {
                $stmt->bind_param("s", $session_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows == 0) {
                    // Insérer la nouvelle session
                    $insert_session = "INSERT INTO sessions (id, ip_address, mac_address, data) VALUES (?, ?, ?, ?)";
                    $session_data = json_encode(['cart' => $_SESSION['cart'], 'favorites' => $_SESSION['favorites']]);
                    
                    $stmt2 = $conn->prepare($insert_session);
                    if ($stmt2) {
                        $stmt2->bind_param("ssss", $session_id, $ip_address, $mac_address, $session_data);
                        $stmt2->execute();
                        $stmt2->close();
                    }
                }
                $stmt->close();
            }
        }
    } catch (Exception $e) {
        error_log("Erreur lors de l'enregistrement de session: " . $e->getMessage());
        // Continue sans arrêter l'exécution
    }
}

// Traitement des actions (ajout au panier, ajout aux favoris, etc.)
if (isset($_POST['action']) && isset($_POST['product_id'])) {
    $product_id = intval($_POST['product_id']);
    
    if ($_POST['action'] === 'add_to_cart' && $product_id > 0) {
        // Ajouter au panier
        if (array_key_exists($product_id, $_SESSION['cart'])) {
            $_SESSION['cart'][$product_id]++;
        } else {
            $_SESSION['cart'][$product_id] = 1;
        }
        
        // Mettre à jour la session dans la base de données (optionnel)
        try {
            $session_id = session_id();
            $session_data = json_encode(['cart' => $_SESSION['cart'], 'favorites' => $_SESSION['favorites']]);
            
            $update_session = "UPDATE sessions SET data = ? WHERE id = ?";
            $stmt = $conn->prepare($update_session);
            if ($stmt) {
                $stmt->bind_param("ss", $session_data, $session_id);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Exception $e) {
            error_log("Erreur mise à jour session: " . $e->getMessage());
        }
        
        header("Location: shop.php?cart_added=1");
        exit;
    } 
    elseif ($_POST['action'] === 'add_to_favorites' && $product_id > 0) {
        // Ajouter aux favoris
        if (!in_array($product_id, $_SESSION['favorites'])) {
            $_SESSION['favorites'][] = $product_id;
            
            // Mettre à jour la session dans la base de données (optionnel)
            try {
                $session_id = session_id();
                $session_data = json_encode(['cart' => $_SESSION['cart'], 'favorites' => $_SESSION['favorites']]);
                
                $update_session = "UPDATE sessions SET data = ? WHERE id = ?";
                $stmt = $conn->prepare($update_session);
                if ($stmt) {
                    $stmt->bind_param("ss", $session_data, $session_id);
                    $stmt->execute();
                    $stmt->close();
                }
            } catch (Exception $e) {
                error_log("Erreur mise à jour session: " . $e->getMessage());
            }
        }
        header("Location: shop.php?fav_added=1");
        exit;
    }
    elseif ($_POST['action'] === 'remove_from_favorites' && $product_id > 0) {
        // Supprimer des favoris
        if (($key = array_search($product_id, $_SESSION['favorites'])) !== false) {
            unset($_SESSION['favorites'][$key]);
            $_SESSION['favorites'] = array_values($_SESSION['favorites']); // Réindexer le tableau
            
            // Mettre à jour la session dans la base de données (optionnel)
            try {
                $session_id = session_id();
                $session_data = json_encode(['cart' => $_SESSION['cart'], 'favorites' => $_SESSION['favorites']]);
                
                $update_session = "UPDATE sessions SET data = ? WHERE id = ?";
                $stmt = $conn->prepare($update_session);
                if ($stmt) {
                    $stmt->bind_param("ss", $session_data, $session_id);
                    $stmt->execute();
                    $stmt->close();
                }
            } catch (Exception $e) {
                error_log("Erreur mise à jour session: " . $e->getMessage());
            }
        }
        header("Location: shop.php?fav_removed=1");
        exit;
    }
    elseif ($_POST['action'] === 'remove_from_cart' && $product_id > 0) {
        // Supprimer du panier
        if (array_key_exists($product_id, $_SESSION['cart'])) {
            unset($_SESSION['cart'][$product_id]);
            
            // Mettre à jour la session dans la base de données (optionnel)
            try {
                $session_id = session_id();
                $session_data = json_encode(['cart' => $_SESSION['cart'], 'favorites' => $_SESSION['favorites']]);
                
                $update_session = "UPDATE sessions SET data = ? WHERE id = ?";
                $stmt = $conn->prepare($update_session);
                if ($stmt) {
                    $stmt->bind_param("ss", $session_data, $session_id);
                    $stmt->execute();
                    $stmt->close();
                }
            } catch (Exception $e) {
                error_log("Erreur mise à jour session: " . $e->getMessage());
            }
        }
        header("Location: shop.php?cart_removed=1");
        exit;
    }
}

// Récupération des catégories avec gestion d'erreurs
$categories = [];
try {
    // Vérifier si la table categories existe
    $check_categories_table = "SHOW TABLES LIKE 'categories'";
    $cat_table_result = $conn->query($check_categories_table);
    
    if ($cat_table_result && $cat_table_result->num_rows > 0) {
        $categories_query = "SELECT * FROM categories ORDER BY name";
        $categories_result = $conn->query($categories_query);
        
        if ($categories_result && $categories_result->num_rows > 0) {
            while ($row = $categories_result->fetch_assoc()) {
                $categories[] = $row;
            }
        }
    }
} catch (Exception $e) {
    error_log("Erreur récupération catégories: " . $e->getMessage());
}

// Paramètres de filtrage et de recherche avec validation
$category_id = isset($_GET['category']) ? max(0, intval($_GET['category'])) : 0;
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$min_price = isset($_GET['min_price']) ? max(0, floatval($_GET['min_price'])) : 0;
$max_price = isset($_GET['max_price']) ? max(0, floatval($_GET['max_price'])) : 1000000;
$sort_by = isset($_GET['sort_by']) && in_array($_GET['sort_by'], ['newest', 'price_low', 'price_high', 'popular']) ? $_GET['sort_by'] : 'newest';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$products_per_page = 12;

// Construction de la requête SQL de base avec vérification de l'existence des tables
$products = [];
$total_products = 0;
$total_pages = 1;

try {
    // Vérifier que les tables existent
    $check_tables = "SHOW TABLES LIKE 'products'";
    $table_result = $conn->query($check_tables);
    
    if (!$table_result || $table_result->num_rows == 0) {
        throw new Exception("La table 'products' n'existe pas");
    }

    // Vérifier la structure de la table products
    $describe_products = "DESCRIBE products";
    $desc_result = $conn->query($describe_products);
    $columns = [];
    if ($desc_result) {
        while ($row = $desc_result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
    }

    // Construction de la requête SQL de base - Version simplifiée d'abord
    $sql = "SELECT p.*";
    
    // Ajouter le nom de catégorie seulement si la table categories existe et que la colonne category_id existe
    if (!empty($categories) && in_array('category_id', $columns)) {
        $sql .= ", c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id";
    } else {
        $sql .= " FROM products p";
    }
    
    // Conditions de base
    $conditions = [];
    $params = [];
    $types = "";

    // Condition de statut (si la colonne existe)
    if (in_array('status', $columns)) {
        $conditions[] = "p.status = ?";
        $params[] = 'active';
        $types .= "s";
    }

    // Condition de prix
    if (in_array('price', $columns)) {
        $conditions[] = "p.price >= ? AND p.price <= ?";
        $params[] = $min_price;
        $params[] = $max_price;
        $types .= "dd";
    }

    // Ajout des conditions de filtrage
    if ($category_id > 0 && in_array('category_id', $columns)) {
        $conditions[] = "p.category_id = ?";
        $params[] = $category_id;
        $types .= "i";
    }

    if (!empty($search_term)) {
        $search_conditions = [];
        if (in_array('name', $columns)) {
            $search_conditions[] = "p.name LIKE ?";
            $params[] = "%{$search_term}%";
            $types .= "s";
        }
        if (in_array('description', $columns)) {
            $search_conditions[] = "p.description LIKE ?";
            $params[] = "%{$search_term}%";
            $types .= "s";
        }
        if (!empty($search_conditions)) {
            $conditions[] = "(" . implode(" OR ", $search_conditions) . ")";
        }
    }

    // Construire la clause WHERE
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    // Comptage du nombre total de produits pour la pagination
    $count_sql = preg_replace('/SELECT p\.\*.*?FROM/i', 'SELECT COUNT(*) as total FROM', $sql);
    
    if (!empty($params)) {
        $stmt = $conn->prepare($count_sql);
        if ($stmt) {
            if (!empty($types)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $count_result = $stmt->get_result();
            
            if ($count_result) {
                $total_products = $count_result->fetch_assoc()['total'] ?? 0;
                $total_pages = ceil($total_products / $products_per_page);
            }
            $stmt->close();
        }
    } else {
        $count_result = $conn->query($count_sql);
        if ($count_result) {
            $total_products = $count_result->fetch_assoc()['total'] ?? 0;
            $total_pages = ceil($total_products / $products_per_page);
        }
    }

    // Tri des résultats
    if (in_array('created_at', $columns) || in_array('price', $columns) || in_array('views', $columns)) {
        switch ($sort_by) {
            case 'price_low':
                if (in_array('price', $columns)) {
                    $sql .= " ORDER BY p.price ASC";
                }
                break;
            case 'price_high':
                if (in_array('price', $columns)) {
                    $sql .= " ORDER BY p.price DESC";
                }
                break;
            case 'popular':
                if (in_array('views', $columns)) {
                    $sql .= " ORDER BY p.views DESC";
                } else {
                    $sql .= " ORDER BY p.id DESC";
                }
                break;
            case 'newest':
            default:
                if (in_array('created_at', $columns)) {
                    $sql .= " ORDER BY p.created_at DESC";
                } else {
                    $sql .= " ORDER BY p.id DESC";
                }
                break;
        }
    } else {
        $sql .= " ORDER BY p.id DESC";
    }

    // Ajout de la pagination
    $offset = ($page - 1) * $products_per_page;
    $sql .= " LIMIT ?, ?";
    $params[] = $offset;
    $params[] = $products_per_page;
    $types .= "ii";

    // Exécution de la requête
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            if (!empty($types)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    // Récupération des images pour chaque produit
                    $product_id = $row['id'];
                    $images = [];
                    
                    try {
                        // Vérifier si la table product_images existe
                        $check_images_table = "SHOW TABLES LIKE 'product_images'";
                        $img_table_result = $conn->query($check_images_table);
                        
                        if ($img_table_result && $img_table_result->num_rows > 0) {
                            $images_query = "SELECT image_url FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, display_order ASC";
                            $img_stmt = $conn->prepare($images_query);
                            
                            if ($img_stmt) {
                                $img_stmt->bind_param("i", $product_id);
                                $img_stmt->execute();
                                $images_result = $img_stmt->get_result();
                                
                                if ($images_result && $images_result->num_rows > 0) {
                                    while ($img_row = $images_result->fetch_assoc()) {
                                        $images[] = $img_row['image_url'];
                                    }
                                }
                                $img_stmt->close();
                            }
                        } else {
                            // Si pas de table product_images, utiliser une colonne image dans products
                            if (isset($row['image']) && !empty($row['image'])) {
                                $images[] = $row['image'];
                            } elseif (isset($row['image_url']) && !empty($row['image_url'])) {
                                $images[] = $row['image_url'];
                            }
                        }
                    } catch (Exception $e) {
                        error_log("Erreur récupération images produit {$product_id}: " . $e->getMessage());
                    }
                    
                    $row['images'] = $images;
                    $products[] = $row;
                }
            }
            $stmt->close();
        }
    } else {
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $row['images'] = [];
                $products[] = $row;
            }
        }
    }

} catch (Exception $e) {
    error_log("Erreur lors de la récupération des produits: " . $e->getMessage());
    // Afficher un message d'erreur en mode développement
    if (isset($_GET['debug'])) {
        echo "<div class='debug-info'>Erreur: " . $e->getMessage() . "</div>";
    }
}

// Obtenir les prix min et max pour les filtres
$price_range = ['min_price' => 0, 'max_price' => 1000000];
try {
    $price_range_query = "SELECT MIN(price) as min_price, MAX(price) as max_price FROM products";
    
    // Ajouter condition status si elle existe
    if (in_array('status', $columns ?? [])) {
        $price_range_query .= " WHERE status = 'active'";
    }
    
    $price_range_result = $conn->query($price_range_query);
    
    if ($price_range_result && $price_range_result->num_rows > 0) {
        $price_data = $price_range_result->fetch_assoc();
        $price_range['min_price'] = $price_data['min_price'] ?? 0;
        $price_range['max_price'] = $price_data['max_price'] ?? 1000000;
    }
} catch (Exception $e) {
    error_log("Erreur récupération prix range: " . $e->getMessage());
}

// Suggestion de produits populaires (version simplifiée)
$popular_products = [];
try {
    $popular_sql = "SELECT p.*";
    
    // Ajouter le nom de catégorie seulement si la table categories existe
    if (!empty($categories) && in_array('category_id', $columns ?? [])) {
        $popular_sql .= ", c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id";
    } else {
        $popular_sql .= " FROM products p";
    }
    
    $popular_conditions = [];
    if (in_array('status', $columns ?? [])) {
        $popular_conditions[] = "p.status = 'active'";
    }
    
    if (!empty($popular_conditions)) {
        $popular_sql .= " WHERE " . implode(" AND ", $popular_conditions);
    }
    
    // Ordre par popularité ou par ID décroissant
    if (in_array('views', $columns ?? [])) {
        $popular_sql .= " ORDER BY p.views DESC";
    } else {
        $popular_sql .= " ORDER BY p.id DESC";
    }
    
    $popular_sql .= " LIMIT 6";
    
    $popular_result = $conn->query($popular_sql);

    if ($popular_result && $popular_result->num_rows > 0) {
        while ($row = $popular_result->fetch_assoc()) {
            $product_id = $row['id'];
            $images = [];
            
            try {
                // Vérifier si la table product_images existe
                $check_images_table = "SHOW TABLES LIKE 'product_images'";
                $img_table_result = $conn->query($check_images_table);
                
                if ($img_table_result && $img_table_result->num_rows > 0) {
                    $images_query = "SELECT image_url FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, display_order ASC LIMIT 1";
                    $img_stmt = $conn->prepare($images_query);
                    
                    if ($img_stmt) {
                        $img_stmt->bind_param("i", $product_id);
                        $img_stmt->execute();
                        $images_result = $img_stmt->get_result();
                        
                        if ($images_result && $images_result->num_rows > 0) {
                            while ($img_row = $images_result->fetch_assoc()) {
                                $images[] = $img_row['image_url'];
                            }
                        }
                        $img_stmt->close();
                    }
                } else {
                    // Si pas de table product_images, utiliser une colonne image dans products
                    if (isset($row['image']) && !empty($row['image'])) {
                        $images[] = $row['image'];
                    } elseif (isset($row['image_url']) && !empty($row['image_url'])) {
                        $images[] = $row['image_url'];
                    }
                }
            } catch (Exception $e) {
                error_log("Erreur récupération images produit populaire {$product_id}: " . $e->getMessage());
            }
            
            $row['images'] = $images;
            $popular_products[] = $row;
        }
    }
} catch (Exception $e) {
    error_log("Erreur récupération produits populaires: " . $e->getMessage());
}

// Debug: Afficher les informations de debug si demandé
if (isset($_GET['debug'])) {
    echo "<div class='debug-info'>";
    echo "<strong>Debug Info:</strong><br>";
    echo "Nombre de produits trouvés: " . count($products) . "<br>";
    echo "Total produits en base: " . $total_products . "<br>";
    echo "SQL généré: " . $sql . "<br>";
    echo "Paramètres: " . print_r($params ?? [], true) . "<br>";
    echo "Types: " . $types . "<br>";
    echo "Colonnes disponibles: " . implode(', ', $columns ?? []) . "<br>";
    echo "Nombre de catégories: " . count($categories) . "<br>";
    echo "Nombre de produits populaires: " . count($popular_products) . "<br>";
    if (!empty($products)) {
        echo "Premier produit: " . print_r($products[0], true) . "<br>";
    }
    echo "</div>";
}
?>
<?php
$page_title    = 'Netcrafter - Boutique en ligne';
$page_keywords = 'boutique en ligne Niger, acheter produits tech Niamey, e-commerce Niger, shop Netcrafter, matériel informatique Niger, gadgets tech Niamey';
include '../includes/header.php';
?>
<!-- Swiper Slider -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.css" />
<script src="https://cdn.jsdelivr.net/npm/swiper@9/swiper-bundle.min.js"></script>
<!-- Fallback (head was already output by header.php, these load fine in body too) -->
<style>
    /* ── Shop-specific styles ──────────────────────────────── */
    .product-card {
        background: rgba(10,24,58,0.6);
        border: 1px solid rgba(0,200,255,0.1);
        border-radius: 16px;
        transition: all 0.3s ease;
        overflow: hidden;
    }
    .product-card:hover {
        background: rgba(10,24,58,0.9);
        border-color: rgba(0,200,255,0.4);
        transform: translateY(-4px);
        box-shadow: 0 20px 40px rgba(0,0,0,0.5), 0 0 20px rgba(0,200,255,0.1);
    }
    .shop-card {
        background: rgba(10,24,58,0.6);
        border: 1px solid rgba(0,200,255,0.12);
        border-radius: 16px;
    }
    .shop-card:hover { border-color: rgba(0,200,255,0.3); }
    .swiper { width:100%; height:100%; }
    .swiper-slide { display:flex; justify-content:center; align-items:center; }
    .swiper-slide img { display:block; width:100%; height:100%; object-fit:cover; }
    .swiper-pagination-bullet-active { background-color:#00c8ff !important; }
    .price-slider {
        width:100%; height:4px; border-radius:4px;
        background: rgba(0,200,255,0.2); outline:none; -webkit-appearance:none; appearance:none;
    }
    .price-slider::-webkit-slider-thumb {
        -webkit-appearance:none; appearance:none; width:16px; height:16px;
        border-radius:50%; background:#00c8ff; cursor:pointer;
        box-shadow: 0 0 8px rgba(0,200,255,0.5);
    }
    .price-slider::-moz-range-thumb {
        width:16px; height:16px; border-radius:50%;
        background:#00c8ff; cursor:pointer; border:none;
    }
    .shop-input {
        background: rgba(10,24,58,0.8);
        border: 1px solid rgba(0,200,255,0.2);
        color: #fff;
        border-radius: 10px;
        padding: 10px 14px;
        width: 100%;
        outline: none;
        transition: border-color 0.2s;
    }
    .shop-input:focus { border-color: rgba(0,200,255,0.6); }
    .shop-input::placeholder { color: #64748b; }
    .shop-select {
        background: rgba(10,24,58,0.8);
        border: 1px solid rgba(0,200,255,0.2);
        color: #fff;
        border-radius: 10px;
        padding: 10px 14px;
        width: 100%;
        outline: none;
        cursor: pointer;
    }
    .shop-select option { background: #0a1835; }
    .btn-secondary {
        background: rgba(0,200,255,0.08);
        border: 1px solid rgba(0,200,255,0.25);
        color: #94a3b8;
        border-radius: 10px;
        padding: 10px 20px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        text-decoration: none;
    }
    .btn-secondary:hover { border-color: rgba(0,200,255,0.5); color: #00c8ff; }
    .cat-badge {
        background: linear-gradient(135deg,rgba(0,200,255,0.9),rgba(0,102,204,0.9));
        color: #fff; font-size: 0.7rem; font-weight: 700;
        padding: 3px 8px; border-radius: 6px;
    }
    .price-tag { color: #00c8ff; font-weight: 800; font-size: 1.1rem; }
    .debug-info {
        background:#fee; border:1px solid #fcc; color:#900;
        padding:10px; margin:10px 0; border-radius:4px;
        font-family:monospace; font-size:12px; white-space:pre-wrap;
    }
    .line-clamp-1 { display:-webkit-box; -webkit-line-clamp:1; line-clamp:1; -webkit-box-orient:vertical; overflow:hidden; }
    .line-clamp-2 { display:-webkit-box; -webkit-line-clamp:2; line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
    .radio-nc { accent-color: #00c8ff; }
    .pagination-btn {
        padding: 8px 14px;
        border: 1px solid rgba(0,200,255,0.2);
        color: #94a3b8;
        border-radius: 8px;
        transition: all 0.2s;
        font-size: 0.875rem;
    }
    .pagination-btn:hover { border-color: rgba(0,200,255,0.5); color: #00c8ff; }
    .pagination-btn.active {
        background: linear-gradient(135deg,#00c8ff,#0066cc);
        color: #fff; border-color: transparent;
    }
</style>

<!-- Cart & Favorites nav icons (appended to existing navbar via JS) -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const desktopRight = document.querySelector('#navbar .hidden.md\\:flex');
    if (desktopRight) {
        const cartIcons = document.createElement('div');
        cartIcons.className = 'flex items-center gap-4 mr-4';
        cartIcons.innerHTML = `
            <a href="favorites.php" class="relative text-gray-400 hover:text-nc-cyan transition-colors" title="Favoris">
                <i class="fas fa-heart text-lg"></i>
                <?php if (count($_SESSION['favorites']) > 0): ?>
                <span class="absolute -top-2 -right-2 text-white text-xs rounded-full h-4 w-4 flex items-center justify-center font-bold" style="background:#00c8ff;font-size:0.6rem"><?= count($_SESSION['favorites']) ?></span>
                <?php endif; ?>
            </a>
            <a href="cart.php" class="relative text-gray-400 hover:text-nc-cyan transition-colors" title="Panier">
                <i class="fas fa-shopping-cart text-lg"></i>
                <?php if (count($_SESSION['cart']) > 0): ?>
                <span class="absolute -top-2 -right-2 text-white text-xs rounded-full h-4 w-4 flex items-center justify-center font-bold" style="background:#00c8ff;font-size:0.6rem"><?= array_sum($_SESSION['cart']) ?></span>
                <?php endif; ?>
            </a>
        `;
        desktopRight.insertBefore(cartIcons, desktopRight.querySelector('a.btn-primary'));
    }
    // Mobile cart icons
    const mobileToggle = document.getElementById('mobile-toggle');
    if (mobileToggle) {
        const mobileCartWrap = document.createElement('div');
        mobileCartWrap.className = 'flex items-center gap-3 mr-2';
        mobileCartWrap.innerHTML = `
            <a href="favorites.php" class="relative text-gray-400"><i class="fas fa-heart text-lg"></i><?php if(count($_SESSION['favorites'])>0): ?><span class="absolute -top-1.5 -right-1.5 text-white text-xs rounded-full h-4 w-4 flex items-center justify-center" style="background:#00c8ff;font-size:0.6rem"><?= count($_SESSION['favorites']) ?></span><?php endif; ?></a>
            <a href="cart.php" class="relative text-gray-400"><i class="fas fa-shopping-cart text-lg"></i><?php if(count($_SESSION['cart'])>0): ?><span class="absolute -top-1.5 -right-1.5 text-white text-xs rounded-full h-4 w-4 flex items-center justify-center" style="background:#00c8ff;font-size:0.6rem"><?= array_sum($_SESSION['cart']) ?></span><?php endif; ?></a>
        `;
        mobileToggle.parentNode.insertBefore(mobileCartWrap, mobileToggle);
    }
});
</script>

    <!-- Page Header -->
    <section class="relative pt-32 pb-16 overflow-hidden">
        <div class="blob bg-nc-cyan" style="width:400px;height:400px;top:-150px;left:-150px;"></div>
        <div class="blob bg-nc-blue" style="width:350px;height:350px;bottom:-100px;right:-100px;animation-delay:2s;"></div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center mb-10" data-aos="fade-up">
                <div class="badge mb-4 mx-auto"><i class="fas fa-shopping-bag"></i> <?= t('shop.title') ?></div>
                <h1 class="font-heading font-bold text-4xl md:text-6xl text-white mb-4">
                    <span class="gradient-text">Netcrafter <?= t('shop.title') ?></span>
                </h1>
                <p class="text-gray-400 text-lg max-w-2xl mx-auto">
                    <?= t('shop.subtitle') ?>
                </p>
            </div>

            <!-- Search Bar -->
            <div class="max-w-3xl mx-auto" data-aos="fade-up" data-aos-delay="100">
                <form action="shop.php" method="GET" class="flex flex-col sm:flex-row gap-3">
                    <div class="flex-1 relative">
                        <input type="text" name="search" id="live-search-input"
                               value="<?= htmlspecialchars($search_term) ?>"
                               placeholder="<?= t('shop.search') ?>" autocomplete="off"
                               class="shop-input pl-10">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-500"></i>
                        <div id="live-search-dropdown"
                             class="hidden absolute top-full left-0 right-0 z-50 mt-1 rounded-xl shadow-2xl border border-white/10 overflow-hidden"
                             style="background:rgba(6,13,30,0.97);backdrop-filter:blur(20px)"></div>
                    </div>
                    <button type="submit" class="btn-primary py-2.5 px-6 text-sm">
                        <i class="fas fa-search"></i> Rechercher
                    </button>
                    <button type="button" id="filter-toggle" class="sm:hidden btn-secondary">
                        <i class="fas fa-filter"></i> Filtres
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- Main Shop Section -->
    <section class="py-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col lg:flex-row gap-8">

            <!-- Filter Sidebar - Desktop -->
            <div class="lg:w-1/4 hidden lg:block">
                <div class="shop-card p-6 sticky top-24" data-aos="fade-right">
                    <h3 class="font-heading font-bold text-lg text-white mb-6 flex items-center gap-2">
                        <i class="fas fa-sliders-h text-sm" style="color:#00c8ff"></i> <?= t('shop.filters') ?>
                    </h3>
                    <form action="shop.php" method="GET" id="filter-form">
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search_term) ?>">

                        <!-- Categories -->
                        <div class="mb-6">
                            <h4 class="text-sm font-semibold uppercase tracking-widest text-gray-500 mb-3"><?= t('shop.category') ?></h4>
                            <div class="space-y-2">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" class="radio-nc" id="cat_all" name="category" value="0" <?= $category_id==0?'checked':'' ?>>
                                    <span class="text-gray-300 text-sm"><?= t('shop.all_cats') ?></span>
                                </label>
                                <?php foreach ($categories as $cat): ?>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" class="radio-nc" id="cat_<?= $cat['id'] ?>" name="category" value="<?= $cat['id'] ?>" <?= $category_id==$cat['id']?'checked':'' ?>>
                                    <span class="text-gray-300 text-sm"><?= htmlspecialchars($cat['name']) ?></span>
                                </label>
                                <?php endforeach; ?>
                                <?php if (empty($categories)): ?>
                                <p class="text-gray-600 text-sm">Aucune catégorie disponible</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Price Range -->
                        <div class="mb-6">
                            <h4 class="text-sm font-semibold uppercase tracking-widest text-gray-500 mb-3">Prix</h4>
                            <div class="flex justify-between text-xs text-gray-400 mb-2">
                                <span>Min : <span id="min-value"><?= number_format($min_price,0) ?></span> FCFA</span>
                                <span>Max : <span id="max-value"><?= number_format($max_price,0) ?></span> FCFA</span>
                            </div>
                            <input type="range" id="min_price_slider" min="<?= $price_range['min_price'] ?>" max="<?= $price_range['max_price'] ?>" value="<?= $min_price ?>" class="price-slider mb-2">
                            <input type="range" id="max_price_slider" min="<?= $price_range['min_price'] ?>" max="<?= $price_range['max_price'] ?>" value="<?= $max_price ?>" class="price-slider">
                            <input type="hidden" id="min_price" name="min_price" value="<?= $min_price ?>">
                            <input type="hidden" id="max_price" name="max_price" value="<?= $max_price ?>">
                        </div>

                        <!-- Sort -->
                        <div class="mb-6">
                            <h4 class="text-sm font-semibold uppercase tracking-widest text-gray-500 mb-3"><?= t('shop.sort') ?></h4>
                            <select name="sort_by" class="shop-select">
                                <option value="newest"     <?= $sort_by=='newest'?'selected':'' ?>><?= t('shop.newest') ?></option>
                                <option value="price_low"  <?= $sort_by=='price_low'?'selected':'' ?>><?= t('shop.price_asc') ?></option>
                                <option value="price_high" <?= $sort_by=='price_high'?'selected':'' ?>><?= t('shop.price_desc') ?></option>
                                <option value="popular"    <?= $sort_by=='popular'?'selected':'' ?>><?= t('shop.newest') ?></option>
                            </select>
                        </div>

                        <button type="submit" class="btn-primary w-full justify-center py-2.5 text-sm mb-3">
                            <i class="fas fa-filter"></i> Appliquer
                        </button>
                        <a href="shop.php" class="btn-secondary w-full justify-center py-2.5 text-sm">
                            <i class="fas fa-undo"></i> Réinitialiser
                        </a>
                    </form>
                </div>
            </div>

            <!-- Mobile Filter Drawer -->
            <div id="mobile-filter-menu" class="fixed inset-0 z-50 transform translate-x-full transition-transform duration-300 lg:hidden overflow-y-auto" style="background:rgba(6,13,30,0.98)">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-heading font-bold text-xl text-white">Filtres</h3>
                        <button id="close-filter-menu" class="text-gray-400 hover:text-white text-xl"><i class="fas fa-times"></i></button>
                    </div>
                    <form action="shop.php" method="GET">
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search_term) ?>">
                        <div class="mb-8">
                            <h4 class="text-sm font-semibold uppercase tracking-widest mb-4" style="color:#00c8ff">Catégories</h4>
                            <div class="space-y-3">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="radio" class="radio-nc h-5 w-5" id="mobile_cat_all" name="category" value="0" <?= $category_id==0?'checked':'' ?>>
                                    <span class="text-gray-300">Toutes les catégories</span>
                                </label>
                                <?php foreach ($categories as $cat): ?>
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="radio" class="radio-nc h-5 w-5" id="mobile_cat_<?= $cat['id'] ?>" name="category" value="<?= $cat['id'] ?>" <?= $category_id==$cat['id']?'checked':'' ?>>
                                    <span class="text-gray-300"><?= htmlspecialchars($cat['name']) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="mb-8">
                            <h4 class="text-sm font-semibold uppercase tracking-widest mb-4" style="color:#00c8ff">Prix</h4>
                            <div class="flex justify-between text-sm text-gray-400 mb-3">
                                <span>Min : <span id="mobile-min-value"><?= number_format($min_price,0) ?></span> FCFA</span>
                                <span>Max : <span id="mobile-max-value"><?= number_format($max_price,0) ?></span> FCFA</span>
                            </div>
                            <input type="range" id="mobile_min_price_slider" min="<?= $price_range['min_price'] ?>" max="<?= $price_range['max_price'] ?>" value="<?= $min_price ?>" class="price-slider mb-3">
                            <input type="range" id="mobile_max_price_slider" min="<?= $price_range['min_price'] ?>" max="<?= $price_range['max_price'] ?>" value="<?= $max_price ?>" class="price-slider">
                            <input type="hidden" id="mobile_min_price" name="min_price" value="<?= $min_price ?>">
                            <input type="hidden" id="mobile_max_price" name="max_price" value="<?= $max_price ?>">
                        </div>
                        <div class="mb-8">
                            <h4 class="text-sm font-semibold uppercase tracking-widest mb-4" style="color:#00c8ff">Trier par</h4>
                            <select name="sort_by" class="shop-select">
                                <option value="newest"     <?= $sort_by=='newest'?'selected':'' ?>>Plus récents</option>
                                <option value="price_low"  <?= $sort_by=='price_low'?'selected':'' ?>>Prix croissant</option>
                                <option value="price_high" <?= $sort_by=='price_high'?'selected':'' ?>>Prix décroissant</option>
                                <option value="popular"    <?= $sort_by=='popular'?'selected':'' ?>>Popularité</option>
                            </select>
                        </div>
                        <button type="submit" class="btn-primary w-full justify-center py-3 mb-4">Appliquer les filtres</button>
                        <a href="shop.php" class="btn-secondary w-full justify-center py-3">Réinitialiser</a>
                    </form>
                </div>
            </div>
                
            <!-- Products Grid -->
            <div class="lg:w-3/4">
                <!-- Results Summary -->
                <div class="shop-card flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 p-4" data-aos="fade-up">
                    <div>
                        <h2 class="font-heading font-bold text-lg text-white">
                            <?php if (!empty($search_term)): ?>
                                Résultats pour "<?= htmlspecialchars($search_term) ?>"
                            <?php elseif ($category_id > 0):
                                $current_category = '';
                                foreach ($categories as $cat) { if ($cat['id']==$category_id) { $current_category=$cat['name']; break; } }
                            ?>
                                Catégorie : <?= htmlspecialchars($current_category) ?>
                            <?php else: ?>
                                Tous nos produits
                            <?php endif; ?>
                        </h2>
                        <p class="text-gray-500 text-sm mt-1"><?= $total_products ?> produit<?= $total_products>1?'s':'' ?> trouvé<?= $total_products>1?'s':'' ?></p>
                    </div>
                    <!-- Sort - mobile/tablet -->
                    <div class="mt-3 sm:mt-0 w-full sm:w-auto lg:hidden">
                        <form action="shop.php" method="GET" class="flex">
                            <input type="hidden" name="search"    value="<?= htmlspecialchars($search_term) ?>">
                            <input type="hidden" name="category"  value="<?= $category_id ?>">
                            <input type="hidden" name="min_price" value="<?= $min_price ?>">
                            <input type="hidden" name="max_price" value="<?= $max_price ?>">
                            <select name="sort_by" onchange="this.form.submit()" class="shop-select">
                                <option value="newest"     <?= $sort_by=='newest'?'selected':'' ?>>Plus récents</option>
                                <option value="price_low"  <?= $sort_by=='price_low'?'selected':'' ?>>Prix croissant</option>
                                <option value="price_high" <?= $sort_by=='price_high'?'selected':'' ?>>Prix décroissant</option>
                                <option value="popular"    <?= $sort_by=='popular'?'selected':'' ?>>Popularité</option>
                            </select>
                        </form>
                    </div>
                </div>
                    
                <?php if (!empty($products)): ?>
                <!-- Products Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($products as $product): ?>
                    <div class="product-card" data-aos="fade-up">
                        <!-- Image gallery -->
                        <div class="relative">
                            <div class="swiper product-swiper-<?= $product['id'] ?> h-56 sm:h-60">
                                <div class="swiper-wrapper">
                                    <?php if (empty($product['images'])): ?>
                                    <div class="swiper-slide">
                                        <div class="w-full h-full flex items-center justify-center" style="background:rgba(0,200,255,0.05)">
                                            <i class="fas fa-image text-5xl" style="color:rgba(0,200,255,0.2)"></i>
                                        </div>
                                    </div>
                                    <?php else: foreach ($product['images'] as $img): ?>
                                    <div class="swiper-slide">
                                        <img src="../<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($product['name']??'Produit') ?>" class="w-full h-full object-cover">
                                    </div>
                                    <?php endforeach; endif; ?>
                                </div>
                                <?php if (!empty($product['images']) && count($product['images'])>1): ?>
                                <div class="swiper-pagination"></div>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($product['category_name'])): ?>
                            <div class="absolute top-3 left-3 cat-badge"><?= htmlspecialchars($product['category_name']) ?></div>
                            <?php endif; ?>

                            <form method="POST" class="absolute top-3 right-3">
                                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                <?php if (in_array($product['id'], $_SESSION['favorites'])): ?>
                                <input type="hidden" name="action" value="remove_from_favorites">
                                <button type="submit" class="w-8 h-8 rounded-full flex items-center justify-center text-red-400 transition-all" style="background:rgba(10,24,58,0.85);backdrop-filter:blur(8px)">
                                    <i class="fas fa-heart text-sm"></i>
                                </button>
                                <?php else: ?>
                                <input type="hidden" name="action" value="add_to_favorites">
                                <button type="submit" class="w-8 h-8 rounded-full flex items-center justify-center text-gray-400 hover:text-red-400 transition-all" style="background:rgba(10,24,58,0.85);backdrop-filter:blur(8px)">
                                    <i class="far fa-heart text-sm"></i>
                                </button>
                                <?php endif; ?>
                            </form>

                            <?php if (isset($product['stock'])): ?>
                                <?php if ($product['stock']>0 && $product['stock']<=5): ?>
                                <div class="absolute bottom-3 left-3 text-white text-xs font-bold px-2 py-1 rounded" style="background:rgba(245,158,11,0.9)"><?= t('shop.stock_low') ?> <?= $product['stock'] ?> <?= t('shop.stock_low2') ?></div>
                                <?php elseif ($product['stock']==0): ?>
                                <div class="absolute bottom-3 left-3 text-white text-xs font-bold px-2 py-1 rounded" style="background:rgba(239,68,68,0.9)"><?= t('shop.out_stock') ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Details -->
                        <div class="p-4">
                            <h3 class="font-heading font-semibold text-white text-base mb-2 line-clamp-1">
                                <?= htmlspecialchars($product['name']??'Produit sans nom') ?>
                            </h3>
                            <div class="flex justify-between items-center mb-2">
                                <span class="price-tag">
                                    <?= number_format($product['price']??0,0) ?> FCFA
                                    <?php if (!empty($product['sale_price']) && $product['sale_price']<($product['price']??0)): ?>
                                    <span class="text-xs text-gray-500 line-through ml-1"><?= number_format($product['sale_price'],0) ?></span>
                                    <?php endif; ?>
                                </span>
                                <?php if (!empty($product['weight']) && $product['weight']>0): ?>
                                <span class="text-gray-600 text-xs"><?= $product['weight'] ?> kg</span>
                                <?php endif; ?>
                            </div>
                            <p class="text-gray-500 text-sm mb-4 line-clamp-2">
                                <?php
                                if (!empty($product['short_description'])) echo htmlspecialchars($product['short_description']);
                                elseif (!empty($product['description'])) echo htmlspecialchars(mb_substr($product['description'],0,100)).(mb_strlen($product['description'])>100?'…':'');
                                else echo 'Description non disponible';
                                ?>
                            </p>
                            <div class="flex gap-2 mb-2">
                                <a href="product.php?id=<?= $product['id'] ?>" class="btn-secondary flex-1 justify-center text-sm py-2"><?= t('shop.details') ?></a>
                                <?php if (!isset($product['stock']) || $product['stock']>0): ?>
                                <form method="POST" class="flex-1">
                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                    <input type="hidden" name="action" value="add_to_cart">
                                    <button type="submit" class="btn-primary w-full justify-center text-sm py-2">
                                        <i class="fas fa-cart-plus"></i> <?= t('shop.buy') ?>
                                    </button>
                                </form>
                                <?php else: ?>
                                <button disabled class="flex-1 text-gray-500 text-sm py-2 rounded-lg cursor-not-allowed" style="background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.06)">
                                    <i class="fas fa-times-circle mr-1"></i> <?= t('shop.out_stock') ?>
                                </button>
                                <?php endif; ?>
                            </div>
                            <button onclick="toggleCompare(<?= $product['id'] ?>, <?= json_encode($product['name'] ?? '') ?>)"
                                    id="cmp-<?= $product['id'] ?>"
                                    class="w-full text-xs py-1.5 rounded-lg border transition-colors"
                                    style="background:rgba(0,200,255,0.05);border-color:rgba(0,200,255,0.15);color:#64748b">
                                <i class="fas fa-columns mr-1"></i>Comparer
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                    
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="mt-8 flex justify-center gap-1 flex-wrap" data-aos="fade-up">
                    <?php $q = http_build_query(['search'=>$search_term,'category'=>$category_id,'min_price'=>$min_price,'max_price'=>$max_price,'sort_by'=>$sort_by]); ?>
                    <?php if ($page>1): ?>
                    <a href="?page=<?= $page-1 ?>&<?= $q ?>" class="pagination-btn"><i class="fas fa-chevron-left"></i></a>
                    <?php else: ?>
                    <span class="pagination-btn opacity-30 cursor-not-allowed"><i class="fas fa-chevron-left"></i></span>
                    <?php endif; ?>

                    <?php
                    $sp = max(1,$page-2); $ep = min($total_pages,$page+2);
                    if ($sp>1) { echo '<a href="?page=1&'.$q.'" class="pagination-btn">1</a>'; if ($sp>2) echo '<span class="pagination-btn cursor-default">…</span>'; }
                    for ($i=$sp;$i<=$ep;$i++) {
                        $cls = $i==$page ? 'pagination-btn active' : 'pagination-btn';
                        echo $i==$page ? '<span class="'.$cls.'">'.$i.'</span>' : '<a href="?page='.$i.'&'.$q.'" class="'.$cls.'">'.$i.'</a>';
                    }
                    if ($ep<$total_pages) { if ($ep<$total_pages-1) echo '<span class="pagination-btn cursor-default">…</span>'; echo '<a href="?page='.$total_pages.'&'.$q.'" class="pagination-btn">'.$total_pages.'</a>'; }
                    ?>

                    <?php if ($page<$total_pages): ?>
                    <a href="?page=<?= $page+1 ?>&<?= $q ?>" class="pagination-btn"><i class="fas fa-chevron-right"></i></a>
                    <?php else: ?>
                    <span class="pagination-btn opacity-30 cursor-not-allowed"><i class="fas fa-chevron-right"></i></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php else: ?>
                <!-- No products -->
                <div class="shop-card flex flex-col items-center justify-center py-16 text-center" data-aos="fade-up">
                    <i class="fas fa-search text-6xl mb-6" style="color:rgba(0,200,255,0.2)"></i>
                    <h3 class="font-heading font-bold text-2xl text-white mb-3"><?= t('shop.no_results') ?></h3>
                    <p class="text-gray-500 max-w-md mb-6">
                        <?php if (!empty($search_term)): ?>
                            Aucun résultat pour "<?= htmlspecialchars($search_term) ?>".
                        <?php elseif ($category_id>0): ?>
                            Aucun produit dans cette catégorie pour le moment.
                        <?php else: ?>
                            Aucun produit disponible pour le moment.
                        <?php endif; ?>
                    </p>
                    <div class="flex gap-3 flex-wrap justify-center">
                        <a href="shop.php" class="btn-primary text-sm py-2.5"><i class="fas fa-th"></i> Tous les produits</a>
                        <?php if (!empty($search_term)||$category_id>0): ?>
                        <a href="shop.php" class="btn-secondary text-sm py-2.5"><i class="fas fa-undo"></i> Réinitialiser</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                    
                <!-- Joegol Banner -->
                <div class="mt-10 rounded-2xl overflow-hidden" style="background:linear-gradient(135deg,rgba(0,102,204,0.25),rgba(0,200,255,0.15));border:1px solid rgba(0,200,255,0.2)" data-aos="fade-up">
                    <div class="flex flex-col md:flex-row items-center">
                        <div class="md:w-1/3 p-6 flex justify-center">
                            <img src="../image/joegolm.png" alt="Joegol Logistics" class="h-24 object-contain" onerror="this.style.display='none'">
                        </div>
                        <div class="md:w-2/3 p-6 md:p-8">
                            <h3 class="font-heading font-bold text-xl text-white mb-2">Livraison rapide avec <span style="color:#db5201">Joegol</span></h3>
                            <p class="text-gray-400 text-sm mb-4">Nos produits sont expédiés directement depuis nos fournisseurs, avec suivi complet et logistique optimisée.</p>
                            <div class="flex flex-wrap gap-5 text-sm text-gray-300">
                                <span><i class="fas fa-shipping-fast mr-2" style="color:#db5201"></i>Livraison rapide</span>
                                <span><i class="fas fa-globe mr-2" style="color:#db5201"></i>Mondial</span>
                                <span><i class="fas fa-shield-alt mr-2" style="color:#db5201"></i>Sécurisé</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div><!-- end products col -->
        </div><!-- end flex -->
    </section>

    <!-- Popular Products -->
    <?php if (!empty($popular_products)): ?>
    <section class="py-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8" data-aos="fade-up">
        <h2 class="font-heading font-bold text-2xl text-white mb-6">
            <span class="gradient-text">Produits</span> populaires
        </h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-4">
            <?php foreach ($popular_products as $product): ?>
            <a href="product.php?id=<?= $product['id'] ?>" class="product-card hover-glow" data-aos="fade-up">
                <div class="h-28 overflow-hidden">
                    <img src="../<?= !empty($product['images'])?htmlspecialchars($product['images'][0]):'image/oops.avif' ?>"
                         alt="<?= htmlspecialchars($product['name']??'Produit') ?>"
                         class="w-full h-full object-cover">
                </div>
                <div class="p-3">
                    <h3 class="text-xs font-medium text-gray-300 line-clamp-1"><?= htmlspecialchars($product['name']??'Produit') ?></h3>
                    <p class="price-tag text-sm mt-1"><?= number_format($product['price']??0,0) ?> <span class="text-xs font-normal">FCFA</span></p>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Advantages -->
    <section class="py-14 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="font-heading font-bold text-2xl text-center text-white mb-10" data-aos="fade-up">
            Pourquoi choisir <span class="gradient-text">Netcrafter Shop</span> ?
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6"
             data-grid-anim data-cols="3" data-cols-responsive='{"640":1,"768":2,"default":3}'>
            <?php foreach ([
                ['fa-check-circle','#00c8ff','Qualité garantie','Produits soigneusement sélectionnés et testés pour une qualité optimale.'],
                ['fa-shipping-fast','#4db8ff','Livraison rapide','Commandes expédiées rapidement via notre partenariat Joegol, avec suivi complet.'],
                ['fa-headset','#0066cc','Support 24/7','Notre équipe est disponible à tout moment pour répondre à vos questions.'],
            ] as $d=>$av): ?>
            <div class="service-card p-8 rounded-2xl text-center" data-aos="fade-up" data-aos-delay="<?= ($d+1)*100 ?>">
                <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-5"
                     style="background:<?= $av[1] ?>12;border:1px solid <?= $av[1] ?>30">
                    <i class="fas <?= $av[0] ?> text-2xl" style="color:<?= $av[1] ?>"></i>
                </div>
                <h3 class="font-heading font-bold text-white text-lg mb-3"><?= $av[2] ?></h3>
                <p class="text-gray-500 text-sm leading-relaxed"><?= $av[3] ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- WhatsApp Float -->
    <a href="https://wa.me/22788672115" target="_blank"
       class="fixed bottom-6 right-6 z-50 w-14 h-14 rounded-full flex items-center justify-center hover:scale-110 transition-all"
       style="background:#25d366;box-shadow:0 0 25px rgba(37,211,102,0.4)">
        <i class="fab fa-whatsapp text-white text-2xl"></i>
    </a>

    <!-- Toast Notifications -->
    <div id="cart-toast" class="fixed bottom-20 right-4 text-white px-5 py-3 rounded-xl shadow-lg transform translate-y-24 opacity-0 transition-all duration-300 flex items-center z-50 gap-3" style="background:linear-gradient(135deg,#10b981,#059669);box-shadow:0 0 20px rgba(16,185,129,0.4)">
        <i class="fas fa-check-circle text-lg"></i><span class="text-sm font-medium">Produit ajouté au panier</span>
    </div>
    <div id="fav-toast" class="fixed bottom-20 right-4 text-white px-5 py-3 rounded-xl shadow-lg transform translate-y-24 opacity-0 transition-all duration-300 flex items-center z-50 gap-3" style="background:linear-gradient(135deg,#00c8ff,#0066cc);box-shadow:0 0 20px rgba(0,200,255,0.4)">
        <i class="fas fa-heart text-lg"></i><span class="text-sm font-medium">Ajouté aux favoris</span>
    </div>
    <div id="fav-removed-toast" class="fixed bottom-20 right-4 text-white px-5 py-3 rounded-xl shadow-lg transform translate-y-24 opacity-0 transition-all duration-300 flex items-center z-50 gap-3" style="background:rgba(10,24,58,0.95);border:1px solid rgba(0,200,255,0.2)">
        <i class="fas fa-heart-broken text-lg" style="color:#00c8ff"></i><span class="text-sm font-medium">Retiré des favoris</span>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script>
        // Initialize product image sliders
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Swiper for each product
            <?php if (!empty($products)): ?>
                <?php foreach ($products as $product): ?>
                    try {
                        new Swiper('.product-swiper-<?php echo $product['id']; ?>', {
                            pagination: {
                                el: '.swiper-pagination',
                                clickable: true,
                            },
                            loop: <?php echo (isset($product['images']) && count($product['images']) > 1) ? 'true' : 'false'; ?>,
                            autoplay: {
                                delay: 5000,
                                disableOnInteraction: false,
                            },
                        });
                    } catch (e) {
                        console.error('Erreur initialisation Swiper pour produit <?php echo $product['id']; ?>:', e);
                    }
                <?php endforeach; ?>
            <?php endif; ?>
            
            // Mobile filter menu toggle
            const filterToggle = document.getElementById('filter-toggle');
            const closeFilterMenu = document.getElementById('close-filter-menu');
            const mobileFilterMenu = document.getElementById('mobile-filter-menu');
            
            if (filterToggle) {
                filterToggle.addEventListener('click', function() {
                    mobileFilterMenu.classList.remove('translate-x-full');
                    document.body.style.overflow = 'hidden';
                });
            }
            
            if (closeFilterMenu) {
                closeFilterMenu.addEventListener('click', function() {
                    mobileFilterMenu.classList.add('translate-x-full');
                    document.body.style.overflow = '';
                });
            }
            
            // Price range sliders for desktop
            const minSlider = document.getElementById('min_price_slider');
            const maxSlider = document.getElementById('max_price_slider');
            const minValueDisplay = document.getElementById('min-value');
            const maxValueDisplay = document.getElementById('max-value');
            const minPriceInput = document.getElementById('min_price');
            const maxPriceInput = document.getElementById('max_price');
            
            if (minSlider && maxSlider) {
                minSlider.addEventListener('input', function() {
                    let minValue = parseFloat(minSlider.value);
                    let maxValue = parseFloat(maxSlider.value);
                    
                    if (minValue > maxValue) {
                        minValue = maxValue;
                        minSlider.value = minValue;
                    }
                    
                    minValueDisplay.textContent = new Intl.NumberFormat('fr-FR').format(minValue);
                    minPriceInput.value = minValue;
                });
                
                maxSlider.addEventListener('input', function() {
                    let maxValue = parseFloat(maxSlider.value);
                    let minValue = parseFloat(minSlider.value);
                    
                    if (maxValue < minValue) {
                        maxValue = minValue;
                        maxSlider.value = maxValue;
                    }
                    
                    maxValueDisplay.textContent = new Intl.NumberFormat('fr-FR').format(maxValue);
                    maxPriceInput.value = maxValue;
                });
            }
            
            // Price range sliders for mobile
            const mobileMinSlider = document.getElementById('mobile_min_price_slider');
            const mobileMaxSlider = document.getElementById('mobile_max_price_slider');
            const mobileMinValueDisplay = document.getElementById('mobile-min-value');
            const mobileMaxValueDisplay = document.getElementById('mobile-max-value');
            const mobileMinPriceInput = document.getElementById('mobile_min_price');
            const mobileMaxPriceInput = document.getElementById('mobile_max_price');
            
            if (mobileMinSlider && mobileMaxSlider) {
                mobileMinSlider.addEventListener('input', function() {
                    let minValue = parseFloat(mobileMinSlider.value);
                    let maxValue = parseFloat(mobileMaxSlider.value);
                    
                    if (minValue > maxValue) {
                        minValue = maxValue;
                        mobileMinSlider.value = minValue;
                    }
                    
                    mobileMinValueDisplay.textContent = new Intl.NumberFormat('fr-FR').format(minValue);
                    mobileMinPriceInput.value = minValue;
                });
                
                mobileMaxSlider.addEventListener('input', function() {
                    let maxValue = parseFloat(mobileMaxSlider.value);
                    let minValue = parseFloat(mobileMinSlider.value);
                    
                    if (maxValue < minValue) {
                        maxValue = minValue;
                        mobileMaxSlider.value = maxValue;
                    }
                    
                    mobileMaxValueDisplay.textContent = new Intl.NumberFormat('fr-FR').format(maxValue);
                    mobileMaxPriceInput.value = maxValue;
                });
            }
            
            // Show toast notifications
            <?php if (isset($_GET['cart_added'])): ?>showToast('cart-toast');<?php endif; ?>
            <?php if (isset($_GET['fav_added'])): ?>showToast('fav-toast');<?php endif; ?>
            <?php if (isset($_GET['fav_removed'])): ?>showToast('fav-removed-toast');<?php endif; ?>

            // Handle image loading errors
            const images = document.querySelectorAll('img');
            images.forEach(function(img) {
                img.addEventListener('error', function() {
                    if (!this.dataset.fallbackSet) {
                        this.dataset.fallbackSet = 'true';
                        this.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2Y1ZjVmNSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LXNpemU9IjE0IiBmaWxsPSIjOTk5IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iLjNlbSI+SW1hZ2UgaW5kaXNwb25pYmxlPC90ZXh0Pjwvc3ZnPg==';
                    }
                });
            });
        });
        
        // Toast notification function
        function showToast(id) {
            const toast = document.getElementById(id);
            if (toast) {
                toast.classList.remove('translate-y-24', 'opacity-0');
                toast.classList.add('translate-y-0', 'opacity-100');
                
                setTimeout(() => {
                    toast.classList.remove('translate-y-0', 'opacity-100');
                    toast.classList.add('translate-y-24', 'opacity-0');
                }, 3000);
            }
        }
        
        // Form validation for filters
        function validateFilters() {
            const minPrice = parseFloat(document.getElementById('min_price')?.value || 0);
            const maxPrice = parseFloat(document.getElementById('max_price')?.value || 1000000);
            
            if (minPrice > maxPrice) {
                alert('Le prix minimum ne peut pas être supérieur au prix maximum.');
                return false;
            }
            
            return true;
        }
        
        // Handle filter form submission
        const filterForm = document.getElementById('filter-form');
        if (filterForm) {
            filterForm.addEventListener('submit', function(e) {
                if (!validateFilters()) {
                    e.preventDefault();
                }
            });
        }
        
        // Auto-submit filters on change (optional)
        const autoSubmitElements = document.querySelectorAll('input[type="radio"][name="category"], select[name="sort_by"]');
        autoSubmitElements.forEach(function(element) {
            element.addEventListener('change', function() {
                // Add a small delay to prevent rapid submissions
                setTimeout(() => {
                    if (validateFilters()) {
                        this.form.submit();
                    }
                }, 100);
            });
        });
        
        // Loading state for form submissions
        document.addEventListener('submit', function(e) {
            const submitButton = e.target.querySelector('button[type="submit"]');
            if (submitButton && !submitButton.disabled) {
                const originalText = submitButton.innerHTML;
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Chargement...';
                
                // Re-enable after 3 seconds as failsafe
                setTimeout(() => {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalText;
                }, 3000);
            }
        });
        
        // Lazy loading for images (if supported)
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.removeAttribute('data-src');
                        }
                        observer.unobserve(img);
                    }
                });
            });
            
            // Apply lazy loading to product images
            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }
        
        /* ── AJAX Live Search ─────────────────────────────── */
        (function() {
            const input    = document.getElementById('live-search-input');
            const dropdown = document.getElementById('live-search-dropdown');
            if (!input || !dropdown) return;
            let timer = null;

            input.addEventListener('input', function() {
                clearTimeout(timer);
                const q = this.value.trim();
                if (q.length < 2) { dropdown.innerHTML = ''; dropdown.classList.add('hidden'); return; }
                timer = setTimeout(function() {
                    fetch('search-ajax.php?q=' + encodeURIComponent(q))
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (!data.length) { dropdown.classList.add('hidden'); return; }
                            dropdown.innerHTML = data.map(function(p) {
                                const img = p.image ? '../' + p.image : '../image/oops.avif';
                                const price = parseFloat(p.price).toLocaleString('fr-FR');
                                return '<a href="product.php?id=' + p.id + '" class="flex items-center gap-3 px-4 py-3 hover:bg-white/5 transition-colors border-b border-white/5 last:border-0">'
                                     + '<img src="' + img + '" alt="" class="w-10 h-10 object-cover rounded-lg flex-shrink-0" onerror="this.src=\'../image/oops.avif\'">'
                                     + '<div class="flex-1 min-w-0"><p class="text-white text-sm font-medium truncate">' + p.name + '</p>'
                                     + '<p class="text-nc-cyan text-xs font-bold">' + price + ' FCFA</p></div>'
                                     + '<i class="fas fa-chevron-right text-xs text-slate-600"></i></a>';
                            }).join('');
                            dropdown.classList.remove('hidden');
                        })
                        .catch(function() {});
                }, 280);
            });

            document.addEventListener('click', function(e) {
                if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.classList.add('hidden');
                }
            });

            input.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') { dropdown.classList.add('hidden'); }
            });
        })();

        /* ── Product Comparison ────────────────────────────── */
        var ncCompare = JSON.parse(localStorage.getItem('nc_compare') || '[]');

        function renderCompareBar() {
            var bar = document.getElementById('compare-bar');
            if (!bar) return;
            if (ncCompare.length < 1) { bar.classList.add('hidden'); return; }
            bar.classList.remove('hidden');
            var label = bar.querySelector('#compare-count');
            if (label) label.textContent = ncCompare.length + ' produit' + (ncCompare.length > 1 ? 's' : '');
            var link = bar.querySelector('#compare-link');
            if (link) link.href = 'compare.php?' + ncCompare.map(function(x) { return 'ids[]=' + x.id; }).join('&');
            // Update all compare buttons
            document.querySelectorAll('[id^="cmp-"]').forEach(function(btn) {
                var pid = parseInt(btn.id.replace('cmp-', ''));
                var active = ncCompare.some(function(x) { return x.id === pid; });
                btn.style.color = active ? '#00c8ff' : '#64748b';
                btn.style.borderColor = active ? 'rgba(0,200,255,0.4)' : 'rgba(0,200,255,0.15)';
            });
        }

        function toggleCompare(id, name) {
            var idx = ncCompare.findIndex(function(x) { return x.id === id; });
            if (idx >= 0) {
                ncCompare.splice(idx, 1);
            } else {
                if (ncCompare.length >= 4) {
                    alert('Vous pouvez comparer au maximum 4 produits.');
                    return;
                }
                ncCompare.push({ id: id, name: name });
            }
            localStorage.setItem('nc_compare', JSON.stringify(ncCompare));
            renderCompareBar();
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Inject compare bar
            var bar = document.createElement('div');
            bar.id = 'compare-bar';
            bar.className = 'fixed bottom-0 left-0 right-0 z-40 hidden';
            bar.style.cssText = 'background:rgba(6,13,30,0.95);backdrop-filter:blur(20px);border-top:1px solid rgba(0,200,255,0.2);padding:12px 24px;';
            bar.innerHTML = '<div class="max-w-7xl mx-auto flex items-center justify-between gap-4">'
                + '<span class="text-white text-sm"><i class="fas fa-columns text-nc-cyan mr-2"></i>Comparaison : <strong id="compare-count"></strong></span>'
                + '<div class="flex gap-3">'
                + '<button onclick="ncCompare=[];localStorage.removeItem(\'nc_compare\');renderCompareBar();" class="text-slate-400 hover:text-red-400 text-sm transition-colors"><i class="fas fa-trash mr-1"></i>Vider</button>'
                + '<a id="compare-link" href="#" class="btn-primary text-sm py-2 px-4"><i class="fas fa-balance-scale mr-1"></i>Comparer</a>'
                + '</div></div>';
            document.body.appendChild(bar);
            renderCompareBar();
        });

        // Debug function (only available with ?debug=1)
        <?php if (isset($_GET['debug'])): ?>
        window.debugShop = function() {
            console.log('=== Debug Info Shop ===');
            console.log('Produits chargés:', <?php echo count($products); ?>);
            console.log('Total produits en base:', <?php echo $total_products; ?>);
            console.log('Catégories disponibles:', <?php echo count($categories); ?>);
            console.log('Terme de recherche:', '<?php echo addslashes($search_term); ?>');
            console.log('Catégorie sélectionnée:', <?php echo $category_id; ?>);
            console.log('Prix min/max:', <?php echo $min_price; ?>, <?php echo $max_price; ?>);
            console.log('Tri par:', '<?php echo $sort_by; ?>');
            console.log('Page courante:', <?php echo $page; ?>, 'Total pages:', <?php echo $total_pages; ?>);
            console.log('Session cart:', <?php echo json_encode($_SESSION['cart']); ?>);
            console.log('Session favorites:', <?php echo json_encode($_SESSION['favorites']); ?>);
            
            <?php if (!empty($products)): ?>
            console.log('Premier produit:', <?php echo json_encode($products[0]); ?>);
            <?php endif; ?>
            
            console.log('Colonnes de la table products:', <?php echo json_encode($columns ?? []); ?>);
        };
        
        // Automatically run debug on page load
        console.log('Debug mode activé. Tapez debugShop() dans la console pour plus d\'infos.');
        <?php endif; ?>
        
    </script>
    
    <?php
    if (isset($conn) && $conn instanceof mysqli) { $conn->close(); }
    ?>