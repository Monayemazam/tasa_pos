<script setup lang="ts">
import { computed } from 'vue';
import { type IconConcept, getIconClass } from '@/lib/icons';

interface Props {
    name: IconConcept;
    size?: 16 | 20 | 24 | 40 | number;
    class?: string;
    ariaLabel?: string;
}

const props = withDefaults(defineProps<Props>(), {
    size: 20,
    class: '',
});

const iconClass = computed(() => {
    return getIconClass(props.name);
});

const sizeStyle = computed(() => {
    return {
        fontSize: `${props.size}px`,
        width: `${props.size}px`,
        height: `${props.size}px`,
        display: 'inline-flex',
        alignItems: 'center',
        justifyContent: 'center',
    };
});
</script>

<template>
    <i
        :class="[iconClass, props.class]"
        :style="sizeStyle"
        :aria-label="ariaLabel"
        :aria-hidden="!ariaLabel"
        role="img"
    />
</template>
