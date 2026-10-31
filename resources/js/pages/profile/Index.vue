<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Camera,
    ChevronDown,
    CircleAlert,
    Info,
    Lock,
    Upload,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import DeleteUser from '@/components/DeleteUser.vue';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { getInitials } from '@/composables/useInitials';
import { update as updateProfile } from '@/routes/profile';
import { send } from '@/routes/verification';

/**
 * Halaman Profil / Pengaturan Akun (Figma "Profil", 19 Okt 2026).
 *
 * Prinsip penting:
 *  - Akun SELALU diambil dari props `auth.user` (pengguna yang login),
 *    bukan dari parameter/ID di URL.
 *  - Hanya nama, nomor telepon, dan foto profil yang bisa diubah.
 *  - Email & peran akun ditampilkan read-only. Peran akun ditetapkan saat
 *    pendaftaran dan TIDAK boleh diubah dari form ini (mencegah eskalasi
 *    hak akses).
 *  - Foto baru hanya di-PREVIEW di browser. File baru benar-benar diunggah
 *    saat "Simpan Perubahan" ditekan, sehingga "Batal" tidak menyentuh
 *    foto profil yang sudah tersimpan.
 */
type ProfilePhotoConfig = {
    maxSizeKb: number;
    extensions: string[];
    mimetypes: string[];
};

const props = withDefaults(
    defineProps<{
        mustVerifyEmail?: boolean;
        status?: string | null;
        profilePhoto?: ProfilePhotoConfig;
    }>(),
    {
        mustVerifyEmail: false,
        status: null,
        profilePhoto: () => ({
            maxSizeKb: 2048,
            extensions: ['jpg', 'jpeg', 'png'],
            mimetypes: ['image/jpeg', 'image/png'],
        }),
    },
);

const page = usePage();
const user = computed(() => page.props.auth.user);

const roleValue = computed(() =>
    user.value.role === 'sponsor' ? 'sponsor' : 'eo',
);
const roleLabel = computed(() =>
    roleValue.value === 'sponsor' ? 'Perusahaan' : 'Event Organizer',
);

const form = useForm({
    name: user.value.name ?? '',
    nomor_telepon: (user.value.nomor_telepon as string | null) ?? '',
    avatar: null as File | null,
});

/** Error validasi file dari sisi browser (sebelum request dikirim). */
const clientError = ref<string | null>(null);
/** Error umum dari server (500 / jaringan) — bukan error validasi field. */
const serverError = ref<string | null>(null);
/** Nama file yang sedang di-preview (belum tersimpan). */
const selectedFileName = ref<string | null>(null);

const fileInput = ref<HTMLInputElement | null>(null);
/** Object URL preview — wajib di-revoke supaya memori tidak bocor. */
const previewUrl = ref<string | null>(null);

const acceptTypes = computed(() =>
    (props.profilePhoto.mimetypes.length > 0
        ? props.profilePhoto.mimetypes
        : ['image/jpeg', 'image/png']
    ).join(','),
);
const allowedExtensions = computed(() =>
    props.profilePhoto.extensions.length > 0
        ? props.profilePhoto.extensions.map((ext) => ext.toLowerCase())
        : ['jpg', 'jpeg', 'png'],
);
const maxSizeBytes = computed(() => props.profilePhoto.maxSizeKb * 1024);
const maxSizeLabel = computed(() => {
    const kb = props.profilePhoto.maxSizeKb;

    return kb >= 1024 && kb % 1024 === 0 ? `${kb / 1024} MB` : `${kb} KB`;
});

const avatarSrc = computed(() => previewUrl.value ?? user.value.avatar ?? null);
const initials = computed(() => getInitials(user.value.name));
const hasPendingAvatar = computed(() => form.avatar !== null);

function openFilePicker(): void {
    if (form.processing) {
        return;
    }

    fileInput.value?.click();
}

function revokePreview(): void {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
        previewUrl.value = null;
    }
}

/** Validasi file di browser — server tetap memvalidasi ulang. */
function validateFile(file: File): string | null {
    const extension = file.name.split('.').pop()?.toLowerCase() ?? '';

    if (!allowedExtensions.value.includes(extension)) {
        return 'Format foto harus JPG, JPEG, atau PNG.';
    }

    // `file.type` berasal dari browser dan bisa saja kosong/diubah, jadi
    // server tetap memeriksa MIME asli file (aturan `image` + `mimetypes`).
    if (file.type !== '' && !props.profilePhoto.mimetypes.includes(file.type)) {
        return 'Format foto harus JPG, JPEG, atau PNG.';
    }

    if (file.size > maxSizeBytes.value) {
        return `Ukuran foto maksimal ${maxSizeLabel.value}. Pilih file yang lebih kecil.`;
    }

    return null;
}

function onFileChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    clientError.value = null;

    if (!file) {
        return;
    }

    const error = validateFile(file);

    if (error) {
        clientError.value = error;
        // File tidak valid: JANGAN mengganti foto yang sedang tampil.
        form.avatar = null;
        selectedFileName.value = null;
        revokePreview();
        input.value = '';

        return;
    }

    revokePreview();
    previewUrl.value = URL.createObjectURL(file);
    form.avatar = file;
    selectedFileName.value = file.name;
    form.clearErrors('avatar');
}

function cancelPhoto(): void {
    revokePreview();
    form.avatar = null;
    selectedFileName.value = null;
    clientError.value = null;
    form.clearErrors('avatar');

    if (fileInput.value) {
        fileInput.value.value = '';
    }
}

function resetForm(): void {
    if (form.processing) {
        return;
    }

    form.reset();
    form.clearErrors();
    cancelPhoto();
    serverError.value = null;
}

function submit(): void {
    if (form.processing) {
        return;
    }

    serverError.value = null;
    form.clearErrors();

    form.patch(updateProfile.url(), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            // Foto baru sudah diterima server: buang preview lokal, lalu
            // jadikan nilai sekarang sebagai baseline tombol "Batal".
            revokePreview();
            form.avatar = null;
            selectedFileName.value = null;
            clientError.value = null;

            if (fileInput.value) {
                fileInput.value.value = '';
            }

            form.defaults({
                name: form.name,
                nomor_telepon: form.nomor_telepon,
                avatar: null,
            });
        },
        onHttpException: () => {
            serverError.value =
                'Server gagal memproses perubahan. Silakan coba lagi beberapa saat lagi.';
        },
        onNetworkError: () => {
            serverError.value =
                'Tidak dapat terhubung ke server. Periksa koneksi kamu lalu coba lagi.';
        },
    });
}

const removeHttpExceptionListener = router.on('httpException', () => {
    serverError.value =
        'Server gagal memproses perubahan. Silakan coba lagi beberapa saat lagi.';
});
const removeNetworkErrorListener = router.on('networkError', () => {
    serverError.value =
        'Tidak dapat terhubung ke server. Periksa koneksi kamu lalu coba lagi.';
});

onBeforeUnmount(() => {
    revokePreview();
    removeHttpExceptionListener();
    removeNetworkErrorListener();
});

const inputClass =
    'h-11 rounded-lg border-gray-300 bg-white px-3 text-sm text-sm-navy placeholder:text-gray-400 focus-visible:border-sm-red focus-visible:ring-sm-red/25';
</script>

