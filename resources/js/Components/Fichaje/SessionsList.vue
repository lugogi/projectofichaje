<script setup>
defineProps({
    sesiones: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-4">
            <h3 class="text-base font-semibold text-slate-900">
                Jornadas de hoy
            </h3>
            <p class="mt-0.5 text-sm text-slate-500">
                Bloques entrada → salida con duración
            </p>
        </div>

        <div v-if="sesiones.length === 0" class="px-5 py-10 text-center">
            <p class="text-sm text-slate-400">Sin jornadas registradas hoy</p>
        </div>

        <ul v-else class="divide-y divide-slate-100">
            <li
                v-for="(sesion, index) in sesiones"
                :key="sesion.id"
                class="flex items-center gap-4 px-5 py-4"
            >
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-bold"
                    :class="
                        sesion.activa
                            ? 'bg-emerald-100 text-emerald-700'
                            : 'bg-slate-100 text-slate-600'
                    "
                >
                    {{ index + 1 }}
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            v-if="sesion.es_correccion"
                            class="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-semibold text-indigo-800"
                        >
                            Corregido
                        </span>
                        <span class="font-mono text-sm font-semibold text-slate-900">
                            {{ sesion.entrada }}
                        </span>
                        <span class="text-slate-300">→</span>
                        <span
                            v-if="sesion.salida"
                            class="font-mono text-sm font-semibold text-slate-900"
                        >
                            {{ sesion.salida }}
                        </span>
                        <span
                            v-else
                            class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800"
                        >
                            En curso
                        </span>
                    </div>
                    <p v-if="sesion.zona" class="mt-1 text-xs text-slate-500">
                        {{ sesion.zona }}
                    </p>
                </div>
                <div class="text-right">
                    <p class="font-mono text-lg font-bold tabular-nums text-slate-900">
                        {{ sesion.duracion }}
                    </p>
                    <p class="text-xs text-slate-400">duración</p>
                </div>
            </li>
        </ul>
    </div>
</template>
