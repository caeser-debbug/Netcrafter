<?php
// Fichier : get_supplier_details.php (AJAX endpoint pour les détails du fournisseur)
// Ce fichier devrait être créé séparément pour gérer les requêtes AJAX

/*
<?php
require_once 'includes/config.php';
require_once 'includes/auth_check.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID fournisseur invalide']);
    exit;
}

$supplier_id = intval($_GET['id']);

// Récupérer les détails du fournisseur
$supplier_query = "SELECT * FROM suppliers WHERE id = ?";
$stmt = $conn->prepare($supplier_query);
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$supplier_result = $stmt->get_result();

if (!$supplier_result || $supplier_result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Fournisseur non trouvé']);
    exit;
}

$supplier = $supplier_result->fetch_assoc();

// Récupérer les produits liés à ce fournisseur
$products_query = "SELECT id, name, price, stock, status FROM products WHERE supplier_id = ? ORDER BY name LIMIT 10";
$stmt = $conn->prepare($products_query);
$stmt->bind_param("s", $supplier_id);
$stmt->execute();
$products_result = $stmt->get_result();

$products = [];
while ($product = $products_result->fetch_assoc()) {
    $products[] = $product;
}

// Statistiques du fournisseur
$stats_query = "SELECT 
                    COUNT(*) as total_products,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_products,
                    AVG(price) as avg_price,
                    SUM(stock) as total_stock
                FROM products 
                WHERE supplier_id = ?";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("s", $supplier_id);
$stmt->execute();
$stats_result = $stmt->get_result();
$stats = $stats_result->fetch_assoc();

// Préparer la réponse
$response = [
    'supplier' => $supplier,
    'products' => $products,
    'stats' => $stats
];

echo json_encode($response);
?>
*/

// Fichier : export_suppliers.php (Export CSV des fournisseurs)

/*
<?php
require_once 'includes/config.php';
require_once 'includes/auth_check.php';

// Paramètres de filtrage (même logique que suppliers.php)
$country_filter = isset($_GET['country']) ? trim($_GET['country']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Construction de la requête
$sql = "SELECT s.*, 
               COUNT(DISTINCT p.id) as products_count,
               AVG(CASE WHEN p.status = 'active' THEN p.stock ELSE NULL END) as avg_stock
        FROM suppliers s 
        LEFT JOIN products p ON s.id = p.supplier_id";

$where_conditions = [];
$params = [];
$types = "";

// Appliquer les mêmes filtres que dans suppliers.php
if (!empty($country_filter)) {
    $where_conditions[] = "s.country LIKE ?";
    $params[] = "%" . $country_filter . "%";
    $types .= "s";
}

if (!empty($status_filter) && in_array($status_filter, ['active', 'inactive'])) {
    $where_conditions[] = "s.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($search_term)) {
    $where_conditions[] = "(s.name LIKE ? OR s.contact_person LIKE ? OR s.email LIKE ? OR s.phone LIKE ?)";
    $search_param = "%" . $search_term . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ssss";
}

if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

$sql .= " GROUP BY s.id ORDER BY s.name";

$stmt = $conn->prepare($sql);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Préparer le fichier CSV
$filename = 'fournisseurs_export_' . date('Y-m-d_H-i-s') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// BOM pour UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// En-têtes CSV
$headers = [
    'ID',
    'Nom',
    'Personne de contact',
    'Email',
    'Téléphone',
    'Site web',
    'Pays',
    'Méthode d\'expédition',
    'Délai de livraison (jours)',
    'Statut',
    'Nombre de produits',
    'Stock moyen',
    'Notes',
    'Date de création'
];

fputcsv($output, $headers, ';');

// Données
while ($row = $result->fetch_assoc()) {
    $data = [
        $row['id'],
        $row['name'],
        $row['contact_person'] ?? '',
        $row['email'] ?? '',
        $row['phone'] ?? '',
        $row['website'] ?? '',
        $row['country'] ?? '',
        $row['shipping_method'] ?? '',
        $row['average_delivery_time'] ?? 0,
        $row['status'] === 'active' ? 'Actif' : 'Inactif',
        $row['products_count'],
        $row['avg_stock'] ? round($row['avg_stock'], 2) : 0,
        $row['notes'] ?? '',
        date('d/m/Y H:i', strtotime($row['created_at']))
    ];
    
    fputcsv($output, $data, ';');
}

fclose($output);
exit;
?>
*/

// Fonctions utilitaires pour suppliers.php

/**
 * Fonction pour valider les données d'un fournisseur
 */
function validateSupplierData($data) {
    $errors = [];
    
    // Nom obligatoire
    if (empty(trim($data['name'] ?? ''))) {
        $errors[] = "Le nom du fournisseur est obligatoire.";
    }
    
    // Validation email
    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'adresse email n'est pas valide.";
    }
    
    // Validation URL
    if (!empty($data['website']) && !filter_var($data['website'], FILTER_VALIDATE_URL)) {
        $errors[] = "L'URL du site web n'est pas valide.";
    }
    
    // Validation délai de livraison
    if (!empty($data['average_delivery_time']) && (!is_numeric($data['average_delivery_time']) || $data['average_delivery_time'] < 0)) {
        $errors[] = "Le délai de livraison doit être un nombre positif.";
    }
    
    // Validation statut
    if (!empty($data['status']) && !in_array($data['status'], ['active', 'inactive'])) {
        $errors[] = "Le statut doit être 'actif' ou 'inactif'.";
    }
    
    return $errors;
}

/**
 * Fonction pour formater les informations d'un fournisseur
 */
function formatSupplierInfo($supplier) {
    $formatted = [
        'name' => htmlspecialchars($supplier['name']),
        'contact_person' => htmlspecialchars($supplier['contact_person'] ?? ''),
        'email' => htmlspecialchars($supplier['email'] ?? ''),
        'phone' => htmlspecialchars($supplier['phone'] ?? ''),
        'website' => htmlspecialchars($supplier['website'] ?? ''),
        'country' => htmlspecialchars($supplier['country'] ?? ''),
        'shipping_method' => htmlspecialchars($supplier['shipping_method'] ?? ''),
        'average_delivery_time' => intval($supplier['average_delivery_time'] ?? 0),
        'status' => $supplier['status'] ?? 'active',
        'notes' => htmlspecialchars($supplier['notes'] ?? ''),
        'created_at' => $supplier['created_at']
    ];
    
    return $formatted;
}

/**
 * Fonction pour obtenir le badge de statut d'un fournisseur
 */
function getSupplierStatusBadge($status) {
    switch ($status) {
        case 'active':
            return '<span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Actif</span>';
        case 'inactive':
            return '<span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">Inactif</span>';
        default:
            return '<span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200">Inconnu</span>';
    }
}

/**
 * Fonction pour générer les options de pays
 */
