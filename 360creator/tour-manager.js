// Import required dependencies
// Remove Marzipano import since it's loaded globally
// import Marzipano from 'https://cdn.jsdelivr.net/npm/marzipano@0.10.2/dist/marzipano.js';

// Tour Manager Class
class TourManager {
    constructor() {
        this.db = null;
        this.currentTour = null;
        this.tours = [];
        this.initialized = false;
    }

    // Initialize the tour manager
    async initialize() {
        if (this.initialized) return;
        
        try {
            this.db = await this.initDatabase();
            await this.loadTours();
            this.initialized = true;
            console.log('Tour Manager initialized successfully');
        } catch (error) {
            console.error('Failed to initialize Tour Manager:', error);
            throw error;
        }
    }

    // Initialize IndexedDB
    async initDatabase() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open("360TourDB", 2); // Increased version for new schema

            request.onerror = () => reject(request.error);
            request.onsuccess = () => resolve(request.result);

            request.onupgradeneeded = (event) => {
                const db = event.target.result;
                
                // Create or update tours store
                if (!db.objectStoreNames.contains('tours')) {
                    const tourStore = db.createObjectStore('tours', { keyPath: 'id' });
                    tourStore.createIndex('date', 'date');
                    tourStore.createIndex('title', 'title');
                    tourStore.createIndex('category', 'category');
                    tourStore.createIndex('tags', 'tags', { multiEntry: true });
                }
                
                // Create or update images store
                if (!db.objectStoreNames.contains('images')) {
                    const imageStore = db.createObjectStore('images', { keyPath: 'id' });
                    imageStore.createIndex('tourId', 'tourId');
                }

                // Create metadata store
                if (!db.objectStoreNames.contains('metadata')) {
                    const metaStore = db.createObjectStore('metadata', { keyPath: 'tourId' });
                    metaStore.createIndex('lastModified', 'lastModified');
                }
            };
        });
    }

    // Load all tours
    async loadTours() {
        try {
            const tx = this.db.transaction(['tours'], 'readonly');
            const store = tx.objectStore('tours');
            this.tours = await new Promise((resolve, reject) => {
                const request = store.getAll();
                request.onsuccess = () => resolve(request.result);
                request.onerror = () => reject(request.error);
            });
            return this.tours;
        } catch (error) {
            console.error('Error loading tours:', error);
            throw error;
        }
    }

    // Create a new tour
    async createTour(tourData) {
        try {
            const tour = {
                id: generateUniqueId('tour'),
                title: tourData.title || 'Untitled Tour',
                description: tourData.description || '',
                category: tourData.category || 'uncategorized',
                tags: tourData.tags || [],
                created: new Date().toISOString(),
                lastModified: new Date().toISOString(),
                scenes: [],
                settings: {
                    autorotate: false,
                    defaultTransition: 'fade',
                    viewControlsEnabled: true,
                    compass: false
                }
            };

            const tx = this.db.transaction(['tours', 'metadata'], 'readwrite');
            const tourStore = tx.objectStore('tours');
            const metaStore = tx.objectStore('metadata');

            await Promise.all([
                new Promise((resolve, reject) => {
                    const request = tourStore.add(tour);
                    request.onsuccess = () => resolve();
                    request.onerror = () => reject(request.error);
                }),
                new Promise((resolve, reject) => {
                    const request = metaStore.add({
                        tourId: tour.id,
                        lastModified: tour.lastModified,
                        version: 1,
                        status: 'draft',
                        statistics: {
                            views: 0,
                            shares: 0,
                            downloads: 0
                        }
                    });
                    request.onsuccess = () => resolve();
                    request.onerror = () => reject(request.error);
                })
            ]);

            this.tours.push(tour);
            this.currentTour = tour;
            return tour;
        } catch (error) {
            console.error('Error creating tour:', error);
            throw error;
        }
    }

    // Update tour metadata
    async updateTourMetadata(tourId, metadata) {
        try {
            const tx = this.db.transaction(['tours', 'metadata'], 'readwrite');
            const tourStore = tx.objectStore('tours');
            const metaStore = tx.objectStore('metadata');

            // Get existing tour
            const tour = await new Promise((resolve, reject) => {
                const request = tourStore.get(tourId);
                request.onsuccess = () => resolve(request.result);
                request.onerror = () => reject(request.error);
            });

            if (!tour) throw new Error('Tour not found');

            // Update tour data
            const updatedTour = {
                ...tour,
                title: metadata.title || tour.title,
                description: metadata.description || tour.description,
                category: metadata.category || tour.category,
                tags: metadata.tags || tour.tags,
                lastModified: new Date().toISOString(),
                settings: {
                    ...tour.settings,
                    ...metadata.settings
                }
            };

            // Update metadata
            const updatedMeta = {
                tourId: tourId,
                lastModified: updatedTour.lastModified,
                version: (await this.getTourMetadata(tourId)).version + 1,
                status: metadata.status || 'draft'
            };

            await Promise.all([
                new Promise((resolve, reject) => {
                    const request = tourStore.put(updatedTour);
                    request.onsuccess = () => resolve();
                    request.onerror = () => reject(request.error);
                }),
                new Promise((resolve, reject) => {
                    const request = metaStore.put(updatedMeta);
                    request.onsuccess = () => resolve();
                    request.onerror = () => reject(request.error);
                })
            ]);

            // Update local cache
            const index = this.tours.findIndex(t => t.id === tourId);
            if (index !== -1) {
                this.tours[index] = updatedTour;
            }

            return updatedTour;
        } catch (error) {
            console.error('Error updating tour metadata:', error);
            throw error;
        }
    }

    // Get tour metadata
    async getTourMetadata(tourId) {
        try {
            const tx = this.db.transaction(['metadata'], 'readonly');
            const store = tx.objectStore('metadata');
            return await new Promise((resolve, reject) => {
                const request = store.get(tourId);
                request.onsuccess = () => resolve(request.result);
                request.onerror = () => reject(request.error);
            });
        } catch (error) {
            console.error('Error getting tour metadata:', error);
            throw error;
        }
    }

    // Delete tour
    async deleteTour(tourId) {
        try {
            const tx = this.db.transaction(['tours', 'metadata', 'images'], 'readwrite');
            const tourStore = tx.objectStore('tours');
            const metaStore = tx.objectStore('metadata');
            const imageStore = tx.objectStore('images');
            const imageIndex = imageStore.index('tourId');

            // Delete tour images
            const images = await new Promise((resolve, reject) => {
                const request = imageIndex.getAll(tourId);
                request.onsuccess = () => resolve(request.result);
                request.onerror = () => reject(request.error);
            });

            await Promise.all([
                ...images.map(img => new Promise((resolve, reject) => {
                    const request = imageStore.delete(img.id);
                    request.onsuccess = () => resolve();
                    request.onerror = () => reject(request.error);
                })),
                new Promise((resolve, reject) => {
                    const request = tourStore.delete(tourId);
                    request.onsuccess = () => resolve();
                    request.onerror = () => reject(request.error);
                }),
                new Promise((resolve, reject) => {
                    const request = metaStore.delete(tourId);
                    request.onsuccess = () => resolve();
                    request.onerror = () => reject(request.error);
                })
            ]);

            // Update local cache
            this.tours = this.tours.filter(tour => tour.id !== tourId);
            if (this.currentTour?.id === tourId) {
                this.currentTour = null;
            }

            return true;
        } catch (error) {
            console.error('Error deleting tour:', error);
            throw error;
        }
    }

    // Search tours
    async searchTours(query) {
        try {
            const searchTerms = query.toLowerCase().split(' ');
            return this.tours.filter(tour => {
                const searchText = `${tour.title} ${tour.description} ${tour.category} ${tour.tags.join(' ')}`.toLowerCase();
                return searchTerms.every(term => searchText.includes(term));
            });
        } catch (error) {
            console.error('Error searching tours:', error);
            throw error;
        }
    }

    // Filter tours
    async filterTours(filters) {
        try {
            return this.tours.filter(tour => {
                if (filters.category && tour.category !== filters.category) return false;
                if (filters.tags && !filters.tags.every(tag => tour.tags.includes(tag))) return false;
                if (filters.dateRange) {
                    const tourDate = new Date(tour.created);
                    if (tourDate < filters.dateRange.start || tourDate > filters.dateRange.end) return false;
                }
                return true;
            });
        } catch (error) {
            console.error('Error filtering tours:', error);
            throw error;
        }
    }

    // Sort tours
    sortTours(tours, sortBy = 'date', order = 'desc') {
        return [...tours].sort((a, b) => {
            let valueA, valueB;
            switch (sortBy) {
                case 'title':
                    valueA = a.title.toLowerCase();
                    valueB = b.title.toLowerCase();
                    break;
                case 'date':
                    valueA = new Date(a.created);
                    valueB = new Date(b.created);
                    break;
                case 'category':
                    valueA = a.category.toLowerCase();
                    valueB = b.category.toLowerCase();
                    break;
                default:
                    return 0;
            }
            
            if (valueA < valueB) return order === 'desc' ? 1 : -1;
            if (valueA > valueB) return order === 'desc' ? -1 : 1;
            return 0;
        });
    }

    // Get tour statistics
    async getTourStatistics(tourId) {
        try {
            const metadata = await this.getTourMetadata(tourId);
            return metadata.statistics;
        } catch (error) {
            console.error('Error getting tour statistics:', error);
            throw error;
        }
    }

    // Update tour statistics
    async updateTourStatistics(tourId, type) {
        try {
            const tx = this.db.transaction(['metadata'], 'readwrite');
            const store = tx.objectStore('metadata');
            
            const metadata = await this.getTourMetadata(tourId);
            metadata.statistics[type]++;
            
            await new Promise((resolve, reject) => {
                const request = store.put(metadata);
                request.onsuccess = () => resolve();
                request.onerror = () => reject(request.error);
            });

            return metadata.statistics;
        } catch (error) {
            console.error('Error updating tour statistics:', error);
            throw error;
        }
    }
}

// Export singleton instance
const tourManager = new TourManager();
export default tourManager; 

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
    }

    // ... rest of the existing code ...
} 