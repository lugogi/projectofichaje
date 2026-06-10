<script setup>
import { useNotificationFilters } from '@/Composables/useNotificationFilters';
import { Link } from '@inertiajs/vue3';
import { computed, toRef } from 'vue';

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
    unreadCount: {
        type: Number,
        default: 0,
    },
    mobile: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['mark-all', 'select']);

const itemsRef = toRef(props, 'items');

const {
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
} = useNotificationFilters(itemsRef);

const isEmpty = computed(
    () => !props.loading && props.items.length === 0,
);

const isFilteredEmpty = computed(
    () => !props.loading && props.items.length > 0 && filteredItems.value.length === 0,
);

const styleFor = (notification) =>
    OUTCOME_STYLES[notification.action_outcome ?? 'info'] ?? OUTCOME_STYLES.info;
</script>

<template>
    <div :class="mobile ? 'flex min-h-0 flex-1 flex-col' : ''">
        <div class="shrink-0 border-b border-gray-100 px-4 py-3">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-gray-900">Notificaciones</h3>
                    <p class="mt-0.5 text-xs text-gray-500">
                        <span v-if="unreadCount > 0" class="font-medium text-indigo-600">
                            {{ unreadCount }} sin leer
                        </span>
                        <span v-if="unreadCount > 0"> · </span>
                        {{ filteredItems.length }} de {{ items.length }} mostradas
                    </p>
                </div>
                <button
                    v-if="unreadCount > 0"
                    type="button"
                    class="shrink-0 text-xs font-medium text-indigo-600 hover:text-indigo-800"
                    @click="emit('mark-all')"
                >
                    Marcar todas
                </button>
            </div>

            <div class="mt-3 flex flex-wrap gap-1.5">
                <button
                    v-for="option in READ_OPTIONS"
                    :key="option.value"
                    type="button"
                    class="rounded-full px-2.5 py-1 text-xs font-medium transition"
                    :class="
                        readFilter === option.value
                            ? 'bg-indigo-600 text-white shadow-sm'
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                    "
                    @click="readFilter = option.value"
                >
                    {{ option.label }}
                    <span
                        v-if="option.value === 'unread' && counts.unread > 0"
                        class="ms-1 rounded-full bg-white/20 px-1.5"
                    >
                        {{ counts.unread }}
                    </span>
                </button>
            </div>

            <div
                class="mt-2.5 grid gap-2"
                :class="mobile ? 'grid-cols-1' : 'grid-cols-2'"
            >
                <label class="block">
                    <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-gray-400">
                        Tipo
                    </span>
                    <select
                        v-model="categoryFilter"
                        class="w-full rounded-lg border-gray-200 bg-gray-50 py-1.5 text-xs text-gray-800 focus:border-indigo-400 focus:ring-indigo-400"
                    >
                        <option
                            v-for="option in CATEGORY_OPTIONS"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-gray-400">
                        Estado / acción
                    </span>
                    <select
                        v-model="actionFilter"
                        class="w-full rounded-lg border-gray-200 bg-gray-50 py-1.5 text-xs text-gray-800 focus:border-indigo-400 focus:ring-indigo-400"
                    >
                        <option
                            v-for="option in ACTION_OPTIONS"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </label>

                <label class="block" :class="mobile ? '' : 'col-span-2'">
                    <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-gray-400">
                        Orden
                    </span>
                    <select
                        v-model="sortOrder"
                        class="w-full rounded-lg border-gray-200 bg-gray-50 py-1.5 text-xs text-gray-800 focus:border-indigo-400 focus:ring-indigo-400"
                    >
                        <option
                            v-for="option in SORT_OPTIONS"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </label>
            </div>

            <div class="mt-2 flex items-center justify-between gap-2">
                <label class="inline-flex cursor-pointer items-center gap-2 text-xs text-gray-600">
                    <input
                        v-model="groupBySection"
                        type="checkbox"
                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    Agrupar por estado
                </label>
                <button
                    v-if="hasActiveFilters"
                    type="button"
                    class="text-xs font-medium text-gray-500 hover:text-gray-800"
                    @click="resetFilters"
                >
                    Limpiar filtros
                </button>
            </div>
        </div>

        <p v-if="loading" class="px-4 py-8 text-center text-sm text-gray-500">
            Cargando…
        </p>

        <p v-else-if="isEmpty" class="px-4 py-10 text-center text-sm text-gray-500">
            No tienes notificaciones
        </p>

        <p v-else-if="isFilteredEmpty" class="px-4 py-10 text-center text-sm text-gray-500">
            Ninguna notificación coincide con los filtros.
            <button
                type="button"
                class="mt-2 block w-full text-xs font-medium text-indigo-600 hover:text-indigo-800"
                @click="resetFilters"
            >
                Ver todas
            </button>
        </p>

        <div
            v-else
            :class="mobile
                ? 'min-h-0 flex-1 overflow-y-auto overscroll-contain px-2 pb-8'
                : 'max-h-[min(70vh,26rem)] overflow-y-auto px-2'"
        >
            <template v-for="section in groupedSections" :key="section.key">
                <div
                    v-if="section.label"
                    class="sticky top-0 z-10 mb-1 mt-3 flex items-center gap-2 bg-white/95 px-2 py-1.5 backdrop-blur-sm first:mt-1"
                >
                    <span class="text-[11px] font-bold uppercase tracking-wide text-gray-500">
                        {{ section.label }}
                    </span>
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-600">
                        {{ section.items.length }}
                    </span>
                </div>

                <ul class="divide-y divide-gray-100 rounded-xl border border-gray-100 bg-white">
                    <li v-for="notification in section.items" :key="notification.id">
                        <component
                            :is="notification.target_url ? Link : 'button'"
                            :href="notification.target_url || undefined"
                            :type="notification.target_url ? undefined : 'button'"
                            class="block w-full border-s-4 px-3 py-3.5 text-left transition hover:bg-gray-50"
                            :class="[
                                styleFor(notification).border,
                                !notification.read ? 'bg-indigo-50/40' : 'bg-white',
                            ]"
                            @click="emit('select', notification)"
                        >
                            <div class="flex items-start gap-3">
                                <span
                                    class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                                    :class="styleFor(notification).iconBg"
                                >
                                    {{
                                        notification.category === 'fichaje'
                                            ? '⏱'
                                            : notification.category === 'ausencia'
                                              ? '📅'
                                              : notification.category === 'correccion'
                                                ? '✎'
                                                : '•'
                                    }}
                                </span>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span
                                            class="rounded-full px-2 py-0.5 text-[10px] font-semibold ring-1 ring-inset"
                                            :class="styleFor(notification).badge"
                                        >
                                            {{ notification.action_outcome_label ?? 'Informativa' }}
                                        </span>
                                        <span
                                            class="rounded-full px-2 py-0.5 text-[10px] font-medium"
                                            :class="CATEGORY_STYLES[notification.category] ?? CATEGORY_STYLES.general"
                                        >
                                            {{ notification.category_label ?? 'General' }}
                                        </span>
                                        <span
                                            v-if="!notification.read"
                                            class="inline-flex items-center gap-1 rounded-full bg-indigo-600 px-2 py-0.5 text-[10px] font-bold text-white"
                                        >
                                            <span
                                                class="h-1.5 w-1.5 rounded-full bg-white"
                                            />
                                            Nueva
                                        </span>
                                    </div>

                                    <p class="mt-2 text-sm font-semibold leading-snug text-gray-900">
                                        {{ notification.title }}
                                    </p>
                                    <p class="mt-1 text-sm leading-relaxed text-gray-600">
                                        {{ notification.message }}
                                    </p>
                                    <p class="mt-2 text-xs text-gray-400">
                                        {{ notification.created_at }}
                                        <span v-if="notification.created_at_relative">
                                            · {{ notification.created_at_relative }}
                                        </span>
                                    </p>
                                </div>

                                <span
                                    class="mt-2 h-2 w-2 shrink-0 rounded-full"
                                    :class="
                                        notification.read
                                            ? 'bg-transparent'
                                            : styleFor(notification).dot
                                    "
                                />
                            </div>
                        </component>
                    </li>
                </ul>
            </template>
        </div>
    </div>
</template>
