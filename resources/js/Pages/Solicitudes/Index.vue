<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    solicitudes: {
        type: Array,
        default: () => [],
    },
    prefill: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();
const mensajeExito = ref(null);
const archivoNombre = ref(null);

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

const form = useForm({
    type: '',
    start_date: '',
    end_date: '',
    request_reason: '',
    attachment: null,
});

const tiposAusencia = [
    { value: 'vacation', label: 'Vacaciones' },
    { value: 'medical_leave', label: 'Baja médica' },
    { value: 'free_day', label: 'Día libre' },
];

const onArchivoChange = (event) => {
    const file = event.target.files?.[0] ?? null;
    form.attachment = file;
    archivoNombre.value = file?.name ?? null;
};

const enviar = () => {
    form.post(route('solicitudes.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            archivoNombre.value = null;
        },
    });
};

const statusClasses = {
    pending: 'bg-amber-100 text-amber-800 ring-amber-200',
    approved: 'bg-emerald-100 text-emerald-800 ring-emerald-200',
    rejected: 'bg-red-100 text-red-800 ring-red-200',
};

const typeBadgeClasses = {
    correction: 'bg-blue-100 text-blue-700',
    absence: 'bg-purple-100 text-purple-700',
};

const historialVacio = computed(() => props.solicitudes.length === 0);
</script>

<template>
    <Head title="Solicitudes" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-slate-900">
                    Solicitudes
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Solicita vacaciones, bajas médicas o días libres
                </p>
            </div>
        </template>

        <div class="py-10">
            <div class="mx-auto max-w-4xl space-y-8 px-4 sm:px-6 lg:px-8">
                <div
                    v-if="mensajeExito"
                    class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                >
                    {{ mensajeExito }}
                </div>

                <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <h3 class="text-lg font-semibold text-slate-900">
                        Nueva solicitud de ausencia
                    </h3>
                    <p class="mt-1 text-sm text-slate-500">
                        Indica el tipo, las fechas y el motivo. Un responsable revisará la petición.
                    </p>

                    <form class="mt-6 space-y-5" @submit.prevent="enviar">
                        <div>
                            <InputLabel for="type" value="Tipo de ausencia" />
                            <select
                                id="type"
                                v-model="form.type"
                                class="mt-1 block w-full max-w-xs rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                                <option value="" disabled>Seleccionar tipo…</option>
                                <option
                                    v-for="tipo in tiposAusencia"
                                    :key="tipo.value"
                                    :value="tipo.value"
                                >
                                    {{ tipo.label }}
                                </option>
                            </select>
                            <InputError class="mt-1" :message="form.errors.type" />
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <p class="mb-3 text-sm font-semibold text-slate-700">
                                    Fecha inicio
                                </p>
                                <InputLabel for="start_date" value="Fecha" />
                                <TextInput
                                    id="start_date"
                                    v-model="form.start_date"
                                    type="date"
                                    class="mt-1 block w-full"
                                    required
                                />
                                <InputError class="mt-1" :message="form.errors.start_date" />
                            </div>

                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <p class="mb-3 text-sm font-semibold text-slate-700">
                                    Fecha fin
                                </p>
                                <InputLabel for="end_date" value="Fecha" />
                                <TextInput
                                    id="end_date"
                                    v-model="form.end_date"
                                    type="date"
                                    class="mt-1 block w-full"
                                    :min="form.start_date"
                                    required
                                />
                                <InputError class="mt-1" :message="form.errors.end_date" />
                            </div>
                        </div>

                        <div>
                            <InputLabel for="request_reason" value="Motivo" />
                            <textarea
                                id="request_reason"
                                v-model="form.request_reason"
                                rows="4"
                                required
                                minlength="10"
                                maxlength="2000"
                                placeholder="Describe el motivo de tu solicitud…"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <InputError class="mt-1" :message="form.errors.request_reason" />
                        </div>

                        <div>
                            <InputLabel for="attachment" value="Adjunto (opcional)" />
                            <input
                                id="attachment"
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                class="mt-1 block w-full text-sm text-slate-600 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100"
                                @change="onArchivoChange"
                            />
                            <p v-if="archivoNombre" class="mt-1 text-xs text-slate-500">
                                Archivo seleccionado: {{ archivoNombre }}
                            </p>
                            <p class="mt-1 text-xs text-slate-400">
                                PDF, imagen o Word · máx. 5 MB
                            </p>
                            <InputError class="mt-1" :message="form.errors.attachment" />
                        </div>

                        <div class="flex justify-end">
                            <PrimaryButton :disabled="form.processing">
                                Enviar solicitud
                            </PrimaryButton>
                        </div>
                    </form>
                </section>

                <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                    <h3 class="text-lg font-semibold text-slate-900">
                        Historial de solicitudes
                    </h3>

                    <p
                        v-if="historialVacio"
                        class="mt-6 text-center text-sm text-slate-500"
                    >
                        Aún no has enviado ninguna solicitud.
                    </p>

                    <ul v-else class="mt-6 divide-y divide-slate-100">
                        <li
                            v-for="item in solicitudes"
                            :key="item.id"
                            class="flex flex-col gap-3 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div class="min-w-0 flex-1 space-y-1">
                                <div class="flex items-center gap-2">
                                    <p class="font-medium text-slate-900">
                                        {{ item.work_date_label }}
                                    </p>
                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                        :class="typeBadgeClasses[item.type] ?? 'bg-slate-100 text-slate-600'"
                                    >
                                        {{ item.type === 'correction' ? 'Corrección' : item.type_label ?? 'Ausencia' }}
                                    </span>
                                </div>

                                <p class="text-sm text-slate-600">
                                    <template v-if="item.type === 'correction'">
                                        <template v-if="item.requested_clock_in">
                                            Entrada:
                                            <span class="font-medium">{{ item.requested_clock_in }}</span>
                                        </template>
                                        <template v-if="item.requested_clock_in && item.requested_clock_out">
                                            ·
                                        </template>
                                        <template v-if="item.requested_clock_out">
                                            Salida:
                                            <span class="font-medium">{{ item.requested_clock_out }}</span>
                                        </template>
                                    </template>
                                    <template v-else>
                                        {{ item.type_label }}
                                    </template>
                                </p>

                                <p class="text-sm text-slate-500 line-clamp-2">
                                    {{ item.reason }}
                                </p>

                                <a
                                    v-if="item.attachment"
                                    :href="item.attachment.url"
                                    class="inline-flex text-xs font-medium text-indigo-600 hover:text-indigo-800"
                                >
                                    {{ item.attachment.name }}
                                </a>

                                <p class="text-xs text-slate-400">
                                    Enviada el {{ item.created_at }}
                                </p>

                                <p
                                    v-if="item.review_note"
                                    class="text-xs italic text-slate-500"
                                >
                                    Nota de revisión: {{ item.review_note }}
                                </p>
                            </div>

                            <span
                                class="inline-flex shrink-0 items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset"
                                :class="statusClasses[item.status] ?? statusClasses.pending"
                            >
                                {{ item.status_label }}
                            </span>
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>