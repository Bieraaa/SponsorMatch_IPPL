<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowRight,
    BadgeCheck,
    Building2,
    CalendarDays,
    CheckCircle2,
    CircleAlert,
    ClipboardCheck,
    FileText,
    Handshake,
    MapPin,
    Megaphone,
    Star,
    Users,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
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

type CollaborationReport = {
    description: string | null;
    goal_achieved: boolean | null;
    submitted_at: string | null;
};

type CollaborationItem = {
    id: number;
    proposal_id: number;
    title: string;
    category: string | null;
    location: string | null;
    period: string;
    stage: string;
    stage_label: string;
    counterpart_id: number;
    counterpart_name: string;
    counterpart_avatar: string | null;
    counterpart_url: string;
    campaign_title: string | null;
    amount: number | null;
    amount_label: string;
    support_type: string | null;
    agreed_at: string | null;
    updated_at: string | null;
    report: CollaborationReport | null;
    can_submit_report: boolean;
    can_review: boolean;
    reviewed: boolean;
    detail_url: string;
};

type Stats = {
    total: number;
    active: number;
    completed: number;
    partners: number;
    total_value: number;
    total_value_label: string;
};

const props = defineProps<{
    role: 'eo' | 'company';
    items: CollaborationItem[];
    stats: Stats;
}>();

type Filter = 'all' | 'active' | 'completed';

const activeFilter = ref<Filter>('all');
const reportOpen = ref(false);
const reviewOpen = ref(false);
const activeItem = ref<CollaborationItem | null>(null);

const reportForm = useForm({
    description: '',
    goal_achieved: true,
});

const reviewForm = useForm({
    rating: 5,
    comment: '',
});

const isCompany = computed(() => props.role === 'company');

const tabs: { key: Filter; label: string; count: number }[] = [
    { key: 'all', label: 'Semua', count: props.stats.total },
    { key: 'active', label: 'Berjalan', count: props.stats.active },
    { key: 'completed', label: 'Selesai', count: props.stats.completed },
];

const filtered = computed(() => {
    if (activeFilter.value === 'active') {
        return props.items.filter((item) =>
            ['deal', 'waiting_report'].includes(item.stage),
        );
    }

    if (activeFilter.value === 'completed') {
        return props.items.filter((item) => item.stage === 'done');
    }

    return props.items;
});

const statCards = computed(() => [
    {
        key: 'active',
        label: 'Kolaborasi berjalan',
        value: String(props.stats.active),
        icon: Handshake,
        circle: 'bg-blue-500',
        hint: 'Kesepakatan yang masih diproses',
        wide: false,
    },
    {
        key: 'completed',
        label: 'Kolaborasi selesai',
        value: String(props.stats.completed),
        icon: CheckCircle2,
        circle: 'bg-emerald-500',
        hint: 'Sudah memiliki laporan event',
        wide: false,
    },
    {
        key: 'partners',
        label: 'Mitra',
        value: String(props.stats.partners),
        icon: Users,
        circle: 'bg-purple-500',
        hint: isCompany.value ? 'Event Organizer' : 'Perusahaan sponsor',
        wide: false,
    },
    {
        key: 'value',
        label: 'Nilai kerja sama',
        value: props.stats.total_value_label,
        icon: Wallet,
        circle: 'bg-sm-red',
        hint: 'Total penawaran yang disetujui',
        wide: true,
    },
]);

const stageTone: Record<string, string> = {
    deal: 'border-blue-200 bg-blue-50 text-blue-700',
    waiting_report: 'border-amber-200 bg-amber-50 text-amber-700',
    done: 'border-emerald-200 bg-emerald-50 text-emerald-700',
};

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

function openReport(item: CollaborationItem): void {
    activeItem.value = item;
    reportForm.reset();
    reportForm.clearErrors();
    reportOpen.value = true;
}

function openReview(item: CollaborationItem): void {
    activeItem.value = item;
    reviewForm.reset();
    reviewForm.clearErrors();
    reviewForm.rating = 5;
    reviewOpen.value = true;
}

function closeReport(open: boolean): void {
    reportOpen.value = open;

    if (!open) {
        activeItem.value = null;
        reportForm.reset();
    }
}

function closeReview(open: boolean): void {
    reviewOpen.value = open;

    if (!open) {
        activeItem.value = null;
        reviewForm.reset();
    }
}

function submitReport(): void {
    if (!activeItem.value) {
        return;
    }

    reportForm.post(`/collaborations/${activeItem.value.id}/report`, {
        preserveScroll: true,
        onSuccess: () => closeReport(false),
    });
}

function submitReview(): void {
    if (!activeItem.value) {
        return;
    }

    reviewForm.post(`/collaborations/${activeItem.value.id}/review`, {
        preserveScroll: true,
        onSuccess: () => closeReview(false),
    });
}

