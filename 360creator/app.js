// Global variables
let viewer = null;
let scenes = [];
let currentScene = null;
let hotspots = [];
let isPlacingHotspot = false;
let currentHotspotType = null;
let viewerOptions = {
    controls: {
        mouseViewMode: 'drag',
        autorotateEnabled: false,
        viewControlsEnabled: false
    }
};
let isInitializing = false; // Add flag to track initialization state

// Use Marzipano's built-in autorotate movement object
let autorotate = Marzipano.autorotate({
    yawSpeed: 0.05,         // Slower speed for smoother rotation
    targetPitch: 0,
    targetFov: Math.PI/2
});

// IndexedDB setup
const dbName = "360TourDB";
const dbVersion = 2; // Update version to match existing DB version
let db;

// Import scene manager
import sceneManager from './scene-manager.js';

// Initialize IndexedDB
async function initDB() {
    try {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(dbName, dbVersion);
        
        request.onerror = (event) => {
                console.error("IndexedDB error:", event.target.error);
                reject(event.target.error);
            };
            
            request.onblocked = (event) => {
                console.warn("IndexedDB blocked. Please close other tabs with this site open");
                reject(new Error("Database blocked"));
        };
        
        request.onsuccess = (event) => {
            db = event.target.result;
                
                // Handle database errors
                db.onerror = (event) => {
                    console.error("Database error:", event.target.error);
                };
                
                console.log("Database initialized successfully");
            resolve(db);
        };
        
        request.onupgradeneeded = (event) => {
                console.log("Upgrading IndexedDB...");
            const db = event.target.result;
            
                // Create object stores if they don't exist
            if (!db.objectStoreNames.contains('tours')) {
                const tourStore = db.createObjectStore('tours', { keyPath: 'id' });
                tourStore.createIndex('date', 'date');
                    tourStore.createIndex('title', 'title');
                    tourStore.createIndex('category', 'category');
                    tourStore.createIndex('tags', 'tags', { multiEntry: true });
                    console.log("Created tours store");
            }
            
            if (!db.objectStoreNames.contains('images')) {
                const imageStore = db.createObjectStore('images', { keyPath: 'id' });
                    imageStore.createIndex('tourId', 'tourId');
                    console.log("Created images store");
                }

                if (!db.objectStoreNames.contains('metadata')) {
                    const metaStore = db.createObjectStore('metadata', { keyPath: 'tourId' });
                    metaStore.createIndex('lastModified', 'lastModified');
                    console.log("Created metadata store");
            }
        };
    });
    } catch (error) {
        console.error("Critical IndexedDB error:", error);
        showNotification('error', 'Failed to initialize database. Some features may not work.');
        throw error;
    }
}

// Generate unique ID function
function generateUniqueId(prefix = 'tour') {
    const timestamp = Date.now();
    const randomStr = Math.random().toString(36).substring(2, 8);
    return `${prefix}_${timestamp}_${randomStr}`;
}

// Save tour to MySQL database
async function saveTourToDatabase() {
    if (scenes.length === 0) {
        showNotification('error', 'No scenes to save. Please add at least one panorama.');
        return;
    }

    try {
        const tourId = generateUniqueId('tour');
        const tourData = {
            title: document.getElementById('tour-title')?.value || 'Untitled Tour',
            description: document.getElementById('tour-description')?.value || '',
            date: new Date().toISOString()
        };

        // Save each scene as a property_media entry
        for (let i = 0; i < scenes.length; i++) {
            const sceneData = scenes[i];
            const mediaData = {
                property_id: tourData.property_id, // This should be set based on the current property
                media_type: '3d_model',
                file_path: sceneData.imageUrl,
                title: sceneData.filename,
                description: JSON.stringify({
                    tourId: tourId,
                    sceneIndex: i,
                    initialViewParameters: sceneData.initialViewParameters,
                    hotspots: sceneData.hotspots
                }),
                is_primary: i === 0 ? 1 : 0
            };

            // Send media data to server
            const response = await fetch('save-tour-media.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(mediaData)
            });

            if (!response.ok) {
                throw new Error(`Failed to save scene ${i + 1}`);
            }
        }

        showNotification('success', 'Tour saved successfully');
        return tourId;

    } catch (error) {
        console.error('Error saving tour:', error);
        showNotification('error', 'Failed to save tour: ' + error.message);
    }
}

// Load saved tours from database
async function loadSavedTours() {
    try {
        // Fetch tours from database
        const response = await fetch('get-tours.php');
        if (!response.ok) {
            throw new Error('Failed to fetch tours');
        }

        const tours = await response.json();
        
        if (tours.length === 0) {
            showNotification('info', 'No saved tours found');
            return;
        }

        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Saved Tours</h3>
                    <button class="close-modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="saved-tours-list">
                        ${tours.map(tour => `
                            <div class="saved-tour-item">
                                <div class="tour-info">
                                    <h4>${tour.title || 'Untitled Tour'}</h4>
                                    <p>${new Date(tour.created_at).toLocaleDateString()}</p>
                                </div>
                                <button class="button" onclick="loadTour(${tour.id})">
                                    <i class="fas fa-folder-open"></i> Load
                                </button>
                            </div>
                        `).join('')}
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        setTimeout(() => modal.classList.add('show'), 10);

        // Close modal functionality
        modal.querySelector('.close-modal').addEventListener('click', () => {
            modal.classList.remove('show');
            setTimeout(() => modal.remove(), 300);
        });

        // Close on outside click
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('show');
                setTimeout(() => modal.remove(), 300);
            }
        });

    } catch (error) {
        console.error('Error loading saved tours:', error);
        showNotification('error', 'Failed to load saved tours: ' + error.message);
    }
}

// Load specific tour from database
async function loadTour(tourId) {
    try {
        // Fetch tour data from database
        const response = await fetch(`get-tour.php?id=${tourId}`);
        if (!response.ok) {
            throw new Error('Failed to fetch tour');
        }

        const tourData = await response.json();
        
        // Clear current scenes
        scenes = [];
        currentScene = null;
        
        // Load each scene
        for (const mediaData of tourData.media) {
            try {
                const sceneData = JSON.parse(mediaData.description);
                await initializeViewer(mediaData.file_path, mediaData.title);
                
                // Restore hotspots and view parameters
                const scene = scenes[scenes.length - 1];
                if (scene) {
                    scene.hotspots = sceneData.hotspots || [];
                    scene.initialViewParameters = sceneData.initialViewParameters;
                    
                    // Recreate hotspots in the viewer
                    sceneData.hotspots?.forEach(hotspot => {
                        addHotspot(hotspot.position, hotspot.type);
                    });
                }
            } catch (error) {
                console.error(`Error loading scene: ${error.message}`);
                showNotification('error', `Failed to load scene: ${error.message}`);
            }
        }

        showNotification('success', 'Tour loaded successfully');
        
        // Close modal if it exists
        const modal = document.querySelector('.modal');
        if (modal) {
            modal.remove();
        }

    } catch (error) {
        console.error('Error loading tour:', error);
        showNotification('error', 'Failed to load tour: ' + error.message);
    }
}

// Initialize the viewer
function initializeViewer(imageUrl, filename) {
    try {
        const panoElement = document.getElementById('pano');

        // Double-check for duplicates
        if (scenes.some(scene => scene.filename === filename)) {
            console.log('Skipping duplicate file:', filename);
            return;
        }

        // Initialize viewer if not already initialized
        if (!viewer) {
            const viewerOpts = {
                controls: {
                    mouseViewMode: viewerOptions.controls.mouseViewMode
                }
            };
            viewer = new Marzipano.Viewer(panoElement, viewerOpts);
            console.log('Viewer initialized successfully');
            showNotification('success', 'Viewer initialized successfully');
            
            // Setup viewer event listeners
            setupViewerEventListeners();
        }

        // Create source
        const source = Marzipano.ImageUrlSource.fromString(imageUrl);

        // Create geometry
        const geometry = new Marzipano.EquirectGeometry([{ width: 4000 }]);

        // Create view
        const limiter = Marzipano.util.compose(
            Marzipano.RectilinearView.limit.traditional(4096, 120 * Math.PI / 180),
            Marzipano.RectilinearView.limit.vfov(30 * Math.PI / 180, 100 * Math.PI / 180)
        );
        const view = new Marzipano.RectilinearView({
            yaw: 0,
            pitch: 0,
            fov: Math.PI / 2
        }, limiter);

        // Create scene
        const scene = viewer.createScene({
            source: source,
            geometry: geometry,
            view: view,
            pinFirstLevel: true
        });

        // Add scene to our list
        const sceneData = {
            id: scenes.length,
            scene: scene,
            filename: filename,
            imageUrl: imageUrl,
            view: view,
            hotspots: [],
            initialViewParameters: {
                yaw: 0,
                pitch: 0,
                fov: Math.PI / 2
            }
        };

        // Final duplicate check before adding
        if (!scenes.some(scene => scene.filename === filename)) {
            scenes.push(sceneData);
            currentScene = sceneData;

            // Display new scene
            scene.switchTo({ transitionDuration: 1000 });

            // Update UI
            updatePanoramaList();
            enableControls();
            hideDropZone();

            console.log('Panorama created successfully:', filename);
            showNotification('success', 'Panorama loaded successfully');
        }

    } catch (error) {
        console.error('Error initializing viewer:', error);
        showNotification('error', 'Error initializing viewer: ' + error.message);
    }
}

// Setup viewer event listeners
function setupViewerEventListeners() {
    const panoElement = document.getElementById('pano');
    const viewerContainer = document.getElementById('viewer-container');
    
    const infoHotspotBtn = document.getElementById('info-hotspot-btn');
    if (infoHotspotBtn) {
        infoHotspotBtn.addEventListener('click', () => {
            startHotspotPlacement('info');
        });
    } else {
        console.warn('Info hotspot button not found in DOM');
    }

    const linkHotspotBtn = document.getElementById('link-hotspot-btn');
    if (linkHotspotBtn) {
        linkHotspotBtn.addEventListener('click', () => {
            startHotspotPlacement('link');
        });
    } else {
        console.warn('Link hotspot button not found in DOM');
    }

    const urlHotspotBtn = document.getElementById('url-hotspot-btn');
    if (urlHotspotBtn) {
        urlHotspotBtn.addEventListener('click', () => {
            startHotspotPlacement('url');
        });
    } else {
        console.warn('URL hotspot button not found in DOM');
    }

    const mediaHotspotBtn = document.getElementById('media-hotspot-btn');
    if (mediaHotspotBtn) {
        mediaHotspotBtn.addEventListener('click', () => {
            startHotspotPlacement('media');
        });
    } else {
        console.warn('Media hotspot button not found in DOM');
    }

    const initialViewBtn = document.getElementById('set-initial-view');
    if (initialViewBtn) {
        initialViewBtn.addEventListener('click', setInitialView);
    } else {
        console.warn('Set initial view button not found in DOM');
    }

    const exportButton = document.getElementById('export-btn');
    if (exportButton) {
        exportButton.addEventListener('click', exportTour);
    } else {
        console.warn('Export button not found in DOM');
    }
    
    panoElement.addEventListener('click', function(e) {
        if (!isPlacingHotspot || !currentScene) return;

        // Get the viewer container's bounding rect
        const rect = viewerContainer.getBoundingClientRect();
        
        // Check if click is within bounds
        if (e.clientX < rect.left || e.clientX > rect.right || 
            e.clientY < rect.top || e.clientY > rect.bottom) {
            return;
        }
        
        // Convert to spherical coordinates
        const coords = currentScene.view.screenToCoordinates({
            x: e.clientX - rect.left,
            y: e.clientY - rect.top
        });
        
        if (coords) {
            // Ensure yaw is within -PI to PI range
            let yaw = coords.yaw;
            while (yaw > Math.PI) yaw -= 2 * Math.PI;
            while (yaw < -Math.PI) yaw += 2 * Math.PI;
            
            // Ensure pitch is within valid range (-PI/2 to PI/2)
            const pitch = Math.max(-Math.PI/2, Math.min(Math.PI/2, coords.pitch));
            
            const position = {
                yaw: yaw,
                pitch: pitch
            };
            
            addHotspot(position, currentHotspotType);
            isPlacingHotspot = false;
            currentHotspotType = null;
            document.body.style.cursor = 'default';
            
            // Deactivate tool buttons
            document.querySelectorAll('.button.tool').forEach(btn => {
                btn.classList.remove('active');
            });
        }
    });
}

