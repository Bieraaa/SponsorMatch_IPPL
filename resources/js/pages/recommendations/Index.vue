<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    CheckCircle2,
    Crown,
    FileText,
    Loader2,
    Megaphone,
    RefreshCw,
    Search,
    Send,
    Upload,
} from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import SendProposalModal from '@/components/recommendations/SendProposalModal.vue';
import SponsorPagination from '@/components/recommendations/SponsorPagination.vue';
import SponsorRecommendationCard from '@/components/recommendations/SponsorRecommendationCard.vue';
import { index as recommendationsIndex } from '@/routes/recommendations';
import { refresh as refreshRecommendationsRoute } from '@/routes/recommendations';
import type {
    LatestPost,
    ProposalOption,
    RecommendationAiStatus,
    RecommendationItem,
    RecommendationPagination,
} from '@/types/recommendation';

/**
 * Halaman "Rekomendasi Sponsor" (Event Organizer).
 *
 * - Hasil matching sudah dihitung & disimpan di server (tabel `matches`),
 *   halaman ini hanya menampilkan SATU halaman berisi maksimal 6 kartu.
 * - Pagination memakai query string `page`, jadi tidak ada infinite scroll
 *   maupun "load more". Setiap pindah halaman, daftar digulir ke atas.
 * - Panel kanan tetap: Aksi Cepat, Upgrade ke Premium, Postingan Terbaru.
 */
const props = defineProps<{
    proposals: ProposalOption[];
    selectedProposalId: number | null;
    recommendations: RecommendationPagination;
    ai: RecommendationAiStatus;
    latestPosts: LatestPost[];
}>();

const loading = ref(false);
const selectedProposal = ref<number | null>(props.selectedProposalId);
const listTop = ref<HTMLElement | null>(null);
const modalOpen = ref(false);
const activeRecommendation = ref<RecommendationItem | null>(null);

watch(
    () => props.selectedProposalId,
    (value) => {
        selectedProposal.value = value;
    },
);

const summary = computed(() =>
    props.recommendations.total === 0
        ? 'Belum ada rekomendasi'
        : `Menampilkan ${props.recommendations.from ?? 0}–${
              props.recommendations.to ?? 0
          } dari ${props.recommendations.total} perusahaan`,
);

const quickActions = [
    { label: 'Buat Proposal', icon: FileText, href: '/proposals?buat=1' },
    { label: 'Upload Proposal', icon: Upload, href: '/proposals?buat=1' },
    { label: 'Cari Perusahaan', icon: Search, href: '/search' },
    { label: 'Proposal Saya', icon: Send, href: '/proposals' },
];

/** Gulir ke awal daftar rekomendasi setelah halaman berubah. */
async function scrollToList(): Promise<void> {
    await nextTick();
    listTop.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/**
 * Pindah halaman. Server hanya mengirim 6 kartu untuk halaman tujuan.
 * `preserveScroll` sengaja false agar browser tidak melompat acak, lalu
 * kita gulir manual ke atas daftar rekomendasi.
 */
function changePage(page: number): void {
    if (page === props.recommendations.current_page) {
        return;
    }

    loading.value = true;

    router.get(
        recommendationsIndex().url,
        { proposal: props.selectedProposalId, page },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => scrollToList(),
            onFinish: () => {
                loading.value = false;
            },
        },
    );
}

/** Ganti proposal acuan: hasil matching tiap proposal berbeda. */
function changeProposal(): void {
    if (selectedProposal.value === null) {
        return;
    }

    loading.value = true;

    router.get(
        recommendationsIndex().url,
        { proposal: selectedProposal.value },
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => scrollToList(),
            onFinish: () => {
                loading.value = false;
            },
        },
    );
}

/** Paksa analisis ulang dengan AI (mis. profil perusahaan baru diperbarui). */
function refreshRecommendations(): void {
    if (props.selectedProposalId === null) {
        return;
    }

    loading.value = true;

    router.post(
        refreshRecommendationsRoute.url(),
        { proposal: props.selectedProposalId },
        {
            preserveScroll: true,
            onFinish: () => {
                loading.value = false;
            },
        },
    );
}

