<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref, type Component } from 'vue';
import {
    ArrowRight,
    ArrowUp ,
    ArrowDown,
    Banknote,
    Bookmark,
    CalendarDays,
    Check,
    ClipboardList,
    Crown,
    Eye,
    FileText,
    Handshake,
    Heart,
    MapPin,
    Megaphone,
    MessageCircle,
    Send,
    ThumbsDown,
    ThumbsUp,
    Upload,
    CircleCheck,
    Wallet,
    Clock,
} from '@lucide/vue';
import { index as sponsorProposalsIndex } from '@/routes/sponsor/proposals';
import { index as proposalsIndex } from '@/routes/proposals';
import { index } from '@/routes/proposals';

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
 * Data statistik/sponsor/postingan = dummy sesuai desain; diisi dari
 * database saat backend Blok B (proposal, postingan) sudah ada.
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
    followUps: { id: number; proposalId: number; title: string; description: string }[];
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
        href: string;
    }[];
    recommendation_ai: {
        generated: boolean;
        message: string | null;
    };
    has_campaigns: boolean;
    has_profile: boolean;
    optional_message: string;
};

const props = defineProps<{
    dashboardEo?: DashboardEoData;
    companyDashboard?: DashboardCompanyData;
}>();

const firstName = computed(
    () => (user.value?.name ?? 'Pengguna').split(' ')[0],
);
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
        { key: 'terkirim', label: 'Proposal Terkirim', display: String(s?.terkirim.value ?? 0), diff: s?.terkirim.diff ?? null, icon: Send, circle: 'bg-blue-500' },
        { key: 'disetujui', label: 'Proposal Disetujui', display: String(s?.disetujui.value ?? 0), diff: s?.disetujui.diff ?? null, icon: CircleCheck, circle: 'bg-green-500' },
        { key: 'pendanaan', label: 'Target Pendanaan', display: `${s?.persentase_pendanaan ?? 0}%`, diff: null, icon: Wallet, circle: 'bg-orange-500' },
        { key: 'tindak', label: 'Perlu Tindak Lanjut', display: String(s?.perlu_tindak_lanjut ?? 0), diff: null, icon: Clock, circle: 'bg-sm-red' },
    ];
});

const pipelineSteps = computed(() => {
    const p = props.dashboardEo?.pipeline;

    return [
        { key: 'draft', label: 'Draft', count: p?.draft ?? 0, icon: FileText, circle: 'bg-gray-100 text-gray-500', badge: 'bg-gray-100 text-gray-600', line: 'bg-gray-300' },
        { key: 'dikirim', label: 'Dikirim', count: p?.dikirim ?? 0, icon: Send, circle: 'bg-blue-100 text-blue-600', badge: 'bg-blue-100 text-blue-700', line: 'bg-blue-300' },
        { key: 'ditinjau', label: 'Ditinjau', count: p?.ditinjau ?? 0, icon: Eye, circle: 'bg-orange-100 text-orange-600', badge: 'bg-orange-100 text-orange-700', line: 'bg-orange-300' },
        { key: 'negosiasi', label: 'Negosiasi', count: p?.negosiasi ?? 0, icon: MessageCircle, circle: 'bg-purple-100 text-purple-600', badge: 'bg-purple-100 text-purple-700', line: 'bg-purple-300' },
        { key: 'disetujui', label: 'Disetujui', count: p?.disetujui ?? 0, icon: Check, circle: 'bg-green-100 text-green-600', badge: 'bg-green-100 text-green-700', line: 'bg-green-300' },
    ];
});

const sponsors = [
    {
        name: 'PT Astra International',
        match: 92,
        range: 'Rp50.000.000 - Rp200.000.000',
        note: 'Tertarik melakukan dukungan terhadap event yang berfokus pada pengembangan teknologi otomotif, dan pengembangan SDM.',
    },
    {
        name: 'BCD',
        match: 60,
        range: 'Rp80.000.000 - Rp300.000.000',
        note: 'Tertarik melakukan dukungan terhadap program edukasi dan pengembangan komunitas',
    },
    {
        name: 'Telkom Indonesia',
        match: 20,
        range: 'Rp10.000.000 - Rp40.000.000',
        note: 'Tertarik melakukan dukungan terhadap program pengembangan telekomunikasi dan digitalisasi terhadap sebuah komunitas',
    },
];

