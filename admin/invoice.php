<?php
// Initialiser la session et vérifier l'authentification
require_once 'includes/auth.php';

// Récupérer l'ID de la commande à partir de l'URL
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Vérifier si l'ID est valide
if ($order_id <= 0) {
    header("Location: orders.php?error=" . urlencode("ID de commande invalide."));
    exit;
}

// Récupérer les détails de la commande
$order_query = "SELECT o.*, 
                u.username, u.email, u.full_name, u.phone,
                sa.street_address as shipping_street, sa.city as shipping_city, sa.state as shipping_state, sa.postal_code as shipping_postal_code, sa.country as shipping_country,
                ba.street_address as billing_street, ba.city as billing_city, ba.state as billing_state, ba.postal_code as billing_postal_code, ba.country as billing_country
                FROM orders o
                LEFT JOIN users u ON o.user_id = u.id
                LEFT JOIN addresses sa ON o.shipping_address_id = sa.id
                LEFT JOIN addresses ba ON o.billing_address_id = ba.id
                WHERE o.id = ?";

$stmt = $conn->prepare($order_query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_result = $stmt->get_result();

if ($order_result->num_rows === 0) {
    header("Location: orders.php?error=" . urlencode("Commande introuvable."));
    exit;
}

$order = $order_result->fetch_assoc();

// Récupérer les articles de la commande
$items_query = "SELECT oi.*, p.name as product_name, p.sku
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ?";

$stmt = $conn->prepare($items_query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items_result = $stmt->get_result();
$order_items = [];

while ($item = $items_result->fetch_assoc()) {
    $order_items[] = $item;
}

// Récupérer les informations sur la boutique
$store_name = "Netcrafter";
$store_address = "Niamey, Niger";
$store_phone = "+227 88 67 21 15";
$store_email = "contact@netcrafterniger.com";
$store_website = "www.netcrafterniger.com";
$store_siret = "123 456 789 00012";
$store_vat = "FR 12 345 678 901";

// Calculer le résumé financier
$subtotal = 0;
foreach ($order_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

$discount = $order['discount_amount'] ?? 0;
$shipping = $order['shipping_cost'] ?? 0;
$tax = $order['tax_amount'] ?? 0;
$total = $order['total_amount'];

// Format de la date de la facture
$invoice_date = date('d/m/Y', strtotime($order['created_at']));
$invoice_number = "FCT-" . date('Ym', strtotime($order['created_at'])) . "-" . $order['id'];

// Entête HTML
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture <?php echo $invoice_number; ?> - Netcrafter</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            body {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
            
            .no-print {
                display: none !important;
            }
            
            .print-content {
                padding: 0;
                margin: 0;
            }
            
            @page {
                size: A4;
                margin: 10mm;
            }
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <!-- Print Controls (not displayed when printing) -->
    <div class="no-print bg-white shadow-md fixed top-0 left-0 right-0 p-4 flex justify-between items-center z-10">
        <div class="flex items-center gap-4">
            <a href="order_details.php?id=<?php echo $order_id; ?>" class="text-gray-700 hover:text-gray-900">
                <i class="fas fa-arrow-left mr-2"></i>Retour aux détails
            </a>
            <h1 class="text-lg font-bold text-gray-800">Facture <?php echo $invoice_number; ?></h1>
        </div>
        <div class="flex gap-2">
            <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md transition-colors">
                <i class="fas fa-print mr-2"></i>Imprimer
            </button>
            <button onclick="downloadPDF()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md transition-colors">
                <i class="fas fa-file-pdf mr-2"></i>Télécharger PDF
            </button>
        </div>
    </div>
    
    <!-- Padding to ensure content is below the fixed header (not applied when printing) -->
    <div class="no-print h-24"></div>
    
    <!-- Invoice Content -->
    <div class="print-content max-w-4xl mx-auto bg-white shadow-md my-8 p-8">
        <!-- Invoice Header -->
        <div class="flex justify-between items-start mb-12">
            <!-- Company Info -->
            <div>
                <div class="flex items-center gap-3 mb-4">
                    <img src="../image/logo-n.png" alt="Netcrafter Logo" class="h-12">
                    <h1 class="text-2xl font-bold text-gray-800"><?php echo $store_name; ?></h1>
                </div>
                <address class="not-italic text-gray-600">
                    <?php echo $store_address; ?><br>
                    Tél: <?php echo $store_phone; ?><br>
                    Email: <?php echo $store_email; ?><br>
                    Web: <?php echo $store_website; ?><br>
                    SIRET: <?php echo $store_siret; ?><br>
                    N° TVA: <?php echo $store_vat; ?>
                </address>
            </div>
            
            <!-- Invoice Details -->
            <div class="text-right">
                <div class="text-3xl font-bold text-gray-800 mb-2">FACTURE</div>
                <div class="text-gray-600 mb-4">
                    <div><span class="font-medium">N° Facture:</span> <?php echo $invoice_number; ?></div>
                    <div><span class="font-medium">Date:</span> <?php echo $invoice_date; ?></div>
                    <div><span class="font-medium">N° Commande:</span> <?php echo htmlspecialchars($order['order_number']); ?></div>
                    <div><span class="font-medium">Méthode de paiement:</span> 
                        <?php 
                        switch(strtolower($order['payment_method'] ?? '')) {
                            case 'credit_card':
                            case 'card':
                                echo 'Carte bancaire';
                                break;
                            case 'paypal':
                                echo 'PayPal';
                                break;
                            case 'bank_transfer':
                                echo 'Virement bancaire';
                                break;
                            default:
                                echo $order['payment_method'] ?? 'Non spécifié';
                        }
                        ?>
                    </div>
                </div>
                
                <!-- Status Badge -->
                <div class="inline-block px-3 py-1 text-sm font-semibold rounded-full <?php 
                    if ($order['payment_status'] === 'paid') {
                        echo 'bg-green-100 text-green-800';
                    } elseif ($order['payment_status'] === 'pending') {
                        echo 'bg-yellow-100 text-yellow-800';
                    } elseif ($order['payment_status'] === 'refunded') {
                        echo 'bg-purple-100 text-purple-800';
                    } else {
                        echo 'bg-red-100 text-red-800';
                    }
                ?>">
                    <?php 
                    switch($order['payment_status']) {
                        case 'paid':
                            echo 'PAYÉE';
                            break;
                        case 'pending':
                            echo 'EN ATTENTE';
                            break;
                        case 'refunded':
                            echo 'REMBOURSÉE';
                            break;
                        case 'failed':
                            echo 'ÉCHOUÉE';
                            break;
                        default:
                            echo strtoupper($order['payment_status']);
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <!-- Billing and Shipping -->
        <div class="grid grid-cols-2 gap-8 mb-12">
            <!-- Billing Address -->
            <div>
                <h2 class="text-lg font-bold text-gray-800 mb-3 pb-2 border-b border-gray-200">Facturation</h2>
                <div class="text-gray-600">
                    <?php if (!empty($order['billing_street']) || !empty($order['shipping_street'])): ?>
                        <p class="font-medium"><?php echo !empty($order['full_name']) ? htmlspecialchars($order['full_name']) : htmlspecialchars($order['username'] ?? 'Client'); ?></p>
                        <?php
                        // Si adresse de facturation définie, utiliser celle-ci
                        if (!empty($order['billing_street'])) {
                            echo '<p>' . htmlspecialchars($order['billing_street']) . '</p>';
                            echo '<p>' . htmlspecialchars($order['billing_postal_code']) . ' ' . htmlspecialchars($order['billing_city']);
                            if (!empty($order['billing_state'])) {
                                echo ', ' . htmlspecialchars($order['billing_state']);
                            }
                            echo '</p>';
                            echo '<p>' . htmlspecialchars($order['billing_country']) . '</p>';
                        } 
                        // Sinon utiliser l'adresse de livraison
                        else {
                            echo '<p>' . htmlspecialchars($order['shipping_street']) . '</p>';
                            echo '<p>' . htmlspecialchars($order['shipping_postal_code']) . ' ' . htmlspecialchars($order['shipping_city']);
                            if (!empty($order['shipping_state'])) {
                                echo ', ' . htmlspecialchars($order['shipping_state']);
                            }
                            echo '</p>';
                            echo '<p>' . htmlspecialchars($order['shipping_country']) . '</p>';
                        }
                        ?>
                    <?php else: ?>
                        <p>Aucune adresse disponible</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Shipping Address -->
            <div>
                <h2 class="text-lg font-bold text-gray-800 mb-3 pb-2 border-b border-gray-200">Livraison</h2>
                <div class="text-gray-600">
                    <?php if (!empty($order['shipping_street'])): ?>
                        <p class="font-medium"><?php echo !empty($order['full_name']) ? htmlspecialchars($order['full_name']) : htmlspecialchars($order['username'] ?? 'Client'); ?></p>
                        <p><?php echo htmlspecialchars($order['shipping_street']); ?></p>
                        <p>
                            <?php echo htmlspecialchars($order['shipping_postal_code']); ?> 
                            <?php echo htmlspecialchars($order['shipping_city']); ?>
                            <?php if (!empty($order['shipping_state'])): ?>
                                , <?php echo htmlspecialchars($order['shipping_state']); ?>
                            <?php endif; ?>
                        </p>
                        <p><?php echo htmlspecialchars($order['shipping_country']); ?></p>
                    <?php else: ?>
                        <p>Aucune adresse de livraison disponible</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Order Items -->
        <h2 class="text-lg font-bold text-gray-800 mb-4 pb-2 border-b border-gray-200">Détail des articles</h2>
        <div class="overflow-x-auto mb-8">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="py-3 px-4 text-left font-medium text-gray-700">Description</th>
                        <th class="py-3 px-4 text-right font-medium text-gray-700">Prix unitaire</th>
                        <th class="py-3 px-4 text-right font-medium text-gray-700">Quantité</th>
                        <th class="py-3 px-4 text-right font-medium text-gray-700">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($order_items as $item): ?>
                    <tr>
                        <td class="py-3 px-4 text-gray-700">
                            <div class="font-medium"><?php echo htmlspecialchars($item['product_name']); ?></div>
                            <?php if (!empty($item['sku'])): ?>
                            <div class="text-gray-500 text-xs">Réf: <?php echo htmlspecialchars($item['sku']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="py-3 px-4 text-right text-gray-700"><?php echo number_format($item['price'], 2); ?> FCFA</td>
                        <td class="py-3 px-4 text-right text-gray-700"><?php echo $item['quantity']; ?></td>
                        <td class="py-3 px-4 text-right font-medium text-gray-700"><?php echo number_format($item['price'] * $item['quantity'], 2); ?> FCFA</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Totals -->
        <div class="flex justify-end mb-12">
            <div class="w-full max-w-xs">
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-200">
                        <tr>
                            <td class="py-2 text-gray-600">Sous-total</td>
                            <td class="py-2 text-right text-gray-800 font-medium"><?php echo number_format($subtotal, 2); ?> FCFA</td>
                        </tr>
                        <?php if ($discount > 0): ?>
                        <tr>
                            <td class="py-2 text-gray-600">Remise</td>
                            <td class="py-2 text-right text-green-600 font-medium">-<?php echo number_format($discount, 2); ?> FCFA</td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td class="py-2 text-gray-600">Frais de livraison</td>
                            <td class="py-2 text-right text-gray-800 font-medium"><?php echo number_format($shipping, 2); ?> FCFA</td>
                        </tr>
                        <?php if ($tax > 0): ?>
                        <tr>
                            <td class="py-2 text-gray-600">TVA (20%)</td>
                            <td class="py-2 text-right text-gray-800 font-medium"><?php echo number_format($tax, 2); ?> FCFA</td>
                        </tr>
                        <?php endif; ?>
                        <tr class="border-t-2 border-gray-300">
                            <td class="py-3 text-gray-800 font-bold">Total</td>
                            <td class="py-3 text-right text-gray-800 font-bold"><?php echo number_format($total, 2); ?> FCFA</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Notes and Terms -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 border-t border-gray-200 pt-8">
            <!-- Notes -->
            <?php if (!empty($order['notes'])): ?>
            <div>
                <h3 class="text-base font-bold text-gray-800 mb-2">Notes</h3>
                <p class="text-sm text-gray-600">
                    <?php echo nl2br(htmlspecialchars($order['notes'])); ?>
                </p>
            </div>
            <?php endif; ?>
            
            <!-- Terms and Conditions -->
            <div>
                <h3 class="text-base font-bold text-gray-800 mb-2">Conditions générales</h3>
                <p class="text-sm text-gray-600">
                    Paiement exigible dans les 30 jours. Nos conditions générales de vente s'appliquent à cette facture.
                    Pour toute question relative à cette facture, veuillez contacter notre service client.
                </p>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="text-center text-gray-500 text-xs mt-12 pt-6 border-t border-gray-200">
            <p>Merci pour votre confiance !</p>
            <p><?php echo $store_name; ?> - <?php echo $store_address; ?> - <?php echo $store_phone; ?></p>
        </div>
    </div>
    
    <!-- Script for PDF download -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        function downloadPDF() {
            // Get the print-content div
            const element = document.querySelector('.print-content');
            
            // Options for PDF generation
            const options = {
                margin: 10,
                filename: 'facture-<?php echo $invoice_number; ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            
            // Generate PDF
            html2pdf().set(options).from(element).save();
        }
    </script>
</body>
</html>