<?php
// Initialisation de la session
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    // Sauvegarder l'URL actuelle pour y revenir après la connexion
    $_SESSION['redirect_url'] = "watch.php" . 
        (isset($_GET['video_id']) ? "?video_id=" . $_GET['video_id'] : "") .
        (isset($_GET['formation_id']) ? "&formation_id=" . $_GET['formation_id'] : "");
    
    // Rediriger vers la page de connexion
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/db.php';

// Connexion à la base de données
$conn = new mysqli($servername, $username, $password, $dbname);

// Vérification de la connexion
if ($conn->connect_error) {
    die("Échec de la connexion: " . $conn->connect_error);
}
$conn->set_charset("utf8");

$user_id = $_SESSION['user_id'];
$video_id = isset($_GET['video_id']) ? intval($_GET['video_id']) : 0;
$formation_id = isset($_GET['formation_id']) ? intval($_GET['formation_id']) : 0;

// Si aucun ID de vidéo n'est fourni, rediriger
if ($video_id === 0) {
    header("Location: my-formations.php");
    exit;
}

// Récupérer les informations de la vidéo et vérifier les permissions
$video_query = "SELECT fv.*, fm.title as module_title, fm.formation_id, f.title as formation_title,
                f.price_per_month, c.name as category_name, c.icon as category_icon
                FROM formation_videos fv
                JOIN formation_modules fm ON fv.module_id = fm.id
                JOIN formations f ON fm.formation_id = f.id
                JOIN formation_categories c ON f.category_id = c.id
                WHERE fv.id = ?";
$stmt = $conn->prepare($video_query);
$stmt->bind_param("i", $video_id);
$stmt->execute();
$video_result = $stmt->get_result();

if ($video_result->num_rows === 0) {
    header("Location: my-formations.php?error=video_not_found");
    exit;
}

$video = $video_result->fetch_assoc();
$formation_id = $video['formation_id'];

// Vérifier si l'utilisateur est abonné à cette formation
$subscription_query = "SELECT * FROM formation_subscriptions 
                      WHERE user_id = ? AND formation_id = ? 
                      AND status = 'active' AND end_date >= CURDATE()";
$stmt = $conn->prepare($subscription_query);
$stmt->bind_param("ii", $user_id, $formation_id);
$stmt->execute();
$subscription_result = $stmt->get_result();

if ($subscription_result->num_rows === 0) {
    header("Location: formation-details.php?id=" . $formation_id . "&error=access_denied");
    exit;
}

$subscription = $subscription_result->fetch_assoc();

// Récupérer les informations de l'utilisateur
$user_query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

// Récupérer la progression de cette vidéo
$progress_query = "SELECT * FROM video_progress WHERE user_id = ? AND video_id = ?";
$stmt = $conn->prepare($progress_query);
$stmt->bind_param("ii", $user_id, $video_id);
$stmt->execute();
$progress_result = $stmt->get_result();

$video_progress = null;
if ($progress_result->num_rows > 0) {
    $video_progress = $progress_result->fetch_assoc();
}

// Récupérer tous les modules et vidéos de cette formation pour la playlist
$playlist_query = "SELECT fm.id as module_id, fm.title as module_title, fm.order_number as module_order,
                   fv.id as video_id, fv.title as video_title, fv.duration, fv.order_number as video_order,
                   COALESCE(vp.is_completed, 0) as is_completed,
                   COALESCE(vp.watched_seconds, 0) as watched_seconds
                   FROM formation_modules fm
                   LEFT JOIN formation_videos fv ON fm.id = fv.module_id
                   LEFT JOIN video_progress vp ON fv.id = vp.video_id AND vp.user_id = ?
                   WHERE fm.formation_id = ?
                   ORDER BY fm.order_number ASC, fv.order_number ASC";
$stmt = $conn->prepare($playlist_query);
$stmt->bind_param("ii", $user_id, $formation_id);
$stmt->execute();
$playlist_result = $stmt->get_result();

$playlist = [];
$current_module = null;

while ($row = $playlist_result->fetch_assoc()) {
    if ($current_module === null || $current_module['id'] !== $row['module_id']) {
        if ($current_module !== null) {
            $playlist[] = $current_module;
        }
        $current_module = [
            'id' => $row['module_id'],
            'title' => $row['module_title'],
            'order' => $row['module_order'],
            'videos' => []
        ];
    }
    
    if ($row['video_id']) {
        $current_module['videos'][] = [
            'id' => $row['video_id'],
            'title' => $row['video_title'],
            'duration' => $row['duration'],
            'order' => $row['video_order'],
            'is_completed' => $row['is_completed'],
            'watched_seconds' => $row['watched_seconds']
        ];
    }
}

