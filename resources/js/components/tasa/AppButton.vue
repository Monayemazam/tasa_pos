<script setup lang="ts">
import { computed } from 'vue';
import AppIcon from './AppIcon.vue';
import { type IconConcept } from '@/lib/icons';

interface Props {
    variant?: 'primary' | 'secondary' | 'ghost' | 'danger' | 'danger-outline';
    size?: 'sm' | 'md' | 'lg';
    type?: 'button' | 'submit' | 'reset';
    disabled?: boolean;
    loading?: boolean;
    icon?: IconConcept;
    iconRight?: IconConcept;
    fullWidth?: boolean;
    ariaLabel?: string;
}

const props = withDefaults(defineProps<Props>(), {
    variant: 'primary',
    size: 'md',
    type: 'button',
    disabled: false,
    loading: false,
    fullWidth: false,
});

const variantClasses = computed(() => {
    if (props.disabled || props.loading) {
        return 'bg-sunken text-ink-500 border border-line-200 cursor-not-allowed';
    }

    switch (props.variant) {
        case 'primary':
            return 'bg-teal-700 text-white border border-teal-700 hover:bg-teal-800 active:bg-teal-900 shadow-none';
        case 'secondary':
            return 'bg-surface text-ink-900 border border-ink-900 hover:bg-teal-50 active:bg-teal-100 shadow-none';
        case 'ghost':
            return 'bg-transparent text-teal-700 border border-transparent hover:bg-teal-50 active:bg-teal-100 shadow-none';
        case 'danger':
            return 'bg-danger text-white border border-danger hover:bg-red-700 active:bg-red-800 shadow-none';
        case 'danger-outline':
            return 'bg-surface text-danger border border-danger hover:bg-danger-bg active:bg-red-100 shadow-none';
        default:
            return 'bg-teal-700 text-white border border-teal-700';
    }
});

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'h-[32px] px-3 text-xs';
        case 'lg':
            return 'h-[48px] px-5 text-base';
        case 'md':
        default:
            // Desktop 40px, mobile 48px touch friendly
            return 'h-[40px] md:h-[40px] px-4 text-sm';
    }
});
</script>

<template>
    <button
        :type="type"
        :disabled="disabled || loading"
        :aria-disabled="disabled || loading"
        :aria-label="ariaLabel"
        :class="[
            'inline-flex items-center justify-center font-semibold transition-colors duration-150',
            'border rounded-none focus-visible:outline-2 focus-visible:outline-teal-700 focus-visible:outline-offset-2',
            variantClasses,
            sizeClasses,
            fullWidth ? 'w-full' : '',
        ]"
    >
        <!-- Loading spinner replaces left icon -->
        <span v-if="loading" class="mr-2 inline-flex items-center">
            <AppIcon name="spinner" :size="size === 'sm' ? 16 : 20" />
        </span>
        <span v-else-if="icon" class="mr-2 inline-flex items-center">
            <AppIcon :name="icon" :size="size === 'sm' ? 16 : 20" />
        </span>

        <slot />

        <span v-if="iconRight && !loading" class="ml-2 inline-flex items-center">
            <AppIcon :name="iconRight" :size="size === 'sm' ? 16 : 20" />
        </span>
    </button>
</template>
