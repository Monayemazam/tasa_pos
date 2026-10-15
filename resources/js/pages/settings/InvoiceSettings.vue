<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '@/components/tasa/PageHeader.vue';
import AppButton from '@/components/tasa/AppButton.vue';
import AppIcon from '@/components/tasa/AppIcon.vue';
import FormField from '@/components/tasa/FormField.vue';

interface InvoiceSettingsProps {
    shop_name: string;
    shop_address: string;
    shop_phone: string;
    logo: string;
    prefix: string;
    paper_size: string;
    footer_note: string;
    terms: string;
    selectable_fields: {
        show_logo: boolean;
        show_shop_address: boolean;
        show_shop_phone: boolean;
        show_discount: boolean;
        show_delivery_charge: boolean;
        show_consignment_id: boolean;
        show_payment_methods: boolean;
        show_footer_note: boolean;
        show_terms: boolean;
    };
    custom_template: string | null;
}

const props = defineProps<{
    settings: InvoiceSettingsProps;
}>();

const previewPaperSize = ref<'A4' | 'A5' | '80mm'>((props.settings.paper_size as any) || 'A4');
const logoPreview = ref<string | null>(props.settings.logo || null);

const form = useForm({
    shop_name: props.settings.shop_name || 'Tasa Leather Goods',
    shop_address: props.settings.shop_address || 'Dhaka, Bangladesh',
    shop_phone: props.settings.shop_phone || '+880 1700-000000',
    logo_file: null as File | null,
    prefix: props.settings.prefix || 'TASA',
    paper_size: props.settings.paper_size || 'A4',
    footer_note: props.settings.footer_note || 'Thank you for shopping with Tasa Leather Goods!',
    terms: props.settings.terms || 'Exchange within 7 days with invoice. No cash refunds.',
    selectable_fields: {
        show_logo: props.settings.selectable_fields?.show_logo ?? true,
        show_shop_address: props.settings.selectable_fields?.show_shop_address ?? true,
        show_shop_phone: props.settings.selectable_fields?.show_shop_phone ?? true,
        show_discount: props.settings.selectable_fields?.show_discount ?? true,
        show_delivery_charge: props.settings.selectable_fields?.show_delivery_charge ?? true,
        show_consignment_id: props.settings.selectable_fields?.show_consignment_id ?? true,
        show_payment_methods: props.settings.selectable_fields?.show_payment_methods ?? true,
        show_footer_note: props.settings.selectable_fields?.show_footer_note ?? true,
        show_terms: props.settings.selectable_fields?.show_terms ?? true,
    },
    custom_template: props.settings.custom_template || '',
});

function handleLogoChange(e: Event) {
    const input = e.target as HTMLInputElement;
    if (input.files && input.files[0]) {
        const file = input.files[0];
        form.logo_file = file;
        const reader = new FileReader();
        reader.onload = (re) => {
            logoPreview.value = re.target?.result as string;
        };
        reader.readAsDataURL(file);
    }
}

function submitInvoiceSettings() {
    form.post('/settings/invoice', {
        preserveScroll: true,
        forceFormData: true,
    });
}

// Safe Tokens Palette
const availableTokens = [
    { token: '{{shop_logo}}', desc: 'Shop branding logo' },
    { token: '{{shop_name}}', desc: 'Store Name' },
    { token: '{{shop_address}}', desc: 'Store Address' },
    { token: '{{shop_phone}}', desc: 'Store Phone' },
    { token: '{{invoice_no}}', desc: 'Invoice Number (e.g. TASA-2610-0001)' },
    { token: '{{sale_date}}', desc: 'Transaction Date' },
    { token: '{{customer_name}}', desc: 'Customer Full Name' },
    { token: '{{customer_phone}}', desc: 'Customer Phone (01XXXXXXXXX)' },
    { token: '{{customer_address}}', desc: 'Destination Address' },
    { token: '{{items_table}}', desc: 'Item rows (Product, Qty, Price, Total)' },
    { token: '{{subtotal}}', desc: 'Subtotal amount' },
    { token: '{{discount}}', desc: 'Discount amount' },
    { token: '{{delivery_charge}}', desc: 'Customer delivery charge' },
    { token: '{{grand_total}}', desc: 'Grand total payable' },
    { token: '{{paid_total}}', desc: 'Amount paid' },
    { token: '{{due_amount}}', desc: 'Balance due' },
    { token: '{{payment_methods}}', desc: 'Payment methods' },
    { token: '{{consignment_id}}', desc: 'Pathao Consignment ID' },
    { token: '{{footer_note}}', desc: 'Footer message' },
    { token: '{{terms}}', desc: 'Return terms' },
];

