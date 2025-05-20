// Scene Manager Class
class SceneManager {
    constructor() {
        this.scenes = [];
        this.currentScene = null;
        this.viewer = null;
        this.transitionDuration = 1000;
        this.transitionEffect = 'fade';
        this.compass = null;
        this.minimap = null;
        this.gyroscopeEnabled = false;
        this.levelIndicator = null;
        this.pipCanvas = null;
        this.pipContext = null;
        this.pipEnabled = false;
    }

    initialize(viewer) {
        if (!viewer) {
            throw new Error('Viewer not provided to SceneManager.initialize()');
        }
        this.viewer = viewer;
        
        // Only setup controls if viewer is available
        if (this.viewer) {
            this.setupCompass();
            this.setupMinimap();
            this.setupKeyboardControls();
            this.setupTouchControls();
            this.setupGyroscope();
            this.setupLevelIndicator();
            this.setupPictureInPicture();
            
            // Setup view preset buttons
            document.querySelectorAll('.preset-btn').forEach(button => {
                button.addEventListener('click', () => {
                    const preset = button.dataset.preset;
                    this.setViewPreset(preset);
                });
            });
        }
    }

    // Scene Management
    addScene(sceneData) {
        const source = Marzipano.ImageUrlSource.fromString(sceneData.imageUrl);
        const geometry = new Marzipano.EquirectGeometry([{ width: 4000 }]);
        
        // Create view with enhanced limits
        const limiter = Marzipano.util.compose(
            Marzipano.RectilinearView.limit.traditional(4096, 120 * Math.PI / 180),
            Marzipano.RectilinearView.limit.vfov(30 * Math.PI / 180, 120 * Math.PI / 180)
        );
        
        const view = new Marzipano.RectilinearView({
            yaw: sceneData.initialViewParameters?.yaw || 0,
            pitch: sceneData.initialViewParameters?.pitch || 0,
            fov: sceneData.initialViewParameters?.fov || Math.PI / 2
        }, limiter);

        // Create scene with enhanced options
        const scene = this.viewer.createScene({
            source: source,
            geometry: geometry,
            view: view,
            pinFirstLevel: true,
            transitionDuration: this.transitionDuration
        });

        const sceneInfo = {
            id: sceneData.id,
            scene: scene,
            view: view,
            data: sceneData,
            hotspots: []
        };

        this.scenes.push(sceneInfo);
        return sceneInfo;
    }

    switchScene(sceneId, options = {}) {
        const targetScene = this.scenes.find(s => s.id === sceneId);
        if (!targetScene) return;

        const transitionEffect = options.effect || this.transitionEffect;
        const duration = options.duration || this.transitionDuration;

        // Store current view parameters for back navigation
        if (this.currentScene) {
            this.currentScene.lastView = {
                yaw: this.currentScene.view.yaw(),
                pitch: this.currentScene.view.pitch(),
                fov: this.currentScene.view.fov()
            };
        }

        // Apply transition effect
        switch (transitionEffect) {
            case 'fade':
                this.applyFadeTransition(targetScene, duration);
                break;
            case 'crossfade':
                this.applyCrossfadeTransition(targetScene, duration);
                break;
            case 'slide':
                this.applySlideTransition(targetScene, duration);
                break;
            default:
                targetScene.scene.switchTo();
        }

        this.currentScene = targetScene;
        this.updateCompass();
        this.updateMinimap();
        
        // Emit scene change event
        const event = new CustomEvent('scenechange', {
            detail: { 
                previousScene: this.currentScene,
                newScene: targetScene
            }
        });
        window.dispatchEvent(event);
    }

    // Transition Effects
    applyFadeTransition(targetScene, duration) {
        const overlay = document.createElement('div');
        overlay.className = 'transition-overlay';
        document.body.appendChild(overlay);

        // Fade out
        overlay.style.opacity = '1';
        setTimeout(() => {
            targetScene.scene.switchTo({ transitionDuration: 0 });
            
            // Fade in
            overlay.style.opacity = '0';
            setTimeout(() => {
                overlay.remove();
            }, duration);
        }, duration);
    }

