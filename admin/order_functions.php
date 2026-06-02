<?php
/**
 * Fonctions utilitaires pour la gestion des commandes
 * Ces fonctions complètent celles présentes dans auth.php
 */

/**
 * Récupère le total des ventes pour une période donnée
 * 
 * @param mysqli $conn Connexion à la base de données
 * @param string $period Période ('day', 'week', 'month', 'year', 'all')
 * @return float Montant total des ventes
 */
function getSalesTotal($conn, $period = 'all') {
    $sql = "SELECT SUM(total_amount) as total FROM orders WHERE payment_status = 'paid'";
    
    switch ($period) {
        case 'day':
            $sql .= " AND DATE(created_at) = CURDATE()";
            break;
        case 'week':
            $sql .= " AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)";
            break;
        case 'month':
            $sql .= " AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())";
            break;
        case 'year':
            $sql .= " AND YEAR(created_at) = YEAR(CURDATE())";
            break;
    }
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['total'] ? $row['total'] : 0;
    }
    
    return 0;
}

/**
 * Récupère le nombre de commandes pour une période donnée
 * 
 * @param mysqli $conn Connexion à la base de données
 * @param string $period Période ('day', 'week', 'month', 'year', 'all')
 * @param string $status Statut des commandes (optionnel)
 * @return int Nombre de commandes
 */
function getOrdersCount($conn, $period = 'all', $status = null) {
    $sql = "SELECT COUNT(*) as count FROM orders WHERE 1=1";
    
    if ($status !== null) {
        $sql .= " AND order_status = '" . $conn->real_escape_string($status) . "'";
    }
    
    switch ($period) {
        case 'day':
            $sql .= " AND DATE(created_at) = CURDATE()";
            break;
        case 'week':
            $sql .= " AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)";
            break;
        case 'month':
            $sql .= " AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())";
            break;
        case 'year':
            $sql .= " AND YEAR(created_at) = YEAR(CURDATE())";
            break;
    }
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['count'];
    }
    
    return 0;
}

/**
 * Récupère les commandes récentes
 * 
 * @param mysqli $conn Connexion à la base de données
 * @param int $limit Nombre de commandes à récupérer
 * @return array Tableau des commandes récentes
 */
function getRecentOrders($conn, $limit = 5) {
    $sql = "SELECT o.*, u.username, u.email 
            FROM orders o 
            LEFT JOIN users u ON o.user_id = u.id 
            ORDER BY o.created_at DESC 
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $orders = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    }
    
    return $orders;
}

/**
 * Récupère les produits les plus vendus
 * 
 * @param mysqli $conn Connexion à la base de données
 * @param int $limit Nombre de produits à récupérer
 * @return array Tableau des produits les plus vendus
 */
function getBestSellingProducts($conn, $limit = 5) {
    $sql = "SELECT p.id, p.name, p.price, p.stock, p.status, 
                  c.name as category_name, 
                  COUNT(oi.id) as order_count, 
                  SUM(oi.quantity) as total_quantity
            FROM products p
            JOIN order_items oi ON p.id = oi.product_id
            JOIN orders o ON oi.order_id = o.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE o.payment_status = 'paid'
            GROUP BY p.id
            ORDER BY total_quantity DESC
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Récupérer l'image principale du produit
            $img_query = "SELECT image_url FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, display_order ASC LIMIT 1";
            $img_stmt = $conn->prepare($img_query);
            $img_stmt->bind_param("i", $row['id']);
            $img_stmt->execute();
            $img_result = $img_stmt->get_result();
            
            $row['image'] = '';
            if ($img_result && $img_result->num_rows > 0) {
                $img_row = $img_result->fetch_assoc();
                $row['image'] = $img_row['image_url'];
            }
            
            $products[] = $row;
        }
    }
    
    return $products;
}

/**
 * Récupère les statistiques de vente pour un graphique
 * 
 * @param mysqli $conn Connexion à la base de données
 * @param string $interval Intervalle ('daily', 'weekly', 'monthly')
 * @param int $limit Nombre de périodes à récupérer
 * @return array Tableau des statistiques de vente
 */
