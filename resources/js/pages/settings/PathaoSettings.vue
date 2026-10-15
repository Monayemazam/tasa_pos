<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
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

interface ConnectionStatus {
    is_configured: boolean;
    status: 'connected' | 'expiring_soon' | 'token_needed' | 'not_configured';
    token_days_left: number;
    warn_expiry: boolean;
    base_url: string;
    sandbox: boolean;
    masked_client_id: string;
    has_token: boolean;
    last_synced_at?: string;
}

interface StoreItem {
    store_id: number;
    store_name: string;
    store_address: string;
}

interface CityItem {
    city_id: number;
    city_name: string;
}

const props = defineProps<{
    settings: PathaoSettingsData;
    connection?: ConnectionStatus;
    stores?: StoreItem[];
    cities?: CityItem[];
}>();

// Main Settings Form
const form = useForm({
    store_id: props.settings.store_id || (props.stores && props.stores.length > 0 ? String(props.stores[0].store_id) : '1'),
    sender_name: props.settings.sender_name || 'Tasa Leather Goods',
    sender_phone: props.settings.sender_phone || '',
    default_delivery_type: props.settings.default_delivery_type || '48',
    default_item_type: props.settings.default_item_type || '2',
    default_weight: props.settings.default_weight || '0.5',
    show_success_rate: props.settings.show_success_rate,
});

const submitPathao = () => {
    form.post('/settings/pathao', {
        preserveScroll: true,
    });
};

// ==========================================
// TEST CONNECTION STATE
// ==========================================
const testingConnection = ref(false);
const testResult = ref<{
    tested: boolean;
    success: boolean;
    message: string;
    stores_count?: number;
} | null>(null);

const runTestConnection = async () => {
    testingConnection.value = true;
    testResult.value = null;

    try {
        const response = await fetch('/settings/pathao/test-connection', {
            headers: {
                Accept: 'application/json',
            },
        });
        const data = await response.json();
        testResult.value = {
            tested: true,
            success: data.success,
            message: data.message || (data.success ? 'Connected successfully.' : 'Connection failed.'),
            stores_count: data.stores_count,
        };
    } catch (err: any) {
        testResult.value = {
            tested: true,
            success: false,
            message: err?.message || 'Network error communicating with server test endpoint.',
        };
    } finally {
        testingConnection.value = false;
    }
};

// ==========================================
// CREATE STORE MODAL & CASCADING LOCATIONS
// ==========================================
const isCreateStoreModalOpen = ref(false);
const storeForm = useForm({
    name: '',
    contact_name: '',
    contact_number: '',
    address: '',
    city_id: '',
    zone_id: '',
    area_id: '',
});

const storeZones = ref<Array<{ zone_id: number; zone_name: string }>>([]);
const storeAreas = ref<Array<{ area_id: number; area_name: string }>>([]);
const loadingZones = ref(false);
const loadingAreas = ref(false);

const onCityChange = async () => {
    storeForm.zone_id = '';
    storeForm.area_id = '';
    storeZones.value = [];
    storeAreas.value = [];

    if (!storeForm.city_id) return;

    loadingZones.value = true;
    try {
        const res = await fetch(`/sales/courier/zones?city_id=${storeForm.city_id}`);
        storeZones.value = await res.json();
    } finally {
        loadingZones.value = false;
    }
};

const onZoneChange = async () => {
    storeForm.area_id = '';
    storeAreas.value = [];

    if (!storeForm.zone_id) return;

    loadingAreas.value = true;
    try {
        const res = await fetch(`/sales/courier/areas?zone_id=${storeForm.zone_id}`);
        storeAreas.value = await res.json();
    } finally {
        loadingAreas.value = false;
    }
};

const submitNewStore = () => {
    storeForm.post('/settings/pathao/stores', {
        preserveScroll: true,
        onSuccess: () => {
            isCreateStoreModalOpen.value = false;
            storeForm.reset();
        },
    });
};
</script>

