<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/register';
import RegisterField from '../components/RegisterField.vue';
import RegisterShell from '../components/RegisterShell.vue';

/**
 * Form pendaftaran PERUSAHAAN (Sponsor) — /register/perusahaan (BLOK A).
 *
 * Field (sesuai Figma & permintaan user):
 *   nama, email, nomor telepon, jabatan, password, konfirmasi password,
 *   nama perusahaan, NPWP, NIB
 *
 * CATATAN KEAMANAN:
 *   NPWP dan NIB adalah data legal yang sensitif. Nilai yang dikirim ke
 *   server disimpan TERENKRIPSI (AES-256 via APP_KEY) di tabel
 *   `company_profiles`, dan hash HMAC-nya disimpan terpisah untuk
 *   mencegah pendaftaran ganda. Backend memvalidasi format & keunikan
 *   lewat App\Actions\Fortify\CreateNewUser.
 *
 * Form ini POST ke /register dengan <input type="hidden" name="role" value="sponsor">.
 *
 * MODE GOOGLE:
 *   Kalau pendaftaran dilanjutkan dari login Google, email & nama sudah
 *   terisi otomatis dan terkunci, serta field password disembunyikan
 *   (password dibuat acak di server).
 */

/**
 * Prop halaman. `google` bernilai null untuk pendaftaran biasa.
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
    <Head title="Daftar Perusahaan" />

    <RegisterShell
        title="Daftar sebagai Perusahaan"
        subtitle="Isi data perusahaan untuk mulai menemukan event dan mengelola campaign di SponsorMatch"
    >
        <Form
            v-bind="store.form()"
            :reset-on-success="[
                'password',
                'password_confirmation',
                'npwp',
                'nib',
            ]"
            v-slot="{ errors, processing }"
            class="mt-6 flex flex-col gap-5"
        >
            <!-- Peran dikirim sebagai field tersembunyi; menentukan cabang validasi -->
            <input type="hidden" name="role" value="sponsor" />

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
                placeholder="Nama penanggung jawab"
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
                placeholder="nama@perusahaan.com"
                autocomplete="email"
                required
                :readonly="isGoogle"
                :default-value="google?.email"
                :input-class="isGoogle ? readOnlyInputClass : inputClass"
                :error="errors.email"
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
                id="jabatan"
                label="Jabatan"
                placeholder="Mis. Marketing Manager, CSR Lead"
                required
                :input-class="inputClass"
                :error="errors.jabatan"
            />

            <RegisterField
                id="nama_perusahaan"
                label="Nama Perusahaan"
                placeholder="Nama perusahaan / brand"
                autocomplete="organization"
                required
                :input-class="inputClass"
                :error="errors.nama_perusahaan"
            />

            <RegisterField
                id="npwp"
                label="NPWP"
                placeholder="15–16 digit NPWP"
                hint="Data ini disimpan terenkripsi dan hanya dipakai untuk verifikasi."
                required
                :input-class="inputClass"
                :error="errors.npwp"
            />

            <RegisterField
                id="nib"
                label="NIB"
                placeholder="Nomor Induk Berusaha (opsional)"
                hint="Kosongkan bila perusahaan belum memiliki NIB."
                :input-class="inputClass"
                :error="errors.nib"
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
