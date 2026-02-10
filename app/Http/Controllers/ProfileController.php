<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'tenant' => $request->user()->conta,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Update user preferences (locale, timezone, theme, location).
     */
    public function updatePreferences(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;
        if ($request->filled('tenant_slug')) {
            $request->merge([
                'tenant_slug' => \Illuminate\Support\Str::slug((string) $request->input('tenant_slug')),
            ]);
        }
        $hasSlugColumn = \Illuminate\Support\Facades\Schema::hasColumn('contas', 'slug');
        $data = $request->validate([
            'locale' => ['nullable', 'string', 'max:10'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'theme' => ['nullable', 'in:light,dark,system'],
            'location_city' => ['nullable', 'string', 'max:120'],
            'location_state' => ['nullable', 'string', 'max:120'],
            'location_country' => ['nullable', 'string', 'max:120'],
            'tenant_slug' => $hasSlugColumn ? [
                'nullable',
                'string',
                'min:4',
                'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $tenant
                    ? Rule::unique('contas', 'slug')->ignore($tenant->id)
                    : Rule::unique('contas', 'slug'),
            ] : ['nullable'],
        ]);

        if (empty($data['theme']) || $data['theme'] === 'system') {
            $data['theme'] = 'light';
        }

        $request->user()->fill($data);
        $request->user()->save();

        if ($hasSlugColumn && array_key_exists('tenant_slug', $data) && $tenant) {
            $tenant->slug = $data['tenant_slug'];
            $tenant->save();
        }

        return Redirect::route('profile.edit')->with('status', 'preferences-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
