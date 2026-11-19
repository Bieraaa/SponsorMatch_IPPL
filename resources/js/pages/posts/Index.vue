<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    ImagePlus,
    Loader2,
    Megaphone,
    Newspaper,
    Pencil,
    Plus,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import PageHeader from '@/components/layout/PageHeader.vue';
import SponsorPagination from '@/components/recommendations/SponsorPagination.vue';
import { getInitials } from '@/composables/useInitials';
import { destroy, index as postsIndex, store, update } from '@/routes/posts';
import type {
    PostImageConfig,
    PostItem,
    PostPaginator,
    PostScope,
} from '@/types/post';

/**
 * Halaman "Postingan".
 *
 * Feed dapat dibaca semua akun login. Tombol ubah/hapus hanya muncul untuk
 * pemilik postingan, dan server tetap memeriksa kepemilikan pada setiap
 * aksi. Tabel `post` memakai penghapusan lunak (`deleteAt`), jadi postingan
 * yang dihapus tidak lagi tampil di feed maupun dashboard.
 */
const props = defineProps<{
    posts: PostPaginator;
    scope: PostScope;
    myPostCount: number;
    totalPostCount: number;
    imageConfig: PostImageConfig;
}>();

const page = usePage();
const currentUserId = computed(() => page.props.auth.user?.id ?? null);
const isBusy = ref(false);
const busyPostId = ref<number | null>(null);
const modalOpen = ref(false);
const editing = ref<PostItem | null>(null);
const previewUrl = ref<string | null>(null);
const localImageError = ref<string | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

const form = useForm<{
    description: string;
    image: File | null;
    remove_image: boolean;
    scope: PostScope;
}>({
    description: '',
    image: null,
    remove_image: false,
    scope: props.scope,
});

const maxMb = computed(() => Math.round(props.imageConfig.max_kb / 1024));

const tabs = computed(() => [
    {
        key: 'all' as PostScope,
        label: 'Semua Postingan',
        count: props.totalPostCount,
    },
    {
        key: 'mine' as PostScope,
        label: 'Postingan Saya',
        count: props.myPostCount,
    },
]);

const shownImage = computed(() => {
    if (previewUrl.value) {
        return previewUrl.value;
    }

    if (form.remove_image) {
        return null;
    }

    return editing.value?.image_url ?? null;
});

const submitting = computed(() => form.processing);

function initials(value: string): string {
    return getInitials(value);
}

function relativeTime(value: string | null): string {
    if (!value) {
        return 'Baru saja';
    }

    const date = new Date(value);
    const seconds = Math.floor((Date.now() - date.getTime()) / 1000);

    if (seconds < 60) {
        return 'Baru saja';
    }

    const minutes = Math.floor(seconds / 60);

    if (minutes < 60) {
        return `${minutes} menit lalu`;
    }

    const hours = Math.floor(minutes / 60);

    if (hours < 24) {
        return `${hours} jam lalu`;
    }

    const days = Math.floor(hours / 24);

    if (days < 7) {
        return `${days} hari lalu`;
    }

    return date.toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Date(value).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function releasePreview(): void {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
        previewUrl.value = null;
    }
}

function resetForm(): void {
    form.reset();
    form.clearErrors();
    form.scope = props.scope;
    localImageError.value = null;
    releasePreview();

    if (fileInput.value) {
        fileInput.value.value = '';
    }
}

function openCreate(): void {
    editing.value = null;
    resetForm();
    modalOpen.value = true;
}

function openEdit(post: PostItem): void {
    editing.value = post;
    resetForm();
    form.description = post.description ?? '';
    form.scope = props.scope;
    modalOpen.value = true;
}

function closeModal(): void {
    if (form.processing) {
        return;
    }

    modalOpen.value = false;
    editing.value = null;
    resetForm();
}

function onFileChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    localImageError.value = null;
    releasePreview();

    if (!file) {
        form.image = null;
        return;
    }

    const extension = file.name.split('.').pop()?.toLowerCase() ?? '';

    if (!props.imageConfig.extensions.includes(extension)) {
        localImageError.value = `Gambar harus berformat ${props.imageConfig.extensions.join(', ')}.`;
        form.image = null;
        input.value = '';
        return;
    }

    if (file.size > props.imageConfig.max_kb * 1024) {
        localImageError.value = `Ukuran gambar maksimal ${maxMb.value} MB.`;
        form.image = null;
        input.value = '';
        return;
    }

    form.image = file;
    form.remove_image = false;
    previewUrl.value = URL.createObjectURL(file);
}

