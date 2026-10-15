<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { api } from '@/lib/api';
import PageHeader from '@/components/tasa/PageHeader.vue';
import AppButton from '@/components/tasa/AppButton.vue';
import AppIcon from '@/components/tasa/AppIcon.vue';
import StatusBadge from '@/components/tasa/StatusBadge.vue';
import FormField from '@/components/tasa/FormField.vue';
import TablePagination from '@/components/tasa/TablePagination.vue';
import EmptyState from '@/components/tasa/EmptyState.vue';
import StatCard from '@/components/tasa/StatCard.vue';
import SideDrawer from '@/components/tasa/SideDrawer.vue';
import ConfirmDialog from '@/components/tasa/ConfirmDialog.vue';
import { formatMoney, formatQuantity, formatDate } from '@/lib/formatters';
import { showToast } from '@/lib/toast';

interface CategoryOption {
    id: number;
    name: string;
    products_count?: number;
}

interface ProductVariantItem {
    id: number;
    product_id: number;
    sku: string;
    color_name: string;
    attribute_name?: string;
    attribute_value?: string;
    cost_price: number | null;
    selling_price: number;
    opening_stock?: number;
    stock_qty: number;
    min_stock: number;
    barcode?: string | null;
    is_active: boolean;
    is_low_stock?: boolean;
}

interface ProductItem {
    id: number;
    name: string;
    sku: string;
    has_variants: boolean;
    variation_type: string | null;
    variants?: ProductVariantItem[];
    variants_count?: number;
    product_category_id: number | null;
    category: { id: number; name: string } | null;
    cost_price: number | null;
    selling_price: number;
    stock_qty: number;
    min_stock: number;
    is_active: boolean;
    is_low_stock: boolean;
    created_at: string;
}

interface PaginatedProducts {
    data: ProductItem[];
    current_page: number;
    last_page: number;
    total: number;
    from: number;
    to: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface StockMovementItem {
    id: number;
    type: string;
    qty: number;
    balance_after: number;
    reference_type: string | null;
    reference_id: number | null;
    movement_date: string;
    note: string | null;
    creator_name: string;
    created_at: string;
}

interface ImportPreviewRow {
    row_number: number;
    name: string;
    sku: string;
    category: string;
    cost_price: string | null;
    selling_price: string;
    opening_stock: number;
    min_stock: number;
    errors: string[];
    is_valid: boolean;
}

interface ImportPreviewData {
    filename: string;
    total_rows: number;
    valid_count: number;
    invalid_count: number;
    rows: ImportPreviewRow[];
}

const props = defineProps<{
    products: PaginatedProducts;
    categories: CategoryOption[];
    filters: {
        search?: string;
        status?: string;
        category_id?: string | number;
        low_stock?: boolean;
        sort?: string;
        direction?: string;
    };
    canViewCost: boolean;
    canManageProducts: boolean;
    canDeleteProducts: boolean;
    canImport: boolean;
    canAdjust: boolean;
    stats: {
        total_products: number;
        active_products: number;
        low_stock_count: number;
        total_stock_qty: number;
    };
    allowNegativeStock: boolean;
}>();

// --- Filter State ---
const searchInput = ref(props.filters.search || '');
const statusFilter = ref(props.filters.status || 'all');
const categoryFilter = ref(props.filters.category_id || '');
const lowStockFilter = ref(Boolean(props.filters.low_stock));

const applyFilters = () => {
    router.get(
        '/inventory/products',
        {
            search: searchInput.value || undefined,
            status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
            category_id: categoryFilter.value || undefined,
            low_stock: lowStockFilter.value ? '1' : undefined,
        },
        { preserveState: true, replace: true }
    );
};

const resetFilters = () => {
    searchInput.value = '';
    statusFilter.value = 'all';
    categoryFilter.value = '';
    lowStockFilter.value = false;
    router.get('/inventory/products', {}, { preserveState: true, replace: true });
};

const onPageChange = (page: number) => {
    router.get(
        '/inventory/products',
        {
            search: searchInput.value || undefined,
            status: statusFilter.value !== 'all' ? statusFilter.value : undefined,
            category_id: categoryFilter.value || undefined,
            low_stock: lowStockFilter.value ? '1' : undefined,
            page,
        },
        { preserveState: true, replace: true }
    );
};

// --- Inline Edit for Minimum Stock ---
const editingMinStockId = ref<number | null>(null);
const inlineMinStockValue = ref<number>(0);
const savingMinStock = ref(false);

const startInlineEditMinStock = (product: ProductItem) => {
    editingMinStockId.value = product.id;
    inlineMinStockValue.value = product.min_stock;
};

const cancelInlineEditMinStock = () => {
    editingMinStockId.value = null;
};

const saveInlineMinStock = async (product: ProductItem) => {
    if (inlineMinStockValue.value < 0) {
        showToast('Minimum stock cannot be negative.', 'danger');
        return;
    }

    savingMinStock.value = true;
    try {
        const response = await api.patch(`/inventory/products/${product.id}/min-stock`, {
            min_stock: inlineMinStockValue.value,
        });
        product.min_stock = response.min_stock;
        product.is_low_stock = product.is_active && product.stock_qty <= product.min_stock;
        editingMinStockId.value = null;
        showToast(`Minimum stock for ${product.name} updated to ${product.min_stock}.`, 'success');
    } catch (err: any) {
        showToast(err.response?.data?.message || err.message || 'Failed to update minimum stock.', 'danger');
    } finally {
        savingMinStock.value = false;
    }
};

// --- Expandable Rows for Product Variants ---
const expandedProductIds = ref<number[]>([]);
const toggleProductExpand = (id: number) => {
    const idx = expandedProductIds.value.indexOf(id);
    if (idx >= 0) {
        expandedProductIds.value.splice(idx, 1);
    } else {
        expandedProductIds.value.push(id);
    }
};

// --- Create Product Modal ---
const isCreateOpen = ref(false);
const createForm = useForm({
    name: '',
    sku: '',
    product_category_id: '' as string | number,
    category_name: '',
    cost_price: 0,
    selling_price: 0,
    opening_stock: 0,
    min_stock: 0,
    is_active: true,
    has_variants: false,
    variation_type: 'Color',
    variants: [] as Array<{
        color_name: string;
        sku: string;
        cost_price: number;
        selling_price: number;
        opening_stock: number;
        min_stock: number;
        is_active: boolean;
    }>,
});

const onToggleCreateVariants = () => {
    if (createForm.has_variants && createForm.variants.length === 0) {
        createForm.variants = [
            {
                color_name: 'Brown',
                sku: '',
                cost_price: createForm.cost_price || 0,
                selling_price: createForm.selling_price || 0,
                opening_stock: 0,
                min_stock: createForm.min_stock || 0,
                is_active: true,
            },
            {
                color_name: 'Black',
                sku: '',
                cost_price: createForm.cost_price || 0,
                selling_price: createForm.selling_price || 0,
                opening_stock: 0,
                min_stock: createForm.min_stock || 0,
                is_active: true,
            },
        ];
    }
};

const addCreateVariantRow = () => {
    createForm.variants.push({
        color_name: '',
        sku: '',
        cost_price: createForm.cost_price || 0,
        selling_price: createForm.selling_price || 0,
        opening_stock: 0,
        min_stock: createForm.min_stock || 0,
        is_active: true,
    });
};

const removeCreateVariantRow = (index: number) => {
    createForm.variants.splice(index, 1);
};

const openCreateModal = () => {
    createForm.reset();
    createForm.clearErrors();
    createForm.cost_price = 0;
    createForm.selling_price = 0;
    createForm.opening_stock = 0;
    createForm.min_stock = 0;
    createForm.is_active = true;
    createForm.has_variants = false;
    createForm.variation_type = 'Color';
    createForm.variants = [];
    isCreateOpen.value = true;
};

const submitCreate = () => {
    createForm.post('/inventory/products', {
        onSuccess: () => {
            isCreateOpen.value = false;
            showToast('Product created successfully.', 'success');
        },
    });
};

// --- Edit Product Modal ---
const isEditOpen = ref(false);
const editingProduct = ref<ProductItem | null>(null);
const editForm = useForm({
    name: '',
    sku: '',
    product_category_id: '' as string | number,
    category_name: '',
    cost_price: 0,
    selling_price: 0,
    min_stock: 0,
    is_active: true,
    has_variants: false,
    variation_type: 'Color',
    variants: [] as Array<{
        id?: number;
        color_name: string;
        sku: string;
        cost_price: number;
        selling_price: number;
        stock_qty?: number;
        min_stock: number;
        is_active: boolean;
    }>,
});

const openEditModal = (product: ProductItem) => {
    editingProduct.value = product;
    editForm.reset();
    editForm.clearErrors();
    editForm.name = product.name;
    editForm.sku = product.sku;
    editForm.product_category_id = product.product_category_id || '';
    editForm.cost_price = product.cost_price !== null ? product.cost_price : 0;
    editForm.selling_price = product.selling_price;
    editForm.min_stock = product.min_stock;
    editForm.is_active = product.is_active;
    editForm.has_variants = product.has_variants;
    editForm.variation_type = product.variation_type || 'Color';
    editForm.variants = (product.variants || []).map((v) => ({
        id: v.id,
        color_name: v.color_name,
        sku: v.sku,
        cost_price: v.cost_price !== null ? v.cost_price : 0,
        selling_price: v.selling_price,
        stock_qty: v.stock_qty,
        min_stock: v.min_stock,
        is_active: v.is_active,
    }));
    isEditOpen.value = true;
};

const addEditVariantRow = () => {
    editForm.variants.push({
        color_name: '',
        sku: '',
        cost_price: editForm.cost_price || 0,
        selling_price: editForm.selling_price || 0,
        min_stock: editForm.min_stock || 0,
        is_active: true,
    });
};

const removeEditVariantRow = (index: number) => {
    editForm.variants.splice(index, 1);
};

const submitEdit = () => {
    if (!editingProduct.value) return;
    editForm.put(`/inventory/products/${editingProduct.value.id}`, {
        onSuccess: () => {
            isEditOpen.value = false;
            showToast('Product updated successfully.', 'success');
        },
    });
};

// --- Add Stock / Production Entry Modal ---
const isAddStockOpen = ref(false);
const selectedStockProduct = ref<ProductItem | null>(null);
const addStockForm = useForm({
    product_id: '' as string | number,
    variant_id: '' as string | number,
    qty: 1,
    date: new Date().toISOString().split('T')[0],
    note: '',
});

const openAddStockModal = (product?: ProductItem) => {
    selectedStockProduct.value = product || props.products.data[0] || null;
    addStockForm.reset();
    addStockForm.clearErrors();
    addStockForm.product_id = selectedStockProduct.value ? selectedStockProduct.value.id : '';
    addStockForm.variant_id = selectedStockProduct.value?.variants?.[0]?.id || '';
    addStockForm.qty = 1;
    addStockForm.date = new Date().toISOString().split('T')[0];
    addStockForm.note = '';
    isAddStockOpen.value = true;
};

const onStockProductChange = () => {
    selectedStockProduct.value = props.products.data.find(p => p.id === Number(addStockForm.product_id)) || null;
    addStockForm.variant_id = selectedStockProduct.value?.variants?.[0]?.id || '';
};

const selectedStockVariant = computed(() => {
    if (!selectedStockProduct.value?.variants) return null;
    return selectedStockProduct.value.variants.find(v => v.id === Number(addStockForm.variant_id)) || null;
});

const projectedNewStock = computed(() => {
    if (selectedStockVariant.value) {
        return selectedStockVariant.value.stock_qty + (Number(addStockForm.qty) || 0);
    }
    if (!selectedStockProduct.value) return 0;
    return selectedStockProduct.value.stock_qty + (Number(addStockForm.qty) || 0);
});

const projectedParentNewStock = computed(() => {
    if (!selectedStockProduct.value) return 0;
    return selectedStockProduct.value.stock_qty + (Number(addStockForm.qty) || 0);
});

const submitAddStock = () => {
    if (!addStockForm.product_id) return;
    if (selectedStockProduct.value?.has_variants && !addStockForm.variant_id) {
        showToast('Please select a color variant.', 'danger');
        return;
    }
    addStockForm.post(`/inventory/products/${addStockForm.product_id}/add-stock`, {
        onSuccess: () => {
            isAddStockOpen.value = false;
            showToast('Production entry added to stock.', 'success');
        },
    });
};

// --- Stock Adjustment Modal ---
const isAdjustOpen = ref(false);
const selectedAdjustProduct = ref<ProductItem | null>(null);
const adjustForm = useForm({
    product_id: '' as string | number,
    variant_id: '' as string | number,
    qty: 0,
    adjusted_on: new Date().toISOString().split('T')[0],
    reason: 'damaged',
    note: '',
});

const openAdjustModal = (product?: ProductItem) => {
    selectedAdjustProduct.value = product || props.products.data[0] || null;
    adjustForm.reset();
    adjustForm.clearErrors();
    adjustForm.product_id = selectedAdjustProduct.value ? selectedAdjustProduct.value.id : '';
    adjustForm.variant_id = selectedAdjustProduct.value?.variants?.[0]?.id || '';
    adjustForm.qty = 0;
    adjustForm.adjusted_on = new Date().toISOString().split('T')[0];
    adjustForm.reason = 'damaged';
    adjustForm.note = '';
    isAdjustOpen.value = true;
};

const onAdjustProductChange = () => {
    selectedAdjustProduct.value = props.products.data.find(p => p.id === Number(adjustForm.product_id)) || null;
    adjustForm.variant_id = selectedAdjustProduct.value?.variants?.[0]?.id || '';
};

const selectedAdjustVariant = computed(() => {
    if (!selectedAdjustProduct.value?.variants) return null;
    return selectedAdjustProduct.value.variants.find(v => v.id === Number(adjustForm.variant_id)) || null;
});

const projectedAdjustedStock = computed(() => {
    if (selectedAdjustVariant.value) {
        return selectedAdjustVariant.value.stock_qty + (Number(adjustForm.qty) || 0);
    }
    if (!selectedAdjustProduct.value) return 0;
    return selectedAdjustProduct.value.stock_qty + (Number(adjustForm.qty) || 0);
});

const projectedParentAdjustedStock = computed(() => {
    if (!selectedAdjustProduct.value) return 0;
    return selectedAdjustProduct.value.stock_qty + (Number(adjustForm.qty) || 0);
});

const submitAdjust = () => {
    if (!adjustForm.product_id) return;
    if (selectedAdjustProduct.value?.has_variants && !adjustForm.variant_id) {
        showToast('Please select a color variant.', 'danger');
        return;
    }
    if (adjustForm.qty === 0) {
        showToast('Adjustment quantity cannot be zero.', 'danger');
        return;
    }
    if (projectedAdjustedStock.value < 0 && !props.allowNegativeStock) {
        showToast(`Negative stock (${projectedAdjustedStock.value}) is prohibited by system settings.`, 'danger');
        return;
    }

    adjustForm.post(`/inventory/products/${adjustForm.product_id}/adjust-stock`, {
        onSuccess: () => {
            isAdjustOpen.value = false;
            showToast('Stock adjustment recorded.', 'success');
        },
    });
};

// --- Stock Movements History Drawer ---
const isMovementsOpen = ref(false);
const movementProduct = ref<ProductItem | null>(null);
const movementsList = ref<StockMovementItem[]>([]);
const loadingMovements = ref(false);

const openMovementsDrawer = async (product: ProductItem) => {
    movementProduct.value = product;
    isMovementsOpen.value = true;
    loadingMovements.value = true;
    movementsList.value = [];

    try {
        const response = await api.get(`/inventory/products/${product.id}/movements`);
        movementsList.value = response.data;
    } catch (err: any) {
        showToast('Failed to load stock movements history.', 'danger');
    } finally {
        loadingMovements.value = false;
    }
};

const getMovementTypeTone = (type: string) => {
    switch (type) {
        case 'production':
            return 'success';
        case 'opening':
            return 'info';
        case 'sale':
            return 'neutral';
        case 'return':
        case 'sale_cancel':
            return 'advance';
        case 'adjustment':
            return 'warning';
        default:
            return 'neutral';
    }
};

// --- Delete / Archive Confirmation ---
const isArchiveOpen = ref(false);
const productToArchive = ref<ProductItem | null>(null);

const confirmArchive = (product: ProductItem) => {
    productToArchive.value = product;
    isArchiveOpen.value = true;
};

const submitArchive = () => {
    if (!productToArchive.value) return;
    router.delete(`/inventory/products/${productToArchive.value.id}`, {
        onSuccess: () => {
            isArchiveOpen.value = false;
            showToast(`Product ${productToArchive.value?.name} archived.`, 'success');
        },
    });
};

// --- Excel Import Modal ---
const isImportOpen = ref(false);
const importFile = ref<File | null>(null);
const parsingImport = ref(false);
const committingImport = ref(false);
const importPreviewData = ref<ImportPreviewData | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);

