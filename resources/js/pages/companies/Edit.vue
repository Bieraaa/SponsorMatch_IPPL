<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Building2, ImagePlus, Save, Tags } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { update } from '@/routes/company/profile';
import type {
    CampaignCategoryOption,
    CompanyProfileForm,
    UploadConfig,
} from '@/types/company';

/**
 * Form "Profil Perusahaan".
 *
 * Profil adalah sumber utama rekomendasi AI. Minimal nama perusahaan dan
 * bidang industri wajib diisi; campaign tetap opsional.
 *
 * Input sengaja putih dengan border abu-abu agar konsisten dengan form
 * lain di aplikasi (bukan input abu-abu bawaan browser).
 */
const props = defineProps<{
    profile: CompanyProfileForm;
    categories: CampaignCategoryOption[];
    logoConfig: UploadConfig;
}>();

const fileInput = ref<HTMLInputElement | null>(null);
const logoPreview = ref<string | null>(props.profile.logo_url);
let objectUrl: string | null = null;

const form = useForm({
    company_name: props.profile.company_name ?? '',
    industry: props.profile.industry ?? '',
    description: props.profile.description ?? '',
    website: props.profile.website ?? '',
    sponsorship_min: props.profile.sponsorship_min?.toString() ?? '',
    sponsorship_max: props.profile.sponsorship_max?.toString() ?? '',
    event_types: props.profile.event_types ?? '',
    target_audience: props.profile.target_audience ?? '',
    categories: [...props.profile.categories],
    logo: null as File | null,
});

watch(
    () => props.profile.logo_url,
    (value) => {
        if (objectUrl === null) {
            logoPreview.value = value;
        }
    },
);

const accept = computed(() =>
    props.logoConfig.extensions.map((extension) => `.${extension}`).join(','),
);

const formats = computed(() =>
    props.logoConfig.extensions
        .map((extension) => extension.toUpperCase())
        .join(', '),
);

const maxSizeMb = computed(() =>
    (props.logoConfig.max_kb / 1024).toFixed(1).replace('.0', ''),
);

function pickLogo(): void {
    fileInput.value?.click();
}

function onLogoChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    form.logo = file;

    if (objectUrl !== null) {
        URL.revokeObjectURL(objectUrl);
        objectUrl = null;
    }

    if (file) {
        objectUrl = URL.createObjectURL(file);
        logoPreview.value = objectUrl;
    }
}

function submit(): void {
    form.patch(update().url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.logo = null;
        },
    });
}

onBeforeUnmount(() => {
    if (objectUrl !== null) {
        URL.revokeObjectURL(objectUrl);
    }
});
</script>

