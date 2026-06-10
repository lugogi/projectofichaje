<script setup>
import NotificationsPanel from '@/Components/NotificationsPanel.vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const page = usePage();
const open = ref(false);
const loading = ref(false);
const items = ref([]);
const unreadCount = ref(page.props.notifications?.unread_count ?? 0);
const isMobile = ref(false);

const checkMobile = () => {
    isMobile.value = window.matchMedia('(max-width: 639px)').matches;
};

const fetchNotifications = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/notifications');
        items.value = data.items;
        unreadCount.value = data.unread_count;
    } catch (error) {
        console.error('Error al cargar notificaciones:', error);
    } finally {
        loading.value = false;
    }
};

const togglePanel = async () => {
    open.value = !open.value;
    if (open.value) {
        await fetchNotifications();
    }
};

const closePanel = () => {
    open.value = false;
};

const markAsRead = async (notification) => {
    if (notification.read) return;

    try {
        const { data } = await axios.patch(`/api/notifications/${notification.id}/read`);
        notification.read = true;
        unreadCount.value = data.unread_count;
    } catch (error) {
        console.error('Error al marcar notificación:', error);
    }
};

const markAllAsRead = async () => {
    try {
        await axios.post('/api/notifications/read-all');
        items.value = items.value.map((n) => ({ ...n, read: true }));
        unreadCount.value = 0;
    } catch (error) {
        console.error('Error al marcar todas:', error);
    }
};

const onNotificationClick = async (notification) => {
    await markAsRead(notification);
    if (notification.target_url) {
        closePanel();
    }
};

const badgeLabel = computed(() => {
    if (unreadCount.value <= 0) return null;
    return unreadCount.value > 9 ? '9+' : String(unreadCount.value);
});

const closeOnEscape = (e) => {
    if (open.value && e.key === 'Escape') closePanel();
};

let echoChannelName = null;

const handleRealtimeNotification = (payload) => {
    if (typeof payload.unread_count === 'number') {
        unreadCount.value = payload.unread_count;
    } else {
        unreadCount.value++;
    }

    if (!open.value) {
        return;
    }

    if (items.value.some((n) => n.id === payload.id)) {
        return;
    }

    items.value.unshift({
        id: payload.id,
        title: payload.title,
        message: payload.message,
        event_type: payload.event_type,
        target_url: payload.target_url,
        read: payload.read ?? false,
        created_at: payload.created_at,
        created_at_relative: payload.created_at_relative,
        created_at_iso: payload.created_at_iso,
        category: payload.category,
        category_label: payload.category_label,
        action_outcome: payload.action_outcome,
        action_outcome_label: payload.action_outcome_label,
    });
};

onMounted(() => {
    checkMobile();
    window.addEventListener('resize', checkMobile);
    document.addEventListener('keydown', closeOnEscape);

    const userId = page.props.auth?.user?.id;
    if (page.props.realtime?.enabled && window.Echo && userId) {
        echoChannelName = `App.Models.Employee.${userId}`;
        window.Echo.private(echoChannelName).listen(
            '.notification.created',
            handleRealtimeNotification,
        );
    }
});

onUnmounted(() => {
    window.removeEventListener('resize', checkMobile);
    document.removeEventListener('keydown', closeOnEscape);

    if (echoChannelName && window.Echo) {
        window.Echo.leave(`private-${echoChannelName}`);
    }
});

watch(
    () => page.props.notifications?.unread_count,
    (count) => {
        if (count !== undefined) unreadCount.value = count;
    },
);
</script>

<template>
    <div class="relative">
        <button
            type="button"
            class="relative inline-flex items-center justify-center rounded-full p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1"
            :aria-expanded="open"
            aria-label="Notificaciones"
            @click="togglePanel"
        >
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                />
            </svg>
            <span
                v-if="badgeLabel"
                class="absolute -end-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white"
            >
                {{ badgeLabel }}
            </span>
        </button>

        <div v-show="open" class="fixed inset-0 z-40 bg-black/40" @click="closePanel" />

        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="open && !isMobile"
                class="absolute end-0 z-50 mt-2 w-[min(100vw-2rem,30rem)] origin-top-right overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-black/5"
            >
                <NotificationsPanel
                    :items="items"
                    :loading="loading"
                    :unread-count="unreadCount"
                    @mark-all="markAllAsRead"
                    @select="onNotificationClick"
                />
            </div>
        </Transition>

        <Transition
            enter-active-class="transition ease-out duration-300"
            enter-from-class="translate-y-full"
            enter-to-class="translate-y-0"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="translate-y-0"
            leave-to-class="translate-y-full"
        >
            <div
                v-if="open && isMobile"
                class="fixed inset-x-0 bottom-0 z-50 flex max-h-[92vh] flex-col rounded-t-2xl bg-white shadow-2xl"
            >
                <div class="flex shrink-0 items-center justify-center py-2">
                    <span class="h-1 w-10 rounded-full bg-gray-300" />
                </div>
                <NotificationsPanel
                    mobile
                    class="min-h-0 flex-1"
                    :items="items"
                    :loading="loading"
                    :unread-count="unreadCount"
                    @mark-all="markAllAsRead"
                    @select="onNotificationClick"
                />
            </div>
        </Transition>
    </div>
</template>
