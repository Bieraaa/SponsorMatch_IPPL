<script setup lang="ts">
import { computed, ref } from 'vue';
import type { ProposalPeriod, ProposalTrend } from '@/types/proposal';

/**
 * Grafik tren proposal sponsorship (SVG ringan, tanpa library eksternal).
 *
 * Dua seri: "Proposal terkirim" (biru) dan "Proposal disetujui" (hijau).
 * Data dikirim dari backend (`ProposalService::trend()`) dan berubah
 * mengikuti periode yang dipilih (7 / 30 / 90 hari).
 */
const props = defineProps<{
    trend: ProposalTrend;
    periode: ProposalPeriod;
}>();

const emit = defineEmits<{
    'update:periode': [value: ProposalPeriod];
}>();

/** Dimensi viewBox SVG (lebar x tinggi). */
const W = 720;
const H = 260;
const PAD = { top: 18, right: 18, bottom: 36, left: 46 };

const plotW = W - PAD.left - PAD.right;
const plotH = H - PAD.top - PAD.bottom;

const periods: { label: string; value: ProposalPeriod }[] = [
    { label: '7 Hari', value: 7 },
    { label: '30 Hari', value: 30 },
    { label: '90 Hari', value: 90 },
];

const labels = computed(() => props.trend?.labels ?? []);
const sent = computed(() => props.trend?.sent ?? []);
const approved = computed(() => props.trend?.approved ?? []);
const total = computed(() => labels.value.length);

/** Nilai maksimum sumbu Y, dibulatkan ke angka yang enak dibaca. */
const maxValue = computed(() => {
    const peak = Math.max(0, ...sent.value, ...approved.value);

    if (peak <= 4) {
        return 4;
    }

    if (peak <= 10) {
        return 10;
    }

    return Math.ceil(peak / 5) * 5;
});

/** Label sumbu Y (4 garis bantu). */
const yTicks = computed(() => {
    const max = maxValue.value;

    return [0, 0.25, 0.5, 0.75, 1].map((ratio) => ({
        value: Math.round(max * ratio),
        y: PAD.top + plotH - plotH * ratio,
    }));
});

/** Every-nth label sumbu X supaya tidak bertumpuk. */
const xLabelStep = computed(() => Math.max(1, Math.ceil(total.value / 7)));

function xAt(index: number): number {
    if (total.value <= 1) {
        return PAD.left + plotW / 2;
    }

    return PAD.left + (plotW * index) / (total.value - 1);
}

function yAt(value: number): number {
    return PAD.top + plotH - (plotH * value) / maxValue.value;
}

function points(values: number[]): string {
    return values
        .map(
            (value, index) =>
                `${xAt(index).toFixed(2)},${yAt(value).toFixed(2)}`,
        )
        .join(' ');
}

/** Indeks titik yang sedang di-hover. */
const hoverIndex = ref<number | null>(null);

const hoverState = computed(() => {
    if (hoverIndex.value === null) {
        return null;
    }

    const index = hoverIndex.value;

    return {
        index,
        x: xAt(index),
        label: labels.value[index] ?? '',
        sent: sent.value[index] ?? 0,
        approved: approved.value[index] ?? 0,
    };
});

function onMove(event: MouseEvent): void {
    const target = event.currentTarget as SVGGraphicsElement;
    const rect = target.getBoundingClientRect();

    if (rect.width === 0 || total.value === 0) {
        return;
    }

    const viewX = ((event.clientX - rect.left) / rect.width) * W;

    if (total.value === 1) {
        hoverIndex.value = 0;

        return;
    }

    const ratio = (viewX - PAD.left) / plotW;
    const index = Math.round(ratio * (total.value - 1));

    hoverIndex.value = Math.min(total.value - 1, Math.max(0, index));
}

function onLeave(): void {
    hoverIndex.value = null;
}
</script>

