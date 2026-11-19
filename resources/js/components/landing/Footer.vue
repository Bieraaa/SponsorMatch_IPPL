<script setup lang="ts">
const navLinks = [
    { label: 'Beranda', href: '#beranda' },
    { label: 'Tentang', href: '#tentang' },
    { label: 'Langganan', href: '#langganan' },
    { label: 'FAQ', href: '#faq' },
];

const accountLinks = [
    { label: 'Masuk', href: '/login' },
    { label: 'Daftar', href: '/register' },
];

const socials = [
    {
        icon: 'vector_email_putih.svg',
        label: 'Email',
        href: 'mailto:hello@sponsormatch.id',
    },
    { icon: 'vector_instagram_putih.svg', label: 'Instagram', href: '#' },
    { icon: 'vector_linkedin_putih.svg', label: 'LinkedIn', href: '#' },
];
</script>

<template>
    <footer class="snap-end bg-sm-footer font-jakarta text-sm-navy">
        <div class="mx-auto max-w-[1440px] px-5 py-14 lg:px-10">
            <div class="grid gap-10 md:grid-cols-[1.5fr_1fr_1fr_auto]">
                <!-- Brand -->
                <div>
                    <img
                        src="/assets/logo_sponsormatch_lengkap.svg"
                        alt="SponsorMatch"
                        class="h-9 w-auto"
                    />
                    <p class="mt-4 max-w-xs text-sm leading-6 text-sm-navy/70">
                        Membantu event organizer menemukan dan mengelola sponsor
                        melalui pencocokan berbasis AI.
                    </p>
                </div>

                <!-- Navigasi -->
                <div>
                    <h3 class="font-poppins text-sm font-semibold">Navigasi</h3>
                    <ul class="mt-4 space-y-3 text-sm">
                        <li v-for="item in navLinks" :key="item.href">
                            <a
                                :href="item.href"
                                class="text-sm-navy/70 transition-colors hover:text-sm-red"
                            >
                                {{ item.label }}
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Akun -->
                <div>
                    <h3 class="font-poppins text-sm font-semibold">Akun</h3>
                    <ul class="mt-4 space-y-3 text-sm">
                        <li v-for="item in accountLinks" :key="item.href">
                            <a
                                :href="item.href"
                                class="text-sm-navy/70 transition-colors hover:text-sm-red"
                            >
                                {{ item.label }}
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Sosial media -->
                <div>
                    <h3 class="font-poppins text-sm font-semibold">
                        Hubungi Kami
                    </h3>
                    <div class="mt-4 flex gap-3">
                        <a
                            v-for="social in socials"
                            :key="social.label"
                            :href="social.href"
                            :aria-label="social.label"
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-sm-navy transition-colors hover:bg-sm-red"
                        >
                            <img
                                :src="`/assets/${social.icon}`"
                                alt=""
                                class="h-4 w-4"
                            />
                        </a>
                    </div>
                </div>
            </div>

            <hr class="my-8 border-sm-navy/10" />

            <p class="text-center text-xs text-sm-navy/60">
                © 2026 SponsorMatch. Seluruh hak cipta dilindungi.
            </p>
        </div>
    </footer>
</template>
