/**
 * Main JavaScript for Laravel Telegram Hybrid Storage Documentation
 * Handles navigation, animations, and interactive features
 */

// ============================================
// Navigation Handling
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    // Smooth scrolling for navigation links
    const navLinks = document.querySelectorAll('.nav-menu a');
    
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remove active class from all links
            navLinks.forEach(l => l.classList.remove('active'));
            
            // Add active class to clicked link
            this.classList.add('active');
            
            // Get target section
            const targetId = this.getAttribute('href').substring(1);
            const targetSection = document.getElementById(targetId);
            
            // Hide all sections and show target
            document.querySelectorAll('.section').forEach(section => {
                section.classList.remove('active');
            });
            
            targetSection.classList.add('active');
            
            // Scroll to top of content
            window.scrollTo({ top: 0, behavior: 'smooth' });
            
            // Update URL hash without scrolling
            history.pushState(null, null, `#${targetId}`);
        });
    });
    
    // Handle initial load with hash
    const hash = window.location.hash;
    if (hash) {
        const targetLink = document.querySelector(`.nav-menu a[href="${hash}"]`);
        if (targetLink) {
            targetLink.click();
        }
    }
});

// ============================================
// Workflow Animation
// ============================================

let animationPlaying = false;

function playAnimation() {
    if (animationPlaying) return;
    
    animationPlaying = true;
    const workflowSteps = document.querySelectorAll('.workflow-step');
    const connectors = document.querySelectorAll('.animated-dot');
    
    // Reset all steps
    workflowSteps.forEach(step => step.classList.remove('active'));
    
    let currentStep = 0;
    
    // Animate through steps
    const animateStep = () => {
        if (currentStep < workflowSteps.length) {
            // Activate current step
            workflowSteps[currentStep].classList.add('active');
            
            // Animate connector dots
            if (currentStep < connectors.length) {
                connectors[currentStep].style.animationPlayState = 'running';
            }
            
            // Move to next step after delay
            currentStep++;
            setTimeout(animateStep, 2000);
        } else {
            // Animation complete
            setTimeout(() => {
                workflowSteps.forEach(step => step.classList.remove('active'));
                animationPlaying = false;
            }, 2000);
        }
    };
    
    animateStep();
}

// ============================================
// Code Block Enhancement
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    // Add copy button to code blocks
    const codeBlocks = document.querySelectorAll('pre');
    
    codeBlocks.forEach(block => {
        // Create copy button
        const copyBtn = document.createElement('button');
        copyBtn.className = 'copy-code-btn';
        copyBtn.innerHTML = '<i class="fas fa-copy"></i> Copy';
        copyBtn.style.cssText = `
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 10px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.2s;
        `;
        
        copyBtn.addEventListener('mouseenter', function() {
            this.style.background = 'rgba(255, 255, 255, 0.2)';
        });
        
        copyBtn.addEventListener('mouseleave', function() {
            this.style.background = 'rgba(255, 255, 255, 0.1)';
        });
        
        copyBtn.addEventListener('click', function() {
            const code = block.querySelector('code') || block;
            const text = code.textContent;
            
            navigator.clipboard.writeText(text).then(() => {
                copyBtn.innerHTML = '<i class="fas fa-check"></i> Copied!';
                setTimeout(() => {
                    copyBtn.innerHTML = '<i class="fas fa-copy"></i> Copy';
                }, 2000);
            });
        });
        
        // Style the pre element for the button
        block.style.position = 'relative';
        block.appendChild(copyBtn);
    });
});

// ============================================
// Table Enhancement
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    // Add responsive wrapper to tables
    const tables = document.querySelectorAll('table');
    
    tables.forEach(table => {
        const wrapper = document.createElement('div');
        wrapper.className = 'table-responsive';
        wrapper.style.overflowX = 'auto';
        wrapper.style.marginBottom = '1rem';
        
        table.parentNode.insertBefore(wrapper, table);
        wrapper.appendChild(table);
    });
});

// ============================================
// Search Functionality (Future Enhancement)
// ============================================

function searchContent(query) {
    const sections = document.querySelectorAll('.section');
    const results = [];
    
    sections.forEach(section => {
        const text = section.textContent.toLowerCase();
        if (text.includes(query.toLowerCase())) {
            results.push(section);
        }
    });
    
    return results;
}

// ============================================
// Sidebar Toggle for Mobile (Future Enhancement)
// ============================================

function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    sidebar.classList.toggle('collapsed');
}

// ============================================
// Theme Toggle (Future Enhancement - Dark Mode)
// ============================================

function toggleTheme() {
    document.body.classList.toggle('dark-theme');
    const isDark = document.body.classList.contains('dark-theme');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
}

// Load saved theme
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.body.classList.add('dark-theme');
    }
});

// ============================================
// Intersection Observer for Animations
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -100px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);
    
    // Observe elements for animation
    const animatedElements = document.querySelectorAll('.feature-card, .component-info, .timeline-item');
    
    animatedElements.forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'opacity 0.5s ease-out, transform 0.5s ease-out';
        observer.observe(el);
    });
});

// ============================================
// Print Optimization
// ============================================

window.addEventListener('beforeprint', function() {
    // Expand all sections for printing
    document.querySelectorAll('.section').forEach(section => {
        section.style.display = 'block';
    });
});

window.addEventListener('afterprint', function() {
    // Restore section visibility
    const activeSection = document.querySelector('.section.active');
    document.querySelectorAll('.section').forEach(section => {
        if (section !== activeSection) {
            section.style.display = 'none';
        }
    });
});

// ============================================
// Keyboard Shortcuts
// ============================================

document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + K for search (future)
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        console.log('Search shortcut pressed');
    }
    
    // Arrow keys for navigation
    if (e.altKey && e.key === 'ArrowDown') {
        navigateToNextSection();
    }
    
    if (e.altKey && e.key === 'ArrowUp') {
        navigateToPreviousSection();
    }
});

function navigateToNextSection() {
    const activeLink = document.querySelector('.nav-menu a.active');
    const nextItem = activeLink.parentElement.nextElementSibling;
    
    if (nextItem && nextItem.querySelector('a')) {
        nextItem.querySelector('a').click();
    }
}

function navigateToPreviousSection() {
    const activeLink = document.querySelector('.nav-menu a.active');
    const prevItem = activeLink.parentElement.previousElementSibling;
    
    if (prevItem && prevItem.querySelector('a')) {
        prevItem.querySelector('a').click();
    }
}

console.log('Laravel Telegram Hybrid Storage Documentation loaded successfully!');
