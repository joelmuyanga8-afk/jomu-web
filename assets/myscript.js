// Carousel functionality
const carousels = document.querySelectorAll('.carousel');
carousels.forEach((carouselEl) => {
    carouselEl.addEventListener('slide.bs.carousel', (event) => {
        carousels.forEach((otherCarousel) => {
            if (otherCarousel !== carouselEl && window.bootstrap?.Carousel) {
                bootstrap.Carousel.getOrCreateInstance(otherCarousel).to(event.to);
            }
        });
    });
});

// Filters keypoints active functionality.
const items = document.querySelectorAll('.filterpointvendorshops');
items.forEach((filterpointvendorshops) => {
    filterpointvendorshops.addEventListener('click', () => {
        items.forEach((item) => item.classList.remove('active'));
        filterpointvendorshops.classList.add('active');
    });
});

// Filters keypoints functionality.
window.showContent = function (type) {
    const target = document.getElementById('filter-info');
    const source = document.getElementById(`${type}-content`);

    if (target && source) {
        target.innerHTML = source.innerHTML;
    }
};

// Filter list functionality.
function toggleAnswer(element) {
    const faqanswer = element?.nextElementSibling;
    if (!faqanswer) return;

    const isOpen = faqanswer.style.maxHeight;

    document.querySelectorAll('.faqanswer').forEach((answer) => {
        answer.style.maxHeight = null;
        answer.style.paddingTop = '0';
        answer.style.paddingBottom = '0';
    });

    if (!isOpen) {
        faqanswer.style.maxHeight = 'max-content';
        faqanswer.style.paddingTop = '6px';
        faqanswer.style.paddingBottom = '15px';
    }
}

// Change navbar color and logo upon scrolling.
window.addEventListener('scroll', function () {
    const navbar = document.querySelector('.navbar-terms');
    const logo = document.getElementById('logo-change');
    const isScrolled = window.scrollY > 110;

    if (navbar) {
        navbar.classList.toggle('navbar-terms-scrolled', isScrolled);
    }

    if (logo) {
        logo.src = isScrolled
            ? '/assets/images/JoMu logo redesigned.png'
            : '/assets/images/JoMu black and white.png';
    }
});

// Back to top functionality.
const btn = document.getElementById('backToTop');
if (btn) {
    window.addEventListener('scroll', () => {
        btn.style.display = window.scrollY > 400 ? 'block' : 'none';
    });

    btn.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}
