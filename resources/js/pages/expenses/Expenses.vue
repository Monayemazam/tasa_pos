<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import PageHeader from '@/components/tasa/PageHeader.vue';
import AppButton from '@/components/tasa/AppButton.vue';
import AppIcon from '@/components/tasa/AppIcon.vue';
import StatusBadge from '@/components/tasa/StatusBadge.vue';
import TablePagination from '@/components/tasa/TablePagination.vue';
import EmptyState from '@/components/tasa/EmptyState.vue';
import ConfirmDialog from '@/components/tasa/ConfirmDialog.vue';
import { formatMoney } from '@/lib/formatters';

interface CategoryDto {
    id: number;
    name: string;
    pl_group: string;
    pl_group_label?: string;
    is_system: boolean;
    is_active: boolean;
}

interface ExpenseDto {
    id: number;
    expense_category_id: number;
    expense_date: string;
    amount: number;
    payment_method: 'cash' | 'bkash' | 'bank_transfer' | 'other';
    vendor: string | null;
    note: string | null;
    attachment_path: string | null;
    source: 'manual' | 'recurring' | 'pathao_charge' | 'return_courier';
    sale_id: number | null;
    courier_order_id: number | null;
    fee_deducted_from_cod: boolean;
    category?: CategoryDto;
    creator?: { id: number; name: string };
    sale?: { id: number; invoice_no: string };
    courier_order?: { id: number; consignment_id: string; status: string };
}

interface RecurringTemplateDto {
    id: number;
    expense_category_id: number;
    amount: number;
    vendor: string | null;
    note: string | null;
    payment_method: string | null;
    day_of_month: number;
    is_active: boolean;
    last_confirmed_period: string | null;
    category?: CategoryDto;
}

interface AnalyticsDto {
    total_amount: number;
    total_count: number;
    facebook_ads_total: number;
    other_marketing_total: number;
    courier_total: number;
    packaging_total: number;
    rent_utilities_total: number;
    other_total: number;
    by_category: Array<{
        category_id: number;
        name: string;
        pl_group: string;
        amount: number;
        count: number;
        percentage: number;
    }>;
    by_pl_group: Record<string, number>;
    by_payment_method: Record<string, number>;
    date_from: string | null;
    date_to: string | null;
}

interface PaginatedExpenses {
    data: ExpenseDto[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

const props = defineProps<{
    expenses: PaginatedExpenses;
    analytics: AnalyticsDto;
    pendingRecurring: RecurringTemplateDto[];
    categories: CategoryDto[];
    recurringTemplates: RecurringTemplateDto[];
    plGroups: Record<string, string>;
    filters: {
        search?: string;
        date_preset?: string;
        date_from?: string;
        date_to?: string;
        category_id?: string;
        pl_group?: string;
        payment_method?: string;
        source?: string;
    };
    can: {
        create: boolean;
        edit: boolean;
        delete: boolean;
        manage_categories: boolean;
        manage_recurring: boolean;
    };
}>();

// ==========================================
// FILTERS STATE
// ==========================================
const search = ref(props.filters.search || '');
const datePreset = ref(props.filters.date_preset || 'this_month');
const dateFrom = ref(props.filters.date_from || '');
const dateTo = ref(props.filters.date_to || '');
const selectedCategoryId = ref(props.filters.category_id || 'all');
const selectedPlGroup = ref(props.filters.pl_group || 'all');
const selectedPaymentMethod = ref(props.filters.payment_method || 'all');
const selectedSource = ref(props.filters.source || 'all');

const activeCategories = computed(() => props.categories.filter((c) => c.is_active));

function onDatePresetChange() {
    if (datePreset.value !== 'custom') {
        dateFrom.value = '';
        dateTo.value = '';
    }
    applyFilters();
}

function applyFilters() {
    router.get(
        '/expenses',
        {
            search: search.value || undefined,
            date_preset: datePreset.value,
            date_from: datePreset.value === 'custom' ? dateFrom.value || undefined : undefined,
            date_to: datePreset.value === 'custom' ? dateTo.value || undefined : undefined,
            category_id: selectedCategoryId.value !== 'all' ? selectedCategoryId.value : undefined,
            pl_group: selectedPlGroup.value !== 'all' ? selectedPlGroup.value : undefined,
            payment_method: selectedPaymentMethod.value !== 'all' ? selectedPaymentMethod.value : undefined,
            source: selectedSource.value !== 'all' ? selectedSource.value : undefined,
        },
        { preserveState: true, replace: true }
    );
}

interface ExpenseFilterChip {
    key: string;
    label: string;
    value: string;
}

const isMobileFilterOpen = ref(false);

const activeFilterChips = computed<ExpenseFilterChip[]>(() => {
    const chips: ExpenseFilterChip[] = [];
    if (search.value.trim()) {
        chips.push({ key: 'search', label: 'Search', value: search.value.trim() });
    }
    if (datePreset.value && datePreset.value !== 'this_month') {
        const labels: Record<string, string> = {
            today: 'Today',
            yesterday: 'Yesterday',
            last_7_days: 'Last 7 Days',
            last_30_days: 'Last 30 Days',
            last_month: 'Last Month',
            custom: dateFrom.value && dateTo.value ? `${dateFrom.value} to ${dateTo.value}` : 'Custom Range',
        };
        chips.push({ key: 'date_preset', label: 'Period', value: labels[datePreset.value] || datePreset.value });
    }
    if (selectedCategoryId.value && selectedCategoryId.value !== 'all') {
        const cat = props.categories.find((c) => String(c.id) === String(selectedCategoryId.value));
        chips.push({ key: 'category_id', label: 'Category', value: cat?.name || 'Category' });
    }
    if (selectedPlGroup.value && selectedPlGroup.value !== 'all') {
        chips.push({ key: 'pl_group', label: 'P&L Line', value: props.plGroups[selectedPlGroup.value] || selectedPlGroup.value });
    }
    if (selectedPaymentMethod.value && selectedPaymentMethod.value !== 'all') {
        chips.push({ key: 'payment_method', label: 'Method', value: selectedPaymentMethod.value.toUpperCase() });
    }
    if (selectedSource.value && selectedSource.value !== 'all') {
        chips.push({ key: 'source', label: 'Source', value: selectedSource.value.toUpperCase() });
    }
    return chips;
});

function removeFilter(key: string) {
    if (key === 'search') search.value = '';
    if (key === 'date_preset') {
        datePreset.value = 'this_month';
        dateFrom.value = '';
        dateTo.value = '';
    }
    if (key === 'category_id') selectedCategoryId.value = 'all';
    if (key === 'pl_group') selectedPlGroup.value = 'all';
    if (key === 'payment_method') selectedPaymentMethod.value = 'all';
    if (key === 'source') selectedSource.value = 'all';
    applyFilters();
}

function resetFilters() {
    search.value = '';
    datePreset.value = 'this_month';
    dateFrom.value = '';
    dateTo.value = '';
    selectedCategoryId.value = 'all';
    selectedPlGroup.value = 'all';
    selectedPaymentMethod.value = 'all';
    selectedSource.value = 'all';
    applyFilters();
}

// ==========================================
// EXPENSE MODAL (ADD / EDIT)
// ==========================================
const isExpenseModalOpen = ref(false);
const editingExpense = ref<ExpenseDto | null>(null);

const expenseForm = useForm<{
    expense_date: string;
    expense_category_id: number | '';
    amount: number | '';
    payment_method: 'cash' | 'bkash' | 'bank_transfer' | 'other';
    vendor: string;
    note: string;
    attachment: File | null;
    remove_attachment: boolean;
}>({
    expense_date: new Date().toISOString().slice(0, 10),
    expense_category_id: '',
    amount: '',
    payment_method: 'cash',
    vendor: '',
    note: '',
    attachment: null,
    remove_attachment: false,
});

function openAddExpenseModal() {
    editingExpense.value = null;
    expenseForm.reset();
    expenseForm.clearErrors();
    expenseForm.expense_date = new Date().toISOString().slice(0, 10);
    expenseForm.payment_method = 'cash';
    if (activeCategories.value.length > 0) {
        expenseForm.expense_category_id = activeCategories.value[0].id;
    }
    isExpenseModalOpen.value = true;
}

function openEditExpenseModal(exp: ExpenseDto) {
    editingExpense.value = exp;
    expenseForm.clearErrors();
    expenseForm.expense_date = exp.expense_date ? exp.expense_date.slice(0, 10) : '';
    expenseForm.expense_category_id = exp.expense_category_id;
    expenseForm.amount = Number(exp.amount);
    expenseForm.payment_method = exp.payment_method || 'cash';
    expenseForm.vendor = exp.vendor || '';
    expenseForm.note = exp.note || '';
    expenseForm.attachment = null;
    expenseForm.remove_attachment = false;
    isExpenseModalOpen.value = true;
}

function onFileSelected(event: Event) {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        expenseForm.attachment = target.files[0];
        expenseForm.remove_attachment = false;
    }
}

function submitExpense() {
    if (editingExpense.value) {
        expenseForm.post(`/expenses/${editingExpense.value.id}`, {
            onSuccess: () => {
                isExpenseModalOpen.value = false;
            },
        });
    } else {
        expenseForm.post('/expenses', {
            onSuccess: () => {
                isExpenseModalOpen.value = false;
            },
        });
    }
}

// ==========================================
// DELETE EXPENSE DIALOG
// ==========================================
const isDeleteDialogOpen = ref(false);
const expenseToDelete = ref<ExpenseDto | null>(null);

function confirmDeleteExpense(exp: ExpenseDto) {
    expenseToDelete.value = exp;
    isDeleteDialogOpen.value = true;
}

function executeDeleteExpense() {
    if (!expenseToDelete.value) return;
    router.delete(`/expenses/${expenseToDelete.value.id}`, {
        onSuccess: () => {
            isDeleteDialogOpen.value = false;
            expenseToDelete.value = null;
        },
    });
}

// ==========================================
// RECURRING CONFIRMATION (1-CLICK)
// ==========================================
const confirmingTemplateId = ref<number | null>(null);

function confirmRecurringItem(template: RecurringTemplateDto) {
    confirmingTemplateId.value = template.id;
    router.post(
        `/expenses/recurring/${template.id}/confirm`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                confirmingTemplateId.value = null;
            },
        }
    );
}

