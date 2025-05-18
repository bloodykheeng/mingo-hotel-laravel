<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Traits\Loggable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FaqController extends Controller
{
    use Loggable;

    /**
     * Display a listing of FAQs.
     */
    public function index(Request $request)
    {
        $query = Faq::with(["createdBy", "updatedBy"]);

        // Apply filters
        if ($request->has('search')) {
            $searchTerm = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('question', 'like', $searchTerm)
                    ->orWhere('answer', 'like', $searchTerm);
            });
        }

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->boolean('paginate')) {
            $perPage = $request->get('rowsPerPage', 5);
            $data    = $query->latest()->paginate($perPage);
            return response()->json(['data' => $data]);
        }

        $data = $query->latest()->get();
        return response()->json(['data' => $data]);
    }

    /**
     * Store a newly created FAQ.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'question' => 'required|string|max:500|unique:faqs,question',
            'answer'   => 'required|string',
            'status'   => 'required|string|max:50',
        ]);

        $validated['created_by'] = Auth::id();
        $validated['updated_by'] = Auth::id();

        $faq = Faq::create($validated);

        $this->logActivity('faq_created', "FAQ '{$faq->question}' was created.", ['faq_id' => $faq->id]);

        return response()->json(['message' => 'FAQ created successfully', 'data' => $faq], 201);
    }

    /**
     * Display the specified FAQ.
     */
    public function show($id)
    {
        $faq = Faq::findOrFail($id);
        return response()->json($faq);
    }

    /**
     * Update the specified FAQ.
     */
    public function update(Request $request, $id)
    {
        $faq = Faq::findOrFail($id);

        $validated = $request->validate([
            'question' => 'sometimes|string|max:500|unique:faqs,question,' . $faq->id,
            'answer'   => 'sometimes|string',
            'status'   => 'sometimes|string|max:50',
        ]);

        $validated['updated_by'] = Auth::id();
        $faq->update($validated);

        $this->logActivity('faq_updated', "FAQ '{$faq->question}' was updated.", ['faq_id' => $faq->id]);

        return response()->json(['message' => 'FAQ updated successfully', 'data' => $faq]);
    }

    /**
     * Remove the specified FAQ.
     */
    public function destroy($id)
    {
        $faq = Faq::findOrFail($id);
        $faq->delete();

        $this->logActivity('faq_deleted', "FAQ '{$faq->question}' was deleted.", ['faq_id' => $faq->id]);

        return response()->json(['message' => 'FAQ deleted successfully']);
    }

    /**
     * Bulk delete FAQs.
     */
    public function bulkDestroy(Request $request)
    {
        $itemsToDelete = $request->input('itemsToDelete');

        if (! is_array($itemsToDelete) || empty($itemsToDelete)) {
            return response()->json(['message' => 'Invalid or empty FAQs data'], 400);
        }

        // Extract only the `id` values from the array of objects
        $faqIds = array_column($itemsToDelete, 'id');

        if (empty($faqIds)) {
            return response()->json(['message' => 'No valid FAQ IDs found'], 400);
        }

        $faqs = Faq::whereIn('id', $faqIds)->get();

        if ($faqs->isEmpty()) {
            return response()->json(['message' => 'No matching FAQs found'], 404);
        }

        $deletedFaqQuestions = $faqs->pluck('question')->toArray();
        Faq::whereIn('id', $faqIds)->delete();

        $this->logActivity('faqs_bulk_deleted', "FAQs deleted: " . implode(', ', $deletedFaqQuestions), ['faq_ids' => $faqIds]);

        return response()->json(['message' => 'FAQs deleted successfully']);
    }
}
