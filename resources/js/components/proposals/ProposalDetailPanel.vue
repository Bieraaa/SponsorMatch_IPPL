<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import ProposalDetailContent from '@/components/proposals/ProposalDetailContent.vue';
import {
    dokumen,
    publish as publishRoute,
    send as sendForm,
    tindakLanjut,
} from '@/routes/proposals';
import type { ProposalDetail, ProposalStatusKey } from '@/types/proposal';

/**
 * Panel detail proposal.
 *
 * Desktop (≥1280px): kolom sticky di samping tabel.
 * Mobile/tablet    : drawer (bottom sheet) dengan backdrop.
 *
 * Hanya SATU versi yang dirender (bukan dua-duanya) supaya tidak ada
 * heading/tombol duplikat di DOM.
 */
const props = defineProps<{
    detail: ProposalDetail | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const isDesktop = useMediaQuery('(min-width: 1280px)');

const statusStyle: Record<ProposalStatusKey, string> = {
    draft: 'bg-gray-100 text-gray-700 border-gray-200',
    ready: 'bg-violet-50 text-violet-700 border-violet-200',
    sent: 'bg-blue-50 text-blue-700 border-blue-200',
    waiting: 'bg-amber-50 text-amber-700 border-amber-200',
    approved: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    rejected: 'bg-red-50 text-sm-red border-red-200',
};

const budgetLabel = computed(() => {
    if (!props.detail) {
        return '';
    }

    const { budgetMin, budgetMax } = props.detail.offer;
    const format = (value: number) => 'Rp ' + value.toLocaleString('id-ID');

    return budgetMin === budgetMax
        ? format(budgetMin)
        : `${format(budgetMin)} – ${format(budgetMax)}`;
});

/** Laporan event hanya dibuka kalau memang ada datanya. */
const showReport = ref(false);

watch(
    () => props.detail?.id,
    () => {
        showReport.value = false;
    },
);

/** Unduh dokumen lewat route yang sudah otorisasinya dicek di backend. */
function download(): void {
    if (props.detail) {
        window.location.href = dokumen(props.detail.id).url;
    }
}

/** Terbitkan proposal draft menjadi siap dikirim. */
function publish(): void {
    if (props.detail) {
        router.post(
            publishRoute(props.detail.id),
            {},
            { preserveScroll: true },
        );
    }
}

function openSend(): void {
    if (props.detail) {
        window.location.href = sendForm(props.detail.id).url;
    }
}

/** Catat follow-up baru untuk tracking proposal ini. */
function followUp(): void {
    const trackingId = props.detail?.actions.trackingId;

    if (trackingId) {
        router.post(tindakLanjut(trackingId), {}, { preserveScroll: true });
    }
}
</script>

<template>
    <template v-if="detail">
        <!-- Desktop: kolom kanan -->
        <aside
            v-if="isDesktop"
            class="sticky top-4 self-start"
            aria-label="Detail proposal"
        >
            <div
                class="max-h-[calc(100vh-2rem)] overflow-y-auto rounded-2xl border border-gray-200 bg-white shadow-sm"
            >
                <ProposalDetailContent
                    :detail="detail"
                    :status-style="statusStyle"
                    :budget-label="budgetLabel"
                    :show-report="showReport"
                    @close="emit('close')"
                    @download="download"
                    @follow-up="followUp"
                    @toggle-report="showReport = !showReport"
                    @publish="publish"
                    @send="openSend"
                />
            </div>
        </aside>

        <!-- Mobile/tablet: drawer -->
        <div
            v-else
            class="fixed inset-0 z-50"
            role="dialog"
            aria-modal="true"
            aria-label="Detail proposal"
        >
            <div
                class="absolute inset-0 bg-sm-navy/60"
                @click="emit('close')"
            ></div>
            <div
                class="absolute inset-x-0 bottom-0 max-h-[88vh] overflow-y-auto rounded-t-2xl bg-white shadow-2xl"
            >
                <div
                    class="mx-auto mt-2 h-1 w-10 rounded-full bg-gray-200"
                ></div>
                <ProposalDetailContent
                    :detail="detail"
                    :status-style="statusStyle"
                    :budget-label="budgetLabel"
                    :show-report="showReport"
                    @close="emit('close')"
                    @download="download"
                    @follow-up="followUp"
                    @toggle-report="showReport = !showReport"
                    @publish="publish"
                    @send="openSend"
                />
            </div>
        </div>
    </template>
</template>
