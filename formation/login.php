<?php
// Initialisation de la session
session_start();

// Redirection si l'utilisateur est déjà connecté
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
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

// Variables pour les messages d'erreur et de succès
$errors = [];
$success = '';

// Traitement du formulaire de connexion
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Récupération des données du formulaire
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;
    
    // Validation des données
    if (empty($phone)) {
        $errors[] = "Le numéro de téléphone est requis";
    }
    
    if (empty($password)) {
        $errors[] = "Le mot de passe est requis";
    }
    
    // Si aucune erreur, procéder à la connexion
    if (empty($errors)) {
        // Recherche de l'utilisateur dans la base de données
        $query = "SELECT id, firstname, lastname, phone, password, status FROM users WHERE phone = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Vérification du mot de passe
            if (password_verify($password, $user['password'])) {
                // Vérification du statut de l'utilisateur
                if ($user['status'] === 'active') {
                    // Enregistrement des informations de l'utilisateur dans la session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['firstname'] . ' ' . $user['lastname'];
                    $_SESSION['user_phone'] = $user['phone'];
                    
                    // Si "Se souvenir de moi" est coché, créer un cookie
                    if ($remember) {
                        // Générer un token unique
                        $token = bin2hex(random_bytes(32));
                        $token_hash = password_hash($token, PASSWORD_DEFAULT);
                        
                        // Stocker le token en base de données
                        $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
                        $insert_token = "INSERT INTO auth_tokens (user_id, token_hash, expiry_date) VALUES (?, ?, ?)";
                        $stmt = $conn->prepare($insert_token);
                        $stmt->bind_param("iss", $user['id'], $token_hash, $expiry);
                        $stmt->execute();
                        
                        // Créer le cookie avec le token
                        setcookie('remember_token', $token, time() + (86400 * 30), "/"); // 30 jours
                    }
                    
                    // Charger les favoris de l'utilisateur
                    $_SESSION['formation_favorites'] = [];
                    $favorites_query = "SELECT formation_id FROM formation_favorites WHERE user_id = ?";
                    $fav_stmt = $conn->prepare($favorites_query);
                    $fav_stmt->bind_param("i", $user['id']);
                    $fav_stmt->execute();
                    $fav_result = $fav_stmt->get_result();
                    
                    while ($row = $fav_result->fetch_assoc()) {
                        $_SESSION['formation_favorites'][] = $row['formation_id'];
                    }
                    
                    // Redirection vers la page d'accueil ou vers la page préalablement demandée
                    $redirect_url = 'dashboard.php';
                    
                    // Si une URL de redirection est spécifiée
                    if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
                        $redirect_url = $_GET['redirect'];
                    } elseif (isset($_SESSION['redirect_url'])) {
                        $redirect_url = $_SESSION['redirect_url'];
                        unset($_SESSION['redirect_url']);
                    }
                    
                    header("Location: " . $redirect_url);
                    exit;
                } else {
                    $errors[] = "Votre compte est inactif. Veuillez contacter l'administrateur.";
                }
            } else {
                $errors[] = "Numéro de téléphone ou mot de passe incorrect";
            }
        } else {
            $errors[] = "Numéro de téléphone ou mot de passe incorrect";
        }
        
        $stmt->close();
    }
}

