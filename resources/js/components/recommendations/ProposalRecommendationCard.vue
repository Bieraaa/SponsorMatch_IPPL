<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Banknote,
    Building2,
    CalendarDays,
    CheckCircle2,
    MapPin,
    Megaphone,
    Send,
    Sparkles,
} from '@lucide/vue';
import { computed } from 'vue';
import type { ProposalRecommendationItem } from '@/types/recommendation';

/**
 * Kartu rekomendasi proposal EO untuk perusahaan.
 *
 * Tombol "Ajukan Penawaran" hanya memicu event; halaman induk yang
 * mengirim request ke backend (membuat tracking lalu membuka alur
 * penawaran sponsor yang sudah ada).
 */
const props = defineProps<{
    recommendation: ProposalRecommendationItem;
}>();

const emit = defineEmits<{
    interest: [recommendation: ProposalRecommendationItem];
}>();

const scoreTone = computed(() => {
    if (props.recommendation.score >= 80) {
        return 'bg-green-100 text-green-700';
    }

    if (props.recommendation.score >= 60) {
        return 'bg-amber-100 text-amber-700';
    }

    return 'bg-red-100 text-sm-red';
});

function formatDate(value: string | null): string {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

const period = computed(() => {
    const start = formatDate(props.recommendation.start_date);
    const end = formatDate(props.recommendation.end_date);

    if (!start && !end) {
        return 'Tanggal belum ditentukan';
    }

    if (!start || !end || start === end) {
        return start || end;
    }

    return `${start} – ${end}`;
});

const budget = computed(() => {
    const min = props.recommendation.budget_min;
    const max = props.recommendation.budget_max;

    if (!min && !max) {
        return 'Belum ditentukan';
    }

    const format = (value: number) => `Rp${value.toLocaleString('id-ID')}`;

    return min === max ? format(min) : `${format(min)} – ${format(max)}`;
});
</script>

<template>
    <article
        class="flex h-full flex-col rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition-shadow hover:shadow-md"
        data-testid="proposal-recommendation-card"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p
                    class="truncate text-[15px] font-semibold text-sm-navy"
                    :title="recommendation.title"
                >
                    {{ recommendation.title }}
                </p>
                <p
                    class="mt-0.5 flex items-center gap-1 truncate text-xs text-sm-card/65"
                >
                    <Building2 class="h-3.5 w-3.5 shrink-0" />
                    {{ recommendation.eo_name ?? 'Event Organizer' }}
                </p>
            </div>

            <span
                class="inline-flex shrink-0 items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-bold"
                :class="scoreTone"
            >
                <Sparkles class="h-3 w-3" />
                {{ recommendation.score }}% Cocok
            </span>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-2">
            <span
                v-if="recommendation.category"
                class="rounded-full bg-red-50 px-2.5 py-1 text-[11px] font-semibold text-sm-red"
            >
                {{ recommendation.category }}
            </span>
            <span
                class="rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                :class="
                    recommendation.source === 'campaign'
                        ? 'border-red-100 bg-red-50 text-sm-red'
                        : 'border-gray-200 bg-gray-50 text-sm-card'
                "
            >
                [{{ recommendation.source_label }}]
            </span>
        </div>

        <div class="mt-3 space-y-1.5 text-[11px] text-sm-card/80">
            <p class="flex items-center gap-1.5">
                <CalendarDays class="h-3.5 w-3.5 shrink-0" />
                {{ period }}
            </p>
            <p class="flex items-center gap-1.5">
                <MapPin class="h-3.5 w-3.5 shrink-0" />
                {{ recommendation.location ?? 'Lokasi belum diisi' }}
            </p>
            <p class="flex items-center gap-1.5">
                <Banknote class="h-3.5 w-3.5 shrink-0" />
                {{ budget }}
            </p>
        </div>

        <div
            v-if="recommendation.campaign"
            class="mt-3 rounded-xl border border-red-100 bg-red-50/60 p-3"
        >
            <p
                class="flex items-center gap-1 text-[10px] font-semibold tracking-wide text-sm-red uppercase"
            >
                <Megaphone class="h-3 w-3" />
                Campaign Anda
            </p>
            <p class="mt-1 line-clamp-1 text-[13px] font-semibold text-sm-navy">
                {{ recommendation.campaign.title }}
            </p>
            <p
                v-if="recommendation.campaign.purpose"
                class="mt-1 line-clamp-2 text-[11px] leading-relaxed text-sm-card/70"
            >
                {{ recommendation.campaign.purpose }}
            </p>
        </div>

        <div class="mt-3 flex-1">
            <p class="text-[11px] font-semibold text-sm-card">Alasan AI:</p>
            <p
                class="mt-0.5 line-clamp-3 text-xs leading-relaxed text-sm-card/75"
            >
                {{
                    recommendation.reason ??
                    recommendation.description ??
                    'Proposal ini belum memiliki deskripsi.'
                }}
            </p>

            <ul
                v-if="recommendation.factors.length"
                class="mt-2 space-y-1 text-[11px] leading-relaxed text-sm-card/70"
            >
                <li
                    v-for="factor in recommendation.factors"
                    :key="factor"
                    class="flex items-start gap-1.5"
                >
                    <span class="mt-1.5 size-1 shrink-0 rounded-full bg-sm-red" />
                    {{ factor }}
                </li>
            </ul>
        </div>

        <div class="mt-4 flex items-center gap-2">
            <Link
                :href="
                    recommendation.tracking_id
                        ? `/sponsor/proposals/${recommendation.tracking_id}`
                        : '/company/recommendations'
                "
                class="inline-flex h-9 flex-1 items-center justify-center rounded-lg border border-gray-200 text-xs font-semibold text-sm-navy transition-colors hover:bg-gray-50"
                :class="{ 'pointer-events-none opacity-60': !recommendation.tracking_id }"
            >
                Lihat Penawaran
            </Link>
            <button
                type="button"
                :disabled="recommendation.already_contacted"
                class="inline-flex h-9 flex-1 items-center justify-center gap-1.5 rounded-lg bg-sm-red text-xs font-semibold text-white transition-colors hover:bg-sm-red-dark disabled:cursor-not-allowed disabled:opacity-60"
                @click="emit('interest', recommendation)"
            >
                <CheckCircle2
                    v-if="recommendation.already_contacted"
                    class="h-3.5 w-3.5"
                />
                <Send v-else class="h-3.5 w-3.5" />
                {{
                    recommendation.already_contacted
                        ? 'Sudah Diajukan'
                        : 'Ajukan Penawaran'
                }}
            </button>
        </div>
    </article>
</template>
