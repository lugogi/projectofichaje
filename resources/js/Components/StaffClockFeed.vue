<script setup>
import { usePage } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';

const page = usePage();
const entries = ref([]);
const live = ref(false);

let pollInterval = null;

const fetchRecentClocks = async () => {
    try {
        const response = await fetch('/api/recent-clocks');
        if (response.ok) {
            const data = await response.json();
            entries.value = data;
            live.value = true;
        }
    } catch (error) {
        console.error('Error fetching recent clocks:', error);
    }
};

onMounted(() => {
    const user = page.props.auth?.user;
    if (!user) {
        return;
    }

    if (user.role !== 'admin' && user.role !== 'manager') {
        return;
    }

    // Fetch immediately on mount
    fetchRecentClocks();

    // Poll every 2 seconds
    pollInterval = setInterval(fetchRecentClocks, 2000);
});

onUnmounted(() => {
    if (pollInterval) {
        clearInterval(pollInterval);
    }
});
</script>

<template>
    <div
        v-if="live"
        class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
    >
        <div class="flex items-center justify-between gap-3">
            <h3 class="text-sm font-semibold text-slate-900">Fichajes en vivo</h3>
            <span
                class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700"
            >
                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500" />
                Conectado
            </span>
        </div>

        <p v-if="entries.length === 0" class="mt-3 text-sm text-slate-500">
            Esperando fichajes del equipo…
        </p>

        <ul v-else class="mt-3 divide-y divide-slate-100">
            <li
                v-for="(entry, index) in entries"
                :key="`${entry.employee_id}-${entry.recorded_at_full}-${index}`"
                class="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0"
            >
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-slate-900">
                        {{ entry.employee_name }}
                        <span class="font-normal text-slate-500">
                            ({{ entry.employee_code }})
                        </span>
                    </p>
                    <p v-if="entry.zona" class="text-xs text-slate-500">
                        {{ entry.zona }}
                    </p>
                </div>
                <div class="shrink-0 text-end">
                    <span
                        class="inline-block rounded-full px-2 py-0.5 text-xs font-semibold"
                        :class="
                            entry.type === 1
                                ? 'bg-emerald-100 text-emerald-800'
                                : 'bg-slate-100 text-slate-700'
                        "
                    >
                        {{ entry.type_label }}
                    </span>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ entry.recorded_at }}
                    </p>
                </div>
            </li>
        </ul>
    </div>
</template>
