<?php
// Initialisation de la session
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    // Sauvegarder l'URL actuelle pour y revenir après la connexion
    $_SESSION['redirect_url'] = "subscribe.php" . (isset($_GET['formation_id']) ? "?formation_id=" . $_GET['formation_id'] : "");
    
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
$formation_id = isset($_GET['formation_id']) ? intval($_GET['formation_id']) : 0;

// Vérifier si un ID de formation est fourni
if ($formation_id === 0) {
    header("Location: formations.php");
    exit;
}

// Vérifier si l'utilisateur est déjà abonné à cette formation
$subscription_query = "SELECT * FROM formation_subscriptions 
                      WHERE user_id = ? AND formation_id = ? 
                      AND status IN ('pending', 'active') 
                      AND end_date >= CURDATE()";
$stmt = $conn->prepare($subscription_query);
$stmt->bind_param("ii", $user_id, $formation_id);
$stmt->execute();
$subscription_result = $stmt->get_result();

$is_subscribed = false;
$subscription = null;

if ($subscription_result->num_rows > 0) {
    $is_subscribed = true;
    $subscription = $subscription_result->fetch_assoc();
    
    // Si l'abonnement est actif, rediriger vers la page des détails de la formation
    if ($subscription['status'] === 'active') {
        header("Location: formation-details.php?id=" . $formation_id);
        exit;
    }
}

// Récupérer les détails de la formation
$formation_query = "SELECT f.*, c.name as category_name, c.icon as category_icon 
                   FROM formations f 
                   JOIN formation_categories c ON f.category_id = c.id 
                   WHERE f.id = ? AND f.status = 'active'";
$stmt = $conn->prepare($formation_query);
$stmt->bind_param("i", $formation_id);
$stmt->execute();
$result = $stmt->get_result();

// Si la formation n'existe pas ou n'est pas active, rediriger vers la liste des formations
if ($result->num_rows === 0) {
    header("Location: formations.php?error=formation_not_found");
    exit;
}

$formation = $result->fetch_assoc();

// Si l'image de couverture n'est pas définie, utiliser une image par défaut
if (empty($formation['cover_image'])) {
    $formation['cover_image'] = "image/formations/default-" . rand(1, 3) . ".jpg";
}

// Récupérer les informations de l'utilisateur
$user_query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

