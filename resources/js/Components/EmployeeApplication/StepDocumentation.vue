<script setup>
import { createWorker } from 'tesseract.js';
import { onBeforeUnmount, ref } from 'vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import {
    WORK_PERMIT_TYPES,
    extractDocumentFromText,
    isBeneficiaryNaf,
    normalizeNaf,
    validateDocument,
    validateNaf,
} from '@/utils/employeeApplicationValidation';
import { computed } from 'vue';

const props = defineProps({
    form: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
});

const videoRef = ref(null);
const stream = ref(null);
const cameraActive = ref(false);
const ocrLoading = ref(false);
const ocrMessage = ref('');
const ocrMatch = ref(null);
const previews = ref([]);

const startCamera = async () => {
    try {
        stream.value = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'environment' },
            audio: false,
        });
        cameraActive.value = true;
        if (videoRef.value) {
            videoRef.value.srcObject = stream.value;
        }
    } catch {
        ocrMessage.value = 'No se pudo acceder a la cámara. Comprueba los permisos del navegador.';
    }
};

const stopCamera = () => {
    stream.value?.getTracks().forEach((t) => t.stop());
    stream.value = null;
    cameraActive.value = false;
};

const captureAndScan = async () => {
    if (!videoRef.value) return;
    ocrLoading.value = true;
    ocrMessage.value = 'Analizando documento...';
    ocrMatch.value = null;

    const canvas = document.createElement('canvas');
    canvas.width = videoRef.value.videoWidth;
    canvas.height = videoRef.value.videoHeight;
    canvas.getContext('2d').drawImage(videoRef.value, 0, 0);

    try {
        canvas.toBlob(async (blob) => {
            if (blob) {
                const file = new File([blob], `scan-${Date.now()}.jpg`, { type: 'image/jpeg' });
                addFiles([file]);
            }

            const worker = await createWorker('spa');
            const { data } = await worker.recognize(canvas);
            await worker.terminate();

            const extracted = extractDocumentFromText(data.text);
            if (extracted) {
                props.form.document_type = extracted.type;
                props.form.document_number = extracted.number;
                const manual = props.form.document_number;
                ocrMatch.value = validateDocument(extracted.type, manual);
                props.form.document_ocr_verified = ocrMatch.value;
                ocrMessage.value = ocrMatch.value
                    ? `Documento detectado: ${extracted.number} — coincide y es válido.`
                    : `Detectado ${extracted.number}, pero no coincide con lo introducido o es inválido.`;
            } else {
                props.form.document_ocr_verified = false;
                ocrMessage.value = 'No se detectó un DNI/NIE válido. Introduce los datos manualmente.';
            }
            ocrLoading.value = false;
        }, 'image/jpeg', 0.92);
    } catch {
        ocrMessage.value = 'Error al procesar la imagen.';
        ocrLoading.value = false;
    }
};

const addFiles = (files) => {
    const list = Array.from(files);
    props.form.document_images = [...props.form.document_images, ...list].slice(0, 6);
    rebuildPreviews();
};

const handleFiles = (event) => {
    addFiles(event.target.files || []);
    event.target.value = '';
};

const removeFile = (index) => {
    props.form.document_images.splice(index, 1);
    rebuildPreviews();
};

const rebuildPreviews = () => {
    previews.value.forEach((p) => URL.revokeObjectURL(p.url));
    previews.value = props.form.document_images.map((file) => ({
        name: file.name,
        url: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
        isPdf: file.type === 'application/pdf',
    }));
};

const checkManualDocument = () => {
    if (!props.form.document_number) {
        ocrMatch.value = null;
        props.form.document_ocr_verified = false;
        return;
    }
    ocrMatch.value = validateDocument(props.form.document_type, props.form.document_number);
    props.form.document_ocr_verified = ocrMatch.value;
};

/**
 * Estado del NAF mientras se escribe, para avisar antes de intentar avanzar.
 */
const nafStatus = computed(() => {
    const value = props.form.social_security_number || '';
    if (!value.trim()) return null;

    if (isBeneficiaryNaf(value)) {
        return { ok: false, message: 'Ese número es de beneficiario. Para el alta hace falta el de titular (acaba en T).' };
    }

    if (!normalizeNaf(value).endsWith('T')) {
        return { ok: false, message: 'Debe terminar en T, la letra que identifica al titular.' };
    }

    if (!validateNaf(value)) {
        return { ok: false, message: 'Los dígitos de control no cuadran. Revisa el número.' };
    }

    return { ok: true, message: 'Número de titular válido.' };
});

