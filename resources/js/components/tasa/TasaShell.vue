<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import AppIcon from './AppIcon.vue';
import AppButton from './AppButton.vue';
import ToastContainer from './ToastContainer.vue';
import RealtimeClock from './RealtimeClock.vue';
import { type IconConcept } from '@/lib/icons';
import { api } from '@/lib/api';
import { formatMoney, formatQuantity } from '@/lib/formatters';

interface NavItem {
    label: string;
    href: string;
    icon?: IconConcept;
    badge?: string;
    permission?: string;
}

interface NavGroup {
    title: string;
    icon: IconConcept;
    items: NavItem[];
}

const page = usePage();
const authUser = computed(() => (page.props.auth as any)?.user || { name: 'Admin User', email: 'owner@tasa.com', role: 'Owner' });
const userRole = computed(() => authUser.value.role || 'Owner');
const shop = computed(() => (page.props as any).shop || {});
const shopLogo = computed(() => shop.value.logo || null);
const shopName = computed(() => shop.value.name || 'TASA');

// Responsive drawer & sheet states
const isMobileMenuOpen = ref(false);
const isMobileMoreOpen = ref(false);
const isMobileSearchOpen = ref(false);

// Expanded state for sidebar groups
const expandedGroups = ref<Record<string, boolean>>({
    Sales: true,
    Inventory: true,
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

// Global search state
interface SearchInvoiceItem {
    id: number;
    invoice_no: string;
    customer_name: string;
    customer_phone: string;
    sale_date: string | null;
    grand_total: number;
    payment_status: string;
    status: string;
    url: string;
}

interface SearchCustomerItem {
    id: number;
    name: string;
    phone: string;
    city_name: string | null;
    address: string | null;
    url: string;
}

interface SearchProductItem {
    id: number;
    name: string;
    sku: string;
    selling_price: number;
    stock_qty: number;
    unit: string;
    is_low_stock: boolean;
    url: string;
}

interface GlobalSearchResponse {
    query: string;
    invoices: SearchInvoiceItem[];
    customers: SearchCustomerItem[];
    products: SearchProductItem[];
    total_count: number;
    elapsed_ms: number;
}

const globalSearch = ref('');
const searchInvoices = ref<SearchInvoiceItem[]>([]);
const searchCustomers = ref<SearchCustomerItem[]>([]);
const searchProducts = ref<SearchProductItem[]>([]);
const searchTotalCount = ref(0);
const searchElapsedMs = ref(0);
const isSearching = ref(false);
const showSearchResults = ref(false);
const highlightedIndex = ref(-1);

const searchInputRef = ref<HTMLInputElement | null>(null);
const mobileSearchInputRef = ref<HTMLInputElement | null>(null);
let searchDebounceTimeout: any = null;

// Flattened results for keyboard navigation
const flattenedResults = computed(() => {
    const list: Array<{ type: 'invoice' | 'customer' | 'product'; id: string; url: string; label: string }> = [];
    searchInvoices.value.forEach((inv) => {
        list.push({ type: 'invoice', id: `inv-${inv.id}`, url: inv.url, label: inv.invoice_no });
    });
    searchCustomers.value.forEach((c) => {
        list.push({ type: 'customer', id: `cust-${c.id}`, url: c.url, label: c.name });
    });
    searchProducts.value.forEach((p) => {
        list.push({ type: 'product', id: `prod-${p.id}`, url: p.url, label: p.name });
    });
    return list;
});

const onSearchInput = () => {
    clearTimeout(searchDebounceTimeout);
    highlightedIndex.value = -1;
    const q = globalSearch.value.trim();
    if (q.length < 1) {
        searchInvoices.value = [];
        searchCustomers.value = [];
        searchProducts.value = [];
        searchTotalCount.value = 0;
        searchElapsedMs.value = 0;
        showSearchResults.value = false;
        return;
    }

    searchDebounceTimeout = setTimeout(async () => {
        isSearching.value = true;
        try {
            const res = await api.get<GlobalSearchResponse>(`/search?q=${encodeURIComponent(q)}`);
            searchInvoices.value = res.invoices || [];
            searchCustomers.value = res.customers || [];
            searchProducts.value = res.products || [];
            searchTotalCount.value = res.total_count || 0;
            searchElapsedMs.value = res.elapsed_ms || 0;
            showSearchResults.value = true;
        } catch {
            searchInvoices.value = [];
            searchCustomers.value = [];
            searchProducts.value = [];
            searchTotalCount.value = 0;
        } finally {
            isSearching.value = false;
        }
    }, 150);
};

const selectResultUrl = (url: string) => {
    showSearchResults.value = false;
    isMobileSearchOpen.value = false;
    globalSearch.value = '';
    highlightedIndex.value = -1;
    router.visit(url);
};

const handleKeyDown = (e: KeyboardEvent) => {
    if (!showSearchResults.value || flattenedResults.value.length === 0) return;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        highlightedIndex.value = (highlightedIndex.value + 1) % flattenedResults.value.length;
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        highlightedIndex.value = (highlightedIndex.value - 1 + flattenedResults.value.length) % flattenedResults.value.length;
    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (highlightedIndex.value >= 0 && highlightedIndex.value < flattenedResults.value.length) {
            selectResultUrl(flattenedResults.value[highlightedIndex.value].url);
        } else if (flattenedResults.value.length > 0) {
            selectResultUrl(flattenedResults.value[0].url);
        }
    } else if (e.key === 'Escape') {
        showSearchResults.value = false;
        isMobileSearchOpen.value = false;
    }
};

const openDesktopSearch = () => {
    nextTick(() => {
        searchInputRef.value?.focus();
        searchInputRef.value?.select();
        if (globalSearch.value.trim().length > 0) {
            showSearchResults.value = true;
        }
    });
};

const openMobileSearch = () => {
    isMobileSearchOpen.value = true;
    nextTick(() => {
        mobileSearchInputRef.value?.focus();
    });
};

const onGlobalKeydown = (e: KeyboardEvent) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        if (window.innerWidth < 640) {
            openMobileSearch();
        } else {
            openDesktopSearch();
        }
    } else if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes((document.activeElement as HTMLElement)?.tagName)) {
        e.preventDefault();
        if (window.innerWidth < 640) {
            openMobileSearch();
        } else {
            openDesktopSearch();
        }
    } else if (e.key === 'Escape' && (showSearchResults.value || isMobileSearchOpen.value)) {
        showSearchResults.value = false;
        isMobileSearchOpen.value = false;
    }
};

