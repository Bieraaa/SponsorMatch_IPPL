<script setup lang="ts">
import { Globe, Mail, Phone, Star } from '@lucide/vue';
import { computed } from 'vue';
import type { CompanyDetail } from '@/types/company';

/**
 * Header profil perusahaan: cover gradient, logo, nama, industri, rating,
 * dan informasi kontak. Dipakai halaman detail perusahaan.
 */
const props = defineProps<{
    company: CompanyDetail;
}>();

const initial = computed(
    () => props.company.name.trim().charAt(0).toUpperCase() || 'P',
);

const rating = computed(() => Number(props.company.rating ?? 0).toFixed(1));
</script>

<template>
    <section
        class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-[0_10px_30px_rgba(22,30,47,0.06)]"
    >
        <div
            class="h-24 bg-gradient-to-r from-sm-navy via-sm-card to-sm-red-dark"
        />

        <div class="px-5 pb-5 sm:px-7">
            <div
                class="-mt-9 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
            >
                <div class="flex items-end gap-4">
                    <div
                        class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-2xl border-4 border-white bg-gray-50 shadow-sm"
                    >
                        <img
                            v-if="company.logo_url"
                            :src="company.logo_url"
                            :alt="`Logo ${company.name}`"
                            class="h-full w-full object-contain p-2"
                        />
                        <span
                            v-else
                            class="flex h-full w-full items-center justify-center bg-sm-navy text-2xl font-bold text-white"
                        >
                            {{ initial }}
                        </span>
                    </div>

                    <div class="pb-1">
                        <h1
                            class="text-xl font-semibold text-sm-navy sm:text-2xl"
                        >
                            {{ company.name }}
                        </h1>
                        <p class="mt-1 text-xs text-sm-card/70">
                            {{
                                company.industry ||
                                'Bidang industri belum diisi'
                            }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 pb-1">
                    <span
                        class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700"
                    >
                        <Star
                            class="h-3.5 w-3.5 fill-amber-400 text-amber-400"
                        />
                        {{ rating }}
                        <span class="font-normal text-amber-700/70">
                            ({{ company.review_count }} ulasan)
                        </span>
                    </span>
                </div>
            </div>

            <div
                class="mt-5 flex flex-wrap gap-x-5 gap-y-2 text-[11px] text-sm-card/70"
            >
                <a
                    v-if="company.website"
                    :href="company.website"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-1.5 hover:text-sm-red"
                >
                    <Globe class="h-3.5 w-3.5" />
                    {{ company.website }}
                </a>

                <a
                    :href="`mailto:${company.contact_email ?? ''}`"
                    class="inline-flex items-center gap-1.5 hover:text-sm-red"
                >
                    <Mail class="h-3.5 w-3.5" />
                    {{ company.contact_email ?? '-' }}
                </a>

                <span
                    v-if="company.contact_phone"
                    class="inline-flex items-center gap-1.5"
                >
                    <Phone class="h-3.5 w-3.5" />
                    {{ company.contact_phone }}
                </span>
            </div>
        </div>
    </section>
</template>
