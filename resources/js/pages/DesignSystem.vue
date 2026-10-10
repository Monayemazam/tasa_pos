<script setup lang="ts">
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/tasa/PageHeader.vue';
import AppButton from '@/components/tasa/AppButton.vue';
import FormField from '@/components/tasa/FormField.vue';
import MoneyInput from '@/components/tasa/MoneyInput.vue';
import PhoneInput from '@/components/tasa/PhoneInput.vue';
import StatusBadge from '@/components/tasa/StatusBadge.vue';
import StatCard from '@/components/tasa/StatCard.vue';
import DataTable, { type ColumnDef } from '@/components/tasa/DataTable.vue';
import EmptyState from '@/components/tasa/EmptyState.vue';
import SkeletonLoader from '@/components/tasa/SkeletonLoader.vue';
import ConfirmDialog from '@/components/tasa/ConfirmDialog.vue';
import SideDrawer from '@/components/tasa/SideDrawer.vue';
import DateRangePicker, { type DateRange } from '@/components/tasa/DateRangePicker.vue';
import SearchFilterBar from '@/components/tasa/SearchFilterBar.vue';
import TablePagination from '@/components/tasa/TablePagination.vue';
import { showToast } from '@/lib/toast';
import { formatMoney, formatQuantity, formatDate } from '@/lib/formatters';

// Form states
const sampleMoney = ref('125000.50');
const samplePhone = ref('01712345678');
const sampleText = ref('Bifold Leather Wallet');
const sampleErrorText = ref('');
const sampleDateRange = ref<DateRange>({ from: '2026-10-01', to: '2026-10-11' });
const sampleSearch = ref('');

// Dialog & Drawer state
const isConfirmOpen = ref(false);
const isDrawerOpen = ref(false);

// Table Sample Data
const columns: ColumnDef[] = [
    { key: 'invoice_no', label: 'Invoice No', isPrimary: true },
    { key: 'date', label: 'Date' },
    { key: 'customer', label: 'Customer' },
    { key: 'total', label: 'Grand Total', align: 'right', isNumeric: true },
    { key: 'paid', label: 'Paid Amount', align: 'right', isNumeric: true },
    { key: 'status', label: 'Payment Status', align: 'center' },
];

const sampleItems = [
    {
        id: 1,
        invoice_no: 'TASA-2610-0001',
        date: formatDate('2026-10-11'),
        customer: 'রহিম উদ্দিন (Rahim Uddin)',
        total: formatMoney(3560),
        paid: formatMoney(500),
        status: 'partial',
    },
    {
        id: 2,
        invoice_no: 'TASA-2610-0002',
        date: formatDate('2026-10-11'),
        customer: 'সালমা আক্তার (Salma Akter)',
        total: formatMoney(1800),
        paid: formatMoney(1800),
        status: 'paid',
    },
    {
        id: 3,
        invoice_no: 'TASA-2610-0003',
        date: formatDate('2026-10-10'),
        customer: 'তানভীর হাসান (Tanveer Hasan)',
        total: formatMoney(4200),
        paid: formatMoney(0),
        status: 'unpaid',
    },
];

const handleTriggerToast = (tone: any) => {
    showToast(`Test notification in ${tone} tone with 3px status bar.`, tone);
};
</script>

