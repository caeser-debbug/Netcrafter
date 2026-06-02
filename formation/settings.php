<?php
// Initialisation de la session
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    // Sauvegarder l'URL actuelle pour y revenir après la connexion
    $_SESSION['redirect_url'] = "settings.php";
    
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

// Créer la table des paramètres utilisateur si elle n'existe pas
$create_settings_table = "CREATE TABLE IF NOT EXISTS user_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_setting (user_id, setting_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
$conn->query($create_settings_table);

// Paramètres par défaut
$default_settings = [
    'theme' => 'light',
    'language' => 'fr',
    'email_notifications' => '1',
    'forum_notifications' => '1',
    'formation_notifications' => '1',
    'marketing_notifications' => '0',
    'auto_play_videos' => '1',
    'video_quality' => 'auto',
    'playback_speed' => '1',
    'subtitles_enabled' => '0',
    'privacy_profile' => 'public',
    'show_progress' => '1',
    'show_certificates' => '1',
    'timezone' => 'Africa/Niamey'
];

// Récupérer les paramètres utilisateur
function getUserSetting($conn, $user_id, $key, $default = null) {
    $query = "SELECT setting_value FROM user_settings WHERE user_id = ? AND setting_key = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $user_id, $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['setting_value'];
    }
    
    return $default;
}

// Sauvegarder un paramètre utilisateur
function saveUserSetting($conn, $user_id, $key, $value) {
    $query = "INSERT INTO user_settings (user_id, setting_key, setting_value) 
              VALUES (?, ?, ?) 
              ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $user_id, $key, $value);
    return $stmt->execute();
}

// Charger tous les paramètres utilisateur
$user_settings = [];
foreach ($default_settings as $key => $default_value) {
    $user_settings[$key] = getUserSetting($conn, $user_id, $key, $default_value);
}

// Variables pour les messages
$messages = [];
$success_messages = [];

