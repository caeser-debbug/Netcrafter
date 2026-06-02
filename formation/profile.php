<?php
// Initialisation de la session
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    // Sauvegarder l'URL actuelle pour y revenir après la connexion
    $_SESSION['redirect_url'] = "profile.php";
    
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

// Variables pour les messages
$messages = [];
$success_messages = [];

// Traitement du formulaire de mise à jour du profil
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_profile'])) {
        $firstname = trim($_POST['firstname']);
        $lastname = trim($_POST['lastname']);
        $email = trim($_POST['email']);
        $date_of_birth = $_POST['date_of_birth'];
        $place_of_birth = trim($_POST['place_of_birth']);
        $address = trim($_POST['address']);
        
        // Validation des données
        if (empty($firstname)) {
            $messages[] = ['type' => 'error', 'text' => 'Le prénom est requis.'];
        }
        
        if (empty($lastname)) {
            $messages[] = ['type' => 'error', 'text' => 'Le nom est requis.'];
        }
        
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $messages[] = ['type' => 'error', 'text' => 'L\'adresse email n\'est pas valide.'];
        }
        
        if (!empty($date_of_birth)) {
            $birth_date = new DateTime($date_of_birth);
            $today = new DateTime();
            $age = $today->diff($birth_date)->y;
            
            if ($age < 13 || $age > 120) {
                $messages[] = ['type' => 'error', 'text' => 'Veuillez entrer une date de naissance valide.'];
            }
        }
        
        // Gestion de l'upload d'image de profil
        $profile_image = $user['profile_image']; // Conserver l'image actuelle par défaut
        
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            $file_info = pathinfo($_FILES['profile_image']['name']);
            $file_extension = strtolower($file_info['extension']);
            
            if (!in_array($file_extension, $allowed_extensions)) {
                $messages[] = ['type' => 'error', 'text' => 'Format d\'image non supporté. Utilisez JPG, JPEG, PNG ou GIF.'];
            } elseif ($_FILES['profile_image']['size'] > 2000000) { // 2MB
                $messages[] = ['type' => 'error', 'text' => 'L\'image est trop volumineuse. Taille maximale : 2MB.'];
            } else {
                // Créer le dossier profiles s'il n'existe pas
                $profiles_dir = 'uploads/profiles';
                if (!file_exists($profiles_dir)) {
                    mkdir($profiles_dir, 0777, true);
                }
                
                // Générer un nom de fichier unique
                $file_name = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
                $upload_path = $profiles_dir . '/' . $file_name;
                
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                    // Supprimer l'ancienne image si elle existe
                    if (!empty($user['profile_image']) && file_exists($user['profile_image'])) {
                        unlink($user['profile_image']);
                    }
                    $profile_image = $upload_path;
                } else {
                    $messages[] = ['type' => 'error', 'text' => 'Erreur lors de l\'upload de l\'image.'];
                }
            }
        }
        
        // Si aucune erreur, mettre à jour le profil
        if (empty(array_filter($messages, function($msg) { return $msg['type'] === 'error'; }))) {
            $update_query = "UPDATE users SET 
                           firstname = ?, 
                           lastname = ?, 
                           email = ?, 
                           date_of_birth = ?, 
                           place_of_birth = ?, 
                           address = ?, 
                           profile_image = ?,
                           updated_at = NOW()
                           WHERE id = ?";
            
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("sssssssi", 
                $firstname, 
                $lastname, 
                $email, 
                $date_of_birth, 
                $place_of_birth, 
                $address, 
                $profile_image,
                $user_id
            );
            
            if ($stmt->execute()) {
                $success_messages[] = 'Profil mis à jour avec succès !';
                
                // Mettre à jour les données utilisateur
                $user['firstname'] = $firstname;
                $user['lastname'] = $lastname;
                $user['email'] = $email;
                $user['date_of_birth'] = $date_of_birth;
                $user['place_of_birth'] = $place_of_birth;
                $user['address'] = $address;
                $user['profile_image'] = $profile_image;
                
                // Mettre à jour la session
                $_SESSION['user_name'] = $firstname . ' ' . $lastname;
            } else {
                $messages[] = ['type' => 'error', 'text' => 'Erreur lors de la mise à jour du profil.'];
            }
        }
    }
    
    // Traitement du changement de mot de passe
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validation des mots de passe
        if (empty($current_password)) {
            $messages[] = ['type' => 'error', 'text' => 'Veuillez entrer votre mot de passe actuel.'];
        } elseif (!password_verify($current_password, $user['password'])) {
            $messages[] = ['type' => 'error', 'text' => 'Le mot de passe actuel est incorrect.'];
        }
        
        if (empty($new_password)) {
            $messages[] = ['type' => 'error', 'text' => 'Veuillez entrer un nouveau mot de passe.'];
        } elseif (strlen($new_password) < 6) {
            $messages[] = ['type' => 'error', 'text' => 'Le nouveau mot de passe doit contenir au moins 6 caractères.'];
        }
        
        if ($new_password !== $confirm_password) {
            $messages[] = ['type' => 'error', 'text' => 'Les nouveaux mots de passe ne correspondent pas.'];
        }
        
        // Si aucune erreur, mettre à jour le mot de passe
        if (empty(array_filter($messages, function($msg) { return $msg['type'] === 'error'; }))) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $update_password_query = "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($update_password_query);
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                $success_messages[] = 'Mot de passe changé avec succès !';
                
                // Supprimer tous les tokens d'authentification pour forcer une nouvelle connexion sur les autres appareils
                $delete_tokens_query = "DELETE FROM auth_tokens WHERE user_id = ?";
                $stmt = $conn->prepare($delete_tokens_query);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                
            } else {
                $messages[] = ['type' => 'error', 'text' => 'Erreur lors du changement de mot de passe.'];
            }
        }
    }
}

