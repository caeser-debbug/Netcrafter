<?php
// certificates/generate_pdf.php
// Générateur de certificats PDF avec TCPDF

// TCPDF est optionnel - le script fonctionne sans
if (file_exists('../vendor/tcpdf/tcpdf.php')) {
    require_once('../vendor/tcpdf/tcpdf.php');
} elseif (file_exists('../tcpdf/tcpdf.php')) {
    require_once('../tcpdf/tcpdf.php');
}

class CertificatePDF extends TCPDF {
    private $certificate_data;
    
    public function __construct($certificate_data) {
        parent::__construct('L', PDF_UNIT, 'A4', true, 'UTF-8', false);
        $this->certificate_data = $certificate_data;
        
        // Configuration du document
        $this->SetCreator('Netcrafter Formation');
        $this->SetAuthor('Netcrafter Formation');
        $this->SetTitle('Certificat de Formation - ' . $certificate_data['firstname'] . ' ' . $certificate_data['lastname']);
        $this->SetSubject('Certificat de Formation Professionnelle');
        $this->SetKeywords('certificat, formation, netcrafter, ' . $certificate_data['formation_title']);
        
        // Supprimer header et footer par défaut
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        
        // Marges
        $this->SetMargins(15, 15, 15);
        $this->SetAutoPageBreak(false, 0);
        
        // Mode d'affichage
        $this->setDisplayMode('fullpage');
    }
    
    public function generateCertificate() {
        $this->AddPage();
        
        // Arrière-plan et bordures
        $this->drawBackground();
        $this->drawBorders();
        
        // Contenu principal
        $this->drawHeader();
        $this->drawMainContent();
        $this->drawFormationDetails();
        $this->drawCertificateInfo();
        $this->drawVerificationSection();
        $this->drawSignatures();
        
        return $this->Output('certificate_' . $this->certificate_data['certificate_number'] . '.pdf', 'S');
    }
    
    private function drawBackground() {
        // Gradient de fond subtil
        $this->SetFillColor(248, 250, 252);
        $this->Rect(0, 0, 297, 210, 'F');
        
        // Motifs décoratifs
        $this->SetAlpha(0.05);
        $this->SetDrawColor(102, 126, 234);
        
        // Cercles décoratifs dans les coins
        $this->Circle(30, 30, 25, 0, 360, 'D');
        $this->Circle(267, 30, 25, 0, 360, 'D');
        $this->Circle(30, 180, 25, 0, 360, 'D');
        $this->Circle(267, 180, 25, 0, 360, 'D');
        
        $this->SetAlpha(1);
    }
    
    private function drawBorders() {
        // Bordure principale
        $this->SetLineWidth(2);
        $this->SetDrawColor(102, 126, 234);
        $this->Rect(10, 10, 277, 190, 'D');
        
        // Bordure intérieure
        $this->SetLineWidth(0.5);
        $this->SetDrawColor(102, 126, 234);
        $this->Rect(15, 15, 267, 180, 'D');
        
        // Coins décoratifs
        $this->drawCornerDecorations();
    }
    
    private function drawCornerDecorations() {
        $this->SetLineWidth(1);
        $this->SetDrawColor(102, 126, 234);
        
        // Coin supérieur gauche
        $this->Line(15, 25, 25, 25);
        $this->Line(15, 30, 20, 30);
        $this->Line(25, 15, 25, 25);
        $this->Line(30, 15, 30, 20);
        
        // Coin supérieur droit
        $this->Line(272, 25, 282, 25);
        $this->Line(277, 30, 282, 30);
        $this->Line(272, 15, 272, 25);
        $this->Line(267, 15, 267, 20);
        
        // Coin inférieur gauche
        $this->Line(15, 185, 25, 185);
        $this->Line(15, 180, 20, 180);
        $this->Line(25, 185, 25, 195);
        $this->Line(30, 190, 30, 195);
        
        // Coin inférieur droit
        $this->Line(272, 185, 282, 185);
        $this->Line(277, 180, 282, 180);
        $this->Line(272, 185, 272, 195);
        $this->Line(267, 190, 267, 195);
    }
    
    private function drawHeader() {
        // Logo/Sceau
        $this->drawSeal(148.5, 35);
        
        // Titre principal
        $this->SetFont('times', 'B', 36);
        $this->SetTextColor(51, 51, 51);
        $this->SetXY(0, 65);
        $this->Cell(297, 15, 'CERTIFICAT', 0, 1, 'C');
        
        // Sous-titre
        $this->SetFont('helvetica', '', 14);
        $this->SetTextColor(102, 126, 234);
        $this->SetXY(0, 80);
        $this->Cell(297, 8, 'DE FORMATION PROFESSIONNELLE', 0, 1, 'C');
        
        // Ligne décorative
        $this->SetLineWidth(1);
        $this->SetDrawColor(102, 126, 234);
        $this->Line(120, 90, 177, 90);
    }
    
