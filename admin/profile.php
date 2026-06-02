<?php
// Titre de la page pour l'inclusion de l'en-tête
$page_title = "Mon profil";

// Inclusion de l'en-tête
require_once 'includes/header.php';

// Récupérer les informations de l'utilisateur actuel
$user_id = $_SESSION['admin_id'];
$query = "SELECT username, email, full_name, phone, is_admin, created_at, last_login FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Traitement du formulaire de mise à jour du profil
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        $current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
        $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        
        // Validation
        if (empty($email)) {
            $error_message = "L'adresse e-mail est requise.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "L'adresse e-mail n'est pas valide.";
        } elseif (!empty($new_password) && empty($current_password)) {
            $error_message = "Le mot de passe actuel est requis pour changer le mot de passe.";
        } elseif (!empty($new_password) && $new_password !== $confirm_password) {
            $error_message = "Les nouveaux mots de passe ne correspondent pas.";
        } else {
            // Vérifier si l'email est déjà utilisé par un autre utilisateur
            $check_query = "SELECT id FROM users WHERE email = ? AND id != ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("si", $email, $user_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error_message = "Cette adresse e-mail est déjà utilisée par un autre utilisateur.";
            } else {
                // Si un nouveau mot de passe est fourni, vérifier le mot de passe actuel
                if (!empty($new_password)) {
                    // Récupérer le mot de passe actuel de la base de données
                    $password_query = "SELECT password FROM users WHERE id = ?";
                    $password_stmt = $conn->prepare($password_query);
                    $password_stmt->bind_param("i", $user_id);
                    $password_stmt->execute();
                    $password_result = $password_stmt->get_result();
                    $password_row = $password_result->fetch_assoc();
                    
                    // Vérifier si le mot de passe actuel est correct
                    if (!password_verify($current_password, $password_row['password'])) {
                        $error_message = "Le mot de passe actuel est incorrect.";
                    } else {
                        // Hasher le nouveau mot de passe
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        
                        // Mettre à jour l'email, le nom complet, le téléphone et le mot de passe
                        $update_query = "UPDATE users SET email = ?, full_name = ?, phone = ?, password = ? WHERE id = ?";
                        $update_stmt = $conn->prepare($update_query);
                        $update_stmt->bind_param("ssssi", $email, $full_name, $phone, $hashed_password, $user_id);
                        
                        if ($update_stmt->execute()) {
                            $success_message = "Votre profil a été mis à jour avec succès.";
                            // Mettre à jour les données en session
                            $_SESSION['admin_email'] = $email;
                            // Mettre à jour les informations affichées
                            $user['email'] = $email;
                            $user['full_name'] = $full_name;
                            $user['phone'] = $phone;
                        } else {
                            $error_message = "Une erreur est survenue lors de la mise à jour de votre profil.";
                        }
                    }
                } else {
                    // Mettre à jour uniquement l'email, le nom complet et le téléphone
                    $update_query = "UPDATE users SET email = ?, full_name = ?, phone = ? WHERE id = ?";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->bind_param("sssi", $email, $full_name, $phone, $user_id);
                    
                    if ($update_stmt->execute()) {
                        $success_message = "Votre profil a été mis à jour avec succès.";
                        // Mettre à jour les données en session
                        $_SESSION['admin_email'] = $email;
                        // Mettre à jour les informations affichées
                        $user['email'] = $email;
                        $user['full_name'] = $full_name;
                        $user['phone'] = $phone;
                    } else {
                        $error_message = "Une erreur est survenue lors de la mise à jour de votre profil.";
                    }
                }
            }
        }
    }
}
?>

<div class="space-y-6">
    <!-- Alert Messages -->
    <?php if (!empty($success_message)): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded" role="alert">
        <p><?php echo htmlspecialchars($success_message); ?></p>
    </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded" role="alert">
        <p><?php echo htmlspecialchars($error_message); ?></p>
    </div>
    <?php endif; ?>

    <!-- Profile and Update Password -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- User Info Card -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
            <div class="flex flex-col items-center text-center">
                <div class="w-24 h-24 rounded-full overflow-hidden bg-gray-200 dark:bg-gray-700 mb-4">
                    <img class="h-full w-full object-cover" src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['username']); ?>&background=3B82F6&color=fff&size=256" alt="Profile">
                </div>
                <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-1"><?php echo htmlspecialchars($user['username']); ?></h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
                    <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                        <?php if ($user['is_admin'] == 1): ?>
                            bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200
                        <?php else: ?>
                            bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                        <?php endif; ?>">
                        <?php echo $user['is_admin'] == 1 ? 'Administrateur' : 'Éditeur'; ?>
                    </span>
                </p>
                <div class="w-full border-t border-gray-200 dark:border-gray-700 pt-4 mt-2">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Email:</span>
                        <span class="text-sm text-gray-900 dark:text-white font-medium"><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Nom complet:</span>
                        <span class="text-sm text-gray-900 dark:text-white font-medium"><?php echo htmlspecialchars($user['full_name'] ?? 'Non défini'); ?></span>
                    </div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Téléphone:</span>
                        <span class="text-sm text-gray-900 dark:text-white font-medium"><?php echo htmlspecialchars($user['phone'] ?? 'Non défini'); ?></span>
                    </div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Compte créé le:</span>
                        <span class="text-sm text-gray-900 dark:text-white font-medium"><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-500 dark:text-gray-400">Dernière connexion:</span>
                        <span class="text-sm text-gray-900 dark:text-white font-medium">
                            <?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Jamais'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Update Profile Form -->
        <div class="md:col-span-2 bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Modifier mon profil</h3>
            
            <form method="POST" action="profile.php">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="space-y-4">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nom d'utilisateur</label>
                        <input type="text" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Le nom d'utilisateur ne peut pas être modifié.</p>
                    </div>
                    
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Adresse e-mail <span class="text-red-500">*</span></label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="full_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nom complet</label>
                        <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                    </div>
                    
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Téléphone</label>
                        <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                    </div>
                    
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-4 mt-4">
                        <h4 class="text-md font-medium text-gray-900 dark:text-white mb-3">Changer le mot de passe</h4>
                        
                        <div class="mb-3">
                            <label for="current_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Mot de passe actuel</label>
                            <input type="password" id="current_password" name="current_password" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Requis uniquement si vous souhaitez changer de mot de passe.</p>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nouveau mot de passe</label>
                            <input type="password" id="new_password" name="new_password" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                        </div>
                        
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirmer le nouveau mot de passe</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-netblue-500 focus:border-netblue-500 bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                        </div>
                    </div>
                </div>
                
                <div class="mt-6">
                    <button type="submit" class="px-4 py-2 bg-netblue-600 text-white rounded-md hover:bg-netblue-700 transition-colors">
                        Mettre à jour le profil
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validation du formulaire
    const profileForm = document.querySelector('form');
    
    profileForm.addEventListener('submit', function(e) {
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        const currentPassword = document.getElementById('current_password').value;
        
        if (newPassword && newPassword !== confirmPassword) {
            e.preventDefault();
            alert('Les nouveaux mots de passe ne correspondent pas.');
        }
        
        if (newPassword && !currentPassword) {
            e.preventDefault();
            alert('Veuillez entrer votre mot de passe actuel pour confirmer le changement.');
        }
    });
});
</script>

<?php
// Inclusion du pied de page
require_once 'includes/footer.php';
?>