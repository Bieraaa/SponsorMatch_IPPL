<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, type Component } from 'vue';
import {
    ArrowRight,
    Banknote,
    CalendarDays,
    Check,
    ChevronRight,
    CircleAlert,
    ClipboardList,
    Crown,
    Eye,
    FileText,
    Handshake,
    MapPin,
    Megaphone,
    MessageCircle,
    Send,
    Sparkles,
    Upload,
    CircleCheck,
    Wallet,
    Clock,
} from '@lucide/vue';
import { index as postsIndex } from '@/routes/posts';
import { index as sponsorProposalsIndex } from '@/routes/sponsor/proposals';
import { index as proposalsIndex } from '@/routes/proposals';
import type { PostItem } from '@/types/post';

/**
 * Blok A — Dashboard EO (Figma frame "Dashboard EO", export 19 Okt 2026).
 *
 * Semua ukuran/warna dari analisis piksel export 1440x814:
 * - Latar konten navy #161E2F.
 * - Hero sapaan: gradient merah #B51A2B → #630E1E (kiri→kanan), radius 24,
 *   "Halo, {nama}" putih ~40px + subteks putih 85%.
 * - 4 kartu statistik putih (Proposal diterima/ditolak/terkirim/dibalas).
 * - "Rekomendasi Sponsor": 3 kartu putih — nama, badge "% Cocok"
 *   (hijau/amber/merah), Rentang Dukungan, Keterangan, tombol
 *   "Lihat Sekarang".
 * - Kolom kanan: "Aksi Cepat" (panel #EDEDED, 4 tombol putih 2x2),
 *   kartu gradient "Upgrade ke Premium" + tombol amber "Lihat Paket",
 *   "Postingan Terbaru" (4 kartu ringkas + 3 counter).
 *
 * Statistik, rekomendasi, dan postingan terbaru berasal dari database.
 * Rekomendasi hanya memakai baris `matches` yang sudah tersimpan.
 *
 * Dashboard Perusahaan memakai sidebar & topbar yang sama, tetapi konten
 * mengikuti frame Figma "Dashboard Perusahaan": latar putih, sapaan nama
 * badan usaha, 4 statistik, rekomendasi event, aksi cepat, premium, dan
 * postingan terbaru.
 */
const page = usePage();
const user = computed(() => page.props.auth.user);
const isEo = computed(() => user.value?.role !== 'sponsor');

type DashboardEoData = {
    stats: {
        terkirim: { value: number; diff: number };
        disetujui: { value: number; diff: number };
        pendanaan: number;
        target_pendanaan: number;
        persentase_pendanaan: number;
        perlu_tindak_lanjut: number;
    };
    pipeline: {
        draft: number;
        dikirim: number;
        ditinjau: number;
        negosiasi: number;
        disetujui: number;
        ditolak: number;
        siap_dikirim: number;
    };
    followUps: {
        id: number;
        proposalId: number;
        title: string;
        description: string;
    }[];
    recommendations: {
        company_id: number;
        company_name: string;
        logo_url: string | null;
        score: number;
        reason: string | null;
        factors: string[];
        funding_min: number | null;
        funding_max: number | null;
        proposal_id: number;
        proposal_title: string;
        proposal_category: string | null;
        campaign: { id: number; title: string } | null;
        source: 'profile' | 'campaign';
        source_label: string;
        already_sent: boolean;
        href: string;
    }[];
    latestPosts: PostItem[];
};

type DashboardCompanyData = {
    stats: {
        campaign_total: number;
        campaign_active: number;
        proposals_received: number;
        active_collaborations: number;
        deals: number;
        pending_offers: number;
    };
    campaigns: {
        id: number;
        title: string;
        status: string;
        is_active: boolean;
    }[];
    recommendations: {
        proposal_id: number;
        title: string;
        match: number;
        category: string;
        date: string;
        location: string | null;
        budget: string;
        description: string | null;
        campaign: { id: number; title: string } | null;
        source: 'profile' | 'campaign';
        source_label: string;
        href: string;
    }[];
    recommendation_ai: {
        generated: boolean;
        message: string | null;
    };
    has_campaigns: boolean;
    has_profile: boolean;
    optional_message: string;
    latestPosts: PostItem[];
};

const props = defineProps<{
    dashboardEo?: DashboardEoData;
    companyDashboard?: DashboardCompanyData;
}>();

const companyName = computed(() => {
    const sharedName = user.value?.company_name;

    if (typeof sharedName === 'string' && sharedName.trim() !== '') {
        return sharedName;
    }

    return user.value?.name ?? 'Perusahaan';
});

const rupiah = (n: number) => `Rp ${n.toLocaleString('id-ID')}`;

