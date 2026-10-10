<script setup lang="ts">
import { computed } from 'vue';
import AppIcon from './AppIcon.vue';

interface Props {
    modelValue: string | null | undefined;
    id?: string;
    placeholder?: string;
    disabled?: boolean;
    readonly?: boolean;
    hasError?: boolean;
    required?: boolean;
    ariaLabel?: string;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: '',
    placeholder: '01XXXXXXXXX',
    disabled: false,
    readonly: false,
    hasError: false,
});

const emit = defineEmits<{
    (e: 'update:modelValue', val: string): void;
    (e: 'blur', event: FocusEvent): void;
}>();

// Clean digits and limit to 11
const handleInput = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const raw = target.value.replace(/\D/g, '').slice(0, 11);
    emit('update:modelValue', raw);
};

// Check if valid Bangladeshi mobile number: 11 digits, starts with 01[3-9]
const isValidBD = computed(() => {
    const val = props.modelValue || '';
    return /^01[3-9]\d{8}$/.test(val);
});
</script>

<template>
    <div class="relative w-full">
        <!-- Phone Icon Prefix -->
        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
            <AppIcon name="phone" :size="16" class="text-ink-500" />
        </div>

        <input
            :id="id"
            :value="modelValue"
            type="tel"
            inputmode="numeric"
            maxlength="11"
            :placeholder="placeholder"
            :disabled="disabled"
            :readonly="readonly"
            :required="required"
            :aria-label="ariaLabel"
            :class="[
                'w-full pl-9 pr-9 tabular-nums rounded-none transition-colors duration-150',
                'h-[40px] md:h-[40px] text-base md:text-sm font-medium',
                disabled
                    ? 'bg-sunken text-ink-500 border border-line-200 cursor-not-allowed'
                    : hasError
                    ? 'bg-surface text-ink-900 border border-danger focus:border-danger focus:outline-danger'
                    : 'bg-surface text-ink-900 border border-line-400 focus:border-teal-700 focus:outline-teal-700 focus:outline-2 focus:outline-offset-2',
            ]"
            @input="handleInput"
            @blur="emit('blur', $event)"
        />

        <!-- Validation Status Indicator Tag -->
        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
            <span v-if="isValidBD" class="text-success inline-flex items-center" title="Valid Bangladeshi mobile number">
                <AppIcon name="check-circle" :size="16" />
            </span>
            <span v-else-if="modelValue && modelValue.length > 0 && !isValidBD" class="text-warning inline-flex items-center" title="Must be an 11-digit number starting with 01">
                <AppIcon name="warning" :size="16" />
            </span>
        </div>
    </div>
</template>
