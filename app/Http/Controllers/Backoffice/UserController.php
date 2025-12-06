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
        return view('backoffice.' . $this->name . '.create', compact('roles'));
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:4|confirmed',
                'role' => 'required|in:admin,operator',
            ], [
                'name.required' => 'Il nome è obbligatorio',
                'email.required' => 'L\'email è obbligatoria',
                'email.email' => 'Inserisci un\'email valida',
                'email.unique' => 'Questa email è già registrata',
                'password.required' => 'La password è obbligatoria',
                'password.min' => 'La password deve essere di almeno 8 caratteri',
                'password.confirmed' => 'Le password non coincidono',
                'role.required' => 'Il ruolo è obbligatorio',
                'role.in' => 'Ruolo non valido',
            ]);

            DB::beginTransaction();

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'],
            ]);

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
        $user = User::findOrFail($id);
        $roles = Utils::key_value((new User())->roles());
        return view('backoffice.' . $this->name . '.edit', compact('user', 'roles'));
    }

    /**
     * Update the specified user
     */
    public function edit(Request $request, $id): JsonResponse
    {
        try {
            $user = User::findOrFail($id);

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
                'password' => 'nullable|string|min:8|confirmed',
                'role' => 'required|in:admin,operator',
            ], [
                'name.required' => 'Il nome è obbligatorio',
                'email.required' => 'L\'email è obbligatoria',
                'email.email' => 'Inserisci un\'email valida',
                'email.unique' => 'Questa email è già registrata',
                'password.min' => 'La password deve essere di almeno 8 caratteri',
                'password.confirmed' => 'Le password non coincidono',
                'role.required' => 'Il ruolo è obbligatorio',
                'role.in' => 'Ruolo non valido',
            ]);

            DB::beginTransaction();

            $updateData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => $validated['role'],
            ];

            // Update password only if provided
            if (!empty($validated['password'])) {
                $updateData['password'] = Hash::make($validated['password']);
            }

            $user->update($updateData);

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
