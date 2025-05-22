<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomAttachment;
use App\Models\RoomBooking;
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
            'roomCategory',
            'roomFeatures.feature',
            'roomAttachments',
            'createdBy',
            'updatedBy',
        ]);

        if ($request->has('search')) {
            $searchTerm = '%' . $request->query('search') . '%';

            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                    ->orWhere('description', 'like', $searchTerm)
                    ->orWhere('room_type', 'like', $searchTerm)
                    ->orWhereHas('roomCategory', function ($subQuery) use ($searchTerm) {
                        $subQuery->where('name', 'like', $searchTerm);
                    })
                    ->orWhereHas('roomFeatures.feature', function ($subQuery) use ($searchTerm) {
                        $subQuery->where('name', 'like', $searchTerm);
                    });
            });
        }

        // Filter by room category ID
        if ($request->filled('room_category_id')) {
            $query->where('room_category_id', $request->input('room_category_id'));
        }

        // Filter by booked status
        if ($request->has('booked')) {
            $query->where('booked', $request->boolean('booked'));
        }

        if ($request->has('room_type')) {
            $query->where('room_type', $request->boolean('room_type'));
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

        // Filter by features
        if ($request->has('features')) {
            $features = $request->query('features');

            // Check if features is an array and not empty
            if (is_array($features) && ! empty($features)) {
                // Extract the IDs from the array of objects
                $featureIds = array_column($features, 'id');

                // Filter rooms that have any of the selected features
                $query->whereHas('roomFeatures', function ($q) use ($featureIds) {
                    $q->whereIn('feature_id', $featureIds);
                });
            }
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

    /**
     * Check room availability for the specified dates and guest count
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkAvailability(Request $request)
    {
        // Validate the request
        $validated = $request->validate([
            'checkInDate'  => 'required|date',
            'checkOutDate' => 'required|date|after:checkInDate',
            'adults'       => 'required|integer|min:1',
            'children'     => 'sometimes|integer|min:0',
        ]);

        // Set default for children if not provided
        $numberOfChildren = $validated['children'] ?? 0;

        // Convert to Carbon instances
        $checkInDate  = Carbon::parse($validated['checkInDate']);
        $checkOutDate = Carbon::parse($validated['checkOutDate']);

        // Verify check-in date is not in the past
        if ($checkInDate->isPast() && $checkInDate->isToday() === false) {
            return response()->json([
                'success' => false,
                'message' => 'Check-in date cannot be in the past',
            ], 422);
        }

        // Find all rooms that can accommodate the guests
        $availableRooms = Room::where('number_of_adults', '>=', $validated['adults'])
            ->where('number_of_children', '>=', $numberOfChildren)
            ->where('booked', false)
            ->get();

        // Filter rooms for booking conflicts
        $finalAvailableRooms = collect();

        foreach ($availableRooms as $room) {
                                                                            // Check for overlapping bookings
            $hasOverlap = RoomBooking::whereIn('status', ['new', 'booked']) // Only consider active or pending bookings
                ->where('room_id', $room->id)
                ->where(function ($q) use ($checkInDate, $checkOutDate) {
                    // Case 1: New booking check-in date falls within an existing booking
                    $q->where(function ($q1) use ($checkInDate, $checkOutDate) {
                        $q1->where('check_in', '<=', $checkInDate)
                            ->where('check_out', '>', $checkInDate);
                    })
                    // Case 2: New booking check-out date falls within an existing booking
                        ->orWhere(function ($q2) use ($checkInDate, $checkOutDate) {
                            $q2->where('check_in', '<', $checkOutDate)
                                ->where('check_out', '>=', $checkOutDate);
                        })
                    // Case 3: New booking completely encompasses an existing booking
                        ->orWhere(function ($q3) use ($checkInDate, $checkOutDate) {
                            $q3->where('check_in', '>=', $checkInDate)
                                ->where('check_out', '<=', $checkOutDate);
                        });
                })
                ->exists();

            if (! $hasOverlap) {
                // Add room details
                $room->features    = $room->features ?? [];
                $room->attachments = $room->attachments ?? [];

                $finalAvailableRooms->push($room);
            }
        }

        // Check if no rooms are available
        if ($finalAvailableRooms->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No available rooms found for the selected dates and guest count',
                'data'    => [
                    'available_rooms' => [],
                    'total'           => 0,
                    'search_criteria' => [
                        'check_in'  => $validated['checkInDate'],
                        'check_out' => $validated['checkOutDate'],
                        'adults'    => $validated['adults'],
                        'children'  => $numberOfChildren,
                    ],
                ],
            ], 200); // Using 200 status since this is not an error, just no results found
        }

        // Return the list of available rooms
        return response()->json([
            'success' => true,
            'message' => 'Available rooms retrieved successfully',
            'data'    => [
                'available_rooms' => $finalAvailableRooms,
                'total'           => $finalAvailableRooms->count(),
                'search_criteria' => [
                    'check_in'  => $validated['checkInDate'],
                    'check_out' => $validated['checkOutDate'],
                    'adults'    => $validated['adults'],
                    'children'  => $numberOfChildren,
                ],
            ],
        ]);
    }

    public function store(Request $request)
    {
        try {
            // Decode JSON fields
            $features = json_decode($request->features, true);

            // Convert string "true"/"false" to actual boolean values
            $request->merge([
                'features' => $features,
                'booked'   => filter_var($request->input('booked'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);

            $validatedData = $request->validate([
                'room_type'               => 'required|in:accommodation,conference_hall',
                'room_category_id'        => 'required|exists:room_categories,id',
                'name'                    => 'required|string|max:255',
                'description'             => 'nullable|string',
                'status'                  => 'required|string|max:50',
                'price'                   => 'required|numeric|min:0',
                'stars'                   => 'required|integer|min:1|max:5',
                'booked'                  => 'nullable|boolean',
                'number_of_adults'        => 'required|integer|min:1',
                'number_of_children'      => 'nullable|integer|min:0',

                'photo.file_path'         => 'nullable|file|max:2048',

                'features'                => 'nullable|array',
                'features.*.id'           => 'required|exists:features,id',
                'features.*.amount'       => 'nullable|numeric|min:0',
                'features.*.photo_url'    => 'nullable|string',

                'attachments'             => 'nullable|array',
                'attachments.*.type'      => 'nullable|string',
                'attachments.*.caption'   => 'nullable|string',
                'attachments.*.file_path' => 'nullable|file|mimes:jpeg,png,jpg,gif,mp4,mov,mkv,avi,mp3,m4a,amr,3gp,wav,pdf,doc,docx,xls,xlsx,heic,hevc',

            ]);

            DB::beginTransaction();

            $validatedData['created_by'] = Auth::id();
            $validatedData['updated_by'] = Auth::id();

            // Handle photo upload
            if ($request->hasFile('photo')) {
                $photo                      = $validatedData['photo']['file_path'];
                $validatedData['photo_url'] = $this->handlePhotoUpload($photo, 'room_images');
            }

            $room = Room::create($validatedData);

            // Handle features
            if (isset($validatedData['features'])) {
                foreach ($validatedData['features'] as $featureData) {
                    RoomFeature::create([
                        'room_id'    => $room->id,
                        'feature_id' => $featureData['id'],
                        'amount'     => $featureData['amount'] ?? null,
                        'photo_url'  => $featureData['photo_url'] ?? null,
                        'created_by' => Auth::id(),
                        'updated_by' => Auth::id(),
                    ]);
                }
            }

            // Handle attachments
            if (isset($validatedData['attachments'])) {
                foreach ($validatedData['attachments'] as $index => $attachmentData) {
                    if ($request->hasFile("attachments.$index.file_path")) {
                        $uploadedFile = $request->file("attachments.$index.file_path");
                        $fileData     = $this->handleAttachmentUpload($uploadedFile, 'room_attachments');

                        RoomAttachment::create([
                            'room_id'    => $room->id,
                            'file_path'  => $fileData['file_path'] ?? null,
                            'type'       => $attachmentData['type'] ?? null,
                            'caption'    => $attachmentData['caption'] ?? null,
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

            // Delete uploaded photo if transaction failed
            if (isset($validatedData['photo_url'])) {
                $this->deletePhoto($validatedData['photo_url']);
            }

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

            $photoUpdated = false;

            // Decode JSON fields
            $features = json_decode($request->features, true);

            // Convert string "true"/"false" to actual boolean values
            $request->merge([
                'features' => $features,
                'booked'   => filter_var($request->input('booked'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);

            $room = Room::with(['roomFeatures', 'roomAttachments'])->find($id);

            if (! isset($room)) {
                return response()->json([
                    'message' => 'Room not found.',
                    'error'   => 'No room exists with the given ID.',
                ], 404);
            }

            // Convert string "true"/"false" to actual boolean values
            $request->merge([
                'booked' => filter_var($request->input('booked'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);

            $validated = $request->validate([
                'room_type'                            => 'required|in:accommodation,conference_hall',
                'room_category_id'                     => 'required|exists:room_categories,id',
                'name'                                 => 'required|string|max:255',
                'description'                          => 'nullable|string',
                'status'                               => 'sometimes|string|max:50',
                'price'                                => 'required|numeric|min:0',
                'stars'                                => 'required|integer|min:1|max:5',
                'booked'                               => 'nullable|boolean',
                'number_of_adults'                     => 'required|integer|min:1',
                'number_of_children'                   => 'nullable|integer|min:0',

                'photo.file_path'                      => 'nullable|file|max:2048',

                'features'                             => 'nullable|array',
                'features.*.id'                        => 'required|exists:features,id',
                'features.*.amount'                    => 'nullable|numeric|min:0',
                'features.*.photo_url'                 => 'nullable|string',

                'attachments'                          => 'nullable|array',
                'attachments.*.type'                   => 'nullable|string',
                'attachments.*.caption'                => 'nullable|string',
                'attachments.*.status'                 => 'nullable|string',
                'attachments.*.existing_attachment_id' => 'nullable',
                'attachments.*.file_path'              => 'nullable|file|mimes:jpeg,png,jpg,gif,mp4,mov,mkv,avi,mp3,m4a,amr,3gp,wav,pdf,doc,docx,xls,xlsx,heic,hevc',
            ]);

            DB::beginTransaction();

            $validated['updated_by'] = Auth::id();
            $oldPhotoPath            = $room->photo_url;

            // Handle photo upload if provided
            if ($request->hasFile('photo')) {
                // Delete old photo if exists
                if ($oldPhotoPath) {
                    $this->deletePhoto($oldPhotoPath);
                }
                $photo                  = $validated['photo']['file_path'];
                $validated['photo_url'] = $this->handlePhotoUpload($photo, 'room_images');
                $photoUpdated           = true;
            }

            $room->update($validated);

            // Handle features
            if (isset($validated['features'])) {
                // Delete all existing features for the room
                $room->roomFeatures()->delete();

                // Recreate features from the request
                foreach ($validated['features'] as $featureData) {
                    RoomFeature::create([
                        'room_id'    => $room->id,
                        'feature_id' => $featureData['id'],
                        'amount'     => $featureData['amount'] ?? 0,
                        'photo_url'  => $featureData['photo_url'] ?? null,
                        'created_by' => Auth::id(),
                        'updated_by' => Auth::id(),
                    ]);
                }
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
                    if (isset($attachmentData['status']) && $attachmentData['status'] === 'new' && $request->hasFile("attachments.$index.file_path")) {
                        $uploadedFile = $request->file("attachments.$index.file_path");
                        $fileData     = $this->handleAttachmentUpload($uploadedFile, 'room_attachments');

                        RoomAttachment::create([
                            'room_id'    => $room->id,
                            'file_path'  => $fileData['file_path'] ?? null,
                            'type'       => $attachmentData['type'] ?? null,
                            'caption'    => $attachmentData['caption'] ?? null,
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

            // If photo was updated but transaction failed, restore old photo
            if ($photoUpdated) {
                // Remove newly uploaded photo if exists
                if (isset($validated['photo_url'])) {
                    $this->deletePhoto($validated['photo_url']);
                }
            }

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

            // Delete photo if exists
            if ($room->photo_url) {
                $this->deletePhoto($room->photo_url);
            }

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

                // Delete photo if exists
                if ($room->photo_url) {
                    $this->deletePhoto($room->photo_url);
                }

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

    //==================================== single photo upload ================================

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
