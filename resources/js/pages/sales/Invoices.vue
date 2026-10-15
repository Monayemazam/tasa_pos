<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router, Link, useForm } from '@inertiajs/vue3';
import PageHeader from '@/components/tasa/PageHeader.vue';
import AppButton from '@/components/tasa/AppButton.vue';
import AppIcon from '@/components/tasa/AppIcon.vue';
import StatusBadge from '@/components/tasa/StatusBadge.vue';
import TablePagination from '@/components/tasa/TablePagination.vue';
import { formatMoney } from '@/lib/formatters';

interface SaleItemDto {
    id: number;
    name: string;
    qty: number;
    returned_qty: number;
    unreturned_qty: number;
    unit_price: number;
    line_total: number;
    is_custom: boolean;
}

interface InvoiceListItem {
    id: number;
    invoice_no: string;
    sale_date: string;
    customer_name: string;
    customer_phone: string;
    customer_address?: string;
    grand_total: number;
    paid_total: number;
    due_amount: number;
    net_due_amount: number;
    payment_status: 'paid' | 'partial' | 'unpaid';
    status: 'active' | 'cancelled' | 'partially_returned' | 'returned';
    courier_mode: 'pathao' | 'none';
    courier_status: string | null;
    consignment_id: string | null;
    cancel_reason: string | null;
    items: SaleItemDto[];
}

interface PaginatedInvoices {
    data: InvoiceListItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

const props = defineProps<{
    invoices: PaginatedInvoices;
    filters: {
        search?: string;
        date_preset?: string;
        date_from?: string;
        date_to?: string;
        payment_status?: string;
        status?: string;
        courier_status?: string;
        courier_mode?: string;
        payment_method?: string;
    };
    can: {
        cancel: boolean;
        return: boolean;
        add_payment: boolean;
    };
}>();

// Filter reactive state
const search = ref(props.filters.search || '');
const datePreset = ref(props.filters.date_preset || '');
const dateFrom = ref(props.filters.date_from || '');
const dateTo = ref(props.filters.date_to || '');
const paymentStatus = ref(props.filters.payment_status || 'all');
const saleStatus = ref(props.filters.status || 'all');
const courierStatus = ref(props.filters.courier_status || 'all');
const courierMode = ref(props.filters.courier_mode || 'all');
const paymentMethod = ref(props.filters.payment_method || 'all');

function onDatePresetChange() {
    if (datePreset.value && datePreset.value !== 'custom' && datePreset.value !== 'all') {
        dateFrom.value = '';
        dateTo.value = '';
    }
    applyFilters();
}

function applyFilters() {
    router.get(
        '/sales/invoices',
        {
            search: search.value || undefined,
            date_preset: datePreset.value && datePreset.value !== 'all' ? datePreset.value : undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
            payment_status: paymentStatus.value !== 'all' ? paymentStatus.value : undefined,
            status: saleStatus.value !== 'all' ? saleStatus.value : undefined,
            courier_status: courierStatus.value !== 'all' ? courierStatus.value : undefined,
            courier_mode: courierMode.value !== 'all' ? courierMode.value : undefined,
            payment_method: paymentMethod.value !== 'all' ? paymentMethod.value : undefined,
        },
        { preserveState: true, replace: true }
    );
}

interface InvoiceFilterChip {
    key: string;
    label: string;
    value: string;
}

const isMobileFilterOpen = ref(false);

const activeFilterChips = computed<InvoiceFilterChip[]>(() => {
    const chips: InvoiceFilterChip[] = [];
    if (search.value.trim()) {
        chips.push({ key: 'search', label: 'Search', value: search.value.trim() });
    }
    if (datePreset.value && datePreset.value !== 'all') {
        const labels: Record<string, string> = {
            today: 'Today',
            yesterday: 'Yesterday',
            last_7_days: 'Last 7 Days',
            this_month: 'This Month',
            last_month: 'Last Month',
            custom: dateFrom.value && dateTo.value ? `${dateFrom.value} to ${dateTo.value}` : 'Custom Range',
        };
        chips.push({ key: 'date_preset', label: 'Date', value: labels[datePreset.value] || datePreset.value });
    }
    if (paymentStatus.value && paymentStatus.value !== 'all') {
        chips.push({ key: 'payment_status', label: 'Payment', value: paymentStatus.value.toUpperCase() });
    }
    if (saleStatus.value && saleStatus.value !== 'all') {
        chips.push({ key: 'status', label: 'Sale Status', value: saleStatus.value.replace('_', ' ').toUpperCase() });
    }
    if (paymentMethod.value && paymentMethod.value !== 'all') {
        chips.push({ key: 'payment_method', label: 'Method', value: paymentMethod.value.toUpperCase() });
    }
    if (courierStatus.value && courierStatus.value !== 'all') {
        chips.push({ key: 'courier_status', label: 'Courier', value: courierStatus.value.replace('_', ' ').toUpperCase() });
    }
    return chips;
});

function removeFilter(key: string) {
    if (key === 'search') search.value = '';
    if (key === 'date_preset') {
        datePreset.value = '';
        dateFrom.value = '';
        dateTo.value = '';
    }
    if (key === 'payment_status') paymentStatus.value = 'all';
    if (key === 'status') saleStatus.value = 'all';
    if (key === 'payment_method') paymentMethod.value = 'all';
    if (key === 'courier_status') courierStatus.value = 'all';
    applyFilters();
}

function resetFilters() {
    search.value = '';
    datePreset.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    paymentStatus.value = 'all';
    saleStatus.value = 'all';
    courierStatus.value = 'all';
    courierMode.value = 'all';
    paymentMethod.value = 'all';
    router.get('/sales/invoices');
}

function handlePrint(id: number) {
    window.open(`/sales/invoices/${id}/print`, '_blank');
}

// ==========================================
// ADD PAYMENT MODAL STATE
// ==========================================
const isPaymentModalOpen = ref(false);
const activePaymentSale = ref<InvoiceListItem | null>(null);

const paymentForm = useForm({
    amount: 0,
    paid_on: new Date().toISOString().substring(0, 10),
    method: 'cash',
    reference: '',
    note: '',
});

function openPaymentModal(sale: InvoiceListItem) {
    activePaymentSale.value = sale;
    paymentForm.amount = sale.net_due_amount > 0 ? sale.net_due_amount : sale.due_amount;
    paymentForm.paid_on = new Date().toISOString().substring(0, 10);
    paymentForm.method = 'cash';
    paymentForm.reference = '';
    paymentForm.note = '';
    paymentForm.clearErrors();
    isPaymentModalOpen.value = true;
}

function closePaymentModal() {
    isPaymentModalOpen.value = false;
    activePaymentSale.value = null;
}

function submitPayment() {
    if (!activePaymentSale.value) return;
    paymentForm.post(`/sales/invoices/${activePaymentSale.value.id}/payments`, {
        onSuccess: () => closePaymentModal(),
    });
}

// ==========================================
// CANCEL SALE MODAL STATE
// ==========================================
const isCancelModalOpen = ref(false);
const activeCancelSale = ref<InvoiceListItem | null>(null);

const cancelForm = useForm({
    reason: '',
});

function openCancelModal(sale: InvoiceListItem) {
    activeCancelSale.value = sale;
    cancelForm.reason = '';
    cancelForm.clearErrors();
    isCancelModalOpen.value = true;
}

function closeCancelModal() {
    isCancelModalOpen.value = false;
    activeCancelSale.value = null;
}

function submitCancel() {
    if (!activeCancelSale.value) return;
    cancelForm.post(`/sales/invoices/${activeCancelSale.value.id}/cancel`, {
        onSuccess: () => closeCancelModal(),
    });
}

// ==========================================
// RETURN SALE MODAL STATE
// ==========================================
const isReturnModalOpen = ref(false);
const activeReturnSale = ref<InvoiceListItem | null>(null);

const returnForm = useForm<{
    return_date: string;
    reason: string;
    items: Array<{
        sale_item_id: number;
        name: string;
        unit_price: number;
        qty_sold: number;
        already_returned: number;
        unreturned_qty: number;
        qty: number;
        is_custom: boolean;
    }>;
    refunded_amount: number;
    restock: boolean;
    return_courier_cost: number;
    return_courier_payment_method: string;
}>({
    return_date: new Date().toISOString().substring(0, 10),
    reason: '',
    items: [],
    refunded_amount: 0,
    restock: true,
    return_courier_cost: 0,
    return_courier_payment_method: 'cash',
});

function openReturnModal(sale: InvoiceListItem) {
    activeReturnSale.value = sale;
    returnForm.return_date = new Date().toISOString().substring(0, 10);
    returnForm.reason = '';
    returnForm.restock = true;
    returnForm.refunded_amount = 0;
    returnForm.return_courier_cost = 0;
    returnForm.return_courier_payment_method = 'cash';
    returnForm.clearErrors();

    // Map sale items for return
    returnForm.items = sale.items.map((i) => ({
        sale_item_id: i.id,
        name: i.name,
        unit_price: i.unit_price,
        qty_sold: i.qty,
        already_returned: i.returned_qty,
        unreturned_qty: i.unreturned_qty,
        qty: 0,
        is_custom: i.is_custom,
    }));

    isReturnModalOpen.value = true;
}

function closeReturnModal() {
    isReturnModalOpen.value = false;
    activeReturnSale.value = null;
}

const computedReturnValue = computed(() => {
    return returnForm.items.reduce((sum, item) => sum + (item.qty * item.unit_price), 0);
});

function submitReturn() {
    if (!activeReturnSale.value) return;

    const selectedItems = returnForm.items
        .filter((i) => i.qty > 0)
        .map((i) => ({
            sale_item_id: i.sale_item_id,
            qty: i.qty,
        }));

    if (selectedItems.length === 0) {
        alert('Please specify a return quantity greater than 0 for at least one item.');
        return;
    }

    returnForm.transform((data) => ({
        ...data,
        items: selectedItems,
    })).post(`/sales/invoices/${activeReturnSale.value.id}/return`, {
        onSuccess: () => closeReturnModal(),
    });
}

function handleRetryCourier(sale: InvoiceListItem) {
    router.post(`/sales/invoices/${sale.id}/retry-courier`);
}
</script>

<template>
    <Head title="Invoices / Sales List — Tasa POS" />

