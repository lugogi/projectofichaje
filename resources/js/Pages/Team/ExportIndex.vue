<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    empleados: {
        type: Array,
        default: () => [],
    },
    filtros: {
        type: Object,
        default: () => ({ departments: [], positions: [] }),
    },
    mesActual: {
        type: String,
        required: true,
    },
    esAdmin: {
        type: Boolean,
        default: false,
    },
    homeRoute: {
        type: String,
        required: true,
    },
    exportTeamUrl: {
        type: String,
        required: true,
    },
    enviarLaboralUrl: {
        type: String,
        required: true,
    },
    laboralConfigurado: {
        type: Boolean,
        default: false,
    },
    laboralNombre: {
        type: String,
        default: 'Asesoría laboral',
    },
});

const tituloPanel = computed(() =>
    props.esAdmin ? 'Administración' : 'Encargado',
);

const empleadoId = ref(props.empleados[0]?.id ?? '');
const mes = ref(props.mesActual);
const mesEquipo = ref(props.mesActual);
const exportandoEquipo = ref(false);
const enviandoLaboral = ref(false);
const errorExport = ref(null);
const mensajeLaboral = ref(null);
const formatoActivo = ref(null);

// Filtros de la exportación conjunta
const alcance = ref('todos'); // todos | departamento | puesto | seleccion
const departamento = ref('');
const puesto = ref('');
const seleccionados = ref([]);

const empleadoSeleccionado = computed(() =>
    props.empleados.find((e) => e.id === empleadoId.value),
);

/** Trabajadores que entrarían en la exportación con los filtros actuales. */
const incluidos = computed(() => {
    if (alcance.value === 'departamento') {
        return departamento.value
            ? props.empleados.filter((e) => e.departamento === departamento.value)
            : [];
    }

    if (alcance.value === 'puesto') {
        return puesto.value ? props.empleados.filter((e) => e.puesto === puesto.value) : [];
    }

    if (alcance.value === 'seleccion') {
        return props.empleados.filter((e) => seleccionados.value.includes(e.id));
    }

    return props.empleados;
});

const ocupadoEquipo = computed(() => exportandoEquipo.value || enviandoLaboral.value);

const puedeExportarEquipo = computed(
    () => Boolean(mesEquipo.value) && incluidos.value.length > 0 && !ocupadoEquipo.value,
);

const alternarSeleccion = (id) => {
    const i = seleccionados.value.indexOf(id);
    if (i === -1) seleccionados.value.push(id);
    else seleccionados.value.splice(i, 1);
};

const seleccionarTodos = () => {
    seleccionados.value = props.empleados.map((e) => e.id);
};

const limpiarSeleccion = () => {
    seleccionados.value = [];
};

const puedeExportar = computed(() => Boolean(empleadoId.value && mes.value));

const urlExport = (formato) => {
    const params = new URLSearchParams({
        month: mes.value,
        employee_id: empleadoId.value,
    });
    return route('profile.export', { format: formato }) + '?' + params.toString();
};

const etiquetaRol = (rol) => {
    const mapa = {
        admin: 'Administración',
        manager: 'Encargado',
        employee: 'Empleado',
    };
    return mapa[rol] ?? rol;
};

const cabecerasFetch = () => ({
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-CSRF-TOKEN':
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
});

const payloadEquipo = (formato = null) => {
    const payload = { month: mesEquipo.value };
    if (formato) payload.format = formato;
    if (alcance.value === 'departamento') payload.department = departamento.value;
    if (alcance.value === 'puesto') payload.position = puesto.value;
    if (alcance.value === 'seleccion') payload.employee_ids = seleccionados.value;
    return payload;
};

const exportarEquipo = async (formato = 'excel') => {
    if (!puedeExportarEquipo.value) return;

    exportandoEquipo.value = true;
    formatoActivo.value = formato;
    errorExport.value = null;
    mensajeLaboral.value = null;

    try {
        const response = await fetch(props.exportTeamUrl, {
            method: 'POST',
            headers: cabecerasFetch(),
            body: JSON.stringify(payloadEquipo(formato)),
        });

        if (!response.ok) {
            errorExport.value =
                response.status === 422
                    ? 'No hay trabajadores que cumplan los filtros seleccionados.'
                    : 'No se pudo generar la exportación. Inténtalo de nuevo.';
            return;
        }

        const extension = formato === 'json' ? 'json' : formato === 'pdf' ? 'pdf' : 'xlsx';
        const nombre =
            response.headers
                .get('content-disposition')
                ?.match(/filename="?([^"]+)"?/)?.[1] ??
            `registros_${mesEquipo.value.replace('-', '_')}.${extension}`;

        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = nombre;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        a.remove();
    } catch (error) {
        console.error('Error:', error);
        errorExport.value = 'Error de conexión. Comprueba tu red e inténtalo de nuevo.';
    } finally {
        exportandoEquipo.value = false;
        formatoActivo.value = null;
    }
};

