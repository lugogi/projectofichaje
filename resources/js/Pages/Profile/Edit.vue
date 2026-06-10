<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ExportarRegistros from './Partials/ExportarRegistros.vue';
import HorasContrato from './Partials/HorasContrato.vue';
import MisDatos from './Partials/MisDatos.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    usuario: {
        type: Object,
        required: true,
    },
    resumenHoras: {
        type: Object,
        required: true,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Mi perfil" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl font-semibold leading-tight text-gray-800"
            >
                Mi perfil
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                    <MisDatos :usuario="usuario" class="max-w-2xl" />
                </div>

                <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                    <HorasContrato :resumen="resumenHoras" />
                </div>

                <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                    <ExportarRegistros :mes-actual="resumenHoras.periodo.mes" />
                </div>

                <div class="bg-white p-4 shadow sm:rounded-lg sm:p-8">
                    <UpdatePasswordForm class="max-w-xl" />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
