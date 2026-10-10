<script setup lang="ts">
import AppIcon from './AppIcon.vue';

interface Props {
    open: boolean;
    title: string;
    subtitle?: string;
}

defineProps<Props>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 overflow-hidden"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="`${title}-drawer-title`"
    >
        <!-- Overlay Scrim: flat #0F1B1A at 40% -->
        <div
            class="fixed inset-0 bg-[#0F1B1A] bg-opacity-40 transition-opacity"
            @click="emit('close')"
        />

        <div class="fixed inset-y-0 right-0 flex max-w-full pl-0 sm:pl-10">
            <!-- Drawer: 480px width on desktop; full width on mobile -->
            <div
                class="w-screen max-w-full sm:max-w-[480px] bg-surface border-l border-line-300 flex flex-col shadow-2xl rounded-none"
            >
                <!-- Drawer Header (56px) -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-line-200">
                    <div>
                        <h2 :id="`${title}-drawer-title`" class="text-base font-bold text-ink-900">
                            {{ title }}
                        </h2>
                        <p v-if="subtitle" class="text-xs text-ink-600 mt-0.5">
                            {{ subtitle }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="text-ink-600 hover:text-ink-900 p-1.5 focus:outline-teal-700"
                        aria-label="Close drawer"
                        @click="emit('close')"
                    >
                        <AppIcon name="close" :size="20" />
                    </button>
                </div>

                <!-- Drawer Scrollable Content -->
                <div class="flex-1 overflow-y-auto p-6 space-y-6">
                    <slot />
                </div>

                <!-- Sticky Action Footer if provided -->
                <div
                    v-if="$slots.footer"
                    class="p-4 border-t border-line-200 bg-page flex items-center justify-end space-x-3 sticky bottom-0"
                >
                    <slot name="footer" />
                </div>
            </div>
        </div>
    </div>
</template>
