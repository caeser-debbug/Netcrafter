<?php
// Initialisation de la session
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    // Sauvegarder l'URL actuelle pour y revenir après la connexion
    $_SESSION['redirect_url'] = "certificates.php";
    
    // Rediriger vers la page de connexion
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

$user_id = $_SESSION['user_id'];

// Récupérer les informations de l'utilisateur
$user_query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

// Recherche et tri
$search_term = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'date_desc';

// Requête de base pour récupérer les certificats
$base_query = "SELECT c.*, f.title as formation_title, f.id as formation_id, f.category_id, 
              fc.name as category_name, fc.icon as category_icon,
              qa.score, c.created_at as certificate_date
              FROM certificates c
              JOIN formations f ON c.formation_id = f.id
              JOIN formation_categories fc ON f.category_id = fc.id
              JOIN quiz_attempts qa ON c.quiz_attempt_id = qa.id
              WHERE c.user_id = ?";

// Ajouter la recherche si présente
if (!empty($search_term)) {
    $base_query .= " AND (f.title LIKE CONCAT('%', ?, '%') OR c.certificate_number LIKE CONCAT('%', ?, '%'))";
}

// Ajouter le tri
if ($sort_by == 'date_asc') {
    $base_query .= " ORDER BY c.created_at ASC";
} elseif ($sort_by == 'score') {
    $base_query .= " ORDER BY qa.score DESC, c.created_at DESC";
} elseif ($sort_by == 'title') {
    $base_query .= " ORDER BY f.title ASC";
} elseif ($sort_by == 'category') {
    $base_query .= " ORDER BY fc.name ASC, f.title ASC";
} else { // date_desc par défaut
    $base_query .= " ORDER BY c.created_at DESC";
}

// Préparer et exécuter la requête
$stmt = $conn->prepare($base_query);

