<script setup lang="ts">
import { ref, computed } from 'vue';
import AppButton from './AppButton.vue';
import AppIcon from './AppIcon.vue';
import { formatDate } from '@/lib/formatters';

export interface DateRange {
    from: string; // YYYY-MM-DD
    to: string;   // YYYY-MM-DD
}

interface Props {
    modelValue: DateRange;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:modelValue', val: DateRange): void;
    (e: 'change', val: DateRange): void;
}>();

const isOpen = ref(false);

const toDateInputString = (d: Date) => {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
};

const customFrom = ref(props.modelValue.from);
const customTo = ref(props.modelValue.to);

// Presets
const applyPreset = (preset: 'today' | 'yesterday' | 'last7' | 'thisMonth' | 'lastMonth' | 'thisYear') => {
    const now = new Date();
    let from = new Date();
    let to = new Date();

    switch (preset) {
        case 'today':
            from = now;
            to = now;
            break;
        case 'yesterday':
            from = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1);
            to = from;
            break;
        case 'last7':
            from = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 6);
            to = now;
            break;
        case 'thisMonth':
            from = new Date(now.getFullYear(), now.getMonth(), 1);
            to = now;
            break;
        case 'lastMonth':
            from = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            to = new Date(now.getFullYear(), now.getMonth(), 0);
            break;
        case 'thisYear':
            from = new Date(now.getFullYear(), 0, 1);
            to = now;
            break;
    }

    const range: DateRange = {
        from: toDateInputString(from),
        to: toDateInputString(to),
    };

    customFrom.value = range.from;
    customTo.value = range.to;
    emit('update:modelValue', range);
    emit('change', range);
    isOpen.value = false;
};

const applyCustom = () => {
    const range: DateRange = {
        from: customFrom.value,
        to: customTo.value,
    };
    emit('update:modelValue', range);
    emit('change', range);
    isOpen.value = false;
};

const displayLabel = computed(() => {
    if (!props.modelValue.from || !props.modelValue.to) return 'Select date range';
    if (props.modelValue.from === props.modelValue.to) {
        return formatDate(props.modelValue.from);
    }
    return `${formatDate(props.modelValue.from)} – ${formatDate(props.modelValue.to)}`;
});
</script>

<template>
    <div class="relative inline-block text-left">
        <!-- Trigger Button -->
        <button
            type="button"
            class="h-[40px] px-3.5 bg-surface border border-line-400 text-ink-700 text-xs font-semibold
                   flex items-center space-x-2 hover:bg-teal-50 focus:outline-teal-700"
            @click="isOpen = !isOpen"
        >
            <AppIcon name="calendar" :size="16" class="text-ink-500" />
            <span class="tabular-nums">{{ displayLabel }}</span>
            <AppIcon name="chevron-down" :size="14" class="text-ink-500 ml-1" />
        </button>

        <!-- Dropdown Popover: Elevation 1 -->
        <div
            v-if="isOpen"
            class="absolute right-0 z-30 mt-1 w-[320px] sm:w-[360px] bg-surface border border-line-300 shadow-xl p-4 rounded-none"
        >
            <div class="flex items-center justify-between pb-3 border-b border-line-200 mb-3">
                <span class="text-xs font-bold text-ink-900 uppercase tracking-wider">Date Presets</span>
                <button type="button" class="text-ink-500 hover:text-ink-900" @click="isOpen = false">
                    <AppIcon name="close" :size="14" />
                </button>
            </div>

            <!-- Presets Grid -->
            <div class="grid grid-cols-2 gap-2 mb-4 text-xs">
                <button
                    type="button"
                    class="py-1.5 px-2.5 text-left border border-line-200 hover:bg-teal-50 font-medium text-ink-700"
                    @click="applyPreset('today')"
                >
                    Today
                </button>
                <button
                    type="button"
                    class="py-1.5 px-2.5 text-left border border-line-200 hover:bg-teal-50 font-medium text-ink-700"
                    @click="applyPreset('yesterday')"
                >
                    Yesterday
                </button>
                <button
                    type="button"
                    class="py-1.5 px-2.5 text-left border border-line-200 hover:bg-teal-50 font-medium text-ink-700"
                    @click="applyPreset('last7')"
                >
                    Last 7 days
                </button>
                <button
                    type="button"
                    class="py-1.5 px-2.5 text-left border border-line-200 hover:bg-teal-50 font-medium text-ink-700"
                    @click="applyPreset('thisMonth')"
                >
                    This month
                </button>
                <button
                    type="button"
                    class="py-1.5 px-2.5 text-left border border-line-200 hover:bg-teal-50 font-medium text-ink-700"
                    @click="applyPreset('lastMonth')"
                >
                    Last month
                </button>
                <button
                    type="button"
                    class="py-1.5 px-2.5 text-left border border-line-200 hover:bg-teal-50 font-medium text-ink-700"
                    @click="applyPreset('thisYear')"
                >
                    This year
                </button>
            </div>

            <!-- Custom Date Inputs -->
            <div class="space-y-2 border-t border-line-200 pt-3">
                <span class="text-xs font-bold text-ink-900 uppercase tracking-wider">Custom Range</span>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-[10px] text-ink-500 font-semibold uppercase">From</label>
                        <input
                            v-model="customFrom"
                            type="date"
                            class="w-full text-xs h-[36px] px-2 bg-surface border border-line-400 focus:border-teal-700 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label class="text-[10px] text-ink-500 font-semibold uppercase">To</label>
                        <input
                            v-model="customTo"
                            type="date"
                            class="w-full text-xs h-[36px] px-2 bg-surface border border-line-400 focus:border-teal-700 focus:outline-none"
                        />
                    </div>
                </div>

                <div class="pt-2 flex justify-end space-x-2">
                    <AppButton variant="secondary" size="sm" @click="isOpen = false">
                        Cancel
                    </AppButton>
                    <AppButton variant="primary" size="sm" @click="applyCustom">
                        Apply
                    </AppButton>
                </div>
            </div>
        </div>
    </div>
</template>
