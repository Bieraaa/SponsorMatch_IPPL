<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ClipboardList, FileText, Megaphone, Plus, Send } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import CampaignCard from '@/components/company/CampaignCard.vue';
import CampaignEmptyState from '@/components/company/CampaignEmptyState.vue';
import CampaignFormModal from '@/components/company/CampaignFormModal.vue';
import { destroy, status as updateStatus } from '@/routes/company/campaigns';
import type {
    CampaignCategoryOption,
    CampaignStats,
    CompanyCampaign,
    UploadConfig,
} from '@/types/company';

/**
 * Halaman "Campaign Saya" milik perusahaan.
 *
 * Campaign bersifat OPSIONAL: perusahaan tetap menerima proposal meski
 * belum membuat campaign. Setiap aksi (edit, aktifkan, nonaktifkan,
 * akhiri) divalidasi ulang di server dengan memeriksa kepemilikan.
 */
const props = defineProps<{
    campaigns: CompanyCampaign[];
    categories: CampaignCategoryOption[];
    stats: CampaignStats;
    bannerConfig: UploadConfig;
    pdfMaxKb: number;
}>();

const modalOpen = ref(false);
const editing = ref<CompanyCampaign | null>(null);
const busyId = ref<number | null>(null);

const activeCount = computed(
    () => props.campaigns.filter((campaign) => campaign.is_active).length,
);

const statCards = computed(() => [
    {
        key: 'total',
        label: 'Total campaign',
        value: props.stats.total,
        icon: ClipboardList,
        circle: 'bg-sm-navy',
        hint: 'Seluruh campaign yang pernah dibuat',
    },
    {
        key: 'active',
        label: 'Campaign aktif',
        value: props.stats.active,
        icon: Megaphone,
        circle: 'bg-[#46B450]',
        hint: 'Tayang sebagai peluang sponsor',
    },
    {
        key: 'draft',
        label: 'Draft / nonaktif',
        value: props.stats.draft,
        icon: FileText,
        circle: 'bg-[#F3B300]',
        hint: 'Belum ditayangkan ke Event Organizer',
    },
    {
        key: 'proposals',
        label: 'Proposal masuk',
        value: props.stats.proposals,
        icon: Send,
        circle: 'bg-[#5B7DBE]',
        hint: 'Proposal yang masuk ke campaign kamu',
    },
]);

function openCreate(): void {
    editing.value = null;
    modalOpen.value = true;
}

function openEdit(campaign: CompanyCampaign): void {
    editing.value = campaign;
    modalOpen.value = true;
}

function changeStatus(
    campaign: CompanyCampaign,
    status: 'active' | 'draft' | 'close',
): void {
    busyId.value = campaign.id;

    router.post(
        updateStatus(campaign.id).url,
        { status },
        {
            preserveScroll: true,
            onFinish: () => {
                busyId.value = null;
            },
            onError: (errors) => {
                toast.error(
                    Object.values(errors)[0] ??
                        'Campaign gagal diubah. Coba lagi.',
                );
            },
        },
    );
}

function endCampaign(campaign: CompanyCampaign): void {
    if (
        !window.confirm(
            `Akhiri campaign "${campaign.title}"? Campaign tidak lagi tampil sebagai peluang aktif, tetapi data historis tetap tersimpan.`,
        )
    ) {
        return;
    }

    busyId.value = campaign.id;

    router.delete(destroy(campaign.id).url, {
        preserveScroll: true,
        onFinish: () => {
            busyId.value = null;
        },
        onError: (errors) => {
            toast.error(
                Object.values(errors)[0] ??
                    'Campaign gagal diakhiri. Coba lagi.',
            );
        },
    });
}

onMounted(() => {
    const url = new URL(window.location.href);

    if (url.searchParams.get('create') === '1') {
        openCreate();
        url.searchParams.delete('create');
        window.history.replaceState({}, '', url.toString());
    }
});
</script>

<template>
    <Head title="Campaign Saya" />

    <div class="min-h-full bg-[#F7F8FA] pb-12">
        <div class="mx-auto w-full max-w-[1180px] px-4 py-6 xl:px-0">
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <h1
                        class="text-2xl font-semibold text-sm-navy lg:text-[30px]"
                    >
                        Campaign Saya
                    </h1>
                    <p class="mt-2 max-w-2xl text-sm text-sm-card/70">
                        Campaign adalah peluang sponsorship yang kamu buka untuk
                        event organizer. Campaign bersifat opsional.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        href="/company/profile"
                        class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 text-xs font-semibold text-sm-navy transition-colors hover:bg-gray-50"
                    >
                        Profil Perusahaan
                    </Link>
                    <button
                        type="button"
                        class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-lg bg-sm-red px-4 text-xs font-semibold text-white transition-colors hover:bg-sm-red-dark"
                        @click="openCreate"
                    >
                        <Plus class="h-4 w-4" />
                        Buat Campaign
                    </button>
                </div>
            </div>

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

            <!-- Catatan opsional -->
            <div
                class="mt-4 flex items-start gap-2 rounded-xl border border-blue-100 bg-blue-50/60 px-4 py-3 text-[11px] leading-relaxed text-sm-card/80"
            >
                <Megaphone class="mt-0.5 h-4 w-4 shrink-0 text-blue-600" />
                <p>
                    Campaign tidak wajib. Perusahaan tetap dapat menerima
                    proposal dari EO meski belum membuat campaign — profil
                    perusahaan sudah cukup untuk mengikuti rekomendasi sponsor.
                </p>
            </div>

            <!-- Daftar campaign -->
            <div v-if="campaigns.length === 0" class="mt-6">
                <CampaignEmptyState owner @create="openCreate" />
            </div>

            <div v-else class="mt-6 space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold text-sm-navy">
                        Semua Campaign
                    </h2>
                    <p class="text-[11px] text-sm-card/55">
                        {{ activeCount }} campaign aktif
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <CampaignCard
                        v-for="campaign in campaigns"
                        :key="campaign.id"
                        :campaign="campaign"
                        owner
                        :busy="busyId === campaign.id"
                        @edit="openEdit"
                        @status="changeStatus"
                        @end="endCampaign"
                    />
                </div>

                <div
                    v-if="stats.proposals > 0"
                    class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <p
                            class="flex items-center gap-2 text-xs font-semibold text-sm-navy"
                        >
                            <ClipboardList class="h-4 w-4 text-sm-red" />
                            {{ stats.proposals }} proposal masuk ke campaign
                            kamu
                        </p>
                        <Link
                            href="/sponsor/proposals"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-[11px] font-semibold text-sm-navy transition-colors hover:bg-gray-50"
                        >
                            <Send class="h-3.5 w-3.5" />
                            Lihat Proposal Masuk
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <CampaignFormModal
            v-model:open="modalOpen"
            :campaign="editing"
            :categories="categories"
            :banner-config="bannerConfig"
            :pdf-max-kb="pdfMaxKb"
        />
    </div>
</template>
