<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BadgeCheck,
    CalendarDays,
    Check,
    CreditCard,
    Crown,
    Info,
    Mail,
    Receipt,
    ShieldCheck,
    Sparkles,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import PageShell from '@/components/layout/PageShell.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Plan = {
    key: 'free' | 'premium';
    name: string;
    description: string;
    price_monthly: number;
    price_yearly: number;
    features: string[];
    highlight: boolean;
};

type PaymentMethod = {
    key: string;
    name: string;
    description: string;
};

type Invoice = {
    id: number;
    number: string;
    date: string;
    amount: number;
    status: string;
};

const props = defineProps<{
    role: 'eo' | 'company';
    current_plan: 'free' | 'premium';
    current_plan_label: string;
    plans: Plan[];
    payment_methods: PaymentMethod[];
    invoices: Invoice[];
    payment_ready: boolean;
}>();

const selectedPlan = ref<'free' | 'premium'>('premium');
const billingCycle = ref<'monthly' | 'yearly'>('monthly');
const selectedMethod = ref<string>(props.payment_methods[0]?.key ?? 'va');
const infoOpen = ref(false);

const plan = computed(
    () =>
        props.plans.find((item) => item.key === selectedPlan.value) ??
        props.plans[0],
);

const price = computed(() =>
    billingCycle.value === 'yearly'
        ? plan.value.price_yearly
        : plan.value.price_monthly,
);

const savings = computed(() => {
    const monthly = plan.value.price_monthly * 12;

    return monthly > plan.value.price_yearly
        ? monthly - plan.value.price_yearly
        : 0;
});

const summary = computed(() => ({
    plan: plan.value.name,
    cycle: billingCycle.value === 'yearly' ? 'Tahunan' : 'Bulanan',
    price: price.value,
    priceLabel: price.value === 0 ? 'Gratis' : rupiah(price.value),
    savingsLabel: savings.value > 0 ? rupiah(savings.value) : null,
}));

function rupiah(value: number): string {
    return 'Rp ' + value.toLocaleString('id-ID');
}

function choosePlan(key: 'free' | 'premium'): void {
    selectedPlan.value = key;
}
</script>

