<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stancl\Tenancy\Tenant;
use Stancl\Tenancy\Database\DatabaseManager;
use Illuminate\Support\Facades\Validator;

class TenantController extends Controller
{
    protected $databaseManager;

    public function __construct(DatabaseManager $databaseManager)
    {
        $this->databaseManager = $databaseManager;
    }

    public function index()
    {
        $tenants = Tenant::with('domains')->get();
        return response()->json([
            'success' => true,
            'data' => $tenants
        ]);
    }

    public function store(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'company' => 'required|string|max:255',
            'domain' => 'required|string|max:255|unique:domains,domain',
        ]);

        if ($validated->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validated->errors()
            ], 422);
        }

        $data = $validated->validated();
        $tenantId = strtolower(str_replace(' ', '_', $data['company']));
        $domain = strtolower($data['domain']);

        try {
            $tenant = Tenant::create([
                'id' => $tenantId,
                'data' => ['company' => $data['company']],
            ]);

            $tenant->domains()->create([
                'domain' => $domain,
            ]);

            $this->databaseManager->createDatabase($tenant);

            tenancy()->initialize($tenant);
            \Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
            ]);
            tenancy()->end();

            return response()->json([
                'success' => true,
                'message' => 'Tenant created successfully.',
                'tenant' => $tenant,
                'domain' => $domain
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant creation failed.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $tenant = Tenant::with('domains')->findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => $tenant
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant not found.',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $tenant = Tenant::findOrFail($id);
            $validated = Validator::make($request->all(), [
                'company' => 'required|string|max:255',
                'domain' => 'required|string|max:255|unique:domains,domain,' . $tenant->domains()->first()->id,
            ]);

            if ($validated->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validated->errors()
                ], 422);
            }

            $data = $validated->validated();

            $tenant->update([
                'data' => array_merge($tenant->data, ['company' => $data['company']]),
            ]);

            $tenant->domains()->update([
                'domain' => strtolower($data['domain']),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tenant updated successfully.',
                'tenant' => $tenant
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant update failed.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $tenant = Tenant::findOrFail($id);
            $this->databaseManager->deleteDatabase($tenant);
            $tenant->domains()->delete();
            $tenant->delete();

            return response()->json([
                'success' => true,
                'message' => 'Tenant deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tenant deletion failed.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}