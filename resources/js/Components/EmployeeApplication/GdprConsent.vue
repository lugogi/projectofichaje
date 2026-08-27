<script setup>
import InputError from '@/Components/InputError.vue';
import { ref } from 'vue';

defineProps({
    modelValue: { type: Boolean, default: false },
    error: { type: String, default: null },
    controller: { type: Object, required: true },
    links: { type: Object, required: true },
});

defineEmits(['update:modelValue']);

const expanded = ref(false);
</script>

<template>
    <section class="rounded-xl border border-slate-200">
        <header class="border-b border-slate-200 bg-slate-50 px-4 py-3">
            <h3 class="text-sm font-semibold text-slate-900">
                Información y consentimiento sobre protección de datos
            </h3>
            <p class="mt-1 text-xs text-slate-600">
                Conforme al
                <a
                    :href="links.gdpr"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="font-semibold text-indigo-600 underline decoration-dotted underline-offset-2 hover:text-indigo-800"
                >RGPD</a>
                (Reglamento UE 2016/679) y a la
                <a
                    :href="links.lopdgdd"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="text-indigo-600 underline decoration-dotted underline-offset-2 hover:text-indigo-800"
                >LOPDGDD 3/2018</a>.
            </p>
        </header>

        <div class="space-y-4 px-4 py-4 text-xs leading-relaxed text-slate-700">
            <div>
                <p class="font-semibold text-slate-900">Responsable del tratamiento</p>
                <p class="mt-1">
                    {{ controller.name }} (NIF {{ controller.legal_id }}), con domicilio en
                    {{ controller.address }}. Contacto:
                    <a :href="`mailto:${controller.email}`" class="text-indigo-600 underline">{{ controller.email }}</a>
                    <span v-if="controller.dpo_email">
                        · Delegado de Protección de Datos:
                        <a :href="`mailto:${controller.dpo_email}`" class="text-indigo-600 underline">{{ controller.dpo_email }}</a>
                    </span>
                </p>
            </div>

            <div>
                <p class="font-semibold text-slate-900">Para qué usamos tus datos</p>
                <p class="mt-1">
                    Tus datos no se usan únicamente para tramitar el alta. Se tratarán con estas tres finalidades:
                </p>
                <ul class="mt-2 list-disc space-y-1 ps-4">
                    <li>
                        <span class="font-medium">Alta laboral y gestión de la relación de trabajo</span>: afiliación
                        y alta en la Seguridad Social, formalización del contrato, nómina, retenciones de IRPF y
                        cotizaciones.
                    </li>
                    <li>
                        <span class="font-medium">Gestión documental</span>: custodia y conservación del expediente
                        personal, incluidos los documentos de identidad, permisos de trabajo, titulaciones y
                        justificantes que aportes, así como su puesta a disposición de la Administración cuando sea
                        legalmente exigible.
                    </li>
                    <li>
                        <span class="font-medium">Acceso al portal del empleado</span>: creación de tus credenciales y
                        uso de la plataforma para fichar, consultar tu jornada, descargar documentación y presentar
                        solicitudes de vacaciones, ausencias o correcciones de fichaje.
                    </li>
                </ul>
            </div>

            <div v-if="expanded" class="space-y-4">
                <div>
                    <p class="font-semibold text-slate-900">Base legal</p>
                    <p class="mt-1">
                        La ejecución del contrato de trabajo y las medidas precontractuales solicitadas por ti
                        (art. 6.1.b RGPD); el cumplimiento de obligaciones legales en materia laboral, de Seguridad
                        Social, fiscal y de registro de jornada (art. 6.1.c RGPD, en relación con el art. 34.9 del
                        Estatuto de los Trabajadores); y tu consentimiento para el tratamiento de la documentación
                        que aportas voluntariamente (art. 6.1.a RGPD).
                    </p>
                </div>

                <div>
                    <p class="font-semibold text-slate-900">A quién se comunican</p>
                    <p class="mt-1">
                        A la Tesorería General de la Seguridad Social, a la Agencia Tributaria, a la mutua de
                        accidentes de trabajo, a la entidad bancaria para el abono de la nómina y, en su caso, a la
                        asesoría laboral que presta servicio a la empresa. No se realizan transferencias
                        internacionales de datos ni se toman decisiones automatizadas que te afecten.
                    </p>
                </div>

                <div>
                    <p class="font-semibold text-slate-900">Cuánto tiempo los conservamos</p>
                    <p class="mt-1">
                        Durante toda la relación laboral y, una vez finalizada, durante los plazos de prescripción
                        legalmente previstos: cuatro años para las obligaciones en materia laboral y de Seguridad
                        Social, y los plazos fiscales y mercantiles que resulten aplicables. Los registros de jornada
                        se conservan cuatro años. Si tu solicitud no prospera, los datos se eliminan en el plazo
                        máximo de un año.
                    </p>
                </div>

                <div>
                    <p class="font-semibold text-slate-900">Tus derechos</p>
                    <p class="mt-1">
                        Puedes solicitar el acceso a tus datos, su rectificación o supresión, la limitación u
                        oposición a su tratamiento y la portabilidad, escribiendo a
                        <a :href="`mailto:${controller.email}`" class="text-indigo-600 underline">{{ controller.email }}</a>
                        e indicando qué derecho ejercitas. También puedes retirar tu consentimiento en cualquier
                        momento, sin que ello afecte a la licitud del tratamiento previo ni a lo que la empresa deba
                        conservar por obligación legal. Si consideras que no se han atendido correctamente, puedes
                        reclamar ante la
                        <a
                            :href="links.aepd"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-indigo-600 underline"
                        >Agencia Española de Protección de Datos</a>.
                    </p>
                </div>

                <div>
                    <p class="font-semibold text-slate-900">Veracidad de los datos</p>
                    <p class="mt-1">
                        Te comprometes a que la información y los documentos aportados son ciertos y están en vigor, y
                        a comunicar cualquier variación. Aportar datos falsos puede acarrear responsabilidades
                        laborales y legales.
                    </p>
                </div>
            </div>

            <button
                type="button"
                class="font-medium text-indigo-600 hover:text-indigo-800"
                @click="expanded = !expanded"
            >
                {{ expanded ? 'Ver menos' : 'Leer la información completa (base legal, plazos y derechos)' }}
            </button>
        </div>

        <footer class="border-t border-slate-200 bg-slate-50 px-4 py-4">
            <label class="flex items-start gap-3">
                <input
                    :checked="modelValue"
                    type="checkbox"
                    class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    @change="$emit('update:modelValue', $event.target.checked)"
                />
                <span class="text-sm text-slate-800">
                    He leído y acepto el tratamiento de mis datos personales para el
                    <span class="font-medium">alta laboral</span>, la
                    <span class="font-medium">gestión documental</span> de mi expediente y el
                    <span class="font-medium">acceso al portal del empleado</span>, en los términos descritos arriba.
                </span>
            </label>
            <InputError :message="error" class="mt-2" />
        </footer>
    </section>
</template>
