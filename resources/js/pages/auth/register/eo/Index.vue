<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import PasswordInput from '@/components/PasswordInput.vue';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/register';
import RegisterField from '../components/RegisterField.vue';
import RegisterShell from '../components/RegisterShell.vue';

/**
 * Form pendaftaran EVENT ORGANIZER — /register/eo (BLOK A).
 *
 * Field (sesuai Figma & permintaan user):
 *   nama, email, password, konfirmasi password,
 *   jabatan, nomor telepon, nama event organizer
 *
 * Form ini POST ke /register (route Fortify `register.store`) dengan
 * <input type="hidden" name="role" value="eo">. Backend
 * (App\Actions\Fortify\CreateNewUser) memvalidasi 'nama_event_organizer'
 * hanya ketika role = 'eo'.
 *
 * MODE GOOGLE:
 *   Kalau user datang lewat tombol "Lanjutkan dengan Google" dan emailnya
 *   belum terdaftar, backend menyalin email & nama Google ke form ini
 *   (prop `google`). Effect-nya:
 *     - email terkunci (read-only) — milik Google, tidak boleh diganti,
 *     - field password disembunyikan — password dibuat acak di server,
 *     - user tinggal mengisi jabatan, telepon, dan nama event organizer.
 *
 * Halaman dirender tanpa AuthLayout (lihat app.ts).
 */

/**
 * Prop halaman. `google` bernilai null untuk pendaftaran biasa (form email +
 * password). Isinya diisi hanya kalau pendaftaran dilanjutkan dari Google.
 */
const props = defineProps<{
    google?: { email: string; name: string } | null;
}>();

/** True kalau pendaftaran ini dimulai dari login Google. */
const isGoogle = computed(() => props.google != null);

/** Gaya input gelap yang seragam untuk seluruh field di halaman ini. */
const inputClass =
    'h-[54px] rounded-[14px] border-[#4A5670] bg-sm-navy px-4 text-base text-white placeholder:text-white/45 focus-visible:border-sm-red focus-visible:ring-0';

/** Gaya input yang terkunci (data dari Google — tidak bisa diedit). */
const readOnlyInputClass =
    'h-[54px] rounded-[14px] border-[#384358] bg-sm-navy px-4 text-base text-white/70 placeholder:text-white/45 focus-visible:border-sm-red focus-visible:ring-0';
</script>

<template>
    <Head title="Daftar Event Organizer" />

    <RegisterShell
        title="Daftar sebagai Event Organizer"
        subtitle="Isi data berikut untuk mulai mengelola event dan mencari sponsor di SponsorMatch"
    >
        <Form
            v-bind="store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="mt-6 flex flex-col gap-5"
        >
            <!-- Peran dikirim sebagai field tersembunyi; menentukan cabang validasi -->
            <input type="hidden" name="role" value="eo" />

            <!-- Info: pendaftaran dilanjutkan dari akun Google -->
            <div
                v-if="isGoogle"
                class="rounded-[14px] border border-[#384358] bg-white/5 px-4 py-3 text-sm leading-relaxed text-white/85"
            >
                Melanjutkan pendaftaran dengan akun Google
                <span class="font-semibold text-white">{{ google?.email }}</span
                >. Email sudah terverifikasi, jadi kamu tidak perlu membuat
                password.
            </div>

            <RegisterField
                id="name"
                label="Nama Lengkap"
                placeholder="Masukkan nama lengkap"
                autocomplete="name"
                required
                autofocus
                :default-value="google?.name"
                :input-class="inputClass"
                :error="errors.name"
            />

            <RegisterField
                id="email"
                label="Email"
                type="email"
                placeholder="nama@email.com"
                autocomplete="email"
                required
                :readonly="isGoogle"
                :default-value="google?.email"
                :input-class="isGoogle ? readOnlyInputClass : inputClass"
                :error="errors.email"
            />

            <RegisterField
                id="jabatan"
                label="Jabatan"
                placeholder="Mis. Ketua, Manajer Event"
                required
                :input-class="inputClass"
                :error="errors.jabatan"
            />

            <RegisterField
                id="nomor_telepon"
                label="Nomor Telepon"
                type="tel"
                placeholder="08xxxxxxxxxx"
                autocomplete="tel"
                required
                :input-class="inputClass"
                :error="errors.nomor_telepon"
            />

            <RegisterField
                id="nama_event_organizer"
                label="Nama Event Organizer"
                placeholder="Nama komunitas / penyelenggara"
                required
                :input-class="inputClass"
                :error="errors.nama_event_organizer"
            />

            <!-- Password hanya untuk pendaftaran biasa. Pada mode Google, password
                 dibuat acak oleh server (lihat App\Actions\Fortify\CreateNewUser). -->
            <div v-if="!isGoogle" class="grid gap-2">
                <Label for="password" class="text-base text-white"
                    >Password</Label
                >
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :class="inputClass"
                />
                <InputError :message="errors.password" class="text-[#FF8A96]" />
            </div>

            <div v-if="!isGoogle" class="grid gap-2">
                <Label for="password_confirmation" class="text-base text-white"
                    >Konfirmasi Password</Label
                >
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                    :class="inputClass"
                />
                <InputError
                    :message="errors.password_confirmation"
                    class="text-[#FF8A96]"
                />
            </div>

            <button
                type="submit"
                :disabled="processing"
                class="mt-1 h-[58px] w-full cursor-pointer rounded-[14px] bg-sm-red font-semibold text-white transition-colors hover:bg-[#C41F31] disabled:opacity-60"
            >
                <span
                    v-if="processing"
                    class="flex items-center justify-center gap-2"
                >
                    <Spinner class="h-4 w-4" />
                    Mendaftar...
                </span>
                <span v-else>Daftar Sekarang</span>
            </button>
        </Form>
    </RegisterShell>
</template>
