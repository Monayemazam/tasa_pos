<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import TasaShell from '@/components/tasa/TasaShell.vue';
import PageHeader from '@/components/tasa/PageHeader.vue';
import AppButton from '@/components/tasa/AppButton.vue';
import AppIcon from '@/components/tasa/AppIcon.vue';
import FormField from '@/components/tasa/FormField.vue';
import PhoneInput from '@/components/tasa/PhoneInput.vue';
import StatusBadge from '@/components/tasa/StatusBadge.vue';

interface PathaoSettingsData {
    store_id: string;
    sender_name: string;
    sender_phone: string;
    default_delivery_type: string;
    default_item_type: string;
    default_weight: string;
    show_success_rate: boolean;
    is_configured: boolean;
}

const props = defineProps<{
    settings: PathaoSettingsData;
}>();

const form = useForm({
    store_id: props.settings.store_id,
    sender_name: props.settings.sender_name,
    sender_phone: props.settings.sender_phone,
    default_delivery_type: props.settings.default_delivery_type,
    default_item_type: props.settings.default_item_type,
    default_weight: props.settings.default_weight,
    show_success_rate: props.settings.show_success_rate,
});

const submitPathao = () => {
    form.post('/settings/pathao', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Pathao Courier Settings — Tasa POS" />

    <TasaShell>
        <div class="space-y-6 max-w-4xl">
            <!-- Header -->
            <PageHeader
                title="Pathao Courier Integration"
                subtitle="Configure store identifier, dispatch defaults, and customer risk indicator."
            />

            <!-- API Status Banner -->
            <div class="bg-surface border border-line-200 p-4 flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="p-2 bg-page border border-line-300">
                        <AppIcon name="courier" :size="24" class="text-teal-700" />
                    </div>
                    <div>
                        <div class="text-xs font-bold text-ink-900">Pathao Merchant API Credentials</div>
                        <div class="text-[11px] text-ink-500">
                            Kept securely in server environment (.env) per PRD §7.2.
                        </div>
                    </div>
                </div>

                <StatusBadge
                    :label="settings.is_configured ? 'API Connected' : 'Config Incomplete'"
                    :tone="settings.is_configured ? 'success' : 'warning'"
                />
            </div>

            <!-- Form -->
            <form class="space-y-6" @submit.prevent="submitPathao">
                <div class="bg-surface border border-line-200 p-6 space-y-4">
                    <h2 class="text-sm font-bold text-ink-900 border-b border-line-200 pb-2">
                        Store Dispatch Details
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormField label="Pathao Store ID" :error="form.errors.store_id">
                            <input
                                v-model="form.store_id"
                                type="text"
                                class="w-full h-[40px] px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-teal-700 font-mono"
                                placeholder="Store ID from Pathao merchant panel"
                            />
                        </FormField>

                        <FormField label="Default Sender Name" :error="form.errors.sender_name">
                            <input
                                v-model="form.sender_name"
                                type="text"
                                class="w-full h-[40px] px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-teal-700"
                                placeholder="Tasa Leather Goods"
                            />
                        </FormField>

                        <FormField label="Default Sender Phone" :error="form.errors.sender_phone">
                            <PhoneInput v-model="form.sender_phone" placeholder="01XXXXXXXXX" />
                        </FormField>

                        <FormField label="Default Package Weight (kg)" required :error="form.errors.default_weight">
                            <input
                                v-model="form.default_weight"
                                type="number"
                                step="0.1"
                                min="0.1"
                                max="20"
                                required
                                class="w-full h-[40px] px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-teal-700 font-mono"
                            />
                        </FormField>

                        <FormField label="Default Delivery Speed" required :error="form.errors.default_delivery_type">
                            <select
                                v-model="form.default_delivery_type"
                                required
                                class="w-full h-[40px] px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-teal-700"
                            >
                                <option value="48">Normal Delivery (48 Hours Standard)</option>
                                <option value="12">On-Demand / Express (12 Hours)</option>
                            </select>
                        </FormField>

                        <FormField label="Default Item Type" required :error="form.errors.default_item_type">
                            <select
                                v-model="form.default_item_type"
                                required
                                class="w-full h-[40px] px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-teal-700"
                            >
                                <option value="2">Parcel (Leather Goods / Accessories)</option>
                                <option value="1">Document</option>
                            </select>
                        </FormField>
                    </div>

                    <div class="pt-2 border-t border-line-200">
                        <label class="flex items-center space-x-2 text-xs font-semibold text-ink-900 cursor-pointer">
                            <input
                                v-model="form.show_success_rate"
                                type="checkbox"
                                class="w-4 h-4 text-teal-700 rounded-none border-line-300 focus:ring-teal-700"
                            />
                            <span>Show Customer Delivery Success Rate & Return Risk Hint</span>
                        </label>
                        <p class="text-[11px] text-ink-500 mt-0.5">
                            Displays a green/yellow/red risk indicator based on the customer's Pathao completion history.
                        </p>
                    </div>
                </div>

                <div class="flex justify-end">
                    <AppButton variant="primary" type="submit" :loading="form.processing">
                        Save Pathao Settings
                    </AppButton>
                </div>
            </form>
        </div>
    </TasaShell>
</template>