function insertToken(token: string) {
    form.custom_template += ` ${token} `;
}

// Live Simulated Preview HTML
const livePreviewHtml = computed(() => {
    const currency = '৳';
    const invoiceNumber = `${form.prefix}-2610-0001`;

    if (form.custom_template && form.custom_template.trim()) {
        let rendered = form.custom_template
            .replace(/{{shop_logo}}/g, logoPreview.value ? `<img src="${logoPreview.value}" style="max-height:40px;">` : '')
            .replace(/{{shop_name}}/g, form.shop_name)
            .replace(/{{shop_address}}/g, form.shop_address)
            .replace(/{{shop_phone}}/g, form.shop_phone)
            .replace(/{{invoice_no}}/g, invoiceNumber)
            .replace(/{{sale_date}}/g, '12 Oct 2026')
            .replace(/{{customer_name}}/g, 'Tanvir Hasan')
            .replace(/{{customer_phone}}/g, '01812345678')
            .replace(/{{customer_address}}/g, 'Road 27, House 45, Dhanmondi, Dhaka')
            .replace(/{{items_table}}/g, `
                <table style="width:100%; border-collapse:collapse; font-size:11px; margin: 8px 0;">
                    <tr style="background:#E6F2EF; font-weight:bold;">
                        <th style="padding:4px; text-align:left;">Item</th>
                        <th style="padding:4px; text-align:center;">Qty</th>
                        <th style="padding:4px; text-align:right;">Price</th>
                        <th style="padding:4px; text-align:right;">Total</th>
                    </tr>
                    <tr style="border-bottom:1px solid #EBEFEE;">
                        <td style="padding:4px;">Classic Leather Bifold Wallet</td>
                        <td style="padding:4px; text-align:center;">2</td>
                        <td style="padding:4px; text-align:right;">${currency}1,500.00</td>
                        <td style="padding:4px; text-align:right; font-weight:bold;">${currency}3,000.00</td>
                    </tr>
                </table>
            `)
            .replace(/{{subtotal}}/g, `${currency}3,000.00`)
            .replace(/{{discount}}/g, `${currency}200.00`)
            .replace(/{{delivery_charge}}/g, `${currency}80.00`)
            .replace(/{{grand_total}}/g, `${currency}2,880.00`)
            .replace(/{{paid_total}}/g, `${currency}1,000.00`)
            .replace(/{{due_amount}}/g, `${currency}1,880.00`)
            .replace(/{{payment_methods}}/g, 'bKash')
            .replace(/{{consignment_id}}/g, 'PTH-998877')
            .replace(/{{footer_note}}/g, form.footer_note)
            .replace(/{{terms}}/g, form.terms);

        return `<div style="background:#fff; padding:15px; border:1px solid #D5DCDB;">${rendered}</div>`;
    }

    // Default Preview based on selected tab
    if (previewPaperSize.value === '80mm') {
        return `
            <div style="font-family: monospace; font-size: 11px; width: 280px; margin: 0 auto; background: #fff; padding: 12px; border: 1px dashed #B8C4C2;">
                <div style="text-align: center;">
                    ${logoPreview.value && form.selectable_fields.show_logo ? `<img src="${logoPreview.value}" style="max-height: 32px; margin-bottom: 4px;"/><br>` : ''}
                    <strong style="font-size: 13px;">${form.shop_name}</strong>
                    ${form.selectable_fields.show_shop_address ? `<div style="font-size: 10px;">${form.shop_address}</div>` : ''}
                    ${form.selectable_fields.show_shop_phone ? `<div style="font-size: 10px;">Phone: ${form.shop_phone}</div>` : ''}
                </div>
                <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>
                <div>
                    <div><strong>INV:</strong> ${invoiceNumber}</div>
                    <div><strong>DATE:</strong> 12 Oct 2026</div>
                    <div><strong>CUST:</strong> Tanvir Hasan (01812345678)</div>
                    ${form.selectable_fields.show_consignment_id ? `<div><strong>PTH:</strong> PTH-998877</div>` : ''}
                </div>
                <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>
                <div>
                    <div style="font-weight:bold;">Classic Bifold Wallet</div>
                    <div style="display:flex; justify-content:space-between; color:#555;">
                        <span>2 × ${currency}1,500.00</span>
                        <strong>${currency}3,000.00</strong>
                    </div>
                </div>
                <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>
                <div style="line-height: 1.4;">
                    <div style="display:flex; justify-content:space-between;"><span>Subtotal:</span><span>${currency}3,000.00</span></div>
                    ${form.selectable_fields.show_discount ? `<div style="display:flex; justify-content:space-between; color:#8E1B12;"><span>Discount:</span><span>-${currency}200.00</span></div>` : ''}
                    ${form.selectable_fields.show_delivery_charge ? `<div style="display:flex; justify-content:space-between;"><span>Delivery:</span><span>${currency}80.00</span></div>` : ''}
                    <div style="display:flex; justify-content:space-between; font-weight:bold; font-size:12px; border-top:1px dashed #000; border-bottom:1px dashed #000; padding:2px 0; margin:2px 0;">
                        <span>TOTAL:</span><span>${currency}2,880.00</span>
                    </div>
                    ${form.selectable_fields.show_payment_methods ? `<div style="display:flex; justify-content:space-between;"><span>Paid (bKash):</span><span>${currency}1,000.00</span></div>` : ''}
                    <div style="display:flex; justify-content:space-between; font-weight:bold; color:#8E1B12;"><span>DUE:</span><span>${currency}1,880.00</span></div>
                </div>
                ${form.selectable_fields.show_footer_note && form.footer_note ? `<div style="border-top:1px dashed #000; margin-top:6px; padding-top:4px; text-align:center; font-size:10px;">${form.footer_note}</div>` : ''}
            </div>
        `;
    }

    // A4 / A5 Standard Sheet Preview
    return `
        <div style="font-family: -apple-system, sans-serif; font-size: 11px; background: #fff; padding: 20px; border: 1px solid #D5DCDB;">
            <div style="display:flex; justify-content:space-between; border-bottom: 2px solid #0B6E5E; padding-bottom: 10px;">
                <div>
                    ${logoPreview.value && form.selectable_fields.show_logo ? `<img src="${logoPreview.value}" style="max-height: 36px; margin-bottom: 4px;"/><br>` : ''}
                    <h2 style="margin: 0; color: #0B6E5E; font-size: 18px; font-weight: 800;">${form.shop_name}</h2>
                    ${form.selectable_fields.show_shop_address ? `<p style="margin: 2px 0 0; color: #52605C;">${form.shop_address}</p>` : ''}
                    ${form.selectable_fields.show_shop_phone ? `<p style="margin: 2px 0 0; color: #52605C;">Phone: ${form.shop_phone}</p>` : ''}
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 16px; font-weight: 800; color: #0F1B1A;">INVOICE</div>
                    <div style="font-family: monospace; font-size: 12px; font-weight: bold; color: #0B6E5E;">${invoiceNumber}</div>
                    <div style="color: #52605C; margin-top: 2px;">12 Oct 2026</div>
                </div>
            </div>

            <div style="display:flex; justify-content:space-between; background:#FAFBFB; border:1px solid #D5DCDB; padding:8px 12px; margin: 12px 0;">
                <div>
                    <span style="font-size:9px; font-weight:bold; color:#0B6E5E; text-transform:uppercase;">Billed To</span>
                    <div style="font-weight:bold;">Tanvir Hasan</div>
                    <div style="color:#52605C;">01812345678 · Road 27, Dhanmondi, Dhaka</div>
                </div>
                ${form.selectable_fields.show_consignment_id ? `
                <div style="text-align:right;">
                    <span style="font-size:9px; font-weight:bold; color:#0B6E5E; text-transform:uppercase;">Delivery</span>
                    <div>Pathao Courier</div>
                    <div style="font-family:monospace; color:#0B6E5E; font-weight:bold;">PTH-998877</div>
                </div>` : ''}
            </div>

            <table style="width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 12px;">
                <thead>
                    <tr style="background: #E6F2EF; color: #0B6E5E; font-weight: bold;">
                        <th style="padding: 6px; text-align: left;">Item</th>
                        <th style="padding: 6px; text-align: center;">Qty</th>
                        <th style="padding: 6px; text-align: right;">Unit Price</th>
                        <th style="padding: 6px; text-align: right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="border-bottom: 1px solid #EBEFEE;">
                        <td style="padding: 6px;">Classic Leather Bifold Wallet</td>
                        <td style="padding: 6px; text-align: center;">2</td>
                        <td style="padding: 6px; text-align: right;">${currency}1,500.00</td>
                        <td style="padding: 6px; text-align: right; font-weight: bold;">${currency}3,000.00</td>
                    </tr>
                </tbody>
            </table>

            <div style="display: flex; justify-content: flex-end;">
                <div style="width: 220px; line-height: 1.5;">
                    <div style="display:flex; justify-content:space-between;"><span>Subtotal:</span><span>${currency}3,000.00</span></div>
                    ${form.selectable_fields.show_discount ? `<div style="display:flex; justify-content:space-between; color:#8E1B12;"><span>Discount:</span><span>-${currency}200.00</span></div>` : ''}
                    ${form.selectable_fields.show_delivery_charge ? `<div style="display:flex; justify-content:space-between;"><span>Delivery:</span><span>${currency}80.00</span></div>` : ''}
                    <div style="display:flex; justify-content:space-between; background:#E6F2EF; padding:4px; font-weight:bold; color:#0B6E5E; margin: 4px 0;">
                        <span>Grand Total:</span><span>${currency}2,880.00</span>
                    </div>
                    ${form.selectable_fields.show_payment_methods ? `<div style="display:flex; justify-content:space-between;"><span>Paid (bKash):</span><span>${currency}1,000.00</span></div>` : ''}
                    <div style="display:flex; justify-content:space-between; font-weight:bold; color:#8E1B12; border-top:1px solid #D5DCDB; padding-top:2px;">
                        <span>Due:</span><span>${currency}1,880.00</span>
                    </div>
                </div>
            </div>

            ${(form.selectable_fields.show_footer_note && form.footer_note) || (form.selectable_fields.show_terms && form.terms) ? `
            <div style="margin-top: 15px; padding-top: 8px; border-top: 1px solid #D5DCDB; font-size: 10px; color: #52605C;">
                ${form.selectable_fields.show_footer_note && form.footer_note ? `<p style="margin: 0; font-weight: 500;">${form.footer_note}</p>` : ''}
                ${form.selectable_fields.show_terms && form.terms ? `<p style="margin: 2px 0 0; font-style: italic;">${form.terms}</p>` : ''}
            </div>` : ''}
        </div>
    `;
});
</script>

