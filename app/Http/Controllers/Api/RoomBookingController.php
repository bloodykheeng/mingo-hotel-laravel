<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Traits\Loggable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RoomBookingController extends Controller
{
    use Loggable;

    public function index(Request $request)
    {
        $query = RoomBooking::with(['room', 'createdBy', 'updatedBy']);

        // Filter by room if provided
        if ($request->has('room_id')) {
            $query->where('room_id', $request->query('room_id'));
        }

        // Filter by date range if provided
        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = Carbon::parse($request->query('start_date'));
            $endDate   = Carbon::parse($request->query('end_date'));

            $query->where(function (Builder $query) use ($startDate, $endDate) {
                // Find bookings that overlap with the requested date range
                $query->where(function (Builder $subQuery) use ($startDate, $endDate) {
                    $subQuery->where('check_in', '>=', $startDate)
                        ->where('check_in', '<=', $endDate);
                })->orWhere(function (Builder $subQuery) use ($startDate, $endDate) {
                    $subQuery->where('check_out', '>=', $startDate)
                        ->where('check_out', '<=', $endDate);
                })->orWhere(function (Builder $subQuery) use ($startDate, $endDate) {
                    $subQuery->where('check_in', '<=', $startDate)
                        ->where('check_out', '>=', $endDate);
                });
            });
        }

        // Sort by check_in date by default
        $query->orderBy('check_in', 'asc');

        // Return paginated results if pagination is requested
        if ($request->boolean('paginate')) {
            $perPage = $request->get('rowsPerPage', 10);
            return response()->json(['data' => $query->paginate($perPage)]);
        }

        // Otherwise return all results
        return response()->json(['data' => $query->get()]);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'room_id'            => 'required|exists:rooms,id',
                'check_in'           => 'required|date',
                'check_out'          => 'required|date|after:check_in',
                'number_of_adults'   => 'required|integer|min:1',
                'number_of_children' => 'nullable|integer|min:0',
                'description'        => 'nullable|string',
            ]);

            // Parse dates for overlap checking
            $checkIn  = Carbon::parse($validated['check_in']);
            $checkOut = Carbon::parse($validated['check_out']);

            // Check if room is already booked for the requested dates
            $roomBookingConflict = RoomBooking::where('room_id', $validated['room_id'])
                ->where(function (Builder $query) use ($checkIn, $checkOut) {
                    $query->where(function (Builder $subQuery) use ($checkIn, $checkOut) {
                        $subQuery->where('check_in', '<=', $checkIn)
                            ->where('check_out', '>', $checkIn);
                    })->orWhere(function (Builder $subQuery) use ($checkIn, $checkOut) {
                        $subQuery->where('check_in', '<', $checkOut)
                            ->where('check_out', '>=', $checkOut);
                    })->orWhere(function (Builder $subQuery) use ($checkIn, $checkOut) {
                        $subQuery->where('check_in', '>=', $checkIn)
                            ->where('check_out', '<=', $checkOut);
                    });
                })
                ->exists();

            if ($roomBookingConflict) {
                return response()->json([
                    'message' => 'Room is already booked for the selected dates',
                    'errors'  => [
                        'check_in'  => ['Room is not available for these dates'],
                        'check_out' => ['Room is not available for these dates'],
                    ],
                ], 422);
            }

            // Check room capacity
            $room        = Room::findOrFail($validated['room_id']);
            $totalGuests = $validated['number_of_adults'] + ($validated['number_of_children'] ?? 0);
            if ($totalGuests > $room->capacity) {
                return response()->json([
                    'message' => 'Number of guests exceeds room capacity',
                    'errors'  => [
                        'number_of_adults'   => ['Total number of guests exceeds room capacity of ' . $room->capacity],
                        'number_of_children' => ['Total number of guests exceeds room capacity of ' . $room->capacity],
                    ],
                ], 422);
            }

            $validated['created_by'] = Auth::id();
            $validated['updated_by'] = Auth::id();

            DB::beginTransaction();

            $booking = RoomBooking::create($validated);

            $this->logActivity(
                'room_booking_created',
                "Room booking created for room #{$booking->room_id} from {$booking->check_in->format('Y-m-d')} to {$booking->check_out->format('Y-m-d')}",
                [
                    'booking_id' => $booking->id,
                    'room_id'    => $booking->room_id,
                ]
            );

            DB::commit();

            return response()->json([
                'message' => 'Room booking created successfully',
                'data'    => $booking->load('room'),
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

    public function show($id)
    {
        $booking = RoomBooking::with(['room', 'createdBy', 'updatedBy'])->findOrFail($id);
        return response()->json($booking);
    }

    public function update(Request $request, $id)
    {
        try {
            $booking = RoomBooking::findOrFail($id);

            $validated = $request->validate([
                'room_id'            => 'sometimes|exists:rooms,id',
                'check_in'           => 'sometimes|date',
                'check_out'          => 'sometimes|date|after:check_in',
                'number_of_adults'   => 'sometimes|integer|min:1',
                'number_of_children' => 'nullable|integer|min:0',
                'description'        => 'nullable|string',
            ]);

            // Use existing values if not provided
            $roomId           = $validated['room_id'] ?? $booking->room_id;
            $checkIn          = isset($validated['check_in']) ? Carbon::parse($validated['check_in']) : $booking->check_in;
            $checkOut         = isset($validated['check_out']) ? Carbon::parse($validated['check_out']) : $booking->check_out;
            $numberOfAdults   = $validated['number_of_adults'] ?? $booking->number_of_adults;
            $numberOfChildren = $validated['number_of_children'] ?? $booking->number_of_children;

            // Check if room is already booked for the requested dates (excluding current booking)
            $roomBookingConflict = RoomBooking::where('room_id', $roomId)
                ->where('id', '!=', $id)
                ->where(function (Builder $query) use ($checkIn, $checkOut) {
                    $query->where(function (Builder $subQuery) use ($checkIn, $checkOut) {
                        $subQuery->where('check_in', '<=', $checkIn)
                            ->where('check_out', '>', $checkIn);
                    })->orWhere(function (Builder $subQuery) use ($checkIn, $checkOut) {
                        $subQuery->where('check_in', '<', $checkOut)
                            ->where('check_out', '>=', $checkOut);
                    })->orWhere(function (Builder $subQuery) use ($checkIn, $checkOut) {
                        $subQuery->where('check_in', '>=', $checkIn)
                            ->where('check_out', '<=', $checkOut);
                    });
                })
                ->exists();

            if ($roomBookingConflict) {
                return response()->json([
                    'message' => 'Room is already booked for the selected dates',
                    'errors'  => [
                        'check_in'  => ['Room is not available for these dates'],
                        'check_out' => ['Room is not available for these dates'],
                    ],
                ], 422);
            }

            // Check room capacity
            $room        = Room::findOrFail($roomId);
            $totalGuests = $numberOfAdults + $numberOfChildren;
            if ($totalGuests > $room->capacity) {
                return response()->json([
                    'message' => 'Number of guests exceeds room capacity',
                    'errors'  => [
                        'number_of_adults'   => ['Total number of guests exceeds room capacity of ' . $room->capacity],
                        'number_of_children' => ['Total number of guests exceeds room capacity of ' . $room->capacity],
                    ],
                ], 422);
            }

            $validated['updated_by'] = Auth::id();

            DB::beginTransaction();

            $booking->update($validated);

            $this->logActivity(
                'room_booking_updated',
                "Room booking #{$booking->id} updated for room #{$booking->room_id} from {$booking->check_in->format('Y-m-d')} to {$booking->check_out->format('Y-m-d')}",
                [
                    'booking_id' => $booking->id,
                    'room_id'    => $booking->room_id,
                ]
            );

            DB::commit();

            return response()->json([
                'message' => 'Room booking updated successfully',
                'data'    => $booking->fresh()->load('room'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            $this->logActivity(
                'room_booking_update_failed',
                "Failed to update room booking with ID {$id}. Error: {$e->getMessage()}",
                [
                    'error_line' => $e->getLine(),
                    'booking_id' => $id,
                    'user_id'    => Auth::id(),
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
        try {
            $booking = RoomBooking::findOrFail($id);

            // Check if booking is in the past
            if ($booking->check_in->isPast() && now()->greaterThan($booking->check_in)) {
                return response()->json([
                    'message' => 'Cannot delete bookings that have already started or completed',
                ], 422);
            }

            DB::beginTransaction();

            $bookingDetails = [
                'id'        => $booking->id,
                'room_id'   => $booking->room_id,
                'check_in'  => $booking->check_in->format('Y-m-d'),
                'check_out' => $booking->check_out->format('Y-m-d'),
            ];

            $booking->delete();

            $this->logActivity(
                'room_booking_deleted',
                "Room booking #{$bookingDetails['id']} for room #{$bookingDetails['room_id']} from {$bookingDetails['check_in']} to {$bookingDetails['check_out']} was deleted",
                $bookingDetails
            );

            DB::commit();

            return response()->json(['message' => 'Room booking deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();

            $this->logActivity(
                'room_booking_delete_failed',
                "Failed to delete room booking with ID {$id}. Error: {$e->getMessage()}",
                [
                    'error_line' => $e->getLine(),
                    'booking_id' => $id,
                    'user_id'    => Auth::id(),
                ]
            );

            return response()->json([
                'message' => 'An error occurred while deleting room booking.',
                'error'   => $e->getMessage(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    public function bulkDestroy(Request $request)
    {
        try {
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

            // Check if any bookings are in the past
            $pastBookings = [];
            foreach ($bookings as $booking) {
                if ($booking->check_in->isPast() && now()->greaterThan($booking->check_in)) {
                    $pastBookings[] = "Booking #{$booking->id} for {$booking->check_in->format('Y-m-d')}";
                }
            }

            if (! empty($pastBookings)) {
                return response()->json([
                    'message' => 'Cannot delete bookings that have already started or completed: ' . implode(', ', $pastBookings),
                ], 422);
            }

            DB::beginTransaction();

            // Track deleted bookings for logging
            $deletedBookings = $bookings->map(function ($booking) {
                return [
                    'id'        => $booking->id,
                    'room_id'   => $booking->room_id,
                    'check_in'  => $booking->check_in->format('Y-m-d'),
                    'check_out' => $booking->check_out->format('Y-m-d'),
                ];
            })->toArray();

            RoomBooking::whereIn('id', $bookingIds)->delete();

            $bookingIdList = implode(', ', $bookingIds);
            $this->logActivity(
                'room_bookings_bulk_deleted',
                "Room bookings deleted: {$bookingIdList}",
                ['booking_ids' => $bookingIds, 'details' => $deletedBookings]
            );

            DB::commit();

            return response()->json(['message' => 'Room bookings deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();

            $this->logActivity(
                'room_bookings_bulk_delete_failed',
                "Failed to bulk delete room bookings. Error: {$e->getMessage()}",
                [
                    'error_line' => $e->getLine(),
                    'user_id'    => Auth::id(),
                ]
            );

            return response()->json([
                'message' => 'An error occurred while deleting room bookings.',
                'error'   => $e->getMessage(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Get availability for a specific room within a date range
     */
    public function checkRoomAvailability(Request $request)
    {
        $validated = $request->validate([
            'room_id'    => 'required|exists:rooms,id',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
        ]);

        $roomId    = $validated['room_id'];
        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate   = Carbon::parse($validated['end_date'])->endOfDay();

        // Get all bookings for this room within the date range
        $bookings = RoomBooking::where('room_id', $roomId)
            ->where(function (Builder $query) use ($startDate, $endDate) {
                $query->where(function (Builder $subQuery) use ($startDate, $endDate) {
                    $subQuery->where('check_in', '>=', $startDate)
                        ->where('check_in', '<=', $endDate);
                })->orWhere(function (Builder $subQuery) use ($startDate, $endDate) {
                    $subQuery->where('check_out', '>=', $startDate)
                        ->where('check_out', '<=', $endDate);
                })->orWhere(function (Builder $subQuery) use ($startDate, $endDate) {
                    $subQuery->where('check_in', '<=', $startDate)
                        ->where('check_out', '>=', $endDate);
                });
            })
            ->get(['id', 'check_in', 'check_out']);

        // Generate availability data
        $availabilityData = [];
        $current          = clone $startDate;

        while ($current <= $endDate) {
            $dateKey              = $current->format('Y-m-d');
            $isAvailable          = true;
            $conflictingBookingId = null;

            // Check if date is within any booking
            foreach ($bookings as $booking) {
                $bookingStart = Carbon::parse($booking->check_in)->startOfDay();
                $bookingEnd   = Carbon::parse($booking->check_out)->startOfDay();

                if ($current->greaterThanOrEqualTo($bookingStart) && $current->lessThan($bookingEnd)) {
                    $isAvailable          = false;
                    $conflictingBookingId = $booking->id;
                    break;
                }
            }

            $availabilityData[] = [
                'date'       => $dateKey,
                'available'  => $isAvailable,
                'booking_id' => $conflictingBookingId,
            ];

            $current->addDay();
        }

        return response()->json([
            'data' => [
                'room_id'      => $roomId,
                'start_date'   => $startDate->format('Y-m-d'),
                'end_date'     => $endDate->format('Y-m-d'),
                'availability' => $availabilityData,
            ],
        ]);
    }
}
