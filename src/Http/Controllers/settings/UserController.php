<?php

namespace Optima\DepotStock\Http\Controllers\Settings;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Optima\DepotStock\Models\Client;
use Optima\DepotStock\Models\Role;

class UserController extends Controller
{
    /**
     * List users + search + show roles.
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $users = User::with(['roles', 'client'])
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                      ->orWhere('email', 'like', "%{$q}%")
                      ->orWhere('id', $q);
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $roles = Role::orderBy('name')->get();
        $clients = Client::orderBy('name')->get();

        return view('depot-stock::settings.users.index', [
            'users'      => $users,
            'roles'      => $roles,
            'clients'    => $clients,
            'q'          => $q,
            'resetFor'   => session('reset_password_for'),
            'resetPlain' => session('reset_plain_password'),
        ]);
    }

    /**
     * Create new user.
     */
    public function create(Request $request)
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'email'     => ['required', 'email', 'max:255', 'unique:users,email'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'password'  => ['nullable', 'string', 'min:8'],
            'roles'     => ['array'],
            'roles.*'   => ['integer', 'exists:roles,id'],
        ]);

        // Use manual password or generate one
        $plain = $data['password'] ?: Str::random(12);

        $user = new User();
        $user->name      = $data['name'];
        $user->email     = $data['email'];
        $user->client_id = $data['client_id'] ?? null;
        $user->password  = Hash::make($plain);
        $user->setRememberToken(Str::random(60));
        $user->save();

        // Roles
        $roleIds = $data['roles'] ?? [];

        // If linked to client → ensure client role present
        if ($user->client_id) {
            $clientRoleId = Role::where('name', 'client')->value('id');
            if ($clientRoleId) {
                $roleIds = array_unique(array_merge($roleIds, [$clientRoleId]));
            }
        }

        $user->roles()->sync($roleIds);

        return redirect()
            ->route('depot.settings.users.index')
            ->with('reset_password_for', $user->id)
            ->with('reset_plain_password', $plain);
    }

    /**
     * Update roles + client link.
     */
    public function updateRoles(Request $request, User $user)
    {
        $data = $request->validate([
            'roles'     => ['array'],
            'roles.*'   => ['integer', 'exists:roles,id'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
        ]);

        // persist chosen client
        if (!empty($data['client_id'])) {
            $user->client_id = $data['client_id'];
            $user->save();
        }

        $roleIds = $data['roles'] ?? [];

        // Ensure client role for client-linked users
        if ($user->client_id) {
            $clientRoleId = Role::where('name', 'client')->value('id');
            if ($clientRoleId) {
                $roleIds = array_unique(array_merge($roleIds, [$clientRoleId]));
            }
        }

        $user->roles()->sync($roleIds);

        return back()->with('success', 'User roles updated.');
    }

    /**
     * Reset password.
     * Uses 'manual_password' if provided, otherwise generates new random password.
     */
    public function resetPassword(Request $request, User $user)
    {
        $data = $request->validate([
            'manual_password' => ['nullable', 'string', 'min:8'],
        ]);

        // Use the manual one OR auto-generate
        $plain = $data['manual_password'] ?: Str::random(12);

        $user->password = Hash::make($plain);
        $user->setRememberToken(Str::random(60));
        $user->save();

        return redirect()
            ->route('depot.settings.users.index')
            ->with('reset_password_for', $user->id)
            ->with('reset_plain_password', $plain);
    }

  

// ...

public function updateBasic(Request $request, User $user)
{
    // 1) Validate incoming data
    $data = $request->validate([
        'name'      => ['required', 'string', 'max:255'],
        'email'     => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
        'client_id' => ['nullable', 'exists:clients,id'],
    ]);

    // 2) Update basic fields
    $user->name      = $data['name'];
    $user->email     = $data['email'];
    $user->client_id = $data['client_id'] ?? null;
    $user->save();

    // 3) Keep "client" role in sync with client_id
    //    IMPORTANT: query Role directly and qualify the column.
    $clientRoleId = Role::where('name', 'client')
        ->select('roles.id')
        ->value('roles.id');

    if ($clientRoleId) {
        if (!empty($data['client_id'])) {
            // has a client linked -> make sure CLIENT role is attached
            $user->roles()->syncWithoutDetaching([$clientRoleId]);
        } else {
            // no client linked -> remove CLIENT role if it exists
            $user->roles()->detach($clientRoleId);
        }
    }

    return back()->with('status', 'User details updated.');
}
}