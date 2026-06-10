<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    empleado: {
        type: Object,
        default: null,
    },
    empresas: {
        type: Array,
        default: () => [],
    },
    calendarios: {
        type: Array,
        default: () => [],
    },
    encargados: {
        type: Array,
        default: () => [],
    },
    roles: {
        type: Array,
        default: () => [],
    },
});

const editando = computed(() => Boolean(props.empleado?.id));

const form = useForm({
    name: props.empleado?.name ?? '',
    email: props.empleado?.email ?? '',
    employee_code: props.empleado?.employee_code ?? '',
    role: props.empleado?.role ?? 'employee',
    hire_date: props.empleado?.hire_date ?? new Date().toISOString().slice(0, 10),
    company_id: props.empleado?.company_id ?? props.empresas[0]?.id ?? '',
    work_calendar_id:
        props.empleado?.work_calendar_id ?? props.calendarios[0]?.id ?? '',
    manager_id: props.empleado?.manager_id ?? '',
    password: '',
    employment_status: props.empleado?.employment_status ?? 1,
});

const calendariosFiltrados = computed(() =>
    props.calendarios.filter((c) => c.company_id === form.company_id),
);

watch(
    () => props.empleado,
    (empleado) => {
        if (!empleado) return;
        form.name = empleado.name;
        form.email = empleado.email;
        form.employee_code = empleado.employee_code;
        form.role = empleado.role;
        form.hire_date = empleado.hire_date ?? form.hire_date;
        form.company_id = empleado.company_id ?? form.company_id;
        form.work_calendar_id = empleado.work_calendar_id ?? form.work_calendar_id;
        form.manager_id = empleado.manager_id ?? '';
    },
);

const enviar = () => {
    if (editando.value) {
        form.put(route('admin.employees.update', props.empleado.id));
    } else {
        form.post(route('admin.employees.store'));
    }
};
</script>

<template>
    <Head :title="editando ? 'Editar empleado' : 'Nuevo empleado'" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <Link
                    :href="route('admin.employees.index')"
                    class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                >
                    ← Volver a empleados
                </Link>
                <h2 class="mt-2 text-xl font-semibold text-slate-900">
                    {{ editando ? 'Editar empleado' : 'Nuevo empleado' }}
                </h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
                <form
                    class="space-y-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                    @submit.prevent="enviar"
                >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <InputLabel for="name" value="Nombre completo" />
                            <TextInput
                                id="name"
                                v-model="form.name"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError :message="form.errors.name" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel for="email" value="Correo electrónico" />
                            <TextInput
                                id="email"
                                v-model="form.email"
                                type="email"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError :message="form.errors.email" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel for="employee_code" value="Código empleado" />
                            <TextInput
                                id="employee_code"
                                v-model="form.employee_code"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError
                                :message="form.errors.employee_code"
                                class="mt-1"
                            />
                        </div>

                        <div>
                            <InputLabel for="role" value="Rol" />
                            <select
                                id="role"
                                v-model="form.role"
                                class="mt-1 block w-full rounded-md border-slate-300 shadow-sm"
                            >
                                <option
                                    v-for="rol in roles"
                                    :key="rol.value"
                                    :value="rol.value"
                                >
                                    {{ rol.label }}
                                </option>
                            </select>
                            <InputError :message="form.errors.role" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel for="hire_date" value="Fecha de alta" />
                            <TextInput
                                id="hire_date"
                                v-model="form.hire_date"
                                type="date"
                                class="mt-1 block w-full"
                            />
                        </div>

                        <div>
                            <InputLabel for="company_id" value="Empresa" />
                            <select
                                id="company_id"
                                v-model="form.company_id"
                                class="mt-1 block w-full rounded-md border-slate-300 shadow-sm"
                            >
                                <option
                                    v-for="empresa in empresas"
                                    :key="empresa.id"
                                    :value="empresa.id"
                                >
                                    {{ empresa.name }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <InputLabel for="work_calendar_id" value="Calendario laboral" />
                            <select
                                id="work_calendar_id"
                                v-model="form.work_calendar_id"
                                class="mt-1 block w-full rounded-md border-slate-300 shadow-sm"
                            >
                                <option
                                    v-for="cal in calendariosFiltrados"
                                    :key="cal.id"
                                    :value="cal.id"
                                >
                                    {{ cal.name }}
                                </option>
                            </select>
                        </div>

                        <div v-if="form.role === 'employee'" class="sm:col-span-2">
                            <InputLabel for="manager_id" value="Encargado asignado" />
                            <select
                                id="manager_id"
                                v-model="form.manager_id"
                                class="mt-1 block w-full rounded-md border-slate-300 shadow-sm"
                            >
                                <option value="">Sin encargado</option>
                                <option
                                    v-for="enc in encargados"
                                    :key="enc.id"
                                    :value="enc.id"
                                >
                                    {{ enc.name }} ({{ enc.employee_code }})
                                </option>
                            </select>
                        </div>

                        <div class="sm:col-span-2">
                            <InputLabel
                                for="password"
                                :value="
                                    editando
                                        ? 'Nueva contraseña (opcional)'
                                        : 'Contraseña inicial'
                                "
                            />
                            <TextInput
                                id="password"
                                v-model="form.password"
                                type="password"
                                class="mt-1 block w-full"
                                :required="!editando"
                            />
                            <InputError :message="form.errors.password" class="mt-1" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-3">
                        <Link
                            :href="route('admin.employees.index')"
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"
                        >
                            Cancelar
                        </Link>
                        <PrimaryButton :disabled="form.processing">
                            {{ editando ? 'Guardar cambios' : 'Crear empleado' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
