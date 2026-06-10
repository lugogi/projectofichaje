<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DayDetailModal from './DayDetailModal.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';

const viewDate = ref(new Date());
const clockedDatesSet = ref(new Set());
const missedDatesSet = ref(new Set());
const workDaysSet = ref(new Set());
const holidaysMap = ref(new Map());
const absencesMap = ref(new Map());
const teamAbsences = ref([]);
const serverToday = ref(null);
const loading = ref(false);
const isMobile = ref(false);

const isModalOpen = ref(false);
const selectedDateStr = ref('');
const dayDetails = ref([]);
const dayAbsence = ref(null);

let visibilityHandler = null;

onMounted(() => {
    isMobile.value = window.matchMedia('(max-width: 639px)').matches;
    const mediaQuery = window.matchMedia('(max-width: 639px)');
    mediaQuery.addEventListener('change', (e) => {
        isMobile.value = e.matches;
    });

    visibilityHandler = () => {
        if (!document.hidden) {
            fetchEvents();
        }
    };
    document.addEventListener('visibilitychange', visibilityHandler);
});

onUnmounted(() => {
    if (visibilityHandler) {
        document.removeEventListener('visibilitychange', visibilityHandler);
    }
});

const changeMonth = (offset) => {
    const newDate = new Date(
        viewDate.value.getFullYear(),
        viewDate.value.getMonth() + offset,
        1,
    );
    viewDate.value = newDate;
};

const fetchEvents = async () => {
    loading.value = true;
    try {
        const month = viewDate.value.getMonth() + 1;
        const year = viewDate.value.getFullYear();
        const response = await axios.get(
            `/api/calendar-events?month=${month}&year=${year}`,
        );

        clockedDatesSet.value = new Set(
            response.data.clocked_dates
                ?? (response.data.records || []).map((r) => r.date),
        );
        missedDatesSet.value = new Set(response.data.missed_days || []);
        workDaysSet.value = new Set(response.data.work_days || []);
        holidaysMap.value = new Map(
            (response.data.holidays || []).map((h) => [h.date, h.name]),
        );
        absencesMap.value = new Map(
            (response.data.absences || []).map((a) => [a.date, a]),
        );
        teamAbsences.value = response.data.team_absences || [];
        serverToday.value = response.data.today ?? todayStr();
    } catch (error) {
        console.error('Error fetching events:', error);
    } finally {
        loading.value = false;
    }
};

const fetchDayEvents = async (dateStr) => {
    try {
        const response = await axios.get(
            `/api/calendar-day-events?date=${dateStr}`,
        );
        dayDetails.value = response.data.records ?? response.data;
        dayAbsence.value = response.data.absence ?? null;
        selectedDateStr.value = dateStr;
        isModalOpen.value = true;
    } catch (error) {
        console.error('Error fetching day events:', error);
    }
};

watch(viewDate, () => fetchEvents(), { immediate: true });

const monthNameFixed = computed(() =>
    viewDate.value.toLocaleString('es-ES', { month: 'long', year: 'numeric' }),
);

const daysOfWeek = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

const calendarGrid = computed(() => {
    const date = new Date(viewDate.value);
    const year = date.getFullYear();
    const month = date.getMonth();
    const firstDayOfMonth = new Date(year, month, 1);
    const lastDayOfMonth = new Date(year, month + 1, 0);
    const grid = [];

    const startPadding = firstDayOfMonth.getDay();
    for (let i = startPadding; i > 0; i--) {
        const d = new Date(year, month, 1 - i);
        grid.push({
            day: d.getDate(),
            isCurrentMonth: false,
            dateStr: formatDateStr(d),
        });
    }

    for (let i = 1; i <= lastDayOfMonth.getDate(); i++) {
        const d = new Date(year, month, i);
        grid.push({
            day: i,
            isCurrentMonth: true,
            dateStr: formatDateStr(d),
        });
    }

    const remaining = 42 - grid.length;
    for (let i = 1; i <= remaining; i++) {
        const d = new Date(year, month, lastDayOfMonth.getDate() + i);
        grid.push({
            day: d.getDate(),
            isCurrentMonth: false,
            dateStr: formatDateStr(d),
        });
    }

    return grid;
});

const formatDateStr = (d) =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

const todayStr = () => serverToday.value ?? formatDateStr(new Date());

const isWorkDay = (dateStr) => workDaysSet.value.has(dateStr);
const hasClocked = (dateStr) => clockedDatesSet.value.has(dateStr);
const isMissed = (dateStr) => missedDatesSet.value.has(dateStr);
const isHoliday = (dateStr) => holidaysMap.value.has(dateStr);
const isAbsence = (dateStr) => absencesMap.value.has(dateStr);
const isToday = (dateStr) => dateStr === todayStr();

const getHolidayName = (dateStr) => holidaysMap.value.get(dateStr) || null;
const getAbsenceLabel = (dateStr) => absencesMap.value.get(dateStr)?.label || null;

const absenceBadgeClass = (dateStr) => {
    const type = absencesMap.value.get(dateStr)?.type;
    if (type === 'vacation') return 'bg-indigo-100 text-indigo-800';
    if (type === 'medical_leave') return 'bg-rose-100 text-rose-800';
    return 'bg-sky-100 text-sky-800';
};

/**
 * clocked | missed | pending-today | work-future | off
 */
const attendanceStatus = (dateStr) => {
    if (isAbsence(dateStr) || isHoliday(dateStr) || !isWorkDay(dateStr)) {
        return 'off';
    }
    if (hasClocked(dateStr)) return 'clocked';
    if (isMissed(dateStr) || dateStr < todayStr()) return 'missed';
    if (isToday(dateStr)) return 'pending-today';
    return 'work-future';
};

