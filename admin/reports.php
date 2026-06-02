<?php
mysqli_report(MYSQLI_REPORT_OFF);
$page_title = 'Rapports & Statistiques';
require_once 'includes/header.php';
require_once 'order_functions.php';

// ── Shop stats (uses $conn from auth.php) ──────────────────────────────────
$revenue_total  = getSalesTotal($conn, 'all');
$revenue_month  = getSalesTotal($conn, 'month');
$revenue_week   = getSalesTotal($conn, 'week');
$orders_total   = getOrdersCount($conn, 'all');
$orders_pending = getOrdersCount($conn, 'all', 'pending');
$orders_month   = getOrdersCount($conn, 'month');

// Orders by status
$status_rows = [];
$_r = $conn->query("SELECT order_status, COUNT(*) n, SUM(total_amount) total FROM orders GROUP BY order_status ORDER BY n DESC");
if ($_r) while ($r = $_r->fetch_assoc()) $status_rows[] = $r;

// Revenue by month (last 12)
$monthly = [];
$_r = $conn->query("SELECT DATE_FORMAT(created_at,'%Y-%m') ym, DATE_FORMAT(created_at,'%b %Y') label,
    COUNT(*) orders, SUM(total_amount) revenue
    FROM orders WHERE payment_status='paid' AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY ym ORDER BY ym ASC");
if ($_r) while ($r = $_r->fetch_assoc()) $monthly[] = $r;

// Top products
$top_products = [];
$_r = $conn->query("SELECT p.name, SUM(oi.quantity) qty, SUM(oi.quantity * oi.unit_price) revenue
    FROM order_items oi JOIN products p ON p.id=oi.product_id
    GROUP BY oi.product_id ORDER BY qty DESC LIMIT 8");
if ($_r) while ($r = $_r->fetch_assoc()) $top_products[] = $r;

// Customers
$customers_total  = getTotalCustomersCount($conn);
$_r = $conn->query("SELECT COUNT(*) c FROM users WHERE is_admin=0 AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$customers_new    = $_r ? (int)$_r->fetch_assoc()['c'] : 0;

// ── Vitrine stats ──────────────────────────────────────────────────────────
$_vh = $_SERVER['HTTP_HOST'] ?? '';
$_vl = PHP_OS_FAMILY === 'Windows'
    || strpos($_vh,'localhost') !== false
    || strpos($_vh,'127.0.0.1') !== false
    || strpos($_vh,'::1')       !== false;
$vconn = new mysqli('localhost',
    $_vl ? 'root'                   : 'u264396140_netcrefternige',
    $_vl ? ''                       : 'Hondaand@1',
    $_vl ? 'netcrafter'             : 'u264396140_netcrafternige'
);
$portfolio_count = $blog_count = $blog_views = 0;
if (!$vconn->connect_error) {
    $vconn->set_charset('utf8mb4');
    $_r = $vconn->query("SELECT COUNT(*) c FROM portfolio_projects WHERE status='published'");
    if ($_r) $portfolio_count = (int)$_r->fetch_assoc()['c'];
    $_r = $vconn->query("SELECT COUNT(*) c FROM blog_posts WHERE status='published'");
    if ($_r) $blog_count = (int)$_r->fetch_assoc()['c'];
    $_r = $vconn->query("SELECT COALESCE(SUM(views),0) v FROM blog_posts");
    if ($_r) $blog_views = (int)$_r->fetch_assoc()['v'];
    $vconn->close();
}

// ── Formations stats ───────────────────────────────────────────────────────
$form_total = $form_subs = $form_rev = 0;
$f_cfg = $_vl ? ['localhost','root','','netcrafter_formation'] : ['localhost','u264396140_formation','Hondaand@1','u264396140_formation'];
$fconn = new mysqli($f_cfg[0],$f_cfg[1],$f_cfg[2],$f_cfg[3]);
if (!$fconn->connect_error) {
    $fconn->set_charset('utf8mb4');
    $_r = $fconn->query("SELECT COUNT(*) c FROM formations WHERE status='active'");
    if ($_r) $form_total = (int)$_r->fetch_assoc()['c'];
    $_r = $fconn->query("SELECT COUNT(*) c FROM formation_subscriptions WHERE status='active'");
    if ($_r) $form_subs = (int)$_r->fetch_assoc()['c'];
    $fconn->close();
}

// Chart data
$chart_labels  = array_column($monthly, 'label');
$chart_revenue = array_column($monthly, 'revenue');
$chart_orders  = array_column($monthly, 'orders');

$statusColors = ['pending'=>'#F59E0B','processing'=>'#3B82F6','shipped'=>'#8B5CF6','delivered'=>'#10B981','cancelled'=>'#EF4444','returned'=>'#6B7280'];
$statusLabels = ['pending'=>'En attente','processing'=>'En traitement','shipped'=>'Expédiée','delivered'=>'Livrée','cancelled'=>'Annulée','returned'=>'Retournée'];
?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<div class="p-6 space-y-8">

    <!-- Page header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold dark:text-white">Rapports &amp; Statistiques</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Vue d'ensemble de toute l'activité Netcrafter</p>
        </div>
        <span class="text-xs text-gray-400 dark:text-gray-500">Mis à jour : <?= date('d/m/Y H:i') ?></span>
    </div>

    <!-- KPI row 1 : Revenue & Orders -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <?php foreach ([
            ['CA Total',         number_format($revenue_total,0,'.',' ').' FCFA', 'fa-coins',       'blue'],
            ['CA ce mois',       number_format($revenue_month,0,'.',' ').' FCFA', 'fa-calendar-alt','green'],
            ['Commandes totales',$orders_total,                                   'fa-shopping-cart','purple'],
            ['En attente',       $orders_pending,                                 'fa-clock',        'orange'],
        ] as $k): ?>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide"><?= $k[0] ?></p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1"><?= $k[1] ?></p>
                </div>
                <span class="w-10 h-10 rounded-xl bg-<?= $k[3] ?>-100 dark:bg-<?= $k[3] ?>-900/30 flex items-center justify-center">
                    <i class="fas <?= $k[2] ?> text-<?= $k[3] ?>-600 dark:text-<?= $k[3] ?>-400"></i>
                </span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- KPI row 2 : Platform activity -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <?php foreach ([
            ['Clients',           $customers_total, 'fa-users',          'indigo'],
            ['Nouveaux (30j)',     $customers_new,   'fa-user-plus',      'cyan'],
            ['Formations actives',$form_total,       'fa-graduation-cap', 'yellow'],
            ['Abonnés formations',$form_subs,        'fa-id-card',        'pink'],
        ] as $k): ?>
        <div class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide"><?= $k[0] ?></p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1"><?= $k[1] ?></p>
                </div>
                <span class="w-10 h-10 rounded-xl bg-<?= $k[3] ?>-100 dark:bg-<?= $k[3] ?>-900/30 flex items-center justify-center">
                    <i class="fas <?= $k[2] ?> text-<?= $k[3] ?>-600 dark:text-<?= $k[3] ?>-400"></i>
                </span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Charts row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Revenue / Orders chart -->
        <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <h2 class="font-semibold text-gray-900 dark:text-white mb-4">Évolution CA &amp; Commandes (12 mois)</h2>
            <?php if (empty($monthly)): ?>
            <div class="flex items-center justify-center h-48 text-gray-400">
                <div class="text-center">
                    <i class="fas fa-chart-line text-3xl mb-2"></i>
                    <p class="text-sm">Aucune donnée de vente disponible</p>
                </div>
            </div>
            <?php else: ?>
            <canvas id="revenueChart" height="90"></canvas>
            <?php endif; ?>
        </div>

        <!-- Orders by status donut -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <h2 class="font-semibold text-gray-900 dark:text-white mb-4">Commandes par statut</h2>
            <?php if (empty($status_rows)): ?>
            <div class="flex items-center justify-center h-48 text-gray-400">
                <div class="text-center">
                    <i class="fas fa-chart-pie text-3xl mb-2"></i>
                    <p class="text-sm">Aucune commande</p>
                </div>
            </div>
            <?php else: ?>
            <canvas id="statusChart" height="160"></canvas>
            <ul class="mt-4 space-y-1.5">
            <?php foreach ($status_rows as $s):
                $col = $statusColors[$s['order_status']] ?? '#6B7280';
                $lbl = $statusLabels[$s['order_status']] ?? $s['order_status'];
            ?>
            <li class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-400">
                <span class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-full" style="background:<?= $col ?>"></span>
                    <?= $lbl ?>
                </span>
                <span class="font-semibold text-gray-900 dark:text-white"><?= $s['n'] ?></span>
            </li>
            <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top products + Vitrine stats -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Top products -->
        <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <h2 class="font-semibold text-gray-900 dark:text-white mb-4">Top produits vendus</h2>
            <?php if (empty($top_products)): ?>
            <div class="flex items-center justify-center h-32 text-gray-400">
                <div class="text-center">
                    <i class="fas fa-box-open text-3xl mb-2"></i>
                    <p class="text-sm">Aucune vente enregistrée</p>
                </div>
            </div>
            <?php else: ?>
            <div class="space-y-3">
                <?php
                $maxQty = max(array_column($top_products, 'qty')) ?: 1;
                foreach ($top_products as $i => $p): ?>
                <div class="flex items-center gap-3">
                    <span class="w-6 h-6 rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 flex items-center justify-center text-xs font-bold flex-shrink-0"><?= $i+1 ?></span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm font-medium text-gray-900 dark:text-white truncate"><?= htmlspecialchars($p['name']) ?></span>
                            <span class="text-xs text-gray-500 dark:text-gray-400 ml-2 flex-shrink-0"><?= $p['qty'] ?> ventes</span>
                        </div>
                        <div class="h-1.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                            <div class="h-full bg-blue-500 rounded-full" style="width:<?= round($p['qty']/$maxQty*100) ?>%"></div>
                        </div>
                    </div>
                    <span class="text-xs font-semibold text-gray-700 dark:text-gray-300 flex-shrink-0"><?= number_format($p['revenue'],0,'.',' ') ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Vitrine & Blog stats -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-6">
            <h2 class="font-semibold text-gray-900 dark:text-white mb-4">Contenu du site</h2>
            <div class="space-y-4">
                <div class="flex items-center gap-4 p-3 rounded-xl bg-purple-50 dark:bg-purple-900/20">
                    <div class="w-10 h-10 rounded-xl bg-purple-100 dark:bg-purple-900/50 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-layer-group text-purple-600 dark:text-purple-400"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Portfolio publiés</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white"><?= $portfolio_count ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-4 p-3 rounded-xl bg-green-50 dark:bg-green-900/20">
                    <div class="w-10 h-10 rounded-xl bg-green-100 dark:bg-green-900/50 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-newspaper text-green-600 dark:text-green-400"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Articles de blog</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white"><?= $blog_count ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-4 p-3 rounded-xl bg-cyan-50 dark:bg-cyan-900/20">
                    <div class="w-10 h-10 rounded-xl bg-cyan-100 dark:bg-cyan-900/50 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-eye text-cyan-600 dark:text-cyan-400"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Vues blog totales</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white"><?= number_format($blog_views) ?></p>
                    </div>
                </div>
                <div class="flex items-center gap-4 p-3 rounded-xl bg-orange-50 dark:bg-orange-900/20">
                    <div class="w-10 h-10 rounded-xl bg-orange-100 dark:bg-orange-900/50 flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-calendar-week text-orange-600 dark:text-orange-400"></i>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Commandes (ce mois)</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white"><?= $orders_month ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div><!-- /p-6 -->

<?php if (!empty($monthly)): ?>
<script>
const isDark = document.documentElement.classList.contains('dark');
const gridColor = isDark ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.06)';
const textColor = isDark ? '#9CA3AF' : '#6B7280';

// Revenue chart
new Chart(document.getElementById('revenueChart'), {
    data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [
            {
                type: 'bar',
                label: 'CA (FCFA)',
                data: <?= json_encode($chart_revenue) ?>,
                backgroundColor: 'rgba(59,130,246,0.25)',
                borderColor: '#3B82F6',
                borderWidth: 2,
                borderRadius: 6,
                yAxisID: 'yRevenue',
            },
            {
                type: 'line',
                label: 'Commandes',
                data: <?= json_encode($chart_orders) ?>,
                borderColor: '#10B981',
                backgroundColor: 'rgba(16,185,129,0.1)',
                borderWidth: 2,
                pointRadius: 4,
                pointBackgroundColor: '#10B981',
                tension: 0.4,
                fill: true,
                yAxisID: 'yOrders',
            }
        ]
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { labels: { color: textColor, boxWidth: 12, padding: 16 } } },
        scales: {
            x:        { grid: { color: gridColor }, ticks: { color: textColor } },
            yRevenue: { position: 'left',  grid: { color: gridColor }, ticks: { color: textColor }, beginAtZero: true },
            yOrders:  { position: 'right', grid: { drawOnChartArea: false }, ticks: { color: textColor }, beginAtZero: true },
        }
    }
});
</script>
<?php endif; ?>

<?php
$_sLabels = array_map(fn($s) => $statusLabels[$s['order_status']] ?? $s['order_status'], $status_rows);
$_sColors = array_map(fn($s) => $statusColors[$s['order_status']] ?? '#6B7280', $status_rows);
if (!empty($status_rows)): ?>
<script>
const statusData = <?= json_encode(array_column($status_rows,'n')) ?>;
const statusLabelsChart = <?= json_encode($_sLabels) ?>;
const statusColorsChart = <?= json_encode($_sColors) ?>;

new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: statusLabelsChart,
        datasets: [{ data: statusData, backgroundColor: statusColorsChart, borderWidth: 0, hoverOffset: 6 }]
    },
    options: {
        cutout: '72%',
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: ctx => ' ' + ctx.label + ': ' + ctx.raw } }
        }
    }
});
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