<template>
    <Head title="Profil Perusahaan" />

    <div class="min-h-full bg-[#F7F8FA] pb-12">
        <div class="mx-auto w-full max-w-[1180px] px-4 py-6 xl:px-0">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <h1
                        class="text-2xl font-semibold text-sm-navy lg:text-[30px]"
                    >
                        Profil Perusahaan
                    </h1>
                    <p class="mt-2 text-sm text-sm-card/70">
                        Profil ini menjadi sumber utama rekomendasi AI untuk
                        perusahaan kamu.
                    </p>
                </div>

                <Link
                    href="/company/campaigns"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 text-xs font-semibold text-sm-navy transition-colors hover:bg-gray-50"
                >
                    Kelola Campaign
                </Link>
            </div>

            <form
                class="mt-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-7"
                @submit.prevent="submit"
            >
                <section>
                    <h2
                        class="flex items-center gap-2 text-sm font-semibold text-sm-navy"
                    >
                        <Building2 class="h-4 w-4 text-sm-red" />
                        Identitas perusahaan
                    </h2>

                    <div
                        class="mt-4 grid gap-5 md:grid-cols-[180px_minmax(0,1fr)]"
                    >
                        <div>
                            <div
                                class="flex h-[150px] w-[150px] items-center justify-center overflow-hidden rounded-2xl border border-gray-200 bg-gray-50"
                            >
                                <img
                                    v-if="logoPreview"
                                    :src="logoPreview"
                                    alt="Logo perusahaan"
                                    class="h-full w-full object-contain p-3"
                                />
                                <ImagePlus
                                    v-else
                                    class="h-8 w-8 text-gray-400"
                                />
                            </div>

                            <input
                                ref="fileInput"
                                type="file"
                                class="hidden"
                                :accept="accept"
                                @change="onLogoChange"
                            />

                            <button
                                type="button"
                                class="mt-3 w-[150px] rounded-lg border border-gray-300 bg-white px-3 py-2 text-[11px] font-semibold text-sm-navy hover:bg-gray-50"
                                @click="pickLogo"
                            >
                                Pilih Logo
                            </button>

                            <p
                                class="mt-2 text-[10px] leading-relaxed text-sm-card/50"
                            >
                                {{ formats }} · maks. {{ maxSizeMb }} MB
                            </p>
                            <InputError
                                :message="form.errors.logo"
                                class="mt-1"
                            />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label
                                    for="company_name"
                                    class="text-xs font-semibold text-sm-navy"
                                >
                                    Nama perusahaan
                                    <span class="text-sm-red">*</span>
                                </label>
                                <input
                                    id="company_name"
                                    v-model="form.company_name"
                                    type="text"
                                    maxlength="100"
                                    class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-sm-navy outline-none focus:border-sm-red focus:ring-2 focus:ring-sm-red/15"
                                />
                                <InputError
                                    class="mt-1"
                                    :message="form.errors.company_name"
                                />
                            </div>

                            <div>
                                <label
                                    for="industry"
                                    class="text-xs font-semibold text-sm-navy"
                                >
                                    Bidang industri
                                    <span class="text-sm-red">*</span>
                                </label>
                                <input
                                    id="industry"
                                    v-model="form.industry"
                                    type="text"
                                    maxlength="100"
                                    placeholder="Contoh: Teknologi, Otomotif"
                                    class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-sm-navy outline-none placeholder:text-gray-400 focus:border-sm-red focus:ring-2 focus:ring-sm-red/15"
                                />
                                <InputError
                                    class="mt-1"
                                    :message="form.errors.industry"
                                />
                            </div>

                            <div>
                                <label
                                    for="website"
                                    class="text-xs font-semibold text-sm-navy"
                                >
                                    Website
                                </label>
                                <input
                                    id="website"
                                    v-model="form.website"
                                    type="url"
                                    maxlength="255"
                                    placeholder="https://perusahaan.co.id"
                                    class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-sm-navy outline-none placeholder:text-gray-400 focus:border-sm-red focus:ring-2 focus:ring-sm-red/15"
                                />
                                <InputError
                                    class="mt-1"
                                    :message="form.errors.website"
                                />
                            </div>

                            <div class="sm:col-span-2">
                                <label
                                    for="description"
                                    class="text-xs font-semibold text-sm-navy"
                                >
                                    Deskripsi perusahaan
                                </label>
                                <textarea
                                    id="description"
                                    v-model="form.description"
                                    rows="4"
                                    maxlength="3000"
                                    placeholder="Ceritakan bisnis, produk, dan fokus sponsorship perusahaan."
                                    class="mt-1.5 w-full resize-y rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-sm-navy outline-none placeholder:text-gray-400 focus:border-sm-red focus:ring-2 focus:ring-sm-red/15"
                                ></textarea>
                                <InputError
                                    class="mt-1"
                                    :message="form.errors.description"
                                />
                            </div>
                        </div>
                    </div>
                </section>

                <section class="mt-7 border-t border-gray-100 pt-6">
                    <h2 class="text-sm font-semibold text-sm-navy">
                        Preferensi sponsorship
                    </h2>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label
                                for="sponsorship_min"
                                class="text-xs font-semibold text-sm-navy"
                            >
                                Dana sponsorship minimum
                            </label>
                            <input
                                id="sponsorship_min"
                                v-model="form.sponsorship_min"
                                type="number"
                                min="0"
                                placeholder="40000000"
                                class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-sm-navy outline-none placeholder:text-gray-400 focus:border-sm-red focus:ring-2 focus:ring-sm-red/15"
                            />
                            <InputError
                                class="mt-1"
                                :message="form.errors.sponsorship_min"
                            />
                        </div>

                        <div>
                            <label
                                for="sponsorship_max"
                                class="text-xs font-semibold text-sm-navy"
                            >
                                Dana sponsorship maksimum
                            </label>
                            <input
                                id="sponsorship_max"
                                v-model="form.sponsorship_max"
                                type="number"
                                min="0"
                                placeholder="120000000"
                                class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-sm-navy outline-none placeholder:text-gray-400 focus:border-sm-red focus:ring-2 focus:ring-sm-red/15"
                            />
                            <InputError
                                class="mt-1"
                                :message="form.errors.sponsorship_max"
                            />
                        </div>

                        <div class="sm:col-span-2">
                            <label
                                for="event_types"
                                class="text-xs font-semibold text-sm-navy"
                            >
                                Jenis event yang diminati
                            </label>
                            <input
                                id="event_types"
                                v-model="form.event_types"
                                type="text"
                                maxlength="500"
                                placeholder="Musik, Olahraga, Edukasi"
                                class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-sm-navy outline-none placeholder:text-gray-400 focus:border-sm-red focus:ring-2 focus:ring-sm-red/15"
                            />
                            <p class="mt-1 text-[10px] text-sm-card/55">
                                Pisahkan dengan koma.
                            </p>
                            <InputError
                                class="mt-1"
                                :message="form.errors.event_types"
                            />
                        </div>

                        <div class="sm:col-span-2">
                            <label
                                for="target_audience"
                                class="text-xs font-semibold text-sm-navy"
                            >
                                Target audiens
                            </label>
                            <input
                                id="target_audience"
                                v-model="form.target_audience"
                                type="text"
                                maxlength="500"
                                placeholder="Gen Z, Milenial muda"
                                class="mt-1.5 w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-sm-navy outline-none placeholder:text-gray-400 focus:border-sm-red focus:ring-2 focus:ring-sm-red/15"
                            />
                            <InputError
                                class="mt-1"
                                :message="form.errors.target_audience"
                            />
                        </div>
                    </div>
                </section>

                <section class="mt-7 border-t border-gray-100 pt-6">
                    <h2
                        class="flex items-center gap-2 text-sm font-semibold text-sm-navy"
                    >
                        <Tags class="h-4 w-4 text-sm-red" />
                        Kategori sponsorship yang diminati
                    </h2>
                    <p class="mt-1 text-[11px] text-sm-card/60">
                        Kategori membantu AI mencocokkan profil dengan event
                        yang relevan.
                    </p>

                    <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <label
                            v-for="category in categories"
                            :key="category.id"
                            class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-gray-200 bg-white px-3 py-2.5 text-xs transition-colors hover:border-sm-red/40"
                            :class="{
                                'border-sm-red/50 bg-red-50/40':
                                    form.categories.includes(category.id),
                            }"
                        >
                            <input
                                v-model="form.categories"
                                type="checkbox"
                                :value="category.id"
                                class="mt-0.5 h-4 w-4 rounded border-gray-300 text-sm-red focus:ring-sm-red"
                            />
                            <span class="min-w-0">
                                <span class="block font-semibold text-sm-navy">
                                    {{ category.name }}
                                </span>
                                <span
                                    v-if="category.description"
                                    class="mt-0.5 block text-[10px] leading-snug text-sm-card/55"
                                >
                                    {{ category.description }}
                                </span>
                            </span>
                        </label>
                    </div>
                    <InputError
                        class="mt-1"
                        :message="form.errors.categories"
                    />
                </section>

                <div
                    class="mt-7 flex items-center justify-end gap-2 border-t border-gray-200 pt-5"
                >
                    <Link
                        href="/company/campaigns"
                        class="inline-flex h-10 items-center justify-center rounded-lg border border-gray-200 bg-white px-4 text-xs font-semibold text-sm-navy transition-colors hover:bg-gray-50"
                    >
                        Batal
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex h-10 items-center gap-2 rounded-lg bg-sm-red px-4 text-xs font-semibold text-white transition-colors hover:bg-sm-red-dark disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <Save class="h-4 w-4" />
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Profil' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