// ==========================================
// CATEGORIES MODAL & MANAGEMENT
// ==========================================
const isCategoriesModalOpen = ref(false);
const editingCategory = ref<CategoryDto | null>(null);

const categoryForm = useForm<{
    name: string;
    pl_group: string;
    is_active: boolean;
}>({
    name: '',
    pl_group: 'other',
    is_active: true,
});

function openCategoriesModal() {
    editingCategory.value = null;
    categoryForm.reset();
    categoryForm.clearErrors();
    categoryForm.pl_group = 'other';
    categoryForm.is_active = true;
    isCategoriesModalOpen.value = true;
}

function startEditCategory(cat: CategoryDto) {
    editingCategory.value = cat;
    categoryForm.name = cat.name;
    categoryForm.pl_group = cat.pl_group;
    categoryForm.is_active = cat.is_active;
}

function cancelEditCategory() {
    editingCategory.value = null;
    categoryForm.reset();
    categoryForm.clearErrors();
}

function submitCategory() {
    if (editingCategory.value) {
        categoryForm.put(`/expenses/categories/${editingCategory.value.id}`, {
            onSuccess: () => {
                editingCategory.value = null;
                categoryForm.reset();
            },
        });
    } else {
        categoryForm.post('/expenses/categories', {
            onSuccess: () => {
                categoryForm.reset();
            },
        });
    }
}

function deleteCategory(cat: CategoryDto) {
    if (confirm(`Are you sure you want to delete category "${cat.name}"?`)) {
        router.delete(`/expenses/categories/${cat.id}`, {
            preserveScroll: true,
        });
    }
}

// ==========================================
// RECURRING TEMPLATES MODAL
// ==========================================
const isRecurringModalOpen = ref(false);
const editingRecurring = ref<RecurringTemplateDto | null>(null);

const recurringForm = useForm<{
    expense_category_id: number | '';
    amount: number | '';
    vendor: string;
    note: string;
    payment_method: 'cash' | 'bkash' | 'bank_transfer' | 'other';
    day_of_month: number;
    is_active: boolean;
}>({
    expense_category_id: '',
    amount: '',
    vendor: '',
    note: '',
    payment_method: 'cash',
    day_of_month: 1,
    is_active: true,
});

function openRecurringModal() {
    editingRecurring.value = null;
    recurringForm.reset();
    recurringForm.clearErrors();
    recurringForm.day_of_month = 1;
    recurringForm.is_active = true;
    recurringForm.payment_method = 'cash';
    if (activeCategories.value.length > 0) {
        recurringForm.expense_category_id = activeCategories.value[0].id;
    }
    isRecurringModalOpen.value = true;
}

function startEditRecurring(item: RecurringTemplateDto) {
    editingRecurring.value = item;
    recurringForm.expense_category_id = item.expense_category_id;
    recurringForm.amount = Number(item.amount);
    recurringForm.vendor = item.vendor || '';
    recurringForm.note = item.note || '';
    recurringForm.payment_method = (item.payment_method as any) || 'cash';
    recurringForm.day_of_month = item.day_of_month || 1;
    recurringForm.is_active = item.is_active;
}

function cancelEditRecurring() {
    editingRecurring.value = null;
    recurringForm.reset();
    recurringForm.clearErrors();
}

function submitRecurring() {
    if (editingRecurring.value) {
        recurringForm.put(`/expenses/recurring/${editingRecurring.value.id}`, {
            onSuccess: () => {
                editingRecurring.value = null;
                recurringForm.reset();
            },
        });
    } else {
        recurringForm.post('/expenses/recurring', {
            onSuccess: () => {
                recurringForm.reset();
            },
        });
    }
}

function deleteRecurring(item: RecurringTemplateDto) {
    if (confirm('Delete this recurring monthly expense template?')) {
        router.delete(`/expenses/recurring/${item.id}`, {
            preserveScroll: true,
        });
    }
}

// Helper for source badges
function sourceBadge(source: string) {
    switch (source) {
        case 'manual':
            return { label: 'Manual', variant: 'neutral' as const };
        case 'recurring':
            return { label: 'Recurring', variant: 'success' as const };
        case 'pathao_charge':
            return { label: 'Pathao Delivery', variant: 'warning' as const };
        case 'return_courier':
            return { label: 'Return Courier', variant: 'danger' as const };
        default:
            return { label: source, variant: 'neutral' as const };
    }
}

// Helper for payment method labels
function paymentMethodLabel(m: string | null) {
    switch (m) {
        case 'cash':
            return 'Cash';
        case 'bkash':
            return 'bKash';
        case 'bank_transfer':
            return 'Bank Transfer';
        case 'other':
            return 'Other / COD';
        default:
            return m || '—';
    }
}

// Active view mode for breakdown
const breakdownTab = ref<'category' | 'pl'>('category');
</script>

