<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '@/components/tasa/PageHeader.vue';
import AppButton from '@/components/tasa/AppButton.vue';
import AppIcon from '@/components/tasa/AppIcon.vue';
import TablePagination from '@/components/tasa/TablePagination.vue';
import EmptyState from '@/components/tasa/EmptyState.vue';

interface UserOption {
    id: number;
    name: string;
    email: string;
}

interface AuditLogItem {
    id: number;
    user: {
        id: number;
        name: string;
        email: string;
    } | null;
    event: string;
    auditable_type: string;
    auditable_type_full: string;
    auditable_id: number | null;
    old_values: Record<string, any> | null;
    new_values: Record<string, any> | null;
    ip_address: string | null;
    user_agent: string | null;
    created_at: string;
}

interface PaginatedLogs {
    data: AuditLogItem[];
    current_page: number;
    last_page: number;
    total: number;
    from: number;
    to: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

const props = defineProps<{
    logs: PaginatedLogs;
    users: UserOption[];
    distinctEvents: string[];
    filters: {
        search?: string;
        event?: string;
        user_id?: string;
        date_from?: string;
        date_to?: string;
    };
}>();

// Filter states
const search = ref(props.filters.search || '');
const event = ref(props.filters.event || '');
const userId = ref(props.filters.user_id || '');
const dateFrom = ref(props.filters.date_from || '');
const dateTo = ref(props.filters.date_to || '');

const handleFilter = () => {
    router.get(
        '/settings/audit-logs',
        {
            search: search.value || undefined,
            event: event.value || undefined,
            user_id: userId.value || undefined,
            date_from: dateFrom.value || undefined,
            date_to: dateTo.value || undefined,
        },
        { preserveState: true, replace: true }
    );
};

const handleReset = () => {
    search.value = '';
    event.value = '';
    userId.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    router.get('/settings/audit-logs', {}, { preserveState: true, replace: true });
};

// Inspector Modal State
const isInspectorOpen = ref(false);
const inspectingLog = ref<AuditLogItem | null>(null);

const inspectPayload = (log: AuditLogItem) => {
    inspectingLog.value = log;
    isInspectorOpen.value = true;
};
</script>

<template>
    <Head title="Audit Trail Logs — Tasa POS" />

        <div class="space-y-6">
            <!-- Header -->
            <PageHeader
                title="System Audit Trail"
                subtitle="Append-only immutable record of all business mutations, logins, and system edits."
            />

            <!-- Filter Controls -->
            <div class="p-4 bg-surface border border-line-200 space-y-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                    <!-- Search Input -->
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-ink-500">
                            <AppIcon name="search" :size="16" />
                        </div>
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search IP, entity, ID..."
                            class="w-full h-[40px] pl-9 pr-3 text-xs text-ink-900 bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-teal-700"
                            @keydown.enter="handleFilter"
                        />
                    </div>

                    <!-- Event Select -->
                    <div>
                        <select
                            v-model="event"
                            class="w-full h-[40px] px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-teal-700"
                            @change="handleFilter"
                        >
                            <option value="">All Events</option>
                            <option v-for="ev in distinctEvents" :key="ev" :value="ev">
                                {{ ev }}
                            </option>
                        </select>
                    </div>

