<script setup>
import { computed } from 'vue';

const props = defineProps({
    historial: {
        type: Array,
        default: () => [],
    },
});

const maxMinutos = computed(() => {
    const max = Math.max(...props.historial.map((d) => d.minutos), 1);
    return max;
});

const barHeight = (minutos) => {
    if (minutos === 0) return '4px';
    const pct = Math.max((minutos / maxMinutos.value) * 100, 8);
    return `${pct}%`;
};
</script>

<template>
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4">
            <h3 class="text-base font-semibold text-slate-900">
                Últimos 14 días
            </h3>
            <p class="text-sm text-slate-500">Horas fichadas por día</p>
        </div>

        <div class="flex items-end justify-between gap-1 sm:gap-2" style="height: 140px">
            <div
                v-for="dia in [...historial].reverse()"
                :key="dia.fecha"
                class="flex min-w-0 flex-1 flex-col items-center justify-end gap-2"
            >
                <span
                    class="hidden text-[10px] font-medium text-slate-600 sm:block"
                    :title="dia.duracion"
                >
                    {{ dia.decimal > 0 ? dia.decimal + 'h' : '' }}
                </span>
                <div
                    class="w-full max-w-[2rem] rounded-t-md transition-all"
                    :class="
                        dia.es_hoy
                            ? 'bg-emerald-500'
                            : dia.minutos > 0
                              ? 'bg-slate-400'
                              : 'bg-slate-200'
                    "
                    :style="{ height: barHeight(dia.minutos) }"
                    :title="`${dia.fecha_label}: ${dia.duracion}`"
                ></div>
                <span
                    class="w-full truncate text-center text-[9px] font-medium uppercase text-slate-500 sm:text-[10px]"
                    :class="dia.es_hoy ? 'text-emerald-700' : ''"
                >
                    {{ dia.fecha_label.split(' ')[0] }}
                </span>
            </div>
        </div>

        <ul class="mt-5 max-h-48 space-y-2 overflow-y-auto border-t border-slate-100 pt-4">
            <li
                v-for="dia in historial"
                :key="dia.fecha"
                class="flex items-center justify-between rounded-lg px-2 py-1.5 text-sm"
                :class="dia.es_hoy ? 'bg-emerald-50 font-medium' : 'hover:bg-slate-50'"
            >
                <span class="capitalize text-slate-700">{{ dia.fecha_label }}</span>
                <span class="flex items-center gap-3">
                    <span class="text-xs text-slate-400">
                        {{ dia.sesiones }} {{ dia.sesiones === 1 ? 'jornada' : 'jornadas' }}
                    </span>
                    <span class="font-mono font-semibold tabular-nums text-slate-900">
                        {{ dia.duracion }}
                    </span>
                </span>
            </li>
        </ul>
    </div>
</template>
