<?php
// certificates/preview.php
// Version simplifiée pour aperçu dans les modales admin

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
$query = "SELECT c.*, u.firstname, u.lastname, 
          f.title as formation_title, f.level,
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
    echo "<div class='text-center p-8 text-red-600'>Certificat non trouvé ou révoqué</div>";
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
    <title>Aperçu Certificat</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Inter:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
        }
        
        .certificate-title {
            font-family: 'Playfair Display', serif;
        }
        
        .certificate-mini {
            background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
            border: 3px solid #667eea;
            transform: scale(0.8);
            transform-origin: top;
        }
        
        .seal-mini {
            width: 60px;
            height: 60px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            font-weight: bold;
            margin: 0 auto;
        }
    </style>
</head>
<body class="p-4">
    <div class="certificate-mini rounded-lg p-8 mx-auto max-w-2xl">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="seal-mini mb-4">NC</div>
            <h1 class="certificate-title text-3xl font-bold text-gray-800 mb-2">Certificat</h1>
            <div class="text-blue-600 font-semibold bg-blue-100 px-4 py-1 rounded-full inline-block">
                de Formation Professionnelle
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="text-center mb-8">
            <p class="text-gray-600 mb-4">Ce certificat atteste que</p>
            
            <div class="mb-6">
                <h2 class="certificate-title text-2xl font-bold text-gray-800 border-b-2 border-blue-500 pb-2 inline-block">
                    <?php echo htmlspecialchars($certificate['firstname'] . ' ' . $certificate['lastname']); ?>
                </h2>
            </div>
            
            <p class="text-gray-600 mb-4">a terminé avec succès la formation</p>
            
            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
                <h3 class="text-xl font-bold text-gray-800 mb-2">
                    <?php echo htmlspecialchars($certificate['formation_title']); ?>
                </h3>
                <div class="text-sm text-gray-600 flex justify-center space-x-4">
                    <span><strong>Catégorie:</strong> <?php echo htmlspecialchars($certificate['category_name']); ?></span>
                    <span><strong>Niveau:</strong> <?php echo ucfirst($certificate['level']); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Certificate Details -->
        <div class="border-t border-gray-200 pt-6">
            <div class="grid grid-cols-3 gap-4 text-center text-sm">
                <div>
                    <div class="text-gray-500 uppercase tracking-wide mb-1">Numéro</div>
                    <div class="font-mono text-xs font-semibold text-gray-800">
                        <?php echo htmlspecialchars($certificate['certificate_number']); ?>
                    </div>
                </div>
                <div>
                    <div class="text-gray-500 uppercase tracking-wide mb-1">Date d'émission</div>
                    <div class="text-xs font-semibold text-gray-800">
                        <?php echo date('d/m/Y', strtotime($certificate['issue_date'])); ?>
                    </div>
                </div>
                <div>
                    <div class="text-gray-500 uppercase tracking-wide mb-1">Code</div>
                    <div class="font-mono text-xs font-semibold text-gray-800">
                        <?php echo htmlspecialchars($certificate['verification_code']); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Verification Badge -->
        <div class="mt-6 text-center">
            <div class="inline-flex items-center bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm">
                <i class="fas fa-shield-alt mr-2"></i>
                Certificat vérifié et authentique
            </div>
        </div>
        
        <!-- Actions -->
        <div class="mt-6 text-center space-x-2">
            <button onclick="openFullView()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm transition-colors">
                <i class="fas fa-expand mr-2"></i>Vue complète
            </button>
            <button onclick="downloadCertificate()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-sm transition-colors">
                <i class="fas fa-download mr-2"></i>Télécharger
            </button>
        </div>
    </div>

    <script>
        function openFullView() {
            window.open('view.php?code=<?php echo $certificate['verification_code']; ?>', '_blank');
        }
        
        function downloadCertificate() {
            window.open('download.php?code=<?php echo $certificate['verification_code']; ?>', '_blank');
        }
        
        // Animation d'entrée
        document.addEventListener('DOMContentLoaded', function() {
            const certificate = document.querySelector('.certificate-mini');
            certificate.style.opacity = '0';
            certificate.style.transform = 'scale(0.8) translateY(20px)';
            
            setTimeout(() => {
                certificate.style.transition = 'all 0.6s ease';
                certificate.style.opacity = '1';
                certificate.style.transform = 'scale(0.8) translateY(0)';
            }, 100);
        });
    </script>
</body>
</html>