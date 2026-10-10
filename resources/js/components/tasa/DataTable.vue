<script setup lang="ts">
export interface ColumnDef {
    key: string;
    label: string;
    align?: 'left' | 'center' | 'right';
    width?: string;
    isPrimary?: boolean;
    isNumeric?: boolean;
}

interface Props {
    columns: ColumnDef[];
    items: any[];
    loading?: boolean;
    compact?: boolean;
    stickyHeader?: boolean;
}

withDefaults(defineProps<Props>(), {
    loading: false,
    compact: false,
    stickyHeader: true,
});

const emit = defineEmits<{
    (e: 'row-click', item: any): void;
}>();
</script>

<template>
    <div class="w-full bg-surface border border-line-200 overflow-hidden">
        <!-- Desktop Table View (>= 640px) -->
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <!-- Header Row -->
                <thead :class="['bg-sunken border-b border-line-300', stickyHeader ? 'sticky top-0 z-10' : '']">
                    <tr>
                        <th
                            v-for="col in columns"
                            :key="col.key"
                            scope="col"
                            :class="[
                                'px-4 py-3 text-xs font-semibold uppercase tracking-wider text-ink-700',
                                col.align === 'right' || col.isNumeric ? 'text-right' : col.align === 'center' ? 'text-center' : 'text-left',
                                col.width || '',
                            ]"
                        >
                            {{ col.label }}
                        </th>
                    </tr>
                </thead>

                <!-- Body Rows -->
                <tbody class="divide-y divide-line-200 text-sm text-ink-700">
                    <tr v-if="loading">
                        <td :colspan="columns.length" class="p-8 text-center text-ink-500">
                            Loading records...
                        </td>
                    </tr>
                    <tr v-else-if="items.length === 0">
                        <td :colspan="columns.length" class="p-8 text-center text-ink-500">
                            No records found.
                        </td>
                    </tr>
                    <tr
                        v-for="(item, idx) in items"
                        v-else
                        :key="item.id || idx"
                        :class="[
                            'hover:bg-teal-50 transition-colors cursor-pointer',
                            compact ? 'h-[40px]' : 'h-[48px]',
                        ]"
                        @click="emit('row-click', item)"
                    >
                        <td
                            v-for="col in columns"
                            :key="col.key"
                            :class="[
                                'px-4 py-2.5',
                                col.align === 'right' || col.isNumeric ? 'text-right tabular-nums' : col.align === 'center' ? 'text-center' : 'text-left',
                                col.isPrimary ? 'font-semibold text-ink-900' : '',
                            ]"
                        >
                            <slot :name="`cell-${col.key}`" :item="item" :value="item[col.key]">
                                {{ item[col.key] }}
                            </slot>
                        </td>
                    </tr>
                </tbody>

                <!-- Totals Row if slot provided -->
                <tfoot v-if="$slots.totals" class="bg-sunken border-t-2 border-ink-900 font-bold text-sm text-ink-900">
                    <slot name="totals" />
                </tfoot>
            </table>
        </div>

        <!-- Mobile Card List View (< 640px) -->
        <div class="sm:hidden p-3 space-y-3">
            <slot name="mobile-cards" :items="items">
                <div v-if="loading" class="p-6 text-center text-ink-500 text-sm">
                    Loading records...
                </div>
                <div v-else-if="items.length === 0" class="p-6 text-center text-ink-500 text-sm">
                    No records found.
                </div>
                <div
                    v-for="(item, idx) in items"
                    v-else
                    :key="item.id || idx"
                    class="bg-surface border border-line-200 p-4 text-xs space-y-2 hover:bg-teal-50"
                    @click="emit('row-click', item)"
                >
                    <div v-for="col in columns" :key="col.key" class="flex justify-between items-center py-0.5">
                        <span class="text-ink-600 font-medium">{{ col.label }}</span>
                        <span :class="[col.isPrimary ? 'font-bold text-ink-900' : 'text-ink-700', col.isNumeric ? 'tabular-nums' : '']">
                            <slot :name="`cell-${col.key}`" :item="item" :value="item[col.key]">
                                {{ item[col.key] }}
                            </slot>
                        </span>
                    </div>
                </div>
            </slot>
        </div>

        <!-- Pagination Slot -->
        <slot name="pagination" />
    </div>
</template>
