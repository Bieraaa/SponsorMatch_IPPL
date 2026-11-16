<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { CalendarDays, CircleAlert, FileUp, LoaderCircle, ScanText } from '@lucide/vue';
import { reactive, ref, watch } from 'vue';
import ProposalController from '@/actions/App/Http/Controllers/ProposalController';
import InputError from '@/components/InputError.vue';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import type { ProposalKategori } from '@/types/proposal';

/**
 * Dialog "+ Tambah Proposal".
 *
 * Form ini benar-benar menyimpan proposal baru ke tabel `proposal`
 * dengan status `draft` (lihat ProposalController::store).
 *
 * ALUR
 * ----
 * 1. User mengunggah PDF proposal (paling atas).
 * 2. Tombol "Ekstrak dari PDF" memanggil endpoint `proposals.ekstrak`
 *    (ProposalController::extract) yang membaca teks PDF lalu mengembalikan
 *    isian yang dikenali: judul, kategori, lokasi, anggaran, tanggal, dst.
 * 3. Nilai hasil ekstraksi ditempel ke state form (`form`), jadi user tinggal
 *    memeriksa/mengoreksi sebelum menyimpan.
 * 4. Field yang tidak dikenali tetap kosong dan bisa diisi manual.
 *
 * Form memakai `v-model` (bukan input tak terkontrol) supaya hasil ekstraksi
 * bisa langsung mengisi field, tapi tetap dikirim lewat <Form> Inertia yang
 * menyerialkan elemen form berdasarkan atribut `name`.
 */
const props = defineProps<{
    kategori: ProposalKategori[];
    open: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

type ProposalForm = {
    title: string;
    idKategori: string;
    needType: string;
    budgetMin: string;
    budgetMax: string;
    start_date: string;
    end_date: string;
    location: string;
    target_audience: string;
    desctiption: string;
    benefits: string;
};

/** Bentuk respons ProposalController::extract. */
type ExtractResponse = {
    fields: Record<string, string | number | null>;
    filled: string[];
    textLength: number;
    source?: 'gemini' | 'regex';
};

const inputClass =
    'h-9 w-full rounded-lg border border-gray-200 bg-white px-3 text-[13px] text-sm-navy placeholder:text-gray-400 focus:border-sm-navy focus:outline-none focus:ring-1 focus:ring-sm-navy';

const textareaClass =
    'w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-[13px] text-sm-navy placeholder:text-gray-400 focus:border-sm-navy focus:outline-none focus:ring-1 focus:ring-sm-navy';

const needTypes = ['Dana', 'Produk', 'Dana & produk', 'Jasa'];

/** Nilai awal form; dipakai juga untuk mereset dialog. */
function emptyForm(): ProposalForm {
    return {
        title: '',
        idKategori: '',
        needType: 'Dana',
        budgetMin: '',
        budgetMax: '',
        start_date: '',
        end_date: '',
        location: '',
        target_audience: '',
        desctiption: '',
        benefits: '',
    };
}

const form = reactive<ProposalForm>(emptyForm());

/** Reset form setiap dialog dibuka kembali. */
const formKey = ref(0);

/** Berkas yang sedang dipilih (untuk menampilkan nama + ukuran). */
const selectedFile = ref<{ name: string; size: number } | null>(null);

/** Status tombol ekstrak. */
const extracting = ref(false);
const extractError = ref<string | null>(null);
const extractedLabels = ref<string[]>([]);
const extractedByAi = ref(false);

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            return;
        }

        Object.assign(form, emptyForm());
        selectedFile.value = null;
        extractError.value = null;
        extractedLabels.value = [];
        extractedByAi.value = false;
        formKey.value++;
    },
);

function onFileChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    selectedFile.value = file ? { name: file.name, size: file.size } : null;
    extractError.value = null;
    extractedLabels.value = [];
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

/**
 * Input anggaran disimpan sebagai digit mentah ("100000000") di state,
 * tapi ditampilkan dengan pemisah ribuan ("100.000.000").
 */