// Traitement du formulaire d'abonnement
$errors = [];
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $subscription_months = isset($_POST['subscription_months']) ? intval($_POST['subscription_months']) : 1;
    $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';
    
    // Validation des entrées
    if ($subscription_months < 1 || $subscription_months > 12) {
        $errors[] = "Veuillez sélectionner une durée d'abonnement valide entre 1 et 12 mois.";
    }
    
    if (!in_array($payment_method, ['nita', 'amana', 'zeyna', 'niya'])) {
        $errors[] = "Veuillez sélectionner une méthode de paiement valide.";
    }
    
    // Gestion de l'upload du reçu
    $receipt_image = '';
    
    if (isset($_FILES['payment_receipt']) && $_FILES['payment_receipt']['error'] == 0) {
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
        $file_info = pathinfo($_FILES['payment_receipt']['name']);
        $file_extension = strtolower($file_info['extension']);
        
        if (!in_array($file_extension, $allowed_extensions)) {
            $errors[] = "Le format du fichier n'est pas pris en charge. Formats autorisés : JPG, JPEG, PNG, PDF.";
        } elseif ($_FILES['payment_receipt']['size'] > 5000000) { // 5MB
            $errors[] = "La taille du fichier est trop grande. Taille maximale : 5MB.";
        } else {
            // Créer le dossier receipts s'il n'existe pas
            $receipts_dir = 'uploads/receipts';
            if (!file_exists($receipts_dir)) {
                mkdir($receipts_dir, 0777, true);
            }
            
            // Générer un nom de fichier unique
            $file_name = 'receipt_' . time() . '_' . mt_rand(1000, 9999) . '.' . $file_extension;
            $upload_path = $receipts_dir . '/' . $file_name;
            
            if (move_uploaded_file($_FILES['payment_receipt']['tmp_name'], $upload_path)) {
                $receipt_image = $upload_path;
            } else {
                $errors[] = "Une erreur est survenue lors de l'upload du fichier.";
            }
        }
    } else {
        $errors[] = "Veuillez télécharger une copie de votre reçu de paiement.";
    }
    
    // Si aucune erreur, procéder à l'abonnement
    if (empty($errors)) {
        $amount_paid = $formation['price_per_month'] * $subscription_months;
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime("+$subscription_months months"));
        
        if ($is_subscribed) {
            // Mettre à jour l'abonnement existant avec les nouvelles informations
            $update_subscription = "UPDATE formation_subscriptions 
                                  SET payment_method = ?, 
                                      payment_proof = ?, 
                                      subscription_months = ?, 
                                      amount_paid = ?, 
                                      start_date = ?, 
                                      end_date = ?, 
                                      status = 'pending', 
                                      updated_at = NOW() 
                                  WHERE id = ?";
            
            $stmt = $conn->prepare($update_subscription);
            $stmt->bind_param("ssidssi", 
                $payment_method, 
                $receipt_image, 
                $subscription_months, 
                $amount_paid, 
                $start_date, 
                $end_date,
                $subscription['id']
            );
            
            if ($stmt->execute()) {
                $success_message = "Votre demande d'abonnement a été mise à jour avec succès! Notre équipe va examiner votre paiement et activer votre abonnement dès que possible.";
            } else {
                $errors[] = "Une erreur est survenue lors de la mise à jour de votre abonnement. Veuillez réessayer.";
            }
        } else {
            // Créer un nouvel abonnement
            $insert_subscription = "INSERT INTO formation_subscriptions 
                                  (user_id, formation_id, payment_method, payment_proof, subscription_months, amount_paid, start_date, end_date, status) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
            
            $stmt = $conn->prepare($insert_subscription);
            $stmt->bind_param("iissidss", 
                $user_id, 
                $formation_id, 
                $payment_method, 
                $receipt_image, 
                $subscription_months, 
                $amount_paid, 
                $start_date, 
                $end_date
            );
            
            if ($stmt->execute()) {
                $success_message = "Votre demande d'abonnement a été enregistrée avec succès! Notre équipe va examiner votre paiement et activer votre abonnement dès que possible.";
                // Send confirmation email to student
                require_once __DIR__ . '/../includes/mail.php';
                $student_name = trim(($user['prenom'] ?? '') . ' ' . ($user['nom'] ?? $user['name'] ?? ''));
                if (!$student_name) $student_name = $user['email'] ?? '';
                nc_mail_formation_inscription($user['email'], $student_name, $formation['title']);
            } else {
                $errors[] = "Une erreur est survenue lors de l'enregistrement de votre abonnement. Veuillez réessayer.";
            }
        }
    }
}

// Fermeture de la connexion à la base de données
$conn->close();
?>

