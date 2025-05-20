// Utility function to generate unique IDs
function generateUniqueId(prefix = 'id') {
    return `${prefix}_${Math.random().toString(36).substr(2, 9)}_${Date.now()}`;
}

// Utility function to show notifications
function showNotification(type, message) {
    const notificationContainer = document.getElementById('notification-container') || createNotificationContainer();
    
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <i class="fas ${getNotificationIcon(type)}"></i>
        <span>${message}</span>
    `;

    notificationContainer.appendChild(notification);

    // Auto-remove notification after 5 seconds
    setTimeout(() => {
        notification.classList.add('fade-out');
        setTimeout(() => notification.remove(), 300);
    }, 5000);
}

// Helper function to create notification container if it doesn't exist
function createNotificationContainer() {
    const container = document.createElement('div');
    container.id = 'notification-container';
    container.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
    `;
    document.body.appendChild(container);
    return container;
}

// Helper function to get appropriate icon for notification type
function getNotificationIcon(type) {
    switch(type) {
        case 'success':
            return 'fa-check-circle';
        case 'error':
            return 'fa-exclamation-circle';
        case 'info':
            return 'fa-info-circle';
        case 'warning':
            return 'fa-exclamation-triangle';
        default:
            return 'fa-info-circle';
    }
}

// Export functions to make them globally available
window.generateUniqueId = generateUniqueId;
window.showNotification = showNotification; 