// Setup application event listeners
function setupEventListeners() {
    // Drop zone events
    const dropZone = document.getElementById('drop-zone');
    
    if (dropZone) {
    document.addEventListener('dragover', (e) => e.preventDefault());
    document.addEventListener('drop', (e) => e.preventDefault());
    
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropZone.classList.add('dragover');
    });

    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('dragover');
    });

    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropZone.classList.remove('dragover');
        handleFiles(e.dataTransfer.files);
    });
    }

    // File input
    const fileInput = document.getElementById('file-input');
    if (fileInput) {
        fileInput.addEventListener('change', (e) => {
        handleFiles(e.target.files);
    });
    }

    // Navigation mode
    document.querySelectorAll('input[name="navMode"]').forEach(input => {
        input.addEventListener('change', (e) => {
            viewerOptions.controls.mouseViewMode = e.target.value;
            if (viewer) {
                viewer.setMouseViewMode(e.target.value);
            }
        });
    });

    // Autorotate
    const autorotateCheckbox = document.getElementById('autorotate');
    if (autorotateCheckbox) {
        autorotateCheckbox.addEventListener('change', (e) => {
        if (e.target.checked) {
            startAutorotate();
        } else {
            stopAutorotate();
        }
    });
    }

    // View controls
    const viewControlsCheckbox = document.getElementById('view-controls');
    if (viewControlsCheckbox) {
        viewControlsCheckbox.addEventListener('change', (e) => {
        viewerOptions.controls.viewControlsEnabled = e.target.checked;
        if (viewer) {
            if (e.target.checked) {
                viewer.controls().enable();
            } else {
                viewer.controls().disable();
            }
        }
    });
    }

    // Hotspot buttons
    const infoHotspotBtn = document.getElementById('info-hotspot-btn');
    if (infoHotspotBtn) {
        infoHotspotBtn.addEventListener('click', () => {
        startHotspotPlacement('info');
    });
    } else {
        console.warn('Info hotspot button not found in DOM');
    }

    const linkHotspotBtn = document.getElementById('link-hotspot-btn');
    if (linkHotspotBtn) {
        linkHotspotBtn.addEventListener('click', () => {
        startHotspotPlacement('link');
    });
    } else {
        console.warn('Link hotspot button not found in DOM');
    }

    const urlHotspotBtn = document.getElementById('url-hotspot-btn');
    if (urlHotspotBtn) {
        urlHotspotBtn.addEventListener('click', () => {
            startHotspotPlacement('url');
        });
    } else {
        console.warn('URL hotspot button not found in DOM');
    }

    const mediaHotspotBtn = document.getElementById('media-hotspot-btn');
    if (mediaHotspotBtn) {
        mediaHotspotBtn.addEventListener('click', () => {
            startHotspotPlacement('media');
        });
    } else {
        console.warn('Media hotspot button not found in DOM');
    }

    // Set initial view button
    const initialViewBtn = document.getElementById('set-initial-view');
    if (initialViewBtn) {
        initialViewBtn.addEventListener('click', setInitialView);
    } else {
        console.warn('Set initial view button not found in DOM');
    }

    // Export button
    const exportButton = document.getElementById('export-btn');
    if (exportButton) {
        exportButton.addEventListener('click', exportTour);
    } else {
        console.warn('Export button not found in DOM');
    }
}

// Handle uploaded files
function handleFiles(files) {
    if (files.length === 0) return;

    // Only process the first file
    const file = files[0];
    
    showProcessing();

    // Check if file is already processed
    if (scenes.some(scene => scene.filename === file.name)) {
        hideProcessing();
        showNotification('info', 'This file has already been added');
        return;
    }

    if (!file.type.startsWith('image/')) {
        hideProcessing();
        showNotification('error', `${file.name} is not an image file`);
        return;
    }

    // Initialize viewer once if needed
    if (!viewer) {
        const panoElement = document.getElementById('pano');
        const viewerOpts = {
            controls: {
                mouseViewMode: viewerOptions.controls.mouseViewMode
            }
        };
        viewer = new Marzipano.Viewer(panoElement, viewerOpts);
        console.log('Viewer initialized successfully');
        showNotification('success', 'Viewer initialized successfully');
        setupViewerEventListeners();
    }

    const reader = new FileReader();
    reader.onload = (e) => {
        createScene(e.target.result, file.name);
        hideProcessing();
    };
    reader.onerror = () => {
        showNotification('error', `Error reading file: ${file.name}`);
        hideProcessing();
    };
    reader.readAsDataURL(file);
}

// Create a new scene
function createScene(imageUrl, filename) {
    try {
        // Create source
        const source = Marzipano.ImageUrlSource.fromString(imageUrl);

        // Create geometry
        const geometry = new Marzipano.EquirectGeometry([{ width: 4000 }]);

        // Create view
        const limiter = Marzipano.util.compose(
            Marzipano.RectilinearView.limit.traditional(4096, 120 * Math.PI / 180),
            Marzipano.RectilinearView.limit.vfov(30 * Math.PI / 180, 100 * Math.PI / 180)
        );
        const view = new Marzipano.RectilinearView({
            yaw: 0,
            pitch: 0,
            fov: Math.PI / 2
        }, limiter);

        // Create scene
        const scene = viewer.createScene({
            source: source,
            geometry: geometry,
            view: view,
            pinFirstLevel: true
        });

        // Create scene data
        const sceneData = {
            id: scenes.length,
            scene: scene,
            filename: filename,
            imageUrl: imageUrl,
            view: view,
            hotspots: [],
            initialViewParameters: {
                yaw: 0,
                pitch: 0,
                fov: Math.PI / 2
            }
        };

        // Add to scenes array
        scenes.push(sceneData);
        currentScene = sceneData;

        // Switch to new scene
        scene.switchTo({ transitionDuration: 1000 });

        // Update UI
        updatePanoramaList();
        enableControls();
        hideDropZone();

        console.log('Panorama created successfully:', filename);
        showNotification('success', 'Panorama loaded successfully');

    } catch (error) {
        console.error('Error creating scene:', error);
        showNotification('error', 'Error creating scene: ' + error.message);
    }
}

// Update panorama list in UI
function updatePanoramaList() {
    const container = document.getElementById('panorama-list');
    container.innerHTML = '';
    
    scenes.forEach((sceneData, index) => {
        const div = document.createElement('div');
        div.className = 'panorama-item' + (sceneData === currentScene ? ' active' : '');
        
        const icon = document.createElement('i');
        icon.className = 'fas fa-image';
        
        const name = document.createElement('span');
        name.textContent = sceneData.filename;
        
        div.appendChild(icon);
        div.appendChild(name);
        
        div.addEventListener('click', () => {
            switchScene(sceneData);
        });
        
        container.appendChild(div);
    });
}

// Switch to a different scene
function switchScene(sceneData) {
    if (sceneData === currentScene) return;
    
    // Add transition effect
    const transitionOverlay = document.createElement('div');
    transitionOverlay.className = 'transition-overlay';
    document.body.appendChild(transitionOverlay);
    
    // Fade out
    transitionOverlay.style.opacity = '1';
    
    setTimeout(() => {
        // Remove all existing hotspot elements from the DOM
        const hotspotContainer = document.querySelector('.marzipano-hotspot-container');
        if (hotspotContainer) {
            while (hotspotContainer.firstChild) {
                hotspotContainer.removeChild(hotspotContainer.firstChild);
            }
        }

        // Switch scene
        sceneData.scene.switchTo({
            transitionDuration: 1000
        });
        
        // Update current scene
        currentScene = sceneData;
        
        // Recreate hotspots for the new scene
        if (currentScene.hotspots) {
            currentScene.hotspots.forEach(hotspot => {
                createHotspotElement(hotspot);
            });
        }
        
        // Update UI
        updatePanoramaList();
        
        // Fade in
        setTimeout(() => {
            transitionOverlay.style.opacity = '0';
            setTimeout(() => {
                transitionOverlay.remove();
            }, 500);
        }, 100);
        
        showNotification('info', `Switched to ${sceneData.filename}`);
    }, 500);
}

// Helper function to create hotspot element
function createHotspotElement(hotspot) {
    const element = document.createElement('div');
    element.id = hotspot.id;
    element.className = `hotspot ${hotspot.type}-hotspot`;
    
    // Set background color based on type
    switch(hotspot.type) {
        case 'info':
            element.style.backgroundColor = '#0f9d58';
            break;
        case 'link':
            element.style.backgroundColor = '#4285f4';
            break;
        case 'url':
            element.style.backgroundColor = '#db4437';
            break;
        case 'media':
            element.style.backgroundColor = '#f4b400';
            break;
    }
    
    const icon = document.createElement('i');
    icon.className = getHotspotIcon(hotspot.type);
    element.appendChild(icon);
    
    // Create tooltip
    const tooltip = document.createElement('div');
    tooltip.className = 'hotspot-tooltip';
    tooltip.style.display = 'none';
    
    // Set tooltip text based on hotspot type
    switch(hotspot.type) {
        case 'info':
            tooltip.textContent = hotspot.title || 'Information';
            break;
        case 'link':
            const targetScene = scenes.find(scene => scene.id === hotspot.targetScene);
            tooltip.textContent = hotspot.title || (targetScene ? `Go to ${targetScene.filename}` : 'Link Hotspot');
            break;
        case 'url':
            tooltip.textContent = hotspot.title || hotspot.url || 'External Link';
            break;
        case 'media':
            tooltip.textContent = hotspot.title || 'Media Content';
            break;
    }
    
    element.appendChild(tooltip);
    
    // Add hover effects
    element.addEventListener('mouseenter', () => {
        tooltip.style.display = 'block';
    });
    
    element.addEventListener('mouseleave', () => {
        tooltip.style.display = 'none';
    });
    
    // Add click handler based on type
    element.addEventListener('click', (e) => {
        e.stopPropagation();
        switch(hotspot.type) {
            case 'info':
                showInfoHotspotModal(hotspot, element);
                break;
            case 'link':
                const targetScene = scenes.find(scene => scene.id === hotspot.targetScene);
                if (targetScene) {
                    switchScene(targetScene);
                }
                break;
            case 'url':
                if (hotspot.url) {
                    window.open(hotspot.url, hotspot.target || '_blank');
                } else {
                    showUrlHotspotModal(hotspot, element);
                }
                break;
            case 'media':
                if (hotspot.mediaUrl) {
                    showMediaViewer(hotspot);
                } else {
                    showMediaHotspotModal(hotspot, element);
                }
                break;
        }
    });
    
    // Add context menu
    element.addEventListener('contextmenu', (e) => {
        e.preventDefault();
        showHotspotContextMenu(hotspot, element, e);
    });
    
    // Create Marzipano hotspot in 3D space
    currentScene.scene.hotspotContainer().createHotspot(element, hotspot.position);
    
    return element;
}

