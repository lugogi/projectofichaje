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
});

const herramientas = [
    {
        titulo: 'Revisar solicitudes',
        descripcion:
            'Aprobar o rechazar ausencias y correcciones de los trabajadores de tu equipo.',
        ruta: 'manager.solicitudes.index',
        destacado: true,
    },
    {
        titulo: 'Ausencias y vacaciones',
        descripcion:
            'Registrar vacaciones o bajas de tu equipo sin pasar por solicitud del empleado.',
        ruta: 'manager.absences.index',
    },
    {
        titulo: 'Exportar registros',
        descripcion:
            'Descargar fichajes de tu equipo en Excel, PDF o JSON por trabajador y mes.',
        ruta: 'manager.exports.index',
    },
];
</script>

<template>
    <Head title="Encargado" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-slate-900">
                    Encargado
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Herramientas de gestión de tu departamento
                </p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div class="mb-6 grid gap-4 sm:grid-cols-2">
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
                        <p class="text-sm text-slate-500">Trabajadores asignados</p>
                        <p class="mt-1 text-3xl font-bold text-slate-900">
                            {{ empleadosEquipo }}
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
                                v-if="herramienta.destacado && pendientes > 0"
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
