<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import PageHeader from '@/components/tasa/PageHeader.vue';
import StatCard from '@/components/tasa/StatCard.vue';
import AppButton from '@/components/tasa/AppButton.vue';
import StatusBadge from '@/components/tasa/StatusBadge.vue';
import { formatMoney } from '@/lib/formatters';
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <PageHeader
            title="Dashboard Overview"
            subtitle="Tasa POS Foundation & Global Visual System (Prompt 1 Baseline)"
        >
            <template #actions>
                <Link href="/design">
                    <AppButton variant="secondary" icon="shield">
                        View Design System Specimen
                    </AppButton>
                </Link>
                <Link href="/sales/create">
                    <AppButton variant="primary" icon="plus">
                        New Sale
                    </AppButton>
                </Link>
            </template>
        </PageHeader>

        <!-- KPI Summary Cards Strip -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <StatCard
                title="Today's Sales"
                :value="formatMoney(0, 0)"
                subtext="0 sales recorded today"
                :is-primary="true"
            />
            <StatCard
                title="This Month"
                :value="formatMoney(0, 0)"
                subtext="0 invoices (current month)"
            />
            <StatCard
                title="COD To Receive"
                :value="formatMoney(0, 0)"
                subtext="0 pending Pathao settlements"
            />
            <StatCard
                title="Low Stock Alerts"
                value="0 Items"
                subtext="Stock tracking active"
            />
        </div>

        <!-- Quick Status & Overview Card -->
        <div class="bg-surface border border-line-200 p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-line-200 pb-3">
                <div>
                    <h2 class="text-base font-bold text-ink-900">
                        System Foundation & Visual Architecture
                    </h2>
                    <p class="text-xs text-ink-600 mt-0.5">
                        Prompt 1 has established the global design system, typography, tokens, and responsive shell.
                    </p>
                </div>
                <StatusBadge label="Phase 0 Ready" tone="success" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2 text-xs">
                <div class="p-4 bg-page border border-line-200">
                    <div class="font-bold text-ink-900 mb-1">Visual Tokens</div>
                    <div class="text-ink-600">
                        Deep Teal (#0B6E5E), 1px solid borders, 0px square corners, no gradients, and strictly light-mode.
                    </div>
                </div>

                <div class="p-4 bg-page border border-line-200">
                    <div class="font-bold text-ink-900 mb-1">Typography & Formatting</div>
                    <div class="text-ink-600">
                        Self-hosted Hanken Grotesk with Noto Sans Bengali fallback. Tabular numerals and BDT (৳) South-Asian digit grouping.
                    </div>
                </div>

                <div class="p-4 bg-page border border-line-200">
                    <div class="font-bold text-ink-900 mb-1">Responsive Shell</div>
                    <div class="text-ink-600">
                        Desktop 248px sidebar, tablet 72px icon rail, and mobile 64px bottom navigation with 5 dedicated slots.
                    </div>
                </div>
            </div>

            <div class="pt-4 flex items-center justify-between">
                <span class="text-xs text-ink-500">
                    Next Phase: Full Database Schema, Migrations, Models, Seeders, and Integrity Tests (Prompt 2).
                </span>
                <Link href="/design">
                    <AppButton variant="secondary" size="sm" icon-right="chevron-right">
                        Explore Design Components
                    </AppButton>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
