<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    empresas: {
        type: Array,
        default: () => [],
    },
    companyId: {
        type: String,
        default: null,
    },
    salas: {
        type: Array,
        default: () => [],
    },
});

const companyId = ref(props.companyId ?? props.empresas[0]?.id ?? '');

const cambiarEmpresa = () => {
    router.get(route('admin.zones.index'), { company_id: companyId.value }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const formNueva = useForm({
    company_id: companyId.value,
    name: '',
    ip: '',
    type: 'sala',
    active: true,
});

const crear = () => {
    formNueva.company_id = companyId.value;
    formNueva.post(route('admin.zones.store'), {
        preserveScroll: true,
        onSuccess: () => formNueva.reset('name', 'ip'),
    });
};

const editando = ref(null);
const formEditar = useForm({
    name: '',
    ip: '',
    type: 'sala',
    active: true,
});

const iniciarEdicion = (sala) => {
    editando.value = sala.id;
    formEditar.name = sala.name;
    formEditar.ip = sala.ip;
    formEditar.type = sala.type;
    formEditar.active = sala.active;
};

const guardarEdicion = () => {
    formEditar.put(route('admin.zones.update', editando.value), {
        preserveScroll: true,
        onSuccess: () => (editando.value = null),
    });
};

const eliminar = (sala) => {
    if (!confirm(`¿Eliminar la sala «${sala.name}»?`)) return;
    router.delete(route('admin.zones.destroy', sala.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Salas y redes" />

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
                    Salas y redes autorizadas
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    IPs o rangos CIDR desde los que se permite fichar cuando la
                    restricción por red está activa.
                </p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 space-y-6">
                <div>
                    <InputLabel value="Empresa" />
                    <select
                        v-model="companyId"
                        class="mt-1 block w-full max-w-xs rounded-md border-slate-300 shadow-sm"
                        @change="cambiarEmpresa"
                    >
                        <option v-for="e in empresas" :key="e.id" :value="e.id">
                            {{ e.name }}
                        </option>
                    </select>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="font-semibold text-slate-900">Nueva sala</h3>
                    <form class="mt-4 grid gap-4 sm:grid-cols-2" @submit.prevent="crear">
                        <div>
                            <InputLabel value="Nombre" />
                            <TextInput v-model="formNueva.name" class="mt-1 w-full" required />
                        </div>
                        <div>
                            <InputLabel value="IP o CIDR" />
                            <TextInput
                                v-model="formNueva.ip"
                                class="mt-1 w-full"
                                placeholder="192.168.1.0/24"
                                required
                            />
                        </div>
                        <div class="sm:col-span-2">
                            <PrimaryButton :disabled="formNueva.processing">
                                Añadir sala
                            </PrimaryButton>
                        </div>
                    </form>
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">
                                    Nombre
                                </th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">
                                    IP / CIDR
                                </th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">
                                    Activa
                                </th>
                                <th class="px-4 py-3 text-right font-semibold text-slate-600">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="sala in salas" :key="sala.id">
                                <template v-if="editando === sala.id">
                                    <td class="px-4 py-3">
                                        <TextInput
                                            v-model="formEditar.name"
                                            class="w-full"
                                        />
                                    </td>
                                    <td class="px-4 py-3">
                                        <TextInput
                                            v-model="formEditar.ip"
                                            class="w-full"
                                        />
                                    </td>
                                    <td class="px-4 py-3">
                                        <input
                                            v-model="formEditar.active"
                                            type="checkbox"
                                            class="rounded border-slate-300"
                                        />
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button
                                            type="button"
                                            class="text-indigo-600"
                                            @click="guardarEdicion"
                                        >
                                            Guardar
                                        </button>
                                        <button
                                            type="button"
                                            class="ml-2 text-slate-500"
                                            @click="editando = null"
                                        >
                                            Cancelar
                                        </button>
                                    </td>
                                </template>
                                <template v-else>
                                    <td class="px-4 py-3 font-medium text-slate-900">
                                        {{ sala.name }}
                                    </td>
                                    <td class="px-4 py-3 font-mono text-slate-700">
                                        {{ sala.ip }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            :class="
                                                sala.active
                                                    ? 'bg-emerald-100 text-emerald-800'
                                                    : 'bg-slate-100 text-slate-600'
                                            "
                                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        >
                                            {{ sala.active ? 'Sí' : 'No' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button
                                            type="button"
                                            class="text-indigo-600 hover:text-indigo-800"
                                            @click="iniciarEdicion(sala)"
                                        >
                                            Editar
                                        </button>
                                        <button
                                            type="button"
                                            class="ml-3 text-red-600 hover:text-red-800"
                                            @click="eliminar(sala)"
                                        >
                                            Eliminar
                                        </button>
                                    </td>
                                </template>
                            </tr>
                            <tr v-if="!salas.length">
                                <td
                                    colspan="4"
                                    class="px-4 py-8 text-center text-slate-500"
                                >
                                    No hay salas configuradas para esta empresa.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