// Traitement des formulaires
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Paramètres généraux
    if (isset($_POST['save_general_settings'])) {
        $theme = $_POST['theme'] ?? 'light';
        $language = $_POST['language'] ?? 'fr';
        $timezone = $_POST['timezone'] ?? 'Africa/Niamey';
        
        if (saveUserSetting($conn, $user_id, 'theme', $theme) &&
            saveUserSetting($conn, $user_id, 'language', $language) &&
            saveUserSetting($conn, $user_id, 'timezone', $timezone)) {
            
            $success_messages[] = 'Paramètres généraux sauvegardés avec succès !';
            $user_settings['theme'] = $theme;
            $user_settings['language'] = $language;
            $user_settings['timezone'] = $timezone;
        } else {
            $messages[] = ['type' => 'error', 'text' => 'Erreur lors de la sauvegarde des paramètres généraux.'];
        }
    }
    
    // Paramètres de notifications
    if (isset($_POST['save_notification_settings'])) {
        $email_notifications = isset($_POST['email_notifications']) ? '1' : '0';
        $forum_notifications = isset($_POST['forum_notifications']) ? '1' : '0';
        $formation_notifications = isset($_POST['formation_notifications']) ? '1' : '0';
        $marketing_notifications = isset($_POST['marketing_notifications']) ? '1' : '0';
        
        if (saveUserSetting($conn, $user_id, 'email_notifications', $email_notifications) &&
            saveUserSetting($conn, $user_id, 'forum_notifications', $forum_notifications) &&
            saveUserSetting($conn, $user_id, 'formation_notifications', $formation_notifications) &&
            saveUserSetting($conn, $user_id, 'marketing_notifications', $marketing_notifications)) {
            
            $success_messages[] = 'Paramètres de notifications sauvegardés avec succès !';
            $user_settings['email_notifications'] = $email_notifications;
            $user_settings['forum_notifications'] = $forum_notifications;
            $user_settings['formation_notifications'] = $formation_notifications;
            $user_settings['marketing_notifications'] = $marketing_notifications;
        } else {
            $messages[] = ['type' => 'error', 'text' => 'Erreur lors de la sauvegarde des paramètres de notifications.'];
        }
    }
    
    // Paramètres vidéo
    if (isset($_POST['save_video_settings'])) {
        $auto_play_videos = isset($_POST['auto_play_videos']) ? '1' : '0';
        $video_quality = $_POST['video_quality'] ?? 'auto';
        $playback_speed = $_POST['playback_speed'] ?? '1';
        $subtitles_enabled = isset($_POST['subtitles_enabled']) ? '1' : '0';
        
        if (saveUserSetting($conn, $user_id, 'auto_play_videos', $auto_play_videos) &&
            saveUserSetting($conn, $user_id, 'video_quality', $video_quality) &&
            saveUserSetting($conn, $user_id, 'playback_speed', $playback_speed) &&
            saveUserSetting($conn, $user_id, 'subtitles_enabled', $subtitles_enabled)) {
            
            $success_messages[] = 'Paramètres vidéo sauvegardés avec succès !';
            $user_settings['auto_play_videos'] = $auto_play_videos;
            $user_settings['video_quality'] = $video_quality;
            $user_settings['playback_speed'] = $playback_speed;
            $user_settings['subtitles_enabled'] = $subtitles_enabled;
        } else {
            $messages[] = ['type' => 'error', 'text' => 'Erreur lors de la sauvegarde des paramètres vidéo.'];
        }
    }
    
    // Paramètres de confidentialité
    if (isset($_POST['save_privacy_settings'])) {
        $privacy_profile = $_POST['privacy_profile'] ?? 'public';
        $show_progress = isset($_POST['show_progress']) ? '1' : '0';
        $show_certificates = isset($_POST['show_certificates']) ? '1' : '0';
        
        if (saveUserSetting($conn, $user_id, 'privacy_profile', $privacy_profile) &&
            saveUserSetting($conn, $user_id, 'show_progress', $show_progress) &&
            saveUserSetting($conn, $user_id, 'show_certificates', $show_certificates)) {
            
            $success_messages[] = 'Paramètres de confidentialité sauvegardés avec succès !';
            $user_settings['privacy_profile'] = $privacy_profile;
            $user_settings['show_progress'] = $show_progress;
            $user_settings['show_certificates'] = $show_certificates;
        } else {
            $messages[] = ['type' => 'error', 'text' => 'Erreur lors de la sauvegarde des paramètres de confidentialité.'];
        }
    }
    
    // Réinitialisation des paramètres
    if (isset($_POST['reset_settings'])) {
        $reset_success = true;
        foreach ($default_settings as $key => $value) {
            if (!saveUserSetting($conn, $user_id, $key, $value)) {
                $reset_success = false;
            }
        }
        
        if ($reset_success) {
            $success_messages[] = 'Tous les paramètres ont été réinitialisés aux valeurs par défaut !';
            $user_settings = $default_settings;
        } else {
            $messages[] = ['type' => 'error', 'text' => 'Erreur lors de la réinitialisation des paramètres.'];
        }
    }
    
    // Exportation des paramètres
    if (isset($_POST['export_settings'])) {
        $export_data = [
            'user_info' => [
                'name' => $user['firstname'] . ' ' . $user['lastname'],
                'phone' => $user['phone'],
                'email' => $user['email'],
                'export_date' => date('Y-m-d H:i:s')
            ],
            'settings' => $user_settings
        ];
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="netcrafter_settings_' . date('Y-m-d') . '.json"');
        echo json_encode($export_data, JSON_PRETTY_PRINT);
        exit;
    }
    
    // Importation des paramètres
    if (isset($_POST['import_settings']) && isset($_FILES['settings_file'])) {
        if ($_FILES['settings_file']['error'] == 0) {
            $file_content = file_get_contents($_FILES['settings_file']['tmp_name']);
            $import_data = json_decode($file_content, true);
            
            if ($import_data && isset($import_data['settings'])) {
                $import_success = true;
                foreach ($import_data['settings'] as $key => $value) {
                    if (array_key_exists($key, $default_settings)) {
                        if (!saveUserSetting($conn, $user_id, $key, $value)) {
                            $import_success = false;
                        }
                    }
                }
                
                if ($import_success) {
                    $success_messages[] = 'Paramètres importés avec succès !';
                    // Recharger les paramètres
                    foreach ($default_settings as $key => $default_value) {
                        $user_settings[$key] = getUserSetting($conn, $user_id, $key, $default_value);
                    }
                } else {
                    $messages[] = ['type' => 'error', 'text' => 'Erreur lors de l\'importation des paramètres.'];
                }
            } else {
                $messages[] = ['type' => 'error', 'text' => 'Fichier de paramètres invalide.'];
            }
        } else {
            $messages[] = ['type' => 'error', 'text' => 'Erreur lors de l\'upload du fichier.'];
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr" class="light scroll">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - Netcrafter</title>
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
        
        /* Settings sections */
        .settings-section {
            transition: all 0.3s ease;
        }
        
        .settings-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        /* Toggle switches */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: #3B82F6;
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        /* Tab navigation */
        .settings-tab {
            transition: all 0.3s ease;
        }
        
        .settings-tab.active {
            background-color: #3B82F6;
            color: white;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Theme preview */
        .theme-preview {
            width: 100px;
            height: 60px;
            border-radius: 8px;
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .theme-preview.selected {
            border-color: #3B82F6;
            transform: scale(1.05);
        }
        
        .theme-light {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        }
        
        .theme-dark {
            background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
        }
        
        .theme-auto {
            background: linear-gradient(90deg, #ffffff 0%, #ffffff 50%, #1f2937 50%, #1f2937 100%);
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
                <div class="w-10 h-10 rounded-full bg-netblue-600 dark:bg-netblue-700 flex items-center justify-center text-white font-bold text-lg flex-shrink-0 overflow-hidden">
                    <?php if (!empty($user['profile_image'])): ?>
                        <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile" class="w-full h-full object-cover">
                    <?php else: ?>
                        <?php echo strtoupper(substr($user['firstname'], 0, 1) . substr($user['lastname'], 0, 1)); ?>
                    <?php endif; ?>
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
                    <a href="forum.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-comments w-6 text-center"></i>
                        <span class="ml-2 nav-text transition-opacity duration-300 truncate">Forum</span>
                    </a>
                </li>
                <li>
                    <a href="quiz.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-comments w-6 text-center"></i>
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
                    <a href="settings.php" class="flex items-center px-3 py-2 text-base rounded-lg bg-netblue-100 dark:bg-netblue-900/30 text-netblue-800 dark:text-netblue-300">
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
                <h1 class="text-xl font-bold text-gray-800 dark:text-white">Paramètres</h1>
                
                <!-- Right Menu -->
                <div class="flex items-center space-x-2">
                    <button id="export-settings" class="bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded-lg transition-colors text-sm hidden md:block">
                        <i class="fas fa-download mr-1"></i>Exporter
                    </button>
                    <button id="import-settings" class="bg-netblue-600 hover:bg-netblue-700 text-white px-3 py-2 rounded-lg transition-colors text-sm hidden md:block">
                        <i class="fas fa-upload mr-1"></i>Importer
                    </button>
                </div>
            </div>
        </header>
        
        <!-- Messages -->
        <?php if (!empty($messages) || !empty($success_messages)): ?>
        <div class="p-4">
            <?php foreach ($success_messages as $message): ?>
            <div class="alert mb-4 p-4 rounded-lg bg-green-100 border-green-500 text-green-700 dark:bg-green-900/30 dark:text-green-300 border-l-4">
                <i class="fas fa-check-circle mr-2"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endforeach; ?>
            
            <?php foreach ($messages as $message): ?>
            <div class="alert mb-4 p-4 rounded-lg <?php echo $message['type'] === 'success' ? 'bg-green-100 border-green-500 text-green-700 dark:bg-green-900/30 dark:text-green-300' : 'bg-red-100 border-red-500 text-red-700 dark:bg-red-900/30 dark:text-red-300'; ?> border-l-4">
                <i class="fas <?php echo $message['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> mr-2"></i>
                <?php echo htmlspecialchars($message['text']); ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Main Content Area -->
        <main class="p-4">
            <!-- Settings Navigation -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg mb-6 overflow-hidden" data-aos="fade-up">
                <div class="border-b border-gray-200 dark:border-gray-700">
                    <nav class="flex space-x-8 px-6 overflow-x-auto" aria-label="Settings">
                        <button class="settings-tab py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 active whitespace-nowrap" data-tab="general">
                            <i class="fas fa-cog mr-2"></i>Général
                        </button>
                        <button class="settings-tab py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 whitespace-nowrap" data-tab="notifications">
                            <i class="fas fa-bell mr-2"></i>Notifications
                        </button>
                        <button class="settings-tab py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 whitespace-nowrap" data-tab="video">
                            <i class="fas fa-video mr-2"></i>Vidéo
                        </button>
                        <button class="settings-tab py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 whitespace-nowrap" data-tab="privacy">
                            <i class="fas fa-shield-alt mr-2"></i>Confidentialité
                        </button>
                        <button class="settings-tab py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 whitespace-nowrap" data-tab="advanced">
                            <i class="fas fa-cogs mr-2"></i>Avancé
                        </button>
                    </nav>
                </div>
                
                <!-- Tab Content -->
                <div class="p-6">
                    <!-- General Settings Tab -->
                    <div id="general" class="tab-content active">
                        <form method="POST" action="settings.php" class="space-y-6">
                            <!-- Theme Settings -->
                            <div class="settings-section bg-gray-50 dark:bg-gray-700 rounded-lg p-6">
                                <h3 class="text-lg font-bold mb-4 dark:text-white">
                                    <i class="fas fa-palette mr-2 text-netblue-600 dark:text-netblue-400"></i>
                                    Apparence
                                </h3>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Thème</label>
                                        <div class="flex space-x-4">
                                            <div class="flex flex-col items-center">
                                                <div class="theme-preview theme-light <?php echo $user_settings['theme'] === 'light' ? 'selected' : ''; ?>" onclick="selectTheme('light')"></div>
                                                <label class="mt-2 text-sm">
                                                    <input type="radio" name="theme" value="light" <?php echo $user_settings['theme'] === 'light' ? 'checked' : ''; ?> class="hidden">
                                                    Clair
                                                </label>
                                            </div>
                                            <div class="flex flex-col items-center">
                                                <div class="theme-preview theme-dark <?php echo $user_settings['theme'] === 'dark' ? 'selected' : ''; ?>" onclick="selectTheme('dark')"></div>
                                                <label class="mt-2 text-sm">
                                                    <input type="radio" name="theme" value="dark" <?php echo $user_settings['theme'] === 'dark' ? 'checked' : ''; ?> class="hidden">
                                                    Sombre
                                                </label>
                                            </div>
                                            <div class="flex flex-col items-center">
                                                <div class="theme-preview theme-auto <?php echo $user_settings['theme'] === 'auto' ? 'selected' : ''; ?>" onclick="selectTheme('auto')"></div>
                                                <label class="mt-2 text-sm">
                                                    <input type="radio" name="theme" value="auto" <?php echo $user_settings['theme'] === 'auto' ? 'checked' : ''; ?> class="hidden">
                                                    Automatique
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Language Settings -->
                            <div class="settings-section bg-gray-50 dark:bg-gray-700 rounded-lg p-6">
                                <h3 class="text-lg font-bold mb-4 dark:text-white">
                                    <i class="fas fa-globe mr-2 text-netblue-600 dark:text-netblue-400"></i>
                                    Langue et région
                                </h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="language" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Langue</label>
                                        <select name="language" id="language" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                                            <option value="fr" <?php echo $user_settings['language'] === 'fr' ? 'selected' : ''; ?>>Français</option>
                                            <option value="en" <?php echo $user_settings['language'] === 'en' ? 'selected' : ''; ?>>English</option>
                                            <option value="ar" <?php echo $user_settings['language'] === 'ar' ? 'selected' : ''; ?>>العربية</option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label for="timezone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Fuseau horaire</label>
                                        <select name="timezone" id="timezone" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                                            <option value="Africa/Niamey" <?php echo $user_settings['timezone'] === 'Africa/Niamey' ? 'selected' : ''; ?>>Niamey (GMT+1)</option>
                                            <option value="Africa/Lagos" <?php echo $user_settings['timezone'] === 'Africa/Lagos' ? 'selected' : ''; ?>>Lagos (GMT+1)</option>
                                            <option value="Africa/Casablanca" <?php echo $user_settings['timezone'] === 'Africa/Casablanca' ? 'selected' : ''; ?>>Casablanca (GMT+1)</option>
                                            <option value="Europe/Paris" <?php echo $user_settings['timezone'] === 'Europe/Paris' ? 'selected' : ''; ?>>Paris (GMT+1)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Submit Button -->
                            <div class="flex justify-end">
                                <button type="submit" name="save_general_settings" class="bg-netblue-600 hover:bg-netblue-700 text-white font-bold py-2 px-6 rounded-lg transition-colors">
                                    <i class="fas fa-save mr-2"></i>Sauvegarder
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Notifications Settings Tab -->
                    <div id="notifications" class="tab-content">
                        <form method="POST" action="settings.php" class="space-y-6">
                            <!-- Email Notifications -->
                            <div class="settings-section bg-gray-50 dark:bg-gray-700 rounded-lg p-6">
                                <h3 class="text-lg font-bold mb-4 dark:text-white">
                                    <i class="fas fa-envelope mr-2 text-netblue-600 dark:text-netblue-400"></i>
                                    Notifications par email
                                </h3>
                                
                                <div class="space-y-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="font-medium dark:text-white">Notifications générales</h4>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Recevoir des emails pour les mises à jour importantes</p>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="email_notifications" <?php echo $user_settings['email_notifications'] === '1' ? 'checked' : ''; ?>>
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                    
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="font-medium dark:text-white">Notifications de formations</h4>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Nouveaux cours, mises à jour et rappels</p>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="formation_notifications" <?php echo $user_settings['formation_notifications'] === '1' ? 'checked' : ''; ?>>
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                    
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="font-medium dark:text-white">Notifications du forum</h4>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Réponses à vos messages et nouvelles discussions</p>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="forum_notifications" <?php echo $user_settings['forum_notifications'] === '1' ? 'checked' : ''; ?>>
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                    
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="font-medium dark:text-white">Notifications marketing</h4>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Promotions, nouvelles fonctionnalités et conseils</p>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="marketing_notifications" <?php echo $user_settings['marketing_notifications'] === '1' ? 'checked' : ''; ?>>
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Notification Frequency -->
                            <div class="settings-section bg-gray-50 dark:bg-gray-700 rounded-lg p-6">
                                <h3 class="text-lg font-bold mb-4 dark:text-white">
                                    <i class="fas fa-clock mr-2 text-netblue-600 dark:text-netblue-400"></i>
                                    Fréquence des notifications
                                </h3>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Résumé des activités</label>
                                        <select name="notification_frequency" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                                            <option value="immediately">Immédiatement</option>
                                            <option value="daily" selected>Quotidien</option>
                                            <option value="weekly">Hebdomadaire</option>
                                            <option value="never">Jamais</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Submit Button -->
                            <div class="flex justify-end">
                                <button type="submit" name="save_notification_settings" class="bg-netblue-600 hover:bg-netblue-700 text-white font-bold py-2 px-6 rounded-lg transition-colors">
                                    <i class="fas fa-save mr-2"></i>Sauvegarder
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Video Settings Tab -->
                    <div id="video" class="tab-content">
                        <form method="POST" action="settings.php" class="space-y-6">
                            <!-- Playback Settings -->
                            <div class="settings-section bg-gray-50 dark:bg-gray-700 rounded-lg p-6">
                                <h3 class="text-lg font-bold mb-4 dark:text-white">
                                    <i class="fas fa-play-circle mr-2 text-netblue-600 dark:text-netblue-400"></i>
                                    Lecture vidéo
                                </h3>
                                
                                <div class="space-y-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="font-medium dark:text-white">Lecture automatique</h4>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Lancer automatiquement la vidéo suivante</p>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="auto_play_videos" <?php echo $user_settings['auto_play_videos'] === '1' ? 'checked' : ''; ?>>
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                    
                                    <div>
                                        <label for="video_quality" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Qualité vidéo par défaut</label>
                                        <select name="video_quality" id="video_quality" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                                            <option value="auto" <?php echo $user_settings['video_quality'] === 'auto' ? 'selected' : ''; ?>>Automatique</option>
                                            <option value="1080p" <?php echo $user_settings['video_quality'] === '1080p' ? 'selected' : ''; ?>>1080p (HD)</option>
                                            <option value="720p" <?php echo $user_settings['video_quality'] === '720p' ? 'selected' : ''; ?>>720p</option>
                                            <option value="480p" <?php echo $user_settings['video_quality'] === '480p' ? 'selected' : ''; ?>>480p</option>
                                            <option value="360p" <?php echo $user_settings['video_quality'] === '360p' ? 'selected' : ''; ?>>360p</option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label for="playback_speed" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Vitesse de lecture par défaut</label>
                                        <select name="playback_speed" id="playback_speed" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                                            <option value="0.5" <?php echo $user_settings['playback_speed'] === '0.5' ? 'selected' : ''; ?>>0.5x</option>
                                            <option value="0.75" <?php echo $user_settings['playback_speed'] === '0.75' ? 'selected' : ''; ?>>0.75x</option>
                                            <option value="1" <?php echo $user_settings['playback_speed'] === '1' ? 'selected' : ''; ?>>1x (Normal)</option>
                                            <option value="1.25" <?php echo $user_settings['playback_speed'] === '1.25' ? 'selected' : ''; ?>>1.25x</option>
                                            <option value="1.5" <?php echo $user_settings['playback_speed'] === '1.5' ? 'selected' : ''; ?>>1.5x</option>
                                            <option value="2" <?php echo $user_settings['playback_speed'] === '2' ? 'selected' : ''; ?>>2x</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Accessibility Settings -->
                            <div class="settings-section bg-gray-50 dark:bg-gray-700 rounded-lg p-6">
                                <h3 class="text-lg font-bold mb-4 dark:text-white">
                                    <i class="fas fa-universal-access mr-2 text-netblue-600 dark:text-netblue-400"></i>
                                    Accessibilité
                                </h3>
                                
                                <div class="space-y-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="font-medium dark:text-white">Sous-titres automatiques</h4>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Activer les sous-titres par défaut (si disponibles)</p>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="subtitles_enabled" <?php echo $user_settings['subtitles_enabled'] === '1' ? 'checked' : ''; ?>>
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Submit Button -->
                            <div class="flex justify-end">
                                <button type="submit" name="save_video_settings" class="bg-netblue-600 hover:bg-netblue-700 text-white font-bold py-2 px-6 rounded-lg transition-colors">
                                    <i class="fas fa-save mr-2"></i>Sauvegarder
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Privacy Settings Tab -->
                    <div id="privacy" class="tab-content">
                        <form method="POST" action="settings.php" class="space-y-6">
                            <!-- Profile Privacy -->
                            <div class="settings-section bg-gray-50 dark:bg-gray-700 rounded-lg p-6">
                                <h3 class="text-lg font-bold mb-4 dark:text-white">
                                    <i class="fas fa-user-secret mr-2 text-netblue-600 dark:text-netblue-400"></i>
                                    Confidentialité du profil
                                </h3>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label for="privacy_profile" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Visibilité du profil</label>
                                        <select name="privacy_profile" id="privacy_profile" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                                            <option value="public" <?php echo $user_settings['privacy_profile'] === 'public' ? 'selected' : ''; ?>>Public - Visible par tous</option>
                                            <option value="students" <?php echo $user_settings['privacy_profile'] === 'students' ? 'selected' : ''; ?>>Étudiants uniquement</option>
                                            <option value="private" <?php echo $user_settings['privacy_profile'] === 'private' ? 'selected' : ''; ?>>Privé - Invisible</option>
                                        </select>
                                    </div>
                                    
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="font-medium dark:text-white">Afficher ma progression</h4>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Permettre aux autres de voir votre progression dans les formations</p>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="show_progress" <?php echo $user_settings['show_progress'] === '1' ? 'checked' : ''; ?>>
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                    
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="font-medium dark:text-white">Afficher mes certificats</h4>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Permettre aux autres de voir vos certificats obtenus</p>
                                        </div>
                                        <label class="toggle-switch">
                                            <input type="checkbox" name="show_certificates" <?php echo $user_settings['show_certificates'] === '1' ? 'checked' : ''; ?>>
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Data Privacy -->
                            <div class="settings-section bg-gray-50 dark:bg-gray-700 rounded-lg p-6">
                                <h3 class="text-lg font-bold mb-4 dark:text-white">
                                    <i class="fas fa-database mr-2 text-netblue-600 dark:text-netblue-400"></i>
                                    Données personnelles
                                </h3>
                                
                                <div class="space-y-4">
                                    <div class="flex items-center justify-between p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                                        <div class="flex items-center">
                                            <i class="fas fa-info-circle text-blue-500 mr-3"></i>
                                            <div>
                                                <h4 class="font-medium text-blue-800 dark:text-blue-300">Contrôle de vos données</h4>
                                                <p class="text-sm text-blue-600 dark:text-blue-400">Gérez l'utilisation de vos données personnelles</p>
                                            </div>
                                        </div>
                                        <a href="profile.php" class="bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-300 px-3 py-1 rounded-full text-sm font-medium hover:bg-blue-200 dark:hover:bg-blue-800 transition-colors">
                                            Gérer
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Submit Button -->
                            <div class="flex justify-end">
                                <button type="submit" name="save_privacy_settings" class="bg-netblue-600 hover:bg-netblue-700 text-white font-bold py-2 px-6 rounded-lg transition-colors">
                                    <i class="fas fa-save mr-2"></i>Sauvegarder
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Advanced Settings Tab -->
                    <div id="advanced" class="tab-content">
                        <div class="space-y-6">
                            <!-- Import/Export Settings -->
                            <div class="settings-section bg-gray-50 dark:bg-gray-700 rounded-lg p-6">
                                <h3 class="text-lg font-bold mb-4 dark:text-white">
                                    <i class="fas fa-exchange-alt mr-2 text-netblue-600 dark:text-netblue-400"></i>
                                    Sauvegarde des paramètres
                                </h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <!-- Export Settings -->
                                    <form method="POST" action="settings.php">
                                        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-600">
                                            <h4 class="font-medium dark:text-white mb-2">Exporter les paramètres</h4>
                                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Téléchargez vos paramètres actuels</p>
                                            <button type="submit" name="export_settings" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg transition-colors">
                                                <i class="fas fa-download mr-2"></i>Exporter
                                            </button>
                                        </div>
                                    </form>
                                    
                                    <!-- Import Settings -->
                                    <form method="POST" action="settings.php" enctype="multipart/form-data">
                                        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg border border-gray-200 dark:border-gray-600">
                                            <h4 class="font-medium dark:text-white mb-2">Importer les paramètres</h4>
                                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Restaurez vos paramètres depuis un fichier</p>
                                            <input type="file" name="settings_file" accept=".json" class="w-full mb-3 text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-netblue-50 file:text-netblue-700 hover:file:bg-netblue-100">
                                            <button type="submit" name="import_settings" class="w-full bg-netblue-600 hover:bg-netblue-700 text-white font-bold py-2 px-4 rounded-lg transition-colors">
                                                <i class="fas fa-upload mr-2"></i>Importer
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            
                            <!-- Reset Settings -->
                            <div class="settings-section bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-6">
                                <h3 class="text-lg font-bold mb-4 text-red-800 dark:text-red-300">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    Zone de danger
                                </h3>
                                
                                <div class="space-y-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="font-medium text-red-800 dark:text-red-300">Réinitialiser tous les paramètres</h4>
                                            <p class="text-sm text-red-600 dark:text-red-400">Restaurer tous les paramètres aux valeurs par défaut</p>
                                        </div>
                                        <button onclick="confirmReset()" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition-colors">
                                            Réinitialiser
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- System Information -->
                            <div class="settings-section bg-gray-50 dark:bg-gray-700 rounded-lg p-6">
                                <h3 class="text-lg font-bold mb-4 dark:text-white">
                                    <i class="fas fa-info-circle mr-2 text-netblue-600 dark:text-netblue-400"></i>
                                    Informations système
                                </h3>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                    <div class="space-y-2">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600 dark:text-gray-400">Version de la plateforme:</span>
                                            <span class="font-medium dark:text-white">v2.1.0</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600 dark:text-gray-400">Navigateur:</span>
                                            <span class="font-medium dark:text-white" id="browser-info">Chrome 120</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600 dark:text-gray-400">Système d'exploitation:</span>
                                            <span class="font-medium dark:text-white" id="os-info">Windows 11</span>
                                        </div>
                                    </div>
                                    <div class="space-y-2">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600 dark:text-gray-400">Dernière sauvegarde:</span>
                                            <span class="font-medium dark:text-white">Jamais</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600 dark:text-gray-400">Espace utilisé:</span>
                                            <span class="font-medium dark:text-white">2.3 MB</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600 dark:text-gray-400">Performances:</span>
                                            <span class="font-medium text-green-600 dark:text-green-400">Optimales</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Cache and Storage -->
                            <div class="settings-section bg-gray-50 dark:bg-gray-700 rounded-lg p-6">
                                <h3 class="text-lg font-bold mb-4 dark:text-white">
                                    <i class="fas fa-broom mr-2 text-netblue-600 dark:text-netblue-400"></i>
                                    Cache et stockage
                                </h3>
                                
                                <div class="space-y-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="font-medium dark:text-white">Vider le cache local</h4>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Supprime les données temporaires pour améliorer les performances</p>
                                        </div>
                                        <button onclick="clearCache()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition-colors">
                                            Vider
                                        </button>
                                    </div>
                                    
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="font-medium dark:text-white">Réinitialiser les données locales</h4>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Supprime toutes les données stockées localement</p>
                                        </div>
                                        <button onclick="resetLocalData()" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded-lg transition-colors">
                                            Réinitialiser
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
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
    
    <!-- Reset Confirmation Modal -->
    <div id="reset-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 opacity-0 invisible transition-all duration-300">
        <div class="absolute inset-0 bg-black bg-opacity-50" onclick="closeResetModal()"></div>
        <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full">
            <div class="p-6">
                <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 dark:bg-red-900/30 rounded-full mb-4">
                    <i class="fas fa-exclamation-triangle text-red-600 dark:text-red-400 text-xl"></i>
                </div>
                <h3 class="text-lg font-bold text-center mb-2 dark:text-white">Réinitialiser les paramètres</h3>
                <p class="text-gray-600 dark:text-gray-400 text-center mb-6">
                    Êtes-vous sûr de vouloir réinitialiser tous vos paramètres aux valeurs par défaut ? Cette action est irréversible.
                </p>
                
                <form method="POST" action="settings.php" class="flex space-x-3">
                    <button type="button" onclick="closeResetModal()" class="flex-1 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-white font-bold py-2 px-4 rounded-lg transition-colors">
                        Annuler
                    </button>
                    <button type="submit" name="reset_settings" class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg transition-colors">
                        Réinitialiser
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Hidden file input for import -->
    <input type="file" id="hidden-file-input" accept=".json" style="display: none;">

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
            
            // Check for saved theme preference or user setting
            const savedTheme = '<?php echo $user_settings['theme']; ?>';
            if (savedTheme === 'dark' || (savedTheme === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                htmlElement.classList.add('dark');
                if (darkModeToggle) {
                    darkModeToggle.checked = true;
                }
            }
            
            // Function to toggle dark mode
            function toggleDarkMode() {
                if (htmlElement.classList.contains('dark')) {
                    htmlElement.classList.remove('dark');
                    if (darkModeToggle) {
                        darkModeToggle.checked = false;
                    }
                } else {
                    htmlElement.classList.add('dark');
                    if (darkModeToggle) {
                        darkModeToggle.checked = true;
                    }
                }
            }
            
            // Event listener for toggle switch
            if (darkModeToggle) {
                darkModeToggle.addEventListener('change', toggleDarkMode);
            }
            
            // Settings tab functionality
            const settingsTabs = document.querySelectorAll('.settings-tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            settingsTabs.forEach(button => {
                button.addEventListener('click', function() {
                    const targetTab = this.getAttribute('data-tab');
                    
                    // Remove active class from all buttons and contents
                    settingsTabs.forEach(btn => {
                        btn.classList.remove('active', 'border-netblue-500', 'text-netblue-600', 'dark:text-netblue-400');
                        btn.classList.add('border-transparent', 'text-gray-500', 'dark:text-gray-400');
                    });
                    tabContents.forEach(content => {
                        content.classList.remove('active');
                    });
                    
                    // Add active class to clicked button and corresponding content
                    this.classList.add('active', 'border-netblue-500', 'text-netblue-600', 'dark:text-netblue-400');
                    this.classList.remove('border-transparent', 'text-gray-500', 'dark:text-gray-400');
                    
                    document.getElementById(targetTab).classList.add('active');
                });
            });
            
            // Theme selection
            const themeOptions = document.querySelectorAll('input[name="theme"]');
            themeOptions.forEach(option => {
                option.addEventListener('change', function() {
                    applyTheme(this.value);
                });
            });
            
            // System information detection
            detectSystemInfo();
            
            // Export/Import buttons in header
            const exportBtn = document.getElementById('export-settings');
            const importBtn = document.getElementById('import-settings');
            const hiddenFileInput = document.getElementById('hidden-file-input');
            
            if (exportBtn) {
                exportBtn.addEventListener('click', function() {
                    // Trigger the export form
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'settings.php';
                    
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'export_settings';
                    input.value = '1';
                    
                    form.appendChild(input);
                    document.body.appendChild(form);
                    form.submit();
                    document.body.removeChild(form);
                });
            }
            
            if (importBtn) {
                importBtn.addEventListener('click', function() {
                    hiddenFileInput.click();
                });
            }
            
            if (hiddenFileInput) {
                hiddenFileInput.addEventListener('change', function() {
                    if (this.files.length > 0) {
                        // Create and submit form
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = 'settings.php';
                        form.enctype = 'multipart/form-data';
                        
                        const fileInput = document.createElement('input');
                        fileInput.type = 'file';
                        fileInput.name = 'settings_file';
                        fileInput.files = this.files;
                        
                        const submitInput = document.createElement('input');
                        submitInput.type = 'hidden';
                        submitInput.name = 'import_settings';
                        submitInput.value = '1';
                        
                        form.appendChild(fileInput);
                        form.appendChild(submitInput);
                        document.body.appendChild(form);
                        form.submit();
                        document.body.removeChild(form);
                    }
                });
            }
            
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
        
        // Theme selection function
        function selectTheme(theme) {
            // Update radio button
            document.querySelector(`input[name="theme"][value="${theme}"]`).checked = true;
            
            // Remove selected class from all previews
            document.querySelectorAll('.theme-preview').forEach(preview => {
                preview.classList.remove('selected');
            });
            
            // Add selected class to clicked preview
            document.querySelector(`.theme-${theme}`).classList.add('selected');
            
            // Apply theme
            applyTheme(theme);
        }
        
        // Apply theme function
        function applyTheme(theme) {
            const htmlElement = document.documentElement;
            const darkModeToggle = document.getElementById('darkModeToggle');
            
            if (theme === 'dark') {
                htmlElement.classList.add('dark');
                if (darkModeToggle) darkModeToggle.checked = true;
            } else if (theme === 'light') {
                htmlElement.classList.remove('dark');
                if (darkModeToggle) darkModeToggle.checked = false;
            } else if (theme === 'auto') {
                // Auto theme based on system preference
                if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    htmlElement.classList.add('dark');
                    if (darkModeToggle) darkModeToggle.checked = true;
                } else {
                    htmlElement.classList.remove('dark');
                    if (darkModeToggle) darkModeToggle.checked = false;
                }
            }
        }
        
        // System information detection
        function detectSystemInfo() {
            const browserInfo = document.getElementById('browser-info');
            const osInfo = document.getElementById('os-info');
            
            if (browserInfo) {
                const userAgent = navigator.userAgent;
                let browserName = 'Unknown';
                let browserVersion = 'Unknown';
                
                if (userAgent.includes('Chrome')) {
                    browserName = 'Chrome';
                    browserVersion = userAgent.match(/Chrome\/(\d+)/)[1];
                } else if (userAgent.includes('Firefox')) {
                    browserName = 'Firefox';
                    browserVersion = userAgent.match(/Firefox\/(\d+)/)[1];
                } else if (userAgent.includes('Safari')) {
                    browserName = 'Safari';
                    browserVersion = userAgent.match(/Version\/(\d+)/)?.[1] || 'Unknown';
                } else if (userAgent.includes('Edge')) {
                    browserName = 'Edge';
                    browserVersion = userAgent.match(/Edge\/(\d+)/)[1];
                }
                
                browserInfo.textContent = `${browserName} ${browserVersion}`;
            }
            
            if (osInfo) {
                const platform = navigator.platform;
                let osName = 'Unknown';
                
                if (platform.includes('Win')) {
                    osName = 'Windows';
                } else if (platform.includes('Mac')) {
                    osName = 'macOS';
                } else if (platform.includes('Linux')) {
                    osName = 'Linux';
                } else if (platform.includes('iPhone') || platform.includes('iPad')) {
                    osName = 'iOS';
                } else if (platform.includes('Android')) {
                    osName = 'Android';
                }
                
                osInfo.textContent = osName;
            }
        }
        
        // Reset confirmation modal
        function confirmReset() {
            const modal = document.getElementById('reset-modal');
            modal.classList.remove('opacity-0', 'invisible');
            modal.classList.add('opacity-100', 'visible');
            document.body.style.overflow = 'hidden';
        }
        
        function closeResetModal() {
            const modal = document.getElementById('reset-modal');
            modal.classList.remove('opacity-100', 'visible');
            modal.classList.add('opacity-0', 'invisible');
            document.body.style.overflow = '';
        }
        
        // Cache management functions
        function clearCache() {
            if ('caches' in window) {
                caches.keys().then(names => {
                    names.forEach(name => {
                        caches.delete(name);
                    });
                });
            }
            
            // Clear localStorage items related to cache
            const keysToRemove = [];
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && (key.includes('cache') || key.includes('temp'))) {
                    keysToRemove.push(key);
                }
            }
            keysToRemove.forEach(key => localStorage.removeItem(key));
            
            showNotification('Cache vidé avec succès !', 'success');
        }
        
        function resetLocalData() {
            if (confirm('Êtes-vous sûr de vouloir supprimer toutes les données locales ? Cela inclut vos préférences et données temporaires.')) {
                // Clear all localStorage except essential items
                const essentialKeys = ['darkMode', 'sidenavCollapsed'];
                const allKeys = Object.keys(localStorage);
                
                allKeys.forEach(key => {
                    if (!essentialKeys.includes(key)) {
                        localStorage.removeItem(key);
                    }
                });
                
                // Clear sessionStorage
                sessionStorage.clear();
                
                // Clear IndexedDB if available
                if ('indexedDB' in window) {
                    indexedDB.databases().then(databases => {
                        databases.forEach(db => {
                            indexedDB.deleteDatabase(db.name);
                        });
                    });
                }
                
                showNotification('Données locales réinitialisées avec succès !', 'success');
            }
        }
        
        // Make toggleMobileMenu function globally accessible
        function toggleMobileMenu() {
            const sidenav = document.getElementById('sidenav');
            const overlay = document.getElementById('overlay');
            
            sidenav.classList.toggle('open');
            overlay.classList.toggle('active');
            document.body.classList.toggle('overflow-hidden');
        }
        
        // Notification system
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg text-white z-50 transform translate-x-full transition-transform duration-300 ${type === 'success' ? 'bg-green-500' : 'bg-red-500'}`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'} mr-2"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Show notification
            setTimeout(() => {
                notification.classList.remove('translate-x-full');
                notification.classList.add('translate-x-0');
            }, 100);
            
            // Hide notification after 3 seconds
            setTimeout(() => {
                notification.classList.remove('translate-x-0');
                notification.classList.add('translate-x-full');
                setTimeout(() => {
                    if (document.body.contains(notification)) {
                        document.body.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }
        
        // Auto-save settings on change
        function autoSaveSettings() {
            const forms = document.querySelectorAll('form');
            
            forms.forEach(form => {
                const inputs = form.querySelectorAll('input[type="checkbox"], select');
                
                inputs.forEach(input => {
                    input.addEventListener('change', function() {
                        // Auto-save after a short delay
                        clearTimeout(this.saveTimeout);
                        this.saveTimeout = setTimeout(() => {
                            const formData = new FormData(form);
                            
                            // Only auto-save if this form has a save button
                            const saveButton = form.querySelector('button[type="submit"]');
                            if (saveButton) {
                                // Show saving indicator
                                const originalText = saveButton.innerHTML;
                                saveButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Sauvegarde...';
                                saveButton.disabled = true;
                                
                                // Simulate auto-save (in a real app, you'd make an AJAX request)
                                setTimeout(() => {
                                    saveButton.innerHTML = '<i class="fas fa-check mr-2"></i>Sauvegardé';
                                    setTimeout(() => {
                                        saveButton.innerHTML = originalText;
                                        saveButton.disabled = false;
                                    }, 1000);
                                }, 1000);
                            }
                        }, 2000); // Auto-save after 2 seconds of inactivity
                    });
                });
            });
        }
        
        // Initialize auto-save
        document.addEventListener('DOMContentLoaded', function() {
            // Uncomment to enable auto-save
            // autoSaveSettings();
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + S to save current tab
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                const activeTab = document.querySelector('.tab-content.active');
                if (activeTab) {
                    const form = activeTab.querySelector('form');
                    if (form) {
                        const saveButton = form.querySelector('button[type="submit"]');
                        if (saveButton) {
                            saveButton.click();
                        }
                    }
                }
            }
            
            // Escape to close modals
            if (e.key === 'Escape') {
                closeResetModal();
            }
        });
        
        // Example usage for successful form submissions
        <?php if (!empty($success_messages)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            <?php foreach ($success_messages as $message): ?>
            showNotification('<?php echo addslashes($message); ?>', 'success');
            <?php endforeach; ?>
        });
        <?php endif; ?>
        
        // Smooth scroll for internal links
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
        
        // Performance monitoring
        function monitorPerformance() {
            if ('performance' in window) {
                const navigation = performance.getEntriesByType('navigation')[0];
                const loadTime = navigation.loadEventEnd - navigation.loadEventStart;
                
                if (loadTime > 3000) {
                    console.warn('Page load time is high:', loadTime + 'ms');
                }
            }
        }
        
        // Initialize performance monitoring
        window.addEventListener('load', monitorPerformance);
        
        // Initialize theme based on user settings
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = '<?php echo $user_settings['theme']; ?>';
            applyTheme(savedTheme);
            
            // Update theme preview selection
            document.querySelector(`.theme-${savedTheme}`).classList.add('selected');
        });
    </script>
</body>
</html>