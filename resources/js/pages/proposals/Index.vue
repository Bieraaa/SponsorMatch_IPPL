<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref, computed } from 'vue';
import ProposalCreateDialog from '@/components/proposals/ProposalCreateDialog.vue';
import ProposalDetailPanel from '@/components/proposals/ProposalDetailPanel.vue';
import ProposalFollowUpPanel from '@/components/proposals/ProposalFollowUpPanel.vue';
import ProposalStatsCards from '@/components/proposals/ProposalStatsCards.vue';
import ProposalTable from '@/components/proposals/ProposalTable.vue';
import ProposalTrendChart from '@/components/proposals/ProposalTrendChart.vue';
import { index as proposalsIndex } from '@/routes/proposals';
import type {
    ProposalDetail,
    ProposalFilters,
    ProposalKategori,
    ProposalPagination,
    ProposalPeriod,
    ProposalStat,
    ProposalStatusCounts,
    ProposalTrend,
    FollowUpPayload,
} from '@/types/proposal';

/**
 * Halaman "Proposal Saya" (Event Organizer).
 *
 * Semua angka di halaman ini berasal dari database melalui
 * ProposalController + ProposalService — tidak ada data dummy di sini.
 *
 * Interaksi memakai partial reload Inertia supaya:
 *  - memilih baris  → hanya `selectedProposal` yang diambil ulang,
 *  - ganti periode  → hanya `chart`, `stats`, dan `filters`,
 *  - cari/filter/tab/pagination → full visit dengan query string.
 */
const props = defineProps<{
    stats: ProposalStat[];
    chart: ProposalTrend;
    followUps: FollowUpPayload;
    proposals: ProposalPagination;
    selectedProposal: ProposalDetail | null;
    statusCounts: ProposalStatusCounts;
    kategori: ProposalKategori[];
    filters: ProposalFilters;
    pdfConfig: {
        max_kb: number;
        max_mb: number;
    };
}>();

/** Dialog tambah proposal. */
const createOpen = ref(false);

onMounted(() => {
    if (new URLSearchParams(window.location.search).has('buat')) {
        createOpen.value = true;

        // Bersihkan ?buat=1 dari URL tanpa merusak state Inertia.
        window.history.replaceState(
            window.history.state,
            '',
            window.location.pathname,
        );
    }
});

/** Indikator loading untuk partial reload (bukan full page visit). */
const loading = ref(false);

const removeStart = router.on('start', () => {
    loading.value = true;
});

const removeFinish = router.on('finish', () => {
    loading.value = false;
});

onBeforeUnmount(() => {
    removeStart();
    removeFinish();
});

/** Buang parameter kosong supaya URL tetap bersih (?selected=4 saja). */
function cleanParams(
    params: Record<string, string | number | null | undefined>,
): Record<string, string | number> {
    return Object.fromEntries(
        Object.entries(params).filter(
            ([, value]) =>
                value !== null && value !== undefined && value !== '',
        ),
    ) as Record<string, string | number>;
}

/** Query string aktif — dipakai ulang di semua navigasi halaman ini. */
const query = computed(() =>
    cleanParams({
        search: props.filters.search,
        status:
            props.filters.status === 'all' ? undefined : props.filters.status,
        periode: props.filters.periode,
        per_page: props.filters.perPage,
        kategori: props.filters.kategori ?? undefined,
    }),
);

/** Buka panel detail satu proposal (partial reload). */
function select(id: number): void {
    router.get(
        proposalsIndex().url,
        { ...query.value, selected: id },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['selectedProposal'],
        },
    );
}

/** Tutup panel detail tanpa menghapus filter yang aktif. */
function closeDetail(): void {
    router.get(
        proposalsIndex().url,
        { ...query.value },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['selectedProposal'],
        },
    );
}

/** Ganti periode grafik (7 / 30 / 90 hari). */
function changePeriode(periode: ProposalPeriod): void {
    router.get(
        proposalsIndex().url,
        {
            ...query.value,
            periode,
            selected: props.selectedProposal?.id,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['chart', 'stats', 'filters'],
        },
    );
}
</script>

<template>
    <Head title="Proposal Saya" />

    <div class="min-h-full bg-[#F7F8FA] pb-10">
        <div class="mx-auto w-full max-w-[1180px] space-y-6 px-4 py-6 xl:px-0">
            <!-- ===================== Judul halaman ===================== -->
            <header>
                <div class="min-w-0">
                    <h1
                        class="text-2xl leading-tight font-semibold text-sm-navy lg:text-[30px]"
                    >
                        Proposal Saya
                    </h1>
                    <p
                        class="mt-2 max-w-2xl text-sm leading-relaxed text-sm-card/70"
                    >
                        Kelola, pantau, dan tindak lanjuti pengajuan sponsorship
                        event kamu.
                    </p>
                </div>
            </header>

            <!-- Indikator loading partial reload -->
            <div
                v-if="loading"
                class="h-0.5 w-full overflow-hidden rounded-full bg-gray-200"
                role="status"
                aria-label="Memuat data"
            >
                <div
                    class="h-full w-1/3 animate-pulse rounded-full bg-sm-red"
                ></div>
            </div>

            <!-- ===================== 4 kartu statistik ===================== -->
            <ProposalStatsCards :stats="stats" />

            <!-- ===================== Grafik + tindak lanjut ===================== -->
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                <ProposalTrendChart
                    :trend="chart"
                    :periode="filters.periode"
                    @update:periode="changePeriode"
                />
                <ProposalFollowUpPanel :follow-ups="followUps" />
            </div>

            <!-- ===================== Daftar proposal + detail ===================== -->
            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
                <ProposalTable
                    :rows="proposals.data"
                    :pagination="proposals"
                    :counts="statusCounts"
                    :filters="filters"
                    :kategori="kategori"
                    :selected-id="selectedProposal?.id ?? null"
                    :loading="loading"
                    @select="select"
                    @create="createOpen = true"
                />

                <ProposalDetailPanel
                    :detail="selectedProposal"
                    @close="closeDetail"
                />
            </div>
        </div>

        <ProposalCreateDialog
            v-model:open="createOpen"
            :kategori="kategori"
            :pdf-max-kb="pdfConfig.max_kb"
        />
    </div>
</template>
