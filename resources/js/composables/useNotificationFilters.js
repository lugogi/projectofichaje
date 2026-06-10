import { computed, ref } from 'vue';

const CATEGORY_OPTIONS = [
    { value: 'all', label: 'Todos los tipos' },
    { value: 'fichaje', label: 'Fichaje' },
    { value: 'ausencia', label: 'Ausencias' },
    { value: 'correccion', label: 'Correcciones' },
];

const READ_OPTIONS = [
    { value: 'all', label: 'Todas' },
    { value: 'unread', label: 'Sin leer' },
    { value: 'read', label: 'Leídas' },
];

const ACTION_OPTIONS = [
    { value: 'all', label: 'Cualquier estado' },
    { value: 'pending_action', label: 'Requieren acción' },
    { value: 'pending_review', label: 'En revisión' },
    { value: 'approved', label: 'Aprobadas' },
    { value: 'rejected', label: 'Rechazadas' },
    { value: 'info', label: 'Informativas' },
];

const SORT_OPTIONS = [
    { value: 'newest', label: 'Más recientes' },
    { value: 'oldest', label: 'Más antiguas' },
];

const SECTION_ORDER = [
    'pending_action',
    'pending_review',
    'approved',
    'rejected',
    'info',
];

const SECTION_LABELS = {
    pending_action: 'Requieren tu acción',
    pending_review: 'Pendientes de revisión',
    approved: 'Aprobadas / atendidas',
    rejected: 'Rechazadas',
    info: 'Informativas',
};

const OUTCOME_STYLES = {
    pending_action: {
        badge: 'bg-amber-100 text-amber-800 ring-amber-200',
        border: 'border-s-amber-400',
        dot: 'bg-amber-500',
        iconBg: 'bg-amber-100 text-amber-700',
    },
    pending_review: {
        badge: 'bg-sky-100 text-sky-800 ring-sky-200',
        border: 'border-s-sky-400',
        dot: 'bg-sky-500',
        iconBg: 'bg-sky-100 text-sky-700',
    },
    approved: {
        badge: 'bg-emerald-100 text-emerald-800 ring-emerald-200',
        border: 'border-s-emerald-400',
        dot: 'bg-emerald-500',
        iconBg: 'bg-emerald-100 text-emerald-700',
    },
    rejected: {
        badge: 'bg-rose-100 text-rose-800 ring-rose-200',
        border: 'border-s-rose-400',
        dot: 'bg-rose-500',
        iconBg: 'bg-rose-100 text-rose-700',
    },
    info: {
        badge: 'bg-slate-100 text-slate-700 ring-slate-200',
        border: 'border-s-slate-300',
        dot: 'bg-slate-400',
        iconBg: 'bg-indigo-100 text-indigo-700',
    },
};

const CATEGORY_STYLES = {
    fichaje: 'bg-violet-100 text-violet-800',
    ausencia: 'bg-teal-100 text-teal-800',
    correccion: 'bg-orange-100 text-orange-800',
    general: 'bg-gray-100 text-gray-700',
};

function matchesFilters(notification, filters) {
    if (filters.read !== 'all') {
        const isUnread = !notification.read;
        if (filters.read === 'unread' && !isUnread) return false;
        if (filters.read === 'read' && isUnread) return false;
    }

    if (filters.category !== 'all' && notification.category !== filters.category) {
        return false;
    }

    if (
        filters.action !== 'all'
        && (notification.action_outcome ?? 'info') !== filters.action
    ) {
        return false;
    }

    return true;
}

function sortNotifications(list, sort) {
    const sorted = [...list];

    sorted.sort((a, b) => {
        const aTime = new Date(a.created_at_iso ?? a.created_at).getTime();
        const bTime = new Date(b.created_at_iso ?? b.created_at).getTime();

        return sort === 'oldest' ? aTime - bTime : bTime - aTime;
    });

    return sorted;
}

export function useNotificationFilters(items) {
    const readFilter = ref('all');
    const categoryFilter = ref('all');
    const actionFilter = ref('all');
    const sortOrder = ref('newest');
    const groupBySection = ref(true);

    const filters = computed(() => ({
        read: readFilter.value,
        category: categoryFilter.value,
        action: actionFilter.value,
        sort: sortOrder.value,
    }));

    const filteredItems = computed(() => {
        const list = items.value.filter((notification) =>
            matchesFilters(notification, filters.value),
        );

        return sortNotifications(list, sortOrder.value);
    });

    const counts = computed(() => ({
        all: items.value.length,
        unread: items.value.filter((n) => !n.read).length,
        read: items.value.filter((n) => n.read).length,
        pending_action: items.value.filter((n) => n.action_outcome === 'pending_action').length,
    }));

    const hasActiveFilters = computed(
        () =>
            readFilter.value !== 'all'
            || categoryFilter.value !== 'all'
            || actionFilter.value !== 'all'
            || sortOrder.value !== 'newest'
            || !groupBySection.value,
    );

    const resetFilters = () => {
        readFilter.value = 'all';
        categoryFilter.value = 'all';
        actionFilter.value = 'all';
        sortOrder.value = 'newest';
        groupBySection.value = true;
    };

    const groupedSections = computed(() => {
        if (!groupBySection.value || actionFilter.value !== 'all') {
            return [{ key: 'flat', label: null, items: filteredItems.value }];
        }

        const buckets = Object.fromEntries(
            SECTION_ORDER.map((key) => [key, []]),
        );

        for (const notification of filteredItems.value) {
            const key = notification.action_outcome ?? 'info';
            if (buckets[key]) {
                buckets[key].push(notification);
            }
        }

        return SECTION_ORDER
            .filter((key) => buckets[key].length > 0)
            .map((key) => ({
                key,
                label: SECTION_LABELS[key],
                items: buckets[key],
            }));
    });

    return {
        readFilter,
        categoryFilter,
        actionFilter,
        sortOrder,
        groupBySection,
        filteredItems,
        groupedSections,
        counts,
        hasActiveFilters,
        resetFilters,
        CATEGORY_OPTIONS,
        READ_OPTIONS,
        ACTION_OPTIONS,
        SORT_OPTIONS,
        OUTCOME_STYLES,
        CATEGORY_STYLES,
    };
}
