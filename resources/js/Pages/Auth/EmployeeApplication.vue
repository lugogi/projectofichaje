<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import StepContact from '@/Components/EmployeeApplication/StepContact.vue';
import StepDocumentation from '@/Components/EmployeeApplication/StepDocumentation.vue';
import StepEmployment from '@/Components/EmployeeApplication/StepEmployment.vue';
import StepPersonalData from '@/Components/EmployeeApplication/StepPersonalData.vue';
import StepSummary from '@/Components/EmployeeApplication/StepSummary.vue';
import WizardStepper from '@/Components/EmployeeApplication/WizardStepper.vue';
import { useEmployeeApplicationWizard } from '@/composables/useEmployeeApplicationWizard';
import { WIZARD_STEPS, validateStep } from '@/utils/employeeApplicationValidation';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    departments: { type: Array, default: () => [] },
    positions: { type: Array, default: () => [] },
    privacy: { type: Object, required: true },
});

const { currentStep, form, stepErrors, lastSavedAt, clearDraft } = useEmployeeApplicationWizard();

const totalSteps = WIZARD_STEPS.length;
const isFirst = computed(() => currentStep.value === 1);
const isLast = computed(() => currentStep.value === totalSteps);

const savedLabel = computed(() => {
    if (!lastSavedAt.value) return null;
    return new Date(lastSavedAt.value).toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
});

const validateCurrentStep = () => {
    stepErrors.value = validateStep(currentStep.value, form.value);
    return Object.keys(stepErrors.value).length === 0;
};

const nextStep = () => {
    if (!validateCurrentStep()) return;
    if (currentStep.value < totalSteps) currentStep.value += 1;
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const prevStep = () => {
    stepErrors.value = {};
    if (currentStep.value > 1) currentStep.value -= 1;
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const goToStep = (step) => {
    currentStep.value = step;
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const submit = () => {
    if (!validateCurrentStep()) return;

    const fd = new FormData();
    const data = form.value;

    Object.entries(data).forEach(([key, value]) => {
        if (key === 'document_images') {
            (value || []).forEach((file) => fd.append('document_images[]', file));
            return;
        }
        if (value === null || value === undefined) return;
        if (typeof value === 'boolean') {
            fd.append(key, value ? '1' : '0');
            return;
        }
        fd.append(key, value);
    });

    fd.set('phone_verified', data.phone_verified ? '1' : '0');
    fd.set('gdpr_accepted', data.gdpr_accepted ? '1' : '0');
    fd.set('gdpr_version', props.privacy.consent_version);

    router.post(route('employee-application.store'), fd, {
        forceFormData: true,
        onSuccess: () => clearDraft(),
        onError: (errors) => {
            stepErrors.value = errors;
        },
    });
};
</script>

<template>
    <GuestLayout wide>
        <Head title="Alta de trabajador" />

        <div class="mb-4">
            <h1 class="text-xl font-bold text-slate-900 sm:text-2xl">Solicitud de alta laboral</h1>
            <p class="mt-1 text-sm text-slate-600">
                Completa el formulario en {{ totalSteps }} pasos. Tu progreso se guarda automáticamente.
            </p>
            <p v-if="savedLabel" class="mt-1 text-xs text-emerald-600">Borrador guardado a las {{ savedLabel }}</p>
        </div>

        <WizardStepper :steps="WIZARD_STEPS" :current-step="currentStep" />

        <form @submit.prevent="isLast ? submit() : nextStep()">
            <StepPersonalData v-if="currentStep === 1" :form="form" :errors="stepErrors" />
            <StepContact v-else-if="currentStep === 2" :form="form" :errors="stepErrors" />
            <StepDocumentation v-else-if="currentStep === 3" :form="form" :errors="stepErrors" />
            <StepEmployment
                v-else-if="currentStep === 4"
                :form="form"
                :errors="stepErrors"
                :departments="departments"
                :positions="positions"
            />
            <StepSummary
                v-else
                :form="form"
                :errors="stepErrors"
                :privacy="privacy"
                @edit-step="goToStep"
            />

            <div class="mt-8 flex flex-col-reverse gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
                <Link :href="route('login')" class="text-center text-sm text-slate-600 underline hover:text-slate-900 sm:text-left">
                    Ya tengo cuenta
                </Link>

                <div class="flex gap-2">
                    <button
                        v-if="!isFirst"
                        type="button"
                        class="flex-1 rounded-md border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50 sm:flex-none"
                        @click="prevStep"
                    >
                        Anterior
                    </button>

                    <PrimaryButton v-if="!isLast" type="submit" class="flex-1 justify-center sm:flex-none">
                        Siguiente
                    </PrimaryButton>

                    <PrimaryButton v-else type="submit" class="flex-1 justify-center sm:flex-none">
                        Enviar solicitud
                    </PrimaryButton>
                </div>
            </div>
        </form>
    </GuestLayout>
</template>
