/**
 * Revista PG Turismo - Scripts principais
 * Contém todas as interações do site:
 * - Slider de destaque
 * - Menu mobile
 * - Funcionalidade de busca
 * - Outras interações
 */

document.addEventListener('DOMContentLoaded', function() {
    // =============================================
    // Slider de Destaque
    // =============================================
    const initSlider = function() {
        const slider = document.querySelector('.slider');
        if (!slider) return;

        const slides = document.querySelectorAll('.slide');
        let currentSlide = 0;
        let slideInterval;
        let isPaused = false;

        // Criar dots de navegação se não existirem
        const createDots = function() {
            const dotsContainer = document.createElement('div');
            dotsContainer.className = 'slider-nav';
            
            slides.forEach((_, i) => {
                const dot = document.createElement('div');
                dot.className = 'slider-dot';
                if (i === 0) dot.classList.add('active');
                dot.addEventListener('click', () => goToSlide(i));
                dotsContainer.appendChild(dot);
            });
            
            slider.appendChild(dotsContainer);
        };

        // Ir para slide específico
        const goToSlide = function(n) {
            slides.forEach(slide => slide.classList.remove('active'));
            document.querySelectorAll('.slider-dot').forEach(dot => dot.classList.remove('active'));
            
            currentSlide = n;
            slides[currentSlide].classList.add('active');
            document.querySelectorAll('.slider-dot')[currentSlide]?.classList.add('active');
            resetInterval();
        };

        // Próximo slide
        const nextSlide = function() {
            goToSlide((currentSlide + 1) % slides.length);
        };

        // Slide anterior
        const prevSlide = function() {
            goToSlide((currentSlide - 1 + slides.length) % slides.length);
        };

        // Resetar intervalo
        const resetInterval = function() {
            clearInterval(slideInterval);
            if (!isPaused) {
                slideInterval = setInterval(nextSlide, 5000);
            }
        };

        // Pausar ao interagir
        const pauseSlider = function() {
            isPaused = true;
            clearInterval(slideInterval);
        };

        // Iniciar slider
        const startSlider = function() {
            if (slides.length > 0) {
                if (document.querySelectorAll('.slider-dot').length === 0) {
                    createDots();
                }
                
                goToSlide(0);
                slideInterval = setInterval(nextSlide, 5000);
                
                // Pausar ao passar mouse
                slider.addEventListener('mouseenter', pauseSlider);
                slider.addEventListener('mouseleave', () => {
                    isPaused = false;
                    resetInterval();
                });
            }
        };

        startSlider();
    };

    // =============================================
    // Menu Mobile
    // =============================================
    const initMobileMenu = function() {
        const menuToggle = document.querySelector('.menu-toggle');
        const mainNav = document.querySelector('.main-navigation ul');
        
        if (!menuToggle || !mainNav) return;

        const toggleMenu = function() {
            menuToggle.classList.toggle('active');
            
            if (mainNav.style.display === 'flex') {
                mainNav.style.display = 'none';
                document.body.style.overflow = 'auto';
            } else {
                mainNav.style.display = 'flex';
                document.body.style.overflow = 'hidden';
                
                // Estilos específicos para o menu mobile
                mainNav.style.flexDirection = 'column';
                mainNav.style.position = 'absolute';
                mainNav.style.top = '100%';
                mainNav.style.left = '0';
                mainNav.style.right = '0';
                mainNav.style.backgroundColor = 'var(--primary-dark)';
                mainNav.style.padding = '1rem';
                mainNav.style.gap = '0.5rem';
                mainNav.style.zIndex = '99';
            }
        };

        menuToggle.addEventListener('click', toggleMenu);

        // Fechar menu ao clicar em um item (mobile)
        const navItems = document.querySelectorAll('.main-navigation a');
        navItems.forEach(item => {
            item.addEventListener('click', function() {
                if (window.innerWidth <= 992) {
                    menuToggle.classList.remove('active');
                    mainNav.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            });
        });
    };

    // =============================================
    // Funcionalidade de Busca
    // =============================================
    const initSearch = function() {
        const searchIcon = document.querySelector('.search-icon');
        const searchToggle = document.querySelector('.search-toggle');
        const searchContainer = document.querySelector('.search-container');
        
        const toggleSearch = function() {
            if (searchContainer) {
                searchContainer.classList.toggle('active');
                
                if (searchContainer.classList.contains('active')) {
                    searchContainer.querySelector('input').focus();
                }
            }
        };

        if (searchIcon) {
            searchIcon.addEventListener('click', function(e) {
                e.preventDefault();
                toggleSearch();
            });
        }

        if (searchToggle) {
            searchToggle.addEventListener('click', toggleSearch);
        }
    };

    // =============================================
    // Responsividade
    // =============================================
    const handleResponsive = function() {
        const mainNav = document.querySelector('.main-navigation ul');
        if (!mainNav) return;

        if (window.innerWidth > 992) {
            // Desktop
            mainNav.style.display = 'flex';
            document.body.style.overflow = 'auto';
        } else {
            // Mobile
            if (!document.querySelector('.menu-toggle').classList.contains('active')) {
                mainNav.style.display = 'none';
            }
        }
    };

    // =============================================
    // Inicialização de todos os componentes
    // =============================================
    const initAll = function() {
        initSlider();
        initMobileMenu();
        initSearch();
        handleResponsive();

        // Atualizar ao redimensionar
        window.addEventListener('resize', handleResponsive);
    };

    initAll();

    // =============================================
    // Outras Interações
    // =============================================
    
    // Suavizar scroll para âncoras
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                targetElement.scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });

    // Adicionar classe ao scroll
    window.addEventListener('scroll', function() {
        const header = document.querySelector('.site-header');
        if (window.scrollY > 50) {
            header.classList.add('scrolled');
        } else {

            header.classList.remove('scrolled');
        }
    });
});