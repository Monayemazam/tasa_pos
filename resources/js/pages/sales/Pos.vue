<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/components/tasa/PageHeader.vue';
import AppButton from '@/components/tasa/AppButton.vue';
import AppIcon from '@/components/tasa/AppIcon.vue';
import StatusBadge from '@/components/tasa/StatusBadge.vue';
import FormField from '@/components/tasa/FormField.vue';
import { formatMoney, formatBDPhone } from '@/lib/formatters';
import { api } from '@/lib/api';

interface ProductVariantOption {
    id: number;
    product_id: number;
    sku: string;
    color_name: string;
    attribute_name?: string;
    attribute_value: string;
    selling_price: number;
    cost_price: number | null;
    stock_qty: number;
    min_stock: number;
    is_low_stock: boolean;
    is_active: boolean;
}

interface ProductItem {
    id: number;
    name: string;
    sku: string;
    has_variants?: boolean;
    variation_type?: string;
    variants?: ProductVariantOption[];
    category_id: number | null;
    category_name: string | null;
    selling_price: number;
    cost_price: number | null;
    stock_qty: number;
    min_stock: number;
    is_low_stock: boolean;
}

interface CategoryItem {
    id: number;
    name: string;
}

interface CityItem {
    city_id: number;
    city_name: string;
}

interface ZoneItem {
    zone_id: number;
    zone_name: string;
}

interface AreaItem {
    area_id: number;
    area_name: string;
}

interface CartLine {
    is_custom: boolean;
    product_id: number | null;
    product_variant_id?: number | null;
    variant_name?: string | null;
    variant_sku?: string | null;
    name: string;
    sku?: string;
    qty: number;
    unit_price: number;
    unit_cost?: number;
    available_stock?: number;
}

const props = defineProps<{
    products: ProductItem[];
    categories: CategoryItem[];
    cities: CityItem[];
    canEditPrice: boolean;
    allowNegativeStock: boolean;
    defaultWeight: number;
    defaultDeliveryType: number;
    showSuccessRate: boolean;
}>();

// Mobile wizard step (1: Customer, 2: Products/Cart, 3: Payment)
const currentStep = ref<1 | 2 | 3>(1);

// Idempotency token
function generateUuid(): string {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
        return crypto.randomUUID();
    }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const r = (Math.random() * 16) | 0;
        const v = c === 'x' ? r : (r & 0x3) | 0x8;
        return v.toString(16);
    });
}
const idempotencyToken = ref(generateUuid());

// Customer Form State
const customerPhone = ref('');
const customerName = ref('');
const customerAltPhone = ref('');
const customerAddress = ref('');
const customerNotes = ref('');
const isExistingCustomer = ref(false);
const customerStats = ref<{ total_orders: number; total_spent: number; outstanding_due: number } | null>(null);
const successRateHint = ref<{ status: string; label: string; success_rate: string | null } | null>(null);
const isLookingUpCustomer = ref(false);

// Delivery Mode
const courierMode = ref<'pathao' | 'none'>('pathao');
const selectedCityId = ref<number | null>(props.cities[0]?.city_id ?? 1);
const selectedZoneId = ref<number | null>(null);
const selectedAreaId = ref<number | null>(null);
const cityName = ref(props.cities[0]?.city_name ?? 'Dhaka');
const zoneName = ref('');
const areaName = ref('');
const itemWeight = ref(props.defaultWeight || 0.5);
const deliveryType = ref(props.defaultDeliveryType || 48);
const specialInstruction = ref('');

const zones = ref<ZoneItem[]>([]);
const areas = ref<AreaItem[]>([]);
const isLoadingZones = ref(false);
const isLoadingAreas = ref(false);

// Products Search & Filter State
const searchQuery = ref('');
const selectedCategoryId = ref<number | null>(null);
const productSearchInput = ref<HTMLInputElement | null>(null);

// Cart State
const cart = ref<CartLine[]>([]);

// Custom item modal
const isCustomItemModalOpen = ref(false);
const customItemName = ref('');
const customItemPrice = ref<number | ''>('');
const customItemCost = ref<number | ''>('');
const customItemQty = ref(1);

// Totals & Discount
const discountType = ref<'flat' | 'percent'>('flat');
const discountValue = ref<number | ''>(0);
const deliveryCharge = ref<number | ''>(0);

// Payment State
const paidAmount = ref<number | ''>(0);
const paymentMethod = ref<'cash' | 'cod' | 'bkash' | 'bank_transfer'>('cod');
const paymentReference = ref('');
const paymentNote = ref('');
const saleNotes = ref('');

// UI Submission and Success Confirmation Modal
const isSubmitting = ref(false);
const errorMessage = ref('');
const successSale = ref<any | null>(null);
const isSuccessModalOpen = ref(false);

// Filtered products list
const filteredProducts = computed(() => {
    let list = props.products;
    if (selectedCategoryId.value) {
        list = list.filter((p) => p.category_id === selectedCategoryId.value);
    }
    const q = searchQuery.value.trim().toLowerCase();
    if (q) {
        list = list.filter((p) => {
            if (p.name.toLowerCase().includes(q) || p.sku.toLowerCase().includes(q)) {
                return true;
            }
            if (p.variants && p.variants.length > 0) {
                return p.variants.some((v) =>
                    v.sku.toLowerCase().includes(q) ||
                    (v.color_name && v.color_name.toLowerCase().includes(q)) ||
                    (v.attribute_value && v.attribute_value.toLowerCase().includes(q))
                );
            }
            return false;
        });
    }
    return list;
});

// Calculations
const subtotal = computed(() => {
    return cart.value.reduce((sum, item) => sum + item.qty * item.unit_price, 0);
});

const calculatedDiscount = computed(() => {
    const val = typeof discountValue.value === 'number' ? discountValue.value : 0;
    if (val <= 0) return 0;
    if (discountType.value === 'percent') {
        const clampedPercent = Math.min(100, Math.max(0, val));
        return Math.round(subtotal.value * (clampedPercent / 100) * 100) / 100;
    }
    return Math.min(subtotal.value, Math.round(val * 100) / 100);
});

const deliveryChargeAmount = computed(() => {
    const charge = typeof deliveryCharge.value === 'number' ? deliveryCharge.value : 0;
    return Math.max(0, Math.round(charge * 100) / 100);
});

const grandTotal = computed(() => {
    return Math.max(0, Math.round((subtotal.value - calculatedDiscount.value + deliveryChargeAmount.value) * 100) / 100);
});

const actualPaidAmount = computed(() => {
    const p = typeof paidAmount.value === 'number' ? paidAmount.value : 0;
    return Math.min(grandTotal.value, Math.max(0, Math.round(p * 100) / 100));
});

const balanceDue = computed(() => {
    return Math.max(0, Math.round((grandTotal.value - actualPaidAmount.value) * 100) / 100);
});

// Phone Lookup watcher
let lookupTimer: any = null;
watch(customerPhone, (newPhone) => {
    clearTimeout(lookupTimer);
    const clean = newPhone.trim();
    if (/^01[3-9]\d{8}$/.test(clean)) {
        lookupTimer = setTimeout(async () => {
            isLookingUpCustomer.value = true;
            try {
                const res = await api.get(`/sales/customers/lookup?phone=${encodeURIComponent(clean)}`);
                if (res.found && res.customer) {
                    isExistingCustomer.value = true;
                    customerName.value = res.customer.name;
                    customerAddress.value = res.customer.address;
                    customerAltPhone.value = res.customer.alt_phone || '';
                    customerNotes.value = res.customer.notes || '';
                    customerStats.value = {
                        total_orders: res.customer.total_orders,
                        total_spent: res.customer.total_spent,
                        outstanding_due: res.customer.outstanding_due,
                    };

                    if (res.customer.pathao_city_id) {
                        selectedCityId.value = res.customer.pathao_city_id;
                        cityName.value = res.customer.city_name || '';
                        await loadZones(res.customer.pathao_city_id);

                        if (res.customer.pathao_zone_id) {
                            selectedZoneId.value = res.customer.pathao_zone_id;
                            zoneName.value = res.customer.zone_name || '';
                            await loadAreas(res.customer.pathao_zone_id);

                            if (res.customer.pathao_area_id) {
                                selectedAreaId.value = res.customer.pathao_area_id;
                                areaName.value = res.customer.area_name || '';
                            }
                        }
                    }
                } else {
                    isExistingCustomer.value = false;
                    customerStats.value = null;
                }

                if (res.success_rate) {
                    successRateHint.value = res.success_rate;
                } else {
                    successRateHint.value = null;
                }
            } catch (e) {
                // Ignore lookup errors
            } finally {
                isLookingUpCustomer.value = false;
            }
        }, 300);
    } else {
        isExistingCustomer.value = false;
        customerStats.value = null;
        successRateHint.value = null;
    }
});

