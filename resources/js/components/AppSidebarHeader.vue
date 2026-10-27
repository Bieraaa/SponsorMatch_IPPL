<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Bell, Coins, Search, X } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { SidebarTrigger } from '@/components/ui/sidebar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { getInitials } from '@/composables/useInitials';
import type { BreadcrumbItem } from '@/types';
import axios from 'axios';

/**
 * Blok A — Topbar aplikasi (Figma "Dashboard EO", export 19 Okt 2026).
 *
 * Hasil analisis piksel:
 * - Bar putih tinggi 67px dengan garis pemisah tipis.
 * - Search pill LEBAR warna navy #242F49 (radius penuh), placeholder
 *   "Cari sponsor, perusahaan atau event" teks putih.
 * - Lonceng notifikasi dengan badge angka merah bila ada notifikasi belum dibaca.
 * - Chip navy berisi angka kredit ("250" pada desain) dengan ikon koin.
 * - Kanan: foto avatar + nama user + peran — nama & peran diambil dari
 *   session, bukan hardcode.
 */
const page = usePage();
const auth = computed(() => page.props.auth);
const roleName = computed(() =>
    auth.value.user?.role === 'sponsor' ? 'Perusahaan' : 'Event Organizer',
);

const unreadCount = computed(() => Number((auth.value as any).unreadNotificationsCount || 0));

type SearchResult = {
    id: number;
    name: string;
    email: string;
    role: 'eo' | 'sponsor';
    roleLabel: string;
    subtitle: string;
    url: string;
};

type Notification = {
    id: string;
    type: string;
    data: Record<string, any>;
    read_at: string | null;
    created_at: string | null;
    diff_for_humans: string | null;
};

const searchQuery = ref('');
const searchResults = ref<SearchResult[]>([]);
const searchOpen = ref(false);
const searchLoading = ref(false);
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const notifications = ref<Notification[]>([]);
const notificationsOpen = ref(false);
const notificationsLoading = ref(false);
const localUnread = ref(unreadCount.value);

watch(unreadCount, (value) => {
    localUnread.value = Number(value || 0);
});

function onSearchInput(): void {
    searchOpen.value = true;

    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    const q = searchQuery.value.trim();

    if (q === '') {
        searchResults.value = [];
        searchLoading.value = false;
        return;
    }

    searchLoading.value = true;

    searchTimeout = setTimeout(async () => {
        try {
            const response = await axios.get('/search', { params: { q } });
            searchResults.value = response.data.results ?? [];
        } catch {
            searchResults.value = [];
        } finally {
            searchLoading.value = false;
        }
    }, 250);
}

function clearSearch(): void {
    searchQuery.value = '';
    searchResults.value = [];
    searchOpen.value = false;
}

async function openNotifications(): Promise<void> {
    notificationsOpen.value = !notificationsOpen.value;

    if (notificationsOpen.value && notifications.value.length === 0) {
        await fetchNotifications();
    }
}

async function fetchNotifications(): Promise<void> {
    notificationsLoading.value = true;

    try {
        const response = await axios.get('/notifications');
        notifications.value = response.data.notifications ?? [];
        localUnread.value = Number(response.data.unread_count ?? 0);
    } catch {
        notifications.value = [];
    } finally {
        notificationsLoading.value = false;
    }
}

async function readNotification(notification: Notification): Promise<void> {
    try {
        const response = await axios.post(`/notifications/${notification.id}/read`);
        if (response.data?.unread_count !== undefined) {
            localUnread.value = Number(response.data.unread_count);
        }

        if (!notification.read_at) {
            notification.read_at = new Date().toISOString();
        }

        const url = notification.data?.url;

        if (url) {
            window.location.href = url;
        }
    } catch {
        // Biarkan user tetap bisa navigasi manual.
    }
}

async function readAllNotifications(): Promise<void> {
    try {
        const response = await axios.post('/notifications/read-all');
        if (response.data?.unread_count !== undefined) {
            localUnread.value = Number(response.data.unread_count);
        }

        notifications.value = notifications.value.map((item) => ({
            ...item,
            read_at: item.read_at ?? new Date().toISOString(),
        }));
    } catch {
        // Ignore
    }
}

function closeOnOutside(event: MouseEvent): void {
    const target = event.target as HTMLElement;

    if (!target.closest('.app-search')) {
        searchOpen.value = false;
    }

    if (!target.closest('.app-notifications')) {
        notificationsOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', closeOnOutside);
    localUnread.value = unreadCount.value;
});

