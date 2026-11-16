<script setup lang="ts">
import { computed } from 'vue';
import type { CompanyTab } from '@/types/company';

/**
 * Tab halaman detail perusahaan: Profil, Campaign, Postingan.
 *
 * Tab Campaign tetap ditampilkan meski perusahaan belum punya campaign
 * aktif — di dalamnya ada empty state yang menegaskan proposal tetap bisa
 * dikirim berdasarkan profil.
 */
defineProps<{
    active: CompanyTab;
    campaignCount: number;
}>();

const emit = defineEmits<{
    'update:active': [tab: CompanyTab];
}>();

const tabs = computed<{ key: CompanyTab; label: string }[]>(() => [
    { key: 'profile', label: 'Profil' },
    { key: 'campaign', label: 'Campaign' },
    { key: 'posts', label: 'Postingan' },
]);
</script>

<template>
    <div class="flex overflow-x-auto border-b border-gray-200 px-4 sm:px-6">
        <button
            v-for="tab in tabs"
            :key="tab.key"
            type="button"
            class="relative shrink-0 px-4 py-3.5 text-xs font-semibold transition-colors"
            :class="
                active === tab.key
                    ? 'text-sm-red'
                    : 'text-sm-card/60 hover:text-sm-navy'
            "
            :aria-selected="active === tab.key"
            role="tab"
            @click="emit('update:active', tab.key)"
        >
            {{ tab.label }}
            <span
                v-if="tab.key === 'campaign' && campaignCount > 0"
                class="ml-1 rounded-full bg-red-50 px-1.5 py-0.5 text-[10px] text-sm-red"
            >
                {{ campaignCount }}
            </span>
            <span
                v-if="active === tab.key"
                class="absolute right-4 bottom-0 left-4 h-0.5 rounded-full bg-sm-red"
            />
        </button>
    </div>
</template>