<template>
    <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-[15px] font-semibold text-sm-navy">
                    Tren Proposal Sponsorship ({{ periode }} Hari Terakhir)
                </h2>
                <p class="mt-0.5 text-xs text-gray-500">
                    Perbandingan proposal terkirim dan proposal disetujui.
                </p>
            </div>

            <div
                class="flex items-center gap-1 rounded-lg border border-gray-200 bg-gray-50 p-1"
                role="group"
                aria-label="Pilih periode grafik"
            >
                <button
                    v-for="item in periods"
                    :key="item.value"
                    type="button"
                    class="rounded-md px-2.5 py-1 text-xs font-medium transition-colors"
                    :class="
                        periode === item.value
                            ? 'bg-sm-navy text-white shadow-sm'
                            : 'text-gray-500 hover:text-sm-navy'
                    "
                    @click="emit('update:periode', item.value)"
                >
                    {{ item.label }}
                </button>
            </div>
        </header>

        <!-- Legenda -->
        <div class="mt-4 flex items-center gap-4 text-[11px] text-gray-500">
            <span class="flex items-center gap-1.5">
                <span class="size-2.5 rounded-full bg-blue-600"></span>
                Proposal terkirim
            </span>
            <span class="flex items-center gap-1.5">
                <span class="size-2.5 rounded-full bg-emerald-600"></span>
                Proposal disetujui
            </span>
        </div>

        <div
            v-if="total === 0"
            class="mt-6 flex h-48 items-center justify-center rounded-xl bg-gray-50"
        >
            <p class="text-sm text-gray-400">
                Belum ada data proposal pada periode ini.
            </p>
        </div>

        <div v-else class="relative mt-3">
            <svg
                :viewBox="`0 0 ${W} ${H}`"
                class="h-auto w-full"
                role="img"
                aria-label="Grafik tren proposal sponsorship"
                @mouseleave="onLeave"
            >
                <!-- Garis bantu + label sumbu Y -->
                <g>
                    <line
                        v-for="tick in yTicks"
                        :key="`grid-${tick.value}`"
                        :x1="PAD.left"
                        :x2="W - PAD.right"
                        :y1="tick.y"
                        :y2="tick.y"
                        stroke="#ececf1"
                        stroke-width="1"
                    />
                    <text
                        v-for="tick in yTicks"
                        :key="`ylabel-${tick.value}`"
                        :x="PAD.left - 10"
                        :y="tick.y + 3"
                        text-anchor="end"
                        class="fill-gray-400"
                        font-size="10"
                    >
                        {{ tick.value }}
                    </text>
                </g>

                <!-- Label sumbu X (tanggal) -->
                <g>
                    <text
                        v-for="(label, index) in labels"
                        v-show="index % xLabelStep === 0 || index === total - 1"
                        :key="`xlabel-${index}`"
                        :x="xAt(index)"
                        :y="H - PAD.bottom + 18"
                        text-anchor="middle"
                        class="fill-gray-400"
                        font-size="10"
                    >
                        {{ label }}
                    </text>
                </g>

                <!-- Seri: proposal terkirim -->
                <polyline
                    :points="points(sent)"
                    fill="none"
                    stroke="#2f64d1"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />

                <!-- Seri: proposal disetujui -->
                <polyline
                    :points="points(approved)"
                    fill="none"
                    stroke="#16a34a"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />

                <!-- Titik data saat hover -->
                <g v-if="hoverState">
                    <line
                        :x1="hoverState.x"
                        :x2="hoverState.x"
                        :y1="PAD.top"
                        :y2="PAD.top + plotH"
                        stroke="#c7cbd6"
                        stroke-width="1"
                        stroke-dasharray="4 4"
                    />
                    <circle
                        :cx="hoverState.x"
                        :cy="yAt(hoverState.sent)"
                        r="4"
                        fill="#2f64d1"
                        stroke="#ffffff"
                        stroke-width="2"
                    />
                    <circle
                        :cx="hoverState.x"
                        :cy="yAt(hoverState.approved)"
                        r="4"
                        fill="#16a34a"
                        stroke="#ffffff"
                        stroke-width="2"
                    />
                </g>

                <!-- Area hover -->
                <rect
                    :x="PAD.left"
                    :y="PAD.top"
                    :width="plotW"
                    :height="plotH"
                    fill="transparent"
                    @mousemove="onMove"
                />
            </svg>

            <!-- Tooltip -->
            <div
                v-if="hoverState"
                class="pointer-events-none absolute z-10 -translate-x-1/2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs shadow-lg"
                :style="{
                    left: `${(hoverState.x / W) * 100}%`,
                    top: `${(PAD.top / H) * 100}%`,
                }"
            >
                <p class="font-semibold text-sm-navy">{{ hoverState.label }}</p>
                <p class="mt-1 flex items-center gap-1.5 text-gray-600">
                    <span class="size-2 rounded-full bg-blue-600"></span>
                    Terkirim: {{ hoverState.sent }}
                </p>
                <p class="mt-0.5 flex items-center gap-1.5 text-gray-600">
                    <span class="size-2 rounded-full bg-emerald-600"></span>
                    Disetujui: {{ hoverState.approved }}
                </p>
            </div>
        </div>
    </section>
</template>