<template>
    <Head title="Business Expenses - Tasa POS" />

        <div class="px-4 py-6 sm:px-6 lg:px-8 max-w-7xl mx-auto space-y-6">
            <!-- PAGE HEADER -->
            <PageHeader
                title="Business Expenses"
                subtitle="Track operating expenses, recurring utility bills, and Facebook Ads marketing spend."
            >
                <template #actions>
                    <div class="flex flex-wrap items-center gap-2">
                        <AppButton
                            v-if="can.manage_categories"
                            variant="secondary"
                            @click="openCategoriesModal"
                        >
                            <AppIcon name="settings" :size="16" class="mr-1.5" />
                            Categories
                        </AppButton>

                        <AppButton
                            v-if="can.manage_recurring"
                            variant="secondary"
                            @click="openRecurringModal"
                        >
                            <AppIcon name="calendar" :size="16" class="mr-1.5" />
                            Recurring Templates
                        </AppButton>

                        <AppButton
                            v-if="can.create"
                            variant="primary"
                            @click="openAddExpenseModal"
                        >
                            <AppIcon name="plus" :size="16" class="mr-1.5" />
                            Add Expense
                        </AppButton>
                    </div>
                </template>
            </PageHeader>

            <!-- 1ST OF MONTH / PENDING RECURRING EXPENSES REMINDER BANNER (BR-9) -->
            <div
                v-if="pendingRecurring && pendingRecurring.length > 0"
                class="border-2 border-amber-400 bg-amber-50 p-4 sm:p-5 text-amber-900"
            >
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 font-bold text-base text-amber-950">
                            <AppIcon name="alert" :size="20" class="text-amber-600" />
                            <span>Monthly Recurring Expenses Pending Confirmation</span>
                            <span class="bg-amber-200 text-amber-900 text-xs px-2 py-0.5 font-bold uppercase tracking-wider">
                                {{ pendingRecurring.length }} Due
                            </span>
                        </div>
                        <p class="text-xs text-amber-800">
                            Operating expenses are never silently auto-posted. Review and click "Confirm" to record this month's scheduled bill.
                        </p>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div
                        v-for="item in pendingRecurring"
                        :key="item.id"
                        class="bg-white border border-amber-200 p-3.5 flex flex-col justify-between space-y-3"
                    >
                        <div>
                            <div class="flex items-start justify-between gap-2">
                                <span class="font-bold text-ink-900 text-sm">
                                    {{ item.category?.name || 'Uncategorized' }}
                                </span>
                                <span class="font-mono font-bold text-ink-900 text-sm">
                                    {{ formatMoney(item.amount) }}
                                </span>
                            </div>
                            <div class="text-xs text-ink-600 mt-1 space-y-0.5">
                                <div v-if="item.vendor">
                                    <span class="text-ink-400">Vendor:</span> {{ item.vendor }}
                                </div>
                                <div>
                                    <span class="text-ink-400">Schedule:</span> Day {{ item.day_of_month }} of each month
                                </div>
                                <div v-if="item.note" class="italic text-ink-500">
                                    "{{ item.note }}"
                                </div>
                            </div>
                        </div>

                        <div class="pt-2 border-t border-ink-100 flex items-center justify-between">
                            <span class="text-xs text-ink-500">
                                Via {{ paymentMethodLabel(item.payment_method) }}
                            </span>
                            <AppButton
                                variant="primary"
                                size="sm"
                                :loading="confirmingTemplateId === item.id"
                                @click="confirmRecurringItem(item)"
                            >
                                <AppIcon name="check" :size="14" class="mr-1" />
                                Add this month's {{ item.category?.name }}?
                            </AppButton>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ANALYTICS / STAT CARDS -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4">
                <!-- TOTAL OPEX -->
                <div class="bg-white border border-ink-200 p-4 space-y-1 col-span-2 sm:col-span-1">
                    <span class="text-xs font-semibold uppercase tracking-wider text-ink-500">Total OPEX</span>
                    <div class="text-2xl font-extrabold text-ink-900 font-mono">
                        {{ formatMoney(analytics.total_amount) }}
                    </div>
                    <span class="text-xs text-ink-500 block">
                        {{ analytics.total_count }} recorded items
                    </span>
                </div>

                <!-- FACEBOOK ADS (FIRST CLASS FOR ROAS) -->
                <div class="bg-teal-50 border-2 border-teal-700 p-4 space-y-1">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-teal-900">Facebook Ads</span>
                        <span class="text-[10px] bg-teal-700 text-white font-bold px-1.5 py-0.5 uppercase">ROAS Feed</span>
                    </div>
                    <div class="text-2xl font-extrabold text-teal-950 font-mono">
                        {{ formatMoney(analytics.facebook_ads_total) }}
                    </div>
                    <span class="text-xs text-teal-800 block">
                        {{ analytics.total_amount > 0 ? Math.round((analytics.facebook_ads_total / analytics.total_amount) * 100) : 0 }}% of OPEX
                    </span>
                </div>

                <!-- OTHER MARKETING -->
                <div class="bg-white border border-ink-200 p-4 space-y-1">
                    <span class="text-xs font-semibold uppercase tracking-wider text-ink-500">Other Marketing</span>
                    <div class="text-2xl font-extrabold text-ink-900 font-mono">
                        {{ formatMoney(analytics.other_marketing_total) }}
                    </div>
                    <span class="text-xs text-ink-500 block">Influencers, promos</span>
                </div>

                <!-- COURIER & RETURNS -->
                <div class="bg-white border border-ink-200 p-4 space-y-1">
                    <span class="text-xs font-semibold uppercase tracking-wider text-ink-500">Courier & Logistics</span>
                    <div class="text-2xl font-extrabold text-ink-900 font-mono">
                        {{ formatMoney(analytics.courier_total) }}
                    </div>
                    <span class="text-xs text-ink-500 block">Pathao & Return fees</span>
                </div>

                <!-- RENT & UTILITIES -->
                <div class="bg-white border border-ink-200 p-4 space-y-1">
                    <span class="text-xs font-semibold uppercase tracking-wider text-ink-500">Rent & Utilities</span>
                    <div class="text-2xl font-extrabold text-ink-900 font-mono">
                        {{ formatMoney(analytics.rent_utilities_total) }}
                    </div>
                    <span class="text-xs text-ink-500 block">Facility & Internet</span>
                </div>
            </div>

            <!-- RANKED EXPENSE BREAKDOWN (COLLAPSIBLE / TOGGLEABLE) -->
            <div class="bg-white border border-ink-200 p-4 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-ink-100 pb-3">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-sm text-ink-900 uppercase tracking-wider">Expense Breakdown</span>
                        <span class="text-xs text-ink-500">(Ranked by spend)</span>
                    </div>
                    <div class="flex items-center gap-1 text-xs">
                        <button
                            type="button"
                            :class="[
                                'px-3 py-1 font-semibold transition-colors border',
                                breakdownTab === 'category'
                                    ? 'bg-teal-700 text-white border-teal-700'
                                    : 'bg-ink-50 text-ink-700 border-ink-200 hover:bg-ink-100'
                            ]"
                            @click="breakdownTab = 'category'"
                        >
                            By Category
                        </button>
                        <button
                            type="button"
                            :class="[
                                'px-3 py-1 font-semibold transition-colors border',
                                breakdownTab === 'pl'
                                    ? 'bg-teal-700 text-white border-teal-700'
                                    : 'bg-ink-50 text-ink-700 border-ink-200 hover:bg-ink-100'
                            ]"
                            @click="breakdownTab = 'pl'"
                        >
                            By P&L Line
                        </button>
                    </div>
                </div>

                <!-- CATEGORY RANKED LIST -->
                <div v-if="breakdownTab === 'category'" class="space-y-3">
                    <div v-if="analytics.by_category.length === 0" class="text-xs text-ink-500 py-2">
                        No expenses logged for this filter range.
                    </div>
                    <div
                        v-for="cat in analytics.by_category.slice(0, 6)"
                        :key="cat.category_id"
                        class="space-y-1"
                    >
                        <div class="flex items-center justify-between text-xs">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-ink-900">{{ cat.name }}</span>
                                <span class="text-ink-400 text-[11px] font-mono">({{ cat.count }} items)</span>
                                <span
                                    v-if="cat.pl_group === 'marketing_facebook'"
                                    class="bg-teal-100 text-teal-800 text-[10px] px-1 font-bold"
                                >
                                    Facebook Ads
                                </span>
                            </div>
                            <div class="font-mono font-bold text-ink-900">
                                {{ formatMoney(cat.amount) }}
                                <span class="text-ink-500 font-normal text-[11px] ml-1">({{ cat.percentage }}%)</span>
                            </div>
                        </div>
                        <div class="w-full bg-ink-100 h-2 overflow-hidden">
                            <div
                                class="h-full transition-all duration-300"
                                :class="cat.pl_group === 'marketing_facebook' ? 'bg-teal-700' : 'bg-ink-700'"
                                :style="{ width: `${Math.min(100, Math.max(2, cat.percentage))}%` }"
                            />
                        </div>
                    </div>
                </div>

                <!-- P&L LINE GROUP DISTRIBUTION -->
                <div v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    <div
                        v-for="(amount, key) in analytics.by_pl_group"
                        :key="key"
                        class="border border-ink-100 bg-ink-50 p-2.5 space-y-1"
                    >
                        <span class="text-[11px] text-ink-500 font-semibold block uppercase">
                            {{ plGroups[key] || key }}
                        </span>
                        <div class="text-sm font-bold font-mono text-ink-900">
                            {{ formatMoney(amount) }}
                        </div>
                        <div class="text-[10px] text-ink-400">
                            {{ analytics.total_amount > 0 ? Math.round((Number(amount) / analytics.total_amount) * 100) : 0 }}% of total
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mobile Filter Trigger Button (< 640px) -->
            <div class="sm:hidden flex items-center gap-2">
                <button
                    type="button"
                    class="flex-1 flex items-center justify-center gap-2 h-10 px-3 bg-white border border-ink-200 text-xs font-bold text-ink-900 active:bg-ink-50"
                    @click="isMobileFilterOpen = true"
                >
                    <AppIcon name="filter" :size="16" />
                    <span>Filters</span>
                    <span
                        v-if="activeFilterChips.length > 0"
                        class="px-1.5 py-0.5 bg-teal-700 text-white text-[10px] font-bold"
                    >
                        {{ activeFilterChips.length }}
                    </span>
                </button>
                <button
                    v-if="activeFilterChips.length > 0"
                    type="button"
                    class="h-10 px-3 bg-white border border-ink-200 text-xs text-danger font-semibold"
                    @click="resetFilters"
                >
                    Reset
                </button>
            </div>

            <!-- DESKTOP FILTER BAR (hidden on mobile, visible on sm and up) -->
            <div class="hidden sm:block bg-white border border-ink-200 p-4 space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <!-- SEARCH -->
                    <div>
                        <label class="block text-xs font-semibold text-ink-700 mb-1">Search</label>
                        <div class="relative">
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Vendor, note, category, consignment..."
                                class="w-full border border-ink-300 px-3 py-1.5 text-xs text-ink-900 focus:outline-none focus:border-teal-700"
                                @keyup.enter="applyFilters"
                            />
                        </div>
                    </div>

                    <!-- DATE PRESET -->
                    <div>
                        <label class="block text-xs font-semibold text-ink-700 mb-1">Date Preset</label>
                        <select
                            v-model="datePreset"
                            class="w-full border border-ink-300 px-3 py-1.5 text-xs text-ink-900 focus:outline-none focus:border-teal-700"
                            @change="onDatePresetChange"
                        >
                            <option value="this_month">This Month</option>
                            <option value="today">Today</option>
                            <option value="yesterday">Yesterday</option>
                            <option value="last_7_days">Last 7 Days</option>
                            <option value="last_30_days">Last 30 Days</option>
                            <option value="last_month">Last Month</option>
                            <option value="custom">Custom Date Range</option>
                        </select>
                    </div>

                    <!-- CATEGORY -->
                    <div>
                        <label class="block text-xs font-semibold text-ink-700 mb-1">Category</label>
                        <select
                            v-model="selectedCategoryId"
                            class="w-full border border-ink-300 px-3 py-1.5 text-xs text-ink-900 focus:outline-none focus:border-teal-700"
                            @change="applyFilters"
                        >
                            <option value="all">All Categories</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                {{ cat.name }} {{ !cat.is_active ? '(Inactive)' : '' }}
                            </option>
                        </select>
                    </div>

                    <!-- P&L GROUP -->
                    <div>
                        <label class="block text-xs font-semibold text-ink-700 mb-1">P&L Group</label>
                        <select
                            v-model="selectedPlGroup"
                            class="w-full border border-ink-300 px-3 py-1.5 text-xs text-ink-900 focus:outline-none focus:border-teal-700"
                            @change="applyFilters"
                        >
                            <option value="all">All P&L Lines</option>
                            <option v-for="(label, key) in plGroups" :key="key" :value="key">
                                {{ label }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- ROW 2 FILTERS (CUSTOM DATES, METHOD, SOURCE, RESET) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-2 border-t border-ink-100">
                    <!-- CUSTOM FROM / TO -->
                    <div v-if="datePreset === 'custom'" class="col-span-1 sm:col-span-2 grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs font-semibold text-ink-700 mb-1">From</label>
                            <input
                                v-model="dateFrom"
                                type="date"
                                class="w-full border border-ink-300 px-3 py-1.5 text-xs text-ink-900 focus:outline-none focus:border-teal-700"
                                @change="applyFilters"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-ink-700 mb-1">To</label>
                            <input
                                v-model="dateTo"
                                type="date"
                                class="w-full border border-ink-300 px-3 py-1.5 text-xs text-ink-900 focus:outline-none focus:border-teal-700"
                                @change="applyFilters"
                            />
                        </div>
                    </div>

                    <!-- PAYMENT METHOD -->
                    <div>
                        <label class="block text-xs font-semibold text-ink-700 mb-1">Payment Method</label>
                        <select
                            v-model="selectedPaymentMethod"
                            class="w-full border border-ink-300 px-3 py-1.5 text-xs text-ink-900 focus:outline-none focus:border-teal-700"
                            @change="applyFilters"
                        >
                            <option value="all">All Methods</option>
                            <option value="cash">Cash</option>
                            <option value="bkash">bKash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="other">Other / Deducted</option>
                        </select>
                    </div>

                    <!-- SOURCE -->
                    <div>
                        <label class="block text-xs font-semibold text-ink-700 mb-1">Source Origin</label>
                        <select
                            v-model="selectedSource"
                            class="w-full border border-ink-300 px-3 py-1.5 text-xs text-ink-900 focus:outline-none focus:border-teal-700"
                            @change="applyFilters"
                        >
                            <option value="all">All Sources</option>
                            <option value="manual">Manual Entry</option>
                            <option value="recurring">Recurring (1-Click)</option>
                            <option value="pathao_charge">Pathao Delivery</option>
                            <option value="return_courier">Return Courier</option>
                        </select>
                    </div>

                    <!-- ACTIONS -->
                    <div class="flex items-end gap-2">
                        <AppButton variant="secondary" size="sm" class="w-full" @click="applyFilters">
                            Filter
                        </AppButton>
                        <AppButton variant="ghost" size="sm" class="w-full" @click="resetFilters">
                            Reset
                        </AppButton>
                    </div>
                </div>
            </div>

            <!-- Active Filter Chips Row -->
            <div
                v-if="activeFilterChips.length > 0"
                class="flex flex-wrap items-center gap-2 pt-1 text-xs"
            >
                <span class="text-ink-600 font-semibold">Active filters:</span>
                <span
                    v-for="chip in activeFilterChips"
                    :key="chip.key"
                    class="inline-flex items-center px-2 py-1 bg-teal-100 text-teal-800 font-medium border border-teal-200"
                >
                    <span class="mr-1.5">{{ chip.label }}: <strong>{{ chip.value }}</strong></span>
                    <button
                        type="button"
                        class="hover:text-teal-950 font-bold p-0.5"
                        :aria-label="`Remove filter ${chip.label}`"
                        @click="removeFilter(chip.key)"
                    >
                        <AppIcon name="close" :size="12" />
                    </button>
                </span>
                <button
                    type="button"
                    class="text-xs text-ink-600 hover:text-danger font-semibold underline ml-1"
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
                aria-label="Filter Expenses"
            >
                <!-- Sticky Header -->
                <div class="h-[56px] px-4 border-b border-ink-200 flex items-center justify-between bg-white sticky top-0 z-10">
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-bold text-ink-900">Filter Expenses</h2>
                        <span
                            v-if="activeFilterChips.length > 0"
                            class="px-2 py-0.5 bg-teal-100 text-teal-800 text-xs font-bold"
                        >
                            {{ activeFilterChips.length }} active
                        </span>
                    </div>
                    <button
                        type="button"
                        class="p-2 text-ink-600 hover:bg-ink-50"
                        aria-label="Close filter sheet"
                        @click="isMobileFilterOpen = false"
                    >
                        <AppIcon name="close" :size="20" />
                    </button>
                </div>

                <!-- Scrollable Body with Filter Controls -->
                <div class="flex-1 overflow-y-auto p-4 space-y-4">
                    <div>
                        <label class="block text-xs font-bold uppercase text-ink-700 mb-1">Search Keyword</label>
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Vendor, note, category..."
                            class="w-full h-11 px-3 text-sm bg-ink-50 border border-ink-300 rounded-none focus:border-teal-700 focus:outline-none"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase text-ink-700 mb-1">Date Preset</label>
                        <select
                            v-model="datePreset"
                            class="w-full h-11 px-3 text-sm bg-ink-50 border border-ink-300 rounded-none focus:border-teal-700 focus:outline-none"
                            @change="onDatePresetChange"
                        >
                            <option value="this_month">This Month</option>
                            <option value="today">Today</option>
                            <option value="yesterday">Yesterday</option>
                            <option value="last_7_days">Last 7 Days</option>
                            <option value="last_30_days">Last 30 Days</option>
                            <option value="last_month">Last Month</option>
                            <option value="custom">Custom Date Range</option>
                        </select>
                    </div>

                    <div v-if="datePreset === 'custom' || dateFrom || dateTo" class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold uppercase text-ink-700 mb-1">From</label>
                            <input
                                v-model="dateFrom"
                                type="date"
                                class="w-full h-11 px-2 text-sm bg-ink-50 border border-ink-300 rounded-none"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-ink-700 mb-1">To</label>
                            <input
                                v-model="dateTo"
                                type="date"
                                class="w-full h-11 px-2 text-sm bg-ink-50 border border-ink-300 rounded-none"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase text-ink-700 mb-1">Category</label>
                        <select
                            v-model="selectedCategoryId"
                            class="w-full h-11 px-3 text-sm bg-ink-50 border border-ink-300 rounded-none focus:border-teal-700 focus:outline-none"
                        >
                            <option value="all">All Categories</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                {{ cat.name }} {{ !cat.is_active ? '(Inactive)' : '' }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase text-ink-700 mb-1">P&L Group</label>
                        <select
                            v-model="selectedPlGroup"
                            class="w-full h-11 px-3 text-sm bg-ink-50 border border-ink-300 rounded-none focus:border-teal-700 focus:outline-none"
                        >
                            <option value="all">All P&L Lines</option>
                            <option v-for="(label, key) in plGroups" :key="key" :value="key">
                                {{ label }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase text-ink-700 mb-1">Payment Method</label>
                        <select
                            v-model="selectedPaymentMethod"
                            class="w-full h-11 px-3 text-sm bg-ink-50 border border-ink-300 rounded-none focus:border-teal-700 focus:outline-none"
                        >
                            <option value="all">All Methods</option>
                            <option value="cash">Cash</option>
                            <option value="bkash">bKash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="other">Other / Deducted</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase text-ink-700 mb-1">Source Origin</label>
                        <select
                            v-model="selectedSource"
                            class="w-full h-11 px-3 text-sm bg-ink-50 border border-ink-300 rounded-none focus:border-teal-700 focus:outline-none"
                        >
                            <option value="all">All Sources</option>
                            <option value="manual">Manual Entry</option>
                            <option value="recurring">Recurring (1-Click)</option>
                            <option value="pathao_charge">Pathao Delivery</option>
                            <option value="return_courier">Return Courier</option>
                        </select>
                    </div>
                </div>

                <!-- Sticky Action Bar at Bottom -->
                <div class="p-4 border-t border-ink-200 bg-ink-50 flex items-center justify-between gap-3 sticky bottom-0 z-10">
                    <button
                        type="button"
                        class="text-xs text-ink-600 hover:text-danger underline font-semibold px-3 py-2"
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

            <!-- EXPENSES TABLE & MOBILE CARD LAYOUT -->
            <div class="bg-white border border-ink-200">
                <!-- DESKTOP TABLE VIEW -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-left text-xs text-ink-800">
                        <thead class="bg-ink-50 border-b border-ink-200 uppercase tracking-wider text-ink-600 font-bold sticky top-0 z-10">
                            <tr>
                                <th scope="col" class="p-3">Date</th>
                                <th scope="col" class="p-3">Category & P&L</th>
                                <th scope="col" class="p-3">Vendor / Payee</th>
                                <th scope="col" class="p-3">Method</th>
                                <th scope="col" class="p-3">Source</th>
                                <th scope="col" class="p-3 text-right">Amount</th>
                                <th scope="col" class="p-3 text-center">Receipt</th>
                                <th scope="col" class="p-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            <tr
                                v-for="exp in expenses.data"
                                :key="exp.id"
                                class="hover:bg-ink-50/75 transition-colors"
                            >
                                <!-- DATE -->
                                <td class="p-3 font-mono font-medium whitespace-nowrap">
                                    {{ exp.expense_date ? exp.expense_date.slice(0, 10) : '—' }}
                                </td>

                                <!-- CATEGORY & P&L GROUP -->
                                <td class="p-3">
                                    <div class="font-bold text-ink-900">
                                        {{ exp.category?.name || 'Uncategorized' }}
                                    </div>
                                    <div class="text-[11px] text-ink-500">
                                        {{ plGroups[exp.category?.pl_group || ''] || exp.category?.pl_group }}
                                    </div>
                                </td>

                                <!-- VENDOR / PAYEE & NOTE -->
                                <td class="p-3 max-w-xs">
                                    <div v-if="exp.vendor" class="font-medium text-ink-900">
                                        {{ exp.vendor }}
                                    </div>
                                    <div v-else class="text-ink-400 italic">
                                        No vendor specified
                                    </div>
                                    <div v-if="exp.note" class="text-[11px] text-ink-500 truncate" :title="exp.note">
                                        {{ exp.note }}
                                    </div>
                                    <div v-if="exp.sale" class="text-[11px] text-teal-700 font-mono">
                                        Inv #{{ exp.sale.invoice_no }}
                                    </div>
                                </td>

                                <!-- PAYMENT METHOD -->
                                <td class="p-3 whitespace-nowrap">
                                    <span class="inline-block px-2 py-0.5 text-[11px] font-semibold bg-ink-100 text-ink-800">
                                        {{ paymentMethodLabel(exp.payment_method) }}
                                    </span>
                                </td>

                                <!-- SOURCE -->
                                <td class="p-3 whitespace-nowrap">
                                    <StatusBadge
                                        :label="sourceBadge(exp.source).label"
                                        :variant="sourceBadge(exp.source).variant"
                                    />
                                    <div
                                        v-if="exp.courier_order"
                                        class="text-[10px] text-ink-500 font-mono mt-0.5"
                                    >
                                        {{ exp.courier_order.consignment_id }}
                                    </div>
                                </td>

                                <!-- AMOUNT -->
                                <td class="p-3 text-right font-mono font-bold text-ink-900 text-sm whitespace-nowrap">
                                    {{ formatMoney(exp.amount) }}
                                </td>

                                <!-- RECEIPT ATTACHMENT -->
                                <td class="p-3 text-center whitespace-nowrap">
                                    <a
                                        v-if="exp.attachment_path"
                                        :href="`/expenses/${exp.id}/attachment`"
                                        target="_blank"
                                        class="inline-flex items-center gap-1 text-teal-700 hover:text-teal-900 font-semibold text-xs border border-teal-300 bg-teal-50 px-2 py-1"
                                        title="Download authorized bill attachment"
                                    >
                                        <AppIcon name="download" :size="12" />
                                        <span>Receipt</span>
                                    </a>
                                    <span v-else class="text-ink-300">—</span>
                                </td>

                                <!-- ACTIONS -->
                                <td class="p-3 text-right whitespace-nowrap">
                                    <!-- SYSTEM COURIER LOGISTICS CHARGE: IMMUTABLE -->
                                    <div
                                        v-if="exp.source === 'pathao_charge' || exp.source === 'return_courier'"
                                        class="text-[11px] text-ink-400 italic"
                                        title="Courier fees are automatically linked to logistics and cannot be manually modified."
                                    >
                                        System linked
                                    </div>

                                    <!-- EDIT & DELETE FOR MANUAL / RECURRING EXPENSES -->
                                    <div v-else class="flex items-center justify-end gap-1">
                                        <AppButton
                                            v-if="can.edit"
                                            variant="ghost"
                                            size="sm"
                                            @click="openEditExpenseModal(exp)"
                                        >
                                            <AppIcon name="edit" :size="14" />
                                        </AppButton>
                                        <AppButton
                                            v-if="can.delete"
                                            variant="ghost"
                                            size="sm"
                                            class="text-red-600 hover:text-red-800"
                                            @click="confirmDeleteExpense(exp)"
                                        >
                                            <AppIcon name="trash" :size="14" />
                                        </AppButton>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- MOBILE CARD VIEW -->
                <div class="block md:hidden divide-y divide-ink-100">
                    <div
                        v-for="exp in expenses.data"
                        :key="exp.id"
                        class="p-4 space-y-2.5"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <span class="font-bold text-ink-900 text-sm">
                                    {{ exp.category?.name || 'Uncategorized' }}
                                </span>
                                <div class="text-xs text-ink-500 font-mono">
                                    {{ exp.expense_date ? exp.expense_date.slice(0, 10) : '—' }}
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-bold font-mono text-ink-900">
                                    {{ formatMoney(exp.amount) }}
                                </div>
                                <StatusBadge
                                    :label="sourceBadge(exp.source).label"
                                    :variant="sourceBadge(exp.source).variant"
                                />
                            </div>
                        </div>

                        <div class="text-xs text-ink-600 space-y-0.5">
                            <div v-if="exp.vendor">
                                <span class="text-ink-400">Vendor:</span> {{ exp.vendor }}
                            </div>
                            <div>
                                <span class="text-ink-400">Paid via:</span> {{ paymentMethodLabel(exp.payment_method) }}
                            </div>
                            <div v-if="exp.note" class="italic text-ink-500">
                                "{{ exp.note }}"
                            </div>
                            <div v-if="exp.sale" class="text-teal-700 font-mono font-semibold">
                                Invoice #{{ exp.sale.invoice_no }}
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-2 border-t border-ink-100">
                            <div>
                                <a
                                    v-if="exp.attachment_path"
                                    :href="`/expenses/${exp.id}/attachment`"
                                    target="_blank"
                                    class="inline-flex items-center gap-1 text-teal-700 font-semibold text-xs border border-teal-200 bg-teal-50 px-2 py-0.5"
                                >
                                    <AppIcon name="download" :size="12" />
                                    <span>Download Receipt</span>
                                </a>
                                <span v-else class="text-xs text-ink-400">No attachment</span>
                            </div>

                            <div v-if="exp.source !== 'pathao_charge' && exp.source !== 'return_courier'" class="flex items-center gap-1">
                                <AppButton
                                    v-if="can.edit"
                                    variant="ghost"
                                    size="sm"
                                    @click="openEditExpenseModal(exp)"
                                >
                                    Edit
                                </AppButton>
                                <AppButton
                                    v-if="can.delete"
                                    variant="ghost"
                                    size="sm"
                                    class="text-red-600"
                                    @click="confirmDeleteExpense(exp)"
                                >
                                    Delete
                                </AppButton>
                            </div>
                            <div v-else class="text-xs text-ink-400 italic">
                                Logistics linked
                            </div>
                        </div>
                    </div>
                </div>

                <!-- EMPTY STATE -->
                <EmptyState
                    v-if="expenses.data.length === 0"
                    title="No business expenses found"
                    description="No expense transactions match your active filters or date range."
                >
                    <template #action>
                        <AppButton v-if="can.create" variant="primary" @click="openAddExpenseModal">
                            <AppIcon name="plus" :size="16" class="mr-1.5" />
                            Record New Expense
                        </AppButton>
                    </template>
                </EmptyState>

                <!-- PAGINATION -->
                <TablePagination
                    v-if="expenses.data.length > 0"
                    :pagination="{
                        current_page: expenses.current_page,
                        last_page: expenses.last_page,
                        per_page: expenses.per_page,
                        total: expenses.total,
                        from: expenses.from,
                        to: expenses.to,
                        links: expenses.links
                    }"
                />
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- MODAL: ADD / EDIT EXPENSE FORM                          -->
        <!-- ======================================================== -->
        <div
            v-if="isExpenseModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-[#0F1B1A] bg-opacity-40"
        >
            <div class="bg-white border-2 border-teal-700 w-full max-w-xl p-6 space-y-4 max-h-[90vh] overflow-y-auto shadow-2xl">
                <div class="flex items-center justify-between border-b border-ink-200 pb-3">
                    <h2 class="text-base font-bold text-ink-900 uppercase tracking-wider">
                        {{ editingExpense ? 'Edit Business Expense' : 'Record Business Expense' }}
                    </h2>
                    <button
                        type="button"
                        class="text-ink-400 hover:text-ink-700 p-1"
                        @click="isExpenseModalOpen = false"
                    >
                        <AppIcon name="close" :size="18" />
                    </button>
                </div>

                <!-- PRD BOUNDARY RULE NOTICE BANNER (BR-5 & BR-9) -->
                <div class="bg-amber-50 border border-amber-300 p-3 text-xs text-amber-900 space-y-1">
                    <div class="font-bold flex items-center gap-1.5">
                        <AppIcon name="info" :size="14" class="text-amber-700" />
                        <span>Business Boundary Notice (PRD §3 BR-5 & BR-9)</span>
                    </div>
                    <p>
                        Leather & accessory purchases are inventory assets tracked in the <strong>Inventory</strong> module. Employee salaries & worker wages are recorded in the <strong>Payroll</strong> module. Do not record inventory or payroll items here.
                    </p>
                </div>

                <form class="space-y-4" @submit.prevent="submitExpense">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- EXPENSE DATE -->
                        <div>
                            <label class="block text-xs font-bold text-ink-700 mb-1">
                                Expense Date <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="expenseForm.expense_date"
                                type="date"
                                required
                                class="w-full border border-ink-300 px-3 py-2 text-xs text-ink-900 focus:outline-none focus:border-teal-700"
                            />
                            <div v-if="expenseForm.errors.expense_date" class="text-xs text-red-600 mt-1">
                                {{ expenseForm.errors.expense_date }}
                            </div>
                        </div>

                        <!-- CATEGORY -->
                        <div>
                            <label class="block text-xs font-bold text-ink-700 mb-1">
                                Category <span class="text-red-600">*</span>
                            </label>
                            <select
                                v-model="expenseForm.expense_category_id"
                                required
                                class="w-full border border-ink-300 px-3 py-2 text-xs text-ink-900 focus:outline-none focus:border-teal-700"
                            >
                                <option value="" disabled>Select category</option>
                                <option
                                    v-for="cat in activeCategories"
                                    :key="cat.id"
                                    :value="cat.id"
                                >
                                    {{ cat.name }} ({{ plGroups[cat.pl_group] || cat.pl_group }})
                                </option>
                            </select>
                            <div v-if="expenseForm.errors.expense_category_id" class="text-xs text-red-600 mt-1">
                                {{ expenseForm.errors.expense_category_id }}
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- AMOUNT -->
                        <div>
                            <label class="block text-xs font-bold text-ink-700 mb-1">
                                Amount (৳) <span class="text-red-600">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-2 text-xs font-bold text-ink-400">৳</span>
                                <input
                                    v-model="expenseForm.amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    required
                                    placeholder="0.00"
                                    class="w-full border border-ink-300 pl-7 pr-3 py-2 text-xs text-ink-900 font-mono font-bold focus:outline-none focus:border-teal-700"
                                />
                            </div>
                            <div v-if="expenseForm.errors.amount" class="text-xs text-red-600 mt-1">
                                {{ expenseForm.errors.amount }}
                            </div>
                        </div>

                        <!-- PAYMENT METHOD -->
                        <div>
                            <label class="block text-xs font-bold text-ink-700 mb-1">
                                Payment Method <span class="text-red-600">*</span>
                            </label>
                            <select
                                v-model="expenseForm.payment_method"
                                required
                                class="w-full border border-ink-300 px-3 py-2 text-xs text-ink-900 focus:outline-none focus:border-teal-700"
                            >
                                <option value="cash">Cash</option>
                                <option value="bkash">bKash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="other">Other / Deducted</option>
                            </select>
                            <div v-if="expenseForm.errors.payment_method" class="text-xs text-red-600 mt-1">
                                {{ expenseForm.errors.payment_method }}
                            </div>
                        </div>
                    </div>

                    <!-- VENDOR -->
                    <div>
                        <label class="block text-xs font-bold text-ink-700 mb-1">
                            Vendor / Payee <span class="text-ink-400 font-normal">(optional)</span>
                        </label>
                        <input
                            v-model="expenseForm.vendor"
                            type="text"
                            placeholder="e.g. Meta Ads, DESCO, Landlord Name, Courier"
                            class="w-full border border-ink-300 px-3 py-2 text-xs text-ink-900 focus:outline-none focus:border-teal-700"
                        />
                        <div v-if="expenseForm.errors.vendor" class="text-xs text-red-600 mt-1">
                            {{ expenseForm.errors.vendor }}
                        </div>
                    </div>

                    <!-- NOTE -->
                    <div>
                        <label class="block text-xs font-bold text-ink-700 mb-1">
                            Note / Memo <span class="text-ink-400 font-normal">(optional)</span>
                        </label>
                        <textarea
                            v-model="expenseForm.note"
                            rows="2"
                            placeholder="Additional details, campaign reference, or receipt number..."
                            class="w-full border border-ink-300 px-3 py-2 text-xs text-ink-900 focus:outline-none focus:border-teal-700"
                        />
                        <div v-if="expenseForm.errors.note" class="text-xs text-red-600 mt-1">
                            {{ expenseForm.errors.note }}
                        </div>
                    </div>

                    <!-- ATTACHMENT (PRIVATE DISK UPLOAD) -->
                    <div>
                        <label class="block text-xs font-bold text-ink-700 mb-1">
                            Bill / Receipt Attachment <span class="text-ink-400 font-normal">(photo or PDF, max 5MB)</span>
                        </label>
                        <input
                            type="file"
                            accept=".jpg,.jpeg,.png,.webp,.pdf"
                            class="w-full border border-ink-300 p-1.5 text-xs text-ink-900 focus:outline-none file:mr-3 file:py-1 file:px-2.5 file:border-0 file:text-xs file:font-semibold file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100"
                            @change="onFileSelected"
                        />
                        <span class="text-[11px] text-ink-500 mt-1 block">
                            Stored privately on local storage; downloads are authorized through controllers.
                        </span>
                        <div v-if="expenseForm.errors.attachment" class="text-xs text-red-600 mt-1">
                            {{ expenseForm.errors.attachment }}
                        </div>

                        <!-- IF EDITING WITH EXISTING ATTACHMENT -->
                        <div
                            v-if="editingExpense && editingExpense.attachment_path"
                            class="mt-2 flex items-center justify-between bg-ink-50 p-2 text-xs border border-ink-200"
                        >
                            <span class="text-ink-700 font-medium">Existing Receipt Attached</span>
                            <label class="flex items-center gap-1.5 text-red-600 font-semibold cursor-pointer">
                                <input
                                    v-model="expenseForm.remove_attachment"
                                    type="checkbox"
                                    class="rounded-none border-ink-300 text-teal-700"
                                />
                                <span>Remove attachment</span>
                            </label>
                        </div>
                    </div>

                    <!-- ACTIONS -->
                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-ink-200">
                        <AppButton
                            type="button"
                            variant="secondary"
                            @click="isExpenseModalOpen = false"
                        >
                            Cancel
                        </AppButton>
                        <AppButton
                            type="submit"
                            variant="primary"
                            :loading="expenseForm.processing"
                        >
                            {{ editingExpense ? 'Save Changes' : 'Record Expense' }}
                        </AppButton>
                    </div>
                </form>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- MODAL: CATEGORIES MANAGEMENT                            -->
        <!-- ======================================================== -->
        <div
            v-if="isCategoriesModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-[#0F1B1A] bg-opacity-40"
        >
            <div class="bg-white border-2 border-teal-700 w-full max-w-2xl p-6 space-y-5 max-h-[90vh] overflow-y-auto shadow-2xl">
                <div class="flex items-center justify-between border-b border-ink-200 pb-3">
                    <h2 class="text-base font-bold text-ink-900 uppercase tracking-wider">
                        Expense Categories & P&L Mappings
                    </h2>
                    <button
                        type="button"
                        class="text-ink-400 hover:text-ink-700 p-1"
                        @click="isCategoriesModalOpen = false"
                    >
                        <AppIcon name="close" :size="18" />
                    </button>
                </div>

                <!-- ADD / EDIT CATEGORY FORM -->
                <form
                    class="bg-ink-50 border border-ink-200 p-4 space-y-3"
                    @submit.prevent="submitCategory"
                >
                    <div class="font-bold text-xs uppercase text-ink-800">
                        {{ editingCategory ? `Edit Category "${editingCategory.name}"` : 'Add New Expense Category' }}
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-ink-700 mb-1">
                                Category Name <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="categoryForm.name"
                                type="text"
                                required
                                :disabled="editingCategory?.is_system"
                                placeholder="e.g. Office Supplies, Generator Fuel"
                                class="w-full border border-ink-300 px-3 py-1.5 text-xs text-ink-900 focus:outline-none focus:border-teal-700 disabled:bg-ink-100"
                            />
                            <div v-if="categoryForm.errors.name" class="text-xs text-red-600 mt-1">
                                {{ categoryForm.errors.name }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-ink-700 mb-1">
                                P&L Line Mapping <span class="text-red-600">*</span>
                            </label>
                            <select
                                v-model="categoryForm.pl_group"
                                required
                                :disabled="editingCategory?.is_system"
                                class="w-full border border-ink-300 px-3 py-1.5 text-xs text-ink-900 focus:outline-none focus:border-teal-700 disabled:bg-ink-100"
                            >
                                <option v-for="(label, key) in plGroups" :key="key" :value="key">
                                    {{ label }}
                                </option>
                            </select>
                            <div v-if="categoryForm.errors.pl_group" class="text-xs text-red-600 mt-1">
                                {{ categoryForm.errors.pl_group }}
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-1">
                        <label class="flex items-center gap-2 text-xs font-semibold text-ink-700 cursor-pointer">
                            <input
                                v-model="categoryForm.is_active"
                                type="checkbox"
                                class="rounded-none border-ink-300 text-teal-700"
                            />
                            <span>Category is Active (Available in forms)</span>
                        </label>

                        <div class="flex items-center gap-2">
                            <AppButton
                                v-if="editingCategory"
                                type="button"
                                variant="secondary"
                                size="sm"
                                @click="cancelEditCategory"
                            >
                                Cancel
                            </AppButton>
                            <AppButton
                                type="submit"
                                variant="primary"
                                size="sm"
                                :loading="categoryForm.processing"
                            >
                                {{ editingCategory ? 'Save' : 'Add Category' }}
                            </AppButton>
                        </div>
                    </div>
                </form>

                <!-- EXISTING CATEGORIES LIST -->
                <div class="border border-ink-200 overflow-hidden">
                    <table class="w-full text-left text-xs text-ink-800">
                        <thead class="bg-ink-100 uppercase tracking-wider text-ink-700 font-bold border-b border-ink-200">
                            <tr>
                                <th class="p-2.5">Category Name</th>
                                <th class="p-2.5">P&L Group</th>
                                <th class="p-2.5 text-center">Status</th>
                                <th class="p-2.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            <tr
                                v-for="cat in categories"
                                :key="cat.id"
                                class="hover:bg-ink-50"
                            >
                                <td class="p-2.5 font-bold text-ink-900">
                                    {{ cat.name }}
                                    <span
                                        v-if="cat.is_system"
                                        class="ml-1.5 text-[10px] bg-ink-200 text-ink-700 px-1 py-0.5 font-bold uppercase"
                                    >
                                        System
                                    </span>
                                </td>
                                <td class="p-2.5 text-ink-600">
                                    {{ plGroups[cat.pl_group] || cat.pl_group }}
                                </td>
                                <td class="p-2.5 text-center">
                                    <span
                                        :class="[
                                            'px-2 py-0.5 text-[10px] font-bold uppercase',
                                            cat.is_active ? 'bg-teal-100 text-teal-800' : 'bg-red-100 text-red-800'
                                        ]"
                                    >
                                        {{ cat.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="p-2.5 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            type="button"
                                            class="text-teal-700 hover:text-teal-900 font-semibold text-xs px-1.5 py-0.5"
                                            @click="startEditCategory(cat)"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            v-if="!cat.is_system"
                                            type="button"
                                            class="text-red-600 hover:text-red-800 font-semibold text-xs px-1.5 py-0.5"
                                            @click="deleteCategory(cat)"
                                        >
                                            Delete
                                        </button>
                                        <span v-else class="text-[10px] text-ink-400 italic">Locked</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end pt-2">
                    <AppButton variant="secondary" @click="isCategoriesModalOpen = false">
                        Done
                    </AppButton>
                </div>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- MODAL: RECURRING EXPENSE TEMPLATES                      -->
        <!-- ======================================================== -->
        <div
            v-if="isRecurringModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-[#0F1B1A] bg-opacity-40"
        >
            <div class="bg-white border-2 border-teal-700 w-full max-w-2xl p-6 space-y-5 max-h-[90vh] overflow-y-auto shadow-2xl">
                <div class="flex items-center justify-between border-b border-ink-200 pb-3">
                    <h2 class="text-base font-bold text-ink-900 uppercase tracking-wider">
                        Monthly Recurring Expense Templates
                    </h2>
                    <button
                        type="button"
                        class="text-ink-400 hover:text-ink-700 p-1"
                        @click="isRecurringModalOpen = false"
                    >
                        <AppIcon name="close" :size="18" />
                    </button>
                </div>

                <p class="text-xs text-ink-600">
                    Recurring expenses generate confirmation prompts on the 1st of each billing month. They are never posted silently or automatically.
                </p>

                <!-- ADD / EDIT TEMPLATE FORM -->
                <form
                    class="bg-ink-50 border border-ink-200 p-4 space-y-3"
                    @submit.prevent="submitRecurring"
                >
                    <div class="font-bold text-xs uppercase text-ink-800">
                        {{ editingRecurring ? 'Edit Recurring Template' : 'Add Recurring Monthly Template' }}
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-ink-700 mb-1">
                                Category <span class="text-red-600">*</span>
                            </label>
                            <select
                                v-model="recurringForm.expense_category_id"
                                required
                                class="w-full border border-ink-300 px-3 py-1.5 text-xs text-ink-900 focus:outline-none focus:border-teal-700"
                            >
                                <option value="" disabled>Select category</option>
                                <option v-for="cat in activeCategories" :key="cat.id" :value="cat.id">
                                    {{ cat.name }}
                                </option>
                            </select>
                            <div v-if="recurringForm.errors.expense_category_id" class="text-xs text-red-600 mt-1">
                                {{ recurringForm.errors.expense_category_id }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-ink-700 mb-1">
                                Amount (৳) <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="recurringForm.amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                required
                                placeholder="0.00"
                                class="w-full border border-ink-300 px-3 py-1.5 text-xs text-ink-900 font-mono font-bold focus:outline-none focus:border-teal-700"
                            />
                            <div v-if="recurringForm.errors.amount" class="text-xs text-red-600 mt-1">
                                {{ recurringForm.errors.amount }}
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-ink-700 mb-1">
                                Day of Month (1-28)
                            </label>
                            <input
                                v-model="recurringForm.day_of_month"
                                type="number"
                                min="1"
                                max="28"
                                class="w-full border border-ink-300 px-3 py-1.5 text-xs text-ink-900 focus:outline-none focus:border-teal-700"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-ink-700 mb-1">Vendor / Payee</label>
                            <input
                                v-model="recurringForm.vendor"
                                type="text"
                                placeholder="e.g. Landlord, ISP Company"
                                class="w-full border border-ink-300 px-3 py-1.5 text-xs text-ink-900 focus:outline-none focus:border-teal-700"
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-ink-700 mb-1">Payment Method</label>
                            <select
                                v-model="recurringForm.payment_method"
                                class="w-full border border-ink-300 px-3 py-1.5 text-xs text-ink-900 focus:outline-none focus:border-teal-700"
                            >
                                <option value="cash">Cash</option>
                                <option value="bkash">bKash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-ink-700 mb-1">Default Note</label>
                        <input
                            v-model="recurringForm.note"
                            type="text"
                            placeholder="e.g. Monthly shop rent payment"
                            class="w-full border border-ink-300 px-3 py-1.5 text-xs text-ink-900 focus:outline-none focus:border-teal-700"
                        />
                    </div>

                    <div class="flex items-center justify-between pt-1">
                        <label class="flex items-center gap-2 text-xs font-semibold text-ink-700 cursor-pointer">
                            <input
                                v-model="recurringForm.is_active"
                                type="checkbox"
                                class="rounded-none border-ink-300 text-teal-700"
                            />
                            <span>Template is Active</span>
                        </label>

                        <div class="flex items-center gap-2">
                            <AppButton
                                v-if="editingRecurring"
                                type="button"
                                variant="secondary"
                                size="sm"
                                @click="cancelEditRecurring"
                            >
                                Cancel
                            </AppButton>
                            <AppButton
                                type="submit"
                                variant="primary"
                                size="sm"
                                :loading="recurringForm.processing"
                            >
                                {{ editingRecurring ? 'Save' : 'Add Template' }}
                            </AppButton>
                        </div>
                    </div>
                </form>

                <!-- EXISTING TEMPLATES LIST -->
                <div class="border border-ink-200 overflow-hidden">
                    <table class="w-full text-left text-xs text-ink-800">
                        <thead class="bg-ink-100 uppercase tracking-wider text-ink-700 font-bold border-b border-ink-200">
                            <tr>
                                <th class="p-2.5">Category</th>
                                <th class="p-2.5">Vendor</th>
                                <th class="p-2.5">Day</th>
                                <th class="p-2.5 text-right">Amount</th>
                                <th class="p-2.5 text-center">Active</th>
                                <th class="p-2.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            <tr
                                v-for="t in recurringTemplates"
                                :key="t.id"
                                class="hover:bg-ink-50"
                            >
                                <td class="p-2.5 font-bold text-ink-900">
                                    {{ t.category?.name || 'Uncategorized' }}
                                </td>
                                <td class="p-2.5 text-ink-600">
                                    {{ t.vendor || '—' }}
                                </td>
                                <td class="p-2.5 text-ink-600">
                                    Day {{ t.day_of_month }}
                                </td>
                                <td class="p-2.5 text-right font-mono font-bold text-ink-900">
                                    {{ formatMoney(t.amount) }}
                                </td>
                                <td class="p-2.5 text-center">
                                    <span
                                        :class="[
                                            'px-2 py-0.5 text-[10px] font-bold uppercase',
                                            t.is_active ? 'bg-teal-100 text-teal-800' : 'bg-red-100 text-red-800'
                                        ]"
                                    >
                                        {{ t.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="p-2.5 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            type="button"
                                            class="text-teal-700 hover:text-teal-900 font-semibold text-xs px-1.5 py-0.5"
                                            @click="startEditRecurring(t)"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            class="text-red-600 hover:text-red-800 font-semibold text-xs px-1.5 py-0.5"
                                            @click="deleteRecurring(t)"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end pt-2">
                    <AppButton variant="secondary" @click="isRecurringModalOpen = false">
                        Done
                    </AppButton>
                </div>
            </div>
        </div>

        <!-- ======================================================== -->
        <!-- CONFIRM DELETE EXPENSE DIALOG                            -->
        <!-- ======================================================== -->
        <ConfirmDialog
            :open="isDeleteDialogOpen"
            title="Delete Expense"
            :description="`Are you sure you want to delete this expense of ${expenseToDelete ? formatMoney(expenseToDelete.amount) : ''}? This will soft-delete the record and preserve an audit log entry.`"
            confirm-label="Delete Expense"
            cancel-label="Cancel"
            danger
            @confirm="executeDeleteExpense"
            @cancel="isDeleteDialogOpen = false"
        />
</template>
