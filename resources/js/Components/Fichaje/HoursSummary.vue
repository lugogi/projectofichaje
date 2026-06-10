<script setup>
import StatCard from '@/Components/Fichaje/StatCard.vue';
import { formatMinutes } from '@/composables/useDuration';

defineProps({
    resumen: {
        type: Object,
        required: true,
    },
    estado: {
        type: String,
        default: 'fuera',
    },
    sesionAbiertaMinutos: {
        type: Number,
        default: 0,
    },
});
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-3">
        <StatCard
            titulo="Hoy"
            :valor="resumen.hoy.formato"
            :subtitulo="`${resumen.hoy.decimal} h · ${estado === 'trabajando' ? 'En jornada' : 'Jornada cerrada'}`"
            icono="clock"
            destacado
        />
        <StatCard
            titulo="Esta semana"
            :valor="resumen.semana.formato"
            :subtitulo="`${resumen.semana.decimal} horas`"
            icono="calendar"
        />
        <StatCard
            titulo="Este mes"
            :valor="resumen.mes.formato"
            :subtitulo="`${resumen.mes.decimal} horas`"
            icono="chart"
        />
    </div>
    <p
        v-if="estado === 'trabajando' && sesionAbiertaMinutos > 0"
        class="mt-3 flex items-center gap-2 rounded-xl bg-emerald-50 px-4 py-2.5 text-sm font-medium text-emerald-800"
    >
        <span class="relative flex h-2.5 w-2.5">
            <span
                class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"
            ></span>
            <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
        </span>
        Sesión activa: {{ formatMinutes(sesionAbiertaMinutos) }} en curso
    </p>
</template>