const statCards = computed(() => {
    const s = props.dashboardEo?.stats;

    return [
        {
            key: 'terkirim',
            label: 'Proposal Terkirim',
            display: String(s?.terkirim.value ?? 0),
            diff: s?.terkirim.diff ?? null,
            icon: Send,
            circle: 'bg-blue-500',
            hint: null as string | null,
        },
        {
            key: 'disetujui',
            label: 'Proposal Disetujui',
            display: String(s?.disetujui.value ?? 0),
            diff: s?.disetujui.diff ?? null,
            icon: CircleCheck,
            circle: 'bg-green-500',
            hint: null,
        },
        {
            key: 'pendanaan',
            label: 'Target Pendanaan',
            display: `${s?.persentase_pendanaan ?? 0}%`,
            diff: null,
            icon: Wallet,
            circle: 'bg-orange-500',
            hint: `Terkumpul ${rupiah(s?.pendanaan ?? 0)} dari target ${rupiah(s?.target_pendanaan ?? 0)}`,
        },
        {
            key: 'tindak',
            label: 'Perlu Tindak Lanjut',
            display: String(s?.perlu_tindak_lanjut ?? 0),
            diff: null,
            icon: Clock,
            circle: 'bg-sm-red',
            hint: 'Proposal menunggu tindak lanjut',
        },
    ];
});

const pipelineSteps = computed(() => {
    const p = props.dashboardEo?.pipeline;

    return [
        {
            key: 'draft',
            label: 'Draft',
            count: p?.draft ?? 0,
            icon: FileText,
            circle: 'bg-gray-100 text-gray-500',
            badge: 'bg-gray-100 text-gray-600',
            line: 'bg-gray-300',
        },
        {
            key: 'dikirim',
            label: 'Dikirim',
            count: p?.dikirim ?? 0,
            icon: Send,
            circle: 'bg-blue-100 text-blue-600',
            badge: 'bg-blue-100 text-blue-700',
            line: 'bg-blue-300',
        },
        {
            key: 'ditinjau',
            label: 'Ditinjau',
            count: p?.ditinjau ?? 0,
            icon: Eye,
            circle: 'bg-orange-100 text-orange-600',
            badge: 'bg-orange-100 text-orange-700',
            line: 'bg-orange-300',
        },
        {
            key: 'negosiasi',
            label: 'Negosiasi',
            count: p?.negosiasi ?? 0,
            icon: MessageCircle,
            circle: 'bg-purple-100 text-purple-600',
            badge: 'bg-purple-100 text-purple-700',
            line: 'bg-purple-300',
        },
        {
            key: 'disetujui',
            label: 'Disetujui',
            count: p?.disetujui ?? 0,
            icon: Check,
            circle: 'bg-green-100 text-green-600',
            badge: 'bg-green-100 text-green-700',
            line: 'bg-green-300',
        },
    ];
});

const eoRecommendations = computed(
    () => props.dashboardEo?.recommendations ?? [],
);

const fundingRange = (min: number | null, max: number | null): string => {
    if (min === null && max === null) {
        return 'Belum ditentukan';
    }

    const format = (value: number | null) =>
        value === null ? '-' : `Rp ${value.toLocaleString('id-ID')}`;

    return `${format(min)} – ${format(max)}`;
};

const matchTint = (match: number) =>
    match >= 80
        ? 'bg-green-100 text-green-700'
        : match >= 50
          ? 'bg-amber-100 text-amber-700'
          : 'bg-red-100 text-sm-red';

const quickActions = [
    {
        label: 'Buat Proposal',
        icon: FileText,
        href: `${proposalsIndex().url}?buat=1`,
    },
    {
        label: 'Upload Proposal Event',
        icon: Upload,
        href: `${proposalsIndex().url}?buat=1`,
    },
    {
        label: 'Buat Postingan',
        icon: Megaphone,
        href: `${postsIndex().url}?create=1`,
    },
    { label: 'Kelola Penawaran', icon: Send, href: '/offers' },
];

const latestPosts = computed<PostItem[]>(
    () =>
        props.dashboardEo?.latestPosts ??
        props.companyDashboard?.latestPosts ??
        [],
);

const postsHref = `${postsIndex().url}?scope=mine`;

