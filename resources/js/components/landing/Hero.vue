<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

/**
 * ============================================================================
 * HERO — seksi pertama (#beranda), layar penuh pertama setelah navbar
 * ============================================================================
 * Struktur:
 *   [section]        -> min-h-svh + snap-start (1 layar penuh, scroll-snap)
 *     [kiri]         -> judul, subjudul, paragraf, tombol CTA
 *     [kanan]        -> glow peach + ilustrasi +3 chip mengambang
 */

// Data3 chip yang melayang di atas ilustrasi.
// Dipetakan lewat v-for, jadi posisi/isi chip diatur cukup dari array ini.
// `class` = posisi absolut terhadap kotak ilustrasi (persen dari lebar/tinggi gambar).
// Posisi sesuai desain target: AI Matching di KIRI, Event di TENGAH (menempel
// atas), Perusahaan di KANAN bawah — tidak ada yang keluar tepi kolom.
const chips = [
    {
        title: 'AI Matching', // judul chip (baris atas)
        note: 'Efisien · Relevan', // keterangan chip (baris bawah)
        icon: 'vector_ai.svg', // icon di lingkaran peach, file dari public/assets/
        class: 'left-[14%] top-[9%]', // posisi: kiri-atas ilustrasi
    },
    {
        title: 'Event',
        note: 'Festival · Seminar · Expo',
        icon: 'vector_event.svg',
        class: 'left-[39%] top-0', // posisi: tengah, menempel tepi atas ilustrasi
    },
    {
        title: 'Perusahaan',
        note: 'Brand · Partnership',
        icon: 'vector_perusahaan.svg',
        class: 'left-[75%] top-[13%]', // posisi: kanan-bawah (75% + lebar chip ≈97%, masih di dalam)
    },
];
</script>

