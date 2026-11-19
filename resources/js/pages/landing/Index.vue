<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Faq from '@/components/landing/Faq.vue';
import Footer from '@/components/landing/Footer.vue';
import Hero from '@/components/landing/Hero.vue';
import Navbar from '@/components/landing/Navbar.vue';
import Pricing from '@/components/landing/Pricing.vue';
import WhyUs from '@/components/landing/WhyUs.vue';
</script>

<template>
    <Head title="SponsorMatch - Temukan Sponsor yang Tepat" />

    <!--
        Wrapper ini adalah *scroll container* untuk efek "per layar penuh":
        - h-svh + overflow-y-auto: tinggi layar kecil (mobile) / layar penuh (desktop)
        - snap-y snap-mandatory: setiap seksi "mengunci" 1 layar penuh
        - scroll-pt-[68px]: kompensasi tinggi navbar sticky agar seksi tidak tertutup
    -->
    <div
        class="h-svh snap-y snap-mandatory scroll-pt-[68px] overflow-y-auto scroll-smooth bg-sm-navy font-jakarta text-white"
    >
        <Navbar />
        <main>
            <Hero />
            <WhyUs />
            <Pricing />
            <Faq />
        </main>
        <Footer />
    </div>
</template>
