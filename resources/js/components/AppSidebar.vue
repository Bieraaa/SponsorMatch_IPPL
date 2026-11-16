<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Building2,
    CreditCard,
    FileText,
    Handshake,
    Headphones,
    History,
    LayoutGrid,
    Megaphone,
    Newspaper,
    Settings,
    Sparkles,
} from '@lucide/vue';
import { computed } from 'vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as campaignsIndex } from '@/routes/company/campaigns';
import { edit as companyProfileEdit } from '@/routes/company/profile';
import { index as companyRecommendationsIndex } from '@/routes/company/recommendations';
import { index as proposalsIndex } from '@/routes/proposals';
import { index as recommendationsIndex } from '@/routes/recommendations';
import { index as sponsorProposalsIndex } from '@/routes/sponsor/proposals';
import { edit } from '@/routes/profile';
import type { NavItem } from '@/types';

/**
 * Blok A — Sidebar aplikasi (Figma "Dashboard EO", export "Sidebar.png").
 *
 * Analisis piksel (canvas 241x814):
 * - Sidebar PUTIH, teks/ikon navy #242F49.
 * - Logo "SponsorMatch" di atas (crop dari export Figma →
 *   /assets/logo_sponsormatch_sidebar.png), lebar ~217px seperti desain.
 * - 9 menu: 7 pertama (Dashboard … Subscription) berada DI DALAM kontainer
 *   berbingkai + garis pemisah; "Pengaturan" & "Bantuan" di bawahnya
 *   tanpa bingkai. Detail class ada di NavMain.vue.
 * - Bagian bawah: avatar + nama + email (NavUser).
 *
 * Menu selain Dashboard & Pengaturan adalah fitur blok berikutnya,
 * jadi href '#' (NavMain merender <a> nonaktif untuk '#' — tidak ada
 * rute di Laravel untuk itu).
 */
const page = usePage();
const isSponsor = computed(() => page.props.auth.user?.role === 'sponsor');

/**
 * Menu "Proposal" menyesuaikan peran:
 *  - EO      → "Proposal Saya" (proposal yang ia buat)
 *  - Sponsor → "Proposal Masuk" (proposal yang dikirim EO kepadanya)
 * Posisi & gaya menu tetap sama seperti desain Figma.
 *
 * Perusahaan juga mendapat menu "Campaign Saya" dan "Profil Perusahaan".
 * Campaign opsional; profil perusahaan wajib agar bisa muncul di
 * rekomendasi sponsor EO.
 */
const proposalNavItem = computed<NavItem>(() =>
    isSponsor.value
        ? { title: 'Proposal Masuk', href: sponsorProposalsIndex(), icon: FileText }
        : { title: 'Proposal Saya', href: proposalsIndex(), icon: FileText },
);

const mainNavItems = computed<NavItem[]>(() => {
    if (isSponsor.value) {
        return [
            { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
            {
                title: 'Campaign Saya',
                href: campaignsIndex(),
                icon: Megaphone,
            },
            {
                title: 'Profil Perusahaan',
                href: companyProfileEdit(),
                icon: Building2,
            },
            {
                title: 'Rekomendasi Event',
                href: companyRecommendationsIndex(),
                icon: Sparkles,
            },
            proposalNavItem.value,
            { title: 'Penawaran', href: '#', icon: Handshake },
            { title: 'Postingan', href: '#', icon: Newspaper },
            { title: 'Riwayat Kolaborasi', href: '#', icon: History },
            { title: 'Subscription', href: '#', icon: CreditCard },
        ];
    }

    return [
        { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
        {
            title: 'Rekomendasi Sponsor',
            href: recommendationsIndex(),
            icon: Sparkles,
        },
        proposalNavItem.value,
        { title: 'Penawaran', href: '#', icon: Handshake },
        { title: 'Postingan', href: '#', icon: Megaphone },
        { title: 'Riwayat Kolaborasi', href: '#', icon: History },
        { title: 'Subscription', href: '#', icon: CreditCard },
    ];
});

const utilityNavItems: NavItem[] = [
    { title: 'Pengaturan', href: edit(), icon: Settings },
    { title: 'Bantuan', href: '#', icon: Headphones },
];
</script>

<template>
    <Sidebar
        collapsible="icon"
        variant="inset"
        class="bg-white [&>[data-sidebar=sidebar]]:bg-white"
    >
        <SidebarHeader class="px-1 pt-[14px] pb-0">
            <Link
                :href="dashboard()"
                class="block group-data-[collapsible=icon]:hidden"
            >
                <img
                    src="/assets/logo_sponsormatch_sidebar.png"
                    alt="SponsorMatch"
                    width="216"
                    height="37"
                    class="h-auto w-[216px]"
                />
            </Link>
        </SidebarHeader>

        <SidebarContent class="mt-10">
            <NavMain
                :items="mainNavItems"
                :utility-items="utilityNavItems"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