// Zones & Areas loading
async function loadZones(cityId: number) {
    if (!cityId) return;
    isLoadingZones.value = true;
    try {
        const data = await api.get(`/sales/courier/zones?city_id=${cityId}`);
        zones.value = data;
        if (!zones.value.some((z) => z.zone_id === selectedZoneId.value)) {
            selectedZoneId.value = zones.value[0]?.zone_id ?? null;
            zoneName.value = zones.value[0]?.zone_name ?? '';
            if (selectedZoneId.value) {
                await loadAreas(selectedZoneId.value);
            }
        }
    } finally {
        isLoadingZones.value = false;
    }
}

async function loadAreas(zoneId: number) {
    if (!zoneId) return;
    isLoadingAreas.value = true;
    try {
        const data = await api.get(`/sales/courier/areas?zone_id=${zoneId}`);
        areas.value = data;
        if (!areas.value.some((a) => a.area_id === selectedAreaId.value)) {
            selectedAreaId.value = areas.value[0]?.area_id ?? null;
            areaName.value = areas.value[0]?.area_name ?? '';
        }
    } finally {
        isLoadingAreas.value = false;
    }
}

function handleCityChange(event: Event) {
    const val = parseInt((event.target as HTMLSelectElement).value, 10);
    selectedCityId.value = val;
    const found = props.cities.find((c) => c.city_id === val);
    cityName.value = found ? found.city_name : '';
    loadZones(val);
}

function handleZoneChange(event: Event) {
    const val = parseInt((event.target as HTMLSelectElement).value, 10);
    selectedZoneId.value = val;
    const found = zones.value.find((z) => z.zone_id === val);
    zoneName.value = found ? found.zone_name : '';
    loadAreas(val);
}

function handleAreaChange(event: Event) {
    const val = parseInt((event.target as HTMLSelectElement).value, 10);
    selectedAreaId.value = val;
    const found = areas.value.find((a) => a.area_id === val);
    areaName.value = found ? found.area_name : '';
}

// Color Variant Modal State
const isVariantModalOpen = ref(false);
const variantModalProduct = ref<ProductItem | null>(null);

function openVariantModal(product: ProductItem) {
    errorMessage.value = '';
    variantModalProduct.value = product;
    isVariantModalOpen.value = true;
}

function closeVariantModal() {
    isVariantModalOpen.value = false;
    variantModalProduct.value = null;
}

function getVariantCartQty(variantId: number): number {
    const found = cart.value.find((i) => !i.is_custom && i.product_variant_id === variantId);
    return found ? found.qty : 0;
}

function addVariantToCart(product: ProductItem, variant: ProductVariantOption) {
    errorMessage.value = '';
    if (!variant.is_active) {
        errorMessage.value = `Variant '${variant.color_name || variant.attribute_value}' is currently inactive.`;
        return;
    }

    const existing = cart.value.find((item) => !item.is_custom && item.product_id === product.id && item.product_variant_id === variant.id);
    if (existing) {
        if (!props.allowNegativeStock && existing.qty + 1 > variant.stock_qty) {
            errorMessage.value = `Cannot add more '${product.name} (${variant.color_name || variant.attribute_value})'. Max available stock is ${variant.stock_qty}.`;
            return;
        }
        existing.qty += 1;
    } else {
        if (!props.allowNegativeStock && variant.stock_qty < 1) {
            errorMessage.value = `'${product.name} (${variant.color_name || variant.attribute_value})' is out of stock.`;
            return;
        }
        cart.value.push({
            is_custom: false,
            product_id: product.id,
            product_variant_id: variant.id,
            variant_name: variant.color_name || variant.attribute_value,
            variant_sku: variant.sku,
            name: product.name,
            sku: variant.sku || product.sku,
            qty: 1,
            unit_price: variant.selling_price || product.selling_price,
            unit_cost: variant.cost_price ?? product.cost_price ?? undefined,
            available_stock: variant.stock_qty,
        });
    }
}

// Add Product to Cart
function addProductToCart(product: ProductItem) {
    errorMessage.value = '';
    if (product.has_variants && product.variants && product.variants.length > 0) {
        openVariantModal(product);
        return;
    }

    const existing = cart.value.find((item) => !item.is_custom && item.product_id === product.id && !item.product_variant_id);
    if (existing) {
        if (!props.allowNegativeStock && existing.qty + 1 > product.stock_qty) {
            errorMessage.value = `Cannot add more '${product.name}'. Max available stock is ${product.stock_qty}.`;
            return;
        }
        existing.qty += 1;
    } else {
        if (!props.allowNegativeStock && product.stock_qty < 1) {
            errorMessage.value = `'${product.name}' is out of stock.`;
            return;
        }
        cart.value.push({
            is_custom: false,
            product_id: product.id,
            product_variant_id: null,
            variant_name: null,
            variant_sku: null,
            name: product.name,
            sku: product.sku,
            qty: 1,
            unit_price: product.selling_price,
            unit_cost: product.cost_price ?? undefined,
            available_stock: product.stock_qty,
        });
    }
}

// Keyboard Enter on Search Input (Scanner friendly)
function handleSearchKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const q = searchQuery.value.trim().toLowerCase();
        if (!q) return;

        // 1. Direct exact variant SKU match (barcode scanner hit)
        for (const p of props.products) {
            if (p.variants && p.variants.length > 0) {
                const matchedVariant = p.variants.find((v) => v.sku.toLowerCase() === q);
                if (matchedVariant) {
                    addVariantToCart(p, matchedVariant);
                    searchQuery.value = '';
                    productSearchInput.value?.focus();
                    return;
                }
            }
        }

        // 2. Direct exact parent SKU match
        const exactParent = props.products.find((p) => p.sku.toLowerCase() === q);
        if (exactParent) {
            addProductToCart(exactParent);
            searchQuery.value = '';
            productSearchInput.value?.focus();
            return;
        }

        // 3. Fallback to first filtered product
        if (filteredProducts.value.length > 0) {
            addProductToCart(filteredProducts.value[0]);
            searchQuery.value = '';
            productSearchInput.value?.focus();
        }
    }
}

function updateCartQty(item: CartLine, delta: number) {
    errorMessage.value = '';
    const newQty = item.qty + delta;
    if (newQty <= 0) {
        removeFromCart(item);
        return;
    }
    if (!item.is_custom && !props.allowNegativeStock && item.available_stock !== undefined) {
        if (newQty > item.available_stock) {
            errorMessage.value = `Cannot exceed available stock of ${item.available_stock} for '${item.name}'.`;
            return;
        }
    }
    item.qty = newQty;
}

function removeFromCart(item: CartLine) {
    cart.value = cart.value.filter((i) => i !== item);
}

function addCustomItem() {
    if (!customItemName.value.trim()) return;
    const price = typeof customItemPrice.value === 'number' ? customItemPrice.value : 0;
    const cost = typeof customItemCost.value === 'number' ? customItemCost.value : 0;
    const qty = Math.max(1, customItemQty.value || 1);

    cart.value.push({
        is_custom: true,
        product_id: null,
        name: customItemName.value.trim(),
        qty,
        unit_price: price,
        unit_cost: cost,
    });

    customItemName.value = '';
    customItemPrice.value = '';
    customItemCost.value = '';
    customItemQty.value = 1;
    isCustomItemModalOpen.value = false;
}

