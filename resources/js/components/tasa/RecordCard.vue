<script setup lang="ts">
interface Props {
    title: string;
    badgeLabel?: string;
    badgeTone?: 'success' | 'warning' | 'danger' | 'info' | 'advance' | 'neutral';
    subtitle?: string;
    date?: string;
    clickable?: boolean;
}

withDefaults(defineProps<Props>(), {
    clickable: true,
});

const emit = defineEmits<{
    (e: 'click'): void;
}>();
</script>

<template>
    <div
        :class="[
            'bg-surface border border-line-200 p-4 transition-colors',
            clickable ? 'cursor-pointer hover:bg-teal-50 active:bg-teal-100 min-h-[56px]' : '',
        ]"
        @click="clickable && emit('click')"
    >
        <!-- Header Row: Title & Badge -->
        <div class="flex items-center justify-between gap-2 mb-1.5">
            <span class="text-sm font-bold text-ink-900 truncate">
                {{ title }}
            </span>
            <slot name="badge">
                <span
                    v-if="badgeLabel"
                    class="text-xs px-2 py-0.5 font-semibold rounded-none border border-line-300 bg-sunken text-ink-700"
                >
                    {{ badgeLabel }}
                </span>
            </slot>
        </div>

        <!-- Subtitle and Date -->
        <div v-if="subtitle || date" class="text-xs text-ink-600 mb-3 space-x-2">
            <span v-if="subtitle">{{ subtitle }}</span>
            <span v-if="subtitle && date">·</span>
            <span v-if="date" class="tabular-nums">{{ date }}</span>
        </div>

        <!-- Card Divider -->
        <div class="border-t border-line-200 pt-2.5 mt-2 space-y-1.5 text-xs text-ink-700">
            <slot />
        </div>
    </div>
</template>
