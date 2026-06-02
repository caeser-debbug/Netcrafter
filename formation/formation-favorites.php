<?php
// Initialisation de la session
session_start();

require_once __DIR__ . '/db.php';

// Connexion à la base de données
$conn = new mysqli($servername, $username, $password, $dbname);

// Vérification de la connexion
if ($conn->connect_error) {
    die("Échec de la connexion: " . $conn->connect_error);
}
$conn->set_charset("utf8");

// Vérifier si l'utilisateur est connecté
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$user_id = $is_logged_in ? $_SESSION['user_id'] : 0;

// Initialisation des favoris s'ils n'existent pas
if (!isset($_SESSION['formation_favorites'])) {
    $_SESSION['formation_favorites'] = [];
    
    // Si l'utilisateur est connecté, charger ses favoris depuis la base de données
    if ($is_logged_in) {
        $favorites_query = "SELECT formation_id FROM formation_favorites WHERE user_id = ?";
        $stmt = $conn->prepare($favorites_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $_SESSION['formation_favorites'][] = $row['formation_id'];
        }
    }
}

// Traitement des actions (suppression des favoris)
if (isset($_POST['action']) && isset($_POST['formation_id'])) {
    $formation_id = intval($_POST['formation_id']);
    
    if ($_POST['action'] === 'remove_from_favorites') {
        // Supprimer des favoris
        if (($key = array_search($formation_id, $_SESSION['formation_favorites'])) !== false) {
            unset($_SESSION['formation_favorites'][$key]);
            $_SESSION['formation_favorites'] = array_values($_SESSION['formation_favorites']); // Réindexer le tableau
            
            // Si l'utilisateur est connecté, supprimer de la base de données
            if ($is_logged_in) {
                $remove_favorite_query = "DELETE FROM formation_favorites WHERE user_id = ? AND formation_id = ?";
                $stmt = $conn->prepare($remove_favorite_query);
                $stmt->bind_param("ii", $user_id, $formation_id);
                $stmt->execute();
            }
        }
        header("Location: formation-favorites.php?fav_removed=1");
        exit;
    }
}

// Récupération des formations favorites
$favorite_formations = [];

if (!empty($_SESSION['formation_favorites'])) {
    // Construire la liste des ID pour la requête IN
    $formation_ids = implode(',', array_map('intval', $_SESSION['formation_favorites']));
    
    // Requête pour récupérer les détails des formations
    $sql = "SELECT f.*, c.name as category_name, c.icon as category_icon 
            FROM formations f 
            JOIN formation_categories c ON f.category_id = c.id 
            WHERE f.id IN ($formation_ids) AND f.status = 'active'";
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Si l'image de couverture n'est pas définie, utiliser une image par défaut
            if (empty($row['cover_image'])) {
                $row['cover_image'] = "image/formations/default-" . rand(1, 3) . ".jpg";
            }
            
            // Vérifier si l'utilisateur est abonné à cette formation
            $row['is_subscribed'] = false;
            if ($is_logged_in) {
                $subscription_query = "SELECT id FROM formation_subscriptions 
                                      WHERE user_id = ? AND formation_id = ? 
                                      AND status = 'active' AND end_date >= CURDATE()";
                $stmt = $conn->prepare($subscription_query);
                $stmt->bind_param("ii", $user_id, $row['id']);
                $stmt->execute();
                $subscription_result = $stmt->get_result();
                
                if ($subscription_result->num_rows > 0) {
                    $row['is_subscribed'] = true;
                }
                $stmt->close();
            }
            
            $favorite_formations[] = $row;
        }
    }
}

// Tri des formations (abonnées en premier, puis par date d'ajout)
usort($favorite_formations, function($a, $b) {
    if ($a['is_subscribed'] && !$b['is_subscribed']) {
        return -1;
    } elseif (!$a['is_subscribed'] && $b['is_subscribed']) {
        return 1;
    } else {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    }
});

// Fermeture de la connexion à la base de données
$conn->close();
?>

