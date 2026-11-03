<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref, type Component } from 'vue';
import {
    ArrowRight,
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
} from '@lucide/vue';
import { index as sponsorProposalsIndex } from '@/routes/sponsor/proposals';
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
        terkirim: {
            value: number;
            diff: number;
        };
        disetujui: {
            value: number;
            diff: number;
        };
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
    };
    followUps: {
        id: number;
        proposal: string | null;
        company: string | null;
        stage: string;
        days: number | null;
    }[];
};

const props = defineProps<{
    dashboardEo?: DashboardEoData;
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

const stats = computed(() => [
    {
        label: 'Proposal diterima',
        value: props.dashboardEo?.stats.disetujui?.value ?? 0,
        icon: ThumbsUp,
        tint: 'text-green-600',
    },
    {
        label: 'Proposal terkirim',
        value: props.dashboardEo?.stats.terkirim?.value ?? 0,
        icon: Send,
        tint: 'text-sm-card',
    },
    {
        label: 'Perlu tindak lanjut',
        value: props.dashboardEo?.stats.perlu_tindak_lanjut ?? 0,
        icon: ClipboardList,
        tint: 'text-sm-amber',
    },
    {
        label: 'Sedang ditinjau',
        value: props.dashboardEo?.pipeline.ditinjau ?? 0,
        icon: FileText,
        tint: 'text-sm-card',
    },
]);

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
    { label: 'Buat laporan', icon: FileText, href: null },
    { label: 'Upload Proposal Event', icon: Upload, href: null },
    { label: 'Buat Postingan', icon: Megaphone, href: null },
    { label: 'Kelola Penawaran', icon: Handshake, href: null },
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
    value: number;
    icon: Component;
    circle: string;
    href?: RouteHref;
};

const companyStats: CompanyStat[] = [
    {
        label: 'Campaign aktif',
        value: 5,
        icon: ClipboardList,
        circle: 'bg-[#46B450]',
    },
    {
        label: 'Event disponsori',
        value: 2,
        icon: CalendarDays,
        circle: 'bg-[#F7836B]',
    },
    {
        label: 'Proposal Masuk',
        value: 12,
        icon: Send,
        circle: 'bg-[#5B7DBE]',
        href: sponsorProposalsIndex(),
    },
    {
        label: 'Penawaran',
        value: 1,
        icon: Handshake,
        circle: 'bg-[#F3B300]',
    },
];

const companyRecommendations = [
    {
        title: 'Tech Summit',
        match: 92,
        category: 'Seminar',
        date: '12 - 14 November',
        location: 'Jakarta Hall',
        budget: 'Rp 50.000 - Rp 100.000',
        description:
            'Event Teknologi tahunan yang menghadirkan pembicara global dan inovasi terbaru.',
    },
    {
        title: 'ITK FEST',
        match: 55,
        category: 'Festival',
        date: '31 Oktober',
        location: 'Institut Teknologi Kalimantan',
        budget: 'Rp 50.000 - Rp 100.000',
        description:
            'Event Institut Teknologi Kalimantan yang diadakan untuk memperingati Dies Natalis pada setiap tahun.',
    },
    {
        title: 'Medical Check',
        match: 25,
        category: 'Kesehatan',
        date: '20 Desember',
        location: 'Samarinda Hall',
        budget: 'Rp 250.000 - Rp 250.000',
        description:
            'Kegiatan medical check-up yang terbuka untuk masyarakat sekitar kota Samarinda.',
    },
];

type CompanyQuickAction = {
    label: string;
    icon: Component;
    href?: RouteHref;
};

const companyQuickActions: CompanyQuickAction[] = [
    { label: 'Buat laporan', icon: FileText },
    { label: 'Upload Campaign', icon: Upload },
    { label: 'Buat Postingan', icon: Megaphone },
    {
        label: 'Kelola Penawaran',
        icon: Handshake,
        href: sponsorProposalsIndex(),
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
                <div
                    class="rounded-3xl bg-gradient-to-r from-sm-red to-[#630E1E] px-8 py-9 text-white"
                >
                    <h1 class="text-3xl font-semibold lg:text-4xl">
                        Halo, {{ firstName }} 👋
                    </h1>
                    <p class="mt-3 max-w-xl text-base text-white/85 lg:text-lg">
                        Menemukan sponsor yang tepat. SponsorMatch siap membantu
                        menemukan mitra terbaik kamu.
                    </p>
                </div>

                <!-- 4 statistik proposal (kartu putih) -->
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <div
                        v-for="stat in stats"
                        :key="stat.label"
                        class="rounded-2xl bg-white p-5"
                    >
                        <p class="text-sm text-sm-card/70">{{ stat.label }}</p>
                        <div class="mt-2 flex items-center gap-3">
                            <span class="text-4xl font-bold text-sm-navy">{{
                                stat.value
                            }}</span>
                            <component
                                :is="stat.icon"
                                class="h-6 w-6"
                                :class="stat.tint"
                            />
                        </div>

                        
                    </div>
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
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <component
                        :is="stat.href ? Link : 'div'"
                        v-for="stat in companyStats"
                        :key="stat.label"
                        :href="stat.href"
                        class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:gap-4 sm:p-5"
                        :class="
                            stat.href
                                ? 'transition hover:-translate-y-0.5 hover:shadow-md'
                                : ''
                        "
                    >
                        <span
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-white sm:h-12 sm:w-12"
                            :class="stat.circle"
                        >
                            <component :is="stat.icon" class="h-6 w-6" />
                        </span>
                        <div class="min-w-0">
                            <p
                                class="text-[11px] font-medium text-sm-card/70 sm:text-xs"
                            >
                                {{ stat.label }}
                            </p>
                            <p
                                class="text-[28px] leading-tight font-bold text-sm-navy"
                            >
                                {{ stat.value }}
                            </p>
                        </div>
                    </component>
                </div>

                <!-- Rekomendasi event untuk perusahaan -->
                <section>
                    <h2 class="text-xl font-semibold text-sm-navy lg:text-2xl">
                        Rekomendasi Sponsor
                    </h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-3">
                        <article
                            v-for="event in companyRecommendations"
                            :key="event.title"
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

                                <button
                                    type="button"
                                    class="mt-4 w-full rounded-md bg-sm-navy py-2.5 text-xs font-semibold text-white transition-colors hover:bg-sm-card"
                                    @click.prevent
                                >
                                    Lihat Sekarang
                                </button>
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
