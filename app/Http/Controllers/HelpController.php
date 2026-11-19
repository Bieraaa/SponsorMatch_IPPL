<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pusat bantuan untuk EO & perusahaan.
 *
 * FAQ dan tautan cepat disesuaikan peran. Halaman ini statis (tidak
 * menyimpan data) sehingga bisa dibuka kapan pun tanpa query tambahan.
 */
class HelpController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isCompany = $user?->isCompany() ?? false;

        $quickLinks = $isCompany
            ? [
                ['label' => 'Dashboard', 'url' => '/dashboard', 'description' => 'Ringkasan aktivitas perusahaan.'],
                ['label' => 'Campaign Saya', 'url' => '/company/campaigns', 'description' => 'Buat dan kelola campaign sponsorship.'],
                ['label' => 'Rekomendasi Event', 'url' => '/company/recommendations', 'description' => 'Lihat proposal EO yang paling cocok.'],
                ['label' => 'Proposal Masuk', 'url' => '/sponsor/proposals', 'description' => 'Tinjau proposal dan kirim penawaran.'],
                ['label' => 'Riwayat Kolaborasi', 'url' => '/collaborations', 'description' => 'Laporan event dan ulasan kerja sama.'],
                ['label' => 'Langganan', 'url' => '/billing', 'description' => 'Paket dan informasi pembayaran.'],
            ]
            : [
                ['label' => 'Dashboard', 'url' => '/dashboard', 'description' => 'Ringkasan progres sponsorship.'],
                ['label' => 'Proposal Saya', 'url' => '/proposals', 'description' => 'Buat, kirim, dan pantau proposal.'],
                ['label' => 'Rekomendasi Sponsor', 'url' => '/recommendations', 'description' => 'Temukan perusahaan yang paling cocok.'],
                ['label' => 'Penawaran', 'url' => '/offers', 'description' => 'Kelola negosiasi dengan sponsor.'],
                ['label' => 'Riwayat Kolaborasi', 'url' => '/collaborations', 'description' => 'Kirim laporan event dan ulasan.'],
                ['label' => 'Langganan', 'url' => '/billing', 'description' => 'Paket dan informasi pembayaran.'],
            ];

        $faqs = $isCompany
            ? [
                ['question' => 'Apakah campaign wajib dibuat sebelum menerima proposal?', 'answer' => 'Tidak. Campaign bersifat opsional. Selama profil perusahaan lengkap, perusahaan tetap dapat menerima proposal dan rekomendasi dari Event Organizer.'],
                ['question' => 'Bagaimana cara mengajukan penawaran ke EO?', 'answer' => 'Buka halaman Rekomendasi Event atau Proposal Masuk, pilih proposal, lalu isi jenis dukungan dan nominal pada halaman penawaran.'],
                ['question' => 'Bagaimana cara menyelesaikan kolaborasi?', 'answer' => 'Setelah penawaran disetujui, EO mengirim laporan realisasi event. Setelah laporan tersedia, kedua pihak dapat saling memberi ulasan di halaman Riwayat Kolaborasi.'],
                ['question' => 'Apakah data pembayaran saya aman?', 'answer' => 'Halaman pembayaran belum terhubung ke gateway. Saat integrasi aktif, seluruh transaksi akan diproses oleh penyedia pembayaran resmi dan aplikasi tidak menyimpan data kartu.'],
            ]
            : [
                ['question' => 'Bagaimana cara membuat proposal yang baik?', 'answer' => 'Lengkapi tujuan event, target audiens, anggaran, dan paket benefit. Unggah PDF proposal agar sponsor dapat meninjau detailnya.'],
                ['question' => 'Apakah proposal harus dikirim manual ke tiap sponsor?', 'answer' => 'Anda dapat memakai Rekomendasi Sponsor untuk melihat perusahaan yang paling cocok, lalu mengirim proposal dari halaman tersebut.'],
                ['question' => 'Apa yang harus dilakukan setelah penawaran disetujui?', 'answer' => 'Kolaborasi berstatus berjalan. Setelah event selesai, buka Riwayat Kolaborasi dan kirim laporan realisasi agar status menjadi selesai.'],
                ['question' => 'Bagaimana cara mendapatkan ulasan yang baik?', 'answer' => 'Kirim laporan event tepat waktu dan lengkap. Setelah kolaborasi selesai, kedua pihak dapat saling memberi ulasan yang tampil di profil publik.'],
            ];

        return Inertia::render('help/Index', [
            'role' => $isCompany ? 'company' : 'eo',
            'quickLinks' => $quickLinks,
            'faqs' => $faqs,
            'contact' => [
                'email' => 'halo@sponsormatch.id',
                'hours' => 'Senin–Jumat, 09.00–17.00 WIB',
                'response' => 'Kami membalas dalam 1×24 jam kerja.',
            ],
        ]);
    }
}
