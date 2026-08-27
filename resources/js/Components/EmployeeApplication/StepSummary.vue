<script setup>
import InputError from '@/Components/InputError.vue';
import GdprConsent from '@/Components/EmployeeApplication/GdprConsent.vue';
import SignatureCanvas from '@/Components/EmployeeApplication/SignatureCanvas.vue';
import {
    CONTRACT_TYPES,
    MARITAL_STATUSES,
    WORK_PERMIT_TYPES,
    WORK_SCHEDULES,
    IRPF_FAMILY_SITUATIONS,
} from '@/utils/employeeApplicationValidation';
import { computed } from 'vue';

const props = defineProps({
    form: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    privacy: { type: Object, required: true },
});

defineEmits(['edit-step']);

const label = (list, value) => list.find((i) => i.value === value)?.label || value;

const sections = computed(() => [
    {
        step: 1,
        title: 'Datos personales',
        rows: [
            ['Nombre', `${props.form.name} ${props.form.surname}`.trim()],
            ['Fecha nacimiento', props.form.birth_date],
            ['Nacionalidad', props.form.nationality],
            ['Estado civil', label(MARITAL_STATUSES, props.form.marital_status)],
            ['Hijos a cargo', props.form.dependents_count],
            ['Discapacidad', props.form.disability_recognized ? 'Sí' : 'No'],
        ],
    },
    {
        step: 2,
        title: 'Contacto y residencia',
        rows: [
            ['Dirección', props.form.street],
            ['CP / Municipio / Provincia', `${props.form.postal_code} ${props.form.city}, ${props.form.province}`],
            ['Teléfono', props.form.phone + (props.form.phone_verified ? ' (verificado)' : '')],
            ['Email', props.form.email],
        ],
    },
    {
        step: 3,
        title: 'Documentación',
        rows: [
            ['Documento', `${props.form.document_type?.toUpperCase()} ${props.form.document_number}`],
            ['Caducidad', props.form.document_expiry_date],
            ...(props.form.has_social_security
                ? [['Nº afiliación SS', props.form.social_security_number]]
                : [
                      ['Permiso de trabajo', label(WORK_PERMIT_TYPES, props.form.work_permit_type)],
                      ['NIE del permiso', props.form.work_permit_number],
                      ['Caducidad permiso', props.form.work_permit_expiry],
                      ['Pasaporte', `${props.form.passport_number} (cad. ${props.form.passport_expiry})`],
                  ]),
            ['OCR verificado', props.form.document_ocr_verified ? 'Sí' : 'No'],
            ['Archivos', `${props.form.document_images?.length || 0} adjunto(s)`],
        ],
    },
    {
        step: 4,
        title: 'Laboral y bancario',
        rows: [
            ['Puesto / Depto.', `${props.form.position} — ${props.form.department}`],
            ['Incorporación', props.form.start_date],
            ['Contrato / Jornada', `${label(CONTRACT_TYPES, props.form.contract_type)} / ${label(WORK_SCHEDULES, props.form.work_schedule)}`],
            ['IBAN', props.form.iban],
            ['Banco', props.form.bank_name || '—'],
            ['Modelo 145', label(IRPF_FAMILY_SITUATIONS, props.form.irpf_family_situation)],
        ],
    },
]);
</script>

<template>
    <div class="space-y-6">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Resumen y envío</h2>
            <p class="mt-1 text-sm text-slate-500">Revisa todos los datos, acepta la política de privacidad y firma.</p>
        </div>

        <div
            v-for="section in sections"
            :key="section.step"
            class="rounded-xl border border-slate-200 bg-slate-50/50 p-4"
        >
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-slate-800">{{ section.title }}</h3>
                <button
                    type="button"
                    class="text-xs font-medium text-indigo-600 hover:text-indigo-800"
                    @click="$emit('edit-step', section.step)"
                >
                    Editar
                </button>
            </div>
            <dl class="grid gap-2 sm:grid-cols-2">
                <div v-for="[key, val] in section.rows" :key="key">
                    <dt class="text-xs text-slate-500">{{ key }}</dt>
                    <dd class="text-sm font-medium text-slate-900 break-words">{{ val || '—' }}</dd>
                </div>
            </dl>
        </div>

        <GdprConsent
            v-model="form.gdpr_accepted"
            :error="errors.gdpr_accepted"
            :controller="privacy.controller"
            :links="privacy.links"
        />

        <div>
            <p class="mb-2 text-sm font-medium text-slate-800">Firma digital *</p>
            <SignatureCanvas v-model="form.signature" />
            <InputError :message="errors.signature" class="mt-1" />
        </div>
    </div>
</template>
