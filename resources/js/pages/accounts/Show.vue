<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building2,
    CalendarDays,
    Mail,
    Megaphone,
    Phone,
    Star,
    UserRound,
} from '@lucide/vue';
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { getInitials } from '@/composables/useInitials';
import type { PostItem } from '@/types/post';

type Account = {
    id: number;
    name: string;
    person_name: string;
    role_label: string;
    field: string | null;
    email: string;
    phone: string | null;
    jabatan: string | null;
    avatar_url: string | null;
    rating: number;
    review_count: number;
    joined_at: string | null;
    is_self: boolean;
};

type ReviewItem = {
    id: number;
    rating: number;
    comment: string | null;
    created_at: string | null;
    reviewer_name: string;
    reviewer_avatar: string | null;
    reviewer_role: string | null;
    event_title: string | null;
};

const props = defineProps<{
    account: Account;
    reviews: ReviewItem[];
    posts: PostItem[];
}>();

const joinedLabel = computed(() => {
    if (!props.account.joined_at) {
        return null;
    }

    return new Date(props.account.joined_at).toLocaleDateString('id-ID', {
        month: 'long',
        year: 'numeric',
    });
});

function formatDate(value: string | null): string {
    if (!value) {
        return 'Baru saja';
    }

    return new Date(value).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}
</script>