const openImportModal = () => {
    importFile.value = null;
    importPreviewData.value = null;
    parsingImport.value = false;
    committingImport.value = false;
    isImportOpen.value = true;
};

const onFileSelected = (e: Event) => {
    const files = (e.target as HTMLInputElement).files;
    if (files && files[0]) {
        importFile.value = files[0];
        handlePreviewFile();
    }
};

const handlePreviewFile = async () => {
    if (!importFile.value) return;

    parsingImport.value = true;
    importPreviewData.value = null;

    const formData = new FormData();
    formData.append('file', importFile.value);

    try {
        const response = await api.post('/inventory/products/import/preview', formData);
        importPreviewData.value = response;
    } catch (err: any) {
        showToast(err.response?.data?.message || err.message || 'Failed to parse spreadsheet.', 'danger');
        importFile.value = null;
    } finally {
        parsingImport.value = false;
    }
};

const commitImportRows = async () => {
    if (!importPreviewData.value) return;

    const validRows = importPreviewData.value.rows.filter(r => r.is_valid);
    if (validRows.length === 0) {
        showToast('No valid rows available to import.', 'danger');
        return;
    }

    committingImport.value = true;
    try {
        const response = await api.post('/inventory/products/import/commit', {
            filename: importPreviewData.value.filename,
            rows: validRows,
        });

        showToast(response.message || 'Products imported successfully.', 'success');
        isImportOpen.value = false;
        router.reload();
    } catch (err: any) {
        showToast(err.response?.data?.message || err.message || 'Failed to complete product import.', 'danger');
    } finally {
        committingImport.value = false;
    }
};

// --- Category Management Modal ---
const isCategoriesModalOpen = ref(false);
const categoryList = ref<CategoryOption[]>(props.categories);
const newCategoryName = ref('');
const editingCatId = ref<number | null>(null);
const editingCatName = ref('');
const loadingCategories = ref(false);

const openCategoriesModal = async () => {
    isCategoriesModalOpen.value = true;
    fetchCategories();
};

