<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const emit = defineEmits(['close', 'submitted']);

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false,
    },
    bloque: {
        type: Object,
        default: null,
    },
});

const archivoNombre = ref(null);
const toast = ref({ visible: false, tipo: '', mensaje: '' });
let toastTimer = null;

const form = useForm({
    work_date: '',
    clock_in_time: '',
    clock_out_time: '',
    reason: '',
    time_record_id: '',
    time_record_id_in: '',
    time_record_id_out: '',
    record_type: '',
});

watch(() => props.bloque, (b) => {
    if (!b) return;
    form.reset();
    archivoNombre.value = null;

    form.work_date = b.work_date;

    if (b.salida) {
        form.time_record_id_in = b.time_record_id_in;
        form.time_record_id_out = b.time_record_id_out;
        form.clock_in_time = b.clock_in_time;
        form.clock_out_time = b.clock_out_time;
    } else {
        form.time_record_id_in = b.time_record_id_in;
        form.clock_in_time = b.clock_in_time;
        form.clock_out_time = b.clock_out_time ?? '';
    }
}, { immediate: true });

const esBloqueCerrado = () => Boolean(props.bloque?.salida);

const mostrarToast = (tipo, mensaje) => {
    if (toastTimer) clearTimeout(toastTimer);
    toast.value = { visible: true, tipo, mensaje };
    const duracion = tipo === 'exito' ? 3000 : 5000;
    toastTimer = setTimeout(() => { toast.value.visible = false; }, duracion);
};

const onArchivoChange = (event) => {
    const file = event.target.files?.[0] ?? null;
    form.attachment = file;
    archivoNombre.value = file?.name ?? null;
};

const enviar = () => {
    form.post(route('correction-requests.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            archivoNombre.value = null;
            emit('submitted');
            mostrarToast('exito', 'Solicitud enviada. Pendiente de revisión.');
            setTimeout(() => emit('close'), 1500);
        },
        onError: (errors) => {
            const mensajes = Object.values(errors).flat();
            mostrarToast('error', mensajes.length ? mensajes.join('. ') : 'No se pudo enviar la solicitud.');
        },
    });
};
</script>

<template>
    <Teleport to="body">
        <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">
                            Corregir fichaje
                        </h3>
                        <p class="mt-0.5 text-sm text-slate-500">
                            {{
                                esBloqueCerrado()
                                    ? 'Modifica la entrada y/o salida del bloque'
                                    : 'Indica la entrada y la salida correctas de la jornada'
                            }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                        @click="emit('close')"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form class="p-6" @submit.prevent="enviar">
                    <input v-model="form.time_record_id" type="hidden" />
                    <input v-model="form.time_record_id_in" type="hidden" />
                    <input v-model="form.time_record_id_out" type="hidden" />
                    <input v-model="form.record_type" type="hidden" />

                    <div class="space-y-5">
                        <div>
                            <InputLabel for="mc-work_date" value="Fecha" />
                            <TextInput
                                id="mc-work_date"
                                v-model="form.work_date"
                                type="date"
                                class="mt-1 block w-full max-w-xs"
                                readonly
                            />
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <p class="mb-3 text-sm font-semibold text-slate-700">
                                    Entrada
                                </p>
                                <InputLabel for="mc-clock_in_time" value="Hora" />
                                <TextInput
                                    id="mc-clock_in_time"
                                    v-model="form.clock_in_time"
                                    type="time"
                                    class="mt-1 block w-full"
                                    required
                                />
                                <InputError class="mt-1" :message="form.errors.clock_in_time" />
                            </div>

                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <p class="mb-3 text-sm font-semibold text-slate-700">
                                    Salida
                                </p>
                                <InputLabel for="mc-clock_out_time" value="Hora" />
                                <TextInput
                                    id="mc-clock_out_time"
                                    v-model="form.clock_out_time"
                                    type="time"
                                    class="mt-1 block w-full"
                                    required
                                />
                                <InputError class="mt-1" :message="form.errors.clock_out_time" />
                            </div>
                        </div>

                        <div>
                            <InputLabel for="mc-reason" value="Motivo" />
                            <textarea
                                id="mc-reason"
                                v-model="form.reason"
                                rows="3"
                                required
                                minlength="10"
                                maxlength="2000"
                                placeholder="Explica por qué necesitas modificar el fichaje…"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <InputError class="mt-1" :message="form.errors.reason" />
                        </div>

                        <div>
                            <InputLabel for="mc-attachment" value="Adjunto (opcional)" />
                            <input
                                id="mc-attachment"
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                                class="mt-1 block w-full text-sm text-slate-600 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100"
                                @change="onArchivoChange"
                            />
                            <p v-if="archivoNombre" class="mt-1 text-xs text-slate-500">
                                {{ archivoNombre }}
                            </p>
                            <InputError class="mt-1" :message="form.errors.attachment" />
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button
                            type="button"
                            class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            @click="emit('close')"
                        >
                            Cancelar
                        </button>
                        <PrimaryButton :disabled="form.processing">
                            {{ form.processing ? 'Enviando…' : 'Enviar solicitud' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>

        <!-- Toast informativo -->
        <transition name="toast">
            <div
                v-if="toast.visible"
                class="fixed bottom-6 right-6 z-[60] flex items-center gap-3 rounded-xl px-5 py-3 shadow-lg"
                :class="toast.tipo === 'exito'
                    ? 'bg-emerald-600 text-white'
                    : 'bg-red-600 text-white'"
            >
                <svg v-if="toast.tipo === 'exito'" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <svg v-else class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
                <span class="text-sm font-medium">{{ toast.mensaje }}</span>
            </div>
        </transition>
    </Teleport>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: all 0.3s ease;
}
.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(20px);
}
</style>