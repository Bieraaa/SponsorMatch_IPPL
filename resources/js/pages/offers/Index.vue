<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    Building2,
    Check,
    CircleAlert,
    Clock,
    FileText,
    Handshake,
    Megaphone,
    MessageCircle,
    RotateCcw,
    Wallet,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { respond as respondOffer } from '@/routes/offers';

type OfferItem = {
    id: number;
    parent_id: number | null;
    status: string;
    status_label: string;
    from_me: boolean;
    can_respond: boolean;
    support_type: string;
    amount: number | null;
    description: string | null;
    benefits: string | null;
    reject_reason: string | null;
    created_at: string | null;
    proposal: {
        id: number;
        title: string;
        category: string | null;
        url: string;
    };
    company_name: string;
    counterpart_name: string;
    sender_label: string;
    campaign_title: string | null;
    deal: boolean;
};

type Counts = {
    all: number;
    action: number;
    negotiating: number;
    accepted: number;
    rejected: number;
};

type Filter = 'all' | 'action' | 'negotiating' | 'accepted' | 'rejected';
type RespondAction = 'accept' | 'reject' | 'counter';

const props = defineProps<{
    items: OfferItem[];
    counts: Counts;
    supportTypes: string[];
    role: 'eo' | 'company';
}>();

const activeFilter = ref<Filter>('all');
const activeOffer = ref<OfferItem | null>(null);
const mode = ref<RespondAction>('accept');
const dialogOpen = ref(false);

const form = useForm({
    action: 'accept' as RespondAction,
    reject_reason: '',
    support_type: 'Dana',
    amount: '',
    message: '',
    benefits: '',
});

const tabs: { key: Filter; label: string }[] = [
    { key: 'all', label: 'Semua' },
    { key: 'action', label: 'Perlu Respons' },
    { key: 'negotiating', label: 'Negosiasi' },
    { key: 'accepted', label: 'Disetujui' },
    { key: 'rejected', label: 'Ditolak' },
];

const filtered = computed(() => {
    if (activeFilter.value === 'all') {
        return props.items;
    }

    if (activeFilter.value === 'action') {
        return props.items.filter((item) => item.can_respond);
    }

    if (activeFilter.value === 'negotiating') {
        return props.items.filter((item) =>
            ['pending', 'counter'].includes(item.status),
        );
    }

    return props.items.filter((item) => item.status === activeFilter.value);
});

const subtitle = computed(() =>
    props.role === 'company'
        ? 'Kelola penawaran yang Anda kirim ke Event Organizer dan penawaran balik yang perlu Anda respons.'
        : 'Kelola penawaran sponsor yang masuk dan kirim penawaran balik untuk mencapai kesepakatan.',
);

const dialogTitle = computed(() => {
    if (mode.value === 'accept') {
        return 'Setujui Penawaran';
    }

    if (mode.value === 'reject') {
        return 'Tolak Penawaran';
    }

    return 'Kirim Penawaran Balik';
});

const submitLabel = computed(() => {
    if (form.processing) {
        return 'Memproses...';
    }

    return mode.value === 'accept'
        ? 'Setujui Penawaran'
        : mode.value === 'reject'
          ? 'Tolak Penawaran'
          : 'Kirim Penawaran Balik';
});

function formatRupiah(value: number | null): string {
    return value === null ? '-' : `Rp ${value.toLocaleString('id-ID')}`;
}