onBeforeUnmount(() => {
    document.removeEventListener('click', closeOnOutside);
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
});

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="sticky top-0 z-40 flex h-16 shrink-0 items-center gap-3 border-b border-gray-200 bg-white px-4 transition-[width,height] ease-linear md:px-6"
    >
        <SidebarTrigger class="-ml-1 shrink-0 text-sm-card" />

        <Breadcrumbs
            v-if="breadcrumbs.length > 0"
            :breadcrumbs="breadcrumbs"
            class="shrink-0"
        />

        <!-- Search lebar navy (Figma: pill dari x~296 s.d. x~1121) -->
        <div class="app-search relative hidden flex-1 lg:block">
            <Search
                class="pointer-events-none absolute left-5 top-1/2 h-4 w-4 -translate-y-1/2 text-white/70"
            />
            <input
                v-model="searchQuery"
                type="search"
                placeholder="Cari sponsor, perusahaan atau event"
                class="h-10 w-full max-w-[820px] rounded-full bg-sm-card pl-12 pr-10 text-sm text-white placeholder:text-white/70 focus:outline-none"
                @input="onSearchInput"
                @focus="searchOpen = searchQuery.trim() !== ''"
            />
            <button
                v-if="searchQuery"
                type="button"
                class="absolute right-4 top-1/2 -translate-y-1/2 text-white/70 transition-colors hover:text-white"
                @click="clearSearch"
            >
                <X class="h-4 w-4" />
            </button>

            <div
                v-if="searchOpen && searchQuery.trim() !== ''"
                class="absolute left-0 top-[calc(100%+8px)] z-50 w-full max-w-[820px] overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl"
            >
                <div v-if="searchLoading" class="px-4 py-3 text-sm text-gray-500">
                    Mencari...
                </div>
                <div v-else-if="searchResults.length === 0" class="px-4 py-3 text-sm text-gray-500">
                    Tidak ada hasil untuk "{{ searchQuery.trim() }}"
                </div>
                <ul v-else class="max-h-[360px] divide-y divide-gray-100 overflow-y-auto">
                    <li v-for="result in searchResults" :key="result.id">
                        <a
                            :href="result.url"
                            class="flex items-center gap-3 px-4 py-3 transition-colors hover:bg-gray-50"
                        >
                            <Avatar class="size-9">
                                <AvatarFallback class="bg-sm-card text-xs font-semibold text-white">
                                    {{ getInitials(result.name) }}
                                </AvatarFallback>
                            </Avatar>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-sm-navy">
                                    {{ result.name }}
                                </p>
                                <p class="truncate text-xs text-gray-500">
                                    {{ result.roleLabel }} • {{ result.subtitle }}
                                </p>
                            </div>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="ml-auto flex shrink-0 items-center gap-3">
            <!-- Notifikasi -->
            <div class="app-notifications relative">
                <button
                    type="button"
                    class="relative flex h-10 w-10 items-center justify-center rounded-full text-sm-card hover:bg-gray-100"
                    title="Notifikasi"
                    @click.stop="openNotifications"
                >
                    <Bell class="h-5 w-5" />
                    <span
                        v-if="localUnread > 0"
                        class="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-sm-red px-1 text-[10px] font-semibold text-white ring-2 ring-white"
                    >
                        {{ localUnread > 99 ? '99+' : localUnread }}
                    </span>
                </button>

                <div
                    v-if="notificationsOpen"
                    class="absolute right-0 top-[calc(100%+8px)] z-50 w-[340px] overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl"
                >
                    <div class="flex items-center justify-between border-b border-gray-100 px-4 py-2.5">
                        <p class="text-sm font-semibold text-sm-navy">Notifikasi</p>
                        <button
                            v-if="localUnread > 0"
                            type="button"
                            class="text-xs font-medium text-sm-red hover:underline"
                            @click="readAllNotifications"
                        >
                            Tandai semua dibaca
                        </button>
                    </div>
                    <div v-if="notificationsLoading" class="px-4 py-3 text-sm text-gray-500">
                        Memuat...
                    </div>
                    <div v-else-if="notifications.length === 0" class="px-4 py-6 text-center text-sm text-gray-500">
                        Belum ada notifikasi
                    </div>
                    <ul v-else class="max-h-[380px] divide-y divide-gray-100 overflow-y-auto">
                        <li v-for="notification in notifications" :key="notification.id">
                            <button
                                type="button"
                                class="flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-gray-50"
                                :class="notification.read_at ? '' : 'bg-red-50/40'"
                                @click="readNotification(notification)"
                            >
                                <div
                                    class="mt-1 size-2 shrink-0 rounded-full"
                                    :class="notification.read_at ? 'bg-gray-300' : 'bg-sm-red'"
                                ></div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm text-sm-navy">
                                        {{ notification.data?.message || 'Notifikasi baru' }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500">
                                        {{ notification.diff_for_humans || '' }}
                                    </p>
                                </div>
                            </button>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Chip kredit (tampilan dulu) -->
            <span
                class="hidden items-center gap-1.5 rounded-full bg-sm-card px-3.5 py-2 text-sm font-medium text-white md:flex"
            >
                <Coins class="h-4 w-4 text-sm-amber" />
                250
            </span>

            <!-- Avatar + nama + peran → dropdown menu user -->
            <DropdownMenu>
                <DropdownMenuTrigger :as-child="true">
                    <button
                        type="button"
                        class="flex items-center gap-3 rounded-full pr-1 focus:outline-none"
                        data-test="user-menu-button"
                    >
                        <div class="hidden text-left leading-tight md:block">
                            <p class="text-sm font-semibold text-sm-card">
                                {{ auth.user?.name }}
                            </p>
                            <p class="text-xs text-sm-card/60">
                                {{ roleName }}
                            </p>
                        </div>
                        <Avatar class="size-10 overflow-hidden rounded-full">
                            <AvatarImage
                                v-if="auth.user?.avatar"
                                :src="auth.user.avatar"
                                :alt="auth.user.name"
                            />
                            <AvatarFallback
                                class="rounded-full bg-sm-card font-semibold text-white"
                            >
                                {{ getInitials(auth.user?.name) }}
                            </AvatarFallback>
                        </Avatar>
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-56">
                    <UserMenuContent :user="auth.user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>
</template>
