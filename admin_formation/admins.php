<?php
// admin/admins.php
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

// Récupérer les informations de l'admin connecté
$admin_id = $_SESSION['admin_id'];
$admin_query = "SELECT * FROM admins WHERE id = ?";
$stmt = $conn->prepare($admin_query);
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$admin_result = $stmt->get_result();
$current_admin = $admin_result->fetch_assoc();

// Vérifier les permissions (seuls super_admin peuvent gérer les admins)
if ($current_admin['role'] !== 'super_admin') {
    header("Location: dashboard.php");
    exit;
}

// Messages
$message = '';
$message_type = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_admin':
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $firstname = trim($_POST['firstname'] ?? '');
            $lastname = trim($_POST['lastname'] ?? '');
            $role = $_POST['role'] ?? 'admin';
            $permissions = $_POST['permissions'] ?? [];
            
            // Validation
            if (empty($username) || empty($email) || empty($password) || empty($firstname) || empty($lastname)) {
                $message = "Tous les champs sont obligatoires";
                $message_type = "error";
            } else {
                // Vérifier si l'username ou email existe déjà
                $check_query = "SELECT id FROM admins WHERE username = ? OR email = ?";
                $stmt = $conn->prepare($check_query);
                $stmt->bind_param("ss", $username, $email);
                $stmt->execute();
                
                if ($stmt->get_result()->num_rows > 0) {
                    $message = "Ce nom d'utilisateur ou email existe déjà";
                    $message_type = "error";
                } else {
                    // Hasher le mot de passe
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Préparer les permissions JSON
                    $permissions_json = json_encode($permissions);
                    
                    // Insérer le nouvel admin
                    $insert_query = "INSERT INTO admins (username, email, password, firstname, lastname, role, permissions, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)";
                    $stmt = $conn->prepare($insert_query);
                    $stmt->bind_param("sssssss", $username, $email, $hashed_password, $firstname, $lastname, $role, $permissions_json);
                    
                    if ($stmt->execute()) {
                        // Enregistrer l'action dans les logs
                        $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'create_admin', ?)";
                        $log_stmt = $conn->prepare($log_query);
                        $log_description = "Création de l'administrateur: $firstname $lastname ($username)";
                        $log_stmt->bind_param("is", $admin_id, $log_description);
                        $log_stmt->execute();
                        
                        $message = "Administrateur créé avec succès";
                        $message_type = "success";
                    } else {
                        $message = "Erreur lors de la création de l'administrateur";
                        $message_type = "error";
                    }
                }
            }
            break;
            
        case 'update_admin':
            $update_admin_id = $_POST['admin_id'] ?? 0;
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $firstname = trim($_POST['firstname'] ?? '');
            $lastname = trim($_POST['lastname'] ?? '');
            $role = $_POST['role'] ?? 'admin';
            $permissions = $_POST['permissions'] ?? [];
            $new_password = $_POST['new_password'] ?? '';
            
            // Validation
            if (empty($username) || empty($email) || empty($firstname) || empty($lastname)) {
                $message = "Tous les champs sont obligatoires";
                $message_type = "error";
            } else {
                // Vérifier si l'username ou email existe déjà (sauf pour cet admin)
                $check_query = "SELECT id FROM admins WHERE (username = ? OR email = ?) AND id != ?";
                $stmt = $conn->prepare($check_query);
                $stmt->bind_param("ssi", $username, $email, $update_admin_id);
                $stmt->execute();
                
                if ($stmt->get_result()->num_rows > 0) {
                    $message = "Ce nom d'utilisateur ou email est déjà utilisé par un autre administrateur";
                    $message_type = "error";
                } else {
                    // Préparer les permissions JSON
                    $permissions_json = json_encode($permissions);
                    
                    // Préparer la requête de mise à jour
                    if (!empty($new_password)) {
                        // Avec nouveau mot de passe
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $update_query = "UPDATE admins SET username = ?, email = ?, password = ?, firstname = ?, lastname = ?, role = ?, permissions = ? WHERE id = ?";
                        $stmt = $conn->prepare($update_query);
                        $stmt->bind_param("sssssssi", $username, $email, $hashed_password, $firstname, $lastname, $role, $permissions_json, $update_admin_id);
                    } else {
                        // Sans changer le mot de passe
                        $update_query = "UPDATE admins SET username = ?, email = ?, firstname = ?, lastname = ?, role = ?, permissions = ? WHERE id = ?";
                        $stmt = $conn->prepare($update_query);
                        $stmt->bind_param("ssssssi", $username, $email, $firstname, $lastname, $role, $permissions_json, $update_admin_id);
                    }
                    
                    if ($stmt->execute()) {
                        // Enregistrer l'action dans les logs
                        $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'update_admin', ?)";
                        $log_stmt = $conn->prepare($log_query);
                        $log_description = "Modification de l'administrateur: $firstname $lastname (ID: $update_admin_id)";
                        $log_stmt->bind_param("is", $admin_id, $log_description);
                        $log_stmt->execute();
                        
                        $message = "Administrateur mis à jour avec succès";
                        $message_type = "success";
                    } else {
                        $message = "Erreur lors de la mise à jour";
                        $message_type = "error";
                    }
                }
            }
            break;
            
        case 'toggle_status':
            $toggle_admin_id = $_POST['admin_id'] ?? 0;
            $current_status = $_POST['current_status'] ?? 0;
            $new_status = $current_status ? 0 : 1;
            
            // Ne pas permettre de désactiver le dernier super_admin
            if (!$new_status) {
                $check_super_admin = "SELECT COUNT(*) as count FROM admins WHERE role = 'super_admin' AND is_active = 1 AND id != ?";
                $stmt = $conn->prepare($check_super_admin);
                $stmt->bind_param("i", $toggle_admin_id);
                $stmt->execute();
                $super_admin_count = $stmt->get_result()->fetch_assoc()['count'];
                
                $admin_role_query = "SELECT role FROM admins WHERE id = ?";
                $stmt = $conn->prepare($admin_role_query);
                $stmt->bind_param("i", $toggle_admin_id);
                $stmt->execute();
                $admin_role = $stmt->get_result()->fetch_assoc()['role'];
                
                if ($admin_role === 'super_admin' && $super_admin_count == 0) {
                    $message = "Impossible de désactiver le dernier super administrateur";
                    $message_type = "error";
                    break;
                }
            }
            
            $update_query = "UPDATE admins SET is_active = ? WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("ii", $new_status, $toggle_admin_id);
            
            if ($stmt->execute()) {
                $status_text = $new_status ? 'activé' : 'désactivé';
                $message = "Administrateur $status_text avec succès";
                $message_type = "success";
                
                // Log
                $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'toggle_admin_status', ?)";
                $log_stmt = $conn->prepare($log_query);
                $log_description = "Changement de statut d'un administrateur (ID: $toggle_admin_id) - $status_text";
                $log_stmt->bind_param("is", $admin_id, $log_description);
                $log_stmt->execute();
            }
            break;
            
        case 'delete_admin':
            $delete_admin_id = $_POST['admin_id'] ?? 0;
            
            // Ne pas permettre de supprimer son propre compte
            if ($delete_admin_id == $admin_id) {
                $message = "Vous ne pouvez pas supprimer votre propre compte";
                $message_type = "error";
                break;
            }
            
            // Ne pas permettre de supprimer le dernier super_admin
            $check_super_admin = "SELECT COUNT(*) as count FROM admins WHERE role = 'super_admin' AND id != ?";
            $stmt = $conn->prepare($check_super_admin);
            $stmt->bind_param("i", $delete_admin_id);
            $stmt->execute();
            $super_admin_count = $stmt->get_result()->fetch_assoc()['count'];
            
            $admin_role_query = "SELECT role FROM admins WHERE id = ?";
            $stmt = $conn->prepare($admin_role_query);
            $stmt->bind_param("i", $delete_admin_id);
            $stmt->execute();
            $admin_role = $stmt->get_result()->fetch_assoc()['role'];
            
            if ($admin_role === 'super_admin' && $super_admin_count == 0) {
                $message = "Impossible de supprimer le dernier super administrateur";
                $message_type = "error";
                break;
            }
            
            // Supprimer l'admin
            $delete_query = "DELETE FROM admins WHERE id = ?";
            $stmt = $conn->prepare($delete_query);
            $stmt->bind_param("i", $delete_admin_id);
            
            if ($stmt->execute()) {
                $message = "Administrateur supprimé avec succès";
                $message_type = "success";
                
                // Log
                $log_query = "INSERT INTO admin_logs (admin_id, action, description) VALUES (?, 'delete_admin', ?)";
                $log_stmt = $conn->prepare($log_query);
                $log_description = "Suppression d'un administrateur (ID: $delete_admin_id)";
                $log_stmt->bind_param("is", $admin_id, $log_description);
                $log_stmt->execute();
            }
            break;
    }
}