<template>
    <Head title="Pathao Courier Settings — Tasa POS" />

        <div class="space-y-6 max-w-4xl mx-auto">
            <!-- Header -->
            <PageHeader
                title="Pathao Courier Integration"
                subtitle="Owner-only logistics boundary, OAuth credentials, store identifiers, and automated dispatch parameters."
            >
                <template #actions>
                    <AppButton
                        variant="secondary"
                        :loading="testingConnection"
                        icon="refresh"
                        @click="runTestConnection"
                    >
                        Test Connection
                    </AppButton>
                </template>
            </PageHeader>

            <!-- Test Connection Result Banner -->
            <div
                v-if="testResult"
                class="p-4 border text-xs flex items-start justify-between"
                :class="testResult.success ? 'bg-[#E6F2EF] border-[#B8DCD5] text-[#0B6E5E]' : 'bg-[#FBEAE9] border-[#F2BCB8] text-[#8E1B12]'"
            >
                <div class="flex items-center space-x-3">
                    <AppIcon :name="testResult.success ? 'check' : 'alert-triangle'" :size="20" />
                    <div>
                        <span class="font-bold">{{ testResult.success ? 'Connection Verified' : 'Connection Problem' }}:</span>
                        <span class="ml-1">{{ testResult.message }}</span>
                        <span v-if="testResult.stores_count !== undefined" class="block text-[11px] mt-0.5 opacity-80">
                            Available Stores: {{ testResult.stores_count }}
                        </span>
                    </div>
                </div>
                <button
                    type="button"
                    class="text-xs font-bold underline ml-4 hover:opacity-75"
                    @click="testResult = null"
                >
                    Dismiss
                </button>
            </div>

            <!-- Token Expiry Alert Banner (Warn when under 7 days or expired) -->
            <div
                v-if="connection && connection.warn_expiry"
                class="p-4 bg-[#FEFCE8] border border-[#FEF08A] text-[#854D0E] text-xs flex items-center space-x-3"
            >
                <AppIcon name="alert-triangle" :size="20" class="text-[#A37A00] flex-shrink-0" />
                <div>
                    <span class="font-bold">Token Expiry Notice:</span>
                    <span class="ml-1">
                        Pathao OAuth access token has {{ connection.token_days_left }} day(s) remaining.
                        Automatic refresh runs before expiration. Ensure environment credentials remain active.
                    </span>
                </div>
            </div>

            <!-- API Status & Connection Overview Card -->
            <div class="bg-surface border border-line-200 p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-line-200 pb-3">
                    <div class="flex items-center space-x-3">
                        <div class="p-2.5 bg-page border border-line-300">
                            <AppIcon name="courier" :size="24" class="text-teal-700" />
                        </div>
                        <div>
                            <div class="text-sm font-bold text-ink-900">Pathao Merchant Gateway</div>
                            <div class="text-[11px] text-ink-500">
                                Environment-based authentication per PRD §7.2. Secrets never leave server-side memory.
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center space-x-2">
                        <StatusBadge
                            v-if="connection?.status === 'connected'"
                            label="API Connected"
                            tone="success"
                        />
                        <StatusBadge
                            v-else-if="connection?.status === 'expiring_soon'"
                            label="Expiring Soon"
                            tone="warning"
                        />
                        <StatusBadge
                            v-else-if="connection?.status === 'token_needed'"
                            label="Token Needed"
                            tone="warning"
                        />
                        <StatusBadge
                            v-else
                            label="Not Configured"
                            tone="neutral"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs pt-1">
                    <div class="p-3 bg-page border border-line-200">
                        <div class="text-[11px] font-bold text-ink-500 uppercase">Client ID (Masked)</div>
                        <div class="font-mono font-bold text-ink-900 mt-1">
                            {{ connection?.masked_client_id || 'Not configured' }}
                        </div>
                        <div class="text-[10px] text-ink-400 mt-0.5">Stored in .env as PATHAO_CLIENT_ID</div>
                    </div>

                    <div class="p-3 bg-page border border-line-200">
                        <div class="text-[11px] font-bold text-ink-500 uppercase">Token Days Left</div>
                        <div class="font-mono font-bold text-ink-900 mt-1 flex items-center space-x-1">
                            <span>{{ connection?.token_days_left ?? 0 }} Days</span>
                            <span
                                v-if="connection && connection.token_days_left < 7"
                                class="text-[10px] px-1 bg-[#FEF3C7] text-[#92400E] font-bold"
                            >
                                Warning (&lt;7d)
                            </span>
                        </div>
                        <div class="text-[10px] text-ink-400 mt-0.5">30-day token lifetime</div>
                    </div>

                    <div class="p-3 bg-page border border-line-200">
                        <div class="text-[11px] font-bold text-ink-500 uppercase">Gateway Environment</div>
                        <div class="font-mono font-bold text-ink-900 mt-1">
                            {{ connection?.sandbox ? 'Sandbox (Test)' : 'Production Hermès' }}
                        </div>
                        <div class="text-[10px] text-ink-400 mt-0.5">api-hermes.pathao.com</div>
                    </div>
                </div>
            </div>

            <!-- Form -->
            <form class="space-y-6" @submit.prevent="submitPathao">
                <div class="bg-surface border border-line-200 p-6 space-y-4">
                    <div class="flex items-center justify-between border-b border-line-200 pb-2">
                        <h2 class="text-sm font-bold text-ink-900">
                            Dispatch Defaults & Pickup Parameters
                        </h2>

                        <!-- Create Store Button -->
                        <button
                            type="button"
                            class="text-xs font-bold text-teal-700 hover:underline flex items-center space-x-1"
                            @click="isCreateStoreModalOpen = true"
                        >
                            <AppIcon name="plus" :size="14" />
                            <span>Add New Store to Pathao</span>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Store Selector -->
                        <FormField
                            label="Default Pickup Store"
                            :error="form.errors.store_id"
                            help="Select the Pathao store from which parcels are collected."
                        >
                            <div v-if="stores && stores.length > 0">
                                <select
                                    v-model="form.store_id"
                                    class="w-full h-[40px] px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-teal-700 font-mono"
                                >
                                    <option
                                        v-for="s in stores"
                                        :key="s.store_id"
                                        :value="String(s.store_id)"
                                    >
                                        {{ s.store_name }} (ID: {{ s.store_id }})
                                    </option>
                                </select>
                            </div>
                            <div v-else>
                                <input
                                    v-model="form.store_id"
                                    type="text"
                                    class="w-full h-[40px] px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-teal-700 font-mono"
                                    placeholder="Enter numeric Store ID"
                                />
                            </div>
                        </FormField>

                        <!-- Sender Name -->
                        <FormField label="Default Sender Name" :error="form.errors.sender_name">
                            <input
                                v-model="form.sender_name"
                                type="text"
                                class="w-full h-[40px] px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-teal-700"
                                placeholder="Tasa Leather Goods"
                            />
                        </FormField>

                        <!-- Sender Phone -->
                        <FormField label="Default Sender Phone" :error="form.errors.sender_phone">
                            <PhoneInput v-model="form.sender_phone" placeholder="01XXXXXXXXX" />
                        </FormField>

                        <!-- Default Package Weight -->
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

                        <!-- Default Delivery Speed -->
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

                        <!-- Default Item Type -->
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

                    <!-- Risk Indicator Toggle -->
                    <div class="pt-3 border-t border-line-200">
                        <label class="flex items-center space-x-2 text-xs font-semibold text-ink-900 cursor-pointer">
                            <input
                                v-model="form.show_success_rate"
                                type="checkbox"
                                class="w-4 h-4 text-teal-700 rounded-none border-line-300 focus:ring-teal-700"
                            />
                            <span>Show Customer Delivery Success Rate & Return Risk Hint</span>
                        </label>
                        <p class="text-[11px] text-ink-500 mt-0.5">
                            Displays a green/yellow/red risk indicator on POS customer lookup based on Pathao delivery completion history.
                        </p>
                    </div>
                </div>

                <div class="flex justify-end">
                    <AppButton variant="primary" type="submit" :loading="form.processing">
                        Save Pathao Settings
                    </AppButton>
                </div>
            </form>

            <!-- Create Store Modal -->
            <div
                v-if="isCreateStoreModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            >
                <div class="bg-surface border border-line-300 max-w-lg w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between border-b border-line-200 pb-3">
                        <h3 class="text-sm font-bold text-ink-900">Add New Store to Pathao</h3>
                        <button
                            type="button"
                            class="text-ink-400 hover:text-ink-900"
                            @click="isCreateStoreModalOpen = false"
                        >
                            <AppIcon name="x" :size="18" />
                        </button>
                    </div>

                    <form class="space-y-4" @submit.prevent="submitNewStore">
                        <FormField label="Store Name" required :error="storeForm.errors.name">
                            <input
                                v-model="storeForm.name"
                                type="text"
                                required
                                class="w-full h-9 px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-none"
                                placeholder="e.g. Tasa Dhanmondi Outlet"
                            />
                        </FormField>

                        <div class="grid grid-cols-2 gap-3">
                            <FormField label="Contact Name" required :error="storeForm.errors.contact_name">
                                <input
                                    v-model="storeForm.contact_name"
                                    type="text"
                                    required
                                    class="w-full h-9 px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-none"
                                    placeholder="Manager Name"
                                />
                            </FormField>

                            <FormField label="Contact Phone" required :error="storeForm.errors.contact_number">
                                <PhoneInput v-model="storeForm.contact_number" placeholder="01XXXXXXXXX" />
                            </FormField>
                        </div>

                        <FormField label="Full Street Address" required :error="storeForm.errors.address">
                            <input
                                v-model="storeForm.address"
                                type="text"
                                required
                                class="w-full h-9 px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-none"
                                placeholder="House #, Road #, Building"
                            />
                        </FormField>

                        <!-- Cascading City, Zone, Area -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            <FormField label="City" required :error="storeForm.errors.city_id">
                                <select
                                    v-model="storeForm.city_id"
                                    required
                                    class="w-full h-9 px-2 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-none"
                                    @change="onCityChange"
                                >
                                    <option value="">Select City</option>
                                    <option
                                        v-for="c in cities"
                                        :key="c.city_id"
                                        :value="c.city_id"
                                    >
                                        {{ c.city_name }}
                                    </option>
                                </select>
                            </FormField>

                            <FormField label="Zone" required :error="storeForm.errors.zone_id">
                                <select
                                    v-model="storeForm.zone_id"
                                    required
                                    :disabled="!storeForm.city_id || loadingZones"
                                    class="w-full h-9 px-2 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-none"
                                    @change="onZoneChange"
                                >
                                    <option value="">{{ loadingZones ? 'Loading...' : 'Select Zone' }}</option>
                                    <option
                                        v-for="z in storeZones"
                                        :key="z.zone_id"
                                        :value="z.zone_id"
                                    >
                                        {{ z.zone_name }}
                                    </option>
                                </select>
                            </FormField>

                            <FormField label="Area" required :error="storeForm.errors.area_id">
                                <select
                                    v-model="storeForm.area_id"
                                    required
                                    :disabled="!storeForm.zone_id || loadingAreas"
                                    class="w-full h-9 px-2 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-none"
                                >
                                    <option value="">{{ loadingAreas ? 'Loading...' : 'Select Area' }}</option>
                                    <option
                                        v-for="a in storeAreas"
                                        :key="a.area_id"
                                        :value="a.area_id"
                                    >
                                        {{ a.area_name }}
                                    </option>
                                </select>
                            </FormField>
                        </div>

                        <div class="flex items-center justify-end space-x-2 pt-2 border-t border-line-200">
                            <AppButton
                                variant="secondary"
                                type="button"
                                @click="isCreateStoreModalOpen = false"
                            >
                                Cancel
                            </AppButton>
                            <AppButton
                                variant="primary"
                                type="submit"
                                :loading="storeForm.processing"
                            >
                                Register Store
                            </AppButton>
                        </div>
                    </form>
                </div>
            </div>
    </div>
</template>
