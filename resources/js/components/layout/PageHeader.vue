<script setup lang="ts">
/**
 * Judul halaman standar: ukuran, jarak, dan warna mengikuti dashboard.
 * Tombol aksi diletakkan lewat slot `actions` agar tetap sejajar.
 */
withDefaults(
    defineProps<{
        title: string;
        description?: string;
        tone?: 'light' | 'dark';
    }>(),
    {
        description: '',
        tone: 'light',
    },
);
</script>

<template>
    <header
        class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
    >
        <div class="min-w-0">
            <h1
                class="text-2xl leading-tight font-semibold lg:text-[30px]"
                :class="tone === 'dark' ? 'text-white' : 'text-sm-navy'"
            >
                {{ title }}
            </h1>
            <p
                v-if="description"
                class="mt-2 max-w-2xl text-sm leading-relaxed"
                :class="tone === 'dark' ? 'text-white/70' : 'text-sm-card/70'"
            >
                {{ description }}
            </p>
        </div>

        <div
            v-if="$slots.actions"
            class="flex shrink-0 flex-wrap items-center gap-2"
        >
            <slot name="actions" />
        </div>
    </header>
</template>
