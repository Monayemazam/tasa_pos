<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/tasa/PageHeader.vue';
import EmptyState from '@/components/tasa/EmptyState.vue';
import { type IconConcept } from '@/lib/icons';

interface Props {
    title: string;
    moduleName: string;
    targetPhase: string;
    icon?: IconConcept;
}

withDefaults(defineProps<Props>(), {
    icon: 'box',
});
</script>

<template>
    <AppLayout>
        <PageHeader
            :title="title"
            :subtitle="`Module: ${moduleName} · Scheduled for implementation in ${targetPhase}`"
        />

        <div class="bg-surface border border-line-200 p-8 text-center max-w-xl mx-auto my-12">
            <EmptyState
                :icon="icon"
                :title="`${title} (Shell Placeholder)`"
                :description="`This module screen has its navigation route and layout shell configured. The business logic, database migrations, and operational workflows are scheduled for ${targetPhase} per the Tasa POS roadmap.`"
            />
        </div>
    </AppLayout>
</template>
