<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Spinner } from '@/components/ui/spinner';
import TextLink from '@/components/TextLink.vue';
import { register } from '@/routes';
import { redirect as googleRedirect } from '@/routes/auth/google';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import { Eye, EyeOff } from '@lucide/vue';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const showPassword = ref(false);

// Status checkbox "Ingat Saya". Dipakai supaya gambar centang (SVG)
// bisa muncul/hilang langsung dari Vue — lihat komentar di template.
const remember = ref(false);
</script>

<template>
    <Head title="Masuk" />

    <!--
        h-screen (bukan min-h-screen) = setinggi layar persis, jadi tidak ada
        scroll di desktop. overflow-y-auto disiapkan sebagai jaring pengaman
        untuk layar yang SANGAT pendek saja.
    -->
    <div class="flex h-screen overflow-hidden bg-white">
        <!-- Ilustrasi kiri (600x1024, sesuai Figma) — hanya tampil di layar besar -->
        <div class="hidden shrink-0 bg-white lg:block lg:w-[42%] xl:w-150">
            <img
                src="/assets/gambar_login.png"
                alt="SponsorMatch — Kolaborasi untuk Event Berdampak"
                class="h-full w-full object-cover object-top"
            />
        </div>
        ``

        <!--
            Panel kanan: navy, form di tengah. Konten dibungkus max-w 712px
            dan dipusatkan; padding vertikal memakai clamp() agar menyusut
            di layar pendek.
        -->
        <div
            class="flex flex-1 items-center justify-center overflow-y-auto bg-sm-navy px-6 lg:px-16"
        >
            <div class="w-full max-w-178 py-[clamp(1.5rem,4vh,4rem)]">
                <div
                    v-if="status"
                    class="mb-4 rounded-xl border-2 border-sm-red-dark bg-sm-card px-5 py-3 text-center text-sm font-medium text-white"
                >
                    {{ status }}
                </div>

                <!-- H1: "Selamat Datang" putih + "Kembali" merah -->
                <h1
                    class="text-[clamp(1.5rem,3.2vh,2.5rem)] leading-tight font-semibold text-white"
                >
                    Selamat Datang <span class="text-sm-red">Kembali</span>
                </h1>

                <!-- Subteks -->
                <p
                    class="mt-[clamp(0.5rem,1.4vh,1rem)] text-[clamp(0.95rem,1.7vh,1.125rem)] leading-snug text-white"
                >
                    Masuk ke akun SponsorMatch untuk kembali melanjutkan
                    perjalanan kolaborasi kamu
                </p>

                <Form
                    v-bind="store.form()"
                    :reset-on-success="['password']"
                    v-slot="{ errors, processing }"
                    class="mt-[clamp(1.25rem,3.2vh,3rem)]"
                >
                    <!-- Input Email -->
                    <div
                        class="flex h-[clamp(3.25rem,6.6vh,4.3125rem)] items-center gap-4 rounded-xl border-2 border-sm-red-dark bg-white px-5"
                    >
                        <img
                            src="/assets/icon_email.png"
                            alt=""
                            class="h-[38%] w-auto shrink-0"
                        />
                        <input
                            id="email"
                            type="text"
                            name="email"
                            required
                            v-focus
                            :tabindex="1"
                            autocomplete="username"
                            placeholder="Email atau nomor telepon"
                            class="h-full w-full border-0 bg-transparent text-[clamp(0.95rem,1.7vh,1.125rem)] text-sm-card outline-none placeholder:text-sm-card/70"
                        />
                    </div>
                    <InputError :message="errors.email" class="mt-2" />

                    <!-- Input Password (+ toggle lihat password) -->
                    <div
                        class="mt-[clamp(0.75rem,1.8vh,1.5rem)] flex h-[clamp(3.25rem,6.6vh,4.3125rem)] items-center gap-4 rounded-xl border-2 border-sm-red-dark bg-white px-5"
                    >
                        <img
                            src="/assets/icon_password.png"
                            alt=""
                            class="h-[42%] w-auto shrink-0"
                        />
                        <input
                            id="password"
                            :type="showPassword ? 'text' : 'password'"
                            name="password"
                            required
                            :tabindex="2"
                            autocomplete="current-password"
                            placeholder="Password"
                            class="h-full w-full border-0 bg-transparent text-[clamp(0.95rem,1.7vh,1.125rem)] text-sm-card outline-none placeholder:text-sm-card/70"
                        />
                        <button
                            type="button"
                            class="shrink-0 text-sm-card/70 hover:text-sm-card"
                            :tabindex="-1"
                            @click="showPassword = !showPassword"
                        >
                            <Eye
                                v-if="!showPassword"
                                class="h-[1.4em] w-[1.4em]"
                            />
                            <EyeOff v-else class="h-[1.4em] w-[1.4em]" />
                        </button>
                    </div>
                    <InputError :message="errors.password" class="mt-2" />

                    <!-- Baris: checkbox "Ingat Saya" (kiri) + "Lupa Password?" (kanan) -->
                    <div
                        class="mt-[clamp(0.9rem,2.2vh,2rem)] flex items-center justify-between"
                    >
                        <label
                            for="remember"
                            class="flex cursor-pointer items-center gap-3"
                        >
                            <!--
                                Checkbox kustom "Ingat Saya".
                                - `appearance-none` menghapus kotak bawaan browser.
                                - Status centang dikendalikan Vue (`v-model="remember"`),
                                  jadi gambar centang muncul/hilang lewat `v-show`.
                                Cara ini paling pasti: tidak bergantung pada varian
                                `peer-checked:` / `checked:` milik Tailwind sehingga
                                centang selalu tampil saat kotak diklik.
                            -->
                            <span
                                class="relative inline-flex h-[clamp(1.1rem,2.1vh,1.5rem)] w-[clamp(1.1rem,2.1vh,1.5rem)] shrink-0"
                            >
                                <input
                                    id="remember"
                                    name="remember"
                                    type="checkbox"
                                    v-model="remember"
                                    :tabindex="3"
                                    class="h-full w-full cursor-pointer appearance-none rounded-md border-2 border-sm-red-dark bg-white checked:border-sm-red checked:bg-sm-red"
                                />
                                <svg
                                    v-show="remember"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="white"
                                    stroke-width="3.5"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="pointer-events-none absolute inset-0 m-auto h-[70%] w-[70%]"
                                >
                                    <polyline points="20 6 9 17 4 12" />
                                </svg>
                            </span>
                            <span
                                class="text-[clamp(0.95rem,1.7vh,1.125rem)] text-white"
                                >Ingat Saya</span
                            >
                        </label>
                        <TextLink
                            v-if="canResetPassword"
                            :href="request()"
                            class="text-[clamp(0.95rem,1.7vh,1.125rem)] text-[#E2E3E5] hover:text-sm-red"
                            :tabindex="4"
                        >
                            Lupa Password?
                        </TextLink>
                    </div>

                    <!-- Tombol Masuk (merah, radius 12px) -->
                    <button
                        type="submit"
                        class="mt-[clamp(1rem,2.6vh,2.25rem)] flex h-[clamp(3.1rem,6.3vh,4.0625rem)] w-full items-center justify-center gap-3 rounded-xl bg-sm-red text-[clamp(1rem,2vh,1.25rem)] font-semibold text-white transition-colors hover:bg-sm-red-dark disabled:opacity-70"
                        :tabindex="5"
                        :disabled="processing"
                        data-test="login-button"
                    >
                        <Spinner v-if="processing" class="h-5 w-5" />
                        Masuk
                    </button>

                    <!-- Pemisah "Atau" -->
                    <div
                        class="mt-[clamp(1rem,2.6vh,2.5rem)] flex items-center gap-4"
                    >
                        <span class="h-px flex-1 bg-white/25"></span>
                        <span
                            class="text-[clamp(0.9rem,1.6vh,1.125rem)] text-white"
                            >Atau</span
                        >
                        <span class="h-px flex-1 bg-white/25"></span>
                    </div>

                    <!-- Tombol sosial (sesuai Figma).
                    Klik → /auth/google/redirect → Google → callback.
                    Tombol ini memakai <a> (bukan <button>) karena harus
                    MEMPINDAHKAN BROWSER ke halaman Google — itu pekerjaan
                    browser, bukan AJAX. Tombol Apple sudah dihapus sesuai
                    permintaan. -->
                    <a
                        :href="googleRedirect.url()"
                        class="mt-[clamp(1rem,2.6vh,2.5rem)] flex h-[clamp(3.25rem,6.6vh,4.3125rem)] w-full items-center justify-center gap-5 rounded-xl border-2 border-sm-red-dark bg-white text-[clamp(0.95rem,1.8vh,1.25rem)] font-medium text-sm-card transition-colors hover:bg-gray-50"
                    >
                        <img
                            src="/assets/icon_google.png"
                            alt=""
                            class="h-[80%] w-auto"
                        />
                        Lanjutkan dengan Google
                    </a>

                    <!-- Footer: daftar -->
                    <p
                        class="mt-[clamp(1rem,2.4vh,2.5rem)] text-center text-[clamp(0.95rem,1.7vh,1.125rem)] text-white"
                    >
                        Belum punya akun?
                        <TextLink
                            :href="register()"
                            class="font-semibold text-sm-red hover:underline"
                            :tabindex="6"
                        >
                            Daftar Sekarang
                        </TextLink>
                    </p>
                </Form>
            </div>
        </div>
    </div>
</template>
