// ===== NAVBAR SCROLL EFFECT =====
const header = document.getElementById('header');
window.addEventListener('scroll', () => {
  header.classList.toggle('scrolled', window.scrollY > 20);
});

// ===== MOBILE MENU =====
const hamburger = document.getElementById('hamburger');
const mobileMenu = document.getElementById('mobileMenu');

function toggleMenu() {
  mobileMenu.classList.toggle('open');
  hamburger.setAttribute('aria-expanded', mobileMenu.classList.contains('open'));
}

hamburger.addEventListener('click', toggleMenu);

document.querySelectorAll('.mobile-menu a').forEach(link => {
  link.addEventListener('click', () => {
    mobileMenu.classList.remove('open');
    hamburger.setAttribute('aria-expanded', 'false');
  });
});

// ===== SCROLL REVEAL =====
const revealElements = document.querySelectorAll('.step-card, .area-card, .dif-item, .section-text, .section-image');
const revealObserver = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.classList.add('visible');
    }
  });
}, { threshold: 0.15, rootMargin: '0px 0px -30px 0px' });

revealElements.forEach(el => {
  el.classList.add('reveal');
  revealObserver.observe(el);
});

// ===== SMOOTH SCROLL =====
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
  anchor.addEventListener('click', function(e) {
    const targetId = this.getAttribute('href');
    if (targetId === '#') return;
    const target = document.querySelector(targetId);
    if (target) {
      e.preventDefault();
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  });
});

// ===== FUNCIÓN PARA ANIMAR TEXTO "EXPLOSIÓN" =====
function explodeText(element, delayBase = 0.12, startDelay = 0) {
    if (!element) return;
    const text = element.textContent;
    const words = text.split(' ');
    element.innerHTML = words.map((word, i) => {
        const delay = startDelay + i * delayBase;
        return `<span style="animation-delay: ${delay}s">${word}</span>`;
    }).join(' ');
}

// ===== ANIMAR TÍTULO DEL HERO AL CARGAR =====
document.addEventListener('DOMContentLoaded', function() {
    const heroTitle = document.getElementById('heroTitle');
    if (heroTitle) {
        explodeText(heroTitle, 0.12, 0);
    }
});

// ===== ANIMAR TÍTULO DE CONTACTO AL HACER SCROLL =====
document.addEventListener('DOMContentLoaded', function() {
    const contactTitle = document.getElementById('contactTitle');
    if (!contactTitle) return;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                explodeText(contactTitle, 0.08, 0.2);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.3 });

    observer.observe(contactTitle);
});

// ===== TYPEWRITER PARA "Con las mejores condiciones..." =====
document.addEventListener('DOMContentLoaded', function() {
  const text = "Con las mejores condiciones de pago para importar sin descapitalizarte";
  const container = document.getElementById('typewriterContent');
  const cursor = document.getElementById('typewriterCursor');
  if (!container || !cursor) return;

  let index = 0;
  let isDeleting = false;

  function type() {
    if (index < text.length) {
      container.textContent += text.charAt(index);
      index++;
      setTimeout(type, 30); // velocidad de escritura
    } else {
      // Terminó de escribir, ocultar cursor después de 3 segundos
      setTimeout(() => {
        cursor.classList.add('hidden');
      }, 3000);
    }
  }

  // Iniciar después de 1.5s para que el hero cargue
  setTimeout(type, 1500);
});

// ===== ANIMACIÓN DE ENTRADA DE PASOS (ZOOM OUT) =====
document.addEventListener('DOMContentLoaded', function() {
  const stepCards = document.querySelectorAll('.step-card');
  if (stepCards.length === 0) return;

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        // Añadir clase 'visible' con delay escalonado
        const card = entry.target;
        const index = Array.from(stepCards).indexOf(card);
        setTimeout(() => {
          card.classList.add('visible');
        }, 150 + index * 120);
        // Dejar de observar una vez activado
        observer.unobserve(card);
      }
    });
  }, { threshold: 0.2, rootMargin: '0px 0px -40px 0px' });

  stepCards.forEach(card => observer.observe(card));
});