function getCountryOptions($selected = '') {
    $common_countries = [
        'China' => 'Chine',
        'United States' => 'États-Unis',
        'Germany' => 'Allemagne',
        'France' => 'France',
        'Italy' => 'Italie',
        'United Kingdom' => 'Royaume-Uni',
        'Japan' => 'Japon',
        'South Korea' => 'Corée du Sud',
        'Taiwan' => 'Taïwan',
        'India' => 'Inde',
        'Turkey' => 'Turquie',
        'Brazil' => 'Brésil',
        'Mexico' => 'Mexique',
        'Canada' => 'Canada',
        'Australia' => 'Australie'
    ];
    
    $options = '';
    foreach ($common_countries as $code => $name) {
        $selected_attr = ($selected === $code) ? 'selected' : '';
        $options .= "<option value=\"{$code}\" {$selected_attr}>{$name}</option>";
    }
    
    return $options;
}

/**
 * Fonction pour nettoyer et formater un numéro de téléphone
 */
function formatPhoneNumber($phone) {
    if (empty($phone)) return '';
    
    // Supprimer tous les caractères non numériques sauf + et espaces
    $cleaned = preg_replace('/[^\d\+\s\-\(\)]/', '', $phone);
    
    return trim($cleaned);
}

/**
 * Fonction pour valider une URL
 */
function validateWebsiteUrl($url) {
    if (empty($url)) return true; // URL vide est acceptable
    
    // Ajouter http:// si pas de schéma
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'http://' . $url;
    }
    
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Fonction pour obtenir les statistiques avancées d'un fournisseur
 */
function getSupplierAdvancedStats($conn, $supplier_id) {
    $stats_query = "SELECT 
                        COUNT(DISTINCT p.id) as total_products,
                        COUNT(DISTINCT CASE WHEN p.status = 'active' THEN p.id END) as active_products,
                        COUNT(DISTINCT CASE WHEN p.stock = 0 THEN p.id END) as out_of_stock_products,
                        AVG(p.price) as avg_product_price,
                        MIN(p.price) as min_product_price,
                        MAX(p.price) as max_product_price,
                        SUM(p.stock) as total_stock_value,
                        COUNT(DISTINCT o.id) as orders_count,
                        SUM(oi.quantity * oi.price) as total_revenue
                    FROM suppliers s
                    LEFT JOIN products p ON s.id = p.supplier_id
                    LEFT JOIN order_items oi ON p.id = oi.product_id
                    LEFT JOIN orders o ON oi.order_id = o.id
                    WHERE s.id = ?";
    
    $stmt = $conn->prepare($stats_query);
    $stmt->bind_param("i", $supplier_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

/**
 * JavaScript pour améliorer l'expérience utilisateur
 */
?>

<script>
// Fonctions JavaScript supplémentaires pour suppliers.php

// Auto-complétion pour les champs pays
function initCountryAutocomplete() {
    const countryInput = document.getElementById('country');
    if (!countryInput) return;
    
    const countries = [
        'Afghanistan', 'Albania', 'Algeria', 'Argentina', 'Australia', 'Austria',
        'Bangladesh', 'Belgium', 'Brazil', 'Bulgaria', 'Cambodia', 'Canada',
        'Chile', 'China', 'Colombia', 'Croatia', 'Czech Republic', 'Denmark',
        'Egypt', 'Finland', 'France', 'Germany', 'Ghana', 'Greece', 'Hungary',
        'India', 'Indonesia', 'Iran', 'Iraq', 'Ireland', 'Israel', 'Italy',
        'Japan', 'Jordan', 'Kenya', 'South Korea', 'Kuwait', 'Lebanon',
        'Malaysia', 'Mexico', 'Morocco', 'Netherlands', 'New Zealand', 'Nigeria',
        'Norway', 'Pakistan', 'Peru', 'Philippines', 'Poland', 'Portugal',
        'Romania', 'Russia', 'Saudi Arabia', 'Singapore', 'South Africa', 'Spain',
        'Sri Lanka', 'Sweden', 'Switzerland', 'Taiwan', 'Thailand', 'Turkey',
        'Ukraine', 'United Arab Emirates', 'United Kingdom', 'United States',
        'Vietnam', 'Yemen', 'Zimbabwe'
    ];
    
    // Créer une liste de suggestions
    const suggestionsContainer = document.createElement('div');
    suggestionsContainer.className = 'absolute z-10 w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg hidden';
    suggestionsContainer.style.maxHeight = '200px';
    suggestionsContainer.style.overflowY = 'auto';
    
    countryInput.parentNode.appendChild(suggestionsContainer);
    countryInput.parentNode.style.position = 'relative';
    
    countryInput.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        const matches = countries.filter(country => 
            country.toLowerCase().includes(query)
        ).slice(0, 10);
        
        if (matches.length > 0 && query.length > 0) {
            suggestionsContainer.innerHTML = matches.map(country => 
                `<div class="px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer">${country}</div>`
            ).join('');
            suggestionsContainer.classList.remove('hidden');
            
            // Ajouter les événements de clic
            suggestionsContainer.querySelectorAll('div').forEach(suggestion => {
                suggestion.addEventListener('click', function() {
                    countryInput.value = this.textContent;
                    suggestionsContainer.classList.add('hidden');
                });
            });
        } else {
            suggestionsContainer.classList.add('hidden');
        }
    });
    
    // Fermer les suggestions quand on clique ailleurs
    document.addEventListener('click', function(e) {
        if (!countryInput.contains(e.target) && !suggestionsContainer.contains(e.target)) {
            suggestionsContainer.classList.add('hidden');
        }
    });
}

// Validation en temps réel du formulaire
function initFormValidation() {
    const form = document.querySelector('form[action="suppliers.php"]');
    if (!form) return;
    
    const nameInput = form.querySelector('#name');
    const emailInput = form.querySelector('#email');
    const websiteInput = form.querySelector('#website');
    const phoneInput = form.querySelector('#phone');
    
    // Validation du nom
    if (nameInput) {
        nameInput.addEventListener('blur', function() {
            if (this.value.trim().length < 2) {
                showFieldError(this, 'Le nom doit contenir au moins 2 caractères');
            } else {
                clearFieldError(this);
            }
        });
    }
    
    // Validation de l'email
    if (emailInput) {
        emailInput.addEventListener('blur', function() {
            if (this.value && !isValidEmail(this.value)) {
                showFieldError(this, 'Adresse email invalide');
            } else {
                clearFieldError(this);
            }
        });
    }
    
    // Validation du site web
    if (websiteInput) {
        websiteInput.addEventListener('blur', function() {
            if (this.value && !isValidUrl(this.value)) {
                showFieldError(this, 'URL invalide');
            } else {
                clearFieldError(this);
            }
        });
    }
    
    // Formatage du téléphone
    if (phoneInput) {
        phoneInput.addEventListener('input', function() {
            // Formatage basique du numéro de téléphone
            let value = this.value.replace(/[^\d\+\s\-\(\)]/g, '');
            this.value = value;
        });
    }
}

// Fonctions utilitaires de validation
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function isValidUrl(url) {
    try {
        const urlObj = new URL(url.startsWith('http') ? url : 'http://' + url);
        return urlObj.protocol === 'http:' || urlObj.protocol === 'https:';
    } catch {
        return false;
    }
}

