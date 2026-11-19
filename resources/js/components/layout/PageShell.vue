<script setup lang="ts">
/**
 * Kerangka halaman standar untuk area aplikasi (EO & perusahaan).
 *
 * Semua halaman memakai lebar konten yang sama (1180px) dan padding yang
 * sama agar perpindahan antarhalaman tidak terasa "melompat".
 */
withDefaults(
    defineProps<{
        tone?: 'light' | 'muted' | 'dark';
        contentClass?: string;
    }>(),
    {
        tone: 'light',
        contentClass: '',
    },
);

const tones: Record<'light' | 'muted' | 'dark', string> = {
    light: 'bg-[#F7F8FA]',
    muted: 'bg-[#F7F8FA]',
    dark: 'bg-sm-navy',
};
</script>

<template>
    <div class="min-h-full pb-10" :class="tones[tone]">
        <div
            class="mx-auto w-full max-w-[1180px] px-4 py-6 xl:px-0"
            :class="contentClass"
        >
            <slot />
        </div>
    </div>
</template>
