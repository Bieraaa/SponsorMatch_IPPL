<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { login } from '@/routes';

/**
 * Halaman "Role Pick" — /register (BLOK A).
 *
 * Langkah pertama pendaftaran: pengguna MEMILIH PERAN. Setelah memilih,
 * pengguna diarahkan ke URL form yang berbeda per peran:
 *   - Event Organizer → /register/eo
 *   - Perusahaan      → /register/perusahaan
 *
 * KENAPA DIPISAH PER URL (bukan satu form dengan field dinamis):
 *   1. Field yang diminta berbeda (EO: nama event organizer; Perusahaan:
 *      nama perusahaan, NPWP, NIB). URL berbeda = halaman & validasi jelas.
 *   2. Tombol "Kembali" browser dan tautan yang dibagikan bekerja wajar.
 *   3. Setiap form bisa punya validasi sendiri tanpa logika bercabang.
 *
 * Halaman ini dirender TANPA AuthLayout (lihat app.ts → 'auth/register*'
 * mengembalikan null) karena desainnya full-screen navy #161E2F.
 *
 * Ukuran/warna diukur dari export Figma "Role pick.png" (1440×1024):
 * kartu 500×550, border 5px, radius 24, tombol 445×58 r14.
 *
 * MODE GOOGLE:
 *   Kalau pendaftaran dimulai dari "Lanjutkan dengan Google" (email belum
 *   terdaftar), backend menaruh profil Google di session lalu mengarahkan
 *   ke halaman ini. Kita tampilkan banner kecil supaya user tahu akunnya
 *   sudah terhubung dan tinggal memilih peran.
 */
defineProps<{
    google?: { email: string; name: string } | null;
}>();

const roles = [
    {
        href: '/register/eo',
        title: 'Event Organizer',
        subtitle: 'Untuk Komunitas & Penyelenggara Event',
        desc: 'Temukan dan kelola sponsor yang tepat untuk acara anda dengan bantuan AI. Dapatkan rekomendasi yang sesuai dengan kebutuhan acara anda.',
        features: [
            'Unggah proposal dan ekstraksi dengan AI',
            'Dapatkan rekomendasi yang sesuai',
            'Kelola dan tracking proposal',
            'Akses laporan dan statistik event',
        ],
        icon: '/assets/ikon_role_eo.png',
    },
    {
        href: '/register/perusahaan',
        title: 'Perusahaan',
        subtitle: 'Untuk Brand & Corporate',
        desc: 'Temukan event yang sesuai dengan campaign anda dan bangun kolaborasi jangka panjang dengan komunitas dan penyelenggara event',
        features: [
            'Akses proposal event yang relevan',
            'Kelola Penawaran & Kerja Sama',
            'Pantau Pipeline dan Riwayat Kolaborasi',
            'Kelola Campaign',
        ],
        icon: '/assets/ikon_role_perusahaan.png',
    },
];
</script>

<template>
    <Head title="Daftar" />

    <div class="min-h-screen bg-sm-navy text-white">
        <div class="mx-auto w-full max-w-[1102px] px-4 pb-14">
            <div class="pt-[124px]">
                <!--
                    Heading tengah 40px bold. "Sponsor" merah di dalam kata
                    SponsorMatch (dua warna dalam satu kata = pola brand).
                -->
                <h1
                    class="text-center text-[30px] leading-tight font-bold sm:text-[40px]"
                >
                    Mulai Perjalanan di <span class="text-sm-red">Sponsor</span
                    ><span>Match</span>
                </h1>
                <!-- Sub-heading 2 baris; max-w memaksa wrap sebelum "SponsorMatch" seperti desain -->
                <p
                    class="mx-auto mt-[14px] max-w-[625px] text-center text-base leading-[30px]"
                >
                    Pilih peran sesuai dengan kebutuhan anda untuk memperoleh
                    pengalaman terbaik di SponsorMatch
                </p>

                <!-- Banner mode Google: akun sudah terhubung, tinggal pilih peran -->
                <div
                    v-if="google"
                    class="mx-auto mt-[22px] max-w-[625px] rounded-[14px] border border-[#384358] bg-white/5 px-5 py-3 text-center text-sm leading-relaxed text-white/85"
                >
                    Melanjutkan pendaftaran dengan akun Google
                    <span class="font-semibold text-white">{{
                        google.email
                    }}</span
                    >. Pilih peran di bawah ini untuk melanjutkan.
                </div>
            </div>

            <!-- Dua kartu peran — 500×550, border 5px, radius 24 (diukur dari Figma) -->
            <div
                class="mt-[64px] flex flex-col items-center gap-8 lg:flex-row lg:justify-center lg:gap-[70px]"
            >
                <div
                    v-for="r in roles"
                    :key="r.href"
                    class="flex h-[550px] w-full max-w-[500px] shrink-0 flex-col rounded-[24px] border-[5px] border-[#384358] bg-[linear-gradient(180deg,#384358_0%,#232D46_8%,#192134_100%)] transition-colors duration-200 focus-within:border-sm-red hover:border-sm-red"
                >
                    <div
                        class="flex h-full flex-col px-[22px] pt-[20px] pb-[34px]"
                    >
                        <!-- Baris judul: ikon peran (crop dari Figma, PNG transparan) + judul/subjudul -->
                        <div class="flex items-center gap-[13px]">
                            <img
                                :src="r.icon"
                                alt=""
                                class="h-16 w-16 shrink-0 object-contain"
                            />
                            <div class="min-w-0">
                                <h3 class="text-[28px] leading-tight font-bold">
                                    {{ r.title }}
                                </h3>
                                <p class="mt-1.5 text-base">{{ r.subtitle }}</p>
                            </div>
                        </div>

                        <!-- Deskripsi peran 16px leading 30px -->
                        <p class="mt-[10px] text-base leading-[30px]">
                            {{ r.desc }}
                        </p>

                        <!--
                            Daftar fitur: lingkaran peach Ø40 + centang knockout
                            #161E2F — pola identik dengan manfaat di Pricing.vue.
                        -->
                        <ul class="mt-[10px] space-y-2">
                            <li
                                v-for="f in r.features"
                                :key="f"
                                class="flex items-center gap-[13px]"
                            >
                                <span
                                    class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-sm-peach"
                                >
                                    <svg
                                        viewBox="0 0 24 24"
                                        class="h-[18px] w-[18px]"
                                        fill="none"
                                        stroke="#161E2F"
                                        stroke-width="3"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        aria-hidden="true"
                                    >
                                        <path d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                                <span class="text-base">{{ f }}</span>
                            </li>
                        </ul>

                        <!-- Tombol full-width 58px r14 merah → buka form peran terkait -->
                        <Link
                            :href="r.href"
                            class="mt-auto grid h-[58px] w-full cursor-pointer place-items-center rounded-[14px] bg-sm-red font-semibold text-white transition-colors hover:bg-[#C41F31] focus-visible:ring-2 focus-visible:ring-sm-peach focus-visible:outline-none"
                        >
                            Daftar Sekarang
                        </Link>
                    </div>
                </div>
            </div>

            <!-- "Kembali" ke landing page (link teks + panah kiri 18px, kiri-sejajar kartu) -->
            <Link
                href="/"
                class="mt-[52px] inline-flex items-center gap-[18px] text-base text-white transition-colors hover:text-sm-peach"
            >
                <ArrowLeft class="h-[18px] w-[18px]" aria-hidden="true" />
                Kembali
            </Link>
        </div>
    </div>
</template>
