<script setup lang="ts">
import { ref, computed } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import AppIcon from './AppIcon.vue';
import AppButton from './AppButton.vue';
import ToastContainer from './ToastContainer.vue';
import { type IconConcept } from '@/lib/icons';

interface NavItem {
    label: string;
    href: string;
    icon?: IconConcept;
    badge?: string;
}

interface NavGroup {
    title: string;
    icon: IconConcept;
    items: NavItem[];
}

const page = usePage();
const authUser = computed(() => (page.props.auth as any)?.user || { name: 'Admin User', email: 'owner@tasa.com', role: 'Owner' });
const userRole = computed(() => authUser.value.role || 'Owner');

// Responsive drawer & sheet states
const isMobileMenuOpen = ref(false);
const isMobileMoreOpen = ref(false);

// Expanded state for sidebar groups
const expandedGroups = ref<Record<string, boolean>>({
    Sales: true,
    Inventory: false,
    Payroll: false,
    Reports: false,
    Settings: false,
});

const toggleGroup = (title: string) => {
    expandedGroups.value[title] = !expandedGroups.value[title];
};

// Check if a path is active
const isActive = (url: string) => {
    return page.url === url || page.url.startsWith(`${url}/`);
};

// Global search query
const globalSearch = ref('');
const handleGlobalSearch = (e: Event) => {
    e.preventDefault();
    if (!globalSearch.value.trim()) return;
    router.visit(`/dashboard?search=${encodeURIComponent(globalSearch.value.trim())}`);
};

const handleLogout = () => {
    router.post('/logout');
};

const userPermissions = computed<string[]>(() => authUser.value?.permissions || []);
const hasPermission = (perm: string) => {
    if (userRole.value === 'Owner') return true;
    return userPermissions.value.includes(perm);
};

const navGroups: NavGroup[] = [
    {
        title: 'Sales',
        icon: 'sales',
        items: [
            { label: 'New Sale (POS)', href: '/sales/create' },
            { label: 'Invoices / Sales List', href: '/sales/invoices' },
            { label: 'Customers', href: '/sales/customers' },
            { label: 'Courier Orders', href: '/sales/courier' },
        ],
    },
    {
        title: 'Inventory',
        icon: 'inventory',
        items: [
            { label: 'Products', href: '/inventory/products' },
            { label: 'Leather Stock', href: '/inventory/leather' },
            { label: 'Accessories', href: '/inventory/accessories' },
            { label: 'Stock Adjustments', href: '/inventory/adjustments' },
        ],
    },
    {
        title: 'Payroll',
        icon: 'payroll',
        items: [
            { label: 'Salaried Employees', href: '/payroll/employees' },
            { label: 'Wage Workers', href: '/payroll/workers' },
        ],
    },
    {
        title: 'Reports',
        icon: 'reports',
        items: [
            { label: 'Sales Report', href: '/reports/sales' },
            { label: 'Profit & Loss', href: '/reports/profit-loss' },
        ],
    },
    {
        title: 'Settings',
        icon: 'settings',
        items: [
            { label: 'User Accounts', href: '/settings/users' },
            { label: 'Roles & Permissions', href: '/settings/roles' },
            { label: 'General & Categories', href: '/settings/general' },
            { label: 'Invoice Branding', href: '/settings/invoice' },
            { label: 'Pathao Courier', href: '/settings/pathao' },
            { label: 'Audit Trail', href: '/settings/audit-logs' },
        ],
    },
];

const filteredNavGroups = computed(() => {
    return navGroups.map(group => {
        if (group.title === 'Payroll' && !hasPermission('payroll.view')) {
            return null;
        }
        if (group.title === 'Settings' && !hasPermission('settings.manage') && !hasPermission('users.manage') && !hasPermission('roles.manage')) {
            return null;
        }

        const items = group.items.filter(item => {
            if (item.href === '/reports/profit-loss' && !hasPermission('reports.profit_loss')) {
                return false;
            }
            if (item.href === '/settings/users' && !hasPermission('users.manage')) {
                return false;
            }
            if (item.href === '/settings/roles' && !hasPermission('roles.manage')) {
                return false;
            }
            if ((item.href === '/settings/invoice' || item.href === '/settings/pathao' || item.href === '/settings/general' || item.href === '/settings/audit-logs') && !hasPermission('settings.manage')) {
                return false;
            }
            return true;
        });

        if (items.length === 0) return null;

        return {
            ...group,
            items,
        };
    }).filter(Boolean) as NavGroup[];
});
</script>

