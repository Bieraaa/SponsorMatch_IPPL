<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar" class="h-svh overflow-hidden">
        <AppSidebar />
        <AppContent variant="sidebar" class="h-svh min-w-0 overflow-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <!--
                Area konten dibuat sebagai satu-satunya scroll container.
                Header berada di luar area ini sehingga search bar selalu
                menempel di atas dan tidak ikut bergeser saat halaman
                di-scroll. Atribut `scroll-region` membuat Inertia tetap
                bisa memulihkan posisi scroll saat partial reload.
            -->
            <div
                scroll-region
                class="min-h-0 flex-1 overflow-x-clip overflow-y-auto"
            >
                <slot />
            </div>
        </AppContent>
        <Toaster />
    </AppShell>
</template>