const uploadHint = computed(() =>
    props.form.has_social_security
        ? 'DNI, NIE o tarjeta de la Seguridad Social.'
        : 'Obligatorio: pasaporte completo en vigor y la TIE o el visado donde figure el NIE y la habilitación para trabajar.',
);

onBeforeUnmount(() => {
    stopCamera();
    previews.value.forEach((p) => p.url && URL.revokeObjectURL(p.url));
});
</script>

<template>
    <div class="space-y-4">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Documentación</h2>
            <p class="mt-1 text-sm text-slate-500">Identificación, Seguridad Social y escaneo con cámara.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <InputLabel for="document_type" value="Tipo de documento *" />
                <select
                    id="document_type"
                    v-model="form.document_type"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    @change="checkManualDocument"
                >
                    <option value="dni">DNI</option>
                    <option value="nif">NIF</option>
                    <option value="nie">NIE</option>
                </select>
                <InputError :message="errors.document_type" class="mt-1" />
            </div>
            <div>
                <InputLabel for="document_number" value="Número de documento *" />
                <TextInput
                    id="document_number"
                    v-model="form.document_number"
                    class="mt-1 block w-full uppercase"
                    @blur="checkManualDocument"
                />
                <p v-if="ocrMatch === true" class="mt-1 text-xs text-emerald-600">✓ Letra de control correcta</p>
                <p v-else-if="ocrMatch === false" class="mt-1 text-xs text-red-600">Letra de control incorrecta</p>
                <InputError :message="errors.document_number" class="mt-1" />
            </div>
        </div>

        <div>
            <InputLabel for="document_expiry_date" value="Fecha de caducidad *" />
            <input
                id="document_expiry_date"
                v-model="form.document_expiry_date"
                type="date"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:max-w-xs"
            />
            <InputError :message="errors.document_expiry_date" class="mt-1" />
        </div>

        <div class="rounded-xl border border-slate-200 p-4">
            <p class="text-sm font-medium text-slate-900">Seguridad Social</p>

            <div class="mt-3 space-y-2">
                <label class="flex items-start gap-3">
                    <input
                        v-model="form.has_social_security"
                        type="radio"
                        :value="true"
                        class="mt-1 border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <span class="text-sm text-slate-700">
                        Tengo número de afiliación como <span class="font-medium">titular</span>
                    </span>
                </label>

                <label class="flex items-start gap-3">
                    <input
                        v-model="form.has_social_security"
                        type="radio"
                        :value="false"
                        class="mt-1 border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <span class="text-sm text-slate-700">
                        No lo tengo todavía — aportaré permiso de trabajo y documentación
                    </span>
                </label>
            </div>

            <div v-if="form.has_social_security" class="mt-4">
                <InputLabel for="social_security_number" value="Número de afiliación (NAF) *" />
                <TextInput
                    id="social_security_number"
                    v-model="form.social_security_number"
                    maxlength="20"
                    class="mt-1 block w-full uppercase font-mono sm:max-w-sm"
                    placeholder="28 12345678 40 T"
                />
                <p class="mt-1 text-xs text-slate-500">
                    12 dígitos seguidos de la letra <span class="font-semibold">T</span>, que identifica al titular.
                    La encontrarás en tu documento de afiliación o en la tarjeta sanitaria.
                </p>
                <p
                    v-if="nafStatus"
                    class="mt-1 text-xs"
                    :class="nafStatus.ok ? 'text-emerald-600' : 'text-red-600'"
                >
                    {{ nafStatus.ok ? '✓ ' : '' }}{{ nafStatus.message }}
                </p>
                <InputError v-if="!nafStatus" :message="errors.social_security_number" class="mt-1" />
            </div>

            <div v-else class="mt-4 space-y-4 border-t border-slate-200 pt-4">
                <p class="text-xs text-slate-600">
                    Sin número de titular necesitamos acreditar que puedes trabajar legalmente en España.
                </p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="work_permit_type" value="Documento que lo acredita *" />
                        <select
                            id="work_permit_type"
                            v-model="form.work_permit_type"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="" disabled>Seleccionar...</option>
                            <option v-for="t in WORK_PERMIT_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                        <InputError :message="errors.work_permit_type" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="work_permit_number" value="NIE que figura en el documento *" />
                        <TextInput
                            id="work_permit_number"
                            v-model="form.work_permit_number"
                            class="mt-1 block w-full uppercase"
                            placeholder="X1234567L"
                        />
                        <InputError :message="errors.work_permit_number" class="mt-1" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <InputLabel for="work_permit_expiry" value="Caducidad del permiso *" />
                        <input
                            id="work_permit_expiry"
                            v-model="form.work_permit_expiry"
                            type="date"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <InputError :message="errors.work_permit_expiry" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="passport_number" value="Nº de pasaporte *" />
                        <TextInput id="passport_number" v-model="form.passport_number" class="mt-1 block w-full uppercase" />
                        <InputError :message="errors.passport_number" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel for="passport_expiry" value="Caducidad pasaporte *" />
                        <input
                            id="passport_expiry"
                            v-model="form.passport_expiry"
                            type="date"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <InputError :message="errors.passport_expiry" class="mt-1" />
                    </div>
                </div>

                <div class="rounded-lg bg-amber-50 p-3 text-xs text-amber-900">
                    <p class="font-medium">Documentación que debes adjuntar más abajo</p>
                    <ul class="mt-1 list-disc space-y-0.5 ps-4">
                        <li><span class="font-medium">Pasaporte completo y en vigor</span>: documento de identidad de tu país de origen.</li>
                        <li>
                            <span class="font-medium">TIE o visado</span>: donde figure de forma expresa el Número de
                            Identificación de Extranjero (NIE) y la habilitación legal para trabajar por cuenta propia o ajena.
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p class="text-sm font-medium text-slate-900">Lector con cámara (OCR)</p>
                    <p class="text-xs text-slate-500">Captura el DNI/NIE para autorellenar y verificar.</p>
                </div>
                <div class="flex gap-2">
                    <button
                        v-if="!cameraActive"
                        type="button"
                        class="rounded-md bg-slate-800 px-3 py-2 text-sm text-white hover:bg-slate-900"
                        @click="startCamera"
                    >
                        Activar cámara
                    </button>
                    <button
                        v-else
                        type="button"
                        class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm hover:bg-slate-100"
                        @click="stopCamera"
                    >
                        Cerrar cámara
                    </button>
                    <button
                        v-if="cameraActive"
                        type="button"
                        class="rounded-md bg-indigo-600 px-3 py-2 text-sm text-white hover:bg-indigo-700 disabled:opacity-50"
                        :disabled="ocrLoading"
                        @click="captureAndScan"
                    >
                        {{ ocrLoading ? 'Escaneando...' : 'Capturar y escanear' }}
                    </button>
                </div>
            </div>

            <video
                v-show="cameraActive"
                ref="videoRef"
                autoplay
                playsinline
                muted
                class="mt-3 max-h-48 w-full rounded-lg bg-black object-cover"
            />

            <p v-if="ocrMessage" class="mt-2 text-sm" :class="form.document_ocr_verified ? 'text-emerald-700' : 'text-slate-600'">
                {{ ocrMessage }}
            </p>
        </div>

        <div>
            <InputLabel for="document_images" value="Subir documentación *" />
            <p class="mt-1 text-xs text-slate-500">{{ uploadHint }}</p>
            <input
                id="document_images"
                type="file"
                multiple
                accept="image/*,.pdf"
                class="mt-2 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"
                @change="handleFiles"
            />
            <InputError :message="errors.document_images" class="mt-1" />

            <div v-if="previews.length" class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                <div
                    v-for="(preview, index) in previews"
                    :key="index"
                    class="relative overflow-hidden rounded-lg border border-slate-200 bg-white"
                >
                    <img v-if="preview.url" :src="preview.url" :alt="preview.name" class="h-24 w-full object-cover" />
                    <div v-else class="flex h-24 items-center justify-center bg-slate-100 text-xs text-slate-600">PDF</div>
                    <button
                        type="button"
                        class="absolute right-1 top-1 rounded bg-red-600 px-1.5 py-0.5 text-xs text-white"
                        @click="removeFile(index)"
                    >
                        ×
                    </button>
                    <p class="truncate px-2 py-1 text-xs text-slate-600">{{ preview.name }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
