document.addEventListener('DOMContentLoaded', function() {


  // Property Modal Functionality
  const propertyModal = new bootstrap.Modal(document.getElementById('propertyViewerModal'));
  const galleryItems = document.querySelectorAll('.gallery-item');
  
  galleryItems.forEach(item => {
    item.addEventListener('click', function(e) {
      // Don't open modal if clicking like or more options buttons
      if (e.target.closest('.likes') || e.target.closest('.more-options')) {
        return;
      }

      const card = this.querySelector('.property-card');
      const userInfo = card.querySelector('.user-info');
      const propertyType = card.querySelector('.property-type');
      const engagement = card.querySelector('.engagement');
      const iframeSrc = card.getAttribute('data-iframe-src');

      // Update modal content
      const modal = document.getElementById('propertyViewerModal');
      modal.querySelector('.property-title').textContent = propertyType.textContent;
      modal.querySelector('.user-avatar').src = userInfo.querySelector('img').src;
      modal.querySelector('.user-name').textContent = userInfo.querySelector('.user-name').textContent;
      modal.querySelector('.property-type').textContent = propertyType.textContent;
      
      // Update engagement stats
      modal.querySelector('.likes-count').textContent = engagement.querySelector('.likes').textContent.match(/\d+/)[0];
      modal.querySelector('.views-count').textContent = engagement.querySelector('.views').textContent.match(/\d+/)[0];
      modal.querySelector('.comments-count').textContent = engagement.querySelector('.comments').textContent.match(/\d+/)[0];

      // Set iframe source from data attribute
      modal.querySelector('iframe').src = iframeSrc || 'https://kuula.co/share/collection/71FTP?logo=1&info=1&fs=1&vr=0&sd=1&thumbs=1';

      propertyModal.show();
    });
  });

  // Fullscreen button functionality
  document.querySelector('.btn-fullscreen').addEventListener('click', function() {
    const iframe = document.querySelector('.viewer-content iframe');
    if (iframe.requestFullscreen) {
      iframe.requestFullscreen();
    } else if (iframe.webkitRequestFullscreen) {
      iframe.webkitRequestFullscreen();
    } else if (iframe.msRequestFullscreen) {
      iframe.msRequestFullscreen();
    }
  });

  // Like button functionality in modal
  document.querySelector('.btn-like').addEventListener('click', function() {
    const icon = this.querySelector('i');
    icon.classList.toggle('far');
    icon.classList.toggle('fas');
    icon.classList.toggle('text-danger');
  });

  // Share button functionality
  document.querySelector('.btn-share').addEventListener('click', function() {
    // Add your share functionality here
    console.log('Share button clicked');
  });

  // Navigation Pills
  const navLinks = document.querySelectorAll('.explore-nav .nav-link');
  navLinks.forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      navLinks.forEach(l => l.classList.remove('active'));
      link.classList.add('active');
    });
  });

  // Like Button Functionality in cards
  document.querySelectorAll('.likes').forEach(likeBtn => {
    likeBtn.addEventListener('click', (e) => {
      e.stopPropagation(); // Prevent modal from opening
      const icon = likeBtn.querySelector('i');
      icon.classList.toggle('far');
      icon.classList.toggle('fas');
      icon.classList.toggle('text-danger');
    });
  });

  // More Options Button
  document.querySelectorAll('.more-options').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation(); // Prevent modal from opening
      console.log('More options clicked');
    });
  });

  // Load More Button
  const loadMoreBtn = document.querySelector('.load-more');
  const spinner = loadMoreBtn.querySelector('.fa-spinner');
  let currentlyShown;

  // Function to show/hide items based on screen size
  function updateVisibleItems(initial = false) {
    const screenWidth = window.innerWidth;
    const initialItems = screenWidth < 768 ? 4 : 8;
    
    if (initial) {
      currentlyShown = initialItems;
      galleryItems.forEach((item, index) => {
        if (index >= initialItems) {
          item.style.display = 'none';
        } else {
          item.style.display = 'block';
        }
      });
    }

    if (currentlyShown >= galleryItems.length) {
      loadMoreBtn.style.display = 'none';
    } else {
      loadMoreBtn.style.display = 'inline-block';
    }
  }

  // Initialize the gallery
  updateVisibleItems(true);

  // Handle load more button click
  loadMoreBtn.addEventListener('click', function() {
    spinner.classList.add('fa-spin');
    const screenWidth = window.innerWidth;
    const itemsToLoad = screenWidth < 768 ? 4 : 8;
    
    setTimeout(() => {
      for (let i = currentlyShown; i < currentlyShown + itemsToLoad && i < galleryItems.length; i++) {
        galleryItems[i].style.display = 'block';
      }
      currentlyShown += itemsToLoad;
      
      if (currentlyShown >= galleryItems.length) {
        loadMoreBtn.style.display = 'none';
      }
      
      spinner.classList.remove('fa-spin');
    }, 800);
  });

  // Handle window resize
  let resizeTimer;
  window.addEventListener('resize', function() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => {
      updateVisibleItems(true);
    }, 250);
  });

  // Lazy Loading Images
  const lazyImages = document.querySelectorAll('.card-image img');
  const imageObserver = new IntersectionObserver((entries, observer) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const img = entry.target;
        img.src = img.dataset.src;
        observer.unobserve(img);
      }
    });
  });

  lazyImages.forEach(img => imageObserver.observe(img));
}); 