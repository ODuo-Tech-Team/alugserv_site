// ===== ALUGSERV - JAVASCRIPT PRINCIPAL =====

// Números de WhatsApp
const WHATSAPP_NUMBERS = {
    louveira: '5519999445111',
    jundiai: '5511964801527'
};

// Inicialização
document.addEventListener('DOMContentLoaded', () => {
    initMobileMenu();
    initCarousel();
    initWhatsAppFloat();
    setActiveNavLink();
});

// ===== MOBILE MENU =====
function initMobileMenu() {
    const menuToggle = document.querySelector('.menu-toggle');
    const mobileNav = document.querySelector('.mobile-nav');

    if (menuToggle && mobileNav) {
        menuToggle.addEventListener('click', () => {
            mobileNav.classList.toggle('active');

            // Trocar ícone
            const icon = menuToggle.querySelector('svg');
            if (mobileNav.classList.contains('active')) {
                icon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                `;
            } else {
                icon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                `;
            }
        });

        // Fechar menu ao clicar em link
        mobileNav.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                mobileNav.classList.remove('active');
            });
        });
    }
}

// ===== CAROUSEL =====
function initCarousel() {
    const carousel = document.querySelector('.carousel-container');
    if (!carousel) return;

    const track = carousel.querySelector('.carousel-track');
    const slides = carousel.querySelectorAll('.carousel-slide');
    const prevBtn = carousel.querySelector('.carousel-prev');
    const nextBtn = carousel.querySelector('.carousel-next');

    if (!track || slides.length === 0) return;

    let currentIndex = 0;
    let slidesPerView = getSlidesPerView();

    function getSlidesPerView() {
        if (window.innerWidth >= 1024) return 3;
        if (window.innerWidth >= 640) return 2;
        return 1;
    }

    function updateCarousel() {
        const slideWidth = 100 / slidesPerView;
        track.style.transform = `translateX(-${currentIndex * slideWidth}%)`;
    }

    function nextSlide() {
        const maxIndex = slides.length - slidesPerView;
        currentIndex = currentIndex >= maxIndex ? 0 : currentIndex + 1;
        updateCarousel();
    }

    function prevSlide() {
        const maxIndex = slides.length - slidesPerView;
        currentIndex = currentIndex <= 0 ? maxIndex : currentIndex - 1;
        updateCarousel();
    }

    if (nextBtn) nextBtn.addEventListener('click', nextSlide);
    if (prevBtn) prevBtn.addEventListener('click', prevSlide);

    // Atualizar ao redimensionar
    window.addEventListener('resize', () => {
        slidesPerView = getSlidesPerView();
        currentIndex = Math.min(currentIndex, slides.length - slidesPerView);
        updateCarousel();
    });

    // Auto-play
    setInterval(nextSlide, 5000);
}

// ===== WHATSAPP =====
function initWhatsAppFloat() {
    const floatBtn = document.querySelector('.whatsapp-float');
    if (floatBtn) {
        floatBtn.addEventListener('click', () => {
            openWhatsApp('louveira');
        });
    }
}

function openWhatsApp(unit, message = '') {
    const number = WHATSAPP_NUMBERS[unit] || WHATSAPP_NUMBERS.louveira;
    const url = message
        ? `https://wa.me/${number}?text=${encodeURIComponent(message)}`
        : `https://wa.me/${number}`;
    window.open(url, '_blank');
}

// ===== ACTIVE NAV LINK =====
function setActiveNavLink() {
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.header-nav a, .mobile-nav a');

    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (currentPath.endsWith(href) ||
            (href === 'index.html' && (currentPath === '/' || currentPath.endsWith('/')))) {
            link.classList.add('active');
        }
    });
}

// ===== SCROLL ANIMATIONS =====
function initScrollAnimations() {
    const observerOptions = {
        root: null,
        rootMargin: '0px',
        threshold: 0.1
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-in');
            }
        });
    }, observerOptions);

    document.querySelectorAll('.animate-on-scroll').forEach(el => {
        observer.observe(el);
    });
}

// ===== UTILITY FUNCTIONS =====

// Smooth scroll para links internos
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});