function showFieldError(field, message) {
    clearFieldError(field);
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'text-red-500 text-xs mt-1 field-error';
    errorDiv.textContent = message;
    
    field.parentNode.appendChild(errorDiv);
    field.classList.add('border-red-500');
}

function clearFieldError(field) {
    const errorDiv = field.parentNode.querySelector('.field-error');
    if (errorDiv) {
        errorDiv.remove();
    }
    field.classList.remove('border-red-500');
}

// Amélioration de la modal de détails avec AJAX
function viewSupplierDetails(supplierId) {
    const modal = document.getElementById('supplier-details-modal');
    const content = document.getElementById('supplier-details-content');
    
    // Afficher le modal avec un indicateur de chargement
    content.innerHTML = `
        <div class="text-center py-8">
            <i class="fas fa-spinner fa-spin text-3xl text-gray-400 mb-4"></i>
            <p class="text-gray-600 dark:text-gray-400">Chargement des détails...</p>
        </div>
    `;
    modal.classList.remove('hidden');
    
    // Requête AJAX pour récupérer les détails
    fetch(`get_supplier_details.php?id=${supplierId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            
            const supplier = data.supplier;
            const products = data.products;
            const stats = data.stats;
            
            content.innerHTML = `
                <div class="space-y-6">
                    <!-- Informations principales -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <h4 class="font-semibold text-gray-800 dark:text-white mb-2">Informations générales</h4>
                            <div class="space-y-2 text-sm">
                                <div><strong>Nom :</strong> ${supplier.name}</div>
                                ${supplier.contact_person ? `<div><strong>Contact :</strong> ${supplier.contact_person}</div>` : ''}
                                ${supplier.email ? `<div><strong>Email :</strong> <a href="mailto:${supplier.email}" class="text-blue-600 hover:underline">${supplier.email}</a></div>` : ''}
                                ${supplier.phone ? `<div><strong>Téléphone :</strong> <a href="tel:${supplier.phone}" class="text-blue-600 hover:underline">${supplier.phone}</a></div>` : ''}
                            </div>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-800 dark:text-white mb-2">Informations logistiques</h4>
                            <div class="space-y-2 text-sm">
                                <div><strong>Pays :</strong> ${supplier.country || 'Non spécifié'}</div>
                                ${supplier.shipping_method ? `<div><strong>Expédition :</strong> ${supplier.shipping_method}</div>` : ''}
                                ${supplier.average_delivery_time ? `<div><strong>Délai :</strong> ${supplier.average_delivery_time} jours</div>` : ''}
                                <div><strong>Statut :</strong> <span class="px-2 py-1 text-xs rounded-full ${supplier.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">${supplier.status === 'active' ? 'Actif' : 'Inactif'}</span></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Statistiques -->
                    <div>
                        <h4 class="font-semibold text-gray-800 dark:text-white mb-2">Statistiques</h4>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded">
                                <div class="text-xl font-bold text-blue-600">${stats.total_products}</div>
                                <div class="text-xs text-gray-600 dark:text-gray-400">Produits</div>
                            </div>
                            <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded">
                                <div class="text-xl font-bold text-green-600">${stats.active_products}</div>
                                <div class="text-xs text-gray-600 dark:text-gray-400">Actifs</div>
                            </div>
                            <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded">
                                <div class="text-xl font-bold text-purple-600">${Math.round(stats.avg_price || 0)} FCFA</div>
                                <div class="text-xs text-gray-600 dark:text-gray-400">Prix moyen</div>
                            </div>
                            <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded">
                                <div class="text-xl font-bold text-orange-600">${stats.total_stock}</div>
                                <div class="text-xs text-gray-600 dark:text-gray-400">Stock total</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Produits récents -->
                    ${products.length > 0 ? `
                    <div>
                        <h4 class="font-semibold text-gray-800 dark:text-white mb-2">Produits récents</h4>
                        <div class="space-y-2">
                            ${products.map(product => `
                                <div class="flex justify-between items-center p-2 bg-gray-50 dark:bg-gray-700 rounded">
                                    <div>
                                        <div class="font-medium">${product.name}</div>
                                        <div class="text-sm text-gray-600 dark:text-gray-400">${product.price} FCFA - Stock: ${product.stock}</div>
                                    </div>
                                    <span class="px-2 py-1 text-xs rounded-full ${product.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">${product.status === 'active' ? 'Actif' : 'Inactif'}</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    ` : ''}
                    
                    <!-- Notes -->
                    ${supplier.notes ? `
                    <div>
                        <h4 class="font-semibold text-gray-800 dark:text-white mb-2">Notes</h4>
                        <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded text-sm">${supplier.notes}</div>
                    </div>
                    ` : ''}
                </div>
            `;
        })
        .catch(error => {
            content.innerHTML = `
                <div class="text-center py-8">
                    <i class="fas fa-exclamation-triangle text-3xl text-red-400 mb-4"></i>
                    <p class="text-red-600 dark:text-red-400">Erreur lors du chargement des détails</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">${error.message}</p>
                </div>
            `;
        });
}

// Initialisation quand le DOM est chargé
document.addEventListener('DOMContentLoaded', function() {
    initCountryAutocomplete();
    initFormValidation();
});
</script>

<?php
// Styles CSS supplémentaires pour améliorer l'apparence
?>

<style>
/* Styles supplémentaires pour suppliers.php */

/* Animation pour les modals */
.modal-enter {
    animation: modalEnter 0.3s ease-out;
}

