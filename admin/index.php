<?php
// Titre de la page pour l'inclusion de l'en-tête
$page_title = "Tableau de bord";

// Inclusion de l'en-tête
require_once 'includes/header.php';

// Inclure les fonctions spécifiques aux commandes pour avoir accès aux fonctions de statistiques
require_once 'order_functions.php';

// Récupérer les statistiques pour le tableau de bord
// Utiliser les fonctions définies dans order_functions.php
$total_customers = getTotalCustomersCount($conn);
$total_revenue = getSalesTotal($conn, 'all');
$pending_orders = getOrdersCount($conn, 'all', 'pending');
$processing_orders = getOrdersCount($conn, 'all', 'processing');

// Récupérer le nombre total de produits
$products_query = "SELECT COUNT(*) as count FROM products";
$products_result = $conn->query($products_query);
$total_products = 0;
if ($products_result && $products_result->num_rows > 0) {
    $row = $products_result->fetch_assoc();
    $total_products = $row['count'];
}

// Récupérer le nombre total de commandes
$total_orders = getOrdersCount($conn, 'all');

// Récupérer les commandes récentes
$recent_orders = getRecentOrders($conn, 5);

// Récupérer les produits populaires (les plus vendus)
$popular_products = getBestSellingProducts($conn, 5);

// Récupérer les données pour le graphique des ventes (6 derniers mois)
$sales_stats = getSalesStats($conn, 'monthly', 6);
$sales_data = [];

if (!empty($sales_stats)) {
    $months = [];
    $sales = [];
    
    foreach ($sales_stats as $stat) {
        $months[] = $stat['period_label'];
        $sales[] = (float) $stat['total_sales'];
    }
    
    $sales_data = [
        'labels' => $months,
        'data' => $sales
    ];
}

// Récupérer la répartition des commandes par statut
$orders_by_status_query = "SELECT 
                           order_status, 
                           COUNT(*) as count
                         FROM 
                           orders
                         GROUP BY 
                           order_status";

$orders_by_status_result = $conn->query($orders_by_status_query);
$orders_by_status = [];

if ($orders_by_status_result && $orders_by_status_result->num_rows > 0) {
    $statuses = [];
    $counts = [];
    
    while ($row = $orders_by_status_result->fetch_assoc()) {
        $status = ucfirst($row['order_status']);
        
        switch ($row['order_status']) {
            case 'pending':
                $status = 'En attente';
                break;
            case 'processing':
                $status = 'En traitement';
                break;
            case 'shipped':
                $status = 'Expédiée';
                break;
            case 'delivered':
                $status = 'Livrée';
                break;
            case 'cancelled':
                $status = 'Annulée';
                break;
            case 'returned':
                $status = 'Retournée';
                break;
        }
        
        $statuses[] = $status;
        $counts[] = (int) $row['count'];
    }
    
    $orders_by_status = [
        'labels' => $statuses,
        'data' => $counts
    ];
}
?>