        <div class="max-w-[1560px] mx-auto space-y-5">
            <!-- Header -->
            <PageHeader
                title="Invoices & Sales History"
                subtitle="Complete sales register with sequential monthly numbering, payment records, cancel & return controls"
            >
                <template #actions>
                    <Link href="/sales/create">
                        <AppButton variant="primary" size="sm">
                            <AppIcon name="plus" :size="16" class="mr-1" />
                            New Sale (POS)
                        </AppButton>
                    </Link>
                </template>
            </PageHeader>

            <!-- Mobile Filter Trigger Button (< 640px) -->
            <div class="sm:hidden flex items-center gap-2">
                <button
                    type="button"
                    class="flex-1 flex items-center justify-center gap-2 h-10 px-3 bg-white border border-[#D5DCDB] text-xs font-bold text-[#111827] active:bg-[#FAFBFB]"
                    @click="isMobileFilterOpen = true"
                >
                    <AppIcon name="filter" :size="16" />
                    <span>Filters</span>
                    <span
                        v-if="activeFilterChips.length > 0"
                        class="px-1.5 py-0.5 bg-[#0B6E5E] text-white text-[10px] font-bold"
                    >
                        {{ activeFilterChips.length }}
                    </span>
                </button>
                <button
                    v-if="activeFilterChips.length > 0"
                    type="button"
                    class="h-10 px-3 bg-white border border-[#D5DCDB] text-xs text-danger font-semibold"
                    @click="resetFilters"
                >
                    Reset
                </button>
            </div>

