<?php
// Titre de la page pour l'inclusion de l'en-tête
$page_title = "Gestion des utilisateurs";

// Inclusion de l'en-tête
require_once 'includes/header.php';

// Vérifier si l'utilisateur a les droits d'administrateur
if (!isset($_SESSION['admin_is_admin']) || $_SESSION['admin_is_admin'] != 1) {
    header("Location: index.php?error=" . urlencode("Vous n'avez pas les droits nécessaires pour accéder à cette page."));
    exit;
}

// Variables pour les messages de succès/erreur
$success_message = isset($_GET['success']) ? $_GET['success'] : '';
$error_message = isset($_GET['error']) ? $_GET['error'] : '';

// Traitement des actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    // Ajouter un nouvel utilisateur
    if ($action === 'add_user') {
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        $is_admin = isset($_POST['is_admin']) ? 1 : 0;
        
        // Validation
        if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
            $error_message = "Tous les champs sont obligatoires.";
        } elseif ($password !== $confirm_password) {
            $error_message = "Les mots de passe ne correspondent pas.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "L'adresse e-mail n'est pas valide.";
        } else {
            // Vérifier si l'utilisateur existe déjà
            $check_query = "SELECT id FROM users WHERE username = ? OR email = ?";
            $stmt = $conn->prepare($check_query);
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error_message = "Ce nom d'utilisateur ou cette adresse e-mail est déjà utilisé.";
            } else {
                // Hasher le mot de passe
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insérer le nouvel utilisateur
                $insert_query = "INSERT INTO users (username, email, password, full_name, phone, is_admin, created_at) 
                                VALUES (?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $conn->prepare($insert_query);
                $stmt->bind_param("sssssi", $username, $email, $hashed_password, $full_name, $phone, $is_admin);
                
                if ($stmt->execute()) {
                    $success_message = "L'utilisateur a été ajouté avec succès.";
                    // Rediriger pour éviter la resoumission du formulaire
                    header("Location: users.php?success=" . urlencode($success_message));
                    exit;
                } else {
                    $error_message = "Erreur lors de l'ajout de l'utilisateur : " . $conn->error;
                }
            }
        }
    }
    
    // Mettre à jour un utilisateur
    elseif ($action === 'update_user') {
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        $is_admin = isset($_POST['is_admin']) ? 1 : 0;
        $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirm_new_password = isset($_POST['confirm_new_password']) ? $_POST['confirm_new_password'] : '';
        
        // Validation
        if (empty($email)) {
            $error_message = "L'adresse e-mail est obligatoire.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "L'adresse e-mail n'est pas valide.";
        } elseif (!empty($new_password) && $new_password !== $confirm_new_password) {
            $error_message = "Les mots de passe ne correspondent pas.";
        } else {
            // Vérifier si l'email existe déjà chez un autre utilisateur
            $check_query = "SELECT id FROM users WHERE email = ? AND id != ?";
            $stmt = $conn->prepare($check_query);
            $stmt->bind_param("si", $email, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error_message = "Cette adresse e-mail est déjà utilisée par un autre utilisateur.";
            } else {
                // Préparation des paramètres de mise à jour
                $update_fields = "email = ?, full_name = ?, phone = ?, is_admin = ?";
                $param_types = "sssi";
                $params = [$email, $full_name, $phone, $is_admin];
                
                // Ajouter la mise à jour du mot de passe si nécessaire
                if (!empty($new_password)) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_fields .= ", password = ?";
                    $param_types .= "s";
                    $params[] = $hashed_password;
                }
                
                // Ajouter l'id de l'utilisateur
                $param_types .= "i";
                $params[] = $user_id;
                
                $update_query = "UPDATE users SET $update_fields WHERE id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param($param_types, ...$params);
                
                if ($stmt->execute()) {
                    $success_message = "Les informations de l'utilisateur ont été mises à jour avec succès.";
                    header("Location: users.php?success=" . urlencode($success_message));
                    exit;
                } else {
                    $error_message = "Erreur lors de la mise à jour de l'utilisateur : " . $conn->error;
                }
            }
        }
    }
    
    // Supprimer un utilisateur
    elseif ($action === 'delete_user') {
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        
        // Ne pas permettre à un utilisateur de se supprimer lui-même
        if ($user_id === $_SESSION['admin_id']) {
            $error_message = "Vous ne pouvez pas supprimer votre propre compte.";
        } else {
            $delete_query = "DELETE FROM users WHERE id = ?";
            $stmt = $conn->prepare($delete_query);
            $stmt->bind_param("i", $user_id);
            
            if ($stmt->execute()) {
                $success_message = "L'utilisateur a été supprimé avec succès.";
                header("Location: users.php?success=" . urlencode($success_message));
                exit;
            } else {
                $error_message = "Erreur lors de la suppression de l'utilisateur : " . $conn->error;
            }
        }
    }
}