    applyCrossfadeTransition(targetScene, duration) {
        if (this.currentScene) {
            const currentContainer = this.currentScene.scene.domElement();
            const targetContainer = targetScene.scene.domElement();
            
            // Setup crossfade
            currentContainer.style.zIndex = '1';
            targetContainer.style.zIndex = '2';
            targetContainer.style.opacity = '0';

            // Switch to new scene without transition
            targetScene.scene.switchTo({ transitionDuration: 0 });

            // Animate crossfade
            setTimeout(() => {
                targetContainer.style.opacity = '1';
                targetContainer.style.transition = `opacity ${duration}ms ease`;
                
                setTimeout(() => {
                    currentContainer.style.zIndex = '';
                    targetContainer.style.zIndex = '';
                    targetContainer.style.transition = '';
                }, duration);
            }, 0);
        } else {
            targetScene.scene.switchTo();
        }
    }

    applySlideTransition(targetScene, duration) {
        if (this.currentScene) {
            const currentContainer = this.currentScene.scene.domElement();
            const targetContainer = targetScene.scene.domElement();
            
            // Setup slide
            currentContainer.style.transform = 'translateX(0)';
            targetContainer.style.transform = 'translateX(100%)';
            targetContainer.style.transition = `transform ${duration}ms ease`;
            currentContainer.style.transition = `transform ${duration}ms ease`;

            // Switch to new scene without transition
            targetScene.scene.switchTo({ transitionDuration: 0 });

            // Animate slide
            setTimeout(() => {
                currentContainer.style.transform = 'translateX(-100%)';
                targetContainer.style.transform = 'translateX(0)';
                
                setTimeout(() => {
                    currentContainer.style.transform = '';
                    targetContainer.style.transform = '';
                    currentContainer.style.transition = '';
                    targetContainer.style.transition = '';
                }, duration);
            }, 0);
        } else {
            targetScene.scene.switchTo();
        }
    }

    // Navigation Controls
    setupKeyboardControls() {
        document.addEventListener('keydown', (e) => {
            switch(e.key) {
                case 'ArrowLeft':
                    this.rotateView(-Math.PI/18, 0);
                    break;
                case 'ArrowRight':
                    this.rotateView(Math.PI/18, 0);
                    break;
                case 'ArrowUp':
                    this.rotateView(0, Math.PI/18);
                    break;
                case 'ArrowDown':
                    this.rotateView(0, -Math.PI/18);
                    break;
                case '+':
                case '=':
                    this.zoomView(-Math.PI/18);
                    break;
                case '-':
                case '_':
                    this.zoomView(Math.PI/18);
                    break;
            }
        });
    }

    setupTouchControls() {
        if (!this.viewer) return;
        
        let touchStartX = 0;
        let touchStartY = 0;
        let lastTouchDistance = 0;

        const container = this.viewer.domElement();
        if (!container) return;

        container.addEventListener('touchstart', (e) => {
            if (e.touches.length === 1) {
                touchStartX = e.touches[0].clientX;
                touchStartY = e.touches[0].clientY;
            } else if (e.touches.length === 2) {
                lastTouchDistance = Math.hypot(
                    e.touches[0].clientX - e.touches[1].clientX,
                    e.touches[0].clientY - e.touches[1].clientY
                );
            }
        });

        container.addEventListener('touchmove', (e) => {
            if (e.touches.length === 1) {
                const touchX = e.touches[0].clientX;
                const touchY = e.touches[0].clientY;
                
                const deltaX = touchX - touchStartX;
                const deltaY = touchY - touchStartY;
                
                this.rotateView(
                    -deltaX * Math.PI/180,
                    -deltaY * Math.PI/180
                );
                
                touchStartX = touchX;
                touchStartY = touchY;
            } else if (e.touches.length === 2) {
                const touchDistance = Math.hypot(
                    e.touches[0].clientX - e.touches[1].clientX,
                    e.touches[0].clientY - e.touches[1].clientY
                );
                
                const deltaDistance = touchDistance - lastTouchDistance;
                this.zoomView(-deltaDistance * Math.PI/900);
                
                lastTouchDistance = touchDistance;
            }
            e.preventDefault();
        });
    }

    rotateView(deltaYaw, deltaPitch) {
        if (!this.currentScene) return;
        
        const view = this.currentScene.view;
        view.setYaw(view.yaw() + deltaYaw);
        view.setPitch(view.pitch() + deltaPitch);
        
        this.updateCompass();
    }

