<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    empleados: {
        type: Array,
        default: () => [],
    },
    empleadoSeleccionado: {
        type: Object,
        default: null,
    },
    horario: {
        type: Array,
        default: () => [],
    },
});

const empleadoId = ref(props.empleadoSeleccionado?.id ?? props.empleados[0]?.id ?? '');

const cambiarEmpleado = () => {
    router.get(route('admin.schedules.index'), { employee_id: empleadoId.value }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const buildDays = () =>
    props.horario.map((d) => ({
        weekday: d.weekday,
        active: d.active,
        start_time: d.start_time,
        end_time: d.end_time,
    }));

const form = useForm({
    days: buildDays(),
});

watch(
    () => props.horario,
    () => {
        form.days = buildDays();
    },
    { deep: true },
);

const guardar = () => {
    if (!props.empleadoSeleccionado) return;
    form.put(route('admin.schedules.update', props.empleadoSeleccionado.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Horarios y turnos" />

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
                    Horarios y turnos
                </h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <div class="mb-6">
                    <InputLabel value="Empleado" />
                    <select
                        v-model="empleadoId"
                        class="mt-1 block w-full rounded-md border-slate-300 shadow-sm"
                        @change="cambiarEmpleado"
                    >
                        <option v-for="e in empleados" :key="e.id" :value="e.id">
                            {{ e.name }} ({{ e.employee_code }})
                        </option>
                    </select>
                </div>

                <form
                    v-if="empleadoSeleccionado"
                    class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                    @submit.prevent="guardar"
                >
                    <p class="mb-4 text-sm text-slate-500">
                        Horario semanal de
                        <strong class="text-slate-800">{{
                            empleadoSeleccionado.name
                        }}</strong>
                    </p>

                    <div class="space-y-3">
                        <div
                            v-for="(dia, idx) in form.days"
                            :key="dia.weekday"
                            class="flex flex-wrap items-center gap-3 rounded-lg border border-slate-100 bg-slate-50 px-4 py-3"
                        >
                            <label class="flex w-32 items-center gap-2 text-sm font-medium">
                                <input
                                    v-model="form.days[idx].active"
                                    type="checkbox"
                                    class="rounded border-slate-300"
                                />
                                {{ horario[idx]?.label }}
                            </label>
                            <input
                                v-model="form.days[idx].start_time"
                                type="time"
                                :disabled="!form.days[idx].active"
                                class="rounded-md border-slate-300 text-sm shadow-sm disabled:opacity-50"
                            />
                            <span class="text-slate-400">—</span>
                            <input
                                v-model="form.days[idx].end_time"
                                type="time"
                                :disabled="!form.days[idx].active"
                                class="rounded-md border-slate-300 text-sm shadow-sm disabled:opacity-50"
                            />
                        </div>
                    </div>

                    <div class="mt-6">
                        <PrimaryButton :disabled="form.processing">
                            Guardar horario
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
