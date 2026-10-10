<script setup lang="ts">
import AppIcon from './AppIcon.vue';
import AppButton from './AppButton.vue';
import { type IconConcept } from '@/lib/icons';

interface Props {
    icon?: IconConcept;
    title: string;
    description: string;
    actionLabel?: string;
    actionIcon?: IconConcept;
}

withDefaults(defineProps<Props>(), {
    icon: 'box',
});

const emit = defineEmits<{
    (e: 'action'): void;
}>();
</script>

<template>
    <div class="flex flex-col items-center justify-center py-12 px-4 text-center bg-surface border border-line-200">
        <!-- 40px Outline Icon in ink-500 -->
        <div class="mb-4 text-ink-500">
            <AppIcon :name="icon" :size="40" />
        </div>

        <!-- H3 Title -->
        <h3 class="text-base font-semibold text-ink-900 mb-1">
            {{ title }}
        </h3>

        <!-- One Sentence Description in ink-600 -->
        <p class="text-sm text-ink-600 max-w-sm mb-6">
            {{ description }}
        </p>

        <!-- One Primary Button -->
        <AppButton
            v-if="actionLabel"
            variant="primary"
            :icon="actionIcon || 'plus'"
            @click="emit('action')"
        >
            {{ actionLabel }}
        </AppButton>
    </div>
</template>
