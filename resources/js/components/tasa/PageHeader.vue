<script setup lang="ts">
interface BreadcrumbItem {
    label: string;
    href?: string;
}

interface Props {
    title: string;
    subtitle?: string;
    breadcrumbs?: BreadcrumbItem[];
}

defineProps<Props>();
</script>

<template>
    <div class="mb-6 w-full">
        <!-- Breadcrumbs (for depth >= 3) -->
        <nav v-if="breadcrumbs && breadcrumbs.length > 0" class="mb-2 text-xs text-ink-500" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-2">
                <li v-for="(item, index) in breadcrumbs" :key="index" class="inline-flex items-center">
                    <span v-if="index > 0" class="mx-1 text-line-400">/</span>
                    <a
                        v-if="item.href && index < breadcrumbs.length - 1"
                        :href="item.href"
                        class="hover:text-teal-700 transition-colors"
                    >
                        {{ item.label }}
                    </a>
                    <span v-else class="text-ink-700 font-medium">
                        {{ item.label }}
                    </span>
                </li>
            </ol>
        </nav>

        <!-- Header Content Row -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-ink-900 md:text-3xl">
                    {{ title }}
                </h1>
                <p v-if="subtitle" class="mt-1 text-sm text-ink-600">
                    {{ subtitle }}
                </p>
            </div>

            <!-- Actions Container -->
            <div v-if="$slots.actions" class="flex flex-wrap items-center gap-3">
                <slot name="actions" />
            </div>
        </div>
    </div>
</template>
