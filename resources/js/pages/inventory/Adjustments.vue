<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { api } from '@/lib/api';
import PageHeader from '@/components/tasa/PageHeader.vue';
import AppButton from '@/components/tasa/AppButton.vue';
import AppIcon from '@/components/tasa/AppIcon.vue';
import FormField from '@/components/tasa/FormField.vue';
import TablePagination from '@/components/tasa/TablePagination.vue';
import EmptyState from '@/components/tasa/EmptyState.vue';
import { formatQuantity, formatDate } from '@/lib/formatters';
import { showToast } from '@/lib/toast';

interface AdjustmentRow {
    id: number;
    item_type: 'product' | 'leather' | 'accessory';
    item_id: number;
    item_name: string;
    unit: string;
    qty: number;
    reason: 'damaged' | 'lost' | 'count_correction' | 'other';
    note: string | null;
    adjusted_on: string;
    raw_date: string;
    creator: string;
    created_at: string;
}

interface ItemOption {
    id: number;
    name: string;
    unit: string;
    current_stock: number;
    min_stock: number;
}

const props = defineProps<{
    adjustments: {
        data: AdjustmentRow[];
        current_page: number;
        per_page: number;
        total: number;
    };
    filters: {
        item_type?: string;
        reason?: string;
        from?: string;
        to?: string;
        search?: string;
    };
    initialItems: ItemOption[];
    canCreate: boolean;
    allowNegativeStock: boolean;
}>();

// --- Filter Controls ---
const filterType = ref(props.filters.item_type || '');
const filterReason = ref(props.filters.reason || '');
const filterFrom = ref(props.filters.from || '');
const filterTo = ref(props.filters.to || '');
const filterSearch = ref(props.filters.search || '');