function formatRupiah(digits: string): string {
    if (digits === '') {
        return '';
    }

    const number = Number(digits);

    return Number.isFinite(number) ? number.toLocaleString('id-ID') : '';
}

function onBudgetInput(field: 'budgetMin' | 'budgetMax', event: Event): void {
    const input = event.target as HTMLInputElement;

    // Hanya angka; batasi 15 digit supaya tidak melebihi batas integer DB.
    const digits = input.value.replace(/\D/g, '').slice(0, 15);

    form[field] = digits;
    input.value = formatRupiah(digits);
}

/**
 * Buka kalender bawaan browser saat field tanggal diklik.
 * `showPicker()` didukung Chrome/Edge; browser lain tetap bisa mengetik
 * tanggal seperti biasa, jadi tidak ada fitur yang rusak.
 */
function openDatePicker(id: string): void {
    const input = document.getElementById(id) as HTMLInputElement | null;

    if (input && typeof input.showPicker === 'function') {
        try {
            input.showPicker();
        } catch {
            // Sebagian browser menolak showPicker() di luar gesture pengguna.
        }
    }
}

/** Token CSRF untuk request fetch (Laravel menaruhnya di cookie). */
function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/** Tempel hasil ekstraksi ke state form (hanya field yang ditemukan). */
function applyExtracted(fields: ExtractResponse['fields']): void {
    if (typeof fields.title === 'string') {
        form.title = fields.title;
    }

    if (fields.idKategori !== null && fields.idKategori !== undefined) {
        form.idKategori = String(fields.idKategori);
    }

    if (typeof fields.location === 'string') {
        form.location = fields.location;
    }

    if (typeof fields.needType === 'string') {
        form.needType = fields.needType;
    }

    if (typeof fields.target_audience === 'string') {
        form.target_audience = fields.target_audience;
    }

    if (typeof fields.desctiption === 'string') {
        form.desctiption = fields.desctiption;
    }

    if (typeof fields.benefits === 'string') {
        form.benefits = fields.benefits;
    }

    if (fields.budgetMin !== null && fields.budgetMin !== undefined) {
        form.budgetMin = String(fields.budgetMin);
    }

    if (fields.budgetMax !== null && fields.budgetMax !== undefined) {
        form.budgetMax = String(fields.budgetMax);
    }

    if (typeof fields.start_date === 'string') {
        form.start_date = fields.start_date;
    }

    if (typeof fields.end_date === 'string') {
        form.end_date = fields.end_date;
    }
}

/**
 * Minta server membaca PDF dan mengembalikan isian yang dikenali.
 * File TIDAK disimpan di sini — penyimpanan tetap lewat submit form.
 */
