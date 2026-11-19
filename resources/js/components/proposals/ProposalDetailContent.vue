<script setup lang="ts">
import {
    Building2,
    CalendarDays,
    Download,
    FileText,
    History,
    MapPin,
    Target,
    Users,
    Wallet,
    X,
} from '@lucide/vue';
import type { ProposalDetail, ProposalStatusKey } from '@/types/proposal';

/**
 * Isi panel detail proposal.
 *
 * Dipakai dua kali: di dalam drawer (mobile) dan di kolom kanan
 * (desktop), supaya markup tidak terduplikasi.
 */
defineProps<{
    detail: ProposalDetail;
    statusStyle: Record<ProposalStatusKey, string>;
    budgetLabel: string;
    showReport: boolean;
}>();

const emit = defineEmits<{
    close: [];
    download: [];
    followUp: [];
    toggleReport: [];
    publish: [];
    send: [];
}>();

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function formatDateTime(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatRupiah(value: number): string {
    return 'Rp ' + value.toLocaleString('id-ID');
}

function formatBytes(size: number | null): string {
    if (size === null) {
        return 'Belum tersedia';
    }

    if (size < 1024) {
        return `${size} B`;
    }

    if (size < 1024 * 1024) {
        return `${(size / 1024).toFixed(1)} KB`;
    }

    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
}
</script>

<template>
    <div class="p-5">
        <!-- Header -->
        <header class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h2 class="text-[15px] font-semibold text-sm-navy">
                    Detail Proposal
                </h2>
                <p
                    class="text-[10px] font-medium tracking-wide text-gray-400 uppercase"
                >
                    Proposal #{{ detail.nomor }}
                </p>
                <p class="mt-1 text-sm leading-snug font-medium text-sm-navy">
                    {{ detail.title }}
                </p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <span
                    class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-medium"
                    :class="statusStyle[detail.status]"
                >
                    {{ detail.statusLabel }}
                </span>
                <button
                    type="button"
                    class="inline-flex size-7 items-center justify-center rounded-md text-gray-400 transition-colors hover:bg-gray-100 hover:text-sm-navy"
                    aria-label="Tutup detail proposal"
                    @click="emit('close')"
                >
                    <X class="size-4" />
                </button>
            </div>
        </header>

        <!-- Informasi Event -->
        <section class="mt-5">
            <h3
                class="flex items-center gap-1.5 text-[13px] font-semibold text-sm-navy"
            >
                <CalendarDays class="size-4 text-gray-400" />
                Informasi Event
            </h3>

            <div class="mt-3 flex gap-3">
                <div
                    class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-sm-navy text-white"
                    aria-hidden="true"
                >
                    <CalendarDays class="size-6" />
                </div>
                <div class="min-w-0 text-[12px]">
                    <p class="truncate text-[13px] font-medium text-sm-navy">
                        {{ detail.event.name }}
                    </p>
                    <p class="mt-1 text-gray-500">
                        {{ detail.event.kategori ?? '-' }}
                    </p>
                    <dl class="mt-2 space-y-1 text-gray-600">
                        <div class="flex items-center gap-1.5">
                            <CalendarDays
                                class="size-3.5 shrink-0 text-gray-400"
                            />
                            <dd>{{ formatDate(detail.event.date) }}</dd>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <MapPin class="size-3.5 shrink-0 text-gray-400" />
                            <dd class="truncate">
                                {{ detail.event.location }}
                            </dd>
                        </div>
                        <div
                            v-if="detail.event.estimatedParticipant"
                            class="flex items-center gap-1.5"
                        >
                            <Users class="size-3.5 shrink-0 text-gray-400" />
                            <dd>
                                {{
                                    detail.event.estimatedParticipant.toLocaleString(
                                        'id-ID',
                                    )
                                }}
                                peserta
                            </dd>
                        </div>
                        <div class="flex items-start gap-1.5">
                            <Target class="size-3.5 shrink-0 text-gray-400" />
                            <dd class="text-gray-500">
                                {{ detail.event.targetAudience }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </section>

        <!-- Sponsor Tujuan -->
        <section class="mt-5 border-t border-gray-100 pt-4">
            <h3
                class="flex items-center gap-1.5 text-[13px] font-semibold text-sm-navy"
            >
                <Building2 class="size-4 text-gray-400" />
                Sponsor Tujuan
            </h3>

            <div class="mt-3 flex items-center gap-3">
                <span
                    v-if="detail.sponsor.name"
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-sm-card text-xs font-semibold text-white"
                >
                    {{ detail.sponsor.initials }}
                </span>
                <span
                    v-else
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-400"
                    aria-hidden="true"
                >
                    <Building2 class="size-4" />
                </span>
                <div class="min-w-0">
                    <p class="truncate text-[13px] font-medium text-sm-navy">
                        {{ detail.sponsor.name ?? 'Belum ada sponsor' }}
                    </p>
                    <p class="text-[11px] text-gray-500">
                        {{ detail.sponsor.industry ?? '-' }}
                    </p>
                </div>
            </div>
        </section>

        <!-- Penawaran Sponsorship -->
        <section class="mt-5 border-t border-gray-100 pt-4">
            <h3
                class="flex items-center gap-1.5 text-[13px] font-semibold text-sm-navy"
            >
                <Wallet class="size-4 text-gray-400" />
                Penawaran Sponsorship
            </h3>

            <p class="mt-3 text-sm font-semibold text-sm-navy">
                {{ budgetLabel }}
            </p>
            <p
                v-if="detail.offer.amount"
                class="mt-0.5 text-[11px] text-gray-500"
            >
                Penawaran sponsor: {{ formatRupiah(detail.offer.amount) }}
                <span v-if="detail.offer.supportType">
                    ({{ detail.offer.supportType }})</span
                >
            </p>

            <ul v-if="detail.offer.benefits.length" class="mt-3 space-y-1.5">
                <li
                    v-for="benefit in detail.offer.benefits"
                    :key="benefit"
                    class="flex items-start gap-2 text-[12px] text-gray-600"
                >
                    <span
                        class="mt-1.5 size-1.5 shrink-0 rounded-full bg-sm-red"
                    ></span>
                    <span>{{ benefit }}</span>
                </li>
            </ul>
            <p v-else class="mt-3 text-[12px] text-gray-400">
                Belum ada benefit yang dicantumkan.
            </p>
        </section>

        <!-- Dokumen Proposal -->
        <section class="mt-5 border-t border-gray-100 pt-4">
            <h3
                class="flex items-center gap-1.5 text-[13px] font-semibold text-sm-navy"
            >
                <FileText class="size-4 text-gray-400" />
                Dokumen Proposal
            </h3>

            <div
                class="mt-3 flex items-center gap-3 rounded-xl border border-gray-200 p-3"
            >
                <span
                    class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-gray-100"
                >
                    <FileText class="size-4 text-gray-500" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-[12px] font-medium text-sm-navy">
                        {{ detail.document.fileName }}
                    </p>
                    <p class="text-[11px] text-gray-500">
                        {{ formatBytes(detail.document.size) }}
                        <span v-if="detail.document.uploadedAt">
                            · {{ formatDate(detail.document.uploadedAt) }}
                        </span>
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center gap-1 rounded-lg border border-gray-200 px-2 py-1 text-[11px] font-medium text-sm-navy transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:text-gray-300"
                    :disabled="!detail.document.available"
                    @click="emit('download')"
                >
                    <Download class="size-3.5" />
                    Unduh
                </button>
            </div>
        </section>

        <!-- Riwayat Status -->
        <section class="mt-5 border-t border-gray-100 pt-4">
            <h3
                class="flex items-center gap-1.5 text-[13px] font-semibold text-sm-navy"
            >
                <History class="size-4 text-gray-400" />
                Riwayat Status
            </h3>

            <ol v-if="detail.timeline.length" class="mt-3 space-y-3">
                <li
                    v-for="(entry, index) in detail.timeline"
                    :key="index"
                    class="flex gap-3"
                >
                    <span class="relative flex flex-col items-center">
                        <span
                            class="mt-1 size-2 rounded-full bg-sm-navy"
                        ></span>
                        <span
                            v-if="index < detail.timeline.length - 1"
                            class="absolute top-3 h-full w-px bg-gray-200"
                        ></span>
                    </span>
                    <div class="min-w-0 pb-1">
                        <p class="text-[12px] font-medium text-sm-navy">
                            {{ entry.label }}
                        </p>
                        <p class="text-[11px] text-gray-500">
                            {{ entry.description }}
                        </p>
                        <p class="mt-0.5 text-[10px] text-gray-400">
                            {{ formatDateTime(entry.at) }}
                        </p>
                    </div>
                </li>
            </ol>
            <p v-else class="mt-3 text-[12px] text-gray-400">
                Belum ada riwayat aktivitas.
            </p>
        </section>

        <!-- Laporan -->
        <section
            v-if="detail.report"
            class="mt-5 border-t border-gray-100 pt-4"
        >
            <button
                type="button"
                class="flex w-full items-center justify-between text-left"
                @click="emit('toggleReport')"
            >
                <span class="text-[13px] font-semibold text-sm-navy"
                    >Laporan Event</span
                >
                <span class="text-[11px] font-medium text-blue-600">
                    {{ showReport ? 'Tutup' : 'Lihat Laporan' }}
                </span>
            </button>

            <div
                v-if="showReport"
                class="mt-2 rounded-xl bg-gray-50 p-3 text-[12px] text-gray-600"
            >
                <p>{{ detail.report.description || 'Tanpa keterangan.' }}</p>
                <p class="mt-1.5 text-[11px] text-gray-500">
                    Target tercapai:
                    {{ detail.report.goalAchieved ? 'Ya' : 'Belum' }}
                    <span v-if="detail.report.submittedAt">
                        · dikirim {{ formatDate(detail.report.submittedAt) }}
                    </span>
                </p>
            </div>
        </section>

        <!-- Tombol Aksi -->
        <section
            class="mt-5 flex flex-wrap gap-2 border-t border-gray-100 pt-4"
        >
            <button
                v-if="detail.status === 'draft' && detail.document.available"
                type="button"
                class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-[13px] font-medium text-white transition-colors hover:bg-emerald-700"
                @click="emit('publish')"
            >
                Terbitkan
            </button>
            <button
                v-if="detail.status === 'draft' && !detail.document.available"
                type="button"
                disabled
                class="inline-flex flex-1 cursor-not-allowed items-center justify-center gap-1.5 rounded-lg bg-gray-100 px-3 py-2 text-[13px] font-medium text-gray-400"
                title="Unggah dokumen proposal terlebih dahulu untuk menerbitkan"
            >
                Terbitkan (perlu dokumen)
            </button>
            <button
                v-if="detail.status === 'ready'"
                type="button"
                class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-sm-navy px-3 py-2 text-[13px] font-medium text-white transition-colors hover:bg-sm-card"
                @click="emit('send')"
            >
                Kirim ke Sponsor
            </button>
            <button
                v-if="detail.actions.canFollowUp"
                type="button"
                class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-sm-navy px-3 py-2 text-[13px] font-medium text-white transition-colors hover:bg-sm-card"
                @click="emit('followUp')"
            >
                Tindak Lanjut
            </button>
            <button
                v-if="detail.report"
                type="button"
                class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-lg border border-gray-200 px-3 py-2 text-[13px] font-medium text-sm-navy transition-colors hover:bg-gray-50"
                @click="emit('toggleReport')"
            >
                Lihat Laporan
            </button>
            <p
                v-if="detail.status === 'sent' || detail.status === 'waiting'"
                class="w-full text-[11px] text-gray-400"
            >
                Menunggu respons sponsor.
            </p>
            <p
                v-if="detail.status === 'approved'"
                class="w-full text-[11px] text-emerald-600"
            >
                Proposal telah disetujui.
            </p>
            <p
                v-if="detail.status === 'rejected'"
                class="w-full text-[11px] text-sm-red"
            >
                Proposal ditolak.
            </p>
        </section>
    </div>
</template>