<template>
    <AppLayout>
        <!-- Page Header -->
        <PageHeader
            title="Tasa POS Design System & UX Specimen"
            subtitle="Strict Light Theme, Deep Teal #0B6E5E, 0px Square Corners, 1px Borders, Bangla & ৳ Rendering"
        >
            <template #actions>
                <AppButton variant="secondary" icon="download" @click="handleTriggerToast('info')">
                    Export Spec
                </AppButton>
                <AppButton variant="primary" icon="plus" @click="isDrawerOpen = true">
                    Open Drawer
                </AppButton>
            </template>
        </PageHeader>

        <!-- KPI Strip -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <StatCard
                title="Today's Sales"
                :value="formatMoney(18400, 0)"
                subtext="6 invoices generated"
                :is-primary="true"
            />
            <StatCard
                title="This Month"
                :value="formatMoney(342000, 0)"
                subtext="58 invoices (12.1% net margin)"
            />
            <StatCard
                title="COD To Receive"
                :value="formatMoney(22300, 0)"
                subtext="9 orders delivered via Pathao"
            />
            <StatCard
                title="Bangla & Currency Test"
                value="৳ ১,২৫,০০০"
                subtext="বাংলা ফন্ট: নোটো সান্স বেঙ্গলি"
            />
        </div>

        <div class="space-y-8">
            <!-- 1. Typography & Contrast Section -->
            <section class="bg-surface border border-line-200 p-6">
                <h2 class="text-base font-bold text-ink-900 mb-4 border-b border-line-200 pb-2">
                    1. Typography, Tabular Figures & Bangla Glyphs
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                    <div>
                        <div class="text-xs uppercase font-semibold text-ink-500 mb-1">English (Hanken Grotesk)</div>
                        <p class="text-lg font-bold text-ink-900">
                            The quick brown fox jumps over the lazy dog.
                        </p>
                        <p class="text-sm text-ink-700 mt-2">
                            Body text in ink-700 (11.9:1 AAA contrast on white surface).
                        </p>
                        <div class="mt-3 font-semibold text-ink-900 tabular-nums">
                            Tabular Numerals: 0123456789 (aligned in columns)
                        </div>
                    </div>
                    <div>
                        <div class="text-xs uppercase font-semibold text-ink-500 mb-1">Bangla & ৳ Symbol (Noto Sans Bengali Fallback)</div>
                        <p class="text-lg font-bold text-ink-900">
                            টাসা পিওএস — চামড়াজাত পণ্যের সহজ হিসাব ও বিক্রয় ব্যবস্থা।
                        </p>
                        <p class="text-base font-semibold text-teal-700 mt-2">
                            মূল্য: {{ formatMoney(125000.5) }} · পরিমাণ: {{ formatQuantity(35.5, 'sq ft') }}
                        </p>
                        <p class="text-xs text-ink-600 mt-1">
                            ৳ প্রতীক (U+09F3) ও বাংলা টেক্সট নিখুঁতভাবে রেন্ডার হচ্ছে।
                        </p>
                    </div>
                </div>
            </section>

            <!-- 2. Buttons Specimen -->
            <section class="bg-surface border border-line-200 p-6">
                <h2 class="text-base font-bold text-ink-900 mb-4 border-b border-line-200 pb-2">
                    2. Button Variants & States (Zero Radius, 1px Border)
                </h2>
                <div class="flex flex-wrap items-center gap-4">
                    <AppButton variant="primary" icon="plus">Primary Button</AppButton>
                    <AppButton variant="secondary" icon="printer">Secondary Button</AppButton>
                    <AppButton variant="ghost" icon="edit">Ghost Button</AppButton>
                    <AppButton variant="danger" icon="trash" @click="isConfirmOpen = true">Danger Action</AppButton>
                    <AppButton variant="danger-outline">Danger Outline</AppButton>
                    <AppButton variant="primary" :loading="true">Loading State</AppButton>
                    <AppButton variant="primary" :disabled="true">Disabled State</AppButton>
                </div>
            </section>

            <!-- 3. Status Badges & Chips -->
            <section class="bg-surface border border-line-200 p-6">
                <h2 class="text-base font-bold text-ink-900 mb-4 border-b border-line-200 pb-2">
                    3. Status Badges (Square 24px, Colour + Icon + Label)
                </h2>
                <div class="flex flex-wrap items-center gap-3">
                    <StatusBadge label="Paid / Delivered" tone="success" />
                    <StatusBadge label="Partial Payment" tone="warning" />
                    <StatusBadge label="Unpaid / Cancelled" tone="danger" />
                    <StatusBadge label="In Transit" tone="info" />
                    <StatusBadge label="Over-paid (Owes Tasa)" tone="advance" />
                    <StatusBadge label="Queued / Neutral" tone="neutral" />
                </div>
            </section>

            <!-- 4. Form Controls -->
            <section class="bg-surface border border-line-200 p-6">
                <h2 class="text-base font-bold text-ink-900 mb-4 border-b border-line-200 pb-2">
                    4. Form Controls & Validation
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <FormField
                        id="money-test"
                        label="Grand Total Amount"
                        :required="true"
                        helper="Tabular digits with South-Asian comma grouping"
                    >
                        <MoneyInput id="money-test" v-model="sampleMoney" />
                    </FormField>

                    <FormField
                        id="phone-test"
                        label="Customer Phone"
                        :required="true"
                        helper="11-digit Bangladeshi mobile format"
                    >
                        <PhoneInput id="phone-test" v-model="samplePhone" />
                    </FormField>

                    <FormField
                        id="error-test"
                        label="Product Name"
                        :required="true"
                        error="Product name is required and cannot be empty."
                    >
                        <input
                            id="error-test"
                            v-model="sampleErrorText"
                            type="text"
                            placeholder="e.g. Bifold Wallet – Tan"
                            class="w-full h-[40px] px-3 text-sm text-ink-900 bg-surface border border-danger focus:outline-danger rounded-none"
                        />
                    </FormField>
                </div>
            </section>

            <!-- 5. Search, Filter Bar & Data Table -->
            <section class="bg-surface border border-line-200 p-6">
                <h2 class="text-base font-bold text-ink-900 mb-4 border-b border-line-200 pb-2">
                    5. Data Table, Mobile Cards & Filter Bar
                </h2>

                <SearchFilterBar
                    v-model="sampleSearch"
                    :show-export="true"
                    :active-filters="[{ key: 'status', label: 'Payment', value: 'All' }]"
                >
                    <template #filters>
                        <DateRangePicker v-model="sampleDateRange" />
                    </template>
                </SearchFilterBar>

                <DataTable
                    :columns="columns"
                    :items="sampleItems"
                >
                    <template #cell-status="{ value }">
                        <StatusBadge
                            :label="value === 'paid' ? 'Paid' : value === 'partial' ? 'Partial' : 'Unpaid'"
                            :tone="value === 'paid' ? 'success' : value === 'partial' ? 'warning' : 'danger'"
                        />
                    </template>

                    <template #totals>
                        <tr>
                            <td colspan="3" class="px-4 py-3 text-right uppercase">Total:</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ formatMoney(9560) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ formatMoney(2300) }}</td>
                            <td class="px-4 py-3 text-center">3 Invoices</td>
                        </tr>
                    </template>

                    <template #pagination>
                        <TablePagination
                            :current-page="1"
                            :total-items="3"
                            :per-page="25"
                        />
                    </template>
                </DataTable>
            </section>

            <!-- 6. Notifications & Toast Triggers -->
            <section class="bg-surface border border-line-200 p-6">
                <h2 class="text-base font-bold text-ink-900 mb-4 border-b border-line-200 pb-2">
                    6. Toast Notifications (360px Width, 3px Left Status Bar)
                </h2>
                <div class="flex flex-wrap items-center gap-3">
                    <AppButton variant="secondary" @click="handleTriggerToast('success')">Show Success Toast</AppButton>
                    <AppButton variant="secondary" @click="handleTriggerToast('warning')">Show Warning Toast</AppButton>
                    <AppButton variant="secondary" @click="handleTriggerToast('danger')">Show Danger Toast</AppButton>
                    <AppButton variant="secondary" @click="handleTriggerToast('info')">Show Info Toast</AppButton>
                </div>
            </section>

            <!-- 7. Skeleton & Empty State -->
            <section class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-surface border border-line-200 p-6">
                    <h3 class="text-sm font-bold text-ink-900 mb-4">Skeleton Pulse Loading</h3>
                    <SkeletonLoader :lines="4" />
                </div>
                <EmptyState
                    icon="leather"
                    title="No Leather Purchases Yet"
                    description="Record the first leather purchase to start tracking square feet stock and valuation."
                    action-label="Add First Purchase"
                    @action="handleTriggerToast('info')"
                />
            </section>
        </div>

        <!-- Confirm Dialog Component Specimen -->
        <ConfirmDialog
            :open="isConfirmOpen"
            title="Cancel Invoice TASA-2610-0043?"
            description="Stock will be returned and the sale will be removed from reports. The invoice number cannot be reused."
            :require-reason="true"
            confirm-label="Cancel Sale"
            @confirm="isConfirmOpen = false; handleTriggerToast('danger')"
            @cancel="isConfirmOpen = false"
        />

        <!-- Side Drawer Component Specimen -->
        <SideDrawer
            :open="isDrawerOpen"
            title="Add Leather Purchase"
            subtitle="Record raw leather sq ft stock receipt"
            @close="isDrawerOpen = false"
        >
            <div class="space-y-4">
                <FormField id="leather-item" label="Leather Item" :required="true">
                    <input
                        id="leather-item"
                        type="text"
                        placeholder="e.g. Cow Nappa Tan"
                        class="w-full h-[40px] px-3 bg-surface border border-line-400 rounded-none focus:border-teal-700"
                    />
                </FormField>

                <FormField id="leather-qty" label="Quantity (sq ft)" :required="true">
                    <input
                        id="leather-qty"
                        type="number"
                        step="0.001"
                        placeholder="0.000"
                        class="w-full h-[40px] px-3 text-right tabular-nums bg-surface border border-line-400 rounded-none focus:border-teal-700"
                    />
                </FormField>

                <FormField id="leather-rate" label="Rate per sq ft" :required="true">
                    <MoneyInput id="leather-rate" model-value="" placeholder="320.00" />
                </FormField>
            </div>

            <template #footer>
                <AppButton variant="secondary" @click="isDrawerOpen = false">Cancel</AppButton>
                <AppButton variant="primary" @click="isDrawerOpen = false; handleTriggerToast('success')">Save Purchase</AppButton>
            </template>
        </SideDrawer>
    </AppLayout>
</template>
