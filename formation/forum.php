<?php
// Initialisation de la session
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    // Sauvegarder l'URL actuelle pour y revenir après la connexion
    $_SESSION['redirect_url'] = "forum.php" . 
        (isset($_GET['formation_id']) ? "?formation_id=" . $_GET['formation_id'] : "") .
        (isset($_GET['topic_id']) ? "&topic_id=" . $_GET['topic_id'] : "");
    
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

// Paramètres de la page
$formation_id = isset($_GET['formation_id']) ? intval($_GET['formation_id']) : 0;
$topic_id = isset($_GET['topic_id']) ? intval($_GET['topic_id']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

$messages = [];
$current_formation = null;

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_topic'])) {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $formation_id_post = intval($_POST['formation_id']);
        
        if (!empty($title) && !empty($content)) {
            // Vérifier que l'utilisateur a accès à cette formation
            if ($formation_id_post > 0) {
                $access_query = "SELECT id FROM formation_subscriptions 
                               WHERE user_id = ? AND formation_id = ? 
                               AND status = 'active' AND end_date >= CURDATE()";
                $stmt = $conn->prepare($access_query);
                $stmt->bind_param("ii", $user_id, $formation_id_post);
                $stmt->execute();
                
                if ($stmt->get_result()->num_rows === 0) {
                    $messages[] = ['type' => 'error', 'text' => 'Vous n\'avez pas accès à cette formation.'];
                } else {
                    // Créer le sujet
                    $create_query = "INSERT INTO forum_topics (user_id, formation_id, title, content) VALUES (?, ?, ?, ?)";
                    $stmt = $conn->prepare($create_query);
                    $stmt->bind_param("iiss", $user_id, $formation_id_post, $title, $content);
                    
                    if ($stmt->execute()) {
                        $new_topic_id = $conn->insert_id;
                        $messages[] = ['type' => 'success', 'text' => 'Sujet créé avec succès !'];
                        header("Location: forum.php?topic_id=" . $new_topic_id);
                        exit;
                    } else {
                        $messages[] = ['type' => 'error', 'text' => 'Erreur lors de la création du sujet.'];
                    }
                }
            } else {
                // Sujet général
                $create_query = "INSERT INTO forum_topics (user_id, title, content) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($create_query);
                $stmt->bind_param("iss", $user_id, $title, $content);
                
                if ($stmt->execute()) {
                    $new_topic_id = $conn->insert_id;
                    $messages[] = ['type' => 'success', 'text' => 'Sujet créé avec succès !'];
                    header("Location: forum.php?topic_id=" . $new_topic_id);
                    exit;
                } else {
                    $messages[] = ['type' => 'error', 'text' => 'Erreur lors de la création du sujet.'];
                }
            }
        } else {
            $messages[] = ['type' => 'error', 'text' => 'Veuillez remplir tous les champs obligatoires.'];
        }
    }
    
    if (isset($_POST['reply_topic'])) {
        $reply_content = trim($_POST['reply_content']);
        $reply_topic_id = intval($_POST['topic_id']);
        
        if (!empty($reply_content) && $reply_topic_id > 0) {
            // Vérifier que le sujet existe
            $topic_check = "SELECT formation_id FROM forum_topics WHERE id = ?";
            $stmt = $conn->prepare($topic_check);
            $stmt->bind_param("i", $reply_topic_id);
            $stmt->execute();
            $topic_result = $stmt->get_result();
            
            if ($topic_result->num_rows > 0) {
                $topic_data = $topic_result->fetch_assoc();
                
                // Si le sujet est lié à une formation, vérifier l'accès
                if ($topic_data['formation_id']) {
                    $access_query = "SELECT id FROM formation_subscriptions 
                                   WHERE user_id = ? AND formation_id = ? 
                                   AND status = 'active' AND end_date >= CURDATE()";
                    $stmt = $conn->prepare($access_query);
                    $stmt->bind_param("ii", $user_id, $topic_data['formation_id']);
                    $stmt->execute();
                    
                    if ($stmt->get_result()->num_rows === 0) {
                        $messages[] = ['type' => 'error', 'text' => 'Vous n\'avez pas accès à cette formation.'];
                    } else {
                        // Créer la réponse
                        $reply_query = "INSERT INTO forum_replies (topic_id, user_id, content) VALUES (?, ?, ?)";
                        $stmt = $conn->prepare($reply_query);
                        $stmt->bind_param("iis", $reply_topic_id, $user_id, $reply_content);
                        
                        if ($stmt->execute()) {
                            $messages[] = ['type' => 'success', 'text' => 'Réponse ajoutée avec succès !'];
                            header("Location: forum.php?topic_id=" . $reply_topic_id . "#reply-" . $conn->insert_id);
                            exit;
                        } else {
                            $messages[] = ['type' => 'error', 'text' => 'Erreur lors de l\'ajout de la réponse.'];
                        }
                    }
                } else {
                    // Sujet général - tout le monde peut répondre
                    $reply_query = "INSERT INTO forum_replies (topic_id, user_id, content) VALUES (?, ?, ?)";
                    $stmt = $conn->prepare($reply_query);
                    $stmt->bind_param("iis", $reply_topic_id, $user_id, $reply_content);
                    
                    if ($stmt->execute()) {
                        $messages[] = ['type' => 'success', 'text' => 'Réponse ajoutée avec succès !'];
                        header("Location: forum.php?topic_id=" . $reply_topic_id . "#reply-" . $conn->insert_id);
                        exit;
                    } else {
                        $messages[] = ['type' => 'error', 'text' => 'Erreur lors de l\'ajout de la réponse.'];
                    }
                }
            } else {
                $messages[] = ['type' => 'error', 'text' => 'Sujet introuvable.'];
            }
        } else {
            $messages[] = ['type' => 'error', 'text' => 'Veuillez saisir votre réponse.'];
        }
    }
}

