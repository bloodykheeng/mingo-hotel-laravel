<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\RoomCategory;
use App\Traits\Loggable;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class RoomCategoryController extends Controller
{
    use Loggable;

    public function index(Request $request)
    {
        $query = RoomCategory::with(["createdBy", "updatedBy"]);

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->query('search') . '%');
        }

        if ($request->boolean('paginate')) {
            $perPage = $request->get('rowsPerPage', 10);
            return response()->json(['data' => $query->latest()->paginate($perPage)]);
        }

        return response()->json(['data' => $query->latest()->get()]);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name'            => 'required|string|max:255|unique:room_categories,name',
                'icon'            => 'nullable|string|max:255',
                'description'     => 'nullable|string|max:1000',
                'status'          => 'required|string|max:50',
                'photo.file_path' => 'nullable|file|max:2048',
            ]);

            $validated['created_by'] = Auth::id();
            $validated['updated_by'] = Auth::id();

            DB::beginTransaction();

            if ($request->hasFile('photo')) {
                $photo                  = $validated['photo']['file_path'];
                $validated['photo_url'] = $this->handlePhotoUpload($photo, 'room_category_images');
            }

            $roomCategory = RoomCategory::create($validated);

            $this->logActivity(
                'room_category_created',
                "Room category '{$roomCategory->name}' was created.",
                ['room_category_id' => $roomCategory->id]
            );

            DB::commit();

            return response()->json([
                'message' => 'Room category created successfully',
                'data'    => $roomCategory,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            if (isset($validated['photo_url'])) {
                $this->deletePhoto($validated['photo_url']);
            }

            $this->logActivity(
                'room_category_create_failed',
                "Failed to create room category. Error: {$e->getMessage()}",
                ['error_line' => $e->getLine(), 'user_id' => Auth::id()]
            );

            return response()->json([
                'message' => 'An error occurred while creating room category.',
                'error'   => $e->getMessage(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    public function show($id)
    {
        $roomCategory = RoomCategory::with(["createdBy", "updatedBy"])->findOrFail($id);
        return response()->json($roomCategory);
    }

    public function update(Request $request, $id)
    {
        $photoUpdated = false;

        try {
            $roomCategory = RoomCategory::findOrFail($id);

            $validated = $request->validate([
                'name'            => 'sometimes|string|max:255|unique:room_categories,name,' . $roomCategory->id,
                'icon'            => 'nullable|string|max:255',
                'description'     => 'nullable|string|max:1000',
                'status'          => 'sometimes|string|max:50',
                'photo.file_path' => 'nullable|file|max:2048',
            ]);

            $validated['updated_by'] = Auth::id();

            DB::beginTransaction();

            $oldPhotoPath = $roomCategory->photo_url;

            if ($request->hasFile('photo')) {
                if ($oldPhotoPath) {
                    $this->deletePhoto($oldPhotoPath);
                }

                $photo                  = $validated['photo']['file_path'];
                $validated['photo_url'] = $this->handlePhotoUpload($photo, 'room_category_images');
                $photoUpdated           = true;
            }

            $roomCategory->update($validated);

            $this->logActivity(
                'room_category_updated',
                "Room category '{$roomCategory->name}' was updated.",
                ['room_category_id' => $roomCategory->id]
            );

            DB::commit();

            return response()->json(['message' => 'Room category updated successfully']);
        } catch (\Exception $e) {
            DB::rollBack();

            if ($photoUpdated && isset($validated['photo_url'])) {
                $this->deletePhoto($validated['photo_url']);
            }

            $this->logActivity(
                'room_category_update_failed',
                "Failed to update room category. Error: {$e->getMessage()}",
                ['room_category_id' => $id, 'error_line' => $e->getLine(), 'user_id' => Auth::id()]
            );

            return response()->json([
                'message' => 'An error occurred while updating room category.',
                'error'   => $e->getMessage(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        $roomCategory = RoomCategory::findOrFail($id);

        if ($roomCategory->rooms()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete room category as it is assigned to one or more rooms.',
            ], 422);
        }

        if ($roomCategory->photo_url) {
            $this->deletePhoto($roomCategory->photo_url);
        }

        $roomCategory->delete();

        $this->logActivity(
            'room_category_deleted',
            "Room category '{$roomCategory->name}' was deleted.",
            ['room_category_id' => $id]
        );

        return response()->json(['message' => 'Room category deleted successfully']);
    }

    public function bulkDestroy(Request $request)
    {
        $itemsToDelete = $request->input('itemsToDelete');

        if (! is_array($itemsToDelete) || empty($itemsToDelete)) {
            return response()->json(['message' => 'Invalid or empty room category data'], 400);
        }

        $categoryIds = array_column($itemsToDelete, 'id');

        if (empty($categoryIds)) {
            return response()->json(['message' => 'No valid room category IDs found'], 400);
        }

        $categories = RoomCategory::whereIn('id', $categoryIds)->get();

        if ($categories->isEmpty()) {
            return response()->json(['message' => 'No matching room categories found'], 404);
        }

        $inUse = [];
        foreach ($categories as $cat) {
            if ($cat->rooms()->count() > 0) {
                $inUse[] = $cat->name;
            }
        }

        if (! empty($inUse)) {
            return response()->json([
                'message' => 'Cannot delete categories that are in use: ' . implode(', ', $inUse),
            ], 422);
        }

        $deletedNames = $categories->pluck('name')->toArray();

        foreach ($categories as $cat) {
            if ($cat->photo_url) {
                $this->deletePhoto($cat->photo_url);
            }
        }

        RoomCategory::whereIn('id', $categoryIds)->delete();

        $this->logActivity(
            'room_categories_bulk_deleted',
            "Room categories deleted: " . implode(', ', $deletedNames),
            ['room_category_ids' => $categoryIds]
        );

        return response()->json(['message' => 'Room categories deleted successfully']);
    }

    private function handlePhotoUpload($file, $folder_path)
    {
        if (! $file) {
            return null;
        }

        $currentDate     = Carbon::now();
        $monthYearFolder = $currentDate->format('F_Y');

        $folderPath = $folder_path . "/" . $monthYearFolder;
        $publicPath = public_path($folderPath);

        if (! File::exists($publicPath)) {
            File::makeDirectory($publicPath, 0777, true, true);
        }

        $fileName = time() . '_' . $file->getClientOriginalName();
        $file->move($publicPath, $fileName);

        return '/' . $folderPath . '/' . $fileName;
    }

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
