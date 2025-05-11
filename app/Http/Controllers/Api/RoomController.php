<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomAttachment;
use App\Models\RoomFeature;
use App\Models\User;
use App\Traits\Loggable;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class RoomController extends Controller
{
    use Loggable;

    public function index(Request $request)
    {
        $query = Room::with([
            'roomFeatures.feature',
            'roomAttachments',
            'createdBy',
            'updatedBy',
        ]);

        if ($request->has('search')) {
            $searchTerm = '%' . $request->query('search') . '%';

            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                    ->orWhere('description', 'like', $searchTerm);
            });
        }

        // Filter by booked status
        if ($request->has('booked')) {
            $query->where('booked', $request->boolean('booked'));
        }

        // Filter by price range
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->query('min_price'));
        }

        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->query('max_price'));
        }

        // Filter by stars
        if ($request->has('stars')) {
            $query->where('stars', $request->query('stars'));
        }

        // Filter by capacity
        if ($request->has('min_adults')) {
            $query->where('number_of_adults', '>=', $request->query('min_adults'));
        }

        if ($request->has('min_children')) {
            $query->where('number_of_children', '>=', $request->query('min_children'));
        }

        // Filter by feature
        if ($request->has('feature_id')) {
            $query->whereHas('roomFeatures', function ($q) use ($request) {
                $q->where('feature_id', $request->query('feature_id'));
            });
        }

        if ($request->boolean('paginate')) {
            $perPage = $request->get('rowsPerPage', 10);
            return response()->json(['data' => $query->latest()->paginate($perPage)]);
        }

        return response()->json(['data' => $query->latest()->get()]);
    }

    public function show($id)
    {
        $room = Room::with([
            'roomFeatures.feature',
            'roomAttachments',
            'createdBy',
            'updatedBy',
        ])->findOrFail($id);

        return response()->json($room);
    }

    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'name'                  => 'required|string|max:255',
                'description'           => 'nullable|string',
                'price'                 => 'required|numeric|min:0',
                'stars'                 => 'required|integer|min:1|max:5',
                'booked'                => 'nullable|boolean',
                'number_of_adults'      => 'required|integer|min:1',
                'number_of_children'    => 'nullable|integer|min:0',

                'features'              => 'nullable|array',
                'features.*.feature_id' => 'required|exists:features,id',
                'features.*.amount'     => 'nullable|numeric|min:0',
                'features.*.photo_url'  => 'nullable|string',

                'attachments'           => 'nullable|array',
                'attachments.*.file'    => 'required|file|mimes:jpeg,png,jpg,gif,mp4,mov,mkv,avi,pdf',
            ]);

            DB::beginTransaction();

            $validatedData['created_by'] = Auth::id();
            $validatedData['updated_by'] = Auth::id();

            $room = Room::create($validatedData);

            // Handle features
            if (isset($validatedData['features'])) {
                foreach ($validatedData['features'] as $featureData) {
                    RoomFeature::create([
                        'room_id'    => $room->id,
                        'feature_id' => $featureData['feature_id'],
                        'amount'     => $featureData['amount'] ?? 0,
                        'photo_url'  => $featureData['photo_url'] ?? null,
                        'created_by' => Auth::id(),
                        'updated_by' => Auth::id(),
                    ]);
                }
            }

            // Handle attachments
            if (isset($validatedData['attachments'])) {
                foreach ($validatedData['attachments'] as $index => $attachmentData) {
                    if ($request->hasFile("attachments.$index.file")) {
                        $uploadedFile = $request->file("attachments.$index.file");
                        $fileData     = $this->handleAttachmentUpload($uploadedFile, 'room_attachments');

                        RoomAttachment::create([
                            'room_id'    => $room->id,
                            'file_path'  => $fileData['file_path'] ?? null,
                            'created_by' => Auth::id(),
                            'updated_by' => Auth::id(),
                        ]);
                    }
                }
            }

            $this->logActivity(
                'room_created',
                "Created Room: {$room->name}.",
                [
                    'room_id'   => $room->id,
                    'room_name' => $room->name,
                ]
            );

            DB::commit();

            return response()->json(['message' => 'Room created successfully', 'data' => $room], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            $this->logActivity(
                'Error Creating Room',
                "Failed to create room. Error: {$e->getMessage()}",
                [
                    'error_line' => $e->getLine(),
                    'user_id'    => Auth::id(),
                ]
            );

            return response()->json([
                'message' => 'An error occurred while creating the room.',
                'error'   => $e->getMessage(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $room = Room::with(['roomFeatures', 'roomAttachments'])->find($id);

            if (! isset($room)) {
                return response()->json([
                    'message' => 'Room not found.',
                    'error'   => 'No room exists with the given ID.',
                ], 404);
            }

            $validated = $request->validate([
                'name'                  => 'required|string|max:255',
                'description'           => 'nullable|string',
                'price'                 => 'required|numeric|min:0',
                'stars'                 => 'required|integer|min:1|max:5',
                'booked'                => 'nullable|boolean',
                'number_of_adults'      => 'required|integer|min:1',
                'number_of_children'    => 'nullable|integer|min:0',

                'features'              => 'nullable|array',
                'features.*.id'         => 'nullable|exists:room_features,id',
                'features.*.feature_id' => 'required|exists:features,id',
                'features.*.amount'     => 'nullable|numeric|min:0',
                'features.*.photo_url'  => 'nullable|string',
                'features.*.status'     => 'nullable|string|in:existing,new,deleted',

                'attachments'           => 'nullable|array',
                'attachments.*.id'      => 'nullable|exists:room_attachments,id',
                'attachments.*.status'  => 'nullable|string|in:existing,new,deleted',
                'attachments.*.file'    => 'nullable|file|mimes:jpeg,png,jpg,gif,mp4,mov,mkv,avi,pdf',
            ]);

            DB::beginTransaction();

            $validated['updated_by'] = Auth::id();
            $room->update($validated);

            // Handle features
            if (isset($validated['features'])) {
                $existingFeatureIds = [];

                foreach ($validated['features'] as $featureData) {
                    // For existing features that need to be updated
                    if (isset($featureData['id']) && (! isset($featureData['status']) || $featureData['status'] !== 'deleted')) {
                        $roomFeature = RoomFeature::find($featureData['id']);
                        if ($roomFeature && $roomFeature->room_id == $room->id) {
                            $roomFeature->feature_id = $featureData['feature_id'];
                            $roomFeature->amount     = $featureData['amount'] ?? $roomFeature->amount;
                            $roomFeature->photo_url  = $featureData['photo_url'] ?? $roomFeature->photo_url;
                            $roomFeature->updated_by = Auth::id();
                            $roomFeature->save();

                            $existingFeatureIds[] = $roomFeature->id;
                        }
                    }
                    // For new features to be created
                    elseif (! isset($featureData['id']) || $featureData['status'] === 'new') {
                        $newRoomFeature = RoomFeature::create([
                            'room_id'    => $room->id,
                            'feature_id' => $featureData['feature_id'],
                            'amount'     => $featureData['amount'] ?? 0,
                            'photo_url'  => $featureData['photo_url'] ?? null,
                            'created_by' => Auth::id(),
                            'updated_by' => Auth::id(),
                        ]);

                        $existingFeatureIds[] = $newRoomFeature->id;
                    }
                }

                // Delete features not in the request
                $room->roomFeatures()
                    ->whereNotIn('id', $existingFeatureIds)
                    ->delete();
            }

            // Handle attachments
            if (isset($validated['attachments'])) {
                $existingAttachmentIds = collect($validated['attachments'])
                    ->where('status', 'existing')
                    ->whereNotNull('id')
                    ->pluck('id')
                    ->toArray();

                // Delete removed attachments
                $room->roomAttachments()
                    ->whereNotIn('id', $existingAttachmentIds)
                    ->each(function ($attachment) {
                        $this->deleteAttachment($attachment);
                    });

                // Process new uploads
                foreach ($validated['attachments'] as $index => $attachmentData) {
                    if (isset($attachmentData['status']) && $attachmentData['status'] === 'new' && $request->hasFile("attachments.$index.file")) {
                        $uploadedFile = $request->file("attachments.$index.file");
                        $fileData     = $this->handleAttachmentUpload($uploadedFile, 'room_attachments');

                        RoomAttachment::create([
                            'room_id'    => $room->id,
                            'file_path'  => $fileData['file_path'] ?? null,
                            'created_by' => Auth::id(),
                            'updated_by' => Auth::id(),
                        ]);
                    }
                }
            }

            DB::commit();

            $this->logActivity(
                'room_updated',
                "Updated room: {$room->name}",
                [
                    'room_id'   => $room->id,
                    'room_name' => $room->name,
                ]
            );

            return response()->json(['message' => 'Room updated successfully', 'data' => $room]);
        } catch (\Exception $e) {
            DB::rollBack();

            $roomName = isset($room) ? $room->name : 'Unknown';

            $this->logActivity(
                'room_update_failed',
                "Error updating room {$roomName}: {$e->getMessage()}",
                [
                    'room_id'   => $id,
                    'room_name' => $roomName,
                    'line'      => $e->getLine(),
                ]
            );
            return response()->json(['message' => 'Failed to update room', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $room = Room::findOrFail($id);

        // Begin transaction
        DB::beginTransaction();

        try {
            // Delete all room attachments
            foreach ($room->roomAttachments as $attachment) {
                $this->deleteAttachment($attachment);
            }

            // Delete all room features
            $room->roomFeatures()->delete();

            // Delete the room
            $room->delete();

            // Log the activity
            $this->logActivity(
                'room_deleted',
                "Deleted room: {$room->name}",
                ['room_id' => $id, 'room_name' => $room->name]
            );

            // Commit the transaction
            DB::commit();

            return response()->json(['message' => 'Room deleted successfully']);
        } catch (\Exception $e) {
            // Rollback in case of error
            DB::rollBack();

            return response()->json([
                'message' => 'An error occurred while deleting the room.',
                'error'   => $e->getMessage(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    public function bulkDestroy(Request $request)
    {
        $itemsToDelete = $request->input('itemsToDelete');

        if (! is_array($itemsToDelete) || empty($itemsToDelete)) {
            return response()->json(['message' => 'Invalid or empty room data'], 400);
        }

        $roomIds = array_column($itemsToDelete, 'id');

        if (empty($roomIds)) {
            return response()->json(['message' => 'No valid room IDs found'], 400);
        }

        // Eager load all necessary relationships
        $rooms = Room::whereIn('id', $roomIds)
            ->with([
                'roomAttachments',
                'roomFeatures',
            ])
            ->get();

        if ($rooms->isEmpty()) {
            return response()->json(['message' => 'No matching rooms found'], 404);
        }

        $deletedRoomDetails = [];

        // Begin transaction
        DB::beginTransaction();

        try {
            foreach ($rooms as $room) {
                // Save room ID before deletion for logging
                $deletedRoomDetails[] = [
                    'id'   => $room->id,
                    'name' => $room->name,
                ];

                // Delete room attachments
                foreach ($room->roomAttachments as $attachment) {
                    $this->deleteAttachment($attachment);
                }

                // Delete room features
                $room->roomFeatures()->delete();

                // Delete the room itself
                $room->delete();
            }

            $this->logActivity(
                'rooms_bulk_deleted',
                "Bulk room deletion performed by " . Auth::user()->name . ". Deleted rooms: " . json_encode($deletedRoomDetails),
                ['deleted_rooms' => $deletedRoomDetails]
            );

            // If everything went well, commit the transaction
            DB::commit();

            return response()->json(['message' => 'Rooms deleted successfully']);
        } catch (\Exception $e) {
            // If anything goes wrong, rollback the transaction
            DB::rollBack();

            return response()->json([
                'message' => 'An error occurred while deleting rooms',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    //---------------------------------- file uploading -----------------

    private function handleAttachmentUpload($file, $baseFolderPath)
    {
        // Get current date using Carbon
        $currentDate     = Carbon::now();
        $monthYearFolder = $currentDate->format('F_Y'); // e.g., March_2025

        $mimeType  = $file->getMimeType();
        $subFolder = $this->getSubFolderForMimeType($mimeType);

        // Create full folder path
        $folderPath = $baseFolderPath . '/' . $monthYearFolder . '/' . $subFolder;
        $publicPath = public_path($folderPath);

        // Create directory if it doesn't exist
        if (! File::exists($publicPath)) {
            File::makeDirectory($publicPath, 0777, true, true);
        }

        // Generate unique filename
        $fileName = time() . '_' . $file->getClientOriginalName();

        // Move file to the new location
        $file->move($publicPath, $fileName);

        return [
            'file_path'         => '/' . $folderPath . '/' . $fileName,
            'file_type'         => $mimeType,
            'month_year_folder' => $monthYearFolder,
        ];
    }

    private function getSubFolderForMimeType($mimeType)
    {
        $mimeTypeMap = [
            'image'    => ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml'],
            'video'    => ['video/mp4', 'video/mpeg', 'video/quicktime'],
            'document' => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ],
        ];

        foreach ($mimeTypeMap as $folder => $mimeTypes) {
            if (in_array($mimeType, $mimeTypes)) {
                return $folder . 's'; // pluralize the folder name
            }
        }

        return 'other_attachments';
    }

    //=================  delete attachment ===========================

    private function deleteAttachment(RoomAttachment $attachment)
    {
        $filePath = public_path($attachment->attachment_url);
        if (File::exists($filePath)) {
            File::delete($filePath);
        }
        $attachment->delete();
    }
}