// Start hotspot placement mode
function startHotspotPlacement(type) {
    if (!viewer || !currentScene) {
        showNotification('error', 'Please load a panorama first');
        return;
    }

    // Deactivate any active tool buttons
    document.querySelectorAll('.button.tool').forEach(btn => {
        btn.classList.remove('active');
    });

    // Activate the clicked button
    const element = document.getElementById(type + '-hotspot-btn');
    if (element) {
        element.classList.add('active');
    } else {
        console.warn('Element not found in DOM');
    }

    isPlacingHotspot = true;
    currentHotspotType = type;
    document.body.style.cursor = 'crosshair';
    showNotification('info', 'Click anywhere on the panorama to place a ' + type + ' hotspot');
}

// Add a hotspot to the current scene
function addHotspot(position, type) {
    if (!currentScene) return;

    // Create hotspot data with normalized coordinates
    const hotspot = {
        id: `hotspot-${currentScene.hotspots.length}`,
        type: type,
        position: {
            yaw: position.yaw,
            pitch: position.pitch
        },
        title: '',
        content: '',
        targetScene: null, // For link hotspots
        url: '', // For URL hotspots
        mediaType: '', // For media hotspots
        mediaUrl: '', // For media hotspots
        mediaOptions: {} // For media hotspots
    };

    // Create hotspot DOM element
    const element = document.createElement('div');
    element.id = hotspot.id;
    element.className = `hotspot ${type}-hotspot`;
    
    // Set background color based on type
    switch(type) {
        case 'info':
            element.style.backgroundColor = '#0f9d58';
            break;
        case 'link':
            element.style.backgroundColor = '#4285f4';
            break;
        case 'url':
            element.style.backgroundColor = '#db4437';
            break;
        case 'media':
            element.style.backgroundColor = '#f4b400';
            break;
    }
    
    const icon = document.createElement('i');
    icon.className = getHotspotIcon(type);
    element.appendChild(icon);
    
    // Create tooltip
    const tooltip = document.createElement('div');
    tooltip.className = 'hotspot-tooltip';
    tooltip.style.display = 'none';
    element.appendChild(tooltip);
    
    // Add hover effects
    element.addEventListener('mouseenter', () => {
        if (!isDragging && (hotspot.title || hotspot.content)) {
            tooltip.style.display = 'block';
        }
    });
    
    element.addEventListener('mouseleave', () => {
        tooltip.style.display = 'none';
    });

    // Add drag functionality
    let isDragging = false;
    let dragStart = null;
    const viewerContainer = document.getElementById('viewer-container');

    element.addEventListener('mousedown', startDragging);
    element.addEventListener('touchstart', startDragging);
    document.addEventListener('mousemove', handleDrag);
    document.addEventListener('touchmove', handleDrag);
    document.addEventListener('mouseup', stopDragging);
    document.addEventListener('touchend', stopDragging);

    function startDragging(e) {
        if (e.type === 'mousedown' && e.button !== 0) return;
        e.preventDefault();
        e.stopPropagation();

        isDragging = true;
        element.classList.add('dragging');
        viewerContainer.classList.add('pano-dragging');
        
        const rect = viewerContainer.getBoundingClientRect();
        dragStart = {
            x: (e.type === 'touchstart' ? e.touches[0].clientX : e.clientX) - rect.left,
            y: (e.type === 'touchstart' ? e.touches[0].clientY : e.clientY) - rect.top
        };
    }

    function handleDrag(e) {
        if (!isDragging) return;
        e.preventDefault();
        e.stopPropagation();

        const rect = viewerContainer.getBoundingClientRect();
        let currentX = (e.type === 'touchmove' ? e.touches[0].clientX : e.clientX);
        let currentY = (e.type === 'touchmove' ? e.touches[0].clientY : e.clientY);
        
        // Constrain to viewer boundaries
        currentX = Math.max(rect.left, Math.min(rect.right, currentX));
        currentY = Math.max(rect.top, Math.min(rect.bottom, currentY));
        
        const currentPos = {
            x: currentX - rect.left,
            y: currentY - rect.top
        };

        // Convert to spherical coordinates
        const coords = currentScene.view.screenToCoordinates(currentPos);
        
        if (coords) {
            // Normalize coordinates
            let yaw = coords.yaw;
            while (yaw > Math.PI) yaw -= 2 * Math.PI;
            while (yaw < -Math.PI) yaw += 2 * Math.PI;
            
            const pitch = Math.max(-Math.PI/2, Math.min(Math.PI/2, coords.pitch));
            
            hotspot.position = {
                yaw: yaw,
                pitch: pitch
            };
            
            // Update hotspot position in 3D space
            currentScene.scene.hotspotContainer().createHotspot(element, hotspot.position);
        }
    }

    function stopDragging(e) {
        if (!isDragging) return;
        e.preventDefault();
        
        isDragging = false;
        dragStart = null;
        element.classList.remove('dragging');
        viewerContainer.classList.remove('pano-dragging');
    }
    
    // Add click handler (only trigger if not dragging)
    element.addEventListener('click', (e) => {
        if (!isDragging) {
            e.stopPropagation();
            switch(type) {
                case 'info':
                showInfoHotspotModal(hotspot, element);
                    break;
                case 'link':
                    if (hotspot.targetScene) {
                const targetScene = scenes.find(scene => scene.id === hotspot.targetScene);
                if (targetScene) {
                    switchScene(targetScene);
                }
                    } else {
                        showLinkHotspotModal(hotspot, element);
                    }
                    break;
                case 'url':
                    if (hotspot.url) {
                        window.open(hotspot.url, '_blank');
                    } else {
                        showUrlHotspotModal(hotspot, element);
                    }
                    break;
                case 'media':
                    if (hotspot.mediaUrl) {
                        showMediaViewer(hotspot);
                    } else {
                        showMediaHotspotModal(hotspot, element);
                    }
                    break;
            }
        }
    });
    
    // Add context menu
    element.addEventListener('contextmenu', (e) => {
        e.preventDefault();
        if (!isDragging) {
            showHotspotContextMenu(hotspot, element, e);
        }
    });
    
    // Create Marzipano hotspot in 3D space with normalized coordinates
    currentScene.scene.hotspotContainer().createHotspot(element, hotspot.position);
    
    // Store hotspot data
    currentScene.hotspots.push(hotspot);
    
    // Show edit modal immediately after creation
    switch(type) {
        case 'info':
        showInfoHotspotModal(hotspot, element);
            break;
        case 'link':
        showLinkHotspotModal(hotspot, element);
            break;
        case 'url':
            showUrlHotspotModal(hotspot, element);
            break;
        case 'media':
            showMediaHotspotModal(hotspot, element);
            break;
    }
}

// Helper function to get hotspot icon based on type
function getHotspotIcon(type) {
    switch(type) {
        case 'info':
            return 'fas fa-info';
        case 'link':
            return 'fas fa-link';
        case 'url':
            return 'fas fa-external-link-alt';
        case 'media':
            return 'fas fa-photo-video';
        default:
            return 'fas fa-circle';
    }
}

// Show info hotspot edit modal
function showInfoHotspotModal(hotspot, element) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Info Hotspot</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" id="hotspot-title" value="${hotspot.title || ''}" placeholder="Enter title">
                </div>
                <div class="form-group">
                    <label>Content</label>
                    <textarea id="hotspot-content" placeholder="Enter information content">${hotspot.content || ''}</textarea>
                </div>
                <div class="form-group">
                    <button class="button" onclick="saveInfoHotspot('${hotspot.id}')">
                        <i class="fas fa-save"></i> Save
                    </button>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
    setTimeout(() => modal.classList.add('show'), 10);

    // Close modal functionality
    modal.querySelector('.close-modal').addEventListener('click', () => {
        modal.classList.remove('show');
        setTimeout(() => modal.remove(), 300);
    });

    // Close on outside click
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('show');
            setTimeout(() => modal.remove(), 300);
        }
    });
}

// Show link hotspot edit modal
function showLinkHotspotModal(hotspot, element) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Link Hotspot</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" id="hotspot-title" value="${hotspot.title || ''}" placeholder="Enter title">
                </div>
                <div class="form-group">
                    <label>Target Scene</label>
                    <select id="target-scene">
                        <option value="">Select target scene</option>
                        ${scenes.filter(scene => scene !== currentScene).map(scene => 
                            `<option value="${scene.id}" ${hotspot.targetScene === scene.id ? 'selected' : ''}>
                                ${scene.filename}
                            </option>`
                        ).join('')}
                    </select>
                </div>
                <div class="form-group">
                    <button class="button" onclick="saveLinkHotspot('${hotspot.id}')">
                        <i class="fas fa-save"></i> Save
                    </button>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
    setTimeout(() => modal.classList.add('show'), 10);

    // Close modal functionality
    modal.querySelector('.close-modal').addEventListener('click', () => {
        modal.classList.remove('show');
        setTimeout(() => modal.remove(), 300);
    });

    // Close on outside click
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('show');
            setTimeout(() => modal.remove(), 300);
        }
    });
}

// Save info hotspot data
function saveInfoHotspot(hotspotId) {
    const title = document.getElementById('hotspot-title').value;
    const content = document.getElementById('hotspot-content').value;
    
    const hotspot = findHotspotById(hotspotId);
    if (hotspot) {
        hotspot.title = title;
        hotspot.content = content;
        
        // Update tooltip
        const element = findHotspotElement(hotspot);
        if (element) {
            const tooltip = element.querySelector('.hotspot-tooltip');
            if (tooltip) {
                tooltip.textContent = title;
            }
        }
        
        showNotification('success', 'Info hotspot updated');
        document.querySelector('.modal').remove();
    }
}

