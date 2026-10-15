<script setup lang="ts">
import { computed, watch, onMounted, onUnmounted } from 'vue';
import AppIcon from './AppIcon.vue';

interface Props {
    open?: boolean;
    isOpen?: boolean;
    title: string;
    subtitle?: string;
    widthClass?: string;
    width?: string;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'close'): void;
}>();

const isVisible = computed(() => Boolean(props.open || props.isOpen));

const computedWidthClass = computed(() => {
    if (props.widthClass) return props.widthClass;
    if (props.width) {
        if (props.width === '640px') return 'sm:max-w-[640px]';
        if (props.width === '560px') return 'sm:max-w-[560px]';
        if (props.width === '480px') return 'sm:max-w-[480px]';
    }
    return 'sm:max-w-[480px]';
});

const handleKeyDown = (e: KeyboardEvent) => {
    if (e.key === 'Escape' && isVisible.value) {
        emit('close');
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
});

watch(isVisible, (val) => {
    if (typeof document !== 'undefined') {
        if (val) {
            document.body.classList.add('overflow-hidden');
        } else {
            document.body.classList.remove('overflow-hidden');
        }
    }
});
</script>

<template>
    <div
        v-if="isVisible"
        class="fixed inset-0 z-50 overflow-hidden"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="`${title}-drawer-title`"
    >
        <!-- Overlay Scrim: flat #0F1B1A at 40%, no glass/blur -->
        <div
            class="fixed inset-0 bg-[#0F1B1A] bg-opacity-40 transition-opacity"
            aria-hidden="true"
            @click="emit('close')"
        />

        <div class="fixed inset-y-0 right-0 flex max-w-full pl-0 sm:pl-10">
            <!-- Drawer: 480px width on desktop (or widthClass override); full-screen on mobile (< 640px) -->
            <div
                :class="[
                    'w-screen max-w-full h-full bg-surface border-l border-line-300 flex flex-col rounded-none',
                    computedWidthClass,
                ]"
            >
                <!-- Drawer Header (56px) with sticky top -->
                <div class="h-14 px-6 border-b border-line-200 flex items-center justify-between bg-surface sticky top-0 z-10 flex-shrink-0">
                    <div class="min-w-0 pr-3">
                        <h2 :id="`${title}-drawer-title`" class="text-base font-bold text-ink-900 truncate">
                            {{ title }}
                        </h2>
                        <p v-if="subtitle" class="text-xs text-ink-600 truncate">
                            {{ subtitle }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="text-ink-600 hover:text-ink-900 p-2 focus:outline-teal-700 flex-shrink-0"
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
                    class="p-4 border-t border-line-200 bg-page flex items-center justify-end space-x-3 sticky bottom-0 z-10 flex-shrink-0"
                >
                    <slot name="footer" />
                </div>
            </div>
        </div>
    </div>
</template>