@keyframes modalEnter {
    from {
        opacity: 0;
        transform: scale(0.9);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

/* Style pour les suggestions de pays */
.country-suggestions {
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

/* Amélioration des cartes de statistiques */
.stats-card {
    transition: all 0.3s ease;
    background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
}

.stats-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Style pour les badges de statut */
.status-badge {
    display: inline-flex;
    align-items: center;
    font-weight: 500;
    transition: all 0.2s ease;
}

.status-badge:hover {
    transform: scale(1.05);
}

/* Animation pour les lignes du tableau */
.table-row {
    transition: all 0.2s ease;
}

.table-row:hover {
    background-color: rgba(59, 130, 246, 0.05);
}

/* Style pour les liens externes */
.external-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: all 0.2s ease;
}

.external-link:hover {
    color: #3B82F6;
    text-decoration: underline;
}

/* Amélioration du formulaire */
.form-input {
    transition: all 0.2s ease;
}

.form-input:focus {
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Style pour les messages d'erreur */
.field-error {
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive design amélioré */
@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .table-container {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
}

/* Dark mode amélioré */
.dark .stats-card {
    background: linear-gradient(135deg, rgba(0,0,0,0.1) 0%, rgba(0,0,0,0.05) 100%);
}

.dark .modal-content {
    background-color: #1f2937;
    border: 1px solid #374151;
}

.dark .country-suggestions {
    background-color: #1f2937;
    border-color: #374151;
}

.dark .status-badge.active {
    background-color: #065f46;
    color: #10b981;
}

.dark .status-badge.inactive {
    background-color: #7f1d1d;
    color: #ef4444;
}

/* Indicateurs de performance */
.performance-indicator {
    position: relative;
    display: inline-block;
}

.performance-indicator::after {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background-color: #10b981; /* Vert par défaut */
}

.performance-indicator.warning::after {
    background-color: #f59e0b; /* Orange */
}

.performance-indicator.danger::after {
    background-color: #ef4444; /* Rouge */
}

/* Animation de chargement pour les données AJAX */
.loading-skeleton {
    animation: skeleton-loading 1.5s ease-in-out infinite alternate;
}

@keyframes skeleton-loading {
    0% {
        background-color: #e5e7eb;
    }
    100% {
        background-color: #f3f4f6;
    }
}

.dark .loading-skeleton {
    animation: skeleton-loading-dark 1.5s ease-in-out infinite alternate;
}

@keyframes skeleton-loading-dark {
    0% {
        background-color: #374151;
    }
    100% {
        background-color: #4b5563;
    }
}

/* Icônes de statut personnalisées */
.supplier-status-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    font-size: 10px;
}

.supplier-status-icon.active {
    background-color: #dcfce7;
    color: #16a34a;
}

.supplier-status-icon.inactive {
    background-color: #fef2f2;
    color: #dc2626;
}

/* Tooltip personnalisé */
.tooltip {
    position: relative;
    display: inline-block;
}

.tooltip .tooltiptext {
    visibility: hidden;
    width: 200px;
    background-color: #1f2937;
    color: white;
    text-align: center;
    border-radius: 6px;
    padding: 8px;
    position: absolute;
    z-index: 1000;
    bottom: 125%;
    left: 50%;
    margin-left: -100px;
    opacity: 0;
    transition: opacity 0.3s;
    font-size: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.tooltip .tooltiptext::after {
    content: "";
    position: absolute;
    top: 100%;
    left: 50%;
    margin-left: -5px;
    border-width: 5px;
    border-style: solid;
    border-color: #1f2937 transparent transparent transparent;
}

.tooltip:hover .tooltiptext {
    visibility: visible;
    opacity: 1;
}
</style>

<?php
// Fonctions avancées pour la gestion des fournisseurs

/**
 * Classe pour gérer les opérations sur les fournisseurs
 */
class SupplierManager {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Obtenir tous les fournisseurs avec filtrage et pagination
     */
    public function getSuppliers($filters = []) {
        $sql = "SELECT s.*, 
                       COUNT(DISTINCT p.id) as products_count,
                       AVG(CASE WHEN p.status = 'active' THEN p.stock ELSE NULL END) as avg_stock,
                       SUM(CASE WHEN p.status = 'active' THEN p.stock * p.price ELSE 0 END) as total_inventory_value
                FROM suppliers s 
                LEFT JOIN products p ON s.id = p.supplier_id";
        
        $where_conditions = [];
        $params = [];
        $types = "";
        
        // Appliquer les filtres
        if (!empty($filters['search'])) {
            $where_conditions[] = "(s.name LIKE ? OR s.contact_person LIKE ? OR s.email LIKE ?)";
            $search_param = "%" . $filters['search'] . "%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $types .= "sss";
        }
        
        if (!empty($filters['country'])) {
            $where_conditions[] = "s.country = ?";
            $params[] = $filters['country'];
            $types .= "s";
        }
        
        if (!empty($filters['status'])) {
            $where_conditions[] = "s.status = ?";
            $params[] = $filters['status'];
            $types .= "s";
        }
        
        if (!empty($where_conditions)) {
            $sql .= " WHERE " . implode(" AND ", $where_conditions);
        }
        
        $sql .= " GROUP BY s.id";
        
        // Tri
        $sort_by = $filters['sort_by'] ?? 'id';
        $sort_order = $filters['sort_order'] ?? 'desc';
        
        if (in_array($sort_by, ['products_count', 'avg_stock', 'total_inventory_value'])) {
            $sql .= " ORDER BY $sort_by $sort_order";
        } else {
            $sql .= " ORDER BY s.$sort_by $sort_order";
        }
        
        // Pagination
        if (isset($filters['limit']) && isset($filters['offset'])) {
            $sql .= " LIMIT ?, ?";
            $params[] = $filters['offset'];
            $params[] = $filters['limit'];
            $types .= "ii";
        }
        
        $stmt = $this->conn->prepare($sql);
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Obtenir le nombre total de fournisseurs
     */
    public function getTotalCount($filters = []) {
        $sql = "SELECT COUNT(*) as total FROM suppliers s";
        $where_conditions = [];
        $params = [];
        $types = "";
        
        // Mêmes filtres que getSuppliers
        if (!empty($filters['search'])) {
            $where_conditions[] = "(s.name LIKE ? OR s.contact_person LIKE ? OR s.email LIKE ?)";
            $search_param = "%" . $filters['search'] . "%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $types .= "sss";
        }
        
        if (!empty($filters['country'])) {
            $where_conditions[] = "s.country = ?";
            $params[] = $filters['country'];
            $types .= "s";
        }
        
        if (!empty($filters['status'])) {
            $where_conditions[] = "s.status = ?";
            $params[] = $filters['status'];
            $types .= "s";
        }
        
        if (!empty($where_conditions)) {
            $sql .= " WHERE " . implode(" AND ", $where_conditions);
        }
        
        $stmt = $this->conn->prepare($sql);
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        
        return $stmt->get_result()->fetch_assoc()['total'];
    }
    
    /**
     * Créer un nouveau fournisseur
     */
    public function createSupplier($data) {
        $sql = "INSERT INTO suppliers (name, contact_person, email, phone, website, country, shipping_method, average_delivery_time, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("sssssssis", 
            $data['name'],
            $data['contact_person'],
            $data['email'],
            $data['phone'],
            $data['website'],
            $data['country'],
            $data['shipping_method'],
            $data['average_delivery_time'],
            $data['notes'],
            $data['status']
        );
        
        return $stmt->execute() ? $this->conn->insert_id : false;
    }
    
    /**
     * Mettre à jour un fournisseur
     */
    public function updateSupplier($id, $data) {
        $sql = "UPDATE suppliers SET name = ?, contact_person = ?, email = ?, phone = ?, website = ?, country = ?, shipping_method = ?, average_delivery_time = ?, notes = ?, status = ? WHERE id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("sssssssisi", 
            $data['name'],
            $data['contact_person'],
            $data['email'],
            $data['phone'],
            $data['website'],
            $data['country'],
            $data['shipping_method'],
            $data['average_delivery_time'],
            $data['notes'],
            $data['status'],
            $id
        );
        
        return $stmt->execute();
    }
    
    /**
     * Supprimer un fournisseur
     */
    public function deleteSupplier($id) {
        // Vérifier s'il y a des produits liés
        $check_sql = "SELECT COUNT(*) as count FROM products WHERE supplier_id = ?";
        $stmt = $this->conn->prepare($check_sql);
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['count'];
        
        if ($count > 0) {
            return ['success' => false, 'message' => "Impossible de supprimer ce fournisseur car $count produit(s) y sont liés."];
        }
        
        $delete_sql = "DELETE FROM suppliers WHERE id = ?";
        $stmt = $this->conn->prepare($delete_sql);
        $stmt->bind_param("i", $id);
        
        return $stmt->execute() ? 
            ['success' => true, 'message' => 'Fournisseur supprimé avec succès.'] :
            ['success' => false, 'message' => 'Erreur lors de la suppression.'];
    }
    
    /**
     * Obtenir les statistiques générales
     */
    public function getGlobalStats() {
        $sql = "SELECT 
                    COUNT(*) as total_suppliers,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_suppliers,
                    AVG(average_delivery_time) as avg_delivery_time,
                    COUNT(DISTINCT country) as countries_count
                FROM suppliers";
        
        $result = $this->conn->query($sql);
        return $result->fetch_assoc();
    }
    
    /**
     * Obtenir les performances des fournisseurs
     */
    public function getSupplierPerformance($id) {
        $sql = "SELECT 
                    s.*,
                    COUNT(DISTINCT p.id) as total_products,
                    COUNT(DISTINCT CASE WHEN p.status = 'active' THEN p.id END) as active_products,
                    AVG(p.price) as avg_product_price,
                    SUM(p.stock) as total_stock,
                    COUNT(DISTINCT oi.order_id) as orders_count,
                    SUM(oi.quantity * oi.price) as total_revenue,
                    AVG(pr.rating) as avg_rating
                FROM suppliers s
                LEFT JOIN products p ON s.id = p.supplier_id
                LEFT JOIN order_items oi ON p.id = oi.product_id
                LEFT JOIN product_reviews pr ON p.id = pr.product_id
                WHERE s.id = ?
                GROUP BY s.id";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_assoc();
    }
}

/**
 * Utilitaires pour l'interface utilisateur
 */
class SupplierUIHelper {
    
    /**
     * Générer le badge de statut
     */
    public static function getStatusBadge($status, $withIcon = true) {
        $icon = $withIcon ? '<i class="fas fa-circle mr-1"></i>' : '';
        
        switch ($status) {
            case 'active':
                return "<span class=\"inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200\">{$icon}Actif</span>";
            case 'inactive':
                return "<span class=\"inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200\">{$icon}Inactif</span>";
            default:
                return "<span class=\"inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200\">{$icon}Inconnu</span>";
        }
    }
    
    /**
     * Générer l'indicateur de performance
     */
    public static function getPerformanceIndicator($score) {
        if ($score >= 80) {
            return '<div class="performance-indicator" title="Excellente performance"></div>';
        } elseif ($score >= 60) {
            return '<div class="performance-indicator warning" title="Performance correcte"></div>';
        } else {
            return '<div class="performance-indicator danger" title="Performance à améliorer"></div>';
        }
    }
    
    /**
     * Formater les délais de livraison
     */
    public static function formatDeliveryTime($days) {
        if (!$days || $days == 0) {
            return '<span class="text-gray-500">Non spécifié</span>';
        }
        
        $class = '';
        if ($days <= 7) {
            $class = 'text-green-600';
        } elseif ($days <= 14) {
            $class = 'text-yellow-600';
        } else {
            $class = 'text-red-600';
        }
        
        return "<span class=\"{$class} font-medium\">{$days} jour" . ($days > 1 ? 's' : '') . "</span>";
    }
    
    /**
     * Générer le lien vers le site web
     */
    public static function formatWebsiteLink($url, $name = '') {
        if (empty($url)) return '';
        
        $display_name = $name ?: 'Site web';
        $clean_url = filter_var($url, FILTER_VALIDATE_URL) ? $url : 'http://' . $url;
        
        return "<a href=\"{$clean_url}\" target=\"_blank\" class=\"external-link text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200\">{$display_name} <i class=\"fas fa-external-link-alt text-xs\"></i></a>";
    }
}

/**
 * Système de notification pour les fournisseurs
 */
class SupplierNotifications {
    
    /**
     * Vérifier les fournisseurs avec des problèmes potentiels
     */
    public static function checkSupplierIssues($conn) {
        $issues = [];
        
        // Fournisseurs sans produits
        $sql = "SELECT s.id, s.name FROM suppliers s 
                LEFT JOIN products p ON s.id = p.supplier_id 
                WHERE s.status = 'active' AND p.id IS NULL";
        $result = $conn->query($sql);
        
        while ($row = $result->fetch_assoc()) {
            $issues[] = [
                'type' => 'no_products',
                'supplier_id' => $row['id'],
                'supplier_name' => $row['name'],
                'message' => 'Aucun produit associé'
            ];
        }
        
        // Fournisseurs avec délai de livraison très long
        $sql = "SELECT id, name, average_delivery_time FROM suppliers 
                WHERE status = 'active' AND average_delivery_time > 30";
        $result = $conn->query($sql);
        
        while ($row = $result->fetch_assoc()) {
            $issues[] = [
                'type' => 'long_delivery',
                'supplier_id' => $row['id'],
                'supplier_name' => $row['name'],
                'message' => "Délai de livraison très long ({$row['average_delivery_time']} jours)"
            ];
        }
        
        // Fournisseurs avec beaucoup de produits en rupture
        $sql = "SELECT s.id, s.name, 
                       COUNT(p.id) as total_products,
                       COUNT(CASE WHEN p.stock = 0 THEN 1 END) as out_of_stock
                FROM suppliers s
                JOIN products p ON s.id = p.supplier_id
                WHERE s.status = 'active'
                GROUP BY s.id
                HAVING (out_of_stock / total_products) > 0.5 AND total_products > 2";
        $result = $conn->query($sql);
        
        while ($row = $result->fetch_assoc()) {
            $issues[] = [
                'type' => 'stock_issues',
                'supplier_id' => $row['id'],
                'supplier_name' => $row['name'],
                'message' => "Beaucoup de produits en rupture ({$row['out_of_stock']}/{$row['total_products']})"
            ];
        }
        
        return $issues;
    }
    
    /**
     * Générer les alertes HTML
     */
    public static function generateAlertsHTML($issues) {
        if (empty($issues)) {
            return '<div class="bg-green-50 dark:bg-green-900 border border-green-200 dark:border-green-700 rounded-md p-4 mb-6">
                        <div class="flex">
                            <i class="fas fa-check-circle text-green-400 mr-3 mt-0.5"></i>
                            <div class="text-sm text-green-700 dark:text-green-200">
                                Aucun problème détecté avec vos fournisseurs.
                            </div>
                        </div>
                    </div>';
        }
        
        $html = '<div class="bg-yellow-50 dark:bg-yellow-900 border border-yellow-200 dark:border-yellow-700 rounded-md p-4 mb-6">
                    <div class="flex">
                        <i class="fas fa-exclamation-triangle text-yellow-400 mr-3 mt-0.5"></i>
                        <div>
                            <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Problèmes détectés</h3>
                            <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                                <ul class="list-disc list-inside space-y-1">';
        
        foreach ($issues as $issue) {
            $html .= "<li><strong>{$issue['supplier_name']}</strong>: {$issue['message']}</li>";
        }
        
        $html .= '      </ul>
                            </div>
                        </div>
                    </div>
                </div>';
        
        return $html;
    }
}

// Fonctions JavaScript avancées pour l'interface

?>

<script>
// Gestion avancée des fournisseurs

class SupplierManager {
    constructor() {
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.setupAutoSave();
        this.setupRealTimeValidation();
        this.checkForIssues();
    }
    
    setupEventListeners() {
        // Recherche en temps réel
        const searchInput = document.getElementById('search');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.performSearch(e.target.value);
                }, 500);
            });
        }
        
        // Export avancé
        const exportBtn = document.getElementById('export-suppliers');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => this.showExportModal());
        }
        
        // Import de fournisseurs
        const importBtn = document.getElementById('import-suppliers');
        if (importBtn) {
            importBtn.addEventListener('click', () => this.showImportModal());
        }
    }
    
    setupAutoSave() {
        const form = document.querySelector('form[action="suppliers.php"]');
        if (!form) return;
        
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('change', () => {
                this.saveFormData(form);
            });
        });
    }
    
    setupRealTimeValidation() {
        const emailInput = document.getElementById('email');
        if (emailInput) {
            emailInput.addEventListener('blur', async (e) => {
                if (e.target.value) {
                    await this.validateEmail(e.target.value);
                }
            });
        }
        
        const websiteInput = document.getElementById('website');
        if (websiteInput) {
            websiteInput.addEventListener('blur', async (e) => {
                if (e.target.value) {
                    await this.validateWebsite(e.target.value);
                }
            });
        }
    }
    
    async validateEmail(email) {
        try {
            const response = await fetch('validate-supplier-data.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ type: 'email', value: email })
            });
            
            const result = await response.json();
            this.showFieldValidation('email', result.valid, result.message);
        } catch (error) {
            console.error('Erreur de validation email:', error);
        }
    }
    
    async validateWebsite(url) {
        try {
            const response = await fetch('validate-supplier-data.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ type: 'website', value: url })
            });
            
            const result = await response.json();
            this.showFieldValidation('website', result.valid, result.message);
        } catch (error) {
            console.error('Erreur de validation website:', error);
        }
    }
    
    showFieldValidation(fieldId, isValid, message) {
        const field = document.getElementById(fieldId);
        const existingFeedback = field.parentNode.querySelector('.validation-feedback');
        
        if (existingFeedback) {
            existingFeedback.remove();
        }
        
        if (message) {
            const feedback = document.createElement('div');
            feedback.className = `validation-feedback text-xs mt-1 ${isValid ? 'text-green-600' : 'text-red-600'}`;
            feedback.textContent = message;
            field.parentNode.appendChild(feedback);
        }
        
        field.classList.toggle('border-green-500', isValid);
        field.classList.toggle('border-red-500', !isValid);
    }
    
    performSearch(query) {
        // Mettre à jour l'URL sans recharger la page
        const url = new URL(window.location);
        if (query) {
            url.searchParams.set('search', query);
        } else {
            url.searchParams.delete('search');
        }
        
        window.history.pushState({}, '', url);
        
        // Recharger les résultats via AJAX (optionnel)
        this.loadSuppliersAjax();
    }
    
    async loadSuppliersAjax() {
        const tableBody = document.querySelector('tbody');
        if (!tableBody) return;
        
        // Afficher un indicateur de chargement
        tableBody.innerHTML = this.getLoadingSkeleton();
        
        try {
            const url = new URL(window.location);
            url.searchParams.set('ajax', '1');
            
            const response = await fetch(url);
            const html = await response.text();
            
            // Mettre à jour le contenu
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = html;
            const newTableBody = tempDiv.querySelector('tbody');
            
            if (newTableBody) {
                tableBody.innerHTML = newTableBody.innerHTML;
            }
        } catch (error) {
            console.error('Erreur lors du chargement:', error);
            tableBody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-red-600">Erreur lors du chargement</td></tr>';
        }
    }
    
    getLoadingSkeleton() {
        let skeleton = '';
        for (let i = 0; i < 5; i++) {
            skeleton += `
                <tr>
                    <td class="px-6 py-4"><div class="loading-skeleton h-4 w-full rounded"></div></td>
                    <td class="px-6 py-4"><div class="loading-skeleton h-4 w-full rounded"></div></td>
                    <td class="px-6 py-4"><div class="loading-skeleton h-4 w-full rounded"></div></td>
                    <td class="px-6 py-4"><div class="loading-skeleton h-4 w-full rounded"></div></td>
                    <td class="px-6 py-4"><div class="loading-skeleton h-4 w-full rounded"></div></td>
                    <td class="px-6 py-4"><div class="loading-skeleton h-4 w-full rounded"></div></td>
                    <td class="px-6 py-4"><div class="loading-skeleton h-4 w-full rounded"></div></td>
                </tr>
            `;
        }
        return skeleton;
    }
    
    saveFormData(form) {
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        
        // Sauvegarder dans localStorage
        localStorage.setItem('supplier_form_data', JSON.stringify(data));
    }
    
    restoreFormData() {
        const savedData = localStorage.getItem('supplier_form_data');
        if (!savedData) return;
        
        try {
            const data = JSON.parse(savedData);
            const form = document.querySelector('form[action="suppliers.php"]');
            if (!form) return;
            
            Object.keys(data).forEach(key => {
                const field = form.querySelector(`[name="${key}"]`);
                if (field && field.value === '') {
                    field.value = data[key];
                }
            });
        } catch (error) {
            console.error('Erreur lors de la restauration des données:', error);
        }
    }
    
    showExportModal() {
        // Créer et afficher une modal d'export avancée
        const modal = this.createExportModal();
        document.body.appendChild(modal);
        modal.classList.remove('hidden');
    }
    
    createExportModal() {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 z-50 overflow-auto bg-black bg-opacity-50 flex items-center justify-center';
        modal.innerHTML = `
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6">
                <h3 class="text-lg font-bold mb-4">Exporter les fournisseurs</h3>
                <form id="export-form">
                    <div class="space-y-3 mb-6">
                        <label class="flex items-center">
                            <input type="checkbox" name="fields[]" value="basic" checked class="mr-2">
                            <span>Informations de base</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="fields[]" value="contact" checked class="mr-2">
                            <span>Informations de contact</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="fields[]" value="logistics" class="mr-2">
                            <span>Informations logistiques</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="fields[]" value="stats" class="mr-2">
                            <span>Statistiques</span>
                        </label>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="this.closest('.fixed').remove()" class="px-4 py-2 bg-gray-300 rounded">Annuler</button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Exporter</button>
                    </div>
                </form>
            </div>
        `;
        
        modal.querySelector('#export-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.performExport(new FormData(e.target));
            modal.remove();
        });
        
        return modal;
    }
    
    performExport(formData) {
        const fields = formData.getAll('fields[]');
        const url = new URL('export_suppliers.php', window.location.origin + '/admin/');
        
        fields.forEach(field => {
            url.searchParams.append('fields[]', field);
        });
        
        // Ajouter les filtres actuels
        const currentUrl = new URL(window.location);
        ['search', 'country', 'status'].forEach(param => {
            const value = currentUrl.searchParams.get(param);
            if (value) {
                url.searchParams.set(param, value);
            }
        });
        
        // Télécharger le fichier
        window.open(url.toString(), '_blank');
    }
    
    checkForIssues() {
        // Vérifier périodiquement les problèmes avec les fournisseurs
        setInterval(() => {
            this.loadSupplierIssues();
        }, 300000); // Toutes les 5 minutes
    }
    
    async loadSupplierIssues() {
        try {
            const response = await fetch('check-supplier-issues.php');
            const issues = await response.json();
            
            if (issues.length > 0) {
                this.showIssuesNotification(issues);
            }
        } catch (error) {
            console.error('Erreur lors de la vérification des problèmes:', error);
        }
    }
    
    showIssuesNotification(issues) {
        // Afficher une notification discrète
        const notification = document.createElement('div');
        notification.className = 'fixed top-4 right-4 bg-yellow-500 text-white p-4 rounded-lg shadow-lg z-50';
        notification.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <span>${issues.length} problème(s) détecté(s) avec vos fournisseurs</span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Supprimer automatiquement après 10 secondes
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 10000);
    }
}

