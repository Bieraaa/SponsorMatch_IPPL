<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        currentPage: number;
        lastPage: number;
        total: number;
        from: number | null;
        to: number | null;
        /** Kata benda yang dihitung, mis. "perusahaan" atau "proposal". */
        entityLabel?: string;
    }>(),
    {
        entityLabel: 'perusahaan',
    },
);

const emit = defineEmits<{
    change: [page: number];
}>();

type PageItem = number | 'ellipsis';

const pages = computed<PageItem[]>(() => {
    const last = Math.max(1, props.lastPage);
    const current = Math.min(Math.max(1, props.currentPage), last);

    if (last <= 7) {
        return Array.from({ length: last }, (_, index) => index + 1);
    }

    const items: PageItem[] = [1];
    const start = Math.max(2, current - 1);
    const end = Math.min(last - 1, current + 1);

    if (start > 2) {
        items.push('ellipsis');
    }

    for (let page = start; page <= end; page += 1) {
        items.push(page);
    }

    if (end < last - 1) {
        items.push('ellipsis');
    }

    items.push(last);

    return items;
});

function go(page: number): void {
    if (page < 1 || page > props.lastPage || page === props.currentPage) {
        return;
    }

    emit('change', page);
}
</script>

<template>
    <nav
        class="mt-6 flex flex-col items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white px-4 py-3 shadow-sm sm:flex-row"
        aria-label="Navigasi halaman rekomendasi"
    >
        <p class="text-xs text-sm-card/70">
            <template v-if="total > 0">
                Menampilkan {{ from ?? 0 }}–{{ to ?? 0 }} dari {{ total }} {{ entityLabel }}
            </template>
            <template v-else>Belum ada {{ entityLabel }} untuk ditampilkan</template>
        </p>

        <div class="flex items-center gap-1.5">
            <button
                type="button"
                class="inline-flex h-8 items-center gap-1 rounded-lg border border-gray-200 px-2.5 text-xs font-medium text-sm-navy transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="currentPage <= 1"
                @click="go(currentPage - 1)"
            >
                <ChevronLeft class="h-3.5 w-3.5" />
                Previous
            </button>

            <template v-for="(item, index) in pages" :key="`${item}-${index}`">
                <span
                    v-if="item === 'ellipsis'"
                    class="px-1 text-xs text-sm-card/50"
                    aria-hidden="true"
                >
                    …
                </span>
                <button
                    v-else
                    type="button"
                    class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg border px-2 text-xs font-semibold transition-colors"
                    :class="
                        item === currentPage
                            ? 'border-sm-red bg-sm-red text-white'
                            : 'border-gray-200 bg-white text-sm-navy hover:bg-gray-50'
                    "
                    :aria-current="item === currentPage ? 'page' : undefined"
                    @click="go(item)"
                >
                    {{ item }}
                </button>
            </template>

            <button
                type="button"
                class="inline-flex h-8 items-center gap-1 rounded-lg border border-gray-200 px-2.5 text-xs font-medium text-sm-navy transition-colors hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="currentPage >= lastPage"
                @click="go(currentPage + 1)"
            >
                Next
                <ChevronRight class="h-3.5 w-3.5" />
            </button>
        </div>
    </nav>
</template>