if (!empty($search_term)) {
    $stmt->bind_param("iss", $user_id, $search_term, $search_term);
} else {
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$certificates_result = $stmt->get_result();
$certificates = [];

while ($certificate = $certificates_result->fetch_assoc()) {
    // Récupérer l'image de couverture de la formation
    $cover_query = "SELECT cover_image FROM formations WHERE id = ?";
    $cover_stmt = $conn->prepare($cover_query);
    $cover_stmt->bind_param("i", $certificate['formation_id']);
    $cover_stmt->execute();
    $cover_result = $cover_stmt->get_result();
    $cover_data = $cover_result->fetch_assoc();
    
    // Si l'image de couverture n'est pas définie, utiliser une image par défaut
    if (empty($cover_data['cover_image'])) {
        $certificate['cover_image'] = "image/formations/default-" . rand(1, 3) . ".jpg";
    } else {
        $certificate['cover_image'] = $cover_data['cover_image'];
    }
    
    $certificates[] = $certificate;
}

// Fermeture de la connexion à la base de données
$conn->close();
?>

<!DOCTYPE html>
<html lang="fr" class="light scroll">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Certificats - Netcrafter</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
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
        
        /* Certificate card hover effect */
        .certificate-card {
            transition: all 0.3s ease;
        }
        
        .certificate-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        /* Certificate styles */
        .certificate-container {
            position: relative;
            background-color: #fff;
            background-image: url('../image/certificate-bg.jpg');
            background-size: cover;
            background-position: center;
            color: #333;
            padding: 40px;
            border: 10px solid #3B82F6;
            box-shadow: 0 0 25px rgba(0,0,0,0.15);
        }
        
        .certificate-container.dark {
            background-image: url('../image/certificate-bg-dark.jpg');
            border-color: #1E40AF;
            color: #E5E7EB;
        }
        
        .certificate-inner {
            border: 2px solid #3B82F6;
            padding: 20px;
            text-align: center;
        }
        
        .certificate-inner.dark {
            border-color: #1E40AF;
        }
        
        .certificate-title {
            font-size: 28px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 10px;
            color: #1A6BE2;
        }
        
        .certificate-title.dark {
            color: #3B82F6;
        }
        
        .certificate-logo {
            max-width: 100px;
            margin: 0 auto 15px;
        }
        
        .certificate-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        
        .certificate-text {
            font-size: 16px;
            margin-bottom: 20px;
        }
        
        .certificate-formation {
            font-weight: bold;
            font-size: 20px;
            margin-bottom: 20px;
        }
        
        .certificate-date {
            font-style: italic;
            margin-bottom: 15px;
        }
        
        .certificate-signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        
        .certificate-signature {
            text-align: center;
            flex: 1;
        }
        
        .certificate-signature img {
            max-width: 120px;
            margin: 0 auto 10px;
        }
        
        .certificate-number {
            font-size: 12px;
            position: absolute;
            bottom: 20px;
            right: 40px;
        }
        
        /* Side Navigation */
        .sidenav {
            transition: all 0.3s ease;
            z-index: 50;
        }
        
        @media (max-width: 768px) {
            .sidenav {
                width: 280px;
                transform: translateX(-100%);
            }
            
            .sidenav.open {
                transform: translateX(0);
            }
        }
        
        @media (min-width: 768px) {
            .sidenav {
                width: 280px;
            }
            
            .sidenav.collapsed {
                width: 70px;
            }
            
            .sidenav.collapsed .nav-text,
            .sidenav.collapsed .sidenav-title span {
                opacity: 0;
                white-space: nowrap;
            }
            
            .content-area {
                transition: all 0.3s ease;
                margin-left: 280px;
            }
            
            .content-area.nav-collapsed {
                margin-left: 70px;
            }
        }

        .overlay {
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        
        .overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        /* Print styling */
        @media print {
            body * {
                visibility: hidden;
            }
            
            #certificate-print, #certificate-print * {
                visibility: visible;
            }
            
            #certificate-print {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
            }
            
            .no-print {
                display: none !important;
            }
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
    <!-- Overlay for mobile menu -->
    <div id="overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 overlay" onclick="toggleMobileMenu()"></div>
    
    <!-- Side Navigation -->
    <aside id="sidenav" class="sidenav fixed h-full bg-white dark:bg-gray-800 shadow-lg z-50">
        <!-- Logo and collapse button -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center sidenav-title">
                <img src="../image/logo-n.png" alt="Netcrafter Logo" class="h-8 mr-2">
                <span class="text-lg font-bold text-netblue-600 dark:text-netblue-400 transition-opacity duration-300 whitespace-nowrap">NETCRAFTER</span>
            </div>
            <button id="sidenav-toggle" class="sidenav-toggle text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 focus:outline-none md:block hidden">
                <i class="fas fa-chevron-left"></i>
            </button>
        </div>
        
        <!-- User info -->
        <div class="px-4 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center">
                <div class="w-10 h-10 rounded-full bg-netblue-600 dark:bg-netblue-700 flex items-center justify-center text-white font-bold text-lg flex-shrink-0">
                    <?php echo strtoupper(substr($user['firstname'], 0, 1) . substr($user['lastname'], 0, 1)); ?>
                </div>
                <div class="ml-3 overflow-hidden">
                    <p class="font-medium text-gray-800 dark:text-white nav-text transition-opacity duration-300 truncate"><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 nav-text transition-opacity duration-300 truncate"><?php echo htmlspecialchars($user['phone']); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Navigation Menu -->
        <nav class="mt-4 px-2 overflow-y-auto" style="max-height: calc(100vh - 200px);">
            <ul class="space-y-1">
                <li>
                    <a href="dashboard.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-tachometer-alt w-6 text-center"></i>
                        <span class="ml-2 nav-text transition-opacity duration-300 truncate">Tableau de bord</span>
                    </a>
                </li>
                <li>
                    <a href="my-formations.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-graduation-cap w-6 text-center"></i>
                        <span class="ml-2 nav-text transition-opacity duration-300 truncate">Mes formations</span>
                    </a>
                </li>
                <li>
                    <a href="formation-favorites.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-heart w-6 text-center"></i>
                        <span class="ml-2 nav-text transition-opacity duration-300 truncate">Favoris</span>
                        <?php if (!empty($_SESSION['formation_favorites'])): ?>
                        <span class="ml-auto bg-red-500 text-white text-xs rounded-full h-5 min-w-[1.25rem] flex items-center justify-center nav-text transition-opacity duration-300">
                            <?php echo count($_SESSION['formation_favorites']); ?>
                        </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="certificates.php" class="flex items-center px-3 py-2 text-base rounded-lg bg-netblue-100 dark:bg-netblue-900/30 text-netblue-800 dark:text-netblue-300">
                        <i class="fas fa-certificate w-6 text-center"></i>
                        <span class="ml-2 nav-text transition-opacity duration-300 truncate">Certificats</span>
                        <?php if (!empty($certificates)): ?>
                        <span class="ml-auto bg-netblue-500 text-white text-xs rounded-full h-5 min-w-[1.25rem] flex items-center justify-center nav-text transition-opacity duration-300">
                            <?php echo count($certificates); ?>
                        </span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="forum.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-comments w-6 text-center"></i>
                        <span class="ml-2 nav-text transition-opacity duration-300 truncate">Forum</span>
                    </a>
                </li>
                <li>
                    <a href="quiz.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-question-circle w-6 text-center"></i>
                        <span class="ml-2 nav-text transition-opacity duration-300 truncate">Quiz</span>
                    </a>
                </li>
                <li class="pt-2 mt-2 border-t border-gray-200 dark:border-gray-700">
                    <a href="profile.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-user-edit w-6 text-center"></i>
                        <span class="ml-2 nav-text transition-opacity duration-300 truncate">Modifier profil</span>
                    </a>
                </li>
                <li>
                    <a href="settings.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-cog w-6 text-center"></i>
                        <span class="ml-2 nav-text transition-opacity duration-300 truncate">Paramètres</span>
                    </a>
                </li>
            </ul>
        </nav>
        
        <!-- Logout and Theme Toggle -->
        <div class="absolute bottom-0 left-0 right-0 border-t border-gray-200 dark:border-gray-700 p-4 bg-white dark:bg-gray-800">
            <div class="flex items-center mb-4">
                <span class="text-gray-700 dark:text-gray-300 mr-2 nav-text transition-opacity duration-300 truncate">Mode sombre</span>
                <label class="theme-switch relative inline-block w-12 h-6 ml-auto">
                    <input type="checkbox" id="darkModeToggle" class="opacity-0 w-0 h-0">
                    <span class="slider absolute cursor-pointer inset-0 bg-gray-300 rounded-full transition-all duration-300 before:absolute before:h-4 before:w-4 before:left-1 before:bottom-1 before:bg-white before:rounded-full before:transition-all before:duration-300"></span>
                </label>
            </div>
            <a href="logout.php" class="flex items-center justify-center px-3 py-2 rounded-lg text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors duration-200">
                <i class="fas fa-sign-out-alt w-6 text-center"></i>
                <span class="ml-2 nav-text transition-opacity duration-300 truncate">Déconnexion</span>
            </a>
        </div>
    </aside>
    
    <!-- Main Content -->
    <div id="content" class="content-area transition-all duration-300">
        <!-- Top Bar -->
        <header class="bg-white dark:bg-gray-800 shadow-sm sticky top-0 z-20">
            <div class="flex items-center justify-between px-4 py-3">
                <!-- Mobile Menu Toggle -->
                <button id="mobile-menu-toggle" class="md:hidden text-gray-700 dark:text-white focus:outline-none">
                    <i class="fas fa-bars text-2xl"></i>
                </button>
                
                <!-- Page Title -->
                <h1 class="text-xl font-bold text-gray-800 dark:text-white">Mes Certificats</h1>
                
                <!-- Right Menu -->
                <div class="flex items-center">
                    <a href="formations.php" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded-lg transition-colors hidden md:block">
                        <i class="fas fa-search mr-2"></i>Explorer les formations
                    </a>
                    <!-- Mobile-Only Explore Button -->
                    <a href="formations.php" class="text-gray-700 dark:text-white md:hidden">
                        <i class="fas fa-search text-2xl"></i>
                    </a>
                </div>
            </div>
        </header>
        
        <!-- Main Content Area -->
        <main class="p-4">
            <!-- Search and Sort Bar -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 mb-6" data-aos="fade-up">
                <div class="flex flex-col sm:flex-row gap-4">
                    <!-- Search -->
                    <div class="flex-grow">
                        <form action="certificates.php" method="GET" class="relative">
                            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_by); ?>">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Rechercher un certificat..." class="w-full pl-10 pr-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                            <button type="submit" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 dark:text-gray-500">
                                <i class="fas fa-search"></i>
                            </button>
                            <?php if (!empty($search_term)): ?>
                            <a href="?sort=<?php echo htmlspecialchars($sort_by); ?>" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300">
                                <i class="fas fa-times"></i>
                            </a>
                            <?php endif; ?>
                        </form>
                    </div>
                    
                    <!-- Sort -->
                    <div class="sm:w-52">
                        <form action="certificates.php" method="GET" id="sort-form">
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
                            <select name="sort" onchange="this.form.submit()" class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 bg-white dark:bg-gray-700 text-gray-800 dark:text-white">
                                <option value="date_desc" <?php echo $sort_by == 'date_desc' ? 'selected' : ''; ?>>Trier par : Plus récents</option>
                                <option value="date_asc" <?php echo $sort_by == 'date_asc' ? 'selected' : ''; ?>>Trier par : Plus anciens</option>
                                <option value="score" <?php echo $sort_by == 'score' ? 'selected' : ''; ?>>Trier par : Score</option>
                                <option value="title" <?php echo $sort_by == 'title' ? 'selected' : ''; ?>>Trier par : Titre</option>
                                <option value="category" <?php echo $sort_by == 'category' ? 'selected' : ''; ?>>Trier par : Catégorie</option>
                            </select>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Certificates Grid -->
            <?php if (empty($certificates)): ?>
            <!-- Empty state -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8 text-center" data-aos="fade-up">
                <div class="mb-6 text-gray-400 dark:text-gray-500">
                    <i class="fas fa-certificate text-6xl"></i>
                </div>
                <h2 class="text-2xl font-bold mb-4 dark:text-white">Aucun certificat trouvé</h2>
                <p class="text-gray-600 dark:text-gray-400 mb-6 max-w-md mx-auto">
                    <?php if (!empty($search_term)): ?>
                        Aucun certificat ne correspond à votre recherche "<?php echo htmlspecialchars($search_term); ?>". Veuillez essayer avec d'autres termes.
                    <?php else: ?>
                        Vous n'avez pas encore obtenu de certificat. Terminez une formation et passez le quiz final pour obtenir votre premier certificat.
                    <?php endif; ?>
                </p>
                <a href="my-formations.php" class="inline-block bg-netblue-600 hover:bg-netblue-700 text-white font-bold py-3 px-6 rounded-lg transition-colors">
                    <i class="fas fa-graduation-cap mr-2"></i>Voir mes formations
                </a>
            </div>
            <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($certificates as $certificate): ?>
                <div class="certificate-card bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden" data-aos="fade-up">
                    <!-- Certificate Preview -->
                    <div class="relative h-48 bg-gray-200 dark:bg-gray-700 overflow-hidden">
                        <img src="../<?php echo htmlspecialchars($certificate['cover_image']); ?>" alt="<?php echo htmlspecialchars($certificate['formation_title']); ?>" class="w-full h-full object-cover opacity-30">
                        <div class="absolute inset-0 flex flex-col items-center justify-center p-4 text-center">
                            <div class="bg-white dark:bg-gray-800 rounded-full w-16 h-16 flex items-center justify-center mb-2">
                                <i class="fas fa-certificate text-2xl text-netblue-600 dark:text-netblue-400"></i>
                            </div>
                            <h3 class="font-bold text-gray-800 dark:text-white text-lg">Certificat de réussite</h3>
                            <p class="text-gray-600 dark:text-gray-400 text-sm mt-1"><?php echo htmlspecialchars($certificate['formation_title']); ?></p>
                        </div>
                        
                        <!-- Category Badge -->
                        <div class="absolute top-3 left-3 bg-netblue-600 text-white text-xs font-bold px-2 py-1 rounded">
                            <i class="fas <?php echo htmlspecialchars($certificate['category_icon']); ?> mr-1"></i>
                            <?php echo htmlspecialchars($certificate['category_name']); ?>
                        </div>
                    </div>
                    
                    <!-- Certificate Details -->
                    <div class="p-4">
                        <div class="flex justify-between items-center mb-3">
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                <i class="fas fa-calendar-alt mr-1"></i>
                                <?php echo date('d/m/Y', strtotime($certificate['certificate_date'])); ?>
                            </div>
                            <div class="flex items-center">
                                <div class="text-sm font-medium">Score:</div>
                                <div class="ml-2 bg-netblue-100 dark:bg-netblue-900 text-netblue-800 dark:text-netblue-300 text-xs font-bold px-2 py-1 rounded">
                                    <?php echo htmlspecialchars($certificate['score']); ?>%
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <div class="font-medium text-sm text-gray-600 dark:text-gray-400">Numéro de certificat:</div>
                            <div class="text-gray-800 dark:text-white text-sm font-mono mt-1 bg-gray-100 dark:bg-gray-700 p-2 rounded overflow-x-auto">
                                <?php echo htmlspecialchars($certificate['certificate_number']); ?>
                            </div>
                        </div>
                        
                        <div class="flex flex-wrap gap-2 mt-4">
                            <button type="button" class="flex-1 bg-netblue-600 hover:bg-netblue-700 text-white px-3 py-2 rounded text-sm font-medium transition-colors" onclick="showCertificate('<?php echo htmlspecialchars(addslashes($certificate['certificate_number'])); ?>', '<?php echo htmlspecialchars(addslashes($user['firstname'] . ' ' . $user['lastname'])); ?>', '<?php echo htmlspecialchars(addslashes($certificate['formation_title'])); ?>', '<?php echo date('d/m/Y', strtotime($certificate['certificate_date'])); ?>', '<?php echo htmlspecialchars($certificate['score']); ?>')">
                                <i class="fas fa-eye mr-1"></i>Afficher
                            </button>
                            <button type="button" class="flex-1 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-white px-3 py-2 rounded text-sm font-medium transition-colors" onclick="printCertificate()">
                                <i class="fas fa-print mr-1"></i>Imprimer
                            </button>
                            <a href="<?php echo htmlspecialchars($certificate['certificate_url']); ?>" download class="flex-1 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-white px-3 py-2 rounded text-sm font-medium transition-colors text-center">
                                <i class="fas fa-download mr-1"></i>Télécharger
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Recommended Formations -->
            <div class="mt-12">
                <h2 class="text-xl font-bold mb-6 dark:text-white">Formations recommandées pour vous</h2>
                
                <div id="recommended-loader" class="flex justify-center py-8">
                    <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-netblue-600"></div>
                </div>
                
                <div id="recommended-formations" class="grid grid-cols-1 md:grid-cols-3 gap-6 hidden">
                    <!-- Recommendations will be loaded here via AJAX -->
                </div>
            </div>
        </main>
        
        <!-- Footer -->
        <footer class="bg-white dark:bg-gray-800 shadow-lg mt-auto py-4">
            <div class="px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col sm:flex-row justify-between items-center">
                    <div class="text-center sm:text-left mb-4 sm:mb-0">
                        <p class="text-gray-600 dark:text-gray-400">
                            © 2023 Netcrafter. Tous droits réservés.
                        </p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <a href="https://www.facebook.com/share/1Y7kHRs16L/" class="text-gray-600 dark:text-gray-400 hover:text-netblue-600 dark:hover:text-netblue-400">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="https://www.instagram.com/netcrafter.niger?igsh=NzJ2bzM2aWRnMzho" class="text-gray-600 dark:text-gray-400 hover:text-netblue-600 dark:hover:text-netblue-400">
                            <i class="fab fa-instagram"></i>
                        </a>
                    </div>
                </div>
            </div>
        </footer>
    </div>
    
    <!-- Certificate Modal -->
    <div id="certificate-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 opacity-0 invisible transition-all duration-300">
        <div class="absolute inset-0 bg-black bg-opacity-50" onclick="hideCertificate()"></div>
        <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-4xl w-full max-h-screen overflow-y-auto">
            <!-- Modal Close Button -->
            <button type="button" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 z-10" onclick="hideCertificate()">
                <i class="fas fa-times text-xl"></i>
            </button>
            
            <!-- Certificate Container -->
            <div id="certificate-container" class="p-4">
                <div id="certificate-print">
                    <div class="certificate-container" id="certificate-display">
                        <div class="certificate-inner">
                            <img src="../image/logo-n.png" alt="Netcrafter Logo" class="certificate-logo">
                            <div class="certificate-title">Certificat de Réussite</div>
                            <p class="certificate-text">Ce certificat atteste que</p>
                            <div class="certificate-name" id="certificate-name">Prénom Nom</div>
                            <p class="certificate-text">a complété avec succès la formation</p>
                            <div class="certificate-formation" id="certificate-formation">Titre de la formation</div>
                            <p class="certificate-text">
                                avec un score de <span id="certificate-score">85</span>%
                            </p>
                            <div class="certificate-date" id="certificate-date">Délivré le 20 mai 2025</div>
                            
                            <div class="certificate-signatures">
                                <div class="certificate-signature">
                                    <img src="../image/signature1.png" alt="Signature du Directeur">
                                    <div>Directeur de la Formation</div>
                                </div>
                                <div class="certificate-signature">
                                    <img src="../image/signature2.png" alt="Signature du Formateur">
                                    <div>Formateur Principal</div>
                                </div>
                            </div>
                        </div>
                        <div class="certificate-number" id="certificate-number">NC-CERT-2023-001</div>
                    </div>
                </div>
                
                <!-- Certificate Actions -->
                <div class="mt-6 flex flex-wrap gap-4 justify-center no-print">
                    <button type="button" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded-lg transition-colors" onclick="printCertificate()">
                        <i class="fas fa-print mr-2"></i>Imprimer
                    </button>
                    <button type="button" class="bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-white px-4 py-2 rounded-lg transition-colors" id="download-certificate">
                        <i class="fas fa-download mr-2"></i>Télécharger PDF
                    </button>
                    <button type="button" class="bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-white px-4 py-2 rounded-lg transition-colors" onclick="shareCertificate()">
                        <i class="fas fa-share-alt mr-2"></i>Partager
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Share Modal -->
    <div id="share-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 opacity-0 invisible transition-all duration-300">
        <div class="absolute inset-0 bg-black bg-opacity-50" onclick="hideShareModal()"></div>
        <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6">
            <button type="button" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200" onclick="hideShareModal()">
                <i class="fas fa-times"></i>
            </button>
            
            <h3 class="text-xl font-bold mb-4 dark:text-white">Partager votre certificat</h3>
            
            <div class="space-y-4">
                <p class="text-gray-600 dark:text-gray-400">
                    Partagez votre réussite sur les réseaux sociaux ou envoyez le certificat par email.
                </p>
                
                <div class="flex flex-wrap gap-3 justify-center">
                    <a href="#" class="bg-blue-600 hover:bg-blue-700 text-white w-12 h-12 rounded-full flex items-center justify-center transition-colors">
                        <i class="fab fa-facebook-f text-lg"></i>
                    </a>
                    <a href="#" class="bg-blue-400 hover:bg-blue-500 text-white w-12 h-12 rounded-full flex items-center justify-center transition-colors">
                        <i class="fab fa-twitter text-lg"></i>
                    </a>
                    <a href="#" class="bg-blue-700 hover:bg-blue-800 text-white w-12 h-12 rounded-full flex items-center justify-center transition-colors">
                        <i class="fab fa-linkedin-in text-lg"></i>
                    </a>
                    <a href="#" class="bg-green-600 hover:bg-green-700 text-white w-12 h-12 rounded-full flex items-center justify-center transition-colors">
                        <i class="fab fa-whatsapp text-lg"></i>
                    </a>
                    <a href="#" class="bg-purple-600 hover:bg-purple-700 text-white w-12 h-12 rounded-full flex items-center justify-center transition-colors">
                        <i class="far fa-envelope text-lg"></i>
                    </a>
                </div>
                
                <div>
                    <label for="certificate-url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Lien du certificat</label>
                    <div class="flex">
                        <input type="text" id="certificate-url" value="https://netcrafter.com/verify/NC-CERT-2023-001" class="flex-grow border border-gray-300 dark:border-gray-600 rounded-l-md bg-white dark:bg-gray-700 text-gray-800 dark:text-white px-3 py-2 focus:outline-none focus:ring-2 focus:ring-netblue-500" readonly>
                        <button type="button" class="bg-netblue-600 hover:bg-netblue-700 text-white px-3 py-2 rounded-r-md transition-colors" onclick="copyCertificateUrl()">
                            <i class="far fa-copy"></i>
                        </button>
                    </div>
                    <p id="copy-message" class="text-green-600 dark:text-green-400 text-sm mt-1 hidden">Lien copié !</p>
                </div>
            </div>
        </div>
    </div>

    <!-- AOS Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <!-- html2canvas for certificate download -->
    <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>
    <!-- jsPDF for certificate download -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <!-- Main JavaScript -->
    <script>
        // Initialize AOS animation library
        AOS.init({
            duration: 800,
            once: true,
            disable: window.innerWidth < 768 ? true : false
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            // Sidenav toggle
            const sidenav = document.getElementById('sidenav');
            const sidenavToggle = document.getElementById('sidenav-toggle');
            const content = document.getElementById('content');
            const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
            const overlay = document.getElementById('overlay');
            
            // Function to toggle sidenav on desktop
            function toggleSidenav() {
                if (window.innerWidth >= 768) {
                    sidenav.classList.toggle('collapsed');
                    content.classList.toggle('nav-collapsed');
                    
                    // Save preference
                    const isCollapsed = sidenav.classList.contains('collapsed');
                    localStorage.setItem('sidenavCollapsed', isCollapsed.toString());
                }
            }
            
            // Function to toggle sidenav on mobile
            function toggleMobileMenu() {
                sidenav.classList.toggle('open');
                overlay.classList.toggle('active');
                document.body.classList.toggle('overflow-hidden');
            }
            
            sidenavToggle.addEventListener('click', toggleSidenav);
            mobileMenuToggle.addEventListener('click', toggleMobileMenu);
            
            // Check for saved sidenav state
            const savedSidenavState = localStorage.getItem('sidenavCollapsed');
            if (savedSidenavState === 'true' && window.innerWidth >= 768) {
                sidenav.classList.add('collapsed');
                content.classList.add('nav-collapsed');
            }
            
            // Dark mode toggle
            const darkModeToggle = document.getElementById('darkModeToggle');
            const htmlElement = document.documentElement;
            
            // Check for saved theme preference
            if (localStorage.getItem('darkMode') === 'enabled') {
                htmlElement.classList.add('dark');
                darkModeToggle.checked = true;
                
                // Update certificate style for dark mode
                document.getElementById('certificate-display').classList.add('dark');
                document.querySelector('.certificate-inner').classList.add('dark');
                document.querySelector('.certificate-title').classList.add('dark');
            }
            
            // Function to toggle dark mode
            function toggleDarkMode() {
                if (htmlElement.classList.contains('dark')) {
                    htmlElement.classList.remove('dark');
                    localStorage.setItem('darkMode', 'disabled');
                    darkModeToggle.checked = false;
                    
                    // Update certificate style for light mode
                    document.getElementById('certificate-display').classList.remove('dark');
                    document.querySelector('.certificate-inner').classList.remove('dark');
                    document.querySelector('.certificate-title').classList.remove('dark');
                } else {
                    htmlElement.classList.add('dark');
                    localStorage.setItem('darkMode', 'enabled');
                    darkModeToggle.checked = true;
                    
                    // Update certificate style for dark mode
                    document.getElementById('certificate-display').classList.add('dark');
                    document.querySelector('.certificate-inner').classList.add('dark');
                    document.querySelector('.certificate-title').classList.add('dark');
                }
            }
            
            // Event listener for toggle switch
            darkModeToggle.addEventListener('change', toggleDarkMode);
            
            // Handle window resizing
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 768) {
                    // Reset mobile menu state when switching to desktop
                    sidenav.classList.remove('open');
                    overlay.classList.remove('active');
                    document.body.classList.remove('overflow-hidden');
                    
                    // Apply saved collapsed state
                    const savedSidenavState = localStorage.getItem('sidenavCollapsed');
                    if (savedSidenavState === 'true') {
                        sidenav.classList.add('collapsed');
                        content.classList.add('nav-collapsed');
                    } else {
                        sidenav.classList.remove('collapsed');
                        content.classList.remove('nav-collapsed');
                    }
                }
            });
            
            // PDF Download
            document.getElementById('download-certificate').addEventListener('click', function() {
                // Show loading state
                this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Préparation...';
                this.disabled = true;
                
                // Set timeout to allow the UI to update before heavy processing
                setTimeout(() => {
                    const certificateElem = document.getElementById('certificate-print');
                    const certificateNumber = document.getElementById('certificate-number').textContent;
                    
                    // Use html2canvas to capture the certificate
                    html2canvas(certificateElem, {
                        scale: 2, // Higher quality
                        useCORS: true,
                        logging: false
                    }).then(canvas => {
                        // Initialize jsPDF
                        const { jsPDF } = window.jspdf;
                        const pdf = new jsPDF({
                            orientation: 'landscape',
                            unit: 'mm',
                            format: 'a4'
                        });
                        
                        // Add the canvas image to the PDF
                        const imgData = canvas.toDataURL('image/jpeg', 1.0);
                        const pdfWidth = pdf.internal.pageSize.getWidth();
                        const pdfHeight = pdf.internal.pageSize.getHeight();
                        pdf.addImage(imgData, 'JPEG', 0, 0, pdfWidth, pdfHeight);
                        
                        // Save the PDF
                        pdf.save(`Certificat_${certificateNumber}.pdf`);
                        
                        // Reset button state
                        this.innerHTML = '<i class="fas fa-download mr-2"></i>Télécharger PDF';
                        this.disabled = false;
                    });
                }, 100);
            });
            
            // Load recommended formations
            loadRecommendedFormations();
        });
        
        // Show certificate modal
        function showCertificate(number, name, formation, date, score) {
            // Update certificate content
            document.getElementById('certificate-number').textContent = number;
            document.getElementById('certificate-name').textContent = name;
            document.getElementById('certificate-formation').textContent = formation;
            document.getElementById('certificate-date').textContent = 'Délivré le ' + date;
            document.getElementById('certificate-score').textContent = score;
            document.getElementById('certificate-url').value = 'https://netcrafter.com/verify/' + number;
            
            // Show modal
            const modal = document.getElementById('certificate-modal');
            modal.classList.remove('opacity-0', 'invisible');
            document.body.style.overflow = 'hidden'; // Prevent scrolling
        }
        
        // Hide certificate modal
        function hideCertificate() {
            const modal = document.getElementById('certificate-modal');
            modal.classList.add('opacity-0', 'invisible');
            document.body.style.overflow = ''; // Re-enable scrolling
        }
        
        // Print certificate
        function printCertificate() {
            window.print();
        }
        
        // Show share modal
        function shareCertificate() {
            hideCertificate(); // Hide certificate modal
            
            const shareModal = document.getElementById('share-modal');
            shareModal.classList.remove('opacity-0', 'invisible');
        }
        
        // Hide share modal
        function hideShareModal() {
            const shareModal = document.getElementById('share-modal');
            shareModal.classList.add('opacity-0', 'invisible');
        }
        
        // Copy certificate URL
        function copyCertificateUrl() {
            const urlInput = document.getElementById('certificate-url');
            const copyMessage = document.getElementById('copy-message');
            
            // Select the text field
            urlInput.select();
            urlInput.setSelectionRange(0, 99999); // For mobile devices
            
            // Copy the text
            document.execCommand('copy');
            
            // Show message
            copyMessage.classList.remove('hidden');
            
            // Hide message after 3 seconds
            setTimeout(() => {
                copyMessage.classList.add('hidden');
            }, 3000);
        }
        
        // Load recommended formations via AJAX
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
                        title: "Administration réseau avancée",
                        category_name: "Réseau Informatique",
                        category_icon: "fa-network-wired",
                        cover_image: "image/formations/default-1.jpg",
                        level: "avance",
                        price_per_month: 25000,
                        short_description: "Configurez et gérez des réseaux d'entreprise complexes"
                    },
                    {
                        id: 2,
                        title: "Excel Avancé",
                        category_name: "Informatique Bureautique",
                        category_icon: "fa-desktop",
                        cover_image: "image/formations/default-2.jpg",
                        level: "avance",
                        price_per_month: 15000,
                        short_description: "Devenez un expert des tableaux croisés dynamiques et des macros"
                    },
                    {
                        id: 3,
                        title: "Création de site e-commerce avec WooCommerce",
                        category_name: "E-Commerce",
                        category_icon: "fa-shopping-cart",
                        cover_image: "image/formations/default-3.jpg",
                        level: "intermediaire",
                        price_per_month: 18000,
                        short_description: "Apprenez à créer et gérer une boutique en ligne complète avec WordPress et WooCommerce"
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
        
        // Make toggleMobileMenu function globally accessible
        function toggleMobileMenu() {
            const sidenav = document.getElementById('sidenav');
            const overlay = document.getElementById('overlay');
            
            sidenav.classList.toggle('open');
            overlay.classList.toggle('active');
            document.body.classList.toggle('overflow-hidden');
        }
    </script>
</body>
</html>