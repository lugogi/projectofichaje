<script setup>
import StaffClockFeed from '@/Components/StaffClockFeed.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    pendientes: {
        type: Number,
        default: 0,
    },
    empleadosEquipo: {
        type: Number,
        default: 0,
    },
    estadisticas: {
        type: Object,
        default: () => ({}),
    },
});

const herramientas = [
    {
        titulo: 'Revisar solicitudes',
        descripcion:
            'Aprobar o rechazar ausencias y correcciones de fichaje de los empleados.',
        ruta: 'admin.solicitudes.index',
        destacado: true,
        badgeKey: 'pendientes',
    },
    {
        titulo: 'Ausencias y vacaciones',
        descripcion:
            'Registrar vacaciones o bajas directamente sin que el empleado tenga que solicitarlas.',
        ruta: 'admin.absences.index',
    },
    {
        titulo: 'Gestión de empleados',
        descripcion: 'Alta, edición, asignación de encargado y baja de trabajadores.',
        ruta: 'admin.employees.index',
    },
    {
        titulo: 'Fichada manual',
        descripcion:
            'Registrar entradas o salidas olvidadas por un empleado (con motivo obligatorio).',
        ruta: 'admin.manual-clock.index',
    },
    {
        titulo: 'Horarios y turnos',
        descripcion: 'Configurar el horario semanal de cada empleado.',
        ruta: 'admin.schedules.index',
    },
    {
        titulo: 'Salas y redes',
        descripcion: 'Gestionar las IPs autorizadas para fichar desde cada sala.',
        ruta: 'admin.zones.index',
    },
    {
        titulo: 'Informes del equipo',
        descripcion:
            'Resumen mensual de horas teóricas, fichadas y extras para Laboral.',
        ruta: 'admin.reports.index',
    },
    {
        titulo: 'Exportar registros',
        descripcion:
            'Descargar fichajes del equipo en Excel, PDF o JSON por trabajador y mes.',
        ruta: 'admin.exports.index',
    },
    {
        titulo: 'Registro de auditoría',
        descripcion: 'Historial inmutable de acciones administrativas.',
        ruta: 'admin.audit.index',
    },
];
</script>

<template>
    <Head title="Administración" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-slate-900">
                    Administración
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Herramientas de gestión para el departamento de informática
                </p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div
                        class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4"
                    >
                        <p class="text-sm text-amber-800">Solicitudes pendientes</p>
                        <p class="mt-1 text-3xl font-bold text-amber-900">
                            {{ pendientes }}
                        </p>
                    </div>
                    <div
                        class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm"
                    >
                        <p class="text-sm text-slate-500">Empleados activos</p>
                        <p class="mt-1 text-3xl font-bold text-slate-900">
                            {{ estadisticas.empleados_activos ?? empleadosEquipo }}
                        </p>
                    </div>
                    <div
                        class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4"
                    >
                        <p class="text-sm text-emerald-800">Trabajando ahora</p>
                        <p class="mt-1 text-3xl font-bold text-emerald-900">
                            {{ estadisticas.trabajando_ahora ?? 0 }}
                        </p>
                    </div>
                    <div
                        class="rounded-2xl border border-indigo-200 bg-indigo-50 px-5 py-4"
                    >
                        <p class="text-sm text-indigo-800">Ficharon hoy</p>
                        <p class="mt-1 text-3xl font-bold text-indigo-900">
                            {{ estadisticas.ficharon_hoy ?? 0 }}
                        </p>
                    </div>
                </div>

                <StaffClockFeed />

                <div class="grid gap-4 sm:grid-cols-2">
                    <Link
                        v-for="herramienta in herramientas"
                        :key="herramienta.ruta"
                        :href="route(herramienta.ruta)"
                        class="group rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:border-indigo-300 hover:shadow-md"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3
                                    class="text-lg font-semibold text-slate-900 group-hover:text-indigo-700"
                                >
                                    {{ herramienta.titulo }}
                                </h3>
                                <p class="mt-2 text-sm text-slate-500">
                                    {{ herramienta.descripcion }}
                                </p>
                            </div>
                            <span
                                v-if="
                                    herramienta.destacado &&
                                    herramienta.badgeKey === 'pendientes' &&
                                    pendientes > 0
                                "
                                class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800"
                            >
                                {{ pendientes }}
                            </span>
                        </div>
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