// Vérifier si un token existe dans les cookies
if (empty($errors) && !isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    
    // Rechercher le token en base de données
    $token_query = "SELECT t.user_id, u.firstname, u.lastname, u.phone, u.status 
                   FROM auth_tokens t 
                   JOIN users u ON t.user_id = u.id 
                   WHERE t.expiry_date > NOW() 
                   ORDER BY t.created_at DESC";
    $token_result = $conn->query($token_query);
    
    if ($token_result && $token_result->num_rows > 0) {
        // Parcourir les tokens pour trouver une correspondance
        while ($token_row = $token_result->fetch_assoc()) {
            $user_id = $token_row['user_id'];
            $token_hash_query = "SELECT token_hash FROM auth_tokens WHERE user_id = ? AND expiry_date > NOW() ORDER BY created_at DESC";
            $token_hash_stmt = $conn->prepare($token_hash_query);
            $token_hash_stmt->bind_param("i", $user_id);
            $token_hash_stmt->execute();
            $token_hash_result = $token_hash_stmt->get_result();
            
            while ($hash_row = $token_hash_result->fetch_assoc()) {
                if (password_verify($token, $hash_row['token_hash'])) {
                    // Token valide, l'utilisateur est authentifié
                    if ($token_row['status'] === 'active') {
                        $_SESSION['user_id'] = $token_row['user_id'];
                        $_SESSION['user_name'] = $token_row['firstname'] . ' ' . $token_row['lastname'];
                        $_SESSION['user_phone'] = $token_row['phone'];
                        
                        // Charger les favoris de l'utilisateur
                        $_SESSION['formation_favorites'] = [];
                        $favorites_query = "SELECT formation_id FROM formation_favorites WHERE user_id = ?";
                        $fav_stmt = $conn->prepare($favorites_query);
                        $fav_stmt->bind_param("i", $token_row['user_id']);
                        $fav_stmt->execute();
                        $fav_result = $fav_stmt->get_result();
                        
                        while ($row = $fav_result->fetch_assoc()) {
                            $_SESSION['formation_favorites'][] = $row['formation_id'];
                        }
                        
                        // Générer un nouveau token pour prolonger la session
                        $new_token = bin2hex(random_bytes(32));
                        $new_token_hash = password_hash($new_token, PASSWORD_DEFAULT);
                        $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));
                        
                        // Supprimer l'ancien token
                        $delete_token = "DELETE FROM auth_tokens WHERE token_hash = ?";
                        $del_stmt = $conn->prepare($delete_token);
                        $del_stmt->bind_param("s", $hash_row['token_hash']);
                        $del_stmt->execute();
                        
                        // Ajouter le nouveau token
                        $insert_token = "INSERT INTO auth_tokens (user_id, token_hash, expiry_date) VALUES (?, ?, ?)";
                        $ins_stmt = $conn->prepare($insert_token);
                        $ins_stmt->bind_param("iss", $token_row['user_id'], $new_token_hash, $expiry);
                        $ins_stmt->execute();
                        
                        // Mettre à jour le cookie
                        setcookie('remember_token', $new_token, time() + (86400 * 30), "/");
                        
                        // Redirection vers la page d'accueil ou vers la page préalablement demandée
                        $redirect_url = 'dashboard.php';
                        
                        if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
                            $redirect_url = $_GET['redirect'];
                        } elseif (isset($_SESSION['redirect_url'])) {
                            $redirect_url = $_SESSION['redirect_url'];
                            unset($_SESSION['redirect_url']);
                        }
                        
                        header("Location: " . $redirect_url);
                        exit;
                    }
                    break 2; // Sortir des deux boucles si un token valide est trouvé
                }
            }
            $token_hash_stmt->close();
        }
    }
}

$conn->close();

// Si une URL de redirection est spécifiée, la sauvegarder dans la session
if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
    $_SESSION['redirect_url'] = $_GET['redirect'];
}
?>

<?php
    $curLang   = $GLOBALS['nc_lang'] ?? 'fr';
    $switchLang = $curLang === 'fr' ? 'en' : 'fr';
    $switchLabel = $curLang === 'fr' ? 'EN' : 'FR';
    $switchUrl  = strtok($_SERVER['REQUEST_URI'],'?').'?'.http_build_query(array_merge($_GET,['lang'=>$switchLang]));
