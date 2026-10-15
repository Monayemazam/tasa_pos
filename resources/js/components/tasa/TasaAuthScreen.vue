<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppButton from '@/components/tasa/AppButton.vue';
import AppIcon from '@/components/tasa/AppIcon.vue';

interface Props {
    status?: string;
    canResetPassword?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    status: '',
    canResetPassword: true,
});

const page = usePage();
const shop = computed(() => (page.props as any).shop || {});
const shopLogo = computed(() => shop.value.logo || null);
const shopName = computed(() => shop.value.name || 'TASA');

const showPassword = ref(false);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login', {
        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <Head :title="`Sign In — ${shopName} POS`" />

    <div class="min-h-screen bg-[#F4F6F6] text-[#0F1B1A] flex flex-col justify-between selection:bg-[#E6F2EF] selection:text-[#0B6E5E]">
        <!-- TOP BRAND STRIP -->
        <header class="border-b border-[#D5DCDB] bg-white px-4 sm:px-8 py-3.5 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <img
                    v-if="shopLogo"
                    :src="shopLogo"
                    :alt="shopName"
                    class="h-9 w-auto max-w-[140px] object-contain"
                />
                <div
                    v-else
                    class="w-8 h-8 bg-[#0B6E5E] text-white flex items-center justify-center font-black text-sm tracking-wider"
                >
                    T
                </div>
                <div>
                    <div class="flex items-center space-x-2 leading-none">
                        <span class="text-lg font-black tracking-widest text-[#0B6E5E]">{{ shopName }}</span>
                        <span class="text-[10px] uppercase font-bold tracking-wider px-1.5 py-0.5 bg-[#E6F2EF] text-[#0B6E5E] border border-[#BFDDD6]">
                            POS
                        </span>
                    </div>
                    <p class="text-[11px] text-[#5F6F6D] font-medium leading-none mt-0.5">
                        Leather Crafts &amp; Retail Operations
                    </p>
                </div>
            </div>

            <div class="flex items-center space-x-4 text-xs text-[#5F6F6D]">
                <div class="hidden sm:flex items-center space-x-1.5 font-mono">
                    <span class="inline-block w-2 h-2 bg-[#1B7F3B]"></span>
                    <span>Dhaka (UTC+6)</span>
                </div>
                <div class="text-[11px] font-semibold text-[#0B6E5E] bg-[#F0F7F5] px-2 py-1 border border-[#BFDDD6]">
                    ৳ BDT Store Currency
                </div>
            </div>
        </header>

        <!-- MAIN CONTENT AREA -->
        <main class="flex-1 flex items-center justify-center p-4 sm:p-6 md:p-10">
            <div class="w-full max-w-4xl bg-white border border-[#D5DCDB] shadow-none grid grid-cols-1 lg:grid-cols-12 overflow-hidden">
                
                <!-- LEFT BRAND / OPERATIONS HIGHLIGHT SECTION (DESKTOP & TABLET) -->
                <div class="lg:col-span-5 bg-[#074538] text-white p-6 sm:p-8 flex flex-col justify-between border-b lg:border-b-0 lg:border-r border-[#095A4C]">
                    <div>
                        <div class="inline-flex items-center space-x-2 px-2.5 py-1 bg-[#095A4C] border border-[#0B6E5E] text-[11px] uppercase tracking-wider font-semibold mb-6">
                            <span>Internal Business System</span>
                        </div>

                        <div v-if="shopLogo" class="mb-4 bg-white p-2.5 inline-block shadow-xs">
                            <img :src="shopLogo" :alt="shopName" class="h-10 w-auto max-w-[160px] object-contain" />
                        </div>

                        <h1 class="text-2xl sm:text-3xl font-black tracking-tight leading-tight mb-2">
                            {{ shopName }} POS
                        </h1>
                        <p class="text-sm text-[#BFDDD6] font-medium mb-1">
                            টাসা পিওএস — লেদার গুডস রিটেইল ও অর্ডার সিস্টেম
                        </p>
                        <p class="text-xs text-[#A1C9C0] leading-relaxed mb-6">
                            Comprehensive point-of-sale, raw material inventory, Pathao courier logistics, worker payroll &amp; financial reporting for leather goods makers.
                        </p>

                        <!-- Operational Capabilities List -->
                        <div class="space-y-4 pt-2 border-t border-[#095A4C]/80">
                            <div class="flex items-start space-x-3">
                                <div class="mt-0.5 w-5 h-5 bg-[#095A4C] border border-[#0B6E5E] flex items-center justify-center flex-shrink-0 text-white">
                                    <AppIcon name="cart" :size="13" />
                                </div>
                                <div>
                                    <h2 class="text-xs font-bold text-white uppercase tracking-wider">Fast POS &amp; Courier</h2>
                                    <p class="text-[11px] text-[#A1C9C0] leading-normal">
                                        Sales in &lt;1 min with Pathao auto-dispatch &amp; COD collection.
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-start space-x-3">
                                <div class="mt-0.5 w-5 h-5 bg-[#095A4C] border border-[#0B6E5E] flex items-center justify-center flex-shrink-0 text-white">
                                    <AppIcon name="leather" :size="13" />
                                </div>
                                <div>
                                    <h2 class="text-xs font-bold text-white uppercase tracking-wider">3-Tier Inventory</h2>
                                    <p class="text-[11px] text-[#A1C9C0] leading-normal">
                                        Finished goods, raw leather (sq ft) &amp; weighted-average valuation.
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-start space-x-3">
                                <div class="mt-0.5 w-5 h-5 bg-[#095A4C] border border-[#0B6E5E] flex items-center justify-center flex-shrink-0 text-white">
                                    <AppIcon name="payroll" :size="13" />
                                </div>
                                <div>
                                    <h2 class="text-xs font-bold text-white uppercase tracking-wider">Payroll &amp; Ledger</h2>
                                    <p class="text-[11px] text-[#A1C9C0] leading-normal">
                                        Salaried sheets, piece/day-rate worker wages &amp; weekly payouts.
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-start space-x-3">
                                <div class="mt-0.5 w-5 h-5 bg-[#095A4C] border border-[#0B6E5E] flex items-center justify-center flex-shrink-0 text-white">
                                    <AppIcon name="reports" :size="13" />
                                </div>
                                <div>
                                    <h2 class="text-xs font-bold text-white uppercase tracking-wider">Real-time P&amp;L (A–Q)</h2>
                                    <p class="text-[11px] text-[#A1C9C0] leading-normal">
                                        Source-level gross/net profit tracking with zero cached drift.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Left Footer Badges -->
                    <div class="pt-6 mt-6 border-t border-[#095A4C] flex items-center justify-between text-[11px] text-[#A1C9C0] font-mono">
                        <span>v1.0 • Enterprise POS</span>
                        <span>Locked Audit Trail</span>
                    </div>
                </div>

                <!-- RIGHT SIGN IN FORM SECTION -->
                <div class="lg:col-span-7 p-6 sm:p-10 flex flex-col justify-between bg-white">
                    <div>
                        <!-- Header Greeting -->
                        <div class="mb-4">
                            <h2 class="text-2xl font-black text-[#0F1B1A] tracking-tight">
                                Welcome back
                            </h2>
                            <p class="text-xs text-[#5F6F6D] font-medium mt-1">
                                Sign in to manage your store operations • আপনার শপে লগইন করুন
                            </p>
                        </div>

                        <!-- Status Alert (e.g. Password Reset Confirmation) -->
                        <div
                            v-if="status"
                            class="mb-6 p-3 bg-[#E7F4EA] border border-[#A6D9B6] text-[#14602C] text-xs font-medium flex items-center space-x-2"
                        >
                            <AppIcon name="check-circle" :size="16" class="text-[#1B7F3B] flex-shrink-0" />
                            <span>{{ status }}</span>
                        </div>

                        <!-- Top Error Summary Alert (if authentication failed) -->
                        <div
                            v-if="form.errors.email || form.errors.password"
                            class="mb-6 p-3 bg-[#FDECEA] border border-[#FECDCA] text-[#8E1B12] text-xs font-medium flex items-start space-x-2"
                        >
                            <AppIcon name="danger" :size="16" class="text-[#B42318] flex-shrink-0 mt-0.5" />
                            <div>
                                <span class="font-bold">Authentication failed:</span>
                                <span class="block mt-0.5">{{ form.errors.email || form.errors.password }}</span>
                            </div>
                        </div>

                        <!-- Sign In Form -->
                        <form @submit.prevent="submit" class="space-y-5" novalidate>
                            <!-- Email Input Field -->
                            <div>
                                <label
                                    for="email"
                                    class="block text-xs font-bold uppercase tracking-wider text-[#2B3A38] mb-1.5"
                                >
                                    Email Address <span class="text-[#B42318]">*</span>
                                </label>
                                <div class="relative">
                                    <input
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                        name="email"
                                        required
                                        autofocus
                                        autocomplete="email"
                                        placeholder="name@tasa.com"
                                        :class="[
                                            'w-full h-11 px-3.5 text-sm bg-white border font-mono transition-colors focus:outline-none rounded-none',
                                            form.errors.email
                                                ? 'border-[#B42318] focus:border-[#B42318] focus:ring-1 focus:ring-[#B42318]'
                                                : 'border-[#B9C4C2] focus:border-[#0B6E5E] focus:ring-1 focus:ring-[#0B6E5E]',
                                        ]"
                                    />
                                </div>
                                <p v-if="form.errors.email" class="text-xs text-[#B42318] font-medium mt-1">
                                    {{ form.errors.email }}
                                </p>
                            </div>

                            <!-- Password Input Field -->
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label
                                        for="password"
                                        class="block text-xs font-bold uppercase tracking-wider text-[#2B3A38]"
                                    >
                                        Password <span class="text-[#B42318]">*</span>
                                    </label>

                                    <Link
                                        v-if="canResetPassword"
                                        href="/forgot-password"
                                        class="text-xs text-[#0B6E5E] hover:underline font-semibold focus:outline-none focus:ring-1 focus:ring-[#0B6E5E]"
                                        tabindex="4"
                                    >
                                        Forgot password?
                                    </Link>
                                </div>

                                <div class="relative">
                                    <input
                                        id="password"
                                        v-model="form.password"
                                        :type="showPassword ? 'text' : 'password'"
                                        name="password"
                                        required
                                        autocomplete="current-password"
                                        placeholder="••••••••"
                                        :class="[
                                            'w-full h-11 pl-3.5 pr-11 text-sm bg-white border font-mono transition-colors focus:outline-none rounded-none',
                                            form.errors.password
                                                ? 'border-[#B42318] focus:border-[#B42318] focus:ring-1 focus:ring-[#B42318]'
                                                : 'border-[#B9C4C2] focus:border-[#0B6E5E] focus:ring-1 focus:ring-[#0B6E5E]',
                                        ]"
                                    />

                                    <!-- Visibility Toggle -->
                                    <button
                                        type="button"
                                        @click="showPassword = !showPassword"
                                        :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                        class="absolute inset-y-0 right-0 px-3.5 flex items-center text-[#5F6F6D] hover:text-[#0F1B1A] focus:outline-none"
                                        tabindex="-1"
                                    >
                                        <AppIcon :name="showPassword ? 'close' : 'eye'" :size="16" />
                                    </button>
                                </div>
                                <p v-if="form.errors.password" class="text-xs text-[#B42318] font-medium mt-1">
                                    {{ form.errors.password }}
                                </p>
                            </div>

                            <!-- Remember Me Checkbox -->
                            <div class="flex items-center">
                                <label for="remember" class="flex items-center space-x-2.5 cursor-pointer text-xs text-[#2B3A38] select-none">
                                    <input
                                        id="remember"
                                        v-model="form.remember"
                                        type="checkbox"
                                        class="w-4 h-4 border border-[#7F8E8C] text-[#0B6E5E] focus:ring-0 focus:ring-offset-0 rounded-none cursor-pointer"
                                    />
                                    <span class="font-medium">Remember this browser session</span>
                                </label>
                            </div>

                            <!-- Primary Submit Button -->
                            <div class="pt-2">
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="w-full h-11 bg-[#0B6E5E] hover:bg-[#095A4C] active:bg-[#074538] text-white text-sm font-bold uppercase tracking-wider transition-colors flex items-center justify-center space-x-2 border border-[#095A4C] rounded-none focus:outline-none focus:ring-2 focus:ring-[#0B6E5E] focus:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed"
                                >
                                    <span
                                        v-if="form.processing"
                                        class="inline-block w-4 h-4 border-2 border-white border-t-transparent animate-spin mr-2"
                                    />
                                    <span>{{ form.processing ? 'Signing In...' : 'Sign In to Store' }}</span>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Security Footnote -->
                    <div class="pt-6 mt-6 border-t border-[#D5DCDB] text-[11px] text-[#5F6F6D] flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
                        <span class="flex items-center space-x-1.5">
                            <AppIcon name="shield" :size="14" class="text-[#0B6E5E]" />
                            <span>Authorized personnel only. All access is logged.</span>
                        </span>
                        <span class="font-mono text-[#7F8E8C]">POS • TASA Leather</span>
                    </div>
                </div>

            </div>
        </main>

        <!-- FOOTER -->
        <footer class="border-t border-[#D5DCDB] bg-white py-3 px-4 sm:px-8 text-center text-xs text-[#5F6F6D] flex flex-col sm:flex-row items-center justify-between gap-2">
            <div>
                &copy; {{ new Date().getFullYear() }} Tasa Leather Goods. All rights reserved.
            </div>
            <div class="text-[11px] font-mono text-[#7F8E8C]">
                Dhaka, Bangladesh • Timezone: Asia/Dhaka
            </div>
        </footer>
    </div>
</template>
