<script setup lang="ts">
import { onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import TasaAuthScreen from '@/components/tasa/TasaAuthScreen.vue';

interface Props {
    status?: string;
    canResetPassword?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    status: '',
    canResetPassword: true,
});

const page = usePage();

onMounted(() => {
    // If user is already authenticated, immediately transition to the POS dashboard
    if (page.props.auth?.user) {
        router.visit('/dashboard');
    }
});
</script>

<template>
    <TasaAuthScreen
        :status="props.status"
        :can-reset-password="props.canResetPassword"
    />
</template>