function relativeTime(value: string | null): string {
    if (!value) {
        return 'Baru saja';
    }

    const date = new Date(value);
    const seconds = Math.floor((Date.now() - date.getTime()) / 1000);

    if (seconds < 60) {
        return 'Baru saja';
    }

    const minutes = Math.floor(seconds / 60);

    if (minutes < 60) {
        return `${minutes} menit lalu`;
    }

    const hours = Math.floor(minutes / 60);

    if (hours < 24) {
        return `${hours} jam lalu`;
    }

    const days = Math.floor(hours / 24);

    if (days < 7) {
        return `${days} hari lalu`;
    }

    return date.toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

/**
 * Statistik nyata dari `companyDashboard`. Campaign TIDAK wajib: angka 0
 * pada "Campaign aktif" bukan berarti perusahaan tidak bisa menerima
 * proposal.
 */
type CompanyStat = {
    label: string;
    value: number | string;
    icon: Component;
    circle: string;
    hint: string;
    href?: string;
};

const companyStats = computed<CompanyStat[]>(() => {
    const stats = props.companyDashboard?.stats;

    return [
        {
            label: 'Campaign aktif',
            value: stats?.campaign_active ?? 0,
            icon: ClipboardList,
            circle: 'bg-[#46B450]',
            hint: 'Campaign yang sedang berjalan',
            href: '/company/campaigns',
        },
        {
            label: 'Proposal Masuk',
            value: stats?.proposals_received ?? 0,
            icon: Send,
            circle: 'bg-[#5B7DBE]',
            hint: 'Proposal dari Event Organizer',
            href: sponsorProposalsIndex().url,
        },
        {
            label: 'Kolaborasi aktif',
            value: stats?.active_collaborations ?? 0,
            icon: CalendarDays,
            circle: 'bg-[#F7836B]',
            hint: 'Kerja sama yang sedang berjalan',
            href: '/collaborations',
        },
        {
            label: 'Penawaran',
            value: stats?.pending_offers ?? 0,
            icon: Handshake,
            circle: 'bg-[#F3B300]',
            hint: 'Penawaran yang menunggu respons',
            href: '/offers',
        },
    ];
});

const dashboardRecommendations = computed(
    () => props.companyDashboard?.recommendations ?? [],
);

type CompanyQuickAction = {
    label: string;
    icon: Component;
    href: string;
};

const companyQuickActions: CompanyQuickAction[] = [
    { label: 'Kelola Campaign', icon: Megaphone, href: '/company/campaigns' },
    {
        label: 'Buat Campaign',
        icon: Upload,
        href: '/company/campaigns?create=1',
    },
    { label: 'Profil Perusahaan', icon: FileText, href: '/company/profile' },
    {
        label: 'Proposal Masuk',
        icon: Handshake,
        href: sponsorProposalsIndex().url,
    },
];

const matchBadgeTint = (match: number) =>
    match >= 80
        ? 'bg-[#46B450]'
        : match >= 50
          ? 'bg-[#F3B300]'
          : 'bg-[#E1443E]';
</script>

<template>
    <Head :title="isEo ? 'Dashboard' : 'Dashboard Perusahaan'" />

    <!-- EO memakai latar navy; dashboard perusahaan memakai latar putih. -->
    <div class="min-h-full pb-10" :class="isEo ? 'bg-sm-navy' : 'bg-[#F7F8FA]'">
        <!-- ===================== EO ===================== -->
        <div
            v-if="isEo"
            class="mx-auto grid w-full max-w-[1180px] items-start gap-6 px-4 py-6 xl:grid-cols-[minmax(0,1fr)_294px] xl:px-0"
        >
            <!-- ---------- Kolom kiri ---------- -->
            <div class="min-w-0 space-y-6">
                <!-- Hero sapaan (gradient merah, radius 24) -->
                <div
                    class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-sm-red to-[#630E1E] px-7 py-7 text-white shadow-sm sm:px-8"
                >
                    <div
                        class="pointer-events-none absolute -top-20 -right-10 h-64 w-64 rounded-full bg-white/5"
                        aria-hidden="true"
                    />
                    <div
                        class="pointer-events-none absolute right-40 -bottom-24 h-56 w-56 rounded-full bg-white/5"
                        aria-hidden="true"
                    />
                    <h1
                        class="relative text-3xl leading-tight font-bold lg:text-4xl"
                    >
                        Halo, {{ user?.name ?? 'Pengguna' }} 👋
                    </h1>
                    <p class="relative mt-2 text-lg font-semibold lg:text-xl">
                        Berikut adalah ringkasan progres sponsorship event kamu.
                    </p>
                    <p class="relative mt-3 max-w-md text-sm text-white/90">
                        Terus semangat! Kamu sudah selangkah lebih dekat untuk
                        mewujudkan event yang lebih besar!
                    </p>
                </div>

                <!-- 4 statistik EO -->
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
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
                            <p
                                class="truncate text-sm font-semibold text-sm-navy"
                            >
                                {{ card.label }}
                            </p>
                            <p
                                class="mt-0.5 text-[30px] leading-tight font-bold text-sm-navy xl:text-[32px]"
                            >
                                {{ card.display }}
                            </p>
                            <p
                                v-if="card.diff !== null"
                                class="mt-1 line-clamp-2 min-h-[2.4em] text-[11px] leading-snug"
                                :class="
                                    card.diff >= 0
                                        ? 'text-emerald-600'
                                        : 'text-sm-red'
                                "
                            >
                                {{ card.diff >= 0 ? '+' : ''
                                }}{{ card.diff }} dibanding 30 hari lalu
                            </p>
                            <p
                                v-else
                                class="mt-1 line-clamp-2 min-h-[2.4em] text-[11px] leading-snug text-sm-card/60"
                            >
                                {{ card.hint }}
                            </p>
                        </div>
                    </article>
                </div>

                <!-- Progress Proposal + Perlu Tindakan -->
                <div
                    class="grid gap-4 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]"
                >
                    <section
                        class="flex h-full flex-col rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="text-lg font-bold text-sm-navy">
                                    Progress Proposal Sponsorship
                                </h2>
                                <p class="mt-0.5 text-xs text-sky-700">
                                    Pantau status setiap proposal kamu secara
                                    real-time.
                                </p>
                            </div>
                            <Link
                                :href="proposalsIndex()"
                                class="flex shrink-0 items-center gap-1 text-xs font-medium text-sm-navy hover:underline"
                            >
                                Lihat semua <ArrowRight class="h-3.5 w-3.5" />
                            </Link>
                        </div>

                        <ol
                            class="mt-6 grid flex-1 grid-cols-5 items-start gap-1"
                        >
                            <li
                                v-for="(step, i) in pipelineSteps"
                                :key="step.key"
                                class="relative flex min-w-0 flex-col items-center"
                            >
                                <span
                                    v-if="i < pipelineSteps.length - 1"
                                    class="absolute top-5 left-1/2 h-0.5 w-full"
                                    :class="step.line"
                                    aria-hidden="true"
                                />
                                <span
                                    class="relative z-10 flex h-10 w-10 items-center justify-center rounded-full"
                                    :class="step.circle"
                                >
                                    <component
                                        :is="step.icon"
                                        class="h-4 w-4"
                                    />
                                </span>
                                <span
                                    class="mt-2 max-w-full truncate text-[11px] font-semibold text-sm-navy"
                                    >{{ step.label }}</span
                                >
                                <span
                                    class="mt-1 flex h-6 min-w-6 items-center justify-center rounded-full px-2 text-xs font-bold"
                                    :class="step.badge"
                                    >{{ step.count }}</span
                                >
                            </li>
                        </ol>

                        <p
                            class="mt-4 border-t border-gray-100 pt-3 text-[11px] text-sm-card/60"
                        >
                            Siap dikirim:
                            {{ props.dashboardEo?.pipeline.siap_dikirim ?? 0 }}
                            · Ditolak:
                            {{ props.dashboardEo?.pipeline.ditolak ?? 0 }}
                        </p>
                    </section>

                    <section
                        class="flex h-full flex-col overflow-hidden rounded-2xl border border-red-100 bg-white shadow-sm"
                    >
                        <div
                            class="flex shrink-0 items-center gap-3 bg-red-50 px-4 py-3"
                        >
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-sm-red text-white"
                            >
                                <CircleAlert class="h-5 w-5" />
                            </span>
                            <div class="min-w-0">
                                <h3 class="text-sm font-bold text-sm-red">
                                    Perlu Tindakan
                                </h3>
                                <p class="truncate text-xs text-sm-navy/70">
                                    {{
                                        props.dashboardEo?.stats
                                            .perlu_tindak_lanjut ?? 0
                                    }}
                                    proposal perlu ditindaklanjuti
                                </p>
                            </div>
                        </div>
                        <ul
                            v-if="props.dashboardEo?.followUps.length"
                            class="flex-1 divide-y divide-gray-100"
                        >
                            <li
                                v-for="item in props.dashboardEo.followUps"
                                :key="item.id"
                            >
                                <Link
                                    :href="`${proposalsIndex().url}?selected=${item.proposalId}`"
                                    class="flex min-h-[52px] items-center justify-between gap-3 px-4 py-3 hover:bg-red-50/50"
                                >
                                    <div class="min-w-0">
                                        <p
                                            class="truncate text-xs font-semibold text-sm-navy"
                                        >
                                            {{ item.title }}
                                        </p>
                                        <p
                                            class="truncate text-[11px] text-sm-red/80"
                                        >
                                            {{ item.description }}
                                        </p>
                                    </div>
                                    <ChevronRight
                                        class="h-4 w-4 shrink-0 text-sm-card/50"
                                    />
                                </Link>
                            </li>
                        </ul>
                        <p
                            v-else
                            class="flex flex-1 items-center justify-center px-4 py-6 text-center text-xs text-sm-card/60"
                        >
                            Tidak ada proposal yang perlu ditindaklanjuti.
                        </p>
                    </section>
                </div>

                <!-- Rekomendasi Sponsor -->
                <section>
                    <div class="flex items-end justify-between gap-3">
                        <div>
                            <h2
                                class="text-xl font-semibold text-white lg:text-2xl"
                            >
                                Rekomendasi Sponsor
                            </h2>
                            <p class="mt-1 text-xs text-white/70">
                                Diambil dari hasil rekomendasi yang sudah
                                dihitung untuk proposal kamu.
                            </p>
                        </div>
                        <Link
                            href="/recommendations"
                            class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-white/90 transition-colors hover:text-white"
                        >
                            Lihat semua
                            <ArrowRight class="h-3.5 w-3.5" />
                        </Link>
                    </div>

                    <div
                        v-if="eoRecommendations.length === 0"
                        class="mt-4 rounded-2xl border border-dashed border-white/20 bg-white/5 p-8 text-center"
                    >
                        <span
                            class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-white/10 text-white"
                        >
                            <Sparkles class="h-7 w-7" />
                        </span>
                        <p class="mt-4 text-sm font-semibold text-white">
                            Belum ada rekomendasi sponsor
                        </p>
                        <p
                            class="mx-auto mt-1 max-w-md text-xs leading-relaxed text-white/70"
                        >
                            Buka halaman Rekomendasi Sponsor untuk menghitung
                            kecocokan perusahaan dengan proposal kamu. Hasilnya
                            akan muncul di sini.
                        </p>
                        <Link
                            href="/recommendations"
                            class="mt-4 inline-flex items-center gap-2 rounded-lg bg-sm-red px-4 py-2 text-xs font-semibold text-white transition-colors hover:bg-sm-red-dark"
                        >
                            <Sparkles class="h-4 w-4" />
                            Buka Rekomendasi
                        </Link>
                    </div>

                    <div
                        v-else
                        class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <article
                            v-for="sponsor in eoRecommendations"
                            :key="`${sponsor.company_id}-${sponsor.proposal_id}`"
                            class="flex h-full min-w-0 flex-col rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-sm-card text-sm font-semibold text-white"
                                >
                                    <img
                                        v-if="sponsor.logo_url"
                                        :src="sponsor.logo_url"
                                        :alt="`Logo ${sponsor.company_name}`"
                                        class="h-full w-full object-cover"
                                    />
                                    <template v-else>
                                        {{ sponsor.company_name.charAt(0) }}
                                    </template>
                                </span>
                                <div class="min-w-0">
                                    <h3
                                        class="truncate font-semibold text-sm-navy"
                                    >
                                        {{ sponsor.company_name }}
                                    </h3>
                                    <p
                                        class="truncate text-[11px] text-sm-card/60"
                                    >
                                        {{
                                            sponsor.proposal_category ?? 'Event'
                                        }}
                                    </p>
                                </div>
                            </div>

                            <span
                                class="mt-4 w-fit rounded-full px-3 py-1 text-xs font-semibold"
                                :class="matchTint(sponsor.score)"
                            >
                                {{ sponsor.score }}% Cocok
                            </span>

                            <div class="mt-3 rounded-xl bg-gray-50 px-3 py-2">
                                <p
                                    class="text-[10px] font-semibold tracking-wide text-sm-card/55 uppercase"
                                >
                                    Untuk sponsorship
                                </p>
                                <p
                                    class="mt-0.5 line-clamp-2 text-xs font-semibold text-sm-navy"
                                >
                                    {{ sponsor.proposal_title }}
                                </p>
                                <p
                                    class="mt-1 flex items-center gap-1 text-[10px] font-medium text-sm-red"
                                >
                                    <Megaphone class="h-3 w-3 shrink-0" />
                                    <span class="truncate">
                                        {{
                                            sponsor.campaign
                                                ? `Campaign: ${sponsor.campaign.title}`
                                                : sponsor.source_label
                                        }}
                                    </span>
                                </p>
                            </div>

                            <p class="mt-4 text-xs font-semibold text-sm-card">
                                Rentang Dukungan:
                            </p>
                            <p class="mt-1 text-sm text-sm-card/80">
                                {{
                                    fundingRange(
                                        sponsor.funding_min,
                                        sponsor.funding_max,
                                    )
                                }}
                            </p>

                            <p class="mt-3 text-xs font-semibold text-sm-card">
                                Keterangan
                            </p>
                            <p
                                class="mt-1 line-clamp-5 flex-1 text-sm leading-relaxed text-sm-card/80"
                            >
                                {{
                                    sponsor.reason ??
                                    'Belum ada keterangan dari analisis.'
                                }}
                            </p>

                            <Link
                                :href="sponsor.href"
                                class="mt-5 w-full rounded-lg bg-sm-red py-2.5 text-center text-sm font-semibold text-white transition-colors hover:bg-sm-red-dark"
                            >
                                {{
                                    sponsor.already_sent
                                        ? 'Lihat Status'
                                        : 'Lihat Sekarang'
                                }}
                            </Link>
                        </article>
                    </div>
                </section>
            </div>

            <!-- ---------- Kolom kanan ---------- -->
            <!-- ---------- QUICK ACTION EO ---------- -->
            <div class="min-w-0 space-y-5">
                <!-- Aksi Cepat (panel abu #EDEDED, 4 tombol putih 2x2) -->
                <section
                    class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm"
                >
                    <h3 class="text-base font-bold text-sm-navy">Aksi Cepat</h3>
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <Link
                            v-for="action in quickActions"
                            :key="action.label"
                            :href="action.href"
                            class="flex min-h-[84px] flex-col items-center justify-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-2 py-3 text-center text-xs leading-tight font-medium text-sm-navy transition hover:-translate-y-0.5 hover:bg-white hover:shadow-md"
                        >
                            <component :is="action.icon" class="h-6 w-6" />
                            {{ action.label }}
                        </Link>
                    </div>
                </section>

                <!-- Upgrade ke Premium (gradient merah vertikal + tombol amber) -->
                <section
                    class="rounded-2xl bg-gradient-to-br from-sm-red to-[#5D0F19] p-5 text-white shadow-sm"
                >
                    <div class="flex items-center gap-2">
                        <Crown class="h-6 w-6 shrink-0 text-sm-amber" />
                        <h3 class="text-lg font-bold">Upgrade ke Premium</h3>
                    </div>
                    <p class="mt-2 text-xs leading-relaxed text-white/85">
                        Dapatkan lebih banyak fitur yang memudahkanmu dalam
                        mengelola proposal.
                    </p>
                    <Link
                        href="/billing"
                        class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-sm-amber py-2.5 text-xs font-semibold text-sm-navy transition-opacity hover:opacity-90"
                    >
                        Lihat Paket <ArrowRight class="h-3.5 w-3.5" />
                    </Link>
                </section>

                <!-- Postingan Terbaru -->
                <section
                    class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <h3 class="text-base font-bold text-sm-navy">
                            Postingan Terbaru
                        </h3>
                        <div class="flex shrink-0 items-center gap-2">
                            <Link
                                :href="`${postsIndex().url}?create=1`"
                                class="rounded-lg bg-sm-red px-2.5 py-1.5 text-[10px] font-semibold text-white transition-colors hover:bg-sm-red-dark"
                            >
                                Buat
                            </Link>
                            <Link
                                :href="postsHref"
                                class="text-right text-[10px] leading-tight font-medium text-sm-navy hover:underline"
                            >
                                Lihat<br />postingan
                            </Link>
                        </div>
                    </div>

                    <div
                        v-if="latestPosts.length === 0"
                        class="mt-3 rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-center"
                    >
                        <Megaphone class="mx-auto h-6 w-6 text-gray-400" />
                        <p class="mt-2 text-xs font-semibold text-sm-navy">
                            Belum ada postingan
                        </p>
                        <p
                            class="mt-1 text-[10px] leading-relaxed text-sm-card/60"
                        >
                            Postingan kegiatan dan informasi organisasi yang
                            kamu buat akan tampil di sini.
                        </p>
                        <Link
                            :href="`${postsIndex().url}?create=1`"
                            class="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-sm-red px-3 py-1.5 text-[10px] font-semibold text-white transition-colors hover:bg-sm-red-dark"
                        >
                            <Megaphone class="h-3 w-3" />
                            Buat Postingan
                        </Link>
                    </div>

                    <ul v-else class="mt-2 divide-y divide-gray-100">
                        <li v-for="post in latestPosts" :key="post.id">
                            <Link
                                :href="postsHref"
                                class="flex gap-3 py-3 first:pt-2 last:pb-0"
                            >
                                <div
                                    class="flex h-[52px] w-[52px] shrink-0 items-center justify-center overflow-hidden rounded-md bg-gradient-to-br from-sm-card to-sm-navy"
                                >
                                    <img
                                        v-if="post.image_url"
                                        :src="post.image_url"
                                        :alt="post.author_name"
                                        class="h-full w-full object-cover"
                                    />
                                    <Megaphone
                                        v-else
                                        class="h-5 w-5 text-white/70"
                                    />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="line-clamp-2 text-[11px] leading-snug font-semibold text-sm-navy"
                                    >
                                        {{
                                            post.description ||
                                            'Postingan gambar'
                                        }}
                                    </p>
                                    <p class="mt-1 text-[10px] text-sm-card/50">
                                        {{ post.author_role_label }} ·
                                        {{ relativeTime(post.created_at) }}
                                    </p>
                                </div>
                                <ArrowRight
                                    class="mt-1 h-4 w-4 shrink-0 text-sm-card/50"
                                />
                            </Link>
                        </li>
                    </ul>
                </section>
            </div>
        </div>

        <!-- ===================== PERUSAHAAN ===================== -->
        <div
            v-else
            class="mx-auto grid w-full max-w-[1180px] items-start gap-6 px-4 py-6 xl:grid-cols-[minmax(0,1fr)_294px] xl:px-0"
        >
            <!-- ---------- Kolom kiri ---------- -->
            <div class="min-w-0 space-y-6">
                <!-- Hero sapaan perusahaan -->
                <div
                    class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-sm-red to-[#630E1E] px-7 py-7 text-white shadow-sm sm:px-8"
                >
                    <div
                        class="pointer-events-none absolute -top-16 -right-10 h-48 w-48 rounded-full bg-white/5"
                        aria-hidden="true"
                    />
                    <h1
                        class="relative text-3xl leading-tight font-bold lg:text-4xl"
                    >
                        Selamat datang, {{ companyName }}
                        <span class="ml-1 inline-block">👋</span>
                    </h1>
                    <p class="relative mt-3 max-w-md text-sm text-white/90">
                        Temukan event yang sesuai dengan kebutuhan perusahaan
                        anda. Dapatkan rekomendasi terbaik melalui AI, dan
                        jelajahi campaign sponsor yang sedang berlangsung
                    </p>
                </div>

                <!-- 4 statistik perusahaan -->
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <component
                        :is="card.href ? Link : 'div'"
                        v-for="card in companyStats"
                        :key="card.label"
                        :href="card.href"
                        class="flex h-full min-h-[116px] items-start gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm"
                        :class="
                            card.href ? 'transition-shadow hover:shadow-md' : ''
                        "
                    >
                        <span
                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full text-white"
                            :class="card.circle"
                        >
                            <component :is="card.icon" class="h-6 w-6" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate text-sm font-semibold text-sm-navy"
                            >
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
                    </component>
                </div>

                <!-- Catatan: campaign bersifat opsional -->
                <div
                    v-if="
                        props.companyDashboard &&
                        !props.companyDashboard.has_campaigns
                    "
                    class="flex flex-col gap-3 rounded-2xl border border-blue-100 bg-blue-50/70 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-[11px] leading-relaxed text-sm-card/80">
                        {{ props.companyDashboard.optional_message }}
                    </p>
                    <Link
                        href="/company/campaigns?create=1"
                        class="inline-flex shrink-0 items-center justify-center rounded-lg bg-sm-red px-3 py-2 text-[11px] font-semibold text-white transition-colors hover:bg-sm-red-dark"
                    >
                        Buat Campaign
                    </Link>
                </div>

                <!-- Rekomendasi event untuk perusahaan -->
                <section>
                    <div class="flex items-end justify-between gap-3">
                        <div class="min-w-0">
                            <h2
                                class="text-xl font-semibold text-sm-navy lg:text-2xl"
                            >
                                Rekomendasi Event
                            </h2>
                            <p class="mt-1 text-xs text-sm-card/60">
                                Diambil dari hasil rekomendasi yang sudah
                                dihitung untuk profil perusahaan.
                            </p>
                        </div>
                        <Link
                            href="/company/recommendations"
                            class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-sm-red hover:text-sm-red-dark"
                        >
                            Lihat semua
                            <ArrowRight class="h-3.5 w-3.5" />
                        </Link>
                    </div>

                    <div
                        v-if="dashboardRecommendations.length === 0"
                        class="mt-4 rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center"
                    >
                        <span
                            class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-white text-sm-navy shadow-sm"
                        >
                            <Sparkles class="h-7 w-7" />
                        </span>
                        <p class="mt-4 text-sm font-semibold text-sm-navy">
                            Belum ada rekomendasi event
                        </p>
                        <p
                            class="mx-auto mt-1 max-w-md text-xs leading-relaxed text-sm-card/65"
                        >
                            Lengkapi profil perusahaan atau perbarui rekomendasi
                            agar AI dapat mencocokkan proposal EO.
                        </p>
                        <Link
                            href="/company/recommendations"
                            class="mt-4 inline-flex items-center gap-2 rounded-lg bg-sm-red px-4 py-2 text-xs font-semibold text-white transition-colors hover:bg-sm-red-dark"
                        >
                            Buka Rekomendasi
                        </Link>
                    </div>

                    <div
                        v-else
                        class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <article
                            v-for="event in dashboardRecommendations"
                            :key="event.proposal_id"
                            class="flex h-full flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"
                        >
                            <!-- Area gambar/cover event -->
                            <div
                                class="h-[72px] shrink-0 bg-gradient-to-r from-sm-navy to-sm-card"
                                aria-hidden="true"
                            ></div>

                            <div class="relative flex flex-1 flex-col p-5">
                                <span
                                    class="absolute -top-3.5 right-4 inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[10px] font-semibold text-white shadow-sm"
                                    :class="matchBadgeTint(event.match)"
                                >
                                    <Check class="h-3 w-3" />
                                    Match {{ event.match }}%
                                </span>

                                <h3
                                    class="line-clamp-2 min-h-[2.6em] pr-24 text-base leading-tight font-semibold text-sm-navy"
                                >
                                    {{ event.title }}
                                </h3>

                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    <span
                                        class="rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-medium text-sm-red"
                                    >
                                        {{ event.category }}
                                    </span>
                                    <span
                                        v-if="event.campaign"
                                        class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-medium text-amber-700"
                                    >
                                        <Megaphone class="h-3 w-3" />
                                        {{ event.campaign.title }}
                                    </span>
                                    <span
                                        v-else
                                        class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-600"
                                    >
                                        {{ event.source_label }}
                                    </span>
                                </div>

                                <div
                                    class="mt-3 space-y-1.5 text-[11px] text-sm-card/80"
                                >
                                    <p class="flex items-center gap-1.5">
                                        <CalendarDays
                                            class="h-3.5 w-3.5 shrink-0"
                                        />
                                        {{ event.date }}
                                    </p>
                                    <p class="flex items-center gap-1.5">
                                        <MapPin class="h-3.5 w-3.5 shrink-0" />
                                        {{
                                            event.location ||
                                            'Lokasi belum ditentukan'
                                        }}
                                    </p>
                                    <p class="flex items-center gap-1.5">
                                        <Banknote
                                            class="h-3.5 w-3.5 shrink-0"
                                        />
                                        {{ event.budget }}
                                    </p>
                                </div>

                                <p
                                    class="mt-3 line-clamp-4 flex-1 text-xs leading-relaxed text-sm-card/70"
                                >
                                    {{
                                        event.description ||
                                        'Belum ada deskripsi event.'
                                    }}
                                </p>

                                <Link
                                    :href="event.href"
                                    class="mt-auto w-full rounded-lg bg-sm-navy py-2.5 text-center text-sm font-semibold text-white transition-colors hover:bg-sm-card"
                                >
                                    Lihat Sekarang
                                </Link>
                            </div>
                        </article>
                    </div>
                </section>
            </div>

            <!-- ---------- Kolom kanan ---------- -->
            <div class="min-w-0 space-y-5">
                <!-- Aksi Cepat -->
                <section
                    class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm"
                >
                    <h3 class="text-base font-bold text-sm-navy">Aksi Cepat</h3>
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <Link
                            v-for="action in companyQuickActions"
                            :key="action.label"
                            :href="action.href"
                            class="flex min-h-[84px] flex-col items-center justify-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-2 py-3 text-center text-xs leading-tight font-medium text-sm-navy transition hover:-translate-y-0.5 hover:bg-white hover:shadow-md"
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
                    class="rounded-2xl bg-gradient-to-br from-sm-red to-[#5D0F19] p-5 text-white shadow-sm"
                >
                    <div class="flex items-center gap-2">
                        <Crown class="h-6 w-6 shrink-0 text-sm-amber" />
                        <h3 class="text-lg font-bold">Upgrade ke Premium</h3>
                    </div>
                    <p class="mt-2 text-xs leading-relaxed text-white/85">
                        Dapatkan lebih banyak fitur yang memudahkanmu dalam
                        mengelola proposal.
                    </p>
                    <Link
                        href="/billing"
                        class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg bg-sm-amber py-2.5 text-xs font-semibold text-sm-navy transition-opacity hover:opacity-90"
                    >
                        Lihat Paket
                        <ArrowRight class="h-3.5 w-3.5" />
                    </Link>
                </section>

                <!-- Postingan Terbaru -->
                <section
                    class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <h3 class="text-base font-bold text-sm-navy">
                            Postingan Terbaru
                        </h3>
                        <div class="flex shrink-0 items-center gap-2">
                            <Link
                                :href="`${postsIndex().url}?create=1`"
                                class="rounded-lg bg-sm-red px-2.5 py-1.5 text-[10px] font-semibold text-white transition-colors hover:bg-sm-red-dark"
                            >
                                Buat
                            </Link>
                            <Link
                                :href="postsHref"
                                class="text-right text-[10px] leading-tight font-medium text-sm-navy hover:underline"
                            >
                                Lihat<br />postingan
                            </Link>
                        </div>
                    </div>

                    <ul
                        v-if="latestPosts.length"
                        class="mt-2 divide-y divide-gray-100"
                    >
                        <li
                            v-for="post in latestPosts"
                            :key="post.id"
                            class="py-3 first:pt-2 last:pb-0"
                        >
                            <Link :href="postsHref" class="flex gap-3">
                                <div
                                    class="flex h-[52px] w-[52px] shrink-0 items-center justify-center overflow-hidden rounded-md bg-gradient-to-br from-sm-card to-sm-navy"
                                >
                                    <img
                                        v-if="post.image_url"
                                        :src="post.image_url"
                                        :alt="post.author_name"
                                        class="h-full w-full object-cover"
                                    />
                                    <Megaphone
                                        v-else
                                        class="h-5 w-5 text-white/70"
                                    />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="line-clamp-2 text-[11px] leading-snug font-semibold text-sm-navy"
                                    >
                                        {{
                                            post.description ||
                                            'Postingan gambar'
                                        }}
                                    </p>
                                    <p class="mt-1 text-[10px] text-sm-card/50">
                                        {{ post.author_role_label }} ·
                                        {{ relativeTime(post.created_at) }}
                                    </p>
                                </div>
                                <ArrowRight
                                    class="mt-1 h-4 w-4 shrink-0 text-sm-card/50"
                                />
                            </Link>
                        </li>
                    </ul>

                    <div
                        v-else
                        class="mt-3 rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-center"
                    >
                        <Megaphone class="mx-auto h-6 w-6 text-gray-400" />
                        <p class="mt-2 text-xs font-semibold text-sm-navy">
                            Belum ada postingan
                        </p>
                        <p
                            class="mt-1 text-[10px] leading-relaxed text-sm-card/60"
                        >
                            Bagikan kegiatan atau informasi terbaru perusahaan
                            agar terlihat oleh Event Organizer.
                        </p>
                        <Link
                            :href="`${postsIndex().url}?create=1`"
                            class="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-sm-red px-3 py-1.5 text-[10px] font-semibold text-white transition-colors hover:bg-sm-red-dark"
                        >
                            <Megaphone class="h-3 w-3" />
                            Buat Postingan
                        </Link>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>