// Save link hotspot data
function saveLinkHotspot(hotspotId) {
    const title = document.getElementById('hotspot-title').value;
    const targetSceneId = parseInt(document.getElementById('target-scene').value);
    
    if (!targetSceneId && targetSceneId !== 0) {
        showNotification('error', 'Please select a target scene');
        return;
    }
    
    const hotspot = findHotspotById(hotspotId);
    if (hotspot) {
        hotspot.title = title;
        hotspot.targetScene = targetSceneId;
        
        // Update tooltip and click handler
        const element = findHotspotElement(hotspot);
        if (element) {
            const tooltip = element.querySelector('.hotspot-tooltip');
            if (tooltip) {
                const targetScene = scenes.find(scene => scene.id === targetSceneId);
                tooltip.textContent = title || (targetScene ? `Go to ${targetScene.filename}` : 'Link Hotspot');
            }
            
            // Update click handler
            element.onclick = (e) => {
                e.stopPropagation();
                const targetScene = scenes.find(scene => scene.id === targetSceneId);
                if (targetScene) {
                    switchScene(targetScene);
                }
            };
        }
        
        showNotification('success', 'Link hotspot updated');
        document.querySelector('.modal').remove();
    }
}

// Show hotspot context menu
function showHotspotContextMenu(hotspot, element, event) {
    // Remove any existing context menus
    document.querySelectorAll('.context-menu').forEach(menu => menu.remove());
    
    const menu = document.createElement('div');
    menu.className = 'context-menu';
    menu.style.position = 'fixed';
    menu.style.left = event.clientX + 'px';
    menu.style.top = event.clientY + 'px';
    menu.style.background = '#2d2d2d';
    menu.style.border = '1px solid #3d3d3d';
    menu.style.borderRadius = '4px';
    menu.style.padding = '8px 0';
    menu.style.zIndex = '1000';
    
    const editOption = document.createElement('div');
    editOption.className = 'context-menu-item';
    editOption.innerHTML = '<i class="fas fa-edit"></i> Edit';
    editOption.style.padding = '8px 16px';
    editOption.style.cursor = 'pointer';
    editOption.style.color = '#fff';
    editOption.onmouseover = () => editOption.style.background = '#3d3d3d';
    editOption.onmouseout = () => editOption.style.background = 'transparent';
    editOption.onclick = () => {
        menu.remove();
        if (hotspot.type === 'info') {
            showInfoHotspotModal(hotspot, element);
        } else {
            showLinkHotspotModal(hotspot, element);
        }
    };
    
    const deleteOption = document.createElement('div');
    deleteOption.className = 'context-menu-item';
    deleteOption.innerHTML = '<i class="fas fa-trash"></i> Delete';
    deleteOption.style.padding = '8px 16px';
    deleteOption.style.cursor = 'pointer';
    deleteOption.style.color = '#fff';
    deleteOption.onmouseover = () => deleteOption.style.background = '#3d3d3d';
    deleteOption.onmouseout = () => deleteOption.style.background = 'transparent';
    deleteOption.onclick = () => {
        menu.remove();
        deleteHotspot(hotspot, element);
    };
    
    menu.appendChild(editOption);
    menu.appendChild(deleteOption);
    document.body.appendChild(menu);
    
    // Close menu on click outside
    document.addEventListener('click', function closeMenu(e) {
        if (!menu.contains(e.target)) {
            menu.remove();
            document.removeEventListener('click', closeMenu);
        }
    });
}

// Delete hotspot
function deleteHotspot(hotspot, element) {
    const index = currentScene.hotspots.indexOf(hotspot);
    if (index > -1) {
        currentScene.hotspots.splice(index, 1);
        element.remove();
        showNotification('success', 'Hotspot deleted');
    }
}

// Helper function to find hotspot by ID
function findHotspotById(hotspotId) {
    return currentScene.hotspots.find(h => h.id === hotspotId);
}

// Helper function to find hotspot DOM element
function findHotspotElement(hotspot) {
    return document.querySelector(`#${hotspot.id}`);
}

// Set initial view for current scene
function setInitialView() {
    if (!currentScene) {
        showNotification('error', 'Please load a panorama first');
        return;
    }

    const view = currentScene.view;
    currentScene.initialViewParameters = {
        yaw: view.yaw(),
        pitch: view.pitch(),
        fov: view.fov()
    };

    showNotification('success', 'Initial view has been set');
}

// Export tour data
function exportTour() {
    if (scenes.length === 0) {
        showNotification('error', 'Please add at least one panorama');
        return;
    }

    // Create a modal for export options
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3>Export & Share Tour</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="modal-section">
                    <div class="form-group">
                        <label>Tour Title</label>
                        <input type="text" id="tour-title" placeholder="Enter tour title" required>
                    </div>
                    <div class="form-group">
                        <label>Tour Description</label>
                        <textarea id="tour-description" placeholder="Enter tour description"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Tour Settings</label>
                        <div class="checkbox-group">
                            <label class="checkbox-button">
                                <input type="checkbox" id="export-images" checked>
                                <span>Include Image Files</span>
                            </label>
                            <label class="checkbox-button">
                                <input type="checkbox" id="export-viewer" checked>
                                <span>Include Viewer</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="modal-section">
                    <div class="form-group">
                        <label>Save & Publish</label>
                        <button class="button" id="publish-btn">
                            <i class="fas fa-globe"></i> Save & Publish Tour
                        </button>
                        <button class="button secondary" id="save-draft-btn">
                            <i class="fas fa-save"></i> Save as Draft
                        </button>
                    </div>
                </div>

                <div class="modal-section">
                    <div class="form-group">
                        <label>Share Options</label>
                        <div class="share-buttons">
                            <button class="share-btn facebook" data-platform="facebook">
                                <i class="fab fa-facebook-f"></i> Facebook
                            </button>
                            <button class="share-btn twitter" data-platform="twitter">
                                <i class="fab fa-twitter"></i> Twitter
                            </button>
                            <button class="share-btn linkedin" data-platform="linkedin">
                                <i class="fab fa-linkedin-in"></i> LinkedIn
                            </button>
                        </div>
                    </div>
                </div>

                <div class="modal-section">
                    <div class="form-group">
                        <label>Export Options</label>
                        <button class="button" id="download-package-btn">
                            <i class="fas fa-download"></i> Download Tour Package
                        </button>
                        <button class="button secondary" id="download-json-btn">
                            <i class="fas fa-file-code"></i> Download Tour JSON
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Add modal to document
    document.body.appendChild(modal);
    
    // Add event listeners
    const closeBtn = modal.querySelector('.close-modal');
    closeBtn.addEventListener('click', () => {
        document.body.removeChild(modal);
    });

    // Publish button
    const publishBtn = modal.querySelector('#publish-btn');
    publishBtn.addEventListener('click', generatePublicView);

    // Save draft button
    const saveDraftBtn = modal.querySelector('#save-draft-btn');
    saveDraftBtn.addEventListener('click', saveTourToDatabase);

    // Share buttons
    const shareButtons = modal.querySelectorAll('.share-btn');
    shareButtons.forEach(button => {
        button.addEventListener('click', () => {
            shareToSocial(button.dataset.platform);
        });
    });

    // Download buttons
    const downloadPackageBtn = modal.querySelector('#download-package-btn');
    downloadPackageBtn.addEventListener('click', downloadTourPackage);

    const downloadJsonBtn = modal.querySelector('#download-json-btn');
    downloadJsonBtn.addEventListener('click', downloadTourJSON);

    // Show modal
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
}

// Generate public view with enhanced error handling and progress feedback
async function generatePublicView() {
    const title = document.getElementById('tour-title').value;
    if (!title) {
        showNotification('error', 'Please enter a tour title');
        return;
    }

    showProcessing();
    try {
        // Save to MySQL database first
        const tourId = await saveTourToDatabase();
        if (!tourId) {
            throw new Error('Failed to save tour');
        }

        // Prepare tour data for publishing
        const tourData = {
            id: tourId,
            title: title,
            description: document.getElementById('tour-description').value || '',
            date: new Date().toISOString(),
            scenes: scenes.map(scene => ({
                ...scene,
                scene: undefined, // Remove Marzipano scene object
                view: undefined, // Remove Marzipano view object
            }))
        };

        // Send tour data to the server
        const response = await fetch('published-tours.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(tourData)
        });

        const result = await response.json();
        
        if (!response.ok) {
            throw new Error(result.message || 'Failed to publish tour');
        }

        hideProcessing();
        showNotification('success', 'Tour published successfully!');
        
        // Open the published tours page in a new tab
        window.open('public-tours.html', '_blank');

    } catch (error) {
        console.error('Error generating public view:', error);
        hideProcessing();
        showNotification('error', 'Failed to generate public view: ' + error.message);
    }
}

// Generate public HTML file
async function generatePublicHtml(tourId) {
    const tour = await getTourData(tourId);
    if (!tour) {
        throw new Error('Could not retrieve tour data');
    }

    return `
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>${escapeHtml(tour.title)} - Virtual Tour</title>
            <script src="https://cdn.jsdelivr.net/npm/marzipano@0.10.2/dist/marzipano.js"></script>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <style>
                /* Include necessary styles */
                ${generatePublicStyles()}
            </style>
        </head>
        <body>
            <div id="pano"></div>
            <div class="tour-controls">
                <!-- Add tour controls HTML -->
                ${generateTourControlsHtml(tour)}
            </div>
            <script>
                // Include tour data and viewer initialization
                const tourData = ${JSON.stringify(tour)};
                ${generatePublicViewerScript()}
            </script>
        </body>
        </html>
    `;
}

// Enhanced social sharing with proper metadata
function shareToSocial(platform) {
    const title = document.getElementById('tour-title').value || 'My 360° Virtual Tour';
    const description = document.getElementById('tour-description').value || 'Check out my amazing 360° virtual tour!';
    
    // Get the current tour's public URL
    const publicUrl = window.location.origin + '/tours/' + getCurrentTourId();
    
    // Prepare Open Graph metadata
    const metadata = {
        title: title,
        description: description,
        image: scenes[0]?.imageUrl || '', // Use first scene as preview
        url: publicUrl
    };

    // Platform-specific sharing
    let shareUrl;
    switch(platform) {
        case 'facebook':
            shareUrl = generateFacebookShareUrl(metadata);
            break;
        case 'twitter':
            shareUrl = generateTwitterShareUrl(metadata);
            break;
        case 'linkedin':
            shareUrl = generateLinkedInShareUrl(metadata);
            break;
        default:
            showNotification('error', 'Invalid sharing platform');
            return;
    }

    // Open sharing dialog
    const width = 600;
    const height = 400;
    const left = (window.innerWidth - width) / 2;
    const top = (window.innerHeight - height) / 2;
    
    window.open(
        shareUrl,
        'Share Tour',
        `width=${width},height=${height},left=${left},top=${top},toolbar=no,menubar=no`
    );
}

