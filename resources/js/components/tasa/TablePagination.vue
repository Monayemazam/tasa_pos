<script setup lang="ts">
import AppButton from './AppButton.vue';

interface Props {
    currentPage: number;
    totalItems: number;
    perPage: number;
}

const props = withDefaults(defineProps<Props>(), {
    currentPage: 1,
    perPage: 25,
});

const emit = defineEmits<{
    (e: 'update:currentPage', page: number): void;
    (e: 'update:perPage', perPage: number): void;
}>();

const totalPages = Math.ceil(props.totalItems / props.perPage) || 1;
const startItem = props.totalItems === 0 ? 0 : (props.currentPage - 1) * props.perPage + 1;
const endItem = Math.min(props.currentPage * props.perPage, props.totalItems);
</script>

<template>
    <div
        class="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 py-3 bg-surface border-t border-line-200 text-xs text-ink-600"
    >
        <div>
            Showing <span class="font-bold text-ink-900 tabular-nums">{{ startItem }}–{{ endItem }}</span> of
            <span class="font-bold text-ink-900 tabular-nums">{{ totalItems }}</span> items
        </div>

        <div class="flex items-center space-x-2">
            <AppButton
                variant="secondary"
                size="sm"
                icon="chevron-left"
                :disabled="currentPage <= 1"
                @click="emit('update:currentPage', currentPage - 1)"
            >
                Prev
            </AppButton>

            <span class="px-2 font-medium tabular-nums text-ink-700">
                Page {{ currentPage }} of {{ totalPages }}
            </span>

            <AppButton
                variant="secondary"
                size="sm"
                icon-right="chevron-right"
                :disabled="currentPage >= totalPages"
                @click="emit('update:currentPage', currentPage + 1)"
            >
                Next
            </AppButton>
        </div>
    </div>
</template>
