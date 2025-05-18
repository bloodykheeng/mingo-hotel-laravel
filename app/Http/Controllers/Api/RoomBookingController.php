<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\User;
use App\Traits\Loggable;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class RoomBookingController extends Controller
{
    use Loggable;

    public function index(Request $request)
    {
        $query = RoomBooking::with(['room', 'createdBy', 'updatedBy']);

        // Get authenticated user
        $authUser = Auth::user();
        if (isset($authUser)) {
            // Find the user with their relationships to determine role
            $user = User::find($authUser->id);
            if (isset($user)) {
                $userRole = $user->roles->pluck('name')->last();

                // If user is Client, only show rooms they created
                if ($userRole === 'Client') {
                    $query->where('created_by', $user->id);
                }
                // System Admin sees all rooms
            }
        }

        if ($request->has('search')) {
            $search = '%' . $request->query('search') . '%';

            $query->where(function ($q) use ($search) {
                $q->whereHas('room', function ($roomQuery) use ($search) {
                    $roomQuery->where('name', 'like', $search);
                })
                    ->orWhereHas('createdBy', function ($createdQuery) use ($search) {
                        $createdQuery->where('name', 'like', $search)
                            ->orWhere('email', 'like', $search);
                    })
                    ->orWhereHas('updatedBy', function ($updatedQuery) use ($search) {
                        $updatedQuery->where('name', 'like', $search)
                            ->orWhere('email', 'like', $search);
                    });
            });
        }

        if ($request->boolean('paginate')) {
            $perPage = $request->get('rowsPerPage', 10);
            return response()->json(['data' => $query->latest()->paginate($perPage)]);
        }

        return response()->json(['data' => $query->latest()->get()]);
    }

    private function validateRoomAvailability(array $data, $bookingId = null, $method = 'store')
    {
        try {
            // Extract required data
            $roomId           = $data['room_id'];
            $checkIn          = $data['check_in'];
            $checkOut         = $data['check_out'];
            $numberOfAdults   = $data['number_of_adults'];
            $numberOfChildren = $data['number_of_children'] ?? 0;

            // Verify the room exists
            $room = Room::find($roomId);
            if (! $room) {
                return response()->json([
                    'message'       => 'Room not found',
                    'error_details' => "Room with ID {$roomId} does not exist.",
                    'errors'        => ['room_id' => 'The selected room does not exist.'],
                ], 422);
            }

            // Check if user already has a pending booking for this room
            // Check for duplicate pending booking of same room by same user
            $pendingBookingExists = RoomBooking::where('room_id', $roomId)
                ->where('status', 'new')
                ->where('created_by', Auth::user()->id) // or $data['created_by'] if passed explicitly
                ->when($method === 'update' && $bookingId, fn($q) => $q->where('id', '!=', $bookingId))
                ->exists();

            if ($pendingBookingExists) {
                return response()->json([
                    'message'       => 'You already have a pending booking for this room.',
                    'error_details' => "Duplicate pending booking detected for room {$room->name}.",
                    'errors'        => ['room_id' => 'You already have a pending booking for this room. Please wait for confirmation.'],
                ], 422);
            }

            // Check if room is marked as booked (only for new bookings or room changes)
            if ($room->booked && ($method === 'store' || ($method === 'update' && $bookingId))) {
                // For updates, check if this is a different room than the current booking
                $skipCheck = false;
                if ($method === 'update' && $bookingId) {
                    $currentBooking = RoomBooking::find($bookingId);
                    // Skip the booked check if we're updating the same room
                    if ($currentBooking && $currentBooking->room_id == $roomId) {
                        $skipCheck = true;
                    }
                }

                if (! $skipCheck) {
                    return response()->json([
                        'message'       => 'Room is not available',
                        'error_details' => "Room {$room->name} is currently marked as booked.",
                        'errors'        => ['room_id' => 'This room is currently not available for booking.'],
                    ], 422);
                }
            }

            // Check if room can accommodate the number of guests
            if ($numberOfAdults > $room->number_of_adults) {
                return response()->json([
                    'message'       => 'Room capacity exceeded',
                    'error_details' => "Room {$room->name} can only accommodate {$room->number_of_adults} adults.",
                    'errors'        => ['number_of_adults' => "This room can only accommodate {$room->number_of_adults} adults."],
                ], 422);
            }

            if ($numberOfChildren > $room->number_of_children) {
                return response()->json([
                    'message'       => 'Room capacity exceeded',
                    'error_details' => "Room {$room->name} can only accommodate {$room->number_of_children} children.",
                    'errors'        => ['number_of_children' => "This room can only accommodate {$room->number_of_children} children."],
                ], 422);
            }

            // Convert to Carbon instances for easier date comparison
            $checkInDate  = Carbon::parse($checkIn);
            $checkOutDate = Carbon::parse($checkOut);

            // Verify the check-in date is not in the past (only for new bookings)
            if ($method === 'store' && $checkInDate->isPast() && $checkInDate->isToday() === false) {
                return response()->json([
                    'message'       => 'Invalid check-in date',
                    'error_details' => "Check-in date cannot be in the past.",
                    'errors'        => ['check_in' => 'Check-in date cannot be in the past.'],
                ], 422);
            }

            // Check for overlapping bookings
            $query = RoomBooking::where('room_id', $roomId)
                ->whereIn('status', ['new', 'booked']) // Only consider active or pending bookings
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
                });

            // If this is an update operation, exclude the current booking from the check
            if ($method === 'update' && $bookingId) {
                $query->where('id', '!=', $bookingId);
            }

            $overlappingBookings = $query->get();

            if ($overlappingBookings->count() > 0) {
                // Format the conflicting dates for a clear error message
                $conflicts = $overlappingBookings->map(function ($booking) {
                    return [
                        'check_in'  => Carbon::parse($booking->check_in)->format('Y-m-d'),
                        'check_out' => Carbon::parse($booking->check_out)->format('Y-m-d'),
                    ];
                });

                return response()->json([
                    'message'       => 'Room is already booked for the selected dates',
                    'error_details' => "Room {$room->name} has overlapping bookings for the selected period.",
                    'conflicts'     => $conflicts,
                    'errors'        => ['dates' => 'This room is already booked during the selected period. Please choose different dates.'],
                ], 422);
            }

            // All checks passed
            return null;
        } catch (\Exception $e) {
            // Log the error

            $this->logActivity(
                'Room availability validation failed',
                "Room availability validation error: {$e->getMessage()}",
                [
                    'room_id'    => $data['room_id'] ?? 'unknown',
                    'check_in'   => $data['check_in'] ?? 'unknown',
                    'check_out'  => $data['check_out'] ?? 'unknown',
                    'error_line' => $e->getLine(),
                ]
            );

            return response()->json([
                'message' => 'An error occurred while validating room availability',
                'error'   => $e->getMessage(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'room_id'            => 'required|exists:rooms,id',
                'check_in'           => 'required|date|before_or_equal:check_out',
                'check_out'          => 'required|date|after_or_equal:check_in',
                'status'             => 'required|string|max:50',
                'number_of_adults'   => 'required|integer|min:1',
                'number_of_children' => 'nullable|integer|min:0',
                'description'        => 'nullable|string',
            ]);

            // Set default value for number_of_children if not provided
            if (! isset($validated['number_of_children'])) {
                $validated['number_of_children'] = 0;
            }

            // Validate room availability first
            $availabilityError = $this->validateRoomAvailability($validated, null, 'store');

            // Return early if validation fails
            if ($availabilityError) {
                return $availabilityError;
            }

            $validated['created_by'] = Auth::id();
            $validated['updated_by'] = Auth::id();

            DB::beginTransaction();

            $roomBooking = RoomBooking::create($validated);

            $this->logActivity(
                'room_booking_created',
                "Room booking for room ID {$validated['room_id']} created.",
                ['room_booking_id' => $roomBooking->id]
            );

            // Load the related room and user information for the email
            $roomBooking->load(['room', 'createdBy']);

            // Get all System Admin users
            $systemAdmins = User::role('System Admin')->get();

            // Send email notification to all System Admins
            foreach ($systemAdmins as $admin) {
                try {
                    Mail::send('emails.room-bookings.admin_notification', [
                        'admin'       => $admin,
                        'roomBooking' => $roomBooking,
                        'room'        => $roomBooking->room,
                        'client'      => $roomBooking->createdBy,
                    ], function ($message) use ($admin, $roomBooking) {
                        $message->to($admin->email)
                            ->subject("New Room Booking: {$roomBooking->room->name} - Mingo Hotel Kayunga");
                    });
                } catch (\Exception $e) {
                    $this->logActivity(
                        'email_send_failed',
                        "Failed to send room booking notification email to admin: {$admin->email}. Error: {$e->getMessage()}",
                        [
                            'room_booking_id' => $roomBooking->id,
                            'admin_id'        => $admin->id,
                            'error_line'      => $e->getLine(),
                            'user_id'         => Auth::id(),
                        ]
                    );
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Room booking created successfully',
                'data'    => $roomBooking,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            $this->logActivity(
                'room_booking_create_failed',
                "Failed to create room booking. Error: {$e->getMessage()}",
                [
                    'error_line' => $e->getLine(),
                    'user_id'    => Auth::id(),
                ]
            );

            return response()->json([
                'message' => 'An error occurred while creating room booking.',
                'error'   => $e->getMessage(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $roomBooking = RoomBooking::findOrFail($id);
            $oldStatus   = $roomBooking->status; // Store the old status for comparison

            $validated = $request->validate([
                'room_id'            => 'sometimes|exists:rooms,id',
                'check_in'           => 'sometimes|date|before_or_equal:check_out',
                'check_out'          => 'sometimes|date|after_or_equal:check_in',
                'status'             => 'required|string|max:50',
                'number_of_adults'   => 'sometimes|integer|min:1',
                'number_of_children' => 'nullable|integer|min:0',
                'description'        => 'nullable|string',
            ]);

            $validated['updated_by'] = Auth::id();

            // Prepare complete data for availability check by merging existing data with updates
            $validationData = array_merge([
                'room_id'            => $roomBooking->room_id,
                'check_in'           => $roomBooking->check_in,
                'check_out'          => $roomBooking->check_out,
                'number_of_adults'   => $roomBooking->number_of_adults,
                'number_of_children' => $roomBooking->number_of_children,
            ], $validated);

            // Validate room availability
            $availabilityError = $this->validateRoomAvailability($validationData, $id, 'update');

            // Return early if validation fails
            if ($availabilityError) {
                return $availabilityError;
            }

            DB::beginTransaction();

            $roomBooking->update($validated);

            // Update the room's booked status based on booking status
            $room = Room::findOrFail($roomBooking->room_id);

            // Update the room's booked status if booking is accepted
            if ($validated['status'] === 'accepted' && $oldStatus === 'new') {
                $room->update(['booked' => true]);

                // Log room status update
                $this->logActivity(
                    'room_status_updated',
                    "Room ID {$room->id} marked as booked due to accepted booking.",
                    ['room_id' => $room->id, 'booking_id' => $roomBooking->id]
                );
            } else {
                $room->update(['booked' => false]);

                // Log room status reversal
                $this->logActivity(
                    'room_status_updated',
                    "Room ID {$room->id} unbooked due to booking status change.",
                    ['room_id' => $room->id, 'booking_id' => $roomBooking->id]
                );
            }

            $this->logActivity(
                'room_booking_updated',
                "Room booking ID {$roomBooking->id} updated.",
                ['room_booking_id' => $roomBooking->id]
            );

            // Load the related room and user information for the email
            $roomBooking->load(['room', 'createdBy', 'updatedBy']);

            // Send status change notification to the booking creator
            if ($validated['status'] === 'accepted' && $oldStatus === 'new') {
                try {
                    Mail::send('emails.room-bookings.booking_accepted', [
                        'roomBooking' => $roomBooking,
                        'room'        => $roomBooking->room,
                        'client'      => $roomBooking->createdBy,
                    ], function ($message) use ($roomBooking) {
                        $message->to($roomBooking->createdBy->email)
                            ->subject("Booking Confirmed: {$roomBooking->room->name} - Mingo Hotel Kayunga");
                    });

                    $this->logActivity(
                        'email_sent',
                        "Booking acceptance notification sent to {$roomBooking->createdBy->email}.",
                        ['room_booking_id' => $roomBooking->id]
                    );
                } catch (\Exception $e) {
                    $this->logActivity(
                        'email_send_failed',
                        "Failed to send booking acceptance notification to {$roomBooking->createdBy->email}. Error: {$e->getMessage()}",
                        [
                            'room_booking_id' => $roomBooking->id,
                            'error_line'      => $e->getLine(),
                            'user_id'         => Auth::id(),
                        ]
                    );
                }
            } elseif ($validated['status'] === 'rejected' && $oldStatus === 'new') {
                try {
                    Mail::send('emails.room-bookings.booking_rejected', [
                        'roomBooking' => $roomBooking,
                        'room'        => $roomBooking->room,
                        'client'      => $roomBooking->createdBy,
                    ], function ($message) use ($roomBooking) {
                        $message->to($roomBooking->createdBy->email)
                            ->subject("Booking Not Available: {$roomBooking->room->name} - Mingo Hotel Kayunga");
                    });

                    $this->logActivity(
                        'email_sent',
                        "Booking rejection notification sent to {$roomBooking->createdBy->email}.",
                        ['room_booking_id' => $roomBooking->id]
                    );
                } catch (\Exception $e) {
                    $this->logActivity(
                        'email_send_failed',
                        "Failed to send booking rejection notification to {$roomBooking->createdBy->email}. Error: {$e->getMessage()}",
                        [
                            'room_booking_id' => $roomBooking->id,
                            'error_line'      => $e->getLine(),
                            'user_id'         => Auth::id(),
                        ]
                    );
                }
            }

            // Get all System Admin users
            $systemAdmins = User::role('System Admin')->get();

            // Send email notification to all System Admins
            foreach ($systemAdmins as $admin) {
                try {
                    Mail::send('emails.room-bookings.admin_update_notification', [
                        'admin'       => $admin,
                        'roomBooking' => $roomBooking,
                        'room'        => $roomBooking->room,
                        'client'      => $roomBooking->createdBy,
                        'updatedBy'   => $roomBooking->updatedBy,
                    ], function ($message) use ($admin, $roomBooking) {
                        $message->to($admin->email)
                            ->subject("Room Booking Updated: {$roomBooking->room->name} - Mingo Hotel Kayunga");
                    });
                } catch (\Exception $e) {
                    $this->logActivity(
                        'email_send_failed',
                        "Failed to send room booking update notification email to admin: {$admin->email}. Error: {$e->getMessage()}",
                        [
                            'room_booking_id' => $roomBooking->id,
                            'admin_id'        => $admin->id,
                            'error_line'      => $e->getLine(),
                            'user_id'         => Auth::id(),
                        ]
                    );
                }
            }

            DB::commit();

            return response()->json(['message' => 'Room booking updated successfully']);
        } catch (\Exception $e) {
            DB::rollBack();

            $this->logActivity(
                'room_booking_update_failed',
                "Failed to update room booking ID {$id}. Error: {$e->getMessage()}",
                [
                    'error_line'      => $e->getLine(),
                    'user_id'         => Auth::id(),
                    'room_booking_id' => $id,
                ]
            );

            return response()->json([
                'message' => 'An error occurred while updating room booking.',
                'error'   => $e->getMessage(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        $roomBooking = RoomBooking::findOrFail($id);
        $roomBooking->delete();

        $this->logActivity(
            'room_booking_deleted',
            "Room booking ID {$id} deleted.",
            ['room_booking_id' => $id]
        );

        return response()->json(['message' => 'Room booking deleted successfully']);
    }

    public function bulkDestroy(Request $request)
    {
        $itemsToDelete = $request->input('itemsToDelete');

        if (! is_array($itemsToDelete) || empty($itemsToDelete)) {
            return response()->json(['message' => 'Invalid or empty booking data'], 400);
        }

        $bookingIds = array_column($itemsToDelete, 'id');

        if (empty($bookingIds)) {
            return response()->json(['message' => 'No valid booking IDs found'], 400);
        }

        $bookings = RoomBooking::whereIn('id', $bookingIds)->get();

        if ($bookings->isEmpty()) {
            return response()->json(['message' => 'No matching bookings found'], 404);
        }

        $deletedBookingIds = $bookings->pluck('id')->toArray();

        RoomBooking::whereIn('id', $bookingIds)->delete();

        $this->logActivity(
            'room_bookings_bulk_deleted',
            'Room bookings deleted: ' . implode(', ', $deletedBookingIds),
            ['room_booking_ids' => $deletedBookingIds]
        );

        return response()->json(['message' => 'Room bookings deleted successfully']);
    }
}