// Récupérer la liste des admins
$admins_query = "SELECT * FROM admins ORDER BY created_at DESC";
$admins_result = $conn->query($admins_query);

// Statistiques
$stats_query = "
    SELECT 
        COUNT(*) as total_admins,
        COUNT(CASE WHEN role = 'super_admin' THEN 1 END) as super_admins,
        COUNT(CASE WHEN role = 'admin' THEN 1 END) as regular_admins,
        COUNT(CASE WHEN role = 'moderator' THEN 1 END) as moderators,
        COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_admins,
        COUNT(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as recent_logins
    FROM admins
";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Permissions par défaut pour les différents rôles
$default_permissions = [
    'super_admin' => [
        'users' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => true],
        'formations' => ['view' => true, 'create' => true, 'edit' => true, 'delete' => true],
        'subscriptions' => ['view' => true, 'approve' => true, 'reject' => true],
        'certificates' => ['view' => true, 'create' => true, 'revoke' => true],
        'settings' => ['view' => true, 'edit' => true],
        'logs' => ['view' => true]
    ],
    'admin' => [
        'users' => ['view' => true, 'edit' => true],
        'formations' => ['view' => true, 'create' => true, 'edit' => true],
        'subscriptions' => ['view' => true, 'approve' => true],
        'certificates' => ['view' => true, 'create' => true]
    ],
    'moderator' => [
        'users' => ['view' => true],
        'formations' => ['view' => true],
        'subscriptions' => ['view' => true],
        'certificates' => ['view' => true]
    ]
];

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Administrateurs - Netcrafter Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Side Navigation */
        .sidenav {
            transition: all 0.3s ease;
            z-index: 1000;
        }
        
        .sidenav.collapsed {
            width: 70px;
        }
        
        .sidenav.collapsed .nav-text {
            opacity: 0;
            visibility: hidden;
        }
        
        .content-area {
            transition: margin-left 0.3s ease;
            margin-left: 280px;
        }
        
        .content-area.nav-collapsed {
            margin-left: 70px;
        }
        
        @media (max-width: 768px) {
            .sidenav {
                position: fixed;
                left: -280px;
                width: 280px;
                height: 100vh;
                z-index: 1000;
            }
            
            .sidenav.mobile-open {
                left: 0;
            }
            
            .content-area {
                margin-left: 0;
            }
            
            .mobile-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 999;
            }
            
            .mobile-overlay.active {
                display: block;
            }
        }

        /* Modal */
        .modal {
            transition: opacity 0.3s ease;
        }
        
        .modal.hidden {
            opacity: 0;
            pointer-events: none;
        }
        
        .modal:not(.hidden) {
            opacity: 1;
            pointer-events: auto;
        }

        /* Permission toggles */
        .permission-toggle {
            position: relative;
            width: 44px;
            height: 24px;
            background-color: #e5e7eb;
            border-radius: 12px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .permission-toggle.active {
            background-color: #3b82f6;
        }

        .permission-toggle::before {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background-color: white;
            top: 2px;
            left: 2px;
            transition: transform 0.3s;
        }

        .permission-toggle.active::before {
            transform: translateX(20px);
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
<body class="bg-gray-50 text-gray-800">
    <!-- Mobile Overlay -->
    <div id="mobileOverlay" class="mobile-overlay" onclick="closeMobileMenu()"></div>
    
    <!-- Side Navigation -->
    <aside id="sidenav" class="sidenav fixed h-full bg-white shadow-lg">
        <!-- Logo and collapse button -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
            <div class="flex items-center">
                <img src="../image/logo-n.png" alt="Netcrafter Logo" class="h-8 mr-2">
                <span class="text-lg font-bold text-netblue-600 nav-text">ADMIN</span>
            </div>
            <button id="sidenavToggle" class="text-gray-500 hover:text-gray-700 focus:outline-none hidden md:block">
                <i class="fas fa-chevron-left"></i>
            </button>
        </div>
        
        <!-- Admin info -->
        <div class="px-4 py-4 border-b border-gray-200">
            <div class="flex items-center">
                <div class="w-10 h-10 rounded-full bg-netblue-600 flex items-center justify-center text-white font-bold text-lg">
                    <?php echo strtoupper(substr($current_admin['firstname'], 0, 1) . substr($current_admin['lastname'], 0, 1)); ?>
                </div>
                <div class="ml-3 nav-text">
                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($current_admin['firstname'] . ' ' . $current_admin['lastname']); ?></p>
                    <p class="text-sm text-gray-500"><?php echo ucfirst($current_admin['role']); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Navigation Menu -->
        <nav class="mt-4 px-2">
            <ul class="space-y-1">
                <li>
                    <a href="dashboard.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-tachometer-alt w-6 text-center"></i>
                        <span class="ml-2 nav-text">Tableau de bord</span>
                    </a>
                </li>
                <li>
                    <a href="users.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-users w-6 text-center"></i>
                        <span class="ml-2 nav-text">Utilisateurs</span>
                    </a>
                </li>
                <li>
                    <a href="formations.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-graduation-cap w-6 text-center"></i>
                        <span class="ml-2 nav-text">Formations</span>
                    </a>
                </li>
                <li>
                    <a href="subscriptions.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-credit-card w-6 text-center"></i>
                        <span class="ml-2 nav-text">Abonnements</span>
                    </a>
                </li>
                <li>
                    <a href="quiz.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-question-circle w-6 text-center"></i>
                        <span class="ml-2 nav-text">Quizz</span>
                    </a>
                </li>
                <li>
                    <a href="certificates.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-certificate w-6 text-center"></i>
                        <span class="ml-2 nav-text">Certificats</span>
                    </a>
                </li>
                <li>
                    <a href="forum.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-comments w-6 text-center"></i>
                        <span class="ml-2 nav-text">Forum</span>
                    </a>
                </li>
                <li>
                    <a href="reports.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-chart-bar w-6 text-center"></i>
                        <span class="ml-2 nav-text">Rapports</span>
                    </a>
                </li>
                <li class="pt-2 mt-2 border-t border-gray-200">
                    <a href="settings.php" class="flex items-center px-3 py-2 text-base rounded-lg text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-cog w-6 text-center"></i>
                        <span class="ml-2 nav-text">Paramètres</span>
                    </a>
                </li>
                <li>
                    <a href="admins.php" class="flex items-center px-3 py-2 text-base rounded-lg bg-netblue-100 text-netblue-800">
                        <i class="fas fa-shield-alt w-6 text-center"></i>
                        <span class="ml-2 nav-text">Administrateurs</span>
                    </a>
                </li>
            </ul>
        </nav>
        
        <!-- Logout -->
        <div class="absolute bottom-0 left-0 right-0 border-t border-gray-200 p-4 bg-white">
            <a href="logout.php" class="flex items-center justify-center px-3 py-2 rounded-lg text-red-600 hover:bg-red-50">
                <i class="fas fa-sign-out-alt w-6 text-center"></i>
                <span class="ml-2 nav-text">Déconnexion</span>
            </a>
        </div>
    </aside>
    
    <!-- Main Content -->
    <div id="content" class="content-area min-h-screen">
        <!-- Top Bar -->
        <header class="bg-white shadow-sm sticky top-0 z-20">
            <div class="flex items-center justify-between px-4 py-3">
                <!-- Mobile Menu Toggle -->
                <button id="mobileMenuToggle" class="md:hidden text-gray-700 focus:outline-none">
                    <i class="fas fa-bars text-2xl"></i>
                </button>
                
                <!-- Page Title -->
                <h1 class="text-xl font-bold text-gray-800">Gestion des Administrateurs</h1>
                
                <!-- Add Admin Button -->
                <button onclick="openAdminModal()" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded-lg flex items-center">
                    <i class="fas fa-plus mr-2"></i>
                    Nouvel Admin
                </button>
            </div>
        </header>
        
        <!-- Main Content Area -->
        <main class="p-6">
            <!-- Messages -->
            <?php if ($message): ?>
            <div class="mb-6">
                <div class="alert bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-100 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-400 text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 px-4 py-3 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?> mr-2"></i>
                        <span><?php echo htmlspecialchars($message); ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-blue-100 rounded-full">
                            <i class="fas fa-users text-blue-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Total Admins</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['total_admins']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-purple-100 rounded-full">
                            <i class="fas fa-crown text-purple-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Super Admins</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['super_admins']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-green-100 rounded-full">
                            <i class="fas fa-user-check text-green-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Admins Actifs</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['active_admins']); ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center">
                        <div class="p-3 bg-yellow-100 rounded-full">
                            <i class="fas fa-clock text-yellow-600 text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Connexions Récentes</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($stats['recent_logins']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Admins Table -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Liste des Administrateurs</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Administrateur</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rôle</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dernière Connexion</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($admin = $admins_result->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 rounded-full bg-netblue-600 flex items-center justify-center text-white font-bold text-sm">
                                            <?php echo strtoupper(substr($admin['firstname'], 0, 1) . substr($admin['lastname'], 0, 1)); ?>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?php echo htmlspecialchars($admin['firstname'] . ' ' . $admin['lastname']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo htmlspecialchars($admin['username']); ?> • <?php echo htmlspecialchars($admin['email']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full <?php 
                                        echo $admin['role'] === 'super_admin' ? 'bg-purple-100 text-purple-800' : 
                                            ($admin['role'] === 'admin' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'); 
                                    ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $admin['role'])); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $admin['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                        <?php echo $admin['is_active'] ? 'Actif' : 'Inactif'; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php 
                                    if ($admin['last_login']) {
                                        echo date('d/m/Y H:i', strtotime($admin['last_login']));
                                    } else {
                                        echo 'Jamais';
                                    }
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center space-x-2">
                                        <!-- Edit Button -->
                                        <button onclick="editAdmin(<?php echo htmlspecialchars(json_encode($admin)); ?>)" 
                                                class="text-indigo-600 hover:text-indigo-900" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        
                                        <!-- Toggle Status -->
                                        <?php if ($admin['id'] != $current_admin['id']): ?>
                                        <form method="POST" class="inline" onsubmit="return confirm('Êtes-vous sûr de vouloir changer le statut de cet administrateur ?')">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                            <input type="hidden" name="current_status" value="<?php echo $admin['is_active']; ?>">
                                            <button type="submit" class="<?php echo $admin['is_active'] ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900'; ?>" 
                                                    title="<?php echo $admin['is_active'] ? 'Désactiver' : 'Activer'; ?>">
                                                <i class="fas <?php echo $admin['is_active'] ? 'fa-ban' : 'fa-check'; ?>"></i>
                                            </button>
                                        </form>
                                        
                                        <!-- Delete Button -->
                                        <form method="POST" class="inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet administrateur ? Cette action est irréversible.')">
                                            <input type="hidden" name="action" value="delete_admin">
                                            <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-900" title="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <span class="text-gray-400" title="Vous ne pouvez pas modifier votre propre compte">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
        
        <!-- Footer -->
        <footer class="bg-white shadow-lg py-4 mt-8">
            <div class="px-6">
                <div class="flex flex-col sm:flex-row justify-between items-center">
                    <div class="text-center sm:text-left mb-4 sm:mb-0">
                        <p class="text-gray-600">© 2023 Netcrafter Admin Panel. Tous droits réservés.</p>
                    </div>
                    <div class="flex items-center space-x-4 text-sm text-gray-500">
                        <span>Version 2.1.0</span>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <!-- Admin Modal -->
    <div id="adminModal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900" id="modalTitle">Nouvel Administrateur</h3>
                    <button onclick="closeAdminModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                
                <form id="adminForm" method="POST" class="space-y-6">
                    <input type="hidden" name="action" id="formAction" value="create_admin">
                    <input type="hidden" name="admin_id" id="adminId" value="">
                    
                    <!-- Basic Info -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Prénom *</label>
                            <input type="text" name="firstname" id="firstname" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-netblue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nom *</label>
                            <input type="text" name="lastname" id="lastname" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-netblue-500 focus:border-transparent">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nom d'utilisateur *</label>
                            <input type="text" name="username" id="username" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-netblue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                            <input type="email" name="email" id="email" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-netblue-500 focus:border-transparent">
                        </div>
                    </div>
                    
                    <!-- Password -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2" id="passwordLabel">Mot de passe *</label>
                            <input type="password" name="password" id="password"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-netblue-500 focus:border-transparent">
                            <input type="password" name="new_password" id="newPassword" style="display: none;"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-netblue-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Rôle *</label>
                            <select name="role" id="role" required onchange="updatePermissions()"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-netblue-500 focus:border-transparent">
                                <option value="moderator">Modérateur</option>
                                <option value="admin">Administrateur</option>
                                <option value="super_admin">Super Administrateur</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Permissions -->
                    <div>
                        <h4 class="text-md font-semibold text-gray-900 mb-4">Permissions</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Users Permissions -->
                            <div class="space-y-3">
                                <h5 class="font-medium text-gray-700">Utilisateurs</h5>
                                <div class="space-y-2">
                                    <label class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600">Voir</span>
                                        <div class="permission-toggle" onclick="togglePermission(this, 'permissions[users][view]')">
                                            <input type="hidden" name="permissions[users][view]" value="">
                                        </div>
                                    </label>
                                    <label class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600">Créer</span>
                                        <div class="permission-toggle" onclick="togglePermission(this, 'permissions[users][create]')">
                                            <input type="hidden" name="permissions[users][create]" value="">
                                        </div>
                                    </label>
                                    <label class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600">Modifier</span>
                                        <div class="permission-toggle" onclick="togglePermission(this, 'permissions[users][edit]')">
                                            <input type="hidden" name="permissions[users][edit]" value="">
                                        </div>
                                    </label>
                                    <label class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600">Supprimer</span>
                                        <div class="permission-toggle" onclick="togglePermission(this, 'permissions[users][delete]')">
                                            <input type="hidden" name="permissions[users][delete]" value="">
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Formations Permissions -->
                            <div class="space-y-3">
                                <h5 class="font-medium text-gray-700">Formations</h5>
                                <div class="space-y-2">
                                    <label class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600">Voir</span>
                                        <div class="permission-toggle" onclick="togglePermission(this, 'permissions[formations][view]')">
                                            <input type="hidden" name="permissions[formations][view]" value="">
                                        </div>
                                    </label>
                                    <label class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600">Créer</span>
                                        <div class="permission-toggle" onclick="togglePermission(this, 'permissions[formations][create]')">
                                            <input type="hidden" name="permissions[formations][create]" value="">
                                        </div>
                                    </label>
                                    <label class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600">Modifier</span>
                                        <div class="permission-toggle" onclick="togglePermission(this, 'permissions[formations][edit]')">
                                            <input type="hidden" name="permissions[formations][edit]" value="">
                                        </div>
                                    </label>
                                    <label class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600">Supprimer</span>
                                        <div class="permission-toggle" onclick="togglePermission(this, 'permissions[formations][delete]')">
                                            <input type="hidden" name="permissions[formations][delete]" value="">
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Subscriptions Permissions -->
                            <div class="space-y-3">
                                <h5 class="font-medium text-gray-700">Abonnements</h5>
                                <div class="space-y-2">
                                    <label class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600">Voir</span>
                                        <div class="permission-toggle" onclick="togglePermission(this, 'permissions[subscriptions][view]')">
                                            <input type="hidden" name="permissions[subscriptions][view]" value="">
                                        </div>
                                    </label>
                                    <label class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600">Approuver</span>
                                        <div class="permission-toggle" onclick="togglePermission(this, 'permissions[subscriptions][approve]')">
                                            <input type="hidden" name="permissions[subscriptions][approve]" value="">
                                        </div>
                                    </label>
                                    <label class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600">Rejeter</span>
                                        <div class="permission-toggle" onclick="togglePermission(this, 'permissions[subscriptions][reject]')">
                                            <input type="hidden" name="permissions[subscriptions][reject]" value="">
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Other Permissions -->
                            <div class="space-y-3">
                                <h5 class="font-medium text-gray-700">Autres</h5>
                                <div class="space-y-2">
                                    <label class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600">Certificats</span>
                                        <div class="permission-toggle" onclick="togglePermission(this, 'permissions[certificates][view]')">
                                            <input type="hidden" name="permissions[certificates][view]" value="">
                                        </div>
                                    </label>
                                    <label class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600">Paramètres</span>
                                        <div class="permission-toggle" onclick="togglePermission(this, 'permissions[settings][view]')">
                                            <input type="hidden" name="permissions[settings][view]" value="">
                                        </div>
                                    </label>
                                    <label class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600">Logs</span>
                                        <div class="permission-toggle" onclick="togglePermission(this, 'permissions[logs][view]')">
                                            <input type="hidden" name="permissions[logs][view]" value="">
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Buttons -->
                    <div class="flex justify-end space-x-4 pt-6 border-t">
                        <button type="button" onclick="closeAdminModal()" 
                                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                            Annuler
                        </button>
                        <button type="submit" 
                                class="px-6 py-2 bg-netblue-600 hover:bg-netblue-700 text-white rounded-lg">
                            <span id="submitText">Créer l'Administrateur</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Default permissions for each role
        const defaultPermissions = <?php echo json_encode($default_permissions); ?>;
        
        // Sidebar functionality
        document.addEventListener('DOMContentLoaded', function() {
            const sidenav = document.getElementById('sidenav');
            const sidenavToggle = document.getElementById('sidenavToggle');
            const content = document.getElementById('content');
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const mobileOverlay = document.getElementById('mobileOverlay');
            
            // Desktop sidebar toggle
            if (sidenavToggle) {
                sidenavToggle.addEventListener('click', function() {
                    sidenav.classList.toggle('collapsed');
                    content.classList.toggle('nav-collapsed');
                    
                    // Update icon
                    const icon = sidenavToggle.querySelector('i');
                    if (sidenav.classList.contains('collapsed')) {
                        icon.classList.remove('fa-chevron-left');
                        icon.classList.add('fa-chevron-right');
                    } else {
                        icon.classList.remove('fa-chevron-right');
                        icon.classList.add('fa-chevron-left');
                    }
                    
                    localStorage.setItem('sidebarCollapsed', sidenav.classList.contains('collapsed'));
                });
            }
            
            // Mobile menu toggle
            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', openMobileMenu);
            }
            
            // Restore sidebar state
            if (window.innerWidth >= 768) {
                const savedState = localStorage.getItem('sidebarCollapsed');
                if (savedState === 'true') {
                    sidenav.classList.add('collapsed');
                    content.classList.add('nav-collapsed');
                    const icon = sidenavToggle?.querySelector('i');
                    if (icon) {
                        icon.classList.remove('fa-chevron-left');
                        icon.classList.add('fa-chevron-right');
                    }
                }
            }
        });
        
        function openMobileMenu() {
            const sidenav = document.getElementById('sidenav');
            const mobileOverlay = document.getElementById('mobileOverlay');
            
            sidenav.classList.add('mobile-open');
            mobileOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeMobileMenu() {
            const sidenav = document.getElementById('sidenav');
            const mobileOverlay = document.getElementById('mobileOverlay');
            
            sidenav.classList.remove('mobile-open');
            mobileOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }
        
        // Modal functions
        function openAdminModal() {
            document.getElementById('adminModal').classList.remove('hidden');
            document.getElementById('modalTitle').textContent = 'Nouvel Administrateur';
            document.getElementById('formAction').value = 'create_admin';
            document.getElementById('submitText').textContent = 'Créer l\'Administrateur';
            document.getElementById('passwordLabel').textContent = 'Mot de passe *';
            document.getElementById('password').style.display = 'block';
            document.getElementById('password').required = true;
            document.getElementById('newPassword').style.display = 'none';
            document.getElementById('adminForm').reset();
            clearPermissions();
            updatePermissions();
        }
        
        function closeAdminModal() {
            document.getElementById('adminModal').classList.add('hidden');
        }
        
        function editAdmin(admin) {
            document.getElementById('adminModal').classList.remove('hidden');
            document.getElementById('modalTitle').textContent = 'Modifier l\'Administrateur';
            document.getElementById('formAction').value = 'update_admin';
            document.getElementById('submitText').textContent = 'Mettre à Jour';
            document.getElementById('passwordLabel').textContent = 'Nouveau mot de passe (optionnel)';
            document.getElementById('password').style.display = 'none';
            document.getElementById('password').required = false;
            document.getElementById('newPassword').style.display = 'block';
            
            // Fill form with admin data
            document.getElementById('adminId').value = admin.id;
            document.getElementById('firstname').value = admin.firstname;
            document.getElementById('lastname').value = admin.lastname;
            document.getElementById('username').value = admin.username;
            document.getElementById('email').value = admin.email;
            document.getElementById('role').value = admin.role;
            
            // Set permissions
            clearPermissions();
            if (admin.permissions) {
                try {
                    const permissions = JSON.parse(admin.permissions);
                    setPermissions(permissions);
                } catch (e) {
                    console.error('Error parsing permissions:', e);
                    updatePermissions();
                }
            } else {
                updatePermissions();
            }
        }
        
        // Permission functions
        function togglePermission(toggle, inputName) {
            toggle.classList.toggle('active');
            const input = toggle.querySelector('input[type="hidden"]');
            input.value = toggle.classList.contains('active') ? 'true' : '';
        }
        
        function updatePermissions() {
            const role = document.getElementById('role').value;
            const permissions = defaultPermissions[role] || {};
            
            clearPermissions();
            setPermissions(permissions);
        }
        
        function clearPermissions() {
            const toggles = document.querySelectorAll('.permission-toggle');
            toggles.forEach(toggle => {
                toggle.classList.remove('active');
                const input = toggle.querySelector('input[type="hidden"]');
                input.value = '';
            });
        }
        
        function setPermissions(permissions) {
            Object.keys(permissions).forEach(section => {
                Object.keys(permissions[section]).forEach(action => {
                    if (permissions[section][action]) {
                        const input = document.querySelector(`input[name="permissions[${section}][${action}]"]`);
                        if (input) {
                            input.value = 'true';
                            const toggle = input.parentElement;
                            toggle.classList.add('active');
                        }
                    }
                });
            });
        }
        
        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768) {
                closeMobileMenu();
                
                const sidenav = document.getElementById('sidenav');
                const content = document.getElementById('content');
                const savedState = localStorage.getItem('sidebarCollapsed');
                
                if (savedState === 'true') {
                    sidenav.classList.add('collapsed');
                    content.classList.add('nav-collapsed');
                } else {
                    sidenav.classList.remove('collapsed');
                    content.classList.remove('nav-collapsed');
                }
            }
        });
        
        // Close modal when clicking outside
        document.getElementById('adminModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAdminModal();
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAdminModal();
                closeMobileMenu();
            }
        });
        
        // Form validation
        document.getElementById('adminForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password');
            const newPassword = document.getElementById('newPassword');
            const action = document.getElementById('formAction').value;
            
            if (action === 'create_admin' && !password.value.trim()) {
                e.preventDefault();
                alert('Le mot de passe est obligatoire');
                return;
            }
            
            if (action === 'update_admin' && newPassword.value && newPassword.value.length < 6) {
                e.preventDefault();
                alert('Le nouveau mot de passe doit contenir au moins 6 caractères');
                return;
            }
        });
    </script>
</body>
</html>