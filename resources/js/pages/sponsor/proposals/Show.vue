<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    Check,
    Download,
    FileText,
    MapPin,
    Target,
    Wallet,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import PageShell from '@/components/layout/PageShell.vue';
import {
    dokumen,
    index,
    offer as offerRoute,
    respond as respondRoute,
} from '@/routes/sponsor/proposals';

type Offer = {
    id: number;
    fromSponsor: boolean;
    supportType: string;
    amount: number | null;
    status: string;
    statusLabel: string;
    message: string | null;
    createdAt: string | null;
};

type Tracking = {
    id: number;
    stage: string;
    stageLabel: string;
    initiatedBy: string;
    sentAt: string | null;
};

type Proposal = {
    id: number;
    title: string;
    description: string;
    targetAudience: string;
    location: string;
    needType: string;
    budgetMin: number;
    budgetMax: number;
    benefits: string;
    startDate: string | null;
    endDate: string | null;
    kategori: string | null;
    eoName: string | null;
    documentAvailable: boolean;
};

/**
 * Halaman detail proposal di sisi sponsor.
 *
 * Tiga aksi yang tersedia (semuanya menulis ke database):
 *  1. Unduh dokumen  → sponsor.proposals.dokumen
 *  2. Ajukan penawaran → sponsor.proposals.offer (stage → negotiating)
 *  3. Terima / Tolak proposal → sponsor.proposals.respond (deal / rejected)
 */
const props = defineProps<{
    tracking: Tracking;
    proposal: Proposal;
    offers: Offer[];
}>();

const page = usePage<{ errors: Record<string, string> }>();
const errors = computed(() => page.props.errors ?? {});

const supportType = ref('Dana');
const amount = ref<number | null>(null);
const message = ref('');
const submitting = ref(false);

/** Tracking yang sudah final tidak bisa diubah lagi. */
const isFinal = computed(() =>
    ['deal', 'waiting_report', 'done', 'rejected'].includes(
        props.tracking.stage,
    ),
);

