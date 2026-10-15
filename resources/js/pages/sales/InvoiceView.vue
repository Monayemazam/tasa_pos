<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppButton from '@/components/tasa/AppButton.vue';
import AppIcon from '@/components/tasa/AppIcon.vue';
import StatusBadge from '@/components/tasa/StatusBadge.vue';
import { formatMoney } from '@/lib/formatters';

interface InvoiceData {
    id: number;
    invoice_no: string;
    sale_date: string;
    sold_at: string | null;
    status: string;
    payment_status: 'paid' | 'partial' | 'unpaid';
    currency: string;
    shop: {
        name: string;
        address: string;
        phone: string;
        logo: string;
    };
    customer: {
        name: string;
        phone: string;
        address: string;
        notes: string | null;
    };
    courier: {
        mode: 'pathao' | 'none';
        consignment_id: string | null;
        status: string | null;
        city: string | null;
        zone: string | null;
        area: string | null;
    };
    items: Array<{
        id: number;
        name: string;
        is_custom: boolean;
        qty: number;
        unit_price: number;
        line_total: number;
    }>;
    financials: {
        subtotal: number;
        discount_type: string;
        discount_value: number;
        discount_amount: number;
        delivery_charge: number;
        grand_total: number;
        paid_total: number;
        due_amount: number;
        payment_methods: string;
    };
    branding: {
        paper_size: string;
        footer_note: string;
        terms: string;
        selectable_fields: Record<string, boolean>;
    };
}

const props = defineProps<{
    invoice: InvoiceData;
    paperSize?: string;
    can?: {
        cancel: boolean;
        return: boolean;
        add_payment: boolean;
    };
}>();

const currentPaperSize = ref<'A4' | 'A5' | '80mm'>(
    (props.paperSize as any) || (props.invoice.branding.paper_size as any) || 'A4'
);

function handlePrint() {
    window.print();
}
</script>

