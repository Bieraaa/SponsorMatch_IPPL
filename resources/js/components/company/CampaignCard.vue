<script setup lang="ts">
import {
    CalendarDays,
    Megaphone,
    Pencil,
    Play,
    Power,
    Target,
    Users,
    Wallet,
} from '@lucide/vue';
import { computed } from 'vue';
import type { CompanyCampaign } from '@/types/company';

/**
 * Kartu campaign perusahaan.
 *
 * Dipakai di dua tempat:
 *  - Halaman detail perusahaan (publik) → hanya campaign aktif, tanpa aksi.
 *  - Halaman "Campaign Saya" → mode `owner`, menampilkan status dan aksi
 *    edit / aktifkan / nonaktifkan / akhiri.
 */
const props = withDefaults(
    defineProps<{
        campaign: CompanyCampaign;
        owner?: boolean;
        busy?: boolean;
    }>(),
    { owner: false, busy: false },
);

const emit = defineEmits<{
    edit: [campaign: CompanyCampaign];
    status: [campaign: CompanyCampaign, status: 'active' | 'draft' | 'close'];
    end: [campaign: CompanyCampaign];
}>();

const money = (value: number) =>
    `Rp${value.toLocaleString('id-ID', { maximumFractionDigits: 0 })}`;

const formatDate = (value: string | null) =>
    value
        ? new Date(value).toLocaleDateString('id-ID', {
              day: '2-digit',
              month: 'short',
              year: 'numeric',
          })
        : '-';

const budget = computed(
    () => `${money(props.campaign.budget_min)} – ${money(props.campaign.budget_max)}`,
);

const period = computed(
    () =>
        `${formatDate(props.campaign.period_start)} – ${formatDate(
            props.campaign.period_end,
        )}`,
);

const statusLabel = computed(() => {
    if (props.campaign.status === 'active' && props.campaign.is_active) {
        return 'Aktif';
    }

    if (props.campaign.status === 'draft') {
        return 'Draft';
    }

    return 'Selesai';
});

const statusTone = computed(() => {
    if (props.campaign.status === 'active' && props.campaign.is_active) {
        return 'bg-emerald-50 text-emerald-700';
    }

    if (props.campaign.status === 'draft') {
        return 'bg-amber-50 text-amber-700';
    }

    return 'bg-gray-100 text-sm-card/70';
});

/** Campaign yang tanggal berakhirnya sudah lewat tidak bisa diaktifkan. */
const canActivate = computed(() => {
    if (!props.campaign.period_end) {
        return false;
    }

    const end = new Date(`${props.campaign.period_end}T23:59:59`);

    return end.getTime() >= Date.now();
});

const isActive = computed(
    () => props.campaign.status === 'active' && props.campaign.is_active,
);
</script>

