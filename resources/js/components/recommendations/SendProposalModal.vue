<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Building2, Megaphone, Send } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { store as sendProposal } from '@/routes/proposals/send';
import type { ProposalOption, SendableCompany } from '@/types/recommendation';

const props = defineProps<{
    open: boolean;
    recommendation: SendableCompany | null;
    proposals: ProposalOption[];
    defaultProposalId: number | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const selectedProposal = ref<number | null>(null);
const selectedCampaign = ref<string>('');
const message = ref('');

const form = useForm({
    sponsor_ids: [] as number[],
    campaign_id: null as number | null,
    message: '',
    redirect: 'recommendations' as const,
});

const campaigns = computed(() => props.recommendation?.campaign_options ?? []);

const selectedProposalTitle = computed(
    () =>
        props.proposals.find((proposal) => proposal.id === selectedProposal.value)
            ?.title ?? '',
);

watch(
    () => props.open,
    (open) => {
        if (!open) {
            return;
        }

        selectedProposal.value =
            props.defaultProposalId ?? props.proposals[0]?.id ?? null;
        selectedCampaign.value = '';
        message.value = '';
        form.clearErrors();
        form.reset();
    },
    { immediate: true },
);

watch(
    () => props.recommendation?.company_id,
    () => {
        selectedCampaign.value = '';
        form.clearErrors();
    },
);

function close(): void {
    emit('update:open', false);
}

function submit(): void {
    if (!props.recommendation || selectedProposal.value === null) {
        return;
    }

    form.sponsor_ids = [props.recommendation.company_id];
    form.campaign_id =
        selectedCampaign.value === '' ? null : Number(selectedCampaign.value);
    form.message = message.value;

    form.post(sendProposal(selectedProposal.value).url, {
        preserveScroll: true,
        onSuccess: () => {
            close();
            form.reset();
        },
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle class="text-lg font-semibold text-sm-navy">
                    Kirim Proposal
                </DialogTitle>
                <DialogDescription class="text-xs text-sm-card/70">
                    Pilih proposal dan campaign tujuan. Campaign bersifat opsional —
                    kamu bisa mengirim langsung ke perusahaan.
                </DialogDescription>
            </DialogHeader>

            <div
                v-if="recommendation"
                class="flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 p-3"
            >
                <div
                    class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-gray-200 bg-white"
                >
                    <img
                        v-if="recommendation.logo_url"
                        :src="recommendation.logo_url"
                        :alt="`Logo ${recommendation.company_name}`"
                        class="h-full w-full object-contain p-1"
                    />
                    <Building2 v-else class="h-5 w-5 text-gray-400" />
                </div>
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-sm-navy">
                        {{ recommendation.company_name }}
                    </p>
                    <p class="truncate text-xs text-sm-card/65">
                        {{ recommendation.industry ?? 'Perusahaan sponsor' }}
                    </p>
                </div>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label for="proposal" class="mb-1 block text-xs font-semibold text-sm-navy">
                        Proposal yang dikirim <span class="text-sm-red">*</span>
                    </label>
                    <select
                        id="proposal"
                        v-model="selectedProposal"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-sm-navy outline-none focus:border-sm-red focus:ring-2 focus:ring-sm-red/15"
                    >
                        <option
                            v-for="proposal in proposals"
                            :key="proposal.id"
                            :value="proposal.id"
                        >
                            {{ proposal.title }} ({{ proposal.status }})
                        </option>
                    </select>
                    <InputError :message="form.errors.sponsor_ids" class="mt-1" />
                </div>

                <div>
                    <label for="campaign" class="mb-1 block text-xs font-semibold text-sm-navy">
                        Campaign tujuan (opsional)
                    </label>
                    <select
                        id="campaign"
                        v-model="selectedCampaign"
                        class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-sm-navy outline-none focus:border-sm-red focus:ring-2 focus:ring-sm-red/15"
                    >
                        <option value="">
                            Kirim langsung ke perusahaan (tanpa campaign)
                        </option>
                        <option
                            v-for="campaign in campaigns"
                            :key="campaign.id"
                            :value="String(campaign.id)"
                        >
                            {{ campaign.title }}
                        </option>
                    </select>
                    <p
                        v-if="campaigns.length === 0"
                        class="mt-1 flex items-center gap-1 text-[11px] text-sm-card/60"
                    >
                        <Megaphone class="h-3 w-3" />
                        Perusahaan ini belum membuka campaign aktif. Proposal tetap dapat dikirim.
                    </p>
                    <InputError :message="form.errors.campaign_id" class="mt-1" />
                </div>

                <div>
                    <label for="message" class="mb-1 block text-xs font-semibold text-sm-navy">
                        Pesan pengantar (opsional)
                    </label>
                    <textarea
                        id="message"
                        v-model="message"
                        rows="3"
                        maxlength="1000"
                        class="w-full resize-y rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-sm-navy outline-none focus:border-sm-red focus:ring-2 focus:ring-sm-red/15"
                        placeholder="Ceritakan singkat alasan proposal ini cocok untuk perusahaan tersebut..."
                    ></textarea>
                    <InputError :message="form.errors.message" class="mt-1" />
                </div>

                <DialogFooter class="gap-2 border-t border-gray-100 pt-4">
                    <button
                        type="button"
                        class="h-9 rounded-lg border border-gray-200 px-3 text-xs font-semibold text-gray-600 transition-colors hover:bg-gray-50"
                        @click="close"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing || !recommendation || selectedProposal === null"
                        class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-sm-red px-4 text-xs font-semibold text-white transition-colors hover:bg-sm-red-dark disabled:opacity-60"
                    >
                        <Send class="h-3.5 w-3.5" />
                        {{ form.processing ? 'Mengirim...' : 'Kirim Proposal' }}
                    </button>
                </DialogFooter>

                <p v-if="selectedProposalTitle" class="text-[11px] text-sm-card/55">
                    Proposal: {{ selectedProposalTitle }}
                </p>
            </form>
        </DialogContent>
    </Dialog>
</template>
