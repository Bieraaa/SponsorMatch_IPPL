<script setup lang="ts">
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { useReveal } from '@/composables/useReveal';

/**
 * Seksi FAQ (#faq) — disesuaikan dengan Figma "landing page desktop".
 *
 * Struktur desain (hasil ukur piksel Figma, kanvas 1440px):
 *   - Header rata tengah: pill merah "FAQ" (155x44) → judul 2 warna 32px
 *     "Pertanyaan Yang" putih + "Sering Diajukan" merah → subjudul 16px.
 *   - Grid 2 kolom (gap 24px, lebar total 1350px):
 *       • Kolom kiri  (607px): kartu kontak #242F49 — ilustrasi, teks, tombol merah
 *         "Hubungi Kami" (255x54, radius 8px), SEMUA rata kiri kecuali tombol (center).
 *       • Kolom kanan (717px): 6 kartu pertanyaan #242F49, tinggi 83px, jarak 14px,
 *         radius 12px — tiap kartu diawali lingkaran putih Ø50 berisi tanda "?".
 *
 * PERTANYAAN diambil VERBATIM dari Figma (termasuk ejaan & tanda baca aslinya).
 * Jawaban di bawah adalah PLACEHOLDER dari SRS — GANTI dengan teks dari frame
 * Figma "isi konten faq" begitu export-nya tersedia.
 */
const faqs = [
    {
        q: 'Apa itu SponsorMatch?',
        a: 'SponsorMatch adalah platform berbasis web yang membantu event organizer menemukan dan mengelola sponsor. Sistem memanfaatkan pencocokan berbasis AI untuk merekomendasikan perusahaan yang relevan dengan kebutuhan acara Anda.',
    },
    {
        // Ejaan verbatim dari Figma (tanpa tanda tanya, sesuai desain)
        q: 'Siapa aja yang dapat menggunakan SponsorMatch',
        a: 'SponsorMatch digunakan oleh dua peran utama: event organizer (EO) yang membuat dan mengelola proposal event, serta perusahaan/sponsor yang memposting kebutuhan campaign dan menerima proposal. Keduanya dapat mendaftar dan langsung saling terhubung.',
    },
    {
        q: 'Bagaimana cara kerja AI dalam SponsorMatch?',
        a: 'AI dipakai untuk dua hal: mengekstrak data penting dari proposal yang Anda unggah agar tidak perlu input manual, dan mencocokan karakteristik event dengan profil perusahaan sehingga rekomendasi yang muncul paling relevan dengan kebutuhan Anda.',
    },
    {
        q: 'Apakah SponsorMatch gratis?',
        a: 'Mendaftar dan menggunakan fitur dasar SponsorMatch gratis. Fitur lengkap — ekstraksi AI tanpa batas, counter-offer, laporan statistik lengkap, dan lainnya — tersedia pada paket Premium seharga Rp.199.000 /Bulan.',
    },
    {
        q: 'Apakah data proposal dan campaign aman?',
        a: 'Ya. Data proposal dan campaign Anda disimpan pada basis data server kami dan hanya dapat diakses oleh akun Anda serta pihak yang Anda tuju saat mengirim proposal. Data Anda tidak dibagikan kepada pihak ketiga.',
    },
    {
        q: 'Apa saja fitur SponsorMatch',
        a: 'Fitur utama SponsorMatch antara lain: pencarian & filter perusahaan/EO, pencocokan berbasis AI, kirim proposal, penawaran & counter-offer, tracking status proposal, laporan statistik, serta notifikasi match terbaru.',
    },
];

// Animasi reveal: aktif saat masuk viewport, reset saat keluar (animasi berulang).
const { el: sectionEl, revealed } = useReveal(0.25);
</script>

