import tourManager from './tour-manager.js';

class TourUI {
    constructor() {
        this.bindElements();
        this.bindEvents();
        this.currentTour = null;
    }

    bindElements() {
        // Buttons
        this.newTourBtn = document.getElementById('new-tour-btn');
        this.loadTourBtn = document.getElementById('load-tour-btn');
        
        // Search and filters
        this.searchInput = document.getElementById('tour-search');
        this.categoryFilter = document.getElementById('category-filter');
        this.sortSelect = document.getElementById('sort-tours');
        
        // Templates
        this.tourDetailsTemplate = document.getElementById('tour-details-modal');
        this.tourListTemplate = document.getElementById('tour-list-modal');
    }

    bindEvents() {
        // Tour management
        this.newTourBtn.addEventListener('click', () => this.showTourDetailsModal());
        this.loadTourBtn.addEventListener('click', () => this.showTourListModal());
        
        // Search and filters
        this.searchInput.addEventListener('input', debounce(() => this.updateTourList(), 300));
        this.categoryFilter.addEventListener('change', () => this.updateTourList());
        this.sortSelect.addEventListener('change', () => this.updateTourList());
    }

    async showTourDetailsModal(tourId = null) {
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.innerHTML = this.tourDetailsTemplate.innerHTML;
        document.body.appendChild(modal);

        const tour = tourId ? await tourManager.getTourMetadata(tourId) : null;
        
        // Populate form if editing existing tour
        if (tour) {
            modal.querySelector('#tour-title').value = tour.title;
            modal.querySelector('#tour-description').value = tour.description;
            modal.querySelector('#tour-category').value = tour.category;
            modal.querySelector('#tour-tags').value = tour.tags.join(', ');
            modal.querySelector('#tour-autorotate').checked = tour.settings.autorotate;
            modal.querySelector('#tour-compass').checked = tour.settings.compass;
            modal.querySelector('#tour-transition').value = tour.settings.defaultTransition;
        }

        // Save button handler
        modal.querySelector('#save-tour-details').addEventListener('click', async () => {
            const tourData = {
                title: modal.querySelector('#tour-title').value,
                description: modal.querySelector('#tour-description').value,
                category: modal.querySelector('#tour-category').value,
                tags: modal.querySelector('#tour-tags').value.split(',').map(tag => tag.trim()).filter(Boolean),
                settings: {
                    autorotate: modal.querySelector('#tour-autorotate').checked,
                    compass: modal.querySelector('#tour-compass').checked,
                    defaultTransition: modal.querySelector('#tour-transition').value
                }
            };

            try {
                if (tourId) {
                    await tourManager.updateTourMetadata(tourId, tourData);
                    showNotification('success', 'Tour updated successfully');
                } else {
                    await tourManager.createTour(tourData);
                    showNotification('success', 'Tour created successfully');
                }
                this.updateTourList();
                modal.remove();
            } catch (error) {
                console.error('Error saving tour:', error);
                showNotification('error', 'Failed to save tour: ' + error.message);
            }
        });

        // Close modal handlers
        const closeModal = () => {
            modal.classList.remove('show');
            setTimeout(() => modal.remove(), 300);
        };

        modal.querySelector('.close-modal').addEventListener('click', closeModal);
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });

        setTimeout(() => modal.classList.add('show'), 10);
    }

    async showTourListModal() {
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.innerHTML = this.tourListTemplate.innerHTML;
        document.body.appendChild(modal);

        const searchInput = modal.querySelector('#tour-list-search');
        const tourList = modal.querySelector('.tour-list');

        // Load and display tours
        const displayTours = async (filter = '') => {
            let tours = await tourManager.loadTours();
            
            if (filter) {
                tours = await tourManager.searchTours(filter);
            }

            tourList.innerHTML = tours.map(tour => `
                <div class="tour-item">
                    <div class="tour-item-info">
                        <div class="tour-item-title">
                            ${escapeHtml(tour.title)}
                            <span class="category-badge ${tour.category}">${tour.category}</span>
                        </div>
                        <div class="tour-item-meta">
                            Created: ${new Date(tour.created).toLocaleDateString()}
                        </div>
                        <div class="tag-list">
                            ${tour.tags.map(tag => `
                                <span class="tag">${escapeHtml(tag)}</span>
                            `).join('')}
                        </div>
                        <div class="tour-stats">
                            <div class="stat-item">
                                <div class="stat-value">${tour.statistics?.views || 0}</div>
                                <div class="stat-label">Views</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">${tour.statistics?.shares || 0}</div>
                                <div class="stat-label">Shares</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">${tour.statistics?.downloads || 0}</div>
                                <div class="stat-label">Downloads</div>
                            </div>
                        </div>
                    </div>
                    <div class="tour-item-actions">
                        <button class="button" onclick="loadTour('${tour.id}')">
                            <i class="fas fa-folder-open"></i> Load
                        </button>
                        <button class="button secondary" onclick="editTour('${tour.id}')">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="button secondary" onclick="deleteTour('${tour.id}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `).join('');
        };

        // Initial load
        await displayTours();

        // Search handler
        searchInput.addEventListener('input', debounce(async (e) => {
            await displayTours(e.target.value);
        }, 300));

        // Close modal handlers
        const closeModal = () => {
            modal.classList.remove('show');
            setTimeout(() => modal.remove(), 300);
        };

        modal.querySelector('.close-modal').addEventListener('click', closeModal);
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });

        setTimeout(() => modal.classList.add('show'), 10);
    }

    async updateTourList() {
        let tours = await tourManager.loadTours();
        
        // Apply search filter
        if (this.searchInput.value) {
            tours = await tourManager.searchTours(this.searchInput.value);
        }
        
        // Apply category filter
        if (this.categoryFilter.value) {
            tours = await tourManager.filterTours({ category: this.categoryFilter.value });
        }
        
        // Apply sorting
        const [sortBy, order] = this.sortSelect.value.split('-');
        tours = tourManager.sortTours(tours, sortBy, order);
        
        // Update UI if needed (e.g., update a list in the sidebar)
        this.renderTourList(tours);
    }

    renderTourList(tours) {
        // Implementation depends on your UI requirements
        console.log('Tours updated:', tours);
    }
}

// Helper function for debouncing
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Helper function to escape HTML
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Export singleton instance
const tourUI = new TourUI();
export default tourUI; 