<template>
    <Head :title="account.name" />

    <div class="min-h-full bg-[#f7f8fa] pb-10">
        <div class="mx-auto w-full max-w-[1180px] px-4 py-6 xl:px-0">
            <Link
                href="/dashboard"
                class="inline-flex items-center gap-1.5 text-sm font-medium text-sm-card/70 transition-colors hover:text-sm-navy"
            >
                <ArrowLeft class="size-4" />
                Kembali ke dashboard
            </Link>

            <section
                class="mt-4 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"
            >
                <div class="h-28 bg-gradient-to-r from-sm-navy to-sm-card" />

                <div class="px-6 pb-6">
                    <div
                        class="-mt-12 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
                    >
                        <div class="flex items-end gap-4">
                            <Avatar
                                class="size-24 rounded-2xl ring-4 ring-white"
                            >
                                <AvatarImage
                                    v-if="account.avatar_url"
                                    :src="account.avatar_url"
                                    :alt="account.name"
                                />
                                <AvatarFallback
                                    class="rounded-2xl bg-sm-card text-2xl font-semibold text-white"
                                >
                                    {{ getInitials(account.name) }}
                                </AvatarFallback>
                            </Avatar>

                            <div class="pb-1">
                                <h1 class="text-2xl font-semibold text-sm-navy">
                                    {{ account.name }}
                                </h1>
                                <p
                                    class="mt-1 flex flex-wrap items-center gap-2 text-sm text-sm-card/70"
                                >
                                    <span
                                        class="rounded-full bg-sm-navy/5 px-2.5 py-1 text-[11px] font-semibold text-sm-navy"
                                    >
                                        {{ account.role_label }}
                                    </span>
                                    <span v-if="account.field">
                                        {{ account.field }}
                                    </span>
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <div
                                class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2 text-center"
                            >
                                <p
                                    class="flex items-center justify-center gap-1 text-lg font-semibold text-amber-700"
                                >
                                    <Star
                                        class="size-4 fill-amber-400 text-amber-400"
                                    />
                                    {{
                                        account.rating > 0
                                            ? account.rating.toFixed(1)
                                            : '-'
                                    }}
                                </p>
                                <p
                                    class="text-[10px] font-medium text-amber-700/80"
                                >
                                    {{ account.review_count }} ulasan
                                </p>
                            </div>

                            <Link
                                v-if="account.is_self"
                                href="/settings/profile"
                                class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-semibold text-sm-navy transition-colors hover:bg-gray-50"
                            >
                                Edit profil
                            </Link>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-3 sm:grid-cols-2">
                        <div
                            class="flex items-center gap-3 rounded-xl border border-gray-100 bg-gray-50/70 px-4 py-3"
                        >
                            <Mail class="size-4 shrink-0 text-sm-card/60" />
                            <div class="min-w-0">
                                <p
                                    class="text-[10px] font-medium tracking-wide text-gray-400 uppercase"
                                >
                                    Email
                                </p>
                                <p class="truncate text-sm text-sm-navy">
                                    {{ account.email }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="flex items-center gap-3 rounded-xl border border-gray-100 bg-gray-50/70 px-4 py-3"
                        >
                            <Phone class="size-4 shrink-0 text-sm-card/60" />
                            <div class="min-w-0">
                                <p
                                    class="text-[10px] font-medium tracking-wide text-gray-400 uppercase"
                                >
                                    Telepon
                                </p>
                                <p class="truncate text-sm text-sm-navy">
                                    {{ account.phone || 'Belum diisi' }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="flex items-center gap-3 rounded-xl border border-gray-100 bg-gray-50/70 px-4 py-3"
                        >
                            <UserRound
                                class="size-4 shrink-0 text-sm-card/60"
                            />
                            <div class="min-w-0">
                                <p
                                    class="text-[10px] font-medium tracking-wide text-gray-400 uppercase"
                                >
                                    Penanggung jawab
                                </p>
                                <p class="truncate text-sm text-sm-navy">
                                    {{ account.person_name }}
                                    <span
                                        v-if="account.jabatan"
                                        class="text-sm-card/60"
                                    >
                                        · {{ account.jabatan }}
                                    </span>
                                </p>
                            </div>
                        </div>

                        <div
                            class="flex items-center gap-3 rounded-xl border border-gray-100 bg-gray-50/70 px-4 py-3"
                        >
                            <CalendarDays
                                class="size-4 shrink-0 text-sm-card/60"
                            />
                            <div class="min-w-0">
                                <p
                                    class="text-[10px] font-medium tracking-wide text-gray-400 uppercase"
                                >
                                    Bergabung
                                </p>
                                <p class="truncate text-sm text-sm-navy">
                                    {{ joinedLabel || 'Baru bergabung' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section
                class="mt-5 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"
            >
                <div
                    class="flex items-center justify-between border-b border-gray-100 px-6 py-4"
                >
                    <h2 class="text-base font-semibold text-sm-navy">Ulasan</h2>
                    <span class="text-xs text-sm-card/60">
                        {{ account.review_count }} ulasan diterima
                    </span>
                </div>

                <div v-if="reviews.length === 0" class="px-6 py-10 text-center">
                    <Building2 class="mx-auto size-8 text-gray-300" />
                    <p class="mt-2 text-sm font-medium text-sm-navy">
                        Belum ada ulasan
                    </p>
                    <p class="mt-1 text-xs text-sm-card/60">
                        Ulasan muncul setelah kerja sama dengan akun ini
                        selesai.
                    </p>
                </div>

                <ul v-else class="divide-y divide-gray-100">
                    <li
                        v-for="review in reviews"
                        :key="review.id"
                        class="flex gap-3 px-6 py-4"
                    >
                        <Avatar class="size-9 shrink-0">
                            <AvatarImage
                                v-if="review.reviewer_avatar"
                                :src="review.reviewer_avatar"
                                :alt="review.reviewer_name"
                            />
                            <AvatarFallback
                                class="bg-sm-card text-xs font-semibold text-white"
                            >
                                {{ getInitials(review.reviewer_name) }}
                            </AvatarFallback>
                        </Avatar>

                        <div class="min-w-0 flex-1">
                            <div
                                class="flex flex-wrap items-center gap-x-2 gap-y-1"
                            >
                                <p class="text-sm font-semibold text-sm-navy">
                                    {{ review.reviewer_name }}
                                </p>
                                <span
                                    v-if="review.reviewer_role"
                                    class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-600"
                                >
                                    {{ review.reviewer_role }}
                                </span>
                                <span class="text-[11px] text-gray-400">
                                    {{ formatDate(review.created_at) }}
                                </span>
                            </div>

                            <p class="mt-1 flex items-center gap-0.5">
                                <Star
                                    v-for="index in 5"
                                    :key="index"
                                    class="size-3.5"
                                    :class="
                                        index <= review.rating
                                            ? 'fill-amber-400 text-amber-400'
                                            : 'text-gray-300'
                                    "
                                />
                            </p>

                            <p
                                v-if="review.comment"
                                class="mt-2 text-sm text-sm-card/80"
                            >
                                {{ review.comment }}
                            </p>

                            <p
                                v-if="review.event_title"
                                class="mt-1 text-[11px] text-gray-400"
                            >
                                Kerja sama: {{ review.event_title }}
                            </p>
                        </div>
                    </li>
                </ul>
            </section>

            <section
                class="mt-5 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"
            >
                <div
                    class="flex items-center justify-between border-b border-gray-100 px-6 py-4"
                >
                    <h2 class="text-base font-semibold text-sm-navy">
                        Postingan
                    </h2>
                    <span class="text-xs text-sm-card/60">
                        {{ posts.length }} postingan terbaru
                    </span>
                </div>

                <div v-if="posts.length === 0" class="px-6 py-10 text-center">
                    <Megaphone class="mx-auto size-8 text-gray-300" />
                    <p class="mt-2 text-sm font-medium text-sm-navy">
                        Belum ada postingan
                    </p>
                    <p class="mt-1 text-xs text-sm-card/60">
                        Postingan kegiatan dan informasi organisasi akan tampil
                        di sini.
                    </p>
                </div>

                <div v-else class="space-y-4 p-5">
                    <article
                        v-for="post in posts"
                        :key="post.id"
                        class="overflow-hidden rounded-xl border border-gray-200"
                    >
                        <div v-if="post.image_url">
                            <img
                                :src="post.image_url"
                                :alt="`Postingan ${post.author_name}`"
                                class="max-h-96 w-full object-cover"
                                loading="lazy"
                            />
                        </div>
                        <div class="p-4">
                            <p
                                v-if="post.description"
                                class="text-sm leading-relaxed whitespace-pre-line text-sm-navy"
                            >
                                {{ post.description }}
                            </p>
                            <p class="mt-2 text-[10px] text-sm-card/50">
                                {{ formatDate(post.created_at) }}
                            </p>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </div>
</template>
