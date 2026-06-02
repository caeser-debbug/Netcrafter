<?php
// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Configuration de la base de données
require_once __DIR__ . '/shop/db.php';

// Numéro WhatsApp de l'entreprise (format international sans le +)
$whatsappNumber = "22788672115"; // Remplacez par votre numéro

// Fonction pour nettoyer les entrées
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Vérifier si le formulaire a été soumis
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    try {
        // Récupérer et nettoyer les données du formulaire
        $nom = sanitize($_POST["nom"] ?? "");
        $prenom = sanitize($_POST["prenom"] ?? "");
        $email = sanitize($_POST["email"] ?? "");
        $telephone = sanitize($_POST["telephone"] ?? "");
        $entreprise = isset($_POST["entreprise"]) ? sanitize($_POST["entreprise"]) : "";
        
        // Vérifier les champs obligatoires
        if (empty($nom) || empty($prenom) || empty($email) || empty($telephone)) {
            throw new Exception("Tous les champs obligatoires doivent être remplis");
        }

        // Récupérer les services sélectionnés
        $services = [];
        if (isset($_POST["services"]) && is_array($_POST["services"])) {
            foreach ($_POST["services"] as $service) {
                $services[] = sanitize($service);
            }
        }
        $servicesStr = implode(", ", $services);
        
        $budget = isset($_POST["budget"]) ? sanitize($_POST["budget"]) : "";
        $delai = isset($_POST["delai"]) ? sanitize($_POST["delai"]) : "";
        $description = sanitize($_POST["description"] ?? "");
        
        if (empty($description)) {
            throw new Exception("La description du projet est obligatoire");
        }
        
        $source = isset($_POST["source"]) ? sanitize($_POST["source"]) : "";
        
        // Générer un ID unique pour la demande
        $devis_id = uniqid("DEV");
        
        // Date de soumission
        $date_soumission = date("Y-m-d H:i:s");
        
        // Créer la connexion à la base de données
        $conn = new mysqli($servername, $username, $password, $dbname);
        
        // Vérifier la connexion
        if ($conn->connect_error) {
            throw new Exception("Erreur de connexion à la base de données: " . $conn->connect_error);
        }
        
        // Définir le charset
        $conn->set_charset("utf8mb4");
        
        // Vérifier si la table existe
        $tableExists = $conn->query("SHOW TABLES LIKE 'demandes_devis'")->num_rows > 0;
        
        if (!$tableExists) {
            // Créer la table si elle n'existe pas
            $createTable = "CREATE TABLE demandes_devis (
                id INT AUTO_INCREMENT PRIMARY KEY,
                devis_id VARCHAR(20) NOT NULL,
                nom VARCHAR(100) NOT NULL,
                prenom VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                telephone VARCHAR(50) NOT NULL,
                entreprise VARCHAR(100),
                services TEXT,
                budget VARCHAR(20),
                delai VARCHAR(20),
                description TEXT NOT NULL,
                source VARCHAR(50),
                date_soumission DATETIME NOT NULL,
                statut VARCHAR(20) NOT NULL
            ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            
            if (!$conn->query($createTable)) {
                throw new Exception("Impossible de créer la table demandes_devis: " . $conn->error);
            }
        }
        
        // Préparer la requête SQL
        $stmt = $conn->prepare("INSERT INTO demandes_devis (devis_id, nom, prenom, email, telephone, entreprise, services, budget, delai, description, source, date_soumission, statut) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'nouveau')");
        
        if (!$stmt) {
            throw new Exception("Erreur de préparation de la requête: " . $conn->error);
        }
        
        // Lier les paramètres
        $stmt->bind_param("ssssssssssss", $devis_id, $nom, $prenom, $email, $telephone, $entreprise, $servicesStr, $budget, $delai, $description, $source, $date_soumission);
        
        // Exécuter la requête
        $result = $stmt->execute();
        
        // Vérifier si l'exécution a réussi
        if (!$result) {
            throw new Exception("Erreur lors de l'insertion des données: " . $stmt->error);
        }
        
        // Fermer la requête et la connexion
        $stmt->close();
        $conn->close();

        // Send confirmation email to client
        require_once __DIR__ . '/includes/mail.php';
        nc_mail_devis($email, [
            'nom'       => $nom,
            'prenom'    => $prenom,
            'email'     => $email,
            'telephone' => $telephone,
            'devis_id'  => $devis_id,
        ]);

        // Préparer le message WhatsApp
        $message = "📋 *NOUVELLE DEMANDE DE DEVIS* 📋\n\n";
        $message .= "*ID Devis:* " . $devis_id . "\n";
        $message .= "*Date:* " . date("d/m/Y H:i", strtotime($date_soumission)) . "\n\n";
        
        $message .= "*INFORMATIONS CLIENT*\n";
        $message .= "Nom: " . $prenom . " " . $nom . "\n";
        $message .= "Email: " . $email . "\n";
        $message .= "Téléphone: " . $telephone . "\n";
        
        if (!empty($entreprise)) {
            $message .= "Entreprise: " . $entreprise . "\n";
        }
        
        $message .= "\n*PROJET*\n";
        
        if (!empty($servicesStr)) {
            $message .= "Services: " . $servicesStr . "\n";
        }
        
        if (!empty($budget)) {
            $budgetLabels = [
                'moins-50000' => 'Moins de 50000 FCFA',
                '50000-100000' => '50000 - 100000 FCFA',
                '100000-300000' => '100000 - 300000 FCFA',
                '300000-500000' => '300000 - 500000 FCFA',
                'plus-500000' => 'Plus de 500000 FCFA'
            ];
            $message .= "Budget: " . ($budgetLabels[$budget] ?? $budget) . "\n";
        }
        
        if (!empty($delai)) {
            $delaiLabels = [
                'urgent' => 'Urgent (moins de 2 semaines)',
                '1-mois' => '1 mois',
                '2-3-mois' => '2-3 mois',
                'flexible' => 'Flexible / À discuter'
            ];
            $message .= "Délai: " . ($delaiLabels[$delai] ?? $delai) . "\n";
        }
        
        $message .= "\nDescription:\n" . $description . "\n";
        
        if (!empty($source)) {
            $sourceLabels = [
                'recherche-google' => 'Recherche Google',
                'reseaux-sociaux' => 'Réseaux sociaux',
                'recommandation' => 'Recommandation',
                'publicite' => 'Publicité',
                'autre' => 'Autre'
            ];
            $message .= "\nSource: " . ($sourceLabels[$source] ?? $source);
        }
        
        // Encoder le message pour l'URL
        $encodedMessage = urlencode($message);
        
        // URL pour WhatsApp
        $whatsappUrl = "https://api.whatsapp.com/send?phone={$whatsappNumber}&text={$encodedMessage}";
        
        // Réponse de succès
        header('Content-Type: application/json');
        echo json_encode([
            "success" => true, 
            "devis_id" => $devis_id,
            "whatsapp_url" => $whatsappUrl
        ]);
    
    } catch (Exception $e) {
        // Gérer les erreurs
        header('Content-Type: application/json');
        echo json_encode([
            "success" => false, 
            "message" => $e->getMessage()
        ]);
    }
    
} else {
    // Accès direct au script sans soumission de formulaire
    header('Content-Type: application/json');
    echo json_encode(["success" => false, "message" => "Méthode non autorisée"]);
}
