<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { MARITAL_STATUSES, NATIONALITIES } from '@/utils/employeeApplicationValidation';

defineProps({
    form: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
});
</script>

<template>
    <div class="space-y-4">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Datos personales</h2>
            <p class="mt-1 text-sm text-slate-500">Información básica del candidato.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <InputLabel for="name" value="Nombre *" />
                <TextInput id="name" v-model="form.name" class="mt-1 block w-full" autocomplete="given-name" />
                <InputError :message="errors.name" class="mt-1" />
            </div>
            <div>
                <InputLabel for="surname" value="Apellidos *" />
                <TextInput id="surname" v-model="form.surname" class="mt-1 block w-full" autocomplete="family-name" />
                <InputError :message="errors.surname" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <InputLabel for="birth_date" value="Fecha de nacimiento *" />
                <input
                    id="birth_date"
                    v-model="form.birth_date"
                    type="date"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                <InputError :message="errors.birth_date" class="mt-1" />
            </div>
            <div>
                <InputLabel for="nationality" value="Nacionalidad *" />
                <select
                    id="nationality"
                    v-model="form.nationality"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option v-for="n in NATIONALITIES" :key="n" :value="n">{{ n }}</option>
                </select>
                <InputError :message="errors.nationality" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <InputLabel for="marital_status" value="Estado civil *" />
                <select
                    id="marital_status"
                    v-model="form.marital_status"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="" disabled>Seleccionar...</option>
                    <option v-for="s in MARITAL_STATUSES" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
                <InputError :message="errors.marital_status" class="mt-1" />
            </div>
            <div>
                <InputLabel for="dependents_count" value="Hijos a cargo *" />
                <input
                    id="dependents_count"
                    v-model.number="form.dependents_count"
                    type="number"
                    min="0"
                    max="20"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                <InputError :message="errors.dependents_count" class="mt-1" />
            </div>
        </div>

        <label class="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
            <input
                v-model="form.disability_recognized"
                type="checkbox"
                class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
            />
            <span class="text-sm text-slate-700">
                <span class="font-medium">Discapacidad reconocida</span>
                <span class="mt-0.5 block text-slate-500">Marca si dispones de certificado oficial de discapacidad.</span>
            </span>
        </label>
    </div>
</template>
