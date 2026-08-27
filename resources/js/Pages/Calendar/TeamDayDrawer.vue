<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    isOpen: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    equipo: { type: Object, default: null },
    esAdmin: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const busqueda = ref('');
const filtro = ref('todos');

watch(
    () => props.equipo?.fecha,
    () => {
        busqueda.value = '';
        filtro.value = 'todos';
    },
);

const personas = computed(() => props.equipo?.personas ?? []);

const filtradas = computed(() => {
    const q = busqueda.value.trim().toLowerCase();
    return personas.value.filter((p) => {
        if (filtro.value === 'incidencias' && !['sin_fichar', 'en_curso'].includes(p.estado)) {
            return false;
        }
        if (filtro.value !== 'todos' && filtro.value !== 'incidencias' && p.estado !== filtro.value) {
            return false;
        }
        if (!q) return true;
        return (
            p.nombre.toLowerCase().includes(q) ||
            (p.puesto || '').toLowerCase().includes(q) ||
            (p.codigo || '').toLowerCase().includes(q)
        );
    });
});

const resumen = computed(() => props.equipo?.resumen ?? {});

const estadoMeta = {
    sin_fichar: { label: 'Sin fichar', class: 'bg-red-100 text-red-800', bar: 'bg-red-500' },
    en_curso: { label: 'En curso', class: 'bg-amber-100 text-amber-800', bar: 'bg-amber-500' },
    ausencia: { label: 'Ausencia', class: 'bg-indigo-100 text-indigo-800', bar: 'bg-indigo-500' },
    completo: { label: 'Completado', class: 'bg-emerald-100 text-emerald-800', bar: 'bg-emerald-500' },
    laborable: { label: 'Pendiente', class: 'bg-slate-100 text-slate-700', bar: 'bg-slate-400' },
    no_laborable: { label: 'No laborable', class: 'bg-slate-50 text-slate-500', bar: 'bg-slate-300' },
};

const filtros = [
    { id: 'todos', label: 'Todos' },
    { id: 'incidencias', label: 'Incidencias' },
    { id: 'sin_fichar', label: 'Sin fichar' },
    { id: 'en_curso', label: 'En curso' },
    { id: 'ausencia', label: 'Ausentes' },
    { id: 'completo', label: 'Completos' },
];

const urlFichadaManual = (persona) => {
    const params = new URLSearchParams({
        employee_id: persona.id,
        fecha: props.equipo?.fecha ?? '',
    });
    return route('admin.manual-clock.index') + '?' + params.toString();
};

const onKey = (e) => {
    if (e.key === 'Escape') emit('close');
};

onMounted(() => document.addEventListener('keydown', onKey));
onUnmounted(() => document.removeEventListener('keydown', onKey));
</script>

