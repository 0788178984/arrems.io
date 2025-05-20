// Tours related functions
const API_BASE_URL = '/ARREMS/backend_PHP_files';

// Function to fetch tours
async function fetchTours(filters = {}, page = 1) {
    try {
        const response = await fetch(`${API_BASE_URL}/get_tours.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                ...filters,
                page: page
            })
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Error fetching tours:', error);
        return { success: false, message: error.message };
    }
}

// Function to render tour card
function renderTourCard(tour) {
    return `
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="tour-card card h-100">
                <div class="tour-image">
                    <iframe src="${tour.tour_url}" frameborder="0" allowfullscreen></iframe>
                    <div class="tour-overlay">
                        <span class="property-price">UGX ${tour.price.toLocaleString()}</span>
                        <button class="btn btn-sm btn-light favorite-btn" onclick="toggleFavorite(${tour.id})">
                            <i class="far fa-heart"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <h5 class="card-title">${tour.title}</h5>
                    <p class="card-text">
                        <i class="fas fa-map-marker-alt text-primary me-2"></i>${tour.location}
                    </p>
                    <div class="property-features">
                        <span><i class="fas fa-bed me-1"></i> ${tour.bedrooms} Beds</span>
                        <span><i class="fas fa-bath me-1"></i> ${tour.bathrooms} Baths</span>
                        <span><i class="fas fa-ruler-combined me-1"></i> ${tour.area} sqft</span>
                    </div>
                </div>
                <div class="card-footer bg-white border-top-0">
                    <a href="tour-details.html?id=${tour.id}" class="btn btn-outline-primary w-100">View Details</a>
                </div>
            </div>
        </div>
    `;
}

// Function to toggle favorite
async function toggleFavorite(tourId) {
    if (!auth.isLoggedIn()) {
        alert('Please login to add properties to favorites');
        return;
    }

    try {
        const response = await fetch(`${API_BASE_URL}/toggle_favorite.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                tour_id: tourId,
                user_id: auth.getCurrentUser().id
            })
        });

        const data = await response.json();
        
        if (data.success) {
            const btn = event.target.closest('.favorite-btn');
            const icon = btn.querySelector('i');
            icon.classList.toggle('far');
            icon.classList.toggle('fas');
            icon.classList.toggle('text-danger');
        }
    } catch (error) {
        console.error('Error toggling favorite:', error);
    }
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    const tourFilterForm = document.getElementById('tourFilterForm');
    const toursGrid = document.getElementById('toursGrid');
    const loadMoreBtn = document.getElementById('loadMoreBtn');
    let currentPage = 1;

    // Handle filter form submission
    tourFilterForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const filters = {
            property_type: document.getElementById('propertyType').value,
            price_range: document.getElementById('priceRange').value,
            location: document.getElementById('location').value
        };

        currentPage = 1;
        const data = await fetchTours(filters, currentPage);
        
        if (data.success) {
            toursGrid.innerHTML = data.tours.map(tour => renderTourCard(tour)).join('');
            loadMoreBtn.style.display = data.has_more ? 'block' : 'none';
        } else {
            alert('Failed to fetch tours: ' + data.message);
        }
    });

    // Handle load more button
    loadMoreBtn.addEventListener('click', async function() {
        currentPage++;
        const filters = {
            property_type: document.getElementById('propertyType').value,
            price_range: document.getElementById('priceRange').value,
            location: document.getElementById('location').value
        };

        const data = await fetchTours(filters, currentPage);
        
        if (data.success) {
            toursGrid.insertAdjacentHTML('beforeend', data.tours.map(tour => renderTourCard(tour)).join(''));
            loadMoreBtn.style.display = data.has_more ? 'block' : 'none';
        }
    });

    // Load initial tours
    fetchTours().then(data => {
        if (data.success) {
            toursGrid.innerHTML = data.tours.map(tour => renderTourCard(tour)).join('');
            loadMoreBtn.style.display = data.has_more ? 'block' : 'none';
        }
    });
}); 