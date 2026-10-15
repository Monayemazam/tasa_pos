<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const page = usePage();
const shop = computed(() => (page.props as any).shop || {});
const name = computed(() => shop.value.name || page.props.name || 'Tasa');
const logo = computed(() => shop.value.logo || null);
</script>

<template>
    <div v-if="logo" class="flex items-center space-x-2">
        <img :src="logo" :alt="name" class="h-8 w-auto max-w-[140px] object-contain" />
    </div>
    <template v-else>
        <div
            class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
        >
            <AppLogoIcon class="size-5 fill-current text-white dark:text-black" />
        </div>
        <div class="ml-1 grid flex-1 text-left text-sm">
            <span class="mb-0.5 truncate leading-tight font-semibold">{{
                name
            }}</span>
        </div>
    </template>
</template>
