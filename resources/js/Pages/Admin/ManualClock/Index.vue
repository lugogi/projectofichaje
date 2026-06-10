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
    empleadoSeleccionado: {
        type: Object,
        default: null,
    },
    salas: {
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

const empleadoId = ref(props.empleadoSeleccionado?.id ?? props.empleados[0]?.id ?? '');

const cambiarEmpleado = () => {
    router.get(route('admin.manual-clock.index'), { employee_id: empleadoId.value }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const ahora = new Date();
const defaultDatetime = `${ahora.getFullYear()}-${String(ahora.getMonth() + 1).padStart(2, '0')}-${String(ahora.getDate()).padStart(2, '0')}T${String(ahora.getHours()).padStart(2, '0')}:${String(ahora.getMinutes()).padStart(2, '0')}`;

const form = useForm({
    employee_id: empleadoId.value,
    type: props.empleadoSeleccionado?.proxima_accion ?? 1,
    recorded_at: defaultDatetime,
    reason: '',
    clock_zone_id: '',
});

watch(empleadoId, (id) => {
    form.employee_id = id;
});

watch(
    () => props.empleadoSeleccionado,
    (empleado) => {
        if (empleado) {
            form.type = empleado.proxima_accion;
        }
    },
    { immediate: true },
);

const tipoLabel = computed(() => (form.type == 1 ? 'Entrada' : 'Salida'));

const enviar = () => {
    form.post(route('admin.manual-clock.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('reason'),
    });
};
</script>

<template>
    <Head title="Fichada manual" />

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
                    Fichada manual
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Registra fichajes olvidados. Quedan en el historial con origen
                    «admin» y motivo obligatorio.
                </p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <div
                    v-if="mensajeExito"
                    class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800 ring-1 ring-emerald-200"
                >
                    {{ mensajeExito }}
                </div>

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

                <div
                    v-if="empleadoSeleccionado"
                    class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <p class="text-sm text-slate-500">Estado actual</p>
                    <p class="mt-1 text-lg font-semibold text-slate-900">
                        {{
                            empleadoSeleccionado.estado === 'trabajando'
                                ? 'Trabajando'
                                : 'Fuera'
                        }}
                    </p>
                    <div
                        v-if="empleadoSeleccionado.registros_hoy?.length"
                        class="mt-4 space-y-2"
                    >
                        <p class="text-xs font-semibold uppercase text-slate-400">
                            Fichajes de hoy
                        </p>
                        <div
                            v-for="r in empleadoSeleccionado.registros_hoy"
                            :key="r.id"
                            class="flex justify-between text-sm text-slate-700"
                        >
                            <span>{{ r.label }}</span>
                            <span>{{ r.hora_corta }}</span>
                        </div>
                    </div>
                </div>

                <form
                    class="space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                    @submit.prevent="enviar"
                >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel value="Tipo de fichaje" />
                            <select
                                v-model="form.type"
                                class="mt-1 block w-full rounded-md border-slate-300 shadow-sm"
                            >
                                <option :value="1">Entrada</option>
                                <option :value="2">Salida</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="recorded_at" value="Fecha y hora" />
                            <TextInput
                                id="recorded_at"
                                v-model="form.recorded_at"
                                type="datetime-local"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError
                                :message="form.errors.recorded_at"
                                class="mt-1"
                            />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="clock_zone_id" value="Sala (opcional)" />
                        <select
                            id="clock_zone_id"
                            v-model="form.clock_zone_id"
                            class="mt-1 block w-full rounded-md border-slate-300 shadow-sm"
                        >
                            <option value="">Sin sala</option>
                            <option v-for="s in salas" :key="s.id" :value="s.id">
                                {{ s.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <InputLabel for="reason" value="Motivo (obligatorio)" />
                        <textarea
                            id="reason"
                            v-model="form.reason"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-slate-300 shadow-sm"
                            placeholder="Ej.: Olvidó fichar la salida ayer"
                            required
                        />
                        <InputError :message="form.errors.reason" class="mt-1" />
                    </div>

                    <PrimaryButton :disabled="form.processing">
                        Registrar {{ tipoLabel }}
                    </PrimaryButton>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