<template>
    <Head title="Profil" />

    <div class="min-h-full bg-[#F6F7FA] pb-12">
        <div class="mx-auto w-full max-w-[1180px] px-4 py-8 sm:px-6 lg:px-8">
            <!-- ===================== Header ===================== -->
            <header class="mb-6">
                <h1
                    class="text-3xl font-bold tracking-tight text-sm-navy sm:text-4xl"
                >
                    Profil
                </h1>
                <p class="mt-1 text-sm text-sm-card/70 sm:text-base">
                    Kelola informasi profil kamu
                </p>
            </header>

            <!-- Email belum terverifikasi (fitur lama tetap dipertahankan) -->
            <div
                v-if="mustVerifyEmail && !user.email_verified_at"
                class="mb-5 flex flex-col gap-2 rounded-xl border border-sm-amber/40 bg-amber-50 px-4 py-3 text-sm text-amber-900 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="flex items-start gap-2">
                    <CircleAlert
                        class="mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    Alamat email kamu belum diverifikasi.
                </p>
                <Link
                    :href="send()"
                    as="button"
                    class="w-fit font-semibold underline underline-offset-4 hover:text-amber-950"
                >
                    Kirim ulang email verifikasi
                </Link>
            </div>

            <div
                v-if="status === 'verification-link-sent'"
                class="mb-5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800"
                role="status"
            >
                Tautan verifikasi baru sudah dikirim ke alamat email kamu.
            </div>

            <!-- ===================== Kartu utama ===================== -->
            <div
                class="rounded-2xl border border-gray-200/80 bg-white shadow-sm"
            >
                <!-- ---------- Foto profil ---------- -->
                <section
                    class="flex flex-col gap-6 p-6 sm:flex-row sm:items-center sm:p-8"
                    aria-labelledby="photo-heading"
                >
                    <div class="relative w-fit shrink-0">
                        <Avatar class="size-28 rounded-full sm:size-36">
                            <AvatarImage
                                v-if="avatarSrc"
                                :src="avatarSrc"
                                :alt="`Foto profil ${user.name}`"
                                class="object-cover"
                            />
                            <AvatarFallback
                                class="rounded-full bg-sm-card text-4xl font-semibold text-white sm:text-5xl"
                            >
                                {{ initials || '?' }}
                            </AvatarFallback>
                        </Avatar>

                        <button
                            type="button"
                            class="absolute right-0 bottom-0 flex size-10 items-center justify-center rounded-full bg-sm-red text-white ring-4 ring-white transition-colors hover:bg-sm-red-dark focus-visible:ring-4 focus-visible:ring-sm-red/30 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60 sm:size-11"
                            :disabled="form.processing"
                            aria-label="Ganti foto profil"
                            @click="openFilePicker"
                        >
                            <Camera class="size-5" aria-hidden="true" />
                        </button>
                    </div>

                    <div class="min-w-0 flex-1">
                        <h2
                            id="photo-heading"
                            class="text-lg font-semibold text-sm-navy"
                        >
                            Foto Profil
                        </h2>
                        <p class="mt-1 text-sm text-sm-card/70">
                            JPG atau PNG, maksimal {{ maxSizeLabel }}
                        </p>

                        <div class="mt-4 flex flex-wrap items-center gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                class="h-11 rounded-lg border-sm-red/50 px-5 font-semibold text-sm-red hover:bg-sm-red/5 hover:text-sm-red-dark"
                                :disabled="form.processing"
                                @click="openFilePicker"
                            >
                                <Upload class="size-4" aria-hidden="true" />
                                Pilih Foto
                            </Button>

                            <Button
                                v-if="hasPendingAvatar"
                                type="button"
                                variant="ghost"
                                class="h-11 rounded-lg px-4 text-sm-card/70 hover:text-sm-red"
                                :disabled="form.processing"
                                @click="cancelPhoto"
                            >
                                Batalkan
                            </Button>
                        </div>

                        <p
                            class="mt-3 flex items-start gap-2 text-xs text-sm-card/60"
                        >
                            <Info
                                class="mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            <span>
                                Hanya file JPG, JPEG, atau PNG yang diterima.
                                Maksimal {{ maxSizeLabel }}.
                            </span>
                        </p>

                        <p
                            v-if="selectedFileName"
                            class="mt-2 text-xs font-medium text-sm-card/70"
                        >
                            Dipilih: {{ selectedFileName }} — belum disimpan.
                        </p>

                        <p
                            v-if="clientError"
                            class="mt-2 flex items-start gap-1.5 text-sm text-sm-red"
                            role="alert"
                        >
                            <CircleAlert
                                class="mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                            {{ clientError }}
                        </p>
                        <InputError
                            v-else
                            :message="form.errors.avatar"
                            class="mt-2"
                        />

                        <!-- Input file asli disembunyikan; tombol di atas memicunya -->
                        <input
                            id="avatar-input"
                            ref="fileInput"
                            type="file"
                            class="sr-only"
                            :accept="acceptTypes"
                            aria-label="Pilih foto profil (JPG, JPEG, atau PNG)"
                            @change="onFileChange"
                        />
                    </div>
                </section>

                <div class="mx-6 border-t border-gray-200 sm:mx-8"></div>

                <!-- ---------- Form informasi profil ---------- -->
                <form class="p-6 sm:p-8" novalidate @submit.prevent="submit">
                    <div
                        v-if="serverError"
                        class="mb-6 flex items-start gap-2 rounded-xl border border-sm-red/30 bg-red-50 px-4 py-3 text-sm text-sm-red"
                        role="alert"
                    >
                        <CircleAlert
                            class="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        {{ serverError }}
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <!-- Nama Lengkap -->
                        <div class="grid gap-2">
                            <Label
                                for="name"
                                class="text-sm font-semibold text-sm-navy"
                            >
                                Nama Lengkap
                            </Label>
                            <Input
                                id="name"
                                :model-value="form.name"
                                name="name"
                                type="text"
                                autocomplete="name"
                                required
                                maxlength="255"
                                placeholder="Masukkan nama lengkap"
                                :class="inputClass"
                                :aria-invalid="!!form.errors.name"
                                aria-describedby="name-error"
                                @update:model-value="
                                    (value) => (form.name = String(value))
                                "
                            />
                            <div id="name-error">
                                <InputError :message="form.errors.name" />
                            </div>
                        </div>

                        <!-- Jabatan / Peran akun (read-only) -->
                        <div class="grid gap-2">
                            <Label
                                for="jabatan"
                                class="text-sm font-semibold text-sm-navy"
                            >
                                Jabatan
                            </Label>
                            <div class="relative">
                                <select
                                    id="jabatan"
                                    :value="roleValue"
                                    disabled
                                    class="h-11 w-full cursor-not-allowed appearance-none rounded-lg border border-gray-300 bg-gray-100 px-3 pr-10 text-sm text-gray-500 disabled:cursor-not-allowed"
                                    aria-describedby="jabatan-hint"
                                >
                                    <option value="eo">Event Organizer</option>
                                    <option value="sponsor">Perusahaan</option>
                                </select>
                                <ChevronDown
                                    class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-gray-400"
                                    aria-hidden="true"
                                />
                            </div>
                            <p
                                id="jabatan-hint"
                                class="flex items-start gap-1.5 text-xs text-sm-card/60"
                            >
                                <Lock
                                    class="mt-0.5 size-3.5 shrink-0"
                                    aria-hidden="true"
                                />
                                <span>
                                    Peran akun: {{ roleLabel }}. Peran
                                    ditetapkan saat pendaftaran dan tidak dapat
                                    diubah di halaman ini.
                                </span>
                            </p>
                        </div>

                        <!-- Nomor Telepon -->
                        <div class="grid gap-2">
                            <Label
                                for="nomor_telepon"
                                class="text-sm font-semibold text-sm-navy"
                            >
                                Nomor Telepon
                            </Label>
                            <Input
                                id="nomor_telepon"
                                :model-value="form.nomor_telepon"
                                name="nomor_telepon"
                                type="tel"
                                inputmode="tel"
                                autocomplete="tel"
                                maxlength="30"
                                placeholder="+62 812-3456-7890"
                                :class="inputClass"
                                :aria-invalid="!!form.errors.nomor_telepon"
                                aria-describedby="nomor_telepon-error"
                                @update:model-value="
                                    (value) =>
                                        (form.nomor_telepon = String(value))
                                "
                            />
                            <div id="nomor_telepon-error">
                                <InputError
                                    :message="form.errors.nomor_telepon"
                                />
                            </div>
                        </div>

                        <!-- Alamat Email (read-only) -->
                        <div class="grid gap-2">
                            <Label
                                for="email"
                                class="text-sm font-semibold text-sm-navy"
                            >
                                Alamat Email
                            </Label>
                            <div class="relative">
                                <Input
                                    id="email"
                                    :model-value="user.email"
                                    name="email"
                                    type="email"
                                    readonly
                                    aria-readonly="true"
                                    aria-describedby="email-hint"
                                    class="h-11 cursor-not-allowed rounded-lg border-gray-300 bg-gray-100 px-3 pr-10 text-sm text-gray-500 focus-visible:border-gray-300 focus-visible:ring-0"
                                />
                                <Lock
                                    class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-gray-400"
                                    aria-hidden="true"
                                />
                            </div>
                            <p
                                id="email-hint"
                                class="flex items-start gap-1.5 text-xs text-sm-card/60"
                            >
                                <Info
                                    class="mt-0.5 size-3.5 shrink-0"
                                    aria-hidden="true"
                                />
                                Email tidak dapat diubah melalui halaman ini.
                            </p>
                        </div>
                    </div>

                    <div
                        class="mt-8 flex flex-col-reverse gap-3 border-t border-gray-200 pt-6 sm:flex-row sm:items-center"
                    >
                        <Button
                            type="submit"
                            class="h-11 rounded-lg bg-sm-red px-6 font-semibold text-white hover:bg-sm-red-dark focus-visible:ring-sm-red/30"
                            :disabled="form.processing || !form.isDirty"
                        >
                            <Spinner
                                v-if="form.processing"
                                class="size-4 text-white"
                            />
                            {{
                                form.processing
                                    ? 'Menyimpan...'
                                    : 'Simpan Perubahan'
                            }}
                        </Button>

                        <Button
                            type="button"
                            variant="outline"
                            class="h-11 rounded-lg border-sm-red/50 px-6 font-semibold text-sm-red hover:bg-sm-red/5 hover:text-sm-red-dark"
                            :disabled="form.processing || !form.isDirty"
                            @click="resetForm"
                        >
                            Batal
                        </Button>
                    </div>
                </form>
            </div>

            <!-- ===================== Zona Berbahaya ===================== -->
            <DeleteUser class="mt-6" />
        </div>
    </div>
</template>
