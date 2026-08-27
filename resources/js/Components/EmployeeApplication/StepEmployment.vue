<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { watch } from 'vue';
import {
    CONTRACT_TYPES,
    IRPF_FAMILY_SITUATIONS,
    WORK_SCHEDULES,
    detectBankName,
    validateIban,
} from '@/utils/employeeApplicationValidation';

const props = defineProps({
    form: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    departments: { type: Array, default: () => [] },
    positions: { type: Array, default: () => [] },
});

watch(
    () => props.form.iban,
    (iban) => {
        if (validateIban(iban)) {
            props.form.bank_name = detectBankName(iban) || 'Entidad bancaria';
        } else {
            props.form.bank_name = '';
        }
    },
);
</script>

<template>
    <div class="space-y-6">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Datos laborales y bancarios</h2>
            <p class="mt-1 text-sm text-slate-500">Puesto, contrato, cuenta bancaria y retención IRPF (modelo 145).</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <InputLabel for="position" value="Puesto *" />
                <select
                    id="position"
                    v-model="form.position"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="" disabled>Seleccionar...</option>
                    <option v-for="p in positions" :key="p" :value="p">{{ p }}</option>
                </select>
                <InputError :message="errors.position" class="mt-1" />
            </div>
            <div>
                <InputLabel for="department" value="Departamento *" />
                <select
                    id="department"
                    v-model="form.department"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="" disabled>Seleccionar...</option>
                    <option v-for="d in departments" :key="d" :value="d">{{ d }}</option>
                </select>
                <InputError :message="errors.department" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <InputLabel for="start_date" value="Fecha incorporación *" />
                <input
                    id="start_date"
                    v-model="form.start_date"
                    type="date"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                <InputError :message="errors.start_date" class="mt-1" />
            </div>
            <div>
                <InputLabel for="contract_type" value="Tipo de contrato *" />
                <select
                    id="contract_type"
                    v-model="form.contract_type"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="" disabled>Seleccionar...</option>
                    <option v-for="c in CONTRACT_TYPES" :key="c.value" :value="c.value">{{ c.label }}</option>
                </select>
                <InputError :message="errors.contract_type" class="mt-1" />
            </div>
            <div>
                <InputLabel for="work_schedule" value="Jornada *" />
                <select
                    id="work_schedule"
                    v-model="form.work_schedule"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="" disabled>Seleccionar...</option>
                    <option v-for="w in WORK_SCHEDULES" :key="w.value" :value="w.value">{{ w.label }}</option>
                </select>
                <InputError :message="errors.work_schedule" class="mt-1" />
            </div>
        </div>

        <div>
            <InputLabel for="iban" value="IBAN (cuenta nómina) *" />
            <TextInput id="iban" v-model="form.iban" class="mt-1 block w-full uppercase font-mono" placeholder="ES00 0000 0000 0000 0000 0000" />
            <p v-if="form.bank_name" class="mt-1 text-xs text-emerald-700">Entidad detectada: {{ form.bank_name }}</p>
            <InputError :message="errors.iban" class="mt-1" />
        </div>

        <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-4">
            <h3 class="text-sm font-semibold text-amber-900">Modelo 145 — Retención IRPF</h3>
            <p class="mt-1 text-xs text-amber-800">Datos simplificados para calcular la retención en nómina.</p>

            <div class="mt-4 space-y-4">
                <div>
                    <InputLabel for="irpf_family_situation" value="Situación familiar *" />
                    <select
                        id="irpf_family_situation"
                        v-model="form.irpf_family_situation"
                        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="" disabled>Seleccionar...</option>
                        <option v-for="s in IRPF_FAMILY_SITUATIONS" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                    <InputError :message="errors.irpf_family_situation" class="mt-1" />
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <InputLabel for="irpf_children_under_3" value="Hijos menores de 3 años" />
                        <input
                            id="irpf_children_under_3"
                            v-model.number="form.irpf_children_under_3"
                            type="number"
                            min="0"
                            max="20"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                    <div>
                        <InputLabel for="irpf_disability_degree" value="Grado discapacidad (%)" />
                        <input
                            id="irpf_disability_degree"
                            v-model.number="form.irpf_disability_degree"
                            type="number"
                            min="0"
                            max="100"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                    <div>
                        <InputLabel for="irpf_additional_withholding" value="Retención adicional (%)" />
                        <input
                            id="irpf_additional_withholding"
                            v-model="form.irpf_additional_withholding"
                            type="number"
                            min="0"
                            max="30"
                            step="0.1"
                            placeholder="Opcional"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div>
            <InputLabel for="notes" value="Comentarios adicionales" />
            <textarea
                id="notes"
                v-model="form.notes"
                rows="3"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                placeholder="Información relevante para RRHH..."
            />
        </div>
    </div>
</template>
