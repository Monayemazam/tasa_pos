<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import TasaShell from '@/components/tasa/TasaShell.vue';
import PageHeader from '@/components/tasa/PageHeader.vue';
import AppButton from '@/components/tasa/AppButton.vue';
import AppIcon from '@/components/tasa/AppIcon.vue';
import FormField from '@/components/tasa/FormField.vue';

interface InvoiceSettingsData {
    prefix: string;
    paper_size: string;
    footer_note: string;
    terms: string;
    custom_template: string | null;
}

const props = defineProps<{
    settings: InvoiceSettingsData;
}>();

const form = useForm({
    prefix: props.settings.prefix,
    paper_size: props.settings.paper_size,
    footer_note: props.settings.footer_note,
    terms: props.settings.terms,
    custom_template: props.settings.custom_template || '',
});

const submitInvoiceSettings = () => {
    form.post('/settings/invoice', {
        preserveScroll: true,
    });
};

// Available Placeholders
const availableTokens = [
    { token: '{{invoice_no}}', desc: 'Sequential Invoice Number (e.g. TASA-2610-0001)' },
    { token: '{{sale_date}}', desc: 'Date of sale transaction' },
    { token: '{{shop_name}}', desc: 'Shop Name from General Settings' },
    { token: '{{shop_address}}', desc: 'Store Address & Contact' },
    { token: '{{customer_name}}', desc: 'Customer Full Name' },
    { token: '{{customer_phone}}', desc: 'Customer Phone (01XXXXXXXXX)' },
    { token: '{{customer_address}}', desc: 'Delivery Destination Address' },
    { token: '{{items_table}}', desc: 'Auto-rendered item rows (Product, Qty, Rate, Total)' },
    { token: '{{subtotal}}', desc: 'Subtotal sum before deductions' },
    { token: '{{discount}}', desc: 'Discount applied' },
    { token: '{{delivery_charge}}', desc: 'Customer delivery charge' },
    { token: '{{grand_total}}', desc: 'Net payable invoice amount' },
    { token: '{{paid_total}}', desc: 'Sum of payments collected' },
    { token: '{{balance_due}}', desc: 'Outstanding receivable balance' },
    { token: '{{footer_note}}', desc: 'Invoice footer message' },
    { token: '{{terms}}', desc: 'Terms & Return conditions' },
];

const insertToken = (token: string) => {
    form.custom_template += ` ${token} `;
};

// Simulated Safe Preview
const previewHtml = computed(() => {
    if (!form.custom_template.trim()) {
        return `
            <div style="font-family: 'Hanken Grotesk', sans-serif; padding: 20px; border: 1px solid #D4DDD9; background: #fff;">
                <div style="display: flex; justify-content: space-between; border-bottom: 2px solid #0B6E5E; padding-bottom: 10px;">
                    <div>
                        <h2 style="margin: 0; color: #0B6E5E; font-size: 20px; font-weight: 800;">TASA LEATHER GOODS</h2>
                        <p style="margin: 4px 0 0; font-size: 11px; color: #52605C;">Dhaka, Bangladesh · 01700000000</p>
                    </div>
                    <div style="text-align: right;">
                        <h3 style="margin: 0; font-size: 16px; color: #0F1B1A;">INVOICE</h3>
                        <p style="margin: 4px 0 0; font-size: 11px; font-family: monospace; font-weight: bold;">${form.prefix}-2610-0001</p>
                    </div>
                </div>
                <div style="margin: 15px 0; font-size: 11px;">
                    <strong>Billed To:</strong> Rahim Ahmed (01812345678)<br />
                    House 12, Road 4, Dhanmondi, Dhaka
                </div>
                <table style="width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 15px;">
                    <thead>
                        <tr style="background: #EBEFEE; text-align: left;">
                            <th style="padding: 6px;">Item</th>
                            <th style="padding: 6px; text-align: center;">Qty</th>
                            <th style="padding: 6px; text-align: right;">Price</th>
                            <th style="padding: 6px; text-align: right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr style="border-bottom: 1px solid #EBEFEE;">
                            <td style="padding: 6px;">Classic Leather Bifold Wallet</td>
                            <td style="padding: 6px; text-align: center;">1</td>
                            <td style="padding: 6px; text-align: right;">৳1,250.00</td>
                            <td style="padding: 6px; text-align: right;">৳1,250.00</td>
                        </tr>
                    </tbody>
                </table>
                <div style="text-align: right; font-size: 11px; margin-bottom: 15px;">
                    <strong>Grand Total: ৳1,250.00</strong><br />
                    Paid: ৳1,250.00 · Due: ৳0.00
                </div>
                <div style="border-top: 1px solid #EBEFEE; padding-top: 8px; font-size: 10px; color: #52605C;">
                    <p style="margin: 0;">${form.footer_note}</p>
                    <p style="margin: 4px 0 0; font-style: italic;">${form.terms}</p>
                </div>
            </div>
        `;
    }

    let rendered = form.custom_template
        .replace(/{{invoice_no}}/g, `${form.prefix}-2610-0001`)
        .replace(/{{sale_date}}/g, '2026-10-11')
        .replace(/{{shop_name}}/g, 'Tasa Leather Goods')
        .replace(/{{shop_address}}/g, 'Dhaka, Bangladesh')
        .replace(/{{customer_name}}/g, 'Rahim Ahmed')
        .replace(/{{customer_phone}}/g, '01812345678')
        .replace(/{{customer_address}}/g, 'House 12, Road 4, Dhanmondi, Dhaka')
        .replace(/{{items_table}}/g, '<div style="background:#EBEFEE;padding:8px;text-align:center;">[Items Table: 1x Leather Wallet @ ৳1,250.00]</div>')
        .replace(/{{subtotal}}/g, '৳1,250.00')
        .replace(/{{discount}}/g, '৳0.00')
        .replace(/{{delivery_charge}}/g, '৳60.00')
        .replace(/{{grand_total}}/g, '৳1,310.00')
        .replace(/{{paid_total}}/g, '৳1,310.00')
        .replace(/{{balance_due}}/g, '৳0.00')
        .replace(/{{footer_note}}/g, form.footer_note)
        .replace(/{{terms}}/g, form.terms);

    return `<div style="padding:15px;background:#fff;border:1px solid #D4DDD9;">${rendered}</div>`;
});
</script>