// Récupérer la liste des utilisateurs administrateurs
$query = "SELECT id, username, email, full_name, phone, is_admin, created_at, last_login 
          FROM users 
          WHERE is_admin = 1 OR id = ?
          ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['admin_id']);
$stmt->execute();
$result = $stmt->get_result();
$users = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
?>

<!-- Main Content Section -->
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

    <!-- Users Management Card -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm">
        <!-- Card Header with Add User Button -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between border-b border-gray-200 dark:border-gray-700 p-4 md:p-6">
            <div>
                <h2 class="text-xl font-bold text-gray-800 dark:text-white">Utilisateurs administrateurs</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Gérez les accès à l'interface d'administration</p>
            </div>
            <button id="add-user-button" class="mt-3 md:mt-0 inline-flex items-center px-4 py-2 bg-netblue-600 border border-transparent rounded-md font-semibold text-white hover:bg-netblue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-netblue-500 transition-colors">
                <i class="fas fa-user-plus mr-2"></i>
                Ajouter un utilisateur
            </button>
        </div>

        <!-- Users Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Utilisateur
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Rôle
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Contact
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Dernière connexion
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Créé le
                        </th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                            Aucun utilisateur trouvé
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <img class="h-10 w-10 rounded-full" src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['username']); ?>&background=3B82F6&color=fff" alt="Profile">
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            <?php echo htmlspecialchars($user['username']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            <?php echo htmlspecialchars($user['email']); ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php if ($user['is_admin'] == 1): ?>
                                        bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200
                                    <?php else: ?>
                                        bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                    <?php endif; ?>">
                                    <?php echo $user['is_admin'] == 1 ? 'Administrateur' : 'Éditeur'; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900 dark:text-white">
                                    <?php echo htmlspecialchars($user['full_name'] ?? 'Non défini'); ?>
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    <?php echo htmlspecialchars($user['phone'] ?? 'Non défini'); ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                <?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Jamais'; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                <?php echo date('d/m/Y', strtotime($user['created_at'])); ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex justify-end space-x-2">
                                    <button type="button" class="edit-user-button text-netblue-600 hover:text-netblue-900 dark:text-netblue-400 dark:hover:text-netblue-200" data-id="<?php echo $user['id']; ?>" data-username="<?php echo htmlspecialchars($user['username']); ?>" data-email="<?php echo htmlspecialchars($user['email']); ?>" data-fullname="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" data-phone="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" data-isadmin="<?php echo $user['is_admin']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($user['id'] !== $_SESSION['admin_id']): ?>
                                    <button type="button" class="delete-user-button text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-200" data-id="<?php echo $user['id']; ?>" data-username="<?php echo htmlspecialchars($user['username']); ?>">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div id="add-user-modal" class="fixed inset-0 z-50 overflow-auto bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6 transform transition-all">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Ajouter un nouvel utilisateur</h3>
            <button id="close-add-modal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="add-user-form" method="POST" action="users.php">
            <input type="hidden" name="action" value="add_user">
            
            <div class="space-y-4">
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nom d'utilisateur <span class="text-red-500">*</span></label>
                    <input type="text" id="username" name="username" required class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-netblue-500 focus:border-netblue-500 sm:text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                </div>
                
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Adresse e-mail <span class="text-red-500">*</span></label>
                    <input type="email" id="email" name="email" required class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-netblue-500 focus:border-netblue-500 sm:text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                </div>
                
                <div>
                    <label for="full_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nom complet</label>
                    <input type="text" id="full_name" name="full_name" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-netblue-500 focus:border-netblue-500 sm:text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                </div>
                
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Téléphone</label>
                    <input type="text" id="phone" name="phone" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-netblue-500 focus:border-netblue-500 sm:text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                </div>
                
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Mot de passe <span class="text-red-500">*</span></label>
                    <input type="password" id="password" name="password" required class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-netblue-500 focus:border-netblue-500 sm:text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                </div>
                
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Confirmer le mot de passe <span class="text-red-500">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" required class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-netblue-500 focus:border-netblue-500 sm:text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                </div>
                
                <div class="flex items-center">
                    <input type="checkbox" id="is_admin" name="is_admin" value="1" class="h-4 w-4 text-netblue-600 focus:ring-netblue-500 border-gray-300 dark:border-gray-600 rounded">
                    <label for="is_admin" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                        Administrateur
                    </label>
                </div>
            </div>
            
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" id="cancel-add" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                    Annuler
                </button>
                <button type="submit" class="px-4 py-2 bg-netblue-600 text-white rounded-md hover:bg-netblue-700 transition-colors">
                    Ajouter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="edit-user-modal" class="fixed inset-0 z-50 overflow-auto bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6 transform transition-all">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Modifier l'utilisateur</h3>
            <button id="close-edit-modal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form id="edit-user-form" method="POST" action="users.php">
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" id="edit_user_id" name="user_id" value="">
            
            <div class="space-y-4">
                <div>
                    <label for="edit_username" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nom d'utilisateur</label>
                    <input type="text" id="edit_username" name="username" disabled class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-gray-100 dark:bg-gray-600 text-gray-700 dark:text-gray-300">
                </div>
                
                <div>
                    <label for="edit_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Adresse e-mail <span class="text-red-500">*</span></label>
                    <input type="email" id="edit_email" name="email" required class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-netblue-500 focus:border-netblue-500 sm:text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                </div>
                
                <div>
                    <label for="edit_full_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nom complet</label>
                    <input type="text" id="edit_full_name" name="full_name" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-netblue-500 focus:border-netblue-500 sm:text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                </div>
                
                <div>
                    <label for="edit_phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Téléphone</label>
                    <input type="text" id="edit_phone" name="phone" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-netblue-500 focus:border-netblue-500 sm:text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                </div>
                
                <div class="flex items-center">
                    <input type="checkbox" id="edit_is_admin" name="is_admin" value="1" class="h-4 w-4 text-netblue-600 focus:ring-netblue-500 border-gray-300 dark:border-gray-600 rounded">
                    <label for="edit_is_admin" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                        Administrateur
                    </label>
                </div>
                
                <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Changer le mot de passe</h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Laissez vide pour conserver le mot de passe actuel</p>
                    
                    <div>
                        <label for="new_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nouveau mot de passe</label>
                        <input type="password" id="new_password" name="new_password" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-netblue-500 focus:border-netblue-500 sm:text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                    </div>
                    
                    <div class="mt-3">
                        <label for="confirm_new_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Confirmer le nouveau mot de passe</label>
                        <input type="password" id="confirm_new_password" name="confirm_new_password" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-netblue-500 focus:border-netblue-500 sm:text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                    </div>
                </div>
            </div>
            
            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" id="cancel-edit" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                    Annuler
                </button>
                <button type="submit" class="px-4 py-2 bg-netblue-600 text-white rounded-md hover:bg-netblue-700 transition-colors">
                    Mettre à jour
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete User Modal -->
<div id="delete-user-modal" class="fixed inset-0 z-50 overflow-auto bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6 transform transition-all">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Confirmer la suppression</h3>
            <button id="close-delete-modal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="mb-6">
            <p class="text-gray-700 dark:text-gray-300">Êtes-vous sûr de vouloir supprimer l'utilisateur <span id="delete-username" class="font-semibold"></span> ? Cette action est irréversible.</p>
        </div>
        <form id="delete-user-form" method="POST" action="users.php">
            <input type="hidden" name="action" value="delete_user">
            <input type="hidden" id="delete_user_id" name="user_id" value="">
            
            <div class="flex justify-end space-x-3">
                <button type="button" id="cancel-delete" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                    Annuler
                </button>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                    Supprimer
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add User Modal
    const addUserButton = document.getElementById('add-user-button');
    const addUserModal = document.getElementById('add-user-modal');
    const closeAddModalButton = document.getElementById('close-add-modal');
    const cancelAddButton = document.getElementById('cancel-add');
    
    addUserButton.addEventListener('click', function() {
        addUserModal.classList.remove('hidden');
    });
    
    const closeAddModal = function() {
        addUserModal.classList.add('hidden');
        document.getElementById('add-user-form').reset();
    };
    
    closeAddModalButton.addEventListener('click', closeAddModal);
    cancelAddButton.addEventListener('click', closeAddModal);
    
    // Edit User Modal
    const editButtons = document.querySelectorAll('.edit-user-button');
    const editUserModal = document.getElementById('edit-user-modal');
    const closeEditModalButton = document.getElementById('close-edit-modal');
    const cancelEditButton = document.getElementById('cancel-edit');
    
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-id');
            const username = this.getAttribute('data-username');
            const email = this.getAttribute('data-email');
            const fullName = this.getAttribute('data-fullname');
            const phone = this.getAttribute('data-phone');
            const isAdmin = this.getAttribute('data-isadmin') === '1';
            
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_full_name').value = fullName;
            document.getElementById('edit_phone').value = phone;
            document.getElementById('edit_is_admin').checked = isAdmin;
            
            // Clear password fields
            document.getElementById('new_password').value = '';
            document.getElementById('confirm_new_password').value = '';
            
            editUserModal.classList.remove('hidden');
        });
    });
    
    const closeEditModal = function() {
        editUserModal.classList.add('hidden');
    };
    
    closeEditModalButton.addEventListener('click', closeEditModal);
    cancelEditButton.addEventListener('click', closeEditModal);
    
    // Delete User Modal
    const deleteButtons = document.querySelectorAll('.delete-user-button');
    const deleteUserModal = document.getElementById('delete-user-modal');
    const closeDeleteModalButton = document.getElementById('close-delete-modal');
    const cancelDeleteButton = document.getElementById('cancel-delete');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-id');
            const username = this.getAttribute('data-username');
            
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('delete-username').textContent = username;
            
            deleteUserModal.classList.remove('hidden');
        });
    });
    
    const closeDeleteModal = function() {
        deleteUserModal.classList.add('hidden');
    };
    
    closeDeleteModalButton.addEventListener('click', closeDeleteModal);
    cancelDeleteButton.addEventListener('click', closeDeleteModal);
    
    // Password confirmation validation
    const addUserForm = document.getElementById('add-user-form');
    addUserForm.addEventListener('submit', function(e) {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        if (password !== confirmPassword) {
            e.preventDefault();
            alert('Les mots de passe ne correspondent pas.');
        }
    });
    
    const editUserForm = document.getElementById('edit-user-form');
    editUserForm.addEventListener('submit', function(e) {
        const newPassword = document.getElementById('new_password').value;
        const confirmNewPassword = document.getElementById('confirm_new_password').value;
        
        if (newPassword && newPassword !== confirmNewPassword) {
            e.preventDefault();
            alert('Les nouveaux mots de passe ne correspondent pas.');
        }
    });
    
    // Close modals when clicking outside
    window.addEventListener('click', function(e) {
        if (e.target === addUserModal) {
            closeAddModal();
        }
        if (e.target === editUserModal) {
            closeEditModal();
        }
        if (e.target === deleteUserModal) {
            closeDeleteModal();
        }
    });
});
</script>

<?php
// Inclusion du pied de page
require_once 'includes/footer.php';
?>