<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DayDetailModal from './DayDetailModal.vue';
import TeamDayDrawer from './TeamDayDrawer.vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    esEquipo: { type: Boolean, default: false },
    esAdmin: { type: Boolean, default: false },
});

const viewDate = ref(new Date());
const clockedDatesSet = ref(new Set());
const missedDatesSet = ref(new Set());
const workDaysSet = ref(new Set());
const holidaysMap = ref(new Map());
const absencesMap = ref(new Map());
const teamAbsences = ref([]);
const teamDays = ref({});
const teamPlantilla = ref(0);
const serverToday = ref(null);
const loading = ref(false);
const isMobile = ref(false);
const vista = ref(props.esEquipo ? 'equipo' : 'yo');

const isModalOpen = ref(false);
const selectedDateStr = ref('');
const dayDetails = ref([]);
const dayAbsence = ref(null);
const teamDay = ref(null);
const teamDayOpen = ref(false);
const teamDayLoading = ref(false);

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
    viewDate.value = new Date(
        viewDate.value.getFullYear(),
        viewDate.value.getMonth() + offset,
        1,
    );
};

const irAHoy = () => {
    viewDate.value = new Date();
};

const fetchEvents = async () => {
    loading.value = true;
    try {
        const month = viewDate.value.getMonth() + 1;
        const year = viewDate.value.getFullYear();
        const response = await axios.get('/api/calendar-events', {
            params: { month, year },
        });

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
        teamDays.value = response.data.equipo?.dias ?? {};
        teamPlantilla.value = response.data.equipo?.plantilla ?? 0;
    } catch (error) {
        console.error('Error fetching events:', error);
    } finally {
        loading.value = false;
    }
};

