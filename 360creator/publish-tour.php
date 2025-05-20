<?php
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Get the POST data
    $tourData = json_decode(file_get_contents('php://input'), true);
    
    if (!$tourData) {
        throw new Exception('No tour data received');
    }

    // Validate required fields
    if (!isset($tourData['id']) || !isset($tourData['title'])) {
        throw new Exception('Missing required tour data');
    }

    // Create tours directory if it doesn't exist
    $toursDir = 'published-tours';
    if (!file_exists($toursDir)) {
        mkdir($toursDir, 0777, true);
    }

    // Create a directory for this tour
    $tourDir = $toursDir . '/' . $tourData['id'];
    if (!file_exists($tourDir)) {
        mkdir($tourDir, 0777, true);
    }

    // Create images directory for the tour
    $imagesDir = $tourDir . '/images';
    if (!file_exists($imagesDir)) {
        mkdir($imagesDir, 0777, true);
    }

    // Save tour data as JSON
    $jsonFile = $tourDir . '/tour.json';
    file_put_contents($jsonFile, json_encode($tourData, JSON_PRETTY_PRINT));

    // Save images
    foreach ($tourData['scenes'] as $scene) {
        if (isset($scene['imageUrl'])) {
            // Extract base64 data
            $imageData = $scene['imageUrl'];
            if (preg_match('/^data:image\/(\w+);base64,/', $imageData, $type)) {
                $imageData = substr($imageData, strpos($imageData, ',') + 1);
                $type = strtolower($type[1]); // jpg, png, etc.

                // Check if image data is valid base64
                if ($imageData !== false) {
                    $imageData = base64_decode($imageData);
                    if ($imageData !== false) {
                        $imagePath = $imagesDir . '/' . $scene['filename'];
                        file_put_contents($imagePath, $imageData);
                        
                        // Update image URL in tour data to relative path
                        $scene['imageUrl'] = 'images/' . $scene['filename'];
                    }
                }
            }
        }
    }

    // Generate the public HTML file
    $htmlContent = generatePublicHtml($tourData);
    file_put_contents($tourDir . '/index.html', $htmlContent);

    // Return success response with the public URL
    $publicUrl = 'published-tours/' . $tourData['id'] . '/index.html';
    echo json_encode([
        'status' => 'success',
        'message' => 'Tour published successfully',
        'url' => $publicUrl
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

function generatePublicHtml($tourData) {
    return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($tourData['title']) . ' - Virtual Tour</title>
    <script src="https://cdn.jsdelivr.net/npm/marzipano@0.10.2/dist/marzipano.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/screenfull@5.2.0/dist/screenfull.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; overflow: hidden; }
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
        .tour-button:hover {
            background: rgba(255, 255, 255, 0.1);
        }
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
        .hotspot:hover {
            transform: scale(1.1);
        }
        .tooltip {
            position: absolute;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 14px;
            white-space: nowrap;
            pointer-events: none;
            z-index: 1000;
        }
    </style>
</head>
<body>
    <div id="pano"></div>
    <div class="tour-controls">
        <button class="tour-button" id="autorotate-button" title="Auto Rotate">
            <i class="fas fa-sync-alt"></i>
        </button>
        <button class="tour-button" id="fullscreen-button" title="Fullscreen">
            <i class="fas fa-expand"></i>
        </button>
        ' . (count($tourData['scenes']) > 1 ? '
        <button class="tour-button" id="prev-scene" title="Previous Scene">
            <i class="fas fa-chevron-left"></i>
        </button>
        <button class="tour-button" id="next-scene" title="Next Scene">
            <i class="fas fa-chevron-right"></i>
        </button>
        ' : '') . '
    </div>
    <script>
        const tourData = ' . json_encode($tourData) . ';
        
        // Initialize viewer
        const viewer = new Marzipano.Viewer(document.getElementById("pano"));
        
        // Create scenes
        const scenes = tourData.scenes.map(sceneData => {
            const source = Marzipano.ImageUrlSource.fromUrl(sceneData.imageUrl);
            const geometry = new Marzipano.EquirectGeometry([{ width: 4000 }]);
            const limiter = Marzipano.RectilinearView.limit.traditional(4096, 100 * Math.PI / 180);
            const view = new Marzipano.RectilinearView(
                sceneData.initialViewParameters || {},
                limiter
            );
            
            const scene = viewer.createScene({
                source: source,
                geometry: geometry,
                view: view,
                pinFirstLevel: true
            });
            
            // Add hotspots
            if (sceneData.hotspots) {
                sceneData.hotspots.forEach(hotspot => {
                    const element = document.createElement("div");
                    element.className = "hotspot";
                    element.innerHTML = hotspot.type === "info" ? 
                        \'<i class="fas fa-info"></i>\' : 
                        \'<i class="fas fa-link"></i>\';
                    
                    scene.hotspotContainer().createHotspot(element, hotspot.position);
                    
                    // Add tooltip
                    element.addEventListener("mouseenter", () => {
                        const tooltip = document.createElement("div");
                        tooltip.className = "tooltip";
                        tooltip.textContent = hotspot.content || "Link to another scene";
                        element.appendChild(tooltip);
                    });
                    
                    element.addEventListener("mouseleave", () => {
                        const tooltip = element.querySelector(".tooltip");
                        if (tooltip) tooltip.remove();
                    });
                    
                    if (hotspot.type === "link") {
                        element.addEventListener("click", () => {
                            const targetScene = scenes.find(s => 
                                s.data.id === hotspot.target
                            );
                            if (targetScene) {
                                targetScene.scene.switchTo();
                            }
                        });
                    }
                });
            }
            
            return {
                data: sceneData,
                scene: scene,
                view: view
            };
        });
        
        // Show first scene
        scenes[0].scene.switchTo();
        
        // Setup controls
        const autorotate = Marzipano.autorotate({
            yawSpeed: 0.03,
            targetPitch: 0,
            targetFov: Math.PI/2
        });
        
        document.getElementById("autorotate-button").addEventListener("click", () => {
            if (viewer.isAutorotating()) {
                viewer.stopMovement();
                viewer.setIdleMovement(null);
            } else {
                viewer.startMovement(autorotate);
                viewer.setIdleMovement(autorotate);
            }
        });
        
        document.getElementById("fullscreen-button").addEventListener("click", () => {
            screenfull.toggle();
        });
        
        if (scenes.length > 1) {
            let currentSceneIndex = 0;
            
            document.getElementById("prev-scene").addEventListener("click", () => {
                currentSceneIndex = (currentSceneIndex - 1 + scenes.length) % scenes.length;
                scenes[currentSceneIndex].scene.switchTo();
            });
            
            document.getElementById("next-scene").addEventListener("click", () => {
                currentSceneIndex = (currentSceneIndex + 1) % scenes.length;
                scenes[currentSceneIndex].scene.switchTo();
            });
        }
    </script>
</body>
</html>';
}
?> 