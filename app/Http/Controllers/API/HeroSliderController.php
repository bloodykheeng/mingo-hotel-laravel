<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\HeroSlider;
use App\Traits\Loggable;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class HeroSliderController extends Controller
{
    use Loggable;

    public function index(Request $request)
    {
        $query = HeroSlider::with(["createdBy", "updatedBy"]);

        // Apply search filter if provided
        if ($request->has('search')) {
            $query->where('title', 'like', '%' . $request->query('search') . '%')
                ->orWhere('description', 'like', '%' . $request->query('search') . '%');
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
                'title'           => 'required|string|max:255',
                'description'     => 'nullable|string',
                'button_link_one' => 'nullable|string|max:255',
                'button_link_two' => 'nullable|string|max:255',
                'status'          => 'required|string|max:50',
                'photo.file_path' => 'nullable|file|max:2048',
            ]);

            $validated['created_by'] = Auth::id();
            $validated['updated_by'] = Auth::id();

            DB::beginTransaction();

            // Handle photo upload
            if ($request->hasFile('photo')) {
                $photo                  = $validated['photo']['file_path'];
                $validated['photo_url'] = $this->handlePhotoUpload($photo, 'hero_slider_images');
            }

            $heroSlider = HeroSlider::create($validated);

            $this->logActivity(
                'hero_slider_created',
                "Hero slider '{$heroSlider->title}' was created.",
                ['hero_slider_id' => $heroSlider->id]
            );

            DB::commit();

            return response()->json([
                'message' => 'Hero slider created successfully',
                'data'    => $heroSlider,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            // Delete uploaded photo if transaction failed
            if (isset($validated['photo_url'])) {
                $this->deletePhoto($validated['photo_url']);
            }

            $this->logActivity(
                'hero_slider_create_failed',
                "Failed to create hero slider. Error: {$e->getMessage()}",
                [
                    'error_line' => $e->getLine(),
                    'user_id'    => Auth::id(),
                ]
            );

            return response()->json([
                'message' => 'An error occurred while creating hero slider.',
                'error'   => $e->getMessage(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    public function show($id)
    {
        $heroSlider = HeroSlider::with(["createdBy", "updatedBy"])->findOrFail($id);
        return response()->json($heroSlider);
    }

    public function update(Request $request, $id)
    {
        $photoUpdated = false;

        try {
            $heroSlider = HeroSlider::findOrFail($id);

            $validated = $request->validate([
                'title'           => 'sometimes|string|max:255',
                'description'     => 'nullable|string',
                'button_link_one' => 'nullable|string|max:255',
                'button_link_two' => 'nullable|string|max:255',
                'status'          => 'sometimes|string|max:50',
                'photo.file_path' => 'nullable|file|max:2048',
            ]);

            $validated['updated_by'] = Auth::id();

            DB::beginTransaction();

            $oldPhotoPath = $heroSlider->photo_url;

            // Handle photo upload if provided
            if ($request->hasFile('photo')) {
                // Delete old photo if exists
                if ($oldPhotoPath) {
                    $this->deletePhoto($oldPhotoPath);
                }
                $photo                  = $validated['photo']['file_path'];
                $validated['photo_url'] = $this->handlePhotoUpload($photo, 'hero_slider_images');
                $photoUpdated           = true;
            }

            $heroSlider->update($validated);

            $this->logActivity(
                'hero_slider_updated',
                "Hero slider '{$heroSlider->title}' was updated.",
                ['hero_slider_id' => $heroSlider->id]
            );

            DB::commit();

            return response()->json(['message' => 'Hero slider updated successfully']);
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
                'hero_slider_update_failed',
                "Failed to update hero slider with ID {$id}. Error: {$e->getMessage()}",
                [
                    'error_line'     => $e->getLine(),
                    'hero_slider_id' => $id,
                    'user_id'        => Auth::id(),
                ]
            );

            return response()->json([
                'message' => 'An error occurred while updating hero slider.',
                'error'   => $e->getMessage(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        $heroSlider = HeroSlider::findOrFail($id);

        // Delete photo if exists
        if ($heroSlider->photo_url) {
            $this->deletePhoto($heroSlider->photo_url);
        }

        $heroSlider->delete();

        $this->logActivity(
            'hero_slider_deleted',
            "Hero slider '{$heroSlider->title}' was deleted.",
            ['hero_slider_id' => $id]
        );

        return response()->json(['message' => 'Hero slider deleted successfully']);
    }

    public function bulkDestroy(Request $request)
    {
        $itemsToDelete = $request->input('itemsToDelete');

        if (! is_array($itemsToDelete) || empty($itemsToDelete)) {
            return response()->json(['message' => 'Invalid or empty hero slider data'], 400);
        }

        $heroSliderIds = array_column($itemsToDelete, 'id');

        if (empty($heroSliderIds)) {
            return response()->json(['message' => 'No valid hero slider IDs found'], 400);
        }

        $heroSliders = HeroSlider::whereIn('id', $heroSliderIds)->get();

        if ($heroSliders->isEmpty()) {
            return response()->json(['message' => 'No matching hero sliders found'], 404);
        }

        $deletedHeroSliderTitles = $heroSliders->pluck('title')->toArray();

        // Delete all photos
        foreach ($heroSliders as $heroSlider) {
            if ($heroSlider->photo_url) {
                $this->deletePhoto($heroSlider->photo_url);
            }
        }

        HeroSlider::whereIn('id', $heroSliderIds)->delete();

        $this->logActivity(
            'hero_sliders_bulk_deleted',
            "Hero sliders deleted: " . implode(', ', $deletedHeroSliderTitles),
            ['hero_slider_ids' => $heroSliderIds]
        );

        return response()->json(['message' => 'Hero sliders deleted successfully']);
    }

    /**
     * Handle photo upload for Hero Slider
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