<template>
    <!--
        <section> =1 seksi scroll-snap:
        - id="beranda"   -> target link navbar (#beranda)
        - relative/isolate → membentuk konteks stacking sendiri (isolate),
        supaya z-index anak (z-0, z-10, z-20) tidak bocor ke elemen luar
        - min-h-svh      -> minimal setinggi layar viewport (small viewport height)
        - snap-start     -> scroll-snap: seksi ini mengunci di awal layar
        - overflow-hidden -> glow yang melebar keluar kotak tidak membuat scroll horizontal
    -->
    <section
        id="beranda"
        class="relative isolate flex min-h-svh snap-start items-center overflow-hidden"
    >
        <!--
            Kontainer isi2 kolom:
            - grid-cols-1 (mobile: bertumpuk) → lg:grid-cols-[0.85fr_1.1fr]
              = di layar lebar: teks45%, visual55% (kolom kanan lebih besar
              karena ilustrasi + chip butuh ruang)
            - max-w-[1800px] → batas lebar desain hero (lebih lebar dari WhyUs
              yang1440px, agar ilustrasi bisa besar)
            - pt-24 → jarak atas mengompensasi navbar sticky (68px) + napas
        -->
        <div
            class="mx-auto grid w-full max-w-[1800px] grid-cols-1 items-center gap-12 px-5 pt-24 pb-12 sm:px-8 md:gap-14 lg:grid-cols-[0.85fr_1.15fr] lg:gap-8 lg:px-10 lg:py-20 xl:gap-12 xl:px-14 2xl:px-16"
        >
            <!--
                KOLOM KIRI — Konten teks.
                z-20 supaya teks selalu di atas glow/gambar kalau tumpang tindih.
                items-start → semua anak rata kiri.
            -->
            <div class="relative z-20 flex flex-col items-start lg:py-8">
                <!--
                    Judul utama: "Sponsor" merah + "Match" putih (span terpisah).
                    Ukuran naik per breakpoint: text-5xl → sm:6xl → md:7xl →
                    lg clamp (3.5rem–5.5rem, ikut lebar layar) → xl:88px.
                    clamp() dipakai agar teks tidak pecah di rentang layar tengah.
                -->
                <h1
                    class="font-poppins text-5xl leading-[1.05] font-bold tracking-tight sm:text-6xl md:text-7xl lg:text-[clamp(3.5rem,5.2vw,5.5rem)] xl:text-[88px]"
                >
                    <span class="text-sm-red">Sponsor</span>Match
                </h1>

                <!--
                    Subjudul. max-w-xl membatasi lebar baris agar tidak terlalu
                    panjang. "Sponsor" di sini amber (berbeda dari judul yang merah).
                -->
                <p
                    class="mt-6 max-w-xl font-poppins text-2xl leading-snug font-semibold sm:text-3xl lg:mt-8 lg:text-[clamp(1.75rem,2.4vw,2rem)]"
                >
                    Sudah Punya Acara, Tapi Masih Kesulitan Cari
                    <span class="text-sm-amber">Sponsor</span>?
                </p>

                <!--
                    Paragraf penjelasan singkat. max-w-lg lebih sempit dari
                    subjudul supaya barisnya pendek dan enak dibaca.
                    text-white/80 = putih80% opasitas (tidak menyilaukan).
                -->
                <p
                    class="mt-5 max-w-lg font-poppins text-sm leading-7 text-white/80 sm:text-base lg:mt-6 lg:text-lg lg:leading-8"
                >
                    SponsorMatch membantu Anda menemukan perusahaan yang relevan
                    melalui pencocokan berbasis AI, sehingga peluang kerja sama
                    lebih tepat sasaran.
                </p>

                <!--
                    Tombol CTA (Call to Action) → halaman daftar akun.
                    <Link> dari Inertia = navigasi tanpa reload penuh (SPA).
                    Efek hover: tombol naik (-translate-y-1) + warna lebih gelap,
                    panah geser kanan (group-hover di dalam Link).
                    shadow-sm-red/20 = bayangan merah transparan20%.
                -->
                <Link
                    href="/register"
                    class="mt-8 inline-flex items-center gap-3 rounded-xl bg-sm-red px-6 py-3.5 font-poppins text-base font-semibold text-white shadow-lg shadow-sm-red/20 transition duration-300 hover:-translate-y-1 hover:bg-sm-red-dark sm:text-lg"
                >
                    Mulai Sekarang
                    <!-- Panah putih; transition-transform supaya mulus saat hover -->
                    <img
                        src="/assets/vector_next_kanan_putih.svg"
                        alt=""
                        class="h-5 w-5 shrink-0 transition-transform duration-300 group-hover:translate-x-1"
                    />
                </Link>
            </div>

            <!--
                KOLOM KANAN — Visual hero (glow + ilustrasi + chip).
                relative → titik acuan posisi absolute anak-anaknya.
                lg:ml-auto lg:mr-0 → di layar lebar kolom ini menempel kanan.
            -->
            <div
                class="relative mx-auto w-full max-w-[10000px] min-w-0 self-center lg:mr-0 lg:ml-auto"
            >
                <!--
                    GLOW peach di BELAKANG ilustrasi:
                    - aspect-[867/849] → kotak glow ikut rasio gambar ilustrasi
                      (932×849), jadi glow tidak pernah "meleset" dari bentuk gambar
                    - w-[88%] + left-[6%] top-[4%] → sedikit lebih kecil dari gambar
                    - radial-gradient CIRCLE: inti peach #FFA586 membulat BESAR,
                      memudar ke #242F49 di95% radius (halo lebar, sesuai yang
                      kamu suka — jangan dipepet pakai ellipse)
                    - z-0 → paling bawah; pointer-events-none → tidak menghalangi klik
                -->
                <div
                    class="pointer-events-none absolute top-[4%] left-[6%] z-0 aspect-[867/849] w-[88%] rounded-full bg-[radial-gradient(circle_at_45%_35%,#FFA586_0%,#242F49_95%)]"
                    aria-hidden="true"
                />

                <!--
                    Ilustrasi utama. z-10 → di atas glow, di bawah chip (z-20).
                    lg:scale-105 xl:scale-110 → gambar sengaja di-perbesar5–10%
                    di layar lebar supaya terasa besar sesuai desain.
                -->
                <img
                    src="/assets/gambar_landing_page_desk_1.png"
                    alt="Event organizer dan perwakilan perusahaan berdiskusi"
                    class="relative z-10 block h-auto w-full origin-center object-contain lg:scale-105 xl:scale-110"
                />

                <!--
                    CHIP mengambang (diulang3× dari array `chips`):
                    - hidden md:flex → chip hanya tampil di layar ≥768px
                      (di mobile dianggap terlalu padat)
                    - absolute + class dari array → menempel pada posisi persen
                      relatif terhadap kotak ilustrasi
                    - z-20 → di atas gambar
                    - bg-white POLOS (target: putih solid, bukan semi-transparan)
                      jadi backdrop-blur tidak diperlukan lagi
                    - ukuran diperkecil (px-2 py-1, badge20px) agar chip ±118×31px
                      di layar lg, proporsional dengan desain target (92×24px
                      pada skala gambar)
                    - shadow → bayangan lembut agar terpisah dari gambar
                -->
                <div
                    v-for="chip in chips"
                    :key="chip.title"
                    :class="chip.class"
                    class="absolute z-20 hidden items-center gap-1.5 rounded-lg border border-white/70 bg-white px-2 py-1 shadow-[0_8px_30px_rgba(0,0,0,0.16)] md:flex lg:gap-2 lg:px-2.5 lg:py-1.5"
                >
                    <!--
                        Badge icon: lingkaran peach (#FFA586)20px (lg:24px)
                        sebagai latar icon kecil hitam/navy.
                        shrink-0 → tidak menyusut.
                    -->
                    <span
                        class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-sm-peach lg:h-6 lg:w-6"
                    >
                        <img
                            :src="`/assets/${chip.icon}`"
                            alt=""
                            class="h-3 w-3 lg:h-3.5 lg:w-3.5"
                        />
                    </span>

                    <!-- Teks chip2 baris: judul (tebal) + keterangan (tipis) -->
                    <div class="min-w-0 font-poppins leading-tight">
                        <!-- whitespace-nowrap → judul tidak boleh pindah baris -->
                        <p
                            class="text-[9px] font-semibold whitespace-nowrap text-sm-navy lg:text-[10px]"
                        >
                            {{ chip.title }}
                        </p>
                        <p
                            class="mt-0.5 text-[7px] whitespace-nowrap text-sm-navy/65 lg:text-[8px]"
                        >
                            {{ chip.note }}
                        </p>
                    </div>

                    <!--
                        Panah kanan "→" sebagai penanda bisa diklik.
                        &rarr; = HTML entity untuk panah (→).
                    -->
                    <span
                        class="ml-0.5 text-[10px] leading-none text-sm-navy lg:text-[11px]"
                        aria-hidden="true"
                    >
                        &rarr;
                    </span>
                </div>
            </div>
        </div>
    </section>
</template>