if ($current_module !== null) {
    $playlist[] = $current_module;
}

// Trouver les vidéos précédente et suivante
$prev_video = null;
$next_video = null;
$found_current = false;

foreach ($playlist as $module) {
    foreach ($module['videos'] as $v) {
        if ($found_current) {
            $next_video = $v;
            break 2;
        }
        
        if ($v['id'] == $video_id) {
            $found_current = true;
        } else if (!$found_current) {
            $prev_video = $v;
        }
    }
}

// Traitement AJAX pour sauvegarder la progression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'update_progress') {
        $watched_seconds = intval($_POST['watched_seconds']);
        $is_completed = isset($_POST['is_completed']) ? 1 : 0;
        
        // Mettre à jour ou insérer la progression
        $update_query = "INSERT INTO video_progress (user_id, video_id, watched_seconds, is_completed, last_watched)
                        VALUES (?, ?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE 
                        watched_seconds = VALUES(watched_seconds),
                        is_completed = VALUES(is_completed),
                        last_watched = NOW()";
        
        $stmt = $conn->prepare($update_query);
        $stmt->bind_param("iiii", $user_id, $video_id, $watched_seconds, $is_completed);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
        exit;
    }
}

$conn->close();
?>

<?php
    $curLang    = $GLOBALS['nc_lang'] ?? 'fr';
    $switchLang  = $curLang === 'fr' ? 'en' : 'fr';
    $switchLabel = $curLang === 'fr' ? 'EN' : 'FR';
    $switchUrl   = strtok($_SERVER['REQUEST_URI'],'?').'?'.http_build_query(array_merge($_GET,['lang'=>$switchLang]));
?>
<!DOCTYPE html>
<html lang="<?= $curLang ?>" class="scroll">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($video['title']); ?> - <?php echo htmlspecialchars($video['formation_title']); ?> - Netcrafter</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Video.js CSS -->
    <link href="https://vjs.zencdn.net/8.6.1/video-js.css" rel="stylesheet">
    <!-- Custom styles -->
    <style>
        html {
            scroll-behavior: smooth;
            overflow-x: hidden;
        }
        
        body {
            width: 100%;
            max-width: 100vw;
            overflow-x: hidden;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        /* Video player customization */
        .video-js {
            width: 100%;
            height: 100%;
        }
        
        .vjs-netblue-theme {
            --vjs-theme-forest: #3B82F6;
            --vjs-theme-sea: #1E40AF;
        }
        
        .vjs-netblue-theme .vjs-big-play-button {
            background-color: rgba(59, 130, 246, 0.8);
            border-color: #3B82F6;
            color: white;
        }
        
        .vjs-netblue-theme .vjs-control-bar {
            background: linear-gradient(180deg, transparent, rgba(0,0,0,0.7));
        }
        
        .vjs-netblue-theme .vjs-volume-level,
        .vjs-netblue-theme .vjs-play-progress {
            background-color: #3B82F6;
        }
        
        .vjs-netblue-theme .vjs-slider:hover .vjs-volume-level,
        .vjs-netblue-theme .vjs-slider:hover .vjs-play-progress {
            background-color: #1E40AF;
        }
        
        /* Player container */
        .player-container {
            position: relative;
            width: 100%;
            height: 0;
            padding-bottom: 56.25%; /* 16:9 aspect ratio */
            background: #000;
        }
        
        .player-container .video-js {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        
        /* Playlist styles */
        .playlist-item {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .playlist-item:hover {
            background-color: rgba(59, 130, 246, 0.1);
        }
        
        .playlist-item.active {
            background-color: rgba(59, 130, 246, 0.2);
            border-left: 4px solid #3B82F6;
        }
        
        .playlist-item.completed {
            background-color: rgba(34, 197, 94, 0.1);
        }
        
        /* Module collapse */
        .module-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        
        .module-content.expanded {
            max-height: 1000px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .playlist-sidebar {
                display: none;
            }
            
            .playlist-sidebar.mobile-open {
                display: block;
                position: fixed;
                top: 0;
                right: 0;
                width: 300px;
                height: 100vh;
                background: white;
                z-index: 50;
                overflow-y: auto;
            }
        }
        
        /* Notes textarea */
        .notes-textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        /* Progress bar */
        .progress-ring {
            transition: stroke-dasharray 0.3s ease;
        }
    </style>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        netblue: {
                            100: '#E6F2FF',
                            200: '#B8D4FF',
                            300: '#8AB6FF',
                            400: '#5C98FF',
                            500: '#3B82F6',
                            600: '#1A6BE2',
                            700: '#0055CC',
                            800: '#003F99',
                            900: '#002966'
                        }
                    }
                }
            }
        }
    </script>
    <?php include __DIR__ . '/nc-theme.php'; ?>