            <!-- Desktop Filters Bar (hidden on mobile, visible on sm and up) -->
            <div class="hidden sm:block bg-white border border-[#D5DCDB] p-4 space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-6 lg:grid-cols-12 gap-3">
                    <!-- Search Input -->
                    <div class="sm:col-span-2 md:col-span-3 lg:col-span-4">
                        <label class="block text-[11px] font-bold uppercase text-[#4A5957] mb-1">
                            Search Invoice / Customer
                        </label>
                        <div class="relative">
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Search invoice #, customer name, phone..."
                                class="w-full h-9 pl-8 pr-3 text-xs bg-[#FAFBFB] border border-[#D5DCDB] rounded-none focus:border-[#0B6E5E] focus:outline-none"
                                @keyup.enter="applyFilters"
                            />
                            <AppIcon
                                name="search"
                                :size="14"
                                class="absolute left-2.5 top-2.5 text-[#728481]"
                            />
                        </div>
                    </div>

                    <!-- Date Preset -->
                    <div class="sm:col-span-2 md:col-span-3 lg:col-span-3">
                        <label class="block text-[11px] font-bold uppercase text-[#4A5957] mb-1">
                            Date Preset
                        </label>
                        <select
                            v-model="datePreset"
                            class="w-full h-9 px-2 text-xs bg-[#FAFBFB] border border-[#D5DCDB] rounded-none focus:border-[#0B6E5E] focus:outline-none"
                            @change="onDatePresetChange"
                        >
                            <option value="">All Dates</option>
                            <option value="today">Today</option>
                            <option value="yesterday">Yesterday</option>
                            <option value="last_7_days">Last 7 Days</option>
                            <option value="this_month">This Month</option>
                            <option value="last_month">Last Month</option>
                            <option value="custom">Custom Range...</option>
                        </select>
                    </div>

                    <!-- Payment Status -->
                    <div class="sm:col-span-1 md:col-span-2 lg:col-span-2">
                        <label class="block text-[11px] font-bold uppercase text-[#4A5957] mb-1">
                            Payment Status
                        </label>
                        <select
                            v-model="paymentStatus"
                            class="w-full h-9 px-2 text-xs bg-[#FAFBFB] border border-[#D5DCDB] rounded-none focus:border-[#0B6E5E] focus:outline-none"
                            @change="applyFilters"
                        >
                            <option value="all">All</option>
                            <option value="paid">Paid</option>
                            <option value="partial">Partial</option>
                            <option value="unpaid">Unpaid</option>
                        </select>
                    </div>

                    <!-- Sale Status -->
                    <div class="sm:col-span-1 md:col-span-2 lg:col-span-3">
                        <label class="block text-[11px] font-bold uppercase text-[#4A5957] mb-1">
                            Sale Status
                        </label>
                        <select
                            v-model="saleStatus"
                            class="w-full h-9 px-2 text-xs bg-[#FAFBFB] border border-[#D5DCDB] rounded-none focus:border-[#0B6E5E] focus:outline-none"
                            @change="applyFilters"
                        >
                            <option value="all">All Sales</option>
                            <option value="active">Active Only</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="partially_returned">Partially Returned</option>
                            <option value="returned">Returned</option>
                        </select>
                    </div>
                </div>

