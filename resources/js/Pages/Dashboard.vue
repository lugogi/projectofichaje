<script setup>
import HoursSummary from '@/Components/Fichaje/HoursSummary.vue';
import MonthlyTarget from '@/Components/Fichaje/MonthlyTarget.vue';
import TodayAttendanceLog from '@/Components/Fichaje/TodayAttendanceLog.vue';
import WeeklyHistory from '@/Components/Fichaje/WeeklyHistory.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { rolLabel } from '@/composables/useDuration';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    asistencia: {
        type: Object,
        required: true,
    },
    usuario: {
        type: Object,
        required: true,
    },
    objetivoMensual: {
        type: Object,
        required: true,
    },
});

const saludo = computed(() => {
    const h = new Date().getHours();
    if (h < 12) return 'Buenos días';
    if (h < 20) return 'Buenas tardes';
    return 'Buenas noches';
});
</script>

<template>
    <Head title="Inicio" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-slate-900">
                        {{ saludo }}, {{ usuario.nombre }}
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ rolLabel(usuario.rol) }} · Panel de registro horario
                    </p>
                </div>
                <Link
                    v-if="asistencia.puede_fichar"
                    :href="route('fichaje.index')"
                    class="inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold text-white shadow-md transition hover:opacity-95"
                    :class="
                        asistencia.es_entrada
                            ? 'bg-emerald-600 hover:bg-emerald-700'
                            : 'bg-rose-600 hover:bg-rose-700'
                    "
                >
                    {{ asistencia.es_entrada ? 'Fichar entrada' : 'Fichar salida' }}
                </Link>
                <span
                    v-else
                    class="inline-flex items-center rounded-xl bg-indigo-100 px-4 py-2 text-sm font-semibold text-indigo-800"
                >
                    {{ asistencia.ausencia_hoy?.label }} hoy
                </span>
            </div>
        </template>

        <div class="bg-slate-50/80 py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div
                    v-if="asistencia.ausencia_hoy"
                    class="rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900"
                >
                    Hoy tienes <strong>{{ asistencia.ausencia_hoy.label }}</strong> aprobada.
                    No debes fichar durante este periodo.
                </div>

                <!-- Estado actual -->
                <div
                    class="overflow-hidden rounded-2xl border shadow-sm"
                    :class="
                        asistencia.estado === 'trabajando'
                            ? 'border-emerald-200 bg-gradient-to-r from-emerald-600 to-emerald-700'
                            : 'border-slate-200 bg-white'
                    "
                >
                    <div class="flex flex-wrap items-center justify-between gap-4 p-6">
                        <div>
                            <p
                                class="text-sm font-medium"
                                :class="
                                    asistencia.estado === 'trabajando'
                                        ? 'text-emerald-100'
                                        : 'text-slate-500'
                                "
                            >
                                Estado actual
                            </p>
                            <p
                                class="mt-1 text-2xl font-bold"
                                :class="
                                    asistencia.estado === 'trabajando'
                                        ? 'text-white'
                                        : 'text-slate-900'
                                "
                            >
                                {{
                                    asistencia.estado === 'trabajando'
                                        ? 'En jornada laboral'
                                        : 'Fuera de jornada'
                                }}
                            </p>
                            <p
                                v-if="asistencia.sesion_abierta_desde"
                                class="mt-2 text-sm"
                                :class="
                                    asistencia.estado === 'trabajando'
                                        ? 'text-emerald-50'
                                        : 'text-slate-600'
                                "
                            >
                                Desde las {{ asistencia.sesion_abierta_desde }}
                            </p>
                        </div>
                        <div
                            class="rounded-2xl px-6 py-4 text-center"
                            :class="
                                asistencia.estado === 'trabajando'
                                    ? 'bg-white/15 backdrop-blur'
                                    : 'bg-slate-100'
                            "
                        >
                            <p
                                class="text-xs font-semibold uppercase tracking-wide"
                                :class="
                                    asistencia.estado === 'trabajando'
                                        ? 'text-emerald-100'
                                        : 'text-slate-500'
                                "
                            >
                                Total hoy
                            </p>
                            <p
                                class="mt-1 font-mono text-3xl font-bold tabular-nums"
                                :class="
                                    asistencia.estado === 'trabajando'
                                        ? 'text-white'
                                        : 'text-emerald-700'
                                "
                            >
                                {{ asistencia.resumen.hoy.formato }}
                            </p>
                            <p
                                class="text-sm"
                                :class="
                                    asistencia.estado === 'trabajando'
                                        ? 'text-emerald-100'
                                        : 'text-slate-500'
                                "
                            >
                                {{ asistencia.resumen.hoy.decimal }} horas
                            </p>
                        </div>
                    </div>
                </div>

                <HoursSummary
                    :resumen="asistencia.resumen"
                    :estado="asistencia.estado"
                    :sesion-abierta-minutos="asistencia.sesion_abierta_minutos"
                />

                <MonthlyTarget :objetivo="objetivoMensual" />

                <div class="grid gap-6 lg:grid-cols-2">
                    <TodayAttendanceLog
                        :sesiones="asistencia.sesiones_hoy"
                        :registros="asistencia.registros_hoy"
                        compact
                    />
                    <WeeklyHistory :historial="asistencia.historial" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