const enviarALaboral = async () => {
    if (!puedeExportarEquipo.value) return;

    enviandoLaboral.value = true;
    errorExport.value = null;
    mensajeLaboral.value = null;

    try {
        const response = await fetch(props.enviarLaboralUrl, {
            method: 'POST',
            headers: cabecerasFetch(),
            body: JSON.stringify(payloadEquipo()),
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            errorExport.value =
                data.message ||
                (response.status === 422
                    ? 'No hay trabajadores que cumplan los filtros seleccionados.'
                    : 'No se pudo enviar el informe. Inténtalo de nuevo.');
            return;
        }

        mensajeLaboral.value = {
            ok: Boolean(data.ok),
            texto:
                data.message ||
                (data.ok
                    ? 'Informe enviado a laboral.'
                    : 'El envío a laboral aún no está configurado.'),
        };
    } catch (error) {
        console.error('Error:', error);
        errorExport.value = 'Error de conexión. Comprueba tu red e inténtalo de nuevo.';
    } finally {
        enviandoLaboral.value = false;
    }
};
</script>

<template>
    <Head title="Exportar registros del equipo" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <Link
                    :href="route(homeRoute)"
                    class="text-sm font-medium text-indigo-600 hover:text-indigo-800"
                >
                    ← Volver a {{ tituloPanel }}
                </Link>
                <h2 class="mt-2 text-xl font-semibold leading-tight text-slate-900">
                    Exportar registros del equipo
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{
                        esAdmin
                            ? 'Como administrador puedes descargar los registros de cualquier empleado.'
                            : 'Como encargado puedes descargar los registros de los trabajadores de tu equipo.'
                    }}
                </p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <!-- Exportar individual -->
                <div
                    v-if="empleados.length === 0"
                    class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-900"
                >
                    No hay trabajadores asignados para exportar.
                    <span v-if="!esAdmin">
                        Contacta con administración para vincular empleados a tu
                        departamento.
                    </span>
                </div>

                <div
                    v-else
                    class="grid gap-6"
                >
                    <!-- Sección: Exportar empleado individual -->
                    <div
                        class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                    >
                        <h3 class="mb-4 text-lg font-semibold text-slate-900">
                            Exportar empleado individual
                        </h3>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label
                                    for="empleado"
                                    class="block text-sm font-medium text-slate-700"
                                >
                                    Trabajador
                                </label>
                                <select
                                    id="empleado"
                                    v-model="empleadoId"
                                    class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option
                                        v-for="emp in empleados"
                                        :key="emp.id"
                                        :value="emp.id"
                                    >
                                        {{ emp.nombre }}
                                        <template v-if="emp.codigo">
                                            ({{ emp.codigo }})
                                        </template>
                                        — {{ etiquetaRol(emp.rol) }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label
                                    for="mes"
                                    class="block text-sm font-medium text-slate-700"
                                >
                                    Mes
                                </label>
                                <input
                                    id="mes"
                                    v-model="mes"
                                    type="month"
                                    class="mt-1 w-full rounded-lg border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>

                            <div
                                v-if="empleadoSeleccionado"
                                class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600"
                            >
                                <p class="font-medium text-slate-800">
                                    {{ empleadoSeleccionado.nombre }}
                                </p>
                                <p>{{ empleadoSeleccionado.email }}</p>
                            </div>
                        </div>

                        <div class="mt-6 flex flex-wrap gap-3">
                            <a
                                :href="puedeExportar ? urlExport('excel') : '#'"
                                class="inline-flex rounded-lg px-4 py-2 text-sm font-semibold text-white"
                                :class="
                                    puedeExportar
                                        ? 'bg-emerald-600 hover:bg-emerald-700'
                                        : 'cursor-not-allowed bg-slate-300'
                                "
                                @click="!puedeExportar && $event.preventDefault()"
                            >
                                Descargar Excel
                            </a>
                            <a
                                :href="puedeExportar ? urlExport('pdf') : '#'"
                                class="inline-flex rounded-lg px-4 py-2 text-sm font-semibold text-white"
                                :class="
                                    puedeExportar
                                        ? 'bg-rose-600 hover:bg-rose-700'
                                        : 'cursor-not-allowed bg-slate-300'
                                "
                                @click="!puedeExportar && $event.preventDefault()"
                            >
                                Descargar PDF
                            </a>
                            <a
                                :href="puedeExportar ? urlExport('json') : '#'"
                                class="inline-flex rounded-lg px-4 py-2 text-sm font-semibold text-white"
                                :class="
                                    puedeExportar
                                        ? 'bg-indigo-600 hover:bg-indigo-700'
                                        : 'cursor-not-allowed bg-slate-300'
                                "
                                @click="!puedeExportar && $event.preventDefault()"
                            >
                                Descargar JSON
                            </a>
                        </div>

                        <p class="mt-4 text-xs text-slate-500">
                            La exportación respeta el tope contractual mensual del
                            trabajador (sin horas extra), igual que en su perfil.
                        </p>
                    </div>

                    <!-- Sección: Exportar varios trabajadores -->
                    <div
                        class="rounded-2xl border border-indigo-200 bg-indigo-50 p-6 shadow-sm"
                    >
                        <h3 class="mb-2 text-lg font-semibold text-indigo-900">
                            Exportar plantilla
                        </h3>
                        <p class="mb-5 text-sm text-indigo-700">
                            Un único archivo con el resumen de todos los incluidos. El Excel añade además
                            una hoja de detalle por trabajador. Es el formato que suele pedir la asesoría laboral.
                        </p>

                        <fieldset class="mb-4">
                            <legend class="mb-2 text-sm font-medium text-indigo-900">A quién incluir</legend>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <label
                                    v-for="opcion in [
                                        { value: 'todos', label: 'Toda la plantilla' },
                                        { value: 'departamento', label: 'Por departamento' },
                                        { value: 'puesto', label: 'Por puesto' },
                                        { value: 'seleccion', label: 'Elegir manualmente' },
                                    ]"
                                    :key="opcion.value"
                                    class="flex items-center gap-2 rounded-lg border border-indigo-200 bg-white px-3 py-2 text-sm"
                                    :class="alcance === opcion.value ? 'ring-2 ring-indigo-500' : ''"
                                >
                                    <input
                                        v-model="alcance"
                                        type="radio"
                                        :value="opcion.value"
                                        class="border-indigo-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                    <span class="text-slate-700">{{ opcion.label }}</span>
                                </label>
                            </div>
                        </fieldset>

                        <div v-if="alcance === 'departamento'" class="mb-4">
                            <label for="departamento" class="block text-sm font-medium text-indigo-900">
                                Departamento
                            </label>
                            <select
                                id="departamento"
                                v-model="departamento"
                                class="mt-1 w-full rounded-lg border-indigo-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="" disabled>Seleccionar...</option>
                                <option v-for="d in filtros.departments" :key="d" :value="d">{{ d }}</option>
                            </select>
                        </div>

                        <div v-if="alcance === 'puesto'" class="mb-4">
                            <label for="puesto" class="block text-sm font-medium text-indigo-900">
                                Puesto
                            </label>
                            <select
                                id="puesto"
                                v-model="puesto"
                                class="mt-1 w-full rounded-lg border-indigo-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="" disabled>Seleccionar...</option>
                                <option v-for="p in filtros.positions" :key="p" :value="p">{{ p }}</option>
                            </select>
                        </div>

                        <div v-if="alcance === 'seleccion'" class="mb-4">
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-sm font-medium text-indigo-900">Trabajadores</span>
                                <div class="flex gap-3 text-xs">
                                    <button type="button" class="text-indigo-700 underline" @click="seleccionarTodos">
                                        Todos
                                    </button>
                                    <button type="button" class="text-indigo-700 underline" @click="limpiarSeleccion">
                                        Ninguno
                                    </button>
                                </div>
                            </div>
                            <div class="max-h-56 overflow-y-auto rounded-lg border border-indigo-200 bg-white">
                                <label
                                    v-for="emp in empleados"
                                    :key="emp.id"
                                    class="flex items-center gap-3 border-b border-slate-100 px-3 py-2 text-sm last:border-0 hover:bg-slate-50"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="seleccionados.includes(emp.id)"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        @change="alternarSeleccion(emp.id)"
                                    />
                                    <span class="min-w-0 flex-1">
                                        <span class="font-medium text-slate-800">{{ emp.nombre }}</span>
                                        <span class="block text-xs text-slate-500">
                                            {{ emp.puesto || etiquetaRol(emp.rol) }}
                                            <template v-if="emp.departamento"> · {{ emp.departamento }}</template>
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="flex flex-col gap-4">
                            <div>
                                <label
                                    for="mes-equipo"
                                    class="block text-sm font-medium text-indigo-900"
                                >
                                    Mes
                                </label>
                                <input
                                    id="mes-equipo"
                                    v-model="mesEquipo"
                                    type="month"
                                    class="mt-1 w-full rounded-lg border-indigo-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:max-w-xs"
                                />
                            </div>

                            <div class="flex flex-wrap gap-3">
                                <button
                                    type="button"
                                    :disabled="!puedeExportarEquipo"
                                    class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-white"
                                    :class="
                                        !puedeExportarEquipo
                                            ? 'cursor-not-allowed bg-emerald-300'
                                            : 'bg-emerald-600 hover:bg-emerald-700'
                                    "
                                    @click="exportarEquipo('excel')"
                                >
                                    <svg
                                        v-if="exportandoEquipo && formatoActivo === 'excel'"
                                        class="h-4 w-4 animate-spin"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                    >
                                        <circle
                                            class="opacity-25"
                                            cx="12"
                                            cy="12"
                                            r="10"
                                            stroke="currentColor"
                                            stroke-width="4"
                                        />
                                        <path
                                            class="opacity-75"
                                            fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                        />
                                    </svg>
                                    {{
                                        exportandoEquipo && formatoActivo === 'excel'
                                            ? 'Generando…'
                                            : 'Descargar Excel'
                                    }}
                                </button>
                                <button
                                    type="button"
                                    :disabled="!puedeExportarEquipo"
                                    class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-white"
                                    :class="
                                        !puedeExportarEquipo
                                            ? 'cursor-not-allowed bg-rose-300'
                                            : 'bg-rose-600 hover:bg-rose-700'
                                    "
                                    @click="exportarEquipo('pdf')"
                                >
                                    <svg
                                        v-if="exportandoEquipo && formatoActivo === 'pdf'"
                                        class="h-4 w-4 animate-spin"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                    >
                                        <circle
                                            class="opacity-25"
                                            cx="12"
                                            cy="12"
                                            r="10"
                                            stroke="currentColor"
                                            stroke-width="4"
                                        />
                                        <path
                                            class="opacity-75"
                                            fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                        />
                                    </svg>
                                    {{
                                        exportandoEquipo && formatoActivo === 'pdf'
                                            ? 'Generando…'
                                            : 'Descargar PDF'
                                    }}
                                </button>
                                <button
                                    type="button"
                                    :disabled="!puedeExportarEquipo"
                                    class="inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-white"
                                    :class="
                                        !puedeExportarEquipo
                                            ? 'cursor-not-allowed bg-indigo-300'
                                            : 'bg-indigo-600 hover:bg-indigo-700'
                                    "
                                    @click="exportarEquipo('json')"
                                >
                                    <svg
                                        v-if="exportandoEquipo && formatoActivo === 'json'"
                                        class="h-4 w-4 animate-spin"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                    >
                                        <circle
                                            class="opacity-25"
                                            cx="12"
                                            cy="12"
                                            r="10"
                                            stroke="currentColor"
                                            stroke-width="4"
                                        />
                                        <path
                                            class="opacity-75"
                                            fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                        />
                                    </svg>
                                    {{
                                        exportandoEquipo && formatoActivo === 'json'
                                            ? 'Generando…'
                                            : 'Descargar JSON'
                                    }}
                                </button>
                                <button
                                    type="button"
                                    :disabled="!puedeExportarEquipo"
                                    class="inline-flex items-center justify-center gap-2 rounded-lg border px-4 py-2 text-sm font-semibold"
                                    :class="
                                        !puedeExportarEquipo
                                            ? 'cursor-not-allowed border-amber-200 bg-amber-50 text-amber-400'
                                            : 'border-amber-300 bg-amber-100 text-amber-900 hover:bg-amber-200'
                                    "
                                    @click="enviarALaboral"
                                >
                                    <svg
                                        v-if="enviandoLaboral"
                                        class="h-4 w-4 animate-spin"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                    >
                                        <circle
                                            class="opacity-25"
                                            cx="12"
                                            cy="12"
                                            r="10"
                                            stroke="currentColor"
                                            stroke-width="4"
                                        />
                                        <path
                                            class="opacity-75"
                                            fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                        />
                                    </svg>
                                    {{
                                        enviandoLaboral
                                            ? 'Enviando…'
                                            : 'Enviar a laboral'
                                    }}
                                </button>
                            </div>
                        </div>

                        <p class="mt-3 text-sm" :class="incluidos.length ? 'text-indigo-800' : 'text-amber-700'">
                            <template v-if="incluidos.length">
                                Se incluirán <span class="font-semibold">{{ incluidos.length }}</span>
                                trabajador(es) más una hoja de resumen.
                            </template>
                            <template v-else>
                                Ningún trabajador cumple los filtros seleccionados.
                            </template>
                        </p>

                        <p v-if="!laboralConfigurado" class="mt-2 text-xs text-amber-800">
                            El envío a {{ laboralNombre }} está visible, pero el correo de la asesoría
                            aún no está configurado. Cuando lo esté, este botón mandará el Excel del mes.
                        </p>

                        <p
                            v-if="mensajeLaboral"
                            class="mt-2 text-sm"
                            :class="mensajeLaboral.ok ? 'text-emerald-700' : 'text-amber-800'"
                        >
                            {{ mensajeLaboral.texto }}
                        </p>

                        <p v-if="errorExport" class="mt-2 text-sm text-red-700">{{ errorExport }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