// Récupérer les statistiques utilisateur
$stats_query = "SELECT 
                (SELECT COUNT(*) FROM formation_subscriptions WHERE user_id = ? AND status = 'active' AND end_date >= CURDATE()) as active_formations,
                (SELECT COUNT(*) FROM formation_subscriptions WHERE user_id = ?) as total_formations,
                (SELECT COUNT(*) FROM formation_favorites WHERE user_id = ?) as favorite_formations,
                (SELECT COUNT(*) FROM video_progress WHERE user_id = ? AND is_completed = 1) as completed_videos,
                (SELECT COUNT(*) FROM certificates WHERE user_id = ?) as certificates_earned";

$stmt = $conn->prepare($stats_query);
$stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$stats_result = $stmt->get_result();
$stats = $stats_result->fetch_assoc();

// Récupérer les formations récentes
$recent_formations_query = "SELECT f.title, f.cover_image, fs.start_date, fs.end_date, fs.status,
                          c.name as category_name, c.icon as category_icon
                          FROM formation_subscriptions fs
                          JOIN formations f ON fs.formation_id = f.id
                          JOIN formation_categories c ON f.category_id = c.id
                          WHERE fs.user_id = ?
                          ORDER BY fs.created_at DESC
                          LIMIT 5";

$stmt = $conn->prepare($recent_formations_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_formations_result = $stmt->get_result();
$recent_formations = [];

while ($row = $recent_formations_result->fetch_assoc()) {
    if (empty($row['cover_image'])) {
        $row['cover_image'] = "image/formations/default-" . rand(1, 3) . ".jpg";
    }
    $recent_formations[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr" class="light scroll">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - Netcrafter</title>
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
        
        /* Profile image preview */
        .profile-image-preview {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #e5e7eb;
            transition: all 0.3s ease;
        }
        
        .profile-image-preview:hover {
            border-color: #3B82F6;
            transform: scale(1.05);
        }
        
        /* Form animations */
        .form-section {
            transition: all 0.3s ease;
        }
        
        .form-section:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        /* Tab navigation */
        .tab-button {
            transition: all 0.3s ease;
        }
        
        .tab-button.active {
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
        
        /* Stats cards */
        .stat-card {
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        /* Password strength indicator */
        .password-strength {
            height: 4px;
            border-radius: 2px;
            transition: all 0.3s ease;
        }
        
        .strength-weak { background-color: #ef4444; width: 25%; }
        .strength-fair { background-color: #f59e0b; width: 50%; }
        .strength-good { background-color: #10b981; width: 75%; }
        .strength-strong { background-color: #059669; width: 100%; }
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
                        <i class="fas fa-question-circle w-6 text-center"></i>
                        <span class="ml-2 nav-text transition-opacity duration-300 truncate">Quiz</span>
                    </a>
                </li>
                <li class="pt-2 mt-2 border-t border-gray-200 dark:border-gray-700">
                    <a href="profile.php" class="flex items-center px-3 py-2 text-base rounded-lg bg-netblue-100 dark:bg-netblue-900/30 text-netblue-800 dark:text-netblue-300">
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
                <h1 class="text-xl font-bold text-gray-800 dark:text-white">Mon Profil</h1>
                
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
            <!-- Profile Header -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden mb-6" data-aos="fade-up">
                <div class="bg-gradient-to-r from-netblue-600 to-netblue-800 h-32 relative">
                    <div class="absolute -bottom-12 left-6">
                        <div class="profile-image-preview bg-white dark:bg-gray-800 p-1">
                            <?php if (!empty($user['profile_image'])): ?>
                                <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile" class="w-full h-full object-cover rounded-full">
                            <?php else: ?>
                                <div class="w-full h-full bg-netblue-600 rounded-full flex items-center justify-center text-white text-3xl font-bold">
                                    <?php echo strtoupper(substr($user['firstname'], 0, 1) . substr($user['lastname'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="pt-16 p-6">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center">
                        <div>
                            <h2 class="text-2xl font-bold dark:text-white"><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></h2>
                            <p class="text-gray-600 dark:text-gray-400 mt-1">
                                <i class="fas fa-phone mr-2"></i><?php echo htmlspecialchars($user['phone']); ?>
                            </p>
                            <?php if (!empty($user['email'])): ?>
                            <p class="text-gray-600 dark:text-gray-400">
                                <i class="fas fa-envelope mr-2"></i><?php echo htmlspecialchars($user['email']); ?>
                            </p>
                            <?php endif; ?>
                            <?php if (!empty($user['date_of_birth'])): ?>
                            <p class="text-gray-600 dark:text-gray-400">
                                <i class="fas fa-birthday-cake mr-2"></i><?php echo date('d/m/Y', strtotime($user['date_of_birth'])); ?>
                            </p>
                            <?php endif; ?>
                        </div>
                        <div class="mt-4 sm:mt-0 text-sm text-gray-500 dark:text-gray-400">
                            Membre depuis <?php echo date('M Y', strtotime($user['created_at'])); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <!-- Active Formations -->
                <div class="stat-card bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 text-center" data-aos="fade-up" data-aos-delay="100">
                    <div class="text-3xl font-bold text-netblue-600 dark:text-netblue-400 mb-2"><?php echo $stats['active_formations']; ?></div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Formations actives</div>
                </div>
                
                <!-- Total Formations -->
                <div class="stat-card bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 text-center" data-aos="fade-up" data-aos-delay="200">
                    <div class="text-3xl font-bold text-green-600 dark:text-green-400 mb-2"><?php echo $stats['total_formations']; ?></div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Total formations</div>
                </div>
                
                <!-- Completed Videos -->
                <div class="stat-card bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 text-center" data-aos="fade-up" data-aos-delay="300">
                    <div class="text-3xl font-bold text-purple-600 dark:text-purple-400 mb-2"><?php echo $stats['completed_videos']; ?></div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Vidéos terminées</div>
                </div>
                
                <!-- Certificates -->
                <div class="stat-card bg-white dark:bg-gray-800 rounded-lg shadow-lg p-4 text-center" data-aos="fade-up" data-aos-delay="400">
                    <div class="text-3xl font-bold text-yellow-600 dark:text-yellow-400 mb-2"><?php echo $stats['certificates_earned']; ?></div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">Certificats</div>
                </div>
            </div>
            
            <!-- Tab Navigation -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg mb-6" data-aos="fade-up">
                <div class="border-b border-gray-200 dark:border-gray-700">
                    <nav class="flex space-x-8 px-6" aria-label="Tabs">
                        <button class="tab-button py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 active" data-tab="profile-info">
                            <i class="fas fa-user mr-2"></i>Informations personnelles
                        </button>
                        <button class="tab-button py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200" data-tab="security">
                            <i class="fas fa-shield-alt mr-2"></i>Sécurité
                        </button>
                        <button class="tab-button py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200" data-tab="activity">
                            <i class="fas fa-chart-line mr-2"></i>Activité
                        </button>
                    </nav>
                </div>
                
                <!-- Tab Content -->
                <div class="p-6">
                    <!-- Profile Information Tab -->
                    <div id="profile-info" class="tab-content active">
                        <form method="POST" action="profile.php" enctype="multipart/form-data" class="space-y-6">
                            <!-- Profile Image Upload -->
                            <div class="form-section bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Photo de profil</label>
                                <div class="flex items-center space-x-4">
                                    <div class="w-20 h-20 rounded-full overflow-hidden bg-gray-200 dark:bg-gray-600 flex items-center justify-center">
                                        <?php if (!empty($user['profile_image'])): ?>
                                            <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile" class="w-full h-full object-cover" id="current-avatar">
                                        <?php else: ?>
                                            <div class="text-gray-500 dark:text-gray-400 text-2xl font-bold" id="current-avatar">
                                                <?php echo strtoupper(substr($user['firstname'], 0, 1) . substr($user['lastname'], 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-1">
                                        <input type="file" name="profile_image" id="profile_image" accept="image/*" class="hidden">
                                        <label for="profile_image" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded-lg cursor-pointer transition-colors inline-block">
                                            <i class="fas fa-camera mr-2"></i>Changer la photo
                                        </label>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">JPG, JPEG, PNG ou GIF. Maximum 2MB.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Personal Information -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- First Name -->
                                <div>
                                    <label for="firstname" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Prénom *</label>
                                    <input type="text" name="firstname" id="firstname" value="<?php echo htmlspecialchars($user['firstname']); ?>" required class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                                </div>
                                
                                <!-- Last Name -->
                                <div>
                                    <label for="lastname" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Nom *</label>
                                    <input type="text" name="lastname" id="lastname" value="<?php echo htmlspecialchars($user['lastname']); ?>" required class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                                </div>
                                
                                <!-- Email -->
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email</label>
                                    <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                                </div>
                                
                                <!-- Phone (readonly) -->
                                <div>
                                    <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Téléphone</label>
                                    <input type="tel" name="phone" id="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" readonly class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-gray-100 dark:bg-gray-600 text-gray-800 dark:text-white cursor-not-allowed">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Le numéro de téléphone ne peut pas être modifié</p>
                                </div>
                                
                                <!-- Date of Birth -->
                                <div>
                                    <label for="date_of_birth" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Date de naissance</label>
                                    <input type="date" name="date_of_birth" id="date_of_birth" value="<?php echo htmlspecialchars($user['date_of_birth'] ?? ''); ?>" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                                </div>
                                
                                <!-- Place of Birth -->
                                <div>
                                    <label for="place_of_birth" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Lieu de naissance</label>
                                    <input type="text" name="place_of_birth" id="place_of_birth" value="<?php echo htmlspecialchars($user['place_of_birth'] ?? ''); ?>" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                                </div>
                            </div>
                            
                            <!-- Address -->
                            <div>
                                <label for="address" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Adresse</label>
                                <textarea name="address" id="address" rows="3" class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                            </div>
                            
                            <!-- Submit Button -->
                            <div class="flex justify-end">
                                <button type="submit" name="update_profile" class="bg-netblue-600 hover:bg-netblue-700 text-white font-bold py-2 px-6 rounded-lg transition-colors">
                                    <i class="fas fa-save mr-2"></i>Sauvegarder les modifications
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Security Tab -->
                    <div id="security" class="tab-content">
                        <div class="space-y-6">
                            <!-- Change Password -->
                            <div class="form-section bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                                <h3 class="text-lg font-bold mb-4 dark:text-white">
                                    <i class="fas fa-key mr-2 text-netblue-600 dark:text-netblue-400"></i>
                                    Changer le mot de passe
                                </h3>
                                
                                <form method="POST" action="profile.php" class="space-y-4">
                                    <!-- Current Password -->
                                    <div>
                                        <label for="current_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Mot de passe actuel *</label>
                                        <div class="relative">
                                            <input type="password" name="current_password" id="current_password" required class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 pr-10 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                                            <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center" onclick="togglePassword('current_password')">
                                                <i class="fas fa-eye text-gray-400" id="current_password_icon"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- New Password -->
                                    <div>
                                        <label for="new_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Nouveau mot de passe *</label>
                                        <div class="relative">
                                            <input type="password" name="new_password" id="new_password" required class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 pr-10 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                                            <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center" onclick="togglePassword('new_password')">
                                                <i class="fas fa-eye text-gray-400" id="new_password_icon"></i>
                                            </button>
                                        </div>
                                        <!-- Password Strength Indicator -->
                                        <div class="mt-2">
                                            <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-1">
                                                <span>Force du mot de passe</span>
                                                <span id="password-strength-text">Faible</span>
                                            </div>
                                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                                <div id="password-strength-bar" class="password-strength bg-red-500 rounded-full transition-all duration-300"></div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Confirm New Password -->
                                    <div>
                                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Confirmer le nouveau mot de passe *</label>
                                        <div class="relative">
                                            <input type="password" name="confirm_password" id="confirm_password" required class="w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 pr-10 bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500">
                                            <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center" onclick="togglePassword('confirm_password')">
                                                <i class="fas fa-eye text-gray-400" id="confirm_password_icon"></i>
                                            </button>
                                        </div>
                                        <div id="password-match" class="text-sm mt-1 hidden">
                                            <span class="text-red-600 dark:text-red-400">
                                                <i class="fas fa-times mr-1"></i>Les mots de passe ne correspondent pas
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <!-- Password Requirements -->
                                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                                        <h4 class="font-medium text-blue-800 dark:text-blue-300 mb-2">Exigences du mot de passe :</h4>
                                        <ul class="text-sm text-blue-700 dark:text-blue-400 space-y-1">
                                            <li id="req-length" class="flex items-center">
                                                <i class="fas fa-times text-red-500 mr-2"></i>Au moins 6 caractères
                                            </li>
                                            <li id="req-uppercase" class="flex items-center">
                                                <i class="fas fa-times text-red-500 mr-2"></i>Au moins une majuscule
                                            </li>
                                            <li id="req-lowercase" class="flex items-center">
                                                <i class="fas fa-times text-red-500 mr-2"></i>Au moins une minuscule
                                            </li>
                                            <li id="req-number" class="flex items-center">
                                                <i class="fas fa-times text-red-500 mr-2"></i>Au moins un chiffre
                                            </li>
                                        </ul>
                                    </div>
                                    
                                    <!-- Submit Button -->
                                    <div class="flex justify-end">
                                        <button type="submit" name="change_password" class="bg-netblue-600 hover:bg-netblue-700 text-white font-bold py-2 px-6 rounded-lg transition-colors">
                                            <i class="fas fa-shield-alt mr-2"></i>Changer le mot de passe
                                        </button>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Account Security -->
                            <div class="form-section bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                                <h3 class="text-lg font-bold mb-4 dark:text-white">
                                    <i class="fas fa-user-shield mr-2 text-netblue-600 dark:text-netblue-400"></i>
                                    Sécurité du compte
                                </h3>
                                
                                <div class="space-y-4">
                                    <!-- Account Status -->
                                    <div class="flex items-center justify-between p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                                        <div class="flex items-center">
                                            <i class="fas fa-check-circle text-green-500 mr-3"></i>
                                            <div>
                                                <h4 class="font-medium text-green-800 dark:text-green-300">Compte actif</h4>
                                                <p class="text-sm text-green-600 dark:text-green-400">Votre compte est en règle et sécurisé</p>
                                            </div>
                                        </div>
                                        <span class="bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-300 px-3 py-1 rounded-full text-sm font-medium">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Last Login -->
                                    <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                        <div class="flex items-center">
                                            <i class="fas fa-clock text-gray-500 mr-3"></i>
                                            <div>
                                                <h4 class="font-medium dark:text-white">Dernière connexion</h4>
                                                <p class="text-sm text-gray-600 dark:text-gray-400">Aujourd'hui</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Two-Factor Authentication (Placeholder) -->
                                    <div class="flex items-center justify-between p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                                        <div class="flex items-center">
                                            <i class="fas fa-mobile-alt text-yellow-500 mr-3"></i>
                                            <div>
                                                <h4 class="font-medium text-yellow-800 dark:text-yellow-300">Authentification à deux facteurs</h4>
                                                <p class="text-sm text-yellow-600 dark:text-yellow-400">Renforcez la sécurité de votre compte</p>
                                            </div>
                                        </div>
                                        <button class="bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-300 px-3 py-1 rounded-full text-sm font-medium hover:bg-yellow-200 dark:hover:bg-yellow-800 transition-colors">
                                            Activer (Bientôt)
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Activity Tab -->
                    <div id="activity" class="tab-content">
                        <div class="space-y-6">
                            <!-- Recent Formations -->
                            <?php if (!empty($recent_formations)): ?>
                            <div class="form-section bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                                <h3 class="text-lg font-bold mb-4 dark:text-white">
                                    <i class="fas fa-graduation-cap mr-2 text-netblue-600 dark:text-netblue-400"></i>
                                    Formations récentes
                                </h3>
                                
                                <div class="space-y-4">
                                    <?php foreach ($recent_formations as $formation): ?>
                                    <div class="flex items-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                        <div class="flex-shrink-0 w-12 h-12 rounded-lg overflow-hidden">
                                            <img src="../<?php echo htmlspecialchars($formation['cover_image']); ?>" alt="<?php echo htmlspecialchars($formation['title']); ?>" class="w-full h-full object-cover">
                                        </div>
                                        <div class="ml-4 flex-1">
                                            <h4 class="font-medium dark:text-white"><?php echo htmlspecialchars($formation['title']); ?></h4>
                                            <div class="flex items-center text-sm text-gray-600 dark:text-gray-400 mt-1">
                                                <i class="fas <?php echo htmlspecialchars($formation['category_icon']); ?> mr-1"></i>
                                                <span><?php echo htmlspecialchars($formation['category_name']); ?></span>
                                                <span class="mx-2">•</span>
                                                <span>Inscrit le <?php echo date('d/m/Y', strtotime($formation['start_date'])); ?></span>
                                            </div>
                                        </div>
                                        <div class="flex-shrink-0">
                                            <span class="px-3 py-1 rounded-full text-xs font-medium
                                                <?php 
                                                switch($formation['status']) {
                                                    case 'active':
                                                        echo 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300';
                                                        break;
                                                    case 'pending':
                                                        echo 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300';
                                                        break;
                                                    case 'expired':
                                                        echo 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
                                                        break;
                                                    default:
                                                        echo 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-300';
                                                }
                                                ?>">
                                                <?php 
                                                switch($formation['status']) {
                                                    case 'active':
                                                        echo 'Active';
                                                        break;
                                                    case 'pending':
                                                        echo 'En attente';
                                                        break;
                                                    case 'expired':
                                                        echo 'Expirée';
                                                        break;
                                                    default:
                                                        echo ucfirst($formation['status']);
                                                }
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="mt-4 text-center">
                                    <a href="my-formations.php" class="text-netblue-600 dark:text-netblue-400 hover:text-netblue-700 dark:hover:text-netblue-300 font-medium">
                                        Voir toutes mes formations <i class="fas fa-arrow-right ml-1"></i>
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Account Activity -->
                            <div class="form-section bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                                <h3 class="text-lg font-bold mb-4 dark:text-white">
                                    <i class="fas fa-history mr-2 text-netblue-600 dark:text-netblue-400"></i>
                                    Activité du compte
                                </h3>
                                
                                <div class="space-y-4">
                                    <!-- Account Created -->
                                    <div class="flex items-center p-3 border-l-4 border-green-500 bg-green-50 dark:bg-green-900/20">
                                        <i class="fas fa-user-plus text-green-500 mr-3"></i>
                                        <div>
                                            <p class="font-medium text-green-800 dark:text-green-300">Compte créé</p>
                                            <p class="text-sm text-green-600 dark:text-green-400"><?php echo date('d/m/Y à H:i', strtotime($user['created_at'])); ?></p>
                                        </div>
                                    </div>
                                    
                                    <!-- Last Profile Update -->
                                    <div class="flex items-center p-3 border-l-4 border-blue-500 bg-blue-50 dark:bg-blue-900/20">
                                        <i class="fas fa-edit text-blue-500 mr-3"></i>
                                        <div>
                                            <p class="font-medium text-blue-800 dark:text-blue-300">Profil mis à jour</p>
                                            <p class="text-sm text-blue-600 dark:text-blue-400"><?php echo date('d/m/Y à H:i', strtotime($user['updated_at'])); ?></p>
                                        </div>
                                    </div>
                                    
                                    <!-- Login Activity (Placeholder) -->
                                    <div class="flex items-center p-3 border-l-4 border-gray-500 bg-gray-50 dark:bg-gray-700">
                                        <i class="fas fa-sign-in-alt text-gray-500 mr-3"></i>
                                        <div>
                                            <p class="font-medium text-gray-800 dark:text-white">Dernière connexion</p>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Aujourd'hui à <?php echo date('H:i'); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Data Export -->
                            <div class="form-section bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-6">
                                <h3 class="text-lg font-bold mb-4 dark:text-white">
                                    <i class="fas fa-download mr-2 text-netblue-600 dark:text-netblue-400"></i>
                                    Exportation des données
                                </h3>
                                
                                <p class="text-gray-600 dark:text-gray-400 mb-4">
                                    Vous pouvez télécharger une copie de toutes vos données personnelles stockées sur notre plateforme.
                                </p>
                                
                                <div class="flex flex-col sm:flex-row gap-4">
                                    <button class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded-lg transition-colors">
                                        <i class="fas fa-file-pdf mr-2"></i>Exporter au format PDF
                                    </button>
                                    <button class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors">
                                        <i class="fas fa-file-excel mr-2"></i>Exporter au format Excel
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Danger Zone -->
                            <div class="form-section bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-6">
                                <h3 class="text-lg font-bold mb-4 text-red-800 dark:text-red-300">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>
                                    Zone de danger
                                </h3>
                                
                                <p class="text-red-600 dark:text-red-400 mb-4">
                                    Les actions suivantes sont irréversibles. Veuillez procéder avec prudence.
                                </p>
                                
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="font-medium text-red-800 dark:text-red-300">Désactiver le compte</h4>
                                            <p class="text-sm text-red-600 dark:text-red-400">Votre compte sera temporairement désactivé</p>
                                        </div>
                                        <button class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg transition-colors" onclick="confirmAction('deactivate')">
                                            Désactiver
                                        </button>
                                    </div>
                                    
                                    <div class="border-t border-red-200 dark:border-red-800 pt-3">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <h4 class="font-medium text-red-800 dark:text-red-300">Supprimer le compte</h4>
                                                <p class="text-sm text-red-600 dark:text-red-400">Suppression définitive de toutes vos données</p>
                                            </div>
                                            <button class="bg-red-700 hover:bg-red-800 text-white px-4 py-2 rounded-lg transition-colors" onclick="confirmAction('delete')">
                                                Supprimer
                                            </button>
                                        </div>
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
    
    <!-- Confirmation Modal -->
    <div id="confirmation-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 opacity-0 invisible transition-all duration-300">
        <div class="absolute inset-0 bg-black bg-opacity-50" onclick="closeConfirmationModal()"></div>
        <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full">
            <div class="p-6">
                <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 dark:bg-red-900/30 rounded-full mb-4">
                    <i class="fas fa-exclamation-triangle text-red-600 dark:text-red-400 text-xl"></i>
                </div>
                <h3 class="text-lg font-bold text-center mb-2 dark:text-white" id="modal-title">Confirmer l'action</h3>
                <p class="text-gray-600 dark:text-gray-400 text-center mb-6" id="modal-message">Êtes-vous sûr de vouloir continuer ?</p>
                
                <div class="flex space-x-3">
                    <button onclick="closeConfirmationModal()" class="flex-1 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-white font-bold py-2 px-4 rounded-lg transition-colors">
                        Annuler
                    </button>
                    <button id="confirm-action" class="flex-1 bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded-lg transition-colors">
                        Confirmer
                    </button>
                </div>
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
            
            // Tab functionality
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const targetTab = this.getAttribute('data-tab');
                    
                    // Remove active class from all buttons and contents
                    tabButtons.forEach(btn => {
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
            
            // Profile image preview
            const profileImageInput = document.getElementById('profile_image');
            const currentAvatar = document.getElementById('current-avatar');
            
            if (profileImageInput) {
                profileImageInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            if (currentAvatar.tagName === 'IMG') {
                                currentAvatar.src = e.target.result;
                            } else {
                                // Replace text with image
                                const img = document.createElement('img');
                                img.src = e.target.result;
                                img.alt = 'Profile';
                                img.className = 'w-full h-full object-cover';
                                img.id = 'current-avatar';
                                currentAvatar.parentNode.replaceChild(img, currentAvatar);
                            }
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
            
            // Password strength checker
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const passwordStrengthBar = document.getElementById('password-strength-bar');
            const passwordStrengthText = document.getElementById('password-strength-text');
            const passwordMatchDiv = document.getElementById('password-match');
            
            if (newPasswordInput) {
                newPasswordInput.addEventListener('input', function() {
                    const password = this.value;
                    const strength = calculatePasswordStrength(password);
                    updatePasswordStrength(strength);
                    updatePasswordRequirements(password);
                });
            }
            
            if (confirmPasswordInput) {
                confirmPasswordInput.addEventListener('input', function() {
                    const newPassword = newPasswordInput.value;
                    const confirmPassword = this.value;
                    
                    if (confirmPassword.length > 0) {
                        if (newPassword === confirmPassword) {
                            passwordMatchDiv.classList.add('hidden');
                            this.classList.remove('border-red-500');
                            this.classList.add('border-green-500');
                        } else {
                            passwordMatchDiv.classList.remove('hidden');
                            this.classList.remove('border-green-500');
                            this.classList.add('border-red-500');
                        }
                    } else {
                        passwordMatchDiv.classList.add('hidden');
                        this.classList.remove('border-red-500', 'border-green-500');
                    }
                });
            }
            
            function calculatePasswordStrength(password) {
                let score = 0;
                
                // Length
                if (password.length >= 6) score += 1;
                if (password.length >= 8) score += 1;
                if (password.length >= 12) score += 1;
                
                // Character variety
                if (/[a-z]/.test(password)) score += 1;
                if (/[A-Z]/.test(password)) score += 1;
                if (/[0-9]/.test(password)) score += 1;
                if (/[^A-Za-z0-9]/.test(password)) score += 1;
                
                return Math.min(score, 4);
            }
            
            function updatePasswordStrength(strength) {
                const strengthClasses = ['strength-weak', 'strength-fair', 'strength-good', 'strength-strong'];
                const strengthTexts = ['Faible', 'Moyen', 'Bon', 'Fort'];
                const strengthColors = ['bg-red-500', 'bg-orange-500', 'bg-yellow-500', 'bg-green-500'];
                
                // Remove all strength classes
                passwordStrengthBar.classList.remove(...strengthClasses, ...strengthColors);
                
                if (strength > 0) {
                    passwordStrengthBar.classList.add(strengthClasses[strength - 1]);
                    passwordStrengthBar.classList.add(strengthColors[strength - 1]);
                    passwordStrengthText.textContent = strengthTexts[strength - 1];
                } else {
                    passwordStrengthBar.classList.add('strength-weak');
                    passwordStrengthText.textContent = 'Faible';
                }
            }
            
            function updatePasswordRequirements(password) {
                const requirements = [
                    { id: 'req-length', test: password.length >= 6 },
                    { id: 'req-uppercase', test: /[A-Z]/.test(password) },
                    { id: 'req-lowercase', test: /[a-z]/.test(password) },
                    { id: 'req-number', test: /[0-9]/.test(password) }
                ];
                
                requirements.forEach(req => {
                    const element = document.getElementById(req.id);
                    const icon = element.querySelector('i');
                    
                    if (req.test) {
                        icon.classList.remove('fa-times', 'text-red-500');
                        icon.classList.add('fa-check', 'text-green-500');
                    } else {
                        icon.classList.remove('fa-check', 'text-green-500');
                        icon.classList.add('fa-times', 'text-red-500');
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
        
        // Password visibility toggle
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(inputId + '_icon');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Confirmation modal
        function confirmAction(action) {
            const modal = document.getElementById('confirmation-modal');
            const title = document.getElementById('modal-title');
            const message = document.getElementById('modal-message');
            const confirmBtn = document.getElementById('confirm-action');
            
            if (action === 'deactivate') {
                title.textContent = 'Désactiver le compte';
                message.textContent = 'Votre compte sera temporairement désactivé. Vous pourrez le réactiver en nous contactant.';
                confirmBtn.onclick = function() {
                    alert('Fonctionnalité en cours de développement');
                    closeConfirmationModal();
                };
            } else if (action === 'delete') {
                title.textContent = 'Supprimer le compte';
                message.textContent = 'Cette action est irréversible. Toutes vos données seront définitivement supprimées.';
                confirmBtn.onclick = function() {
                    alert('Fonctionnalité en cours de développement');
                    closeConfirmationModal();
                };
            }
            
            modal.classList.remove('opacity-0', 'invisible');
            modal.classList.add('opacity-100', 'visible');
            document.body.style.overflow = 'hidden';
        }
        
        function closeConfirmationModal() {
            const modal = document.getElementById('confirmation-modal');
            modal.classList.remove('opacity-100', 'visible');
            modal.classList.add('opacity-0', 'invisible');
            document.body.style.overflow = '';
        }
        
        // Make toggleMobileMenu function globally accessible
        function toggleMobileMenu() {
            const sidenav = document.getElementById('sidenav');
            const overlay = document.getElementById('overlay');
            
            sidenav.classList.toggle('open');
            overlay.classList.toggle('active');
            document.body.classList.toggle('overflow-hidden');
        }
        
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const requiredInputs = form.querySelectorAll('input[required]');
                    let isValid = true;
                    
                    requiredInputs.forEach(input => {
                        if (!input.value.trim()) {
                            isValid = false;
                            input.classList.add('border-red-500');
                            input.classList.remove('border-green-500');
                        } else {
                            input.classList.remove('border-red-500');
                            input.classList.add('border-green-500');
                        }
                    });
                    
                    if (!isValid) {
                        e.preventDefault();
                        alert('Veuillez remplir tous les champs obligatoires.');
                    }
                });
            });
        });
        
        // Auto-save form data
        function autoSaveFormData() {
            const formInputs = document.querySelectorAll('#profile-info input, #profile-info textarea');
            
            formInputs.forEach(input => {
                input.addEventListener('input', function() {
                    const formData = {};
                    formInputs.forEach(inp => {
                        if (inp.name && inp.type !== 'file') {
                            formData[inp.name] = inp.value;
                        }
                    });
                    localStorage.setItem('profileFormData', JSON.stringify(formData));
                });
            });
        }
        
        function loadSavedFormData() {
            const savedData = localStorage.getItem('profileFormData');
            if (savedData) {
                const formData = JSON.parse(savedData);
                Object.keys(formData).forEach(key => {
                    const input = document.querySelector(`[name="${key}"]`);
                    if (input && input.type !== 'file') {
                        input.value = formData[key];
                    }
                });
            }
        }
        
        function clearSavedFormData() {
            localStorage.removeItem('profileFormData');
        }
        
        // Initialize auto-save
        document.addEventListener('DOMContentLoaded', function() {
            autoSaveFormData();
            
            // Clear saved data on successful form submission
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function() {
                    setTimeout(clearSavedFormData, 1000);
                });
            });
        });
        
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
        
        // Back to top button
        const backToTopButton = document.createElement('button');
        backToTopButton.id = 'back-to-top';
        backToTopButton.className = 'fixed bottom-6 right-6 bg-netblue-600 dark:bg-netblue-700 text-white w-12 h-12 rounded-full flex items-center justify-center shadow-lg opacity-0 invisible transition-all hover:bg-netblue-700 dark:hover:bg-netblue-600';
        backToTopButton.innerHTML = '<i class="fas fa-arrow-up"></i>';
        backToTopButton.title = 'Retour en haut';
        
        backToTopButton.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
        
        document.body.appendChild(backToTopButton);
        
        // Show/hide back to top button
        window.addEventListener('scroll', function() {
            if (window.scrollY > 300) {
                backToTopButton.classList.remove('opacity-0', 'invisible');
                backToTopButton.classList.add('opacity-100', 'visible');
            } else {
                backToTopButton.classList.remove('opacity-100', 'visible');
                backToTopButton.classList.add('opacity-0', 'invisible');
            }
        });
        
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
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }
        
        // Example usage for successful form submissions
        <?php if (!empty($success_messages)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            <?php foreach ($success_messages as $message): ?>
            showNotification('<?php echo addslashes($message); ?>', 'success');
            <?php endforeach; ?>
        });
        <?php endif; ?>
    </script>
</body>
</html>