onMounted(() => {
    window.addEventListener('keydown', onGlobalKeydown);
    navGroups.forEach(g => {
        if (g.items.some(i => isActive(i.href))) {
            expandedGroups.value[g.title] = true;
        }
    });
});

onUnmounted(() => {
    window.removeEventListener('keydown', onGlobalKeydown);
    clearTimeout(searchDebounceTimeout);
});

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
            { label: 'New Sale (POS)', href: '/sales/create', permission: 'sales.create' },
            { label: 'Invoices / Sales List', href: '/sales/invoices', permission: 'sales.view' },
            { label: 'Customers', href: '/sales/customers', permission: 'customers.view' },
            { label: 'Courier Orders', href: '/sales/courier', permission: 'courier.view' },
        ],
    },
    {
        title: 'Inventory',
        icon: 'inventory',
        items: [
            { label: 'Products', href: '/inventory/products', permission: 'products.view' },
            { label: 'Leather Stock', href: '/inventory/leather', permission: 'leather.view' },
            { label: 'Accessories', href: '/inventory/accessories', permission: 'accessories.view' },
            { label: 'Stock Adjustments', href: '/inventory/adjustments', permission: 'adjustments.view' },
        ],
    },
    {
        title: 'Payroll',
        icon: 'payroll',
        items: [
            { label: 'Salaried Employees', href: '/payroll/employees', permission: 'payroll.view' },
            { label: 'Wage Workers', href: '/payroll/workers', permission: 'payroll.view' },
        ],
    },
    {
        title: 'Reports',
        icon: 'reports',
        items: [
            { label: 'Sales Report', href: '/reports/sales', permission: 'reports.sales' },
            { label: 'Profit & Loss', href: '/reports/profit-loss', permission: 'reports.profit_loss' },
        ],
    },
    {
        title: 'Settings',
        icon: 'settings',
        items: [
            { label: 'User Accounts', href: '/settings/users', permission: 'users.manage' },
            { label: 'Roles & Permissions', href: '/settings/roles', permission: 'roles.manage' },
            { label: 'General & Categories', href: '/settings/general', permission: 'settings.manage' },
            { label: 'Invoice Branding', href: '/settings/invoice', permission: 'settings.manage' },
            { label: 'Pathao Courier', href: '/settings/pathao', permission: 'settings.manage' },
            { label: 'Audit Trail', href: '/settings/audit-logs', permission: 'settings.manage' },
        ],
    },
];

