<script setup>
defineProps({
    steps: { type: Array, required: true },
    currentStep: { type: Number, required: true },
});
</script>

<template>
    <nav aria-label="Progreso del formulario" class="mb-8">
        <ol class="flex items-center justify-between gap-1 sm:gap-2">
            <li
                v-for="step in steps"
                :key="step.id"
                class="flex flex-1 flex-col items-center"
            >
                <div class="flex w-full items-center">
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold transition sm:h-9 sm:w-9 sm:text-sm"
                        :class="
                            step.id < currentStep
                                ? 'bg-emerald-600 text-white'
                                : step.id === currentStep
                                  ? 'bg-indigo-600 text-white ring-4 ring-indigo-100'
                                  : 'bg-slate-200 text-slate-500'
                        "
                    >
                        <span v-if="step.id < currentStep">✓</span>
                        <span v-else>{{ step.id }}</span>
                    </div>
                    <div
                        v-if="step.id < steps.length"
                        class="mx-1 h-0.5 flex-1 rounded"
                        :class="step.id < currentStep ? 'bg-emerald-500' : 'bg-slate-200'"
                    />
                </div>
                <span
                    class="mt-2 hidden text-center text-[10px] font-medium leading-tight text-slate-600 sm:block sm:text-xs"
                    :class="step.id === currentStep ? 'text-indigo-700' : ''"
                >
                    {{ step.label }}
                </span>
                <span
                    class="mt-2 text-center text-[10px] font-medium leading-tight text-slate-600 sm:hidden"
                    :class="step.id === currentStep ? 'text-indigo-700' : ''"
                >
                    {{ step.short }}
                </span>
            </li>
        </ol>
    </nav>
</template>