const matchTint = (match: number) =>
    match >= 80
        ? 'bg-green-100 text-green-700'
        : match >= 50
          ? 'bg-amber-100 text-amber-700'
          : 'bg-red-100 text-sm-red';

const quickActions = [
    { label: 'Buat Proposal', icon: FileText, href: `${proposalsIndex().url}?buat=1` },
    { label: 'Upload Proposal Event', icon: Upload, href: `${proposalsIndex().url}?buat=1` },
    { label: 'Buat Postingan', icon: Megaphone, href: null as string | null },
    { label: 'Kelola Penawaran', icon: Send, href: null as string | null },
];

const posts = ref([]);

/**
 * Data ringkas dashboard perusahaan. Angka & rekomendasi mengikuti frame
 * Figma "Dashboard Perusahaan"; nanti bisa diganti data pipeline saat
 * modul campaign/penawaran sudah punya endpoint rekap.
 */
type RouteHref = ReturnType<typeof sponsorProposalsIndex>;

type CompanyStat = {
    label: string;
    value: number | string;
    icon: Component;
    circle: string;
    href?: string;
};

/**
 * Statistik nyata dari `companyDashboard`. Campaign TIDAK wajib: angka 0
 * pada "Campaign aktif" bukan berarti perusahaan tidak bisa menerima
 * proposal.
 */
const companyStats = computed<CompanyStat[]>(() => {
    const stats = props.companyDashboard?.stats;

    return [
        {
            label: 'Campaign aktif',
            value: stats?.campaign_active ?? 0,
            icon: ClipboardList,
            circle: 'bg-[#46B450]',
            href: '/company/campaigns',
        },
        {
            label: 'Proposal Masuk',
            value: stats?.proposals_received ?? 0,
            icon: Send,
            circle: 'bg-[#5B7DBE]',
            href: sponsorProposalsIndex().url,
        },
        {
            label: 'Kolaborasi aktif',
            value: stats?.active_collaborations ?? 0,
            icon: CalendarDays,
            circle: 'bg-[#F7836B]',
        },
        {
            label: 'Penawaran',
            value: stats?.pending_offers ?? 0,
            icon: Handshake,
            circle: 'bg-[#F3B300]',
        },
    ];
});

const dashboardRecommendations = computed(
    () => props.companyDashboard?.recommendations ?? [],
);

