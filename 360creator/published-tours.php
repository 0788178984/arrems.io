<?php
// Prevent any HTML output
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

// Function to get published tours
function getPublishedTours() {
    $toursFile = __DIR__ . '/data/published-tours.json';
    
    // Create data directory if it doesn't exist
    $dataDir = __DIR__ . '/data';
    if (!file_exists($dataDir)) {
        mkdir($dataDir, 0777, true);
    }
    
    // Create empty tours file if it doesn't exist
    if (!file_exists($toursFile)) {
        file_put_contents($toursFile, json_encode([]));
    }
    
    $content = file_get_contents($toursFile);
    if ($content === false) {
        error_log("Error reading tours file: " . error_get_last()['message']);
        return [];
    }
    
    $tours = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
        return [];
    }
    
    return $tours ?? [];
}

// Function to save published tours
function savePublishedTours($tours) {
    $toursDir = __DIR__ . '/data';
    if (!file_exists($toursDir)) {
        if (!mkdir($toursDir, 0777, true)) {
            throw new Exception("Failed to create data directory");
        }
    }
    
    $toursFile = $toursDir . '/published-tours.json';
    if (file_put_contents($toursFile, json_encode($tours, JSON_PRETTY_PRINT)) === false) {
        throw new Exception("Failed to write tours data");
    }
}

// Database connection
$db_host = 'localhost';
$db_name = 'arrems_realestate_db';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Return list of published tours
    $tours = getPublishedTours();
    echo json_encode($tours);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get POST data
        $input = file_get_contents('php://input');
        $tourData = json_decode($input, true);

        if (!$tourData) {
            throw new Exception('Invalid tour data');
        }

        // Start transaction
        $pdo->beginTransaction();

        // Generate URL
        $urlSlug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $tourData['title'])) . '-' . $tourData['id'];
        $viewUrl = "tour-view.php?tour=" . urlencode($urlSlug);

        // For each scene in the tour
        foreach ($tourData['scenes'] as $scene) {
            // First, insert the media record if it doesn't exist
            $insertStmt = $pdo->prepare("INSERT INTO property_media 
                (property_id, media_type, file_path, title, description, is_primary, created_at, status, view_url) 
                VALUES 
                (1, '3d_model', :filePath, :title, :description, 0, NOW(), 'published', :viewUrl)
                ON DUPLICATE KEY UPDATE 
                status = 'published',
                view_url = :viewUrl,
                updated_at = NOW()");

            $sceneData = [
                'filePath' => $scene['imageUrl'] ?? '',
                'title' => $tourData['title'],
                'description' => json_encode([
                    'tourId' => $tourData['id'],
                    'sceneId' => $scene['id'],
                    'initialViewParameters' => $scene['initialViewParameters'] ?? null,
                    'hotspots' => $scene['hotspots'] ?? []
                ]),
                'viewUrl' => $viewUrl
            ];

            $insertStmt->execute($sceneData);
        }

        // Create analytics entry
        $checkStmt = $pdo->prepare("SELECT 1 FROM property_analytics WHERE property_id = 1");
        $checkStmt->execute();
        
        if (!$checkStmt->fetch()) {
            $createAnalyticsStmt = $pdo->prepare("INSERT INTO property_analytics 
                (property_id, views_count, ar_views_count, created_at, updated_at) 
                VALUES (1, 0, 0, NOW(), NOW())");
            $createAnalyticsStmt->execute();
        }

        // Commit changes
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Tour published successfully',
            'viewUrl' => $viewUrl
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Method not allowed']);
}

// Helper function to generate URL slug
function generateUrlSlug($title) {
    // Convert to lowercase
    $slug = strtolower($title);
    
    // Replace non-alphanumeric characters with hyphens
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    
    // Remove multiple consecutive hyphens
    $slug = preg_replace('/-+/', '-', $slug);
    
    // Remove leading/trailing hyphens
    $slug = trim($slug, '-');
    
    return $slug;
}

// Helper function to generate viewer HTML
function generateViewerHtml($tourData, $urlSlug) {
    return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($tourData['title']) . ' - Virtual Tour</title>
    <script src="https://cdn.jsdelivr.net/npm/marzipano@0.10.2/dist/marzipano.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        #pano { width: 100vw; height: 100vh; }
        .tour-controls {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
            padding: 10px;
            background: rgba(0, 0, 0, 0.7);
            border-radius: 8px;
            z-index: 100;
        }
        .tour-button {
            background: transparent;
            border: none;
            color: white;
            padding: 8px;
            cursor: pointer;
            border-radius: 4px;
            transition: background 0.2s;
        }
        .tour-button:hover { background: rgba(255, 255, 255, 0.1); }
        .hotspot {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.5);
            border: 2px solid white;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: transform 0.3s;
        }
        .hotspot:hover { transform: scale(1.1); }
    </style>
</head>
<body>
    <div id="pano"></div>
    <div class="tour-controls">
        <button class="tour-button" id="autorotate-btn" title="Toggle Autorotate">
            <i class="fas fa-sync-alt"></i>
        </button>
        <button class="tour-button" id="fullscreen-btn" title="Toggle Fullscreen">
            <i class="fas fa-expand"></i>
        </button>
    </div>
    <script>
        // Tour data
        const tourData = ' . json_encode($tourData) . ';
        
        // Initialize viewer
        const viewer = new Marzipano.Viewer(document.getElementById("pano"));
        
        // Create scenes
        const scenes = tourData.scenes.map(sceneData => {
            const source = Marzipano.ImageUrlSource.fromUrl(sceneData.imageUrl);
            const geometry = new Marzipano.EquirectGeometry([{ width: 4000 }]);
            const view = new Marzipano.RectilinearView(sceneData.initialViewParameters);
            
            const scene = viewer.createScene({
                source: source,
                geometry: geometry,
                view: view,
                pinFirstLevel: true
            });

            // Add hotspots
            sceneData.hotspots?.forEach(hotspot => {
                const element = document.createElement("div");
                element.className = "hotspot";
                
                const icon = document.createElement("i");
                icon.className = getHotspotIcon(hotspot.type);
                element.appendChild(icon);
                
                scene.hotspotContainer().createHotspot(element, hotspot.position);
                
                // Add hotspot behavior
                if (hotspot.type === "link") {
                    element.onclick = () => {
                        const targetScene = scenes.find(s => s.id === hotspot.targetScene);
                        if (targetScene) targetScene.scene.switchTo();
                    };
                }
            });
            
            return { scene, view, data: sceneData };
        });

        // Show first scene
        scenes[0].scene.switchTo();
        
        // Setup controls
        const autorotate = Marzipano.autorotate({
            yawSpeed: 0.03,
            targetPitch: 0,
            targetFov: Math.PI/2
        });
        
        document.getElementById("autorotate-btn").onclick = () => {
            if (viewer.isAutorotating()) {
                viewer.stopMovement();
                viewer.setIdleMovement(null);
            } else {
                viewer.startMovement(autorotate);
                viewer.setIdleMovement(3000, autorotate);
            }
        };
        
        document.getElementById("fullscreen-btn").onclick = () => {
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else {
                document.documentElement.requestFullscreen();
            }
        };
        
        function getHotspotIcon(type) {
            switch(type) {
                case "info": return "fas fa-info";
                case "link": return "fas fa-link";
                case "url": return "fas fa-external-link-alt";
                case "media": return "fas fa-photo-video";
                default: return "fas fa-circle";
            }
        }
    </script>
</body>
</html>';
}
?> 