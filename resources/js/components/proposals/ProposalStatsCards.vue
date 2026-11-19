<script setup lang="ts">
import { Check, Clock, Send, TrendingDown, TrendingUp, X } from '@lucide/vue';
import { computed } from 'vue';
import type { Component } from 'vue';
import type { ProposalStat, ProposalStatKey } from '@/types/proposal';

/**
 * Empat kartu statistik proposal.
 *
 * Data (jumlah + perubahan vs periode sebelumnya) dikirim dari backend
 * sebagai `ProposalStat[]`, jadi komponen ini murni presentasi.
 */
const props = defineProps<{
    stats: ProposalStat[];
}>();

type Accent = {
    icon: Component;
    iconClass: string;
    badgeClass: string;
};

const accents: Record<ProposalStatKey, Accent> = {
    sent: {
        icon: Send,
        iconClass: 'bg-blue-50 text-blue-600',
        badgeClass: 'bg-blue-50 text-blue-700',
    },
    approved: {
        icon: Check,
        iconClass: 'bg-emerald-50 text-emerald-600',
        badgeClass: 'bg-emerald-50 text-emerald-700',
    },
    waiting: {
        icon: Clock,
        iconClass: 'bg-amber-50 text-amber-600',
        badgeClass: 'bg-amber-50 text-amber-700',
    },
    rejected: {
        icon: X,
        iconClass: 'bg-red-50 text-sm-red',
        badgeClass: 'bg-red-50 text-sm-red',
    },
};

const cards = computed(() =>
    props.stats.map((stat) => ({
        ...stat,
        ...(accents[stat.key] ?? accents.sent),
    })),
);

/** Teks indikator perubahan (arah + persentase). */
function changeText(stat: ProposalStat): string {
    if (stat.change === null) {
        return 'Belum ada pembanding';
    }

    const sign = stat.change > 0 ? '+' : '';

    return `${sign}${stat.change}% dari periode sebelumnya`;
}
</script>

<template>
    <section
        aria-label="Statistik proposal"
        class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4"
    >
        <article
            v-for="card in cards"
            :key="card.key"
            class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition-shadow hover:shadow-md"
        >
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="truncate text-[13px] font-medium text-gray-500">
                        {{ card.label }}
                    </p>
                    <p
                        class="mt-1.5 text-[28px] leading-none font-semibold text-sm-navy"
                    >
                        {{ card.value }}
                    </p>
                </div>
                <span
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl"
                    :class="card.iconClass"
                >
                    <component
                        :is="card.icon"
                        class="size-5"
                        :stroke-width="2"
                    />
                </span>
            </div>

            <p
                class="mt-3 flex items-center gap-1 text-[11px] font-medium"
                :class="
                    card.change === null
                        ? 'text-gray-400'
                        : card.change >= 0
                          ? 'text-emerald-600'
                          : 'text-sm-red'
                "
            >
                <TrendingUp
                    v-if="card.change !== null && card.change > 0"
                    class="size-3.5"
                />
                <TrendingDown
                    v-else-if="card.change !== null && card.change < 0"
                    class="size-3.5"
                />
                <span>{{ changeText(card) }}</span>
            </p>
        </article>
    </section>
</template>
