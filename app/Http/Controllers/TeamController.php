<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeamStoreRequest;
use App\Http\Requests\TeamUpdateRequest;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;

class TeamController extends Controller
{
    // GET /api/teams?search=&page=&pageSize=&sort=name,asc
    public function index(Request $request) {
        $search   = trim((string) $request->query('search', ''));
        $page     = max((int) $request->query('page', 1), 1);        // 1-based
        $pageSize = max((int) $request->query('pageSize', 10), 1);
        $sort     = $request->query('sort', 'name,asc');

        [$sortField, $sortDir] = array_pad(explode(',', $sort, 2), 2, 'asc');
        $sortField = in_array($sortField, ['name','city','created_at','updated_at']) ? $sortField : 'name';
        $sortDir   = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';

        $q = Team::query();
        if ($search !== '') {
            // ILIKE funciona en PostgreSQL
            $q->where(function($w) use ($search) {
                $w->where('name', 'ILIKE', "%{$search}%")
                  ->orWhere('city','ILIKE', "%{$search}%");
            });
        }
        $q->orderBy($sortField, $sortDir);

        $paginator = $q->paginate($pageSize, ['*'], 'page', $page);

        return response()->json([
            'items'      => $paginator->items(),
            'totalItems' => $paginator->total(),
            'page'       => $paginator->currentPage(),
            'pageSize'   => $paginator->perPage(),
        ]);
    }

    // GET /api/teams/{id}
    public function show($id) {
        $team = Team::find($id);
        if (!$team) return response()->json(['message'=>'Team not found'], 404);
        return response()->json($team);
    }

// POST /api/teams
public function store(TeamStoreRequest $request) {
    try {
        $team = Team::create($request->validated());
        return response()->json($team, 201);
    } catch (QueryException $e) {
        // Postgres unique violation
        if ($e->getCode() === '23505') {
            return response()->json([
                'message' => 'Team already exists for this name and city.'
            ], 409);
        }
        return response()->json(['message' => 'Database error', 'error' => $e->getMessage()], 500);
    } catch (\Throwable $e) {
        return response()->json(['message' => 'Unexpected error', 'error' => $e->getMessage()], 500);
    }
}

// PUT /api/teams/{id}
public function update(TeamUpdateRequest $request, $id) {
    $team = Team::find($id);
    if (!$team) return response()->json(['message'=>'Team not found'], 404);

    try {
        $team->update($request->validated());
        return response()->json($team);
    } catch (QueryException $e) {
        if ($e->getCode() === '23505') {
            return response()->json([
                'message' => 'Another team with the same name and city already exists.'
            ], 409);
        }
        return response()->json(['message' => 'Database error', 'error' => $e->getMessage()], 500);
    } catch (\Throwable $e) {
        return response()->json(['message' => 'Unexpected error', 'error' => $e->getMessage()], 500);
    }
}

    // DELETE /api/teams/{id}
    public function destroy($id) {
        $team = Team::find($id);
        if (!$team) return response()->json(['message'=>'Team not found'], 404);
        $team->delete();
        return response()->noContent();
    }

    // GET /api/health
    public function health() {
        // prueba mínima de conexión a BD
        try { DB::select('select 1'); $db='ok'; } catch (\Throwable $e) { $db=$e->getMessage(); }
        return response()->json(['status'=>'OK','db'=>$db]);
    }
}
