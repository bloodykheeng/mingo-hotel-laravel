<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Traits\Loggable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FeatureController extends Controller
{
    use Loggable;

    /**
     * Display a listing of the features.
     */
    public function index(Request $request)
    {
        $query = Feature::with(['createdBy', 'updatedBy']);

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->query('search') . '%');
        }

        if ($request->boolean('paginate')) {
            $perPage = $request->get('rowsPerPage', 10);
            $data    = $query->latest()->paginate($perPage);
            return response()->json(['data' => $data]);
        }

        $data = $query->latest()->get();
        return response()->json(['data' => $data]);
    }

    /**
     * Store a newly created feature.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255|unique:features,name',
            'icon'      => 'nullable|string|max:255',
            'photo_url' => 'nullable|string|max:255',
        ]);

        $validated['created_by'] = Auth::id();
        $validated['updated_by'] = Auth::id();

        $feature = Feature::create($validated);

        $this->logActivity('feature_created', "Feature '{$feature->name}' was created.", ['feature_id' => $feature->id]);

        return response()->json(['message' => 'Feature created successfully', 'data' => $feature], 201);
    }

    /**
     * Display the specified feature.
     */
    public function show($id)
    {
        $feature = Feature::with(['createdBy', 'updatedBy'])->findOrFail($id);
        return response()->json($feature);
    }

    /**
     * Update the specified feature.
     */
    public function update(Request $request, $id)
    {
        $feature = Feature::findOrFail($id);

        $validated = $request->validate([
            'name'      => 'sometimes|string|max:255|unique:features,name,' . $feature->id,
            'icon'      => 'nullable|string|max:255',
            'photo_url' => 'nullable|string|max:255',
        ]);

        $validated['updated_by'] = Auth::id();

        $feature->update($validated);

        $this->logActivity('feature_updated', "Feature '{$feature->name}' was updated.", ['feature_id' => $feature->id]);

        return response()->json(['message' => 'Feature updated successfully', 'data' => $feature]);
    }

    /**
     * Remove the specified feature.
     */
    public function destroy($id)
    {
        $feature = Feature::findOrFail($id);
        $feature->delete();

        $this->logActivity('feature_deleted', "Feature '{$feature->name}' was deleted.", ['feature_id' => $feature->id]);

        return response()->json(['message' => 'Feature deleted successfully']);
    }

    /**
     * Bulk delete features.
     */
    public function bulkDestroy(Request $request)
    {
        $itemsToDelete = $request->input('itemsToDelete');

        if (! is_array($itemsToDelete) || empty($itemsToDelete)) {
            return response()->json(['message' => 'Invalid or empty features data'], 400);
        }

        $featureIds = array_column($itemsToDelete, 'id');
        if (empty($featureIds)) {
            return response()->json(['message' => 'No valid feature IDs found'], 400);
        }

        $features = Feature::whereIn('id', $featureIds)->get();

        if ($features->isEmpty()) {
            return response()->json(['message' => 'No matching features found'], 404);
        }

        $deletedFeatureNames = $features->pluck('name')->toArray();
        Feature::whereIn('id', $featureIds)->delete();

        $this->logActivity('features_bulk_deleted', "Features deleted: " . implode(', ', $deletedFeatureNames), ['feature_ids' => $featureIds]);

        return response()->json(['message' => 'Features deleted successfully']);
    }
}