<template>
    <Head title="Invoice Branding & Templates — Tasa POS" />

    <TasaShell>
        <div class="space-y-6 max-w-4xl">
            <!-- Header -->
            <PageHeader
                title="Invoice Branding & Templates"
                subtitle="Configure invoice numbering prefix, paper layouts, terms, and custom safe markup."
            />

            <!-- Form -->
            <form class="space-y-6" @submit.prevent="submitInvoiceSettings">
                <div class="bg-surface border border-line-200 p-6 space-y-4">
                    <h2 class="text-sm font-bold text-ink-900 border-b border-line-200 pb-2">
                        Invoice Format & Layout
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <FormField label="Invoice Prefix" required :error="form.errors.prefix">
                            <input
                                v-model="form.prefix"
                                type="text"
                                required
                                maxlength="10"
                                class="w-full h-[40px] px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-teal-700 font-mono uppercase font-bold"
                                placeholder="TASA"
                            />
                            <p class="text-[11px] text-ink-500 mt-1">
                                Sequences follow format: <span class="font-mono font-bold">{{ form.prefix }}-YYMM-0001</span>.
                            </p>
                        </FormField>

                        <FormField label="Paper Size / Print Layout" required :error="form.errors.paper_size">
                            <select
                                v-model="form.paper_size"
                                required
                                class="w-full h-[40px] px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-teal-700"
                            >
                                <option value="A4">A4 Full Sheet (Default Standard)</option>
                                <option value="A5">A5 Half Sheet</option>
                                <option value="80mm">80mm Thermal Receipt (POS Roll)</option>
                            </select>
                        </FormField>

                        <FormField label="Invoice Footer Note" class="md:col-span-2" :error="form.errors.footer_note">
                            <input
                                v-model="form.footer_note"
                                type="text"
                                maxlength="500"
                                class="w-full h-[40px] px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-teal-700"
                                placeholder="Thank you for shopping with Tasa Leather Goods!"
                            />
                        </FormField>

                        <FormField label="Terms & Conditions / Return Policy" class="md:col-span-2" :error="form.errors.terms">
                            <textarea
                                v-model="form.terms"
                                rows="2"
                                maxlength="1000"
                                class="w-full p-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-teal-700 resize-none"
                                placeholder="Exchange within 7 days with invoice. No cash refund."
                            />
                        </FormField>
                    </div>
                </div>

                <!-- Custom Template Editor Section -->
                <div class="bg-surface border border-line-200 p-6 space-y-4">
                    <div class="flex items-center justify-between border-b border-line-200 pb-2">
                        <h2 class="text-sm font-bold text-ink-900">
                            Custom Template Editor (Safe Placeholders)
                        </h2>
                        <span class="text-[11px] text-teal-700 font-bold">No PHP Execution</span>
                    </div>

                    <!-- Security Alert -->
                    <div class="p-3 bg-teal-50 border border-teal-300 text-teal-800 text-xs flex items-start space-x-2">
                        <AppIcon name="shield" :size="18" class="flex-shrink-0 text-teal-700 mt-0.5" />
                        <div>
                            <span class="font-bold">Strict Security Sandbox (PRD §6.3, Schema §7.3):</span>
                            Templates accept sanitized HTML and documented placeholders only. Evaluating raw PHP (&lt;?php ... ?&gt;), Blade expressions, or JavaScript is strictly forbidden.
                        </div>
                    </div>

                    <!-- Tokens Palette -->
                    <div class="space-y-1.5">
                        <div class="text-[11px] font-bold text-ink-700 uppercase">Click token to insert:</div>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="t in availableTokens"
                                :key="t.token"
                                type="button"
                                class="px-2 py-1 text-[11px] font-mono bg-page border border-line-300 hover:border-teal-700 hover:bg-teal-50 text-ink-800 transition-colors"
                                :title="t.desc"
                                @click="insertToken(t.token)"
                            >
                                {{ t.token }}
                            </button>
                        </div>
                    </div>

                    <FormField label="Custom Template Markup (Leave blank to use default built-in layout)" :error="form.errors.custom_template">
                        <textarea
                            v-model="form.custom_template"
                            rows="8"
                            class="w-full p-3 text-xs font-mono bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-teal-700"
                            placeholder="Enter custom HTML structure with {{tokens}}..."
                        />
                    </FormField>

                    <!-- Live Template Preview -->
                    <div class="space-y-2 pt-2 border-t border-line-200">
                        <div class="text-xs font-bold text-ink-900 flex items-center justify-between">
                            <span>Live Layout Preview (Sample Data)</span>
                            <span class="text-ink-500 font-normal">Paper Size: {{ form.paper_size }}</span>
                        </div>
                        <div class="p-4 bg-page border border-line-300 overflow-auto max-h-96">
                            <!-- Safe HTML Output -->
                            <div v-html="previewHtml" />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <AppButton variant="primary" type="submit" :loading="form.processing">
                        Save Invoice Settings
                    </AppButton>
                </div>
            </form>
        </div>
    </TasaShell>
</template>