function getSalesStats($conn, $interval = 'monthly', $limit = 6) {
    $sql = "";
    
    switch ($interval) {
        case 'daily':
            $sql = "SELECT 
                      DATE(created_at) as period,
                      COUNT(*) as orders_count,
                      SUM(total_amount) as total_sales
                    FROM 
                      orders
                    WHERE 
                      payment_status = 'paid'
                      AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                    GROUP BY 
                      DATE(created_at)
                    ORDER BY 
                      period DESC
                    LIMIT ?";
            break;
            
        case 'weekly':
            $sql = "SELECT 
                      YEARWEEK(created_at, 1) as period_num,
                      MIN(DATE(created_at)) as period_start,
                      COUNT(*) as orders_count,
                      SUM(total_amount) as total_sales
                    FROM 
                      orders
                    WHERE 
                      payment_status = 'paid'
                      AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? WEEK)
                    GROUP BY 
                      YEARWEEK(created_at, 1)
                    ORDER BY 
                      period_num DESC
                    LIMIT ?";
            break;
            
        case 'monthly':
        default:
            $sql = "SELECT 
                      DATE_FORMAT(created_at, '%Y-%m') as period,
                      DATE_FORMAT(created_at, '%b %Y') as period_label,
                      COUNT(*) as orders_count,
                      SUM(total_amount) as total_sales
                    FROM 
                      orders
                    WHERE 
                      payment_status = 'paid'
                      AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                    GROUP BY 
                      DATE_FORMAT(created_at, '%Y-%m'), 
                      DATE_FORMAT(created_at, '%b %Y')
                    ORDER BY 
                      period DESC
                    LIMIT ?";
            break;
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $limit, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $stats = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $stats[] = $row;
        }
    }
    
    // Inverser le tableau pour avoir les données dans l'ordre chronologique
    return array_reverse($stats);
}

/**
 * Crée un numéro de commande unique
 * 
 * @param mysqli $conn Connexion à la base de données
 * @return string Numéro de commande au format NET-YYYYMMDD-XXXX
 */
function generateOrderNumber($conn) {
    $prefix = 'NET';
    $date = date('Ymd');
    
    // Récupérer le dernier numéro de commande pour aujourd'hui
    $sql = "SELECT order_number FROM orders 
            WHERE order_number LIKE ? 
            ORDER BY id DESC LIMIT 1";
    
    $like_pattern = $prefix . '-' . $date . '-%';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $like_pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $last_number = $row['order_number'];
        
        // Extraire le suffixe numérique
        $suffix = intval(substr($last_number, -4));
        $suffix++;
    } else {
        $suffix = 1;
    }
    
    // Formater le suffixe avec des zéros de remplissage
    $formatted_suffix = str_pad($suffix, 4, '0', STR_PAD_LEFT);
    
    return $prefix . '-' . $date . '-' . $formatted_suffix;
}

/**
 * Génère une chaîne de caractères JSON pour le graphique de l'historique des commandes
 * 
 * @param mysqli $conn Connexion à la base de données
 * @param int $order_id ID de la commande
 * @return string Chaîne de caractères JSON pour le graphique
 */
function getOrderTrackingTimelineJson($conn, $order_id) {
    $sql = "SELECT status, created_at FROM order_tracking WHERE order_id = ? ORDER BY created_at ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $tracking_data = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $status_text = getTrackingStatusText($row['status']);
            $date = date('d/m/Y H:i', strtotime($row['created_at']));
            
            $tracking_data[] = [
                'status' => $row['status'],
                'statusText' => $status_text,
                'date' => $date,
                'timestamp' => strtotime($row['created_at'])
            ];
        }
    }
    
    return json_encode($tracking_data);
}

/**
 * Génère une estimation du délai de livraison basée sur le statut actuel
 * 
 * @param string $status Statut de la commande
 * @param string $created_at Date de création de la commande
 * @return array Estimation du délai de livraison
 */
function getDeliveryEstimate($status, $created_at) {
    $estimate = [
        'min_days' => 0,
        'max_days' => 0,
        'is_delivered' => false,
        'is_cancelled' => false,
        'message' => ''
    ];
    
    $order_date = new DateTime($created_at);
    $current_date = new DateTime();
    $days_since_order = $current_date->diff($order_date)->days;
    
    switch ($status) {
        case 'pending':
            $estimate['min_days'] = 5;
            $estimate['max_days'] = 10;
            $estimate['message'] = 'Livraison estimée dans 5 à 10 jours';
            break;
            
        case 'processing':
            $estimate['min_days'] = max(0, 4 - $days_since_order);
            $estimate['max_days'] = max(0, 8 - $days_since_order);
            $estimate['message'] = 'Livraison estimée dans ' . $estimate['min_days'] . ' à ' . $estimate['max_days'] . ' jours';
            break;
            
        case 'shipped':
            $estimate['min_days'] = max(0, 2 - $days_since_order);
            $estimate['max_days'] = max(0, 5 - $days_since_order);
            $estimate['message'] = 'Livraison estimée dans ' . $estimate['min_days'] . ' à ' . $estimate['max_days'] . ' jours';
            break;
            
        case 'delivered':
            $estimate['is_delivered'] = true;
            $estimate['message'] = 'Commande livrée';
            break;
            
        case 'cancelled':
        case 'returned':
            $estimate['is_cancelled'] = true;
            $estimate['message'] = 'Commande ' . ($status === 'cancelled' ? 'annulée' : 'retournée');
            break;
            
        default:
            $estimate['message'] = 'Estimation de livraison indisponible';
    }
    
    return $estimate;
}

