<script setup lang="ts">
import { useReveal } from '@/composables/useReveal';
import { Link } from '@inertiajs/vue3';

/**
 * Seksi "Langganan" (#langganan) — disesuaikan dengan Figma "landing page desktop".
 *
 * Konten diambil VERBATIM dari desain Figma:
 *   - H1    : "Tingkatkan Peluang Kerja Sama Anda" ("Kerja Sama Anda" merah #B51A2B)
 *   - Lead  : "Pilih premium sesuai dengan peran anda dan dapatkan akses fitur
 *              lengkap untuk hasil yang lebih maksimal"
 *   - Label : "Coba Berlangganan Selama 1 Bulan:"
 *   - Kartu : Premium EO (gradien abu) & Premium Sponsor (gradien merah),
 *             keduanya "Mulai dari Rp.199.000 /Bulan".
 *
 * Ukuran/posisi hasil ukur piksel Figma (1440px):
 *   - Kartu 431 x 550, radius 24px (rounded-3xl), border 1px #F59E0B (border-sm-amber)
 *   - Jarak antar kartu 120px, bayangan 0 4px 12px rgba(0,0,0,.3)
 *   - Ikon manfaat: lingkaran peach #FFA586 Ø36px dengan centang "knockout" #161E2F
 *   - Tombol "Pilih Paket" 152 x 61, radius 14px, teks putih 16px semibold
 *   - Gradien kartu kiri #384358 → #161E2F, kartu kanan #541A2E (sm-red-dark) → #300409
 *   - Divider 2px di dalam kartu: #161E2F (kiri) / #B51A2B (kanan)
 */
const plans = [
    {
        name: 'Premium EO',
        audience: 'Untuk Event Organizer',
        // Gradien vertikal isi kartu (dari atas ke bawah)
        gradient: 'linear-gradient(180deg, #384358 0%, #161E2F 100%)',
        // Divider (garis 2px) di dalam kartu
        dividerClass: 'bg-sm-navy',
        // Tombol "Pilih Paket"
        buttonClass: 'bg-[#384358] hover:bg-[#414C63]',
        benefits: [
            'Laporan Statistik dan Event',
            'Notifikasi Match Terbaru',
            'Follow Up Otomatis',
            'Counter Penawaran',
            'Dukungan Prioritas',
        ],
    },
    {
        name: 'Premium Sponsor',
        audience: 'Untuk Perusahaan',
        gradient: 'linear-gradient(180deg, #541A2E 0%, #300409 100%)',
        dividerClass: 'bg-sm-red',
        buttonClass: 'bg-sm-red hover:bg-[#9C1625]',
        benefits: [
            'Laporan Statistik dan Campaign',
            'Notifikasi Match Terbaru',
            'Kelola Multiple Campaign',
            'Laporan dan Analitik Kolaborasi',
            'Dukungan Prioritas',
        ],
    },
];

// Animasi reveal: aktif saat masuk viewport, reset saat keluar (animasi berulang).
const { el: sectionEl, revealed } = useReveal(0.25);
</script>

