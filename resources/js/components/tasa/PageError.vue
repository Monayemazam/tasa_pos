<script setup lang="ts">
import AppIcon from './AppIcon.vue';
import AppButton from './AppButton.vue';

interface Props {
    title?: string;
    message?: string;
    showRetry?: boolean;
}

withDefaults(defineProps<Props>(), {
    title: 'Something went wrong',
    message: 'We could not load this information right now. Please try again or contact support if the issue persists.',
    showRetry: true,
});

const emit = defineEmits<{
    (e: 'retry'): void;
}>();
</script>

<template>
    <div
        class="bg-surface border border-danger p-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4"
        role="alert"
    >
        <div class="flex items-start space-x-3.5">
            <div class="mt-0.5 text-danger flex-shrink-0">
                <AppIcon name="danger" :size="24" />
            </div>
            <div>
                <h3 class="text-base font-bold text-ink-900">
                    {{ title }}
                </h3>
                <p class="text-sm text-ink-600 mt-0.5">
                    {{ message }}
                </p>
            </div>
        </div>

        <div v-if="showRetry" class="self-end sm:self-center flex-shrink-0">
            <AppButton variant="secondary" icon="refresh" @click="emit('retry')">
                Try again
            </AppButton>
        </div>
    </div>
</template>
