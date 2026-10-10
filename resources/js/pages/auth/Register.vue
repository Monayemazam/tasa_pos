<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppButton from '@/components/tasa/AppButton.vue';

defineOptions({
    layout: {
        title: 'Registration Disabled',
        description: 'Public registration is disabled for Tasa POS.',
    },
});
</script>

<template>
    <Head title="Registration Disabled" />

    <div class="flex flex-col items-center justify-center space-y-4 text-center py-6">
        <div class="p-3 bg-teal-50 border border-teal-300 text-teal-800 text-xs">
            <span class="font-bold">Public self-registration is disabled.</span>
            User accounts are authorized and created exclusively by the Owner in System Administration.
        </div>

        <Link href="/login">
            <AppButton variant="primary" size="sm">
                Return to Login
            </AppButton>
        </Link>
    </div>
</template>
