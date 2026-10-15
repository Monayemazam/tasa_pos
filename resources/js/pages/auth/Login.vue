<script setup lang="ts">
import TasaAuthScreen from '@/components/tasa/TasaAuthScreen.vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <TasaAuthScreen
        :status="status"
        :can-reset-password="canResetPassword"
    />
</template>
