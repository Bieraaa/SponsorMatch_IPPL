<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    Building2,
    CheckCircle2,
    FileText,
    Loader2,
    Megaphone,
    RefreshCw,
    Sparkles,
    UserCheck,
} from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import ProposalRecommendationCard from '@/components/recommendations/ProposalRecommendationCard.vue';
import SponsorPagination from '@/components/recommendations/SponsorPagination.vue';
import {
    index as companyRecommendationsIndex,
    interest as companyRecommendationsInterest,
    refresh as companyRecommendationsRefresh,
} from '@/routes/company/recommendations';
import { index as sponsorProposalsIndex } from '@/routes/sponsor/proposals';
import type {
    ProposalRecommendationItem,
    ProposalRecommendationPagination,
    RecommendationAiStatus,
} from '@/types/recommendation';

/**
 * Halaman "Rekomendasi Event" untuk perusahaan.
 *
 * - Hasil matching dihitung & disimpan di server (tabel `matches`,
 *   direction company_to_eo). Halaman hanya menerima maksimal 6 kartu per
 *   halaman lewat pagination server, bukan seluruh data.
 * - Tombol "Ajukan Penawaran" membuat tracking lalu membuka halaman
 *   penawaran sponsor yang sudah ada.
 */
const props = defineProps<{
    recommendations: ProposalRecommendationPagination;
    ai: RecommendationAiStatus;
    profile: {
        has_profile: boolean;
        is_complete: boolean;
        has_categories: boolean;
        has_campaigns: boolean;
    };
}>();

const loading = ref(false);
const processingId = ref<number | null>(null);
const listTop = ref<HTMLElement | null>(null);

const summary = computed(() =>
    props.recommendations.total === 0
        ? 'Belum ada rekomendasi'
        : `Menampilkan ${props.recommendations.from ?? 0}–${
              props.recommendations.to ?? 0
          } dari ${props.recommendations.total} proposal`,
);

const quickActions = [
    {
        label: 'Profil Perusahaan',
        icon: Building2,
        href: '/company/profile',
    },
    { label: 'Campaign Saya', icon: Megaphone, href: '/company/campaigns' },
    { label: 'Proposal Masuk', icon: FileText, href: sponsorProposalsIndex().url },
    { label: 'Dashboard', icon: UserCheck, href: '/dashboard' },
];