<template>
    <Head title="Langganan & Pembayaran" />

    <PageShell>
        <PageHeader
            title="Langganan & Pembayaran"
            :description="
                role === 'company'
                    ? 'Kelola paket langganan perusahaan dan siapkan pembayaran untuk fitur yang lebih lengkap.'
                    : 'Kelola paket langganan Event Organizer dan siapkan pembayaran untuk fitur yang lebih lengkap.'
            "
        >
            <template #actions>
                <Link
                    href="/bantuan"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 text-xs font-semibold text-sm-navy transition-colors hover:bg-gray-50"
                >
                    <Info class="h-4 w-4" />
                    Pusat Bantuan
                </Link>
            </template>
        </PageHeader>

        <!-- Status gateway -->
        <div
            v-if="!payment_ready"
            class="mt-5 flex flex-col gap-3 rounded-2xl border border-amber-200 bg-amber-50/70 px-4 py-3 sm:flex-row sm:items-start"
        >
            <span
                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700"
            >
                <Info class="h-5 w-5" />
            </span>
            <div class="min-w-0">
                <p class="text-xs font-semibold text-amber-900">
                    Halaman pembayaran sudah disiapkan, gateway belum aktif
                </p>
                <p class="mt-0.5 text-[11px] leading-relaxed text-amber-800/80">
                    Paket, metode pembayaran, dan ringkasan di bawah adalah
                    pratinjau. Transaksi belum diproses dan status langganan
                    tidak berubah sampai integrasi pembayaran diaktifkan.
                </p>
            </div>
        </div>

        <div
            class="mt-6 grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_294px]"
        >
            <div class="min-w-0 space-y-6">
                <!-- Paket -->
                <section>
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-sm-navy">
                                Pilih Paket
                            </h2>
                            <p class="mt-0.5 text-xs text-sm-card/65">
                                Paket saat ini:
                                <span class="font-semibold text-sm-navy">
                                    {{ current_plan_label }}
                                </span>
                            </p>
                        </div>

                        <div
                            class="inline-flex rounded-lg border border-gray-200 bg-white p-1"
                        >
                            <button
                                type="button"
                                class="rounded-md px-3 py-1.5 text-[11px] font-semibold transition-colors"
                                :class="
                                    billingCycle === 'monthly'
                                        ? 'bg-sm-navy text-white'
                                        : 'text-sm-card/70 hover:text-sm-navy'
                                "
                                @click="billingCycle = 'monthly'"
                            >
                                Bulanan
                            </button>
                            <button
                                type="button"
                                class="rounded-md px-3 py-1.5 text-[11px] font-semibold transition-colors"
                                :class="
                                    billingCycle === 'yearly'
                                        ? 'bg-sm-navy text-white'
                                        : 'text-sm-card/70 hover:text-sm-navy'
                                "
                                @click="billingCycle = 'yearly'"
                            >
                                Tahunan
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <article
                            v-for="item in plans"
                            :key="item.key"
                            class="relative flex h-full flex-col rounded-2xl border bg-white p-5 shadow-sm transition-colors"
                            :class="
                                selectedPlan === item.key
                                    ? 'border-sm-red ring-1 ring-sm-red/20'
                                    : 'border-gray-200'
                            "
                        >
                            <span
                                v-if="item.highlight"
                                class="absolute -top-3 right-4 inline-flex items-center gap-1 rounded-full bg-sm-red px-2.5 py-1 text-[10px] font-semibold text-white shadow-sm"
                            >
                                <Sparkles class="h-3 w-3" />
                                Rekomendasi
                            </span>

                            <div class="flex items-center gap-2">
                                <span
                                    class="flex h-9 w-9 items-center justify-center rounded-full"
                                    :class="
                                        item.key === 'premium'
                                            ? 'bg-sm-red text-white'
                                            : 'bg-gray-100 text-sm-card'
                                    "
                                >
                                    <Crown
                                        v-if="item.key === 'premium'"
                                        class="h-5 w-5"
                                    />
                                    <Wallet v-else class="h-5 w-5" />
                                </span>
                                <div>
                                    <h3
                                        class="text-base font-bold text-sm-navy"
                                    >
                                        {{ item.name }}
                                    </h3>
                                    <p class="text-[11px] text-sm-card/60">
                                        {{
                                            item.key === current_plan
                                                ? 'Paket aktif'
                                                : 'Tersedia untuk diaktifkan'
                                        }}
                                    </p>
                                </div>
                            </div>

                            <p
                                class="mt-3 min-h-[2.6em] text-[11px] leading-relaxed text-sm-card/70"
                            >
                                {{ item.description }}
                            </p>

                            <p class="mt-4 flex items-end gap-1">
                                <span
                                    class="text-2xl leading-none font-bold text-sm-navy"
                                >
                                    {{
                                        item.key === 'free'
                                            ? 'Gratis'
                                            : rupiah(
                                                  billingCycle === 'yearly'
                                                      ? item.price_yearly
                                                      : item.price_monthly,
                                              )
                                    }}
                                </span>
                                <span
                                    v-if="item.key !== 'free'"
                                    class="text-[11px] text-sm-card/60"
                                >
                                    /{{
                                        billingCycle === 'yearly'
                                            ? 'tahun'
                                            : 'bulan'
                                    }}
                                </span>
                            </p>

                            <ul
                                class="mt-4 flex-1 space-y-2 text-[11px] leading-relaxed text-sm-card/80"
                            >
                                <li
                                    v-for="feature in item.features"
                                    :key="feature"
                                    class="flex items-start gap-1.5"
                                >
                                    <Check
                                        class="mt-0.5 h-3.5 w-3.5 shrink-0 text-emerald-600"
                                    />
                                    {{ feature }}
                                </li>
                            </ul>

                            <button
                                type="button"
                                class="mt-5 inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg text-xs font-semibold transition-colors"
                                :class="
                                    selectedPlan === item.key
                                        ? 'bg-sm-navy text-white'
                                        : 'border border-gray-200 bg-white text-sm-navy hover:bg-gray-50'
                                "
                                @click="choosePlan(item.key)"
                            >
                                <BadgeCheck
                                    v-if="selectedPlan === item.key"
                                    class="h-4 w-4"
                                />
                                {{
                                    selectedPlan === item.key
                                        ? 'Paket Dipilih'
                                        : 'Pilih Paket'
                                }}
                            </button>
                        </article>
                    </div>
                </section>

                <!-- Metode pembayaran -->
                <section
                    class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"
                >
                    <div class="flex items-center gap-2">
                        <CreditCard class="h-5 w-5 text-sm-red" />
                        <h2 class="text-lg font-bold text-sm-navy">
                            Metode Pembayaran
                        </h2>
                    </div>
                    <p class="mt-1 text-xs text-sm-card/65">
                        Pilih metode yang paling nyaman. Semua transaksi akan
                        diproses melalui penyedia pembayaran resmi.
                    </p>

                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        <button
                            v-for="method in payment_methods"
                            :key="method.key"
                            type="button"
                            class="flex h-full flex-col items-start rounded-xl border p-3 text-left transition-colors"
                            :class="
                                selectedMethod === method.key
                                    ? 'border-sm-red bg-red-50/50'
                                    : 'border-gray-200 bg-white hover:bg-gray-50'
                            "
                            @click="selectedMethod = method.key"
                        >
                            <span
                                class="flex w-full items-center justify-between gap-2"
                            >
                                <span
                                    class="text-xs font-semibold text-sm-navy"
                                >
                                    {{ method.name }}
                                </span>
                                <span
                                    class="flex h-4 w-4 items-center justify-center rounded-full border"
                                    :class="
                                        selectedMethod === method.key
                                            ? 'border-sm-red bg-sm-red text-white'
                                            : 'border-gray-300 bg-white'
                                    "
                                >
                                    <Check
                                        v-if="selectedMethod === method.key"
                                        class="h-2.5 w-2.5"
                                    />
                                </span>
                            </span>
                            <span
                                class="mt-1 text-[10px] leading-relaxed text-sm-card/60"
                            >
                                {{ method.description }}
                            </span>
                        </button>
                    </div>

                    <p
                        class="mt-3 flex items-center gap-1.5 text-[10px] text-sm-card/55"
                    >
                        <ShieldCheck class="h-3.5 w-3.5" />
                        Data pembayaran tidak disimpan di server SponsorMatch.
                    </p>
                </section>

                <!-- Riwayat pembayaran -->
                <section
                    class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"
                >
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-lg font-bold text-sm-navy">
                            Riwayat Pembayaran
                        </h2>
                        <span
                            class="rounded-full bg-gray-100 px-2.5 py-1 text-[10px] font-semibold text-sm-card/70"
                        >
                            {{ invoices.length }} transaksi
                        </span>
                    </div>

                    <div
                        v-if="invoices.length === 0"
                        class="mt-3 rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-center"
                    >
                        <Receipt class="mx-auto h-6 w-6 text-gray-400" />
                        <p class="mt-2 text-xs font-semibold text-sm-navy">
                            Belum ada transaksi
                        </p>
                        <p
                            class="mt-1 text-[10px] leading-relaxed text-sm-card/60"
                        >
                            Invoice akan muncul di sini setelah pembayaran
                            pertama berhasil.
                        </p>
                    </div>

                    <ul v-else class="mt-3 divide-y divide-gray-100">
                        <li
                            v-for="invoice in invoices"
                            :key="invoice.id"
                            class="flex items-center justify-between gap-3 py-3"
                        >
                            <div class="min-w-0">
                                <p
                                    class="truncate text-xs font-semibold text-sm-navy"
                                >
                                    {{ invoice.number }}
                                </p>
                                <p class="mt-0.5 text-[10px] text-sm-card/55">
                                    {{ invoice.date }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs font-semibold text-sm-navy">
                                    {{ rupiah(invoice.amount) }}
                                </p>
                                <p class="mt-0.5 text-[10px] text-sm-card/55">
                                    {{ invoice.status }}
                                </p>
                            </div>
                        </li>
                    </ul>
                </section>
            </div>

            <!-- Ringkasan -->
            <aside class="min-w-0 space-y-5 xl:sticky xl:top-24">
                <section
                    class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"
                >
                    <h2 class="text-base font-bold text-sm-navy">
                        Ringkasan Pesanan
                    </h2>

                    <dl class="mt-4 space-y-2.5 text-xs">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-sm-card/65">Paket</dt>
                            <dd class="font-semibold text-sm-navy">
                                {{ summary.plan }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-sm-card/65">Siklus</dt>
                            <dd class="font-semibold text-sm-navy">
                                {{ summary.cycle }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-sm-card/65">Harga</dt>
                            <dd class="font-semibold text-sm-navy">
                                {{ summary.priceLabel }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-sm-card/65">Biaya layanan</dt>
                            <dd class="font-semibold text-emerald-600">
                                Gratis
                            </dd>
                        </div>
                        <div
                            v-if="summary.savingsLabel"
                            class="flex items-center justify-between gap-3 rounded-lg bg-emerald-50 px-2.5 py-1.5"
                        >
                            <dt class="text-emerald-700">Hemat</dt>
                            <dd class="font-semibold text-emerald-700">
                                {{ summary.savingsLabel }}
                            </dd>
                        </div>
                    </dl>

                    <div
                        class="mt-4 flex items-center justify-between border-t border-gray-100 pt-3"
                    >
                        <p class="text-sm font-semibold text-sm-navy">Total</p>
                        <p class="text-xl font-bold text-sm-navy">
                            {{ summary.priceLabel }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="mt-4 inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-sm-red text-xs font-semibold text-white transition-colors hover:bg-sm-red-dark"
                        @click="infoOpen = true"
                    >
                        {{
                            payment_ready
                                ? 'Lanjutkan Pembayaran'
                                : 'Pratinjau Pembayaran'
                        }}
                        <ArrowRight class="h-3.5 w-3.5" />
                    </button>

                    <p
                        class="mt-2 flex items-start gap-1.5 text-[10px] leading-relaxed text-sm-card/55"
                    >
                        <CalendarDays class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                        Paket aktif sampai akhir periode. Pembatalan dapat
                        dilakukan kapan saja.
                    </p>
                </section>

                <section
                    class="rounded-2xl bg-gradient-to-br from-sm-red to-[#5D0F19] p-5 text-white shadow-sm"
                >
                    <div class="flex items-center gap-2">
                        <Crown class="h-6 w-6 shrink-0 text-sm-amber" />
                        <h2 class="text-base font-bold">Butuh bantuan?</h2>
                    </div>
                    <p class="mt-2 text-[11px] leading-relaxed text-white/85">
                        Tim kami dapat membantu memilih paket atau menjelaskan
                        rincian pembayaran.
                    </p>
                    <a
                        href="mailto:halo@sponsormatch.id"
                        class="mt-3 inline-flex h-9 w-full items-center justify-center gap-2 rounded-lg bg-sm-amber text-xs font-semibold text-sm-navy transition-opacity hover:opacity-90"
                    >
                        <Mail class="h-3.5 w-3.5" />
                        Hubungi Kami
                    </a>
                </section>
            </aside>
        </div>

        <!-- Dialog info pembayaran -->
        <Dialog :open="infoOpen" @update:open="infoOpen = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle class="text-lg font-semibold text-sm-navy">
                        Pembayaran belum tersedia
                    </DialogTitle>
                    <DialogDescription
                        class="text-xs leading-relaxed text-sm-card/70"
                    >
                        Halaman ini sudah siap, tetapi gateway pembayaran belum
                        diintegrasikan. Tidak ada tagihan yang dibuat dan paket
                        langganan Anda belum berubah.
                    </DialogDescription>
                </DialogHeader>

                <div
                    class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-[11px] leading-relaxed text-sm-card/75"
                >
                    <p class="font-semibold text-sm-navy">
                        {{ summary.plan }} · {{ summary.cycle }}
                    </p>
                    <p class="mt-0.5">
                        Total pratinjau: {{ summary.priceLabel }}
                    </p>
                </div>

                <DialogFooter>
                    <button
                        type="button"
                        class="inline-flex h-10 items-center justify-center rounded-lg bg-sm-red px-4 text-xs font-semibold text-white transition-colors hover:bg-sm-red-dark"
                        @click="infoOpen = false"
                    >
                        Mengerti
                    </button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </PageShell>
</template>
