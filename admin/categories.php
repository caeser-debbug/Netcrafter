<?php
// Titre de la page pour l'inclusion de l'en-tête
$page_title = "Gestion des catégories";

// Inclusion de l'en-tête
require_once 'includes/header.php';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ajout d'une catégorie
    if (isset($_POST['action']) && $_POST['action'] === 'add_category') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $parent_id = isset($_POST['parent_id']) && $_POST['parent_id'] > 0 ? intval($_POST['parent_id']) : null;
        
        // Validation
        if (empty($name)) {
            header("Location: categories.php?error=" . urlencode("Le nom de la catégorie est obligatoire."));
            exit;
        }
        
        // Génération du slug
        $slug = generateSlug($name);
        
        // Vérification de l'unicité du slug
        $check_slug_query = "SELECT id FROM categories WHERE slug = ?";
        $stmt = $conn->prepare($check_slug_query);
        $stmt->bind_param("s", $slug);
        $stmt->execute();
        $check_result = $stmt->get_result();
        
        if ($check_result && $check_result->num_rows > 0) {
            // Ajouter un suffixe unique au slug
            $slug = $slug . '-' . time();
        }
        
        // Téléchargement de l'image (si fournie)
        $image_url = null;
        if (!empty($_FILES['image']['name'])) {
            $upload_result = uploadImage($_FILES['image'], '../uploads/categories/');
            
            if ($upload_result['success']) {
                $image_url = $upload_result['file_path'];
            } else {
                header("Location: categories.php?error=" . urlencode($upload_result['message']));
                exit;
            }
        }
        
        // Insertion de la catégorie
        $insert_query = "INSERT INTO categories (name, description, image_url, parent_id, slug) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("sssss", $name, $description, $image_url, $parent_id, $slug);
        $result = $stmt->execute();
        
        if ($result) {
            header("Location: categories.php?success=" . urlencode("La catégorie a été ajoutée avec succès."));
            exit;
        } else {
            header("Location: categories.php?error=" . urlencode("Une erreur est survenue lors de l'ajout de la catégorie."));
            exit;
        }
    }
    
    // Modification d'une catégorie
    if (isset($_POST['action']) && $_POST['action'] === 'edit_category') {
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $parent_id = isset($_POST['parent_id']) && $_POST['parent_id'] > 0 ? intval($_POST['parent_id']) : null;
        
        // Validation
        if (empty($name)) {
            header("Location: categories.php?error=" . urlencode("Le nom de la catégorie est obligatoire."));
            exit;
        }
        
        if ($category_id === 0) {
            header("Location: categories.php?error=" . urlencode("ID de catégorie invalide."));
            exit;
        }
        
        // Vérifier que la catégorie existe
        $check_query = "SELECT id, image_url FROM categories WHERE id = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $check_result = $stmt->get_result();
        
        if ($check_result && $check_result->num_rows === 0) {
            header("Location: categories.php?error=" . urlencode("Catégorie introuvable."));
            exit;
        }
        
        $category = $check_result->fetch_assoc();
        $current_image = $category['image_url'];
        
        // Éviter les références circulaires parent-enfant
        if ($parent_id === $category_id) {
            header("Location: categories.php?error=" . urlencode("Une catégorie ne peut pas être son propre parent."));
            exit;
        }
        
        // Téléchargement de l'image (si fournie)
        $image_url = $current_image;
        if (!empty($_FILES['image']['name'])) {
            $upload_result = uploadImage($_FILES['image'], '../uploads/categories/');
            
            if ($upload_result['success']) {
                $image_url = $upload_result['file_path'];
                
                // Supprimer l'ancienne image si elle existe
                if (!empty($current_image) && file_exists('../' . $current_image)) {
                    unlink('../' . $current_image);
                }
            } else {
                header("Location: categories.php?error=" . urlencode($upload_result['message']));
                exit;
            }
        }
        
        // Mise à jour de la catégorie
        $update_query = "UPDATE categories SET name = ?, description = ?, image_url = ?, parent_id = ? WHERE id = ?";
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("ssssi", $name, $description, $image_url, $parent_id, $category_id);
        $result = $stmt->execute();
        
        if ($result) {
            header("Location: categories.php?success=" . urlencode("La catégorie a été mise à jour avec succès."));
            exit;
        } else {
            header("Location: categories.php?error=" . urlencode("Une erreur est survenue lors de la mise à jour de la catégorie."));
            exit;
        }
    }
    
    // Suppression d'une catégorie
    if (isset($_POST['action']) && $_POST['action'] === 'delete_category') {
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        
        if ($category_id === 0) {
            header("Location: categories.php?error=" . urlencode("ID de catégorie invalide."));
            exit;
        }
        
        // Vérifier si la catégorie a des produits
        $check_products_query = "SELECT COUNT(*) as count FROM products WHERE category_id = ?";
        $stmt = $conn->prepare($check_products_query);
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $products_result = $stmt->get_result();
        $products_count = $products_result->fetch_assoc()['count'];
        
        if ($products_count > 0) {
            header("Location: categories.php?error=" . urlencode("Impossible de supprimer cette catégorie car elle contient des produits."));
            exit;
        }
        
        // Vérifier si la catégorie a des sous-catégories
        $check_subcategories_query = "SELECT COUNT(*) as count FROM categories WHERE parent_id = ?";
        $stmt = $conn->prepare($check_subcategories_query);
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $subcategories_result = $stmt->get_result();
        $subcategories_count = $subcategories_result->fetch_assoc()['count'];
        
        if ($subcategories_count > 0) {
            header("Location: categories.php?error=" . urlencode("Impossible de supprimer cette catégorie car elle contient des sous-catégories."));
            exit;
        }
        
        // Récupérer l'image pour la supprimer
        $get_image_query = "SELECT image_url FROM categories WHERE id = ?";
        $stmt = $conn->prepare($get_image_query);
        $stmt->bind_param("i", $category_id);
        $stmt->execute();
        $image_result = $stmt->get_result();
        
        if ($image_result && $image_result->num_rows > 0) {
            $image_url = $image_result->fetch_assoc()['image_url'];
            
            // Supprimer l'image si elle existe
            if (!empty($image_url) && file_exists('../' . $image_url)) {
                unlink('../' . $image_url);
            }
        }
        
        // Suppression de la catégorie
        $delete_query = "DELETE FROM categories WHERE id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param("i", $category_id);
        $result = $stmt->execute();
        
        if ($result) {
            header("Location: categories.php?success=" . urlencode("La catégorie a été supprimée avec succès."));
            exit;
        } else {
            header("Location: categories.php?error=" . urlencode("Une erreur est survenue lors de la suppression de la catégorie."));
            exit;
        }
    }
}

