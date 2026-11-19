<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Bell,
    CircleAlert,
    Coins,
    LoaderCircle,
    Search,
    Star,
    X,
} from '@lucide/vue';
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
 *   "Cari akun, perusahaan, atau event organizer" teks putih.
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
const planLabel = computed(() =>
    auth.value.user?.status_subscription === 'Subscription'
        ? 'Premium'
        : 'Reguler',
);
const displayName = computed(() => {
    const companyName = auth.value.user?.company_name;

    if (typeof companyName === 'string' && companyName.trim() !== '') {
        return companyName;
    }

    return auth.value.user?.name ?? '';
});

const unreadCount = computed(() =>
    Number((auth.value as any).unreadNotificationsCount || 0),
);

type SearchResult = {
    id: number;
    name: string;
    account_name: string;
    email: string;
    role: 'eo' | 'sponsor';
    role_label: string;
    subtitle: string;
    industry: string | null;
    location: string | null;
    rating: number;
    review_count: number;
    avatar_url: string | null;
    logo_url: string | null;
    is_self: boolean;
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
const searchError = ref<string | null>(null);
const searchLoaded = ref(false);
let searchTimeout: ReturnType<typeof setTimeout> | null = null;
let searchAbort: AbortController | null = null;
let searchSequence = 0;

const notifications = ref<Notification[]>([]);
const notificationsOpen = ref(false);
const notificationsLoading = ref(false);
const notificationsError = ref<string | null>(null);
const localUnread = ref(unreadCount.value);

watch(unreadCount, (value) => {
    localUnread.value = Number(value || 0);
});

/**
 * Ambil hasil pencarian dari server. Request lama dibatalkan supaya hasil
 * query sebelumnya tidak menimpa hasil query terbaru.
 */
async function fetchSearch(query: string): Promise<void> {
    searchAbort?.abort();

    const controller = new AbortController();
    searchAbort = controller;
    const sequence = ++searchSequence;

    searchLoading.value = true;
    searchError.value = null;

    try {
        const response = await axios.get('/search', {
            params: { q: query },
            signal: controller.signal,
        });

        if (sequence !== searchSequence) {
            return;
        }

        searchResults.value = response.data.results ?? [];
        searchLoaded.value = true;
    } catch (error) {
        if (controller.signal.aborted || sequence !== searchSequence) {
            return;
        }

        searchResults.value = [];
        searchLoaded.value = false;
        searchError.value =
            'Gagal memuat hasil pencarian. Periksa koneksi lalu coba lagi.';
    } finally {
        if (sequence === searchSequence) {
            searchLoading.value = false;
        }
    }
}

/** Debounce input; query kosong langsung menampilkan saran akun. */
function onSearchInput(): void {
    searchOpen.value = true;
    searchError.value = null;

    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    // Hentikan request lama agar hasil query sebelumnya tidak sempat tampil.
    searchAbort?.abort();
    searchSequence++;
    searchResults.value = [];

    const q = searchQuery.value.trim();
    searchLoading.value = true;

    if (q === '') {
        searchLoaded.value = false;
        searchTimeout = setTimeout(() => void fetchSearch(''), 50);
        return;
    }

    searchTimeout = setTimeout(() => void fetchSearch(q), 300);
}

/** Saat search bar dibuka, tampilkan saran akun walau belum mengetik. */
function onSearchFocus(): void {
    searchOpen.value = true;

    if (!searchLoaded.value && !searchLoading.value) {
        void fetchSearch(searchQuery.value.trim());
    }
}

function retrySearch(): void {
    void fetchSearch(searchQuery.value.trim());
}

function clearSearch(): void {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    searchQuery.value = '';
    searchResults.value = [];
    searchError.value = null;
    searchLoaded.value = false;
    searchOpen.value = true;
    void fetchSearch('');
}

function closeSearch(): void {
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
    notificationsError.value = null;

    try {
        const response = await axios.get('/notifications');
        notifications.value = response.data.notifications ?? [];
        localUnread.value = Number(response.data.unread_count ?? 0);
    } catch {
        notifications.value = [];
        notificationsError.value =
            'Gagal memuat notifikasi. Periksa koneksi lalu coba lagi.';
    } finally {
        notificationsLoading.value = false;
    }
}

async function readNotification(notification: Notification): Promise<void> {
    try {
        const response = await axios.post(
            `/notifications/${notification.id}/read`,
        );
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

function closeOnEscape(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        searchOpen.value = false;
        notificationsOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', closeOnOutside);
    document.addEventListener('keydown', closeOnEscape);
    localUnread.value = unreadCount.value;
});

onBeforeUnmount(() => {
    document.removeEventListener('click', closeOnOutside);
    document.removeEventListener('keydown', closeOnEscape);

    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    searchAbort?.abort();
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
                class="pointer-events-none absolute top-1/2 left-5 h-4 w-4 -translate-y-1/2 text-white/70"
            />
            <input
                v-model="searchQuery"
                type="search"
                placeholder="Cari akun, perusahaan, atau event organizer"
                class="h-10 w-full max-w-[820px] rounded-full bg-sm-card pr-10 pl-12 text-sm text-white placeholder:text-white/70 focus:outline-none"
                @input="onSearchInput"
                @focus="onSearchFocus"
            />
            <button
                v-if="searchQuery"
                type="button"
                class="absolute top-1/2 right-4 -translate-y-1/2 text-white/70 transition-colors hover:text-white"
                @click="clearSearch"
            >
                <X class="h-4 w-4" />
            </button>

            <div
                v-if="searchOpen"
                class="absolute top-[calc(100%+8px)] left-0 z-50 w-full max-w-[820px] overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl"
            >
                <div
                    class="flex items-center justify-between border-b border-gray-100 px-4 py-2.5"
                >
                    <p class="text-xs font-semibold text-sm-navy">
                        {{
                            searchQuery.trim() === ''
                                ? 'Akun yang mungkin kamu kenal'
                                : 'Hasil pencarian akun'
                        }}
                    </p>
                    <span
                        v-if="searchQuery.trim() !== ''"
                        class="text-[11px] text-gray-400"
                    >
                        {{ searchResults.length }} hasil
                    </span>
                </div>

                <div
                    v-if="searchLoading"
                    class="flex items-center gap-2 px-4 py-4 text-sm text-gray-500"
                >
                    <LoaderCircle class="size-4 animate-spin text-sm-card" />
                    Mencari akun...
                </div>

                <div
                    v-else-if="searchError"
                    class="flex flex-col gap-2 px-4 py-4 text-sm text-red-600"
                >
                    <p class="flex items-start gap-2">
                        <CircleAlert class="mt-0.5 size-4 shrink-0" />
                        <span>{{ searchError }}</span>
                    </p>
                    <button
                        type="button"
                        class="w-fit rounded-lg border border-red-200 px-2.5 py-1 text-xs font-semibold text-red-600 transition-colors hover:bg-red-50"
                        @click="retrySearch"
                    >
                        Coba lagi
                    </button>
                </div>

                <div
                    v-else-if="searchResults.length === 0"
                    class="px-4 py-5 text-center text-sm text-gray-500"
                >
                    <template v-if="searchQuery.trim() === ''">
                        Belum ada akun yang bisa ditampilkan.
                    </template>
                    <template v-else>
                        Tidak ada akun yang cocok dengan "{{
                            searchQuery.trim()
                        }}". Coba nama perusahaan, industri, atau nama
                        organisasi.
                    </template>
                </div>

                <ul
                    v-else
                    class="max-h-[380px] divide-y divide-gray-100 overflow-y-auto"
                >
                    <li v-for="result in searchResults" :key="result.id">
                        <Link
                            :href="result.url"
                            class="flex items-center gap-3 px-4 py-3 transition-colors hover:bg-gray-50"
                            @click="closeSearch"
                        >
                            <span
                                class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-gray-200 bg-gray-50"
                            >
                                <img
                                    v-if="result.logo_url || result.avatar_url"
                                    :src="
                                        result.logo_url ||
                                        result.avatar_url ||
                                        ''
                                    "
                                    :alt="result.name"
                                    class="h-full w-full object-cover"
                                />
                                <span
                                    v-else
                                    class="text-xs font-semibold text-sm-card"
                                >
                                    {{ getInitials(result.name) }}
                                </span>
                            </span>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5">
                                    <p
                                        class="truncate text-sm font-semibold text-sm-navy"
                                    >
                                        {{ result.name }}
                                    </p>
                                    <span
                                        v-if="result.is_self"
                                        class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-500"
                                    >
                                        Kamu
                                    </span>
                                </div>
                                <p class="truncate text-xs text-gray-500">
                                    {{ result.role_label }} •
                                    {{ result.subtitle }}
                                </p>
                            </div>

                            <div
                                v-if="result.rating > 0"
                                class="flex shrink-0 items-center gap-1 rounded-full bg-amber-50 px-2 py-1 text-[11px] font-semibold text-amber-700"
                            >
                                <Star
                                    class="size-3 fill-amber-400 text-amber-400"
                                />
                                {{ result.rating.toFixed(1) }}
                                <span class="font-normal text-amber-700/70">
                                    ({{ result.review_count }})
                                </span>
                            </div>
                        </Link>
                    </li>
                </ul>

                <p
                    v-if="
                        !searchLoading &&
                        !searchError &&
                        searchResults.length > 0
                    "
                    class="border-t border-gray-100 px-4 py-2 text-[11px] text-gray-400"
                >
                    Klik akun untuk membuka profilnya.
                </p>
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
                        class="absolute -top-0.5 -right-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-sm-red px-1 text-[10px] font-semibold text-white ring-2 ring-white"
                    >
                        {{ localUnread > 99 ? '99+' : localUnread }}
                    </span>
                </button>

                <div
                    v-if="notificationsOpen"
                    class="absolute top-[calc(100%+8px)] right-0 z-50 w-[340px] overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl"
                >
                    <div
                        class="flex items-center justify-between border-b border-gray-100 px-4 py-2.5"
                    >
                        <p class="text-sm font-semibold text-sm-navy">
                            Notifikasi
                        </p>
                        <button
                            v-if="localUnread > 0"
                            type="button"
                            class="text-xs font-medium text-sm-red hover:underline"
                            @click="readAllNotifications"
                        >
                            Tandai semua dibaca
                        </button>
                    </div>
                    <div
                        v-if="notificationsLoading"
                        class="px-4 py-3 text-sm text-gray-500"
                    >
                        Memuat...
                    </div>
                    <div
                        v-else-if="notificationsError"
                        class="flex flex-col gap-2 px-4 py-3 text-sm text-red-600"
                    >
                        <p class="flex items-start gap-2">
                            <CircleAlert class="mt-0.5 size-4 shrink-0" />
                            <span>{{ notificationsError }}</span>
                        </p>
                        <button
                            type="button"
                            class="w-fit rounded-lg border border-red-200 px-2.5 py-1 text-xs font-semibold text-red-600 transition-colors hover:bg-red-50"
                            @click="fetchNotifications"
                        >
                            Coba lagi
                        </button>
                    </div>
                    <div
                        v-else-if="notifications.length === 0"
                        class="px-4 py-6 text-center text-sm text-gray-500"
                    >
                        Belum ada notifikasi
                    </div>
                    <ul
                        v-else
                        class="max-h-[380px] divide-y divide-gray-100 overflow-y-auto"
                    >
                        <li
                            v-for="notification in notifications"
                            :key="notification.id"
                        >
                            <button
                                type="button"
                                class="flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-gray-50"
                                :class="
                                    notification.read_at ? '' : 'bg-red-50/40'
                                "
                                @click="readNotification(notification)"
                            >
                                <div
                                    class="mt-1 size-2 shrink-0 rounded-full"
                                    :class="
                                        notification.read_at
                                            ? 'bg-gray-300'
                                            : 'bg-sm-red'
                                    "
                                ></div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm text-sm-navy">
                                        {{
                                            notification.data?.message ||
                                            'Notifikasi baru'
                                        }}
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

            <!-- Status langganan (mengarah ke halaman pembayaran) -->
            <Link
                href="/billing"
                class="hidden items-center gap-1.5 rounded-full bg-sm-card px-3.5 py-2 text-sm font-medium text-white md:flex"
                title="Lihat langganan dan pembayaran"
            >
                <Coins class="h-4 w-4 text-sm-amber" />
                {{ planLabel }}
            </Link>

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
                                {{ displayName }}
                            </p>
                            <p class="text-xs text-sm-card/60">
                                {{ roleName }}
                            </p>
                        </div>
                        <Avatar class="size-10 overflow-hidden rounded-full">
                            <AvatarImage
                                v-if="auth.user?.avatar"
                                :src="auth.user.avatar"
                                :alt="displayName"
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
