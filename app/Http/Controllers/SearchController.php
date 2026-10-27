<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if ($q === '') {
            return response()->json(['results' => []]);
        }

        $results = User::query()
            ->whereIn('role', ['eo', 'sponsor'])
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            })
            ->with(['companyProfile', 'eoProfile'])
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->map(function (User $user) {
                $roleLabel = $user->role === 'sponsor' ? 'Perusahaan' : 'Event Organizer';

                $subtitle = match ($user->role) {
                    'sponsor' => $user->companyProfile?->companyName ?? $user->email,
                    'eo' => $user->eoProfile?->namaEventOrganizer ?? $user->email,
                    default => $user->email,
                };

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'roleLabel' => $roleLabel,
                    'subtitle' => $subtitle,
                    'url' => route('profile.show', $user),
                ];
            });

        return response()->json(['results' => $results]);
    }
}
