<?php 
// Activer la mise en tampon de sortie
ob_start();

// Inclure le fichier d'authentification
require_once 'auth.php';

// Variables définies dans auth.php — déclarations pour l'analyseur statique
$current_page              = $current_page ?? basename($_SERVER['PHP_SELF']);
$pending_orders_count      = $pending_orders_count ?? 0;
$out_of_stock_products_count = $out_of_stock_products_count ?? 0;

// Récupérer le titre de la page (défini avant l'inclusion de l'en-tête)
$page_title = isset($page_title) ? $page_title : 'Administration';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Administration Netcrafter</title>
    <link rel="icon" type="image/png" href="../image/logo-n.png">
    <link rel="shortcut icon" type="image/png" href="../image/logo-n.png">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Datepicker CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <!-- Custom styles -->
    <style>
        /* Sidebar custom scrollbar */
        .sidebar-scroll::-webkit-scrollbar {
            width: 4px;
        }
        
        .sidebar-scroll::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-scroll::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 2px;
        }
        
        .sidebar-scroll::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        /* Table custom styles */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Select2 custom styles for dark mode */
        .dark .select2-container--default .select2-selection--single,
        .dark .select2-container--default .select2-selection--multiple {
            background-color: #374151;
            border-color: #4B5563;
            color: #F9FAFB;
        }
        
        .dark .select2-container--default .select2-selection__rendered {
            color: #F9FAFB;
        }
        
        .dark .select2-dropdown {
            background-color: #374151;
            border-color: #4B5563;
        }
        
        .dark .select2-container--default .select2-results__option {
            color: #F9FAFB;
        }
        
        .dark .select2-container--default .select2-results__option[aria-selected=true] {
            background-color: #2D3748;
        }
        
        .dark .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #3B82F6;
            color: white;
        }
        
        /* Flatpickr dark mode */
        .dark .flatpickr-calendar {
            background: #374151;
            border-color: #4B5563;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3), 0 4px 6px -2px rgba(0, 0, 0, 0.2);
        }
        
        .dark .flatpickr-day {
            color: #F9FAFB;
        }
        
        .dark .flatpickr-day.selected {
            background: #3B82F6;
            border-color: #3B82F6;
        }
        
        .dark .flatpickr-months .flatpickr-month,
        .dark .flatpickr-current-month .flatpickr-monthDropdown-months,
        .dark .flatpickr-current-month input.cur-year {
            color: #F9FAFB;
            fill: #F9FAFB;
        }
        
        .dark .flatpickr-weekday {
            color: #9CA3AF;
        }
        
        /* Fade-in animation */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .fade-in {
            animation: fadeIn 0.3s ease-in-out;
        }
        
        /* Toast notifications */
        .toast {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 9999;
            padding: 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            transform: translateY(-100%);
            opacity: 0;
            transition: all 0.3s ease;
        }
        
        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }

        /* ── Light-mode content fixes ──────────────────────────────────── */
        html:not(.dark) .bg-gray-900  { background-color: #f9fafb; }
        html:not(.dark) .bg-gray-800  { background-color: #ffffff; }
        html:not(.dark) .bg-gray-700  { background-color: #f3f4f6; }
        html:not(.dark) .border-gray-700 { border-color: #e5e7eb; }
        html:not(.dark) .border-gray-600 { border-color: #d1d5db; }
        html:not(.dark) .text-white   { color: #111827; }
        html:not(.dark) .text-gray-300 { color: #4b5563; }
        html:not(.dark) .hover\:text-white:hover    { color: #111827; }
        html:not(.dark) .hover\:text-gray-200:hover { color: #374151; }
        html:not(.dark) .divide-gray-700 > :not([hidden]) ~ :not([hidden]) { border-color: #e5e7eb; }
        html:not(.dark) .hover\:bg-gray-750:hover,
        html:not(.dark) .hover\:bg-gray-700:hover   { background-color: #f3f4f6; }
        html:not(.dark) .hover\:bg-gray-600:hover   { background-color: #e5e7eb; }

        /* Form controls */
        html:not(.dark) input:not([type="radio"]):not([type="checkbox"]):not([type="file"]),
        html:not(.dark) select,
        html:not(.dark) textarea {
            background-color: #f9fafb !important;
            border-color: #d1d5db !important;
            color: #111827 !important;
        }
        html:not(.dark) input:not([type="radio"]):not([type="checkbox"])::placeholder,
        html:not(.dark) textarea::placeholder { color: #9ca3af; }
        html:not(.dark) input[type="file"] { background-color: #f9fafb; color: #111827; }

        /* Preserve white text on colored action buttons */
        html:not(.dark) .bg-blue-600.text-white,
        html:not(.dark) .bg-blue-700.text-white,
        html:not(.dark) .bg-red-600.text-white,
        html:not(.dark) .bg-red-700.text-white,
        html:not(.dark) .bg-green-600.text-white { color: #ffffff; }

        /* Flash / alert messages */
        html:not(.dark) [class*="bg-green-900"] { background-color: #f0fdf4; }
        html:not(.dark) [class*="bg-red-900"]   { background-color: #fef2f2; }
        html:not(.dark) [class*="bg-blue-900"]  { background-color: #eff6ff; }
        html:not(.dark) [class*="bg-yellow-900"]{ background-color: #fefce8; }
        html:not(.dark) .text-green-300 { color: #15803d; }
        html:not(.dark) .text-red-300   { color: #b91c1c; }
        html:not(.dark) .text-blue-300  { color: #1d4ed8; }
        html:not(.dark) .text-yellow-300{ color: #a16207; }

        /* ── Restore sidebar dark theme ─────────────────────────────── */
        html:not(.dark) #sidebar { background-color: #1f2937; }
        html:not(.dark) #sidebar .bg-gray-900 { background-color: #111827; }
        html:not(.dark) #sidebar .bg-gray-800 { background-color: #1f2937; }
        html:not(.dark) #sidebar .bg-gray-700 { background-color: #374151; }
        html:not(.dark) #sidebar .border-gray-700 { border-color: #374151; }
        html:not(.dark) #sidebar .text-white   { color: #ffffff; }
        html:not(.dark) #sidebar .text-gray-300 { color: #d1d5db; }
        html:not(.dark) #sidebar .text-gray-400 { color: #9ca3af; }
        html:not(.dark) #sidebar .hover\:bg-gray-700:hover  { background-color: #374151 !important; }
        html:not(.dark) #sidebar .hover\:text-white:hover   { color: #ffffff; }
        html:not(.dark) #sidebar .hover\:text-gray-200:hover { color: #d1d5db; }
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
<body class="bg-gray-100 dark:bg-gray-900 text-gray-800 dark:text-white">
    <!-- Toast container for notifications -->
    <div id="toast-container"></div>
    
    <!-- Layout container -->
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-gray-800 dark:bg-gray-900 text-white transform transition-transform duration-300 ease-in-out -translate-x-full md:translate-x-0">
            <!-- Sidebar Header -->
            <div class="flex items-center justify-between p-4 border-b border-gray-700">
                <div class="flex items-center space-x-2">
                    <img src="../image/logo-n.png" alt="Netcrafter Logo" class="h-8">
                    <span class="text-xl font-bold">Netcrafter</span>
                </div>
                <button id="sidebar-close" class="md:hidden text-gray-400 hover:text-white">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <!-- Sidebar Menu -->
            <nav class="sidebar-scroll py-4 overflow-y-auto h-[calc(100vh-64px)]">
                <ul class="space-y-1 px-2">
                    <!-- Dashboard -->
                    <li>
                        <a href="index.php" class="flex items-center px-4 py-2 rounded-md <?php echo $current_page === 'index.php' ? 'bg-netblue-600 text-white' : 'text-gray-300 hover:bg-gray-700'; ?>">
                            <i class="fas fa-tachometer-alt w-5 h-5 mr-3"></i>
                            <span>Tableau de bord</span>
                        </a>
                    </li>
                    
                    <!-- Orders -->
                    <li class="relative">
                        <button type="button" class="flex items-center justify-between w-full px-4 py-2 rounded-md text-left <?php echo in_array($current_page, ['orders.php', 'order_details.php', 'order_tracking.php', 'invoice.php']) ? 'bg-netblue-600 text-white' : 'text-gray-300 hover:bg-gray-700'; ?>" id="orders-menu-button">
                            <div class="flex items-center">
                                <i class="fas fa-shopping-cart w-5 h-5 mr-3"></i>
                                <span>Commandes</span>
                            </div>
                            <div class="flex items-center">
                                <?php if ($pending_orders_count > 0): ?>
                                <span class="inline-flex items-center justify-center px-2 py-1 mr-2 text-xs font-bold leading-none text-red-100 bg-red-500 rounded-full"><?php echo $pending_orders_count; ?></span>
                                <?php endif; ?>
                                <i class="fas fa-chevron-down text-xs"></i>
                            </div>
                        </button>
                        <div class="mt-1 space-y-1 px-2 <?php echo in_array($current_page, ['orders.php', 'order_details.php', 'order_tracking.php', 'invoice.php']) ? 'block' : 'hidden'; ?>" id="orders-submenu">
                            <a href="orders.php" class="flex items-center px-4 py-2 rounded-md text-sm <?php echo $current_page === 'orders.php' ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-gray-200'; ?>">
                                <i class="fas fa-list w-5 h-5 mr-3"></i>
                                <span>Liste des commandes</span>
                            </a>
                            <a href="order_tracking.php" class="flex items-center px-4 py-2 rounded-md text-sm <?php echo $current_page === 'order_tracking.php' ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-gray-200'; ?>">
                                <i class="fas fa-truck w-5 h-5 mr-3"></i>
                                <span>Suivi des expéditions</span>
                            </a>
                        </div>
                    </li>
                    
                    <!-- Products -->
                    <li class="relative">
                        <button type="button" class="flex items-center justify-between w-full px-4 py-2 rounded-md text-left <?php echo in_array($current_page, ['products.php', 'add_product.php', 'edit_product.php', 'categories.php']) ? 'bg-netblue-600 text-white' : 'text-gray-300 hover:bg-gray-700'; ?>" id="products-menu-button">
                            <div class="flex items-center">
                                <i class="fas fa-box w-5 h-5 mr-3"></i>
                                <span>Produits</span>
                            </div>
                            <div class="flex items-center">
                                <?php if ($out_of_stock_products_count > 0): ?>
                                <span class="inline-flex items-center justify-center px-2 py-1 mr-2 text-xs font-bold leading-none text-red-100 bg-red-500 rounded-full"><?php echo $out_of_stock_products_count; ?></span>
                                <?php endif; ?>
                                <i class="fas fa-chevron-down text-xs"></i>
                            </div>
                        </button>
                        <div class="mt-1 space-y-1 px-2 <?php echo in_array($current_page, ['products.php', 'add_product.php', 'edit_product.php', 'categories.php']) ? 'block' : 'hidden'; ?>" id="products-submenu">
                            <a href="products.php" class="flex items-center justify-between px-4 py-2 rounded-md text-sm <?php echo $current_page === 'products.php' ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-gray-200'; ?>">
                                <div class="flex items-center">
                                    <i class="fas fa-list w-5 h-5 mr-3"></i>
                                    <span>Liste des produits</span>
                                </div>
                                <?php if ($out_of_stock_products_count > 0): ?>
                                <span class="inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-red-100 bg-red-500 rounded-full"><?php echo $out_of_stock_products_count; ?></span>
                                <?php endif; ?>
                            </a>
                            <a href="add_product.php" class="flex items-center px-4 py-2 rounded-md text-sm <?php echo $current_page === 'add_product.php' ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-gray-200'; ?>">
                                <i class="fas fa-plus w-5 h-5 mr-3"></i>
                                <span>Ajouter un produit</span>
                            </a>
                            <a href="categories.php" class="flex items-center px-4 py-2 rounded-md text-sm <?php echo $current_page === 'categories.php' ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-gray-200'; ?>">
                                <i class="fas fa-tags w-5 h-5 mr-3"></i>
                                <span>Catégories</span>
                            </a>
                        </div>
                    </li>
                    
                    <!-- Customers -->
                    <li>
                        <a href="customers.php" class="flex items-center px-4 py-2 rounded-md <?php echo $current_page === 'customers.php' ? 'bg-netblue-600 text-white' : 'text-gray-300 hover:bg-gray-700'; ?>">
                            <i class="fas fa-users w-5 h-5 mr-3"></i>
                            <span>Clients</span>
                        </a>
                    </li>
                    
                    <!-- Suppliers -->
                    <li>
                        <a href="suppliers.php" class="flex items-center px-4 py-2 rounded-md <?php echo $current_page === 'suppliers.php' ? 'bg-netblue-600 text-white' : 'text-gray-300 hover:bg-gray-700'; ?>">
                            <i class="fas fa-truck w-5 h-5 mr-3"></i>
                            <span>Fournisseurs</span>
                        </a>
                    </li>
                    
                    <!-- Reports -->
                    <li>
                        <a href="reports.php" class="flex items-center px-4 py-2 rounded-md <?php echo $current_page === 'reports.php' ? 'bg-netblue-600 text-white' : 'text-gray-300 hover:bg-gray-700'; ?>">
                            <i class="fas fa-chart-bar w-5 h-5 mr-3"></i>
                            <span>Rapports</span>
                        </a>
                    </li>

                    <!-- Formations -->
                    <li class="relative">
                        <button type="button" class="flex items-center justify-between w-full px-4 py-2 rounded-md text-left <?php echo in_array($current_page, ['formations.php', 'add_formation.php', 'edit_formation.php', 'formation_categories.php']) ? 'bg-netblue-600 text-white' : 'text-gray-300 hover:bg-gray-700'; ?>" id="formations-menu-button">
                            <div class="flex items-center">
                                <i class="fas fa-graduation-cap w-5 h-5 mr-3"></i>
                                <span>Formations</span>
                            </div>
                            <i class="fas fa-chevron-down text-xs ml-2"></i>
                        </button>
                        <div class="mt-1 space-y-1 px-2 <?php echo in_array($current_page, ['formations.php', 'add_formation.php', 'edit_formation.php']) ? 'block' : 'hidden'; ?>" id="formations-submenu">
                            <a href="formations.php" class="flex items-center px-4 py-2 rounded-md text-sm <?php echo $current_page === 'formations.php' ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-gray-200'; ?>">
                                <i class="fas fa-list w-5 h-5 mr-3"></i>
                                <span>Liste des formations</span>
                            </a>
                            <a href="add_formation.php" class="flex items-center px-4 py-2 rounded-md text-sm <?php echo $current_page === 'add_formation.php' ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-gray-200'; ?>">
                                <i class="fas fa-plus w-5 h-5 mr-3"></i>
                                <span>Ajouter une formation</span>
                            </a>
                            <a href="formations.php?tab=subscriptions" class="flex items-center px-4 py-2 rounded-md text-sm text-gray-400 hover:bg-gray-700 hover:text-gray-200">
                                <i class="fas fa-users w-5 h-5 mr-3"></i>
                                <span>Abonnements</span>
                            </a>
                            <a href="formation_categories.php" class="flex items-center px-4 py-2 rounded-md text-sm <?php echo $current_page === 'formation_categories.php' ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-gray-200'; ?>">
                                <i class="fas fa-tags w-5 h-5 mr-3"></i>
                                <span>Catégories</span>
                            </a>
                        </div>
                    </li>

                    <!-- Portfolio -->
                    <li>
                        <a href="portfolio.php" class="flex items-center px-4 py-2 rounded-md <?php echo $current_page === 'portfolio.php' ? 'bg-netblue-600 text-white' : 'text-gray-300 hover:bg-gray-700'; ?>">
                            <i class="fas fa-layer-group w-5 h-5 mr-3"></i>
                            <span>Portfolio</span>
                        </a>
                    </li>

                    <!-- Blog -->
                    <li>
                        <a href="blog.php" class="flex items-center px-4 py-2 rounded-md <?php echo $current_page === 'blog.php' ? 'bg-netblue-600 text-white' : 'text-gray-300 hover:bg-gray-700'; ?>">
                            <i class="fas fa-rss w-5 h-5 mr-3"></i>
                            <span>Blog</span>
                        </a>
                    </li>

                    <!-- Cahiers des charges -->
                    <li>
                        <a href="cahier.php" class="flex items-center px-4 py-2 rounded-md <?php echo $current_page === 'cahier.php' ? 'bg-netblue-600 text-white' : 'text-gray-300 hover:bg-gray-700'; ?>">
                            <i class="fas fa-file-contract w-5 h-5 mr-3"></i>
                            <span>Cahiers des charges</span>
                        </a>
                    </li>

                    <!-- Promo Codes -->
                    <li>
                        <a href="promo-codes.php" class="flex items-center px-4 py-2 rounded-md <?php echo $current_page === 'promo-codes.php' ? 'bg-netblue-600 text-white' : 'text-gray-300 hover:bg-gray-700'; ?>">
                            <i class="fas fa-tag w-5 h-5 mr-3"></i>
                            <span>Codes promo</span>
                        </a>
                    </li>

                    <!-- Settings -->
                    <li class="relative">
                        <button type="button" class="flex items-center justify-between w-full px-4 py-2 rounded-md text-left <?php echo in_array($current_page, ['settings.php', 'users.php']) ? 'bg-netblue-600 text-white' : 'text-gray-300 hover:bg-gray-700'; ?>" id="settings-menu-button">
                            <div class="flex items-center">
                                <i class="fas fa-cog w-5 h-5 mr-3"></i>
                                <span>Paramètres</span>
                            </div>
                            <i class="fas fa-chevron-down text-xs ml-2"></i>
                        </button>
                        <div class="mt-1 space-y-1 px-2 <?php echo in_array($current_page, ['settings.php', 'users.php']) ? 'block' : 'hidden'; ?>" id="settings-submenu">
                            <a href="settings.php" class="flex items-center px-4 py-2 rounded-md text-sm <?php echo $current_page === 'settings.php' ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-gray-200'; ?>">
                                <i class="fas fa-store w-5 h-5 mr-3"></i>
                                <span>Paramètres de la boutique</span>
                            </a>
                            <a href="users.php" class="flex items-center px-4 py-2 rounded-md text-sm <?php echo $current_page === 'users.php' ? 'bg-gray-700 text-white' : 'text-gray-400 hover:bg-gray-700 hover:text-gray-200'; ?>">
                                <i class="fas fa-user-shield w-5 h-5 mr-3"></i>
                                <span>Utilisateurs admin</span>
                            </a>
                        </div>
                    </li>
                </ul>
                
                <!-- Sidebar Footer -->
                <div class="mt-6 px-6 py-4 border-t border-gray-700">
                    <a href="../shop.php" class="flex items-center text-gray-400 hover:text-white">
                        <i class="fas fa-external-link-alt mr-2"></i>
                        <span class="text-sm">Voir la boutique</span>
                    </a>
                </div>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <div class="flex-1 md:ml-64 transition-all duration-300 ease-in-out flex flex-col min-h-screen">
            <!-- Top Navigation Bar -->
            <header class="bg-white dark:bg-gray-800 shadow-sm">
                <div class="flex items-center justify-between px-4 py-3">
                    <!-- Mobile Menu Button -->
                    <button id="sidebar-toggle" class="md:hidden text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    
                    <!-- Page Title - Mobile -->
                    <h1 class="text-lg font-bold text-gray-700 dark:text-white md:hidden"><?php echo $page_title; ?></h1>
                    
                    <!-- Right Navigation Items -->
                    <div class="flex items-center space-x-4">
                        <!-- Dark Mode Toggle -->
                        <button id="dark-mode-toggle" class="text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">
                            <i class="fas fa-moon dark:hidden"></i>
                            <i class="fas fa-sun hidden dark:inline"></i>
                        </button>
                        
                        <!-- User Profile Dropdown -->
                        <div class="relative" id="user-menu">
                            <button id="user-menu-button" class="flex items-center text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">
                                <img class="h-8 w-8 rounded-full object-cover" src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['admin_username']); ?>&background=3B82F6&color=fff" alt="Profile">
                                <span class="ml-2 text-sm font-medium hidden md:block"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                                <i class="fas fa-chevron-down text-xs ml-2"></i>
                            </button>
                            <div id="user-dropdown" class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-md shadow-lg py-1 z-10 hidden">
                                <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-user mr-2"></i>
                                    Mon profil
                                </a>
                                <a href="settings.php" class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-cog mr-2"></i>
                                    Paramètres
                                </a>
                                <div class="border-t border-gray-200 dark:border-gray-700"></div>
                                <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <i class="fas fa-sign-out-alt mr-2"></i>
                                    Déconnexion
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <!-- Main Content Area -->
            <main class="p-4 fade-in flex-grow overflow-y-auto">
                <!-- Desktop Page Title -->
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-white hidden md:block"><?php echo $page_title; ?></h1>
                    
                    <!-- Breadcrumbs can be added here if needed -->
                </div>
                
                <!-- Content starts here -->
                <div class="space-y-6">