    private function drawSeal(x, y) {
        // Cercle principal
        $this->SetFillColor(102, 126, 234);
        $this->Circle(x, y, 15, 0, 360, 'F');
        
        // Cercle intérieur
        $this->SetDrawColor(255, 255, 255);
        $this->SetLineWidth(1);
        $this->Circle(x, y, 12, 0, 360, 'D');
        
        // Texte du sceau
        $this->SetFont('helvetica', 'B', 18);
        $this->SetTextColor(255, 255, 255);
        $this->SetXY($x - 8, $y - 4);
        $this->Cell(16, 8, 'NC', 0, 0, 'C');
    }
    
    private function drawMainContent() {
        // "Ce certificat atteste que"
        $this->SetFont('helvetica', '', 12);
        $this->SetTextColor(100, 100, 100);
        $this->SetXY(0, 100);
        $this->Cell(297, 8, 'Ce certificat atteste que', 0, 1, 'C');
        
        // Nom du titulaire
        $this->SetFont('times', 'B', 28);
        $this->SetTextColor(51, 51, 51);
        $this->SetXY(0, 112);
        $full_name = $this->certificate_data['firstname'] . ' ' . $this->certificate_data['lastname'];
        $this->Cell(297, 12, $full_name, 0, 1, 'C');
        
        // Ligne sous le nom
        $name_width = $this->GetStringWidth($full_name, 'times', 'B', 28);
        $line_x = (297 - $name_width) / 2;
        $this->SetLineWidth(1);
        $this->SetDrawColor(102, 126, 234);
        $this->Line($line_x, 126, $line_x + $name_width, 126);
        
        // "a terminé avec succès la formation"
        $this->SetFont('helvetica', '', 12);
        $this->SetTextColor(100, 100, 100);
        $this->SetXY(0, 135);
        $this->Cell(297, 8, 'a terminé avec succès la formation', 0, 1, 'C');
    }
    
    private function drawFormationDetails() {
        // Encadré pour la formation
        $this->SetFillColor(240, 248, 255);
        $this->SetDrawColor(102, 126, 234);
        $this->SetLineWidth(0.5);
        $this->Rect(50, 148, 197, 25, 'DF');
        
        // Titre de la formation
        $this->SetFont('helvetica', 'B', 16);
        $this->SetTextColor(51, 51, 51);
        $this->SetXY(55, 153);
        $formation_title = $this->fitText($this->certificate_data['formation_title'], 187, 16);
        $this->Cell(187, 8, $formation_title, 0, 1, 'C');
        
        // Détails de la formation
        $this->SetFont('helvetica', '', 10);
        $this->SetTextColor(100, 100, 100);
        $this->SetXY(55, 163);
        
        $details = [];
        if (!empty($this->certificate_data['category_name'])) {
            $details[] = 'Catégorie: ' . $this->certificate_data['category_name'];
        }
        if (!empty($this->certificate_data['level'])) {
            $details[] = 'Niveau: ' . ucfirst($this->certificate_data['level']);
        }
        if (!empty($this->certificate_data['duration'])) {
            $details[] = 'Durée: ' . $this->certificate_data['duration'];
        }
        
        $details_text = implode(' • ', $details);
        $this->Cell(187, 6, $details_text, 0, 1, 'C');
    }
    
    private function drawCertificateInfo() {
        $y_pos = 185;
        
        // Ligne de séparation
        $this->SetLineWidth(0.3);
        $this->SetDrawColor(200, 200, 200);
        $this->Line(30, $y_pos, 267, $y_pos);
        
        // Informations du certificat
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(100, 100, 100);
        
        // Numéro de certificat
        $this->SetXY(30, $y_pos + 3);
        $this->Cell(70, 4, 'NUMÉRO DE CERTIFICAT', 0, 0, 'C');
        $this->SetFont('helvetica', 'B', 9);
        $this->SetTextColor(51, 51, 51);
        $this->SetXY(30, $y_pos + 8);
        $this->Cell(70, 4, $this->certificate_data['certificate_number'], 0, 0, 'C');
        
        // Date d'émission
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(100, 100, 100);
        $this->SetXY(113.5, $y_pos + 3);
        $this->Cell(70, 4, 'DATE D\'ÉMISSION', 0, 0, 'C');
        $this->SetFont('helvetica', 'B', 9);
        $this->SetTextColor(51, 51, 51);
        $this->SetXY(113.5, $y_pos + 8);
        $issue_date = date('d F Y', strtotime($this->certificate_data['issue_date']));
        $this->Cell(70, 4, $this->translateMonth($issue_date), 0, 0, 'C');
        
        // Code de vérification
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(100, 100, 100);
        $this->SetXY(197, $y_pos + 3);
        $this->Cell(70, 4, 'CODE DE VÉRIFICATION', 0, 0, 'C');
        $this->SetFont('courier', 'B', 9);
        $this->SetTextColor(51, 51, 51);
        $this->SetXY(197, $y_pos + 8);
        $this->Cell(70, 4, $this->certificate_data['verification_code'], 0, 0, 'C');
    }
    
