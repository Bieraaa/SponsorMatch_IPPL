<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    CircleAlert,
    FileUp,
    ImagePlus,
    Loader2,
    Megaphone,
    ScanText,
} from '@lucide/vue';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ekstrak, store, update } from '@/routes/company/campaigns';
import type {
    CampaignCategoryOption,
    CompanyCampaign,
    UploadConfig,
} from '@/types/company';

/**
 * Dialog buat / edit campaign perusahaan.
 *
 * Campaign bersifat opsional: perusahaan boleh menyimpan sebagai `draft`
 * lebih dulu. Campaign hanya muncul sebagai peluang aktif bila statusnya
 * `active` dan tanggal berakhirnya belum lewat.
 */
const props = withDefaults(
    defineProps<{
        open: boolean;
        campaign: CompanyCampaign | null;
        categories: CampaignCategoryOption[];
        bannerConfig: UploadConfig;
        pdfMaxKb?: number;
    }>(),
    {
        pdfMaxKb: 5120,
    },
);

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const bannerPreview = ref<string | null>(null);
const bannerName = ref<string | null>(null);

/** Berkas PDF yang dipilih untuk ekstraksi AI (tidak ikut tersimpan). */
const pdfFile = ref<File | null>(null);
const pdfName = ref<string | null>(null);
const pdfSize = ref<number>(0);
const extracting = ref(false);
const extractError = ref<string | null>(null);
const extractedLabels = ref<string[]>([]);
const extractedByAi = ref(false);

/** Respons CompanyCampaignController::extract. */
type CampaignExtractResponse = {
    fields: Record<string, string | number | null>;
    filled: string[];
    source?: 'gemini';
};

const form = useForm({
    title: '',
    description: '',
    idKategori: '' as number | string,
    purpose: '',
    target_audience: '',
    budgetMin: '' as number | string,
    budgetMax: '' as number | string,
    supportType: 'Dana',
    benefits: '',
    estimatedParticipant: '' as number | string,
    periodStart: '',
    periodEnd: '',
    status: 'draft' as 'draft' | 'active',
    banner: null as File | null,
});

const inputClass =
    'h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-sm-navy outline-none placeholder:text-gray-400 focus:border-sm-red focus:ring-2 focus:ring-sm-red/15';

const textareaClass =
    'w-full resize-y rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-sm-navy outline-none placeholder:text-gray-400 focus:border-sm-red focus:ring-2 focus:ring-sm-red/15';

const accept = props.bannerConfig.extensions
    .map((extension) => `.${extension}`)
    .join(',');

function syncForm(): void {
    form.clearErrors();
    bannerName.value = null;
    pdfFile.value = null;
    pdfName.value = null;
    pdfSize.value = 0;
    extractError.value = null;
    extractedLabels.value = [];
    extractedByAi.value = false;

    if (props.campaign) {
        const campaign = props.campaign;

        form.defaults({
            title: campaign.title,
            description: campaign.description,
            idKategori: campaign.category_id,
            purpose: campaign.purpose ?? '',
            target_audience: campaign.target_audience ?? '',
            budgetMin: campaign.budget_min,
            budgetMax: campaign.budget_max,
            supportType: campaign.support_type ?? 'Dana',
            benefits: campaign.benefits ?? '',
            estimatedParticipant: campaign.estimated_participant ?? '',
            periodStart: campaign.period_start ?? '',
            periodEnd: campaign.period_end ?? '',
            status: campaign.status === 'active' ? 'active' : 'draft',
            banner: null,
        });

        bannerPreview.value = campaign.banner_url;
    } else {
        form.defaults({
            title: '',
            description: '',
            idKategori: '',
            purpose: '',
            target_audience: '',
            budgetMin: '',
            budgetMax: '',
            supportType: 'Dana',
            benefits: '',
            estimatedParticipant: '',
            periodStart: '',
            periodEnd: '',
            status: 'draft',
            banner: null,
        });

        bannerPreview.value = null;
    }

    form.reset();
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            syncForm();
        }
    },
    { immediate: true },
);

function onBannerChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    form.banner = file;
    bannerName.value = file?.name ?? null;

    if (file) {
        bannerPreview.value = URL.createObjectURL(file);
    }
}

function onPdfChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    pdfFile.value = file;
    pdfName.value = file?.name ?? null;
    pdfSize.value = file?.size ?? 0;
    extractError.value = null;
    extractedLabels.value = [];
    extractedByAi.value = false;
}

function formatBytes(size: number): string {
    if (size < 1024) {
        return `${size} B`;
    }

    if (size < 1024 * 1024) {
        return `${(size / 1024).toFixed(1)} KB`;
    }

    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
}

/** Token CSRF untuk request fetch (Laravel menaruhnya di cookie). */
function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/** Tempel hasil ekstraksi ke state form (hanya field yang ditemukan). */
function applyExtracted(fields: CampaignExtractResponse['fields']): void {
    if (typeof fields.title === 'string') {
        form.title = fields.title;
    }

    if (typeof fields.description === 'string') {
        form.description = fields.description;
    }

    if (typeof fields.purpose === 'string') {
        form.purpose = fields.purpose;
    }

    if (fields.idKategori !== null && fields.idKategori !== undefined) {
        form.idKategori = Number(fields.idKategori);
    }

    if (typeof fields.target_audience === 'string') {
        form.target_audience = fields.target_audience;
    }

    if (fields.budgetMin !== null && fields.budgetMin !== undefined) {
        form.budgetMin = String(fields.budgetMin);
    }

    if (fields.budgetMax !== null && fields.budgetMax !== undefined) {
        form.budgetMax = String(fields.budgetMax);
    }

    if (typeof fields.supportType === 'string') {
        form.supportType = fields.supportType;
    }

    if (typeof fields.benefits === 'string') {
        form.benefits = fields.benefits;
    }

    if (
        fields.estimatedParticipant !== null &&
        fields.estimatedParticipant !== undefined
    ) {
        form.estimatedParticipant = String(fields.estimatedParticipant);
    }

    if (typeof fields.periodStart === 'string') {
        form.periodStart = fields.periodStart;
    }

    if (typeof fields.periodEnd === 'string') {
        form.periodEnd = fields.periodEnd;
    }
}

/**
 * Minta server membaca PDF campaign dan mengisi form.
 * File TIDAK disimpan di sini; penyimpanan tetap lewat submit form.
 */
async function extractFromPdf(): Promise<void> {
    if (!pdfFile.value) {
        extractError.value = 'Pilih file PDF campaign terlebih dahulu.';
        return;
    }

    extracting.value = true;
    extractError.value = null;
    extractedLabels.value = [];
    extractedByAi.value = false;

    try {
        const body = new FormData();
        body.append('file', pdfFile.value);

        const response = await fetch(ekstrak().url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            credentials: 'same-origin',
            body,
        });

        if (response.status === 419) {
            extractError.value =
                'Sesi kamu sudah berakhir. Muat ulang halaman lalu coba lagi.';
            return;
        }

        const payload = (await response.json().catch(() => null)) as
            | (CampaignExtractResponse & { message?: string })
            | null;

        if (!response.ok) {
            extractError.value =
                payload?.message ??
                'Ekstraksi gagal. Coba lagi atau isi form manual.';
            return;
        }

        applyExtracted(payload?.fields ?? {});
        extractedLabels.value = payload?.filled ?? [];
        extractedByAi.value = payload?.source === 'gemini';
    } catch {
        extractError.value =
            'Tidak bisa menghubungi server. Periksa koneksimu lalu coba lagi.';
    } finally {
        extracting.value = false;
    }
}


function close(): void {
    emit('update:open', false);
    form.clearErrors();
}

