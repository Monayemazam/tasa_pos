<script setup lang="ts">
interface Props {
    title: string;
    value: string | number;
    subtext?: string;
    isPrimary?: boolean;
    isNegative?: boolean;
}

withDefaults(defineProps<Props>(), {
    isPrimary: false,
    isNegative: false,
});
</script>

<template>
    <div
        :class="[
            'bg-surface border border-line-200 p-5 relative overflow-hidden flex flex-col justify-between',
            isPrimary ? 'border-l-[4px] border-l-teal-700' : '',
        ]"
    >
        <!-- Overline Label -->
        <span class="text-xs uppercase font-semibold tracking-wider text-ink-600 mb-2">
            {{ title }}
        </span>

        <!-- Display Hero Number -->
        <div
            :class="[
                'text-2xl sm:text-3xl font-bold tabular-nums tracking-tight',
                isNegative ? 'text-danger' : 'text-ink-900',
            ]"
        >
            {{ value }}
        </div>

        <!-- Subtext / Delta / Count -->
        <div v-if="subtext" class="text-xs text-ink-500 font-medium mt-2">
            {{ subtext }}
        </div>
    </div>
</template>
