document.addEventListener('DOMContentLoaded', function () {
    initEvaluacionUrgencia();
});

function initEvaluacionUrgencia() {
    const form = document.getElementById('ticket-form');
    if (!form) return;

    const inputs = form.querySelectorAll('.urg-input');
    const badge = document.getElementById('urg-prioridad');
    const box = document.getElementById('urg-result');

    function nivelDeUrgencia(impacto) {
        if (impacto === 'total') return 'urgente';
        if (impacto === 'parcial') return 'alta';
        return 'baja';
    }

    function calcular() {
        const impacto = form.elements['impacto'] ? form.elements['impacto'].value : 'ninguno';
        const detener = form.elements['detener_operaciones'] ? form.elements['detener_operaciones'].value : 'no';
        const reapertura = form.elements['reapertura'] ? form.elements['reapertura'].value : 'no';

        // Se detuvo operaciones => urgencia máxima.
        if (detener === 'si') return 'urgente';

        let prioridad = nivelDeUrgencia(impacto);
        if (reapertura === 'si') {
            const niveles = ['baja', 'media', 'alta', 'urgente'];
            const idx = niveles.indexOf(prioridad);
            if (idx > -1 && idx < niveles.length - 1) prioridad = niveles[idx + 1];
        }
        return prioridad;
    }

    const colores = { baja: 'bg-success', media: 'bg-primary', alta: 'bg-warning text-dark', urgente: 'bg-danger' };

    function actualizar() {
        const p = calcular();
        badge.className = 'badge ' + (colores[p] || 'bg-secondary') + ' fs-6';
        badge.textContent = p.charAt(0).toUpperCase() + p.slice(1);
        box.classList.remove('alert-info');
        box.classList.add('alert-info');
    }

    inputs.forEach(function (i) {
        i.addEventListener('change', actualizar);
    });
    actualizar();
}
