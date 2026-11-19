<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Building2,
    CalendarDays,
    CheckCircle2,
    FileText,
    Inbox,
    MapPin,
    MessageCircle,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import PageShell from '@/components/layout/PageShell.vue';
import { show } from '@/routes/sponsor/proposals';

type Item = {
    trackingId: number;
    proposalId: number;
    title: string;
    kategori: string | null;
    eoName: string | null;
    location: string | null;
    budgetMin: number;
    budgetMax: number;
    stage: string;
    stageLabel: string;
    sentAt: string | null;
    offerCount: number;
};

type Counts = {
    all: number;
    new: number;
    negotiating: number;
    deal: number;
    rejected: number;
};

const props = defineProps<{
    items: Item[];
    counts: Counts;
}>();

type Filter = 'all' | 'new' | 'negotiating' | 'deal' | 'rejected';

const activeFilter = ref<Filter>('all');

const tabs: { key: Filter; label: string }[] = [
    { key: 'all', label: 'Semua' },
    { key: 'new', label: 'Baru' },
    { key: 'negotiating', label: 'Negosiasi' },
    { key: 'deal', label: 'Disetujui' },
    { key: 'rejected', label: 'Ditolak' },
];

const statCards = computed(() => [
    {
        key: 'all',
        label: 'Total proposal',
        value: props.counts.all,
        icon: Inbox,
        circle: 'bg-sm-navy',
        hint: 'Seluruh proposal yang masuk',
    },
    {
        key: 'new',
        label: 'Proposal baru',
        value: props.counts.new,
        icon: FileText,
        circle: 'bg-[#5B7DBE]',
        hint: 'Belum ditinjau atau direspons',
    },
    {
        key: 'negotiating',
        label: 'Negosiasi',
        value: props.counts.negotiating,
        icon: MessageCircle,
        circle: 'bg-[#F3B300]',
        hint: 'Sedang dibahas dengan EO',
    },
    {
        key: 'deal',
        label: 'Disetujui',
        value: props.counts.deal,
        icon: CheckCircle2,
        circle: 'bg-[#46B450]',
        hint: 'Kolaborasi yang sudah disepakati',
    },
]);

const filtered = computed(() => {
    if (activeFilter.value === 'all') {
        return props.items;
    }

    if (activeFilter.value === 'deal') {
        return props.items.filter((item) =>
            ['deal', 'waiting_report', 'done'].includes(item.stage),
        );
    }

    if (activeFilter.value === 'new') {
        return props.items.filter((item) =>
            ['sent', 'viewed'].includes(item.stage),
        );
    }

    if (activeFilter.value === 'negotiating') {
        return props.items.filter((item) => item.stage === 'negotiating');
    }

    return props.items.filter((item) => item.stage === 'rejected');
});

const stageStyle: Record<string, string> = {
    sent: 'bg-sky-50 text-sky-700 border-sky-200',
    viewed: 'bg-blue-50 text-blue-700 border-blue-200',
    negotiating: 'bg-amber-50 text-amber-700 border-amber-200',
    deal: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    waiting_report: 'bg-violet-50 text-violet-700 border-violet-200',
    done: 'bg-gray-100 text-gray-700 border-gray-200',
    rejected: 'bg-red-50 text-red-700 border-red-200',
};

function formatRupiah(value: number): string {
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
</script>

<template>
    <Head title="Proposal Masuk - Sponsor" />

    <PageShell>
        <PageHeader
            title="Proposal Masuk"
            description="Daftar proposal sponsorship yang dikirim ke perusahaan kamu."
        >
            <template #actions>
                <Link
                    href="/company/recommendations"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 text-xs font-semibold text-sm-navy transition-colors hover:bg-gray-50"
                >
                    Rekomendasi Event
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
                        class="mt-0.5 text-[30px] leading-tight font-bold text-sm-navy xl:text-[32px]"
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
                    {{ props.counts[tab.key] }}
                </span>
            </button>
        </div>

        <section
            class="mt-5 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"
        >
            <div
                v-if="filtered.length === 0"
                class="px-6 py-10 text-center text-sm text-gray-500"
            >
                Tidak ada proposal pada kategori ini.
            </div>
            <ul v-else class="divide-y divide-gray-100">
                <li v-for="item in filtered" :key="item.trackingId">
                    <Link
                        :href="show(item.trackingId).url"
                        class="flex flex-wrap items-center gap-4 px-5 py-4 transition-colors hover:bg-gray-50"
                    >
                        <div
                            class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-sm-navy text-white"
                        >
                            <FileText class="size-5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate text-[14px] font-medium text-sm-navy"
                            >
                                {{ item.title }}
                            </p>
                            <p
                                class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-gray-500"
                            >
                                <span class="flex items-center gap-1">
                                    <Building2 class="size-3.5" />{{
                                        item.eoName ?? 'EO'
                                    }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <MapPin class="size-3.5" />{{
                                        item.location ?? '-'
                                    }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <CalendarDays class="size-3.5" />Dikirim
                                    {{ formatDate(item.sentAt) }}
                                </span>
                            </p>
                        </div>
                        <div class="flex flex-col items-end gap-1">
                            <p class="text-[12px] font-medium text-sm-navy">
                                {{ formatRupiah(item.budgetMin) }} –
                                {{ formatRupiah(item.budgetMax) }}
                            </p>
                            <span
                                class="rounded-full border px-2 py-0.5 text-[10px] font-medium"
                                :class="
                                    stageStyle[item.stage] ??
                                    'border-gray-200 bg-gray-50 text-gray-600'
                                "
                            >
                                {{ item.stageLabel }}
                            </span>
                            <p
                                v-if="item.offerCount > 0"
                                class="text-[10px] text-gray-400"
                            >
                                {{ item.offerCount }} penawaran
                            </p>
                        </div>
                    </Link>
                </li>
            </ul>
        </section>
    </PageShell>
</template>