<template>
    <article
        class="flex h-full flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"
    >
        <div class="relative h-36 shrink-0 bg-sm-navy">
            <img
                v-if="campaign.banner_url"
                :src="campaign.banner_url"
                :alt="campaign.title"
                class="h-full w-full object-cover"
            />
            <div
                v-else
                class="flex h-full w-full items-center justify-center bg-gradient-to-br from-sm-navy via-sm-card to-sm-red-dark text-white/80"
            >
                <Megaphone class="h-10 w-10" />
            </div>

            <span
                class="absolute top-3 left-3 rounded-full bg-white/95 px-2.5 py-1 text-[10px] font-semibold text-sm-navy shadow-sm"
            >
                {{ campaign.category || 'Campaign' }}
            </span>
        </div>

        <div class="flex flex-1 flex-col p-5">
            <div class="flex items-start justify-between gap-3">
                <h3 class="text-base font-semibold text-sm-navy">
                    {{ campaign.title }}
                </h3>
                <span
                    class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-semibold"
                    :class="statusTone"
                >
                    {{ statusLabel }}
                </span>
            </div>

            <p class="mt-2 line-clamp-3 text-xs leading-relaxed text-sm-card/75">
                {{ campaign.description }}
            </p>

            <div class="mt-4 space-y-2 text-[11px] text-sm-card/75">
                <p class="flex items-start gap-2">
                    <Target class="mt-0.5 h-3.5 w-3.5 shrink-0 text-sm-red" />
                    <span>
                        <strong class="text-sm-navy">Tujuan:</strong>
                        {{ campaign.purpose || '-' }}
                    </span>
                </p>
                <p class="flex items-start gap-2">
                    <Users class="mt-0.5 h-3.5 w-3.5 shrink-0 text-sm-red" />
                    <span>
                        <strong class="text-sm-navy">Target audiens:</strong>
                        {{ campaign.target_audience || '-' }}
                    </span>
                </p>
                <p class="flex items-start gap-2">
                    <Wallet class="mt-0.5 h-3.5 w-3.5 shrink-0 text-sm-red" />
                    <span>
                        <strong class="text-sm-navy">Anggaran:</strong>
                        {{ budget }}
                    </span>
                </p>
                <p class="flex items-start gap-2">
                    <CalendarDays
                        class="mt-0.5 h-3.5 w-3.5 shrink-0 text-sm-red"
                    />
                    <span>{{ period }}</span>
                </p>
            </div>

            <div class="mt-4 rounded-lg bg-gray-50 p-3">
                <p
                    class="text-[10px] font-semibold tracking-wide text-sm-card/55 uppercase"
                >
                    Jenis dukungan & benefit
                </p>
                <p class="mt-1 text-xs font-medium text-sm-navy">
                    {{ campaign.support_type || 'Dana' }}
                </p>
                <p class="mt-1 line-clamp-2 text-[11px] leading-relaxed text-sm-card/70">
                    {{ campaign.benefits || 'Benefit campaign belum diisi.' }}
                </p>
            </div>

            <p
                v-if="owner && campaign.proposal_count !== null"
                class="mt-3 text-[10px] text-sm-card/50"
            >
                {{ campaign.proposal_count ?? 0 }} proposal masuk ke campaign ini
            </p>

            <!-- Aksi pemilik campaign -->
            <div
                v-if="owner"
                class="mt-4 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-4"
            >
                <button
                    type="button"
                    class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-gray-200 px-3 text-[11px] font-semibold text-sm-navy transition-colors hover:bg-gray-50 disabled:opacity-50"
                    :disabled="busy"
                    @click="emit('edit', campaign)"
                >
                    <Pencil class="h-3.5 w-3.5" />
                    Edit
                </button>

                <button
                    v-if="!isActive && campaign.status !== 'close'"
                    type="button"
                    class="inline-flex h-8 items-center gap-1.5 rounded-lg bg-emerald-600 px-3 text-[11px] font-semibold text-white transition-colors hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="busy || !canActivate"
                    :title="
                        canActivate
                            ? 'Aktifkan campaign'
                            : 'Tanggal berakhir campaign sudah lewat'
                    "
                    @click="emit('status', campaign, 'active')"
                >
                    <Play class="h-3.5 w-3.5" />
                    Aktifkan
                </button>

                <button
                    v-if="isActive"
                    type="button"
                    class="inline-flex h-8 items-center gap-1.5 rounded-lg border border-amber-200 px-3 text-[11px] font-semibold text-amber-700 transition-colors hover:bg-amber-50 disabled:opacity-50"
                    :disabled="busy"
                    @click="emit('status', campaign, 'draft')"
                >
                    <Power class="h-3.5 w-3.5" />
                    Nonaktifkan
                </button>

                <button
                    v-if="campaign.status !== 'close'"
                    type="button"
                    class="ml-auto inline-flex h-8 items-center gap-1.5 rounded-lg border border-red-200 px-3 text-[11px] font-semibold text-sm-red transition-colors hover:bg-red-50 disabled:opacity-50"
                    :disabled="busy"
                    @click="emit('end', campaign)"
                >
                    Akhiri
                </button>
            </div>
        </div>
    </article>
</template>