/**
 * Calcule le montant total des remboursements dans une période donnée
 * 
 * @param mysqli $conn Connexion à la base de données
 * @param string $period Période ('day', 'week', 'month', 'year', 'all')
 * @return float Montant total des remboursements
 */
function getRefundsTotal($conn, $period = 'all') {
    $sql = "SELECT SUM(total_amount) as total FROM orders WHERE payment_status = 'refunded'";
    
    switch ($period) {
        case 'day':
            $sql .= " AND DATE(updated_at) = CURDATE()";
            break;
        case 'week':
            $sql .= " AND YEARWEEK(updated_at, 1) = YEARWEEK(CURDATE(), 1)";
            break;
        case 'month':
            $sql .= " AND YEAR(updated_at) = YEAR(CURDATE()) AND MONTH(updated_at) = MONTH(CURDATE())";
            break;
        case 'year':
            $sql .= " AND YEAR(updated_at) = YEAR(CURDATE())";
            break;
    }
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['total'] ? $row['total'] : 0;
    }
    
    return 0;
}

/**
 * Calcule le panier moyen sur une période donnée
 * 
 * @param mysqli $conn Connexion à la base de données
 * @param string $period Période ('day', 'week', 'month', 'year', 'all')
 * @return float Montant moyen du panier
 */
function getAverageOrderValue($conn, $period = 'all') {
    $sql = "SELECT AVG(total_amount) as average FROM orders WHERE payment_status = 'paid'";
    
    switch ($period) {
        case 'day':
            $sql .= " AND DATE(created_at) = CURDATE()";
            break;
        case 'week':
            $sql .= " AND YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)";
            break;
        case 'month':
            $sql .= " AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())";
            break;
        case 'year':
            $sql .= " AND YEAR(created_at) = YEAR(CURDATE())";
            break;
    }
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['average'] ? $row['average'] : 0;
    }
    
    return 0;
}

/**
 * Récupère le texte du statut de suivi
 * 
 * @param string $status Code du statut
 * @return string Texte du statut
 */
function getTrackingStatusText($status) {
    switch ($status) {
        case 'processing':
            return 'Commande en traitement';
        case 'shipped':
            return 'Commande expédiée';
        case 'in_transit':
            return 'En transit';
        case 'delivered':
            return 'Commande livrée';
        case 'returned':
            return 'Commande retournée';
        case 'cancelled':
            return 'Commande annulée';
        default:
            return 'Mise à jour de statut';
    }
}

/**
 * Génère un numéro de facture
 * 
 * @param int $order_id ID de la commande
 * @param string $created_at Date de création de la commande
 * @return string Numéro de facture au format FCT-YYYYMM-XXX
 */
function generateInvoiceNumber($order_id, $created_at) {
    $date = date('Ym', strtotime($created_at));
    return "FCT-" . $date . "-" . $order_id;
}

/**
 * Envoie un email de confirmation de commande
 * 
 * @param array $order Détails de la commande
 * @param array $items Articles de la commande
 * @return bool Succès de l'envoi
 */
