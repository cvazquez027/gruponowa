// ===== FORMULARIO DE CONTACTO =====
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('contactForm');
    if (!form) return;

    // ===== 1. TIMESTAMP (tiempo de carga) =====
    const loadTime = Date.now();
    document.getElementById('timestamp').value = loadTime;

    // ===== 2. MANEJO DEL ENVÍO =====
    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        // === Validar honeypot (campo oculto) ===
        const honeypot = document.getElementById('website').value;
        if (honeypot && honeypot.trim() !== '') {
            console.warn('Honeypot activado: posible bot');
            alert('Error al enviar. Intente nuevamente.');
            return;
        }

        // === Validar timestamp (mínimo 3 segundos) ===
        const now = Date.now();
        const elapsed = (now - loadTime) / 1000;
        if (elapsed < 3) {
            alert('Por favor, espere unos segundos antes de enviar.');
            return;
        }

        // === Validar campos obligatorios ===
        const nombre = document.getElementById('nombre').value.trim();
        const whatsapp = document.getElementById('whatsapp').value.trim();
        const rubro = document.getElementById('rubro').value;
        if (!nombre || !whatsapp || !rubro) {
            alert('Por favor, completá todos los campos obligatorios (*).');
            return;
        }

        // === Obtener token de reCAPTCHA ===
        const siteKey = '6LcNfiUtAAAAAPfVdC3SMeerDwZIqOju8Yta_X5t';
        try {
            const token = await grecaptcha.execute(siteKey, { action: 'submit' });
            document.getElementById('recaptcha_token').value = token;
        } catch (error) {
            console.error('Error al obtener reCAPTCHA:', error);
            alert('Error de seguridad. Por favor, recargue la página e intente nuevamente.');
            return;
        }

        // === Preparar datos para enviar ===
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        // === Enviar al backend ===
        const btn = form.querySelector('button[type="submit"]');
        const originalText = btn.textContent;
        btn.textContent = 'Enviando...';
        btn.disabled = true;

        try {
            const response = await fetch('/api/form-handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                alert('¡Consulta enviada con éxito! Te responderemos a la brevedad.');
                form.reset();
                // Resetear timestamp para evitar reenvíos rápidos
                document.getElementById('timestamp').value = Date.now();
            } else {
                alert('Error: ' + (result.message || 'Hubo un problema al enviar.'));
            }
        } catch (error) {
            console.error('Error de red:', error);
            alert('Error de conexión. Por favor, intentá de nuevo.');
        } finally {
            btn.textContent = originalText;
            btn.disabled = false;
        }
    });
});