function formatDate(value: string | null): string {
    if (!value) {
        return 'Baru saja';
    }

    return new Date(value).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function statusTone(status: string): string {
    if (status === 'accepted') {
        return 'bg-emerald-100 text-emerald-700';
    }

    if (status === 'rejected') {
        return 'bg-red-100 text-sm-red';
    }

    if (status === 'counter') {
        return 'bg-amber-100 text-amber-700';
    }

    return 'bg-blue-100 text-blue-700';
}

function openRespond(offer: OfferItem, action: RespondAction): void {
    activeOffer.value = offer;
    mode.value = action;
    form.reset();
    form.clearErrors();
    form.action = action;
    form.support_type = offer.support_type || 'Dana';
    form.amount = offer.amount === null ? '' : String(offer.amount);
    dialogOpen.value = true;
}

function closeDialog(open: boolean): void {
    dialogOpen.value = open;

    if (!open) {
        activeOffer.value = null;
        form.reset();
        form.clearErrors();
    }
}

function submit(): void {
    if (activeOffer.value === null) {
        return;
    }

    form.post(respondOffer.url(activeOffer.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            dialogOpen.value = false;
            activeOffer.value = null;
            form.reset();
        },
    });
}
</script>

<template>
    <Head title="Penawaran" />

    <div class="min-h-full bg-[#F7F8FA] pb-10">
        <div class="mx-auto w-full max-w-[1180px] px-4 py-6 xl:px-0">
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <h1
                        class="text-2xl font-semibold text-sm-navy lg:text-[30px]"
                    >
                        Penawaran
                    </h1>
                    <p class="mt-2 max-w-2xl text-sm text-sm-card/70">
                        {{ subtitle }}
                    </p>
                </div>

                <Link
                    :href="
                        role === 'company' ? '/sponsor/proposals' : '/proposals'
                    "
                    class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 text-xs font-semibold text-sm-navy transition-colors hover:bg-gray-50"
                >
                    <FileText class="h-4 w-4" />
                    {{
                        role === 'company' ? 'Proposal Masuk' : 'Proposal Saya'
                    }}
                </Link>
            </div>

            <!-- Ringkasan -->
            <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <article
                    class="flex h-full min-h-[116px] items-start gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm"
                >
                    <span
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[#5B7DBE] text-white"
                    >
                        <MessageCircle class="h-6 w-6" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-sm-navy">
                            Perlu Respons
                        </p>
                        <p
                            class="mt-0.5 text-[30px] leading-tight font-bold text-sm-navy xl:text-[32px]"
                        >
                            {{ counts.action }}
                        </p>
                        <p
                            class="mt-1 line-clamp-2 min-h-[2.4em] text-[11px] leading-snug text-sm-card/60"
                        >
                            Penawaran yang menunggu jawaban kamu
                        </p>
                    </div>
                </article>
                <article
                    class="flex h-full min-h-[116px] items-start gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm"
                >
                    <span
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[#F3B300] text-white"
                    >
                        <Clock class="h-6 w-6" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-sm-navy">
                            Negosiasi
                        </p>
                        <p
                            class="mt-0.5 text-[30px] leading-tight font-bold text-sm-navy xl:text-[32px]"
                        >
                            {{ counts.negotiating }}
                        </p>
                        <p
                            class="mt-1 line-clamp-2 min-h-[2.4em] text-[11px] leading-snug text-sm-card/60"
                        >
                            Sedang dibahas kedua pihak
                        </p>
                    </div>
                </article>
                <article
                    class="flex h-full min-h-[116px] items-start gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm"
                >
                    <span
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[#46B450] text-white"
                    >
                        <Handshake class="h-6 w-6" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-sm-navy">
                            Disetujui
                        </p>
                        <p
                            class="mt-0.5 text-[30px] leading-tight font-bold text-sm-navy xl:text-[32px]"
                        >
                            {{ counts.accepted }}
                        </p>
                        <p
                            class="mt-1 line-clamp-2 min-h-[2.4em] text-[11px] leading-snug text-sm-card/60"
                        >
                            Kesepakatan yang sudah tercapai
                        </p>
                    </div>
                </article>
                <article
                    class="flex h-full min-h-[116px] items-start gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm"
                >
                    <span
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-sm-red text-white"
                    >
                        <X class="h-6 w-6" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-sm-navy">
                            Ditolak
                        </p>
                        <p
                            class="mt-0.5 text-[30px] leading-tight font-bold text-sm-navy xl:text-[32px]"
                        >
                            {{ counts.rejected }}
                        </p>
                        <p
                            class="mt-1 line-clamp-2 min-h-[2.4em] text-[11px] leading-snug text-sm-card/60"
                        >
                            Penawaran yang tidak dilanjutkan
                        </p>
                    </div>
                </article>
            </div>

            <!-- Filter -->
            <div class="mt-6 flex flex-wrap gap-2">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-xs font-semibold transition-colors"
                    :class="
                        activeFilter === tab.key
                            ? 'border-sm-red bg-sm-red text-white'
                            : 'border-gray-200 bg-white text-sm-card/70 hover:bg-gray-50'
                    "
                    @click="activeFilter = tab.key"
                >
                    {{ tab.label }}
                    <span class="ml-1 opacity-80">
                        {{
                            tab.key === 'all'
                                ? counts.all
                                : tab.key === 'action'
                                  ? counts.action
                                  : tab.key === 'negotiating'
                                    ? counts.negotiating
                                    : tab.key === 'accepted'
                                      ? counts.accepted
                                      : counts.rejected
                        }}
                    </span>
                </button>
            </div>

            <!-- Daftar penawaran -->
            <div
                v-if="filtered.length === 0"
                class="mt-6 rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-10 text-center"
            >
                <span
                    class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-50 text-sm-red"
                >
                    <Handshake class="h-7 w-7" />
                </span>
                <h2 class="mt-4 text-lg font-semibold text-sm-navy">
                    Belum ada penawaran
                </h2>
                <p
                    class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-sm-card/70"
                >
                    Penawaran akan muncul di sini setelah salah satu pihak
                    mengirimkan nominal dukungan pada sebuah proposal.
                </p>
            </div>

            <div v-else class="mt-6 space-y-4">
                <article
                    v-for="offer in filtered"
                    :key="offer.id"
                    class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"
                >
                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div class="flex min-w-0 items-start gap-3">
                            <span
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-sm-navy text-sm font-semibold text-white"
                            >
                                {{ offer.company_name.charAt(0).toUpperCase() }}
                            </span>
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2
                                        class="truncate text-[15px] font-semibold text-sm-navy"
                                    >
                                        {{ offer.company_name }}
                                    </h2>
                                    <span
                                        class="rounded-full px-2.5 py-0.5 text-[10px] font-semibold"
                                        :class="statusTone(offer.status)"
                                    >
                                        {{ offer.status_label }}
                                    </span>
                                </div>

                                <p
                                    class="mt-1 flex items-center gap-1.5 truncate text-xs text-sm-card/70"
                                >
                                    <FileText class="h-3.5 w-3.5 shrink-0" />
                                    <Link
                                        :href="offer.proposal.url"
                                        class="truncate hover:text-sm-red"
                                    >
                                        {{ offer.proposal.title }}
                                    </Link>
                                </p>

                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    <span
                                        v-if="offer.proposal.category"
                                        class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-600"
                                    >
                                        {{ offer.proposal.category }}
                                    </span>
                                    <span
                                        v-if="offer.campaign_title"
                                        class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-medium text-sm-red"
                                    >
                                        <Megaphone class="h-3 w-3" />
                                        {{ offer.campaign_title }}
                                    </span>
                                    <span
                                        v-else
                                        class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-600"
                                    >
                                        Berdasarkan profil perusahaan
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="shrink-0 text-left sm:text-right">
                            <p
                                class="flex items-center gap-1 text-[11px] font-semibold text-sm-card/60 sm:justify-end"
                            >
                                <Wallet class="h-3.5 w-3.5" />
                                {{ offer.support_type }}
                            </p>
                            <p class="mt-1 text-xl font-bold text-sm-navy">
                                {{ formatRupiah(offer.amount) }}
                            </p>
                            <p class="mt-1 text-[11px] text-sm-card/60">
                                Dari {{ offer.sender_label }} ·
                                {{ formatDate(offer.created_at) }}
                            </p>
                        </div>
                    </div>

                    <p
                        v-if="offer.description"
                        class="mt-4 rounded-xl bg-gray-50 px-4 py-3 text-sm leading-relaxed text-sm-card/85"
                    >
                        {{ offer.description }}
                    </p>

                    <div v-if="offer.benefits" class="mt-3">
                        <p class="text-[11px] font-semibold text-sm-card">
                            Benefit yang ditawarkan
                        </p>
                        <p class="mt-1 text-xs leading-relaxed text-sm-card/75">
                            {{ offer.benefits }}
                        </p>
                    </div>

                    <p
                        v-if="offer.reject_reason"
                        class="mt-3 rounded-lg border border-red-100 bg-red-50 px-3 py-2 text-xs text-sm-red"
                    >
                        Alasan penolakan: {{ offer.reject_reason }}
                    </p>

                    <div
                        class="mt-4 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-4"
                    >
                        <template v-if="offer.can_respond">
                            <button
                                type="button"
                                class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-emerald-600 px-4 text-xs font-semibold text-white transition-colors hover:bg-emerald-700"
                                @click="openRespond(offer, 'accept')"
                            >
                                <Check class="h-3.5 w-3.5" />
                                Setujui
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-4 text-xs font-semibold text-amber-700 transition-colors hover:bg-amber-100"
                                @click="openRespond(offer, 'counter')"
                            >
                                <RotateCcw class="h-3.5 w-3.5" />
                                Penawaran Balik
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-red-200 px-4 text-xs font-semibold text-sm-red transition-colors hover:bg-red-50"
                                @click="openRespond(offer, 'reject')"
                            >
                                <X class="h-3.5 w-3.5" />
                                Tolak
                            </button>
                        </template>
                        <p
                            v-else
                            class="flex items-center gap-1.5 text-xs text-sm-card/60"
                        >
                            <Building2 class="h-3.5 w-3.5" />
                            {{
                                offer.from_me
                                    ? `Menunggu respons ${offer.counterpart_name}.`
                                    : offer.deal
                                      ? 'Negosiasi sudah mencapai kesepakatan.'
                                      : 'Penawaran ini sudah diproses.'
                            }}
                        </p>
                    </div>
                </article>
            </div>
        </div>

        <!-- Dialog respons -->
        <Dialog :open="dialogOpen" @update:open="closeDialog">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle class="text-lg font-semibold text-sm-navy">
                        {{ dialogTitle }}
                    </DialogTitle>
                    <DialogDescription class="text-xs text-sm-card/70">
                        <template v-if="activeOffer">
                            {{ activeOffer.company_name }} ·
                            {{ formatRupiah(activeOffer.amount) }} ·
                            {{ activeOffer.proposal.title }}
                        </template>
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submit">
                    <div
                        v-if="Object.keys(form.errors).length > 0"
                        class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-[12px] text-red-700"
                    >
                        <p class="flex items-center gap-1.5 font-semibold">
                            <CircleAlert class="size-4 shrink-0" />
                            Respons belum bisa dikirim
                        </p>
                        <ul class="mt-1 list-disc space-y-0.5 pl-5">
                            <li
                                v-for="(message, field) in form.errors"
                                :key="field"
                            >
                                {{ message }}
                            </li>
                        </ul>
                    </div>

                    <template v-if="mode === 'accept'">
                        <p
                            class="rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm leading-relaxed text-emerald-800"
                        >
                            Dengan menyetujui penawaran ini, proposal akan
                            berstatus <strong>deal</strong> dan penawaran lain
                            pada tracking yang sama akan ditutup otomatis.
                        </p>
                    </template>

                    <template v-else-if="mode === 'reject'">
                        <div>
                            <label
                                for="reject-reason"
                                class="mb-1 block text-xs font-semibold text-sm-navy"
                            >
                                Alasan penolakan (opsional)
                            </label>
                            <textarea
                                id="reject-reason"
                                v-model="form.reject_reason"
                                rows="3"
                                maxlength="1000"
                                class="w-full resize-y rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-sm-navy outline-none focus:border-sm-red focus:ring-2 focus:ring-sm-red/15"
                                placeholder="Contoh: nominal masih di bawah kebutuhan minimum event."
                            ></textarea>
                            <InputError
                                :message="form.errors.reject_reason"
                                class="mt-1"
                            />
                        </div>
                    </template>

                    <template v-else>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label
                                    for="counter-support"
                                    class="mb-1 block text-xs font-semibold text-sm-navy"
                                >
                                    Jenis dukungan
                                    <span class="text-sm-red">*</span>
                                </label>
                                <select
                                    id="counter-support"
                                    v-model="form.support_type"
                                    class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-sm-navy outline-none focus:border-sm-red focus:ring-2 focus:ring-sm-red/15"
                                >
                                    <option
                                        v-for="type in supportTypes"
                                        :key="type"
                                        :value="type"
                                    >
                                        {{ type }}
                                    </option>
                                </select>
                                <InputError
                                    :message="form.errors.support_type"
                                    class="mt-1"
                                />
                            </div>

                            <div>
                                <label
                                    for="counter-amount"
                                    class="mb-1 block text-xs font-semibold text-sm-navy"
                                >
                                    Nominal <span class="text-sm-red">*</span>
                                </label>
                                <input
                                    id="counter-amount"
                                    v-model="form.amount"
                                    type="number"
                                    min="1"
                                    class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-sm-navy outline-none focus:border-sm-red focus:ring-2 focus:ring-sm-red/15"
                                    placeholder="25000000"
                                />
                                <InputError
                                    :message="form.errors.amount"
                                    class="mt-1"
                                />
                            </div>
                        </div>

                        <div>
                            <label
                                for="counter-message"
                                class="mb-1 block text-xs font-semibold text-sm-navy"
                            >
                                Pesan (opsional)
                            </label>
                            <textarea
                                id="counter-message"
                                v-model="form.message"
                                rows="3"
                                maxlength="1000"
                                class="w-full resize-y rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-sm-navy outline-none focus:border-sm-red focus:ring-2 focus:ring-sm-red/15"
                                placeholder="Jelaskan alasan penyesuaian nominal..."
                            ></textarea>
                            <InputError
                                :message="form.errors.message"
                                class="mt-1"
                            />
                        </div>

                        <div>
                            <label
                                for="counter-benefits"
                                class="mb-1 block text-xs font-semibold text-sm-navy"
                            >
                                Benefit yang ditawarkan (opsional)
                            </label>
                            <textarea
                                id="counter-benefits"
                                v-model="form.benefits"
                                rows="2"
                                maxlength="1000"
                                class="w-full resize-y rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-sm-navy outline-none focus:border-sm-red focus:ring-2 focus:ring-sm-red/15"
                                placeholder="Contoh: logo utama pada banner dan panggung."
                            ></textarea>
                            <InputError
                                :message="form.errors.benefits"
                                class="mt-1"
                            />
                        </div>
                    </template>

                    <InputError :message="form.errors.action" />

                    <DialogFooter class="gap-2 border-t border-gray-100 pt-4">
                        <button
                            type="button"
                            class="h-9 rounded-lg border border-gray-200 px-3 text-xs font-semibold text-gray-600 transition-colors hover:bg-gray-50"
                            @click="closeDialog(false)"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex h-9 items-center gap-1.5 rounded-lg px-4 text-xs font-semibold text-white transition-colors disabled:opacity-60"
                            :class="
                                mode === 'accept'
                                    ? 'bg-emerald-600 hover:bg-emerald-700'
                                    : mode === 'reject'
                                      ? 'bg-sm-red hover:bg-sm-red-dark'
                                      : 'bg-amber-600 hover:bg-amber-700'
                            "
                        >
                            {{ submitLabel }}
                        </button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