// Utilitaires pour les graphiques et visualisations
class SupplierCharts {
    constructor() {
        this.initCharts();
    }
    
    initCharts() {
        this.createCountryDistributionChart();
        this.createDeliveryTimeChart();
        this.createPerformanceChart();
    }
    
    createCountryDistributionChart() {
        const canvas = document.getElementById('country-distribution-chart');
        if (!canvas) return;
        
        fetch('supplier-analytics.php?type=country_distribution')
            .then(response => response.json())
            .then(data => {
                new Chart(canvas, {
                    type: 'doughnut',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            data: data.values,
                            backgroundColor: [
                                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
                                '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Répartition par pays'
                            }
                        }
                    }
                });
            });
    }
    
    createDeliveryTimeChart() {
        const canvas = document.getElementById('delivery-time-chart');
        if (!canvas) return;
        
        fetch('supplier-analytics.php?type=delivery_times')
            .then(response => response.json())
            .then(data => {
                new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: data.suppliers,
                        datasets: [{
                            label: 'Délai de livraison (jours)',
                            data: data.delivery_times,
                            backgroundColor: data.delivery_times.map(time => {
                                if (time <= 7) return '#10B981';
                                if (time <= 14) return '#F59E0B';
                                return '#EF4444';
                            })
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Délais de livraison par fournisseur'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Jours'
                                }
                            }
                        }
                    }
                });
            });
    }
    
    createPerformanceChart() {
        const canvas = document.getElementById('performance-chart');
        if (!canvas) return;
        
        fetch('supplier-analytics.php?type=performance')
            .then(response => response.json())
            .then(data => {
                new Chart(canvas, {
                    type: 'radar',
                    data: {
                        labels: ['Nombre de produits', 'Stock moyen', 'Délai de livraison', 'Note moyenne', 'Chiffre d\'affaires'],
                        datasets: data.suppliers.map((supplier, index) => ({
                            label: supplier.name,
                            data: supplier.metrics,
                            borderColor: `hsl(${index * 137.5}, 70%, 50%)`,
                            backgroundColor: `hsla(${index * 137.5}, 70%, 50%, 0.2)`
                        }))
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Comparaison des performances'
                            }
                        },
                        scales: {
                            r: {
                                beginAtZero: true,
                                max: 100
                            }
                        }
                    }
                });
            });
    }
}

