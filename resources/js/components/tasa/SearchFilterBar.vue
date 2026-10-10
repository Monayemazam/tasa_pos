<script setup lang="ts">
import { ref, watch } from 'vue';
import AppIcon from './AppIcon.vue';
import AppButton from './AppButton.vue';

interface FilterChip {
    key: string;
    label: string;
    value: string;
}

interface Props {
    searchPlaceholder?: string;
    modelValue?: string;
    activeFilters?: FilterChip[];
    showExport?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    searchPlaceholder: 'Search by keyword, invoice, or phone...',
    modelValue: '',
    activeFilters: () => [],
    showExport: false,
});

const emit = defineEmits<{
    (e: 'update:modelValue', val: string): void;
    (e: 'remove-filter', key: string): void;
    (e: 'clear-all'): void;
    (e: 'export'): void;
}>();

const query = ref(props.modelValue);
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

            <!-- Filters, Date Picker & Export Slot -->
            <div class="flex flex-wrap items-center gap-2 sm:justify-end">
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
                    class="hover:text-teal-900 font-bold"
                    aria-label="Remove filter"
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
    </div>
</template>