const fetchDayEvents = async (dateStr) => {
    selectedDateStr.value = dateStr;

    if (vista.value === 'equipo' && props.esEquipo) {
        teamDayLoading.value = true;
        teamDayOpen.value = true;
        try {
            const response = await axios.get('/api/calendar-day-events', {
                params: { date: dateStr },
            });
            teamDay.value = response.data.equipo ?? null;
        } catch (error) {
            console.error('Error fetching team day:', error);
        } finally {
            teamDayLoading.value = false;
        }
        return;
    }

    try {
        const response = await axios.get('/api/calendar-day-events', {
            params: { date: dateStr },
        });
        dayDetails.value = response.data.records ?? [];
        dayAbsence.value = response.data.absence ?? null;
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
const esVistaEquipo = computed(() => vista.value === 'equipo' && props.esEquipo);

const getHolidayName = (dateStr) => holidaysMap.value.get(dateStr) || null;
const getAbsenceLabel = (dateStr) => absencesMap.value.get(dateStr)?.label || null;
const teamStats = (dateStr) => teamDays.value[dateStr] ?? null;

const absenceBadgeClass = (dateStr) => {
    const type = absencesMap.value.get(dateStr)?.type;
    if (type === 'vacation') return 'bg-indigo-100 text-indigo-800';
    if (type === 'medical_leave') return 'bg-rose-100 text-rose-800';
    return 'bg-sky-100 text-sky-800';
};

const attendanceStatus = (dateStr) => {
    if (isAbsence(dateStr) || isHoliday(dateStr) || !isWorkDay(dateStr)) {
        return 'off';
    }
    if (hasClocked(dateStr)) return 'clocked';
    if (isMissed(dateStr) || dateStr < todayStr()) return 'missed';
    if (isToday(dateStr)) return 'pending-today';
    return 'work-future';
};

const teamStatus = (dateStr) => {
    const d = teamStats(dateStr);
    if (!d || d.laborables === 0) return 'off';
    if (d.sin_fichar > 0) return 'alert';
    if (d.en_curso > 0) return 'live';
    if (d.fichados >= d.laborables) return 'ok';
    return 'work';
};

const dayCellClass = (date) => {
    const dateStr = date.dateStr;
    const classes = [
        'min-h-[108px] p-2 border rounded-xl transition-all duration-200 cursor-pointer hover:border-indigo-400 hover:shadow-sm',
    ];

    if (!date.isCurrentMonth) {
        classes.push('bg-slate-50 text-slate-300 opacity-60 border-slate-100');
        return classes;
    }

    if (isToday(dateStr)) {
        classes.push('ring-2 ring-indigo-500/50');
    }

    if (esVistaEquipo.value) {
        switch (teamStatus(dateStr)) {
            case 'ok':
                classes.push('border-emerald-400 bg-emerald-50');
                break;
            case 'alert':
                classes.push('border-red-400 bg-red-50');
                break;
            case 'live':
                classes.push('border-amber-400 bg-amber-50');
                break;
            case 'work':
                classes.push('border-slate-200 bg-white');
                break;
            default:
                classes.push('border-slate-200 bg-white');
        }
        return classes;
    }

    switch (attendanceStatus(dateStr)) {
        case 'clocked':
            classes.push('border-emerald-500 bg-emerald-50');
            break;
        case 'missed':
            classes.push('border-red-500 bg-red-50');
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

const ocupacion = (dateStr) => {
    const d = teamStats(dateStr);
    if (!d || d.laborables === 0) return 0;
    return Math.min(100, Math.round((d.fichados / d.laborables) * 100));
};

const hoyEquipo = computed(() => teamStats(todayStr()));

const handleDayClick = async (dateStr) => {
    await fetchDayEvents(dateStr);
};
</script>

<template>
    <Head title="Calendario" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold capitalize leading-tight text-gray-800">
                        {{ monthNameFixed }}
                    </h2>
                    <p class="mt-0.5 text-sm text-slate-500">
                        {{
                            esVistaEquipo
                                ? 'Pulsa un día para ver las fichadas de toda la plantilla.'
                                : 'Tu asistencia del mes. Pulsa un día para ver el detalle.'
                        }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <div
                        v-if="esEquipo"
                        class="mr-2 inline-flex rounded-full bg-slate-100 p-1 text-xs font-semibold"
                    >
                        <button
                            type="button"
                            class="rounded-full px-3 py-1"
                            :class="vista === 'equipo' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'"
                            @click="vista = 'equipo'"
                        >
                            Equipo
                        </button>
                        <button
                            type="button"
                            class="rounded-full px-3 py-1"
                            :class="vista === 'yo' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500'"
                            @click="vista = 'yo'"
                        >
                            Yo
                        </button>
                    </div>
                    <button
                        type="button"
                        class="rounded-full bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow hover:bg-slate-50"
                        @click="irAHoy"
                    >
                        Hoy
                    </button>
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

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div
                    v-if="esVistaEquipo && hoyEquipo"
                    class="mb-4 grid gap-3 sm:grid-cols-4"
                >
                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                        <p class="text-xs text-slate-500">Plantilla</p>
                        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ teamPlantilla }}</p>
                    </div>
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                        <p class="text-xs text-emerald-700">Fichados hoy</p>
                        <p class="mt-1 text-2xl font-semibold text-emerald-900">
                            {{ hoyEquipo.fichados }}/{{ hoyEquipo.laborables }}
                        </p>
                    </div>
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
                        <p class="text-xs text-amber-700">En curso</p>
                        <p class="mt-1 text-2xl font-semibold text-amber-900">{{ hoyEquipo.en_curso }}</p>
                    </div>
                    <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3">
                        <p class="text-xs text-red-700">Sin fichar hoy</p>
                        <p class="mt-1 text-2xl font-semibold text-red-900">{{ hoyEquipo.sin_fichar }}</p>
                    </div>
                </div>

                <div class="overflow-hidden bg-white p-4 shadow-sm sm:rounded-2xl md:p-8">
                    <div class="mb-4 flex flex-wrap gap-3 text-xs text-slate-600">
                        <template v-if="esVistaEquipo">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 ring-1 ring-emerald-300">
                                <span class="h-2 w-2 rounded-full bg-emerald-600"></span>
                                Plantilla completa
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-2.5 py-1 ring-1 ring-red-300">
                                <span class="h-2 w-2 rounded-full bg-red-600"></span>
                                Faltan fichajes
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 ring-1 ring-amber-300">
                                <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                                Alguien en curso
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-2.5 py-1 ring-1 ring-indigo-200">
                                Ausencias en el día
                            </span>
                        </template>
                        <template v-else>
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
                        </template>
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
                        <button
                            v-for="(date, index) in calendarGrid"
                            :key="index"
                            type="button"
                            :class="dayCellClass(date)"
                            class="text-left"
                            @click="handleDayClick(date.dateStr)"
                        >
                            <div :class="isMobile ? 'flex flex-col items-center' : ''">
                                <span
                                    class="text-sm font-semibold"
                                    :class="date.isCurrentMonth ? 'text-slate-800' : ''"
                                >
                                    {{ date.day }}
                                </span>

                                <div class="mt-1 flex w-full flex-col items-start gap-1">
                                    <div
                                        v-if="isHoliday(date.dateStr)"
                                        class="w-full truncate rounded bg-yellow-100 px-1 py-0.5 text-left text-[9px] font-semibold text-yellow-800"
                                    >
                                        {{ getHolidayName(date.dateStr) }}
                                    </div>

                                    <template v-if="esVistaEquipo && date.isCurrentMonth">
                                        <template v-if="teamStats(date.dateStr)?.laborables">
                                            <div class="w-full">
                                                <div class="mb-0.5 flex justify-between text-[10px] font-semibold text-slate-600">
                                                    <span>
                                                        {{ teamStats(date.dateStr).fichados }}/{{
                                                            teamStats(date.dateStr).laborables
                                                        }}
                                                    </span>
                                                    <span v-if="teamStats(date.dateStr).sin_fichar">
                                                        {{ teamStats(date.dateStr).sin_fichar }} sin
                                                    </span>
                                                </div>
                                                <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-200">
                                                    <div
                                                        class="h-full rounded-full"
                                                        :class="
                                                            teamStatus(date.dateStr) === 'alert'
                                                                ? 'bg-red-500'
                                                                : teamStatus(date.dateStr) === 'live'
                                                                  ? 'bg-amber-500'
                                                                  : 'bg-emerald-500'
                                                        "
                                                        :style="{ width: ocupacion(date.dateStr) + '%' }"
                                                    />
                                                </div>
                                            </div>
                                            <div class="flex flex-wrap gap-1">
                                                <span
                                                    v-if="teamStats(date.dateStr).en_curso"
                                                    class="rounded bg-amber-100 px-1 text-[9px] font-semibold text-amber-800"
                                                >
                                                    {{ teamStats(date.dateStr).en_curso }} en curso
                                                </span>
                                                <span
                                                    v-if="teamStats(date.dateStr).ausentes"
                                                    class="rounded bg-indigo-100 px-1 text-[9px] font-semibold text-indigo-800"
                                                >
                                                    {{ teamStats(date.dateStr).ausentes }} aus.
                                                </span>
                                            </div>
                                        </template>
                                    </template>

                                    <template v-else>
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
                                    </template>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>

                <div
                    v-if="!esVistaEquipo && teamAbsences.length > 0"
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

        <TeamDayDrawer
            :is-open="teamDayOpen"
            :loading="teamDayLoading"
            :equipo="teamDay"
            :es-admin="esAdmin"
            @close="teamDayOpen = false"
        />
    </AuthenticatedLayout>
</template>
