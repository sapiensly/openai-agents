// Enhanced JavaScript for OpenAI Agents GitHub Pages

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all interactive features
    initScrollIndicator();
    initAnimations();
    initSmoothScrolling();
    initNavigationEffects();
    initCodeBlockEnhancements();
    initStatsCounter();
    initSearchFunctionality();
    // initThemeToggle(); - removed dark mode toggle
    initMobileMenu();
    initDocumentationLinks();
});

// Scroll progress indicator
function initScrollIndicator() {
    const scrollIndicator = document.createElement('div');
    scrollIndicator.className = 'scroll-indicator';
    document.body.appendChild(scrollIndicator);

    window.addEventListener('scroll', function() {
        const scrollTop = window.pageYOffset;
        const docHeight = document.documentElement.scrollHeight - window.innerHeight;
        const scrollPercent = (scrollTop / docHeight) * 100;

        scrollIndicator.style.setProperty('--scroll-width', scrollPercent + '%');
        scrollIndicator.querySelector('::after').style.width = scrollPercent + '%';
    });
}

// Intersection Observer for animations
function initAnimations() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('loading-fade-in');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Observe all feature cards and sections
    document.querySelectorAll('.feature-card, .stats-card, .doc-card').forEach(el => {
        observer.observe(el);
    });
}

// Enhanced smooth scrolling
function initSmoothScrolling() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            const target = document.querySelector(targetId);

            if (target) {
                const headerHeight = document.querySelector('nav').offsetHeight;
                const targetPosition = target.offsetTop - headerHeight - 20;

                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });

                // Update URL without page jump
                history.pushState(null, null, targetId);
            }
        });
    });
}

// Navigation effects
function initNavigationEffects() {
    const nav = document.querySelector('nav');
    let lastScrollTop = 0;

    window.addEventListener('scroll', function() {
        const scrollTop = window.pageYOffset;

        // Add/remove background blur effect
        if (scrollTop > 100) {
            nav.classList.add('nav-enhanced');
        } else {
            nav.classList.remove('nav-enhanced');
        }

        // Hide/show navigation on scroll
        if (scrollTop > lastScrollTop && scrollTop > 200) {
            nav.style.transform = 'translateY(-100%)';
        } else {
            nav.style.transform = 'translateY(0)';
        }

        lastScrollTop = scrollTop;
    });
}

// Enhanced code block features
function initCodeBlockEnhancements() {
    document.querySelectorAll('.code-block pre code').forEach(block => {
        // Add copy button
        const copyButton = document.createElement('button');
        copyButton.className = 'copy-btn';
        copyButton.innerHTML = '📋';
        copyButton.style.cssText = `
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(255,255,255,0.1);
            border: none;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s ease;
        `;

        copyButton.addEventListener('click', function() {
            navigator.clipboard.writeText(block.textContent).then(() => {
                this.innerHTML = '✅';
                setTimeout(() => {
                    this.innerHTML = '📋';
                }, 2000);
            });
        });

        block.parentElement.style.position = 'relative';
        block.parentElement.appendChild(copyButton);
    });
}

// Animated stats counter
function initStatsCounter() {
    const statsNumbers = document.querySelectorAll('.stats-number');

    const countUp = (element, target) => {
        let current = 0;
        const increment = target / 50;
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            element.textContent = Math.floor(current);
        }, 30);
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const target = parseInt(entry.target.textContent);
                countUp(entry.target, target);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.5 });

    statsNumbers.forEach(stat => observer.observe(stat));
}

// Search functionality for documentation
function initSearchFunctionality() {
    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.placeholder = 'Search documentation...';
    searchInput.className = 'search-input';
    searchInput.style.cssText = `
        width: 100%;
        max-width: 300px;
        padding: 8px 12px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        margin-bottom: 16px;
        font-size: 14px;
    `;

    const docSection = document.querySelector('#documentation .grid');
    if (docSection) {
        docSection.parentElement.insertBefore(searchInput, docSection);

        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            const docCards = document.querySelectorAll('.doc-card');

            docCards.forEach(card => {
                const title = card.querySelector('h3').textContent.toLowerCase();
                const description = card.querySelector('p').textContent.toLowerCase();

                if (title.includes(query) || description.includes(query)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    }
}

// Theme toggle functionality - removed
function initThemeToggle() {
    // La funcionalidad de cambio de tema ha sido eliminada
    // Si hay un tema oscuro activo, volvemos al tema claro
    if (localStorage.getItem('theme') === 'dark') {
        document.documentElement.classList.remove('dark');
        localStorage.removeItem('theme');
    }
}

// Mobile menu functionality
function initMobileMenu() {
    const nav = document.querySelector('nav');
    const mobileMenuButton = document.createElement('button');
    mobileMenuButton.innerHTML = '☰';
    mobileMenuButton.className = 'mobile-menu-btn';
    mobileMenuButton.style.cssText = `
        display: none;
        background: none;
        border: none;
        font-size: 24px;
        color: #374151;
        cursor: pointer;
    `;

    const navLinks = nav.querySelector('.hidden.md\\:block');
    if (navLinks) {
        nav.appendChild(mobileMenuButton);

        // Show mobile menu button on small screens
        const mediaQuery = window.matchMedia('(max-width: 768px)');

        function handleMobileMenu(e) {
            if (e.matches) {
                mobileMenuButton.style.display = 'block';
                navLinks.style.display = 'none';
            } else {
                mobileMenuButton.style.display = 'none';
                navLinks.style.display = 'flex';
            }
        }

        mediaQuery.addListener(handleMobileMenu);
        handleMobileMenu(mediaQuery);

        // Toggle mobile menu
        mobileMenuButton.addEventListener('click', function() {
            navLinks.style.display = navLinks.style.display === 'none' ? 'flex' : 'none';
        });
    }
}

// Documentation links handling
function initDocumentationLinks() {
    // Intercept all documentation links (both doc-card and footer-link classes)
    document.querySelectorAll('a[href$=".md"]').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const mdPath = this.getAttribute('href');

            // Skip external links (like GitHub links)
            if (mdPath.startsWith('http')) {
                window.open(mdPath, '_blank');
                return;
            }

            showMarkdown(mdPath);
        });
    });

    // Modal close functionality
    document.getElementById('doc-close').addEventListener('click', function() {
        closeModal();
    });

    // Close modal when clicking outside
    document.getElementById('doc-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('doc-modal').style.display === 'block') {
            closeModal();
        }
    });
}

