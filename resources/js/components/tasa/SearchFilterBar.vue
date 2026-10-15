<script setup lang="ts">
import { ref, watch } from 'vue';
import AppIcon from './AppIcon.vue';
import AppButton from './AppButton.vue';

export interface FilterChip {
    key: string;
    label: string;
    value: string;
}

interface Props {
    searchPlaceholder?: string;
    modelValue?: string;
    activeFilters?: FilterChip[];
    showExport?: boolean;
    mobileTitle?: string;
}

const props = withDefaults(defineProps<Props>(), {
    searchPlaceholder: 'Search by keyword, invoice, or phone...',
    modelValue: '',
    activeFilters: () => [],
    showExport: false,
    mobileTitle: 'Filter Records',
});

const emit = defineEmits<{
    (e: 'update:modelValue', val: string): void;
    (e: 'remove-filter', key: string): void;
    (e: 'clear-all'): void;
    (e: 'export'): void;
    (e: 'apply-mobile'): void;
}>();

const query = ref(props.modelValue);
const isMobileFilterOpen = ref(false);
let debounceTimer: any = null;

watch(query, (val) => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        emit('update:modelValue', val);
    }, 250);
});

watch(
    () => props.modelValue,
    (val) => {
        query.value = val;
    }
);

const handleApplyMobile = () => {
    isMobileFilterOpen.value = false;
    emit('apply-mobile');
};
</script>

<template>
    <div class="space-y-2 mb-4 w-full">
        <!-- Main Bar Row -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
            <!-- Search Box (Left) -->
            <div class="relative flex-1 max-w-md">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-ink-500">
                    <AppIcon name="search" :size="16" />
                </div>
                <input
                    v-model="query"
                    type="search"
                    :placeholder="searchPlaceholder"
                    class="w-full h-[40px] pl-9 pr-8 text-sm text-ink-900 bg-surface border border-line-400 rounded-none focus:border-teal-700 focus:outline-teal-700"
                />
                <button
                    v-if="query"
                    type="button"
                    class="absolute inset-y-0 right-0 flex items-center pr-2.5 text-ink-500 hover:text-ink-900"
                    aria-label="Clear search"
                    @click="query = ''"
                >
                    <AppIcon name="close" :size="14" />
                </button>
            </div>

            <!-- Mobile Filter Trigger Button (< 640px) -->
            <div class="sm:hidden flex items-center gap-2">
                <button
                    type="button"
                    class="flex-1 flex items-center justify-center gap-2 h-[40px] px-3 bg-surface border border-line-400 text-xs font-bold text-ink-800 hover:bg-teal-50"
                    @click="isMobileFilterOpen = true"
                >
                    <AppIcon name="filter" :size="16" />
                    <span>Filters</span>
                    <span
                        v-if="activeFilters.length > 0"
                        class="px-1.5 py-0.5 bg-teal-700 text-white text-[10px] font-bold"
                    >
                        {{ activeFilters.length }}
                    </span>
                </button>

                <AppButton
                    v-if="showExport"
                    variant="secondary"
                    icon="download"
                    @click="emit('export')"
                >
                    Export
                </AppButton>
            </div>

            <!-- Desktop Filters, Date Picker & Export Slot (>= 640px) -->
            <div class="hidden sm:flex flex-wrap items-center gap-2 sm:justify-end">
                <slot name="filters" />

                <AppButton
                    v-if="showExport"
                    variant="secondary"
                    icon="download"
                    @click="emit('export')"
                >
                    Export
                </AppButton>
            </div>
        </div>

        <!-- Active Filter Chips Row -->
        <div
            v-if="activeFilters.length > 0"
            class="flex flex-wrap items-center gap-2 pt-1 text-xs"
        >
            <span class="text-ink-600 font-semibold">Active filters:</span>

            <span
                v-for="chip in activeFilters"
                :key="chip.key"
                class="inline-flex items-center px-2 py-1 bg-teal-100 text-teal-700 font-medium border border-teal-200"
            >
                <span class="mr-1.5">{{ chip.label }}: <strong>{{ chip.value }}</strong></span>
                <button
                    type="button"
                    class="hover:text-teal-900 font-bold p-0.5"
                    :aria-label="`Remove filter ${chip.label}`"
                    @click="emit('remove-filter', chip.key)"
                >
                    <AppIcon name="close" :size="12" />
                </button>
            </span>

            <button
                type="button"
                class="text-xs text-ink-600 hover:text-danger font-semibold underline ml-1"
                @click="emit('clear-all')"
            >
                Clear all
            </button>
        </div>

        <!-- FULL-SCREEN MOBILE FILTER SHEET (< 640px) -->
        <div
            v-if="isMobileFilterOpen"
            class="sm:hidden fixed inset-0 z-50 bg-surface flex flex-col"
            role="dialog"
            aria-modal="true"
            aria-label="Filter Options"
        >
            <!-- Mobile Sticky Header -->
            <div class="h-[56px] px-4 border-b border-line-200 flex items-center justify-between bg-surface sticky top-0 z-10">
                <div class="flex items-center gap-2">
                    <h2 class="text-base font-bold text-ink-900">{{ mobileTitle }}</h2>
                    <span
                        v-if="activeFilters.length > 0"
                        class="px-2 py-0.5 bg-teal-100 text-teal-800 text-xs font-bold"
                    >
                        {{ activeFilters.length }} active
                    </span>
                </div>
                <button
                    type="button"
                    class="p-2 text-ink-700 hover:bg-teal-50"
                    aria-label="Close filter sheet"
                    @click="isMobileFilterOpen = false"
                >
                    <AppIcon name="close" :size="20" />
                </button>
            </div>

            <!-- Scrollable Body with Filter Controls -->
            <div class="flex-1 overflow-y-auto p-4 space-y-4">
                <slot name="mobile-filters">
                    <div class="space-y-4">
                        <slot name="filters" />
                    </div>
                </slot>
            </div>

            <!-- Sticky Action Bar at Bottom -->
            <div class="p-4 border-t border-line-200 bg-page flex items-center justify-between gap-3 sticky bottom-0 z-10">
                <button
                    type="button"
                    class="text-xs text-ink-600 hover:text-danger underline font-semibold px-2 py-2"
                    @click="emit('clear-all'); isMobileFilterOpen = false;"
                >
                    Reset all
                </button>
                <AppButton
                    variant="primary"
                    class="flex-1"
                    @click="handleApplyMobile"
                >
                    Apply filters
                </AppButton>
            </div>
        </div>
    </div>
</template>
