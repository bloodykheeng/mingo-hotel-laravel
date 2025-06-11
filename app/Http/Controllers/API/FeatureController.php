<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Traits\Loggable;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class FeatureController extends Controller
{
    use Loggable;

    public function index(Request $request)
    {
        $query = Feature::with(["createdBy", "updatedBy"]);

        // Apply search filter if provided
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->query('search') . '%');
        }

        // Return paginated results if pagination is requested
        if ($request->boolean('paginate')) {
            $perPage = $request->get('rowsPerPage', 10);
            return response()->json(['data' => $query->latest()->paginate($perPage)]);
        }

        // Otherwise return all results
        return response()->json(['data' => $query->latest()->get()]);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name'            => 'required|string|max:255|unique:features,name',
                'icon'            => 'nullable|string|max:255',
                'status'          => 'required|string|max:50',
                // 'photo.file_path' => 'nullable|file|max:2048',
                'photo.file_path' => 'nullable|file|max:20480',
            ]);

            $validated['created_by'] = Auth::id();
            $validated['updated_by'] = Auth::id();

            DB::beginTransaction();

            // Handle photo upload
            if ($request->hasFile('photo')) {
                $photo                  = $validated['photo']['file_path'];
                $validated['photo_url'] = $this->handlePhotoUpload($photo, 'feature_images');
            }

            $feature = Feature::create($validated);

            $this->logActivity(
                'feature_created',
                "Feature '{$feature->name}' was created.",
                ['feature_id' => $feature->id]
            );

            DB::commit();

            return response()->json([
                'message' => 'Feature created successfully',
                'data'    => $feature,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            // Delete uploaded photo if transaction failed
            if (isset($validated['photo_url'])) {
                $this->deletePhoto($validated['photo_url']);
            }

            $this->logActivity(
                'feature_create_failed',
                "Failed to create feature. Error: {$e->getMessage()}",
                [
                    'error_line' => $e->getLine(),
                    'user_id'    => Auth::id(),
                ]
            );

            return response()->json([
                'message' => 'An error occurred while creating feature.',
                'error'   => $e->getMessage(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    public function show($id)
    {
        $feature = Feature::with(["createdBy", "updatedBy"])->findOrFail($id);
        return response()->json($feature);
    }

    public function update(Request $request, $id)
    {
        $photoUpdated = false;

        try {
            $feature = Feature::findOrFail($id);

            $validated = $request->validate([
                'name'            => 'sometimes|string|max:255|unique:features,name,' . $feature->id,
                'status'          => 'sometimes|string|max:50',
                'icon'            => 'nullable|string|max:255',
                // 'photo.file_path' => 'nullable|file|max:2048',
                'photo.file_path' => 'nullable|file|max:20480',
            ]);

            $validated['updated_by'] = Auth::id();

            DB::beginTransaction();

            $oldPhotoPath = $feature->photo_url;

            // Handle photo upload if provided
            if ($request->hasFile('photo')) {
                // Delete old photo if exists
                if ($oldPhotoPath) {
                    $this->deletePhoto($oldPhotoPath);
                }
                $photo                  = $validated['photo']['file_path'];
                $validated['photo_url'] = $this->handlePhotoUpload($photo, 'feature_images');
                $photoUpdated           = true;
            }

            $feature->update($validated);

            $this->logActivity(
                'feature_updated',
                "Feature '{$feature->name}' was updated.",
                ['feature_id' => $feature->id]
            );

            DB::commit();

            return response()->json(['message' => 'Feature updated successfully']);
        } catch (\Exception $e) {
            DB::rollBack();

            // If photo was updated but transaction failed, restore old photo
            if ($photoUpdated) {
                // Remove newly uploaded photo if exists
                if (isset($validated['photo_url'])) {
                    $this->deletePhoto($validated['photo_url']);
                }
            }

            $this->logActivity(
                'feature_update_failed',
                "Failed to update feature with ID {$id}. Error: {$e->getMessage()}",
                [
                    'error_line' => $e->getLine(),
                    'feature_id' => $id,
                    'user_id'    => Auth::id(),
                ]
            );

            return response()->json([
                'message' => 'An error occurred while updating feature.',
                'error'   => $e->getMessage(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        $feature = Feature::findOrFail($id);

        // Check if feature is in use by any rooms
        if ($feature->roomFeatures()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete feature as it is being used by one or more rooms.',
            ], 422);
        }

        // Delete photo if exists
        if ($feature->photo_url) {
            $this->deletePhoto($feature->photo_url);
        }

        $feature->delete();

        $this->logActivity(
            'feature_deleted',
            "Feature '{$feature->name}' was deleted.",
            ['feature_id' => $id]
        );

        return response()->json(['message' => 'Feature deleted successfully']);
    }

    public function bulkDestroy(Request $request)
    {
        $itemsToDelete = $request->input('itemsToDelete');

        if (! is_array($itemsToDelete) || empty($itemsToDelete)) {
            return response()->json(['message' => 'Invalid or empty feature data'], 400);
        }

        $featureIds = array_column($itemsToDelete, 'id');

        if (empty($featureIds)) {
            return response()->json(['message' => 'No valid feature IDs found'], 400);
        }

        $features = Feature::whereIn('id', $featureIds)->get();

        if ($features->isEmpty()) {
            return response()->json(['message' => 'No matching features found'], 404);
        }

        // Check if any of the features are in use
        $inUseFeatures = [];
        foreach ($features as $feature) {
            if ($feature->roomFeatures()->count() > 0) {
                $inUseFeatures[] = $feature->name;
            }
        }

        if (! empty($inUseFeatures)) {
            return response()->json([
                'message' => 'Cannot delete features that are in use: ' . implode(', ', $inUseFeatures),
            ], 422);
        }

        $deletedFeatureNames = $features->pluck('name')->toArray();

        // Delete all photos
        foreach ($features as $feature) {
            if ($feature->photo_url) {
                $this->deletePhoto($feature->photo_url);
            }
        }

        Feature::whereIn('id', $featureIds)->delete();

        $this->logActivity(
            'features_bulk_deleted',
            "Features deleted: " . implode(', ', $deletedFeatureNames),
            ['feature_ids' => $featureIds]
        );

        return response()->json(['message' => 'Features deleted successfully']);
    }

    /**
     * Handle photo upload for Feature
     *
     * @param \Illuminate\Http\UploadedFile|null $file
     * @param string $folder_path
     * @return string|null
     */
    private function handlePhotoUpload($file, $folder_path)
    {
        if (! $file) {
            return null;
        }

        // Get current date using Carbon
        $currentDate     = Carbon::now();
        $monthYearFolder = $currentDate->format('F_Y'); // e.g., March_2025

        // Create folder path for photos
        $folderPath = $folder_path . "/" . $monthYearFolder;
        $publicPath = public_path($folderPath);

        // Create directory if it doesn't exist
        if (! File::exists($publicPath)) {
            File::makeDirectory($publicPath, 0777, true, true);
        }

        // Generate unique filename
        $fileName = time() . '_' . $file->getClientOriginalName();

        // Move file to the new location
        $file->move($publicPath, $fileName);

        return '/' . $folderPath . '/' . $fileName;
    }

    /**
     * Delete photo file
     *
     * @param string|null $photoPath
     * @return bool
     */
    private function deletePhoto($photoPath)
    {
        if (! $photoPath) {
            return false;
        }

        $filePath = public_path($photoPath);
        if (File::exists($filePath)) {
            File::delete($filePath);
            return true;
        }

        return false;
    }
}