function formatRupiah(value: number | null): string {
    if (value === null) {
        return '-';
    }

    return 'Rp ' + value.toLocaleString('id-ID');
}

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function sendOffer(): void {
    submitting.value = true;

    router.post(
        offerRoute(props.tracking.id),
        {
            support_type: supportType.value,
            amount: amount.value,
            message: message.value || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                amount.value = null;
                message.value = '';
            },
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
}

function respond(action: 'accept' | 'reject'): void {
    router.post(
        respondRoute(props.tracking.id),
        { action },
        { preserveScroll: true },
    );
}

function download(): void {
    window.location.href = dokumen(props.proposal.id).url;
}

const benefits = computed(() =>
    props.proposal.benefits
        ? props.proposal.benefits
              .split(/[\n,;]/)
              .map((item) => item.trim())
              .filter(Boolean)
        : [],
);

const offerStatusStyle: Record<string, string> = {
    pending: 'bg-amber-50 text-amber-700 border-amber-200',
    accepted: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    rejected: 'bg-red-50 text-red-700 border-red-200',
    counter: 'bg-violet-50 text-violet-700 border-violet-200',
};
</script>

<template>
    <Head :title="proposal.title" />

    <PageShell>
        <Link
            :href="index().url"
            class="inline-flex items-center gap-1.5 text-sm text-sm-card/70 transition-colors hover:text-sm-navy"
        >
            <ArrowLeft class="size-4" />
            Kembali ke Proposal Masuk
        </Link>

        <header class="mt-4 flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <p
                    class="text-xs font-medium tracking-wide text-sm-red uppercase"
                >
                    {{ proposal.kategori }}
                </p>
                <h1
                    class="mt-1 text-2xl leading-tight font-semibold text-sm-navy lg:text-[30px]"
                >
                    {{ proposal.title }}
                </h1>
                <p class="mt-2 text-sm text-sm-card/70">
                    Diajukan oleh {{ proposal.eoName ?? 'Event Organizer' }}
                </p>
            </div>
            <button
                v-if="proposal.documentAvailable"
                type="button"
                class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 text-xs font-semibold text-sm-navy transition-colors hover:bg-gray-50"
                @click="download"
            >
                <Download class="size-4" />
                Unduh Dokumen
            </button>
            <p v-else class="text-[12px] text-sm-card/60">
                Dokumen belum tersedia
            </p>
        </header>

        <div
            class="mt-6 grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_340px]"
        >
            <div class="min-w-0 space-y-6">
                <section
                    class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"
                >
                    <h2 class="text-[15px] font-semibold text-sm-navy">
                        Deskripsi Proposal
                    </h2>
                    <p
                        class="mt-2 text-[13px] leading-relaxed whitespace-pre-line text-sm-card/75"
                    >
                        {{ proposal.description || 'Tidak ada deskripsi.' }}
                    </p>
                </section>

                <section
                    class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"
                >
                    <h2 class="text-[15px] font-semibold text-sm-navy">
                        Informasi Acara
                    </h2>
                    <div
                        class="mt-3 grid gap-2 text-[12px] text-sm-card/75 sm:grid-cols-2"
                    >
                        <p class="flex items-center gap-2">
                            <MapPin class="size-4 shrink-0 text-gray-400" />{{
                                proposal.location || '-'
                            }}
                        </p>
                        <p class="flex items-center gap-2">
                            <CalendarDays
                                class="size-4 shrink-0 text-gray-400"
                            />
                            {{ formatDate(proposal.startDate) }} –
                            {{ formatDate(proposal.endDate) }}
                        </p>
                        <p class="flex items-center gap-2">
                            <Target class="size-4 shrink-0 text-gray-400" />{{
                                proposal.targetAudience || '-'
                            }}
                        </p>
                        <p class="flex items-center gap-2">
                            <FileText
                                class="size-4 shrink-0 text-gray-400"
                            />Jenis:
                            {{ proposal.needType }}
                        </p>
                    </div>
                </section>

                <section
                    class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"
                >
                    <h2 class="text-[15px] font-semibold text-sm-navy">
                        Benefit yang Diinginkan
                    </h2>
                    <ul class="mt-3 space-y-1.5 text-[12px] text-sm-card/75">
                        <li
                            v-for="(benefit, i) in benefits"
                            :key="i"
                            class="flex items-start gap-2"
                        >
                            <span
                                class="mt-1.5 size-1.5 shrink-0 rounded-full bg-sm-red"
                            ></span>
                            <span>{{ benefit }}</span>
                        </li>
                        <li v-if="benefits.length === 0" class="text-gray-400">
                            Tidak disebutkan
                        </li>
                    </ul>
                </section>

                <section
                    v-if="offers.length"
                    class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"
                >
                    <h2 class="text-[15px] font-semibold text-sm-navy">
                        Riwayat Penawaran
                    </h2>
                    <ul class="mt-3 space-y-2">
                        <li
                            v-for="item in offers"
                            :key="item.id"
                            class="rounded-xl border border-gray-100 p-3"
                        >
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <p class="text-[13px] font-medium text-sm-navy">
                                    {{ item.supportType }} –
                                    {{ formatRupiah(item.amount) }}
                                </p>
                                <span
                                    class="rounded-full border px-2 py-0.5 text-[11px] font-medium"
                                    :class="
                                        offerStatusStyle[item.status] ??
                                        'border-gray-200 bg-gray-50 text-gray-600'
                                    "
                                >
                                    {{ item.statusLabel }}
                                </span>
                            </div>
                            <p
                                v-if="item.message"
                                class="mt-1 text-[11px] text-gray-500"
                            >
                                {{ item.message }}
                            </p>
                            <p class="mt-1 text-[11px] text-gray-400">
                                {{
                                    item.fromSponsor
                                        ? 'Dari perusahaan kamu'
                                        : 'Dari EO'
                                }}
                                · {{ formatDate(item.createdAt) }}
                            </p>
                        </li>
                    </ul>
                </section>
            </div>

            <div class="min-w-0 space-y-5">
                <section
                    class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"
                >
                    <h2
                        class="flex items-center gap-2 text-[15px] font-semibold text-sm-navy"
                    >
                        <Wallet class="size-4" /> Anggaran
                    </h2>
                    <p class="mt-2 text-lg font-semibold text-sm-navy">
                        {{ formatRupiah(proposal.budgetMin) }} –
                        {{ formatRupiah(proposal.budgetMax) }}
                    </p>
                </section>

                <section
                    class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"
                >
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-[15px] font-semibold text-sm-navy">
                            Status
                        </h2>
                        <span
                            class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-600"
                        >
                            {{ tracking.stageLabel }}
                        </span>
                    </div>

                    <div v-if="!isFinal" class="mt-3 flex gap-2">
                        <button
                            type="button"
                            class="inline-flex h-9 flex-1 items-center justify-center gap-1.5 rounded-lg bg-emerald-600 text-[13px] font-medium text-white transition-colors hover:bg-emerald-700"
                            @click="respond('accept')"
                        >
                            <Check class="size-4" />
                            Terima
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-9 flex-1 items-center justify-center gap-1.5 rounded-lg border border-gray-200 text-[13px] font-medium text-gray-600 transition-colors hover:bg-gray-50"
                            @click="respond('reject')"
                        >
                            <X class="size-4" />
                            Tolak
                        </button>
                    </div>
                    <p v-else class="mt-2 text-[12px] text-gray-500">
                        Proposal ini sudah selesai diproses.
                    </p>
                </section>

                <section
                    v-if="!isFinal"
                    class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"
                >
                    <h2 class="text-[15px] font-semibold text-sm-navy">
                        Ajukan Penawaran
                    </h2>
                    <p class="mt-1 text-[11px] text-gray-500">
                        Ajukan nominal atau bentuk dukungan lain. Status berubah
                        menjadi negosiasi.
                    </p>
                    <div class="mt-3 space-y-2">
                        <label
                            class="block text-[11px] font-medium text-gray-600"
                        >
                            Bentuk dukungan
                            <select
                                v-model="supportType"
                                class="mt-1 h-9 w-full rounded-lg border border-gray-200 px-2 text-[13px] text-sm-navy"
                            >
                                <option value="Dana">Dana</option>
                                <option value="Produk">Produk</option>
                                <option value="Dana & produk">
                                    Dana & produk
                                </option>
                                <option value="Jasa">Jasa</option>
                            </select>
                        </label>
                        <label
                            class="block text-[11px] font-medium text-gray-600"
                        >
                            Nominal (Rp)
                            <input
                                v-model.number="amount"
                                type="number"
                                min="1"
                                class="mt-1 h-9 w-full rounded-lg border border-gray-200 px-2 text-[13px] text-sm-navy"
                                placeholder="Contoh: 25000000"
                            />
                        </label>
                        <p
                            v-if="errors.amount"
                            class="text-[11px] text-red-600"
                        >
                            {{ errors.amount }}
                        </p>
                        <label
                            class="block text-[11px] font-medium text-gray-600"
                        >
                            Pesan untuk EO (opsional)
                            <textarea
                                v-model="message"
                                rows="3"
                                class="mt-1 w-full rounded-lg border border-gray-200 px-2 py-1.5 text-[13px] text-sm-navy"
                                placeholder="Tulis catatan penawaran"
                            />
                        </label>
                        <p
                            v-if="errors.message"
                            class="text-[11px] text-red-600"
                        >
                            {{ errors.message }}
                        </p>
                        <button
                            type="button"
                            class="h-9 w-full rounded-lg bg-sm-red text-[13px] font-medium text-white transition-colors hover:bg-sm-red-dark disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="submitting"
                            @click="sendOffer"
                        >
                            {{ submitting ? 'Mengirim…' : 'Kirim Penawaran' }}
                        </button>
                    </div>
                </section>
            </div>
        </div>
    </PageShell>
</template>