// Download complete tour package
async function downloadTourPackage() {
    try {
        showProcessing();
        
        // Get tour data
        const tourData = await prepareTourData();
        
        // Create a ZIP file
        const zip = new JSZip();
        
        // Add tour JSON
        zip.file('tour.json', JSON.stringify(tourData, null, 2));
        
        // Add images if selected
        if (document.getElementById('export-images').checked) {
            const imagesFolder = zip.folder('images');
            for (const scene of scenes) {
                const imageBlob = await fetch(scene.imageUrl).then(r => r.blob());
                imagesFolder.file(scene.filename, imageBlob);
            }
        }
        
        // Add viewer if selected
        if (document.getElementById('export-viewer').checked) {
            zip.file('index.html', generateStandaloneHtml(tourData));
        }
        
        // Generate and download ZIP
        const blob = await zip.generateAsync({type: 'blob'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
        a.download = `${tourData.title || 'virtual-tour'}.zip`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    
        hideProcessing();
    showNotification('success', 'Tour package downloaded successfully');
        
    } catch (error) {
        console.error('Error downloading tour package:', error);
        hideProcessing();
        showNotification('error', 'Failed to download tour package: ' + error.message);
    }
}

// Download tour JSON only
function downloadTourJSON() {
    try {
        const tourData = prepareTourData();
        const blob = new Blob([JSON.stringify(tourData, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${tourData.title || 'virtual-tour'}.json`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        
        showNotification('success', 'Tour JSON downloaded successfully');
        
    } catch (error) {
        console.error('Error downloading tour JSON:', error);
        showNotification('error', 'Failed to download tour JSON: ' + error.message);
    }
}

// Helper function to prepare tour data
function prepareTourData() {
    const title = document.getElementById('tour-title').value || 'Untitled Tour';
    const description = document.getElementById('tour-description').value || '';
    
    return {
        title: title,
        description: description,
        created: new Date().toISOString(),
        scenes: scenes.map(scene => ({
            id: scene.id,
            filename: scene.filename,
            initialViewParameters: scene.initialViewParameters,
            hotspots: scene.hotspots.map(hotspot => ({
                id: hotspot.id,
                type: hotspot.type,
                position: hotspot.position,
                content: hotspot.content,
                target: hotspot.target
            }))
        }))
    };
}

// Helper functions for social sharing
function generateFacebookShareUrl(metadata) {
    return `https://www.facebook.com/dialog/share?${new URLSearchParams({
        app_id: 'YOUR_FB_APP_ID', // Replace with your Facebook App ID
        href: metadata.url,
        quote: `${metadata.title} - ${metadata.description}`,
        hashtag: '#VirtualTour'
    })}`;
}

function generateTwitterShareUrl(metadata) {
    return `https://twitter.com/intent/tweet?${new URLSearchParams({
        text: `${metadata.title} - ${metadata.description}`,
        url: metadata.url,
        hashtags: 'VirtualTour,360Tour'
    })}`;
}

function generateLinkedInShareUrl(metadata) {
    return `https://www.linkedin.com/sharing/share-offsite/?${new URLSearchParams({
        url: metadata.url,
        title: metadata.title,
        summary: metadata.description
    })}`;
}

// Helper function to escape HTML
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Helper function to get current tour ID
function getCurrentTourId() {
    // Implementation depends on your tour ID management system
    return generateUniqueId('tour');
}

// UI helper functions
function showProcessing() {
    document.querySelector('.processing-overlay').style.display = 'flex';
}

function hideProcessing() {
    document.querySelector('.processing-overlay').style.display = 'none';
}

function hideDropZone() {
    document.getElementById('drop-zone').style.display = 'none';
}

function enableControls() {
    const controls = [
        'info-hotspot-btn',
        'link-hotspot-btn',
        'url-hotspot-btn',
        'media-hotspot-btn',
        'set-initial-view',
        'export-btn'
    ];
    
    controls.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            element.disabled = false;
            console.log(`Enabled control: ${id}`);
        }
    });
}

function showNotification(type, message) {
    const container = document.getElementById('notifications');
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    
    const icon = document.createElement('i');
    icon.className = `fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}`;
    
    notification.appendChild(icon);
    notification.appendChild(document.createTextNode(message));
    
    container.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => {
            container.removeChild(notification);
        }, 300);
    }, 3000);
}

// Initialize the application
document.addEventListener('DOMContentLoaded', async () => {
    let dbInitialized = false;
    
    try {
        await initDB();
        dbInitialized = true;
        console.log('Database initialized successfully');
    } catch (error) {
        console.error('Failed to initialize IndexedDB:', error);
        showNotification('warning', 'Some features may be limited due to database initialization failure');
    }

    // Setup event listeners with null checks
    const setupEventListeners = () => {
        // Drop zone events
        const dropZone = document.getElementById('drop-zone');
        if (dropZone) {
            document.addEventListener('dragover', (e) => e.preventDefault());
            document.addEventListener('drop', (e) => e.preventDefault());
            
            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropZone.classList.add('dragover');
            });

            dropZone.addEventListener('dragleave', () => {
                dropZone.classList.remove('dragover');
            });

            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropZone.classList.remove('dragover');
                handleFiles(e.dataTransfer.files);
            });
        }

        // File input
        const fileInput = document.getElementById('file-input');
        if (fileInput) {
            fileInput.addEventListener('change', (e) => {
                handleFiles(e.target.files);
            });
        }

        // Navigation mode
        document.querySelectorAll('input[name="navMode"]').forEach(input => {
            input.addEventListener('change', (e) => {
                viewerOptions.controls.mouseViewMode = e.target.value;
                if (viewer) {
                    viewer.setMouseViewMode(e.target.value);
                }
            });
        });

        // Autorotate
        const autorotateCheckbox = document.getElementById('autorotate');
        if (autorotateCheckbox) {
            autorotateCheckbox.addEventListener('change', (e) => {
                if (e.target.checked) {
                    startAutorotate();
                } else {
                    stopAutorotate();
                }
            });
        }

        // View controls
        const viewControlsCheckbox = document.getElementById('view-controls');
        if (viewControlsCheckbox) {
            viewControlsCheckbox.addEventListener('change', (e) => {
                viewerOptions.controls.viewControlsEnabled = e.target.checked;
                if (viewer) {
                    if (e.target.checked) {
                        viewer.controls().enable();
                    } else {
                        viewer.controls().disable();
                    }
                }
            });
        }
    };

    // Call setup event listeners
    setupEventListeners();
    
    // Rest of the initialization code...
});

function startAutorotate() {
    if (!viewer || !currentScene) return;
    
    stopAutorotate(); // Stop any existing rotation
    
    try {
        viewer.startMovement(autorotate);
        viewer.setIdleMovement(3000, autorotate);
        document.getElementById('autorotate').classList.add('active');
    } catch (error) {
        console.error('Error starting autorotate:', error);
        showNotification('error', 'Failed to start auto-rotation');
    }
}

function stopAutorotate() {
    if (!viewer) return;
    
    try {
        viewer.stopMovement();
        viewer.setIdleMovement(Infinity);
        document.getElementById('autorotate').classList.remove('active');
    } catch (error) {
        console.error('Error stopping autorotate:', error);
    }
}

// Helper function to get tour data
async function getTourData(tourId) {
    try {
        const tx = db.transaction(['tours'], 'readonly');
        const tourStore = tx.objectStore('tours');
        return await new Promise((resolve, reject) => {
            const request = tourStore.get(tourId);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    } catch (error) {
        console.error('Error getting tour data:', error);
        return null;
    }
}

// Generate styles for public view
function generatePublicStyles() {
    return `
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
    `;
}

// Generate tour controls HTML
function generateTourControlsHtml(tour) {
    return `
        <button class="tour-button" id="autorotate-button" title="Toggle Autorotate">
            <i class="fas fa-sync-alt"></i>
        </button>
        <button class="tour-button" id="fullscreen-button" title="Toggle Fullscreen">
            <i class="fas fa-expand"></i>
        </button>
        ${tour.scenes.length > 1 ? `
            <button class="tour-button" id="prev-scene" title="Previous Scene">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button class="tour-button" id="next-scene" title="Next Scene">
                <i class="fas fa-chevron-right"></i>
            </button>
        ` : ''}
    `;
}

// Generate viewer initialization script
function generatePublicViewerScript() {
    return `
        // Initialize viewer
        const viewer = new Marzipano.Viewer(document.getElementById('pano'));
        
        // Create scenes
        const scenes = tourData.scenes.map(sceneData => {
            const source = Marzipano.ImageUrlSource.fromUrl(
                'images/' + sceneData.filename
            );
            const geometry = new Marzipano.EquirectGeometry([{ width: 4000 }]);
            const limiter = Marzipano.RectilinearView.limit.traditional(
                4096,
                100 * Math.PI / 180
            );
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
            sceneData.hotspots.forEach(hotspot => {
                const element = document.createElement('div');
                element.className = 'hotspot';
                element.innerHTML = hotspot.type === 'info' ? 
                    '<i class="fas fa-info"></i>' : 
                    '<i class="fas fa-link"></i>';
                
                scene.hotspotContainer().createHotspot(element, hotspot.position);
                
                // Add tooltip
                element.addEventListener('mouseenter', () => {
                    const tooltip = document.createElement('div');
                    tooltip.className = 'tooltip';
                    tooltip.textContent = hotspot.content || 'Link to another scene';
                    element.appendChild(tooltip);
                });
                
                element.addEventListener('mouseleave', () => {
                    const tooltip = element.querySelector('.tooltip');
                    if (tooltip) tooltip.remove();
                });
                
                if (hotspot.type === 'link') {
                    element.addEventListener('click', () => {
                        const targetScene = scenes.find(s => 
                            s.data.id === hotspot.target
                        );
                        if (targetScene) {
                            targetScene.scene.switchTo();
                        }
                    });
                }
            });
            
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
        
        document.getElementById('autorotate-button')?.addEventListener('click', () => {
            if (viewer.isAutorotating()) {
                viewer.stopMovement();
                viewer.setIdleMovement(null);
            } else {
                viewer.startMovement(autorotate);
                viewer.setIdleMovement(autorotate);
            }
        });
        
        document.getElementById('fullscreen-button')?.addEventListener('click', () => {
            screenfull.toggle();
        });
        
        if (scenes.length > 1) {
            let currentSceneIndex = 0;
            
            document.getElementById('prev-scene')?.addEventListener('click', () => {
                currentSceneIndex = (currentSceneIndex - 1 + scenes.length) % scenes.length;
                scenes[currentSceneIndex].scene.switchTo();
            });
            
            document.getElementById('next-scene')?.addEventListener('click', () => {
                currentSceneIndex = (currentSceneIndex + 1) % scenes.length;
                scenes[currentSceneIndex].scene.switchTo();
            });
        }
    `;
}

// Generate standalone HTML for downloaded package
function generateStandaloneHtml(tourData) {
    return `
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>${escapeHtml(tourData.title)} - Virtual Tour</title>
            <script src="https://cdn.jsdelivr.net/npm/marzipano@0.10.2/dist/marzipano.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/screenfull@5.2.0/dist/screenfull.min.js"></script>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <style>${generatePublicStyles()}</style>
        </head>
        <body>
            <div id="pano"></div>
            <div class="tour-controls">
                ${generateTourControlsHtml(tourData)}
            </div>
            <script>
                const tourData = ${JSON.stringify(tourData)};
                ${generatePublicViewerScript()}
            </script>
        </body>
        </html>
    `;
}

// Save public HTML file
async function savePublicHtml(html, tourId) {
    // This function would typically save the HTML file to your server
    // For now, we'll create a local file using the File System Access API
    try {
        const handle = await window.showSaveFilePicker({
            suggestedName: `virtual-tour-${tourId}.html`,
            types: [{
                description: 'HTML Files',
                accept: {'text/html': ['.html']}
            }],
        });
        
        const writable = await handle.createWritable();
        await writable.write(html);
        await writable.close();
        
        return handle.name;
    } catch (error) {
        console.error('Error saving public HTML:', error);
        throw new Error('Failed to save public HTML file');
    }
}

// Navigation Controls
const navigationControls = {
    backBtn: document.getElementById('back-btn'),
    rotateLeftBtn: document.getElementById('rotate-left-btn'),
    rotateRightBtn: document.getElementById('rotate-right-btn'),
    zoomInBtn: document.getElementById('zoom-in-btn'),
    zoomOutBtn: document.getElementById('zoom-out-btn'),
    fullscreenBtn: document.getElementById('fullscreen-btn')
};

// Navigation button handlers
if (navigationControls.backBtn) {
    navigationControls.backBtn.addEventListener('click', () => sceneManager.goBack());
}

if (navigationControls.rotateLeftBtn) {
    navigationControls.rotateLeftBtn.addEventListener('mousedown', () => {
        const interval = setInterval(() => sceneManager.rotateView(-Math.PI/180, 0), 16);
        const stopRotation = () => clearInterval(interval);
        
        navigationControls.rotateLeftBtn.addEventListener('mouseup', stopRotation, { once: true });
        navigationControls.rotateLeftBtn.addEventListener('mouseleave', stopRotation, { once: true });
    });
}

if (navigationControls.rotateRightBtn) {
    navigationControls.rotateRightBtn.addEventListener('mousedown', () => {
        const interval = setInterval(() => sceneManager.rotateView(Math.PI/180, 0), 16);
        const stopRotation = () => clearInterval(interval);
        
        navigationControls.rotateRightBtn.addEventListener('mouseup', stopRotation, { once: true });
        navigationControls.rotateRightBtn.addEventListener('mouseleave', stopRotation, { once: true });
    });
}

if (navigationControls.zoomInBtn) {
    navigationControls.zoomInBtn.addEventListener('mousedown', () => {
        const interval = setInterval(() => sceneManager.zoomView(-Math.PI/180), 16);
        const stopZoom = () => clearInterval(interval);
        
        navigationControls.zoomInBtn.addEventListener('mouseup', stopZoom, { once: true });
        navigationControls.zoomInBtn.addEventListener('mouseleave', stopZoom, { once: true });
    });
}

if (navigationControls.zoomOutBtn) {
    navigationControls.zoomOutBtn.addEventListener('mousedown', () => {
        const interval = setInterval(() => sceneManager.zoomView(Math.PI/180), 16);
        const stopZoom = () => clearInterval(interval);
        
        navigationControls.zoomOutBtn.addEventListener('mouseup', stopZoom, { once: true });
        navigationControls.zoomOutBtn.addEventListener('mouseleave', stopZoom, { once: true });
    });
}

if (navigationControls.fullscreenBtn) {
    navigationControls.fullscreenBtn.addEventListener('click', () => {
        if (screenfull.isEnabled) {
            screenfull.toggle(document.documentElement);
            const icon = navigationControls.fullscreenBtn.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-expand');
                icon.classList.toggle('fa-compress');
            }
        }
    });
}

// Touch indicators
let touchIndicators = [];

// Only add touch event listeners if we're in a touch-capable environment
if ('ontouchstart' in window) {
    document.addEventListener('touchstart', (e) => {
        Array.from(e.touches).forEach(touch => {
            const indicator = document.createElement('div');
            indicator.className = 'touch-indicator';
            indicator.style.left = touch.clientX + 'px';
            indicator.style.top = touch.clientY + 'px';
            document.body.appendChild(indicator);
            touchIndicators.push(indicator);
            
            setTimeout(() => {
                indicator.remove();
                touchIndicators = touchIndicators.filter(i => i !== indicator);
            }, 500);
        });
    });
}

// Loading indicator
function showLoadingIndicator() {
    const template = document.getElementById('loading-indicator');
    if (template) {
        const indicator = template.content.cloneNode(true);
        document.body.appendChild(indicator);
    }
}

function hideLoadingIndicator() {
    const indicator = document.querySelector('.scene-loading');
    if (indicator) {
        indicator.remove();
    }
}

// Scene change event handler
window.addEventListener('scenechange', (e) => {
    // Update UI elements
    const currentScene = e.detail.newScene;
    
    // Update navigation buttons state
    const backBtn = document.getElementById('back-btn');
    if (backBtn) {
        backBtn.disabled = !e.detail.previousScene;
    }
    
    // Update document title
    document.title = `${currentScene.data.title || 'Untitled Scene'} - 360° Creator @Asmart`;
    
    // Update scene list selection
    const sceneList = document.getElementById('panorama-list');
    if (sceneList) {
        Array.from(sceneList.children).forEach(item => {
            item.classList.toggle('active', item.dataset.sceneId === currentScene.id);
        });
    }
});

// Scene loading event handlers
if (viewer) {
    viewer.addEventListener('sceneChange', () => {
        showLoadingIndicator();
    });

    viewer.addEventListener('renderComplete', () => {
        hideLoadingIndicator();
    });
}

// Export existing functions and variables
export {
    viewer,
    showLoadingIndicator,
    hideLoadingIndicator
};

// Show media hotspot edit modal
function showMediaHotspotModal(hotspot, element) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Media Hotspot</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" id="hotspot-title" value="${hotspot.title || ''}" placeholder="Enter title">
                </div>
                <div class="form-group">
                    <label>Media Type</label>
                    <select id="hotspot-media-type">
                        <option value="image" ${hotspot.mediaType === 'image' ? 'selected' : ''}>Image</option>
                        <option value="video" ${hotspot.mediaType === 'video' ? 'selected' : ''}>Video</option>
                        <option value="audio" ${hotspot.mediaType === 'audio' ? 'selected' : ''}>Audio</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Media URL</label>
                    <input type="url" id="hotspot-media-url" value="${hotspot.mediaUrl || ''}" placeholder="Enter media URL">
                </div>
                <div class="form-group media-options" style="display: none;">
                    <label>Media Options</label>
                    <div class="checkbox-group">
                        <label class="checkbox-button">
                            <input type="checkbox" id="media-autoplay" ${hotspot.mediaOptions.autoplay ? 'checked' : ''}>
                            <span>Autoplay</span>
                        </label>
                        <label class="checkbox-button">
                            <input type="checkbox" id="media-loop" ${hotspot.mediaOptions.loop ? 'checked' : ''}>
                            <span>Loop</span>
                        </label>
                        <label class="checkbox-button">
                            <input type="checkbox" id="media-controls" ${hotspot.mediaOptions.controls ? 'checked' : ''}>
                            <span>Show Controls</span>
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <button class="button" onclick="saveMediaHotspot('${hotspot.id}')">
                        <i class="fas fa-save"></i> Save
                    </button>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
    setTimeout(() => modal.classList.add('show'), 10);

    // Show/hide media options based on media type
    const mediaTypeSelect = modal.querySelector('#hotspot-media-type');
    const mediaOptions = modal.querySelector('.media-options');
    
    mediaTypeSelect.addEventListener('change', () => {
        mediaOptions.style.display = mediaTypeSelect.value !== 'image' ? 'block' : 'none';
    });
    
    // Set initial media options visibility
    mediaOptions.style.display = mediaTypeSelect.value !== 'image' ? 'block' : 'none';

    // Close modal functionality
    modal.querySelector('.close-modal').addEventListener('click', () => {
        modal.classList.remove('show');
        setTimeout(() => modal.remove(), 300);
    });

    // Close on outside click
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('show');
            setTimeout(() => modal.remove(), 300);
        }
    });
}

// Save media hotspot data
function saveMediaHotspot(hotspotId) {
    const title = document.getElementById('hotspot-title').value;
    const mediaType = document.getElementById('hotspot-media-type').value;
    const mediaUrl = document.getElementById('hotspot-media-url').value;
    
    if (!mediaUrl) {
        showNotification('error', 'Please enter a valid media URL');
        return;
    }

    const mediaOptions = {
        autoplay: document.getElementById('media-autoplay')?.checked || false,
        loop: document.getElementById('media-loop')?.checked || false,
        controls: document.getElementById('media-controls')?.checked || false
    };

    const hotspot = findHotspotById(hotspotId);
    if (hotspot) {
        hotspot.title = title;
        hotspot.mediaType = mediaType;
        hotspot.mediaUrl = mediaUrl;
        hotspot.mediaOptions = mediaOptions;
        
        // Update tooltip and preview
        const element = findHotspotElement(hotspot);
        if (element) {
            const tooltip = element.querySelector('.hotspot-tooltip');
            if (tooltip) {
                tooltip.textContent = title || 'Media Hotspot';
            }
            
            // Add media preview for images
            if (mediaType === 'image') {
                const preview = document.createElement('div');
                preview.className = 'media-preview';
                const img = document.createElement('img');
                img.src = mediaUrl;
                preview.appendChild(img);
                element.appendChild(preview);
            }
            
            // Update click handler
            element.onclick = (e) => {
                e.stopPropagation();
                showMediaViewer(hotspot);
            };
        }
        
        showNotification('success', 'Media hotspot updated');
        document.querySelector('.modal').remove();
    }
}

// Show media viewer
function showMediaViewer(hotspot) {
    const viewer = document.createElement('div');
    viewer.className = 'media-viewer';
    
    const content = document.createElement('div');
    content.className = 'media-viewer-content';
    
    const closeBtn = document.createElement('button');
    closeBtn.className = 'media-viewer-close';
    closeBtn.innerHTML = '&times;';
    closeBtn.onclick = () => viewer.remove();
    
    content.appendChild(closeBtn);
    
    switch(hotspot.mediaType) {
        case 'image':
            const img = document.createElement('img');
            img.src = hotspot.mediaUrl;
            content.appendChild(img);
            break;
            
        case 'video':
            const video = document.createElement('video');
            video.src = hotspot.mediaUrl;
            if (hotspot.mediaOptions.autoplay) video.autoplay = true;
            if (hotspot.mediaOptions.loop) video.loop = true;
            if (hotspot.mediaOptions.controls) video.controls = true;
            content.appendChild(video);
            break;
            
        case 'audio':
            const audio = document.createElement('audio');
            audio.src = hotspot.mediaUrl;
            if (hotspot.mediaOptions.autoplay) audio.autoplay = true;
            if (hotspot.mediaOptions.loop) audio.loop = true;
            if (hotspot.mediaOptions.controls) audio.controls = true;
            content.appendChild(audio);
            break;
    }
    
    viewer.appendChild(content);
    document.body.appendChild(viewer);
    
    // Close on background click
    viewer.addEventListener('click', (e) => {
        if (e.target === viewer) {
            viewer.remove();
        }
    });
}

// Make functions globally accessible
window.saveLinkHotspot = saveLinkHotspot;
window.saveInfoHotspot = saveInfoHotspot;
window.saveMediaHotspot = saveMediaHotspot;
window.saveUrlHotspot = saveUrlHotspot;

// Show URL hotspot edit modal
function showUrlHotspotModal(hotspot, element) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit URL Hotspot</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" id="hotspot-title" value="${hotspot.title || ''}" placeholder="Enter title">
                </div>
                <div class="form-group">
                    <label>URL</label>
                    <input type="url" id="hotspot-url" value="${hotspot.url || ''}" placeholder="Enter URL (e.g., https://example.com)">
                </div>
                <div class="form-group">
                    <label>Open in</label>
                    <select id="hotspot-target">
                        <option value="_blank" ${hotspot.target === '_blank' ? 'selected' : ''}>New Window</option>
                        <option value="_self" ${hotspot.target === '_self' ? 'selected' : ''}>Same Window</option>
                    </select>
                </div>
                <div class="form-group">
                    <button class="button" onclick="saveUrlHotspot('${hotspot.id}')">
                        <i class="fas fa-save"></i> Save
                    </button>
                </div>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
    setTimeout(() => modal.classList.add('show'), 10);

    // Close modal functionality
    modal.querySelector('.close-modal').addEventListener('click', () => {
        modal.classList.remove('show');
        setTimeout(() => modal.remove(), 300);
    });

    // Close on outside click
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('show');
            setTimeout(() => modal.remove(), 300);
        }
    });
}

// Save URL hotspot data
function saveUrlHotspot(hotspotId) {
    const title = document.getElementById('hotspot-title').value;
    const url = document.getElementById('hotspot-url').value;
    const target = document.getElementById('hotspot-target').value;
    
    if (!url) {
        showNotification('error', 'Please enter a valid URL');
        return;
    }

    // Validate URL format
    try {
        new URL(url);
    } catch (e) {
        showNotification('error', 'Please enter a valid URL with http:// or https://');
        return;
    }

    const hotspot = findHotspotById(hotspotId);
    if (hotspot) {
        hotspot.title = title;
        hotspot.url = url;
        hotspot.target = target;
        
        // Update tooltip
        const element = findHotspotElement(hotspot);
        if (element) {
            const tooltip = element.querySelector('.hotspot-tooltip');
            if (tooltip) {
                tooltip.textContent = title || url;
            }
            
            // Update click handler
            element.onclick = (e) => {
                e.stopPropagation();
                window.open(url, target);
            };
        }
        
        showNotification('success', 'URL hotspot updated');
        document.querySelector('.modal').remove();
    }
}

// Audio Narration System
class AudioManager {
    constructor() {
        this.audioElements = new Map();
        this.currentAudio = null;
        this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
        this.setupEventListeners();
    }

    setupEventListeners() {
        document.getElementById('add-audio-btn').addEventListener('click', () => {
            document.getElementById('audio-input').click();
        });

        document.getElementById('audio-input').addEventListener('change', (e) => {
            this.handleAudioUpload(e.target.files[0]);
        });
    }

    async handleAudioUpload(file) {
        try {
            const audioBuffer = await this.loadAudioFile(file);
            const audioId = 'audio_' + Date.now();
            
            this.audioElements.set(audioId, {
                buffer: audioBuffer,
                file: file,
                title: file.name,
                duration: Math.round(audioBuffer.duration)
            });

            this.addAudioToList(audioId);
            showNotification('success', 'Audio added successfully');
        } catch (error) {
            console.error('Error loading audio:', error);
            showNotification('error', 'Failed to load audio file');
        }
    }

    async loadAudioFile(file) {
        const arrayBuffer = await file.arrayBuffer();
        return await this.audioContext.decodeAudioData(arrayBuffer);
    }

    addAudioToList(audioId) {
        const audioData = this.audioElements.get(audioId);
        const audioList = document.getElementById('audio-list');
        
        const audioItem = document.createElement('div');
        audioItem.className = 'audio-item';
        audioItem.innerHTML = `
            <div class="audio-info">
                <div class="audio-title">${audioData.title}</div>
                <div class="audio-duration">${this.formatDuration(audioData.duration)}</div>
            </div>
            <div class="audio-controls">
                <button class="audio-btn play-btn" data-audio-id="${audioId}">
                    <i class="fas fa-play"></i>
                </button>
                <button class="audio-btn delete-btn" data-audio-id="${audioId}">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;

        audioList.appendChild(audioItem);
        this.setupAudioControls(audioId);
    }

    setupAudioControls(audioId) {
        const playBtn = document.querySelector(`.play-btn[data-audio-id="${audioId}"]`);
        const deleteBtn = document.querySelector(`.delete-btn[data-audio-id="${audioId}"]`);

        playBtn.addEventListener('click', () => this.toggleAudio(audioId));
        deleteBtn.addEventListener('click', () => this.deleteAudio(audioId));
    }

    async toggleAudio(audioId) {
        if (this.currentAudio) {
            this.currentAudio.stop();
            if (this.currentAudioId === audioId) {
                this.currentAudio = null;
                this.currentAudioId = null;
                return;
            }
        }

        const audioData = this.audioElements.get(audioId);
        const source = this.audioContext.createBufferSource();
        source.buffer = audioData.buffer;
        source.connect(this.audioContext.destination);
        source.start(0);

        this.currentAudio = source;
        this.currentAudioId = audioId;

        source.onended = () => {
            this.currentAudio = null;
            this.currentAudioId = null;
            document.querySelector(`.play-btn[data-audio-id="${audioId}"] i`).className = 'fas fa-play';
        };

        document.querySelector(`.play-btn[data-audio-id="${audioId}"] i`).className = 'fas fa-pause';
    }

    deleteAudio(audioId) {
        if (this.currentAudioId === audioId && this.currentAudio) {
            this.currentAudio.stop();
        }
        this.audioElements.delete(audioId);
        document.querySelector(`.audio-item:has(.play-btn[data-audio-id="${audioId}"])`).remove();
    }

    formatDuration(seconds) {
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = Math.floor(seconds % 60);
        return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
    }
}