<!DOCTYPE html>
<html lang="fr" class="light scroll">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S'abonner - <?php echo htmlspecialchars($formation['title']); ?> - Netcrafter Formations</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom styles -->
    <style>
        html {
            scroll-behavior: smooth;
        }
        
        .hero-gradient-light {
            background: linear-gradient(135deg, #0288d1 0%, #01579b 100%);
        }
        
        .hero-gradient-dark {
            background: linear-gradient(135deg, #1e3a8a 0%, #0f172a 100%);
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
        
        /* Payment method selection */
        .payment-method-label {
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .payment-method-input:checked + .payment-method-label {
            border-color: #3B82F6;
            background-color: rgba(59, 130, 246, 0.1);
        }
        
        .payment-method-input:focus + .payment-method-label {
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.5);
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
    <!-- Navigation simplifiée -->
    <nav class="fixed w-full bg-white dark:bg-gray-800 shadow-md z-50 transition-all duration-300">
        <div class="container mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center">
                <a href="index.html" class="flex items-center">
                    <img src="../image/logo-n.png" alt="Netcrafter Logo" class="h-10 mr-2">
                    <span class="text-xl md:text-2xl font-bold text-netblue-600 dark:text-netblue-400 navbar-brand">NETCRAFTER</span>
                </a>
            </div>
            
            <!-- Links -->
            <div class="hidden md:flex items-center space-x-6 text-gray-700 dark:text-gray-300">
                <a href="index.html" class="hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors">Accueil</a>
                <a href="formations.php" class="text-netblue-600 dark:text-netblue-400 font-medium">Formations</a>
                <a href="formation-favorites.php" class="hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors">Mes favoris</a>
                <a href="dashboard.php" class="hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors">Mon compte</a>
                
                <!-- Dark Mode Toggle -->
                <div class="flex items-center">
                    <label class="theme-switch relative inline-block w-14 h-7">
                        <input type="checkbox" id="darkModeToggle" class="opacity-0 w-0 h-0">
                        <span class="slider absolute cursor-pointer inset-0 bg-gray-300 rounded-full transition-all duration-300 before:absolute before:h-5 before:w-5 before:left-1 before:bottom-1 before:bg-white before:rounded-full before:transition-all before:duration-300"></span>
                    </label>
                </div>
            </div>
            
            <!-- Mobile Menu Button and Dark Mode Toggle -->
            <div class="md:hidden flex items-center space-x-4">
                <!-- Mobile Dark Mode Toggle -->
                <div class="flex items-center">
                    <label class="theme-switch relative inline-block w-12 h-6">
                        <input type="checkbox" id="darkModeToggleMobile" class="opacity-0 w-0 h-0">
                        <span class="slider absolute cursor-pointer inset-0 bg-gray-300 rounded-full transition-all duration-300 before:absolute before:h-4 before:w-4 before:left-1 before:bottom-1 before:bg-white before:rounded-full before:transition-all before:duration-300"></span>
                    </label>
                </div>
                
                <button id="menu-toggle" class="text-gray-700 dark:text-white focus:outline-none">
                    <i class="fas fa-bars text-2xl"></i>
                </button>
            </div>
        </div>
        
        <!-- Mobile Menu -->
        <div id="mobile-menu" class="hidden md:hidden bg-white dark:bg-gray-800 shadow-md">
            <div class="px-4 py-2 space-y-3">
                <a href="index.html" class="block py-2 hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors dark:text-gray-300">Accueil</a>
                <a href="formations.php" class="block py-2 text-netblue-600 dark:text-netblue-400 font-medium">Formations</a>
                <a href="formation-favorites.php" class="block py-2 hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors dark:text-gray-300">Mes favoris</a>
                <a href="dashboard.php" class="block py-2 hover:text-netblue-600 dark:hover:text-netblue-400 transition-colors dark:text-gray-300">Mon compte</a>
                <a href="logout.php" class="block py-2 text-red-600">Déconnexion</a>
            </div>
        </div>
    </nav>

    <!-- Subscribe Section -->
    <section class="pt-24 pb-12">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row gap-8">
                <!-- Formation Information -->
                <div class="md:w-1/3">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden sticky top-24">
                        <!-- Formation Image -->
                        <div class="relative h-48">
                            <img src="../<?php echo htmlspecialchars($formation['cover_image']); ?>" alt="<?php echo htmlspecialchars($formation['title']); ?>" class="w-full h-full object-cover">
                            
                            <!-- Category Badge -->
                            <div class="absolute top-3 left-3 bg-netblue-600 text-white text-xs font-bold px-2 py-1 rounded">
                                <i class="fas <?php echo htmlspecialchars($formation['category_icon']); ?> mr-1"></i>
                                <?php echo htmlspecialchars($formation['category_name']); ?>
                            </div>
                            
                            <!-- Level Badge -->
                            <div class="absolute bottom-3 left-3 text-white text-xs font-bold px-2 py-1 rounded
                                <?php 
                                switch($formation['level']) {
                                    case 'debutant':
                                        echo 'bg-green-600';
                                        break;
                                    case 'intermediaire':
                                        echo 'bg-yellow-600';
                                        break;
                                    case 'avance':
                                        echo 'bg-red-600';
                                        break;
                                }
                                ?>">
                                <?php 
                                switch($formation['level']) {
                                    case 'debutant':
                                        echo 'Débutant';
                                        break;
                                    case 'intermediaire':
                                        echo 'Intermédiaire';
                                        break;
                                    case 'avance':
                                        echo 'Avancé';
                                        break;
                                }
                                ?>
                            </div>
                        </div>
                        
                        <!-- Formation Details -->
                        <div class="p-4">
                            <h2 class="text-xl font-bold mb-2 dark:text-white"><?php echo htmlspecialchars($formation['title']); ?></h2>
                            
                            <div class="flex items-center mb-3 text-sm text-gray-500 dark:text-gray-400">
                                <div class="flex items-center mr-4">
                                    <i class="fas fa-clock mr-1"></i>
                                    <span><?php echo !empty($formation['duration']) ? htmlspecialchars($formation['duration']) : 'Durée variable'; ?></span>
                                </div>
                            </div>
                            
                            <p class="text-gray-600 dark:text-gray-300 text-sm mb-4">
                                <?php 
                                if (!empty($formation['short_description'])) {
                                    echo htmlspecialchars($formation['short_description']);
                                } else {
                                    echo htmlspecialchars(substr($formation['description'], 0, 150)) . (strlen($formation['description']) > 150 ? '...' : '');
                                }
                                ?>
                            </p>
                            
                            <div class="flex justify-between items-center mb-2">
                                <div class="text-netblue-600 dark:text-netblue-400 font-bold text-2xl">
                                    <?php echo number_format($formation['price_per_month'], 0, ',', ' '); ?> FCFA
                                </div>
                                <div class="text-gray-500 dark:text-gray-400 text-sm">
                                    par mois
                                </div>
                            </div>
                            
                            <div class="bg-netblue-50 dark:bg-netblue-900/30 rounded-lg p-3 border border-netblue-100 dark:border-netblue-800 mb-4">
                                <div class="flex items-center">
                                    <i class="fas fa-info-circle text-netblue-600 dark:text-netblue-400 mr-2"></i>
                                    <p class="text-sm text-netblue-800 dark:text-netblue-300">
                                        <?php if ($is_subscribed && $subscription['status'] === 'pending'): ?>
                                            Votre demande d'abonnement est en attente de validation par notre équipe.
                                        <?php else: ?>
                                            Suivez les étapes ci-contre pour vous abonner à cette formation.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            
                            <a href="formation-details.php?id=<?php echo $formation_id; ?>" class="w-full bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-800 dark:text-white text-center py-2 px-4 rounded-lg transition-colors flex items-center justify-center">
                                <i class="fas fa-arrow-left mr-2"></i>Retour aux détails de la formation
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Subscription Form -->
                <div class="md:w-2/3">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 md:p-8">
                        <h1 class="text-2xl font-bold mb-6 dark:text-white">
                            <?php if ($is_subscribed && $subscription['status'] === 'pending'): ?>
                                Mettre à jour votre demande d'abonnement
                            <?php else: ?>
                                S'abonner à la formation
                            <?php endif; ?>
                        </h1>
                        
                        <?php if (!empty($errors)): ?>
                        <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                            <ul class="list-disc pl-5">
                                <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success_message)): ?>
                        <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                            <p><?php echo $success_message; ?></p>
                            <p class="mt-2">Vous pouvez suivre l'état de votre abonnement depuis votre <a href="dashboard.php" class="font-bold underline">tableau de bord</a>.</p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (empty($success_message)): ?>
                        <!-- Steps -->
                        <div class="mb-8">
                            <ol class="relative border-l border-gray-300 dark:border-gray-700 ml-3">
                                <li class="mb-6 ml-6">
                                    <span class="absolute flex items-center justify-center w-8 h-8 bg-netblue-600 rounded-full -left-4 text-white">
                                        1
                                    </span>
                                    <h3 class="font-bold text-gray-800 dark:text-white">Choisissez la durée de votre abonnement</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Sélectionnez le nombre de mois pendant lesquels vous souhaitez accéder à la formation.</p>
                                </li>
                                <li class="mb-6 ml-6">
                                    <span class="absolute flex items-center justify-center w-8 h-8 bg-netblue-600 rounded-full -left-4 text-white">
                                        2
                                    </span>
                                    <h3 class="font-bold text-gray-800 dark:text-white">Effectuez votre paiement</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Utilisez l'une des méthodes de paiement proposées et conservez votre reçu.</p>
                                </li>
                                <li class="mb-6 ml-6">
                                    <span class="absolute flex items-center justify-center w-8 h-8 bg-netblue-600 rounded-full -left-4 text-white">
                                        3
                                    </span>
                                    <h3 class="font-bold text-gray-800 dark:text-white">Téléchargez votre reçu</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Envoyez-nous une copie de votre reçu de paiement pour validation.</p>
                                </li>
                                <li class="ml-6">
                                    <span class="absolute flex items-center justify-center w-8 h-8 bg-netblue-600 rounded-full -left-4 text-white">
                                        4
                                    </span>
                                    <h3 class="font-bold text-gray-800 dark:text-white">Attendez la validation</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Notre équipe validera votre paiement et activera votre accès dans les plus brefs délais.</p>
                                </li>
                            </ol>
                        </div>
                        
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?formation_id=' . $formation_id); ?>" enctype="multipart/form-data" class="space-y-6">
                            <!-- Subscription Duration -->
                            <div>
                                <label for="subscription_months" class="block text-gray-700 dark:text-gray-300 mb-2 font-medium">Durée de l'abonnement</label>
                                <div class="grid grid-cols-3 md:grid-cols-6 gap-3">
                                    <?php for ($i = 1; $i <= 6; $i++): ?>
                                    <div>
                                        <input type="radio" id="months_<?php echo $i; ?>" name="subscription_months" value="<?php echo $i; ?>" class="hidden" <?php echo $i === 3 ? 'checked' : ''; ?>>
                                        <label for="months_<?php echo $i; ?>" class="cursor-pointer block text-center py-2 px-1 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors <?php echo $i === 3 ? 'bg-netblue-50 dark:bg-netblue-900/30 border-netblue-300 dark:border-netblue-700 text-netblue-700 dark:text-netblue-300' : 'text-gray-800 dark:text-gray-300'; ?>">
                                            <div class="font-bold"><?php echo $i; ?></div>
                                            <div class="text-xs">mois</div>
                                        </label>
                                    </div>
                                    <?php endfor; ?>
                                </div>
                                <div class="mt-3 mb-4 bg-gray-100 dark:bg-gray-700 rounded-lg p-4">
                                    <h4 class="font-bold text-gray-800 dark:text-white mb-2">Récapitulatif</h4>
                                    <div class="flex justify-between">
                                        <div class="text-gray-600 dark:text-gray-400">Prix mensuel</div>
                                        <div class="text-gray-800 dark:text-white font-medium"><?php echo number_format($formation['price_per_month'], 0, ',', ' '); ?> FCFA</div>
                                    </div>
                                    <div class="flex justify-between">
                                        <div class="text-gray-600 dark:text-gray-400">Durée sélectionnée</div>
                                        <div class="text-gray-800 dark:text-white font-medium"><span id="selected_months">3</span> mois</div>
                                    </div>
                                    <div class="flex justify-between mt-2 pt-2 border-t border-gray-200 dark:border-gray-600">
                                        <div class="text-gray-800 dark:text-white font-bold">Total à payer</div>
                                        <div class="text-netblue-600 dark:text-netblue-400 font-bold"><span id="total_amount"><?php echo number_format($formation['price_per_month'] * 3, 0, ',', ' '); ?></span> FCFA</div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Payment Method -->
                            <div>
                                <label class="block text-gray-700 dark:text-gray-300 mb-2 font-medium">Méthode de paiement</label>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <!-- Nita -->
                                    <div>
                                        <input type="radio" id="payment_nita" name="payment_method" value="nita" class="payment-method-input hidden" checked>
                                        <label for="payment_nita" class="payment-method-label h-full cursor-pointer block text-center py-4 px-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors bg-white dark:bg-gray-800">
                                            <div class="font-bold text-gray-800 dark:text-white mb-2">Nita</div>
                                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                                <div>+227 90 XX XX XX</div>
                                            </div>
                                        </label>
                                    </div>
                                    
                                    <!-- Amana -->
                                    <div>
                                        <input type="radio" id="payment_amana" name="payment_method" value="amana" class="payment-method-input hidden">
                                        <label for="payment_amana" class="payment-method-label h-full cursor-pointer block text-center py-4 px-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors bg-white dark:bg-gray-800">
                                            <div class="font-bold text-gray-800 dark:text-white mb-2">Amana</div>
                                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                                <div>+227 91 XX XX XX</div>
                                            </div>
                                        </label>
                                    </div>
                                    
                                    <!-- Zeyna -->
                                    <div>
                                        <input type="radio" id="payment_zeyna" name="payment_method" value="zeyna" class="payment-method-input hidden">
                                        <label for="payment_zeyna" class="payment-method-label h-full cursor-pointer block text-center py-4 px-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors bg-white dark:bg-gray-800">
                                            <div class="font-bold text-gray-800 dark:text-white mb-2">Zeyna</div>
                                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                                <div>+227 92 XX XX XX</div>
                                            </div>
                                        </label>
                                    </div>
                                    
                                    <!-- Niya -->
                                    <div>
                                        <input type="radio" id="payment_niya" name="payment_method" value="niya" class="payment-method-input hidden">
                                        <label for="payment_niya" class="payment-method-label h-full cursor-pointer block text-center py-4 px-2 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors bg-white dark:bg-gray-800">
                                            <div class="font-bold text-gray-800 dark:text-white mb-2">Niya</div>
                                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                                <div>+227 93 XX XX XX</div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="mt-4 bg-gray-100 dark:bg-gray-700 rounded-lg p-4" id="payment_instructions">
                                    <h4 class="font-bold text-gray-800 dark:text-white mb-2">Instructions de paiement <span id="selected_payment">Nita</span></h4>
                                    <ol class="list-decimal pl-5 text-gray-600 dark:text-gray-400 space-y-2">
                                        <li>Composez le <span class="font-medium text-gray-800 dark:text-white" id="payment_number">*144#</span> sur votre téléphone</li>
                                        <li>Sélectionnez l'option "Transfert d'argent"</li>
                                        <li>Entrez le numéro de réception : <span class="font-medium text-gray-800 dark:text-white">+227 90 XX XX XX</span></li>
                                        <li>Entrez le montant : <span class="font-medium text-gray-800 dark:text-white" id="payment_amount"><?php echo number_format($formation['price_per_month'] * 3, 0, ',', ' '); ?> FCFA</span></li>
                                        <li>Confirmez le paiement avec votre code PIN</li>
                                        <li>Conservez le reçu ou prenez une capture d'écran de la confirmation</li>
                                    </ol>
                                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">Une fois le paiement effectué, téléchargez ci-dessous une photo ou une capture d'écran de votre reçu de paiement.</p>
                                </div>
                            </div>
                            
                            <!-- Receipt Upload -->
                            <div>
                                <label for="payment_receipt" class="block text-gray-700 dark:text-gray-300 mb-2 font-medium">Télécharger votre reçu de paiement</label>
                                <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center bg-white dark:bg-gray-800" id="drop_zone">
                                    <input type="file" name="payment_receipt" id="payment_receipt" accept=".jpg,.jpeg,.png,.pdf" class="hidden">
                                    <div class="space-y-3">
                                        <div class="mx-auto flex justify-center">
                                            <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 dark:text-gray-500"></i>
                                        </div>
                                        <p class="text-gray-700 dark:text-gray-300">Glissez-déposez votre reçu ici, ou</p>
                                        <button type="button" id="browse_button" class="bg-netblue-600 hover:bg-netblue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                                            Parcourir les fichiers
                                        </button>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            Formats supportés : JPG, JPEG, PNG, PDF (max. 5MB)
                                        </p>
                                    </div>
                                    <div class="hidden mt-4 p-3 bg-gray-100 dark:bg-gray-700 rounded-lg" id="file_preview">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center">
                                                <i class="fas fa-file-alt text-netblue-600 dark:text-netblue-400 mr-3"></i>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-800 dark:text-white" id="file_name">receipt.jpg</p>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400" id="file_size">120 KB</p>
                                                </div>
                                            </div>
                                            <button type="button" id="remove_file" class="text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Terms and Conditions -->
                            <div class="flex items-start mt-6">
                                <div class="flex items-center h-5">
                                    <input id="terms" name="terms" type="checkbox" required class="focus:ring-netblue-500 h-4 w-4 text-netblue-600 border-gray-300 dark:border-gray-600 rounded">
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="terms" class="text-gray-700 dark:text-gray-300">
                                        J'accepte les <a href="#" class="text-netblue-600 dark:text-netblue-400 hover:underline">conditions générales</a> et la <a href="#" class="text-netblue-600 dark:text-netblue-400 hover:underline">politique de confidentialité</a>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Submit Button -->
                            <div class="pt-2">
                                <button type="submit" class="w-full bg-netblue-600 hover:bg-netblue-700 text-white font-bold py-3 px-4 rounded-lg transition-colors">
                                    <?php if ($is_subscribed && $subscription['status'] === 'pending'): ?>
                                        Mettre à jour ma demande d'abonnement
                                    <?php else: ?>
                                        Soumettre ma demande d'abonnement
                                    <?php endif; ?>
                                </button>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Help Section -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 mt-6">
                        <h2 class="text-xl font-bold mb-4 dark:text-white">Besoin d'aide ?</h2>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">
                            Si vous rencontrez des difficultés pour vous abonner à cette formation, ou si vous avez des questions concernant nos méthodes de paiement, n'hésitez pas à nous contacter.
                        </p>
                        <div class="flex flex-col md:flex-row gap-4">
                            <a href="tel:+22790000000" class="flex items-center justify-center bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-800 dark:text-white py-2 px-4 rounded-lg transition-colors">
                                <i class="fas fa-phone-alt mr-2"></i>+227 88 67 21 15
                            </a>
                            <a href="mailto:support@netcrafterniger.com" class="flex items-center justify-center bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-800 dark:text-white py-2 px-4 rounded-lg transition-colors">
                                <i class="fas fa-envelope mr-2"></i>support@netcrafterniger.com
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Simple Footer -->
    <footer class="bg-gray-100 dark:bg-gray-800 py-6">
        <div class="container mx-auto px-4 text-center">
            <p class="text-gray-600 dark:text-gray-400">
                © 2023 Netcrafter. Tous droits réservés.
            </p>
        </div>
    </footer>

    <!-- JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            document.getElementById('menu-toggle').addEventListener('click', function() {
                const mobileMenu = document.getElementById('mobile-menu');
                mobileMenu.classList.toggle('hidden');
            });
            
            // Dark mode toggle
            const darkModeToggle = document.getElementById('darkModeToggle');
            const darkModeToggleMobile = document.getElementById('darkModeToggleMobile');
            const htmlElement = document.documentElement;
            
            // Check for saved theme preference
            if (localStorage.getItem('darkMode') === 'enabled') {
                htmlElement.classList.add('dark');
                darkModeToggle.checked = true;
                darkModeToggleMobile.checked = true;
            }
            
            // Function to toggle dark mode
            function toggleDarkMode() {
                if (htmlElement.classList.contains('dark')) {
                    htmlElement.classList.remove('dark');
                    localStorage.setItem('darkMode', 'disabled');
                    darkModeToggle.checked = false;
                    darkModeToggleMobile.checked = false;
                } else {
                    htmlElement.classList.add('dark');
                    localStorage.setItem('darkMode', 'enabled');
                    darkModeToggle.checked = true;
                    darkModeToggleMobile.checked = true;
                }
            }
            
            // Event listeners for toggle switches
            darkModeToggle.addEventListener('change', toggleDarkMode);
            darkModeToggleMobile.addEventListener('change', toggleDarkMode);
            
            // Subscription month selection
            const monthOptions = document.querySelectorAll('input[name="subscription_months"]');
            const selectedMonths = document.getElementById('selected_months');
            const totalAmount = document.getElementById('total_amount');
            const paymentAmount = document.getElementById('payment_amount');
            const pricePerMonth = <?php echo $formation['price_per_month']; ?>;
            
            function formatNumber(number) {
                return new Intl.NumberFormat('fr-FR').format(number);
            }
            
            monthOptions.forEach(option => {
                option.addEventListener('change', function() {
                    // Update all labels to default style
                    document.querySelectorAll('label[for^="months_"]').forEach(label => {
                        label.classList.remove('bg-netblue-50', 'dark:bg-netblue-900/30', 'border-netblue-300', 'dark:border-netblue-700', 'text-netblue-700', 'dark:text-netblue-300');
                        label.classList.add('text-gray-800', 'dark:text-gray-300');
                    });
                    
                    // Update selected label style
                    const selectedLabel = document.querySelector(`label[for="months_${this.value}"]`);
                    selectedLabel.classList.remove('text-gray-800', 'dark:text-gray-300');
                    selectedLabel.classList.add('bg-netblue-50', 'dark:bg-netblue-900/30', 'border-netblue-300', 'dark:border-netblue-700', 'text-netblue-700', 'dark:text-netblue-300');
                    
                    // Update selected months and total amount
                    selectedMonths.textContent = this.value;
                    const total = pricePerMonth * parseInt(this.value);
                    totalAmount.textContent = formatNumber(total);
                    paymentAmount.textContent = formatNumber(total) + ' FCFA';
                });
            });
            
            // Payment method selection
            const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
            const selectedPayment = document.getElementById('selected_payment');
            const paymentNumber = document.getElementById('payment_number');
            
            const paymentDetails = {
                'nita': { name: 'Nita', number: '*144#' },
                'amana': { name: 'Amana', number: '*700#' },
                'zeyna': { name: 'Zeyna', number: '*222#' },
                'niya': { name: 'Niya', number: '*888#' }
            };
            
            paymentMethods.forEach(method => {
                method.addEventListener('change', function() {
                    selectedPayment.textContent = paymentDetails[this.value].name;
                    paymentNumber.textContent = paymentDetails[this.value].number;
                });
            });
            
            // File upload handling
            const dropZone = document.getElementById('drop_zone');
            const fileInput = document.getElementById('payment_receipt');
            const browseButton = document.getElementById('browse_button');
            const filePreview = document.getElementById('file_preview');
            const fileName = document.getElementById('file_name');
            const fileSize = document.getElementById('file_size');
            const removeFile = document.getElementById('remove_file');
            
            // Convert bytes to human readable format
            function formatFileSize(bytes) {
                const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
                if (bytes === 0) return '0 Byte';
                const i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)));
                return Math.round(bytes / Math.pow(1024, i), 2) + ' ' + sizes[i];
            }
            
            // Display file preview
            function showFilePreview(file) {
                fileName.textContent = file.name;
                fileSize.textContent = formatFileSize(file.size);
                filePreview.classList.remove('hidden');
                dropZone.classList.add('border-netblue-500', 'bg-netblue-50', 'dark:bg-netblue-900/10');
            }
            
            // Clear file preview
            function clearFilePreview() {
                fileInput.value = '';
                filePreview.classList.add('hidden');
                dropZone.classList.remove('border-netblue-500', 'bg-netblue-50', 'dark:bg-netblue-900/10');
            }
            
            // Handle file selection
            fileInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    showFilePreview(file);
                }
            });
            
            // Browse button click
            browseButton.addEventListener('click', function() {
                fileInput.click();
            });
            
            // Remove file button click
            removeFile.addEventListener('click', function() {
                clearFilePreview();
            });
            
            // Drag and drop functionality
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, preventDefaults, false);
            });
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, highlight, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, unhighlight, false);
            });
            
            function highlight() {
                dropZone.classList.add('border-netblue-500', 'bg-netblue-50', 'dark:bg-netblue-900/10');
            }
            
            function unhighlight() {
                if (!fileInput.files.length) {
                    dropZone.classList.remove('border-netblue-500', 'bg-netblue-50', 'dark:bg-netblue-900/10');
                }
            }
            
            dropZone.addEventListener('drop', handleDrop, false);
            
            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                
                if (files.length) {
                    fileInput.files = files;
                    showFilePreview(files[0]);
                }
            }
        });
    </script>
</body>
</html>