// ===== SLIDER DE RUBROS =====
document.addEventListener('DOMContentLoaded', function() {
    const track = document.getElementById('sliderTrack');
    const slides = track ? track.querySelectorAll('.slider-slide') : [];
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const dotsContainer = document.getElementById('sliderDots');

    if (!track || slides.length === 0) return;

    let currentIndex = 0;
    const totalSlides = slides.length;

    // Crear dots
    slides.forEach((_, i) => {
        const dot = document.createElement('span');
        if (i === 0) dot.classList.add('active');
        dot.dataset.index = i;
        dot.addEventListener('click', () => goToSlide(i));
        dotsContainer.appendChild(dot);
    });

    const dots = dotsContainer.querySelectorAll('span');

    function goToSlide(index) {
        if (index < 0) index = totalSlides - 1;
        if (index >= totalSlides) index = 0;
        currentIndex = index;
        track.style.transform = `translateX(-${currentIndex * 100}%)`;
        dots.forEach((dot, i) => {
            dot.classList.toggle('active', i === currentIndex);
        });
    }

    // Eventos
    prevBtn.addEventListener('click', () => goToSlide(currentIndex - 1));
    nextBtn.addEventListener('click', () => goToSlide(currentIndex + 1));

    // Inicializar
    goToSlide(0);

    // Autoplay
    let autoPlay = setInterval(() => goToSlide(currentIndex + 1), 5000);
    const container = document.querySelector('.slider-container');
    container.addEventListener('mouseenter', () => clearInterval(autoPlay));
    container.addEventListener('mouseleave', () => {
        autoPlay = setInterval(() => goToSlide(currentIndex + 1), 5000);
    });
});

// ===== POR QUÉ NOWA - ANIMACIÓN CIRCULAR =====
document.addEventListener('DOMContentLoaded', function() {
    const section = document.getElementById('diferenciales');
    const items = document.querySelectorAll('.circle-item');
    const path = document.querySelector('.circle-path');
    const totalItems = items.length;

    if (!section || items.length === 0) return;

    const circumference = 2 * Math.PI * 200;
    path.style.strokeDasharray = circumference;
    path.style.strokeDashoffset = circumference;

    let animationStarted = false;
    let currentIndex = 0;

    function revealNext() {
        if (currentIndex >= totalItems) return;
        const item = items[currentIndex];
        item.classList.add('visible');
        const progress = (currentIndex + 1) / totalItems;
        const offset = circumference * (1 - progress);
        path.style.strokeDashoffset = offset;
        currentIndex++;
        if (currentIndex < totalItems) {
            setTimeout(revealNext, 1500);
        }
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting && !animationStarted) {
                animationStarted = true;
                setTimeout(revealNext, 400);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.2 });

    observer.observe(section);
});

// ===== MODAL PERSONALIZADO =====
function showModal(icon, title, message, isSuccess = true) {
    const modal = document.getElementById('customModal');
    const iconEl = document.getElementById('modalIcon');
    const titleEl = document.getElementById('modalTitle');
    const msgEl = document.getElementById('modalMessage');
    const btn = document.getElementById('modalBtn');

    iconEl.textContent = icon || (isSuccess ? '✅' : '❌');
    titleEl.textContent = title || (isSuccess ? '¡Éxito!' : 'Error');
    msgEl.textContent = message || (isSuccess ? 'Operación completada.' : 'Ocurrió un error.');

    // Cambiar color del botón según éxito/error
    btn.style.background = isSuccess ? 'var(--color-blue)' : '#c0392b';
    btn.style.color = 'var(--color-white)';

    modal.style.display = 'flex';

    // Cerrar al hacer clic en el botón
    btn.onclick = closeModal;

    // Cerrar al hacer clic fuera del modal (en el backdrop)
    const backdrop = modal.querySelector('.modal-backdrop');
    backdrop.onclick = closeModal;

    // Prevenir cierre accidental del contenedor
    modal.querySelector('.modal-container').onclick = (e) => e.stopPropagation();
}

function closeModal() {
    const modal = document.getElementById('customModal');
    modal.style.display = 'none';
}

// Cerrar con tecla ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});