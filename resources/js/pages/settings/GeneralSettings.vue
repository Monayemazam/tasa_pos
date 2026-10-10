<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import TasaShell from '@/components/tasa/TasaShell.vue';
import PageHeader from '@/components/tasa/PageHeader.vue';
import AppButton from '@/components/tasa/AppButton.vue';
import AppIcon from '@/components/tasa/AppIcon.vue';
import FormField from '@/components/tasa/FormField.vue';
import ConfirmDialog from '@/components/tasa/ConfirmDialog.vue';

interface GeneralSettingsData {
    shop_name: string;
    address: string;
    phone: string;
    logo: string;
    currency_symbol: string;
    allow_negative_stock: boolean;
    cost_price_includes_labour: boolean;
    allow_price_edit: boolean;
    show_success_rate: boolean;
}

interface ProductCategoryItem {
    id: number;
    name: string;
    products_count: number;
}

interface ExpenseCategoryItem {
    id: number;
    name: string;
    pl_group: string;
    is_system: boolean;
    is_active: boolean;
}

const props = defineProps<{
    settings: GeneralSettingsData;
    categories: ProductCategoryItem[];
    expenseCategories: ExpenseCategoryItem[];
}>();

// Settings Form
const form = useForm({
    shop_name: props.settings.shop_name,
    address: props.settings.address,
    phone: props.settings.phone,
    currency_symbol: props.settings.currency_symbol,
    allow_negative_stock: props.settings.allow_negative_stock,
    cost_price_includes_labour: props.settings.cost_price_includes_labour,
    allow_price_edit: props.settings.allow_price_edit,
    show_success_rate: props.settings.show_success_rate,
});

const submitSettings = () => {
    form.post('/settings/general', {
        preserveScroll: true,
    });
};

// Add Category Form
const newCategoryForm = useForm({
    name: '',
});

const submitNewCategory = () => {
    newCategoryForm.post('/settings/categories', {
        preserveScroll: true,
        onSuccess: () => {
            newCategoryForm.reset();
        },
    });
};

// Delete Category Dialog
const isDeleteCategoryOpen = ref(false);
const deletingCategory = ref<ProductCategoryItem | null>(null);

const openDeleteCategory = (cat: ProductCategoryItem) => {
    deletingCategory.value = cat;
    isDeleteCategoryOpen.value = true;
};

