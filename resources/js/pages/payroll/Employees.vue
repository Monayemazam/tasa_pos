<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import PageHeader from '@/components/tasa/PageHeader.vue';
import AppButton from '@/components/tasa/AppButton.vue';
import AppIcon from '@/components/tasa/AppIcon.vue';
import StatCard from '@/components/tasa/StatCard.vue';
import StatusBadge, { type BadgeTone } from '@/components/tasa/StatusBadge.vue';
import FormField from '@/components/tasa/FormField.vue';
import MoneyInput from '@/components/tasa/MoneyInput.vue';
import PhoneInput from '@/components/tasa/PhoneInput.vue';
import ConfirmDialog from '@/components/tasa/ConfirmDialog.vue';
import SideDrawer from '@/components/tasa/SideDrawer.vue';
import EmptyState from '@/components/tasa/EmptyState.vue';
import TablePagination from '@/components/tasa/TablePagination.vue';
import { formatMoney, formatDate, formatBDPhone } from '@/lib/formatters';

interface PaymentDto {
    id: number;
    employee_id?: number;
    paid_on: string;
    amount: number;
    type: 'salary' | 'advance';
    method: string | null;
    note: string | null;
    created_by?: string | null;
    period?: string;
    period_label?: string | null;
    employee?: { id: number; name: string; phone: string | null };
}

interface SheetRowDto {
    employee_id: number;
    employee_name: string;
    employee_phone: string | null;
    employee_designation: string | null;
    employee_monthly_salary: number;
    is_active: boolean;
    is_archived: boolean;
    salary_month_id: number | null;
    is_generated: boolean;
    base_salary: number;
    bonus: number;
    deduction: number;
    payable: number;
    paid_in_month: number;
    month_balance: number;
    carry_forward: number;
    net_balance: number;
    status: 'fully_paid' | 'partially_paid' | 'over_paid' | 'unpaid';
    status_label: string;
    status_tone: BadgeTone;
    note: string | null;
    payments: PaymentDto[];
}

interface EmployeeDto {
    id: number;
    name: string;
    phone: string | null;
    designation: string | null;
    monthly_salary: number;
    joined_at: string | null;
    left_at: string | null;
    is_active: boolean;
    is_archived: boolean;
}

