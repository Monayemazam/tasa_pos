<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import AppButton from './AppButton.vue';
import FormField from './FormField.vue';

interface Props {
    open?: boolean;
    isOpen?: boolean;
    title: string;
    description?: string;
    message?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    requireReason?: boolean;
    reasonPlaceholder?: string;
    danger?: boolean;
    tone?: 'danger' | 'neutral' | 'warning';
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    open: undefined,
    isOpen: undefined,
    description: '',
    message: '',
    confirmLabel: 'Confirm',
    cancelLabel: 'Keep it',
    requireReason: false,
    reasonPlaceholder: 'Please state the reason for this action...',
    danger: undefined,
    tone: 'danger',
    loading: false,
});

const isVisible = computed(() => props.open ?? props.isOpen ?? false);
const resolvedDescription = computed(() => props.description || props.message || '');
const isDestructive = computed(() => props.danger ?? (props.tone === 'danger'));

const emit = defineEmits<{
    (e: 'confirm', reason?: string): void;
    (e: 'cancel'): void;
}>();

const reason = ref('');
const reasonError = ref('');
const cancelBtnRef = ref<any>(null);
const reasonInputRef = ref<HTMLTextAreaElement | null>(null);

watch(
    isVisible,
    (isOpen) => {
        if (isOpen) {
            reason.value = '';
            reasonError.value = '';
            nextTick(() => {
                if (props.requireReason && reasonInputRef.value) {
                    reasonInputRef.value.focus();
                } else if (cancelBtnRef.value?.$el) {
                    cancelBtnRef.value.$el.focus();
                }
            });
        }
    }
);

const onKeydown = (e: KeyboardEvent) => {
    if (isVisible.value && e.key === 'Escape') {
        emit('cancel');
    }
};

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
});

const handleConfirm = () => {
    if (props.requireReason && !reason.value.trim()) {
        reasonError.value = 'A reason is required to complete this action.';
        return;
    }
    emit('confirm', reason.value.trim());
};
</script>

<template>
    <div
        v-if="isVisible"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="`${title}-dialog-title`"
    >
        <!-- Overlay Scrim: flat #0F1B1A at 40% opacity, no blur -->
        <div
            class="fixed inset-0 bg-[#0F1B1A] bg-opacity-40 transition-opacity"
            @click="emit('cancel')"
        />

        <!-- Dialog Container: 440px width, square corners, 1px border -->
        <div
            class="relative z-10 w-full max-w-[440px] bg-surface border border-line-300 p-6 shadow-lg rounded-none"
        >
            <h2 :id="`${title}-dialog-title`" class="text-lg font-bold text-ink-900 mb-2">
                {{ title }}
            </h2>

            <p class="text-sm text-ink-600 mb-4 leading-relaxed">
                {{ resolvedDescription }}
            </p>

            <!-- Mandatory reason field if required -->
            <div v-if="requireReason" class="mb-5">
                <FormField
                    id="dialog-reason"
                    label="Reason"
                    :required="true"
                    :error="reasonError"
                >
                    <textarea
                        id="dialog-reason"
                        ref="reasonInputRef"
                        v-model="reason"
                        rows="2"
                        :placeholder="reasonPlaceholder"
                        class="w-full p-2.5 text-sm text-ink-900 bg-surface border border-line-400 rounded-none focus:border-teal-700 focus:outline-teal-700 focus:outline-2"
                    />
                </FormField>
            </div>

            <!-- Action buttons: Secondary on left (auto-focused), Destructive/Primary on right (NEVER auto-focused) -->
            <div class="flex items-center justify-end space-x-3 mt-6">
                <AppButton
                    ref="cancelBtnRef"
                    variant="secondary"
                    :disabled="loading"
                    @click="emit('cancel')"
                >
                    {{ cancelLabel }}
                </AppButton>

                <AppButton
                    :variant="isDestructive ? 'danger' : 'primary'"
                    :loading="loading"
                    @click="handleConfirm"
                >
                    {{ confirmLabel }}
                </AppButton>
            </div>
        </div>
    </div>
</template>
