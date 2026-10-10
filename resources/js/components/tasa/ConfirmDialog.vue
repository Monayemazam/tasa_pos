<script setup lang="ts">
import { ref, watch } from 'vue';
import AppButton from './AppButton.vue';
import FormField from './FormField.vue';

interface Props {
    open: boolean;
    title: string;
    description: string;
    confirmLabel?: string;
    cancelLabel?: string;
    requireReason?: boolean;
    reasonPlaceholder?: string;
    danger?: boolean;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    confirmLabel: 'Confirm',
    cancelLabel: 'Keep it',
    requireReason: false,
    reasonPlaceholder: 'Please state the reason for this action...',
    danger: true,
    loading: false,
});

const emit = defineEmits<{
    (e: 'confirm', reason?: string): void;
    (e: 'cancel'): void;
}>();

const reason = ref('');
const reasonError = ref('');

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            reason.value = '';
            reasonError.value = '';
        }
    }
);

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
        v-if="open"
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
                {{ description }}
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
                        v-model="reason"
                        rows="2"
                        :placeholder="reasonPlaceholder"
                        class="w-full p-2.5 text-sm text-ink-900 bg-surface border border-line-400 rounded-none focus:border-teal-700 focus:outline-teal-700 focus:outline-2"
                    />
                </FormField>
            </div>

            <!-- Action buttons: Secondary on left, Danger/Primary on right -->
            <div class="flex items-center justify-end space-x-3 mt-6">
                <AppButton
                    variant="secondary"
                    :disabled="loading"
                    @click="emit('cancel')"
                >
                    {{ cancelLabel }}
                </AppButton>

                <AppButton
                    :variant="danger ? 'danger' : 'primary'"
                    :loading="loading"
                    @click="handleConfirm"
                >
                    {{ confirmLabel }}
                </AppButton>
            </div>
        </div>
    </div>
</template>