function openSendModal(item: RecommendationItem): void {
    if (item.already_sent) {
        return;
    }

    activeRecommendation.value = item;
    modalOpen.value = true;
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
</script>

<template>
    <Head title="Rekomendasi Sponsor" />

    <div class="min-h-full bg-white pb-10">
        <div
            class="mx-auto grid max-w-[1180px] items-start gap-6 px-4 py-6 xl:grid-cols-[minmax(0,1fr)_294px] xl:px-0"
        >
            <!-- ===================== Kolom utama ===================== -->
            <div class="min-w-0">
                <div ref="listTop" class="scroll-mt-24">
                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
                    >
                        <div>
                            <h1
                                class="text-2xl font-semibold text-sm-navy lg:text-[30px]"
                            >
                                Rekomendasi Sponsor
                            </h1>
                            <p class="mt-2 text-sm text-sm-card/70">
                                Perusahaan yang paling sesuai dengan kebutuhan
                                event kamu.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-xs font-semibold text-sm-navy transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="loading || selectedProposalId === null"
                            @click="refreshRecommendations"
                        >
                            <RefreshCw
                                class="h-4 w-4"
                                :class="{ 'animate-spin': loading }"
                            />
                            Perbarui Rekomendasi
                        </button>
                    </div>

                    <!-- Pemilih proposal -->
                    <div
                        v-if="proposals.length"
                        class="mt-5 rounded-xl border border-gray-200 bg-gray-50 p-4"
                    >
                        <label
                            for="recommendation-proposal"
                            class="text-[11px] font-semibold tracking-wide text-sm-card/60 uppercase"
                        >
                            Rekomendasi untuk proposal
                        </label>
                        <div
                            class="mt-1.5 flex flex-col gap-3 sm:flex-row sm:items-center"
                        >
                            <select
                                id="recommendation-proposal"
                                v-model="selectedProposal"
                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm font-medium text-sm-navy outline-none focus:border-sm-red focus:ring-2 focus:ring-sm-red/15 sm:max-w-md"
                                :disabled="loading"
                                @change="changeProposal"
                            >
                                <option
                                    v-for="proposal in proposals"
                                    :key="proposal.id"
                                    :value="proposal.id"
                                >
                                    {{ proposal.title }} ·
                                    {{ proposal.kategori ?? 'Tanpa kategori' }}
                                </option>
                            </select>
                            <p class="text-[11px] leading-relaxed text-sm-card/60">
                                Setiap proposal memiliki hasil matching sendiri.
                            </p>
                        </div>
                    </div>

                    <!-- Status AI -->
                    <div
                        v-if="ai.message"
                        class="mt-4 flex items-start gap-2 rounded-lg border px-3 py-2.5 text-[11px] leading-relaxed"
                        :class="
                            ai.generated
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                : 'border-amber-200 bg-amber-50 text-amber-800'
                        "
                    >
                        <component
                            :is="ai.generated ? CheckCircle2 : AlertCircle"
                            class="mt-0.5 h-3.5 w-3.5 shrink-0"
                        />
                        <span>{{ ai.message }}</span>
                    </div>
                </div>

                <!-- Belum punya proposal -->
                <div
                    v-if="proposals.length === 0"
                    class="mt-8 rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-10 text-center"
                >
                    <span
                        class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-50 text-sm-red"
                    >
                        <FileText class="h-7 w-7" />
                    </span>
                    <h2 class="mt-4 text-lg font-semibold text-sm-navy">
                        Belum ada proposal
                    </h2>
                    <p
                        class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-sm-card/70"
                    >
                        Buat proposal terlebih dahulu. Sistem akan mencocokkan
                        kebutuhan event kamu dengan profil dan campaign
                        perusahaan yang sesuai.
                    </p>
                    <Link
                        href="/proposals?buat=1"
                        class="mt-5 inline-flex items-center gap-2 rounded-lg bg-sm-red px-5 py-2.5 text-xs font-semibold text-white transition-colors hover:bg-sm-red-dark"
                    >
                        <FileText class="h-4 w-4" />
                        Buat Proposal
                    </Link>
                </div>

                <template v-else>
                    <div class="mt-5 flex items-center justify-between gap-3">
                        <p class="text-[11px] font-medium text-sm-card/55">
                            {{ summary }}
                        </p>
                        <p
                            v-if="recommendations.last_page > 1"
                            class="text-[11px] font-medium text-sm-card/55"
                        >
                            Halaman {{ recommendations.current_page }} dari
                            {{ recommendations.last_page }}
                        </p>
                    </div>

                    <div class="relative mt-3">
                        <div
                            v-if="loading"
                            class="absolute inset-0 z-10 flex items-start justify-center rounded-2xl bg-white/70 pt-24 backdrop-blur-[1px]"
                        >
                            <span
                                class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-xs font-semibold text-sm-navy shadow"
                            >
                                <Loader2 class="h-4 w-4 animate-spin" />
                                Memuat rekomendasi...
                            </span>
                        </div>

                        <div
                            v-if="recommendations.data.length"
                            class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3"
                            :class="{
                                'pointer-events-none opacity-50': loading,
                            }"
                        >
                            <SponsorRecommendationCard
                                v-for="item in recommendations.data"
                                :key="item.company_id"
                                :recommendation="item"
                                @send="openSendModal"
                            />
                        </div>

                        <div
                            v-else
                            class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-10 text-center"
                        >
                            <span
                                class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-50 text-sm-red"
                            >
                                <AlertCircle class="h-7 w-7" />
                            </span>
                            <h2 class="mt-4 text-lg font-semibold text-sm-navy">
                                Belum ada rekomendasi yang cocok
                            </h2>
                            <p
                                class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-sm-card/70"
                            >
                                Belum ada perusahaan yang lolos penyaringan
                                untuk proposal ini. Lengkapi kebutuhan proposal
                                atau perbarui rekomendasi beberapa saat lagi.
                            </p>
                        </div>
                    </div>

                    <SponsorPagination
                        :current-page="recommendations.current_page"
                        :last-page="recommendations.last_page"
                        :total="recommendations.total"
                        :from="recommendations.from"
                        :to="recommendations.to"
                        @change="changePage"
                    />
                </template>
            </div>

            <!-- ===================== Panel kanan ===================== -->
            <aside class="min-w-0 space-y-5 xl:sticky xl:top-24">
                <!-- Aksi Cepat -->
                <section>
                    <h2 class="text-sm font-semibold text-sm-navy">
                        Aksi Cepat
                    </h2>
                    <div
                        class="mt-2 grid grid-cols-2 gap-2.5 rounded-xl bg-[#EFEFEF] p-3"
                    >
                        <Link
                            v-for="action in quickActions"
                            :key="action.label"
                            :href="action.href"
                            class="flex flex-col items-center justify-center gap-2 rounded-lg bg-white px-2 py-3 text-center text-[11px] font-medium text-sm-card shadow-[0_1px_3px_rgba(22,30,47,0.08)] transition-shadow hover:shadow-[0_2px_6px_rgba(22,30,47,0.14)]"
                        >
                            <component
                                :is="action.icon"
                                class="h-6 w-6 text-sm-navy"
                            />
                            {{ action.label }}
                        </Link>
                    </div>
                </section>

                <!-- Upgrade ke Premium -->
                <section
                    class="rounded-xl bg-gradient-to-b from-sm-red to-[#5D0F19] p-5 text-white shadow-sm"
                >
                    <div class="flex items-center gap-2">
                        <Crown class="h-5 w-5 shrink-0 text-sm-amber" />
                        <h2 class="text-lg font-semibold">
                            Upgrade ke Premium
                        </h2>
                    </div>
                    <p class="mt-2 text-xs leading-relaxed text-white/85">
                        Dapatkan lebih banyak fitur yang memudahkanmu dalam
                        mengelola proposal
                    </p>
                    <a
                        href="/#harga"
                        class="mt-4 inline-flex w-full items-center justify-center rounded-md bg-sm-amber py-2.5 text-xs font-semibold text-sm-navy transition-opacity hover:opacity-90"
                    >
                        Lihat Paket
                    </a>
                </section>

                <!-- Postingan Terbaru -->
                <section
                    class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <h2 class="font-semibold text-sm-navy">
                            Postingan Terbaru
                        </h2>
                        <span
                            class="text-right text-[10px] leading-tight font-medium text-sm-card/55"
                        >
                            Informasi<br />perusahaan
                        </span>
                    </div>

                    <ul
                        v-if="latestPosts.length"
                        class="mt-2 divide-y divide-gray-100"
                    >
                        <li
                            v-for="post in latestPosts"
                            :key="post.id"
                            class="flex gap-3 py-3 first:pt-2 last:pb-0"
                        >
                            <div
                                class="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-gray-100"
                            >
                                <img
                                    v-if="post.image_url"
                                    :src="post.image_url"
                                    :alt="
                                        post.company_name ??
                                        'Postingan perusahaan'
                                    "
                                    class="h-full w-full object-cover"
                                />
                                <div
                                    v-else
                                    class="flex h-full w-full items-center justify-center bg-sm-navy text-white"
                                >
                                    <Megaphone class="h-5 w-5" />
                                </div>
                            </div>
                            <div class="min-w-0">
                                <p
                                    class="line-clamp-2 text-[11px] leading-snug font-semibold text-sm-navy"
                                >
                                    {{
                                        post.description ||
                                        'Postingan perusahaan'
                                    }}
                                </p>
                                <p class="mt-1 text-[10px] text-sm-card/55">
                                    {{ post.company_name ?? 'Perusahaan' }} ·
                                    {{ formatDate(post.created_at) }}
                                </p>
                            </div>
                        </li>
                    </ul>

                    <p v-else class="mt-3 text-[11px] leading-relaxed text-sm-card/60">
                        Belum ada postingan perusahaan.
                    </p>
                </section>
            </aside>
        </div>

        <SendProposalModal
            v-model:open="modalOpen"
            :recommendation="activeRecommendation"
            :proposals="proposals"
            :default-proposal-id="selectedProposalId"
        />
    </div>
</template>
