<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/components/tasa/PageHeader.vue';
import AppButton from '@/components/tasa/AppButton.vue';
import AppIcon from '@/components/tasa/AppIcon.vue';
import StatCard from '@/components/tasa/StatCard.vue';
import StatusBadge, { type BadgeTone } from '@/components/tasa/StatusBadge.vue';
import { formatMoney, formatDate, formatBDPhone } from '@/lib/formatters';

interface PaymentRecord {
    id: number;
    period: string | null;
    period_label: string | null;
    paid_on: string | null;
    amount: number;
    type: 'salary' | 'advance';
    method: string | null;
    note: string | null;
    created_by?: string | null;
}

interface MonthRecord {
    period: string;
    period_label: string;
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
    payments: PaymentRecord[];
}

interface EmployeeStatementDto {
    employee: {
        id: number;
        name: string;
        phone: string | null;
        designation: string | null;
        monthly_salary: number;
        joined_at: string | null;
        left_at: string | null;
        is_active: boolean;
        is_archived: boolean;
    };
    months: MonthRecord[];
    payments: PaymentRecord[];
    totals: {
        total_earned: number;
        total_paid: number;
        current_net_balance: number;
        is_owed_by_tasa: boolean;
        is_overpaid: boolean;
        status_label: string;
        status_tone: BadgeTone;
    };
}

const props = defineProps<{
    statement: EmployeeStatementDto;
    filters: {
        from?: string | null;
        to?: string | null;
    };
    can: {
        create: boolean;
        update: boolean;
    };
}>();

const fromMonth = ref(props.filters.from || '');
const toMonth = ref(props.filters.to || '');

const applyFilter = () => {
    router.get(
        `/payroll/employees/${props.statement.employee.id}/statement`,
        {
            from: fromMonth.value || undefined,
            to: toMonth.value || undefined,
        },
        { preserveState: true, replace: true }
    );
};

const clearFilter = () => {
    fromMonth.value = '';
    toMonth.value = '';
    applyFilter();
};

const printStatement = () => {
    window.print();
};
</script>

