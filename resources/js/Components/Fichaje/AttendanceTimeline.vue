<script setup>
defineProps({
    registros: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4">
            <h3 class="text-base font-semibold text-slate-900">
                Registro detallado
            </h3>
            <p class="mt-0.5 text-sm text-slate-500">
                Línea temporal de cada fichaje
            </p>
        </div>

        <div v-if="registros.length === 0" class="px-5 py-10 text-center">
            <p class="text-sm text-slate-400">Aún no hay fichajes hoy</p>
        </div>

        <div v-else class="px-5 py-4">
            <ol class="relative border-s border-slate-200 ms-3">
                <li
                    v-for="(registro, index) in registros"
                    :key="registro.id"
                    class="mb-8 ms-6 last:mb-2"
                >
                    <span
                        class="absolute -start-3 flex h-6 w-6 items-center justify-center rounded-full ring-4 ring-white"
                        :class="
                            registro.tipo === 1
                                ? 'bg-emerald-500'
                                : 'bg-rose-500'
                        "
                    >
                        <svg
                            v-if="registro.tipo === 1"
                            class="h-3.5 w-3.5 text-white"
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
                        <svg
                            v-else
                            class="h-3.5 w-3.5 text-white"
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
                    <div
                        class="rounded-xl border border-slate-100 bg-slate-50/80 p-4"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p
                                    class="text-sm font-semibold"
                                    :class="
                                        registro.tipo === 1
                                            ? 'text-emerald-700'
                                            : 'text-rose-700'
                                    "
                                >
                                    {{ registro.label }}
                                </p>
                                <p class="mt-1 font-mono text-xl font-bold tabular-nums text-slate-900">
                                    {{ registro.hora_corta }}
                                </p>
                            </div>
                            <div class="flex flex-col items-end gap-1">
                                <span
                                    v-if="registro.es_correccion"
                                    class="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-semibold text-indigo-800"
                                >
                                    Corregido
                                </span>
                                <span
                                    class="rounded-lg bg-white px-2.5 py-1 text-xs font-medium text-slate-600 shadow-sm"
                                >
                                    #{{ index + 1 }}
                                </span>
                            </div>
                        </div>
                        <div
                            v-if="registro.zona || registro.metodo"
                            class="mt-3 flex flex-wrap gap-2 text-xs text-slate-500"
                        >
                            <span
                                v-if="registro.zona"
                                class="inline-flex items-center gap-1 rounded-md bg-white px-2 py-1"
                            >
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                </svg>
                                {{ registro.zona }}
                            </span>
                            <span class="rounded-md bg-white px-2 py-1">
                                {{ registro.metodo }}
                            </span>
                        </div>
                    </div>
                </li>
            </ol>
        </div>
    </div>
</template>