// Récupérer les formations accessibles à l'utilisateur
$formations_query = "SELECT f.id, f.title, c.name as category_name, c.icon 
                    FROM formations f
                    JOIN formation_categories c ON f.category_id = c.id
                    JOIN formation_subscriptions fs ON f.id = fs.formation_id
                    WHERE fs.user_id = ? AND fs.status = 'active' AND fs.end_date >= CURDATE()
                    ORDER BY f.title ASC";
$stmt = $conn->prepare($formations_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$formations_result = $stmt->get_result();
$user_formations = [];

while ($row = $formations_result->fetch_assoc()) {
    $user_formations[] = $row;
}

// Si une formation spécifique est sélectionnée
if ($formation_id > 0) {
    $formation_query = "SELECT f.*, c.name as category_name, c.icon 
                       FROM formations f
                       JOIN formation_categories c ON f.category_id = c.id
                       WHERE f.id = ?";
    $stmt = $conn->prepare($formation_query);
    $stmt->bind_param("i", $formation_id);
    $stmt->execute();
    $formation_result = $stmt->get_result();
    
    if ($formation_result->num_rows > 0) {
        $current_formation = $formation_result->fetch_assoc();
        
        // Vérifier l'accès à cette formation
        $access_query = "SELECT id FROM formation_subscriptions 
                        WHERE user_id = ? AND formation_id = ? 
                        AND status = 'active' AND end_date >= CURDATE()";
        $stmt = $conn->prepare($access_query);
        $stmt->bind_param("ii", $user_id, $formation_id);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows === 0) {
            $messages[] = ['type' => 'error', 'text' => 'Vous n\'avez pas accès à cette formation.'];
            $formation_id = 0;
            $current_formation = null;
        }
    }
}

// Affichage d'un sujet spécifique
if ($topic_id > 0) {
    // Récupérer le sujet
    $topic_query = "SELECT ft.*, u.firstname, u.lastname, u.phone,
                   f.title as formation_title, f.id as formation_id
                   FROM forum_topics ft
                   JOIN users u ON ft.user_id = u.id
                   LEFT JOIN formations f ON ft.formation_id = f.id
                   WHERE ft.id = ?";
    $stmt = $conn->prepare($topic_query);
    $stmt->bind_param("i", $topic_id);
    $stmt->execute();
    $topic_result = $stmt->get_result();
    
    if ($topic_result->num_rows > 0) {
        $topic = $topic_result->fetch_assoc();
        
        // Vérifier l'accès si le sujet est lié à une formation
        if ($topic['formation_id']) {
            $access_query = "SELECT id FROM formation_subscriptions 
                           WHERE user_id = ? AND formation_id = ? 
                           AND status = 'active' AND end_date >= CURDATE()";
            $stmt = $conn->prepare($access_query);
            $stmt->bind_param("ii", $user_id, $topic['formation_id']);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows === 0) {
                $messages[] = ['type' => 'error', 'text' => 'Vous n\'avez pas accès à ce sujet.'];
                $topic_id = 0;
                $topic = null;
            }
        }
        
        if ($topic) {
            // Incrémenter le nombre de vues
            $update_views = "UPDATE forum_topics SET views = views + 1 WHERE id = ?";
            $stmt = $conn->prepare($update_views);
            $stmt->bind_param("i", $topic_id);
            $stmt->execute();
            
            // Récupérer les réponses
            $replies_query = "SELECT fr.*, u.firstname, u.lastname, u.phone
                             FROM forum_replies fr
                             JOIN users u ON fr.user_id = u.id
                             WHERE fr.topic_id = ?
                             ORDER BY fr.created_at ASC";
            $stmt = $conn->prepare($replies_query);
            $stmt->bind_param("i", $topic_id);
            $stmt->execute();
            $replies_result = $stmt->get_result();
            $replies = [];
            
            while ($row = $replies_result->fetch_assoc()) {
                $replies[] = $row;
            }
        }
    } else {
        $messages[] = ['type' => 'error', 'text' => 'Sujet introuvable.'];
        $topic_id = 0;
    }
}