    private function drawVerificationSection() {
        // Section de vérification en bas à droite
        $this->SetFont('helvetica', '', 7);
        $this->SetTextColor(100, 100, 100);
        $this->SetXY(220, 175);
        $this->Cell(50, 3, 'Ce certificat peut être vérifié en ligne', 0, 1, 'C');
        $this->SetXY(220, 178);
        $this->Cell(50, 3, 'avec le code de vérification', 0, 1, 'C');
        
        // QR Code placeholder (à remplacer par un vrai QR code si nécessaire)
        $this->SetDrawColor(200, 200, 200);
        $this->SetLineWidth(0.5);
        $this->Rect(235, 158, 15, 15, 'D');
        $this->SetFont('helvetica', '', 6);
        $this->SetXY(235, 165);
        $this->Cell(15, 3, 'QR CODE', 0, 0, 'C');
    }
    
    private function drawSignatures() {
        $y_pos = 175;
        
        // Signature Netcrafter
        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(100, 100, 100);
        $this->SetXY(40, $y_pos);
        $this->Cell(60, 4, 'NETCRAFTER FORMATION', 0, 1, 'C');
        $this->SetXY(40, $y_pos + 4);
        $this->Cell(60, 4, 'Organisme de formation', 0, 1, 'C');
        
        // Ligne de signature
        $this->SetLineWidth(0.3);
        $this->SetDrawColor(150, 150, 150);
        $this->Line(50, $y_pos - 3, 90, $y_pos - 3);
        
        // Signature directeur
        $this->SetXY(130, $y_pos);
        $this->Cell(60, 4, 'DIRECTEUR PÉDAGOGIQUE', 0, 1, 'C');
        $this->SetXY(130, $y_pos + 4);
        $this->Cell(60, 4, 'Signature et cachet', 0, 1, 'C');
        
        // Ligne de signature
        $this->Line(140, $y_pos - 3, 180, $y_pos - 3);
    }
    
    private function fitText($text, $maxWidth, $fontSize) {
        $this->SetFont('helvetica', 'B', $fontSize);
        while ($this->GetStringWidth($text) > $maxWidth && $fontSize > 8) {
            $fontSize--;
            $this->SetFont('helvetica', 'B', $fontSize);
        }
        return $text;
    }
    
    private function translateMonth($date) {
        $months = [
            'January' => 'Janvier', 'February' => 'Février', 'March' => 'Mars',
            'April' => 'Avril', 'May' => 'Mai', 'June' => 'Juin',
            'July' => 'Juillet', 'August' => 'Août', 'September' => 'Septembre',
            'October' => 'Octobre', 'November' => 'Novembre', 'December' => 'Décembre'
        ];
        
        return str_replace(array_keys($months), array_values($months), $date);
    }
}

// Fonction pour générer un certificat PDF
function generateCertificatePDF($certificate_data, $output_mode = 'I') {
    try {
        $pdf = new CertificatePDF($certificate_data);
        
        if ($output_mode === 'S') {
            // Retourner le contenu PDF comme string
            return $pdf->generateCertificate();
        } else {
            // Afficher directement dans le navigateur
            $pdf_content = $pdf->generateCertificate();
            
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="certificate_' . $certificate_data['certificate_number'] . '.pdf"');
            header('Content-Length: ' . strlen($pdf_content));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
            
            echo $pdf_content;
        }
        
    } catch (Exception $e) {
        // Gestion d'erreur
        header('Content-Type: text/html; charset=utf-8');
        echo "<h1>Erreur lors de la génération du PDF</h1>";
        echo "<p>Erreur: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><a href='javascript:history.back()'>Retour</a></p>";
    }
}

// Version simplifiée sans TCPDF (pour les cas où TCPDF n'est pas disponible)
function generateSimplePDF($certificate_data) {
    // Générer un HTML optimisé pour la conversion PDF
    $html = generateCertificateHTML($certificate_data);
    
    // Headers pour forcer le téléchargement
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="certificate_' . $certificate_data['certificate_number'] . '.html"');
    
    echo $html;
}