function sendOrderConfirmationEmail($order, $items) {
    // Cette fonction nécessite une bibliothèque d'envoi d'emails comme PHPMailer
    // Ceci est un exemple simplifié
    
    $to = $order['email'];
    $subject = "Confirmation de votre commande #" . $order['order_number'];
    
    $message = "Bonjour " . ($order['full_name'] ?? $order['username'] ?? "Client") . ",\n\n";
    $message .= "Merci pour votre commande sur Netcrafter. Votre commande #" . $order['order_number'] . " a été reçue et est en cours de traitement.\n\n";
    
    $message .= "Détails de votre commande :\n";
    $message .= "--------------------------------\n";
    
    $subtotal = 0;
    foreach ($items as $item) {
        $message .= $item['product_name'] . " x " . $item['quantity'] . " : " . number_format($item['price'] * $item['quantity'], 2) . " FCFA\n";
        $subtotal += $item['price'] * $item['quantity'];
    }
    
    $message .= "--------------------------------\n";
    $message .= "Sous-total : " . number_format($subtotal, 2) . " FCFA\n";
    
    if (!empty($order['discount_amount']) && $order['discount_amount'] > 0) {
        $message .= "Remise : -" . number_format($order['discount_amount'], 2) . " FCFA\n";
    }
    
    $message .= "Livraison : " . number_format($order['shipping_cost'], 2) . " FCFA\n";
    
    if (!empty($order['tax_amount']) && $order['tax_amount'] > 0) {
        $message .= "TVA : " . number_format($order['tax_amount'], 2) . " FCFA\n";
    }
    
    $message .= "Total : " . number_format($order['total_amount'], 2) . " FCFA\n\n";
    
    $message .= "Nous vous tiendrons informé de l'avancement de votre commande.\n\n";
    $message .= "Cordialement,\n";
    $message .= "L'équipe Netcrafter";
    
    $headers = "From: noreply@netcrafter.com\r\n";
    $headers .= "Reply-To: support@netcrafterniger.com\r\n";
    
    // En production, utilisez une bibliothèque d'envoi d'emails robuste
    // Retourne toujours true pour cet exemple
    // return mail($to, $subject, $message, $headers);
    return true;
}

/**
 * Envoie un email de notification de changement de statut
 * 
 * @param array $order Détails de la commande
 * @param string $old_status Ancien statut
 * @param string $new_status Nouveau statut
 * @return bool Succès de l'envoi
 */
function sendOrderStatusUpdateEmail($order, $old_status, $new_status) {
    // Cette fonction nécessite une bibliothèque d'envoi d'emails comme PHPMailer
    // Ceci est un exemple simplifié
    
    $to = $order['email'];
    $subject = "Mise à jour de votre commande #" . $order['order_number'];
    
    $message = "Bonjour " . ($order['full_name'] ?? $order['username'] ?? "Client") . ",\n\n";
    $message .= "Le statut de votre commande #" . $order['order_number'] . " a été mis à jour.\n\n";
    $message .= "Nouveau statut : " . getOrderStatusText($new_status) . "\n\n";
    
    // Ajouter des informations spécifiques selon le statut
    switch ($new_status) {
        case 'processing':
            $message .= "Votre commande est en cours de préparation dans nos entrepôts.\n";
            break;
        case 'shipped':
            $message .= "Votre commande a été expédiée et est en route vers vous.\n";
            if (!empty($order['tracking_number'])) {
                $message .= "Numéro de suivi : " . $order['tracking_number'] . "\n";
                $message .= "Transporteur : " . $order['carrier'] . "\n";
            }
            break;
        case 'delivered':
            $message .= "Votre commande a été livrée à l'adresse indiquée.\n";
            break;
        case 'cancelled':
            $message .= "Votre commande a été annulée. Si vous avez des questions, n'hésitez pas à contacter notre service client.\n";
            break;
        case 'returned':
            $message .= "Votre retour a été traité. Si vous avez des questions, n'hésitez pas à contacter notre service client.\n";
            break;
    }
    
    $message .= "\nVous pouvez suivre l'évolution de votre commande à tout moment sur votre compte client.\n\n";
    $message .= "Cordialement,\n";
    $message .= "L'équipe Netcrafter";
    
    $headers = "From: noreply@netcrafter.com\r\n";
    $headers .= "Reply-To: support@netcrafterniger.com\r\n";
    
    // En production, utilisez une bibliothèque d'envoi d'emails robuste
    // Retourne toujours true pour cet exemple
    // return mail($to, $subject, $message, $headers);
    return true;
}

/**
 * Récupère le texte du statut de commande
 * 
 * @param string $status Code du statut
 * @return string Texte du statut
 */
function getOrderStatusText($status) {
    switch ($status) {
        case 'pending':
            return 'En attente';
        case 'processing':
            return 'En traitement';
        case 'shipped':
            return 'Expédiée';
        case 'delivered':
            return 'Livrée';
        case 'cancelled':
            return 'Annulée';
        case 'returned':
            return 'Retournée';
        default:
            return ucfirst($status);
    }
}

/**
 * Récupère le texte du statut de paiement
 * 
 * @param string $status Code du statut
 * @return string Texte du statut
 */
function getPaymentStatusText($status) {
    switch ($status) {
        case 'pending':
            return 'En attente';
        case 'paid':
            return 'Payé';
        case 'failed':
            return 'Échoué';
        case 'refunded':
            return 'Remboursé';
        default:
            return ucfirst($status);
    }
}
?>