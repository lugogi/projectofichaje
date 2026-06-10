<script setup>
defineProps({
    titulo: { type: String, required: true },
    valor: { type: String, required: true },
    subtitulo: { type: String, default: '' },
    icono: { type: String, default: 'clock' },
    destacado: { type: Boolean, default: false },
});

const iconPaths = {
    clock: 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z',
    calendar: 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5',
    chart: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z',
    pulse: 'M9.348 14.652a3.75 3.75 0 010-5.304m5.304 0a3.75 3.75 0 010 5.304m-7.425 2.121a6.75 6.75 0 010-9.546m9.546 0a6.75 6.75 0 010 9.546M5.106 18.894c-3.808-3.808-3.808-9.98 0-13.789m13.788 0c3.808 3.808 3.808 9.981 0 13.79M12 12h.008v.008H12V12z',
};
</script>

<template>
    <div
        class="rounded-2xl border p-5 transition-shadow"
        :class="
            destacado
                ? 'border-emerald-200 bg-gradient-to-br from-emerald-50 to-white shadow-md'
                : 'border-slate-200 bg-white shadow-sm'
        "
    >
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    {{ titulo }}
                </p>
                <p
                    class="mt-2 font-mono text-2xl font-bold tabular-nums"
                    :class="destacado ? 'text-emerald-700' : 'text-slate-900'"
                >
                    {{ valor }}
                </p>
                <p v-if="subtitulo" class="mt-1 text-sm text-slate-500">
                    {{ subtitulo }}
                </p>
            </div>
            <div
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl"
                :class="destacado ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-100 text-slate-600'"
            >
                <svg
                    class="h-6 w-6"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        :d="iconPaths[icono] ?? iconPaths.clock"
                    />
                </svg>
            </div>
        </div>
    </div>
</template>