const filteredNavGroups = computed(() => {
    return navGroups.map(group => {
        const items = group.items.filter(item => {
            if (item.permission && !hasPermission(item.permission)) {
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
                <div class="h-[56px] px-5 border-b border-line-200 flex items-center justify-between">
                    <Link href="/dashboard" class="flex items-center space-x-2 focus:outline-teal-700 overflow-hidden py-1">
                        <img
                            v-if="shopLogo"
                            :src="shopLogo"
                            :alt="shopName"
                            class="h-8 w-auto max-w-[170px] object-contain"
                        />
                        <template v-else>
                            <span class="text-xl font-black tracking-widest text-teal-700">{{ shopName }}</span>
                            <span class="text-xs font-semibold text-ink-500 uppercase tracking-wider">POS</span>
                        </template>
                    </Link>
                </div>

                <!-- Navigation List -->
                <div class="flex-1 overflow-y-auto p-3 space-y-1">
                    <!-- Dashboard -->
                    <Link
                        v-if="hasPermission('dashboard.view')"
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
                        v-if="hasPermission('expenses.view')"
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
                <Link href="/dashboard" class="mb-6 p-2 text-teal-700 font-black text-lg focus:outline-teal-700 flex items-center justify-center">
                    <img
                        v-if="shopLogo"
                        :src="shopLogo"
                        :alt="shopName"
                        class="w-10 h-10 object-contain"
                    />
                    <span v-else>{{ shopName }}</span>
                </Link>

                <div class="flex-1 space-y-4 flex flex-col items-center">
                    <Link
                        v-if="hasPermission('dashboard.view')"
                        href="/dashboard"
                        :class="['p-3 transition-colors', isActive('/dashboard') ? 'bg-teal-100 text-teal-700' : 'text-ink-700 hover:bg-teal-50']"
                        title="Dashboard"
                    >
                        <AppIcon name="dashboard" :size="24" />
                    </Link>

                    <Link
                        v-if="hasPermission('sales.view') || hasPermission('sales.create')"
                        href="/sales/create"
                        :class="['p-3 transition-colors', isActive('/sales') ? 'bg-teal-100 text-teal-700' : 'text-ink-700 hover:bg-teal-50']"
                        title="Sales"
                    >
                        <AppIcon name="sales" :size="24" />
                    </Link>

                    <Link
                        v-if="hasPermission('products.view') || hasPermission('leather.view') || hasPermission('accessories.view')"
                        href="/inventory/products"
                        :class="['p-3 transition-colors', isActive('/inventory') ? 'bg-teal-100 text-teal-700' : 'text-ink-700 hover:bg-teal-50']"
                        title="Inventory"
                    >
                        <AppIcon name="inventory" :size="24" />
                    </Link>

                    <Link
                        v-if="hasPermission('expenses.view')"
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
                    <div class="flex items-center space-x-2 sm:space-x-3">
                        <button
                            type="button"
                            class="sm:hidden p-2 text-ink-700 hover:bg-teal-50"
                            aria-label="Open navigation menu"
                            @click="isMobileMenuOpen = true"
                        >
                            <AppIcon name="menu" :size="20" />
                        </button>

                        <Link href="/dashboard" class="lg:hidden flex items-center space-x-1.5 focus:outline-teal-700">
                            <img
                                v-if="shopLogo"
                                :src="shopLogo"
                                :alt="shopName"
                                class="h-7 w-auto max-w-[110px] object-contain"
                            />
                            <span v-else class="font-bold text-teal-700 tracking-wider">{{ shopName }}</span>
                        </Link>

                        <!-- Mobile Global Search Icon Button -->
                        <button
                            type="button"
                            class="sm:hidden p-2 text-ink-700 hover:bg-teal-50 ml-1"
                            aria-label="Open Global Search"
                            title="Global Search (Ctrl+K)"
                            @click="openMobileSearch"
                        >
                            <AppIcon name="search" :size="18" />
                        </button>
                    </div>

                    <!-- Center: Desktop & Tablet Global Search (< 300ms SLA target, permission-aware) -->
                    <div class="hidden sm:flex flex-1 max-w-lg mx-6 relative" role="search">
                        <div class="w-full relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-ink-500">
                                <AppIcon v-if="!isSearching" name="search" :size="16" />
                                <span v-else class="inline-block w-4 h-4 border-2 border-teal-700 border-t-transparent animate-spin" />
                            </div>
                            <input
                                ref="searchInputRef"
                                v-model="globalSearch"
                                type="search"
                                role="combobox"
                                :aria-expanded="showSearchResults"
                                aria-label="Global search invoices, customers, products"
                                placeholder="Search invoice #, customer name/phone, product SKU..."
                                class="w-full h-[36px] pl-9 pr-14 text-xs text-ink-900 bg-page border border-line-300 focus:bg-surface focus:border-teal-700 rounded-none focus:outline-teal-700"
                                @input="onSearchInput"
                                @focus="showSearchResults = globalSearch.trim().length > 0"
                                @keydown="handleKeyDown"
                            />
                            <!-- Shortcut hint badge -->
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                                <span class="px-1.5 py-0.5 border border-line-300 bg-surface text-[10px] text-ink-500 font-mono tracking-tighter">Ctrl+K</span>
                            </div>
                        </div>

                        <!-- Backdrop Click-away -->
                        <div
                            v-if="showSearchResults"
                            class="fixed inset-0 z-40"
                            aria-hidden="true"
                            @click="showSearchResults = false"
                        />

                        <!-- Live Search Results Dropdown -->
                        <div
                            v-if="showSearchResults"
                            class="absolute left-0 right-0 top-[40px] bg-surface border border-line-300 shadow-lg z-50 max-h-[460px] overflow-y-auto divide-y divide-line-200 rounded-none"
                            role="listbox"
                        >
                            <!-- Search Status Header -->
                            <div class="px-3 py-1.5 bg-sunken text-[10px] font-bold uppercase tracking-wider text-ink-600 flex justify-between items-center">
                                <span>Search Results</span>
                                <span class="text-ink-500 font-mono lowercase">
                                    <template v-if="isSearching">Searching...</template>
                                    <template v-else>{{ searchElapsedMs }}ms · {{ searchTotalCount }} matches</template>
                                </span>
                            </div>

                            <!-- Empty State -->
                            <div
                                v-if="!isSearching && searchTotalCount === 0"
                                class="px-4 py-6 text-center text-xs text-ink-600"
                            >
                                No matching invoices, customers, or products found for “<strong>{{ globalSearch }}</strong>”.
                            </div>

                            <!-- 1. Invoices Results Section -->
                            <div v-if="searchInvoices.length > 0">
                                <div class="px-3 py-1 bg-surface text-[10px] font-bold uppercase text-teal-800 tracking-wider border-b border-line-100 flex items-center gap-1.5">
                                    <AppIcon name="invoices" :size="12" class="text-teal-700" />
                                    <span>Invoices ({{ searchInvoices.length }})</span>
                                </div>
                                <div
                                    v-for="inv in searchInvoices"
                                    :key="`inv-${inv.id}`"
                                    class="px-3 py-2 hover:bg-teal-50 cursor-pointer flex items-center justify-between transition-colors border-b border-line-100 last:border-b-0"
                                    :class="{ 'bg-teal-50': flattenedResults[highlightedIndex]?.id === `inv-${inv.id}` }"
                                    @click="selectResultUrl(inv.url)"
                                >
                                    <div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-[9px] font-bold px-1.5 py-0.2 bg-teal-100 text-teal-800 border border-teal-200">
                                                INVOICE
                                            </span>
                                            <span class="text-xs font-bold text-ink-900 font-mono">{{ inv.invoice_no }}</span>
                                            <span v-if="inv.sale_date" class="text-[10px] text-ink-500 font-mono">({{ inv.sale_date }})</span>
                                        </div>
                                        <div class="text-[11px] text-ink-700 mt-0.5">
                                            {{ inv.customer_name }} <span v-if="inv.customer_phone" class="text-ink-500 font-mono">· {{ inv.customer_phone }}</span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xs font-bold text-ink-900 tabular-nums">
                                            {{ formatMoney(inv.grand_total) }}
                                        </div>
                                        <div class="text-[10px] uppercase font-semibold" :class="inv.payment_status === 'paid' ? 'text-teal-700' : 'text-danger'">
                                            {{ inv.payment_status }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 2. Customers Results Section -->
                            <div v-if="searchCustomers.length > 0">
                                <div class="px-3 py-1 bg-surface text-[10px] font-bold uppercase text-ink-700 tracking-wider border-b border-line-100 flex items-center gap-1.5">
                                    <AppIcon name="users" :size="12" class="text-ink-600" />
                                    <span>Customers ({{ searchCustomers.length }})</span>
                                </div>
                                <div
                                    v-for="c in searchCustomers"
                                    :key="`cust-${c.id}`"
                                    class="px-3 py-2 hover:bg-teal-50 cursor-pointer flex items-center justify-between transition-colors border-b border-line-100 last:border-b-0"
                                    :class="{ 'bg-teal-50': flattenedResults[highlightedIndex]?.id === `cust-${c.id}` }"
                                    @click="selectResultUrl(c.url)"
                                >
                                    <div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-[9px] font-bold px-1.5 py-0.2 bg-[#EDE9FE] text-[#6D28D9] border border-[#DDD6FE]">
                                                CUSTOMER
                                            </span>
                                            <span class="text-xs font-bold text-ink-900">{{ c.name }}</span>
                                        </div>
                                        <div class="text-[11px] text-ink-600 font-mono mt-0.5">
                                            {{ c.phone }} <span v-if="c.city_name" class="text-ink-500 font-sans">· {{ c.city_name }}</span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-[11px] text-teal-700 font-semibold underline">View Profile</span>
                                    </div>
                                </div>
                            </div>

                            <!-- 3. Products Results Section (Strictly NO cost price) -->
                            <div v-if="searchProducts.length > 0">
                                <div class="px-3 py-1 bg-surface text-[10px] font-bold uppercase text-ink-700 tracking-wider border-b border-line-100 flex items-center gap-1.5">
                                    <AppIcon name="inventory" :size="12" class="text-ink-600" />
                                    <span>Products ({{ searchProducts.length }})</span>
                                </div>
                                <div
                                    v-for="p in searchProducts"
                                    :key="`prod-${p.id}`"
                                    class="px-3 py-2 hover:bg-teal-50 cursor-pointer flex items-center justify-between transition-colors border-b border-line-100 last:border-b-0"
                                    :class="{ 'bg-teal-50': flattenedResults[highlightedIndex]?.id === `prod-${p.id}` }"
                                    @click="selectResultUrl(p.url)"
                                >
                                    <div>
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-[9px] font-bold px-1.5 py-0.2 bg-amber-100 text-amber-800 border border-amber-200">
                                                PRODUCT
                                            </span>
                                            <span class="text-xs font-bold text-ink-900">{{ p.name }}</span>
                                        </div>
                                        <div class="text-[11px] text-ink-600 font-mono mt-0.5">
                                            SKU: {{ p.sku }}
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xs font-bold text-ink-900 tabular-nums">
                                            {{ formatMoney(p.selling_price) }}
                                        </div>
                                        <div class="text-[10px] font-semibold flex items-center gap-1 justify-end" :class="p.is_low_stock ? 'text-danger' : 'text-teal-700'">
                                            <span>{{ p.stock_qty }} {{ p.unit }}</span>
                                            <span>· {{ p.is_low_stock ? 'Low Stock' : 'In Stock' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Keyboard hint footer -->
                            <div class="px-3 py-1 bg-sunken text-[10px] text-ink-500 font-mono flex items-center justify-between border-t border-line-200">
                                <span>↑↓ to navigate · Enter to open</span>
                                <span>Esc to dismiss</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Clock, New Sale Shortcut & User Actions -->
                    <div class="flex items-center space-x-2 sm:space-x-3">
                        <!-- Live Bangladesh Digital Clock -->
                        <RealtimeClock />

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

        <!-- MOBILE FULL-SCREEN GLOBAL SEARCH SHEET (< 640px) -->
        <div
            v-if="isMobileSearchOpen"
            class="sm:hidden fixed inset-0 z-50 bg-surface flex flex-col"
            role="dialog"
            aria-modal="true"
            aria-label="Global Search"
        >
            <!-- Mobile Header with Search Input (56px) -->
            <div class="h-[56px] px-3 border-b border-line-300 flex items-center gap-2 bg-surface">
                <button
                    type="button"
                    class="p-2 text-ink-700 hover:bg-teal-50"
                    aria-label="Close search"
                    @click="isMobileSearchOpen = false"
                >
                    <AppIcon name="close" :size="20" />
                </button>
                <div class="relative flex-1">
                    <input
                        ref="mobileSearchInputRef"
                        v-model="globalSearch"
                        type="search"
                        placeholder="Search invoice, customer, product..."
                        class="w-full h-[40px] pl-3 pr-8 text-sm text-ink-900 bg-page border border-line-300 rounded-none focus:outline-teal-700"
                        @input="onSearchInput"
                    />
                    <button
                        v-if="globalSearch"
                        type="button"
                        class="absolute inset-y-0 right-0 flex items-center pr-2 text-ink-500"
                        aria-label="Clear search query"
                        @click="globalSearch = ''; onSearchInput();"
                    >
                        <AppIcon name="close" :size="16" />
                    </button>
                </div>
                <button
                    type="button"
                    class="text-xs text-teal-700 font-bold px-2 py-1"
                    @click="isMobileSearchOpen = false"
                >
                    Done
                </button>
            </div>

            <!-- Mobile Results List -->
            <div class="flex-1 overflow-y-auto divide-y divide-line-200 p-2">
                <div v-if="isSearching" class="p-6 text-center text-xs text-ink-500">
                    <span class="inline-block w-4 h-4 border-2 border-teal-700 border-t-transparent animate-spin mb-2 mr-2" />
                    Searching...
                </div>
                <div v-else-if="globalSearch.trim() && searchTotalCount === 0" class="p-6 text-center text-xs text-ink-600">
                    No results found for “{{ globalSearch }}”.
                </div>
                <div v-else-if="!globalSearch.trim()" class="p-6 text-center text-xs text-ink-500">
                    Type invoice number, customer phone/name, or product SKU to search.
                </div>

                <!-- Invoices -->
                <div v-if="searchInvoices.length > 0" class="py-2">
                    <div class="px-2 py-1 text-[10px] font-bold uppercase text-teal-800">Invoices</div>
                    <div
                        v-for="inv in searchInvoices"
                        :key="`m-inv-${inv.id}`"
                        class="p-3 hover:bg-teal-50 active:bg-teal-100 flex items-center justify-between cursor-pointer border-b border-line-100 last:border-b-0"
                        @click="selectResultUrl(inv.url)"
                    >
                        <div>
                            <div class="text-xs font-bold font-mono text-teal-800">{{ inv.invoice_no }}</div>
                            <div class="text-xs text-ink-700 mt-0.5">{{ inv.customer_name }} ({{ inv.customer_phone }})</div>
                        </div>
                        <div class="text-right">
                            <div class="text-xs font-bold text-ink-900 tabular-nums">{{ formatMoney(inv.grand_total) }}</div>
                            <div class="text-[10px] font-semibold uppercase text-ink-600">{{ inv.payment_status }}</div>
                        </div>
                    </div>
                </div>

                <!-- Customers -->
                <div v-if="searchCustomers.length > 0" class="py-2">
                    <div class="px-2 py-1 text-[10px] font-bold uppercase text-ink-700">Customers</div>
                    <div
                        v-for="c in searchCustomers"
                        :key="`m-cust-${c.id}`"
                        class="p-3 hover:bg-teal-50 active:bg-teal-100 flex items-center justify-between cursor-pointer border-b border-line-100 last:border-b-0"
                        @click="selectResultUrl(c.url)"
                    >
                        <div>
                            <div class="text-xs font-bold text-ink-900">{{ c.name }}</div>
                            <div class="text-xs text-ink-600 font-mono">{{ c.phone }}</div>
                        </div>
                        <span class="text-xs text-teal-700 font-bold">Select</span>
                    </div>
                </div>

                <!-- Products -->
                <div v-if="searchProducts.length > 0" class="py-2">
                    <div class="px-2 py-1 text-[10px] font-bold uppercase text-amber-800">Products</div>
                    <div
                        v-for="p in searchProducts"
                        :key="`m-prod-${p.id}`"
                        class="p-3 hover:bg-teal-50 active:bg-teal-100 flex items-center justify-between cursor-pointer border-b border-line-100 last:border-b-0"
                        @click="selectResultUrl(p.url)"
                    >
                        <div>
                            <div class="text-xs font-bold text-ink-900">{{ p.name }}</div>
                            <div class="text-xs text-ink-600 font-mono">SKU: {{ p.sku }}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-xs font-bold text-ink-900 tabular-nums">{{ formatMoney(p.selling_price) }}</div>
                            <div class="text-[10px] font-semibold" :class="p.is_low_stock ? 'text-danger' : 'text-teal-700'">
                                {{ p.stock_qty }} {{ p.unit }}
                            </div>
                        </div>
                    </div>
                </div>
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
                v-if="hasPermission('sales.create')"
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
                v-if="hasPermission('sales.view')"
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
                v-if="hasPermission('products.view')"
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
                v-if="hasPermission('expenses.view')"
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
                        v-if="hasPermission('dashboard.view')"
                        href="/dashboard"
                        class="p-3 bg-page border border-line-200 font-semibold flex items-center space-x-2"
                        @click="isMobileMoreOpen = false"
                    >
                        <AppIcon name="dashboard" :size="18" class="text-teal-700" />
                        <span>Dashboard</span>
                    </Link>
                    <Link
                        v-if="hasPermission('customers.view')"
                        href="/sales/customers"
                        class="p-3 bg-page border border-line-200 font-semibold flex items-center space-x-2"
                        @click="isMobileMoreOpen = false"
                    >
                        <AppIcon name="customers" :size="18" class="text-teal-700" />
                        <span>Customers</span>
                    </Link>
                    <Link
                        v-if="hasPermission('courier.view')"
                        href="/sales/courier"
                        class="p-3 bg-page border border-line-200 font-semibold flex items-center space-x-2"
                        @click="isMobileMoreOpen = false"
                    >
                        <AppIcon name="courier" :size="18" class="text-teal-700" />
                        <span>Pathao Orders</span>
                    </Link>
                    <Link
                        v-if="hasPermission('payroll.view')"
                        href="/payroll/employees"
                        class="p-3 bg-page border border-line-200 font-semibold flex items-center space-x-2"
                        @click="isMobileMoreOpen = false"
                    >
                        <AppIcon name="payroll" :size="18" class="text-teal-700" />
                        <span>Payroll</span>
                    </Link>
                    <Link
                        v-if="hasPermission('reports.sales')"
                        href="/reports/sales"
                        class="p-3 bg-page border border-line-200 font-semibold flex items-center space-x-2"
                        @click="isMobileMoreOpen = false"
                    >
                        <AppIcon name="reports" :size="18" class="text-teal-700" />
                        <span>Sales Report</span>
                    </Link>
                    <Link
                        v-if="hasPermission('reports.profit_loss')"
                        href="/reports/profit-loss"
                        class="p-3 bg-page border border-line-200 font-semibold flex items-center space-x-2"
                        @click="isMobileMoreOpen = false"
                    >
                        <AppIcon name="receipt" :size="18" class="text-teal-700" />
                        <span>Profit & Loss</span>
                    </Link>
                    <Link
                        v-if="hasPermission('settings.manage') || hasPermission('users.manage') || hasPermission('roles.manage')"
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
                    <div class="flex items-center space-x-2">
                        <img
                            v-if="shopLogo"
                            :src="shopLogo"
                            :alt="shopName"
                            class="h-8 w-auto max-w-[160px] object-contain"
                        />
                        <span v-else class="text-xl font-black text-teal-700">{{ shopName }} POS</span>
                    </div>
                    <button type="button" class="p-1 text-ink-600" @click="isMobileMenuOpen = false">
                        <AppIcon name="close" :size="20" />
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto space-y-2 text-sm font-medium">
                    <Link
                        v-if="hasPermission('dashboard.view')"
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
                        v-if="hasPermission('expenses.view')"
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
