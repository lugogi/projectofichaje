<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    wide: { type: Boolean, default: false },
});
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-gray-100 pt-4 pb-8 sm:justify-center sm:pt-0"
    >
        <div>
            <Link href="/">
                <ApplicationLogo class="h-16 w-16 fill-current text-gray-500 sm:h-20 sm:w-20" />
            </Link>
        </div>

        <div
            class="mt-4 w-full overflow-hidden bg-white px-4 py-5 shadow-md sm:mt-6 sm:rounded-lg sm:px-6 sm:py-6"
            :class="wide ? 'sm:max-w-3xl' : 'sm:max-w-md'"
        >
            <slot />
        </div>
    </div>
</template>