function clearNewImage(): void {
    form.image = null;
    releasePreview();

    if (fileInput.value) {
        fileInput.value.value = '';
    }
}

function submit(): void {
    if (localImageError.value) {
        return;
    }

    form.scope = props.scope;

    const options = {
        forceFormData: true,
        preserveScroll: true,
        onError: (errors: Record<string, string>) => {
            toast.error(
                Object.values(errors)[0] ??
                    'Postingan gagal disimpan. Periksa kembali isian kamu.',
            );
        },
        onSuccess: () => {
            modalOpen.value = false;
            editing.value = null;
            resetForm();
        },
    };

    if (editing.value) {
        form.patch(update(editing.value.id).url, options);
    } else {
        form.post(store().url, options);
    }
}

function deletePost(post: PostItem): void {
    if (
        !window.confirm(
            'Hapus postingan ini? Postingan tidak akan tampil lagi di feed dan dashboard.',
        )
    ) {
        return;
    }

    busyPostId.value = post.id;

    router.delete(destroy(post.id).url, {
        preserveScroll: true,
        onError: (errors) => {
            toast.error(
                Object.values(errors)[0] ??
                    'Postingan gagal dihapus. Coba lagi.',
            );
        },
        onFinish: () => {
            busyPostId.value = null;
        },
    });
}

function changeScope(scope: PostScope): void {
    if (scope === props.scope) {
        return;
    }

    router.get(
        postsIndex().url,
        { scope },
        { preserveScroll: true, preserveState: true },
    );
}

function changePage(nextPage: number): void {
    router.get(
        postsIndex().url,
        { scope: props.scope, page: nextPage },
        { preserveScroll: true, preserveState: true },
    );
}

onMounted(() => {
    const url = new URL(window.location.href);

    if (url.searchParams.get('create') === '1') {
        openCreate();
        url.searchParams.delete('create');
        window.history.replaceState({}, '', url.toString());
    }
});

onBeforeUnmount(releasePreview);
</script>

