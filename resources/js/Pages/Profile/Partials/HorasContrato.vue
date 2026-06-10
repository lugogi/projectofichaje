<script setup>
defineProps({
    resumen: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-slate-900">
                Horas del mes ({{ resumen.periodo.mes_label }})
            </h2>
            <p class="mt-1 text-sm text-slate-600">
                Contrato de {{ resumen.contrato.horas_semanales }} h/semana.
                <template v-if="!resumen.perfil.ocultar_extra">
                    Las horas extra se muestran aquí pero no se incluyen en la
                    exportación.
                </template>
            </p>
        </header>

        <div class="mt-6 grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-indigo-100 bg-indigo-50 p-4">
                <p class="text-xs font-semibold uppercase text-indigo-600">
                    Según contrato
                </p>
                <p class="mt-2 font-mono text-2xl font-bold text-indigo-900">
                    {{ resumen.contrato.formato_esperado_mes }}
                </p>
            </div>
            <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-4">
                <p class="text-xs font-semibold uppercase text-emerald-600">
                    Has fichado
                </p>
                <p class="mt-2 font-mono text-2xl font-bold text-emerald-900">
                    {{ resumen.perfil.formato_fichado_real }}
                </p>
            </div>
            <div
                v-if="!resumen.perfil.ocultar_extra"
                class="rounded-xl border p-4"
                :class="
                    resumen.perfil.tiene_horas_extra
                        ? 'border-amber-200 bg-amber-50'
                        : 'border-slate-200 bg-slate-50'
                "
            >
                <p
                    class="text-xs font-semibold uppercase"
                    :class="
                        resumen.perfil.tiene_horas_extra
                            ? 'text-amber-700'
                            : 'text-slate-500'
                    "
                >
                    Horas extra
                </p>
                <p
                    class="mt-2 font-mono text-2xl font-bold"
                    :class="
                        resumen.perfil.tiene_horas_extra
                            ? 'text-amber-900'
                            : 'text-slate-700'
                    "
                >
                    {{ resumen.perfil.formato_extra }}
                </p>
                <p
                    v-if="resumen.perfil.tiene_horas_extra"
                    class="mt-1 text-xs text-amber-800"
                >
                    Cuentan como tiempo adicional trabajado
                </p>
            </div>
        </div>

        <p
            v-if="resumen.exportacion.tope_aplicado"
            class="mt-4 rounded-lg bg-slate-100 px-4 py-3 text-sm text-slate-700"
        >
            Al exportar solo se incluirán
            <strong>{{ resumen.exportacion.formato_incluido }}</strong>
            (máximo contractual). Se omiten
            <strong>{{ resumen.exportacion.formato_omitido }}</strong>
            de horas extra.
        </p>
    </section>
</template>
