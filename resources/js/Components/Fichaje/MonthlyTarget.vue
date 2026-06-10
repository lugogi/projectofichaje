<script setup>
import { computed } from 'vue';

const props = defineProps({
    objetivo: {
        type: Object,
        required: true,
    },
});

const barColor = computed(() => {
    const p = props.objetivo.porcentaje_cumplido;
    if (p >= 100) return 'bg-emerald-500';
    if (p >= 75) return 'bg-emerald-400';
    if (p >= 50) return 'bg-amber-400';
    return 'bg-slate-400';
});

const estadoTexto = computed(() => {
    if (props.objetivo.adelantado && !props.objetivo.ocultar_extra) {
        return 'Por encima del objetivo';
    }
    if (props.objetivo.porcentaje_cumplido >= 100) {
        return 'Objetivo alcanzado';
    }
    return 'En progreso';
});
</script>

<template>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 bg-gradient-to-r from-indigo-50 to-white px-5 py-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">
                        Objetivo mensual
                    </h3>
                    <p class="mt-0.5 text-sm capitalize text-slate-500">
                        {{ objetivo.mes_label }} · Contrato
                        {{ objetivo.horas_semanales_contrato }} h/semana
                    </p>
                </div>
                <span
                    class="rounded-full px-3 py-1 text-xs font-semibold"
                    :class="
                        objetivo.adelantado || objetivo.porcentaje_cumplido >= 100
                            ? 'bg-emerald-100 text-emerald-800'
                            : 'bg-indigo-100 text-indigo-800'
                    "
                >
                    {{ estadoTexto }}
                </span>
            </div>
        </div>

        <div class="space-y-5 p-5">
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-indigo-100 bg-indigo-50/60 p-4 text-center">
                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">
                        Deberías trabajar
                    </p>
                    <p class="mt-2 font-mono text-2xl font-bold tabular-nums text-indigo-900">
                        {{ objetivo.formato_esperado }}
                    </p>
                    <p class="mt-1 text-sm text-indigo-700">
                        {{ objetivo.decimal_esperado }} h
                    </p>
                </div>
                <div class="rounded-xl border border-emerald-100 bg-emerald-50/60 p-4 text-center">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">
                        Has fichado
                    </p>
                    <p class="mt-2 font-mono text-2xl font-bold tabular-nums text-emerald-900">
                        {{ objetivo.trabajado.formato }}
                    </p>
                    <p class="mt-1 text-sm text-emerald-700">
                        {{ objetivo.trabajado.decimal }} h
                    </p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-center">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        {{
                            objetivo.adelantado && !objetivo.ocultar_extra
                                ? 'Adelanto'
                                : 'Te quedan'
                        }}
                    </p>
                    <p
                        class="mt-2 font-mono text-2xl font-bold tabular-nums"
                        :class="
                            objetivo.adelantado && !objetivo.ocultar_extra
                                ? 'text-emerald-700'
                                : 'text-slate-900'
                        "
                    >
                        {{
                            objetivo.adelantado && !objetivo.ocultar_extra
                                ? objetivo.trabajado.formato
                                : objetivo.ocultar_extra && objetivo.porcentaje_cumplido >= 100
                                  ? '0 min'
                                  : objetivo.restante.formato
                        }}
                    </p>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ objetivo.porcentaje_cumplido }}% del objetivo
                    </p>
                </div>
            </div>

            <div>
                <div class="mb-2 flex justify-between text-xs font-medium text-slate-500">
                    <span>Progreso del mes</span>
                    <span>{{ objetivo.trabajado.formato }} / {{ objetivo.formato_esperado }}</span>
                </div>
                <div class="h-3 overflow-hidden rounded-full bg-slate-100">
                    <div
                        class="h-full rounded-full transition-all duration-500"
                        :class="barColor"
                        :style="{ width: `${Math.min(objetivo.porcentaje_cumplido, 100)}%` }"
                    ></div>
                </div>
            </div>

            <dl class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                <div class="rounded-lg bg-slate-50 px-3 py-2">
                    <dt class="text-xs text-slate-500">Días laborables</dt>
                    <dd class="mt-0.5 font-semibold text-slate-900">
                        {{ objetivo.dias_laborables }}
                    </dd>
                </div>
                <div class="rounded-lg bg-slate-50 px-3 py-2">
                    <dt class="text-xs text-slate-500">Festivos</dt>
                    <dd class="mt-0.5 font-semibold text-slate-900">
                        {{ objetivo.dias_festivos }}
                    </dd>
                </div>
                <div class="rounded-lg bg-slate-50 px-3 py-2">
                    <dt class="text-xs text-slate-500">Jornada diaria</dt>
                    <dd class="mt-0.5 font-semibold text-slate-900">
                        {{ objetivo.horas_diarias_contrato }} h
                    </dd>
                </div>
                <div class="rounded-lg bg-slate-50 px-3 py-2">
                    <dt class="text-xs text-slate-500">Calendario</dt>
                    <dd class="mt-0.5 truncate font-semibold text-slate-900" :title="objetivo.calendario">
                        {{ objetivo.calendario ?? '—' }}
                    </dd>
                </div>
            </dl>

            <div v-if="objetivo.festivos.length > 0">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    Festivos este mes
                </p>
                <ul class="flex flex-wrap gap-2">
                    <li
                        v-for="festivo in objetivo.festivos"
                        :key="festivo.fecha"
                        class="rounded-lg border border-rose-100 bg-rose-50 px-2.5 py-1 text-xs text-rose-800"
                    >
                        <span class="font-medium">{{ festivo.fecha_label }}</span>
                        · {{ festivo.nombre }}
                    </li>
                </ul>
            </div>

            <p
                v-if="objetivo.usa_horario_por_defecto"
                class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800"
            >
                Sin horario personal asignado: se usa jornada estándar de lunes a viernes.
            </p>

            <p class="text-xs text-slate-400">
                Cálculo según tu horario, calendario laboral y festivos.
                Contrato de {{ objetivo.horas_semanales_contrato }} h semanales
                ({{ objetivo.horas_diarias_contrato }} h por día laborable).
            </p>
        </div>
    </div>
</template>
