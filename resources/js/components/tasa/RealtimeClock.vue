<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';

const TIMEZONE = 'Asia/Dhaka';

const timeFormatter = new Intl.DateTimeFormat('en-US', {
    timeZone: TIMEZONE,
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: true,
});

const fullDateFormatter = new Intl.DateTimeFormat('en-GB', {
    timeZone: TIMEZONE,
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

const compactDateFormatter = new Intl.DateTimeFormat('en-GB', {
    timeZone: TIMEZONE,
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

const mobileDateFormatter = new Intl.DateTimeFormat('en-GB', {
    timeZone: TIMEZONE,
    day: 'numeric',
    month: 'short',
});

const currentTime = ref('');
const currentDate = ref('');
const compactDate = ref('');
const mobileDate = ref('');

const updateClock = () => {
    const now = new Date();
    currentTime.value = timeFormatter.format(now);
    currentDate.value = fullDateFormatter.format(now);
    compactDate.value = compactDateFormatter.format(now);
    mobileDate.value = mobileDateFormatter.format(now);
};

// Immediate initial tick
updateClock();

let timer: ReturnType<typeof setInterval> | null = null;

const onVisibilityChange = () => {
    if (document.visibilityState === 'visible') {
        updateClock();
    }
};

onMounted(() => {
    updateClock();
    timer = setInterval(updateClock, 1000);
    document.addEventListener('visibilitychange', onVisibilityChange);
});

onUnmounted(() => {
    if (timer) {
        clearInterval(timer);
        timer = null;
    }
    document.removeEventListener('visibilitychange', onVisibilityChange);
});
</script>

<template>
    <div
        class="flex items-center gap-2 sm:gap-2.5 px-2 sm:px-2.5 py-1 bg-surface border border-line-200 select-none flex-shrink-0"
        title="Bangladesh Standard Time (UTC+6) — Asia/Dhaka"
        aria-label="Current Bangladesh Date and Time"
    >
        <!-- Teal clock icon container -->
        <div class="w-6 h-6 sm:w-7 sm:h-7 rounded-none bg-teal-50 border border-teal-200 flex items-center justify-center text-teal-700 flex-shrink-0">
            <svg
                class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-teal-700"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <circle cx="12" cy="12" r="10" />
                <polyline points="12 6 12 12 16 14" />
            </svg>
        </div>

        <div class="flex flex-col justify-center min-w-0">
            <!-- Digital time display & Timezone tag -->
            <div class="flex items-center gap-1.5 leading-none">
                <span class="font-mono text-xs sm:text-sm font-bold text-ink-900 tracking-tight tabular-nums">
                    {{ currentTime }}
                </span>
                <span class="hidden sm:inline-flex items-center px-1 py-0.5 bg-teal-50 text-teal-800 border border-teal-200 text-[9px] font-mono font-semibold uppercase tracking-wider leading-none">
                    Asia/Dhaka
                </span>
                <span class="sm:hidden px-1 py-0.2 bg-teal-50 text-teal-800 border border-teal-200 text-[8px] font-mono font-bold leading-none">
                    BST
                </span>
            </div>

            <!-- Date Display (Full on lg+, compact on sm/md, mobile on xs) -->
            <div class="mt-0.5 leading-none text-ink-500">
                <span class="hidden lg:inline text-[11px] font-medium text-ink-600">
                    {{ currentDate }}
                </span>
                <span class="hidden sm:inline lg:hidden text-[10px] font-medium text-ink-600">
                    {{ compactDate }}
                </span>
                <span class="sm:hidden text-[9px] font-mono text-ink-500">
                    {{ mobileDate }} · BST
                </span>
            </div>
        </div>
    </div>
</template>
