<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    empleados: {
        type: Array,
        default: () => [],
    },
    mesActual: {
        type: String,
        required: true,
    },
    esAdmin: {
        type: Boolean,
        default: false,
    },
    homeRoute: {
        type: String,
        required: true,
    },
});

const tituloPanel = computed(() =>
    props.esAdmin ? 'Administración' : 'Encargado',
);

const empleadoId = ref(props.empleados[0]?.id ?? '');
const mes = ref(props.mesActual);

const empleadoSeleccionado = computed(() =>
    props.empleados.find((e) => e.id === empleadoId.value),
);

const puedeExportar = computed(() => Boolean(empleadoId.value && mes.value));

const urlExport = (formato) => {
    const params = new URLSearchParams({
        month: mes.value,
        employee_id: empleadoId.value,
    });
    return route('profile.export', { format: formato }) + '?' + params.toString();
};

const etiquetaRol = (rol) => {
    const mapa = {
        admin: 'Administración',
        manager: 'Encargado',
        employee: 'Empleado',
    };
    return mapa[rol] ?? rol;
};
</script>

<template>
    <Head title="Exportar registros del equipo" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <Link
                    :href="route(homeRoute)"
                    class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                >
                    ← Volver a {{ tituloPanel }}
                </Link>
                <h2 class="mt-2 text-xl font-semibold leading-tight text-slate-900">
                    Exportar registros del equipo
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{
                        esAdmin
                            ? 'Como administrador puedes descargar los registros de cualquier empleado.'
                            : 'Como encargado puedes descargar los registros de los trabajadores de tu equipo.'
                    }}
                </p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <div
                    v-if="empleados.length === 0"
                    class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-900"
                >
                    No hay trabajadores asignados para exportar.
                    <span v-if="!esAdmin">
                        Contacta con administración para vincular empleados a tu
                        departamento.
                    </span>
                </div>

                <div
                    v-else
                    class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                >
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label
                                for="empleado"
                                class="block text-sm font-medium text-slate-700"
                            >
                                Trabajador
                            </label>
                            <select
                                id="empleado"
                                v-model="empleadoId"
                                class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option
                                    v-for="emp in empleados"
                                    :key="emp.id"
                                    :value="emp.id"
                                >
                                    {{ emp.nombre }}
                                    <template v-if="emp.codigo">
                                        ({{ emp.codigo }})
                                    </template>
                                    — {{ etiquetaRol(emp.rol) }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label
                                for="mes-equipo"
                                class="block text-sm font-medium text-slate-700"
                            >
                                Mes
                            </label>
                            <input
                                id="mes-equipo"
                                v-model="mes"
                                type="month"
                                class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>

                        <div
                            v-if="empleadoSeleccionado"
                            class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600"
                        >
                            <p class="font-medium text-slate-800">
                                {{ empleadoSeleccionado.nombre }}
                            </p>
                            <p>{{ empleadoSeleccionado.email }}</p>
                        </div>
                    </div>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <a
                            :href="puedeExportar ? urlExport('excel') : '#'"
                            class="inline-flex rounded-lg px-4 py-2 text-sm font-semibold text-white"
                            :class="
                                puedeExportar
                                    ? 'bg-emerald-600 hover:bg-emerald-700'
                                    : 'cursor-not-allowed bg-slate-300'
                            "
                            @click="!puedeExportar && $event.preventDefault()"
                        >
                            Descargar Excel
                        </a>
                        <a
                            :href="puedeExportar ? urlExport('pdf') : '#'"
                            class="inline-flex rounded-lg px-4 py-2 text-sm font-semibold text-white"
                            :class="
                                puedeExportar
                                    ? 'bg-rose-600 hover:bg-rose-700'
                                    : 'cursor-not-allowed bg-slate-300'
                            "
                            @click="!puedeExportar && $event.preventDefault()"
                        >
                            Descargar PDF
                        </a>
                        <a
                            :href="puedeExportar ? urlExport('json') : '#'"
                            class="inline-flex rounded-lg px-4 py-2 text-sm font-semibold text-white"
                            :class="
                                puedeExportar
                                    ? 'bg-indigo-600 hover:bg-indigo-700'
                                    : 'cursor-not-allowed bg-slate-300'
                            "
                            @click="!puedeExportar && $event.preventDefault()"
                        >
                            Descargar JSON
                        </a>
                    </div>

                    <p class="mt-4 text-xs text-slate-500">
                        La exportación respeta el tope contractual mensual del
                        trabajador (sin horas extra), igual que en su perfil.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