<template>
    <section
        id="langganan"
        ref="sectionEl"
        class="flex min-h-svh snap-start scroll-mt-[68px] items-center bg-sm-navy font-jakarta text-white"
    >
        <div class="mx-auto w-full max-w-[1000px] px-5 py-16 lg:py-20">
            <!--
                Header seksi — semuanya rata tengah persis seperti Figma:
                H1 48px (dua kata terakhir merah) → lead 2 baris → label kecil.
            -->
            <div
                class="mx-auto max-w-[940px] text-center transition-all duration-700 ease-out"
                :class="
                    revealed
                        ? 'translate-y-0 opacity-100'
                        : 'translate-y-8 opacity-0'
                "
            >
                <h2
                    class="font-poppins text-4xl leading-tight font-bold lg:text-5xl"
                >
                    Tingkatkan Peluang
                    <span class="text-sm-red">Kerja Sama Anda</span>
                </h2>
                <!-- max-w-[670px] membuat teks patah menjadi 2 baris seperti desain -->
                <p
                    class="mx-auto mt-5 max-w-[670px] text-base leading-[35px] text-white/90"
                >
                    Pilih premium sesuai dengan peran anda dan dapatkan akses
                    fitur lengkap untuk hasil yang lebih maksimal
                </p>
                <p class="mt-9 text-base font-semibold">
                    Coba Berlangganan Selama 1 Bulan:
                </p>
            </div>

            <!--
                Baris kartu — desktop: 2 kartu berdampingan (gap 120px),
                mobile: menumpuk. Setiap kartu punya gradien latar sendiri.
            -->
            <!-- lg:flex-row (bukan md): di layar 768–1023px dua kartu tak muat (baris
                 harga tak bisa menyusut) sehingga kartu menumpuk dulu, berdampingan di ≥1024px -->
            <div
                class="mt-10 flex flex-col items-center gap-8 lg:flex-row lg:justify-center lg:gap-[120px]"
            >
                <div
                    v-for="(plan, index) in plans"
                    :key="plan.name"
                    class="w-full max-w-[431px] rounded-3xl border border-sm-amber shadow-[0_4px_12px_rgba(0,0,0,0.3)] transition-all duration-500 ease-out"
                    :class="
                        revealed
                            ? 'translate-y-0 opacity-100'
                            : 'translate-y-6 opacity-0'
                    "
                    :style="{
                        background: plan.gradient,
                        transitionDelay: `${150 + index * 120}ms`,
                    }"
                >
                    <!-- Nama paket + target pengguna -->
                    <div class="px-6 pt-9">
                        <h3 class="font-poppins text-2xl font-bold">
                            {{ plan.name }}
                        </h3>
                        <p class="mt-2 text-base text-white/90">
                            {{ plan.audience }}
                        </p>
                    </div>

                    <!-- Divider 1 (2px, inset ~20px seperti di Figma) -->
                    <div
                        class="mx-5 mt-[22px] h-0.5"
                        :class="plan.dividerClass"
                    ></div>

                    <!--
                        Daftar manfaat: lingkaran peach Ø36 berisi centang
                        (warna centang #161E2F = "knockout" sesuai desain), teks 18px.
                        Jarak antar baris 18px sehingga jarak antar-ikon = 54px (Figma).
                    -->
                    <ul class="mt-[27px] space-y-[18px] pr-5 pl-[19px]">
                        <li
                            v-for="benefit in plan.benefits"
                            :key="benefit"
                            class="flex items-center gap-[13px]"
                        >
                            <span
                                class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-sm-peach"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    class="h-4 w-4"
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
                            <span class="text-lg font-medium">{{
                                benefit
                            }}</span>
                        </li>
                    </ul>

                    <!-- Divider 2 sebelum blok harga -->
                    <div
                        class="mx-5 mt-[38px] h-0.5"
                        :class="plan.dividerClass"
                    ></div>

                    <!--
                        Blok harga + tombol. "Rp.199.000" 24px bold, "/Bulan" 16px regular
                        menempel di belakangnya (baseline sejajar) seperti di Figma.
                    -->
                    <div
                        class="flex items-center justify-between gap-4 px-6 pt-7 pb-7"
                    >
                        <div>
                            <p class="text-sm text-white/90">Mulai dari</p>
                            <p
                                class="mt-1 font-poppins text-2xl leading-none font-bold"
                            >
                                Rp.199.000<span
                                    class="ml-0.5 text-base font-normal"
                                    >/Bulan</span
                                >
                            </p>
                        </div>
                        <Link
                            href="/register"
                            class="inline-flex h-[52px] w-[152px] shrink-0 items-center justify-center rounded-[14px] font-poppins text-base font-semibold text-white transition-colors sm:h-[61px]"
                            :class="plan.buttonClass"
                        >
                            Pilih Paket
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
