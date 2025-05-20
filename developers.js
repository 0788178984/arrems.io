document.addEventListener('DOMContentLoaded', () => {
    // Initialize animations
    const animatedElements = document.querySelectorAll('[data-animate]');
    const observerOptions = {
        threshold: 0.2,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    animatedElements.forEach(element => {
        observer.observe(element);
    });

    // Code copy functionality
    const codeBlocks = document.querySelectorAll('.code-content code');
    codeBlocks.forEach(block => {
        block.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(block.textContent);
                showToast('Code copied to clipboard!');
            } catch (err) {
                console.error('Failed to copy code:', err);
                showToast('Failed to copy code', 'error');
            }
        });
    });

    // Toast notification
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);

        // Trigger reflow
        toast.offsetHeight;

        // Add visible class for animation
        toast.classList.add('visible');

        // Remove toast after animation
        setTimeout(() => {
            toast.classList.remove('visible');
            setTimeout(() => {
                document.body.removeChild(toast);
            }, 300);
        }, 3000);
    }

    // Add toast styles dynamically
    const style = document.createElement('style');
    style.textContent = `
        .toast {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: var(--card-bg);
            color: var(--text-primary);
            padding: 12px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            opacity: 0;
            transition: transform 0.3s ease, opacity 0.3s ease;
        }

        .toast.visible {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }

        .toast.toast-success {
            border-left: 4px solid var(--primary);
        }

        .toast.toast-error {
            border-left: 4px solid #ff5f57;
        }
    `;
    document.head.appendChild(style);

    // Handle navigation active state
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (currentPath.includes('developers') && href.includes('developers')) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });

    // Handle mobile menu
    const menuToggle = document.querySelector('.menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
            menuToggle.classList.toggle('active');
        });

        // Close sidebar when clicking outside
        document.addEventListener('click', (e) => {
            if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                sidebar.classList.remove('active');
                menuToggle.classList.remove('active');
            }
        });
    }
}); 