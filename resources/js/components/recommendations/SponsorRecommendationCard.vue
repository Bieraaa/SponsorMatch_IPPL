<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Building2, Check, Megaphone, Send, Sparkles } from '@lucide/vue';
import { computed } from 'vue';
import type { RecommendationItem } from '@/types/recommendation';

const props = defineProps<{
    recommendation: RecommendationItem;
    selectable?: boolean;
    selected?: boolean;
}>();

const emit = defineEmits<{
    send: [recommendation: RecommendationItem];
    toggle: [companyId: number];
}>();

const initial = computed(() =>
    (props.recommendation.company_name ?? 'P').charAt(0).toUpperCase(),
);

const scoreTone = computed(() => {
    if (props.recommendation.score >= 80) {
        return 'bg-green-100 text-green-700';
    }

    if (props.recommendation.score >= 60) {
        return 'bg-amber-100 text-amber-700';
    }

    return 'bg-red-100 text-sm-red';
});

const funding = computed(() => {
    const min = props.recommendation.funding_min;
    const max = props.recommendation.funding_max;

    if (min === null && max === null) {
        return 'Belum ditentukan';
    }

    const format = (value: number | null) =>
        value === null ? '-' : `Rp ${value.toLocaleString('id-ID')}`;

    return `${format(min)} – ${format(max)}`;
});

const description = computed(
    () =>
        props.recommendation.description ??
        props.recommendation.reason ??
        'Perusahaan ini belum menambahkan deskripsi profil.',
);
</script>

<template>
    <article
        class="relative flex h-full flex-col rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition-shadow hover:shadow-md"
        data-testid="recommendation-card"
    >
        <button
            v-if="selectable && !recommendation.already_sent"
            type="button"
            class="absolute top-4 right-4 z-10 flex h-6 w-6 items-center justify-center rounded-md border transition-colors"
            :class="
                selected
                    ? 'border-sm-red bg-sm-red text-white'
                    : 'border-gray-300 bg-white text-transparent hover:border-sm-red'
            "
            :title="selected ? 'Batalkan pilihan' : 'Pilih perusahaan ini'"
            :aria-pressed="selected ? 'true' : 'false'"
            @click="emit('toggle', recommendation.company_id)"
        >
            <Check class="h-3.5 w-3.5" />
        </button>

        <div class="flex items-start gap-3 pr-8">
            <div
                class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-gray-100 bg-gray-50"
            >
                <img
                    v-if="recommendation.logo_url"
                    :src="recommendation.logo_url"
                    :alt="`Logo ${recommendation.company_name}`"
                    class="h-full w-full object-contain p-1.5"
                />
                <span v-else class="text-lg font-bold text-sm-card">
                    {{ initial }}
                </span>
            </div>

            <div class="min-w-0 flex-1">
                <Link
                    :href="recommendation.company_url"
                    class="block truncate text-[15px] font-semibold text-sm-navy hover:text-sm-red"
                >
                    {{ recommendation.company_name }}
                </Link>
                <p
                    class="mt-0.5 flex items-center gap-1 truncate text-xs text-sm-card/65"
                >
                    <Building2 class="h-3.5 w-3.5 shrink-0" />
                    {{
                        recommendation.industry ?? 'Bidang industri belum diisi'
                    }}
                </p>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-2">
            <span
                class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-bold"
                :class="scoreTone"
            >
                <Sparkles class="h-3 w-3" />
                {{ recommendation.score }}% Cocok
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

        <div
            v-if="recommendation.campaign"
            class="mt-3 rounded-xl border border-red-100 bg-red-50/60 p-3"
        >
            <p
                class="flex items-center gap-1 text-[10px] font-semibold tracking-wide text-sm-red uppercase"
            >
                <Megaphone class="h-3 w-3" />
                Campaign
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

        <div class="mt-3">
            <p class="text-[11px] font-semibold text-sm-card">
                Rentang Dukungan:
            </p>
            <p class="mt-0.5 text-sm font-medium text-sm-navy">{{ funding }}</p>
        </div>

        <div class="mt-3 flex-1">
            <p class="text-[11px] font-semibold text-sm-card">Keterangan:</p>
            <p
                class="mt-0.5 line-clamp-3 text-xs leading-relaxed text-sm-card/75"
            >
                {{ description }}
            </p>
        </div>

        <p
            v-if="recommendation.active_campaign_count > 1"
            class="mt-3 inline-flex w-fit rounded-full bg-[#FFA586]/20 px-2.5 py-1 text-[11px] font-semibold text-sm-red-dark"
        >
            {{ recommendation.active_campaign_count }} Campaign Aktif
        </p>

        <div class="mt-4 flex items-center gap-2">
            <Link
                :href="recommendation.company_url"
                class="inline-flex h-9 flex-1 items-center justify-center rounded-lg border border-gray-200 text-xs font-semibold text-sm-navy transition-colors hover:bg-gray-50"
            >
                Lihat Profil
            </Link>
            <button
                type="button"
                :disabled="recommendation.already_sent"
                class="inline-flex h-9 flex-1 items-center justify-center gap-1.5 rounded-lg bg-sm-red text-xs font-semibold text-white transition-colors hover:bg-sm-red-dark disabled:cursor-not-allowed disabled:opacity-60"
                @click="emit('send', recommendation)"
            >
                <Send class="h-3.5 w-3.5" />
                {{
                    recommendation.already_sent
                        ? 'Sudah Dikirim'
                        : 'Kirim Proposal'
                }}
            </button>
        </div>
    </article>
</template>