const fetchCategories = async () => {
    loadingCategories.value = true;
    try {
        const res = await api.get('/inventory/categories');
        categoryList.value = res;
    } catch (e) {
        // fallback to prop
    } finally {
        loadingCategories.value = false;
    }
};

const addCategory = async () => {
    if (!newCategoryName.value.trim()) return;
    try {
        await api.post('/inventory/categories', { name: newCategoryName.value.trim() });
        newCategoryName.value = '';
        fetchCategories();
        showToast('Category created.', 'success');
    } catch (err: any) {
        showToast(err.response?.data?.message || err.message || 'Failed to create category.', 'danger');
    }
};

const startEditCategory = (cat: CategoryOption) => {
    editingCatId.value = cat.id;
    editingCatName.value = cat.name;
};

const saveEditCategory = async (cat: CategoryOption) => {
    if (!editingCatName.value.trim()) return;
    try {
        await api.put(`/inventory/categories/${cat.id}`, { name: editingCatName.value.trim() });
        editingCatId.value = null;
        fetchCategories();
        showToast('Category renamed.', 'success');
    } catch (err: any) {
        showToast(err.response?.data?.message || err.message || 'Failed to rename category.', 'danger');
    }
};

const deleteCategory = async (cat: CategoryOption) => {
    if (!confirm(`Delete category "${cat.name}"? Products will become uncategorized.`)) return;
    try {
        await api.delete(`/inventory/categories/${cat.id}`);
        fetchCategories();
        showToast('Category deleted.', 'success');
    } catch (err: any) {
        showToast(err.response?.data?.message || err.message || 'Failed to delete category.', 'danger');
    }
};
</script>

