<?php
// Initialiser la session et vérifier l'authentification
require_once 'auth.php';

// Vérifier si TCPDF est installé
if (!file_exists('../vendor/tecnickcom/tcpdf/tcpdf.php')) {
    die("La bibliothèque TCPDF n'est pas installée. Veuillez l'installer via Composer.");
}

// Inclure TCPDF
require_once '../vendor/tecnickcom/tcpdf/tcpdf.php';

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
$store_address = "1 rue de la Technologie, 75001 Paris";
$store_phone = "+33 1 23 45 67 89";
$store_email = "contact@netcrafterniger.com";
$store_website = "www.netcrafter.com";
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

// Créer une nouvelle instance de TCPDF
class MYPDF extends TCPDF {
    // En-tête de page
    public function Header() {
        $this->SetFont('helvetica', 'B', 15);
        $this->Cell(0, 10, 'FACTURE', 0, false, 'R', 0, '', 0, false, 'M', 'M');
    }

    // Pied de page
    public function Footer() {
        global $store_name, $store_address, $store_phone;
        
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $footer_text = $store_name . ' - ' . $store_address . ' - ' . $store_phone;
        $this->Cell(0, 10, $footer_text, 0, false, 'C', 0, '', 0, false, 'T', 'M');
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'R', 0, '', 0, false, 'T', 'M');
    }
}

// Initialisation du PDF
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Définir les informations du document
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor($store_name);
$pdf->SetTitle('Facture ' . $invoice_number);
$pdf->SetSubject('Facture pour commande #' . $order['order_number']);
$pdf->SetKeywords('Facture, Commande, ' . $order['order_number']);

// Définir les marges
$pdf->SetMargins(15, 15, 15);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(10);

// Définir les sauts de page automatiques
$pdf->SetAutoPageBreak(TRUE, 25);

// Ajouter une page
$pdf->AddPage();

// Définir la police
$pdf->SetFont('helvetica', '', 10);

// Logo et informations de l'entreprise
$pdf->Image('../image/logo-n.png', 15, 15, 30, 0, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
$pdf->SetFont('helvetica', 'B', 14);
$pdf->SetXY(50, 15);
$pdf->Cell(0, 7, $store_name, 0, 1, 'L');
$pdf->SetFont('helvetica', '', 9);
$pdf->SetXY(50, 23);
$pdf->MultiCell(70, 5, $store_address . "\nTél: " . $store_phone . "\nEmail: " . $store_email . "\nWeb: " . $store_website . "\nSIRET: " . $store_siret . "\nN° TVA: " . $store_vat, 0, 'L');

// Informations de la facture
$pdf->SetFont('helvetica', 'B', 10);
$pdf->SetXY(140, 25);
$pdf->Cell(0, 5, 'N° Facture: ' . $invoice_number, 0, 1, 'R');
$pdf->SetXY(140, 31);
$pdf->Cell(0, 5, 'Date: ' . $invoice_date, 0, 1, 'R');
$pdf->SetXY(140, 37);
$pdf->Cell(0, 5, 'N° Commande: ' . $order['order_number'], 0, 1, 'R');

// Statut de paiement
$pdf->SetXY(140, 43);
switch($order['payment_status']) {
    case 'paid':
        $pdf->SetFillColor(200, 230, 201); // Vert clair
        $pdf->SetTextColor(27, 94, 32);    // Vert foncé
        $payment_status_text = 'PAYÉE';
        break;
    case 'pending':
        $pdf->SetFillColor(255, 236, 179); // Jaune clair
        $pdf->SetTextColor(179, 134, 0);   // Jaune foncé
        $payment_status_text = 'EN ATTENTE';
        break;
    case 'refunded':
        $pdf->SetFillColor(209, 196, 233); // Violet clair
        $pdf->SetTextColor(81, 45, 168);   // Violet foncé
        $payment_status_text = 'REMBOURSÉE';
        break;
    case 'failed':
        $pdf->SetFillColor(255, 205, 210); // Rouge clair
        $pdf->SetTextColor(183, 28, 28);   // Rouge foncé
        $payment_status_text = 'ÉCHOUÉE';
        break;
    default:
        $pdf->SetFillColor(224, 224, 224); // Gris clair
        $pdf->SetTextColor(66, 66, 66);    // Gris foncé
        $payment_status_text = strtoupper($order['payment_status']);
}
$pdf->Cell(50, 6, $payment_status_text, 0, 1, 'R', 1);
$pdf->SetTextColor(0, 0, 0); // Réinitialiser la couleur du texte

// Adresses de facturation et de livraison
$pdf->Ln(10);
$pdf->SetFillColor(240, 240, 240);

// Adresse de facturation
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(90, 7, 'FACTURATION', 0, 0, 'L', 1);
$pdf->Cell(5, 7, '', 0, 0, 'L');
$pdf->Cell(90, 7, 'LIVRAISON', 0, 1, 'L', 1);
$pdf->SetFont('helvetica', '', 9);

// Contenu de l'adresse de facturation
$billing_address = '';
if (!empty($order['full_name'])) {
    $billing_address .= $order['full_name'] . "\n";
} else if (!empty($order['username'])) {
    $billing_address .= $order['username'] . "\n";
}

if (!empty($order['billing_street'])) {
    $billing_address .= $order['billing_street'] . "\n";
    $billing_address .= $order['billing_postal_code'] . ' ' . $order['billing_city'];
    if (!empty($order['billing_state'])) {
        $billing_address .= ', ' . $order['billing_state'];
    }
    $billing_address .= "\n" . $order['billing_country'];
} else if (!empty($order['shipping_street'])) {
    $billing_address .= $order['shipping_street'] . "\n";
    $billing_address .= $order['shipping_postal_code'] . ' ' . $order['shipping_city'];
    if (!empty($order['shipping_state'])) {
        $billing_address .= ', ' . $order['shipping_state'];
    }
    $billing_address .= "\n" . $order['shipping_country'];
} else {
    $billing_address .= "Aucune adresse disponible";
}

// Contenu de l'adresse de livraison
$shipping_address = '';
if (!empty($order['full_name'])) {
    $shipping_address .= $order['full_name'] . "\n";
} else if (!empty($order['username'])) {
    $shipping_address .= $order['username'] . "\n";
}

if (!empty($order['shipping_street'])) {
    $shipping_address .= $order['shipping_street'] . "\n";
    $shipping_address .= $order['shipping_postal_code'] . ' ' . $order['shipping_city'];
    if (!empty($order['shipping_state'])) {
        $shipping_address .= ', ' . $order['shipping_state'];
    }
    $shipping_address .= "\n" . $order['shipping_country'];
} else {
    $shipping_address .= "Aucune adresse de livraison disponible";
}

$pdf->MultiCell(90, 5, $billing_address, 0, 'L');
$pdf->SetXY(110, $pdf->GetY() - 5 * (substr_count($billing_address, "\n") + 1));
$pdf->MultiCell(90, 5, $shipping_address, 0, 'L');

// Tableau des articles
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 7, 'DÉTAIL DES ARTICLES', 0, 1, 'L', 1);
$pdf->Ln(2);