// Payment Shortcut Helpers
function setFullPayment() {
    paidAmount.value = grandTotal.value;
    paymentMethod.value = 'cash';
}

function setCodPayment() {
    paidAmount.value = 0;
    paymentMethod.value = 'cod';
}

function setHalfPayment() {
    paidAmount.value = Math.round((grandTotal.value / 2) * 100) / 100;
    paymentMethod.value = 'bkash';
}

// Validation before Submit
function validateForm(): boolean {
    errorMessage.value = '';

    if (!customerName.value.trim()) {
        errorMessage.value = 'Customer name is required.';
        currentStep.value = 1;
        return false;
    }

    const cleanPhone = customerPhone.value.trim();
    if (!/^01[3-9]\d{8}$/.test(cleanPhone)) {
        errorMessage.value = 'Valid 11-digit Bangladeshi phone number is required (e.g. 01712345678).';
        currentStep.value = 1;
        return false;
    }

    if (customerAddress.value.trim().length < 10) {
        errorMessage.value = 'Customer address must be at least 10 characters long.';
        currentStep.value = 1;
        return false;
    }

    if (courierMode.value === 'pathao') {
        if (!selectedCityId.value || !selectedZoneId.value || !selectedAreaId.value) {
            errorMessage.value = 'Pathao City, Zone, and Area are required for courier delivery.';
            currentStep.value = 1;
            return false;
        }
        if (!itemWeight.value || itemWeight.value <= 0) {
            errorMessage.value = 'Package weight (kg) must be greater than zero.';
            currentStep.value = 1;
            return false;
        }
    }

    if (cart.value.length === 0) {
        errorMessage.value = 'At least one product or custom item must be added to the cart.';
        currentStep.value = 2;
        return false;
    }

    if (calculatedDiscount.value > subtotal.value) {
        errorMessage.value = 'Discount amount cannot exceed the subtotal.';
        currentStep.value = 3;
        return false;
    }

    if (actualPaidAmount.value > grandTotal.value) {
        errorMessage.value = 'Paid amount cannot exceed the grand total.';
        currentStep.value = 3;
        return false;
    }

    return true;
}

// Submit Sale
async function handleSaveSale() {
    if (!validateForm()) return;

    isSubmitting.value = true;
    errorMessage.value = '';

    const payload = {
        idempotency_token: idempotencyToken.value,
        customer_name: customerName.value.trim(),
        customer_phone: customerPhone.value.trim(),
        customer_alt_phone: customerAltPhone.value.trim() || null,
        customer_address: customerAddress.value.trim(),
        customer_notes: customerNotes.value.trim() || null,
        courier_mode: courierMode.value,
        city_id: courierMode.value === 'pathao' ? selectedCityId.value : null,
        zone_id: courierMode.value === 'pathao' ? selectedZoneId.value : null,
        area_id: courierMode.value === 'pathao' ? selectedAreaId.value : null,
        city_name: courierMode.value === 'pathao' ? cityName.value : null,
        zone_name: courierMode.value === 'pathao' ? zoneName.value : null,
        area_name: courierMode.value === 'pathao' ? areaName.value : null,
        item_weight: courierMode.value === 'pathao' ? itemWeight.value : null,
        delivery_type: courierMode.value === 'pathao' ? deliveryType.value : null,
        special_instruction: courierMode.value === 'pathao' ? specialInstruction.value.trim() || null : null,
        items: cart.value.map((item) => ({
            is_custom: item.is_custom,
            product_id: item.product_id,
            product_variant_id: item.product_variant_id || null,
            variant_name: item.variant_name || null,
            variant_sku: item.variant_sku || null,
            name: item.name,
            qty: item.qty,
            unit_price: item.unit_price,
            unit_cost: item.unit_cost,
        })),
        discount_type: discountType.value,
        discount_value: typeof discountValue.value === 'number' ? discountValue.value : 0,
        delivery_charge: deliveryChargeAmount.value,
        paid_amount: actualPaidAmount.value,
        payment_method: paymentMethod.value,
        payment_reference: paymentReference.value.trim() || null,
        payment_note: paymentNote.value.trim() || null,
        notes: saleNotes.value.trim() || null,
    };

    try {
        const response = await api.post('/sales', payload);
        if (response.success && response.sale) {
            successSale.value = response.sale;
            isSuccessModalOpen.value = true;
        } else {
            errorMessage.value = response.message || 'Failed to complete sale.';
        }
    } catch (err: any) {
        if (err.response?.data?.errors) {
            const firstKey = Object.keys(err.response.data.errors)[0];
            errorMessage.value = err.response.data.errors[firstKey][0];
        } else {
            errorMessage.value = err.message || 'Error occurred while saving sale.';
        }
    } finally {
        isSubmitting.value = false;
    }
}

// Reset for New Sale
function resetForNewSale() {
    isSuccessModalOpen.value = false;
    successSale.value = null;
    idempotencyToken.value = generateUuid();
    cart.value = [];
    customerPhone.value = '';
    customerName.value = '';
    customerAltPhone.value = '';
    customerAddress.value = '';
    customerNotes.value = '';
    isExistingCustomer.value = false;
    customerStats.value = null;
    successRateHint.value = null;
    discountValue.value = 0;
    deliveryCharge.value = 0;
    paidAmount.value = 0;
    paymentMethod.value = 'cod';
    paymentReference.value = '';
    paymentNote.value = '';
    saleNotes.value = '';
    errorMessage.value = '';
    currentStep.value = 1;
    productSearchInput.value?.focus();
}

function handlePrintInvoice() {
    if (!successSale.value) return;
    window.open(`/sales/invoices/${successSale.value.id}/print`, '_blank');
}

onMounted(() => {
    if (props.cities.length > 0) {
        loadZones(props.cities[0].city_id);
    }
    productSearchInput.value?.focus();
});
</script>