// Initialisation quand le DOM est chargé
document.addEventListener('DOMContentLoaded', function() {
    const supplierManager = new SupplierManager();
    const supplierCharts = new SupplierCharts();
    
    // Restaurer les données du formulaire si nécessaire
    supplierManager.restoreFormData();
    
    // Gestion des raccourcis clavier
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + K pour la recherche rapide
        if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
            e.preventDefault();
            const searchInput = document.getElementById('search');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }
        
        // Ctrl/Cmd + N pour nouveau fournisseur
        if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
            e.preventDefault();
            const nameInput = document.getElementById('name');
            if (nameInput) {
                nameInput.focus();
            }
        }
    });
});
</script>

<?php
// Fichiers annexes à créer séparément

/**
 * validate-supplier-data.php
 * Endpoint pour la validation en temps réel
 */
/*
<?php
require_once 'includes/config.php';
require_once 'includes/auth_check.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['type']) || !isset($input['value'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Paramètres invalides']);
    exit;
}

$type = $input['type'];
$value = $input['value'];
$response = ['valid' => false, 'message' => ''];

switch ($type) {
    case 'email':
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            // Vérifier si l'email existe déjà
            $stmt = $conn->prepare("SELECT id FROM suppliers WHERE email = ?");
            $stmt->bind_param("s", $value);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $response['message'] = 'Cette adresse email est déjà utilisée';
            } else {
                $response['valid'] = true;
                $response['message'] = 'Adresse email valide';
            }
        } else {
            $response['message'] = 'Format d\'email invalide';
        }
        break;
        
    case 'website':
        if (filter_var($value, FILTER_VALIDATE_URL) || filter_var('http://' . $value, FILTER_VALIDATE_URL)) {
            $response['valid'] = true;
            $response['message'] = 'URL valide';
        } else {
            $response['message'] = 'Format d\'URL invalide';
        }
        break;
        
    default:
        $response['message'] = 'Type de validation non supporté';
}

echo json_encode($response);
?>
*/