// Close modal helper function
function closeModal() {
    document.getElementById('doc-modal').style.display = 'none';
    document.getElementById('doc-html').innerHTML = '';
    document.getElementById('doc-title').innerHTML = '';
    document.body.style.overflow = 'auto';
}

// Show markdown content in modal
function showMarkdown(mdPath) {
    // Prevent body scrolling when modal is open
    document.body.style.overflow = 'hidden';

    // Set title from filename
    const filename = mdPath.split('/').pop().replace('.md', '').replace(/_/g, ' ');
    const formattedTitle = filename.split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase()).join(' ');
    document.getElementById('doc-title').innerHTML = formattedTitle;

    // Show loading state
    document.getElementById('doc-html').innerHTML = '<div style="text-align: center; padding: 2em;"><div style="display: inline-block; width: 40px; height: 40px; border: 4px solid #f3f4f6; border-top: 4px solid #3b82f6; border-radius: 50%; animation: spin 1s linear infinite;"></div><p style="margin-top: 1em; color: #6b7280;">Loading documentation...</p></div>';
    document.getElementById('doc-modal').style.display = 'block';

    // Fetch and convert markdown
    fetch(mdPath)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(md => {
            // Configure marked.js options
            marked.setOptions({
                breaks: true,
                gfm: true,
                headerIds: true,
                mangle: false,
                sanitize: false
            });

            // Convert markdown to HTML
            const html = marked.parse(md);

            // Apply syntax highlighting
            document.getElementById('doc-html').innerHTML = html;

            // Re-apply Prism.js highlighting
            if (typeof Prism !== 'undefined') {
                Prism.highlightAll();
            }

            // Scroll to top of modal
            document.getElementById('doc-content').scrollTop = 0;

            // Track analytics
            if (typeof trackEvent === 'function') {
                trackEvent('documentation_viewed', { path: mdPath });
            }
        })
        .catch(error => {
            console.error('Error loading documentation:', error);
            document.getElementById('doc-html').innerHTML = `
                <div style="text-align: center; padding: 2em; color: #ef4444;">
                    <h3>Error Loading Documentation</h3>
                    <p>The requested documentation could not be loaded.</p>
                    <p style="font-size: 0.9em; color: #6b7280;">${error.message}</p>
                    <button onclick="closeModal()" style="margin-top: 1em; padding: 0.5em 1em; background: #3b82f6; color: white; border: none; border-radius: 4px; cursor: pointer;">Close</button>
                </div>
            `;
        });
}

// Performance monitoring
function initPerformanceMonitoring() {
    // Track page load time
    window.addEventListener('load', function() {
        const loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;
        console.log(`Page loaded in ${loadTime}ms`);

        // Send to analytics if available
        if (typeof gtag !== 'undefined') {
            gtag('event', 'timing_complete', {
                name: 'load',
                value: loadTime
            });
        }
    });

    // Track scroll depth
    let maxScroll = 0;
    window.addEventListener('scroll', function() {
        const scrollPercent = Math.round((window.pageYOffset / (document.documentElement.scrollHeight - window.innerHeight)) * 100);
        if (scrollPercent > maxScroll) {
            maxScroll = scrollPercent;

            // Track scroll milestones
            if (maxScroll >= 25 && maxScroll < 50) {
                trackEvent('scroll_25_percent');
            } else if (maxScroll >= 50 && maxScroll < 75) {
                trackEvent('scroll_50_percent');
            } else if (maxScroll >= 75) {
                trackEvent('scroll_75_percent');
            }
        }
    });
}

// Analytics helper
function trackEvent(eventName, properties = {}) {
    // Google Analytics
    if (typeof gtag !== 'undefined') {
        gtag('event', eventName, properties);
    }

    // Custom analytics
    console.log(`Event: ${eventName}`, properties);
}

// Initialize performance monitoring
initPerformanceMonitoring();

// Export functions for potential external use
window.OpenAIAgentsLanding = {
    trackEvent,
    initAnimations,
    initSmoothScrolling,
    closeModal
};