interface PaginatedPayments {
    data: PaymentDto[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

const props = defineProps<{
    month: string;
    period: string;
    periodLabel: string;
    sheetRows: SheetRowDto[];
    totals: {
        total_base: number;
        total_bonus: number;
        total_deduction: number;
        total_payable: number;
        total_paid: number;
        total_month_balance: number;
        total_carry_forward: number;
        total_net_balance: number;
        total_owed: number;
        total_overpaid: number;
        active_count: number;
    };
    employees: EmployeeDto[];
    payments: PaginatedPayments;
    filters: {
        month: string;
        search: string;
        status: string;
        tab?: string;
    };
    can: {
        create: boolean;
        update: boolean;
        delete: boolean;
    };
}>();

// UI States
const getInitialTab = (): 'sheet' | 'employees' | 'payments' => {
    if (props.filters?.tab && ['sheet', 'employees', 'payments'].includes(props.filters.tab)) {
        return props.filters.tab as 'sheet' | 'employees' | 'payments';
    }
    if (typeof window !== 'undefined') {
        const urlParams = new URLSearchParams(window.location.search);
        const tab = urlParams.get('tab');
        if (tab === 'employees' || tab === 'payments' || tab === 'sheet') {
            return tab;
        }
    }
    return 'sheet';
};

const activeTab = ref<'sheet' | 'employees' | 'payments'>(getInitialTab());

const setTab = (tab: 'sheet' | 'employees' | 'payments') => {
    activeTab.value = tab;
    if (typeof window !== 'undefined') {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        window.history.replaceState({}, '', url.toString());
    }
};

const searchQuery = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || 'all');
const currentMonthInput = ref(props.month);

// Drawers & Modals
const isEmployeeDrawerOpen = ref(false);
const isEditingEmployee = ref(false);
const isPaymentModalOpen = ref(false);
const isEditingPayment = ref(false);
const isSheetEditModalOpen = ref(false);
const isArchiveConfirmOpen = ref(false);
const selectedEmployee = ref<EmployeeDto | null>(null);
const selectedSheetRow = ref<SheetRowDto | null>(null);

// Forms
const employeeForm = useForm({
    id: null as number | null,
    name: '',
    phone: '',
    designation: '',
    monthly_salary: '0',
    joined_at: '',
    left_at: '',
    is_active: true,
});

const paymentForm = useForm({
    id: null as number | null,
    employee_id: null as number | null,
    period: props.month,
    paid_on: new Date().toISOString().slice(0, 10),
    amount: '0',
    type: 'salary' as 'salary' | 'advance',
    method: 'cash',
    note: '',
});

const sheetRowForm = useForm({
    salary_month_id: null as number | null,
    employee_id: null as number | null,
    period: props.month,
    base_salary: '0',
    bonus: '0',
    deduction: '0',
    note: '',
});

// Computed preview of payable for the edit modal
const previewPayable = computed(() => {
    const base = parseFloat(sheetRowForm.base_salary) || 0;
    const bonus = parseFloat(sheetRowForm.bonus) || 0;
    const ded = parseFloat(sheetRowForm.deduction) || 0;
    return base + bonus - ded;
});

// Navigation & Month change
const changeMonth = (newMonth: string) => {
    currentMonthInput.value = newMonth;
    router.get(
        '/payroll/employees',
        {
            month: newMonth,
            tab: activeTab.value,
            search: searchQuery.value,
            status: statusFilter.value,
        },
        { preserveState: true, replace: true }
    );
};

const prevMonth = () => {
    const [year, m] = currentMonthInput.value.split('-').map(Number);
    const d = new Date(year, m - 2, 1);
    const prev = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
    changeMonth(prev);
};

const nextMonth = () => {
    const [year, m] = currentMonthInput.value.split('-').map(Number);
    const d = new Date(year, m, 1);
    const next = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
    changeMonth(next);
};

const applyFilters = () => {
    router.get(
        '/payroll/employees',
        {
            month: currentMonthInput.value,
            tab: activeTab.value,
            search: searchQuery.value,
            status: statusFilter.value,
        },
        { preserveState: true, replace: true }
    );
};

// Handlers for Employees
const openNewEmployeeDrawer = () => {
    isEditingEmployee.value = false;
    employeeForm.reset();
    employeeForm.clearErrors();
    employeeForm.is_active = true;
    isEmployeeDrawerOpen.value = true;
};

const openEditEmployeeDrawer = (emp: EmployeeDto) => {
    isEditingEmployee.value = true;
    employeeForm.clearErrors();
    employeeForm.id = emp.id;
    employeeForm.name = emp.name;
    employeeForm.phone = emp.phone || '';
    employeeForm.designation = emp.designation || '';
    employeeForm.monthly_salary = String(emp.monthly_salary);
    employeeForm.joined_at = emp.joined_at || '';
    employeeForm.left_at = emp.left_at || '';
    employeeForm.is_active = emp.is_active;
    isEmployeeDrawerOpen.value = true;
};

const submitEmployeeForm = () => {
    if (isEditingEmployee.value && employeeForm.id) {
        employeeForm.put(`/payroll/employees/${employeeForm.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                isEmployeeDrawerOpen.value = false;
            },
        });
    } else {
        employeeForm.post('/payroll/employees', {
            preserveScroll: true,
            onSuccess: () => {
                isEmployeeDrawerOpen.value = false;
                setTab('employees');
            },
        });
    }
};

const confirmArchiveEmployee = (emp: EmployeeDto) => {
    selectedEmployee.value = emp;
    isArchiveConfirmOpen.value = true;
};

const executeArchiveEmployee = () => {
    if (!selectedEmployee.value) return;
    router.delete(`/payroll/employees/${selectedEmployee.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            isArchiveConfirmOpen.value = false;
            selectedEmployee.value = null;
        },
    });
};

const restoreEmployee = (empId: number) => {
    router.post(`/payroll/employees/${empId}/restore`, {}, {
        preserveScroll: true,
    });
};

// Handlers for Salary Month Sheet
const generateMonthSheet = () => {
    router.post('/payroll/months/generate', { period: currentMonthInput.value }, {
        preserveScroll: true,
    });
};

const openEditSheetRowModal = (row: SheetRowDto) => {
    selectedSheetRow.value = row;
    sheetRowForm.clearErrors();
    sheetRowForm.salary_month_id = row.salary_month_id;
    sheetRowForm.employee_id = row.employee_id;
    sheetRowForm.period = currentMonthInput.value;
    sheetRowForm.base_salary = String(row.base_salary);
    sheetRowForm.bonus = String(row.bonus);
    sheetRowForm.deduction = String(row.deduction);
    sheetRowForm.note = row.note || '';
    isSheetEditModalOpen.value = true;
};

const submitSheetRowForm = () => {
    const url = sheetRowForm.salary_month_id
        ? `/payroll/months/${sheetRowForm.salary_month_id}`
        : '/payroll/months';

    sheetRowForm.put(url, {
        preserveScroll: true,
        onSuccess: () => {
            isSheetEditModalOpen.value = false;
        },
    });
};

// Handlers for Payments
const openNewPaymentModal = (row?: SheetRowDto) => {
    isEditingPayment.value = false;
    paymentForm.reset();
    paymentForm.clearErrors();
    paymentForm.id = null;
    paymentForm.period = currentMonthInput.value;
    paymentForm.paid_on = new Date().toISOString().slice(0, 10);
    paymentForm.type = 'salary';
    paymentForm.method = 'cash';
    paymentForm.amount = '';

    if (row) {
        paymentForm.employee_id = row.employee_id;
        // Pre-fill remaining payable if positive
        if (row.net_balance > 0) {
            paymentForm.amount = String(row.net_balance);
        }
    } else if (props.employees.length > 0) {
        paymentForm.employee_id = props.employees[0].id;
    }

    isPaymentModalOpen.value = true;
};

const openEditPaymentModal = (pay: PaymentDto) => {
    isEditingPayment.value = true;
    paymentForm.clearErrors();
    paymentForm.id = pay.id;
    paymentForm.employee_id = pay.employee_id || pay.employee?.id || null;
    paymentForm.period = pay.period ? pay.period.slice(0, 7) : currentMonthInput.value;
    paymentForm.paid_on = pay.paid_on ? pay.paid_on.slice(0, 10) : new Date().toISOString().slice(0, 10);
    paymentForm.amount = String(pay.amount);
    paymentForm.type = pay.type;
    paymentForm.method = pay.method || 'cash';
    paymentForm.note = pay.note || '';
    isPaymentModalOpen.value = true;
};

const submitPaymentForm = () => {
    if (isEditingPayment.value && paymentForm.id) {
        paymentForm.put(`/payroll/payments/${paymentForm.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                isPaymentModalOpen.value = false;
            },
        });
    } else {
        paymentForm.post('/payroll/payments', {
            preserveScroll: true,
            onSuccess: () => {
                isPaymentModalOpen.value = false;
            },
        });
    }
};

const printSheet = () => {
    window.print();
};
</script>

<template>
    <Head :title="`Payroll — ${periodLabel}`" />

    <div class="space-y-6">
        <!-- Header Section -->
        <PageHeader
            title="Salaried Employees & Monthly Payroll"
            :subtitle="`Monthly salary sheets, advance tracking, carry-forward balances and payments · ${periodLabel}`"
        >
            <template #actions>
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Month Selector -->
                    <div class="flex items-center bg-surface border border-line-300 h-[36px]">
                        <button
                            type="button"
                            class="px-2.5 h-full hover:bg-surface-hover text-ink-700 transition"
                            title="Previous Month"
                            @click="prevMonth"
                        >
                            <AppIcon name="chevron-left" :size="14" />
                        </button>
                        <input
                            v-model="currentMonthInput"
                            type="month"
                            class="h-full px-2 text-xs font-semibold text-ink-900 bg-transparent border-0 focus:outline-none"
                            @change="changeMonth(currentMonthInput)"
                        />
                        <button
                            type="button"
                            class="px-2.5 h-full hover:bg-surface-hover text-ink-700 transition"
                            title="Next Month"
                            @click="nextMonth"
                        >
                            <AppIcon name="chevron-right" :size="14" />
                        </button>
                    </div>

                    <AppButton
                        variant="secondary"
                        icon="refresh"
                        title="Generate rows for active employees for this month"
                        @click="generateMonthSheet"
                    >
                        Generate Sheet
                    </AppButton>

                    <AppButton
                        v-if="can.create"
                        variant="primary"
                        icon="plus"
                        @click="openNewPaymentModal()"
                    >
                        Record Payment
                    </AppButton>

                    <AppButton
                        v-if="can.create"
                        variant="secondary"
                        icon="user"
                        @click="openNewEmployeeDrawer"
                    >
                        New Employee
                    </AppButton>

                    <AppButton
                        variant="ghost"
                        icon="printer"
                        title="Print Monthly Salary Sheet"
                        @click="printSheet"
                    >
                        Print
                    </AppButton>
                </div>
            </template>
        </PageHeader>

        <!-- KPI / Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <StatCard
                label="Earned Payable (P&L Expense)"
                :value="formatMoney(totals.total_payable)"
                hint="Base + bonus − deductions (accrual)"
            />
            <StatCard
                label="Total Cash Paid in Month"
                :value="formatMoney(totals.total_paid)"
                hint="Cash out-flow (does not hit P&L twice)"
            />
            <StatCard
                label="Total Owed to Staff"
                :value="formatMoney(totals.total_owed)"
                hint="Net unpaid balance across employees"
            />
            <StatCard
                label="Total Over-Paid / Advances"
                :value="formatMoney(totals.total_overpaid)"
                hint="Balances held by employees to carry forward"
            />
        </div>

        <!-- Navigation Tabs -->
        <div class="border-b border-line-200 mb-6 flex items-center justify-between">
            <nav class="flex space-x-6">
                <button
                    type="button"
                    :class="[
                        'pb-3 text-sm font-semibold border-b-2 transition flex items-center gap-1.5',
                        activeTab === 'sheet'
                            ? 'border-ink-900 text-ink-900'
                            : 'border-transparent text-ink-500 hover:text-ink-700 hover:border-line-300',
                    ]"
                    @click="setTab('sheet')"
                >
                    <AppIcon name="payroll" :size="16" />
                    <span>Monthly Salary Sheet</span>
                    <span class="ml-1 text-xs bg-sunken px-1.5 py-0.5 text-ink-600">
                        {{ sheetRows.length }}
                    </span>
                </button>

                <button
                    type="button"
                    :class="[
                        'pb-3 text-sm font-semibold border-b-2 transition flex items-center gap-1.5',
                        activeTab === 'employees'
                            ? 'border-ink-900 text-ink-900'
                            : 'border-transparent text-ink-500 hover:text-ink-700 hover:border-line-300',
                    ]"
                    @click="setTab('employees')"
                >
                    <AppIcon name="users" :size="16" />
                    <span>Employee Profiles</span>
                    <span class="ml-1 text-xs bg-sunken px-1.5 py-0.5 text-ink-600">
                        {{ employees.length }}
                    </span>
                </button>

                <button
                    type="button"
                    :class="[
                        'pb-3 text-sm font-semibold border-b-2 transition flex items-center gap-1.5',
                        activeTab === 'payments'
                            ? 'border-ink-900 text-ink-900'
                            : 'border-transparent text-ink-500 hover:text-ink-700 hover:border-line-300',
                    ]"
                    @click="setTab('payments')"
                >
                    <AppIcon name="receipt" :size="16" />
                    <span>Payment Records</span>
                    <span class="ml-1 text-xs bg-sunken px-1.5 py-0.5 text-ink-600">
                        {{ payments.total }}
                    </span>
                </button>
            </nav>

            <div class="text-xs text-ink-500 font-mono">
                Formula: Payable = Base + Bonus − Ded · Net = Carry-Fwd + (Payable − Paid)
            </div>
        </div>

        <!-- TAB 1: MONTHLY SALARY SHEET -->
        <div v-if="activeTab === 'sheet'" class="space-y-4">
            <!-- Filter Bar -->
            <div class="bg-surface border border-line-200 p-4 flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-3">
                    <div class="relative w-64">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-ink-400">
                            <AppIcon name="search" :size="14" />
                        </span>
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search employee by name, phone..."
                            class="w-full h-[36px] pl-9 pr-3 text-xs bg-surface border border-line-300 focus:outline-none focus:border-ink-900"
                            @keyup.enter="applyFilters"
                        />
                    </div>

                    <select
                        v-model="statusFilter"
                        class="h-[36px] px-3 text-xs bg-surface border border-line-300 focus:outline-none focus:border-ink-900"
                        @change="applyFilters"
                    >
                        <option value="all">All Statuses</option>
                        <option value="fully_paid">Fully Paid</option>
                        <option value="partially_paid">Partially Paid</option>
                        <option value="over_paid">Over-paid (Advances)</option>
                        <option value="unpaid">Unpaid</option>
                    </select>

                    <AppButton variant="secondary" icon="search" @click="applyFilters">
                        Filter
                    </AppButton>
                </div>

                <div class="flex items-center gap-2 text-xs text-ink-600">
                    <span class="flex items-center gap-1">
                        <span class="w-2.5 h-2.5 rounded-none bg-[#E7F4EA] border border-[#A8DBB5] inline-block"></span>
                        Fully Paid
                    </span>
                    <span class="flex items-center gap-1 ml-2">
                        <span class="w-2.5 h-2.5 rounded-none bg-[#FFF4DB] border border-[#E8D19B] inline-block"></span>
                        Partially Paid
                    </span>
                    <span class="flex items-center gap-1 ml-2">
                        <span class="w-2.5 h-2.5 rounded-none bg-[#F1EAF8] border border-[#D0BAEC] inline-block"></span>
                        Over-paid (Violet)
                    </span>
                </div>
            </div>

            <!-- Salary Sheet Table -->
            <div class="bg-surface border border-line-200 overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-sunken border-b border-line-200 text-ink-700 font-bold uppercase tracking-wider">
                            <th class="py-3 px-4">Employee</th>
                            <th class="py-3 px-4 text-right">Base Salary</th>
                            <th class="py-3 px-4 text-right">+ Bonus</th>
                            <th class="py-3 px-4 text-right">− Ded.</th>
                            <th class="py-3 px-4 text-right font-black bg-teal-50/50">Payable</th>
                            <th class="py-3 px-4 text-right">Paid in Month</th>
                            <th class="py-3 px-4 text-right">Month Balance</th>
                            <th class="py-3 px-4 text-right">Carry-Forward</th>
                            <th class="py-3 px-4 text-right font-black">Net Balance</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-100">
                        <tr
                            v-for="row in sheetRows"
                            :key="row.employee_id"
                            :class="[
                                'hover:bg-surface-hover transition',
                                row.net_balance < 0 ? 'bg-[#FAF6FD]/60' : '',
                            ]"
                        >
                            <!-- Employee Info -->
                            <td class="py-3 px-4">
                                <div class="font-bold text-ink-900 text-sm">
                                    {{ row.employee_name }}
                                </div>
                                <div class="text-ink-500 text-[11px] flex items-center gap-2">
                                    <span>{{ row.employee_designation || 'Staff' }}</span>
                                    <span v-if="row.employee_phone">· {{ formatBDPhone(row.employee_phone) }}</span>
                                    <span
                                        v-if="row.is_archived"
                                        class="text-danger font-semibold bg-red-50 px-1 border border-red-200"
                                    >
                                        Archived
                                    </span>
                                </div>
                                <div v-if="row.note" class="text-[11px] text-ink-500 italic mt-0.5">
                                    Note: {{ row.note }}
                                </div>
                            </td>

                            <!-- Base Salary -->
                            <td class="py-3 px-4 text-right tabular-nums text-ink-800">
                                <div>{{ formatMoney(row.base_salary) }}</div>
                                <div
                                    v-if="row.base_salary !== row.employee_monthly_salary"
                                    class="text-[10px] text-amber-700"
                                    title="Custom base salary for this month"
                                >
                                    (std: {{ formatMoney(row.employee_monthly_salary) }})
                                </div>
                            </td>

                            <!-- Bonus -->
                            <td class="py-3 px-4 text-right tabular-nums text-success font-medium">
                                {{ row.bonus > 0 ? `+${formatMoney(row.bonus)}` : '—' }}
                            </td>

                            <!-- Deduction -->
                            <td class="py-3 px-4 text-right tabular-nums text-danger font-medium">
                                {{ row.deduction > 0 ? `−${formatMoney(row.deduction)}` : '—' }}
                            </td>

                            <!-- Payable -->
                            <td class="py-3 px-4 text-right tabular-nums font-bold text-ink-900 bg-teal-50/50">
                                {{ formatMoney(row.payable) }}
                            </td>

                            <!-- Paid in Month -->
                            <td class="py-3 px-4 text-right tabular-nums font-semibold text-ink-900">
                                <div>{{ formatMoney(row.paid_in_month) }}</div>
                                <div v-if="row.payments.length > 0" class="text-[10px] text-ink-500">
                                    {{ row.payments.length }} payment(s)
                                </div>
                            </td>

                            <!-- Month Balance -->
                            <td class="py-3 px-4 text-right tabular-nums font-medium text-ink-800">
                                <span :class="row.month_balance < 0 ? 'text-[#6B3FA0] font-bold' : ''">
                                    {{ formatMoney(row.month_balance) }}
                                </span>
                            </td>

                            <!-- Carry-Forward -->
                            <td class="py-3 px-4 text-right tabular-nums font-medium">
                                <span
                                    v-if="row.carry_forward < 0"
                                    class="text-[#6B3FA0] font-bold bg-[#F1EAF8] px-1 py-0.5"
                                >
                                    {{ formatMoney(row.carry_forward) }}
                                </span>
                                <span
                                    v-else-if="row.carry_forward > 0"
                                    class="text-amber-800 font-semibold"
                                >
                                    +{{ formatMoney(row.carry_forward) }}
                                </span>
                                <span v-else class="text-ink-400">
                                    ৳ 0.00
                                </span>
                            </td>

                            <!-- Net Balance (PRD §10.1: positive = Tasa owes; negative = employee was over-paid in violet) -->
                            <td class="py-3 px-4 text-right tabular-nums font-black text-sm">
                                <span
                                    v-if="row.net_balance < 0"
                                    class="text-[#6B3FA0] bg-[#F1EAF8] px-1.5 py-0.5 border border-[#D0BAEC] inline-block font-mono"
                                    title="Employee holds an advance / over-paid and owes Tasa"
                                >
                                    {{ formatMoney(row.net_balance) }}
                                </span>
                                <span
                                    v-else-if="row.net_balance > 0"
                                    class="text-ink-900 font-mono"
                                    title="Tasa owes this employee"
                                >
                                    {{ formatMoney(row.net_balance) }}
                                </span>
                                <span v-else class="text-success font-mono">
                                    ৳ 0.00
                                </span>
                            </td>

                            <!-- Status Badge -->
                            <td class="py-3 px-4 text-center">
                                <StatusBadge
                                    :label="row.status_label"
                                    :tone="row.status_tone"
                                />
                            </td>

                            <!-- Actions -->
                            <td class="py-3 px-4 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    <button
                                        v-if="can.create"
                                        type="button"
                                        class="px-2 py-1 text-xs bg-ink-900 hover:bg-ink-800 text-white font-medium transition"
                                        title="Record payment for this employee"
                                        @click="openNewPaymentModal(row)"
                                    >
                                        Pay
                                    </button>

                                    <button
                                        v-if="can.update"
                                        type="button"
                                        class="px-2 py-1 text-xs border border-line-300 hover:bg-sunken text-ink-800 font-medium transition"
                                        title="Edit base salary, bonus or deduction for this month"
                                        @click="openEditSheetRowModal(row)"
                                    >
                                        Edit
                                    </button>

                                    <a
                                        :href="`/payroll/employees/${row.employee_id}/statement`"
                                        class="px-2 py-1 text-xs border border-line-300 hover:bg-sunken text-ink-800 font-medium transition inline-block"
                                        title="View printable employee statement"
                                    >
                                        Ledger
                                    </a>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="sheetRows.length === 0">
                            <td colspan="11" class="py-12 text-center text-ink-500">
                                <EmptyState
                                    icon="payroll"
                                    title="No salary records for this month"
                                    description="Click 'Generate Sheet' to initialize salary sheet rows for active employees, or click 'New Employee' to add your staff."
                                />
                            </td>
                        </tr>
                    </tbody>

                    <!-- Summary Footer Row -->
                    <tfoot v-if="sheetRows.length > 0" class="bg-sunken border-t-2 border-line-300 font-black text-ink-900">
                        <tr>
                            <td class="py-3 px-4">Total ({{ sheetRows.length }} employees)</td>
                            <td class="py-3 px-4 text-right tabular-nums">{{ formatMoney(totals.total_base) }}</td>
                            <td class="py-3 px-4 text-right tabular-nums text-success">+{{ formatMoney(totals.total_bonus) }}</td>
                            <td class="py-3 px-4 text-right tabular-nums text-danger">−{{ formatMoney(totals.total_deduction) }}</td>
                            <td class="py-3 px-4 text-right tabular-nums bg-teal-50/50">{{ formatMoney(totals.total_payable) }}</td>
                            <td class="py-3 px-4 text-right tabular-nums">{{ formatMoney(totals.total_paid) }}</td>
                            <td class="py-3 px-4 text-right tabular-nums">{{ formatMoney(totals.total_month_balance) }}</td>
                            <td class="py-3 px-4 text-right tabular-nums">{{ formatMoney(totals.total_carry_forward) }}</td>
                            <td class="py-3 px-4 text-right tabular-nums text-sm">
                                <span :class="totals.total_net_balance < 0 ? 'text-[#6B3FA0]' : ''">
                                    {{ formatMoney(totals.total_net_balance) }}
                                </span>
                            </td>
                            <td colspan="2" class="py-3 px-4 text-right text-[11px] text-ink-600 font-normal">
                                Owed: {{ formatMoney(totals.total_owed) }} · Adv: {{ formatMoney(totals.total_overpaid) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- TAB 2: EMPLOYEE PROFILES DIRECTORY -->
        <div v-else-if="activeTab === 'employees'" class="space-y-4">
            <div class="bg-surface border border-line-200 p-4 flex justify-between items-center">
                <div>
                    <h2 class="text-sm font-bold text-ink-900">Salaried Employee Profiles</h2>
                    <p class="text-xs text-ink-500">
                        Manage name, designation, phone, standard monthly salary, and active employment status.
                    </p>
                </div>
                <AppButton v-if="can.create" variant="primary" icon="plus" @click="openNewEmployeeDrawer">
                    Add Employee
                </AppButton>
            </div>

            <div class="bg-surface border border-line-200 overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-sunken border-b border-line-200 text-ink-700 font-bold uppercase tracking-wider">
                            <th class="py-3 px-4">Name</th>
                            <th class="py-3 px-4">Designation</th>
                            <th class="py-3 px-4">Phone</th>
                            <th class="py-3 px-4 text-right">Default Salary</th>
                            <th class="py-3 px-4">Joined Date</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-100">
                        <tr v-for="emp in employees" :key="emp.id" class="hover:bg-surface-hover transition">
                            <td class="py-3 px-4 font-bold text-ink-900 text-sm">
                                {{ emp.name }}
                            </td>
                            <td class="py-3 px-4 text-ink-700">
                                {{ emp.designation || '—' }}
                            </td>
                            <td class="py-3 px-4 font-mono text-ink-800">
                                {{ formatBDPhone(emp.phone) || '—' }}
                            </td>
                            <td class="py-3 px-4 text-right tabular-nums font-semibold text-ink-900">
                                {{ formatMoney(emp.monthly_salary) }}
                            </td>
                            <td class="py-3 px-4 text-ink-600">
                                {{ emp.joined_at ? formatDate(emp.joined_at) : '—' }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                <StatusBadge
                                    v-if="emp.is_archived"
                                    label="Archived"
                                    tone="danger"
                                />
                                <StatusBadge
                                    v-else-if="emp.is_active"
                                    label="Active"
                                    tone="success"
                                />
                                <StatusBadge
                                    v-else
                                    label="Inactive"
                                    tone="neutral"
                                />
                            </td>
                            <td class="py-3 px-4 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    <button
                                        v-if="!emp.is_archived && can.update"
                                        type="button"
                                        class="px-2 py-1 text-xs border border-line-300 hover:bg-sunken text-ink-800 font-medium transition"
                                        @click="openEditEmployeeDrawer(emp)"
                                    >
                                        Edit
                                    </button>

                                    <a
                                        :href="`/payroll/employees/${emp.id}/statement`"
                                        class="px-2 py-1 text-xs border border-line-300 hover:bg-sunken text-ink-800 font-medium transition inline-block"
                                    >
                                        Statement
                                    </a>

                                    <button
                                        v-if="!emp.is_archived && can.delete"
                                        type="button"
                                        class="px-2 py-1 text-xs border border-red-300 text-danger hover:bg-red-50 font-medium transition"
                                        title="Soft delete / archive employee"
                                        @click="confirmArchiveEmployee(emp)"
                                    >
                                        Archive
                                    </button>

                                    <button
                                        v-if="emp.is_archived && can.delete"
                                        type="button"
                                        class="px-2 py-1 text-xs border border-line-300 text-teal-800 hover:bg-teal-50 font-medium transition"
                                        @click="restoreEmployee(emp.id)"
                                    >
                                        Restore
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="employees.length === 0">
                            <td colspan="7" class="py-12 text-center text-ink-500">
                                <EmptyState
                                    icon="users"
                                    title="No employees registered"
                                    description="Click 'Add Employee' to register salaried staff members."
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 3: PAYMENT RECORDS LEDGER -->
        <div v-else-if="activeTab === 'payments'" class="space-y-4">
            <div class="bg-surface border border-line-200 p-4 flex justify-between items-center">
                <div>
                    <h2 class="text-sm font-bold text-ink-900">Payroll Payments Ledger</h2>
                    <p class="text-xs text-ink-500">
                        Audited log of every salary disbursement and employee advance for {{ periodLabel }}.
                    </p>
                </div>
                <AppButton v-if="can.create" variant="primary" icon="plus" @click="openNewPaymentModal()">
                    Record Payment
                </AppButton>
            </div>

            <div class="bg-surface border border-line-200 overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-sunken border-b border-line-200 text-ink-700 font-bold uppercase tracking-wider">
                            <th class="py-3 px-4">Paid On</th>
                            <th class="py-3 px-4">Employee</th>
                            <th class="py-3 px-4">Attributed Month</th>
                            <th class="py-3 px-4 text-center">Type</th>
                            <th class="py-3 px-4">Method</th>
                            <th class="py-3 px-4 text-right">Amount</th>
                            <th class="py-3 px-4">Note</th>
                            <th class="py-3 px-4">Recorded By</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-100">
                        <tr v-for="pay in payments.data" :key="pay.id" class="hover:bg-surface-hover transition">
                            <td class="py-3 px-4 font-medium text-ink-900 whitespace-nowrap">
                                {{ formatDate(pay.paid_on) }}
                            </td>
                            <td class="py-3 px-4 font-bold text-ink-900">
                                {{ pay.employee?.name || '—' }}
                            </td>
                            <td class="py-3 px-4 text-ink-700">
                                {{ pay.period ? formatDate(pay.period).slice(3) : '—' }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                <StatusBadge
                                    v-if="pay.type === 'advance'"
                                    label="Advance"
                                    tone="advance"
                                />
                                <StatusBadge
                                    v-else
                                    label="Salary"
                                    tone="neutral"
                                />
                            </td>
                            <td class="py-3 px-4 text-ink-700 capitalize">
                                {{ pay.method?.replace('_', ' ') || 'Cash' }}
                            </td>
                            <td class="py-3 px-4 text-right tabular-nums font-bold text-ink-900 text-sm">
                                {{ formatMoney(pay.amount) }}
                            </td>
                            <td class="py-3 px-4 text-ink-600 max-w-xs truncate">
                                {{ pay.note || '—' }}
                            </td>
                            <td class="py-3 px-4 text-ink-500">
                                {{ pay.created_by || 'System' }}
                            </td>
                            <td class="py-3 px-4 text-right whitespace-nowrap">
                                <button
                                    v-if="can.update"
                                    type="button"
                                    class="px-2 py-1 text-xs border border-line-300 hover:bg-sunken text-ink-800 font-medium transition"
                                    title="Edit payment entry (audited)"
                                    @click="openEditPaymentModal(pay)"
                                >
                                    Edit
                                </button>
                            </td>
                        </tr>

                        <tr v-if="payments.data.length === 0">
                            <td colspan="9" class="py-12 text-center text-ink-500">
                                <EmptyState
                                    icon="receipt"
                                    title="No payment records found"
                                    description="No payments have been recorded for this period yet. Use 'Record Payment' to record a salary or advance disbursement."
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <TablePagination
                v-if="payments.total > payments.per_page"
                :current-page="payments.current_page"
                :last-page="payments.last_page"
                :from="payments.from"
                :to="payments.to"
                :total="payments.total"
                :links="payments.links"
            />
        </div>

        <!-- DRAWER: ADD / EDIT EMPLOYEE -->
        <SideDrawer
            :open="isEmployeeDrawerOpen"
            :is-open="isEmployeeDrawerOpen"
            :title="isEditingEmployee ? 'Edit Employee Profile' : 'New Salaried Employee'"
            @close="isEmployeeDrawerOpen = false"
        >
            <form @submit.prevent="submitEmployeeForm" class="space-y-4">
                <FormField id="emp-name" label="Full Name" :required="true" :error="employeeForm.errors.name">
                    <input
                        id="emp-name"
                        v-model="employeeForm.name"
                        type="text"
                        placeholder="e.g. মোমিনুল হক (Mominul Haque)"
                        class="w-full h-[38px] px-3 text-sm text-ink-900 bg-surface border border-line-300 focus:outline-none focus:border-ink-900"
                        required
                    />
                </FormField>

                <FormField id="emp-phone" label="Phone Number" :error="employeeForm.errors.phone">
                    <PhoneInput id="emp-phone" v-model="employeeForm.phone" />
                </FormField>

                <FormField id="emp-designation" label="Designation / Role" :error="employeeForm.errors.designation">
                    <input
                        id="emp-designation"
                        v-model="employeeForm.designation"
                        type="text"
                        placeholder="e.g. Master Craftsman / Store Manager"
                        class="w-full h-[38px] px-3 text-sm text-ink-900 bg-surface border border-line-300 focus:outline-none focus:border-ink-900"
                    />
                </FormField>

                <FormField id="emp-salary" label="Default Monthly Salary (৳)" :required="true" :error="employeeForm.errors.monthly_salary">
                    <MoneyInput id="emp-salary" v-model="employeeForm.monthly_salary" />
                </FormField>

                <div class="grid grid-cols-2 gap-4">
                    <FormField id="emp-joined" label="Joining Date" :error="employeeForm.errors.joined_at">
                        <input
                            id="emp-joined"
                            v-model="employeeForm.joined_at"
                            type="date"
                            class="w-full h-[38px] px-3 text-sm text-ink-900 bg-surface border border-line-300 focus:outline-none focus:border-ink-900"
                        />
                    </FormField>

                    <FormField id="emp-left" label="Left Date (if inactive)" :error="employeeForm.errors.left_at">
                        <input
                            id="emp-left"
                            v-model="employeeForm.left_at"
                            type="date"
                            class="w-full h-[38px] px-3 text-sm text-ink-900 bg-surface border border-line-300 focus:outline-none focus:border-ink-900"
                        />
                    </FormField>
                </div>

                <div class="flex items-center gap-2 pt-2">
                    <input
                        id="emp-active"
                        v-model="employeeForm.is_active"
                        type="checkbox"
                        class="w-4 h-4 text-ink-900 border-line-300 focus:ring-0"
                    />
                    <label for="emp-active" class="text-xs font-semibold text-ink-900 cursor-pointer">
                        Active Salaried Employee (included in automatic monthly sheets)
                    </label>
                </div>

                <div class="pt-6 border-t border-line-200 flex justify-end gap-3">
                    <AppButton variant="secondary" @click="isEmployeeDrawerOpen = false">
                        Cancel
                    </AppButton>
                    <AppButton variant="primary" type="submit" :loading="employeeForm.processing">
                        {{ isEditingEmployee ? 'Update Profile' : 'Save Employee' }}
                    </AppButton>
                </div>
            </form>
        </SideDrawer>

        <!-- MODAL: RECORD PAYMENT -->
        <div
            v-if="isPaymentModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        >
            <div class="bg-surface border border-line-300 shadow-xl w-full max-w-lg p-6 relative">
                <div class="flex justify-between items-center pb-3 border-b border-line-200 mb-4">
                    <div>
                        <h3 class="text-base font-bold text-ink-900">
                            {{ isEditingPayment ? 'Edit Payment Record' : 'Record Salary or Advance Payment' }}
                        </h3>
                        <p class="text-xs text-ink-500">
                            {{ isEditingPayment ? 'Controlled edit with audit log history (PRD §10.1)' : `Disburse salary or employee advance for ${periodLabel}` }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="text-ink-400 hover:text-ink-700"
                        @click="isPaymentModalOpen = false"
                    >
                        <AppIcon name="close" :size="16" />
                    </button>
                </div>

                <form @submit.prevent="submitPaymentForm" class="space-y-4">
                    <FormField id="pay-emp" label="Employee" :required="true" :error="paymentForm.errors.employee_id">
                        <select
                            id="pay-emp"
                            v-model="paymentForm.employee_id"
                            class="w-full h-[38px] px-3 text-sm text-ink-900 bg-surface border border-line-300 focus:outline-none focus:border-ink-900"
                            required
                        >
                            <option
                                v-for="emp in employees.filter(e => !e.is_archived)"
                                :key="emp.id"
                                :value="emp.id"
                            >
                                {{ emp.name }} ({{ emp.designation || 'Staff' }})
                            </option>
                        </select>
                    </FormField>

                    <div class="grid grid-cols-2 gap-4">
                        <FormField id="pay-period" label="Attributed Month" :required="true" :error="paymentForm.errors.period">
                            <input
                                id="pay-period"
                                v-model="paymentForm.period"
                                type="month"
                                class="w-full h-[38px] px-3 text-sm text-ink-900 bg-surface border border-line-300 focus:outline-none focus:border-ink-900"
                                required
                            />
                        </FormField>

                        <FormField id="pay-date" label="Payment Date" :required="true" :error="paymentForm.errors.paid_on">
                            <input
                                id="pay-date"
                                v-model="paymentForm.paid_on"
                                type="date"
                                class="w-full h-[38px] px-3 text-sm text-ink-900 bg-surface border border-line-300 focus:outline-none focus:border-ink-900"
                                required
                            />
                        </FormField>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <FormField id="pay-type" label="Payment Type" :required="true" :error="paymentForm.errors.type">
                            <select
                                id="pay-type"
                                v-model="paymentForm.type"
                                class="w-full h-[38px] px-3 text-sm text-ink-900 bg-surface border border-line-300 focus:outline-none focus:border-ink-900"
                            >
                                <option value="salary">Salary (Regular payment)</option>
                                <option value="advance">Advance (Carried forward)</option>
                            </select>
                        </FormField>

                        <FormField id="pay-method" label="Payment Method" :error="paymentForm.errors.method">
                            <select
                                id="pay-method"
                                v-model="paymentForm.method"
                                class="w-full h-[38px] px-3 text-sm text-ink-900 bg-surface border border-line-300 focus:outline-none focus:border-ink-900"
                            >
                                <option value="cash">Cash</option>
                                <option value="bkash">bKash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                            </select>
                        </FormField>
                    </div>

                    <FormField id="pay-amount" label="Payment Amount (৳)" :required="true" :error="paymentForm.errors.amount">
                        <MoneyInput id="pay-amount" v-model="paymentForm.amount" />
                    </FormField>

                    <FormField id="pay-note" label="Note / Reference" :error="paymentForm.errors.note">
                        <input
                            id="pay-note"
                            v-model="paymentForm.note"
                            type="text"
                            placeholder="e.g. Mid-month advance for festival / bonus adjustment"
                            class="w-full h-[38px] px-3 text-sm text-ink-900 bg-surface border border-line-300 focus:outline-none focus:border-ink-900"
                        />
                    </FormField>

                    <div class="bg-amber-50 border border-amber-200 p-3 text-[11px] text-amber-900 leading-relaxed">
                        <strong>PRD Notice:</strong> Advances reduce employee balance and carry forward to future months.
                        They are recorded as cash movements and do not count as P&L earned salary expense a second time.
                    </div>

                    <div class="pt-4 border-t border-line-200 flex justify-end gap-3">
                        <AppButton variant="secondary" @click="isPaymentModalOpen = false">
                            Cancel
                        </AppButton>
                        <AppButton variant="primary" type="submit" :loading="paymentForm.processing">
                            {{ isEditingPayment ? 'Save Changes' : 'Confirm Payment' }}
                        </AppButton>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: EDIT SHEET ROW (BASE, BONUS, DEDUCTION) -->
        <div
            v-if="isSheetEditModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        >
            <div class="bg-surface border border-line-300 shadow-xl w-full max-w-md p-6 relative">
                <div class="flex justify-between items-center pb-3 border-b border-line-200 mb-4">
                    <div>
                        <h3 class="text-base font-bold text-ink-900">
                            Edit Month Sheet Row
                        </h3>
                        <p class="text-xs text-ink-500">
                            {{ selectedSheetRow?.employee_name }} · {{ periodLabel }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="text-ink-400 hover:text-ink-700"
                        @click="isSheetEditModalOpen = false"
                    >
                        <AppIcon name="close" :size="16" />
                    </button>
                </div>

                <form @submit.prevent="submitSheetRowForm" class="space-y-4">
                    <FormField
                        id="sheet-base"
                        label="Base Salary for this Month (৳)"
                        :required="true"
                        helper="Editable per month if salary changes (PRD §10.1)"
                        :error="sheetRowForm.errors.base_salary"
                    >
                        <MoneyInput id="sheet-base" v-model="sheetRowForm.base_salary" />
                    </FormField>

                    <div class="grid grid-cols-2 gap-4">
                        <FormField
                            id="sheet-bonus"
                            label="+ Bonus / Overtime (৳)"
                            :error="sheetRowForm.errors.bonus"
                        >
                            <MoneyInput id="sheet-bonus" v-model="sheetRowForm.bonus" />
                        </FormField>

                        <FormField
                            id="sheet-ded"
                            label="− Deductions (৳)"
                            :error="sheetRowForm.errors.deduction"
                        >
                            <MoneyInput id="sheet-ded" v-model="sheetRowForm.deduction" />
                        </FormField>
                    </div>

                    <!-- Calculated Payable Preview -->
                    <div class="bg-sunken border border-line-200 p-3 flex justify-between items-center text-xs">
                        <span class="font-semibold text-ink-700">Calculated Payable for Month:</span>
                        <span class="text-sm font-black text-ink-900 tabular-nums">
                            {{ formatMoney(previewPayable) }}
                        </span>
                    </div>

                    <FormField id="sheet-note" label="Month Note / Reason" :error="sheetRowForm.errors.note">
                        <input
                            id="sheet-note"
                            v-model="sheetRowForm.note"
                            type="text"
                            placeholder="e.g. Festival bonus / 2 days unpaid absence"
                            class="w-full h-[38px] px-3 text-sm text-ink-900 bg-surface border border-line-300 focus:outline-none focus:border-ink-900"
                        />
                    </FormField>

                    <div class="pt-4 border-t border-line-200 flex justify-end gap-3">
                        <AppButton variant="secondary" @click="isSheetEditModalOpen = false">
                            Cancel
                        </AppButton>
                        <AppButton variant="primary" type="submit" :loading="sheetRowForm.processing">
                            Save Sheet Row
                        </AppButton>
                    </div>
                </form>
            </div>
        </div>

        <!-- CONFIRM ARCHIVE DIALOG -->
        <ConfirmDialog
            :is-open="isArchiveConfirmOpen"
            title="Archive Employee Profile"
            :consequence-text="`Are you sure you want to archive ${selectedEmployee?.name}? In accordance with PRD §10.1, employee data is soft-deleted; all salary months, payments, and ledger balances are strictly preserved in history.`"
            confirm-button-text="Archive Employee"
            variant="danger"
            @confirm="executeArchiveEmployee"
            @cancel="isArchiveConfirmOpen = false"
        />
    </div>
</template>

<style scoped>
@media print {
    :deep(aside),
    :deep(header),
    .no-print,
    button,
    nav {
        display: none !important;
    }

    table {
        width: 100% !important;
        border: 1px solid #000 !important;
    }

    th, td {
        border: 1px solid #ddd !important;
        padding: 4px 6px !important;
        font-size: 10px !important;
    }
}
</style>
