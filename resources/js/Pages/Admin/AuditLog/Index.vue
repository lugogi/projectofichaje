<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    registros: {
        type: Array,
        default: () => [],
    },
    total: {
        type: Number,
        default: 0,
    },
    pagina: {
        type: Number,
        default: 1,
    },
    filtroAccion: {
        type: String,
        default: null,
    },
    acciones: {
        type: Array,
        default: () => [],
    },
});

const filtro = ref(props.filtroAccion ?? '');

const totalPaginas = computed(() => Math.max(1, Math.ceil(props.total / 30)));

const aplicarFiltro = () => {
    router.get(
        route('admin.audit.index'),
        filtro.value ? { action: filtro.value, page: 1 } : { page: 1 },
        { preserveState: true, preserveScroll: true },
    );
};

const irPagina = (pagina) => {
    const params = { page: pagina };
    if (filtro.value) params.action = filtro.value;
    router.get(route('admin.audit.index'), params, {
        preserveState: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Registro de auditoría" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <Link
                    :href="route('admin.index')"
                    class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                >
                    ← Volver a Administración
                </Link>
                <h2 class="mt-2 text-xl font-semibold text-slate-900">
                    Registro de auditoría
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Historial inmutable de acciones administrativas (cumplimiento
                    RDL 8/2019).
                </p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div class="mb-4 flex flex-wrap items-end gap-4">
                    <div>
                        <InputLabel value="Filtrar por acción" />
                        <select
                            v-model="filtro"
                            class="mt-1 rounded-md border-slate-300 shadow-sm"
                            @change="aplicarFiltro"
                        >
                            <option value="">Todas</option>
                            <option
                                v-for="a in acciones"
                                :key="a.value"
                                :value="a.value"
                            >
                                {{ a.label }}
                            </option>
                        </select>
                    </div>
                    <p class="text-sm text-slate-500">{{ total }} registros</p>
                </div>

                <div class="space-y-3">
                    <div
                        v-for="reg in registros"
                        :key="reg.id"
                        class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <span
                                    class="rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-800"
                                >
                                    {{ reg.action_label }}
                                </span>
                                <p class="mt-2 text-sm text-slate-700">
                                    <span v-if="reg.actor">
                                        {{ reg.actor.name }}
                                    </span>
                                    <span v-else class="text-slate-400">Sistema</span>
                                    · {{ reg.created_at }}
                                </p>
                            </div>
                            <span class="font-mono text-xs text-slate-400">
                                {{ reg.entity_type }}
                            </span>
                        </div>
                        <p v-if="reg.reason" class="mt-2 text-sm text-slate-600">
                            Motivo: {{ reg.reason }}
                        </p>
                    </div>

                    <p
                        v-if="!registros.length"
                        class="rounded-2xl border border-dashed border-slate-200 py-12 text-center text-slate-500"
                    >
                        No hay registros de auditoría todavía.
                    </p>
                </div>

                <div
                    v-if="totalPaginas > 1"
                    class="mt-6 flex justify-center gap-2"
                >
                    <button
                        v-for="p in totalPaginas"
                        :key="p"
                        type="button"
                        :class="
                            p === pagina
                                ? 'bg-indigo-600 text-white'
                                : 'bg-white text-slate-700 hover:bg-slate-50'
                        "
                        class="rounded-lg border border-slate-200 px-3 py-1 text-sm"
                        @click="irPagina(p)"
                    >
                        {{ p }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
