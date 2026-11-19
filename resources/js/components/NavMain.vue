<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useSidebar } from '@/components/ui/sidebar';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { NavItem } from '@/types';

defineProps<{
    items: NavItem[];
    utilityItems?: NavItem[];
}>();

const { isCurrentUrl } = useCurrentUrl();
const { isMobile, state } = useSidebar();

/*
 * Blok A — Menu sidebar (Figma "Dashboard EO", export "Sidebar.png" 241x814).
 *
 * Hasil analisis piksel yang dipakai untuk class di bawah:
 * - Kontainer 7 menu pertama: border kiri & kanan 1px #E6E6E6, border bawah
 *   1px #D9D9D9, TIDAK ada border atas, sudut atas 12px, sudut bawah 4px.
 * - Tinggi baris: menu 1 = 30px (konten menempel atas), menu 2-7 = 44px.
 * - Pemisah antar baris: garis 1px #CCCCCC + gradasi lembut ke bawah 5px
 *   (#D8D8D8 → #E6E6E6 → #F3F3F3 → #FBFBFB → transparan).
 * - "Pengaturan" & "Bantuan" DI LUAR kontainer (tanpa garis), jarak 30px.
 * - Ikon 24px (ink ±18px) navy #242F49, teks 13px navy #242F49.
 *   Ikon mulai x=25 (dari dalam kontainer), teks mulai x=55.
 *
 * Semua teks menu bewarna NAVY (#242F49) sesuai desain Figma — termasuk
 * item yang sedang aktif. Agar halaman aktif tetap terbaca, item aktif
 * diberi tanda garis aksen merah 3px di sisi kirinya (bukan perubahan
 * warna teks).
 * Item href '#' (fitur blok berikutnya) dirender sebagai <a> nonaktif.
 */
const containerCls = [
    'mx-1 flex flex-col rounded-t-[12px] rounded-b-[4px]',
    'border-x border-[#E6E6E6] border-b-[#D9D9D9]',
    // 5px jarak atas + 10px jarak bawah (hasil ukur piksel Figma)
    'pt-[5px] pb-[10px] shadow-[0_1px_3px_rgba(22,30,47,0.06)]',
    'group-data-[collapsible=icon]:mx-0 group-data-[collapsible=icon]:border-0',
    'group-data-[collapsible=icon]:pt-0 group-data-[collapsible=icon]:pb-0',
    'group-data-[collapsible=icon]:shadow-none',
].join(' ');

const rowCls = [
    'relative flex w-full items-center gap-1.5 px-3',
    'text-[13px] leading-5 text-sm-card transition-colors',
    'hover:bg-sm-navy/[0.04]',
    'group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-0',
].join(' ');

const dividerCls =
    'pointer-events-none absolute inset-x-0 top-full h-[5px] bg-[linear-gradient(to_bottom,#CCCCCC_0_1px,#D8D8D8_1px,#E6E6E6_2px,#F3F3F3_3px,#FBFBFB_4px,transparent_5px)]';

const labelCls = 'truncate group-data-[collapsible=icon]:hidden';

/** Tanda halaman aktif: garis aksen merah 3px di sisi kiri baris. */
const activeBarCls =
    'pointer-events-none absolute inset-y-0 left-0 w-[3px] bg-sm-red';

/** Baris pertama lebih pendek (30px) & kontennya menempel atas (hasil ukur Figma). */
const rowHeight = (index: number) =>
    index === 0 ? 'h-[30px] items-start' : 'h-11';
</script>

<template>
    <div data-slot="sidebar-group" class="flex w-full min-w-0 flex-col">
        <!-- 7 menu utama: di dalam kontainer berbingkai + garis pemisah -->
        <nav aria-label="Navigasi utama" :class="containerCls">
            <template v-for="(item, index) in items" :key="item.title">
                <Tooltip>
                    <TooltipTrigger as-child>
                        <a
                            v-if="item.href === '#'"
                            href="#"
                            :class="[rowCls, rowHeight(index)]"
                            :title="`${item.title} — segera hadir`"
                            @click.prevent
                        >
                            <component
                                :is="item.icon"
                                class="size-6 shrink-0"
                            />
                            <span :class="labelCls">{{ item.title }}</span>
                            <span :class="dividerCls" aria-hidden="true" />
                        </a>
                        <Link
                            v-else
                            :href="item.href"
                            :class="[rowCls, rowHeight(index)]"
                        >
                            <component
                                :is="item.icon"
                                class="size-6 shrink-0"
                            />
                            <span :class="labelCls">{{ item.title }}</span>
                            <span
                                v-if="isCurrentUrl(item.href)"
                                :class="activeBarCls"
                                aria-hidden="true"
                            />
                            <span :class="dividerCls" aria-hidden="true" />
                        </Link>
                    </TooltipTrigger>
                    <TooltipContent
                        side="right"
                        align="center"
                        :hidden="state !== 'collapsed' || isMobile"
                    >
                        {{ item.title }}
                    </TooltipContent>
                </Tooltip>
            </template>
        </nav>

        <!-- Pengaturan & Bantuan: di bawah kontainer, tanpa bingkai/garis -->
        <div class="mx-1 mt-1 flex flex-col group-data-[collapsible=icon]:mx-0">
            <template v-for="item in utilityItems ?? []" :key="item.title">
                <Tooltip>
                    <TooltipTrigger as-child>
                        <a
                            v-if="item.href === '#'"
                            href="#"
                            :class="[rowCls, 'h-[30px]']"
                            :title="`${item.title} — segera hadir`"
                            @click.prevent
                        >
                            <component
                                :is="item.icon"
                                class="size-6 shrink-0"
                            />
                            <span :class="labelCls">{{ item.title }}</span>
                        </a>
                        <Link
                            v-else
                            :href="item.href"
                            :class="[rowCls, 'h-[30px]']"
                        >
                            <component
                                :is="item.icon"
                                class="size-6 shrink-0"
                            />
                            <span :class="labelCls">{{ item.title }}</span>
                            <span
                                v-if="isCurrentUrl(item.href)"
                                :class="activeBarCls"
                                aria-hidden="true"
                            />
                        </Link>
                    </TooltipTrigger>
                    <TooltipContent
                        side="right"
                        align="center"
                        :hidden="state !== 'collapsed' || isMobile"
                    >
                        {{ item.title }}
                    </TooltipContent>
                </Tooltip>
            </template>
        </div>
    </div>
</template>
