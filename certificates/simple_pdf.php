<?php
// certificates/simple_pdf.php
// Générateur de certificats sans dépendances externes

function generateCertificateHTML($certificate_data, $for_pdf = true) {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Certificat - <?php echo htmlspecialchars($certificate_data['firstname'] . ' ' . $certificate_data['lastname']); ?></title>
        <style>
            <?php if ($for_pdf): ?>
            @page {
                size: A4 landscape;
                margin: 0.5cm;
            }
            <?php endif; ?>
            
            @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Inter:wght@300;400;500;600;700&display=swap');
            
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Inter', Arial, sans-serif;
                background: #f8fafc;
                padding: 20px;
                color: #333;
            }
            
            .certificate-container {
                width: 100%;
                max-width: 1000px;
                margin: 0 auto;
                background: white;
                position: relative;
                <?php if ($for_pdf): ?>
                width: 297mm;
                height: 210mm;
                <?php else: ?>
                min-height: 700px;
                <?php endif; ?>
            }
            
            .certificate {
                width: 100%;
                height: 100%;
                border: 12px solid;
                border-image: linear-gradient(45deg, #667eea, #764ba2) 1;
                padding: 40px;
                text-align: center;
                position: relative;
                background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
                overflow: hidden;
            }
            
            .certificate::before {
                content: "";
                position: absolute;
                top: 20px;
                left: 20px;
                right: 20px;
                bottom: 20px;
                border: 3px solid #667eea;
                border-radius: 15px;
                opacity: 0.3;
            }
            
            /* Éléments décoratifs dans les coins */
            .corner-decoration {
                position: absolute;
                width: 60px;
                height: 60px;
                border: 4px solid #667eea;
                transform: rotate(45deg);
                opacity: 0.1;
            }
            
            .corner-decoration.top-left {
                top: -30px;
                left: -30px;
            }
            
            .corner-decoration.top-right {
                top: -30px;
                right: -30px;
            }
            
            .corner-decoration.bottom-left {
                bottom: -30px;
                left: -30px;
            }
            
            .corner-decoration.bottom-right {
                bottom: -30px;
                right: -30px;
            }
            
            .seal {
                width: 120px;
                height: 120px;
                background: linear-gradient(45deg, #667eea, #764ba2);
                border-radius: 50%;
                margin: 0 auto 30px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 36px;
                font-weight: bold;
                position: relative;
                box-shadow: 0 8px 32px rgba(102, 126, 234, 0.3);
            }
            
            .seal::before {
                content: '';
                position: absolute;
                inset: 15px;
                border: 3px dashed white;
                border-radius: 50%;
                opacity: 0.8;
            }
            
            .certificate-title {
                font-family: 'Playfair Display', serif;
                font-size: 72px;
                font-weight: 700;
                color: #333;
                margin-bottom: 15px;
                letter-spacing: 8px;
                text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
                background: linear-gradient(45deg, #667eea, #764ba2);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }
            
            .certificate-subtitle {
                font-size: 24px;
                color: #667eea;
                font-weight: 600;
                margin-bottom: 40px;
                text-transform: uppercase;
                letter-spacing: 4px;
            }
            
            .attestation-text {
                font-size: 18px;
                color: #666;
                margin-bottom: 20px;
                font-style: italic;
            }
            
            .recipient-name {
                font-family: 'Playfair Display', serif;
                font-size: 48px;
                font-weight: 700;
                color: #333;
                margin: 30px 0;
                padding-bottom: 15px;
                border-bottom: 4px solid #667eea;
                display: inline-block;
                position: relative;
            }
            
            .recipient-name::after {
                content: '';
                position: absolute;
                bottom: -8px;
                left: 50%;
                transform: translateX(-50%);
                width: 60px;
                height: 4px;
                background: #764ba2;
            }
            
            .completion-text {
                font-size: 18px;
                color: #666;
                margin-bottom: 30px;
            }
            
            .formation-box {
                background: linear-gradient(135deg, #f0f8ff 0%, #e6f3ff 100%);
                border: 3px solid #667eea;
                border-radius: 20px;
                padding: 30px;
                margin: 40px auto;
                max-width: 600px;
                position: relative;
                box-shadow: 0 8px 32px rgba(102, 126, 234, 0.1);
            }
            
            .formation-box::before {
                content: '';
                position: absolute;
                top: -2px;
                left: -2px;
                right: -2px;
                bottom: -2px;
                background: linear-gradient(45deg, #667eea, #764ba2);
                border-radius: 22px;
                z-index: -1;
            }
            
            .formation-title {
                font-family: 'Playfair Display', serif;
                font-size: 28px;
                font-weight: 700;
                color: #333;
                margin-bottom: 15px;
                line-height: 1.3;
            }
            
            .formation-details {
                font-size: 16px;
                color: #555;
                line-height: 1.6;
            }
            
            .formation-details strong {
                color: #667eea;
            }
            
            .certificate-info {
                margin-top: 50px;
                padding-top: 30px;
                border-top: 2px solid #e9ecef;
                display: flex;
                justify-content: space-around;
                flex-wrap: wrap;
                gap: 20px;
            }
            
            .cert-info-item {
                text-align: center;
                min-width: 200px;
            }
            
            .cert-info-label {
                font-size: 12px;
                color: #666;
                text-transform: uppercase;
                letter-spacing: 2px;
                margin-bottom: 8px;
                font-weight: 600;
            }
            
            .cert-info-value {
                font-family: 'Courier New', monospace;
                font-size: 16px;
                font-weight: bold;
                color: #333;
                background: #f8f9fa;
                padding: 8px 12px;
                border-radius: 8px;
                border: 1px solid #e9ecef;
            }
            
            .verification-section {
                position: absolute;
                bottom: 40px;
                right: 40px;
                text-align: center;
                font-size: 12px;
                color: #666;
            }
            
            .qr-placeholder {
                width: 80px;
                height: 80px;
                background: #f8f9fa;
                border: 2px dashed #ccc;
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 8px;
                font-size: 10px;
                color: #999;
            }
            
            .signatures {
                position: absolute;
                bottom: 40px;
                left: 40px;
                right: 200px;
                display: flex;
                justify-content: space-between;
            }
            
            .signature-block {
                text-align: center;
                font-size: 12px;
                color: #666;
            }
            
            .signature-line {
                width: 150px;
                height: 1px;
                background: #333;
                margin-bottom: 8px;
            }
            
            .signature-title {
                font-weight: 600;
                margin-bottom: 2px;
            }
            
            /* Responsive */
            @media (max-width: 768px) {
                .certificate-title { font-size: 48px; }
                .recipient-name { font-size: 32px; }
                .formation-title { font-size: 20px; }
                .certificate-info { flex-direction: column; }
                .signatures { position: static; flex-direction: column; gap: 20px; margin-top: 40px; }
            }
            
            /* Print styles */
            @media print {
                body { 
                    background: white !important; 
                    padding: 0 !important;
                }
                .certificate-container {
                    width: 100% !important;
                    height: 100vh !important;
                    max-width: none !important;
                }
                .corner-decoration { display: none; }
            }
        </style>
    </head>
    <body>
        <div class="certificate-container">
            <div class="certificate">
                <!-- Décorations dans les coins -->
                <div class="corner-decoration top-left"></div>
                <div class="corner-decoration top-right"></div>
                <div class="corner-decoration bottom-left"></div>
                <div class="corner-decoration bottom-right"></div>
                
                <!-- En-tête avec sceau -->
                <div class="seal">NC</div>
                
                <!-- Titre principal -->
                <h1 class="certificate-title">CERTIFICAT</h1>
                <div class="certificate-subtitle">de Formation Professionnelle</div>
                
                <!-- Contenu principal -->
                <div class="attestation-text">Ce certificat atteste que</div>
                
                <div class="recipient-name">
                    <?php echo htmlspecialchars($certificate_data['firstname'] . ' ' . $certificate_data['lastname']); ?>
                </div>
                
                <div class="completion-text">a terminé avec succès la formation</div>
                
                <!-- Détails de la formation -->
                <div class="formation-box">
                    <div class="formation-title">
                        <?php echo htmlspecialchars($certificate_data['formation_title']); ?>
                    </div>
                    <div class="formation-details">
                        <?php if (!empty($certificate_data['category_name'])): ?>
                        <strong>Catégorie :</strong> <?php echo htmlspecialchars($certificate_data['category_name']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($certificate_data['level'])): ?>
                        <strong>Niveau :</strong> <?php echo ucfirst($certificate_data['level']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($certificate_data['duration'])): ?>
                        <strong>Durée :</strong> <?php echo htmlspecialchars($certificate_data['duration']); ?><br>
                        <?php endif; ?>
                        <?php if (!empty($certificate_data['quiz_score'])): ?>
                        <strong>Score obtenu :</strong> <?php echo $certificate_data['quiz_score']; ?>%
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Informations du certificat -->
                <div class="certificate-info">
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
                
                <!-- Section de vérification -->
                <div class="verification-section">
                    <div class="qr-placeholder">
                        <div>QR<br>CODE</div>
                    </div>
                    <div>Vérifiez ce certificat<br>sur netcrafter.com/verify</div>
                </div>
                
                <!-- Signatures -->
                <div class="signatures">
                    <div class="signature-block">
                        <div class="signature-line"></div>
                        <div class="signature-title">Netcrafter Formation</div>
                        <div>Organisme de formation</div>
                    </div>
                    <div class="signature-block">
                        <div class="signature-line"></div>
                        <div class="signature-title">Directeur Pédagogique</div>
                        <div>Signature et cachet</div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (!$for_pdf): ?>
        <script>
            // Fonctions pour les boutons d'action
            function printCertificate() {
                window.print();
            }
            
            function downloadPDF() {
                // Redirect vers la version PDF
                window.location.href = window.location.href + '&format=pdf';
            }
        </script>
        <?php endif; ?>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

// Fonction principale pour générer le certificat
function generateCertificate($certificate_data, $format = 'html') {
    if ($format === 'pdf') {
        // Headers pour PDF
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="certificat_' . $certificate_data['certificate_number'] . '.html"');
        echo generateCertificateHTML($certificate_data, true);
    } else {
        // Affichage HTML normal
        echo generateCertificateHTML($certificate_data, false);
    }
}

?>