<template>
    <section
        id="faq"
        ref="sectionEl"
        class="flex min-h-svh snap-start scroll-mt-[68px] items-center bg-sm-navy font-jakarta text-white"
    >
        <!-- px-11 (44px) di layar besar = margin Figma x44–x1394 (konten 1350px) -->
        <div class="mx-auto w-full max-w-[1440px] px-5 py-14 lg:px-11 lg:py-16">
            <!--
                Header seksi (rata tengah):
                pill merah "FAQ" → judul 2 warna → subjudul. Ukuran font dari hasil ukur
                piksel Figma: pill text-3xl, judul ~32px, subjudul 16px.
            -->
            <div
                class="mx-auto max-w-3xl text-center transition-all duration-700 ease-out"
                :class="
                    revealed
                        ? 'translate-y-0 opacity-100'
                        : 'translate-y-8 opacity-0'
                "
            >
                <span
                    class="inline-flex rounded-full bg-sm-red px-11 py-1.5 font-poppins text-3xl leading-none font-bold"
                >
                    FAQ
                </span>
                <h2
                    class="mt-4 font-poppins text-3xl leading-tight font-bold lg:text-[32px]"
                >
                    Pertanyaan Yang
                    <span class="text-sm-red">Sering Diajukan</span>
                </h2>
                <p class="mt-2 text-base text-white/70">
                    Temukan jawaban yang paling sering ditanyakan
                </p>
            </div>

            <!--
                Grid 2 kolom: kartu kontak (kiri, lebih lebar) + daftar pertanyaan (kanan).
                fr mendekati rasio Figma 607 : 717.
            -->
            <div
                class="mt-12 grid items-start gap-6 lg:grid-cols-[607fr_717fr]"
            >
                <!--
                    Kartu kontak (kiri) — ilustrasi hasil crop langsung dari PNG Figma
                    (gambar_faq_kontak.png, latar senada #242F49 jadi menyatu).
                    Teks rata kiri; tombol "Hubungi Kami" merah di-center.
                -->
                <div
                    class="rounded-xl bg-sm-card p-8 transition-all delay-150 duration-700 ease-out lg:p-10"
                    :class="
                        revealed
                            ? 'translate-y-0 opacity-100'
                            : 'translate-y-8 opacity-0'
                    "
                >
                    <img
                        src="/assets/gambar_faq_kontak.png"
                        alt="Ilustrasi pertanyaan seputar SponsorMatch"
                        class="mx-auto mb-6 block w-full max-w-[500px]"
                    />
                    <!-- Split warna verbatim: "Masih ada" putih, sisanya merah (termasuk typo desain) -->
                    <h3
                        class="font-poppins text-3xl leading-tight font-bold lg:text-4xl"
                    >
                        Masih ada
                        <span class="text-sm-red">pertanyaaan lagi ?</span>
                    </h3>
                    <p class="mt-5 text-lg leading-8 text-white/80">
                        Jika anda tidak menemukan jawaban yang anda cari, jangan
                        ragu untuk menghubungi tim kami.
                    </p>
                    <p class="mt-3 text-lg leading-8 text-white/80">
                        Kami siap membantu anda.
                    </p>
                    <a
                        href="mailto:hello@sponsormatch.id"
                        class="mx-auto mt-10 flex h-[54px] w-full max-w-[255px] items-center justify-center rounded-lg bg-sm-red font-poppins text-base font-semibold text-white transition-colors hover:bg-sm-red-dark"
                    >
                        Hubungi Kami
                    </a>
                </div>

                <!--
                    Kolom kanan: 6 kartu pertanyaan. Tinggi alami kartu = 83px
                    (py-4 16px + lingkaran 50px), jarak antar kartu 14px (space-y-3.5),
                    radius 12px (rounded-xl) — semua sesuai ukuran Figma.
                    Seluruh baris adalah pemicu accordion (jawaban muncul saat diklik).
                -->
                <div class="space-y-3.5">
                    <Collapsible
                        v-for="(faq, index) in faqs"
                        :key="faq.q"
                        class="overflow-hidden rounded-xl bg-sm-card transition-all duration-500 ease-out"
                        :class="
                            revealed
                                ? 'translate-y-0 opacity-100'
                                : 'translate-y-6 opacity-0'
                        "
                        :style="{ transitionDelay: `${200 + index * 60}ms` }"
                    >
                        <CollapsibleTrigger
                            class="flex w-full items-center gap-4 px-4 py-4 text-left transition-colors hover:text-sm-amber"
                        >
                            <!-- Lingkaran putih Ø50 berisi tanda "?" (warna kartu) sesuai Figma -->
                            <span
                                class="grid size-[50px] shrink-0 place-items-center rounded-full bg-white font-poppins text-2xl font-bold text-sm-card"
                                aria-hidden="true"
                            >
                                ?
                            </span>
                            <span class="text-lg font-medium lg:text-xl">{{
                                faq.q
                            }}</span>
                        </CollapsibleTrigger>
                        <!-- Jawaban: indentasi sejajar dengan teks pertanyaan (4 + 50 + 16 = 70px) -->
                        <CollapsibleContent
                            class="pr-4 pb-5 pl-[70px] text-base leading-7 text-white/70"
                        >
                            {{ faq.a }}
                        </CollapsibleContent>
                    </Collapsible>
                </div>
            </div>
        </div>
    </section>
</template>