async function scrollToList(): Promise<void> {
    await nextTick();
    listTop.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function changePage(page: number): void {
    if (page === props.recommendations.current_page) {
        return;
    }

    loading.value = true;

    router.get(
        companyRecommendationsIndex().url,
        { page },
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

function refreshRecommendations(): void {
    loading.value = true;

    router.post(
        companyRecommendationsRefresh().url,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                loading.value = false;
            },
        },
    );
}

/**
 * Ajukan penawaran → server menyiapkan tracking lalu mengarahkan ke halaman
 * penawaran sponsor yang sudah ada.
 */
function submitInterest(item: ProposalRecommendationItem): void {
    if (item.already_contacted) {
        return;
    }

    processingId.value = item.proposal_id;

    router.post(
        companyRecommendationsInterest(item.proposal_id).url,
        { campaign_id: item.campaign?.id ?? null },
        {
            preserveScroll: true,
            onFinish: () => {
                processingId.value = null;
            },
        },
    );
}
</script>

<template>
    <Head title="Rekomendasi Event" />

    <div class="min-h-full bg-white pb-10">
        <div
            class="mx-auto grid max-w-[1180px] items-start gap-6 px-4 py-6 xl:grid-cols-[minmax(0,1fr)_294px] xl:px-0"
        >
            <div class="min-w-0">
                <div ref="listTop" class="scroll-mt-24">
                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
                    >
                        <div>
                            <h1
                                class="text-2xl font-semibold text-sm-navy lg:text-[30px]"
                            >
                                Rekomendasi Event
                            </h1>
                            <p class="mt-2 text-sm text-sm-card/70">
                                Proposal Event Organizer yang paling sesuai
                                dengan profil dan campaign perusahaan.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-xs font-semibold text-sm-navy transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="loading || !profile.is_complete"
                            @click="refreshRecommendations"
                        >
                            <RefreshCw
                                class="h-4 w-4"
                                :class="{ 'animate-spin': loading }"
                            />
                            Perbarui Rekomendasi
                        </button>
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

                    <!-- Profil belum lengkap -->
                    <div
                        v-if="!profile.is_complete"
                        class="mt-4 flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50/70 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <p class="text-[11px] leading-relaxed text-sm-card/80">
                            Lengkapi profil perusahaan (nama dan bidang
                            industri) agar AI dapat mencocokkan proposal EO.
                        </p>
                        <Link
                            href="/company/profile"
                            class="inline-flex shrink-0 items-center justify-center rounded-lg bg-sm-red px-3 py-2 text-[11px] font-semibold text-white transition-colors hover:bg-sm-red-dark"
                        >
                            Lengkapi Profil
                        </Link>
                    </div>

                    <div
                        v-else-if="!profile.has_categories"
                        class="mt-4 flex flex-col gap-3 rounded-xl border border-blue-100 bg-blue-50/70 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <p class="text-[11px] leading-relaxed text-sm-card/80">
                            Tambahkan minat kategori event pada profil agar
                            pre-filter rekomendasi lebih tepat. Campaign tetap
                            opsional.
                        </p>
                        <Link
                            href="/company/profile"
                            class="inline-flex shrink-0 items-center justify-center rounded-lg border border-sm-navy/20 bg-white px-3 py-2 text-[11px] font-semibold text-sm-navy transition-colors hover:bg-gray-50"
                        >
                            Atur Minat Kategori
                        </Link>
                    </div>
                </div>

                <template v-if="recommendations.total > 0">
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
                            class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3"
                            :class="{
                                'pointer-events-none opacity-50': loading,
                            }"
                        >
                            <ProposalRecommendationCard
                                v-for="item in recommendations.data"
                                :key="item.proposal_id"
                                :recommendation="item"
                                @interest="submitInterest"
                            />
                        </div>

                        <div
                            v-if="processingId !== null"
                            class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center rounded-2xl bg-white/60 backdrop-blur-[1px]"
                        >
                            <span
                                class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-xs font-semibold text-sm-navy shadow"
                            >
                                <Loader2 class="h-4 w-4 animate-spin" />
                                Menyiapkan halaman penawaran...
                            </span>
                        </div>
                    </div>

                    <SponsorPagination
                        :current-page="recommendations.current_page"
                        :last-page="recommendations.last_page"
                        :total="recommendations.total"
                        :from="recommendations.from"
                        :to="recommendations.to"
                        entity-label="proposal"
                        @change="changePage"
                    />
                </template>

                <!-- Kondisi kosong -->
                <div
                    v-else
                    class="mt-8 rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-10 text-center"
                >
                    <span
                        class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-50 text-sm-red"
                    >
                        <Sparkles class="h-7 w-7" />
                    </span>
                    <h2 class="mt-4 text-lg font-semibold text-sm-navy">
                        {{
                            profile.is_complete
                                ? 'Belum ada proposal yang cocok'
                                : 'Profil perusahaan belum lengkap'
                        }}
                    </h2>
                    <p
                        class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-sm-card/70"
                    >
                        {{
                            profile.is_complete
                                ? 'Belum ada proposal EO yang lolos penyaringan untuk profil dan campaign perusahaan ini. Lengkapi minat kategori/anggaran atau perbarui rekomendasi beberapa saat lagi.'
                                : 'Isi nama perusahaan dan bidang industri terlebih dahulu. Campaign bersifat opsional dan tidak wajib untuk mendapatkan rekomendasi.'
                        }}
                    </p>
                    <Link
                        :href="
                            profile.is_complete
                                ? '/company/campaigns'
                                : '/company/profile'
                        "
                        class="mt-5 inline-flex items-center gap-2 rounded-lg bg-sm-red px-5 py-2.5 text-xs font-semibold text-white transition-colors hover:bg-sm-red-dark"
                    >
                        <component
                            :is="profile.is_complete ? Megaphone : Building2"
                            class="h-4 w-4"
                        />
                        {{
                            profile.is_complete
                                ? 'Kelola Campaign'
                                : 'Lengkapi Profil'
                        }}
                    </Link>
                </div>
            </div>

            <!-- Panel kanan -->
            <aside class="min-w-0 space-y-5 xl:sticky xl:top-24">
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

                <section
                    class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm"
                >
                    <h2 class="text-sm font-semibold text-sm-navy">
                        Tips Rekomendasi
                    </h2>
                    <p class="mt-2 text-[11px] leading-relaxed text-sm-card/65">
                        Profil yang lengkap dan campaign aktif membuat analisis
                        AI lebih akurat. Campaign tidak wajib — perusahaan tanpa
                        campaign tetap mendapat rekomendasi dari data profil.
                    </p>
                </section>
            </aside>
        </div>
    </div>
</template>
