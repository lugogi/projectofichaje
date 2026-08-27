import { onMounted, ref, watch } from 'vue';
import { createEmptyForm } from '@/utils/employeeApplicationValidation';

const STORAGE_KEY = 'fichatime_employee_application_wizard';

export function useEmployeeApplicationWizard() {
    const currentStep = ref(1);
    const form = ref(createEmptyForm());
    const stepErrors = ref({});
    const lastSavedAt = ref(null);

    const persist = () => {
        const payload = {
            currentStep: currentStep.value,
            form: { ...form.value, document_images: [], signature: form.value.signature || '' },
            savedAt: new Date().toISOString(),
        };
        localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
        lastSavedAt.value = payload.savedAt;
    };

    const restore = () => {
        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return false;
            const data = JSON.parse(raw);
            if (data.form) {
                form.value = { ...createEmptyForm(), ...data.form, document_images: [] };
            }
            if (data.currentStep) currentStep.value = data.currentStep;
            lastSavedAt.value = data.savedAt;
            return true;
        } catch {
            return false;
        }
    };

    const clearDraft = () => {
        localStorage.removeItem(STORAGE_KEY);
        lastSavedAt.value = null;
    };

    watch(form, persist, { deep: true });
    watch(currentStep, persist);

    onMounted(() => restore());

    return {
        currentStep,
        form,
        stepErrors,
        lastSavedAt,
        persist,
        restore,
        clearDraft,
    };
}
