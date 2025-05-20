document.addEventListener('DOMContentLoaded', function() {
  // DOM Elements
  const uploadArea = document.getElementById('uploadArea');
  const fileInput = document.getElementById('fileInput');
  const filePreview = document.getElementById('filePreview');
  const previewImage = document.getElementById('previewImage');
  const fileName = document.getElementById('fileName');
  const fileSize = document.getElementById('fileSize');
  const removeFile = document.getElementById('removeFile');
  const convertBtn = document.getElementById('convertBtn');
  const resetBtn = document.getElementById('resetBtn');
  const modelViewer = document.getElementById('modelViewer');
  const previewPlaceholder = document.getElementById('previewPlaceholder');
  const downloadSection = document.getElementById('downloadSection');

  // Three.js Setup
  let scene, camera, renderer, controls, model;
  
  function initThreeJS() {
    // Scene setup
    scene = new THREE.Scene();
    scene.background = new THREE.Color(0xf8f9fa);

    // Camera setup
    camera = new THREE.PerspectiveCamera(75, modelViewer.clientWidth / modelViewer.clientHeight, 0.1, 1000);
    camera.position.z = 5;

    // Renderer setup
    renderer = new THREE.WebGLRenderer({
      canvas: document.getElementById('three-canvas'),
      antialias: true
    });
    renderer.setSize(modelViewer.clientWidth, modelViewer.clientHeight);

    // Controls setup
    controls = new THREE.OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.05;

    // Lighting
    const ambientLight = new THREE.AmbientLight(0xffffff, 0.5);
    scene.add(ambientLight);

    const directionalLight = new THREE.DirectionalLight(0xffffff, 0.5);
    directionalLight.position.set(0, 1, 0);
    scene.add(directionalLight);

    // Animation loop
    function animate() {
      requestAnimationFrame(animate);
      controls.update();
      renderer.render(scene, camera);
    }
    animate();
  }

  // File Upload Handling
  function handleFileUpload(file) {
    if (file) {
      const reader = new FileReader();
      reader.onload = function(e) {
        previewImage.src = e.target.result;
        fileName.textContent = file.name;
        fileSize.textContent = formatFileSize(file.size);
        filePreview.style.display = 'block';
        convertBtn.disabled = false;
      };
      reader.readAsDataURL(file);
    }
  }

  function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  }

  // Drag and Drop
  ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
    uploadArea.addEventListener(eventName, preventDefaults, false);
  });

  function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
  }

  ['dragenter', 'dragover'].forEach(eventName => {
    uploadArea.addEventListener(eventName, highlight, false);
  });

  ['dragleave', 'drop'].forEach(eventName => {
    uploadArea.addEventListener(eventName, unhighlight, false);
  });

  function highlight() {
    uploadArea.classList.add('drag-over');
  }

  function unhighlight() {
    uploadArea.classList.remove('drag-over');
  }

  uploadArea.addEventListener('drop', handleDrop, false);

  function handleDrop(e) {
    const dt = e.dataTransfer;
    const file = dt.files[0];
    handleFileUpload(file);
  }

  // Event Listeners
  fileInput.addEventListener('change', function(e) {
    handleFileUpload(e.target.files[0]);
  });

  removeFile.addEventListener('click', function() {
    filePreview.style.display = 'none';
    fileInput.value = '';
    convertBtn.disabled = true;
  });

  resetBtn.addEventListener('click', function() {
    filePreview.style.display = 'none';
    fileInput.value = '';
    convertBtn.disabled = true;
    modelViewer.style.display = 'none';
    previewPlaceholder.style.display = 'flex';
    downloadSection.style.display = 'none';
  });

  convertBtn.addEventListener('click', async function() {
    const spinner = convertBtn.querySelector('.spinner-border');
    const buttonText = convertBtn.querySelector('.button-text');
    
    // Show loading state
    spinner.classList.remove('d-none');
    buttonText.textContent = 'Converting...';
    convertBtn.disabled = true;

    try {
      // Simulate conversion delay (replace with actual conversion logic)
      await new Promise(resolve => setTimeout(resolve, 2000));

      // Initialize Three.js viewer
      modelViewer.style.display = 'block';
      previewPlaceholder.style.display = 'none';
      downloadSection.style.display = 'block';
      
      if (!renderer) {
        initThreeJS();
      }

      // Add a sample 3D model (replace with actual converted model)
      const geometry = new THREE.BoxGeometry();
      const material = new THREE.MeshPhongMaterial({ color: 0xff6b2b });
      const cube = new THREE.Mesh(geometry, material);
      scene.add(cube);

      // Reset camera position
      camera.position.z = 5;
      controls.reset();

    } catch (error) {
      console.error('Conversion failed:', error);
      alert('Failed to convert the image. Please try again.');
    } finally {
      // Reset button state
      spinner.classList.add('d-none');
      buttonText.textContent = 'Convert to 3D';
      convertBtn.disabled = false;
    }
  });

  // Viewer Controls
  document.getElementById('resetView').addEventListener('click', function() {
    if (controls) {
      camera.position.set(0, 0, 5);
      controls.reset();
    }
  });

  document.getElementById('zoomIn').addEventListener('click', function() {
    if (camera) {
      camera.position.z = Math.max(camera.position.z - 1, 2);
    }
  });

  document.getElementById('zoomOut').addEventListener('click', function() {
    if (camera) {
      camera.position.z = Math.min(camera.position.z + 1, 10);
    }
  });

  // Handle window resize
  window.addEventListener('resize', function() {
    if (renderer && camera) {
      const width = modelViewer.clientWidth;
      const height = modelViewer.clientHeight;
      
      camera.aspect = width / height;
      camera.updateProjectionMatrix();
      renderer.setSize(width, height);
    }
  });

  // Download buttons
  document.querySelectorAll('.download-options button').forEach(button => {
    button.addEventListener('click', function() {
      const format = this.dataset.format;
      // Implement actual download logic here
      alert(`Downloading model in ${format.toUpperCase()} format...`);
    });
  });
});