<template>
    <Head :title="`Invoice ${invoice.invoice_no} — Tasa POS`" />

        <div class="max-w-[1020px] mx-auto space-y-4">
            <!-- Cancelled Notice -->
            <div
                v-if="invoice.status === 'cancelled'"
                class="bg-[#FBEAE9] border border-[#F2BCB8] p-3 text-xs text-[#8E1B12] font-bold text-center"
            >
                THIS INVOICE HAS BEEN CANCELLED — Historical snapshot preserved.
            </div>

            <!-- Top Controls Toolbar (Hidden in Print) -->
            <div class="no-print bg-white border border-[#D5DCDB] p-3 flex flex-wrap items-center justify-between gap-3 shadow-xs">
                <!-- Navigation -->
                <div class="flex items-center gap-2">
                    <Link href="/sales/invoices">
                        <AppButton variant="secondary" size="sm">
                            <AppIcon name="arrow-left" :size="14" class="mr-1" />
                            Back to Invoices
                        </AppButton>
                    </Link>
                    <span class="text-xs font-mono font-bold text-[#0B6E5E] px-2 py-1 bg-[#E6F2EF] border border-[#B8DCD5]">
                        {{ invoice.invoice_no }}
                    </span>
                </div>

                <!-- Paper Size Switcher -->
                <div class="flex items-center bg-[#EBEFEE] p-0.5 border border-[#D5DCDB] text-xs">
                    <button
                        type="button"
                        class="px-3 py-1 font-medium transition-colors"
                        :class="currentPaperSize === 'A4' ? 'bg-[#0B6E5E] text-white font-bold' : 'text-[#4A5957] hover:text-[#0F1B1A]'"
                        @click="currentPaperSize = 'A4'"
                    >
                        A4 Full
                    </button>
                    <button
                        type="button"
                        class="px-3 py-1 font-medium transition-colors"
                        :class="currentPaperSize === 'A5' ? 'bg-[#0B6E5E] text-white font-bold' : 'text-[#4A5957] hover:text-[#0F1B1A]'"
                        @click="currentPaperSize = 'A5'"
                    >
                        A5 Half
                    </button>
                    <button
                        type="button"
                        class="px-3 py-1 font-medium transition-colors"
                        :class="currentPaperSize === '80mm' ? 'bg-[#0B6E5E] text-white font-bold' : 'text-[#4A5957] hover:text-[#0F1B1A]'"
                        @click="currentPaperSize = '80mm'"
                    >
                        80mm Thermal
                    </button>
                </div>

                <!-- Print & PDF Download Buttons -->
                <div class="flex items-center gap-2">
                    <AppButton
                        variant="secondary"
                        size="sm"
                        @click="handlePrint"
                    >
                        <AppIcon name="printer" :size="16" class="mr-1.5" />
                        Print (Ctrl+P)
                    </AppButton>
                    <a
                        :href="`/sales/invoices/${invoice.id}/pdf?paper_size=${currentPaperSize}`"
                        target="_blank"
                    >
                        <AppButton variant="primary" size="sm">
                            <AppIcon name="download" :size="16" class="mr-1.5" />
                            Download PDF
                        </AppButton>
                    </a>
                </div>
            </div>

            <!-- Printable Invoice Document Sheet Container -->
            <div class="flex justify-center">
                <!-- 80mm Thermal Receipt Layout -->
                <div
                    v-if="currentPaperSize === '80mm'"
                    class="w-[340px] bg-white border border-[#D5DCDB] p-5 shadow-xs font-mono text-[11px] print:border-0 print:p-0 print:w-full print:shadow-none"
                >
                    <!-- Thermal Header -->
                    <div class="text-center space-y-1">
                        <img
                            v-if="invoice.shop.logo"
                            :src="invoice.shop.logo"
                            alt="Logo"
                            class="max-h-10 mx-auto mb-1"
                        />
                        <div class="font-extrabold text-[15px] text-[#0F1B1A]">
                            {{ invoice.shop.name }}
                        </div>
                        <div v-if="invoice.shop.address" class="text-[10px] text-[#52605C]">
                            {{ invoice.shop.address }}
                        </div>
                        <div v-if="invoice.shop.phone" class="text-[10px] text-[#52605C]">
                            Phone: {{ invoice.shop.phone }}
                        </div>
                    </div>

                    <div class="my-3 border-t border-dashed border-[#8E9B99]"></div>

                    <!-- Thermal Details -->
                    <div class="space-y-0.5 text-[11px]">
                        <div><span class="font-bold">INVOICE:</span> {{ invoice.invoice_no }}</div>
                        <div><span class="font-bold">DATE:</span> {{ invoice.sale_date }}</div>
                        <div><span class="font-bold">CUSTOMER:</span> {{ invoice.customer.name }}</div>
                        <div><span class="font-bold">PHONE:</span> {{ invoice.customer.phone }}</div>
                        <div><span class="font-bold">ADDRESS:</span> {{ invoice.customer.address }}</div>
                        <div v-if="invoice.courier.consignment_id">
                            <span class="font-bold">CONSIGNMENT:</span> {{ invoice.courier.consignment_id }}
                        </div>
                    </div>

                    <div class="my-3 border-t border-dashed border-[#8E9B99]"></div>

                    <!-- Thermal Items -->
                    <div class="space-y-2">
                        <div
                            v-for="item in invoice.items"
                            :key="item.id"
                            class="space-y-0.5 border-b border-dotted border-[#D5DCDB] pb-1.5"
                        >
                            <div class="font-bold text-[#0F1B1A]">{{ item.name }}</div>
                            <div class="flex justify-between text-[#52605C]">
                                <span>{{ item.qty }} × {{ formatMoney(item.unit_price) }}</span>
                                <span class="font-bold text-[#0F1B1A]">{{ formatMoney(item.line_total) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="my-3 border-t border-dashed border-[#8E9B99]"></div>

                    <!-- Thermal Totals -->
                    <div class="space-y-1 text-right">
                        <div class="flex justify-between">
                            <span class="text-[#52605C]">Subtotal:</span>
                            <span>{{ formatMoney(invoice.financials.subtotal) }}</span>
                        </div>
                        <div v-if="invoice.financials.discount_amount > 0" class="flex justify-between text-[#8E1B12]">
                            <span>Discount:</span>
                            <span>-{{ formatMoney(invoice.financials.discount_amount) }}</span>
                        </div>
                        <div v-if="invoice.financials.delivery_charge > 0" class="flex justify-between">
                            <span class="text-[#52605C]">Delivery:</span>
                            <span>{{ formatMoney(invoice.financials.delivery_charge) }}</span>
                        </div>
                        <div class="flex justify-between text-[13px] font-extrabold border-y border-dashed border-[#0F1B1A] py-1 my-1">
                            <span>GRAND TOTAL:</span>
                            <span>{{ formatMoney(invoice.financials.grand_total) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-[#52605C]">Paid ({{ invoice.financials.payment_methods }}):</span>
                            <span>{{ formatMoney(invoice.financials.paid_total) }}</span>
                        </div>
                        <div class="flex justify-between font-bold" :class="invoice.financials.due_amount > 0 ? 'text-[#8E1B12]' : 'text-[#0A5E2E]'">
                            <span>BALANCE DUE:</span>
                            <span>{{ formatMoney(invoice.financials.due_amount) }}</span>
                        </div>
                    </div>

                    <!-- Thermal Footer -->
                    <div v-if="invoice.branding.footer_note || invoice.branding.terms" class="mt-4 pt-2 border-t border-dashed border-[#8E9B99] text-center text-[10px] text-[#52605C] space-y-1">
                        <div v-if="invoice.branding.footer_note">{{ invoice.branding.footer_note }}</div>
                        <div v-if="invoice.branding.terms" class="italic">{{ invoice.branding.terms }}</div>
                    </div>
                </div>

                <!-- A4 & A5 Standard Sheet Layout -->
                <div
                    v-else
                    class="bg-white border border-[#D5DCDB] p-8 sm:p-12 shadow-xs transition-all print:border-0 print:p-0 print:shadow-none w-full"
                    :class="currentPaperSize === 'A5' ? 'max-w-[620px]' : 'max-w-[840px]'"
                >
                    <!-- Sheet Header -->
                    <div class="flex items-start justify-between border-b-2 border-[#0B6E5E] pb-6 gap-6">
                        <!-- Shop Info -->
                        <div class="space-y-1">
                            <img
                                v-if="invoice.shop.logo"
                                :src="invoice.shop.logo"
                                alt="Shop Logo"
                                class="max-h-12 mb-2"
                            />
                            <h1 class="text-xl sm:text-2xl font-extrabold text-[#0B6E5E] tracking-tight">
                                {{ invoice.shop.name }}
                            </h1>
                            <p v-if="invoice.shop.address" class="text-xs text-[#52605C] max-w-sm">
                                {{ invoice.shop.address }}
                            </p>
                            <p v-if="invoice.shop.phone" class="text-xs text-[#52605C]">
                                Phone: {{ invoice.shop.phone }}
                            </p>
                        </div>

                        <!-- Invoice Meta -->
                        <div class="text-right space-y-1.5">
                            <div class="text-xl sm:text-2xl font-black text-[#0F1B1A]">
                                INVOICE
                            </div>
                            <div class="font-mono text-sm font-bold text-[#0B6E5E]">
                                {{ invoice.invoice_no }}
                            </div>
                            <div class="text-xs text-[#52605C]">
                                <strong>Date:</strong> {{ invoice.sale_date }}
                            </div>
                            <div>
                                <StatusBadge
                                    :status="invoice.payment_status === 'paid' ? 'success' : (invoice.payment_status === 'partial' ? 'warning' : 'danger')"
                                    :label="invoice.payment_status.toUpperCase()"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Customer & Delivery Two-Column Box -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 my-6 p-4 bg-[#FAFBFB] border border-[#D5DCDB] text-xs">
                        <!-- Customer -->
                        <div class="space-y-1">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-[#0B6E5E]">
                                Billed To / Customer
                            </div>
                            <div class="text-sm font-bold text-[#0F1B1A]">
                                {{ invoice.customer.name }}
                            </div>
                            <div class="font-mono text-[#52605C]">
                                Phone: {{ invoice.customer.phone }}
                            </div>
                            <div class="text-[#52605C] whitespace-pre-line">
                                {{ invoice.customer.address }}
                            </div>
                        </div>

                        <!-- Delivery / Shipping -->
                        <div class="space-y-1 sm:border-l sm:border-[#D5DCDB] sm:pl-4">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-[#0B6E5E]">
                                Delivery & Payment
                            </div>
                            <div>
                                <strong class="text-[#52605C]">Delivery Mode:</strong>
                                <span class="ml-1 font-medium">
                                    {{ invoice.courier.mode === 'pathao' ? 'Pathao Courier' : 'Hand-delivered / No courier' }}
                                </span>
                            </div>
                            <div v-if="invoice.courier.consignment_id">
                                <strong class="text-[#52605C]">Consignment ID:</strong>
                                <span class="ml-1 font-mono font-bold text-[#0B6E5E]">{{ invoice.courier.consignment_id }}</span>
                            </div>
                            <div v-if="invoice.courier.city" class="text-[#52605C]">
                                {{ invoice.courier.area }}, {{ invoice.courier.zone }}, {{ invoice.courier.city }}
                            </div>
                            <div>
                                <strong class="text-[#52605C]">Payment Method:</strong>
                                <span class="ml-1">{{ invoice.financials.payment_methods }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <div class="overflow-x-auto my-6">
                        <table class="w-full text-xs text-left border-collapse">
                            <thead>
                                <tr class="bg-[#E6F2EF] text-[#0B6E5E] text-[11px] uppercase font-bold border-b border-[#D5DCDB]">
                                    <th class="p-2.5 w-8">#</th>
                                    <th class="p-2.5">Item Description</th>
                                    <th class="p-2.5 text-center w-14">Qty</th>
                                    <th class="p-2.5 text-right w-24">Unit Price</th>
                                    <th class="p-2.5 text-right w-28">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#EBEFEE]">
                                <tr
                                    v-for="(item, idx) in invoice.items"
                                    :key="item.id"
                                    class="hover:bg-[#FAFBFB]"
                                >
                                    <td class="p-2.5 text-[#728481]">{{ idx + 1 }}</td>
                                    <td class="p-2.5 font-medium text-[#0F1B1A]">
                                        {{ item.name }}
                                        <span v-if="item.is_custom" class="ml-1 text-[10px] px-1 bg-[#EBEFEE] text-[#52605C]">Custom</span>
                                    </td>
                                    <td class="p-2.5 text-center">{{ item.qty }}</td>
                                    <td class="p-2.5 text-right font-mono">{{ formatMoney(item.unit_price) }}</td>
                                    <td class="p-2.5 text-right font-mono font-bold">{{ formatMoney(item.line_total) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Financial Summary Bar (page-break-inside: avoid) -->
                    <div class="flex justify-end my-6 break-inside-avoid">
                        <div class="w-72 space-y-2 text-xs">
                            <div class="flex justify-between text-[#52605C]">
                                <span>Subtotal:</span>
                                <span class="font-mono text-[#0F1B1A]">{{ formatMoney(invoice.financials.subtotal) }}</span>
                            </div>

                            <div v-if="invoice.financials.discount_amount > 0" class="flex justify-between text-[#8E1B12]">
                                <span>Discount:</span>
                                <span class="font-mono">-{{ formatMoney(invoice.financials.discount_amount) }}</span>
                            </div>

                            <div v-if="invoice.financials.delivery_charge > 0" class="flex justify-between text-[#52605C]">
                                <span>Delivery Charge:</span>
                                <span class="font-mono text-[#0F1B1A]">{{ formatMoney(invoice.financials.delivery_charge) }}</span>
                            </div>

                            <div class="flex justify-between items-center bg-[#E6F2EF] p-2 text-sm font-bold text-[#0B6E5E] border-y border-[#B8DCD5]">
                                <span>Grand Total:</span>
                                <span class="font-mono text-base">{{ formatMoney(invoice.financials.grand_total) }}</span>
                            </div>

                            <div class="flex justify-between text-[#52605C]">
                                <span>Amount Paid:</span>
                                <span class="font-mono font-bold text-[#0A5E2E]">{{ formatMoney(invoice.financials.paid_total) }}</span>
                            </div>

                            <div
                                class="flex justify-between font-bold pt-1 border-t border-[#D5DCDB]"
                                :class="invoice.financials.due_amount > 0 ? 'text-[#8E1B12]' : 'text-[#0B6E5E]'"
                            >
                                <span>Balance Due:</span>
                                <span class="font-mono">{{ formatMoney(invoice.financials.due_amount) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer & Terms -->
                    <div
                        v-if="invoice.branding.footer_note || invoice.branding.terms"
                        class="mt-8 pt-4 border-t border-[#D5DCDB] text-xs text-[#52605C] space-y-1 break-inside-avoid"
                    >
                        <p v-if="invoice.branding.footer_note" class="font-medium text-[#0F1B1A]">
                            {{ invoice.branding.footer_note }}
                        </p>
                        <p v-if="invoice.branding.terms" class="italic text-[11px]">
                            {{ invoice.branding.terms }}
                        </p>
                    </div>
                </div>
            </div>
    </div>
</template>

<style>
@media print {
    /* Hide all page shell, navigation, header, sidebar, buttons */
    nav, header, aside, .no-print, #app > div > div > header, #app > div > div > aside {
        display: none !important;
    }
    body {
        background: #fff !important;
        color: #000 !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    .break-inside-avoid {
        page-break-inside: avoid;
        break-inside: avoid;
    }
}
</style>