<!DOCTYPE html>
<html lang="fr" class="light scroll">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Formations Favorites - Netcrafter</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Animation library -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- AOS Library for scroll animations -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
    <!-- Custom styles -->
    <style>
        html {
            scroll-behavior: smooth;
            overflow-x: hidden;
        }
        
        body {
            width: 100%;
            max-width: 100vw;
            overflow-x: hidden;
        }
        
        .hero-gradient-light {
            background: linear-gradient(135deg, #0288d1 0%, #01579b 100%);
        }
        
        .hero-gradient-dark {
            background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%);
        }
        
        .formation-card {
            transition: all 0.3s ease;
        }
        
        .formation-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        /* Truncate lines */
        .line-clamp-1 {
            display: -webkit-box;
            -webkit-line-clamp: 1;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .line-clamp-3 {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        netblue: {
                            100: '#E6F2FF',
                            200: '#B8D4FF',
                            300: '#8AB6FF',
                            400: '#5C98FF',
                            500: '#3B82F6',
                            600: '#1A6BE2',
                            700: '#0055CC',
                            800: '#003F99',
                            900: '#002966'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-white transition-colors duration-300">
    <!-- Navigation -->
    <nav class="fixed w-full bg-white dark:bg-gray-800 shadow-md z-50 transition-all duration-300" id="navbar">
        <div class="container mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center">
                <a href="index.html">
                    <img src="../image/logo-n.png" alt="Netcrafter Logo" class="h-10 mr-2">
                    <span class="text-xl md:text-2xl font-bold text-netblue-600 dark:text-netblue-400 navbar-brand">NETCRAFTER</span>
                </a>
            </div>
            
            <!-- Desktop Menu -->
            <div class="hidden md:flex items-center">
                <div class="space-x-4 md:space-x-6 text-gray-700 dark:text-gray-300 mr-6">
                    <a href="index.html" class="hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors">Accueil</a>
                    <a href="service.html" class="hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors">Services</a>
                    <a href="devis.html" class="hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors">Devis</a>
                    <a href="shop.php" class="hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors">Boutique</a>
                    <a href="formations.php" class="text-netblue-600 dark:text-netblue-400 font-medium">Formation</a>
                </div>
                
                <!-- Favorites and Account -->
                <div class="flex items-center space-x-4 mr-4">
                    <a href="formation-favorites.php" class="relative text-netblue-600 dark:text-netblue-400" title="Mes formations favorites">
                        <i class="fas fa-heart text-xl"></i>
                        <?php if (count($_SESSION['formation_favorites']) > 0): ?>
                        <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                            <?php echo count($_SESSION['formation_favorites']); ?>
                        </span>
                        <?php endif; ?>
                    </a>
                    <?php if ($is_logged_in): ?>
                    <div class="relative group">
                        <button class="flex items-center text-gray-700 dark:text-gray-300 hover:text-netblue-600 dark:hover:text-netblue-400">
                            <i class="fas fa-user-circle text-xl mr-2"></i>
                            <span>Mon Compte</span>
                            <i class="fas fa-chevron-down ml-1 text-xs"></i>
                        </button>
                        <div class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg py-2 z-10 hidden group-hover:block">
                            <a href="dashboard.php" class="block px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-netblue-100 dark:hover:bg-gray-700">
                                <i class="fas fa-tachometer-alt mr-2"></i>Tableau de bord
                            </a>
                            <a href="my-formations.php" class="block px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-netblue-100 dark:hover:bg-gray-700">
                                <i class="fas fa-graduation-cap mr-2"></i>Mes formations
                            </a>
                            <a href="certificates.php" class="block px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-netblue-100 dark:hover:bg-gray-700">
                                <i class="fas fa-certificate mr-2"></i>Mes certificats
                            </a>
                            <a href="quiz.php" class="block px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-netblue-100 dark:hover:bg-gray-700">
                                <i class="fas fa-question-circle mr-2"></i>Quiz
                            </a>
                            <a href="profile.php" class="block px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-netblue-100 dark:hover:bg-gray-700">
                                <i class="fas fa-user-edit mr-2"></i>Modifier profil
                            </a>
                            <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>
                            <a href="logout.php" class="block px-4 py-2 text-red-600 hover:bg-red-100 dark:hover:bg-red-900">
                                <i class="fas fa-sign-out-alt mr-2"></i>Déconnexion
                            </a>
                        </div>
                    </div>
                    <?php else: ?>
                    <a href="login.php" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fas fa-sign-in-alt mr-1"></i>Connexion
                    </a>
                    <?php endif; ?>
                </div>
                
                <!-- Dark Mode Toggle -->
                <div class="flex items-center">
                    <label class="theme-switch relative inline-block w-14 h-7">
                        <input type="checkbox" id="darkModeToggle" class="opacity-0 w-0 h-0">
                        <span class="slider absolute cursor-pointer inset-0 bg-gray-300 rounded-full transition-all duration-300 before:absolute before:h-5 before:w-5 before:left-1 before:bottom-1 before:bg-white before:rounded-full before:transition-all before:duration-300"></span>
                    </label>
                </div>
            </div>
            
            <!-- Mobile Menu Button, Favorites and Dark Mode Toggle -->
            <div class="md:hidden flex items-center space-x-4">
                <a href="formation-favorites.php" class="relative text-netblue-600 dark:text-netblue-400">
                    <i class="fas fa-heart text-xl"></i>
                    <?php if (count($_SESSION['formation_favorites']) > 0): ?>
                    <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                        <?php echo count($_SESSION['formation_favorites']); ?>
                    </span>
                    <?php endif; ?>
                </a>
                
                <?php if ($is_logged_in): ?>
                <a href="dashboard.php" class="text-gray-700 dark:text-gray-300">
                    <i class="fas fa-user-circle text-xl"></i>
                </a>
                <?php else: ?>
                <a href="login.php" class="text-gray-700 dark:text-gray-300">
                    <i class="fas fa-sign-in-alt text-xl"></i>
                </a>
                <?php endif; ?>
                
                <!-- Mobile Dark Mode Toggle -->
                <div class="flex items-center">
                    <label class="theme-switch relative inline-block w-12 h-6">
                        <input type="checkbox" id="darkModeToggleMobile" class="opacity-0 w-0 h-0">
                        <span class="slider absolute cursor-pointer inset-0 bg-gray-300 rounded-full transition-all duration-300 before:absolute before:h-4 before:w-4 before:left-1 before:bottom-1 before:bg-white before:rounded-full before:transition-all before:duration-300"></span>
                    </label>
                </div>
                
                <button id="menu-toggle" class="text-gray-700 dark:text-white focus:outline-none">
                    <i class="fas fa-bars text-2xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Mobile Menu -->
        <div id="mobile-menu" class="hidden md:hidden bg-white dark:bg-gray-800 shadow-md">
            <div class="px-4 py-2 space-y-3">
                <a href="index.html" class="block py-2 hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors dark:text-gray-300">Accueil</a>
                <a href="service.html" class="block py-2 hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors dark:text-gray-300">Services</a>
                <a href="devis.html" class="block py-2 hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors dark:text-gray-300">Devis</a>
                <a href="shop.php" class="block py-2 hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors dark:text-gray-300">Boutique</a>
                <a href="formations.php" class="block py-2 text-netblue-600 dark:text-netblue-400 font-medium">Formation</a>
                
                <?php if ($is_logged_in): ?>
                <div class="border-t border-gray-200 dark:border-gray-700 pt-2 mt-2">
                    <a href="dashboard.php" class="block py-2 hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors dark:text-gray-300">
                        <i class="fas fa-tachometer-alt mr-2"></i>Tableau de bord
                    </a>
                    <a href="my-formations.php" class="block py-2 hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors dark:text-gray-300">
                        <i class="fas fa-graduation-cap mr-2"></i>Mes formations
                    </a>
                    <a href="certificates.php" class="block py-2 hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors dark:text-gray-300">
                        <i class="fas fa-certificate mr-2"></i>Mes certificats
                    </a>
                    <a href="profile.php" class="block py-2 hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors dark:text-gray-300">
                        <i class="fas fa-user-edit mr-2"></i>Modifier profil
                    </a>
                    <a href="logout.php" class="block py-2 text-red-600">
                        <i class="fas fa-sign-out-alt mr-2"></i>Déconnexion
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <section class="bg-white dark:bg-gray-800 pt-24 pb-8">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold mb-2 dark:text-white">Mes Formations Favorites</h1>
                    <p class="text-gray-600 dark:text-gray-400">
                        <?php echo count($favorite_formations); ?> formation<?php echo count($favorite_formations) > 1 ? 's' : ''; ?> dans vos favoris
                    </p>
                </div>
                <a href="formations.php" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded-lg transition-colors hidden sm:inline-block">
                    <i class="fas fa-search mr-2"></i>Explorer d'autres formations
                </a>
            </div>
        </div>
    </section>

    <!-- Favorites content -->
    <section class="py-8 bg-gray-50 dark:bg-gray-900">
        <div class="container mx-auto px-4">
            <?php if (empty($favorite_formations)): ?>
            <!-- Empty state -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8 text-center" data-aos="fade-up">
                <div class="mb-6 text-gray-400 dark:text-gray-500">
                    <i class="fas fa-heart-broken text-6xl"></i>
                </div>
                <h2 class="text-2xl font-bold mb-4 dark:text-white">Aucune formation favorite</h2>
                <p class="text-gray-600 dark:text-gray-400 mb-6 max-w-md mx-auto">
                    Vous n'avez pas encore ajouté de formations à vos favoris. Explorez notre catalogue pour trouver des formations qui vous intéressent.
                </p>
                <a href="formations.php" class="inline-block bg-netblue-600 hover:bg-netblue-700 text-white font-bold py-3 px-6 rounded-lg transition-colors">
                    <i class="fas fa-search mr-2"></i>Explorer les formations
                </a>
            </div>
            <?php else: ?>
            <!-- Favorites grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($favorite_formations as $formation): ?>
                <div class="formation-card bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden" data-aos="fade-up">
                    <!-- Formation Image -->
                    <div class="relative h-48">
                        <img src="../<?php echo htmlspecialchars($formation['cover_image']); ?>" alt="<?php echo htmlspecialchars($formation['title']); ?>" class="w-full h-full object-cover">
                        
                        <!-- Category Badge -->
                        <div class="absolute top-3 left-3 bg-netblue-600 text-white text-xs font-bold px-2 py-1 rounded">
                            <i class="fas <?php echo htmlspecialchars($formation['category_icon']); ?> mr-1"></i>
                            <?php echo htmlspecialchars($formation['category_name']); ?>
                        </div>
                        
                        <!-- Remove from Favorites Button -->
                        <form method="POST" class="absolute top-3 right-3">
                            <input type="hidden" name="formation_id" value="<?php echo $formation['id']; ?>">
                            <input type="hidden" name="action" value="remove_from_favorites">
                            <button type="submit" class="bg-white dark:bg-gray-700 text-red-500 h-8 w-8 rounded-full flex items-center justify-center shadow-md hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                                <i class="fas fa-heart"></i>
                            </button>
                        </form>
                        
                        <!-- Level Badge -->
                        <div class="absolute bottom-3 left-3 text-white text-xs font-bold px-2 py-1 rounded
                            <?php 
                            switch($formation['level']) {
                                case 'debutant':
                                    echo 'bg-green-600';
                                    break;
                                case 'intermediaire':
                                    echo 'bg-yellow-600';
                                    break;
                                case 'avance':
                                    echo 'bg-red-600';
                                    break;
                            }
                            ?>">
                            <?php 
                            switch($formation['level']) {
                                case 'debutant':
                                    echo 'Débutant';
                                    break;
                                case 'intermediaire':
                                    echo 'Intermédiaire';
                                    break;
                                case 'avance':
                                    echo 'Avancé';
                                    break;
                            }
                            ?>
                        </div>
                        
                        <!-- Subscription Badge -->
                        <?php if ($formation['is_subscribed']): ?>
                        <div class="absolute top-3 left-1/2 transform -translate-x-1/2 bg-green-600 text-white text-xs font-bold px-2 py-1 rounded-full">
                            <i class="fas fa-check-circle mr-1"></i>Abonné
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Formation Details -->
                    <div class="p-4">
                        <h3 class="text-lg font-bold mb-2 text-gray-800 dark:text-white line-clamp-2">
                            <?php echo htmlspecialchars($formation['title']); ?>
                        </h3>
                        
                        <div class="flex items-center mb-3 text-sm text-gray-500 dark:text-gray-400">
                            <div class="flex items-center mr-4">
                                <i class="fas fa-clock mr-1"></i>
                                <span><?php echo !empty($formation['duration']) ? htmlspecialchars($formation['duration']) : 'Durée variable'; ?></span>
                            </div>
                        </div>
                        
                        <p class="text-gray-600 dark:text-gray-300 text-sm mb-4 line-clamp-2">
                            <?php 
                            if (!empty($formation['short_description'])) {
                                echo htmlspecialchars($formation['short_description']);
                            } else {
                                echo htmlspecialchars(substr($formation['description'], 0, 100)) . (strlen($formation['description']) > 100 ? '...' : '');
                            }
                            ?>
                        </p>
                        
                        <div class="flex justify-between items-center">
                            <div class="text-netblue-600 dark:text-netblue-400 font-bold">
                                <?php echo number_format($formation['price_per_month'], 0, ',', ' '); ?> FCFA<span class="text-sm font-normal">/mois</span>
                            </div>
                            
                            <?php if ($formation['is_subscribed']): ?>
                            <a href="watch.php?formation_id=<?php echo $formation['id']; ?>" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-sm font-medium transition-colors">
                                <i class="fas fa-play mr-1"></i>Continuer
                            </a>
                            <?php else: ?>
                            <a href="formation-details.php?id=<?php echo $formation['id']; ?>" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded text-sm font-medium transition-colors">
                                <i class="fas fa-info-circle mr-1"></i>Détails
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Mobile CTA -->
            <div class="mt-8 text-center sm:hidden">
                <a href="formations.php" class="inline-block bg-netblue-600 hover:bg-netblue-700 text-white font-bold py-3 px-6 rounded-lg transition-colors">
                    <i class="fas fa-search mr-2"></i>Explorer d'autres formations
                </a>
            </div>
            <?php endif; ?>
        </div>
    </section>
    
    <!-- Recommended Formations -->
    <section class="py-12 bg-white dark:bg-gray-800">
        <div class="container mx-auto px-4">
            <h2 class="text-2xl font-bold mb-8 dark:text-white">Formations recommandées pour vous</h2>
            
            <div id="recommended-loader" class="flex justify-center">
                <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-netblue-600"></div>
            </div>
            
            <div id="recommended-formations" class="grid grid-cols-1 md:grid-cols-3 gap-6 hidden">
                <!-- Recommendations will be loaded here via AJAX -->
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="py-12 bg-netblue-600 dark:bg-netblue-800 text-white">
        <div class="container mx-auto px-4 text-center">
            <h2 class="text-3xl font-bold mb-4">Prêt à développer vos compétences ?</h2>
            <p class="text-xl mb-8 max-w-3xl mx-auto">
                Rejoignez notre communauté d'apprenants et accédez à des formations de qualité pour booster votre carrière.
            </p>
            <?php if (!$is_logged_in): ?>
            <div class="flex flex-col sm:flex-row items-center justify-center space-y-4 sm:space-y-0 sm:space-x-4">
                <a href="register.php" class="bg-white text-netblue-600 hover:bg-netblue-100 font-bold py-3 px-8 rounded-lg transition-colors inline-block">
                    <i class="fas fa-user-plus mr-2"></i>Créer un compte
                </a>
                <a href="login.php" class="bg-transparent border-2 border-white hover:bg-white hover:text-netblue-600 text-white font-bold py-3 px-8 rounded-lg transition-colors inline-block">
                    <i class="fas fa-sign-in-alt mr-2"></i>Se connecter
                </a>
            </div>
            <?php else: ?>
            <a href="formations.php" class="bg-white text-netblue-600 hover:bg-netblue-100 font-bold py-3 px-8 rounded-lg transition-colors inline-block">
                <i class="fas fa-graduation-cap mr-2"></i>Explorer toutes les formations
            </a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-10 md:py-12">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <!-- About -->
                <div>
                    <div class="flex items-center mb-6">
                        <img src="../image/logo-n.png" alt="Netcrafter Logo" class="h-10 mr-4">
                        <span class="text-xl md:text-2xl font-bold text-netblue-400">NETCRAFTER</span>
                    </div>
                    <p class="text-gray-400 mb-6">
                        Solutions informatiques globales pour les entreprises : développement, équipement et formation professionnelle.
                    </p>
                    <p class="text-gray-400">
                        © 2023 Netcrafter. Tous droits réservés.
                    </p>
                </div>
                
                <!-- Quick Links -->
                <div>
                    <h4 class="text-xl font-bold mb-6">Liens rapides</h4>
                    <ul class="space-y-3">
                        <li><a href="index.html" class="text-gray-400 hover:text-white transition-colors">Accueil</a></li>
                        <li><a href="service.html" class="text-gray-400 hover:text-white transition-colors">Services</a></li>
                        <li><a href="devis.html" class="text-gray-400 hover:text-white transition-colors">Devis</a></li>
                        <li><a href="shop.php" class="text-gray-400 hover:text-white transition-colors">Boutique</a></li>
                        <li><a href="formations.php" class="text-gray-400 hover:text-white transition-colors">Formation</a></li>
                    </ul>
                </div>
                
                <!-- Contact -->
                <div>
                    <h4 class="text-xl font-bold mb-6">Contactez-nous</h4>
                    <ul class="space-y-3 text-gray-400">
                        <li class="flex items-start">
                            <i class="fas fa-map-marker-alt mt-1 mr-3"></i>
                            <span>Niamey, Niger</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-phone-alt mr-3"></i>
                            <span>+227 88 67 21 15</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-envelope mr-3"></i>
                            <span>contact@netcrafterniger.com</span>
                        </li>
                    </ul>
                    
                    <!-- Social Media -->
                    <div class="flex space-x-4 mt-6">
                        <a href="https://www.facebook.com/share/1Y7kHRs16L/" class="text-gray-400 hover:text-white transition-colors">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        
                        <a href="https://www.instagram.com/netcrafter.niger?igsh=NzJ2bzM2aWRnMzho" class="text-gray-400 hover:text-white transition-colors">
                            <i class="fab fa-instagram"></i>
                        </a>
                        
                    </div>
                </div>
                
                <!-- Newsletter -->
                <div>
                    <h4 class="text-xl font-bold mb-6">Newsletter</h4>
                    <p class="text-gray-400 mb-4">
                        Restez informé des dernières actualités et nouvelles formations
                    </p>
                    <form class="mb-4">
                        <div class="flex">
                            <input type="email" placeholder="Votre email" class="px-4 py-2 w-full rounded-l-lg focus:outline-none text-gray-800 dark:bg-gray-700 dark:text-white">
                            <button type="submit" class="bg-netblue-600 px-4 py-2 rounded-r-lg hover:bg-netblue-700 transition-colors">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </footer>

    <!-- Back to top button -->
    <button id="back-to-top" class="fixed bottom-6 right-6 bg-netblue-600 dark:bg-netblue-700 text-white w-12 h-12 rounded-full flex items-center justify-center shadow-lg opacity-0 invisible transition-all">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- Favorites Removed Toast Notification -->
    <div id="fav-removed-toast" class="fixed bottom-4 right-4 bg-gray-700 text-white px-6 py-3 rounded-lg shadow-lg transform translate-y-24 opacity-0 transition-all duration-300 flex items-center z-50">
        <i class="fas fa-heart-broken mr-3 text-xl"></i>
        <span>Formation retirée des favoris</span>
    </div>

    <!-- AOS Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>

    <!-- Main JavaScript -->
    <script>
        // Initialize AOS animation library
        AOS.init({
            duration: 800,
            once: true,
            disable: window.innerWidth < 768 ? true : false
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Show toast notifications
            <?php if (isset($_GET['fav_removed'])): ?>
            showToast('fav-removed-toast');
            <?php endif; ?>
            
            // Dark mode toggle
            const darkModeToggle = document.getElementById('darkModeToggle');
            const darkModeToggleMobile = document.getElementById('darkModeToggleMobile');
            const htmlElement = document.documentElement;
            
            // Check for saved theme preference
            if (localStorage.getItem('darkMode') === 'enabled') {
                htmlElement.classList.add('dark');
                darkModeToggle.checked = true;
                darkModeToggleMobile.checked = true;
            }
            
            // Function to toggle dark mode
            function toggleDarkMode() {
                if (htmlElement.classList.contains('dark')) {
                    htmlElement.classList.remove('dark');
                    localStorage.setItem('darkMode', 'disabled');
                    darkModeToggle.checked = false;
                    darkModeToggleMobile.checked = false;
                } else {
                    htmlElement.classList.add('dark');
                    localStorage.setItem('darkMode', 'enabled');
                    darkModeToggle.checked = true;
                    darkModeToggleMobile.checked = true;
                }
            }
            
            // Event listeners for toggle switches
            darkModeToggle.addEventListener('change', toggleDarkMode);
            darkModeToggleMobile.addEventListener('change', toggleDarkMode);
            
            // Mobile menu toggle
            document.getElementById('menu-toggle').addEventListener('click', function() {
                const mobileMenu = document.getElementById('mobile-menu');
                mobileMenu.classList.toggle('hidden');
            });
            
            // Back to top button
            const backToTopButton = document.getElementById('back-to-top');
            
            window.addEventListener('scroll', function() {
                if (window.scrollY > 300) {
                    backToTopButton.classList.remove('opacity-0', 'invisible');
                    backToTopButton.classList.add('opacity-100', 'visible');
                } else {
                    backToTopButton.classList.remove('opacity-100', 'visible');
                    backToTopButton.classList.add('opacity-0', 'invisible');
                }
            });
            
            backToTopButton.addEventListener('click', function() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
            
            // Load recommended formations
            loadRecommendedFormations();
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
        
        // Function to load recommended formations via AJAX
        function loadRecommendedFormations() {
            // Simulating AJAX request
            setTimeout(() => {
                const loader = document.getElementById('recommended-loader');
                const recommendedFormations = document.getElementById('recommended-formations');
                
                // Hide loader and show formations
                loader.classList.add('hidden');
                recommendedFormations.classList.remove('hidden');
                
                // Demo data for recommendations - in a real application, this would come from the server
                const recommendations = [
                    {
                        id: 1,
                        title: "Introduction à la cybersécurité",
                        category_name: "Hacking Éthique",
                        category_icon: "fa-shield-alt",
                        cover_image: "image/formations/default-1.jpg",
                        level: "debutant",
                        price_per_month: 20000,
                        duration: "15 heures",
                        short_description: "Cette formation vous introduit aux principes fondamentaux de la cybersécurité, y compris l'identification des vulnérabilités, les types d'attaques courants et les pratiques de sécurité de base."
                    },
                    {
                        id: 2,
                        title: "Excel Avancé",
                        category_name: "Informatique Bureautique",
                        category_icon: "fa-desktop",
                        cover_image: "image/formations/default-2.jpg",
                        level: "avance",
                        price_per_month: 15000,
                        duration: "20 heures",
                        short_description: "Formation avancée sur Excel, concentrée sur les fonctions complexes, les tableaux croisés dynamiques, l'analyse de données et l'automatisation avec les macros VBA."
                    },
                    {
                        id: 3,
                        title: "Création de site e-commerce avec WooCommerce",
                        category_name: "E-Commerce",
                        category_icon: "fa-shopping-cart",
                        cover_image: "image/formations/default-3.jpg",
                        level: "intermediaire",
                        price_per_month: 18000,
                        duration: "25 heures",
                        short_description: "Apprenez à créer et gérer une boutique en ligne complète avec WordPress et WooCommerce, incluant la configuration des paiements, la gestion des produits et l'optimisation SEO."
                    }
                ];
                
                // Generate HTML for recommendations
                let html = '';
                recommendations.forEach(formation => {
                    html += `
                    <div class="formation-card bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden" data-aos="fade-up">
                        <!-- Formation Image -->
                        <div class="relative h-48">
                            <img src="../${formation.cover_image}" alt="${formation.title}" class="w-full h-full object-cover">
                            
                            <!-- Category Badge -->
                            <div class="absolute top-3 left-3 bg-netblue-600 text-white text-xs font-bold px-2 py-1 rounded">
                                <i class="fas ${formation.category_icon} mr-1"></i>
                                ${formation.category_name}
                            </div>
                            
                            <!-- Add to Favorites Button -->
                            <form method="POST" action="formations.php" class="absolute top-3 right-3">
                                <input type="hidden" name="formation_id" value="${formation.id}">
                                <input type="hidden" name="action" value="add_to_favorites">
                                <button type="submit" class="bg-white dark:bg-gray-700 text-gray-400 h-8 w-8 rounded-full flex items-center justify-center shadow-md hover:bg-gray-100 dark:hover:bg-gray-600 hover:text-red-500 transition-colors">
                                    <i class="far fa-heart"></i>
                                </button>
                            </form>
                            
                            <!-- Level Badge -->
                            <div class="absolute bottom-3 left-3 text-white text-xs font-bold px-2 py-1 rounded
                                ${formation.level === 'debutant' ? 'bg-green-600' : 
                                  formation.level === 'intermediaire' ? 'bg-yellow-600' : 
                                  'bg-red-600'}">
                                ${formation.level === 'debutant' ? 'Débutant' : 
                                  formation.level === 'intermediaire' ? 'Intermédiaire' : 
                                  'Avancé'}
                            </div>
                        </div>
                        
                        <!-- Formation Details -->
                        <div class="p-4">
                            <h3 class="text-lg font-bold mb-2 text-gray-800 dark:text-white line-clamp-2">
                                ${formation.title}
                            </h3>
                            
                            <div class="flex items-center mb-3 text-sm text-gray-500 dark:text-gray-400">
                                <div class="flex items-center mr-4">
                                    <i class="fas fa-clock mr-1"></i>
                                    <span>${formation.duration}</span>
                                </div>
                            </div>
                            
                            <p class="text-gray-600 dark:text-gray-300 text-sm mb-4 line-clamp-2">
                                ${formation.short_description}
                            </p>
                            
                            <div class="flex justify-between items-center">
                                <div class="text-netblue-600 dark:text-netblue-400 font-bold">
                                    ${formation.price_per_month.toLocaleString()} FCFA<span class="text-sm font-normal">/mois</span>
                                </div>
                                
                                <a href="formation-details.php?id=${formation.id}" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded text-sm font-medium transition-colors">
                                    <i class="fas fa-info-circle mr-1"></i>Détails
                                </a>
                            </div>
                        </div>
                    </div>
                    `;
                });
                
                recommendedFormations.innerHTML = html;
                
                // Re-initialize AOS for dynamically loaded content
                AOS.refresh();
            }, 1500); // Simulate loading time
        }
    </script>
</body>
</html>