async function extractFromPdf(): Promise<void> {
    const file = (document.getElementById('file') as HTMLInputElement | null)?.files?.[0];

    if (!file) {
        extractError.value = 'Pilih dokumen PDF terlebih dahulu.';
        return;
    }

    extracting.value = true;
    extractError.value = null;
    extractedLabels.value = [];
    extractedByAi.value = false;

    try {
        const body = new FormData();
        body.append('file', file);

        const response = await fetch(ProposalController.extract.url(), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            credentials: 'same-origin',
            body,
        });

        if (response.status === 419) {
            extractError.value = 'Sesi kamu sudah berakhir. Muat ulang halaman lalu coba lagi.';
            return;
        }

        const payload = (await response.json().catch(() => null)) as
            | (ExtractResponse & { message?: string })
            | null;

        if (!response.ok) {
            extractError.value = payload?.message ?? 'Ekstraksi gagal. Coba lagi atau isi manual.';
            return;
        }

        applyExtracted(payload?.fields ?? {});
        extractedLabels.value = payload?.filled ?? [];
        extractedByAi.value = payload?.source === 'gemini';
    } catch {
        extractError.value = 'Tidak bisa menghubungi server. Periksa koneksimu lalu coba lagi.';
    } finally {
        extracting.value = false;
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[90vh] overflow-y-auto bg-white text-sm-navy sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle class="text-base font-semibold text-sm-navy">Tambah Proposal</DialogTitle>
                <DialogDescription class="text-xs text-gray-500">
                    Proposal baru disimpan sebagai <strong>Draft</strong>. Kamu bisa mengirimnya ke sponsor
                    setelah dokumennya siap.
                </DialogDescription>
            </DialogHeader>

            <Form
                :key="formKey"
                v-bind="ProposalController.store.form()"
                v-slot="{ errors, processing }"
                class="space-y-4"
                :options="{ preserveScroll: true }"
                @success="emit('update:open', false)"
            >
                <!-- ================= Unggah PDF + ekstraksi (paling atas) =================
                     Ditaruh pertama: PDF adalah sumber isian, form manual di bawah
                     hanya untuk melengkapi/mengoreksi hasil ekstraksi. -->
                <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50/70 p-3">
                    <div class="flex items-start gap-3">
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-sm-red"
                        >
                            <FileUp class="size-5" :stroke-width="2" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <label for="file" class="block text-[13px] font-medium text-sm-navy">
                                Dokumen proposal (PDF)
                            </label>
                            <p class="mt-0.5 text-[11px] leading-relaxed text-gray-500">
                                Unggah proposal, lalu klik <strong>Ekstrak dari PDF</strong> — judul, kategori,
                                lokasi, anggaran, tanggal, dan deskripsi akan terisi otomatis.
                            </p>

                            <input
                                id="file"
                                name="file"
                                type="file"
                                accept="application/pdf"
                                class="mt-2 block w-full text-[12px] text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-sm-navy file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-white hover:file:bg-sm-card"
                                @change="onFileChange"
                            />

                            <div v-if="selectedFile" class="mt-1.5 flex items-center gap-1 text-[11px] text-emerald-600">
                                <FileUp class="size-3.5 shrink-0" />
                                <span class="truncate">{{ selectedFile.name }}</span>
                                <span class="shrink-0 text-emerald-600/70">· {{ formatBytes(selectedFile.size) }}</span>
                            </div>

                            <button
                                type="button"
                                :disabled="!selectedFile || extracting"
                                class="mt-2 flex h-8 w-full items-center justify-center gap-1.5 rounded-lg border border-sm-navy/20 bg-white px-3 text-[12px] font-medium text-sm-navy transition-colors hover:border-sm-navy/40 hover:bg-sm-navy/5 disabled:cursor-not-allowed disabled:opacity-50"
                                @click="extractFromPdf"
                            >
                                <LoaderCircle v-if="extracting" class="size-3.5 animate-spin" />
                                <ScanText v-else class="size-3.5" />
                                <template v-if="extracting">Membaca dokumen...</template>
                                <template v-else-if="!selectedFile">Pilih PDF dulu untuk ekstrak</template>
                                <template v-else>Ekstrak dari PDF</template>
                            </button>

                            <!-- Hasil ekstraksi: daftar bagian yang berhasil diisi -->
                            <div
                                v-if="extractedLabels.length"
                                class="mt-2 rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-1.5"
                            >
                                <p class="text-[11px] font-medium text-emerald-700">
                                    <template v-if="extractedByAi">Hasil ekstraksi AI · </template>
                                    {{ extractedLabels.length }} bagian terisi otomatis. Periksa lagi sebelum menyimpan.
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

                            <!-- Pesan gagal (mis. PDF hasil scan / sesi habis) -->
                            <p
                                v-if="extractError"
                                class="mt-2 flex items-start gap-1 rounded-lg border border-amber-200 bg-amber-50 px-2 py-1.5 text-[11px] leading-relaxed text-amber-700"
                            >
                                <CircleAlert class="mt-px size-3.5 shrink-0" />
                                {{ extractError }}
                            </p>

                            <p class="mt-1.5 flex items-center gap-1 text-[10px] text-gray-400">
                                <ScanText class="size-3" />
                                Maks. 5 MB · PDF berbasis teks (bukan hasil scan)
                            </p>

                            <InputError :message="errors.file" class="mt-1" />
                        </div>
                    </div>
                </div>

                <!-- Pemisah: dokumen di atas, isian manual di bawah -->
                <div class="relative">
                    <div class="absolute inset-0 flex items-center" aria-hidden="true">
                        <span class="w-full border-t border-gray-100"></span>
                    </div>
                    <div class="relative flex justify-center">
                        <span class="bg-white px-2 text-[10px] font-medium uppercase tracking-wide text-gray-400">
                            atau isi manual
                        </span>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="title" class="mb-1 block text-xs font-medium text-gray-600">
                            Judul proposal <span class="text-sm-red">*</span>
                        </label>
                        <input
                            id="title"
                            v-model="form.title"
                            name="title"
                            type="text"
                            required
                            :class="inputClass"
                            placeholder="Sponsorship Festival Musik 2026"
                        />
                        <InputError :message="errors.title" class="mt-1" />
                    </div>

                    <div>
                        <label for="idKategori" class="mb-1 block text-xs font-medium text-gray-600">
                            Kategori event <span class="text-sm-red">*</span>
                        </label>
                        <select id="idKategori" v-model="form.idKategori" name="idKategori" required :class="inputClass">
                            <option value="" disabled>Pilih kategori</option>
                            <option v-for="item in kategori" :key="item.id" :value="String(item.id)">
                                {{ item.name }}
                            </option>
                        </select>
                        <InputError :message="errors.idKategori" class="mt-1" />
                    </div>

                    <div>
                        <label for="needType" class="mb-1 block text-xs font-medium text-gray-600">
                            Jenis kebutuhan <span class="text-sm-red">*</span>
                        </label>
                        <select id="needType" v-model="form.needType" name="needType" required :class="inputClass">
                            <option v-for="type in needTypes" :key="type" :value="type">{{ type }}</option>
                        </select>
                        <InputError :message="errors.needType" class="mt-1" />
                    </div>

                    <div>
                        <label for="budgetMin" class="mb-1 block text-xs font-medium text-gray-600">
                            Anggaran minimum (Rp) <span class="text-sm-red">*</span>
                        </label>
                        <input
                            id="budgetMin"
                            :value="formatRupiah(form.budgetMin)"
                            name="budgetMin"
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            required
                            :class="inputClass"
                            placeholder="25.000.000"
                            @input="onBudgetInput('budgetMin', $event)"
                        />
                        <p class="mt-0.5 text-[10px] text-gray-400">Pemisah ribuan otomatis, contoh 25.000.000</p>
                        <InputError :message="errors.budgetMin" class="mt-1" />
                    </div>

                    <div>
                        <label for="budgetMax" class="mb-1 block text-xs font-medium text-gray-600">
                            Anggaran maksimum (Rp) <span class="text-sm-red">*</span>
                        </label>
                        <input
                            id="budgetMax"
                            :value="formatRupiah(form.budgetMax)"
                            name="budgetMax"
                            type="text"
                            inputmode="numeric"
                            autocomplete="off"
                            required
                            :class="inputClass"
                            placeholder="75.000.000"
                            @input="onBudgetInput('budgetMax', $event)"
                        />
                        <p class="mt-0.5 text-[10px] text-gray-400">Tidak boleh lebih kecil dari anggaran minimum</p>
                        <InputError :message="errors.budgetMax" class="mt-1" />
                    </div>

                    <div>
                        <label for="start_date" class="mb-1 block text-xs font-medium text-gray-600">
                            Tanggal mulai <span class="text-sm-red">*</span>
                        </label>
                        <div class="relative">
                            <input
                                id="start_date"
                                v-model="form.start_date"
                                name="start_date"
                                type="date"
                                required
                                :class="[inputClass, 'cursor-pointer pr-9 [&::-webkit-calendar-picker-indicator]:hidden']"
                                @click="openDatePicker('start_date')"
                            />
                            <button
                                type="button"
                                tabindex="-1"
                                aria-label="Buka kalender tanggal mulai"
                                class="absolute top-1/2 right-1 flex size-7 -translate-y-1/2 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-gray-100 hover:text-sm-navy"
                                @click="openDatePicker('start_date')"
                            >
                                <CalendarDays class="size-4" />
                            </button>
                        </div>
                        <InputError :message="errors.start_date" class="mt-1" />
                    </div>

                    <div>
                        <label for="end_date" class="mb-1 block text-xs font-medium text-gray-600">
                            Tanggal selesai <span class="text-sm-red">*</span>
                        </label>
                        <div class="relative">
                            <input
                                id="end_date"
                                v-model="form.end_date"
                                name="end_date"
                                type="date"
                                required
                                :min="form.start_date || undefined"
                                :class="[inputClass, 'cursor-pointer pr-9 [&::-webkit-calendar-picker-indicator]:hidden']"
                                @click="openDatePicker('end_date')"
                            />
                            <button
                                type="button"
                                tabindex="-1"
                                aria-label="Buka kalender tanggal selesai"
                                class="absolute top-1/2 right-1 flex size-7 -translate-y-1/2 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-gray-100 hover:text-sm-navy"
                                @click="openDatePicker('end_date')"
                            >
                                <CalendarDays class="size-4" />
                            </button>
                        </div>
                        <InputError :message="errors.end_date" class="mt-1" />
                    </div>

                    <div class="sm:col-span-2">
                        <label for="location" class="mb-1 block text-xs font-medium text-gray-600">
                            Lokasi event <span class="text-sm-red">*</span>
                        </label>
                        <input
                            id="location"
                            v-model="form.location"
                            name="location"
                            type="text"
                            required
                            :class="inputClass"
                            placeholder="Jakarta Convention Center"
                        />
                        <InputError :message="errors.location" class="mt-1" />
                    </div>

                    <div class="sm:col-span-2">
                        <label for="target_audience" class="mb-1 block text-xs font-medium text-gray-600">
                            Target audiens <span class="text-sm-red">*</span>
                        </label>
                        <textarea
                            id="target_audience"
                            v-model="form.target_audience"
                            name="target_audience"
                            rows="2"
                            required
                            :class="textareaClass"
                            placeholder="Mahasiswa dan profesional muda 18-30 tahun"
                        ></textarea>
                        <InputError :message="errors.target_audience" class="mt-1" />
                    </div>

                    <div class="sm:col-span-2">
                        <label for="desctiption" class="mb-1 block text-xs font-medium text-gray-600">
                            Deskripsi proposal <span class="text-sm-red">*</span>
                        </label>
                        <textarea
                            id="desctiption"
                            v-model="form.desctiption"
                            name="desctiption"
                            rows="3"
                            required
                            :class="textareaClass"
                            placeholder="Jelaskan konsep acara dan peluang sponsorship..."
                        ></textarea>
                        <InputError :message="errors.desctiption" class="mt-1" />
                    </div>

                    <div class="sm:col-span-2">
                        <label for="benefits" class="mb-1 block text-xs font-medium text-gray-600">
                            Benefit untuk sponsor
                        </label>
                        <textarea
                            id="benefits"
                            v-model="form.benefits"
                            name="benefits"
                            rows="2"
                            :class="textareaClass"
                            placeholder="Logo di panggung utama, booth eksklusif, publikasi media sosial"
                        ></textarea>
                        <InputError :message="errors.benefits" class="mt-1" />
                    </div>
                </div>

                <div class="flex justify-end gap-2 border-t border-gray-100 pt-3">
                    <button
                        type="button"
                        class="h-9 rounded-lg border border-gray-200 px-3 text-[13px] font-medium text-gray-600 transition-colors hover:bg-gray-50"
                        @click="emit('update:open', false)"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        :disabled="processing"
                        class="h-9 rounded-lg bg-sm-red px-4 text-[13px] font-medium text-white transition-colors hover:bg-sm-red-dark disabled:opacity-60"
                    >
                        {{ processing ? 'Menyimpan...' : 'Simpan Draft' }}
                    </button>
                </div>
            </Form>
        </DialogContent>
    </Dialog>
</template>
