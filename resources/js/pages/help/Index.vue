<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookOpen,
    Building2,
    ChevronDown,
    FileText,
    Handshake,
    HelpCircle,
    Mail,
    Search,
    Sparkles,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import PageShell from '@/components/layout/PageShell.vue';

type QuickLink = {
    label: string;
    url: string;
    description: string;
};

type Faq = {
    question: string;
    answer: string;
};

const props = defineProps<{
    role: 'eo' | 'company';
    quickLinks: QuickLink[];
    faqs: Faq[];
    contact: {
        email: string;
        hours: string;
        response: string;
    };
}>();

const search = ref('');

const filteredFaqs = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (term === '') {
        return props.faqs;
    }

    return props.faqs.filter(
        (faq) =>
            faq.question.toLowerCase().includes(term) ||
            faq.answer.toLowerCase().includes(term),
    );
});

const linkIcons = [
    BookOpen,
    FileText,
    Sparkles,
    Handshake,
    Building2,
    ArrowRight,
];
</script>

<template>
    <Head title="Pusat Bantuan" />

    <PageShell>
        <PageHeader
            title="Pusat Bantuan"
            :description="
                role === 'company'
                    ? 'Panduan singkat mengelola profil, campaign, proposal masuk, dan kolaborasi perusahaan.'
                    : 'Panduan singkat membuat proposal, mencari sponsor, dan menyelesaikan kolaborasi.'
            "
        >
            <template #actions>
                <Link
                    href="/billing"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 text-xs font-semibold text-sm-navy transition-colors hover:bg-gray-50"
                >
                    <Sparkles class="h-4 w-4" />
                    Langganan
                </Link>
            </template>
        </PageHeader>

        <div
            class="mt-6 grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_294px]"
        >
            <div class="min-w-0 space-y-6">
                <!-- Akses cepat -->
                <section>
                    <h2 class="text-lg font-bold text-sm-navy">Akses Cepat</h2>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <Link
                            v-for="(link, index) in quickLinks"
                            :key="link.url"
                            :href="link.url"
                            class="group flex h-full flex-col rounded-2xl border border-gray-200 bg-white p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
                        >
                            <span
                                class="flex h-10 w-10 items-center justify-center rounded-full bg-red-50 text-sm-red"
                            >
                                <component
                                    :is="linkIcons[index] ?? HelpCircle"
                                    class="h-5 w-5"
                                />
                            </span>
                            <p
                                class="mt-3 flex items-center gap-1 text-sm font-semibold text-sm-navy"
                            >
                                {{ link.label }}
                                <ArrowRight
                                    class="h-3.5 w-3.5 opacity-0 transition-opacity group-hover:opacity-100"
                                />
                            </p>
                            <p
                                class="mt-1 line-clamp-2 text-[11px] leading-relaxed text-sm-card/65"
                            >
                                {{ link.description }}
                            </p>
                        </Link>
                    </div>
                </section>

                <!-- FAQ -->
                <section
                    class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"
                >
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
                    >
                        <div>
                            <h2 class="text-lg font-bold text-sm-navy">
                                Pertanyaan Umum
                            </h2>
                            <p class="mt-0.5 text-xs text-sm-card/65">
                                Jawaban singkat untuk alur yang paling sering
                                ditanyakan.
                            </p>
                        </div>

                        <div class="relative w-full sm:w-64">
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-gray-400"
                            />
                            <input
                                v-model="search"
                                type="search"
                                placeholder="Cari pertanyaan..."
                                class="h-10 w-full rounded-lg border border-gray-200 bg-white pr-3 pl-9 text-xs text-sm-navy outline-none placeholder:text-gray-400 focus:border-sm-red focus:ring-2 focus:ring-sm-red/15"
                            />
                        </div>
                    </div>

                    <div
                        v-if="filteredFaqs.length === 0"
                        class="mt-4 rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-center"
                    >
                        <HelpCircle class="mx-auto h-6 w-6 text-gray-400" />
                        <p class="mt-2 text-xs font-semibold text-sm-navy">
                            Pertanyaan tidak ditemukan
                        </p>
                        <p class="mt-1 text-[10px] text-sm-card/60">
                            Coba kata kunci lain atau hubungi tim kami.
                        </p>
                    </div>

                    <div v-else class="mt-4 divide-y divide-gray-100">
                        <details
                            v-for="faq in filteredFaqs"
                            :key="faq.question"
                            class="group py-3 first:pt-0 last:pb-0"
                        >
                            <summary
                                class="flex cursor-pointer list-none items-center justify-between gap-3 text-left"
                            >
                                <span
                                    class="text-xs font-semibold text-sm-navy"
                                >
                                    {{ faq.question }}
                                </span>
                                <ChevronDown
                                    class="h-4 w-4 shrink-0 text-sm-card/50 transition-transform group-open:rotate-180"
                                />
                            </summary>
                            <p
                                class="mt-2 text-[11px] leading-relaxed text-sm-card/70"
                            >
                                {{ faq.answer }}
                            </p>
                        </details>
                    </div>
                </section>
            </div>

            <!-- Kontak -->
            <aside class="min-w-0 space-y-5 xl:sticky xl:top-24">
                <section
                    class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm"
                >
                    <div class="flex items-center gap-2">
                        <span
                            class="flex h-9 w-9 items-center justify-center rounded-full bg-sm-red text-white"
                        >
                            <Mail class="h-5 w-5" />
                        </span>
                        <h2 class="text-base font-bold text-sm-navy">
                            Hubungi Tim Kami
                        </h2>
                    </div>
                    <p class="mt-2 text-[11px] leading-relaxed text-sm-card/70">
                        {{ contact.response }}
                    </p>

                    <a
                        :href="`mailto:${contact.email}`"
                        class="mt-3 inline-flex h-10 w-full items-center justify-center gap-2 rounded-lg bg-sm-red text-xs font-semibold text-white transition-colors hover:bg-sm-red-dark"
                    >
                        <Mail class="h-4 w-4" />
                        {{ contact.email }}
                    </a>

                    <p
                        class="mt-3 border-t border-gray-100 pt-3 text-[10px] text-sm-card/60"
                    >
                        {{ contact.hours }}
                    </p>
                </section>

                <section
                    class="rounded-2xl bg-gradient-to-br from-sm-red to-[#5D0F19] p-5 text-white shadow-sm"
                >
                    <div class="flex items-center gap-2">
                        <HelpCircle class="h-6 w-6 shrink-0 text-sm-amber" />
                        <h2 class="text-base font-bold">Tips cepat</h2>
                    </div>
                    <p class="mt-2 text-[11px] leading-relaxed text-white/85">
                        Lengkapi profil terlebih dahulu agar rekomendasi dan
                        penawaran berjalan lebih akurat. Campaign dan langganan
                        premium bersifat opsional.
                    </p>
                </section>
            </aside>
        </div>
    </PageShell>
</template>