<template>
    <div class="min-h-screen bg-page text-ink-700 flex flex-col antialiased">
        <!-- Accessibility Skip Link -->
        <a href="#main-content" class="skip-to-content">
            Skip to main content
        </a>

        <!-- Global Toast Container -->
        <ToastContainer />

        <div class="flex flex-1 min-h-screen overflow-hidden">
            <!-- DESKTOP SIDEBAR (>= 1024px, 248px wide) -->
            <aside
                class="hidden lg:flex lg:flex-col lg:w-[248px] bg-surface border-r border-line-200 flex-shrink-0 z-20"
                aria-label="Desktop Sidebar"
            >
                <!-- Brand Header (56px) -->
                <div class="h-[56px] px-6 border-b border-line-200 flex items-center justify-between">
                    <Link href="/dashboard" class="flex items-center space-x-2 focus:outline-teal-700">
                        <span class="text-xl font-black tracking-widest text-teal-700">TASA</span>
                        <span class="text-xs font-semibold text-ink-500 uppercase tracking-wider">POS</span>
                    </Link>
                </div>

                <!-- Navigation List -->
                <div class="flex-1 overflow-y-auto p-3 space-y-1">
                    <!-- Dashboard -->
                    <Link
                        href="/dashboard"
                        :class="[
                            'flex items-center h-[44px] px-3 text-sm font-medium transition-colors duration-150',
                            isActive('/dashboard')
                                ? 'bg-teal-100 text-teal-700 font-semibold border-l-4 border-teal-700 pl-[8px]'
                                : 'text-ink-700 hover:bg-teal-50',
                        ]"
                    >
                        <AppIcon name="dashboard" :size="20" class="mr-3" />
                        <span>Dashboard</span>
                    </Link>

                    <!-- Nav Groups (Sales, Inventory, etc.) -->
                    <div v-for="group in filteredNavGroups" :key="group.title" class="pt-1">
                        <!-- Group Header Toggle -->
                        <button
                            type="button"
                            class="w-full flex items-center justify-between h-[40px] px-3 text-xs font-bold uppercase tracking-wider text-ink-600 hover:bg-teal-50 transition-colors"
                            @click="toggleGroup(group.title)"
                        >
                            <span class="flex items-center">
                                <AppIcon :name="group.icon" :size="18" class="mr-2.5 text-ink-500" />
                                <span>{{ group.title }}</span>
                            </span>
                            <AppIcon
                                :name="expandedGroups[group.title] ? 'chevron-up' : 'chevron-down'"
                                :size="14"
                                class="text-ink-500"
                            />
                        </button>

                        <!-- Group Sub-items -->
                        <div v-show="expandedGroups[group.title]" class="space-y-0.5 mt-0.5">
                            <Link
                                v-for="sub in group.items"
                                :key="sub.href"
                                :href="sub.href"
                                :class="[
                                    'flex items-center h-[36px] pl-[34px] pr-3 text-xs transition-colors duration-150',
                                    isActive(sub.href)
                                        ? 'bg-teal-100 text-teal-700 font-bold border-l-4 border-teal-700 pl-[30px]'
                                        : 'text-ink-700 hover:bg-teal-50 font-medium',
                                ]"
                            >
                                <span>{{ sub.label }}</span>
                            </Link>
                        </div>
                    </div>

                    <!-- Direct Expenses Link -->
                    <Link
                        href="/expenses"
                        :class="[
                            'flex items-center h-[44px] px-3 text-sm font-medium transition-colors duration-150 mt-1',
                            isActive('/expenses')
                                ? 'bg-teal-100 text-teal-700 font-semibold border-l-4 border-teal-700 pl-[8px]'
                                : 'text-ink-700 hover:bg-teal-50',
                        ]"
                    >
                        <AppIcon name="expenses" :size="20" class="mr-3" />
                        <span>Expenses</span>
                    </Link>
                </div>

                <!-- User Footer in Sidebar -->
                <div class="p-3 border-t border-line-200 bg-surface flex items-center justify-between">
                    <div class="flex flex-col truncate mr-2">
                        <span class="text-xs font-bold text-ink-900 truncate">{{ authUser.name }}</span>
                        <span class="text-[10px] text-teal-700 font-semibold uppercase">{{ userRole }}</span>
                    </div>

                    <button
                        type="button"
                        class="p-2 text-ink-500 hover:text-danger hover:bg-danger-bg transition-colors"
                        title="Log out"
                        aria-label="Log out"
                        @click="handleLogout"
                    >
                        <AppIcon name="logout" :size="18" />
                    </button>
                </div>
            </aside>

            <!-- TABLET ICON RAIL (640px to 1023px, 72px wide) -->
            <aside
                class="hidden sm:flex lg:hidden sm:flex-col sm:w-[72px] bg-surface border-r border-line-200 flex-shrink-0 z-20 items-center py-3"
                aria-label="Tablet Rail"
            >
                <Link href="/dashboard" class="mb-6 p-2 text-teal-700 font-black text-lg focus:outline-teal-700">
                    TASA
                </Link>

                <div class="flex-1 space-y-4 flex flex-col items-center">
                    <Link
                        href="/dashboard"
                        :class="['p-3 transition-colors', isActive('/dashboard') ? 'bg-teal-100 text-teal-700' : 'text-ink-700 hover:bg-teal-50']"
                        title="Dashboard"
                    >
                        <AppIcon name="dashboard" :size="24" />
                    </Link>

                    <Link
                        href="/sales/create"
                        :class="['p-3 transition-colors', isActive('/sales') ? 'bg-teal-100 text-teal-700' : 'text-ink-700 hover:bg-teal-50']"
                        title="Sales"
                    >
                        <AppIcon name="sales" :size="24" />
                    </Link>

                    <Link
                        href="/inventory/products"
                        :class="['p-3 transition-colors', isActive('/inventory') ? 'bg-teal-100 text-teal-700' : 'text-ink-700 hover:bg-teal-50']"
                        title="Inventory"
                    >
                        <AppIcon name="inventory" :size="24" />
                    </Link>

                    <Link
                        href="/expenses"
                        :class="['p-3 transition-colors', isActive('/expenses') ? 'bg-teal-100 text-teal-700' : 'text-ink-700 hover:bg-teal-50']"
                        title="Expenses"
                    >
                        <AppIcon name="expenses" :size="24" />
                    </Link>

                    <button
                        type="button"
                        class="p-3 text-ink-700 hover:bg-teal-50 transition-colors"
                        title="Open Menu"
                        aria-label="Open Menu Drawer"
                        @click="isMobileMenuOpen = true"
                    >
                        <AppIcon name="menu" :size="24" />
                    </button>
                </div>
            </aside>

            <!-- MAIN CONTENT WRAPPER -->
            <div class="flex-1 flex flex-col min-w-0 overflow-y-auto">
                <!-- TOP BAR (56px) -->
                <header
                    class="h-[56px] bg-surface border-b border-line-200 px-4 sm:px-6 flex items-center justify-between sticky top-0 z-10"
                >
                    <!-- Left: Mobile Menu Toggle / Brand -->
                    <div class="flex items-center space-x-3">
                        <button
                            type="button"
                            class="sm:hidden p-2 text-ink-700 hover:bg-teal-50"
                            aria-label="Open navigation menu"
                            @click="isMobileMenuOpen = true"
                        >
                            <AppIcon name="menu" :size="20" />
                        </button>

                        <div class="lg:hidden flex items-center space-x-1 font-bold text-teal-700 tracking-wider">
                            <span>TASA</span>
                        </div>
                    </div>

                    <!-- Center: Global Search (Desktop & Tablet) -->
                    <div class="hidden sm:flex flex-1 max-w-md mx-6">
                        <form class="w-full relative" @submit="handleGlobalSearch">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-ink-500">
                                <AppIcon name="search" :size="16" />
                            </div>
                            <input
                                v-model="globalSearch"
                                type="search"
                                placeholder="Search invoice no, phone, customer, or product..."
                                class="w-full h-[36px] pl-9 pr-3 text-xs text-ink-900 bg-page border border-line-300 focus:bg-surface focus:border-teal-700 rounded-none focus:outline-teal-700"
                            />
                        </form>
                    </div>

                    <!-- Right: New Sale Shortcut & User Actions -->
                    <div class="flex items-center space-x-3">
                        <!-- Primary Shortcut: ＋ New Sale (The Only Solid Teal Button on Shell) -->
                        <Link href="/sales/create">
                            <AppButton variant="primary" size="sm" icon="plus">
                                <span class="hidden sm:inline">New Sale</span>
                                <span class="sm:hidden">Sale</span>
                            </AppButton>
                        </Link>

                        <!-- User Profile Info / Logout -->
                        <div class="hidden md:flex items-center space-x-2 pl-3 border-l border-line-200">
                            <div class="text-right">
                                <div class="text-xs font-bold text-ink-900">{{ authUser.name }}</div>
                                <div class="text-[10px] text-ink-500 uppercase">{{ userRole }}</div>
                            </div>
                        </div>
                    </div>
                </header>

                <!-- MAIN WORKSPACE CONTAINER (Max-width 1440px) -->
                <main
                    id="main-content"
                    tabindex="-1"
                    class="flex-1 p-4 sm:p-6 lg:p-8 max-w-[1440px] w-full mx-auto outline-none focus:outline-none mb-16 sm:mb-0"
                >
                    <slot />
                </main>
            </div>
        </div>

        <!-- MOBILE BOTTOM NAVIGATION (< 640px, 64px tall, 5 slots) -->
        <nav
            class="sm:hidden fixed bottom-0 inset-x-0 h-[64px] bg-surface border-t border-line-300 flex items-center justify-around z-30"
            style="padding-bottom: env(safe-area-inset-bottom, 0px);"
            aria-label="Mobile Navigation"
        >
            <!-- Slot 1: New Sale -->
            <Link
                href="/sales/create"
                :class="[
                    'flex flex-col items-center justify-center flex-1 h-full py-1 text-[11px] font-semibold transition-colors',
                    isActive('/sales/create') ? 'text-teal-700 border-t-2 border-teal-700' : 'text-ink-600',
                ]"
            >
                <AppIcon name="plus" :size="20" />
                <span class="mt-0.5">Sale</span>
            </Link>

            <!-- Slot 2: Invoices -->
            <Link
                href="/sales/invoices"
                :class="[
                    'flex flex-col items-center justify-center flex-1 h-full py-1 text-[11px] font-semibold transition-colors',
                    isActive('/sales/invoices') ? 'text-teal-700 border-t-2 border-teal-700' : 'text-ink-600',
                ]"
            >
                <AppIcon name="invoices" :size="20" />
                <span class="mt-0.5">Invoices</span>
            </Link>

            <!-- Slot 3: Inventory -->
            <Link
                href="/inventory/products"
                :class="[
                    'flex flex-col items-center justify-center flex-1 h-full py-1 text-[11px] font-semibold transition-colors',
                    isActive('/inventory') ? 'text-teal-700 border-t-2 border-teal-700' : 'text-ink-600',
                ]"
            >
                <AppIcon name="inventory" :size="20" />
                <span class="mt-0.5">Stock</span>
            </Link>

            <!-- Slot 4: Expenses -->
            <Link
                href="/expenses"
                :class="[
                    'flex flex-col items-center justify-center flex-1 h-full py-1 text-[11px] font-semibold transition-colors',
                    isActive('/expenses') ? 'text-teal-700 border-t-2 border-teal-700' : 'text-ink-600',
                ]"
            >
                <AppIcon name="expenses" :size="20" />
                <span class="mt-0.5">Expenses</span>
            </Link>

            <!-- Slot 5: More -->
            <button
                type="button"
                :class="[
                    'flex flex-col items-center justify-center flex-1 h-full py-1 text-[11px] font-semibold transition-colors',
                    isMobileMoreOpen ? 'text-teal-700 border-t-2 border-teal-700' : 'text-ink-600',
                ]"
                @click="isMobileMoreOpen = true"
            >
                <AppIcon name="more" :size="20" />
                <span class="mt-0.5">More</span>
            </button>
        </nav>

        <!-- MOBILE MORE BOTTOM SHEET -->
        <div
            v-if="isMobileMoreOpen"
            class="sm:hidden fixed inset-0 z-40"
            role="dialog"
            aria-modal="true"
        >
            <div class="fixed inset-0 bg-[#0F1B1A] bg-opacity-40" @click="isMobileMoreOpen = false" />
            <div
                class="fixed bottom-[64px] inset-x-0 bg-surface border-t border-line-300 p-4 space-y-3 z-50 max-h-[70vh] overflow-y-auto"
            >
                <div class="flex justify-between items-center pb-2 border-b border-line-200">
                    <span class="text-sm font-bold text-ink-900">More Sections</span>
                    <button type="button" class="text-ink-500 p-1" @click="isMobileMoreOpen = false">
                        <AppIcon name="close" :size="18" />
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-2 text-xs">
                    <Link
                        href="/dashboard"
                        class="p-3 bg-page border border-line-200 font-semibold flex items-center space-x-2"
                        @click="isMobileMoreOpen = false"
                    >
                        <AppIcon name="dashboard" :size="18" class="text-teal-700" />
                        <span>Dashboard</span>
                    </Link>
                    <Link
                        href="/sales/customers"
                        class="p-3 bg-page border border-line-200 font-semibold flex items-center space-x-2"
                        @click="isMobileMoreOpen = false"
                    >
                        <AppIcon name="customers" :size="18" class="text-teal-700" />
                        <span>Customers</span>
                    </Link>
                    <Link
                        href="/sales/courier"
                        class="p-3 bg-page border border-line-200 font-semibold flex items-center space-x-2"
                        @click="isMobileMoreOpen = false"
                    >
                        <AppIcon name="courier" :size="18" class="text-teal-700" />
                        <span>Pathao Orders</span>
                    </Link>
                    <Link
                        href="/payroll/employees"
                        class="p-3 bg-page border border-line-200 font-semibold flex items-center space-x-2"
                        @click="isMobileMoreOpen = false"
                    >
                        <AppIcon name="payroll" :size="18" class="text-teal-700" />
                        <span>Payroll</span>
                    </Link>
                    <Link
                        href="/reports/sales"
                        class="p-3 bg-page border border-line-200 font-semibold flex items-center space-x-2"
                        @click="isMobileMoreOpen = false"
                    >
                        <AppIcon name="reports" :size="18" class="text-teal-700" />
                        <span>Sales Report</span>
                    </Link>
                    <Link
                        href="/reports/profit-loss"
                        class="p-3 bg-page border border-line-200 font-semibold flex items-center space-x-2"
                        @click="isMobileMoreOpen = false"
                    >
                        <AppIcon name="receipt" :size="18" class="text-teal-700" />
                        <span>Profit & Loss</span>
                    </Link>
                    <Link
                        href="/settings/general"
                        class="p-3 bg-page border border-line-200 font-semibold flex items-center space-x-2"
                        @click="isMobileMoreOpen = false"
                    >
                        <AppIcon name="settings" :size="18" class="text-teal-700" />
                        <span>Settings</span>
                    </Link>
                    <button
                        type="button"
                        class="p-3 bg-danger-bg text-danger border border-danger font-semibold flex items-center space-x-2 text-left"
                        @click="handleLogout"
                    >
                        <AppIcon name="logout" :size="18" />
                        <span>Log out</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- FULL MOBILE OVERLAY DRAWER -->
        <div
            v-if="isMobileMenuOpen"
            class="fixed inset-0 z-50 lg:hidden"
            role="dialog"
            aria-modal="true"
        >
            <div class="fixed inset-0 bg-[#0F1B1A] bg-opacity-40" @click="isMobileMenuOpen = false" />
            <div class="fixed inset-y-0 left-0 w-[280px] bg-surface border-r border-line-300 p-4 flex flex-col z-50">
                <div class="flex items-center justify-between pb-4 border-b border-line-200 mb-4">
                    <span class="text-xl font-black text-teal-700">TASA POS</span>
                    <button type="button" class="p-1 text-ink-600" @click="isMobileMenuOpen = false">
                        <AppIcon name="close" :size="20" />
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto space-y-2 text-sm font-medium">
                    <Link
                        href="/dashboard"
                        class="block px-3 py-2 hover:bg-teal-50"
                        @click="isMobileMenuOpen = false"
                    >
                        Dashboard
                    </Link>

                    <div v-for="g in filteredNavGroups" :key="g.title" class="pt-2">
                        <div class="px-3 text-xs font-bold text-ink-500 uppercase">{{ g.title }}</div>
                        <Link
                            v-for="sub in g.items"
                            :key="sub.href"
                            :href="sub.href"
                            class="block px-4 py-1.5 text-xs text-ink-700 hover:bg-teal-50"
                            @click="isMobileMenuOpen = false"
                        >
                            {{ sub.label }}
                        </Link>
                    </div>

                    <Link
                        href="/expenses"
                        class="block px-3 py-2 hover:bg-teal-50 mt-2 font-semibold"
                        @click="isMobileMenuOpen = false"
                    >
                        Expenses
                    </Link>
                </div>

                <div class="pt-4 border-t border-line-200 flex justify-between items-center">
                    <div class="text-xs">
                        <div class="font-bold text-ink-900">{{ authUser.name }}</div>
                        <div class="text-teal-700 uppercase">{{ userRole }}</div>
                    </div>
                    <button type="button" class="text-danger text-xs font-bold" @click="handleLogout">
                        Log out
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