<template>
    <Teleport to="body">
        <div
            v-if="isOpen"
            class="fixed inset-0 z-50 flex justify-end"
        >
            <button
                type="button"
                class="absolute inset-0 bg-slate-900/40"
                aria-label="Cerrar"
                @click="emit('close')"
            />

            <aside
                class="relative flex h-full w-full max-w-xl flex-col bg-white shadow-2xl"
            >
                <header class="border-b border-slate-200 px-5 py-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Plantilla del día
                            </p>
                            <h3 class="mt-0.5 text-lg font-semibold capitalize text-slate-900">
                                {{ equipo?.fecha_label || 'Día' }}
                            </h3>
                        </div>
                        <button
                            type="button"
                            class="rounded-full p-2 text-slate-500 hover:bg-slate-100"
                            @click="emit('close')"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <div class="rounded-xl bg-emerald-50 px-3 py-2">
                            <p class="text-[11px] text-emerald-700">Completos</p>
                            <p class="text-lg font-semibold text-emerald-900">{{ resumen.completos ?? 0 }}</p>
                        </div>
                        <div class="rounded-xl bg-amber-50 px-3 py-2">
                            <p class="text-[11px] text-amber-700">En curso</p>
                            <p class="text-lg font-semibold text-amber-900">{{ resumen.en_curso ?? 0 }}</p>
                        </div>
                        <div class="rounded-xl bg-red-50 px-3 py-2">
                            <p class="text-[11px] text-red-700">Sin fichar</p>
                            <p class="text-lg font-semibold text-red-900">{{ resumen.sin_fichar ?? 0 }}</p>
                        </div>
                        <div class="rounded-xl bg-indigo-50 px-3 py-2">
                            <p class="text-[11px] text-indigo-700">Ausentes</p>
                            <p class="text-lg font-semibold text-indigo-900">{{ resumen.ausentes ?? 0 }}</p>
                        </div>
                    </div>
                </header>

                <div class="border-b border-slate-100 px-5 py-3">
                    <input
                        v-model="busqueda"
                        type="search"
                        placeholder="Buscar por nombre, puesto o código…"
                        class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <button
                            v-for="f in filtros"
                            :key="f.id"
                            type="button"
                            class="rounded-full px-2.5 py-1 text-xs font-medium"
                            :class="
                                filtro === f.id
                                    ? 'bg-slate-900 text-white'
                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                            "
                            @click="filtro = f.id"
                        >
                            {{ f.label }}
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-4">
                    <p v-if="loading" class="py-10 text-center text-sm text-slate-500">
                        Cargando fichajes del equipo…
                    </p>

                    <p
                        v-else-if="filtradas.length === 0"
                        class="py-10 text-center text-sm text-slate-500"
                    >
                        Nadie coincide con este filtro.
                    </p>

                    <ul v-else class="space-y-3">
                        <li
                            v-for="persona in filtradas"
                            :key="persona.id"
                            class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4"
                        >
                            <div class="flex items-start gap-3">
                                <span
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white"
                                    :class="estadoMeta[persona.estado]?.bar || 'bg-slate-400'"
                                >
                                    {{ persona.iniciales }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold text-slate-900">
                                                {{ persona.nombre }}
                                            </p>
                                            <p class="text-xs text-slate-500">
                                                {{ persona.puesto || 'Sin puesto' }}
                                                <template v-if="persona.codigo"> · {{ persona.codigo }}</template>
                                            </p>
                                        </div>
                                        <span
                                            class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                            :class="estadoMeta[persona.estado]?.class"
                                        >
                                            {{ estadoMeta[persona.estado]?.label }}
                                        </span>
                                    </div>

                                    <p
                                        v-if="persona.ausencia"
                                        class="mt-2 text-sm text-indigo-800"
                                    >
                                        {{ persona.ausencia.label }}
                                    </p>

                                    <div
                                        v-else-if="persona.jornadas.length"
                                        class="mt-3 space-y-1.5"
                                    >
                                        <div
                                            v-for="j in persona.jornadas"
                                            :key="j.id"
                                            class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-white px-3 py-2 text-sm"
                                        >
                                            <span class="font-mono font-semibold text-slate-800">
                                                {{ j.entrada }}
                                                <span class="text-slate-400">→</span>
                                                <span :class="j.abierta ? 'text-amber-600' : ''">
                                                    {{ j.salida || 'en curso' }}
                                                </span>
                                            </span>
                                            <span class="text-xs text-slate-500">
                                                {{ j.duracion }}
                                                <template v-if="j.zona"> · {{ j.zona }}</template>
                                                <template v-if="j.es_correccion"> · corregido</template>
                                            </span>
                                        </div>
                                        <p class="text-xs font-medium text-slate-600">
                                            Total del día: {{ persona.horas }}
                                        </p>
                                    </div>

                                    <p
                                        v-else-if="persona.estado === 'sin_fichar'"
                                        class="mt-2 text-sm text-red-700"
                                    >
                                        No hay entrada ni salida este día.
                                    </p>

                                    <div v-if="esAdmin" class="mt-3">
                                        <Link
                                            :href="urlFichadaManual(persona)"
                                            class="text-xs font-medium text-indigo-700 hover:text-indigo-900"
                                        >
                                            Añadir fichada manual
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </aside>
        </div>
    </Teleport>
</template>