function applyFilters() {
    router.get(
        '/inventory/adjustments',
        {
            item_type: filterType.value || undefined,
            reason: filterReason.value || undefined,
            from: filterFrom.value || undefined,
            to: filterTo.value || undefined,
            search: filterSearch.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}

function resetFilters() {
    filterType.value = '';
    filterReason.value = '';
    filterFrom.value = '';
    filterTo.value = '';
    filterSearch.value = '';
    applyFilters();
}

// --- Modal State ---
const showModal = ref(false);
const itemsLoading = ref(false);
const availableItems = ref<ItemOption[]>(props.initialItems || []);

const form = useForm({
    item_type: 'product',
    item_id: '',
    qty: '',
    adjusted_on: new Date().toISOString().slice(0, 10),
    reason: 'damaged',
    note: '',
});

const selectedItem = computed(() => {
    const id = Number(form.item_id);
    return availableItems.value.find((i) => i.id === id) || null;
});

const projectedStock = computed(() => {
    if (!selectedItem.value) return null;
    const delta = parseFloat(form.qty) || 0;
    return Math.round((selectedItem.value.current_stock + delta) * 1000) / 1000;
});

async function loadItemsForType(type: string) {
    itemsLoading.value = true;
    form.item_id = '';
    try {
        const res = await api.get<{ type: string; items: ItemOption[] }>(
            `/inventory/adjustments/items?type=${type}`
        );
        availableItems.value = res.items || [];
        if (availableItems.value.length > 0) {
            form.item_id = String(availableItems.value[0].id);
        }
    } catch {
        showToast('Failed to load item options for the selected type.', 'danger');
    } finally {
        itemsLoading.value = false;
    }
}

watch(
    () => form.item_type,
    (newType) => {
        loadItemsForType(newType);
    }
);

function openCreateModal() {
    form.reset();
    form.clearErrors();
    form.item_type = 'product';
    form.adjusted_on = new Date().toISOString().slice(0, 10);
    form.reason = 'damaged';
    loadItemsForType('product');
    showModal.value = true;
}

function submitAdjustment() {
    form.post('/inventory/adjustments', {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false;
            showToast('Stock adjustment recorded successfully.', 'success');
        },
    });
}

function getReasonBadge(reason: string): { label: string; icon: import('@/lib/icons').IconConcept; classes: string } {
    switch (reason) {
        case 'damaged':
            return {
                label: 'Damaged',
                icon: 'danger',
                classes: 'bg-[#FEE2E2] text-[#B91C1C] border-[#FCA5A5]',
            };
        case 'lost':
            return {
                label: 'Lost / Stolen',
                icon: 'warning',
                classes: 'bg-[#FEF3C7] text-[#B45309] border-[#FCD34D]',
            };
        case 'count_correction':
            return {
                label: 'Count Correction',
                icon: 'check-circle',
                classes: 'bg-[#D1FAE5] text-[#065F46] border-[#6EE7B7]',
            };
        case 'other':
        default:
            return {
                label: 'Other',
                icon: 'info',
                classes: 'bg-[#EFF6FF] text-[#1E40AF] border-[#BFDBFE]',
            };
    }
}

function getTypeBadge(type: string): { label: string; icon: import('@/lib/icons').IconConcept; classes: string } {
    switch (type) {
        case 'product':
            return {
                label: 'Product',
                icon: 'products',
                classes: 'bg-[#F3F4F6] text-[#374151] border-[#E5E7EB]',
            };
        case 'leather':
            return {
                label: 'Leather',
                icon: 'leather',
                classes: 'bg-[#FEF3C7] text-[#92400E] border-[#FDE68A]',
            };
        case 'accessory':
            return {
                label: 'Accessory',
                icon: 'accessories',
                classes: 'bg-[#EDE9FE] text-[#6D28D9] border-[#DDD6FE]',
            };
        default:
            return {
                label: type,
                icon: 'box',
                classes: 'bg-[#F3F4F6] text-[#374151] border-[#E5E7EB]',
            };
    }
}
</script>

<template>
    <Head title="Universal Stock Adjustments — Tasa POS" />

        <div class="space-y-6">
            <!-- Header -->
            <PageHeader
                title="Universal Stock Adjustments"
                subtitle="Append-only audit trail of stock adjustments across finished goods, raw leather, and accessories"
            >
                <template #actions>
                    <AppButton
                        v-if="canCreate"
                        tone="primary"
                        icon="plus"
                        @click="openCreateModal"
                    >
                        New Adjustment
                    </AppButton>
                </template>
            </PageHeader>

            <!-- Filter Bar -->
            <div class="bg-white border border-[#E5E7EB] p-4 flex flex-wrap items-center gap-3">
                <!-- Item Type Filter -->
                <div class="w-44">
                    <select
                        v-model="filterType"
                        class="w-full py-1.5 px-2 text-sm bg-white border border-[#D1D5DB] focus:border-[#0B6E5E] focus:outline-none"
                        @change="applyFilters()"
                    >
                        <option value="">All Item Types</option>
                        <option value="product">Finished Products</option>
                        <option value="leather">Raw Leather</option>
                        <option value="accessory">Accessories</option>
                    </select>
                </div>

                <!-- Reason Filter -->
                <div class="w-44">
                    <select
                        v-model="filterReason"
                        class="w-full py-1.5 px-2 text-sm bg-white border border-[#D1D5DB] focus:border-[#0B6E5E] focus:outline-none"
                        @change="applyFilters()"
                    >
                        <option value="">All Reasons</option>
                        <option value="damaged">Damaged Material</option>
                        <option value="lost">Lost / Missing</option>
                        <option value="count_correction">Count Correction</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <!-- Date Range -->
                <div class="flex items-center gap-1.5 text-xs text-[#6B7280]">
                    <span>From:</span>
                    <input
                        v-model="filterFrom"
                        type="date"
                        class="py-1 px-2 text-xs border border-[#D1D5DB] bg-white focus:border-[#0B6E5E] focus:outline-none"
                        @change="applyFilters()"
                    />
                    <span>To:</span>
                    <input
                        v-model="filterTo"
                        type="date"
                        class="py-1 px-2 text-xs border border-[#D1D5DB] bg-white focus:border-[#0B6E5E] focus:outline-none"
                        @change="applyFilters()"
                    />
                </div>

                <AppButton tone="secondary" size="sm" @click="applyFilters()">
                    Filter
                </AppButton>
                <AppButton tone="ghost" size="sm" @click="resetFilters">
                    Reset
                </AppButton>
            </div>

            <!-- Adjustments Table -->
            <div class="bg-white border border-[#E5E7EB] overflow-hidden">
                <div v-if="adjustments.data.length === 0" class="p-8">
                    <EmptyState
                        title="No stock adjustments found"
                        description="Try clearing your filters or create a new signed stock adjustment entry."
                        icon="adjustments"
                    >
                        <template #action>
                            <AppButton
                                v-if="canCreate"
                                tone="primary"
                                icon="plus"
                                @click="openCreateModal"
                            >
                                Log Stock Adjustment
                            </AppButton>
                        </template>
                    </EmptyState>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-sm border-collapse">
                        <thead>
                            <tr class="bg-[#F9FAFB] border-b border-[#E5E7EB] text-xs font-semibold text-[#4B5563] uppercase tracking-wider">
                                <th class="py-3 px-4">Date</th>
                                <th class="py-3 px-3">Type</th>
                                <th class="py-3 px-4">Item Name</th>
                                <th class="py-3 px-3 text-right">Adjustment Quantity</th>
                                <th class="py-3 px-3 text-center">Reason</th>
                                <th class="py-3 px-4">Audit Note</th>
                                <th class="py-3 px-4 text-right">Created By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E5E7EB]">
                            <tr
                                v-for="adj in adjustments.data"
                                :key="adj.id"
                                class="hover:bg-[#F9FAFB] transition-colors"
                            >
                                <td class="py-3 px-4 whitespace-nowrap text-xs font-medium text-[#111827]">
                                    {{ adj.adjusted_on }}
                                </td>
                                <td class="py-3 px-3 whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-semibold border"
                                        :class="getTypeBadge(adj.item_type).classes"
                                    >
                                        <AppIcon :name="getTypeBadge(adj.item_type).icon" :size="12" />
                                        {{ getTypeBadge(adj.item_type).label }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 font-semibold text-[#111827]">
                                    {{ adj.item_name }}
                                </td>
                                <td class="py-3 px-3 text-right tabular-nums font-bold text-sm">
                                    <span :class="adj.qty >= 0 ? 'text-[#059669]' : 'text-[#DC2626]'">
                                        {{ adj.qty > 0 ? '+' : '' }}{{ formatQuantity(adj.qty) }} {{ adj.unit }}
                                    </span>
                                </td>
                                <td class="py-3 px-3 text-center whitespace-nowrap">
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-semibold border"
                                        :class="getReasonBadge(adj.reason).classes"
                                    >
                                        <AppIcon :name="getReasonBadge(adj.reason).icon" :size="12" />
                                        {{ getReasonBadge(adj.reason).label }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-xs text-[#4B5563]">
                                    {{ adj.note || '—' }}
                                </td>
                                <td class="py-3 px-4 text-right text-xs text-[#6B7280] whitespace-nowrap">
                                    <div>{{ adj.creator }}</div>
                                    <div class="text-[10px] text-[#9CA3AF]">{{ adj.created_at }}</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="p-4 border-t border-[#E5E7EB]">
                        <TablePagination
                            :current-page="adjustments.current_page"
                            :total-items="adjustments.total"
                            :per-page="adjustments.per_page"
                            @update:current-page="(page) => router.get('/inventory/adjustments', { ...filters, page }, { preserveState: true })"
                        />
                    </div>
                </div>
            </div>

            <!-- MODAL: NEW UNIVERSAL ADJUSTMENT -->
            <div
                v-if="showModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-none p-4"
            >
                <div class="bg-white border border-[#E5E7EB] shadow-lg w-full max-w-lg p-6 space-y-4">
                    <h2 class="text-lg font-bold text-[#111827]">New Stock Adjustment</h2>
                    <p class="text-xs text-[#6B7280]">
                        Record a manual adjustment for damaged goods, physical count corrections, or missing items.
                    </p>

                    <form class="space-y-4" @submit.prevent="submitAdjustment">
                        <!-- Item Type Selector -->
                        <FormField label="Inventory Item Type" :error="form.errors.item_type" required>
                            <div class="grid grid-cols-3 gap-2">
                                <button
                                    type="button"
                                    class="py-2 px-3 text-xs font-semibold border text-center transition-colors cursor-pointer flex items-center justify-center gap-1.5"
                                    :class="[
                                        form.item_type === 'product'
                                            ? 'border-[#0B6E5E] bg-[#E6F4F1] text-[#0B6E5E]'
                                            : 'border-[#D1D5DB] bg-white text-[#4B5563] hover:bg-[#F9FAFB]'
                                    ]"
                                    @click="form.item_type = 'product'"
                                >
                                    <AppIcon name="products" :size="14" />
                                    Finished Product
                                </button>
                                <button
                                    type="button"
                                    class="py-2 px-3 text-xs font-semibold border text-center transition-colors cursor-pointer flex items-center justify-center gap-1.5"
                                    :class="[
                                        form.item_type === 'leather'
                                            ? 'border-[#0B6E5E] bg-[#E6F4F1] text-[#0B6E5E]'
                                            : 'border-[#D1D5DB] bg-white text-[#4B5563] hover:bg-[#F9FAFB]'
                                    ]"
                                    @click="form.item_type = 'leather'"
                                >
                                    <AppIcon name="leather" :size="14" />
                                    Raw Leather
                                </button>
                                <button
                                    type="button"
                                    class="py-2 px-3 text-xs font-semibold border text-center transition-colors cursor-pointer flex items-center justify-center gap-1.5"
                                    :class="[
                                        form.item_type === 'accessory'
                                            ? 'border-[#0B6E5E] bg-[#E6F4F1] text-[#0B6E5E]'
                                            : 'border-[#D1D5DB] bg-white text-[#4B5563] hover:bg-[#F9FAFB]'
                                    ]"
                                    @click="form.item_type = 'accessory'"
                                >
                                    <AppIcon name="accessories" :size="14" />
                                    Accessory
                                </button>
                            </div>
                        </FormField>

                        <!-- Dynamic Item Dropdown -->
                        <FormField label="Select Item" :error="form.errors.item_id" required>
                            <div v-if="itemsLoading" class="text-xs text-[#6B7280] py-2">
                                Loading {{ form.item_type }} items...
                            </div>
                            <select
                                v-else
                                v-model="form.item_id"
                                class="w-full px-3 py-2 border border-[#D1D5DB] text-sm focus:border-[#0B6E5E] focus:outline-none"
                            >
                                <option value="" disabled>-- Choose an item --</option>
                                <option v-for="item in availableItems" :key="item.id" :value="String(item.id)">
                                    {{ item.name }} (Current: {{ formatQuantity(item.current_stock) }} {{ item.unit }})
                                </option>
                            </select>
                        </FormField>

                        <!-- Signed Qty and Date -->
                        <div class="grid grid-cols-2 gap-3">
                            <FormField label="Adjustment Quantity (+/-)" :error="form.errors.qty" required>
                                <input
                                    v-model="form.qty"
                                    type="number"
                                    step="0.001"
                                    placeholder="e.g. -5 or +10"
                                    class="w-full px-3 py-2 border border-[#D1D5DB] text-sm focus:border-[#0B6E5E] focus:outline-none font-mono"
                                />
                            </FormField>
                            <FormField label="Adjustment Date" :error="form.errors.adjusted_on" required>
                                <input
                                    v-model="form.adjusted_on"
                                    type="date"
                                    class="w-full px-3 py-2 border border-[#D1D5DB] text-sm focus:border-[#0B6E5E] focus:outline-none"
                                />
                            </FormField>
                        </div>

                        <!-- Live Stock Balance Calculation Preview -->
                        <div
                            v-if="selectedItem && form.qty"
                            class="p-3 border text-xs flex justify-between items-center"
                            :class="[
                                projectedStock !== null && projectedStock < 0
                                    ? 'bg-[#FEE2E2] border-[#FCA5A5] text-[#991B1B]'
                                    : 'bg-[#F9FAFB] border-[#E5E7EB] text-[#374151]'
                            ]"
                        >
                            <span>
                                Current: <strong>{{ formatQuantity(selectedItem.current_stock) }} {{ selectedItem.unit }}</strong>
                            </span>
                            <span class="font-bold text-sm">
                                Projected Stock: {{ formatQuantity(projectedStock) }} {{ selectedItem.unit }}
                            </span>
                        </div>

                        <!-- Reason -->
                        <FormField label="Reason for Adjustment" :error="form.errors.reason" required>
                            <select
                                v-model="form.reason"
                                class="w-full px-3 py-2 border border-[#D1D5DB] text-sm focus:border-[#0B6E5E] focus:outline-none"
                            >
                                <option value="damaged">Damaged Material / Production Flaw</option>
                                <option value="lost">Lost / Missing from Shelf</option>
                                <option value="count_correction">Count Correction (Physical Stock Audit)</option>
                                <option value="other">Other (Requires Detailed Note)</option>
                            </select>
                        </FormField>

                        <!-- Note -->
                        <FormField
                            label="Audit Note"
                            :error="form.errors.note"
                            :required="form.reason === 'other'"
                        >
                            <input
                                v-model="form.note"
                                type="text"
                                placeholder="Audit note, discrepancy explanation, reference #"
                                class="w-full px-3 py-2 border border-[#D1D5DB] text-sm focus:border-[#0B6E5E] focus:outline-none"
                            />
                        </FormField>

                        <div class="flex justify-end gap-2 pt-4 border-t border-[#E5E7EB]">
                            <AppButton tone="ghost" type="button" @click="showModal = false">
                                Cancel
                            </AppButton>
                            <AppButton tone="primary" type="submit" :loading="form.processing">
                                Save Adjustment
                            </AppButton>
                        </div>
                    </form>
                </div>
            </div>
    </div>
</template>
