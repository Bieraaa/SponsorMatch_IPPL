<script setup lang="ts">
import { Megaphone } from '@lucide/vue';

/**
 * Empty state untuk tab Campaign.
 *
 * Memakai teks yang sama di halaman detail perusahaan dan halaman
 * pengelolaan campaign, dengan penegasan bahwa campaign bersifat opsional
 * dan proposal tetap dapat dikirim berdasarkan profil perusahaan.
 */
withDefaults(
    defineProps<{
        owner?: boolean;
    }>(),
    { owner: false },
);

const emit = defineEmits<{
    create: [];
}>();
</script>

<template>
    <div
        class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-10 text-center"
    >
        <span
            class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-50 text-sm-red"
        >
            <Megaphone class="h-7 w-7" />
        </span>

        <h2 class="mt-4 text-lg font-semibold text-sm-navy">
            Belum Ada Campaign Aktif
        </h2>

        <p
            class="mx-auto mt-2 max-w-lg text-sm leading-relaxed text-sm-card/70"
        >
            <template v-if="owner">
                Campaign bersifat opsional. Perusahaan tetap dapat menerima
                proposal dari EO meski belum membuat campaign — profil
                perusahaan sudah cukup untuk mengikuti rekomendasi sponsor.
            </template>
            <template v-else>
                Perusahaan belum membuka campaign sponsorship saat ini. Namun,
                kamu tetap dapat mengirim proposal berdasarkan profil
                perusahaan.
            </template>
        </p>

        <button
            v-if="owner"
            type="button"
            class="mt-5 inline-flex h-10 items-center gap-2 rounded-lg bg-sm-red px-4 text-xs font-semibold text-white transition-colors hover:bg-sm-red-dark"
            @click="emit('create')"
        >
            <Megaphone class="h-4 w-4" />
            Buat Campaign
        </button>
    </div>
</template>