<template>
    <Head title="Postingan" />

    <div class="min-h-full bg-[#F7F8FA] pb-12">
        <div class="mx-auto w-full max-w-[1180px] px-4 py-6 xl:px-0">
            <PageHeader
                title="Postingan"
                description="Bagikan informasi, aktivitas, atau kabar terbaru dari organisasi dan perusahaan kamu."
            >
                <template #actions>
                    <button
                        type="button"
                        class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-lg bg-sm-red px-4 text-xs font-semibold text-white transition-colors hover:bg-sm-red-dark"
                        @click="openCreate"
                    >
                        <Plus class="h-4 w-4" />
                        Buat Postingan
                    </button>
                </template>
            </PageHeader>

            <div class="mt-5 flex flex-wrap gap-2">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-xs font-semibold transition-colors"
                    :class="
                        props.scope === tab.key
                            ? 'border-sm-red bg-sm-red text-white'
                            : 'border-gray-200 bg-white text-sm-navy hover:border-gray-300'
                    "
                    @click="changeScope(tab.key)"
                >
                    {{ tab.label }}
                    <span
                        class="rounded-full px-1.5 py-0.5 text-[10px]"
                        :class="
                            props.scope === tab.key
                                ? 'bg-white/20 text-white'
                                : 'bg-gray-100 text-sm-card/70'
                        "
                    >
                        {{ tab.count }}
                    </span>
                </button>
            </div>

            <div
                v-if="props.posts.data.length === 0"
                class="mt-5 rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center"
            >
                <span
                    class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-red-50 text-sm-red"
                >
                    <Newspaper class="h-7 w-7" />
                </span>
                <h2 class="mt-4 text-lg font-semibold text-sm-navy">
                    {{
                        props.scope === 'mine'
                            ? 'Kamu Belum Punya Postingan'
                            : 'Belum Ada Postingan'
                    }}
                </h2>
                <p
                    class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-sm-card/70"
                >
                    {{
                        props.scope === 'mine'
                            ? 'Mulai bagikan kegiatan atau informasi terbaru organisasi kamu.'
                            : 'Postingan dari Event Organizer dan perusahaan akan tampil di sini.'
                    }}
                </p>
                <button
                    type="button"
                    class="mt-4 inline-flex items-center gap-2 rounded-lg bg-sm-red px-4 py-2 text-xs font-semibold text-white transition-colors hover:bg-sm-red-dark"
                    @click="openCreate"
                >
                    <Plus class="h-4 w-4" />
                    Buat Postingan
                </button>
            </div>

            <div v-else class="mt-5 space-y-5">
                <article
                    v-for="post in props.posts.data"
                    :key="post.id"
                    class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"
                >
                    <div class="flex items-start gap-3 px-5 pt-5">
                        <span
                            class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-sm-card text-sm font-semibold text-white"
                        >
                            <img
                                v-if="post.logo_url || post.author_avatar"
                                :src="post.logo_url ?? post.author_avatar ?? ''"
                                :alt="post.author_name"
                                class="h-full w-full object-cover"
                            />
                            <template v-else>
                                {{ initials(post.author_name) }}
                            </template>
                        </span>

                        <div class="min-w-0 flex-1">
                            <div
                                class="flex flex-wrap items-center gap-x-2 gap-y-1"
                            >
                                <a
                                    :href="post.href"
                                    class="truncate text-sm font-semibold text-sm-navy hover:underline"
                                >
                                    {{ post.author_name }}
                                </a>
                                <span
                                    class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-600"
                                >
                                    {{ post.author_role_label }}
                                </span>
                            </div>
                            <p class="mt-0.5 text-[11px] text-sm-card/50">
                                {{ relativeTime(post.created_at) }}
                                <span
                                    v-if="
                                        post.updated_at &&
                                        post.created_at &&
                                        post.updated_at !== post.created_at
                                    "
                                >
                                    · diedit
                                </span>
                            </p>
                        </div>

                        <div
                            v-if="post.author_id === currentUserId"
                            class="flex shrink-0 items-center gap-1"
                        >
                            <button
                                type="button"
                                class="rounded-md p-2 text-sm-card/60 transition-colors hover:bg-gray-100 hover:text-sm-navy"
                                title="Ubah postingan"
                                @click="openEdit(post)"
                            >
                                <Pencil class="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                class="rounded-md p-2 text-sm-card/60 transition-colors hover:bg-red-50 hover:text-sm-red disabled:opacity-50"
                                title="Hapus postingan"
                                :disabled="busyPostId === post.id"
                                @click="deletePost(post)"
                            >
                                <Loader2
                                    v-if="busyPostId === post.id"
                                    class="h-4 w-4 animate-spin"
                                />
                                <Trash2 v-else class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <p
                        v-if="post.description"
                        class="mt-4 px-5 text-sm leading-relaxed whitespace-pre-line text-sm-navy"
                    >
                        {{ post.description }}
                    </p>

                    <div v-if="post.image_url" class="mt-4">
                        <img
                            :src="post.image_url"
                            :alt="`Postingan ${post.author_name}`"
                            class="max-h-[560px] w-full object-cover"
                            loading="lazy"
                        />
                    </div>
                </article>
            </div>

            <SponsorPagination
                v-if="props.posts.last_page > 1"
                class="mt-6"
                :current-page="props.posts.current_page"
                :last-page="props.posts.last_page"
                :total="props.posts.total"
                :from="props.posts.from"
                :to="props.posts.to"
                entity-label="postingan"
                @change="changePage"
            />
        </div>
    </div>

    <!-- ===================== MODAL FORM ===================== -->
    <Teleport to="body">
        <div
            v-if="modalOpen"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4"
            role="dialog"
            aria-modal="true"
            @click.self="closeModal"
        >
            <div
                class="max-h-[92vh] w-full max-w-xl overflow-y-auto rounded-t-2xl bg-white p-5 shadow-xl sm:rounded-2xl sm:p-6"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-sm-navy">
                            {{ editing ? 'Ubah Postingan' : 'Buat Postingan' }}
                        </h2>
                        <p class="mt-1 text-xs text-sm-card/60">
                            Maksimal 2000 karakter. Gambar opsional, maksimal
                            {{ maxMb }} MB.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-md p-1.5 text-sm-card/60 transition-colors hover:bg-gray-100 hover:text-sm-navy"
                        title="Tutup"
                        @click="closeModal"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <form class="mt-4 space-y-4" @submit.prevent="submit">
                    <div>
                        <label
                            for="post-description"
                            class="text-xs font-semibold text-sm-navy"
                        >
                            Teks postingan
                        </label>
                        <textarea
                            id="post-description"
                            v-model="form.description"
                            rows="5"
                            maxlength="2000"
                            class="mt-1.5 w-full resize-y rounded-lg border border-gray-300 px-3 py-2 text-sm text-sm-navy transition-colors outline-none focus:border-sm-red focus:ring-2 focus:ring-sm-red/20"
                            placeholder="Tulis informasi, kegiatan, atau kabar terbaru..."
                            :disabled="submitting"
                        />
                        <div
                            class="mt-1 flex items-center justify-between gap-2"
                        >
                            <p
                                v-if="form.errors.description"
                                class="text-[11px] text-sm-red"
                            >
                                {{ form.errors.description }}
                            </p>
                            <span class="ml-auto text-[10px] text-sm-card/50">
                                {{ form.description.length }}/2000
                            </span>
                        </div>
                    </div>

                    <div>
                        <label class="text-xs font-semibold text-sm-navy">
                            Gambar (opsional)
                        </label>

                        <div
                            v-if="shownImage"
                            class="relative mt-1.5 overflow-hidden rounded-xl border border-gray-200"
                        >
                            <img
                                :src="shownImage"
                                alt="Pratinjau gambar postingan"
                                class="max-h-64 w-full object-cover"
                            />
                            <button
                                type="button"
                                class="absolute top-2 right-2 flex h-8 w-8 items-center justify-center rounded-full bg-black/60 text-white transition-colors hover:bg-black/80"
                                title="Hapus gambar"
                                @click="
                                    previewUrl
                                        ? clearNewImage()
                                        : (form.remove_image = true)
                                "
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>

                        <label
                            class="mt-1.5 flex cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border border-dashed border-gray-300 bg-gray-50/70 px-4 py-6 text-center transition-colors hover:border-sm-red/50 hover:bg-red-50/30"
                        >
                            <ImagePlus class="h-6 w-6 text-sm-card/50" />
                            <span class="text-xs font-medium text-sm-navy">
                                Pilih gambar
                            </span>
                            <span class="text-[10px] text-sm-card/50">
                                {{ props.imageConfig.extensions.join(', ') }},
                                maks. {{ maxMb }} MB
                            </span>
                            <input
                                ref="fileInput"
                                type="file"
                                class="hidden"
                                :accept="
                                    props.imageConfig.extensions
                                        .map((ext) => `.${ext}`)
                                        .join(',')
                                "
                                :disabled="submitting"
                                @change="onFileChange"
                            />
                        </label>

                        <p
                            v-if="localImageError || form.errors.image"
                            class="mt-1 flex items-center gap-1 text-[11px] text-sm-red"
                        >
                            <AlertCircle class="h-3.5 w-3.5 shrink-0" />
                            {{ localImageError ?? form.errors.image }}
                        </p>

                        <p
                            v-else-if="
                                form.remove_image &&
                                editing?.image_url &&
                                !previewUrl
                            "
                            class="mt-1 flex items-center gap-2 text-[11px] text-amber-700"
                        >
                            Gambar akan dihapus saat disimpan.
                            <button
                                type="button"
                                class="font-semibold underline"
                                @click="form.remove_image = false"
                            >
                                Batalkan
                            </button>
                        </p>
                    </div>

                    <div
                        v-if="
                            form.progress && form.progress.percentage !== null
                        "
                        class="space-y-1"
                    >
                        <div
                            class="h-1.5 overflow-hidden rounded-full bg-gray-200"
                        >
                            <div
                                class="h-full rounded-full bg-sm-red transition-all"
                                :style="{
                                    width: `${form.progress.percentage ?? 0}%`,
                                }"
                            />
                        </div>
                        <p class="text-[10px] text-sm-card/60">
                            Mengunggah
                            {{ Math.round(form.progress.percentage ?? 0) }}%
                        </p>
                    </div>

                    <div
                        class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"
                    >
                        <button
                            type="button"
                            class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-sm-navy transition-colors hover:bg-gray-50 disabled:opacity-50"
                            :disabled="submitting"
                            @click="closeModal"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-sm-red px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-sm-red-dark disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="submitting"
                        >
                            <Loader2
                                v-if="submitting"
                                class="h-4 w-4 animate-spin"
                            />
                            <Megaphone v-else class="h-4 w-4" />
                            {{
                                submitting
                                    ? 'Menyimpan...'
                                    : editing
                                      ? 'Simpan Perubahan'
                                      : 'Publikasikan'
                            }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
