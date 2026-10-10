<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    modelValue: number | string | null | undefined;
    id?: string;
    placeholder?: string;
    disabled?: boolean;
    readonly?: boolean;
    hasError?: boolean;
    min?: number;
    max?: number;
    step?: number | string;
    required?: boolean;
    ariaLabel?: string;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: '',
    placeholder: '0.00',
    disabled: false,
    readonly: false,
    hasError: false,
    step: 'any',
});

const emit = defineEmits<{
    (e: 'update:modelValue', val: number | string): void;
    (e: 'blur', event: FocusEvent): void;
}>();

const inputValue = computed({
    get: () => (props.modelValue === null || props.modelValue === undefined ? '' : props.modelValue),
    set: (val: string | number) => {
        emit('update:modelValue', val);
    },
});
</script>

<template>
    <div class="relative w-full">
        <!-- ৳ Symbol Prefix -->
        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
            <span class="text-sm font-bold text-ink-700">৳</span>
        </div>

        <input
            :id="id"
            v-model="inputValue"
            type="number"
            inputmode="decimal"
            :step="step"
            :min="min"
            :max="max"
            :placeholder="placeholder"
            :disabled="disabled"
            :readonly="readonly"
            :required="required"
            :aria-label="ariaLabel"
            :class="[
                'w-full pl-8 pr-3 text-right tabular-nums rounded-none transition-colors duration-150',
                'h-[40px] md:h-[40px] text-base md:text-sm font-medium',
                readonly
                    ? 'bg-sunken text-ink-900 font-bold border border-line-200 cursor-default focus:outline-none'
                    : disabled
                    ? 'bg-sunken text-ink-500 border border-line-200 cursor-not-allowed'
                    : hasError
                    ? 'bg-surface text-ink-900 border border-danger focus:border-danger focus:outline-danger'
                    : 'bg-surface text-ink-900 border border-line-400 focus:border-teal-700 focus:outline-teal-700 focus:outline-2 focus:outline-offset-2',
            ]"
            @blur="emit('blur', $event)"
        />
    </div>
</template>
