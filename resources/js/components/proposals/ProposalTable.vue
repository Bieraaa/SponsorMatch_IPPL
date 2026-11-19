<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    Building2,
    Check,
    Clock,
    Download,
    Eye,
    FileEdit,
    Filter,
    Megaphone,
    Plus,
    Search,
    Send,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import type { Component } from 'vue';
import {
    dokumen,
    index as proposalsIndex,
    tindakLanjut,
} from '@/routes/proposals';
import type {
    ProposalFilters,
    ProposalKategori,
    ProposalPagination,
    ProposalRow,
    ProposalStatusCounts,
    ProposalStatusKey,
} from '@/types/proposal';

/**
 * Kartu "Daftar Proposal": pencarian, filter, tab status, tabel, dan pagination.
 *
 * Semua interaksi memakai partial reload Inertia (query string), sehingga
 * filter, tab, dan pagination bisa dipakai bersamaan tanpa memuat ulang
 * seluruh halaman.
 */
const props = defineProps<{
    rows: ProposalRow[];
    pagination: ProposalPagination;
    counts: ProposalStatusCounts;
    filters: ProposalFilters;
    kategori: ProposalKategori[];
    selectedId: number | null;
    loading?: boolean;
}>();

const emit = defineEmits<{
    select: [id: number];
    create: [];
}>();

const searchInput = ref(props.filters.search);
let searchTimer: ReturnType<typeof setTimeout> | null = null;

watch(
    () => props.filters.search,
    (value) => {
        searchInput.value = value;
    },
);

const tabs = computed<
    { key: ProposalStatusKey | 'all'; label: string; count: number }[]
>(() => [
    { key: 'all', label: 'Semua', count: props.counts.all ?? 0 },
    { key: 'draft', label: 'Draft', count: props.counts.draft ?? 0 },
    { key: 'ready', label: 'Siap Dikirim', count: props.counts.ready ?? 0 },
    { key: 'sent', label: 'Terkirim', count: props.counts.sent ?? 0 },
    {
        key: 'waiting',
        label: 'Menunggu Respons',
        count: props.counts.waiting ?? 0,
    },
    { key: 'approved', label: 'Disetujui', count: props.counts.approved ?? 0 },
    { key: 'rejected', label: 'Ditolak', count: props.counts.rejected ?? 0 },
]);

/** Peta gaya badge status (warna + ikon agar tidak hanya mengandalkan warna). */
const statusStyle: Record<
    ProposalStatusKey,
    { badge: string; icon: Component }
> = {
    draft: {
        badge: 'bg-gray-100 text-gray-700 border-gray-200',
        icon: FileEdit,
    },
    ready: {
        badge: 'bg-violet-50 text-violet-700 border-violet-200',
        icon: Megaphone,
    },
    sent: { badge: 'bg-blue-50 text-blue-700 border-blue-200', icon: Send },
    waiting: {
        badge: 'bg-amber-50 text-amber-700 border-amber-200',
        icon: Clock,
    },
    approved: {
        badge: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        icon: Check,
    },
    rejected: { badge: 'bg-red-50 text-sm-red border-red-200', icon: X },
};

/** Panel filter (kategori event + jumlah baris per halaman). */
const showFilters = ref(false);

const perPageOptions = [10, 25, 50];

/** Buang parameter kosong supaya URL tetap bersih. */
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