const dayCellClass = (date) => {
    const dateStr = date.dateStr;
    const status = attendanceStatus(dateStr);
    const classes = [
        'min-h-[100px] p-2 border rounded-lg transition-all duration-200 cursor-pointer hover:border-blue-400',
    ];

    if (!date.isCurrentMonth) {
        classes.push('bg-gray-100 text-gray-400 opacity-50 border-gray-200');
        return classes;
    }

    if (isToday(dateStr)) {
        classes.push('ring-2 ring-blue-500/60');
    }

    switch (status) {
        case 'clocked':
            classes.push('border-emerald-500 bg-emerald-50 shadow-sm');
            break;
        case 'missed':
            classes.push('border-red-500 bg-red-50 shadow-sm');
            break;
        case 'pending-today':
            classes.push('border-amber-400 bg-amber-50');
            break;
        case 'work-future':
            classes.push('border-emerald-200 bg-white');
            break;
        default:
            classes.push('border-slate-200 bg-white');
    }

    return classes;
};

const attendanceBadge = (dateStr) => {
    const status = attendanceStatus(dateStr);
    const map = {
        clocked: { text: 'Fichado', class: 'bg-emerald-600 text-white' },
        missed: { text: 'Sin fichar', class: 'bg-red-600 text-white' },
        'pending-today': { text: 'Hoy · pendiente', class: 'bg-amber-500 text-white' },
        'work-future': { text: 'Laborable', class: 'bg-emerald-100 text-emerald-800' },
    };
    return map[status] ?? null;
};

const handleDayClick = async (dateStr) => {
    await fetchDayEvents(dateStr);
};
</script>

<template>
    <Head title="Calendario" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold capitalize leading-tight text-gray-800">
                    {{ monthNameFixed }}
                </h2>
                <div class="flex space-x-2">
                    <button
                        type="button"
                        class="rounded-full bg-white p-2 shadow transition hover:bg-gray-50"
                        @click="changeMonth(-1)"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        class="rounded-full bg-white p-2 shadow transition hover:bg-gray-50"
                        @click="changeMonth(1)"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white p-4 shadow-sm sm:rounded-lg md:p-8">
                    <div class="mb-4 flex flex-wrap gap-3 text-xs text-slate-600">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 ring-1 ring-emerald-300">
                            <span class="h-2 w-2 rounded-full bg-emerald-600"></span>
                            Fichado
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-2.5 py-1 ring-1 ring-red-300">
                            <span class="h-2 w-2 rounded-full bg-red-600"></span>
                            Sin fichar (día pasado)
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 ring-1 ring-amber-300">
                            <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                            Hoy, aún sin fichar
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-2.5 py-1 ring-1 ring-indigo-200">
                            Ausencia aprobada
                        </span>
                    </div>

                    <div class="mb-4 grid grid-cols-7 gap-1">
                        <div
                            v-for="day in daysOfWeek"
                            :key="day"
                            class="py-2 text-center text-xs font-bold uppercase text-gray-500"
                        >
                            {{ day }}
                        </div>
                    </div>

                    <div
                        v-if="loading"
                        class="py-8 text-center text-sm text-slate-500"
                    >
                        Cargando calendario…
                    </div>

                    <div
                        v-else
                        class="grid grid-cols-7 gap-2"
                    >
                        <div
                            v-for="(date, index) in calendarGrid"
                            :key="index"
                            :class="dayCellClass(date)"
                            @click="handleDayClick(date.dateStr)"
                        >
                            <div
                                :class="isMobile ? 'flex flex-col items-center' : ''"
                            >
                                <span
                                    class="text-sm font-semibold"
                                    :class="{ 'text-blue-600': date.isCurrentMonth }"
                                >
                                    {{ date.day }}
                                </span>

                                <div
                                    class="mt-1 flex w-full flex-col items-start gap-1"
                                >
                                    <div
                                        v-if="isHoliday(date.dateStr)"
                                        class="w-full truncate rounded bg-yellow-100 px-1 py-0.5 text-left text-[9px] font-semibold text-yellow-800"
                                    >
                                        {{ getHolidayName(date.dateStr) }}
                                    </div>

                                    <div
                                        v-if="isAbsence(date.dateStr)"
                                        class="w-full truncate rounded px-1 py-0.5 text-left text-[9px] font-semibold"
                                        :class="absenceBadgeClass(date.dateStr)"
                                    >
                                        {{ getAbsenceLabel(date.dateStr) }}
                                    </div>

                                    <div
                                        v-if="attendanceBadge(date.dateStr)"
                                        class="w-full truncate rounded px-1.5 py-0.5 text-left text-[9px] font-bold"
                                        :class="attendanceBadge(date.dateStr).class"
                                    >
                                        {{ attendanceBadge(date.dateStr).text }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="teamAbsences.length > 0"
                    class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">
                        Ausencias del equipo este mes
                    </h3>
                    <ul class="mt-3 divide-y divide-slate-100">
                        <li
                            v-for="item in teamAbsences"
                            :key="`${item.date}-${item.employee_id}`"
                            class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm"
                        >
                            <span class="font-medium text-slate-800">
                                {{ item.employee_name }}
                            </span>
                            <span class="text-slate-500">
                                {{ item.date }} · {{ item.label }}
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <DayDetailModal
            :is-open="isModalOpen"
            :events="dayDetails"
            :date="selectedDateStr"
            :absence="dayAbsence"
            @close="isModalOpen = false"
            @refresh="fetchDayEvents(selectedDateStr); fetchEvents()"
        />
    </AuthenticatedLayout>
</template>
