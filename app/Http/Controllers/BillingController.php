<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman langganan & pembayaran (tampilan saja).
 *
 * Gateway pembayaran BELUM diintegrasikan. Halaman ini menyiapkan struktur
 * paket, metode pembayaran, ringkasan pesanan, dan riwayat transaksi agar
 * integrasi berikutnya tidak perlu mengubah tata letak. Tidak ada transaksi
 * atau perubahan status langganan yang dilakukan di sini.
 */
class BillingController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user, 403);

        $currentPlan = $user->status_subscription === 'Subscription'
            ? 'premium'
            : 'free';

        return Inertia::render('billing/Index', [
            'role' => $user->isCompany() ? 'company' : 'eo',
            'current_plan' => $currentPlan,
            'current_plan_label' => $currentPlan === 'premium' ? 'Premium' : 'Reguler',
            'plans' => [
                [
                    'key' => 'free',
                    'name' => 'Reguler',
                    'description' => 'Cukup untuk mulai membangun kolaborasi sponsorship.',
                    'price_monthly' => 0,
                    'price_yearly' => 0,
                    'features' => [
                        'Profil perusahaan / Event Organizer',
                        'Postingan dan pencarian akun',
                        'Rekomendasi dasar dari data profil',
                        'Negosiasi penawaran tanpa batas',
                    ],
                    'highlight' => false,
                ],
                [
                    'key' => 'premium',
                    'name' => 'Premium',
                    'description' => 'Untuk tim yang aktif mengelola banyak kolaborasi.',
                    'price_monthly' => 99000,
                    'price_yearly' => 990000,
                    'features' => [
                        'Semua fitur Reguler',
                        'Analisis rekomendasi AI lebih sering',
                        'Prioritas tampil di rekomendasi mitra',
                        'Riwayat kolaborasi & laporan event',
                        'Dukungan prioritas',
                    ],
                    'highlight' => true,
                ],
            ],
            'payment_methods' => [
                ['key' => 'va', 'name' => 'Virtual Account', 'description' => 'BCA, Mandiri, BNI, BRI'],
                ['key' => 'qris', 'name' => 'QRIS', 'description' => 'Semua aplikasi pembayaran'],
                ['key' => 'ewallet', 'name' => 'E-Wallet', 'description' => 'GoPay, OVO, DANA'],
            ],
            'invoices' => [],
            'payment_ready' => false,
        ]);
    }
}
