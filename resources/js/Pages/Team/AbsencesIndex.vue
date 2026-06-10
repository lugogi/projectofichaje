<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    empleados: {
        type: Array,
        default: () => [],
    },
    ausencias: {
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
    storeRoute: {
        type: String,
        required: true,
    },
    destroyRoute: {
        type: String,
        required: true,
    },
    tipos: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const mensajeExito = ref(null);

watch(
    () => page.props.flash?.success,
    (msg) => {
        if (msg) {
            mensajeExito.value = msg;
            setTimeout(() => (mensajeExito.value = null), 6000);
        }
    },
    { immediate: true },
);

const tituloPanel = computed(() =>
    props.esAdmin ? 'Administración' : 'Encargado',
);

const hoy = new Date().toISOString().slice(0, 10);

const form = useForm({
    employee_id: props.empleados[0]?.id ?? '',
    type: 'vacation',
    start_date: hoy,
    end_date: hoy,
    note: '',
});

const enviar = () => {
    form.post(route(props.storeRoute), {
        preserveScroll: true,
        onSuccess: () => form.reset('note'),
    });
};

const anular = (ausencia) => {
    if (
        !confirm(
            `¿Anular ${ausencia.type_label} de ${ausencia.employee_name} (${ausencia.periodo_label})?`,
        )
    ) {
        return;
    }

    router.delete(route(props.destroyRoute, ausencia.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Ausencias del equipo" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <Link
                    :href="route(homeRoute)"
                    class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                >
                    ← Volver a {{ tituloPanel }}
                </Link>
                <h2 class="mt-2 text-xl font-semibold text-slate-900">
                    Ausencias y vacaciones
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Registra vacaciones, bajas o días libre directamente — el
                    empleado no tiene que solicitarlas. Quedan aprobadas al
                    instante y bloquean el fichaje en esas fechas.
                </p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 space-y-6">
                <div
                    v-if="mensajeExito"
                    class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ring-1 ring-emerald-200"
                >
                    {{ mensajeExito }}
                </div>

                <form
                    class="space-y-4 rounded-2xl border border-indigo-100 bg-indigo-50/40 p-6 shadow-sm"
                    @submit.prevent="enviar"
                >
                    <h3 class="font-semibold text-slate-900">
                        Registrar ausencia manual
                    </h3>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <InputLabel value="Empleado" />
                            <select
                                v-model="form.employee_id"
                                class="mt-1 block w-full rounded-md border-slate-300 shadow-sm"
                                required
                            >
                                <option
                                    v-for="e in empleados"
                                    :key="e.id"
                                    :value="e.id"
                                >
                                    {{ e.name }} ({{ e.employee_code }})
                                </option>
                            </select>
                            <InputError
                                :message="form.errors.employee_id"
                                class="mt-1"
                            />
                        </div>

                        <div>
                            <InputLabel value="Tipo" />
                            <select
                                v-model="form.type"
                                class="mt-1 block w-full rounded-md border-slate-300 shadow-sm"
                            >
                                <option
                                    v-for="t in tipos"
                                    :key="t.value"
                                    :value="t.value"
                                >
                                    {{ t.label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <InputLabel value="Motivo / referencia" />
                            <TextInput
                                v-model="form.note"
                                class="mt-1 w-full"
                                placeholder="Ej.: Vacaciones agosto acordadas con RRHH"
                                required
                            />
                            <InputError :message="form.errors.note" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel for="start_date" value="Desde" />
                            <TextInput
                                id="start_date"
                                v-model="form.start_date"
                                type="date"
                                class="mt-1 w-full"
                                required
                            />
                            <InputError
                                :message="form.errors.start_date"
                                class="mt-1"
                            />
                        </div>

                        <div>
                            <InputLabel for="end_date" value="Hasta" />
                            <TextInput
                                id="end_date"
                                v-model="form.end_date"
                                type="date"
                                class="mt-1 w-full"
                                required
                            />
                            <InputError
                                :message="form.errors.end_date"
                                class="mt-1"
                            />
                        </div>
                    </div>

                    <PrimaryButton :disabled="form.processing || !empleados.length">
                        Registrar y aprobar
                    </PrimaryButton>
                </form>

                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h3 class="font-semibold text-slate-900">
                            Ausencias activas del equipo
                        </h3>
                        <p class="mt-1 text-xs text-slate-500">
                            Últimos 3 meses y futuras. Las marcadas como
                            «Manual» no pasaron por solicitud del empleado.
                        </p>
                    </div>

                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">
                                    Empleado
                                </th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">
                                    Tipo
                                </th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">
                                    Periodo
                                </th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">
                                    Origen
                                </th>
                                <th class="px-4 py-3 text-right font-semibold text-slate-600">
                                    Acción
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="a in ausencias" :key="a.id">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900">
                                        {{ a.employee_name }}
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        {{ a.employee_code }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    {{ a.type_label }}
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    {{ a.periodo_label }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="
                                            a.es_manual
                                                ? 'bg-indigo-100 text-indigo-800'
                                                : 'bg-slate-100 text-slate-600'
                                        "
                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    >
                                        {{
                                            a.es_manual
                                                ? 'Manual'
                                                : 'Solicitud aprobada'
                                        }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button
                                        type="button"
                                        class="text-red-600 hover:text-red-800"
                                        @click="anular(a)"
                                    >
                                        Anular
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!ausencias.length">
                                <td
                                    colspan="5"
                                    class="px-4 py-8 text-center text-slate-500"
                                >
                                    No hay ausencias aprobadas recientes.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
