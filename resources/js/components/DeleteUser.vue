<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { TriangleAlert, Trash2 } from '@lucide/vue';
import { useTemplateRef } from 'vue';
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
 * Endpoint & pengamanan TIDAK berubah: `DELETE /settings/profile` dengan
 * konfirmasi password (lihat ProfileDeleteRequest). Komponen ini hanya
 * dirapikan tampilannya agar menyatu dengan halaman Profil.
 */
const passwordInput = useTemplateRef('passwordInput');
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
                    Penghapusan akun bersifat permanen dan tidak dapat
                    dibatalkan.
                </p>
            </div>
        </div>

        <Dialog>
            <DialogTrigger as-child>
                <Button
                    variant="outline"
                    class="h-11 w-full shrink-0 rounded-lg border-sm-red/50 bg-white/70 px-5 font-semibold text-sm-red hover:bg-white hover:text-sm-red-dark sm:w-auto"
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
                        <DialogTitle>Hapus akun secara permanen?</DialogTitle>
                        <DialogDescription>
                            Seluruh data akun, proposal, dan riwayat kamu akan
                            dihapus dan tidak dapat dikembalikan. Masukkan
                            password untuk mengonfirmasi.
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