<!-- Dashboard Content -->
<div class="space-y-6">
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total des ventes -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="text-gray-500 dark:text-gray-400 text-sm font-medium">Total des ventes</h3>
                    <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo number_format($total_revenue, 2); ?> FCFA</p>
                </div>
                <div class="p-3 rounded-full bg-green-100 dark:bg-green-900 text-green-600 dark:text-green-400">
                    <i class="fas fa-euro-sign text-xl"></i>
                </div>
            </div>
            <div class="flex items-center">
                <span class="text-green-500 dark:text-green-400 text-sm font-medium">
                    <i class="fas fa-arrow-up mr-1"></i><?php echo number_format(getSalesTotal($conn, 'month') / max(1, getSalesTotal($conn, 'year')) * 100, 1); ?>%
                </span>
                <span class="text-gray-500 dark:text-gray-400 text-sm ml-2">Ce mois-ci</span>
            </div>
        </div>

        <!-- Commandes -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="text-gray-500 dark:text-gray-400 text-sm font-medium">Commandes</h3>
                    <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo $total_orders; ?></p>
                </div>
                <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-400">
                    <i class="fas fa-shopping-cart text-xl"></i>
                </div>
            </div>
            <div class="flex items-center">
                <span class="text-blue-500 dark:text-blue-400 text-sm font-medium">
                    <i class="fas fa-arrow-up mr-1"></i><?php echo number_format(getOrdersCount($conn, 'month') / max(1, getOrdersCount($conn, 'year')) * 100, 1); ?>%
                </span>
                <span class="text-gray-500 dark:text-gray-400 text-sm ml-2">Ce mois-ci</span>
            </div>
        </div>
        
        <!-- Clients -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="text-gray-500 dark:text-gray-400 text-sm font-medium">Clients</h3>
                    <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo $total_customers; ?></p>
                </div>
                <div class="p-3 rounded-full bg-purple-100 dark:bg-purple-900 text-purple-600 dark:text-purple-400">
                    <i class="fas fa-users text-xl"></i>
                </div>
            </div>
            <div class="flex items-center">
                <span class="text-purple-500 dark:text-purple-400 text-sm font-medium">
                    <i class="fas fa-arrow-up mr-1"></i>5%
                </span>
                <span class="text-gray-500 dark:text-gray-400 text-sm ml-2">Ce mois-ci</span>
            </div>
        </div>
        
        <!-- Produits -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h3 class="text-gray-500 dark:text-gray-400 text-sm font-medium">Produits</h3>
                    <p class="text-2xl font-bold text-gray-800 dark:text-white"><?php echo $total_products; ?></p>
                </div>
                <div class="p-3 rounded-full bg-orange-100 dark:bg-orange-900 text-orange-600 dark:text-orange-400">
                    <i class="fas fa-box text-xl"></i>
                </div>
            </div>
            <div class="flex items-center">
                <span class="text-orange-500 dark:text-orange-400 text-sm font-medium">
                    <i class="fas fa-plus mr-1"></i><?php echo $out_of_stock_products_count; ?>
                </span>
                <span class="text-gray-500 dark:text-gray-400 text-sm ml-2">En rupture de stock</span>
            </div>
        </div>
    </div>

    <!-- Orders Status Summary -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <!-- En attente -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 border-l-4 border-yellow-500">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-100 dark:bg-yellow-900 text-yellow-600 dark:text-yellow-400 mr-4">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">En attente</p>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white"><?php echo $pending_orders; ?></h3>
                </div>
            </div>
        </div>
        
        <!-- En traitement -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 border-l-4 border-blue-500">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-400 mr-4">
                    <i class="fas fa-cogs"></i>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">En traitement</p>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white"><?php echo $processing_orders; ?></h3>
                </div>
            </div>
        </div>
        
        <!-- Expédiées -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 border-l-4 border-purple-500">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 dark:bg-purple-900 text-purple-600 dark:text-purple-400 mr-4">
                    <i class="fas fa-shipping-fast"></i>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">Expédiées</p>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white"><?php echo getOrdersCount($conn, 'all', 'shipped'); ?></h3>
                </div>
            </div>
        </div>
        
        <!-- Livrées -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 border-l-4 border-green-500">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 dark:bg-green-900 text-green-600 dark:text-green-400 mr-4">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <p class="text-gray-500 dark:text-gray-400 text-sm">Livrées</p>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white"><?php echo getOrdersCount($conn, 'all', 'delivered'); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Sales Chart and Orders by Status -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Sales Chart -->
        <div class="lg:col-span-2 bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <h3 class="text-xl font-bold mb-6 text-gray-800 dark:text-white">Évolution des ventes</h3>
            <div class="h-80">
                <canvas id="salesChart"></canvas>
            </div>
        </div>
        
        <!-- Orders by Status -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <h3 class="text-xl font-bold mb-6 text-gray-800 dark:text-white">Commandes par statut</h3>
            <div class="h-80">
                <canvas id="ordersStatusChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Recent Orders and Popular Products -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Orders -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-800 dark:text-white">Commandes récentes</h3>
                <a href="orders.php" class="text-netblue-600 dark:text-netblue-400 hover:underline text-sm font-medium">
                    Voir toutes <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            
            <?php if (!empty($recent_orders)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">N° Commande</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Client</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Montant</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Statut</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($recent_orders as $order): ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-white">
                                <a href="order_details.php?id=<?php echo $order['id']; ?>" class="hover:text-netblue-600 dark:hover:text-netblue-400">
                                    #<?php echo $order['order_number']; ?>
                                </a>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                <?php if (!empty($order['username']) || !empty($order['email'])): ?>
                                    <?php echo !empty($order['username']) ? htmlspecialchars($order['username']) : htmlspecialchars($order['email']); ?>
                                <?php else: ?>
                                    Client anonyme
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                <?php echo formatDate($order['created_at']); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                <?php echo number_format($order['total_amount'], 2); ?> FCFA
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                <?php echo getOrderStatusBadge($order['order_status']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-8">
                <p class="text-gray-600 dark:text-gray-400">Aucune commande récente</p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Popular Products -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-800 dark:text-white">Produits les plus vendus</h3>
                <a href="products.php" class="text-netblue-600 dark:text-netblue-400 hover:underline text-sm font-medium">
                    Voir tous <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            
            <?php if (!empty($popular_products)): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Produit</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Catégorie</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Prix</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Stock</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Ventes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <?php foreach ($popular_products as $product): ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-800 dark:text-white">
                                <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="hover:text-netblue-600 dark:hover:text-netblue-400">
                                    <?php echo htmlspecialchars($product['name']); ?>
                                </a>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                <?php echo htmlspecialchars($product['category_name']); ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                <?php echo number_format($product['price'], 2); ?> FCFA
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                <?php if ($product['stock'] > 10): ?>
                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        <?php echo $product['stock']; ?>
                                    </span>
                                <?php elseif ($product['stock'] > 0): ?>
                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                        <?php echo $product['stock']; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                        Rupture
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                <?php echo $product['total_quantity']; ?> unités
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-8">
                <p class="text-gray-600 dark:text-gray-400">Aucune donnée de vente disponible</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Définir les couleurs pour le thème clair et sombre
    const isDarkMode = document.documentElement.classList.contains('dark');
    const gridColor = isDarkMode ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
    const textColor = isDarkMode ? '#9CA3AF' : '#6B7280';
    
    // Sales Chart
    const salesCtx = document.getElementById('salesChart').getContext('2d');
    const salesData = <?php echo json_encode($sales_data); ?>;
    
    if (salesData && salesData.labels && salesData.labels.length > 0) {
        new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: salesData.labels,
                datasets: [{
                    label: 'Ventes (FCFA)',
                    data: salesData.data,
                    backgroundColor: 'rgba(59, 130, 246, 0.2)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 2,
                    tension: 0.3,
                    pointBackgroundColor: 'rgba(59, 130, 246, 1)',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: 'rgba(59, 130, 246, 1)',
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: gridColor
                        },
                        ticks: {
                            color: textColor,
                            callback: function(value) {
                                return value + ' FCFA';
                            }
                        }
                    },
                    x: {
                        grid: {
                            color: gridColor
                        },
                        ticks: {
                            color: textColor
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        labels: {
                            color: textColor
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.raw + ' FCFA';
                            }
                        }
                    }
                }
            }
        });
    } else {
        document.getElementById('salesChart').parentElement.innerHTML = '<div class="flex items-center justify-center h-full"><p class="text-gray-500 dark:text-gray-400">Pas assez de données pour afficher le graphique</p></div>';
    }
    
    // Orders by Status Chart
    const ordersStatusCtx = document.getElementById('ordersStatusChart').getContext('2d');
    const ordersStatusData = <?php echo json_encode($orders_by_status); ?>;
    
    if (ordersStatusData && ordersStatusData.labels && ordersStatusData.labels.length > 0) {
        const statusColors = [
            'rgba(245, 158, 11, 0.7)', // pending - amber
            'rgba(59, 130, 246, 0.7)', // processing - blue
            'rgba(139, 92, 246, 0.7)', // shipped - purple
            'rgba(16, 185, 129, 0.7)', // delivered - green
            'rgba(239, 68, 68, 0.7)',  // cancelled - red
            'rgba(107, 114, 128, 0.7)' // returned - gray
        ];
        
        new Chart(ordersStatusCtx, {
            type: 'doughnut',
            data: {
                labels: ordersStatusData.labels,
                datasets: [{
                    data: ordersStatusData.data,
                    backgroundColor: statusColors,
                    borderColor: isDarkMode ? 'rgba(31, 41, 55, 1)' : 'white',
                    borderWidth: 2,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: textColor,
                            padding: 20,
                            font: {
                                size: 12
                            }
                        }
                    }
                }
            }
        });
    } else {
        document.getElementById('ordersStatusChart').parentElement.innerHTML = '<div class="flex items-center justify-center h-full"><p class="text-gray-500 dark:text-gray-400">Pas assez de données pour afficher le graphique</p></div>';
    }
});
</script>

<?php
// Inclusion du pied de page
require_once 'includes/footer.php';
?>