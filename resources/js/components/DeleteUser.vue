<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { TriangleAlert, Trash2 } from '@lucide/vue';
import { computed, useTemplateRef } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

/**
 * Zona Berbahaya — penghapusan akun.
 *
 * Penghapusan memerlukan password + konfirmasi password. Server juga
 * menolak penghapusan selama masih ada kerja sama aktif (lihat
 * ProfileController). Di sisi tampilan, tombol dinonaktifkan agar
 * pengguna mendapat penjelasan sebelum mencoba.
 */
const props = withDefaults(
    defineProps<{
        activeCollaborationCount?: number;
    }>(),
    {
        activeCollaborationCount: 0,
    },
);

const passwordInput = useTemplateRef('passwordInput');
const canDelete = computed(() => props.activeCollaborationCount === 0);
</script>

<template>
    <section
        class="flex flex-col gap-5 rounded-2xl border border-[#F3C6CB] bg-[#FDF2F3] p-6 sm:flex-row sm:items-center sm:justify-between sm:p-8"
        aria-labelledby="danger-zone-heading"
    >
        <div class="flex items-start gap-4">
            <span
                class="flex size-10 shrink-0 items-center justify-center rounded-full text-sm-red"
                aria-hidden="true"
            >
                <TriangleAlert class="size-6" />
            </span>
            <div class="min-w-0">
                <h2
                    id="danger-zone-heading"
                    class="text-lg font-bold text-sm-red"
                >
                    Zona Berbahaya
                </h2>
                <p class="mt-1 text-sm text-sm-card/70">
                    Akun yang dihapus tidak bisa dipakai untuk masuk lagi.
                    Riwayat kerja sama tetap tersimpan untuk mitra kamu.
                </p>
                <p
                    v-if="!canDelete"
                    class="mt-2 text-sm font-medium text-sm-red"
                    role="alert"
                >
                    Akun belum bisa dihapus: masih ada
                    {{ activeCollaborationCount }} kerja sama aktif.
                    Selesaikan semuanya terlebih dahulu.
                </p>
            </div>
        </div>

        <Dialog>
            <DialogTrigger as-child>
                <Button
                    variant="outline"
                    class="h-11 w-full shrink-0 rounded-lg border-sm-red/50 bg-white/70 px-5 font-semibold text-sm-red hover:bg-white hover:text-sm-red-dark sm:w-auto"
                    :disabled="!canDelete"
                    data-test="delete-user-button"
                >
                    <Trash2 class="size-4" aria-hidden="true" />
                    Hapus Akun
                </Button>
            </DialogTrigger>

            <DialogContent class="sm:max-w-md">
                <Form
                    v-bind="ProfileController.destroy.form()"
                    reset-on-success
                    :options="{ preserveScroll: true }"
                    class="space-y-5"
                    v-slot="{ errors, processing, reset, clearErrors }"
                    @error="() => passwordInput?.focus()"
                >
                    <DialogHeader class="space-y-2">
                        <DialogTitle>Hapus akun?</DialogTitle>
                        <DialogDescription>
                            Peringatan: akun kamu akan dihapus dan tidak bisa
                            dipakai untuk masuk lagi. Masukkan password dan
                            konfirmasi password untuk melanjutkan.
                        </DialogDescription>
                    </DialogHeader>

                    <div class="grid gap-2">
                        <Label for="delete-password">Password</Label>
                        <PasswordInput
                            id="delete-password"
                            name="password"
                            ref="passwordInput"
                            placeholder="Masukkan password"
                            autocomplete="current-password"
                        />
                        <InputError :message="errors.password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="delete-password-confirmation">
                            Konfirmasi Password
                        </Label>
                        <PasswordInput
                            id="delete-password-confirmation"
                            name="password_confirmation"
                            placeholder="Ulangi password"
                            autocomplete="current-password"
                        />
                    </div>

                    <InputError :message="errors.account" />

                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button
                                type="button"
                                variant="outline"
                                class="h-11 rounded-lg px-5"
                                @click="
                                    () => {
                                        clearErrors();
                                        reset();
                                    }
                                "
                            >
                                Batal
                            </Button>
                        </DialogClose>

                        <Button
                            type="submit"
                            variant="destructive"
                            class="h-11 rounded-lg px-5"
                            :disabled="processing"
                            data-test="confirm-delete-user-button"
                        >
                            <Spinner v-if="processing" class="size-4" />
                            {{ processing ? 'Menghapus...' : 'Hapus Akun' }}
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </section>
</template>