const confirmDeleteCategory = () => {
    if (!deletingCategory.value) return;
    router.delete(`/settings/categories/${deletingCategory.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            isDeleteCategoryOpen.value = false;
            deletingCategory.value = null;
        },
    });
};
</script>

<template>
    <Head title="General Settings & Categories — Tasa POS" />

    <TasaShell>
        <div class="space-y-6 max-w-4xl">
            <!-- Header -->
            <PageHeader
                title="General & Operational Settings"
                subtitle="Configure store information, business switches, and product categories."
            />

            <!-- Form -->
            <form class="space-y-6" @submit.prevent="submitSettings">
                <!-- Shop Details Section -->
                <div class="bg-surface border border-line-200 p-6 space-y-4">
                    <h2 class="text-sm font-bold text-ink-900 border-b border-line-200 pb-2">
                        Shop Information
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormField label="Shop Name" required :error="form.errors.shop_name">
                            <input
                                v-model="form.shop_name"
                                type="text"
                                required
                                class="w-full h-[40px] px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-teal-700"
                            />
                        </FormField>

                        <FormField label="Contact Phone" :error="form.errors.phone">
                            <input
                                v-model="form.phone"
                                type="text"
                                class="w-full h-[40px] px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-teal-700 font-mono"
                                placeholder="01XXXXXXXXX"
                            />
                        </FormField>

                        <FormField label="Currency Symbol" required :error="form.errors.currency_symbol">
                            <input
                                v-model="form.currency_symbol"
                                type="text"
                                required
                                class="w-full h-[40px] px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-teal-700 font-bold"
                            />
                        </FormField>

                        <FormField label="Store Address" class="md:col-span-2" :error="form.errors.address">
                            <textarea
                                v-model="form.address"
                                rows="2"
                                class="w-full p-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-teal-700 resize-none"
                                placeholder="Street, Market, City"
                            />
                        </FormField>
                    </div>
                </div>

                <!-- Business Switches Section (Database Architecture §7.3 & PRD §12) -->
                <div class="bg-surface border border-line-200 p-6 space-y-4">
                    <h2 class="text-sm font-bold text-ink-900 border-b border-line-200 pb-2">
                        Operational Switches & Rules
                    </h2>

                    <div class="space-y-4">
                        <!-- Allow Negative Stock -->
                        <div class="flex items-start justify-between p-3 bg-page border border-line-200">
                            <div class="pr-4">
                                <div class="text-xs font-bold text-ink-900">Allow Negative Stock</div>
                                <div class="text-[11px] text-ink-500">
                                    When disabled (recommended), sales and consumptions are strictly blocked from reducing inventory below zero.
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                                <input
                                    v-model="form.allow_negative_stock"
                                    type="checkbox"
                                    class="w-5 h-5 text-teal-700 rounded-none border-line-300 focus:ring-teal-700"
                                />
                            </label>
                        </div>

                        <!-- Cost Price Includes Labour -->
                        <div class="flex items-start justify-between p-3 bg-page border border-line-200">
                            <div class="pr-4">
                                <div class="text-xs font-bold text-ink-900">Cost Price Includes Labour (PRD O-1)</div>
                                <div class="text-[11px] text-ink-500">
                                    When disabled (default), product cost captures materials only; worker wages are kept as a separate operational expense.
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                                <input
                                    v-model="form.cost_price_includes_labour"
                                    type="checkbox"
                                    class="w-5 h-5 text-teal-700 rounded-none border-line-300 focus:ring-teal-700"
                                />
                            </label>
                        </div>

                        <!-- Price Editing at Sale -->
                        <div class="flex items-start justify-between p-3 bg-page border border-line-200">
                            <div class="pr-4">
                                <div class="text-xs font-bold text-ink-900">Allow Price Editing at Sale</div>
                                <div class="text-[11px] text-ink-500">
                                    Enables authorized sales staff to adjust unit selling prices dynamically during the POS checkout flow.
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                                <input
                                    v-model="form.allow_price_edit"
                                    type="checkbox"
                                    class="w-5 h-5 text-teal-700 rounded-none border-line-300 focus:ring-teal-700"
                                />
                            </label>
                        </div>

                        <!-- Pathao Courier Risk Hint -->
                        <div class="flex items-start justify-between p-3 bg-page border border-line-200">
                            <div class="pr-4">
                                <div class="text-xs font-bold text-ink-900">Show Courier Delivery Success Rate Hint</div>
                                <div class="text-[11px] text-ink-500">
                                    Displays customer delivery completion rate and return-risk badge when creating or viewing courier orders.
                                </div>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                                <input
                                    v-model="form.show_success_rate"
                                    type="checkbox"
                                    class="w-5 h-5 text-teal-700 rounded-none border-line-300 focus:ring-teal-700"
                                />
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <AppButton variant="primary" type="submit" :loading="form.processing">
                        Save General Settings
                    </AppButton>
                </div>
            </form>

            <!-- Product Categories Management Section -->
            <div class="bg-surface border border-line-200 p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-line-200 pb-2">
                    <h2 class="text-sm font-bold text-ink-900">
                        Product Categories
                    </h2>
                    <span class="text-xs text-ink-500">{{ categories.length }} category item(s)</span>
                </div>

                <!-- Add Category Form -->
                <form class="flex items-center space-x-2" @submit.prevent="submitNewCategory">
                    <div class="flex-1 max-w-sm">
                        <input
                            v-model="newCategoryForm.name"
                            type="text"
                            required
                            maxlength="100"
                            placeholder="New category name (e.g. Wallets, Belts, Bags)"
                            class="w-full h-[40px] px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-teal-700"
                        />
                    </div>
                    <AppButton variant="secondary" type="submit" :loading="newCategoryForm.processing">
                        Add Category
                    </AppButton>
                </form>

                <!-- Categories Table -->
                <div class="border border-line-200 overflow-hidden">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-page border-b border-line-200 text-ink-600 uppercase font-semibold">
                                <th class="py-2.5 px-3">Category Name</th>
                                <th class="py-2.5 px-3 text-center">Assigned Products</th>
                                <th class="py-2.5 px-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-200">
                            <tr v-for="cat in categories" :key="cat.id" class="hover:bg-teal-50">
                                <td class="py-2.5 px-3 font-semibold text-ink-900">{{ cat.name }}</td>
                                <td class="py-2.5 px-3 text-center font-mono">{{ cat.products_count }}</td>
                                <td class="py-2.5 px-3 text-right">
                                    <button
                                        type="button"
                                        :disabled="cat.products_count > 0"
                                        class="text-xs text-danger hover:underline disabled:opacity-30 disabled:no-underline font-semibold"
                                        :title="cat.products_count > 0 ? 'Cannot delete category with products' : 'Delete category'"
                                        @click="openDeleteCategory(cat)"
                                    >
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="categories.length === 0">
                                <td colspan="3" class="p-4 text-center text-ink-500">
                                    No categories defined yet. Add the first category above.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- DELETE CATEGORY DIALOG -->
        <ConfirmDialog
            :open="isDeleteCategoryOpen"
            title="Delete Product Category"
            :description="`Are you sure you want to delete category '${deletingCategory?.name}'?`"
            confirm-label="Delete Category"
            cancel-label="Cancel"
            :danger="true"
            @confirm="confirmDeleteCategory"
            @cancel="isDeleteCategoryOpen = false"
        />
    </TasaShell>
</template>
