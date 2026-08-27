<script setup>
import { usePushNotifications } from '@/composables/usePushNotifications';
import { ref } from 'vue';

const {
    supported,
    serverEnabled,
    enabled,
    permission,
    busy,
    error,
    needsIosInstall,
    insecureContext,
    isIos,
    enable,
    disable,
    sendTest,
} = usePushNotifications();

const showIosHelp = ref(false);
const testSent = ref(false);

const toggle = async () => {
    if (enabled.value) {
        await disable();
        return;
    }

    const ok = await enable();

    if (ok) {
        testSent.value = await sendTest();
        setTimeout(() => (testSent.value = false), 5000);
    }
};
</script>

<template>
    <div
        v-if="insecureContext"
        class="border-b border-gray-100 bg-amber-50 px-4 py-3 text-xs text-amber-800"
    >
        Los avisos con la app cerrada necesitan una conexión segura (HTTPS).
        Abre FichaTime por su dirección https para poder activarlos.
    </div>

    <div v-else-if="supported && serverEnabled" class="border-b border-gray-100 bg-slate-50/70 px-4 py-3">
        <div v-if="needsIosInstall">
            <div class="flex items-start gap-2">
                <span class="mt-0.5 text-base leading-none">📲</span>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-medium text-slate-800">
                        Recibe avisos con la app cerrada
                    </p>
                    <p class="mt-0.5 text-xs text-slate-500">
                        En iPhone hay que añadir FichaTime a la pantalla de inicio.
                    </p>
                    <button
                        type="button"
                        class="mt-1 text-xs font-medium text-indigo-600 hover:text-indigo-800"
                        @click="showIosHelp = !showIosHelp"
                    >
                        {{ showIosHelp ? 'Ocultar pasos' : 'Ver cómo' }}
                    </button>

                    <ol v-if="showIosHelp" class="mt-2 space-y-1 text-xs text-slate-600">
                        <li>1. Pulsa el botón <span class="font-medium">Compartir</span> de Safari.</li>
                        <li>2. Elige <span class="font-medium">Añadir a pantalla de inicio</span>.</li>
                        <li>3. Abre FichaTime desde su nuevo icono.</li>
                        <li>4. Vuelve aquí y activa los avisos.</li>
                    </ol>
                </div>
            </div>
        </div>

        <div v-else class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xs font-medium text-slate-800">
                    Avisos en este dispositivo
                </p>
                <p class="mt-0.5 text-xs text-slate-500">
                    <span v-if="enabled" class="text-emerald-600">Activados</span>
                    <span v-else-if="permission === 'denied'" class="text-red-600">
                        Bloqueados en el navegador
                    </span>
                    <span v-else>Llegan aunque cierres la aplicación</span>
                </p>
            </div>

            <button
                type="button"
                class="shrink-0 rounded-full px-3 py-1.5 text-xs font-semibold transition disabled:opacity-50"
                :class="
                    enabled
                        ? 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-100'
                        : 'bg-indigo-600 text-white hover:bg-indigo-700'
                "
                :disabled="busy || permission === 'denied'"
                @click="toggle"
            >
                <span v-if="busy">...</span>
                <span v-else-if="enabled">Desactivar</span>
                <span v-else>Activar</span>
            </button>
        </div>

        <p v-if="testSent" class="mt-2 text-xs text-emerald-600">
            Te hemos enviado una notificación de prueba.
        </p>
        <p v-if="error" class="mt-2 text-xs text-red-600">{{ error }}</p>
        <p v-if="isIos && enabled" class="mt-2 text-xs text-slate-400">
            Si dejas de recibirlos, comprueba que no has borrado el icono de la pantalla de inicio.
        </p>
    </div>
</template>
