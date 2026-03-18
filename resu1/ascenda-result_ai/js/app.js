/**
 * Ascenda AI Resume Matcher - JavaScript Application
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize all components
    initMatchScoreAnimation();
    initMobileMenu();
    initModal();
    initActionButtons();
});

/**
 * Animate the match score circle on page load
 */
function initMatchScoreAnimation() {
    const scoreCircle = document.querySelector('.progress-circle');
    const scoreValue = document.querySelector('.score-value');

    if (!scoreCircle || !scoreValue) return;

    const score = parseInt(scoreCircle.dataset.score) || 0;
    const circumference = 2 * Math.PI * 72; // radius = 72
    const offset = circumference - (score / 100) * circumference;

    // Set initial state
    scoreCircle.style.strokeDasharray = circumference;
    scoreCircle.style.strokeDashoffset = circumference;

    // Animate after a small delay
    setTimeout(() => {
        scoreCircle.style.strokeDashoffset = offset;
    }, 300);

    // Animate the number counting up
    animateValue(scoreValue, 0, score, 1000);
}

/**
 * Animate a number from start to end
 */
function animateValue(element, start, end, duration) {
    const startTime = performance.now();

    function update(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        const easeProgress = 1 - Math.pow(1 - progress, 3); // Ease out cubic
        const current = Math.round(start + (end - start) * easeProgress);

        element.innerHTML = current + '<span>%</span>';

        if (progress < 1) {
            requestAnimationFrame(update);
        }
    }

    requestAnimationFrame(update);
}

/**
 * Mobile menu toggle
 */
function initMobileMenu() {
    const menuBtn = document.querySelector('.mobile-menu-btn');
    const nav = document.querySelector('.navbar-nav');

    if (!menuBtn || !nav) return;

    menuBtn.addEventListener('click', () => {
        nav.classList.toggle('active');

        // Toggle icon
        const icon = menuBtn.querySelector('svg');
        if (nav.classList.contains('active')) {
            icon.innerHTML = `
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            `;
        } else {
            icon.innerHTML = `
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            `;
        }
    });

    // Close menu when clicking outside
    document.addEventListener('click', (e) => {
        if (!menuBtn.contains(e.target) && !nav.contains(e.target)) {
            nav.classList.remove('active');
        }
    });
}

/**
 * Modal functionality
 */
function initModal() {
    const modal = document.getElementById('feedbackModal');
    const openBtn = document.getElementById('reportBtn');
    const closeBtn = document.querySelector('.modal-close');
    const cancelBtn = document.getElementById('cancelFeedback');
    const submitBtn = document.getElementById('submitFeedback');
    const textarea = document.getElementById('feedbackText');

    if (!modal) return;

    // Open modal
    if (openBtn) {
        openBtn.addEventListener('click', () => {
            modal.classList.add('active');
            textarea.focus();
        });
    }

    // Close modal functions
    function closeModal() {
        modal.classList.remove('active');
        textarea.value = '';
    }

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

    // Close on overlay click
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.classList.contains('active')) {
            closeModal();
        }
    });

    // Submit feedback
    if (submitBtn) {
        submitBtn.addEventListener('click', () => {
            const feedbackText = textarea.value.trim();

            if (!feedbackText) {
                showToast('Please enter your feedback', 'error');
                return;
            }

            submitFeedback(feedbackText);
            closeModal();
        });
    }
}

/**
 * Action buttons functionality
 */
function initActionButtons() {
    const interviewBtn = document.getElementById('interviewBtn');

    if (interviewBtn) {
        interviewBtn.addEventListener('click', () => {
            selectForInterview();
        });
    }
}

/**
 * Submit feedback to Supabase
 */
async function submitFeedback(feedbackText) {
    const candidateId = document.getElementById('candidateId')?.value;

    if (!candidateId) {
        showToast('Error: Candidate ID not found', 'error');
        return;
    }

    try {
        const response = await fetch('api/submit_feedback.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                candidate_id: candidateId,
                feedback_text: feedbackText
            })
        });

        const data = await response.json();

        if (data.success) {
            showToast('Feedback submitted successfully', 'success');
        } else {
            showToast(data.message || 'Error submitting feedback', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Error submitting feedback', 'error');
    }
}

/**
 * Select candidate for interview
 */
async function selectForInterview() {
    const candidateId = document.getElementById('candidateId')?.value;
    const btn = document.getElementById('interviewBtn');

    if (!candidateId) {
        showToast('Error: Candidate ID not found', 'error');
        return;
    }

    // Show loading state
    const originalContent = btn.innerHTML;
    btn.innerHTML = '<span class="loading-spinner"></span> Processing...';
    btn.disabled = true;

    try {
        const response = await fetch('api/update_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                candidate_id: candidateId,
                status: 'Interview Selected'
            })
        });

        const data = await response.json();

        if (data.success) {
            showToast('Candidate selected for interview!', 'success');
            btn.innerHTML = `
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Interview Selected
            `;
            btn.classList.add('btn-success');
        } else {
            btn.innerHTML = originalContent;
            btn.disabled = false;
            showToast(data.message || 'Error updating status', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        btn.innerHTML = originalContent;
        btn.disabled = false;
        showToast('Error updating status', 'error');
    }
}

/**
 * Show toast notification
 */
function showToast(message, type = 'success') {
    // Remove existing toasts
    const existingToast = document.querySelector('.toast');
    if (existingToast) {
        existingToast.remove();
    }

    // Create toast element
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;

    const icon = type === 'success'
        ? `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
           </svg>`
        : `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
               <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
           </svg>`;

    toast.innerHTML = `${icon}<span>${message}</span>`;
    document.body.appendChild(toast);

    // Trigger animation
    setTimeout(() => toast.classList.add('active'), 10);

    // Remove toast after delay
    setTimeout(() => {
        toast.classList.remove('active');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