type CompanyQuickAction = {
    label: string;
    icon: Component;
    href?: string;
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

const companyPosts = [
    {
        title: 'Keseruan dibalik layar',
        event: 'ITK EXPO 2026',
        meta: 'Event · 3 hari lalu',
        views: 124,
        likes: 12,
        comments: 0,
    },
    {
        title: 'Kolaborasi Pendidikan demi masa depan',
        event: null,
        meta: 'Perusahaan · 5 hari lalu',
        views: 200,
        likes: 150,
        comments: 45,
    },
    {
        title: 'Bank Sendiri dukung kerja sama Teknologi',
        event: null,
        meta: 'Sponsor · 1 hari lalu',
        views: 250,
        likes: 150,
        comments: 80,
    },
    {
        title: 'Musik Kita Gas mencari sponsorship',
        event: null,
        meta: 'Event · 1 hari lalu',
        views: 250,
        likes: 150,
        comments: 80,
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
    <div class="min-h-full pb-10" :class="isEo ? 'bg-sm-navy' : 'bg-white'">
        <!-- ===================== EO ===================== -->
        <div
            v-if="isEo"
            class="mx-auto grid max-w-[1180px] gap-6 px-4 py-6 xl:grid-cols-[1fr_294px] xl:px-0"
        >
            <!-- ---------- Kolom kiri ---------- -->
            <div class="min-w-0 space-y-8">
                <!-- Hero sapaan (gradient merah, radius 24) -->
                <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-sm-red to-[#630E1E] px-8 py-6 text-white shadow-sm">
                    <div class="pointer-events-none absolute -top-20 -right-10 h-64 w-64 rounded-full bg-white/5" aria-hidden="true" />
                    <div class="pointer-events-none absolute -bottom-24 right-40 h-56 w-56 rounded-full bg-white/5" aria-hidden="true" />
                    <h1 class="relative text-3xl font-bold lg:text-4xl">Halo, {{ user?.name ?? 'Pengguna' }} 👋</h1>
                    <p class="relative mt-2 text-lg font-semibold lg:text-xl">Berikut adalah ringkasan progres sponsorship event kamu.</p>
                    <p class="relative mt-3 max-w-md text-sm text-white/90">Terus semangat! Kamu sudah selangkah lebih dekat untuk mewujudkan event yang lebih besar!</p>
                </div>

                <!-- Progress Proposal + Perlu Tindakan -->
                <div class="grid gap-4 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                    <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 class="text-lg font-bold text-sm-navy">Progress Proposal Sponsorship</h2>
                                <p class="mt-0.5 text-xs text-sky-700">Pantau status setiap proposal kamu secara real-time.</p>
                            </div>
                            <Link :href="proposalsIndex()" class="flex shrink-0 items-center gap-1 text-xs font-medium text-sm-navy hover:underline">
                                Lihat semua <ArrowRight class="h-3.5 w-3.5" />
                            </Link>
                        </div>

                        <ol class="mt-6 grid grid-cols-5">
                            <li v-for="(step, i) in pipelineSteps" :key="step.key" class="relative flex flex-col items-center">
                                <span v-if="i < pipelineSteps.length - 1" class="absolute top-5 left-1/2 h-0.5 w-full" :class="step.line" aria-hidden="true" />
                                <span class="relative z-10 flex h-10 w-10 items-center justify-center rounded-full" :class="step.circle">
                                    <component :is="step.icon" class="h-4 w-4" />
                                </span>
                                <span class="mt-2 text-xs font-semibold text-sm-navy">{{ step.label }}</span>
                                <span class="mt-1 flex h-6 min-w-6 items-center justify-center rounded-full px-2 text-xs font-bold" :class="step.badge">{{ step.count }}</span>
                            </li>
                        </ol>

                        <p class="mt-4 text-[11px] text-sm-card/60">
                            Siap dikirim: {{ props.dashboardEo?.pipeline.siap_dikirim ?? 0 }} ·
                            Ditolak: {{ props.dashboardEo?.pipeline.ditolak ?? 0 }}
                        </p>
                    </section>

                    <section class="overflow-hidden rounded-2xl border border-red-100 bg-white shadow-sm">
                        <div class="flex items-center gap-3 bg-red-50 px-4 py-3">
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-sm-red text-white">
                                <CircleAlert class="h-5 w-5" />
                            </span>
                            <div>
                                <h3 class="text-sm font-bold text-sm-red">Perlu Tindakan</h3>
                                <p class="text-xs text-sm-navy/70">{{ props.dashboardEo?.stats.perlu_tindak_lanjut ?? 0 }} proposal perlu ditindaklanjuti</p>
                            </div>
                        </div>
                        <ul v-if="props.dashboardEo?.followUps.length" class="divide-y divide-gray-100">
                            <li v-for="item in props.dashboardEo.followUps" :key="item.id">
                                <Link :href="`${proposalsIndex().url}?selected=${item.proposalId}`" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-red-50/50">
                                    <div class="min-w-0">
                                        <p class="truncate text-xs font-semibold text-sm-navy">{{ item.title }}</p>
                                        <p class="truncate text-[11px] text-sm-red/80">{{ item.description }}</p>
                                    </div>
                                    <ChevronRight class="h-4 w-4 shrink-0 text-sm-card/50" />
                                </Link>
                            </li>
                        </ul>
                        <p v-else class="px-4 py-6 text-center text-xs text-sm-card/60">Tidak ada proposal yang perlu ditindaklanjuti.</p>
                    </section>
                </div>

                <!-- Rekomendasi Sponsor -->
                <section>
                    <h2 class="text-xl font-semibold text-white lg:text-2xl">
                        Rekomendasi Sponsor
                    </h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-3">
                        <article
                            v-for="sponsor in sponsors"
                            :key="sponsor.name"
                            class="flex flex-col rounded-2xl bg-white p-5"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-sm-card text-sm font-semibold text-white"
                                >
                                    {{ sponsor.name.charAt(0) }}
                                </span>
                                <h3 class="font-semibold text-sm-navy">
                                    {{ sponsor.name }}
                                </h3>
                            </div>

                            <span
                                class="mt-4 w-fit rounded-full px-3 py-1 text-xs font-semibold"
                                :class="matchTint(sponsor.match)"
                            >
                                {{ sponsor.match }}% Cocok
                            </span>

                            <p class="mt-4 text-xs font-semibold text-sm-card">
                                Rentang Dukungan:
                            </p>
                            <p class="mt-1 text-sm text-sm-card/80">
                                {{ sponsor.range }}
                            </p>

                            <p class="mt-3 text-xs font-semibold text-sm-card">
                                Keterangan
                            </p>
                            <p
                                class="mt-1 flex-1 text-sm leading-relaxed text-sm-card/80"
                            >
                                {{ sponsor.note }}
                            </p>

                            <button
                                type="button"
                                class="mt-5 w-full rounded-xl bg-sm-red py-2.5 text-sm font-semibold text-white transition-colors hover:bg-sm-red-dark"
                            >
                                Lihat Sekarang
                            </button>
                        </article>
                    </div>
                </section>
            </div>

            <!-- ---------- Kolom kanan ---------- -->
            <!-- ---------- QUICK ACTION EO ---------- -->
            <div class="min-w-0 space-y-6">
                <!-- Aksi Cepat (panel abu #EDEDED, 4 tombol putih 2x2) -->
                <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                    <h3 class="text-base font-bold text-sm-navy">Aksi Cepat</h3>
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <component
                            :is="action.href ? Link : 'a'"
                            v-for="action in quickActions"
                            :key="action.label"
                            :href="action.href ?? '#'"
                            class="flex flex-col items-center justify-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-2 py-4 text-center text-xs font-medium text-sm-navy transition hover:-translate-y-0.5 hover:bg-white hover:shadow-md"
                            :title="action.href ? action.label : `${action.label} — segera hadir`"
                            @click="!action.href && $event.preventDefault()"
                        >
                            <component :is="action.icon" class="h-6 w-6" />
                            {{ action.label }}
                        </component>
                    </div>
                </section>

                <!-- Upgrade ke Premium (gradient merah vertikal + tombol amber) -->
                <section class="rounded-2xl bg-gradient-to-br from-sm-red to-[#5D0F19] p-5 text-white shadow-sm">
                    <div class="flex items-center gap-2">
                        <Crown class="h-6 w-6 shrink-0 text-sm-amber" />
                        <h3 class="text-lg font-bold">Upgrade ke Premium</h3>
                    </div>
                    <p class="mt-2 text-xs leading-relaxed text-white/85">
                        Dapatkan lebih banyak fitur yang memudahkanmu dalam mengelola proposal.
                    </p>
                    <a href="/#harga" class="mt-4 inline-flex items-center gap-2 rounded-md bg-sm-amber px-4 py-2 text-xs font-semibold text-sm-navy transition-opacity hover:opacity-90">
                        Lihat Paket <ArrowRight class="h-3.5 w-3.5" />
                    </a>
                </section>

                <!-- Postingan Terbaru -->
                <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-bold text-sm-navy">
                            Postingan Terbaru
                        </h3>
                    </div>

                    <div
                        v-if="posts.length === 0"
                        class="flex flex-col items-center justify-center py-10 text-center"
                    >
                        <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-gray-100">
                            <Megaphone class="h-7 w-7 text-gray-400" />
                        </div>

                        <h4 class="text-sm font-semibold text-gray-700">
                            Belum Ada Postingan
                        </h4>

                        <p class="mt-1 max-w-xs text-xs text-gray-500">
                            Postingan kegiatan dan informasi organisasi yang kamu buat
                            nantinya akan ditampilkan di sini.
                        </p>
                    </div>

                    <div v-else class="mt-4 space-y-3">
                        <div
                            v-for="(post, index) in posts"
                            :key="index"
                            class="rounded-xl border border-gray-100 p-3"
                        >
                            <!-- Konten postingan -->
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <!-- ===================== PERUSAHAAN ===================== -->
        <div
            v-else
            class="mx-auto grid max-w-[1180px] items-start gap-6 px-4 py-6 xl:grid-cols-[minmax(0,1fr)_294px] xl:px-0"
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
                        class="relative text-[26px] leading-tight font-semibold sm:text-[30px] lg:text-[32px]"
                    >
                        Selamat datang, {{ companyName }}
                        <span class="ml-1 inline-block">👋</span>
                    </h1>
                    <p
                        class="relative mt-3 max-w-[560px] text-sm leading-relaxed text-white/85 lg:text-[15px]"
                    >
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
                        class="flex items-start gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm"
                        :class="card.href ? 'transition-shadow hover:shadow-md' : ''"
                    >
                        <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full text-white" :class="card.circle">
                            <component :is="card.icon" class="h-7 w-7" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-sm-navy">{{ card.label }}</p>
                            <p class="text-[34px] leading-tight font-bold text-sm-navy">{{ card.value }}</p>
                            <p class="mt-1 text-[11px] text-sm-card/60">
                                {{ card.label === 'Campaign aktif' ? 'Campaign yang sedang berjalan' : 'Data terbaru dari aktivitas kamu' }}
                            </p>
                        </div>
                    </component>
                </div>

                <!-- Catatan: campaign bersifat opsional -->
                <div
                    v-if="props.companyDashboard && !props.companyDashboard.has_campaigns"
                    class="flex flex-col gap-3 rounded-xl border border-blue-100 bg-blue-50/70 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
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
                        <h2 class="text-xl font-semibold text-sm-navy lg:text-2xl">
                            Rekomendasi Sponsor
                        </h2>
                        <Link
                            href="/company/recommendations"
                            class="inline-flex shrink-0 items-center gap-1 text-[11px] font-semibold text-sm-red hover:text-sm-red-dark"
                        >
                            Lihat semua
                            <ArrowRight class="h-3.5 w-3.5" />
                        </Link>
                    </div>

                    <div
                        v-if="dashboardRecommendations.length === 0"
                        class="mt-4 rounded-xl border border-dashed border-gray-300 bg-gray-50 p-6 text-center"
                    >
                        <p class="text-sm font-semibold text-sm-navy">
                            Belum ada rekomendasi event
                        </p>
                        <p class="mx-auto mt-1 max-w-md text-[11px] leading-relaxed text-sm-card/65">
                            Lengkapi profil perusahaan atau perbarui rekomendasi
                            agar AI dapat mencocokkan proposal EO.
                        </p>
                        <Link
                            href="/company/recommendations"
                            class="mt-3 inline-flex items-center gap-2 rounded-lg bg-sm-red px-4 py-2 text-[11px] font-semibold text-white transition-colors hover:bg-sm-red-dark"
                        >
                            Buka Rekomendasi
                        </Link>
                    </div>

                    <div v-else class="mt-4 grid gap-4 md:grid-cols-3">
                        <article
                            v-for="event in dashboardRecommendations"
                            :key="event.proposal_id"
                            class="flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"
                        >
                            <!-- Area gambar/cover event -->
                            <div class="relative h-[72px] shrink-0 bg-sm-navy">
                                <button
                                    type="button"
                                    class="absolute top-3 right-3 flex h-8 w-8 items-center justify-center rounded-md text-white/90 transition-colors hover:bg-white/10 hover:text-white"
                                    title="Simpan event"
                                    @click.prevent
                                >
                                    <Bookmark class="h-5 w-5" />
                                </button>
                            </div>

                            <div
                                class="relative flex flex-1 flex-col px-4 pt-4 pb-4 sm:px-5"
                            >
                                <span
                                    class="absolute -top-3.5 right-4 inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[10px] font-semibold text-white shadow-sm"
                                    :class="matchBadgeTint(event.match)"
                                >
                                    <Check class="h-3 w-3" />
                                    Match {{ event.match }}%
                                </span>

                                <h3
                                    class="pr-24 text-[17px] leading-tight font-semibold text-sm-navy"
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
                                        class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-600"
                                    >
                                        Teknologi
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
                                        {{ event.location }}
                                    </p>
                                    <p class="flex items-center gap-1.5">
                                        <Banknote
                                            class="h-3.5 w-3.5 shrink-0"
                                        />
                                        {{ event.budget }}
                                    </p>
                                </div>

                                <p
                                    class="mt-3 flex-1 text-[11px] leading-relaxed text-sm-card/70"
                                >
                                    {{ event.description }}
                                </p>

                                <Link
                                    :href="event.href"
                                    class="mt-4 w-full rounded-md bg-sm-navy py-2.5 text-center text-xs font-semibold text-white transition-colors hover:bg-sm-card"
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
                <section>
                    <h3 class="text-sm font-semibold text-sm-navy">
                        Aksi Cepat
                    </h3>
                    <div
                        class="mt-2 grid grid-cols-2 gap-2.5 rounded-xl bg-[#EFEFEF] p-3"
                    >
                        <component
                            :is="action.href ? Link : 'a'"
                            v-for="action in companyQuickActions"
                            :key="action.label"
                            :href="action.href ?? '#'"
                            class="flex flex-col items-center justify-center gap-2 rounded-lg bg-white px-2 py-3 text-center text-[11px] font-medium text-sm-card shadow-[0_1px_3px_rgba(22,30,47,0.08)] transition-shadow hover:shadow-[0_2px_6px_rgba(22,30,47,0.14)]"
                            :title="
                                action.href
                                    ? action.label
                                    : `${action.label} — segera hadir`
                            "
                            @click="!action.href && $event.preventDefault()"
                        >
                            <component
                                :is="action.icon"
                                class="h-6 w-6 text-sm-navy"
                            />
                            {{ action.label }}
                        </component>
                    </div>
                </section>

                <!-- Upgrade ke Premium -->
                <section
                    class="rounded-xl bg-gradient-to-b from-sm-red to-[#5D0F19] p-5 text-white shadow-sm"
                >
                    <div class="flex items-center gap-2">
                        <Crown class="h-5 w-5 shrink-0 text-sm-amber" />
                        <h3 class="text-lg font-semibold">
                            Upgrade ke Premium
                        </h3>
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
                        <h3 class="font-semibold text-sm-navy">
                            Postingan Terbaru
                        </h3>
                        <a
                            href="#"
                            class="text-right text-[10px] leading-tight font-medium text-sm-navy hover:underline"
                            @click.prevent
                        >
                            Lihat<br />postingan
                        </a>
                    </div>

                    <ul class="mt-2 divide-y divide-gray-100">
                        <li
                            v-for="post in companyPosts"
                            :key="post.title"
                            class="flex gap-3 py-3 first:pt-2 last:pb-0"
                        >
                            <div
                                class="h-[58px] w-[58px] shrink-0 rounded-md bg-gradient-to-br from-sm-card to-sm-navy"
                                aria-hidden="true"
                            />
                            <div class="min-w-0 flex-1">
                                <p
                                    class="line-clamp-2 text-[11px] leading-snug font-semibold text-sm-navy"
                                >
                                    {{ post.title }}
                                </p>
                                <p
                                    v-if="post.event"
                                    class="mt-0.5 text-[10px] font-medium text-sm-navy"
                                >
                                    {{ post.event }}
                                </p>
                                <p class="mt-0.5 text-[10px] text-sm-card/50">
                                    {{ post.meta }}
                                </p>
                                <div
                                    class="mt-1 flex items-center gap-3 text-[10px] text-sm-card/70"
                                >
                                    <span class="flex items-center gap-1">
                                        <Eye class="h-3 w-3" />{{ post.views }}
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <Heart class="h-3 w-3" />{{
                                            post.likes
                                        }}
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <MessageCircle class="h-3 w-3" />{{
                                            post.comments
                                        }}
                                    </span>
                                </div>
                            </div>
                            <ArrowRight
                                class="mt-1 h-4 w-4 shrink-0 text-sm-card/50"
                            />
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </div>
</template>