    zoomView(deltaFov) {
        if (!this.currentScene) return;
        
        const view = this.currentScene.view;
        view.setFov(Math.max(Math.PI/6, Math.min(Math.PI, view.fov() + deltaFov)));
    }

    // Compass
    setupCompass() {
        this.compass = document.createElement('div');
        this.compass.className = 'compass';
        this.compass.innerHTML = `
            <div class="compass-ring"></div>
            <div class="compass-arrow"></div>
            <div class="compass-labels">
                <span class="compass-n">N</span>
                <span class="compass-e">E</span>
                <span class="compass-s">S</span>
                <span class="compass-w">W</span>
            </div>
        `;
        
        document.querySelector('#viewer-container').appendChild(this.compass);
    }

    updateCompass() {
        if (!this.currentScene || !this.compass) return;
        
        const yaw = this.currentScene.view.yaw();
        this.compass.style.setProperty('--compass-rotation', `${(yaw * 180 / Math.PI)}deg`);
    }

    // Minimap
    setupMinimap() {
        this.minimap = document.createElement('div');
        this.minimap.className = 'minimap';
        document.querySelector('#viewer-container').appendChild(this.minimap);
        
        this.updateMinimap();
    }

    updateMinimap() {
        if (!this.minimap) return;

        // Create SVG for scene connections
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('width', '100%');
        svg.setAttribute('height', '100%');

        // Draw connections between scenes
        this.scenes.forEach(scene => {
            scene.hotspots.forEach(hotspot => {
                if (hotspot.type === 'link') {
                    const targetScene = this.scenes.find(s => s.id === hotspot.target);
                    if (targetScene) {
                        const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                        line.setAttribute('x1', `${scene.data.x}%`);
                        line.setAttribute('y1', `${scene.data.y}%`);
                        line.setAttribute('x2', `${targetScene.data.x}%`);
                        line.setAttribute('y2', `${targetScene.data.y}%`);
                        line.setAttribute('stroke', '#fff');
                        line.setAttribute('stroke-width', '2');
                        svg.appendChild(line);
                    }
                }
            });
        });

        // Create scene markers
        const markers = this.scenes.map(scene => `
            <div class="minimap-marker ${scene === this.currentScene ? 'active' : ''}"
                 style="left: ${scene.data.x}%; top: ${scene.data.y}%"
                 title="${scene.data.title || 'Untitled Scene'}"
                 onclick="sceneManager.switchScene('${scene.id}')">
            </div>
        `).join('');

        this.minimap.innerHTML = svg.outerHTML + markers;
    }

    // Scene History
    goBack() {
        if (this.sceneHistory && this.sceneHistory.length > 1) {
            this.sceneHistory.pop(); // Remove current scene
            const previousScene = this.sceneHistory.pop(); // Get previous scene
            if (previousScene) {
                this.switchScene(previousScene.id, {
                    yaw: previousScene.lastView?.yaw,
                    pitch: previousScene.lastView?.pitch,
                    fov: previousScene.lastView?.fov
                });
            }
        }
    }

    // Utility Methods
    setTransitionOptions(options) {
        if (options.duration !== undefined) {
            this.transitionDuration = options.duration;
        }
        if (options.effect !== undefined) {
            this.transitionEffect = options.effect;
        }
    }

    getSceneById(sceneId) {
        return this.scenes.find(scene => scene.id === sceneId);
    }

    getCurrentScene() {
        return this.currentScene;
    }

    setupGyroscope() {
        if (!window.DeviceOrientationEvent) {
            console.warn('Gyroscope not supported on this device');
            return;
        }

        const handleOrientation = (event) => {
            if (!this.gyroscopeEnabled || !this.currentScene) return;

            const view = this.currentScene.view;
            
            // Convert device orientation to view angles
            const pitch = -event.beta * Math.PI / 180;  // Convert to radians
            const yaw = -event.alpha * Math.PI / 180;   // Convert to radians
            
            view.setYaw(yaw);
            view.setPitch(Math.max(-Math.PI/2, Math.min(Math.PI/2, pitch)));
            
            this.updateCompass();
            this.updateLevelIndicator();
        };

        // Request permission for iOS 13+ devices
        if (typeof DeviceOrientationEvent.requestPermission === 'function') {
            document.getElementById('gyroscope-btn').addEventListener('click', async () => {
                try {
                    const permission = await DeviceOrientationEvent.requestPermission();
                    if (permission === 'granted') {
                        window.addEventListener('deviceorientation', handleOrientation);
                        this.gyroscopeEnabled = true;
                    }
                } catch (error) {
                    console.error('Error requesting gyroscope permission:', error);
                }
            });
        } else {
            window.addEventListener('deviceorientation', handleOrientation);
        }
    }