// Measurement System
class MeasurementSystem {
    constructor(viewer) {
        this.viewer = viewer;
        this.points = [];
        this.lines = [];
        this.areas = [];
        this.isActive = false;
        this.measurementType = null;
        this.currentUnit = 'meters';
        this.setupEventListeners();
    }

    setupEventListeners() {
        document.getElementById('measure-distance-btn').addEventListener('click', () => {
            this.startMeasurement('distance');
        });

        document.getElementById('measure-area-btn').addEventListener('click', () => {
            this.startMeasurement('area');
        });

        document.getElementById('measurement-unit').addEventListener('change', (e) => {
            this.currentUnit = e.target.value;
            this.updateMeasurements();
        });
    }

    startMeasurement(type) {
        this.isActive = true;
        this.measurementType = type;
        this.points = [];
        this.clearMeasurements();
        
        document.getElementById('measure-distance-btn').classList.toggle('active', type === 'distance');
        document.getElementById('measure-area-btn').classList.toggle('active', type === 'area');
        document.getElementById('measurement-info').style.display = 'block';
    }

    handleClick(event) {
        if (!this.isActive) return;

        const coords = this.viewer.view().screenToCoordinates(event.clientX, event.clientY);
        this.addPoint(coords);

        if (this.measurementType === 'distance' && this.points.length === 2) {
            this.calculateDistance();
            this.isActive = false;
        } else if (this.measurementType === 'area' && this.points.length >= 3) {
            this.calculateArea();
            if (event.detail === 2) { // Double click to finish area
                this.isActive = false;
            }
        }
    }

