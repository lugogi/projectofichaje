import axios from 'axios';
import { computed, onMounted, ref } from 'vue';

/**
 * Convierte la clave VAPID (base64 url-safe) al formato que espera pushManager.
 */
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);
    return Uint8Array.from([...raw].map((char) => char.charCodeAt(0)));
}

export function isIos() {
    if (typeof navigator === 'undefined') return false;
    return (
        /iPad|iPhone|iPod/.test(navigator.userAgent) ||
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
    );
}

export function isStandalone() {
    if (typeof window === 'undefined') return false;
    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        window.navigator.standalone === true
    );
}

/**
 * navigator.serviceWorker.ready no se resuelve nunca si el registro falló,
 * así que lo limitamos en el tiempo para no dejar la interfaz colgada.
 */
async function readyRegistration(timeoutMs = 5000) {
    return Promise.race([
        navigator.serviceWorker.ready,
        new Promise((_, reject) =>
            setTimeout(() => reject(new Error('service worker no disponible')), timeoutMs),
        ),
    ]);
}

export function usePushNotifications() {
    const supported = ref(false);
    const enabled = ref(false);
    const permission = ref('default');
    const busy = ref(false);
    const error = ref(null);
    const publicKey = ref(null);
    const serverEnabled = ref(false);
    const insecureContext = ref(false);

    const onIos = isIos();
    const standalone = isStandalone();

    /**
     * En iPhone/iPad el navegador solo expone la API de push si la web
     * está instalada en la pantalla de inicio y se abre desde su icono.
     */
    const needsIosInstall = computed(() => onIos && !standalone);

    const canSubscribe = computed(
        () => supported.value && serverEnabled.value && !needsIosInstall.value,
    );

    const registration = ref(null);

    const detectSupport = async () => {
        if (typeof window === 'undefined') return;

        // Los navegadores solo exponen los service workers en contextos seguros
        // (https o localhost). Al abrir la app por IP de red local no existen.
        insecureContext.value = !window.isSecureContext;

        supported.value =
            'serviceWorker' in navigator &&
            'PushManager' in window &&
            'Notification' in window;

        if (!supported.value) return;

        permission.value = Notification.permission;

        try {
            const { data } = await axios.get('/api/push/config');
            serverEnabled.value = Boolean(data.enabled);
            publicKey.value = data.public_key;
        } catch {
            serverEnabled.value = false;
        }

        try {
            registration.value = await readyRegistration();
            const existing = await registration.value.pushManager.getSubscription();
            enabled.value = Boolean(existing);
        } catch {
            enabled.value = false;
        }
    };

    const enable = async () => {
        error.value = null;

        if (!canSubscribe.value) {
            error.value = needsIosInstall.value
                ? 'Instala la app en la pantalla de inicio para poder activar los avisos.'
                : 'Este navegador no admite notificaciones push.';
            return false;
        }

        busy.value = true;

        try {
            // El permiso debe pedirse dentro de un gesto del usuario (requisito de iOS).
            const result = await Notification.requestPermission();
            permission.value = result;

            if (result !== 'granted') {
                error.value =
                    result === 'denied'
                        ? 'Has bloqueado las notificaciones. Actívalas desde los ajustes del navegador.'
                        : 'No se concedió el permiso de notificaciones.';
                return false;
            }

            const reg = registration.value || (await readyRegistration());
            registration.value = reg;

            let subscription = await reg.pushManager.getSubscription();

            if (!subscription) {
                subscription = await reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(publicKey.value),
                });
            }

            await axios.post('/api/push/subscribe', subscription.toJSON());

            enabled.value = true;
            return true;
        } catch (e) {
            error.value = e.response?.data?.message || 'No se pudieron activar las notificaciones.';
            return false;
        } finally {
            busy.value = false;
        }
    };

    const disable = async () => {
        error.value = null;
        busy.value = true;

        try {
            const reg = registration.value || (await readyRegistration());
            const subscription = await reg.pushManager.getSubscription();

            if (subscription) {
                await axios.post('/api/push/unsubscribe', { endpoint: subscription.endpoint });
                await subscription.unsubscribe();
            }

            enabled.value = false;
            return true;
        } catch {
            error.value = 'No se pudieron desactivar las notificaciones.';
            return false;
        } finally {
            busy.value = false;
        }
    };

    const sendTest = async () => {
        error.value = null;
        busy.value = true;

        try {
            await axios.post('/api/push/test');
            return true;
        } catch {
            error.value = 'No se pudo enviar la notificación de prueba.';
            return false;
        } finally {
            busy.value = false;
        }
    };

    onMounted(detectSupport);

    return {
        supported,
        serverEnabled,
        enabled,
        permission,
        busy,
        error,
        canSubscribe,
        needsIosInstall,
        insecureContext,
        isIos: onIos,
        isStandalone: standalone,
        enable,
        disable,
        sendTest,
        refresh: detectSupport,
    };
}
