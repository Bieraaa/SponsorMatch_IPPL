<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';

/**
 * Halaman verifikasi kode OTP.
 *
 * Desain mengikuti frame Figma "OTP" (1440 x 1176).
 * Semua angka di bawah diambil dari analisis piksel PNG desain
 * (bukan dikira-kira), jadi posisinya presisi:
 *
 *   Kartu putih   : x 358..1081 (lebar 724px), y 167..930 (tinggi 764px), radius 20px
 *   Logo          : 346 x 54px, y 190 (23px dari atas kartu)
 *   H1            : "Verifikasi Kode", y 282..311, 40px bold, #242F49
 *   Subjudul      : 2 baris, y 330 & y 355, 18px, #242F49, rata-KIRI mulai x 499
 *   Kotak OTP     : 6 buah, 80 x 80px, y 388..467, border 1px #384358, radius 12px, jarak 20px
 *   Countdown     : y 512..530, "Waktu berakhir dalam" + "01:00" merah #B51A2B
 *   Tombol        : 603 x 61px, y 536..596, merah #B51A2B, radius 12px
 *   Kirim ulang   : y 641..658, rata kiri mulai x 427
 *   Kembali       : y 702..719, rata kiri mulai x 425
 *
 * Countdown dihitung di sisi browser supaya tidak perlu request ke
 * server tiap detik. Server tetap sumber kebenaran (kode hanya
 * berlaku 5 menit di sana).
 */
const props = defineProps<{
    purpose: string;
    email: string;
    userName: string;
    /** Sisa detik sebelum kode OTP expire. */
    expiresIn: number;
    /** Sisa detik cooldown sebelum boleh minta kode baru. */
    resendIn: number;
    /** Pesan error dari aksi sebelumnya (kirim ulang / verifikasi). */
    error?: string | null;
    /** true kalau kode baru JUST terkirim. */
    sent?: boolean;
    /**
     * true kalau email OTP GAGAL dikirim (mis. domain demo Mailtrap hanya
     * boleh mengirim ke email pemilik akun). Tanpa peringatan ini user
     * akan menunggu kode yang memang tidak pernah sampai.
     */
    mailFailed?: boolean;
}>();

/** Isi 6 kotak OTP. */
const code = ref('');

/**
 * Masa berlaku kode OTP (detik).
 *
 * Harus SAMA dengan `OtpService::TTL_SECONDS` di backend (300 detik).
 * Dipakai untuk me-reset countdown setelah kode baru dikirim.
 */
const OTP_TTL_SECONDS = 300;

/** Cooldown kirim ulang (detik) — sama dengan `OtpService::RESEND_COOLDOWN`. */
const RESEND_COOLDOWN = 60;

/** Countdown masa berlaku kode. */
const secondsLeft = ref(props.expiresIn);

/** Countdown cooldown kirim ulang. */
const resendSeconds = ref(props.resendIn);

/** State form Inertia (error validasi masuk ke form.errors). */
const form = useForm({ code: '' });

const resending = ref(false);

let timer: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    timer = setInterval(() => {
        if (secondsLeft.value > 0) {
            secondsLeft.value -= 1;
        }

        if (resendSeconds.value > 0) {
            resendSeconds.value -= 1;
        }
    }, 1000);
});

onUnmounted(() => {
    if (timer !== null) {
        clearInterval(timer);
    }
});

/**
 * Sinkronkan countdown dengan props terbaru.
 *
 * Inertia memakai instance komponen yang SAMA saat pindah halaman,
 * jadi `ref(props.expiresIn)` hanya jadi nilai awal. Kalau server
 * mengirim nilai baru (misal setelah kode baru dikirim), countdown
 * di layar harus ikut diperbarui — inilah yang bekerja.
 */
watch(
    () => props.expiresIn,
    (value) => {
        secondsLeft.value = value;
    },
);

watch(
    () => props.resendIn,
    (value) => {
        resendSeconds.value = value;
    },
);

/**
 * Kirim kode ke server.
 *
 * `onError` mengosongkan kotak supaya user bisa coba lagi dari awal
 * (lebih baik daripada kotak yang half-filled).
 */
function submit(): void {
    if (code.value.length !== 6 || form.processing) {
        return;
    }

    form.post('/verify-otp/verify', {
        preserveScroll: true,
        onError: () => {
            code.value = '';
        },
    });
}

// Auto-submit begitu 6 digit terisi — user tidak perlu klik tombol.
watch(code, (value) => {
    form.code = value;

    if (value.length === 6 && !form.processing) {
        submit();
    }
});

/**
 * Minta kode baru.
 *
 * Server yang membatasi (cooldown 60 detik + throttle), jadi meskipun
 * tombolnya di-disable di browser, perlindungannya tetap ada di server.
 */
function resend(): void {
    if (resendSeconds.value > 0 || resending.value) {
        return;
    }

    resending.value = true;

    router.post(
        '/verify-otp/send',
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                secondsLeft.value = OTP_TTL_SECONDS;
                resendSeconds.value = RESEND_COOLDOWN;
            },
            onFinish: () => {
                resending.value = false;
            },
        },
    );
}

/** Format detik -> "MM:SS" */
function formatTime(total: number): string {
    const minutes = Math.floor(total / 60)
        .toString()
        .padStart(2, '0');
    const seconds = (total % 60).toString().padStart(2, '0');

    return `${minutes}:${seconds}`;
}

const expiryText = computed(() => formatTime(secondsLeft.value));
const resendText = computed(() => formatTime(resendSeconds.value));

/** Pesan error yang tampil: dari server (props.error) atau validasi form. */
const errorMessage = computed(() => props.error ?? form.errors.code ?? null);
</script>

