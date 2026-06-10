<script setup>
import { computed, ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import NotificationsBell from '@/Components/NotificationsBell.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link, usePage } from '@inertiajs/vue3';

const showingNavigationDropdown = ref(false);
const page = usePage();

const panelActivo = computed(() => {
    const ruta = page.props.auth?.panel?.route;
    if (!ruta) {
        return false;
    }

    const prefijo = ruta.startsWith('admin') ? 'admin.' : 'manager.';

    const adminRoutes = [
        `${prefijo}index`,
        `${prefijo}exports.index`,
        `${prefijo}solicitudes.index`,
        'admin.employees.index',
        'admin.employees.create',
        'admin.employees.edit',
        'admin.manual-clock.index',
        'admin.schedules.index',
        'admin.zones.index',
        'admin.reports.index',
        'admin.audit.index',
        'admin.absences.index',
        'manager.absences.index',
    ];

    return adminRoutes.some((name) => route().current(name));
});
</script>

<template>
    <div>
        <div class="min-h-screen bg-slate-50">
            <nav
                class="border-b border-gray-100 bg-white"
            >
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="flex h-16 justify-between">
                        <div class="flex min-w-0 flex-1">
                            <div class="flex shrink-0 items-center">
                                <Link :href="route('dashboard')">
                                    <ApplicationLogo
                                        class="block h-9 w-auto fill-current text-gray-800"
                                    />
                                </Link>
                            </div>

                            <div
                                class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex"
                            >
                                <NavLink
                                    :href="route('dashboard')"
                                    :active="route().current('dashboard')"
                                >
                                    Inicio
                                </NavLink>
                                <NavLink
                                    :href="route('fichaje.index')"
                                    :active="route().current('fichaje.index')"
                                >
                                    Fichar
                                </NavLink>
                                <NavLink
                                    :href="route('calendar.index')"
                                    :active="route().current('calendar.index')"
                                >
                                    Calendario
                                </NavLink>
                                <NavLink
                                    :href="route('solicitudes.index')"
                                    :active="route().current('solicitudes.index')"
                                >
                                    Solicitudes
                                </NavLink>
                                <NavLink
                                    v-if="$page.props.auth.panel"
                                    :href="route($page.props.auth.panel.route)"
                                    :active="panelActivo"
                                >
                                    {{ $page.props.auth.panel.label }}
                                </NavLink>
                            </div>
                        </div>

                        <!-- Escritorio: notificaciones + perfil -->
                        <div class="hidden sm:ms-4 sm:flex sm:items-center sm:gap-1">
                            <NotificationsBell />
                            <div class="relative">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none"
                                            >
                                                {{ $page.props.auth.user.name }}
                                                <svg
                                                    class="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <DropdownLink :href="route('profile.edit')">
                                            Mi perfil
                                        </DropdownLink>
                                        <DropdownLink
                                            :href="route('logout')"
                                            method="post"
                                            as="button"
                                        >
                                            Cerrar sesión
                                        </DropdownLink>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                        <!-- Móvil: campana + menú -->
                        <div class="flex items-center gap-1 sm:hidden">
                            <NotificationsBell />
                            <button
                                type="button"
                                class="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none"
                                @click="showingNavigationDropdown = !showingNavigationDropdown"
                            >
                                <svg
                                    class="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        :class="{
                                            hidden: showingNavigationDropdown,
                                            'inline-flex': !showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        :class="{
                                            hidden: !showingNavigationDropdown,
                                            'inline-flex': showingNavigationDropdown,
                                        }"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    :class="{
                        block: showingNavigationDropdown,
                        hidden: !showingNavigationDropdown,
                    }"
                    class="sm:hidden"
                >
                    <div class="space-y-1 border-b border-gray-200 pb-3 pt-2">
                        <ResponsiveNavLink
                            :href="route('dashboard')"
                            :active="route().current('dashboard')"
                        >
                            Inicio
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('fichaje.index')"
                            :active="route().current('fichaje.index')"
                        >
                            Fichar
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('calendar.index')"
                            :active="route().current('calendar.index')"
                        >
                            Calendario
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('solicitudes.index')"
                            :active="route().current('solicitudes.index')"
                        >
                            Solicitudes
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            v-if="$page.props.auth.panel"
                            :href="route($page.props.auth.panel.route)"
                            :active="panelActivo"
                        >
                            {{ $page.props.auth.panel.label }}
                        </ResponsiveNavLink>
                    </div>

                    <div class="border-t border-gray-200 pb-1 pt-4">
                        <div class="px-4">
                            <div class="text-base font-medium text-gray-800">
                                {{ $page.props.auth.user.name }}
                            </div>
                            <div class="text-sm font-medium text-gray-500">
                                {{ $page.props.auth.user.email }}
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.edit')">
                                Mi perfil
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                :href="route('logout')"
                                method="post"
                                as="button"
                            >
                                Cerrar sesión
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            <header
                class="bg-white shadow"
                v-if="$slots.header"
            >
                <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
