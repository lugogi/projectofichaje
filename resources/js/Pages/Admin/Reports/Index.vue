<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    mesActual: {
        type: String,
        required: true,
    },
    mesLabel: {
        type: String,
        required: true,
    },
    estadisticas: {
        type: Object,
        default: () => ({}),
    },
    informe: {
        type: Array,
        default: () => [],
    },
    ausencias: {
        type: Array,
        default: () => [],
    },
});

const mes = ref(props.mesActual);

const aplicarMes = () => {
    router.get(route('admin.reports.index'), { month: mes.value }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const urlExport = (empleadoId, formato) => {
    const params = new URLSearchParams({
        month: mes.value,
        employee_id: empleadoId,
    });
    return route('profile.export', { format: formato }) + '?' + params.toString();
};
</script>

<template>
    <Head title="Informes del equipo" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <Link
                    :href="route('admin.index')"
                    class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                >
                    ← Volver a Administración
                </Link>
                <h2 class="mt-2 text-xl font-semibold text-slate-900">
                    Informes del equipo
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Horas teóricas, fichadas y extras por empleado — datos para
                    Laboral.
                </p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="mb-6 grid gap-4 sm:grid-cols-4">
                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                        <p class="text-xs text-slate-500">Empleados activos</p>
                        <p class="text-2xl font-bold text-slate-900">
                            {{ estadisticas.empleados_activos ?? 0 }}
                        </p>
                    </div>
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                        <p class="text-xs text-emerald-700">Trabajando ahora</p>
                        <p class="text-2xl font-bold text-emerald-900">
                            {{ estadisticas.trabajando_ahora ?? 0 }}
                        </p>
                    </div>
                    <div class="rounded-2xl border border-indigo-200 bg-indigo-50 px-4 py-3">
                        <p class="text-xs text-indigo-700">Ficharon hoy</p>
                        <p class="text-2xl font-bold text-indigo-900">
                            {{ estadisticas.ficharon_hoy ?? 0 }}
                        </p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                        <p class="text-xs text-slate-500">Salas activas</p>
                        <p class="text-2xl font-bold text-slate-900">
                            {{ estadisticas.salas_activas ?? 0 }}
                        </p>
                    </div>
                </div>

                <div class="mb-4 flex flex-wrap items-end gap-4">
                    <div>
                        <InputLabel value="Mes del informe" />
                        <input
                            v-model="mes"
                            type="month"
                            class="mt-1 rounded-md border-slate-300 shadow-sm"
                            @change="aplicarMes"
                        />
                    </div>
                    <p class="text-sm text-slate-600 capitalize">{{ mesLabel }}</p>
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">
                                    Empleado
                                </th>
                                <th class="px-4 py-3 text-right font-semibold text-slate-600">
                                    Horas teóricas
                                </th>
                                <th class="px-4 py-3 text-right font-semibold text-slate-600">
                                    Horas fichadas
                                </th>
                                <th class="px-4 py-3 text-right font-semibold text-slate-600">
                                    Horas extra
                                </th>
                                <th class="px-4 py-3 text-right font-semibold text-slate-600">
                                    Cumplimiento
                                </th>
                                <th class="px-4 py-3 text-right font-semibold text-slate-600">
                                    Exportar
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="fila in informe" :key="fila.id">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900">
                                        {{ fila.name }}
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        {{ fila.employee_code }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right text-slate-700">
                                    {{ fila.esperado.formato }}
                                </td>
                                <td class="px-4 py-3 text-right text-slate-700">
                                    {{ fila.trabajado.formato }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span
                                        :class="
                                            fila.extra.tiene_extra
                                                ? 'font-semibold text-amber-700'
                                                : 'text-slate-500'
                                        "
                                    >
                                        {{ fila.extra.formato }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-slate-700">
                                    {{ fila.porcentaje_cumplido }}%
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a
                                        :href="urlExport(fila.id, 'excel')"
                                        class="text-indigo-600 hover:text-indigo-800"
                                    >
                                        Excel
                                    </a>
                                    <a
                                        :href="urlExport(fila.id, 'pdf')"
                                        class="ml-2 text-indigo-600 hover:text-indigo-800"
                                    >
                                        PDF
                                    </a>
                                </td>
                            </tr>
                            <tr v-if="!informe.length">
                                <td
                                    colspan="6"
                                    class="px-4 py-8 text-center text-slate-500"
                                >
                                    No hay empleados activos para mostrar.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-4 py-3">
                        <h3 class="text-sm font-semibold text-slate-900">
                            Vacaciones y bajas laborales
                        </h3>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Ausencias aprobadas del mes, para que laboral vea quién no ha estado trabajando.
                        </p>
                    </div>
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">
                                    Nombre y apellidos
                                </th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">
                                    Periodo
                                </th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">
                                    Tipo
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(ausencia, index) in ausencias" :key="index">
                                <td class="px-4 py-3 font-medium text-slate-900">
                                    {{ ausencia.nombre }}
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    {{ ausencia.periodo }}
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    {{ ausencia.tipo_label }}
                                </td>
                            </tr>
                            <tr v-if="!ausencias.length">
                                <td
                                    colspan="3"
                                    class="px-4 py-8 text-center text-slate-500"
                                >
                                    Ninguna vacación ni baja laboral aprobada en este mes.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
