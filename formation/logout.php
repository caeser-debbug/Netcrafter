<?php
// Initialisation de la session
session_start();

require_once __DIR__ . '/db.php';

// Connexion à la base de données pour enregistrer la déconnexion
$conn = new mysqli($servername, $username, $password, $dbname);

// Vérification de la connexion
if (!$conn->connect_error) {
    $conn->set_charset("utf8");
    
    // Si l'utilisateur est connecté, enregistrer l'heure de déconnexion
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        
        // Mettre à jour l'heure de dernière activité dans la table users
        $update_query = "UPDATE users SET last_login = NOW() WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    // Fermer la connexion
    $conn->close();
}

// Sauvegarder des informations importantes avant de détruire la session
$user_was_logged_in = isset($_SESSION['user_id']);

// Détruire toutes les variables de session
$_SESSION = array();

// Supprimer le cookie de session s'il existe
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Détruire la session
session_destroy();

// Supprimer les cookies de "Se souvenir de moi" s'ils existent
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}
if (isset($_COOKIE['remember_user'])) {
    setcookie('remember_user', '', time() - 3600, '/');
}

// Empêcher la mise en cache de cette page
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Vérifier si une URL de redirection est demandée
$redirect_url = 'login.php';
if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
    // Valider l'URL de redirection pour éviter les attaques de redirection
    $allowed_redirects = ['login.php', 'index.php', '../index.php'];
    $requested_redirect = $_GET['redirect'];
    
    if (in_array($requested_redirect, $allowed_redirects)) {
        $redirect_url = $requested_redirect;
    }
}

// Démarrer une nouvelle session pour le message de déconnexion
session_start();
if ($user_was_logged_in) {
    $_SESSION['logout_message'] = "Vous avez été déconnecté avec succès.";
    $_SESSION['logout_success'] = true;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Déconnexion - Netcrafter Formations</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .animate-fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3B82F6;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        netblue: {
                            500: '#3B82F6',
                            600: '#1A6BE2',
                            700: '#0055CC'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen flex items-center justify-center">
    <div class="container mx-auto px-4">
        <div class="max-w-md mx-auto">
            <!-- Logo -->
            <div class="text-center mb-8 animate-fade-in">
                <img src="../image/logo-n.png" alt="Netcrafter Logo" class="h-16 mx-auto mb-4">
                <h1 class="text-2xl font-bold text-gray-800">NETCRAFTER</h1>
                <p class="text-gray-600">Formations professionnelles</p>
            </div>
            
            <!-- Logout Card -->
            <div class="bg-white rounded-2xl shadow-xl p-8 animate-fade-in">
                <div class="text-center">
                    <!-- Success Icon -->
                    <div class="mb-6">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full">
                            <i class="fas fa-check-circle text-3xl text-green-500"></i>
                        </div>
                    </div>
                    
                    <!-- Message -->
                    <h2 class="text-2xl font-bold text-gray-800 mb-4">Déconnexion réussie</h2>
                    <p class="text-gray-600 mb-6">
                        Vous avez été déconnecté avec succès de votre compte Netcrafter Formations.
                    </p>
                    
                    <!-- Loading Spinner -->
                    <div class="mb-6">
                        <div class="spinner mx-auto"></div>
                        <p class="text-sm text-gray-500 mt-2">Redirection en cours...</p>
                    </div>
                    
                    <!-- Actions -->
                    <div class="space-y-3">
                        <a href="<?php echo htmlspecialchars($redirect_url); ?>" 
                           class="block w-full bg-netblue-600 hover:bg-netblue-700 text-white font-medium py-3 px-4 rounded-lg transition-colors duration-200">
                            <i class="fas fa-sign-in-alt mr-2"></i>
                            Se reconnecter
                        </a>
                        
                        <a href="../index.php" 
                           class="block w-full bg-gray-100 hover:bg-gray-200 text-gray-800 font-medium py-3 px-4 rounded-lg transition-colors duration-200">
                            <i class="fas fa-home mr-2"></i>
                            Retour à l'accueil
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Additional Info -->
            <div class="text-center mt-6 animate-fade-in">
                <p class="text-sm text-gray-500">
                    Merci d'avoir utilisé Netcrafter Formations
                </p>
                <div class="flex justify-center space-x-4 mt-4">
                    <a href="https://www.facebook.com/share/1Y7kHRs16L/" class="text-gray-400 hover:text-netblue-600 transition-colors">
                        <i class="fab fa-facebook-f text-lg"></i>
                    </a>
                    <a href="https://www.instagram.com/netcrafter.niger?igsh=NzJ2bzM2aWRnMzho" class="text-gray-400 hover:text-netblue-600 transition-colors">
                        <i class="fab fa-instagram text-lg"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript pour la redirection automatique -->
    <script>
        // Redirection automatique après 3 secondes
        setTimeout(function() {
            window.location.href = '<?php echo htmlspecialchars($redirect_url); ?>';
        }, 3000);
        
        // Afficher un message de confirmation si l'utilisateur essaie de quitter
        window.addEventListener('beforeunload', function(e) {
            // Cette fonction ne s'exécutera que si l'utilisateur essaie de naviguer ailleurs
            // avant la redirection automatique
        });
        
        // Animation d'entrée progressive
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.animate-fade-in');
            elements.forEach((el, index) => {
                el.style.animationDelay = (index * 0.2) + 's';
            });
        });
    </script>
</body>
</html>