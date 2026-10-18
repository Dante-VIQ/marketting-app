<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Models\PersonaEvidence;
use App\Services\Personas\PersonaInsights;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PersonaController extends Controller
{
    public function index(PersonaInsights $insights)
    {
        $brand = Auth::user()->activeBrand;
        if (!$brand) {
            return redirect()->route('brands.index')->with('warning', 'Please select a brand first.');
        }

        return view('personas.index', [
            'brand' => $brand,
            'personas' => $insights->personas($brand->id),
            'leads' => $insights->leadSignals($brand->id),
        ]);
    }

    public function store(Request $request)
    {
        $brand = $this->brand();
        $p = Persona::create(['brand_id' => $brand->id] + $this->validated($request));

        return redirect()->route('personas.index')->with('message', "Persona \"{$p->name}\" added as a hypothesis. Add customer evidence to build confidence.");
    }

    public function update(Request $request, int $persona)
    {
        $p = Persona::where('brand_id', $this->brand()->id)->findOrFail($persona);
        $p->update($this->validated($request));

        return back()->with('message', 'Persona updated.');
    }

    public function destroy(int $persona)
    {
        $p = Persona::where('brand_id', $this->brand()->id)->findOrFail($persona);
        $p->delete();

        return back()->with('message', "Persona \"{$p->name}\" deleted.");
    }

    public function addEvidence(Request $request, int $persona)
    {
        $p = Persona::where('brand_id', $this->brand()->id)->findOrFail($persona);
        $data = $request->validate([
            'kind' => 'required|in:' . implode(',', array_keys(PersonaEvidence::KINDS)),
            'note' => 'required|string|min:3|max:500',
            'observed_on' => 'nullable|date|before_or_equal:today',
        ]);
        $p->evidence()->create($data);

        return back()->with('message', 'Evidence added.');
    }

    public function removeEvidence(int $persona, int $evidence)
    {
        $p = Persona::where('brand_id', $this->brand()->id)->findOrFail($persona);
        $p->evidence()->findOrFail($evidence)->delete();

        return back()->with('message', 'Evidence removed.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|min:2|max:80',
            'role_title' => 'nullable|string|max:120',
            'summary' => 'nullable|string|max:1000',
            'buying_role' => 'nullable|in:decision_maker,influencer,user,gatekeeper',
            'quote' => 'nullable|string|max:300',
            'is_active' => 'sometimes|boolean',
        ] + array_fill_keys(Persona::LIST_FIELDS, 'nullable|string|max:2000'));

        foreach (Persona::LIST_FIELDS as $f) {
            $lines = preg_split('/\r\n|\r|\n/', (string) ($data[$f] ?? ''));
            $data[$f] = array_slice(array_values(array_filter(array_map(fn ($l) => mb_substr(trim($l), 0, 200), $lines))), 0, 10);
        }
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }

    private function brand()
    {
        $brand = Auth::user()->activeBrand;
        abort_unless($brand, 403);

        return $brand;
    }
}