/** Navigate dengan query string baru (partial reload). */
function navigate(
    patch: Record<string, string | number | null | undefined>,
): void {
    router.get(
        proposalsIndex().url,
        cleanParams({
            search: props.filters.search,
            status:
                props.filters.status === 'all'
                    ? undefined
                    : props.filters.status,
            periode: props.filters.periode,
            per_page: props.filters.perPage,
            kategori: props.filters.kategori ?? undefined,
            ...patch,
        }),
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function onSearch(): void {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }

    searchTimer = setTimeout(() => {
        navigate({ search: searchInput.value.trim(), page: null });
    }, 350);
}

function onKategoriChange(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;

    navigate({ kategori: value === '' ? null : Number(value), page: null });
}

function onPerPageChange(event: Event): void {
    const value = (event.target as HTMLSelectElement).value;

    navigate({ per_page: Number(value), page: null });
}

function selectTab(key: ProposalStatusKey | 'all'): void {
    navigate({ status: key, page: null });
}

function selectRow(row: ProposalRow): void {
    emit('select', row.id);
}

function download(row: ProposalRow): void {
    window.location.href = dokumen(row.id).url;
}

function followUp(row: ProposalRow): void {
    if (row.trackingId) {
        router.post(tindakLanjut(row.trackingId), {}, { preserveScroll: true });
    }
}

/** Tanggal "mm/dd/yyyy" → "12 Nov 2026" (locale id-ID). */
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

function formatRupiah(value: number): string {
    return 'Rp ' + value.toLocaleString('id-ID');
}

/** Tombol pagination: label HTML entity → teks. */
function linkLabel(label: string): string {
    const map: Record<string, string> = {
        '&laquo; Previous': 'Sebelumnya',
        Previous: 'Sebelumnya',
        '&lsaquo; Previous': 'Sebelumnya',
        'Next &raquo;': 'Berikutnya',
        Next: 'Berikutnya',
        'Next &rsaquo;': 'Berikutnya',
    };

    if (map[label]) {
        return map[label];
    }

    return label.replace(/&[a-z]+;/g, '').trim();
}
</script>

<template>
    <section class="rounded-2xl border border-gray-200 bg-white shadow-sm">
        <!-- Header -->
        <header
            class="flex flex-col gap-4 border-b border-gray-100 p-5 lg:flex-row lg:items-center lg:justify-between"
        >
            <div>
                <h2 class="text-[15px] font-semibold text-sm-navy">
                    Daftar Proposal
                </h2>
                <p class="mt-0.5 text-xs text-gray-500">
                    Menampilkan {{ pagination.from ?? 0 }}–{{
                        pagination.to ?? 0
                    }}
                    dari {{ pagination.total }} proposal
                </p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-gray-400"
                    />
                    <input
                        v-model="searchInput"
                        type="search"
                        placeholder="Cari event, sponsor, atau judul proposal..."
                        aria-label="Cari proposal"
                        class="h-9 w-full rounded-lg border border-gray-200 bg-white pr-3 pl-9 text-sm text-sm-navy placeholder:text-gray-400 focus:border-sm-navy focus:ring-1 focus:ring-sm-navy focus:outline-none sm:w-64"
                        @input="onSearch"
                    />
                </div>

                <button
                    type="button"
                    class="inline-flex h-9 items-center justify-center gap-2 rounded-lg border px-3 text-sm font-medium transition-colors"
                    :class="
                        showFilters || filters.kategori
                            ? 'border-sm-navy bg-sm-navy text-white hover:bg-sm-card'
                            : 'border-gray-200 bg-white text-sm-navy hover:bg-gray-50'
                    "
                    :aria-expanded="showFilters"
                    @click="showFilters = !showFilters"
                >
                    <Filter class="size-4" />
                    Filter
                </button>

                <button
                    type="button"
                    class="inline-flex h-9 items-center justify-center gap-2 rounded-lg bg-sm-red px-3 text-sm font-medium text-white transition-colors hover:bg-sm-red-dark"
                    @click="emit('create')"
                >
                    <Plus class="size-4" />
                    Tambah Proposal
                </button>
            </div>
        </header>

        <!-- Panel filter -->
        <div
            v-if="showFilters"
            class="flex flex-wrap items-end gap-3 border-b border-gray-100 bg-gray-50/60 px-5 py-3"
        >
            <div>
                <label
                    for="filter-kategori"
                    class="mb-1 block text-[11px] font-medium text-gray-500"
                >
                    Kategori event
                </label>
                <select
                    id="filter-kategori"
                    class="h-9 rounded-lg border border-gray-200 bg-white px-2.5 text-[13px] text-sm-navy focus:border-sm-navy focus:outline-none"
                    :value="filters.kategori ?? ''"
                    @change="onKategoriChange"
                >
                    <option value="">Semua kategori</option>
                    <option
                        v-for="item in kategori"
                        :key="item.id"
                        :value="item.id"
                    >
                        {{ item.name }}
                    </option>
                </select>
            </div>

            <div>
                <label
                    for="filter-per-page"
                    class="mb-1 block text-[11px] font-medium text-gray-500"
                >
                    Baris per halaman
                </label>
                <select
                    id="filter-per-page"
                    class="h-9 rounded-lg border border-gray-200 bg-white px-2.5 text-[13px] text-sm-navy focus:border-sm-navy focus:outline-none"
                    :value="filters.perPage"
                    @change="onPerPageChange"
                >
                    <option
                        v-for="option in perPageOptions"
                        :key="option"
                        :value="option"
                    >
                        {{ option }}
                    </option>
                </select>
            </div>

            <button
                type="button"
                class="h-9 rounded-lg border border-gray-200 bg-white px-3 text-[13px] font-medium text-gray-600 transition-colors hover:bg-gray-100"
                @click="navigate({ kategori: null, per_page: 10, page: null })"
            >
                Atur ulang
            </button>
        </div>

        <!-- Tab status -->
        <div class="overflow-x-auto border-b border-gray-100 px-5">
            <nav
                class="flex min-w-max gap-1"
                aria-label="Filter status proposal"
            >
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="relative -mb-px flex items-center gap-1.5 border-b-2 px-3 py-3 text-[13px] font-medium transition-colors"
                    :class="
                        filters.status === tab.key
                            ? 'border-sm-red text-sm-navy'
                            : 'border-transparent text-gray-500 hover:text-sm-navy'
                    "
                    @click="selectTab(tab.key)"
                >
                    {{ tab.label }}
                    <span
                        class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold"
                        :class="
                            filters.status === tab.key
                                ? 'bg-sm-navy text-white'
                                : 'bg-gray-100 text-gray-500'
                        "
                    >
                        {{ tab.count }}
                    </span>
                </button>
            </nav>
        </div>

        <!-- Tabel (desktop & tablet) -->
        <div
            class="hidden overflow-x-auto transition-opacity lg:block"
            :class="loading ? 'pointer-events-none opacity-60' : ''"
        >
            <table class="w-full min-w-[900px] border-collapse text-left">
                <thead>
                    <tr
                        class="border-b border-gray-100 bg-gray-50/60 text-[11px] tracking-wide text-gray-500 uppercase"
                    >
                        <th scope="col" class="px-5 py-3 font-medium">Nomor</th>
                        <th scope="col" class="px-3 py-3 font-medium">Event</th>
                        <th scope="col" class="px-3 py-3 font-medium">
                            Sponsor Tujuan
                        </th>
                        <th scope="col" class="px-3 py-3 font-medium">
                            Judul Proposal
                        </th>
                        <th scope="col" class="px-3 py-3 font-medium">
                            Tanggal Kirim
                        </th>
                        <th scope="col" class="px-3 py-3 font-medium">
                            Status
                        </th>
                        <th
                            scope="col"
                            class="px-5 py-3 text-right font-medium"
                        >
                            Aksi
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in rows"
                        :key="row.id"
                        class="cursor-pointer border-b border-gray-50 text-[13px] transition-colors last:border-0 hover:bg-gray-50/60"
                        :class="selectedId === row.id ? 'bg-blue-50/40' : ''"
                        @click="selectRow(row)"
                    >
                        <td class="px-5 py-3 font-medium text-gray-500">
                            {{ row.nomor }}
                        </td>
                        <td class="px-3 py-3">
                            <p
                                class="max-w-[180px] truncate font-medium text-sm-navy"
                            >
                                {{ row.eventName }}
                            </p>
                            <p class="text-[11px] text-gray-500">
                                {{ formatDate(row.eventDate) }}
                            </p>
                        </td>
                        <td class="px-3 py-3">
                            <div class="flex items-center gap-2">
                                <span
                                    v-if="row.sponsorName"
                                    class="flex size-7 shrink-0 items-center justify-center rounded-full bg-sm-card text-[10px] font-semibold text-white"
                                >
                                    {{ row.sponsorInitials }}
                                </span>
                                <span
                                    v-else
                                    class="flex size-7 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-400"
                                    aria-hidden="true"
                                >
                                    <Building2 class="size-3.5" />
                                </span>
                                <div class="min-w-0">
                                    <p
                                        class="max-w-[150px] truncate font-medium text-sm-navy"
                                    >
                                        {{
                                            row.sponsorName ??
                                            'Belum ada sponsor'
                                        }}
                                    </p>
                                    <p
                                        v-if="row.sponsorIndustry"
                                        class="text-[11px] text-gray-500"
                                    >
                                        {{ row.sponsorIndustry }}
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-3">
                            <p
                                class="max-w-[220px] truncate font-medium text-sm-navy"
                            >
                                {{ row.title }}
                            </p>
                            <p class="text-[11px] text-gray-500">
                                {{ formatRupiah(row.budgetMin) }} –
                                {{ formatRupiah(row.budgetMax) }}
                            </p>
                        </td>
                        <td class="px-3 py-3 text-gray-600">
                            {{ formatDate(row.sentAt) }}
                        </td>
                        <td class="px-3 py-3">
                            <span
                                class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-medium"
                                :class="statusStyle[row.status].badge"
                            >
                                <component
                                    :is="statusStyle[row.status].icon"
                                    class="size-3"
                                />
                                {{ row.statusLabel }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-md border border-gray-200 px-2 py-1 text-[11px] font-medium text-sm-navy transition-colors hover:bg-gray-50"
                                    @click.stop="selectRow(row)"
                                >
                                    <Eye class="size-3.5" />
                                    Lihat
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex size-7 items-center justify-center rounded-md border border-gray-200 text-gray-500 transition-colors hover:bg-gray-50 hover:text-sm-navy"
                                    :title="
                                        row.documentAvailable
                                            ? 'Unduh dokumen proposal'
                                            : 'Dokumen belum tersedia'
                                    "
                                    :disabled="!row.documentAvailable"
                                    @click.stop="download(row)"
                                >
                                    <Download class="size-3.5" />
                                </button>
                                <button
                                    v-if="row.canFollowUp"
                                    type="button"
                                    class="inline-flex size-7 items-center justify-center rounded-md border border-gray-200 text-gray-500 transition-colors hover:bg-gray-50 hover:text-sm-navy"
                                    title="Tindak lanjut ke sponsor"
                                    @click.stop="followUp(row)"
                                >
                                    <Send class="size-3.5" />
                                </button>
                            </div>
                        </td>
                    </tr>

                    <tr v-if="rows.length === 0">
                        <td colspan="7" class="px-5 py-14 text-center">
                            <p class="text-sm font-medium text-sm-navy">
                                Belum ada proposal
                            </p>
                            <p class="mt-1 text-xs text-gray-500">
                                Ubah kata kunci pencarian atau pilih tab status
                                lain.
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Tampilan kartu (mobile) -->
        <div class="space-y-3 p-4 lg:hidden">
            <article
                v-for="row in rows"
                :key="row.id"
                class="rounded-xl border border-gray-200 p-3"
                @click="selectRow(row)"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-[11px] font-medium text-gray-400">
                            {{ row.nomor }}
                        </p>
                        <p class="truncate text-sm font-semibold text-sm-navy">
                            {{ row.title }}
                        </p>
                    </div>
                    <span
                        class="inline-flex shrink-0 items-center gap-1 rounded-full border px-2 py-0.5 text-[10px] font-medium"
                        :class="statusStyle[row.status].badge"
                    >
                        {{ row.statusLabel }}
                    </span>
                </div>

                <dl class="mt-3 space-y-1.5 text-[11px]">
                    <div class="flex justify-between gap-2">
                        <dt class="text-gray-500">Event</dt>
                        <dd
                            class="truncate text-right font-medium text-sm-navy"
                        >
                            {{ row.eventName }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-gray-500">Sponsor</dt>
                        <dd
                            class="truncate text-right font-medium text-sm-navy"
                        >
                            {{ row.sponsorName ?? '-' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-gray-500">Tanggal kirim</dt>
                        <dd class="font-medium text-sm-navy">
                            {{ formatDate(row.sentAt) }}
                        </dd>
                    </div>
                </dl>
            </article>

            <p
                v-if="rows.length === 0"
                class="py-10 text-center text-sm text-gray-500"
            >
                Belum ada proposal yang cocok.
            </p>
        </div>

        <!-- Pagination -->
        <footer
            v-if="pagination.lastPage > 1"
            class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-5 py-3"
        >
            <p class="text-[11px] text-gray-500">
                Halaman {{ pagination.currentPage }} dari
                {{ pagination.lastPage }}
            </p>
            <nav
                class="flex items-center gap-1"
                aria-label="Pagination proposal"
            >
                <template
                    v-for="(link, index) in pagination.links"
                    :key="index"
                >
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        preserve-scroll
                        class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2 text-[11px] font-medium transition-colors"
                        :class="
                            link.active
                                ? 'border-sm-navy bg-sm-navy text-white'
                                : 'border-gray-200 text-gray-600 hover:bg-gray-50'
                        "
                        :aria-current="link.active ? 'page' : undefined"
                    >
                        {{ linkLabel(link.label) }}
                    </Link>
                    <span
                        v-else
                        class="inline-flex h-8 min-w-8 cursor-not-allowed items-center justify-center rounded-md border border-gray-100 px-2 text-[11px] font-medium text-gray-300"
                    >
                        {{ linkLabel(link.label) }}
                    </span>
                </template>
            </nav>
        </footer>
    </section>
</template>