                    <!-- User Select -->
                    <div>
                        <select
                            v-model="userId"
                            class="w-full h-[40px] px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-teal-700"
                            @change="handleFilter"
                        >
                            <option value="">All Users</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">
                                {{ u.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Date From -->
                    <div>
                        <input
                            v-model="dateFrom"
                            type="date"
                            class="w-full h-[40px] px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-teal-700"
                            @change="handleFilter"
                        />
                    </div>

                    <!-- Date To -->
                    <div>
                        <input
                            v-model="dateTo"
                            type="date"
                            class="w-full h-[40px] px-3 text-xs bg-page border border-line-300 rounded-none focus:border-teal-700 focus:outline-teal-700"
                            @change="handleFilter"
                        />
                    </div>
                </div>

                <div class="flex justify-end space-x-2 pt-1 border-t border-line-200">
                    <AppButton variant="secondary" size="sm" @click="handleFilter">
                        Apply Filters
                    </AppButton>
                    <AppButton
                        v-if="filters.search || filters.event || filters.user_id || filters.date_from || filters.date_to"
                        variant="ghost"
                        size="sm"
                        @click="handleReset"
                    >
                        Reset
                    </AppButton>
                </div>
            </div>

            <!-- Audit Table (Strictly Read-Only) -->
            <div class="bg-surface border border-line-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-page border-b border-line-200 text-ink-600 uppercase font-semibold">
                                <th class="py-3 px-4">Date / Time (Dhaka)</th>
                                <th class="py-3 px-4">Operator</th>
                                <th class="py-3 px-4">Event</th>
                                <th class="py-3 px-4">Entity Type</th>
                                <th class="py-3 px-3 text-center">Entity ID</th>
                                <th class="py-3 px-4">IP Address</th>
                                <th class="py-3 px-4 text-right">Payload</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-200">
                            <tr
                                v-for="log in logs.data"
                                :key="log.id"
                                class="hover:bg-teal-50 transition-colors"
                            >
                                <td class="py-3 px-4 font-mono text-ink-700 whitespace-nowrap">
                                    {{ log.created_at }}
                                </td>
                                <td class="py-3 px-4">
                                    <div v-if="log.user" class="font-bold text-ink-900">
                                        {{ log.user.name }}
                                    </div>
                                    <div v-else class="text-ink-400 italic">System / CLI</div>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="inline-block px-2 py-0.5 font-mono text-[11px] font-bold bg-page border border-line-300 text-teal-800">
                                        {{ log.event }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 font-semibold text-ink-800">
                                    {{ log.auditable_type }}
                                </td>
                                <td class="py-3 px-3 text-center font-mono text-ink-600">
                                    {{ log.auditable_id || '—' }}
                                </td>
                                <td class="py-3 px-4 font-mono text-ink-500">
                                    {{ log.ip_address || '127.0.0.1' }}
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <AppButton
                                        variant="ghost"
                                        size="sm"
                                        @click="inspectPayload(log)"
                                    >
                                        Inspect
                                    </AppButton>
                                </td>
                            </tr>
                            <tr v-if="logs.data.length === 0">
                                <td colspan="7" class="p-8 text-center">
                                    <EmptyState
                                        title="No audit entries found"
                                        description="Try adjusting your filter criteria."
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <TablePagination
                    v-if="logs.total > 0"
                    :current-page="logs.current_page"
                    :total-items="logs.total"
                    :per-page="25"
                    @update:current-page="(p: number) => router.get('/settings/audit-logs', { ...filters, page: p }, { preserveState: true })"
                />
            </div>
        </div>

        <!-- PAYLOAD INSPECTOR MODAL -->
        <div v-if="isInspectorOpen && inspectingLog" class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog">
            <div class="fixed inset-0 bg-[#0F1B1A] bg-opacity-40" @click="isInspectorOpen = false" />
            <div class="relative bg-surface border border-line-300 w-full max-w-2xl p-6 space-y-4 z-10 shadow-lg max-h-[85vh] flex flex-col">
                <div class="flex justify-between items-center pb-3 border-b border-line-200">
                    <div>
                        <h2 class="text-base font-bold text-ink-900">
                            Audit Record #{{ inspectingLog.id }} — {{ inspectingLog.event }}
                        </h2>
                        <div class="text-xs text-ink-500 mt-0.5">
                            Target: {{ inspectingLog.auditable_type_full }} (ID: {{ inspectingLog.auditable_id || 'N/A' }})
                        </div>
                    </div>
                    <button type="button" class="text-ink-500 hover:text-ink-900" @click="isInspectorOpen = false">
                        <AppIcon name="close" :size="20" />
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto space-y-4 pr-1 text-xs">
                    <!-- User Agent & IP details -->
                    <div class="p-3 bg-page border border-line-200 space-y-1">
                        <div><strong class="text-ink-700">Timestamp:</strong> {{ inspectingLog.created_at }}</div>
                        <div><strong class="text-ink-700">User:</strong> {{ inspectingLog.user?.name || 'System' }} ({{ inspectingLog.user?.email || 'N/A' }})</div>
                        <div><strong class="text-ink-700">Client IP:</strong> {{ inspectingLog.ip_address }}</div>
                        <div class="truncate"><strong class="text-ink-700">User Agent:</strong> {{ inspectingLog.user_agent || 'N/A' }}</div>
                    </div>

                    <!-- Old Values -->
                    <div>
                        <div class="font-bold text-ink-900 mb-1">Previous Values (Before Mutation):</div>
                        <pre class="p-3 bg-page border border-line-300 font-mono text-[11px] text-ink-800 overflow-x-auto whitespace-pre-wrap">{{ inspectingLog.old_values ? JSON.stringify(inspectingLog.old_values, null, 2) : 'None (Created row)' }}</pre>
                    </div>

                    <!-- New Values -->
                    <div>
                        <div class="font-bold text-ink-900 mb-1">New Values (After Mutation):</div>
                        <pre class="p-3 bg-page border border-line-300 font-mono text-[11px] text-ink-800 overflow-x-auto whitespace-pre-wrap">{{ inspectingLog.new_values ? JSON.stringify(inspectingLog.new_values, null, 2) : 'None (Deleted row)' }}</pre>
                    </div>
                </div>

                <div class="flex justify-end pt-3 border-t border-line-200">
                    <AppButton variant="secondary" @click="isInspectorOpen = false">
                        Close Inspector
                    </AppButton>
                </div>
            </div>
    </div>
</template>
