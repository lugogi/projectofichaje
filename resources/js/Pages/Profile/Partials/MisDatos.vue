<script setup>
defineProps({
    usuario: {
        type: Object,
        required: true,
    },
});

// Devuelve una etiqueta legible para el rol del empleado
const etiquetaRol = (rol) => {
    const mapa = {
        admin: 'Administrador (IT)',
        manager: 'Encargado de departamento',
        employee: 'Empleado',
        empleado: 'Empleado',
    };
    return mapa[rol] ?? rol;
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">
                Mis datos
            </h2>

            <p class="mt-1 text-sm text-gray-600">
                Si algún dato no es correcto, ponte en contacto con tu encargado o con el departamento de IT.
            </p>
        </header>

        <dl class="mt-6 grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm font-medium text-gray-500">Nombre</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ usuario.nombre || '—' }}</dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-gray-500">Email</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ usuario.email || '—' }}</dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-gray-500">Código de empleado</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ usuario.codigo_empleado || '—' }}</dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-gray-500">Rol</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ etiquetaRol(usuario.rol) }}</dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-gray-500">Centro de trabajo</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ usuario.centro_trabajo || '—' }}</dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-gray-500">Calendario laboral</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ usuario.calendario_laboral || '—' }}</dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-gray-500">Fecha de contratación</dt>
                <dd class="mt-1 text-sm text-gray-900">{{ usuario.fecha_contratacion || '—' }}</dd>
            </div>
        </dl>
    </section>
</template>
