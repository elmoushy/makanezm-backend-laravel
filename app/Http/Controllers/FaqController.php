<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Models\FaqQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FaqController extends Controller
{
    /**
     * Get all active FAQs with their questions (Public endpoint).
     */
    public function index()
    {
        $faqs = Faq::with(['questions' => function ($query) {
            $query->active()->ordered();
        }])
            ->active()
            ->ordered()
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'faqs' => $faqs,
            ],
        ]);
    }

    /**
     * Get all FAQs including inactive ones with all questions (Admin only).
     */
    public function adminIndex()
    {
        $faqs = Faq::with(['questions' => function ($query) {
            $query->ordered();
        }])
            ->ordered()
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'faqs' => $faqs,
            ],
        ]);
    }

    /**
     * Store a new FAQ with questions (Admin only).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:1024',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'questions' => 'required|array|min:1',
            'questions.*.question_ar' => 'required|string|max:1000',
            'questions.*.question_en' => 'required|string|max:1000',
            'questions.*.answer_ar' => 'required|string',
            'questions.*.answer_en' => 'required|string',
            'questions.*.order' => 'nullable|integer|min:0',
            'questions.*.is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Handle image upload
        $imageBase64 = null;
        if ($request->hasFile('image')) {
            $imageData = file_get_contents($request->file('image')->getRealPath());
            $mimeType = $request->file('image')->getMimeType();
            $imageBase64 = 'data:'.$mimeType.';base64,'.base64_encode($imageData);
        }

        DB::beginTransaction();
        try {
            $maxOrder = Faq::max('order') ?? 0;

            // Create the FAQ (parent with image)
            $faq = Faq::create([
                'image' => $imageBase64,
                'order' => $request->order ?? ($maxOrder + 1),
                'is_active' => $request->is_active ?? true,
            ]);

            // Create questions for this FAQ
            foreach ($request->questions as $index => $questionData) {
                FaqQuestion::create([
                    'faq_id' => $faq->id,
                    'question_ar' => $questionData['question_ar'],
                    'question_en' => $questionData['question_en'],
                    'answer_ar' => $questionData['answer_ar'],
                    'answer_en' => $questionData['answer_en'],
                    'order' => $questionData['order'] ?? ($index + 1),
                    'is_active' => $questionData['is_active'] ?? true,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'FAQ created successfully with '.count($request->questions).' question(s)',
                'data' => [
                    'faq' => $faq->load('questions'),
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create FAQ',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing FAQ (Admin only).
     */
    public function update(Request $request, Faq $faq)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:1024',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $updateData = [];

        // Handle image upload
        if ($request->hasFile('image')) {
            $imageData = file_get_contents($request->file('image')->getRealPath());
            $mimeType = $request->file('image')->getMimeType();
            $updateData['image'] = 'data:'.$mimeType.';base64,'.base64_encode($imageData);
        }

        if ($request->has('order')) {
            $updateData['order'] = $request->order;
        }

        if ($request->has('is_active')) {
            $updateData['is_active'] = $request->is_active;
        }

        $faq->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'FAQ updated successfully',
            'data' => [
                'faq' => $faq->fresh()->load('questions'),
            ],
        ]);
    }

    /**
     * Delete a FAQ and all its questions (Admin only).
     */
    public function destroy(Faq $faq)
    {
        $faq->delete(); // Cascade will delete questions

        return response()->json([
            'success' => true,
            'message' => 'FAQ deleted successfully',
        ]);
    }

    /**
     * Reorder FAQs (Admin only).
     */
    public function reorder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'faqs' => 'required|array',
            'faqs.*.id' => 'required|exists:faqs,id',
            'faqs.*.order' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        foreach ($request->faqs as $faqData) {
            Faq::where('id', $faqData['id'])->update(['order' => $faqData['order']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'FAQs reordered successfully',
        ]);
    }

    // ==================== FAQ Question Methods ====================

    /**
     * Add a question to an existing FAQ.
     */
    public function addQuestion(Request $request, Faq $faq)
    {
        $validator = Validator::make($request->all(), [
            'question_ar' => 'required|string|max:1000',
            'question_en' => 'required|string|max:1000',
            'answer_ar' => 'required|string',
            'answer_en' => 'required|string',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $maxOrder = $faq->questions()->max('order') ?? 0;

        $question = FaqQuestion::create([
            'faq_id' => $faq->id,
            'question_ar' => $request->question_ar,
            'question_en' => $request->question_en,
            'answer_ar' => $request->answer_ar,
            'answer_en' => $request->answer_en,
            'order' => $request->order ?? ($maxOrder + 1),
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Question added successfully',
            'data' => [
                'question' => $question,
            ],
        ], 201);
    }

    /**
     * Update a FAQ question.
     */
    public function updateQuestion(Request $request, FaqQuestion $question)
    {
        $validator = Validator::make($request->all(), [
            'question_ar' => 'sometimes|required|string|max:1000',
            'question_en' => 'sometimes|required|string|max:1000',
            'answer_ar' => 'sometimes|required|string',
            'answer_en' => 'sometimes|required|string',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $question->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Question updated successfully',
            'data' => [
                'question' => $question->fresh(),
            ],
        ]);
    }

    /**
     * Delete a FAQ question.
     */
    public function deleteQuestion(FaqQuestion $question)
    {
        $question->delete();

        return response()->json([
            'success' => true,
            'message' => 'Question deleted successfully',
        ]);
    }

    /**
     * Reorder questions within a FAQ.
     */
    public function reorderQuestions(Request $request, Faq $faq)
    {
        $validator = Validator::make($request->all(), [
            'questions' => 'required|array',
            'questions.*.id' => 'required|exists:faq_questions,id',
            'questions.*.order' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        foreach ($request->questions as $questionData) {
            FaqQuestion::where('id', $questionData['id'])
                ->where('faq_id', $faq->id)
                ->update(['order' => $questionData['order']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Questions reordered successfully',
        ]);
    }
}
