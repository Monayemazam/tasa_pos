<script setup lang="ts">
import AppIcon from './AppIcon.vue';

interface Props {
    id?: string;
    label?: string;
    required?: boolean;
    error?: string;
    helper?: string;
}

defineProps<Props>();
</script>

<template>
    <div class="flex flex-col space-y-1.5 w-full">
        <!-- Label Row -->
        <label
            v-if="label"
            :for="id"
            class="text-sm font-semibold text-ink-700 flex items-center justify-between"
        >
            <span>
                {{ label }}
                <span v-if="required" class="text-danger ml-0.5" aria-hidden="true">*</span>
                <span v-if="required" class="sr-only">(Required)</span>
            </span>
            <slot name="label-extra" />
        </label>

        <!-- Input Slot -->
        <div class="relative w-full">
            <slot :id="id" :has-error="Boolean(error)" />
        </div>

        <!-- Helper Text -->
        <p v-if="helper && !error" :id="id ? `${id}-helper` : undefined" class="text-xs text-ink-600">
            {{ helper }}
        </p>

        <!-- Error Message (Colour + Icon + Text) -->
        <div
            v-if="error"
            :id="id ? `${id}-error` : undefined"
            class="flex items-center space-x-1.5 text-xs text-danger-text"
            role="alert"
        >
            <AppIcon name="danger" :size="14" class="text-danger flex-shrink-0" />
            <span>{{ error }}</span>
        </div>
    </div>
</template>