<template>
    <Head title="Invoice Branding & Templates — Tasa POS" />

        <div class="max-w-[1560px] mx-auto space-y-6">
            <!-- Header -->
            <PageHeader
                title="Invoice Branding & Print Layouts"
                subtitle="Owner settings: Configure logo, shop address, sequential prefix, paper layouts, and safe template markup."
            />

            <!-- Main Split: Form on Left, Live Preview on Right -->
            <form class="space-y-6" @submit.prevent="submitInvoiceSettings">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    <!-- LEFT COLUMN: Settings Form (7 cols) -->
                    <div class="lg:col-span-7 space-y-5">
                        <!-- Shop Identity & Logo -->
                        <div class="bg-white border border-[#D5DCDB] p-5 space-y-4">
                            <h2 class="text-xs font-bold uppercase tracking-wider text-[#0B6E5E] border-b border-[#D5DCDB] pb-2">
                                Shop Identity & Logo
                            </h2>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <FormField label="Shop Name" required :error="form.errors.shop_name">
                                    <input
                                        v-model="form.shop_name"
                                        type="text"
                                        required
                                        maxlength="120"
                                        class="w-full h-9 px-3 text-xs bg-[#FAFBFB] border border-[#D5DCDB] rounded-none focus:border-[#0B6E5E] focus:outline-none"
                                    />
                                </FormField>

                                <FormField label="Shop Phone" :error="form.errors.shop_phone">
                                    <input
                                        v-model="form.shop_phone"
                                        type="text"
                                        maxlength="50"
                                        class="w-full h-9 px-3 text-xs bg-[#FAFBFB] border border-[#D5DCDB] rounded-none focus:border-[#0B6E5E] focus:outline-none font-mono"
                                    />
                                </FormField>

                                <FormField label="Shop Address" class="sm:col-span-2" :error="form.errors.shop_address">
                                    <textarea
                                        v-model="form.shop_address"
                                        rows="2"
                                        maxlength="500"
                                        class="w-full p-2.5 text-xs bg-[#FAFBFB] border border-[#D5DCDB] rounded-none focus:border-[#0B6E5E] focus:outline-none resize-none"
                                    />
                                </FormField>

                                <!-- Logo Upload -->
                                <div class="sm:col-span-2">
                                    <label class="block text-[11px] font-bold uppercase text-[#4A5957] mb-1">
                                        Shop Logo Upload (PNG, JPG, SVG, WebP max 2MB)
                                    </label>
                                    <div class="flex items-center gap-4">
                                        <div
                                            v-if="logoPreview"
                                            class="w-16 h-16 border border-[#D5DCDB] bg-[#FAFBFB] flex items-center justify-center p-1"
                                        >
                                            <img :src="logoPreview" alt="Logo preview" class="max-h-full max-w-full object-contain" />
                                        </div>
                                        <div class="flex-1">
                                            <input
                                                type="file"
                                                accept="image/png,image/jpeg,image/webp,image/svg+xml"
                                                class="text-xs file:mr-3 file:py-1.5 file:px-3 file:border-0 file:text-xs file:font-semibold file:bg-[#E6F2EF] file:text-[#0B6E5E] hover:file:bg-[#D4DDD9] cursor-pointer"
                                                @change="handleLogoChange"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Invoice Numbering & Paper Layout -->
                        <div class="bg-white border border-[#D5DCDB] p-5 space-y-4">
                            <h2 class="text-xs font-bold uppercase tracking-wider text-[#0B6E5E] border-b border-[#D5DCDB] pb-2">
                                Numbering & Paper Layout
                            </h2>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <FormField label="Invoice Prefix" required :error="form.errors.prefix">
                                    <input
                                        v-model="form.prefix"
                                        type="text"
                                        required
                                        maxlength="10"
                                        class="w-full h-9 px-3 text-xs font-mono uppercase font-bold bg-[#FAFBFB] border border-[#D5DCDB] rounded-none focus:border-[#0B6E5E] focus:outline-none"
                                        placeholder="TASA"
                                    />
                                    <p class="text-[11px] text-[#728481] mt-1 font-mono">
                                        Format: {{ form.prefix }}-YYMM-0001
                                    </p>
                                </FormField>

                                <FormField label="Default Paper Layout" required :error="form.errors.paper_size">
                                    <select
                                        v-model="form.paper_size"
                                        required
                                        class="w-full h-9 px-2 text-xs bg-[#FAFBFB] border border-[#D5DCDB] rounded-none focus:border-[#0B6E5E] focus:outline-none"
                                        @change="previewPaperSize = form.paper_size as any"
                                    >
                                        <option value="A4">A4 Full Sheet (Default Standard)</option>
                                        <option value="A5">A5 Half Sheet</option>
                                        <option value="80mm">80mm Thermal Receipt (POS Roll)</option>
                                    </select>
                                </FormField>

                                <FormField label="Invoice Footer Note" class="sm:col-span-2" :error="form.errors.footer_note">
                                    <input
                                        v-model="form.footer_note"
                                        type="text"
                                        maxlength="500"
                                        class="w-full h-9 px-3 text-xs bg-[#FAFBFB] border border-[#D5DCDB] rounded-none focus:border-[#0B6E5E] focus:outline-none"
                                    />
                                </FormField>

                                <FormField label="Terms & Conditions / Return Policy" class="sm:col-span-2" :error="form.errors.terms">
                                    <textarea
                                        v-model="form.terms"
                                        rows="2"
                                        maxlength="1000"
                                        class="w-full p-2.5 text-xs bg-[#FAFBFB] border border-[#D5DCDB] rounded-none focus:border-[#0B6E5E] focus:outline-none resize-none"
                                    />
                                </FormField>
                            </div>
                        </div>

                        <!-- Selectable Fields -->
                        <div class="bg-white border border-[#D5DCDB] p-5 space-y-3">
                            <h2 class="text-xs font-bold uppercase tracking-wider text-[#0B6E5E] border-b border-[#D5DCDB] pb-2">
                                Selectable Invoice Fields
                            </h2>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs text-[#1A2725]">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input v-model="form.selectable_fields.show_logo" type="checkbox" class="accent-[#0B6E5E]" />
                                    Show Logo
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input v-model="form.selectable_fields.show_shop_address" type="checkbox" class="accent-[#0B6E5E]" />
                                    Shop Address
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input v-model="form.selectable_fields.show_shop_phone" type="checkbox" class="accent-[#0B6E5E]" />
                                    Shop Phone
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input v-model="form.selectable_fields.show_discount" type="checkbox" class="accent-[#0B6E5E]" />
                                    Discount Line
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input v-model="form.selectable_fields.show_delivery_charge" type="checkbox" class="accent-[#0B6E5E]" />
                                    Delivery Charge
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input v-model="form.selectable_fields.show_consignment_id" type="checkbox" class="accent-[#0B6E5E]" />
                                    Pathao Consignment
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input v-model="form.selectable_fields.show_payment_methods" type="checkbox" class="accent-[#0B6E5E]" />
                                    Payment Methods
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input v-model="form.selectable_fields.show_footer_note" type="checkbox" class="accent-[#0B6E5E]" />
                                    Footer Note
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input v-model="form.selectable_fields.show_terms" type="checkbox" class="accent-[#0B6E5E]" />
                                    Terms & Conditions
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT COLUMN: Live Layout Preview (5 cols) -->
                    <div class="lg:col-span-5 space-y-3">
                        <div class="bg-white border border-[#D5DCDB] p-4 space-y-3 sticky top-4">
                            <div class="flex items-center justify-between border-b border-[#D5DCDB] pb-2">
                                <h2 class="text-xs font-bold uppercase tracking-wider text-[#0F1B1A]">
                                    Live Print Preview
                                </h2>

                                <!-- Size Tabs -->
                                <div class="flex items-center bg-[#EBEFEE] p-0.5 border border-[#D5DCDB] text-[11px]">
                                    <button
                                        type="button"
                                        class="px-2 py-0.5 transition-colors"
                                        :class="previewPaperSize === 'A4' ? 'bg-[#0B6E5E] text-white font-bold' : 'text-[#4A5957]'"
                                        @click="previewPaperSize = 'A4'"
                                    >
                                        A4
                                    </button>
                                    <button
                                        type="button"
                                        class="px-2 py-0.5 transition-colors"
                                        :class="previewPaperSize === 'A5' ? 'bg-[#0B6E5E] text-white font-bold' : 'text-[#4A5957]'"
                                        @click="previewPaperSize = 'A5'"
                                    >
                                        A5
                                    </button>
                                    <button
                                        type="button"
                                        class="px-2 py-0.5 transition-colors"
                                        :class="previewPaperSize === '80mm' ? 'bg-[#0B6E5E] text-white font-bold' : 'text-[#4A5957]'"
                                        @click="previewPaperSize = '80mm'"
                                    >
                                        80mm
                                    </button>
                                </div>
                            </div>

                            <!-- Live Sheet Display Container -->
                            <div class="max-h-[640px] overflow-auto border border-[#D5DCDB] bg-[#FAFBFB] p-2">
                                <div v-html="livePreviewHtml" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BOTTOM SECTION: Safe Custom Template Editor -->
                <div class="bg-white border border-[#D5DCDB] p-6 space-y-4">
                    <div class="flex items-center justify-between border-b border-[#D5DCDB] pb-2">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-[#0B6E5E]">
                            Custom Template Editor (Safe HTML / CSS Only)
                        </h2>
                        <span class="text-[11px] font-bold text-[#8E1B12] bg-[#FDECEA] px-2 py-0.5 border border-[#F9B6AF]">
                            No PHP / Scripts Allowed
                        </span>
                    </div>

                    <!-- Security Alert -->
                    <div class="p-3 bg-[#E6F2EF] border border-[#B8DCD5] text-[#0B6E5E] text-xs flex items-start gap-2">
                        <AppIcon name="shield" :size="16" class="shrink-0 mt-0.5" />
                        <div>
                            <span class="font-bold">Strict Security Sandbox (PRD §6.3):</span>
                            Templates accept sanitized HTML/CSS and only documented safe placeholders. Arbitrary PHP, Blade directives, and script tags are strictly blocked and stripped.
                        </div>
                    </div>

                    <!-- Placeholders Palette -->
                    <div class="space-y-1.5">
                        <div class="text-[11px] font-bold text-[#4A5957] uppercase">Click placeholder to insert:</div>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="t in availableTokens"
                                :key="t.token"
                                type="button"
                                class="px-2 py-1 text-[11px] font-mono bg-[#FAFBFB] border border-[#D5DCDB] hover:border-[#0B6E5E] hover:bg-[#E6F2EF] text-[#1A2725] transition-colors"
                                :title="t.desc"
                                @click="insertToken(t.token)"
                            >
                                {{ t.token }}
                            </button>
                        </div>
                    </div>

                    <FormField
                        label="Custom Template Markup (Leave blank to use the built-in system layout)"
                        :error="form.errors.custom_template"
                    >
                        <textarea
                            v-model="form.custom_template"
                            rows="7"
                            class="w-full p-3 text-xs font-mono bg-[#FAFBFB] border border-[#D5DCDB] rounded-none focus:border-[#0B6E5E] focus:outline-none"
                            placeholder="<div>{{shop_name}}</div>..."
                        />
                    </FormField>
                </div>

                <!-- Sticky Save Bar -->
                <div class="flex items-center justify-end gap-3 pt-2">
                    <AppButton
                        variant="primary"
                        type="submit"
                        :loading="form.processing"
                        class="px-8"
                    >
                        Save Invoice Branding
                    </AppButton>
                </div>
            </form>
    </div>
</template>