function generateCertificateHTML($certificate_data) {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Certificat - <?php echo htmlspecialchars($certificate_data['firstname'] . ' ' . $certificate_data['lastname']); ?></title>
        <style>
            @page {
                size: A4 landscape;
                margin: 1cm;
            }
            body {
                font-family: 'Times New Roman', serif;
                margin: 0;
                padding: 20px;
                background: white;
                color: #333;
            }
            .certificate {
                width: 100%;
                height: 100%;
                border: 8px solid #667eea;
                border-radius: 20px;
                padding: 40px;
                text-align: center;
                position: relative;
                background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            }
            .certificate::before {
                content: "";
                position: absolute;
                top: 15px;
                left: 15px;
                right: 15px;
                bottom: 15px;
                border: 2px solid #667eea;
                border-radius: 10px;
            }
            .seal {
                width: 80px;
                height: 80px;
                background: #667eea;
                border-radius: 50%;
                margin: 0 auto 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 24px;
                font-weight: bold;
            }
            .title {
                font-size: 42px;
                font-weight: bold;
                margin-bottom: 10px;
                color: #333;
                letter-spacing: 3px;
            }
            .subtitle {
                font-size: 16px;
                color: #667eea;
                margin-bottom: 30px;
                font-weight: 600;
            }
            .recipient-name {
                font-size: 32px;
                font-weight: bold;
                color: #333;
                border-bottom: 3px solid #667eea;
                display: inline-block;
                padding-bottom: 5px;
                margin: 20px 0;
            }
            .formation-box {
                background: #f0f8ff;
                border: 2px solid #667eea;
                border-radius: 10px;
                padding: 20px;
                margin: 30px auto;
                max-width: 500px;
            }
            .formation-title {
                font-size: 20px;
                font-weight: bold;
                color: #333;
                margin-bottom: 10px;
            }
            .formation-details {
                font-size: 12px;
                color: #666;
            }
            .cert-info {
                display: flex;
                justify-content: space-around;
                margin-top: 40px;
                padding-top: 20px;
                border-top: 1px solid #ddd;
            }
            .cert-info-item {
                text-align: center;
            }
            .cert-info-label {
                font-size: 10px;
                color: #666;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            .cert-info-value {
                font-size: 12px;
                font-weight: bold;
                color: #333;
                margin-top: 5px;
            }
            .text-sm {
                font-size: 12px;
                color: #666;
            }
        </style>
    </head>
    <body>
        <div class="certificate">
            <div class="seal">NC</div>
            <div class="title">CERTIFICAT</div>
            <div class="subtitle">DE FORMATION PROFESSIONNELLE</div>
            
            <div class="text-sm">Ce certificat atteste que</div>
            <div class="recipient-name"><?php echo htmlspecialchars($certificate_data['firstname'] . ' ' . $certificate_data['lastname']); ?></div>
            <div class="text-sm">a terminé avec succès la formation</div>
            
            <div class="formation-box">
                <div class="formation-title"><?php echo htmlspecialchars($certificate_data['formation_title']); ?></div>
                <div class="formation-details">
                    <?php if (!empty($certificate_data['category_name'])): ?>
                    Catégorie: <?php echo htmlspecialchars($certificate_data['category_name']); ?><br>
                    <?php endif; ?>
                    <?php if (!empty($certificate_data['level'])): ?>
                    Niveau: <?php echo ucfirst($certificate_data['level']); ?><br>
                    <?php endif; ?>
                    <?php if (!empty($certificate_data['duration'])): ?>
                    Durée: <?php echo htmlspecialchars($certificate_data['duration']); ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="cert-info">
                <div class="cert-info-item">
                    <div class="cert-info-label">Numéro de certificat</div>
                    <div class="cert-info-value"><?php echo htmlspecialchars($certificate_data['certificate_number']); ?></div>
                </div>
                <div class="cert-info-item">
                    <div class="cert-info-label">Date d'émission</div>
                    <div class="cert-info-value"><?php echo date('d/m/Y', strtotime($certificate_data['issue_date'])); ?></div>
                </div>
                <div class="cert-info-item">
                    <div class="cert-info-label">Code de vérification</div>
                    <div class="cert-info-value"><?php echo htmlspecialchars($certificate_data['verification_code']); ?></div>
                </div>
            </div>
            
            <div style="position: absolute; bottom: 30px; left: 50px; right: 50px;">
                <div style="display: flex; justify-content: space-between;">
                    <div style="text-align: center;">
                        <div style="border-bottom: 1px solid #333; width: 150px; margin-bottom: 5px;"></div>
                        <div style="font-size: 10px;">Netcrafter Formation<br>Organisme de formation</div>
                    </div>
                    <div style="text-align: center;">
                        <div style="border-bottom: 1px solid #333; width: 150px; margin-bottom: 5px;"></div>
                        <div style="font-size: 10px;">Directeur Pédagogique<br>Signature et cachet</div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

// Auto-détection et utilisation de TCPDF si disponible
function smartGeneratePDF($certificate_data, $output_mode = 'I') {
    if (class_exists('TCPDF')) {
        return generateCertificatePDF($certificate_data, $output_mode);
    } else {
        return generateSimplePDF($certificate_data);
    }
}

?>