<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppButton from '@/components/tasa/AppButton.vue';
import AppIcon from '@/components/tasa/AppIcon.vue';

interface Props {
    status?: number;
    message?: string;
}

const props = withDefaults(defineProps<Props>(), {
    status: 403,
    message: '',
});

const title = computed(() => {
    return {
        403: 'Access Restricted',
        404: 'Page Not Found',
        500: 'Server Error',
        503: 'Service Unavailable',
    }[props.status] || 'Error';
});

const defaultDescription = computed(() => {
    return {
        403: 'You do not have permission to view this section or perform this action. If you require access, please contact the shop Owner to update your role permissions in Settings > Roles & Permissions.',
        404: 'The page or resource you are looking for does not exist or has been moved.',
        500: 'An unexpected internal error occurred on our server. Please try again later or contact support.',
        503: 'Tasa POS is currently undergoing maintenance. Please check back shortly.',
    }[props.status] || 'An unexpected error occurred.';
});
</script>

<template>
    <Head :title="`${status} - ${title}`" />

    <div class="max-w-2xl mx-auto py-12 px-4 sm:px-6">
        <div class="bg-surface border border-line-200 p-8 text-center shadow-sm">
            <!-- Icon badge -->
            <div class="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center" :class="status === 403 ? 'bg-danger-bg text-danger border border-danger/30' : 'bg-amber-50 text-amber-600 border border-amber-200'">
                <AppIcon v-if="status === 403" name="security" :size="32" />
                <AppIcon v-else name="close" :size="32" />
            </div>

            <!-- Status Code & Title -->
            <div class="inline-block px-2.5 py-0.5 mb-2 text-xs font-mono font-bold uppercase tracking-wider rounded" :class="status === 403 ? 'bg-danger-bg text-danger' : 'bg-line-100 text-ink-700'">
                HTTP {{ status }}
            </div>
            <h1 class="text-2xl font-bold text-ink-900 tracking-tight mb-2">
                {{ title }}
            </h1>

            <!-- Explanation message -->
            <p class="text-sm text-ink-600 max-w-md mx-auto mb-6">
                {{ message || defaultDescription }}
            </p>

            <!-- Role & RBAC helper info for 403 -->
            <div v-if="status === 403" class="bg-sunken border border-line-200 p-4 text-left text-xs text-ink-700 max-w-lg mx-auto mb-6 space-y-1.5">
                <div class="font-bold text-ink-900 flex items-center gap-1.5">
                    <AppIcon name="lock" :size="14" class="text-ink-600" />
                    <span>Role-Based Access Control</span>
                </div>
                <p class="text-ink-600">
                    Your current account role does not have the required module permission configured in the database.
                    The Owner account can grant access at any time through
                    <span class="font-semibold text-teal-800">Settings &gt; Roles &amp; Permissions</span>.
                </p>
            </div>

            <!-- Action buttons -->
            <div class="flex items-center justify-center gap-3">
                <Link href="/dashboard">
                    <AppButton variant="primary" icon="dashboard">
                        Return to Dashboard
                    </AppButton>
                </Link>
                <button
                    type="button"
                    class="px-4 py-2 border border-line-300 text-ink-700 text-xs font-semibold hover:bg-page transition-colors"
                    @click="window.history.length > 1 ? window.history.back() : window.location.href = '/dashboard'"
                >
                    Go Back
                </button>
            </div>
        </div>
    </div>
</template>