    addPoint(coords) {
        const point = document.createElement('div');
        point.className = 'measurement-point';
        point.style.left = coords.x + 'px';
        point.style.top = coords.y + 'px';
        this.viewer.container().appendChild(point);
        this.points.push({ element: point, coords: coords });

        if (this.points.length > 1) {
            this.drawLine(this.points[this.points.length - 2], this.points[this.points.length - 1]);
        }
    }

    drawLine(start, end) {
        const dx = end.coords.x - start.coords.x;
        const dy = end.coords.y - start.coords.y;
        const distance = Math.sqrt(dx * dx + dy * dy);
        const angle = Math.atan2(dy, dx);

        const line = document.createElement('div');
        line.className = 'measurement-line';
        line.style.left = start.coords.x + 'px';
        line.style.top = start.coords.y + 'px';
        line.style.width = distance + 'px';
        line.style.transform = `rotate(${angle}rad)`;

        this.viewer.container().appendChild(line);
        this.lines.push(line);
    }

    calculateDistance() {
        const dx = this.points[1].coords.x - this.points[0].coords.x;
        const dy = this.points[1].coords.y - this.points[0].coords.y;
        const distance = Math.sqrt(dx * dx + dy * dy);
        
        const convertedDistance = this.convertDistance(distance);
        this.showMeasurement(`Distance: ${convertedDistance.toFixed(2)} ${this.currentUnit}`);
    }

    calculateArea() {
        if (this.points.length < 3) return;

        let area = 0;
        for (let i = 0; i < this.points.length; i++) {
            const j = (i + 1) % this.points.length;
            area += this.points[i].coords.x * this.points[j].coords.y;
            area -= this.points[j].coords.x * this.points[i].coords.y;
        }
        area = Math.abs(area) / 2;

        const convertedArea = this.convertArea(area);
        this.showMeasurement(`Area: ${convertedArea.toFixed(2)} ${this.currentUnit}²`);
    }

    convertDistance(pixels) {
        // Convert pixels to meters (approximate conversion)
        const metersPerPixel = 0.01; // This should be calibrated based on your scene
        const meters = pixels * metersPerPixel;

        switch (this.currentUnit) {
            case 'feet': return meters * 3.28084;
            case 'inches': return meters * 39.3701;
            default: return meters;
        }
    }

    convertArea(pixelsSquared) {
        const metersSquared = this.convertDistance(Math.sqrt(pixelsSquared)) ** 2;
        
        switch (this.currentUnit) {
            case 'feet': return metersSquared * 10.7639;
            case 'inches': return metersSquared * 1550.0031;
            default: return metersSquared;
        }
    }

    showMeasurement(text) {
        document.querySelector('.measurement-result').textContent = text;
    }

    clearMeasurements() {
        this.points.forEach(point => point.element.remove());
        this.lines.forEach(line => line.remove());
        this.areas.forEach(area => area.remove());
        this.points = [];
        this.lines = [];
        this.areas = [];
    }

    updateMeasurements() {
        if (this.measurementType === 'distance' && this.points.length === 2) {
            this.calculateDistance();
        } else if (this.measurementType === 'area' && this.points.length >= 3) {
            this.calculateArea();
        }
    }
}

// Initialize systems when viewer is ready
function initializeAdvancedFeatures(viewer) {
    const audioManager = new AudioManager();
    const measurementSystem = new MeasurementSystem(viewer);

    // Enable measurement buttons when a panorama is loaded
    viewer.addEventListener('sceneChange', () => {
        document.getElementById('measure-distance-btn').disabled = false;
        document.getElementById('measure-area-btn').disabled = false;
    });

    // Handle clicks for measurements
    viewer.container().addEventListener('click', (e) => {
        measurementSystem.handleClick(e);
    });
}

// Performance Optimizations
class PerformanceOptimizer {
    constructor() {
        this.imageCache = new Map();
        this.audioCache = new Map();
        this.maxCacheSize = 100 * 1024 * 1024; // 100MB cache limit
        this.currentCacheSize = 0;
        this.setupIntersectionObserver();
    }