?>
<!DOCTYPE html>
<html lang="<?= $curLang ?>" class="scroll">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('form.login_title') ?> - Netcrafter</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @keyframes fadeIn { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
        .animate-fadeIn { animation: fadeIn 0.5s ease-out forwards; }
        .delay-1{animation-delay:.1s} .delay-2{animation-delay:.2s}
        .delay-3{animation-delay:.3s} .delay-4{animation-delay:.4s} .delay-5{animation-delay:.5s}
    </style>
    <?php include __DIR__ . '/nc-theme.php'; ?>
</head>
<body style="background:linear-gradient(180deg,#060d1e 0%,#030810 100%)">
    <!-- Nav -->
    <nav class="fixed w-full z-50 transition-all duration-300" style="background:rgba(6,13,30,0.96);border-bottom:1px solid rgba(0,200,255,0.1);backdrop-filter:blur(20px)">
        <div class="container mx-auto px-4 py-3 flex items-center justify-between">
            <a href="../index.php" class="flex items-center">
                <img src="../image/logo-n.png" alt="Netcrafter" class="h-10 mr-2">
                <span class="text-xl font-bold" style="color:#00c8ff">NETCRAFTER</span>
            </a>

            <div class="hidden md:flex items-center space-x-6">
                <a href="../index.php" class="hover:text-nc-cyan transition-colors" style="color:#94a3b8"><?= t('nav.home') ?></a>
                <a href="formations.php" class="hover:text-nc-cyan transition-colors" style="color:#94a3b8"><?= t('nav.training') ?></a>
                <a href="register.php<?= isset($_GET['redirect']) ? '?redirect='.urlencode($_GET['redirect']) : '' ?>" class="hover:text-nc-cyan transition-colors" style="color:#94a3b8"><?= t('form.register_title') ?></a>
                <a href="<?= htmlspecialchars($switchUrl) ?>" class="nc-lang-btn"><i class="fas fa-globe text-xs"></i><?= $switchLabel ?></a>
            </div>

            <div class="md:hidden flex items-center space-x-3">
                <a href="<?= htmlspecialchars($switchUrl) ?>" class="nc-lang-btn"><i class="fas fa-globe text-xs"></i><?= $switchLabel ?></a>
                <button id="menu-toggle" style="color:#94a3b8"><i class="fas fa-bars text-2xl"></i></button>
            </div>
        </div>

        <div id="mobile-menu" class="hidden md:hidden" style="background:rgba(6,13,30,0.98);border-top:1px solid rgba(0,200,255,0.08)">
            <div class="px-4 py-2 space-y-3">
                <a href="../index.php" class="block py-2 hover:text-nc-cyan transition-colors" style="color:#94a3b8"><?= t('nav.home') ?></a>
                <a href="formations.php" class="block py-2 hover:text-nc-cyan transition-colors" style="color:#94a3b8"><?= t('nav.training') ?></a>
                <a href="register.php" class="block py-2 hover:text-nc-cyan transition-colors" style="color:#94a3b8"><?= t('form.register_title') ?></a>
            </div>
        </div>
    </nav>

    <!-- Login Section -->
    <section class="min-h-screen flex items-center justify-center pt-24 pb-10 px-4">
        <div class="w-full max-w-md">
            <!-- Login Card -->
            <div class="rounded-2xl overflow-hidden" style="background:rgba(10,24,58,0.82);border:1px solid rgba(0,200,255,0.14);backdrop-filter:blur(16px)">
                <!-- Card Header -->
                <div class="p-6 text-white text-center" style="background:linear-gradient(135deg,rgba(0,200,255,0.12),rgba(6,13,30,0.98))">
                    <div class="text-5xl mb-3" style="color:#00c8ff"><i class="fas fa-user-circle"></i></div>
                    <h1 class="text-2xl font-bold animate-fadeIn"><?= t('form.login_title') ?></h1>
                    <p class="mt-2 animate-fadeIn delay-1" style="color:#94a3b8"><?= t('form.login_sub') ?></p>
                </div>

                <!-- Card Body -->
                <div class="p-6 md:p-8">
                    <?php if (!empty($errors)): ?>
                    <div class="mb-6 px-4 py-3 rounded-lg animate-fadeIn" style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);color:#fca5a5">
                        <ul class="list-disc pl-5">
                            <?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($success)): ?>
                    <div class="mb-6 px-4 py-3 rounded-lg animate-fadeIn" style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);color:#6ee7b7">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . (isset($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '')); ?>" class="space-y-5">
                        <!-- Phone -->
                        <div class="animate-fadeIn delay-2">
                            <label for="phone" class="block mb-2 font-medium" style="color:#cbd5e1"><?= t('form.phone') ?></label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3" style="color:#94a3b8"><i class="fas fa-phone"></i></span>
                                <input type="tel" id="phone" name="phone" value="<?php echo isset($phone) ? htmlspecialchars($phone) : ''; ?>"
                                    class="w-full pl-10 pr-3 py-3 rounded-lg"
                                    placeholder="<?= t('form.phone') ?>" required>
                            </div>
                        </div>

                        <!-- Password -->
                        <div class="animate-fadeIn delay-3">
                            <div class="flex items-center justify-between mb-2">
                                <label for="password" class="font-medium" style="color:#cbd5e1"><?= t('form.password') ?></label>
                                <a href="forgot-password.php" class="text-sm hover:underline" style="color:#00c8ff"><?= t('form.forgot') ?></a>
                            </div>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3" style="color:#94a3b8"><i class="fas fa-lock"></i></span>
                                <input type="password" id="password" name="password"
                                    class="w-full pl-10 pr-10 py-3 rounded-lg"
                                    placeholder="<?= t('form.password') ?>" required>
                                <button type="button" id="toggle-password" class="absolute inset-y-0 right-0 flex items-center pr-3" style="color:#94a3b8">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Remember Me -->
                        <div class="flex items-center animate-fadeIn delay-4">
                            <input type="checkbox" id="remember" name="remember" class="h-4 w-4 rounded" style="accent-color:#00c8ff">
                            <label for="remember" class="ml-2" style="color:#cbd5e1"><?= t('form.remember') ?></label>
                        </div>

                        <!-- Submit -->
                        <div class="animate-fadeIn delay-5">
                            <button type="submit" class="w-full btn-primary justify-center py-3">
                                <i class="fas fa-sign-in-alt mr-2"></i><?= t('form.login_title') ?>
                            </button>
                        </div>
                    </form>

                    <!-- Register Link -->
                    <div class="mt-6 text-center animate-fadeIn delay-5">
                        <p style="color:#94a3b8">
                            <?= t('form.no_account') ?>
                            <a href="register.php<?= isset($_GET['redirect']) ? '?redirect='.urlencode($_GET['redirect']) : '' ?>" class="font-medium hover:underline" style="color:#00c8ff">
                                <?= t('form.register_title') ?>
                            </a>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Info Card -->
            <div class="mt-6 p-5 rounded-2xl animate-fadeIn delay-5" style="background:rgba(10,24,58,0.72);border:1px solid rgba(0,200,255,0.12)">
                <div class="flex items-start">
                    <i class="fas fa-info-circle text-lg mt-0.5 mr-3" style="color:#00c8ff"></i>
                    <div>
                        <h3 class="text-sm font-medium text-white mb-1">Besoin d'assistance ?</h3>
                        <p class="text-sm" style="color:#94a3b8">
                            Contactez notre support au <span class="text-white font-medium">+227 88 67 21 15</span>
                            ou <a href="mailto:support@netcrafterniger.com" class="hover:underline" style="color:#00c8ff">support@netcrafterniger.com</a>.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-6 text-center" style="background:rgba(6,13,30,0.8);border-top:1px solid rgba(0,200,255,0.08)">
        <p style="color:#475569">© <?= date('Y') ?> Netcrafter. <?= t('footer.rights') ?></p>
    </footer>

    <script>
        document.getElementById('menu-toggle').addEventListener('click', function() {
            document.getElementById('mobile-menu').classList.toggle('hidden');
        });
        document.getElementById('toggle-password').addEventListener('click', function() {
            const inp = document.getElementById('password');
            const icon = this.querySelector('i');
            if (inp.type === 'password') { inp.type = 'text'; icon.className = 'fas fa-eye-slash'; }
            else { inp.type = 'password'; icon.className = 'fas fa-eye'; }
        });
    </script>
</body>
</html>