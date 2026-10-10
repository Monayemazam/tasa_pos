<script setup lang="ts">
interface Props {
    lines?: number;
    height?: string;
    width?: string;
}

withDefaults(defineProps<Props>(), {
    lines: 1,
    height: 'h-5',
    width: 'w-full',
});
</script>

<template>
    <div class="space-y-3 w-full" role="status" aria-label="Loading...">
        <div
            v-for="i in lines"
            :key="i"
            :class="[
                'bg-sunken rounded-none animate-pulse',
                height,
                width,
                i === lines && lines > 1 ? 'w-3/4' : '',
            ]"
            style="animation-duration: 1.2s;"
        />
        <span class="sr-only">Loading...</span>
    </div>
</template>