<template>
    <Head title="New Sale (POS)" />

        <div class="max-w-[1560px] mx-auto space-y-4">
            <!-- Header -->
            <PageHeader
                title="POS — New Sale"
                subtitle="Fast text-only sales terminal with real-time stock locking and automated monthly invoicing"
            >
                <template #actions>
                    <div class="flex items-center gap-2">
                        <span class="text-xs px-2.5 py-1 bg-[#EBEFEE] text-[#4A5957] font-mono border border-[#D5DCDB]">
                            TOKEN: {{ idempotencyToken.substring(0, 8) }}…
                        </span>
                        <AppButton
                            variant="secondary"
                            size="sm"
                            @click="isCustomItemModalOpen = true"
                        >
                            <AppIcon name="plus" :size="16" class="mr-1" />
                            Custom Item
                        </AppButton>
                    </div>
                </template>
            </PageHeader>

            <!-- Error Banner -->
            <div
                v-if="errorMessage"
                class="p-3 bg-[#FDECEA] border-l-4 border-[#B42318] text-[#8E1B12] text-sm flex items-center justify-between"
            >
                <div class="flex items-center gap-2">
                    <AppIcon name="danger" :size="16" />
                    <span>{{ errorMessage }}</span>
                </div>
                <button
                    class="text-xs underline hover:text-black"
                    @click="errorMessage = ''"
                >
                    Dismiss
                </button>
            </div>

            <!-- Mobile Guided Stepper (<1024px) -->
            <div class="lg:hidden flex border border-[#D5DCDB] bg-white divide-x divide-[#D5DCDB] text-xs font-medium">
                <button
                    type="button"
                    class="flex-1 py-2.5 px-3 text-center transition-colors flex items-center justify-center gap-1.5"
                    :class="currentStep === 1 ? 'bg-[#E6F2EF] text-[#0B6E5E] font-bold border-b-2 border-[#0B6E5E]' : 'text-[#4A5957]'"
                    @click="currentStep = 1"
                >
                    <span class="w-4 h-4 rounded-none bg-[#0B6E5E] text-white inline-flex items-center justify-center text-[10px]">1</span>
                    Customer
                </button>
                <button
                    type="button"
                    class="flex-1 py-2.5 px-3 text-center transition-colors flex items-center justify-center gap-1.5"
                    :class="currentStep === 2 ? 'bg-[#E6F2EF] text-[#0B6E5E] font-bold border-b-2 border-[#0B6E5E]' : 'text-[#4A5957]'"
                    @click="currentStep = 2"
                >
                    <span class="w-4 h-4 rounded-none bg-[#0B6E5E] text-white inline-flex items-center justify-center text-[10px]">2</span>
                    Cart ({{ cart.length }})
                </button>
                <button
                    type="button"
                    class="flex-1 py-2.5 px-3 text-center transition-colors flex items-center justify-center gap-1.5"
                    :class="currentStep === 3 ? 'bg-[#E6F2EF] text-[#0B6E5E] font-bold border-b-2 border-[#0B6E5E]' : 'text-[#4A5957]'"
                    @click="currentStep = 3"
                >
                    <span class="w-4 h-4 rounded-none bg-[#0B6E5E] text-white inline-flex items-center justify-center text-[10px]">3</span>
                    Pay ({{ formatMoney(grandTotal) }})
                </button>
            </div>

            <!-- Main Layout Grid: Desktop 2-Column -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                <!-- LEFT COLUMN: Product Catalogue (Desktop Always, Mobile on Step 2) -->
                <div
                    class="lg:col-span-6 xl:col-span-7 flex flex-col space-y-4"
                    :class="{'hidden lg:flex': currentStep !== 2}"
                >
                    <div class="bg-white border border-[#D5DCDB] p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <h2 class="text-xs uppercase font-bold tracking-wider text-[#4A5957] flex items-center gap-1.5">
                                <AppIcon name="products" :size="16" />
                                Product Catalogue (Text Only)
                            </h2>
                            <span class="text-xs text-[#5F6F6D]">
                                {{ filteredProducts.length }} items available
                            </span>
                        </div>

                        <!-- Barcode Scanner Friendly Search Bar -->
                        <div class="relative">
                            <input
                                id="pos-product-search"
                                ref="productSearchInput"
                                v-model="searchQuery"
                                type="text"
                                placeholder="Scan barcode SKU or search name… (Press Enter to add first)"
                                class="w-full h-10 px-3 pl-9 border border-[#7F8E8C] focus:border-[#0B6E5E] focus:ring-1 focus:ring-[#0B6E5E] text-sm font-normal text-[#0F1B1A] placeholder-[#5F6F6D] outline-none"
                                @keydown="handleSearchKeydown"
                            />
                            <div class="absolute left-2.5 top-2.5 text-[#5F6F6D]">
                                <AppIcon name="search" :size="16" />
                            </div>
                            <button
                                v-if="searchQuery"
                                type="button"
                                class="absolute right-2.5 top-2 text-xs text-[#5F6F6D] hover:text-[#0F1B1A]"
                                @click="searchQuery = ''"
                            >
                                Clear
                            </button>
                        </div>

                        <!-- Category Filter Chips -->
                        <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs no-scrollbar">
                            <button
                                type="button"
                                class="px-2.5 py-1 border transition-colors whitespace-nowrap"
                                :class="selectedCategoryId === null ? 'bg-[#0B6E5E] text-white border-[#0B6E5E]' : 'bg-[#F4F6F6] text-[#4A5957] border-[#D5DCDB] hover:border-[#7F8E8C]'"
                                @click="selectedCategoryId = null"
                            >
                                All ({{ props.products.length }})
                            </button>
                            <button
                                v-for="cat in categories"
                                :key="cat.id"
                                type="button"
                                class="px-2.5 py-1 border transition-colors whitespace-nowrap"
                                :class="selectedCategoryId === cat.id ? 'bg-[#0B6E5E] text-white border-[#0B6E5E]' : 'bg-[#F4F6F6] text-[#4A5957] border-[#D5DCDB] hover:border-[#7F8E8C]'"
                                @click="selectedCategoryId = cat.id"
                            >
                                {{ cat.name }}
                            </button>
                        </div>

                        <!-- Text-Only Product List Table -->
                        <div class="border border-[#D5DCDB] overflow-y-auto max-h-[520px]">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead class="bg-[#EBEFEE] sticky top-0 border-b border-[#D5DCDB] text-[#4A5957]">
                                    <tr>
                                        <th class="py-2 px-3 font-semibold">Product & SKU</th>
                                        <th class="py-2 px-3 font-semibold text-right">Price</th>
                                        <th class="py-2 px-3 font-semibold text-center">Stock</th>
                                        <th class="py-2 px-3 font-semibold text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#D5DCDB]">
                                    <tr
                                        v-for="p in filteredProducts"
                                        :key="p.id"
                                        class="hover:bg-[#F0F7F5] cursor-pointer transition-colors"
                                        @click="addProductToCart(p)"
                                    >
                                        <td class="py-2 px-3">
                                            <div class="font-medium text-[#0F1B1A] flex items-center gap-1.5 flex-wrap">
                                                <span>{{ p.name }}</span>
                                                <span
                                                    v-if="p.has_variants && p.variants?.length"
                                                    class="px-1.5 py-0.2 text-[10px] font-bold bg-[#E6F2EF] text-[#074538] border border-[#BFDDD6]"
                                                >
                                                    🎨 {{ p.variants.length }} Colors
                                                </span>
                                            </div>
                                            <div class="text-[11px] font-mono text-[#5F6F6D]">
                                                {{ p.sku }}
                                                <span v-if="p.category_name" class="ml-1 text-[#7F8E8C]">· {{ p.category_name }}</span>
                                            </div>
                                            <div v-if="p.has_variants && p.variants?.length" class="flex flex-wrap gap-1 mt-1">
                                                <span
                                                    v-for="v in p.variants"
                                                    :key="v.id"
                                                    class="inline-block text-[10px] px-1 py-0.2 bg-[#F4F6F6] text-[#4A5957] border border-[#D5DCDB]"
                                                >
                                                    {{ v.color_name || v.attribute_value }} ({{ v.stock_qty }})
                                                </span>
                                            </div>
                                        </td>
                                        <td class="py-2 px-3 text-right font-medium text-[#0F1B1A] tabular-nums">
                                            {{ formatMoney(p.selling_price) }}
                                        </td>
                                        <td class="py-2 px-3 text-center">
                                            <span
                                                v-if="p.stock_qty <= 0"
                                                class="px-1.5 py-0.5 text-[10px] font-bold bg-[#FDECEA] text-[#B42318] border border-[#B42318]"
                                            >
                                                Out of Stock ({{ p.stock_qty }})
                                            </span>
                                            <span
                                                v-else-if="p.is_low_stock"
                                                class="px-1.5 py-0.5 text-[10px] font-bold bg-[#FFF4DB] text-[#B26A00] border border-[#B26A00]"
                                            >
                                                Low ({{ p.stock_qty }})
                                            </span>
                                            <span
                                                v-else
                                                class="px-1.5 py-0.5 text-[10px] font-medium bg-[#E7F4EA] text-[#14602C] border border-[#14602C]"
                                            >
                                                {{ p.stock_qty }} pcs{{ p.has_variants ? ' (total)' : '' }}
                                            </span>
                                        </td>
                                        <td class="py-2 px-3 text-right">
                                            <button
                                                v-if="p.has_variants && p.variants?.length"
                                                type="button"
                                                class="px-2.5 py-1 text-xs bg-[#E6F2EF] hover:bg-[#0B6E5E] hover:text-white border border-[#0B6E5E] text-[#074538] font-bold transition-colors inline-flex items-center gap-1"
                                                @click.stop="openVariantModal(p)"
                                            >
                                                Select Color
                                            </button>
                                            <button
                                                v-else
                                                type="button"
                                                class="px-2.5 py-1 text-xs bg-white hover:bg-[#0B6E5E] hover:text-white border border-[#0B6E5E] text-[#0B6E5E] font-medium transition-colors"
                                                @click.stop="addProductToCart(p)"
                                            >
                                                + Add
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="filteredProducts.length === 0">
                                        <td colspan="4" class="py-8 text-center text-[#5F6F6D]">
                                            No matching catalogue products found.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN: Customer, Cart & Payment -->
                <div class="lg:col-span-6 xl:col-span-5 space-y-4">
                    <!-- SECTION 1: Customer Details (Desktop Always, Mobile on Step 1) -->
                    <div
                        class="bg-white border border-[#D5DCDB] p-4 space-y-3"
                        :class="{'hidden lg:block': currentStep !== 1}"
                    >
                        <div class="flex items-center justify-between border-b border-[#D5DCDB] pb-2">
                            <h2 class="text-xs uppercase font-bold tracking-wider text-[#4A5957] flex items-center gap-1.5">
                                <AppIcon name="user" :size="16" />
                                1. Customer & Delivery
                            </h2>
                            <div v-if="isExistingCustomer" class="flex items-center gap-1">
                                <StatusBadge tone="success" label="Existing Buyer" icon="check-circle" />
                            </div>
                            <div v-else class="flex items-center gap-1">
                                <StatusBadge tone="info" label="New Customer" icon="user" />
                            </div>
                        </div>

                        <!-- Phone-first Search Input -->
                        <div>
                            <FormField
                                label="Customer Phone (11 digits BD)"
                                required
                                hint="Type Bangladeshi phone (e.g. 01712345678) to auto-fill"
                            >
                                <div class="relative">
                                    <input
                                        v-model="customerPhone"
                                        type="tel"
                                        placeholder="01XXXXXXXXX"
                                        maxlength="11"
                                        class="w-full h-10 px-3 pl-8 font-mono border border-[#7F8E8C] focus:border-[#0B6E5E] focus:ring-1 focus:ring-[#0B6E5E] text-sm text-[#0F1B1A] outline-none"
                                    />
                                    <div class="absolute left-2.5 top-2.5 text-[#5F6F6D]">
                                        <AppIcon name="phone" :size="16" />
                                    </div>
                                    <div v-if="isLookingUpCustomer" class="absolute right-2.5 top-2.5 text-xs text-[#0B6E5E]">
                                        Looking up…
                                    </div>
                                </div>
                            </FormField>
                        </div>

                        <!-- Customer Success Rate & Lifetime Stats Banner -->
                        <div
                            v-if="isExistingCustomer && customerStats"
                            class="p-2.5 bg-[#F0F7F5] border border-[#BFDDD6] text-xs space-y-1"
                        >
                            <div class="flex items-center justify-between font-medium text-[#074538]">
                                <span>History: {{ customerStats.total_orders }} orders completed</span>
                                <span>Total spent: {{ formatMoney(customerStats.total_spent) }}</span>
                            </div>
                            <div v-if="customerStats.outstanding_due > 0" class="text-[#B42318] font-bold">
                                ⚠ Outstanding Due: {{ formatMoney(customerStats.outstanding_due) }}
                            </div>
                        </div>

                        <div
                            v-if="props.showSuccessRate && successRateHint"
                            class="p-2 bg-[#E8F0FA] border border-[#1D5FA8] text-xs flex items-center justify-between text-[#174B85]"
                        >
                            <span>Pathao Delivery Score:</span>
                            <span class="font-bold">{{ successRateHint.success_rate || successRateHint.label }}</span>
                        </div>

                        <!-- Customer Name & Alt Phone -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <FormField label="Full Name" required>
                                <input
                                    v-model="customerName"
                                    type="text"
                                    placeholder="Customer name"
                                    class="w-full h-9 px-3 border border-[#7F8E8C] focus:border-[#0B6E5E] text-sm outline-none"
                                />
                            </FormField>
                            <FormField label="Alt Phone (Optional)">
                                <input
                                    v-model="customerAltPhone"
                                    type="tel"
                                    placeholder="Alternate number"
                                    maxlength="11"
                                    class="w-full h-9 px-3 font-mono border border-[#7F8E8C] focus:border-[#0B6E5E] text-sm outline-none"
                                />
                            </FormField>
                        </div>

                        <!-- Address -->
                        <FormField label="Delivery Address (min 10 chars)" required>
                            <textarea
                                v-model="customerAddress"
                                rows="2"
                                placeholder="House, Road, Area, Detailed delivery address…"
                                class="w-full p-2 border border-[#7F8E8C] focus:border-[#0B6E5E] text-sm outline-none"
                            ></textarea>
                        </FormField>

                        <!-- Courier Delivery Options -->
                        <div class="pt-2 border-t border-[#D5DCDB] space-y-3">
                            <div class="flex items-center gap-4 text-xs">
                                <label class="font-bold text-[#4A5957]">Delivery Mode:</label>
                                <label class="flex items-center gap-1.5 cursor-pointer">
                                    <input
                                        v-model="courierMode"
                                        type="radio"
                                        value="pathao"
                                        class="accent-[#0B6E5E]"
                                    />
                                    <span>Send via Pathao</span>
                                </label>
                                <label class="flex items-center gap-1.5 cursor-pointer">
                                    <input
                                        v-model="courierMode"
                                        type="radio"
                                        value="none"
                                        class="accent-[#0B6E5E]"
                                    />
                                    <span>Hand-delivered / Store Pickup</span>
                                </label>
                            </div>

                            <!-- Cascading Pathao Locations -->
                            <div v-if="courierMode === 'pathao'" class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs">
                                <div>
                                    <label class="block text-[#4A5957] mb-1 font-medium">City *</label>
                                    <select
                                        :value="selectedCityId"
                                        class="w-full h-8 px-2 border border-[#7F8E8C] focus:border-[#0B6E5E] bg-white text-xs outline-none"
                                        @change="handleCityChange"
                                    >
                                        <option v-for="c in cities" :key="c.city_id" :value="c.city_id">
                                            {{ c.city_name }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[#4A5957] mb-1 font-medium">
                                        Zone * <span v-if="isLoadingZones" class="text-[10px] text-[#0B6E5E]">…</span>
                                    </label>
                                    <select
                                        :value="selectedZoneId"
                                        class="w-full h-8 px-2 border border-[#7F8E8C] focus:border-[#0B6E5E] bg-white text-xs outline-none"
                                        @change="handleZoneChange"
                                    >
                                        <option v-for="z in zones" :key="z.zone_id" :value="z.zone_id">
                                            {{ z.zone_name }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[#4A5957] mb-1 font-medium">
                                        Area * <span v-if="isLoadingAreas" class="text-[10px] text-[#0B6E5E]">…</span>
                                    </label>
                                    <select
                                        :value="selectedAreaId"
                                        class="w-full h-8 px-2 border border-[#7F8E8C] focus:border-[#0B6E5E] bg-white text-xs outline-none"
                                        @change="handleAreaChange"
                                    >
                                        <option v-for="a in areas" :key="a.area_id" :value="a.area_id">
                                            {{ a.area_name }}
                                        </option>
                                    </select>
                                </div>
                                <div class="sm:col-span-1">
                                    <label class="block text-[#4A5957] mb-1 font-medium">Weight (kg)</label>
                                    <input
                                        v-model="itemWeight"
                                        type="number"
                                        step="0.1"
                                        min="0.1"
                                        class="w-full h-8 px-2 border border-[#7F8E8C] focus:border-[#0B6E5E] text-xs outline-none tabular-nums"
                                    />
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-[#4A5957] mb-1 font-medium">Special Instruction</label>
                                    <input
                                        v-model="specialInstruction"
                                        type="text"
                                        placeholder="e.g. Call before delivery, fragile leather"
                                        class="w-full h-8 px-2 border border-[#7F8E8C] focus:border-[#0B6E5E] text-xs outline-none"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Mobile Next Button to Step 2 -->
                        <div class="lg:hidden pt-2">
                            <AppButton
                                variant="primary"
                                class="w-full"
                                @click="currentStep = 2"
                            >
                                Next: Select Products & Cart →
                            </AppButton>
                        </div>
                    </div>

                    <!-- SECTION 2: Cart Lines (Desktop Always, Mobile on Step 2) -->
                    <div
                        class="bg-white border border-[#D5DCDB] p-4 space-y-3"
                        :class="{'hidden lg:block': currentStep !== 2}"
                    >
                        <div class="flex items-center justify-between border-b border-[#D5DCDB] pb-2">
                            <h2 class="text-xs uppercase font-bold tracking-wider text-[#4A5957] flex items-center gap-1.5">
                                <AppIcon name="cart" :size="16" />
                                2. Cart Items ({{ cart.length }})
                            </h2>
                            <button
                                v-if="cart.length > 0"
                                type="button"
                                class="text-xs text-[#B42318] hover:underline"
                                @click="cart = []"
                            >
                                Clear All
                            </button>
                        </div>

                        <!-- Cart Line Items Table -->
                        <div v-if="cart.length > 0" class="border border-[#D5DCDB] max-h-[300px] overflow-y-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead class="bg-[#EBEFEE] sticky top-0 border-b border-[#D5DCDB] text-[#4A5957]">
                                    <tr>
                                        <th class="py-2 px-2.5 font-semibold">Item</th>
                                        <th class="py-2 px-2.5 font-semibold text-center w-24">Qty</th>
                                        <th class="py-2 px-2.5 font-semibold text-right w-24">Price</th>
                                        <th class="py-2 px-2.5 font-semibold text-right w-24">Total</th>
                                        <th class="py-2 px-2 text-center w-8"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#D5DCDB]">
                                    <tr
                                        v-for="item in cart"
                                        :key="item.is_custom ? ('c_' + item.name) : (item.product_variant_id ? ('v_' + item.product_variant_id) : ('p_' + item.product_id))"
                                        class="hover:bg-[#F0F7F5]"
                                    >
                                        <td class="py-2 px-2.5">
                                            <div class="font-medium text-[#0F1B1A] flex items-center gap-1.5 flex-wrap">
                                                <span>{{ item.name }}</span>
                                                <span
                                                    v-if="item.variant_name"
                                                    class="px-1.5 py-0.2 text-[10px] font-bold bg-[#E6F2EF] text-[#074538] border border-[#BFDDD6]"
                                                >
                                                    🎨 {{ item.variant_name }}
                                                </span>
                                                <span v-if="item.is_custom" class="px-1 text-[9px] bg-[#EBEFEE] text-[#4A5957] border border-[#D5DCDB]">
                                                    Custom
                                                </span>
                                            </div>
                                            <div v-if="item.sku" class="text-[10px] font-mono text-[#5F6F6D]">
                                                {{ item.sku }}
                                            </div>
                                        </td>
                                        <td class="py-2 px-2.5 text-center">
                                            <div class="inline-flex items-center border border-[#7F8E8C]">
                                                <button
                                                    type="button"
                                                    class="w-6 h-6 bg-[#EBEFEE] hover:bg-[#D5DCDB] text-xs font-bold flex items-center justify-center"
                                                    @click="updateCartQty(item, -1)"
                                                >
                                                    −
                                                </button>
                                                <span class="w-7 text-center font-medium tabular-nums">{{ item.qty }}</span>
                                                <button
                                                    type="button"
                                                    class="w-6 h-6 bg-[#EBEFEE] hover:bg-[#D5DCDB] text-xs font-bold flex items-center justify-center"
                                                    @click="updateCartQty(item, 1)"
                                                >
                                                    +
                                                </button>
                                            </div>
                                        </td>
                                        <td class="py-2 px-2.5 text-right tabular-nums">
                                            <input
                                                v-if="props.canEditPrice"
                                                v-model.number="item.unit_price"
                                                type="number"
                                                step="1"
                                                min="0"
                                                class="w-20 h-7 text-right px-1.5 border border-[#7F8E8C] focus:border-[#0B6E5E] text-xs outline-none tabular-nums"
                                            />
                                            <span v-else class="font-medium">{{ formatMoney(item.unit_price) }}</span>
                                        </td>
                                        <td class="py-2 px-2.5 text-right font-medium tabular-nums text-[#0F1B1A]">
                                            {{ formatMoney(item.qty * item.unit_price) }}
                                        </td>
                                        <td class="py-2 px-2 text-center">
                                            <button
                                                type="button"
                                                class="text-[#B42318] hover:text-black font-bold text-sm"
                                                title="Remove"
                                                @click="removeFromCart(item)"
                                            >
                                                ×
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="p-6 text-center text-xs text-[#5F6F6D] border border-dashed border-[#D5DCDB]">
                            Cart is empty. Click "+ Add" on product catalogue or "Custom Item" above.
                        </div>

                        <!-- Mobile Next Button to Step 3 -->
                        <div class="lg:hidden pt-2 flex gap-2">
                            <AppButton
                                variant="secondary"
                                class="flex-1"
                                @click="currentStep = 1"
                            >
                                ← Customer
                            </AppButton>
                            <AppButton
                                variant="primary"
                                class="flex-1"
                                :disabled="cart.length === 0"
                                @click="currentStep = 3"
                            >
                                Next: Payment →
                            </AppButton>
                        </div>
                    </div>

                    <!-- SECTION 3: Totals & Payment (Desktop Always, Mobile on Step 3) -->
                    <div
                        class="bg-white border border-[#D5DCDB] p-4 space-y-4"
                        :class="{'hidden lg:block': currentStep !== 3}"
                    >
                        <div class="border-b border-[#D5DCDB] pb-2">
                            <h2 class="text-xs uppercase font-bold tracking-wider text-[#4A5957] flex items-center gap-1.5">
                                <AppIcon name="receipt" :size="16" />
                                3. Totals & Payment
                            </h2>
                        </div>

                        <!-- Subtotal & Discount & Delivery Charge Grid -->
                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between py-1 text-[#4A5957]">
                                <span>Subtotal:</span>
                                <span class="font-medium text-[#0F1B1A] tabular-nums">{{ formatMoney(subtotal) }}</span>
                            </div>

                            <!-- Discount Controls -->
                            <div class="grid grid-cols-12 gap-2 items-center bg-[#F4F6F6] p-2 border border-[#D5DCDB]">
                                <div class="col-span-5 font-medium text-[#4A5957]">
                                    Discount:
                                </div>
                                <div class="col-span-3 flex border border-[#7F8E8C]">
                                    <button
                                        type="button"
                                        class="flex-1 py-1 text-center font-bold"
                                        :class="discountType === 'flat' ? 'bg-[#0B6E5E] text-white' : 'bg-white text-[#4A5957]'"
                                        @click="discountType = 'flat'"
                                    >
                                        ৳
                                    </button>
                                    <button
                                        type="button"
                                        class="flex-1 py-1 text-center font-bold"
                                        :class="discountType === 'percent' ? 'bg-[#0B6E5E] text-white' : 'bg-white text-[#4A5957]'"
                                        @click="discountType = 'percent'"
                                    >
                                        %
                                    </button>
                                </div>
                                <div class="col-span-4">
                                    <input
                                        v-model.number="discountValue"
                                        type="number"
                                        step="1"
                                        min="0"
                                        placeholder="0"
                                        class="w-full h-7 px-2 text-right border border-[#7F8E8C] focus:border-[#0B6E5E] text-xs outline-none tabular-nums"
                                    />
                                </div>
                            </div>

                            <!-- Delivery Charge -->
                            <div class="flex items-center justify-between bg-[#F4F6F6] p-2 border border-[#D5DCDB]">
                                <span class="font-medium text-[#4A5957]">Customer Delivery Charge:</span>
                                <input
                                    v-model.number="deliveryCharge"
                                    type="number"
                                    step="1"
                                    min="0"
                                    placeholder="0"
                                    class="w-24 h-7 px-2 text-right border border-[#7F8E8C] focus:border-[#0B6E5E] text-xs outline-none tabular-nums"
                                />
                            </div>

                            <!-- Grand Total Highlight Box -->
                            <div class="flex justify-between items-center p-3 bg-[#E6F2EF] border-2 border-[#0B6E5E] text-[#074538]">
                                <span class="text-sm font-bold uppercase tracking-wider">Grand Total:</span>
                                <span class="text-2xl font-bold font-mono tabular-nums text-[#074538]">
                                    {{ formatMoney(grandTotal) }}
                                </span>
                            </div>
                        </div>

                        <!-- Payment Controls -->
                        <div class="space-y-3 pt-2 border-t border-[#D5DCDB]">
                            <div class="flex items-center justify-between text-xs">
                                <span class="font-bold text-[#4A5957]">Payment Shortcuts:</span>
                                <div class="flex gap-1.5">
                                    <button
                                        type="button"
                                        class="px-2 py-0.5 border border-[#0B6E5E] text-[#0B6E5E] hover:bg-[#E6F2EF] text-[11px] font-medium"
                                        @click="setFullPayment"
                                    >
                                        Full Paid
                                    </button>
                                    <button
                                        type="button"
                                        class="px-2 py-0.5 border border-[#0B6E5E] text-[#0B6E5E] hover:bg-[#E6F2EF] text-[11px] font-medium"
                                        @click="setHalfPayment"
                                    >
                                        Half (50%)
                                    </button>
                                    <button
                                        type="button"
                                        class="px-2 py-0.5 border border-[#B26A00] text-[#B26A00] hover:bg-[#FFF4DB] text-[11px] font-medium"
                                        @click="setCodPayment"
                                    >
                                        COD (৳0 Paid)
                                    </button>
                                </div>
                            </div>

                            <!-- Paid Amount Input -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                <div>
                                    <label class="block text-[#4A5957] mb-1 font-medium">Amount Paid Now (৳)</label>
                                    <input
                                        v-model.number="paidAmount"
                                        type="number"
                                        step="1"
                                        min="0"
                                        :max="grandTotal"
                                        class="w-full h-9 px-3 border border-[#7F8E8C] focus:border-[#0B6E5E] text-sm outline-none tabular-nums font-bold"
                                    />
                                </div>
                                <div>
                                    <label class="block text-[#4A5957] mb-1 font-medium">Payment Method</label>
                                    <select
                                        v-model="paymentMethod"
                                        class="w-full h-9 px-2 border border-[#7F8E8C] focus:border-[#0B6E5E] bg-white text-xs outline-none"
                                    >
                                        <option value="cod">Cash on Delivery (COD)</option>
                                        <option value="cash">Cash (In-person)</option>
                                        <option value="bkash">bKash (Mobile Banking)</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Balance Due / COD collection amount display -->
                            <div class="p-2.5 border flex items-center justify-between text-xs" :class="balanceDue > 0 ? 'bg-[#FFF4DB] border-[#B26A00] text-[#8A5A00]' : 'bg-[#E7F4EA] border-[#14602C] text-[#14602C]'">
                                <div>
                                    <span class="font-bold">Balance Due:</span>
                                    <span v-if="courierMode === 'pathao' && paymentMethod === 'cod'" class="ml-1 text-[11px]">
                                        (Pathao will collect: {{ formatMoney(balanceDue) }})
                                    </span>
                                </div>
                                <span class="font-mono text-base font-bold tabular-nums">
                                    {{ formatMoney(balanceDue) }}
                                </span>
                            </div>

                            <!-- Optional Transaction Reference / Note -->
                            <div v-if="paymentMethod !== 'cash' && paymentMethod !== 'cod'" class="grid grid-cols-2 gap-2 text-xs">
                                <div>
                                    <label class="block text-[#5F6F6D] mb-1">bKash / Bank Trx ID</label>
                                    <input
                                        v-model="paymentReference"
                                        type="text"
                                        placeholder="Trx ID"
                                        class="w-full h-8 px-2 border border-[#7F8E8C] text-xs outline-none"
                                    />
                                </div>
                                <div>
                                    <label class="block text-[#5F6F6D] mb-1">Payment Note</label>
                                    <input
                                        v-model="paymentNote"
                                        type="text"
                                        placeholder="Optional note"
                                        class="w-full h-8 px-2 border border-[#7F8E8C] text-xs outline-none"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Sticky Action Button -->
                        <div class="pt-3 border-t border-[#D5DCDB] space-y-2">
                            <AppButton
                                variant="primary"
                                size="lg"
                                class="w-full h-12 text-base font-bold tracking-wide"
                                :disabled="isSubmitting || cart.length === 0"
                                :loading="isSubmitting"
                                @click="handleSaveSale"
                            >
                                <AppIcon name="check" :size="16" class="mr-2" />
                                Save sale · {{ formatMoney(grandTotal) }}
                            </AppButton>

                            <p class="text-[11px] text-[#5F6F6D] text-center">
                                Concurrency-safe atomic transaction. Invoice will reserve sequential monthly number.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Custom Item Modal Dialog -->
        <div
            v-if="isCustomItemModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-[#0F1B1A]/40"
        >
            <div class="w-full max-w-md bg-white border border-[#D5DCDB] p-5 space-y-4 shadow-xl">
                <div class="flex items-center justify-between border-b border-[#D5DCDB] pb-3">
                    <h3 class="text-sm font-bold text-[#0F1B1A]">Add Custom Made-to-Order Item</h3>
                    <button
                        type="button"
                        class="text-sm font-bold text-[#5F6F6D] hover:text-black"
                        @click="isCustomItemModalOpen = false"
                    >
                        ×
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <FormField label="Item Name / Description" required>
                        <input
                            v-model="customItemName"
                            type="text"
                            placeholder="e.g. Custom Monogrammed Cardholder (Tan)"
                            class="w-full h-9 px-3 border border-[#7F8E8C] focus:border-[#0B6E5E] text-sm outline-none"
                        />
                    </FormField>

                    <div class="grid grid-cols-2 gap-3">
                        <FormField label="Selling Price (৳)" required>
                            <input
                                v-model.number="customItemPrice"
                                type="number"
                                step="1"
                                min="0"
                                placeholder="0.00"
                                class="w-full h-9 px-3 border border-[#7F8E8C] focus:border-[#0B6E5E] text-sm outline-none tabular-nums"
                            />
                        </FormField>

                        <FormField label="Quantity (pcs)" required>
                            <input
                                v-model.number="customItemQty"
                                type="number"
                                step="1"
                                min="1"
                                class="w-full h-9 px-3 border border-[#7F8E8C] focus:border-[#0B6E5E] text-sm outline-none tabular-nums"
                            />
                        </FormField>
                    </div>

                    <FormField
                        label="Manual Cost (৳) — Optional"
                        hint="Preserved for P&L COGS. Defaults to ৳0.00 if omitted."
                    >
                        <input
                            v-model.number="customItemCost"
                            type="number"
                            step="1"
                            min="0"
                            placeholder="0.00"
                            class="w-full h-9 px-3 border border-[#7F8E8C] focus:border-[#0B6E5E] text-sm outline-none tabular-nums"
                        />
                    </FormField>

                    <p class="text-[11px] text-[#5F6F6D]">
                        Note: Custom items do not deduct from catalogue product stock.
                    </p>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-[#D5DCDB]">
                    <AppButton
                        variant="secondary"
                        size="sm"
                        @click="isCustomItemModalOpen = false"
                    >
                        Cancel
                    </AppButton>
                    <AppButton
                        variant="primary"
                        size="sm"
                        :disabled="!customItemName.trim() || customItemPrice === ''"
                        @click="addCustomItem"
                    >
                        Add to Cart
                    </AppButton>
                </div>
            </div>
        </div>

        <!-- Color Variant Selection Modal -->
        <div
            v-if="isVariantModalOpen && variantModalProduct"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-[#0F1B1A]/40"
            @click.self="closeVariantModal"
        >
            <div class="w-full max-w-xl bg-white border border-[#D5DCDB] p-5 space-y-4 shadow-xl">
                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b border-[#D5DCDB] pb-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 bg-[#0B6E5E] inline-block"></span>
                            <h3 class="font-bold text-sm text-[#0F1B1A]">Select Color Variation</h3>
                        </div>
                        <p class="text-xs text-[#5F6F6D] mt-0.5">
                            {{ variantModalProduct.name }} · SKU: <span class="font-mono">{{ variantModalProduct.sku }}</span>
                        </p>
                    </div>
                    <button
                        type="button"
                        class="text-gray-400 hover:text-black font-bold text-lg leading-none"
                        @click="closeVariantModal"
                    >
                        ×
                    </button>
                </div>

                <!-- Product Summary bar -->
                <div class="p-2.5 bg-[#F4F6F6] border border-[#D5DCDB] flex items-center justify-between text-xs">
                    <div>
                        <span class="text-[#5F6F6D]">Total Available Across Colors:</span>
                        <span class="font-bold ml-1 text-[#0F1B1A]">{{ variantModalProduct.stock_qty }} pcs</span>
                    </div>
                    <div>
                        <span class="text-[#5F6F6D]">Base Price:</span>
                        <span class="font-bold ml-1 text-[#0F1B1A] tabular-nums">{{ formatMoney(variantModalProduct.selling_price) }}</span>
                    </div>
                </div>

                <!-- Variants List Table -->
                <div class="border border-[#D5DCDB] max-h-72 overflow-y-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead class="bg-[#EBEFEE] sticky top-0 border-b border-[#D5DCDB] text-[#4A5957]">
                            <tr>
                                <th class="py-2 px-3 font-semibold">Color & SKU</th>
                                <th class="py-2 px-3 font-semibold text-right">Price</th>
                                <th class="py-2 px-3 font-semibold text-center">Stock</th>
                                <th class="py-2 px-3 font-semibold text-center">In Cart</th>
                                <th class="py-2 px-3 font-semibold text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#D5DCDB]">
                            <tr
                                v-for="v in variantModalProduct.variants"
                                :key="v.id"
                                class="hover:bg-[#F0F7F5] transition-colors"
                            >
                                <td class="py-2.5 px-3">
                                    <div class="font-bold text-[#0F1B1A] flex items-center gap-1.5">
                                        <span class="w-2.5 h-2.5 rounded-full border border-gray-400 inline-block bg-[#0B6E5E]"></span>
                                        <span>{{ v.color_name || v.attribute_value }}</span>
                                    </div>
                                    <div class="text-[11px] font-mono text-[#5F6F6D]">
                                        {{ v.sku }}
                                    </div>
                                </td>
                                <td class="py-2.5 px-3 text-right font-medium text-[#0F1B1A] tabular-nums">
                                    {{ formatMoney(v.selling_price) }}
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <span
                                        v-if="v.stock_qty <= 0"
                                        class="px-1.5 py-0.5 text-[10px] font-bold bg-[#FDECEA] text-[#B42318] border border-[#B42318]"
                                    >
                                        Out of Stock (0)
                                    </span>
                                    <span
                                        v-else-if="v.is_low_stock"
                                        class="px-1.5 py-0.5 text-[10px] font-bold bg-[#FFF4DB] text-[#B26A00] border border-[#B26A00]"
                                    >
                                        Low ({{ v.stock_qty }})
                                    </span>
                                    <span
                                        v-else
                                        class="px-1.5 py-0.5 text-[10px] font-medium bg-[#E7F4EA] text-[#14602C] border border-[#14602C]"
                                    >
                                        {{ v.stock_qty }} pcs
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 text-center tabular-nums">
                                    <span
                                        v-if="getVariantCartQty(v.id) > 0"
                                        class="px-2 py-0.5 font-bold text-xs bg-[#E6F2EF] text-[#074538] border border-[#BFDDD6]"
                                    >
                                        {{ getVariantCartQty(v.id) }}
                                    </span>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                                <td class="py-2.5 px-3 text-right">
                                    <button
                                        type="button"
                                        :disabled="(!props.allowNegativeStock && v.stock_qty < 1) || (!props.allowNegativeStock && getVariantCartQty(v.id) >= v.stock_qty)"
                                        class="px-3 py-1 text-xs font-semibold border transition-colors"
                                        :class="(!props.allowNegativeStock && v.stock_qty < 1) || (!props.allowNegativeStock && getVariantCartQty(v.id) >= v.stock_qty)
                                            ? 'bg-gray-100 text-gray-400 border-gray-300 cursor-not-allowed'
                                            : 'bg-[#0B6E5E] text-white hover:bg-[#074538] border-[#0B6E5E] shadow-sm'"
                                        @click="addVariantToCart(variantModalProduct, v)"
                                    >
                                        + Add
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Footer with Done button -->
                <div class="flex items-center justify-between pt-3 border-t border-[#D5DCDB]">
                    <span class="text-xs text-[#5F6F6D]">
                        Click "+ Add" to add desired color units into your cart.
                    </span>
                    <AppButton
                        variant="primary"
                        size="sm"
                        @click="closeVariantModal"
                    >
                        Done
                    </AppButton>
                </div>
            </div>
        </div>

        <!-- Sale Success Confirmation Modal -->
        <div
            v-if="isSuccessModalOpen && successSale"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-[#0F1B1A]/40"
        >
            <div class="w-full max-w-lg bg-white border border-[#D5DCDB] p-6 space-y-4 shadow-2xl">
                <div class="p-3 bg-[#E7F4EA] border border-[#14602C] text-[#14602C] flex items-center gap-3">
                    <AppIcon name="check-circle" :size="20" />
                    <div>
                        <div class="font-bold text-sm">Sale Completed Successfully!</div>
                        <div class="text-xs">Invoice generated and inventory movements audited.</div>
                    </div>
                </div>

                <!-- Invoice Summary Card -->
                <div class="p-4 bg-[#F4F6F6] border border-[#D5DCDB] space-y-2 text-xs">
                    <div class="flex items-center justify-between border-b border-[#D5DCDB] pb-2">
                        <span class="text-[#5F6F6D]">Invoice Number:</span>
                        <span class="font-mono text-base font-bold text-[#0B6E5E]">{{ successSale.invoice_no }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-[#5F6F6D]">Customer:</span>
                        <span class="font-medium text-[#0F1B1A]">{{ successSale.customer_name }} ({{ formatBDPhone(successSale.customer_phone) }})</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-[#5F6F6D]">Grand Total:</span>
                        <span class="font-bold text-[#0F1B1A] tabular-nums">{{ formatMoney(successSale.grand_total) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-[#5F6F6D]">Amount Paid:</span>
                        <span class="font-medium text-[#14602C] tabular-nums">{{ formatMoney(successSale.paid_total) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-[#5F6F6D]">Balance Due:</span>
                        <span class="font-bold text-[#B42318] tabular-nums">{{ formatMoney(successSale.due_amount) }}</span>
                    </div>
                    <div v-if="successSale.courier_mode === 'pathao'" class="flex items-center justify-between border-t border-[#D5DCDB] pt-2 text-[#174B85]">
                        <span>Pathao Delivery:</span>
                        <span class="font-medium">Queued (Collect: {{ formatMoney(successSale.due_amount) }})</span>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2 pt-2">
                    <AppButton
                        variant="primary"
                        @click="resetForNewSale"
                    >
                        <AppIcon name="plus" :size="16" class="mr-1.5" />
                        Start New Sale
                    </AppButton>
                    <AppButton
                        variant="secondary"
                        @click="handlePrintInvoice"
                    >
                        <AppIcon name="printer" :size="16" class="mr-1.5" />
                        Print Invoice
                    </AppButton>
                    <a
                        :href="`/sales/invoices/${successSale.id}/pdf`"
                        target="_blank"
                        class="block"
                    >
                        <AppButton
                            variant="secondary"
                            class="w-full"
                        >
                            <AppIcon name="download" :size="16" class="mr-1.5" />
                            Download PDF
                        </AppButton>
                    </a>
                    <Link
                        :href="`/sales/invoices/${successSale.id}`"
                        class="block"
                    >
                        <AppButton
                            variant="secondary"
                            class="w-full"
                        >
                            <AppIcon name="eye" :size="16" class="mr-1.5" />
                            View Invoice
                        </AppButton>
                    </Link>
                </div>
            </div>
    </div>
</template>
