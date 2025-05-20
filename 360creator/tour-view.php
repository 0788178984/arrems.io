<?php
// Database connection
$db_host = 'localhost';
$db_name = 'arrems_realestate_db';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Get tour slug from URL
$tourSlug = isset($_GET['tour']) ? $_GET['tour'] : null;

if (!$tourSlug) {
    die('Tour not found');
}

try {
    // Get tour data from database
    $stmt = $pdo->prepare("SELECT 
                            p.id as property_id,
                            p.title,
                            p.description,
                            pm.id as media_id,
                            pm.file_path,
                            pm.title as media_title,
                            pm.description as media_description,
                            pm.view_url
                        FROM property_media pm
                        JOIN properties p ON p.id = pm.property_id
                        WHERE pm.media_type = '3d_model'
                        AND pm.view_url LIKE :tourSlug
                        ORDER BY JSON_EXTRACT(pm.description, '$.sceneIndex')");

    $stmt->execute(['tourSlug' => '%' . $tourSlug . '%']);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($results)) {
        die('Tour not found');
    }

    // Extract tour data
    $tourData = [
        'title' => $results[0]['title'],
        'description' => $results[0]['description'],
        'scenes' => array_map(function($item) {
            $sceneData = json_decode($item['media_description'], true);
            return [
                'id' => $item['media_id'],
                'imageUrl' => $item['file_path'],
                'title' => $item['media_title'],
                'initialViewParameters' => $sceneData['initialViewParameters'],
                'hotspots' => $sceneData['hotspots'] ?? []
            ];
        }, $results)
    ];

    // Update view count
    $updateStmt = $pdo->prepare("UPDATE property_analytics 
                                SET views_count = views_count + 1,
                                    ar_views_count = ar_views_count + 1,
                                    last_viewed_at = NOW()
                                WHERE property_id = ?");
    $updateStmt->execute([$results[0]['property_id']]);

} catch(PDOException $e) {
    die('Error loading tour: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tourData['title']) ?> - Virtual Tour</title>
    <script src="https://cdn.jsdelivr.net/npm/marzipano@0.10.2/dist/marzipano.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        #pano { width: 100vw; height: 100vh; }
        .tour-info {
            position: fixed;
            top: 20px;
            left: 20px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 15px;
            border-radius: 8px;
            max-width: 300px;
            z-index: 100;
        }
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
        .hotspot-tooltip {
            position: absolute;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 14px;
            white-space: nowrap;
            pointer-events: none;
            transform: translateY(-100%);
            margin-top: -10px;
        }
    </style>
</head>
<body>
    <div class="tour-info">
        <h2><?= htmlspecialchars($tourData['title']) ?></h2>
        <p><?= htmlspecialchars($tourData['description']) ?></p>
    </div>
    <div id="pano"></div>
    <div class="tour-controls">
        <button class="tour-button" id="prev-scene" title="Previous Scene" style="display: none;">
            <i class="fas fa-chevron-left"></i>
        </button>
        <button class="tour-button" id="autorotate-btn" title="Toggle Autorotate">
            <i class="fas fa-sync-alt"></i>
        </button>
        <button class="tour-button" id="fullscreen-btn" title="Toggle Fullscreen">
            <i class="fas fa-expand"></i>
        </button>
        <button class="tour-button" id="next-scene" title="Next Scene" style="display: none;">
            <i class="fas fa-chevron-right"></i>
        </button>
    </div>
    <script>
        // Tour data
        const tourData = <?= json_encode($tourData) ?>;
        
        // Initialize viewer
        const viewer = new Marzipano.Viewer(document.getElementById('pano'));
        let currentSceneIndex = 0;
        
        // Create scenes
        const scenes = tourData.scenes.map((sceneData, index) => {
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
                const element = document.createElement('div');
                element.className = 'hotspot';
                
                const icon = document.createElement('i');
                icon.className = getHotspotIcon(hotspot.type);
                element.appendChild(icon);
                
                const tooltip = document.createElement('div');
                tooltip.className = 'hotspot-tooltip';
                tooltip.textContent = hotspot.title || getDefaultTooltip(hotspot);
                tooltip.style.display = 'none';
                element.appendChild(tooltip);
                
                element.addEventListener('mouseenter', () => tooltip.style.display = 'block');
                element.addEventListener('mouseleave', () => tooltip.style.display = 'none');
                
                scene.hotspotContainer().createHotspot(element, hotspot.position);
                
                // Add hotspot behavior
                if (hotspot.type === 'link') {
                    element.onclick = () => {
                        const targetScene = scenes[hotspot.targetScene];
                        if (targetScene) {
                            switchScene(hotspot.targetScene);
                        }
                    };
                }
            });
            
            return { scene, view, data: sceneData };
        });

        // Show first scene
        scenes[0].scene.switchTo();
        updateSceneControls();
        
        // Setup controls
        const autorotate = Marzipano.autorotate({
            yawSpeed: 0.03,
            targetPitch: 0,
            targetFov: Math.PI/2
        });
        
        document.getElementById('autorotate-btn').onclick = () => {
            if (viewer.isAutorotating()) {
                viewer.stopMovement();
                viewer.setIdleMovement(null);
            } else {
                viewer.startMovement(autorotate);
                viewer.setIdleMovement(3000, autorotate);
            }
        };
        
        document.getElementById('fullscreen-btn').onclick = () => {
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else {
                document.documentElement.requestFullscreen();
            }
        };

        // Scene navigation
        if (scenes.length > 1) {
            const prevBtn = document.getElementById('prev-scene');
            const nextBtn = document.getElementById('next-scene');
            
            prevBtn.style.display = 'block';
            nextBtn.style.display = 'block';
            
            prevBtn.onclick = () => switchScene(currentSceneIndex - 1);
            nextBtn.onclick = () => switchScene(currentSceneIndex + 1);
        }

        function switchScene(index) {
            if (index >= 0 && index < scenes.length) {
                currentSceneIndex = index;
                scenes[index].scene.switchTo();
                updateSceneControls();
            }
        }

        function updateSceneControls() {
            const prevBtn = document.getElementById('prev-scene');
            const nextBtn = document.getElementById('next-scene');
            
            if (prevBtn && nextBtn) {
                prevBtn.disabled = currentSceneIndex === 0;
                nextBtn.disabled = currentSceneIndex === scenes.length - 1;
            }
        }
        
        function getHotspotIcon(type) {
            switch(type) {
                case 'info': return 'fas fa-info';
                case 'link': return 'fas fa-link';
                case 'url': return 'fas fa-external-link-alt';
                case 'media': return 'fas fa-photo-video';
                default: return 'fas fa-circle';
            }
        }

        function getDefaultTooltip(hotspot) {
            switch(hotspot.type) {
                case 'info': return 'Information';
                case 'link': return 'Go to next scene';
                case 'url': return 'External link';
                case 'media': return 'View media';
                default: return '';
            }
        }
    </script>
</body>
</html> 