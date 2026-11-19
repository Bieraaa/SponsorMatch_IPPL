<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';

/**
 * Kerangka halaman form pendaftaran per peran.
 *
 * Membungkus bagian yang SAMA di kedua form (EO & Perusahaan):
 *   - latar navy full-screen
 *   - tombol "Kembali" ke layar Role Pick (/register)
 *   - kartu gradient dengan judul + subjudul
 *   - footer "Sudah punya akun? Masuk"
 *
 * Isi field-nya disediakan lewat <slot /> sehingga tiap peran bebas
 * menentukan field-nya sendiri tanpa menggandakan kerangka ini.
 */
defineProps<{
    /** Judul kartu, mis. "Daftar sebagai Event Organizer" */
    title: string;
    /** Kalimat penjelas di bawah judul */
    subtitle: string;
    /** Teks tombol "Masuk" di footer */
    loginHref?: string;
}>();
</script>

<template>
    <div class="min-h-screen bg-sm-navy text-white">
        <div class="mx-auto w-full max-w-[520px] px-4 pb-14">
            <!-- Kembali ke layar Role Pick (/register), bukan ke landing -->
            <Link
                href="/register"
                class="mt-[40px] inline-flex items-center gap-[18px] text-base text-white transition-colors hover:text-sm-peach"
            >
                <ArrowLeft class="h-[18px] w-[18px]" aria-hidden="true" />
                Kembali
            </Link>

            <div
                class="mt-8 w-full rounded-[24px] border-[5px] border-[#384358] bg-[linear-gradient(180deg,#384358_0%,#232D46_8%,#192134_100%)] p-[34px]"
            >
                <h2 class="text-center text-[28px] font-bold">{{ title }}</h2>
                <p
                    class="mt-2 text-center text-base leading-[30px] text-white/80"
                >
                    {{ subtitle }}
                </p>

                <!-- Field-field khusus peran -->
                <slot />

                <p class="mt-6 text-center text-sm text-white/80">
                    Sudah punya akun?
                    <Link
                        :href="loginHref ?? '/login'"
                        class="text-sm-peach underline-offset-2 hover:underline"
                    >
                        Masuk
                    </Link>
                </p>
            </div>
        </div>
    </div>
</template>
