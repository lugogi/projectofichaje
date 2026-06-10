/**
 * Utilidades para mostrar duraciones de fichaje.
 */
export function formatMinutes(minutes) {
    if (!minutes || minutes < 1) return '0 min';
    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    if (h === 0) return `${m} min`;
    if (m === 0) return `${h} h`;
    return `${h} h ${m} min`;
}

export function rolLabel(rol) {
    const labels = {
        admin: 'Administración',
        manager: 'Encargado',
        employee: 'Empleado',
    };
    return labels[rol] ?? rol;
}