<template>
    <Head title="Verifikasi Kode" />

    <div
        class="flex min-h-screen flex-col items-center justify-center bg-sm-navy px-4 py-8"
    >
        <!-- Kartu putih 724 x 764px (sesuai Figma) -->
        <div
            class="w-full max-w-181 rounded-[20px] bg-white px-6 py-8 sm:px-10 lg:min-h-191 lg:px-15 lg:pt-5.75"
        >
            <!-- Logo SponsorMatch (346 x 54px) -->
            <div class="flex justify-center">
                <img
                    src="/assets/logo_sponsormatch_lengkap.svg"
                    alt="SponsorMatch"
                    class="h-10.5 w-auto lg:h-13.5"
                />
            </div>

            <!-- Judul -->
            <h1
                class="mt-[28px] text-center text-[28px] leading-[1.15] font-bold text-sm-card sm:text-[40px] lg:mt-[36px] lg:leading-[40px]"
            >
                Verifikasi Kode
            </h1>

            <!--
                Subjudul: 2 baris, rata KIRI (bukan center) — sesuai desain.
                break-2 memaksa baris pertama berhenti sebelum "anda".
            -->
            <p
                class="mt-[10px] max-w-[440px] text-left text-[15px] leading-[25px] text-sm-card sm:text-[18px] lg:ml-[81px]"
            >
                Masukkan kode OTP yang kami kirim kan ke email
                <br class="hidden lg:inline" />
                anda
            </p>

            <!-- Pesan error (dari server atau validasi) -->
            <p
                v-if="errorMessage"
                class="mt-5 rounded-xl border-2 border-sm-red-dark bg-[#FEF2F2] px-4 py-3 text-center text-sm font-medium text-sm-red-dark"
                role="alert"
            >
                {{ errorMessage }}
            </p>

            <!-- Konfirmasi kode baru terkirim -->
            <p
                v-else-if="sent"
                class="mt-5 rounded-xl border-2 border-sm-amber bg-[#FFF7ED] px-4 py-3 text-center text-sm font-medium text-[#7C4A03]"
                role="status"
            >
                Kode OTP baru sudah dikirim ke {{ email }}.
            </p>

            <!-- Peringatan: email gagal dikirim -->
            <p
                v-else-if="mailFailed"
                class="mt-5 rounded-xl border-2 border-sm-amber bg-[#FFF7ED] px-4 py-3 text-center text-sm font-medium text-[#7C4A03]"
                role="alert"
            >
                Kode OTP sudah dibuat, tetapi email gagal dikirim. Coba kirim
                ulang atau hubungi admin.
            </p>

            <!--
                6 kotak OTP: masing-masing 80 x 80px, border 1px #384358,
                radius 12px, jarak 20px (baris selebar 580px, di tengah).
            -->
            <div class="mt-3.5 lg:mt-2">
                <InputOTP
                    v-model="code"
                    :maxlength="6"
                    pattern="^[0-9]*$"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    aria-label="Kode OTP 6 digit"
                    class="mx-auto w-full max-w-145"
                >
                    <InputOTPGroup
                        class="grid w-full grid-cols-6 gap-2.5 lg:gap-5"
                    >
                        <InputOTPSlot
                            v-for="index in 6"
                            :key="index"
                            :index="index - 1"
                            class="h-[56px] w-full !rounded-[12px] border border-[#384358] bg-white text-[26px] leading-none font-semibold text-sm-card shadow-none first:!rounded-[12px] last:!rounded-[12px] data-[active=true]:border-sm-red lg:h-[80px] lg:text-[38px] dark:bg-white"
                        />
                    </InputOTPGroup>
                </InputOTP>
            </div>

            <!-- Countdown masa berlaku kode -->
            <div
                class="mt-[40px] flex items-center gap-[6px] text-[16px] lg:pl-[7px] lg:text-[18px]"
            >
                <span class="text-sm-card">Waktu berakhir dalam</span>
                <span class="font-semibold text-sm-red">{{ expiryText }}</span>
            </div>

            <!-- Tombol Verifikasi -->
            <button
                type="button"
                class="mt-[1px] flex h-[56px] w-full items-center justify-center gap-3 rounded-[12px] bg-sm-red text-[20px] font-semibold text-white transition-colors hover:bg-sm-red-dark disabled:opacity-70 lg:h-[61px] lg:text-[24px]"
                :disabled="form.processing || code.length !== 6"
                data-test="verify-otp-button"
                @click="submit"
            >
                <Spinner v-if="form.processing" class="h-5 w-5" />
                Verifikasi
            </button>

            <!-- Kirim ulang -->
            <p
                class="mt-[42px] text-left text-[15px] text-sm-card lg:pl-[7px] lg:text-[18px]"
            >
                Belum mendapatkan kode?
                <button
                    v-if="resendSeconds > 0"
                    type="button"
                    class="cursor-default font-semibold text-sm-red"
                    disabled
                >
                    Kirim ulang dalam {{ resendText }}
                </button>
                <button
                    v-else
                    type="button"
                    class="font-semibold text-sm-red underline-offset-2 hover:underline"
                    :disabled="resending"
                    @click="resend"
                >
                    {{ resending ? 'Mengirim...' : 'Kirim ulang' }}
                </button>
            </p>

            <!-- Kembali -->
            <div class="mt-[32px] lg:pl-[7px]">
                <Link
                    href="/"
                    class="inline-flex items-center gap-2 text-[16px] text-sm-card transition-colors hover:text-sm-red lg:text-[18px]"
                >
                    <ArrowLeft class="h-[18px] w-[18px]" aria-hidden="true" />
                    Kembali
                </Link>
            </div>
        </div>
    </div>
</template>
