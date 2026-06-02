<?php
// Fichier register.php - Inscription d'un nouvel utilisateur

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

// Traitement du formulaire d'inscription
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Récupération des données du formulaire
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation des données
    if (empty($firstname)) {
        $errors[] = "Le prénom est requis";
    }
    
    if (empty($lastname)) {
        $errors[] = "Le nom est requis";
    }
    
    if (empty($phone)) {
        $errors[] = "Le numéro de téléphone est requis";
    } elseif (!preg_match("/^[0-9]{8,}$/", $phone)) {
        $errors[] = "Veuillez entrer un numéro de téléphone valide (au moins 8 chiffres)";
    } else {
        // Vérifier si le numéro de téléphone existe déjà
        $check_phone = "SELECT id FROM users WHERE phone = ?";
        $stmt = $conn->prepare($check_phone);
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $errors[] = "Ce numéro de téléphone est déjà utilisé";
        }
        $stmt->close();
    }
    
    if (empty($password)) {
        $errors[] = "Le mot de passe est requis";
    } elseif (strlen($password) < 6) {
        $errors[] = "Le mot de passe doit contenir au moins 6 caractères";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Les mots de passe ne correspondent pas";
    }
    
    // Si aucune erreur, procéder à l'inscription
    if (empty($errors)) {
        // Hachage du mot de passe
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Préparation de la requête d'insertion
        $insert_query = "INSERT INTO users (firstname, lastname, phone, password, created_at) VALUES (?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("ssss", $firstname, $lastname, $phone, $hashed_password);
        
        // Exécution de la requête
        if ($stmt->execute()) {
            $success = "Inscription réussie! Vous pouvez maintenant vous connecter.";
            
            // Redirection vers la page de connexion après 2 secondes
            header("refresh:2;url=login.php");
        } else {
            $errors[] = "Erreur lors de l'inscription: " . $stmt->error;
        }
        
        $stmt->close();
    }
}

$conn->close();
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
    <title><?= t('form.register_title') ?> - Netcrafter</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                <a href="login.php" class="hover:text-nc-cyan transition-colors" style="color:#94a3b8"><?= t('nav.login') ?></a>
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
                <a href="login.php" class="block py-2 hover:text-nc-cyan transition-colors" style="color:#94a3b8"><?= t('nav.login') ?></a>
            </div>
        </div>
    </nav>

    <!-- Register Section -->
    <section class="min-h-screen flex items-center justify-center pt-24 pb-10 px-4">
        <div class="w-full max-w-md">
            <div class="rounded-2xl overflow-hidden" style="background:rgba(10,24,58,0.82);border:1px solid rgba(0,200,255,0.14);backdrop-filter:blur(16px)">
                <!-- Header -->
                <div class="p-6 text-white text-center" style="background:linear-gradient(135deg,rgba(0,200,255,0.12),rgba(6,13,30,0.98))">
                    <div class="text-5xl mb-3" style="color:#00c8ff"><i class="fas fa-user-plus"></i></div>
                    <h1 class="text-2xl font-bold"><?= t('form.register_title') ?></h1>
                    <p class="mt-2" style="color:#94a3b8"><?= t('form.register_sub') ?></p>
                </div>

                <!-- Body -->
                <div class="p-6">
                    <?php if (!empty($errors)): ?>
                    <div class="mb-5 px-4 py-3 rounded-lg" style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);color:#fca5a5">
                        <ul class="list-disc pl-5">
                            <?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($success)): ?>
                    <div class="mb-5 px-4 py-3 rounded-lg" style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);color:#6ee7b7">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="firstname" class="block mb-1 font-medium" style="color:#cbd5e1"><?= t('form.firstname') ?></label>
                                <input type="text" id="firstname" name="firstname" value="<?php echo isset($firstname) ? htmlspecialchars($firstname) : ''; ?>"
                                    class="w-full px-4 py-2 rounded-lg" required>
                            </div>
                            <div>
                                <label for="lastname" class="block mb-1 font-medium" style="color:#cbd5e1"><?= t('form.lastname') ?></label>
                                <input type="text" id="lastname" name="lastname" value="<?php echo isset($lastname) ? htmlspecialchars($lastname) : ''; ?>"
                                    class="w-full px-4 py-2 rounded-lg" required>
                            </div>
                        </div>

                        <div>
                            <label for="phone" class="block mb-1 font-medium" style="color:#cbd5e1"><?= t('form.phone') ?></label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3" style="color:#94a3b8"><i class="fas fa-phone"></i></span>
                                <input type="tel" id="phone" name="phone" value="<?php echo isset($phone) ? htmlspecialchars($phone) : ''; ?>"
                                    class="w-full pl-10 pr-4 py-2 rounded-lg" placeholder="<?= t('form.phone') ?>" required>
                            </div>
                        </div>

                        <div>
                            <label for="password" class="block mb-1 font-medium" style="color:#cbd5e1"><?= t('form.password') ?></label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3" style="color:#94a3b8"><i class="fas fa-lock"></i></span>
                                <input type="password" id="password" name="password"
                                    class="w-full pl-10 pr-10 py-2 rounded-lg" required>
                                <button type="button" id="toggle-password" class="absolute inset-y-0 right-0 flex items-center pr-3" style="color:#94a3b8">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label for="confirm_password" class="block mb-1 font-medium" style="color:#cbd5e1"><?= t('form.confirm_pass') ?></label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3" style="color:#94a3b8"><i class="fas fa-lock"></i></span>
                                <input type="password" id="confirm_password" name="confirm_password"
                                    class="w-full pl-10 pr-10 py-2 rounded-lg" required>
                                <button type="button" id="toggle-confirm" class="absolute inset-y-0 right-0 flex items-center pr-3" style="color:#94a3b8">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="submit" class="w-full btn-primary justify-center py-3">
                                <i class="fas fa-user-plus mr-2"></i><?= t('form.register_title') ?>
                            </button>
                        </div>
                    </form>

                    <div class="mt-5 text-center">
                        <p style="color:#94a3b8">
                            <?= t('form.has_account') ?>
                            <a href="login.php" class="font-medium hover:underline" style="color:#00c8ff"><?= t('nav.login') ?></a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="py-6 text-center" style="background:rgba(6,13,30,0.8);border-top:1px solid rgba(0,200,255,0.08)">
        <p style="color:#475569">© <?= date('Y') ?> Netcrafter. <?= t('footer.rights') ?></p>
    </footer>

    <script>
        document.getElementById('menu-toggle').addEventListener('click', function() {
            document.getElementById('mobile-menu').classList.toggle('hidden');
        });
        function togglePwd(inputId, btn) {
            const inp = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            if (inp.type === 'password') { inp.type = 'text'; icon.className = 'fas fa-eye-slash'; }
            else { inp.type = 'password'; icon.className = 'fas fa-eye'; }
        }
        document.getElementById('toggle-password').addEventListener('click', function() { togglePwd('password', this); });
        document.getElementById('toggle-confirm').addEventListener('click', function() { togglePwd('confirm_password', this); });
    </script>
</body>
</html>
