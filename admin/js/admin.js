// Admin Dashboard JavaScript

// Authentication Check
function checkAdminAuth() {
    // Check if user is logged in and has admin role
    const user = JSON.parse(localStorage.getItem('currentUser'));
    if (!user || user.role !== 'admin') {
        window.location.href = '../index.html';
    }
}

// Initialize Dashboard
function initDashboard() {
    checkAdminAuth();
    initNotifications();
    initActivityFeed();
}

// Notifications System
function initNotifications() {
    const notifications = [
        { type: 'user', message: 'New user registration', time: '5 minutes ago' },
        { type: 'property', message: 'New property listing', time: '15 minutes ago' },
        { type: 'system', message: 'System update available', time: '1 hour ago' }
    ];

    updateNotificationBadge(notifications.length);
}

function updateNotificationBadge(count) {
    const badge = document.querySelector('.badge');
    if (badge) {
        badge.textContent = count;
        badge.style.display = count > 0 ? 'block' : 'none';
    }
}

// Activity Feed
function initActivityFeed() {
    // Fetch recent activities from server (mock data for now)
    const activities = [
        {
            type: 'user',
            title: 'New User Registration',
            description: 'John Doe registered as a new user',
            time: '5 minutes ago',
            icon: 'fa-user-plus',
            color: 'primary'
        },
        {
            type: 'property',
            title: 'New Property Listed',
            description: 'Modern Villa in Gulu City',
            time: '15 minutes ago',
            icon: 'fa-home',
            color: 'success'
        },
        {
            type: 'tour',
            title: 'Virtual Tour Created',
            description: 'New tour added for Downtown Loft',
            time: '1 hour ago',
            icon: 'fa-vr-cardboard',
            color: 'info'
        }
    ];

    updateActivityFeed(activities);
}

function updateActivityFeed(activities) {
    const feed = document.querySelector('.activity-feed');
    if (!feed) return;

    activities.forEach(activity => {
        const item = createActivityItem(activity);
        feed.appendChild(item);
    });
}

function createActivityItem(activity) {
    const item = document.createElement('div');
    item.className = 'activity-item';
    item.innerHTML = `
        <div class="icon-box bg-${activity.color} text-white">
            <i class="fas ${activity.icon}"></i>
        </div>
        <div class="activity-content">
            <h6>${activity.title}</h6>
            <p class="text-muted mb-0">${activity.description}</p>
            <small class="text-muted">${activity.time}</small>
        </div>
    `;
    return item;
}

// Analytics Chart Updates
function updateAnalyticsChart(chart, newData) {
    if (!chart) return;
    
    chart.data.datasets.forEach((dataset, index) => {
        dataset.data = newData[index];
    });
    chart.update();
}

// Sidebar Toggle
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebarCollapse');
    const sidebar = document.getElementById('sidebar');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });
    }

    // Initialize dashboard
    initDashboard();

    // Handle logout
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', (e) => {
            e.preventDefault();
            // Clear user data
            localStorage.removeItem('currentUser');
            // Redirect to home
            window.location.href = '../index.html';
        });
    }
});

// Export functions for use in other admin pages
export {
    checkAdminAuth,
    updateNotificationBadge,
    updateActivityFeed,
    updateAnalyticsChart
}; 