    setupIntersectionObserver() {
        this.observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const element = entry.target;
                    if (element.dataset.src) {
                        this.loadImage(element);
                    }
                }
            });
        }, {
            root: null,
            rootMargin: '50px',
            threshold: 0.1
        });
    }

    async loadImage(element) {
        const src = element.dataset.src;
        try {
            const cachedImage = this.imageCache.get(src);
            if (cachedImage) {
                element.src = cachedImage;
            } else {
                const response = await fetch(src);
                const blob = await response.blob();
                const objectUrl = URL.createObjectURL(blob);
                
                this.addToCache(src, objectUrl, blob.size);
                element.src = objectUrl;
            }
            element.removeAttribute('data-src');
            this.observer.unobserve(element);
        } catch (error) {
            console.error('Error loading image:', error);
        }
    }

    addToCache(key, value, size) {
        while (this.currentCacheSize + size > this.maxCacheSize && this.imageCache.size > 0) {
            const oldestKey = this.imageCache.keys().next().value;
            this.removeFromCache(oldestKey);
        }

        this.imageCache.set(key, value);
        this.currentCacheSize += size;
    }

    removeFromCache(key) {
        const objectUrl = this.imageCache.get(key);
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            this.imageCache.delete(key);
            // Approximate size reduction (actual size tracking would require more overhead)
            this.currentCacheSize = Math.max(0, this.currentCacheSize - 1024 * 1024);
        }
    }

    observeImage(element) {
        if ('IntersectionObserver' in window) {
            this.observer.observe(element);
        } else {
            // Fallback for browsers that don't support IntersectionObserver
            this.loadImage(element);
        }
    }

    clearCache() {
        this.imageCache.forEach((objectUrl) => {
            URL.revokeObjectURL(objectUrl);
        });
        this.imageCache.clear();
        this.currentCacheSize = 0;
    }
}

// Initialize performance optimizer
const performanceOptimizer = new PerformanceOptimizer();

// Modify the panorama loading function to use lazy loading
async function loadPanorama(file) {
    try {
        const imageUrl = URL.createObjectURL(file);
        const image = document.createElement('img');
        image.dataset.src = imageUrl;
        
        // Add to lazy loading queue
        performanceOptimizer.observeImage(image);

        // Create a low-resolution preview immediately
        const preview = await createPreview(file);
        showPreview(preview);

        // Wait for the full image to load
        await new Promise((resolve, reject) => {
            image.onload = resolve;
            image.onerror = reject;
        });

        // Create and display the panorama
        createPanoramaScene(image);
        
        showNotification('success', 'Panorama loaded successfully');
    } catch (error) {
        console.error('Error loading panorama:', error);
        showNotification('error', 'Failed to load panorama');
    }
}

// Create a low-resolution preview
async function createPreview(file) {
    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onload = (e) => {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                
                // Create a small preview
                canvas.width = 256;
                canvas.height = 128;
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                
                resolve(canvas.toDataURL('image/jpeg', 0.5));
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });
}

// Show preview while loading
function showPreview(previewUrl) {
    const previewElement = document.createElement('div');
    previewElement.className = 'panorama-preview';
    previewElement.style.backgroundImage = `url(${previewUrl})`;
    document.getElementById('pano').appendChild(previewElement);
}

// Memory management
function cleanupUnusedResources() {
    performanceOptimizer.clearCache();
    if (window.gc) window.gc(); // Request garbage collection if available
}

// Add event listener for visibility change to optimize memory usage
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        cleanupUnusedResources();
    }
});

// Export necessary functions to global scope for HTML access
window.generatePublicView = generatePublicView;
window.saveTourToDatabase = saveTourToDatabase;
window.shareToSocial = shareToSocial;
window.downloadTourPackage = downloadTourPackage;
window.downloadTourJSON = downloadTourJSON;

// Add hotspot context menu
function showHotspotContextMenu(hotspot, x, y) {
    // Remove any existing context menu
    removeContextMenu();

    const menu = document.createElement('div');
    menu.className = 'context-menu';
    menu.style.left = `${x}px`;
    menu.style.top = `${y}px`;

    menu.innerHTML = `
        <div class="context-menu-item" onclick="editHotspot('${hotspot.id}')">
            <i class="fas fa-edit"></i> Edit
        </div>
        <div class="context-menu-item" onclick="deleteHotspot('${hotspot.id}')">
            <i class="fas fa-trash"></i> Delete
        </div>
        <div class="context-menu-item" onclick="refreshHotspot('${hotspot.id}')">
            <i class="fas fa-sync-alt"></i> Refresh
        </div>
    `;

    document.body.appendChild(menu);

    // Close menu on outside click
    document.addEventListener('click', removeContextMenu);
}

function removeContextMenu() {
    const existingMenu = document.querySelector('.context-menu');
    if (existingMenu) {
        existingMenu.remove();
    }
    document.removeEventListener('click', removeContextMenu);
}

// Add context menu to hotspot element
function addHotspotContextMenu(hotspotElement, hotspot) {
    hotspotElement.addEventListener('contextmenu', (e) => {
        e.preventDefault();
        showHotspotContextMenu(hotspot, e.pageX, e.pageY);
    });
}

// Hotspot management functions
function editHotspot(hotspotId) {
    const hotspot = findHotspotById(hotspotId);
    if (hotspot) {
        switch(hotspot.type) {
            case 'info':
                showInfoHotspotModal(hotspot);
                break;
            case 'link':
                showLinkHotspotModal(hotspot);
                break;
            case 'media':
                showMediaHotspotModal(hotspot);
                break;
            case 'url':
                showUrlHotspotModal(hotspot);
                break;
        }
    }
}

function deleteHotspot(hotspotId) {
    const hotspot = findHotspotById(hotspotId);
    if (hotspot) {
        const element = findHotspotElement(hotspot);
        if (element) {
            element.remove();
        }
        removeHotspotFromScene(hotspot);
        showNotification('success', 'Hotspot deleted');
    }
}

function refreshHotspot(hotspotId) {
    const hotspot = findHotspotById(hotspotId);
    if (hotspot) {
        updateHotspotPosition(hotspot);
        showNotification('success', 'Hotspot position refreshed');
    }
}

// Panorama upload and management
function handlePanoramaUpload(file) {
    const reader = new FileReader();
    const panoramaItem = createPanoramaListItem(file.name, 'Uploading...');
    
    reader.onprogress = (event) => {
        if (event.lengthComputable) {
            const progress = Math.round((event.loaded / event.total) * 100);
            updatePanoramaProgress(panoramaItem, progress);
        }
    };

    reader.onload = async (e) => {
        try {
            // Create tiles/cube faces
            showNotification('info', 'Processing panorama...');
            await processPanorama(e.target.result, panoramaItem);
            updatePanoramaStatus(panoramaItem, 'Ready');
            addPanoramaControls(panoramaItem);
        } catch (error) {
            console.error('Error processing panorama:', error);
            updatePanoramaStatus(panoramaItem, 'Error');
            showNotification('error', 'Failed to process panorama');
        }
    };

    reader.onerror = () => {
        updatePanoramaStatus(panoramaItem, 'Error');
        showNotification('error', 'Failed to read panorama file');
    };

    reader.readAsDataURL(file);
}

function createPanoramaListItem(filename, status) {
    const item = document.createElement('div');
    item.className = 'panorama-item';
    item.innerHTML = `
        <div class="panorama-info">
            <div class="panorama-name">${filename}</div>
            <div class="panorama-status">${status}</div>
            <div class="progress-bar">
                <div class="progress-fill"></div>
                <div class="progress-text">0%</div>
            </div>
        </div>
        <div class="panorama-controls" style="display: none;">
            <button class="icon-button edit-panorama" title="Edit">
                <i class="fas fa-edit"></i>
            </button>
            <button class="icon-button delete-panorama" title="Delete">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;

    document.getElementById('panorama-list').appendChild(item);
    return item;
}

function updatePanoramaProgress(item, progress) {
    const progressFill = item.querySelector('.progress-fill');
    const progressText = item.querySelector('.progress-text');
    
    progressFill.style.width = `${progress}%`;
    progressText.textContent = `${progress}%`;
    
    if (progress === 100) {
        setTimeout(() => {
            item.querySelector('.progress-bar').style.display = 'none';
        }, 1000);
    }
}

function updatePanoramaStatus(item, status) {
    item.querySelector('.panorama-status').textContent = status;
}

function addPanoramaControls(item) {
    const controls = item.querySelector('.panorama-controls');
    controls.style.display = 'flex';

    // Edit panorama
    controls.querySelector('.edit-panorama').addEventListener('click', () => {
        showPanoramaEditModal(item);
    });

    // Delete panorama
    controls.querySelector('.delete-panorama').addEventListener('click', () => {
        if (confirm('Are you sure you want to delete this panorama?')) {
            item.remove();
            // Additional cleanup if needed
            showNotification('success', 'Panorama deleted');
        }
    });
}

function showPanoramaEditModal(item) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Panorama</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" id="panorama-name" value="${item.querySelector('.panorama-name').textContent}">
                </div>
                <div class="form-group">
                    <label>Initial View</label>
                    <div class="view-settings">
                        <input type="number" id="view-yaw" placeholder="Yaw" step="1">
                        <input type="number" id="view-pitch" placeholder="Pitch" step="1">
                        <input type="number" id="view-fov" placeholder="FOV" step="1">
                    </div>
                </div>
                <button class="button" onclick="savePanoramaSettings(this)">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);
    setTimeout(() => modal.classList.add('show'), 10);

    // Close modal functionality
    const closeModal = () => {
        modal.classList.remove('show');
        setTimeout(() => modal.remove(), 300);
    };

    modal.querySelector('.close-modal').addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });
}

// Add CSS for new elements
const style = document.createElement('style');
style.textContent = `
    .panorama-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px;
        border-bottom: 1px solid #3d3d3d;
    }

    .panorama-info {
        flex: 1;
    }

    .panorama-name {
        font-weight: 500;
        margin-bottom: 5px;
    }

    .panorama-status {
        font-size: 12px;
        color: #999;
    }

    .progress-bar {
        height: 4px;
        background: #3d3d3d;
        border-radius: 2px;
        margin-top: 8px;
        position: relative;
    }

    .progress-fill {
        height: 100%;
        background: #4285f4;
        border-radius: 2px;
        width: 0;
        transition: width 0.3s ease;
    }

    .progress-text {
        position: absolute;
        right: 0;
        top: -20px;
        font-size: 12px;
        color: #999;
    }

    .panorama-controls {
        display: none;
        gap: 8px;
    }

    .view-settings {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
    }

    .view-settings input {
        width: 100%;
        padding: 8px;
        background: #1c1c1c;
        border: 1px solid #3d3d3d;
        border-radius: 4px;
        color: #fff;
    }
`;

document.head.appendChild(style);

// Make functions globally accessible
window.savePanoramaSettings = function(button) {
    const modal = button.closest('.modal');
    const name = modal.querySelector('#panorama-name').value;
    const yaw = parseFloat(modal.querySelector('#view-yaw').value) || 0;
    const pitch = parseFloat(modal.querySelector('#view-pitch').value) || 0;
    const fov = parseFloat(modal.querySelector('#view-fov').value) || 90;

    // Update panorama settings
    // ... implementation depends on your panorama management system

    showNotification('success', 'Panorama settings saved');
    modal.querySelector('.close-modal').click();
};
