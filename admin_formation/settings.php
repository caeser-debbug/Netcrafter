<?php
// admin/settings.php
session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
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

// Récupérer les informations de l'admin
$admin_id = $_SESSION['admin_id'];
$admin_query = "SELECT * FROM admins WHERE id = ?";
$stmt = $conn->prepare($admin_query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin_result = $stmt->get_result();
$admin = $admin_result->fetch_assoc();

// Traitement des formulaires
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_general':
            $site_name = $_POST['site_name'] ?? '';
            $site_description = $_POST['site_description'] ?? '';
            $maintenance_mode = isset($_POST['maintenance_mode']) ? '1' : '0';
            $registration_enabled = isset($_POST['registration_enabled']) ? '1' : '0';
            
            // Mise à jour des paramètres généraux
            $settings = [
                'site_name' => $site_name,
                'site_description' => $site_description,
                'maintenance_mode' => $maintenance_mode,
                'registration_enabled' => $registration_enabled
            ];
            
            foreach ($settings as $key => $value) {
                $update_query = "UPDATE system_settings SET setting_value = ? WHERE setting_key = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param("ss", $value, $key);
                $stmt->execute();
            }
            
            $message = "Paramètres généraux mis à jour avec succès";
            $message_type = "success";
            break;
            
        case 'update_notifications':
            $email_notifications = isset($_POST['email_notifications']) ? '1' : '0';
            $auto_approve_subscriptions = isset($_POST['auto_approve_subscriptions']) ? '1' : '0';
            
            $settings = [
                'email_notifications' => $email_notifications,
                'auto_approve_subscriptions' => $auto_approve_subscriptions
            ];
            
            foreach ($settings as $key => $value) {
                $update_query = "UPDATE system_settings SET setting_value = ? WHERE setting_key = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param("ss", $value, $key);
                $stmt->execute();
            }
            
            $message = "Paramètres de notifications mis à jour avec succès";
            $message_type = "success";
            break;
            
        case 'update_files':
            $max_file_size = $_POST['max_file_size'] ?? '50';
            $supported_video_formats = $_POST['supported_video_formats'] ?? 'mp4,webm,avi';
            
            $settings = [
                'max_file_size' => $max_file_size,
                'supported_video_formats' => $supported_video_formats
            ];
            
            foreach ($settings as $key => $value) {
                $update_query = "UPDATE system_settings SET setting_value = ? WHERE setting_key = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param("ss", $value, $key);
                $stmt->execute();
            }
            
            $message = "Paramètres de fichiers mis à jour avec succès";
            $message_type = "success";
            break;
            
        case 'update_payments':
            $payment_methods = [];
            if (isset($_POST['nita'])) $payment_methods[] = 'nita';
            if (isset($_POST['amana'])) $payment_methods[] = 'amana';
            if (isset($_POST['zeyna'])) $payment_methods[] = 'zeyna';
            if (isset($_POST['niya'])) $payment_methods[] = 'niya';
            
            $payment_methods_str = implode(',', $payment_methods);
            
            $update_query = "UPDATE system_settings SET setting_value = ? WHERE setting_key = 'payment_methods'";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("s", $payment_methods_str);
            $stmt->execute();
            
            $message = "Méthodes de paiement mises à jour avec succès";
            $message_type = "success";
            break;
            
        case 'backup_database':
            // Simulation de sauvegarde
            $backup_filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $backup_path = '../backups/' . $backup_filename;
            
            // Créer le dossier de sauvegarde s'il n'existe pas
            if (!file_exists('../backups/')) {
                mkdir('../backups/', 0755, true);
            }
            
            // Insérer l'enregistrement de sauvegarde
            $insert_backup = "INSERT INTO backups (filename, file_path, file_size, backup_type, status) VALUES (?, ?, ?, 'full', 'completed')";
            $stmt = $conn->prepare($insert_backup);
            $file_size = rand(1000000, 5000000); // Simulation
            $stmt->bind_param("ssi", $backup_filename, $backup_path, $file_size);
            $stmt->execute();
            
            $message = "Sauvegarde de la base de données créée avec succès";
            $message_type = "success";
            break;
    }
}

// Récupérer les paramètres actuels
$settings_query = "SELECT setting_key, setting_value FROM system_settings";
$settings_result = $conn->query($settings_query);
$current_settings = [];
while ($row = $settings_result->fetch_assoc()) {
    $current_settings[$row['setting_key']] = $row['setting_value'];
}

// Récupérer les sauvegardes récentes
$backups_query = "SELECT * FROM backups ORDER BY created_at DESC LIMIT 5";
$backups_result = $conn->query($backups_query);
$recent_backups = [];
while ($row = $backups_result->fetch_assoc()) {
    $recent_backups[] = $row;
}

// Statistiques système
$stats = [];

// Espace disque utilisé (simulation)
$stats['disk_usage'] = rand(40, 80);

// Taille de la base de données
$db_size_query = "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'db_size' FROM information_schema.tables WHERE table_schema = ?";
$stmt = $conn->prepare($db_size_query);
$stmt->bind_param("s", $dbname);
$stmt->execute();
$db_size_result = $stmt->get_result();
$stats['db_size'] = $db_size_result->fetch_assoc()['db_size'] ?? 0;

// Nombre de fichiers uploadés
$files_query = "SELECT COUNT(*) as total_files FROM formations WHERE cover_image IS NOT NULL 
                UNION ALL 
                SELECT COUNT(*) FROM formation_videos 
                UNION ALL 
                SELECT COUNT(*) FROM formation_subscriptions WHERE payment_proof IS NOT NULL";
