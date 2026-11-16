<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Globe,
    Mail,
    Megaphone,
    Send,
    Star,
    Target,
    Users,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import CampaignCard from '@/components/company/CampaignCard.vue';
import CampaignEmptyState from '@/components/company/CampaignEmptyState.vue';
import CompanyProfileHeader from '@/components/company/CompanyProfileHeader.vue';
import CompanyProfileTabs from '@/components/company/CompanyProfileTabs.vue';
import SendProposalModal from '@/components/recommendations/SendProposalModal.vue';
import type {
    CompanyCampaign,
    CompanyDetail,
    CompanyPost,
    CompanyTab,
} from '@/types/company';
import type {
    ProposalOption,
    SendableCompany,
} from '@/types/recommendation';

/**
 * Halaman detail perusahaan.
 *
 * Tab Profil selalu tersedia, termasuk saat perusahaan belum punya campaign
 * aktif. Tab Campaign hanya menampilkan campaign aktif, dan pada empty state
 * pengiriman proposal berbasis profil tetap bisa dilakukan.
 */
const props = defineProps<{
    company: CompanyDetail;
    campaigns: CompanyCampaign[];
    posts: CompanyPost[];
    proposals: ProposalOption[];
}>();

const page = usePage();
const activeTab = ref<CompanyTab>('profile');
const modalOpen = ref(false);

const isCompanyOwner = computed(
    () => page.props.auth.user?.id === props.company.id,
);

const canSendProposal = computed(
    () => !isCompanyOwner.value && props.proposals.length > 0,
);

const sendTarget = computed<SendableCompany>(() => ({
    company_id: props.company.id,
    company_name: props.company.name,
    logo_url: props.company.logo_url,
    industry: props.company.industry,
    campaign_options: props.campaigns.map((campaign) => ({
        id: campaign.id,
        title: campaign.title,
        budget_min: campaign.budget_min,
        budget_max: campaign.budget_max,
        period_end: campaign.period_end,
    })),
}));

const defaultProposalId = computed(
    () => props.proposals[0]?.id ?? null,
);

const funding = computed(() => {
    const min = props.company.funding_min;
    const max = props.company.funding_max;

    if (min === null && max === null) {
        return 'Belum diisi';
    }

    const money = (value: number) =>
        `Rp${value.toLocaleString('id-ID', { maximumFractionDigits: 0 })}`;

    if (min !== null && max !== null) {
        return `${money(min)} – ${money(max)}`;
    }

    return min === null ? `Hingga ${money(max ?? 0)}` : `Mulai ${money(min)}`;
});

const rating = computed(() => Number(props.company.rating ?? 0).toFixed(1));

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
}

function openSendModal(): void {
    if (!canSendProposal.value) {
        return;
    }

    modalOpen.value = true;
}
</script>

