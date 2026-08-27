<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import axios from 'axios';
import { ref, watch } from 'vue';

const props = defineProps({
    form: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
});

const otpSending = ref(false);
const otpVerifying = ref(false);
const otpCode = ref('');
const otpMessage = ref('');
const otpDebugCode = ref(null);
const postalLoading = ref(false);

const sendOtp = async () => {
    otpMessage.value = '';
    otpDebugCode.value = null;
    otpSending.value = true;
    try {
        const { data } = await axios.post(route('employee-application.otp.send'), { phone: props.form.phone });
        otpMessage.value = data.message;
        otpDebugCode.value = data.debug_code;
    } catch (e) {
        otpMessage.value = e.response?.data?.message || 'No se pudo enviar el código.';
    } finally {
        otpSending.value = false;
    }
};

const verifyOtp = async () => {
    otpMessage.value = '';
    otpVerifying.value = true;
    try {
        const { data } = await axios.post(route('employee-application.otp.verify'), {
            phone: props.form.phone,
            code: otpCode.value,
        });
        props.form.phone_verified = data.ok;
        otpMessage.value = data.message;
    } catch (e) {
        props.form.phone_verified = false;
        otpMessage.value = e.response?.data?.message || 'Código incorrecto.';
    } finally {
        otpVerifying.value = false;
    }
};

const lookupPostalCode = async (cp) => {
    if (!/^\d{5}$/.test(cp)) return;
    postalLoading.value = true;
    try {
        const res = await fetch(`https://api.zippopotam.us/es/${cp}`);
        if (!res.ok) return;
        const data = await res.json();
        const place = data.places?.[0];
        if (place) {
            props.form.city = place['place name'] || props.form.city;
            props.form.province = place['state'] || props.form.province;
        }
    } catch {
        // API externa opcional; el usuario puede rellenar manualmente
    } finally {
        postalLoading.value = false;
    }
};

watch(
    () => props.form.postal_code,
    (cp) => {
        if (/^\d{5}$/.test(cp || '')) lookupPostalCode(cp);
    },
);

watch(
    () => props.form.phone,
    () => {
        props.form.phone_verified = false;
        otpCode.value = '';
    },
);
</script>

<template>
    <div class="space-y-4">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Contacto y residencia</h2>
            <p class="mt-1 text-sm text-slate-500">Dirección completa y datos de contacto verificados.</p>
        </div>

        <div>
            <InputLabel for="street" value="Dirección (calle, número, piso) *" />
            <TextInput id="street" v-model="form.street" class="mt-1 block w-full" autocomplete="street-address" />
            <InputError :message="errors.street" class="mt-1" />
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <InputLabel for="postal_code" value="Código postal *" />
                <TextInput id="postal_code" v-model="form.postal_code" maxlength="5" class="mt-1 block w-full" inputmode="numeric" />
                <p v-if="postalLoading" class="mt-1 text-xs text-indigo-600">Buscando municipio...</p>
                <InputError :message="errors.postal_code" class="mt-1" />
            </div>
            <div>
                <InputLabel for="city" value="Municipio *" />
                <TextInput id="city" v-model="form.city" class="mt-1 block w-full" />
                <InputError :message="errors.city" class="mt-1" />
            </div>
            <div>
                <InputLabel for="province" value="Provincia *" />
                <TextInput id="province" v-model="form.province" class="mt-1 block w-full" />
                <InputError :message="errors.province" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <InputLabel for="phone" value="Teléfono móvil *" />
                <TextInput id="phone" v-model="form.phone" class="mt-1 block w-full" type="tel" inputmode="tel" autocomplete="tel" />
                <InputError :message="errors.phone" class="mt-1" />
            </div>
            <div>
                <InputLabel for="email" value="Correo electrónico *" />
                <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" autocomplete="email" />
                <InputError :message="errors.email" class="mt-1" />
            </div>
        </div>

        <div class="rounded-xl border border-indigo-100 bg-indigo-50/60 p-4">
            <p class="text-sm font-medium text-indigo-900">Verificación SMS</p>
            <p class="mt-1 text-xs text-indigo-700">Enviaremos un código de 6 dígitos para confirmar tu teléfono.</p>

            <div class="mt-3 flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                    :disabled="otpSending || !form.phone"
                    @click="sendOtp"
                >
                    {{ otpSending ? 'Enviando...' : 'Enviar código' }}
                </button>

                <input
                    v-model="otpCode"
                    type="text"
                    maxlength="6"
                    inputmode="numeric"
                    placeholder="Código 6 dígitos"
                    class="w-36 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />

                <button
                    type="button"
                    class="rounded-md border border-indigo-300 bg-white px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50 disabled:opacity-50"
                    :disabled="otpVerifying || otpCode.length !== 6"
                    @click="verifyOtp"
                >
                    {{ otpVerifying ? 'Verificando...' : 'Verificar' }}
                </button>
            </div>

            <p v-if="form.phone_verified" class="mt-2 text-sm font-medium text-emerald-700">✓ Teléfono verificado</p>
            <p v-if="otpMessage" class="mt-2 text-sm text-slate-600">{{ otpMessage }}</p>
            <p v-if="otpDebugCode" class="mt-1 text-xs text-amber-700">Modo desarrollo — código: {{ otpDebugCode }}</p>
        </div>
    </div>
</template>
