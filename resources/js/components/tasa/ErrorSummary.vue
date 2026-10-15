<script setup lang="ts">
import { computed } from 'vue';
import AppIcon from './AppIcon.vue';

interface Props {
    errors?: Record<string, string>;
    title?: string;
}

const props = withDefaults(defineProps<Props>(), {
    errors: () => ({}),
    title: 'There is a problem with your submission',
});

const errorList = computed(() => {
    return Object.entries(props.errors || {}).map(([key, msg]) => ({
        key,
        message: msg,
    }));
});

const scrollToField = (fieldKey: string, e: Event) => {
    e.preventDefault();
    const el = document.getElementById(fieldKey) || document.querySelector(`[name="${fieldKey}"]`);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        (el as HTMLElement).focus?.();
    }
};
</script>

<template>
    <div
        v-if="errorList.length > 0"
        class="bg-[#FEF2F2] border-2 border-danger p-4 mb-5 rounded-none"
        role="alert"
        aria-labelledby="error-summary-title"
        tabindex="-1"
    >
        <div class="flex items-start space-x-3">
            <div class="mt-0.5 text-danger flex-shrink-0">
                <AppIcon name="danger" :size="20" />
            </div>
            <div class="flex-1">
                <h2 id="error-summary-title" class="text-sm font-bold text-[#991B1B]">
                    {{ title }} ({{ errorList.length }} {{ errorList.length === 1 ? 'error' : 'errors' }})
                </h2>
                <ul class="mt-2 space-y-1 text-xs text-danger-text list-disc list-inside">
                    <li v-for="err in errorList" :key="err.key">
                        <a
                            :href="`#${err.key}`"
                            class="underline hover:text-[#991B1B] font-medium focus:outline-teal-700"
                            @click="scrollToField(err.key, $event)"
                        >
                            {{ err.message }}
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
