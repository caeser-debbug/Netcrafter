<?php
// certificates/view.php
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
          fc.name as category_name,
          qa.score as quiz_score
          FROM certificates c
          JOIN users u ON c.user_id = u.id
          JOIN formations f ON c.formation_id = f.id
          LEFT JOIN formation_categories fc ON f.category_id = fc.id
          LEFT JOIN quiz_attempts qa ON c.quiz_attempt_id = qa.id
          WHERE c.verification_code = ? AND c.verified = 1";

$stmt = $conn->prepare($query);
$stmt->bind_param("s", $verification_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Afficher une page d'erreur élégante
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Certificat non trouvé - Netcrafter</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-100 flex items-center justify-center min-h-screen">
        <div class="text-center">
            <div class="mb-8">
                <i class="fas fa-exclamation-triangle text-6xl text-red-500"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-800 mb-4">Certificat non trouvé</h1>
            <p class="text-gray-600 mb-8">Le certificat que vous recherchez n'existe pas ou a été révoqué.</p>
            <a href="../" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg transition-colors">
                Retour à l'accueil
            </a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$certificate = $result->fetch_assoc();
$conn->close();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificat de Formation - <?php echo htmlspecialchars($certificate['firstname'] . ' ' . $certificate['lastname']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Inter:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
        }
        
        .certificate-title {
            font-family: 'Playfair Display', serif;
        }
        
        .certificate-paper {
            background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
            box-shadow: 
                0 20px 40px rgba(0, 0, 0, 0.1),
                0 0 0 1px rgba(0, 0, 0, 0.05),
                inset 0 1px 0 rgba(255, 255, 255, 0.9);
        }
        
        .certificate-border {
            background: linear-gradient(45deg, #667eea, #764ba2);
            padding: 8px;
            border-radius: 20px;
        }
        
        .certificate-inner {
            background: white;
            border-radius: 12px;
            position: relative;
        }
        
        .decorative-corner {
            position: absolute;
            width: 60px;
            height: 60px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            transform: rotate(45deg);
            opacity: 0.1;
        }
        
        .decorative-corner.top-left {
            top: -30px;
            left: -30px;
        }
        
        .decorative-corner.top-right {
            top: -30px;
            right: -30px;
        }
        
        .decorative-corner.bottom-left {
            bottom: -30px;
            left: -30px;
        }
        
        .decorative-corner.bottom-right {
            bottom: -30px;
            right: -30px;
        }
        
        .seal {
            width: 120px;
            height: 120px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: bold;
            position: relative;
            margin: 0 auto;
        }
        
        .seal::before {
            content: '';
            position: absolute;
            inset: 10px;
            border: 2px dashed white;
            border-radius: 50%;
        }
        
        @media print {
            body {
                background: white !important;
            }
            .no-print {
                display: none !important;
            }
            .certificate-paper {
                box-shadow: none;
                border: 2px solid #667eea;
            }
        }
        
        .ribbon {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 8px 24px;
            border-radius: 25px;
            font-weight: 600;
            display: inline-block;
            margin: 0 auto;
        }
        
        .verification-qr {
            width: 100px;
            height: 100px;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: #6c757d;
            text-align: center;
            margin: 0 auto;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-purple-50 min-h-screen py-8">
    <!-- Actions Bar -->
    <div class="no-print fixed top-4 right-4 flex space-x-2 z-10">
        <button onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow-lg transition-colors">
            <i class="fas fa-print mr-2"></i>Imprimer
        </button>
        <button onclick="downloadPDF()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg shadow-lg transition-colors">
            <i class="fas fa-download mr-2"></i>Télécharger PDF
        </button>
        <button onclick="shareCertificate()" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg shadow-lg transition-colors">
            <i class="fas fa-share mr-2"></i>Partager
        </button>
    </div>

    <div class="container mx-auto px-4 max-w-4xl">
        <div class="certificate-border">
            <div class="certificate-inner certificate-paper p-12 relative overflow-hidden">
                <!-- Decorative Corners -->
                <div class="decorative-corner top-left"></div>
                <div class="decorative-corner top-right"></div>
                <div class="decorative-corner bottom-left"></div>
                <div class="decorative-corner bottom-right"></div>
                
                <!-- Header -->
                <div class="text-center mb-12">
                    <div class="seal mb-6">
                        <span>NC</span>
                    </div>
                    <h1 class="certificate-title text-5xl font-bold text-gray-800 mb-4">Certificat</h1>
                    <div class="ribbon">
                        de Formation Professionnelle
                    </div>
                </div>
                
                <!-- Main Content -->
                <div class="text-center mb-12">
                    <p class="text-lg text-gray-600 mb-6">Ce certificat atteste que</p>
                    
                    <div class="mb-8">
                        <h2 class="certificate-title text-4xl font-bold text-gray-800 border-b-4 border-blue-500 pb-4 inline-block">
                            <?php echo htmlspecialchars($certificate['firstname'] . ' ' . $certificate['lastname']); ?>
                        </h2>
                    </div>
                    
                    <p class="text-lg text-gray-600 mb-4">a terminé avec succès la formation</p>
                    
                    <div class="bg-blue-50 border-l-4 border-blue-500 p-6 mb-8 text-left max-w-2xl mx-auto">
                        <h3 class="text-2xl font-bold text-gray-800 mb-4">
                            <?php echo htmlspecialchars($certificate['formation_title']); ?>
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600">
                            <div>
                                <strong>Catégorie:</strong> <?php echo htmlspecialchars($certificate['category_name']); ?>
                            </div>
                            <div>
                                <strong>Niveau:</strong> <?php echo ucfirst($certificate['level']); ?>
                            </div>
                            <?php if ($certificate['duration']): ?>
                            <div>
                                <strong>Durée:</strong> <?php echo htmlspecialchars($certificate['duration']); ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($certificate['quiz_score']): ?>
                            <div>
                                <strong>Score obtenu:</strong> <?php echo $certificate['quiz_score']; ?>%
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Certificate Details -->
                <div class="border-t border-gray-200 pt-8">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-center">
                        <div>
                            <div class="text-sm text-gray-500 uppercase tracking-wide mb-2">Numéro de certificat</div>
                            <div class="font-mono text-lg font-semibold text-gray-800">
                                <?php echo htmlspecialchars($certificate['certificate_number']); ?>
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 uppercase tracking-wide mb-2">Date d'émission</div>
                            <div class="text-lg font-semibold text-gray-800">
                                <?php echo date('d F Y', strtotime($certificate['issue_date'])); ?>
                            </div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 uppercase tracking-wide mb-2">Code de vérification</div>
                            <div class="font-mono text-lg font-semibold text-gray-800">
                                <?php echo htmlspecialchars($certificate['verification_code']); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Verification Section -->
                <div class="mt-12 pt-8 border-t border-gray-200">
                    <div class="flex flex-col md:flex-row items-center justify-between">
                        <div class="text-center md:text-left mb-6 md:mb-0">
                            <h4 class="font-semibold text-gray-800 mb-2">Vérification du certificat</h4>
                            <p class="text-sm text-gray-600 mb-4">
                                Ce certificat peut être vérifié à tout moment en ligne<br>
                                en utilisant le code de vérification ci-dessus.
                            </p>
                            <div class="flex items-center justify-center md:justify-start space-x-4 text-sm text-gray-500">
                                <div class="flex items-center">
                                    <i class="fas fa-shield-alt text-green-500 mr-2"></i>
                                    Certificat vérifié
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-calendar text-blue-500 mr-2"></i>
                                    <?php echo date('d/m/Y', strtotime($certificate['issue_date'])); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center">
                            <div class="verification-qr mb-2">
                                <div>
                                    <i class="fas fa-qrcode text-2xl mb-2"></i><br>
                                    Code QR<br>de vérification
                                </div>
                            </div>
                            <div class="text-xs text-gray-500">
                                netcrafter.com/verify
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Signature Section -->
                <div class="mt-12 pt-8 border-t border-gray-200">
                    <div class="flex justify-between items-end">
                        <div class="text-center">
                            <div class="w-48 border-b border-gray-400 mb-2"></div>
                            <div class="text-sm text-gray-600">
                                <strong>Netcrafter Formation</strong><br>
                                Organisme de formation
                            </div>
                        </div>
                        <div class="text-center">
                            <div class="w-48 border-b border-gray-400 mb-2"></div>
                            <div class="text-sm text-gray-600">
                                <strong>Directeur Pédagogique</strong><br>
                                Signature et cachet
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Additional Info Section -->
        <div class="no-print mt-8 bg-white rounded-lg shadow-lg p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                Informations supplémentaires
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-medium text-gray-700 mb-2">Titulaire du certificat</h4>
                    <div class="text-sm text-gray-600 space-y-1">
                        <div><strong>Nom complet:</strong> <?php echo htmlspecialchars($certificate['firstname'] . ' ' . $certificate['lastname']); ?></div>
                        <?php if (!empty($certificate['email'])): ?>
                        <div><strong>Email:</strong> <?php echo htmlspecialchars($certificate['email']); ?></div>
                        <?php endif; ?>
                        <div><strong>Date d'obtention:</strong> <?php echo date('d F Y à H:i', strtotime($certificate['issue_date'])); ?></div>
                    </div>
                </div>
                <div>
                    <h4 class="font-medium text-gray-700 mb-2">À propos de cette formation</h4>
                    <div class="text-sm text-gray-600 space-y-1">
                        <div><strong>Organisme:</strong> Netcrafter Formation</div>
                        <div><strong>Type:</strong> Formation professionnelle</div>
                        <div><strong>Validation:</strong> Contrôle continu et évaluation finale</div>
                    </div>
                </div>
            </div>
            
            <!-- Verification Tool -->
            <div class="mt-6 pt-6 border-t border-gray-200">
                <h4 class="font-medium text-gray-700 mb-4">Vérifier un autre certificat</h4>
                <div class="flex flex-col sm:flex-row gap-3">
                    <input type="text" id="verifyCode" placeholder="Entrez le code de vérification..." class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <button onclick="verifyCertificate()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg transition-colors">
                        <i class="fas fa-search mr-2"></i>Vérifier
                    </button>
                </div>
                <div id="verifyResult" class="mt-3"></div>
            </div>
        </div>
    </div>

    <script>
        function downloadPDF() {
            // Rediriger vers la version téléchargement
            window.open('download.php?code=<?php echo $certificate['verification_code']; ?>&format=pdf', '_blank');
        }
        
        function shareCertificate() {
            const url = window.location.href;
            const title = 'Certificat de Formation - <?php echo addslashes($certificate['firstname'] . ' ' . $certificate['lastname']); ?>';
            
            if (navigator.share) {
                navigator.share({
                    title: title,
                    text: 'Consultez ce certificat de formation professionnelle',
                    url: url
                });
            } else {
                // Fallback pour les navigateurs qui ne supportent pas l'API Web Share
                navigator.clipboard.writeText(url).then(() => {
                    alert('Lien du certificat copié dans le presse-papiers !');
                });
            }
        }
        
        function verifyCertificate() {
            const code = document.getElementById('verifyCode').value.trim();
            const resultDiv = document.getElementById('verifyResult');
            
            if (!code) {
                resultDiv.innerHTML = '<div class="text-red-600 text-sm">Veuillez entrer un code de vérification.</div>';
                return;
            }
            
            resultDiv.innerHTML = '<div class="text-blue-600 text-sm"><i class="fas fa-spinner fa-spin mr-2"></i>Vérification en cours...</div>';
            
            fetch('../admin/api/certificates.php?action=verify&code=' + encodeURIComponent(code))
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        resultDiv.innerHTML = `
                            <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-sm">
                                <div class="flex items-center text-green-800 mb-2">
                                    <i class="fas fa-check-circle mr-2"></i>
                                    <strong>Certificat valide</strong>
                                </div>
                                <div class="text-green-700">
                                    <strong>Titulaire:</strong> ${data.certificate.user_name}<br>
                                    <strong>Formation:</strong> ${data.certificate.formation_title}<br>
                                    <strong>Date d'émission:</strong> ${data.certificate.issue_date}<br>
                                    <strong>Numéro:</strong> ${data.certificate.certificate_number}
                                </div>
                            </div>
                        `;
                    } else {
                        resultDiv.innerHTML = `
                            <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-sm">
                                <div class="flex items-center text-red-800">
                                    <i class="fas fa-times-circle mr-2"></i>
                                    <strong>Certificat non valide ou inexistant</strong>
                                </div>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    resultDiv.innerHTML = '<div class="text-red-600 text-sm">Erreur lors de la vérification. Veuillez réessayer.</div>';
                });
        }
        
        // Permettre la vérification avec la touche Entrée
        document.getElementById('verifyCode').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                verifyCertificate();
            }
        });
        
        // Animation d'entrée
        document.addEventListener('DOMContentLoaded', function() {
            const certificate = document.querySelector('.certificate-inner');
            certificate.style.opacity = '0';
            certificate.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                certificate.style.transition = 'all 0.8s ease';
                certificate.style.opacity = '1';
                certificate.style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
</body>
</html>