    setupLevelIndicator() {
        this.levelIndicator = document.createElement('div');
        this.levelIndicator.className = 'level-indicator';
        this.levelIndicator.innerHTML = `
            <div class="level-bubble"></div>
            <div class="level-line"></div>
        `;
        
        document.querySelector('#viewer-container').appendChild(this.levelIndicator);
    }

    updateLevelIndicator() {
        if (!this.levelIndicator || !this.currentScene) return;
        
        const view = this.currentScene.view;
        const pitch = view.pitch();
        const yaw = view.yaw();
        
        // Calculate bubble position based on view angles
        const maxOffset = 20; // Maximum pixel offset for the bubble
        const xOffset = Math.sin(yaw) * maxOffset;
        const yOffset = Math.sin(pitch) * maxOffset;
        
        const bubble = this.levelIndicator.querySelector('.level-bubble');
        bubble.style.transform = `translate(${xOffset}px, ${yOffset}px)`;
        
        // Change color when level
        const isLevel = Math.abs(pitch) < 0.05 && Math.abs(yaw % (Math.PI * 2)) < 0.05;
        bubble.classList.toggle('level', isLevel);
    }

    setViewPreset(preset) {
        if (!this.currentScene) return;
        
        const view = this.currentScene.view;
        const transitions = {
            top: { yaw: 0, pitch: Math.PI/2, fov: Math.PI/2 },
            bottom: { yaw: 0, pitch: -Math.PI/2, fov: Math.PI/2 },
            front: { yaw: 0, pitch: 0, fov: Math.PI/2 },
            left: { yaw: -Math.PI/2, pitch: 0, fov: Math.PI/2 },
            right: { yaw: Math.PI/2, pitch: 0, fov: Math.PI/2 },
            back: { yaw: Math.PI, pitch: 0, fov: Math.PI/2 }
        };
        
        const target = transitions[preset];
        if (!target) return;
        
        // Animate to new view position
        view.animateTo(target, {
            transitionDuration: 1000,
            transitionUpdate: () => {
                this.updateCompass();
                this.updateLevelIndicator();
            }
        });
    }

    setupPictureInPicture() {
        this.pipCanvas = document.getElementById('pip-canvas');
        if (!this.pipCanvas) return;
        
        this.pipContext = this.pipCanvas.getContext('2d');
        this.pipEnabled = true;
        
        // Set initial canvas size
        this.resizePipCanvas();
        
        // Handle window resize
        window.addEventListener('resize', () => this.resizePipCanvas());
        
        // Start rendering loop
        this.renderPipPreview();
    }

    resizePipCanvas() {
        if (!this.pipCanvas) return;
        
        const container = this.pipCanvas.parentElement;
        const rect = container.getBoundingClientRect();
        
        this.pipCanvas.width = rect.width * window.devicePixelRatio;
        this.pipCanvas.height = rect.height * window.devicePixelRatio;
        this.pipCanvas.style.width = rect.width + 'px';
        this.pipCanvas.style.height = rect.height + 'px';
    }

    renderPipPreview() {
        if (!this.pipEnabled || !this.currentScene) return;
        
        // Calculate preview camera position
        const view = this.currentScene.view;
        const previewYaw = view.yaw() + Math.PI; // Opposite direction
        const previewPitch = 0; // Keep level
        const previewFov = Math.PI / 2;
        
        // Create a temporary view for the preview
        const previewView = {
            yaw: () => previewYaw,
            pitch: () => previewPitch,
            fov: () => previewFov
        };
        
        // Render the preview
        this.currentScene.scene.render({
            view: previewView,
            canvas: this.pipCanvas
        });
        
        // Continue rendering loop
        requestAnimationFrame(() => this.renderPipPreview());
    }
}

// Export singleton instance
const sceneManager = new SceneManager();
export default sceneManager; 