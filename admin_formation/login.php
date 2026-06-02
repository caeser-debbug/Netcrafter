<?php
// admin/login.php
session_start();

require_once __DIR__ . '/db.php';

// Connexion à la base de données
$conn = new mysqli($servername, $username, $password, $dbname);

// Vérification de la connexion
if ($conn->connect_error) {
    die("Échec de la connexion: " . $conn->connect_error);
}
$conn->set_charset("utf8");

// Rediriger si déjà connecté
if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error_message = '';
$success_message = '';

// Traitement du formulaire de connexion
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username_or_email = $conn->real_escape_string($_POST['username']);
    $password = $_POST['password'];
    $remember_me = isset($_POST['remember_me']);
    
    // Requête pour trouver l'admin
    $query = "SELECT * FROM admins WHERE (username = ? OR email = ?) AND is_active = 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $username_or_email, $username_or_email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $admin = $result->fetch_assoc();
        
        // Vérifier le mot de passe
        if (password_verify($password, $admin['password'])) {
            // Connexion réussie
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['admin_role'] = $admin['role'];
            $_SESSION['admin_name'] = $admin['firstname'] . ' ' . $admin['lastname'];
            
            // Mettre à jour la dernière connexion
            $update_query = "UPDATE admins SET last_login = NOW() WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("i", $admin['id']);
            $update_stmt->execute();
            
            // Enregistrer l'activité
            $log_query = "INSERT INTO admin_logs (admin_id, action, description, ip_address, user_agent) VALUES (?, 'login', 'Connexion administrateur', ?, ?)";
            $log_stmt = $conn->prepare($log_query);
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            $log_stmt->bind_param("iss", $admin['id'], $ip_address, $user_agent);
            $log_stmt->execute();
            
            // Gérer "Se souvenir de moi"
            if ($remember_me) {
                $token = bin2hex(random_bytes(32));
                $token_hash = password_hash($token, PASSWORD_DEFAULT);
                $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                
                // Stocker le token dans la base
                $token_query = "INSERT INTO auth_tokens (admin_id, token_hash, expiry_date) VALUES (?, ?, ?)";
                $token_stmt = $conn->prepare($token_query);
                $token_stmt->bind_param("iss", $admin['id'], $token_hash, $expires);
                $token_stmt->execute();
                
                // Définir le cookie
                setcookie('admin_remember_token', $token, time() + (30 * 24 * 60 * 60), '/admin/', '', true, true);
            }
            
            // Redirection
            $redirect_to = isset($_GET['redirect']) ? $_GET['redirect'] : 'dashboard.php';
            header("Location: " . $redirect_to);
            exit;
        } else {
            $error_message = "Identifiants incorrects.";
        }
    } else {
        $error_message = "Identifiants incorrects.";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="fr" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Admin - Netcrafter</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom styles -->
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .login-card {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
        
        .floating-shapes {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: -1;
        }
        
        .shape {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            animation: float 6s ease-in-out infinite;
        }
        
        .shape:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 10%;
            left: 20%;
            animation-delay: 0s;
        }
        
        .shape:nth-child(2) {
            width: 120px;
            height: 120px;
            top: 20%;
            right: 20%;
            animation-delay: 2s;
        }
        
        .shape:nth-child(3) {
            width: 60px;
            height: 60px;
            bottom: 20%;
            left: 10%;
            animation-delay: 4s;
        }
        
        .shape:nth-child(4) {
            width: 100px;
            height: 100px;
            bottom: 10%;
            right: 30%;
            animation-delay: 1s;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
        
        .input-group {
            position: relative;
        }
        
        .input-group input:focus + label,
        .input-group input:not(:placeholder-shown) + label {
            transform: translateY(-12px) scale(0.8);
            color: #667eea;
        }
        
        .input-group label {
            position: absolute;
            left: 12px;
            top: 12px;
            transition: all 0.3s ease;
            pointer-events: none;
            background: white;
            padding: 0 4px;
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
<body class="flex items-center justify-center min-h-screen p-4">
    <!-- Floating shapes background -->
    <div class="floating-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>
    
    <!-- Login Card -->
    <div class="login-card w-full max-w-md p-8 rounded-2xl shadow-2xl">
        <!-- Logo and title -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-netblue-500 to-purple-600 rounded-full mb-4">
                <i class="fas fa-shield-alt text-white text-2xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Administration</h1>
            <p class="text-gray-600">Connectez-vous à votre espace admin</p>
        </div>
        
        <!-- Error message -->
        <?php if (!empty($error_message)): ?>
        <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg flex items-center">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>
        
        <!-- Success message -->
        <?php if (!empty($success_message)): ?>
        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg flex items-center">
            <i class="fas fa-check-circle mr-2"></i>
            <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>
        
        <!-- Login Form -->
        <form method="POST" action="login.php" class="space-y-6">
            <!-- Username/Email field -->
            <div class="input-group">
                <input type="text" 
                       name="username" 
                       id="username" 
                       placeholder=" " 
                       required 
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-netblue-500 focus:border-transparent transition-all duration-300">
                <label for="username" class="text-gray-500">Nom d'utilisateur ou Email</label>
            </div>
            
            <!-- Password field -->
            <div class="input-group">
                <input type="password" 
                       name="password" 
                       id="password" 
                       placeholder=" " 
                       required 
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-netblue-500 focus:border-transparent transition-all duration-300">
                <label for="password" class="text-gray-500">Mot de passe</label>
                <button type="button" onclick="togglePassword()" class="absolute right-3 top-3 text-gray-500 hover:text-gray-700">
                    <i class="fas fa-eye" id="password-toggle"></i>
                </button>
            </div>
            
            <!-- Remember me checkbox -->
            <div class="flex items-center justify-between">
                <label class="flex items-center">
                    <input type="checkbox" name="remember_me" class="w-4 h-4 text-netblue-600 border-gray-300 rounded focus:ring-netblue-500">
                    <span class="ml-2 text-sm text-gray-600">Se souvenir de moi</span>
                </label>
                
                <button type="button" onclick="showForgotPassword()" class="text-sm text-netblue-600 hover:text-netblue-800 transition-colors">
                    Mot de passe oublié ?
                </button>
            </div>
            
            <!-- Submit button -->
            <button type="submit" class="w-full bg-gradient-to-r from-netblue-500 to-purple-600 hover:from-netblue-600 hover:to-purple-700 text-white font-bold py-3 px-4 rounded-lg transition-all duration-300 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-netblue-500 focus:ring-offset-2">
                <i class="fas fa-sign-in-alt mr-2"></i>
                Se connecter
            </button>
        </form>
        
        <!-- Footer -->
        <div class="mt-8 pt-6 border-t border-gray-200 text-center">
            <p class="text-xs text-gray-500">
                © 2023 Netcrafter. Tous droits réservés.
            </p>
            <div class="mt-2 space-x-4">
                <button onclick="showAbout()" class="text-xs text-gray-400 hover:text-gray-600 transition-colors">
                    À propos
                </button>
                <button onclick="showHelp()" class="text-xs text-gray-400 hover:text-gray-600 transition-colors">
                    Aide
                </button>
            </div>
        </div>
    </div>
    
    <!-- Forgot Password Modal -->
    <div id="forgot-password-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 hidden z-50">
        <div class="bg-white rounded-lg max-w-md w-full p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold">Mot de passe oublié</h3>
                <button onclick="hideForgotPassword()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <p class="text-gray-600 mb-4">
                Contactez le super administrateur pour réinitialiser votre mot de passe.
            </p>
            <div class="flex justify-end">
                <button onclick="hideForgotPassword()" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">
                    Fermer
                </button>
            </div>
        </div>
    </div>
    
    <!-- About Modal -->
    <div id="about-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 hidden z-50">
        <div class="bg-white rounded-lg max-w-md w-full p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold">À propos</h3>
                <button onclick="hideAbout()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="space-y-4">
                <div class="text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-netblue-500 to-purple-600 rounded-full mb-4">
                        <i class="fas fa-graduation-cap text-white text-2xl"></i>
                    </div>
                    <h4 class="font-bold text-lg">Netcrafter Formation</h4>
                    <p class="text-gray-600">Version 2.1.0</p>
                </div>
                <p class="text-sm text-gray-600 text-center">
                    Plateforme de formation professionnelle développée pour offrir une expérience d'apprentissage moderne et interactive.
                </p>
            </div>
            <div class="flex justify-end mt-6">
                <button onclick="hideAbout()" class="bg-netblue-500 hover:bg-netblue-600 text-white px-4 py-2 rounded-lg">
                    Fermer
                </button>
            </div>
        </div>
    </div>
    
    <!-- Help Modal -->
    <div id="help-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 hidden z-50">
        <div class="bg-white rounded-lg max-w-md w-full p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold">Aide</h3>
                <button onclick="hideHelp()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="space-y-4">
                <div>
                    <h4 class="font-semibold">Connexion</h4>
                    <p class="text-sm text-gray-600">
                        Utilisez votre nom d'utilisateur ou votre adresse email avec votre mot de passe pour vous connecter.
                    </p>
                </div>
                <div>
                    <h4 class="font-semibold">Problèmes de connexion</h4>
                    <p class="text-sm text-gray-600">
                        Si vous rencontrez des difficultés, vérifiez vos identifiants ou contactez le support technique.
                    </p>
                </div>
                <div>
                    <h4 class="font-semibold">Sécurité</h4>
                    <p class="text-sm text-gray-600">
                        Assurez-vous de vous déconnecter après utilisation, surtout sur un ordinateur partagé.
                    </p>
                </div>
            </div>
            <div class="flex justify-end mt-6">
                <button onclick="hideHelp()" class="bg-netblue-500 hover:bg-netblue-600 text-white px-4 py-2 rounded-lg">
                    Fermer
                </button>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('password-toggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        // Modal functions
        function showForgotPassword() {
            document.getElementById('forgot-password-modal').classList.remove('hidden');
        }
        
        function hideForgotPassword() {
            document.getElementById('forgot-password-modal').classList.add('hidden');
        }
        
        function showAbout() {
            document.getElementById('about-modal').classList.remove('hidden');
        }
        
        function hideAbout() {
            document.getElementById('about-modal').classList.add('hidden');
        }
        
        function showHelp() {
            document.getElementById('help-modal').classList.remove('hidden');
        }
        
        function hideHelp() {
            document.getElementById('help-modal').classList.add('hidden');
        }
        
        // Close modals with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideForgotPassword();
                hideAbout();
                hideHelp();
            }
        });
        
        // Focus on username field when page loads
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
        });
        
        // Add loading state to form submission
        document.querySelector('form').addEventListener('submit', function() {
            const submitButton = this.querySelector('button[type="submit"]');
            const originalText = submitButton.innerHTML;
            
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Connexion...';
            submitButton.disabled = true;
            
            // Re-enable button after 5 seconds as fallback
            setTimeout(() => {
                submitButton.innerHTML = originalText;
                submitButton.disabled = false;
            }, 5000);
        });
    </script>
</body>
</html>