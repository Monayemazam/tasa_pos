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

interface WorkerDto {
    id: number;
    name: string;
    phone: string | null;
    role: string | null;
    wage_basis: 'per_day' | 'per_piece' | 'fixed_weekly';
    rate: number;
    is_active: boolean;
    is_archived: boolean;
    earned_this_week: number;
    earned_this_month: number;
    total_earned: number;
    total_paid: number;
    current_balance: number;
    status_label: string;
    status_tone: BadgeTone;
}

interface EarningRecord {
    id: number;
    worker_id: number;
    work_date: string;
    period_start: string | null;
    qty: number;
    rate: number;
    amount: number;
    note: string | null;
    worker?: { id: number; name: string; role: string | null; wage_basis: string };
    creator?: { id: number; name: string };
}

interface PaymentRecord {
    id: number;
    worker_id: number;
    paid_on: string;
    amount: number;
    method: string | null;
    note: string | null;
    worker?: { id: number; name: string; role: string | null };
    creator?: { id: number; name: string };
}

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

const props = defineProps<{
    workers: WorkerDto[];
    totals: {
        total_workers: number;
        active_workers: number;
        total_earned_week: number;
        total_earned_month: number;
        total_earned_all: number;
        total_paid_all: number;
        total_net_balance: number;
        total_owed_by_tasa: number;
        total_overpaid_by_worker: number;
    };
    allWorkers: Array<{
        id: number;
        name: string;
        phone: string | null;
        role: string | null;
        wage_basis: 'per_day' | 'per_piece' | 'fixed_weekly';
        rate: number;
        is_active: boolean;
        is_archived: boolean;
    }>;
    recentEarnings: PaginatedData<EarningRecord>;
    recentPayments: PaginatedData<PaymentRecord>;
    filters: {
        search: string;
        wage_basis: string;
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
const getInitialTab = (): 'workers' | 'payout' | 'earnings' | 'payments' => {
    if (props.filters?.tab && ['workers', 'payout', 'earnings', 'payments'].includes(props.filters.tab)) {
        return props.filters.tab as 'workers' | 'payout' | 'earnings' | 'payments';
    }
    if (typeof window !== 'undefined') {
        const urlParams = new URLSearchParams(window.location.search);
        const tab = urlParams.get('tab');
        if (tab === 'payout' || tab === 'earnings' || tab === 'payments' || tab === 'workers') {
            return tab;
        }
    }
    return 'workers';
};

const activeTab = ref<'workers' | 'payout' | 'earnings' | 'payments'>(getInitialTab());

const setTab = (tab: 'workers' | 'payout' | 'earnings' | 'payments') => {
    activeTab.value = tab;
    if (typeof window !== 'undefined') {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        window.history.replaceState({}, '', url.toString());
    }
};

const searchQuery = ref(props.filters.search || '');
const basisFilter = ref(props.filters.wage_basis || 'all');
const statusFilter = ref(props.filters.status || 'all');

// Drawers & Modals
const isWorkerDrawerOpen = ref(false);
const isEditingWorker = ref(false);
const isEarningModalOpen = ref(false);
const isEditingEarning = ref(false);
const isPaymentModalOpen = ref(false);
const isEditingPayment = ref(false);
const isArchiveConfirmOpen = ref(false);
const selectedWorker = ref<WorkerDto | null>(null);

// Forms
const workerForm = useForm({
    id: null as number | null,
    name: '',
    phone: '',
    role: 'Stitcher',
    wage_basis: 'per_day' as 'per_day' | 'per_piece' | 'fixed_weekly',
    rate: '0',
    is_active: true,
});

const earningForm = useForm({
    id: null as number | null,
    worker_id: null as number | null,
    work_date: new Date().toISOString().slice(0, 10),
    period_start: '',
    qty: '1',
    rate: '0',
    amount: '0',
    note: '',
});

const paymentForm = useForm({
    id: null as number | null,
    worker_id: null as number | null,
    paid_on: new Date().toISOString().slice(0, 10),
    amount: '0',
    method: 'cash',
    note: '',
});

// Weekly Payout Helper Form
const payoutForm = useForm({
    paid_on: new Date().toISOString().slice(0, 10),
    method: 'cash',
    note: 'Weekly wage settlement',
    payouts: [] as Array<{ worker_id: number; name: string; balance: number; amount: string; selected: boolean }>,
});

// Sync payout list with workers
const initPayoutForm = () => {
    payoutForm.payouts = props.workers
        .filter((w) => !w.is_archived && w.is_active)
        .map((w) => ({
            worker_id: w.id,
            name: w.name,
            balance: w.current_balance,
            amount: w.current_balance > 0 ? String(w.current_balance) : '',
            selected: w.current_balance > 0,
        }));
};
initPayoutForm();

const totalSelectedPayout = computed(() => {
    return payoutForm.payouts.reduce((acc, row) => {
        if (!row.selected) return acc;
        const val = parseFloat(row.amount) || 0;
        return acc + (val > 0 ? val : 0);
    }, 0);
});

const selectedWorkerObject = computed(() => {
    if (!earningForm.worker_id) return null;
    return props.allWorkers.find((w) => w.id === earningForm.worker_id) || null;
});

// Computed auto-calculated earning amount
const computedEarningAmount = computed(() => {
    const q = parseFloat(earningForm.qty) || 0;
    const r = parseFloat(earningForm.rate) || 0;
    return Math.round(q * r * 100) / 100;
});

// Handlers for Filters
const applyFilters = () => {
    router.get(
        '/payroll/workers',
        {
            search: searchQuery.value,
            wage_basis: basisFilter.value,
            status: statusFilter.value,
            tab: activeTab.value,
        },
        { preserveState: true, replace: true }
    );
};

// Handlers for Workers
const openNewWorkerDrawer = () => {
    isEditingWorker.value = false;
    workerForm.reset();
    workerForm.clearErrors();
    workerForm.wage_basis = 'per_day';
    workerForm.is_active = true;
    workerForm.rate = '0';
    isWorkerDrawerOpen.value = true;
};

const openEditWorkerDrawer = (w: WorkerDto) => {
    isEditingWorker.value = true;
    workerForm.clearErrors();
    workerForm.id = w.id;
    workerForm.name = w.name;
    workerForm.phone = w.phone || '';
    workerForm.role = w.role || '';
    workerForm.wage_basis = w.wage_basis;
    workerForm.rate = String(w.rate);
    workerForm.is_active = w.is_active;
    isWorkerDrawerOpen.value = true;
};

const submitWorkerForm = () => {
    if (isEditingWorker.value && workerForm.id) {
        workerForm.put(`/payroll/workers/${workerForm.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                isWorkerDrawerOpen.value = false;
                initPayoutForm();
            },
        });
    } else {
        workerForm.post('/payroll/workers', {
            preserveScroll: true,
            onSuccess: () => {
                isWorkerDrawerOpen.value = false;
                setTab('workers');
                initPayoutForm();
            },
        });
    }
};

const confirmArchiveWorker = (w: WorkerDto) => {
    selectedWorker.value = w;
    isArchiveConfirmOpen.value = true;
};

const executeArchiveWorker = () => {
    if (!selectedWorker.value) return;
    router.delete(`/payroll/workers/${selectedWorker.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            isArchiveConfirmOpen.value = false;
            selectedWorker.value = null;
            initPayoutForm();
        },
    });
};

const restoreWorker = (workerId: number) => {
    router.post(`/payroll/workers/${workerId}/restore`, {}, {
        preserveScroll: true,
        onSuccess: () => initPayoutForm(),
    });
};

// Handlers for Earnings
const openNewEarningModal = (w?: WorkerDto) => {
    isEditingEarning.value = false;
    earningForm.reset();
    earningForm.clearErrors();
    earningForm.work_date = new Date().toISOString().slice(0, 10);
    earningForm.period_start = '';
    earningForm.qty = '1';
    earningForm.amount = '';

    if (w) {
        earningForm.worker_id = w.id;
        earningForm.rate = String(w.rate);
    } else if (props.allWorkers.length > 0) {
        const first = props.allWorkers.find((item) => !item.is_archived) || props.allWorkers[0];
        earningForm.worker_id = first.id;
        earningForm.rate = String(first.rate);
    }

    isEarningModalOpen.value = true;
};

const onWorkerSelectChange = () => {
    const w = selectedWorkerObject.value;
    if (w) {
        earningForm.rate = String(w.rate);
        if (w.wage_basis === 'fixed_weekly') {
            earningForm.qty = '1';
            // Pre-fill period start as 6 days prior
            const d = new Date(earningForm.work_date);
            d.setDate(d.getDate() - 6);
            earningForm.period_start = d.toISOString().slice(0, 10);
        }
    }
};

const openEditEarningModal = (e: EarningRecord) => {
    isEditingEarning.value = true;
    earningForm.clearErrors();
    earningForm.id = e.id;
    earningForm.worker_id = e.worker_id;
    earningForm.work_date = e.work_date.slice(0, 10);
    earningForm.period_start = e.period_start ? e.period_start.slice(0, 10) : '';
    earningForm.qty = String(e.qty);
    earningForm.rate = String(e.rate);
    earningForm.amount = String(e.amount);
    earningForm.note = e.note || '';
    isEarningModalOpen.value = true;
};

const submitEarningForm = () => {
    // If amount is left blank, auto-fill from computed
    if (!earningForm.amount || parseFloat(earningForm.amount) <= 0) {
        earningForm.amount = String(computedEarningAmount.value);
    }

    if (isEditingEarning.value && earningForm.id) {
        earningForm.put(`/payroll/workers/earnings/${earningForm.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                isEarningModalOpen.value = false;
                initPayoutForm();
            },
        });
    } else {
        earningForm.post('/payroll/workers/earnings', {
            preserveScroll: true,
            onSuccess: () => {
                isEarningModalOpen.value = false;
                initPayoutForm();
            },
        });
    }
};

// Handlers for Payments
const openNewPaymentModal = (w?: WorkerDto) => {
    isEditingPayment.value = false;
    paymentForm.reset();
    paymentForm.clearErrors();
    paymentForm.paid_on = new Date().toISOString().slice(0, 10);
    paymentForm.method = 'cash';
    paymentForm.amount = '';

    if (w) {
        paymentForm.worker_id = w.id;
        if (w.current_balance > 0) {
            paymentForm.amount = String(w.current_balance);
        }
    } else if (props.allWorkers.length > 0) {
        const first = props.allWorkers.find((item) => !item.is_archived) || props.allWorkers[0];
        paymentForm.worker_id = first.id;
    }

    isPaymentModalOpen.value = true;
};

const openEditPaymentModal = (p: PaymentRecord) => {
    isEditingPayment.value = true;
    paymentForm.clearErrors();
    paymentForm.id = p.id;
    paymentForm.worker_id = p.worker_id;
    paymentForm.paid_on = p.paid_on.slice(0, 10);
    paymentForm.amount = String(p.amount);
    paymentForm.method = p.method || 'cash';
    paymentForm.note = p.note || '';
    isPaymentModalOpen.value = true;
};

const submitPaymentForm = () => {
    if (isEditingPayment.value && paymentForm.id) {
        paymentForm.put(`/payroll/workers/payments/${paymentForm.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                isPaymentModalOpen.value = false;
                initPayoutForm();
            },
        });
    } else {
        paymentForm.post('/payroll/workers/payments', {
            preserveScroll: true,
            onSuccess: () => {
                isPaymentModalOpen.value = false;
                initPayoutForm();
            },
        });
    }
};

// Handlers for Batch Weekly Payout
const toggleAllPayouts = (selected: boolean) => {
    payoutForm.payouts.forEach((p) => {
        p.selected = selected && p.balance > 0;
    });
};

const submitBatchPayout = () => {
    // Filter down to selected with amount > 0
    const filteredPayload = {
        paid_on: payoutForm.paid_on,
        method: payoutForm.method,
        note: payoutForm.note,
        payouts: payoutForm.payouts
            .filter((p) => p.selected && parseFloat(p.amount) > 0)
            .map((p) => ({
                worker_id: p.worker_id,
                amount: parseFloat(p.amount),
            })),
    };

    router.post('/payroll/workers/batch-payout', filteredPayload, {
        preserveScroll: true,
        onSuccess: () => {
            initPayoutForm();
        },
    });
};
</script>

<template>
    <Head title="Wage Workers — Payroll" />

    <div class="space-y-6">
        <PageHeader
            title="Wage Workers & Running Ledgers"
            subtitle="Manage piece-rate, daily-wage, and weekly workers, record earnings, track running balances, and execute weekly payouts"
        >
            <template #actions>
                <div class="flex flex-wrap items-center gap-2">
                    <AppButton
                        v-if="can.create"
                        variant="secondary"
                        icon="receipt"
                        @click="openNewEarningModal()"
                    >
                        Log Earning
                    </AppButton>

                    <AppButton
                        v-if="can.create"
                        variant="secondary"
                        icon="plus"
                        @click="openNewPaymentModal()"
                    >
                        Record Payment
                    </AppButton>

                    <AppButton
                        v-if="can.create"
                        variant="primary"
                        icon="user"
                        @click="openNewWorkerDrawer"
                    >
                        New Worker
                    </AppButton>
                </div>
            </template>
        </PageHeader>

        <!-- KPI Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <StatCard
                label="Earned This Week"
                :value="formatMoney(totals.total_earned_week)"
                hint="Current week wage accrual (Monday–Sunday)"
            />
            <StatCard
                label="Earned This Month"
                :value="formatMoney(totals.total_earned_month)"
                hint="Total accrued earnings this calendar month"
            />
            <StatCard
                label="Total Cash Paid"
                :value="formatMoney(totals.total_paid_all)"
                hint="Cumulative disbursements to all workers"
            />
            <div class="bg-surface border border-line-200 p-4">
                <div class="text-xs uppercase font-semibold text-ink-500">Current Net Balance</div>
                <div class="text-xl font-black mt-1 tabular-nums font-mono">
                    <span
                        v-if="totals.total_net_balance < 0"
                        class="text-[#6B3FA0] bg-[#F1EAF8] px-2 py-0.5 border border-[#D0BAEC] inline-block"
                        title="Workers are over-paid / hold advances and owe Tasa"
                    >
                        {{ formatMoney(totals.total_net_balance) }}
                    </span>
                    <span v-else class="text-ink-900">
                        {{ formatMoney(totals.total_net_balance) }}
                    </span>
                </div>
                <div class="text-[11px] mt-1 font-medium text-ink-600 flex justify-between">
                    <span>Owed: {{ formatMoney(totals.total_owed_by_tasa) }}</span>
                    <span v-if="totals.total_overpaid_by_worker > 0" class="text-[#6B3FA0] font-bold">
                        Adv: {{ formatMoney(totals.total_overpaid_by_worker) }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="border-b border-line-200 mb-6 flex space-x-6">
            <button
                type="button"
                :class="[
                    'pb-3 text-sm font-bold border-b-2 transition flex items-center gap-2',
                    activeTab === 'workers'
                        ? 'border-ink-900 text-ink-900'
                        : 'border-transparent text-ink-500 hover:text-ink-800',
                ]"
                @click="setTab('workers')"
            >
                <AppIcon name="user" :size="16" />
                <span>Wage Workers & Balances ({{ workers.length }})</span>
            </button>

            <button
                type="button"
                :class="[
                    'pb-3 text-sm font-bold border-b-2 transition flex items-center gap-2',
                    activeTab === 'payout'
                        ? 'border-ink-900 text-ink-900'
                        : 'border-transparent text-ink-500 hover:text-ink-800',
                ]"
                @click="setTab('payout')"
            >
                <AppIcon name="receipt" :size="16" />
                <span>Weekly Payout Helper</span>
            </button>

            <button
                type="button"
                :class="[
                    'pb-3 text-sm font-bold border-b-2 transition flex items-center gap-2',
                    activeTab === 'earnings'
                        ? 'border-ink-900 text-ink-900'
                        : 'border-transparent text-ink-500 hover:text-ink-800',
                ]"
                @click="setTab('earnings')"
            >
                <AppIcon name="calendar" :size="16" />
                <span>Earnings Ledger</span>
            </button>

            <button
                type="button"
                :class="[
                    'pb-3 text-sm font-bold border-b-2 transition flex items-center gap-2',
                    activeTab === 'payments'
                        ? 'border-ink-900 text-ink-900'
                        : 'border-transparent text-ink-500 hover:text-ink-800',
                ]"
                @click="setTab('payments')"
            >
                <AppIcon name="expenses" :size="16" />
                <span>Payments Ledger</span>
            </button>
        </div>

        <!-- TAB 1: WORKERS DIRECTORY & RUNNING BALANCES -->
        <div v-if="activeTab === 'workers'" class="space-y-4">
            <!-- Filter Bar -->
            <div class="bg-surface border border-line-200 p-3 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-3 flex-1">
                    <div class="relative w-full max-w-xs">
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search by worker name, role, phone..."
                            class="w-full h-[36px] pl-9 pr-3 text-xs text-ink-900 bg-surface border border-line-300 focus:outline-none focus:border-ink-900"
                            @keyup.enter="applyFilters"
                        />
                        <AppIcon
                            name="search"
                            :size="14"
                            class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-400"
                        />
                    </div>

                    <select
                        v-model="basisFilter"
                        class="h-[36px] px-3 text-xs text-ink-900 bg-surface border border-line-300 focus:outline-none"
                        @change="applyFilters"
                    >
                        <option value="all">All Wage Bases</option>
                        <option value="per_day">Per Day (Daily wage)</option>
                        <option value="per_piece">Per Piece (Piece rate)</option>
                        <option value="fixed_weekly">Fixed Weekly</option>
                    </select>

                    <select
                        v-model="statusFilter"
                        class="h-[36px] px-3 text-xs text-ink-900 bg-surface border border-line-300 focus:outline-none"
                        @change="applyFilters"
                    >
                        <option value="all">All Statuses</option>
                        <option value="active">Active Workers</option>
                        <option value="owed">Tasa Owes Worker (> 0)</option>
                        <option value="overpaid">Worker Over-paid (&lt; 0 in violet)</option>
                        <option value="archived">Archived Workers</option>
                    </select>

                    <AppButton variant="secondary" size="sm" @click="applyFilters">
                        Filter
                    </AppButton>
                </div>
            </div>

            <!-- Workers Table -->
            <div class="bg-surface border border-line-200 overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-sunken border-b border-line-200 text-ink-700 font-bold uppercase tracking-wider">
                            <th class="py-3 px-4">Worker Profile</th>
                            <th class="py-3 px-4">Wage Basis</th>
                            <th class="py-3 px-4 text-right">Standard Rate</th>
                            <th class="py-3 px-4 text-right">Earned This Week</th>
                            <th class="py-3 px-4 text-right">Earned This Month</th>
                            <th class="py-3 px-4 text-right">Total Earned</th>
                            <th class="py-3 px-4 text-right">Total Paid</th>
                            <th class="py-3 px-4 text-right font-black">Current Balance</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-100">
                        <tr
                            v-for="w in workers"
                            :key="w.id"
                            :class="[
                                'hover:bg-surface-hover transition',
                                w.is_archived ? 'opacity-60 bg-sunken/40' : '',
                            ]"
                        >
                            <!-- Name & Role -->
                            <td class="py-3 px-4">
                                <div class="font-bold text-ink-900 text-sm flex items-center gap-1.5">
                                    <span>{{ w.name }}</span>
                                    <span v-if="w.is_archived" class="text-[10px] text-danger bg-danger/10 px-1 py-0.5">
                                        Archived
                                    </span>
                                </div>
                                <div class="text-[11px] text-ink-500 mt-0.5 flex items-center gap-2">
                                    <span>{{ w.role || 'Artisan' }}</span>
                                    <span v-if="w.phone">· {{ formatBDPhone(w.phone) }}</span>
                                </div>
                            </td>

                            <!-- Basis Badge -->
                            <td class="py-3 px-4">
                                <span
                                    v-if="w.wage_basis === 'per_day'"
                                    class="inline-block px-2 py-0.5 text-[11px] font-semibold bg-blue-50 text-blue-800 border border-blue-200"
                                >
                                    Per Day
                                </span>
                                <span
                                    v-else-if="w.wage_basis === 'per_piece'"
                                    class="inline-block px-2 py-0.5 text-[11px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200"
                                >
                                    Per Piece
                                </span>
                                <span
                                    v-else
                                    class="inline-block px-2 py-0.5 text-[11px] font-semibold bg-purple-50 text-purple-800 border border-purple-200"
                                >
                                    Fixed Weekly
                                </span>
                            </td>

                            <!-- Standard Rate -->
                            <td class="py-3 px-4 text-right tabular-nums text-ink-700 font-medium">
                                {{ formatMoney(w.rate) }}
                                <span class="text-[10px] text-ink-400 block">
                                    {{ w.wage_basis === 'per_day' ? '/ day' : (w.wage_basis === 'per_piece' ? '/ piece' : '/ week') }}
                                </span>
                            </td>

                            <!-- Earned This Week -->
                            <td class="py-3 px-4 text-right tabular-nums text-ink-800 font-semibold">
                                {{ formatMoney(w.earned_this_week) }}
                            </td>

                            <!-- Earned This Month -->
                            <td class="py-3 px-4 text-right tabular-nums text-ink-800 font-semibold">
                                {{ formatMoney(w.earned_this_month) }}
                            </td>

                            <!-- Total Earned -->
                            <td class="py-3 px-4 text-right tabular-nums text-ink-700">
                                {{ formatMoney(w.total_earned) }}
                            </td>

                            <!-- Total Paid -->
                            <td class="py-3 px-4 text-right tabular-nums text-ink-700">
                                {{ formatMoney(w.total_paid) }}
                            </td>

                            <!-- Current Balance (PRD §10.2: positive = Tasa owes; negative = worker over-paid in violet) -->
                            <td class="py-3 px-4 text-right tabular-nums font-mono font-black text-sm">
                                <span
                                    v-if="w.current_balance < 0"
                                    class="text-[#6B3FA0] bg-[#F1EAF8] px-2 py-0.5 border border-[#D0BAEC] inline-block"
                                    title="Worker was over-paid and owes Tasa"
                                >
                                    {{ formatMoney(w.current_balance) }}
                                </span>
                                <span
                                    v-else-if="w.current_balance > 0"
                                    class="text-ink-900"
                                    title="Tasa owes this worker"
                                >
                                    {{ formatMoney(w.current_balance) }}
                                </span>
                                <span v-else class="text-success">
                                    ৳ 0.00
                                </span>
                            </td>

                            <!-- Status Badge -->
                            <td class="py-3 px-4 text-center">
                                <StatusBadge
                                    :label="w.status_label"
                                    :tone="w.status_tone"
                                />
                            </td>

                            <!-- Actions -->
                            <td class="py-3 px-4 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    <button
                                        v-if="can.create && !w.is_archived"
                                        type="button"
                                        class="px-2 py-1 text-xs bg-ink-900 hover:bg-ink-800 text-white font-medium transition"
                                        title="Record payment for worker"
                                        @click="openNewPaymentModal(w)"
                                    >
                                        Pay
                                    </button>

                                    <button
                                        v-if="can.create && !w.is_archived"
                                        type="button"
                                        class="px-2 py-1 text-xs border border-line-300 hover:bg-sunken text-ink-800 font-medium transition"
                                        title="Log daily or piece-rate earning"
                                        @click="openNewEarningModal(w)"
                                    >
                                        Earn
                                    </button>

                                    <a
                                        :href="`/payroll/workers/${w.id}/statement`"
                                        class="px-2 py-1 text-xs border border-line-300 hover:bg-sunken text-ink-800 font-medium transition inline-block"
                                        title="View running statement ledger"
                                    >
                                        Ledger
                                    </a>

                                    <button
                                        v-if="can.update"
                                        type="button"
                                        class="px-2 py-1 text-xs border border-line-300 hover:bg-sunken text-ink-800 font-medium transition"
                                        title="Edit profile"
                                        @click="openEditWorkerDrawer(w)"
                                    >
                                        Edit
                                    </button>

                                    <button
                                        v-if="!w.is_archived && can.delete"
                                        type="button"
                                        class="px-2 py-1 text-xs border border-line-300 text-danger hover:bg-danger/10 font-medium transition"
                                        title="Archive worker"
                                        @click="confirmArchiveWorker(w)"
                                    >
                                        Archive
                                    </button>

                                    <button
                                        v-if="w.is_archived && can.delete"
                                        type="button"
                                        class="px-2 py-1 text-xs border border-line-300 text-teal-800 hover:bg-teal-50 font-medium transition"
                                        title="Restore worker"
                                        @click="restoreWorker(w.id)"
                                    >
                                        Restore
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="workers.length === 0">
                            <td colspan="10" class="py-12 text-center text-ink-500">
                                <EmptyState
                                    icon="user"
                                    title="No workers found"
                                    description="Click 'New Worker' to register piece-rate, daily-wage, or fixed-weekly workers."
                                />
                            </td>
                        </tr>
                    </tbody>

                    <!-- Summary Footer Row -->
                    <tfoot v-if="workers.length > 0" class="bg-sunken border-t-2 border-line-300 font-black text-ink-900">
                        <tr>
                            <td class="py-3 px-4">Total ({{ workers.length }} workers)</td>
                            <td></td>
                            <td></td>
                            <td class="py-3 px-4 text-right tabular-nums">{{ formatMoney(totals.total_earned_week) }}</td>
                            <td class="py-3 px-4 text-right tabular-nums">{{ formatMoney(totals.total_earned_month) }}</td>
                            <td class="py-3 px-4 text-right tabular-nums">{{ formatMoney(totals.total_earned_all) }}</td>
                            <td class="py-3 px-4 text-right tabular-nums">{{ formatMoney(totals.total_paid_all) }}</td>
                            <td class="py-3 px-4 text-right tabular-nums text-sm">
                                <span :class="totals.total_net_balance < 0 ? 'text-[#6B3FA0]' : ''">
                                    {{ formatMoney(totals.total_net_balance) }}
                                </span>
                            </td>
                            <td colspan="2" class="py-3 px-4 text-right text-[11px] text-ink-600 font-normal">
                                Owed: {{ formatMoney(totals.total_owed_by_tasa) }} · Adv: {{ formatMoney(totals.total_overpaid_by_worker) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- TAB 2: WEEKLY PAYOUT HELPER -->
        <div v-else-if="activeTab === 'payout'" class="space-y-4">
            <div class="bg-surface border border-line-200 p-4">
                <div class="flex flex-wrap justify-between items-center gap-4 border-b border-line-200 pb-4 mb-4">
                    <div>
                        <h2 class="text-sm font-bold text-ink-900">Weekly Payout Helper</h2>
                        <p class="text-xs text-ink-500 mt-0.5">
                            PRD §10.2: Quickly review current balances for active workers and record batch weekly payouts. Blank or zero entries are safely ignored.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2">
                            <label class="text-xs text-ink-600 font-semibold">Payment Date:</label>
                            <input
                                v-model="payoutForm.paid_on"
                                type="date"
                                class="h-[34px] px-2 text-xs border border-line-300 bg-surface focus:outline-none"
                            />
                        </div>

                        <div class="flex items-center gap-2">
                            <label class="text-xs text-ink-600 font-semibold">Method:</label>
                            <select
                                v-model="payoutForm.method"
                                class="h-[34px] px-2 text-xs border border-line-300 bg-surface focus:outline-none"
                            >
                                <option value="cash">Cash</option>
                                <option value="bkash">bKash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                            </select>
                        </div>

                        <div class="flex items-center gap-2">
                            <label class="text-xs text-ink-600 font-semibold">Note:</label>
                            <input
                                v-model="payoutForm.note"
                                type="text"
                                placeholder="e.g. Weekly settlement"
                                class="h-[34px] px-2 text-xs border border-line-300 bg-surface focus:outline-none w-44"
                            />
                        </div>

                        <AppButton
                            variant="primary"
                            icon="receipt"
                            :disabled="totalSelectedPayout <= 0 || payoutForm.processing"
                            :loading="payoutForm.processing"
                            @click="submitBatchPayout"
                        >
                            Disburse ৳ {{ formatMoney(totalSelectedPayout) }}
                        </AppButton>
                    </div>
                </div>

                <div class="flex justify-between items-center text-xs mb-3 text-ink-600">
                    <div>
                        Select workers to disburse. Blank or zero amounts are skipped automatically.
                    </div>
                    <div class="space-x-3">
                        <button
                            type="button"
                            class="text-teal-700 hover:underline font-semibold"
                            @click="toggleAllPayouts(true)"
                        >
                            Select All Owed
                        </button>
                        <span>·</span>
                        <button
                            type="button"
                            class="text-ink-500 hover:underline"
                            @click="toggleAllPayouts(false)"
                        >
                            Deselect All
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto border border-line-200">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-sunken border-b border-line-200 text-ink-700 font-bold uppercase tracking-wider">
                                <th class="py-2.5 px-3 w-10 text-center">Include</th>
                                <th class="py-2.5 px-3">Worker Name</th>
                                <th class="py-2.5 px-3 text-right">Current Ledger Balance</th>
                                <th class="py-2.5 px-3 text-right w-48">Disbursement Amount (৳)</th>
                                <th class="py-2.5 px-3">Quick Fill</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-100">
                            <tr
                                v-for="(p, idx) in payoutForm.payouts"
                                :key="p.worker_id"
                                :class="p.selected ? 'bg-teal-50/40' : ''"
                            >
                                <td class="py-2.5 px-3 text-center">
                                    <input
                                        v-model="p.selected"
                                        type="checkbox"
                                        class="h-4 w-4 border-line-300 text-teal-700 focus:ring-teal-700"
                                    />
                                </td>
                                <td class="py-2.5 px-3 font-bold text-ink-900">
                                    {{ p.name }}
                                </td>
                                <td class="py-2.5 px-3 text-right tabular-nums font-mono font-bold">
                                    <span :class="p.balance < 0 ? 'text-[#6B3FA0]' : (p.balance > 0 ? 'text-ink-900' : 'text-success')">
                                        {{ formatMoney(p.balance) }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-right">
                                    <input
                                        v-model="p.amount"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        placeholder="0.00"
                                        class="w-full h-[32px] px-2 text-right tabular-nums text-xs border border-line-300 bg-surface focus:outline-none focus:border-ink-900"
                                        @input="p.selected = parseFloat(p.amount) > 0"
                                    />
                                </td>
                                <td class="py-2.5 px-3">
                                    <button
                                        v-if="p.balance > 0"
                                        type="button"
                                        class="px-2 py-1 text-[11px] border border-line-300 hover:bg-surface-hover text-ink-700 transition"
                                        @click="p.amount = String(p.balance); p.selected = true;"
                                    >
                                        Pay Full Balance
                                    </button>
                                    <span v-else class="text-[11px] text-ink-400 italic">
                                        {{ p.balance < 0 ? 'Holds advance' : 'Settled' }}
                                    </span>
                                </td>
                            </tr>

                            <tr v-if="payoutForm.payouts.length === 0">
                                <td colspan="5" class="py-8 text-center text-ink-500 italic">
                                    No active workers found for weekly payout.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-sunken border-t-2 border-line-300 font-bold text-ink-900">
                            <tr>
                                <td colspan="3" class="py-3 px-4 text-right">
                                    Total Payout Selected:
                                </td>
                                <td class="py-3 px-3 text-right tabular-nums font-black text-sm text-teal-800">
                                    ৳ {{ formatMoney(totalSelectedPayout) }}
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB 3: EARNINGS LEDGER -->
        <div v-else-if="activeTab === 'earnings'" class="space-y-4">
            <div class="bg-surface border border-line-200 p-4 flex justify-between items-center">
                <div>
                    <h2 class="text-sm font-bold text-ink-900">Wage Worker Earnings Records</h2>
                    <p class="text-xs text-ink-500">
                        Audited log of daily, piece-rate, and weekly wages earned across all active jobs.
                    </p>
                </div>
                <AppButton v-if="can.create" variant="primary" icon="plus" @click="openNewEarningModal()">
                    Log Earning
                </AppButton>
            </div>

            <div class="bg-surface border border-line-200 overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-sunken border-b border-line-200 text-ink-700 font-bold uppercase tracking-wider">
                            <th class="py-3 px-4">Date</th>
                            <th class="py-3 px-4">Worker</th>
                            <th class="py-3 px-4">Wage Basis</th>
                            <th class="py-3 px-4 text-right">Quantity</th>
                            <th class="py-3 px-4 text-right">Applied Rate</th>
                            <th class="py-3 px-4 text-right">Earned Amount</th>
                            <th class="py-3 px-4">Note / Period</th>
                            <th class="py-3 px-4">Recorded By</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-100">
                        <tr v-for="e in recentEarnings.data" :key="e.id" class="hover:bg-surface-hover transition">
                            <td class="py-3 px-4 font-medium text-ink-900 whitespace-nowrap">
                                {{ formatDate(e.work_date) }}
                            </td>
                            <td class="py-3 px-4 font-bold text-ink-900">
                                {{ e.worker?.name || '—' }}
                                <span class="text-[10px] text-ink-500 block font-normal">{{ e.worker?.role }}</span>
                            </td>
                            <td class="py-3 px-4 capitalize text-ink-700">
                                {{ e.worker?.wage_basis?.replace('_', ' ') }}
                            </td>
                            <td class="py-3 px-4 text-right tabular-nums font-semibold text-ink-900">
                                {{ e.qty }}
                                <span class="text-[10px] text-ink-400">
                                    {{ e.worker?.wage_basis === 'per_day' ? 'days' : (e.worker?.wage_basis === 'per_piece' ? 'pcs' : 'wk') }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right tabular-nums text-ink-700">
                                {{ formatMoney(e.rate) }}
                            </td>
                            <td class="py-3 px-4 text-right tabular-nums font-bold text-ink-900 text-sm">
                                {{ formatMoney(e.amount) }}
                            </td>
                            <td class="py-3 px-4 text-ink-600 max-w-xs truncate">
                                <span v-if="e.period_start" class="text-ink-400 text-[10px] block">
                                    Week Start: {{ formatDate(e.period_start) }}
                                </span>
                                {{ e.note || '—' }}
                            </td>
                            <td class="py-3 px-4 text-ink-500">
                                {{ e.creator?.name || 'System' }}
                            </td>
                            <td class="py-3 px-4 text-right whitespace-nowrap">
                                <button
                                    v-if="can.update"
                                    type="button"
                                    class="px-2 py-1 text-xs border border-line-300 hover:bg-sunken text-ink-800 font-medium transition"
                                    title="Edit earning entry"
                                    @click="openEditEarningModal(e)"
                                >
                                    Edit
                                </button>
                            </td>
                        </tr>

                        <tr v-if="recentEarnings.data.length === 0">
                            <td colspan="9" class="py-12 text-center text-ink-500">
                                <EmptyState
                                    icon="calendar"
                                    title="No earning records found"
                                    description="No wage earnings have been logged yet. Click 'Log Earning' to record work done."
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <TablePagination
                v-if="recentEarnings.total > recentEarnings.per_page"
                :current-page="recentEarnings.current_page"
                :last-page="recentEarnings.last_page"
                :from="recentEarnings.from"
                :to="recentEarnings.to"
                :total="recentEarnings.total"
                :links="recentEarnings.links"
            />
        </div>

        <!-- TAB 4: PAYMENTS LEDGER -->
        <div v-else-if="activeTab === 'payments'" class="space-y-4">
            <div class="bg-surface border border-line-200 p-4 flex justify-between items-center">
                <div>
                    <h2 class="text-sm font-bold text-ink-900">Worker Wage Payments Ledger</h2>
                    <p class="text-xs text-ink-500">
                        Audited log of every individual and batch wage payout to workers.
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
                            <th class="py-3 px-4">Worker</th>
                            <th class="py-3 px-4">Method</th>
                            <th class="py-3 px-4 text-right">Amount</th>
                            <th class="py-3 px-4">Note / Reference</th>
                            <th class="py-3 px-4">Recorded By</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-100">
                        <tr v-for="p in recentPayments.data" :key="p.id" class="hover:bg-surface-hover transition">
                            <td class="py-3 px-4 font-medium text-ink-900 whitespace-nowrap">
                                {{ formatDate(p.paid_on) }}
                            </td>
                            <td class="py-3 px-4 font-bold text-ink-900">
                                {{ p.worker?.name || '—' }}
                                <span class="text-[10px] text-ink-500 block font-normal">{{ p.worker?.role }}</span>
                            </td>
                            <td class="py-3 px-4 text-ink-700 capitalize">
                                {{ p.method?.replace('_', ' ') || 'Cash' }}
                            </td>
                            <td class="py-3 px-4 text-right tabular-nums font-bold text-ink-900 text-sm">
                                {{ formatMoney(p.amount) }}
                            </td>
                            <td class="py-3 px-4 text-ink-600 max-w-xs truncate">
                                {{ p.note || '—' }}
                            </td>
                            <td class="py-3 px-4 text-ink-500">
                                {{ p.creator?.name || 'System' }}
                            </td>
                            <td class="py-3 px-4 text-right whitespace-nowrap">
                                <button
                                    v-if="can.update"
                                    type="button"
                                    class="px-2 py-1 text-xs border border-line-300 hover:bg-sunken text-ink-800 font-medium transition"
                                    title="Edit payment entry"
                                    @click="openEditPaymentModal(p)"
                                >
                                    Edit
                                </button>
                            </td>
                        </tr>

                        <tr v-if="recentPayments.data.length === 0">
                            <td colspan="7" class="py-12 text-center text-ink-500">
                                <EmptyState
                                    icon="expenses"
                                    title="No payment records found"
                                    description="No wage disbursements recorded yet. Click 'Record Payment' or use the Weekly Payout helper."
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <TablePagination
                v-if="recentPayments.total > recentPayments.per_page"
                :current-page="recentPayments.current_page"
                :last-page="recentPayments.last_page"
                :from="recentPayments.from"
                :to="recentPayments.to"
                :total="recentPayments.total"
                :links="recentPayments.links"
            />
        </div>

        <!-- DRAWER: NEW / EDIT WORKER PROFILE -->
        <SideDrawer
            :open="isWorkerDrawerOpen"
            :is-open="isWorkerDrawerOpen"
            :title="isEditingWorker ? 'Edit Worker Profile' : 'New Wage Worker Profile'"
            @close="isWorkerDrawerOpen = false"
        >
            <form @submit.prevent="submitWorkerForm" class="space-y-4 p-4">
                <FormField id="worker-name" label="Worker Full Name" :required="true" :error="workerForm.errors.name">
                    <input
                        id="worker-name"
                        v-model="workerForm.name"
                        type="text"
                        placeholder="e.g. Rahim Miah"
                        class="w-full h-[38px] px-3 text-sm text-ink-900 bg-surface border border-line-300 focus:outline-none focus:border-ink-900"
                        required
                    />
                </FormField>

                <FormField id="worker-phone" label="Phone Number" :error="workerForm.errors.phone">
                    <PhoneInput id="worker-phone" v-model="workerForm.phone" />
                </FormField>

                <FormField id="worker-role" label="Role / Trade" :error="workerForm.errors.role">
                    <input
                        id="worker-role"
                        v-model="workerForm.role"
                        type="text"
                        placeholder="e.g. Master Pattern Cutter, Stitcher, Finisher"
                        class="w-full h-[38px] px-3 text-sm text-ink-900 bg-surface border border-line-300 focus:outline-none focus:border-ink-900"
                    />
                </FormField>

                <FormField id="worker-basis" label="Wage Basis (Decision O-2)" :required="true" :error="workerForm.errors.wage_basis">
                    <select
                        id="worker-basis"
                        v-model="workerForm.wage_basis"
                        class="w-full h-[38px] px-3 text-sm text-ink-900 bg-surface border border-line-300 focus:outline-none focus:border-ink-900"
                        required
                    >
                        <option value="per_day">Per Day — Daily wage rate</option>
                        <option value="per_piece">Per Piece — Piece rate per finished item</option>
                        <option value="fixed_weekly">Fixed Weekly — Guaranteed weekly stipend</option>
                    </select>
                </FormField>

                <FormField
                    id="worker-rate"
                    :label="`Standard Rate (৳ ${workerForm.wage_basis === 'per_day' ? '/ day' : (workerForm.wage_basis === 'per_piece' ? '/ piece' : '/ week')})`"
                    :required="true"
                    helper="Default rate for new earning entries; editable per job"
                    :error="workerForm.errors.rate"
                >
                    <MoneyInput id="worker-rate" v-model="workerForm.rate" />
                </FormField>

                <div class="flex items-center gap-2 pt-2">
                    <input
                        id="worker-active"
                        v-model="workerForm.is_active"
                        type="checkbox"
                        class="h-4 w-4 border-line-300 text-teal-700 focus:ring-teal-700"
                    />
                    <label for="worker-active" class="text-xs font-semibold text-ink-900">
                        Active Worker (Available for work assignments and weekly payout)
                    </label>
                </div>

                <div class="pt-6 border-t border-line-200 flex justify-end gap-3">
                    <AppButton variant="secondary" @click="isWorkerDrawerOpen = false">
                        Cancel
                    </AppButton>
                    <AppButton variant="primary" type="submit" :loading="workerForm.processing">
                        {{ isEditingWorker ? 'Save Changes' : 'Create Worker' }}
                    </AppButton>
                </div>
            </form>
        </SideDrawer>

        <!-- MODAL: LOG / EDIT EARNING -->
        <div
            v-if="isEarningModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        >
            <div class="bg-surface border border-line-300 shadow-xl w-full max-w-lg p-6 relative">
                <div class="flex justify-between items-center pb-3 border-b border-line-200 mb-4">
                    <div>
                        <h3 class="text-base font-bold text-ink-900">
                            {{ isEditingEarning ? 'Edit Earning Entry' : 'Log Worker Earning' }}
                        </h3>
                        <p class="text-xs text-ink-500">
                            {{ isEditingEarning ? 'Controlled edit with audit log history (PRD §10.2)' : 'Accrue earned wage for piece-rate, daily or weekly work' }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="text-ink-400 hover:text-ink-700"
                        @click="isEarningModalOpen = false"
                    >
                        <AppIcon name="close" :size="16" />
                    </button>
                </div>

                <form @submit.prevent="submitEarningForm" class="space-y-4">
                    <FormField id="earning-worker" label="Worker" :required="true" :error="earningForm.errors.worker_id">
                        <select
                            id="earning-worker"
                            v-model="earningForm.worker_id"
                            :disabled="isEditingEarning"
                            class="w-full h-[38px] px-3 text-sm text-ink-900 bg-surface border border-line-300 focus:outline-none focus:border-ink-900"
                            required
                            @change="onWorkerSelectChange"
                        >
                            <option
                                v-for="w in allWorkers.filter(item => !item.is_archived)"
                                :key="w.id"
                                :value="w.id"
                            >
                                {{ w.name }} ({{ w.role || 'Worker' }} · {{ w.wage_basis.replace('_', ' ') }})
                            </option>
                        </select>
                    </FormField>

                    <div class="grid grid-cols-2 gap-4">
                        <FormField id="earning-date" label="Work Date / Ending Date" :required="true" :error="earningForm.errors.work_date">
                            <input
                                id="earning-date"
                                v-model="earningForm.work_date"
                                type="date"
                                class="w-full h-[38px] px-3 text-sm text-ink-900 bg-surface border border-line-300 focus:outline-none focus:border-ink-900"
                                required
                            />
                        </FormField>

                        <FormField
                            v-if="selectedWorkerObject?.wage_basis === 'fixed_weekly'"
                            id="earning-period-start"
                            label="Period Start Date (Optional)"
                            :error="earningForm.errors.period_start"
                        >
                            <input
                                id="earning-period-start"
                                v-model="earningForm.period_start"
                                type="date"
                                class="w-full h-[38px] px-3 text-sm text-ink-900 bg-surface border border-line-300 focus:outline-none focus:border-ink-900"
                            />
                        </FormField>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <FormField
                            id="earning-qty"
                            :label="selectedWorkerObject?.wage_basis === 'per_day' ? 'Days Worked (qty)' : (selectedWorkerObject?.wage_basis === 'per_piece' ? 'Pieces Produced (qty)' : 'Weeks (qty)')"
                            :required="true"
                            :error="earningForm.errors.qty"
                        >
                            <input
                                id="earning-qty"
                                v-model="earningForm.qty"
                                type="number"
                                step="any"
                                min="0.01"
                                class="w-full h-[38px] px-3 text-sm text-ink-900 bg-surface border border-line-300 focus:outline-none focus:border-ink-900 tabular-nums"
                                required
                            />
                        </FormField>

                        <FormField
                            id="earning-rate"
                            :label="`Rate per unit (৳)`"
                            :required="true"
                            :error="earningForm.errors.rate"
                        >
                            <MoneyInput id="earning-rate" v-model="earningForm.rate" />
                        </FormField>
                    </div>

                    <!-- Calculated Amount with override option -->
                    <FormField
                        id="earning-amount"
                        label="Calculated Earned Amount (৳)"
                        :helper="`Computed: ${earningForm.qty || 1} × ${formatMoney(parseFloat(earningForm.rate) || 0)} = ৳ ${formatMoney(computedEarningAmount)}`"
                        :error="earningForm.errors.amount"
                    >
                        <MoneyInput
                            id="earning-amount"
                            v-model="earningForm.amount"
                            :placeholder="String(computedEarningAmount)"
                        />
                    </FormField>

                    <FormField id="earning-note" label="Job Reference / Note" :error="earningForm.errors.note">
                        <input
                            id="earning-note"
                            v-model="earningForm.note"
                            type="text"
                            placeholder="e.g. Batch #42 leather briefcases stitch job"
                            class="w-full h-[38px] px-3 text-sm text-ink-900 bg-surface border border-line-300 focus:outline-none focus:border-ink-900"
                        />
                    </FormField>

                    <div class="pt-4 border-t border-line-200 flex justify-end gap-3">
                        <AppButton variant="secondary" @click="isEarningModalOpen = false">
                            Cancel
                        </AppButton>
                        <AppButton variant="primary" type="submit" :loading="earningForm.processing">
                            {{ isEditingEarning ? 'Save Changes' : 'Confirm Earning' }}
                        </AppButton>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: RECORD / EDIT PAYMENT -->
        <div
            v-if="isPaymentModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
        >
            <div class="bg-surface border border-line-300 shadow-xl w-full max-w-lg p-6 relative">
                <div class="flex justify-between items-center pb-3 border-b border-line-200 mb-4">
                    <div>
                        <h3 class="text-base font-bold text-ink-900">
                            {{ isEditingPayment ? 'Edit Wage Payment Record' : 'Record Worker Wage Payment' }}
                        </h3>
                        <p class="text-xs text-ink-500">
                            {{ isEditingPayment ? 'Controlled edit with audit log history (PRD §10.2)' : 'Disburse wage payment to settle worker balance' }}
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
                    <FormField id="pay-worker" label="Worker" :required="true" :error="paymentForm.errors.worker_id">
                        <select
                            id="pay-worker"
                            v-model="paymentForm.worker_id"
                            :disabled="isEditingPayment"
                            class="w-full h-[38px] px-3 text-sm text-ink-900 bg-surface border border-line-300 focus:outline-none focus:border-ink-900"
                            required
                        >
                            <option
                                v-for="w in allWorkers.filter(item => !item.is_archived)"
                                :key="w.id"
                                :value="w.id"
                            >
                                {{ w.name }} ({{ w.role || 'Worker' }})
                            </option>
                        </select>
                    </FormField>

                    <div class="grid grid-cols-2 gap-4">
                        <FormField id="pay-date" label="Payment Date" :required="true" :error="paymentForm.errors.paid_on">
                            <input
                                id="pay-date"
                                v-model="paymentForm.paid_on"
                                type="date"
                                class="w-full h-[38px] px-3 text-sm text-ink-900 bg-surface border border-line-300 focus:outline-none focus:border-ink-900"
                                required
                            />
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
                            placeholder="e.g. Mid-week cash advance / weekend settlement"
                            class="w-full h-[38px] px-3 text-sm text-ink-900 bg-surface border border-line-300 focus:outline-none focus:border-ink-900"
                        />
                    </FormField>

                    <div class="bg-amber-50 border border-amber-200 p-3 text-[11px] text-amber-900 leading-relaxed">
                        <strong>PRD Notice:</strong> Worker payments adjust the running balance and sit as cash disbursements.
                        Accrued earnings (not payments) represent P&L wage expense.
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

        <!-- CONFIRM ARCHIVE WORKER DIALOG -->
        <ConfirmDialog
            :is-open="isArchiveConfirmOpen"
            title="Archive Worker"
            :message="`Are you sure you want to archive ${selectedWorker?.name}?`"
            consequence-text="In accordance with PRD BR-4, worker profile is soft-deleted; all earnings entries, payments, and running statement ledger history are strictly preserved."
            confirm-button-text="Archive Worker"
            variant="danger"
            @confirm="executeArchiveWorker"
            @cancel="isArchiveConfirmOpen = false"
        />
    </div>
</template>