function refresh(): void {
    router.reload({ only: ['items', 'stats'] });
}
</script>

<template>
    <Head title="Riwayat Kolaborasi" />

    <PageShell>
        <PageHeader
            title="Riwayat Kolaborasi"
            :description="
                isCompany
                    ? 'Kerja sama yang sudah disepakati bersama Event Organizer, lengkap dengan laporan dan ulasan.'
                    : 'Kerja sama yang sudah disepakati bersama sponsor, lengkap dengan laporan event dan ulasan.'
            "
        >
            <template #actions>
                <button
                    type="button"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 text-xs font-semibold text-sm-navy transition-colors hover:bg-gray-50"
                    @click="refresh"
                >
                    <ClipboardCheck class="h-4 w-4" />
                    Muat Ulang
                </button>
                <Link
                    :href="isCompany ? '/sponsor/proposals' : '/proposals'"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-sm-red px-4 text-xs font-semibold text-white transition-colors hover:bg-sm-red-dark"
                >
                    <FileText class="h-4 w-4" />
                    {{ isCompany ? 'Proposal Masuk' : 'Proposal Saya' }}
                </Link>
            </template>
        </PageHeader>

        <!-- Ringkasan -->
        <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <article
                v-for="card in statCards"
                :key="card.key"
                class="flex h-full min-h-[116px] items-start gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm"
            >
                <span
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full text-white"
                    :class="card.circle"
                >
                    <component :is="card.icon" class="h-6 w-6" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-sm-navy">
                        {{ card.label }}
                    </p>
                    <p
                        class="mt-0.5 leading-tight font-bold text-sm-navy"
                        :class="
                            card.wide
                                ? 'text-xl xl:text-2xl'
                                : 'text-[30px] xl:text-[32px]'
                        "
                    >
                        {{ card.value }}
                    </p>
                    <p
                        class="mt-1 line-clamp-2 min-h-[2.4em] text-[11px] leading-snug text-sm-card/60"
                    >
                        {{ card.hint }}
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
                        : 'border-gray-200 bg-white text-sm-navy hover:border-gray-300'
                "
                @click="activeFilter = tab.key"
            >
                {{ tab.label }}
                <span
                    class="rounded-full px-1.5 py-0.5 text-[10px]"
                    :class="
                        activeFilter === tab.key
                            ? 'bg-white/20 text-white'
                            : 'bg-gray-100 text-sm-card/70'
                    "
                >
                    {{ tab.count }}
                </span>
            </button>
        </div>

        <!-- Kosong -->
        <div
            v-if="filtered.length === 0"
            class="mt-5 rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-10 text-center"
        >
            <span
                class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-50 text-sm-red"
            >
                <Handshake class="h-7 w-7" />
            </span>
            <h2 class="mt-4 text-lg font-semibold text-sm-navy">
                Belum ada kolaborasi
            </h2>
            <p
                class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-sm-card/70"
            >
                Kolaborasi muncul di sini setelah penawaran disetujui oleh kedua
                pihak. Mulai dari halaman
                {{ isCompany ? 'Proposal Masuk' : 'Proposal Saya' }}.
            </p>
        </div>

        <!-- Daftar -->
        <div v-else class="mt-5 space-y-4">
            <article
                v-for="item in filtered"
                :key="item.id"
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"
            >
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div class="flex min-w-0 items-start gap-3">
                        <span
                            class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-sm-navy text-sm font-semibold text-white"
                        >
                            <img
                                v-if="item.counterpart_avatar"
                                :src="item.counterpart_avatar"
                                :alt="item.counterpart_name"
                                class="h-full w-full object-cover"
                            />
                            <template v-else>
                                {{
                                    item.counterpart_name
                                        .charAt(0)
                                        .toUpperCase()
                                }}
                            </template>
                        </span>

                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <Link
                                    :href="item.counterpart_url"
                                    class="truncate text-[15px] font-semibold text-sm-navy hover:underline"
                                >
                                    {{ item.counterpart_name }}
                                </Link>
                                <span
                                    class="rounded-full border px-2.5 py-0.5 text-[10px] font-semibold"
                                    :class="
                                        stageTone[item.stage] ??
                                        'border-gray-200 bg-gray-50 text-gray-600'
                                    "
                                >
                                    {{ item.stage_label }}
                                </span>
                            </div>

                            <Link
                                :href="item.detail_url"
                                class="mt-1 flex items-center gap-1.5 truncate text-sm font-medium text-sm-card/80 hover:text-sm-red"
                            >
                                <FileText class="h-3.5 w-3.5 shrink-0" />
                                {{ item.title }}
                            </Link>

                            <div
                                class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-sm-card/65"
                            >
                                <span
                                    v-if="item.category"
                                    class="flex items-center gap-1"
                                >
                                    <Building2 class="h-3.5 w-3.5" />
                                    {{ item.category }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <CalendarDays class="h-3.5 w-3.5" />
                                    {{ item.period }}
                                </span>
                                <span
                                    v-if="item.location"
                                    class="flex items-center gap-1"
                                >
                                    <MapPin class="h-3.5 w-3.5" />
                                    {{ item.location }}
                                </span>
                                <span
                                    v-if="item.campaign_title"
                                    class="flex items-center gap-1"
                                >
                                    <Megaphone class="h-3.5 w-3.5" />
                                    {{ item.campaign_title }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="shrink-0 text-left sm:text-right">
                        <p class="text-lg font-bold text-sm-navy">
                            {{ item.amount_label }}
                        </p>
                        <p class="mt-0.5 text-[11px] text-sm-card/60">
                            {{ item.support_type ?? 'Dukungan sponsorship' }}
                        </p>
                        <p
                            v-if="item.agreed_at"
                            class="mt-1 text-[10px] text-sm-card/50"
                        >
                            Disetujui {{ formatDate(item.agreed_at) }}
                        </p>
                    </div>
                </div>

                <!-- Laporan event -->
                <div
                    v-if="item.report"
                    class="mt-4 rounded-xl border border-emerald-100 bg-emerald-50/60 p-3"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <p
                            class="flex items-center gap-1.5 text-[11px] font-semibold text-emerald-800"
                        >
                            <BadgeCheck class="h-4 w-4" />
                            Laporan event
                            <span
                                class="rounded-full px-2 py-0.5 text-[10px]"
                                :class="
                                    item.report.goal_achieved
                                        ? 'bg-emerald-100 text-emerald-700'
                                        : 'bg-amber-100 text-amber-700'
                                "
                            >
                                {{
                                    item.report.goal_achieved
                                        ? 'Target tercapai'
                                        : 'Target belum tercapai'
                                }}
                            </span>
                        </p>
                        <p class="text-[10px] text-emerald-700/70">
                            {{ formatDate(item.report.submitted_at) }}
                        </p>
                    </div>
                    <p
                        class="mt-1.5 line-clamp-3 text-xs leading-relaxed text-emerald-900/80"
                    >
                        {{ item.report.description }}
                    </p>
                </div>

                <!-- Aksi -->
                <div
                    class="mt-4 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-4"
                >
                    <Link
                        :href="item.detail_url"
                        class="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-white px-4 text-xs font-semibold text-sm-navy transition-colors hover:bg-gray-50"
                    >
                        Lihat Detail
                        <ArrowRight class="h-3.5 w-3.5" />
                    </Link>

                    <button
                        v-if="item.can_submit_report"
                        type="button"
                        class="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg bg-sm-navy px-4 text-xs font-semibold text-white transition-colors hover:bg-sm-card"
                        @click="openReport(item)"
                    >
                        <ClipboardCheck class="h-3.5 w-3.5" />
                        Kirim Laporan
                    </button>

                    <button
                        v-if="item.can_review"
                        type="button"
                        class="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg bg-sm-red px-4 text-xs font-semibold text-white transition-colors hover:bg-sm-red-dark"
                        @click="openReview(item)"
                    >
                        <Star class="h-3.5 w-3.5" />
                        Beri Ulasan
                    </button>

                    <span
                        v-else-if="item.reviewed"
                        class="inline-flex items-center gap-1.5 text-[11px] font-medium text-emerald-600"
                    >
                        <CheckCircle2 class="h-3.5 w-3.5" />
                        Ulasan sudah dikirim
                    </span>
                </div>
            </article>
        </div>

        <!-- Dialog laporan -->
        <Dialog :open="reportOpen" @update:open="closeReport">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle class="text-lg font-semibold text-sm-navy">
                        Laporan Realisasi Event
                    </DialogTitle>
                    <DialogDescription class="text-xs text-sm-card/70">
                        {{ activeItem?.title }} ·
                        {{ activeItem?.counterpart_name }}
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitReport">
                    <div
                        v-if="Object.keys(reportForm.errors).length > 0"
                        class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-[12px] text-red-700"
                    >
                        <p class="flex items-center gap-1.5 font-semibold">
                            <CircleAlert class="h-4 w-4 shrink-0" />
                            Laporan belum bisa dikirim
                        </p>
                        <ul class="mt-1 list-disc space-y-0.5 pl-5">
                            <li
                                v-for="(message, field) in reportForm.errors"
                                :key="field"
                            >
                                {{ message }}
                            </li>
                        </ul>
                    </div>

                    <div>
                        <label
                            for="report-description"
                            class="mb-1 block text-xs font-semibold text-sm-navy"
                        >
                            Ringkasan realisasi event
                            <span class="text-sm-red">*</span>
                        </label>
                        <textarea
                            id="report-description"
                            v-model="reportForm.description"
                            rows="5"
                            maxlength="5000"
                            class="w-full resize-y rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-sm-navy outline-none placeholder:text-gray-400 focus:border-sm-red focus:ring-2 focus:ring-sm-red/15"
                            placeholder="Jelaskan pelaksanaan event, jumlah peserta, dan manfaat yang diterima sponsor."
                        ></textarea>
                        <InputError
                            :message="reportForm.errors.description"
                            class="mt-1"
                        />
                    </div>

                    <label
                        class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 bg-gray-50 px-3 py-3"
                    >
                        <input
                            v-model="reportForm.goal_achieved"
                            type="checkbox"
                            class="mt-0.5 h-4 w-4 rounded border-gray-300 text-sm-red focus:ring-sm-red"
                        />
                        <span>
                            <span
                                class="block text-xs font-semibold text-sm-navy"
                            >
                                Target sponsorship tercapai
                            </span>
                            <span
                                class="mt-0.5 block text-[11px] text-sm-card/65"
                            >
                                Centang bila target audiens, benefit, dan
                                deliverables sudah terpenuhi.
                            </span>
                        </span>
                    </label>

                    <DialogFooter class="gap-2">
                        <button
                            type="button"
                            class="inline-flex h-10 items-center justify-center rounded-lg border border-gray-200 bg-white px-4 text-xs font-semibold text-sm-navy transition-colors hover:bg-gray-50"
                            @click="closeReport(false)"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="reportForm.processing"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-sm-red px-4 text-xs font-semibold text-white transition-colors hover:bg-sm-red-dark disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <ClipboardCheck class="h-4 w-4" />
                            {{
                                reportForm.processing
                                    ? 'Mengirim...'
                                    : 'Kirim Laporan'
                            }}
                        </button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Dialog ulasan -->
        <Dialog :open="reviewOpen" @update:open="closeReview">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle class="text-lg font-semibold text-sm-navy">
                        Beri Ulasan
                    </DialogTitle>
                    <DialogDescription class="text-xs text-sm-card/70">
                        Bagaimana pengalaman bekerja sama dengan
                        {{ activeItem?.counterpart_name }}?
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitReview">
                    <div
                        v-if="Object.keys(reviewForm.errors).length > 0"
                        class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-[12px] text-red-700"
                    >
                        <ul class="list-disc space-y-0.5 pl-5">
                            <li
                                v-for="(message, field) in reviewForm.errors"
                                :key="field"
                            >
                                {{ message }}
                            </li>
                        </ul>
                    </div>

                    <div>
                        <p class="mb-2 text-xs font-semibold text-sm-navy">
                            Rating <span class="text-sm-red">*</span>
                        </p>
                        <div class="flex items-center gap-1.5">
                            <button
                                v-for="value in 5"
                                :key="value"
                                type="button"
                                class="rounded-lg p-1 transition-transform hover:scale-110"
                                :aria-label="`Beri ${value} bintang`"
                                @click="reviewForm.rating = value"
                            >
                                <Star
                                    class="h-7 w-7"
                                    :class="
                                        value <= reviewForm.rating
                                            ? 'fill-amber-400 text-amber-400'
                                            : 'text-gray-300'
                                    "
                                />
                            </button>
                            <span
                                class="ml-1 text-sm font-semibold text-sm-navy"
                            >
                                {{ reviewForm.rating }}/5
                            </span>
                        </div>
                        <InputError
                            :message="reviewForm.errors.rating"
                            class="mt-1"
                        />
                    </div>

                    <div>
                        <label
                            for="review-comment"
                            class="mb-1 block text-xs font-semibold text-sm-navy"
                        >
                            Ulasan (opsional)
                        </label>
                        <textarea
                            id="review-comment"
                            v-model="reviewForm.comment"
                            rows="4"
                            maxlength="1000"
                            class="w-full resize-y rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-sm-navy outline-none placeholder:text-gray-400 focus:border-sm-red focus:ring-2 focus:ring-sm-red/15"
                            placeholder="Ceritakan pengalaman kolaborasi, komunikasi, dan hasil event."
                        ></textarea>
                        <InputError
                            :message="reviewForm.errors.comment"
                            class="mt-1"
                        />
                    </div>

                    <DialogFooter class="gap-2">
                        <button
                            type="button"
                            class="inline-flex h-10 items-center justify-center rounded-lg border border-gray-200 bg-white px-4 text-xs font-semibold text-sm-navy transition-colors hover:bg-gray-50"
                            @click="closeReview(false)"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="reviewForm.processing"
                            class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-sm-red px-4 text-xs font-semibold text-white transition-colors hover:bg-sm-red-dark disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <Star class="h-4 w-4" />
                            {{
                                reviewForm.processing
                                    ? 'Mengirim...'
                                    : 'Kirim Ulasan'
                            }}
                        </button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </PageShell>
</template>
