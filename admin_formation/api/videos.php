<?php
// api/videos.php
header('Content-Type: application/json');
session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

require_once __DIR__ . '/../db.php';

try {
    // Connexion à la base de données
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Échec de la connexion: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8");
    
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get':
            if (!isset($_GET['id'])) {
                throw new Exception("ID de vidéo manquant");
            }
            
            $video_id = intval($_GET['id']);
            $query = "SELECT fv.*, fm.formation_id 
                     FROM formation_videos fv 
                     JOIN formation_modules fm ON fv.module_id = fm.id 
                     WHERE fv.id = ?";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $video_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $video = $result->fetch_assoc();
            
            if (!$video) {
                throw new Exception("Vidéo non trouvée");
            }
            
            echo json_encode([
                'success' => true, 
                'video' => $video
            ]);
            break;
            
        case 'list':
            if (!isset($_GET['module_id'])) {
                throw new Exception("ID de module manquant");
            }
            
            $module_id = intval($_GET['module_id']);
            $query = "SELECT * FROM formation_videos WHERE module_id = ? ORDER BY order_number ASC";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $module_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $videos = [];
            while ($row = $result->fetch_assoc()) {
                $videos[] = $row;
            }
            
            echo json_encode([
                'success' => true, 
                'videos' => $videos
            ]);
            break;
            
        default:
            throw new Exception("Action non supportée");
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>