/**
 * supplier-analytics.php
 * Endpoint pour les données des graphiques
 */
/*
<?php
require_once 'includes/config.php';
require_once 'includes/auth_check.php';

header('Content-Type: application/json');

$type = $_GET['type'] ?? '';

switch ($type) {
    case 'country_distribution':
        $sql = "SELECT country, COUNT(*) as count FROM suppliers WHERE country IS NOT NULL AND country != '' GROUP BY country ORDER BY count DESC LIMIT 8";
        $result = $conn->query($sql);
        
        $data = ['labels' => [], 'values' => []];
        while ($row = $result->fetch_assoc()) {
            $data['labels'][] = $row['country'];
            $data['values'][] = (int)$row['count'];
        }
        
        echo json_encode($data);
        break;
        
    case 'delivery_times':
        $sql = "SELECT name, average_delivery_time FROM suppliers WHERE average_delivery_time > 0 ORDER BY average_delivery_time DESC LIMIT 10";
        $result = $conn->query($sql);
        
        $data = ['suppliers' => [], 'delivery_times' => []];
        while ($row = $result->fetch_assoc()) {
            $data['suppliers'][] = $row['name'];
            $data['delivery_times'][] = (int)$row['average_delivery_time'];
        }
        
        echo json_encode($data);
        break;
        
    case 'performance':
        $sql = "SELECT s.id, s.name,
                       COUNT(DISTINCT p.id) as product_count,
                       AVG(p.stock) as avg_stock,
                       s.average_delivery_time,
                       AVG(pr.rating) as avg_rating,
                       SUM(oi.quantity * oi.price) as revenue
                FROM suppliers s
                LEFT JOIN products p ON s.id = p.supplier_id
                LEFT JOIN product_reviews pr ON p.id = pr.product_id
                LEFT JOIN order_items oi ON p.id = oi.product_id
                WHERE s.status = 'active'
                GROUP BY s.id
                ORDER BY revenue DESC
                LIMIT 5";
        $result = $conn->query($sql);
        
        $suppliers = [];
        while ($row = $result->fetch_assoc()) {
            $suppliers[] = [
                'name' => $row['name'],
                'metrics' => [
                    min(100, ($row['product_count'] ?? 0) * 10), // Produits (max 10 = 100%)
                    min(100, ($row['avg_stock'] ?? 0) / 10), // Stock moyen (max 1000 = 100%)
                    max(0, 100 - (($row['average_delivery_time'] ?? 30) * 3)), // Délai (inversé, max 30j = 0%)
                    ($row['avg_rating'] ?? 3) * 20, // Note (max 5 = 100%)
                    min(100, ($row['revenue'] ?? 0) / 10000) // Revenue (max 1M = 100%)
                ]
            ];
        }
        
        echo json_encode(['suppliers' => $suppliers]);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Type d\'analytique non supporté']);
}
?>
*/

