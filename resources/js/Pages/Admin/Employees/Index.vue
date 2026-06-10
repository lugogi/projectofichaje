<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    empleados: {
        type: Array,
        default: () => [],
    },
});

const etiquetaRol = (rol) =>
    ({
        admin: 'Administración',
        manager: 'Encargado',
        employee: 'Empleado',
    })[rol] ?? rol;

const darDeBaja = (empleado) => {
    if (
        !confirm(
            `¿Dar de baja a ${empleado.name}? No podrá volver a iniciar sesión.`,
        )
    ) {
        return;
    }

    router.delete(route('admin.employees.destroy', empleado.id));
};
</script>

<template>
    <Head title="Gestión de empleados" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <Link
                        :href="route('admin.index')"
                        class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                    >
                        ← Volver a Administración
                    </Link>
                    <h2 class="mt-2 text-xl font-semibold text-slate-900">
                        Gestión de empleados
                    </h2>
                </div>
                <Link
                    :href="route('admin.employees.create')"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                >
                    Nuevo empleado
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div
                    class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                >
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">
                                    Nombre
                                </th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">
                                    Código
                                </th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">
                                    Rol
                                </th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">
                                    Encargado
                                </th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">
                                    Estado
                                </th>
                                <th class="px-4 py-3 text-right font-semibold text-slate-600">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="empleado in empleados" :key="empleado.id">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900">
                                        {{ empleado.name }}
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        {{ empleado.email }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    {{ empleado.employee_code }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700"
                                    >
                                        {{ etiquetaRol(empleado.role) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-600">
                                    {{ empleado.manager_name ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        :class="
                                            empleado.activo
                                                ? 'bg-emerald-100 text-emerald-800'
                                                : 'bg-red-100 text-red-800'
                                        "
                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    >
                                        {{ empleado.activo ? 'Activo' : 'Baja' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Link
                                        v-if="empleado.activo"
                                        :href="route('admin.employees.edit', empleado.id)"
                                        class="text-indigo-600 hover:text-indigo-800"
                                    >
                                        Editar
                                    </Link>
                                    <button
                                        v-if="empleado.activo && empleado.role !== 'admin'"
                                        type="button"
                                        class="ml-3 text-red-600 hover:text-red-800"
                                        @click="darDeBaja(empleado)"
                                    >
                                        Baja
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