<template>
    <Head :title="company.name" />

    <div class="min-h-full bg-[#F7F8FA] pb-12">
        <div class="mx-auto max-w-[1100px] px-4 py-6 xl:px-0">
            <Link
                href="/recommendations"
                class="inline-flex items-center gap-2 text-xs font-semibold text-sm-card/70 transition-colors hover:text-sm-red"
            >
                <ArrowLeft class="h-4 w-4" />
                Kembali ke Rekomendasi Sponsor
            </Link>

            <div class="mt-4">
                <CompanyProfileHeader :company="company" />
            </div>

            <div
                v-if="canSendProposal"
                class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-red-100 bg-red-50/60 px-4 py-3"
            >
                <p class="text-xs leading-relaxed text-sm-card/80">
                    Kirim proposal event kamu ke perusahaan ini. Campaign
                    bersifat opsional — proposal bisa dikirim langsung
                    berdasarkan profil perusahaan.
                </p>
                <button
                    type="button"
                    class="inline-flex shrink-0 items-center gap-2 rounded-lg bg-sm-red px-4 py-2.5 text-xs font-semibold text-white transition-colors hover:bg-sm-red-dark"
                    @click="openSendModal"
                >
                    <Send class="h-4 w-4" />
                    Kirim Proposal
                </button>
            </div>

            <div class="mt-6 rounded-2xl border border-gray-200 bg-white shadow-sm">
                <CompanyProfileTabs
                    :active="activeTab"
                    :campaign-count="campaigns.length"
                    @update:active="activeTab = $event"
                />

                <!-- ===================== TAB PROFIL ===================== -->
                <div v-if="activeTab === 'profile'" class="p-5 sm:p-7">
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                            <span
                                class="flex h-9 w-9 items-center justify-center rounded-lg bg-white text-sm-red shadow-sm"
                            >
                                <Wallet class="h-4 w-4" />
                            </span>
                            <p
                                class="mt-3 text-[10px] font-semibold tracking-wide text-sm-card/55 uppercase"
                            >
                                Rentang dukungan
                            </p>
                            <p class="mt-1 text-sm font-semibold text-sm-navy">
                                {{ funding }}
                            </p>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                            <span
                                class="flex h-9 w-9 items-center justify-center rounded-lg bg-white text-sm-red shadow-sm"
                            >
                                <Megaphone class="h-4 w-4" />
                            </span>
                            <p
                                class="mt-3 text-[10px] font-semibold tracking-wide text-sm-card/55 uppercase"
                            >
                                Kategori sponsorship
                            </p>
                            <p class="mt-1 text-sm font-semibold text-sm-navy">
                                {{ company.categories.length }} kategori
                            </p>
                        </div>

                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                            <span
                                class="flex h-9 w-9 items-center justify-center rounded-lg bg-white text-sm-red shadow-sm"
                            >
                                <Star class="h-4 w-4" />
                            </span>
                            <p
                                class="mt-3 text-[10px] font-semibold tracking-wide text-sm-card/55 uppercase"
                            >
                                Rating perusahaan
                            </p>
                            <p class="mt-1 text-sm font-semibold text-sm-navy">
                                {{ rating }} / 5.0
                            </p>
                        </div>
                    </div>

                    <div class="mt-7">
                        <h2 class="text-sm font-semibold text-sm-navy">
                            Deskripsi perusahaan
                        </h2>
                        <p class="mt-2 text-sm leading-relaxed text-sm-card/75">
                            {{
                                company.description ||
                                'Deskripsi perusahaan belum diisi.'
                            }}
                        </p>
                    </div>

                    <div class="mt-6">
                        <h2 class="text-sm font-semibold text-sm-navy">
                            Kategori sponsorship yang diminati
                        </h2>
                        <div
                            v-if="company.categories.length"
                            class="mt-2 flex flex-wrap gap-2"
                        >
                            <span
                                v-for="category in company.categories"
                                :key="category.id"
                                class="rounded-full bg-red-50 px-3 py-1.5 text-[11px] font-semibold text-sm-red"
                            >
                                {{ category.name }}
                            </span>
                        </div>
                        <p v-else class="mt-2 text-xs text-sm-card/60">
                            Belum ada kategori yang dipilih.
                        </p>
                    </div>

                    <div class="mt-6 grid gap-5 md:grid-cols-2">
                        <div>
                            <h2 class="flex items-center gap-2 text-sm font-semibold text-sm-navy">
                                <Users class="h-4 w-4 text-sm-red" />
                                Jenis event yang diminati
                            </h2>
                            <div
                                v-if="company.event_types.length"
                                class="mt-2 flex flex-wrap gap-2"
                            >
                                <span
                                    v-for="type in company.event_types"
                                    :key="type"
                                    class="rounded-full bg-gray-100 px-3 py-1.5 text-[11px] font-medium text-sm-card"
                                >
                                    {{ type }}
                                </span>
                            </div>
                            <p v-else class="mt-2 text-xs text-sm-card/60">
                                Belum diisi.
                            </p>
                        </div>

                        <div>
                            <h2 class="flex items-center gap-2 text-sm font-semibold text-sm-navy">
                                <Target class="h-4 w-4 text-sm-red" />
                                Target audiens
                            </h2>
                            <p class="mt-2 text-xs leading-relaxed text-sm-card/70">
                                {{ company.target_audience || 'Belum diisi.' }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 rounded-xl border border-gray-200 p-4">
                        <h2 class="flex items-center gap-2 text-sm font-semibold text-sm-navy">
                            <Mail class="h-4 w-4 text-sm-red" />
                            Informasi kontak
                        </h2>
                        <div
                            class="mt-2 flex flex-wrap gap-x-6 gap-y-1 text-xs text-sm-card/75"
                        >
                            <span>
                                Email: {{ company.contact_email ?? '-' }}
                            </span>
                            <span>
                                Telepon: {{ company.contact_phone ?? '-' }}
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <Globe class="h-3 w-3" />
                                Website: {{ company.website || '-' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- ===================== TAB CAMPAIGN ===================== -->
                <div v-else-if="activeTab === 'campaign'" class="p-5 sm:p-7">
                    <div v-if="campaigns.length" class="grid gap-5 md:grid-cols-2">
                        <CampaignCard
                            v-for="campaign in campaigns"
                            :key="campaign.id"
                            :campaign="campaign"
                        />
                    </div>

                    <div v-else>
                        <CampaignEmptyState />

                        <div
                            v-if="canSendProposal"
                            class="mt-5 text-center"
                        >
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-lg bg-sm-red px-5 py-2.5 text-xs font-semibold text-white transition-colors hover:bg-sm-red-dark"
                                @click="openSendModal"
                            >
                                <Send class="h-4 w-4" />
                                Kirim Proposal Berdasarkan Profil
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ===================== TAB POSTINGAN ===================== -->
                <div v-else class="p-5 sm:p-7">
                    <div v-if="posts.length" class="space-y-4">
                        <article
                            v-for="post in posts"
                            :key="post.id"
                            class="overflow-hidden rounded-xl border border-gray-200 md:flex"
                        >
                            <div
                                class="h-44 shrink-0 bg-sm-navy md:h-auto md:w-52"
                            >
                                <img
                                    v-if="post.image_url"
                                    :src="post.image_url"
                                    :alt="company.name"
                                    class="h-full w-full object-cover"
                                />
                                <div
                                    v-else
                                    class="flex h-full w-full items-center justify-center bg-gradient-to-br from-sm-navy to-sm-card text-white/80"
                                >
                                    <Megaphone class="h-8 w-8" />
                                </div>
                            </div>

                            <div class="flex-1 p-5">
                                <p
                                    class="text-[10px] font-semibold tracking-wide text-sm-red uppercase"
                                >
                                    Postingan perusahaan
                                </p>
                                <p class="mt-2 text-sm leading-relaxed text-sm-navy">
                                    {{ post.description || 'Postingan tanpa teks.' }}
                                </p>
                                <p class="mt-3 text-[10px] text-sm-card/50">
                                    {{ formatDate(post.created_at) }}
                                </p>
                            </div>
                        </article>
                    </div>

                    <div
                        v-else
                        class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-10 text-center"
                    >
                        <span
                            class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-50 text-sm-red"
                        >
                            <Megaphone class="h-7 w-7" />
                        </span>
                        <h2 class="mt-4 text-lg font-semibold text-sm-navy">
                            Belum Ada Postingan
                        </h2>
                        <p
                            class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-sm-card/70"
                        >
                            Perusahaan belum membagikan informasi atau aktivitas
                            terbaru.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <SendProposalModal
            v-model:open="modalOpen"
            :recommendation="sendTarget"
            :proposals="proposals"
            :default-proposal-id="defaultProposalId"
        />
    </div>
</template>