/**
 * check-supplier-issues.php
 * Endpoint pour vérifier les problèmes avec les fournisseurs
 */
/*
<?php
require_once 'includes/config.php';
require_once 'includes/auth_check.php';

header('Content-Type: application/json');

$issues = [];

// Fournisseurs actifs sans produits
$sql = "SELECT s.id, s.name FROM suppliers s 
        LEFT JOIN products p ON s.id = p.supplier_id 
        WHERE s.status = 'active' AND p.id IS NULL";
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    $issues[] = [
        'type' => 'no_products',
        'supplier_id' => $row['id'],
        'supplier_name' => $row['name'],
        'message' => 'Aucun produit associé',
        'severity' => 'warning'
    ];
}

// Fournisseurs avec délai très long
$sql = "SELECT id, name, average_delivery_time FROM suppliers 
        WHERE status = 'active' AND average_delivery_time > 45";
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    $issues[] = [
        'type' => 'long_delivery',
        'supplier_id' => $row['id'],
        'supplier_name' => $row['name'],
        'message' => "Délai très long: {$row['average_delivery_time']} jours",
        'severity' => 'high'
    ];
}

// Fournisseurs avec beaucoup de ruptures
$sql = "SELECT s.id, s.name, 
               COUNT(p.id) as total,
               COUNT(CASE WHEN p.stock = 0 THEN 1 END) as out_of_stock
        FROM suppliers s
        JOIN products p ON s.id = p.supplier_id
        WHERE s.status = 'active' AND p.status = 'active'
        GROUP BY s.id
        HAVING total > 3 AND (out_of_stock / total) > 0.6";
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    $issues[] = [
        'type' => 'stock_issues',
        'supplier_id' => $row['id'],
        'supplier_name' => $row['name'],
        'message' => "Beaucoup de ruptures: {$row['out_of_stock']}/{$row['total']}",
        'severity' => 'medium'
    ];
}

echo json_encode($issues);
?>
*/

// Documentation pour l'intégration
?>

<!-- Documentation d'utilisation du système de fournisseurs -->
<!--
FONCTIONNALITÉS PRINCIPALES:
============================

1. GESTION DES FOURNISSEURS
   - Ajout/modification/suppression de fournisseurs
   - Validation en temps réel des données
   - Gestion des statuts (actif/inactif)
   - Informations complètes (contact, logistique, notes)

2. SYSTÈME DE RECHERCHE ET FILTRES
   - Recherche par nom, contact, email
   - Filtres par pays et statut
   - Tri par différents critères
   - Pagination avancée

3. STATISTIQUES ET ANALYTIQUES
   - Cartes de statistiques en temps réel
   - Graphiques de distribution
   - Analyse des performances
   - Détection automatique de problèmes

4. IMPORT/EXPORT
   - Export CSV personnalisable
   - Import de données en masse
   - Sauvegarde automatique des formulaires

5. NOTIFICATIONS ET ALERTES
   - Vérification périodique des problèmes
   - Notifications en temps réel
   - Système d'alertes visuelles

STRUCTURE DES FICHIERS:
======================

suppliers.php                 - Page principale
get_supplier_details.php      - Détails AJAX d'un fournisseur
export_suppliers.php          - Export CSV des fournisseurs
validate-supplier-data.php    - Validation en temps réel
supplier-analytics.php        - Données pour graphiques
check-supplier-issues.php     - Vérification des problèmes

CONFIGURATION REQUISE:
=====================

1. Base de données:
   - Table 'suppliers' avec tous les champs nécessaires
   - Relations avec les tables 'products' et 'orders'

2. JavaScript:
   - Chart.js pour les graphiques
   - Support des modules ES6
   - Fetch API pour les requêtes AJAX

3. PHP:
   - Version 7.4+ recommandée
   - Extension MySQLi activée
   - Support JSON

UTILISATION:
===========

1. Intégrer les fichiers dans votre système d'administration
2. Configurer les connexions à la base de données
3. Personnaliser les styles CSS selon votre thème
4. Tester les fonctionnalités AJAX
5. Configurer les permissions d'accès

PERSONNALISATION:
================

- Modifier les couleurs dans les variables CSS
- Adapter les champs du formulaire selon vos besoins
- Personnaliser les notifications
- Ajouter des validations spécifiques

MAINTENANCE:
============

- Vérifier régulièrement les logs d'erreur
- Mettre à jour les statistiques
- Optimiser les requêtes selon la taille des données
- Sauvegarder les configurations
-->

<?php
/**
 * Notes techniques pour les développeurs
 * 
 * OPTIMISATIONS POSSIBLES:
 * - Mise en cache des statistiques (Redis/Memcached)
 * - Indexation des champs de recherche en base
 * - Pagination avec curseur pour de gros volumes
 * - WebSockets pour les notifications temps réel
 * 
 * SÉCURITÉ:
 * - Validation stricte des entrées utilisateur
 * - Échappement des données d'affichage
 * - Protection CSRF sur tous les formulaires
 * - Limitation du taux de requêtes AJAX
 * 
 * PERFORMANCE:
 * - Lazy loading des données non critiques
 * - Compression des réponses JSON
 * - Optimisation des requêtes SQL complexes
 * - Mise en cache des résultats fréquents
 * 
 * EXTENSIBILITÉ:
 * - Architecture modulaire pour nouvelles fonctionnalités
 * - Hooks pour personnalisations tierces
 * - API REST pour intégrations externes
 * - Support multi-langues prévu
 */

// Fin du système de gestion des fournisseurs
echo "<!-- Système de gestion des fournisseurs chargé avec succès -->";
?>