function submit(): void {
    const options = {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => close(),
    };

    if (props.campaign) {
        form.patch(update(props.campaign.id).url, options);
    } else {
        form.post(store().url, options);
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2 text-lg font-semibold text-sm-navy">
                    <Megaphone class="h-5 w-5 text-sm-red" />
                    {{ campaign ? 'Edit Campaign' : 'Buat Campaign' }}
                </DialogTitle>
                <DialogDescription class="text-xs text-sm-card/70">
                    Campaign membantu EO memahami kebutuhan sponsorship
                    perusahaan. Kamu tetap bisa menerima proposal meski belum
                    punya campaign.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <!-- ============ Upload PDF + ekstraksi AI (paling atas) ============ -->
                <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50/70 p-3">
                    <div class="flex items-start gap-3">
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-sm-red"
                        >
                            <FileUp class="size-5" :stroke-width="2" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <label
                                for="campaign-pdf"
                                class="block text-[13px] font-medium text-sm-navy"
                            >
                                Upload Campaign PDF
                            </label>
                            <p class="mt-0.5 text-[11px] leading-relaxed text-sm-card/65">
                                Unggah dokumen campaign, lalu klik
                                <strong>Ekstrak dengan AI</strong> — nama,
                                deskripsi, tujuan, kategori, audiens, anggaran,
                                dan periode akan terisi otomatis.
                            </p>

                            <input
                                id="campaign-pdf"
                                type="file"
                                accept="application/pdf"
                                class="mt-2 block w-full text-[12px] text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-sm-navy file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-white hover:file:bg-sm-card"
                                @change="onPdfChange"
                            />

                            <p
                                v-if="pdfName"
                                class="mt-1.5 flex items-center gap-1 text-[11px] text-emerald-600"
                            >
                                <FileUp class="size-3.5 shrink-0" />
                                <span class="truncate">{{ pdfName }}</span>
                                <span class="shrink-0 text-emerald-600/70">
                                    · {{ formatBytes(pdfSize) }}
                                </span>
                            </p>

                            <button
                                type="button"
                                :disabled="!pdfFile || extracting"
                                class="mt-2 flex h-8 w-full items-center justify-center gap-1.5 rounded-lg border border-sm-navy/20 bg-white px-3 text-[12px] font-medium text-sm-navy transition-colors hover:border-sm-navy/40 hover:bg-sm-navy/5 disabled:cursor-not-allowed disabled:opacity-50"
                                @click="extractFromPdf"
                            >
                                <Loader2
                                    v-if="extracting"
                                    class="size-3.5 animate-spin"
                                />
                                <ScanText v-else class="size-3.5" />
                                <template v-if="extracting">
                                    Membaca dokumen dengan AI...
                                </template>
                                <template v-else-if="!pdfFile">
                                    Pilih PDF dulu untuk ekstrak
                                </template>
                                <template v-else>Ekstrak dengan AI</template>
                            </button>

                            <div
                                v-if="extractedLabels.length"
                                class="mt-2 rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-1.5"
                            >
                                <p
                                    class="text-[11px] font-medium text-emerald-700"
                                >
                                    <template v-if="extractedByAi">
                                        Hasil ekstraksi AI ·
                                    </template>
                                    {{ extractedLabels.length }} bagian
                                    terisi otomatis. Periksa lagi sebelum
                                    menyimpan.
                                </p>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    <span
                                        v-for="label in extractedLabels"
                                        :key="label"
                                        class="rounded bg-white px-1.5 py-0.5 text-[10px] font-medium text-emerald-700 ring-1 ring-emerald-200"
                                    >
                                        {{ label }}
                                    </span>
                                </div>
                            </div>

                            <p
                                v-if="extractError"
                                class="mt-2 flex items-start gap-1 rounded-lg border border-amber-200 bg-amber-50 px-2 py-1.5 text-[11px] leading-relaxed text-amber-700"
                            >
                                <CircleAlert
                                    class="mt-px size-3.5 shrink-0"
                                />
                                {{ extractError }}
                            </p>

                            <p
                                class="mt-1.5 flex items-center gap-1 text-[10px] text-sm-card/50"
                            >
                                <ScanText class="size-3" />
                                Maks. {{ Math.round(pdfMaxKb / 1024) }} MB ·
                                PDF saja
                            </p>
                        </div>
                    </div>
                </div>

                <div class="relative">
                    <div
                        class="absolute inset-0 flex items-center"
                        aria-hidden="true"
                    >
                        <span class="w-full border-t border-gray-100"></span>
                    </div>
                    <div class="relative flex justify-center">
                        <span
                            class="bg-white px-2 text-[10px] font-medium uppercase tracking-wide text-gray-400"
                        >
                            atau isi manual
                        </span>
                    </div>
                </div>

                <div>
                    <label for="campaign-title" class="mb-1 block text-xs font-semibold text-sm-navy">
                        Nama campaign <span class="text-sm-red">*</span>
                    </label>
                    <input
                        id="campaign-title"
                        v-model="form.title"
                        type="text"
                        maxlength="150"
                        :class="inputClass"
                        placeholder="Contoh: Kampanye Produk Baru 2026"
                    />
                    <InputError :message="form.errors.title" class="mt-1" />
                </div>

                <div>
                    <label for="campaign-description" class="mb-1 block text-xs font-semibold text-sm-navy">
                        Deskripsi <span class="text-sm-red">*</span>
                    </label>
                    <textarea
                        id="campaign-description"
                        v-model="form.description"
                        rows="3"
                        maxlength="3000"
                        :class="textareaClass"
                        placeholder="Jelaskan campaign dan peluang kolaborasinya"
                    ></textarea>
                    <InputError :message="form.errors.description" class="mt-1" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="campaign-category" class="mb-1 block text-xs font-semibold text-sm-navy">
                            Kategori event <span class="text-sm-red">*</span>
                        </label>
                        <select
                            id="campaign-category"
                            v-model="form.idKategori"
                            :class="inputClass"
                        >
                            <option value="" disabled>Pilih kategori</option>
                            <option
                                v-for="category in categories"
                                :key="category.id"
                                :value="category.id"
                            >
                                {{ category.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.idKategori" class="mt-1" />
                    </div>

                    <div>
                        <label for="campaign-audience" class="mb-1 block text-xs font-semibold text-sm-navy">
                            Target audiens
                        </label>
                        <input
                            id="campaign-audience"
                            v-model="form.target_audience"
                            type="text"
                            maxlength="500"
                            :class="inputClass"
                            placeholder="Gen Z, penggemar musik"
                        />
                        <InputError :message="form.errors.target_audience" class="mt-1" />
                    </div>

                    <div class="sm:col-span-2">
                        <label for="campaign-purpose" class="mb-1 block text-xs font-semibold text-sm-navy">
                            Tujuan campaign
                        </label>
                        <textarea
                            id="campaign-purpose"
                            v-model="form.purpose"
                            rows="2"
                            maxlength="1000"
                            :class="textareaClass"
                            placeholder="Apa yang ingin dicapai dari campaign ini?"
                        ></textarea>
                        <InputError :message="form.errors.purpose" class="mt-1" />
                    </div>

                    <div>
                        <label for="campaign-budget-min" class="mb-1 block text-xs font-semibold text-sm-navy">
                            Anggaran minimum <span class="text-sm-red">*</span>
                        </label>
                        <input
                            id="campaign-budget-min"
                            v-model="form.budgetMin"
                            type="number"
                            min="0"
                            :class="inputClass"
                            placeholder="40000000"
                        />
                        <InputError :message="form.errors.budgetMin" class="mt-1" />
                    </div>

                    <div>
                        <label for="campaign-budget-max" class="mb-1 block text-xs font-semibold text-sm-navy">
                            Anggaran maksimum <span class="text-sm-red">*</span>
                        </label>
                        <input
                            id="campaign-budget-max"
                            v-model="form.budgetMax"
                            type="number"
                            min="0"
                            :class="inputClass"
                            placeholder="120000000"
                        />
                        <InputError :message="form.errors.budgetMax" class="mt-1" />
                    </div>

                    <div>
                        <label for="campaign-start" class="mb-1 block text-xs font-semibold text-sm-navy">
                            Tanggal mulai <span class="text-sm-red">*</span>
                        </label>
                        <input
                            id="campaign-start"
                            v-model="form.periodStart"
                            type="date"
                            :class="inputClass"
                        />
                        <InputError :message="form.errors.periodStart" class="mt-1" />
                    </div>

                    <div>
                        <label for="campaign-end" class="mb-1 block text-xs font-semibold text-sm-navy">
                            Tanggal berakhir <span class="text-sm-red">*</span>
                        </label>
                        <input
                            id="campaign-end"
                            v-model="form.periodEnd"
                            type="date"
                            :class="inputClass"
                        />
                        <InputError :message="form.errors.periodEnd" class="mt-1" />
                    </div>

                    <div>
                        <label for="campaign-support" class="mb-1 block text-xs font-semibold text-sm-navy">
                            Jenis dukungan
                        </label>
                        <input
                            id="campaign-support"
                            v-model="form.supportType"
                            type="text"
                            maxlength="150"
                            :class="inputClass"
                            placeholder="Dana & sampling produk"
                        />
                        <InputError :message="form.errors.supportType" class="mt-1" />
                    </div>

                    <div>
                        <label for="campaign-participant" class="mb-1 block text-xs font-semibold text-sm-navy">
                            Perkiraan peserta
                        </label>
                        <input
                            id="campaign-participant"
                            v-model="form.estimatedParticipant"
                            type="number"
                            min="0"
                            :class="inputClass"
                            placeholder="15000"
                        />
                        <InputError
                            :message="form.errors.estimatedParticipant"
                            class="mt-1"
                        />
                    </div>

                    <div class="sm:col-span-2">
                        <label for="campaign-benefits" class="mb-1 block text-xs font-semibold text-sm-navy">
                            Benefit untuk event
                        </label>
                        <textarea
                            id="campaign-benefits"
                            v-model="form.benefits"
                            rows="2"
                            maxlength="2000"
                            :class="textareaClass"
                            placeholder="Apa yang didapat EO dari kolaborasi ini?"
                        ></textarea>
                        <InputError :message="form.errors.benefits" class="mt-1" />
                    </div>

                    <div>
                        <label for="campaign-status" class="mb-1 block text-xs font-semibold text-sm-navy">
                            Status
                        </label>
                        <select
                            id="campaign-status"
                            v-model="form.status"
                            :class="inputClass"
                        >
                            <option value="draft">Draft</option>
                            <option value="active">Aktif</option>
                        </select>
                        <p class="mt-1 text-[10px] text-sm-card/55">
                            Campaign aktif akan tampil sebagai peluang
                            sponsorship selama tanggal berakhir belum lewat.
                        </p>
                        <InputError :message="form.errors.status" class="mt-1" />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-sm-navy">
                            Banner campaign
                        </label>
                        <label
                            class="flex h-[92px] cursor-pointer items-center justify-center overflow-hidden rounded-lg border border-dashed border-gray-300 bg-gray-50 transition-colors hover:border-sm-red/40"
                        >
                            <img
                                v-if="bannerPreview"
                                :src="bannerPreview"
                                alt="Pratinjau banner campaign"
                                class="h-full w-full object-cover"
                            />
                            <span
                                v-else
                                class="flex flex-col items-center gap-1 text-[10px] text-sm-card/55"
                            >
                                <ImagePlus class="h-5 w-5" />
                                Pilih gambar (maks. {{ bannerConfig.max_kb }} KB)
                            </span>
                            <input
                                type="file"
                                class="hidden"
                                :accept="accept"
                                @change="onBannerChange"
                            />
                        </label>
                        <p v-if="bannerName" class="mt-1 truncate text-[10px] text-sm-card/60">
                            {{ bannerName }}
                        </p>
                        <p v-else class="mt-1 text-[10px] text-sm-card/50">
                            Format: {{ bannerConfig.extensions.join(', ') }}
                        </p>
                        <InputError :message="form.errors.banner" class="mt-1" />
                    </div>
                </div>

                <DialogFooter class="gap-2 border-t border-gray-100 pt-4">
                    <button
                        type="button"
                        class="h-9 rounded-lg border border-gray-300 bg-white px-4 text-xs font-semibold text-sm-navy transition-colors hover:bg-gray-50"
                        :disabled="form.processing"
                        @click="close"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="inline-flex h-9 items-center gap-2 rounded-lg bg-sm-red px-4 text-xs font-semibold text-white transition-colors hover:bg-sm-red-dark disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="form.processing"
                    >
                        <Loader2
                            v-if="form.processing"
                            class="h-4 w-4 animate-spin"
                        />
                        {{ campaign ? 'Simpan Perubahan' : 'Buat Campaign' }}
                    </button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