// Récupération des catégories
$categories_query = "SELECT c.*, 
                    (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count,
                    p.name as parent_name
                    FROM categories c
                    LEFT JOIN categories p ON c.parent_id = p.id
                    ORDER BY c.name";
$categories_result = $conn->query($categories_query);
$categories = [];

if ($categories_result && $categories_result->num_rows > 0) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Construction de l'arborescence des catégories (pour les select)
function buildCategoryTree($categories, $parent_id = null, $prefix = '') {
    $tree = [];
    
    foreach ($categories as $category) {
        if ($category['parent_id'] == $parent_id) {
            $category['name_with_prefix'] = $prefix . $category['name'];
            $tree[] = $category;
            
            // Récursion pour les enfants
            $children = buildCategoryTree($categories, $category['id'], $prefix . '— ');
            $tree = array_merge($tree, $children);
        }
    }
    
    return $tree;
}

$categories_tree = buildCategoryTree($categories);
?>

<!-- Categories Management Content -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <!-- Add/Edit Category Form -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6" id="form-title">Ajouter une catégorie</h2>
        
        <form action="categories.php" method="POST" enctype="multipart/form-data" id="category-form">
            <input type="hidden" name="action" value="add_category" id="form-action">
            <input type="hidden" name="category_id" value="0" id="category-id">
            
            <div class="space-y-4">
                <!-- Category Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Nom <span class="text-red-500">*</span></label>
                    <input type="text" id="name" name="name" required class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-netblue-500 focus:ring-netblue-500 dark:bg-gray-700 dark:text-white">
                </div>
                
                <!-- Parent Category -->
                <div>
                    <label for="parent_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Catégorie parente</label>
                    <select id="parent_id" name="parent_id" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-netblue-500 focus:ring-netblue-500 dark:bg-gray-700 dark:text-white">
                        <option value="0">Aucune (catégorie principale)</option>
                        <?php foreach ($categories_tree as $category): ?>
                        <option value="<?php echo $category['id']; ?>">
                            <?php echo htmlspecialchars($category['name_with_prefix']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Category Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Description</label>
                    <textarea id="description" name="description" rows="3" class="block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-netblue-500 focus:ring-netblue-500 dark:bg-gray-700 dark:text-white"></textarea>
                </div>
                
                <!-- Category Image -->
                <div>
                    <label for="image" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Image</label>
                    <div class="mt-1 flex items-center">
                        <div id="image-preview" class="hidden mr-3 h-16 w-16 rounded-md border border-gray-300 dark:border-gray-600 overflow-hidden bg-gray-100 dark:bg-gray-700">
                            <img id="preview-img" src="" alt="Aperçu" class="h-full w-full object-cover">
                        </div>
                        <input type="file" id="image" name="image" accept="image/*" class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-netblue-50 file:text-netblue-600 dark:file:bg-netblue-900 dark:file:text-netblue-400 hover:file:bg-netblue-100 dark:hover:file:bg-netblue-800">
                    </div>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" id="current-image-text"></p>
                </div>
                
                <!-- Form Actions -->
                <div class="flex justify-between pt-4">
                    <button type="button" id="cancel-edit" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors hidden">
                        Annuler
                    </button>
                    <button type="submit" id="submit-button" class="px-4 py-2 bg-netblue-600 text-white rounded-md hover:bg-netblue-700 transition-colors">
                        Ajouter
                    </button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Categories List -->
    <div class="md:col-span-2 bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6">Liste des catégories</h2>
        
        <?php if (empty($categories)): ?>
        <!-- Empty State -->
        <div class="text-center py-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-400 dark:text-gray-500 mb-4">
                <i class="fas fa-folder-open text-2xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Aucune catégorie</h3>
            <p class="text-gray-500 dark:text-gray-400 max-w-md mx-auto">
                Vous n'avez pas encore ajouté de catégories. Commencez par ajouter votre première catégorie.
            </p>
        </div>
        <?php else: ?>
        <!-- Categories Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Image
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Nom
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Parent
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Produits
                        </th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($categories as $category): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="h-12 w-12 rounded-md overflow-hidden bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                <?php if (!empty($category['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars('../' . $category['image_url']); ?>" alt="<?php echo htmlspecialchars($category['name']); ?>" class="h-full w-full object-cover">
                                <?php else: ?>
                                <i class="fas fa-folder text-gray-400 text-xl"></i>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </div>
                            <?php if (!empty($category['description'])): ?>
                            <div class="text-xs text-gray-500 dark:text-gray-400 max-w-xs truncate">
                                <?php echo htmlspecialchars($category['description']); ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm text-gray-900 dark:text-white">
                                <?php echo !empty($category['parent_name']) ? htmlspecialchars($category['parent_name']) : '<span class="text-gray-400 dark:text-gray-500">-</span>'; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                <?php echo $category['product_count']; ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex space-x-2">
                                <button type="button" class="edit-category-btn text-netblue-600 hover:text-netblue-900 dark:text-netblue-400 dark:hover:text-netblue-200" title="Modifier" data-id="<?php echo $category['id']; ?>" data-name="<?php echo htmlspecialchars($category['name']); ?>" data-parent="<?php echo $category['parent_id'] ?? '0'; ?>" data-description="<?php echo htmlspecialchars($category['description']); ?>" data-image="<?php echo htmlspecialchars($category['image_url']); ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" action="categories.php" class="inline-block delete-form">
                                    <input type="hidden" name="action" value="delete_category">
                                    <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                    <button type="button" class="delete-button text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-200 ml-3" title="Supprimer" <?php echo $category['product_count'] > 0 ? 'disabled' : ''; ?>>
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Confirmation Modal for Delete -->
<div id="delete-confirmation-modal" class="fixed inset-0 z-50 overflow-auto bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6 transform transition-all">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Confirmer la suppression</h3>
            <button id="close-delete-modal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="mb-6">
            <p class="text-gray-700 dark:text-gray-300">Êtes-vous sûr de vouloir supprimer cette catégorie ? Cette action est irréversible.</p>
        </div>
        <div class="flex justify-end space-x-3">
            <button id="cancel-delete" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                Annuler
            </button>
            <button id="confirm-delete" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors">
                Supprimer
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Image preview functionality
    const imageInput = document.getElementById('image');
    const imagePreview = document.getElementById('image-preview');
    const previewImage = document.getElementById('preview-img');
    const currentImageText = document.getElementById('current-image-text');
    
    imageInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                previewImage.src = e.target.result;
                imagePreview.classList.remove('hidden');
                currentImageText.textContent = '';
            };
            
            reader.readAsDataURL(this.files[0]);
        } else {
            imagePreview.classList.add('hidden');
        }
    });
    
    // Edit category functionality
    const editButtons = document.querySelectorAll('.edit-category-btn');
    const categoryForm = document.getElementById('category-form');
    const formAction = document.getElementById('form-action');
    const formTitle = document.getElementById('form-title');
    const submitButton = document.getElementById('submit-button');
    const categoryId = document.getElementById('category-id');
    const nameInput = document.getElementById('name');
    const descriptionInput = document.getElementById('description');
    const parentSelect = document.getElementById('parent_id');
    const cancelEditButton = document.getElementById('cancel-edit');
    
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const parent = this.getAttribute('data-parent') || '0';
            const description = this.getAttribute('data-description') || '';
            const image = this.getAttribute('data-image') || '';
            
            // Update form
            formAction.value = 'edit_category';
            formTitle.textContent = 'Modifier la catégorie';
            submitButton.textContent = 'Mettre à jour';
            categoryId.value = id;
            nameInput.value = name;
            descriptionInput.value = description;
            parentSelect.value = parent;
            
            // Disable the parent option that is the category itself (to avoid circular references)
            Array.from(parentSelect.options).forEach(option => {
                if (option.value === id) {
                    option.disabled = true;
                } else {
                    option.disabled = false;
                }
            });
            
            // Update image preview
            if (image) {
                currentImageText.textContent = 'Image actuelle : ' + image.split('/').pop();
                imagePreview.classList.add('hidden');
            } else {
                currentImageText.textContent = 'Aucune image';
                imagePreview.classList.add('hidden');
            }
            
            // Show cancel button
            cancelEditButton.classList.remove('hidden');
            
            // Scroll to form
            categoryForm.scrollIntoView({ behavior: 'smooth' });
        });
    });
    
    // Cancel edit
    cancelEditButton.addEventListener('click', function() {
        // Reset form
        formAction.value = 'add_category';
        formTitle.textContent = 'Ajouter une catégorie';
        submitButton.textContent = 'Ajouter';
        categoryForm.reset();
        categoryId.value = '0';
        
        // Enable all parent options
        Array.from(parentSelect.options).forEach(option => {
            option.disabled = false;
        });
        
        // Hide image preview
        imagePreview.classList.add('hidden');
        currentImageText.textContent = '';
        
        // Hide cancel button
        this.classList.add('hidden');
    });
    
    // Delete confirmation
    const deleteButtons = document.querySelectorAll('.delete-button');
    const deleteModal = document.getElementById('delete-confirmation-modal');
    const closeDeleteModalButton = document.getElementById('close-delete-modal');
    const cancelDeleteButton = document.getElementById('cancel-delete');
    const confirmDeleteButton = document.getElementById('confirm-delete');
    let currentForm = null;
    
    deleteButtons.forEach(button => {
        if (!button.disabled) {
            button.addEventListener('click', function() {
                currentForm = this.closest('.delete-form');
                deleteModal.classList.remove('hidden');
            });
        }
    });
    
    const closeDeleteModal = function() {
        deleteModal.classList.add('hidden');
        currentForm = null;
    };
    
    closeDeleteModalButton.addEventListener('click', closeDeleteModal);
    cancelDeleteButton.addEventListener('click', closeDeleteModal);
    
    confirmDeleteButton.addEventListener('click', function() {
        if (currentForm) {
            currentForm.submit();
        }
    });
});
</script>

<?php
// Inclusion du pied de page
require_once 'includes/footer.php';
?>