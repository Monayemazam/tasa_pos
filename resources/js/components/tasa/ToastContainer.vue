<script setup lang="ts">
import { toasts, removeToast } from '@/lib/toast';
import AppIcon from './AppIcon.vue';

const getToneBarClass = (tone: string) => {
    switch (tone) {
        case 'success':
            return 'bg-success';
        case 'warning':
            return 'bg-warning';
        case 'danger':
            return 'bg-danger';
        case 'info':
            return 'bg-info';
        case 'advance':
            return 'bg-advance';
        default:
            return 'bg-teal-700';
    }
};

const getToneIcon = (tone: string) => {
    switch (tone) {
        case 'success':
            return 'check-circle';
        case 'warning':
            return 'warning';
        case 'danger':
            return 'danger';
        case 'info':
            return 'info';
        default:
            return 'info';
    }
};
</script>

<template>
    <div
        class="fixed z-50 pointer-events-none flex flex-col gap-2 p-4 w-full sm:w-auto
               top-0 sm:top-auto sm:bottom-0 right-0 max-w-[360px]"
        aria-live="polite"
        role="region"
        aria-label="Notifications"
    >
        <transition-group
            enter-active-class="transform ease-out duration-150 transition"
            enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
            enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
            leave-active-class="transition ease-in duration-100"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="pointer-events-auto flex items-start bg-surface border border-line-300 p-3.5 shadow-lg w-full relative overflow-hidden"
                role="status"
            >
                <!-- 3px Solid Status Left Bar -->
                <div
                    class="absolute left-0 top-0 bottom-0 w-[3px]"
                    :class="getToneBarClass(toast.tone || 'info')"
                />

                <!-- Icon -->
                <div class="mr-3 mt-0.5 text-ink-900 flex-shrink-0 pl-1">
                    <AppIcon :name="getToneIcon(toast.tone || 'info')" :size="18" />
                </div>

                <!-- Content -->
                <div class="flex-1 text-xs text-ink-700 font-medium leading-relaxed pr-2">
                    {{ toast.message }}
                    <div v-if="toast.actionLabel" class="mt-2">
                        <button
                            type="button"
                            class="font-bold text-teal-700 hover:underline"
                            @click="toast.onAction?.(); removeToast(toast.id)"
                        >
                            {{ toast.actionLabel }}
                        </button>
                    </div>
                </div>

                <!-- Dismiss Button -->
                <button
                    type="button"
                    class="text-ink-500 hover:text-ink-900 p-1 flex-shrink-0"
                    aria-label="Dismiss notification"
                    @click="removeToast(toast.id)"
                >
                    <AppIcon name="close" :size="14" />
                </button>
            </div>
        </transition-group>
    </div>
</template>