// Liste des sujets
if ($topic_id === 0) {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 10;
    $offset = ($page - 1) * $per_page;
    
    // Construction de la requête
    $where_conditions = [];
    $params = [];
    $types = "";
    
    if ($formation_id > 0) {
        $where_conditions[] = "ft.formation_id = ?";
        $params[] = $formation_id;
        $types .= "i";
    } else {
        // Afficher les sujets généraux + les sujets des formations accessibles
        $formation_ids = array_column($user_formations, 'id');
        if (!empty($formation_ids)) {
            $placeholders = str_repeat('?,', count($formation_ids) - 1) . '?';
            $where_conditions[] = "(ft.formation_id IS NULL OR ft.formation_id IN ($placeholders))";
            $params = array_merge($params, $formation_ids);
            $types .= str_repeat('i', count($formation_ids));
        } else {
            $where_conditions[] = "ft.formation_id IS NULL";
        }
    }
    
    if (!empty($search)) {
        $where_conditions[] = "(ft.title LIKE CONCAT('%', ?, '%') OR ft.content LIKE CONCAT('%', ?, '%'))";
        $params[] = $search;
        $params[] = $search;
        $types .= "ss";
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    // Compter le total
    $count_query = "SELECT COUNT(*) as total
                   FROM forum_topics ft
                   $where_clause";
    
    if (!empty($params)) {
        $stmt = $conn->prepare($count_query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
    } else {
        $stmt = $conn->query($count_query);
    }
    
    $total_topics = $stmt->get_result()->fetch_assoc()['total'];
    $total_pages = ceil($total_topics / $per_page);
    
    // Récupérer les sujets avec pagination
    $topics_query = "SELECT ft.*, u.firstname, u.lastname, u.phone,
                    f.title as formation_title, f.id as formation_id,
                    (SELECT COUNT(*) FROM forum_replies fr WHERE fr.topic_id = ft.id) as reply_count,
                    (SELECT MAX(fr.created_at) FROM forum_replies fr WHERE fr.topic_id = ft.id) as last_reply_date
                    FROM forum_topics ft
                    JOIN users u ON ft.user_id = u.id
                    LEFT JOIN formations f ON ft.formation_id = f.id
                    $where_clause
                    ORDER BY ft.is_pinned DESC, 
                    COALESCE((SELECT MAX(fr.created_at) FROM forum_replies fr WHERE fr.topic_id = ft.id), ft.created_at) DESC
                    LIMIT ? OFFSET ?";
    
    $params[] = $per_page;
    $params[] = $offset;
    $types .= "ii";
    
    $stmt = $conn->prepare($topics_query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $topics_result = $stmt->get_result();
    $topics = [];
    
    while ($row = $topics_result->fetch_assoc()) {
        $topics[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr" class="light scroll">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forum - Netcrafter</title>
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
        
        /* Forum specific styles */
        .topic-card {
            transition: all 0.3s ease;
        }
        
        .topic-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .reply-card {
            transition: all 0.3s ease;
        }
        
        .reply-card:hover {
            background-color: rgba(59, 130, 246, 0.05);
        }
        
        /* Text editor */
        .editor-toolbar {
            border-bottom: 1px solid #e5e7eb;
        }
        
        .editor-toolbar.dark {
            border-bottom-color: #374151;
        }
        
        .editor-button {
            transition: all 0.2s ease;
        }
        
        .editor-button:hover {
            background-color: rgba(59, 130, 246, 0.1);
        }
        
        .editor-button.active {
            background-color: rgba(59, 130, 246, 0.2);
            color: #3B82F6;
        }
        
        /* Responsive text */
        @media (max-width: 640px) {
            .mobile-hide {
                display: none;
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
                    <a href="certificates.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-certificate w-6 text-center"></i>
                        <span class="ml-2 nav-text transition-opacity duration-300 truncate">Certificats</span>
                    </a>
                </li>
                <li>
                    <a href="forum.php" class="flex items-center px-3 py-2 text-base rounded-lg bg-netblue-100 dark:bg-netblue-900/30 text-netblue-800 dark:text-netblue-300">
                        <i class="fas fa-comments w-6 text-center"></i>
                        <span class="ml-2 nav-text transition-opacity duration-300 truncate">Forum</span>
                    </a>
                </li>
                <li>
                    <a href="quiz.php" class="flex items-center px-3 py-2 text-base rounded-lg bg-netblue-100 dark:bg-netblue-900/30 text-netblue-800 dark:text-netblue-300">
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
                <h1 class="text-xl font-bold text-gray-800 dark:text-white">
                    <?php if ($topic_id > 0 && isset($topic)): ?>
                        Discussion: <?php echo htmlspecialchars($topic['title']); ?>
                    <?php elseif ($current_formation): ?>
                        Forum - <?php echo htmlspecialchars($current_formation['title']); ?>
                    <?php else: ?>
                        Forum de discussion
                    <?php endif; ?>
                </h1>
                
                <!-- Right Menu -->
                <div class="flex items-center space-x-2">
                    <?php if ($topic_id === 0): ?>
                    <button id="new-topic-btn" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded-lg transition-colors hidden md:block">
                        <i class="fas fa-plus mr-2"></i>Nouveau sujet
                    </button>
                    <!-- Mobile-Only New Topic Button -->
                    <button id="new-topic-btn-mobile" class="text-gray-700 dark:text-white md:hidden">
                        <i class="fas fa-plus text-2xl"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </header>
        
        <!-- Messages -->
        <?php if (!empty($messages)): ?>
        <div class="p-4">
            <?php foreach ($messages as $message): ?>
            <div class="alert mb-4 p-4 rounded-lg <?php echo $message['type'] === 'success' ? 'bg-green-100 border-green-500 text-green-700 dark:bg-green-900 dark:text-green-300' : 'bg-red-100 border-red-500 text-red-700 dark:bg-red-900 dark:text-red-300'; ?> border-l-4">
                <i class="fas <?php echo $message['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> mr-2"></i>
                <?php echo htmlspecialchars($message['text']); ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Main Content Area -->
        <main class="p-4">
            <?php if ($topic_id > 0 && isset($topic)): ?>
            <!-- Topic View -->
            <div class="max-w-4xl mx-auto">
                <!-- Breadcrumb -->
                <nav class="mb-6 text-sm" data-aos="fade-up">
                    <ol class="flex items-center space-x-2 text-gray-600 dark:text-gray-400">
                        <li><a href="forum.php" class="hover:text-netblue-600 dark:hover:text-netblue-400">Forum</a></li>
                        <li><i class="fas fa-chevron-right mx-2"></i></li>
                        <?php if ($topic['formation_title']): ?>
                        <li><a href="forum.php?formation_id=<?php echo $topic['formation_id']; ?>" class="hover:text-netblue-600 dark:hover:text-netblue-400"><?php echo htmlspecialchars($topic['formation_title']); ?></a></li>
                        <li><i class="fas fa-chevron-right mx-2"></i></li>
                        <?php endif; ?>
                        <li class="text-gray-800 dark:text-white"><?php echo htmlspecialchars($topic['title']); ?></li>
                    </ol>
                </nav>
                
                <!-- Original Topic -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mb-6" data-aos="fade-up">
                    <div class="flex items-start space-x-4">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-netblue-600 dark:bg-netblue-700 rounded-full flex items-center justify-center text-white font-bold text-lg">
                                <?php echo strtoupper(substr($topic['firstname'], 0, 1) . substr($topic['lastname'], 0, 1)); ?>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <h2 class="text-xl font-bold dark:text-white"><?php echo htmlspecialchars($topic['title']); ?></h2>
                                    <div class="flex items-center text-sm text-gray-600 dark:text-gray-400 mt-1">
                                        <span class="font-medium"><?php echo htmlspecialchars($topic['firstname'] . ' ' . $topic['lastname']); ?></span>
                                        <span class="mx-2">•</span>
                                        <span><?php echo date('d/m/Y à H:i', strtotime($topic['created_at'])); ?></span>
                                        <span class="mx-2">•</span>
                                        <span><i class="fas fa-eye mr-1"></i><?php echo $topic['views']; ?> vues</span>
                                        <?php if ($topic['formation_title']): ?>
                                        <span class="mx-2">•</span>
                                        <span class="bg-netblue-100 dark:bg-netblue-900 text-netblue-800 dark:text-netblue-300 px-2 py-1 rounded text-xs">
                                            <?php echo htmlspecialchars($topic['formation_title']); ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($topic['is_pinned']): ?>
                                <div class="text-yellow-500">
                                    <i class="fas fa-thumbtack"></i>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="prose prose-gray max-w-none dark:prose-invert">
                                <?php echo nl2br(htmlspecialchars($topic['content'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Replies -->
                <?php if (!empty($replies)): ?>
                <div class="space-y-4 mb-6">
                    <h3 class="text-lg font-bold dark:text-white">
                        <i class="fas fa-comments mr-2 text-netblue-600 dark:text-netblue-400"></i>
                        Réponses (<?php echo count($replies); ?>)
                    </h3>
                    
                    <?php foreach ($replies as $index => $reply): ?>
                    <div id="reply-<?php echo $reply['id']; ?>" class="reply-card bg-white dark:bg-gray-800 rounded-lg shadow p-4" data-aos="fade-up" data-aos-delay="<?php echo ($index % 5) * 100; ?>">
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0">
                                <div class="w-10 h-10 bg-gray-500 dark:bg-gray-600 rounded-full flex items-center justify-center text-white font-bold">
                                    <?php echo strtoupper(substr($reply['firstname'], 0, 1) . substr($reply['lastname'], 0, 1)); ?>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                        <span class="font-medium text-gray-800 dark:text-white"><?php echo htmlspecialchars($reply['firstname'] . ' ' . $reply['lastname']); ?></span>
                                        <span class="mx-2">•</span>
                                        <span><?php echo date('d/m/Y à H:i', strtotime($reply['created_at'])); ?></span>
                                        <?php if ($reply['is_solution']): ?>
                                        <span class="ml-2 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-300 px-2 py-1 rounded text-xs">
                                            <i class="fas fa-check mr-1"></i>Solution
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="prose prose-gray max-w-none dark:prose-invert">
                                    <?php echo nl2br(htmlspecialchars($reply['content'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <!-- Reply Form -->
                <?php if (!$topic['is_locked']): ?>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6" data-aos="fade-up">
                    <h3 class="text-lg font-bold mb-4 dark:text-white">
                        <i class="fas fa-reply mr-2 text-netblue-600 dark:text-netblue-400"></i>
                        Répondre à ce sujet
                    </h3>
                    
                    <form method="POST" action="forum.php?topic_id=<?php echo $topic_id; ?>">
                        <input type="hidden" name="topic_id" value="<?php echo $topic_id; ?>">
                        
                        <div class="mb-4">
                            <label for="reply_content" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Votre réponse *</label>
                            <div class="border border-gray-300 dark:border-gray-600 rounded-lg overflow-hidden">
                                <!-- Simple toolbar -->
                                <div class="editor-toolbar bg-gray-50 dark:bg-gray-700 p-2 flex items-center space-x-1">
                                    <button type="button" class="editor-button p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-600 dark:text-gray-400" onclick="insertText('reply_content', '**', '**')" title="Gras">
                                        <i class="fas fa-bold"></i>
                                    </button>
                                    <button type="button" class="editor-button p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-600 dark:text-gray-400" onclick="insertText('reply_content', '*', '*')" title="Italique">
                                        <i class="fas fa-italic"></i>
                                    </button>
                                    <button type="button" class="editor-button p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-600 dark:text-gray-400" onclick="insertText('reply_content', '`', '`')" title="Code">
                                        <i class="fas fa-code"></i>
                                    </button>
                                    <div class="border-l border-gray-300 dark:border-gray-600 h-6 mx-2"></div>
                                    <button type="button" class="editor-button p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-600 dark:text-gray-400" onclick="insertText('reply_content', '\n- ', '')" title="Liste">
                                        <i class="fas fa-list-ul"></i>
                                    </button>
                                    <button type="button" class="editor-button p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-600 dark:text-gray-400" onclick="insertText('reply_content', '\n> ', '')" title="Citation">
                                        <i class="fas fa-quote-right"></i>
                                    </button>
                                </div>
                                <textarea id="reply_content" name="reply_content" rows="6" class="w-full p-3 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none resize-vertical border-none" placeholder="Écrivez votre réponse ici..." required></textarea>
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                <i class="fas fa-info-circle mr-1"></i>
                                Vous pouvez utiliser la barre d'outils pour formater votre texte.
                            </p>
                        </div>
                        
                        <div class="flex justify-between items-center">
                            <a href="forum.php<?php echo $topic['formation_id'] ? '?formation_id=' . $topic['formation_id'] : ''; ?>" class="text-gray-600 dark:text-gray-400 hover:text-netblue-600 dark:hover:text-netblue-400">
                                <i class="fas fa-arrow-left mr-2"></i>Retour à la liste
                            </a>
                            <button type="submit" name="reply_topic" class="bg-netblue-600 hover:bg-netblue-700 text-white font-bold py-2 px-6 rounded-lg transition-colors">
                                <i class="fas fa-paper-plane mr-2"></i>Publier la réponse
                            </button>
                        </div>
                    </form>
                </div>
                <?php else: ?>
                <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 text-center" data-aos="fade-up">
                    <i class="fas fa-lock text-yellow-600 dark:text-yellow-400 text-2xl mb-2"></i>
                    <p class="text-yellow-800 dark:text-yellow-300">Ce sujet est verrouillé et n'accepte plus de nouvelles réponses.</p>
                </div>
                <?php endif; ?>
            </div>
            
            <?php else: ?>
            <!-- Topics List -->
            <div class="max-w-6xl mx-auto">
                <!-- Forum Navigation -->
                <div class="mb-6" data-aos="fade-up">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center space-y-4 sm:space-y-0">
                        <!-- Formation Filter -->
                        <div class="flex items-center space-x-4">
                            <div class="relative">
                                <select onchange="window.location.href='forum.php' + (this.value ? '?formation_id=' + this.value : '')" class="appearance-none bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg px-4 py-2 pr-8 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                                    <option value="">Tous les forums</option>
                                    <?php foreach ($user_formations as $formation): ?>
                                    <option value="<?php echo $formation['id']; ?>" <?php echo $formation_id == $formation['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($formation['title']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="absolute inset-y-0 right-0 flex items-center px-2 pointer-events-none">
                                    <i class="fas fa-chevron-down text-gray-400"></i>
                                </div>
                            </div>
                            
                            <?php if ($current_formation): ?>
                            <div class="flex items-center bg-netblue-100 dark:bg-netblue-900 text-netblue-800 dark:text-netblue-300 px-3 py-2 rounded-lg">
                                <i class="fas <?php echo htmlspecialchars($current_formation['icon']); ?> mr-2"></i>
                                <span class="font-medium"><?php echo htmlspecialchars($current_formation['category_name']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Search -->
                        <div class="flex items-center space-x-2 w-full sm:w-auto">
                            <form method="GET" action="forum.php" class="flex items-center space-x-2 flex-1 sm:flex-initial">
                                <?php if ($formation_id > 0): ?>
                                <input type="hidden" name="formation_id" value="<?php echo $formation_id; ?>">
                                <?php endif; ?>
                                <div class="relative flex-1 sm:w-64">
                                    <input type="text" name="search" value="<?php echo htmlspecialchars($search ?? ''); ?>" placeholder="Rechercher un sujet..." class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                </div>
                                <button type="submit" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded-lg transition-colors">
                                    <i class="fas fa-search"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Topics List -->
                <?php if (empty($topics)): ?>
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8 text-center" data-aos="fade-up">
                    <div class="mb-6 text-gray-400 dark:text-gray-500">
                        <i class="fas fa-comments text-6xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold mb-4 dark:text-white">Aucun sujet trouvé</h2>
                    <p class="text-gray-600 dark:text-gray-400 mb-6">
                        <?php if (!empty($search)): ?>
                            Aucun sujet ne correspond à votre recherche "<?php echo htmlspecialchars($search); ?>".
                        <?php elseif ($current_formation): ?>
                            Il n'y a pas encore de discussions pour cette formation. Soyez le premier à poser une question !
                        <?php else: ?>
                            Il n'y a pas encore de discussions dans le forum. Commencez une conversation !
                        <?php endif; ?>
                    </p>
                    <button id="create-first-topic" class="bg-netblue-600 hover:bg-netblue-700 text-white font-bold py-3 px-6 rounded-lg transition-colors">
                        <i class="fas fa-plus mr-2"></i>Créer le premier sujet
                    </button>
                </div>
                <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($topics as $index => $topic_item): ?>
                    <div class="topic-card bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6" data-aos="fade-up" data-aos-delay="<?php echo ($index % 5) * 100; ?>">
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0">
                                <div class="w-12 h-12 bg-gray-500 dark:bg-gray-600 rounded-full flex items-center justify-center text-white font-bold text-lg">
                                    <?php echo strtoupper(substr($topic_item['firstname'], 0, 1) . substr($topic_item['lastname'], 0, 1)); ?>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center mb-1">
                                            <?php if ($topic_item['is_pinned']): ?>
                                            <i class="fas fa-thumbtack text-yellow-500 mr-2"></i>
                                            <?php endif; ?>
                                            <h3 class="text-lg font-bold dark:text-white">
                                                <a href="forum.php?topic_id=<?php echo $topic_item['id']; ?>" class="hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors">
                                                    <?php echo htmlspecialchars($topic_item['title']); ?>
                                                </a>
                                            </h3>
                                            <?php if ($topic_item['is_locked']): ?>
                                            <i class="fas fa-lock text-gray-400 ml-2"></i>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="flex flex-wrap items-center text-sm text-gray-600 dark:text-gray-400 mb-2">
                                            <span class="font-medium"><?php echo htmlspecialchars($topic_item['firstname'] . ' ' . $topic_item['lastname']); ?></span>
                                            <span class="mx-2">•</span>
                                            <span><?php echo date('d/m/Y à H:i', strtotime($topic_item['created_at'])); ?></span>
                                            <?php if ($topic_item['formation_title']): ?>
                                            <span class="mx-2">•</span>
                                            <span class="bg-netblue-100 dark:bg-netblue-900 text-netblue-800 dark:text-netblue-300 px-2 py-1 rounded text-xs">
                                                <?php echo htmlspecialchars($topic_item['formation_title']); ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <p class="text-gray-600 dark:text-gray-400 line-clamp-2">
                                            <?php echo htmlspecialchars(substr($topic_item['content'], 0, 200)) . (strlen($topic_item['content']) > 200 ? '...' : ''); ?>
                                        </p>
                                    </div>
                                    
                                    <div class="flex flex-col items-end text-sm text-gray-500 dark:text-gray-400 ml-4">
                                        <div class="flex items-center mb-1">
                                            <i class="fas fa-eye mr-1"></i>
                                            <span><?php echo $topic_item['views']; ?></span>
                                        </div>
                                        <div class="flex items-center">
                                            <i class="fas fa-comments mr-1"></i>
                                            <span><?php echo $topic_item['reply_count']; ?></span>
                                        </div>
                                        <?php if ($topic_item['last_reply_date']): ?>
                                        <div class="text-xs mt-2 text-center">
                                            <div>Dernière réponse</div>
                                            <div><?php echo date('d/m/Y', strtotime($topic_item['last_reply_date'])); ?></div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if (isset($total_pages) && $total_pages > 1): ?>
                <div class="mt-8 flex justify-center" data-aos="fade-up">
                    <nav class="inline-flex rounded-md shadow-sm -space-x-px">
                        <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?><?php echo $formation_id > 0 ? '&formation_id=' . $formation_id : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                        <span class="relative inline-flex items-center px-4 py-2 border border-netblue-500 bg-netblue-50 dark:bg-netblue-900 text-sm font-medium text-netblue-600 dark:text-netblue-300"><?php echo $i; ?></span>
                        <?php else: ?>
                        <a href="?page=<?php echo $i; ?><?php echo $formation_id > 0 ? '&formation_id=' . $formation_id : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600"><?php echo $i; ?></a>
                        <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?><?php echo $formation_id > 0 ? '&formation_id=' . $formation_id : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                        <?php endif; ?>
                    </nav>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
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

    <!-- New Topic Modal -->
    <div id="new-topic-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 opacity-0 invisible transition-all duration-300">
        <div class="absolute inset-0 bg-black bg-opacity-50" onclick="closeNewTopicModal()"></div>
        <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full max-h-screen overflow-y-auto">
            <!-- Modal Header -->
            <div class="flex items-center justify-between p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-bold dark:text-white">Créer un nouveau sujet</h3>
                <button type="button" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200" onclick="closeNewTopicModal()">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <!-- Modal Body -->
            <div class="p-4">
                <form method="POST" action="forum.php" id="new-topic-form">
                    <!-- Formation Selection -->
                    <div class="mb-4">
                        <label for="modal_formation_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Formation (optionnel)</label>
                        <select name="formation_id" id="modal_formation_id" class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 bg-white dark:bg-gray-700 text-gray-800 dark:text-white">
                            <option value="">Sujet général</option>
                            <?php foreach ($user_formations as $formation): ?>
                            <option value="<?php echo $formation['id']; ?>" <?php echo $formation_id == $formation['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($formation['title']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Sélectionnez une formation si votre sujet la concerne spécifiquement</p>
                    </div>
                    
                    <!-- Title -->
                    <div class="mb-4">
                        <label for="modal_title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Titre du sujet *</label>
                        <input type="text" name="title" id="modal_title" required class="w-full border border-gray-300 dark:border-gray-600 rounded-md px-3 py-2 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500" placeholder="Saisissez le titre de votre sujet">
                    </div>
                    
                    <!-- Content -->
                    <div class="mb-4">
                        <label for="modal_content" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Contenu *</label>
                        <div class="border border-gray-300 dark:border-gray-600 rounded-lg overflow-hidden">
                            <!-- Simple toolbar -->
                            <div class="editor-toolbar bg-gray-50 dark:bg-gray-700 p-2 flex items-center space-x-1">
                                <button type="button" class="editor-button p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-600 dark:text-gray-400" onclick="insertText('modal_content', '**', '**')" title="Gras">
                                    <i class="fas fa-bold"></i>
                                </button>
                                <button type="button" class="editor-button p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-600 dark:text-gray-400" onclick="insertText('modal_content', '*', '*')" title="Italique">
                                    <i class="fas fa-italic"></i>
                                </button>
                                <button type="button" class="editor-button p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-600 dark:text-gray-400" onclick="insertText('modal_content', '`', '`')" title="Code">
                                    <i class="fas fa-code"></i>
                                </button>
                                <div class="border-l border-gray-300 dark:border-gray-600 h-6 mx-2"></div>
                                <button type="button" class="editor-button p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-600 dark:text-gray-400" onclick="insertText('modal_content', '\n- ', '')" title="Liste">
                                    <i class="fas fa-list-ul"></i>
                                </button>
                                <button type="button" class="editor-button p-2 rounded hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-600 dark:text-gray-400" onclick="insertText('modal_content', '\n> ', '')" title="Citation">
                                    <i class="fas fa-quote-right"></i>
                                </button>
                            </div>
                            <textarea name="content" id="modal_content" rows="8" class="w-full p-3 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none resize-vertical border-none" placeholder="Décrivez votre question ou partagez votre expérience..." required></textarea>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            Utilisez la barre d'outils pour formater votre texte. Soyez précis et détaillé dans votre description.
                        </p>
                    </div>
                    
                    <!-- Guidelines -->
                    <div class="mb-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                        <h4 class="font-medium text-blue-800 dark:text-blue-300 mb-2">
                            <i class="fas fa-lightbulb mr-2"></i>Conseils pour un bon sujet
                        </h4>
                        <ul class="text-sm text-blue-700 dark:text-blue-400 space-y-1">
                            <li>• Choisissez un titre clair et descriptif</li>
                            <li>• Expliquez votre problème ou question en détail</li>
                            <li>• Mentionnez les étapes que vous avez déjà essayées</li>
                            <li>• Ajoutez des captures d'écran si nécessaire</li>
                            <li>• Soyez respectueux et courtois</li>
                        </ul>
                    </div>
                    
                    <!-- Submit Buttons -->
                    <div class="flex flex-col sm:flex-row gap-3">
                        <button type="button" onclick="closeNewTopicModal()" class="flex-1 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-white font-bold py-2 px-4 rounded-lg transition-colors">
                            Annuler
                        </button>
                        <button type="submit" name="create_topic" class="flex-1 bg-netblue-600 hover:bg-netblue-700 text-white font-bold py-2 px-4 rounded-lg transition-colors">
                            <i class="fas fa-paper-plane mr-2"></i>Créer le sujet
                        </button>
                    </div>
                </form>
            </div>
        </div>
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
                    
                    // Update toggle icon
                    const icon = sidenavToggle.querySelector('i');
                    if (sidenav.classList.contains('collapsed')) {
                        icon.classList.remove('fa-chevron-left');
                        icon.classList.add('fa-chevron-right');
                    } else {
                        icon.classList.remove('fa-chevron-right');
                        icon.classList.add('fa-chevron-left');
                    }
                    
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
            
            if (sidenavToggle) {
                sidenavToggle.addEventListener('click', toggleSidenav);
            }
            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', toggleMobileMenu);
            }
            
            // Check for saved sidenav state
            const savedSidenavState = localStorage.getItem('sidenavCollapsed');
            if (savedSidenavState === 'true' && window.innerWidth >= 768) {
                sidenav.classList.add('collapsed');
                content.classList.add('nav-collapsed');
                const icon = sidenavToggle.querySelector('i');
                if (icon) {
                    icon.classList.remove('fa-chevron-left');
                    icon.classList.add('fa-chevron-right');
                }
            }
            
            // Dark mode toggle
            const darkModeToggle = document.getElementById('darkModeToggle');
            const htmlElement = document.documentElement;
            
            // Check for saved theme preference
            if (localStorage.getItem('darkMode') === 'enabled') {
                htmlElement.classList.add('dark');
                if (darkModeToggle) {
                    darkModeToggle.checked = true;
                }
            }
            
            // Function to toggle dark mode
            function toggleDarkMode() {
                if (htmlElement.classList.contains('dark')) {
                    htmlElement.classList.remove('dark');
                    localStorage.setItem('darkMode', 'disabled');
                    if (darkModeToggle) {
                        darkModeToggle.checked = false;
                    }
                } else {
                    htmlElement.classList.add('dark');
                    localStorage.setItem('darkMode', 'enabled');
                    if (darkModeToggle) {
                        darkModeToggle.checked = true;
                    }
                }
            }
            
            // Event listener for toggle switch
            if (darkModeToggle) {
                darkModeToggle.addEventListener('change', toggleDarkMode);
            }
            
            // New topic modal functions
            const newTopicModal = document.getElementById('new-topic-modal');
            const newTopicBtn = document.getElementById('new-topic-btn');
            const newTopicBtnMobile = document.getElementById('new-topic-btn-mobile');
            const createFirstTopicBtn = document.getElementById('create-first-topic');
            
            // Open modal functions
            function openNewTopicModal() {
                newTopicModal.classList.remove('opacity-0', 'invisible');
                newTopicModal.classList.add('opacity-100', 'visible');
                document.body.style.overflow = 'hidden';
                document.getElementById('modal_title').focus();
            }
            
            // Close modal function
            window.closeNewTopicModal = function() {
                newTopicModal.classList.remove('opacity-100', 'visible');
                newTopicModal.classList.add('opacity-0', 'invisible');
                document.body.style.overflow = '';
                
                // Reset form
                document.getElementById('new-topic-form').reset();
            }
            
            // Event listeners for opening modal
            if (newTopicBtn) {
                newTopicBtn.addEventListener('click', openNewTopicModal);
            }
            if (newTopicBtnMobile) {
                newTopicBtnMobile.addEventListener('click', openNewTopicModal);
            }
            if (createFirstTopicBtn) {
                createFirstTopicBtn.addEventListener('click', openNewTopicModal);
            }
            
            // Close modal on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && newTopicModal.classList.contains('visible')) {
                    closeNewTopicModal();
                }
            });
            
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
        });
        
        // Text formatting functions for the editor
        function insertText(textareaId, beforeText, afterText) {
            const textarea = document.getElementById(textareaId);
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const selectedText = textarea.value.substring(start, end);
            
            const newText = beforeText + selectedText + afterText;
            textarea.value = textarea.value.substring(0, start) + newText + textarea.value.substring(end);
            
            // Set cursor position
            const newCursorPos = start + beforeText.length + selectedText.length;
            textarea.focus();
            textarea.setSelectionRange(newCursorPos, newCursorPos);
        }
        
        // Make toggleMobileMenu function globally accessible
        function toggleMobileMenu() {
            const sidenav = document.getElementById('sidenav');
            const overlay = document.getElementById('overlay');
            
            sidenav.classList.toggle('open');
            overlay.classList.toggle('active');
            document.body.classList.toggle('overflow-hidden');
        }
        
        // Auto-resize textareas
        function autoResizeTextarea(textarea) {
            textarea.style.height = 'auto';
            textarea.style.height = textarea.scrollHeight + 'px';
        }
        
        // Apply auto-resize to all textareas
        document.querySelectorAll('textarea').forEach(textarea => {
            textarea.addEventListener('input', function() {
                autoResizeTextarea(this);
            });
            
            // Initial resize
            autoResizeTextarea(textarea);
        });
        
        // Form validation for new topic
        document.getElementById('new-topic-form').addEventListener('submit', function(e) {
            const title = document.getElementById('modal_title').value.trim();
            const content = document.getElementById('modal_content').value.trim();
            
            if (title.length < 5) {
                e.preventDefault();
                alert('Le titre doit contenir au moins 5 caractères.');
                document.getElementById('modal_title').focus();
                return;
            }
            
            if (content.length < 10) {
                e.preventDefault();
                alert('Le contenu doit contenir au moins 10 caractères.');
                document.getElementById('modal_content').focus();
                return;
            }
        });
        
        // Character counter for textareas
        function addCharacterCounter(textareaId, maxLength = null) {
            const textarea = document.getElementById(textareaId);
            if (!textarea) return;
            
            const counter = document.createElement('div');
            counter.className = 'text-xs text-gray-500 dark:text-gray-400 text-right mt-1';
            
            function updateCounter() {
                const length = textarea.value.length;
                if (maxLength) {
                    counter.textContent = `${length}/${maxLength} caractères`;
                    if (length > maxLength) {
                        counter.classList.add('text-red-500');
                        counter.classList.remove('text-gray-500', 'dark:text-gray-400');
                    } else {
                        counter.classList.remove('text-red-500');
                        counter.classList.add('text-gray-500', 'dark:text-gray-400');
                    }
                } else {
                    counter.textContent = `${length} caractères`;
                }
            }
            
            textarea.addEventListener('input', updateCounter);
            textarea.parentNode.appendChild(counter);
            updateCounter();
        }
        
        // Add character counters to relevant textareas
        addCharacterCounter('modal_title', 255);
        addCharacterCounter('modal_content');
        
        // Search functionality enhancement
        const searchForm = document.querySelector('form[action="forum.php"]');
        if (searchForm) {
            const searchInput = searchForm.querySelector('input[name="search"]');
            if (searchInput) {
                // Add search suggestions (could be enhanced with AJAX)
                searchInput.addEventListener('focus', function() {
                    // You could implement search suggestions here
                });
                
                // Clear search functionality
                const clearSearch = document.createElement('button');
                clearSearch.type = 'button';
                clearSearch.className = 'absolute right-12 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300';
                clearSearch.innerHTML = '<i class="fas fa-times"></i>';
                clearSearch.style.display = searchInput.value ? 'block' : 'none';
                
                clearSearch.addEventListener('click', function() {
                    searchInput.value = '';
                    this.style.display = 'none';
                    searchForm.submit();
                });
                
                searchInput.addEventListener('input', function() {
                    clearSearch.style.display = this.value ? 'block' : 'none';
                });
                
                // Insert clear button
                const inputContainer = searchInput.parentNode;
                if (inputContainer.style.position !== 'relative') {
                    inputContainer.style.position = 'relative';
                }
                inputContainer.appendChild(clearSearch);
            }
        }
        
        // Topic cards hover effects
        document.querySelectorAll('.topic-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
        
        // Reply cards hover effects
        document.querySelectorAll('.reply-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.backgroundColor = 'rgba(59, 130, 246, 0.05)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
            });
        });
        
        // Smooth scroll to anchors
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        
        // Back to top functionality
        const backToTop = document.createElement('button');
        backToTop.id = 'back-to-top';
        backToTop.className = 'fixed bottom-6 right-6 bg-netblue-600 dark:bg-netblue-700 text-white w-12 h-12 rounded-full flex items-center justify-center shadow-lg opacity-0 invisible transition-all hover:bg-netblue-700 dark:hover:bg-netblue-600';
        backToTop.innerHTML = '<i class="fas fa-arrow-up"></i>';
        backToTop.title = 'Retour en haut';
        
        backToTop.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
        
        document.body.appendChild(backToTop);
        
        // Show/hide back to top button
        window.addEventListener('scroll', function() {
            if (window.scrollY > 300) {
                backToTop.classList.remove('opacity-0', 'invisible');
                backToTop.classList.add('opacity-100', 'visible');
            } else {
                backToTop.classList.remove('opacity-100', 'visible');
                backToTop.classList.add('opacity-0', 'invisible');
            }
        });
        
        // Auto-save draft functionality for new topic
        const titleInput = document.getElementById('modal_title');
        const contentTextarea = document.getElementById('modal_content');
        const formationSelect = document.getElementById('modal_formation_id');
        
        function saveDraft() {
            if (titleInput && contentTextarea) {
                const draft = {
                    title: titleInput.value,
                    content: contentTextarea.value,
                    formation_id: formationSelect ? formationSelect.value : ''
                };
                localStorage.setItem('forum_draft', JSON.stringify(draft));
            }
        }
        
        function loadDraft() {
            const draft = localStorage.getItem('forum_draft');
            if (draft) {
                try {
                    const draftData = JSON.parse(draft);
                    if (titleInput) titleInput.value = draftData.title || '';
                    if (contentTextarea) contentTextarea.value = draftData.content || '';
                    if (formationSelect) formationSelect.value = draftData.formation_id || '';
                } catch (e) {
                    console.error('Error loading draft:', e);
                }
            }
        }
        
        function clearDraft() {
            localStorage.removeItem('forum_draft');
        }
        
        // Auto-save every 30 seconds
        if (titleInput && contentTextarea) {
            setInterval(saveDraft, 30000);
            
            // Save on input
            titleInput.addEventListener('input', saveDraft);
            contentTextarea.addEventListener('input', saveDraft);
            if (formationSelect) {
                formationSelect.addEventListener('change', saveDraft);
            }
            
            // Clear draft on successful submission
            document.getElementById('new-topic-form').addEventListener('submit', function() {
                setTimeout(clearDraft, 1000);
            });
        }
        
        // Load draft when modal opens
        if (newTopicBtn) {
            newTopicBtn.addEventListener('click', function() {
                setTimeout(loadDraft, 100);
            });
        }
        if (newTopicBtnMobile) {
            newTopicBtnMobile.addEventListener('click', function() {
                setTimeout(loadDraft, 100);
            });
        }
        
        // Notification system for new replies (could be enhanced with WebSocket)
        function checkForNewReplies() {
            // This would typically make an AJAX request to check for new replies
            // For now, it's just a placeholder
            console.log('Checking for new replies...');
        }
        
        // Check for new replies every 30 seconds (on topic view)
        <?php if ($topic_id > 0): ?>
        setInterval(checkForNewReplies, 30000);
        <?php endif; ?>
        
        // Enhanced form validation with real-time feedback
        function validateForm() {
            const title = document.getElementById('modal_title');
            const content = document.getElementById('modal_content');
            const submitBtn = document.querySelector('button[name="create_topic"]');
            
            if (!title || !content || !submitBtn) return;
            
            function checkValidity() {
                const titleValid = title.value.trim().length >= 5;
                const contentValid = content.value.trim().length >= 10;
                
                // Update title validation visual feedback
                if (title.value.trim().length > 0) {
                    if (titleValid) {
                        title.classList.remove('border-red-500');
                        title.classList.add('border-green-500');
                    } else {
                        title.classList.remove('border-green-500');
                        title.classList.add('border-red-500');
                    }
                } else {
                    title.classList.remove('border-red-500', 'border-green-500');
                }
                
                // Update content validation visual feedback
                if (content.value.trim().length > 0) {
                    if (contentValid) {
                        content.classList.remove('border-red-500');
                        content.classList.add('border-green-500');
                    } else {
                        content.classList.remove('border-green-500');
                        content.classList.add('border-red-500');
                    }
                } else {
                    content.classList.remove('border-red-500', 'border-green-500');
                }
                
                // Enable/disable submit button
                if (titleValid && contentValid) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                } else {
                    submitBtn.disabled = true;
                    submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                }
            }
            
            title.addEventListener('input', checkValidity);
            content.addEventListener('input', checkValidity);
            
            // Initial check
            checkValidity();
        }
        
        // Initialize form validation
        validateForm();
    </script>
</body>
</html>