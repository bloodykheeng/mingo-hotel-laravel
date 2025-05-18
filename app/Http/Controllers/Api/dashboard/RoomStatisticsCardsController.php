<?php
namespace App\Http\Controllers\API\dashboard;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class RoomStatisticsCardsController extends Controller
{
    /**
     * Apply filters based on authenticated user's role
     */
    private function applyFilters($query)
    {
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

        return $query;
    }

    /**
     * Apply filters for room bookings based on authenticated user's role
     */
    private function applyBookingFilters($query)
    {
        // Get authenticated user
        $authUser = Auth::user();
        if (isset($authUser)) {
            // Find the user with their relationships to determine role
            $user = User::find($authUser->id);
            if (isset($user)) {
                $userRole = $user->roles->pluck('name')->last();

                // If user is Client, only show bookings they created
                if ($userRole === 'Client') {
                    $query->where('created_by', $user->id);
                }
                // System Admin sees all bookings
            }
        }

        return $query;
    }

    /**
     * Count total rooms
     */
    private function countTotalRooms($roomsQuery)
    {
        $query = clone $roomsQuery;
        $count = $query->count();

        return [
            'title'     => 'Total Rooms',
            'link'      => '/dashboard/rooms',
            'icon'      => 'pi-home',
            'bgColor'   => 'bg-blue-600',
            'textColor' => 'text-white',
            'message'   => 'Total number of rooms',
            'count'     => $count,
        ];
    }

    /**
     * Count free rooms (not booked)
     */
    private function countFreeRooms($roomsQuery)
    {
        $query = clone $roomsQuery;
        $count = $query->where('booked', false)->count();

        return [
            'title'     => 'Free Rooms',
            'link'      => '/dashboard/rooms',
            'icon'      => 'pi-check-circle',
            'bgColor'   => 'bg-green-600',
            'textColor' => 'text-white',
            'message'   => 'Available rooms for booking',
            'count'     => $count,
        ];
    }

    /**
     * Count booked rooms
     */
    private function countBookedRooms($roomsQuery)
    {
        $query = clone $roomsQuery;
        $count = $query->where('booked', true)->count();

        return [
            'title'     => 'Booked Rooms',
            'link'      => '/dashboard/rooms',
            'icon'      => 'pi-calendar',
            'bgColor'   => 'bg-orange-500',
            'textColor' => 'text-white',
            'message'   => 'Currently booked rooms',
            'count'     => $count,
        ];
    }

    /**
     * Count bookings made by authenticated user
     */
    private function countUserBookings($bookingsQuery)
    {
        $userId = Auth::id();
        $query  = clone $bookingsQuery;
        $count  = $query->where('created_by', $userId)->count();

        return [
            'title'     => 'Your Bookings',
            'link'      => '/dashboard/room-bookings',
            'icon'      => 'pi-user',
            'bgColor'   => 'bg-purple-500',
            'textColor' => 'text-white',
            'message'   => 'Bookings you have made',
            'count'     => $count,
        ];
    }

    /**
     * Count total bookings
     */
    private function countTotalBookings($bookingsQuery)
    {
        $query = clone $bookingsQuery;
        $count = $query->count();

        return [
            'title'     => 'Total Bookings',
            'link'      => '/dashboard/room-bookings',
            'icon'      => 'pi-list',
            'bgColor'   => 'bg-blue-500',
            'textColor' => 'text-white',
            'message'   => 'Total number of bookings',
            'count'     => $count,
        ];
    }

    /**
     * Count new bookings (with status 'Pending')
     */
    private function countNewBookings($bookingsQuery)
    {
        $query = clone $bookingsQuery;
        $count = $query->where('status', 'Pending')->count();

        return [
            'title'     => 'New Bookings',
            'link'      => '/dashboard/room-bookings',
            'icon'      => 'pi-bell',
            'bgColor'   => 'bg-yellow-500',
            'textColor' => 'text-white',
            'message'   => 'Newly submitted booking requests',
            'count'     => $count,
        ];
    }

    /**
     * Count accepted bookings
     */
    private function countAcceptedBookings($bookingsQuery)
    {
        $query = clone $bookingsQuery;
        $count = $query->where('status', 'Accepted')->count();

        return [
            'title'     => 'Accepted Bookings',
            'link'      => '/dashboard/room-bookings',
            'icon'      => 'pi-check',
            'bgColor'   => 'bg-green-500',
            'textColor' => 'text-white',
            'message'   => 'Bookings that have been accepted',
            'count'     => $count,
        ];
    }

    /**
     * Count declined bookings
     */
    private function countDeclinedBookings($bookingsQuery)
    {
        $query = clone $bookingsQuery;
        $count = $query->where('status', 'Declined')->count();

        return [
            'title'     => 'Declined Bookings',
            'link'      => '/dashboard/room-bookings',
            'icon'      => 'pi-times',
            'bgColor'   => 'bg-red-500',
            'textColor' => 'text-white',
            'message'   => 'Bookings that have been declined',
            'count'     => $count,
        ];
    }

    /**
     * Get all statistics cards
     */
    public function getAllStatisticsCards()
    {
        $userId   = Auth::id();
        $user     = User::find($userId);
        $userRole = $user ? $user->roles->pluck('name')->last() : null;

        // Initialize base queries that will be reused
        $roomsBaseQuery = Room::query();
        // $roomsBaseQuery = $this->applyFilters($roomsBaseQuery);

        $bookingsBaseQuery = RoomBooking::query();
        $bookingsBaseQuery = $this->applyBookingFilters($bookingsBaseQuery);

        $counts = [];

        // Common statistics for all roles
        $counts[] = $this->countTotalRooms($roomsBaseQuery);
        $counts[] = $this->countFreeRooms($roomsBaseQuery);
        $counts[] = $this->countBookedRooms($roomsBaseQuery);

        // If the user is a Client, show their bookings
        // For System Admin, this will show all bookings they personally made
        $counts[] = $this->countUserBookings($bookingsBaseQuery);

        // All users can see total bookings and status-based counts
        // For Clients, these will be filtered to only show their bookings
        // For System Admin, these will show all bookings
        $counts[] = $this->countTotalBookings($bookingsBaseQuery);
        $counts[] = $this->countNewBookings($bookingsBaseQuery);
        $counts[] = $this->countAcceptedBookings($bookingsBaseQuery);
        $counts[] = $this->countDeclinedBookings($bookingsBaseQuery);

        // Return all counts as a single JSON response
        return response()->json($counts);
    }
}
