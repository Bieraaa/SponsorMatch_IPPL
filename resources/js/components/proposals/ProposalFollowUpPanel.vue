<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ChevronRight, FileText, Handshake, RotateCcw } from '@lucide/vue';
import { computed } from 'vue';
import { index as offersIndex } from '@/routes/offers';
import { tindakLanjut } from '@/routes/proposals';
import type { FollowUpItem, FollowUpPayload } from '@/types/proposal';

/**
 * Panel "Perlu Tindak Lanjut".
 *
 * Hanya menampilkan proposal yang BENAR-BENAR butuh aksi (berdasarkan
 * data nyata: penawaran pending/counter, atau belum ada follow-up
 * dalam 7 hari setelah dikirim / setelah dibuka).
 */
const props = defineProps<{
    followUps: FollowUpPayload | null | undefined;
}>();

const items = computed(() => props.followUps?.items ?? []);
const total = computed(() => props.followUps?.total ?? 0);

function iconFor(action: FollowUpItem['action']) {
    if (action === 'konfirmasi') {
        return Handshake;
    }

    if (action === 'revisi') {
        return RotateCcw;
    }

    return FileText;
}

function iconClassFor(action: FollowUpItem['action']): string {
    if (action === 'konfirmasi') {
        return 'bg-blue-50 text-blue-600';
    }

    if (action === 'revisi') {
        return 'bg-amber-50 text-amber-600';
    }

    return 'bg-gray-100 text-gray-600';
}

function followUp(item: FollowUpItem): void {
    // Penawaran pending/counter diselesaikan di halaman negosiasi, bukan
    // dengan mencatat follow-up baru.
    if (item.action === 'konfirmasi' || item.action === 'revisi') {
        router.get(offersIndex().url);

        return;
    }

    // Tombol "Tindak Lanjut" mencatat follow-up baru ke follow_up_logs.
    router.post(tindakLanjut(item.id), {}, { preserveScroll: true });
}
</script>

<template>
    <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <header class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-[15px] font-semibold text-sm-navy">
                    Perlu Tindak Lanjut
                </h2>
                <p class="mt-0.5 text-xs text-gray-500">
                    {{ total }} proposal membutuhkan tindakan
                </p>
            </div>
        </header>

        <div
            v-if="items.length === 0"
            class="mt-5 flex h-48 flex-col items-center justify-center text-center"
        >
            <div
                class="flex size-10 items-center justify-center rounded-full bg-emerald-50"
            >
                <Handshake class="size-5 text-emerald-600" />
            </div>
            <p class="mt-3 text-sm font-medium text-sm-navy">
                Semua proposal tertangani
            </p>
            <p class="mt-1 text-xs text-gray-500">
                Tidak ada proposal yang perlu tindak lanjut saat ini.
            </p>
        </div>

        <ul v-else class="mt-4 divide-y divide-gray-100">
            <li
                v-for="item in items"
                :key="item.id"
                class="flex gap-3 py-3 first:pt-0 last:pb-0"
            >
                <span
                    class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full"
                    :class="iconClassFor(item.action)"
                >
                    <component
                        :is="iconFor(item.action)"
                        class="size-4.5"
                        :stroke-width="2"
                    />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-[13px] font-medium text-sm-navy">
                        {{ item.actionLabel }}
                    </p>
                    <p class="mt-0.5 truncate text-xs text-gray-500">
                        {{ item.description }}
                    </p>
                    <button
                        type="button"
                        class="mt-2 flex items-center gap-1 text-xs font-medium text-blue-600 transition-colors hover:text-blue-700"
                        @click="followUp(item)"
                    >
                        Tindak Lanjut
                        <ChevronRight class="size-3.5" />
                    </button>
                </div>
            </li>
        </ul>
    </section>
</template>
