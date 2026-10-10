<script setup lang="ts">
import { computed } from 'vue';
import AppIcon from './AppIcon.vue';
import { type IconConcept } from '@/lib/icons';

export type BadgeTone = 'success' | 'warning' | 'danger' | 'info' | 'advance' | 'neutral';

interface Props {
    label: string;
    tone?: BadgeTone;
    icon?: IconConcept;
}

const props = withDefaults(defineProps<Props>(), {
    tone: 'neutral',
});

// Select default icon based on tone if not explicitly provided
const resolvedIcon = computed<IconConcept>(() => {
    if (props.icon) return props.icon;
    switch (props.tone) {
        case 'success':
            return 'check-circle';
        case 'warning':
            return 'warning';
        case 'danger':
            return 'danger';
        case 'info':
            return 'info';
        case 'advance':
            return 'advance';
        case 'neutral':
        default:
            return 'info';
    }
});

// Style mappings from UX §3.2 and §5.5
const toneClasses = computed(() => {
    switch (props.tone) {
        case 'success':
            // Solid #1B7F3B, text #14602C, bg #E7F4EA, 1px border
            return 'bg-[#E7F4EA] text-[#14602C] border-[#A8DBB5]';
        case 'warning':
            // Solid #B26A00, text #8A5A00, bg #FFF4DB, 1px border
            return 'bg-[#FFF4DB] text-[#8A5A00] border-[#E8D19B]';
        case 'danger':
            // Solid #B42318, text #8E1B12, bg #FDECEA, 1px border
            return 'bg-[#FDECEA] text-[#8E1B12] border-[#F4B2AC]';
        case 'info':
            // Solid #1D5FA8, text #174B85, bg #E8F0FA, 1px border
            return 'bg-[#E8F0FA] text-[#174B85] border-[#A9C8ED]';
        case 'advance':
            // Solid #6B3FA0, text #55327F, bg #F1EAF8, 1px border (Over-paid)
            return 'bg-[#F1EAF8] text-[#55327F] border-[#D0BAEC]';
        case 'neutral':
        default:
            return 'bg-sunken text-ink-700 border-line-200';
    }
});
</script>

<template>
    <span
        :class="[
            'inline-flex items-center h-[24px] px-2 text-xs font-semibold rounded-none border space-x-1.5 whitespace-nowrap',
            toneClasses,
        ]"
    >
        <AppIcon :name="resolvedIcon" :size="14" class="flex-shrink-0" />
        <span>{{ label }}</span>
    </span>
</template>