<template>
    <Head title="Finished Goods Inventory" />

    <div class="space-y-6">
        <!-- Page Header -->
        <PageHeader
            title="Finished Products"
            subtitle="PRD §8.1 · Products catalogue, stock ledger, production entries, and Excel import"
            icon="products"
        >
            <template #actions>
                <div class="flex flex-wrap items-center gap-2">
                    <AppButton
                        v-if="canManageProducts"
                        variant="secondary"
                        size="sm"
                        @click="openCategoriesModal"
                    >
                        <AppIcon name="box" :size="16" class="mr-1.5" />
                        Categories
                    </AppButton>

                    <AppButton
                        v-if="canImport"
                        variant="secondary"
                        size="sm"
                        @click="openImportModal"
                    >
                        <AppIcon name="download" :size="16" class="mr-1.5" />
                        Import Excel
                    </AppButton>

                    <AppButton
                        v-if="canAdjust"
                        variant="secondary"
                        size="sm"
                        @click="() => openAdjustModal()"
                    >
                        <AppIcon name="adjustments" :size="16" class="mr-1.5" />
                        Adjust Stock
                    </AppButton>

                    <AppButton
                        v-if="canManageProducts"
                        variant="secondary"
                        size="sm"
                        @click="() => openAddStockModal()"
                    >
                        <AppIcon name="plus" :size="16" class="mr-1.5" />
                        Add Stock
                    </AppButton>

                    <AppButton
                        v-if="canManageProducts"
                        variant="primary"
                        size="sm"
                        @click="openCreateModal"
                    >
                        <AppIcon name="plus" :size="16" class="mr-1.5" />
                        New Product
                    </AppButton>
                </div>
            </template>
        </PageHeader>

        <!-- KPI Summary Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
            <StatCard
                title="Total Products"
                :value="stats.total_products"
                icon="products"
                caption="Finished goods catalogue"
            />
            <StatCard
                title="Active Catalogue"
                :value="stats.active_products"
                icon="check-circle"
                caption="Available for sale"
            />
            <StatCard
                title="Low Stock Alert"
                :value="stats.low_stock_count"
                icon="warning"
                :tone="stats.low_stock_count > 0 ? 'warning' : 'neutral'"
                caption="Current stock <= minimum"
            />
            <StatCard
                title="Total Finished Pieces"
                :value="formatQuantity(stats.total_stock_qty, 'pcs')"
                icon="box"
                caption="Sum of product stock ledger"
            />
        </div>

        <!-- Search & Filter Controls -->
        <div class="bg-surface border border-line-200 p-3 sm:p-4 mb-4 space-y-3">
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                <!-- Search Input -->
                <div class="flex-1 relative">
                    <input
                        v-model="searchInput"
                        type="text"
                        placeholder="Search product name or SKU..."
                        class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-ink-900 placeholder:text-ink-500 rounded-none focus:outline-teal-700"
                        @keydown.enter="applyFilters"
                    />
                    <button
                        v-if="searchInput"
                        type="button"
                        class="absolute right-2.5 top-2.5 text-ink-500 hover:text-ink-900"
                        @click="searchInput = ''; applyFilters()"
                    >
                        <AppIcon name="close" :size="14" />
                    </button>
                </div>

                <!-- Category Filter -->
                <div class="w-full sm:w-48">
                    <select
                        v-model="categoryFilter"
                        class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-ink-900 rounded-none focus:outline-teal-700"
                        @change="applyFilters"
                    >
                        <option value="">All Categories</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                            {{ cat.name }}
                        </option>
                    </select>
                </div>

                <!-- Status Filter -->
                <div class="w-full sm:w-36">
                    <select
                        v-model="statusFilter"
                        class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-ink-900 rounded-none focus:outline-teal-700"
                        @change="applyFilters"
                    >
                        <option value="all">All Status</option>
                        <option value="active">Active Only</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <!-- Low Stock Toggle Button -->
                <button
                    type="button"
                    :class="[
                        'px-3 py-2 text-xs font-semibold uppercase tracking-wider flex items-center justify-center gap-1.5 rounded-none border transition-colors whitespace-nowrap',
                        lowStockFilter
                            ? 'bg-warning text-ink-900 border-warning'
                            : 'bg-white text-ink-700 border-line-300 hover:bg-teal-50',
                    ]"
                    @click="lowStockFilter = !lowStockFilter; applyFilters()"
                >
                    <AppIcon name="warning" :size="14" />
                    Low Stock Only
                </button>

                <!-- Apply & Reset Buttons -->
                <div class="flex items-center gap-2">
                    <AppButton variant="secondary" size="sm" @click="applyFilters">
                        Filter
                    </AppButton>
                    <button
                        type="button"
                        class="text-xs text-ink-600 hover:text-teal-700 underline px-1 py-1"
                        @click="resetFilters"
                    >
                        Reset
                    </button>
                </div>
            </div>
        </div>

        <!-- Products Table & Mobile Cards -->
        <div class="w-full bg-surface border border-line-200 overflow-hidden">
            <!-- Desktop Table View (>= 640px) -->
            <div class="hidden sm:block overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-sunken border-b border-line-300 sticky top-0 z-10">
                        <tr>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-ink-700 text-left">
                                Product / SKU
                            </th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-ink-700 text-left">
                                Category
                            </th>
                            <th
                                v-if="canViewCost"
                                class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-ink-700 text-right"
                            >
                                Cost Price
                            </th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-ink-700 text-right">
                                Selling Price
                            </th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-ink-700 text-right">
                                Current Stock
                            </th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-ink-700 text-right min-w-[140px]">
                                Min Stock (Inline)
                            </th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-ink-700 text-center">
                                Status
                            </th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-ink-700 text-right">
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-line-200 text-sm text-ink-700">
                        <tr v-if="products.data.length === 0">
                            <td :colspan="canViewCost ? 8 : 7" class="p-8 text-center text-ink-500">
                                <EmptyState
                                    icon="products"
                                    title="No products found"
                                    description="No finished goods match your search or filter criteria."
                                    action-label="Reset Filters"
                                    @action="resetFilters"
                                />
                            </td>
                        </tr>

                        <template v-for="product in products.data" :key="product.id">
                            <tr class="hover:bg-teal-50 transition-colors">
                            <!-- Name & SKU -->
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <button
                                        v-if="product.has_variants && product.variants?.length"
                                        type="button"
                                        class="text-ink-500 hover:text-teal-700 p-0.5 transition-transform"
                                        :title="expandedProductIds.includes(product.id) ? 'Collapse variants' : 'Expand variants'"
                                        @click.stop="toggleProductExpand(product.id)"
                                    >
                                        <AppIcon :name="expandedProductIds.includes(product.id) ? 'chevron-up' : 'chevron-down'" :size="16" />
                                    </button>
                                    <div>
                                        <div class="font-bold text-ink-900 leading-snug flex items-center gap-1.5">
                                            <span>{{ product.name }}</span>
                                            <span
                                                v-if="product.has_variants"
                                                class="px-1.5 py-0.5 text-[10px] font-semibold bg-[#E8F0FA] text-[#1D5FA8] border border-[#BFD5F2]"
                                            >
                                                {{ product.variants?.length || 0 }} {{ product.variation_type || 'Color' }}s
                                            </span>
                                        </div>
                                        <div class="text-xs text-ink-500 font-mono mt-0.5">
                                            {{ product.sku }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Category -->
                            <td class="px-4 py-3 text-sm text-ink-700">
                                <span v-if="product.category" class="bg-line-100 text-ink-700 text-xs px-2 py-0.5 font-medium border border-line-200">
                                    {{ product.category.name }}
                                </span>
                                <span v-else class="text-ink-400 text-xs italic">
                                    Uncategorized
                                </span>
                            </td>

                            <!-- Cost Price (Server protected) -->
                            <td v-if="canViewCost" class="px-4 py-3 text-right tabular-nums text-sm text-ink-700">
                                {{ formatMoney(product.cost_price) }}
                            </td>

                            <!-- Selling Price -->
                            <td class="px-4 py-3 text-right tabular-nums font-semibold text-ink-900 text-sm">
                                {{ formatMoney(product.selling_price) }}
                            </td>

                            <!-- Current Stock -->
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <span
                                        :class="[
                                            'font-bold tabular-nums text-sm',
                                            product.is_low_stock ? 'text-danger font-extrabold' : 'text-ink-900'
                                        ]"
                                    >
                                        {{ formatQuantity(product.stock_qty, 'pcs') }}
                                    </span>
                                    <span
                                        v-if="product.is_low_stock"
                                        class="bg-danger text-white text-[10px] font-bold px-1.5 py-0.2 uppercase tracking-wider"
                                        title="Stock is at or below minimum alert level"
                                    >
                                        Low
                                    </span>
                                </div>
                            </td>

                            <!-- Min Stock (Inline Editable) -->
                            <td class="px-4 py-3 text-right">
                                <div v-if="editingMinStockId === product.id" class="flex items-center justify-end gap-1">
                                    <input
                                        v-model.number="inlineMinStockValue"
                                        type="number"
                                        min="0"
                                        class="w-16 bg-white border border-teal-700 px-1.5 py-1 text-right text-xs tabular-nums text-ink-900 rounded-none focus:outline-none"
                                        :disabled="savingMinStock"
                                        @keydown.enter="saveInlineMinStock(product)"
                                        @keydown.esc="cancelInlineEditMinStock"
                                    />
                                    <button
                                        type="button"
                                        class="text-teal-700 hover:text-teal-900 p-1"
                                        title="Save minimum stock"
                                        :disabled="savingMinStock"
                                        @click="saveInlineMinStock(product)"
                                    >
                                        <AppIcon name="check" :size="14" />
                                    </button>
                                    <button
                                        type="button"
                                        class="text-ink-500 hover:text-ink-900 p-1"
                                        title="Cancel"
                                        :disabled="savingMinStock"
                                        @click="cancelInlineEditMinStock"
                                    >
                                        <AppIcon name="close" :size="14" />
                                    </button>
                                </div>
                                <div
                                    v-else
                                    class="inline-flex items-center gap-1 group cursor-pointer"
                                    title="Click to edit minimum stock alert level"
                                    @click="startInlineEditMinStock(product)"
                                >
                                    <span class="tabular-nums text-sm text-ink-700 font-medium">
                                        {{ product.min_stock }} pcs
                                    </span>
                                    <span class="text-ink-400 group-hover:text-teal-700 text-xs opacity-60 group-hover:opacity-100">
                                        <AppIcon name="edit" :size="12" />
                                    </span>
                                </div>
                            </td>

                            <!-- Active / Inactive Status -->
                            <td class="px-4 py-3 text-center">
                                <StatusBadge
                                    :tone="product.is_active ? 'success' : 'neutral'"
                                    :label="product.is_active ? 'Active' : 'Inactive'"
                                    :icon="product.is_active ? 'check-circle' : 'close'"
                                    size="sm"
                                />
                            </td>

                            <!-- Actions -->
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex items-center gap-1">
                                    <button
                                        type="button"
                                        class="p-1.5 text-ink-600 hover:text-teal-700 hover:bg-teal-50 border border-transparent hover:border-line-200"
                                        title="View stock movement history"
                                        @click="openMovementsDrawer(product)"
                                    >
                                        <AppIcon name="box" :size="15" />
                                    </button>

                                    <button
                                        v-if="canManageProducts"
                                        type="button"
                                        class="p-1.5 text-ink-600 hover:text-teal-700 hover:bg-teal-50 border border-transparent hover:border-line-200"
                                        title="Add stock / production entry"
                                        @click="openAddStockModal(product)"
                                    >
                                        <AppIcon name="plus" :size="15" />
                                    </button>

                                    <button
                                        v-if="canManageProducts"
                                        type="button"
                                        class="p-1.5 text-ink-600 hover:text-ink-900 hover:bg-teal-50 border border-transparent hover:border-line-200"
                                        title="Edit product"
                                        @click="openEditModal(product)"
                                    >
                                        <AppIcon name="edit" :size="15" />
                                    </button>

                                    <button
                                        v-if="canDeleteProducts"
                                        type="button"
                                        class="p-1.5 text-ink-500 hover:text-danger hover:bg-danger-50 border border-transparent hover:border-line-200"
                                        title="Archive product (soft-delete)"
                                        @click="confirmArchive(product)"
                                    >
                                        <AppIcon name="trash" :size="15" />
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Expanded Variations Row -->
                        <tr
                            v-if="product.has_variants && expandedProductIds.includes(product.id)"
                            :key="'variants-' + product.id"
                            class="bg-[#F8FAF9] border-b border-line-200"
                        >
                            <td :colspan="canViewCost ? 8 : 7" class="px-6 py-3">
                                <div class="border border-[#BFDDD6] bg-white p-3 space-y-2">
                                    <div class="text-xs font-bold text-[#074538] uppercase tracking-wider flex items-center justify-between">
                                        <span class="flex items-center gap-1.5">
                                            <AppIcon name="products" :size="14" />
                                            Color Variants Breakdown for {{ product.name }} (Parent SKU: {{ product.sku }})
                                        </span>
                                        <span class="text-[11px] text-[#4A5957] font-normal">
                                            Total variant stock: <strong>{{ product.stock_qty }} pcs</strong>
                                        </span>
                                    </div>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-xs text-left border-collapse">
                                            <thead class="bg-[#EBEFEE] text-[#4A5957] font-semibold">
                                                <tr>
                                                    <th class="py-1.5 px-3">Variant / Color</th>
                                                    <th class="py-1.5 px-3 font-mono">Variant SKU</th>
                                                    <th class="py-1.5 px-3 text-right">Selling Price</th>
                                                    <th v-if="canViewCost" class="py-1.5 px-3 text-right">Cost Price</th>
                                                    <th class="py-1.5 px-3 text-right">Current Stock</th>
                                                    <th class="py-1.5 px-3 text-right">Min Stock</th>
                                                    <th class="py-1.5 px-3 text-center">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-[#EBEFEE]">
                                                <tr v-for="variant in product.variants" :key="variant.id" class="hover:bg-[#F0F7F5]">
                                                    <td class="py-2 px-3 font-semibold text-[#0F1B1A]">
                                                        <div class="flex items-center gap-1.5">
                                                            <span class="w-2.5 h-2.5 rounded-full border border-black/20" :style="{ backgroundColor: variant.color_name.toLowerCase() }"></span>
                                                            {{ variant.color_name }}
                                                        </div>
                                                    </td>
                                                    <td class="py-2 px-3 font-mono text-[#5F6F6D]">
                                                        {{ variant.sku }}
                                                    </td>
                                                    <td class="py-2 px-3 text-right tabular-nums font-medium text-[#0F1B1A]">
                                                        {{ formatMoney(variant.selling_price) }}
                                                    </td>
                                                    <td v-if="canViewCost" class="py-2 px-3 text-right tabular-nums text-[#5F6F6D]">
                                                        {{ formatMoney(variant.cost_price) }}
                                                    </td>
                                                    <td class="py-2 px-3 text-right tabular-nums font-bold">
                                                        <span :class="variant.is_low_stock ? 'text-danger' : 'text-ink-900'">
                                                            {{ variant.stock_qty }} pcs
                                                        </span>
                                                        <span v-if="variant.is_low_stock" class="ml-1 text-[9px] bg-danger text-white px-1 font-bold">LOW</span>
                                                    </td>
                                                    <td class="py-2 px-3 text-right tabular-nums text-ink-600">
                                                        {{ variant.min_stock }} pcs
                                                    </td>
                                                    <td class="py-2 px-3 text-center">
                                                        <StatusBadge
                                                            :tone="variant.is_active ? 'success' : 'neutral'"
                                                            :label="variant.is_active ? 'Active' : 'Inactive'"
                                                            size="sm"
                                                        />
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card View (< 640px) -->
            <div class="sm:hidden p-3 space-y-3">
                <div v-if="products.data.length === 0" class="p-6 text-center text-ink-500 text-sm">
                    No products found.
                </div>

                <div
                    v-for="product in products.data"
                    :key="product.id"
                    class="bg-surface border border-line-200 p-4 text-xs space-y-2.5"
                >
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="font-bold text-sm text-ink-900">
                                {{ product.name }}
                            </div>
                            <div class="text-[11px] font-mono text-ink-500">
                                {{ product.sku }}
                            </div>
                        </div>
                        <StatusBadge
                            :tone="product.is_active ? 'success' : 'neutral'"
                            :label="product.is_active ? 'Active' : 'Inactive'"
                            size="sm"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-2 py-2 border-t border-b border-line-200 text-ink-700">
                        <div>
                            <span class="text-ink-500 block text-[10px] uppercase">Selling Price</span>
                            <span class="font-bold text-ink-900 text-sm tabular-nums">{{ formatMoney(product.selling_price) }}</span>
                        </div>
                        <div v-if="canViewCost">
                            <span class="text-ink-500 block text-[10px] uppercase">Cost Price</span>
                            <span class="tabular-nums text-sm">{{ formatMoney(product.cost_price) }}</span>
                        </div>
                        <div>
                            <span class="text-ink-500 block text-[10px] uppercase">Current Stock</span>
                            <span :class="['font-extrabold text-sm tabular-nums', product.is_low_stock ? 'text-danger' : 'text-ink-900']">
                                {{ product.stock_qty }} pcs
                            </span>
                            <span v-if="product.is_low_stock" class="text-danger font-bold text-[10px] ml-1 uppercase">Low</span>
                        </div>
                        <div>
                            <span class="text-ink-500 block text-[10px] uppercase">Min Stock</span>
                            <span class="font-medium text-sm tabular-nums">{{ product.min_stock }} pcs</span>
                        </div>
                    </div>

                    <!-- Mobile Action Strip -->
                    <div class="flex items-center justify-between pt-1">
                        <span v-if="product.category" class="bg-line-100 text-ink-600 px-2 py-0.5 text-[10px] font-medium border border-line-200">
                            {{ product.category.name }}
                        </span>
                        <div class="flex items-center gap-1.5 ml-auto">
                            <button
                                type="button"
                                class="px-2 py-1 text-xs border border-line-300 text-ink-700 hover:bg-teal-50"
                                @click="openMovementsDrawer(product)"
                            >
                                History
                            </button>
                            <button
                                v-if="canManageProducts"
                                type="button"
                                class="px-2 py-1 text-xs border border-teal-700 text-teal-700 hover:bg-teal-50 font-medium"
                                @click="openAddStockModal(product)"
                            >
                                + Stock
                            </button>
                            <button
                                v-if="canManageProducts"
                                type="button"
                                class="p-1 border border-line-300 text-ink-700 hover:bg-teal-50"
                                title="Edit"
                                @click="openEditModal(product)"
                            >
                                <AppIcon name="edit" :size="14" />
                            </button>
                            <button
                                v-if="canDeleteProducts"
                                type="button"
                                class="p-1 border border-danger text-danger hover:bg-danger-50"
                                title="Archive"
                                @click="confirmArchive(product)"
                            >
                                <AppIcon name="trash" :size="14" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table Pagination -->
            <TablePagination
                :current-page="products.current_page"
                :total-items="products.total"
                :per-page="25"
                @update:current-page="onPageChange"
            />
        </div>

        <!-- ==================== CREATE PRODUCT MODAL ==================== -->
        <SideDrawer
            :open="isCreateOpen"
            title="New Finished Product"
            subtitle="Auto-generates sequential SKU (P-000123) if left blank"
            :width-class="createForm.has_variants ? 'sm:max-w-[720px]' : 'sm:max-w-[520px]'"
            @close="isCreateOpen = false"
        >
            <form id="create-product-form" class="space-y-4" @submit.prevent="submitCreate">
                <FormField label="Product Name" :error="createForm.errors.name" required>
                    <input
                        v-model="createForm.name"
                        type="text"
                        placeholder="e.g. Eyewear Case Series 1"
                        class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-ink-900 rounded-none focus:outline-teal-700"
                        required
                    />
                </FormField>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <FormField
                        label="Parent SKU / Code"
                        :error="createForm.errors.sku"
                        help-text="Leave blank to auto-generate P-000123"
                    >
                        <input
                            v-model="createForm.sku"
                            type="text"
                            placeholder="e.g. EWC-S1"
                            class="w-full bg-white border border-line-300 px-3 py-2 text-sm font-mono text-ink-900 rounded-none focus:outline-teal-700"
                        />
                    </FormField>

                    <FormField label="Category" :error="createForm.errors.product_category_id">
                        <select
                            v-model="createForm.product_category_id"
                            class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-ink-900 rounded-none focus:outline-teal-700"
                        >
                            <option value="">Select Category</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                {{ cat.name }}
                            </option>
                        </select>
                    </FormField>
                </div>

                <!-- Variations Toggle -->
                <div class="p-3 bg-[#F4F6F6] border border-line-200 space-y-1">
                    <label class="flex items-center gap-2 cursor-pointer font-bold text-sm text-ink-900">
                        <input
                            v-model="createForm.has_variants"
                            type="checkbox"
                            class="h-4 w-4 rounded-none text-teal-700 focus:ring-teal-700"
                            @change="onToggleCreateVariants"
                        />
                        <span>Enable Product Color Variations</span>
                    </label>
                    <p class="text-xs text-ink-500 pl-6">
                        Enable if this product has multiple colors (e.g. Eyewear Case Series 1 in Brown and Black).
                    </p>
                </div>

                <!-- Variations Editor -->
                <div v-if="createForm.has_variants" class="p-3 bg-[#F0F7F5] border border-[#BFDDD6] space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-[#074538]">
                            Color Variants List ({{ createForm.variants.length }})
                        </span>
                        <button
                            type="button"
                            class="text-xs bg-[#0B6E5E] text-white hover:bg-[#074538] px-2.5 py-1 font-semibold flex items-center gap-1"
                            @click="addCreateVariantRow"
                        >
                            <AppIcon name="plus" :size="12" /> + Add Color Variant
                        </button>
                    </div>

                    <div v-if="createForm.variants.length === 0" class="text-xs text-danger">
                        At least one color variant is required when variations are enabled.
                    </div>

                    <div class="space-y-2">
                        <div
                            v-for="(v, vIdx) in createForm.variants"
                            :key="vIdx"
                            class="bg-white border border-[#D5DCDB] p-2.5 space-y-2 text-xs"
                        >
                            <div class="flex items-center justify-between font-bold text-[#0F1B1A]">
                                <span class="flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full border border-black/20" :style="{ backgroundColor: v.color_name ? v.color_name.toLowerCase() : '#ccc' }"></span>
                                    Variant #{{ vIdx + 1 }}: {{ v.color_name || 'Unnamed Color' }}
                                </span>
                                <button
                                    v-if="createForm.variants.length > 1"
                                    type="button"
                                    class="text-danger hover:underline text-xs"
                                    @click="removeCreateVariantRow(vIdx)"
                                >
                                    Remove
                                </button>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                <div>
                                    <label class="block text-[11px] text-ink-600 mb-0.5 font-medium">Color Name *</label>
                                    <input
                                        v-model="v.color_name"
                                        type="text"
                                        placeholder="e.g. Brown"
                                        class="w-full bg-white border border-line-300 px-2 py-1 text-xs text-ink-900 rounded-none focus:outline-teal-700"
                                        required
                                    />
                                </div>
                                <div>
                                    <label class="block text-[11px] text-ink-600 mb-0.5 font-medium">Variant SKU</label>
                                    <input
                                        v-model="v.sku"
                                        type="text"
                                        placeholder="Auto: EWC-S1-BRN"
                                        class="w-full bg-white border border-line-300 px-2 py-1 text-xs font-mono text-ink-900 rounded-none focus:outline-teal-700"
                                    />
                                </div>
                                <div>
                                    <label class="block text-[11px] text-ink-600 mb-0.5 font-medium">Selling Price (৳) *</label>
                                    <input
                                        v-model.number="v.selling_price"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="w-full bg-white border border-line-300 px-2 py-1 text-xs text-right tabular-nums text-ink-900 rounded-none focus:outline-teal-700"
                                        required
                                    />
                                </div>
                                <div v-if="canViewCost">
                                    <label class="block text-[11px] text-ink-600 mb-0.5 font-medium">Cost Price (৳)</label>
                                    <input
                                        v-model.number="v.cost_price"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="w-full bg-white border border-line-300 px-2 py-1 text-xs text-right tabular-nums text-ink-900 rounded-none focus:outline-teal-700"
                                    />
                                </div>
                                <div>
                                    <label class="block text-[11px] text-ink-600 mb-0.5 font-medium">Opening Stock (pcs)</label>
                                    <input
                                        v-model.number="v.opening_stock"
                                        type="number"
                                        min="0"
                                        class="w-full bg-white border border-line-300 px-2 py-1 text-xs text-right tabular-nums text-ink-900 rounded-none focus:outline-teal-700"
                                    />
                                </div>
                                <div>
                                    <label class="block text-[11px] text-ink-600 mb-0.5 font-medium">Min Stock (Alert)</label>
                                    <input
                                        v-model.number="v.min_stock"
                                        type="number"
                                        min="0"
                                        class="w-full bg-white border border-line-300 px-2 py-1 text-xs text-right tabular-nums text-ink-900 rounded-none focus:outline-teal-700"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Single Product Price & Stock (When NOT using variants) -->
                <template v-if="!createForm.has_variants">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <FormField
                            v-if="canViewCost"
                            label="Manual Cost Price (৳)"
                            :error="createForm.errors.cost_price"
                            help-text="Materials cost (hidden from staff)"
                        >
                            <input
                                v-model.number="createForm.cost_price"
                                type="number"
                                step="0.01"
                                min="0"
                                class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-right tabular-nums text-ink-900 rounded-none focus:outline-teal-700"
                            />
                        </FormField>

                        <FormField label="Selling Price (৳)" :error="createForm.errors.selling_price" required>
                            <input
                                v-model.number="createForm.selling_price"
                                type="number"
                                step="0.01"
                                min="0"
                                class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-right tabular-nums text-ink-900 rounded-none focus:outline-teal-700"
                                required
                            />
                        </FormField>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <FormField
                            label="Initial Opening Stock"
                            :error="createForm.errors.opening_stock"
                            help-text="Writes an opening movement row"
                        >
                            <input
                                v-model.number="createForm.opening_stock"
                                type="number"
                                min="0"
                                class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-right tabular-nums text-ink-900 rounded-none focus:outline-teal-700"
                            />
                        </FormField>

                        <FormField
                            label="Minimum Stock (Alert Level)"
                            :error="createForm.errors.min_stock"
                            help-text="Low stock alert trigger"
                        >
                            <input
                                v-model.number="createForm.min_stock"
                                type="number"
                                min="0"
                                class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-right tabular-nums text-ink-900 rounded-none focus:outline-teal-700"
                            />
                        </FormField>
                    </div>
                </template>

                <div class="pt-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            v-model="createForm.is_active"
                            type="checkbox"
                            class="h-4 w-4 rounded-none text-teal-700 focus:ring-teal-700"
                        />
                        <span class="text-sm font-medium text-ink-900">Active product (available for sale in POS)</span>
                    </label>
                </div>
            </form>

            <template #footer>
                <AppButton variant="secondary" size="sm" @click="isCreateOpen = false">
                    Cancel
                </AppButton>
                <AppButton
                    variant="primary"
                    size="sm"
                    :loading="createForm.processing"
                    @click="submitCreate"
                >
                    Save Product
                </AppButton>
            </template>
        </SideDrawer>

        <!-- ==================== EDIT PRODUCT MODAL ==================== -->
        <SideDrawer
            :open="isEditOpen"
            title="Edit Finished Product"
            :subtitle="editingProduct ? `${editingProduct.name} (${editingProduct.sku})` : ''"
            :width-class="editForm.has_variants ? 'sm:max-w-[720px]' : 'sm:max-w-[520px]'"
            @close="isEditOpen = false"
        >
            <form id="edit-product-form" class="space-y-4" @submit.prevent="submitEdit">
                <FormField label="Product Name" :error="editForm.errors.name" required>
                    <input
                        v-model="editForm.name"
                        type="text"
                        class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-ink-900 rounded-none focus:outline-teal-700"
                        required
                    />
                </FormField>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <FormField label="SKU / Code" :error="editForm.errors.sku">
                        <input
                            v-model="editForm.sku"
                            type="text"
                            class="w-full bg-white border border-line-300 px-3 py-2 text-sm font-mono text-ink-900 rounded-none focus:outline-teal-700"
                            required
                        />
                    </FormField>

                    <FormField label="Category" :error="editForm.errors.product_category_id">
                        <select
                            v-model="editForm.product_category_id"
                            class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-ink-900 rounded-none focus:outline-teal-700"
                        >
                            <option value="">Select Category</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                {{ cat.name }}
                            </option>
                        </select>
                    </FormField>
                </div>

                <!-- Product Variations Section (When Product has variants) -->
                <div v-if="editForm.has_variants" class="p-3 bg-[#F0F7F5] border border-[#BFDDD6] space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-[#074538]">
                                Color Variants ({{ editForm.variants.length }})
                            </span>
                            <p class="text-[11px] text-ink-500">
                                Stock changes must follow standard stock adjustments or production entry.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="text-xs bg-[#0B6E5E] text-white hover:bg-[#074538] px-2.5 py-1 font-semibold flex items-center gap-1"
                            @click="addEditVariantRow"
                        >
                            <AppIcon name="plus" :size="12" /> + Add Color Variant
                        </button>
                    </div>

                    <div class="space-y-2">
                        <div
                            v-for="(v, vIdx) in editForm.variants"
                            :key="vIdx"
                            class="bg-white border border-[#D5DCDB] p-2.5 space-y-2 text-xs"
                        >
                            <div class="flex items-center justify-between font-bold text-[#0F1B1A]">
                                <span class="flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full border border-black/20" :style="{ backgroundColor: v.color_name ? v.color_name.toLowerCase() : '#ccc' }"></span>
                                    Variant #{{ vIdx + 1 }}: {{ v.color_name || 'Unnamed' }}
                                    <span v-if="v.stock_qty !== undefined" class="ml-1.5 px-1.5 py-0.2 bg-[#E7F4EA] text-[#14602C] font-mono text-[10px] border border-[#BFDDD6]">
                                        Stock: {{ v.stock_qty }} pcs
                                    </span>
                                </span>
                                <div class="flex items-center gap-2">
                                    <label class="flex items-center gap-1 cursor-pointer text-xs font-normal text-ink-700">
                                        <input v-model="v.is_active" type="checkbox" class="h-3.5 w-3.5 text-teal-700" />
                                        Active
                                    </label>
                                    <button
                                        v-if="!v.id && editForm.variants.length > 1"
                                        type="button"
                                        class="text-danger hover:underline text-xs"
                                        @click="removeEditVariantRow(vIdx)"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                <div>
                                    <label class="block text-[11px] text-ink-600 mb-0.5 font-medium">Color *</label>
                                    <input
                                        v-model="v.color_name"
                                        type="text"
                                        placeholder="e.g. Brown"
                                        class="w-full bg-white border border-line-300 px-2 py-1 text-xs text-ink-900 rounded-none focus:outline-teal-700"
                                        required
                                    />
                                </div>
                                <div>
                                    <label class="block text-[11px] text-ink-600 mb-0.5 font-medium">SKU</label>
                                    <input
                                        v-model="v.sku"
                                        type="text"
                                        class="w-full bg-white border border-line-300 px-2 py-1 text-xs font-mono text-ink-900 rounded-none focus:outline-teal-700"
                                        required
                                    />
                                </div>
                                <div>
                                    <label class="block text-[11px] text-ink-600 mb-0.5 font-medium">Selling Price (৳) *</label>
                                    <input
                                        v-model.number="v.selling_price"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="w-full bg-white border border-line-300 px-2 py-1 text-xs text-right tabular-nums text-ink-900 rounded-none focus:outline-teal-700"
                                        required
                                    />
                                </div>
                                <div v-if="canViewCost">
                                    <label class="block text-[11px] text-ink-600 mb-0.5 font-medium">Cost Price (৳)</label>
                                    <input
                                        v-model.number="v.cost_price"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="w-full bg-white border border-line-300 px-2 py-1 text-xs text-right tabular-nums text-ink-900 rounded-none focus:outline-teal-700"
                                    />
                                </div>
                                <div>
                                    <label class="block text-[11px] text-ink-600 mb-0.5 font-medium">Min Stock (Alert)</label>
                                    <input
                                        v-model.number="v.min_stock"
                                        type="number"
                                        min="0"
                                        class="w-full bg-white border border-line-300 px-2 py-1 text-xs text-right tabular-nums text-ink-900 rounded-none focus:outline-teal-700"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Single Product Price & Stock (When NOT using variants) -->
                <template v-if="!editForm.has_variants">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <FormField
                            v-if="canViewCost"
                            label="Manual Cost Price (৳)"
                            :error="editForm.errors.cost_price"
                        >
                            <input
                                v-model.number="editForm.cost_price"
                                type="number"
                                step="0.01"
                                min="0"
                                class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-right tabular-nums text-ink-900 rounded-none focus:outline-teal-700"
                            />
                        </FormField>

                        <FormField label="Selling Price (৳)" :error="editForm.errors.selling_price" required>
                            <input
                                v-model.number="editForm.selling_price"
                                type="number"
                                step="0.01"
                                min="0"
                                class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-right tabular-nums text-ink-900 rounded-none focus:outline-teal-700"
                                required
                            />
                        </FormField>
                    </div>

                    <FormField label="Minimum Stock (Alert Level)" :error="editForm.errors.min_stock">
                        <input
                            v-model.number="editForm.min_stock"
                            type="number"
                            min="0"
                            class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-right tabular-nums text-ink-900 rounded-none focus:outline-teal-700"
                        />
                    </FormField>
                </template>

                <div class="pt-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            v-model="editForm.is_active"
                            type="checkbox"
                            class="h-4 w-4 rounded-none text-teal-700 focus:ring-teal-700"
                        />
                        <span class="text-sm font-medium text-ink-900">Active product (available for sale in POS)</span>
                    </label>
                </div>
            </form>

            <template #footer>
                <AppButton variant="secondary" size="sm" @click="isEditOpen = false">
                    Cancel
                </AppButton>
                <AppButton
                    variant="primary"
                    size="sm"
                    :loading="editForm.processing"
                    @click="submitEdit"
                >
                    Update Product
                </AppButton>
            </template>
        </SideDrawer>

        <!-- ==================== ADD STOCK / PRODUCTION MODAL ==================== -->
        <SideDrawer
            :open="isAddStockOpen"
            title="Add Stock / Production Entry"
            subtitle="PRD §8.1 · Writes signed movement row and increments cached stock"
            width-class="sm:max-w-[480px]"
            @close="isAddStockOpen = false"
        >
            <form id="add-stock-form" class="space-y-4" @submit.prevent="submitAddStock">
                <FormField label="Product" :error="addStockForm.errors.product_id" required>
                    <select
                        v-model="addStockForm.product_id"
                        class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-ink-900 rounded-none focus:outline-teal-700"
                        required
                        @change="onStockProductChange"
                    >
                        <option value="">Select Product</option>
                        <option v-for="p in products.data" :key="p.id" :value="p.id">
                            {{ p.name }} (Current: {{ p.stock_qty }} pcs)
                        </option>
                    </select>
                </FormField>

                <!-- Color Variant Selection if Product has variants -->
                <FormField
                    v-if="selectedStockProduct?.has_variants && selectedStockProduct.variants?.length"
                    label="Color Variant"
                    :error="addStockForm.errors.variant_id"
                    required
                >
                    <select
                        v-model="addStockForm.variant_id"
                        class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-ink-900 rounded-none focus:outline-teal-700 font-medium"
                        required
                    >
                        <option value="" disabled>-- Select Color Variant --</option>
                        <option v-for="v in selectedStockProduct.variants" :key="v.id" :value="v.id">
                            {{ v.color_name }} ({{ v.sku }}) — Current: {{ v.stock_qty }} pcs
                        </option>
                    </select>
                </FormField>

                <div v-if="selectedStockProduct" class="bg-sunken border border-line-200 p-3 space-y-1 text-xs">
                    <div v-if="selectedStockVariant" class="flex justify-between font-bold text-[#074538]">
                        <span>Variant ({{ selectedStockVariant.color_name }}):</span>
                        <span class="tabular-nums">{{ selectedStockVariant.stock_qty }} pcs</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-ink-600">Total Parent Stock:</span>
                        <span class="font-bold tabular-nums text-ink-900">{{ selectedStockProduct.stock_qty }} pcs</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-ink-600">Adding Pieces:</span>
                        <span class="font-bold tabular-nums text-success">+{{ addStockForm.qty || 0 }} pcs</span>
                    </div>
                    <div class="flex justify-between border-t border-line-200 pt-1 font-bold">
                        <span class="text-ink-900">New {{ selectedStockVariant ? selectedStockVariant.color_name : 'Stock' }} Balance:</span>
                        <span class="tabular-nums text-teal-800">{{ projectedNewStock }} pcs</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <FormField label="Production Date" :error="addStockForm.errors.date" required>
                        <input
                            v-model="addStockForm.date"
                            type="date"
                            class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-ink-900 rounded-none focus:outline-teal-700"
                            required
                        />
                    </FormField>

                    <FormField label="Quantity (Pieces)" :error="addStockForm.errors.qty" required>
                        <input
                            v-model.number="addStockForm.qty"
                            type="number"
                            min="1"
                            class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-right tabular-nums text-ink-900 rounded-none focus:outline-teal-700"
                            required
                        />
                    </FormField>
                </div>

                <FormField label="Note / Batch Info" :error="addStockForm.errors.note">
                    <input
                        v-model="addStockForm.note"
                        type="text"
                        placeholder="e.g. Batch 14 production"
                        class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-ink-900 rounded-none focus:outline-teal-700"
                    />
                </FormField>
            </form>

            <template #footer>
                <AppButton variant="secondary" size="sm" @click="isAddStockOpen = false">
                    Cancel
                </AppButton>
                <AppButton
                    variant="primary"
                    size="sm"
                    :loading="addStockForm.processing"
                    @click="submitAddStock"
                >
                    Record Production
                </AppButton>
            </template>
        </SideDrawer>

        <!-- ==================== STOCK ADJUSTMENT MODAL ==================== -->
        <SideDrawer
            :open="isAdjustOpen"
            title="Stock Adjustment"
            subtitle="PRD §8.4 · Record finished goods damage, loss, or count correction"
            width-class="sm:max-w-[480px]"
            @close="isAdjustOpen = false"
        >
            <form id="adjust-stock-form" class="space-y-4" @submit.prevent="submitAdjust">
                <FormField label="Product" :error="adjustForm.errors.product_id" required>
                    <select
                        v-model="adjustForm.product_id"
                        class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-ink-900 rounded-none focus:outline-teal-700"
                        required
                        @change="onAdjustProductChange"
                    >
                        <option value="">Select Product</option>
                        <option v-for="p in products.data" :key="p.id" :value="p.id">
                            {{ p.name }} (Current: {{ p.stock_qty }} pcs)
                        </option>
                    </select>
                </FormField>

                <!-- Color Variant Selection if Product has variants -->
                <FormField
                    v-if="selectedAdjustProduct?.has_variants && selectedAdjustProduct.variants?.length"
                    label="Color Variant"
                    :error="adjustForm.errors.variant_id"
                    required
                >
                    <select
                        v-model="adjustForm.variant_id"
                        class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-ink-900 rounded-none focus:outline-teal-700 font-medium"
                        required
                    >
                        <option value="" disabled>-- Select Color Variant --</option>
                        <option v-for="v in selectedAdjustProduct.variants" :key="v.id" :value="v.id">
                            {{ v.color_name }} ({{ v.sku }}) — Current: {{ v.stock_qty }} pcs
                        </option>
                    </select>
                </FormField>

                <div v-if="selectedAdjustProduct" class="bg-sunken border border-line-200 p-3 space-y-1 text-xs">
                    <div v-if="selectedAdjustVariant" class="flex justify-between font-bold text-[#074538]">
                        <span>Variant ({{ selectedAdjustVariant.color_name }}):</span>
                        <span class="tabular-nums">{{ selectedAdjustVariant.stock_qty }} pcs</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-ink-600">Total Parent Stock:</span>
                        <span class="font-bold tabular-nums text-ink-900">{{ selectedAdjustProduct.stock_qty }} pcs</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-ink-600">Adjustment:</span>
                        <span :class="['font-bold tabular-nums', adjustForm.qty < 0 ? 'text-danger' : 'text-success']">
                            {{ adjustForm.qty > 0 ? `+${adjustForm.qty}` : adjustForm.qty }} pcs
                        </span>
                    </div>
                    <div class="flex justify-between border-t border-line-200 pt-1 font-bold">
                        <span class="text-ink-900">Projected Balance:</span>
                        <span :class="['tabular-nums', projectedAdjustedStock < 0 ? 'text-danger' : 'text-teal-800']">
                            {{ projectedAdjustedStock }} pcs
                        </span>
                    </div>
                    <div v-if="projectedAdjustedStock < 0 && !allowNegativeStock" class="text-danger font-bold text-[11px] pt-1">
                         Negative stock is prohibited by system settings.
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <FormField label="Adjustment Date" :error="adjustForm.errors.adjusted_on" required>
                        <input
                            v-model="adjustForm.adjusted_on"
                            type="date"
                            class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-ink-900 rounded-none focus:outline-teal-700"
                            required
                        />
                    </FormField>

                    <FormField label="Reason" :error="adjustForm.errors.reason" required>
                        <select
                            v-model="adjustForm.reason"
                            class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-ink-900 rounded-none focus:outline-teal-700"
                            required
                        >
                            <option value="damaged">Damaged</option>
                            <option value="lost">Lost</option>
                            <option value="count_correction">Count Correction</option>
                            <option value="other">Other</option>
                        </select>
                    </FormField>
                </div>

                <FormField
                    label="Signed Quantity (+ to add, − to deduct)"
                    :error="adjustForm.errors.qty"
                    help-text="Use negative numbers (e.g. -2) for damage or loss"
                    required
                >
                    <input
                        v-model.number="adjustForm.qty"
                        type="number"
                        class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-right tabular-nums text-ink-900 rounded-none focus:outline-teal-700"
                        required
                    />
                </FormField>

                <FormField
                    label="Note"
                    :error="adjustForm.errors.note"
                    :help-text="adjustForm.reason === 'other' ? 'Required when reason is Other' : 'Optional notes'"
                    :required="adjustForm.reason === 'other'"
                >
                    <input
                        v-model="adjustForm.note"
                        type="text"
                        placeholder="Provide details on this adjustment"
                        class="w-full bg-white border border-line-300 px-3 py-2 text-sm text-ink-900 rounded-none focus:outline-teal-700"
                        :required="adjustForm.reason === 'other'"
                    />
                </FormField>
            </form>

            <template #footer>
                <AppButton variant="secondary" size="sm" @click="isAdjustOpen = false">
                    Cancel
                </AppButton>
                <AppButton
                    variant="primary"
                    size="sm"
                    :loading="adjustForm.processing"
                    @click="submitAdjust"
                >
                    Save Adjustment
                </AppButton>
            </template>
        </SideDrawer>

        <!-- ==================== STOCK MOVEMENTS DRAWER ==================== -->
        <SideDrawer
            :open="isMovementsOpen"
            :title="movementProduct ? `Movement Ledger — ${movementProduct.name}` : 'Stock Movement History'"
            :subtitle="movementProduct ? `SKU: ${movementProduct.sku} · Current Stock: ${movementProduct.stock_qty} pcs` : ''"
            width-class="sm:max-w-[620px]"
            @close="isMovementsOpen = false"
        >
            <div v-if="loadingMovements" class="p-8 text-center text-ink-500 text-sm">
                <AppIcon name="spinner" :size="24" class="animate-spin mb-2" />
                <div>Loading append-only stock movement ledger...</div>
            </div>

            <div v-else-if="movementsList.length === 0" class="p-8 text-center text-ink-500 text-sm">
                No movements recorded for this product yet.
            </div>

            <div v-else class="space-y-3">
                <div class="text-xs text-ink-600 bg-sunken p-2.5 border border-line-200">
                    ℹ️ Movements are append-only. Cached product stock equals the exact sum of all signed movement quantities.
                </div>

                <div class="border border-line-200 overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead class="bg-sunken border-b border-line-200">
                            <tr>
                                <th class="px-3 py-2 font-semibold text-ink-700">Date / Time</th>
                                <th class="px-3 py-2 font-semibold text-ink-700">Type</th>
                                <th class="px-3 py-2 font-semibold text-ink-700 text-right">Qty</th>
                                <th class="px-3 py-2 font-semibold text-ink-700 text-right">Balance</th>
                                <th class="px-3 py-2 font-semibold text-ink-700">Note / By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-200">
                            <tr v-for="m in movementsList" :key="m.id" class="hover:bg-teal-50">
                                <td class="px-3 py-2 tabular-nums">
                                    <div class="font-medium text-ink-900">{{ m.movement_date }}</div>
                                    <div class="text-[10px] text-ink-500">{{ m.created_at }}</div>
                                </td>
                                <td class="px-3 py-2">
                                    <StatusBadge
                                        :tone="getMovementTypeTone(m.type)"
                                        :label="m.type.toUpperCase()"
                                        size="sm"
                                    />
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums font-bold">
                                    <span :class="m.qty > 0 ? 'text-success' : 'text-danger'">
                                        {{ m.qty > 0 ? `+${m.qty}` : m.qty }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums font-semibold text-ink-900">
                                    {{ m.balance_after }}
                                </td>
                                <td class="px-3 py-2 text-ink-600">
                                    <div>{{ m.note || '—' }}</div>
                                    <div class="text-[10px] text-ink-400">By {{ m.creator_name }}</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </SideDrawer>

        <!-- ==================== EXCEL IMPORT MODAL ==================== -->
        <SideDrawer
            :open="isImportOpen"
            title="Excel / CSV Product Import"
            subtitle="PRD §8.1 · Upload, preview row-by-row validation, and commit valid items"
            width-class="sm:max-w-[760px]"
            @close="isImportOpen = false"
        >
            <div class="space-y-4">
                <!-- Step 1: Template download -->
                <div class="bg-sunken border border-line-200 p-3 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                    <div>
                        <div class="font-bold text-xs text-ink-900">Download Template</div>
                        <div class="text-[11px] text-ink-600">Columns: Name, SKU, Category, Cost price, Selling price, Opening stock, Min stock</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <a
                            href="/inventory/products/import/template?format=xlsx"
                            target="_blank"
                            class="px-2.5 py-1 text-xs border border-line-300 bg-white hover:bg-teal-50 text-ink-900 font-medium inline-flex items-center gap-1"
                        >
                            <AppIcon name="download" :size="13" />
                            XLSX
                        </a>
                        <a
                            href="/inventory/products/import/template?format=csv"
                            target="_blank"
                            class="px-2.5 py-1 text-xs border border-line-300 bg-white hover:bg-teal-50 text-ink-900 font-medium inline-flex items-center gap-1"
                        >
                            <AppIcon name="download" :size="13" />
                            CSV
                        </a>
                    </div>
                </div>

                <!-- Step 2: Upload Box -->
                <div class="border-2 border-dashed border-line-300 p-6 text-center hover:border-teal-700 transition-colors bg-white">
                    <input
                        ref="fileInputRef"
                        type="file"
                        accept=".xlsx,.xls,.csv"
                        class="hidden"
                        @change="onFileSelected"
                    />
                    <AppIcon name="download" :size="32" class="text-ink-400 mb-2" />
                    <div class="text-sm font-semibold text-ink-900">
                        {{ importFile ? importFile.name : 'Select or drag your Excel (.xlsx) or CSV file' }}
                    </div>
                    <p class="text-xs text-ink-500 mt-1">Maximum file size: 5MB</p>
                    <div class="mt-4">
                        <AppButton
                            variant="secondary"
                            size="sm"
                            :loading="parsingImport"
                            @click="fileInputRef?.click()"
                        >
                            {{ importFile ? 'Choose Different File' : 'Browse Files' }}
                        </AppButton>
                    </div>
                </div>

                <!-- Step 3: Preview Table -->
                <div v-if="importPreviewData" class="space-y-3">
                    <!-- Preview Summary Banner -->
                    <div class="flex items-center justify-between bg-sunken p-3 border border-line-200 text-xs">
                        <div>
                            <span class="font-bold text-ink-900">{{ importPreviewData.filename }}:</span>
                            <span class="ml-2 text-ink-700">{{ importPreviewData.total_rows }} total rows parsed</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="bg-success text-white px-2 py-0.5 text-[10px] font-bold uppercase">
                                {{ importPreviewData.valid_count }} Valid
                            </span>
                            <span v-if="importPreviewData.invalid_count > 0" class="bg-danger text-white px-2 py-0.5 text-[10px] font-bold uppercase">
                                {{ importPreviewData.invalid_count }} Errors
                            </span>
                        </div>
                    </div>

                    <!-- Row-level Preview Table -->
                    <div class="max-h-64 overflow-y-auto border border-line-200">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead class="bg-sunken border-b border-line-200 sticky top-0 z-10">
                                <tr>
                                    <th class="px-2.5 py-1.5 font-semibold text-ink-700">Row</th>
                                    <th class="px-2.5 py-1.5 font-semibold text-ink-700">Name</th>
                                    <th class="px-2.5 py-1.5 font-semibold text-ink-700">SKU</th>
                                    <th class="px-2.5 py-1.5 font-semibold text-ink-700 text-right">Price</th>
                                    <th class="px-2.5 py-1.5 font-semibold text-ink-700 text-right">Stock</th>
                                    <th class="px-2.5 py-1.5 font-semibold text-ink-700 text-center">Status / Errors</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line-200">
                                <tr
                                    v-for="r in importPreviewData.rows"
                                    :key="r.row_number"
                                    :class="r.is_valid ? 'bg-white' : 'bg-danger-50'"
                                >
                                    <td class="px-2.5 py-1.5 tabular-nums text-ink-500 font-mono">
                                        #{{ r.row_number }}
                                    </td>
                                    <td class="px-2.5 py-1.5 font-medium text-ink-900">
                                        {{ r.name || '—' }}
                                    </td>
                                    <td class="px-2.5 py-1.5 font-mono text-ink-700">
                                        {{ r.sku || '(Auto)' }}
                                    </td>
                                    <td class="px-2.5 py-1.5 text-right tabular-nums text-ink-900">
                                        {{ formatMoney(r.selling_price) }}
                                    </td>
                                    <td class="px-2.5 py-1.5 text-right tabular-nums font-semibold">
                                        {{ r.opening_stock }} pcs
                                    </td>
                                    <td class="px-2.5 py-1.5">
                                        <div v-if="r.is_valid" class="text-center text-success font-bold text-[10px]">
                                            VALID
                                        </div>
                                        <div v-else class="space-y-0.5">
                                            <span
                                                v-for="(err, eIdx) in r.errors"
                                                :key="eIdx"
                                                class="block text-danger text-[10px] font-medium leading-tight"
                                            >
                                                • {{ err }}
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <template #footer>
                <AppButton variant="secondary" size="sm" @click="isImportOpen = false">
                    Cancel
                </AppButton>
                <AppButton
                    v-if="importPreviewData"
                    variant="primary"
                    size="sm"
                    :disabled="importPreviewData.valid_count === 0"
                    :loading="committingImport"
                    @click="commitImportRows"
                >
                    Import {{ importPreviewData.valid_count }} Valid Products
                </AppButton>
            </template>
        </SideDrawer>

        <!-- ==================== CATEGORIES MODAL ==================== -->
        <SideDrawer
            :open="isCategoriesModalOpen"
            title="Product Categories"
            subtitle="Organize finished goods into categories"
            width-class="sm:max-w-[480px]"
            @close="isCategoriesModalOpen = false"
        >
            <div class="space-y-4">
                <!-- Add Category Form -->
                <div class="flex items-center gap-2">
                    <input
                        v-model="newCategoryName"
                        type="text"
                        placeholder="New category name (e.g. Card Holders)"
                        class="flex-1 bg-white border border-line-300 px-3 py-2 text-sm text-ink-900 rounded-none focus:outline-teal-700"
                        @keydown.enter="addCategory"
                    />
                    <AppButton variant="primary" size="sm" @click="addCategory">
                        Add
                    </AppButton>
                </div>

                <!-- Categories List -->
                <div class="border border-line-200 divide-y divide-line-200">
                    <div v-if="categoryList.length === 0" class="p-4 text-center text-ink-500 text-xs">
                        No categories created yet.
                    </div>

                    <div
                        v-for="cat in categoryList"
                        :key="cat.id"
                        class="p-3 flex items-center justify-between text-sm hover:bg-teal-50"
                    >
                        <div v-if="editingCatId === cat.id" class="flex-1 flex items-center gap-2 mr-2">
                            <input
                                v-model="editingCatName"
                                type="text"
                                class="w-full bg-white border border-teal-700 px-2 py-1 text-xs text-ink-900 rounded-none focus:outline-none"
                                @keydown.enter="saveEditCategory(cat)"
                                @keydown.esc="editingCatId = null"
                            />
                            <button
                                type="button"
                                class="text-teal-700 hover:text-teal-900 p-1"
                                @click="saveEditCategory(cat)"
                            >
                                <AppIcon name="check" :size="14" />
                            </button>
                            <button
                                type="button"
                                class="text-ink-500 hover:text-ink-900 p-1"
                                @click="editingCatId = null"
                            >
                                <AppIcon name="close" :size="14" />
                            </button>
                        </div>
                        <div v-else class="flex-1">
                            <span class="font-semibold text-ink-900">{{ cat.name }}</span>
                            <span v-if="cat.products_count !== undefined" class="text-xs text-ink-500 ml-2">
                                ({{ cat.products_count }} products)
                            </span>
                        </div>

                        <div v-if="editingCatId !== cat.id" class="flex items-center gap-1">
                            <button
                                type="button"
                                class="p-1 text-ink-600 hover:text-ink-900"
                                title="Rename"
                                @click="startEditCategory(cat)"
                            >
                                <AppIcon name="edit" :size="14" />
                            </button>
                            <button
                                type="button"
                                class="p-1 text-ink-500 hover:text-danger"
                                title="Delete"
                                @click="deleteCategory(cat)"
                            >
                                <AppIcon name="trash" :size="14" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <AppButton variant="secondary" size="sm" @click="isCategoriesModalOpen = false">
                    Close
                </AppButton>
            </template>
        </SideDrawer>

        <!-- ==================== CONFIRM ARCHIVE DIALOG ==================== -->
        <ConfirmDialog
            :open="isArchiveOpen"
            title="Archive Product"
            :description="`Are you sure you want to archive '${productToArchive?.name}'? The product will be soft-deleted. Historical stock movements and sales invoice lines will remain fully intact.`"
            :danger="true"
            confirm-label="Archive Product"
            @confirm="submitArchive"
            @cancel="isArchiveOpen = false"
        />
    </div>
</template>