</head>
<body style="background:linear-gradient(180deg,#060d1e 0%,#030810 100%)">
    <!-- Navigation Bar -->
    <nav class="sticky top-0 z-40" style="background:rgba(6,13,30,0.96);border-bottom:1px solid rgba(0,200,255,0.1);backdrop-filter:blur(20px)">
        <div class="px-4 py-3">
            <div class="flex items-center justify-between">
                <!-- Left: Back button and formation info -->
                <div class="flex items-center">
                    <a href="my-formations.php" class="hover:text-nc-cyan mr-4 transition-colors" style="color:#94a3b8">
                        <i class="fas fa-arrow-left text-xl"></i>
                    </a>
                    <div class="hidden sm:block">
                        <h1 class="font-bold text-gray-800 dark:text-white"><?php echo htmlspecialchars($video['formation_title']); ?></h1>
                        <p class="text-sm text-gray-600 dark:text-gray-400"><?php echo htmlspecialchars($video['module_title']); ?></p>
                    </div>
                </div>
                
                <!-- Center: Video title (mobile) -->
                <div class="sm:hidden flex-1 mx-4">
                    <h1 class="font-bold text-gray-800 dark:text-white truncate"><?php echo htmlspecialchars($video['title']); ?></h1>
                </div>
                
                <!-- Right: Controls -->
                <div class="flex items-center space-x-2">
                    <!-- Mobile playlist toggle -->
                    <button id="mobile-playlist-toggle" class="sm:hidden hover:text-nc-cyan transition-colors" style="color:#94a3b8">
                        <i class="fas fa-list text-xl"></i>
                    </button>

                    <!-- Notes toggle -->
                    <button id="notes-toggle" class="hidden sm:inline-flex items-center hover:text-nc-cyan px-3 py-2 rounded-lg transition-colors" style="color:#94a3b8">
                        <i class="fas fa-sticky-note mr-2"></i>Notes
                    </button>

                    <!-- Progress indicator -->
                    <div class="hidden sm:flex items-center text-sm" style="color:#94a3b8">
                        <div class="w-8 h-8 mr-2">
                            <svg class="w-8 h-8 transform -rotate-90">
                                <circle cx="16" cy="16" r="14" stroke="currentColor" stroke-width="2" fill="none" class="opacity-25"></circle>
                                <circle cx="16" cy="16" r="14" stroke="#3B82F6" stroke-width="2" fill="none" 
                                        stroke-dasharray="87.96" stroke-dashoffset="<?php echo 87.96 - (87.96 * (($video_progress['watched_seconds'] ?? 0) / (60 * 25))); ?>" 
                                        class="progress-ring"></circle>
                            </svg>
                        </div>
                        <span><?php echo $video_progress ? round(($video_progress['watched_seconds'] / (60 * 25)) * 100) : 0; ?>%</span>
                    </div>
                    
                    <!-- Language switcher -->
                    <a href="<?= htmlspecialchars($switchUrl) ?>" class="nc-lang-btn"><i class="fas fa-globe text-xs"></i><?= $switchLabel ?></a>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="flex">
        <!-- Video Player Area -->
        <div class="flex-1">
            <!-- Video Player -->
            <div class="bg-black">
                <div class="player-container">
                    <video-js
                        id="video-player"
                        class="vjs-netblue-theme"
                        controls
                        preload="auto"
                        data-setup="{}"
                        poster="">
                        <source src="../<?php echo htmlspecialchars($video['video_url']); ?>" type="video/mp4">
                        <p class="vjs-no-js">
                            Pour regarder cette vidéo, veuillez activer JavaScript et envisager de mettre à jour votre navigateur vers une
                            <a href="https://videojs.com/html5-video-support/" target="_blank">version qui prend en charge la vidéo HTML5</a>.
                        </p>
                    </video-js>
                </div>
            </div>
            
            <!-- Video Info & Controls -->
            <div class="bg-white dark:bg-gray-800 p-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center">
                    <div class="flex-1">
                        <h2 class="text-xl font-bold dark:text-white mb-2"><?php echo htmlspecialchars($video['title']); ?></h2>
                        <div class="flex flex-wrap items-center text-sm text-gray-600 dark:text-gray-400 space-x-4">
                            <span><i class="fas fa-play-circle mr-1"></i><?php echo htmlspecialchars($video['duration']); ?></span>
                            <span><i class="fas fa-folder mr-1"></i><?php echo htmlspecialchars($video['module_title']); ?></span>
                        </div>
                    </div>
                    
                    <!-- Video Actions -->
                    <div class="flex items-center space-x-2 mt-4 sm:mt-0">
                        <?php if ($prev_video): ?>
                        <a href="watch.php?video_id=<?php echo $prev_video['id']; ?>&formation_id=<?php echo $formation_id; ?>" class="bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-white px-3 py-2 rounded-lg transition-colors">
                            <i class="fas fa-step-backward mr-1"></i>Précédent
                        </a>
                        <?php endif; ?>
                        
                        <button id="mark-complete" class="bg-green-600 hover:bg-green-700 text-white px-3 py-2 rounded-lg transition-colors <?php echo $video_progress && $video_progress['is_completed'] ? '' : 'hidden'; ?>">
                            <i class="fas fa-check mr-1"></i>Terminé
                        </button>
                        
                        <?php if ($next_video): ?>
                        <a href="watch.php?video_id=<?php echo $next_video['id']; ?>&formation_id=<?php echo $formation_id; ?>" class="bg-netblue-600 hover:bg-netblue-700 text-white px-3 py-2 rounded-lg transition-colors">
                            Suivant<i class="fas fa-step-forward ml-1"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Video Description -->
            <?php if (!empty($video['description'])): ?>
            <div class="bg-white dark:bg-gray-800 p-4">
                <h3 class="font-bold mb-2 dark:text-white">Description</h3>
                <p class="text-gray-600 dark:text-gray-400 leading-relaxed">
                    <?php echo nl2br(htmlspecialchars($video['description'])); ?>
                </p>
            </div>
            <?php endif; ?>
            
            <!-- Notes Section (Hidden by default on mobile) -->
            <div id="notes-section" class="bg-white dark:bg-gray-800 p-4 border-t border-gray-200 dark:border-gray-700 hidden">
                <h3 class="font-bold mb-3 dark:text-white">
                    <i class="fas fa-sticky-note mr-2 text-netblue-600 dark:text-netblue-400"></i>Mes notes
                </h3>
                <textarea id="video-notes" class="notes-textarea w-full p-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:outline-none focus:ring-2 focus:ring-netblue-500" placeholder="<?= t('form.notes_hint') ?>"></textarea>
                <div class="flex justify-between items-center mt-2 gap-2 flex-wrap">
                    <button onclick="exportNotes()" class="text-sm px-3 py-2 rounded-lg border transition-colors"
                            style="background:rgba(0,200,255,0.06);border-color:rgba(0,200,255,0.2);color:#00c8ff">
                        <i class="fas fa-download mr-1"></i>Exporter (.txt)
                    </button>
                    <button id="save-notes" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded-lg text-sm transition-colors">
                        <i class="fas fa-save mr-1"></i>Sauvegarder
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Playlist Sidebar -->
        <div id="playlist-sidebar" class="playlist-sidebar w-80 bg-white dark:bg-gray-800 border-l border-gray-200 dark:border-gray-700 h-screen overflow-y-auto">
            <!-- Sidebar Header -->
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <h3 class="font-bold dark:text-white">Contenu du cours</h3>
                    <button id="close-mobile-playlist" class="sm:hidden text-gray-600 dark:text-gray-400">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    <?php 
                    $total_videos = 0;
                    $completed_videos = 0;
                    foreach ($playlist as $module) {
                        foreach ($module['videos'] as $v) {
                            $total_videos++;
                            if ($v['is_completed']) $completed_videos++;
                        }
                    }
                    echo "$completed_videos sur $total_videos vidéos terminées";
                    ?>
                </p>
            </div>
            
            <!-- Playlist Content -->
            <div class="p-2">
                <?php foreach ($playlist as $module): ?>
                <div class="mb-2">
                    <!-- Module Header -->
                    <button class="module-toggle w-full flex items-center justify-between p-3 text-left bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors" data-module="<?php echo $module['id']; ?>">
                        <div class="flex items-center">
                            <i class="fas fa-folder mr-3 text-netblue-600 dark:text-netblue-400"></i>
                            <div>
                                <h4 class="font-medium dark:text-white"><?php echo htmlspecialchars($module['title']); ?></h4>
                                <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo count($module['videos']); ?> vidéos</p>
                            </div>
                        </div>
                        <i class="fas fa-chevron-down transform transition-transform" data-icon="<?php echo $module['id']; ?>"></i>
                    </button>
                    
                    <!-- Module Videos -->
                    <div class="module-content expanded" data-content="<?php echo $module['id']; ?>">
                        <div class="ml-4 mt-2 space-y-1">
                            <?php foreach ($module['videos'] as $v): ?>
                            <div class="playlist-item p-3 rounded-lg flex items-center <?php echo $v['id'] == $video_id ? 'active' : ''; ?> <?php echo $v['is_completed'] ? 'completed' : ''; ?>"
                                 onclick="window.location.href='watch.php?video_id=<?php echo $v['id']; ?>&formation_id=<?php echo $formation_id; ?>'">
                                <div class="flex-shrink-0 mr-3">
                                    <?php if ($v['is_completed']): ?>
                                    <div class="w-6 h-6 bg-green-500 rounded-full flex items-center justify-center">
                                        <i class="fas fa-check text-white text-xs"></i>
                                    </div>
                                    <?php elseif ($v['id'] == $video_id): ?>
                                    <div class="w-6 h-6 bg-netblue-500 rounded-full flex items-center justify-center">
                                        <i class="fas fa-play text-white text-xs"></i>
                                    </div>
                                    <?php else: ?>
                                    <div class="w-6 h-6 bg-gray-300 dark:bg-gray-600 rounded-full flex items-center justify-center">
                                        <i class="fas fa-play text-gray-600 dark:text-gray-400 text-xs"></i>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium dark:text-white truncate"><?php echo htmlspecialchars($v['title']); ?></p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo htmlspecialchars($v['duration']); ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Mobile Playlist Overlay -->
    <div id="mobile-playlist-overlay" class="fixed inset-0 bg-black bg-opacity-50 z-40 hidden sm:hidden"></div>

    <!-- Video.js JavaScript -->
    <script src="https://vjs.zencdn.net/8.6.1/video.js"></script>
    
    <!-- Main JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize video player
            const player = videojs('video-player', {
                fluid: true,
                responsive: true,
                playbackRates: [0.5, 1, 1.25, 1.5, 2],
                plugins: {
                    hotkeys: {
                        volumeStep: 0.1,
                        seekStep: 5,
                        enableModifiersForNumbers: false
                    }
                }
            });
            
            // Video progress tracking
            let lastSavedTime = 0;
            const saveInterval = 5; // Save every 5 seconds
            const videoDuration = <?php echo !empty($video['duration']) ? (int)filter_var($video['duration'], FILTER_SANITIZE_NUMBER_INT) * 60 : 1500; ?>; // Convert minutes to seconds
            
            // Set initial playback position
            <?php if ($video_progress && $video_progress['watched_seconds'] > 0): ?>
            player.ready(() => {
                player.currentTime(<?php echo $video_progress['watched_seconds']; ?>);
            });
            <?php endif; ?>
            
            // Track video progress
            player.on('timeupdate', function() {
                const currentTime = Math.floor(player.currentTime());
                
                // Save progress every 5 seconds
                if (currentTime - lastSavedTime >= saveInterval) {
                    updateProgress(currentTime);
                    lastSavedTime = currentTime;
                }
                
                // Update progress ring
                updateProgressRing(currentTime);
                
                // Auto-mark as complete when 90% watched
                if (!player.hasClass('completed') && currentTime >= videoDuration * 0.9) {
                    markVideoComplete();
                }
            });
            
            // Save progress when video ends
            player.on('ended', function() {
                updateProgress(Math.floor(player.currentTime()), true);
                markVideoComplete();
            });
            
            // Save progress when leaving page
            window.addEventListener('beforeunload', function() {
                if (player && !player.isDisposed()) {
                    updateProgress(Math.floor(player.currentTime()));
                }
            });
            
            // Function to update progress
            function updateProgress(watchedSeconds, isCompleted = false) {
                fetch('watch.php?video_id=<?php echo $video_id; ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        'action': 'update_progress',
                        'watched_seconds': watchedSeconds,
                        'is_completed': isCompleted ? '1' : '0'
                    })
                }).catch(error => {
                    console.error('Error updating progress:', error);
                });
            }
            
            // Function to update progress ring
            function updateProgressRing(currentTime) {
                const progressRing = document.querySelector('.progress-ring');
                if (progressRing) {
                    const progress = Math.min(currentTime / videoDuration, 1);
                    const circumference = 87.96;
                    const offset = circumference - (progress * circumference);
                    progressRing.style.strokeDashoffset = offset;
                    
                    // Update percentage text
                    const percentText = progressRing.parentElement.nextElementSibling;
                    if (percentText) {
                        percentText.textContent = Math.round(progress * 100) + '%';
                    }
                }
            }
            
            // Function to mark video as complete
            function markVideoComplete() {
                const markCompleteBtn = document.getElementById('mark-complete');
                if (markCompleteBtn) {
                    markCompleteBtn.classList.remove('hidden');
                    markCompleteBtn.classList.add('bg-green-600');
                }
                
                // Update playlist item
                const currentPlaylistItem = document.querySelector('.playlist-item.active');
                if (currentPlaylistItem && !currentPlaylistItem.classList.contains('completed')) {
                    currentPlaylistItem.classList.add('completed');
                    const icon = currentPlaylistItem.querySelector('.flex-shrink-0 div');
                    if (icon) {
                        icon.className = 'w-6 h-6 bg-green-500 rounded-full flex items-center justify-center';
                        icon.innerHTML = '<i class="fas fa-check text-white text-xs"></i>';
                    }
                }
            }
            
            // Mark complete button
            document.getElementById('mark-complete').addEventListener('click', function() {
                updateProgress(Math.floor(player.currentTime()), true);
                markVideoComplete();
            });
            
            // Module toggle functionality
            document.querySelectorAll('.module-toggle').forEach(toggle => {
                toggle.addEventListener('click', function() {
                    const moduleId = this.getAttribute('data-module');
                    const content = document.querySelector(`[data-content="${moduleId}"]`);
                    const icon = document.querySelector(`[data-icon="${moduleId}"]`);
                    
                    if (content.classList.contains('expanded')) {
                        content.classList.remove('expanded');
                        icon.classList.add('rotate-180');
                    } else {
                        content.classList.add('expanded');
                        icon.classList.remove('rotate-180');
                    }
                });
            });
            
            // Mobile playlist toggle
            document.getElementById('mobile-playlist-toggle').addEventListener('click', function() {
                const sidebar = document.getElementById('playlist-sidebar');
                const overlay = document.getElementById('mobile-playlist-overlay');
                
                sidebar.classList.add('mobile-open');
                overlay.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            });
            
            // Close mobile playlist
            document.getElementById('close-mobile-playlist').addEventListener('click', closeMobilePlaylist);
            document.getElementById('mobile-playlist-overlay').addEventListener('click', closeMobilePlaylist);
            
            function closeMobilePlaylist() {
                const sidebar = document.getElementById('playlist-sidebar');
                const overlay = document.getElementById('mobile-playlist-overlay');
                
                sidebar.classList.remove('mobile-open');
                overlay.classList.add('hidden');
                document.body.style.overflow = '';
            }
            
            // Notes functionality
            const notesToggle = document.getElementById('notes-toggle');
            const notesSection = document.getElementById('notes-section');
            const notesTextarea = document.getElementById('video-notes');
            const saveNotesBtn = document.getElementById('save-notes');
            
            // Load saved notes
            const savedNotes = localStorage.getItem(`notes_video_<?php echo $video_id; ?>`);
            if (savedNotes) {
                notesTextarea.value = savedNotes;
            }
            
            // Toggle notes section
            notesToggle.addEventListener('click', function() {
                if (notesSection.classList.contains('hidden')) {
                    notesSection.classList.remove('hidden');
                    notesToggle.classList.add('bg-netblue-100', 'dark:bg-netblue-900', 'text-netblue-600', 'dark:text-netblue-400');
                    notesTextarea.focus();
                } else {
                    notesSection.classList.add('hidden');
                    notesToggle.classList.remove('bg-netblue-100', 'dark:bg-netblue-900', 'text-netblue-600', 'dark:text-netblue-400');
                }
            });
            
            // Save notes
            saveNotesBtn.addEventListener('click', function() {
                const notes = notesTextarea.value;
                localStorage.setItem(`notes_video_<?php echo $video_id; ?>`, notes);
                
                // Show success feedback
                const originalText = saveNotesBtn.innerHTML;
                saveNotesBtn.innerHTML = '<i class="fas fa-check mr-1"></i>Sauvegardé';
                saveNotesBtn.classList.remove('bg-netblue-600', 'hover:bg-netblue-700');
                saveNotesBtn.classList.add('bg-green-600');
                
                setTimeout(() => {
                    saveNotesBtn.innerHTML = originalText;
                    saveNotesBtn.classList.remove('bg-green-600');
                    saveNotesBtn.classList.add('bg-netblue-600', 'hover:bg-netblue-700');
                }, 2000);
            });
            
            // Auto-save notes
            let notesTimeout;
            notesTextarea.addEventListener('input', function() {
                clearTimeout(notesTimeout);
                notesTimeout = setTimeout(() => {
                    localStorage.setItem(`notes_video_<?php echo $video_id; ?>`, notesTextarea.value);
                }, 2000);
            });
            
            // Dark mode toggle
            const darkModeToggle = document.getElementById('darkModeToggle');
            const htmlElement = document.documentElement;
            
            // Check for saved theme preference
            if (localStorage.getItem('darkMode') === 'enabled') {
                htmlElement.classList.add('dark');
                darkModeToggle.checked = true;
            }
            
            // Function to toggle dark mode
            function toggleDarkMode() {
                if (htmlElement.classList.contains('dark')) {
                    htmlElement.classList.remove('dark');
                    localStorage.setItem('darkMode', 'disabled');
                    darkModeToggle.checked = false;
                } else {
                    htmlElement.classList.add('dark');
                    localStorage.setItem('darkMode', 'enabled');
                    darkModeToggle.checked = true;
                }
            }
            
            // Event listener for toggle switch
            darkModeToggle.addEventListener('change', toggleDarkMode);
            
            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Only handle shortcuts when not typing in textarea
                if (e.target.tagName.toLowerCase() === 'textarea' || e.target.tagName.toLowerCase() === 'input') {
                    return;
                }
                
                switch(e.code) {
                    case 'Space':
                        e.preventDefault();
                        if (player.paused()) {
                            player.play();
                        } else {
                            player.pause();
                        }
                        break;
                    case 'ArrowLeft':
                        e.preventDefault();
                        player.currentTime(Math.max(0, player.currentTime() - 10));
                        break;
                    case 'ArrowRight':
                        e.preventDefault();
                        player.currentTime(Math.min(player.duration(), player.currentTime() + 10));
                        break;
                    case 'KeyF':
                        e.preventDefault();
                        if (player.isFullscreen()) {
                            player.exitFullscreen();
                        } else {
                            player.requestFullscreen();
                        }
                        break;
                    case 'KeyM':
                        e.preventDefault();
                        player.muted(!player.muted());
                        break;
                    case 'KeyN':
                        e.preventDefault();
                        notesToggle.click();
                        break;
                }
            });
            
            // Handle video errors
            player.on('error', function() {
                const error = player.error();
                console.error('Video error:', error);
                
                // Show user-friendly error message
                const errorDiv = document.createElement('div');
                errorDiv.className = 'absolute inset-0 bg-black bg-opacity-75 flex items-center justify-center text-white p-4';
                errorDiv.innerHTML = `
                    <div class="text-center">
                        <i class="fas fa-exclamation-triangle text-4xl mb-4 text-yellow-500"></i>
                        <h3 class="text-xl font-bold mb-2">Erreur de lecture</h3>
                        <p class="mb-4">Une erreur est survenue lors du chargement de la vidéo.</p>
                        <button onclick="location.reload()" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded-lg transition-colors">
                            Recharger la page
                        </button>
                    </div>
                `;
                
                player.el().appendChild(errorDiv);
            });
            
            // Auto-advance to next video (optional)
            player.on('ended', function() {
                <?php if ($next_video): ?>
                // Show next video overlay
                const nextVideoOverlay = document.createElement('div');
                nextVideoOverlay.className = 'absolute inset-0 bg-black bg-opacity-75 flex items-center justify-center text-white p-4';
                nextVideoOverlay.innerHTML = `
                    <div class="text-center">
                        <h3 class="text-xl font-bold mb-2">Vidéo suivante</h3>
                        <p class="mb-4"><?php echo htmlspecialchars($next_video['title']); ?></p>
                        <div class="space-x-4">
                            <button onclick="this.parentElement.parentElement.parentElement.remove()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition-colors">
                                Rester ici
                            </button>
                            <a href="watch.php?video_id=<?php echo $next_video['id']; ?>&formation_id=<?php echo $formation_id; ?>" class="bg-netblue-600 hover:bg-netblue-700 text-white px-4 py-2 rounded-lg transition-colors inline-block">
                                Continuer
                            </a>
                        </div>
                    </div>
                `;
                
                player.el().appendChild(nextVideoOverlay);
                
                // Auto-advance after 10 seconds
                setTimeout(() => {
                    if (nextVideoOverlay.parentElement) {
                        window.location.href = 'watch.php?video_id=<?php echo $next_video['id']; ?>&formation_id=<?php echo $formation_id; ?>';
                    }
                }, 10000);
                <?php endif; ?>
            });
            
            // Picture-in-Picture support
            if ('pictureInPicture' in document) {
                const pipButton = document.createElement('button');
                pipButton.className = 'bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-white px-3 py-2 rounded-lg transition-colors text-sm';
                pipButton.innerHTML = '<i class="fas fa-external-link-alt mr-1"></i>PiP';
                pipButton.title = 'Picture-in-Picture';
                
                pipButton.addEventListener('click', async () => {
                    try {
                        if (document.pictureInPictureElement) {
                            await document.exitPictureInPicture();
                        } else {
                            await player.el().querySelector('video').requestPictureInPicture();
                        }
                    } catch (error) {
                        console.error('Picture-in-Picture error:', error);
                    }
                });
                
                // Add to video controls
                const videoControls = document.querySelector('.flex.items-center.space-x-2');
                if (videoControls) {
                    videoControls.insertBefore(pipButton, videoControls.lastElementChild);
                }
            }
            
            // Speed control
            const speedControl = document.createElement('select');
            speedControl.className = 'bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-white px-2 py-1 rounded text-sm border-none';
            speedControl.innerHTML = `
                <option value="0.5">0.5x</option>
                <option value="0.75">0.75x</option>
                <option value="1" selected>1x</option>
                <option value="1.25">1.25x</option>
                <option value="1.5">1.5x</option>
                <option value="2">2x</option>
            `;
            
            speedControl.addEventListener('change', function() {
                player.playbackRate(parseFloat(this.value));
            });
            
            // Add speed control to video controls
            const videoControls = document.querySelector('.flex.items-center.space-x-2');
            if (videoControls) {
                videoControls.insertBefore(speedControl, videoControls.lastElementChild);
            }
            
            // Resize handler for responsive video
            function handleResize() {
                if (window.innerWidth < 768) {
                    // Mobile: hide sidebar by default
                    const sidebar = document.getElementById('playlist-sidebar');
                    if (sidebar && sidebar.classList.contains('mobile-open')) {
                        closeMobilePlaylist();
                    }
                }
            }
            
            window.addEventListener('resize', handleResize);
            
            // Initialize responsive behavior
            handleResize();
            
            // Focus management for accessibility
            player.on('play', function() {
                document.title = '▶ ' + '<?php echo htmlspecialchars($video['title']); ?>';
            });
            
            player.on('pause', function() {
                document.title = '⏸ ' + '<?php echo htmlspecialchars($video['title']); ?>';
            });
            
            // Initialize player state
            player.ready(function() {
                console.log('Video player ready');
                
                // Set initial volume from localStorage
                const savedVolume = localStorage.getItem('video_volume');
                if (savedVolume !== null) {
                    player.volume(parseFloat(savedVolume));
                }
                
                // Save volume changes
                player.on('volumechange', function() {
                    localStorage.setItem('video_volume', player.volume());
                });
            });
        });
        
        // Utility function to format time
        function formatTime(seconds) {
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = Math.floor(seconds % 60);
            return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
        }
        
        // Add timestamp to notes
        function addTimestamp() {
            const player = videojs('video-player');
            const currentTime = Math.floor(player.currentTime());
            const timestamp = `[${formatTime(currentTime)}] `;
            const notesTextarea = document.getElementById('video-notes');
            
            const cursorPos = notesTextarea.selectionStart;
            const textBefore = notesTextarea.value.substring(0, cursorPos);
            const textAfter = notesTextarea.value.substring(cursorPos);
            
            notesTextarea.value = textBefore + timestamp + textAfter;
            notesTextarea.selectionStart = notesTextarea.selectionEnd = cursorPos + timestamp.length;
            notesTextarea.focus();
        }
        
        // Export notes as .txt
        function exportNotes() {
            const notes = document.getElementById('video-notes')?.value || '';
            if (!notes.trim()) { alert('Aucune note à exporter.'); return; }
            const title = <?= json_encode($video['title'] ?? 'Video') ?>;
            const header = 'Notes — ' + title + '\n' + new Date().toLocaleDateString('fr-FR') + '\n' + '─'.repeat(40) + '\n\n';
            const blob = new Blob([header + notes], { type: 'text/plain;charset=utf-8' });
            const url  = URL.createObjectURL(blob);
            const a    = document.createElement('a');
            a.href     = url;
            a.download = 'notes-' + title.replace(/[^a-z0-9]/gi, '-').toLowerCase().substring(0, 40) + '.txt';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        // Add timestamp button to notes section
        document.addEventListener('DOMContentLoaded', function() {
            const saveNotesBtn = document.getElementById('save-notes');
            const timestampBtn = document.createElement('button');
            timestampBtn.type = 'button';
            timestampBtn.className = 'bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-800 dark:text-white px-3 py-2 rounded-lg text-sm transition-colors mr-2';
            timestampBtn.innerHTML = '<i class="fas fa-clock mr-1"></i>Horodatage';
            timestampBtn.onclick = addTimestamp;
            
            saveNotesBtn.parentElement.insertBefore(timestampBtn, saveNotesBtn);
        });
    </script>
</body>
</html>