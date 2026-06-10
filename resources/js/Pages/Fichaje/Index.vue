<script setup>
import TodayAttendanceLog from '@/Components/Fichaje/TodayAttendanceLog.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    asistencia: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const ahora = ref(new Date());
let intervalo = null;

onMounted(() => {
    intervalo = setInterval(() => (ahora.value = new Date()), 1000);
});
onUnmounted(() => clearInterval(intervalo));

const horaActual = computed(() =>
    ahora.value.toLocaleTimeString('es-ES', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }),
);
const fechaActual = computed(() =>
    ahora.value.toLocaleDateString('es-ES', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }),
);

const form = useForm({});
const mensajeExito = ref(null);

watch(
    () => page.props.flash?.success,
    (msg) => {
        if (msg) {
            mensajeExito.value = msg;
            setTimeout(() => (mensajeExito.value = null), 5000);
        }
    },
    { immediate: true },
);

const fichar = () => {
    form.post(route('fichaje.store'), {
        preserveScroll: true,
    });
};

const textoBoton = computed(() =>
    props.asistencia.es_entrada ? 'FICHAR ENTRADA' : 'FICHAR SALIDA',
);
</script>

<template>
    <Head title="Fichar" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-slate-900">
                        Registro horario
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Ficha tu entrada y salida · Registro legal inmutable
                    </p>
                </div>
                <Link
                    :href="route('dashboard')"
                    class="text-sm font-medium text-slate-600 hover:text-slate-900"
                >
                    ← Volver al inicio
                </Link>
            </div>
        </template>

        <div class="bg-slate-50/80 py-8">
            <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
                <!-- Mensaje éxito -->
                <div
                    v-if="asistencia.ausencia_hoy"
                    class="flex items-center gap-3 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm font-medium text-indigo-900"
                >
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    Hoy tienes {{ asistencia.ausencia_hoy.label }} aprobada. No debes fichar.
                </div>

                <div
                    v-if="mensajeExito"
                    class="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800"
                >
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ mensajeExito }}
                </div>

                <div class="grid gap-6 lg:grid-cols-5">
                    <!-- Columna fichaje -->
                    <div class="space-y-6 lg:col-span-2">
                        <div
                            class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                        >
                            <div
                                class="px-6 py-8 text-center"
                                :class="
                                    asistencia.estado === 'trabajando'
                                        ? 'bg-gradient-to-b from-emerald-50 to-white'
                                        : ''
                                "
                            >
                                <p class="text-sm capitalize text-slate-500">
                                    {{ fechaActual }}
                                </p>
                                <p
                                    class="mt-2 font-mono text-5xl font-bold tracking-tight text-slate-900 tabular-nums sm:text-6xl"
                                >
                                    {{ horaActual }}
                                </p>
                                <div
                                    class="mx-auto mt-4 inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold"
                                    :class="
                                        asistencia.estado === 'trabajando'
                                            ? 'bg-emerald-100 text-emerald-800'
                                            : 'bg-slate-100 text-slate-600'
                                    "
                                >
                                    <span
                                        v-if="asistencia.estado === 'trabajando'"
                                        class="relative flex h-2 w-2"
                                    >
                                        <span
                                            class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-500 opacity-75"
                                        ></span>
                                        <span
                                            class="relative inline-flex h-2 w-2 rounded-full bg-emerald-600"
                                        ></span>
                                    </span>
                                    {{
                                        asistencia.estado === 'trabajando'
                                            ? `Trabajando desde ${asistencia.sesion_abierta_desde}`
                                            : 'Sin jornada activa'
                                    }}
                                </div>
                            </div>

                            <div class="border-t border-slate-100 p-6">
                                <button
                                    type="button"
                                    :disabled="form.processing || !asistencia.puede_fichar"
                                    class="group relative w-full overflow-hidden rounded-2xl py-10 text-xl font-bold text-white shadow-lg transition active:scale-[0.98] disabled:opacity-60 sm:text-2xl"
                                    :class="
                                        asistencia.es_entrada
                                            ? 'bg-gradient-to-br from-emerald-500 to-emerald-700 hover:from-emerald-600 hover:to-emerald-800'
                                            : 'bg-gradient-to-br from-rose-500 to-rose-700 hover:from-rose-600 hover:to-rose-800'
                                    "
                                    @click="fichar"
                                >
                                    <span
                                        v-if="form.processing"
                                        class="absolute inset-0 flex items-center justify-center bg-black/20"
                                    >
                                        Registrando…
                                    </span>
                                    {{ textoBoton }}
                                </button>

                                <p
                                    v-if="form.errors.fichaje"
                                    class="mt-4 rounded-xl bg-red-50 px-4 py-3 text-center text-sm font-medium text-red-700"
                                >
                                    {{ form.errors.fichaje }}
                                </p>

                                <p class="mt-4 text-center text-xs text-slate-400">
                                    La hora la registra el servidor (no se puede modificar manualmente).
                                </p>
                            </div>
                        </div>

                        <div
                            class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                        >
                            <p class="text-xs font-semibold uppercase text-slate-500">
                                Resumen rápido
                            </p>
                            <dl class="mt-3 grid grid-cols-3 gap-3 text-center">
                                <div class="rounded-xl bg-slate-50 py-3">
                                    <dt class="text-[10px] font-medium uppercase text-slate-500">
                                        Hoy
                                    </dt>
                                    <dd class="mt-1 font-mono text-lg font-bold text-emerald-700">
                                        {{ asistencia.resumen.hoy.formato }}
                                    </dd>
                                </div>
                                <div class="rounded-xl bg-slate-50 py-3">
                                    <dt class="text-[10px] font-medium uppercase text-slate-500">
                                        Semana
                                    </dt>
                                    <dd class="mt-1 font-mono text-lg font-bold text-slate-800">
                                        {{ asistencia.resumen.semana.formato }}
                                    </dd>
                                </div>
                                <div class="rounded-xl bg-slate-50 py-3">
                                    <dt class="text-[10px] font-medium uppercase text-slate-500">
                                        Mes
                                    </dt>
                                    <dd class="mt-1 font-mono text-lg font-bold text-slate-800">
                                        {{ asistencia.resumen.mes.formato }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- Columna registro unificado -->
                    <div class="lg:col-span-3">
                        <TodayAttendanceLog
                            :sesiones="asistencia.sesiones_hoy"
                            :registros="asistencia.registros_hoy"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
