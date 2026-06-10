<script setup>
import { computed } from 'vue';

const props = defineProps({
    sesiones: {
        type: Array,
        default: () => [],
    },
    registros: {
        type: Array,
        default: () => [],
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const jornadasFromRegistros = () => {
    const bloques = [];
    let actual = null;

    for (const registro of props.registros) {
        if (registro.tipo === 1) {
            if (actual) {
                bloques.push(actual);
            }
            actual = { entrada: registro, salida: null };
        } else if (registro.tipo === 2) {
            if (actual) {
                actual.salida = registro;
                bloques.push(actual);
                actual = null;
            }
        }
    }

    if (actual) {
        bloques.push(actual);
    }

    return bloques.map((bloque, index) => ({
        numero: index + 1,
        entradaHora: bloque.entrada?.hora_corta ?? null,
        salidaHora: bloque.salida?.hora_corta ?? null,
        entradaCorregida: Boolean(bloque.entrada?.es_correccion),
        salidaCorregida: Boolean(bloque.salida?.es_correccion),
        duracion: null,
        activa: !bloque.salida && Boolean(bloque.entrada),
        zona: bloque.entrada?.zona ?? bloque.salida?.zona,
        esCorreccion:
            Boolean(bloque.entrada?.es_correccion)
            || Boolean(bloque.salida?.es_correccion),
        cerrada: Boolean(bloque.salida),
    }));
};

const jornadas = computed(() => {
    if (props.sesiones.length > 0) {
        return props.sesiones.map((sesion, index) => ({
            numero: index + 1,
            entradaHora: sesion.entrada,
            salidaHora: sesion.salida,
            entradaCorregida: false,
            salidaCorregida: false,
            duracion: sesion.duracion,
            activa: Boolean(sesion.activa),
            zona: sesion.zona,
            esCorreccion: Boolean(sesion.es_correccion),
            cerrada: sesion.estado === 'cerrada',
        }));
    }

    return jornadasFromRegistros();
});

const vacio = computed(
    () => props.registros.length === 0 && props.sesiones.length === 0,
);
</script>

<template>
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4">
            <h3 class="text-base font-semibold text-slate-900">
                Registro de hoy
            </h3>
            <p class="mt-0.5 text-sm text-slate-500">
                Entrada y salida de cada jornada
            </p>
        </div>

        <div v-if="vacio" class="px-5 py-10 text-center">
            <p class="text-sm text-slate-400">Aún no hay fichajes hoy</p>
        </div>

        <ul v-else class="divide-y divide-slate-100">
            <li
                v-for="jornada in jornadas"
                :key="jornada.numero"
                class="px-5 py-4"
            >
                <div class="flex items-start gap-4">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-bold"
                        :class="
                            jornada.activa
                                ? 'bg-emerald-100 text-emerald-700'
                                : 'bg-slate-100 text-slate-600'
                        "
                    >
                        {{ jornada.numero }}
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                v-if="jornada.esCorreccion"
                                class="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-semibold text-indigo-800"
                            >
                                Corregido
                            </span>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="
                                    jornada.activa
                                        ? 'bg-amber-100 text-amber-800'
                                        : jornada.cerrada
                                          ? 'bg-emerald-100 text-emerald-800'
                                          : 'bg-slate-100 text-slate-600'
                                "
                            >
                                {{
                                    jornada.activa
                                        ? 'En curso'
                                        : jornada.cerrada
                                          ? 'Completada'
                                          : 'Incompleta'
                                }}
                            </span>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <div
                                v-if="jornada.entradaHora"
                                class="inline-flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2"
                            >
                                <span
                                    class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-500 text-white"
                                >
                                    <svg
                                        class="h-4 w-4"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke-width="2.5"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M4.5 10.5L12 3m0 0l7.5 7.5M12 3v18"
                                        />
                                    </svg>
                                </span>
                                <span
                                    class="font-mono font-bold tabular-nums text-emerald-900"
                                    :class="compact ? 'text-base' : 'text-xl'"
                                >
                                    {{ jornada.entradaHora }}
                                </span>
                            </div>

                            <span
                                v-if="jornada.entradaHora && (jornada.salidaHora || jornada.activa)"
                                class="text-slate-300"
                            >
                                →
                            </span>

                            <div
                                v-if="jornada.salidaHora"
                                class="inline-flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2"
                            >
                                <span
                                    class="flex h-7 w-7 items-center justify-center rounded-full bg-rose-500 text-white"
                                >
                                    <svg
                                        class="h-4 w-4"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke-width="2.5"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M19.5 13.5L12 21m0 0l-7.5-7.5M12 21V3"
                                        />
                                    </svg>
                                </span>
                                <span
                                    class="font-mono font-bold tabular-nums text-rose-900"
                                    :class="compact ? 'text-base' : 'text-xl'"
                                >
                                    {{ jornada.salidaHora }}
                                </span>
                            </div>

                            <span
                                v-else-if="jornada.activa && jornada.entradaHora"
                                class="rounded-lg border border-dashed border-amber-300 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800"
                            >
                                Sin salida
                            </span>
                        </div>

                        <p v-if="jornada.zona" class="mt-2 text-xs text-slate-500">
                            {{ jornada.zona }}
                        </p>
                    </div>

                    <div v-if="jornada.duracion" class="shrink-0 text-right">
                        <p class="font-mono text-lg font-bold tabular-nums text-slate-900">
                            {{ jornada.duracion }}
                        </p>
                        <p class="text-xs text-slate-400">duración</p>
                    </div>
                </div>
            </li>
        </ul>
    </div>
</template>