// En-têtes du tableau
$pdf->SetFillColor(245, 245, 245);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(90, 7, 'Description', 1, 0, 'L', 1);
$pdf->Cell(30, 7, 'Prix unitaire', 1, 0, 'R', 1);
$pdf->Cell(25, 7, 'Quantité', 1, 0, 'R', 1);
$pdf->Cell(35, 7, 'Total', 1, 1, 'R', 1);

// Lignes du tableau
$pdf->SetFont('helvetica', '', 9);
foreach ($order_items as $item) {
    $description = $item['product_name'];
    if (!empty($item['sku'])) {
        $description .= "\nRéf: " . $item['sku'];
    }
    $pdf->MultiCell(90, 10, $description, 1, 'L', 0, 0);
    $pdf->Cell(30, 10, number_format($item['price'], 2) . ' FCFA', 1, 0, 'R');
    $pdf->Cell(25, 10, $item['quantity'], 1, 0, 'R');
    $pdf->Cell(35, 10, number_format($item['price'] * $item['quantity'], 2) . ' FCFA', 1, 1, 'R');
}

// Totaux
$pdf->Ln(5);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(145, 6, 'Sous-total', 0, 0, 'R');
$pdf->Cell(35, 6, number_format($subtotal, 2) . ' FCFA', 0, 1, 'R');

if ($discount > 0) {
    $pdf->Cell(145, 6, 'Remise', 0, 0, 'R');
    $pdf->Cell(35, 6, '-' . number_format($discount, 2) . ' FCFA', 0, 1, 'R');
}

$pdf->Cell(145, 6, 'Frais de livraison', 0, 0, 'R');
$pdf->Cell(35, 6, number_format($shipping, 2) . ' FCFA', 0, 1, 'R');

if ($tax > 0) {
    $pdf->Cell(145, 6, 'TVA (20%)', 0, 0, 'R');
    $pdf->Cell(35, 6, number_format($tax, 2) . ' FCFA', 0, 1, 'R');
}

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(145, 8, 'TOTAL', 'T', 0, 'R');
$pdf->Cell(35, 8, number_format($total, 2) . ' FCFA', 'T', 1, 'R');

// Notes et Conditions
$pdf->Ln(5);

if (!empty($order['notes'])) {
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 7, 'NOTES', 0, 1, 'L', 1);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->MultiCell(0, 5, $order['notes'], 0, 'L');
    $pdf->Ln(5);
}

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 7, 'CONDITIONS GÉNÉRALES', 0, 1, 'L', 1);
$pdf->SetFont('helvetica', '', 9);
$pdf->MultiCell(0, 5, 'Paiement exigible dans les 30 jours. Nos conditions générales de vente s\'appliquent à cette facture. Pour toute question relative à cette facture, veuillez contacter notre service client.', 0, 'L');

// Message de remerciement
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'I', 10);
$pdf->Cell(0, 6, 'Merci pour votre confiance !', 0, 1, 'C');

// Fermer et sortir le PDF
$pdf_name = 'facture-' . $invoice_number . '.pdf';
$pdf->Output($pdf_name, 'I');
?>