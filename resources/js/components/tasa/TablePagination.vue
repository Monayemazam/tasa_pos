<script setup lang="ts">
import { computed } from 'vue';
import AppButton from './AppButton.vue';

interface Props {
    currentPage?: number;
    totalItems?: number;
    perPage?: number;
    lastPage?: number;
    from?: number | null;
    to?: number | null;
    total?: number;
    links?: any[];
    paginator?: any;
}

const props = withDefaults(defineProps<Props>(), {
    currentPage: 1,
    totalItems: 0,
    perPage: 25,
});

const emit = defineEmits<{
    (e: 'update:currentPage', page: number): void;
    (e: 'update:perPage', perPage: number): void;
}>();

const effectiveCurrentPage = computed(() => props.paginator?.currentPage ?? props.paginator?.current_page ?? props.currentPage ?? 1);
const effectiveTotalItems = computed(() => props.paginator?.total ?? props.total ?? props.totalItems ?? 0);
const effectivePerPage = computed(() => props.paginator?.perPage ?? props.paginator?.per_page ?? props.perPage ?? 25);

const totalPages = computed(() => Math.ceil(effectiveTotalItems.value / effectivePerPage.value) || 1);
const startItem = computed(() => effectiveTotalItems.value === 0 ? 0 : (effectiveCurrentPage.value - 1) * effectivePerPage.value + 1);
const endItem = computed(() => Math.min(effectiveCurrentPage.value * effectivePerPage.value, effectiveTotalItems.value));
</script>

<template>
    <div
        class="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 py-3 bg-surface border-t border-line-200 text-xs text-ink-600"
    >
        <div>
            Showing <span class="font-bold text-ink-900 tabular-nums">{{ startItem }}–{{ endItem }}</span> of
            <span class="font-bold text-ink-900 tabular-nums">{{ effectiveTotalItems }}</span> items
        </div>

        <div class="flex items-center space-x-2">
            <AppButton
                variant="secondary"
                size="sm"
                icon="chevron-left"
                :disabled="effectiveCurrentPage <= 1"
                @click="emit('update:currentPage', effectiveCurrentPage - 1)"
            >
                Prev
            </AppButton>

            <span class="px-2 font-medium tabular-nums text-ink-700">
                Page {{ effectiveCurrentPage }} of {{ totalPages }}
            </span>

            <AppButton
                variant="secondary"
                size="sm"
                icon-right="chevron-right"
                :disabled="effectiveCurrentPage >= totalPages"
                @click="emit('update:currentPage', effectiveCurrentPage + 1)"
            >
                Next
            </AppButton>
        </div>
    </div>
</template>
