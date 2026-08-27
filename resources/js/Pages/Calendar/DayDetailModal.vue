<script setup>
import CorrectionModal from '@/Components/Fichaje/CorrectionModal.vue';
import { ref, computed } from 'vue';

const emit = defineEmits(['close', 'refresh']);

const props = defineProps({
    events: {
        type: Array,
        default: () => [],
    },
    date: {
        type: String,
        default: '',
    },
    isOpen: {
        type: Boolean,
        default: false,
    },
    absence: {
        type: Object,
        default: null,
    },
});

const correctionModalOpen = ref(false);
const selectedBloque = ref(null);

const bloques = computed(() => {
    const blocks = [];
    let current = null;

    for (const event of props.events) {
        if (event.type === 1) {
            if (current) blocks.push(current);
            current = { entrada: event, salida: null };
        } else if (event.type === 2) {
            if (current) {
                current.salida = event;
                blocks.push(current);
                current = null;
            }
        }
    }
    if (current) blocks.push(current);
    return blocks;
});

const abrirCorreccion = (bloque) => {
    selectedBloque.value = {
        work_date: bloque.entrada.work_date || props.date,
        time_record_id_in: bloque.entrada.id,
        clock_in_time: bloque.entrada.recorded_at,
        salida: bloque.salida,
        ...(bloque.salida
            ? {
                time_record_id_out: bloque.salida.id,
                clock_out_time: bloque.salida.recorded_at,
              }
            : {}),
    };
    correctionModalOpen.value = true;
};

const onCorrectionSubmitted = () => {
    emit('refresh');
};
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 sm:items-center">
        <div class="max-h-[80vh] w-full overflow-y-auto rounded-t-2xl bg-white p-5 shadow-xl sm:max-w-lg sm:rounded-2xl">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-bold text-slate-900">Tus fichajes</h3>
                <button type="button" class="rounded-full p-2 hover:bg-gray-100" @click="emit('close')">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div
                v-if="absence"
                class="mb-4 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900"
            >
                <p class="font-semibold">{{ absence.label }} aprobada</p>
                <p class="mt-1 text-indigo-800">
                    No debes fichar este día.
                </p>
            </div>

            <div v-if="bloques.length > 0">
                <div v-for="(bloque, index) in bloques" :key="index" class="mb-4 rounded-lg bg-gray-50 p-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-semibold text-gray-800">
                            Bloque {{ index + 1 }}
                        </span>
                        <span class="text-xs rounded-full px-2 py-0.5"
                              :class="bloque.salida
                                  ? 'bg-green-100 text-green-700'
                                  : 'bg-amber-100 text-amber-700'">
                            {{ bloque.salida ? 'Completado' : 'En curso' }}
                        </span>
                    </div>

                    <div class="mt-2 flex items-center gap-2 text-sm">
                        <span class="font-mono font-semibold">{{ bloque.entrada.recorded_at }}</span>
                        <span class="text-gray-400">→</span>
                        <span class="font-mono font-semibold" :class="bloque.salida ? '' : 'text-amber-600'">
                            {{ bloque.salida ? bloque.salida.recorded_at : 'En curso' }}
                        </span>
                    </div>

                    <div class="mt-1 flex flex-wrap items-center gap-2 text-sm text-gray-600">
                        <span v-if="bloque.entrada.zona">
                            {{ bloque.entrada.zona }}
                        </span>
                        <span v-if="bloque.entrada.clock_method">
                            Método: {{ bloque.entrada.clock_method }}
                        </span>
                        <span
                            v-if="bloque.entrada.es_correccion || bloque.salida?.es_correccion"
                            class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-800"
                        >
                            Corregido
                        </span>
                    </div>

                    <div class="mt-3">
                        <button
                            type="button"
                            class="inline-flex w-full items-center justify-center rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-700 transition hover:bg-indigo-100"
                            @click="abrirCorreccion(bloque)"
                        >
                            Solicitar modificación
                        </button>
                    </div>
                </div>
            </div>

            <div v-else-if="!absence" class="py-4 text-center text-gray-500">
                No hay registros para este día
            </div>
        </div>
    </div>

    <CorrectionModal
        :is-open="correctionModalOpen"
        :bloque="selectedBloque"
        @close="correctionModalOpen = false"
        @submitted="onCorrectionSubmitted"
    />
</template>