<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class TeamController extends Controller
{
    /* ---------------------------- Helpers ---------------------------- */

    public function health(): JsonResponse
    {
        return response()->json([
            'service' => 'teams-service',
            'status'  => 'ok',
            'time'    => now()->toISOString(),
        ]);
    }

    private function pick(Request $r, array $keys): ?string
    {
        foreach ($keys as $k) {
            $v = $r->input($k);
            if (is_string($v)) $v = trim($v);
            if ($v !== null && $v !== '') return $v;
        }
        return null;
    }

    private function pickFile(Request $r, array $keys)
    {
        foreach ($keys as $k) {
            if ($r->hasFile($k)) return $r->file($k);
        }
        return null;
    }

    private function isDataUri(?string $s): bool
    {
        return is_string($s) && str_starts_with($s, 'data:') && str_contains($s, ';base64,');
    }

    private function persistDataUri(string $dataUri): ?string
    {
        try {
            [$meta, $b64] = explode(',', $dataUri, 2);
            // meta: data:image/png;base64
            $meta = substr($meta, 5); // remove "data:"
            $mime = explode(';', $meta)[0] ?? 'application/octet-stream';

            $ext = match ($mime) {
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
                'image/gif'  => 'gif',
                default      => 'bin',
            };

            $bytes = base64_decode($b64, true);
            if ($bytes === false) return null;

            $name = 'logos/' . uniqid('logo_', true) . '.' . $ext;
            Storage::disk('public')->put($name, $bytes);

            return asset('storage/' . $name);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Obtiene URL de logo desde: archivo, data-URI o URL ya válida. */
    private function handleLogoUpload(Request $request): ?string
    {
        // 1) Archivo (multipart)
        $file = $this->pickFile($request, [
            'logo','Logo','file','File','logoFile','LogoFile','imagen','Imagen','image','Image'
        ]);
        if ($file) {
            $path = $file->store('logos', 'public');   // storage/app/public/logos
            return asset('storage/' . $path);          // requiere: php artisan storage:link
        }

        $str = $this->pick($request, ['logo','Logo','logo_url','LogoUrl','logoUrl']);
        if ($this->isDataUri($str)) {
            return $this->persistDataUri($str);
        }
        if (is_string($str) && $str !== '' &&
            (str_starts_with($str,'http://') || str_starts_with($str,'https://') || str_starts_with($str,'/storage/'))) {
            return $str;
        }

        return null;
    }

    private function dto(Team $t): array
    {
        $logo = $t->logo_url;

        if (is_string($logo)) {
            $isHttp   = str_starts_with($logo, 'http://') || str_starts_with($logo, 'https://');
            $isPublic = str_starts_with($logo, '/storage/');
            if (!$isHttp && !$isPublic) $logo = null;
        }

        return [
            'id'       => $t->id,
            'name'     => $t->name,
            'city'     => $t->city,
            'logo_url' => $logo,
            'nombre'   => $t->name,
            'ciudad'   => $t->city,
            'logo'     => $logo,
            'logoUrl'  => $logo, 
        ];
    }

    /* ----------------------------- Listados -------------------------- */

    // GET /api/teams-paged   (proxy: /api/equipos/paged)
    public function index(Request $request): JsonResponse
    {
        $page     = max(1, (int) $request->query('page', 1));
        $pageSize = max(1, (int) $request->query('pageSize', 10));

        $search  = $this->pick($request, ['q', 'search']);
        $city    = $this->pick($request, ['city', 'ciudad']);
        $sortBy  = $request->query('sortBy');
        $sortDir = strtolower($request->query('sortDir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $q = Team::query();

        if ($search) {
            $q->where(function ($s) use ($search) {
                $s->where('name', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($city) {
            $q->where('city', 'like', "%{$city}%");
        }

        if ($sortBy) {
            $map = ['nombre' => 'name', 'ciudad' => 'city'];
            $col = $map[$sortBy] ?? null;
            if ($col) $q->orderBy($col, $sortDir);
        } else {
            $q->orderBy('id', 'desc');
        }

        $total = $q->count();
        $items = $q->forPage($page, $pageSize)->get()->map(fn ($t) => $this->dto($t));

        return response()->json([
            'items'      => $items->values(),
            'totalItems' => $total,
            'page'       => $page,
            'pageSize'   => $pageSize,
        ]);
    }

    // GET /api/teams   (proxy: /api/equipos) → array simple
    public function listAll(Request $request): JsonResponse
    {
        $search = $this->pick($request, ['q', 'search']);
        $city   = $this->pick($request, ['city', 'ciudad']);

        $q = Team::query();
        if ($search) {
            $q->where(fn ($s) => $s->where('name', 'like', "%{$search}%")
                                  ->orWhere('city', 'like', "%{$search}%"));
        }
        if ($city)   $q->where('city', 'like', "%{$city}%");

        $items = $q->orderBy('id', 'desc')->get()->map(fn ($t) => $this->dto($t));
        return response()->json($items->values());
    }

    /* ----------------------------- CRUD ------------------------------ */

    public function show(int $id): JsonResponse
    {
        $t = Team::findOrFail($id);
        return response()->json($this->dto($t));
    }

    public function store(Request $request): JsonResponse
    {
        // Si vino JSON con content-type no estándar, parsear manual
        if (empty($request->all())) {
            $raw = $request->getContent();
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) $request->merge($decoded);
        }

        $name = $this->pick($request, ['name','nombre','Name','Nombre']);
        $city = $this->pick($request, ['city','ciudad','City','Ciudad']);

        $data = validator(['name'=>$name,'city'=>$city], [
            'name' => 'required|string|max:120',
            'city' => 'nullable|string|max:120',
        ])->validate();

        $team = new Team($data);

        $logoUrl = $this->handleLogoUpload($request); // archivo / data-URI / URL
        if ($logoUrl) $team->logo_url = $logoUrl;

        $team->save();
        return response()->json($this->dto($team), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if (empty($request->all())) {
            $raw = $request->getContent();
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) $request->merge($decoded);
        }

        $name = $this->pick($request, ['name','nombre','Name','Nombre']);
        $city = $this->pick($request, ['city','ciudad','City','Ciudad']);

        $payload = [];
        if ($name !== null) $payload['name'] = $name;
        if ($city !== null) $payload['city'] = $city;

        $data = validator($payload, [
            'name' => 'sometimes|required|string|max:120',
            'city' => 'sometimes|nullable|string|max:120',
        ])->validate();

        $team = Team::findOrFail($id);
        $team->fill($data);

        $logoUrl = $this->handleLogoUpload($request); // archivo / data-URI / URL
        if ($logoUrl !== null) $team->logo_url = $logoUrl;

        $team->save();
        return response()->json($this->dto($team));
    }

    public function destroy(int $id): JsonResponse
    {
        $team = Team::findOrFail($id);
        $team->delete();
        return response()->json(['deleted' => true]);
    }
}
