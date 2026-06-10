<script setup>
import { ref } from 'vue';

const props = defineProps({
    mesActual: {
        type: String,
        required: true,
    },
});

const mes = ref(props.mesActual);

const urlExport = (formato) => {
    const params = new URLSearchParams({ month: mes.value });
    return route('profile.export', { format: formato }) + '?' + params.toString();
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-slate-900">
                Descargar mis registros
            </h2>
            <p class="mt-1 text-sm text-slate-600">
                Exporta tus fichajes del mes seleccionado. El archivo respeta el
                tope de horas de tu contrato (sin horas extra).
            </p>
        </header>

        <div class="mt-6 flex flex-wrap items-end gap-4">
            <div>
                <label
                    for="mes-export"
                    class="block text-sm font-medium text-slate-700"
                >
                    Mes
                </label>
                <input
                    id="mes-export"
                    v-model="mes"
                    type="month"
                    class="mt-1 rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>

            <div class="flex flex-wrap gap-3">
                <a
                    :href="urlExport('excel')"
                    class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
                >
                    Excel
                </a>
                <a
                    :href="urlExport('pdf')"
                    class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700"
                >
                    PDF
                </a>
                <a
                    :href="urlExport('json')"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                >
                    JSON
                </a>
            </div>
        </div>
    </section>
</template>