<template>
    <Head :title="`Statement — ${statement.employee.name}`" />

    <div class="space-y-6">
        <!-- Top Action Bar (Hidden on Print) -->
        <div class="print:hidden">
            <PageHeader
                :title="`Salary Statement: ${statement.employee.name}`"
                :subtitle="`${statement.employee.designation || 'Staff'} · Standard Monthly Salary: ${formatMoney(statement.employee.monthly_salary)}`"
            >
                <template #actions>
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex items-center gap-2 bg-surface border border-line-300 p-1">
                            <label class="text-xs text-ink-600 pl-2">From:</label>
                            <input
                                v-model="fromMonth"
                                type="month"
                                class="h-[28px] px-2 text-xs text-ink-900 bg-transparent border-0 focus:outline-none"
                            />
                            <label class="text-xs text-ink-600">To:</label>
                            <input
                                v-model="toMonth"
                                type="month"
                                class="h-[28px] px-2 text-xs text-ink-900 bg-transparent border-0 focus:outline-none"
                            />
                            <button
                                type="button"
                                class="px-2.5 py-1 text-xs bg-ink-900 text-white hover:bg-ink-800 transition"
                                @click="applyFilter"
                            >
                                Filter
                            </button>
                            <button
                                v-if="fromMonth || toMonth"
                                type="button"
                                class="px-2 py-1 text-xs text-ink-500 hover:text-ink-800 transition"
                                @click="clearFilter"
                            >
                                Clear
                            </button>
                        </div>

                        <AppButton variant="secondary" icon="printer" @click="printStatement">
                            Print Statement
                        </AppButton>

                        <a
                            href="/payroll/employees"
                            class="inline-flex items-center gap-1.5 px-3 h-[36px] text-xs font-semibold border border-line-300 text-ink-700 bg-surface hover:bg-sunken transition"
                        >
                            <AppIcon name="chevron-left" :size="14" />
                            Back to Payroll
                        </a>
                    </div>
                </template>
            </PageHeader>
        </div>

        <!-- Printable Document Container -->
        <div class="bg-surface border border-line-200 p-8 max-w-5xl mx-auto printable-document">
            <!-- Printable Header -->
            <div class="border-b-2 border-ink-900 pb-4 mb-6 flex justify-between items-start">
                <div>
                    <h1 class="text-xl font-black text-ink-900 tracking-tight uppercase">
                        Tasa Leather Goods
                    </h1>
                    <div class="text-xs text-ink-600 font-mono mt-0.5">
                        SALARIED EMPLOYEE SALARY STATEMENT & RUNNING LEDGER
                    </div>
                </div>

                <div class="text-right text-xs text-ink-600 font-mono">
                    <div>Date Printed: {{ formatDate(new Date().toISOString()) }}</div>
                    <div v-if="filters.from || filters.to">
                        Period: {{ filters.from || 'Beginning' }} to {{ filters.to || 'Present' }}
                    </div>
                    <div v-else>Statement: Full Employment History</div>
                </div>
            </div>

            <!-- Employee Info Block -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 bg-sunken p-4 border border-line-200 mb-6 text-xs">
                <div>
                    <div class="text-ink-500 font-semibold uppercase text-[10px]">Employee Name</div>
                    <div class="font-bold text-ink-900 text-sm mt-0.5">{{ statement.employee.name }}</div>
                </div>
                <div>
                    <div class="text-ink-500 font-semibold uppercase text-[10px]">Designation</div>
                    <div class="font-medium text-ink-900 mt-0.5">{{ statement.employee.designation || 'Staff Member' }}</div>
                </div>
                <div>
                    <div class="text-ink-500 font-semibold uppercase text-[10px]">Phone Number</div>
                    <div class="font-mono text-ink-900 mt-0.5">{{ formatBDPhone(statement.employee.phone) || '—' }}</div>
                </div>
                <div>
                    <div class="text-ink-500 font-semibold uppercase text-[10px]">Joined Date</div>
                    <div class="font-medium text-ink-900 mt-0.5">
                        {{ statement.employee.joined_at ? formatDate(statement.employee.joined_at) : '—' }}
                    </div>
                </div>
            </div>

            <!-- Financial Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6 print:grid-cols-3">
                <StatCard
                    label="Total Earned (Payable)"
                    :value="formatMoney(statement.totals.total_earned)"
                    hint="Cumulative salary accrued"
                />
                <StatCard
                    label="Total Cash Paid"
                    :value="formatMoney(statement.totals.total_paid)"
                    hint="All salary and advance disbursements"
                />
                <div class="bg-surface border border-line-200 p-4">
                    <div class="text-xs uppercase font-semibold text-ink-500">Current Net Balance</div>
                    <div class="text-xl font-black mt-1 tabular-nums font-mono">
                        <span
                            v-if="statement.totals.current_net_balance < 0"
                            class="text-[#6B3FA0] bg-[#F1EAF8] px-2 py-0.5 border border-[#D0BAEC] inline-block"
                        >
                            {{ formatMoney(statement.totals.current_net_balance) }}
                        </span>
                        <span v-else class="text-ink-900">
                            {{ formatMoney(statement.totals.current_net_balance) }}
                        </span>
                    </div>
                    <div class="text-[11px] mt-1 font-medium text-ink-600">
                        {{ statement.totals.status_label }}
                    </div>
                </div>
            </div>

            <!-- Section 1: Month-by-Month Breakdown -->
            <div class="mb-8">
                <h2 class="text-sm font-bold text-ink-900 mb-2 uppercase tracking-wider border-b border-line-200 pb-1">
                    1. Monthly Salary Breakdown & Carry-Forward Ledger
                </h2>
                <div class="overflow-x-auto border border-line-200">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-sunken border-b border-line-200 text-ink-700 font-bold uppercase text-[11px]">
                                <th class="py-2.5 px-3">Month</th>
                                <th class="py-2.5 px-3 text-right">Base Salary</th>
                                <th class="py-2.5 px-3 text-right">+ Bonus</th>
                                <th class="py-2.5 px-3 text-right">− Ded.</th>
                                <th class="py-2.5 px-3 text-right font-black bg-teal-50/50">Payable</th>
                                <th class="py-2.5 px-3 text-right">Paid in Month</th>
                                <th class="py-2.5 px-3 text-right">Month Bal.</th>
                                <th class="py-2.5 px-3 text-right">Carry-Fwd</th>
                                <th class="py-2.5 px-3 text-right font-black">Net Balance</th>
                                <th class="py-2.5 px-3 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-100">
                            <tr
                                v-for="m in statement.months"
                                :key="m.period"
                                :class="m.net_balance < 0 ? 'bg-[#FAF6FD]/60' : ''"
                            >
                                <td class="py-2 px-3 font-bold text-ink-900 whitespace-nowrap">
                                    {{ m.period_label }}
                                </td>
                                <td class="py-2 px-3 text-right tabular-nums text-ink-700">
                                    {{ formatMoney(m.base_salary) }}
                                </td>
                                <td class="py-2 px-3 text-right tabular-nums text-success">
                                    {{ m.bonus > 0 ? `+${formatMoney(m.bonus)}` : '—' }}
                                </td>
                                <td class="py-2 px-3 text-right tabular-nums text-danger">
                                    {{ m.deduction > 0 ? `−${formatMoney(m.deduction)}` : '—' }}
                                </td>
                                <td class="py-2 px-3 text-right tabular-nums font-bold text-ink-900 bg-teal-50/50">
                                    {{ formatMoney(m.payable) }}
                                </td>
                                <td class="py-2 px-3 text-right tabular-nums font-semibold text-ink-900">
                                    {{ formatMoney(m.paid_in_month) }}
                                </td>
                                <td class="py-2 px-3 text-right tabular-nums font-medium text-ink-800">
                                    <span :class="m.month_balance < 0 ? 'text-[#6B3FA0] font-bold' : ''">
                                        {{ formatMoney(m.month_balance) }}
                                    </span>
                                </td>
                                <td class="py-2 px-3 text-right tabular-nums">
                                    <span v-if="m.carry_forward < 0" class="text-[#6B3FA0] font-bold">
                                        {{ formatMoney(m.carry_forward) }}
                                    </span>
                                    <span v-else-if="m.carry_forward > 0" class="text-amber-800 font-semibold">
                                        +{{ formatMoney(m.carry_forward) }}
                                    </span>
                                    <span v-else class="text-ink-400">
                                        ৳ 0.00
                                    </span>
                                </td>
                                <td class="py-2 px-3 text-right tabular-nums font-black text-sm">
                                    <span
                                        v-if="m.net_balance < 0"
                                        class="text-[#6B3FA0] font-bold"
                                    >
                                        {{ formatMoney(m.net_balance) }}
                                    </span>
                                    <span v-else class="text-ink-900">
                                        {{ formatMoney(m.net_balance) }}
                                    </span>
                                </td>
                                <td class="py-2 px-3 text-center">
                                    <StatusBadge :label="m.status_label" :tone="m.status_tone" />
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-sunken border-t-2 border-line-300 font-black text-ink-900">
                            <tr>
                                <td class="py-2.5 px-3">Total</td>
                                <td colspan="3"></td>
                                <td class="py-2.5 px-3 text-right tabular-nums bg-teal-50/50">
                                    {{ formatMoney(statement.totals.total_earned) }}
                                </td>
                                <td class="py-2.5 px-3 text-right tabular-nums">
                                    {{ formatMoney(statement.totals.total_paid) }}
                                </td>
                                <td colspan="2"></td>
                                <td class="py-2.5 px-3 text-right tabular-nums text-sm">
                                    <span :class="statement.totals.current_net_balance < 0 ? 'text-[#6B3FA0]' : ''">
                                        {{ formatMoney(statement.totals.current_net_balance) }}
                                    </span>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Section 2: Complete Payment Ledger Records -->
            <div class="mb-10">
                <h2 class="text-sm font-bold text-ink-900 mb-2 uppercase tracking-wider border-b border-line-200 pb-1">
                    2. Payment Transactions & Advance Disbursements
                </h2>
                <div class="overflow-x-auto border border-line-200">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-sunken border-b border-line-200 text-ink-700 font-bold uppercase text-[11px]">
                                <th class="py-2.5 px-3">Payment Date</th>
                                <th class="py-2.5 px-3">Attributed Month</th>
                                <th class="py-2.5 px-3 text-center">Type</th>
                                <th class="py-2.5 px-3">Method</th>
                                <th class="py-2.5 px-3">Note / Reference</th>
                                <th class="py-2.5 px-3 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-100">
                            <tr v-for="pay in statement.payments" :key="pay.id">
                                <td class="py-2 px-3 font-semibold text-ink-900 whitespace-nowrap">
                                    {{ formatDate(pay.paid_on) }}
                                </td>
                                <td class="py-2 px-3 text-ink-700">
                                    {{ pay.period_label || (pay.period ? formatDate(pay.period).slice(3) : '—') }}
                                </td>
                                <td class="py-2 px-3 text-center">
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
                                <td class="py-2 px-3 capitalize text-ink-700">
                                    {{ pay.method?.replace('_', ' ') || 'Cash' }}
                                </td>
                                <td class="py-2 px-3 text-ink-600">
                                    {{ pay.note || '—' }}
                                </td>
                                <td class="py-2 px-3 text-right tabular-nums font-bold text-ink-900">
                                    {{ formatMoney(pay.amount) }}
                                </td>
                            </tr>
                            <tr v-if="statement.payments.length === 0">
                                <td colspan="6" class="py-4 text-center text-ink-500 italic">
                                    No payment records found for this period.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-sunken border-t-2 border-line-300 font-bold text-ink-900">
                            <tr>
                                <td colspan="5" class="py-2 px-3 text-right">Total Payments Disbursed:</td>
                                <td class="py-2 px-3 text-right tabular-nums text-sm font-black">
                                    {{ formatMoney(statement.totals.total_paid) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Signature Blocks (Visible on Print) -->
            <div class="pt-12 border-t-2 border-line-300 grid grid-cols-3 gap-8 text-center text-xs">
                <div>
                    <div class="border-t border-ink-900 pt-1.5 font-bold text-ink-900">Prepared By</div>
                    <div class="text-[10px] text-ink-500 mt-0.5">Accountant / HR</div>
                </div>
                <div>
                    <div class="border-t border-ink-900 pt-1.5 font-bold text-ink-900">Approved By (Owner)</div>
                    <div class="text-[10px] text-ink-500 mt-0.5">Tasa Administration</div>
                </div>
                <div>
                    <div class="border-t border-ink-900 pt-1.5 font-bold text-ink-900">Employee Signature & Date</div>
                    <div class="text-[10px] text-ink-500 mt-0.5">{{ statement.employee.name }}</div>
                </div>
            </div>
        </div>
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

    body {
        background: #fff !important;
    }

    .printable-document {
        border: none !important;
        padding: 0 !important;
        max-width: 100% !important;
    }

    table {
        border: 1px solid #333 !important;
    }

    th, td {
        border: 1px solid #ddd !important;
        padding: 4px 6px !important;
        font-size: 10px !important;
    }
}
</style>
