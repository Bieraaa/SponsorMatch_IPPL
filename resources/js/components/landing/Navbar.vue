<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

const links = [
    { label: 'Beranda', href: '#beranda' },
    { label: 'Tentang', href: '#tentang' },
    { label: 'Langganan', href: '#langganan' },
    { label: 'FAQ', href: '#faq' },
];
</script>

<template>
    <header class="sticky top-0 z-50 bg-white text-sm-navy shadow-sm">
        <nav
            class="mx-auto flex h-[68px] max-w-[1440px] items-center justify-between px-5 lg:px-10"
        >
            <a href="#beranda" aria-label="SponsorMatch">
                <img
                    src="/assets/logo_sponsormatch_lengkap.svg"
                    alt="SponsorMatch"
                    class="h-9 w-auto"
                />
            </a>

            <ul
                class="hidden items-center gap-12 font-poppins text-[15px] font-medium md:flex"
            >
                <li v-for="item in links" :key="item.href">
                    <a
                        :href="item.href"
                        class="transition-colors hover:text-sm-red"
                        >{{ item.label }}</a
                    >
                </li>
            </ul>

            <div class="flex items-center gap-5">
                <Link
                    href="/login"
                    class="font-poppins text-lg font-medium transition-colors hover:text-sm-red"
                >
                    Masuk
                </Link>
                <Link
                    href="/register"
                    class="hidden items-center gap-2 rounded-lg bg-sm-red px-5 py-2.5 font-poppins text-sm font-medium text-white transition-colors hover:bg-sm-red-dark sm:inline-flex"
                >
                    Daftar Sekarang
                    <img
                        src="/assets/vector_next_kanan_putih.svg"
                        alt=""
                        class="h-3.5 w-3.5"
                    />
                </Link>
            </div>
        </nav>
    </header>
</template>
