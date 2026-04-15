<?php

namespace App\Http\Controllers\Backoffice;

use App\Facades\Utils;
use App\Models\User;
use App\Traits\DatatableTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends BaseController
{
    use DatatableTrait;

    protected string $name;

    public function __construct()
    {
        $this->name = 'users';
    }

    /**
     * Display a listing of users
     */
    public function index(): View
    {
        return view('backoffice.' . $this->name . '.index');
    }

    /**
     * Get datatable data
     */
    public function datatable(Request $request): JsonResponse
    {
        try {
            $filters = $request->get('filters') ?? [];

            $query = User::query()->orderBy('created_at', 'desc');

            // Apply filters
            if (!empty($filters['role'])) {
                $query->where('role', $filters['role']);
            }

            if (!empty($filters['search'])) {
                $query->where(function ($q) use ($filters) {
                    $q->where('name', 'like', '%' . $filters['search'] . '%')
                      ->orWhere('email', 'like', '%' . $filters['search'] . '%');
                });
            }

            $elements = $query->get();

            return $this->editColumns(
                datatables()->of($elements),
                $this->name,
                ['edit'],
                null,
                'users'
            )
                ->addColumn('user_info', function ($item) {
                    return '<strong>' . $item->name . '</strong><br><small>' . $item->email . '</small>';
                })
                ->addColumn('role_label', function ($item) {
                    $roles = $item->roles();
                    $roleLabel = $roles[$item->role] ?? $item->role;
                    $badgeClass = $item->role === 'admin' ? 'badge-danger' : 'badge-info';
                    return '<span class="badge ' . $badgeClass . '">' . $roleLabel . '</span>';
                })
                ->addColumn('created', function ($item) {
                    return $item->created_at->format('d/m/Y H:i');
                })
                ->addColumn('actions_custom', function ($item) {
                    $html = '<div class="btn-group">';
                    $html .= '<a href="' . route('users.show', $item->id) . '" class="btn btn-sm btn-primary" title="Modifica">';
                    $html .= '<i class="fas fa-edit"></i></a>';

                    // Non permettere di eliminare se stesso
                    if (auth()->id() !== $item->id) {
                        $html .= '<button class="btn btn-sm btn-danger btn-delete-user" data-id="' . $item->id . '" title="Elimina">';
                        $html .= '<i class="fas fa-trash"></i></button>';
                    }

                    $html .= '</div>';
                    return $html;
                })
                ->rawColumns(['user_info', 'role_label', 'actions_custom', 'action'])
                ->make(true);

        } catch (Exception $e) {
            Log::error('Error in UserController datatable: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Show the form for creating a new user
     */
    public function create(): View
    {
        $roles = Utils::key_value((new User())->roles());
        $availablePermissions = User::availablePermissions();
        return view('backoffice.' . $this->name . '.create', compact('roles', 'availablePermissions'));
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $role = $request->input('role');

            if ($role === 'admin') {
                $validated = $request->validate([
                    'name' => 'required|string|max:255',
                    'email' => 'required|email|unique:users,email',
                    'role' => 'required|in:admin,operator',
                    'backoffice_password' => 'required|string|min:6|confirmed',
                    'authentication_pin' => 'required|digits_between:1,5|unique:users,authentication_pin',
                ], [
                    'name.required' => 'Il nome è obbligatorio',
                    'email.required' => 'L\'email è obbligatoria',
                    'email.email' => 'Inserisci un\'email valida',
                    'email.unique' => 'Questa email è già registrata',
                    'backoffice_password.required' => 'La password backoffice è obbligatoria',
                    'backoffice_password.min' => 'La password deve essere di almeno 6 caratteri',
                    'backoffice_password.confirmed' => 'Le password non coincidono',
                    'authentication_pin.required' => 'Il PIN di autenticazione è obbligatorio',
                    'authentication_pin.digits_between' => 'Il PIN deve essere numerico, da 1 a 5 cifre',
                    'authentication_pin.unique' => 'Questo PIN è già assegnato a un altro utente',
                    'role.required' => 'Il ruolo è obbligatorio',
                    'role.in' => 'Ruolo non valido',
                ]);

                DB::beginTransaction();

                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'role' => 'admin',
                    'backoffice_password' => $validated['backoffice_password'],
                    'authentication_pin' => $validated['authentication_pin'],
                    'permissions' => null,
                ]);
            } else {
                $validated = $request->validate([
                    'name' => 'required|string|max:255',
                    'role' => 'required|in:admin,operator',
                    'password' => 'required|digits_between:1,5',
                    'permissions' => 'nullable|array',
                    'permissions.*' => 'string|in:' . implode(',', array_keys(User::availablePermissions())),
                ], [
                    'name.required' => 'Il nome è obbligatorio',
                    'password.required' => 'La password (PIN) è obbligatoria',
                    'password.digits_between' => 'La password deve essere numerica, da 1 a 5 cifre',
                    'role.required' => 'Il ruolo è obbligatorio',
                    'role.in' => 'Ruolo non valido',
                ]);

                DB::beginTransaction();

                $user = User::create([
                    'name' => $validated['name'],
                    'role' => 'operator',
                    'password' => Hash::make($validated['password']),
                    'permissions' => $validated['permissions'] ?? [],
                ]);
            }

            DB::commit();

            return $this->success([
                'message' => 'Utente creato con successo',
                'user' => $user,
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error creating user: ' . $e->getMessage());
            return $this->error(['message' => 'Errore nella creazione dell\'utente: ' . $e->getMessage()]);
        }
    }

    /**
     * Show the form for editing the specified user
     */
    public function show($id): View
    {
        $_user = User::findOrFail($id);
        $roles = Utils::key_value((new User())->roles());
        $availablePermissions = User::availablePermissions();
        return view('backoffice.' . $this->name . '.edit', compact('_user', 'roles', 'availablePermissions'));
    }

    /**
     * Update the specified user
     */
    public function edit(Request $request, $id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);
            $role = $request->input('role');

            if ($role === 'admin') {
                $validated = $request->validate([
                    'name' => 'required|string|max:255',
                    'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
                    'backoffice_password' => 'nullable|string|min:6|confirmed',
                    'authentication_pin' => ['nullable', 'digits_between:1,5', Rule::unique('users')->ignore($user->id)],
                    'role' => 'required|in:admin,operator',
                ], [
                    'name.required' => 'Il nome è obbligatorio',
                    'email.required' => 'L\'email è obbligatoria',
                    'email.email' => 'Inserisci un\'email valida',
                    'email.unique' => 'Questa email è già registrata',
                    'backoffice_password.min' => 'La password deve essere di almeno 6 caratteri',
                    'backoffice_password.confirmed' => 'Le password non coincidono',
                    'authentication_pin.digits_between' => 'Il PIN deve essere numerico, da 1 a 5 cifre',
                    'authentication_pin.unique' => 'Questo PIN è già assegnato a un altro utente',
                    'role.required' => 'Il ruolo è obbligatorio',
                    'role.in' => 'Ruolo non valido',
                ]);

                DB::beginTransaction();

                $updateData = [
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'role' => 'admin',
                    'permissions' => null,
                ];

                if (!empty($validated['backoffice_password'])) {
                    $updateData['backoffice_password'] = $validated['backoffice_password'];
                }

                if (!empty($validated['authentication_pin'])) {
                    $updateData['authentication_pin'] = $validated['authentication_pin'];
                }

                $user->update($updateData);
            } else {
                $validated = $request->validate([
                    'name' => 'required|string|max:255',
                    'role' => 'required|in:admin,operator',
                    'password' => 'nullable|digits_between:1,5',
                    'permissions' => 'nullable|array',
                    'permissions.*' => 'string|in:' . implode(',', array_keys(User::availablePermissions())),
                ], [
                    'name.required' => 'Il nome è obbligatorio',
                    'password.digits_between' => 'La password deve essere numerica, da 1 a 5 cifre',
                    'role.required' => 'Il ruolo è obbligatorio',
                    'role.in' => 'Ruolo non valido',
                ]);

                DB::beginTransaction();

                $updateData = [
                    'name' => $validated['name'],
                    'role' => 'operator',
                    'permissions' => $validated['permissions'] ?? [],
                ];

                if (!empty($validated['password'])) {
                    $updateData['password'] = Hash::make($validated['password']);
                }

                $user->update($updateData);
            }

            DB::commit();

            return $this->success([
                'message' => 'Utente aggiornato con successo',
                'user' => $user,
            ]);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error updating user: ' . $e->getMessage());
            return $this->error(['message' => 'Errore nell\'aggiornamento dell\'utente']);
        }
    }

    /**
     * Remove the specified user
     */
    public function destroy($id): JsonResponse
    {
        try {
            // Non permettere di eliminare se stesso
            if (auth()->id() == $id) {
                return $this->error(['message' => 'Non puoi eliminare il tuo account']);
            }

            $user = User::findOrFail($id);

            DB::beginTransaction();

            $user->delete();

            DB::commit();

            return $this->success(['message' => 'Utente eliminato con successo']);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error deleting user: ' . $e->getMessage());
            return $this->error(['message' => 'Errore nell\'eliminazione dell\'utente']);
        }
    }
}
