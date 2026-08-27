<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    empleados: {
        type: Array,
        default: () => [],
    },
    esAdmin: {
        type: Boolean,
        default: false,
    },
    homeRoute: {
        type: String,
        required: true,
    },
    saveUrl: {
        type: String,
        required: true,
    },
});

const page = usePage();
const tituloPanel = computed(() => (props.esAdmin ? 'Administración' : 'Encargado'));

const form = useForm({
    rates: props.empleados.map((e) => ({
        id: e.id,
        overtime_rate: e.overtime_rate ?? '',
    })),
});

const guardado = computed(() => page.props.flash?.success ?? null);

const empleadosPorId = computed(() => {
    const mapa = {};
    props.empleados.forEach((e) => {
        mapa[e.id] = e;
    });
    return mapa;
});
</script>

<template>
    <Head title="Tarifas de horas extra" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <Link
                    :href="route(homeRoute)"
                    class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                >
                    ← Volver a {{ tituloPanel }}
                </Link>
                <h2 class="mt-2 text-xl font-semibold leading-tight text-slate-900">
                    Tarifas de horas extra
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Precio por hora extra de cada trabajador. Si ficha más que su contrato,
                    esas horas se valoran a esta tarifa en los informes para laboral.
                </p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <p
                    v-if="guardado"
                    class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"
                >
                    {{ guardado }}
                </p>

                <div
                    v-if="empleados.length === 0"
                    class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-900"
                >
                    No hay trabajadores asignados.
                </div>

                <form
                    v-else
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                    @submit.prevent="form.put(saveUrl)"
                >
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">
                                    Trabajador
                                </th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">
                                    Puesto
                                </th>
                                <th class="w-40 px-4 py-3 text-left font-semibold text-slate-700">
                                    € / hora extra
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(fila, index) in form.rates" :key="fila.id">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-slate-900">
                                        {{ empleadosPorId[fila.id]?.nombre }}
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        {{ empleadosPorId[fila.id]?.codigo }}
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    {{ empleadosPorId[fila.id]?.puesto || '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <input
                                        v-model="form.rates[index].overtime_rate"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        placeholder="—"
                                        class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="flex justify-end border-t border-slate-200 px-4 py-3">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:bg-indigo-300"
                        >
                            {{ form.processing ? 'Guardando…' : 'Guardar tarifas' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