$files_result = $conn->query($files_query);
$total_files = 0;
while ($row = $files_result->fetch_assoc()) {
    $total_files += $row['total_files'];
}
$stats['total_files'] = $total_files;

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr" class="light scroll">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - Netcrafter Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
    
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
            
            .sidenav.collapsed .nav-text {
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
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        /* Toggle switch */
        .toggle-switch {
            position: relative;
            width: 60px;
            height: 30px;
            background-color: #ccc;
            border-radius: 15px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .toggle-switch.active {
            background-color: #3B82F6;
        }

        .toggle-switch::before {
            content: '';
            position: absolute;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background-color: white;
            top: 2px;
            left: 2px;
            transition: transform 0.3s;
        }

        .toggle-switch.active::before {
            transform: translateX(30px);
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
            <div class="flex items-center">
                <img src="../image/logo-n.png" alt="Netcrafter Logo" class="h-8 mr-2">
                <span class="text-lg font-bold text-netblue-600 dark:text-netblue-400 nav-text transition-opacity duration-300">ADMIN</span>
            </div>
            <button id="sidenav-toggle" class="text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 focus:outline-none md:block hidden">
                <i class="fas fa-chevron-left"></i>
            </button>
        </div>
        
        <!-- Admin info -->
        <div class="px-4 py-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center">
                <div class="w-10 h-10 rounded-full bg-netblue-600 dark:bg-netblue-700 flex items-center justify-center text-white font-bold text-lg flex-shrink-0">
                    <?php echo strtoupper(substr($admin['firstname'], 0, 1) . substr($admin['lastname'], 0, 1)); ?>
                </div>
                <div class="ml-3 overflow-hidden nav-text">
                    <p class="font-medium text-gray-800 dark:text-white truncate"><?php echo htmlspecialchars($admin['firstname'] . ' ' . $admin['lastname']); ?></p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 truncate"><?php echo ucfirst($admin['role']); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Navigation Menu -->
        <nav class="mt-4 px-2 overflow-y-auto" style="max-height: calc(100vh - 200px);">
            <ul class="space-y-1">
                <li>
                    <a href="dashboard.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-tachometer-alt w-6 text-center"></i>
                        <span class="ml-2 nav-text">Tableau de bord</span>
                    </a>
                </li>
                <li>
                    <a href="users.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-users w-6 text-center"></i>
                        <span class="ml-2 nav-text">Utilisateurs</span>
                    </a>
                </li>
                <li>
                    <a href="formations.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-graduation-cap w-6 text-center"></i>
                        <span class="ml-2 nav-text">Formations</span>
                    </a>
                </li>
                <li>
                    <a href="subscriptions.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-credit-card w-6 text-center"></i>
                        <span class="ml-2 nav-text">Abonnements</span>
                    </a>
                </li>
                <li>
                    <a href="quiz.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-question-circle w-6 text-center"></i>
                        <span class="ml-2 nav-text">Quizz</span>
                    </a>
                </li>
                <li>
                    <a href="certificates.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-certificate w-6 text-center"></i>
                        <span class="ml-2 nav-text">Certificats</span>
                    </a>
                </li>
                <li>
                    <a href="forum.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-comments w-6 text-center"></i>
                        <span class="ml-2 nav-text">Forum</span>
                    </a>
                </li>
                <li>
                    <a href="reports.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-chart-bar w-6 text-center"></i>
                        <span class="ml-2 nav-text">Rapports</span>
                    </a>
                </li>
                <li class="pt-2 mt-2 border-t border-gray-200 dark:border-gray-700">
                    <a href="settings.php" class="flex items-center px-3 py-2 text-base rounded-lg bg-netblue-100 dark:bg-netblue-900/30 text-netblue-800 dark:text-netblue-300">
                        <i class="fas fa-cog w-6 text-center"></i>
                        <span class="ml-2 nav-text">Paramètres</span>
                    </a>
                </li>
                <li>
                    <a href="admins.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200">
                        <i class="fas fa-shield-alt w-6 text-center"></i>
                        <span class="ml-2 nav-text">Administrateurs</span>
                    </a>
                </li>
            </ul>
        </nav>
        
        <!-- Logout -->
        <div class="absolute bottom-0 left-0 right-0 border-t border-gray-200 dark:border-gray-700 p-4 bg-white dark:bg-gray-800">
            <a href="logout.php" class="flex items-center justify-center px-3 py-2 rounded-lg text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors duration-200">
                <i class="fas fa-sign-out-alt w-6 text-center"></i>
                <span class="ml-2 nav-text">Déconnexion</span>
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
                <div class="flex items-center space-x-4">
                    <!-- Profile -->
                    <div class="flex items-center">
                        <div class="w-8 h-8 rounded-full bg-netblue-600 flex items-center justify-center text-white font-bold text-sm">
                            <?php echo strtoupper(substr($admin['firstname'], 0, 1) . substr($admin['lastname'], 0, 1)); ?>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Main Content Area -->
        <main class="p-4">
            <!-- Messages -->
            <?php if ($message): ?>
            <div class="mb-6">
                <div class="alert alert-<?php echo $message_type; ?> bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-100 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-400 text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 px-4 py-3 rounded-lg" data-aos="fade-down">
                    <div class="flex items-center">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?> mr-2"></i>
                        <span><?php echo htmlspecialchars($message); ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Settings Tabs -->
            <div class="mb-8">
                <div class="border-b border-gray-200 dark:border-gray-700">
                    <nav class="-mb-px flex space-x-8">
                        <button onclick="showTab('general')" class="tab-button py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap active">
                            <i class="fas fa-cog mr-2"></i>
                            Général
                        </button>
                        <button onclick="showTab('notifications')" class="tab-button py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap">
                            <i class="fas fa-bell mr-2"></i>
                            Notifications
                        </button>
                        <button onclick="showTab('files')" class="tab-button py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap">
                            <i class="fas fa-file mr-2"></i>
                            Fichiers
                        </button>
                        <button onclick="showTab('payments')" class="tab-button py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap">
                            <i class="fas fa-credit-card mr-2"></i>
                            Paiements
                        </button>
                        <button onclick="showTab('system')" class="tab-button py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap">
                            <i class="fas fa-server mr-2"></i>
                            Système
                        </button>
                        <button onclick="showTab('backup')" class="tab-button py-2 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap">
                            <i class="fas fa-database mr-2"></i>
                            Sauvegarde
                        </button>
                    </nav>
                </div>
            </div>

            <!-- General Settings Tab -->
            <div id="general-tab" class="tab-content">
                <div class="settings-section bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up">
                    <h3 class="text-lg font-bold mb-6 dark:text-white flex items-center">
                        <i class="fas fa-cog text-netblue-600 mr-2"></i>
                        Paramètres généraux
                    </h3>
                    
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="action" value="update_general">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Nom de la plateforme
                                </label>
                                <input type="text" name="site_name" value="<?php echo htmlspecialchars($current_settings['site_name'] ?? ''); ?>" 
                                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-netblue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Description
                                </label>
                                <input type="text" name="site_description" value="<?php echo htmlspecialchars($current_settings['site_description'] ?? ''); ?>" 
                                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-netblue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <div>
                                    <h4 class="font-medium text-gray-800 dark:text-white">Mode maintenance</h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Désactiver temporairement la plateforme</p>
                                </div>
                                <div class="toggle-switch <?php echo ($current_settings['maintenance_mode'] ?? '0') == '1' ? 'active' : ''; ?>" 
                                     onclick="toggleSwitch(this, 'maintenance_mode')">
                                </div>
                                <input type="hidden" name="maintenance_mode" id="maintenance_mode" value="<?php echo ($current_settings['maintenance_mode'] ?? '0') == '1' ? '1' : ''; ?>">
                            </div>
                            
                            <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <div>
                                    <h4 class="font-medium text-gray-800 dark:text-white">Inscriptions ouvertes</h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Permettre aux nouveaux utilisateurs de s'inscrire</p>
                                </div>
                                <div class="toggle-switch <?php echo ($current_settings['registration_enabled'] ?? '1') == '1' ? 'active' : ''; ?>" 
                                     onclick="toggleSwitch(this, 'registration_enabled')">
                                </div>
                                <input type="hidden" name="registration_enabled" id="registration_enabled" value="<?php echo ($current_settings['registration_enabled'] ?? '1') == '1' ? '1' : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" class="bg-netblue-600 hover:bg-netblue-700 text-white px-6 py-2 rounded-lg transition-colors">
                                <i class="fas fa-save mr-2"></i>
                                Enregistrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Notifications Settings Tab -->
            <div id="notifications-tab" class="tab-content hidden">
                <div class="settings-section bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up">
                    <h3 class="text-lg font-bold mb-6 dark:text-white flex items-center">
                        <i class="fas fa-bell text-netblue-600 mr-2"></i>
                        Paramètres de notifications
                    </h3>
                    
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="action" value="update_notifications">
                        
                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <div>
                                    <h4 class="font-medium text-gray-800 dark:text-white">Notifications par email</h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Envoyer des notifications aux utilisateurs par email</p>
                                </div>
                                <div class="toggle-switch <?php echo ($current_settings['email_notifications'] ?? '1') == '1' ? 'active' : ''; ?>" 
                                     onclick="toggleSwitch(this, 'email_notifications')">
                                </div>
                                <input type="hidden" name="email_notifications" id="email_notifications" value="<?php echo ($current_settings['email_notifications'] ?? '1') == '1' ? '1' : ''; ?>">
                            </div>
                            
                            <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <div>
                                    <h4 class="font-medium text-gray-800 dark:text-white">Approbation automatique</h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Approuver automatiquement les abonnements</p>
                                </div>
                                <div class="toggle-switch <?php echo ($current_settings['auto_approve_subscriptions'] ?? '0') == '1' ? 'active' : ''; ?>" 
                                     onclick="toggleSwitch(this, 'auto_approve_subscriptions')">
                                </div>
                                <input type="hidden" name="auto_approve_subscriptions" id="auto_approve_subscriptions" value="<?php echo ($current_settings['auto_approve_subscriptions'] ?? '0') == '1' ? '1' : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" class="bg-netblue-600 hover:bg-netblue-700 text-white px-6 py-2 rounded-lg transition-colors">
                                <i class="fas fa-save mr-2"></i>
                                Enregistrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Files Settings Tab -->
            <div id="files-tab" class="tab-content hidden">
                <div class="settings-section bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up">
                    <h3 class="text-lg font-bold mb-6 dark:text-white flex items-center">
                        <i class="fas fa-file text-netblue-600 mr-2"></i>
                        Paramètres des fichiers
                    </h3>
                    
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="action" value="update_files">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Taille maximale des fichiers (MB)
                                </label>
                                <input type="number" name="max_file_size" value="<?php echo htmlspecialchars($current_settings['max_file_size'] ?? '50'); ?>" 
                                       min="1" max="500"
                                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-netblue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Formats vidéo supportés
                                </label>
                                <input type="text" name="supported_video_formats" value="<?php echo htmlspecialchars($current_settings['supported_video_formats'] ?? 'mp4,webm,avi'); ?>" 
                                       placeholder="mp4,webm,avi"
                                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-netblue-500 focus:border-transparent dark:bg-gray-700 dark:text-white">
                                <p class="text-xs text-gray-500 mt-1">Séparez les formats par des virgules</p>
                            </div>
                        </div>
                        
                        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                            <h4 class="font-medium text-blue-800 dark:text-blue-300 mb-2">
                                <i class="fas fa-info-circle mr-2"></i>
                                Informations sur le stockage
                            </h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                                <div>
                                    <span class="text-blue-600 dark:text-blue-400">Fichiers totaux:</span>
                                    <span class="font-medium"><?php echo number_format($stats['total_files']); ?></span>
                                </div>
                                <div>
                                    <span class="text-blue-600 dark:text-blue-400">Taille DB:</span>
                                    <span class="font-medium"><?php echo number_format($stats['db_size'], 2); ?> MB</span>
                                </div>
                                <div>
                                    <span class="text-blue-600 dark:text-blue-400">Utilisation disque:</span>
                                    <span class="font-medium"><?php echo $stats['disk_usage']; ?>%</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" class="bg-netblue-600 hover:bg-netblue-700 text-white px-6 py-2 rounded-lg transition-colors">
                                <i class="fas fa-save mr-2"></i>
                                Enregistrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Payments Settings Tab -->
            <div id="payments-tab" class="tab-content hidden">
                <div class="settings-section bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up">
                    <h3 class="text-lg font-bold mb-6 dark:text-white flex items-center">
                        <i class="fas fa-credit-card text-netblue-600 mr-2"></i>
                        Méthodes de paiement
                    </h3>
                    
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="action" value="update_payments">
                        
                        <?php 
                        $payment_methods = explode(',', $current_settings['payment_methods'] ?? 'nita,amana,zeyna,niya');
                        $all_methods = [
                            'nita' => ['name' => 'NITA Money', 'icon' => 'fa-mobile-alt', 'color' => 'green'],
                            'amana' => ['name' => 'Amana Money', 'icon' => 'fa-mobile-alt', 'color' => 'blue'],
                            'zeyna' => ['name' => 'Zeyna Money', 'icon' => 'fa-mobile-alt', 'color' => 'purple'],
                            'niya' => ['name' => 'Niya Money', 'icon' => 'fa-mobile-alt', 'color' => 'orange']
                        ];
                        ?>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php foreach ($all_methods as $key => $method): ?>
                            <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-<?php echo $method['color']; ?>-100 dark:bg-<?php echo $method['color']; ?>-900 rounded-lg flex items-center justify-center mr-3">
                                        <i class="fas <?php echo $method['icon']; ?> text-<?php echo $method['color']; ?>-600 dark:text-<?php echo $method['color']; ?>-400"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-800 dark:text-white"><?php echo $method['name']; ?></h4>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Paiement mobile</p>
                                    </div>
                                </div>
                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox" name="<?php echo $key; ?>" value="1" 
                                           <?php echo in_array($key, $payment_methods) ? 'checked' : ''; ?>
                                           class="sr-only">
                                    <div class="toggle-switch <?php echo in_array($key, $payment_methods) ? 'active' : ''; ?>" 
                                         onclick="toggleCheckbox(this, '<?php echo $key; ?>')">
                                    </div>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="flex justify-end">
                            <button type="submit" class="bg-netblue-600 hover:bg-netblue-700 text-white px-6 py-2 rounded-lg transition-colors">
                                <i class="fas fa-save mr-2"></i>
                                Enregistrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- System Info Tab -->
            <div id="system-tab" class="tab-content hidden">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- System Information -->
                    <div class="settings-section bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up">
                        <h3 class="text-lg font-bold mb-6 dark:text-white flex items-center">
                            <i class="fas fa-server text-netblue-600 mr-2"></i>
                            Informations système
                        </h3>
                        
                        <div class="space-y-4">
                            <div class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-600">
                                <span class="text-gray-600 dark:text-gray-400">Version PHP:</span>
                                <span class="font-medium"><?php echo phpversion(); ?></span>
                            </div>
                            
                            <div class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-600">
                                <span class="text-gray-600 dark:text-gray-400">Serveur web:</span>
                                <span class="font-medium"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?></span>
                            </div>
                            
                            <div class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-600">
                                <span class="text-gray-600 dark:text-gray-400">Base de données:</span>
                                <span class="font-medium">MySQL <?php echo $conn->server_info ?? 'N/A'; ?></span>
                            </div>
                            
                            <div class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-600">
                                <span class="text-gray-600 dark:text-gray-400">Limite mémoire:</span>
                                <span class="font-medium"><?php echo ini_get('memory_limit'); ?></span>
                            </div>
                            
                            <div class="flex justify-between items-center py-2 border-b border-gray-200 dark:border-gray-600">
                                <span class="text-gray-600 dark:text-gray-400">Limite d'exécution:</span>
                                <span class="font-medium"><?php echo ini_get('max_execution_time'); ?>s</span>
                            </div>
                            
                            <div class="flex justify-between items-center py-2">
                                <span class="text-gray-600 dark:text-gray-400">Taille max upload:</span>
                                <span class="font-medium"><?php echo ini_get('upload_max_filesize'); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Performance Stats -->
                    <div class="settings-section bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up" data-aos-delay="100">
                        <h3 class="text-lg font-bold mb-6 dark:text-white flex items-center">
                            <i class="fas fa-chart-line text-netblue-600 mr-2"></i>
                            Performance
                        </h3>
                        
                        <div class="space-y-4">
                            <!-- Disk Usage -->
                            <div>
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-gray-600 dark:text-gray-400">Utilisation disque</span>
                                    <span class="font-medium"><?php echo $stats['disk_usage']; ?>%</span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                                    <div class="bg-<?php echo $stats['disk_usage'] > 80 ? 'red' : ($stats['disk_usage'] > 60 ? 'yellow' : 'green'); ?>-500 h-3 rounded-full transition-all duration-300" 
                                         style="width: <?php echo $stats['disk_usage']; ?>%"></div>
                                </div>
                            </div>
                            
                            <!-- Memory Usage -->
                            <div>
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-gray-600 dark:text-gray-400">Utilisation mémoire</span>
                                    <span class="font-medium"><?php echo round(memory_get_usage() / 1024 / 1024, 2); ?> MB</span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                                    <div class="bg-blue-500 h-3 rounded-full transition-all duration-300" 
                                         style="width: <?php echo min(round(memory_get_usage() / (1024 * 1024 * 128) * 100), 100); ?>%"></div>
                                </div>
                            </div>
                            
                            <!-- Database Size -->
                            <div class="flex justify-between items-center py-2 border-t border-gray-200 dark:border-gray-600 pt-4">
                                <span class="text-gray-600 dark:text-gray-400">Taille base de données:</span>
                                <span class="font-medium"><?php echo number_format($stats['db_size'], 2); ?> MB</span>
                            </div>
                            
                            <div class="flex justify-between items-center py-2">
                                <span class="text-gray-600 dark:text-gray-400">Fichiers uploadés:</span>
                                <span class="font-medium"><?php echo number_format($stats['total_files']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Backup Tab -->
            <div id="backup-tab" class="tab-content hidden">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Create Backup -->
                    <div class="settings-section bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up">
                        <h3 class="text-lg font-bold mb-6 dark:text-white flex items-center">
                            <i class="fas fa-database text-netblue-600 mr-2"></i>
                            Créer une sauvegarde
                        </h3>
                        
                        <div class="space-y-4">
                            <p class="text-gray-600 dark:text-gray-400">
                                Créez une sauvegarde complète de la base de données pour protéger vos données.
                            </p>
                            
                            <form method="POST" onsubmit="return confirmBackup()">
                                <input type="hidden" name="action" value="backup_database">
                                
                                <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-4">
                                    <div class="flex items-center">
                                        <i class="fas fa-exclamation-triangle text-yellow-600 mr-2"></i>
                                        <span class="text-yellow-800 dark:text-yellow-300 text-sm">
                                            Cette opération peut prendre quelques minutes selon la taille de votre base de données.
                                        </span>
                                    </div>
                                </div>
                                
                                <button type="submit" class="w-full bg-netblue-600 hover:bg-netblue-700 text-white px-6 py-3 rounded-lg transition-colors flex items-center justify-center">
                                    <i class="fas fa-download mr-2"></i>
                                    Créer une sauvegarde maintenant
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Recent Backups -->
                    <div class="settings-section bg-white dark:bg-gray-800 rounded-xl shadow-lg p-6" data-aos="fade-up" data-aos-delay="100">
                        <h3 class="text-lg font-bold mb-6 dark:text-white flex items-center">
                            <i class="fas fa-history text-netblue-600 mr-2"></i>
                            Sauvegardes récentes
                        </h3>
                        
                        <div class="space-y-3">
                            <?php if (empty($recent_backups)): ?>
                            <div class="text-center py-8">
                                <i class="fas fa-database text-gray-400 text-3xl mb-2"></i>
                                <p class="text-gray-500 dark:text-gray-400">Aucune sauvegarde trouvée</p>
                                <p class="text-sm text-gray-400">Créez votre première sauvegarde</p>
                            </div>
                            <?php else: ?>
                                <?php foreach ($recent_backups as $backup): ?>
                                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-green-100 dark:bg-green-900 rounded-lg flex items-center justify-center mr-3">
                                            <i class="fas fa-check text-green-600 dark:text-green-400 text-sm"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-800 dark:text-white text-sm"><?php echo htmlspecialchars($backup['filename']); ?></p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                <?php echo date('d/m/Y H:i', strtotime($backup['created_at'])); ?> - 
                                                <?php echo number_format($backup['file_size'] / 1024 / 1024, 2); ?> MB
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <button onclick="downloadBackup('<?php echo $backup['id']; ?>')" 
                                                class="text-blue-600 hover:text-blue-800 dark:text-blue-400" title="Télécharger">
                                            <i class="fas fa-download"></i>
                                        </button>
                                        <button onclick="deleteBackup('<?php echo $backup['id']; ?>')" 
                                                class="text-red-600 hover:text-red-800 dark:text-red-400" title="Supprimer">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        
        <!-- Footer -->
        <footer class="bg-white dark:bg-gray-800 shadow-lg mt-8 py-4">
            <div class="px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col sm:flex-row justify-between items-center">
                    <div class="text-center sm:text-left mb-4 sm:mb-0">
                        <p class="text-gray-600 dark:text-gray-400">
                            © 2023 Netcrafter Admin Panel. Tous droits réservés.
                        </p>
                    </div>
                    <div class="flex items-center space-x-4 text-sm text-gray-500 dark:text-gray-400">
                        <span>Version 2.1.0</span>
                        <span>•</span>
                        <button onclick="checkUpdates()" class="hover:text-netblue-600 dark:hover:text-netblue-400">
                            Vérifier les mises à jour
                        </button>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <!-- AOS Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>

    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            once: true,
            disable: window.innerWidth < 768 ? true : false
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar toggle functionality
            const sidenav = document.getElementById('sidenav');
            const sidenavToggle = document.getElementById('sidenav-toggle');
            const content = document.getElementById('content');
            const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
            const overlay = document.getElementById('overlay');
            
            // Desktop sidebar toggle
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
                    localStorage.setItem('adminSidenavCollapsed', sidenav.classList.contains('collapsed'));
                }
            }
            
            // Mobile menu toggle
            function toggleMobileMenu() {
                sidenav.classList.toggle('open');
                overlay.classList.toggle('active');
                document.body.classList.toggle('overflow-hidden');
            }
            
            // Event listeners
            if (sidenavToggle) {
                sidenavToggle.addEventListener('click', toggleSidenav);
            }
            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', toggleMobileMenu);
            }
            
            // Restore sidebar state
            const savedState = localStorage.getItem('adminSidenavCollapsed');
            if (savedState === 'true' && window.innerWidth >= 768) {
                sidenav.classList.add('collapsed');
                content.classList.add('nav-collapsed');
                const icon = sidenavToggle?.querySelector('i');
                if (icon) {
                    icon.classList.remove('fa-chevron-left');
                    icon.classList.add('fa-chevron-right');
                }
            }
        });
        
        // Make toggleMobileMenu globally accessible
        function toggleMobileMenu() {
            const sidenav = document.getElementById('sidenav');
            const overlay = document.getElementById('overlay');
            
            sidenav.classList.toggle('open');
            overlay.classList.toggle('active');
            document.body.classList.toggle('overflow-hidden');
        }
        
        // Tab functionality
        function showTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(tab => tab.classList.add('hidden'));
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.remove('hidden');
            
            // Update tab buttons
            const tabButtons = document.querySelectorAll('.tab-button');
            tabButtons.forEach(button => {
                button.classList.remove('active', 'border-netblue-500', 'text-netblue-600');
                button.classList.add('border-transparent', 'text-gray-500');
            });
            
            // Activate selected tab button
            event.target.classList.add('active', 'border-netblue-500', 'text-netblue-600');
            event.target.classList.remove('border-transparent', 'text-gray-500');
        }
        
        // Toggle switch functionality
        function toggleSwitch(element, inputName) {
            element.classList.toggle('active');
            const input = document.getElementById(inputName);
            if (input) {
                input.value = element.classList.contains('active') ? '1' : '';
            }
        }
        
        // Toggle checkbox for payments
        function toggleCheckbox(element, inputName) {
            element.classList.toggle('active');
            const checkbox = document.querySelector(`input[name="${inputName}"]`);
            if (checkbox) {
                checkbox.checked = element.classList.contains('active');
            }
        }
        
        // Backup functions
        function confirmBackup() {
            return confirm('Êtes-vous sûr de vouloir créer une sauvegarde de la base de données ?');
        }
        
        function downloadBackup(backupId) {
            // Simulate download
            showNotification('Téléchargement de la sauvegarde...', 'info');
            setTimeout(() => {
                showNotification('Sauvegarde téléchargée avec succès', 'success');
            }, 2000);
        }
        
        function deleteBackup(backupId) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cette sauvegarde ?')) {
                // Simulate deletion
                showNotification('Sauvegarde supprimée', 'success');
                setTimeout(() => location.reload(), 1500);
            }
        }
        
        // Utility functions
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg text-white z-50 transform translate-x-full transition-transform duration-300 ${
                type === 'success' ? 'bg-green-500' : 
                type === 'error' ? 'bg-red-500' : 
                type === 'warning' ? 'bg-yellow-500' : 'bg-blue-500'
            }`;
            
            notification.innerHTML = `
                <div class="flex items-center">
                    <i class="fas ${
                        type === 'success' ? 'fa-check-circle' : 
                        type === 'error' ? 'fa-exclamation-circle' : 
                        type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle'
                    } mr-2"></i>
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
        
        function checkUpdates() {
            showNotification('Vérification des mises à jour...', 'info');
            // Simulate update check
            setTimeout(() => {
                showNotification('Vous utilisez la dernière version', 'success');
            }, 2000);
        }
        
        // Handle window resize
        window.addEventListener('resize', function() {
            const sidenav = document.getElementById('sidenav');
            const content = document.getElementById('content');
            const overlay = document.getElementById('overlay');
        if (window.innerWidth >= 768) {
                // Reset mobile menu state
                sidenav.classList.remove('open');
                overlay.classList.remove('active');
                document.body.classList.remove('overflow-hidden');
                
                // Apply saved collapsed state
                const savedState = localStorage.getItem('adminSidenavCollapsed');
                if (savedState === 'true') {
                    sidenav.classList.add('collapsed');
                    content.classList.add('nav-collapsed');
                } else {
                    sidenav.classList.remove('collapsed');
                    content.classList.remove('nav-collapsed');
                }
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Alt + D for dashboard
            if (e.altKey && e.key === 'd') {
                e.preventDefault();
                window.location.href = 'dashboard.php';
            }
            
            // Alt + U for users
            if (e.altKey && e.key === 'u') {
                e.preventDefault();
                window.location.href = 'users.php';
            }
            
            // Alt + F for formations
            if (e.altKey && e.key === 'f') {
                e.preventDefault();
                window.location.href = 'formations.php';
            }
            
            // Alt + S for settings
            if (e.altKey && e.key === 's') {
                e.preventDefault();
                // Already on settings page, maybe scroll to top
                window.scrollTo(0, 0);
            }
            
            // Escape to close mobile menu
            if (e.key === 'Escape') {
                const sidenav = document.getElementById('sidenav');
                const overlay = document.getElementById('overlay');
                
                if (sidenav.classList.contains('open')) {
                    sidenav.classList.remove('open');
                    overlay.classList.remove('active');
                    document.body.classList.remove('overflow-hidden');
                }
            }
        });
        
        // Auto-save form changes (optional enhancement)
        function enableAutoSave() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                const inputs = form.querySelectorAll('input, select, textarea');
                inputs.forEach(input => {
                    input.addEventListener('change', function() {
                        // Show indicator that changes need to be saved
                        const submitBtn = form.querySelector('button[type="submit"]');
                        if (submitBtn && !submitBtn.classList.contains('bg-yellow-500')) {
                            const originalText = submitBtn.innerHTML;
                            submitBtn.classList.remove('bg-netblue-600', 'hover:bg-netblue-700');
                            submitBtn.classList.add('bg-yellow-500', 'hover:bg-yellow-600');
                            submitBtn.innerHTML = '<i class="fas fa-exclamation-triangle mr-2"></i>Modifications non sauvegardées';
                            
                            // Reset after 3 seconds if no action
                            setTimeout(() => {
                                if (submitBtn.classList.contains('bg-yellow-500')) {
                                    submitBtn.classList.remove('bg-yellow-500', 'hover:bg-yellow-600');
                                    submitBtn.classList.add('bg-netblue-600', 'hover:bg-netblue-700');
                                    submitBtn.innerHTML = originalText;
                                }
                            }, 3000);
                        }
                    });
                });
            });
        }
        
        // Enable auto-save indicators
        enableAutoSave();
        
        // System health check
        function performHealthCheck() {
            showNotification('Vérification de l\'état du système...', 'info');
            
            // Simulate health check
            setTimeout(() => {
                const issues = [];
                
                // Check disk space
                if (<?php echo $stats['disk_usage']; ?> > 85) {
                    issues.push('Espace disque faible');
                }
                
                // Check database size
                if (<?php echo $stats['db_size']; ?> > 100) {
                    issues.push('Base de données volumineuse');
                }
                
                if (issues.length === 0) {
                    showNotification('Système en bonne santé', 'success');
                } else {
                    showNotification(`Attention: ${issues.join(', ')}`, 'warning');
                }
            }, 2000);
        }
        
        // Export settings
        function exportSettings() {
            const settings = {
                export_date: new Date().toISOString(),
                site_name: "<?php echo htmlspecialchars($current_settings['site_name'] ?? ''); ?>",
                site_description: "<?php echo htmlspecialchars($current_settings['site_description'] ?? ''); ?>",
                maintenance_mode: "<?php echo $current_settings['maintenance_mode'] ?? '0'; ?>",
                registration_enabled: "<?php echo $current_settings['registration_enabled'] ?? '1'; ?>",
                email_notifications: "<?php echo $current_settings['email_notifications'] ?? '1'; ?>",
                auto_approve_subscriptions: "<?php echo $current_settings['auto_approve_subscriptions'] ?? '0'; ?>",
                max_file_size: "<?php echo $current_settings['max_file_size'] ?? '50'; ?>",
                supported_video_formats: "<?php echo $current_settings['supported_video_formats'] ?? 'mp4,webm,avi'; ?>",
                payment_methods: "<?php echo $current_settings['payment_methods'] ?? 'nita,amana,zeyna,niya'; ?>"
            };
            
            const dataStr = JSON.stringify(settings, null, 2);
            const dataBlob = new Blob([dataStr], {type: 'application/json'});
            const url = URL.createObjectURL(dataBlob);
            
            const link = document.createElement('a');
            link.href = url;
            link.download = 'netcrafter_settings_' + new Date().toISOString().split('T')[0] + '.json';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
            
            showNotification('Paramètres exportés avec succès', 'success');
        }
        
        // Import settings
        function importSettings() {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = '.json';
            input.onchange = function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        try {
                            const settings = JSON.parse(e.target.result);
                            
                            if (confirm('Êtes-vous sûr de vouloir importer ces paramètres ? Cela remplacera la configuration actuelle.')) {
                                // Apply imported settings to form fields
                                Object.keys(settings).forEach(key => {
                                    const input = document.querySelector(`[name="${key}"]`);
                                    if (input) {
                                        if (input.type === 'checkbox') {
                                            input.checked = settings[key] === '1';
                                        } else {
                                            input.value = settings[key];
                                        }
                                    }
                                });
                                
                                showNotification('Paramètres importés. N\'oubliez pas de sauvegarder.', 'warning');
                            }
                        } catch (error) {
                            showNotification('Erreur lors de l\'importation: fichier invalide', 'error');
                        }
                    };
                    reader.readAsText(file);
                }
            };
            input.click();
        }
        
        // Reset to defaults
        function resetToDefaults() {
            if (confirm('Êtes-vous sûr de vouloir réinitialiser tous les paramètres aux valeurs par défaut ?')) {
                // Reset all form fields to default values
                const defaults = {
                    site_name: 'Netcrafter Formation',
                    site_description: 'Plateforme de formation professionnelle',
                    maintenance_mode: false,
                    registration_enabled: true,
                    email_notifications: true,
                    auto_approve_subscriptions: false,
                    max_file_size: '50',
                    supported_video_formats: 'mp4,webm,avi'
                };
                
                Object.keys(defaults).forEach(key => {
                    const input = document.querySelector(`[name="${key}"]`);
                    if (input) {
                        if (input.type === 'checkbox') {
                            input.checked = defaults[key];
                            // Update toggle switch
                            const toggle = input.closest('.settings-section').querySelector('.toggle-switch');
                            if (toggle) {
                                if (defaults[key]) {
                                    toggle.classList.add('active');
                                } else {
                                    toggle.classList.remove('active');
                                }
                            }
                        } else {
                            input.value = defaults[key];
                        }
                    }
                });
                
                // Reset payment methods
                const paymentMethods = ['nita', 'amana', 'zeyna', 'niya'];
                paymentMethods.forEach(method => {
                    const checkbox = document.querySelector(`input[name="${method}"]`);
                    if (checkbox) {
                        checkbox.checked = true;
                        const toggle = checkbox.closest('.settings-section').querySelector('.toggle-switch');
                        if (toggle) {
                            toggle.classList.add('active');
                        }
                    }
                });
                
                showNotification('Paramètres réinitialisés aux valeurs par défaut. N\'oubliez pas de sauvegarder.', 'warning');
            }
        }
        
        // Add export/import/reset buttons to the page
        function addUtilityButtons() {
            const generalTab = document.getElementById('general-tab');
            if (generalTab) {
                const form = generalTab.querySelector('form');
                const submitButton = form.querySelector('button[type="submit"]');
                
                if (submitButton) {
                    const buttonContainer = submitButton.parentElement;
                    buttonContainer.className = 'flex flex-wrap justify-end gap-3';
                    
                    // Create utility buttons
                    const exportBtn = document.createElement('button');
                    exportBtn.type = 'button';
                    exportBtn.onclick = exportSettings;
                    exportBtn.className = 'bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors text-sm';
                    exportBtn.innerHTML = '<i class="fas fa-download mr-1"></i>Exporter';
                    
                    const importBtn = document.createElement('button');
                    importBtn.type = 'button';
                    importBtn.onclick = importSettings;
                    importBtn.className = 'bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors text-sm';
                    importBtn.innerHTML = '<i class="fas fa-upload mr-1"></i>Importer';
                    
                    const resetBtn = document.createElement('button');
                    resetBtn.type = 'button';
                    resetBtn.onclick = resetToDefaults;
                    resetBtn.className = 'bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition-colors text-sm';
                    resetBtn.innerHTML = '<i class="fas fa-undo mr-1"></i>Réinitialiser';
                    
                    // Insert buttons before submit button
                    buttonContainer.insertBefore(exportBtn, submitButton);
                    buttonContainer.insertBefore(importBtn, submitButton);
                    buttonContainer.insertBefore(resetBtn, submitButton);
                }
            }
        }
        
        // Add health check button to system tab
        function addHealthCheckButton() {
            const systemTab = document.getElementById('system-tab');
            if (systemTab) {
                const systemInfo = systemTab.querySelector('.settings-section');
                if (systemInfo) {
                    const healthBtn = document.createElement('button');
                    healthBtn.type = 'button';
                    healthBtn.onclick = performHealthCheck;
                    healthBtn.className = 'w-full mt-4 bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg transition-colors';
                    healthBtn.innerHTML = '<i class="fas fa-heartbeat mr-2"></i>Vérifier l\'état du système';
                    
                    systemInfo.appendChild(healthBtn);
                }
            }
        }
        
        // Initialize utility features
        setTimeout(() => {
            addUtilityButtons();
            addHealthCheckButton();
        }, 500);
        
        // Dark mode toggle (if needed)
        function toggleDarkMode() {
            document.documentElement.classList.toggle('dark');
            const isDark = document.documentElement.classList.contains('dark');
            localStorage.setItem('darkMode', isDark);
            showNotification(`Mode ${isDark ? 'sombre' : 'clair'} activé`, 'info');
        }
        
        // Load dark mode preference
        if (localStorage.getItem('darkMode') === 'true') {
            document.documentElement.classList.add('dark');
        }
        
        // Performance monitoring
        function monitorPerformance() {
            const startTime = performance.now();
            
            window.addEventListener('load', function() {
                const loadTime = performance.now() - startTime;
                if (loadTime > 3000) {
                    console.warn('Page load time is slow:', loadTime + 'ms');
                }
            });
        }
        
        monitorPerformance();
        
        // Cleanup function for memory management
        window.addEventListener('beforeunload', function() {
            // Clean up any intervals or listeners if needed
            console.log('Settings page cleanup');
        });
    </script>
</body>
</html>