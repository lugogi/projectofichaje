<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    panel: {
        type: String,
        required: true,
    },
    homeRoute: {
        type: String,
        required: true,
    },
    solicitudes: {
        type: Array,
        default: () => [],
    },
    filtroEstado: {
        type: String,
        default: null,
    },
    reviewAbsenceRoute: {
        type: String,
        required: true,
    },
    reviewCorrectionRoute: {
        type: String,
        required: true,
    },
});

const page = usePage();
const mensajeExito = ref(null);
const notaRevision = ref({});
const procesando = ref(null);

watch(
    () => page.props.flash?.success,
    (msg) => {
        if (msg) {
            mensajeExito.value = msg;
            setTimeout(() => (mensajeExito.value = null), 6000);
        }
    },
    { immediate: true },
);

const tituloPanel = computed(() =>
    props.panel === 'admin' ? 'Administración' : 'Encargado',
);

const solicitudesRoute = computed(() =>
    props.panel === 'admin'
        ? 'admin.solicitudes.index'
        : 'manager.solicitudes.index',
);

const filtros = [
    { value: null, label: 'Todas' },
    { value: 'pending', label: 'Pendientes' },
    { value: 'approved', label: 'Aprobadas' },
    { value: 'rejected', label: 'Rechazadas' },
];

const aplicarFiltro = (estado) => {
    router.get(
        route(solicitudesRoute.value),
        estado ? { estado } : {},
        { preserveState: true, preserveScroll: true },
    );
};

const revisar = (solicitud, action) => {
    const routeName =
        solicitud.kind === 'absence'
            ? props.reviewAbsenceRoute
            : props.reviewCorrectionRoute;

    procesando.value = solicitud.id;

    router.patch(
        route(routeName, solicitud.id),
        {
            action,
            review_comment: notaRevision.value[solicitud.id] || null,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                procesando.value = null;
            },
        },
    );
};

const statusClasses = {
    pending: 'bg-amber-100 text-amber-800 ring-amber-200',
    approved: 'bg-emerald-100 text-emerald-800 ring-emerald-200',
    rejected: 'bg-red-100 text-red-800 ring-red-200',
};

const kindBadgeClasses = {
    correction: 'bg-blue-100 text-blue-700',
    absence: 'bg-purple-100 text-purple-700',
};
</script>

<template>
    <Head :title="`Revisar solicitudes — ${tituloPanel}`" />

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
                    Revisar solicitudes
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Aprueba o rechaza las solicitudes de los trabajadores
                </p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div
                    v-if="mensajeExito"
                    class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                >
                    {{ mensajeExito }}
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="filtro in filtros"
                        :key="filtro.label"
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                        :class="
                            (filtroEstado ?? null) === filtro.value
                                ? 'bg-indigo-600 text-white'
                                : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50'
                        "
                        @click="aplicarFiltro(filtro.value)"
                    >
                        {{ filtro.label }}
                    </button>
                </div>

                <div
                    v-if="solicitudes.length === 0"
                    class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500"
                >
                    No hay solicitudes para mostrar con este filtro.
                </div>

                <article
                    v-for="solicitud in solicitudes"
                    :key="`${solicitud.kind}-${solicitud.id}`"
                    class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                    :class="kindBadgeClasses[solicitud.kind]"
                                >
                                    {{ solicitud.type_label }}
                                </span>
                                <span
                                    class="rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset"
                                    :class="statusClasses[solicitud.status]"
                                >
                                    {{ solicitud.status_label }}
                                </span>
                            </div>
                            <h3 class="mt-2 text-lg font-semibold text-slate-900">
                                {{ solicitud.employee_name }}
                            </h3>
                            <p class="text-sm text-slate-500">
                                {{ solicitud.employee_email }}
                            </p>
                        </div>
                        <p class="text-sm text-slate-400">
                            {{ solicitud.created_at }}
                        </p>
                    </div>

                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="font-medium text-slate-500">Periodo</dt>
                            <dd class="text-slate-900">
                                {{ solicitud.period_label }}
                            </dd>
                        </div>
                        <div
                            v-if="
                                solicitud.requested_clock_in ||
                                solicitud.requested_clock_out
                            "
                        >
                            <dt class="font-medium text-slate-500">
                                Horas solicitadas
                            </dt>
                            <dd class="text-slate-900">
                                <template v-if="solicitud.requested_clock_in">
                                    Entrada: {{ solicitud.requested_clock_in }}
                                </template>
                                <template v-if="solicitud.requested_clock_out">
                                    <span v-if="solicitud.requested_clock_in">
                                        ·
                                    </span>
                                    Salida: {{ solicitud.requested_clock_out }}
                                </template>
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="font-medium text-slate-500">Motivo</dt>
                            <dd class="whitespace-pre-wrap text-slate-900">
                                {{ solicitud.reason }}
                            </dd>
                        </div>
                        <div v-if="solicitud.review_note" class="sm:col-span-2">
                            <dt class="font-medium text-slate-500">
                                Nota de revisión
                            </dt>
                            <dd class="text-slate-700">
                                {{ solicitud.review_note }}
                            </dd>
                        </div>
                    </dl>

                    <div v-if="solicitud.attachment" class="mt-3">
                        <a
                            :href="solicitud.attachment.url"
                            class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                            target="_blank"
                        >
                            Ver adjunto: {{ solicitud.attachment.name }}
                        </a>
                    </div>

                    <div
                        v-if="solicitud.status === 'pending'"
                        class="mt-5 border-t border-slate-100 pt-5"
                    >
                        <label
                            :for="`nota-${solicitud.id}`"
                            class="block text-sm font-medium text-slate-700"
                        >
                            Comentario (opcional)
                        </label>
                        <textarea
                            :id="`nota-${solicitud.id}`"
                            v-model="notaRevision[solicitud.id]"
                            rows="2"
                            class="mt-1 w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Motivo de la decisión..."
                        />
                        <div class="mt-3 flex flex-wrap gap-3">
                            <button
                                type="button"
                                class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                                :disabled="procesando === solicitud.id"
                                @click="revisar(solicitud, 'approve')"
                            >
                                Aprobar
                            </button>
                            <button
                                type="button"
                                class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50"
                                :disabled="procesando === solicitud.id"
                                @click="revisar(solicitud, 'reject')"
                            >
                                Rechazar
                            </button>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