                <!-- Secondary Filter Row -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3 pt-2 border-t border-[#EDF1F0]">
                    <!-- Payment Method -->
                    <div>
                        <label class="block text-[11px] font-bold uppercase text-[#4A5957] mb-1">
                            Payment Method
                        </label>
                        <select
                            v-model="paymentMethod"
                            class="w-full h-9 px-2 text-xs bg-[#FAFBFB] border border-[#D5DCDB] rounded-none focus:border-[#0B6E5E] focus:outline-none"
                            @change="applyFilters"
                        >
                            <option value="all">All Methods</option>
                            <option value="cash">Cash</option>
                            <option value="bkash">bKash</option>
                            <option value="cod">Cash on Delivery (COD)</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                    </div>

                    <!-- Courier Status -->
                    <div>
                        <label class="block text-[11px] font-bold uppercase text-[#4A5957] mb-1">
                            Courier Status
                        </label>
                        <select
                            v-model="courierStatus"
                            class="w-full h-9 px-2 text-xs bg-[#FAFBFB] border border-[#D5DCDB] rounded-none focus:border-[#0B6E5E] focus:outline-none"
                            @change="applyFilters"
                        >
                            <option value="all">All Couriers</option>
                            <option value="none">Hand-delivered</option>
                            <option value="queued">Queued</option>
                            <option value="created">Created</option>
                            <option value="picked_up">Picked Up / Dispatched</option>
                            <option value="in_transit">In Transit</option>
                            <option value="delivered">Delivered</option>
                            <option value="failed">Failed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>

                    <!-- Custom Date From -->
                    <div v-if="datePreset === 'custom' || dateFrom">
                        <label class="block text-[11px] font-bold uppercase text-[#4A5957] mb-1">
                            From Date
                        </label>
                        <input
                            v-model="dateFrom"
                            type="date"
                            class="w-full h-9 px-2 text-xs bg-[#FAFBFB] border border-[#D5DCDB] rounded-none focus:border-[#0B6E5E] focus:outline-none"
                            @change="applyFilters"
                        />
                    </div>

                    <!-- Custom Date To -->
                    <div v-if="datePreset === 'custom' || dateTo">
                        <label class="block text-[11px] font-bold uppercase text-[#4A5957] mb-1">
                            To Date
                        </label>
                        <input
                            v-model="dateTo"
                            type="date"
                            class="w-full h-9 px-2 text-xs bg-[#FAFBFB] border border-[#D5DCDB] rounded-none focus:border-[#0B6E5E] focus:outline-none"
                            @change="applyFilters"
                        />
                    </div>

                    <!-- Filter Actions -->
                    <div class="flex items-end gap-2" :class="datePreset !== 'custom' && !dateFrom ? 'md:col-start-5' : ''">
                        <AppButton
                            variant="secondary"
                            size="sm"
                            class="flex-1 h-9"
                            @click="applyFilters"
                        >
                            Filter
                        </AppButton>
                        <button
                            type="button"
                            class="h-9 px-3 text-xs text-[#52605C] hover:text-[#0F1B1A] underline"
                            @click="resetFilters"
                        >
                            Clear
                        </button>
                    </div>
                </div>
            </div>

            <!-- Active Filter Chips Row -->
            <div
                v-if="activeFilterChips.length > 0"
                class="flex flex-wrap items-center gap-2 pt-1 text-xs"
            >
                <span class="text-[#4A5957] font-semibold">Active filters:</span>
                <span
                    v-for="chip in activeFilterChips"
                    :key="chip.key"
                    class="inline-flex items-center px-2 py-1 bg-[#E6F4F1] text-[#0B6E5E] font-medium border border-[#BCE1DA]"
                >
                    <span class="mr-1.5">{{ chip.label }}: <strong>{{ chip.value }}</strong></span>
                    <button
                        type="button"
                        class="hover:text-[#063B32] font-bold p-0.5"
                        :aria-label="`Remove filter ${chip.label}`"
                        @click="removeFilter(chip.key)"
                    >
                        <AppIcon name="close" :size="12" />
                    </button>
                </span>
                <button
                    type="button"
                    class="text-xs text-[#4A5957] hover:text-danger font-semibold underline ml-1"
                    @click="resetFilters"
                >
                    Clear all
                </button>
            </div>

            <!-- FULL-SCREEN MOBILE FILTER SHEET (< 640px) -->
            <div
                v-if="isMobileFilterOpen"
                class="sm:hidden fixed inset-0 z-50 bg-white flex flex-col"
                role="dialog"
                aria-modal="true"
                aria-label="Filter Invoices"
            >
                <!-- Sticky Header -->
                <div class="h-[56px] px-4 border-b border-[#D5DCDB] flex items-center justify-between bg-white sticky top-0 z-10">
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-bold text-[#111827]">Filter Invoices</h2>
                        <span
                            v-if="activeFilterChips.length > 0"
                            class="px-2 py-0.5 bg-[#E6F4F1] text-[#0B6E5E] text-xs font-bold"
                        >
                            {{ activeFilterChips.length }} active
                        </span>
                    </div>
                    <button
                        type="button"
                        class="p-2 text-[#4A5957] hover:bg-[#FAFBFB]"
                        aria-label="Close filter sheet"
                        @click="isMobileFilterOpen = false"
                    >
                        <AppIcon name="close" :size="20" />
                    </button>
                </div>

                <!-- Scrollable Body with Filter Controls -->
                <div class="flex-1 overflow-y-auto p-4 space-y-4">
                    <!-- Search Input -->
                    <div>
                        <label class="block text-xs font-bold uppercase text-[#4A5957] mb-1">
                            Search Invoice # / Customer
                        </label>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Invoice #, name, phone..."
                            class="w-full h-11 px-3 text-sm bg-[#FAFBFB] border border-[#D5DCDB] rounded-none focus:border-[#0B6E5E] focus:outline-none"
                        />
                    </div>

                    <!-- Date Preset -->
                    <div>
                        <label class="block text-xs font-bold uppercase text-[#4A5957] mb-1">
                            Date Range Preset
                        </label>
                        <select
                            v-model="datePreset"
                            class="w-full h-11 px-3 text-sm bg-[#FAFBFB] border border-[#D5DCDB] rounded-none focus:border-[#0B6E5E] focus:outline-none"
                            @change="onDatePresetChange"
                        >
                            <option value="">All Dates</option>
                            <option value="today">Today</option>
                            <option value="yesterday">Yesterday</option>
                            <option value="last_7_days">Last 7 Days</option>
                            <option value="this_month">This Month</option>
                            <option value="last_month">Last Month</option>
                            <option value="custom">Custom Range...</option>
                        </select>
                    </div>

                    <!-- Custom Date From/To -->
                    <div v-if="datePreset === 'custom' || dateFrom || dateTo" class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold uppercase text-[#4A5957] mb-1">From</label>
                            <input
                                v-model="dateFrom"
                                type="date"
                                class="w-full h-11 px-2 text-sm bg-[#FAFBFB] border border-[#D5DCDB] rounded-none"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-[#4A5957] mb-1">To</label>
                            <input
                                v-model="dateTo"
                                type="date"
                                class="w-full h-11 px-2 text-sm bg-[#FAFBFB] border border-[#D5DCDB] rounded-none"
                            />
                        </div>
                    </div>

                    <!-- Payment Status -->
                    <div>
                        <label class="block text-xs font-bold uppercase text-[#4A5957] mb-1">
                            Payment Status
                        </label>
                        <select
                            v-model="paymentStatus"
                            class="w-full h-11 px-3 text-sm bg-[#FAFBFB] border border-[#D5DCDB] rounded-none focus:border-[#0B6E5E] focus:outline-none"
                        >
                            <option value="all">All Payment Statuses</option>
                            <option value="paid">Paid</option>
                            <option value="partial">Partial</option>
                            <option value="unpaid">Unpaid</option>
                        </select>
                    </div>

                    <!-- Sale Status -->
                    <div>
                        <label class="block text-xs font-bold uppercase text-[#4A5957] mb-1">
                            Sale Status
                        </label>
                        <select
                            v-model="saleStatus"
                            class="w-full h-11 px-3 text-sm bg-[#FAFBFB] border border-[#D5DCDB] rounded-none focus:border-[#0B6E5E] focus:outline-none"
                        >
                            <option value="all">All Sales</option>
                            <option value="active">Active Only</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="partially_returned">Partially Returned</option>
                            <option value="returned">Returned</option>
                        </select>
                    </div>

                    <!-- Payment Method -->
                    <div>
                        <label class="block text-xs font-bold uppercase text-[#4A5957] mb-1">
                            Payment Method
                        </label>
                        <select
                            v-model="paymentMethod"
                            class="w-full h-11 px-3 text-sm bg-[#FAFBFB] border border-[#D5DCDB] rounded-none focus:border-[#0B6E5E] focus:outline-none"
                        >
                            <option value="all">All Methods</option>
                            <option value="cash">Cash</option>
                            <option value="bkash">bKash</option>
                            <option value="cod">Cash on Delivery (COD)</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                    </div>

                    <!-- Courier Status -->
                    <div>
                        <label class="block text-xs font-bold uppercase text-[#4A5957] mb-1">
                            Courier Status
                        </label>
                        <select
                            v-model="courierStatus"
                            class="w-full h-11 px-3 text-sm bg-[#FAFBFB] border border-[#D5DCDB] rounded-none focus:border-[#0B6E5E] focus:outline-none"
                        >
                            <option value="all">All Couriers</option>
                            <option value="none">Hand-delivered</option>
                            <option value="queued">Queued</option>
                            <option value="created">Created</option>
                            <option value="picked_up">Picked Up / Dispatched</option>
                            <option value="in_transit">In Transit</option>
                            <option value="delivered">Delivered</option>
                            <option value="failed">Failed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <!-- Sticky Action Bar at Bottom -->
                <div class="p-4 border-t border-[#D5DCDB] bg-[#FAFBFB] flex items-center justify-between gap-3 sticky bottom-0 z-10">
                    <button
                        type="button"
                        class="text-xs text-[#4A5957] hover:text-danger underline font-semibold px-3 py-2"
                        @click="resetFilters(); isMobileFilterOpen = false;"
                    >
                        Reset all
                    </button>
                    <AppButton
                        variant="primary"
                        class="flex-1 h-11"
                        @click="isMobileFilterOpen = false; applyFilters();"
                    >
                        Apply filters
                    </AppButton>
                </div>
            </div>

            <!-- Invoices Table -->
            <div class="bg-white border border-[#D5DCDB] overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-[#EBEFEE] border-b border-[#D5DCDB] text-[11px] uppercase font-bold text-[#4A5957] sticky top-0 z-10">
                                <th scope="col" class="p-3">Invoice #</th>
                                <th scope="col" class="p-3">Date</th>
                                <th scope="col" class="p-3">Customer</th>
                                <th scope="col" class="p-3 text-right">Grand Total</th>
                                <th scope="col" class="p-3 text-right">Paid</th>
                                <th scope="col" class="p-3 text-right">Due</th>
                                <th scope="col" class="p-3 text-center">Payment</th>
                                <th scope="col" class="p-3 text-center">Sale Status</th>
                                <th scope="col" class="p-3 text-center">Courier</th>
                                <th scope="col" class="p-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#D5DCDB]">
                            <tr
                                v-for="inv in invoices.data"
                                :key="inv.id"
                                class="hover:bg-[#FAFBFB] transition-colors"
                                :class="inv.status === 'cancelled' ? 'bg-[#FCF6F6] opacity-75' : ''"
                            >
                                <!-- Invoice Number -->
                                <td class="p-3 font-mono font-bold">
                                    <Link
                                        :href="`/sales/invoices/${inv.id}`"
                                        class="hover:underline"
                                        :class="inv.status === 'cancelled' ? 'text-[#8E1B12] line-through' : 'text-[#0B6E5E]'"
                                    >
                                        {{ inv.invoice_no }}
                                    </Link>
                                </td>

                                <!-- Date -->
                                <td class="p-3 text-[#4A5957] whitespace-nowrap">
                                    {{ inv.sale_date }}
                                </td>

                                <!-- Customer -->
                                <td class="p-3">
                                    <div class="font-medium text-[#1A2725]">{{ inv.customer_name }}</div>
                                    <div class="font-mono text-[11px] text-[#728481]">{{ inv.customer_phone }}</div>
                                </td>

                                <!-- Grand Total -->
                                <td class="p-3 text-right font-mono font-bold text-[#1A2725]">
                                    {{ formatMoney(inv.grand_total) }}
                                </td>

                                <!-- Paid -->
                                <td class="p-3 text-right font-mono text-[#0A5E2E]">
                                    {{ formatMoney(inv.paid_total) }}
                                </td>

                                <!-- Net Due -->
                                <td
                                    class="p-3 text-right font-mono font-bold"
                                    :class="inv.net_due_amount > 0 ? 'text-[#8E1B12]' : 'text-[#728481]'"
                                >
                                    {{ formatMoney(inv.net_due_amount) }}
                                </td>

                                <!-- Payment Status Badge -->
                                <td class="p-3 text-center">
                                    <StatusBadge
                                        :status="inv.payment_status === 'paid' ? 'success' : (inv.payment_status === 'partial' ? 'warning' : 'danger')"
                                        :label="inv.payment_status.toUpperCase()"
                                    />
                                </td>

                                <!-- Sale Status Badge -->
                                <td class="p-3 text-center">
                                    <span
                                        v-if="inv.status === 'cancelled'"
                                        class="px-2 py-0.5 text-[10px] font-bold bg-[#FBEAE9] text-[#8E1B12] border border-[#F2BCB8]"
                                        :title="inv.cancel_reason || 'Cancelled sale'"
                                    >
                                        CANCELLED
                                    </span>
                                    <span
                                        v-else-if="inv.status === 'returned'"
                                        class="px-2 py-0.5 text-[10px] font-bold bg-[#F3E8FF] text-[#6B21A8] border border-[#D8B4FE]"
                                    >
                                        RETURNED
                                    </span>
                                    <span
                                        v-else-if="inv.status === 'partially_returned'"
                                        class="px-2 py-0.5 text-[10px] font-bold bg-[#FEF3C7] text-[#92400E] border border-[#FDE68A]"
                                    >
                                        PARTIAL RETURN
                                    </span>
                                    <span
                                        v-else
                                        class="px-2 py-0.5 text-[10px] font-bold bg-[#E6F2EF] text-[#0B6E5E] border border-[#B8DCD5]"
                                    >
                                        ACTIVE
                                    </span>
                                </td>

                                <!-- Courier / Consignment -->
                                <td class="p-3 text-center">
                                    <div v-if="inv.courier_mode === 'pathao'" class="inline-flex flex-col items-center">
                                        <span class="text-[10px] font-bold px-1.5 py-0.5 bg-[#E6F2EF] text-[#0B6E5E] border border-[#B8DCD5]">
                                            PATHAO
                                        </span>
                                        <span v-if="inv.consignment_id" class="font-mono text-[10px] text-[#52605C] mt-0.5">
                                            {{ inv.consignment_id }}
                                        </span>
                                        <span v-else class="text-[10px] text-[#A37A00] mt-0.5">
                                            {{ inv.courier_status || 'queued' }}
                                        </span>
                                    </div>
                                    <div v-else>
                                        <span class="text-[10px] px-1.5 py-0.5 bg-[#F0F2F2] text-[#52605C] border border-[#D5DCDB]">
                                            HAND-DELIVERED
                                        </span>
                                    </div>
                                </td>

                                <!-- Actions -->
                                <td class="p-3 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1">
                                        <!-- View -->
                                        <Link
                                            :href="`/sales/invoices/${inv.id}`"
                                            class="p-1.5 text-[#4A5957] hover:text-[#0B6E5E] hover:bg-[#EBEFEE]"
                                            title="View Invoice Document"
                                        >
                                            <AppIcon name="eye" :size="15" />
                                        </Link>

                                        <!-- Print -->
                                        <button
                                            type="button"
                                            class="p-1.5 text-[#4A5957] hover:text-[#0B6E5E] hover:bg-[#EBEFEE]"
                                            title="Print"
                                            @click="handlePrint(inv.id)"
                                        >
                                            <AppIcon name="printer" :size="15" />
                                        </button>

                                        <!-- Download PDF -->
                                        <a
                                            :href="`/sales/invoices/${inv.id}/pdf`"
                                            target="_blank"
                                            class="p-1.5 text-[#4A5957] hover:text-[#0B6E5E] hover:bg-[#EBEFEE]"
                                            title="Download PDF"
                                        >
                                            <AppIcon name="download" :size="15" />
                                        </a>

                                        <!-- Add Payment (if due > 0 and not cancelled) -->
                                        <button
                                            v-if="can.add_payment && inv.status !== 'cancelled' && inv.net_due_amount > 0"
                                            type="button"
                                            class="p-1.5 text-[#0A5E2E] hover:bg-[#E6F2EF]"
                                            title="Add Payment"
                                            @click="openPaymentModal(inv)"
                                        >
                                            <AppIcon name="plus" :size="15" />
                                        </button>

                                        <!-- Mark Returned (if not cancelled and not fully returned) -->
                                        <button
                                            v-if="can.return && inv.status !== 'cancelled' && inv.status !== 'returned'"
                                            type="button"
                                            class="p-1.5 text-[#6B21A8] hover:bg-[#F3E8FF]"
                                            title="Mark Return (Full or Partial)"
                                            @click="openReturnModal(inv)"
                                        >
                                            <AppIcon name="history" :size="15" />
                                        </button>

                                        <!-- Retry Pathao (if queued or failed) -->
                                        <button
                                            v-if="inv.courier_mode === 'pathao' && (inv.courier_status === 'failed' || inv.courier_status === 'queued')"
                                            type="button"
                                            class="p-1.5 text-[#A37A00] hover:bg-[#FEF3C7]"
                                            title="Retry Pathao Order"
                                            @click="handleRetryCourier(inv)"
                                        >
                                            <AppIcon name="refresh" :size="15" />
                                        </button>

                                        <!-- Cancel Sale (if not cancelled and user can cancel) -->
                                        <button
                                            v-if="can.cancel && inv.status !== 'cancelled'"
                                            type="button"
                                            class="p-1.5 text-[#8E1B12] hover:bg-[#FBEAE9]"
                                            title="Cancel Sale"
                                            @click="openCancelModal(inv)"
                                        >
                                            <AppIcon name="x" :size="15" />
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="invoices.data.length === 0">
                                <td colspan="10" class="p-8 text-center text-[#728481]">
                                    No invoices found matching your filter criteria.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="invoices.total > invoices.per_page" class="p-3 border-t border-[#D5DCDB]">
                    <TablePagination :paginator="invoices" />
                </div>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- ADD PAYMENT MODAL                                                  -->
        <!-- =================================================================== -->
        <div
            v-if="isPaymentModalOpen && activePaymentSale"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-[#0F1B1A]/40"
        >
            <div class="w-full max-w-md bg-white border border-[#D5DCDB] p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[#D5DCDB]">
                    <h3 class="text-base font-bold text-[#0F1B1A]">
                        Record Payment — {{ activePaymentSale.invoice_no }}
                    </h3>
                    <button type="button" class="text-[#728481] hover:text-[#0F1B1A]" @click="closePaymentModal">
                        <AppIcon name="x" :size="18" />
                    </button>
                </div>

                <div class="bg-[#FAFBFB] p-3 border border-[#D5DCDB] space-y-1 text-xs">
                    <div class="flex justify-between">
                        <span class="text-[#52605C]">Customer:</span>
                        <span class="font-medium text-[#0F1B1A]">{{ activePaymentSale.customer_name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-[#52605C]">Grand Total:</span>
                        <span class="font-mono">{{ formatMoney(activePaymentSale.grand_total) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-[#52605C]">Already Paid:</span>
                        <span class="font-mono text-[#0A5E2E]">{{ formatMoney(activePaymentSale.paid_total) }}</span>
                    </div>
                    <div class="flex justify-between font-bold text-[#8E1B12] pt-1 border-t border-[#D5DCDB]">
                        <span>Remaining Balance Due:</span>
                        <span class="font-mono">{{ formatMoney(activePaymentSale.net_due_amount) }}</span>
                    </div>
                </div>

                <form class="space-y-3 text-xs" @submit.prevent="submitPayment">
                    <div>
                        <label class="block font-bold text-[#4A5957] mb-1">Payment Amount (৳) *</label>
                        <input
                            v-model.number="paymentForm.amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            :max="activePaymentSale.net_due_amount"
                            required
                            class="w-full h-9 px-3 bg-[#FAFBFB] border border-[#D5DCDB] rounded-none focus:border-[#0B6E5E] focus:outline-none font-mono"
                        />
                        <p v-if="paymentForm.errors.amount" class="text-[#8E1B12] text-[11px] mt-0.5">
                            {{ paymentForm.errors.amount }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-[#4A5957] mb-1">Payment Date</label>
                            <input
                                v-model="paymentForm.paid_on"
                                type="date"
                                required
                                class="w-full h-9 px-2 bg-[#FAFBFB] border border-[#D5DCDB] rounded-none focus:border-[#0B6E5E] focus:outline-none"
                            />
                        </div>

                        <div>
                            <label class="block font-bold text-[#4A5957] mb-1">Method *</label>
                            <select
                                v-model="paymentForm.method"
                                class="w-full h-9 px-2 bg-[#FAFBFB] border border-[#D5DCDB] rounded-none focus:border-[#0B6E5E] focus:outline-none"
                            >
                                <option value="cash">Cash</option>
                                <option value="bkash">bKash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-[#4A5957] mb-1">Transaction Ref / Trx ID</label>
                        <input
                            v-model="paymentForm.reference"
                            type="text"
                            placeholder="Optional transaction reference"
                            class="w-full h-9 px-3 bg-[#FAFBFB] border border-[#D5DCDB] rounded-none focus:border-[#0B6E5E] focus:outline-none"
                        />
                    </div>

                    <div>
                        <label class="block font-bold text-[#4A5957] mb-1">Internal Note</label>
                        <input
                            v-model="paymentForm.note"
                            type="text"
                            placeholder="Optional note"
                            class="w-full h-9 px-3 bg-[#FAFBFB] border border-[#D5DCDB] rounded-none focus:border-[#0B6E5E] focus:outline-none"
                        />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-[#D5DCDB]">
                        <AppButton variant="secondary" size="sm" type="button" @click="closePaymentModal">
                            Cancel
                        </AppButton>
                        <AppButton variant="primary" size="sm" type="submit" :loading="paymentForm.processing">
                            Add payment
                        </AppButton>
                    </div>
                </form>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- CANCEL SALE CONFIRMATION MODAL                                     -->
        <!-- =================================================================== -->
        <div
            v-if="isCancelModalOpen && activeCancelSale"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-[#0F1B1A]/40"
        >
            <div class="w-full max-w-md bg-white border border-[#D5DCDB] p-6 shadow-xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[#D5DCDB]">
                    <h3 class="text-base font-bold text-[#8E1B12]">
                        Cancel Sale — {{ activeCancelSale.invoice_no }}
                    </h3>
                    <button type="button" class="text-[#728481] hover:text-[#0F1B1A]" @click="closeCancelModal">
                        <AppIcon name="x" :size="18" />
                    </button>
                </div>

                <div class="p-3 bg-[#FBEAE9] border border-[#F2BCB8] text-xs text-[#8E1B12] leading-relaxed">
                    <strong>Permanent Action:</strong> Cancelling will restore sold catalogue items back to inventory with a <code>sale_cancel</code> ledger movement. Revenue will be excluded from reports. The invoice number and historical logs remain intact.
                </div>

                <!-- Pathao parcel dispatched warning -->
                <div
                    v-if="activeCancelSale.courier_mode === 'pathao' && activeCancelSale.consignment_id"
                    class="p-3 bg-[#FEF3C7] border border-[#FDE68A] text-xs text-[#92400E] leading-relaxed"
                >
                    <strong>Dispatched Parcel Notice:</strong> An active Pathao consignment (#{{ activeCancelSale.consignment_id }}) exists. The courier shipment must be returned or cancelled directly in the Pathao Merchant Panel.
                </div>

                <form class="space-y-3 text-xs" @submit.prevent="submitCancel">
                    <div>
                        <label class="block font-bold text-[#4A5957] mb-1">
                            Cancellation Reason * (min 5 characters)
                        </label>
                        <textarea
                            v-model="cancelForm.reason"
                            rows="3"
                            required
                            placeholder="E.g., Customer requested order cancellation before delivery, wrong item ordered..."
                            class="w-full p-2.5 bg-[#FAFBFB] border border-[#D5DCDB] rounded-none focus:border-[#8E1B12] focus:outline-none"
                        />
                        <p v-if="cancelForm.errors.reason" class="text-[#8E1B12] text-[11px] mt-0.5">
                            {{ cancelForm.errors.reason }}
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-[#D5DCDB]">
                        <AppButton variant="secondary" size="sm" type="button" @click="closeCancelModal">
                            Keep Sale
                        </AppButton>
                        <AppButton
                            variant="danger"
                            size="sm"
                            type="submit"
                            :loading="cancelForm.processing"
                        >
                            Confirm Cancellation
                        </AppButton>
                    </div>
                </form>
            </div>
        </div>

        <!-- =================================================================== -->
        <!-- RETURN MODAL (FULL OR PARTIAL)                                     -->
        <!-- =================================================================== -->
        <div
            v-if="isReturnModalOpen && activeReturnSale"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-[#0F1B1A]/40 overflow-y-auto"
        >
            <div class="w-full max-w-2xl bg-white border border-[#D5DCDB] p-6 shadow-xl space-y-4 my-8">
                <div class="flex items-center justify-between pb-3 border-b border-[#D5DCDB]">
                    <div>
                        <h3 class="text-base font-bold text-[#0F1B1A]">
                            Process Return — {{ activeReturnSale.invoice_no }}
                        </h3>
                        <p class="text-xs text-[#52605C]">
                            Customer: {{ activeReturnSale.customer_name }} ({{ activeReturnSale.customer_phone }})
                        </p>
                    </div>
                    <button type="button" class="text-[#728481] hover:text-[#0F1B1A]" @click="closeReturnModal">
                        <AppIcon name="x" :size="18" />
                    </button>
                </div>

                <form class="space-y-4 text-xs" @submit.prevent="submitReturn">
                    <!-- Line Items Return Selection Table -->
                    <div class="border border-[#D5DCDB] overflow-hidden">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-[#EBEFEE] border-b border-[#D5DCDB] text-[11px] font-bold text-[#4A5957]">
                                    <th class="p-2.5">Item Name</th>
                                    <th class="p-2.5 text-right">Unit Price</th>
                                    <th class="p-2.5 text-center">Sold</th>
                                    <th class="p-2.5 text-center">Returned</th>
                                    <th class="p-2.5 text-center w-28">Return Qty</th>
                                    <th class="p-2.5 text-right">Return Value</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#D5DCDB]">
                                <tr v-for="item in returnForm.items" :key="item.sale_item_id" class="hover:bg-[#FAFBFB]">
                                    <td class="p-2.5">
                                        <div class="font-medium text-[#0F1B1A]">{{ item.name }}</div>
                                        <span v-if="item.is_custom" class="text-[10px] text-[#A37A00]">Custom Item</span>
                                    </td>
                                    <td class="p-2.5 text-right font-mono">
                                        {{ formatMoney(item.unit_price) }}
                                    </td>
                                    <td class="p-2.5 text-center font-mono">
                                        {{ item.qty_sold }}
                                    </td>
                                    <td class="p-2.5 text-center font-mono text-[#6B21A8]">
                                        {{ item.already_returned }}
                                    </td>
                                    <td class="p-2.5 text-center">
                                        <input
                                            v-model.number="item.qty"
                                            type="number"
                                            min="0"
                                            :max="item.unreturned_qty"
                                            :disabled="item.unreturned_qty === 0"
                                            class="w-20 h-7 px-2 text-center bg-[#FAFBFB] border border-[#D5DCDB] rounded-none focus:border-[#0B6E5E] focus:outline-none font-mono font-bold"
                                        />
                                    </td>
                                    <td class="p-2.5 text-right font-mono font-bold text-[#0B6E5E]">
                                        {{ formatMoney(item.qty * item.unit_price) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Return Financial & Reason Details -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Left Details -->
                        <div class="space-y-3">
                            <div>
                                <label class="block font-bold text-[#4A5957] mb-1">Return Date *</label>
                                <input
                                    v-model="returnForm.return_date"
                                    type="date"
                                    required
                                    class="w-full h-8 px-2 bg-[#FAFBFB] border border-[#D5DCDB] rounded-none focus:border-[#0B6E5E] focus:outline-none"
                                />
                            </div>

                            <div>
                                <label class="block font-bold text-[#4A5957] mb-1">Return Reason *</label>
                                <textarea
                                    v-model="returnForm.reason"
                                    rows="2"
                                    required
                                    placeholder="Reason for returning items..."
                                    class="w-full p-2 bg-[#FAFBFB] border border-[#D5DCDB] rounded-none focus:border-[#0B6E5E] focus:outline-none"
                                />
                                <p v-if="returnForm.errors.reason" class="text-[#8E1B12] text-[11px] mt-0.5">
                                    {{ returnForm.errors.reason }}
                                </p>
                            </div>

                            <div class="flex items-center gap-2 pt-1">
                                <input
                                    id="restock-checkbox"
                                    v-model="returnForm.restock"
                                    type="checkbox"
                                    class="w-4 h-4 rounded-none accent-[#0B6E5E]"
                                />
                                <label for="restock-checkbox" class="text-xs text-[#0F1B1A]">
                                    Restore returned catalogue items to stock inventory
                                </label>
                            </div>
                        </div>

                        <!-- Right Summary & Refund -->
                        <div class="bg-[#FAFBFB] p-3.5 border border-[#D5DCDB] space-y-3">
                            <div class="flex justify-between font-bold text-sm">
                                <span>Total Return Value:</span>
                                <span class="font-mono text-[#0B6E5E]">{{ formatMoney(computedReturnValue) }}</span>
                            </div>

                            <div>
                                <label class="block font-bold text-[#4A5957] mb-1">
                                    Cash/bKash Refunded to Customer (৳)
                                </label>
                                <input
                                    v-model.number="returnForm.refunded_amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    :max="activeReturnSale.paid_total"
                                    class="w-full h-8 px-2 bg-white border border-[#D5DCDB] rounded-none focus:border-[#0B6E5E] focus:outline-none font-mono"
                                />
                                <span class="text-[10px] text-[#728481]">
                                    Max cash refundable: {{ formatMoney(activeReturnSale.paid_total) }}
                                </span>
                            </div>

                            <div class="pt-2 border-t border-[#D5DCDB] space-y-2">
                                <label class="block font-bold text-[#4A5957]">
                                    Return Courier Cost (৳, optional)
                                </label>
                                <div class="grid grid-cols-2 gap-2">
                                    <input
                                        v-model.number="returnForm.return_courier_cost"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        placeholder="0.00"
                                        class="w-full h-8 px-2 bg-white border border-[#D5DCDB] rounded-none focus:border-[#0B6E5E] focus:outline-none font-mono"
                                    />
                                    <select
                                        v-model="returnForm.return_courier_payment_method"
                                        class="w-full h-8 px-2 bg-white border border-[#D5DCDB] rounded-none focus:border-[#0B6E5E] focus:outline-none"
                                    >
                                        <option value="cash">Cash</option>
                                        <option value="bkash">bKash</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                    </select>
                                </div>
                                <span class="text-[10px] text-[#728481]">
                                    Creates a linked courier expense in P&L for this return.
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-[#D5DCDB]">
                        <AppButton variant="secondary" size="sm" type="button" @click="closeReturnModal">
                            Cancel
                        </AppButton>
                        <AppButton
                            variant="primary"
                            size="sm"
                            type="submit"
                            :disabled="computedReturnValue <= 0"
                            :loading="returnForm.processing"
                        >
                            Confirm Return
                        </AppButton>
                    </div>
                </form>
            </div>
    </div>
</template>
