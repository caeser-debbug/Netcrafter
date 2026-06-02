<?php
// certificates/download.php
session_start();

// Configuration de la base de données
require_once dirname(__DIR__) . '/formation/db.php';

// Connexion à la base de données
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Erreur de connexion à la base de données");
}
$conn->set_charset("utf8");

// Récupérer le code de vérification
$verification_code = $_GET['code'] ?? '';

if (empty($verification_code)) {
    die("Code de vérification manquant");
}

// Vérifier le certificat
$query = "SELECT c.*, u.firstname, u.lastname, u.email, u.phone, 
          f.title as formation_title, f.duration, f.level,
          fc.name as category_name
          FROM certificates c
          JOIN users u ON c.user_id = u.id
          JOIN formations f ON c.formation_id = f.id
          LEFT JOIN formation_categories fc ON f.category_id = fc.id
          WHERE c.verification_code = ? AND c.verified = 1";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $verification_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Certificat non valide ou révoqué");
}

$certificate = $result->fetch_assoc();

// Inclure le générateur de certificats
require_once('simple_pdf.php');

// Vérifier si on veut télécharger en PDF ou afficher en HTML
$format = $_GET['format'] ?? 'html';

if ($format === 'pdf') {
    // Générer le certificat optimisé pour PDF
    generateCertificate($certificate, 'pdf');
    exit;
} else {
    // Affichage HTML normal
    generateCertificate($certificate, 'html');
    exit;
}

// Ancienne fonction (gardée pour compatibilité)
function generateCertificatePDF($certificate) {
    // Ici, nous utiliserions une bibliothèque comme TCPDF ou FPDF
    // Pour cet exemple, nous créons un HTML simple qui peut être converti en PDF
    
    $html = '
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Certificat de Formation</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 0;
                padding: 40px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
            }
            .certificate {
                background: white;
                padding: 60px;
                border-radius: 20px;
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                text-align: center;
                max-width: 800px;
                width: 100%;
                position: relative;
                border: 5px solid #667eea;
            }
            .certificate::before {
                content: "";
                position: absolute;
                top: 20px;
                left: 20px;
                right: 20px;
                bottom: 20px;
                border: 2px solid #667eea;
                border-radius: 10px;
            }
            .header {
                margin-bottom: 40px;
            }
            .logo {
                width: 100px;
                height: 100px;
                background: #667eea;
                border-radius: 50%;
                margin: 0 auto 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 36px;
                font-weight: bold;
            }
            .title {
                font-size: 48px;
                font-weight: bold;
                color: #333;
                margin-bottom: 10px;
                text-transform: uppercase;
                letter-spacing: 3px;
            }
            .subtitle {
                font-size: 24px;
                color: #667eea;
                margin-bottom: 40px;
            }
            .recipient {
                margin-bottom: 40px;
            }
            .recipient-label {
                font-size: 18px;
                color: #666;
                margin-bottom: 10px;
            }
            .recipient-name {
                font-size: 36px;
                font-weight: bold;
                color: #333;
                border-bottom: 2px solid #667eea;
                display: inline-block;
                padding-bottom: 10px;
            }
            .formation-info {
                margin-bottom: 40px;
            }
            .formation-title {
                font-size: 24px;
                font-weight: bold;
                color: #333;
                margin-bottom: 10px;
            }
            .formation-details {
                font-size: 16px;
                color: #666;
                line-height: 1.6;
            }
            .certificate-info {
                display: flex;
                justify-content: space-between;
                margin-top: 40px;
                padding-top: 20px;
                border-top: 1px solid #eee;
            }
            .info-item {
                text-align: center;
            }
            .info-label {
                font-size: 12px;
                color: #666;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            .info-value {
                font-size: 14px;
                font-weight: bold;
                color: #333;
                margin-top: 5px;
            }
            .qr-code {
                width: 80px;
                height: 80px;
                background: #f0f0f0;
                border: 1px solid #ddd;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 10px;
                color: #666;
                margin: 0 auto;
            }
        </style>
    </head>
    <body>
        <div class="certificate">
            <div class="header">
                <div class="logo">NC</div>
                <div class="title">Certificat</div>
                <div class="subtitle">de Formation Professionnelle</div>
            </div>
            
            <div class="recipient">
                <div class="recipient-label">Ce certificat atteste que</div>
                <div class="recipient-name">' . htmlspecialchars($certificate['firstname'] . ' ' . $certificate['lastname']) . '</div>
            </div>
            
            <div class="formation-info">
                <div class="recipient-label">a terminé avec succès la formation</div>
                <div class="formation-title">' . htmlspecialchars($certificate['formation_title']) . '</div>
                <div class="formation-details">
                    Catégorie: ' . htmlspecialchars($certificate['category_name']) . '<br>
                    Niveau: ' . ucfirst($certificate['level']) . '<br>
                    ' . ($certificate['duration'] ? 'Durée: ' . htmlspecialchars($certificate['duration']) : '') . '
                </div>
            </div>
            
            <div class="certificate-info">
                <div class="info-item">
                    <div class="info-label">Numéro de certificat</div>
                    <div class="info-value">' . htmlspecialchars($certificate['certificate_number']) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Date d\'émission</div>
                    <div class="info-value">' . date('d/m/Y', strtotime($certificate['issue_date'])) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Code de vérification</div>
                    <div class="info-value">' . htmlspecialchars($certificate['verification_code']) . '</div>
                </div>
            </div>
            
            <div style="margin-top: 30px;">
                <div class="qr-code">QR Code</div>
                <div style="font-size: 12px; color: #666; margin-top: 10px;">
                    Vérifiez ce certificat sur: netcrafter.com/verify
                </div>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}

// Vérifier si on veut télécharger en PDF ou afficher en HTML
$format = $_GET['format'] ?? 'pdf';

if ($format === 'pdf') {
    // Pour un vrai PDF, vous devriez utiliser une bibliothèque comme TCPDF
    // Ici, nous générons un HTML que le navigateur peut imprimer en PDF
    $html = generateCertificatePDF($certificate);
    
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: inline; filename="certificate_' . $certificate['certificate_number'] . '.html"');
    
    echo $html;
} else {
    // Affichage HTML pour prévisualisation
    $html = generateCertificatePDF($certificate);
    echo $html;
}

$conn->close();
?>